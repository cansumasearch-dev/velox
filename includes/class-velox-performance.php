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
		// Page builders' editors (Oxygen, Bricks, Elementor…) never get front-end rewrites.
		$builder = Velox::is_builder_request();

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
		if ( $this->on( 'perf_minify_css' ) && ! $builder ) {
			add_filter( 'style_loader_src', array( $this, 'minified_css_src' ), 12, 2 );
		}
		add_action( 'velox_purge_all', array( __CLASS__, 'clear_minified' ) );
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
		// Never rewrite script loading inside a page builder (Oxygen's editor runs
		// its own app in the canvas iframe and breaks if its scripts are deferred).
		if ( $this->on( 'perf_defer_scripts' ) && ! $builder ) {
			if ( self::core_strategy_supported() ) {
				// WP 6.3+: let core apply defer through its loading-strategy API, which
				// respects dependencies and inline "after" scripts (a deferred script
				// whose inline init code runs before it loads is what breaks sliders,
				// popups, etc.).
				add_action( 'wp_print_scripts', array( $this, 'apply_defer_strategy' ), 1 );
				add_action( 'wp_print_footer_scripts', array( $this, 'apply_defer_strategy' ), 1 );
			} else {
				add_filter( 'script_loader_tag', array( $this, 'defer_scripts' ), 10, 2 );
			}
		}
		if ( $this->on( 'perf_delay_js' ) && ! $builder ) {
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
		// Oxygen (and any theme/builder that prints its own <img>/<iframe> markup)
		// bypasses the WordPress image + the_content filters above, so on most
		// Oxygen pages those switches did nothing. Apply the same optimizations to
		// the finished page HTML so they reach every element.
		if ( ! $builder && $this->page_pass_wanted() ) {
			add_action( 'template_redirect', array( $this, 'start_page_pass' ), 1 );
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
		if ( $this->on( 'perf_system_fonts' ) ) {
			add_action( 'wp_head', array( $this, 'system_fonts_css' ), 4 );
		}

		// ---- CDN ----
		if ( $this->on( 'perf_cdn_enable' ) && ! empty( $this->s['perf_cdn_url'] ) ) {
			add_filter( 'style_loader_src', array( $this, 'cdn_url' ), 20 );
			add_filter( 'script_loader_src', array( $this, 'cdn_url' ), 20 );
			add_filter( 'wp_get_attachment_url', array( $this, 'cdn_url' ), 20 );
			add_filter( 'wp_calculate_image_srcset', array( $this, 'cdn_srcset' ), 20 );
			add_filter( 'the_content', array( $this, 'cdn_content' ), 25 );
		}

		// ---- Preload / Network ----
		add_action( 'wp_head', array( $this, 'resource_hints' ), 2 );
		if ( ! empty( $this->s['perf_preload_assets'] ) ) {
			add_action( 'wp_head', array( $this, 'preload_assets' ), 2 );
		}
		if ( in_array( $this->s['perf_speculative_loading'], array( 'conservative', 'moderate' ), true ) && ! $builder ) {
			if ( function_exists( 'wp_get_speculation_rules_configuration' ) ) {
				// WP 6.8+ ships speculative loading in core (with the right exclusions
				// for wp-admin, login/logout, nonce URLs…). Upgrade its config to
				// prerender instead of printing a second, competing ruleset.
				add_filter( 'wp_speculation_rules_configuration', array( $this, 'core_speculation_config' ) );
			} else {
				add_action( 'wp_footer', array( $this, 'speculation_rules' ), 99 );
			}
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

	/* ---------------------------------------------------------------- Fonts / CDN */

	/** Force the system font stack so no web fonts load. */
	public function system_fonts_css() {
		$stack = "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Oxygen,Ubuntu,Cantarell,'Helvetica Neue',Arial,'Noto Sans',sans-serif,'Apple Color Emoji','Segoe UI Emoji'";
		echo "\n<style id=\"velox-system-fonts\">body,button,input,select,textarea,h1,h2,h3,h4,h5,h6,p,a,span,div,li{font-family:" . $stack . " !important}</style>\n";
	}

	private function cdn_base() {
		return untrailingslashit( esc_url_raw( (string) $this->s['perf_cdn_url'] ) );
	}

	private function cdn_excluded( $url ) {
		$lines = array_filter( array_map( 'trim', explode( "\n", (string) $this->s['perf_cdn_exclude'] ) ) );
		foreach ( $lines as $frag ) {
			if ( '' !== $frag && false !== strpos( $url, $frag ) ) {
				return true;
			}
		}
		return false;
	}

	/** Rewrite a single origin asset URL to the CDN host. */
	public function cdn_url( $url ) {
		$cdn  = $this->cdn_base();
		$home = untrailingslashit( home_url() );
		if ( '' === $cdn || 0 !== strpos( (string) $url, $home ) || $this->cdn_excluded( $url ) ) {
			return $url;
		}
		return $cdn . substr( $url, strlen( $home ) );
	}

	public function cdn_srcset( $sources ) {
		if ( is_array( $sources ) ) {
			foreach ( $sources as $k => $src ) {
				if ( isset( $src['url'] ) ) {
					$sources[ $k ]['url'] = $this->cdn_url( $src['url'] );
				}
			}
		}
		return $sources;
	}

	/** Rewrite static asset URLs inside post content (handles srcset lists). */
	public function cdn_content( $html ) {
		$cdn  = $this->cdn_base();
		$home = untrailingslashit( home_url() );
		if ( '' === $cdn ) {
			return $html;
		}
		$self = $this;
		return preg_replace_callback(
			'#(src|href|srcset)=([\'"])([^\'"]+)\2#i',
			function ( $m ) use ( $cdn, $home, $self ) {
				$out = array();
				foreach ( preg_split( '/\s*,\s*/', $m[3] ) as $part ) {
					$seg  = preg_split( '/\s+/', trim( $part ), 2 );
					$u    = $seg[0];
					$rest = isset( $seg[1] ) ? ' ' . $seg[1] : '';
					if ( 0 === strpos( $u, $home )
						&& preg_match( '/\.(css|js|png|jpe?g|gif|webp|avif|svg|woff2?|ttf|otf|ico|mp4|webm)(\?|$)/i', $u )
						&& ! $self->cdn_is_excluded( $u ) ) {
						$u = $cdn . substr( $u, strlen( $home ) );
					}
					$out[] = $u . $rest;
				}
				return $m[1] . '=' . $m[2] . implode( ', ', $out ) . $m[2];
			},
			$html
		);
	}

	/** Public wrapper so the content closure can reach the exclusion check. */
	public function cdn_is_excluded( $url ) {
		return $this->cdn_excluded( $url );
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

	/** WordPress 6.3+ can defer scripts itself while honouring their dependencies. */
	private static function core_strategy_supported() {
		global $wp_version;
		return isset( $wp_version ) && version_compare( $wp_version, '6.3', '>=' );
	}

	/** True when a handle/tag matches the user's exclusion list for $key. */
	private function excluded( $key, $handle, $haystack ) {
		foreach ( $this->lines( $key ) as $ex ) {
			if ( false !== stripos( $handle, $ex ) || false !== stripos( $haystack, $ex ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Mark every queued front-end script (and its dependencies) as "defer" via the
	 * core loading-strategy API. Core then only actually defers a script when that's
	 * safe — e.g. it keeps a script blocking if something that runs immediately
	 * depends on it — so this can't break execution order the way string-rewriting
	 * the <script> tag can.
	 */
	public function apply_defer_strategy() {
		if ( is_admin() || Velox_PageMeta::disabled( 'js' ) ) {
			return;
		}
		$scripts = wp_scripts();
		foreach ( self::queued_with_deps( $scripts ) as $handle ) {
			$dep = $scripts->registered[ $handle ];
			if ( empty( $dep->src ) || $scripts->get_data( $handle, 'strategy' ) ) {
				continue; // inline-only alias, or the author already chose a strategy
			}
			if ( $this->excluded( 'perf_defer_exclude', $handle, (string) $dep->src ) ) {
				continue;
			}
			$scripts->add_data( $handle, 'strategy', 'defer' );
		}
	}

	/** Every queued script handle plus its (recursive) dependencies. */
	private static function queued_with_deps( $scripts ) {
		$out   = array();
		$stack = (array) $scripts->queue;
		while ( $stack ) {
			$h = array_pop( $stack );
			if ( isset( $out[ $h ] ) || ! isset( $scripts->registered[ $h ] ) ) {
				continue;
			}
			$out[ $h ] = true;
			foreach ( (array) $scripts->registered[ $h ]->deps as $d ) {
				$stack[] = $d;
			}
		}
		return array_keys( $out );
	}

	/**
	 * Handles that must never be delayed: themes and builders (Oxygen included)
	 * print inline jQuery(...) calls straight into the page, which throw
	 * "jQuery is not defined" if jQuery itself waits for user interaction.
	 */
	private static $never_delay = array( 'jquery', 'jquery-core', 'jquery-migrate' );

	public function delay_scripts( $tag, $handle, $src ) {
		if ( is_admin() || Velox_PageMeta::disabled( 'js' ) || empty( $src ) || false === strpos( $tag, 'src=' ) ) {
			return $tag;
		}
		if ( in_array( $handle, self::$never_delay, true ) || $this->excluded( 'perf_delay_js_exclude', $handle, $tag ) ) {
			return $tag;
		}
		// Delay every <script> in this tag — the external file AND any inline
		// before/after code WordPress bundled with it (translations, config, init
		// calls). That inline code depends on the file, so it has to wait too; the
		// loader replays them strictly in document order.
		return preg_replace_callback(
			'#<script\b([^>]*)>#i',
			function ( $m ) {
				$attrs = $m[1];
				// Leave JSON / template / other non-JS data blocks alone.
				if ( preg_match( '#\btype=("|\')(?!text/javascript|application/javascript)[^"\']*\1#i', $attrs ) ) {
					return $m[0];
				}
				$attrs = preg_replace( '#\stype=("|\')[^"\']*\1#i', '', $attrs );
				if ( preg_match( '#\bsrc=("|\')([^"\']*)\1#i', $attrs, $src ) ) {
					$attrs = str_replace( $src[0], 'data-velox-src="' . esc_url( html_entity_decode( $src[2] ) ) . '"', $attrs );
				}
				return '<script type="velox/lazy"' . $attrs . '>';
			},
			$tag
		);
	}

	public function delay_js_loader() {
		$timeout = max( 0, (int) $this->s['perf_delay_js_timeout'] ) * 1000;
		// Replays delayed scripts one at a time, in document order: an external file
		// is fully loaded before the next script (often its inline init code) runs.
		// Dynamically inserted scripts are async by default, so a plain "swap them
		// all in" loop runs them in random order and breaks dependencies.
		?>
<script id="velox-delay-js">
(function(){var started=false;function load(){if(started)return;started=true;
var list=Array.prototype.slice.call(document.querySelectorAll('script[type="velox/lazy"]'));
function next(){var o=list.shift();if(!o){try{document.dispatchEvent(new Event('velox:delayed-loaded'));}catch(e){}return;}
var n=document.createElement('script');for(var i=0;i<o.attributes.length;i++){var a=o.attributes[i];if(a.name!=='type'&&a.name!=='data-velox-src')n.setAttribute(a.name,a.value);}
var src=o.getAttribute('data-velox-src');
if(src){n.async=false;n.onload=n.onerror=next;n.src=src;o.parentNode.replaceChild(n,o);}
else{n.text=o.text;o.parentNode.replaceChild(n,o);next();}}
next();}
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
		// Responsive-embed wrappers (Oxygen's video element, WP embed blocks) size
		// themselves with padding and position the iframe absolutely — the facade
		// has to fill that box the same way or the video area doubles in height.
		?>
<style id="velox-yt-css">.velox-yt{position:relative;width:100%;max-width:100%;aspect-ratio:16/9;background-size:cover;background-position:center;border-radius:10px;cursor:pointer;overflow:hidden}.oxy-video-container>.velox-yt,.wp-block-embed__wrapper>.velox-yt,.fluid-width-video-wrapper>.velox-yt{position:absolute;inset:0;height:100%;aspect-ratio:auto;border-radius:0}.velox-yt-btn{position:absolute;inset:0;margin:auto;width:68px;height:48px;border:0;border-radius:12px;background:rgba(0,0,0,.65);cursor:pointer}.velox-yt-btn::before{content:"";position:absolute;top:50%;left:50%;transform:translate(-40%,-50%);border-style:solid;border-width:11px 0 11px 19px;border-color:transparent transparent transparent #fff}.velox-yt:hover .velox-yt-btn{background:#f00}</style>
<script id="velox-yt-js">document.addEventListener('click',function(e){var f=e.target.closest('.velox-yt');if(!f)return;var i=document.createElement('iframe');i.setAttribute('allow','accelerometer;autoplay;clipboard-write;encrypted-media;gyroscope;picture-in-picture');i.setAttribute('allowfullscreen','');i.style.cssText='width:100%;height:100%;border:0;position:absolute;inset:0';i.src='https://www.youtube-nocookie.com/embed/'+f.dataset.id+'?autoplay=1';f.innerHTML='';f.appendChild(i);});</script>
		<?php
	}

	/* ---------------------------------------------------------------- Page pass */

	/** True when at least one option needs the full-page HTML pass. */
	private function page_pass_wanted() {
		return $this->on( 'perf_lazyload_images' ) || $this->on( 'perf_lazyload_bg' ) || $this->on( 'perf_add_image_dimensions' )
			|| $this->on( 'perf_fetchpriority_lcp' ) || $this->on( 'perf_lazyload_iframes' )
			|| $this->on( 'perf_youtube_facade' )
			|| ( $this->on( 'perf_cdn_enable' ) && ! empty( $this->s['perf_cdn_url'] ) );
	}

	/** Buffer normal front-end page views so optimize_page() sees the final HTML. */
	public function start_page_pass() {
		if ( is_admin() || is_feed() || is_embed() || is_robots() || is_trackback() ) {
			return;
		}
		if ( ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return;
		}
		if ( Velox::is_builder_request() || Velox_PageMeta::disabled( 'all' ) ) {
			return;
		}
		ob_start( array( $this, 'optimize_page' ) );
	}

	/**
	 * Apply the image / iframe / YouTube / CDN options to a whole HTML page.
	 * Fails OPEN: any problem returns the original HTML untouched.
	 *
	 * @param string $html Full page HTML.
	 * @return string
	 */
	public function optimize_page( $html ) {
		if ( ! is_string( $html ) || '' === $html || false === stripos( $html, '<html' ) ) {
			return $html; // not a full HTML document (JSON, XML, a fragment…)
		}
		try {
			$out = $this->optimize_markup( $html );
			return ( is_string( $out ) && '' !== $out ) ? $out : $html;
		} catch ( \Throwable $e ) {
			return $html;
		}
	}

	/** @internal Public for tests. Does the actual rewriting; may return null on regex failure. */
	public function optimize_markup( $html ) {
		// Park blocks we must never touch (inline JS/JSON, CSS, <noscript> lazy
		// fallbacks, textareas, comments) so the tag regexes can't reach into them.
		$parked = array();
		$html   = preg_replace_callback(
			'#<(script|style|noscript|textarea|template)\b[^>]*>.*?</\1\s*>|<!--.*?-->#is',
			function ( $m ) use ( &$parked ) {
				$parked[] = $m[0];
				return "\x1AVX" . ( count( $parked ) - 1 ) . "\x1A";
			},
			$html
		);
		if ( null === $html ) {
			return null;
		}

		$lazy_off = Velox_PageMeta::disabled( 'lazy' );

		// 1) YouTube facade first, so the iframes it replaces aren't touched below.
		if ( $this->on( 'perf_youtube_facade' ) ) {
			$html = $this->youtube_facade( $html );
		}

		// 2) Iframes.
		if ( $this->on( 'perf_lazyload_iframes' ) && ! $lazy_off ) {
			$html = preg_replace( '/<iframe\b(?![^>]*\bloading=)/i', '<iframe loading="lazy"', $html );
		}

		// 3) Images: dimensions, hero priority, lazy-loading — in document order.
		$html = $this->optimize_images( $html, $lazy_off );

		// 3b) CSS background images (Oxygen section backgrounds live in stylesheets).
		if ( $this->on( 'perf_lazyload_bg' ) && ! $lazy_off && is_string( $html ) ) {
			$html = $this->lazy_backgrounds( $html, $parked );
		}

		// 4) CDN for everything the asset filters didn't see (Oxygen images, inline links).
		if ( $this->on( 'perf_cdn_enable' ) && ! empty( $this->s['perf_cdn_url'] ) && is_string( $html ) ) {
			$html = $this->cdn_content( $html );
		}

		if ( ! is_string( $html ) ) {
			return null;
		}
		return preg_replace_callback(
			"#\x1AVX(\d+)\x1A#",
			function ( $m ) use ( $parked ) {
				return $parked[ (int) $m[1] ];
			},
			$html
		);
	}

	/** Width/height, fetchpriority and loading="lazy" for every <img> on the page. */
	private function optimize_images( $html, $lazy_off ) {
		if ( ! is_string( $html ) ) {
			return $html;
		}
		$do_dims  = $this->on( 'perf_add_image_dimensions' );
		$do_lazy  = $this->on( 'perf_lazyload_images' ) && ! $lazy_off;
		$do_lcp   = $this->on( 'perf_fetchpriority_lcp' );
		$eager    = max( 0, (int) $this->s['perf_lazy_skip_count'] );
		// Someone (WP's featured-image filter, the theme) already chose a hero?
		$lcp_done = (bool) preg_match( '/<img\b[^>]*\bfetchpriority=["\']?high/i', $html );
		$preload  = (string) $this->s['perf_preload_lcp'];
		$index    = 0;
		$dims_added = false;
		$self     = $this;

		$html = preg_replace_callback(
			'/<img\b[^>]*>/i',
			function ( $m ) use ( &$index, &$lcp_done, &$dims_added, $do_dims, $do_lazy, $do_lcp, $eager, $preload, $self ) {
				$tag = $m[0];
				$pos = $index++;
				$src = $self->attr( $tag, 'src' );
				// Placeholders / JS lazy-loaders / explicit opt-outs: leave alone.
				if ( '' === $src || 0 === stripos( $src, 'data:' ) || preg_match( '/\bdata-(?:lazy-)?src=|\bclass=["\'][^"\']*\b(?:skip-lazy|no-lazy|velox-skip)\b/i', $tag ) ) {
					return $tag;
				}

				$w = $self->attr( $tag, 'width' );
				$h = $self->attr( $tag, 'height' );
				if ( $do_dims && '' === $w && '' === $h ) {
					$size = $self->image_size( $src );
					if ( $size ) {
						list( $w, $h ) = $size;
						$tag        = preg_replace( '/^<img\b/i', '<img width="' . (int) $w . '" height="' . (int) $h . '" data-velox-dim', $tag );
						$dims_added = true;
					}
				}

				// Hero image: the first reasonably large image among the eager ones
				// (skips a small header logo), or the URL set as "Preload LCP image".
				$is_hero = false;
				if ( $do_lcp && ! $lcp_done ) {
					if ( '' !== $preload && false !== strpos( $preload, (string) wp_parse_url( $src, PHP_URL_PATH ) ) ) {
						$is_hero = true;
					} elseif ( $pos < max( 1, $eager ) && ( '' === $w || (int) $w >= 300 ) && ! preg_match( '/\.svg(\?|$)/i', $src ) ) {
						$is_hero = true;
					}
				}
				if ( $is_hero ) {
					$lcp_done = true;
					$tag      = preg_replace( '/\sloading=(["\']?)lazy\1/i', '', $tag );
					if ( ! preg_match( '/\bfetchpriority=/i', $tag ) ) {
						$tag = preg_replace( '/^<img\b/i', '<img fetchpriority="high"', $tag );
					}
					return $tag;
				}

				if ( $do_lazy && $pos >= $eager && ! preg_match( '/\bloading=/i', $tag ) ) {
					$tag = preg_replace( '/^<img\b/i', '<img loading="lazy"', $tag );
					if ( ! preg_match( '/\bdecoding=/i', $tag ) ) {
						$tag = preg_replace( '/^<img\b/i', '<img decoding="async"', $tag );
					}
				}
				return $tag;
			},
			$html
		);

		// Width/height attributes only reserve space if the image also scales its
		// height; zero-specificity rule so any height the site's own CSS sets wins.
		if ( $dims_added && is_string( $html ) ) {
			$css  = '<style id="velox-img-dims">:where(img[data-velox-dim]){height:auto}</style>';
			$html = preg_replace( '#</head>#i', $css . '</head>', $html, 1 );
		}
		return $html;
	}

	/** @internal Read one attribute's value from a single HTML tag ('' if absent). */
	public function attr( $tag, $name ) {
		if ( preg_match( '/\s' . preg_quote( $name, '/' ) . '\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', $tag, $m ) ) {
			// PCRE drops trailing unmatched groups, so take the last one present.
			return html_entity_decode( (string) end( $m ), ENT_QUOTES );
		}
		return '';
	}

	/**
	 * Intrinsic size of a local image: from WordPress's "-800x600" size suffix when
	 * present, else by reading the file. Remote images are skipped. Cached per
	 * request, and file reads are capped so a gallery page can't stall rendering.
	 *
	 * @internal Public for tests.
	 * @return int[]|null [width, height]
	 */
	public function image_size( $src ) {
		static $cache = array(), $reads = 0;
		$path = (string) wp_parse_url( $src, PHP_URL_PATH );
		if ( '' === $path || array_key_exists( $path, $cache ) ) {
			return '' === $path ? null : $cache[ $path ];
		}
		$cache[ $path ] = null;
		if ( preg_match( '/\.svg$/i', $path ) ) {
			return null;
		}
		if ( preg_match( '/-(\d{1,5})x(\d{1,5})\.(?:jpe?g|png|gif|webp|avif)$/i', $path, $m ) ) {
			return $cache[ $path ] = array( (int) $m[1], (int) $m[2] );
		}
		$file = $reads < 40 ? $this->local_file( $src ) : null;
		if ( ! $file ) {
			return null;
		}
		$reads++;
		$info = @getimagesize( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		if ( ! $info || empty( $info[0] ) || empty( $info[1] ) ) {
			return null;
		}
		return $cache[ $path ] = array( (int) $info[0], (int) $info[1] );
	}

	/**
	 * Map one of this site's URLs onto the file on disk. Remote URLs, traversal
	 * attempts and missing files give null.
	 *
	 * @internal Public for tests.
	 */
	public function local_file( $url ) {
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		if ( '' === $path ) {
			return null;
		}
		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( $host && strtolower( $host ) !== strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) ) {
			return null;
		}
		$up      = wp_upload_dir();
		$up_path = (string) wp_parse_url( $up['baseurl'], PHP_URL_PATH );
		if ( '' !== $up_path && 0 === strpos( $path, $up_path . '/' ) ) {
			$file = $up['basedir'] . substr( rawurldecode( $path ), strlen( $up_path ) );
		} else {
			$home_path = rtrim( (string) wp_parse_url( home_url(), PHP_URL_PATH ), '/' );
			$rel       = ( '' !== $home_path && 0 === strpos( $path, $home_path . '/' ) ) ? substr( $path, strlen( $home_path ) ) : $path;
			$file      = rtrim( ABSPATH, '/\\' ) . rawurldecode( $rel );
		}
		if ( false !== strpos( $file, '..' ) || ! is_file( $file ) ) {
			return null;
		}
		return $file;
	}

	/* ---------------------------------------------------------- Minify CSS */

	const MIN_DIR = 'velox-min';

	/**
	 * Swap a local, unminified stylesheet for a minified copy in uploads/velox-min/.
	 * The copy is named after the source file's path + mtime + size, so it's rebuilt
	 * automatically when the file changes (e.g. Oxygen regenerating its CSS).
	 * Fails open: anything unexpected keeps the original URL.
	 */
	public function minified_css_src( $src, $handle = '' ) {
		if ( ! $src || is_admin() || preg_match( '/[.\-]min\.css(\?|$)/i', $src ) ) {
			return $src;
		}
		$file = $this->local_file( $src );
		if ( ! $file || ! preg_match( '/\.css$/i', $file ) ) {
			return $src;
		}
		$size = (int) @filesize( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		if ( $size <= 0 || $size > 3 * MB_IN_BYTES ) {
			return $src;
		}
		$up   = wp_upload_dir();
		$name = md5( $file . '|' . (int) @filemtime( $file ) . '|' . $size ) . '.css'; // phpcs:ignore
		$dir  = trailingslashit( $up['basedir'] ) . self::MIN_DIR;
		$dest = $dir . '/' . $name;
		if ( ! is_file( $dest ) ) {
			$css = (string) @file_get_contents( $file ); // phpcs:ignore
			$min = self::minify_css( $css, (string) preg_replace( '/[?#].*$/', '', $src ) );
			if ( null === $min || '' === trim( $min ) ) {
				return $src;
			}
			if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
				return $src;
			}
			// Write to a temp name first so a visitor never gets a half-written file.
			$tmp = $dest . '.' . uniqid( '', true ) . '.tmp';
			if ( false === @file_put_contents( $tmp, $min ) || ! @rename( $tmp, $dest ) ) { // phpcs:ignore
				@unlink( $tmp ); // phpcs:ignore
				return $src;
			}
		}
		return trailingslashit( $up['baseurl'] ) . self::MIN_DIR . '/' . $name;
	}

	/** Remove all minified copies (runs on every full cache purge). */
	public static function clear_minified() {
		$up  = wp_upload_dir();
		$dir = trailingslashit( $up['basedir'] ) . self::MIN_DIR;
		foreach ( (array) glob( $dir . '/*.css' ) as $f ) {
			@unlink( $f ); // phpcs:ignore
		}
	}

	/**
	 * Conservative CSS minifier. Strings, comments and url() are tokenised first so
	 * nothing inside them is touched; outside them it only drops comments (keeps
	 * /*! licence blocks), collapses whitespace, and removes it around { } ; , >
	 * and after ":" — never around + - (calc) and never before ":" (descendant
	 * pseudo-selectors). Relative url()/@import paths are made absolute against
	 * the original file's URL, because the copy lives in a different folder.
	 *
	 * @param string $css  Stylesheet source.
	 * @param string $base Original stylesheet URL (relative paths resolve against it).
	 * @return string|null null on regex failure.
	 */
	public static function minify_css( $css, $base ) {
		$parts = preg_split(
			// Strings use the "unrolled loop" form: no per-character alternation, so a
			// 40 KB base64 font inside quotes can't exhaust PCRE's stack.
			'#("[^"\\\\]*(?:\\\\.[^"\\\\]*)*"|\'[^\'\\\\]*(?:\\\\.[^\'\\\\]*)*\'|/\*.*?\*/|url\(\s*[^)"\'\s][^)"\']*\))#is',
			$css,
			-1,
			PREG_SPLIT_DELIM_CAPTURE
		);
		if ( ! is_array( $parts ) ) {
			return null;
		}
		$out = '';
		foreach ( $parts as $i => $part ) {
			if ( 0 === $i % 2 ) { // plain CSS between tokens
				$p = preg_replace( '/\s+/', ' ', $part );
				$p = preg_replace( '/\s*([{};,>])\s*/', '$1', $p );
				$p = preg_replace( '/:\s+/', ':', $p );
				if ( '' !== $out && false !== strpos( '{};,>', substr( $out, -1 ) ) ) {
					$p = ltrim( (string) $p ); // e.g. the gap a removed comment leaves after "}"
				}
				$out .= str_replace( ';}', '}', (string) $p );
				continue;
			}
			if ( 0 === strpos( $part, '/*' ) ) {
				if ( 0 === strpos( $part, '/*!' ) ) {
					$out .= $part;
				}
				continue;
			}
			if ( 0 === stripos( $part, 'url(' ) ) { // unquoted url(...)
				$out .= 'url(' . self::absolute_css_url( trim( substr( $part, 4, -1 ) ), $base ) . ')';
				continue;
			}
			// Quoted string: a path only when it's the argument of url( or @import.
			if ( preg_match( '/(?:url\(\s*|@import\s*)$/i', $out ) ) {
				$q     = $part[0];
				$part  = $q . self::absolute_css_url( substr( $part, 1, -1 ), $base ) . $q;
			}
			$out .= $part;
		}
		return trim( $out );
	}

	/** Resolve a stylesheet-relative path against the stylesheet's URL. */
	private static function absolute_css_url( $url, $base ) {
		if ( '' === $url || preg_match( '#^(?:[a-z][a-z0-9+.\-]*:|//|/|\#|%23)#i', $url ) ) {
			return $url; // absolute, root-relative, data:, fragment (SVG filters)…
		}
		$b = wp_parse_url( $base );
		if ( empty( $b['host'] ) ) {
			return $url;
		}
		$suffix = '';
		if ( preg_match( '/^([^?#]*)([?#].*)$/', $url, $m ) ) {
			$url    = $m[1];
			$suffix = $m[2];
		}
		$dir  = isset( $b['path'] ) ? preg_replace( '#/[^/]*$#', '/', $b['path'] ) : '/';
		$segs = array();
		foreach ( explode( '/', $dir . $url ) as $seg ) {
			if ( '..' === $seg ) {
				array_pop( $segs );
			} elseif ( '.' !== $seg && '' !== $seg ) {
				$segs[] = $seg;
			}
		}
		$path = '/' . implode( '/', $segs ) . ( '/' === substr( $url, -1 ) ? '/' : '' );
		$port = isset( $b['port'] ) ? ':' . $b['port'] : '';
		return ( isset( $b['scheme'] ) ? $b['scheme'] . ':' : '' ) . '//' . $b['host'] . $port . $path . $suffix;
	}

	/* ---------------------------------------------------------- Lazy backgrounds */

	/**
	 * Lazy-load CSS background images. Oxygen writes every section/div background
	 * into its generated stylesheets, so the browser downloads all of them up
	 * front. Here we find those rules (in the page's local stylesheets and inline
	 * <style> blocks), keep only the ones whose element is on THIS page and sits
	 * below the first section, and hold their background back with one override
	 * rule until a small IntersectionObserver sees the element near the viewport.
	 *
	 * Safe by construction: the override only applies once an inline script has
	 * put a class on <html>, so without JavaScript every background loads as
	 * normal; and the observer reveals anything already on screen immediately.
	 */
	private function lazy_backgrounds( $html, array $parked ) {
		foreach ( $parked as $block ) {
			if ( false !== strpos( $block, 'velox-lazy-bg' ) ) {
				return $html; // already processed (e.g. a second pass)
			}
		}
		$body = stripos( $html, '<body' );
		if ( false === $body ) {
			return $html;
		}

		// 1) Selectors with a background image, from inline <style> + local stylesheets.
		$selectors = array();
		foreach ( $parked as $block ) {
			if ( 0 === stripos( $block, '<style' ) ) {
				$selectors = array_merge( $selectors, self::bg_selectors( preg_replace( '#^<style\b[^>]*>|</style\s*>$#i', '', $block ) ) );
			}
		}
		if ( preg_match_all( '#<link\b[^>]*>#i', substr( $html, 0, $body ), $links ) ) {
			$budget = 12; // stylesheets per page we're willing to read
			foreach ( $links[0] as $link ) {
				if ( ! preg_match( '/\brel=["\']?stylesheet/i', $link ) || $budget <= 0 ) {
					continue;
				}
				$file = $this->local_file( $this->attr( $link, 'href' ) );
				if ( $file && preg_match( '/\.css$/i', $file ) ) {
					$budget--;
					$selectors = array_merge( $selectors, self::bg_selectors_for_file( $file ) );
				}
			}
		}
		if ( ! $selectors ) {
			return $html;
		}

		// 2) Where does each id / class first appear in the body?
		$ids = array();
		$classes = array();
		if ( preg_match_all( '/\s(id|class)\s*=\s*(["\'])(.*?)\2/is', $html, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE, $body ) ) {
			foreach ( $m as $x ) {
				$is_id = 'id' === strtolower( $x[1][0] );
				foreach ( preg_split( '/\s+/', trim( $x[3][0] ) ) as $tok ) {
					if ( '' === $tok ) {
						continue;
					}
					if ( $is_id && ! isset( $ids[ $tok ] ) ) {
						$ids[ $tok ] = $x[0][1];
					} elseif ( ! $is_id && ! isset( $classes[ $tok ] ) ) {
						$classes[ $tok ] = $x[0][1];
					}
				}
			}
		}

		// 3) The fold: everything before the second <section> (header + hero) stays
		// eager. Pages without sections: the first fifth of the body.
		$fold = $body + (int) ( ( strlen( $html ) - $body ) * 0.2 );
		if ( preg_match_all( '/<section\b/i', $html, $sm, PREG_OFFSET_CAPTURE, $body ) && count( $sm[0] ) >= 2 ) {
			$fold = $sm[0][1][1];
		}

		$lazy = array();
		foreach ( array_unique( $selectors ) as $sel ) {
			$pos = self::selector_position( $sel, $ids, $classes );
			if ( null !== $pos && $pos >= $fold ) {
				$lazy[] = $sel;
			}
		}
		if ( ! $lazy ) {
			return $html;
		}

		$hide    = array();
		$observe = array();
		foreach ( $lazy as $sel ) {
			$hide[]                                 = 'html.vx-lzbg ' . self::until_loaded( $sel );
			$observe[ self::strip_pseudo( $sel ) ] = true;
		}
		$head = '<style id="velox-lazy-bg">' . implode( ',', $hide ) . '{background-image:none!important}</style>'
			. '<script id="velox-lazy-bg-flag">document.documentElement.classList.add("vx-lzbg")</script>';
		$foot = '<script id="velox-lazy-bg-js">(function(){var q=' . wp_json_encode( array_keys( $observe ), JSON_UNESCAPED_SLASHES ) . ',els=[];'
			. 'q.forEach(function(s){try{els=els.concat([].slice.call(document.querySelectorAll(s)));}catch(e){}});'
			. 'function on(e){e.classList.add("vx-bg");}'
			. 'if(!("IntersectionObserver" in window)){els.forEach(on);return;}'
			. 'var io=new IntersectionObserver(function(es){es.forEach(function(x){if(x.isIntersecting){on(x.target);io.unobserve(x.target);}});},{rootMargin:"300px"});'
			. 'els.forEach(function(e){if(e.closest(".skip-lazy")){on(e);}else{io.observe(e);}});})();</script>';

		$html = preg_replace( '#</head>#i', $head . '</head>', $html, 1 );
		$end  = strripos( $html, '</body>' );
		return false === $end ? $html . $foot : substr_replace( $html, $foot, $end, 0 );
	}

	/** Selectors in a stylesheet whose rule sets a background image (url(), not data:). */
	private static function bg_selectors( $css ) {
		$out = array();
		$css = preg_replace( '#/\*.*?\*/#s', '', (string) $css );
		if ( ! is_string( $css ) || false === stripos( $css, 'url(' ) ) {
			return $out;
		}
		if ( ! preg_match_all( '/([^{}]+)\{([^{}]*)\}/', $css, $m, PREG_SET_ORDER ) ) {
			return $out;
		}
		foreach ( $m as $x ) {
			if ( ! preg_match( '/background(?:-image)?\s*:[^;}]*url\(\s*["\']?(?!data:)[^)"\'\s]/i', $x[2] ) ) {
				continue;
			}
			$sel = trim( $x[1] );
			if ( '' === $sel || '@' === $sel[0] || preg_match( '/^(?:from|to|[\d.]+%)\b/i', $sel ) || preg_match( '/\([^)]*,/', $sel ) ) {
				continue; // at-rules, keyframe steps, or :is(a,b)-style lists we won't split
			}
			foreach ( explode( ',', $sel ) as $one ) {
				$one = trim( $one );
				if ( '' !== $one && ! preg_match( '/^(?:html|body|:root)\b/i', $one ) && strlen( $one ) < 300 ) {
					$out[] = $one;
				}
			}
		}
		return $out;
	}

	/** bg_selectors() for a file on disk, cached until the file changes. */
	private static function bg_selectors_for_file( $file ) {
		$size = (int) @filesize( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		if ( $size <= 0 || $size > 2 * MB_IN_BYTES ) {
			return array();
		}
		$key    = 'velox_bgsel_' . md5( $file . '|' . (int) @filemtime( $file ) . '|' . $size ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		$cached = get_transient( $key );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$sel = self::bg_selectors( (string) @file_get_contents( $file ) ); // phpcs:ignore
		set_transient( $key, $sel, WEEK_IN_SECONDS );
		return $sel;
	}

	/**
	 * Earliest point in the HTML where the element a selector targets can appear.
	 * Every #id / .class in the selector — the element's own and its ancestors' or
	 * preceding siblings' — has to occur at or before the element, so the latest
	 * first-occurrence among them is a safe lower bound. null = not on this page,
	 * or a selector we can't place (tag-only, attribute-only…) — left alone.
	 */
	private static function selector_position( $sel, array $ids, array $classes ) {
		$parts = preg_split( '/\s*[\s>+~]\s*/', trim( self::strip_pseudo( $sel ) ) );
		$last  = (string) end( $parts );
		// The targeted element itself must be identifiable by id/class.
		if ( ! preg_match( '/[#.][A-Za-z0-9_-]/', $last ) ) {
			return null;
		}
		if ( ! preg_match_all( '/([#.])([A-Za-z0-9_-]+)/', implode( ' ', $parts ), $t, PREG_SET_ORDER ) ) {
			return null;
		}
		$pos = -1;
		foreach ( $t as $tok ) {
			$map = '#' === $tok[1] ? $ids : $classes;
			if ( ! isset( $map[ $tok[2] ] ) ) {
				return null;
			}
			$pos = max( $pos, $map[ $tok[2] ] );
		}
		return $pos;
	}

	/** Drop pseudo-classes/elements so the selector matches the element itself. */
	private static function strip_pseudo( $sel ) {
		$s = preg_replace( '/::?[a-zA-Z-]+(?:\([^)]*\))?/', '', $sel );
		$s = trim( (string) $s );
		return '' === $s ? '*' : $s;
	}

	/** $sel, but only while its element hasn't been revealed (keeps ::before etc. last). */
	private static function until_loaded( $sel ) {
		if ( preg_match( '/(::?(?:before|after|first-line|first-letter|marker|backdrop|placeholder))\s*$/i', $sel, $m ) ) {
			return substr( $sel, 0, -strlen( $m[0] ) ) . ':not(.vx-bg)' . $m[1];
		}
		return $sel . ':not(.vx-bg)';
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

	/** WP 6.8+: switch core's speculative loading to prerender at our eagerness. */
	public function core_speculation_config( $config ) {
		if ( ! is_array( $config ) ) {
			return $config; // core turned it off (logged-in user, plain permalinks…)
		}
		$config['mode']      = 'prerender';
		$config['eagerness'] = ( 'moderate' === $this->s['perf_speculative_loading'] ) ? 'moderate' : 'conservative';
		return $config;
	}

	/** Pre-6.8 fallback: our own ruleset, with the same exclusions core uses. */
	public function speculation_rules() {
		// Logged-in users hover admin/edit/logout links — never prerender for them.
		if ( is_user_logged_in() ) {
			return;
		}
		$eagerness = ( 'moderate' === $this->s['perf_speculative_loading'] ) ? 'moderate' : 'conservative';
		$prefix    = '/' . trim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
		$prefix    = '/' === $prefix ? '' : $prefix;
		$skip      = array(
			$prefix . '/wp-admin/*',
			$prefix . '/wp-login.php*',
			$prefix . '/wp-content/*',
			$prefix . '/wp-includes/*',
			$prefix . '/*\\?(.+)', // anything with a query string (nonces, add-to-cart, logout…)
		);
		$rules = array(
			'prerender' => array(
				array(
					'source'    => 'document',
					'where'     => array(
						'and' => array(
							array( 'href_matches' => $prefix . '/*' ),
							array( 'not' => array( 'href_matches' => $skip ) ),
							array( 'not' => array( 'selector_matches' => 'a[rel~="nofollow"], .no-prerender, .no-prerender a' ) ),
						),
					),
					'eagerness' => $eagerness,
				),
			),
		);
		echo '<script type="speculationrules">' . wp_json_encode( $rules, JSON_UNESCAPED_SLASHES ) . "</script>\n";
	}
}
