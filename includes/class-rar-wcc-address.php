<?php
/**
 * Bangladesh checkout / cart address experience and phone validation.
 *
 * @package RAR_Woo_Cart_Checkout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RAR_WCC_Address {

	public static function init() {
		add_filter( 'woocommerce_checkout_fields', array( __CLASS__, 'checkout_fields' ), 999 );
		add_filter( 'woocommerce_get_country_locale', array( __CLASS__, 'country_locale' ), 999 );
		add_filter( 'woocommerce_checkout_posted_data', array( __CLASS__, 'posted_data' ), 999 );
		add_action( 'woocommerce_after_checkout_validation', array( __CLASS__, 'validate' ), 10, 2 );
		add_filter( 'woocommerce_ship_to_different_address_checked', array( __CLASS__, 'ship_to_different' ), 999 );
		add_filter( 'woocommerce_cart_needs_shipping_address', array( __CLASS__, 'needs_shipping_address' ), 999 );
		add_filter( 'woocommerce_enable_order_notes_field', array( __CLASS__, 'order_notes_enabled' ), 999 );
		add_filter( 'woocommerce_shipping_calculator_enable_postcode', array( __CLASS__, 'cart_postcode' ), 999 );
		add_filter( 'woocommerce_process_myaccount_field_billing_phone', array( __CLASS__, 'format_phone' ) );
		add_action( 'woocommerce_checkout_create_order', array( __CLASS__, 'copy_to_shipping' ), 20, 2 );
		add_action( 'template_redirect', array( __CLASS__, 'register_gettext' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ), 60 );
	}

	private static function on() {
		return RAR_WCC_Settings::yes( 'address_enabled' );
	}

	/**
	 * Target priorities for the chosen field order.
	 */
	private static function priorities() {
		if ( 'name_first' === RAR_WCC_Settings::get( 'field_order' ) ) {
			return array( 'first_name' => 10, 'phone' => 20, 'email' => 30, 'address_1' => 40, 'city' => 50, 'state' => 60 );
		}
		return array( 'first_name' => 10, 'phone' => 20, 'state' => 30, 'city' => 40, 'address_1' => 50, 'email' => 60 );
	}

	public static function checkout_fields( $fields ) {
		if ( ! self::on() || empty( $fields['billing'] ) ) {
			return $fields;
		}

		$b = &$fields['billing'];
		$p = self::priorities();

		if ( isset( $b['billing_country'] ) && RAR_WCC_Settings::yes( 'hide_country' ) ) {
			$b['billing_country']['type']     = 'hidden';
			$b['billing_country']['default']  = 'BD';
			$b['billing_country']['priority'] = 1;
			$b['billing_country']['required'] = false;
		}

		if ( RAR_WCC_Settings::yes( 'remove_optional_fields' ) ) {
			unset( $b['billing_last_name'], $b['billing_company'], $b['billing_address_2'], $b['billing_postcode'] );
		}

		$map = array(
			'billing_first_name' => array( 'label_full_name', 'name_placeholder', 'first_name', 'name' ),
			'billing_phone'      => array( 'label_phone', 'phone_placeholder', 'phone', 'tel' ),
			'billing_email'      => array( 'label_email', '', 'email', 'email' ),
			'billing_address_1'  => array( 'label_street', 'street_placeholder', 'address_1', 'street-address' ),
			'billing_city'       => array( 'label_city', 'city_placeholder', 'city', 'address-level2' ),
			'billing_state'      => array( 'label_district', '', 'state', 'address-level1' ),
		);

		foreach ( $map as $key => $cfg ) {
			if ( ! isset( $b[ $key ] ) ) {
				continue;
			}
			$b[ $key ]['label'] = RAR_WCC_Settings::text( $cfg[0] );
			if ( $cfg[1] ) {
				$b[ $key ]['placeholder'] = RAR_WCC_Settings::text( $cfg[1] );
			}
			$b[ $key ]['priority']     = $p[ $cfg[2] ];
			$b[ $key ]['class']        = array( 'form-row-wide', 'rar-wcc-field-' . $cfg[2] );
			$b[ $key ]['autocomplete'] = $cfg[3];
		}

		if ( isset( $b['billing_phone'] ) ) {
			$b['billing_phone']['required']          = true;
			$b['billing_phone']['custom_attributes'] = array_merge(
				(array) ( $b['billing_phone']['custom_attributes'] ?? array() ),
				array( 'inputmode' => 'tel', 'maxlength' => '20' )
			);
		}
		if ( isset( $b['billing_email'] ) && RAR_WCC_Settings::yes( 'email_optional' ) ) {
			$b['billing_email']['required'] = false;
		}
		if ( isset( $b['billing_first_name'] ) ) {
			$b['billing_first_name']['required'] = true;
		}

		if ( isset( $fields['order']['order_comments'] ) ) {
			$fields['order']['order_comments']['label'] = RAR_WCC_Settings::text( 'order_notes_label' );
		}

		return $fields;
	}

	/**
	 * WooCommerce's address-i18n.js re-applies locale labels/priorities on load,
	 * which silently reverted custom labels in v1. Registering them on the BD
	 * locale keeps them in place.
	 */
	public static function country_locale( $locale ) {
		if ( ! self::on() && ! RAR_WCC_Settings::yes( 'cart_address_enabled' ) ) {
			return $locale;
		}
		$p  = self::priorities();
		$bd = $locale['BD'] ?? array();

		$bd['first_name'] = array_merge( $bd['first_name'] ?? array(), array( 'label' => RAR_WCC_Settings::text( 'label_full_name' ), 'priority' => $p['first_name'], 'required' => true ) );
		$bd['address_1']  = array_merge( $bd['address_1'] ?? array(), array( 'label' => RAR_WCC_Settings::text( 'label_street' ), 'placeholder' => RAR_WCC_Settings::text( 'street_placeholder' ), 'priority' => $p['address_1'] ) );
		$bd['city']       = array_merge( $bd['city'] ?? array(), array( 'label' => RAR_WCC_Settings::text( 'label_city' ), 'priority' => $p['city'] ) );
		$bd['state']      = array_merge( $bd['state'] ?? array(), array( 'label' => RAR_WCC_Settings::text( 'label_district' ), 'priority' => $p['state'], 'required' => true ) );

		if ( RAR_WCC_Settings::yes( 'remove_optional_fields' ) ) {
			foreach ( array( 'last_name', 'company', 'address_2', 'postcode' ) as $k ) {
				$bd[ $k ] = array_merge( $bd[ $k ] ?? array(), array( 'required' => false, 'hidden' => true ) );
			}
		}

		$locale['BD'] = $bd;
		return $locale;
	}

	public static function ship_to_different( $checked ) {
		return self::on() && RAR_WCC_Settings::yes( 'hide_ship_different' ) ? false : $checked;
	}

	public static function needs_shipping_address( $needs ) {
		return self::on() && RAR_WCC_Settings::yes( 'hide_ship_different' ) ? false : $needs;
	}

	public static function order_notes_enabled( $enabled ) {
		return self::on() ? RAR_WCC_Settings::yes( 'show_order_notes' ) : $enabled;
	}

	public static function cart_postcode( $enabled ) {
		return RAR_WCC_Settings::yes( 'cart_address_enabled' ) && RAR_WCC_Settings::yes( 'remove_optional_fields' ) ? false : $enabled;
	}

	public static function format_phone( $value ) {
		return RAR_WCC_Settings::yes( 'phone_validation' ) ? RAR_WCC_Phone::format( $value ) : $value;
	}

	public static function posted_data( $data ) {
		if ( ! self::on() ) {
			return $data;
		}
		if ( RAR_WCC_Settings::yes( 'hide_country' ) ) {
			$data['billing_country'] = 'BD';
			if ( empty( $data['shipping_country'] ) ) {
				$data['shipping_country'] = 'BD';
			}
		}
		if ( ! empty( $data['billing_phone'] ) ) {
			$data['billing_phone'] = RAR_WCC_Phone::format( $data['billing_phone'] );
		}
		if ( ! empty( $data['billing_first_name'] ) ) {
			$data['billing_first_name'] = trim( preg_replace( '/\s+/', ' ', $data['billing_first_name'] ) );
		}
		return $data;
	}

	/**
	 * Server-side validation (runs even if JavaScript is bypassed).
	 */
	public static function validate( $data, $errors ) {
		if ( ! self::on() ) {
			return;
		}

		if ( RAR_WCC_Settings::yes( 'phone_validation' ) && ! empty( $data['billing_phone'] ) && ! RAR_WCC_Phone::is_valid( $data['billing_phone'] ) ) {
			$errors->add(
				'billing_phone_validation',
				sprintf(
					/* translators: %s: field label */
					__( '%s must be a valid Bangladeshi mobile number, e.g. 01712345678.', 'rar-woo-cart-checkout' ),
					'<strong>' . esc_html( RAR_WCC_Settings::text( 'label_phone' ) ) . '</strong>'
				),
				array( 'id' => 'billing_phone' )
			);
		}

		if ( RAR_WCC_Settings::yes( 'city_validation' ) && ! RAR_WCC_Settings::yes( 'city_allow_custom' )
			&& ! empty( $data['billing_city'] ) && ! empty( $data['billing_state'] ) && 'BD' === ( $data['billing_country'] ?? 'BD' )
			&& ! RAR_WCC_Locations::city_in_district( $data['billing_city'], $data['billing_state'] ) ) {
			$errors->add(
				'billing_city_validation',
				sprintf(
					/* translators: 1: city label, 2: district label */
					__( 'Please choose a %1$s that belongs to the selected %2$s.', 'rar-woo-cart-checkout' ),
					'<strong>' . esc_html( RAR_WCC_Settings::text( 'label_city' ) ) . '</strong>',
					esc_html( RAR_WCC_Settings::text( 'label_district' ) )
				),
				array( 'id' => 'billing_city' )
			);
		}
	}

	/**
	 * Keep shipping address = billing address so courier plugins read a full address.
	 */
	public static function copy_to_shipping( $order, $data ) {
		if ( ! self::on() || ! RAR_WCC_Settings::yes( 'hide_ship_different' ) ) {
			return;
		}
		if ( $order->get_shipping_address_1() && $order->get_shipping_city() ) {
			return;
		}
		$order->set_shipping_first_name( $order->get_billing_first_name() );
		$order->set_shipping_last_name( $order->get_billing_last_name() );
		$order->set_shipping_address_1( $order->get_billing_address_1() );
		$order->set_shipping_address_2( $order->get_billing_address_2() );
		$order->set_shipping_city( $order->get_billing_city() );
		$order->set_shipping_state( $order->get_billing_state() );
		$order->set_shipping_postcode( $order->get_billing_postcode() );
		$order->set_shipping_country( $order->get_billing_country() ? $order->get_billing_country() : 'BD' );
		if ( method_exists( $order, 'set_shipping_phone' ) ) {
			$order->set_shipping_phone( $order->get_billing_phone() );
		}
	}

	/**
	 * Replace WooCommerce headings server-side (no text flicker).
	 */
	public static function register_gettext() {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_order_received_page() ) {
			return;
		}
		add_filter( 'gettext_woocommerce', array( __CLASS__, 'gettext' ), 20, 2 );
		add_filter( 'woocommerce_checkout_coupon_message', array( __CLASS__, 'coupon_message' ) );
	}

	public static function gettext( $translation, $text ) {
		$express = RAR_WCC_Express::in_frame();
		switch ( $text ) {
			case 'Billing details':
			case 'Billing &amp; Shipping':
				if ( $express ) {
					return esc_html( RAR_WCC_Settings::text( 'express_delivery_heading' ) );
				}
				return self::on() ? esc_html( RAR_WCC_Settings::text( 'billing_heading' ) ) : $translation;
			case 'Additional information':
				return self::on() ? esc_html( RAR_WCC_Settings::text( 'additional_heading' ) ) : $translation;
			case 'Your order':
				return $express ? esc_html( RAR_WCC_Settings::text( 'express_order_heading' ) ) : $translation;
		}
		return $translation;
	}

	public static function coupon_message( $message ) {
		$label = trim( (string) RAR_WCC_Settings::get( 'coupon_label' ) );
		if ( '' === $label ) {
			return $message;
		}
		return '<a href="#" role="button" aria-label="' . esc_attr( $label ) . '" aria-controls="woocommerce-checkout-form-coupon" aria-expanded="false" class="showcoupon">' . esc_html( $label ) . '</a>';
	}

	public static function assets() {
		$is_checkout = function_exists( 'is_checkout' ) && is_checkout() && ! is_order_received_page();
		$is_cart     = function_exists( 'is_cart' ) && is_cart();

		if ( ( ! $is_checkout || ! self::on() ) && ( ! $is_cart || ! RAR_WCC_Settings::yes( 'cart_address_enabled' ) ) ) {
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
				'cityMap'           => RAR_WCC_Locations::map(),
				'aliases'           => RAR_WCC_Locations::aliases(),
				'searchableCity'    => RAR_WCC_Settings::yes( 'searchable_city' ),
				'allowCustomCity'   => RAR_WCC_Settings::yes( 'city_allow_custom' ),
				'hideCountry'       => RAR_WCC_Settings::yes( 'hide_country' ),
				'showOrderNotes'    => RAR_WCC_Settings::yes( 'show_order_notes' ),
				'billingHeading'    => RAR_WCC_Settings::text( 'billing_heading' ),
				'additionalHeading' => RAR_WCC_Settings::text( 'additional_heading' ),
				'orderNotesLabel'   => RAR_WCC_Settings::text( 'order_notes_label' ),
				'cityPlaceholder'   => RAR_WCC_Settings::text( 'city_placeholder' ),
				'checkoutEnabled'   => $is_checkout && self::on(),
				'cartEnabled'       => $is_cart && RAR_WCC_Settings::yes( 'cart_address_enabled' ),
				'phoneValidation'   => RAR_WCC_Settings::yes( 'phone_validation' ),
				'operatorHint'      => RAR_WCC_Settings::yes( 'phone_operator_hint' ),
				'inFrame'           => RAR_WCC_Express::in_frame(),
				'i18n'              => array(
					'selectCity'   => __( 'Select town / city', 'rar-woo-cart-checkout' ),
					'selectFirst'  => __( 'Select district first, or search all areas', 'rar-woo-cart-checkout' ),
					/* translators: %s: text typed by the customer */
					'useTyped'     => __( 'Use “%s”', 'rar-woo-cart-checkout' ),
					'phoneInvalid' => __( 'Enter an 11-digit mobile number starting with 01', 'rar-woo-cart-checkout' ),
					'phoneOk'      => __( 'Valid number', 'rar-woo-cart-checkout' ),
				),
			)
		);
	}
}
