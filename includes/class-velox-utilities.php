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
			'installer'  => array( 'label' => 'Bulk installer',      'icon' => 'plug',     'ready' => false, 'desc' => 'Install a saved stack of plugins on a fresh site in one go, all or one by one.' ),
			'redirects'  => array( 'label' => 'Redirects & 404s',    'icon' => 'redirect', 'ready' => false, 'desc' => 'Log 404s and turn any of them into a redirect; auto-redirect on permalink changes.' ),
			'mail'       => array( 'label' => 'Mail & forms',        'icon' => 'mail',     'ready' => false, 'desc' => 'Build and style forms, send through SMTP, with consent checkbox and CAPTCHA.' ),
			'unusedmedia'=> array( 'label' => 'Unused media',        'icon' => 'broom',    'ready' => false, 'desc' => 'Find media files nothing in your content references, and clean them out.' ),
			'loginurl'   => array( 'label' => 'Custom login URL',    'icon' => 'lock',     'ready' => false, 'desc' => 'Move wp-login to a custom path to cut brute-force bot traffic.' ),
			'maintenance'=> array( 'label' => 'Maintenance mode',    'icon' => 'cone',     'ready' => false, 'desc' => 'Show visitors a branded coming-soon page while you work, admins still get in.' ),
			'activity'   => array( 'label' => 'Activity log',        'icon' => 'list',     'ready' => false, 'desc' => 'A simple audit trail of who changed what across the site.' ),
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
}
