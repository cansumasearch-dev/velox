<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Performance tweaks that COMPLEMENT WP Fastest Cache + Cloudflare rather than
 * duplicate them. No page caching here (WPFC owns that), no CSS/JS combine
 * (WPFC owns that, and combining breaks Oxygen). These are the safe, additive
 * wins: head cleanup, fewer requests, controlled heartbeat, optional defer.
 */
class Velox_Performance {

	public function __construct() {
		if ( ! Velox_Settings::get( 'module_performance' ) ) {
			return;
		}

		if ( Velox_Settings::get( 'perf_disable_emojis' ) ) {
			$this->disable_emojis();
		}
		if ( Velox_Settings::get( 'perf_disable_embeds' ) ) {
			add_action( 'init', array( $this, 'disable_embeds' ), 9999 );
		}
		if ( Velox_Settings::get( 'perf_remove_query_strings' ) ) {
			add_filter( 'script_loader_src', array( $this, 'remove_query_strings' ), 15 );
			add_filter( 'style_loader_src', array( $this, 'remove_query_strings' ), 15 );
		}
		if ( Velox_Settings::get( 'perf_disable_xmlrpc' ) ) {
			add_filter( 'xmlrpc_enabled', '__return_false' );
			add_filter( 'wp_headers', array( $this, 'remove_pingback_header' ) );
		}
		if ( Velox_Settings::get( 'perf_clean_head' ) ) {
			$this->clean_head();
		}
		if ( Velox_Settings::get( 'perf_disable_dashicons' ) ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'disable_dashicons' ), 100 );
		}
		if ( Velox_Settings::get( 'perf_disable_jquery_migrate' ) ) {
			add_action( 'wp_default_scripts', array( $this, 'remove_jquery_migrate' ) );
		}
		if ( Velox_Settings::get( 'perf_limit_revisions' ) ) {
			add_filter( 'wp_revisions_to_keep', array( $this, 'limit_revisions' ), 10, 2 );
		}

		$hb = Velox_Settings::get( 'perf_heartbeat', 'default' );
		if ( 'default' !== $hb ) {
			add_filter( 'heartbeat_settings', array( $this, 'heartbeat_settings' ) );
			if ( 'off' === $hb ) {
				add_action( 'init', array( $this, 'maybe_disable_heartbeat' ), 1 );
			}
		}

		$prefetch = trim( (string) Velox_Settings::get( 'perf_dns_prefetch', '' ) );
		if ( '' !== $prefetch ) {
			add_action( 'wp_head', array( $this, 'output_dns_prefetch' ), 0 );
		}

		if ( Velox_Settings::get( 'perf_defer_js' ) ) {
			add_filter( 'script_loader_tag', array( $this, 'defer_scripts' ), 10, 3 );
		}

		if ( Velox_Settings::get( 'perf_lazy_native' ) ) {
			add_filter( 'wp_lazy_loading_enabled', '__return_true' );
		}
	}

	/* --------------------------------------------------------------- */
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
		add_filter( 'emoji_svg_url', '__return_false' );
	}

	public function disable_embeds() {
		remove_action( 'rest_api_init', 'wp_oembed_register_route' );
		add_filter( 'embed_oembed_discover', '__return_false' );
		remove_filter( 'oembed_dataparse', 'wp_filter_oembed_result', 10 );
		remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
		remove_action( 'wp_head', 'wp_oembed_add_host_js' );
		add_filter( 'tiny_mce_plugins', function ( $plugins ) {
			return is_array( $plugins ) ? array_diff( $plugins, array( 'wpembed' ) ) : array();
		} );
	}

	public function remove_query_strings( $src ) {
		if ( $src && strpos( $src, '?ver=' ) !== false ) {
			$src = remove_query_arg( 'ver', $src );
		}
		return $src;
	}

	public function remove_pingback_header( $headers ) {
		unset( $headers['X-Pingback'] );
		return $headers;
	}

	private function clean_head() {
		remove_action( 'wp_head', 'rsd_link' );
		remove_action( 'wp_head', 'wlwmanifest_link' );
		remove_action( 'wp_head', 'wp_generator' );
		remove_action( 'wp_head', 'wp_shortlink_wp_head' );
		remove_action( 'wp_head', 'adjacent_posts_rel_link_wp_head' );
		remove_action( 'wp_head', 'feed_links_extra', 3 );
	}

	public function disable_dashicons() {
		if ( ! is_user_logged_in() ) {
			wp_deregister_style( 'dashicons' );
		}
	}

	public function remove_jquery_migrate( $scripts ) {
		if ( is_admin() ) {
			return;
		}
		if ( ! empty( $scripts->registered['jquery'] ) ) {
			$deps = $scripts->registered['jquery']->deps;
			$scripts->registered['jquery']->deps = array_diff( $deps, array( 'jquery-migrate' ) );
		}
	}

	public function limit_revisions( $num, $post ) {
		return (int) Velox_Settings::get( 'perf_revisions_keep', 5 );
	}

	public function heartbeat_settings( $settings ) {
		$hb = Velox_Settings::get( 'perf_heartbeat', 'default' );
		if ( 'slow' === $hb ) {
			$settings['interval'] = 60;
		}
		return $settings;
	}

	public function maybe_disable_heartbeat() {
		// Keep it alive in the post editor (autosave / lock), kill it elsewhere.
		global $pagenow;
		if ( ! in_array( $pagenow, array( 'post.php', 'post-new.php' ), true ) ) {
			wp_deregister_script( 'heartbeat' );
		}
	}

	public function output_dns_prefetch() {
		$lines = preg_split( '/\r\n|\r|\n/', (string) Velox_Settings::get( 'perf_dns_prefetch', '' ) );
		foreach ( $lines as $domain ) {
			$domain = trim( $domain );
			if ( '' === $domain ) {
				continue;
			}
			$domain = esc_url( $domain );
			echo '<link rel="dns-prefetch" href="' . $domain . '" />' . "\n";
			echo '<link rel="preconnect" href="' . $domain . '" crossorigin />' . "\n";
		}
	}

	public function defer_scripts( $tag, $handle, $src ) {
		if ( is_admin() ) {
			return $tag;
		}
		$exclude = preg_split( '/\r\n|\r|\n/', (string) Velox_Settings::get( 'perf_defer_exclude', '' ) );
		foreach ( $exclude as $needle ) {
			$needle = trim( $needle );
			if ( '' !== $needle && ( stripos( $handle, $needle ) !== false || stripos( $src, $needle ) !== false ) ) {
				return $tag;
			}
		}
		if ( strpos( $tag, ' defer' ) !== false || strpos( $tag, ' async' ) !== false ) {
			return $tag;
		}
		return str_replace( ' src=', ' defer src=', $tag );
	}
}
