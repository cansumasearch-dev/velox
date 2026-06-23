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
			'svg'        => array( 'label' => 'SVG uploads',        'icon' => 'file',     'ready' => true,  'setting' => 'util_svg_upload', 'desc' => 'Allow SVG files in the media library, sanitised on upload so they can\'t carry scripts.' ),
			'duplicate'  => array( 'label' => 'Duplicate post/page', 'icon' => 'copy',     'ready' => true,  'setting' => 'util_duplicate',  'desc' => 'Adds a one-click "Duplicate" link to every post and page so you can clone one as a draft.' ),
			'media'      => array( 'label' => 'Media Editor',        'icon' => 'tag',      'ready' => true,  'setting' => 'module_media', 'link' => 'media', 'desc' => 'Bulk-edit alt text and titles in a grid, rename files safely, and browse your whole library.' ),
			'installer'  => array( 'label' => 'Bulk installer',      'icon' => 'plug',     'ready' => true,  'page' => true, 'desc' => 'Install a saved stack of plugins on a fresh site in one go, all or one by one.' ),
			'redirects'  => array( 'label' => 'Redirects & 404s',    'icon' => 'redirect', 'ready' => true,  'page' => true, 'desc' => 'Log 404s and turn any of them into a redirect; auto-redirect on permalink changes.' ),
			'mail'       => array( 'label' => 'Mail & forms',        'icon' => 'mail',     'ready' => true,  'page' => true, 'desc' => 'Build and style forms, send through SMTP, with consent checkbox and CAPTCHA.' ),
			'unusedmedia'=> array( 'label' => 'Unused media',        'icon' => 'broom',    'ready' => true,  'page' => true, 'desc' => 'Find media files nothing in your content references, and clean them out.' ),
			'loginurl'   => array( 'label' => 'Custom login URL',    'icon' => 'lock',     'ready' => true,  'page' => true, 'desc' => 'Move wp-login to a custom path to cut brute-force bot traffic.' ),
			'maintenance'=> array( 'label' => 'Maintenance mode',    'icon' => 'cone',     'ready' => true,  'page' => true, 'desc' => 'Show visitors a branded coming-soon page while you work, admins still get in.' ),
			'activity'   => array( 'label' => 'Activity log',        'icon' => 'list',     'ready' => true,  'page' => true, 'desc' => 'A simple audit trail of who changed what across the site.' ),
			'scripts'    => array( 'label' => 'Script Manager',      'icon' => 'code',     'ready' => true,  'page' => true, 'desc' => 'Stop specific CSS/JS from loading where it isn\'t needed — globally or per page.' ),
		);
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
		// Maintenance mode (front end only — wp-admin/login stay reachable)
		if ( Velox_Settings::get( 'util_maintenance' ) ) {
			add_action( 'template_redirect', array( __CLASS__, 'maybe_maintenance' ) );
		}
		// Custom login URL
		if ( '' !== self::login_slug() ) {
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
		$title = Velox_Settings::get( 'util_maintenance_title' );
		$msg   = Velox_Settings::get( 'util_maintenance_message' );
		$title = $title ? $title : 'We\'ll be right back';
		$msg   = $msg ? $msg : 'The site is undergoing maintenance. Please check back soon.';

		nocache_headers();
		status_header( 503 );
		header( 'Retry-After: 3600' );
		header( 'Content-Type: text/html; charset=utf-8' );
		echo self::maintenance_html( $title, $msg ); // phpcs:ignore
		exit;
	}

	private static function maintenance_html( $title, $msg ) {
		$t    = esc_html( $title );
		$m    = nl2br( esc_html( $msg ) );
		$name = esc_html( get_bloginfo( 'name' ) );
		return '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
			. '<meta name="viewport" content="width=device-width,initial-scale=1">'
			. '<meta name="robots" content="noindex,nofollow">'
			. '<title>' . $t . ' — ' . $name . '</title><style>'
			. '*{box-sizing:border-box}body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;'
			. 'font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:#0c0e17;color:#e9edf5;padding:24px}'
			. '.box{max-width:540px;text-align:center}.dot{width:54px;height:54px;border-radius:50%;background:#2ab7f1;'
			. 'margin:0 auto 26px;animation:p 1.6s ease-in-out infinite}@keyframes p{0%,100%{opacity:.35;transform:scale(.92)}50%{opacity:1;transform:scale(1)}}'
			. 'h1{font-size:30px;margin:0 0 14px;letter-spacing:-.02em}p{font-size:16px;line-height:1.6;color:#aab2c5;margin:0}'
			. '.brand{margin-top:30px;font-size:13px;color:#566}</style></head><body><div class="box">'
			. '<div class="dot"></div><h1>' . $t . '</h1><p>' . $m . '</p>'
			. '<div class="brand">' . $name . '</div></div></body></html>';
	}

	/* ---------------------------------------------------------------------
	 * Custom login URL  (hides wp-login.php behind a custom slug)
	 * ------------------------------------------------------------------- */

	public static function login_slug() {
		return trim( (string) Velox_Settings::get( 'util_login_slug' ), '/' );
	}

	public static function login_init() {
		add_action( 'plugins_loaded', array( __CLASS__, 'login_intercept' ), 1 );
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

	public static function login_intercept() {
		if ( ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) || ( defined( 'DOING_CRON' ) && DOING_CRON ) ) {
			return;
		}
		$slug = self::login_slug();
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
		$path = trim( (string) wp_parse_url( $uri, PHP_URL_PATH ), '/' );

		// Visiting the secret slug → serve the real login page.
		if ( $path === $slug && '' !== $slug ) {
			global $pagenow;
			$pagenow = 'wp-login.php';
			require_once ABSPATH . 'wp-login.php';
			exit;
		}
		// Hide the default wp-login.php / wp-register.php from logged-out visitors.
		if ( ! is_user_logged_in()
			&& ( false !== strpos( $uri, 'wp-login.php' ) || false !== strpos( $uri, 'wp-register.php' ) ) ) {
			wp_safe_redirect( home_url( '/' ), 302 );
			exit;
		}
	}

	public static function filter_login_url( $url ) {
		return is_string( $url ) ? str_replace( 'wp-login.php', self::login_slug(), $url ) : $url;
	}

	public static function filter_site_url( $url ) {
		if ( is_string( $url ) && false !== strpos( $url, 'wp-login.php' ) ) {
			$url = str_replace( 'wp-login.php', self::login_slug(), $url );
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
		$ids = get_posts( array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'post_mime_type' => 'image',
			'posts_per_page' => (int) $limit,
			'fields'         => 'ids',
			'orderby'        => 'date',
			'order'          => 'ASC',
		) );
		$out = array();
		foreach ( $ids as $id ) {
			if ( self::is_referenced( $id ) ) {
				continue;
			}
			$file = get_attached_file( $id );
			$out[] = array(
				'id'    => $id,
				'title' => get_the_title( $id ),
				'url'   => wp_get_attachment_url( $id ),
				'thumb' => self::thumb_url( $id ),
				'bytes' => ( $file && file_exists( $file ) ) ? (int) filesize( $file ) : 0,
			);
		}
		return $out;
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
		// Referenced in any post/page content?
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
		return (bool) $in_meta;
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

		$skin     = new WP_Ajax_Upgrader_Skin();
		$upgrader = new Plugin_Upgrader( $skin );
		$result   = $upgrader->install( $info->download_link );

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
