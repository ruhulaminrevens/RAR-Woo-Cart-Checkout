<?php
/**
 * REST API (namespace rar-wcc/v1) — for staff apps and automations.
 * Auth: any logged-in user / Application Password with manage_woocommerce
 * (or edit_shop_orders for read + status updates).
 *
 * @package RAR_Woo_Cart_Checkout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RAR_WCC_Rest {

	public const NS = 'rar-wcc/v1';

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function can_read() {
		return current_user_can( 'manage_woocommerce' ) || current_user_can( 'edit_shop_orders' );
	}

	public static function can_manage() {
		return current_user_can( 'manage_woocommerce' );
	}

	public static function routes() {
		register_rest_route(
			self::NS,
			'/stats',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'permission_callback' => array( __CLASS__, 'can_read' ),
				'callback'            => static function ( WP_REST_Request $r ) {
					return rest_ensure_response( RAR_WCC_Stats::get( (bool) $r->get_param( 'refresh' ) ) );
				},
			)
		);

		register_rest_route(
			self::NS,
			'/incomplete',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'permission_callback' => array( __CLASS__, 'can_read' ),
				'args'                => array(
					'status'   => array( 'type' => 'string', 'default' => '' ),
					'search'   => array( 'type' => 'string', 'default' => '' ),
					'page'     => array( 'type' => 'integer', 'default' => 1, 'minimum' => 1 ),
					'per_page' => array( 'type' => 'integer', 'default' => 20, 'minimum' => 1, 'maximum' => 100 ),
				),
				'callback'            => static function ( WP_REST_Request $r ) {
					$res  = RAR_WCC_Incomplete::query(
						array(
							'status'   => sanitize_key( $r['status'] ),
							'search'   => sanitize_text_field( $r['search'] ),
							'page'     => (int) $r['page'],
							'per_page' => (int) $r['per_page'],
						)
					);
					$resp = rest_ensure_response( array_map( array( __CLASS__, 'present' ), $res['items'] ) );
					$resp->header( 'X-WP-Total', $res['total'] );
					$resp->header( 'X-WP-TotalPages', (int) ceil( $res['total'] / max( 1, (int) $r['per_page'] ) ) );
					return $resp;
				},
			)
		);

		register_rest_route(
			self::NS,
			'/incomplete/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'permission_callback' => array( __CLASS__, 'can_read' ),
					'callback'            => static function ( WP_REST_Request $r ) {
						$row = RAR_WCC_Incomplete::get( (int) $r['id'] );
						return $row ? self::present( $row ) : new WP_Error( 'not_found', __( 'Record not found.', 'rar-woo-cart-checkout' ), array( 'status' => 404 ) );
					},
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'permission_callback' => array( __CLASS__, 'can_read' ),
					'args'                => array(
						'status'     => array( 'type' => 'string' ),
						'admin_note' => array( 'type' => 'string' ),
					),
					'callback'            => static function ( WP_REST_Request $r ) {
						$fields = array_intersect_key( $r->get_params(), array_flip( array( 'status', 'admin_note' ) ) );
						$row    = RAR_WCC_Incomplete::update( (int) $r['id'], $fields );
						return is_wp_error( $row ) ? $row : self::present( $row );
					},
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'permission_callback' => array( __CLASS__, 'can_manage' ),
					'callback'            => static function ( WP_REST_Request $r ) {
						return array( 'deleted' => (bool) RAR_WCC_Incomplete::delete( array( (int) $r['id'] ) ) );
					},
				),
			)
		);

		register_rest_route(
			self::NS,
			'/incomplete/(?P<id>\d+)/order',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'permission_callback' => array( __CLASS__, 'can_read' ),
				'callback'            => static function ( WP_REST_Request $r ) {
					$order = RAR_WCC_Incomplete::create_order( (int) $r['id'] );
					if ( is_wp_error( $order ) ) {
						return $order;
					}
					return array(
						'order_id' => $order->get_id(),
						'number'   => $order->get_order_number(),
						'status'   => $order->get_status(),
						'total'    => (float) $order->get_total(),
						'edit_url' => $order->get_edit_order_url(),
					);
				},
			)
		);

		register_rest_route(
			self::NS,
			'/customer',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'permission_callback' => array( __CLASS__, 'can_read' ),
				'args'                => array( 'phone' => array( 'type' => 'string', 'required' => true ) ),
				'callback'            => static function ( WP_REST_Request $r ) {
					$phone = sanitize_text_field( $r['phone'] );
					$h     = RAR_WCC_Guard::history( $phone );
					if ( ! $h ) {
						return new WP_Error( 'bad_phone', __( 'Phone is required.', 'rar-woo-cart-checkout' ), array( 'status' => 400 ) );
					}
					$h['phone']       = RAR_WCC_Phone::local( $phone ) ? RAR_WCC_Phone::local( $phone ) : $phone;
					$h['valid_phone'] = RAR_WCC_Phone::is_valid( $phone );
					$h['risk']        = array( 'code' => $h['risk'][0], 'label' => $h['risk'][1] );
					return $h;
				},
			)
		);

		register_rest_route(
			self::NS,
			'/customer/block',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'permission_callback' => array( __CLASS__, 'can_manage' ),
				'args'                => array(
					'phone' => array( 'type' => 'string', 'required' => true ),
					'block' => array( 'type' => 'boolean', 'default' => true ),
				),
				'callback'            => static function ( WP_REST_Request $r ) {
					RAR_WCC_Guard::set_blocked( sanitize_text_field( $r['phone'] ), (bool) $r['block'] );
					return array( 'blocked' => RAR_WCC_Guard::is_blocked( $r['phone'] ) );
				},
			)
		);
	}

	public static function present( $row ) {
		$row['whatsapp_url'] = RAR_WCC_Incomplete::whatsapp_url( $row );
		$row['status_label'] = RAR_WCC_Incomplete::statuses()[ $row['status'] ] ?? $row['status'];
		$row['created_at']   = mysql_to_rfc3339( $row['created_at'] );
		$row['updated_at']   = mysql_to_rfc3339( $row['updated_at'] );
		unset( $row['ip'] );
		return $row;
	}
}
