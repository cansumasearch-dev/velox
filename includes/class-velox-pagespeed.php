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

	/** Hit PSI, parse, and cache. Returns the stored result array. */
	public static function fetch_and_store() {
		$key  = trim( (string) Velox_Settings::get( 'ps_api_key', '' ) );
		$args = array(
			'url'      => self::target_url(),
			'strategy' => self::strategy(),
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
			return self::store_error( $resp->get_error_message() );
		}
		$code = (int) wp_remote_retrieve_response_code( $resp );
		$body = json_decode( wp_remote_retrieve_body( $resp ), true );

		if ( 200 !== $code || ! is_array( $body ) ) {
			$msg = 'PageSpeed API returned an error.';
			if ( is_array( $body ) && isset( $body['error']['message'] ) ) {
				$msg = (string) $body['error']['message'];
			}
			return self::store_error( $msg );
		}

		$parsed = self::parse( $body );
		if ( empty( $parsed['ok'] ) ) {
			return self::store_error( 'Could not read the PageSpeed response.' );
		}
		update_option( self::RESULT_OPT, $parsed, false );
		return $parsed;
	}

	private static function store_error( $message ) {
		$prev = get_option( self::RESULT_OPT, array() );
		$out  = is_array( $prev ) ? $prev : array();
		$out['ok']         = ! empty( $prev['score'] ); // keep last good score if we had one
		$out['error']      = (string) $message;
		$out['fetched']    = time();
		update_option( self::RESULT_OPT, $out, false );
		return $out;
	}

	/**
	 * Turn a PSI v5 response into a compact dashboard payload.
	 *
	 * @return array{ok:bool,score:int,strategy:string,url:string,fetched:int,metrics:array,issues:array}
	 */
	public static function parse( array $body ) {
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

		return array(
			'ok'       => true,
			'score'    => $score,
			'strategy' => self::strategy(),
			'url'      => self::target_url(),
			'fetched'  => time(),
			'metrics'  => $metrics,
			'issues'   => $issues,
		);
	}

	/** The cached result (or a shape with ok=false when nothing is stored yet). */
	public static function result() {
		$r = get_option( self::RESULT_OPT, array() );
		if ( ! is_array( $r ) || ! isset( $r['score'] ) ) {
			return array( 'ok' => false, 'score' => 0, 'metrics' => array(), 'issues' => array() );
		}
		return $r;
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
