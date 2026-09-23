<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RAR_WCC_Address {

	public static function init() {
		add_filter( 'woocommerce_checkout_fields', array( __CLASS__, 'checkout_fields' ), 999 );
		add_filter( 'woocommerce_checkout_posted_data', array( __CLASS__, 'posted_data' ), 999 );
		add_filter( 'woocommerce_ship_to_different_address_checked', array( __CLASS__, 'ship_to_different' ), 999 );
		add_filter( 'woocommerce_enable_order_notes_field', array( __CLASS__, 'order_notes_enabled' ), 999 );
		add_filter( 'woocommerce_shipping_calculator_enable_postcode', array( __CLASS__, 'cart_postcode' ), 999 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ), 60 );
	}

	public static function checkout_fields( $fields ) {
		if ( ! RAR_WCC_Settings::yes( 'address_enabled' ) || empty( $fields['billing'] ) ) {
			return $fields;
		}

		$b =& $fields['billing'];

		if ( isset( $b['billing_country'] ) && RAR_WCC_Settings::yes( 'hide_country' ) ) {
			$b['billing_country']['type']     = 'hidden';
			$b['billing_country']['default']  = 'BD';
			$b['billing_country']['priority'] = 1;
		}

		if ( isset( $b['billing_first_name'] ) ) {
			$b['billing_first_name']['label']       = RAR_WCC_Settings::get( 'label_full_name', 'Full name' );
			$b['billing_first_name']['placeholder'] = __( 'Enter your full name', 'rar-woo-cart-checkout' );
			$b['billing_first_name']['priority']    = 10;
			$b['billing_first_name']['class']       = array( 'form-row-wide' );
		}

		if ( RAR_WCC_Settings::yes( 'remove_optional_fields' ) ) {
			unset(
				$b['billing_last_name'],
				$b['billing_company'],
				$b['billing_address_2'],
				$b['billing_postcode']
			);
		}

		if ( isset( $b['billing_phone'] ) ) {
			$b['billing_phone']['label']       = RAR_WCC_Settings::get( 'label_phone', 'Phone' );
			$b['billing_phone']['placeholder'] = '+8801XXXXXXXXX';
			$b['billing_phone']['priority']    = 20;
			$b['billing_phone']['class']       = array( 'form-row-wide' );
		}

		if ( isset( $b['billing_email'] ) ) {
			$b['billing_email']['label']       = RAR_WCC_Settings::get( 'label_email', 'Email address' );
			$b['billing_email']['placeholder'] = 'name@example.com';
			$b['billing_email']['priority']    = 30;
			$b['billing_email']['class']       = array( 'form-row-wide' );
		}

		if ( isset( $b['billing_address_1'] ) ) {
			$b['billing_address_1']['label']       = RAR_WCC_Settings::get( 'label_street', 'Street address' );
			$b['billing_address_1']['placeholder'] = __( 'House / Road / Area', 'rar-woo-cart-checkout' );
			$b['billing_address_1']['priority']    = 40;
			$b['billing_address_1']['class']       = array( 'form-row-wide' );
		}

		if ( isset( $b['billing_city'] ) ) {
			$b['billing_city']['label']       = RAR_WCC_Settings::get( 'label_city', 'Town / City' );
			$b['billing_city']['placeholder'] = RAR_WCC_Settings::get( 'city_placeholder', 'Search town / city…' );
			$b['billing_city']['priority']    = 50;
			$b['billing_city']['class']       = array( 'form-row-wide' );
		}

		if ( isset( $b['billing_state'] ) ) {
			$b['billing_state']['label']    = RAR_WCC_Settings::get( 'label_district', 'District' );
			$b['billing_state']['priority'] = 60;
			$b['billing_state']['class']    = array( 'form-row-wide' );
		}

		return $fields;
	}

	public static function ship_to_different( $checked ) {
		return RAR_WCC_Settings::yes( 'address_enabled' ) && RAR_WCC_Settings::yes( 'hide_ship_different' ) ? false : $checked;
	}

	public static function order_notes_enabled( $enabled ) {
		if ( ! RAR_WCC_Settings::yes( 'address_enabled' ) ) {
			return $enabled;
		}
		return RAR_WCC_Settings::yes( 'show_order_notes' );
	}

	public static function posted_data( $data ) {
		if ( ! RAR_WCC_Settings::yes( 'address_enabled' ) || ! RAR_WCC_Settings::yes( 'hide_country' ) ) {
			return $data;
		}
		$data['billing_country'] = 'BD';
		if ( empty( $data['shipping_country'] ) ) {
			$data['shipping_country'] = 'BD';
		}
		return $data;
	}

	public static function cart_postcode( $enabled ) {
		if ( RAR_WCC_Settings::yes( 'cart_address_enabled' ) && RAR_WCC_Settings::yes( 'remove_optional_fields' ) ) {
			return false;
		}
		return $enabled;
	}

	private static function load_city_map() {
		$file = RAR_WCC_DIR . 'assets/data/bd-cities.json';
		if ( ! is_readable( $file ) ) {
			return array();
		}
		$json = file_get_contents( $file );
		$map  = json_decode( $json, true );
		return is_array( $map ) ? $map : array();
	}

	public static function assets() {
		$is_checkout = function_exists( 'is_checkout' ) && is_checkout() && ! is_order_received_page();
		$is_cart     = function_exists( 'is_cart' ) && is_cart();

		if ( ( ! $is_checkout || ! RAR_WCC_Settings::yes( 'address_enabled' ) ) && ( ! $is_cart || ! RAR_WCC_Settings::yes( 'cart_address_enabled' ) ) ) {
			return;
		}

		wp_enqueue_style( 'rar-wcc-address', RAR_WCC_URL . 'assets/css/address.css', array(), RAR_WCC_VERSION );
		wp_enqueue_script( 'selectWoo' );
		wp_enqueue_style( 'select2' );
		wp_enqueue_script( 'rar-wcc-address', RAR_WCC_URL . 'assets/js/address.js', array( 'jquery' ), RAR_WCC_VERSION, true );

		wp_localize_script(
			'rar-wcc-address',
			'RAR_WCC_ADDRESS',
			array(
				'cityMap'           => self::load_city_map(),
				'searchableCity'    => RAR_WCC_Settings::yes( 'searchable_city' ),
				'hideCountry'       => RAR_WCC_Settings::yes( 'hide_country' ),
				'hideShipDifferent' => RAR_WCC_Settings::yes( 'hide_ship_different' ),
				'showOrderNotes'    => RAR_WCC_Settings::yes( 'show_order_notes' ),
				'billingHeading'    => RAR_WCC_Settings::get( 'billing_heading', 'Billing Details' ),
				'additionalHeading' => RAR_WCC_Settings::get( 'additional_heading', 'Additional Information' ),
				'orderNotesLabel'   => RAR_WCC_Settings::get( 'order_notes_label', 'Order notes' ),
				'cityPlaceholder'   => RAR_WCC_Settings::get( 'city_placeholder', 'Search town / city…' ),
				'checkoutEnabled'   => RAR_WCC_Settings::yes( 'address_enabled' ),
				'cartEnabled'       => RAR_WCC_Settings::yes( 'cart_address_enabled' ),
			)
		);
	}
}
