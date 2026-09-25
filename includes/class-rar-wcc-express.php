<?php
/**
 * Express Buy Now: background add-to-cart + compact checkout modal.
 *
 * @package RAR_Woo_Cart_Checkout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RAR_WCC_Express {

	public const QUERY_VAR   = 'rar_wcc_express';
	public const SESSION_KEY = 'rar_wcc_express';

	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ), 70 );
		add_action( 'template_redirect', array( __CLASS__, 'frame_mode' ), 5 );
		add_action( 'wc_ajax_rar_wcc_express_add', array( __CLASS__, 'ajax_add' ) );
		add_action( 'woocommerce_after_add_to_cart_button', array( __CLASS__, 'render_button' ), 20 );
		add_action( 'woocommerce_checkout_create_order', array( __CLASS__, 'tag_order' ), 10, 2 );
		add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'clear_flag' ), 99 );
	}

	/**
	 * Is the current request the checkout page rendered inside the modal?
	 */
	public static function in_frame() {
		return isset( $_GET[ self::QUERY_VAR ] ) && function_exists( 'is_checkout' ) && is_checkout(); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	public static function checkout_url() {
		$path = RAR_WCC_Settings::get( 'express_checkout_path', '' );
		return $path ? home_url( $path ) : wc_get_checkout_url();
	}

	public static function frame_mode() {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || ! WC()->session ) {
			return;
		}
		if ( is_order_received_page() ) {
			return;
		}
		if ( self::in_frame() ) {
			WC()->session->set( self::SESSION_KEY, 1 );
			add_filter( 'show_admin_bar', '__return_false' );
			add_filter( 'body_class', array( __CLASS__, 'frame_body_class' ) );
		} elseif ( ! wp_doing_ajax() ) {
			WC()->session->set( self::SESSION_KEY, null );
		}
	}

	public static function frame_body_class( $classes ) {
		$classes[] = 'rar-wcc-in-frame';
		foreach ( array( 'express_hide_additional' => 'rar-wcc-hide-notes', 'express_hide_account' => 'rar-wcc-hide-account', 'express_hide_coupon' => 'rar-wcc-hide-coupon' ) as $key => $class ) {
			if ( RAR_WCC_Settings::yes( $key ) ) {
				$classes[] = $class;
			}
		}
		return $classes;
	}

	public static function primary_css() {
		$c = RAR_WCC_Settings::get( 'express_primary_color', '#117865' );
		$c = sanitize_hex_color( $c ) ? $c : '#117865';
		return ':root{--rar-wcc-primary:' . $c . ';}';
	}

	public static function assets() {
		if ( ! function_exists( 'is_product' ) ) {
			return;
		}

		// Break out of the modal on the thank-you page (covers every gateway).
		if ( is_order_received_page() ) {
			wp_register_script( 'rar-wcc-breakout', '', array(), RAR_WCC_VERSION, false );
			wp_enqueue_script( 'rar-wcc-breakout' );
			wp_add_inline_script( 'rar-wcc-breakout', 'try{if(window.top!==window.self&&window.top.location.origin===window.location.origin){window.top.location.replace(window.location.href);}}catch(e){}' );
			return;
		}

		if ( self::in_frame() ) {
			wp_enqueue_style( 'rar-wcc-frame', RAR_WCC_URL . 'assets/css/frame.css', array(), RAR_WCC_VERSION );
			wp_add_inline_style( 'rar-wcc-frame', self::primary_css() );
			wp_enqueue_script( 'rar-wcc-frame', RAR_WCC_URL . 'assets/js/frame.js', array( 'jquery' ), RAR_WCC_VERSION, true );
			return;
		}

		if ( ! RAR_WCC_Settings::yes( 'express_enabled' ) || ! is_product() ) {
			return;
		}

		wp_enqueue_style( 'rar-wcc-express', RAR_WCC_URL . 'assets/css/express.css', array(), RAR_WCC_VERSION );
		wp_add_inline_style( 'rar-wcc-express', self::primary_css() );
		wp_enqueue_script( 'rar-wcc-express', RAR_WCC_URL . 'assets/js/express.js', array(), RAR_WCC_VERSION, true );

		$selector = RAR_WCC_Settings::get( 'express_button_selector', '.wd-buy-now-btn' );
		if ( RAR_WCC_Settings::yes( 'express_add_button' ) ) {
			$selector .= ', .rar-wcc-buy-now';
		}

		wp_localize_script(
			'rar-wcc-express',
			'RAR_WCC_EXPRESS',
			array(
				'selector'     => $selector,
				'ajaxUrl'      => WC_AJAX::get_endpoint( 'rar_wcc_express_add' ),
				'checkoutUrl'  => add_query_arg( self::QUERY_VAR, '1', self::checkout_url() ),
				'fullCheckout' => self::checkout_url(),
				'title'        => RAR_WCC_Settings::text( 'express_modal_title' ),
				'subtitle'     => RAR_WCC_Settings::text( 'express_modal_subtitle' ),
				'footerText'   => RAR_WCC_Settings::get( 'express_footer_text', '' ),
				'openFullText' => RAR_WCC_Settings::text( 'express_open_full_text' ),
				'loadingText'  => RAR_WCC_Settings::text( 'express_loading_text' ),
				'loaderText'   => RAR_WCC_Settings::text( 'express_loader_text' ),
				'i18n'         => array(
					'close'       => __( 'Close checkout', 'rar-woo-cart-checkout' ),
					'error'       => __( 'Could not add this product. Please try again.', 'rar-woo-cart-checkout' ),
					'retry'       => __( 'Try again', 'rar-woo-cart-checkout' ),
					'slow'        => __( 'Taking longer than usual…', 'rar-woo-cart-checkout' ),
					'chooseFirst' => __( 'Please choose product options first.', 'rar-woo-cart-checkout' ),
				),
			)
		);
	}

	/**
	 * Optional Buy Now button for themes that lack one.
	 */
	public static function render_button() {
		if ( ! RAR_WCC_Settings::yes( 'express_enabled' ) || ! RAR_WCC_Settings::yes( 'express_add_button' ) ) {
			return;
		}
		global $product;
		if ( ! $product instanceof WC_Product || ! $product->is_purchasable() || ! in_array( $product->get_type(), array( 'simple', 'variable' ), true ) ) {
			return;
		}
		printf(
			'<button type="button" class="button alt rar-wcc-buy-now" value="%1$d">%2$s</button>',
			(int) $product->get_id(),
			esc_html( RAR_WCC_Settings::text( 'express_button_text' ) )
		);
	}

	private static function fail( $message, $fallback = false ) {
		wc_clear_notices();
		wp_send_json_error(
			array(
				'message'  => wp_strip_all_tags( html_entity_decode( (string) $message, ENT_QUOTES, 'UTF-8' ) ),
				'fallback' => $fallback,
			)
		);
	}

	/**
	 * Background add-to-cart. Accepts the full product form so add-on plugins
	 * that read $_POST (gift wrap, custom fields…) keep working.
	 */
	public static function ajax_add() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- mirrors WooCommerce core add_to_cart AJAX (no nonce; cart is session-scoped).
		wc_nocache_headers();

		if ( ! RAR_WCC_Settings::yes( 'express_enabled' ) ) {
			self::fail( '', true );
		}

		$product_id   = absint( wp_unslash( $_POST['rar_product_id'] ?? 0 ) );
		$variation_id = absint( wp_unslash( $_POST['variation_id'] ?? 0 ) );
		$quantity     = wc_stock_amount( wp_unslash( $_POST['quantity'] ?? 1 ) );
		$quantity     = $quantity > 0 ? $quantity : 1;

		$product = wc_get_product( $variation_id ? $variation_id : $product_id );
		if ( ! $product ) {
			self::fail( __( 'Product not found.', 'rar-woo-cart-checkout' ), true );
		}

		if ( $product->is_type( 'variation' ) ) {
			$variation_id = $product->get_id();
			$product_id   = $product->get_parent_id();
		}

		$parent = wc_get_product( $product_id );
		if ( ! $parent || in_array( $parent->get_type(), array( 'external', 'grouped' ), true ) ) {
			self::fail( '', true );
		}

		$variations = array();
		foreach ( $_POST as $key => $value ) {
			if ( is_string( $key ) && str_starts_with( $key, 'attribute_' ) && is_scalar( $value ) ) {
				$variations[ sanitize_title( wp_unslash( $key ) ) ] = wc_clean( wp_unslash( $value ) );
			}
		}

		if ( $parent->is_type( 'variable' ) && ! $variation_id ) {
			$variation_id = ( new WC_Product_Data_Store_CPT() )->find_matching_product_variation( $parent, $variations );
			if ( ! $variation_id ) {
				self::fail( __( 'Please choose product options first.', 'rar-woo-cart-checkout' ) );
			}
		}

		if ( $variation_id ) {
			$variation = wc_get_product( $variation_id );
			if ( $variation ) {
				foreach ( $variation->get_variation_attributes() as $attr => $val ) {
					if ( '' !== $val ) {
						$variations[ $attr ] = $val;
					}
				}
			}
		}
		// phpcs:enable

		if ( 'clear' === RAR_WCC_Settings::get( 'express_clear_cart' ) ) {
			WC()->cart->empty_cart();
		}

		$passed = apply_filters( 'woocommerce_add_to_cart_validation', true, $product_id, $quantity, $variation_id, $variations );
		if ( ! $passed ) {
			$errors = wc_get_notices( 'error' );
			self::fail( $errors ? $errors[0]['notice'] : __( 'Could not add this product. Please try again.', 'rar-woo-cart-checkout' ) );
		}

		// Tapping Buy Now twice should not double the quantity.
		$existing = WC()->cart->find_product_in_cart( WC()->cart->generate_cart_id( $product_id, $variation_id, $variations ) );
		if ( $existing ) {
			$ok  = WC()->cart->set_quantity( $existing, $quantity, true );
			$key = $ok ? $existing : false;
		} else {
			try {
				$key = WC()->cart->add_to_cart( $product_id, $quantity, $variation_id, $variations );
			} catch ( Exception $e ) {
				$key = false;
				wc_add_notice( $e->getMessage(), 'error' );
			}
		}

		if ( ! $key ) {
			$errors = wc_get_notices( 'error' );
			self::fail( $errors ? $errors[0]['notice'] : __( 'Could not add this product. Please try again.', 'rar-woo-cart-checkout' ) );
		}

		do_action( 'woocommerce_ajax_added_to_cart', $product_id );
		WC()->session->set( self::SESSION_KEY, 1 );
		wc_clear_notices();

		wp_send_json_success(
			array(
				'cart_key'   => $key,
				'cart_count' => WC()->cart->get_cart_contents_count(),
				'cart_hash'  => WC()->cart->get_cart_hash(),
			)
		);
	}

	public static function tag_order( $order, $data ) {
		$express = WC()->session && WC()->session->get( self::SESSION_KEY );
		$order->update_meta_data( '_rar_wcc_source', $express ? 'express' : 'checkout' );
	}

	public static function clear_flag() {
		if ( WC()->session ) {
			WC()->session->set( self::SESSION_KEY, null );
		}
	}
}
