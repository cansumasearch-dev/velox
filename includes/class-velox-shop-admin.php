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
			'data:image/svg+xml;base64,' . base64_encode( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#a7aaad" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 7h12l-1 12.2a1 1 0 0 1-1 .8H8a1 1 0 0 1-1-.8z"/><path d="M9 7V6a3 3 0 0 1 6 0v1"/></svg>' ),
			100.9
		);
		foreach ( array( '' => __( 'Overview', 'velox' ), 'orders' => __( 'Orders', 'velox' ), 'products' => __( 'Products', 'velox' ), 'coupons' => __( 'Coupons', 'velox' ), 'customers' => __( 'Customers', 'velox' ), 'settings' => __( 'Settings', 'velox' ) ) as $k => $l ) {
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
		return array(
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
				Velox_Shop::mark_shipped( $id, (string) ( $in['carrier'] ?? '' ), (string) ( $in['tracking'] ?? '' ) );
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

	/* ---- customers */

	private static function do_customers( $in ) {
		$by  = array();
		$ids = get_posts( array( 'post_type' => Velox_Shop::ORDER, 'post_status' => 'publish', 'numberposts' => 3000, 'fields' => 'ids' ) );
		foreach ( $ids as $id ) {
			$o = Velox_Shop::order( $id );
			if ( ! $o || '' === $o['customer']['email'] ) {
				continue;
			}
			$k = strtolower( $o['customer']['email'] );
			if ( ! isset( $by[ $k ] ) ) {
				$by[ $k ] = array( 'email' => $o['customer']['email'], 'name' => trim( $o['customer']['first'] . ' ' . $o['customer']['last'] ), 'orders' => 0, 'spent' => 0, 'last' => 0, 'country' => $o['customer']['address']['country'], 'user' => (int) $o['user'] );
			}
			$by[ $k ]['orders']++;
			if ( in_array( $o['payment']['status'], array( 'paid', 'partial' ), true ) ) {
				$by[ $k ]['spent'] += $o['total'] - (float) $o['payment']['refunded'];
			}
			$by[ $k ]['last'] = max( $by[ $k ]['last'], (int) $o['created'] );
		}
		$rows = array_values( $by );
		$s = strtolower( trim( (string) ( $in['q'] ?? '' ) ) );
		if ( '' !== $s ) {
			$rows = array_values( array_filter( $rows, function ( $r ) use ( $s ) { return false !== strpos( strtolower( $r['email'] . ' ' . $r['name'] ), $s ); } ) );
		}
		usort( $rows, function ( $a, $b ) { return $b['last'] <=> $a['last']; } );
		return array( 'rows' => array_slice( $rows, 0, 500 ) );
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
			} elseif ( in_array( $k, array( 'store_address', 'small_note', 'mail_footer', 'pickup_address' ), true ) ) {
				$out[ $k ] = sanitize_textarea_field( (string) $v );
			} elseif ( 'accent' === $k ) {
				$out[ $k ] = sanitize_hex_color( (string) $v ) ?: '#0a6f9f';
			} elseif ( 'custom_css' === $k ) {
				$out[ $k ] = self::clean_css( (string) $v );
			} elseif ( 'design' === $k ) {
				$out[ $k ] = isset( Velox_Shop::presets()[ (string) $v ] ) ? (string) $v : 'velox';
			} elseif ( in_array( $k, array( 'd_ink', 'd_surface', 'd_line', 'd_soft', 'd_bg' ), true ) ) {
				$out[ $k ] = (string) ( sanitize_hex_color( (string) $v ) ?: '' );
			} elseif ( in_array( $k, array( 'store_email', 'mail_from', 'mail_admin' ), true ) ) {
				$out[ $k ] = sanitize_email( (string) $v );
			} else {
				$out[ $k ] = sanitize_text_field( (string) $v );
			}
		}
		if ( isset( $out['stripe_secret'] ) && '' !== $out['stripe_secret'] && ! preg_match( '/^(sk|rk)_(test|live)_[A-Za-z0-9]+$/', $out['stripe_secret'] ) ) {
			return new WP_Error( 'x', __( 'That is not a Stripe secret key (it starts with sk_test_ or sk_live_).', 'velox' ) );
		}
		if ( isset( $out['bank_iban'] ) && '' !== $out['bank_iban'] && ! self::iban_ok( $out['bank_iban'] ) ) {
			return new WP_Error( 'x', __( 'This IBAN does not look right — please check it.', 'velox' ) );
		}
		Velox_Shop::save_settings( $out );
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
		Velox_Shop::override( $vals );
		$d    = Velox_Shop::design();
		$grid = Velox_Shop_Front::grid( array( 'count' => 6, 'columns' => '', 'category' => '', 'sort' => 'menu', 'filter' => '1', 'ids' => '' ) );
		$btn  = Velox_Shop_Front::sc_cart_button( array( 'label' => __( 'Cart', 'velox' ) ) );
		$own  = (string) Velox_Shop::get( 'custom_css', '' );
		$bg   = preg_match( '/^#[0-9a-f]{3,8}$/i', (string) $d['bg'] ) ? $d['bg'] : '#ffffff';
		$html = '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><base target="_blank">'
			. '<link rel="stylesheet" href="' . esc_url( VELOX_URL . 'assets/css/velox-shop.css?ver=' . VELOX_VERSION ) . '">'
			. '<style>:root{' . Velox_Shop::design_vars( $d ) . '}body{margin:0;padding:28px;font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;background:' . $bg . ';color:var(--vxs-ink,#111)}'
			. 'form,button,a{pointer-events:none}.vsa-pv-top{display:flex;align-items:center;justify-content:space-between;gap:16px;margin:0 0 24px;font-weight:700;font-size:18px}'
			. str_ireplace( '</style', '', $own ) . '</style></head><body>'
			. '<div class="vsa-pv-top"><span>' . esc_html( (string) Velox_Shop::get( 'store_name', get_bloginfo( 'name' ) ) ) . '</span>' . $btn . '</div>' . $grid . '</body></html>';
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
