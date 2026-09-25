<?php
/**
 * Dashboard / REST statistics (cached).
 *
 * @package RAR_Woo_Cart_Checkout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RAR_WCC_Stats {

	public static function get( $force = false ) {
		$cached = $force ? false : get_transient( 'rar_wcc_stats' );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$tz      = wp_timezone();
		$today   = ( new DateTimeImmutable( 'today', $tz ) )->getTimestamp();
		$days    = 14;
		$start   = $today - ( $days - 1 ) * DAY_IN_SECONDS;
		$since30 = $today - 29 * DAY_IN_SECONDS;
		$valid   = array_diff( array_keys( wc_get_order_statuses() ), array( 'wc-failed', 'wc-cancelled', 'wc-checkout-draft' ) );

		$series = array();
		for ( $i = 0; $i < $days; $i++ ) {
			$d            = wp_date( 'Y-m-d', $start + $i * DAY_IN_SECONDS );
			$series[ $d ] = array(
				'date'       => $d,
				'orders'     => 0,
				'express'    => 0,
				'recovered'  => 0,
				'incomplete' => 0,
				'revenue'    => 0.0,
			);
		}

		$totals = array(
			'orders_today'    => 0,
			'revenue_today'   => 0.0,
			'orders_30'       => 0,
			'revenue_30'      => 0.0,
			'express_30'      => 0,
			'express_rev_30'  => 0.0,
			'recovered_30'    => 0,
			'recovered_rev_30' => 0.0,
		);

		$page = 1;
		do {
			$orders = wc_get_orders(
				array(
					'type'         => 'shop_order',
					'status'       => $valid,
					'date_created' => '>=' . $since30,
					'limit'        => 250,
					'page'         => $page,
					'orderby'      => 'date',
					'order'        => 'DESC',
				)
			);
			foreach ( $orders as $o ) {
				$ts     = $o->get_date_created() ? $o->get_date_created()->getTimestamp() : 0;
				$total  = (float) $o->get_total();
				$source = $o->get_meta( '_rar_wcc_source' );
				++$totals['orders_30'];
				$totals['revenue_30'] += $total;
				if ( $ts >= $today ) {
					++$totals['orders_today'];
					$totals['revenue_today'] += $total;
				}
				if ( 'express' === $source ) {
					++$totals['express_30'];
					$totals['express_rev_30'] += $total;
				} elseif ( 'recovered' === $source ) {
					++$totals['recovered_30'];
					$totals['recovered_rev_30'] += $total;
				}
				$d = wp_date( 'Y-m-d', $ts );
				if ( isset( $series[ $d ] ) ) {
					++$series[ $d ]['orders'];
					$series[ $d ]['revenue'] += $total;
					if ( 'express' === $source ) {
						++$series[ $d ]['express'];
					} elseif ( 'recovered' === $source ) {
						++$series[ $d ]['recovered'];
					}
				}
			}
			++$page;
		} while ( count( $orders ) === 250 && $page <= 20 );

		// Incomplete checkouts.
		global $wpdb;
		$table = RAR_WCC_Install::table();
		$since = gmdate( 'Y-m-d H:i:s', $since30 );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT status, cart_total, created_at FROM {$table} WHERE created_at >= %s", $since ) );
		// phpcs:enable
		$inc = array(
			'captured_30'  => 0,
			'open_value'   => 0.0,
			'recovered_30' => 0,
			'converted_30' => 0,
			'lost_30'      => 0,
		);
		foreach ( (array) $rows as $r ) {
			++$inc['captured_30'];
			if ( in_array( $r->status, RAR_WCC_Incomplete::open_statuses(), true ) ) {
				$inc['open_value'] += (float) $r->cart_total;
			} elseif ( 'recovered' === $r->status ) {
				++$inc['recovered_30'];
			} elseif ( 'converted' === $r->status ) {
				++$inc['converted_30'];
			} elseif ( in_array( $r->status, array( 'lost', 'spam' ), true ) ) {
				++$inc['lost_30'];
			}
			$d = wp_date( 'Y-m-d', strtotime( $r->created_at . ' UTC' ) );
			if ( isset( $series[ $d ] ) ) {
				++$series[ $d ]['incomplete'];
			}
		}
		$inc['recovery_rate'] = $inc['captured_30'] ? (int) round( ( $inc['recovered_30'] + $inc['converted_30'] ) / $inc['captured_30'] * 100 ) : 0;
		$inc['counts']        = RAR_WCC_Incomplete::counts();
		$inc['open']          = array_sum( array_intersect_key( $inc['counts'], array_flip( RAR_WCC_Incomplete::open_statuses() ) ) );

		$stats = array(
			'generated' => time(),
			'currency'  => get_woocommerce_currency(),
			'totals'    => $totals,
			'incomplete' => $inc,
			'blocked'   => array(
				'total'  => (int) get_option( 'rar_wcc_block_total', 0 ),
				'recent' => array_slice( (array) get_option( 'rar_wcc_block_log', array() ), 0, 10 ),
			),
			'series'    => array_values( $series ),
		);

		set_transient( 'rar_wcc_stats', $stats, 10 * MINUTE_IN_SECONDS );
		return $stats;
	}
}
