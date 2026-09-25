<?php
/**
 * Incomplete checkout capture and recovery.
 *
 * @package RAR_Woo_Cart_Checkout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RAR_WCC_Incomplete {

	public static function statuses() {
		return array(
			'new'       => __( 'New', 'rar-woo-cart-checkout' ),
			'contacted' => __( 'Contacted', 'rar-woo-cart-checkout' ),
			'callback'  => __( 'Call back later', 'rar-woo-cart-checkout' ),
			'recovered' => __( 'Recovered', 'rar-woo-cart-checkout' ),
			'converted' => __( 'Ordered by customer', 'rar-woo-cart-checkout' ),
			'lost'      => __( 'Not interested', 'rar-woo-cart-checkout' ),
			'spam'      => __( 'Spam / fake', 'rar-woo-cart-checkout' ),
		);
	}

	/** Statuses where the lead is still actionable. */
	public static function open_statuses() {
		return array( 'new', 'contacted', 'callback' );
	}

	public static function init() {
		add_action( 'wc_ajax_rar_wcc_capture', array( __CLASS__, 'ajax_capture' ) );
		add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'mark_converted' ), 20, 1 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( __CLASS__, 'mark_converted' ), 20, 1 );
	}

	private static function now() {
		return current_time( 'mysql', true );
	}

	private static function session_key() {
		if ( ! WC()->session ) {
			return '';
		}
		if ( ! WC()->session->has_session() ) {
			WC()->session->set_customer_session_cookie( true );
		}
		return (string) WC()->session->get_customer_id();
	}

	/**
	 * Snapshot of the current cart.
	 */
	public static function cart_snapshot() {
		$items = array();
		if ( ! WC()->cart ) {
			return $items;
		}
		foreach ( WC()->cart->get_cart() as $item ) {
			$product = $item['data'];
			if ( ! $product ) {
				continue;
			}
			$items[] = array(
				'product_id'   => (int) $item['product_id'],
				'variation_id' => (int) $item['variation_id'],
				'variation'    => array_map( 'wc_clean', (array) ( $item['variation'] ?? array() ) ),
				'qty'          => (int) $item['quantity'],
				'name'         => wp_strip_all_tags( $product->get_name() ),
				'sku'          => (string) $product->get_sku(),
				'price'        => (float) wc_get_price_to_display( $product ),
			);
		}
		return $items;
	}

	/**
	 * Rate-limited, nonce-protected capture of checkout fields.
	 */
	public static function ajax_capture() {
		if ( ! RAR_WCC_Settings::yes( 'incomplete_enabled' ) ) {
			wp_send_json_success( array( 'skipped' => true ) );
		}
		check_ajax_referer( 'rar-wcc-checkout', 'nonce' );

		$phone = RAR_WCC_Phone::local( wp_unslash( $_POST['phone'] ?? '' ) );
		if ( '' === $phone || ! WC()->cart || WC()->cart->is_empty() ) {
			wp_send_json_success( array( 'skipped' => true ) );
		}

		$session = self::session_key();
		$rl_key  = 'rar_wcc_rl_' . md5( $session . ( class_exists( 'WC_Geolocation' ) ? WC_Geolocation::get_ip_address() : '' ) );
		$hits    = (int) get_transient( $rl_key );
		if ( $hits > 40 ) {
			wp_send_json_success( array( 'skipped' => 'rate' ) );
		}
		set_transient( $rl_key, $hits + 1, HOUR_IN_SECONDS );

		$state = sanitize_text_field( wp_unslash( $_POST['state'] ?? '' ) );
		$data  = array(
			'phone'         => RAR_WCC_Phone::format( $phone ),
			'name'          => mb_substr( sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ), 0, 190 ),
			'email'         => mb_substr( sanitize_email( wp_unslash( $_POST['email'] ?? '' ) ), 0, 190 ),
			'address'       => sanitize_textarea_field( wp_unslash( $_POST['address'] ?? '' ) ),
			'district'      => $state ? RAR_WCC_Locations::district_label( $state ) : '',
			'city'          => mb_substr( sanitize_text_field( wp_unslash( $_POST['city'] ?? '' ) ), 0, 100 ),
			'customer_note' => sanitize_textarea_field( wp_unslash( $_POST['note'] ?? '' ) ),
			'cart'          => wp_json_encode( self::cart_snapshot() ),
			'cart_total'    => (float) WC()->cart->get_total( 'edit' ),
			'currency'      => get_woocommerce_currency(),
			'source'        => 'express' === ( $_POST['source'] ?? '' ) ? 'express' : 'checkout',
			'user_id'       => get_current_user_id(),
			'ip'            => class_exists( 'WC_Geolocation' ) ? WC_Geolocation::get_ip_address() : '',
			'user_agent'    => mb_substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ) ), 0, 255 ),
			'updated_at'    => self::now(),
		);

		$id = self::upsert( $session, $data );
		wp_send_json_success( array( 'id' => $id ) );
	}

	/**
	 * One open row per visitor session (or phone). Returns row id.
	 */
	public static function upsert( $session, $data ) {
		global $wpdb;
		$table = RAR_WCC_Install::table();
		$open  = "'" . implode( "','", array_map( 'esc_sql', self::open_statuses() ) ) . "'";
		$local = RAR_WCC_Phone::local( $data['phone'] );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, attempts FROM {$table} WHERE status IN ({$open}) AND ( ( session_key <> '' AND session_key = %s ) OR phone IN (%s, %s) ) ORDER BY updated_at DESC LIMIT 1",
				$session,
				$local,
				'+88' . $local
			)
		);

		if ( $row ) {
			$data['attempts']    = (int) $row->attempts + 1;
			$data['session_key'] = $session;
			$wpdb->update( $table, $data, array( 'id' => $row->id ) );
			$id = (int) $row->id;
		} else {
			$data['session_key'] = $session;
			$data['status']      = 'new';
			$data['created_at']  = $data['updated_at'];
			$wpdb->insert( $table, $data );
			$id = (int) $wpdb->insert_id;
			do_action( 'rar_wcc_incomplete_captured', $id, $data );
		}
		// phpcs:enable
		delete_transient( 'rar_wcc_stats' );
		return $id;
	}

	/**
	 * Order placed → close matching open records.
	 */
	public static function mark_converted( $order ) {
		global $wpdb;
		$order = $order instanceof WC_Order ? $order : wc_get_order( $order );
		if ( ! $order ) {
			return;
		}
		$table   = RAR_WCC_Install::table();
		$local   = RAR_WCC_Phone::local( $order->get_billing_phone() );
		$session = WC()->session ? (string) WC()->session->get_customer_id() : '';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, status FROM {$table} WHERE status IN ('new','contacted','callback') AND ( ( session_key <> '' AND session_key = %s ) OR ( phone <> '' AND phone IN (%s, %s) ) )",
				$session,
				$local ? $local : '-',
				$local ? '+88' . $local : '-'
			)
		);
		foreach ( $rows as $row ) {
			// Contacted by staff and then ordered = recovered by your team.
			$status = in_array( $row->status, array( 'contacted', 'callback' ), true ) ? 'recovered' : 'converted';
			$wpdb->update(
				$table,
				array(
					'status'     => $status,
					'order_id'   => $order->get_id(),
					'updated_at' => self::now(),
				),
				array( 'id' => $row->id )
			);
			do_action( 'rar_wcc_incomplete_status_changed', (int) $row->id, $status, $row->status );
		}
		// phpcs:enable
		delete_transient( 'rar_wcc_stats' );
	}

	/* ── Data access (admin + REST) ──────────────────────────────────── */

	public static function get( $id ) {
		global $wpdb;
		$table = RAR_WCC_Install::table();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A ); // phpcs:ignore
		return $row ? self::hydrate( $row ) : null;
	}

	public static function hydrate( $row ) {
		$row['id']         = (int) $row['id'];
		$row['order_id']   = (int) $row['order_id'];
		$row['cart_total'] = (float) $row['cart_total'];
		$row['attempts']   = (int) $row['attempts'];
		$row['cart']       = json_decode( (string) $row['cart'], true ) ?: array();
		unset( $row['session_key'], $row['user_agent'] );
		return $row;
	}

	/**
	 * @param array $args status, search, per_page, page, orderby, order, since.
	 * @return array{items: array, total: int}
	 */
	public static function query( $args = array() ) {
		global $wpdb;
		$table = RAR_WCC_Install::table();
		$args  = wp_parse_args(
			$args,
			array(
				'status'   => '',
				'search'   => '',
				'per_page' => 20,
				'page'     => 1,
				'orderby'  => 'updated_at',
				'order'    => 'DESC',
				'since'    => '',
			)
		);

		$where = array( '1=1' );
		$vals  = array();
		if ( 'open' === $args['status'] ) {
			$where[] = "status IN ('new','contacted','callback')";
		} elseif ( $args['status'] && isset( self::statuses()[ $args['status'] ] ) ) {
			$where[] = 'status = %s';
			$vals[]  = $args['status'];
		}
		if ( '' !== $args['search'] ) {
			$like    = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$local   = RAR_WCC_Phone::local( $args['search'] );
			$where[] = '( name LIKE %s OR phone LIKE %s OR address LIKE %s OR city LIKE %s OR district LIKE %s )';
			array_push( $vals, $like, $local ? '%' . $wpdb->esc_like( substr( $local, 1 ) ) . '%' : $like, $like, $like, $like );
		}
		if ( $args['since'] ) {
			$where[] = 'updated_at >= %s';
			$vals[]  = $args['since'];
		}

		$orderby = in_array( $args['orderby'], array( 'updated_at', 'created_at', 'cart_total', 'name', 'status', 'attempts' ), true ) ? $args['orderby'] : 'updated_at';
		$order   = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';
		$limit   = max( 1, min( 500, (int) $args['per_page'] ) );
		$offset  = ( max( 1, (int) $args['page'] ) - 1 ) * $limit;
		$w       = implode( ' AND ', $where );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery
		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$w}";
		$list_sql  = "SELECT * FROM {$table} WHERE {$w} ORDER BY {$orderby} {$order} LIMIT {$limit} OFFSET {$offset}";
		$total     = (int) ( $vals ? $wpdb->get_var( $wpdb->prepare( $count_sql, $vals ) ) : $wpdb->get_var( $count_sql ) );
		$rows      = $vals ? $wpdb->get_results( $wpdb->prepare( $list_sql, $vals ), ARRAY_A ) : $wpdb->get_results( $list_sql, ARRAY_A );
		// phpcs:enable

		return array(
			'items' => array_map( array( __CLASS__, 'hydrate' ), (array) $rows ),
			'total' => $total,
		);
	}

	public static function counts() {
		global $wpdb;
		$table = RAR_WCC_Install::table();
		$out   = array_fill_keys( array_keys( self::statuses() ), 0 );
		foreach ( (array) $wpdb->get_results( "SELECT status, COUNT(*) c FROM {$table} GROUP BY status" ) as $r ) { // phpcs:ignore
			$out[ $r->status ] = (int) $r->c;
		}
		return $out;
	}

	public static function update( $id, $fields ) {
		global $wpdb;
		$row = self::get( $id );
		if ( ! $row ) {
			return new WP_Error( 'not_found', __( 'Record not found.', 'rar-woo-cart-checkout' ), array( 'status' => 404 ) );
		}
		$data = array();
		if ( isset( $fields['status'] ) ) {
			if ( ! isset( self::statuses()[ $fields['status'] ] ) ) {
				return new WP_Error( 'bad_status', __( 'Invalid status.', 'rar-woo-cart-checkout' ), array( 'status' => 400 ) );
			}
			$data['status'] = $fields['status'];
		}
		if ( isset( $fields['admin_note'] ) ) {
			$data['admin_note'] = sanitize_textarea_field( $fields['admin_note'] );
		}
		if ( ! $data ) {
			return $row;
		}
		$data['updated_at'] = self::now();
		$wpdb->update( RAR_WCC_Install::table(), $data, array( 'id' => (int) $id ) ); // phpcs:ignore
		if ( isset( $data['status'] ) && $data['status'] !== $row['status'] ) {
			do_action( 'rar_wcc_incomplete_status_changed', (int) $id, $data['status'], $row['status'] );
		}
		delete_transient( 'rar_wcc_stats' );
		return self::get( $id );
	}

	public static function delete( $ids ) {
		global $wpdb;
		$ids = array_filter( array_map( 'absint', (array) $ids ) );
		if ( ! $ids ) {
			return 0;
		}
		$table = RAR_WCC_Install::table();
		$in    = implode( ',', $ids );
		delete_transient( 'rar_wcc_stats' );
		return (int) $wpdb->query( "DELETE FROM {$table} WHERE id IN ({$in})" ); // phpcs:ignore
	}

	/**
	 * Create a real WooCommerce order from a captured record.
	 *
	 * @return WC_Order|WP_Error
	 */
	public static function create_order( $id ) {
		$row = self::get( $id );
		if ( ! $row ) {
			return new WP_Error( 'not_found', __( 'Record not found.', 'rar-woo-cart-checkout' ), array( 'status' => 404 ) );
		}
		if ( $row['order_id'] && wc_get_order( $row['order_id'] ) ) {
			return new WP_Error( 'exists', __( 'An order already exists for this record.', 'rar-woo-cart-checkout' ), array( 'status' => 409 ) );
		}
		if ( ! $row['cart'] ) {
			return new WP_Error( 'empty', __( 'This record has no products.', 'rar-woo-cart-checkout' ), array( 'status' => 400 ) );
		}

		$order = wc_create_order(
			array(
				'customer_id' => (int) $row['user_id'],
				'created_via' => 'rar-wcc-recovery',
			)
		);
		if ( is_wp_error( $order ) ) {
			return $order;
		}

		$missing = array();
		foreach ( $row['cart'] as $item ) {
			$product = wc_get_product( $item['variation_id'] ? $item['variation_id'] : $item['product_id'] );
			if ( ! $product ) {
				$missing[] = $item['name'];
				continue;
			}
			$order->add_product( $product, max( 1, (int) $item['qty'] ), $item['variation_id'] ? array( 'variation' => (array) ( $item['variation'] ?? array() ) ) : array() );
		}

		$states = WC()->countries->get_states( 'BD' );
		$state  = array_search( $row['district'], array_map( 'trim', (array) $states ), true );
		$addr   = array(
			'first_name' => $row['name'],
			'phone'      => $row['phone'],
			'email'      => $row['email'],
			'address_1'  => $row['address'],
			'city'       => $row['city'],
			'state'      => false !== $state ? $state : $row['district'],
			'country'    => 'BD',
		);
		$order->set_address( $addr, 'billing' );
		unset( $addr['email'] );
		$order->set_address( $addr, 'shipping' );
		if ( $row['customer_note'] ) {
			$order->set_customer_note( $row['customer_note'] );
		}

		$gateways = WC()->payment_gateways() ? WC()->payment_gateways()->payment_gateways() : array();
		if ( isset( $gateways['cod'] ) ) {
			$order->set_payment_method( $gateways['cod'] );
		}
		$order->update_meta_data( '_rar_wcc_source', 'recovered' );
		$order->update_meta_data( '_rar_wcc_incomplete_id', $row['id'] );
		$order->calculate_totals();

		$status = RAR_WCC_Settings::get( 'incomplete_order_status', 'pending' );
		$note   = sprintf(
			/* translators: 1: record id, 2: user name */
			__( 'Created from incomplete checkout #%1$d by %2$s. Please verify shipping charge before confirming.', 'rar-woo-cart-checkout' ),
			$row['id'],
			wp_get_current_user()->display_name ? wp_get_current_user()->display_name : 'API'
		);
		if ( $missing ) {
			/* translators: %s: product names */
			$note .= ' ' . sprintf( __( 'Unavailable products skipped: %s.', 'rar-woo-cart-checkout' ), implode( ', ', $missing ) );
		}
		$order->set_status( $status, $note );
		$order->save();

		global $wpdb;
		$wpdb->update( // phpcs:ignore
			RAR_WCC_Install::table(),
			array(
				'status'     => 'recovered',
				'order_id'   => $order->get_id(),
				'updated_at' => self::now(),
			),
			array( 'id' => $row['id'] )
		);
		do_action( 'rar_wcc_incomplete_status_changed', (int) $row['id'], 'recovered', $row['status'] );
		do_action( 'rar_wcc_incomplete_order_created', $order, $row );
		delete_transient( 'rar_wcc_stats' );

		return $order;
	}

	public static function whatsapp_url( $row ) {
		$wa = RAR_WCC_Phone::whatsapp( $row['phone'] );
		if ( ! $wa ) {
			return '';
		}
		$names = array();
		foreach ( (array) $row['cart'] as $item ) {
			$names[] = $item['name'] . ( $item['qty'] > 1 ? ' ×' . $item['qty'] : '' );
		}
		$msg = strtr(
			(string) RAR_WCC_Settings::get( 'incomplete_whatsapp', '' ),
			array(
				'{name}'     => $row['name'] ? $row['name'] : '',
				'{products}' => implode( ', ', $names ),
				'{total}'    => wp_strip_all_tags( html_entity_decode( wc_price( $row['cart_total'] ), ENT_QUOTES, 'UTF-8' ) ),
				'{site}'     => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			)
		);
		return 'https://wa.me/' . $wa . ( $msg ? '?text=' . rawurlencode( trim( preg_replace( '/\s+/', ' ', $msg ) ) ) : '' );
	}
}
