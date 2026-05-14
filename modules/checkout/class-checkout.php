<?php

/**
 * Checkout — Classic + Block-based Checkout compatible.
 *
 * Adds a "Block" (district) select field as a billing-section field on both classic
 * checkout (via `woocommerce_billing_fields`) and the new Cart/Checkout Blocks
 * (via the Additional Checkout Fields API introduced in WooCommerce 8.6+).
 */
class Checkout
{
    protected static $_instance = null;
    public $glSettings = array();

    /** Additional Checkout Fields API field id (block checkout). */
    const PD_FIELD_ID = 'parcel-delivery/billing-block';

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

    public function setup_hooks()
    {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_style_script'));

        // Classic checkout
        add_filter('woocommerce_billing_fields', array($this, 'add_custom_billing_field'));
        // CRITICAL: Parse billing_block from POST data BEFORE calculate_totals()
        // runs. woocommerce_checkout_update_order_review fires too late —
        // shipping rates are already cached by then.
        add_action('woocommerce_before_calculate_totals', array($this, 'prefetch_block_shipping_cost'));
        add_action('woocommerce_checkout_update_order_review', array($this, 'woocommerce_checkout_update_order_review'));
        add_action('woocommerce_after_checkout_validation', array($this, 'add_checkout_fields_custom_validation'), 10, 2);

        // Recalculate shipping cost on rates (works for both classic & block checkout)
        // Priority 50 ensures we run after other plugins.
        add_filter('woocommerce_package_rates', array($this, 'adjust_shipping_rate'), 50, 2);
        // Force WooCommerce to recalculate shipping on every page load / AJAX
        // call so our adjust_shipping_rate filter always runs with fresh data.
        add_action('woocommerce_before_calculate_totals', array($this, 'force_shipping_recalculation'));

        // Save data to order
        add_action('woocommerce_checkout_order_processed', array($this, 'ck_custom_order_meta'), 10, 1);
        // Block checkout uses Store API: this fires for both classic and block when an order is created.
        add_action('woocommerce_checkout_create_order', array($this, 'ck_save_billing_block_on_order'), 10, 2);
        add_action('woocommerce_store_api_checkout_update_order_from_request', array($this, 'ck_save_billing_block_from_request'), 10, 2);

        // Register Additional Checkout Field for Block Checkout.
        add_action('woocommerce_init', array($this, 'register_additional_checkout_field'));

        // When the customer changes the field on the block checkout, refresh shipping cost.
        add_action('woocommerce_set_additional_field_value', array($this, 'on_additional_field_set'), 10, 4);

        // Block checkout: invalidate shipping cache every time the customer
        // updates their address/fields via the Store API so calculate_shipping
        // gets re-run and reads the new block selection.
        add_action('woocommerce_store_api_cart_update_customer_from_request', array($this, 'on_store_api_customer_update'), 20, 2);

        // Block Checkout validation (Store API): enforce minimum-order amount per block.
        add_action('woocommerce_store_api_checkout_update_order_from_request', array($this, 'validate_block_checkout_min_order'), 20, 2);
    }

    /**
     * Block Checkout validation — enforce min_order for the selected block.
     * Throws a Store API RouteException to stop checkout with a user-visible message.
     */
    public function validate_block_checkout_min_order($order, $request)
    {
        $block_id = $this->extract_block_id_from_request($request);
        if ('' === $block_id) {
            return; // Required validation handled by the field itself.
        }

        $blocks = global_features_object()->get_blocks_data();
        if (empty($blocks)) {
            return;
        }
        $key = array_search($block_id, array_column($blocks, 'id'));
        if ($key === false) {
            return;
        }

        $minimum_order_amount = is_object($blocks[$key]) && isset($blocks[$key]->min_order)
            ? (float) $blocks[$key]->min_order
            : ( is_array($blocks[$key]) && isset($blocks[$key]['min_order']) ? (float) $blocks[$key]['min_order'] : 0 );
        if ($minimum_order_amount <= 0) {
            return;
        }

        // Compare against the order total (which Store API has already populated).
        $order_total = (float) $order->get_total();
        if ($order_total < $minimum_order_amount) {
            $message = sprintf(
                /* translators: %s: minimum order amount */
                __('A minimum total purchase amount of %s is required to checkout.', 'parcel-delivery'),
                wp_strip_all_tags(wc_price($minimum_order_amount))
            );

            if (class_exists('\Automattic\WooCommerce\StoreApi\Exceptions\RouteException')) {
                throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException(
                    'parcel_delivery_min_order',
                    $message,
                    400
                );
            }
            // Fallback if the Store API namespace is not available for some reason.
            throw new \Exception($message);
        }
    }

    /**
     * Try to read the registered additional field value from a Store API request.
     */
    protected function extract_block_id_from_request($request)
    {
        if (! is_object($request) || ! method_exists($request, 'get_json_params')) {
            return '';
        }
        $params = $request->get_json_params();
        if (! is_array($params)) {
            return '';
        }
        if (! empty($params['extensions']['parcel-delivery']['billing-block'])) {
            return (string) $params['extensions']['parcel-delivery']['billing-block'];
        }
        foreach (array('billing_address', 'shipping_address', 'additional_fields') as $section) {
            if (isset($params[$section][self::PD_FIELD_ID]) && '' !== $params[$section][self::PD_FIELD_ID]) {
                return (string) $params[$section][self::PD_FIELD_ID];
            }
        }
        return '';
    }

    public function enqueue_style_script()
    {
        if (function_exists('is_checkout') && is_checkout()) {
            wp_enqueue_style('pd_checkout_style', PARCEL_DELIVERY_DIR_URL . '/modules/checkout/css/style.css', array(), PARCEL_DELIVERY_VERSION, 'all');
            wp_enqueue_script('pd_checkout_script', PARCEL_DELIVERY_DIR_URL . '/modules/checkout/js/script.js', array('jquery'), PARCEL_DELIVERY_VERSION, true);

            wp_localize_script('pd_checkout_script', 'checkoutVar', array(
                'adminurl' => admin_url('admin-ajax.php'),
                'wc_currency' => get_woocommerce_currency_symbol(),
                'pd_shipping_calculator_method' => global_features_object()->glSettings['shipping_calculator_method'],
                'pd_manual_blocks_info' => global_features_object()->glSettings['manual_blocks_data'],
                'pd_api_blocks_info' => global_features_object()->glSettings['api_blocks_data'],
            ));
        }
    }

    /**
     * Build the options array used by both classic & block checkout fields.
     *
     * @param bool $with_placeholder Include an empty "Select Block" entry (classic only).
     * @return array
     */
    protected function get_block_options($with_placeholder = true)
    {
        $blocks = global_features_object()->get_blocks_data();
        $options = $with_placeholder ? array('' => __('Select Block', 'parcel-delivery')) : array();
        if (! empty($blocks)) {
            foreach ($blocks as $value) {
                $status = is_object($value) && isset($value->status) ? $value->status : (is_array($value) && isset($value['status']) ? $value['status'] : false);
                $id     = is_object($value) && isset($value->id)     ? $value->id     : (is_array($value) && isset($value['id'])     ? $value['id']     : '');
                $name   = is_object($value) && isset($value->name)   ? $value->name   : (is_array($value) && isset($value['name'])   ? $value['name']   : '');
                if (! empty($status) && '' !== $id) {
                    $options[$id] = $name;
                }
            }
        }
        return $options;
    }

    /**
     * Classic checkout billing field.
     */
    public function add_custom_billing_field($fields)
    {
        $options = $this->get_block_options(true);

        if (count($options) > 1) {
            $fields['billing_block'] = array(
                'type'     => 'select',
                'label'    => __('Block', 'parcel-delivery'),
                'class'    => array('form-row-wide'),
                'required' => true,
                'options'  => $options,
                'priority' => 45,
            );
        }
        return $fields;
    }

    /**
     * Register the field for Cart/Checkout Blocks (Additional Checkout Fields API).
     */
    public function register_additional_checkout_field()
    {
        if (! function_exists('woocommerce_register_additional_checkout_field')) {
            return; // WC < 8.6 — block checkout extension not available.
        }
        // Only register on front-end / Store API requests; skip admin to avoid
        // _doing_it_wrong notices and keep admin pages fast.
        if (is_admin() && ! wp_doing_ajax() && ! (defined('REST_REQUEST') && REST_REQUEST)) {
            return;
        }
        try {
            $options_assoc = $this->get_block_options(false);
            if (empty($options_assoc)) {
                return;
            }

            $options = array();
            foreach ($options_assoc as $value => $label) {
                $options[] = array(
                    'value' => (string) $value,
                    'label' => (string) $label,
                );
            }

            woocommerce_register_additional_checkout_field(array(
                'id'        => self::PD_FIELD_ID,
                'label'     => __('Block', 'parcel-delivery'),
                'location'  => 'address',
                'type'      => 'select',
                'required'  => true,
                'options'   => $options,
            ));
        } catch (\Throwable $e) {
            if (function_exists('wc_get_logger')) {
                wc_get_logger()->error('Parcel Delivery: failed to register block checkout field — ' . $e->getMessage(), array('source' => 'parcel-delivery'));
            }
        }
    }

    /**
     * Block Checkout (Store API): customer hit cart/update-customer.
     * Read the block field straight from the request body, recompute the
     * shipping cost and invalidate cached package rates so calculate_shipping
     * runs again on the next read.
     */
    public function on_store_api_customer_update($customer, $request)
    {
        $block_id = $this->extract_block_id_from_request($request);
        $log = function_exists('wc_get_logger') ? wc_get_logger() : null;
        if ($log) { $log->info('[PD] on_store_api_customer_update: extracted block_id="' . $block_id . '"', array('source' => 'parcel-delivery')); }
        if ('' !== $block_id) {
            $this->recalculate_shipping_for_block($block_id);
            return;
        }

        // No block selected (yet) — make sure stale cost is cleared.
        $this->set_session_related_to_shipping(0);
    }

    /**
     * Reacts to the additional field being set on customer session during block checkout.
     */
    public function on_additional_field_set($key, $value, $group, $wc_object)
    {
        $log = function_exists('wc_get_logger') ? wc_get_logger() : null;
        if ($log) { $log->info('[PD] on_additional_field_set: key="' . $key . '" value="' . $value . '" group="' . $group . '"', array('source' => 'parcel-delivery')); }

        if ($key !== self::PD_FIELD_ID) {
            return;
        }
        // Only handle billing address group for shipping cost.
        if ($group !== 'billing' && $group !== 'other') {
            if ($log) { $log->warning('[PD] on_additional_field_set: ignored group="' . $group . '" (expected billing/other)', array('source' => 'parcel-delivery')); }
            return;
        }

        $this->recalculate_shipping_for_block($value);
    }

    /**
     * Force WooCommerce to recalculate shipping on every cart calculation.
     * Without this, WC may return cached rates and our adjust_shipping_rate
     * filter would never see the updated block selection.
     */
    public function force_shipping_recalculation()
    {
        if (! function_exists('WC') || ! WC() || ! WC()->session) {
            return;
        }
        // Only on frontend / AJAX.
        if (is_admin() && ! wp_doing_ajax()) {
            return;
        }
        foreach (WC()->cart->get_shipping_packages() as $package_key => $package) {
            WC()->session->set('shipping_for_package_' . $package_key, false);
        }
    }

    /**
     * Runs BEFORE WC()->cart->calculate_totals() (which calls calculate_shipping).
     * Parses the checkout POST data to extract billing_block and set the
     * session shipping cost early, so resolve_cost() finds the correct value.
     */
    public function prefetch_block_shipping_cost()
    {
        // Only during the checkout AJAX update.
        if ( ! wp_doing_ajax() || ! isset( $_POST['post_data'] ) ) {
            return;
        }

        try {
            $data = array();
            $vars = explode('&', (string) wp_unslash($_POST['post_data']));
            foreach ($vars as $value) {
                $pair = explode('=', $value, 2);
                $key   = isset($pair[0]) ? urldecode($pair[0]) : '';
                $val   = isset($pair[1]) ? urldecode($pair[1]) : '';
                if ('' !== $key) {
                    $data[$key] = $val;
                }
            }

            if (! empty($data['billing_block'])) {
                $this->recalculate_shipping_for_block($data['billing_block']);
            }
        } catch (\Throwable $e) {
            if (function_exists('wc_get_logger')) {
                wc_get_logger()->error('Parcel Delivery: prefetch_block_shipping_cost failed — ' . $e->getMessage(), array('source' => 'parcel-delivery'));
            }
        }
    }

    /**
     * Classic checkout review update.
     */
    public function woocommerce_checkout_update_order_review($post_data)
    {
        try {
            $data = array();
            $vars = explode('&', (string) $post_data);
            foreach ($vars as $value) {
                $pair = explode('=', $value, 2);
                $key   = isset($pair[0]) ? urldecode($pair[0]) : '';
                $val   = isset($pair[1]) ? urldecode($pair[1]) : '';
                if ('' !== $key) {
                    $data[$key] = $val;
                }
            }

            if (! empty($data['billing_block'])) {
                $this->recalculate_shipping_for_block($data['billing_block']);
            } else {
                $this->set_session_related_to_shipping(0);
            }
        } catch (\Throwable $e) {
            if (function_exists('wc_get_logger')) {
                wc_get_logger()->error('Parcel Delivery: checkout_update_order_review failed — ' . $e->getMessage(), array('source' => 'parcel-delivery'));
            }
        }
    }

    /**
     * Public entry point used by the Parcel Delivery shipping method during
     * rate calculation. Mirrors {@see self::recalculate_shipping_for_block()}.
     */
    public function recalculate_shipping_for_block_public($block_id)
    {
        $this->recalculate_shipping_for_block($block_id);
    }

    /**
     * Common helper to compute shipping cost from a block id and stash it in session.
     */
    protected function recalculate_shipping_for_block($block_id)
    {
        $log = function_exists('wc_get_logger') ? wc_get_logger() : null;
        if ($log) {
            $log->info('[PD] recalculate_shipping_for_block called with block_id="' . $block_id . '"', array('source' => 'parcel-delivery'));
        }

        $blocks = global_features_object()->get_blocks_data();
        if (empty($blocks)) {
            if ($log) { $log->warning('[PD] No blocks data available — setting shipping cost = 0', array('source' => 'parcel-delivery')); }
            $this->set_session_related_to_shipping(0);
            return;
        }

        $ids = array_column($blocks, 'id');
        if ($log) {
            $log->info('[PD] Found ' . count($blocks) . ' blocks. First 5 ids: ' . wp_json_encode(array_slice($ids, 0, 5)), array('source' => 'parcel-delivery'));
        }

        $key = array_search($block_id, $ids);
        if ($key === false) {
            // Try loose comparison (string vs int)
            $key = array_search((string) $block_id, array_map('strval', $ids));
        }
        if ($key === false) {
            if ($log) { $log->warning('[PD] block_id="' . $block_id . '" NOT FOUND in saved blocks — setting shipping cost = 0', array('source' => 'parcel-delivery')); }
            $this->set_session_related_to_shipping(0);
            return;
        }

        $method = global_features_object()->glSettings['shipping_calculator_method'];

        if ('api' === $method) {
            // For API method, the LIVE delivery fee from Parcel's pricing
            // endpoint is the source of truth. The locally stored del_charge
            // is only used as a fallback when the API call cannot succeed.
            $pickup_location  = global_features_object()->get_pickup_location();
            $pickup_latitude  = isset($pickup_location['pickup_latitude'])  ? $pickup_location['pickup_latitude']  : '';
            $pickup_longitude = isset($pickup_location['pickup_longitude']) ? $pickup_location['pickup_longitude'] : '';

            $delivery_latitude  = is_object($blocks[$key]) && isset($blocks[$key]->latitude)  ? $blocks[$key]->latitude  : '';
            $delivery_longitude = is_object($blocks[$key]) && isset($blocks[$key]->longitude) ? $blocks[$key]->longitude : '';

            $response = $this->get_delivery_fees($pickup_latitude, $pickup_longitude, $delivery_latitude, $delivery_longitude);

            if (! empty($response['status']) && isset($response['response']['data']['taskFees'])) {
                $shipping_cost = (float) $response['response']['data']['taskFees'];
            } else {
                // API failed. The admin opted-in to the live API for pricing,
                // so we do NOT silently fall back to a saved del_charge here.
                // Surface a 0 cost and log the exact coordinates that failed.
                if (function_exists('wc_get_logger')) {
                    wc_get_logger()->warning(
                        sprintf(
                            'Parcel Delivery: API price call failed for block %s. pickup=(%s,%s) delivery=(%s,%s).',
                            isset($blocks[$key]->id) ? $blocks[$key]->id : $block_id,
                            $pickup_latitude,
                            $pickup_longitude,
                            $delivery_latitude,
                            $delivery_longitude
                        ),
                        array('source' => 'parcel-delivery')
                    );
                }
                $shipping_cost = 0.0;
            }
        } else {
            // Manual method: use the locally saved per-block charge.
            $block = $blocks[$key];
            $shipping_cost = is_object($block) && isset($block->del_charge)
                ? (float) $block->del_charge
                : ( is_array($block) && isset($block['del_charge']) ? (float) $block['del_charge'] : 0 );
        }

        if ($log) {
            $log->info('[PD] recalculate_shipping_for_block: final cost=' . $shipping_cost . ' for block_id="' . $block_id . '"', array('source' => 'parcel-delivery'));
        }
        $this->set_session_related_to_shipping($shipping_cost, $block_id);
    }

    public function set_session_related_to_shipping($shipping_cost, $block_id = '')
    {
        if (! function_exists('WC') || ! WC() || ! WC()->session) {
            return;
        }
        // Ensure session is initialized (front-end requests may not have it yet).
        if ( ! WC()->session->has_session() && ! is_admin() ) {
            WC()->session->init();
        }
        WC()->session->set('block_shipping_cost', $shipping_cost);
        if ('' !== $block_id) {
            WC()->session->set('chosen_billing_block', $block_id);
        }

        if (WC()->cart) {
            foreach (WC()->cart->get_shipping_packages() as $package_key => $package) {
                WC()->session->set('shipping_for_package_' . $package_key, false);
            }
        }
    }

    public function adjust_shipping_rate($rates)
    {
        try {
            return $this->_adjust_shipping_rate_inner($rates);
        } catch (\Throwable $e) {
            if (function_exists('wc_get_logger')) {
                wc_get_logger()->error('Parcel Delivery: adjust_shipping_rate failed — ' . $e->getMessage(), array('source' => 'parcel-delivery'));
            }
            return $rates;
        }
    }

    private function _adjust_shipping_rate_inner($rates)
    {
        if (empty($rates)) {
            return $rates;
        }

        // Check if our parcel_delivery method is in the zone at all.
        $has_pd = false;
        foreach ($rates as $rate) {
            if ('parcel_delivery' === $rate->method_id) {
                $has_pd = true;
                break;
            }
        }
        if (! $has_pd && ! $this->has_legacy_flat_rate($rates)) {
            return $rates;
        }

        // ── Compute the correct cost RIGHT HERE ──
        // This filter always runs (even on cached rates), so it's the
        // most reliable place to override the cost — no session dependency.
        $computed_cost = $this->compute_current_block_cost();

        $log = function_exists('wc_get_logger') ? wc_get_logger() : null;
        if ($log) {
            $log->info('[PD] adjust_shipping_rate: computed_cost=' . $computed_cost, array('source' => 'parcel-delivery'));
        }

        // Apply to parcel_delivery rate (preferred).
        foreach ($rates as $rate_key => $rate) {
            if ('parcel_delivery' === $rate->method_id) {
                $rates[$rate_key]->cost = (float) $computed_cost;
                return array($rate_key => $rates[$rate_key]);
            }
        }

        // Legacy fallback: adjust flat_rate / free_shipping.
        if ($computed_cost > 0) {
            foreach ($rates as $rate) {
                if ('flat_rate' === $rate->method_id) {
                    $rate->cost = $computed_cost;
                    return array($rate->id => $rates[$rate->id]);
                }
            }
        } else {
            foreach ($rates as $rate) {
                if ('free_shipping' === $rate->method_id) {
                    return array($rate->id => $rates[$rate->id]);
                }
            }
        }

        return $rates;
    }

    /**
     * Compute the shipping cost for the currently selected block.
     * Tries every available source: POST data, Store API request,
     * customer meta, session. Does NOT depend on any single source.
     */
    private function compute_current_block_cost()
    {
        $block_id = $this->detect_selected_block_id();
        $log = function_exists('wc_get_logger') ? wc_get_logger() : null;

        if ($log) {
            $log->info('[PD] compute_current_block_cost: detected block_id="' . $block_id . '"', array('source' => 'parcel-delivery'));
        }

        if ('' === $block_id) {
            return 0;
        }

        $method = '';
        if (function_exists('global_features_object') && isset(global_features_object()->glSettings['shipping_calculator_method'])) {
            $method = (string) global_features_object()->glSettings['shipping_calculator_method'];
        }

        // API mode: the live API is the source of truth.
        if ('api' === $method) {
            // If session already has a cost for THIS block, use it.
            if (WC() && WC()->session) {
                $cached_block = WC()->session->get('chosen_billing_block');
                $cached_cost  = WC()->session->get('block_shipping_cost');
                if ((string) $cached_block === (string) $block_id
                    && is_numeric($cached_cost) && $cached_cost > 0) {
                    if ($log) { $log->info('[PD] compute_current_block_cost: API session hit cost=' . $cached_cost, array('source' => 'parcel-delivery')); }
                    return (float) $cached_cost;
                }
            }

            // Session miss or stale — fetch fresh price from the API now.
            if ($log) { $log->info('[PD] compute_current_block_cost: API mode, calling recalculate_shipping_for_block', array('source' => 'parcel-delivery')); }
            $this->recalculate_shipping_for_block($block_id);

            if (WC() && WC()->session) {
                $fresh_cost = WC()->session->get('block_shipping_cost');
                if (is_numeric($fresh_cost) && $fresh_cost > 0) {
                    return (float) $fresh_cost;
                }
            }
            return 0;
        }

        // Manual mode: look up del_charge from saved blocks.
        if (function_exists('global_features_object')) {
            $blocks = global_features_object()->get_blocks_data();
            if (is_array($blocks) && ! empty($blocks)) {
                $ids = array_column($blocks, 'id');
                $key = array_search($block_id, $ids, false);
                if (false === $key) {
                    $key = array_search((string) $block_id, array_map('strval', $ids), false);
                }
                if (false !== $key) {
                    $block = $blocks[$key];
                    $cost = is_object($block) && isset($block->del_charge)
                        ? (float) $block->del_charge
                        : (is_array($block) && isset($block['del_charge']) ? (float) $block['del_charge'] : null);
                    if (null !== $cost) {
                        if ($log) { $log->info('[PD] compute_current_block_cost: found del_charge=' . $cost, array('source' => 'parcel-delivery')); }
                        return $cost;
                    }
                } else {
                    if ($log) { $log->warning('[PD] compute_current_block_cost: block_id="' . $block_id . '" NOT found in ' . count($blocks) . ' blocks. IDs: ' . wp_json_encode(array_slice($ids, 0, 5)), array('source' => 'parcel-delivery')); }
                }
            }
        }

        return 0;
    }

    /**
     * Detect the selected block ID from every possible source.
     */
    private function detect_selected_block_id()
    {
        $log = function_exists('wc_get_logger') ? wc_get_logger() : null;

        // 1. Use the proper CheckoutFields service (documented public API).
        if (WC() && WC()->customer && class_exists('\\Automattic\\WooCommerce\\Blocks\\Package')) {
            try {
                $checkout_fields = \Automattic\WooCommerce\Blocks\Package::container()->get(
                    \Automattic\WooCommerce\Blocks\Domain\Services\CheckoutFields::class
                );
                foreach (array('billing', 'shipping', 'other') as $group) {
                    $val = $checkout_fields->get_field_from_object(self::PD_FIELD_ID, WC()->customer, $group);
                    if ($log) { $log->debug('[PD] detect: CheckoutFields[' . $group . ']="' . $val . '"', array('source' => 'parcel-delivery')); }
                    if (! empty($val)) {
                        return (string) $val;
                    }
                }
            } catch (\Throwable $e) {
                if ($log) { $log->debug('[PD] detect: CheckoutFields error: ' . $e->getMessage(), array('source' => 'parcel-delivery')); }
            }
        }

        // 2. Customer meta directly (Block Checkout persists here).
        if (WC() && WC()->customer) {
            foreach (array('_wc_billing/parcel-delivery/billing-block', '_wc_shipping/parcel-delivery/billing-block', '_wc_other/parcel-delivery/billing-block') as $meta_key) {
                $meta = WC()->customer->get_meta($meta_key);
                if ($log) { $log->debug('[PD] detect: meta[' . $meta_key . ']="' . $meta . '"', array('source' => 'parcel-delivery')); }
                if (! empty($meta)) {
                    return (string) $meta;
                }
            }
        }

        // 3. Session fallback (set by on_store_api_customer_update or prefetch).
        if (WC() && WC()->session) {
            $session_block = WC()->session->get('chosen_billing_block');
            if ($log) { $log->debug('[PD] detect: session_block="' . $session_block . '"', array('source' => 'parcel-delivery')); }
            if (! empty($session_block)) {
                return (string) $session_block;
            }
        }

        // 4. Classic checkout AJAX: billing_block inside post_data.
        if (isset($_POST['post_data'])) {
            $parsed = $this->parse_post_data_string(wp_unslash($_POST['post_data']));
            if (! empty($parsed['billing_block'])) {
                return sanitize_text_field($parsed['billing_block']);
            }
        }

        // 5. Direct POST field (non-AJAX classic checkout).
        if (isset($_POST['billing_block']) && '' !== $_POST['billing_block']) {
            return sanitize_text_field(wp_unslash($_POST['billing_block']));
        }

        // 6. Store API request body (fallback).
        if (isset($_REQUEST['billing_address']['parcel-delivery/billing-block'])) {
            return sanitize_text_field(wp_unslash($_REQUEST['billing_address']['parcel-delivery/billing-block']));
        }
        if (isset($_REQUEST['shipping_address']['parcel-delivery/billing-block'])) {
            return sanitize_text_field(wp_unslash($_REQUEST['shipping_address']['parcel-delivery/billing-block']));
        }

        if ($log) { $log->debug('[PD] detect: ALL sources empty — returning ""', array('source' => 'parcel-delivery')); }

        return '';
    }

    /**
     * Parse a URL-encoded post_data string into key => value array.
     */
    private function parse_post_data_string($raw)
    {
        $data = array();
        $vars = explode('&', (string) $raw);
        foreach ($vars as $value) {
            $pair = explode('=', $value, 2);
            $key  = isset($pair[0]) ? urldecode($pair[0]) : '';
            $val  = isset($pair[1]) ? urldecode($pair[1]) : '';
            if ('' !== $key) {
                $data[$key] = $val;
            }
        }
        return $data;
    }

    private function has_legacy_flat_rate($rates)
    {
        foreach ($rates as $rate) {
            if ('flat_rate' === $rate->method_id || 'free_shipping' === $rate->method_id) {
                return true;
            }
        }
        return false;
    }

    public function add_checkout_fields_custom_validation($fields, $errors)
    {
        $blocks = global_features_object()->get_blocks_data();
        if (empty($blocks) || empty($fields['billing_block'])) {
            return;
        }

        $key = array_search($fields['billing_block'], array_column($blocks, 'id'));
        if ($key === false) {
            return;
        }

        $minimum_order_amount = is_object($blocks[$key]) && isset($blocks[$key]->min_order)
            ? (float) $blocks[$key]->min_order
            : ( is_array($blocks[$key]) && isset($blocks[$key]['min_order']) ? (float) $blocks[$key]['min_order'] : 0 );
        $cart = WC()->cart;
        $cart_total = $cart ? (float) $cart->get_total() : 0;
        if ($minimum_order_amount > 0 && $cart_total < $minimum_order_amount) {
            $errors->add('validation', sprintf(
                /* translators: %s: minimum order amount */
                __('A minimum total purchase amount of %s is required to checkout.', 'parcel-delivery'),
                wc_price($minimum_order_amount)
            ));
        }
    }

    public function get_delivery_fees($pickup_latitude, $pickup_longitude, $delivery_latitude, $delivery_longitude)
    {
        $log = function_exists('wc_get_logger') ? wc_get_logger() : null;

        $auth_response = global_features_object()->pd_authentication();
        if ($log) {
            $log->info('[PD] API auth response: ' . wp_json_encode($auth_response), array('source' => 'parcel-delivery'));
        }
        if (empty($auth_response['status'])) {
            if ($log) { $log->error('[PD] API auth FAILED — cannot fetch price', array('source' => 'parcel-delivery')); }
            return false;
        }
        $access_token = $auth_response['access_token'];
        $token_type = $auth_response['token_type'];

        $fee_response = $this->pd_calculate_delivery_fees($access_token, $token_type, $pickup_latitude, $pickup_longitude, $delivery_latitude, $delivery_longitude);
        if ($log) {
            $log->info('[PD] API fee_response: ' . wp_json_encode($fee_response), array('source' => 'parcel-delivery'));
        }
        if (! empty($fee_response['status'])) {
            return array('status' => true, 'response' => $fee_response['response']);
        }
        return false;
    }

    public function pd_calculate_delivery_fees($access_token, $token_type, $pickup_latitude, $pickup_longitude, $delivery_latitude, $delivery_longitude)
    {
        $log = function_exists('wc_get_logger') ? wc_get_logger() : null;

        $delivery_vehicle = get_option('pd_delivery_vehicle');
        if (empty($delivery_vehicle)) {
            $delivery_vehicle = 'bike';
        }

        $body = wp_json_encode(array(
            'vehicle'    => $delivery_vehicle,
            'pickup'     => array(
                'address' => array(
                    'location' => array(
                        'lat' => (float) $pickup_latitude,
                        'lng' => (float) $pickup_longitude,
                    ),
                ),
            ),
            'deliveries' => array(
                array(
                    'address' => array(
                        'location' => array(
                            'lat' => (float) $delivery_latitude,
                            'lng' => (float) $delivery_longitude,
                        ),
                    ),
                ),
            ),
        ));

        $auth = $token_type . ' ' . $access_token;
        $url = global_features_object()->glSettings['api_url'] . 'task/price';

        if ($log) {
            $log->info('[PD] API price request URL=' . $url, array('source' => 'parcel-delivery'));
            $log->info('[PD] API price request body=' . $body, array('source' => 'parcel-delivery'));
        }

        $response = global_features_object()->pd_curl_request($url, 'POST', $body, $auth);

        if ($log) {
            $log->info('[PD] API price raw response: ' . wp_json_encode($response), array('source' => 'parcel-delivery'));
        }

        if (isset($response['status']) && $response['status'] == 200) {
            return array('status' => true, 'response' => $response);
        }
        return array('status' => false, 'message' => isset($response['message']) ? $response['message'] : '');
    }

    /**
     * Classic checkout: persist pickup location to order at processing.
     */
    public function ck_custom_order_meta($order_id)
    {
        $order = wc_get_order($order_id);
        if (! $order) {
            return;
        }
        $pickup_location = global_features_object()->glSettings['pickup_location'];
        $order->update_meta_data('_pickup_location', $pickup_location);
        $order->save();
    }

    /**
     * Classic checkout: persist billing_block to order at create time (HPOS-safe).
     */
    public function ck_save_billing_block_on_order($order, $data)
    {
        if (! empty($_POST['billing_block'])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
            $order->update_meta_data('_billing_block', sanitize_text_field(wp_unslash($_POST['billing_block']))); // phpcs:ignore WordPress.Security.NonceVerification.Missing
        }
    }

    /**
     * Block checkout (Store API): persist billing_block from the additional field.
     */
    public function ck_save_billing_block_from_request($order, $request)
    {
        // Try to read the registered additional field from the request payload.
        $params = method_exists($request, 'get_json_params') ? $request->get_json_params() : array();

        $block_id = '';
        if (! empty($params['extensions']) && is_array($params['extensions'])) {
            // namespace-based (older shape)
            if (isset($params['extensions']['parcel-delivery']['billing-block'])) {
                $block_id = $params['extensions']['parcel-delivery']['billing-block'];
            }
        }
        // Newer shape: additional_fields under billing_address / shipping_address / additional_fields.
        foreach (array('billing_address', 'shipping_address', 'additional_fields') as $section) {
            if (isset($params[$section][self::PD_FIELD_ID]) && '' !== $params[$section][self::PD_FIELD_ID]) {
                $block_id = $params[$section][self::PD_FIELD_ID];
                break;
            }
        }

        if ('' !== $block_id) {
            $order->update_meta_data('_billing_block', sanitize_text_field($block_id));
            // Mirror to a billing_block field too so legacy code paths keep working.
        }

        // Also stash pickup location at this point for block checkout flow.
        $pickup_location = global_features_object()->glSettings['pickup_location'];
        if (! empty($pickup_location)) {
            $order->update_meta_data('_pickup_location', $pickup_location);
        }
    }
}

function pd_checkout_object()
{
    return Checkout::get_instance();
}

pd_checkout_object();
