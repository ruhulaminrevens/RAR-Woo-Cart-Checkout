<?php
/**
 * Plugin Name: RAR Woo Cart & Checkout
 * Plugin URI: https://github.com/ruhulaminrevens/RAR-Woo-Cart-Checkout
 * Description: WooCommerce cart and checkout UX toolkit with Bangladesh address fields, searchable Town/City selection, and an optional Express Buy Now checkout modal.
 * Version: 1.0.0
 * Author: Ruhul Amin Revens
 * Text Domain: rar-woo-cart-checkout
 * Requires at least: 6.5
 * Requires PHP: 8.0
 * WC requires at least: 8.5
 * WC tested up to: 11.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'RAR_WCC_VERSION', '1.0.0' );
define( 'RAR_WCC_FILE', __FILE__ );
define( 'RAR_WCC_DIR', plugin_dir_path( __FILE__ ) );
define( 'RAR_WCC_URL', plugin_dir_url( __FILE__ ) );

require_once RAR_WCC_DIR . 'includes/class-rar-wcc-settings.php';

register_activation_hook(
	__FILE__,
	static function () {
		if ( false === get_option( RAR_WCC_Settings::OPTION ) ) {
			add_option( RAR_WCC_Settings::OPTION, RAR_WCC_Settings::defaults(), '', false );
		}
	}
);

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
		RAR_WCC_Settings::init();

		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action(
				'admin_notices',
				static function () {
					if ( current_user_can( 'activate_plugins' ) ) {
						echo '<div class="notice notice-error"><p><strong>RAR Woo Cart &amp; Checkout</strong> requires WooCommerce to be active.</p></div>';
					}
				}
			);
			return;
		}

		require_once RAR_WCC_DIR . 'includes/class-rar-wcc-address.php';
		require_once RAR_WCC_DIR . 'includes/class-rar-wcc-express.php';

		RAR_WCC_Address::init();
		RAR_WCC_Express::init();
	},
	20
);

add_filter(
	'plugin_action_links_' . plugin_basename( __FILE__ ),
	static function ( $links ) {
		$url = admin_url( 'admin.php?page=rar-wcc' );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'rar-woo-cart-checkout' ) . '</a>' );
		return $links;
	}
);
