<?php
/**
 * Settings registry: schema, defaults, getters and sanitisation.
 *
 * @package RAR_Woo_Cart_Checkout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RAR_WCC_Settings {

	public const OPTION = 'rar_wcc_settings';

	private static $settings = null;
	private static $schema   = null;

	/**
	 * Tabs shown on the settings screen.
	 */
	public static function tabs() {
		return array(
			'address'    => array( __( 'Address & Fields', 'rar-woo-cart-checkout' ), 'dashicons-location' ),
			'express'    => array( __( 'Express Buy Now', 'rar-woo-cart-checkout' ), 'dashicons-performance' ),
			'checkout'   => array( __( 'Cart & Checkout', 'rar-woo-cart-checkout' ), 'dashicons-cart' ),
			'guard'      => array( __( 'Order Protection', 'rar-woo-cart-checkout' ), 'dashicons-shield' ),
			'incomplete' => array( __( 'Incomplete Orders', 'rar-woo-cart-checkout' ), 'dashicons-phone' ),
			'advanced'   => array( __( 'Advanced', 'rar-woo-cart-checkout' ), 'dashicons-admin-tools' ),
		);
	}

	/**
	 * Full settings schema. Each field: tab, group, type, default, label, desc, [options], [min], [max].
	 */
	public static function schema() {
		if ( null !== self::$schema ) {
			return self::$schema;
		}

		$s = array();

		// ── Address & Fields ───────────────────────────────────────────────
		$g = __( 'Bangladesh address workflow', 'rar-woo-cart-checkout' );
		$s['address_enabled']        = array( 'address', $g, 'toggle', 'yes', __( 'Checkout address UX', 'rar-woo-cart-checkout' ), __( 'Streamlined Bangladesh billing fields on the checkout page.', 'rar-woo-cart-checkout' ) );
		$s['cart_address_enabled']   = array( 'address', $g, 'toggle', 'yes', __( 'Cart shipping calculator UX', 'rar-woo-cart-checkout' ), __( 'District-aware Town / City selector in the Cart shipping calculator.', 'rar-woo-cart-checkout' ) );
		$s['hide_country']           = array( 'address', $g, 'toggle', 'yes', __( 'Bangladesh-only country', 'rar-woo-cart-checkout' ), __( 'Keeps Bangladesh selected internally and hides the Country field.', 'rar-woo-cart-checkout' ) );
		$s['remove_optional_fields'] = array( 'address', $g, 'toggle', 'yes', __( 'Remove unused fields', 'rar-woo-cart-checkout' ), __( 'Removes Last name, Company, Address line 2 and Postcode.', 'rar-woo-cart-checkout' ) );
		$s['hide_ship_different']    = array( 'address', $g, 'toggle', 'yes', __( 'Hide “Ship to a different address?”', 'rar-woo-cart-checkout' ), __( 'The billing address is used as the delivery address.', 'rar-woo-cart-checkout' ) );
		$s['show_order_notes']       = array( 'address', $g, 'toggle', 'yes', __( 'Show Order notes', 'rar-woo-cart-checkout' ), __( 'Keeps the Additional Information / Order notes box on the normal checkout page.', 'rar-woo-cart-checkout' ) );
		$s['email_optional']         = array( 'address', $g, 'toggle', 'yes', __( 'Email is optional', 'rar-woo-cart-checkout' ), __( 'Most Bangladeshi COD shoppers skip email. Customers can still enter one for order updates.', 'rar-woo-cart-checkout' ) );
		$s['field_order']            = array( 'address', $g, 'select', 'phone_first', __( 'Field order', 'rar-woo-cart-checkout' ), __( 'Which field the customer sees first.', 'rar-woo-cart-checkout' ), array( 'name_first' => __( 'Name → Phone → Email → Address → City → District', 'rar-woo-cart-checkout' ), 'phone_first' => __( 'Name → Phone → District → Town/City → Address → Email', 'rar-woo-cart-checkout' ) ) );

		$g = __( 'Phone number', 'rar-woo-cart-checkout' );
		$s['phone_validation']  = array( 'address', $g, 'toggle', 'yes', __( 'Validate Bangladeshi mobile number', 'rar-woo-cart-checkout' ), __( 'Accepts 01XXXXXXXXX, +8801XXXXXXXXX, 8801…, spaces, dashes and Bangla digits (০-৯). Rejects invalid numbers before the order is placed.', 'rar-woo-cart-checkout' ) );
		$s['phone_normalize']   = array( 'address', $g, 'select', 'local', __( 'Save phone as', 'rar-woo-cart-checkout' ), __( 'Consistent formatting makes courier booking and customer history lookups reliable.', 'rar-woo-cart-checkout' ), array( 'local' => '01XXXXXXXXX', 'intl' => '+8801XXXXXXXXX', 'none' => __( 'As typed', 'rar-woo-cart-checkout' ) ) );
		$s['phone_operator_hint'] = array( 'address', $g, 'toggle', 'yes', __( 'Live operator hint', 'rar-woo-cart-checkout' ), __( 'Shows “✓ Grameenphone”, “✓ Robi”… under the phone field while typing, or a warning when the number looks wrong.', 'rar-woo-cart-checkout' ) );

		$g = __( 'Town / City selector', 'rar-woo-cart-checkout' );
		$s['searchable_city']   = array( 'address', $g, 'toggle', 'yes', __( 'Searchable Town / City', 'rar-woo-cart-checkout' ), __( 'Turns Town / City into a searchable list filtered by the selected District.', 'rar-woo-cart-checkout' ) );
		$s['city_allow_custom'] = array( 'address', $g, 'toggle', 'yes', __( 'Allow typing an unlisted area', 'rar-woo-cart-checkout' ), __( 'Customers can type their own area/thana if it is not in the list.', 'rar-woo-cart-checkout' ) );
		$s['city_validation']   = array( 'address', $g, 'toggle', 'no', __( 'Strict Town / City validation', 'rar-woo-cart-checkout' ), __( 'Rejects a Town / City that does not belong to the selected District (ignored when unlisted areas are allowed).', 'rar-woo-cart-checkout' ) );
		$s['metro_areas']       = array( 'address', $g, 'toggle', 'yes', __( 'Include Dhaka & Chattogram metro thanas', 'rar-woo-cart-checkout' ), __( 'Adds Mirpur, Uttara, Dhanmondi, Gulshan, Panchlaish, Double Mooring… so couriers get an exact area.', 'rar-woo-cart-checkout' ) );
		$s['custom_locations']  = array( 'address', $g, 'textarea', '', __( 'Extra areas', 'rar-woo-cart-checkout' ), __( 'One district per line: District: Area 1, Area 2 (example — Dhaka: Aftabnagar, Bashundhara R/A).', 'rar-woo-cart-checkout' ) );

		$g = __( 'Labels & placeholders', 'rar-woo-cart-checkout' );
		$s['billing_heading']    = array( 'address', $g, 'text', 'Billing Details', __( 'Billing section heading', 'rar-woo-cart-checkout' ) );
		$s['label_full_name']    = array( 'address', $g, 'text', 'Full name', __( 'Full name label', 'rar-woo-cart-checkout' ) );
		$s['name_placeholder']   = array( 'address', $g, 'text', 'Enter your full name', __( 'Full name placeholder', 'rar-woo-cart-checkout' ) );
		$s['label_phone']        = array( 'address', $g, 'text', 'Phone', __( 'Phone label', 'rar-woo-cart-checkout' ) );
		$s['phone_placeholder']  = array( 'address', $g, 'text', '01XXXXXXXXX', __( 'Phone placeholder', 'rar-woo-cart-checkout' ) );
		$s['label_email']        = array( 'address', $g, 'text', 'Email address', __( 'Email label', 'rar-woo-cart-checkout' ) );
		$s['label_street']       = array( 'address', $g, 'text', 'Street address', __( 'Street address label', 'rar-woo-cart-checkout' ) );
		$s['street_placeholder'] = array( 'address', $g, 'text', 'House / Road / Area', __( 'Street address placeholder', 'rar-woo-cart-checkout' ) );
		$s['label_city']         = array( 'address', $g, 'text', 'Town / City', __( 'Town / City label', 'rar-woo-cart-checkout' ) );
		$s['label_district']     = array( 'address', $g, 'text', 'District', __( 'District label', 'rar-woo-cart-checkout' ) );
		$s['city_placeholder']   = array( 'address', $g, 'text', 'Search town / city…', __( 'Town / City search placeholder', 'rar-woo-cart-checkout' ) );
		$s['additional_heading'] = array( 'address', $g, 'text', 'Additional Information', __( 'Additional Information heading', 'rar-woo-cart-checkout' ) );
		$s['order_notes_label']  = array( 'address', $g, 'text', 'Order notes', __( 'Order notes label', 'rar-woo-cart-checkout' ) );

		// ── Express Buy Now ───────────────────────────────────────────────
		$g = __( 'Behaviour', 'rar-woo-cart-checkout' );
		$s['express_enabled']       = array( 'express', $g, 'toggle', 'yes', __( 'Express Buy Now modal', 'rar-woo-cart-checkout' ), __( 'Buy Now adds the product in the background and opens a compact checkout in a modal. Unsupported products fall back to the theme behaviour.', 'rar-woo-cart-checkout' ) );
		$s['express_button_selector'] = array( 'express', $g, 'text', '.wd-buy-now-btn', __( 'Theme Buy Now selector', 'rar-woo-cart-checkout' ), __( 'CSS selector of your theme’s Buy Now button. Woodmart: .wd-buy-now-btn', 'rar-woo-cart-checkout' ) );
		$s['express_add_button']    = array( 'express', $g, 'toggle', 'no', __( 'Add our own Buy Now button', 'rar-woo-cart-checkout' ), __( 'For themes without a Buy Now button. Adds one next to Add to cart on product pages.', 'rar-woo-cart-checkout' ) );
		$s['express_button_text']   = array( 'express', $g, 'text', 'Buy Now', __( 'Our Buy Now button text', 'rar-woo-cart-checkout' ) );
		$s['express_clear_cart']    = array( 'express', $g, 'select', 'keep', __( 'Cart handling', 'rar-woo-cart-checkout' ), __( 'What happens to products already in the cart when the customer taps Buy Now.', 'rar-woo-cart-checkout' ), array( 'keep' => __( 'Keep them (buy everything together)', 'rar-woo-cart-checkout' ), 'clear' => __( 'Buy only this product (empty the cart first)', 'rar-woo-cart-checkout' ) ) );
		$s['express_checkout_path'] = array( 'express', $g, 'text', '', __( 'Checkout path override', 'rar-woo-cart-checkout' ), __( 'Leave empty to use the WooCommerce checkout page automatically. Otherwise a same-site path such as /checkout/.', 'rar-woo-cart-checkout' ) );

		$g = __( 'Modal content', 'rar-woo-cart-checkout' );
		$s['express_modal_title']      = array( 'express', $g, 'text', 'Express Checkout', __( 'Modal title', 'rar-woo-cart-checkout' ) );
		$s['express_modal_subtitle']   = array( 'express', $g, 'text', 'Fast & secure checkout', __( 'Modal subtitle', 'rar-woo-cart-checkout' ) );
		$s['express_footer_text']      = array( 'express', $g, 'text', 'Cash on Delivery · Nationwide Delivery', __( 'Footer text', 'rar-woo-cart-checkout' ) );
		$s['express_open_full_text']   = array( 'express', $g, 'text', 'Open full checkout', __( 'Open-full-checkout link text', 'rar-woo-cart-checkout' ) );
		$s['express_loading_text']     = array( 'express', $g, 'text', 'Preparing…', __( 'Button loading text', 'rar-woo-cart-checkout' ) );
		$s['express_loader_text']      = array( 'express', $g, 'text', 'Preparing secure checkout…', __( 'Modal loader text', 'rar-woo-cart-checkout' ) );
		$s['express_delivery_heading'] = array( 'express', $g, 'text', 'Delivery Information', __( 'Billing heading inside modal', 'rar-woo-cart-checkout' ) );
		$s['express_order_heading']    = array( 'express', $g, 'text', 'Order Summary', __( 'Order heading inside modal', 'rar-woo-cart-checkout' ) );
		$s['express_hide_additional']  = array( 'express', $g, 'toggle', 'yes', __( 'Hide Order notes inside modal', 'rar-woo-cart-checkout' ) );
		$s['express_hide_account']     = array( 'express', $g, 'toggle', 'yes', __( 'Hide account / shipping extras inside modal', 'rar-woo-cart-checkout' ) );
		$s['express_hide_coupon']      = array( 'express', $g, 'toggle', 'no', __( 'Hide coupon box inside modal', 'rar-woo-cart-checkout' ) );
		$s['express_primary_color']    = array( 'express', $g, 'color', '#117865', __( 'Primary colour', 'rar-woo-cart-checkout' ), __( 'Used for the modal, Place Order button, progress bars and quantity controls.', 'rar-woo-cart-checkout' ) );

		// ── Cart & Checkout ───────────────────────────────────────────────
		$g = __( 'Checkout experience', 'rar-woo-cart-checkout' );
		$s['qty_editor']        = array( 'checkout', $g, 'toggle', 'yes', __( 'Edit quantity on checkout', 'rar-woo-cart-checkout' ), __( 'Adds − / + and remove controls to each product in the order summary (checkout page and Express modal).', 'rar-woo-cart-checkout' ) );
		$s['place_order_text']  = array( 'checkout', $g, 'text', '', __( 'Place Order button text', 'rar-woo-cart-checkout' ), __( 'Leave empty for the default. Use {total} to show the order total, e.g. “Confirm Order · {total}”.', 'rar-woo-cart-checkout' ) );
		$s['trust_enabled']     = array( 'checkout', $g, 'toggle', 'yes', __( 'Trust line under Place Order', 'rar-woo-cart-checkout' ) );
		$s['trust_text']        = array( 'checkout', $g, 'text', '✓ Cash on Delivery  ✓ 100% Authentic  ✓ Easy Return', __( 'Trust line text', 'rar-woo-cart-checkout' ) );
		$s['coupon_label']      = array( 'checkout', $g, 'text', '', __( 'Coupon toggle text', 'rar-woo-cart-checkout' ), __( 'Replaces “Have a coupon? Click here to enter your code”. Leave empty for default.', 'rar-woo-cart-checkout' ) );

		$g = __( 'Free-delivery progress bar', 'rar-woo-cart-checkout' );
		$s['fs_enabled']   = array( 'checkout', $g, 'toggle', 'yes', __( 'Show free-delivery progress', 'rar-woo-cart-checkout' ), __( 'Shown in Cart totals, Checkout summary, Express modal and mini-cart. Hidden automatically if no threshold exists.', 'rar-woo-cart-checkout' ) );
		$s['fs_amount']    = array( 'checkout', $g, 'number', '0', __( 'Threshold amount', 'rar-woo-cart-checkout' ), __( '0 = detect automatically from your WooCommerce “Free shipping” method (minimum order amount).', 'rar-woo-cart-checkout' ), null, 0 );
		$s['fs_text']      = array( 'checkout', $g, 'text', 'Add {amount} more to get FREE delivery!', __( 'Progress text', 'rar-woo-cart-checkout' ), __( '{amount} = remaining amount.', 'rar-woo-cart-checkout' ) );
		$s['fs_unlock_mode'] = array( 'checkout', $g, 'select', 'select', __( 'When free delivery is unlocked', 'rar-woo-cart-checkout' ), __( 'WooCommerce keeps the paid rate selected by default, which confuses customers who just unlocked free delivery.', 'rar-woo-cart-checkout' ), array( 'select' => __( 'Pre-select free delivery (customer can still change)', 'rar-woo-cart-checkout' ), 'hide' => __( 'Hide paid delivery options', 'rar-woo-cart-checkout' ), 'none' => __( 'Do nothing', 'rar-woo-cart-checkout' ) ) );
		$s['fs_done_text'] = array( 'checkout', $g, 'text', '🎉 You’ve unlocked FREE delivery!', __( 'Unlocked text', 'rar-woo-cart-checkout' ) );

		// ── Order Protection ──────────────────────────────────────────────
		$g = __( 'Customer insight', 'rar-woo-cart-checkout' );
		$s['history_enabled'] = array( 'guard', $g, 'toggle', 'yes', __( 'Customer order history', 'rar-woo-cart-checkout' ), __( 'Shows each customer’s previous orders, delivered/cancelled counts and success rate (matched by phone) on the order screen and orders list.', 'rar-woo-cart-checkout' ) );
		$s['history_column']  = array( 'guard', $g, 'toggle', 'yes', __( 'Success-rate column in Orders list', 'rar-woo-cart-checkout' ) );

		$g = __( 'Blocking rules', 'rar-woo-cart-checkout' );
		$s['guard_enabled']   = array( 'guard', $g, 'toggle', 'yes', __( 'Enable order protection', 'rar-woo-cart-checkout' ), __( 'Master switch for the rules below.', 'rar-woo-cart-checkout' ) );
		$s['guard_cooldown']  = array( 'guard', $g, 'number', '0', __( 'Duplicate-order cooldown (minutes)', 'rar-woo-cart-checkout' ), __( 'Block a new order from the same phone within this many minutes. Stops double-submits and prank repeats. 0 = off.', 'rar-woo-cart-checkout' ), null, 0, 1440 );
		$s['guard_min_total'] = array( 'guard', $g, 'number', '0', __( 'Minimum order total', 'rar-woo-cart-checkout' ), __( '0 = off.', 'rar-woo-cart-checkout' ), null, 0 );
		$s['guard_max_cod']   = array( 'guard', $g, 'number', '0', __( 'Maximum Cash-on-Delivery total', 'rar-woo-cart-checkout' ), __( 'Larger COD orders must use an online payment method. 0 = off.', 'rar-woo-cart-checkout' ), null, 0 );
		$s['guard_min_success'] = array( 'guard', $g, 'number', '0', __( 'Minimum success rate for COD (%)', 'rar-woo-cart-checkout' ), __( 'Customers with at least 3 finished orders and a lower delivery success rate cannot use COD. 0 = off.', 'rar-woo-cart-checkout' ), null, 0, 100 );
		$s['guard_blocklist'] = array( 'guard', $g, 'textarea', '', __( 'Blocked phones / emails / IPs', 'rar-woo-cart-checkout' ), __( 'One per line. Phones are matched in any format. Tip: use “Block this customer” on an order screen.', 'rar-woo-cart-checkout' ) );
		$s['guard_message']   = array( 'guard', $g, 'text', 'Sorry, we could not place this order. Please call us to complete your order.', __( 'Blocked-order message', 'rar-woo-cart-checkout' ) );
		$s['guard_cooldown_message'] = array( 'guard', $g, 'text', 'You placed an order a few minutes ago. We will call you shortly to confirm it.', __( 'Cooldown message', 'rar-woo-cart-checkout' ) );

		// ── Incomplete Orders ─────────────────────────────────────────────
		$g = __( 'Capture', 'rar-woo-cart-checkout' );
		$s['incomplete_enabled']   = array( 'incomplete', $g, 'toggle', 'yes', __( 'Capture incomplete checkouts', 'rar-woo-cart-checkout' ), __( 'When a shopper enters a valid phone number but leaves without ordering, their details and cart are saved so your team can call them.', 'rar-woo-cart-checkout' ) );
		$s['incomplete_retention'] = array( 'incomplete', $g, 'number', '60', __( 'Keep records for (days)', 'rar-woo-cart-checkout' ), __( 'Older records are deleted automatically every day.', 'rar-woo-cart-checkout' ), null, 1, 730 );
		$s['incomplete_whatsapp']  = array( 'incomplete', $g, 'textarea', "Assalamu Alaikum {name}, you were ordering {products} from {site}. Shall we confirm your order?", __( 'WhatsApp message template', 'rar-woo-cart-checkout' ), __( 'Used by the WhatsApp button. Placeholders: {name} {products} {total} {site}.', 'rar-woo-cart-checkout' ) );
		$s['incomplete_order_status'] = array( 'incomplete', $g, 'select', 'pending', __( 'Status for recovered orders', 'rar-woo-cart-checkout' ), __( 'Status of an order created with “Create order” from an incomplete record.', 'rar-woo-cart-checkout' ), array( 'pending' => __( 'Pending payment', 'rar-woo-cart-checkout' ), 'on-hold' => __( 'On hold', 'rar-woo-cart-checkout' ), 'processing' => __( 'Processing', 'rar-woo-cart-checkout' ) ) );

		// ── Advanced ──────────────────────────────────────────────────────
		$g = __( 'Advanced', 'rar-woo-cart-checkout' );
		$s['block_notice']     = array( 'advanced', $g, 'toggle', 'yes', __( 'Warn when Cart/Checkout Blocks are used', 'rar-woo-cart-checkout' ), __( 'This plugin works on the Classic cart/checkout. The dashboard offers a one-click, reversible switch.', 'rar-woo-cart-checkout' ) );
		$s['delete_on_uninstall'] = array( 'advanced', $g, 'toggle', 'no', __( 'Delete all plugin data on uninstall', 'rar-woo-cart-checkout' ), __( 'Removes settings and the incomplete-orders table when the plugin is deleted. Orders are never touched.', 'rar-woo-cart-checkout' ) );

		$out = array();
		foreach ( $s as $key => $f ) {
			$out[ $key ] = array(
				'tab'     => $f[0],
				'group'   => $f[1],
				'type'    => $f[2],
				'default' => $f[3],
				'label'   => $f[4],
				'desc'    => $f[5] ?? '',
				'options' => $f[6] ?? null,
				'min'     => $f[7] ?? null,
				'max'     => $f[8] ?? null,
			);
		}

		self::$schema = apply_filters( 'rar_wcc_settings_schema', $out );
		return self::$schema;
	}

	public static function defaults() {
		$d = array();
		foreach ( self::schema() as $key => $f ) {
			$d[ $key ] = $f['default'];
		}
		return $d;
	}

	public static function all() {
		if ( null === self::$settings ) {
			$stored         = get_option( self::OPTION, array() );
			self::$settings = wp_parse_args( is_array( $stored ) ? $stored : array(), self::defaults() );
		}
		return self::$settings;
	}

	public static function get( $key, $default = '' ) {
		$settings = self::all();
		return array_key_exists( $key, $settings ) ? $settings[ $key ] : $default;
	}

	/**
	 * Get a text setting, falling back to its default when blank.
	 */
	public static function text( $key ) {
		$v = trim( (string) self::get( $key ) );
		if ( '' === $v ) {
			$schema = self::schema();
			$v      = isset( $schema[ $key ] ) ? (string) $schema[ $key ]['default'] : '';
		}
		return $v;
	}

	public static function yes( $key ) {
		return 'yes' === self::get( $key, 'no' );
	}

	public static function num( $key ) {
		return (float) self::get( $key, 0 );
	}

	public static function reset_cache() {
		self::$settings = null;
	}

	/**
	 * Sanitise an array of raw values against the schema.
	 *
	 * @param array $raw     Raw input (already unslashed).
	 * @param bool  $partial When true, only keys present in $raw are changed.
	 */
	public static function sanitize( $raw, $partial = false ) {
		$schema = self::schema();
		$clean  = $partial ? self::all() : self::defaults();

		foreach ( $schema as $key => $f ) {
			if ( $partial && ! array_key_exists( $key, $raw ) ) {
				continue;
			}
			$v = $raw[ $key ] ?? null;

			switch ( $f['type'] ) {
				case 'toggle':
					$clean[ $key ] = ( 'yes' === $v || true === $v || '1' === $v ) ? 'yes' : 'no';
					break;
				case 'number':
					$n = is_numeric( $v ) ? (float) $v : (float) $f['default'];
					if ( null !== $f['min'] ) {
						$n = max( (float) $f['min'], $n );
					}
					if ( null !== $f['max'] ) {
						$n = min( (float) $f['max'], $n );
					}
					$clean[ $key ] = (string) ( floor( $n ) == $n ? (int) $n : $n ); // phpcs:ignore Universal.Operators.StrictComparisons
					break;
				case 'color':
					$c             = sanitize_hex_color( (string) $v );
					$clean[ $key ] = $c ? $c : $f['default'];
					break;
				case 'select':
					$clean[ $key ] = ( is_array( $f['options'] ) && array_key_exists( (string) $v, $f['options'] ) ) ? (string) $v : $f['default'];
					break;
				case 'textarea':
					$clean[ $key ] = sanitize_textarea_field( (string) $v );
					break;
				default:
					$clean[ $key ] = sanitize_text_field( (string) ( $v ?? $f['default'] ) );
			}
		}

		$clean['express_button_selector'] = self::sanitize_selector( $clean['express_button_selector'] );
		$clean['express_checkout_path']   = self::sanitize_path( $clean['express_checkout_path'] );

		return $clean;
	}

	public static function save( $values ) {
		update_option( self::OPTION, $values, false );
		self::$settings = null;
		do_action( 'rar_wcc_settings_saved', $values );
	}

	public static function sanitize_path( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}
		$parts = wp_parse_url( $value );
		if ( false === $parts || isset( $parts['scheme'] ) || isset( $parts['host'] ) || str_starts_with( $value, '//' ) ) {
			return '';
		}
		return '/' . ltrim( sanitize_text_field( $value ), '/' );
	}

	public static function sanitize_selector( $value ) {
		$value = trim( (string) $value );
		$value = preg_replace( '/[^a-zA-Z0-9\-\_\.\#\[\]\=\"\'\:\s>,+~*()^$|]/', '', $value );
		return $value ? $value : '.wd-buy-now-btn';
	}

	/**
	 * Bangla label preset applied from the Tools screen.
	 */
	public static function bangla_preset() {
		return array(
			'billing_heading'          => 'ডেলিভারি তথ্য',
			'label_full_name'          => 'আপনার নাম',
			'name_placeholder'         => 'আপনার পুরো নাম লিখুন',
			'label_phone'              => 'মোবাইল নম্বর',
			'phone_placeholder'        => '01XXXXXXXXX',
			'label_email'              => 'ইমেইল',
			'label_street'             => 'সম্পূর্ণ ঠিকানা',
			'street_placeholder'       => 'বাসা / রোড / এলাকা',
			'label_city'               => 'থানা / উপজেলা',
			'label_district'           => 'জেলা',
			'city_placeholder'         => 'থানা / উপজেলা খুঁজুন…',
			'additional_heading'       => 'অতিরিক্ত তথ্য',
			'order_notes_label'        => 'অর্ডার নোট',
			'express_modal_title'      => 'দ্রুত অর্ডার করুন',
			'express_modal_subtitle'   => 'নিরাপদ ও দ্রুত চেকআউট',
			'express_footer_text'      => 'ক্যাশ অন ডেলিভারি · সারাদেশে ডেলিভারি',
			'express_open_full_text'   => 'সম্পূর্ণ চেকআউট পেজ',
			'express_loading_text'     => 'অপেক্ষা করুন…',
			'express_loader_text'      => 'চেকআউট প্রস্তুত হচ্ছে…',
			'express_delivery_heading' => 'ডেলিভারি তথ্য',
			'express_order_heading'    => 'অর্ডার সারাংশ',
			'express_button_text'      => 'এখনই কিনুন',
			'place_order_text'         => 'অর্ডার কনফার্ম করুন · {total}',
			'trust_text'               => '✓ ক্যাশ অন ডেলিভারি  ✓ ১০০% অরিজিনাল  ✓ সহজ রিটার্ন',
			'coupon_label'             => 'কুপন কোড আছে? এখানে ক্লিক করুন',
			'fs_text'                  => 'আর {amount} কেনাকাটা করলেই ফ্রি ডেলিভারি!',
			'fs_done_text'             => '🎉 অভিনন্দন! আপনি ফ্রি ডেলিভারি পাচ্ছেন!',
			'guard_message'            => 'দুঃখিত, এই অর্ডারটি সম্পন্ন করা যাচ্ছে না। অর্ডার করতে অনুগ্রহ করে আমাদের কল করুন।',
			'guard_cooldown_message'   => 'আপনি কিছুক্ষণ আগেই অর্ডার করেছেন। আমরা শীঘ্রই আপনাকে কল করে কনফার্ম করবো।',
			'incomplete_whatsapp'      => 'আসসালামু আলাইকুম {name}, আপনি {site} থেকে {products} অর্ডার করছিলেন। অর্ডারটি কি কনফার্ম করে দেবো?',
		);
	}
}
