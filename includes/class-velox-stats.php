<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lightweight, privacy-conscious dashboard stats.
 *
 * - Form submissions: a per-day counter bumped from Velox_Forms on a real submit.
 * - Traffic: a first-party beacon. A tiny front-end script pings a REST endpoint
 *   on each page view (so it still counts when the HTML is cache-served). We store
 *   ONLY daily aggregates — never per-visitor rows, never a raw IP. "Unique"
 *   visitors are de-duped with a salted hash of IP+UA whose salt rotates every day,
 *   so a hash can't be linked across days. Bots and logged-in admins are skipped.
 *
 * Everything lives in two autoload-off options; no custom tables.
 */
final class Velox_Stats {

	const FORM_OPT    = 'velox_form_log';   // array( 'Y-m-d' => int )
	const TRAFFIC_OPT = 'velox_traffic';    // array( 'Y-m-d' => array( 'v' => int, 'u' => int ) )

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest' ) );
		if ( ! is_admin() && Velox_Settings::get( 'traffic_tracking', true ) ) {
			add_action( 'wp_footer', array( __CLASS__, 'beacon' ), 99 );
		}
	}

	/* ----------------------------------------------------------------- *
	 * Form submissions
	 * ----------------------------------------------------------------- */

	/** Call once per genuine form submission. */
	public static function bump_form() {
		$log = get_option( self::FORM_OPT, array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}
		$day         = current_time( 'Y-m-d' );
		$log[ $day ] = isset( $log[ $day ] ) ? (int) $log[ $day ] + 1 : 1;
		update_option( self::FORM_OPT, self::prune( $log, 60 ), false );
	}

	/** Total submissions over the last $days days. */
	public static function form_total( $days = 30 ) {
		$log = get_option( self::FORM_OPT, array() );
		if ( ! is_array( $log ) ) {
			return 0;
		}
		$sum = 0;
		foreach ( self::recent_days( $days ) as $d ) {
			$sum += isset( $log[ $d ] ) ? (int) $log[ $d ] : 0;
		}
		return $sum;
	}

	/* ----------------------------------------------------------------- *
	 * Traffic
	 * ----------------------------------------------------------------- */

	public static function register_rest() {
		register_rest_route(
			'velox/v1',
			'/hit',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'record_hit' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public static function record_hit( $request ) {
		$skip = array( 'ok' => false );

		if ( ! Velox_Settings::get( 'traffic_tracking', true ) ) {
			return new WP_REST_Response( $skip, 200 );
		}
		// Don't count the people who run the site.
		if ( is_user_logged_in() && current_user_can( 'edit_posts' ) ) {
			return new WP_REST_Response( $skip, 200 );
		}
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';
		if ( '' === $ua || preg_match( '/bot|crawl|spider|slurp|bing|baidu|yandex|preview|monitor|curl|wget|headless|lighthouse|pingdom|gtmetrix|uptime/i', $ua ) ) {
			return new WP_REST_Response( $skip, 200 );
		}

		$ip    = self::client_ip();
		$daykey = gmdate( 'Ymd' );
		// Daily-rotating salt: hashes can't be correlated across days.
		$salt  = wp_salt( 'nonce' ) . $daykey;
		$hash  = substr( hash( 'sha256', $salt . '|' . $ip . '|' . $ua ), 0, 24 );

		$seen_key = 'velox_tr_seen_' . $daykey;
		$seen     = get_transient( $seen_key );
		if ( ! is_array( $seen ) ) {
			$seen = array();
		}
		$unique = ! isset( $seen[ $hash ] );
		if ( $unique ) {
			$seen[ $hash ] = 1;
			set_transient( $seen_key, $seen, DAY_IN_SECONDS );
		}

		$day = current_time( 'Y-m-d' );
		$t   = get_option( self::TRAFFIC_OPT, array() );
		if ( ! is_array( $t ) ) {
			$t = array();
		}
		if ( ! isset( $t[ $day ] ) || ! is_array( $t[ $day ] ) ) {
			$t[ $day ] = array( 'v' => 0, 'u' => 0 );
		}
		$t[ $day ]['v']++;
		if ( $unique ) {
			$t[ $day ]['u']++;
		}
		update_option( self::TRAFFIC_OPT, self::prune( $t, 90 ), false );

		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	/** Print the front-end beacon. Fires once per page view; fire-and-forget. */
	public static function beacon() {
		$url = wp_json_encode( esc_url_raw( rest_url( 'velox/v1/hit' ) ) );
		echo '<script>(function(){try{var u=' . $url . ';if(navigator.sendBeacon){navigator.sendBeacon(u);}else{fetch(u,{method:"POST",keepalive:true,credentials:"same-origin"});}}catch(e){}})();</script>' . "\n";
	}

	/**
	 * Visitors/views over the last $days days.
	 *
	 * @return array{visitors:int,views:int,series:array<int,array{d:string,u:int,v:int}>}
	 */
	public static function traffic_summary( $days = 7 ) {
		$t = get_option( self::TRAFFIC_OPT, array() );
		if ( ! is_array( $t ) ) {
			$t = array();
		}
		$series   = array();
		$visitors = 0;
		$views    = 0;
		foreach ( self::recent_days( $days, true ) as $d ) {
			$u = isset( $t[ $d ]['u'] ) ? (int) $t[ $d ]['u'] : 0;
			$v = isset( $t[ $d ]['v'] ) ? (int) $t[ $d ]['v'] : 0;
			$series[]  = array( 'd' => $d, 'u' => $u, 'v' => $v );
			$visitors += $u;
			$views    += $v;
		}
		return array(
			'visitors' => $visitors,
			'views'    => $views,
			'series'   => $series,
		);
	}

	/* ----------------------------------------------------------------- *
	 * Helpers
	 * ----------------------------------------------------------------- */

	private static function client_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '';
		return preg_replace( '/[^0-9a-f:.]/i', '', $ip );
	}

	/** Array of 'Y-m-d' strings for the last $days days. Oldest-first if $asc. */
	private static function recent_days( $days, $asc = false ) {
		$days = max( 1, (int) $days );
		$out  = array();
		$base = (int) current_time( 'timestamp' );
		for ( $i = 0; $i < $days; $i++ ) {
			$out[] = gmdate( 'Y-m-d', $base - $i * DAY_IN_SECONDS );
		}
		if ( $asc ) {
			$out = array_reverse( $out );
		}
		return $out;
	}

	/** Keep only the $keep most-recent day keys. */
	private static function prune( $log, $keep ) {
		if ( count( $log ) <= $keep ) {
			return $log;
		}
		krsort( $log );
		return array_slice( $log, 0, $keep, true );
	}
}
