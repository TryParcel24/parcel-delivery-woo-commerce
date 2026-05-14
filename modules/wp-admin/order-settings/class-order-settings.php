<?php

/**
 * Admin order settings — HPOS compatible.
 */
class OrderSettings
{
    protected static $_instance = null;
    public $glSettings = array();

    public static function get_instance()
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function __construct()
    {
        $this->glSettings = $this->get_general_option();
        $this->setup_hooks();
    }

    public function get_general_option()
    {
        return array();
    }

    /**
     * Returns true if HPOS (Custom Order Tables) is the authoritative storage.
     */
    public static function is_hpos_enabled()
    {
        if (class_exists('\Automattic\WooCommerce\Utilities\OrderUtil')) {
            return \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
        }
        return false;
    }

    public function setup_hooks()
    {
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_style_script'));

        // Meta box: support both legacy CPT and HPOS screens.
        add_action('add_meta_boxes', array($this, 'parcel_delivery_order_meta_box'));

        // AJAX
        add_action('wp_ajax_cancel_order_delivery_task', array($this, 'cancel_order_delivery_task'));
        add_action('wp_ajax_request_delivery_bulk_action', array($this, 'request_delivery_bulk_action'));

        // Order list top bar button — legacy CPT screen.
        add_action('manage_posts_extra_tablenav', array($this, 'admin_order_list_top_bar_button'), 20, 1);
        // Order list top bar button — HPOS screen.
        add_action('woocommerce_order_list_table_extra_tablenav', array($this, 'admin_order_list_top_bar_button_hpos'), 20, 2);

        add_filter('woocommerce_order_get_formatted_billing_address', array($this, 'filter_woocommerce_order_get_formatted_billing_address'), 10, 3);

        add_filter('woocommerce_admin_order_preview_get_order_details', array($this, 'admin_order_preview_add_order_id'), 10, 2);
        add_action('woocommerce_admin_order_preview_start', array($this, 'custom_order_preview_content'));

        // Columns — legacy CPT screen.
        add_filter('manage_edit-shop_order_columns', array($this, 'order_status_column'));
        add_action('manage_shop_order_posts_custom_column', array($this, 'order_status_column_content_legacy'), 10, 2);
        // Columns — HPOS screen.
        add_filter('manage_woocommerce_page_wc-orders_columns', array($this, 'order_status_column'));
        add_action('manage_woocommerce_page_wc-orders_custom_column', array($this, 'order_status_column_content_hpos'), 10, 2);

        // Scope the "Shipping method" → "Shipping rate" relabel to admin only, and
        // attach the filter late (at `admin_init`) so it doesn't run on the front-end
        // or during early bootstrap of every single request.
        add_action('admin_init', array($this, 'maybe_register_shipping_label_filter'));
        add_filter('woocommerce_admin_order_preview_get_order_details', array($this, 'admin_order_preview_add_custom_billing_data'), 10, 2);
    }

    /**
     * Render column content for legacy CPT shop_order screen.
     */
    public function order_status_column_content_legacy($column, $post_id = null)
    {
        if ($post_id === null) {
            global $post;
            $post_id = $post ? $post->ID : 0;
        }
        $this->render_delivery_status_column($column, $post_id);
    }

    /**
     * Render column content for HPOS orders screen.
     */
    public function order_status_column_content_hpos($column, $order)
    {
        $order_id = is_a($order, 'WC_Order') ? $order->get_id() : (int) $order;
        $this->render_delivery_status_column($column, $order_id);
    }

    protected function render_delivery_status_column($column, $order_id)
    {
        if ('delivery_status' !== $column || ! $order_id) {
            return;
        }
        try {
            $order = wc_get_order($order_id);
            if (! $order) {
                return;
            }

            $delivery_status = $order->get_meta('_delivery_task_status');
            if ($delivery_status == 1 && ! empty($delivery_status)) {
                $delivery_task_res = json_decode((string) $order->get_meta('_delivery_task_res'), true);
                if (empty($delivery_task_res['data']['taskRelation'])) {
                    echo '-';
                    return;
                }
                $response = $this->get_delivary_task_details_by_id($delivery_task_res['data']['taskRelation']);
                if (isset($response['status']) && $response['status'] == 202) {
                    $response = $this->get_delivary_task_details_by_id($delivery_task_res['data']['taskRelation']);
                }
                if (isset($response['status']) && $response['status'] == 200 && ! empty($response['data']['deliveries'])) {
                    $filteredArr = array_filter($response['data']['deliveries'], function ($v) use ($order_id) {
                        return isset($v['orderId']) && $v['orderId'] == '#' . $order_id;
                    });

                    if (! empty($filteredArr)) {
                        foreach ($filteredArr as $delivery) {
                            echo esc_html(isset($delivery['deliveryStatus']) ? $delivery['deliveryStatus'] : '-');
                        }
                    } else {
                        echo '-';
                    }
                } else {
                    echo '-';
                }
            } else {
                esc_html_e('Not Requested', 'parcel-delivery');
            }
        } catch (\Throwable $e) {
            echo '-';
            if (function_exists('wc_get_logger')) {
                wc_get_logger()->error('Parcel Delivery column render error: ' . $e->getMessage(), array('source' => 'parcel-delivery'));
            }
        }
    }

    public function order_status_column($columns)
    {
        $columns['delivery_status'] = __('Order Status', 'parcel-delivery');
        return $columns;
    }

    public function admin_order_preview_add_order_id($data, $order)
    {
        $post_id = $order->get_id();
        $disable_button = '';
        $delivery_status = $order->get_meta('_delivery_task_status');
        if ($delivery_status == 1 && ! empty($delivery_status)) {
            $delivery_api_status = $this->get_delivery_status_from_api_response($post_id);
            if ($delivery_api_status != 'Canceled') {
                $disable_button = 'disabled';
            }
        }
        $html = '<div class="wp-clearfix ck-delivery-popup-btn">
            <button type="submit" order_id="' . esc_attr($post_id) . '" name="request_single_delivery" style="height:32px;" class="button button-primary" ' . esc_attr($disable_button) . '>
                ' . esc_html__('Request Delivery', 'parcel-delivery') . '
            </button>
        </div>';
        $data['content'] = $html;
        return $data;
    }

    public function custom_order_preview_content($order)
    {
        echo "{{{data.content}}}";
    }

    public function enqueue_admin_style_script()
    {
        if ( ! $this->is_order_screen() ) {
            return;
        }

        wp_enqueue_style('magnific_popup_style', 'https://cdnjs.cloudflare.com/ajax/libs/magnific-popup.js/1.1.0/magnific-popup.min.css', array(), '1.1.0', 'all');
        wp_enqueue_style('order_settings_style', PARCEL_DELIVERY_DIR_URL . '/modules/wp-admin/order-settings/css/style.css', array(), PARCEL_DELIVERY_VERSION, 'all');

        wp_enqueue_script('magnific_popup_script', 'https://cdnjs.cloudflare.com/ajax/libs/magnific-popup.js/1.1.0/jquery.magnific-popup.min.js', array('jquery'), '1.1.0', true);
        wp_enqueue_script('order_settings_script', PARCEL_DELIVERY_DIR_URL . '/modules/wp-admin/order-settings/js/script.js', array('jquery'), PARCEL_DELIVERY_VERSION, true);

        wp_localize_script(
            'order_settings_script',
            'orderVar',
            array(
                'adminurl' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('pd_order_actions'),
            )
        );
    }

    /**
     * Detect if we are on a WC order list / edit screen (legacy CPT or HPOS).
     */
    protected function is_order_screen()
    {
        if ( ! is_admin() ) {
            return false;
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';
        if ( 'wc-orders' === $page ) {
            return true;
        }
        if ( function_exists('get_current_screen') ) {
            $screen = get_current_screen();
            if ( $screen ) {
                $id = (string) $screen->id;
                if ( 'edit-shop_order' === $id || 'shop_order' === $id ) {
                    return true;
                }
                // HPOS edit screen id is woocommerce_page_wc-orders.
                if ( false !== strpos( $id, 'wc-orders' ) ) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Register meta box for both legacy CPT (shop_order) and HPOS (wc_order) screens.
     */
    public function parcel_delivery_order_meta_box()
    {
        $screen = self::is_hpos_enabled()
            ? wc_get_page_screen_id('shop-order')
            : 'shop_order';

        add_meta_box(
            'parcel_delivery_box',
            __('Delivery Order', 'parcel-delivery'),
            array($this, 'parcel_delivery_order_meta_box_content'),
            $screen,
            'side',
            'high'
        );
    }

    /**
     * Meta box content. $post_or_order is a WP_Post on legacy CPT or a WC_Order on HPOS.
     */
    public function parcel_delivery_order_meta_box_content($post_or_order)
    {
        $order = is_a($post_or_order, 'WC_Order') ? $post_or_order : wc_get_order($post_or_order->ID);
        if (! $order) {
            return;
        }
        $post_id = $order->get_id();

        $delivery_status = $order->get_meta('_delivery_task_status');

        if ($delivery_status == 1 && ! empty($delivery_status)) {
            $delivery_task_res = json_decode((string) $order->get_meta('_delivery_task_res'), true);

            if (empty($delivery_task_res['data']['taskRelation'])) {
                esc_html_e('Delivery API is not responded.', 'parcel-delivery');
                return;
            }

            $response = $this->get_delivary_task_details_by_id($delivery_task_res['data']['taskRelation']);
            if (isset($response['status']) && $response['status'] == 202) {
                $response = $this->get_delivary_task_details_by_id($delivery_task_res['data']['taskRelation']);
            }

            $order->update_meta_data('_final_delivery_responce', wp_json_encode($response));
            $order->save();

            if (isset($response['status']) && $response['status'] == 200) {
                $filteredArr = array_filter($response['data']['deliveries'], function ($v) use ($post_id) {
                    return $v['orderId'] == '#' . $post_id;
                });
                if (! empty($filteredArr)) {
                    $delivery_status = '';
                    $delivery_id = '';
                    foreach ($filteredArr as $delivery) {
                        $delivery_status = $delivery['deliveryStatus'];
                        $delivery_id = $delivery['deliveryTaskID'];

                        echo "<p>" . esc_html__('Delivery Track ID:', 'parcel-delivery') . " <a href='javascript:void(0);'>#" . esc_html($delivery_id) . "</a></p>";
                        echo "<p>" . esc_html__('Delivery Status:', 'parcel-delivery') . " " . esc_html($delivery['deliveryStatus']) . "</p>";
                        echo "<p>" . esc_html__('Tracking Link:', 'parcel-delivery') . "<br/></p>";
                        echo "<p><input type='text' id='myInput' value='" . esc_attr($delivery['trackingLink']) . "' name='track_link' readonly><a class='button button-primary' href='javascript:void(0);' onclick='myFunction()'>" . esc_html__('Copy', 'parcel-delivery') . "</a><span class='tooltiptext' id='myTooltip'></span></p>";
                    }

                    if ($delivery_status != 'Canceled') {
                        echo "<div class='parcel_delivery_request_box'>
                              <a href='javascript:void(0);' data-id='" . esc_attr($post_id) . "' class='button button-primary delivery_cancel'>" . esc_html__('Cancel Delivery', 'parcel-delivery') . "</a>
                              <input type='hidden' name='delivery_task_relation' value='" . esc_attr($response['data']['taskRelation']) . "'>
                              <input type='hidden' name='delivery_task_id' value='" . esc_attr($delivery_id) . "'>
                              <p class='alrt_msg'></p>
                              </div>";

                        echo "<div id='parcel-delivery-cancel-popup' class='white-popup mfp-hide'><h3>" . esc_html__('Cancel Delivery', 'parcel-delivery') . "</h3><p>" . esc_html__('Are you sure you want to cancel this delivery task? The WooCommerce order will not be cancelled.', 'parcel-delivery') . "</p><div class='cancel-delivery-action'><a href='javascript:void(0);' class='popup-btn cancel_yes_btn'>" . esc_html__('Yes', 'parcel-delivery') . "</a><a href='javascript:void(0);' class='popup-btn cancel_no_btn'>" . esc_html__('No', 'parcel-delivery') . "</a></div></div>";
                        echo "<div id='parcel-delivery-cancel-popup-msg' class='white-popup mfp-hide'><h3>" . esc_html__('Delivery cancelled', 'parcel-delivery') . "</h3><p>" . esc_html__('The delivery task has been successfully cancelled.', 'parcel-delivery') . "</p><div class='cancel-delivery-action'><a href='javascript:void(0);' class='popup-btn cancel_ok_btn'>" . esc_html__('OK', 'parcel-delivery') . "</a></div></div>";
                    }

                    if ($delivery_status == 'Canceled') {
                        echo "<div class='parcel_delivery_request_box'><a href='javascript:void(0);' data-id='" . esc_attr($post_id) . "' class='button button-primary delivery_request' data-text='" . esc_attr__('Request a Delivery Again', 'parcel-delivery') . "'>" . esc_html__('Request a Delivery Again', 'parcel-delivery') . "</a><p class='alrt_msg'></p></div>";
                    }
                }
            } else {
                esc_html_e('Delivery API is not responded.', 'parcel-delivery');
            }
        } else {
            echo "<div class='parcel_delivery_request_box'><a href='javascript:void(0);' data-id='" . esc_attr($post_id) . "' class='button button-primary delivery_request' data-text=''>" . esc_html__('Request a Delivery', 'parcel-delivery') . "</a><p class='alrt_msg'></p></div>";
        }
    }

    public function get_delivary_task_details_by_id($id)
    {
        $body = '';
        $auth_response = global_features_object()->pd_authentication();

        if ($auth_response['status']) {
            $access_token = $auth_response['access_token'];
            $token_type = $auth_response['token_type'];
            $auth = $token_type . ' ' . $access_token;
            $url = global_features_object()->glSettings['api_url'] . 'task/' . $id;

            return global_features_object()->pd_curl_request($url, 'GET', $body, $auth);
        }
        return array('status' => 0, 'message' => 'auth failed');
    }

    public function cancel_order_delivery_task()
    {
        check_ajax_referer('pd_order_actions', 'nonce');
        if (! current_user_can('manage_woocommerce')) {
            wp_send_json(array('status' => 0, 'message' => 'forbidden'), 403);
        }

        $data = array(
            'order_id'      => isset($_POST['order_id']) ? absint($_POST['order_id']) : 0,
            'task_relation' => isset($_POST['task_relation']) ? sanitize_text_field(wp_unslash($_POST['task_relation'])) : '',
            'delivery_id'   => isset($_POST['delivery_id']) ? sanitize_text_field(wp_unslash($_POST['delivery_id'])) : '',
        );

        $response = $this->cancel_delivary_task($data);

        wp_send_json(array('status' => $response['status'], 'message' => $response['message']));
    }

    public function cancel_delivary_task($data)
    {
        $order_id = (int) $data['order_id'];
        $task_relation = $data['task_relation'];
        $delivery_id = $data['delivery_id'];
        $body = '';
        $status = 0;
        $response = array();

        $order = wc_get_order($order_id);
        if (! $order) {
            return array('status' => 0, 'message' => 'invalid order');
        }

        $auth_response = global_features_object()->pd_authentication();
        if ($auth_response['status']) {
            $access_token = $auth_response['access_token'];
            $token_type = $auth_response['token_type'];
            $auth = $token_type . ' ' . $access_token;
            $api_base = global_features_object()->glSettings['api_url'];

            // Decide the correct cancel endpoint based on how many delivery
            // points the task has. Parcel exposes two endpoints:
            //   - task/cancel/{taskRelation}      : cancels the WHOLE task
            //                                       (must be used when the
            //                                       task only has 1 point).
            //   - task/cancel-point?...&deliveryId=... : cancels a single
            //                                       delivery point inside a
            //                                       multi-point task.
            $multi_ids = $order->get_meta('_delivery_task_with_multiple_id');
            $delivery_count = is_array($multi_ids) ? count($multi_ids) : 1;

            $cancel_whole_task_url = $api_base . 'task/cancel/' . rawurlencode($task_relation) . '?cancellationReason=';
            $cancel_point_url      = $api_base . 'task/cancel-point/?taskRelation=' . rawurlencode($task_relation) . '&deliveryId=' . rawurlencode($delivery_id) . '&cancellationReason=';

            if ($delivery_count <= 1) {
                $url      = $cancel_whole_task_url;
                $fallback = $cancel_point_url;
            } else {
                $url      = $cancel_point_url;
                $fallback = $cancel_whole_task_url;
            }

            $response = global_features_object()->pd_curl_request($url, 'PUT', $body, $auth);
            $order->update_meta_data('_delivery_task_cancel_response', wp_json_encode($response));

            $is_ok = (isset($response['status']) && $response['status'] == 200);
            if (! $is_ok) {
                // Try the other endpoint as a fallback in case our local
                // delivery count is out of sync with Parcel's view.
                $response = global_features_object()->pd_curl_request($fallback, 'PUT', $body, $auth);
                $order->update_meta_data('_delivery_task_cancel_response', wp_json_encode($response));
                $is_ok = (isset($response['status']) && $response['status'] == 200);
            }

            if ($is_ok) {
                $order->update_meta_data('_delivery_task_cancelled', 1);
                $order->update_meta_data('_delivery_task_cancelled_by', 'admin');
                $status = 1;
            } else {
                $order->update_meta_data('_delivery_task_cancelled', 0);
                $status = 0;
            }

            $order->save();
        }

        return array('status' => $status, 'message' => isset($response['message']) ? $response['message'] : '');
    }

    /**
     * Top bar bulk button — legacy CPT screen.
     */
    public function admin_order_list_top_bar_button($which)
    {
        global $typenow;
        if ('shop_order' === $typenow && 'top' === $which) {
            $this->render_bulk_request_button();
        }
    }

    /**
     * Top bar bulk button — HPOS Orders screen.
     */
    public function admin_order_list_top_bar_button_hpos($order_type, $which)
    {
        if ('shop_order' === $order_type && 'top' === $which) {
            $this->render_bulk_request_button(true);
        }
    }

    protected function render_bulk_request_button($hpos = false)
    {
        $ajax_url = admin_url('admin-ajax.php');
        $nonce    = wp_create_nonce('pd_order_actions');
        // On HPOS screen, checkboxes use name="id[]" instead of name="post[]".
        $checkbox_selector = $hpos
            ? '.check-column input[name="id[]"]:checked, .check-column input[name="order[]"]:checked, .check-column input[type="checkbox"]:checked'
            : '.check-column input[name="post[]"]:checked';
        ?>
        <div class="alignleft actions custom">
            <button type="submit" name="request_delivery" style="height:32px;" class="button button-primary" value=""><?php echo esc_html__('Request Delivery', 'parcel-delivery'); ?></button>
        </div>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                $(document).on('click', 'button[name="request_delivery"]', function(e) {
                    e.preventDefault();
                    var order_ids = [];
                    var This = jQuery(this);
                    var ajaxurl = <?php echo wp_json_encode($ajax_url); ?>;
                    $(<?php echo wp_json_encode($checkbox_selector); ?>).each(function() {
                        var v = $(this).val();
                        if (v && v !== 'on') order_ids.push(v);
                    });
                    if (order_ids.length > 0) {
                        var data = {
                            action: 'request_delivery_bulk_action',
                            order_ids: order_ids,
                            nonce: <?php echo wp_json_encode($nonce); ?>
                        };
                        This.html('Processing...');
                        jQuery.ajax({
                            type: "post",
                            url: ajaxurl,
                            dataType: 'json',
                            data: data,
                            success: function(response) {
                                This.html('<?php echo esc_js(__('Request Delivery', 'parcel-delivery')); ?>');
                                jQuery('.wp-header-end').after(response.notice);
                            }
                        });
                    } else {
                        alert(<?php echo wp_json_encode(__('Please select some orders.', 'parcel-delivery')); ?>);
                    }
                });
            });
        </script>
        <?php
    }

    public function request_delivery_bulk_action()
    {
        check_ajax_referer('pd_order_actions', 'nonce');
        if (! current_user_can('manage_woocommerce')) {
            wp_send_json(array('status' => 0, 'message' => 'forbidden'), 403);
        }

        $data = array();
        $deliveries = array();
        $response = array();
        $valid_order_ids = array();
        $valid_orders = array();
        $api_response = array();
        $status = 0;

        $order_ids = isset($_POST['order_ids']) ? array_map('absint', (array) $_POST['order_ids']) : array();

        if (! empty($order_ids)) {
            foreach ($order_ids as $key => $order_id) {
                $order = wc_get_order($order_id);
                if (! $order) {
                    continue;
                }
                $delivery_task_status = $order->get_meta('_delivery_task_status');
                if (in_array($order->get_status(), array('processing', 'completed'), true)) {
                    $delivery_status = '';
                    if ($delivery_task_status == 1 && ! empty($delivery_task_status)) {
                        $delivery_status = $this->get_delivery_status_from_api_response($order_id);
                    }

                    if ($delivery_task_status != 1 || $delivery_status == 'Canceled') {
                        $valid_order_ids[] = $order_id;
                        $valid_orders[$order_id] = $order;
                        $billing_first_name = $order->get_billing_first_name();
                        $billing_last_name  = $order->get_billing_last_name();
                        $billing_address_1  = $order->get_billing_address_1();
                        $billing_address_2  = $order->get_billing_address_2();
                        $billing_city       = $order->get_billing_city();
                        $billing_state      = $order->get_billing_state();
                        $billing_postcode   = $order->get_billing_postcode();
                        $billing_country    = $order->get_billing_country();
                        $billing_phone      = $order->get_billing_phone();

                        $billing_block = $order->get_meta('_billing_block');

                        $delivery_data = global_features_object()->ck_get_block_data_by_id($billing_block);
                        $delivary_latitude  = $delivery_data['latitude'];
                        $delivary_longitude = $delivery_data['longitude'];
                        $full_address = $billing_address_1 . ' ' . $billing_address_2 . ' ' . $billing_city . ' ' . $billing_state . ' ' . $billing_postcode . ' ' . $delivery_data['name'] . ' ' . $billing_country;
                        $delivery = array(
                            'name'    => trim($billing_first_name . ' ' . $billing_last_name),
                            'phone'   => $billing_phone,
                            'address' => array(
                                'fullAddress'        => $full_address,
                                'specialInstruction' => '',
                                'location'           => array(
                                    'lat' => (float) $delivary_latitude,
                                    'lng' => (float) $delivary_longitude,
                                ),
                                'accurate'           => true,
                            ),
                            'notes'   => '-',
                            'orderId' => '#' . $order_id,
                        );

                        if ($order->get_payment_method() == 'cod') {
                            $delivery['cashCollected'] = (float) $order->get_total();
                        }

                        $deliveries[] = $delivery;
                    } else {
                        $response[] = array('order_id' => $order_id, 'message' => 'Driver already requested for this delivery. ');
                    }
                } else {
                    $response[] = array('order_id' => $order_id, 'message' => 'Delivery is only requested for processing and completed orders. ');
                }
            }

            if (! empty($deliveries)) {
                $pickup_location = global_features_object()->get_pickup_location();
                $pickup_latitude = $pickup_location['pickup_latitude'];
                $pickup_longitude = $pickup_location['pickup_longitude'];
                $pickup_full_address = $pickup_location['full_address'];
                $delivery_vehicle = get_option('pd_delivery_vehicle');
                $curr_time = date('Y-m-d\TH:i:sP');

                $body = wp_json_encode(array(
                    'vehicle'    => $delivery_vehicle,
                    'pickup'     => array(
                        'time'    => $curr_time,
                        'instant' => false,
                        'address' => array(
                            'fullAddress'        => $pickup_full_address,
                            'specialInstruction' => '',
                            'location'           => array(
                                'lat' => (float) $pickup_latitude,
                                'lng' => (float) $pickup_longitude,
                            ),
                        ),
                    ),
                    'deliveries' => $deliveries,
                ));
                $auth_response = global_features_object()->pd_authentication();
                if ($auth_response['status']) {
                    $access_token = $auth_response['access_token'];
                    $token_type = $auth_response['token_type'];
                    $auth = $token_type . ' ' . $access_token;
                    $url = global_features_object()->glSettings['api_url'] . 'task';

                    $api_response = global_features_object()->pd_curl_request($url, 'POST', $body, $auth);
                    if (isset($api_response['status']) && $api_response['status'] == 200) {
                        foreach ($valid_order_ids as $order_id) {
                            $o = $valid_orders[$order_id];
                            $o->update_meta_data('_delivery_task_res', wp_json_encode($api_response));
                            $o->update_meta_data('_delivery_task_status', 1);
                            $o->update_meta_data('_delivery_task_with_multiple_id', $valid_order_ids);
                            $o->save();
                            $response[] = array('order_id' => $order_id, 'message' => 'Delivery is requested successfully. ');
                        }
                        $status = 1;
                    } else {
                        $status = 0;
                        foreach ($valid_order_ids as $order_id) {
                            $o = $valid_orders[$order_id];
                            $o->update_meta_data('_delivery_task_res', wp_json_encode($api_response));
                            $o->update_meta_data('_delivery_task_status', 0);
                            $o->save();
                            $response[] = array('order_id' => $order_id, 'message' => 'Delivery is not requested successfully. ');
                        }
                    }
                }

                $data = array('status' => $status, 'data' => wp_json_encode($api_response), 'notice' => $this->get_admin_notice_for_bulk_delivery_request($response));
            } else {
                $data = array('status' => 0, 'notice' => $this->get_admin_notice_for_bulk_delivery_request($response));
            }
        }

        $data['message'] = isset($response[0]['message']) ? $response[0]['message'] : '';
        wp_send_json($data);
    }

    public function get_admin_notice_for_bulk_delivery_request($response)
    {
        $notice_str = '';
        if (! empty($response)) {
            $keys = array_column($response, 'order_id');
            array_multisort($keys, SORT_ASC, $response);
            foreach ($response as $value) {
                $notice_str .= '#' . $value['order_id'] . ' : ' . $value['message'] . '<br>';
            }
        }
        wc_add_notice('<div class="notice notice-info is-dismissible"><p>' . $notice_str . '</p></div>', "notice");
        return wc_print_notices(true);
    }

    public function filter_woocommerce_order_get_formatted_billing_address($address, $raw_address, $order)
    {
        try {
            if (! is_a($order, 'WC_Order')) {
                return $address;
            }
            $block_id = $order->get_meta('_billing_block');
            if (empty($block_id)) {
                return $address;
            }
            $billing_block_data = global_features_object()->ck_get_block_data_by_id($block_id);
            if (! is_array($billing_block_data) || empty($billing_block_data['name'])) {
                return $address;
            }
            $address .= '<br/>' . $billing_block_data['name'];
        } catch (\Throwable $e) {
            // Don't break the order screen if the helper misbehaves.
        }
        return $address;
    }

    public function get_delivery_status_from_api_response($order_id)
    {
        $delivery_status = '';
        $order = wc_get_order($order_id);
        if (! $order) {
            return $delivery_status;
        }
        $delivery_task_res = json_decode((string) $order->get_meta('_delivery_task_res'), true);
        if (empty($delivery_task_res['data']['taskRelation'])) {
            return $delivery_status;
        }
        $delivery_response = $this->get_delivary_task_details_by_id($delivery_task_res['data']['taskRelation']);
        if (isset($delivery_response['status']) && $delivery_response['status'] == 200) {
            $filteredArr = array_filter($delivery_response['data']['deliveries'], function ($v) use ($order_id) {
                return $v['orderId'] == '#' . $order_id;
            });
            if (! empty($filteredArr)) {
                $filteredArr = array_values($filteredArr);
                $delivery_status = $filteredArr[0]['deliveryStatus'];
            }
        }
        return $delivery_status;
    }

    /**
     * Attach the gettext label filter only in admin, and only on order-related screens.
     */
    public function maybe_register_shipping_label_filter()
    {
        if ( ! is_admin() ) {
            return;
        }
        if ( ! $this->is_order_screen() && ! wp_doing_ajax() ) {
            return;
        }
        add_filter('gettext', array($this, 'custom_change_shipping_label'), 20, 3);
    }

    public function custom_change_shipping_label($translated_text, $text, $domain)
    {
        if ('woocommerce' === $domain && 'Shipping method' === $text) {
            $translated_text = __('Shipping rate', 'parcel-delivery');
        }
        return $translated_text;
    }

    public function admin_order_preview_add_custom_billing_data($data, $order)
    {
        $data['shipping_via'] = get_option('woocommerce_currency') . ' ' . ($data['data']['shipping_total']);
        return $data;
    }
}

function order_settings_object()
{
    return OrderSettings::get_instance();
}

order_settings_object();
