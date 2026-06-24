<?php
/**
 * Velox — OctoberCMS theme builder.
 *
 * Crawls every published page, strips WordPress-specific markup, lifts the shared
 * chrome (head / nav / footer) into partials, converts the site's CSS into the
 * theme SCSS structure, pulls in only the media that's actually used and present
 * in the library, and packages it all as an importable OctoberCMS theme .zip.
 *
 * Builds are versioned: each scan is a build row with timing + size metadata, and
 * a "re-scan" diffs the page list against the previous build so you can see what
 * was added. Every version's zip is kept on disk, so reverting is just re-download.
 *
 * @package Velox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Velox_October {

	const DB_VERSION = '1.0';
	const VER_OPTION = 'velox_october_db';
	const SUBDIR     = 'velox-october';
	const MAX_PAGES  = 300;

	/* ------------------------------------------------------------------ setup */

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'velox_october_builds';
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$t       = self::table();
		dbDelta(
			"CREATE TABLE {$t} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				project VARCHAR(190) NOT NULL,
				version INT UNSIGNED NOT NULL DEFAULT 1,
				status VARCHAR(20) NOT NULL DEFAULT 'done',
				started DATETIME NULL,
				finished DATETIME NULL,
				duration_ms BIGINT UNSIGNED NULL,
				pages INT UNSIGNED NULL,
				media INT UNSIGNED NULL,
				size BIGINT UNSIGNED NULL,
				zip VARCHAR(255) NULL,
				manifest LONGTEXT NULL,
				note TEXT NULL,
				PRIMARY KEY  (id),
				KEY project (project)
			) {$charset};"
		);
		update_option( self::VER_OPTION, self::DB_VERSION );
	}

	public static function maybe_install() {
		if ( get_option( self::VER_OPTION ) !== self::DB_VERSION ) {
			self::install();
		}
	}

	public static function init() {
		// The download endpoint streams a file, so it can't go through the JSON router.
		add_action( 'wp_ajax_velox_october_download', array( __CLASS__, 'stream_download' ) );
	}

	private static function dir() {
		$up   = wp_upload_dir();
		$path = trailingslashit( $up['basedir'] ) . self::SUBDIR;
		if ( ! file_exists( $path ) ) {
			wp_mkdir_p( $path );
		}
		return $path;
	}

	/* ------------------------------------------------------------- public API */

	/** List builds, newest first, grouped-friendly (caller groups by project). */
	public static function builds() {
		global $wpdb;
		$t = self::table();
		return $wpdb->get_results( "SELECT * FROM {$t} ORDER BY id DESC", ARRAY_A ) ?: array(); // phpcs:ignore WordPress.DB
	}

	public static function get( $id ) {
		global $wpdb;
		$t = self::table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", (int) $id ), ARRAY_A ); // phpcs:ignore WordPress.DB
	}

	public static function delete( $id ) {
		$row = self::get( $id );
		if ( $row ) {
			$file = trailingslashit( self::dir() ) . basename( (string) $row['zip'] );
			if ( $row['zip'] && file_exists( $file ) ) {
				wp_delete_file( $file );
			}
			global $wpdb;
			$wpdb->delete( self::table(), array( 'id' => (int) $id ), array( '%d' ) );
		}
		return array( 'ok' => true );
	}

	/**
	 * Run a full build. $rescan_project, when given, makes this the next version of
	 * an existing project and returns a diff of which pages are new.
	 */
	public static function build( $name = '', $rescan_project = '' ) {
		@set_time_limit( 0 ); // phpcs:ignore
		$started    = current_time( 'mysql' );
		$start_ms   = (int) round( microtime( true ) * 1000 );

		$project = $rescan_project ? $rescan_project : self::project_slug( $name );
		$version = self::next_version( $project );

		// Previous manifest (for re-scan diffing).
		$prev = self::latest_build( $project );
		$prev_pages = array();
		if ( $prev && ! empty( $prev['manifest'] ) ) {
			$m = json_decode( $prev['manifest'], true );
			$prev_pages = isset( $m['pages'] ) ? (array) $m['pages'] : array();
		}

		// 1) Enumerate published pages.
		$pages = self::collect_pages();

		// 2) Crawl every page; lift chrome from the first reachable one.
		$theme_files = array();
		$page_files  = array();
		$media_urls  = array();
		$css_links   = array();   // same-origin stylesheet URLs (fetched + inlined)
		$ext_css     = array();   // cross-origin stylesheets (Google Fonts etc.) — kept as <link>
		$inline_css  = '';
		$chrome      = null;
		$page_slugs  = array();
		$report      = array();

		foreach ( $pages as $i => $pg ) {
			$err  = '';
			$html = self::fetch( $pg['url'], $err );
			if ( '' === $html ) {
				$report[] = array( 'url' => $pg['url'], 'ok' => false, 'why' => $err );
				continue;
			}
			$parsed = self::parse_page( $html );
			if ( null === $chrome ) {
				$chrome = $parsed['chrome'];
			}
			// Stylesheets from EVERY page: same-origin get fetched, cross-origin kept as links.
			foreach ( self::stylesheet_links( $parsed['doc'], $pg['url'] ) as $sheet ) {
				if ( ! empty( $sheet['external'] ) ) {
					$ext_css[ $sheet['url'] ] = true;
				} else {
					$css_links[ $sheet['url'] ] = true;
				}
			}
			$inline_css = $inline_css . self::inline_styles( $parsed['doc'] );
			$media_urls = array_merge( $media_urls, $parsed['media'] );
			$page_files[ $pg['slug'] ] = self::page_file( $pg, $parsed['content'] );
			$page_slugs[] = $pg['slug'];
			$report[] = array( 'url' => $pg['url'], 'ok' => true, 'why' => '' );
			if ( $i + 1 >= self::MAX_PAGES ) {
				break;
			}
		}

		// Nothing reachable → fail loudly, write no row and no zip.
		if ( empty( $page_files ) ) {
			return array(
				'ok'      => false,
				'pages'   => 0,
				'media'   => 0,
				'message' => self::fetch_failure_message( $report ),
				'report'  => array_slice( $report, 0, 8 ),
			);
		}

		if ( null === $chrome ) {
			$chrome = self::empty_chrome();
		}

		// Fetch each unique stylesheet once, then append inline CSS.
		$css_blob = '';
		foreach ( array_keys( $css_links ) as $href ) {
			$body = self::fetch( $href );
			if ( '' !== trim( (string) $body ) ) {
				$css_blob .= "\n/* ---- " . basename( (string) wp_parse_url( $href, PHP_URL_PATH ) ) . " ---- */\n" . $body . "\n";
			}
		}
		$css_blob .= $inline_css;

		// 3) Media: collect from pages + from CSS url() refs (backgrounds, fonts).
		$media_urls = array_merge( $media_urls, self::css_url_matches( $css_blob ) );
		$media_urls = array_values( array_unique( array_filter( $media_urls ) ) );
		$media_map  = self::resolve_media( $media_urls );

		// 4) Rewrite media references in chrome + pages.
		$chrome = self::rewrite_media_in_chrome( $chrome, $media_map );
		foreach ( $page_files as $slug => $body ) {
			$page_files[ $slug ] = self::rewrite_media_refs( $body, $media_map );
		}
		$css_blob = self::rewrite_media_in_css( $css_blob, $media_map );

		// 5) CSS → SCSS structure (for editing) + a plain compiled CSS the theme links live.
		$scss = self::css_to_scss( $css_blob );
		$plain_css = "/* Converted from the original WordPress site. Linked live by the theme.\n"
			. "   The assets/scss/ versions are provided for editing. */\n" . $css_blob;

		// 6) Assemble the theme file list.
		$theme_files = self::assemble_theme( $project, $chrome, $page_files, $scss, array_keys( $ext_css ) );
		$theme_files['assets/css/style.css'] = $plain_css;

		// Build manifest so the contents are verifiable without guessing.
		$media_bytes = 0;
		foreach ( $media_map as $info ) {
			if ( isset( $info['path'] ) && file_exists( $info['path'] ) ) {
				$media_bytes += (int) filesize( $info['path'] );
			} elseif ( isset( $info['data'] ) ) {
				$media_bytes += strlen( $info['data'] );
			}
		}
		$theme_files['BUILD-INFO.txt'] = self::build_info( $project, $version, $page_files, $css_blob, $media_map, $media_bytes );
		$theme_files['INSTALL.txt']    = self::install_text( $project );

		// 7) Zip it (theme files + media binaries).
		$zip_name = $project . '-v' . $version . '.zip';
		$zip_path = trailingslashit( self::dir() ) . $zip_name;
		$media_added = 0;
		$size     = self::write_zip( $zip_path, $theme_files, $media_map, $media_added );

		$finished    = current_time( 'mysql' );
		$duration_ms = (int) round( microtime( true ) * 1000 ) - $start_ms;

		$new_pages = array_values( array_diff( $page_slugs, $prev_pages ) );

		global $wpdb;
		$wpdb->insert(
			self::table(),
			array(
				'project'     => $project,
				'version'     => $version,
				'status'      => 'done',
				'started'     => $started,
				'finished'    => $finished,
				'duration_ms' => $duration_ms,
				'pages'       => count( $page_files ),
				'media'       => count( $media_map ),
				'size'        => $size,
				'zip'         => $zip_name,
				'manifest'    => wp_json_encode( array( 'pages' => $page_slugs, 'media' => array_keys( $media_map ) ) ),
				'note'        => $rescan_project ? 'Re-scan' : 'Initial build',
			),
			array( '%s', '%d', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%s', '%s' )
		);
		$id = (int) $wpdb->insert_id;

		return array(
			'ok'          => true,
			'id'          => $id,
			'project'     => $project,
			'version'     => $version,
			'pages'       => count( $page_files ),
			'media'       => count( $media_map ),
			'media_added' => $media_added,
			'css_bytes'   => strlen( $css_blob ),
			'size'        => $size,
			'duration'    => $duration_ms,
			'new_pages'   => $new_pages,
			'is_rescan'   => (bool) $rescan_project,
		);
	}

	/* ----------------------------------------------------------- enumeration */

	private static function collect_pages() {
		$out  = array();
		$seen = array(); // path => true
		$used = array(); // slug => true

		$add = function ( $title, $url, $slug_seed ) use ( &$out, &$seen, &$used ) {
			if ( ! $url ) {
				return;
			}
			$path = self::url_path( $url );
			if ( isset( $seen[ $path ] ) ) {
				return;
			}
			$seen[ $path ] = true;
			$slug = sanitize_title( $slug_seed );
			if ( '' === $slug ) {
				$slug = 'page';
			}
			$base = $slug;
			$n    = 2;
			while ( isset( $used[ $slug ] ) ) {
				$slug = $base . '-' . $n;
				$n++;
			}
			$used[ $slug ] = true;
			$out[] = array(
				'title' => $title ? $title : $slug,
				'url'   => $url,
				'slug'  => $slug,
				'path'  => $path,
			);
		};

		// Home / front page first.
		$add( get_bloginfo( 'name' ), home_url( '/' ), 'startseite' );

		// Every public post type: page, post, AND custom post types (landing pages,
		// portfolio, products, page-builder types, …) — that's where the rest live.
		$types = get_post_types( array( 'public' => true ), 'names' );
		unset( $types['attachment'] );

		foreach ( $types as $type ) {
			$items = get_posts(
				array(
					'post_type'        => $type,
					'post_status'      => 'publish',
					'numberposts'      => self::MAX_PAGES,
					'orderby'          => 'menu_order title',
					'order'            => 'ASC',
					'suppress_filters' => false,
				)
			);
			foreach ( (array) $items as $p ) {
				$url  = get_permalink( $p->ID );
				$seed = $p->post_name ? $p->post_name : ( $p->post_title ? $p->post_title : $type . '-' . $p->ID );
				$add( $p->post_title, $url, $seed );
				if ( count( $out ) >= self::MAX_PAGES ) {
					break 2;
				}
			}
		}

		return $out;
	}

	private static function fetch_args() {
		return array(
			'timeout'     => 25,
			'redirection' => 5,
			'sslverify'   => false,
			'user-agent'  => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
			'headers'     => array( 'X-Velox-Builder' => '1', 'Accept' => 'text/html,*/*' ),
		);
	}

	/** Fetch a URL robustly. Falls back to the origin (127.0.0.1 + Host) for Cloudflare-fronted / loopback-blocked sites. */
	private static function fetch( $url, &$err = null ) {
		$err  = '';
		$args = self::fetch_args();
		$res  = wp_remote_get( $url, $args );
		$body = '';
		$code = 0;
		if ( is_wp_error( $res ) ) {
			$err = $res->get_error_message();
		} else {
			$code = (int) wp_remote_retrieve_response_code( $res );
			$body = (string) wp_remote_retrieve_body( $res );
		}

		$good = ( 200 === $code && '' !== trim( $body ) && ! self::is_challenge( $body ) );
		if ( $good ) {
			return $body;
		}

		// Public request failed or was challenged — try the origin directly.
		$loop = self::fetch_origin( $url, $err );
		if ( '' !== $loop ) {
			return $loop;
		}

		if ( '' === $err ) {
			$err = $code ? ( 'HTTP ' . $code . ( self::is_challenge( $body ) ? ' (Cloudflare challenge)' : '' ) ) : 'empty response';
		}
		return '';
	}

	/** Hit the site's own server directly (bypasses Cloudflare) using a Host header. */
	private static function fetch_origin( $url, &$err = null ) {
		$parts = wp_parse_url( $url );
		if ( empty( $parts['host'] ) ) {
			return '';
		}
		$host = $parts['host'];
		$path = ( isset( $parts['path'] ) ? $parts['path'] : '/' ) . ( isset( $parts['query'] ) ? '?' . $parts['query'] : '' );
		$schemes = array();
		$schemes[] = isset( $parts['scheme'] ) ? $parts['scheme'] : 'https';
		$schemes[] = ( 'https' === $schemes[0] ) ? 'http' : 'https';

		foreach ( array( '127.0.0.1', 'localhost' ) as $origin ) {
			foreach ( array_unique( $schemes ) as $scheme ) {
				$args = self::fetch_args();
				$args['headers']['Host'] = $host;
				$res = wp_remote_get( $scheme . '://' . $origin . $path, $args );
				if ( is_wp_error( $res ) ) {
					$err = $res->get_error_message();
					continue;
				}
				$code = (int) wp_remote_retrieve_response_code( $res );
				$body = (string) wp_remote_retrieve_body( $res );
				if ( 200 === $code && '' !== trim( $body ) && ! self::is_challenge( $body ) ) {
					return $body;
				}
			}
		}
		return '';
	}

	private static function is_challenge( $body ) {
		if ( '' === $body ) {
			return false;
		}
		foreach ( array( 'cf-browser-verification', 'Just a moment', 'Attention Required! | Cloudflare', '__cf_chl', 'cf-challenge', 'Checking your browser before', 'cf_chl_opt' ) as $n ) {
			if ( false !== stripos( $body, $n ) ) {
				return true;
			}
		}
		return false;
	}

	private static function fetch_failure_message( $report ) {
		$why = '';
		foreach ( $report as $r ) {
			if ( empty( $r['ok'] ) && ! empty( $r['why'] ) ) {
				$why = $r['why'];
				break;
			}
		}
		$msg = 'Could not fetch any pages from your site.';
		if ( false !== stripos( $why, 'Cloudflare' ) || false !== stripos( $why, 'challenge' ) ) {
			$msg .= ' Cloudflare is challenging the server. Try pausing Cloudflare (or adding a WAF rule that allows requests with the header X-Velox-Builder) and re-run.';
		} elseif ( '' !== $why ) {
			$msg .= ' First error: ' . $why . '. The server may be blocking requests to its own domain (loopback).';
		}
		return $msg;
	}

	/** Quick connection test surfaced in the UI. */
	public static function diagnose() {
		$url    = home_url( '/' );
		$args   = self::fetch_args();
		$res    = wp_remote_get( $url, $args );
		if ( is_wp_error( $res ) ) {
			$public = 'error: ' . $res->get_error_message();
		} else {
			$code   = (int) wp_remote_retrieve_response_code( $res );
			$body   = (string) wp_remote_retrieve_body( $res );
			$public = 'HTTP ' . $code . ( self::is_challenge( $body ) ? ' — Cloudflare challenge' : ( 200 === $code && '' !== trim( $body ) ? ' — OK (' . number_format_i18n( strlen( $body ) ) . ' bytes)' : ' — empty' ) );
		}
		$e2          = '';
		$origin_body = self::fetch_origin( $url, $e2 );
		$origin      = '' !== $origin_body ? 'OK (' . number_format_i18n( strlen( $origin_body ) ) . ' bytes)' : ( 'failed' . ( $e2 ? ': ' . $e2 : '' ) );

		// Per-post-type published counts so we can see where content actually lives.
		$skip  = array( 'attachment', 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset', 'oembed_cache', 'user_request', 'wp_block', 'wp_template', 'wp_template_part', 'wp_global_styles', 'wp_navigation', 'acf-field', 'acf-field-group' );
		$types = array();
		foreach ( get_post_types( array(), 'objects' ) as $name => $obj ) {
			if ( in_array( $name, $skip, true ) ) {
				continue;
			}
			$counts = (array) wp_count_posts( $name );
			$pub    = isset( $counts['publish'] ) ? (int) $counts['publish'] : 0;
			if ( $pub > 0 || ! empty( $obj->public ) ) {
				$types[] = $name . ': ' . $pub . ( empty( $obj->public ) ? ' (private type)' : '' );
			}
		}

		return array(
			'version' => defined( 'VELOX_VERSION' ) ? VELOX_VERSION : '?',
			'home'    => $url,
			'public'  => $public,
			'origin'  => $origin,
			'pages'   => count( self::collect_pages() ),
			'types'   => $types ? implode( ' · ', $types ) : 'none',
			'dom'     => class_exists( 'DOMDocument' ) ? 'available' : 'MISSING — ask your host to enable php-dom',
			'zip'     => class_exists( 'ZipArchive' ) ? 'available' : 'MISSING — ask your host to enable php-zip',
		);
	}

	/** All stylesheet URLs in a document, flagged same-origin vs external. */
	private static function stylesheet_links( DOMDocument $doc, $page_url ) {
		$out  = array();
		$host = wp_parse_url( $page_url, PHP_URL_HOST );
		foreach ( $doc->getElementsByTagName( 'link' ) as $link ) {
			if ( false === stripos( (string) $link->getAttribute( 'rel' ), 'stylesheet' ) ) {
				continue;
			}
			$href = (string) $link->getAttribute( 'href' );
			if ( '' === $href ) {
				continue;
			}
			$abs = self::absolutise( $href, $page_url );
			$lh  = wp_parse_url( $abs, PHP_URL_HOST );
			$out[] = array( 'url' => $abs, 'external' => ( $lh && $lh !== $host ) );
		}
		return $out;
	}

	/** Concatenated inline <style> blocks in a document. */
	private static function inline_styles( DOMDocument $doc ) {
		$css = '';
		foreach ( $doc->getElementsByTagName( 'style' ) as $st ) {
			$txt = (string) $st->textContent;
			if ( '' !== trim( $txt ) ) {
				$css .= "\n/* ---- inline ---- */\n" . $txt . "\n";
			}
		}
		return $css;
	}

	/* ------------------------------------------------------- parse & convert */

	/** Parse one page: strip WP cruft, lift chrome, return main content + media. */
	public static function parse_page( $html ) {
		$doc = new DOMDocument();
		libxml_use_internal_errors( true );
		$doc->loadHTML( '<?xml encoding="utf-8" ?>' . $html, LIBXML_NOERROR | LIBXML_NOWARNING );
		libxml_clear_errors();

		self::strip_wp( $doc );

		$media = self::find_media( $doc );

		// Chrome: head meta we care about, header/nav, footer.
		$head_title = '';
		$head_desc  = '';
		foreach ( $doc->getElementsByTagName( 'title' ) as $t ) { $head_title = trim( $t->textContent ); break; }
		foreach ( $doc->getElementsByTagName( 'meta' ) as $m ) {
			if ( 'description' === strtolower( (string) $m->getAttribute( 'name' ) ) ) {
				$head_desc = (string) $m->getAttribute( 'content' );
			}
		}

		$header = self::first_node( $doc, array( 'header', 'nav' ) );
		$footer = self::first_node( $doc, array( 'footer' ) );
		$main   = self::first_node( $doc, array( 'main' ) );

		$nav_html    = $header ? self::inner_html( $header ) : '';
		$footer_html = $footer ? self::inner_html( $footer ) : '';

		// Main content: prefer <main>; else <body> minus header/footer/scripts.
		$body = $doc->getElementsByTagName( 'body' )->item( 0 );
		if ( $main ) {
			$content = self::inner_html( $main );
		} else {
			$content = $body ? self::body_minus_chrome( $body ) : '';
		}
		// Safety: if stripping chrome left almost nothing (common on page-builder
		// sites with no semantic <main>), fall back to the full body so a page is
		// never exported empty.
		if ( $body && strlen( trim( $content ) ) < 200 ) {
			$full = self::inner_html( $body );
			if ( strlen( trim( $full ) ) > strlen( trim( $content ) ) ) {
				$content = $full;
			}
		}

		$content = self::relativise_links( $content );
		// Never let a bare "==" line slip into markup — OctoberCMS treats it as a section break.
		$content = preg_replace( '/^(\s*==+\s*)$/m', ' $1', $content );

		return array(
			'doc'     => $doc,
			'content' => $content,
			'media'   => $media,
			'chrome'  => array(
				'nav'    => self::relativise_links( $nav_html ),
				'footer' => self::relativise_links( $footer_html ),
				'title'  => $head_title,
				'desc'   => $head_desc,
			),
		);
	}

	/** Remove WordPress-specific nodes that don't belong in a static theme. */
	private static function strip_wp( DOMDocument $doc ) {
		$xpath = new DOMXPath( $doc );

		// Admin bar + its styles/scripts.
		$kill = array();
		foreach ( $xpath->query( '//*[@id="wpadminbar"]' ) as $n ) { $kill[] = $n; }
		foreach ( $xpath->query( '//*[contains(@class,"admin-bar")]' ) as $n ) {
			// just the class is fine to keep on <body>; only remove the bar element itself
			if ( 'wpadminbar' === $n->getAttribute( 'id' ) ) { $kill[] = $n; }
		}

		// link/script/style that point at WP internals or are WP chrome.
		$needles = array( 'wp-includes', 'wp-json', 'wp-emoji', 'comment-reply', 'xmlrpc.php', 'wlwmanifest', '/feed', 'admin-bar' );
		foreach ( array( 'link', 'script' ) as $tag ) {
			foreach ( $doc->getElementsByTagName( $tag ) as $el ) {
				$attr = 'link' === $tag ? (string) $el->getAttribute( 'href' ) : (string) $el->getAttribute( 'src' );
				$id   = (string) $el->getAttribute( 'id' );
				$rel  = (string) $el->getAttribute( 'rel' );
				$hit  = false;
				foreach ( $needles as $nd ) {
					if ( '' !== $attr && false !== stripos( $attr, $nd ) ) { $hit = true; break; }
					if ( '' !== $id && false !== stripos( $id, $nd ) ) { $hit = true; break; }
				}
				if ( in_array( strtolower( $rel ), array( 'pingback', 'alternate', 'https://api.w.org/', 'edituri', 'wlwmanifest', 'shortlink', 'dns-prefetch' ), true ) ) {
					$hit = true;
				}
				if ( $hit ) { $kill[] = $el; }
			}
		}

		// generator / WP meta.
		foreach ( $doc->getElementsByTagName( 'meta' ) as $m ) {
			$name = strtolower( (string) $m->getAttribute( 'name' ) );
			if ( 'generator' === $name ) { $kill[] = $m; }
		}

		// inline emoji/admin-bar style blocks.
		foreach ( $doc->getElementsByTagName( 'style' ) as $st ) {
			$txt = (string) $st->textContent;
			if ( false !== stripos( $txt, 'wpadminbar' ) || false !== stripos( $txt, 'emoji' ) || false !== stripos( $txt, 'admin-bar' ) ) {
				$kill[] = $st;
			}
		}

		foreach ( $kill as $node ) {
			if ( $node->parentNode ) {
				$node->parentNode->removeChild( $node );
			}
		}

		// Remove ALL scripts + noscript — a static theme carries none of the site's JS
		// (this is what was leaking Oxygen's jQuery menu code as visible text).
		foreach ( array( 'script', 'noscript' ) as $tag ) {
			$nodes = iterator_to_array( $doc->getElementsByTagName( $tag ) );
			foreach ( $nodes as $n ) {
				if ( $n->parentNode ) {
					$n->parentNode->removeChild( $n );
				}
			}
		}

		self::normalise_classes( $doc );
	}

	/**
	 * Remove WordPress / page-builder junk from class names, IDs and data-attributes
	 * so the markup reads like hand-written HTML — WITHOUT stripping the structural
	 * builder classes the converted CSS still relies on for layout.
	 */
	private static function normalise_classes( DOMDocument $doc ) {
		$xpath = new DOMXPath( $doc );

		// Junk classes: pure WP/menu/state metadata that carry no real styling.
		$junk = array(
			'/^menu-item.*/', '/^current[-_]menu.*/', '/^current[-_]page.*/', '/^page-item.*/',
			'/^page-id-\d+$/', '/^postid-\d+$/', '/^post-\d+$/', '/^attachment-.*/',
			'/^wp-block-[a-z-]+$/', '/^has-.*-color$/', '/^has-.*-background-color$/',
			'/^is-layout-.*/', '/^wp-elements-[a-f0-9]+$/', '/^wp-container-.*/',
			'/^logged-in$/', '/^admin-bar$/', '/^customize-support$/', '/^no-customize-support$/',
			'/^elementor-page.*/', '/^elementor-\d+$/', '/^e-\d+$/',
			'/^screen-reader-text$/',
		);
		foreach ( $xpath->query( '//*[@class]' ) as $el ) {
			$classes = preg_split( '/\s+/', trim( (string) $el->getAttribute( 'class' ) ) );
			$keep    = array();
			foreach ( $classes as $c ) {
				if ( '' === $c ) { continue; }
				$drop = false;
				foreach ( $junk as $re ) {
					if ( preg_match( $re, $c ) ) { $drop = true; break; }
				}
				if ( ! $drop ) { $keep[] = $c; }
			}
			if ( $keep ) {
				$el->setAttribute( 'class', implode( ' ', $keep ) );
			} else {
				$el->removeAttribute( 'class' );
			}
		}

		// Remove WP-generated, meaningless IDs (e.g. pro-menu-269-83, menu-item-123,
		// block_abc123) — but keep short, human-looking IDs that may be anchor targets.
		foreach ( $xpath->query( '//*[@id]' ) as $el ) {
			$id = (string) $el->getAttribute( 'id' );
			if ( preg_match( '/^(pro-menu-|menu-item-|menu-\d|block_|_|post-\d|wp-|ctf-|et-|elementor-|ast-| wpforms-)/i', $id )
				|| preg_match( '/-\d+-\d+$/', $id )
				|| preg_match( '/^[a-f0-9]{8,}$/i', $id ) ) {
				$el->removeAttribute( 'id' );
			}
		}

		// Strip builder/data attributes that are pure noise.
		$drop_attr_prefixes = array( 'data-elementor', 'data-widget', 'data-id', 'data-element_type', 'data-settings', 'data-oxy', 'data-ct', 'data-shortcode' );
		foreach ( $xpath->query( '//*' ) as $el ) {
			if ( ! $el->hasAttributes() ) { continue; }
			$remove = array();
			foreach ( $el->attributes as $attr ) {
				foreach ( $drop_attr_prefixes as $p ) {
					if ( 0 === stripos( $attr->nodeName, $p ) ) { $remove[] = $attr->nodeName; break; }
				}
			}
			foreach ( $remove as $an ) {
				$el->removeAttribute( $an );
			}
		}
	}

	/** Gather all same-origin stylesheets + inline <style> into one blob. */
	private static function collect_css( DOMDocument $doc, $page_url ) {
		$css  = '';
		$host = wp_parse_url( $page_url, PHP_URL_HOST );
		foreach ( $doc->getElementsByTagName( 'link' ) as $link ) {
			if ( false === stripos( (string) $link->getAttribute( 'rel' ), 'stylesheet' ) ) {
				continue;
			}
			$href = (string) $link->getAttribute( 'href' );
			if ( '' === $href ) { continue; }
			$abs  = self::absolutise( $href, $page_url );
			$lh   = wp_parse_url( $abs, PHP_URL_HOST );
			if ( $lh && $lh !== $host ) {
				continue; // skip third-party CSS (fonts.googleapis etc.) — leave them out of the theme
			}
			$body = self::fetch( $abs );
			if ( '' !== $body ) {
				$css .= "\n/* ---- " . esc_html( basename( wp_parse_url( $abs, PHP_URL_PATH ) ) ) . " ---- */\n" . $body . "\n";
			}
		}
		foreach ( $doc->getElementsByTagName( 'style' ) as $st ) {
			$txt = (string) $st->textContent;
			if ( '' !== trim( $txt ) ) {
				$css .= "\n/* ---- inline ---- */\n" . $txt . "\n";
			}
		}
		return $css;
	}

	/**
	 * Convert a CSS blob into the theme's SCSS structure. Returns a map of
	 * assets/scss/* file => contents. CSS is valid SCSS, so the conversion is
	 * structural: pull :root custom properties into SCSS variables, keep the
	 * standard partial files, and import everything from style.scss.
	 */
	public static function css_to_scss( $css ) {
		$css = (string) $css;

		// Extract :root custom props → SCSS variables (kept as CSS vars too).
		$vars = '';
		if ( preg_match_all( '/:root\s*\{([^}]*)\}/i', $css, $mm ) ) {
			$seen = array();
			foreach ( $mm[1] as $block ) {
				if ( preg_match_all( '/--([\w-]+)\s*:\s*([^;}]+);?/', $block, $pairs, PREG_SET_ORDER ) ) {
					foreach ( $pairs as $p ) {
						$key = trim( $p[1] );
						$val = trim( $p[2] );
						if ( isset( $seen[ $key ] ) ) { continue; }
						$seen[ $key ] = true;
						$vars .= '$' . $key . ': ' . $val . ";\n";
					}
				}
			}
		}

		$variables = "// Variables (extracted from :root custom properties)\n" . ( '' !== $vars ? $vars : "// none detected\n" );

		$style  = "// Main SCSS entry — imports the partials, then the converted site styles.\n";
		$style .= "@import 'variables';\n@import 'fonts';\n@import 'navbar';\n@import 'header';\n@import 'offcanvas';\n@import 'footer';\n@import 'form';\n@import 'animations';\n\n";
		$style .= "/* ============================================================\n";
		$style .= " * Converted from the original WordPress site CSS.\n";
		$style .= " * ============================================================ */\n";
		$style .= $css . "\n";

		return array(
			'assets/scss/style.scss'      => $style,
			'assets/scss/variables.scss'  => $variables,
			'assets/scss/fonts.scss'      => "// Fonts\n",
			'assets/scss/navbar.scss'     => "// Navbar styles\n",
			'assets/scss/header.scss'     => "// Header styles\n",
			'assets/scss/offcanvas.scss'  => "// Offcanvas styles\n",
			'assets/scss/footer.scss'     => "// Footer styles\n",
			'assets/scss/form.scss'       => "// Form styles\n",
			'assets/scss/animations.scss' => "// Animations\n",
		);
	}

	/* ----------------------------------------------------------------- media */

	/** Find every referenced image/source URL, including lazy-loaded ones. */
	private static function find_media( DOMDocument $doc ) {
		$urls    = array();
		$src_at  = array( 'src', 'data-src', 'data-lazy-src', 'data-original', 'data-bg', 'data-background', 'data-background-image' );
		$set_at  = array( 'srcset', 'data-srcset', 'data-lazy-srcset' );

		foreach ( array( 'img', 'source' ) as $tag ) {
			foreach ( $doc->getElementsByTagName( $tag ) as $el ) {
				foreach ( $src_at as $a ) {
					$v = (string) $el->getAttribute( $a );
					if ( '' !== $v && 0 !== stripos( $v, 'data:' ) ) {
						$urls[] = $v;
					}
				}
				foreach ( $set_at as $a ) {
					$v = (string) $el->getAttribute( $a );
					if ( '' !== $v ) {
						$urls = array_merge( $urls, self::srcset_urls( $v ) );
					}
				}
			}
		}
		// inline style + data-bg background images on any element.
		$xpath = new DOMXPath( $doc );
		foreach ( $xpath->query( '//*[@style]' ) as $el ) {
			$urls = array_merge( $urls, self::css_url_matches( (string) $el->getAttribute( 'style' ) ) );
		}
		foreach ( $xpath->query( '//*[@data-bg or @data-background-image or @data-background]' ) as $el ) {
			foreach ( array( 'data-bg', 'data-background', 'data-background-image' ) as $a ) {
				$v = (string) $el->getAttribute( $a );
				if ( '' !== $v && 0 !== stripos( $v, 'data:' ) ) {
					$urls[] = trim( $v );
				}
			}
		}
		return $urls;
	}

	private static function srcset_urls( $srcset ) {
		$out = array();
		foreach ( explode( ',', $srcset ) as $part ) {
			$u = trim( preg_replace( '/\s+\d+[wx]$/', '', trim( $part ) ) );
			if ( '' !== $u ) { $out[] = $u; }
		}
		return $out;
	}

	private static function css_url_matches( $css ) {
		$out = array();
		if ( preg_match_all( '/url\(\s*[\'"]?([^\'")]+)[\'"]?\s*\)/i', $css, $m ) ) {
			foreach ( $m[1] as $u ) {
				$u = trim( $u );
				if ( '' !== $u && 0 !== stripos( $u, 'data:' ) ) { $out[] = $u; }
			}
		}
		return $out;
	}

	/**
	 * For each used URL, if it lives under the uploads dir and the file exists,
	 * include it. Returns url => [ 'rel' => assets/images/NAME, 'path' => abs ].
	 */
	public static function resolve_media( $urls ) {
		$up      = wp_upload_dir();
		$baseurl = trailingslashit( $up['baseurl'] );
		$basedir = trailingslashit( $up['basedir'] );
		$home    = home_url();
		$host    = wp_parse_url( $home, PHP_URL_HOST );
		$map     = array();
		$used    = array();
		$fetched = 0;

		$img_ext  = array( 'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'avif', 'bmp', 'ico' );
		$font_ext = array( 'woff', 'woff2', 'ttf', 'otf', 'eot' );

		foreach ( array_unique( array_filter( $urls ) ) as $url ) {
			if ( 0 === stripos( $url, 'data:' ) ) {
				continue;
			}
			$abs = self::absolutise( $url, $home . '/' );
			if ( ! $abs ) {
				continue;
			}
			$h = wp_parse_url( $abs, PHP_URL_HOST );
			if ( $h && $host && $h !== $host && ( 'www.' . $host ) !== $h && $host !== ( 'www.' . $h ) ) {
				continue; // same-origin assets only
			}
			$clean = strtok( $abs, '?#' );
			$ext   = strtolower( pathinfo( (string) wp_parse_url( $clean, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
			$is_font = in_array( $ext, $font_ext, true );
			$is_img  = in_array( $ext, $img_ext, true );
			if ( ! $is_font && ! $is_img ) {
				continue;
			}
			$folder = $is_font ? 'assets/fonts/' : 'assets/images/';

			// Prefer the local uploads file; otherwise fetch the asset over HTTP.
			$path = '';
			$data = null;
			$rel  = self::uploads_rel( $clean, $baseurl );
			if ( '' !== $rel && file_exists( $basedir . $rel ) ) {
				$path = $basedir . $rel;
			} elseif ( $fetched < 400 ) {
				$body = self::fetch_binary( $abs );
				if ( '' === $body ) {
					continue;
				}
				$data = $body;
				$fetched++;
			} else {
				continue;
			}

			$name = self::safe_asset_name( basename( (string) wp_parse_url( $clean, PHP_URL_PATH ) ), $used );
			$used[ $name ] = true;
			$entry = array( 'rel' => $folder . $name );
			if ( '' !== $path ) {
				$entry['path'] = $path;
			} else {
				$entry['data'] = $data;
			}
			$map[ $url ] = $entry;
		}
		return $map;
	}

	/** Path relative to the uploads base URL, scheme-agnostic; '' if not an uploads URL. */
	private static function uploads_rel( $clean, $baseurl ) {
		if ( false !== strpos( $clean, $baseurl ) ) {
			return ltrim( substr( $clean, strpos( $clean, $baseurl ) + strlen( $baseurl ) ), '/' );
		}
		$bu = preg_replace( '#^https?:#', '', $baseurl );
		$cu = preg_replace( '#^https?:#', '', $clean );
		if ( false !== strpos( $cu, $bu ) ) {
			return ltrim( substr( $cu, strpos( $cu, $bu ) + strlen( $bu ) ), '/' );
		}
		return '';
	}

	/** Fetch raw bytes for an asset (image/font). */
	private static function fetch_binary( $url ) {
		$res = wp_remote_get(
			$url,
			array(
				'timeout'     => 25,
				'redirection' => 3,
				'sslverify'   => false,
				'user-agent'  => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
			)
		);
		if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
			return '';
		}
		return (string) wp_remote_retrieve_body( $res );
	}

	private static function safe_asset_name( $name, $used ) {
		$name = sanitize_file_name( $name );
		if ( '' === $name ) { $name = 'image'; }
		$base = $name;
		$i    = 1;
		while ( isset( $used[ $name ] ) ) {
			$dot  = strrpos( $base, '.' );
			$name = ( false !== $dot ) ? substr( $base, 0, $dot ) . '-' . $i . substr( $base, $dot ) : $base . '-' . $i;
			$i++;
		}
		return $name;
	}

	private static function rewrite_media_refs( $html, $media_map ) {
		foreach ( $media_map as $url => $info ) {
			// In Twig pages, point at the theme asset.
			$twig = "{{ '" . $info['rel'] . "'|theme }}";
			$html = str_replace( array( $url, strtok( $url, '?' ) ), $twig, $html );
		}
		return $html;
	}

	private static function rewrite_media_in_chrome( $chrome, $media_map ) {
		$chrome['nav']    = self::rewrite_media_refs( $chrome['nav'], $media_map );
		$chrome['footer'] = self::rewrite_media_refs( $chrome['footer'], $media_map );
		return $chrome;
	}

	private static function rewrite_media_in_css( $css, $media_map ) {
		foreach ( $media_map as $url => $info ) {
			// style.css lives in assets/css/, so ../images or ../fonts from there.
			$target = '../' . preg_replace( '#^assets/#', '', $info['rel'] );
			$css    = str_replace( array( $url, strtok( $url, '?' ) ), $target, $css );
		}
		return $css;
	}

	/* -------------------------------------------------------------- assembly */

	private static function assemble_theme( $project, $chrome, $page_files, $scss, $ext_css = array() ) {
		$files = array();

		$files['theme.yaml'] = "name: '" . self::yaml( $project ) . "'\ndescription: 'Converted from WordPress by Velox'\nauthor: ''\nhomepage: ''\nauthorCode: ''\ncode: ''\nparent: ''\ndatabase: '0'\n";

		$files['layouts/default.htm'] = self::layout();

		// Partials.
		$files['partials/site/head.htm']   = self::head_partial( $ext_css );
		$files['partials/site/nav.htm']    = '' !== trim( $chrome['nav'] ) ? $chrome['nav'] : "<nav class=\"navbar navbar-expand-lg\">\n    <div class=\"container\">{# nav #}</div>\n</nav>\n";
		$files['partials/site/footer.htm'] = '' !== trim( $chrome['footer'] ) ? $chrome['footer'] : "<div class=\"container\">{# footer #}</div>\n";
		$files['partials/site/script.htm'] = self::script_partial();

		// Pages.
		foreach ( $page_files as $slug => $body ) {
			$files[ 'pages/' . $slug . '.htm' ] = $body;
		}

		// SCSS.
		foreach ( $scss as $rel => $contents ) {
			$files[ $rel ] = $contents;
		}

		// JS + housekeeping.
		$files['assets/js/script.js'] = "// Custom scripts\n";
		$files['content/.gitkeep']    = '';
		$files['meta/.gitkeep']       = '';

		return $files;
	}

	private static function layout() {
		return "==\n<?php\nfunction onStart() {\n    \$this['weburl'] = \$_SERVER['REQUEST_URI'];\n}\n?>\n==\n"
			. "<!DOCTYPE html>\n<html lang=\"de-DE\">\n    <head>\n        {% partial 'site/head' %}\n        {% framework extras %}\n    </head>\n    <body>\n        <header>\n            {% partial 'site/nav' %}\n        </header>\n        <main>\n            {% page %}\n        </main>\n        <footer>\n            {% partial 'site/footer' %}\n        </footer>\n        {% partial 'site/script' %}\n    </body>\n</html>\n";
	}

	private static function head_partial( $ext_css = array() ) {
		$ext = '';
		foreach ( (array) $ext_css as $href ) {
			$ext .= '<link href="' . esc_url( $href ) . "\" rel=\"stylesheet\">\n";
		}
		return "[seoTags]\n==\n<meta charset=\"utf-8\">\n<meta name=\"viewport\" content=\"width=device-width, initial-scale=1, shrink-to-fit=no\">\n<link rel=\"alternate\" href=\"{{ url('/') }}{{ weburl }}\" hreflang=\"de-DE\" />\n{% component 'seoTags' %}\n\n{# Bootstrap 5 #}\n<link href=\"https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css\" rel=\"stylesheet\">\n"
			. ( '' !== $ext ? "\n{# External stylesheets kept from the original site (fonts, etc.) #}\n" . $ext : '' )
			. "\n{# Converted site styles (plain CSS — always loads; SCSS sources in assets/scss/) #}\n<link href=\"{{ 'assets/css/style.css'|theme }}\" rel=\"stylesheet\">\n";
	}

	private static function script_partial() {
		return "{# Bootstrap 5 bundle (carousels, dropdowns, offcanvas, etc.) #}\n"
			. "<script src=\"https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js\"></script>\n"
			. "<script src=\"{{ 'assets/js/script.js' | theme }}\"></script>\n";
	}

	private static function page_file( $pg, $content ) {
		$url   = $pg['path'];
		$title = self::yaml( $pg['title'] );
		$fm    = 'url = "' . $url . "\"\nlayout = \"default\"\ntitle = \"" . $title . "\"\n==\n";
		return $fm . $content . "\n";
	}

	private static function write_zip( $zip_path, $theme_files, $media_map, &$media_added = 0 ) {
		$media_added = 0;
		if ( file_exists( $zip_path ) ) {
			wp_delete_file( $zip_path );
		}
		$zip = new ZipArchive();
		if ( true !== $zip->open( $zip_path, ZipArchive::CREATE ) ) {
			return 0;
		}
		foreach ( $theme_files as $rel => $contents ) {
			$zip->addFromString( $rel, $contents );
		}
		foreach ( $media_map as $info ) {
			if ( isset( $info['path'] ) && file_exists( $info['path'] ) ) {
				if ( $zip->addFile( $info['path'], $info['rel'] ) ) {
					$media_added++;
				}
			} elseif ( isset( $info['data'] ) ) {
				if ( $zip->addFromString( $info['rel'], $info['data'] ) ) {
					$media_added++;
				}
			}
		}
		$zip->close();
		return file_exists( $zip_path ) ? (int) filesize( $zip_path ) : 0;
	}

	private static function build_info( $project, $version, $page_files, $css_blob, $media_map, $media_bytes ) {
		$lines   = array();
		$lines[] = 'Velox → OctoberCMS theme build';
		$lines[] = 'Project: ' . $project . '   Version: v' . $version;
		$lines[] = 'Generated: ' . current_time( 'mysql' );
		$lines[] = str_repeat( '=', 52 );
		$lines[] = '';
		$lines[] = 'PAGES (' . count( $page_files ) . ')';
		foreach ( $page_files as $slug => $body ) {
			$body_only = (string) substr( (string) strstr( $body, "==\n" ), 3 );
			$lines[]   = sprintf( '  pages/%-28s %s of markup', $slug . '.htm', size_format( strlen( $body_only ) ) );
		}
		$lines[] = '';
		$lines[] = 'CSS: ' . size_format( strlen( $css_blob ) ) . '  →  assets/css/style.css (linked live) + assets/scss/*';
		$lines[] = '';
		$lines[] = 'MEDIA (' . count( $media_map ) . ', ' . size_format( $media_bytes ) . ')';
		$i = 0;
		foreach ( $media_map as $info ) {
			$lines[] = '  ' . $info['rel'];
			if ( ++$i >= 40 ) {
				$lines[] = '  … and ' . ( count( $media_map ) - 40 ) . ' more';
				break;
			}
		}
		return implode( "\n", $lines ) . "\n";
	}

	private static function install_text( $project ) {
		return "INSTALL — " . $project . "\n"
			. str_repeat( '=', 40 ) . "\n\n"
			. "Most reliable: copy this whole folder into your OctoberCMS site at\n"
			. "    themes/" . $project . "/\n"
			. "(so themes/" . $project . "/theme.yaml exists), then in the backend go to\n"
			. "Settings → Frontend Theme and activate it.\n\n"
			. "The backend \"Import\" button also works, but extracting straight into\n"
			. "themes/ guarantees the assets (images + CSS) come across.\n\n"
			. "Styles are linked from assets/css/style.css (plain CSS, always works).\n"
			. "The assets/scss/ folder holds the same styles split for editing.\n";
	}

	/* ------------------------------------------------------------- download */

	public static function stream_download() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Insufficient permissions.', 403 );
		}
		check_admin_referer( 'velox_october_dl' );

		// Bulk: every version of a project, zipped together.
		$project = isset( $_GET['project'] ) ? sanitize_title( wp_unslash( $_GET['project'] ) ) : '';
		if ( '' !== $project ) {
			self::stream_project( $project );
			return;
		}

		$id  = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		$row = self::get( $id );
		if ( ! $row || ! $row['zip'] ) {
			wp_die( 'Build not found.', 404 );
		}
		$file = trailingslashit( self::dir() ) . basename( (string) $row['zip'] );
		if ( ! file_exists( $file ) ) {
			wp_die( 'File missing.', 404 );
		}
		nocache_headers();
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . basename( $file ) . '"' );
		header( 'Content-Length: ' . filesize( $file ) );
		readfile( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}

	private static function stream_project( $project ) {
		global $wpdb;
		$t    = self::table();
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE project = %s ORDER BY version ASC", $project ), ARRAY_A ); // phpcs:ignore WordPress.DB
		if ( empty( $rows ) ) {
			wp_die( 'Nothing to download.', 404 );
		}
		$tmp = trailingslashit( self::dir() ) . $project . '-all-' . wp_generate_password( 6, false ) . '.zip';
		$zip = new ZipArchive();
		if ( true !== $zip->open( $tmp, ZipArchive::CREATE ) ) {
			wp_die( 'Could not assemble download.', 500 );
		}
		foreach ( $rows as $r ) {
			$f = trailingslashit( self::dir() ) . basename( (string) $r['zip'] );
			if ( $r['zip'] && file_exists( $f ) ) {
				$zip->addFile( $f, 'v' . (int) $r['version'] . '-' . basename( $f ) );
			}
		}
		$zip->close();
		if ( ! file_exists( $tmp ) ) {
			wp_die( 'Could not assemble download.', 500 );
		}
		nocache_headers();
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . $project . '-all-versions.zip"' );
		header( 'Content-Length: ' . filesize( $tmp ) );
		readfile( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		wp_delete_file( $tmp );
		exit;
	}

	/* -------------------------------------------------------------- helpers */

	private static function project_slug( $name ) {
		$slug = sanitize_title( $name );
		if ( '' === $slug ) {
			$slug = sanitize_title( wp_parse_url( home_url(), PHP_URL_HOST ) );
		}
		if ( '' === $slug ) { $slug = 'theme'; }
		return $slug;
	}

	private static function next_version( $project ) {
		global $wpdb;
		$t = self::table();
		$v = (int) $wpdb->get_var( $wpdb->prepare( "SELECT MAX(version) FROM {$t} WHERE project = %s", $project ) ); // phpcs:ignore WordPress.DB
		return $v + 1;
	}

	private static function latest_build( $project ) {
		global $wpdb;
		$t = self::table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE project = %s ORDER BY version DESC LIMIT 1", $project ), ARRAY_A ); // phpcs:ignore WordPress.DB
	}

	private static function url_path( $url ) {
		$p = wp_parse_url( $url, PHP_URL_PATH );
		$p = $p ? $p : '/';
		return '/' . trim( $p, '/' ) . ( '/' === $p ? '' : '/' );
	}

	private static function first_node( DOMDocument $doc, $tags ) {
		foreach ( $tags as $tag ) {
			$n = $doc->getElementsByTagName( $tag );
			if ( $n->length ) {
				return $n->item( 0 );
			}
		}
		return null;
	}

	private static function inner_html( DOMNode $node ) {
		$html = '';
		foreach ( $node->childNodes as $child ) {
			$html .= $node->ownerDocument->saveHTML( $child );
		}
		return trim( $html );
	}

	private static function body_minus_chrome( DOMNode $body ) {
		$html = '';
		foreach ( $body->childNodes as $child ) {
			if ( XML_ELEMENT_NODE === $child->nodeType ) {
				$tag = strtolower( $child->nodeName );
				if ( in_array( $tag, array( 'header', 'footer', 'nav', 'script', 'noscript' ), true ) ) {
					continue;
				}
			}
			$html .= $body->ownerDocument->saveHTML( $child );
		}
		return trim( $html );
	}

	/** Convert absolute on-site links to root-relative for OctoberCMS. */
	private static function relativise_links( $html ) {
		$home = home_url();
		$host = wp_parse_url( $home, PHP_URL_HOST );
		if ( ! $host ) {
			return $html;
		}
		// https://host/path → /path  (both schemes, optional www handled by host match)
		$html = preg_replace( '#https?://' . preg_quote( $host, '#' ) . '#i', '', $html );
		return $html;
	}

	private static function absolutise( $href, $base ) {
		if ( preg_match( '#^https?://#i', $href ) ) {
			return $href;
		}
		if ( 0 === strpos( $href, '//' ) ) {
			return ( 0 === stripos( $base, 'https' ) ? 'https:' : 'http:' ) . $href;
		}
		$root = preg_replace( '#(https?://[^/]+).*#i', '$1', $base );
		if ( 0 === strpos( $href, '/' ) ) {
			return $root . $href;
		}
		return trailingslashit( preg_replace( '#[^/]*$#', '', $base ) ) . $href;
	}

	private static function yaml( $s ) {
		return str_replace( array( "'", "\n", "\r" ), array( "''", ' ', '' ), (string) $s );
	}

	private static function empty_chrome() {
		return array( 'nav' => '', 'footer' => '', 'title' => '', 'desc' => '' );
	}

	private static function rewrite_media_refs_static() {} // reserved
}
