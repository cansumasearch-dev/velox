<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Velox Shop — a small, honest commerce layer.
 *
 * Deliberate boundaries, because getting these wrong is how shops leak data:
 *
 *  - AUTHENTICATION IS NOT REINVENTED. Accounts are WordPress users. Login uses
 *    wp_signon(), registration uses wp_create_user(), and the reset flow is
 *    WordPress's own. Nothing here stores or compares a password itself.
 *  - NO CARD DATA EVER TOUCHES THIS CODE. Checkout records an order and tells
 *    the customer how to pay (bank transfer, or "on request" for commissions).
 *    A real gateway belongs behind a redirect — Stripe Checkout, PayPal — added
 *    later as an adapter. Storing or proxying card numbers here would be
 *    both illegal-adjacent and unnecessary.
 *  - The cart is a signed cookie, not a session. No server state to expire, no
 *    PHP sessions to fight with page caching, and the signature stops a visitor
 *    editing prices client-side — prices are always re-read from the product.
 *
 * @package Velox
 */
class Velox_Shop {

	const PRODUCT   = 'velox_product';
	const ORDER     = 'velox_order';
	const COOKIE    = 'velox_cart';

	/* ------------------------------------------------------------------ boot */

	public static function init() {
		if ( ! Velox_Settings::get( 'util_shop' ) ) {
			return;
		}
		add_action( 'init', array( __CLASS__, 'register_types' ) );
		add_action( 'init', array( __CLASS__, 'register_meta' ) );

		add_shortcode( 'velox_shop', array( __CLASS__, 'sc_catalogue' ) );
		add_shortcode( 'velox_cart', array( __CLASS__, 'sc_cart' ) );
		add_shortcode( 'velox_checkout', array( __CLASS__, 'sc_checkout' ) );
		add_shortcode( 'velox_account', array( __CLASS__, 'sc_account' ) );

		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
		// Not template_redirect: the builder renders its own document on that hook
		// and exits, so a handler there never runs on a builder-built page. init is
		// also the last safe point to set the cart cookie before headers go out.
		add_action( 'init', array( __CLASS__, 'handle_post' ), 20 );
		// Cart, checkout and account output is per-visitor. Even before anything
		// is in the cart, caching those pages would serve one person's view to
		// the next, so they opt out of the page cache by content.
		add_filter( 'velox_cache_skip', array( __CLASS__, 'skip_cache' ) );

		// Product admin columns so the catalogue is usable from wp-admin.
		add_filter( 'manage_' . self::PRODUCT . '_posts_columns', array( __CLASS__, 'product_cols' ) );
		add_action( 'manage_' . self::PRODUCT . '_posts_custom_column', array( __CLASS__, 'product_col' ), 10, 2 );
		add_filter( 'manage_' . self::ORDER . '_posts_columns', array( __CLASS__, 'order_cols' ) );
		add_action( 'manage_' . self::ORDER . '_posts_custom_column', array( __CLASS__, 'order_col' ), 10, 2 );
		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_boxes' ) );
		add_action( 'save_post_' . self::PRODUCT, array( __CLASS__, 'save_product' ), 10, 2 );
	}

	/** True for any page that renders a personal shop view. */
	public static function skip_cache( $skip ) {
		if ( $skip ) {
			return $skip;
		}
		if ( ! empty( $_COOKIE[ self::COOKIE ] ) ) {
			return true;
		}
		$post = get_post();
		if ( $post && has_shortcode( (string) $post->post_content, 'velox_cart' ) ) {
			return true;
		}
		return $skip;
	}

	/* -------------------------------------------------------------- post types */

	public static function register_types() {
		register_post_type( self::PRODUCT, array(
			'labels'       => array(
				'name'          => __( 'Products', 'velox' ),
				'singular_name' => __( 'Product', 'velox' ),
				'add_new_item'  => __( 'Add product', 'velox' ),
				'edit_item'     => __( 'Edit product', 'velox' ),
			),
			'public'       => true,
			'has_archive'  => false,
			'menu_icon'    => 'dashicons-art',
			'show_in_menu' => true,
			// 'custom-fields' is required or registered meta never appears in the
			// REST API, which silently breaks any programmatic import.
			'supports'     => array( 'title', 'editor', 'thumbnail', 'excerpt', 'page-attributes', 'custom-fields' ),
			'rewrite'      => array( 'slug' => 'werk' ),
			'show_in_rest' => true,
		) );

		// Orders are private records, never public, never indexed.
		register_post_type( self::ORDER, array(
			'labels'          => array(
				'name'          => __( 'Orders', 'velox' ),
				'singular_name' => __( 'Order', 'velox' ),
			),
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => 'edit.php?post_type=' . self::PRODUCT,
			'capability_type' => 'post',
			'capabilities'    => array( 'create_posts' => 'do_not_allow' ),
			'map_meta_cap'    => true,
			'supports'        => array( 'title' ),
		) );
	}

	public static function register_meta() {
		$keys = array(
			'_vx_price'    => 'number',   // REST rejects a string here — send a float
			'_vx_sku'      => 'string',
			'_vx_dims'     => 'string',
			'_vx_medium'   => 'string',
			'_vx_year'     => 'string',
			'_vx_status'   => 'string', // available | sold | commission
		);
		foreach ( $keys as $key => $type ) {
			register_post_meta( self::PRODUCT, $key, array(
				'type'          => $type,
				'single'        => true,
				'show_in_rest'  => true,
				'auth_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			) );
		}
	}

	/* ------------------------------------------------------------------ money */

	public static function currency() {
		return (string) Velox_Settings::get( 'shop_currency', '€' );
	}

	/** Format a price for display. Never used as an input to anything. */
	public static function money( $amount ) {
		$n = number_format_i18n( (float) $amount, 2 );
		return 'left' === Velox_Settings::get( 'shop_currency_pos', 'right' )
			? self::currency() . ' ' . $n
			: $n . ' ' . self::currency();
	}

	public static function price( $product_id ) {
		return (float) get_post_meta( (int) $product_id, '_vx_price', true );
	}

	public static function status( $product_id ) {
		$s = (string) get_post_meta( (int) $product_id, '_vx_status', true );
		return in_array( $s, array( 'available', 'sold', 'commission' ), true ) ? $s : 'available';
	}

	/* ------------------------------------------------------------------- cart */

	/**
	 * The cart lives in a signed cookie: { id: qty }. The signature only proves
	 * the browser did not hand-edit it — prices are ALWAYS re-read from the
	 * product on read, so even a forged cookie cannot change what something
	 * costs. Quantity is capped because these are one-off works.
	 */
	private static function sign( $json ) {
		return wp_hash( $json, 'nonce' );
	}

	public static function get_cart() {
		if ( empty( $_COOKIE[ self::COOKIE ] ) ) {
			return array();
		}
		$raw = wp_unslash( $_COOKIE[ self::COOKIE ] );
		$parts = explode( '|', (string) $raw, 2 );
		if ( count( $parts ) !== 2 ) {
			return array();
		}
		list( $json, $sig ) = $parts;
		$json = base64_decode( $json, true );
		if ( false === $json || ! hash_equals( self::sign( $json ), $sig ) ) {
			return array();
		}
		$data = json_decode( $json, true );
		if ( ! is_array( $data ) ) {
			return array();
		}
		$out = array();
		foreach ( $data as $id => $qty ) {
			$id  = (int) $id;
			$qty = max( 1, min( 10, (int) $qty ) );
			if ( $id > 0 && get_post_type( $id ) === self::PRODUCT && 'publish' === get_post_status( $id ) ) {
				$out[ $id ] = $qty;
			}
		}
		return $out;
	}

	public static function save_cart( $cart ) {
		$json = wp_json_encode( $cart );
		$val  = base64_encode( $json ) . '|' . self::sign( $json );
		// Not HttpOnly: the count badge is read client-side. It holds no secrets
		// and no prices — only ids and quantities.
		setcookie( self::COOKIE, $val, time() + WEEK_IN_SECONDS, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), false );
		$_COOKIE[ self::COOKIE ] = $val;
	}

	public static function cart_lines() {
		$lines = array();
		foreach ( self::get_cart() as $id => $qty ) {
			if ( 'available' !== self::status( $id ) ) {
				continue; // sold or commission-only pieces cannot sit in a cart
			}
			$price   = self::price( $id );
			$lines[] = array(
				'id'    => $id,
				'title' => get_the_title( $id ),
				'qty'   => $qty,
				'price' => $price,
				'total' => $price * $qty,
				'thumb' => get_the_post_thumbnail_url( $id, 'medium' ),
				'dims'  => (string) get_post_meta( $id, '_vx_dims', true ),
			);
		}
		return $lines;
	}

	public static function cart_total() {
		$t = 0.0;
		foreach ( self::cart_lines() as $l ) {
			$t += $l['total'];
		}
		return $t;
	}

	public static function cart_count() {
		$n = 0;
		foreach ( self::get_cart() as $qty ) {
			$n += (int) $qty;
		}
		return $n;
	}

	/* --------------------------------------------------------- form handling */

	/**
	 * All shop writes are ordinary POSTs with a nonce, handled before output.
	 * Deliberately not AJAX-first: a cart that works without JavaScript is a
	 * cart that works.
	 */
	public static function handle_post() {
		if ( empty( $_POST['velox_shop_action'] ) ) {
			return;
		}
		$action = sanitize_key( wp_unslash( $_POST['velox_shop_action'] ) );
		if ( ! isset( $_POST['_vxs'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_vxs'] ) ), 'velox_shop' ) ) {
			return;
		}

		if ( 'add' === $action ) {
			$id = isset( $_POST['product'] ) ? (int) $_POST['product'] : 0;
			if ( $id && self::PRODUCT === get_post_type( $id ) && 'available' === self::status( $id ) ) {
				$cart = self::get_cart();
				$cart[ $id ] = min( 10, ( isset( $cart[ $id ] ) ? (int) $cart[ $id ] : 0 ) + 1 );
				self::save_cart( $cart );
			}
			self::redirect_back( 'added' );
		}

		if ( 'remove' === $action ) {
			$id   = isset( $_POST['product'] ) ? (int) $_POST['product'] : 0;
			$cart = self::get_cart();
			unset( $cart[ $id ] );
			self::save_cart( $cart );
			self::redirect_back( 'removed' );
		}

		if ( 'checkout' === $action ) {
			self::place_order();
		}
	}

	private static function redirect_back( $flag ) {
		// wp_get_referer() is unreliable this early and was sending people to the
		// home page after adding to the cart. The form carries where it came from.
		$url = '';
		if ( ! empty( $_POST['vx_return'] ) ) {
			$raw = esc_url_raw( wp_unslash( $_POST['vx_return'] ) );
			if ( $raw && 0 === strpos( $raw, untrailingslashit( home_url() ) ) ) {
				$url = $raw;
			}
		}
		if ( ! $url ) {
			$url = wp_get_referer();
		}
		if ( ! $url ) {
			$url = home_url( '/' );
		}
		wp_safe_redirect( add_query_arg( 'vx', $flag, $url ) );
		exit;
	}

	/* ------------------------------------------------------------------ order */

	private static function place_order() {
		$lines = self::cart_lines();
		if ( ! $lines ) {
			self::redirect_back( 'empty' );
		}
		$name  = sanitize_text_field( wp_unslash( $_POST['vx_name'] ?? '' ) );
		$email = sanitize_email( wp_unslash( $_POST['vx_email'] ?? '' ) );
		$addr  = sanitize_textarea_field( wp_unslash( $_POST['vx_address'] ?? '' ) );
		$note  = sanitize_textarea_field( wp_unslash( $_POST['vx_note'] ?? '' ) );
		if ( '' === $name || ! is_email( $email ) ) {
			self::redirect_back( 'invalid' );
		}

		$total = self::cart_total();
		$ref   = strtoupper( wp_generate_password( 6, false, false ) );
		$oid   = wp_insert_post( array(
			'post_type'   => self::ORDER,
			'post_status' => 'publish',
			'post_title'  => sprintf( 'ORD-%s — %s', $ref, $name ),
		), true );
		if ( is_wp_error( $oid ) ) {
			self::redirect_back( 'error' );
		}
		update_post_meta( $oid, '_vx_ref', $ref );
		update_post_meta( $oid, '_vx_name', $name );
		update_post_meta( $oid, '_vx_email', $email );
		update_post_meta( $oid, '_vx_address', $addr );
		update_post_meta( $oid, '_vx_note', $note );
		update_post_meta( $oid, '_vx_total', $total );
		update_post_meta( $oid, '_vx_state', 'awaiting_payment' );
		update_post_meta( $oid, '_vx_items', wp_json_encode( $lines ) );
		if ( is_user_logged_in() ) {
			update_post_meta( $oid, '_vx_user', get_current_user_id() );
		}

		// Reserve: a one-off work that has been ordered is no longer available.
		foreach ( $lines as $l ) {
			update_post_meta( $l['id'], '_vx_status', 'sold' );
		}

		self::notify( $oid, $lines, $total, $ref, $name, $email );
		self::save_cart( array() );

		// Return to the checkout page itself, which then renders the thank-you
		// state. Taken from the form rather than a stored option so it works
		// wherever the shortcode has been placed.
		$thanks = '';
		if ( ! empty( $_POST['vx_return'] ) ) {
			$raw = esc_url_raw( wp_unslash( $_POST['vx_return'] ) );
			if ( $raw && 0 === strpos( $raw, untrailingslashit( home_url() ) ) ) {
				$thanks = remove_query_arg( array( 'vx', 'ref' ), $raw );
			}
		}
		if ( ! $thanks ) {
			$thanks = home_url( '/' );
		}
		wp_safe_redirect( add_query_arg( array( 'vx' => 'ordered', 'ref' => $ref ), $thanks ) );
		exit;
	}

	private static function notify( $oid, $lines, $total, $ref, $name, $email ) {
		$rows = '';
		foreach ( $lines as $l ) {
			$rows .= '- ' . $l['title'] . ' × ' . $l['qty'] . ' — ' . self::money( $l['total'] ) . "\n";
		}
		$body = sprintf(
			/* translators: 1: customer name 2: reference 3: item lines 4: total */
			__( "Hello %1\$s,\n\nthank you for your order (%2\$s).\n\n%3\$s\nTotal: %4\$s\n\nWe will be in touch with payment details before anything ships.\n", 'velox' ),
			$name, $ref, $rows, self::money( $total )
		);
		wp_mail( $email, sprintf( __( 'Your order %s', 'velox' ), $ref ), $body );
		wp_mail( get_option( 'admin_email' ), sprintf( __( 'New order %s', 'velox' ), $ref ), $body );
	}

	/* ------------------------------------------------------------- shortcodes */

	public static function sc_catalogue( $atts ) {
		$a = shortcode_atts( array( 'count' => 12, 'columns' => 3 ), $atts, 'velox_shop' );
		$q = new WP_Query( array(
			'post_type'      => self::PRODUCT,
			'post_status'    => 'publish',
			'posts_per_page' => (int) $a['count'],
			'no_found_rows'  => true,
		) );
		if ( ! $q->have_posts() ) {
			return '<p class="vxs-empty">' . esc_html__( 'No works are listed yet.', 'velox' ) . '</p>';
		}
		$out = '<div class="vxs-grid" style="--vxs-cols:' . (int) $a['columns'] . '">';
		while ( $q->have_posts() ) {
			$q->the_post();
			$id     = get_the_ID();
			$status = self::status( $id );
			$out   .= '<article class="vxs-card vxs-' . esc_attr( $status ) . '">';
			$thumb  = get_the_post_thumbnail( $id, 'large', array( 'loading' => 'lazy' ) );
			$out   .= '<a class="vxs-im" href="' . esc_url( get_permalink( $id ) ) . '">' . $thumb . '</a>';
			$out   .= '<h3 class="vxs-t"><a href="' . esc_url( get_permalink( $id ) ) . '">' . esc_html( get_the_title() ) . '</a></h3>';
			$dims   = (string) get_post_meta( $id, '_vx_dims', true );
			if ( $dims ) {
				$out .= '<p class="vxs-d">' . esc_html( $dims ) . '</p>';
			}
			$out .= '<p class="vxs-p">' . esc_html( self::money( self::price( $id ) ) ) . '</p>';
			$out .= self::add_button( $id, $status );
			$out .= '</article>';
		}
		wp_reset_postdata();
		return $out . '</div>';
	}

	private static function add_button( $id, $status ) {
		if ( 'sold' === $status ) {
			return '<span class="vxs-state">' . esc_html__( 'Sold', 'velox' ) . '</span>';
		}
		if ( 'commission' === $status ) {
			return '<span class="vxs-state">' . esc_html__( 'On commission', 'velox' ) . '</span>';
		}
		return '<form method="post" class="vxs-add">'
			. wp_nonce_field( 'velox_shop', '_vxs', true, false )
			. '<input type="hidden" name="vx_return" value="' . esc_url( home_url( add_query_arg( array() ) ) ) . '">'
			. '<input type="hidden" name="velox_shop_action" value="add">'
			. '<input type="hidden" name="product" value="' . (int) $id . '">'
			. '<button type="submit" class="vxs-btn">' . esc_html__( 'Add to cart', 'velox' ) . '</button>'
			. '</form>';
	}

	public static function sc_cart( $atts = array(), $content = '', $tag = '', $editable = true ) {
		$lines = self::cart_lines();
		if ( ! $lines ) {
			return '<p class="vxs-empty">' . esc_html__( 'Your cart is empty.', 'velox' ) . '</p>';
		}
		$out = '<ul class="vxs-cart">';
		foreach ( $lines as $l ) {
			$out .= '<li class="vxs-line">';
			if ( $l['thumb'] ) {
				$out .= '<img class="vxs-line-im" src="' . esc_url( $l['thumb'] ) . '" alt="" loading="lazy">';
			}
			$out .= '<div class="vxs-line-b"><strong>' . esc_html( $l['title'] ) . '</strong>';
			if ( $l['dims'] ) {
				$out .= '<span class="vxs-d">' . esc_html( $l['dims'] ) . '</span>';
			}
			$out .= '</div>';
			$out .= '<span class="vxs-line-p">' . esc_html( self::money( $l['total'] ) ) . '</span>';
			// A remove form must not be nested inside the checkout form: nested
			// forms are invalid HTML and the browser attributes the submit to the
			// wrong one, so ordering removed an item instead of checking out.
			if ( $editable ) {
				$out .= '<form method="post" class="vxs-rm">'
					. wp_nonce_field( 'velox_shop', '_vxs', true, false )
					. '<input type="hidden" name="vx_return" value="' . esc_url( home_url( add_query_arg( array() ) ) ) . '">'
					. '<input type="hidden" name="velox_shop_action" value="remove">'
					. '<input type="hidden" name="product" value="' . (int) $l['id'] . '">'
					. '<button type="submit" aria-label="' . esc_attr__( 'Remove', 'velox' ) . '">&times;</button></form>';
			}
			$out .= '</li>';
		}
		$out .= '</ul><p class="vxs-total">' . esc_html__( 'Total', 'velox' ) . ' <strong>'
			. esc_html( self::money( self::cart_total() ) ) . '</strong></p>';
		return $out;
	}

	public static function sc_checkout() {
		if ( isset( $_GET['vx'] ) && 'ordered' === $_GET['vx'] ) {
			$ref = isset( $_GET['ref'] ) ? sanitize_text_field( wp_unslash( $_GET['ref'] ) ) : '';
			return '<div class="vxs-done"><h2>' . esc_html__( 'Thank you — your order is in.', 'velox' ) . '</h2>'
				. '<p>' . sprintf( esc_html__( 'Reference %s. We will email you payment details before anything ships.', 'velox' ), '<strong>' . esc_html( $ref ) . '</strong>' ) . '</p></div>';
		}
		$lines = self::cart_lines();
		if ( ! $lines ) {
			return '<p class="vxs-empty">' . esc_html__( 'Your cart is empty.', 'velox' ) . '</p>';
		}
		$u     = wp_get_current_user();
		$name  = $u && $u->ID ? $u->display_name : '';
		$email = $u && $u->ID ? $u->user_email : '';
		return '<form method="post" class="vxs-checkout">'
			. wp_nonce_field( 'velox_shop', '_vxs', true, false )
			. '<input type="hidden" name="vx_return" value="' . esc_url( home_url( add_query_arg( array() ) ) ) . '">'
			. '<input type="hidden" name="velox_shop_action" value="checkout">'
			. self::sc_cart( array(), '', '', false )
			. '<label>' . esc_html__( 'Name', 'velox' ) . '<input type="text" name="vx_name" required value="' . esc_attr( $name ) . '"></label>'
			. '<label>' . esc_html__( 'Email', 'velox' ) . '<input type="email" name="vx_email" required value="' . esc_attr( $email ) . '"></label>'
			. '<label>' . esc_html__( 'Delivery address', 'velox' ) . '<textarea name="vx_address" rows="3"></textarea></label>'
			. '<label>' . esc_html__( 'Anything we should know?', 'velox' ) . '<textarea name="vx_note" rows="3"></textarea></label>'
			. '<p class="vxs-payinfo">' . esc_html__( 'No card details are taken here. We confirm the order first and send payment details by email.', 'velox' ) . '</p>'
			. '<button type="submit" class="vxs-btn vxs-btn-lg">' . esc_html__( 'Place order', 'velox' ) . '</button>'
			. '</form>';
	}

	/**
	 * Account area. Login, registration and password reset are WordPress's own
	 * functions — this only draws the surface and lists the customer's orders.
	 */
	public static function sc_account() {
		if ( ! is_user_logged_in() ) {
			return '<div class="vxs-account">'
				. wp_login_form( array( 'echo' => false, 'redirect' => get_permalink() ) )
				. '<p class="vxs-alt"><a href="' . esc_url( wp_lostpassword_url( get_permalink() ) ) . '">'
				. esc_html__( 'Forgotten your password?', 'velox' ) . '</a>'
				. ( get_option( 'users_can_register' )
					? ' · <a href="' . esc_url( wp_registration_url() ) . '">' . esc_html__( 'Create an account', 'velox' ) . '</a>'
					: '' )
				. '</p></div>';
		}
		$u  = wp_get_current_user();
		$q  = new WP_Query( array(
			'post_type'      => self::ORDER,
			'post_status'    => 'publish',
			'posts_per_page' => 20,
			'no_found_rows'  => true,
			'meta_key'       => '_vx_user',
			'meta_value'     => (string) $u->ID,
		) );
		$out = '<div class="vxs-account"><p class="vxs-hi">'
			. sprintf( esc_html__( 'Signed in as %s.', 'velox' ), esc_html( $u->display_name ) )
			. ' <a href="' . esc_url( wp_logout_url( get_permalink() ) ) . '">' . esc_html__( 'Sign out', 'velox' ) . '</a></p>';
		if ( ! $q->have_posts() ) {
			$out .= '<p class="vxs-empty">' . esc_html__( 'No orders yet.', 'velox' ) . '</p>';
		} else {
			$out .= '<ul class="vxs-orders">';
			while ( $q->have_posts() ) {
				$q->the_post();
				$oid = get_the_ID();
				$out .= '<li><strong>' . esc_html( (string) get_post_meta( $oid, '_vx_ref', true ) ) . '</strong> · '
					. esc_html( get_the_date() ) . ' · '
					. esc_html( self::money( (float) get_post_meta( $oid, '_vx_total', true ) ) ) . ' · '
					. esc_html( self::state_label( (string) get_post_meta( $oid, '_vx_state', true ) ) ) . '</li>';
			}
			wp_reset_postdata();
			$out .= '</ul>';
		}
		return $out . '</div>';
	}

	public static function state_label( $state ) {
		$map = array(
			'awaiting_payment' => __( 'Awaiting payment', 'velox' ),
			'paid'             => __( 'Paid', 'velox' ),
			'shipped'          => __( 'Shipped', 'velox' ),
			'cancelled'        => __( 'Cancelled', 'velox' ),
		);
		return $map[ $state ] ?? $state;
	}

	/* ----------------------------------------------------------------- assets */

	public static function assets() {
		// Unstyled on purpose beyond a sane baseline: the builder's own design
		// system should own how a shop looks, not this module.
		$css = '.vxs-grid{display:grid;grid-template-columns:repeat(var(--vxs-cols,3),minmax(0,1fr));gap:28px}'
			. '.vxs-card{display:flex;flex-direction:column;gap:8px}'
			. '.vxs-im img{width:100%;height:auto;display:block}'
			. '.vxs-card.vxs-sold .vxs-im{opacity:.55}'
			. '.vxs-cart{list-style:none;margin:0;padding:0}'
			. '.vxs-line{display:flex;align-items:center;gap:14px;padding:12px 0;border-bottom:1px solid rgba(0,0,0,.1)}'
			. '.vxs-line-im{width:56px;height:56px;object-fit:cover;display:block}'
			. '.vxs-line-b{flex:1;display:flex;flex-direction:column}'
			. '.vxs-rm button{background:none;border:0;font-size:20px;cursor:pointer;line-height:1}'
			. '.vxs-checkout label{display:block;margin:14px 0}'
			. '.vxs-checkout input,.vxs-checkout textarea{display:block;width:100%}'
			. '.vxs-btn{cursor:pointer}'
			. '@media(max-width:900px){.vxs-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}'
			. '@media(max-width:560px){.vxs-grid{grid-template-columns:1fr}}';
		wp_register_style( 'velox-shop', false, array(), VELOX_VERSION );
		wp_enqueue_style( 'velox-shop' );
		wp_add_inline_style( 'velox-shop', $css );
	}

	/* ------------------------------------------------------------ admin columns */

	public static function product_cols( $cols ) {
		$new = array();
		foreach ( $cols as $k => $v ) {
			$new[ $k ] = $v;
			if ( 'title' === $k ) {
				$new['vx_price']  = __( 'Price', 'velox' );
				$new['vx_status'] = __( 'Availability', 'velox' );
				$new['vx_dims']   = __( 'Dimensions', 'velox' );
			}
		}
		return $new;
	}

	public static function product_col( $col, $post_id ) {
		if ( 'vx_price' === $col ) {
			echo esc_html( self::money( self::price( $post_id ) ) );
		} elseif ( 'vx_status' === $col ) {
			echo esc_html( ucfirst( self::status( $post_id ) ) );
		} elseif ( 'vx_dims' === $col ) {
			echo esc_html( (string) get_post_meta( $post_id, '_vx_dims', true ) );
		}
	}

	public static function order_cols( $cols ) {
		return array(
			'cb'        => $cols['cb'] ?? '',
			'title'     => __( 'Order', 'velox' ),
			'vx_total'  => __( 'Total', 'velox' ),
			'vx_state'  => __( 'State', 'velox' ),
			'vx_email'  => __( 'Customer', 'velox' ),
			'date'      => __( 'Date', 'velox' ),
		);
	}

	public static function order_col( $col, $post_id ) {
		if ( 'vx_total' === $col ) {
			echo esc_html( self::money( (float) get_post_meta( $post_id, '_vx_total', true ) ) );
		} elseif ( 'vx_state' === $col ) {
			echo esc_html( self::state_label( (string) get_post_meta( $post_id, '_vx_state', true ) ) );
		} elseif ( 'vx_email' === $col ) {
			echo esc_html( (string) get_post_meta( $post_id, '_vx_email', true ) );
		}
	}

	/* ------------------------------------------------------------- product box */

	public static function meta_boxes() {
		add_meta_box( 'velox-product', __( 'Work details', 'velox' ), array( __CLASS__, 'product_box' ), self::PRODUCT, 'side' );
		add_meta_box( 'velox-order', __( 'Order', 'velox' ), array( __CLASS__, 'order_box' ), self::ORDER, 'normal' );
	}

	public static function product_box( $post ) {
		wp_nonce_field( 'velox_product', '_vxp' );
		$f = function ( $k ) use ( $post ) {
			return esc_attr( (string) get_post_meta( $post->ID, $k, true ) );
		};
		$status = self::status( $post->ID );
		echo '<p><label>' . esc_html__( 'Price', 'velox' ) . '<br><input type="number" step="0.01" min="0" name="_vx_price" value="' . $f( '_vx_price' ) . '" class="widefat"></label></p>';
		echo '<p><label>' . esc_html__( 'Dimensions', 'velox' ) . '<br><input type="text" name="_vx_dims" value="' . $f( '_vx_dims' ) . '" class="widefat" placeholder="80 × 100 cm"></label></p>';
		echo '<p><label>' . esc_html__( 'Medium', 'velox' ) . '<br><input type="text" name="_vx_medium" value="' . $f( '_vx_medium' ) . '" class="widefat" placeholder="Öl auf Leinwand"></label></p>';
		echo '<p><label>' . esc_html__( 'Year', 'velox' ) . '<br><input type="text" name="_vx_year" value="' . $f( '_vx_year' ) . '" class="widefat"></label></p>';
		echo '<p><label>' . esc_html__( 'Availability', 'velox' ) . '<br><select name="_vx_status" class="widefat">';
		foreach ( array( 'available' => __( 'Available', 'velox' ), 'sold' => __( 'Sold', 'velox' ), 'commission' => __( 'On commission', 'velox' ) ) as $k => $label ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $status, $k, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></label></p>';
	}

	public static function order_box( $post ) {
		$items = json_decode( (string) get_post_meta( $post->ID, '_vx_items', true ), true );
		echo '<p><strong>' . esc_html__( 'Customer', 'velox' ) . ':</strong> '
			. esc_html( (string) get_post_meta( $post->ID, '_vx_name', true ) ) . ' &lt;'
			. esc_html( (string) get_post_meta( $post->ID, '_vx_email', true ) ) . '&gt;</p>';
		$addr = (string) get_post_meta( $post->ID, '_vx_address', true );
		if ( $addr ) {
			echo '<p><strong>' . esc_html__( 'Address', 'velox' ) . ':</strong><br>' . nl2br( esc_html( $addr ) ) . '</p>';
		}
		if ( is_array( $items ) ) {
			echo '<ul>';
			foreach ( $items as $i ) {
				echo '<li>' . esc_html( $i['title'] ?? '' ) . ' × ' . (int) ( $i['qty'] ?? 1 )
					. ' — ' . esc_html( self::money( (float) ( $i['total'] ?? 0 ) ) ) . '</li>';
			}
			echo '</ul>';
		}
		echo '<p><strong>' . esc_html__( 'Total', 'velox' ) . ':</strong> '
			. esc_html( self::money( (float) get_post_meta( $post->ID, '_vx_total', true ) ) ) . '</p>';
	}

	public static function save_product( $post_id, $post ) {
		if ( ! isset( $_POST['_vxp'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_vxp'] ) ), 'velox_product' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		update_post_meta( $post_id, '_vx_price', (float) ( $_POST['_vx_price'] ?? 0 ) );
		foreach ( array( '_vx_dims', '_vx_medium', '_vx_year' ) as $k ) {
			update_post_meta( $post_id, $k, sanitize_text_field( wp_unslash( $_POST[ $k ] ?? '' ) ) );
		}
		$st = sanitize_key( wp_unslash( $_POST['_vx_status'] ?? 'available' ) );
		update_post_meta( $post_id, '_vx_status', in_array( $st, array( 'available', 'sold', 'commission' ), true ) ? $st : 'available' );
	}
}
