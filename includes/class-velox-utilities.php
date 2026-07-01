<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Utilities section. Each tool is a module that only wires up its hooks when
 * the matching util_* toggle is on, so nothing runs unless you turn it on.
 *
 * Live tools in this build: SVG upload (sanitised) and Duplicate post/page.
 * The rest of the Utilities hub is scaffolding for tools shipping in later builds.
 */
class Velox_Utilities {

	/** Tools shown in the hub. 'ready' ones have working toggles; others are planned. */
	public static function catalog() {
		return array(
			'svg'        => array( 'label' => 'SVG uploads',        'icon' => 'file',     'ready' => true,  'enable' => 'util_svg_upload', 'setting' => 'util_svg_upload', 'desc' => 'Allow SVG files in the media library, sanitised on upload so they can\'t carry scripts.' ),
			'duplicate'  => array( 'label' => 'Duplicate post/page', 'icon' => 'copy',     'ready' => true,  'enable' => 'util_duplicate', 'setting' => 'util_duplicate',  'desc' => 'Adds a one-click "Duplicate" link to every post and page so you can clone one as a draft.' ),
			'media'      => array( 'label' => 'Media Editor',        'icon' => 'tag',      'ready' => true,  'enable' => 'module_media', 'setting' => 'module_media', 'link' => 'media', 'desc' => 'Bulk-edit alt text and titles in a grid, rename files safely, and browse your whole library.' ),
			'installer'  => array( 'label' => 'Bulk installer',      'icon' => 'plug',     'ready' => true,  'enable' => 'util_installer', 'page' => true, 'desc' => 'Install a saved stack of plugins on a fresh site in one go, all or one by one.' ),
			'redirects'  => array( 'label' => 'Redirects & 404s',    'icon' => 'redirect', 'ready' => true,  'enable' => 'util_redirects', 'page' => true, 'desc' => 'Log 404s and turn any of them into a redirect; auto-redirect on permalink changes.' ),
			'mail'       => array( 'label' => 'Mail & forms',        'icon' => 'mail',     'ready' => true,  'enable' => 'util_mail', 'page' => true, 'desc' => 'Build and style forms, send through SMTP, with consent checkbox and CAPTCHA.' ),
			'fields'     => array( 'label' => 'Custom fields',       'icon' => 'grid',     'ready' => true,  'enable' => 'util_fields', 'page' => true, 'desc' => 'Add custom fields to posts, pages and any post type — field groups with location rules and a get_field() API, ACF-style.' ),
			'cookies'    => array( 'label' => 'Cookie banner',       'icon' => 'cookie',   'ready' => true,  'enable' => 'util_cookies', 'page' => true, 'desc' => 'A fully styleable consent banner with Google Consent Mode v2 — colours, placement, texts and legal links, all editable.' ),
			'october'    => array( 'label' => 'OctoberCMS theme',     'icon' => 'package',  'ready' => true,  'enable' => 'util_october', 'page' => true, 'desc' => 'Scan the whole site and export it as an importable OctoberCMS theme — pages, partials, CSS converted to SCSS, and the used media. Versioned with re-scan.' ),
			'backup'     => array( 'label' => 'Backup & restore',    'icon' => 'package',  'ready' => true,  'enable' => 'util_backup', 'page' => true, 'desc' => 'Back up the database and/or your files, download them, restore in a click, and run scheduled backups with a keep-N retention.' ),
			'unusedmedia'=> array( 'label' => 'Unused media',        'icon' => 'broom',    'ready' => true,  'enable' => 'util_unusedmedia', 'page' => true, 'desc' => 'Find media files nothing in your content references, and clean them out.' ),
			'loginurl'   => array( 'label' => 'Custom login URL',    'icon' => 'lock',     'ready' => true,  'enable' => 'util_loginurl', 'page' => true, 'desc' => 'Move wp-login to a custom path to cut brute-force bot traffic.' ),
			'maintenance'=> array( 'label' => 'Maintenance mode',    'icon' => 'cone',     'ready' => true,  'enable' => 'util_maintenance', 'page' => true, 'desc' => 'Show visitors a branded coming-soon page while you work, admins still get in.' ),
			'scripts'    => array( 'label' => 'Script Manager',      'icon' => 'code',     'ready' => true,  'enable' => 'util_scripts', 'page' => true, 'desc' => 'Stop specific CSS/JS from loading where it isn\'t needed — globally or per page.' ),
			'snippets'   => array( 'label' => 'Code Snippets',       'icon' => 'code',     'ready' => true,  'enable' => 'util_snippets', 'setting' => 'util_snippets', 'link' => 'snippets', 'desc' => 'Add PHP, CSS, JS or HTML snippets with run-location and priority. They get their own Snippets menu below Velox.' ),
		);
	}

	/** The enable-setting key for a utility (or '' if none). */
	public static function enable_key( $id ) {
		$cat = self::catalog();
		return isset( $cat[ $id ]['enable'] ) ? $cat[ $id ]['enable'] : '';
	}

	/** Is a utility switched on? */
	public static function is_enabled( $id ) {
		$key = self::enable_key( $id );
		return '' !== $key && (bool) Velox_Settings::get( $key, false );
	}

	/** Ordered list of switched-on utility ids — drives the sidebar sub-menu. */
	public static function enabled_tools() {
		$out = array();
		foreach ( array_keys( self::catalog() ) as $id ) {
			if ( self::is_enabled( $id ) ) {
				$out[] = $id;
			}
		}
		return $out;
	}

	/** Settings URL for a utility, honouring its catalog routing. */
	public static function tool_url( $id ) {
		$cat = self::catalog();
		if ( ! isset( $cat[ $id ] ) ) {
			return admin_url( 'admin.php?page=velox-utilities' );
		}
		$entry = $cat[ $id ];
		if ( ! empty( $entry['link'] ) ) {
			// e.g. snippets → velox-snippets, seo → velox-seo, media → velox-media
			return admin_url( 'admin.php?page=velox-' . $entry['link'] );
		}
		if ( ! empty( $entry['page'] ) ) {
			return admin_url( 'admin.php?page=velox-utilities&tool=' . $id );
		}
		// settings-only utilities (svg, duplicate) live on the Utilities hub
		return admin_url( 'admin.php?page=velox-utilities' );
	}

	public static function init() {
		// SVG uploads
		if ( Velox_Settings::get( 'util_svg_upload' ) ) {
			add_filter( 'upload_mimes', array( __CLASS__, 'allow_svg_mime' ) );
			add_filter( 'wp_check_filetype_and_ext', array( __CLASS__, 'fix_svg_filetype' ), 10, 4 );
			add_filter( 'wp_handle_upload_prefilter', array( __CLASS__, 'sanitize_svg_upload' ) );
		}
		// Duplicate post/page
		if ( Velox_Settings::get( 'util_duplicate' ) && is_admin() ) {
			add_filter( 'post_row_actions', array( __CLASS__, 'duplicate_link' ), 10, 2 );
			add_filter( 'page_row_actions', array( __CLASS__, 'duplicate_link' ), 10, 2 );
			add_action( 'admin_action_velox_duplicate', array( __CLASS__, 'do_duplicate' ) );
		}
		// Allow Lottie animation uploads (.json / .lottie) so they can be picked from
		// the media library for the maintenance logo or loading animation.
		add_filter( 'upload_mimes', array( __CLASS__, 'allow_lottie_mime' ) );
		add_filter( 'wp_check_filetype_and_ext', array( __CLASS__, 'fix_lottie_filetype' ), 10, 4 );
		// Maintenance mode (front end only — wp-admin/login stay reachable)
		if ( Velox_Settings::get( 'util_maintenance' ) ) {
			add_action( 'template_redirect', array( __CLASS__, 'maybe_maintenance' ) );
		}
		// Admin-bar quick toggle (available to admins whether it's on or off).
		add_action( 'admin_init', array( __CLASS__, 'maybe_toggle_maintenance' ) );
		// Custom login URL
		if ( Velox_Settings::get( 'util_loginurl' ) && '' !== self::login_slug() ) {
			self::login_init();
		}
	}

	/* ---------------------------------------------------------------------
	 * SVG uploads
	 * ------------------------------------------------------------------- */

	public static function allow_svg_mime( $mimes ) {
		if ( current_user_can( 'manage_options' ) ) {
			$mimes['svg']  = 'image/svg+xml';
			$mimes['svgz'] = 'image/svg+xml';
		}
		return $mimes;
	}

	public static function fix_svg_filetype( $data, $file, $filename, $mimes ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return $data;
		}
		if ( '.svg' === strtolower( substr( $filename, -4 ) ) ) {
			$data['ext']  = 'svg';
			$data['type'] = 'image/svg+xml';
		}
		return $data;
	}

	public static function allow_lottie_mime( $mimes ) {
		if ( current_user_can( 'manage_options' ) ) {
			$mimes['json']   = 'application/json';
			$mimes['lottie'] = 'application/zip';
		}
		return $mimes;
	}

	public static function fix_lottie_filetype( $data, $file, $filename, $mimes ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return $data;
		}
		$lower = strtolower( $filename );
		if ( '.json' === substr( $lower, -5 ) ) {
			$data['ext']  = 'json';
			$data['type'] = 'application/json';
		} elseif ( '.lottie' === substr( $lower, -7 ) ) {
			$data['ext']  = 'lottie';
			$data['type'] = 'application/zip';
		}
		return $data;
	}

	/** Scrub an SVG as it's uploaded; refuse it if it can't be cleaned. */
	public static function sanitize_svg_upload( $file ) {
		$name = isset( $file['name'] ) ? $file['name'] : '';
		if ( '.svg' !== strtolower( substr( $name, -4 ) ) ) {
			return $file;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			$file['error'] = 'You don\'t have permission to upload SVG files.';
			return $file;
		}
		$path  = isset( $file['tmp_name'] ) ? $file['tmp_name'] : '';
		$dirty = $path && is_readable( $path ) ? file_get_contents( $path ) : '';
		$clean = self::scrub_svg( (string) $dirty );
		if ( null === $clean ) {
			$file['error'] = 'That SVG couldn\'t be sanitised safely, so it wasn\'t uploaded.';
			return $file;
		}
		file_put_contents( $path, $clean );
		return $file;
	}

	/**
	 * Conservative SVG sanitiser: strips entities (XXE/billion-laughs), <script>
	 * and <foreignObject>, every on* event attribute, and javascript:/data:text
	 * URLs. Returns the cleaned markup, or null if it isn't a usable SVG.
	 */
	public static function scrub_svg( $svg ) {
		if ( '' === $svg || false === stripos( $svg, '<svg' ) ) {
			return null;
		}
		// Kill DOCTYPE/ENTITY declarations before parsing (entity-expansion attacks).
		$svg = preg_replace( '/<!DOCTYPE[^>]*>/is', '', $svg );
		$svg = preg_replace( '/<!ENTITY[^>]*>/is', '', $svg );

		// Fallback for the rare host without ext-dom: regex scrub.
		if ( ! class_exists( 'DOMDocument' ) ) {
			return self::scrub_svg_regex( $svg );
		}

		$prev = libxml_use_internal_errors( true );
		if ( function_exists( 'libxml_disable_entity_loader' ) && PHP_VERSION_ID < 80000 ) {
			libxml_disable_entity_loader( true );
		}
		$dom = new DOMDocument();
		$ok  = $dom->loadXML( $svg, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING );
		libxml_clear_errors();
		libxml_use_internal_errors( $prev );
		if ( ! $ok || ! $dom->documentElement ) {
			return null;
		}

		$bad_tags = array( 'script', 'foreignobject', 'iframe', 'embed', 'object', 'use', 'set', 'animate', 'handler', 'listener' );
		$remove   = array();
		foreach ( $dom->getElementsByTagName( '*' ) as $node ) {
			if ( in_array( strtolower( $node->nodeName ), $bad_tags, true ) ) {
				$remove[] = $node;
				continue;
			}
			if ( $node->hasAttributes() ) {
				$kill = array();
				foreach ( $node->attributes as $attr ) {
					$an = strtolower( $attr->nodeName );
					$av = strtolower( $attr->nodeValue );
					if ( 0 === strpos( $an, 'on' ) ) {
						$kill[] = $attr->nodeName;
					} elseif ( in_array( $an, array( 'href', 'xlink:href', 'src' ), true ) &&
						( false !== strpos( $av, 'javascript:' ) || false !== strpos( $av, 'data:text/html' ) ) ) {
						$kill[] = $attr->nodeName;
					}
				}
				foreach ( $kill as $a ) {
					$node->removeAttribute( $a );
				}
			}
		}
		foreach ( $remove as $node ) {
			if ( $node->parentNode ) {
				$node->parentNode->removeChild( $node );
			}
		}
		$out = $dom->saveXML( $dom->documentElement );
		return $out ? $out : null;
	}

	/** Regex-based scrub used only when ext-dom is missing. */
	private static function scrub_svg_regex( $svg ) {
		$svg = preg_replace( '#<(script|foreignObject|iframe|embed|object|use|set|animate|handler|listener)\b[^>]*>.*?</\1>#is', '', $svg );
		$svg = preg_replace( '#<(script|foreignObject|iframe|embed|object|use|set|animate|handler|listener)\b[^>]*/?>#is', '', $svg );
		$svg = preg_replace( '/\son[a-z-]+\s*=\s*"(?:[^"]*)"/i', '', $svg );
		$svg = preg_replace( "/\son[a-z-]+\s*=\s*'(?:[^']*)'/i", '', $svg );
		$svg = preg_replace( '/(href|xlink:href|src)\s*=\s*([\'"])\s*(?:javascript:|data:text\/html)[^\'"]*\2/i', '', $svg );
		return ( false !== stripos( $svg, '<svg' ) ) ? $svg : null;
	}

	/* ---------------------------------------------------------------------
	 * Duplicate post / page
	 * ------------------------------------------------------------------- */

	public static function duplicate_link( $actions, $post ) {
		if ( current_user_can( 'edit_posts' ) ) {
			$url = wp_nonce_url(
				admin_url( 'admin.php?action=velox_duplicate&post=' . $post->ID ),
				'velox_duplicate_' . $post->ID
			);
			$actions['velox_duplicate'] = '<a href="' . esc_url( $url ) . '">Duplicate</a>';
		}
		return $actions;
	}

	public static function do_duplicate() {
		$id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		if ( ! $id || ! current_user_can( 'edit_posts' ) ) {
			wp_die( 'Cannot duplicate this item.' );
		}
		check_admin_referer( 'velox_duplicate_' . $id );

		$post = get_post( $id );
		if ( ! $post ) {
			wp_die( 'Original not found.' );
		}

		$new_id = wp_insert_post( array(
			'post_title'   => $post->post_title . ' (copy)',
			'post_content' => $post->post_content,
			'post_excerpt' => $post->post_excerpt,
			'post_status'  => 'draft',
			'post_type'    => $post->post_type,
			'post_author'  => get_current_user_id(),
			'post_parent'  => $post->post_parent,
			'menu_order'   => $post->menu_order,
		) );
		if ( is_wp_error( $new_id ) || ! $new_id ) {
			wp_die( 'Could not create the copy.' );
		}

		// Taxonomies
		$taxes = get_object_taxonomies( $post->post_type );
		foreach ( $taxes as $tax ) {
			$terms = wp_get_object_terms( $id, $tax, array( 'fields' => 'slugs' ) );
			if ( ! is_wp_error( $terms ) && $terms ) {
				wp_set_object_terms( $new_id, $terms, $tax );
			}
		}
		// Meta (skip internals)
		$meta = get_post_meta( $id );
		foreach ( $meta as $key => $values ) {
			if ( '_edit_lock' === $key || '_edit_last' === $key || '_wp_old_slug' === $key ) {
				continue;
			}
			foreach ( $values as $v ) {
				add_post_meta( $new_id, $key, maybe_unserialize( $v ) );
			}
		}

		wp_safe_redirect( admin_url( 'post.php?action=edit&post=' . $new_id ) );
		exit;
	}

	/* ---------------------------------------------------------------------
	 * Maintenance mode
	 * ------------------------------------------------------------------- */

	public static function maybe_maintenance() {
		if ( current_user_can( 'manage_options' ) ) {
			return; // admins always see the live site
		}
		// The Velox theme builder crawls the site over loopback; let it see real pages.
		if ( ! empty( $_SERVER['HTTP_X_VELOX_BUILDER'] ) ) {
			return;
		}
		nocache_headers();
		status_header( 503 );
		header( 'Retry-After: 3600' );
		header( 'Content-Type: text/html; charset=utf-8' );
		echo self::maintenance_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/** Admin-bar quick toggle for maintenance mode. */
	public static function maybe_toggle_maintenance() {
		if ( empty( $_GET['velox_maint_toggle'] ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ), 'velox_maint_toggle' ) ) {
			return;
		}
		Velox_Settings::set( 'util_maintenance', ! Velox_Settings::get( 'util_maintenance' ) );
		// Go back to wherever the toggle was clicked from (the admin bar), not to the
		// maintenance settings page.
		$back = wp_get_referer();
		$back = $back ? remove_query_arg( array( 'velox_maint_toggle', '_wpnonce' ), $back ) : admin_url();
		wp_safe_redirect( $back );
		exit;
	}

	private static function hex_to_rgba( $hex, $alpha ) {
		$hex = ltrim( (string) $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		if ( 6 !== strlen( $hex ) || ! ctype_xdigit( $hex ) ) {
			return 'rgba(0,0,0,' . $alpha . ')';
		}
		return 'rgba(' . hexdec( substr( $hex, 0, 2 ) ) . ',' . hexdec( substr( $hex, 2, 2 ) ) . ',' . hexdec( substr( $hex, 4, 2 ) ) . ',' . $alpha . ')';
	}

	/** Build the maintenance page from saved settings. */
	/** Animation markup + CSS for the maintenance page, by type. */
	private static function maintenance_anim( $type, $accent, $text, $lottie = '' ) {
		$track = self::hex_to_rgba( $text, 0.14 );
		switch ( $type ) {
			case 'none':
				return array( '', '' );
			case 'lottie':
				if ( '' === (string) $lottie ) {
					return array( '', '' );
				}
				$html = '<script src="https://unpkg.com/@lottiefiles/lottie-player@latest/dist/lottie-player.js"></script>'
					. '<lottie-player class="vx-lottie" src="' . esc_url( $lottie ) . '" autoplay loop style="width:120px;height:120px;margin:24px auto 0"></lottie-player>';
				return array( '', $html );
			case 'pulse':
				$css  = '.vx-pulse{width:46px;height:46px;border-radius:50%;margin:30px auto 0;background:' . esc_attr( $accent ) . ';animation:vxpulse 1.5s ease-in-out infinite}@keyframes vxpulse{0%,100%{opacity:.35;transform:scale(.86)}50%{opacity:1;transform:scale(1)}}';
				return array( $css, '<div class="vx-pulse"></div>' );
			case 'dots':
				$css  = '.vx-dots{display:flex;gap:8px;justify-content:center;margin:32px auto 0}.vx-dots i{width:11px;height:11px;border-radius:50%;background:' . esc_attr( $accent ) . ';animation:vxbounce 1.3s ease-in-out infinite}.vx-dots i:nth-child(2){animation-delay:.18s}.vx-dots i:nth-child(3){animation-delay:.36s}@keyframes vxbounce{0%,100%{transform:translateY(0);opacity:.4}50%{transform:translateY(-9px);opacity:1}}';
				return array( $css, '<div class="vx-dots"><i></i><i></i><i></i></div>' );
			case 'spinner':
				$css  = '.vx-spin{width:40px;height:40px;margin:30px auto 0;border-radius:50%;border:4px solid ' . $track . ';border-top-color:' . esc_attr( $accent ) . ';animation:vxspin .9s linear infinite}@keyframes vxspin{to{transform:rotate(360deg)}}';
				return array( $css, '<div class="vx-spin"></div>' );
			case 'bar':
			default:
				$css  = '.vx-bar{width:120px;height:4px;border-radius:4px;margin:30px auto 0;overflow:hidden;background:' . $track . '}.vx-bar i{display:block;width:40%;height:100%;border-radius:4px;background:' . esc_attr( $accent ) . ';animation:vxslide 1.4s ease-in-out infinite}@keyframes vxslide{0%{transform:translateX(-120%)}100%{transform:translateX(320%)}}';
				return array( $css, '<div class="vx-bar"><i></i></div>' );
		}
	}

	/** Logo/media markup — supports images, GIFs and Lottie (.json / .lottie). */
	private static function maintenance_media( $logo, $alt ) {
		if ( preg_match( '/\.(json|lottie)(\?|$)/i', $logo ) ) {
			return '<script src="https://unpkg.com/@lottiefiles/lottie-player@latest/dist/lottie-player.js"></script>'
				. '<lottie-player class="logo" src="' . esc_url( $logo ) . '" autoplay loop style="max-width:220px;max-height:160px;margin:0 auto 26px"></lottie-player>';
		}
		return '<img class="logo" src="' . esc_url( $logo ) . '" alt="' . esc_attr( $alt ) . '">';
	}

	private static function maintenance_html() {
		$title  = Velox_Settings::get( 'util_maintenance_title' );
		$msg    = Velox_Settings::get( 'util_maintenance_message' );
		$logo   = Velox_Settings::get( 'util_maintenance_logo' );
		$bg     = Velox_Settings::get( 'util_maintenance_bg' );
		$text   = Velox_Settings::get( 'util_maintenance_text' );
		$accent = Velox_Settings::get( 'util_maintenance_accent' );
		$bgimg  = Velox_Settings::get( 'util_maintenance_bgimage' );
		$btn_t  = Velox_Settings::get( 'util_maintenance_btn_text' );
		$btn_u  = Velox_Settings::get( 'util_maintenance_btn_url' );
		$brand  = Velox_Settings::get( 'util_maintenance_brand' );
		$anim   = Velox_Settings::get( 'util_maintenance_anim' );
		$lottie = Velox_Settings::get( 'util_maintenance_lottie' );

		$title  = '' !== (string) $title ? $title : 'We\'ll be right back';
		$msg    = '' !== (string) $msg ? $msg : 'The site is undergoing maintenance. Please check back soon.';
		$bg     = $bg ? $bg : '#0c0e17';
		$text   = $text ? $text : '#e9edf5';
		$accent = $accent ? $accent : '#2ab7f1';
		$logo   = $logo ? $logo : VELOX_URL . 'assets/logo.png';

		$t     = esc_html( $title );
		$m     = nl2br( esc_html( $msg ) );
		$name  = esc_html( get_bloginfo( 'name' ) );
		$muted = self::hex_to_rgba( $text, 0.62 );

		if ( $bgimg ) {
			$body_bg = 'background:linear-gradient(' . self::hex_to_rgba( $bg, 0.86 ) . ',' . self::hex_to_rgba( $bg, 0.94 ) . '),url(' . esc_url( $bgimg ) . ') center/cover no-repeat fixed;';
		} else {
			$body_bg = 'background:' . esc_attr( $bg ) . ';';
		}

		$button = '';
		if ( '' !== (string) $btn_t && '' !== (string) $btn_u ) {
			$button = '<a class="btn" href="' . esc_url( $btn_u ) . '">' . esc_html( $btn_t ) . '</a>';
		}
		$footer = '' !== (string) $brand ? '<div class="brand">' . esc_html( $brand ) . '</div>' : '';

		list( $anim_css, $anim_html ) = self::maintenance_anim( $anim, $accent, $text, $lottie );

		return '<!DOCTYPE html><html lang="' . esc_attr( get_bloginfo( 'language' ) ) . '"><head><meta charset="utf-8">'
			. '<meta name="viewport" content="width=device-width,initial-scale=1">'
			. '<meta name="robots" content="noindex,nofollow">'
			. '<title>' . $t . '</title><style>'
			. '*{box-sizing:border-box}body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;'
			. 'font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;color:' . esc_attr( $text ) . ';padding:24px;' . $body_bg . '}'
			. '.box{max-width:560px;text-align:center}.logo{max-width:220px;max-height:72px;width:auto;height:auto;margin:0 auto 26px;display:block}'
			. 'h1{font-size:32px;margin:0 0 14px;letter-spacing:-.02em;line-height:1.15}'
			. 'p{font-size:16px;line-height:1.65;color:' . esc_attr( $muted ) . ';margin:0 auto;max-width:46ch}'
			. $anim_css
			. '.btn{display:inline-block;margin-top:28px;padding:12px 26px;border-radius:10px;font-weight:700;font-size:15px;'
			. 'background:' . esc_attr( $accent ) . ';color:#fff;text-decoration:none}'
			. '.brand{margin-top:32px;font-size:13px;color:' . self::hex_to_rgba( $text, 0.4 ) . '}</style></head><body><div class="box">'
			. self::maintenance_media( $logo, $name )
			. '<h1>' . $t . '</h1><p>' . $m . '</p>'
			. $button
			. $anim_html
			. $footer
			. '</div></body></html>';
	}

	/* ---------------------------------------------------------------------
	 * Custom login URL  (hides wp-login.php behind a custom slug)
	 * ------------------------------------------------------------------- */

	public static function login_slug() {
		return trim( (string) Velox_Settings::get( 'util_login_slug' ), '/' );
	}

	public static function login_init() {
		// init@0 is the earliest hook still pending at this point (we're already
		// inside plugins_loaded), runs before the main query (so no 404), and is
		// late enough that is_user_logged_in() and the redirect helpers exist.
		add_action( 'init', array( __CLASS__, 'login_intercept' ), 0 );
		add_filter( 'login_url', array( __CLASS__, 'filter_login_url' ), 10, 1 );
		add_filter( 'logout_url', array( __CLASS__, 'filter_login_url' ), 10, 1 );
		add_filter( 'lostpassword_url', array( __CLASS__, 'filter_login_url' ), 10, 1 );
		add_filter( 'register_url', array( __CLASS__, 'filter_login_url' ), 10, 1 );
		add_filter( 'site_url', array( __CLASS__, 'filter_site_url' ), 10, 1 );
		add_filter( 'network_site_url', array( __CLASS__, 'filter_site_url' ), 10, 1 );
		add_filter( 'wp_redirect', array( __CLASS__, 'filter_site_url' ), 10, 1 );
	}

	public static function new_login_url() {
		return home_url( '/' . self::login_slug() . '/' );
	}

	/**
	 * Pure decision for the login interceptor (kept separate so it can be tested):
	 *   'serve'    → serve the real login at the pretty secret slug
	 *   'redirect' → hide the default wp-login from a logged-out visitor
	 *   'pass'     → do nothing (allow the request through)
	 */
	public static function login_decision( $uri, $slug, $has_key, $logged_in, $is_post ) {
		if ( '' === $slug ) {
			return 'pass';
		}
		$path        = trim( (string) wp_parse_url( $uri, PHP_URL_PATH ), '/' );
		$on_wp_login = ( false !== strpos( $uri, 'wp-login.php' ) ) || ( false !== strpos( $uri, 'wp-register.php' ) );

		if ( ! $on_wp_login && $path === $slug ) {
			return 'serve';
		}
		if ( $on_wp_login ) {
			if ( $has_key || $logged_in || $is_post ) {
				return 'pass';
			}
			return 'redirect';
		}
		return 'pass';
	}

	public static function login_intercept() {
		if ( ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) || ( defined( 'DOING_CRON' ) && DOING_CRON ) ) {
			return;
		}
		$slug = self::login_slug();
		if ( '' === $slug ) {
			return;
		}
		$uri      = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
		$decision = self::login_decision( $uri, $slug, isset( $_GET[ $slug ] ), is_user_logged_in(), ! empty( $_POST ) );

		if ( 'serve' === $decision ) {
			nocache_headers();
			global $pagenow;
			$pagenow = 'wp-login.php';
			require_once ABSPATH . 'wp-login.php';
			exit;
		}
		// nocache_headers() here is critical: without it a CDN/browser can cache the
		// redirect and lock everyone out — which is exactly what bit us before.
		if ( 'redirect' === $decision ) {
			nocache_headers();
			wp_safe_redirect( home_url( '/' ), 302 );
			exit;
		}

		// Hide wp-admin from logged-out visitors entirely. WordPress would otherwise
		// bounce them to the login URL (revealing the secret slug); instead we send
		// them home, so the only way in is to know the custom login path. admin-ajax
		// and admin-post stay open because public front-end features rely on them.
		if ( ! is_user_logged_in()
			&& false !== strpos( $uri, '/wp-admin' )
			&& false === strpos( $uri, 'admin-ajax.php' )
			&& false === strpos( $uri, 'admin-post.php' ) ) {
			nocache_headers();
			wp_safe_redirect( home_url( '/' ), 302 );
			exit;
		}
	}

	public static function filter_login_url( $url ) {
		// Trailing slash matters: many Nginx/Plesk setups 404 a slashless,
		// extension-less path (/canicani) but route /canicani/ to index.php.
		return is_string( $url ) ? str_replace( 'wp-login.php', self::login_slug() . '/', $url ) : $url;
	}

	public static function filter_site_url( $url ) {
		if ( is_string( $url ) && false !== strpos( $url, 'wp-login.php' ) ) {
			$url = str_replace( 'wp-login.php', self::login_slug() . '/', $url );
		}
		return $url;
	}

	/* ---------------------------------------------------------------------
	 * Unused-media finder
	 *
	 * Conservative on purpose: it errs toward "in use" so it never flags a file
	 * that's actually referenced. It checks featured images, post content and
	 * post meta (where page builders like Oxygen store image references) against
	 * the file's name stem, which also catches resized variants.
	 * ------------------------------------------------------------------- */

	public static function find_unused( $limit = 250 ) {
		$all = self::scan_media( $limit );
		$out = array();
		foreach ( $all as $m ) {
			if ( empty( $m['used'] ) ) {
				unset( $m['used'] );
				$out[] = $m;
			}
		}
		return $out;
	}

	/**
	 * Classify every image in the library as used or unused, with file size.
	 * One rendered-HTML/CSS blob is built and reused across all images. Errs toward
	 * "used" so a referenced file is never mislabeled as safe to delete.
	 */
	public static function scan_media( $limit = 400 ) {
		$ids = get_posts( array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'post_mime_type' => 'image',
			'posts_per_page' => (int) $limit,
			'fields'         => 'ids',
			'orderby'        => 'date',
			'order'          => 'ASC',
		) );

		// DB pass: which ids are referenced somewhere, plus each id's filenames.
		$used  = array();
		$names = array();
		foreach ( $ids as $id ) {
			if ( self::is_referenced( $id ) ) {
				$used[ $id ] = true;
			} else {
				$names[ $id ] = self::all_basenames( $id );
			}
		}

		// Front-end pass: any remaining candidate whose filename appears in the
		// rendered HTML or generated CSS is actually in use.
		if ( $names ) {
			$html = self::rendered_html_blob();
			if ( '' !== $html ) {
				foreach ( $names as $id => $list ) {
					foreach ( $list as $n ) {
						if ( '' !== $n && false !== strpos( $html, $n ) ) {
							$used[ $id ] = true;
							break;
						}
					}
				}
			}
		}

		$out = array();
		foreach ( $ids as $id ) {
			$file  = get_attached_file( $id );
			$out[] = array(
				'id'    => $id,
				'title' => get_the_title( $id ),
				'url'   => wp_get_attachment_url( $id ),
				'thumb' => self::thumb_url( $id ),
				'bytes' => ( $file && file_exists( $file ) ) ? (int) filesize( $file ) : 0,
				'used'  => ! empty( $used[ $id ] ),
			);
		}
		return $out;
	}

	/** Every filename this attachment could appear as: original + all generated sizes. */
	private static function all_basenames( $id ) {
		$names = array();
		$file = get_post_meta( $id, '_wp_attached_file', true );
		if ( ! $file ) { $file = (string) get_attached_file( $id ); }
		if ( $file ) { $names[ basename( $file ) ] = 1; }
		$meta = wp_get_attachment_metadata( $id );
		if ( ! empty( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
			foreach ( $meta['sizes'] as $size ) {
				if ( ! empty( $size['file'] ) ) { $names[ basename( $size['file'] ) ] = 1; }
			}
		}
		// WordPress also creates a "-scaled" variant for big images
		if ( $file ) {
			$stem = pathinfo( basename( $file ), PATHINFO_FILENAME );
			$ext  = pathinfo( basename( $file ), PATHINFO_EXTENSION );
			if ( $stem && $ext ) { $names[ $stem . '-scaled.' . $ext ] = 1; }
		}
		return array_keys( $names );
	}

	/**
	 * Fetch a set of representative front-end pages and return their combined HTML,
	 * so we can confirm whether a media file actually appears on the live site.
	 * Cached briefly so a scan of many files only fetches the pages once.
	 */
	private static function rendered_html_blob() {
		static $blob = null;
		if ( null !== $blob ) { return $blob; }
		$blob = '';

		$urls = array( home_url( '/' ) );
		// Pages first (these are usually the builder-built ones), then posts and
		// products. A wider net than before so an image on a deeper page isn't
		// mistaken for unused.
		$posts = get_posts( array(
			'numberposts' => 80,
			'post_status' => 'publish',
			'post_type'   => array( 'page', 'post', 'product' ),
			'fields'      => 'ids',
			'orderby'     => 'modified',
			'order'       => 'DESC',
		) );
		foreach ( $posts as $pid ) {
			$link = get_permalink( $pid );
			if ( $link ) { $urls[] = $link; }
		}
		$urls = array_values( array_unique( array_filter( $urls ) ) );
		$urls = array_slice( $urls, 0, 60 );

		foreach ( $urls as $u ) {
			$res = wp_remote_get( $u, array( 'timeout' => 10, 'sslverify' => false ) );
			if ( is_wp_error( $res ) ) { continue; }
			$body = wp_remote_retrieve_body( $res );
			if ( is_string( $body ) && '' !== $body ) { $blob .= "\n" . $body; }
		}

		// CRITICAL for Oxygen/Elementor: background-image URLs live in generated CSS
		// cache files, not the page HTML. Read those local files directly so a section
		// background is never mistaken for an unused image.
		$up  = wp_upload_dir();
		$dir = isset( $up['basedir'] ) ? trailingslashit( $up['basedir'] ) : '';
		$globs = array();
		if ( $dir ) {
			$globs[] = $dir . 'oxygen/css/*.css';
			$globs[] = $dir . 'elementor/css/*.css';
			$globs[] = $dir . 'bricks/css/*.css';
		}
		foreach ( $globs as $g ) {
			foreach ( (array) glob( $g ) as $cssfile ) {
				if ( is_readable( $cssfile ) && filesize( $cssfile ) < 4000000 ) {
					$css = file_get_contents( $cssfile );
					if ( is_string( $css ) && '' !== $css ) { $blob .= "\n" . $css; }
				}
			}
		}
		return $blob;
	}

	private static function thumb_url( $id ) {
		$img = wp_get_attachment_image_src( $id, 'thumbnail' );
		return $img ? $img[0] : wp_get_attachment_url( $id );
	}

	private static function is_referenced( $id ) {
		global $wpdb;
		// Featured image anywhere?
		$as_thumb = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_thumbnail_id' AND meta_value = %d",
			$id
		) );
		if ( $as_thumb ) {
			return true;
		}
		// Site logo / icon?
		if ( (int) get_option( 'site_icon' ) === (int) $id || (int) get_theme_mod( 'custom_logo' ) === (int) $id ) {
			return true;
		}
		$file = get_post_meta( $id, '_wp_attached_file', true );
		$base = $file ? basename( $file ) : basename( (string) get_attached_file( $id ) );
		if ( '' === $base ) {
			return true; // can't identify the file → treat as in-use (safe)
		}
		$stem = pathinfo( $base, PATHINFO_FILENAME );
		if ( strlen( $stem ) < 3 ) {
			return true; // too generic to match safely → leave it alone
		}
		$like = '%' . $wpdb->esc_like( $stem ) . '%';
		// Referenced in any post/page content? (catches all size variants via the stem)
		$in_content = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status NOT IN ('trash','auto-draft') AND post_content LIKE %s",
			$like
		) );
		if ( $in_content ) {
			return true;
		}
		// Referenced in any OTHER post's meta (builders, ACF, galleries)?
		$in_meta = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE post_id <> %d AND meta_value LIKE %s",
			$id,
			$like
		) );
		if ( $in_meta ) {
			return true;
		}
		// Referenced in options (theme mods, customizer, widgets, plugin settings)?
		$in_options = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->options} WHERE option_value LIKE %s",
			$like
		) );
		if ( $in_options ) {
			return true;
		}
		// Referenced by attachment ID in meta (ACF image fields, galleries, blocks store
		// the ID, not the name). Match the ID only as a *discrete* value — exact, inside a
		// comma-separated list, or quoted in serialized/JSON data — NEVER as a bare
		// substring, which would match the ID's digits anywhere inside a page-builder JSON
		// blob (e.g. Oxygen's ct_builder_shortcodes) and flag basically everything as used.
		$id_str = (string) (int) $id;
		$idq    = $wpdb->esc_like( $id_str );
		$by_id  = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE post_id <> %d AND (
				meta_value = %s
				OR meta_value LIKE %s
				OR meta_value LIKE %s
				OR meta_value LIKE %s
				OR meta_value LIKE %s
			)",
			$id,
			$id_str,                    // exact single-value field (ACF image, _thumbnail_id-style)
			$idq . ',%',                // start of a comma list
			'%,' . $idq . ',%',         // middle of a comma list
			'%,' . $idq,                // end of a comma list
			'%"' . $idq . '"%'          // serialized / JSON quoted id (ACF gallery, block attrs)
		) );
		return (bool) $by_id;
	}

	public static function delete_media( $ids ) {
		if ( ! current_user_can( 'delete_posts' ) ) {
			return array( 'ok' => false, 'message' => 'You can\'t delete media.' );
		}
		$done = 0;
		$freed = 0;
		foreach ( (array) $ids as $id ) {
			$id = (int) $id;
			if ( ! $id || 'attachment' !== get_post_type( $id ) ) {
				continue;
			}
			$file = get_attached_file( $id );
			if ( $file && file_exists( $file ) ) {
				$freed += (int) filesize( $file );
			}
			if ( wp_delete_attachment( $id, true ) ) {
				$done++;
			}
		}
		return array( 'ok' => true, 'deleted' => $done, 'freed' => $freed );
	}

	/* ---------------------------------------------------------------------
	 * Bulk plugin installer + blueprints
	 *
	 * Installs plugins straight from the WordPress.org directory by slug, using
	 * core's own Plugin_Upgrader. Blueprints are named slug lists you can save on
	 * one site and re-apply on the next — perfect for spinning up agency builds.
	 * ------------------------------------------------------------------- */

	/**
	 * Clear the bits of state that make back-to-back installs fail after the first
	 * one: a leftover upgrader lock, a stale ".maintenance" flag, and a cached
	 * plugin/update list. This is the usual reason a queue installs one plugin and
	 * then errors on the rest.
	 */
	private static function prep_install() {
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		WP_Filesystem();
		delete_option( 'plugin_upgrader.lock' );
		delete_option( 'core_updater.lock' );
		if ( file_exists( ABSPATH . '.maintenance' ) ) {
			@unlink( ABSPATH . '.maintenance' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
		if ( function_exists( 'wp_clean_plugins_cache' ) ) {
			wp_clean_plugins_cache( false );
		}
		delete_site_transient( 'update_plugins' );
	}

	public static function install_plugin( $slug, $activate = true ) {
		if ( ! current_user_can( 'install_plugins' ) ) {
			return array( 'ok' => false, 'slug' => $slug, 'message' => 'You can\'t install plugins.' );
		}
		$slug = sanitize_key( $slug );
		if ( '' === $slug ) {
			return array( 'ok' => false, 'slug' => $slug, 'message' => 'Empty slug.' );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/misc.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		if ( ! class_exists( 'WP_Ajax_Upgrader_Skin' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-ajax-upgrader-skin.php';
		}

		// Already installed? Just (optionally) activate it.
		$installed = self::find_installed( $slug );
		if ( $installed ) {
			if ( $activate && ! is_plugin_active( $installed ) ) {
				$act = activate_plugin( $installed );
				if ( is_wp_error( $act ) ) {
					return array( 'ok' => true, 'slug' => $slug, 'status' => 'installed', 'message' => 'Already installed (activation failed).' );
				}
				return array( 'ok' => true, 'slug' => $slug, 'status' => 'activated', 'message' => 'Already installed — activated.' );
			}
			return array( 'ok' => true, 'slug' => $slug, 'status' => 'installed', 'message' => 'Already installed.' );
		}

		$info = plugins_api( 'plugin_information', array( 'slug' => $slug, 'fields' => array( 'sections' => false ) ) );
		if ( is_wp_error( $info ) || empty( $info->download_link ) ) {
			return array( 'ok' => false, 'slug' => $slug, 'message' => 'Not found on WordPress.org.' );
		}

		self::prep_install();
		$skin     = new WP_Ajax_Upgrader_Skin();
		$upgrader = new Plugin_Upgrader( $skin );
		try {
			$result = $upgrader->install( $info->download_link );
		} catch ( \Throwable $e ) {
			return array( 'ok' => false, 'slug' => $slug, 'message' => 'Install error: ' . $e->getMessage() );
		}

		if ( is_wp_error( $result ) ) {
			return array( 'ok' => false, 'slug' => $slug, 'message' => $result->get_error_message() );
		}
		if ( ! $result ) {
			$errs = $skin->get_errors();
			$msg  = ( is_wp_error( $errs ) && $errs->get_error_message() ) ? $errs->get_error_message() : 'Install failed.';
			return array( 'ok' => false, 'slug' => $slug, 'message' => $msg );
		}

		$file = $upgrader->plugin_info();
		if ( $activate && $file ) {
			$act = activate_plugin( $file );
			if ( is_wp_error( $act ) ) {
				return array( 'ok' => true, 'slug' => $slug, 'status' => 'installed', 'message' => 'Installed (activation failed).' );
			}
			return array( 'ok' => true, 'slug' => $slug, 'status' => 'activated', 'message' => 'Installed & activated.' );
		}
		return array( 'ok' => true, 'slug' => $slug, 'status' => 'installed', 'message' => 'Installed.' );
	}

	/**
	 * Install from any source: a wordpress.org slug, a wordpress.org plugin URL,
	 * or a direct .zip download URL. Slugs go through the API; URLs install directly.
	 */
	public static function install_source( $source, $activate = true ) {
		$source = trim( (string) $source );
		if ( '' === $source ) {
			return array( 'ok' => false, 'slug' => $source, 'message' => 'Empty line.' );
		}
		if ( preg_match( '#^https?://#i', $source ) ) {
			// A wordpress.org plugin page → pull the slug and use the clean API path.
			if ( preg_match( '#wordpress\.org/plugins/([a-z0-9\-]+)#i', $source, $m ) ) {
				return self::install_plugin( $m[1], $activate );
			}
			// Otherwise treat it as a direct package URL.
			return self::install_package( esc_url_raw( $source ), $activate, self::source_label( $source ) );
		}
		// Bare slug.
		return self::install_plugin( $source, $activate );
	}

	private static function source_label( $url ) {
		$base = basename( wp_parse_url( $url, PHP_URL_PATH ) );
		return $base ? $base : $url;
	}

	/** Shared install-from-package routine (URL or local zip path). */
	public static function install_package( $package, $activate, $label ) {
		if ( ! current_user_can( 'install_plugins' ) ) {
			return array( 'ok' => false, 'slug' => $label, 'message' => 'You can\'t install plugins.' );
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/misc.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		if ( ! class_exists( 'WP_Ajax_Upgrader_Skin' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-ajax-upgrader-skin.php';
		}
		self::prep_install();
		$skin     = new WP_Ajax_Upgrader_Skin();
		$upgrader = new Plugin_Upgrader( $skin );
		try {
			$result = $upgrader->install( $package );
		} catch ( \Throwable $e ) {
			return array( 'ok' => false, 'slug' => $label, 'message' => 'Install error: ' . $e->getMessage() );
		}

		if ( is_wp_error( $result ) ) {
			return array( 'ok' => false, 'slug' => $label, 'message' => $result->get_error_message() );
		}
		if ( ! $result ) {
			$errs = $skin->get_errors();
			$msg  = ( is_wp_error( $errs ) && $errs->get_error_message() ) ? $errs->get_error_message() : 'Install failed.';
			return array( 'ok' => false, 'slug' => $label, 'message' => $msg );
		}
		$file = $upgrader->plugin_info();
		if ( $activate && $file ) {
			$act = activate_plugin( $file );
			if ( is_wp_error( $act ) ) {
				return array( 'ok' => true, 'slug' => $label, 'status' => 'installed', 'message' => 'Installed (activation failed).' );
			}
			return array( 'ok' => true, 'slug' => $label, 'status' => 'activated', 'message' => 'Installed & activated.' );
		}
		return array( 'ok' => true, 'slug' => $label, 'status' => 'installed', 'message' => 'Installed.' );
	}

	/** Install an uploaded plugin .zip from the user's computer. */
	public static function install_zip( $file, $activate = true ) {
		if ( ! current_user_can( 'install_plugins' ) ) {
			return array( 'ok' => false, 'slug' => 'upload', 'message' => 'You can\'t install plugins.' );
		}
		if ( empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			return array( 'ok' => false, 'slug' => 'upload', 'message' => 'No file received.' );
		}
		$name = isset( $file['name'] ) ? sanitize_file_name( $file['name'] ) : 'plugin.zip';
		if ( ! preg_match( '/\.zip$/i', $name ) ) {
			return array( 'ok' => false, 'slug' => $name, 'message' => 'Only .zip plugin files are allowed.' );
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		$tmp = wp_tempnam( $name );
		if ( ! $tmp || ! @move_uploaded_file( $file['tmp_name'], $tmp ) ) {
			// Fallback for environments where move_uploaded_file is restricted.
			if ( $tmp ) {
				@copy( $file['tmp_name'], $tmp );
			}
		}
		if ( ! $tmp || ! file_exists( $tmp ) ) {
			return array( 'ok' => false, 'slug' => $name, 'message' => 'Could not stage the upload.' );
		}
		$res = self::install_package( $tmp, $activate, $name );
		@unlink( $tmp );
		return $res;
	}

	private static function find_installed( $slug ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		foreach ( array_keys( get_plugins() ) as $file ) {
			if ( dirname( $file ) === $slug || $file === $slug . '.php' || 0 === strpos( $file, $slug . '/' ) ) {
				return $file;
			}
		}
		return '';
	}

	public static function blueprints() {
		$bp = get_option( 'velox_blueprints', array() );
		return is_array( $bp ) ? $bp : array();
	}

	public static function save_blueprint( $name, $slugs ) {
		$name = sanitize_text_field( $name );
		if ( '' === $name ) {
			return array( 'ok' => false, 'message' => 'Give the blueprint a name.' );
		}
		$slugs = array_values( array_unique( array_filter( array_map( 'sanitize_key', (array) $slugs ) ) ) );
		if ( empty( $slugs ) ) {
			return array( 'ok' => false, 'message' => 'No plugin slugs to save.' );
		}
		$bp          = self::blueprints();
		$bp[ $name ] = $slugs;
		update_option( 'velox_blueprints', $bp );
		return array( 'ok' => true, 'name' => $name, 'slugs' => $slugs );
	}

	public static function delete_blueprint( $name ) {
		$bp = self::blueprints();
		unset( $bp[ sanitize_text_field( $name ) ] );
		update_option( 'velox_blueprints', $bp );
		return array( 'ok' => true );
	}
}
