<?php
/**
 * Velox — Cookie consent banner.
 *
 * Renders a fully styleable consent banner on the front end and wires it to
 * Google Consent Mode v2. The banner's CSS and HTML are produced by
 * style_block() and markup() so the admin live-preview can render the EXACT
 * same banner — no more drift between preview and the real thing.
 *
 * @package Velox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Velox_Cookies {

	const COOKIE = 'velox_consent';

	public static function init() {
		if ( is_admin() ) {
			return;
		}
		if ( ! Velox_Settings::get( 'util_cookies', false ) ) {
			return;
		}
		add_action( 'wp_head', array( __CLASS__, 'head' ), 0 );
		add_action( 'wp_footer', array( __CLASS__, 'footer' ), 20 );
	}

	/** Categories the visitor has already granted (server-side read of the cookie). */
	private static function granted() {
		if ( empty( $_COOKIE[ self::COOKIE ] ) ) {
			return null;
		}
		$raw = sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE ] ) );
		if ( 'deny' === $raw ) {
			return array();
		}
		return array_filter( array_map( 'trim', explode( ',', $raw ) ) );
	}

	public static function head() {
		$consent_mode = Velox_Settings::get( 'cookie_consent_mode', true );
		$ga           = trim( (string) Velox_Settings::get( 'cookie_ga_id', '' ) );
		if ( ! $consent_mode ) {
			return;
		}
		$granted   = self::granted();
		$analytics = ( is_array( $granted ) && in_array( 'analytics', $granted, true ) ) ? 'granted' : 'denied';
		$marketing = ( is_array( $granted ) && in_array( 'marketing', $granted, true ) ) ? 'granted' : 'denied';
		?>
<!-- Velox Consent Mode v2 -->
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('consent','default',{
	ad_storage:'denied', ad_user_data:'denied', ad_personalization:'denied',
	analytics_storage:'denied', functionality_storage:'granted', security_storage:'granted',
	wait_for_update: 500
});
<?php if ( null !== $granted ) : ?>
gtag('consent','update',{
	analytics_storage:'<?php echo esc_js( $analytics ); ?>',
	ad_storage:'<?php echo esc_js( $marketing ); ?>',
	ad_user_data:'<?php echo esc_js( $marketing ); ?>',
	ad_personalization:'<?php echo esc_js( $marketing ); ?>'
});
<?php endif; ?>
</script>
		<?php
		if ( '' !== $ga ) {
			$is_gtm = ( 0 === stripos( $ga, 'GTM-' ) );
			if ( $is_gtm ) {
				?>
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','<?php echo esc_js( $ga ); ?>');</script>
				<?php
			} else {
				?>
<script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo esc_attr( rawurlencode( $ga ) ); ?>"></script>
<script>gtag('js', new Date()); gtag('config','<?php echo esc_js( $ga ); ?>');</script>
				<?php
			}
		}
	}

	/**
	 * Collect every styling/content option (from settings, or overridden by
	 * $override for the live preview). One place defines the option set.
	 */
	public static function options( $override = array() ) {
		$keys = array(
			'cookie_layout' => 'bar-bottom', 'cookie_animation' => 'slide-up', 'cookie_heading' => 'We value your privacy', 'cookie_body' => 'We use cookies to improve your browsing experience, analyse site traffic and personalise content. You can choose which categories you allow.',
			'cookie_btn_accept' => 'Accept all', 'cookie_btn_reject' => 'Reject non-essential',
			'cookie_btn_settings' => 'Preferences', 'cookie_small_text' => '', 'cookie_logo' => '',
			'cookie_link1_label' => '', 'cookie_link1_url' => '', 'cookie_link2_label' => '', 'cookie_link2_url' => '',
			'cookie_cat_analytics' => true, 'cookie_cat_marketing' => true,
			'cookie_bg' => '#ffffff', 'cookie_text' => '#1d1d1f', 'cookie_accent' => '#2ab7f1',
			'cookie_accent_text' => '#ffffff', 'cookie_btn2_bg' => '#f1f2f5', 'cookie_btn2_text' => '#1d1d1f',
			'cookie_border_color' => '#e6e7eb', 'cookie_border_width' => 1, 'cookie_radius' => 16,
			'cookie_shadow' => true, 'cookie_overlay' => false, 'cookie_offset' => 24,
			'cookie_reconsent_days' => 180,
			// new responsive controls
			'cookie_layout_mobile' => 'inherit', 'cookie_width' => 460, 'cookie_font_size' => 14,
			'cookie_btn_full_mobile' => true,
			// Oxygen-style structural layout controls
			'cookie_layout_mode' => 'preset', 'cookie_display' => 'flex', 'cookie_direction' => 'row',
			'cookie_align' => 'center', 'cookie_justify' => 'space-between', 'cookie_gap' => 24,
			'cookie_grid_cols' => 2, 'cookie_pad_y' => 22, 'cookie_pad_x' => 24, 'cookie_margin' => 0,
			// NEW: dynamic buttons (JSON array of button objects), advanced custom CSS,
			// and expanded banner-wide styling controls.
			'cookie_buttons' => self::default_buttons_json(),
			'cookie_custom_css' => '',
			'cookie_heading_size' => 0, 'cookie_heading_weight' => 0, 'cookie_heading_color' => '',
			'cookie_body_size' => 0, 'cookie_body_color' => '',
			'cookie_link_color' => '', 'cookie_link_underline' => true,
			'cookie_btn_gap' => 10, 'cookie_btn_font_size' => 14, 'cookie_btn_font_weight' => 600,
			'cookie_backdrop_blur' => 0, 'cookie_overlay_color' => 'rgba(10,12,20,.45)',
			'cookie_max_height' => 0, 'cookie_z_index' => 0,
		);
		$out = array();
		foreach ( $keys as $k => $d ) {
			$out[ $k ] = array_key_exists( $k, $override ) ? $override[ $k ] : Velox_Settings::get( $k, $d );
		}
		// Decode the buttons list into an array for rendering.
		$out['cookie_buttons_list'] = self::parse_buttons( $out['cookie_buttons'] );
		return $out;
	}

	/** Default button set as JSON (matches the old three-button behaviour). */
	public static function default_buttons_json() {
		return wp_json_encode( array(
			array( 'id' => 'b1', 'label' => 'Accept all',            'action' => 'accept',      'element' => 'button', 'url' => '', 'variant' => 'primary' ),
			array( 'id' => 'b2', 'label' => 'Reject non-essential',  'action' => 'reject',      'element' => 'button', 'url' => '', 'variant' => 'secondary' ),
			array( 'id' => 'b3', 'label' => 'Preferences',           'action' => 'preferences', 'element' => 'button', 'url' => '', 'variant' => 'secondary' ),
		) );
	}

	/** Decode + sanitise the stored buttons JSON into a clean array of button objects. */
	public static function parse_buttons( $json ) {
		$list = is_array( $json ) ? $json : json_decode( (string) $json, true );
		if ( ! is_array( $list ) ) { $list = json_decode( (string) self::default_buttons_json(), true ); }
		$actions  = array( 'accept', 'reject', 'preferences', 'save', 'link' );
		$elements = array( 'button', 'link' );
		$variants = array( 'primary', 'secondary', 'ghost', 'custom' );
		$style_keys = array( 'bg', 'color', 'border_color', 'border_width', 'radius', 'pad_y', 'pad_x', 'font_size', 'font_weight', 'hover_bg', 'hover_color', 'underline' );
		$out = array();
		$i = 0;
		foreach ( (array) $list as $b ) {
			if ( ! is_array( $b ) ) { continue; }
			$i++;
			$action  = in_array( ( $b['action'] ?? 'accept' ), $actions, true ) ? $b['action'] : 'accept';
			$element = in_array( ( $b['element'] ?? 'button' ), $elements, true ) ? $b['element'] : 'button';
			$variant = in_array( ( $b['variant'] ?? 'secondary' ), $variants, true ) ? $b['variant'] : 'secondary';
			$style = array();
			if ( ! empty( $b['style'] ) && is_array( $b['style'] ) ) {
				foreach ( $style_keys as $sk ) {
					if ( isset( $b['style'][ $sk ] ) && '' !== $b['style'][ $sk ] ) {
						$style[ $sk ] = sanitize_text_field( (string) $b['style'][ $sk ] );
					}
				}
			}
			$out[] = array(
				'id'      => preg_replace( '/[^a-z0-9_-]/i', '', (string) ( $b['id'] ?? ( 'b' . $i ) ) ) ?: ( 'b' . $i ),
				'label'   => sanitize_text_field( (string) ( $b['label'] ?? 'Button' ) ),
				'action'  => $action,
				'element' => $element,
				'url'     => esc_url_raw( (string) ( $b['url'] ?? '' ) ),
				'variant' => $variant,
				'style'   => $style,
			);
		}
		if ( empty( $out ) ) { $out = json_decode( (string) self::default_buttons_json(), true ); }
		return $out;
	}

	/**
	 * The banner's full CSS. $scope lets the admin preview namespace every rule
	 * under a container so it can't leak into the WP admin. For the front end,
	 * $scope is '' (global).
	 */
	public static function style_block( $o, $scope = '' ) {
		$bg       = self::hex( $o['cookie_bg'] );
		$text     = self::hex( $o['cookie_text'] );
		$accent   = self::hex( $o['cookie_accent'] );
		$accentTx = self::hex( $o['cookie_accent_text'] );
		$b2bg     = self::hex( $o['cookie_btn2_bg'] );
		$b2tx     = self::hex( $o['cookie_btn2_text'] );
		$bcol     = self::hex( $o['cookie_border_color'] );
		$bw       = (int) $o['cookie_border_width'];
		$rad      = (int) $o['cookie_radius'];
		$fs       = max( 11, min( 20, (int) $o['cookie_font_size'] ) );
		$btnrad   = max( 6, (int) round( $rad * 0.6 ) );
		$shadow   = ! empty( $o['cookie_shadow'] ) ? '0 18px 50px rgba(15,18,30,.22)' : 'none';
		$width    = max( 280, min( 720, (int) $o['cookie_width'] ) );
		$offset   = (int) $o['cookie_offset'];
		$layout   = (string) $o['cookie_layout'];
		$mobile   = (string) $o['cookie_layout_mobile'];
		$btn_full = ! empty( $o['cookie_btn_full_mobile'] );

		$pos = self::position_css( $layout, $offset, $width );
		// Entrance animation. The banner's resting state stays visible (no fill-mode),
		// so it always appears even if the animation can't run — the animation is only
		// a nice entrance, never what makes it show.
		$anim     = isset( $o['cookie_animation'] ) ? (string) $o['cookie_animation'] : 'slide-up';
		$anim_kf  = array(
			'slide-up'    => 'from{transform:translateY(120%)}to{transform:translateY(0)}',
			'slide-down'  => 'from{transform:translateY(-120%)}to{transform:translateY(0)}',
			'slide-left'  => 'from{transform:translateX(-120%)}to{transform:translateX(0)}',
			'slide-right' => 'from{transform:translateX(120%)}to{transform:translateX(0)}',
			'fade'        => 'from{opacity:0}to{opacity:1}',
			'zoom'        => 'from{opacity:0;transform:scale(.92)}to{opacity:1;transform:scale(1)}',
		);
		// Mobile layout: 'inherit' keeps desktop placement; otherwise switch.
		$mob_layout = ( 'inherit' === $mobile ) ? $layout : $mobile;
		$pos_m = self::position_css( $mob_layout, min( $offset, 12 ), $width );

		// --- Oxygen-style structural layout ---
		// In 'custom' mode the inner box layout is driven by explicit controls
		// instead of the preset's box rule. Positioning (where the box sits on
		// screen) always comes from the preset's 'root'.
		$custom_layout = ( isset( $o['cookie_layout_mode'] ) && 'custom' === $o['cookie_layout_mode'] );
		if ( $custom_layout ) {
			$disp  = in_array( $o['cookie_display'], array( 'flex', 'grid', 'block' ), true ) ? $o['cookie_display'] : 'flex';
			$gap   = max( 0, (int) $o['cookie_gap'] );
			$pad_y = max( 0, (int) $o['cookie_pad_y'] );
			$pad_x = max( 0, (int) $o['cookie_pad_x'] );
			$mgn   = max( 0, (int) $o['cookie_margin'] );
			$box_css = 'width:100%;';
			if ( 'flex' === $disp ) {
				$dir   = ( 'column' === $o['cookie_direction'] ) ? 'column' : 'row';
				$ai    = in_array( $o['cookie_align'], array( 'flex-start', 'center', 'flex-end', 'stretch' ), true ) ? $o['cookie_align'] : 'center';
				$ji    = in_array( $o['cookie_justify'], array( 'flex-start', 'center', 'flex-end', 'space-between', 'space-around' ), true ) ? $o['cookie_justify'] : 'space-between';
				$box_css .= 'display:flex;flex-wrap:wrap;flex-direction:' . $dir . ';align-items:' . $ai . ';justify-content:' . $ji . ';gap:' . $gap . 'px;';
			} elseif ( 'grid' === $disp ) {
				$cols  = max( 1, min( 4, (int) $o['cookie_grid_cols'] ) );
				$box_css .= 'display:grid;grid-template-columns:repeat(' . $cols . ',minmax(0,1fr));gap:' . $gap . 'px;align-items:center;';
			} else {
				$box_css .= 'display:block;';
			}
			if ( $mgn > 0 ) { $box_css .= 'margin:' . $mgn . 'px;'; }
			$pad_css = $pad_y . 'px ' . $pad_x . 'px';
			// Replace the preset box rule with our structural one.
			$pos['box'] = $box_css;
			$pad_decl = $pad_css;
		} else {
			$pad_decl = '22px 24px';
		}

		// p = prefix selector (scoped or global)
		$p = $scope ? $scope . ' ' : '';

		ob_start();
		?>
<?php echo $p; ?>.vxck-root{position:<?php echo $scope ? 'absolute' : 'fixed'; ?>;z-index:2147483600;<?php echo $pos['root']; // phpcs:ignore ?>}
<?php if ( ! $scope ) : ?>
.vxck-root[data-decided="1"]:not(.vxck-force){display:none;}
<?php if ( isset( $anim_kf[ $anim ] ) ) : ?>
@keyframes vxck-in{<?php echo $anim_kf[ $anim ]; // phpcs:ignore ?>}
.vxck-root[data-decided="0"] .vxck,.vxck-root.vxck-force .vxck{animation:vxck-in .45s cubic-bezier(.16,1,.3,1);}
@media(prefers-reduced-motion:reduce){.vxck-root .vxck{animation:none!important;}}
<?php endif; ?>
<?php endif; ?>
<?php echo $p; ?>.vxck-overlay{position:<?php echo $scope ? 'absolute' : 'fixed'; ?>;inset:0;background:rgba(8,10,18,.5);z-index:2147483500;}
<?php echo $p; ?>.vxck{box-sizing:border-box;<?php echo $pos['box']; // phpcs:ignore ?>;background:<?php echo $bg; ?>;color:<?php echo $text; ?>;border:<?php echo $bw; ?>px solid <?php echo $bcol; ?>;border-radius:<?php echo $rad; ?>px;box-shadow:<?php echo $shadow; ?>;padding:<?php echo $pad_decl; ?>;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;line-height:1.5;font-size:<?php echo $fs; ?>px;}
<?php echo $p; ?>.vxck *{box-sizing:border-box;}
<?php echo $p; ?>.vxck-main{flex:1 1 360px;min-width:0;}
<?php echo $p; ?>.vxck-logo{max-height:34px;width:auto;margin-bottom:12px;display:block;}
<?php echo $p; ?>.vxck-h{margin:0 0 8px;font-size:<?php echo $fs + 3; ?>px;font-weight:700;letter-spacing:-.01em;color:<?php echo $text; ?>;}
<?php echo $p; ?>.vxck-body{margin:0 0 14px;font-size:<?php echo $fs; ?>px;opacity:.85;}
<?php echo $p; ?>.vxck-links{display:flex;flex-wrap:wrap;gap:14px;margin:0 0 16px;font-size:<?php echo $fs - 1.5; ?>px;}
<?php echo $p; ?>.vxck-links a{color:<?php echo $accent; ?>;text-decoration:none;}
<?php echo $p; ?>.vxck-links a:hover{text-decoration:underline;}
<?php echo $p; ?>.vxck-actions{display:flex;flex-wrap:wrap;gap:<?php echo max( 0, (int) $o['cookie_btn_gap'] ); ?>px;<?php echo 'bar-bottom' === $layout ? 'flex:0 0 auto;align-items:center;' : ''; ?>}
<?php $btnfs = max( 10, (int) $o['cookie_btn_font_size'] ); $btnfw = (int) $o['cookie_btn_font_weight']; ?>
<?php echo $p; ?>.vxck-btn{appearance:none;border:0;cursor:pointer;font-size:<?php echo $btnfs; ?>px;font-weight:<?php echo $btnfw ? $btnfw : 600; ?>;padding:11px 18px;border-radius:<?php echo $btnrad; ?>px;transition:transform .08s,filter .15s;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;line-height:1.2;}
<?php echo $p; ?>.vxck-btn:active{transform:translateY(1px);}
<?php echo $p; ?>.vxck-btn--primary{background:<?php echo $accent; ?>;color:<?php echo $accentTx; ?>;}
<?php echo $p; ?>.vxck-btn--primary:hover{filter:brightness(1.05);}
<?php echo $p; ?>.vxck-btn--secondary{background:<?php echo $b2bg; ?>;color:<?php echo $b2tx; ?>;}
<?php echo $p; ?>.vxck-btn--secondary:hover{filter:brightness(.97);}
<?php echo $p; ?>.vxck-btn--ghost{background:transparent;color:<?php echo $text; ?>;text-decoration:underline;padding-left:6px;padding-right:6px;}
<?php echo $p; ?>.vxck-btn--ghost:hover{opacity:.7;}
<?php
		// Per-button custom styling: each button with style overrides gets its own rule.
		foreach ( $o['cookie_buttons_list'] as $btn ) {
			$s = $btn['style'];
			if ( empty( $s ) ) { continue; }
			$sel = $p . '.vxck-b-' . $btn['id'];
			$decl = '';
			if ( ! empty( $s['bg'] ) ) { $decl .= 'background:' . self::hex( $s['bg'] ) . ';'; }
			if ( ! empty( $s['color'] ) ) { $decl .= 'color:' . self::hex( $s['color'] ) . ';'; }
			if ( ! empty( $s['border_color'] ) || isset( $s['border_width'] ) ) {
				$bwid = isset( $s['border_width'] ) ? (int) $s['border_width'] : 1;
				$bc   = ! empty( $s['border_color'] ) ? self::hex( $s['border_color'] ) : 'currentColor';
				$decl .= 'border:' . $bwid . 'px solid ' . $bc . ';';
			}
			if ( isset( $s['radius'] ) && '' !== $s['radius'] ) { $decl .= 'border-radius:' . (int) $s['radius'] . 'px;'; }
			if ( ( isset( $s['pad_y'] ) && '' !== $s['pad_y'] ) || ( isset( $s['pad_x'] ) && '' !== $s['pad_x'] ) ) {
				$decl .= 'padding:' . (int) ( $s['pad_y'] ?? 11 ) . 'px ' . (int) ( $s['pad_x'] ?? 18 ) . 'px;';
			}
			if ( ! empty( $s['font_size'] ) ) { $decl .= 'font-size:' . (int) $s['font_size'] . 'px;'; }
			if ( ! empty( $s['font_weight'] ) ) { $decl .= 'font-weight:' . (int) $s['font_weight'] . ';'; }
			if ( ! empty( $s['underline'] ) ) { $decl .= 'text-decoration:underline;'; }
			if ( '' !== $decl ) { echo $p . '.vxck-b-' . esc_attr( $btn['id'] ) . '{' . $decl . '}' . "\n"; } // phpcs:ignore
			if ( ! empty( $s['hover_bg'] ) || ! empty( $s['hover_color'] ) ) {
				$h = '';
				if ( ! empty( $s['hover_bg'] ) ) { $h .= 'background:' . self::hex( $s['hover_bg'] ) . ';'; }
				if ( ! empty( $s['hover_color'] ) ) { $h .= 'color:' . self::hex( $s['hover_color'] ) . ';'; }
				echo $p . '.vxck-b-' . esc_attr( $btn['id'] ) . ':hover{' . $h . 'filter:none;}' . "\n"; // phpcs:ignore
			}
		}
?>
<?php echo $p; ?>.vxck-small{margin:14px 0 0;font-size:<?php echo $fs - 2.5; ?>px;opacity:.6;width:100%;}
<?php echo $p; ?>.vxck-prefs{margin:6px 0 16px;display:none;flex-direction:column;gap:10px;width:100%;}
<?php echo $p; ?>.vxck-prefs.is-open{display:flex;}
<?php echo $p; ?>.vxck-cat{display:flex;align-items:flex-start;gap:10px;font-size:<?php echo $fs - 1; ?>px;padding:10px 12px;border:1px solid <?php echo $bcol; ?>;border-radius:10px;}
<?php echo $p; ?>.vxck-cat input{margin-top:2px;}
<?php echo $p; ?>.vxck-cat strong{display:block;}
<?php echo $p; ?>.vxck-cat span{display:block;font-size:<?php echo $fs - 2; ?>px;opacity:.65;}
<?php
		// Responsive rules. In the preview we emulate via a body class instead of a
		// real media query (so the device tabs work), so only emit @media on front end.
		if ( $scope ) {
			?>
<?php echo $scope; ?>.is-mobile .vxck{<?php echo $pos_m['box_mobile']; // phpcs:ignore ?>}
<?php echo $scope; ?>.is-mobile .vxck-root{<?php echo $pos_m['root']; // phpcs:ignore ?>}
<?php if ( $btn_full ) : ?><?php echo $scope; ?>.is-mobile .vxck-actions{flex-direction:column;}<?php echo $scope; ?>.is-mobile .vxck-btn{width:100%;}<?php endif; ?>
<?php
		} else {
			?>
@media(max-width:600px){.vxck{<?php echo $pos_m['box_mobile']; // phpcs:ignore ?>}.vxck-root{<?php echo $pos_m['root']; // phpcs:ignore ?>}<?php if ( $btn_full ) : ?>.vxck-actions{flex-direction:column;}.vxck-btn{width:100%;}<?php endif; ?>}
<?php
		}

		// --- Expanded typography / overlay / sizing controls ---
		$extra = '';
		if ( (int) $o['cookie_heading_size'] > 0 ) { $extra .= $p . '.vxck-h{font-size:' . (int) $o['cookie_heading_size'] . 'px;}'; }
		if ( (int) $o['cookie_heading_weight'] > 0 ) { $extra .= $p . '.vxck-h{font-weight:' . (int) $o['cookie_heading_weight'] . ';}'; }
		if ( ! empty( $o['cookie_heading_color'] ) ) { $extra .= $p . '.vxck-h{color:' . self::hex( $o['cookie_heading_color'] ) . ';}'; }
		if ( (int) $o['cookie_body_size'] > 0 ) { $extra .= $p . '.vxck-body{font-size:' . (int) $o['cookie_body_size'] . 'px;}'; }
		if ( ! empty( $o['cookie_body_color'] ) ) { $extra .= $p . '.vxck-body{color:' . self::hex( $o['cookie_body_color'] ) . ';opacity:1;}'; }
		if ( ! empty( $o['cookie_link_color'] ) ) { $extra .= $p . '.vxck-links a{color:' . self::hex( $o['cookie_link_color'] ) . ';}'; }
		$extra .= $p . '.vxck-links a{text-decoration:' . ( ! empty( $o['cookie_link_underline'] ) ? 'underline' : 'none' ) . ';}';
		// Keep the banner's blocks packed together. Without this, a flex box with any
		// spare height (e.g. a vertical/column layout, or a max-height) spreads its
		// heading/body, categories and buttons into large empty gaps.
		$extra .= $p . '.vxck{align-content:flex-start;}';
		if ( isset( $o['cookie_layout_mode'] ) && 'custom' === $o['cookie_layout_mode'] && 'column' === $o['cookie_direction'] ) {
			$extra .= $p . '.vxck{justify-content:flex-start;}' . $p . '.vxck-main{flex-grow:0;}';
		}
		if ( (int) $o['cookie_backdrop_blur'] > 0 ) { $extra .= $p . '.vxck-overlay{backdrop-filter:blur(' . (int) $o['cookie_backdrop_blur'] . 'px);}'; }
		if ( ! empty( $o['cookie_overlay_color'] ) ) { $extra .= $p . '.vxck-overlay{background:' . self::safe_color( $o['cookie_overlay_color'] ) . ';}'; }
		if ( (int) $o['cookie_max_height'] > 0 ) { $extra .= $p . '.vxck{max-height:' . (int) $o['cookie_max_height'] . 'px;overflow:auto;}'; }
		if ( (int) $o['cookie_z_index'] > 0 ) { $extra .= $p . '.vxck-root{z-index:' . (int) $o['cookie_z_index'] . ';}'; }
		echo $extra; // phpcs:ignore

		// --- Advanced custom CSS (raw, scoped where possible) ---
		$custom = trim( (string) $o['cookie_custom_css'] );
		if ( '' !== $custom ) {
			// strip anything that could break out of a <style> block
			$custom = str_replace( array( '</style', '<script' ), '', $custom );
			echo "\n/* custom */\n" . $custom; // phpcs:ignore
		}
		return ob_get_clean();
	}

	/** Allow hex or rgba()/named colours for overlay etc. */
	private static function safe_color( $v ) {
		$v = trim( (string) $v );
		if ( preg_match( '/^(#[0-9a-f]{3,8}|rgba?\([\d.,\s%]+\)|[a-z]+)$/i', $v ) ) { return $v; }
		return 'rgba(10,12,20,.45)';
	}

	/**
	 * The banner markup. $preview=true makes buttons inert (type=button, no IDs
	 * that the front-end script binds to) so it's safe inside the admin.
	 */
	public static function markup( $o, $preview = false ) {
		$layout         = (string) $o['cookie_layout'];
		$overlay        = ! empty( $o['cookie_overlay'] );
		$show_analytics = ! empty( $o['cookie_cat_analytics'] );
		$show_marketing = ! empty( $o['cookie_cat_marketing'] );
		$id = function ( $x ) use ( $preview ) { return $preview ? '' : ' id="' . $x . '"'; };

		ob_start();
		?>
<?php if ( $overlay && 'modal-center' === $layout ) : ?><div class="vxck-overlay"></div><?php endif; ?>
<div class="vxck" role="dialog" aria-label="Cookie consent" aria-live="polite">
	<div class="vxck-main">
		<?php if ( ! empty( $o['cookie_logo'] ) ) : ?>
			<img class="vxck-logo" src="<?php echo esc_url( $o['cookie_logo'] ); ?>" alt="">
		<?php endif; ?>
		<?php if ( ! empty( $o['cookie_heading'] ) ) : ?>
			<p class="vxck-h"><?php echo esc_html( $o['cookie_heading'] ); ?></p>
		<?php endif; ?>
		<p class="vxck-body"><?php echo esc_html( $o['cookie_body'] ); ?></p>

		<?php if ( ! empty( $o['cookie_link1_label'] ) || ! empty( $o['cookie_link2_label'] ) ) : ?>
		<div class="vxck-links">
			<?php if ( ! empty( $o['cookie_link1_label'] ) ) : ?><a href="<?php echo esc_url( $o['cookie_link1_url'] ); ?>"><?php echo esc_html( $o['cookie_link1_label'] ); ?></a><?php endif; ?>
			<?php if ( ! empty( $o['cookie_link2_label'] ) ) : ?><a href="<?php echo esc_url( $o['cookie_link2_url'] ); ?>"><?php echo esc_html( $o['cookie_link2_label'] ); ?></a><?php endif; ?>
		</div>
		<?php endif; ?>
	</div>

	<div class="vxck-prefs"<?php echo $id( 'vxck-prefs' ); // phpcs:ignore ?>>
		<label class="vxck-cat">
			<input type="checkbox" checked disabled>
			<span><strong>Necessary</strong><span>Required for the site to work. Always on.</span></span>
		</label>
		<?php if ( $show_analytics ) : ?>
		<label class="vxck-cat">
			<input type="checkbox"<?php echo $id( 'vxck-analytics' ); // phpcs:ignore ?>>
			<span><strong>Analytics</strong><span>Helps us understand how the site is used.</span></span>
		</label>
		<?php endif; ?>
		<?php if ( $show_marketing ) : ?>
		<label class="vxck-cat">
			<input type="checkbox"<?php echo $id( 'vxck-marketing' ); // phpcs:ignore ?>>
			<span><strong>Marketing</strong><span>Used to personalise ads and measure campaigns.</span></span>
		</label>
		<?php endif; ?>
	</div>

	<div class="vxck-actions">
		<?php
		foreach ( $o['cookie_buttons_list'] as $btn ) :
			$cls = 'vxck-btn vxck-btn--' . $btn['variant'] . ' vxck-b-' . $btn['id'];
			// the "save choices" button only shows once preferences are open
			$extra = ( 'save' === $btn['action'] ) ? ' style="display:none;"' : '';
			$attrs = ' class="' . esc_attr( $cls ) . '" data-cookie-action="' . esc_attr( $btn['action'] ) . '"' . $extra;
			if ( 'link' === $btn['element'] ) :
				$href = ( 'link' === $btn['action'] && $btn['url'] ) ? $btn['url'] : '#';
				?>
				<a href="<?php echo esc_url( $href ); ?>"<?php echo $attrs; // phpcs:ignore ?>><?php echo esc_html( $btn['label'] ); ?></a>
			<?php else : ?>
				<button type="button"<?php echo $attrs; // phpcs:ignore ?>><?php echo esc_html( $btn['label'] ); ?></button>
			<?php endif;
		endforeach;
		?>
	</div>

	<?php if ( ! empty( $o['cookie_small_text'] ) ) : ?>
		<p class="vxck-small"><?php echo esc_html( $o['cookie_small_text'] ); ?></p>
	<?php endif; ?>
</div>
		<?php
		return ob_get_clean();
	}

	public static function footer() {
		$o       = self::options();
		$css     = self::style_block( $o, '' );
		$markup  = self::markup( $o, false );
		?>
<style><?php echo $css; // phpcs:ignore ?></style>
<div class="vxck-root" id="vxck-root" data-decided="0" data-reconsent="<?php echo (int) $o['cookie_reconsent_days']; ?>" data-show-display="<?php echo 'modal-center' === $o['cookie_layout'] ? 'flex' : 'block'; ?>">
<?php echo $markup; // phpcs:ignore WordPress.Security.EscapeOutput -- built with esc_* helpers ?>
</div>
<script>
(function(){
	var root=document.getElementById('vxck-root'); if(!root) return;
	var NAME='<?php echo esc_js( self::COOKIE ); ?>';
	function getCookie(n){var m=document.cookie.match('(?:^|; )'+n.replace(/([.$?*|{}()\[\]\\\/+^])/g,'\\$1')+'=([^;]*)');return m?decodeURIComponent(m[1]):null;}
	// Cache-proof: the server always ships the banner as "not decided", and the
	// client decides visibility from the real cookie. This stops full-page caches
	// (WP Fastest Cache / Cloudflare) from freezing one visitor's consent for all.
	root.setAttribute('data-decided', getCookie(NAME) ? '1' : '0');
	// Failsafe: if the visitor hasn't chosen yet, guarantee the banner is actually
	// visible — override a theme or optimiser that may have hidden .vxck-root.
	if ( ! getCookie(NAME) ) {
		try {
			if ( getComputedStyle(root).display === 'none' ) {
				root.style.setProperty('display', root.getAttribute('data-show-display') || 'block', 'important');
			}
			root.style.setProperty('visibility','visible','important');
		} catch(e){}
	}
	var days=parseInt(root.getAttribute('data-reconsent'),10)||180;
	function setCookie(v){var d=new Date();d.setTime(d.getTime()+days*864e5);document.cookie=NAME+'='+encodeURIComponent(v)+';expires='+d.toUTCString()+';path=/;SameSite=Lax';}
	function update(granted){
		if(typeof gtag==='function'){
			var a=granted.indexOf('analytics')>-1?'granted':'denied';
			var m=granted.indexOf('marketing')>-1?'granted':'denied';
			gtag('consent','update',{analytics_storage:a,ad_storage:m,ad_user_data:m,ad_personalization:m});
		}
		try{window.dispatchEvent(new CustomEvent('velox-consent-changed',{detail:{granted:granted}}));}catch(e){}
	}
	function close(){root.setAttribute('data-decided','1');root.classList.remove('vxck-force');}
	function open(){root.classList.add('vxck-force');}
	function decide(granted){setCookie(granted.length?granted.join(','):'deny');update(granted);close();}

	var prefs=document.getElementById('vxck-prefs'),
		ca=document.getElementById('vxck-analytics'),
		cm=document.getElementById('vxck-marketing');
	var saveBtns=root.querySelectorAll('[data-cookie-action="save"]');

	function showSave(on){for(var i=0;i<saveBtns.length;i++){saveBtns[i].style.display=on?'':'none';}}
	function openPrefs(){if(prefs){prefs.classList.add('is-open');}showSave(true);}
	function togglePrefs(){if(prefs){prefs.classList.toggle('is-open');showSave(prefs.classList.contains('is-open'));}}

	root.addEventListener('click',function(e){
		var t=e.target.closest('[data-cookie-action]');
		if(!t) return;
		var act=t.getAttribute('data-cookie-action');
		if(act==='accept'){e.preventDefault();decide(['analytics','marketing']);}
		else if(act==='reject'){e.preventDefault();decide([]);}
		else if(act==='preferences'){e.preventDefault();togglePrefs();}
		else if(act==='save'){e.preventDefault();var g=[];if(ca&&ca.checked)g.push('analytics');if(cm&&cm.checked)g.push('marketing');decide(g);}
		// 'link' buttons: let the anchor navigate normally (no preventDefault)
	});

	document.addEventListener('click',function(e){
		var t=e.target.closest('[data-cookie-settings],a[href="#cookie-settings"]');
		if(t){e.preventDefault();open();openPrefs();}
	});

	// Initial visibility is handled by CSS (.vxck-root[data-decided="1"] is hidden),
	// so the banner appears even if this script is delayed or optimised.
})();
</script>
		<?php
	}

	/* ---- helpers ---- */

	private static function hex( $v ) {
		$v = trim( (string) $v );
		return preg_match( '/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $v ) ? $v : '#000000';
	}

	private static function position_css( $layout, $offset, $width = 460 ) {
		$o = (int) $offset;
		$w = (int) $width;
		switch ( $layout ) {
			case 'box-bl':
				return array(
					'root'       => "left:{$o}px;bottom:{$o}px;",
					'box'        => "width:{$w}px;max-width:calc(100vw - " . ( $o * 2 ) . 'px)',
					'box_mobile' => 'width:auto;',
				);
			case 'box-br':
				return array(
					'root'       => "right:{$o}px;bottom:{$o}px;",
					'box'        => "width:{$w}px;max-width:calc(100vw - " . ( $o * 2 ) . 'px)',
					'box_mobile' => 'width:auto;',
				);
			case 'box-tl':
				return array(
					'root'       => "left:{$o}px;top:{$o}px;",
					'box'        => "width:{$w}px;max-width:calc(100vw - " . ( $o * 2 ) . 'px)',
					'box_mobile' => 'width:auto;',
				);
			case 'box-tr':
				return array(
					'root'       => "right:{$o}px;top:{$o}px;",
					'box'        => "width:{$w}px;max-width:calc(100vw - " . ( $o * 2 ) . 'px)',
					'box_mobile' => 'width:auto;',
				);
			case 'modal-center':
				return array(
					'root'       => 'inset:0;display:flex;align-items:center;justify-content:center;padding:' . $o . 'px;',
					'box'        => "position:relative;width:{$w}px;max-width:100%;z-index:2147483600",
					'box_mobile' => 'width:100%;',
				);
			case 'bar-top':
				return array(
					'root'       => "left:{$o}px;right:{$o}px;top:{$o}px;",
					'box'        => 'width:100%;display:flex;flex-wrap:wrap;align-items:center;gap:8px 24px',
					'box_mobile' => 'display:block;',
				);
			case 'bar-bottom':
			default:
				return array(
					'root'       => "left:{$o}px;right:{$o}px;bottom:{$o}px;",
					'box'        => 'width:100%;display:flex;flex-wrap:wrap;align-items:center;gap:8px 24px',
					'box_mobile' => 'display:block;',
				);
		}
	}
}
