<?php
/**
 * Accessibility menu for visitors.
 *
 * A floating button that opens a panel of display aids: bigger text, more
 * spacing, contrast, a readable font, highlighted links, no motion, no images,
 * a big cursor, a reading guide and read-aloud. Each visitor's choice stays in
 * their browser (localStorage) and is put on <html> before the page paints.
 *
 * Honest by design: an overlay helps people adjust what they see. It does not
 * make a site accessible or BFSG / WCAG compliant on its own, and the settings
 * page says so.
 *
 * @package Velox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Velox_A11y {

	/** Feature keys, in panel order. Each has a setting a11y_f_<key>. */
	const FEATURES = array( 'text', 'spacing', 'contrast', 'font', 'links', 'motion', 'images', 'cursor', 'guide', 'read' );

	public static function init() {
		if ( ! Velox_Settings::get( 'util_a11y' ) || is_admin() ) {
			return;
		}
		add_action( 'wp_head', array( __CLASS__, 'boot' ), 1 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	/** Not in page-builder editors, previews or embeds: only for visitors' pages. */
	private static function skip() {
		if ( is_feed() || is_embed() || wp_doing_ajax() ) {
			return true;
		}
		if ( class_exists( 'Velox' ) && method_exists( 'Velox', 'is_builder_request' ) && Velox::is_builder_request() ) {
			return true;
		}
		return isset( $_GET['velox_canvas'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	/** The features this site offers. */
	public static function features() {
		$on = array();
		foreach ( self::FEATURES as $f ) {
			if ( Velox_Settings::get( 'a11y_f_' . $f, true ) ) {
				$on[] = $f;
			}
		}
		return $on;
	}

	/**
	 * Before anything paints: the visitor's saved choices become classes on
	 * <html>, so a page never flashes in the wrong contrast or spacing.
	 */
	public static function boot() {
		if ( self::skip() ) {
			return;
		}
		echo "<script id=\"velox-a11y-boot\">(function(){try{var s=JSON.parse(localStorage.getItem('vxa11y')||'{}'),c=document.documentElement.classList,k;for(k in s){if(s[k]&&k!=='text'){c.add('vxa-'+k+(s[k]>1?'-'+s[k]:''));}}}catch(e){}})();</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	public static function enqueue() {
		if ( self::skip() ) {
			return;
		}
		$feats = self::features();
		if ( ! $feats ) {
			return;
		}
		wp_enqueue_style( 'velox-a11y', VELOX_URL . 'assets/css/velox-a11y.css', array(), VELOX_VERSION );
		wp_enqueue_script( 'velox-a11y', VELOX_URL . 'assets/js/velox-a11y.js', array(), VELOX_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
		$color = sanitize_hex_color( (string) Velox_Settings::get( 'a11y_color', '#1d4ed8' ) );
		$cfg   = array(
			'side'      => 'left' === Velox_Settings::get( 'a11y_side', 'right' ) ? 'left' : 'right',
			'color'     => $color ? $color : '#1d4ed8',
			'mobile'    => (bool) Velox_Settings::get( 'a11y_mobile', true ),
			'features'  => $feats,
			'profiles'  => (bool) Velox_Settings::get( 'a11y_f_profiles', true ),
			'statement' => esc_url_raw( (string) Velox_Settings::get( 'a11y_statement', '' ) ),
			'lang'      => str_replace( '_', '-', get_locale() ),
			't'         => self::texts(),
		);
		wp_add_inline_script( 'velox-a11y', 'window.VELOX_A11Y=' . wp_json_encode( $cfg ) . ';', 'before' );
	}

	/** Everything the panel says, in the site language. */
	private static function texts() {
		return array(
			'open'      => __( 'Accessibility', 'velox' ),
			'title'     => __( 'Accessibility', 'velox' ),
			'intro'     => __( 'Adjust this website to how you read best. Your choice is saved in this browser.', 'velox' ),
			'close'     => __( 'Close', 'velox' ),
			'reset'     => __( 'Reset all', 'velox' ),
			'profiles'  => __( 'Profiles', 'velox' ),
			'tools'     => __( 'Adjustments', 'velox' ),
			'statement' => __( 'Accessibility statement', 'velox' ),
			'off'       => __( 'Off', 'velox' ),
			'on'        => __( 'On', 'velox' ),
			'p_vision'  => __( 'Low vision', 'velox' ),
			'p_vision_d'   => __( 'Larger text, clear links, big cursor', 'velox' ),
			'p_dyslexia'   => __( 'Dyslexia friendly', 'velox' ),
			'p_dyslexia_d' => __( 'Readable font, more spacing, reading guide', 'velox' ),
			'p_seizure'    => __( 'Seizure safe', 'velox' ),
			'p_seizure_d'  => __( 'No motion, calmer colours', 'velox' ),
			'p_focus'      => __( 'Focus (ADHD)', 'velox' ),
			'p_focus_d'    => __( 'No motion, reading guide', 'velox' ),
			'f_text'    => __( 'Text size', 'velox' ),
			'f_text_v'  => array( __( 'Normal', 'velox' ), '+15 %', '+30 %', '+50 %' ),
			'f_spacing' => __( 'Spacing', 'velox' ),
			'f_spacing_v' => array( __( 'Normal', 'velox' ), __( 'More', 'velox' ), __( 'Most', 'velox' ) ),
			'f_contrast'  => __( 'Contrast', 'velox' ),
			'f_contrast_v' => array( __( 'Normal', 'velox' ), __( 'Dark', 'velox' ), __( 'Light', 'velox' ), __( 'Greyscale', 'velox' ) ),
			'f_font'    => __( 'Readable font', 'velox' ),
			'f_links'   => __( 'Highlight links', 'velox' ),
			'f_motion'  => __( 'Stop animations', 'velox' ),
			'f_images'  => __( 'Hide images', 'velox' ),
			'f_cursor'  => __( 'Big cursor', 'velox' ),
			'f_guide'   => __( 'Reading guide', 'velox' ),
			'f_read'    => __( 'Read aloud', 'velox' ),
			'read_hint' => __( 'Click any text to hear it.', 'velox' ),
			'note'      => __( 'These aids change how the page looks in your browser only.', 'velox' ),
		);
	}
}
