<?php

/**

 * Plugin Name: Parcel Delivery

 * Description: An easy-use Parcel Delivery plugin that fits all WordPress (WOOCommerce) websites.

 * Version: 1.1.2

 * Author: Parcel

 * Author URI: https://tryparcel.com/

 * Text Domain: parcel-delivery

 * Domain Path: /languages

 * Requires at least: 6.0

 * Requires PHP: 7.4

 * WC requires at least: 7.0

 * WC tested up to: 9.4

 *

 */



/* 

 *If this file is called directly, abort.

 */

if ( ! defined( 'ABSPATH' ) ) {

	die;

}



/*

 * Define all constants for the plugin

 */

if ( ! defined( 'PARCEL_DELIVERY_PLUGIN_FILE' ) ) {

	define( 'PARCEL_DELIVERY_PLUGIN_FILE', __FILE__ );

}

if ( ! defined( 'PARCEL_DELIVERY_VERSION' ) ) {

	define( 'PARCEL_DELIVERY_VERSION', '1.1.1' );

}

if ( ! defined( 'PARCEL_DELIVERY_DIR_URL' ) ) {

	define( 'PARCEL_DELIVERY_DIR_URL', untrailingslashit( plugins_url( '/', PARCEL_DELIVERY_PLUGIN_FILE ) ) );

}

if ( ! defined( 'PARCEL_DELIVERY_DIR_PATH' ) ) {

	define( 'PARCEL_DELIVERY_DIR_PATH', plugin_dir_path(__FILE__) );

}





$pd_plugin_flag = false;

if ( ! function_exists( 'is_plugin_active_for_network' ) ) {

	require_once( ABSPATH . '/wp-admin/includes/plugin.php' );

}



if ( is_multisite() ) {

	// this plugin is network activated - WC must be network activated

	if ( is_plugin_active_for_network( plugin_basename( __FILE__ ) ) ) {

		$pd_plugin_flag = is_plugin_active_for_network( 'woocommerce/woocommerce.php' ) ? false : true;

		// this plugin is locally activated - WC can be network or locally activated

	} else {

		$pd_plugin_flag = is_plugin_active( 'woocommerce/woocommerce.php' ) ? false : true;

	}

} else { // this plugin runs on a single site

	$pd_plugin_flag = is_plugin_active( 'woocommerce/woocommerce.php' ) ? false : true;

}



if ( $pd_plugin_flag === true ) {

	add_action( 'admin_notices', 'pd_wc_requirements_error' );



	return;

}



/**

 * Declare compatibility with WooCommerce HPOS (Custom Order Tables) and Cart/Checkout Blocks.

 */

add_action( 'before_woocommerce_init', function() {

	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {

		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );

		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );

	}

} );



/**

 * Load plugin translations.

 */

add_action( 'init', function() {

	load_plugin_textdomain( 'parcel-delivery', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

} );



/**

 * If WC requirements are not match

 */

function pd_wc_requirements_error() {

	?>

	<div class="error notice"><p>

			<strong><?php esc_html_e( 'The WooCommerce plugin needs to be installed and activated in order for Parcel Delivery Plugin to work properly.', 'parcel-delivery' ); ?></strong> <?php esc_html_e( 'Please activate WooCommerce to enable Parcel Delivery.', 'parcel-delivery' ); ?>

		</p></div>

	<?php

}



/**

 * Register the activation hook

 */

register_activation_hook( __FILE__, 'pd_plugin_activation' );



/**

 * Define the activation function

 */

function pd_plugin_activation() {

    $woocommerce_allowed_countries = get_option('woocommerce_allowed_countries');

	$woocommerce_specific_allowed_countries  = get_option('woocommerce_specific_allowed_countries');

	

	update_option('pd_woocommerce_allowed_countries_backup', $woocommerce_allowed_countries);

	update_option( 'pd_woocommerce_specific_allowed_countries_backup',  $woocommerce_specific_allowed_countries);

	// Run one-time setup: configure allowed country and create shipping zone.
	if ( ! class_exists( 'GeneralSettings' ) ) {
		require_once PARCEL_DELIVERY_DIR_PATH . 'modules/wp-admin/general-settings/class-general-settings.php';
	}
	if ( class_exists( 'GeneralSettings' ) && function_exists( 'general_settings_object' ) ) {
		general_settings_object()->run_on_activation();
	}
}

/**
 * Inject a "Settings" link into the plugin row on the Plugins screen.
 *
 * We register the filter against BOTH the runtime plugin_basename() result
 * AND the canonical "parcel-delivery/parcel-delivery.php" path. This is
 * defensive: when the plugin is installed via a symlink or NTFS junction
 * (common on dev environments such as Local by Flywheel and our junction-
 * based dev workflow) plugin_basename() can return a path that does not
 * match the one WordPress uses to render the plugin row, which causes the
 * filter to silently never fire. Registering both hooks guarantees the
 * Settings link shows up on every install — including the zips we ship to
 * merchants.
 */
function pd_plugin_action_links( $actions ) {
    if ( isset( $actions['settings'] ) ) {
        return $actions;
    }
    $url      = esc_url( get_admin_url( null, 'admin.php?page=wc-settings&tab=parcel_delivery' ) );
    $label    = esc_html__( 'Settings', 'parcel-delivery' );
    $settings = array( 'settings' => '<a href="' . $url . '">' . $label . '</a>' );
    return array_merge( $settings, $actions );
}

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'pd_plugin_action_links' );
add_filter( 'plugin_action_links_parcel-delivery/parcel-delivery.php', 'pd_plugin_action_links' );



if (!class_exists('ParcelDelivery')) {

    class ParcelDelivery{

        // first call create a construct function 

        public function __construct()

        {

            /**

             * include files

             */

            require(PARCEL_DELIVERY_DIR_PATH . 'includes/init.php');

                

        }

    }

    $parcelDelivery = new ParcelDelivery();

}



/**

 * Register the deactivation hook.

 *

 * IMPORTANT: deactivation must NOT delete user data (API keys, pickup location,

 * blocks, etc.) — users frequently deactivate plugins temporarily. Destructive

 * cleanup belongs in uninstall.php and is only executed on a full uninstall.

 */

register_deactivation_hook( __FILE__, 'pd_plugin_deactivation' );



function pd_plugin_deactivation() {

	// Restore the merchant's original allowed-countries configuration.

	$woocommerce_allowed_countries          = get_option('pd_woocommerce_allowed_countries_backup');

	$woocommerce_specific_allowed_countries = get_option('pd_woocommerce_specific_allowed_countries_backup');



	if ( false !== $woocommerce_allowed_countries ) {

		update_option('woocommerce_allowed_countries', $woocommerce_allowed_countries);

	}

	if ( false !== $woocommerce_specific_allowed_countries ) {

		update_option('woocommerce_specific_allowed_countries', $woocommerce_specific_allowed_countries);

	}

}







