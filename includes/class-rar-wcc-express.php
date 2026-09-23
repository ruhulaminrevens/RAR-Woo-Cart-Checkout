<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RAR_WCC_Express {

	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ), 70 );
	}

	public static function assets() {
		if ( ! RAR_WCC_Settings::yes( 'express_enabled' ) || ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}

		wp_enqueue_style( 'rar-wcc-express', RAR_WCC_URL . 'assets/css/express.css', array(), RAR_WCC_VERSION );
		wp_enqueue_script( 'rar-wcc-express', RAR_WCC_URL . 'assets/js/express.js', array(), RAR_WCC_VERSION, true );

		wp_localize_script(
			'rar-wcc-express',
			'RAR_WCC_EXPRESS',
			array(
				'selector'        => RAR_WCC_Settings::get( 'express_button_selector', '.wd-buy-now-btn' ),
				'checkoutPath'    => RAR_WCC_Settings::get( 'express_checkout_path', '/checkout/' ),
				'title'           => RAR_WCC_Settings::get( 'express_modal_title', 'Express Checkout' ),
				'subtitle'        => RAR_WCC_Settings::get( 'express_modal_subtitle', 'Fast & secure checkout' ),
				'footerText'      => RAR_WCC_Settings::get( 'express_footer_text', 'Cash on Delivery · Nationwide Delivery' ),
				'openFullText'    => RAR_WCC_Settings::get( 'express_open_full_text', 'Open full checkout' ),
				'loadingText'     => RAR_WCC_Settings::get( 'express_loading_text', 'Preparing…' ),
				'loaderText'      => RAR_WCC_Settings::get( 'express_loader_text', 'Preparing secure checkout…' ),
				'deliveryHeading' => RAR_WCC_Settings::get( 'express_delivery_heading', 'Delivery Information' ),
				'orderHeading'    => RAR_WCC_Settings::get( 'express_order_heading', 'Order Summary' ),
				'hideAdditional'  => RAR_WCC_Settings::yes( 'express_hide_additional' ),
				'hideAccount'     => RAR_WCC_Settings::yes( 'express_hide_account' ),
				'primaryColor'    => RAR_WCC_Settings::get( 'express_primary_color', '#117865' ),
			)
		);
	}
}
