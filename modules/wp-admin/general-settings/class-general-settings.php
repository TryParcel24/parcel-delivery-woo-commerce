<?php
class GeneralSettings
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
        $data['pd_specific_country_codes'] = array('BH'); // BH - behrain
        return $data;
    }

    /*
     * this function adds hooks only for this plugin
     */
    public function setup_hooks()
    {
        add_action( 'admin_enqueue_scripts', array($this,'enqueue_admin_style_script') );

        // NOTE: pd_country_setup + pd_shipping_zone_setup are run once on plugin
        // activation (see parcel-delivery.php) — not on every `init` — to avoid
        // hitting the database on every request and overriding merchant settings.
    }


    /**
     * include style and scripts (only on our own settings tab).
     */
    public function enqueue_admin_style_script( $hook_suffix = '' ){
        if ( ! $this->is_plugin_settings_screen() ) {
            return;
        }

        wp_enqueue_style('general_settings_style', PARCEL_DELIVERY_DIR_URL . '/modules/wp-admin/general-settings/css/style.css', array(), PARCEL_DELIVERY_VERSION, 'all');

        wp_enqueue_script('general_settings_script', PARCEL_DELIVERY_DIR_URL . '/modules/wp-admin/general-settings/js/script.js', array('jquery'), PARCEL_DELIVERY_VERSION, true);

        wp_localize_script('general_settings_script', 'generalVar', array(
            'adminurl' => admin_url('admin-ajax.php'),
        ));
    }

    /**
     * True when the admin is on WooCommerce → Settings → Parcel Delivery.
     */
    protected function is_plugin_settings_screen(){
        if ( ! is_admin() ) {
            return false;
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $tab  = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : '';
        return ( 'wc-settings' === $page && 'parcel_delivery' === $tab );
    }


    /**
     * Runs ONCE from the plugin activation hook.
     * Configures the allowed country and a shipping zone.
     */
    public function run_on_activation(){
        $this->pd_country_setup();
        $this->pd_shipping_zone_setup();
    }

    /**
     * parcel delivery plugin's country settings.
     * Only writes when the values are not already what we want.
     */
    public function pd_country_setup(){
        if ( get_option('woocommerce_allowed_countries') !== 'specific' ) {
            update_option('woocommerce_allowed_countries', 'specific');
        }

        $desired_countries = $this->glSettings['pd_specific_country_codes'];
        $current_countries = (array) get_option('woocommerce_specific_allowed_countries', array());
        // Merge with existing codes so we don't shrink a merchant's configuration.
        $merged = array_values( array_unique( array_merge( $current_countries, $desired_countries ) ) );
        sort($merged);
        $current_sorted = $current_countries;
        sort($current_sorted);
        if ( $merged !== $current_sorted ) {
            update_option('woocommerce_specific_allowed_countries', $merged);
        }
    }

    /**
     * parcel delivery plugin's shipping zone settings
     */
    public function pd_shipping_zone_setup() {
        if ( ! class_exists('WC_Shipping_Zones') ) {
            return;
        }

        foreach ($this->glSettings['pd_specific_country_codes'] as $country_code) {
            if ( ! $this->pd_check_zone_exists($country_code) ) {
                $zone_id = $this->pd_create_shipping_zone($country_code);
                update_option('pd_shipping_zone', $zone_id);
            }
        }
    }

    /**
     * Check if a zone for the specified country already exists
     */
    public function pd_check_zone_exists($country_code){
        // Retrieve all shipping zones
        $all_zones = WC_Shipping_Zones::get_zones();
       
        $zone_exists = false;
        foreach ( $all_zones as $zone ) {
            foreach($zone['zone_locations'] as $zone_loc){

                if ( ! empty( $zone_loc->code ) && ( $country_code ==  $zone_loc->code ) ) {
                    $zone_exists = true;
                    break;
                }
            }
        }
        return $zone_exists;
    }

    /**
     * create shipping zone 
     */
    public function pd_create_shipping_zone($country_code){
        // Define the shipping zone data
        $zone_data = new WC_Shipping_Zone();
        $zone_data->set_zone_name( WC()->countries->countries[$country_code]." (".$country_code.")" );
        $zone_data->set_zone_order( 1 );
        $zone_data->add_location( $country_code, 'country' );

        // Add the flat rate shipping method to the zone
        $flat_rate_id = $zone_data->add_shipping_method('flat_rate');

        // Add the free shipping method to the zone
        $free_shipping_id = $zone_data->add_shipping_method('free_shipping');
        $zone_id = $zone_data->save();
        return $zone_id;
    }

} // class ends

function general_settings_object()
{
    return GeneralSettings::get_instance();
}

general_settings_object();