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

	/**
	 * The Lighthouse categories PSI can return, in display order.
	 * (Google does NOT expose an "agentic browsing" category — these four are
	 * everything runPagespeed reports.)
	 */
	const CATEGORIES = array(
		'performance'    => 'Performance',
		'accessibility'  => 'Accessibility',
		'best-practices' => 'Best Practices',
		'seo'            => 'SEO',
	);

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

		// Base params (single-value) via add_query_arg…
		$args = array(
			'url'      => self::target_url(),
			'strategy' => $strategy,
			'locale'   => 'en', // force English audit titles/descriptions
		);
		if ( '' !== $key ) {
			$args['key'] = $key;
		}
		$endpoint = add_query_arg( array_map( 'rawurlencode', $args ), self::ENDPOINT );

		// …then append one category param per category (PSI takes it repeated).
		foreach ( array_keys( self::CATEGORIES ) as $cat ) {
			$endpoint .= '&category=' . rawurlencode( $cat );
		}

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
	 * Turn a PSI v5 response into our cached payload: category scores, the full
	 * per-category audit list (for the report accordions), the Core Web Vitals
	 * chips, plus the top issues / passing checks for the dashboard widget.
	 *
	 * @return array{ok:bool,score:int,strategy:string,url:string,fetched:int,metrics:array,categories:array,audits:array,issues:array,passed:array}
	 */
	public static function parse( array $body, $strategy = null ) {
		$strategy = in_array( $strategy, array( 'mobile', 'desktop' ), true ) ? $strategy : self::strategy();
		$lh       = isset( $body['lighthouseResult'] ) && is_array( $body['lighthouseResult'] ) ? $body['lighthouseResult'] : array();
		$cats     = isset( $lh['categories'] ) && is_array( $lh['categories'] ) ? $lh['categories'] : array();
		if ( empty( $cats['performance'] ) || ! array_key_exists( 'score', $cats['performance'] ) ) {
			return array( 'ok' => false );
		}
		$audits = isset( $lh['audits'] ) && is_array( $lh['audits'] ) ? $lh['audits'] : array();
		$score  = null !== $cats['performance']['score'] ? (int) round( 100 * (float) $cats['performance']['score'] ) : 0;

		// Core Web Vitals / lab metrics (chips on the dashboard + report).
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
				$a         = $audits[ $id ];
				$metrics[] = array(
					'key'   => $label,
					'value' => isset( $a['displayValue'] ) ? (string) $a['displayValue'] : '—',
					'score' => isset( $a['score'] ) ? (float) $a['score'] : null,
				);
			}
		}

		// Build the per-category audit lists (what powers the report accordions).
		$categories  = array();
		$audits_by   = array();
		foreach ( self::CATEGORIES as $cat_id => $cat_label ) {
			if ( empty( $cats[ $cat_id ] ) || ! is_array( $cats[ $cat_id ] ) ) {
				continue;
			}
			$c        = $cats[ $cat_id ];
			$c_score  = array_key_exists( 'score', $c ) && null !== $c['score'] ? (int) round( 100 * (float) $c['score'] ) : null;
			$refs     = isset( $c['auditRefs'] ) && is_array( $c['auditRefs'] ) ? $c['auditRefs'] : array();
			$list     = array();
			foreach ( $refs as $ref ) {
				$aid   = isset( $ref['id'] ) ? $ref['id'] : '';
				$group = isset( $ref['group'] ) ? (string) $ref['group'] : '';
				if ( '' === $aid || ! isset( $audits[ $aid ] ) || 'metrics' === $group || 'hidden' === $group ) {
					continue; // metric tiles are shown as chips, not accordion rows
				}
				$a     = $audits[ $aid ];
				$sc    = array_key_exists( 'score', $a ) ? $a['score'] : null;
				$mode  = isset( $a['scoreDisplayMode'] ) ? (string) $a['scoreDisplayMode'] : '';
				$title = isset( $a['title'] ) ? (string) $a['title'] : '';
				if ( '' === $title || in_array( $mode, array( 'notApplicable', 'manual' ), true ) ) {
					continue;
				}
				$display = isset( $a['displayValue'] ) ? (string) $a['displayValue'] : '';
				// Keep scored checks, plus informational rows that actually say something.
				$scored = in_array( $mode, array( 'binary', 'numeric', 'metricSavings' ), true ) && null !== $sc;
				if ( ! $scored && ! ( 'informative' === $mode && '' !== $display ) ) {
					continue;
				}
				$fail    = $scored && (float) $sc < 0.9;
				// PSI shape-coded severity: poor (red ▲) / avg (orange ◼) / pass (green ●) / na (○).
				if ( ! $scored ) {
					$sev = 'na';
				} elseif ( (float) $sc < 0.5 ) {
					$sev = 'poor';
				} elseif ( (float) $sc < 0.9 ) {
					$sev = 'avg';
				} else {
					$sev = 'pass';
				}
				$savings = self::audit_savings( $a, $mode );
				$list[]  = array(
					'id'      => (string) $aid,
					'title'   => $title,
					'desc'    => self::clean_desc( isset( $a['description'] ) ? (string) $a['description'] : '' ),
					'display' => $display,
					'state'   => $scored ? ( $fail ? 'fail' : 'pass' ) : 'info',
					'sev'     => $sev,
					'weight'  => isset( $ref['weight'] ) ? (float) $ref['weight'] : 0,
					'savings' => $savings,
				);
			}
			// Order: failures first (biggest savings / weight), then passes, then info.
			$rank = array( 'fail' => 0, 'pass' => 1, 'info' => 2 );
			usort(
				$list,
				function ( $x, $y ) use ( $rank ) {
					if ( $rank[ $x['state'] ] !== $rank[ $y['state'] ] ) {
						return $rank[ $x['state'] ] <=> $rank[ $y['state'] ];
					}
					if ( $x['savings'] !== $y['savings'] ) {
						return $y['savings'] <=> $x['savings'];
					}
					return $y['weight'] <=> $x['weight'];
				}
			);
			$fail_n = 0;
			foreach ( $list as $it ) {
				if ( 'fail' === $it['state'] ) {
					++$fail_n;
				}
			}
			$categories[]         = array( 'id' => $cat_id, 'label' => $cat_label, 'score' => $c_score, 'fails' => $fail_n, 'total' => count( $list ) );
			$audits_by[ $cat_id ] = $list;
		}

		// Dashboard widget: top issues + passing checks come from Performance.
		$perf   = isset( $audits_by['performance'] ) ? $audits_by['performance'] : array();
		$issues = array();
		$passed = array();
		foreach ( $perf as $it ) {
			if ( 'fail' === $it['state'] && count( $issues ) < 5 ) {
				$issues[] = array( 'title' => $it['title'], 'value' => $it['display'], 'savings' => $it['savings'] );
			} elseif ( 'pass' === $it['state'] && count( $passed ) < 8 ) {
				$passed[] = array( 'title' => $it['title'] );
			}
		}

		return array(
			'ok'         => true,
			'score'      => $score,
			'strategy'   => $strategy,
			'url'        => self::target_url(),
			'fetched'    => time(),
			'metrics'    => $metrics,
			'categories' => $categories,
			'audits'     => $audits_by,
			'issues'     => $issues,
			'passed'     => $passed,
		);
	}

	/** Numeric "how much would this save" used to rank failing audits. */
	private static function audit_savings( $a, $mode ) {
		$s = 0.0;
		if ( isset( $a['details']['overallSavingsMs'] ) ) {
			$s = (float) $a['details']['overallSavingsMs'];
		}
		if ( isset( $a['metricSavings'] ) && is_array( $a['metricSavings'] ) ) {
			$sum = 0.0;
			foreach ( $a['metricSavings'] as $v ) {
				$sum += (float) $v;
			}
			$s = max( $s, $sum );
		}
		if ( 0.0 === $s && 'metricSavings' === $mode && isset( $a['numericValue'] ) ) {
			$s = (float) $a['numericValue'];
		}
		return $s;
	}

	/** Strip Lighthouse's markdown links / backticks and trim to a readable length. */
	private static function clean_desc( $s ) {
		$s = preg_replace( '/\[([^\]]+)\]\([^)]+\)/', '$1', $s ); // [label](url) → label
		$s = str_replace( '`', '', $s );
		$s = trim( preg_replace( '/\s+/', ' ', $s ) );
		if ( function_exists( 'mb_strlen' ) && mb_strlen( $s ) > 320 ) {
			$s = rtrim( mb_substr( $s, 0, 317 ) ) . '…';
		} elseif ( strlen( $s ) > 320 ) {
			$s = rtrim( substr( $s, 0, 317 ) ) . '…';
		}
		return $s;
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
			return array( 'ok' => false, 'score' => 0, 'strategy' => $strategy, 'metrics' => array(), 'categories' => array(), 'audits' => array(), 'issues' => array(), 'passed' => array() );
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
