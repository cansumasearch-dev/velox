<?php
/**
 * Velox icon set: Bootstrap Icons 1.11.3 (MIT, see assets/icons/LICENSE-bootstrap-icons.txt).
 *
 * One icon set everywhere: the editor UI, the icon picker and the icons on the
 * site all come from Bootstrap Icons. The SVGs ship with the plugin as JSON
 * (nothing is fetched from a CDN, so no visitor IP leaves the site). The editor
 * loads the whole set once; the front end reads only the small file for the
 * first letter of the icon it needs.
 *
 * Names from the earlier hand-drawn set (phone, mail, map-pin, …) still work:
 * they are aliases for their Bootstrap equivalent, so existing pages keep their icons.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Velox_Icons {

	const VERSION = '1.11.3';

	/** Old Velox icon names => Bootstrap Icons names. */
	public static function aliases() {
		return array(
			'phone' => 'telephone',
			'mail' => 'envelope',
			'message' => 'chat-dots',
			'map-pin' => 'geo-alt',
			'navigation' => 'cursor',
			'calendar' => 'calendar3',
			'globe' => 'globe2',
			'menu' => 'list',
			'x' => 'x-lg',
			'plus' => 'plus-lg',
			'minus' => 'dash-lg',
			'check' => 'check-lg',
			'external' => 'box-arrow-up-right',
			'link' => 'link-45deg',
			'settings' => 'gear',
			'info' => 'info-circle',
			'alert' => 'exclamation-triangle',
			'help' => 'question-circle',
			'filter' => 'funnel',
			'thumbs-up' => 'hand-thumbs-up',
			'euro' => 'currency-euro',
			'user' => 'person',
			'users' => 'people',
			'home' => 'house',
			'package' => 'box-seam',
			'paint' => 'palette',
			'car' => 'car-front',
			'zap' => 'lightning-charge',
			'play' => 'play-circle',
			'file' => 'file-earmark',
			'trending-up' => 'graph-up-arrow',
			'target' => 'bullseye',
			'coffee' => 'cup-hot',
		);
	}

	/** The Bootstrap name for any name we may have stored. */
	public static function resolve( $name ) {
		$name = strtolower( preg_replace( '/[^a-z0-9-]/i', '', (string) $name ) );
		$a    = self::aliases();
		return isset( $a[ $name ] ) ? $a[ $name ] : $name;
	}

	/** URL of the full set the editor's icon picker loads. */
	public static function bundle_url() {
		return VELOX_URL . 'assets/icons/bootstrap-icons.json?v=' . self::VERSION;
	}

	/** SVG body (paths) of one icon, '' when unknown. Shards are cached per request. */
	public static function inner( $name ) {
		static $shards = array();
		$name = self::resolve( $name );
		if ( '' === $name ) {
			return '';
		}
		$c = $name[0];
		if ( ! isset( $shards[ $c ] ) ) {
			$file          = VELOX_PATH . 'assets/icons/bi-' . $c . '.json';
			$shards[ $c ]  = is_readable( $file ) ? (array) json_decode( (string) file_get_contents( $file ), true ) : array(); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		}
		return isset( $shards[ $c ][ $name ] ) ? (string) $shards[ $c ][ $name ] : '';
	}

	/** Inline SVG for one icon, or an empty string when the name is unknown. */
	public static function svg( $name, $size = 24, $class = '' ) {
		$inner = self::inner( $name );
		if ( '' === $inner ) {
			return '';
		}
		return '<svg' . ( $class ? ' class="' . esc_attr( $class ) . '"' : '' ) . ' width="' . (int) $size . '" height="' . (int) $size .
			'" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true" focusable="false">' . $inner . '</svg>';
	}

	/** Kept for older callers: the alias names, so a stored value is always recognised. */
	public static function all() {
		$out = array();
		foreach ( self::aliases() as $old => $new ) {
			$out[ $old ] = array( 'legacy', $new );
		}
		return $out;
	}
}
