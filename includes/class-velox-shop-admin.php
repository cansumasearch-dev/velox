<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Velox Shop — the shop editor in wp-admin: one full-screen app (orders,
 * products, coupons, customers, settings) in the Velox design, talking to
 * this class through one AJAX action. The screens themselves are drawn by
 * admin/js/velox-shop-admin.js.
 *
 * @package Velox
 */
class Velox_Shop_Admin {

	const SLUG = 'velox-shop';
	const CAP  = 'manage_options';
	const MASK = '••••••••';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'admin_body_class', array( __CLASS__, 'body_class' ) );
		add_action( 'wp_ajax_velox_shop_admin', array( __CLASS__, 'ajax' ) );
		add_action( 'wp_ajax_velox_shop_export', array( __CLASS__, 'export' ) );
		// The old product and order screens open the shop editor instead.
		add_action( 'load-post.php', array( __CLASS__, 'redirect_classic' ) );
		add_action( 'load-post-new.php', array( __CLASS__, 'redirect_classic' ) );
		add_action( 'admin_init', array( __CLASS__, 'fonts_once' ) );
	}

	/**
	 * The design's fonts are fetched when it is saved. A shop updated from an
	 * older version has not saved yet: fetch them on the next admin visit, and
	 * try at most once an hour if the font server cannot be reached.
	 */
	public static function fonts_once() {
		if ( wp_doing_ajax() || ! current_user_can( 'manage_options' ) || ! Velox_Shop::design_fonts() || '' !== Velox_Shop::fonts_url() || get_transient( 'velox_shop_fonts_try' ) ) {
			return;
		}
		set_transient( 'velox_shop_fonts_try', 1, HOUR_IN_SECONDS );
		Velox_Shop::fonts_url( null, true );
	}

	public static function order_admin_url( $id ) {
		return admin_url( 'admin.php?page=' . self::SLUG . '#/orders/' . (int) $id );
	}

	public static function product_admin_url( $id ) {
		return admin_url( 'admin.php?page=' . self::SLUG . '#/products/' . (int) $id );
	}

	public static function redirect_classic() {
		$pt = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : '';
		$id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
		if ( $id ) {
			$pt = get_post_type( $id );
		}
		if ( Velox_Shop::PRODUCT === $pt && empty( $_GET['classic'] ) ) {
			wp_safe_redirect( $id ? self::product_admin_url( $id ) : admin_url( 'admin.php?page=' . self::SLUG . '#/products/new' ) );
			exit;
		}
		if ( Velox_Shop::ORDER === $pt && $id ) {
			wp_safe_redirect( self::order_admin_url( $id ) );
			exit;
		}
	}

	public static function menu() {
		$open = self::badge_count();
		add_menu_page(
			__( 'Velox Shop', 'velox' ),
			__( 'Velox Shop', 'velox' ) . ( $open ? ' <span class="awaiting-mod">' . (int) $open . '</span>' : '' ),
			self::CAP,
			self::SLUG,
			array( __CLASS__, 'render' ),
			'data:image/svg+xml;base64,' . base64_encode( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#a7aaad" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6.5 3.5h11l2 4v11.5a1.5 1.5 0 0 1-1.5 1.5h-12A1.5 1.5 0 0 1 4.5 19V7.5z"/><path d="M4.5 7.5h15"/><path d="M15.5 11a3.5 3.5 0 0 1-7 0"/></svg>' ),
			100.9
		);
		// The same sections, in the same order, as the shop's own bar.
		foreach ( array( '' => __( 'Overview', 'velox' ), 'revenue' => __( 'Revenue', 'velox' ), 'payouts' => __( 'Payouts', 'velox' ), 'orders' => __( 'Orders', 'velox' ), 'products' => __( 'Products', 'velox' ), 'coupons' => __( 'Coupons', 'velox' ), 'customers' => __( 'Customers', 'velox' ), 'reviews' => __( 'Reviews', 'velox' ), 'settings' => __( 'Settings', 'velox' ), 'settings/help' => __( 'Help', 'velox' ) ) as $k => $l ) {
			add_submenu_page( self::SLUG, 'Velox Shop — ' . $l, $l, self::CAP, '' === $k ? self::SLUG : self::SLUG . '#/' . $k, '' === $k ? array( __CLASS__, 'render' ) : '__return_null' );
		}
	}

	/** Paid orders waiting to be shipped + open withdrawals. */
	private static function badge_count() {
		$n = get_transient( 'velox_shop_badge' );
		if ( false === $n ) {
			$a = get_posts( array( 'post_type' => Velox_Shop::ORDER, 'post_status' => 'publish', 'numberposts' => 100, 'fields' => 'ids',
				'meta_query' => array( array( 'key' => '_vxs_pay', 'value' => 'paid' ), array( 'key' => '_vxs_ful', 'value' => 'unfulfilled' ) ) ) );
			$n = count( $a );
			set_transient( 'velox_shop_badge', $n, 5 * MINUTE_IN_SECONDS );
		}
		return (int) $n;
	}

	public static function body_class( $c ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && false !== strpos( (string) $screen->id, self::SLUG ) ) {
			$c .= ' velox-builder-fullbleed velox-shop-admin';
		}
		return $c;
	}

	public static function render() {
		echo '<div id="velox-builder-admin" class="vsa"><div id="vsa-app" aria-live="polite"><div class="vsa-boot">' . esc_html__( 'Opening the shop…', 'velox' ) . '</div></div></div>';
	}

	public static function assets( $hook ) {
		if ( false === strpos( (string) $hook, self::SLUG ) ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_style( 'velox-builder-admin', VELOX_ASSETS . 'css/velox-builder.css', array(), VELOX_VERSION );
		wp_enqueue_style( 'velox-shop-admin', VELOX_ASSETS . 'css/velox-shop-admin.css', array( 'velox-builder-admin' ), VELOX_VERSION );
		wp_enqueue_script( 'velox-select', VELOX_ASSETS . 'js/velox-select.js', array(), VELOX_VERSION, true );
		wp_enqueue_script( 'velox-shop-admin', VELOX_ASSETS . 'js/velox-shop-admin.js', array( 'velox-select' ), VELOX_VERSION, true );
		wp_localize_script( 'velox-shop-admin', 'VSA', self::boot() );
		if ( class_exists( 'Velox_Help' ) ) {
			Velox_Help::enqueue( 'shop' );
		}
	}

	/* ---------------------------------------------------------------- boot */

	private static function boot() {
		$pages = array();
		foreach ( get_pages( array( 'post_status' => 'publish,draft,private' ) ) as $p ) {
			$pages[] = array( 'id' => (int) $p->ID, 'title' => $p->post_title ? $p->post_title : '#' . $p->ID, 'url' => get_permalink( $p ) );
		}
		$mails = array();
		foreach ( Velox_Shop::mail_types() as $k => $m ) {
			$mails[] = array( 'key' => $k, 'title' => $m[0], 'desc' => $m[1] );
		}
		$methods = array();
		foreach ( Velox_Shop_Pay::methods() as $k => $m ) {
			if ( 'manual' !== $k ) { // the fallback for shops from before 4.53 is not a setting
				$methods[] = array( 'key' => $k, 'title' => $m[0], 'desc' => $m[1] );
			}
		}
		return array(
			'ajax'      => admin_url( 'admin-ajax.php' ),
			'nonce'     => wp_create_nonce( 'velox_shop_admin' ),
			'settings'  => self::settings_out(),
			'countries' => Velox_Shop::countries(),
			'methods'   => $methods,
			'designs'   => Velox_Shop::presets(),
			'shopPages' => self::pages_status(),
			'stripeTypes' => Velox_Shop_Pay::stripe_types(),
			'currencyCode' => strtoupper( (string) Velox_Shop::get( 'currency', 'EUR' ) ),
			'cartStats'    => Velox_Shop_Extras::cart_stats(),
			'couponCodes'  => array_values( array_filter( array_map( function ( $c ) { return empty( $c['off'] ) ? (string) $c['code'] : ''; }, Velox_Shop::coupons() ) ) ),
			'reviewsOpen'  => Velox_Shop_Extras::pending_reviews(),
			'carriers'     => array_keys( Velox_Shop::carriers() ),
			'fonts'     => array_map( function ( $f ) { return array( $f[0], $f[1] ); }, Velox_Shop::fonts() ),
			'mails'     => $mails,
			'pages'     => $pages,
			'cats'      => self::cats(),
			'home'      => home_url( '/' ),
			'webhook'   => rest_url( 'velox/v1/shop/stripe' ),
			'currency'  => Velox_Shop::currency(),
			'symbolPos' => Velox_Shop::get( 'symbol_pos' ),
			'locale'    => str_replace( '_', '-', get_user_locale() ),
			'classic'   => admin_url( 'edit.php?post_type=' . Velox_Shop::PRODUCT ),
			'export'    => wp_nonce_url( admin_url( 'admin-ajax.php?action=velox_shop_export' ), 'velox_shop_export' ),
			'user'      => wp_get_current_user()->display_name,
			'mailOk'    => (bool) Velox_Settings::get( 'util_mail' ),
			'mailUrl'   => admin_url( 'admin.php?page=velox&tab=utilities&util=mail' ),
			'i18n'      => class_exists( 'Velox' ) ? Velox::js_dictionary() : array(),
		);
	}

	/** Settings for the screen; secrets are never sent back, only whether they are set. */
	private static function settings_out() {
		$s = Velox_Shop::settings();
		foreach ( array( 'stripe_secret', 'stripe_whsec', 'paypal_secret' ) as $k ) {
			$s[ $k ] = '' !== (string) $s[ $k ] ? self::MASK : '';
		}
		if ( '' !== (string) Velox_Shop::get( 'stripe_secret' ) ) {
			$s['stripe_mode'] = false !== strpos( (string) Velox_Shop::get( 'stripe_secret' ), '_test_' ) ? 'test' : 'live';
		}
		$s['logo_url'] = (int) $s['logo'] ? wp_get_attachment_image_url( (int) $s['logo'], 'medium' ) : '';
		return $s;
	}

	private static function cats() {
		$out   = array();
		$terms = get_terms( array( 'taxonomy' => Velox_Shop::CAT, 'hide_empty' => false ) );
		if ( $terms && ! is_wp_error( $terms ) ) {
			foreach ( $terms as $t ) {
				$out[] = array( 'id' => (int) $t->term_id, 'name' => $t->name, 'slug' => $t->slug, 'count' => (int) $t->count, 'parent' => (int) $t->parent );
			}
		}
		return $out;
	}

	/* ---------------------------------------------------------------- ajax */

	public static function ajax() {
		if ( ! current_user_can( self::CAP ) || ! check_ajax_referer( 'velox_shop_admin', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'velox' ) ), 403 );
		}
		$do = sanitize_key( wp_unslash( $_POST['do'] ?? '' ) );
		$in = json_decode( wp_unslash( (string) ( $_POST['data'] ?? '{}' ) ), true );
		$in = is_array( $in ) ? $in : array();
		$fn = 'do_' . $do;
		if ( ! method_exists( __CLASS__, $fn ) ) {
			wp_send_json_error( array( 'message' => 'Unknown action' ), 400 );
		}
		$res = call_user_func( array( __CLASS__, $fn ), $in );
		if ( is_wp_error( $res ) ) {
			wp_send_json_error( array( 'message' => $res->get_error_message() ), 400 );
		}
		delete_transient( 'velox_shop_badge' );
		wp_send_json_success( $res );
	}

	/* ---- overview */

	/**
	 * The pages the shop uses and whether each exists. The shop's own pages can be
	 * created with one click; legal pages hold the owner's own texts, so they are
	 * chosen in Settings instead of being created empty.
	 */
	/** Edit a page where it is built: Velox Builder if it has a design there, else WordPress. */
	private static function page_edit_url( $id ) {
		if ( class_exists( 'Velox_Builder' ) && Velox_Builder::is_enabled() ) {
			$doc = (int) Velox_Builder::doc_id_for_post( $id );
			if ( $doc ) {
				return Velox_Builder::edit_url( $doc );
			}
		}
		return (string) get_edit_post_link( $id, 'raw' );
	}

	private static function pages_status() {
		$list = array(
			'shop'       => array( __( 'Shop', 'velox' ), 1 ),
			'cart'       => array( __( 'Cart', 'velox' ), 1 ),
			'checkout'   => array( __( 'Checkout', 'velox' ), 1 ),
			'account'    => array( __( 'My account', 'velox' ), 1 ),
			'withdraw'   => array( __( 'Withdraw from a purchase', 'velox' ), 1 ),
			'terms'      => array( __( 'Terms and conditions', 'velox' ), 0 ),
			'withdrawal' => array( __( 'Withdrawal policy', 'velox' ), 0 ),
			'privacy'    => array( __( 'Privacy policy', 'velox' ), 0 ),
			'shipping'   => array( __( 'Shipping and payment', 'velox' ), 0 ),
		);
		$out = array();
		foreach ( $list as $k => $l ) {
			$id = (int) Velox_Shop::get( 'page_' . $k );
			if ( ! $id && 'privacy' === $k ) {
				$id = (int) get_option( 'wp_page_for_privacy_policy' );
			}
			$st    = $id ? get_post_status( $id ) : '';
			$out[] = array(
				'key'    => $k,
				'label'  => $l[0],
				'create' => (bool) $l[1],
				'ok'     => 'publish' === $st,
				'state'  => $st ? $st : 'missing',
				'title'  => $id && $st ? get_the_title( $id ) : '',
				'url'    => $id && 'publish' === $st ? get_permalink( $id ) : '',
				'edit'   => $id && $st ? self::page_edit_url( $id ) : '',
			);
		}
		return $out;
	}

	private static function do_stats( $in ) {
		$days = 30;
		$from = strtotime( '-' . ( $days - 1 ) . ' days', strtotime( wp_date( 'Y-m-d' ) . ' 00:00:00' ) );
		$ids  = get_posts( array( 'post_type' => Velox_Shop::ORDER, 'post_status' => 'publish', 'numberposts' => 2000, 'fields' => 'ids', 'date_query' => array( array( 'after' => gmdate( 'Y-m-d H:i:s', $from - DAY_IN_SECONDS ) ) ) ) );
		$series = array();
		for ( $i = 0; $i < $days; $i++ ) {
			$series[ wp_date( 'Y-m-d', $from + $i * DAY_IN_SECONDS ) ] = 0;
		}
		$rev = 0;
		$cnt = 0;
		$today = 0;
		$top = array();
		foreach ( $ids as $id ) {
			$o = Velox_Shop::order( $id );
			if ( ! $o || ! in_array( $o['payment']['status'], array( 'paid', 'partial' ), true ) ) {
				continue;
			}
			$d   = wp_date( 'Y-m-d', (int) ( $o['payment']['paid_at'] ? $o['payment']['paid_at'] : $o['created'] ) );
			$net = $o['total'] - (float) $o['payment']['refunded'];
			if ( isset( $series[ $d ] ) ) {
				$series[ $d ] += $net;
				$rev += $net;
				$cnt++;
				foreach ( $o['items'] as $it ) {
					$k = (int) $it['pid'];
					if ( ! isset( $top[ $k ] ) ) {
						$top[ $k ] = array( 'id' => $k, 'name' => $it['name'], 'qty' => 0, 'total' => 0 );
					}
					$top[ $k ]['qty']   += (int) $it['qty'];
					$top[ $k ]['total'] += (float) $it['total'];
				}
			}
			if ( wp_date( 'Y-m-d' ) === $d ) {
				$today += $net;
			}
		}
		usort( $top, function ( $a, $b ) { return $b['total'] <=> $a['total']; } );
		$q = function ( $mq ) {
			return count( get_posts( array( 'post_type' => Velox_Shop::ORDER, 'post_status' => 'publish', 'numberposts' => 500, 'fields' => 'ids', 'meta_query' => $mq ) ) );
		};
		$low = array();
		foreach ( get_posts( array( 'post_type' => Velox_Shop::PRODUCT, 'post_status' => 'publish', 'numberposts' => 300, 'fields' => 'ids' ) ) as $pid ) {
			$p  = Velox_Shop::product( $pid );
			$st = Velox_Shop::stock_of( $p );
			if ( null !== $st && $st <= (int) Velox_Shop::get( 'low_stock', 3 ) && empty( $p['unique'] ) ) {
				$low[] = array( 'id' => $pid, 'name' => $p['title'], 'stock' => $st, 'thumb' => get_the_post_thumbnail_url( $pid, 'thumbnail' ) );
			}
		}
		$recent = array_map( array( __CLASS__, 'order_row' ), get_posts( array( 'post_type' => Velox_Shop::ORDER, 'post_status' => 'publish', 'numberposts' => 6, 'fields' => 'ids' ) ) );
		$line = self::do_revenue( array( 'range' => '30d', 'compare' => 'prev' ) );
		return array(
			'line'    => is_wp_error( $line ) ? null : array( 'labels' => $line['labels'], 'short' => $line['short'], 'cmpLabels' => $line['cmpLabels'], 'cur' => $line['cur']['revenue'], 'cmp' => $line['cmp']['revenue'], 'span' => $line['span'], 'cmpSpan' => $line['cmpSpan'], 'total' => $line['total'], 'cmpTotal' => $line['cmpTotal'] ),
			'revenue' => Velox_Shop::r( $rev ),
			'orders'  => $cnt,
			'avg'     => $cnt ? Velox_Shop::r( $rev / $cnt ) : 0,
			'today'   => Velox_Shop::r( $today ),
			'series'  => array_map( array( 'Velox_Shop', 'r' ), $series ),
			'toShip'  => $q( array( array( 'key' => '_vxs_pay', 'value' => 'paid' ), array( 'key' => '_vxs_ful', 'value' => 'unfulfilled' ) ) ),
			'unpaid'  => $q( array( array( 'key' => '_vxs_pay', 'value' => 'pending' ) ) ),
			'withdrawals' => count( self::withdrawal_ids() ),
			'low'     => array_slice( $low, 0, 8 ),
			'top'     => array_slice( array_values( $top ), 0, 5 ),
			'recent'  => array_values( array_filter( $recent ) ),
			'products'=> (int) ( wp_count_posts( Velox_Shop::PRODUCT )->publish ?? 0 ),
			'checks'  => self::health(),
			'pages'   => self::pages_status(),
		);
	}

	/** What still stops the shop from selling (shown on the overview). */
	private static function health() {
		$s   = Velox_Shop::settings();
		$out = array();
		if ( ! Velox_Shop_Pay::available() ) {
			$out[] = array( 'k' => 'pay', 'sev' => 'err', 'text' => __( 'No payment method is ready — visitors cannot order.', 'velox' ), 'go' => '#/settings/payments' );
		}
		foreach ( array( 'checkout' => __( 'There is no checkout page yet.', 'velox' ), 'cart' => __( 'There is no cart page yet.', 'velox' ) ) as $k => $m ) {
			if ( ! (int) $s[ 'page_' . $k ] || 'publish' !== get_post_status( (int) $s[ 'page_' . $k ] ) ) {
				$out[] = array( 'k' => 'page', 'sev' => 'err', 'text' => $m, 'go' => '#/settings/pages' );
			}
		}
		foreach ( array( 'terms' => __( 'No terms and conditions page is linked.', 'velox' ), 'withdrawal' => __( 'No withdrawal policy page is linked (required for selling to consumers in the EU).', 'velox' ), 'privacy' => __( 'No privacy policy page is linked.', 'velox' ) ) as $k => $m ) {
			if ( ! (int) $s[ 'page_' . $k ] ) {
				$out[] = array( 'k' => 'legal', 'sev' => 'warn', 'text' => $m, 'go' => '#/settings/pages' );
			}
		}
		if ( '' === trim( (string) $s['store_address'] ) ) {
			$out[] = array( 'k' => 'store', 'sev' => 'warn', 'text' => __( 'Your business address is missing (needed on invoices).', 'velox' ), 'go' => '#/settings/store' );
		}
		if ( ! empty( $s['pay_stripe'] ) && '' === (string) $s['stripe_whsec'] ) {
			$out[] = array( 'k' => 'stripe', 'sev' => 'warn', 'text' => __( 'Stripe has no webhook secret: payments are still confirmed when customers come back, but not if they close the tab.', 'velox' ), 'go' => '#/settings/payments' );
		}
		if ( ! Velox_Settings::get( 'util_mail' ) ) {
			$out[] = array( 'k' => 'mail', 'sev' => 'warn', 'text' => __( 'Order emails go out through the server’s mail function. Set up Velox Mail (SMTP) so they don’t land in spam.', 'velox' ), 'go' => 'mail' );
		}
		return $out;
	}

	/* ---- orders */

	private static function order_row( $id ) {
		$o = Velox_Shop::order( $id );
		if ( ! $o ) {
			return null;
		}
		return array(
			'id'      => (int) $id,
			'number'  => $o['number'],
			'created' => (int) $o['created'],
			'name'    => trim( $o['customer']['first'] . ' ' . $o['customer']['last'] ),
			'email'   => $o['customer']['email'],
			'total'   => $o['total'],
			'items'   => array_sum( array_map( function ( $i ) { return (int) $i['qty']; }, $o['items'] ) ),
			'pay'     => $o['payment']['status'],
			'method'  => $o['payment']['method'],
			'methodLabel' => Velox_Shop_Pay::label( $o['payment']['method'] ),
			'ful'     => $o['fulfil']['status'],
			'country' => $o['ship_to'] ? $o['ship_to']['address']['country'] : $o['customer']['address']['country'],
			'withdrawn' => ! empty( $o['withdrawal'] ),
		);
	}

	private static function withdrawal_ids() {
		$ids = get_posts( array( 'post_type' => Velox_Shop::ORDER, 'post_status' => 'publish', 'numberposts' => 300, 'fields' => 'ids', 'meta_key' => Velox_Shop::OMETA, 'meta_compare' => 'LIKE', 'meta_value' => '"status":"open"' ) );
		return array_values( array_filter( $ids, function ( $id ) { $o = Velox_Shop::order( $id ); return $o && ! empty( $o['withdrawal'] ) && 'open' === ( $o['withdrawal']['status'] ?? '' ); } ) );
	}

	private static function do_orders( $in ) {
		$view = sanitize_key( (string) ( $in['view'] ?? 'all' ) );
		$args = array( 'post_type' => Velox_Shop::ORDER, 'post_status' => 'publish', 'posts_per_page' => 30, 'paged' => max( 1, (int) ( $in['page'] ?? 1 ) ), 'fields' => 'ids' );
		$mq   = array();
		switch ( $view ) {
			case 'unpaid':
				$mq[] = array( 'key' => '_vxs_pay', 'value' => 'pending' );
				break;
			case 'ship':
				$mq[] = array( 'key' => '_vxs_pay', 'value' => array( 'paid', 'pending' ), 'compare' => 'IN' );
				$mq[] = array( 'key' => '_vxs_ful', 'value' => 'unfulfilled' );
				break;
			case 'shipped':
				$mq[] = array( 'key' => '_vxs_ful', 'value' => array( 'shipped', 'ready', 'delivered', 'done' ), 'compare' => 'IN' );
				break;
			case 'cancelled':
				$mq[] = array( 'key' => '_vxs_ful', 'value' => 'cancelled' );
				break;
			case 'withdrawn':
				$args['post__in'] = self::withdrawal_ids() ? self::withdrawal_ids() : array( 0 );
				break;
		}
		$s = trim( sanitize_text_field( (string) ( $in['q'] ?? '' ) ) );
		if ( '' !== $s ) {
			$mq[] = array( 'relation' => 'OR',
				array( 'key' => '_vx_ref', 'value' => $s, 'compare' => 'LIKE' ),
				array( 'key' => '_vx_email', 'value' => $s, 'compare' => 'LIKE' ),
				array( 'key' => '_vx_name', 'value' => $s, 'compare' => 'LIKE' ) );
		}
		if ( $mq ) {
			$args['meta_query'] = array_merge( array( 'relation' => 'AND' ), $mq );
		}
		$q = new WP_Query( $args );
		return array( 'rows' => array_values( array_filter( array_map( array( __CLASS__, 'order_row' ), $q->posts ) ) ), 'total' => (int) $q->found_posts, 'pages' => (int) $q->max_num_pages );
	}

	private static function order_out( $o ) {
		$o['view']     = Velox_Shop_Front::order_url( $o );
		$o['invoiceUrl'] = ! empty( $o['invoice']['number'] ) ? add_query_arg( 'vxinv', $o['key'], home_url( '/' ) ) : '';
		$o['canRefundApi'] = in_array( $o['payment']['method'], array( 'stripe', 'paypal' ), true ) && '' !== (string) $o['payment']['ref'];
		$o['address_html'] = Velox_Shop::address_html( $o['customer'] );
		$o['ship_html']    = $o['ship_to'] ? Velox_Shop::address_html( $o['ship_to'] ) : '';
		$o['methodLabel']  = Velox_Shop_Pay::label( $o['payment']['method'] );
		$o['editWhy']      = self::edit_block( $o );
		$o['editable']     = '' === $o['editWhy'];
		$o['instructions'] = 'pending' === $o['payment']['status'] ? wp_strip_all_tags( str_replace( array( '<br>', '</div>', '</dd>' ), array( "\n", "\n", "\n" ), Velox_Shop_Pay::instructions_html( $o ) ) ) : '';
		foreach ( $o['items'] as &$i ) {
			$i['thumb'] = ! empty( $i['pid'] ) ? get_the_post_thumbnail_url( (int) $i['pid'], 'thumbnail' ) : '';
		}
		unset( $i );
		$other = array();
		if ( '' !== $o['customer']['email'] ) {
			$other = get_posts( array( 'post_type' => Velox_Shop::ORDER, 'post_status' => 'publish', 'numberposts' => 10, 'fields' => 'ids', 'meta_key' => '_vx_email', 'meta_value' => $o['customer']['email'], 'post__not_in' => array( $o['id'] ) ) );
		}
		$o['others'] = array_values( array_filter( array_map( array( __CLASS__, 'order_row' ), $other ) ) );
		return $o;
	}

	/**
	 * Why an order cannot be edited any more ('' = it can). Money that has
	 * moved, an invoice that exists or a parcel that left all fix the order.
	 */
	private static function edit_block( $o ) {
		$pay = $o['payment']['status'];
		if ( 'cancelled' === $pay || 'cancelled' === $o['fulfil']['status'] ) {
			return __( 'Reopen the order first, then change it.', 'velox' );
		}
		if ( 'pending' !== $pay ) {
			return __( 'This order is paid, so its items can no longer change. Refund the difference, or cancel it and take a new order.', 'velox' );
		}
		if ( in_array( $o['fulfil']['status'], array( 'shipped', 'ready', 'delivered', 'done' ), true ) ) {
			return __( 'This order has already been shipped.', 'velox' );
		}
		if ( ! empty( $o['invoice']['number'] ) ) {
			return __( 'An invoice exists for this order; invoices must not change afterwards. Cancel it and take a new order instead.', 'velox' );
		}
		if ( in_array( $o['payment']['method'], array( 'stripe', 'paypal' ), true ) && ! empty( $o['payment']['session'] ) ) {
			return __( 'The customer was sent to pay online with the old total. Switch the payment method to bank transfer or invoice in a new order, or wait until it is paid or cancelled.', 'velox' );
		}
		return '';
	}

	/**
	 * Payouts: what came in per payment method, and where the money is now —
	 * at Stripe, at PayPal, or already on the shop's own account.
	 */
	private static function do_payouts( $in ) {
		$fresh = ! empty( $in['fresh'] );
		$days  = 30;
		$ids   = get_posts( array( 'post_type' => Velox_Shop::ORDER, 'post_status' => 'publish', 'numberposts' => 2000, 'fields' => 'ids', 'date_query' => array( array( 'after' => gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS ) ) ) ) );
		$by    = array();
		foreach ( array_keys( Velox_Shop_Pay::methods() ) as $m ) {
			if ( 'manual' !== $m ) {
				$by[ $m ] = array( 'key' => $m, 'label' => Velox_Shop_Pay::label( $m ), 'on' => Velox_Shop_Pay::ready( $m ), 'paid' => 0.0, 'open' => 0.0, 'n' => 0, 'nOpen' => 0 );
			}
		}
		foreach ( $ids as $id ) {
			$o = Velox_Shop::order( $id );
			$m = $o ? $o['payment']['method'] : '';
			if ( ! isset( $by[ $m ] ) ) {
				continue;
			}
			if ( in_array( $o['payment']['status'], array( 'paid', 'partial' ), true ) ) {
				$by[ $m ]['paid'] += $o['total'] - (float) $o['payment']['refunded'];
				$by[ $m ]['n']++;
			} elseif ( 'pending' === $o['payment']['status'] && in_array( $m, array( 'bank', 'invoice', 'cod' ), true ) ) {
				$by[ $m ]['open'] += $o['total'];
				$by[ $m ]['nOpen']++;
			}
		}
		$iban = preg_replace( '/\s+/', '', (string) Velox_Shop::get( 'bank_iban', '' ) );
		return array(
			'days'    => $days,
			'methods' => array_values( $by ),
			'stripe'  => Velox_Shop_Pay::stripe_money( $fresh ),
			'paypal'  => Velox_Shop_Pay::paypal_money( $fresh ),
			'iban'    => '' !== $iban ? substr( $iban, 0, 4 ) . ' ···· ' . substr( $iban, -4 ) : '',
			'owner'   => (string) Velox_Shop::get( 'bank_owner', '' ),
		);
	}

	private static function do_payout_now() {
		$r = Velox_Shop_Pay::stripe_payout();
		return is_wp_error( $r ) ? $r : array( 'msg' => $r, 'stripe' => Velox_Shop_Pay::stripe_money( true ) );
	}

	/** Reviews: waiting for approval, published, or all — newest first. */
	private static function do_reviews( $in ) {
		$view   = in_array( $in['view'] ?? '', array( 'hold', 'approve', 'all' ), true ) ? $in['view'] : 'hold';
		$page   = max( 1, (int) ( $in['page'] ?? 1 ) );
		$args   = array( 'type' => Velox_Shop_Extras::REVIEW, 'status' => $view, 'number' => 30, 'offset' => ( $page - 1 ) * 30, 'orderby' => 'comment_date_gmt', 'order' => 'DESC' );
		$total  = (int) get_comments( array_merge( $args, array( 'count' => true, 'number' => 0, 'offset' => 0 ) ) );
		$rows   = array();
		foreach ( get_comments( $args ) as $c ) {
			$rows[] = self::review_row( $c );
		}
		return array( 'rows' => $rows, 'total' => $total, 'pages' => max( 1, (int) ceil( $total / 30 ) ), 'counts' => array(
			'hold'    => (int) get_comments( array( 'type' => Velox_Shop_Extras::REVIEW, 'status' => 'hold', 'count' => true ) ),
			'approve' => (int) get_comments( array( 'type' => Velox_Shop_Extras::REVIEW, 'status' => 'approve', 'count' => true ) ),
		) );
	}

	private static function review_row( $c ) {
		return array(
			'id'       => (int) $c->comment_ID,
			'product'  => (int) $c->comment_post_ID,
			'title'    => get_the_title( $c->comment_post_ID ),
			'thumb'    => (string) get_the_post_thumbnail_url( $c->comment_post_ID, 'thumbnail' ),
			'url'      => get_permalink( $c->comment_post_ID ),
			'rating'   => max( 1, min( 5, (int) get_comment_meta( $c->comment_ID, 'vx_rating', true ) ) ),
			'head'     => (string) get_comment_meta( $c->comment_ID, 'vx_title', true ),
			'text'     => (string) $c->comment_content,
			'name'     => (string) $c->comment_author,
			'email'    => (string) $c->comment_author_email,
			'verified' => (bool) get_comment_meta( $c->comment_ID, 'vx_verified', true ),
			'reply'    => (string) get_comment_meta( $c->comment_ID, 'vx_reply', true ),
			'status'   => '1' === (string) $c->comment_approved ? 'approve' : ( 'trash' === $c->comment_approved ? 'trash' : 'hold' ),
			'date'     => strtotime( $c->comment_date_gmt . ' UTC' ),
		);
	}

	/** Publish, hold back, reply to, or move a review to the trash (it can be restored there). */
	private static function do_review_action( $in ) {
		$c = get_comment( (int) ( $in['id'] ?? 0 ) );
		if ( ! $c || Velox_Shop_Extras::REVIEW !== $c->comment_type ) {
			return new WP_Error( 'x', __( 'This review does not exist.', 'velox' ) );
		}
		$msg = '';
		switch ( sanitize_key( (string) ( $in['act'] ?? '' ) ) ) {
			case 'approve':
				wp_set_comment_status( $c->comment_ID, 'approve' );
				$msg = __( 'Published.', 'velox' );
				break;
			case 'hold':
				wp_set_comment_status( $c->comment_ID, 'hold' );
				$msg = __( 'Hidden again.', 'velox' );
				break;
			case 'trash':
				wp_trash_comment( $c->comment_ID );
				$msg = __( 'Moved to the trash. It can be restored under Comments → Trash.', 'velox' );
				break;
			case 'reply':
				update_comment_meta( $c->comment_ID, 'vx_reply', mb_substr( sanitize_textarea_field( (string) ( $in['text'] ?? '' ) ), 0, 2000 ) );
				$msg = '' !== trim( (string) ( $in['text'] ?? '' ) ) ? __( 'Reply saved — it shows under the review.', 'velox' ) : __( 'Reply removed.', 'velox' );
				break;
			default:
				return new WP_Error( 'x', '' );
		}
		delete_transient( 'velox_shop_badge_rv' );
		Velox_Shop_Extras::refresh_rating( (int) $c->comment_post_ID );
		return array( 'row' => self::review_row( get_comment( $c->comment_ID ) ), 'msg' => $msg, 'open' => Velox_Shop_Extras::pending_reviews() );
	}

	/** Products for "add a product" when editing an order. */
	private static function do_order_pick() {
		$out = array();
		foreach ( get_posts( array( 'post_type' => Velox_Shop::PRODUCT, 'post_status' => 'publish', 'numberposts' => 400, 'orderby' => 'title', 'order' => 'ASC' ) ) as $post ) {
			$p = Velox_Shop::product( $post->ID );
			if ( ! $p ) {
				continue;
			}
			$row = array( 'id' => (int) $p['id'], 'title' => $p['title'], 'price' => Velox_Shop::price_of( $p ), 'can' => Velox_Shop::can_buy( $p, null, 1 ), 'variants' => array() );
			if ( Velox_Shop::has_variants( $p ) ) {
				foreach ( $p['variants'] as $v ) {
					if ( ! empty( $v['off'] ) ) {
						continue;
					}
					$row['variants'][] = array( 'id' => (string) $v['id'], 'label' => self::variant_label( $p, $v ), 'price' => Velox_Shop::price_of( $p, $v ), 'can' => Velox_Shop::can_buy( $p, $v, 1 ) );
				}
			}
			$out[] = $row;
		}
		$methods = array();
		foreach ( array( 'bank', 'invoice', 'cod' ) as $m ) {
			if ( Velox_Shop_Pay::ready( $m ) ) {
				$methods[] = array( 'key' => $m, 'label' => Velox_Shop_Pay::label( $m ) );
			}
		}
		$cod = Velox_Shop::num( Velox_Shop::get( 'cod_fee' ) );
		return array( 'products' => $out, 'methods' => $methods, 'codFee' => null === $cod ? 0 : $cod );
	}

	private static function variant_label( $p, $v ) {
		$l = array();
		foreach ( $p['options'] as $i => $opt ) {
			$l[] = $opt['name'] . ': ' . ( $v['opts'][ $i ] ?? '' );
		}
		return implode( ', ', $l );
	}

	/** New items, prices, shipping, discount or payment method; stock, VAT and total follow. */
	private static function edit_order( $id, $o, $in ) {
		$why = self::edit_block( $o );
		if ( '' !== $why ) {
			return new WP_Error( 'x', $why );
		}
		$rows = array_slice( (array) ( $in['items'] ?? array() ), 0, 100 );
		if ( ! $rows ) {
			return new WP_Error( 'x', __( 'An order needs at least one item. Cancel it instead.', 'velox' ) );
		}
		$before = $o['total'];
		$old    = $o['items'];
		$items  = array();
		foreach ( $rows as $r ) {
			$pid  = (int) ( $r['pid'] ?? 0 );
			$vid  = sanitize_text_field( (string) ( $r['vid'] ?? '' ) );
			$qty  = max( 1, min( 999, (int) ( $r['qty'] ?? 1 ) ) );
			$unit = Velox_Shop::num( $r['unit'] ?? '' );
			$line = null;
			foreach ( $old as $k => $ol ) {
				if ( (int) ( $ol['pid'] ?? 0 ) === $pid && (string) ( $ol['vid'] ?? '' ) === $vid && ( $pid || (string) $ol['name'] === (string) ( $r['name'] ?? '' ) ) ) {
					$line = $ol;
					unset( $old[ $k ] );
					break;
				}
			}
			if ( ! $line ) {
				$p = Velox_Shop::product( $pid );
				if ( ! $p ) {
					continue;
				}
				$v    = '' !== $vid ? Velox_Shop::variant( $p, $vid ) : null;
				$line = array(
					'pid' => $pid, 'vid' => $v ? (string) $v['id'] : '', 'name' => $p['title'], 'variant' => $v ? self::variant_label( $p, $v ) : '',
					'sku' => $v && '' !== (string) $v['sku'] ? $v['sku'] : $p['sku'], 'unit' => Velox_Shop::price_of( $p, $v ),
					'rate' => Velox_Shop::rate( $p['tax'] ), 'type' => $p['type'], 'delivery' => $p['delivery'],
				);
			}
			$line['qty']   = $qty;
			$line['unit']  = null === $unit ? (float) $line['unit'] : max( 0, Velox_Shop::r( $unit ) );
			$line['total'] = Velox_Shop::r( $line['unit'] * $qty );
			$line['tax']   = Velox_Shop::tax_in( $line['total'], (float) $line['rate'] );
			$items[]       = $line;
		}
		if ( ! $items ) {
			return new WP_Error( 'x', __( 'An order needs at least one item. Cancel it instead.', 'velox' ) );
		}
		// Stock: everything back, then the new lines out again.
		Velox_Shop::restock( $o );
		foreach ( $items as $i ) {
			if ( ! empty( $i['pid'] ) ) {
				Velox_Shop::adjust_stock( $i['pid'], $i['vid'] ?? '', -$i['qty'] );
			}
		}
		$o['stock_out'] = 1;
		$o['items']     = $items;
		// Payment method: only the ones that need no online step.
		$m = sanitize_key( (string) ( $in['method'] ?? $o['payment']['method'] ) );
		if ( $m !== $o['payment']['method'] && in_array( $m, array( 'bank', 'invoice', 'cod' ), true ) && Velox_Shop_Pay::ready( $m ) ) {
			$o['payment']['method'] = $m;
		}
		$sub = 0.0;
		foreach ( $items as $i ) {
			$sub += $i['total'];
		}
		$sub  = Velox_Shop::r( $sub );
		$disc = Velox_Shop::num( $in['discount'] ?? $o['discount'] );
		$o['subtotal'] = $sub;
		$o['discount'] = min( $sub, max( 0, Velox_Shop::r( null === $disc ? 0 : $disc ) ) );
		if ( $o['shipping'] && 'none' !== ( $o['shipping']['id'] ?? '' ) ) {
			$sc = Velox_Shop::num( $in['ship'] ?? $o['shipping']['cost'] );
			$o['shipping']['cost'] = max( 0, Velox_Shop::r( null === $sc ? 0 : $sc ) );
		}
		$fee      = Velox_Shop::num( Velox_Shop::get( 'cod_fee' ) );
		$o['fee'] = 'cod' === $o['payment']['method'] ? Velox_Shop::r( null === $fee ? 0 : $fee ) : 0;
		$ship     = $o['shipping'] ? (float) $o['shipping']['cost'] : 0;
		// VAT like at checkout: lines carry their rate, the discount lowers them all alike.
		$goods = Velox_Shop::r( $sub - $o['discount'] );
		$share = $sub > 0 ? $goods / $sub : 0;
		$tl    = array();
		if ( empty( $o['small'] ) ) {
			foreach ( $items as $i ) {
				if ( (float) $i['rate'] > 0 ) {
					$k        = (string) $i['rate'];
					$tl[ $k ] = ( $tl[ $k ] ?? 0 ) + Velox_Shop::tax_in( $i['total'] * $share, (float) $i['rate'] );
				}
			}
			$std = Velox_Shop::rate( 'standard' );
			if ( $std > 0 && ( $ship + $o['fee'] ) > 0 ) {
				$tl[ (string) $std ] = ( $tl[ (string) $std ] ?? 0 ) + Velox_Shop::tax_in( $ship + $o['fee'], $std );
			}
		}
		$o['tax_lines'] = array_map( array( 'Velox_Shop', 'r' ), $tl );
		$o['tax']       = Velox_Shop::r( array_sum( $o['tax_lines'] ) );
		$o['total']     = Velox_Shop::r( $goods + $ship + $o['fee'] );
		/* translators: 1: old total, 2: new total */
		$text = sprintf( __( 'Order changed: %1$s → %2$s.', 'velox' ), Velox_Shop::money( $before ), Velox_Shop::money( $o['total'] ) );
		Velox_Shop::note( $o, $text, ! empty( $in['tell'] ) );
		Velox_Shop::save_order( $id, $o );
		if ( ! empty( $in['tell'] ) && is_email( $o['customer']['email'] ) ) {
			/* translators: %s: order number */
			$subject = sprintf( __( 'Your order %s was updated', 'velox' ), $o['number'] );
			$lead    = __( 'We have updated your order as agreed. Here is how it looks now:', 'velox' );
			wp_mail( $o['customer']['email'], $subject, Velox_Shop::mail_layout( '<p>' . esc_html( $lead ) . '</p>' . Velox_Shop::summary_html( $o ) . Velox_Shop_Pay::instructions_html( $o, true ) . Velox_Shop::mail_button( Velox_Shop_Front::order_url( $o ), __( 'View your order', 'velox' ) ), $subject ), array( 'Content-Type: text/html; charset=UTF-8' ) );
		}
		return array( 'order' => self::order_out( Velox_Shop::order( $id ) ), 'msg' => ! empty( $in['tell'] ) ? __( 'Saved. The customer has the updated order.', 'velox' ) : __( 'Saved.', 'velox' ) );
	}

	private static function do_order( $in ) {
		$o = Velox_Shop::order( (int) ( $in['id'] ?? 0 ) );
		return $o ? self::order_out( $o ) : new WP_Error( 'x', __( 'This order does not exist.', 'velox' ) );
	}

	private static function do_order_action( $in ) {
		$id = (int) ( $in['id'] ?? 0 );
		$o  = Velox_Shop::order( $id );
		if ( ! $o ) {
			return new WP_Error( 'x', __( 'This order does not exist.', 'velox' ) );
		}
		$msg = '';
		switch ( sanitize_key( (string) ( $in['act'] ?? '' ) ) ) {
			case 'paid':
				Velox_Shop::mark_paid( $id, sanitize_text_field( (string) ( $in['ref'] ?? '' ) ), true );
				$msg = __( 'Marked as paid. The customer has been told.', 'velox' );
				break;
			case 'ship':
				Velox_Shop::mark_shipped( $id, (string) ( $in['carrier'] ?? '' ), (string) ( $in['tracking'] ?? '' ), (string) ( $in['url'] ?? '' ) );
				$msg = __( 'Marked as shipped. The customer has been told.', 'velox' );
				break;
			case 'delivered':
				Velox_Shop::mark_delivered( $id );
				break;
			case 'unship':
				$o['fulfil'] = array( 'status' => 'unfulfilled', 'carrier' => '', 'tracking' => '', 'at' => 0 );
				Velox_Shop::note( $o, __( 'Shipping undone.', 'velox' ) );
				Velox_Shop::save_order( $id, $o );
				break;
			case 'cancel':
				Velox_Shop::cancel( $id, sanitize_text_field( (string) ( $in['reason'] ?? '' ) ) );
				$msg = __( 'Cancelled. The stock is back and the customer has been told.', 'velox' );
				break;
			case 'reopen':
				$r = Velox_Shop::reopen( $id, ! empty( $in['tell'] ) );
				if ( is_wp_error( $r ) ) {
					return $r;
				}
				$msg = ! empty( $in['tell'] ) ? __( 'Reopened. The items are reserved again and the customer has been told.', 'velox' ) : __( 'Reopened. The items are reserved again.', 'velox' );
				break;
			case 'refund':
				$amount = (float) Velox_Shop::num( $in['amount'] ?? 0 );
				if ( $amount <= 0 ) {
					return new WP_Error( 'x', __( 'Enter the amount to refund.', 'velox' ) );
				}
				$ref = '';
				if ( ! empty( $in['api'] ) ) {
					$r = Velox_Shop_Pay::refund( $o, $amount );
					if ( is_wp_error( $r ) ) {
						return $r;
					}
					$ref = $r;
				}
				Velox_Shop::record_refund( $id, $amount, ! empty( $in['restock'] ), $ref );
				$msg = ! empty( $in['api'] ) ? __( 'Refunded through the payment provider.', 'velox' ) : __( 'Refund recorded. Remember to transfer the money.', 'velox' );
				break;
			case 'edit':
				return self::edit_order( $id, $o, $in );
			case 'note':
				$text = sanitize_textarea_field( (string) ( $in['text'] ?? '' ) );
				if ( '' === $text ) {
					return new WP_Error( 'x', __( 'Write a note first.', 'velox' ) );
				}
				Velox_Shop::note( $o, $text, ! empty( $in['customer'] ) );
				Velox_Shop::save_order( $id, $o );
				if ( ! empty( $in['customer'] ) && is_email( $o['customer']['email'] ) ) {
					/* translators: %s: order number */
					$subject = sprintf( __( 'A message about your order %s', 'velox' ), $o['number'] );
					wp_mail( $o['customer']['email'], $subject, Velox_Shop::mail_layout( '<p>' . nl2br( esc_html( $text ) ) . '</p>' . Velox_Shop::summary_html( $o ) . Velox_Shop::mail_button( Velox_Shop_Front::order_url( $o ), __( 'View your order', 'velox' ) ), $subject ), array( 'Content-Type: text/html; charset=UTF-8' ) );
					$msg = __( 'Sent to the customer.', 'velox' );
				}
				break;
			case 'reminder':
				Velox_Shop::mail( 'reminder', $o );
				Velox_Shop::note( $o, __( 'Payment reminder sent.', 'velox' ) );
				Velox_Shop::save_order( $id, $o );
				$msg = __( 'Reminder sent.', 'velox' );
				break;
			case 'resend':
				Velox_Shop::mail( 'placed', $o );
				$msg = __( 'Confirmation sent again.', 'velox' );
				break;
			case 'invoice':
				Velox_Shop::assign_invoice( $o );
				Velox_Shop::note( $o, __( 'Invoice created.', 'velox' ) . ' ' . $o['invoice']['number'] );
				Velox_Shop::save_order( $id, $o );
				break;
			case 'withdrawal':
				if ( ! empty( $o['withdrawal'] ) ) {
					$o['withdrawal']['status'] = 'done';
					Velox_Shop::note( $o, __( 'Withdrawal handled.', 'velox' ) );
					Velox_Shop::save_order( $id, $o );
				}
				break;
			case 'address':
				foreach ( array( 'customer', 'ship_to' ) as $who ) {
					if ( isset( $in[ $who ] ) && is_array( $in[ $who ] ) ) {
						$c = $in[ $who ];
						$clean = array(
							'first' => sanitize_text_field( (string) ( $c['first'] ?? '' ) ), 'last' => sanitize_text_field( (string) ( $c['last'] ?? '' ) ),
							'company' => sanitize_text_field( (string) ( $c['company'] ?? '' ) ),
							'address' => array_map( 'sanitize_text_field', array_intersect_key( (array) ( $c['address'] ?? array() ), array_flip( array( 'line1', 'line2', 'zip', 'city', 'country' ) ) ) ),
						);
						if ( 'customer' === $who ) {
							$clean['email'] = sanitize_email( (string) ( $c['email'] ?? $o['customer']['email'] ) );
							$clean['phone'] = sanitize_text_field( (string) ( $c['phone'] ?? '' ) );
							$o['customer'] = array_merge( $o['customer'], $clean );
						} else {
							$o['ship_to'] = $clean;
						}
					}
				}
				Velox_Shop::note( $o, __( 'Address changed.', 'velox' ) );
				Velox_Shop::save_order( $id, $o );
				break;
			case 'delete':
				if ( ! in_array( $o['payment']['status'], array( 'cancelled', 'pending' ), true ) ) {
					return new WP_Error( 'x', __( 'Only unpaid or cancelled orders can be deleted. Paid orders have to be kept for your books.', 'velox' ) );
				}
				Velox_Shop::restock( $o );
				wp_trash_post( $id );
				return array( 'deleted' => 1 );
			default:
				return new WP_Error( 'x', 'Unknown' );
		}
		return array( 'order' => self::order_out( Velox_Shop::order( $id ) ), 'msg' => $msg );
	}

	/* ---- products */

	private static function product_row( $id ) {
		$p = Velox_Shop::product( $id );
		if ( ! $p ) {
			return null;
		}
		list( $lo, $hi ) = Velox_Shop::price_range( $p );
		$a = Velox_Shop::availability( $p );
		$terms = wp_get_post_terms( $id, Velox_Shop::CAT, array( 'fields' => 'names' ) );
		return array(
			'id' => (int) $id, 'title' => $p['title'], 'status' => $p['status'], 'thumb' => get_the_post_thumbnail_url( $id, 'thumbnail' ),
			'price' => $lo, 'priceHi' => $hi, 'compare' => Velox_Shop::compare_of( $p ), 'mode' => $p['mode'], 'type' => $p['type'],
			'stock' => Velox_Shop::stock_of( $p ), 'avail' => $a[0], 'availText' => $a[1], 'sku' => $p['sku'],
			'variants' => count( $p['variants'] ), 'cats' => is_wp_error( $terms ) ? array() : $terms, 'url' => get_permalink( $id ), 'unique' => ! empty( $p['unique'] ),
		);
	}

	private static function do_products( $in ) {
		$args = array( 'post_type' => Velox_Shop::PRODUCT, 'post_status' => array( 'publish', 'draft', 'private', 'pending' ), 'posts_per_page' => 50, 'paged' => max( 1, (int) ( $in['page'] ?? 1 ) ), 'fields' => 'ids', 'orderby' => array( 'menu_order' => 'ASC', 'date' => 'DESC' ) );
		$s = trim( sanitize_text_field( (string) ( $in['q'] ?? '' ) ) );
		if ( '' !== $s ) {
			$args['s'] = $s;
		}
		if ( ! empty( $in['cat'] ) ) {
			$args['tax_query'] = array( array( 'taxonomy' => Velox_Shop::CAT, 'field' => 'term_id', 'terms' => (int) $in['cat'] ) );
		}
		if ( in_array( $in['status'] ?? '', array( 'publish', 'draft' ), true ) ) {
			$args['post_status'] = $in['status'];
		}
		$q    = new WP_Query( $args );
		$rows = array_values( array_filter( array_map( array( __CLASS__, 'product_row' ), $q->posts ) ) );
		if ( 'low' === ( $in['status'] ?? '' ) ) {
			$rows = array_values( array_filter( $rows, function ( $r ) { return in_array( $r['avail'], array( 'low', 'out', 'sold' ), true ); } ) );
		}
		return array( 'rows' => $rows, 'total' => (int) $q->found_posts, 'pages' => (int) $q->max_num_pages, 'cats' => self::cats() );
	}

	private static function do_product( $in ) {
		$id = (int) ( $in['id'] ?? 0 );
		$p  = Velox_Shop::product( $id );
		if ( ! $p ) {
			return new WP_Error( 'x', __( 'This product does not exist.', 'velox' ) );
		}
		$post = get_post( $id );
		$img  = function ( $aid ) {
			return array( 'id' => (int) $aid, 'url' => (string) wp_get_attachment_image_url( (int) $aid, 'medium' ), 'name' => get_the_title( (int) $aid ) );
		};
		$p['content']   = $post->post_content;
		$p['excerpt']   = $post->post_excerpt;
		$p['slug']      = $post->post_name;
		$p['menu']      = (int) $post->menu_order;
		$p['thumb']     = get_post_thumbnail_id( $id ) ? $img( get_post_thumbnail_id( $id ) ) : null;
		$p['galleryImgs'] = array_map( $img, $p['gallery'] );
		$p['fileInfo']  = array_map( function ( $f ) { return array( 'id' => (int) $f, 'name' => basename( (string) get_attached_file( (int) $f ) ) ); }, $p['files'] );
		$p['varImgs']   = array();
		foreach ( $p['variants'] as $v ) {
			if ( ! empty( $v['image'] ) ) {
				$p['varImgs'][ (string) $v['image'] ] = (string) wp_get_attachment_image_url( (int) $v['image'], 'thumbnail' );
			}
		}
		$p['catIds']    = array_map( 'intval', wp_get_post_terms( $id, Velox_Shop::CAT, array( 'fields' => 'ids' ) ) );
		$p['url']       = get_permalink( $id );
		$p['alertsWaiting'] = Velox_Shop_Extras::alerts_waiting( $id );
		// The colour each value shows in the shop now (from the word), as the picker's start.
		foreach ( $p['options'] as &$opt ) {
			if ( Velox_Shop_Extras::is_color_option( $opt['name'] ) ) {
				$opt['guess'] = array();
				foreach ( $opt['values'] as $val ) {
					$opt['guess'][ $val ] = Velox_Shop_Extras::color_of( array(), $val );
				}
			}
		}
		unset( $opt );
		$p['upsellInfo']= array_values( array_filter( array_map( function ( $u ) { return get_post( $u ) ? array( 'id' => (int) $u, 'title' => get_the_title( $u ) ) : null; }, $p['upsell'] ) ) );
		$p['sold']      = self::sold_count( $id );
		return $p;
	}

	private static function sold_count( $pid ) {
		global $wpdb;
		$like = '%"pid":' . (int) $pid . ',%';
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value LIKE %s", Velox_Shop::OMETA, $like ) );
	}

	private static function do_product_save( $in ) {
		$id    = (int) ( $in['id'] ?? 0 );
		$title = sanitize_text_field( (string) ( $in['title'] ?? '' ) );
		if ( '' === $title ) {
			return new WP_Error( 'x', __( 'Give the product a name.', 'velox' ) );
		}
		$post = array(
			'post_type'    => Velox_Shop::PRODUCT,
			'post_title'   => $title,
			'post_content' => wp_kses_post( (string) ( $in['content'] ?? '' ) ),
			'post_excerpt' => wp_kses_post( (string) ( $in['excerpt'] ?? '' ) ),
			'post_status'  => in_array( $in['status'] ?? '', array( 'publish', 'draft', 'private' ), true ) ? $in['status'] : 'draft',
			'menu_order'   => (int) ( $in['menu'] ?? 0 ),
		);
		if ( '' !== (string) ( $in['slug'] ?? '' ) ) {
			$post['post_name'] = sanitize_title( (string) $in['slug'] );
		}
		if ( $id ) {
			if ( Velox_Shop::PRODUCT !== get_post_type( $id ) ) {
				return new WP_Error( 'x', 'Wrong type' );
			}
			$post['ID'] = $id;
			$r = wp_update_post( wp_slash( $post ), true );
		} else {
			$r = wp_insert_post( wp_slash( $post ), true );
		}
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$id = (int) $r;
		if ( isset( $in['thumb'] ) ) {
			(int) $in['thumb'] ? set_post_thumbnail( $id, (int) $in['thumb'] ) : delete_post_thumbnail( $id );
		}
		wp_set_object_terms( $id, array_map( 'intval', (array) ( $in['catIds'] ?? array() ) ), Velox_Shop::CAT );
		Velox_Shop::save_product( $id, $in );
		// Old fields from the first shop are now in "specs"; drop them so they can't disagree.
		foreach ( array( '_vx_dims', '_vx_medium', '_vx_year' ) as $k ) {
			delete_post_meta( $id, $k );
		}
		return self::do_product( array( 'id' => $id ) );
	}

	private static function do_product_bulk( $in ) {
		$ids = array_map( 'intval', (array) ( $in['ids'] ?? array() ) );
		$act = sanitize_key( (string) ( $in['act'] ?? '' ) );
		foreach ( $ids as $id ) {
			if ( Velox_Shop::PRODUCT !== get_post_type( $id ) ) {
				continue;
			}
			if ( 'trash' === $act ) {
				wp_trash_post( $id );
			} elseif ( in_array( $act, array( 'publish', 'draft' ), true ) ) {
				wp_update_post( array( 'ID' => $id, 'post_status' => $act ) );
			} elseif ( 'duplicate' === $act ) {
				$src = get_post( $id );
				$new = wp_insert_post( wp_slash( array( 'post_type' => Velox_Shop::PRODUCT, 'post_status' => 'draft', 'post_title' => $src->post_title . ' ' . __( '(copy)', 'velox' ), 'post_content' => $src->post_content, 'post_excerpt' => $src->post_excerpt ) ) );
				if ( $new && ! is_wp_error( $new ) ) {
					update_post_meta( $new, Velox_Shop::META, wp_slash( (string) get_post_meta( $id, Velox_Shop::META, true ) ) );
					if ( get_post_thumbnail_id( $id ) ) {
						set_post_thumbnail( $new, get_post_thumbnail_id( $id ) );
					}
					wp_set_object_terms( $new, wp_get_post_terms( $id, Velox_Shop::CAT, array( 'fields' => 'ids' ) ), Velox_Shop::CAT );
					Velox_Shop::sync_index( $new );
				}
			} elseif ( 'stock' === $act ) {
				// Quick stock edit from the list.
				$p = Velox_Shop::product( $id );
				$in2 = $p;
				$in2['track'] = 1;
				$in2['stock'] = (string) max( 0, (int) ( $in['value'] ?? 0 ) );
				Velox_Shop::save_product( $id, $in2 );
			} elseif ( 'price' === $act ) {
				$p = Velox_Shop::product( $id );
				$in2 = $p;
				$in2['price'] = (string) ( $in['value'] ?? '' );
				Velox_Shop::save_product( $id, $in2 );
			}
		}
		return self::do_products( $in );
	}

	private static function do_cat( $in ) {
		$act = sanitize_key( (string) ( $in['act'] ?? '' ) );
		if ( 'add' === $act ) {
			$r = wp_insert_term( sanitize_text_field( (string) ( $in['name'] ?? '' ) ), Velox_Shop::CAT, array( 'parent' => (int) ( $in['parent'] ?? 0 ) ) );
			if ( is_wp_error( $r ) ) {
				return $r;
			}
		} elseif ( 'rename' === $act ) {
			wp_update_term( (int) $in['id'], Velox_Shop::CAT, array( 'name' => sanitize_text_field( (string) ( $in['name'] ?? '' ) ) ) );
		} elseif ( 'delete' === $act ) {
			wp_delete_term( (int) $in['id'], Velox_Shop::CAT );
		}
		return array( 'cats' => self::cats() );
	}

	/* ---- coupons */

	private static function do_coupons( $in ) {
		return array( 'rows' => Velox_Shop::coupons() );
	}

	private static function do_coupons_save( $in ) {
		$out  = array();
		$seen = array();
		foreach ( (array) ( $in['rows'] ?? array() ) as $c ) {
			$code = strtoupper( preg_replace( '/[^A-Za-z0-9_-]/', '', (string) ( $c['code'] ?? '' ) ) );
			if ( '' === $code || isset( $seen[ $code ] ) ) {
				continue;
			}
			$seen[ $code ] = 1;
			$out[] = array(
				'code'  => $code,
				'type'  => in_array( $c['type'] ?? '', array( 'percent', 'fixed', 'ship' ), true ) ? $c['type'] : 'percent',
				'value' => (string) ( Velox_Shop::num( $c['value'] ?? '' ) ?? '' ),
				'min'   => (string) ( Velox_Shop::num( $c['min'] ?? '' ) ?? '' ),
				'until' => preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $c['until'] ?? '' ) ) ? $c['until'] : '',
				'limit' => '' === (string) ( $c['limit'] ?? '' ) ? '' : (string) max( 1, (int) $c['limit'] ),
				'used'  => (int) ( $c['used'] ?? 0 ),
				'off'   => empty( $c['off'] ) ? 0 : 1,
				'note'  => mb_substr( sanitize_text_field( (string) ( $c['note'] ?? '' ) ), 0, 120 ),
			);
		}
		Velox_Shop::save_coupons( $out );
		return array( 'rows' => $out );
	}

	/* ---- revenue report */

	/**
	 * Revenue over a period, grouped by day, week or month, next to a period
	 * to compare with. Filters: payment method, product category, paid or
	 * placed orders, with or without VAT.
	 */
	private static function do_revenue( $in ) {
		$tz    = wp_timezone();
		$today = new DateTimeImmutable( 'today', $tz );
		$range = sanitize_key( (string) ( $in['range'] ?? '30d' ) );
		$cal   = '';
		switch ( $range ) {
			case '7d':
				$from = $today->modify( '-6 days' );
				$to   = $today;
				break;
			case '90d':
				$from = $today->modify( '-89 days' );
				$to   = $today;
				break;
			case 'month':
				$from = $today->modify( 'first day of this month' );
				$to   = $today;
				$cal  = 'month';
				break;
			case 'lastmonth':
				$from = $today->modify( 'first day of last month' );
				$to   = $from->modify( 'last day of this month' );
				$cal  = 'month';
				break;
			case '12m':
				$from = $today->modify( 'first day of this month' )->modify( '-11 months' );
				$to   = $today;
				$cal  = 'year';
				break;
			case 'year':
				$from = $today->setDate( (int) $today->format( 'Y' ), 1, 1 );
				$to   = $today;
				$cal  = 'year';
				break;
			case 'lastyear':
				$from = $today->setDate( (int) $today->format( 'Y' ) - 1, 1, 1 );
				$to   = $today->setDate( (int) $today->format( 'Y' ) - 1, 12, 31 );
				$cal  = 'year';
				break;
			case 'custom':
				$f    = DateTimeImmutable::createFromFormat( '!Y-m-d', (string) ( $in['from'] ?? '' ), $tz );
				$t    = DateTimeImmutable::createFromFormat( '!Y-m-d', (string) ( $in['to'] ?? '' ), $tz );
				if ( ! $f || ! $t ) {
					return new WP_Error( 'x', __( 'Choose a start and an end date.', 'velox' ) );
				}
				$from = min( $f, $t );
				$to   = max( $f, $t );
				if ( $from->diff( $to )->days > 1100 ) {
					$from = $to->modify( '-1100 days' );
				}
				break;
			default:
				$range = '30d';
				$from  = $today->modify( '-29 days' );
				$to    = $today;
		}
		$days  = $from->diff( $to )->days + 1;
		$group = sanitize_key( (string) ( $in['group'] ?? 'auto' ) );
		if ( ! in_array( $group, array( 'day', 'week', 'month' ), true ) ) {
			$group = $days <= 45 ? 'day' : ( $days <= 190 ? 'week' : 'month' );
		}
		// The period to compare with: the one just before, or the same dates a year earlier.
		$cmp_mode = sanitize_key( (string) ( $in['compare'] ?? 'prev' ) );
		$cmp      = null;
		if ( 'year' === $cmp_mode ) {
			$cmp = array( $from->modify( '-1 year' ), $to->modify( '-1 year' ) );
		} elseif ( 'prev' === $cmp_mode ) {
			if ( 'month' === $cal ) {
				$cf  = $from->modify( 'first day of last month' );
				$cmp = array( $cf, min( $cf->modify( '+' . ( $days - 1 ) . ' days' ), $cf->modify( 'last day of this month' ) ) );
				if ( 'lastmonth' === $range ) {
					$cmp[1] = $cf->modify( 'last day of this month' );
				}
			} elseif ( 'year' === $cal ) {
				$cmp = array( $from->modify( '-1 year' ), $to->modify( '-1 year' ) );
			} else {
				$cmp = array( $from->modify( '-' . $days . ' days' ), $from->modify( '-1 day' ) );
			}
		} else {
			$cmp_mode = 'none';
		}

		$basis  = 'placed' === ( $in['basis'] ?? '' ) ? 'placed' : 'paid';
		$net    = 'net' === ( $in['tax'] ?? '' );
		$method = sanitize_key( (string) ( $in['method'] ?? '' ) );
		$cat    = (int) ( $in['cat'] ?? 0 );
		$in_cat = array();
		if ( $cat ) {
			foreach ( get_posts( array( 'post_type' => Velox_Shop::PRODUCT, 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids', 'tax_query' => array( array( 'taxonomy' => Velox_Shop::CAT, 'field' => 'term_id', 'terms' => $cat, 'include_children' => true ) ) ) ) as $pid ) {
				$in_cat[ (int) $pid ] = true;
			}
		}

		$buckets = function ( $f, $t ) use ( $group ) {
			$out = array();
			$cur = 'month' === $group ? $f->modify( 'first day of this month' ) : ( 'week' === $group ? $f->modify( 'monday this week' ) : $f );
			if ( $cur > $f && 'week' === $group ) {
				$cur = $cur->modify( '-7 days' );
			}
			$step = 'month' === $group ? '+1 month' : ( 'week' === $group ? '+7 days' : '+1 day' );
			while ( $cur <= $t && count( $out ) < 400 ) {
				$next  = $cur->modify( $step );
				$out[] = array( 'start' => max( $cur, $f ), 'end' => min( $next->modify( '-1 day' ), $t ), 'key' => $cur->format( 'Y-m-d' ) );
				$cur   = $next;
			}
			return $out;
		};
		$label = function ( $b ) use ( $group ) {
			if ( 'month' === $group ) {
				return wp_date( 'M Y', $b['start']->getTimestamp() );
			}
			if ( 'week' === $group ) {
				return wp_date( 'j. M', $b['start']->getTimestamp() ) . ' – ' . wp_date( 'j. M', $b['end']->getTimestamp() );
			}
			return wp_date( 'D, j. M Y', $b['start']->getTimestamp() );
		};
		$short = function ( $b ) use ( $group ) {
			return 'month' === $group ? wp_date( 'M', $b['start']->getTimestamp() ) : wp_date( 'j. M', $b['start']->getTimestamp() );
		};

		$cur_b = $buckets( $from, $to );
		$cmp_b = $cmp ? $buckets( $cmp[0], $cmp[1] ) : array();
		$empty = function ( $n ) { return array( 'revenue' => array_fill( 0, $n, 0 ), 'orders' => array_fill( 0, $n, 0 ), 'items' => array_fill( 0, $n, 0 ) ); };
		$cur_s = $empty( count( $cur_b ) );
		$cmp_s = $empty( count( $cmp_b ) );
		$find  = function ( $list, $day ) {
			foreach ( $list as $i => $b ) {
				if ( $day >= $b['start']->format( 'Y-m-d' ) && $day <= $b['end']->format( 'Y-m-d' ) ) {
					return $i;
				}
			}
			return -1;
		};

		// Orders can be paid weeks after they were placed: look a little further back.
		$q_from = min( $from, $cmp ? $cmp[0] : $from )->modify( '-90 days' );
		$ids    = get_posts( array( 'post_type' => Velox_Shop::ORDER, 'post_status' => 'publish', 'numberposts' => 20000, 'fields' => 'ids', 'date_query' => array( array( 'after' => $q_from->format( 'Y-m-d' ), 'inclusive' => true ) ) ) );
		$top      = array();
		$methods  = array();
		$countries = array();
		$refunds  = 0;
		foreach ( $ids as $id ) {
			$o = Velox_Shop::order( $id );
			if ( ! $o || ! empty( $o['legacy'] ) ) {
				continue;
			}
			$pay = $o['payment']['status'];
			if ( 'paid' === $basis ) {
				if ( ! in_array( $pay, array( 'paid', 'partial', 'refunded' ), true ) ) {
					continue;
				}
				$ts = (int) ( $o['payment']['paid_at'] ? $o['payment']['paid_at'] : $o['created'] );
			} else {
				if ( in_array( $pay, array( 'cancelled', 'failed' ), true ) || 'cancelled' === $o['fulfil']['status'] ) {
					continue;
				}
				$ts = (int) $o['created'];
			}
			if ( '' !== $method && $method !== $o['payment']['method'] ) {
				continue;
			}
			$day = wp_date( 'Y-m-d', $ts );
			$ci  = $find( $cur_b, $day );
			$pi  = $cmp_b ? $find( $cmp_b, $day ) : -1;
			if ( $ci < 0 && $pi < 0 ) {
				continue;
			}
			$total    = (float) $o['total'];
			$refunded = (float) $o['payment']['refunded'];
			$taxshare = $total > 0 ? (float) $o['tax'] / $total : 0;
			$value    = 0;
			$qty      = 0;
			$lines    = array();
			foreach ( (array) $o['items'] as $it ) {
				if ( $cat && empty( $in_cat[ (int) ( $it['pid'] ?? 0 ) ] ) ) {
					continue;
				}
				$lt      = (float) ( $it['total'] ?? 0 ) - ( $net ? (float) ( $it['tax'] ?? 0 ) : 0 );
				$lines[] = array( $it, $lt );
				$qty    += (int) ( $it['qty'] ?? 1 );
			}
			if ( $cat ) {
				if ( ! $lines ) {
					continue;
				}
				foreach ( $lines as $l ) {
					$value += $l[1];
				}
				// A refund is spread over the order, so the category carries its share.
				$value -= $total > 0 ? $refunded * ( ( $value / ( $net ? max( 0.01, $total - (float) $o['tax'] ) : $total ) ) ) : 0;
			} else {
				$value = ( $total - $refunded ) * ( $net ? 1 - $taxshare : 1 );
			}
			$value = max( 0, $value );
			if ( $ci >= 0 ) {
				$cur_s['revenue'][ $ci ] += $value;
				$cur_s['orders'][ $ci ]++;
				$cur_s['items'][ $ci ] += $qty;
			}
			if ( $pi >= 0 ) {
				$cmp_s['revenue'][ $pi ] += $value;
				$cmp_s['orders'][ $pi ]++;
				$cmp_s['items'][ $pi ] += $qty;
			}
			if ( $ci < 0 ) {
				continue;
			}
			$refunds += $refunded;
			foreach ( $lines as $l ) {
				$k = (int) ( $l[0]['pid'] ?? 0 ) . ':' . (string) ( $l[0]['name'] ?? '' );
				if ( ! isset( $top[ $k ] ) ) {
					$top[ $k ] = array( 'id' => (int) ( $l[0]['pid'] ?? 0 ), 'name' => (string) ( $l[0]['name'] ?? '' ), 'qty' => 0, 'total' => 0 );
				}
				$top[ $k ]['qty']   += (int) ( $l[0]['qty'] ?? 1 );
				$top[ $k ]['total'] += $l[1];
			}
			$m = (string) $o['payment']['method'];
			$methods[ $m ] = ( $methods[ $m ] ?? 0 ) + $value;
			$cc = (string) ( $o['customer']['address']['country'] ?? '' );
			$countries[ $cc ] = ( $countries[ $cc ] ?? 0 ) + $value;
		}
		$round = function ( $s ) {
			$s['revenue'] = array_map( array( 'Velox_Shop', 'r' ), $s['revenue'] );
			$s['avg']     = array_map( function ( $r, $n ) { return $n ? Velox_Shop::r( $r / $n ) : 0; }, $s['revenue'], $s['orders'] );
			return $s;
		};
		$cur_s = $round( $cur_s );
		$cmp_s = $round( $cmp_s );
		$sum   = function ( $s ) {
			$r = array_sum( $s['revenue'] );
			$n = array_sum( $s['orders'] );
			return array( 'revenue' => Velox_Shop::r( $r ), 'orders' => $n, 'avg' => $n ? Velox_Shop::r( $r / $n ) : 0, 'items' => array_sum( $s['items'] ) );
		};
		usort( $top, function ( $a, $b ) { return $b['total'] <=> $a['total']; } );
		arsort( $methods );
		arsort( $countries );
		$all_m = Velox_Shop_Pay::methods();
		$all_c = Velox_Shop::countries();
		$span  = function ( $f, $t ) {
			$same = $f->format( 'Y' ) === $t->format( 'Y' );
			return wp_date( $same ? 'j. M' : 'j. M Y', $f->getTimestamp() ) . ' – ' . wp_date( 'j. M Y', $t->getTimestamp() );
		};
		$cats = array();
		foreach ( (array) get_terms( array( 'taxonomy' => Velox_Shop::CAT, 'hide_empty' => false ) ) as $t ) {
			if ( $t instanceof WP_Term ) {
				$cats[] = array( 'id' => $t->term_id, 'name' => $t->name );
			}
		}
		return array(
			'range'   => $range,
			'group'   => $group,
			'compare' => $cmp_mode,
			'from'    => $from->format( 'Y-m-d' ),
			'to'      => $to->format( 'Y-m-d' ),
			'span'    => $span( $from, $to ),
			'cmpSpan' => $cmp ? $span( $cmp[0], $cmp[1] ) : '',
			'labels'  => array_map( $label, $cur_b ),
			'short'   => array_map( $short, $cur_b ),
			'cmpLabels' => array_map( $label, $cmp_b ),
			'cur'     => $cur_s,
			'cmp'     => $cmp_s,
			'total'   => $sum( $cur_s ),
			'cmpTotal' => $cmp ? $sum( $cmp_s ) : null,
			'refunds' => Velox_Shop::r( $refunds ),
			'top'     => array_slice( array_map( function ( $p ) { $p['total'] = Velox_Shop::r( $p['total'] ); return $p; }, $top ), 0, 10 ),
			'methods' => array_map( function ( $k, $v ) use ( $all_m ) { return array( 'key' => $k, 'name' => isset( $all_m[ $k ] ) ? (string) ( is_array( $all_m[ $k ] ) ? reset( $all_m[ $k ] ) : $all_m[ $k ] ) : $k, 'total' => Velox_Shop::r( $v ) ); }, array_keys( $methods ), array_values( $methods ) ),
			'countries' => array_slice( array_map( function ( $k, $v ) use ( $all_c ) { return array( 'key' => $k, 'name' => $all_c[ $k ] ?? ( '' === $k ? '—' : $k ), 'total' => Velox_Shop::r( $v ) ); }, array_keys( $countries ), array_values( $countries ) ), 0, 8 ),
			'cats'    => $cats,
		);
	}

	/* ---- customers */

	private static function do_customers( $in ) {
		$by  = array();
		$uid = array(); // user id => row key
		// Customer accounts first, so orders can join them by account.
		$users = get_users( array( 'role' => Velox_Shop_Account::ROLE, 'number' => 3000 ) );
		foreach ( $users as $u ) {
			$k          = strtolower( $u->user_email );
			$by[ $k ]   = self::customer_row( $u->user_email, trim( $u->first_name . ' ' . $u->last_name ), $u );
			$uid[ $u->ID ] = $k;
		}
		$ids = get_posts( array( 'post_type' => Velox_Shop::ORDER, 'post_status' => 'publish', 'numberposts' => 3000, 'fields' => 'ids' ) );
		foreach ( $ids as $id ) {
			$o = Velox_Shop::order( $id );
			if ( ! $o || '' === $o['customer']['email'] ) {
				continue;
			}
			$user = (int) $o['user'];
			$k    = $user && isset( $uid[ $user ] ) ? $uid[ $user ] : strtolower( $o['customer']['email'] );
			if ( ! isset( $by[ $k ] ) ) {
				$acc = $user ? get_userdata( $user ) : false;
				if ( ! $acc ) {
					$found = email_exists( $o['customer']['email'] );
					$acc   = $found ? get_userdata( $found ) : false;
				}
				$by[ $k ] = self::customer_row( $o['customer']['email'], trim( $o['customer']['first'] . ' ' . $o['customer']['last'] ), $acc ? $acc : null );
				if ( $acc ) {
					$uid[ $acc->ID ] = $k;
				}
			}
			$r = &$by[ $k ];
			$r['orders']++;
			if ( in_array( $o['payment']['status'], array( 'paid', 'partial' ), true ) ) {
				$r['spent'] += $o['total'] - (float) $o['payment']['refunded'];
			}
			if ( '' === $r['name'] ) {
				$r['name'] = trim( $o['customer']['first'] . ' ' . $o['customer']['last'] );
			}
			$r['last'] = max( $r['last'], (int) $o['created'] );
			unset( $r );
		}
		$rows   = array_values( $by );
		$counts = array( 'all' => count( $rows ), 'account' => 0, 'guest' => 0, 'locked' => 0 );
		foreach ( $rows as $r ) {
			$counts[ $r['user'] ? 'account' : 'guest' ]++;
			if ( $r['locked'] ) {
				$counts['locked']++;
			}
		}
		$kind = sanitize_key( (string) ( $in['kind'] ?? '' ) );
		if ( in_array( $kind, array( 'account', 'guest', 'locked' ), true ) ) {
			$rows = array_values( array_filter( $rows, function ( $r ) use ( $kind ) {
				return 'locked' === $kind ? $r['locked'] : ( 'account' === $kind ? $r['user'] > 0 : 0 === $r['user'] );
			} ) );
		}
		$s = strtolower( trim( (string) ( $in['q'] ?? '' ) ) );
		if ( '' !== $s ) {
			$rows = array_values( array_filter( $rows, function ( $r ) use ( $s ) { return false !== strpos( strtolower( $r['email'] . ' ' . $r['name'] ), $s ); } ) );
		}
		usort( $rows, function ( $a, $b ) { return max( $b['last'], $b['registered'] ) <=> max( $a['last'], $a['registered'] ); } );
		return array( 'rows' => array_slice( $rows, 0, 500 ), 'counts' => $counts );
	}

	private static function customer_row( $email, $name, $u = null ) {
		return array(
			'key'        => $u ? 'u' . $u->ID : $email,
			'email'      => $u ? $u->user_email : $email,
			'name'       => $u && trim( $u->first_name . ' ' . $u->last_name ) ? trim( $u->first_name . ' ' . $u->last_name ) : $name,
			'user'       => $u ? (int) $u->ID : 0,
			'staff'      => $u && user_can( $u, 'edit_posts' ),
			'registered' => $u ? (int) strtotime( $u->user_registered . ' UTC' ) : 0,
			'login'      => $u ? (int) get_user_meta( $u->ID, 'velox_last_login', true ) : 0,
			'locked'     => $u ? (bool) get_user_meta( $u->ID, Velox_Shop_Account::BLOCK, true ) : false,
			'orders'     => 0,
			'spent'      => 0,
			'last'       => 0,
		);
	}

	/** "u12" is an account, anything else a guest's email. A guest who has since made an account gets the account. */
	private static function find_customer( $key ) {
		$key = trim( (string) $key );
		if ( preg_match( '/^u(\d+)$/', $key, $m ) ) {
			$u = get_userdata( (int) $m[1] );
			return $u ? array( $u, $u->user_email ) : array( null, '' );
		}
		$email = sanitize_email( $key );
		if ( ! is_email( $email ) ) {
			return array( null, '' );
		}
		$id = email_exists( $email );
		return array( $id ? get_userdata( $id ) : null, $email );
	}

	private static function do_customer( $in ) {
		list( $u, $email ) = self::find_customer( $in['id'] ?? '' );
		if ( '' === $email ) {
			return new WP_Error( 'x', __( 'This customer does not exist any more.', 'velox' ) );
		}
		$ids = $u ? Velox_Shop_Account::order_ids( $u ) : get_posts( array( 'post_type' => Velox_Shop::ORDER, 'post_status' => 'publish', 'numberposts' => 500, 'fields' => 'ids', 'meta_key' => '_vx_email', 'meta_value' => $email ) );
		$orders = array();
		$spent  = 0;
		$latest = null;
		foreach ( $ids as $id ) {
			$o = Velox_Shop::order( $id );
			if ( ! $o ) {
				continue;
			}
			$latest   = $latest && $latest['created'] >= $o['created'] ? $latest : $o;
			$orders[] = array( 'id' => (int) $id, 'number' => $o['number'], 'created' => (int) $o['created'], 'total' => $o['total'], 'pay' => $o['payment']['status'], 'ful' => $o['fulfil']['status'], 'items' => count( (array) $o['items'] ) );
			if ( in_array( $o['payment']['status'], array( 'paid', 'partial' ), true ) ) {
				$spent += $o['total'] - (float) $o['payment']['refunded'];
			}
		}
		usort( $orders, function ( $a, $b ) { return $b['created'] <=> $a['created']; } );
		$addr = array();
		if ( $u ) {
			$addr = array_filter( (array) get_user_meta( $u->ID, Velox_Shop_Account::META, true ) );
		}
		if ( ! $addr && $latest ) {
			$c    = $latest['customer'];
			$addr = array( 'vx_first' => $c['first'], 'vx_last' => $c['last'], 'vx_company' => $c['company'], 'vx_phone' => $c['phone'], 'vx_line1' => $c['address']['line1'], 'vx_line2' => $c['address']['line2'], 'vx_zip' => $c['address']['zip'], 'vx_city' => $c['address']['city'], 'vx_country' => $c['address']['country'] );
		}
		$first = $u ? $u->first_name : ( $addr['vx_first'] ?? '' );
		$last  = $u ? $u->last_name : ( $addr['vx_last'] ?? '' );
		return array(
			'key'        => $u ? 'u' . $u->ID : $email,
			'account'    => (bool) $u,
			'staff'      => $u && user_can( $u, 'edit_posts' ),
			'canEdit'    => ! $u || current_user_can( 'edit_user', $u->ID ),
			'email'      => $u ? $u->user_email : $email,
			'first'      => (string) $first,
			'last'       => (string) $last,
			'registered' => $u ? (int) strtotime( $u->user_registered . ' UTC' ) : 0,
			'login'      => $u ? (int) get_user_meta( $u->ID, 'velox_last_login', true ) : 0,
			'locked'     => $u ? (bool) get_user_meta( $u->ID, Velox_Shop_Account::BLOCK, true ) : false,
			'privacy'    => $u ? (int) get_user_meta( $u->ID, 'velox_shop_privacy', true ) : 0,
			'twofa'      => $u ? Velox_Shop_Account::twofa_info( $u->ID ) : null,
			'address'    => (object) $addr,
			'orders'     => $orders,
			'spent'      => Velox_Shop::r( $spent ),
			'accountUrl' => Velox_Shop_Account::url(),
		);
	}

	private static function do_customer_action( $in ) {
		list( $u, $email ) = self::find_customer( $in['id'] ?? '' );
		if ( '' === $email ) {
			return new WP_Error( 'x', __( 'This customer does not exist any more.', 'velox' ) );
		}
		$act = sanitize_key( (string) ( $in['act'] ?? '' ) );
		// Account changes need the right to edit that user (never an admin from the shop by a lesser role).
		if ( $u && 'message' !== $act && 'export' !== $act && ! current_user_can( 'edit_user', $u->ID ) ) {
			return new WP_Error( 'x', __( 'You are not allowed to change this account.', 'velox' ) );
		}
		$staff = $u && user_can( $u, 'edit_posts' );
		$msg   = '';
		switch ( $act ) {
			case 'save':
				if ( ! $u ) {
					return new WP_Error( 'x', __( 'Guests have no account to change. Edit the details in the order, or create an account.', 'velox' ) );
				}
				$new = sanitize_email( (string) ( $in['email'] ?? '' ) );
				if ( ! is_email( $new ) ) {
					return new WP_Error( 'x', __( 'Please enter a valid email address.', 'velox' ) );
				}
				$other = email_exists( $new );
				if ( $other && (int) $other !== (int) $u->ID ) {
					return new WP_Error( 'x', __( 'This email address belongs to another account.', 'velox' ) );
				}
				$first = mb_substr( sanitize_text_field( (string) ( $in['first'] ?? '' ) ), 0, 60 );
				$last  = mb_substr( sanitize_text_field( (string) ( $in['last'] ?? '' ) ), 0, 60 );
				$r = wp_update_user( array( 'ID' => $u->ID, 'user_email' => $new, 'first_name' => $first, 'last_name' => $last, 'display_name' => trim( $first . ' ' . $last ) ? trim( $first . ' ' . $last ) : $u->user_login ) );
				if ( is_wp_error( $r ) ) {
					return $r;
				}
				if ( isset( $in['address'] ) && is_array( $in['address'] ) ) {
					Velox_Shop_Account::save_address( $u->ID, wp_slash( array_merge( (array) $in['address'], array( 'vx_first' => $first, 'vx_last' => $last ) ) ) );
				}
				$msg = __( 'Saved.', 'velox' );
				break;
			case 'reset':
				if ( ! $u ) {
					return new WP_Error( 'x', __( 'This customer has no account yet.', 'velox' ) );
				}
				$r = Velox_Shop_Account::send_reset( $u );
				if ( is_wp_error( $r ) || ! $r ) {
					return new WP_Error( 'x', __( 'The email could not be sent. Please check the mail settings.', 'velox' ) );
				}
				/* translators: %s: email address */
				$msg = sprintf( __( 'A link to set a new password has been sent to %s.', 'velox' ), $u->user_email );
				break;
			case 'invite':
				if ( $u ) {
					return new WP_Error( 'x', __( 'This customer already has an account.', 'velox' ) );
				}
				$d  = self::do_customer( array( 'id' => $email ) );
				$a  = (array) $d['address'];
				$id = Velox_Shop_Account::create( $email, wp_generate_password( 32, true, true ), (string) $d['first'], (string) $d['last'], false );
				if ( ! is_int( $id ) ) {
					return new WP_Error( 'x', __( 'The account could not be created. Please try again.', 'velox' ) );
				}
				Velox_Shop_Account::save_address( $id, wp_slash( $a ) );
				foreach ( $d['orders'] as $row ) {
					$o = Velox_Shop::order( $row['id'] );
					if ( $o && ! (int) $o['user'] ) {
						$o['user'] = $id;
						Velox_Shop::save_order( $row['id'], $o );
					}
				}
				$u = get_userdata( $id );
				if ( ! empty( $in['tell'] ) ) {
					Velox_Shop_Account::send_reset( $u, true );
					$msg = __( 'Account created. The customer got an email to choose a password.', 'velox' );
				} else {
					$msg = __( 'Account created. Send a password link when the customer should sign in.', 'velox' );
				}
				break;
			case 'message':
				$subject = mb_substr( sanitize_text_field( (string) ( $in['subject'] ?? '' ) ), 0, 150 );
				$text    = sanitize_textarea_field( (string) ( $in['text'] ?? '' ) );
				if ( '' === $subject || '' === $text ) {
					return new WP_Error( 'x', __( 'Write a subject and a message first.', 'velox' ) );
				}
				$to   = $u ? $u->user_email : $email;
				$body = '<p style="margin:0 0 16px">' . nl2br( esc_html( $text ) ) . '</p>' . ( $u ? Velox_Shop::mail_button( Velox_Shop_Account::url(), __( 'Open my account', 'velox' ) ) : '' );
				$from = (string) Velox_Shop::get( 'store_email', '' );
				$head = array( 'Content-Type: text/html; charset=UTF-8' );
				if ( is_email( $from ) ) {
					$head[] = 'Reply-To: ' . $from;
				}
				if ( ! wp_mail( $to, $subject, Velox_Shop::mail_layout( $body, $subject ), $head ) ) {
					return new WP_Error( 'x', __( 'The email could not be sent. Please check the mail settings.', 'velox' ) );
				}
				if ( $u ) {
					$log   = (array) get_user_meta( $u->ID, 'velox_shop_msgs', true );
					$log[] = array( 't' => time(), 's' => $subject, 'by' => wp_get_current_user()->display_name );
					update_user_meta( $u->ID, 'velox_shop_msgs', array_slice( array_filter( $log ), -50 ) );
				}
				/* translators: %s: email address */
				$msg = sprintf( __( 'Sent to %s.', 'velox' ), $to );
				break;
			case 'lock':
			case 'unlock':
				if ( ! $u || $staff ) {
					return new WP_Error( 'x', __( 'Only customer accounts can be locked here.', 'velox' ) );
				}
				if ( 'lock' === $act ) {
					update_user_meta( $u->ID, Velox_Shop_Account::BLOCK, time() );
					WP_Session_Tokens::get_instance( $u->ID )->destroy_all();
					$msg = __( 'Locked. The customer is signed out and cannot sign in until you unlock the account.', 'velox' );
				} else {
					delete_user_meta( $u->ID, Velox_Shop_Account::BLOCK );
					$msg = __( 'Unlocked. The customer can sign in again.', 'velox' );
				}
				break;
			case 'reset2fa':
				if ( ! $u ) {
					return new WP_Error( 'x', __( 'This customer has no account yet.', 'velox' ) );
				}
				Velox_Shop_Account::reset_2fa( $u->ID );
				WP_Session_Tokens::get_instance( $u->ID )->destroy_all();
				$msg = __( 'Two-factor sign-in is off for this customer. They sign in with their password and can set it up again.', 'velox' );
				break;
			case 'signout':
				if ( ! $u ) {
					return new WP_Error( 'x', __( 'This customer has no account yet.', 'velox' ) );
				}
				WP_Session_Tokens::get_instance( $u->ID )->destroy_all();
				$msg = __( 'Signed out on every device.', 'velox' );
				break;
			case 'export':
				if ( ! $u ) {
					return new WP_Error( 'x', __( 'This customer has no account yet.', 'velox' ) );
				}
				return array( 'export' => Velox_Shop_Account::export_data( $u ), 'file' => 'kundendaten-' . sanitize_file_name( $u->user_login ) . '-' . gmdate( 'Y-m-d' ) . '.json' );
			case 'delete':
				if ( ! $u || $staff ) {
					return new WP_Error( 'x', __( 'Only customer accounts can be deleted here.', 'velox' ) );
				}
				Velox_Shop_Account::delete_customer( $u );
				return array( 'deleted' => true, 'msg' => __( 'Account deleted. The orders are kept.', 'velox' ) );
			default:
				return new WP_Error( 'x', 'Unknown action' );
		}
		$res        = self::do_customer( array( 'id' => $u ? 'u' . $u->ID : $email ) );
		$res['msg'] = $msg;
		return $res;
	}

	/* ---- settings */

	private static function do_settings_save( $in ) {
		$d   = Velox_Shop::defaults();
		$out = array();
		foreach ( (array) ( $in['settings'] ?? array() ) as $k => $v ) {
			if ( ! array_key_exists( $k, $d ) ) {
				continue;
			}
			if ( in_array( $k, array( 'stripe_secret', 'stripe_whsec', 'paypal_secret' ), true ) && self::MASK === $v ) {
				continue; // unchanged secret
			}
			if ( is_array( $d[ $k ] ) ) {
				$out[ $k ] = self::clean_deep( $k, (array) $v );
			} elseif ( is_int( $d[ $k ] ) ) {
				$out[ $k ] = (int) $v;
			} elseif ( in_array( $k, array( 'store_address', 'small_note', 'mail_footer', 'pickup_address', 'usp', 'notice_text' ), true ) ) {
				$out[ $k ] = sanitize_textarea_field( (string) $v );
			} elseif ( 'accent' === $k ) {
				$out[ $k ] = sanitize_hex_color( (string) $v ) ?: '#0a6f9f';
			} elseif ( 'custom_css' === $k ) {
				$out[ $k ] = self::clean_css( (string) $v );
			} elseif ( 'design' === $k ) {
				$v         = Velox_Shop::OLD_PRESETS[ (string) $v ] ?? (string) $v;
				$out[ $k ] = isset( Velox_Shop::presets()[ $v ] ) ? $v : 'velox';
			} elseif ( in_array( $k, array( 'd_ink', 'd_surface', 'd_line', 'd_soft', 'd_bg', 'd_muted', 'd_accent', 'd_accent_ink', 'd_sale' ), true ) ) {
				$out[ $k ] = (string) ( sanitize_hex_color( (string) $v ) ?: '' );
			} elseif ( in_array( $k, array( 'store_email', 'mail_from', 'mail_admin' ), true ) ) {
				$out[ $k ] = sanitize_email( (string) $v );
			} elseif ( 'notice_link' === $k ) {
				$out[ $k ] = esc_url_raw( (string) $v );
			} elseif ( 0 === strpos( $k, 'd_font' ) ) {
				$out[ $k ] = sanitize_key( (string) $v );
			} else {
				$out[ $k ] = sanitize_text_field( (string) $v );
			}
		}
		if ( isset( $out['stripe_secret'] ) && '' !== $out['stripe_secret'] && ! preg_match( '/^(sk|rk)_(test|live)_[A-Za-z0-9]+$/', $out['stripe_secret'] ) ) {
			return new WP_Error( 'x', __( 'That is not a Stripe secret key (it starts with sk_test_ or sk_live_).', 'velox' ) );
		}
		// Only a changed IBAN is checked, so an old entry never blocks saving another tab.
		if ( isset( $out['bank_iban'] ) && '' !== $out['bank_iban'] && $out['bank_iban'] !== (string) Velox_Shop::get( 'bank_iban', '' ) && ! self::iban_ok( $out['bank_iban'] ) ) {
			return new WP_Error( 'x', __( 'This IBAN does not look right — please check it.', 'velox' ) );
		}
		Velox_Shop::save_settings( $out );
		// Fonts the design uses are fetched once and served from this site.
		if ( isset( $out['design'] ) || isset( $out['d_font_h'] ) || isset( $out['d_font_b'] ) ) {
			Velox_Shop::fonts_url( null, true );
		}
		return array( 'settings' => self::settings_out() );
	}

	/** The shop's own CSS: no tags, nothing that could close the style block. */
	private static function clean_css( $css ) {
		$css = preg_replace( '#</?[a-z][^>]*>#i', '', (string) $css );
		$css = str_ireplace( array( '</style', '<script' ), '', $css );
		return mb_substr( $css, 0, 60000 );
	}

	/**
	 * Settings → Design: the real product grid and cart button, drawn with
	 * the choices on screen (not saved yet), as a small standalone page.
	 */
	/** Settings → Design → "Open a test page": the unsaved choices, on the real shop, for 30 minutes. */
	private static function do_design_try( $in ) {
		$keys = array( 'design', 'card_ratio', 'custom_css' );
		foreach ( Velox_Shop::DESIGN_KEYS as $k ) {
			$keys[] = 'd_' . $k;
		}
		$vals = array();
		foreach ( (array) ( $in['settings'] ?? array() ) as $k => $v ) {
			if ( in_array( $k, $keys, true ) && is_scalar( $v ) ) {
				$vals[ $k ] = 'custom_css' === $k ? self::clean_css( (string) $v ) : sanitize_text_field( (string) $v );
			}
		}
		$token = strtolower( wp_generate_password( 16, false, false ) );
		set_transient( 'velox_shop_try_' . $token, $vals, 30 * MINUTE_IN_SECONDS );
		// The test page shows the template's real fonts: fetch them now if needed.
		Velox_Shop::override( $vals );
		Velox_Shop::fonts_url( Velox_Shop::design(), true );
		$where = (string) ( $in['where'] ?? 'shop' );
		$page  = (int) Velox_Shop::get( 'page_' . ( in_array( $where, array( 'shop', 'cart', 'checkout', 'account' ), true ) ? $where : 'shop' ), 0 );
		$url   = $page ? get_permalink( $page ) : home_url( '/' );
		if ( 'product' === $where ) {
			$first = get_posts( array( 'post_type' => Velox_Shop::PRODUCT, 'post_status' => 'publish', 'posts_per_page' => 1, 'fields' => 'ids' ) );
			$url   = $first ? get_permalink( (int) $first[0] ) : $url;
		}
		return array( 'url' => add_query_arg( 'vxs_try', $token, $url ) );
	}

	/**
	 * Settings → Design → "… in Velox Builder": a shop page made of a
	 * shortcode becomes a builder page holding the same shop part as an
	 * element, so it can be placed, framed and styled by hand. The page keeps
	 * showing the old way until it is published in the builder.
	 */
	private static function do_page_builder( $in ) {
		$key = (string) ( $in['key'] ?? '' );
		$els = array(
			'shop'     => array( 'Shopgrid', '.shop-grid', array( 'count' => '24', 'filter' => '1' ) ),
			'cart'     => array( 'Shopcart', '.shop-cart', array() ),
			'checkout' => array( 'Shopcheckout', '.shop-checkout', array() ),
			'account'  => array( 'Shopaccount', '.shop-account', array() ),
			'withdraw' => array( 'Shopwithdraw', '.shop-withdraw', array() ),
		);
		if ( ! isset( $els[ $key ] ) ) {
			return new WP_Error( 'x', __( 'Unknown page.', 'velox' ) );
		}
		if ( ! class_exists( 'Velox_Builder' ) || ! Velox_Builder::is_enabled() ) {
			return new WP_Error( 'x', __( 'Switch on Velox Builder first (Velox → Modules).', 'velox' ) );
		}
		$page = (int) Velox_Shop::get( 'page_' . $key, 0 );
		if ( ! $page || ! get_post( $page ) ) {
			return new WP_Error( 'x', __( 'This page does not exist yet. Create it under Pages & legal.', 'velox' ) );
		}
		$doc = (int) Velox_Builder::doc_id_for_post( $page );
		if ( ! $doc ) {
			global $wpdb;
			$rid  = function ( $base ) {
				return $base . '-' . strtolower( wp_generate_password( 6, false, false ) );
			};
			$e    = $els[ $key ];
			$part = array( 'id' => $rid( strtolower( $e[0] ) ), 'el' => $e[0], 'tag' => 'div', 'classes' => array( $e[1] ), 'overrides' => new stdClass(), 'children' => array() );
			if ( $e[2] ) {
				$part['settings'] = $e[2];
			}
			$model = array(
				'tree'    => array( array( 'id' => $rid( 'section' ), 'el' => 'Section', 'tag' => 'section', 'classes' => array( '.section' ), 'overrides' => new stdClass(), 'children' => array( $part ) ) ),
				'content' => new stdClass(),
				'classes' => array(
					'.section' => array( 'base' => array( 'paddingTop' => '48', 'paddingBottom' => '48', 'paddingLeft' => '32', 'paddingRight' => '32' ) ),
					$e[1]      => array( 'base' => new stdClass() ),
				),
			);
			$now = current_time( 'mysql' );
			$wpdb->insert( Velox_Builder::table(), array( 'kind' => 'page', 'title' => get_the_title( $page ), 'data' => wp_json_encode( $model ), 'css_size' => 0, 'status' => 'draft', 'post_id' => $page, 'updated' => $now, 'created' => $now ), array( '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s' ) );
			$doc = (int) $wpdb->insert_id;
			if ( ! $doc ) {
				return new WP_Error( 'x', __( 'Could not save.', 'velox' ) );
			}
			update_post_meta( $page, '_velox_builder_doc', $doc );
		}
		return array( 'url' => Velox_Builder::edit_url( $doc ) );
	}

	/** Settings → Texts: every text customers see. */
	private static function do_texts( $in ) {
		return array( 'texts' => Velox_Shop_Texts::catalog(), 'areas' => Velox_Shop_Texts::areas() );
	}

	private static function do_texts_save( $in ) {
		$ok = Velox_Shop_Texts::save( (array) ( $in['changes'] ?? array() ) );
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		return array( 'texts' => Velox_Shop_Texts::catalog() );
	}

	private static function do_design_preview( $in ) {
		$keys = array( 'design', 'card_ratio', 'accent', 'custom_css' );
		foreach ( Velox_Shop::DESIGN_KEYS as $k ) {
			$keys[] = 'd_' . $k;
		}
		$vals = array();
		foreach ( (array) ( $in['settings'] ?? array() ) as $k => $v ) {
			if ( in_array( $k, $keys, true ) && is_scalar( $v ) ) {
				$vals[ $k ] = 'custom_css' === $k ? self::clean_css( (string) $v ) : sanitize_text_field( (string) $v );
			}
		}
		$vals['buy_bar'] = '0';
		Velox_Shop::override( $vals );
		$d    = Velox_Shop::design();
		$mini = ! empty( $in['mini'] );
		// Which page to draw: the shop page, a product page, cart and checkout, or
		// the customer account. Without one: the grid and a product (the tiles).
		$which = sanitize_key( (string) ( $in['page'] ?? '' ) );
		$which = in_array( $which, array( 'shop', 'product', 'cart', 'account' ), true ) && ! $mini ? $which : '';
		// Products that can be bought: a sold-out one makes a poor example and
		// would drop out of the sample cart.
		$first = array();
		$cart  = array();
		if ( ! $mini && 'shop' !== $which && 'account' !== $which ) {
			$pool = get_posts( array( 'post_type' => Velox_Shop::PRODUCT, 'post_status' => 'publish', 'posts_per_page' => 24, 'fields' => 'ids', 'orderby' => array( 'menu_order' => 'ASC', 'date' => 'DESC' ) ) );
			foreach ( $pool as $pid ) {
				$p = Velox_Shop::product( (int) $pid );
				if ( ! $p ) {
					continue;
				}
				foreach ( ! empty( $p['variants'] ) ? $p['variants'] : array( array( 'id' => '' ) ) as $vv ) {
					$vid = (string) ( $vv['id'] ?? '' );
					if ( Velox_Shop::can_buy( $p, Velox_Shop::variant( $p, $vid ), 1 ) ) {
						$first[] = (int) $pid;
						$cart[ (int) $pid . ( '' !== $vid ? ':' . $vid : '' ) ] = 1;
						break;
					}
				}
				if ( count( $first ) >= 2 ) {
					break;
				}
			}
			if ( ! $first && $pool ) {
				$first = array( (int) $pool[0] );
			}
		}
		$grid = '';
		$page = '';
		if ( '' === $which || 'shop' === $which ) {
			$grid = Velox_Shop_Front::grid( array( 'count' => $mini ? 4 : 8, 'columns' => '', 'category' => '', 'sort' => $mini ? '' : 'menu', 'filter' => $mini ? '' : '1', 'ids' => '' ) );
		}
		if ( ( '' === $which || 'product' === $which ) && $first ) {
			// The first product as its product page, so photos, buy box and type show too.
			$page = Velox_Shop_Front::product_layout( Velox_Shop::product( (int) $first[0] ), '' );
		}
		if ( 'cart' === $which ) {
			// A sample cart with the first products: the cart, then the checkout.
			Velox_Shop::preview_cart( $cart );
			$page = $cart ? '<h2 class="vsa-pv-h">' . esc_html__( 'Cart', 'velox' ) . '</h2>' . Velox_Shop_Front::sc_cart() . '<div class="vsa-pv-sep"></div><h2 class="vsa-pv-h">' . esc_html__( 'Checkout', 'velox' ) . '</h2>' . Velox_Shop_Front::sc_checkout()
				: '<p>' . esc_html__( 'Add a product to see the cart here.', 'velox' ) . '</p>';
			Velox_Shop::preview_cart( null );
		}
		if ( 'account' === $which && class_exists( 'Velox_Shop_Account' ) ) {
			// Your own account, as a customer sees theirs.
			$page = Velox_Shop_Account::render();
		}
		// Fonts are fetched once and kept on this site; the preview shows the real ones.
		$fonts = Velox_Shop::fonts_url( $d, true );
		$geist = in_array( 'geist', array( $d['font_h'], $d['font_b'] ), true ) ? '@font-face{font-family:"Geist";font-weight:100 900;font-display:swap;src:url(' . esc_url( VELOX_URL . 'assets/fonts/geist-latin-wght-normal.woff2' ) . ') format("woff2")}' : '';
		$btn  = Velox_Shop_Front::sc_cart_button( array( 'label' => __( 'Cart', 'velox' ) ) );
		$own  = (string) Velox_Shop::get( 'custom_css', '' );
		$bg   = preg_match( '/^#[0-9a-f]{3,8}$/i', (string) $d['bg'] ) ? $d['bg'] : '#ffffff';
		$html = '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><base target="_blank">'
			. '<link rel="stylesheet" href="' . esc_url( VELOX_URL . 'assets/css/velox-shop.css?ver=' . VELOX_VERSION ) . '">'
			. ( '' !== $fonts ? '<link rel="stylesheet" href="' . esc_url( set_url_scheme( $fonts ) ) . '">' : '' )
			. '<style>' . $geist . ':root{' . Velox_Shop::design_vars( $d ) . '}body{margin:0;padding:28px;font-family:var(--vxs-font,system-ui,-apple-system,"Segoe UI",Roboto,sans-serif);background:' . $bg . ';color:var(--vxs-ink,#111)}'
			. 'form,button,a{pointer-events:none}.vsa-pv-top{display:flex;align-items:center;justify-content:space-between;gap:16px;margin:0 0 28px;padding:0 0 18px;border-bottom:1px solid var(--vxs-line,#e5e5e5);font-family:var(--vxs-font-h,inherit);font-weight:var(--vxs-h-weight,700);text-transform:var(--vxs-h-case,none);letter-spacing:var(--vxs-h-track,0);font-size:20px}'
			. '.vsa-pv-sep{height:1px;margin:56px 0;background:var(--vxs-line,#e5e5e5)}.vsa-pv-page .vxs-product-more .vxs-more{display:none}'
			. '.vsa-pv-h{margin:0 0 20px;font-family:var(--vxs-font-h,inherit);font-weight:var(--vxs-h-weight,700);text-transform:var(--vxs-h-case,none);letter-spacing:var(--vxs-h-track,0);font-size:28px}'
			. str_ireplace( '</style', '', $own ) . '</style></head><body>'
			. '<div class="vsa-pv-top"><span>' . esc_html( (string) Velox_Shop::get( 'store_name', get_bloginfo( 'name' ) ) ) . '</span>' . $btn . '</div>' . $grid
			. ( '' !== $page ? ( '' !== $grid ? '<div class="vsa-pv-sep"></div>' : '' ) . '<div class="vsa-pv-page">' . $page . '</div>' : '' ) . '</body></html>';
		return array( 'html' => $html );
	}

	private static function iban_ok( $iban ) {
		$i = strtoupper( preg_replace( '/\s+/', '', $iban ) );
		if ( ! preg_match( '/^[A-Z]{2}\d{2}[A-Z0-9]{10,30}$/', $i ) ) {
			return false;
		}
		$r = substr( $i, 4 ) . substr( $i, 0, 4 );
		$n = '';
		foreach ( str_split( $r ) as $ch ) {
			$n .= ctype_alpha( $ch ) ? (string) ( ord( $ch ) - 55 ) : $ch;
		}
		$mod = 0;
		foreach ( str_split( $n, 7 ) as $part ) {
			$mod = (int) ( $mod . $part ) % 97;
		}
		return 1 === $mod;
	}

	private static function clean_deep( $key, $v ) {
		if ( 'zones' === $key ) {
			$z = array();
			foreach ( array_slice( $v, 0, 20 ) as $i => $zone ) {
				$rates = array();
				foreach ( array_slice( (array) ( $zone['rates'] ?? array() ), 0, 10 ) as $j => $r ) {
					$rates[] = array(
						'id'         => preg_replace( '/[^a-z0-9]/i', '', (string) ( $r['id'] ?? '' ) ) ?: 'r' . $i . $j . wp_rand( 10, 99 ),
						'type'       => 'flat',
						'name'       => sanitize_text_field( (string) ( $r['name'] ?? '' ) ),
						'cost'       => (string) ( Velox_Shop::num( $r['cost'] ?? '' ) ?? '0' ),
						'free_above' => (string) ( Velox_Shop::num( $r['free_above'] ?? '' ) ?? '' ),
						'days'       => sanitize_text_field( (string) ( $r['days'] ?? '' ) ),
					);
				}
				$z[] = array(
					'id'        => preg_replace( '/[^a-z0-9]/i', '', (string) ( $zone['id'] ?? '' ) ) ?: 'z' . $i . wp_rand( 10, 99 ),
					'name'      => sanitize_text_field( (string) ( $zone['name'] ?? '' ) ),
					'countries' => array_values( array_intersect( array_map( 'strtoupper', (array) ( $zone['countries'] ?? array() ) ), array_keys( Velox_Shop::countries() ) ) ),
					'rates'     => $rates,
				);
			}
			return $z;
		}
		if ( 'ship_countries' === $key ) {
			return array_values( array_intersect( array_map( 'strtoupper', $v ), array_keys( Velox_Shop::countries() ) ) );
		}
		if ( 'pay_order' === $key ) {
			return array_values( array_intersect( array_map( 'sanitize_key', $v ), array_keys( Velox_Shop_Pay::methods() ) ) );
		}
		if ( 'stripe_methods' === $key ) {
			return array_values( array_intersect( array_map( 'sanitize_key', $v ), array_keys( Velox_Shop_Pay::stripe_types() ) ) );
		}
		if ( 'mail_off' === $key ) {
			return array_values( array_intersect( array_map( 'sanitize_key', $v ), array_keys( Velox_Shop::mail_types() ) ) );
		}
		return array_map( 'sanitize_text_field', $v );
	}

	private static function do_pay_test( $in ) {
		$r = Velox_Shop_Pay::test( sanitize_key( (string) ( $in['method'] ?? '' ) ) );
		return is_wp_error( $r ) ? $r : array( 'msg' => $r );
	}

	/* ---- emails */

	private static function sample_order() {
		$ids = get_posts( array( 'post_type' => Velox_Shop::ORDER, 'post_status' => 'publish', 'numberposts' => 1, 'fields' => 'ids', 'meta_key' => '_vxs_key', 'meta_compare' => 'EXISTS' ) );
		if ( $ids ) {
			return Velox_Shop::order( $ids[0] );
		}
		$o = array(
			'id' => 0, 'number' => '1001', 'key' => str_repeat( 'x', 32 ), 'created' => time(),
			'customer' => array( 'email' => 'anna@example.com', 'first' => 'Anna', 'last' => 'Beispiel', 'phone' => '', 'company' => '', 'address' => array( 'line1' => 'Musterstraße 1', 'line2' => '', 'zip' => '10115', 'city' => 'Berlin', 'country' => 'DE' ) ),
			'ship_to' => null, 'note' => '', 'items' => array( array( 'pid' => 0, 'vid' => '', 'name' => __( 'Sample product', 'velox' ), 'variant' => '', 'sku' => '', 'qty' => 2, 'unit' => 24.5, 'total' => 49, 'rate' => 19, 'tax' => 7.82, 'type' => 'physical' ) ),
			'subtotal' => 49, 'discount' => 0, 'coupon' => '', 'shipping' => array( 'id' => 'r1', 'name' => __( 'Standard shipping', 'velox' ), 'cost' => 4.9, 'type' => 'flat' ), 'fee' => 0,
			'tax_lines' => array( '19' => 8.61 ), 'tax' => 8.61, 'total' => 53.9, 'currency' => 'EUR', 'small' => Velox_Shop::small_business() ? 1 : 0,
			'payment' => array( 'method' => 'bank', 'status' => 'pending', 'ref' => '', 'paid_at' => 0, 'refunded' => 0 ),
			'fulfil' => array( 'status' => 'unfulfilled', 'carrier' => 'DHL', 'tracking' => '00340434161234567890', 'at' => time() ),
			'notes' => array(), 'invoice' => null, 'withdrawal' => array( 'at' => time() ), 'user' => 0,
		);
		return $o;
	}

	private static function do_mail_preview( $in ) {
		$type = sanitize_key( (string) ( $in['type'] ?? 'placed' ) );
		list( $subject, $html ) = Velox_Shop::mail_body( $type, self::sample_order() );
		return array( 'subject' => $subject, 'html' => $html );
	}

	private static function do_mail_test( $in ) {
		$type = sanitize_key( (string) ( $in['type'] ?? 'placed' ) );
		list( $subject, $html ) = Velox_Shop::mail_body( $type, self::sample_order() );
		$to = wp_get_current_user()->user_email;
		$ok = wp_mail( $to, '[Test] ' . $subject, $html, array( 'Content-Type: text/html; charset=UTF-8' ) );
		/* translators: %s: email */
		return $ok ? array( 'msg' => sprintf( __( 'Test sent to %s.', 'velox' ), $to ) ) : new WP_Error( 'x', __( 'The email could not be sent. Check Velox Mail.', 'velox' ) );
	}

	/* ---- setup */

	/** Create the pages the shop needs (only the missing ones). */
	private static function do_create_pages( $in ) {
		$want = array(
			'shop'     => array( __( 'Shop', 'velox' ), '[velox_shop columns="3" count="24" filter="1"]' ),
			'cart'     => array( __( 'Cart', 'velox' ), '[velox_cart]' ),
			'checkout' => array( __( 'Checkout', 'velox' ), '[velox_checkout]' ),
			'account'  => array( __( 'My account', 'velox' ), '[velox_account]' ),
			'withdraw' => array( __( 'Withdraw from a purchase', 'velox' ), '[velox_withdraw]' ),
		);
		$set = array();
		$made = array();
		foreach ( $want as $k => $p ) {
			$cur = (int) Velox_Shop::get( 'page_' . $k );
			if ( $cur && 'publish' === get_post_status( $cur ) ) {
				continue;
			}
			$id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $p[0], 'post_content' => $p[1] ) );
			if ( $id && ! is_wp_error( $id ) ) {
				$set[ 'page_' . $k ] = (int) $id;
				$made[] = $p[0];
			}
		}
		if ( ! empty( $in['setup'] ) ) {
			$set['setup_done'] = '1';
		}
		Velox_Shop::save_settings( $set );
		$pages = array();
		foreach ( get_pages( array( 'post_status' => 'publish,draft,private' ) ) as $p ) {
			$pages[] = array( 'id' => (int) $p->ID, 'title' => $p->post_title ? $p->post_title : '#' . $p->ID, 'url' => get_permalink( $p ) );
		}
		return array( 'settings' => self::settings_out(), 'pages' => $pages, 'made' => $made );
	}

	/* ---- export */

	public static function export() {
		if ( ! current_user_can( self::CAP ) || ! check_admin_referer( 'velox_shop_export' ) ) {
			wp_die( '403' );
		}
		$ids = get_posts( array( 'post_type' => Velox_Shop::ORDER, 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids' ) );
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="orders-' . gmdate( 'Y-m-d' ) . '.csv"' );
		$f = fopen( 'php://output', 'w' );
		fwrite( $f, "\xEF\xBB\xBF" );
		fputcsv( $f, array( 'Order', 'Date', 'Invoice', 'Name', 'Email', 'Country', 'Items', 'Subtotal', 'Discount', 'Shipping', 'VAT', 'Total', 'Refunded', 'Payment', 'Payment status', 'Shipping status' ), ';' );
		foreach ( $ids as $id ) {
			$o = Velox_Shop::order( $id );
			if ( ! $o ) {
				continue;
			}
			fputcsv( $f, array(
				$o['number'], wp_date( 'Y-m-d H:i', (int) $o['created'] ), $o['invoice']['number'] ?? '',
				trim( $o['customer']['first'] . ' ' . $o['customer']['last'] ), $o['customer']['email'], $o['customer']['address']['country'],
				implode( ' | ', array_map( function ( $i ) { return $i['qty'] . 'x ' . $i['name'] . ( $i['variant'] ? ' (' . $i['variant'] . ')' : '' ); }, $o['items'] ) ),
				number_format( $o['subtotal'], 2, ',', '' ), number_format( $o['discount'], 2, ',', '' ), number_format( $o['shipping']['cost'] ?? 0, 2, ',', '' ),
				number_format( $o['tax'], 2, ',', '' ), number_format( $o['total'], 2, ',', '' ), number_format( (float) $o['payment']['refunded'], 2, ',', '' ),
				Velox_Shop_Pay::label( $o['payment']['method'] ), $o['payment']['status'], $o['fulfil']['status'],
			), ';' );
		}
		fclose( $f );
		exit;
	}
}
