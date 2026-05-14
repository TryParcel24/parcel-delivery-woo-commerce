<?php

class GlobalFeatures

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

        $data = array(

            'api_url' => 'https://api.tryparcel.com/api/v4/',

            'oauth2_url' => 'https://auth.tryparcel.com/oauth2/token',

            'shipping_calculator_method' => get_option( 'pd_shipping_calculator_method', 'manual'),

            'manual_blocks_data' => get_option('pd_manual_blocks_info', array()),

            'api_blocks_data' => get_option('pd_api_blocks_info', array()),

            'pickup_location' => get_option('pd_pickup_location')

        );

        return $data;

    }



    /*

     * this function adds hooks only for this plugin

     */

    public function setup_hooks()

    {

        add_action( 'admin_enqueue_scripts', array($this,'enqueue_admin_assets') );

        add_action( 'wp_enqueue_scripts',    array($this,'enqueue_frontend_assets') );

    }



    /**

     * Enqueue admin assets only on our settings tab and on WC order screens.

     */

    public function enqueue_admin_assets(){

        if ( ! $this->is_plugin_admin_screen() ) {

            return;

        }

        wp_enqueue_style('global_style', PARCEL_DELIVERY_DIR_URL . '/modules/global-features/css/style.css', array(), PARCEL_DELIVERY_VERSION, 'all');

        wp_enqueue_script('global_script', PARCEL_DELIVERY_DIR_URL . '/modules/global-features/js/script.js', array('jquery'), PARCEL_DELIVERY_VERSION, true);

        wp_localize_script('global_script', 'globalVar', array(

            'adminurl' => admin_url('admin-ajax.php'),

        ));

    }



    /**

     * Enqueue front-end assets only on checkout / cart / my-account pages.

     */

    public function enqueue_frontend_assets(){

        if ( ! function_exists('is_checkout') ) {

            return;

        }

        if ( ! ( is_checkout() || is_cart() || is_account_page() ) ) {

            return;

        }

        wp_enqueue_style('global_style', PARCEL_DELIVERY_DIR_URL . '/modules/global-features/css/style.css', array(), PARCEL_DELIVERY_VERSION, 'all');

        wp_enqueue_script('global_script', PARCEL_DELIVERY_DIR_URL . '/modules/global-features/js/script.js', array('jquery'), PARCEL_DELIVERY_VERSION, true);

        wp_localize_script('global_script', 'globalVar', array(

            'adminurl' => admin_url('admin-ajax.php'),

        ));

    }



    /**

     * True when on our settings tab or on a WC order screen (legacy or HPOS).

     */

    protected function is_plugin_admin_screen(){

        if ( ! is_admin() ) {

            return false;

        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        $page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        $tab  = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : '';

        if ( 'wc-settings' === $page && 'parcel_delivery' === $tab ) {

            return true;

        }

        // HPOS orders screens.

        if ( 'wc-orders' === $page ) {

            return true;

        }

        // Legacy CPT orders screen.

        if ( function_exists('get_current_screen') ) {

            $screen = get_current_screen();

            if ( $screen && ( 'edit-shop_order' === $screen->id || 'shop_order' === $screen->id ) ) {

                return true;

            }

        }

        return false;

    }



    public function pd_authentication(){

        

        $clientDetails = $this->pd_get_api_client_credentials();

        if($clientDetails && is_array($clientDetails)){

            // Parcel uses different OAuth scopes per environment:
            //   Test  -> "api/test"
            //   Live  -> "api/tasks"
            // The same Client ID/Secret works for both; only the scope changes.
            $environment = get_option( 'pd_api_environment', 'test' );
            $scope       = ( 'live' === $environment ) ? 'api/tasks' : 'api/test';

            $body = wp_json_encode( array(
                'grant_type' => 'client_credentials',
                'scope'      => $scope,
            ) );

            $clientCredBase64 = base64_encode($clientDetails['clientKey'].':'.$clientDetails['clientSecretKey']);

            $auth = 'Basic '.$clientCredBase64;

            $response = $this->pd_curl_request($this->glSettings['oauth2_url'], 'POST', $body, $auth);

            if($response['status'] == 200){

                $access_token = $response['access_token'];

                $token_type = $response['token_type'];

                return array('status' => true, 'access_token' => $access_token, 'token_type' => $token_type);

            }else{

                return array('status' => false, 'message' => $response['message']);

            }

            

        }else{

            return array( 'status' => false , 'message' => 'Required API keys.' );

        }

    }



    public function pd_get_api_client_credentials(){

        $clientKey = get_option('pd_api_client_key');

        $clientSecretKey = get_option('pd_api_client_secret_key');

        if($clientKey && $clientSecretKey){

            $data = array(

                'clientKey' => $clientKey ,

                'clientSecretKey' => $clientSecretKey

            );

        }else{

            $data = false;

        }

        

        return $data;

    }



    public function pd_curl_request($url, $method, $body, $auth) {



        $curl = curl_init();



        curl_setopt_array($curl, array(

        CURLOPT_URL => $url,

        CURLOPT_RETURNTRANSFER => true,

        CURLOPT_ENCODING => '',

        CURLOPT_MAXREDIRS => 10,

        CURLOPT_TIMEOUT => 30,

        CURLOPT_FOLLOWLOCATION => true,

        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,

        CURLOPT_CUSTOMREQUEST => $method,

        CURLOPT_HTTPHEADER => array(

            'Authorization: '.$auth,

            'Content-Type: application/json',

            'Accept: application/json',



        ),

        CURLOPT_POSTFIELDS => $body,

        // Many API gateways (nginx / Cloudflare) reject HTTP requests that do
        // not present a User-Agent and respond with a generic HTML 403 page
        // before the request ever reaches the application. Send a stable UA
        // built from the site URL so the upstream can identify the caller.
        CURLOPT_USERAGENT => sprintf(

            'ParcelDeliveryWP/%s (+%s)',

            defined( 'PARCEL_DELIVERY_VERSION' ) ? PARCEL_DELIVERY_VERSION : '1.0',

            function_exists( 'home_url' ) ? home_url( '/' ) : 'unknown'

        ),

        ));

        // Use the CA bundle that ships with WordPress so cURL can verify the
        // remote certificate even on hosts whose PHP install has no curl.cainfo
        // configured (e.g. fresh WAMP). Without this many Windows dev setups
        // fail with: "SSL certificate problem: unable to get local issuer
        // certificate".
        if ( defined( 'ABSPATH' ) && defined( 'WPINC' ) ) {

            $ca_bundle = ABSPATH . WPINC . '/certificates/ca-bundle.crt';

            if ( is_readable( $ca_bundle ) ) {

                curl_setopt( $curl, CURLOPT_CAINFO, $ca_bundle );

            }

        }

        $response = curl_exec($curl);

        $error_msg   = curl_errno($curl) ? curl_error($curl) : '';

        $http_code   = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);

        $effective   = (string) curl_getinfo($curl, CURLINFO_EFFECTIVE_URL);

        curl_close($curl);

        // Helper that logs the failure (when WC logger is available) and
        // returns a structured error array with enough context to diagnose
        // why the Parcel API call failed without exposing secrets.
        $fail = function ( $message, $extra = array() ) use ( $url, $effective, $http_code, $method ) {

            if ( function_exists('wc_get_logger') ) {

                wc_get_logger()->error(

                    sprintf(

                        '[Parcel Delivery] %s — %s %s (effective: %s) http=%d',

                        $message,

                        $method,

                        $url,

                        $effective,

                        $http_code

                    ),

                    array_merge( array( 'source' => 'parcel-delivery' ), $extra )

                );

            }

            return array(

                'status'    => false,

                'message'   => $message,

                'http_code' => $http_code,

                'url'       => $url,

            );

        };

        if ( $error_msg !== '' ) {

            return $fail( 'cURL error: ' . $error_msg );

        }

        $raw = (string) $response;

        $decoded = json_decode( $raw, true );

        if ( ! is_array( $decoded ) ) {

            // Surface a snippet of the raw body so the admin can see whether
            // the endpoint returned HTML, a redirect page, or something else.
            $snippet = trim( preg_replace( '/\s+/', ' ', mb_substr( $raw, 0, 200 ) ) );

            $hint    = ( $snippet === '' )

                ? 'empty response body'

                : 'first 200 chars: ' . $snippet;

            return $fail( 'Invalid response from API (' . $hint . ').', array( 'raw_body_preview' => $snippet ) );

        }

        return $decoded;

    }



    public function get_blocks_data(){

        if(isset($this->glSettings['shipping_calculator_method']) && !empty($this->glSettings['shipping_calculator_method'])){

            $method = $this->glSettings['shipping_calculator_method'];

            if($method){

                $blocks = array();

                if($method == 'manual'){

                    $blocks = $this->glSettings['manual_blocks_data'];

                }elseif($method == 'api'){

                    $blocks = $this->glSettings['api_blocks_data'];

                }

                return is_array($blocks) ? $blocks : array();

            }

        }

        return array();

    }



    public function ck_get_block_data_by_id( $block_id ) {

        $default = array('latitude' => 0.0, 'longitude' => 0.0, 'name' => '');



        $blocks = $this->get_blocks_data();

        if ( empty($blocks) || ! is_array($blocks) ) {

            return $default;

        }



        $key = array_search( $block_id, array_column( $blocks, 'id' ), false );

        if ( false === $key || ! isset( $blocks[$key] ) ) {

            return $default;

        }



        $block = $blocks[$key];

        return array(

            'latitude'  => isset($block->latitude)  ? (float) $block->latitude  : 0.0,

            'longitude' => isset($block->longitude) ? (float) $block->longitude : 0.0,

            'name'      => isset($block->name)      ? (string) $block->name     : '',

        );

    }



    public function get_pickup_location($location = array()){

        $data = array(

            'pickup_latitude'  => 0.0,

            'pickup_longitude' => 0.0,

            'pickup_name'      => '',

            'full_address'     => '',

        );



        $pickup_location = empty($location) ? $this->glSettings['pickup_location'] : $location;

        if ( empty($pickup_location) || ! is_object($pickup_location) ) {

            return $data;

        }



        $lat = ! empty($pickup_location->pickup_latitude)

            ? $pickup_location->pickup_latitude

            : ( isset($pickup_location->pickup_block_json->latitude) ? $pickup_location->pickup_block_json->latitude : 0 );

        $lng = ! empty($pickup_location->pickup_longitude)

            ? $pickup_location->pickup_longitude

            : ( isset($pickup_location->pickup_block_json->longitude) ? $pickup_location->pickup_block_json->longitude : 0 );

        $name = isset($pickup_location->pickup_block_json->name) ? (string) $pickup_location->pickup_block_json->name : '';

        $address1 = isset($pickup_location->pickup_address1) ? (string) $pickup_location->pickup_address1 : '';

        $address2 = isset($pickup_location->pickup_address2) ? (string) $pickup_location->pickup_address2 : '';



        $data['pickup_latitude']  = (float) $lat;

        $data['pickup_longitude'] = (float) $lng;

        $data['pickup_name']      = $name;

        $data['full_address']     = trim( $address1 . ' ' . $address2 . ' ' . $name );

        return $data;

    }





} // class ends



function global_features_object()

{

    return GlobalFeatures::get_instance();

}



global_features_object();