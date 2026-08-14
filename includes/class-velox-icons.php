<?php
/**
 * Velox icon set.
 *
 * A curated set rather than a whole icon library: ~70 icons that cover what
 * agency sites actually need, shipped as inline SVG paths so nothing is
 * downloaded from a CDN (which would leak visitor IPs — see the Google Fonts
 * ruling) and no icon font is loaded for the sake of three glyphs.
 *
 * Paths are drawn on a 24x24 grid, stroked, matching the editor's own icons.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Velox_Icons {

	/** name => [ group, path-data ] */
	public static function all() {
		return array(
			// Contact
			'phone'        => array( 'contact', '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>' ),
			'mail'         => array( 'contact', '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>' ),
			'message'      => array( 'contact', '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>' ),
			'send'         => array( 'contact', '<path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/>' ),
			'map-pin'      => array( 'contact', '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/>' ),
			'navigation'   => array( 'contact', '<polygon points="3 11 22 2 13 21 11 13 3 11"/>' ),
			'clock'        => array( 'contact', '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>' ),
			'calendar'     => array( 'contact', '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>' ),
			'globe'        => array( 'contact', '<circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10Z"/>' ),
			'printer'      => array( 'contact', '<path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/>' ),
			// Interface
			'menu'         => array( 'interface', '<path d="M4 6h16M4 12h16M4 18h16"/>' ),
			'x'            => array( 'interface', '<path d="M18 6 6 18M6 6l12 12"/>' ),
			'search'       => array( 'interface', '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>' ),
			'chevron-down' => array( 'interface', '<path d="m6 9 6 6 6-6"/>' ),
			'chevron-right'=> array( 'interface', '<path d="m9 18 6-6-6-6"/>' ),
			'chevron-up'   => array( 'interface', '<path d="m18 15-6-6-6 6"/>' ),
			'arrow-right'  => array( 'interface', '<path d="M5 12h14M12 5l7 7-7 7"/>' ),
			'arrow-left'   => array( 'interface', '<path d="M19 12H5M12 19l-7-7 7-7"/>' ),
			'arrow-up'     => array( 'interface', '<path d="M12 19V5M5 12l7-7 7 7"/>' ),
			'plus'         => array( 'interface', '<path d="M5 12h14M12 5v14"/>' ),
			'minus'        => array( 'interface', '<path d="M5 12h14"/>' ),
			'check'        => array( 'interface', '<path d="M20 6 9 17l-5-5"/>' ),
			'check-circle' => array( 'interface', '<circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/>' ),
			'external'     => array( 'interface', '<path d="M15 3h6v6M10 14 21 3M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>' ),
			'download'     => array( 'interface', '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/>' ),
			'upload'       => array( 'interface', '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12"/>' ),
			'share'        => array( 'interface', '<circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.6 13.5 6.8 4M15.4 6.5l-6.8 4"/>' ),
			'link'         => array( 'interface', '<path d="M10 13a5 5 0 0 0 7.5.5l3-3a5 5 0 0 0-7-7l-1.7 1.7"/><path d="M14 11a5 5 0 0 0-7.5-.5l-3 3a5 5 0 0 0 7 7l1.7-1.7"/>' ),
			'settings'     => array( 'interface', '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-2.9 1.2 2 2 0 1 1-4 0 1.7 1.7 0 0 0-2.9-1.2l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1A1.7 1.7 0 0 0 4.6 15a2 2 0 1 1 0-4 1.7 1.7 0 0 0 1.2-2.9l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 2.9-1.2 2 2 0 1 1 4 0 1.7 1.7 0 0 0 2.9 1.2l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0 1.2 2.9 2 2 0 1 1 0 4Z"/>' ),
			'info'         => array( 'interface', '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/>' ),
			'alert'        => array( 'interface', '<path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4M12 17h.01"/>' ),
			'help'         => array( 'interface', '<circle cx="12" cy="12" r="10"/><path d="M9.1 9a3 3 0 0 1 5.8 1c0 2-3 3-3 3M12 17h.01"/>' ),
			'eye'          => array( 'interface', '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>' ),
			'filter'       => array( 'interface', '<polygon points="22 3 2 3 10 12.5 10 19 14 21 14 12.5 22 3"/>' ),
			// Commerce & trust
			'star'         => array( 'trust', '<polygon points="12 2 15.1 8.6 22 9.7 17 14.6 18.2 21.5 12 18.3 5.8 21.5 7 14.6 2 9.7 8.9 8.6 12 2"/>' ),
			'heart'        => array( 'trust', '<path d="M19 14c1.5-1.5 3-3.4 3-5.5A5.5 5.5 0 0 0 12 5a5.5 5.5 0 0 0-10 3.5c0 2.1 1.5 4 3 5.5l7 7Z"/>' ),
			'shield'       => array( 'trust', '<path d="M20 13c0 5-3.5 7.5-8 9-4.5-1.5-8-4-8-9V6l8-3 8 3Z"/>' ),
			'shield-check' => array( 'trust', '<path d="M20 13c0 5-3.5 7.5-8 9-4.5-1.5-8-4-8-9V6l8-3 8 3Z"/><path d="m9 12 2 2 4-4"/>' ),
			'award'        => array( 'trust', '<circle cx="12" cy="8" r="6"/><path d="M15.5 13.5 17 22l-5-3-5 3 1.5-8.5"/>' ),
			'thumbs-up'    => array( 'trust', '<path d="M7 10v12M15 5.9 14 10h5.8a2 2 0 0 1 2 2.3l-1.4 9a2 2 0 0 1-2 1.7H7V10l4-8a3 3 0 0 1 4 3.9Z"/>' ),
			'lock'         => array( 'trust', '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>' ),
			'euro'         => array( 'trust', '<path d="M4 10h12M4 14h9M18.5 19a7.5 7.5 0 1 1 0-14"/>' ),
			'credit-card'  => array( 'trust', '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/>' ),
			'tag'          => array( 'trust', '<path d="M12.6 2.6A2 2 0 0 0 11.2 2H4a2 2 0 0 0-2 2v7.2a2 2 0 0 0 .6 1.4l8.8 8.8a2 2 0 0 0 2.8 0l7.2-7.2a2 2 0 0 0 0-2.8Z"/><path d="M7 7h.01"/>' ),
			'gift'         => array( 'trust', '<rect x="3" y="8" width="18" height="4"/><path d="M12 8v13M20 12v7a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-7"/><path d="M7.5 8a2.5 2.5 0 0 1 0-5C11 3 12 8 12 8s1-5 4.5-5a2.5 2.5 0 0 1 0 5"/>' ),
			// People & business
			'user'         => array( 'people', '<circle cx="12" cy="8" r="4"/><path d="M20 21a8 8 0 0 0-16 0"/>' ),
			'users'        => array( 'people', '<circle cx="9" cy="8" r="4"/><path d="M16 3.1a4 4 0 0 1 0 7.8M17 21a8 8 0 0 0-16 0M23 21a6 6 0 0 0-4-5.7"/>' ),
			'briefcase'    => array( 'people', '<rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>' ),
			'building'     => array( 'people', '<rect x="4" y="2" width="16" height="20" rx="2"/><path d="M9 22v-4h6v4M8 6h.01M16 6h.01M8 10h.01M16 10h.01M8 14h.01M16 14h.01"/>' ),
			'home'         => array( 'people', '<path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2Z"/><path d="M9 22V12h6v10"/>' ),
			'quote'        => array( 'people', '<path d="M3 21c3 0 7-1 7-8V5a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h3"/><path d="M14 21c3 0 7-1 7-8V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h3"/>' ),
			// Trades & services
			'wrench'       => array( 'trade', '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.8-3.8a6 6 0 0 1-7.9 7.9l-6.9 6.9a2.1 2.1 0 0 1-3-3l6.9-6.9a6 6 0 0 1 7.9-7.9l-3.7 3.8Z"/>' ),
			'hammer'       => array( 'trade', '<path d="m15 12-8.4 8.4a2.1 2.1 0 0 1-3-3L12 9"/><path d="m18 15 4-4M14.5 5.5 18 2l4 4-3.5 3.5a2.1 2.1 0 0 1-3 0l-1-1a2.1 2.1 0 0 1 0-3Z"/>' ),
			'key'          => array( 'trade', '<circle cx="7.5" cy="15.5" r="5.5"/><path d="m21 2-9.6 9.6M15.5 7.5l3 3L22 7l-3-3"/>' ),
			'truck'        => array( 'trade', '<path d="M10 17h4V5H2v12h3M14 9h4l4 4v4h-3"/><circle cx="7.5" cy="17.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/>' ),
			'package'      => array( 'trade', '<path d="m7.5 4.3 9 5.2M21 16V8a2 2 0 0 0-1-1.7l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.7l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5M12 22V12"/>' ),
			'paint'        => array( 'trade', '<rect x="2" y="2" width="20" height="8" rx="2"/><path d="M10 10v4a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2v-2M12 16v6"/>' ),
			'car'          => array( 'trade', '<path d="M19 17h2v-5l-2-5H5L3 12v5h2"/><circle cx="7" cy="17" r="2"/><circle cx="17" cy="17" r="2"/>' ),
			'zap'          => array( 'trade', '<polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>' ),
			'wifi'         => array( 'trade', '<path d="M5 13a10 10 0 0 1 14 0M8.5 16.5a5 5 0 0 1 7 0M2 8.8a15 15 0 0 1 20 0M12 20h.01"/>' ),
			// Media & content
			'image'        => array( 'media', '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-5-5L5 21"/>' ),
			'camera'       => array( 'media', '<path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/>' ),
			'play'         => array( 'media', '<polygon points="6 3 20 12 6 21 6 3"/>' ),
			'file'         => array( 'media', '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v6h6"/>' ),
			'folder'       => array( 'media', '<path d="M4 20h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.7-.9L9.6 3.9A2 2 0 0 0 7.9 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z"/>' ),
			'bar-chart'    => array( 'media', '<path d="M12 20V10M18 20V4M6 20v-4"/>' ),
			'trending-up'  => array( 'media', '<polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/>' ),
			'target'       => array( 'media', '<circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/>' ),
			'coffee'       => array( 'media', '<path d="M17 8h1a4 4 0 1 1 0 8h-1M3 8h14v9a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4Z"/><path d="M6 2v2M10 2v2M14 2v2"/>' ),
			'sun'          => array( 'media', '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>' ),
		);
	}

	/** Icon names grouped for the picker. */
	public static function groups() {
		$out = array();
		foreach ( self::all() as $name => $def ) {
			$out[ $def[0] ][] = $name;
		}
		return $out;
	}

	/** Inline SVG for one icon, or an empty string when the name is unknown. */
	public static function svg( $name, $size = 24, $class = '' ) {
		$all = self::all();
		$name = (string) $name;
		if ( ! isset( $all[ $name ] ) ) {
			return '';
		}
		return '<svg class="' . esc_attr( $class ) . '" width="' . (int) $size . '" height="' . (int) $size .
			'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' .
			$all[ $name ][1] . '</svg>';
	}
}
