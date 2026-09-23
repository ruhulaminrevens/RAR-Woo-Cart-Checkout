<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RAR_WCC_Settings {
	public const OPTION = 'rar_wcc_settings';

	private static $settings = null;

	public static function defaults() {
		return array(
			'address_enabled'            => 'yes',
			'cart_address_enabled'       => 'yes',
			'hide_country'               => 'yes',
			'remove_optional_fields'     => 'yes',
			'hide_ship_different'        => 'yes',
			'show_order_notes'           => 'yes',
			'searchable_city'            => 'yes',
			'billing_heading'            => 'Billing Details',
			'label_full_name'            => 'Full name',
			'label_phone'                => 'Phone',
			'label_email'                => 'Email address',
			'label_street'               => 'Street address',
			'label_city'                 => 'Town / City',
			'label_district'             => 'District',
			'city_placeholder'           => 'Search town / city…',
			'additional_heading'         => 'Additional Information',
			'order_notes_label'          => 'Order notes',
			'express_enabled'            => 'yes',
			'express_button_selector'    => '.wd-buy-now-btn',
			'express_modal_title'        => 'Express Checkout',
			'express_modal_subtitle'     => 'Fast & secure checkout',
			'express_footer_text'        => 'Cash on Delivery · Nationwide Delivery',
			'express_open_full_text'     => 'Open full checkout',
			'express_loading_text'       => 'Preparing…',
			'express_loader_text'        => 'Preparing secure checkout…',
			'express_checkout_path'      => '/checkout/',
			'express_delivery_heading'   => 'Delivery Information',
			'express_order_heading'      => 'Order Summary',
			'express_hide_additional'    => 'yes',
			'express_hide_account'       => 'yes',
			'express_primary_color'      => '#117865',
		);
	}

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ), 60 );
		add_action( 'admin_post_rar_wcc_save_settings', array( __CLASS__, 'save' ) );
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

	public static function yes( $key ) {
		return 'yes' === self::get( $key, 'no' );
	}

	public static function admin_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'RAR Cart & Checkout', 'rar-woo-cart-checkout' ),
			__( 'RAR Cart & Checkout', 'rar-woo-cart-checkout' ),
			'manage_woocommerce',
			'rar-wcc',
			array( __CLASS__, 'page' )
		);
	}

	private static function sanitize_checkbox( $value ) {
		return 'yes' === $value ? 'yes' : 'no';
	}

	private static function sanitize_path( $value ) {
		$value = trim( wp_unslash( (string) $value ) );
		if ( '' === $value ) {
			return '/checkout/';
		}
		$parts = wp_parse_url( $value );
		if ( false === $parts || isset( $parts['scheme'] ) || isset( $parts['host'] ) ) {
			return '/checkout/';
		}
		if ( '/' !== substr( $value, 0, 1 ) ) {
			$value = '/' . $value;
		}
		return '/' . ltrim( sanitize_text_field( $value ), '/' );
	}

	private static function sanitize_selector( $value ) {
		$value = trim( wp_unslash( (string) $value ) );
		$value = preg_replace( '/[^a-zA-Z0-9\-\_\.\#\[\]\=\"\'\:\s>,+~*()]/', '', $value );
		return $value ? $value : '.wd-buy-now-btn';
	}

	public static function save() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage these settings.', 'rar-woo-cart-checkout' ) );
		}
		check_admin_referer( 'rar_wcc_save_settings' );

		$raw      = isset( $_POST['rar_wcc'] ) && is_array( $_POST['rar_wcc'] ) ? wp_unslash( $_POST['rar_wcc'] ) : array();
		$defaults = self::defaults();
		$clean    = $defaults;

		$checkboxes = array(
			'address_enabled',
			'cart_address_enabled',
			'hide_country',
			'remove_optional_fields',
			'hide_ship_different',
			'show_order_notes',
			'searchable_city',
			'express_enabled',
			'express_hide_additional',
			'express_hide_account',
		);
		foreach ( $checkboxes as $key ) {
			$clean[ $key ] = self::sanitize_checkbox( $raw[ $key ] ?? 'no' );
		}

		$text_keys = array(
			'billing_heading',
			'label_full_name',
			'label_phone',
			'label_email',
			'label_street',
			'label_city',
			'label_district',
			'city_placeholder',
			'additional_heading',
			'order_notes_label',
			'express_modal_title',
			'express_modal_subtitle',
			'express_footer_text',
			'express_open_full_text',
			'express_loading_text',
			'express_loader_text',
			'express_delivery_heading',
			'express_order_heading',
		);
		foreach ( $text_keys as $key ) {
			$clean[ $key ] = sanitize_text_field( $raw[ $key ] ?? $defaults[ $key ] );
		}

		$clean['express_button_selector'] = self::sanitize_selector( $raw['express_button_selector'] ?? $defaults['express_button_selector'] );
		$clean['express_checkout_path']   = self::sanitize_path( $raw['express_checkout_path'] ?? $defaults['express_checkout_path'] );
		$color                            = sanitize_hex_color( $raw['express_primary_color'] ?? $defaults['express_primary_color'] );
		$clean['express_primary_color']   = $color ? $color : $defaults['express_primary_color'];

		update_option( self::OPTION, $clean, false );
		self::$settings = $clean;

		wp_safe_redirect( add_query_arg( array( 'page' => 'rar-wcc', 'updated' => '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	private static function checked( $key ) {
		checked( self::yes( $key ), true );
	}

	private static function field( $key, $label, $description = '' ) {
		$value = self::get( $key );
		echo '<tr><th scope="row"><label for="rar-wcc-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td>';
		echo '<input class="regular-text" type="text" id="rar-wcc-' . esc_attr( $key ) . '" name="rar_wcc[' . esc_attr( $key ) . ']" value="' . esc_attr( $value ) . '">';
		if ( $description ) {
			echo '<p class="description">' . esc_html( $description ) . '</p>';
		}
		echo '</td></tr>';
	}

	private static function toggle( $key, $label, $description = '' ) {
		echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td><label>';
		echo '<input type="checkbox" name="rar_wcc[' . esc_attr( $key ) . ']" value="yes" ';
		self::checked( $key );
		echo '> ' . esc_html__( 'Enabled', 'rar-woo-cart-checkout' ) . '</label>';
		if ( $description ) {
			echo '<p class="description">' . esc_html( $description ) . '</p>';
		}
		echo '</td></tr>';
	}

	public static function page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'RAR Woo Cart & Checkout', 'rar-woo-cart-checkout' ); ?></h1>
			<?php if ( isset( $_GET['updated'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'rar-woo-cart-checkout' ); ?></p></div>
			<?php endif; ?>

			<div class="notice notice-info inline">
				<p><strong><?php esc_html_e( 'Safe WPCode migration:', 'rar-woo-cart-checkout' ); ?></strong>
				<?php esc_html_e( 'Activate this plugin first. Then deactivate the old checkout/address and Express Buy Now WPCode snippets. Clear caches and test Cart, Checkout, Buy Now, variation products and Place Order before deleting the old snippets.', 'rar-woo-cart-checkout' ); ?></p>
			</div>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="rar_wcc_save_settings">
				<?php wp_nonce_field( 'rar_wcc_save_settings' ); ?>

				<h2><?php esc_html_e( 'Bangladesh Address UX', 'rar-woo-cart-checkout' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php
					self::toggle( 'address_enabled', 'Checkout billing UX', 'Controls the streamlined Bangladesh checkout fields.' );
					self::toggle( 'cart_address_enabled', 'Cart shipping calculator UX', 'Applies the Bangladesh Town/City selector to the Cart shipping calculator.' );
					self::toggle( 'hide_country', 'Bangladesh-only country', 'Keeps Bangladesh internally selected and hides the country field.' );
					self::toggle( 'remove_optional_fields', 'Remove unused billing fields', 'Removes last name, company, address line 2 and postcode from checkout.' );
					self::toggle( 'hide_ship_different', 'Hide “Ship to a different address?”', 'Billing address remains the delivery address.' );
					self::toggle( 'show_order_notes', 'Show Additional Information / Order notes', 'Keeps the order-notes textarea visible.' );
					self::toggle( 'searchable_city', 'Searchable Town / City', 'Converts Town / City into a district-aware searchable selector.' );
					self::field( 'billing_heading', 'Billing heading' );
					self::field( 'label_full_name', 'Full-name label' );
					self::field( 'label_phone', 'Phone label' );
					self::field( 'label_email', 'Email label' );
					self::field( 'label_street', 'Street-address label' );
					self::field( 'label_city', 'Town / City label' );
					self::field( 'label_district', 'District label' );
					self::field( 'city_placeholder', 'Town / City search placeholder' );
					self::field( 'additional_heading', 'Additional Information heading' );
					self::field( 'order_notes_label', 'Order notes label' );
					?>
				</table>

				<hr>
				<h2><?php esc_html_e( 'Express Buy Now', 'rar-woo-cart-checkout' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php
					self::toggle( 'express_enabled', 'Express Buy Now modal', 'Intercepts the configured Buy Now button and opens checkout in a same-origin modal. Unsupported product types fall back to the theme behavior.' );
					self::field( 'express_button_selector', 'Buy Now CSS selector', 'Default Woodmart selector: .wd-buy-now-btn' );
					self::field( 'express_checkout_path', 'Checkout path', 'Must be a same-site relative path such as /checkout/.' );
					self::field( 'express_modal_title', 'Modal title' );
					self::field( 'express_modal_subtitle', 'Modal subtitle' );
					self::field( 'express_footer_text', 'Modal footer text' );
					self::field( 'express_open_full_text', 'Open-full-checkout link text' );
					self::field( 'express_loading_text', 'Buy Now loading text' );
					self::field( 'express_loader_text', 'Modal loader text' );
					self::field( 'express_delivery_heading', 'Express billing heading' );
					self::field( 'express_order_heading', 'Express order-summary heading' );
					self::toggle( 'express_hide_additional', 'Hide Additional Information inside Express modal', 'The normal checkout page can still show Order notes.' );
					self::toggle( 'express_hide_account', 'Hide account/shipping extras inside Express modal' );
					?>
					<tr>
						<th scope="row"><label for="rar-wcc-color"><?php esc_html_e( 'Primary color', 'rar-woo-cart-checkout' ); ?></label></th>
						<td><input type="color" id="rar-wcc-color" name="rar_wcc[express_primary_color]" value="<?php echo esc_attr( self::get( 'express_primary_color', '#117865' ) ); ?>"></td>
					</tr>
				</table>

				<?php submit_button( __( 'Save Settings', 'rar-woo-cart-checkout' ) ); ?>
			</form>

			<hr>
			<h2><?php esc_html_e( 'Compatibility notes', 'rar-woo-cart-checkout' ); ?></h2>
			<p><?php esc_html_e( 'Version 1.0.0 is designed for WooCommerce Classic Cart/Checkout templates. HPOS is supported. The Express Buy Now feature is theme-agnostic at the plugin level, but its default selector targets Woodmart’s .wd-buy-now-btn button and can be edited above.', 'rar-woo-cart-checkout' ); ?></p>
		</div>
		<?php
	}
}
