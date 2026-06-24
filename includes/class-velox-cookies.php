<?php
/**
 * Velox — Cookie consent banner.
 *
 * Renders a fully styleable consent banner on the front end and wires it to
 * Google Consent Mode v2. Everything is printed inline so it works on any theme
 * and is never broken by page caching of the plugin's admin assets.
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
		// Consent defaults + (optional) gtag must run as early as possible.
		add_action( 'wp_head', array( __CLASS__, 'head' ), 0 );
		add_action( 'wp_footer', array( __CLASS__, 'footer' ), 20 );
	}

	/** Categories the visitor has already granted (server-side read of the cookie). */
	private static function granted() {
		if ( empty( $_COOKIE[ self::COOKIE ] ) ) {
			return null; // no decision yet
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
		$granted = self::granted();
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

	public static function footer() {
		// A decision already made → no banner, but keep a re-open hook available.
		$decided = ( null !== self::granted() );

		$g = function ( $k, $d = '' ) { return Velox_Settings::get( $k, $d ); };

		$layout   = (string) $g( 'cookie_layout', 'bar-bottom' );
		$bg       = self::hex( $g( 'cookie_bg', '#ffffff' ) );
		$text     = self::hex( $g( 'cookie_text', '#1d1d1f' ) );
		$accent   = self::hex( $g( 'cookie_accent', '#2ab7f1' ) );
		$accentTx = self::hex( $g( 'cookie_accent_text', '#ffffff' ) );
		$b2bg     = self::hex( $g( 'cookie_btn2_bg', '#f1f2f5' ) );
		$b2tx     = self::hex( $g( 'cookie_btn2_text', '#1d1d1f' ) );
		$bcol     = self::hex( $g( 'cookie_border_color', '#e6e7eb' ) );
		$bw       = (int) $g( 'cookie_border_width', 1 );
		$rad      = (int) $g( 'cookie_radius', 16 );
		$shadow   = $g( 'cookie_shadow', true ) ? '0 18px 50px rgba(15,18,30,.22)' : 'none';
		$overlay  = (bool) $g( 'cookie_overlay', false );
		$offset   = (int) $g( 'cookie_offset', 24 );

		$show_analytics = (bool) $g( 'cookie_cat_analytics', true );
		$show_marketing = (bool) $g( 'cookie_cat_marketing', true );

		$pos_css = self::position_css( $layout, $offset );

		// Build the markup.
		ob_start();
		?>
<style>
.vxck-root{position:fixed;z-index:2147483600;<?php echo $pos_css['root']; // phpcs:ignore ?>}
.vxck-overlay{position:fixed;inset:0;background:rgba(8,10,18,.5);z-index:2147483500;backdrop-filter:saturate(120%) blur(1px);}
.vxck{box-sizing:border-box;<?php echo $pos_css['box']; // phpcs:ignore ?>;background:<?php echo $bg; ?>;color:<?php echo $text; ?>;border:<?php echo $bw; ?>px solid <?php echo $bcol; ?>;border-radius:<?php echo $rad; ?>px;box-shadow:<?php echo $shadow; ?>;padding:22px 24px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;line-height:1.5;}
.vxck *{box-sizing:border-box;}
.vxck-logo{max-height:34px;width:auto;margin-bottom:12px;display:block;}
.vxck-h{margin:0 0 8px;font-size:17px;font-weight:700;letter-spacing:-.01em;color:<?php echo $text; ?>;}
.vxck-body{margin:0 0 14px;font-size:14px;opacity:.85;}
.vxck-links{display:flex;flex-wrap:wrap;gap:14px;margin:0 0 16px;font-size:12.5px;}
.vxck-links a{color:<?php echo $accent; ?>;text-decoration:none;}
.vxck-links a:hover{text-decoration:underline;}
.vxck-actions{display:flex;flex-wrap:wrap;gap:10px;}
.vxck-btn{appearance:none;border:0;cursor:pointer;font-size:14px;font-weight:600;padding:11px 18px;border-radius:<?php echo max( 6, (int) round( $rad * 0.6 ) ); ?>px;transition:transform .08s,filter .15s;}
.vxck-btn:active{transform:translateY(1px);}
.vxck-accept{background:<?php echo $accent; ?>;color:<?php echo $accentTx; ?>;}
.vxck-accept:hover{filter:brightness(1.05);}
.vxck-btn2{background:<?php echo $b2bg; ?>;color:<?php echo $b2tx; ?>;}
.vxck-btn2:hover{filter:brightness(.97);}
.vxck-small{margin:14px 0 0;font-size:11.5px;opacity:.6;}
.vxck-prefs{margin:6px 0 16px;display:none;flex-direction:column;gap:10px;}
.vxck-prefs.is-open{display:flex;}
.vxck-cat{display:flex;align-items:flex-start;gap:10px;font-size:13px;padding:10px 12px;border:1px solid <?php echo $bcol; ?>;border-radius:10px;}
.vxck-cat input{margin-top:2px;}
.vxck-cat strong{display:block;font-size:13px;}
.vxck-cat span{display:block;font-size:12px;opacity:.65;}
@media(max-width:560px){.vxck{<?php echo $pos_css['box_mobile']; // phpcs:ignore ?>}.vxck-actions{flex-direction:column;}.vxck-btn{width:100%;}}
</style>
<div class="vxck-root" id="vxck-root" data-decided="<?php echo $decided ? '1' : '0'; ?>" data-reconsent="<?php echo (int) $g( 'cookie_reconsent_days', 180 ); ?>" hidden>
	<?php if ( $overlay && 'modal-center' === $layout ) : ?><div class="vxck-overlay"></div><?php endif; ?>
	<div class="vxck" role="dialog" aria-label="Cookie consent" aria-live="polite">
		<?php if ( $g( 'cookie_logo' ) ) : ?>
			<img class="vxck-logo" src="<?php echo esc_url( $g( 'cookie_logo' ) ); ?>" alt="">
		<?php endif; ?>
		<?php if ( $g( 'cookie_heading' ) ) : ?>
			<p class="vxck-h"><?php echo esc_html( $g( 'cookie_heading' ) ); ?></p>
		<?php endif; ?>
		<p class="vxck-body"><?php echo esc_html( $g( 'cookie_body' ) ); ?></p>

		<div class="vxck-prefs" id="vxck-prefs">
			<label class="vxck-cat">
				<input type="checkbox" checked disabled>
				<span><strong>Necessary</strong><span>Required for the site to work. Always on.</span></span>
			</label>
			<?php if ( $show_analytics ) : ?>
			<label class="vxck-cat">
				<input type="checkbox" id="vxck-analytics">
				<span><strong>Analytics</strong><span>Helps us understand how the site is used.</span></span>
			</label>
			<?php endif; ?>
			<?php if ( $show_marketing ) : ?>
			<label class="vxck-cat">
				<input type="checkbox" id="vxck-marketing">
				<span><strong>Marketing</strong><span>Used to personalise ads and measure campaigns.</span></span>
			</label>
			<?php endif; ?>
		</div>

		<?php if ( $g( 'cookie_link1_label' ) || $g( 'cookie_link2_label' ) ) : ?>
		<div class="vxck-links">
			<?php if ( $g( 'cookie_link1_label' ) ) : ?><a href="<?php echo esc_url( $g( 'cookie_link1_url' ) ); ?>"><?php echo esc_html( $g( 'cookie_link1_label' ) ); ?></a><?php endif; ?>
			<?php if ( $g( 'cookie_link2_label' ) ) : ?><a href="<?php echo esc_url( $g( 'cookie_link2_url' ) ); ?>"><?php echo esc_html( $g( 'cookie_link2_label' ) ); ?></a><?php endif; ?>
		</div>
		<?php endif; ?>

		<div class="vxck-actions">
			<button type="button" class="vxck-btn vxck-accept" id="vxck-accept"><?php echo esc_html( $g( 'cookie_btn_accept', 'Accept all' ) ); ?></button>
			<button type="button" class="vxck-btn vxck-btn2" id="vxck-reject"><?php echo esc_html( $g( 'cookie_btn_reject', 'Reject non-essential' ) ); ?></button>
			<?php if ( $show_analytics || $show_marketing ) : ?>
			<button type="button" class="vxck-btn vxck-btn2" id="vxck-prefs-toggle"><?php echo esc_html( $g( 'cookie_btn_settings', 'Preferences' ) ); ?></button>
			<button type="button" class="vxck-btn vxck-accept" id="vxck-save" style="display:none;">Save choices</button>
			<?php endif; ?>
		</div>

		<?php if ( $g( 'cookie_small_text' ) ) : ?>
			<p class="vxck-small"><?php echo esc_html( $g( 'cookie_small_text' ) ); ?></p>
		<?php endif; ?>
	</div>
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

	// Re-open from anywhere: <a href="#cookie-settings"> or [data-cookie-settings]
	document.addEventListener('click',function(e){
		var t=e.target.closest('[data-cookie-settings],a[href="#cookie-settings"]');
		if(t){e.preventDefault();open();if(prefs){prefs.classList.add('is-open');if(save)save.style.display='';}}
	});

	if(root.getAttribute('data-decided')==='0'){open();}
})();
</script>
		<?php
		echo ob_get_clean(); // phpcs:ignore WordPress.Security.EscapeOutput -- built above with esc_* helpers.
	}

	/* ---- helpers ---- */

	private static function hex( $v ) {
		$v = trim( (string) $v );
		return preg_match( '/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $v ) ? $v : '#000000';
	}

	private static function position_css( $layout, $offset ) {
		$o = (int) $offset;
		switch ( $layout ) {
			case 'box-bl':
				return array(
					'root'        => "left:{$o}px;bottom:{$o}px;",
					'box'         => 'width:380px;max-width:calc(100vw - ' . ( $o * 2 ) . 'px)',
					'box_mobile'  => 'width:100%;',
				);
			case 'box-br':
				return array(
					'root'        => "right:{$o}px;bottom:{$o}px;",
					'box'         => 'width:380px;max-width:calc(100vw - ' . ( $o * 2 ) . 'px)',
					'box_mobile'  => 'width:100%;',
				);
			case 'modal-center':
				return array(
					'root'        => 'inset:0;display:flex;align-items:center;justify-content:center;padding:' . $o . 'px;',
					'box'         => 'position:relative;width:480px;max-width:100%;z-index:2147483600',
					'box_mobile'  => 'width:100%;',
				);
			case 'bar-bottom':
			default:
				return array(
					'root'        => "left:{$o}px;right:{$o}px;bottom:{$o}px;",
					'box'         => 'width:100%;display:flex;flex-wrap:wrap;align-items:center;gap:8px 24px',
					'box_mobile'  => 'display:block;',
				);
		}
	}
}
