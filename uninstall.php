<?php
/**
 * Uninstall: data is preserved unless "Delete all plugin data on uninstall" is enabled.
 * WooCommerce orders and customers are never touched.
 *
 * @package RAR_Woo_Cart_Checkout
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

wp_clear_scheduled_hook( 'rar_wcc_daily_maintenance' );

$rar_wcc_settings = get_option( 'rar_wcc_settings', array() );
if ( ! is_array( $rar_wcc_settings ) || 'yes' !== ( $rar_wcc_settings['delete_on_uninstall'] ?? 'no' ) ) {
	return;
}

global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}rar_wcc_incomplete" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

foreach ( array( 'rar_wcc_settings', 'rar_wcc_db_version', 'rar_wcc_installed_at', 'rar_wcc_block_log', 'rar_wcc_block_total' ) as $rar_wcc_option ) {
	delete_option( $rar_wcc_option );
}
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_rar\_wcc\_%' OR option_name LIKE '\_transient\_timeout\_rar\_wcc\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_rar_wcc_block_backup'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
