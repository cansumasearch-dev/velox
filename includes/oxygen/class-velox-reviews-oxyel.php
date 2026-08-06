<?php
/**
 * Velox Reviews — Oxygen element.
 *
 * A thin Oxygen element: two dropdowns (Connection, Preset) that render the
 * [velox_reviews] shortcode. Styling lives in the preset, edited on the utility
 * page — so what you see in Oxygen matches the front end exactly, and there is one
 * source of truth for the design.
 *
 * Loaded only when Oxygen's OxyEl base class exists (builder/runtime).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'OxyEl' ) ) {
	return;
}

class Velox_Reviews_OxyEl extends OxyEl {

	public function name() {
		return 'Velox Reviews';
	}

	public function slug() {
		return 'velox_reviews';
	}

	public function icon() {
		// Oxygen accepts a path to an SVG; fall back to a built-in if missing.
		return VELOX_PATH . 'assets/img/reviews-oxy.svg';
	}

	public function button() {
		// Shown in the + panel; group under our "Velox" section when supported.
		$this->add_button( $this->button_place( 'velox' ) );
	}

	public function controls() {
		// Oxygen's control API varies across versions. Guard every call so a signature
		// mismatch can't fatal the builder — worst case the element shows with no
		// custom controls and you use the shortcode instead.
		if ( ! method_exists( $this, 'addControlSection' ) ) {
			return;
		}
		try {
			$opts    = Velox_Reviews_Oxygen::options();
			$conns   = $opts['connections'];
			$presets = $opts['presets'];

			$c = $this->addControlSection( 'velox_reviews_conn', __( 'Reviews connection', 'velox' ), 'assets/icon.png', $this );

			$conn_ctrl = $c->addOptionControl( array(
				'type'  => 'dropdown',
				'name'  => __( 'Connection', 'velox' ),
				'slug'  => 'velox_conn',
				'value' => '',
				'css'   => false,
			) );
			if ( is_object( $conn_ctrl ) && method_exists( $conn_ctrl, 'setValue' ) ) {
				$conn_ctrl->setValue( $conns );
			}

			$preset_ctrl = $c->addOptionControl( array(
				'type'  => 'dropdown',
				'name'  => __( 'Preset', 'velox' ),
				'slug'  => 'velox_preset',
				'value' => '',
				'css'   => false,
			) );
			if ( is_object( $preset_ctrl ) && method_exists( $preset_ctrl, 'setValue' ) ) {
				$preset_ctrl->setValue( $presets );
			}
		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Velox Reviews Oxygen controls: ' . $e->getMessage() );
			}
		}
	}

	public function render( $options, $defaults, $content ) {
		$conn   = isset( $options['velox_conn'] ) ? sanitize_key( $options['velox_conn'] ) : '';
		$preset = isset( $options['velox_preset'] ) ? sanitize_key( $options['velox_preset'] ) : '';
		if ( '' === $conn ) {
			echo '<div style="padding:16px;border:1px dashed #cbd5e1;border-radius:8px;color:#64748b;font:13px system-ui;">'
				. esc_html__( 'Velox Reviews — pick a connection (and preset) in the element settings.', 'velox' )
				. '</div>';
			return;
		}
		echo do_shortcode( sprintf( '[velox_reviews connection="%s" preset="%s"]', $conn, $preset ) );
	}
}

// Register the element instance (Oxygen collects it).
new Velox_Reviews_OxyEl();
