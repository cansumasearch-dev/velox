<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Velox Login Guard — limit failed sign-in attempts.
 *
 * Two decisions worth understanding before changing anything here:
 *
 *  1. LOCKOUTS ARE BY IP, NOT BY USERNAME. Locking an account after N failures
 *     sounds safer but hands anyone on the internet a way to lock the owner out
 *     of their own site: type the username, fail five times, done. Locking the
 *     source of the attempts has no such hole. The username is recorded for the
 *     log, never used as the key.
 *
 *  2. THERE IS ALWAYS A WAY BACK IN. A lock that only an admin can lift is
 *     exactly the kind that traps the admin outside. Every lockout emails the
 *     site administrator a one-click unlock link, valid for 24 hours, so the
 *     person who owns the site can never be permanently shut out by this.
 *
 * @package Velox
 */
class Velox_Login_Guard {

	const OPT_LOCKS = 'velox_login_locks';
	const OPT_FAILS = 'velox_login_fails';
	const MAX_LOG   = 200;

	public static function init() {
		if ( ! Velox_Settings::get( 'util_loginguard' ) ) {
			return;
		}
		add_filter( 'authenticate', array( __CLASS__, 'gate' ), 30, 3 );
		add_action( 'wp_login_failed', array( __CLASS__, 'on_fail' ) );
		add_action( 'wp_login', array( __CLASS__, 'on_success' ), 10, 2 );
		add_action( 'init', array( __CLASS__, 'maybe_unlock_by_link' ) );
	}

	/* ------------------------------------------------------------------- ip */

	/**
	 * The visitor's address. Proxy headers are only trusted when the site says
	 * it sits behind a proxy — otherwise anyone could spoof X-Forwarded-For and
	 * walk straight past a lockout by inventing a new address per attempt.
	 */
	public static function ip() {
		$remote = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '';
		if ( Velox_Settings::get( 'loginguard_behind_proxy' ) ) {
			foreach ( array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR' ) as $h ) {
				if ( ! empty( $_SERVER[ $h ] ) ) {
					$parts = explode( ',', (string) $_SERVER[ $h ] );
					$cand  = trim( $parts[0] );
					if ( filter_var( $cand, FILTER_VALIDATE_IP ) ) {
						return $cand;
					}
				}
			}
		}
		return filter_var( $remote, FILTER_VALIDATE_IP ) ? $remote : '0.0.0.0';
	}

	private static function key( $ip ) {
		return md5( $ip );
	}

	/* ---------------------------------------------------------------- state */

	public static function locks() {
		$l = get_option( self::OPT_LOCKS, array() );
		return is_array( $l ) ? $l : array();
	}

	public static function is_locked( $ip ) {
		$locks = self::locks();
		return isset( $locks[ self::key( $ip ) ] );
	}

	public static function unlock( $ip_or_key ) {
		$locks = self::locks();
		$k     = isset( $locks[ $ip_or_key ] ) ? $ip_or_key : self::key( $ip_or_key );
		if ( isset( $locks[ $k ] ) ) {
			unset( $locks[ $k ] );
			update_option( self::OPT_LOCKS, $locks, false );
			$fails = get_option( self::OPT_FAILS, array() );
			unset( $fails[ $k ] );
			update_option( self::OPT_FAILS, $fails, false );
			return true;
		}
		return false;
	}

	public static function unlock_all() {
		update_option( self::OPT_LOCKS, array(), false );
		update_option( self::OPT_FAILS, array(), false );
	}

	/* ----------------------------------------------------------------- gate */

	/**
	 * Runs before WordPress checks the password. A locked address is refused
	 * without the credentials ever being evaluated, so a lockout also stops the
	 * guessing rather than merely reporting it.
	 */
	public static function gate( $user, $username, $password ) {
		if ( '' === $username && '' === $password ) {
			return $user; // not a login attempt
		}
		$ip = self::ip();
		if ( ! self::is_locked( $ip ) ) {
			return $user;
		}
		$locks = self::locks();
		$lock  = $locks[ self::key( $ip ) ];
		return new WP_Error(
			'velox_locked',
			sprintf(
				/* translators: %s: number of failed attempts */
				__( '<strong>Signing in is blocked from this address.</strong> There were %s failed attempts. An administrator has to unlock it before you can try again.', 'velox' ),
				'<strong>' . (int) ( $lock['count'] ?? 0 ) . '</strong>'
			)
		);
	}

	public static function on_fail( $username ) {
		$ip = self::ip();
		if ( self::is_locked( $ip ) ) {
			return;
		}
		$limit = max( 1, (int) Velox_Settings::get( 'loginguard_max', 5 ) );
		$fails = get_option( self::OPT_FAILS, array() );
		$fails = is_array( $fails ) ? $fails : array();
		$k     = self::key( $ip );
		$row   = $fails[ $k ] ?? array( 'count' => 0, 'ip' => $ip, 'first' => time() );
		$row['count']  = (int) $row['count'] + 1;
		$row['last']   = time();
		$row['user']   = sanitize_user( (string) $username, true );
		$fails[ $k ]   = $row;

		if ( $row['count'] >= $limit ) {
			unset( $fails[ $k ] );
			self::lock( $ip, $row['count'], $row['user'] );
		}
		update_option( self::OPT_FAILS, $fails, false );
	}

	/** A clean sign-in clears the counter for that address. */
	public static function on_success( $login, $user ) {
		$fails = get_option( self::OPT_FAILS, array() );
		$k     = self::key( self::ip() );
		if ( is_array( $fails ) && isset( $fails[ $k ] ) ) {
			unset( $fails[ $k ] );
			update_option( self::OPT_FAILS, $fails, false );
		}
	}

	private static function lock( $ip, $count, $username ) {
		$locks = self::locks();
		$token = wp_generate_password( 24, false, false );
		$locks[ self::key( $ip ) ] = array(
			'ip'    => $ip,
			'count' => (int) $count,
			'user'  => (string) $username,
			'when'  => time(),
			'token' => $token,
		);
		// Keep the list from growing without bound under a sustained attack.
		if ( count( $locks ) > self::MAX_LOG ) {
			uasort( $locks, function ( $a, $b ) {
				return ( $a['when'] ?? 0 ) <=> ( $b['when'] ?? 0 );
			} );
			$locks = array_slice( $locks, -self::MAX_LOG, null, true );
		}
		update_option( self::OPT_LOCKS, $locks, false );
		self::notify( $ip, $count, $username, $token );
	}

	private static function notify( $ip, $count, $username, $token ) {
		if ( ! Velox_Settings::get( 'loginguard_notify', true ) ) {
			return;
		}
		$unlock = add_query_arg(
			array( 'velox_unlock' => rawurlencode( $token ) ),
			home_url( '/' )
		);
		$body = sprintf(
			/* translators: 1: site name 2: IP address 3: attempt count 4: username tried 5: unlock URL */
			__( "Sign-ins to %1\$s are now blocked from %2\$s after %3\$d failed attempts (last username tried: %4\$s).\n\nIf that was you, unlock it here:\n%5\$s\n\nThe link works once and expires in 24 hours. You can also unlock it under Velox → Utilities → Login protection.\n", 'velox' ),
			get_bloginfo( 'name' ), $ip, (int) $count, $username ? $username : '—', $unlock
		);
		wp_mail(
			get_option( 'admin_email' ),
			sprintf( __( 'Sign-in blocked from %s', 'velox' ), $ip ),
			$body
		);
	}

	/**
	 * The emailed escape hatch. Single-use, and only ever unlocks the one
	 * address that triggered it.
	 */
	public static function maybe_unlock_by_link() {
		if ( empty( $_GET['velox_unlock'] ) ) {
			return;
		}
		$token = sanitize_text_field( wp_unslash( $_GET['velox_unlock'] ) );
		$locks = self::locks();
		foreach ( $locks as $k => $lock ) {
			if ( ! empty( $lock['token'] ) && hash_equals( (string) $lock['token'], $token ) ) {
				$fresh = ( time() - (int) ( $lock['when'] ?? 0 ) ) < DAY_IN_SECONDS;
				self::unlock( $k );
				wp_safe_redirect( add_query_arg( 'velox_unlocked', $fresh ? '1' : 'expired', wp_login_url() ) );
				exit;
			}
		}
	}
}
