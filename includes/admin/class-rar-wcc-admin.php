<?php
/**
 * Admin: menu, dashboard, settings, incomplete orders, tools and notices.
 *
 * @package RAR_Woo_Cart_Checkout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RAR_WCC_Admin {

	public const CAP       = 'manage_woocommerce';
	public const STAFF_CAP = 'edit_shop_orders';

	private static $hooks = array();

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 60 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
		add_filter( 'plugin_action_links_' . RAR_WCC_BASENAME, array( __CLASS__, 'action_links' ) );
		add_filter( 'set-screen-option', array( __CLASS__, 'set_screen_option' ), 10, 3 );

		add_action( 'admin_post_rar_wcc_save_settings', array( __CLASS__, 'save_settings' ) );
		add_action( 'admin_post_rar_wcc_tool', array( __CLASS__, 'run_tool' ) );
		add_action( 'admin_post_rar_wcc_incomplete_order', array( __CLASS__, 'incomplete_create_order' ) );
		add_action( 'wp_ajax_rar_wcc_incomplete_update', array( __CLASS__, 'ajax_incomplete_update' ) );
	}

	/* ── Menu ────────────────────────────────────────────────────────── */

	public static function menu() {
		$open  = self::open_incomplete_count();
		$badge = $open ? ' <span class="awaiting-mod"><span class="pending-count">' . (int) $open . '</span></span>' : '';

		self::$hooks[] = add_menu_page( __( 'RAR Checkout', 'rar-woo-cart-checkout' ), __( 'RAR Checkout', 'rar-woo-cart-checkout' ) . $badge, self::STAFF_CAP, 'rar-wcc-dashboard', array( __CLASS__, 'page_dashboard' ), 'dashicons-cart', 56 );
		self::$hooks[] = add_submenu_page( 'rar-wcc-dashboard', __( 'Dashboard', 'rar-woo-cart-checkout' ), __( 'Dashboard', 'rar-woo-cart-checkout' ), self::STAFF_CAP, 'rar-wcc-dashboard', array( __CLASS__, 'page_dashboard' ) );
		$inc           = add_submenu_page( 'rar-wcc-dashboard', __( 'Incomplete Orders', 'rar-woo-cart-checkout' ), __( 'Incomplete Orders', 'rar-woo-cart-checkout' ) . $badge, self::STAFF_CAP, 'rar-wcc-incomplete', array( __CLASS__, 'page_incomplete' ) );
		self::$hooks[] = $inc;
		self::$hooks[] = add_submenu_page( 'rar-wcc-dashboard', __( 'Settings', 'rar-woo-cart-checkout' ), __( 'Settings', 'rar-woo-cart-checkout' ), self::CAP, 'rar-wcc', array( __CLASS__, 'page_settings' ) );
		self::$hooks[] = add_submenu_page( 'rar-wcc-dashboard', __( 'Tools & Status', 'rar-woo-cart-checkout' ), __( 'Tools & Status', 'rar-woo-cart-checkout' ), self::CAP, 'rar-wcc-tools', array( __CLASS__, 'page_tools' ) );

		add_action(
			'load-' . $inc,
			static function () {
				self::incomplete_bulk();
				add_screen_option(
					'per_page',
					array(
						'default' => 20,
						'option'  => 'rar_wcc_incomplete_per_page',
					)
				);
			}
		);
	}

	public static function set_screen_option( $status, $option, $value ) {
		return 'rar_wcc_incomplete_per_page' === $option ? min( 200, max( 5, (int) $value ) ) : $status;
	}

	private static function open_incomplete_count() {
		$c = wp_cache_get( 'rar_wcc_open_count' );
		if ( false === $c ) {
			$counts = RAR_WCC_Incomplete::counts();
			$c      = (int) ( $counts['new'] ?? 0 ) + (int) ( $counts['callback'] ?? 0 );
			wp_cache_set( 'rar_wcc_open_count', $c, '', 60 );
		}
		return (int) $c;
	}

	public static function action_links( $links ) {
		array_unshift(
			$links,
			'<a href="' . esc_url( admin_url( 'admin.php?page=rar-wcc-dashboard' ) ) . '">' . esc_html__( 'Dashboard', 'rar-woo-cart-checkout' ) . '</a>',
			'<a href="' . esc_url( admin_url( 'admin.php?page=rar-wcc' ) ) . '">' . esc_html__( 'Settings', 'rar-woo-cart-checkout' ) . '</a>'
		);
		return $links;
	}

	public static function assets( $hook ) {
		$screen   = get_current_screen();
		$is_order = $screen && in_array( $screen->id, array( 'shop_order', 'edit-shop_order', 'woocommerce_page_wc-orders' ), true );
		if ( ! in_array( $hook, self::$hooks, true ) && ! $is_order ) {
			return;
		}
		wp_enqueue_style( 'rar-wcc-admin', RAR_WCC_URL . 'assets/admin/admin.css', array(), RAR_WCC_VERSION );
		if ( in_array( $hook, self::$hooks, true ) ) {
			wp_enqueue_script( 'rar-wcc-admin', RAR_WCC_URL . 'assets/admin/admin.js', array( 'jquery' ), RAR_WCC_VERSION, true );
			wp_localize_script(
				'rar-wcc-admin',
				'RAR_WCC_ADMIN',
				array(
					'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
					'nonce'    => wp_create_nonce( 'rar_wcc_admin' ),
					'i18n'     => array(
						'notePrompt' => __( 'Note for this customer (visible to staff only):', 'rar-woo-cart-checkout' ),
						'addNote'    => __( '+ Add note', 'rar-woo-cart-checkout' ),
						'saved'      => __( 'Saved', 'rar-woo-cart-checkout' ),
						'error'      => __( 'Could not save. Please reload and try again.', 'rar-woo-cart-checkout' ),
						'unsaved'    => __( 'You have unsaved changes.', 'rar-woo-cart-checkout' ),
					),
				)
			);
		}
	}

	/* ── Shared chrome ───────────────────────────────────────────────── */

	private static function header( $active ) {
		$pages = array(
			'rar-wcc-dashboard'  => array( __( 'Dashboard', 'rar-woo-cart-checkout' ), self::STAFF_CAP ),
			'rar-wcc-incomplete' => array( __( 'Incomplete Orders', 'rar-woo-cart-checkout' ), self::STAFF_CAP ),
			'rar-wcc'            => array( __( 'Settings', 'rar-woo-cart-checkout' ), self::CAP ),
			'rar-wcc-tools'      => array( __( 'Tools & Status', 'rar-woo-cart-checkout' ), self::CAP ),
		);
		?>
		<div class="rar-wcc-hero">
			<div class="rar-wcc-hero-brand">
				<span class="rar-wcc-logo dashicons dashicons-cart"></span>
				<div>
					<h1><?php esc_html_e( 'RAR Cart & Checkout', 'rar-woo-cart-checkout' ); ?> <span class="rar-wcc-ver">v<?php echo esc_html( RAR_WCC_VERSION ); ?></span></h1>
					<p><?php esc_html_e( 'Bangladesh-first checkout, Express Buy Now, incomplete-order recovery & order protection.', 'rar-woo-cart-checkout' ); ?></p>
				</div>
			</div>
			<nav class="rar-wcc-nav">
				<?php foreach ( $pages as $slug => $p ) : ?>
					<?php
					if ( ! current_user_can( $p[1] ) ) {
						continue;
					}
					?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $slug ) ); ?>" class="<?php echo $slug === $active ? 'is-active' : ''; ?>"><?php echo esc_html( $p[0] ); ?></a>
				<?php endforeach; ?>
			</nav>
		</div>
		<?php
		self::flash();
	}

	private static function flash() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$msg = sanitize_key( wp_unslash( $_GET['rar_msg'] ?? '' ) );
		if ( ! $msg ) {
			return;
		}
		$messages = array(
			'saved'      => array( 'success', __( 'Settings saved.', 'rar-woo-cart-checkout' ) ),
			'imported'   => array( 'success', __( 'Settings imported.', 'rar-woo-cart-checkout' ) ),
			'import_bad' => array( 'error', __( 'Import failed: the text is not a valid settings export.', 'rar-woo-cart-checkout' ) ),
			'reset'      => array( 'success', __( 'Settings reset to defaults.', 'rar-woo-cart-checkout' ) ),
			'bangla'     => array( 'success', __( 'Bangla labels applied. Review them under Settings.', 'rar-woo-cart-checkout' ) ),
			'english'    => array( 'success', __( 'English labels restored.', 'rar-woo-cart-checkout' ) ),
			'classic'    => array( 'success', __( 'Cart & Checkout pages switched to the Classic shortcode. A backup of the block content was kept.', 'rar-woo-cart-checkout' ) ),
			'restored'   => array( 'success', __( 'Original block Cart & Checkout content restored.', 'rar-woo-cart-checkout' ) ),
			'cache'      => array( 'success', __( 'Caches cleared.', 'rar-woo-cart-checkout' ) ),
			'cleanup'    => array( 'success', __( 'Maintenance finished.', 'rar-woo-cart-checkout' ) ),
			'bulk'       => array( 'success', __( 'Records updated.', 'rar-woo-cart-checkout' ) ),
			'deleted'    => array( 'success', __( 'Records deleted.', 'rar-woo-cart-checkout' ) ),
			'order_err'  => array( 'error', sanitize_text_field( wp_unslash( $_GET['rar_err'] ?? __( 'Could not create the order.', 'rar-woo-cart-checkout' ) ) ) ),
		);
		// phpcs:enable
		if ( isset( $messages[ $msg ] ) ) {
			printf( '<div class="notice notice-%1$s is-dismissible rar-wcc-notice"><p>%2$s</p></div>', esc_attr( $messages[ $msg ][0] ), esc_html( $messages[ $msg ][1] ) );
		}
	}

	private static function redirect( $page, $args = array() ) {
		wp_safe_redirect( add_query_arg( array_merge( array( 'page' => $page ), $args ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/* ── Dashboard ───────────────────────────────────────────────────── */

	public static function page_dashboard() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$stats = RAR_WCC_Stats::get( ! empty( $_GET['refresh'] ) && current_user_can( self::STAFF_CAP ) );
		$t     = $stats['totals'];
		$inc   = $stats['incomplete'];
		$cur   = array( 'currency' => $stats['currency'] );
		$share = $t['orders_30'] ? round( $t['express_30'] / $t['orders_30'] * 100 ) : 0;
		?>
		<div class="wrap rar-wcc-wrap">
			<?php self::header( 'rar-wcc-dashboard' ); ?>
			<h2 class="screen-reader-text"><?php esc_html_e( 'Dashboard', 'rar-woo-cart-checkout' ); ?></h2>

			<div class="rar-wcc-cards">
				<?php
				self::card( 'dashicons-chart-bar', __( 'Orders today', 'rar-woo-cart-checkout' ), $t['orders_today'], wc_price( $t['revenue_today'], $cur ), 'blue' );
				self::card( 'dashicons-calendar-alt', __( 'Orders · 30 days', 'rar-woo-cart-checkout' ), $t['orders_30'], wc_price( $t['revenue_30'], $cur ), 'indigo' );
				/* translators: %s: percent */
				self::card( 'dashicons-performance', __( 'Express Buy Now · 30 days', 'rar-woo-cart-checkout' ), $t['express_30'], wc_price( $t['express_rev_30'], $cur ) . ' · ' . sprintf( __( '%s%% of orders', 'rar-woo-cart-checkout' ), $share ), 'green' );
				self::card( 'dashicons-phone', __( 'Needs follow-up', 'rar-woo-cart-checkout' ), $inc['open'], wc_price( $inc['open_value'], $cur ) . ' ' . __( 'in open carts', 'rar-woo-cart-checkout' ), 'orange', admin_url( 'admin.php?page=rar-wcc-incomplete&status=open' ) );
				/* translators: 1: captured count */
				self::card( 'dashicons-backup', __( 'Recovery rate · 30 days', 'rar-woo-cart-checkout' ), $inc['recovery_rate'] . '%', sprintf( __( '%1$d captured · %2$d recovered · %3$d self-ordered', 'rar-woo-cart-checkout' ), $inc['captured_30'], $inc['recovered_30'], $inc['converted_30'] ), 'teal' );
				self::card( 'dashicons-undo', __( 'Recovered orders · 30 days', 'rar-woo-cart-checkout' ), $t['recovered_30'], wc_price( $t['recovered_rev_30'], $cur ), 'purple' );
				self::card( 'dashicons-shield', __( 'Blocked attempts (all time)', 'rar-woo-cart-checkout' ), $stats['blocked']['total'], __( 'Blocklist, cooldown & COD rules', 'rar-woo-cart-checkout' ), 'red', admin_url( 'admin.php?page=rar-wcc&tab=guard' ) );
				?>
			</div>

			<div class="rar-wcc-grid">
				<section class="rar-wcc-panel rar-wcc-span-2">
					<header><h2><?php esc_html_e( 'Last 14 days', 'rar-woo-cart-checkout' ); ?></h2>
						<a class="button button-small" href="<?php echo esc_url( add_query_arg( 'refresh', 1 ) ); ?>">↻ <?php esc_html_e( 'Refresh', 'rar-woo-cart-checkout' ); ?></a></header>
					<?php self::chart( $stats['series'] ); ?>
				</section>

				<section class="rar-wcc-panel">
					<header><h2><?php esc_html_e( 'Call these customers', 'rar-woo-cart-checkout' ); ?></h2>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=rar-wcc-incomplete&status=open' ) ); ?>"><?php esc_html_e( 'View all', 'rar-woo-cart-checkout' ); ?> →</a></header>
					<?php
					$leads = RAR_WCC_Incomplete::query(
						array(
							'status'   => 'open',
							'per_page' => 6,
						)
					)['items'];
					if ( ! $leads ) {
						echo '<p class="rar-wcc-empty">🎉 ' . esc_html__( 'No pending follow-ups.', 'rar-woo-cart-checkout' ) . '</p>';
					}
					?>
					<ul class="rar-wcc-leads">
						<?php foreach ( $leads as $l ) : ?>
							<li>
								<div><strong><?php echo esc_html( $l['name'] ? $l['name'] : $l['phone'] ); ?></strong>
									<small><?php echo esc_html( $l['phone'] . ' · ' . human_time_diff( strtotime( $l['updated_at'] . ' UTC' ) ) ); ?></small>
									<small><?php echo wp_kses_post( wc_price( $l['cart_total'], array( 'currency' => $l['currency'] ) ) ); ?> · <?php echo esc_html( count( $l['cart'] ) ); ?> <?php esc_html_e( 'item(s)', 'rar-woo-cart-checkout' ); ?></small></div>
								<div class="rar-wcc-lead-actions">
									<a class="button button-small" href="tel:<?php echo esc_attr( $l['phone'] ); ?>">📞</a>
									<?php $wa = RAR_WCC_Incomplete::whatsapp_url( $l ); ?>
									<?php if ( $wa ) : ?>
										<a class="button button-small" target="_blank" rel="noopener" href="<?php echo esc_url( $wa ); ?>">💬</a>
									<?php endif; ?>
								</div>
							</li>
						<?php endforeach; ?>
					</ul>
				</section>

				<section class="rar-wcc-panel">
					<header><h2><?php esc_html_e( 'Health check', 'rar-woo-cart-checkout' ); ?></h2>
						<?php if ( current_user_can( self::CAP ) ) : ?>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=rar-wcc-tools' ) ); ?>"><?php esc_html_e( 'Tools', 'rar-woo-cart-checkout' ); ?> →</a>
						<?php endif; ?></header>
					<?php self::health_list( true ); ?>
				</section>

				<section class="rar-wcc-panel">
					<header><h2><?php esc_html_e( 'Recently blocked', 'rar-woo-cart-checkout' ); ?></h2></header>
					<?php if ( ! $stats['blocked']['recent'] ) : ?>
						<p class="rar-wcc-empty"><?php esc_html_e( 'Nothing blocked yet.', 'rar-woo-cart-checkout' ); ?></p>
					<?php else : ?>
						<ul class="rar-wcc-simple-list">
							<?php
							$reasons = array(
								'blocklist'   => __( 'Blocklist', 'rar-woo-cart-checkout' ),
								'cooldown'    => __( 'Duplicate within cooldown', 'rar-woo-cart-checkout' ),
								'max_cod'     => __( 'Above COD limit', 'rar-woo-cart-checkout' ),
								'low_success' => __( 'Low success rate', 'rar-woo-cart-checkout' ),
							);
							foreach ( $stats['blocked']['recent'] as $b ) :
								?>
								<li><strong><?php echo esc_html( $b['phone'] ); ?></strong> — <?php echo esc_html( $reasons[ $b['reason'] ] ?? $b['reason'] ); ?> <small><?php echo esc_html( human_time_diff( $b['time'] ) ); ?></small></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</section>
			</div>
			<p class="rar-wcc-foot"><?php
				/* translators: %s: time */
				printf( esc_html__( 'Figures cached for 10 minutes · generated %s ago · excludes failed and cancelled orders.', 'rar-woo-cart-checkout' ), esc_html( human_time_diff( $stats['generated'] ) ) );
			?></p>
		</div>
		<?php
	}

	private static function card( $icon, $label, $value, $sub, $color, $link = '' ) {
		$tag = $link ? 'a' : 'div';
		printf(
			'<%1$s class="rar-wcc-card is-%2$s"%3$s><span class="dashicons %4$s"></span><div><span class="rar-wcc-card-label">%5$s</span><strong>%6$s</strong><small>%7$s</small></div></%1$s>',
			$tag, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			esc_attr( $color ),
			$link ? ' href="' . esc_url( $link ) . '"' : '', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			esc_attr( $icon ),
			esc_html( $label ),
			esc_html( (string) $value ),
			wp_kses_post( $sub )
		);
	}

	/**
	 * Dependency-free SVG bar chart.
	 */
	private static function chart( $series ) {
		$max = 1;
		foreach ( $series as $d ) {
			$max = max( $max, $d['orders'], $d['incomplete'] );
		}
		$max = (int) ( ceil( max( 4, $max ) / 4 ) * 4 ); // Clean gridline labels.
		$w   = 720;
		$h   = 220;
		$pad = 28;
		$n   = count( $series );
		$bw  = ( $w - $pad * 2 ) / max( 1, $n );
		?>
		<div class="rar-wcc-chart">
			<svg viewBox="0 0 <?php echo (int) $w; ?> <?php echo (int) ( $h + 30 ); ?>" role="img" aria-label="<?php esc_attr_e( 'Orders and incomplete checkouts per day', 'rar-woo-cart-checkout' ); ?>">
				<?php for ( $g = 0; $g <= 4; $g++ ) : ?>
					<?php $y = $pad + ( $h - $pad ) * $g / 4; ?>
					<line x1="<?php echo (int) $pad; ?>" x2="<?php echo (int) ( $w - $pad ); ?>" y1="<?php echo esc_attr( $y ); ?>" y2="<?php echo esc_attr( $y ); ?>" class="grid"/>
					<text x="<?php echo (int) ( $pad - 6 ); ?>" y="<?php echo esc_attr( $y + 4 ); ?>" class="axis" text-anchor="end"><?php echo esc_html( (string) round( $max * ( 4 - $g ) / 4 ) ); ?></text>
				<?php endfor; ?>
				<?php
				$points = array();
				foreach ( $series as $i => $d ) :
					$x      = $pad + $i * $bw;
					$scale  = ( $h - $pad ) / $max;
					$oh     = $d['orders'] * $scale;
					$eh     = $d['express'] * $scale;
					$rh     = $d['recovered'] * $scale;
					$cx     = $x + $bw / 2;
					$points[] = round( $cx, 1 ) . ',' . round( $h - $d['incomplete'] * $scale, 1 );
					$tip    = sprintf( '%s — %d orders (%d express, %d recovered) · %d incomplete', wp_date( 'D, d M', strtotime( $d['date'] ) ), $d['orders'], $d['express'], $d['recovered'], $d['incomplete'] );
					?>
					<g class="bar"><title><?php echo esc_html( $tip ); ?></title>
						<rect x="<?php echo esc_attr( $x + $bw * 0.18 ); ?>" y="<?php echo esc_attr( $h - $oh ); ?>" width="<?php echo esc_attr( $bw * 0.64 ); ?>" height="<?php echo esc_attr( max( 0, $oh ) ); ?>" rx="3" class="orders"/>
						<rect x="<?php echo esc_attr( $x + $bw * 0.18 ); ?>" y="<?php echo esc_attr( $h - $eh - $rh ); ?>" width="<?php echo esc_attr( $bw * 0.64 ); ?>" height="<?php echo esc_attr( max( 0, $eh ) ); ?>" rx="3" class="express"/>
						<rect x="<?php echo esc_attr( $x + $bw * 0.18 ); ?>" y="<?php echo esc_attr( $h - $rh ); ?>" width="<?php echo esc_attr( $bw * 0.64 ); ?>" height="<?php echo esc_attr( max( 0, $rh ) ); ?>" rx="3" class="recovered"/>
						<text x="<?php echo esc_attr( $cx ); ?>" y="<?php echo (int) ( $h + 18 ); ?>" class="axis" text-anchor="middle"><?php echo esc_html( wp_date( 'd', strtotime( $d['date'] ) ) ); ?></text>
					</g>
				<?php endforeach; ?>
				<polyline points="<?php echo esc_attr( implode( ' ', $points ) ); ?>" class="line"/>
			</svg>
			<div class="rar-wcc-legend">
				<span class="orders"><?php esc_html_e( 'Orders', 'rar-woo-cart-checkout' ); ?></span>
				<span class="express"><?php esc_html_e( 'Express Buy Now', 'rar-woo-cart-checkout' ); ?></span>
				<span class="recovered"><?php esc_html_e( 'Recovered', 'rar-woo-cart-checkout' ); ?></span>
				<span class="line"><?php esc_html_e( 'Incomplete checkouts', 'rar-woo-cart-checkout' ); ?></span>
			</div>
		</div>
		<?php
	}

	/* ── Health checks ───────────────────────────────────────────────── */

	private static function block_pages() {
		$out = array();
		foreach ( array( 'cart' => 'woocommerce/cart', 'checkout' => 'woocommerce/checkout' ) as $page => $block ) {
			$id = wc_get_page_id( $page );
			if ( $id > 0 ) {
				$post = get_post( $id );
				if ( $post && has_block( $block, $post ) ) {
					$out[ $page ] = $id;
				}
			}
		}
		return $out;
	}

	private static function has_block_backup() {
		foreach ( array( 'cart', 'checkout' ) as $page ) {
			$id = wc_get_page_id( $page );
			if ( $id > 0 && get_post_meta( $id, '_rar_wcc_block_backup', true ) ) {
				return true;
			}
		}
		return false;
	}

	private static function duplicate_snippets() {
		if ( ! post_type_exists( 'wpcode' ) ) {
			return array();
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (array) $wpdb->get_results(
			"SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type = 'wpcode' AND post_status = 'publish'
			AND ( post_content LIKE '%nabiadExpressBuyNow%' OR post_content LIKE '%calc_shipping_city%' OR post_content LIKE '%wd-buy-now-btn%' OR post_content LIKE '%bd-cities%' ) LIMIT 10"
		);
	}

	public static function health() {
		$checks = array();

		$blocks   = self::block_pages();
		$checks[] = array(
			$blocks ? 'bad' : 'ok',
			$blocks ? __( 'Cart/Checkout uses WooCommerce Blocks — this plugin needs the Classic cart & checkout.', 'rar-woo-cart-checkout' ) : __( 'Classic Cart & Checkout in use.', 'rar-woo-cart-checkout' ),
			$blocks ? array( 'switch_classic', __( 'Switch to Classic (reversible)', 'rar-woo-cart-checkout' ) ) : ( self::has_block_backup() ? array( 'restore_blocks', __( 'Restore block version', 'rar-woo-cart-checkout' ) ) : null ),
		);

		$snips = self::duplicate_snippets();
		if ( $snips ) {
			$titles   = implode( ', ', wp_list_pluck( $snips, 'post_title' ) );
			$checks[] = array(
				'warn',
				/* translators: %s: snippet titles */
				sprintf( __( 'Active WPCode snippet(s) may duplicate this plugin: %s. Deactivate them to avoid double modals or fields.', 'rar-woo-cart-checkout' ), $titles ),
				array( 'link', __( 'Open WPCode', 'rar-woo-cart-checkout' ), admin_url( 'admin.php?page=wpcode' ) ),
			);
		}

		$gateways = WC()->payment_gateways() ? WC()->payment_gateways()->get_available_payment_gateways() : array();
		$checks[] = array(
			isset( $gateways['cod'] ) ? 'ok' : 'warn',
			isset( $gateways['cod'] ) ? __( 'Cash on Delivery is enabled.', 'rar-woo-cart-checkout' ) : __( 'Cash on Delivery is not enabled.', 'rar-woo-cart-checkout' ),
			isset( $gateways['cod'] ) ? null : array( 'link', __( 'Payments', 'rar-woo-cart-checkout' ), admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ),
		);

		$theme    = wp_get_theme();
		$template = strtolower( (string) $theme->get_template() );
		$selector = RAR_WCC_Settings::get( 'express_button_selector' );
		if ( RAR_WCC_Settings::yes( 'express_enabled' ) ) {
			$ok       = 'woodmart' === $template || RAR_WCC_Settings::yes( 'express_add_button' ) || '.wd-buy-now-btn' !== $selector;
			$checks[] = array(
				$ok ? 'ok' : 'warn',
				$ok
					/* translators: %s: theme name */
					? sprintf( __( 'Express Buy Now active (theme: %s).', 'rar-woo-cart-checkout' ), $theme->get( 'Name' ) )
					/* translators: %s: theme name */
					: sprintf( __( 'Theme “%s” is not Woodmart — enable “Add our own Buy Now button” or set your theme’s selector.', 'rar-woo-cart-checkout' ), $theme->get( 'Name' ) ),
				$ok ? null : array( 'link', __( 'Express settings', 'rar-woo-cart-checkout' ), admin_url( 'admin.php?page=rar-wcc&tab=express' ) ),
			);
		}

		if ( RAR_WCC_Settings::yes( 'fs_enabled' ) ) {
			$manual   = RAR_WCC_Settings::num( 'fs_amount' );
			$detected = $manual > 0 ? $manual : self::detect_free_shipping();
			$checks[] = array(
				$detected > 0 ? 'ok' : 'info',
				$detected > 0
					/* translators: %s: amount */
					? sprintf( __( 'Free-delivery progress bar threshold: %s.', 'rar-woo-cart-checkout' ), wp_strip_all_tags( wc_price( $detected ) ) )
					: __( 'Free-delivery bar is hidden: no “Free shipping” method with a minimum amount and no manual threshold.', 'rar-woo-cart-checkout' ),
				$detected > 0 ? null : array( 'link', __( 'Set threshold', 'rar-woo-cart-checkout' ), admin_url( 'admin.php?page=rar-wcc&tab=checkout' ) ),
			);
		}

		$checks[] = array(
			'ok',
			/* translators: 1: districts, 2: areas */
			sprintf( __( 'Location data: %1$d districts, %2$d towns/areas.', 'rar-woo-cart-checkout' ), count( RAR_WCC_Locations::map() ), RAR_WCC_Locations::count_areas() ),
			null,
		);

		$hpos     = class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
		$checks[] = array( 'ok', $hpos ? __( 'HPOS (High-Performance Order Storage) active and supported.', 'rar-woo-cart-checkout' ) : __( 'Legacy order storage in use (supported).', 'rar-woo-cart-checkout' ), null );

		$next     = wp_next_scheduled( RAR_WCC_Install::CRON_HOOK );
		$checks[] = array(
			$next ? 'ok' : 'warn',
			$next
				/* translators: %s: time */
				? sprintf( __( 'Daily cleanup scheduled (next run in %s).', 'rar-woo-cart-checkout' ), human_time_diff( $next ) )
				: __( 'Daily cleanup is not scheduled.', 'rar-woo-cart-checkout' ),
			$next ? null : array( 'cleanup', __( 'Run now', 'rar-woo-cart-checkout' ) ),
		);

		return $checks;
	}

	private static function detect_free_shipping() {
		$min = 0;
		$zones = WC_Shipping_Zones::get_zones();
		$zones[] = array( 'zone_id' => 0 );
		foreach ( $zones as $z ) {
			$zone = WC_Shipping_Zones::get_zone( $z['zone_id'] ?? $z['id'] ?? 0 );
			if ( ! $zone ) {
				continue;
			}
			foreach ( $zone->get_shipping_methods( true ) as $m ) {
				if ( 'free_shipping' === $m->id && in_array( $m->get_option( 'requires' ), array( 'min_amount', 'either' ), true ) ) {
					$a = (float) $m->get_option( 'min_amount' );
					if ( $a > 0 && ( ! $min || $a < $min ) ) {
						$min = $a;
					}
				}
			}
		}
		return $min;
	}

	private static function health_list( $compact = false ) {
		$icons = array( 'ok' => '✅', 'warn' => '⚠️', 'bad' => '⛔', 'info' => 'ℹ️' );
		echo '<ul class="rar-wcc-health">';
		foreach ( self::health() as $c ) {
			echo '<li class="is-' . esc_attr( $c[0] ) . '"><span class="ico">' . esc_html( $icons[ $c[0] ] ) . '</span><span class="txt">' . esc_html( $c[1] ) . '</span>';
			if ( $c[2] && current_user_can( self::CAP ) ) {
				if ( 'link' === $c[2][0] ) {
					echo ' <a class="button button-small" href="' . esc_url( $c[2][2] ) . '">' . esc_html( $c[2][1] ) . '</a>';
				} else {
					$confirm = 'switch_classic' === $c[2][0] ? __( 'Replace the block Cart & Checkout with the Classic shortcode? The block version is backed up and can be restored.', 'rar-woo-cart-checkout' ) : '';
					echo ' ' . self::tool_button( $c[2][0], $c[2][1], $confirm, 'button-small button-primary' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
			}
			echo '</li>';
		}
		echo '</ul>';
	}

	public static function notices() {
		if ( ! current_user_can( self::CAP ) || ! RAR_WCC_Settings::yes( 'block_notice' ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( $screen && false !== strpos( (string) $screen->id, 'rar-wcc' ) ) {
			return;
		}
		if ( ! self::block_pages() ) {
			return;
		}
		echo '<div class="notice notice-warning"><p><strong>RAR Cart &amp; Checkout:</strong> ' . esc_html__( 'your Cart/Checkout page uses WooCommerce Blocks, so Bangladesh fields, Express Buy Now and incomplete-order capture will not run there.', 'rar-woo-cart-checkout' ) . ' <a href="' . esc_url( admin_url( 'admin.php?page=rar-wcc-dashboard' ) ) . '">' . esc_html__( 'Fix it in one click →', 'rar-woo-cart-checkout' ) . '</a></p></div>';
	}

	/* ── Settings ────────────────────────────────────────────────────── */

	public static function page_settings() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		$tabs   = RAR_WCC_Settings::tabs();
		$active = sanitize_key( wp_unslash( $_GET['tab'] ?? 'address' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$active = isset( $tabs[ $active ] ) ? $active : 'address';
		$groups = array();
		foreach ( RAR_WCC_Settings::schema() as $key => $f ) {
			$groups[ $f['tab'] ][ $f['group'] ][ $key ] = $f;
		}
		?>
		<div class="wrap rar-wcc-wrap">
			<?php self::header( 'rar-wcc' ); ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="rar-wcc-settings" id="rar-wcc-settings">
				<input type="hidden" name="action" value="rar_wcc_save_settings">
				<input type="hidden" name="tab" value="<?php echo esc_attr( $active ); ?>" id="rar-wcc-tab-input">
				<?php wp_nonce_field( 'rar_wcc_save_settings' ); ?>

				<div class="rar-wcc-tabs" role="tablist">
					<?php foreach ( $tabs as $slug => $tab ) : ?>
						<button type="button" role="tab" class="rar-wcc-tab <?php echo $slug === $active ? 'is-active' : ''; ?>" data-tab="<?php echo esc_attr( $slug ); ?>" aria-selected="<?php echo $slug === $active ? 'true' : 'false'; ?>">
							<span class="dashicons <?php echo esc_attr( $tab[1] ); ?>"></span><?php echo esc_html( $tab[0] ); ?>
						</button>
					<?php endforeach; ?>
				</div>

				<?php foreach ( $tabs as $slug => $tab ) : ?>
					<div class="rar-wcc-tabpanel" data-panel="<?php echo esc_attr( $slug ); ?>" <?php echo $slug === $active ? '' : 'hidden'; ?>>
						<?php foreach ( $groups[ $slug ] ?? array() as $group => $fields ) : ?>
							<section class="rar-wcc-panel">
								<header><h2><?php echo esc_html( $group ); ?></h2></header>
								<div class="rar-wcc-fields">
									<?php
									foreach ( $fields as $key => $f ) {
										self::render_field( $key, $f );
									}
									?>
								</div>
							</section>
						<?php endforeach; ?>
						<?php if ( 'incomplete' === $slug ) : ?>
							<p class="rar-wcc-privacy">🔒 <?php esc_html_e( 'Privacy: data is captured only after a valid phone number is typed on checkout, stays on your server, is never shared, and is auto-deleted after the retention period. Consider mentioning order-follow-up calls in your privacy policy.', 'rar-woo-cart-checkout' ); ?></p>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>

				<div class="rar-wcc-savebar">
					<span class="rar-wcc-dirty" hidden><?php esc_html_e( 'Unsaved changes', 'rar-woo-cart-checkout' ); ?></span>
					<?php submit_button( __( 'Save Settings', 'rar-woo-cart-checkout' ), 'primary large', 'submit', false ); ?>
				</div>
			</form>
		</div>
		<?php
	}

	private static function render_field( $key, $f ) {
		$id    = 'rar-wcc-' . $key;
		$name  = 'rar_wcc[' . $key . ']';
		$value = RAR_WCC_Settings::get( $key );
		echo '<div class="rar-wcc-field type-' . esc_attr( $f['type'] ) . '">';

		if ( 'toggle' === $f['type'] ) {
			echo '<label class="rar-wcc-switch" for="' . esc_attr( $id ) . '"><input type="hidden" name="' . esc_attr( $name ) . '" value="no"><input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="yes" ' . checked( 'yes', $value, false ) . '><span class="slider" aria-hidden="true"></span><span class="lbl">' . esc_html( $f['label'] ) . '</span></label>';
		} else {
			echo '<label class="rar-wcc-label" for="' . esc_attr( $id ) . '">' . esc_html( $f['label'] ) . '</label>';
			switch ( $f['type'] ) {
				case 'textarea':
					echo '<textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" rows="4" class="large-text">' . esc_textarea( $value ) . '</textarea>';
					break;
				case 'select':
					echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">';
					foreach ( (array) $f['options'] as $ok => $ol ) {
						echo '<option value="' . esc_attr( $ok ) . '" ' . selected( $value, $ok, false ) . '>' . esc_html( $ol ) . '</option>';
					}
					echo '</select>';
					break;
				case 'number':
					echo '<input type="number" step="any" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '"' . ( null !== $f['min'] ? ' min="' . esc_attr( $f['min'] ) . '"' : '' ) . ( null !== $f['max'] ? ' max="' . esc_attr( $f['max'] ) . '"' : '' ) . ' class="small-text">';
					break;
				case 'color':
					echo '<input type="color" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '"> <code class="rar-wcc-color-code">' . esc_html( $value ) . '</code>';
					break;
				default:
					echo '<input type="text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" class="regular-text" placeholder="' . esc_attr( $f['default'] ) . '">';
			}
		}
		if ( $f['desc'] ) {
			echo '<p class="description">' . esc_html( $f['desc'] ) . '</p>';
		}
		echo '</div>';
	}

	public static function save_settings() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to manage these settings.', 'rar-woo-cart-checkout' ) );
		}
		check_admin_referer( 'rar_wcc_save_settings' );
		$raw = isset( $_POST['rar_wcc'] ) && is_array( $_POST['rar_wcc'] ) ? wp_unslash( $_POST['rar_wcc'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised by schema.
		RAR_WCC_Settings::save( RAR_WCC_Settings::sanitize( $raw, true ) );
		delete_transient( 'rar_wcc_stats' );
		self::redirect(
			'rar-wcc',
			array(
				'tab'     => sanitize_key( wp_unslash( $_POST['tab'] ?? 'address' ) ),
				'rar_msg' => 'saved',
			)
		);
	}

	/* ── Incomplete orders ───────────────────────────────────────────── */

	public static function page_incomplete() {
		if ( ! current_user_can( self::STAFF_CAP ) ) {
			return;
		}
		$table = new RAR_WCC_Incomplete_Table();
		$table->prepare_items();
		?>
		<div class="wrap rar-wcc-wrap">
			<?php self::header( 'rar-wcc-incomplete' ); ?>
			<?php if ( ! RAR_WCC_Settings::yes( 'incomplete_enabled' ) ) : ?>
				<div class="notice notice-warning inline"><p><?php esc_html_e( 'Capture is turned off. Existing records are shown, but new ones are not collected.', 'rar-woo-cart-checkout' ); ?> <a href="<?php echo esc_url( admin_url( 'admin.php?page=rar-wcc&tab=incomplete' ) ); ?>"><?php esc_html_e( 'Turn on', 'rar-woo-cart-checkout' ); ?></a></p></div>
			<?php endif; ?>
			<p class="rar-wcc-intro"><?php esc_html_e( 'Shoppers who entered a phone number on checkout but did not order. Call or WhatsApp them, set a status, add notes, or create the order for them in one click. Records close automatically when the same customer orders.', 'rar-woo-cart-checkout' ); ?></p>
			<?php $table->views(); ?>
			<form method="get">
				<input type="hidden" name="page" value="rar-wcc-incomplete">
				<?php
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				if ( ! empty( $_GET['status'] ) ) {
					echo '<input type="hidden" name="status" value="' . esc_attr( sanitize_key( wp_unslash( $_GET['status'] ) ) ) . '">'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				}
				$table->search_box( __( 'Search name, phone, area', 'rar-woo-cart-checkout' ), 'rar-wcc-search' );
				?>
			</form>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=rar-wcc-incomplete' ) ); ?>">
				<input type="hidden" name="rar_wcc_bulk" value="1">
				<?php $table->display(); ?>
			</form>
		</div>
		<?php
	}

	public static function ajax_incomplete_update() {
		check_ajax_referer( 'rar_wcc_admin', 'nonce' );
		if ( ! current_user_can( self::STAFF_CAP ) ) {
			wp_send_json_error( null, 403 );
		}
		$fields = array();
		if ( isset( $_POST['status'] ) ) {
			$fields['status'] = sanitize_key( wp_unslash( $_POST['status'] ) );
		}
		if ( isset( $_POST['admin_note'] ) ) {
			$fields['admin_note'] = sanitize_textarea_field( wp_unslash( $_POST['admin_note'] ) );
		}
		$row = RAR_WCC_Incomplete::update( absint( $_POST['id'] ?? 0 ), $fields );
		if ( is_wp_error( $row ) ) {
			wp_send_json_error( array( 'message' => $row->get_error_message() ) );
		}
		wp_send_json_success( array( 'status' => $row['status'] ) );
	}

	public static function incomplete_create_order() {
		$id = absint( $_GET['id'] ?? 0 );
		if ( ! current_user_can( self::STAFF_CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'rar-woo-cart-checkout' ) );
		}
		check_admin_referer( 'rar_wcc_incomplete_order_' . $id );
		$order = RAR_WCC_Incomplete::create_order( $id );
		if ( is_wp_error( $order ) ) {
			self::redirect(
				'rar-wcc-incomplete',
				array(
					'rar_msg' => 'order_err',
					'rar_err' => rawurlencode( $order->get_error_message() ),
				)
			);
		}
		wp_safe_redirect( $order->get_edit_order_url() );
		exit;
	}

	/**
	 * Bulk actions — processed on the page's load hook, before output.
	 */
	public static function incomplete_bulk() {
		if ( empty( $_POST['rar_wcc_bulk'] ) ) {
			return;
		}
		if ( ! current_user_can( self::STAFF_CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'rar-woo-cart-checkout' ) );
		}
		check_admin_referer( 'bulk-incompletes' ); // Nonce printed by WP_List_Table.
		$top    = sanitize_key( wp_unslash( $_POST['action'] ?? '-1' ) );
		$bottom = sanitize_key( wp_unslash( $_POST['action2'] ?? '-1' ) );
		$action = ( $top && '-1' !== $top ) ? $top : $bottom;
		$ids = array_map( 'absint', (array) wp_unslash( $_POST['ids'] ?? array() ) );

		if ( 'export' === $action ) {
			self::export_csv( $ids );
		}
		if ( ! $ids ) {
			self::redirect( 'rar-wcc-incomplete' );
		}
		if ( 'delete' === $action && current_user_can( self::CAP ) ) {
			RAR_WCC_Incomplete::delete( $ids );
			self::redirect( 'rar-wcc-incomplete', array( 'rar_msg' => 'deleted' ) );
		}
		if ( str_starts_with( $action, 'mark_' ) ) {
			$status = substr( $action, 5 );
			foreach ( $ids as $id ) {
				RAR_WCC_Incomplete::update( $id, array( 'status' => $status ) );
			}
		}
		self::redirect( 'rar-wcc-incomplete', array( 'rar_msg' => 'bulk' ) );
	}

	private static function export_csv( $ids ) {
		$rows = array();
		if ( $ids ) {
			foreach ( $ids as $id ) {
				$r = RAR_WCC_Incomplete::get( $id );
				if ( $r ) {
					$rows[] = $r;
				}
			}
		} else {
			$rows = RAR_WCC_Incomplete::query( array( 'per_page' => 500 ) )['items'];
		}
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=incomplete-orders-' . gmdate( 'Y-m-d' ) . '.csv' );
		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- UTF-8 BOM for Excel (Bangla text).
		fputcsv( $out, array( 'ID', 'Date', 'Status', 'Name', 'Phone', 'Email', 'District', 'Town/City', 'Address', 'Products', 'Total', 'Source', 'Order', 'Staff note' ) );
		foreach ( $rows as $r ) {
			$items = array();
			foreach ( $r['cart'] as $c ) {
				$items[] = $c['name'] . ' x' . $c['qty'];
			}
			fputcsv(
				$out,
				array_map(
					static function ( $v ) {
						$v = (string) $v;
						return preg_match( '/^[=+\-@]/', $v ) ? "'" . $v : $v; // CSV-injection guard.
					},
					array( $r['id'], $r['created_at'], $r['status'], $r['name'], $r['phone'], $r['email'], $r['district'], $r['city'], $r['address'], implode( '; ', $items ), $r['cart_total'], $r['source'], $r['order_id'] ? $r['order_id'] : '', $r['admin_note'] )
				)
			);
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	/* ── Tools ───────────────────────────────────────────────────────── */

	private static function tool_button( $tool, $label, $confirm = '', $class = 'button' ) {
		$url = wp_nonce_url( admin_url( 'admin-post.php?action=rar_wcc_tool&tool=' . $tool ), 'rar_wcc_tool_' . $tool );
		return '<a class="button ' . esc_attr( $class ) . '" href="' . esc_url( $url ) . '"' . ( $confirm ? ' onclick="return confirm(\'' . esc_js( $confirm ) . '\');"' : '' ) . '>' . esc_html( $label ) . '</a>';
	}

	public static function page_tools() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		$export = wp_json_encode(
			array(
				'plugin'   => 'rar-woo-cart-checkout',
				'version'  => RAR_WCC_VERSION,
				'settings' => RAR_WCC_Settings::all(),
			),
			JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
		);
		?>
		<div class="wrap rar-wcc-wrap">
			<?php self::header( 'rar-wcc-tools' ); ?>
			<div class="rar-wcc-grid">
				<section class="rar-wcc-panel rar-wcc-span-2">
					<header><h2><?php esc_html_e( 'System status', 'rar-woo-cart-checkout' ); ?></h2></header>
					<?php self::health_list(); ?>
					<table class="widefat striped rar-wcc-sysinfo">
						<tbody>
							<tr><th><?php esc_html_e( 'Plugin', 'rar-woo-cart-checkout' ); ?></th><td><?php echo esc_html( RAR_WCC_VERSION ); ?> (DB <?php echo esc_html( (string) get_option( 'rar_wcc_db_version' ) ); ?>)</td></tr>
							<tr><th>WooCommerce</th><td><?php echo esc_html( WC()->version ); ?></td></tr>
							<tr><th>WordPress / PHP</th><td><?php echo esc_html( get_bloginfo( 'version' ) . ' / ' . PHP_VERSION ); ?></td></tr>
							<tr><th><?php esc_html_e( 'Theme', 'rar-woo-cart-checkout' ); ?></th><td><?php echo esc_html( wp_get_theme()->get( 'Name' ) . ' ' . wp_get_theme()->get( 'Version' ) ); ?></td></tr>
							<tr><th><?php esc_html_e( 'Checkout URL (Express)', 'rar-woo-cart-checkout' ); ?></th><td><code><?php echo esc_html( RAR_WCC_Express::checkout_url() ); ?></code></td></tr>
							<tr><th>REST API</th><td><code><?php echo esc_html( rest_url( RAR_WCC_Rest::NS ) ); ?></code> — /stats · /incomplete · /incomplete/{id} · /incomplete/{id}/order · /customer?phone= · /customer/block</td></tr>
						</tbody>
					</table>
				</section>

				<section class="rar-wcc-panel">
					<header><h2><?php esc_html_e( 'Labels', 'rar-woo-cart-checkout' ); ?></h2></header>
					<p><?php esc_html_e( 'Switch every customer-facing text to Bangla in one click, or back to English.', 'rar-woo-cart-checkout' ); ?></p>
					<p>
						<?php echo self::tool_button( 'bangla', 'বাংলা লেবেল প্রয়োগ করুন', __( 'Replace current labels with Bangla?', 'rar-woo-cart-checkout' ), 'button-primary' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php echo self::tool_button( 'english', __( 'Restore English labels', 'rar-woo-cart-checkout' ), __( 'Replace current labels with English defaults?', 'rar-woo-cart-checkout' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</p>
				</section>

				<section class="rar-wcc-panel">
					<header><h2><?php esc_html_e( 'Maintenance', 'rar-woo-cart-checkout' ); ?></h2></header>
					<p>
						<?php echo self::tool_button( 'cache', __( 'Clear stats & customer-history cache', 'rar-woo-cart-checkout' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php echo self::tool_button( 'cleanup', __( 'Run cleanup now', 'rar-woo-cart-checkout' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</p>
					<p><?php echo self::tool_button( 'reset', __( 'Reset all settings to defaults', 'rar-woo-cart-checkout' ), __( 'Reset ALL settings to defaults? This cannot be undone (export first).', 'rar-woo-cart-checkout' ), 'rar-wcc-danger' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
				</section>

				<section class="rar-wcc-panel">
					<header><h2><?php esc_html_e( 'Export settings', 'rar-woo-cart-checkout' ); ?></h2></header>
					<textarea readonly rows="8" class="large-text code" id="rar-wcc-export"><?php echo esc_textarea( $export ); ?></textarea>
					<p><button type="button" class="button" data-copy="#rar-wcc-export"><?php esc_html_e( 'Copy', 'rar-woo-cart-checkout' ); ?></button>
						<a class="button" download="rar-wcc-settings.json" href="data:application/json;charset=utf-8,<?php echo rawurlencode( $export ); ?>"><?php esc_html_e( 'Download .json', 'rar-woo-cart-checkout' ); ?></a></p>
				</section>

				<section class="rar-wcc-panel">
					<header><h2><?php esc_html_e( 'Import settings', 'rar-woo-cart-checkout' ); ?></h2></header>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="rar_wcc_tool">
						<input type="hidden" name="tool" value="import">
						<?php wp_nonce_field( 'rar_wcc_tool_import' ); ?>
						<textarea name="import" rows="8" class="large-text code" placeholder="{ &quot;plugin&quot;: &quot;rar-woo-cart-checkout&quot;, … }"></textarea>
						<p><button class="button button-primary"><?php esc_html_e( 'Import', 'rar-woo-cart-checkout' ); ?></button></p>
					</form>
				</section>
			</div>
		</div>
		<?php
	}

	public static function run_tool() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'rar-woo-cart-checkout' ) );
		}
		$tool = sanitize_key( wp_unslash( $_REQUEST['tool'] ?? '' ) );
		check_admin_referer( 'rar_wcc_tool_' . $tool );

		switch ( $tool ) {
			case 'bangla':
			case 'english':
				$settings = RAR_WCC_Settings::all();
				$preset   = RAR_WCC_Settings::bangla_preset();
				$defaults = RAR_WCC_Settings::defaults();
				foreach ( $preset as $k => $v ) {
					$settings[ $k ] = 'bangla' === $tool ? $v : $defaults[ $k ];
				}
				RAR_WCC_Settings::save( $settings );
				self::redirect( 'rar-wcc-tools', array( 'rar_msg' => $tool ) );
				break;

			case 'reset':
				RAR_WCC_Settings::save( RAR_WCC_Settings::defaults() );
				self::redirect( 'rar-wcc-tools', array( 'rar_msg' => 'reset' ) );
				break;

			case 'import':
				$json = json_decode( wp_unslash( $_POST['import'] ?? '' ), true ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				if ( ! is_array( $json ) || ( $json['plugin'] ?? '' ) !== 'rar-woo-cart-checkout' || ! is_array( $json['settings'] ?? null ) ) {
					self::redirect( 'rar-wcc-tools', array( 'rar_msg' => 'import_bad' ) );
				}
				RAR_WCC_Settings::save( RAR_WCC_Settings::sanitize( $json['settings'], true ) );
				self::redirect( 'rar-wcc-tools', array( 'rar_msg' => 'imported' ) );
				break;

			case 'switch_classic':
				$map = array(
					'cart'     => '<!-- wp:shortcode -->[woocommerce_cart]<!-- /wp:shortcode -->',
					'checkout' => '<!-- wp:shortcode -->[woocommerce_checkout]<!-- /wp:shortcode -->',
				);
				foreach ( self::block_pages() as $page => $id ) {
					$post = get_post( $id );
					update_post_meta( $id, '_rar_wcc_block_backup', wp_slash( $post->post_content ) );
					wp_update_post(
						array(
							'ID'           => $id,
							'post_content' => $map[ $page ],
						)
					);
				}
				self::redirect( 'rar-wcc-dashboard', array( 'rar_msg' => 'classic' ) );
				break;

			case 'restore_blocks':
				foreach ( array( 'cart', 'checkout' ) as $page ) {
					$id     = wc_get_page_id( $page );
					$backup = $id > 0 ? get_post_meta( $id, '_rar_wcc_block_backup', true ) : '';
					if ( $backup ) {
						wp_update_post(
							array(
								'ID'           => $id,
								'post_content' => wp_slash( $backup ),
							)
						);
						delete_post_meta( $id, '_rar_wcc_block_backup' );
					}
				}
				self::redirect( 'rar-wcc-dashboard', array( 'rar_msg' => 'restored' ) );
				break;

			case 'cache':
				global $wpdb;
				delete_transient( 'rar_wcc_stats' );
				$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_rar\_wcc\_hist\_%' OR option_name LIKE '\_transient\_timeout\_rar\_wcc\_hist\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				self::redirect( 'rar-wcc-tools', array( 'rar_msg' => 'cache' ) );
				break;

			case 'cleanup':
				RAR_WCC_Install::maintenance();
				if ( ! wp_next_scheduled( RAR_WCC_Install::CRON_HOOK ) ) {
					wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', RAR_WCC_Install::CRON_HOOK );
				}
				self::redirect( 'rar-wcc-tools', array( 'rar_msg' => 'cleanup' ) );
				break;
		}
		self::redirect( 'rar-wcc-tools' );
	}
}
