<?php
/**
 * Cart & checkout enhancements: quantity editor, free-delivery progress,
 * Place Order text and trust line.
 *
 * @package RAR_Woo_Cart_Checkout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RAR_WCC_Checkout {

	public static function init() {
		add_filter( 'woocommerce_checkout_cart_item_quantity', array( __CLASS__, 'qty_controls' ), 20, 3 );
		add_action( 'wc_ajax_rar_wcc_update_qty', array( __CLASS__, 'ajax_update_qty' ) );
		add_filter( 'woocommerce_order_button_text', array( __CLASS__, 'order_button_text' ), 20 );
		add_action( 'woocommerce_review_order_after_submit', array( __CLASS__, 'trust_line' ) );
		add_action( 'woocommerce_review_order_before_shipping', array( __CLASS__, 'fs_row' ) );
		add_action( 'woocommerce_cart_totals_before_shipping', array( __CLASS__, 'fs_row' ) );
		add_action( 'woocommerce_widget_shopping_cart_before_buttons', array( __CLASS__, 'fs_block' ) );
		add_filter( 'woocommerce_package_rates', array( __CLASS__, 'free_rate_handling' ), 100, 2 );
		add_filter( 'woocommerce_shipping_chosen_method', array( __CLASS__, 'default_free_rate' ), 20, 3 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ), 65 );
	}

	/**
	 * Show free delivery first (or exclusively) once it is unlocked.
	 */
	public static function free_rate_handling( $rates, $package ) {
		$mode = RAR_WCC_Settings::get( 'fs_unlock_mode', 'select' );
		if ( 'none' === $mode ) {
			return $rates;
		}
		$free  = array();
		$other = array();
		foreach ( $rates as $id => $rate ) {
			if ( 'free_shipping' === $rate->get_method_id() ) {
				$free[ $id ] = $rate;
			} elseif ( 'hide' !== $mode || 'local_pickup' === $rate->get_method_id() ) {
				$other[ $id ] = $rate;
			}
		}
		return $free ? $free + $other : $rates;
	}

	/**
	 * WooCommerce re-picks the default rate whenever the available rates change
	 * (e.g. the moment free delivery unlocks). Make that default the free rate.
	 * A rate the customer picks afterwards is respected.
	 */
	public static function default_free_rate( $default, $rates, $chosen ) {
		if ( 'select' !== RAR_WCC_Settings::get( 'fs_unlock_mode', 'select' ) ) {
			return $default;
		}
		foreach ( (array) $rates as $id => $rate ) {
			if ( is_object( $rate ) && 'free_shipping' === $rate->get_method_id() ) {
				return $id;
			}
		}
		return $default;
	}

	/* ── Quantity editor ─────────────────────────────────────────────── */

	public static function qty_controls( $html, $cart_item, $cart_item_key ) {
		if ( ! RAR_WCC_Settings::yes( 'qty_editor' ) || empty( $cart_item['data'] ) ) {
			return $html;
		}
		$product = $cart_item['data'];
		$qty     = (int) $cart_item['quantity'];
		$max     = $product->get_max_purchase_quantity();
		$single  = $product->is_sold_individually();

		ob_start();
		?>
		<span class="rar-wcc-qty" data-key="<?php echo esc_attr( $cart_item_key ); ?>" data-max="<?php echo esc_attr( $max > 0 ? $max : 0 ); ?>">
			<?php if ( ! $single ) : ?>
				<button type="button" class="rar-wcc-qty-btn" data-step="-1" aria-label="<?php esc_attr_e( 'Decrease quantity', 'rar-woo-cart-checkout' ); ?>" <?php disabled( $qty <= 1 ); ?>>−</button>
				<span class="rar-wcc-qty-val" aria-live="polite"><?php echo esc_html( $qty ); ?></span>
				<button type="button" class="rar-wcc-qty-btn" data-step="1" aria-label="<?php esc_attr_e( 'Increase quantity', 'rar-woo-cart-checkout' ); ?>" <?php disabled( $max > 0 && $qty >= $max ); ?>>+</button>
			<?php else : ?>
				<span class="rar-wcc-qty-val">× <?php echo esc_html( $qty ); ?></span>
			<?php endif; ?>
			<button type="button" class="rar-wcc-qty-remove" aria-label="<?php esc_attr_e( 'Remove item', 'rar-woo-cart-checkout' ); ?>" title="<?php esc_attr_e( 'Remove', 'rar-woo-cart-checkout' ); ?>">×</button>
		</span>
		<?php
		return ob_get_clean();
	}

	public static function ajax_update_qty() {
		check_ajax_referer( 'rar-wcc-checkout', 'nonce' );
		$key = sanitize_text_field( wp_unslash( $_POST['key'] ?? '' ) );
		$qty = max( 0, (int) wp_unslash( $_POST['qty'] ?? 0 ) );

		$item = WC()->cart->get_cart_item( $key );
		if ( ! $item ) {
			wp_send_json_error( array( 'message' => __( 'This item is no longer in your cart.', 'rar-woo-cart-checkout' ) ) );
		}

		$max = $item['data']->get_max_purchase_quantity();
		if ( $max > 0 && $qty > $max ) {
			/* translators: %d: maximum quantity */
			wp_send_json_error( array( 'message' => sprintf( __( 'Only %d available.', 'rar-woo-cart-checkout' ), $max ) ) );
		}

		if ( 0 === $qty ) {
			WC()->cart->remove_cart_item( $key );
		} else {
			$passed = apply_filters( 'woocommerce_update_cart_validation', true, $key, $item, $qty );
			if ( ! $passed ) {
				$errors = wc_get_notices( 'error' );
				wc_clear_notices();
				wp_send_json_error( array( 'message' => $errors ? wp_strip_all_tags( $errors[0]['notice'] ) : __( 'Could not update quantity.', 'rar-woo-cart-checkout' ) ) );
			}
			WC()->cart->set_quantity( $key, $qty, true );
		}
		wc_clear_notices();
		wp_send_json_success(
			array(
				'empty'  => WC()->cart->is_empty(),
				'count'  => WC()->cart->get_cart_contents_count(),
				// Lets the page sync its shipping radios before update_checkout re-posts them.
				'chosen' => array_values( (array) WC()->session->get( 'chosen_shipping_methods', array() ) ),
			)
		);
	}

	/* ── Place order button & trust line ─────────────────────────────── */

	public static function order_button_text( $text ) {
		$custom = trim( (string) RAR_WCC_Settings::get( 'place_order_text' ) );
		if ( '' === $custom ) {
			return $text;
		}
		if ( false !== strpos( $custom, '{total}' ) ) {
			$total  = WC()->cart ? wp_strip_all_tags( html_entity_decode( wc_price( WC()->cart->get_total( 'edit' ) ), ENT_QUOTES, 'UTF-8' ) ) : '';
			$custom = str_replace( '{total}', $total, $custom );
		}
		return $custom;
	}

	public static function trust_line() {
		if ( ! RAR_WCC_Settings::yes( 'trust_enabled' ) ) {
			return;
		}
		$text = trim( (string) RAR_WCC_Settings::get( 'trust_text' ) );
		if ( '' !== $text ) {
			echo '<p class="rar-wcc-trust">' . esc_html( $text ) . '</p>';
		}
	}

	/* ── Free-delivery progress ──────────────────────────────────────── */

	/**
	 * Threshold: manual setting, otherwise the matching zone's free-shipping minimum.
	 */
	public static function fs_threshold() {
		$manual = RAR_WCC_Settings::num( 'fs_amount' );
		if ( $manual > 0 ) {
			return $manual;
		}
		if ( ! WC()->cart || ! WC()->customer ) {
			return 0;
		}

		$package = array(
			'destination' => array(
				'country'  => WC()->customer->get_shipping_country() ? WC()->customer->get_shipping_country() : 'BD',
				'state'    => WC()->customer->get_shipping_state(),
				'postcode' => WC()->customer->get_shipping_postcode(),
				'city'     => WC()->customer->get_shipping_city(),
			),
		);
		$zone = WC_Shipping_Zones::get_zone_matching_package( $package );
		$min  = 0;
		foreach ( $zone->get_shipping_methods( true ) as $method ) {
			if ( 'free_shipping' === $method->id && in_array( $method->get_option( 'requires' ), array( 'min_amount', 'either' ), true ) ) {
				$amount = (float) $method->get_option( 'min_amount' );
				if ( $amount > 0 && ( ! $min || $amount < $min ) ) {
					$min = $amount;
				}
			}
		}
		return (float) apply_filters( 'rar_wcc_free_shipping_threshold', $min );
	}

	public static function fs_markup() {
		if ( ! RAR_WCC_Settings::yes( 'fs_enabled' ) || ! WC()->cart || WC()->cart->is_empty() || ! WC()->cart->needs_shipping() ) {
			return '';
		}
		$threshold = self::fs_threshold();
		if ( $threshold <= 0 ) {
			return '';
		}
		$subtotal = (float) WC()->cart->get_displayed_subtotal();
		if ( WC()->cart->display_prices_including_tax() ) {
			$subtotal -= (float) WC()->cart->get_discount_tax();
		}
		$subtotal -= (float) WC()->cart->get_discount_total();

		$remaining = max( 0, $threshold - $subtotal );
		$pct       = min( 100, max( 0, round( $subtotal / $threshold * 100 ) ) );
		$done      = $remaining <= 0;
		$text      = $done ? esc_html( RAR_WCC_Settings::text( 'fs_done_text' ) ) : str_replace( '{amount}', wc_price( $remaining ), esc_html( RAR_WCC_Settings::text( 'fs_text' ) ) );

		return '<div class="rar-wcc-fs' . ( $done ? ' is-done' : '' ) . '"><div class="rar-wcc-fs-text">' . wp_kses_post( $text ) . '</div><div class="rar-wcc-fs-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' . esc_attr( $pct ) . '"><span style="width:' . esc_attr( $pct ) . '%"></span></div></div>';
	}

	public static function fs_row() {
		$html = self::fs_markup();
		if ( $html ) {
			echo '<tr class="rar-wcc-fs-row"><td colspan="2">' . $html . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
		}
	}

	public static function fs_block() {
		echo self::fs_markup(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	public static function assets() {
		$css_needed = RAR_WCC_Settings::yes( 'fs_enabled' ) || RAR_WCC_Settings::yes( 'qty_editor' ) || RAR_WCC_Settings::yes( 'trust_enabled' );
		if ( $css_needed ) {
			wp_enqueue_style( 'rar-wcc-checkout', RAR_WCC_URL . 'assets/css/checkout.css', array(), RAR_WCC_VERSION );
			wp_add_inline_style( 'rar-wcc-checkout', RAR_WCC_Express::primary_css() );
		}

		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_order_received_page() ) {
			return;
		}

		wp_enqueue_script( 'rar-wcc-checkout', RAR_WCC_URL . 'assets/js/checkout.js', array( 'jquery' ), RAR_WCC_VERSION, true );
		wp_localize_script(
			'rar-wcc-checkout',
			'RAR_WCC_CHECKOUT',
			array(
				'qtyUrl'         => WC_AJAX::get_endpoint( 'rar_wcc_update_qty' ),
				'captureUrl'     => WC_AJAX::get_endpoint( 'rar_wcc_capture' ),
				'nonce'          => wp_create_nonce( 'rar-wcc-checkout' ),
				'qtyEditor'      => RAR_WCC_Settings::yes( 'qty_editor' ),
				'capture'        => RAR_WCC_Settings::yes( 'incomplete_enabled' ),
				'source'         => RAR_WCC_Express::in_frame() ? 'express' : 'checkout',
			)
		);
	}
}
