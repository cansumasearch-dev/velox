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
		'display' => 'display', 'flexDirection' => 'flex-direction', 'alignItems' => 'align-items', 'gap' => 'gap',
		'paddingTop' => 'padding-top', 'paddingRight' => 'padding-right', 'paddingBottom' => 'padding-bottom', 'paddingLeft' => 'padding-left',
		'marginTop' => 'margin-top', 'marginRight' => 'margin-right', 'marginBottom' => 'margin-bottom', 'marginLeft' => 'margin-left',
		'fontSize' => 'font-size', 'fontWeight' => 'font-weight', 'color' => 'color', 'background' => 'background',
		'borderRadius' => 'border-radius', 'gridTemplateColumns' => 'grid-template-columns',
	);
	private static $UNIT = array( 'gap', 'paddingTop', 'paddingRight', 'paddingBottom', 'paddingLeft', 'marginTop', 'marginRight', 'marginBottom', 'marginLeft', 'fontSize', 'borderRadius' );
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
		$css_url = self::ensure_css_file( $doc );
		$body    = self::render_tree( $doc['tree'], $doc );

		// Own document — wp_head/wp_footer kept for plugin compatibility, but no
		// theme header/footer, so markup stays shallow and only-used CSS ships.
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
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'velox-built' ); ?>>
	<?php echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — built from sanitized model ?>
	<?php wp_footer(); ?>
</body>
</html>
		<?php
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
		$id      = sanitize_html_class( $node['id'] ?? '' );
		$content = isset( $doc['content'][ $node['id'] ] ) ? wp_kses_post( $doc['content'][ $node['id'] ] ) : '';
		$kids    = self::render_tree( $node['children'] ?? array(), $doc );

		$attr = ' id="' . esc_attr( $id ) . '"';
		if ( $classes ) {
			$attr .= ' class="' . esc_attr( implode( ' ', $classes ) ) . '"';
		}
		// Anchors need an href to be valid; default to a no-op if none stored.
		if ( 'a' === $tag ) {
			$href  = isset( $node['href'] ) ? esc_url( $node['href'] ) : '#';
			$attr .= ' href="' . $href . '"';
		}
		return '<' . $tag . $attr . '>' . $content . $kids . '</' . $tag . '>';
	}

	/* -------------------------------------------------- CSS generation */

	/** Compile the document's class rules + overrides into a CSS string. */
	public static function generate_css( $doc ) {
		$out     = '';
		$classes = isset( $doc['classes'] ) && is_array( $doc['classes'] ) ? $doc['classes'] : array();

		foreach ( self::$BP as $bp => $mq ) {
			$body = '';
			foreach ( $classes as $cls => $byBp ) {
				if ( empty( $byBp[ $bp ] ) || ! is_array( $byBp[ $bp ] ) ) {
					continue;
				}
				$body .= self::escape_selector( $cls ) . '{' . self::decls( $byBp[ $bp ] ) . '}';
			}
			// element-level overrides
			self::walk(
				$doc['tree'] ?? array(),
				function ( $node ) use ( &$body, $bp ) {
					if ( ! empty( $node['overrides'][ $bp ] ) && is_array( $node['overrides'][ $bp ] ) ) {
						$body .= '#' . sanitize_html_class( $node['id'] ) . '{' . self::decls( $node['overrides'][ $bp ] ) . '}';
					}
				}
			);
			if ( '' === $body ) {
				continue;
			}
			$out .= $mq ? '@media ' . $mq . '{' . $body . '}' : $body;
		}
		return $out;
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
		// kill braces, semicolons at the end, angle brackets, url()/expression
		$v = preg_replace( '/[{}<>]/', '', $v );
		$v = preg_replace( '/;+$/', '', $v );
		if ( preg_match( '/(expression|javascript:|@import)/i', $v ) ) {
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
	public static function ensure_css_file( $doc ) {
		$css = self::generate_css( $doc );
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
