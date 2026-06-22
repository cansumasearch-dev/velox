<?php
/**
 * Velox CSS engine — the "strong" CSS optimizations.
 *
 *  - Optimize CSS delivery: load stylesheets non-render-blocking + inline critical CSS.
 *  - Remove Unused CSS (local, best-effort): trim rules whose selectors never appear
 *    in the server-rendered HTML. This is NOT a cloud renderer, so it can't see styles
 *    that JavaScript adds later — that's what the safelist is for. Conservative by
 *    design: when in doubt it keeps the rule. Cached per-URL and baked into the page
 *    cache, so the heavy work runs once per page.
 *
 * @package Velox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Velox_CSS {

	const DIRNAME = 'velox-css';
	const LEARN_PREFIX = 'velox_csslearn_';
	const LEARN_THRESHOLD = 5;   // build after this many visits
	const LEARN_REBUILD = 25;    // rebuild every N further visits to absorb new classes

	private $s;

	public function __construct() {
		$this->s = Velox_Settings::all();
		$engine  = isset( $this->s['perf_rucss_engine'] ) ? $this->s['perf_rucss_engine'] : 'local';

		// The visitor collector endpoint must work in the (is_admin) admin-ajax context.
		if ( $this->on( 'perf_remove_unused_css' ) && 'auto' === $engine ) {
			add_action( 'wp_ajax_nopriv_velox_collect_css', array( $this, 'collect' ) );
			add_action( 'wp_ajax_velox_collect_css', array( $this, 'collect' ) );
		}

		if ( is_admin() ) {
			return; // settings are loaded for the AJAX scanner; no front-end hooks in admin
		}

		// Inline critical CSS as early as possible.
		if ( $this->on( 'perf_optimize_css_delivery' ) && trim( (string) $this->s['perf_critical_css'] ) !== '' ) {
			add_action( 'wp_head', array( $this, 'print_critical_css' ), 2 );
		}
		// Async (non-render-blocking) stylesheet loading for enqueued styles.
		if ( $this->on( 'perf_optimize_css_delivery' ) ) {
			add_filter( 'style_loader_tag', array( $this, 'async_style_tag' ), 20, 4 );
		}
		// Used-CSS: serve cached trimmed CSS (and, for the local engine, build on the fly).
		if ( $this->on( 'perf_remove_unused_css' ) && $this->is_eligible_request() ) {
			add_action( 'template_redirect', array( $this, 'start_buffer' ), 1 );
			// Auto-learn: drop a tiny collector that reports the real post-JS classes.
			if ( 'auto' === $engine ) {
				add_action( 'wp_footer', array( $this, 'print_collector' ), 99 );
			}
		}
	}

	private function on( $key ) {
		return ! empty( $this->s[ $key ] );
	}

	private function lines( $key ) {
		$raw = isset( $this->s[ $key ] ) ? (string) $this->s[ $key ] : '';
		return array_filter( array_map( 'trim', preg_split( '/[\r\n]+/', $raw ) ) );
	}

	/** Only touch normal, logged-out, front-end HTML page views. */
	private function is_eligible_request() {
		if ( is_admin() || is_feed() || is_embed() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return false;
		}
		if ( isset( $_GET['ct_builder'] ) || isset( $_GET['oxygen_iframe'] ) || isset( $_GET['elementor-preview'] ) ) {
			return false; // never run inside a page builder
		}
		if ( is_user_logged_in() ) {
			return false; // logged-in users may see admin-only classes
		}
		return true;
	}

	/* ---------------------------------------------------------------- critical + async */

	public function print_critical_css() {
		echo '<style id="velox-critical-css">' . wp_strip_all_tags( (string) $this->s['perf_critical_css'] ) . "</style>\n"; // phpcs:ignore
	}

	public function async_style_tag( $tag, $handle, $href, $media ) {
		foreach ( $this->lines( 'perf_css_async_exclude' ) as $frag ) {
			if ( false !== stripos( $handle, $frag ) || false !== stripos( $href, $frag ) ) {
				return $tag; // keep render-blocking
			}
		}
		if ( false === stripos( $tag, "rel='stylesheet'" ) && false === stripos( $tag, 'rel="stylesheet"' ) ) {
			return $tag;
		}
		// Load async: print media + flip to all on load, with a noscript fallback.
		$async = str_replace( array( "media='all'", 'media="all"' ), array( "media='print' onload=\"this.media='all'\"", 'media="print" onload="this.media=\'all\'"' ), $tag );
		if ( $async === $tag ) {
			$async = str_replace( ' />', " media='print' onload=\"this.media='all'\" />", $tag );
		}
		$async .= '<noscript>' . $tag . '</noscript>';
		return $async;
	}

	/* ---------------------------------------------------------------- used-CSS (local RUCSS) */

	public static function dir() {
		$up = wp_upload_dir();
		return array(
			'path' => trailingslashit( $up['basedir'] ) . self::DIRNAME,
			'url'  => trailingslashit( $up['baseurl'] ) . self::DIRNAME,
		);
	}

	public function start_buffer() {
		ob_start( array( $this, 'process_html' ) );
	}

	/**
	 * The buffer callback. Fails OPEN: on any problem it returns the original HTML
	 * untouched, so a parsing edge case can never blank-page the site.
	 */
	/** Normalized cache key for a path so scan + serve always agree. */
	public static function cache_key( $path ) {
		$p = wp_parse_url( $path, PHP_URL_PATH );
		if ( ! $p ) {
			$p = '/';
		}
		return md5( '/' . trim( $p, '/' ) );
	}

	/** Local, same-domain, readable stylesheet <link> tags (minus exclusions). */
	private function local_links( $html ) {
		$out = array();
		if ( ! preg_match_all( '#<link\b[^>]*rel=["\']stylesheet["\'][^>]*>#i', $html, $links ) ) {
			return $out;
		}
		$skip = $this->lines( 'perf_rucss_exclude' );
		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		foreach ( $links[0] as $tag ) {
			if ( ! preg_match( '#href=["\']([^"\']+)["\']#i', $tag, $hm ) ) {
				continue;
			}
			$href = html_entity_decode( $hm[1] );
			$bad  = false;
			foreach ( $skip as $frag ) {
				if ( $frag && false !== stripos( $href, $frag ) ) {
					$bad = true;
					break;
				}
			}
			if ( $bad ) {
				continue;
			}
			$path = $this->url_to_path( $href, $host );
			if ( ! $path || ! is_readable( $path ) || filesize( $path ) > 2000000 ) {
				continue;
			}
			$out[] = array( 'tag' => $tag, 'path' => $path );
		}
		return $out;
	}

	/** Trim each stylesheet against the used-token set and write the combined file. */
	private function write_used_css( $paths, $used, $cache_file ) {
		$safel    = $this->lines( 'perf_rucss_safelist' );
		$combined = '';
		foreach ( $paths as $p ) {
			if ( is_readable( $p ) ) {
				$combined .= $this->trim_css( (string) file_get_contents( $p ), $used, $safel ) . "\n";
			}
		}
		if ( trim( $combined ) === '' ) {
			return false;
		}
		return (bool) file_put_contents( $cache_file, $combined ); // phpcs:ignore
	}

	/** Build the trimmed used-CSS for a page's HTML and write it to $cache_file. */
	public function generate_css_file( $html, $cache_file ) {
		$links = $this->local_links( $html );
		if ( empty( $links ) ) {
			return false;
		}
		$paths = array();
		foreach ( $links as $l ) {
			$paths[] = $l['path'];
		}
		return $this->write_used_css( $paths, $this->collect_tokens( $html ), $cache_file );
	}

	/** Build trimmed CSS from a token set collected by visitors' browsers. */
	private function generate_from_tokens( $paths, $classes, $ids, $cache_file ) {
		$used = array( 'classes' => $classes, 'ids' => $ids, 'tags' => array() );
		return $this->write_used_css( $paths, $used, $cache_file );
	}

	/** Replace the page's local stylesheet links with one link to the used-CSS file. */
	private function swap_links( $html, $used_url ) {
		$links = $this->local_links( $html );
		if ( empty( $links ) ) {
			return $html;
		}
		if ( $this->on( 'perf_optimize_css_delivery' ) ) {
			$rep = '<link rel="stylesheet" href="' . esc_url( $used_url ) . '" media="print" onload="this.media=\'all\'"><noscript><link rel="stylesheet" href="' . esc_url( $used_url ) . '"></noscript>';
		} else {
			$rep = '<link rel="stylesheet" href="' . esc_url( $used_url ) . '">';
		}
		$first = array_shift( $links );
		$html  = str_replace( $first['tag'], $rep, $html );
		foreach ( $links as $l ) {
			$html = str_replace( $l['tag'], '', $html );
		}
		return $html;
	}

	/**
	 * Front-end: serve a cached used-CSS file if we have one. The local engine also
	 * builds it on the fly from the server HTML; the Cloudflare engine only serves
	 * what a scan produced. Fails OPEN — any problem returns the page untouched.
	 */
	public function process_html( $html ) {
		if ( ! is_string( $html ) || strlen( $html ) < 200 || stripos( $html, '<html' ) === false || stripos( $html, '</html>' ) === false ) {
			return $html;
		}
		if ( Velox_PageMeta::disabled( 'css' ) ) {
			return $html;
		}
		try {
			$dir = self::dir();
			if ( ! wp_mkdir_p( $dir['path'] ) ) {
				return $html;
			}
			$path       = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
			$cache_file = trailingslashit( $dir['path'] ) . self::cache_key( $path ) . '.css';

			if ( ! file_exists( $cache_file ) ) {
				$engine = isset( $this->s['perf_rucss_engine'] ) ? $this->s['perf_rucss_engine'] : 'local';
				if ( 'local' !== $engine ) {
					return $html; // auto/cloudflare wait for learning or a scan
				}
				if ( ! $this->generate_css_file( $html, $cache_file ) ) {
					return $html;
				}
			}
			$used_url = trailingslashit( $dir['url'] ) . self::cache_key( $path ) . '.css?v=' . ( @filemtime( $cache_file ) ?: time() );
			return $this->swap_links( $html, $used_url );
		} catch ( \Throwable $e ) {
			return $html;
		}
	}

	/* ---------------------------------------------------------------- scan / Cloudflare */

	/** Build (or rebuild) the used-CSS for one path. Used by the scanner. */
	public function build_for_path( $path ) {
		$dir = self::dir();
		if ( ! wp_mkdir_p( $dir['path'] ) ) {
			return new WP_Error( 'mkdir', __( 'Could not create the used-CSS folder.', 'velox' ) );
		}
		$url    = home_url( '/' . ltrim( $path, '/' ) );
		$engine = isset( $this->s['perf_rucss_engine'] ) ? $this->s['perf_rucss_engine'] : 'local';
		if ( 'cloudflare' === $engine ) {
			$html = $this->render_via_cloudflare( $url );
		} else {
			$resp = wp_remote_get( $url, array( 'timeout' => 30, 'user-agent' => 'Velox CSS scan' ) );
			$html = is_wp_error( $resp ) ? $resp : wp_remote_retrieve_body( $resp );
		}
		if ( is_wp_error( $html ) ) {
			return $html;
		}
		if ( ! $html || stripos( $html, '<html' ) === false ) {
			return new WP_Error( 'no_html', __( 'No HTML came back for that path.', 'velox' ) );
		}
		$cache_file = trailingslashit( $dir['path'] ) . self::cache_key( $path ) . '.css';
		@unlink( $cache_file ); // phpcs:ignore — always rebuild fresh
		if ( ! $this->generate_css_file( $html, $cache_file ) ) {
			return new WP_Error( 'no_css', __( 'No local stylesheets to trim on that page.', 'velox' ) );
		}
		return array(
			'message' => sprintf( __( 'Built %1$s (%2$s)', 'velox' ), $path, size_format( filesize( $cache_file ) ) ),
			'path'    => $path,
			'bytes'   => (int) filesize( $cache_file ),
		);
	}

	/** Render a URL through Cloudflare Browser Run and return the post-JS HTML. */
	private function render_via_cloudflare( $url ) {
		$account = trim( (string) Velox_Settings::get( 'cf_account_id' ) );
		$token   = trim( (string) Velox_Settings::get( 'cf_api_token' ) );
		if ( ! $account || ! $token ) {
			return new WP_Error( 'cf_creds', __( 'Add your Cloudflare account ID and API token first.', 'velox' ) );
		}
		$endpoint = 'https://api.cloudflare.com/client/v4/accounts/' . rawurlencode( $account ) . '/browser-rendering/content';
		$resp = wp_remote_post( $endpoint, array(
			'timeout' => 60,
			'headers' => array(
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json',
			),
			'body' => wp_json_encode( array(
				'url'         => $url,
				'gotoOptions' => array( 'waitUntil' => 'networkidle0', 'timeout' => 30000 ),
			) ),
		) );
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}
		$code = wp_remote_retrieve_response_code( $resp );
		if ( 429 === $code ) {
			return new WP_Error( 'cf_limit', __( 'Cloudflare daily browser limit reached (10 min/day on the free plan). Try again tomorrow or upgrade to Workers Paid.', 'velox' ) );
		}
		$body = json_decode( wp_remote_retrieve_body( $resp ), true );
		if ( empty( $body['success'] ) ) {
			$msg = ! empty( $body['errors'][0]['message'] ) ? $body['errors'][0]['message'] : ( 'HTTP ' . $code );
			return new WP_Error( 'cf_fail', sprintf( __( 'Cloudflare render failed: %s', 'velox' ), $msg ) );
		}
		return isset( $body['result'] ) ? (string) $body['result'] : '';
	}

	/** All class names, ids and tag names present in the HTML. */
	private function collect_tokens( $html ) {
		$classes = array();
		if ( preg_match_all( '/class\s*=\s*["\']([^"\']+)["\']/i', $html, $cm ) ) {
			foreach ( $cm[1] as $list ) {
				foreach ( preg_split( '/\s+/', $list ) as $c ) {
					if ( $c !== '' ) {
						$classes[ $c ] = true;
					}
				}
			}
		}
		$ids = array();
		if ( preg_match_all( '/id\s*=\s*["\']([^"\']+)["\']/i', $html, $im ) ) {
			foreach ( $im[1] as $id ) {
				$ids[ $id ] = true;
			}
		}
		$tags = array();
		if ( preg_match_all( '/<([a-z][a-z0-9-]*)/i', $html, $tm ) ) {
			foreach ( $tm[1] as $t ) {
				$tags[ strtolower( $t ) ] = true;
			}
		}
		return array( 'classes' => $classes, 'ids' => $ids, 'tags' => $tags );
	}

	private function url_to_path( $href, $home_host ) {
		if ( strpos( $href, '//' ) === 0 ) {
			$href = 'https:' . $href;
		}
		$host = wp_parse_url( $href, PHP_URL_HOST );
		if ( $host && $home_host && strcasecmp( $host, $home_host ) !== 0 ) {
			return false; // different domain
		}
		$path = wp_parse_url( $href, PHP_URL_PATH );
		if ( ! $path ) {
			return false;
		}
		$path = strtok( $path, '?' );
		return untrailingslashit( ABSPATH ) . $path;
	}

	/**
	 * Trim a stylesheet. Conservative: keeps every at-rule (@media, @font-face,
	 * @keyframes, @supports…) wholesale, and keeps any style rule whose selector
	 * has no class/id (structural) or whose classes/ids are all present/safelisted.
	 */
	private function trim_css( $css, $used, $safelist ) {
		$css = preg_replace( '#/\*.*?\*/#s', '', $css ); // strip comments
		$out = '';
		$len = strlen( $css );
		$i = 0;
		$buf = '';
		$depth = 0;
		while ( $i < $len ) {
			$ch = $css[ $i ];
			$buf .= $ch;
			if ( $ch === '{' ) {
				$depth++;
			} elseif ( $ch === '}' ) {
				$depth--;
				if ( $depth === 0 ) {
					$out .= $this->keep_block( $buf, $used, $safelist );
					$buf = '';
				}
			}
			$i++;
		}
		return $out;
	}

	private function keep_block( $block, $used, $safelist ) {
		$brace = strpos( $block, '{' );
		if ( $brace === false ) {
			return '';
		}
		$selector = trim( substr( $block, 0, $brace ) );
		// Keep all at-rules untouched (responsive, fonts, keyframes, etc.).
		if ( $selector === '' || $selector[0] === '@' ) {
			return $block;
		}
		$parts = explode( ',', $selector );
		foreach ( $parts as $sel ) {
			if ( $this->selector_used( $sel, $used, $safelist ) ) {
				return $block; // at least one selector is used → keep whole rule
			}
		}
		return '';
	}

	private function selector_used( $sel, $used, $safelist ) {
		$sel = trim( $sel );
		if ( $sel === '' ) {
			return true;
		}
		foreach ( $safelist as $token ) {
			if ( $token !== '' && false !== stripos( $sel, $token ) ) {
				return true;
			}
		}
		// Attribute selectors are hard to verify — keep to be safe.
		if ( strpos( $sel, '[' ) !== false ) {
			return true;
		}
		preg_match_all( '/\.([A-Za-z0-9_-]+)/', $sel, $cm );
		preg_match_all( '/#([A-Za-z0-9_-]+)/', $sel, $im );
		$classes = $cm[1];
		$ids = $im[1];
		if ( empty( $classes ) && empty( $ids ) ) {
			return true; // pure tag / pseudo / universal selector
		}
		foreach ( $classes as $c ) {
			if ( empty( $used['classes'][ $c ] ) ) {
				return false;
			}
		}
		foreach ( $ids as $id ) {
			if ( empty( $used['ids'][ $id ] ) ) {
				return false;
			}
		}
		return true;
	}

	/* ---------------------------------------------------------------- auto-learn */

	/** Tiny front-end collector: report the real post-JS classes/ids, once per visit. */
	public function print_collector() {
		if ( ! $this->is_eligible_request() ) {
			return;
		}
		$ajax = esc_url( admin_url( 'admin-ajax.php' ) );
		?>
<script id="velox-css-learn">
(function(){
try{
if(sessionStorage.getItem('vxCssLearned'))return;
}catch(e){}
var seen={};
function add(root){
 if(!root||!root.querySelectorAll)return;
 var els=root.querySelectorAll('[class],[id]');
 for(var i=0;i<els.length;i++){var el=els[i];
  if(el.classList){for(var j=0;j<el.classList.length;j++)seen['.'+el.classList[j]]=1;}
  if(el.id)seen['#'+el.id]=1;}
}
add(document);
var mo;
try{mo=new MutationObserver(function(m){for(var i=0;i<m.length;i++){var x=m[i];
 if(x.target&&x.target.classList){for(var j=0;j<x.target.classList.length;j++)seen['.'+x.target.classList[j]]=1;}
 if(x.addedNodes)for(var k=0;k<x.addedNodes.length;k++){if(x.addedNodes[k].nodeType===1)add(x.addedNodes[k]);}}});
mo.observe(document.documentElement,{subtree:true,childList:true,attributes:true,attributeFilter:['class','id']});}catch(e){}
var sent=false;
function send(){
 if(sent)return;sent=true;
 try{sessionStorage.setItem('vxCssLearned','1');}catch(e){}
 try{if(mo)mo.disconnect();}catch(e){}
 var sheets=[],ls=document.querySelectorAll('link[rel="stylesheet"]');
 for(var i=0;i<ls.length;i++){if(ls[i].href)sheets.push(ls[i].href);}
 var fd=new FormData();
 fd.append('action','velox_collect_css');
 fd.append('path',location.pathname);
 fd.append('tokens',JSON.stringify(Object.keys(seen)));
 fd.append('sheets',JSON.stringify(sheets));
 try{if(navigator.sendBeacon){navigator.sendBeacon('<?php echo $ajax; ?>',fd);return;}}catch(e){}
 try{fetch('<?php echo $ajax; ?>',{method:'POST',body:fd,keepalive:true,credentials:'same-origin'});}catch(e){}
}
window.addEventListener('load',function(){setTimeout(send,5000);});
document.addEventListener('visibilitychange',function(){if(document.visibilityState==='hidden')send();});
window.addEventListener('pagehide',send);
})();
</script>
		<?php
	}

	/** Receive a visitor's token report, merge it, and (re)build when ready. */
	public function collect() {
		$path   = isset( $_POST['path'] ) ? wp_unslash( $_POST['path'] ) : '';
		$tokens = isset( $_POST['tokens'] ) ? json_decode( wp_unslash( $_POST['tokens'] ), true ) : array();
		$sheets = isset( $_POST['sheets'] ) ? json_decode( wp_unslash( $_POST['sheets'] ), true ) : array();
		if ( ! $path || ! is_array( $tokens ) ) {
			wp_die( '', '', array( 'response' => 204 ) );
		}

		$key    = self::cache_key( $path );
		$option = self::LEARN_PREFIX . $key;
		$data   = get_option( $option, array( 'c' => array(), 'i' => array(), 's' => array(), 'v' => 0, 'b' => -1 ) );

		// Merge tokens. Extra tokens only ever make the result KEEP more CSS — never break.
		$cc = 0;
		foreach ( $tokens as $t ) {
			if ( ! is_string( $t ) || strlen( $t ) > 120 ) {
				continue;
			}
			if ( '.' === $t[0] && preg_match( '/^\.[A-Za-z0-9_-]+$/', $t ) ) {
				$data['c'][ substr( $t, 1 ) ] = 1;
			} elseif ( '#' === $t[0] && preg_match( '/^#[A-Za-z0-9_-]+$/', $t ) ) {
				$data['i'][ substr( $t, 1 ) ] = 1;
			}
			if ( ++$cc > 6000 ) {
				break;
			}
		}
		// Resolve + remember the local stylesheet paths from this visit.
		if ( is_array( $sheets ) ) {
			$host = wp_parse_url( home_url(), PHP_URL_HOST );
			foreach ( $sheets as $href ) {
				if ( ! is_string( $href ) ) {
					continue;
				}
				$p = $this->url_to_path( $href, $host );
				if ( $p && is_readable( $p ) && filesize( $p ) < 2000000 ) {
					$data['s'][ $p ] = 1;
				}
			}
		}
		$data['v']++;
		update_option( $option, $data, false ); // non-autoloaded

		// Build at the threshold, then rebuild occasionally to absorb newly-seen classes.
		if ( $data['v'] >= self::LEARN_THRESHOLD && ( $data['b'] < 0 || ( $data['v'] - $data['b'] ) >= self::LEARN_REBUILD ) ) {
			$dir = self::dir();
			if ( wp_mkdir_p( $dir['path'] ) && ! empty( $data['s'] ) ) {
				$cache_file = trailingslashit( $dir['path'] ) . $key . '.css';
				if ( $this->generate_from_tokens( array_keys( $data['s'] ), $data['c'], $data['i'], $cache_file ) ) {
					$data['b'] = $data['v'];
					update_option( $option, $data, false );
				}
			}
		}
		wp_die( '', '', array( 'response' => 204 ) );
	}

	/** How many pages the auto-learner is tracking + how many are built. */
	public static function learn_stats() {
		global $wpdb;
		$rows = $wpdb->get_col( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( self::LEARN_PREFIX ) . '%' ) );
		$pages = 0;
		$built = 0;
		foreach ( $rows as $r ) {
			$d = maybe_unserialize( $r );
			if ( is_array( $d ) ) {
				$pages++;
				if ( isset( $d['b'] ) && $d['b'] >= 0 ) {
					$built++;
				}
			}
		}
		return array( 'pages' => $pages, 'built' => $built );
	}

	public static function reset_learning() {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( self::LEARN_PREFIX ) . '%' ) );
		self::clear_cache();
		return array( 'message' => __( 'Auto-learn data and cached CSS cleared. Learning will restart from your next visitors.', 'velox' ) );
	}

	/* ---------------------------------------------------------------- cache mgmt */

	public static function clear_cache() {
		$dir = self::dir();
		$n = 0;
		if ( is_dir( $dir['path'] ) ) {
			foreach ( glob( trailingslashit( $dir['path'] ) . '*.css' ) as $f ) {
				if ( @unlink( $f ) ) { // phpcs:ignore
					$n++;
				}
			}
		}
		return array( 'message' => sprintf( __( 'Cleared %d cached used-CSS file(s).', 'velox' ), $n ) );
	}
}
