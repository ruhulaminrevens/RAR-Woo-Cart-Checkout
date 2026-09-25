<?php
/**
 * Order protection (blocklist, cooldown, COD rules) and customer history.
 *
 * @package RAR_Woo_Cart_Checkout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RAR_WCC_Guard {

	public static function init() {
		add_action( 'woocommerce_after_checkout_validation', array( __CLASS__, 'validate' ), 20, 2 );
		add_action( 'woocommerce_order_status_changed', array( __CLASS__, 'flush_for_order' ), 10, 1 );
		add_action( 'woocommerce_new_order', array( __CLASS__, 'flush_for_order' ), 10, 1 );
		add_action( 'woocommerce_update_order', array( __CLASS__, 'flush_for_order' ), 10, 1 );

		if ( is_admin() ) {
			add_action( 'add_meta_boxes', array( __CLASS__, 'meta_box' ), 30 );
			add_filter( 'manage_edit-shop_order_columns', array( __CLASS__, 'add_column' ), 20 );
			add_filter( 'manage_woocommerce_page_wc-orders_columns', array( __CLASS__, 'add_column' ), 20 );
			add_action( 'manage_shop_order_posts_custom_column', array( __CLASS__, 'render_column_legacy' ), 20, 2 );
			add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( __CLASS__, 'render_column' ), 20, 2 );
			add_action( 'admin_post_rar_wcc_block', array( __CLASS__, 'handle_block' ) );
		}
	}

	/* ── Status buckets ──────────────────────────────────────────────── */

	public static function success_statuses() {
		return apply_filters( 'rar_wcc_success_statuses', array( 'completed', 'delivered' ) );
	}

	public static function failed_statuses() {
		return apply_filters( 'rar_wcc_failed_statuses', array( 'cancelled', 'failed', 'refunded', 'returned', 'rejected', 'return', 'partial-return' ) );
	}

	/* ── Customer history ────────────────────────────────────────────── */

	private static function hpos() {
		return class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
	}

	/**
	 * Order IDs for a phone (any stored format), newest first.
	 */
	public static function order_ids_for_phone( $phone, $args = array() ) {
		$variants = RAR_WCC_Phone::variants( $phone );
		if ( ! $variants ) {
			return array();
		}
		$query = array_merge(
			array(
				'limit'   => 200,
				'return'  => 'ids',
				'type'    => 'shop_order',
				'status'  => array_keys( wc_get_order_statuses() ),
				'orderby' => 'date',
				'order'   => 'DESC',
			),
			$args
		);
		if ( self::hpos() ) {
			$query['field_query'] = array(
				array(
					'field'   => 'billing_phone',
					'value'   => $variants,
					'compare' => 'IN',
				),
			);
		} else {
			$query['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'     => '_billing_phone',
					'value'   => $variants,
					'compare' => 'IN',
				),
			);
		}
		return array_map( 'intval', (array) wc_get_orders( $query ) );
	}

	private static function cache_key( $phone ) {
		$local = RAR_WCC_Phone::local( $phone );
		return 'rar_wcc_hist_' . md5( $local ? $local : strtolower( trim( (string) $phone ) ) );
	}

	/**
	 * Aggregated customer history, cached for 10 minutes.
	 */
	public static function history( $phone ) {
		if ( '' === trim( (string) $phone ) ) {
			return null;
		}
		$key    = self::cache_key( $phone );
		$cached = get_transient( $key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$success = self::success_statuses();
		$failed  = self::failed_statuses();
		$h       = array(
			'total'     => 0,
			'success'   => 0,
			'failed'    => 0,
			'open'      => 0,
			'spent'     => 0.0,
			'rate'      => null,
			'first'     => '',
			'last'      => '',
			'recent'    => array(),
			'operator'  => RAR_WCC_Phone::operator( $phone ),
			'blocked'   => self::is_blocked( $phone ),
		);

		foreach ( self::order_ids_for_phone( $phone ) as $i => $id ) {
			$o = wc_get_order( $id );
			if ( ! $o ) {
				continue;
			}
			$status = $o->get_status();
			++$h['total'];
			if ( in_array( $status, $success, true ) ) {
				++$h['success'];
				$h['spent'] += (float) $o->get_total();
			} elseif ( in_array( $status, $failed, true ) ) {
				++$h['failed'];
			} else {
				++$h['open'];
			}
			$date = $o->get_date_created() ? $o->get_date_created()->getTimestamp() : 0;
			if ( ! $h['last'] ) {
				$h['last'] = $date;
			}
			$h['first'] = $date;
			if ( $i < 8 ) {
				$h['recent'][] = array(
					'id'     => $id,
					'number' => $o->get_order_number(),
					'status' => $status,
					'label'  => wc_get_order_status_name( $status ),
					'total'  => (float) $o->get_total(),
					'date'   => $date,
					'url'    => $o->get_edit_order_url(),
				);
			}
		}
		$finished = $h['success'] + $h['failed'];
		$h['rate'] = $finished > 0 ? (int) round( $h['success'] / $finished * 100 ) : null;
		$h['risk'] = self::risk_label( $h );

		set_transient( $key, $h, 10 * MINUTE_IN_SECONDS );
		return $h;
	}

	public static function risk_label( $h ) {
		if ( ! empty( $h['blocked'] ) ) {
			return array( 'blocked', __( 'Blocked', 'rar-woo-cart-checkout' ) );
		}
		$finished = $h['success'] + $h['failed'];
		if ( $finished < 1 ) {
			return $h['total'] > 1 ? array( 'neutral', __( 'Returning (no delivered yet)', 'rar-woo-cart-checkout' ) ) : array( 'new', __( 'New customer', 'rar-woo-cart-checkout' ) );
		}
		if ( $h['rate'] >= 80 ) {
			return array( 'good', __( 'Trusted', 'rar-woo-cart-checkout' ) );
		}
		if ( $h['rate'] >= 50 ) {
			return array( 'warn', __( 'Watch', 'rar-woo-cart-checkout' ) );
		}
		return array( 'bad', __( 'High risk', 'rar-woo-cart-checkout' ) );
	}

	public static function flush_for_order( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( $order && $order->get_billing_phone() ) {
			delete_transient( self::cache_key( $order->get_billing_phone() ) );
		}
		delete_transient( 'rar_wcc_stats' );
	}

	/* ── Blocklist ───────────────────────────────────────────────────── */

	public static function blocklist() {
		$lines = preg_split( '/\r\n|\r|\n/', (string) RAR_WCC_Settings::get( 'guard_blocklist', '' ) );
		return array_values( array_filter( array_map( 'trim', $lines ) ) );
	}

	public static function is_blocked( $phone = '', $email = '', $ip = '' ) {
		$local = RAR_WCC_Phone::local( $phone );
		$email = strtolower( trim( (string) $email ) );
		foreach ( self::blocklist() as $entry ) {
			$entry_local = RAR_WCC_Phone::local( $entry );
			if ( $entry_local && $local && $entry_local === $local ) {
				return true;
			}
			if ( $email && false !== strpos( $entry, '@' ) && strtolower( $entry ) === $email ) {
				return true;
			}
			if ( $ip && preg_match( '/^[0-9a-f\.:\*]+$/i', $entry ) && ! $entry_local ) {
				$pattern = '/^' . str_replace( '\*', '.*', preg_quote( $entry, '/' ) ) . '$/i';
				if ( preg_match( $pattern, $ip ) ) {
					return true;
				}
			}
		}
		return false;
	}

	public static function set_blocked( $phone, $block ) {
		$local = RAR_WCC_Phone::local( $phone );
		$list  = array();
		foreach ( self::blocklist() as $entry ) {
			$entry_local = RAR_WCC_Phone::local( $entry );
			if ( $local && $entry_local === $local ) {
				continue;
			}
			if ( ! $local && strtolower( $entry ) === strtolower( trim( $phone ) ) ) {
				continue;
			}
			$list[] = $entry;
		}
		if ( $block ) {
			$list[] = $local ? $local : trim( $phone );
		}
		$settings                    = RAR_WCC_Settings::all();
		$settings['guard_blocklist'] = implode( "\n", $list );
		RAR_WCC_Settings::save( $settings );
		delete_transient( self::cache_key( $phone ) );
	}

	/* ── Checkout rules ──────────────────────────────────────────────── */

	private static function log_block( $phone, $reason ) {
		$log = get_option( 'rar_wcc_block_log', array() );
		$log = is_array( $log ) ? $log : array();
		array_unshift(
			$log,
			array(
				'time'   => time(),
				'phone'  => RAR_WCC_Phone::local( $phone ) ? RAR_WCC_Phone::local( $phone ) : sanitize_text_field( $phone ),
				'reason' => $reason,
			)
		);
		update_option( 'rar_wcc_block_log', array_slice( $log, 0, 50 ), false );
		update_option( 'rar_wcc_block_total', (int) get_option( 'rar_wcc_block_total', 0 ) + 1, false );
	}

	public static function validate( $data, $errors ) {
		if ( ! RAR_WCC_Settings::yes( 'guard_enabled' ) || $errors->has_errors() ) {
			return;
		}

		$phone   = (string) ( $data['billing_phone'] ?? '' );
		$email   = (string) ( $data['billing_email'] ?? '' );
		$ip      = class_exists( 'WC_Geolocation' ) ? WC_Geolocation::get_ip_address() : '';
		$total   = WC()->cart ? (float) WC()->cart->get_total( 'edit' ) : 0;
		$payment = (string) ( $data['payment_method'] ?? '' );

		if ( self::is_blocked( $phone, $email, $ip ) ) {
			self::log_block( $phone, 'blocklist' );
			$errors->add( 'rar_wcc_blocked', esc_html( RAR_WCC_Settings::text( 'guard_message' ) ) );
			return;
		}

		$min = RAR_WCC_Settings::num( 'guard_min_total' );
		if ( $min > 0 && $total < $min ) {
			/* translators: %s: minimum amount */
			$errors->add( 'rar_wcc_min_total', sprintf( __( 'The minimum order amount is %s.', 'rar-woo-cart-checkout' ), wc_price( $min ) ) );
			return;
		}

		$max_cod = RAR_WCC_Settings::num( 'guard_max_cod' );
		if ( 'cod' === $payment && $max_cod > 0 && $total > $max_cod ) {
			self::log_block( $phone, 'max_cod' );
			/* translators: %s: maximum amount */
			$errors->add( 'rar_wcc_max_cod', sprintf( __( 'Cash on Delivery is available for orders up to %s. Please choose an online payment method.', 'rar-woo-cart-checkout' ), wc_price( $max_cod ) ) );
			return;
		}

		$cooldown = (int) RAR_WCC_Settings::num( 'guard_cooldown' );
		if ( $cooldown > 0 && RAR_WCC_Phone::local( $phone ) ) {
			$recent = self::order_ids_for_phone(
				$phone,
				array(
					'limit'        => 1,
					'date_created' => '>' . ( time() - $cooldown * MINUTE_IN_SECONDS ),
					'status'       => array_diff( array_keys( wc_get_order_statuses() ), array( 'wc-failed', 'wc-cancelled' ) ),
				)
			);
			if ( $recent ) {
				self::log_block( $phone, 'cooldown' );
				$errors->add( 'rar_wcc_cooldown', esc_html( RAR_WCC_Settings::text( 'guard_cooldown_message' ) ) );
				return;
			}
		}

		$min_rate = (int) RAR_WCC_Settings::num( 'guard_min_success' );
		if ( 'cod' === $payment && $min_rate > 0 && RAR_WCC_Phone::local( $phone ) ) {
			$h = self::history( $phone );
			if ( $h && ( $h['success'] + $h['failed'] ) >= 3 && null !== $h['rate'] && $h['rate'] < $min_rate ) {
				self::log_block( $phone, 'low_success' );
				$errors->add( 'rar_wcc_low_success', __( 'Cash on Delivery is not available for this number. Please choose an online payment method or call us.', 'rar-woo-cart-checkout' ) );
				return;
			}
		}

		/**
		 * Add your own rules (e.g. courier fraud-check APIs).
		 *
		 * @param WP_Error $errors Checkout errors.
		 * @param array    $data   Posted checkout data.
		 */
		do_action( 'rar_wcc_guard_validate', $errors, $data );
	}

	/* ── Admin: meta box & column ────────────────────────────────────── */

	public static function meta_box() {
		if ( ! RAR_WCC_Settings::yes( 'history_enabled' ) ) {
			return;
		}
		foreach ( array( 'shop_order', function_exists( 'wc_get_page_screen_id' ) ? wc_get_page_screen_id( 'shop-order' ) : 'woocommerce_page_wc-orders' ) as $screen ) {
			add_meta_box( 'rar-wcc-customer', __( 'Customer Insight', 'rar-woo-cart-checkout' ), array( __CLASS__, 'render_meta_box' ), $screen, 'side', 'high' );
		}
	}

	public static function render_meta_box( $post_or_order ) {
		$order = $post_or_order instanceof WC_Order ? $post_or_order : wc_get_order( $post_or_order->ID ?? 0 );
		if ( ! $order ) {
			return;
		}
		$phone = $order->get_billing_phone();
		$h     = self::history( $phone );
		if ( ! $h ) {
			echo '<p>' . esc_html__( 'No phone number on this order.', 'rar-woo-cart-checkout' ) . '</p>';
			return;
		}
		$source = $order->get_meta( '_rar_wcc_source' );
		$wa     = RAR_WCC_Phone::whatsapp( $phone );
		?>
		<div class="rar-wcc-insight">
			<div class="rar-wcc-insight-head">
				<span class="rar-wcc-badge is-<?php echo esc_attr( $h['risk'][0] ); ?>"><?php echo esc_html( $h['risk'][1] ); ?></span>
				<?php if ( $source ) : ?>
					<span class="rar-wcc-badge is-source"><?php echo esc_html( 'express' === $source ? __( 'Express Buy Now', 'rar-woo-cart-checkout' ) : ( 'recovered' === $source ? __( 'Recovered', 'rar-woo-cart-checkout' ) : __( 'Checkout', 'rar-woo-cart-checkout' ) ) ); ?></span>
				<?php endif; ?>
			</div>
			<p class="rar-wcc-insight-phone"><strong><?php echo esc_html( $phone ); ?></strong><?php echo $h['operator'] ? ' · ' . esc_html( $h['operator'] ) : ''; ?></p>
			<div class="rar-wcc-insight-grid">
				<div><b><?php echo esc_html( $h['total'] ); ?></b><span><?php esc_html_e( 'Orders', 'rar-woo-cart-checkout' ); ?></span></div>
				<div class="ok"><b><?php echo esc_html( $h['success'] ); ?></b><span><?php esc_html_e( 'Delivered', 'rar-woo-cart-checkout' ); ?></span></div>
				<div class="bad"><b><?php echo esc_html( $h['failed'] ); ?></b><span><?php esc_html_e( 'Cancelled', 'rar-woo-cart-checkout' ); ?></span></div>
				<div><b><?php echo null === $h['rate'] ? '—' : esc_html( $h['rate'] . '%' ); ?></b><span><?php esc_html_e( 'Success', 'rar-woo-cart-checkout' ); ?></span></div>
			</div>
			<?php if ( null !== $h['rate'] ) : ?>
				<div class="rar-wcc-meter"><span style="width:<?php echo esc_attr( $h['rate'] ); ?>%"></span></div>
			<?php endif; ?>
			<p class="description">
				<?php
				/* translators: %s: amount */
				printf( esc_html__( 'Lifetime delivered value: %s', 'rar-woo-cart-checkout' ), wp_kses_post( wc_price( $h['spent'] ) ) );
				?>
			</p>
			<?php if ( count( $h['recent'] ) > 1 ) : ?>
				<ul class="rar-wcc-insight-orders">
					<?php foreach ( $h['recent'] as $r ) : ?>
						<?php
						if ( (int) $r['id'] === (int) $order->get_id() ) {
							continue;
						}
						?>
						<li><a href="<?php echo esc_url( $r['url'] ); ?>">#<?php echo esc_html( $r['number'] ); ?></a>
							<mark class="order-status status-<?php echo esc_attr( $r['status'] ); ?>"><span><?php echo esc_html( $r['label'] ); ?></span></mark>
							<span><?php echo wp_kses_post( wc_price( $r['total'] ) ); ?></span>
							<small><?php echo esc_html( $r['date'] ? wp_date( 'd M y', $r['date'] ) : '' ); ?></small></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<div class="rar-wcc-insight-actions">
				<a class="button" href="tel:<?php echo esc_attr( RAR_WCC_Phone::local( $phone ) ? RAR_WCC_Phone::local( $phone ) : $phone ); ?>">📞 <?php esc_html_e( 'Call', 'rar-woo-cart-checkout' ); ?></a>
				<?php if ( $wa ) : ?>
					<a class="button" target="_blank" rel="noopener" href="<?php echo esc_url( 'https://wa.me/' . $wa ); ?>">💬 WhatsApp</a>
				<?php endif; ?>
				<a class="button <?php echo $h['blocked'] ? '' : 'rar-wcc-danger'; ?>" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=rar_wcc_block&order=' . $order->get_id() . '&block=' . ( $h['blocked'] ? 0 : 1 ) ), 'rar_wcc_block' ) ); ?>" onclick="return confirm('<?php echo esc_js( $h['blocked'] ? __( 'Unblock this customer?', 'rar-woo-cart-checkout' ) : __( 'Block this phone number from placing new orders?', 'rar-woo-cart-checkout' ) ); ?>');">
					<?php echo $h['blocked'] ? '✅ ' . esc_html__( 'Unblock', 'rar-woo-cart-checkout' ) : '⛔ ' . esc_html__( 'Block', 'rar-woo-cart-checkout' ); ?>
				</a>
			</div>
		</div>
		<?php
	}

	public static function handle_block() {
		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'rar-woo-cart-checkout' ) );
		}
		check_admin_referer( 'rar_wcc_block' );
		$order = wc_get_order( absint( $_GET['order'] ?? 0 ) );
		$block = ! empty( $_GET['block'] );
		if ( $order && $order->get_billing_phone() ) {
			self::set_blocked( $order->get_billing_phone(), $block );
			$order->add_order_note(
				$block
					/* translators: %s: user name */
					? sprintf( __( 'Customer phone blocked by %s (RAR Cart & Checkout).', 'rar-woo-cart-checkout' ), wp_get_current_user()->display_name )
					/* translators: %s: user name */
					: sprintf( __( 'Customer phone unblocked by %s (RAR Cart & Checkout).', 'rar-woo-cart-checkout' ), wp_get_current_user()->display_name )
			);
		}
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
		exit;
	}

	public static function add_column( $columns ) {
		if ( ! RAR_WCC_Settings::yes( 'history_enabled' ) || ! RAR_WCC_Settings::yes( 'history_column' ) ) {
			return $columns;
		}
		$out = array();
		foreach ( $columns as $key => $label ) {
			$out[ $key ] = $label;
			if ( 'order_status' === $key ) {
				$out['rar_wcc_customer'] = __( 'Customer score', 'rar-woo-cart-checkout' );
			}
		}
		if ( ! isset( $out['rar_wcc_customer'] ) ) {
			$out['rar_wcc_customer'] = __( 'Customer score', 'rar-woo-cart-checkout' );
		}
		return $out;
	}

	public static function render_column_legacy( $column, $post_id ) {
		if ( 'rar_wcc_customer' === $column ) {
			self::render_column( $column, wc_get_order( $post_id ) );
		}
	}

	public static function render_column( $column, $order ) {
		if ( 'rar_wcc_customer' !== $column || ! $order instanceof WC_Order ) {
			return;
		}
		$h = self::history( $order->get_billing_phone() );
		if ( ! $h ) {
			echo '—';
			return;
		}
		$label = null === $h['rate'] ? $h['risk'][1] : $h['rate'] . '% · ' . $h['success'] . '/' . ( $h['success'] + $h['failed'] );
		$src   = $order->get_meta( '_rar_wcc_source' );
		printf(
			'<span class="rar-wcc-badge is-%1$s" title="%2$s">%3$s</span>%4$s',
			esc_attr( $h['risk'][0] ),
			/* translators: 1: total orders, 2: delivered, 3: cancelled */
			esc_attr( sprintf( __( '%1$d orders · %2$d delivered · %3$d cancelled', 'rar-woo-cart-checkout' ), $h['total'], $h['success'], $h['failed'] ) ),
			esc_html( $label ),
			'express' === $src ? ' <span class="rar-wcc-badge is-source" title="' . esc_attr__( 'Express Buy Now', 'rar-woo-cart-checkout' ) . '">⚡</span>' : ( 'recovered' === $src ? ' <span class="rar-wcc-badge is-source" title="' . esc_attr__( 'Recovered from incomplete', 'rar-woo-cart-checkout' ) . '">↺</span>' : '' )
		);
	}
}
