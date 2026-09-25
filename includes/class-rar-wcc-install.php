<?php
/**
 * Activation, database schema, upgrades and scheduled cleanup.
 *
 * @package RAR_Woo_Cart_Checkout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RAR_WCC_Install {

	public const CRON_HOOK = 'rar_wcc_daily_maintenance';

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'rar_wcc_incomplete';
	}

	public static function activate() {
		if ( false === get_option( RAR_WCC_Settings::OPTION ) ) {
			add_option( RAR_WCC_Settings::OPTION, RAR_WCC_Settings::defaults(), '', false );
		}
		self::create_tables();
		self::schedule();
		update_option( 'rar_wcc_db_version', RAR_WCC_DB_VERSION, false );
		if ( ! get_option( 'rar_wcc_installed_at' ) ) {
			update_option( 'rar_wcc_installed_at', time(), false );
		}
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	public static function maybe_upgrade() {
		if ( get_option( 'rar_wcc_db_version' ) !== RAR_WCC_DB_VERSION ) {
			self::activate();
		}
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			self::schedule();
		}
		add_action( self::CRON_HOOK, array( __CLASS__, 'maintenance' ) );
	}

	private static function schedule() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	public static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			session_key varchar(64) NOT NULL DEFAULT '',
			phone varchar(20) NOT NULL DEFAULT '',
			name varchar(190) NOT NULL DEFAULT '',
			email varchar(190) NOT NULL DEFAULT '',
			address text NULL,
			district varchar(100) NOT NULL DEFAULT '',
			city varchar(100) NOT NULL DEFAULT '',
			customer_note text NULL,
			cart longtext NULL,
			cart_total decimal(19,4) NOT NULL DEFAULT 0,
			currency varchar(10) NOT NULL DEFAULT '',
			source varchar(20) NOT NULL DEFAULT 'checkout',
			status varchar(20) NOT NULL DEFAULT 'new',
			order_id bigint(20) unsigned NOT NULL DEFAULT 0,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			ip varchar(45) NOT NULL DEFAULT '',
			user_agent varchar(255) NOT NULL DEFAULT '',
			admin_note text NULL,
			attempts int(10) unsigned NOT NULL DEFAULT 1,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY session_key (session_key),
			KEY phone (phone),
			KEY status (status),
			KEY updated_at (updated_at)
		) {$charset};";

		dbDelta( $sql );
	}

	/**
	 * Daily: purge old incomplete records and expire stale "new" ones.
	 */
	public static function maintenance() {
		global $wpdb;
		$days  = max( 1, (int) RAR_WCC_Settings::get( 'incomplete_retention', 60 ) );
		$table = self::table();
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE updated_at < %s", gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		delete_transient( 'rar_wcc_stats' );
	}
}
