<?php
/**
 * Velox — Google Reviews · Oxygen builder integration.
 *
 * Registers a "Velox Reviews" element in Oxygen's Add (+) panel. The element is
 * intentionally thin: it exposes two dropdowns — Connection and Preset — and
 * renders the existing [velox_reviews] shortcode. All visual styling lives in the
 * preset (edited on the utility page), so there is a single source of truth and
 * we don't duplicate the whole preset-builder inside Oxygen's control API.
 *
 * This file only loads inside the Oxygen builder/runtime, guarded by CT_VERSION.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Velox_Reviews_Oxygen {

	public static function init() {
		if ( ! Velox_Settings::get( 'util_reviews', false ) ) {
			return;
		}
		// Only meaningful when Oxygen is present.
		add_action( 'plugins_loaded', array( __CLASS__, 'maybe_register' ), 20 );
	}

	public static function maybe_register() {
		if ( ! defined( 'CT_VERSION' ) && ! defined( 'OXYGEN_VERSION' ) ) {
			return;
		}
		add_action( 'wp_ajax_velox_reviews_oxy_render', array( __CLASS__, 'ajax_render' ) );

		// Register the element class only if Oxygen's base class is available AND
		// has the methods we rely on. Oxygen's element API has shifted across
		// versions, so we guard defensively: a mismatch must never break the
		// builder — the shortcode always remains as a fallback.
		if ( class_exists( 'OxyEl' ) ) {
			try {
				require_once __DIR__ . '/oxygen/class-velox-reviews-oxyel.php';
			} catch ( \Throwable $e ) {
				// Swallow — the shortcode path still works; just no dedicated element.
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'Velox Reviews Oxygen element could not register: ' . $e->getMessage() );
				}
			}
		}
	}

	/** Add a "Velox" section to the Oxygen + panel (visual grouping). */
	public static function plus_section( $sections ) {
		if ( is_array( $sections ) ) {
			$sections['velox'] = 'Velox';
		}
		return $sections;
	}

	/**
	 * Builder-side live preview: Oxygen posts the chosen connection + preset and we
	 * return the rendered HTML so the element shows real reviews inside the builder.
	 */
	public static function ajax_render() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error();
		}
		check_ajax_referer( 'velox_reviews_oxy', 'nonce' );
		$conn   = isset( $_POST['connection'] ) ? sanitize_key( $_POST['connection'] ) : '';
		$preset = isset( $_POST['preset'] ) ? sanitize_key( $_POST['preset'] ) : '';
		$html   = do_shortcode( sprintf( '[velox_reviews connection="%s" preset="%s"]', $conn, $preset ) );
		wp_send_json_success( array( 'html' => $html ) );
	}

	/** Options used by both the Oxygen control and any picker: connections + presets. */
	public static function options() {
		$store = Velox_Reviews::store();
		$conns = array();
		foreach ( $store['connections'] as $c ) {
			$conns[ $c['id'] ] = $c['name'];
		}
		$presets = array();
		foreach ( $store['presets'] as $p ) {
			$presets[ $p['id'] ] = $p['name'] . ' (' . ( 'static' === $p['type'] ? 'grid' : 'slider' ) . ')';
		}
		return array( 'connections' => $conns, 'presets' => $presets );
	}
}
