<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Velox Builder — front-end renderer.
 *
 * Turns a stored document model (the same tree + classes + content shape the
 * editor uses) into the real page a visitor sees:
 *
 *   • generates the HTML from the node tree,
 *   • compiles the class rules + element overrides into CSS — byte-for-byte the
 *     same output the editor's live CSS shows — and writes it to a STATIC file
 *     under uploads/velox/builder/ so only the CSS the page uses ever ships,
 *   • serves the page via `template_include`, outputting its own standalone
 *     document (wp_head / wp_footer for compatibility) without the theme.
 *
 * This is the payoff of the whole approach: clean markup, only-used CSS, no
 * theme cruft — the thing that keeps Core Web Vitals green.
 */
class Velox_Builder_Render {

	/** camelCase model keys → real CSS properties (mirrors the editor). */
	private static $CSS_PROP = array(
		'display' => 'display', 'flexDirection' => 'flex-direction', 'flexWrap' => 'flex-wrap', 'alignItems' => 'align-items', 'justifyContent' => 'justify-content', 'gap' => 'gap',
		'paddingTop' => 'padding-top', 'paddingRight' => 'padding-right', 'paddingBottom' => 'padding-bottom', 'paddingLeft' => 'padding-left',
		'marginTop' => 'margin-top', 'marginRight' => 'margin-right', 'marginBottom' => 'margin-bottom', 'marginLeft' => 'margin-left',
		'width' => 'width', 'maxWidth' => 'max-width', 'height' => 'height', 'minHeight' => 'min-height',
		'fontSize' => 'font-size', 'fontWeight' => 'font-weight', 'lineHeight' => 'line-height', 'letterSpacing' => 'letter-spacing', 'textAlign' => 'text-align', 'textDecoration' => 'text-decoration', 'textTransform' => 'text-transform',
		'color' => 'color', 'background' => 'background', 'opacity' => 'opacity',
		'borderWidth' => 'border-width', 'borderStyle' => 'border-style', 'borderColor' => 'border-color', 'borderRadius' => 'border-radius',
		'boxShadow' => 'box-shadow', 'gridTemplateColumns' => 'grid-template-columns',
	);
	private static $UNIT = array( 'gap', 'paddingTop', 'paddingRight', 'paddingBottom', 'paddingLeft', 'marginTop', 'marginRight', 'marginBottom', 'marginLeft', 'width', 'maxWidth', 'height', 'minHeight', 'fontSize', 'letterSpacing', 'borderWidth', 'borderRadius' );
	private static $BP = array(
		'base'   => null,
		'tablet' => '(max-width: 991px)',
		'mobile' => '(max-width: 767px)',
	);

	public static function init() {
		add_action( 'template_include', array( __CLASS__, 'maybe_render' ), 99 );
	}

	/* -------------------------------------------------- serving a page */

	/**
	 * If the current singular post is linked to a Velox Builder document, take
	 * over rendering: output our standalone template instead of the theme's.
	 */
	public static function maybe_render( $template ) {
		if ( ! is_singular() ) {
			return $template;
		}
		$post_id = get_queried_object_id();
		$doc     = self::doc_for_post( $post_id );
		if ( ! $doc ) {
			return $template;
		}

		// Serve our own standalone document and stop the theme entirely.
		self::output_page( $doc );
		exit;
	}

	/** Find a published document bound to this post, if any. */
	private static function doc_for_post( $post_id ) {
		global $wpdb;
		$t   = Velox_Builder::table();
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT id, title, data FROM {$t} WHERE post_id = %d AND status = 'published' LIMIT 1", $post_id ), ARRAY_A );
		if ( ! $row ) {
			return null;
		}
		$model = json_decode( $row['data'], true );
		if ( ! is_array( $model ) || empty( $model['tree'] ) ) {
			return null;
		}
		$model['__id']    = (int) $row['id'];
		$model['__title'] = $row['title'];
		return $model;
	}

	/** Print the full standalone HTML document. */
	private static function output_page( $doc ) {
		$roles  = class_exists( 'Velox_Builder' ) ? Velox_Builder::roles() : array( 'header' => 0, 'footer' => 0 );
		$header = $roles['header'] ? Velox_Builder::doc_model( $roles['header'] ) : null;
		$footer = $roles['footer'] ? Velox_Builder::doc_model( $roles['footer'] ) : null;

		// Build the effective CSS from the page + any reusables it references +
		// the active header/footer templates, so every class the visitor can see
		// is covered by exactly one stylesheet.
		$css_url = self::ensure_css_file( $doc, array( $header, $footer ) );

		$body  = '';
		$body .= $header ? '<header class="velox-template-header">' . self::render_tree( $header['tree'], $header ) . '</header>' : '';
		$body .= self::render_tree( $doc['tree'], $doc );
		$body .= $footer ? '<footer class="velox-template-footer">' . self::render_tree( $footer['tree'], $footer ) . '</footer>' : '';

		header( 'Content-Type: text/html; charset=' . get_bloginfo( 'charset' ) );
		?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php if ( $css_url ) : ?>
		<link rel="stylesheet" id="velox-builder-css" href="<?php echo esc_url( $css_url ); ?>">
	<?php endif; ?>
	<?php self::print_global_head(); ?>
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'velox-built' ); ?>>
	<?php echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — built from sanitized model ?>
	<?php wp_footer(); ?>
</body>
</html>
		<?php
	}

	/** Global design tokens (as CSS variables) + registered fonts, in <head>. */
	public static function print_global_head() {
		if ( ! class_exists( 'Velox_Builder' ) ) {
			return;
		}
		foreach ( Velox_Builder::fonts() as $f ) {
			if ( 'google' === $f['type'] && ! empty( $f['name'] ) ) {
				$fam = str_replace( ' ', '+', $f['name'] );
				echo '<link rel="stylesheet" href="' . esc_url( 'https://fonts.googleapis.com/css2?family=' . $fam . ':wght@400;600;700;800&display=swap' ) . '">' . "\n";
			} elseif ( 'url' === $f['type'] && ! empty( $f['url'] ) ) {
				echo '<link rel="stylesheet" href="' . esc_url( $f['url'] ) . '">' . "\n";
			}
		}
		$tokens = Velox_Builder::tokens();
		$vars   = '';
		foreach ( (array) ( $tokens['colors'] ?? array() ) as $c ) {
			$name = preg_replace( '/[^a-z0-9\-]/', '', strtolower( $c['name'] ) );
			$val  = self::sanitize_value( (string) $c['value'] );
			if ( $name && '' !== $val ) {
				$vars .= '--' . $name . ':' . $val . ';';
			}
		}
		$i = 0;
		foreach ( (array) ( $tokens['spacing'] ?? array() ) as $s ) {
			$s = preg_replace( '/[^0-9.]/', '', (string) $s );
			if ( '' !== $s ) {
				$vars .= '--space-' . $i . ':' . $s . 'px;';
				$i++;
			}
		}
		if ( '' !== $vars ) {
			echo '<style id="velox-builder-tokens">:root{' . $vars . '}</style>' . "\n"; // phpcs:ignore
		}
		// Global CSS files (apply to every Velox page).
		$gcss = Velox_Builder::global_css();
		if ( '' !== trim( $gcss ) ) {
			echo '<style id="velox-builder-global-css">' . $gcss . '</style>' . "\n"; // phpcs:ignore
		}
	}

	/* -------------------------------------------------- HTML generation */

	/** Recursively render the node tree to HTML (mirrors the editor's genHTML). */
	public static function render_tree( $nodes, $doc ) {
		$out = '';
		foreach ( (array) $nodes as $node ) {
			$out .= self::render_node( $node, $doc );
		}
		return $out;
	}

	private static function render_node( $node, $doc ) {
		$tag = preg_replace( '/[^a-z0-9]/', '', strtolower( $node['tag'] ?? 'div' ) );
		if ( '' === $tag ) {
			$tag = 'div';
		}
		$classes = array();
		foreach ( (array) ( $node['classes'] ?? array() ) as $c ) {
			$classes[] = sanitize_html_class( ltrim( $c, '.' ) );
		}
		$id   = sanitize_html_class( $node['id'] ?? '' );
		$attr = ' id="' . esc_attr( $id ) . '"';
		if ( $classes ) {
			$attr .= ' class="' . esc_attr( implode( ' ', $classes ) ) . '"';
		}

		// Reusable element: pull the referenced reusable document and render its
		// tree inline (by reference — editing the reusable updates every page).
		if ( isset( $node['el'] ) && 'Reusable' === $node['el'] ) {
			$ref = isset( $node['ref'] ) ? (int) $node['ref'] : 0;
			if ( ! $ref || ! class_exists( 'Velox_Builder' ) ) {
				return '';
			}
			$reuse = Velox_Builder::doc_model( $ref );
			if ( ! $reuse || empty( $reuse['tree'] ) ) {
				return '';
			}
			return '<div' . $attr . ' data-velox-reusable="' . $ref . '">' . self::render_tree( $reuse['tree'], $reuse ) . '</div>';
		}

		// Image element: emit a real <img> from the stored URL (empty = nothing).
		if ( isset( $node['el'] ) && 'Image' === $node['el'] ) {
			$src = isset( $doc['content'][ $node['id'] ] ) ? esc_url( $doc['content'][ $node['id'] ] ) : '';
			$img = $src ? '<img src="' . $src . '" alt="" style="display:block;max-width:100%;height:auto">' : '';
			return '<div' . $attr . '>' . $img . '</div>';
		}

		// Google Reviews: render the plugin's real reviews via its shortcode,
		// using the connection + preset chosen in the element settings.
		if ( isset( $node['el'] ) && 'Reviews' === $node['el'] ) {
			$conn   = isset( $node['conn'] ) ? sanitize_key( $node['conn'] ) : '';
			$preset = isset( $node['preset'] ) ? sanitize_key( $node['preset'] ) : '';
			if ( ! $conn || ! shortcode_exists( 'velox_reviews' ) ) {
				return '<div' . $attr . '></div>';
			}
			$sc = '[velox_reviews connection="' . esc_attr( $conn ) . '" preset="' . esc_attr( $preset ) . '"]';
			return '<div' . $attr . '>' . do_shortcode( $sc ) . '</div>';
		}

		// WordPress-data elements: pull live data from the bound/current post.
		if ( isset( $node['el'] ) && 'WP' === $node['el'] ) {
			return self::render_wp_node( $node, $attr, $tag );
		}

		$content = isset( $doc['content'][ $node['id'] ] ) ? wp_kses_post( self::resolve_tokens( $doc['content'][ $node['id'] ] ) ) : '';
		$kids    = self::render_tree( $node['children'] ?? array(), $doc );
		if ( 'a' === $tag ) {
			$href  = isset( $node['href'] ) ? esc_url( $node['href'] ) : '#';
			$attr .= ' href="' . $href . '"';
			if ( ! empty( $node['target'] ) && '_blank' === $node['target'] ) {
				$attr .= ' target="_blank" rel="noopener"';
			}
		}
		return '<' . $tag . $attr . '>' . $content . $kids . '</' . $tag . '>';
	}

	/**
	 * Replace dynamic-data token spans (<span data-vx="post.title">…</span>) with
	 * live WordPress values. Unknown/unsupported tokens are left blank.
	 */
	public static function resolve_tokens( $html ) {
		if ( false === strpos( $html, 'data-vx' ) ) {
			return $html;
		}
		return preg_replace_callback(
			'/<span[^>]*data-vx="([^"]+)"[^>]*>.*?<\/span>/is',
			function ( $m ) {
				return self::token_value( html_entity_decode( $m[1] ) );
			},
			$html
		);
	}

	private static function token_value( $token ) {
		$arg = '';
		if ( false !== strpos( $token, ':' ) ) {
			list( $token, $arg ) = array_pad( explode( ':', $token, 2 ), 2, '' );
		}
		$pid = get_the_ID();
		switch ( $token ) {
			case 'post.title':    return esc_html( get_the_title( $pid ) );
			case 'post.content':  return apply_filters( 'the_content', get_post_field( 'post_content', $pid ) );
			case 'post.excerpt':  return esc_html( get_the_excerpt( $pid ) );
			case 'post.date':     return esc_html( get_the_date( '', $pid ) );
			case 'post.id':       return (string) $pid;
			case 'post.type':     return esc_html( get_post_type( $pid ) );
			case 'post.comments': return (string) get_comments_number( $pid );
			case 'post.meta':     return $arg ? esc_html( get_post_meta( $pid, $arg, true ) ) : '';
			case 'post.terms':    $t = get_the_category_list( ', ', '', $pid ); return $t ? $t : '';
			case 'post.taxterms': return $arg ? strip_tags( get_the_term_list( $pid, $arg, '', ', ' ) ) : '';
			case 'featured.title':   return esc_html( get_the_title( get_post_thumbnail_id( $pid ) ) );
			case 'featured.caption': return esc_html( wp_get_attachment_caption( get_post_thumbnail_id( $pid ) ) );
			case 'featured.alt':     return esc_html( get_post_meta( get_post_thumbnail_id( $pid ), '_wp_attachment_image_alt', true ) );
			case 'author.name':   return esc_html( get_the_author_meta( 'display_name', (int) get_post_field( 'post_author', $pid ) ) );
			case 'author.bio':    return esc_html( get_the_author_meta( 'description', (int) get_post_field( 'post_author', $pid ) ) );
			case 'author.meta':   return $arg ? esc_html( get_the_author_meta( $arg, (int) get_post_field( 'post_author', $pid ) ) ) : '';
			case 'user.name':     $u = wp_get_current_user(); return $u ? esc_html( $u->display_name ) : '';
			case 'user.bio':      $u = wp_get_current_user(); return $u ? esc_html( $u->description ) : '';
			case 'user.meta':     $u = wp_get_current_user(); return ( $u && $arg ) ? esc_html( get_user_meta( $u->ID, $arg, true ) ) : '';
			case 'site.title':    return esc_html( get_bloginfo( 'name' ) );
			case 'site.tagline':  return esc_html( get_bloginfo( 'description' ) );
			case 'site.other':    return $arg ? esc_html( get_bloginfo( $arg ) ) : '';
			case 'archive.title':       return esc_html( wp_strip_all_tags( get_the_archive_title() ) );
			case 'archive.description': return wp_kses_post( get_the_archive_description() );
			// Advanced / not resolved server-side (security): leave a visible marker.
			case 'php.return': return '';
			default:           return '';
		}
	}

	/** Render a WordPress-data element (post title / content / featured / menu). */
	private static function render_wp_node( $node, $attr, $tag ) {
		$kind = isset( $node['wp'] ) ? $node['wp'] : '';
		$pid  = get_the_ID();
		switch ( $kind ) {
			case 'title':
				return '<' . $tag . $attr . '>' . esc_html( get_the_title( $pid ) ) . '</' . $tag . '>';
			case 'content':
				return '<div' . $attr . '>' . apply_filters( 'the_content', get_post_field( 'post_content', $pid ) ) . '</div>';
			case 'featured':
				$img = $pid ? get_the_post_thumbnail( $pid, 'large', array( 'style' => 'display:block;max-width:100%;height:auto' ) ) : '';
				return '<div' . $attr . '>' . $img . '</div>';
			case 'menu':
				$nav = wp_nav_menu( array( 'echo' => false, 'container' => false, 'fallback_cb' => false ) );
				return '<nav' . $attr . '>' . ( $nav ? $nav : '' ) . '</nav>';
			default:
				return '<' . $tag . $attr . '></' . $tag . '>';
		}
	}

	/* -------------------------------------------------- CSS generation */

	/** Compile CSS for the page plus any referenced reusables and extra models
	 *  (e.g. header/footer templates), so one stylesheet covers everything the
	 *  visitor sees. $extra is an array of additional decoded models (or nulls). */
	public static function generate_css( $doc, $extra = array() ) {
		// Gather every contributing model: the page, the reusables it references,
		// and any extra models passed in (header/footer templates).
		$models = array( $doc );
		foreach ( self::collect_reusable_ids( $doc['tree'] ?? array() ) as $rid ) {
			if ( class_exists( 'Velox_Builder' ) ) {
				$rm = Velox_Builder::doc_model( $rid );
				if ( $rm ) {
					$models[] = $rm;
				}
			}
		}
		foreach ( (array) $extra as $em ) {
			if ( is_array( $em ) ) {
				$models[] = $em;
				foreach ( self::collect_reusable_ids( $em['tree'] ?? array() ) as $rid2 ) {
					if ( class_exists( 'Velox_Builder' ) ) {
						$rm2 = Velox_Builder::doc_model( $rid2 );
						if ( $rm2 ) {
							$models[] = $rm2;
						}
					}
				}
			}
		}

		// Merge class maps (later models don't override earlier same-named classes;
		// first definition wins, which keeps the page authoritative).
		$classes = array();
		$trees   = array();
		foreach ( $models as $m ) {
			if ( ! empty( $m['classes'] ) && is_array( $m['classes'] ) ) {
				foreach ( $m['classes'] as $cls => $rules ) {
					if ( ! isset( $classes[ $cls ] ) ) {
						$classes[ $cls ] = $rules;
					}
				}
			}
			if ( ! empty( $m['tree'] ) ) {
				$trees[] = $m['tree'];
			}
		}

		$out    = '';
		$states = array( 'normal', 'hover', 'focus' );
		foreach ( self::$BP as $bp => $mq ) {
			$body = '';
			foreach ( $states as $state ) {
				$key    = 'normal' === $state ? $bp : $bp . ':' . $state;
				$pseudo = 'normal' === $state ? '' : ':' . $state;
				foreach ( $classes as $cls => $byKey ) {
					if ( empty( $byKey[ $key ] ) || ! is_array( $byKey[ $key ] ) ) {
						continue;
					}
					$body .= self::escape_selector( $cls ) . $pseudo . '{' . self::decls( $byKey[ $key ] ) . '}';
				}
				foreach ( $trees as $tree ) {
					self::walk(
						$tree,
						function ( $node ) use ( &$body, $key, $pseudo ) {
							if ( ! empty( $node['overrides'][ $key ] ) && is_array( $node['overrides'][ $key ] ) ) {
								$body .= '#' . sanitize_html_class( $node['id'] ) . $pseudo . '{' . self::decls( $node['overrides'][ $key ] ) . '}';
							}
						}
					);
				}
			}
			if ( '' === $body ) {
				continue;
			}
			$out .= $mq ? '@media ' . $mq . '{' . $body . '}' : $body;
		}
		return $out;
	}

	/** Collect the ids of every reusable referenced anywhere in a tree. */
	private static function collect_reusable_ids( $nodes ) {
		$ids = array();
		self::walk(
			$nodes,
			function ( $node ) use ( &$ids ) {
				if ( isset( $node['el'] ) && 'Reusable' === $node['el'] && ! empty( $node['ref'] ) ) {
					$ids[] = (int) $node['ref'];
				}
			}
		);
		return array_unique( $ids );
	}

	private static function decls( $rules ) {
		$out = '';
		foreach ( (array) $rules as $prop => $val ) {
			if ( ! isset( self::$CSS_PROP[ $prop ] ) ) {
				continue; // only known properties reach CSS — no injection surface
			}
			$kebab = self::$CSS_PROP[ $prop ];
			$val   = self::sanitize_value( (string) $val );
			if ( '' === $val ) {
				continue;
			}
			if ( in_array( $prop, self::$UNIT, true ) && preg_match( '/^-?\d+(\.\d+)?$/', $val ) ) {
				$val .= 'px';
			}
			$out .= $kebab . ':' . $val . ';';
		}
		return $out;
	}

	/** Values are model-authored but still scrubbed of anything that could break out. */
	private static function sanitize_value( $v ) {
		$v = trim( $v );
		// Strip anything that could break out of the declaration/rule.
		$v = preg_replace( '/[{}<>;]/', '', $v );
		if ( preg_match( '/(expression|javascript:|@import|url\s*\()/i', $v ) ) {
			return '';
		}
		return $v;
	}

	private static function escape_selector( $cls ) {
		// classes only (leading dot) — strip to a safe class token
		$name = sanitize_html_class( ltrim( $cls, '.' ) );
		return '.' . $name;
	}

	private static function walk( $nodes, $fn ) {
		foreach ( (array) $nodes as $node ) {
			$fn( $node );
			if ( ! empty( $node['children'] ) ) {
				self::walk( $node['children'], $fn );
			}
		}
	}

	/* -------------------------------------------------- static CSS file */

	/**
	 * Write the page CSS to a static file and return its URL. Regenerated when
	 * the document changes (filename carries the doc id; content is hashed into
	 * a query arg for cache-busting). Falls back to inline if the dir isn't
	 * writable.
	 */
	public static function ensure_css_file( $doc, $extra = array() ) {
		$css = self::generate_css( $doc, $extra );
		$up  = wp_upload_dir();
		if ( ! empty( $up['error'] ) ) {
			return self::inline_fallback( $css );
		}
		$dir = trailingslashit( $up['basedir'] ) . 'velox/builder';
		if ( ! wp_mkdir_p( $dir ) ) {
			return self::inline_fallback( $css );
		}
		$id   = (int) ( $doc['__id'] ?? 0 );
		$file = $dir . '/doc-' . $id . '.css';
		$hash = substr( md5( $css ), 0, 8 );

		// Only rewrite if content changed.
		if ( ! file_exists( $file ) || md5_file( $file ) !== md5( $css ) ) {
			if ( false === file_put_contents( $file, $css ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
				return self::inline_fallback( $css );
			}
		}
		return trailingslashit( $up['baseurl'] ) . 'velox/builder/doc-' . $id . '.css?v=' . $hash;
	}

	/** When we can't write a file, echo the CSS inline instead (still only-used). */
	private static $inline = '';
	private static function inline_fallback( $css ) {
		self::$inline = $css;
		add_action( 'wp_head', array( __CLASS__, 'print_inline' ), 5 );
		return '';
	}
	public static function print_inline() {
		if ( '' !== self::$inline ) {
			echo '<style id="velox-builder-inline">' . self::$inline . '</style>'; // phpcs:ignore
		}
	}

	/** Public helper: (re)write the CSS file for a doc after save. */
	public static function write_css_for( $doc_id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT id, data FROM ' . Velox_Builder::table() . ' WHERE id = %d', $doc_id ), ARRAY_A );
		if ( ! $row ) {
			return false;
		}
		$model = json_decode( $row['data'], true );
		if ( ! is_array( $model ) ) {
			return false;
		}
		$model['__id'] = (int) $row['id'];
		return self::ensure_css_file( $model );
	}
}
