<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Velox — live PageSpeed status.
 *
 * Fetches a real Lighthouse score from Google's PageSpeed Insights API on a
 * schedule (WP-Cron), caches the parsed result, and hands the dashboard a small
 * structured payload to render. Nothing runs on a normal front-end request — the
 * fetch is slow, so it only happens on the cron tick or a manual "refresh now".
 */
final class Velox_Pagespeed {

	const RESULT_OPT = 'velox_pagespeed';        // cached parsed result
	const HOOK       = 'velox_pagespeed_refresh'; // cron hook
	const ENDPOINT   = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed';

	public static function init() {
		add_action( self::HOOK, array( __CLASS__, 'refresh' ) );
		add_action( 'admin_init', array( __CLASS__, 'sync_schedule' ) );
	}

	/* ----------------------------------------------------------------- *
	 * Settings helpers
	 * ----------------------------------------------------------------- */

	public static function enabled() {
		return (bool) Velox_Settings::get( 'ps_enable', false );
	}

	/** The URL we test — falls back to the site home. */
	public static function target_url() {
		$u = trim( (string) Velox_Settings::get( 'ps_url', '' ) );
		if ( '' === $u ) {
			$u = home_url( '/' );
		}
		return $u;
	}

	public static function strategy() {
		$s = Velox_Settings::get( 'ps_strategy', 'mobile' );
		return in_array( $s, array( 'mobile', 'desktop' ), true ) ? $s : 'mobile';
	}

	public static function interval() {
		$i = Velox_Settings::get( 'ps_interval', 'daily' );
		return in_array( $i, array( 'hourly', 'twicedaily', 'daily' ), true ) ? $i : 'daily';
	}

	/* ----------------------------------------------------------------- *
	 * Scheduling — keep the cron event in step with the settings.
	 * ----------------------------------------------------------------- */

	public static function sync_schedule() {
		$next = wp_next_scheduled( self::HOOK );
		if ( ! self::enabled() ) {
			if ( $next ) {
				wp_unschedule_event( $next, self::HOOK );
			}
			return;
		}
		$want = self::interval();
		if ( $next ) {
			$sched = wp_get_schedule( self::HOOK );
			if ( $sched === $want ) {
				return; // already on the right cadence
			}
			wp_unschedule_event( $next, self::HOOK );
		}
		wp_schedule_event( time() + MINUTE_IN_SECONDS, $want, self::HOOK );
	}

	public static function refresh() {
		self::fetch_and_store();
	}

	/* ----------------------------------------------------------------- *
	 * Fetch + parse
	 * ----------------------------------------------------------------- */

	/**
	 * Fetch BOTH mobile + desktop, parse, and cache them side by side so the
	 * dashboard can flip between devices without re-hitting the API each time.
	 *
	 * @return array{ok:bool,mobile:array,desktop:array}
	 */
	public static function fetch_and_store() {
		$store = self::normalize_store( get_option( self::RESULT_OPT, array() ) );
		$ok_any = false;

		foreach ( array( 'mobile', 'desktop' ) as $strat ) {
			$res = self::fetch_one( $strat );
			if ( ! empty( $res['ok'] ) ) {
				$store[ $strat ] = $res;
				$ok_any = true;
			} else {
				// Keep the last good score for this device, just flag the error.
				$prev              = isset( $store[ $strat ] ) && is_array( $store[ $strat ] ) ? $store[ $strat ] : array();
				$prev['ok']        = ! empty( $prev['score'] );
				$prev['strategy']  = $strat;
				$prev['error']     = ! empty( $res['error'] ) ? $res['error'] : 'PageSpeed check failed.';
				$prev['fetched']   = time();
				$store[ $strat ]   = $prev;
			}
		}

		update_option( self::RESULT_OPT, $store, false );
		return array(
			'ok'      => $ok_any,
			'mobile'  => isset( $store['mobile'] ) ? $store['mobile'] : array( 'ok' => false ),
			'desktop' => isset( $store['desktop'] ) ? $store['desktop'] : array( 'ok' => false ),
		);
	}

	/** Hit PSI once for a single device and return the parsed payload (or an error shape). */
	private static function fetch_one( $strategy ) {
		$strategy = in_array( $strategy, array( 'mobile', 'desktop' ), true ) ? $strategy : 'mobile';
		$key      = trim( (string) Velox_Settings::get( 'ps_api_key', '' ) );
		$args     = array(
			'url'      => self::target_url(),
			'strategy' => $strategy,
			'category' => 'performance',
		);
		if ( '' !== $key ) {
			$args['key'] = $key;
		}
		$endpoint = add_query_arg( array_map( 'rawurlencode', $args ), self::ENDPOINT );

		$resp = wp_remote_get(
			$endpoint,
			array(
				'timeout'   => 60,
				'sslverify' => true,
			)
		);

		if ( is_wp_error( $resp ) ) {
			return array( 'ok' => false, 'error' => $resp->get_error_message() );
		}
		$code = (int) wp_remote_retrieve_response_code( $resp );
		$body = json_decode( wp_remote_retrieve_body( $resp ), true );

		if ( 200 !== $code || ! is_array( $body ) ) {
			$msg = 'PageSpeed API returned an error.';
			if ( is_array( $body ) && isset( $body['error']['message'] ) ) {
				$msg = (string) $body['error']['message'];
			}
			return array( 'ok' => false, 'error' => $msg );
		}

		$parsed = self::parse( $body, $strategy );
		if ( empty( $parsed['ok'] ) ) {
			return array( 'ok' => false, 'error' => 'Could not read the PageSpeed response.' );
		}
		return $parsed;
	}

	/** Migrate the old single-strategy flat cache into the per-device shape. */
	private static function normalize_store( $store ) {
		if ( ! is_array( $store ) ) {
			return array();
		}
		if ( isset( $store['score'] ) ) { // legacy flat result → key it under its device
			$strat = isset( $store['strategy'] ) && in_array( $store['strategy'], array( 'mobile', 'desktop' ), true ) ? $store['strategy'] : 'mobile';
			return array( $strat => $store );
		}
		return $store;
	}

	/**
	 * Turn a PSI v5 response into a compact dashboard payload.
	 *
	 * @return array{ok:bool,score:int,strategy:string,url:string,fetched:int,metrics:array,issues:array,passed:array}
	 */
	public static function parse( array $body, $strategy = null ) {
		$strategy = in_array( $strategy, array( 'mobile', 'desktop' ), true ) ? $strategy : self::strategy();
		$lh = isset( $body['lighthouseResult'] ) && is_array( $body['lighthouseResult'] ) ? $body['lighthouseResult'] : array();
		if ( empty( $lh['categories']['performance'] ) || ! isset( $lh['categories']['performance']['score'] ) ) {
			return array( 'ok' => false );
		}
		$score = (int) round( 100 * (float) $lh['categories']['performance']['score'] );
		$audits = isset( $lh['audits'] ) && is_array( $lh['audits'] ) ? $lh['audits'] : array();

		// Core Web Vitals / lab metrics.
		$metric_map = array(
			'largest-contentful-paint' => 'LCP',
			'cumulative-layout-shift'  => 'CLS',
			'total-blocking-time'      => 'TBT',
			'first-contentful-paint'   => 'FCP',
			'speed-index'              => 'Speed Index',
		);
		$metrics = array();
		foreach ( $metric_map as $id => $label ) {
			if ( isset( $audits[ $id ] ) ) {
				$a = $audits[ $id ];
				$metrics[] = array(
					'key'   => $label,
					'value' => isset( $a['displayValue'] ) ? (string) $a['displayValue'] : '—',
					'score' => isset( $a['score'] ) ? (float) $a['score'] : null,
				);
			}
		}

		// Top opportunities (things to fix), biggest time-savings first.
		$issues = array();
		foreach ( $audits as $a ) {
			if ( ! is_array( $a ) ) {
				continue;
			}
			$is_opp   = isset( $a['details']['type'] ) && 'opportunity' === $a['details']['type'];
			$savings  = isset( $a['details']['overallSavingsMs'] ) ? (float) $a['details']['overallSavingsMs'] : 0;
			$sc       = isset( $a['score'] ) ? $a['score'] : null;
			if ( $is_opp && null !== $sc && $sc < 0.9 && ! empty( $a['title'] ) ) {
				$issues[] = array(
					'title'   => (string) $a['title'],
					'value'   => isset( $a['displayValue'] ) ? (string) $a['displayValue'] : '',
					'savings' => $savings,
				);
			}
		}
		usort(
			$issues,
			function ( $x, $y ) {
				return $y['savings'] <=> $x['savings'];
			}
		);
		$issues = array_slice( $issues, 0, 5 );

		// What's right — pass/fail checks that Lighthouse marked green.
		$passed = array();
		foreach ( $audits as $a ) {
			if ( ! is_array( $a ) || empty( $a['title'] ) ) {
				continue;
			}
			$sc   = isset( $a['score'] ) ? $a['score'] : null;
			$mode = isset( $a['scoreDisplayMode'] ) ? (string) $a['scoreDisplayMode'] : '';
			$is_opp = isset( $a['details']['type'] ) && 'opportunity' === $a['details']['type'];
			// Only clean pass/fail-style checks, and only the ones that passed.
			if ( null !== $sc && $sc >= 0.9 && ( 'binary' === $mode || $is_opp ) ) {
				$passed[] = array( 'title' => (string) $a['title'] );
			}
		}
		$passed = array_slice( $passed, 0, 8 );

		return array(
			'ok'       => true,
			'score'    => $score,
			'strategy' => $strategy,
			'url'      => self::target_url(),
			'fetched'  => time(),
			'metrics'  => $metrics,
			'issues'   => $issues,
			'passed'   => $passed,
		);
	}

	/**
	 * The cached result for one device (defaults to the configured strategy).
	 * Returns a shape with ok=false when nothing is stored yet.
	 */
	public static function result( $strategy = null ) {
		$strategy = in_array( $strategy, array( 'mobile', 'desktop' ), true ) ? $strategy : self::strategy();
		$store    = self::normalize_store( get_option( self::RESULT_OPT, array() ) );
		$r        = isset( $store[ $strategy ] ) && is_array( $store[ $strategy ] ) ? $store[ $strategy ] : array();
		if ( ! isset( $r['score'] ) ) {
			return array( 'ok' => false, 'score' => 0, 'strategy' => $strategy, 'metrics' => array(), 'issues' => array(), 'passed' => array() );
		}
		return $r;
	}

	/** True if either device has a stored score — used for the dashboard empty state. */
	public static function has_any() {
		$store = self::normalize_store( get_option( self::RESULT_OPT, array() ) );
		foreach ( array( 'mobile', 'desktop' ) as $s ) {
			if ( isset( $store[ $s ]['score'] ) ) {
				return true;
			}
		}
		return false;
	}

	/** Grade band for a 0-100 score → [label, css-suffix]. */
	public static function grade( $score ) {
		$score = (int) $score;
		if ( $score >= 90 ) {
			return array( 'Good', 'ok' );
		}
		if ( $score >= 50 ) {
			return array( 'Needs work', 'warn' );
		}
		return array( 'Poor', 'bad' );
	}
}
