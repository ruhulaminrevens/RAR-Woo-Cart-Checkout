<?php
/**
 * Plugin Name:       RAR Woo Cart & Checkout
 * Plugin URI:        https://github.com/ruhulaminrevens/RAR-Woo-Cart-Checkout
 * Description:       Bangladesh-first WooCommerce cart & checkout suite: smart address fields, BD phone validation, Express Buy Now modal, incomplete-order recovery, fraud/duplicate order protection, customer order history, free-shipping progress, checkout quantity editing, dashboard and REST API.
 * Version:           2.0.0
 * Author:            Ruhul Amin Revens
 * Author URI:        https://www.nabiad.com/
 * Text Domain:       rar-woo-cart-checkout
 * Domain Path:       /languages
 * License:           GPLv2 or later
 * Requires at least: 6.5
 * Requires PHP:      8.0
 * Requires Plugins:  woocommerce
 * WC requires at least: 8.5
 * WC tested up to:   10.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'RAR_WCC_VERSION', '2.0.0' );
define( 'RAR_WCC_DB_VERSION', '2.0.0' );
define( 'RAR_WCC_FILE', __FILE__ );
define( 'RAR_WCC_DIR', plugin_dir_path( __FILE__ ) );
define( 'RAR_WCC_URL', plugin_dir_url( __FILE__ ) );
define( 'RAR_WCC_BASENAME', plugin_basename( __FILE__ ) );

require_once RAR_WCC_DIR . 'includes/class-rar-wcc-settings.php';
require_once RAR_WCC_DIR . 'includes/class-rar-wcc-phone.php';
require_once RAR_WCC_DIR . 'includes/class-rar-wcc-install.php';

register_activation_hook( __FILE__, array( 'RAR_WCC_Install', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'RAR_WCC_Install', 'deactivate' ) );

add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, false );
		}
	}
);

add_action(
	'plugins_loaded',
	static function () {
		load_plugin_textdomain( 'rar-woo-cart-checkout', false, dirname( RAR_WCC_BASENAME ) . '/languages' );

		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action(
				'admin_notices',
				static function () {
					if ( current_user_can( 'activate_plugins' ) ) {
						echo '<div class="notice notice-error"><p><strong>RAR Woo Cart &amp; Checkout</strong> ' . esc_html__( 'requires WooCommerce to be installed and active.', 'rar-woo-cart-checkout' ) . '</p></div>';
					}
				}
			);
			return;
		}

		RAR_WCC_Install::maybe_upgrade();

		$modules = array(
			'class-rar-wcc-locations.php',
			'class-rar-wcc-address.php',
			'class-rar-wcc-express.php',
			'class-rar-wcc-checkout.php',
			'class-rar-wcc-guard.php',
			'class-rar-wcc-incomplete.php',
			'class-rar-wcc-stats.php',
			'class-rar-wcc-rest.php',
		);
		foreach ( $modules as $file ) {
			require_once RAR_WCC_DIR . 'includes/' . $file;
		}

		RAR_WCC_Address::init();
		RAR_WCC_Express::init();
		RAR_WCC_Checkout::init();
		RAR_WCC_Guard::init();
		RAR_WCC_Incomplete::init();
		RAR_WCC_Rest::init();

		if ( is_admin() ) {
			require_once RAR_WCC_DIR . 'includes/admin/class-rar-wcc-admin.php';
			require_once RAR_WCC_DIR . 'includes/admin/class-rar-wcc-incomplete-table.php';
			RAR_WCC_Admin::init();
		}

		/**
		 * Fires after all RAR Woo Cart & Checkout modules are loaded.
		 */
		do_action( 'rar_wcc_loaded' );
	},
	20
);
