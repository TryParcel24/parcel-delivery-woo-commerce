<?php
/**
 * Parcel Delivery — uninstall cleanup.
 *
 * WordPress loads this file ONLY when the user fully uninstalls the plugin
 * (Plugins screen → Delete). It is never loaded on simple deactivation.
 *
 * Here we remove every option and custom shipping zone the plugin created,
 * and restore the merchant's original allowed-countries configuration.
 */

// Exit if accessed directly or not during an uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// 1) Restore merchant's original allowed-countries configuration.
$allowed_countries          = get_option( 'pd_woocommerce_allowed_countries_backup' );
$specific_allowed_countries = get_option( 'pd_woocommerce_specific_allowed_countries_backup' );

if ( false !== $allowed_countries ) {
	update_option( 'woocommerce_allowed_countries', $allowed_countries );
}
if ( false !== $specific_allowed_countries ) {
	update_option( 'woocommerce_specific_allowed_countries', $specific_allowed_countries );
}

// 2) Delete the shipping zone we created (if any) and only if WC is still loaded.
$zone_id = get_option( 'pd_shipping_zone' );
if ( $zone_id && class_exists( 'WC_Shipping_Zones' ) ) {
	WC_Shipping_Zones::delete_zone( (int) $zone_id );
}

// 3) Delete all plugin options.
$pd_options = array(
	// Backups
	'pd_woocommerce_allowed_countries_backup',
	'pd_woocommerce_specific_allowed_countries_backup',
	// Settings
	'pd_api_client_key',
	'pd_api_client_secret_key',
	'pd_google_maps_api_key',
	'pd_shipping_calculator_method',
	'pd_api_blocks_info',
	'pd_manual_blocks_info',
	'pd_pickup_location',
	'pd_delivery_vehicle',
	'pd_shipping_zone',
);

foreach ( $pd_options as $opt ) {
	delete_option( $opt );
	delete_site_option( $opt ); // multisite safety.
}
