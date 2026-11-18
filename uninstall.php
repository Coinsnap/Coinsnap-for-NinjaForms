<?php
// Removes only this plugin's data: its webhook secrets and its own entries in the Ninja Forms settings.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) { exit; }

global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$coinsnapnf_options = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( 'ninja_forms_settings_coinsnap_webhook_' ) . '%' ) );
foreach ( $coinsnapnf_options as $coinsnapnf_option ) {
    delete_option( $coinsnapnf_option );
}
delete_option( 'coinsnapnf_records_since' );
delete_transient( 'coinsnapnf_btcpay_state' );

$coinsnapnf_settings = get_option( 'ninja_forms_settings' );
if ( is_array( $coinsnapnf_settings ) ) {
    foreach ( array( 'coinsnap_provider', 'coinsnap_store_id', 'coinsnap_api_key', 'btcpay_server_url', 'btcpay_store_id', 'btcpay_api_key', 'coinsnap_autoredirect', 'coinsnap_returnurl', 'coinsnap_currency' ) as $coinsnapnf_key ) {
        unset( $coinsnapnf_settings[ $coinsnapnf_key ] );
    }
    update_option( 'ninja_forms_settings', $coinsnapnf_settings );
}
