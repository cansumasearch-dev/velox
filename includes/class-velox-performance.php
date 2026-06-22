<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Performance module. Every tweak here ADDS something WP Fastest Cache / Cloudflare
 * / Oxygen don't already do — there is no page cache and no CSS/JS combine (combine
 * breaks Oxygen). Each feature is gated behind its own setting and the module master
 * switch, and everything ships OFF by default unless it is zero-risk.
 */
class Velox_Performance {

	private $s = array();

	public function __construct() {
		if ( ! Velox_Settings::get( 'module_performance' ) ) {
			return;
		}
		$this->s = Velox_Settings::all();

		// ---- General ----
		if ( $this->on( 'perf_disable_emojis' ) ) {
			$this->disable_emojis();
		}
		if ( $this->on( 'perf_disable_embeds' ) ) {
			$this->disable_embeds();
		}
		if ( $this->on( 'perf_remove_query_strings' ) ) {
			add_filter( 'style_loader_src', array( $this, 'strip_ver' ), 15 );
			add_filter( 'script_loader_src', array( $this, 'strip_ver' ), 15 );
		}
		if ( $this->on( 'perf_disable_xmlrpc' ) ) {
			add_filter( 'xmlrpc_enabled', '__return_false' );
			add_filter( 'wp_headers', array( $this, 'remove_pingback_header' ) );
		}
		if ( $this->on( 'perf_disable_self_pingbacks' ) ) {
			add_action( 'pre_ping', array( $this, 'no_self_ping' ) );
		}
		if ( $this->on( 'perf_clean_head' ) ) {
			$this->clean_head();
		}
		if ( $this->on( 'perf_disable_dashicons' ) ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'dequeue_dashicons' ), 100 );
		}
		if ( $this->on( 'perf_remove_jquery_migrate' ) ) {
			add_action( 'wp_default_scripts', array( $this, 'remove_jquery_migrate' ) );
		}
		if ( $this->on( 'perf_disable_comments' ) ) {
			$this->disable_comments();
		}
		if ( $this->on( 'perf_disable_rss' ) ) {
			$this->disable_rss();
		}
		if ( $this->on( 'perf_disable_app_passwords' ) ) {
			add_filter( 'wp_is_application_passwords_available', '__return_false' );
		}
		$this->heartbeat();
		if ( (int) $this->s['perf_revisions_keep'] >= 0 ) {
			add_filter( 'wp_revisions_to_keep', array( $this, 'revisions_to_keep' ), 99 );
		}
		add_filter( 'autosave_interval', array( $this, 'autosave_interval' ), 99 );

		// ---- CSS ----
		if ( $this->on( 'perf_disable_block_css' ) ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'dequeue_block_css' ), 100 );
		}
		if ( $this->on( 'perf_disable_global_styles' ) ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'dequeue_global_styles' ), 100 );
			remove_action( 'wp_enqueue_scripts', 'wp_enqueue_global_styles' );
			remove_action( 'wp_footer', 'wp_enqueue_global_styles', 1 );
		}
		if ( $this->on( 'perf_disable_woo_css' ) ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'dequeue_woo_css' ), 100 );
		}

		// ---- JavaScript ----
		if ( $this->on( 'perf_defer_scripts' ) ) {
			add_filter( 'script_loader_tag', array( $this, 'defer_scripts' ), 10, 2 );
		}
		if ( $this->on( 'perf_delay_js' ) ) {
			add_filter( 'script_loader_tag', array( $this, 'delay_scripts' ), 11, 3 );
			add_action( 'wp_footer', array( $this, 'delay_js_loader' ), 99 );
		}
		if ( $this->on( 'perf_disable_woo_fragments' ) ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'dequeue_woo_fragments' ), 100 );
		}

		// ---- Images (front end) ----
		if ( $this->on( 'perf_add_image_dimensions' ) ) {
			add_filter( 'wp_get_attachment_image_attributes', array( $this, 'ensure_dimensions' ), 10, 3 );
		}
		if ( $this->on( 'perf_fetchpriority_lcp' ) ) {
			add_filter( 'wp_get_attachment_image_attributes', array( $this, 'fetchpriority_lcp' ), 10, 2 );
		}
		if ( $this->on( 'perf_lazyload_iframes' ) ) {
			add_filter( 'wp_lazy_loading_enabled', '__return_true' );
			add_filter( 'the_content', array( $this, 'lazyload_iframes' ), 20 );
		}
		// Keep the first N images eager (above-the-fold) so lazy-load never delays the LCP.
		if ( (int) $this->s['perf_lazy_skip_count'] > 0 ) {
			add_filter( 'wp_omit_loading_attr_threshold', array( $this, 'lazy_skip_threshold' ) );
		}
		if ( $this->on( 'perf_youtube_facade' ) ) {
			add_filter( 'the_content', array( $this, 'youtube_facade' ), 21 );
			add_action( 'wp_footer', array( $this, 'youtube_facade_assets' ), 98 );
		}
		if ( ! empty( $this->s['perf_preload_lcp'] ) ) {
			add_action( 'wp_head', array( $this, 'preload_lcp' ), 1 );
		}
		if ( $this->on( 'perf_content_visibility' ) && ! empty( $this->s['perf_content_visibility_selector'] ) ) {
			add_action( 'wp_head', array( $this, 'content_visibility_css' ), 3 );
		}

		// ---- Fonts ----
		if ( $this->on( 'perf_fonts_preconnect' ) ) {
			add_action( 'wp_head', array( $this, 'fonts_preconnect' ), 1 );
		}
		if ( $this->on( 'perf_fonts_display_swap' ) ) {
			add_filter( 'style_loader_src', array( $this, 'google_font_display_swap' ), 20 );
		}
		if ( ! empty( $this->s['perf_preload_fonts'] ) ) {
			add_action( 'wp_head', array( $this, 'preload_fonts' ), 2 );
		}

		// ---- Preload / Network ----
		add_action( 'wp_head', array( $this, 'resource_hints' ), 2 );
		if ( ! empty( $this->s['perf_preload_assets'] ) ) {
			add_action( 'wp_head', array( $this, 'preload_assets' ), 2 );
		}
		if ( in_array( $this->s['perf_speculative_loading'], array( 'conservative', 'moderate' ), true ) ) {
			add_action( 'wp_footer', array( $this, 'speculation_rules' ), 99 );
		}
	}

	private function on( $key ) {
		return ! empty( $this->s[ $key ] );
	}

	private function lines( $key ) {
		$raw = isset( $this->s[ $key ] ) ? (string) $this->s[ $key ] : '';
		$out = array();
		foreach ( preg_split( '/\r\n|\r|\n/', $raw ) as $line ) {
			$line = trim( $line );
			if ( '' !== $line ) {
				$out[] = $line;
			}
		}
		return $out;
	}

	/* ---------------------------------------------------------------- General */

	private function disable_emojis() {
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		remove_action( 'admin_print_styles', 'print_emoji_styles' );
		remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
		remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
		remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
		add_filter( 'tiny_mce_plugins', function ( $plugins ) {
			return is_array( $plugins ) ? array_diff( $plugins, array( 'wpemoji' ) ) : array();
		} );
		add_filter( 'wp_resource_hints', function ( $hints, $relation ) {
			if ( 'dns-prefetch' === $relation ) {
				$hints = array_filter( $hints, function ( $h ) {
					return false === strpos( (string) $h, 's.w.org' );
				} );
			}
			return $hints;
		}, 10, 2 );
	}

	private function disable_embeds() {
		remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
		remove_action( 'wp_head', 'wp_oembed_add_host_js' );
		add_filter( 'embed_oembed_discover', '__return_false' );
		add_action( 'wp_footer', function () {
			wp_dequeue_script( 'wp-embed' );
		} );
	}

	public function strip_ver( $src ) {
		if ( $src && false !== strpos( $src, 'ver=' ) ) {
			$src = remove_query_arg( 'ver', $src );
		}
		return $src;
	}

	public function remove_pingback_header( $headers ) {
		if ( isset( $headers['X-Pingback'] ) ) {
			unset( $headers['X-Pingback'] );
		}
		return $headers;
	}

	public function no_self_ping( &$links ) {
		$home = home_url();
		foreach ( $links as $i => $link ) {
			if ( 0 === strpos( $link, $home ) ) {
				unset( $links[ $i ] );
			}
		}
	}

	private function clean_head() {
		remove_action( 'wp_head', 'rsd_link' );
		remove_action( 'wp_head', 'wlwmanifest_link' );
		remove_action( 'wp_head', 'wp_generator' );
		remove_action( 'wp_head', 'wp_shortlink_wp_head' );
		remove_action( 'wp_head', 'feed_links_extra', 3 );
		remove_action( 'wp_head', 'rest_output_link_wp_head' );
		remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
		remove_action( 'template_redirect', 'rest_output_link_header', 11 );
		add_filter( 'the_generator', '__return_empty_string' );
	}

	public function dequeue_dashicons() {
		if ( ! is_user_logged_in() && ! is_admin_bar_showing() ) {
			wp_dequeue_style( 'dashicons' );
			wp_deregister_style( 'dashicons' );
		}
	}

	public function remove_jquery_migrate( $scripts ) {
		if ( is_admin() || empty( $scripts->registered['jquery'] ) ) {
			return;
		}
		$deps = $scripts->registered['jquery']->deps;
		$scripts->registered['jquery']->deps = array_diff( $deps, array( 'jquery-migrate' ) );
	}

	private function disable_comments() {
		add_filter( 'comments_open', '__return_false', 20 );
		add_filter( 'pings_open', '__return_false', 20 );
		add_filter( 'comments_array', '__return_empty_array', 20 );
		add_action( 'admin_menu', function () {
			remove_menu_page( 'edit-comments.php' );
		} );
		add_action( 'wp_before_admin_bar_render', function () {
			global $wp_admin_bar;
			if ( $wp_admin_bar ) {
				$wp_admin_bar->remove_node( 'comments' );
			}
		} );
	}

	private function disable_rss() {
		$kill = function () {
			wp_die( esc_html__( 'Feeds are disabled on this site.', 'velox' ) );
		};
		foreach ( array( 'do_feed', 'do_feed_rdf', 'do_feed_rss', 'do_feed_rss2', 'do_feed_atom', 'do_feed_rss2_comments', 'do_feed_atom_comments' ) as $hook ) {
			add_action( $hook, $kill, 1 );
		}
		remove_action( 'wp_head', 'feed_links', 2 );
		remove_action( 'wp_head', 'feed_links_extra', 3 );
	}

	private function heartbeat() {
		$mode = $this->s['perf_heartbeat'];
		if ( 'default' === $mode ) {
			return;
		}
		if ( 'off' === $mode ) {
			add_action( 'init', function () {
				if ( ! ( is_admin() && false !== strpos( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), 'post.php' ) ) ) {
					wp_deregister_script( 'heartbeat' );
				}
			}, 1 );
		}
		add_filter( 'heartbeat_settings', function ( $settings ) use ( $mode ) {
			$settings['interval'] = ( 'slow' === $mode ) ? 60 : 120;
			return $settings;
		} );
	}

	public function revisions_to_keep( $num ) {
		return (int) $this->s['perf_revisions_keep'];
	}

	public function autosave_interval( $interval ) {
		$v = (int) $this->s['perf_autosave_interval'];
		return $v > 0 ? $v : $interval;
	}

	/* ---------------------------------------------------------------- CSS */

	public function dequeue_block_css() {
		if ( is_admin() ) {
			return;
		}
		wp_dequeue_style( 'wp-block-library' );
		wp_dequeue_style( 'wp-block-library-theme' );
		wp_dequeue_style( 'wc-blocks-style' );
	}

	public function dequeue_global_styles() {
		if ( is_admin() ) {
			return;
		}
		wp_dequeue_style( 'global-styles' );
		wp_dequeue_style( 'classic-theme-styles' );
	}

	public function dequeue_woo_css() {
		if ( is_admin() || ! function_exists( 'is_woocommerce' ) ) {
			return;
		}
		if ( is_woocommerce() || is_cart() || is_checkout() || is_account_page() ) {
			return;
		}
		foreach ( array( 'woocommerce-general', 'woocommerce-layout', 'woocommerce-smallscreen', 'wc-blocks-style' ) as $h ) {
			wp_dequeue_style( $h );
		}
	}

	/* ---------------------------------------------------------------- JavaScript */

	public function defer_scripts( $tag, $handle ) {
		if ( is_admin() || Velox_PageMeta::disabled( 'js' ) || false !== strpos( $tag, ' defer' ) || false !== strpos( $tag, ' async' ) ) {
			return $tag;
		}
		foreach ( $this->lines( 'perf_defer_exclude' ) as $ex ) {
			if ( false !== stripos( $handle, $ex ) || false !== stripos( $tag, $ex ) ) {
				return $tag;
			}
		}
		// WP 6.9+ may bundle an inline translation <script> with the external one in a
		// single $tag. Only add defer to the tag that actually has a src — never the inline part.
		return preg_replace_callback(
			'#<script\b([^>]*\bsrc=[^>]*)>#i',
			function ( $m ) {
				return '<script defer' . $m[1] . '>';
			},
			$tag,
			1
		);
	}

	public function delay_scripts( $tag, $handle, $src ) {
		if ( is_admin() || Velox_PageMeta::disabled( 'js' ) || empty( $src ) || false === strpos( $tag, 'src=' ) ) {
			return $tag;
		}
		foreach ( $this->lines( 'perf_delay_js_exclude' ) as $ex ) {
			if ( false !== stripos( $handle, $ex ) || false !== stripos( $tag, $ex ) ) {
				return $tag;
			}
		}
		// Only transform the external (src-bearing) <script>; leave any inline
		// translation script that WP 6.9 bundled into the same tag untouched.
		return preg_replace_callback(
			'#<script\b([^>]*?)\bsrc=("|\')([^"\']*)\2([^>]*)>#i',
			function ( $m ) {
				return '<script type="velox/lazy" data-velox-src="' . esc_url( $m[3] ) . '"' . $m[1] . $m[4] . '>';
			},
			$tag,
			1
		);
	}

	public function delay_js_loader() {
		$timeout = max( 0, (int) $this->s['perf_delay_js_timeout'] ) * 1000;
		?>
<script id="velox-delay-js">
(function(){var loaded=false;function load(){if(loaded)return;loaded=true;
document.querySelectorAll('script[type="velox/lazy"]').forEach(function(o){var n=document.createElement('script');
if(o.dataset.veloxSrc){n.src=o.dataset.veloxSrc;}else{n.textContent=o.textContent;}
for(var i=0;i<o.attributes.length;i++){var a=o.attributes[i];if(['type','data-velox-src'].indexOf(a.name)===-1)n.setAttribute(a.name,a.value);}
o.parentNode.replaceChild(n,o);});}
var evts=['mousemove','mousedown','keydown','touchstart','scroll','wheel'];
function fire(){evts.forEach(function(e){window.removeEventListener(e,fire,{passive:true});});load();}
evts.forEach(function(e){window.addEventListener(e,fire,{passive:true});});
<?php if ( $timeout > 0 ) : ?>setTimeout(load,<?php echo (int) $timeout; ?>);<?php endif; ?>})();
</script>
		<?php
	}

	public function dequeue_woo_fragments() {
		if ( is_admin() || ! function_exists( 'is_woocommerce' ) ) {
			return;
		}
		if ( is_cart() || is_checkout() ) {
			return;
		}
		wp_dequeue_script( 'wc-cart-fragments' );
	}

	/* ---------------------------------------------------------------- Images */

	public function ensure_dimensions( $attr, $attachment, $size ) {
		if ( ( empty( $attr['width'] ) || empty( $attr['height'] ) ) && $attachment ) {
			$meta = wp_get_attachment_metadata( $attachment->ID );
			if ( ! empty( $meta['width'] ) && ! empty( $meta['height'] ) ) {
				$attr['width']  = $meta['width'];
				$attr['height'] = $meta['height'];
			}
		}
		return $attr;
	}

	public function lazy_skip_threshold( $threshold ) {
		if ( Velox_PageMeta::disabled( 'lazy' ) ) {
			return $threshold;
		}
		$n = (int) $this->s['perf_lazy_skip_count'];
		return $n > 0 ? $n : $threshold;
	}

	public function lazyload_iframes( $content ) {
		if ( Velox_PageMeta::disabled( 'lazy' ) ) {
			return $content;
		}
		if ( is_admin() || empty( $content ) ) {
			return $content;
		}
		return preg_replace_callback( '/<iframe\b(?![^>]*\bloading=)([^>]*)>/i', function ( $m ) {
			return '<iframe loading="lazy"' . $m[1] . '>';
		}, $content );
	}

	/**
	 * Give the page's hero image high fetch priority and stop it being lazy-loaded.
	 * Targets the featured image on singular views — the most common LCP element.
	 */
	public function fetchpriority_lcp( $attr, $attachment ) {
		if ( is_admin() || empty( $attachment ) || ! is_singular() ) {
			return $attr;
		}
		static $done = false;
		if ( $done ) {
			return $attr;
		}
		$thumb_id = get_post_thumbnail_id( get_queried_object_id() );
		if ( $thumb_id && (int) $thumb_id === (int) $attachment->ID ) {
			$attr['fetchpriority'] = 'high';
			$attr['loading']       = 'eager';
			$done                  = true;
		}
		return $attr;
	}

	/**
	 * Replace YouTube embeds with a lightweight click-to-load thumbnail (facade).
	 * Saves ~1MB+ of YouTube JS/iframe weight on initial load.
	 */
	public function youtube_facade( $content ) {
		if ( is_admin() || is_feed() || empty( $content ) ) {
			return $content;
		}
		return preg_replace_callback(
			'#<iframe[^>]+src=["\']https?://(?:www\.)?(?:youtube(?:-nocookie)?\.com|youtu\.be)/embed/([A-Za-z0-9_\-]+)[^"\']*["\'][^>]*></iframe>#i',
			function ( $m ) {
				$id = esc_attr( $m[1] );
				return '<div class="velox-yt" data-id="' . $id . '" style="background-image:url(https://i.ytimg.com/vi/' . $id . '/hqdefault.jpg)">'
					. '<button type="button" class="velox-yt-btn" aria-label="Play video"></button></div>';
			},
			$content
		);
	}

	public function youtube_facade_assets() {
		if ( is_admin() ) {
			return;
		}
		?>
<style id="velox-yt-css">.velox-yt{position:relative;width:100%;max-width:100%;aspect-ratio:16/9;background-size:cover;background-position:center;border-radius:10px;cursor:pointer;overflow:hidden}.velox-yt-btn{position:absolute;inset:0;margin:auto;width:68px;height:48px;border:0;border-radius:12px;background:rgba(0,0,0,.65);cursor:pointer}.velox-yt-btn::before{content:"";position:absolute;top:50%;left:50%;transform:translate(-40%,-50%);border-style:solid;border-width:11px 0 11px 19px;border-color:transparent transparent transparent #fff}.velox-yt:hover .velox-yt-btn{background:#f00}</style>
<script id="velox-yt-js">document.addEventListener('click',function(e){var f=e.target.closest('.velox-yt');if(!f)return;var i=document.createElement('iframe');i.setAttribute('allow','accelerometer;autoplay;clipboard-write;encrypted-media;gyroscope;picture-in-picture');i.setAttribute('allowfullscreen','');i.style.cssText='width:100%;height:100%;border:0;position:absolute;inset:0';i.src='https://www.youtube-nocookie.com/embed/'+f.dataset.id+'?autoplay=1';f.innerHTML='';f.appendChild(i);});</script>
		<?php
	}

	/** Inject content-visibility:auto for offscreen sections (risky — needs intrinsic size). */
	public function content_visibility_css() {
		if ( Velox_PageMeta::disabled( 'css' ) ) {
			return;
		}
		$sel = array_filter( array_map( 'trim', preg_split( '/[\r\n,]+/', (string) $this->s['perf_content_visibility_selector'] ) ) );
		if ( empty( $sel ) ) {
			return;
		}
		$sel = implode( ',', array_map( 'esc_html', $sel ) );
		echo '<style id="velox-cv">' . $sel . '{content-visibility:auto;contain-intrinsic-size:auto 600px}</style>' . "\n";
	}

	public function preload_lcp() {
		$url = esc_url( $this->s['perf_preload_lcp'] );
		if ( $url ) {
			echo '<link rel="preload" as="image" fetchpriority="high" href="' . $url . "\">\n";
		}
	}

	/* ---------------------------------------------------------------- Fonts */

	public function fonts_preconnect() {
		echo '<link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>' . "\n";
		echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
	}

	public function google_font_display_swap( $src ) {
		if ( $src && false !== strpos( $src, 'fonts.googleapis.com' ) && false === strpos( $src, 'display=' ) ) {
			$src = add_query_arg( 'display', 'swap', $src );
		}
		return $src;
	}

	public function preload_fonts() {
		foreach ( $this->lines( 'perf_preload_fonts' ) as $url ) {
			$ext  = strtolower( pathinfo( wp_parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
			$type = 'woff2' === $ext ? 'font/woff2' : ( 'woff' === $ext ? 'font/woff' : 'font/' . $ext );
			echo '<link rel="preload" as="font" type="' . esc_attr( $type ) . '" href="' . esc_url( $url ) . '" crossorigin>' . "\n";
		}
	}

	/* ---------------------------------------------------------------- Preload / Network */

	public function resource_hints() {
		foreach ( $this->lines( 'perf_dns_prefetch' ) as $url ) {
			echo '<link rel="dns-prefetch" href="' . esc_url( $url ) . '">' . "\n";
		}
		foreach ( $this->lines( 'perf_preconnect' ) as $url ) {
			echo '<link rel="preconnect" href="' . esc_url( $url ) . '" crossorigin>' . "\n";
		}
	}

	public function preload_assets() {
		foreach ( $this->lines( 'perf_preload_assets' ) as $url ) {
			$ext = strtolower( pathinfo( wp_parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
			$as  = 'css' === $ext ? 'style' : ( 'js' === $ext ? 'script' : ( in_array( $ext, array( 'jpg', 'jpeg', 'png', 'webp', 'gif', 'svg', 'avif' ), true ) ? 'image' : 'fetch' ) );
			echo '<link rel="preload" as="' . esc_attr( $as ) . '" href="' . esc_url( $url ) . '"' . ( 'fetch' === $as ? ' crossorigin' : '' ) . ">\n";
		}
	}

	public function speculation_rules() {
		$eagerness = ( 'moderate' === $this->s['perf_speculative_loading'] ) ? 'moderate' : 'conservative';
		$rules = array(
			'prerender' => array(
				array(
					'source'    => 'document',
					'where'     => array( 'href_matches' => '/*' ),
					'eagerness' => $eagerness,
				),
			),
		);
		echo '<script type="speculationrules">' . wp_json_encode( $rules ) . "</script>\n";
	}
}
