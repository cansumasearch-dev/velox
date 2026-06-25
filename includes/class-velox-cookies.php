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
			'cookie_layout' => 'bar-bottom', 'cookie_heading' => '', 'cookie_body' => '',
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
		);
		$out = array();
		foreach ( $keys as $k => $d ) {
			$out[ $k ] = array_key_exists( $k, $override ) ? $override[ $k ] : Velox_Settings::get( $k, $d );
		}
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
		// Mobile layout: 'inherit' keeps desktop placement; otherwise switch.
		$mob_layout = ( 'inherit' === $mobile ) ? $layout : $mobile;
		$pos_m = self::position_css( $mob_layout, min( $offset, 12 ), $width );

		// p = prefix selector (scoped or global)
		$p = $scope ? $scope . ' ' : '';

		ob_start();
		?>
<?php echo $p; ?>.vxck-root{position:<?php echo $scope ? 'absolute' : 'fixed'; ?>;z-index:2147483600;<?php echo $pos['root']; // phpcs:ignore ?>}
<?php echo $p; ?>.vxck-overlay{position:<?php echo $scope ? 'absolute' : 'fixed'; ?>;inset:0;background:rgba(8,10,18,.5);z-index:2147483500;}
<?php echo $p; ?>.vxck{box-sizing:border-box;<?php echo $pos['box']; // phpcs:ignore ?>;background:<?php echo $bg; ?>;color:<?php echo $text; ?>;border:<?php echo $bw; ?>px solid <?php echo $bcol; ?>;border-radius:<?php echo $rad; ?>px;box-shadow:<?php echo $shadow; ?>;padding:22px 24px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;line-height:1.5;font-size:<?php echo $fs; ?>px;}
<?php echo $p; ?>.vxck *{box-sizing:border-box;}
<?php echo $p; ?>.vxck-main{flex:1 1 360px;min-width:0;}
<?php echo $p; ?>.vxck-logo{max-height:34px;width:auto;margin-bottom:12px;display:block;}
<?php echo $p; ?>.vxck-h{margin:0 0 8px;font-size:<?php echo $fs + 3; ?>px;font-weight:700;letter-spacing:-.01em;color:<?php echo $text; ?>;}
<?php echo $p; ?>.vxck-body{margin:0 0 14px;font-size:<?php echo $fs; ?>px;opacity:.85;}
<?php echo $p; ?>.vxck-links{display:flex;flex-wrap:wrap;gap:14px;margin:0 0 16px;font-size:<?php echo $fs - 1.5; ?>px;}
<?php echo $p; ?>.vxck-links a{color:<?php echo $accent; ?>;text-decoration:none;}
<?php echo $p; ?>.vxck-links a:hover{text-decoration:underline;}
<?php echo $p; ?>.vxck-actions{display:flex;flex-wrap:wrap;gap:10px;<?php echo 'bar-bottom' === $layout ? 'flex:0 0 auto;align-items:center;' : ''; ?>}
<?php echo $p; ?>.vxck-btn{appearance:none;border:0;cursor:pointer;font-size:<?php echo $fs; ?>px;font-weight:600;padding:11px 18px;border-radius:<?php echo $btnrad; ?>px;transition:transform .08s,filter .15s;}
<?php echo $p; ?>.vxck-btn:active{transform:translateY(1px);}
<?php echo $p; ?>.vxck-accept{background:<?php echo $accent; ?>;color:<?php echo $accentTx; ?>;}
<?php echo $p; ?>.vxck-accept:hover{filter:brightness(1.05);}
<?php echo $p; ?>.vxck-btn2{background:<?php echo $b2bg; ?>;color:<?php echo $b2tx; ?>;}
<?php echo $p; ?>.vxck-btn2:hover{filter:brightness(.97);}
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
		return ob_get_clean();
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
		<button type="button" class="vxck-btn vxck-accept"<?php echo $id( 'vxck-accept' ); // phpcs:ignore ?>><?php echo esc_html( $o['cookie_btn_accept'] ); ?></button>
		<button type="button" class="vxck-btn vxck-btn2"<?php echo $id( 'vxck-reject' ); // phpcs:ignore ?>><?php echo esc_html( $o['cookie_btn_reject'] ); ?></button>
		<?php if ( $show_analytics || $show_marketing ) : ?>
		<button type="button" class="vxck-btn vxck-btn2"<?php echo $id( 'vxck-prefs-toggle' ); // phpcs:ignore ?>><?php echo esc_html( $o['cookie_btn_settings'] ); ?></button>
		<button type="button" class="vxck-btn vxck-accept"<?php echo $id( 'vxck-save' ); // phpcs:ignore ?> style="display:none;">Save choices</button>
		<?php endif; ?>
	</div>

	<?php if ( ! empty( $o['cookie_small_text'] ) ) : ?>
		<p class="vxck-small"><?php echo esc_html( $o['cookie_small_text'] ); ?></p>
	<?php endif; ?>
</div>
		<?php
		return ob_get_clean();
	}

	public static function footer() {
		$decided = ( null !== self::granted() );
		$o       = self::options();
		$css     = self::style_block( $o, '' );
		$markup  = self::markup( $o, false );
		?>
<style><?php echo $css; // phpcs:ignore ?></style>
<div class="vxck-root" id="vxck-root" data-decided="<?php echo $decided ? '1' : '0'; ?>" data-reconsent="<?php echo (int) $o['cookie_reconsent_days']; ?>" hidden>
<?php echo $markup; // phpcs:ignore WordPress.Security.EscapeOutput -- built with esc_* helpers ?>
</div>
<script>
(function(){
	var root=document.getElementById('vxck-root'); if(!root) return;
	var NAME='<?php echo esc_js( self::COOKIE ); ?>';
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
	function close(){root.setAttribute('hidden','');}
	function open(){root.removeAttribute('hidden');}
	function decide(granted){setCookie(granted.length?granted.join(','):'deny');update(granted);close();}

	var accept=document.getElementById('vxck-accept'),
		reject=document.getElementById('vxck-reject'),
		pToggle=document.getElementById('vxck-prefs-toggle'),
		save=document.getElementById('vxck-save'),
		prefs=document.getElementById('vxck-prefs'),
		ca=document.getElementById('vxck-analytics'),
		cm=document.getElementById('vxck-marketing');

	if(accept) accept.addEventListener('click',function(){decide(['analytics','marketing']);});
	if(reject) reject.addEventListener('click',function(){decide([]);});
	if(pToggle) pToggle.addEventListener('click',function(){prefs.classList.toggle('is-open');if(save) save.style.display=prefs.classList.contains('is-open')?'':'none';});
	if(save) save.addEventListener('click',function(){var g=[];if(ca&&ca.checked)g.push('analytics');if(cm&&cm.checked)g.push('marketing');decide(g);});

	document.addEventListener('click',function(e){
		var t=e.target.closest('[data-cookie-settings],a[href="#cookie-settings"]');
		if(t){e.preventDefault();open();if(prefs){prefs.classList.add('is-open');if(save)save.style.display='';}}
	});

	if(root.getAttribute('data-decided')==='0'){open();}
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
