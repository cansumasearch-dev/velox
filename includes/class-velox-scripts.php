<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Script Manager — disable specific CSS/JS per page.
 *
 * Two halves:
 *   1. Discovery: as the front end is visited, it records which handles actually
 *      load, building a list the admin can manage (no guessing handle names).
 *   2. Enforcement: late on wp_enqueue_scripts it dequeues handles whose rule
 *      matches the current page (everywhere / everywhere-except / only-on).
 *
 * Pages are matched by ID, slug, or the token "front" for the homepage, so a
 * rule like "Contact Form 7 everywhere except: kontakt" just works.
 */
class Velox_Scripts {

	const SEEN_OPTION  = 'velox_assets_seen';
	const RULES_OPTION = 'velox_script_rules';
	const MAX_HANDLES  = 250;

	public static function init() {
		if ( ! Velox_Settings::get( 'util_scripts', false ) || is_admin() ) {
			return;
		}
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enforce' ), 9999 );
		add_action( 'wp_footer', array( __CLASS__, 'collect' ), 99999 );
	}

	/* ------------------------------------------------------------ discovery */

	public static function collect() {
		global $wp_scripts, $wp_styles;
		$seen    = self::seen();
		$changed = false;

		if ( $wp_scripts && ! empty( $wp_scripts->done ) ) {
			foreach ( $wp_scripts->done as $h ) {
				if ( ! isset( $seen['scripts'][ $h ] ) && count( $seen['scripts'] ) < self::MAX_HANDLES ) {
					$seen['scripts'][ $h ] = self::src_of( $wp_scripts, $h );
					$changed = true;
				}
			}
		}
		if ( $wp_styles && ! empty( $wp_styles->done ) ) {
			foreach ( $wp_styles->done as $h ) {
				if ( ! isset( $seen['styles'][ $h ] ) && count( $seen['styles'] ) < self::MAX_HANDLES ) {
					$seen['styles'][ $h ] = self::src_of( $wp_styles, $h );
					$changed = true;
				}
			}
		}
		if ( $changed ) {
			ksort( $seen['scripts'] );
			ksort( $seen['styles'] );
			update_option( self::SEEN_OPTION, $seen, false );
		}
	}

	private static function src_of( $deps, $handle ) {
		if ( isset( $deps->registered[ $handle ] ) && ! empty( $deps->registered[ $handle ]->src ) ) {
			$src = $deps->registered[ $handle ]->src;
			// Trim to the path so the UI stays readable.
			$path = wp_parse_url( $src, PHP_URL_PATH );
			return $path ? $path : $src;
		}
		return '';
	}

	public static function seen() {
		$s = get_option( self::SEEN_OPTION, array() );
		if ( ! is_array( $s ) ) {
			$s = array();
		}
		$s += array( 'scripts' => array(), 'styles' => array() );
		return $s;
	}

	public static function clear_seen() {
		update_option( self::SEEN_OPTION, array( 'scripts' => array(), 'styles' => array() ), false );
		return array( 'ok' => true );
	}

	/** Best-effort loopback request so the list fills in without browsing manually. */
	public static function scan() {
		$res = wp_remote_get( home_url( '/' ), array( 'timeout' => 12, 'sslverify' => false ) );
		if ( is_wp_error( $res ) ) {
			return array( 'ok' => false, 'message' => 'Could not reach the front page: ' . $res->get_error_message() );
		}
		$seen = self::seen();
		return array( 'ok' => true, 'scripts' => count( $seen['scripts'] ), 'styles' => count( $seen['styles'] ) );
	}

	/* ------------------------------------------------------------- rules */

	public static function rules() {
		$r = get_option( self::RULES_OPTION, array() );
		return is_array( $r ) ? $r : array();
	}

	public static function save_rules( $rules ) {
		$clean = array();
		foreach ( (array) $rules as $key => $rule ) {
			$type = ( isset( $rule['type'] ) && 'style' === $rule['type'] ) ? 'style' : 'script';
			$mode = isset( $rule['mode'] ) ? $rule['mode'] : 'off';
			if ( ! in_array( $mode, array( 'off', 'everywhere', 'except', 'only' ), true ) ) {
				$mode = 'off';
			}
			if ( 'off' === $mode ) {
				continue; // don't store no-op rules
			}
			$handle = isset( $rule['handle'] ) ? sanitize_text_field( $rule['handle'] ) : '';
			if ( '' === $handle ) {
				continue;
			}
			$ids = array();
			if ( ! empty( $rule['ids'] ) ) {
				foreach ( preg_split( '/[\s,]+/', (string) $rule['ids'] ) as $tok ) {
					$tok = sanitize_title( $tok );
					if ( '' !== $tok ) {
						$ids[] = $tok;
					}
				}
			}
			// "except" / "only" without targets would be meaningless — keep as everywhere/off.
			if ( ( 'except' === $mode || 'only' === $mode ) && empty( $ids ) ) {
				$mode = ( 'except' === $mode ) ? 'everywhere' : 'off';
				if ( 'off' === $mode ) {
					continue;
				}
			}
			$clean[ $type . ':' . $handle ] = array(
				'handle' => $handle,
				'type'   => $type,
				'mode'   => $mode,
				'ids'    => array_values( array_unique( $ids ) ),
			);
		}
		update_option( self::RULES_OPTION, $clean, false );
		return array( 'ok' => true, 'count' => count( $clean ) );
	}

	/* --------------------------------------------------------- enforcement */

	public static function enforce() {
		$rules = self::rules();
		if ( empty( $rules ) ) {
			return;
		}
		foreach ( $rules as $rule ) {
			if ( empty( $rule['mode'] ) || 'off' === $rule['mode'] ) {
				continue;
			}
			if ( ! self::should_disable( $rule ) ) {
				continue;
			}
			if ( 'style' === $rule['type'] ) {
				wp_dequeue_style( $rule['handle'] );
				wp_deregister_style( $rule['handle'] );
			} else {
				wp_dequeue_script( $rule['handle'] );
				wp_deregister_script( $rule['handle'] );
			}
		}
	}

	private static function current_tokens() {
		$t = array();
		if ( is_front_page() ) {
			$t[] = 'front';
		}
		$id = get_queried_object_id();
		if ( $id ) {
			$t[] = (string) $id;
			$obj = get_post( $id );
			if ( $obj && ! empty( $obj->post_name ) ) {
				$t[] = $obj->post_name;
			}
		}
		return $t;
	}

	private static function should_disable( $rule ) {
		$mode = $rule['mode'];
		if ( 'everywhere' === $mode ) {
			return true;
		}
		$match = (bool) array_intersect( self::current_tokens(), (array) $rule['ids'] );
		if ( 'only' === $mode ) {
			return $match;        // disable only on the listed pages
		}
		if ( 'except' === $mode ) {
			return ! $match;      // disable everywhere except the listed pages
		}
		return false;
	}
}
