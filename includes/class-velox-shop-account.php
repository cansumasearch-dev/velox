<?php
/**
 * Velox Shop — customer accounts.
 *
 * Everything a customer needs on the account page, without ever seeing
 * wp-login.php or wp-admin: sign in, create an account, forgotten password
 * (own reset link and form), and once signed in an overview, orders,
 * downloads, address, account details (name, email, password) and privacy
 * (download my data, delete my account). Guest orders join the account by
 * email; the address fills the checkout; customers can create an account
 * while ordering.
 *
 * Forms post to the account page itself and are handled in
 * template_redirect (post → redirect → get), each with its own nonce.
 *
 * @package Velox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Velox_Shop_Account {

	const ROLE  = 'velox_customer';
	const NONCE = 'velox_shop_acc';
	const META  = 'velox_shop_address';
	const BLOCK = 'velox_blocked';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'role' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle' ), 4 );
		add_filter( 'show_admin_bar', array( __CLASS__, 'admin_bar' ) );
		add_action( 'admin_init', array( __CLASS__, 'keep_out_of_admin' ) );
		add_shortcode( 'velox_account_button', array( __CLASS__, 'sc_button' ) );
		add_filter( 'authenticate', array( __CLASS__, 'block_login' ), 99 );
		add_action( 'wp_login', array( __CLASS__, 'seen' ), 10, 2 );
	}

	/** A customer the shop has locked cannot sign in (staff accounts are never locked here). */
	public static function block_login( $user ) {
		if ( $user instanceof WP_User && get_user_meta( $user->ID, self::BLOCK, true ) && ! user_can( $user, 'edit_posts' ) ) {
			return new WP_Error( 'velox_blocked', __( 'This account is locked. Please contact the shop.', 'velox' ) );
		}
		return $user;
	}

	/** Remember the last sign-in, for the shop's customer list. */
	public static function seen( $login, $user ) {
		if ( $user instanceof WP_User ) {
			update_user_meta( $user->ID, 'velox_last_login', time() );
		}
	}

	/* ------------------------------------------------------------------ basics */

	/** The customer role: may read, nothing else. */
	public static function role() {
		if ( ! get_role( self::ROLE ) ) {
			add_role( self::ROLE, __( 'Customer', 'velox' ), array( 'read' => true ) );
		}
	}

	public static function is_customer( $u = null ) {
		$u = $u ? $u : wp_get_current_user();
		return $u && $u->ID && in_array( self::ROLE, (array) $u->roles, true ) && ! user_can( $u, 'edit_posts' );
	}

	public static function admin_bar( $show ) {
		return self::is_customer() ? false : $show;
	}

	/** Customers have nothing to do in wp-admin: they go to their account. */
	public static function keep_out_of_admin() {
		if ( wp_doing_ajax() || ( defined( 'DOING_CRON' ) && DOING_CRON ) || ! self::is_customer() ) {
			return;
		}
		wp_safe_redirect( self::url() );
		exit;
	}

	public static function enabled() {
		return '1' === (string) Velox_Shop::get( 'acc_register', '1' );
	}

	/** The account page, optionally a section of it. */
	public static function url( $tab = '', $args = array() ) {
		$u = Velox_Shop_Front::page_url( 'account', home_url( '/' ) );
		if ( '' !== $tab ) {
			$args = array_merge( array( 'vxa' => $tab ), $args );
		}
		return $args ? add_query_arg( $args, $u ) : $u;
	}

	private static function go( $tab, $note = '', $extra = array() ) {
		$args = $extra;
		if ( '' !== $note ) {
			$args['vxn'] = $note;
		}
		wp_safe_redirect( self::url( $tab, $args ) );
		exit;
	}

	private static function ip_limit( $what, $max, $minutes ) {
		$k = 'velox_acc_' . $what . '_' . md5( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) );
		$n = (int) get_transient( $k );
		if ( $n >= $max ) {
			return false;
		}
		set_transient( $k, $n + 1, $minutes * MINUTE_IN_SECONDS );
		return true;
	}

	private static function p( $k, $max = 200 ) {
		return isset( $_POST[ $k ] ) ? mb_substr( trim( sanitize_text_field( wp_unslash( $_POST[ $k ] ) ) ), 0, $max ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	}

	/** Raw password field (never sanitised: that would change it). */
	private static function pw( $k ) {
		return isset( $_POST[ $k ] ) ? (string) wp_unslash( $_POST[ $k ] ) : ''; // phpcs:ignore
	}

	/** Messages shown after a redirect, by code. */
	private static function notes() {
		return array(
			'login-bad'    => array( 'err', __( 'Email or password is not right. Please try again.', 'velox' ) ),
			'login-locked' => array( 'err', __( 'This account is locked. Please contact the shop.', 'velox' ) ),
			'login-many'   => array( 'err', __( 'Too many attempts. Please wait a few minutes.', 'velox' ) ),
			'reg-off'      => array( 'err', __( 'Creating accounts is switched off for this shop.', 'velox' ) ),
			'reg-email'    => array( 'err', __( 'Please enter a valid email address.', 'velox' ) ),
			'reg-exists'   => array( 'err', __( 'There is already an account for this email. Sign in or reset your password.', 'velox' ) ),
			'reg-pass'     => array( 'err', __( 'Please choose a password with at least 8 characters.', 'velox' ) ),
			'reg-name'     => array( 'err', __( 'Please enter your first and last name.', 'velox' ) ),
			'reg-privacy'  => array( 'err', __( 'Please confirm that you have read the privacy policy.', 'velox' ) ),
			'reg-fail'     => array( 'err', __( 'The account could not be created. Please try again.', 'velox' ) ),
			'welcome'      => array( 'ok', __( 'Welcome! Your account is ready.', 'velox' ) ),
			'lost-sent'    => array( 'ok', __( 'If there is an account for this address, a link to set a new password is on its way. Please check your inbox.', 'velox' ) ),
			'reset-bad'    => array( 'err', __( 'This link is no longer valid. Please ask for a new one.', 'velox' ) ),
			'reset-ok'     => array( 'ok', __( 'Your new password is set. You can sign in now.', 'velox' ) ),
			'pass-match'   => array( 'err', __( 'The two passwords do not match.', 'velox' ) ),
			'pass-wrong'   => array( 'err', __( 'Your current password is not right.', 'velox' ) ),
			'saved'        => array( 'ok', __( 'Saved.', 'velox' ) ),
			'pass-ok'      => array( 'ok', __( 'Your password has been changed.', 'velox' ) ),
			'email-ok'     => array( 'ok', __( 'Your email address has been changed.', 'velox' ) ),
			'email-used'   => array( 'err', __( 'This email address belongs to another account.', 'velox' ) ),
			'deleted'      => array( 'ok', __( 'Your account has been deleted. Orders are kept as long as the law requires.', 'velox' ) ),
			'delete-check' => array( 'err', __( 'Please tick the box to confirm.', 'velox' ) ),
			'expired'      => array( 'err', __( 'The page had expired. Please try again.', 'velox' ) ),
			'signed-out'   => array( 'ok', __( 'You are signed out.', 'velox' ) ),
		);
	}

	private static function note_html() {
		$c = isset( $_GET['vxn'] ) ? sanitize_key( wp_unslash( $_GET['vxn'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$n = self::notes();
		if ( '' === $c || ! isset( $n[ $c ] ) ) {
			return '';
		}
		return '<div class="vxs-note ' . ( 'ok' === $n[ $c ][0] ? 'is-ok' : 'is-err' ) . '" role="' . ( 'ok' === $n[ $c ][0] ? 'status' : 'alert' ) . '">' . esc_html( $n[ $c ][1] ) . '</div>';
	}

	/* ------------------------------------------------------------------ actions */

	public static function handle() {
		// Sign out from a link (own route: wp-login may live at a custom address).
		if ( isset( $_GET['vxa'] ) && 'logout' === $_GET['vxa'] && is_user_logged_in() ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ), 'velox_shop_logout' ) ) {
				wp_logout();
				self::go( '', 'signed-out' );
			}
		}
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || empty( $_POST['velox_shop_acc'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}
		$act = sanitize_key( wp_unslash( $_POST['velox_shop_acc'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! isset( $_POST['_vxacc'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_vxacc'] ) ), self::NONCE . $act ) ) {
			self::go( 'login' === $act || 'register' === $act ? $act : '', 'expired' );
		}
		switch ( $act ) {
			case 'login':
				self::do_login();
				break;
			case 'register':
				self::do_register();
				break;
			case 'lost':
				self::do_lost();
				break;
			case 'reset':
				self::do_reset();
				break;
		}
		if ( ! is_user_logged_in() ) {
			self::go( 'login' );
		}
		switch ( $act ) {
			case 'profile':
				self::do_profile();
				break;
			case 'address':
				self::do_address();
				break;
			case 'password':
				self::do_password();
				break;
			case 'email':
				self::do_email();
				break;
			case 'export':
				self::do_export();
				break;
			case 'delete':
				self::do_delete();
				break;
		}
	}

	private static function do_login() {
		if ( ! self::ip_limit( 'login', 10, 10 ) ) {
			self::go( 'login', 'login-many' );
		}
		$user = wp_signon( array(
			'user_login'    => self::p( 'vx_login', 120 ),
			'user_password' => self::pw( 'vx_pass' ),
			'remember'      => ! empty( $_POST['vx_remember'] ), // phpcs:ignore WordPress.Security.NonceVerification.Missing
		), is_ssl() );
		if ( is_wp_error( $user ) ) {
			self::go( 'login', 'velox_blocked' === $user->get_error_code() ? 'login-locked' : 'login-bad' );
		}
		$to = isset( $_POST['vx_to'] ) ? esc_url_raw( wp_unslash( $_POST['vx_to'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		wp_safe_redirect( $to ? $to : self::url() );
		exit;
	}

	/** Create a customer account; returns the user id or an error code. */
	public static function create( $email, $pass, $first, $last, $welcome = true ) {
		if ( ! is_email( $email ) ) {
			return 'reg-email';
		}
		if ( email_exists( $email ) ) {
			return 'reg-exists';
		}
		if ( strlen( $pass ) < 8 ) {
			return 'reg-pass';
		}
		self::role();
		$base  = sanitize_user( current( explode( '@', $email ) ), true );
		$base  = '' !== $base ? $base : 'kunde';
		$login = $base;
		for ( $i = 2; username_exists( $login ); $i++ ) {
			$login = $base . $i;
		}
		$id = wp_insert_user( array(
			'user_login'   => $login,
			'user_email'   => $email,
			'user_pass'    => $pass,
			'first_name'   => $first,
			'last_name'    => $last,
			'display_name' => trim( $first . ' ' . $last ) ? trim( $first . ' ' . $last ) : $login,
			'role'         => self::ROLE,
		) );
		if ( is_wp_error( $id ) ) {
			return 'reg-fail';
		}
		update_user_meta( $id, 'show_admin_bar_front', 'false' );
		if ( $welcome ) {
			self::welcome_mail( $id );
		}
		return (int) $id;
	}

	private static function do_register() {
		if ( ! self::enabled() ) {
			self::go( 'register', 'reg-off' );
		}
		if ( ! empty( $_POST['vx_website'] ) || ! self::ip_limit( 'reg', 5, 60 ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			self::go( 'register', 'login-many' );
		}
		$first = self::p( 'vx_first', 60 );
		$last  = self::p( 'vx_last', 60 );
		if ( '' === $first || '' === $last ) {
			self::go( 'register', 'reg-name' );
		}
		if ( empty( $_POST['vx_privacy'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			self::go( 'register', 'reg-privacy' );
		}
		$id = self::create( sanitize_email( self::p( 'vx_email', 120 ) ), self::pw( 'vx_pass' ), $first, $last );
		if ( ! is_int( $id ) ) {
			self::go( 'register', $id );
		}
		update_user_meta( $id, 'velox_shop_privacy', time() );
		wp_set_current_user( $id );
		wp_set_auth_cookie( $id, true, is_ssl() );
		self::go( '', 'welcome' );
	}

	private static function do_lost() {
		if ( ! self::ip_limit( 'lost', 5, 30 ) ) {
			self::go( 'lost', 'login-many' );
		}
		$in   = self::p( 'vx_login', 120 );
		$user = is_email( $in ) ? get_user_by( 'email', $in ) : get_user_by( 'login', $in );
		if ( $user ) {
			self::send_reset( $user );
		}
		// The same answer either way: the form must not reveal who has an account.
		self::go( 'login', 'lost-sent' );
	}

	/**
	 * Email a link to choose a new password. $invite: the shop made the account,
	 * so the mail says "choose your password" instead of "someone asked".
	 */
	public static function send_reset( $user, $invite = false ) {
		$key = get_password_reset_key( $user );
		if ( is_wp_error( $key ) ) {
			return $key;
		}
		$store = (string) Velox_Shop::get( 'store_name', get_bloginfo( 'name' ) );
		$link  = self::url( 'reset', array( 'key' => $key, 'login' => rawurlencode( $user->user_login ) ) );
		$title = $invite ? __( 'Your customer account is ready', 'velox' ) : __( 'Set a new password', 'velox' );
		$text  = $invite
			/* translators: %s: shop name */
			? sprintf( __( 'We have set up a customer account for you at %s. In it you see your orders, invoices and downloads. Choose your password with the button below — the link works for 24 hours.', 'velox' ), $store )
			: __( 'Someone asked to set a new password for your customer account. If that was you, choose a new password with the button below. The link works for 24 hours.', 'velox' );
		$body  = '<h1 style="margin:0 0 12px;font-size:22px">' . esc_html( $title ) . '</h1>'
			. '<p style="margin:0">' . esc_html( $text ) . '</p>'
			. Velox_Shop::mail_button( $link, $invite ? __( 'Choose my password', 'velox' ) : __( 'Set a new password', 'velox' ) )
			. ( $invite ? '' : '<p style="margin:22px 0 0;color:#6b7280;font-size:13px">' . esc_html__( 'If you did not ask for this, you can ignore this email — nothing changes.', 'velox' ) . '</p>' );
		return wp_mail( $user->user_email, $title . ' — ' . $store, Velox_Shop::mail_layout( $body, $title ), array( 'Content-Type: text/html; charset=UTF-8' ) );
	}

	private static function do_reset() {
		$login = self::p( 'vx_rlogin', 120 );
		$user  = check_password_reset_key( self::p( 'vx_key', 80 ), $login );
		if ( is_wp_error( $user ) ) {
			self::go( 'lost', 'reset-bad' );
		}
		$pass = self::pw( 'vx_pass' );
		if ( strlen( $pass ) < 8 ) {
			self::go( 'reset', 'reg-pass', array( 'key' => self::p( 'vx_key', 80 ), 'login' => rawurlencode( $login ) ) );
		}
		if ( $pass !== self::pw( 'vx_pass2' ) ) {
			self::go( 'reset', 'pass-match', array( 'key' => self::p( 'vx_key', 80 ), 'login' => rawurlencode( $login ) ) );
		}
		// A customer resetting their password is no news for the shop owner's inbox.
		remove_action( 'after_password_reset', 'wp_password_change_notification' );
		reset_password( $user, $pass );
		self::go( 'login', 'reset-ok' );
	}

	private static function do_profile() {
		$u     = wp_get_current_user();
		$first = self::p( 'vx_first', 60 );
		$last  = self::p( 'vx_last', 60 );
		if ( '' === $first || '' === $last ) {
			self::go( 'details', 'reg-name' );
		}
		wp_update_user( array( 'ID' => $u->ID, 'first_name' => $first, 'last_name' => $last, 'display_name' => trim( $first . ' ' . $last ) ) );
		self::go( 'details', 'saved' );
	}

	private static function do_password() {
		$u = wp_get_current_user();
		if ( ! wp_check_password( self::pw( 'vx_cur' ), $u->user_pass, $u->ID ) ) {
			self::go( 'details', 'pass-wrong' );
		}
		$new = self::pw( 'vx_pass' );
		if ( strlen( $new ) < 8 ) {
			self::go( 'details', 'reg-pass' );
		}
		if ( $new !== self::pw( 'vx_pass2' ) ) {
			self::go( 'details', 'pass-match' );
		}
		wp_set_password( $new, $u->ID );
		// wp_set_password ends every session: sign this one back in.
		wp_set_current_user( $u->ID );
		wp_set_auth_cookie( $u->ID, true, is_ssl() );
		self::go( 'details', 'pass-ok' );
	}

	private static function do_email() {
		$u = wp_get_current_user();
		if ( ! wp_check_password( self::pw( 'vx_cur' ), $u->user_pass, $u->ID ) ) {
			self::go( 'details', 'pass-wrong' );
		}
		$new = sanitize_email( self::p( 'vx_email', 120 ) );
		if ( ! is_email( $new ) ) {
			self::go( 'details', 'reg-email' );
		}
		$owner = email_exists( $new );
		if ( $owner && (int) $owner !== (int) $u->ID ) {
			self::go( 'details', 'email-used' );
		}
		wp_update_user( array( 'ID' => $u->ID, 'user_email' => $new ) );
		self::go( 'details', 'email-ok' );
	}

	/** The address fields, named like the checkout's so they fill it. */
	public static function address_keys() {
		return array( 'vx_first' => 60, 'vx_last' => 60, 'vx_company' => 100, 'vx_phone' => 30, 'vx_line1' => 120, 'vx_line2' => 120, 'vx_zip' => 12, 'vx_city' => 80, 'vx_country' => 2 );
	}

	public static function save_address( $user_id, $src ) {
		$a = array();
		foreach ( self::address_keys() as $k => $max ) {
			$v = isset( $src[ $k ] ) ? mb_substr( trim( sanitize_text_field( wp_unslash( $src[ $k ] ) ) ), 0, $max ) : '';
			if ( 'vx_country' === $k ) {
				$v = strtoupper( $v );
			}
			$a[ $k ] = $v;
		}
		update_user_meta( $user_id, self::META, array_filter( $a ) );
	}

	private static function do_address() {
		self::save_address( get_current_user_id(), $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		self::go( 'address', 'saved' );
	}

	public static function order_ids( $u ) {
		return get_posts( array( 'post_type' => Velox_Shop::ORDER, 'post_status' => 'publish', 'numberposts' => 200, 'fields' => 'ids',
			'meta_query' => array( 'relation' => 'OR', array( 'key' => '_vx_user', 'value' => (string) $u->ID ), array( 'key' => '_vx_email', 'value' => $u->user_email ) ) ) );
	}

	/** Everything the shop knows about a customer (GDPR Art. 15/20). */
	public static function export_data( $u ) {
		$data = array(
			'account' => array( 'email' => $u->user_email, 'first_name' => $u->first_name, 'last_name' => $u->last_name, 'registered' => $u->user_registered ),
			'address' => array_filter( (array) get_user_meta( $u->ID, self::META, true ) ),
			'orders'  => array(),
		);
		foreach ( self::order_ids( $u ) as $id ) {
			$o = Velox_Shop::order( $id );
			if ( $o ) {
				$data['orders'][] = array(
					'number' => $o['number'], 'date' => wp_date( 'c', (int) $o['created'] ), 'total' => $o['total'], 'currency' => $o['currency'] ?? '',
					'customer' => $o['customer'] ?? array(), 'items' => array_map( function ( $l ) { return array( 'name' => $l['name'] ?? '', 'variant' => $l['variant'] ?? '', 'qty' => $l['qty'] ?? 1, 'total' => $l['total'] ?? 0 ); }, (array) ( $o['items'] ?? array() ) ),
					'payment' => $o['payment']['status'] ?? '', 'shipping' => $o['fulfil']['status'] ?? '',
				);
			}
		}
		return $data;
	}

	private static function do_export() {
		$data = self::export_data( wp_get_current_user() );
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="meine-daten-' . gmdate( 'Y-m-d' ) . '.json"' );
		echo wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		exit;
	}

	/** Delete a customer account. Orders stay (tax law: 10 years) but no longer point to it. */
	public static function delete_customer( $u ) {
		if ( ! $u || ! $u->ID || user_can( $u, 'edit_posts' ) ) {
			return false;
		}
		foreach ( self::order_ids( $u ) as $id ) {
			$o = Velox_Shop::order( $id );
			if ( $o && (int) ( $o['user'] ?? 0 ) === (int) $u->ID ) {
				$o['user'] = 0;
				Velox_Shop::save_order( $id, $o );
			}
			delete_post_meta( $id, '_vx_user' );
		}
		require_once ABSPATH . 'wp-admin/includes/user.php';
		return wp_delete_user( $u->ID );
	}

	private static function do_delete() {
		$u = wp_get_current_user();
		if ( user_can( $u, 'edit_posts' ) ) {
			self::go( 'privacy' ); // staff accounts are not deleted from the shop front
		}
		if ( empty( $_POST['vx_sure'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			self::go( 'privacy', 'delete-check' );
		}
		if ( ! wp_check_password( self::pw( 'vx_cur' ), $u->user_pass, $u->ID ) ) {
			self::go( 'privacy', 'pass-wrong' );
		}
		wp_logout();
		self::delete_customer( $u );
		self::go( '', 'deleted' );
	}

	public static function welcome_mail( $id ) {
		$u = get_userdata( $id );
		if ( ! $u ) {
			return;
		}
		$store = (string) Velox_Shop::get( 'store_name', get_bloginfo( 'name' ) );
		$body  = '<h1 style="margin:0 0 12px;font-size:22px">' . esc_html( sprintf( /* translators: %s: first name */ __( 'Welcome, %s!', 'velox' ), $u->first_name ? $u->first_name : $u->display_name ) ) . '</h1>'
			. '<p style="margin:0">' . esc_html( sprintf( /* translators: %s: shop name */ __( 'Your customer account at %s is ready. In it you see your orders, downloads and invoices, and you can change your address and password at any time.', 'velox' ), $store ) ) . '</p>'
			. Velox_Shop::mail_button( self::url(), __( 'Open my account', 'velox' ) );
		wp_mail( $u->user_email, sprintf( /* translators: %s: shop name */ __( 'Your account at %s', 'velox' ), $store ), Velox_Shop::mail_layout( $body, __( 'Welcome', 'velox' ) ), array( 'Content-Type: text/html; charset=UTF-8' ) );
	}

	/* ------------------------------------------------------------------ checkout hooks */

	/** After an order: keep the address of signed-in customers; create the account if asked. */
	public static function after_order( $o ) {
		if ( is_user_logged_in() ) {
			self::save_address( get_current_user_id(), $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}
		if ( ! self::enabled() || empty( $_POST['vx_account'] ) || '1' !== (string) Velox_Shop::get( 'acc_checkout', '1' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}
		$c  = $o['customer'] ?? array();
		$id = self::create( (string) ( $c['email'] ?? '' ), self::pw( 'vx_acc_pass' ), (string) ( $c['first'] ?? '' ), (string) ( $c['last'] ?? '' ) );
		if ( ! is_int( $id ) ) {
			return; // the order stands; the account can still be made later
		}
		$o['user'] = $id;
		Velox_Shop::save_order( (int) $o['id'], $o ); // also writes the _vx_user copy
		self::save_address( $id, $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		wp_set_current_user( $id );
		wp_set_auth_cookie( $id, true, is_ssl() );
	}

	/** The checkout's "create an account" box (only for guests). */
	public static function checkout_box( $post = array(), $err = array() ) {
		if ( is_user_logged_in() || ! self::enabled() || '1' !== (string) Velox_Shop::get( 'acc_checkout', '1' ) ) {
			return '';
		}
		return '<div class="vxs-accbox"><label class="vxs-check"><input type="checkbox" name="vx_account" value="1" data-vxs-acc' . checked( ! empty( $post['vx_account'] ), true, false ) . '> <span><b>' . esc_html__( 'Create a customer account', 'velox' ) . '</b> ' . esc_html__( 'See your orders and invoices any time, order faster next time.', 'velox' ) . '</span></label>'
			. '<label class="vxs-f vxs-accpass"><span>' . esc_html__( 'Choose a password', 'velox' ) . ' <small>' . esc_html__( 'at least 8 characters', 'velox' ) . '</small></span><input type="password" name="vx_acc_pass" minlength="8" autocomplete="new-password"' . ( isset( $err['vx_acc_pass'] ) ? ' aria-invalid="true" aria-describedby="vx_acc_pass-e"' : '' ) . '>' . ( isset( $err['vx_acc_pass'] ) ? '<span class="vxs-ferr" id="vx_acc_pass-e">' . esc_html( $err['vx_acc_pass'] ) . '</span>' : '' ) . '</label></div>';
	}

	/* ------------------------------------------------------------------ the page */

	private static function nonce( $act ) {
		return '<input type="hidden" name="velox_shop_acc" value="' . esc_attr( $act ) . '">' . wp_nonce_field( self::NONCE . $act, '_vxacc', false, false );
	}

	private static function field( $name, $label, $type = 'text', $value = '', $extra = '' ) {
		return '<label class="vxs-f"><span>' . esc_html( $label ) . '</span><input type="' . esc_attr( $type ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" ' . $extra . '></label>';
	}

	private static function icon( $name ) {
		$p = array(
			'home'   => '<path d="M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6h-6v6H4a1 1 0 0 1-1-1z"/>',
			'bag'    => '<path d="M6 7h12l-1 12.2a1 1 0 0 1-1 .8H8a1 1 0 0 1-1-.8z"/><path d="M9 7V6a3 3 0 0 1 6 0v1"/>',
			'down'   => '<path d="M12 3v12m0 0-4.5-4.5M12 15l4.5-4.5M4 19h16"/>',
			'pin'    => '<path d="M12 21s-7-6.3-7-11.5a7 7 0 0 1 14 0C19 14.7 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>',
			'user'   => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
			'shield' => '<path d="M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6z"/>',
			'out'    => '<path d="M15 4h4a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1h-4M10 16l4-4-4-4M14 12H4"/>',
		);
		return '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ( $p[ $name ] ?? '' ) . '</svg>';
	}

	public static function render() {
		$tab = isset( $_GET['vxa'] ) ? sanitize_key( wp_unslash( $_GET['vxa'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! is_user_logged_in() ) {
			return '<div class="vxs-acc vxs-account vxs-acc-out">' . self::note_html() . self::guest( $tab ) . '</div>';
		}
		$u    = wp_get_current_user();
		$tabs = array(
			''         => array( 'home', __( 'Overview', 'velox' ) ),
			'orders'   => array( 'bag', __( 'Orders', 'velox' ) ),
			'downloads' => array( 'down', __( 'Downloads', 'velox' ) ),
			'address'  => array( 'pin', __( 'Address', 'velox' ) ),
			'details'  => array( 'user', __( 'Account details', 'velox' ) ),
			'privacy'  => array( 'shield', __( 'Privacy', 'velox' ) ),
		);
		if ( ! isset( $tabs[ $tab ] ) ) {
			$tab = '';
		}
		$nav = '<nav class="vxs-acc-nav" aria-label="' . esc_attr__( 'Account', 'velox' ) . '"><div class="vxs-acc-me"><span class="vxs-acc-av" aria-hidden="true">' . esc_html( mb_strtoupper( mb_substr( $u->first_name ? $u->first_name : $u->display_name, 0, 1 ) ) ) . '</span><span><b>' . esc_html( $u->display_name ) . '</b><small>' . esc_html( $u->user_email ) . '</small></span></div><ul>';
		foreach ( $tabs as $k => $t ) {
			$nav .= '<li><a href="' . esc_url( self::url( $k ) ) . '"' . ( $k === $tab ? ' aria-current="page"' : '' ) . '>' . self::icon( $t[0] ) . esc_html( $t[1] ) . '</a></li>';
		}
		$nav .= '<li><a href="' . esc_url( wp_nonce_url( self::url( 'logout' ), 'velox_shop_logout' ) ) . '">' . self::icon( 'out' ) . esc_html__( 'Sign out', 'velox' ) . '</a></li></ul></nav>';
		$body = self::note_html();
		switch ( $tab ) {
			case 'orders':
				$body .= self::tab_orders( $u );
				break;
			case 'downloads':
				$body .= self::tab_downloads( $u );
				break;
			case 'address':
				$body .= self::tab_address( $u );
				break;
			case 'details':
				$body .= self::tab_details( $u );
				break;
			case 'privacy':
				$body .= self::tab_privacy( $u );
				break;
			default:
				$body .= self::tab_overview( $u );
		}
		return '<div class="vxs-acc vxs-account">' . $nav . '<div class="vxs-acc-main">' . $body . '</div></div>';
	}

	private static function guest( $tab ) {
		if ( 'reset' === $tab && isset( $_GET['key'], $_GET['login'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$key   = sanitize_text_field( wp_unslash( $_GET['key'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$login = sanitize_user( rawurldecode( wp_unslash( $_GET['login'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( is_wp_error( check_password_reset_key( $key, $login ) ) ) {
				return '<div class="vxs-acc-card"><h2>' . esc_html__( 'Set a new password', 'velox' ) . '</h2><p>' . esc_html( self::notes()['reset-bad'][1] ) . '</p><a class="vxs-btn" href="' . esc_url( self::url( 'lost' ) ) . '">' . esc_html__( 'Ask for a new link', 'velox' ) . '</a></div>';
			}
			return '<form method="post" class="vxs-acc-card vxs-acc-form">' . self::nonce( 'reset' )
				. '<input type="hidden" name="vx_key" value="' . esc_attr( $key ) . '"><input type="hidden" name="vx_rlogin" value="' . esc_attr( $login ) . '">'
				. '<h2>' . esc_html__( 'Set a new password', 'velox' ) . '</h2>'
				. self::field( 'vx_pass', __( 'New password (at least 8 characters)', 'velox' ), 'password', '', 'required minlength="8" autocomplete="new-password"' )
				. self::field( 'vx_pass2', __( 'Repeat the new password', 'velox' ), 'password', '', 'required minlength="8" autocomplete="new-password"' )
				. '<button type="submit" class="vxs-btn vxs-btn-lg vxs-btn-block">' . esc_html__( 'Save password', 'velox' ) . '</button></form>';
		}
		if ( 'lost' === $tab ) {
			return '<form method="post" class="vxs-acc-card vxs-acc-form">' . self::nonce( 'lost' )
				. '<h2>' . esc_html__( 'Forgotten your password?', 'velox' ) . '</h2><p>' . esc_html__( 'Enter your email address. We send you a link to set a new password.', 'velox' ) . '</p>'
				. self::field( 'vx_login', __( 'Email', 'velox' ), 'email', '', 'required autocomplete="email"' )
				. '<button type="submit" class="vxs-btn vxs-btn-lg vxs-btn-block">' . esc_html__( 'Send the link', 'velox' ) . '</button>'
				. '<p class="vxs-acc-alt"><a href="' . esc_url( self::url( 'login' ) ) . '">← ' . esc_html__( 'Back to sign in', 'velox' ) . '</a></p></form>';
		}
		$reg   = self::enabled();
		$is_rg = $reg && 'register' === $tab;
		$to    = isset( $_GET['redirect_to'] ) ? esc_url( wp_unslash( $_GET['redirect_to'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$mail  = isset( $_GET['email'] ) ? sanitize_email( wp_unslash( $_GET['email'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$priv  = (int) Velox_Shop::get( 'page_privacy' );
		$out   = '<div class="vxs-acc-card">';
		if ( $reg ) {
			$out .= '<div class="vxs-acc-tabs" role="tablist"><a role="tab" href="' . esc_url( self::url( 'login' ) ) . '" aria-selected="' . ( $is_rg ? 'false' : 'true' ) . '">' . esc_html__( 'Sign in', 'velox' ) . '</a><a role="tab" href="' . esc_url( self::url( 'register' ) ) . '" aria-selected="' . ( $is_rg ? 'true' : 'false' ) . '">' . esc_html__( 'Create an account', 'velox' ) . '</a></div>';
		}
		if ( $is_rg ) {
			$out .= '<form method="post" class="vxs-acc-form">' . self::nonce( 'register' )
				. '<p class="vxs-acc-lead">' . esc_html__( 'With an account you see your orders, invoices and downloads in one place and order faster next time.', 'velox' ) . '</p>'
				. '<div class="vxs-fg vxs-fg-2">' . self::field( 'vx_first', __( 'First name', 'velox' ), 'text', '', 'required autocomplete="given-name"' ) . self::field( 'vx_last', __( 'Last name', 'velox' ), 'text', '', 'required autocomplete="family-name"' ) . '</div>'
				. self::field( 'vx_email', __( 'Email', 'velox' ), 'email', $mail, 'required autocomplete="email"' )
				. self::field( 'vx_pass', __( 'Password (at least 8 characters)', 'velox' ), 'password', '', 'required minlength="8" autocomplete="new-password"' )
				. '<input type="text" name="vx_website" value="" tabindex="-1" autocomplete="off" class="vxs-hp" aria-hidden="true">'
				. '<label class="vxs-check"><input type="checkbox" name="vx_privacy" value="1" required> <span>' . ( $priv
					? sprintf( /* translators: %s: privacy policy link */ esc_html__( 'I have read the %s.', 'velox' ), '<a href="' . esc_url( get_permalink( $priv ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'privacy policy', 'velox' ) . '</a>' )
					: esc_html__( 'I agree that my data is stored for my customer account.', 'velox' ) ) . '</span></label>'
				. '<button type="submit" class="vxs-btn vxs-btn-lg vxs-btn-block">' . esc_html__( 'Create my account', 'velox' ) . '</button></form>';
		} else {
			$out .= '<form method="post" class="vxs-acc-form">' . self::nonce( 'login' ) . ( $to ? '<input type="hidden" name="vx_to" value="' . esc_attr( $to ) . '">' : '' )
				. ( $reg ? '' : '<h2>' . esc_html__( 'Sign in', 'velox' ) . '</h2>' )
				. self::field( 'vx_login', __( 'Email', 'velox' ), 'text', $mail, 'required autocomplete="username"' )
				. self::field( 'vx_pass', __( 'Password', 'velox' ), 'password', '', 'required autocomplete="current-password"' )
				. '<div class="vxs-acc-row"><label class="vxs-check"><input type="checkbox" name="vx_remember" value="1"> <span>' . esc_html__( 'Stay signed in', 'velox' ) . '</span></label><a href="' . esc_url( self::url( 'lost' ) ) . '">' . esc_html__( 'Forgotten your password?', 'velox' ) . '</a></div>'
				. '<button type="submit" class="vxs-btn vxs-btn-lg vxs-btn-block">' . esc_html__( 'Sign in', 'velox' ) . '</button></form>';
		}
		$out .= '</div><p class="vxs-acc-hint">' . esc_html__( 'Ordered without an account? The link in your confirmation email always shows your order.', 'velox' ) . '</p>';
		return $out;
	}

	private static function order_rows( $ids, $limit = 0 ) {
		$out = '<ul class="vxs-orders">';
		$n   = 0;
		foreach ( $ids as $id ) {
			$o = Velox_Shop::order( $id );
			if ( ! $o ) {
				continue;
			}
			$href = $o['key'] ? self::url( 'orders', array( 'vxo' => $o['key'] ) ) : '';
			$out .= '<li>' . ( $href ? '<a href="' . esc_url( $href ) . '">' : '<span>' ) . '<b>' . esc_html( $o['number'] ) . '</b><span>' . esc_html( wp_date( get_option( 'date_format' ), (int) $o['created'] ) ) . '</span><span>' . Velox_Shop_Front::money( $o['total'] ) . '</span>' . Velox_Shop_Front::status_chip( 'pay', $o['payment']['status'] ) . ( 'none' !== $o['fulfil']['status'] ? Velox_Shop_Front::status_chip( 'ful', $o['fulfil']['status'] ) : '' ) . ( $href ? '</a>' : '</span>' ) . '</li>';
			if ( $limit && ++$n >= $limit ) {
				break;
			}
		}
		return $out . '</ul>';
	}

	private static function tab_overview( $u ) {
		$ids  = self::order_ids( $u );
		$addr = array_filter( (array) get_user_meta( $u->ID, self::META, true ) );
		$out  = '<h2 class="vxs-acc-h">' . esc_html( sprintf( /* translators: %s: first name */ __( 'Hello, %s', 'velox' ), $u->first_name ? $u->first_name : $u->display_name ) ) . '</h2>'
			. '<p class="vxs-acc-lead">' . esc_html__( 'Here you find your orders, downloads and invoices and can change your details.', 'velox' ) . '</p>';
		$out .= '<div class="vxs-acc-tiles">'
			. '<a class="vxs-acc-tile" href="' . esc_url( self::url( 'orders' ) ) . '">' . self::icon( 'bag' ) . '<b>' . count( $ids ) . '</b><span>' . esc_html( _n( 'Order', 'Orders', count( $ids ), 'velox' ) ) . '</span></a>'
			. '<a class="vxs-acc-tile" href="' . esc_url( self::url( 'address' ) ) . '">' . self::icon( 'pin' ) . '<b>' . esc_html( $addr ? __( 'Saved', 'velox' ) : __( 'Not yet', 'velox' ) ) . '</b><span>' . esc_html__( 'Address', 'velox' ) . '</span></a>'
			. '<a class="vxs-acc-tile" href="' . esc_url( self::url( 'details' ) ) . '">' . self::icon( 'user' ) . '<b>' . esc_html__( 'Details', 'velox' ) . '</b><span>' . esc_html__( 'Name, email, password', 'velox' ) . '</span></a></div>';
		$out .= '<div class="vxs-acc-sec"><div class="vxs-acc-sec-h"><h3>' . esc_html__( 'Latest orders', 'velox' ) . '</h3>' . ( $ids ? '<a href="' . esc_url( self::url( 'orders' ) ) . '">' . esc_html__( 'All orders', 'velox' ) . ' →</a>' : '' ) . '</div>'
			. ( $ids ? self::order_rows( $ids, 3 ) : '<p class="vxs-empty">' . esc_html__( 'No orders yet.', 'velox' ) . ' <a href="' . esc_url( Velox_Shop_Front::page_url( 'shop', home_url( '/' ) ) ) . '">' . esc_html__( 'Go to the shop', 'velox' ) . '</a></p>' ) . '</div>';
		return $out;
	}

	private static function tab_orders( $u ) {
		if ( ! empty( $_GET['vxo'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$o = Velox_Shop::order_by_key( sanitize_text_field( wp_unslash( $_GET['vxo'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( $o && ( (int) ( $o['user'] ?? 0 ) === (int) $u->ID || strtolower( (string) ( $o['customer']['email'] ?? '' ) ) === strtolower( $u->user_email ) ) ) {
				return '<p class="vxs-acc-back"><a href="' . esc_url( self::url( 'orders' ) ) . '">← ' . esc_html__( 'All orders', 'velox' ) . '</a></p>' . Velox_Shop_Front::order_view( $o );
			}
		}
		$ids = self::order_ids( $u );
		return '<h2 class="vxs-acc-h">' . esc_html__( 'Orders', 'velox' ) . '</h2>' . ( $ids ? self::order_rows( $ids ) : '<p class="vxs-empty">' . esc_html__( 'No orders yet.', 'velox' ) . '</p>' );
	}

	private static function tab_downloads( $u ) {
		$out = '';
		foreach ( self::order_ids( $u ) as $id ) {
			$o = Velox_Shop::order( $id );
			if ( ! $o ) {
				continue;
			}
			$links = Velox_Shop_Front::download_links( $o );
			if ( '' !== trim( wp_strip_all_tags( $links ) ) ) {
				$out .= '<div class="vxs-acc-sec"><h3>' . esc_html( $o['number'] ) . '</h3>' . $links . '</div>';
			}
		}
		return '<h2 class="vxs-acc-h">' . esc_html__( 'Downloads', 'velox' ) . '</h2>' . ( '' !== $out ? $out : '<p class="vxs-empty">' . esc_html__( 'Files you buy show up here as soon as the payment has arrived.', 'velox' ) . '</p>' );
	}

	private static function tab_address( $u ) {
		$a  = array_filter( (array) get_user_meta( $u->ID, self::META, true ) );
		$a += array( 'vx_first' => $u->first_name, 'vx_last' => $u->last_name );
		$v  = function ( $k ) use ( $a ) { return (string) ( $a[ $k ] ?? '' ); };
		$opts = '';
		$cur  = '' !== $v( 'vx_country' ) ? $v( 'vx_country' ) : (string) Velox_Shop::get( 'store_country', 'DE' );
		foreach ( Velox_Shop::ship_countries() as $code => $name ) {
			$opts .= '<option value="' . esc_attr( $code ) . '"' . selected( $cur, $code, false ) . '>' . esc_html( $name ) . '</option>';
		}
		return '<h2 class="vxs-acc-h">' . esc_html__( 'Address', 'velox' ) . '</h2><p class="vxs-acc-lead">' . esc_html__( 'Used for invoices and delivery — and filled in for you at checkout.', 'velox' ) . '</p>'
			. '<form method="post" class="vxs-acc-form vxs-acc-card">' . self::nonce( 'address' )
			. '<div class="vxs-fg vxs-fg-2">' . self::field( 'vx_first', __( 'First name', 'velox' ), 'text', $v( 'vx_first' ), 'autocomplete="given-name"' ) . self::field( 'vx_last', __( 'Last name', 'velox' ), 'text', $v( 'vx_last' ), 'autocomplete="family-name"' ) . '</div>'
			. '<div class="vxs-fg vxs-fg-2">' . self::field( 'vx_company', __( 'Company (optional)', 'velox' ), 'text', $v( 'vx_company' ), 'autocomplete="organization"' ) . self::field( 'vx_phone', __( 'Phone (optional)', 'velox' ), 'tel', $v( 'vx_phone' ), 'autocomplete="tel"' ) . '</div>'
			. self::field( 'vx_line1', __( 'Street and number', 'velox' ), 'text', $v( 'vx_line1' ), 'autocomplete="address-line1"' )
			. self::field( 'vx_line2', __( 'Address line 2 (optional)', 'velox' ), 'text', $v( 'vx_line2' ), 'autocomplete="address-line2"' )
			. '<div class="vxs-fg vxs-fg-zip">' . self::field( 'vx_zip', __( 'Postcode', 'velox' ), 'text', $v( 'vx_zip' ), 'autocomplete="postal-code"' ) . self::field( 'vx_city', __( 'Town', 'velox' ), 'text', $v( 'vx_city' ), 'autocomplete="address-level2"' ) . '</div>'
			. '<label class="vxs-f"><span>' . esc_html__( 'Country', 'velox' ) . '</span><select name="vx_country" autocomplete="country">' . $opts . '</select></label>'
			. '<button type="submit" class="vxs-btn">' . esc_html__( 'Save address', 'velox' ) . '</button></form>';
	}

	private static function tab_details( $u ) {
		return '<h2 class="vxs-acc-h">' . esc_html__( 'Account details', 'velox' ) . '</h2>'
			. '<form method="post" class="vxs-acc-form vxs-acc-card">' . self::nonce( 'profile' ) . '<h3>' . esc_html__( 'Name', 'velox' ) . '</h3>'
			. '<div class="vxs-fg vxs-fg-2">' . self::field( 'vx_first', __( 'First name', 'velox' ), 'text', $u->first_name, 'required autocomplete="given-name"' ) . self::field( 'vx_last', __( 'Last name', 'velox' ), 'text', $u->last_name, 'required autocomplete="family-name"' ) . '</div>'
			. '<button type="submit" class="vxs-btn">' . esc_html__( 'Save', 'velox' ) . '</button></form>'
			. '<form method="post" class="vxs-acc-form vxs-acc-card">' . self::nonce( 'email' ) . '<h3>' . esc_html__( 'Email address', 'velox' ) . '</h3>'
			. self::field( 'vx_email', __( 'New email address', 'velox' ), 'email', $u->user_email, 'required autocomplete="email"' )
			. self::field( 'vx_cur', __( 'Your current password', 'velox' ), 'password', '', 'required autocomplete="current-password"' )
			. '<button type="submit" class="vxs-btn">' . esc_html__( 'Change email', 'velox' ) . '</button></form>'
			. '<form method="post" class="vxs-acc-form vxs-acc-card">' . self::nonce( 'password' ) . '<h3>' . esc_html__( 'Password', 'velox' ) . '</h3>'
			. self::field( 'vx_cur', __( 'Current password', 'velox' ), 'password', '', 'required autocomplete="current-password"' )
			. '<div class="vxs-fg vxs-fg-2">' . self::field( 'vx_pass', __( 'New password (at least 8 characters)', 'velox' ), 'password', '', 'required minlength="8" autocomplete="new-password"' ) . self::field( 'vx_pass2', __( 'Repeat the new password', 'velox' ), 'password', '', 'required minlength="8" autocomplete="new-password"' ) . '</div>'
			. '<button type="submit" class="vxs-btn">' . esc_html__( 'Change password', 'velox' ) . '</button></form>';
	}

	private static function tab_privacy( $u ) {
		$staff = user_can( $u, 'edit_posts' );
		$out   = '<h2 class="vxs-acc-h">' . esc_html__( 'Privacy', 'velox' ) . '</h2>'
			. '<form method="post" class="vxs-acc-form vxs-acc-card">' . self::nonce( 'export' ) . '<h3>' . esc_html__( 'Download my data', 'velox' ) . '</h3>'
			. '<p>' . esc_html__( 'A file with everything the shop has stored about you: account, address and orders.', 'velox' ) . '</p>'
			. '<button type="submit" class="vxs-btn vxs-btn-ghost">' . esc_html__( 'Download (JSON)', 'velox' ) . '</button></form>';
		if ( $staff ) {
			return $out . '<p class="vxs-acc-hint">' . esc_html__( 'This is a staff account: it can only be deleted in WordPress.', 'velox' ) . '</p>';
		}
		return $out . '<form method="post" class="vxs-acc-form vxs-acc-card vxs-acc-danger">' . self::nonce( 'delete' ) . '<h3>' . esc_html__( 'Delete my account', 'velox' ) . '</h3>'
			. '<p>' . esc_html__( 'Your account and saved address are deleted. Orders are kept as long as tax law requires (10 years), but no longer linked to an account. This cannot be undone.', 'velox' ) . '</p>'
			. self::field( 'vx_cur', __( 'Your password', 'velox' ), 'password', '', 'required autocomplete="current-password"' )
			. '<label class="vxs-check"><input type="checkbox" name="vx_sure" value="1" required> <span>' . esc_html__( 'Yes, delete my account.', 'velox' ) . '</span></label>'
			. '<button type="submit" class="vxs-btn vxs-btn-danger">' . esc_html__( 'Delete account', 'velox' ) . '</button></form>';
	}

	/* ------------------------------------------------------------------ header button */

	public static function sc_button( $atts = array() ) {
		$a  = shortcode_atts( array( 'label' => '' ), $atts, 'velox_account_button' );
		$in = is_user_logged_in();
		$l  = '' !== $a['label'] ? $a['label'] : ( $in ? __( 'My account', 'velox' ) : __( 'Sign in', 'velox' ) );
		return '<a class="vxs-accbtn' . ( $in ? ' is-in' : '' ) . '" href="' . esc_url( self::url() ) . '" aria-label="' . esc_attr( $l ) . '">' . self::icon( 'user' ) . ( '' !== $a['label'] ? '<span>' . esc_html( $l ) . '</span>' : '' ) . '</a>';
	}
}
