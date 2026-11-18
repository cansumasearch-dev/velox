<?php
/**
 * Velox Builder — element conditions.
 *
 * An element can carry rules that decide whether it is rendered at all. The
 * rules are checked on the server, so an element that does not pass is never
 * sent to the browser (unlike hiding it with CSS).
 *
 * Data on the node: `cond` = list of rule sets. The element shows when ANY set
 * passes (OR); a set passes when ALL of its rules pass (AND). An empty or
 * missing list means "always show". A rule that is incomplete (no role picked,
 * no parameter name…) is skipped, so a half-filled rule never hides content.
 *
 * Rule: { t: type, op: operator, v: value, k: key (parameter / cookie / meta name), v2: second value }.
 *
 * Conditions decide what a page shows; they are NOT access control. Content that
 * must stay private belongs behind a login or a membership plugin.
 *
 * @package Velox
 */

defined( 'ABSPATH' ) || exit;

class Velox_Builder_Conditions {

	/** Rule types whose answer can change between two visits to the same URL. */
	const VOLATILE = array( 'date', 'weekday', 'time', 'param', 'cookie', 'referrer' );
	/** Rule types that depend on who is logged in. */
	const PER_USER = array( 'user', 'role', 'usermeta' );

	/** Most sets and rules evaluated per element — the editor allows far fewer. */
	const MAX_SETS  = 10;
	const MAX_RULES = 12;

	/**
	 * Does the element pass its conditions?
	 *
	 * @param mixed $cond The node's `cond` value.
	 * @return bool True when the element should render.
	 */
	public static function passes( $cond ) {
		if ( ! is_array( $cond ) || ! $cond ) {
			return true;
		}
		$any_set = false;
		foreach ( array_slice( $cond, 0, self::MAX_SETS ) as $set ) {
			$rules = array_filter( array_slice( (array) $set, 0, self::MAX_RULES ), array( __CLASS__, 'is_complete' ) );
			if ( ! $rules ) {
				continue;
			}
			$any_set = true;
			$ok      = true;
			foreach ( $rules as $rule ) {
				if ( ! self::rule( $rule ) ) {
					$ok = false;
					break;
				}
			}
			if ( $ok ) {
				return true;
			}
		}
		// Only incomplete rules: nothing to decide on, so the element shows.
		return ! $any_set;
	}

	/** A rule with everything it needs to be checked. */
	public static function is_complete( $r ) {
		if ( ! is_array( $r ) || empty( $r['t'] ) ) {
			return false;
		}
		$t  = (string) $r['t'];
		$op = (string) ( $r['op'] ?? '' );
		$v  = trim( (string) ( $r['v'] ?? '' ) );
		$k  = trim( (string) ( $r['k'] ?? '' ) );
		switch ( $t ) {
			case 'user':
				return true;
			case 'role':
			case 'posttype':
			case 'context':
			case 'page':
			case 'date':
			case 'weekday':
			case 'referrer':
				return '' !== $v;
			case 'time':
				return '' !== $v && '' !== trim( (string) ( $r['v2'] ?? '' ) );
			case 'param':
			case 'cookie':
			case 'meta':
			case 'usermeta':
				if ( '' === $k ) {
					return false;
				}
				// Operators that compare need something to compare with.
				return in_array( $op, array( 'exists', 'missing', 'empty', 'notempty' ), true ) || '' !== $v;
		}
		return false;
	}

	/** Check one rule. */
	private static function rule( $r ) {
		$t  = (string) $r['t'];
		$op = (string) ( $r['op'] ?? '' );
		$v  = trim( (string) ( $r['v'] ?? '' ) );
		$k  = trim( (string) ( $r['k'] ?? '' ) );
		self::note_cache( $t );

		switch ( $t ) {
			case 'user':
				$in = is_user_logged_in();
				return 'out' === $op ? ! $in : $in;

			case 'role':
				$u   = wp_get_current_user();
				$has = $u && $u->ID && array_intersect( self::csv( $v ), (array) $u->roles );
				return 'not' === $op ? ! $has : (bool) $has;

			case 'posttype':
				$pt = get_post_type( get_the_ID() );
				$is = $pt && in_array( $pt, self::csv( $v ), true );
				return 'not' === $op ? ! $is : $is;

			case 'context':
				$is = false;
				foreach ( self::csv( $v ) as $c ) {
					if ( self::context_is( $c ) ) {
						$is = true;
						break;
					}
				}
				return 'not' === $op ? ! $is : $is;

			case 'page':
				$ids = array_map( 'absint', self::csv( $v ) );
				$cur = (int) ( in_the_loop() ? get_the_ID() : get_queried_object_id() );
				$is  = $cur && in_array( $cur, $ids, true );
				return 'not' === $op ? ! $is : $is;

			case 'date':
				$when = self::site_time( $v );
				if ( ! $when ) {
					return true;
				}
				$now = current_datetime();
				return 'before' === $op ? $now < $when : $now >= $when;

			case 'weekday':
				$days = array_map( 'absint', self::csv( $v ) );
				$is   = in_array( (int) current_datetime()->format( 'N' ), $days, true );
				return 'not' === $op ? ! $is : $is;

			case 'time':
				$from = self::hm( $v );
				$to   = self::hm( (string) ( $r['v2'] ?? '' ) );
				if ( null === $from || null === $to ) {
					return true;
				}
				$now = (int) current_datetime()->format( 'G' ) * 60 + (int) current_datetime()->format( 'i' );
				// 22:00–06:00 runs past midnight.
				$in = $from <= $to ? ( $now >= $from && $now < $to ) : ( $now >= $from || $now < $to );
				return 'outside' === $op ? ! $in : $in;

			case 'param':
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display rule
				$val = isset( $_GET[ $k ] ) && is_scalar( $_GET[ $k ] ) ? sanitize_text_field( wp_unslash( $_GET[ $k ] ) ) : null;
				return self::compare( $val, $op, $v );

			case 'cookie':
				$val = isset( $_COOKIE[ $k ] ) && is_scalar( $_COOKIE[ $k ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ $k ] ) ) : null;
				return self::compare( $val, $op, $v );

			case 'referrer':
				$ref = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';
				$has = '' !== $ref && false !== stripos( $ref, $v );
				return 'notcontains' === $op ? ! $has : $has;

			case 'meta':
				$pid = get_the_ID();
				$val = $pid && metadata_exists( 'post', $pid, $k ) ? get_post_meta( $pid, $k, true ) : null;
				return self::compare( is_scalar( $val ) || null === $val ? $val : '', $op, $v );

			case 'usermeta':
				$u = wp_get_current_user();
				// Passwords, sessions and keys are never readable, not even for a comparison.
				if ( ! $u || ! $u->ID || ! self::safe_user_key( $k ) ) {
					return self::compare( null, $op, $v );
				}
				$val = metadata_exists( 'user', $u->ID, $k ) ? get_user_meta( $u->ID, $k, true ) : null;
				return self::compare( is_scalar( $val ) || null === $val ? $val : '', $op, $v );
		}
		return true;
	}

	/**
	 * Compare a value. `null` means "not there at all" (no parameter, no cookie,
	 * no meta row), which is different from an empty value.
	 */
	public static function compare( $actual, $op, $v ) {
		$has = null !== $actual;
		$a   = $has ? (string) $actual : '';
		switch ( $op ) {
			case 'exists':      return $has;
			case 'missing':     return ! $has;
			case 'empty':       return '' === trim( $a );
			case 'notempty':    return '' !== trim( $a );
			case 'neq':         return ! $has || $a !== $v;
			case 'contains':    return $has && '' !== $v && false !== stripos( $a, $v );
			case 'notcontains': return ! $has || '' === $v || false === stripos( $a, $v );
			case 'gt':          return $has && is_numeric( $a ) && is_numeric( $v ) && (float) $a > (float) $v;
			case 'lt':          return $has && is_numeric( $a ) && is_numeric( $v ) && (float) $a < (float) $v;
			case 'eq':
			default:            return $has && $a === $v;
		}
	}

	/**
	 * A page whose output depends on the clock, the URL or a cookie must not be
	 * stored by a page cache — the next visitor would get the wrong version.
	 */
	private static function note_cache( $type ) {
		$volatile = in_array( $type, self::VOLATILE, true );
		// Per-user rules only matter when the cache also serves logged-in visitors.
		if ( ! $volatile && in_array( $type, self::PER_USER, true ) && class_exists( 'Velox_Settings' ) ) {
			$volatile = (bool) Velox_Settings::get( 'cache_logged_in', false );
		}
		if ( ! $volatile ) {
			return;
		}
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		if ( ! headers_sent() ) {
			nocache_headers();
		}
	}

	private static function context_is( $c ) {
		switch ( $c ) {
			case 'front':    return is_front_page();
			case 'blog':     return is_home();
			case 'single':   return is_single();
			case 'page':     return is_page();
			case 'singular': return is_singular();
			case 'archive':  return is_archive();
			case 'search':   return is_search();
			case '404':      return is_404();
		}
		return false;
	}

	/** "Y-m-d\TH:i" (or just a date) in the site's time zone. */
	private static function site_time( $v ) {
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}(T\d{2}:\d{2})?$/', $v ) ) {
			return null;
		}
		try {
			return new DateTimeImmutable( strlen( $v ) > 10 ? $v : $v . 'T00:00', wp_timezone() );
		} catch ( Exception $e ) {
			return null;
		}
	}

	/** "HH:MM" → minutes after midnight. */
	private static function hm( $v ) {
		if ( ! preg_match( '/^(\d{1,2}):(\d{2})$/', trim( $v ), $m ) || (int) $m[1] > 24 || (int) $m[2] > 59 ) {
			return null;
		}
		return min( 1440, (int) $m[1] * 60 + (int) $m[2] );
	}

	private static function csv( $v ) {
		return array_values( array_filter( array_map( 'trim', explode( ',', (string) $v ) ), 'strlen' ) );
	}

	/** Same rule the dynamic data uses: never passwords, sessions or keys. */
	private static function safe_user_key( $key ) {
		$key = strtolower( (string) $key );
		if ( in_array( $key, array( 'user_pass', 'user_activation_key', 'session_tokens', 'user_email', 'user_login' ), true ) ) {
			return false;
		}
		return ! preg_match( '/(pass|token|secret|session|capabilit|_key$|api)/', $key );
	}

	/** What the editor needs to offer the choices: roles, post types, the site clock. */
	public static function editor_data() {
		$roles = array();
		if ( function_exists( 'wp_roles' ) ) {
			foreach ( wp_roles()->get_names() as $slug => $name ) {
				$roles[] = array( 'v' => $slug, 'l' => translate_user_role( $name ) );
			}
		}
		$types = array();
		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $pt ) {
			if ( 'attachment' === $pt->name ) {
				continue;
			}
			$types[] = array( 'v' => $pt->name, 'l' => $pt->labels->singular_name );
		}
		return array(
			'roles'     => $roles,
			'postTypes' => $types,
			'tz'        => wp_timezone_string(),
			'now'       => current_datetime()->format( 'Y-m-d\TH:i' ),
		);
	}
}
