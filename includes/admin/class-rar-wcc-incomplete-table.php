<?php
/**
 * Incomplete orders list table.
 *
 * @package RAR_Woo_Cart_Checkout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class RAR_WCC_Incomplete_Table extends WP_List_Table {

	private $counts = array();

	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'incomplete',
				'plural'   => 'incompletes',
				'ajax'     => false,
			)
		);
	}

	public function get_columns() {
		return array(
			'cb'       => '<input type="checkbox" />',
			'customer' => __( 'Customer', 'rar-woo-cart-checkout' ),
			'location' => __( 'Address', 'rar-woo-cart-checkout' ),
			'cart'     => __( 'Cart', 'rar-woo-cart-checkout' ),
			'status'   => __( 'Status', 'rar-woo-cart-checkout' ),
			'updated'  => __( 'Last activity', 'rar-woo-cart-checkout' ),
			'actions'  => __( 'Actions', 'rar-woo-cart-checkout' ),
		);
	}

	protected function get_sortable_columns() {
		return array(
			'updated' => array( 'updated_at', true ),
			'cart'    => array( 'cart_total', false ),
			'status'  => array( 'status', false ),
		);
	}

	protected function get_bulk_actions() {
		$out = array();
		foreach ( RAR_WCC_Incomplete::statuses() as $k => $label ) {
			if ( in_array( $k, array( 'contacted', 'callback', 'lost', 'spam', 'new' ), true ) ) {
				/* translators: %s: status label */
				$out[ 'mark_' . $k ] = sprintf( __( 'Mark as: %s', 'rar-woo-cart-checkout' ), $label );
			}
		}
		$out['export'] = __( 'Export CSV', 'rar-woo-cart-checkout' );
		if ( current_user_can( 'manage_woocommerce' ) ) {
			$out['delete'] = __( 'Delete', 'rar-woo-cart-checkout' );
		}
		return $out;
	}

	protected function get_views() {
		$current = sanitize_key( wp_unslash( $_GET['status'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$base    = admin_url( 'admin.php?page=rar-wcc-incomplete' );
		$counts  = $this->counts;
		$open    = array_sum( array_intersect_key( $counts, array_flip( RAR_WCC_Incomplete::open_statuses() ) ) );
		$views   = array(
			'all'  => sprintf( '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>', esc_url( $base ), '' === $current ? 'current' : '', esc_html__( 'All', 'rar-woo-cart-checkout' ), array_sum( $counts ) ),
			'open' => sprintf( '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>', esc_url( add_query_arg( 'status', 'open', $base ) ), 'open' === $current ? 'current' : '', esc_html__( 'Needs follow-up', 'rar-woo-cart-checkout' ), $open ),
		);
		foreach ( RAR_WCC_Incomplete::statuses() as $k => $label ) {
			if ( empty( $counts[ $k ] ) ) {
				continue;
			}
			$views[ $k ] = sprintf( '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>', esc_url( add_query_arg( 'status', $k, $base ) ), $current === $k ? 'current' : '', esc_html( $label ), $counts[ $k ] );
		}
		return $views;
	}

	public function prepare_items() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$per_page     = $this->get_items_per_page( 'rar_wcc_incomplete_per_page', 20 );
		$this->counts = RAR_WCC_Incomplete::counts();
		$res          = RAR_WCC_Incomplete::query(
			array(
				'status'   => sanitize_key( wp_unslash( $_GET['status'] ?? '' ) ),
				'search'   => sanitize_text_field( wp_unslash( $_REQUEST['s'] ?? '' ) ),
				'per_page' => $per_page,
				'page'     => $this->get_pagenum(),
				'orderby'  => sanitize_key( wp_unslash( $_GET['orderby'] ?? 'updated_at' ) ),
				'order'    => sanitize_key( wp_unslash( $_GET['order'] ?? 'desc' ) ),
			)
		);
		// phpcs:enable
		$this->items           = $res['items'];
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
		$this->set_pagination_args(
			array(
				'total_items' => $res['total'],
				'per_page'    => $per_page,
			)
		);
	}

	public function no_items() {
		esc_html_e( 'No incomplete checkouts yet. They appear here as soon as a shopper types a valid phone number on checkout and leaves without ordering.', 'rar-woo-cart-checkout' );
	}

	protected function column_cb( $item ) {
		return '<input type="checkbox" name="ids[]" value="' . esc_attr( $item['id'] ) . '" />';
	}

	protected function column_customer( $item ) {
		$op   = RAR_WCC_Phone::operator( $item['phone'] );
		$hist = RAR_WCC_Settings::yes( 'history_enabled' ) ? RAR_WCC_Guard::history( $item['phone'] ) : null;
		$html = '<strong>' . esc_html( $item['name'] ? $item['name'] : __( '(no name)', 'rar-woo-cart-checkout' ) ) . '</strong>';
		$html .= '<br><a class="rar-wcc-phone" href="tel:' . esc_attr( $item['phone'] ) . '">' . esc_html( $item['phone'] ) . '</a>';
		if ( $op ) {
			$html .= ' <small class="rar-wcc-muted">' . esc_html( $op ) . '</small>';
		}
		if ( $item['email'] ) {
			$html .= '<br><small>' . esc_html( $item['email'] ) . '</small>';
		}
		$meta = array();
		$meta[] = 'express' === $item['source'] ? '⚡ ' . __( 'Express', 'rar-woo-cart-checkout' ) : __( 'Checkout', 'rar-woo-cart-checkout' );
		if ( $item['attempts'] > 1 ) {
			/* translators: %d: number of visits */
			$meta[] = sprintf( _n( '%d update', '%d updates', $item['attempts'], 'rar-woo-cart-checkout' ), $item['attempts'] );
		}
		$html .= '<br><small class="rar-wcc-muted">' . esc_html( implode( ' · ', $meta ) ) . '</small>';
		if ( $hist && $hist['total'] > 0 ) {
			$html .= '<br><span class="rar-wcc-badge is-' . esc_attr( $hist['risk'][0] ) . '">' . esc_html( $hist['risk'][1] ) . ( null !== $hist['rate'] ? ' · ' . esc_html( $hist['rate'] ) . '%' : '' ) . '</span>';
		}
		return $html;
	}

	protected function column_location( $item ) {
		$parts = array_filter( array( $item['city'], $item['district'] ) );
		$html  = $parts ? '<strong>' . esc_html( implode( ', ', $parts ) ) . '</strong>' : '<span class="rar-wcc-muted">—</span>';
		if ( $item['address'] ) {
			$html .= '<br>' . esc_html( $item['address'] );
		}
		if ( $item['customer_note'] ) {
			$html .= '<br><em class="rar-wcc-muted">“' . esc_html( wp_trim_words( $item['customer_note'], 14 ) ) . '”</em>';
		}
		return $html;
	}

	protected function column_cart( $item ) {
		$lines = array();
		foreach ( (array) $item['cart'] as $c ) {
			$url     = get_edit_post_link( $c['product_id'] );
			$label   = esc_html( $c['name'] ) . ' <b>×' . (int) $c['qty'] . '</b>';
			$lines[] = $url ? '<a href="' . esc_url( $url ) . '">' . $label . '</a>' : $label;
		}
		return '<div class="rar-wcc-cart-lines">' . implode( '<br>', $lines ) . '</div><strong>' . wp_kses_post( wc_price( $item['cart_total'], array( 'currency' => $item['currency'] ) ) ) . '</strong>';
	}

	protected function column_status( $item ) {
		$html = '<select class="rar-wcc-status-select is-' . esc_attr( $item['status'] ) . '" data-id="' . esc_attr( $item['id'] ) . '">';
		foreach ( RAR_WCC_Incomplete::statuses() as $k => $label ) {
			$html .= '<option value="' . esc_attr( $k ) . '"' . selected( $item['status'], $k, false ) . '>' . esc_html( $label ) . '</option>';
		}
		$html .= '</select>';
		$html .= '<div class="rar-wcc-note" data-id="' . esc_attr( $item['id'] ) . '">' . ( $item['admin_note'] ? '📝 <span>' . esc_html( $item['admin_note'] ) . '</span>' : '<span class="rar-wcc-muted">' . esc_html__( '+ Add note', 'rar-woo-cart-checkout' ) . '</span>' ) . '</div>';
		return $html;
	}

	protected function column_updated( $item ) {
		$ts = strtotime( $item['updated_at'] . ' UTC' );
		/* translators: %s: human time diff */
		$ago  = sprintf( __( '%s ago', 'rar-woo-cart-checkout' ), human_time_diff( $ts ) );
		$full = wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $ts );
		return '<span title="' . esc_attr( $full ) . '">' . esc_html( $ago ) . '</span>';
	}

	protected function column_actions( $item ) {
		$btns   = array();
		$btns[] = '<a class="button button-small" href="tel:' . esc_attr( $item['phone'] ) . '" title="' . esc_attr__( 'Call', 'rar-woo-cart-checkout' ) . '">📞</a>';
		$wa     = RAR_WCC_Incomplete::whatsapp_url( $item );
		if ( $wa ) {
			$btns[] = '<a class="button button-small" target="_blank" rel="noopener" href="' . esc_url( $wa ) . '" title="WhatsApp">💬</a>';
		}
		$order = $item['order_id'] ? wc_get_order( $item['order_id'] ) : null;
		if ( $order ) {
			$btns[] = '<a class="button button-small button-primary" href="' . esc_url( $order->get_edit_order_url() ) . '">' . esc_html( sprintf( /* translators: %s: order number */ __( 'Order #%s', 'rar-woo-cart-checkout' ), $order->get_order_number() ) ) . '</a>';
		} elseif ( $item['cart'] ) {
			$url    = wp_nonce_url( admin_url( 'admin-post.php?action=rar_wcc_incomplete_order&id=' . $item['id'] ), 'rar_wcc_incomplete_order_' . $item['id'] );
			$btns[] = '<a class="button button-small button-primary" href="' . esc_url( $url ) . '" onclick="return confirm(\'' . esc_js( __( 'Create a WooCommerce order from this record?', 'rar-woo-cart-checkout' ) ) . '\');">' . esc_html__( 'Create order', 'rar-woo-cart-checkout' ) . '</a>';
		}
		return '<div class="rar-wcc-row-actions">' . implode( ' ', $btns ) . '</div>';
	}
}
