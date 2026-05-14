<?php

class ParcelDeliveryTab

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



    public function __construct() {

        $this->glSettings = $this->get_general_option();

        $this->setup_hooks();



    }



    public function get_general_option(){

        $data = array();

        return $data;

    }



    /*

     * this function adds hooks only for this plugin

     */

    public function setup_hooks()

    {

        add_action( 'admin_enqueue_scripts', array($this,'enqueue_admin_style_script') );

        // Lazy AJAX endpoint: JS fetches blocks on demand (no API call during page render).

        add_action( 'wp_ajax_pd_load_blocks', array($this, 'ajax_pd_load_blocks') );

        add_filter( 'woocommerce_settings_tabs_array', array($this,'pd_woocommerce_settings_tabs_array'), 99 );

        add_action( 'woocommerce_sections_parcel_delivery',array($this,'pd_woocommerce_parcel_delivery_tab_sections'),  10 );

        add_action( 'woocommerce_settings_parcel_delivery', array($this,'pd_woocommerce_parcel_delivery_settings'), 10 );

        add_action( 'woocommerce_update_options_parcel_delivery', array($this,'pd_save_woocommerce_parcel_delivery_settings'), 10 );

    }



    /**

     * include style and scripts

     */

    public function enqueue_admin_style_script($hook_suffix ){

        

        global $current_section;

        $page    = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
        $tab     = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : '';
        $section = isset( $_GET['section'] ) ? sanitize_text_field( wp_unslash( $_GET['section'] ) ) : $current_section;

        // Only load scripts on the custom tab section

        if ( 'wc-settings' === $page && 'parcel_delivery' === $tab && ( 'pd_shipping_calculator' === $section || '' === $section ) ) {

            // *************************  stylesheets ******************/

            wp_enqueue_style('datatable_style', 'https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css', array(), '1.13.4', 'all');
            wp_enqueue_style('datatable_fixed_header_style', 'https://cdn.datatables.net/fixedheader/3.1.2/css/fixedHeader.dataTables.min.css', array(), '3.1.2', 'all');

            // Magnific Popup is required by the Set status / Set delivery charge modals
            // and to hide the popup markup (.mfp-hide). Without it the popup contents
            // render inline and the shipping calculator UI looks broken.
            wp_enqueue_style('magnific_popup_style', 'https://cdnjs.cloudflare.com/ajax/libs/magnific-popup.js/1.1.0/magnific-popup.min.css', array(), '1.1.0', 'all');

            // Select2 styles (WC admin already enqueues select2 JS, but the styles
            // are tied to `select2` handle which we ensure here for safety).
            if ( wp_style_is( 'select2', 'registered' ) ) {
                wp_enqueue_style( 'select2' );
            }

            wp_enqueue_style('pd_style', PARCEL_DELIVERY_DIR_URL . '/modules/wp-admin/parcel-delivery-tab/css/style.css', array(), PARCEL_DELIVERY_VERSION, 'all');

            

            // ************************  stylesheets ******************/

            // ********************** javascript ********************* /

            wp_enqueue_script('datatable_script','https://cdn.datatables.net/1.12.1/js/jquery.dataTables.min.js', array('jquery'), '1.12.1', true );

            wp_enqueue_script('datatable_fixed_header_script','https://cdn.datatables.net/fixedheader/3.1.2/js/dataTables.fixedHeader.min.js', array('jquery','datatable_script'), '3.1.2', true );

            wp_enqueue_script('magnific_popup_script', 'https://cdnjs.cloudflare.com/ajax/libs/magnific-popup.js/1.1.0/jquery.magnific-popup.min.js', array('jquery'), '1.1.0', true);

            // Make sure Select2 JS is available for the pickup_block dropdown.
            if ( wp_script_is( 'select2', 'registered' ) ) {
                wp_enqueue_script( 'select2' );
            } elseif ( wp_script_is( 'selectWoo', 'registered' ) ) {
                wp_enqueue_script( 'selectWoo' );
            }

            // Plugin script declares its dependencies so it runs after the libs above.
            wp_enqueue_script(
                'pd_script',
                PARCEL_DELIVERY_DIR_URL . '/modules/wp-admin/parcel-delivery-tab/js/script.js',
                array( 'jquery', 'datatable_script', 'datatable_fixed_header_script', 'magnific_popup_script' ),
                PARCEL_DELIVERY_VERSION,
                true
            );
            

            // ********************** javascript  ********************* /

            // Blocks are loaded with a 1-hour transient cache to keep this admin page

            // fast (no live external API call on cache hit). The cache can be busted

            // via the AJAX endpoint `pd_load_blocks` (force=1).

            $blocks_data = $this->pd_load_blocks();

            wp_localize_script('pd_script', 'pdVar', array(

                'adminurl' => admin_url('admin-ajax.php'),

                'nonce' => wp_create_nonce('pd_load_blocks'),

                'wc_currency' => get_woocommerce_currency_symbol(),

                'pd_all_blocks_info' => $blocks_data,

                'pd_shipping_calculator_method' => get_option( 'pd_shipping_calculator_method'),

                'pd_pickup_location' => get_option('pd_pickup_location'),

                'pd_manual_blocks_info' => get_option('pd_manual_blocks_info'),

                'pd_api_blocks_info' => get_option('pd_api_blocks_info'),

                'pd_delivery_vehicle' => get_option('pd_delivery_vehicle')

                )

            );

        }

    }



    /**

     * Add the parcel delivery tab to the woocommerce settings tabs array

     */

    public function pd_woocommerce_settings_tabs_array( $settings_tabs ) {

        $settings_tabs['parcel_delivery'] = __( 'Parcel Delivery', 'woocommerce' );

        return $settings_tabs;

    }



    /**

     * Add new sections to the parcel delivery tab

     */

    public function pd_woocommerce_parcel_delivery_tab_sections() {

        global $current_section;

        $tab_id = 'parcel_delivery';

        // Must contain more than one section to display the links

        // Make first element's key empty ('')

        $sections = array(

            ''  => __( 'API Keys', 'woocommerce' ),

            'pd_shipping_calculator'  => __( 'Shipping Calculator', 'woocommerce' )

        );

        echo '<ul class="subsubsub">';

        $array_keys = array_keys( $sections );

        foreach ( $sections as $id => $label ) {

            echo '<li><a href="' . admin_url( 'admin.php?page=wc-settings&tab=' . $tab_id . '&section=' . sanitize_title( $id ) ) . '" class="' . ( $current_section == $id ? 'current' : '' ) . '">' . $label . '</a> ' . ( end( $array_keys ) == $id ? '' : '|' ) . ' </li>';

        }

        echo '</ul><br class="clear" />';

    }



    /**

     * Add settings to parcel delivery tab

     */

    public function pd_woocommerce_parcel_delivery_settings() {

        // Call settings function

        $settings = $this->get_parcel_delivery_custom_settings();

        WC_Admin_Settings::output_fields( $settings );  

    }



    /**

     * parcel delivery Settings function

     */

    function get_parcel_delivery_custom_settings() {

        global $current_section;

        $settings = array();

        if ( $current_section == 'pd_shipping_calculator' ) {

            // Render the template with the latest blocks status so the admin sees a
            // clear message when the API returned no usable data (missing keys,
            // failed auth, network error, etc.).

            $shipcal_method = get_option( 'pd_shipping_calculator_method' );

            $blocks_status  = $this->pd_load_blocks();

            echo ck_helper_object()->ck_get_template(

                'parcel-delivery-shipping-calculator.php',

                'wp-admin/parcel-delivery-tab',

                array(

                    'shipcal_method' => $shipcal_method,

                    'blocks_status'  => $blocks_status,

                )

            );

        } else {

            // api keys

            $settings = array(

                // Title

                array(

                    'title'     => __( 'Parcel delivery API Keys', 'woocommerce' ),

                    'type'      => 'title',

                    'id'        => 'pd_api_keys_title'

                ),

                // Text

                array(

                    'title'     => __( 'Client Key', 'woocommerce' ),

                    'type'      => 'text',

                    'desc'      => __( 'parcel delivery API client ID', 'woocommerce' ),

                    'desc_tip'  => true,

                    'id'        => 'pd_api_client_key',

                    'css'       => 'min-width:300px;',

                    'custom_attributes' => array('autocomplete' => 'off')

                ),

                array(

                    'title'     => __( 'Client Secret Key', 'woocommerce' ),

                    'type'      => 'text',

                    'desc'      => __( 'parcel delivery API client Secret Key', 'woocommerce' ),

                    'desc_tip'  => true,

                    'id'        => 'pd_api_client_secret_key',

                    'css'       => 'min-width:300px;',

                    'custom_attributes' => array('autocomplete' => 'off')

                ),

                array(

                    'title'     => __( 'Google Maps API Key', 'parcel-delivery' ),

                    'type'      => 'text',

                    'desc'      => __( 'Required for the order tracking map shown to customers on the order details page. Leave empty to disable the map.', 'parcel-delivery' ),

                    'desc_tip'  => true,

                    'id'        => 'pd_google_maps_api_key',

                    'css'       => 'min-width:300px;',

                    'custom_attributes' => array('autocomplete' => 'off')

                ),

                array(

                    'title'     => __( 'API Environment', 'parcel-delivery' ),

                    'type'      => 'select',

                    'desc'      => __( 'Choose Test while integrating, then switch to Live for production. The same Client Key/Secret works for both; only the OAuth scope changes (test = api/test, live = api/tasks).', 'parcel-delivery' ),

                    'desc_tip'  => true,

                    'id'        => 'pd_api_environment',

                    'default'   => 'test',

                    'options'   => array(

                        'test' => __( 'Test (api/test)', 'parcel-delivery' ),

                        'live' => __( 'Live (api/tasks)', 'parcel-delivery' ),

                    ),

                ),

                array(

                    'type'      => 'sectionend',

                    'id'        => 'pd_api_keys'

                ),

            );

        }

        return $settings;

    }



    

    /**

     * Process/save the settings of parcel delivery tab

     */

    public function pd_save_woocommerce_parcel_delivery_settings() {

        global $current_section;

        $tab_id = 'parcel_delivery';

        // Call settings function

        if ( $current_section == 'pd_shipping_calculator' ) {

            // print_r($_POST );die;

            // print_r(json_decode( html_entity_decode( stripslashes ($_POST['pd_api_blocks_info'] ) ) ));die;



            $shipcal_method = isset( $_POST['pdsc_method'] )
                ? sanitize_key( wp_unslash( $_POST['pdsc_method'] ) )
                : ( isset( $_POST['pd_shipping_calculator_method'] ) ? sanitize_key( wp_unslash( $_POST['pd_shipping_calculator_method'] ) ) : 'manual' );

            if ( ! in_array( $shipcal_method, array( 'manual', 'api' ), true ) ) {
                $shipcal_method = 'manual';
            }

            update_option( 'pd_shipping_calculator_method', $shipcal_method );
            update_option( 'pd_delivery_vehicle', isset( $_POST['pd_delivery_vehicle'] ) ? sanitize_text_field( wp_unslash( $_POST['pd_delivery_vehicle'] ) ) : '' );

            if ( isset( $_POST['pd_pickup_location'] ) ) {
                $pickup_decoded = json_decode( html_entity_decode( stripslashes( $_POST['pd_pickup_location'] ) ) );
                update_option( 'pd_pickup_location', $pickup_decoded );
            }

            if($shipcal_method == 'api'){

                if ( isset( $_POST['pd_api_blocks_info'] ) ) {
                    $api_decoded = json_decode( html_entity_decode( stripslashes( $_POST['pd_api_blocks_info'] ) ) );
                    update_option( 'pd_api_blocks_info', is_array( $api_decoded ) ? $api_decoded : array() );
                }

            }else if($shipcal_method == 'manual'){

                if ( isset( $_POST['pd_manual_blocks_info'] ) ) {
                    $manual_decoded = json_decode( html_entity_decode( stripslashes( $_POST['pd_manual_blocks_info'] ) ) );
                    update_option( 'pd_manual_blocks_info', is_array( $manual_decoded ) ? $manual_decoded : array() );
                }

            }

            

        }else{

            // Detect changes to any field that affects API authentication so we
            // can flush the blocks cache afterwards. Switching environment
            // (test <-> live) changes the OAuth scope and therefore the token,
            // so a refresh is required.

            $old_client_key        = get_option('pd_api_client_key');

            $old_client_secret_key = get_option('pd_api_client_secret_key');

            $old_environment       = get_option('pd_api_environment');

            $settings = $this->get_parcel_delivery_custom_settings();

            WC_Admin_Settings::save_fields( $settings );

            do_action( 'woocommerce_update_options_' . $tab_id . '_' . $current_section );

            $new_client_key        = get_option('pd_api_client_key');

            $new_client_secret_key = get_option('pd_api_client_secret_key');

            $new_environment       = get_option('pd_api_environment');

            if (
                $old_client_key !== $new_client_key
                || $old_client_secret_key !== $new_client_secret_key
                || $old_environment !== $new_environment
            ) {

                self::flush_blocks_cache();

            }

        }

    }



    /**

     * Transient key + TTL for cached blocks (1 hour).

     */

    const PD_BLOCKS_TRANSIENT = 'pd_blocks_cache';

    const PD_BLOCKS_TTL       = HOUR_IN_SECONDS;



    /**

     * AJAX endpoint: lazily refresh blocks from the Parcel API.

     * Requires a valid nonce and the `manage_woocommerce` capability.

     * Pass `force=1` to bust the transient cache.

     */

    public function ajax_pd_load_blocks(){

        check_ajax_referer('pd_load_blocks', 'nonce');

        if ( ! current_user_can('manage_woocommerce') ) {

            wp_send_json( array('status' => false, 'message' => 'forbidden'), 403 );

        }

        $force = ! empty( $_REQUEST['force'] );

        wp_send_json( $this->pd_load_blocks( $force ) );

    }



    /**

     * Load blocks with a transient cache so the admin page never blocks on the

     * external API on cache hits. On miss, hits the API and stores the result.

     *

     * @param bool $force_refresh Ignore the cache and re-fetch.

     * @return array { status: bool, block_data?: array, message?: string }

     */

    public function pd_load_blocks( $force_refresh = false ){

        if ( ! $force_refresh ) {

            $cached = get_transient( self::PD_BLOCKS_TRANSIENT );

            if ( is_array( $cached ) && ! empty( $cached['status'] ) ) {

                return $cached;

            }

        }



        $response = array();

        $auth_response = global_features_object()->pd_authentication();

        if ( ! empty($auth_response['status']) ) {

            $access_token = $auth_response['access_token'];

            $token_type   = $auth_response['token_type'];

            $block_response = $this->pd_get_blocks($access_token, $token_type);

            if ( ! empty($block_response['status']) ) {

                $response = array('status' => true, 'block_data' => $block_response['response']['data']);

                set_transient( self::PD_BLOCKS_TRANSIENT, $response, self::PD_BLOCKS_TTL );

            } else {

                $response = $block_response;

            }

        } else {

            $response = $auth_response;

        }

        return $response;

    }



    /**

     * Bust the blocks cache. Called when API keys are saved.

     */

    public static function flush_blocks_cache(){

        delete_transient( self::PD_BLOCKS_TRANSIENT );

    }



    public function pd_get_blocks($access_token, $token_type){

        $body = '';

        $auth = $token_type.' '.$access_token;

        $url = global_features_object()->glSettings['api_url'].'blocks';

        $response = global_features_object()->pd_curl_request($url, 'GET', $body, $auth);

        if ( isset($response['status']) && 200 == $response['status'] ) {

            return array('status' => true, 'response' => $response);

        }

        return array('status' => false, 'message' => isset($response['message']) ? $response['message'] : 'Unknown error.');

    }



} // class ends



function parcel_delivery_tab_object()

{

    return ParcelDeliveryTab::get_instance();

}



parcel_delivery_tab_object();