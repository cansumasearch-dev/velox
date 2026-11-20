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
	const TFA   = 'velox_2fa';          // array( s => secret, on => time, rc => recovery hashes, last => step )
	const TFA_P = 'velox_2fa_pending';  // secret while setting up

	/** True while our own sign-in checks the password (the 2FA step follows). */
	private static $in_flow = false;

	public static function init() {
		add_action( 'init', array( __CLASS__, 'role' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle' ), 4 );
		add_filter( 'show_admin_bar', array( __CLASS__, 'admin_bar' ) );
		add_action( 'admin_init', array( __CLASS__, 'keep_out_of_admin' ) );
		add_shortcode( 'velox_account_button', array( __CLASS__, 'sc_button' ) );
		add_filter( 'authenticate', array( __CLASS__, 'block_login' ), 99 );
		add_filter( 'authenticate', array( __CLASS__, 'require_2fa' ), 100 );
		add_action( 'wp_login', array( __CLASS__, 'seen' ), 10, 2 );
	}

	/** A customer the shop has locked cannot sign in (staff accounts are never locked here). */
	public static function block_login( $user ) {
		if ( $user instanceof WP_User && get_user_meta( $user->ID, self::BLOCK, true ) && ! user_can( $user, 'edit_posts' ) ) {
			return new WP_Error( 'velox_blocked', __( 'This account is locked. Please contact the shop.', 'velox' ) );
		}
		return $user;
	}

	/**
	 * An account with two-factor sign-in can only sign in through the account
	 * page, which asks for the code. wp-login.php and XML-RPC say so instead of
	 * letting the password alone in.
	 */
	public static function require_2fa( $user ) {
		if ( $user instanceof WP_User && ! self::$in_flow && self::has_2fa( $user->ID ) ) {
			return new WP_Error( 'velox_2fa', __( 'This account uses two-factor sign-in. Please sign in on the shop’s account page.', 'velox' ) );
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
			'2fa-bad'      => array( 'err', __( 'That code is not right. Please try again — use the newest code from your app.', 'velox' ) ),
			'2fa-expired'  => array( 'err', __( 'Too much time has passed. Please sign in again.', 'velox' ) ),
			'2fa-on'       => array( 'ok', __( 'Two-factor sign-in is on. Keep your recovery codes somewhere safe.', 'velox' ) ),
			'2fa-off'      => array( 'ok', __( 'Two-factor sign-in is off.', 'velox' ) ),
			'2fa-codes'    => array( 'ok', __( 'New recovery codes are ready. The old ones no longer work.', 'velox' ) ),
			'2fa-rc'       => array( 'ok', __( 'Signed in with a recovery code. It cannot be used again.', 'velox' ) ),
			'others-out'   => array( 'ok', __( 'You are signed out on all other devices.', 'velox' ) ),
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
			case 'login2':
				self::do_login2();
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
			case '2fa_start':
				self::do_2fa_start();
				break;
			case '2fa_confirm':
				self::do_2fa_confirm();
				break;
			case '2fa_cancel':
				delete_user_meta( get_current_user_id(), self::TFA_P );
				self::go( 'security' );
				break;
			case '2fa_off':
				self::do_2fa_off();
				break;
			case '2fa_codes':
				self::do_2fa_codes();
				break;
			case 'others_out':
				WP_Session_Tokens::get_instance( get_current_user_id() )->destroy_others( wp_get_session_token() );
				self::go( 'security', 'others-out' );
				break;
		}
	}

	private static function do_login() {
		if ( ! self::ip_limit( 'login', 10, 10 ) ) {
			self::go( 'login', 'login-many' );
		}
		$login    = self::p( 'vx_login', 120 );
		$remember = ! empty( $_POST['vx_remember'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$to       = isset( $_POST['vx_to'] ) ? esc_url_raw( wp_unslash( $_POST['vx_to'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		self::$in_flow = true;
		$user          = wp_authenticate( $login, self::pw( 'vx_pass' ) );
		self::$in_flow = false;
		if ( is_wp_error( $user ) ) {
			self::go( 'login', 'velox_blocked' === $user->get_error_code() ? 'login-locked' : 'login-bad' );
		}
		if ( self::has_2fa( $user->ID ) ) {
			// Password right: the code comes next, on a page of its own.
			$t = wp_generate_password( 32, false, false );
			set_transient( 'velox_2fa_' . hash( 'sha256', $t ), array( 'u' => $user->ID, 'r' => $remember, 'to' => $to, 'n' => 0 ), 10 * MINUTE_IN_SECONDS );
			self::go( '2fa', '', array( 't' => $t ) );
		}
		self::sign_in( $user, $remember, $to );
	}

	private static function sign_in( $user, $remember, $to, $note = '' ) {
		wp_set_auth_cookie( $user->ID, $remember, is_ssl() );
		do_action( 'wp_login', $user->user_login, $user ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- core hook
		if ( '' !== $note && ! $to ) {
			self::go( '', $note );
		}
		wp_safe_redirect( $to ? $to : self::url() );
		exit;
	}

	private static function do_login2() {
		$t   = self::p( 'vx_t', 64 );
		$key = 'velox_2fa_' . hash( 'sha256', $t );
		$st  = '' !== $t ? get_transient( $key ) : false;
		if ( ! is_array( $st ) ) {
			self::go( 'login', '2fa-expired' );
		}
		$st['n']++;
		if ( $st['n'] > 5 ) {
			delete_transient( $key );
			self::go( 'login', 'login-many' );
		}
		set_transient( $key, $st, 10 * MINUTE_IN_SECONDS );
		$user = get_userdata( (int) $st['u'] );
		$ok   = $user ? self::check_code( $user->ID, self::p( 'vx_code', 40 ) ) : false;
		if ( ! $ok ) {
			self::go( '2fa', '2fa-bad', array( 't' => $t ) );
		}
		delete_transient( $key );
		self::sign_in( $user, ! empty( $st['r'] ), (string) $st['to'], 'rc' === $ok ? '2fa-rc' : '' );
	}

	/* ------------------------------------------------------------------ two-factor sign-in */

	public static function has_2fa( $user_id ) {
		$d = get_user_meta( (int) $user_id, self::TFA, true );
		return is_array( $d ) && ! empty( $d['s'] );
	}

	private static function b32_secret() {
		$a = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
		$s = '';
		$b = random_bytes( 32 ); // 256 is a multiple of 32: every letter equally likely, 160 bits
		for ( $i = 0; $i < 32; $i++ ) {
			$s .= $a[ ord( $b[ $i ] ) % 32 ];
		}
		return $s;
	}

	private static function b32_decode( $s ) {
		$a    = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
		$bits = '';
		foreach ( str_split( strtoupper( preg_replace( '/[^A-Za-z2-7]/', '', $s ) ) ) as $c ) {
			$bits .= str_pad( decbin( strpos( $a, $c ) ), 5, '0', STR_PAD_LEFT );
		}
		$out = '';
		foreach ( str_split( $bits, 8 ) as $byte ) {
			if ( 8 === strlen( $byte ) ) {
				$out .= chr( bindec( $byte ) );
			}
		}
		return $out;
	}

	/** RFC 6238: six digits, 30-second steps, HMAC-SHA1 — what every authenticator app does. */
	public static function totp( $secret, $step ) {
		$h = hash_hmac( 'sha1', pack( 'N2', 0, $step ), self::b32_decode( $secret ), true );
		$o = ord( $h[19] ) & 0xf;
		$c = ( ( ( ord( $h[ $o ] ) & 0x7f ) << 24 ) | ( ord( $h[ $o + 1 ] ) << 16 ) | ( ord( $h[ $o + 2 ] ) << 8 ) | ord( $h[ $o + 3 ] ) ) % 1000000;
		return str_pad( (string) $c, 6, '0', STR_PAD_LEFT );
	}

	/** A code from the app (this step or the one before/after), never twice. */
	private static function totp_ok( $secret, $code, $last = 0 ) {
		$code = preg_replace( '/\D/', '', (string) $code );
		if ( 6 !== strlen( $code ) ) {
			return 0;
		}
		$now = (int) floor( time() / 30 );
		for ( $s = $now - 1; $s <= $now + 1; $s++ ) {
			if ( $s > $last && hash_equals( self::totp( $secret, $s ), $code ) ) {
				return $s;
			}
		}
		return 0;
	}

	private static function rc_hash( $code ) {
		return hash_hmac( 'sha256', strtolower( preg_replace( '/[^A-Za-z0-9]/', '', (string) $code ) ), wp_salt( 'auth' ) );
	}

	/** true (app code), 'rc' (a recovery code, now used up) or false. */
	private static function check_code( $user_id, $code ) {
		$d = get_user_meta( $user_id, self::TFA, true );
		if ( ! is_array( $d ) || empty( $d['s'] ) ) {
			return false;
		}
		$step = self::totp_ok( $d['s'], $code, (int) ( $d['last'] ?? 0 ) );
		if ( $step ) {
			$d['last'] = $step;
			update_user_meta( $user_id, self::TFA, $d );
			return true;
		}
		$h = self::rc_hash( $code );
		foreach ( (array) ( $d['rc'] ?? array() ) as $i => $rc ) {
			if ( hash_equals( $rc, $h ) ) {
				unset( $d['rc'][ $i ] );
				$d['rc'] = array_values( $d['rc'] );
				update_user_meta( $user_id, self::TFA, $d );
				return 'rc';
			}
		}
		return false;
	}

	private static function new_codes( $user_id ) {
		$codes = array();
		$hash  = array();
		for ( $i = 0; $i < 10; $i++ ) {
			$c       = strtolower( wp_generate_password( 4, false, false ) . '-' . wp_generate_password( 4, false, false ) );
			$codes[] = $c;
			$hash[]  = self::rc_hash( $c );
		}
		// Shown once, right after this request.
		set_transient( 'velox_2fa_show_' . $user_id, $codes, 10 * MINUTE_IN_SECONDS );
		return $hash;
	}

	private static function do_2fa_start() {
		$id = get_current_user_id();
		if ( ! get_user_meta( $id, self::TFA_P, true ) ) {
			update_user_meta( $id, self::TFA_P, self::b32_secret() );
		}
		self::go( 'security', '', array( 'setup' => 1 ) );
	}

	private static function do_2fa_confirm() {
		$id     = get_current_user_id();
		$secret = (string) get_user_meta( $id, self::TFA_P, true );
		$step   = '' !== $secret ? self::totp_ok( $secret, self::p( 'vx_code', 20 ) ) : 0;
		if ( ! $step ) {
			self::go( 'security', '2fa-bad', array( 'setup' => 1 ) );
		}
		update_user_meta( $id, self::TFA, array( 's' => $secret, 'on' => time(), 'last' => $step, 'rc' => self::new_codes( $id ) ) );
		delete_user_meta( $id, self::TFA_P );
		// Every other device has to sign in again, now with the code.
		WP_Session_Tokens::get_instance( $id )->destroy_others( wp_get_session_token() );
		self::go( 'security', '2fa-on' );
	}

	private static function do_2fa_off() {
		$u = wp_get_current_user();
		if ( ! wp_check_password( self::pw( 'vx_cur' ), $u->user_pass, $u->ID ) ) {
			self::go( 'security', 'pass-wrong' );
		}
		if ( ! self::check_code( $u->ID, self::p( 'vx_code', 40 ) ) ) {
			self::go( 'security', '2fa-bad' );
		}
		delete_user_meta( $u->ID, self::TFA );
		self::go( 'security', '2fa-off' );
	}

	private static function do_2fa_codes() {
		$u = wp_get_current_user();
		if ( ! wp_check_password( self::pw( 'vx_cur' ), $u->user_pass, $u->ID ) ) {
			self::go( 'security', 'pass-wrong' );
		}
		$d = get_user_meta( $u->ID, self::TFA, true );
		if ( is_array( $d ) && ! empty( $d['s'] ) ) {
			$d['rc'] = self::new_codes( $u->ID );
			update_user_meta( $u->ID, self::TFA, $d );
		}
		self::go( 'security', '2fa-codes' );
	}

	/** The shop owner turns it off for a customer who lost their phone. */
	public static function reset_2fa( $user_id ) {
		delete_user_meta( (int) $user_id, self::TFA );
		delete_user_meta( (int) $user_id, self::TFA_P );
	}

	public static function twofa_info( $user_id ) {
		$d = get_user_meta( (int) $user_id, self::TFA, true );
		return is_array( $d ) && ! empty( $d['s'] ) ? array( 'on' => (int) ( $d['on'] ?? 0 ), 'codes' => count( (array) ( $d['rc'] ?? array() ) ) ) : null;
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

	private static function icon( $name, $size = 18 ) {
		$p = array(
			'home'   => '<path d="M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6h-6v6H4a1 1 0 0 1-1-1z"/>',
			'bag'    => '<path d="M6 7h12l-1 12.2a1 1 0 0 1-1 .8H8a1 1 0 0 1-1-.8z"/><path d="M9 7V6a3 3 0 0 1 6 0v1"/>',
			'down'   => '<path d="M12 3v12m0 0-4.5-4.5M12 15l4.5-4.5M4 19h16"/>',
			'pin'    => '<path d="M12 21s-7-6.3-7-11.5a7 7 0 0 1 14 0C19 14.7 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>',
			'user'   => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
			'shield' => '<path d="M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6z"/>',
			'lock'   => '<rect x="4.5" y="10.5" width="15" height="10" rx="2"/><path d="M8 10.5V7.5a4 4 0 0 1 8 0v3"/>',
			'out'    => '<path d="M15 4h4a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1h-4M10 16l4-4-4-4M14 12H4"/>',
			'check'  => '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
			'arrow'  => '<path d="M5 12h14m-5-5 5 5-5 5"/>',
			'phone'  => '<rect x="6.5" y="2.5" width="11" height="19" rx="2.5"/><path d="M11 18h2"/>',
			'screen' => '<rect x="3" y="4.5" width="18" height="12" rx="2"/><path d="M8.5 20.5h7M12 16.5v4"/>',
			'key'    => '<circle cx="8" cy="15" r="4"/><path d="m11 12 8.5-8.5M16 7l2.5 2.5M14 9l2 2"/>',
			'copy'   => '<rect x="8" y="8" width="12" height="12" rx="2"/><path d="M16 8V5a1 1 0 0 0-1-1H5a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h3"/>',
		);
		return '<svg viewBox="0 0 24 24" width="' . (int) $size . '" height="' . (int) $size . '" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . ( $p[ $name ] ?? '' ) . '</svg>';
	}

	private static function initials( $u ) {
		$a = trim( (string) $u->first_name );
		$b = trim( (string) $u->last_name );
		$i = ( '' !== $a ? mb_substr( $a, 0, 1 ) : '' ) . ( '' !== $b ? mb_substr( $b, 0, 1 ) : '' );
		if ( '' === $i ) {
			$i = mb_substr( (string) $u->display_name, 0, 1 );
		}
		return mb_strtoupper( $i );
	}

	/** A settings section: what it is on the left, the controls on the right. */
	private static function sec( $title, $text, $body, $extra = '' ) {
		return '<section class="vxa-sec' . ( $extra ? ' ' . $extra : '' ) . '"><div class="vxa-sec-l"><h3>' . esc_html( $title ) . '</h3>' . ( '' !== $text ? '<p>' . esc_html( $text ) . '</p>' : '' ) . '</div><div class="vxa-sec-r">' . $body . '</div></section>';
	}

	private static $ids = null;
	private static function my_orders( $u ) {
		if ( null === self::$ids ) {
			self::$ids = self::order_ids( $u );
		}
		return self::$ids;
	}

	public static function render() {
		$tab = isset( $_GET['vxa'] ) ? sanitize_key( wp_unslash( $_GET['vxa'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! is_user_logged_in() ) {
			return '<div class="vxa vxa-out vxs-account">' . self::guest( $tab ) . '</div>';
		}
		$u    = wp_get_current_user();
		$ids  = self::my_orders( $u );
		$tabs = array(
			''          => array( 'home', __( 'Overview', 'velox' ) ),
			'orders'    => array( 'bag', __( 'Orders', 'velox' ) ),
			'downloads' => array( 'down', __( 'Downloads', 'velox' ) ),
			'address'   => array( 'pin', __( 'Address', 'velox' ) ),
			'details'   => array( 'user', __( 'Profile', 'velox' ) ),
			'security'  => array( 'lock', __( 'Security', 'velox' ) ),
			'privacy'   => array( 'shield', __( 'Privacy', 'velox' ) ),
		);
		if ( ! isset( $tabs[ $tab ] ) ) {
			$tab = '';
		}
		$since = wp_date( 'F Y', (int) strtotime( $u->user_registered . ' UTC' ) );
		$head  = '<header class="vxa-head"><span class="vxa-av" aria-hidden="true">' . esc_html( self::initials( $u ) ) . '</span><div class="vxa-who"><h2>' . esc_html( sprintf( /* translators: %s: first name */ __( 'Hello, %s', 'velox' ), $u->first_name ? $u->first_name : $u->display_name ) ) . '</h2>'
			. '<p>' . esc_html( $u->user_email ) . ' · ' . esc_html( sprintf( /* translators: %s: month and year */ __( 'Customer since %s', 'velox' ), $since ) ) . '</p></div>'
			. '<a class="vxa-signout" href="' . esc_url( wp_nonce_url( self::url( 'logout' ), 'velox_shop_logout' ) ) . '">' . self::icon( 'out', 17 ) . '<span>' . esc_html__( 'Sign out', 'velox' ) . '</span></a></header>';
		$nav = '<nav class="vxa-nav" aria-label="' . esc_attr__( 'Account', 'velox' ) . '"><ul>';
		foreach ( $tabs as $k => $t ) {
			$n    = 'orders' === $k && $ids ? '<i>' . count( $ids ) . '</i>' : '';
			$nav .= '<li><a href="' . esc_url( self::url( $k ) ) . '"' . ( $k === $tab ? ' aria-current="page"' : '' ) . '>' . self::icon( $t[0], 17 ) . '<span>' . esc_html( $t[1] ) . '</span>' . $n . '</a></li>';
		}
		$nav .= '</ul></nav>';
		switch ( $tab ) {
			case 'orders':
				$body = self::tab_orders( $u );
				break;
			case 'downloads':
				$body = self::tab_downloads( $u );
				break;
			case 'address':
				$body = self::tab_address( $u );
				break;
			case 'details':
				$body = self::tab_details( $u );
				break;
			case 'security':
				$body = self::tab_security( $u );
				break;
			case 'privacy':
				$body = self::tab_privacy( $u );
				break;
			default:
				$body = self::tab_overview( $u );
		}
		return '<div class="vxa vxs-account">' . $head . $nav . '<div class="vxa-body">' . self::note_html() . $body . '</div></div>';
	}

	private static function guest( $tab ) {
		$note = self::note_html();
		if ( '2fa' === $tab ) {
			$t = isset( $_GET['t'] ) ? preg_replace( '/[^A-Za-z0-9]/', '', (string) wp_unslash( $_GET['t'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( '' === $t || ! get_transient( 'velox_2fa_' . hash( 'sha256', $t ) ) ) {
				return '<div class="vxa-box"><div class="vxs-note is-err" role="alert">' . esc_html( self::notes()['2fa-expired'][1] ) . '</div><a class="vxs-btn vxs-btn-block" href="' . esc_url( self::url( 'login' ) ) . '">' . esc_html__( 'Sign in', 'velox' ) . '</a></div>';
			}
			return '<form method="post" class="vxa-box vxa-form vxa-2fa">' . self::nonce( 'login2' ) . '<input type="hidden" name="vx_t" value="' . esc_attr( $t ) . '">'
				. '<span class="vxa-badge">' . self::icon( 'phone', 22 ) . '</span><h2>' . esc_html__( 'Two-factor sign-in', 'velox' ) . '</h2>'
				. '<p class="vxa-lead">' . esc_html__( 'Open your authenticator app and enter the 6-digit code for this shop.', 'velox' ) . '</p>' . $note
				. '<label class="vxs-f"><span>' . esc_html__( 'Code', 'velox' ) . '</span><input class="vxa-code" type="text" name="vx_code" inputmode="numeric" autocomplete="one-time-code" maxlength="12" required autofocus placeholder="123 456"></label>'
				. '<button type="submit" class="vxs-btn vxs-btn-lg vxs-btn-block">' . esc_html__( 'Confirm and sign in', 'velox' ) . '</button>'
				. '<p class="vxa-small">' . esc_html__( 'No phone at hand? Enter one of your recovery codes instead.', 'velox' ) . '</p>'
				. '<p class="vxa-alt"><a href="' . esc_url( self::url( 'login' ) ) . '">← ' . esc_html__( 'Back to sign in', 'velox' ) . '</a></p></form>';
		}
		if ( 'reset' === $tab && isset( $_GET['key'], $_GET['login'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$key   = sanitize_text_field( wp_unslash( $_GET['key'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$login = sanitize_user( rawurldecode( wp_unslash( $_GET['login'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( is_wp_error( check_password_reset_key( $key, $login ) ) ) {
				return '<div class="vxa-box"><h2>' . esc_html__( 'Set a new password', 'velox' ) . '</h2><p class="vxa-lead">' . esc_html( self::notes()['reset-bad'][1] ) . '</p><a class="vxs-btn vxs-btn-block" href="' . esc_url( self::url( 'lost' ) ) . '">' . esc_html__( 'Ask for a new link', 'velox' ) . '</a></div>';
			}
			return '<form method="post" class="vxa-box vxa-form">' . self::nonce( 'reset' )
				. '<input type="hidden" name="vx_key" value="' . esc_attr( $key ) . '"><input type="hidden" name="vx_rlogin" value="' . esc_attr( $login ) . '">'
				. '<span class="vxa-badge">' . self::icon( 'key', 22 ) . '</span><h2>' . esc_html__( 'Set a new password', 'velox' ) . '</h2>' . $note
				. self::field( 'vx_pass', __( 'New password (at least 8 characters)', 'velox' ), 'password', '', 'required minlength="8" autocomplete="new-password"' )
				. self::field( 'vx_pass2', __( 'Repeat the new password', 'velox' ), 'password', '', 'required minlength="8" autocomplete="new-password"' )
				. '<button type="submit" class="vxs-btn vxs-btn-lg vxs-btn-block">' . esc_html__( 'Save password', 'velox' ) . '</button></form>';
		}
		if ( 'lost' === $tab ) {
			return '<form method="post" class="vxa-box vxa-form">' . self::nonce( 'lost' )
				. '<span class="vxa-badge">' . self::icon( 'key', 22 ) . '</span><h2>' . esc_html__( 'Forgotten your password?', 'velox' ) . '</h2><p class="vxa-lead">' . esc_html__( 'Enter your email address. We send you a link to set a new password.', 'velox' ) . '</p>' . $note
				. self::field( 'vx_login', __( 'Email', 'velox' ), 'email', '', 'required autocomplete="email"' )
				. '<button type="submit" class="vxs-btn vxs-btn-lg vxs-btn-block">' . esc_html__( 'Send the link', 'velox' ) . '</button>'
				. '<p class="vxa-alt"><a href="' . esc_url( self::url( 'login' ) ) . '">← ' . esc_html__( 'Back to sign in', 'velox' ) . '</a></p></form>';
		}
		$reg   = self::enabled();
		$is_rg = $reg && 'register' === $tab;
		$to    = isset( $_GET['redirect_to'] ) ? esc_url( wp_unslash( $_GET['redirect_to'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$mail  = isset( $_GET['email'] ) ? sanitize_email( wp_unslash( $_GET['email'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$priv  = (int) Velox_Shop::get( 'page_privacy' );
		$store = (string) Velox_Shop::get( 'store_name', get_bloginfo( 'name' ) );
		$why   = '<aside class="vxa-why"><h2>' . esc_html( $is_rg ? __( 'Your customer account', 'velox' ) : __( 'Welcome back', 'velox' ) ) . '</h2><p>' . esc_html( sprintf( /* translators: %s: shop name */ __( 'Everything you bought at %s, in one place.', 'velox' ), $store ) ) . '</p><ul>'
			. '<li>' . self::icon( 'bag', 18 ) . '<span>' . esc_html__( 'Orders, invoices and tracking at any time', 'velox' ) . '</span></li>'
			. '<li>' . self::icon( 'pin', 18 ) . '<span>' . esc_html__( 'Your address filled in at checkout', 'velox' ) . '</span></li>'
			. '<li>' . self::icon( 'down', 18 ) . '<span>' . esc_html__( 'Downloads always at hand', 'velox' ) . '</span></li>'
			. '<li>' . self::icon( 'lock', 18 ) . '<span>' . esc_html__( 'Protected with two-factor sign-in if you like', 'velox' ) . '</span></li></ul></aside>';
		$out = '<div class="vxa-auth">' . $why . '<div class="vxa-box">';
		if ( $reg ) {
			$out .= '<div class="vxa-switch" role="tablist"><a role="tab" href="' . esc_url( self::url( 'login' ) ) . '" aria-selected="' . ( $is_rg ? 'false' : 'true' ) . '">' . esc_html__( 'Sign in', 'velox' ) . '</a><a role="tab" href="' . esc_url( self::url( 'register' ) ) . '" aria-selected="' . ( $is_rg ? 'true' : 'false' ) . '">' . esc_html__( 'Create an account', 'velox' ) . '</a></div>';
		}
		$out .= $note;
		if ( $is_rg ) {
			$out .= '<form method="post" class="vxa-form">' . self::nonce( 'register' )
				. '<div class="vxs-fg vxs-fg-2">' . self::field( 'vx_first', __( 'First name', 'velox' ), 'text', '', 'required autocomplete="given-name"' ) . self::field( 'vx_last', __( 'Last name', 'velox' ), 'text', '', 'required autocomplete="family-name"' ) . '</div>'
				. self::field( 'vx_email', __( 'Email', 'velox' ), 'email', $mail, 'required autocomplete="email"' )
				. self::field( 'vx_pass', __( 'Password (at least 8 characters)', 'velox' ), 'password', '', 'required minlength="8" autocomplete="new-password"' )
				. '<input type="text" name="vx_website" value="" tabindex="-1" autocomplete="off" class="vxs-hp" aria-hidden="true">'
				. '<label class="vxs-check"><input type="checkbox" name="vx_privacy" value="1" required> <span>' . ( $priv
					? sprintf( /* translators: %s: privacy policy link */ esc_html__( 'I have read the %s.', 'velox' ), '<a href="' . esc_url( get_permalink( $priv ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'privacy policy', 'velox' ) . '</a>' )
					: esc_html__( 'I agree that my data is stored for my customer account.', 'velox' ) ) . '</span></label>'
				. '<button type="submit" class="vxs-btn vxs-btn-lg vxs-btn-block">' . esc_html__( 'Create my account', 'velox' ) . '</button></form>';
		} else {
			$out .= '<form method="post" class="vxa-form">' . self::nonce( 'login' ) . ( $to ? '<input type="hidden" name="vx_to" value="' . esc_attr( $to ) . '">' : '' )
				. self::field( 'vx_login', __( 'Email', 'velox' ), 'text', $mail, 'required autocomplete="username"' )
				. self::field( 'vx_pass', __( 'Password', 'velox' ), 'password', '', 'required autocomplete="current-password"' )
				. '<div class="vxa-row"><label class="vxs-check"><input type="checkbox" name="vx_remember" value="1"> <span>' . esc_html__( 'Stay signed in', 'velox' ) . '</span></label><a href="' . esc_url( self::url( 'lost' ) ) . '">' . esc_html__( 'Forgotten your password?', 'velox' ) . '</a></div>'
				. '<button type="submit" class="vxs-btn vxs-btn-lg vxs-btn-block">' . esc_html__( 'Sign in', 'velox' ) . '</button></form>';
		}
		$out .= '<p class="vxa-small">' . esc_html__( 'Ordered without an account? The link in your confirmation email always shows your order.', 'velox' ) . '</p></div></div>';
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

	/** Ordered → paid → shipped → delivered, as far as this order got. */
	private static function progress( $o ) {
		$pay  = $o['payment']['status'];
		$ful  = $o['fulfil']['status'];
		$paid = in_array( $pay, array( 'paid', 'partial', 'refunded' ), true );
		if ( 'cancelled' === $ful || 'cancelled' === $pay ) {
			return '<p class="vxa-state is-off">' . esc_html__( 'Cancelled', 'velox' ) . '</p>';
		}
		$steps = array( array( __( 'Ordered', 'velox' ), true ), array( __( 'Paid', 'velox' ), $paid ) );
		if ( 'none' === $ful || 'done' === $ful ) {
			$steps[] = array( __( 'Complete', 'velox' ), 'done' === $ful || ( 'none' === $ful && $paid ) );
		} else {
			$pickup  = $o['shipping'] && 'pickup' === ( $o['shipping']['type'] ?? '' );
			$steps[] = array( $pickup ? __( 'Ready for pickup', 'velox' ) : __( 'Shipped', 'velox' ), in_array( $ful, array( 'shipped', 'ready', 'delivered' ), true ) );
			$steps[] = array( __( 'Delivered', 'velox' ), 'delivered' === $ful );
		}
		$out = '<ol class="vxa-steps">';
		foreach ( $steps as $st ) {
			$out .= '<li class="' . ( $st[1] ? 'is-done' : '' ) . '"><i>' . ( $st[1] ? self::icon( 'check', 13 ) : '' ) . '</i><span>' . esc_html( $st[0] ) . '</span></li>';
		}
		return $out . '</ol>';
	}

	private static function tab_overview( $u ) {
		$ids   = self::my_orders( $u );
		$addr  = array_filter( (array) get_user_meta( $u->ID, self::META, true ) );
		$open  = 0;
		$spent = 0;
		$last  = null;
		foreach ( $ids as $id ) {
			$o = Velox_Shop::order( $id );
			if ( ! $o ) {
				continue;
			}
			$last = $last ? $last : $o;
			if ( in_array( $o['payment']['status'], array( 'paid', 'partial' ), true ) ) {
				$spent += $o['total'] - (float) $o['payment']['refunded'];
			}
			if ( ! in_array( $o['fulfil']['status'], array( 'delivered', 'done', 'cancelled', 'none' ), true ) && 'cancelled' !== $o['payment']['status'] ) {
				$open++;
			}
		}
		$out = '';
		if ( ! self::has_2fa( $u->ID ) ) {
			$out .= '<div class="vxa-nudge"><span class="vxa-badge">' . self::icon( 'shield', 20 ) . '</span><div><b>' . esc_html__( 'Protect your account', 'velox' ) . '</b><span>' . esc_html__( 'With two-factor sign-in, a code from your phone is needed besides your password.', 'velox' ) . '</span></div><a class="vxs-btn vxs-btn-sm" href="' . esc_url( self::url( 'security' ) ) . '">' . esc_html__( 'Set up', 'velox' ) . '</a></div>';
		}
		$out .= '<div class="vxa-stats">'
			. '<a href="' . esc_url( self::url( 'orders' ) ) . '"><span>' . esc_html__( 'Orders', 'velox' ) . '</span><b>' . count( $ids ) . '</b></a>'
			. '<a href="' . esc_url( self::url( 'orders' ) ) . '"><span>' . esc_html__( 'In progress', 'velox' ) . '</span><b>' . (int) $open . '</b></a>'
			. '<div><span>' . esc_html__( 'Spent so far', 'velox' ) . '</span><b>' . Velox_Shop_Front::money( $spent ) . '</b></div></div>';
		if ( $last ) {
			$href = self::url( 'orders', array( 'vxo' => $last['key'] ) );
			$names = array();
			foreach ( array_slice( (array) $last['items'], 0, 3 ) as $it ) {
				$names[] = (int) ( $it['qty'] ?? 1 ) . '× ' . (string) ( $it['name'] ?? '' );
			}
			$out .= '<section class="vxa-card"><div class="vxa-card-h"><h3>' . esc_html__( 'Latest order', 'velox' ) . '</h3><a href="' . esc_url( self::url( 'orders' ) ) . '">' . esc_html__( 'All orders', 'velox' ) . self::icon( 'arrow', 15 ) . '</a></div>'
				. '<a class="vxa-last" href="' . esc_url( $href ) . '"><div><b>' . esc_html( $last['number'] ) . '</b><span>' . esc_html( wp_date( get_option( 'date_format' ), (int) $last['created'] ) ) . ' · ' . esc_html( implode( ', ', $names ) ) . ( count( (array) $last['items'] ) > 3 ? ' …' : '' ) . '</span></div><strong>' . Velox_Shop_Front::money( $last['total'] ) . '</strong></a>'
				. self::progress( $last ) . '</section>';
		} else {
			$out .= '<section class="vxa-card vxa-empty"><span class="vxa-badge">' . self::icon( 'bag', 20 ) . '</span><h3>' . esc_html__( 'No orders yet', 'velox' ) . '</h3><p>' . esc_html__( 'As soon as you order, you can follow it here.', 'velox' ) . '</p><a class="vxs-btn vxs-btn-sm" href="' . esc_url( Velox_Shop_Front::page_url( 'shop', home_url( '/' ) ) ) . '">' . esc_html__( 'Go to the shop', 'velox' ) . '</a></section>';
		}
		$tile = function ( $tab, $icon, $title, $state, $ok ) {
			return '<a class="vxa-tile" href="' . esc_url( self::url( $tab ) ) . '">' . self::icon( $icon, 20 ) . '<span><b>' . esc_html( $title ) . '</b><small class="' . ( $ok ? 'is-ok' : '' ) . '">' . esc_html( $state ) . '</small></span>' . self::icon( 'arrow', 16 ) . '</a>';
		};
		$out .= '<div class="vxa-tiles">'
			. $tile( 'address', 'pin', __( 'Address', 'velox' ), $addr ? trim( ( $addr['vx_line1'] ?? '' ) . ', ' . ( $addr['vx_city'] ?? '' ), ', ' ) : __( 'Not saved yet', 'velox' ), (bool) $addr )
			. $tile( 'security', 'lock', __( 'Security', 'velox' ), self::has_2fa( $u->ID ) ? __( 'Two-factor sign-in is on', 'velox' ) : __( 'Password only', 'velox' ), self::has_2fa( $u->ID ) )
			. $tile( 'details', 'user', __( 'Profile', 'velox' ), $u->display_name, true )
			. '</div>';
		return $out;
	}

	private static function tab_orders( $u ) {
		if ( ! empty( $_GET['vxo'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$o = Velox_Shop::order_by_key( sanitize_text_field( wp_unslash( $_GET['vxo'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( $o && ( (int) ( $o['user'] ?? 0 ) === (int) $u->ID || strtolower( (string) ( $o['customer']['email'] ?? '' ) ) === strtolower( $u->user_email ) ) ) {
				return '<p class="vxa-back"><a href="' . esc_url( self::url( 'orders' ) ) . '">← ' . esc_html__( 'All orders', 'velox' ) . '</a></p>' . self::progress( $o ) . Velox_Shop_Front::order_view( $o );
			}
		}
		$ids = self::my_orders( $u );
		return '<div class="vxa-title"><h3>' . esc_html__( 'Orders', 'velox' ) . '</h3></div>' . ( $ids ? self::order_rows( $ids ) : '<section class="vxa-card vxa-empty"><span class="vxa-badge">' . self::icon( 'bag', 20 ) . '</span><h3>' . esc_html__( 'No orders yet', 'velox' ) . '</h3><a class="vxs-btn vxs-btn-sm" href="' . esc_url( Velox_Shop_Front::page_url( 'shop', home_url( '/' ) ) ) . '">' . esc_html__( 'Go to the shop', 'velox' ) . '</a></section>' );
	}

	private static function tab_downloads( $u ) {
		$out = '';
		foreach ( self::my_orders( $u ) as $id ) {
			$o = Velox_Shop::order( $id );
			if ( ! $o ) {
				continue;
			}
			$links = Velox_Shop_Front::download_links( $o );
			if ( '' !== trim( wp_strip_all_tags( $links ) ) ) {
				$out .= '<section class="vxa-card"><div class="vxa-card-h"><h3>' . esc_html( $o['number'] ) . '</h3><span>' . esc_html( wp_date( get_option( 'date_format' ), (int) $o['created'] ) ) . '</span></div>' . $links . '</section>';
			}
		}
		return '<div class="vxa-title"><h3>' . esc_html__( 'Downloads', 'velox' ) . '</h3></div>' . ( '' !== $out ? $out : '<section class="vxa-card vxa-empty"><span class="vxa-badge">' . self::icon( 'down', 20 ) . '</span><p>' . esc_html__( 'Files you buy show up here as soon as the payment has arrived.', 'velox' ) . '</p></section>' );
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
		return self::sec( __( 'Address', 'velox' ), __( 'Used for invoices and delivery — and filled in for you at checkout.', 'velox' ),
			'<form method="post" class="vxa-form">' . self::nonce( 'address' )
			. '<div class="vxs-fg vxs-fg-2">' . self::field( 'vx_first', __( 'First name', 'velox' ), 'text', $v( 'vx_first' ), 'autocomplete="given-name"' ) . self::field( 'vx_last', __( 'Last name', 'velox' ), 'text', $v( 'vx_last' ), 'autocomplete="family-name"' ) . '</div>'
			. '<div class="vxs-fg vxs-fg-2">' . self::field( 'vx_company', __( 'Company (optional)', 'velox' ), 'text', $v( 'vx_company' ), 'autocomplete="organization"' ) . self::field( 'vx_phone', __( 'Phone (optional)', 'velox' ), 'tel', $v( 'vx_phone' ), 'autocomplete="tel"' ) . '</div>'
			. self::field( 'vx_line1', __( 'Street and number', 'velox' ), 'text', $v( 'vx_line1' ), 'autocomplete="address-line1"' )
			. self::field( 'vx_line2', __( 'Address line 2 (optional)', 'velox' ), 'text', $v( 'vx_line2' ), 'autocomplete="address-line2"' )
			. '<div class="vxs-fg vxs-fg-zip">' . self::field( 'vx_zip', __( 'Postcode', 'velox' ), 'text', $v( 'vx_zip' ), 'autocomplete="postal-code"' ) . self::field( 'vx_city', __( 'Town', 'velox' ), 'text', $v( 'vx_city' ), 'autocomplete="address-level2"' ) . '</div>'
			. '<label class="vxs-f"><span>' . esc_html__( 'Country', 'velox' ) . '</span><select name="vx_country" autocomplete="country">' . $opts . '</select></label>'
			. '<div class="vxa-actions"><button type="submit" class="vxs-btn">' . esc_html__( 'Save address', 'velox' ) . '</button></div></form>' );
	}

	private static function tab_details( $u ) {
		return self::sec( __( 'Name', 'velox' ), __( 'How we address you, and the name on new invoices.', 'velox' ),
				'<form method="post" class="vxa-form">' . self::nonce( 'profile' )
				. '<div class="vxs-fg vxs-fg-2">' . self::field( 'vx_first', __( 'First name', 'velox' ), 'text', $u->first_name, 'required autocomplete="given-name"' ) . self::field( 'vx_last', __( 'Last name', 'velox' ), 'text', $u->last_name, 'required autocomplete="family-name"' ) . '</div>'
				. '<div class="vxa-actions"><button type="submit" class="vxs-btn">' . esc_html__( 'Save', 'velox' ) . '</button></div></form>' )
			. self::sec( __( 'Email address', 'velox' ), __( 'You sign in with it and get order emails there. Your old address is told about the change.', 'velox' ),
				'<form method="post" class="vxa-form">' . self::nonce( 'email' )
				. self::field( 'vx_email', __( 'New email address', 'velox' ), 'email', $u->user_email, 'required autocomplete="email"' )
				. self::field( 'vx_cur', __( 'Your current password', 'velox' ), 'password', '', 'required autocomplete="current-password"' )
				. '<div class="vxa-actions"><button type="submit" class="vxs-btn">' . esc_html__( 'Change email', 'velox' ) . '</button></div></form>' );
	}

	private static function ua_label( $ua ) {
		$ua = (string) $ua;
		$b  = preg_match( '/Edg\\//', $ua ) ? 'Edge' : ( preg_match( '/OPR\\//', $ua ) ? 'Opera' : ( preg_match( '/Firefox\\//', $ua ) ? 'Firefox' : ( preg_match( '/Chrome\\//', $ua ) ? 'Chrome' : ( preg_match( '/Safari\\//', $ua ) ? 'Safari' : __( 'Browser', 'velox' ) ) ) ) );
		$os = preg_match( '/iPhone/', $ua ) ? 'iPhone' : ( preg_match( '/iPad/', $ua ) ? 'iPad' : ( preg_match( '/Android/', $ua ) ? 'Android' : ( preg_match( '/Windows/', $ua ) ? 'Windows' : ( preg_match( '/Mac OS X|Macintosh/', $ua ) ? 'macOS' : ( preg_match( '/Linux/', $ua ) ? 'Linux' : '' ) ) ) ) );
		/* translators: 1: browser, 2: operating system */
		return array( '' !== $os ? sprintf( __( '%1$s on %2$s', 'velox' ), $b, $os ) : $b, (bool) preg_match( '/iPhone|Android|Mobile/', $ua ) );
	}

	private static function tab_security( $u ) {
		$out = self::sec( __( 'Password', 'velox' ), __( 'Use one you do not use anywhere else — at least 8 characters.', 'velox' ),
			'<form method="post" class="vxa-form">' . self::nonce( 'password' )
			. self::field( 'vx_cur', __( 'Current password', 'velox' ), 'password', '', 'required autocomplete="current-password"' )
			. '<div class="vxs-fg vxs-fg-2">' . self::field( 'vx_pass', __( 'New password', 'velox' ), 'password', '', 'required minlength="8" autocomplete="new-password"' ) . self::field( 'vx_pass2', __( 'Repeat the new password', 'velox' ), 'password', '', 'required minlength="8" autocomplete="new-password"' ) . '</div>'
			. '<div class="vxa-actions"><button type="submit" class="vxs-btn">' . esc_html__( 'Change password', 'velox' ) . '</button></div></form>' );

		// Two-factor sign-in.
		$info    = self::twofa_info( $u->ID );
		$pending = (string) get_user_meta( $u->ID, self::TFA_P, true );
		$codes   = get_transient( 'velox_2fa_show_' . $u->ID );
		if ( $codes ) {
			delete_transient( 'velox_2fa_show_' . $u->ID );
		}
		if ( $info ) {
			$body = '<div class="vxa-status is-on">' . self::icon( 'check', 16 ) . '<span><b>' . esc_html__( 'On', 'velox' ) . '</b> · ' . esc_html( sprintf( /* translators: %s: date */ __( 'since %s', 'velox' ), wp_date( get_option( 'date_format' ), $info['on'] ) ) ) . ' · ' . esc_html( sprintf( /* translators: %d: number of codes */ _n( '%d recovery code left', '%d recovery codes left', $info['codes'], 'velox' ), $info['codes'] ) ) . '</span></div>';
			if ( $codes ) {
				$txt   = implode( "\n", $codes );
				$body .= '<div class="vxa-codes"><b>' . esc_html__( 'Your recovery codes', 'velox' ) . '</b><p>' . esc_html__( 'Each code works once, if you ever cannot use your phone. Save them now — they are shown only this one time.', 'velox' ) . '</p><ul>';
				foreach ( $codes as $c ) {
					$body .= '<li><code>' . esc_html( $c ) . '</code></li>';
				}
				$body .= '</ul><div class="vxa-actions"><button type="button" class="vxs-btn vxs-btn-sm vxs-btn-ghost" data-vxa-copy="' . esc_attr( $txt ) . '" data-done="' . esc_attr__( 'Copied', 'velox' ) . '">' . self::icon( 'copy', 15 ) . esc_html__( 'Copy', 'velox' ) . '</button><a class="vxs-btn vxs-btn-sm vxs-btn-ghost" download="recovery-codes.txt" href="data:text/plain;charset=utf-8,' . rawurlencode( $txt ) . '">' . self::icon( 'down', 15 ) . esc_html__( 'Download', 'velox' ) . '</a></div></div>';
			}
			$body .= '<details class="vxa-more"><summary>' . esc_html__( 'New recovery codes', 'velox' ) . '</summary><form method="post" class="vxa-form">' . self::nonce( '2fa_codes' )
				. '<p class="vxa-small">' . esc_html__( 'Makes 10 new codes; the old ones stop working.', 'velox' ) . '</p>'
				. self::field( 'vx_cur', __( 'Your password', 'velox' ), 'password', '', 'required autocomplete="current-password"' )
				. '<div class="vxa-actions"><button type="submit" class="vxs-btn vxs-btn-sm">' . esc_html__( 'Make new codes', 'velox' ) . '</button></div></form></details>'
				. '<details class="vxa-more"><summary>' . esc_html__( 'Turn off two-factor sign-in', 'velox' ) . '</summary><form method="post" class="vxa-form">' . self::nonce( '2fa_off' )
				. '<div class="vxs-fg vxs-fg-2">' . self::field( 'vx_cur', __( 'Your password', 'velox' ), 'password', '', 'required autocomplete="current-password"' )
				. '<label class="vxs-f"><span>' . esc_html__( 'Code from the app', 'velox' ) . '</span><input type="text" name="vx_code" inputmode="numeric" autocomplete="one-time-code" maxlength="12" required></label></div>'
				. '<div class="vxa-actions"><button type="submit" class="vxs-btn vxs-btn-sm vxs-btn-danger">' . esc_html__( 'Turn off', 'velox' ) . '</button></div></form></details>';
		} elseif ( '' !== $pending ) {
			$store = (string) Velox_Shop::get( 'store_name', get_bloginfo( 'name' ) );
			$uri   = 'otpauth://totp/' . rawurlencode( $store . ':' . $u->user_email ) . '?secret=' . $pending . '&issuer=' . rawurlencode( $store ) . '&digits=6&period=30';
			$body  = '<ol class="vxa-setup"><li><b>' . esc_html__( 'Scan the code', 'velox' ) . '</b><p>' . esc_html__( 'with an authenticator app — for example Google Authenticator, Microsoft Authenticator, 1Password or the passwords app on your iPhone.', 'velox' ) . '</p>'
				. '<div class="vxa-qr">' . Velox_Qr::svg( $uri, 176 ) . '<div><span>' . esc_html__( 'Cannot scan? Enter this key:', 'velox' ) . '</span><code>' . esc_html( trim( chunk_split( $pending, 4, ' ' ) ) ) . '</code></div></div></li>'
				. '<li><b>' . esc_html__( 'Enter the code', 'velox' ) . '</b><p>' . esc_html__( 'the app now shows for this shop.', 'velox' ) . '</p>'
				. '<form method="post" class="vxa-form vxa-inline">' . self::nonce( '2fa_confirm' ) . '<input class="vxa-code" type="text" name="vx_code" inputmode="numeric" autocomplete="one-time-code" maxlength="7" required placeholder="123456" aria-label="' . esc_attr__( 'Code', 'velox' ) . '"><button type="submit" class="vxs-btn">' . esc_html__( 'Turn on', 'velox' ) . '</button></form></li></ol>'
				. '<form method="post" class="vxa-cancel">' . self::nonce( '2fa_cancel' ) . '<button type="submit" class="vxa-link">' . esc_html__( 'Cancel', 'velox' ) . '</button></form>';
		} else {
			$body = '<div class="vxa-status">' . self::icon( 'lock', 16 ) . '<span><b>' . esc_html__( 'Off', 'velox' ) . '</b> · ' . esc_html__( 'your password alone signs you in', 'velox' ) . '</span></div>'
				. '<form method="post">' . self::nonce( '2fa_start' ) . '<button type="submit" class="vxs-btn">' . self::icon( 'phone', 17 ) . esc_html__( 'Set up two-factor sign-in', 'velox' ) . '</button></form>';
		}
		$out .= self::sec( __( 'Two-factor sign-in', 'velox' ), __( 'Besides your password, a 6-digit code from an app on your phone. Even someone who knows your password cannot get in.', 'velox' ), $body, 'vxa-sec-2fa' );

		// Signed-in devices.
		$raw   = get_user_meta( $u->ID, 'session_tokens', true );
		$curv  = function_exists( 'hash' ) ? hash( 'sha256', wp_get_session_token() ) : '';
		$items = '';
		$count = 0;
		if ( is_array( $raw ) ) {
			uasort( $raw, function ( $a, $b ) { return (int) ( $b['login'] ?? 0 ) <=> (int) ( $a['login'] ?? 0 ); } );
			foreach ( $raw as $verifier => $sess ) {
				if ( (int) ( $sess['expiration'] ?? 0 ) < time() ) {
					continue;
				}
				$count++;
				list( $label, $mobile ) = self::ua_label( $sess['ua'] ?? '' );
				$ip    = (string) ( $sess['ip'] ?? '' );
				$ip    = preg_replace( '/\\.\\d+$/', '.•', preg_replace( '/:[0-9a-f]*:[0-9a-f]*$/i', ':•', $ip ) );
				$here  = hash_equals( (string) $verifier, $curv );
				$items .= '<li>' . self::icon( $mobile ? 'phone' : 'screen', 20 ) . '<span><b>' . esc_html( $label ) . ( $here ? ' <em>' . esc_html__( 'this device', 'velox' ) . '</em>' : '' ) . '</b><small>' . esc_html( sprintf( /* translators: %s: date and time */ __( 'Signed in %s', 'velox' ), wp_date( get_option( 'date_format' ) . ', ' . get_option( 'time_format' ), (int) ( $sess['login'] ?? 0 ) ) ) ) . ( '' !== $ip ? ' · ' . esc_html( $ip ) : '' ) . '</small></span></li>';
			}
		}
		$out .= self::sec( __( 'Signed-in devices', 'velox' ), __( 'Where your account is signed in right now. Something you do not recognise? Sign out everywhere else and change your password.', 'velox' ),
			( '' !== $items ? '<ul class="vxa-devices">' . $items . '</ul>' : '' )
			. ( $count > 1 ? '<form method="post" class="vxa-actions">' . self::nonce( 'others_out' ) . '<button type="submit" class="vxs-btn vxs-btn-ghost">' . esc_html__( 'Sign out on all other devices', 'velox' ) . '</button></form>' : '' ) );
		return $out;
	}

	private static function tab_privacy( $u ) {
		$staff = user_can( $u, 'edit_posts' );
		$out   = self::sec( __( 'Download my data', 'velox' ), __( 'A file with everything the shop has stored about you: account, address and orders.', 'velox' ),
			'<form method="post">' . self::nonce( 'export' ) . '<button type="submit" class="vxs-btn vxs-btn-ghost">' . self::icon( 'down', 17 ) . esc_html__( 'Download (JSON)', 'velox' ) . '</button></form>' );
		if ( $staff ) {
			return $out . self::sec( __( 'Delete my account', 'velox' ), '', '<p class="vxa-small">' . esc_html__( 'This is a staff account: it can only be deleted in WordPress.', 'velox' ) . '</p>' );
		}
		return $out . self::sec( __( 'Delete my account', 'velox' ), __( 'Your account and saved address are deleted. Orders are kept as long as tax law requires (10 years), but no longer linked to an account. This cannot be undone.', 'velox' ),
			'<form method="post" class="vxa-form">' . self::nonce( 'delete' )
			. self::field( 'vx_cur', __( 'Your password', 'velox' ), 'password', '', 'required autocomplete="current-password"' )
			. '<label class="vxs-check"><input type="checkbox" name="vx_sure" value="1" required> <span>' . esc_html__( 'Yes, delete my account.', 'velox' ) . '</span></label>'
			. '<div class="vxa-actions"><button type="submit" class="vxs-btn vxs-btn-danger">' . esc_html__( 'Delete account', 'velox' ) . '</button></div></form>', 'vxa-sec-danger' );
	}

	/* ------------------------------------------------------------------ header button */

	/**
	 * The account icon for the header. With a mouse, hovering (or tabbing to
	 * it) opens a small menu; on touch screens it is a plain link to the page.
	 */
	public static function sc_button( $atts = array() ) {
		$a    = shortcode_atts( array( 'label' => '', 'menu' => '1' ), $atts, 'velox_account_button' );
		$in   = is_user_logged_in();
		$u    = $in ? wp_get_current_user() : null;
		$l    = '' !== $a['label'] ? $a['label'] : ( $in ? __( 'My account', 'velox' ) : __( 'Sign in', 'velox' ) );
		$face = $in ? '<span class="vxa-ini" aria-hidden="true">' . esc_html( self::initials( $u ) ) . '</span>' : self::icon( 'user' );
		$btn  = '<a class="vxs-accbtn' . ( $in ? ' is-in' : '' ) . '" href="' . esc_url( self::url() ) . '" aria-label="' . esc_attr( $l ) . '">' . $face . ( '' !== $a['label'] ? '<span>' . esc_html( $l ) . '</span>' : '' ) . '</a>';
		if ( '0' === (string) $a['menu'] ) {
			return $btn;
		}
		if ( $in ) {
			$n   = count( self::my_orders( $u ) );
			$pop = '<div class="vxa-pop-h"><span class="vxa-av" aria-hidden="true">' . esc_html( self::initials( $u ) ) . '</span><span><b>' . esc_html( $u->display_name ) . '</b><small>' . esc_html( $u->user_email ) . '</small></span></div>'
				. '<a href="' . esc_url( self::url() ) . '">' . self::icon( 'home', 17 ) . esc_html__( 'Overview', 'velox' ) . '</a>'
				. '<a href="' . esc_url( self::url( 'orders' ) ) . '">' . self::icon( 'bag', 17 ) . esc_html__( 'Orders', 'velox' ) . ( $n ? '<i>' . $n . '</i>' : '' ) . '</a>'
				. '<a href="' . esc_url( self::url( 'address' ) ) . '">' . self::icon( 'pin', 17 ) . esc_html__( 'Address', 'velox' ) . '</a>'
				. '<a href="' . esc_url( self::url( 'security' ) ) . '">' . self::icon( 'lock', 17 ) . esc_html__( 'Security', 'velox' ) . '</a>'
				. '<hr><a href="' . esc_url( wp_nonce_url( self::url( 'logout' ), 'velox_shop_logout' ) ) . '">' . self::icon( 'out', 17 ) . esc_html__( 'Sign out', 'velox' ) . '</a>';
		} else {
			$pop = '<div class="vxa-pop-g"><b>' . esc_html__( 'Your customer account', 'velox' ) . '</b><span>' . esc_html__( 'Orders, invoices and downloads in one place — and faster checkout.', 'velox' ) . '</span></div>'
				. '<a class="vxs-btn vxs-btn-block" href="' . esc_url( self::url( 'login' ) ) . '">' . esc_html__( 'Sign in', 'velox' ) . '</a>'
				. ( self::enabled() ? '<a class="vxa-pop-alt" href="' . esc_url( self::url( 'register' ) ) . '">' . esc_html__( 'New here? Create an account', 'velox' ) . '</a>' : '' );
		}
		return '<div class="vxa-menu">' . $btn . '<div class="vxa-pop' . ( $in ? '' : ' is-guest' ) . '">' . $pop . '</div></div>';
	}
}
