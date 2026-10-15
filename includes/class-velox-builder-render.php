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
	/** True while building only the CSS for the editor preview. */
	private static $css_only = false;
	/** Reusables being rendered right now (recursion guard). */
	private static $reuse_stack = array();
	/** Whether a Video or Icon element is on the page. */
	private static $media_used = false;
	private static $CSS_PROP = array(
		'display' => 'display', 'flexDirection' => 'flex-direction', 'flexWrap' => 'flex-wrap', 'alignItems' => 'align-items', 'justifyContent' => 'justify-content', 'gap' => 'gap',
		'paddingTop' => 'padding-top', 'paddingRight' => 'padding-right', 'paddingBottom' => 'padding-bottom', 'paddingLeft' => 'padding-left',
		'marginTop' => 'margin-top', 'marginRight' => 'margin-right', 'marginBottom' => 'margin-bottom', 'marginLeft' => 'margin-left',
		'width' => 'width', 'minWidth' => 'min-width', 'maxWidth' => 'max-width', 'height' => 'height', 'minHeight' => 'min-height', 'maxHeight' => 'max-height',
		'fontSize' => 'font-size', 'fontWeight' => 'font-weight', 'lineHeight' => 'line-height', 'letterSpacing' => 'letter-spacing', 'textAlign' => 'text-align', 'textDecoration' => 'text-decoration', 'textTransform' => 'text-transform',
		'color' => 'color', 'background' => 'background', 'opacity' => 'opacity',
		'borderWidth' => 'border-width', 'borderStyle' => 'border-style', 'borderColor' => 'border-color', 'borderRadius' => 'border-radius',
		'boxShadow' => 'box-shadow', 'gridTemplateColumns' => 'grid-template-columns',
		// Mirrors the editor. Absent here, any preset or class rule using one of
		// these was silently discarded when the page was built.
		'padding' => 'padding', 'margin' => 'margin', 'position' => 'position',
		'zIndex' => 'z-index', 'overflow' => 'overflow', 'aspectRatio' => 'aspect-ratio',
		'objectFit' => 'object-fit', 'alignSelf' => 'align-self', 'justifySelf' => 'justify-self',
		'fontFamily' => 'font-family', 'fontStyle' => 'font-style', 'whiteSpace' => 'white-space',
		'textWrap' => 'text-wrap', 'gridColumn' => 'grid-column', 'gridRow' => 'grid-row',
	);
	private static $UNIT = array( 'gap', 'paddingTop', 'paddingRight', 'paddingBottom', 'paddingLeft', 'marginTop', 'marginRight', 'marginBottom', 'marginLeft', 'width', 'minWidth', 'maxWidth', 'height', 'minHeight', 'maxHeight', 'fontSize', 'letterSpacing', 'borderWidth', 'borderRadius', 'padding', 'margin' );
	/** Media queries, built from the editable breakpoints. */
	private static function bp_map() {
		$b = class_exists( 'Velox_Builder' ) ? Velox_Builder::breakpoints() : array();
		$d = array( 'xxl' => 1399.98, 'xl' => 1199.98, 'lg' => 991.98, 'md' => 767.98, 'sm' => 575.98 );
		foreach ( $d as $k => $v ) {
			if ( ! isset( $b[ $k ] ) ) {
				$b[ $k ] = $v;
			}
		}
		$mq = function ( $k ) use ( $b ) {
			return '(max-width: ' . rtrim( rtrim( number_format( (float) $b[ $k ], 2, '.', '' ), '0' ), '.' ) . 'px)';
		};
		// Legacy keys ('tablet'/'mobile') alias lg/md and are emitted immediately
		// BEFORE their modern twin, so a value saved on the new key wins on source
		// order. Documents saved before the Bootstrap 5 switch keep rendering.
		return array(
			'base'   => null,
			'xxl'    => $mq( 'xxl' ),
			'xl'     => $mq( 'xl' ),
			'tablet' => $mq( 'lg' ),
			'lg'     => $mq( 'lg' ),
			'mobile' => $mq( 'md' ),
			'md'     => $mq( 'md' ),
			'sm'     => $mq( 'sm' ),
		);
	}

	/** Breakpoint key → media query, for the Classes screen's CSS editor. */
	public static function media_queries() {
		return self::bp_map();
	}

	/** camelCase model key → CSS property name (used by the class editor). */
	public static function css_prop_name( $key ) {
		return self::$CSS_PROP[ $key ] ?? '';
	}

	/** CSS property name → camelCase model key (the reverse lookup). */
	public static function model_prop_name( $css ) {
		$flip = array_flip( self::$CSS_PROP );
		return $flip[ strtolower( trim( $css ) ) ] ?? '';
	}

	/** Value as it should appear in CSS text (adds px to bare numbers). */
	public static function css_value( $key, $value ) {
		$v = (string) $value;
		if ( in_array( $key, self::$UNIT, true ) && preg_match( '/^-?\\d+(\\.\\d+)?$/', $v ) ) {
			$v .= 'px';
		}
		return $v;
	}

	public static function init() {
		add_action( 'template_include', array( __CLASS__, 'maybe_render' ), 99 );
	}

	/* -------------------------------------------------- serving a page */

	/**
	 * If the current singular post is linked to a Velox Builder document, take
	 * over rendering: output our standalone template instead of the theme's.
	 */
	public static function maybe_render( $template ) {
		// ?preview=true is just a query string anyone can add; only someone who
		// may edit the content gets to see unpublished drafts.
		self::$previewing = is_preview() && is_user_logged_in()
			&& ( is_singular() ? current_user_can( 'edit_post', get_queried_object_id() ) : current_user_can( 'manage_options' ) );
		if ( ! is_singular() ) {
			// Templates made for 404, Search or Archives were offered on the
			// Templates screen but never used — only single pages were handled.
			$ctx = self::context_doc();
			if ( ! $ctx ) {
				return $template;
			}
			self::output_page( $ctx );
			exit;
		}
		$post_id = get_queried_object_id();
		// A password-protected page was rendered in full to anyone. Leave it to the
		// theme, which shows WordPress's password form.
		if ( post_password_required( $post_id ) ) {
			return $template;
		}
		$doc     = self::doc_for_post( $post_id );

		if ( ! $doc ) {
			// No Velox layout on this page. If catch-all is on and a template with
			// an Inner Content slot applies, wrap the post's own content in it —
			// otherwise leave the page to the theme, untouched.
			$legacy = self::legacy_doc_for_post( $post_id );
			if ( ! $legacy ) {
				return $template;
			}
			$doc = $legacy;
		}

		// Serve our own standalone document and stop the theme entirely.
		self::output_page( $doc );
		exit;
	}

	/** True while rendering a preview (?preview=true): drafts are shown. */
	private static $previewing = false;

	/**
	 * A stand-in document for a 404, search or archive screen: the template made
	 * for it, with a list of posts (or a not-found message) in its Inner Content.
	 */
	private static function context_doc() {
		if ( ! class_exists( 'Velox_Builder' ) || is_feed() || is_embed() || is_admin() ) {
			return null;
		}
		if ( is_404() ) {
			$ctx = array( 'error404' );
		} elseif ( is_search() ) {
			$ctx = array( 'search', 'archive' );
		} elseif ( is_front_page() && is_home() ) {
			$ctx = array( 'front_page', 'archive' );
		} elseif ( is_home() || is_category() || is_tag() || is_date() || is_author() || is_post_type_archive( 'post' ) ) {
			$ctx = array( 'archive' );
		} else {
			// Shops, product categories and other custom archives have their own
			// templates (prices, add to cart…) — a blog post list would break them.
			return null;
		}
		if ( function_exists( 'is_woocommerce' ) && ( is_woocommerce() || is_cart() || is_checkout() || is_account_page() ) ) {
			return null;
		}
		if ( ! apply_filters( 'velox_builder_context_template', true, $ctx[0] ) ) {
			return null;
		}
		$tpl = Velox_Builder::template_for_context( $ctx );
		// Catch-all templates only take over these screens when "wrap pages
		// without a Velox layout" is on — the same rule as for plain pages.
		if ( ! $tpl && Velox_Builder::wrap_legacy() ) {
			$tpl = Velox_Builder::template_for_context( array( 'catch_all' ) );
		}
		if ( ! $tpl ) {
			return null;
		}
		self::$loop_used = true;
		return array(
			'tree'    => array(),
			'classes' => array(),
			'content' => array(),
			'__id'    => 0,
			'__file'  => '0-' . $ctx[0] . '-' . $tpl,
			'__tpl'   => $tpl,
			'__title' => wp_get_document_title(),
			'__html'  => self::loop_html( $ctx[0] ),
		);
	}

	/** The posts of the current archive / search, or the not-found message. */
	private static function loop_html( $ctx ) {
		if ( 'error404' === $ctx ) {
			return '<div class="velox-404"><h1>' . esc_html__( 'Page not found', 'velox' ) . '</h1>'
				. '<p>' . esc_html__( 'The page you are looking for does not exist or has moved.', 'velox' ) . '</p>'
				. get_search_form( array( 'echo' => false ) ) . '</div>';
		}
		$head = '';
		$desc = '';
		if ( is_search() ) {
			/* translators: %s: what was searched for. */
			$head = esc_html( sprintf( __( 'Search results for “%s”', 'velox' ), get_search_query() ) );
		} elseif ( is_archive() ) {
			$head = wp_kses_post( get_the_archive_title() );
			$desc = wp_kses_post( get_the_archive_description() );
		}
		$out = '<div class="velox-loop">';
		if ( '' !== $head ) {
			$out .= '<header class="velox-loop-head"><h1>' . $head . '</h1>' . ( '' !== $desc ? '<div class="velox-loop-desc">' . $desc . '</div>' : '' ) . '</header>';
		}
		if ( have_posts() ) {
			$out .= '<div class="velox-loop-items">';
			while ( have_posts() ) {
				the_post();
				$link = esc_url( get_permalink() );
				$out .= '<article class="velox-loop-item">';
				if ( has_post_thumbnail() ) {
					$out .= '<a class="velox-loop-img" href="' . $link . '" tabindex="-1" aria-hidden="true">' . get_the_post_thumbnail( null, 'medium_large' ) . '</a>';
				}
				$out .= '<h2 class="velox-loop-title"><a href="' . $link . '">' . esc_html( get_the_title() ) . '</a></h2>'
					. '<div class="velox-loop-meta">' . esc_html( get_the_date() ) . '</div>'
					. '<div class="velox-loop-excerpt">' . wp_kses_post( get_the_excerpt() ) . '</div>'
					. '</article>';
			}
			$out .= '</div>';
			rewind_posts();
			$pages = paginate_links( array( 'type' => 'list', 'prev_text' => '←', 'next_text' => '→' ) );
			if ( $pages ) {
				$out .= '<nav class="velox-loop-pages" aria-label="' . esc_attr__( 'More pages', 'velox' ) . '">' . $pages . '</nav>';
			}
		} else {
			$out .= '<p class="velox-loop-none">' . esc_html__( 'Nothing found.', 'velox' ) . '</p>'
				. ( is_search() ? get_search_form( array( 'echo' => false ) ) : '' );
		}
		return $out . '</div>';
	}

	/** Whether the archive/search list is on the page (for its base CSS). */
	private static $loop_used = false;

	/** Find a published document bound to this post, if any. */
	private static function doc_for_post( $post_id ) {
		global $wpdb;
		$t = Velox_Builder::table();
		// Previewing a revision hands us the revision id, not the page's — the
		// document is bound to the parent, so resolve that first.
		$parent = wp_is_post_revision( $post_id );
		if ( $parent ) {
			$post_id = (int) $parent;
		}
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT id, title, data FROM {$t} WHERE post_id = %d AND status = 'published' AND kind = 'page' LIMIT 1", $post_id ), ARRAY_A );
		// Fall back to the binding stored on the post itself. The two can drift
		// apart (a doc duplicated, a page restored from trash), and without this
		// the visitor silently gets the theme with no clue why.
		if ( ! $row ) {
			$meta_doc = (int) get_post_meta( $post_id, '_velox_builder_doc', true );
			if ( $meta_doc ) {
				$row = $wpdb->get_row( $wpdb->prepare( "SELECT id, title, data FROM {$t} WHERE id = %d AND status = 'published' AND kind = 'page' LIMIT 1", $meta_doc ), ARRAY_A );
			}
		}
		// Logged-in editors previewing see the latest SAVED version — the draft
		// of a published page, or a layout that was never published.
		if ( is_preview() && current_user_can( 'edit_post', $post_id ) ) {
			$prev = $wpdb->get_row( $wpdb->prepare( "SELECT id, title, COALESCE( NULLIF( draft, '' ), data ) AS data FROM {$t} WHERE post_id = %d AND kind = 'page' LIMIT 1", $post_id ), ARRAY_A );
			if ( $prev ) {
				$row = $prev;
			}
		}
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


	/**
	 * Why is (or isn't) this post served by Velox? Returns a short human answer
	 * for the page editor, so a blank front end stops being a guessing game.
	 *
	 * @return array{live:bool,reason:string}
	 */
	public static function render_status( $post_id ) {
		global $wpdb;
		$t   = Velox_Builder::table();
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT id, status, post_id, kind FROM {$t} WHERE post_id = %d LIMIT 1", (int) $post_id ), ARRAY_A );
		if ( ! $row ) {
			$meta_doc = (int) get_post_meta( $post_id, '_velox_builder_doc', true );
			if ( $meta_doc ) {
				$row = $wpdb->get_row( $wpdb->prepare( "SELECT id, status, post_id, kind FROM {$t} WHERE id = %d LIMIT 1", $meta_doc ), ARRAY_A );
			}
		}
		if ( ! $row ) {
			// No layout of its own — but catch-all may still be wrapping it. Ask the
			// SAME function the front end uses, so this can never disagree with what
			// a visitor actually gets.
			if ( self::legacy_doc_for_post( $post_id ) ) {
				return array( 'live' => true, 'reason' => __( 'Velox is serving this page: your default template wraps it and the page content sits in the Inner Content slot.', 'velox' ) );
			}
			if ( class_exists( 'Velox_Builder' ) && ! Velox_Builder::wrap_legacy() ) {
				return array( 'live' => false, 'reason' => __( 'No Velox layout on this page, and templates are not set to wrap pages without one — so WordPress renders it with your theme.', 'velox' ) );
			}
			return array( 'live' => false, 'reason' => __( 'No Velox layout is attached to this page yet, so WordPress renders it with your theme.', 'velox' ) );
		}
		if ( 'published' !== $row['status'] ) {
			return array( 'live' => false, 'reason' => __( 'This page has a Velox layout but it is still a draft. Open the builder and press Publish to put it live — until then visitors see the theme.', 'velox' ) );
		}
		if ( 'page' !== $row['kind'] ) {
			return array( 'live' => false, 'reason' => __( 'The document attached to this page is saved as a Template or Reusable, not a Page, so it is not served at this URL. Change its type on the Velox Builder overview.', 'velox' ) );
		}
		return array( 'live' => true, 'reason' => __( 'Velox is serving this page — your theme is bypassed entirely.', 'velox' ) );
	}


	/**
	 * A stand-in "document" for a page that has no Velox layout: an empty tree
	 * plus the post's own content, so a template can frame it. Returns null
	 * unless catch-all is enabled AND a template with a slot actually applies —
	 * without a slot the content would have nowhere to go.
	 */
	private static function legacy_doc_for_post( $post_id ) {
		if ( ! class_exists( 'Velox_Builder' ) || ! Velox_Builder::wrap_legacy() ) {
			return null;
		}
		$choice = get_post_meta( $post_id, '_velox_template', true );
		if ( '-1' === (string) $choice ) {
			return null; // explicitly opted out
		}
		$post = get_post( $post_id );
		// Only pages and posts: products, attachments and other post types have
		// their own templates that a plain content wrapper would break.
		if ( ! $post || ! in_array( $post->post_type, (array) apply_filters( 'velox_builder_wrap_post_types', array( 'page', 'post' ) ), true ) ) {
			return null;
		}
		if ( function_exists( 'is_woocommerce' ) && ( is_cart() || is_checkout() || is_account_page() ) ) {
			return null;
		}
		$tpl_id = Velox_Builder::template_for_post( $post_id );
		if ( ! $tpl_id || ! Velox_Builder::template_has_inner( $tpl_id ) ) {
			return null;
		}
		return array(
			'tree'      => array(),
			'classes'   => array(),
			'content'   => array(),
			'__id'      => 0,
			'__file'    => '0-p' . (int) $post_id,
			'__title'   => $post->post_title,
			'__html'    => apply_filters( 'the_content', $post->post_content ),
		);
	}


	/** Elements needing the runtime, collected while rendering this page. */
	private static $runtime_used = array();

	/** A node's saved settings, with the element's defaults filled in. */
	private static function el_settings( $node ) {
		return isset( $node['settings'] ) && is_array( $node['settings'] ) ? $node['settings'] : array();
	}
	/**
	 * A setting resolved per breakpoint. Base keeps its plain key (so documents
	 * written before responsive settings existed read back unchanged); narrower
	 * breakpoints store under 'key@bp'. Returns only the breakpoints that carry
	 * an explicit value — the caller emits a media query for each, and the CSS
	 * cascade does the inheriting rather than us repeating values.
	 */
	/**
	 * The five text-effect presets. Each is named after something CSS can do but
	 * none of them shipped the CSS to do it — a "Gradient text" element rendered
	 * as plain bold text. The effects need properties with vendor prefixes and a
	 * pseudo-element, which the ordinary style controls cannot express, so they
	 * are written here from the element's own settings instead.
	 */
	private static function text_effect_css( $node ) {
		$id  = sanitize_html_class( $node['id'] ?? '' );
		if ( '' === $id ) {
			return;
		}
		$sel = '#' . $id;
		$el  = $node['el'];

		if ( 'Gradienttext' === $el ) {
			$from  = self::el_color( $node, 'from', '#2ab7f1' );
			$to    = self::el_color( $node, 'to', '#7b5cff' );
			$angle = (int) self::el_set( $node, 'angle', 90 );
			self::$float_css .= $sel . '{background-image:linear-gradient(' . $angle . 'deg,'
				. esc_attr( $from ) . ',' . esc_attr( $to ) . ');'
				. '-webkit-background-clip:text;background-clip:text;'
				. 'color:transparent;-webkit-text-fill-color:transparent;'
				. 'display:inline-block}';
			return;
		}

		if ( 'Outlinetext' === $el ) {
			$w    = self::el_set_bp( $node, 'strokeW', 2 );
			$col  = self::el_color( $node, 'strokeC', '#0b0f14' );
			$fill = self::sanitize_value( (string) self::el_set( $node, 'fill', '' ) );
			$mqs  = self::bp_map();
			foreach ( array( 'base', 'xxl', 'xl', 'lg', 'md', 'sm' ) as $bp ) {
				if ( ! isset( $w[ $bp ] ) ) {
					continue;
				}
				$rule = $sel . '{-webkit-text-stroke:' . (float) $w[ $bp ] . 'px ' . esc_attr( $col )
					. ';text-stroke:' . (float) $w[ $bp ] . 'px ' . esc_attr( $col ) . '}';
				$mq = $mqs[ $bp ] ?? null;
				self::$float_css .= $mq ? ( '@media ' . $mq . '{' . $rule . '}' ) : $rule;
			}
			// An empty fill is the classic hollow look; a colour keeps it filled.
			self::$float_css .= $sel . '{color:' . ( '' !== $fill ? esc_attr( $fill ) : 'transparent' )
				. ';-webkit-text-fill-color:' . ( '' !== $fill ? esc_attr( $fill ) : 'transparent' ) . '}';
			return;
		}

		if ( 'Highlighttext' === $el ) {
			$c     = self::el_color( $node, 'hlColor', '#ffe066' );
			$style = self::el_set( $node, 'hlStyle', 'full' );
			if ( 'underline' === $style ) {
				self::$float_css .= $sel . '{background:linear-gradient(' . esc_attr( $c ) . ',' . esc_attr( $c )
					. ') 0 88%/100% 34% no-repeat;color:inherit}';
			} elseif ( 'marker' === $style ) {
				self::$float_css .= $sel . '{background:linear-gradient(104deg,transparent .5%,'
					. esc_attr( $c ) . ' 2%,' . esc_attr( $c ) . ' 96%,transparent 98%);'
					. 'color:inherit;border-radius:2px;padding:2px 6px;box-decoration-break:clone;'
					. '-webkit-box-decoration-break:clone}';
			} else {
				self::$float_css .= $sel . '{background:' . esc_attr( $c ) . ';color:inherit}';
			}
			return;
		}

		if ( 'Dropcap' === $el ) {
			$lines = self::el_set_bp( $node, 'capLines', 3 );
			$col   = self::sanitize_value( (string) self::el_set( $node, 'capColor', '' ) );
			$wt    = self::el_set( $node, 'capWeight', '700' );
			$mqs   = self::bp_map();
			foreach ( array( 'base', 'xxl', 'xl', 'lg', 'md', 'sm' ) as $bp ) {
				if ( ! isset( $lines[ $bp ] ) ) {
					continue;
				}
				$n    = max( 2, (int) $lines[ $bp ] );
				// Sized in em against the paragraph so it scales with the text.
				$rule = $sel . '::first-letter{float:left;font-size:' . $n . 'em;line-height:.85;'
					. 'padding:2px 8px 0 0;font-weight:' . (int) $wt
					. ( '' !== $col ? ';color:' . esc_attr( $col ) : '' ) . '}';
				$mq = $mqs[ $bp ] ?? null;
				self::$float_css .= $mq ? ( '@media ' . $mq . '{' . $rule . '}' ) : $rule;
			}
			return;
		}

		if ( 'Verticaltext' === $el ) {
			$up = 'up' === self::el_set( $node, 'vDir', 'down' );
			self::$float_css .= $sel . '{writing-mode:vertical-rl;'
				. ( $up ? 'transform:rotate(180deg);' : '' )
				. 'white-space:nowrap;display:inline-block}';
		}
	}

	/**
	 * Utility presets that, like the text effects, were named after behaviour they
	 * never implemented: screen-reader text that was not actually hidden, a skip
	 * link that never appeared on focus, an anchor that scrolled under a sticky
	 * header, avatars that did not overlap and a skeleton that did not shimmer.
	 * None of it is expressible through the ordinary style controls.
	 */
	private static function utility_effect_css( $node ) {
		$id = sanitize_html_class( $node['id'] ?? '' );
		if ( '' === $id ) {
			return;
		}
		$sel = '#' . $id;
		$el  = $node['el'];
		$mqs = self::bp_map();
		$bps = array( 'base', 'xxl', 'xl', 'lg', 'md', 'sm' );

		if ( 'Sronly' === $el ) {
			// The standard visually-hidden recipe: off-canvas positioning alone
			// (which is all this shipped with) still leaves the text selectable
			// and can create stray scrollbars.
			self::$float_css .= $sel . '{position:absolute!important;width:1px;height:1px;padding:0;margin:-1px;'
				. 'overflow:hidden;clip:rect(0,0,0,0);clip-path:inset(50%);white-space:nowrap;border:0}';
			if ( self::el_set( $node, 'srFocus', '' ) ) {
				self::$float_css .= $sel . ':focus,' . $sel . ':focus-within{position:static!important;width:auto;'
					. 'height:auto;margin:0;overflow:visible;clip:auto;clip-path:none;white-space:normal}';
			}
			return;
		}

		if ( 'Skiplink' === $el ) {
			$bg  = self::el_color( $node, 'skBg', '#0b0f14' );
			$fg  = self::el_color( $node, 'skFg', '#ffffff' );
			$top = self::el_int( $node, 'skTop', 8 );
			self::$float_css .= $sel . '{position:absolute;left:8px;top:-64px;z-index:1000;'
				. 'padding:10px 16px;border-radius:6px;background:' . esc_attr( $bg ) . ';color:' . esc_attr( $fg )
				. ';text-decoration:none;transition:top .15s}'
				. $sel . ':focus{top:' . $top . 'px;outline:2px solid ' . esc_attr( $fg ) . ';outline-offset:2px}';
			return;
		}

		if ( 'Anchortarget' === $el ) {
			$off = self::el_set_bp( $node, 'anOffset', 80 );
			foreach ( $bps as $bp ) {
				if ( ! isset( $off[ $bp ] ) ) {
					continue;
				}
				$rule = $sel . '{display:block;scroll-margin-top:' . (int) $off[ $bp ] . 'px}';
				$mq   = $mqs[ $bp ] ?? null;
				self::$float_css .= $mq ? ( '@media ' . $mq . '{' . $rule . '}' ) : $rule;
			}
			return;
		}

		if ( 'Avatargroup' === $el ) {
			$ring  = self::el_color( $node, 'avRing', '#ffffff' );
			$ringW = (int) self::el_set( $node, 'avRingW', 2 );
			$last  = 'last' === self::el_set( $node, 'avOrder', 'first' );
			$size  = self::el_set_bp( $node, 'avSize', 40 );
			$over  = self::el_set_bp( $node, 'avOverlap', 12 );
			foreach ( $bps as $bp ) {
				if ( ! isset( $size[ $bp ] ) && ! isset( $over[ $bp ] ) ) {
					continue;
				}
				$sz = (int) ( $size[ $bp ] ?? self::el_set( $node, 'avSize', 40 ) );
				$ov = (int) ( $over[ $bp ] ?? self::el_set( $node, 'avOverlap', 12 ) );
				$rule = $sel . '>*{width:' . $sz . 'px;height:' . $sz . 'px;border-radius:50%;overflow:hidden;'
					. 'flex:0 0 auto;box-shadow:0 0 0 ' . $ringW . 'px ' . esc_attr( $ring ) . '}'
					. $sel . '>*+*{margin-left:-' . $ov . 'px}'
					. $sel . '>* img{width:100%;height:100%;object-fit:cover;display:block}';
				$mq = $mqs[ $bp ] ?? null;
				self::$float_css .= $mq ? ( '@media ' . $mq . '{' . $rule . '}' ) : $rule;
			}
			// Later avatars paint on top by default. "Front avatar is the first one"
			// reverses the stacking only — reversing the row as well put the faces
			// in the wrong order and broke the overlap between the first two.
			self::$float_css .= $sel . '{isolation:isolate}' . $sel . '>*{position:relative}';
			if ( ! $last ) {
				for ( $i = 1; $i <= 12; $i++ ) {
					self::$float_css .= $sel . '>*:nth-child(' . $i . '){z-index:' . ( 13 - $i ) . '}';
				}
			}
			return;
		}

		if ( 'Skeleton' === $el ) {
			if ( ! self::el_set( $node, 'skShimmer', '1' ) ) {
				return;
			}
			$ms = max( 300, (int) self::el_set( $node, 'skSpeed', 1400 ) );
			$hi = self::el_color( $node, 'skHi', '#f6f8fb' );
			self::$float_css .= $sel . '{background-image:linear-gradient(90deg,transparent,' . esc_attr( $hi )
				. ',transparent);background-size:200% 100%;background-repeat:no-repeat;'
				. 'animation:vx-shimmer ' . $ms . 'ms linear infinite}'
				. '@keyframes vx-shimmer{from{background-position:-150% 0}to{background-position:250% 0}}'
				// Anything that loops forever has to stop for a visitor who asked
				// for less motion.
				. '@media (prefers-reduced-motion:reduce){' . $sel . '{animation:none}}';
		}
	}

	/**
	 * The grid layouts. Column counts were only reachable as a raw
	 * grid-template-columns string, which meant no per-screen control at all —
	 * a four-column stats row stayed four columns on a phone. These write the
	 * real grid per breakpoint from a plain "how many columns" number.
	 */
	private static function layout_effect_css( $node ) {
		$id = sanitize_html_class( $node['id'] ?? '' );
		if ( '' === $id ) {
			return;
		}
		$sel = '#' . $id;
		$el  = $node['el'];
		$mqs = self::bp_map();
		$bps = array( 'base', 'xxl', 'xl', 'lg', 'md', 'sm' );
		$emit = function ( $bp, $rule ) use ( $mqs ) {
			$mq = $mqs[ $bp ] ?? null;
			self::$float_css .= $mq ? ( '@media ' . $mq . '{' . $rule . '}' ) : $rule;
		};
		// A breakpoint with no value of its own inherits the nearest wider one.
		$carry = function ( $vals, $bp, $fallback ) use ( $bps ) {
			$upto = array_slice( $bps, 0, array_search( $bp, $bps, true ) + 1 );
			foreach ( array_reverse( $upto ) as $b ) {
				if ( isset( $vals[ $b ] ) && '' !== $vals[ $b ] ) {
					return $vals[ $b ];
				}
			}
			return $fallback;
		};

		if ( in_array( $el, array( 'Bento', 'Statsrow', 'Featuregrid' ), true ) ) {
			$defc  = 'Statsrow' === $el ? 4 : 3;
			$defg  = 'Bento' === $el ? 16 : 24;
			$cols  = self::el_set_bp( $node, 'cols', $defc );
			$gaps  = self::el_set_bp( $node, 'gGap', $defg );
			$minc  = (int) self::el_set( $node, 'minCol', 0 );
			$align = self::el_set( $node, 'gAlign', 'stretch' );
			foreach ( $bps as $bp ) {
				if ( ! isset( $cols[ $bp ] ) && ! isset( $gaps[ $bp ] ) ) {
					continue;
				}
				$c = max( 1, (int) $carry( $cols, $bp, $defc ) );
				$g = (int) $carry( $gaps, $bp, $defg );
				// With a minimum column width the grid wraps on its own and the
				// column count becomes a ceiling rather than a fixed number.
				$tpl = $minc
					? 'repeat(auto-fit,minmax(min(' . $minc . 'px,100%),1fr))'
					: 'repeat(' . $c . ',minmax(0,1fr))';
				$emit( $bp, $sel . '{display:grid;grid-template-columns:' . $tpl . ';gap:' . $g . 'px}' );
			}
			if ( in_array( $align, array( 'start', 'center' ), true ) ) {
				self::$float_css .= $sel . '{align-items:' . $align . '}';
			}
			return;
		}

		if ( 'Splitscreen' === $el ) {
			$ratio = self::el_set_bp( $node, 'spRatio', '1fr 1fr' );
			$gaps  = self::el_set_bp( $node, 'spGap', 0 );
			$stack = self::el_set( $node, 'spStack', 'md' );
			foreach ( $bps as $bp ) {
				if ( ! isset( $ratio[ $bp ] ) && ! isset( $gaps[ $bp ] ) ) {
					continue;
				}
				$r = (string) $carry( $ratio, $bp, '1fr 1fr' );
				$r = preg_match( '/^[0-9fr\s.]+$/', $r ) ? $r : '1fr 1fr';
				$emit( $bp, $sel . '{display:grid;grid-template-columns:' . $r . ';gap:'
					. (int) $carry( $gaps, $bp, 0 ) . 'px}' );
			}
			if ( 'never' !== $stack && isset( $mqs[ $stack ] ) ) {
				self::$float_css .= '@media ' . $mqs[ $stack ] . '{' . $sel . '{grid-template-columns:1fr}}';
			}
			return;
		}

		if ( 'Sidebarlayout' === $el ) {
			$w     = self::el_set_bp( $node, 'sbWidth', 300 );
			$gaps  = self::el_set_bp( $node, 'sbGap', 32 );
			$left  = 'left' === self::el_set( $node, 'sbSide', 'right' );
			$stack = self::el_set( $node, 'sbStack', 'md' );
			foreach ( $bps as $bp ) {
				if ( ! isset( $w[ $bp ] ) && ! isset( $gaps[ $bp ] ) ) {
					continue;
				}
				$px  = (int) $carry( $w, $bp, 300 );
				$tpl = $left ? ( $px . 'px minmax(0,1fr)' ) : ( 'minmax(0,1fr) ' . $px . 'px' );
				$emit( $bp, $sel . '{display:grid;grid-template-columns:' . $tpl . ';gap:'
					. (int) $carry( $gaps, $bp, 32 ) . 'px}' );
			}
			// The sidebar is the second child in the markup either way, so putting
			// it on the left is an order change, not a different DOM.
			if ( $left ) {
				self::$float_css .= $sel . '>*:first-child{order:2}' . $sel . '>*:nth-child(2){order:1}';
			}
			if ( 'never' !== $stack && isset( $mqs[ $stack ] ) ) {
				self::$float_css .= '@media ' . $mqs[ $stack ] . '{' . $sel . '{grid-template-columns:1fr}'
					. $sel . '>*{order:0}}';
			}
			return;
		}

		if ( 'Logostrip' === $el ) {
			$h    = self::el_set_bp( $node, 'lsHeight', 32 );
			$gaps = self::el_set_bp( $node, 'lsGap', 40 );
			foreach ( $bps as $bp ) {
				if ( ! isset( $h[ $bp ] ) && ! isset( $gaps[ $bp ] ) ) {
					continue;
				}
				$emit( $bp, $sel . '{gap:' . (int) $carry( $gaps, $bp, 40 ) . 'px}'
					. $sel . ' img{height:' . (int) $carry( $h, $bp, 32 ) . 'px;width:auto;display:block}' );
			}
			if ( self::el_set( $node, 'lsGrey', '1' ) ) {
				$op = max( 0, min( 100, (int) self::el_set( $node, 'lsFade', 55 ) ) ) / 100;
				self::$float_css .= $sel . ' img{filter:grayscale(1);opacity:' . $op . ';transition:filter .2s,opacity .2s}'
					. $sel . ' img:hover{filter:grayscale(0);opacity:1}';
			}
		}
	}

	/**
	 * The last of the presets. Small by design: each of these only needed the one
	 * or two things the ordinary style controls genuinely cannot express — a
	 * ratio picked from a list, a row of badges forced to a common height, a
	 * quotation mark or rule drawn with a pseudo-element.
	 */
	private static function trim_effect_css( $node ) {
		$id = sanitize_html_class( $node['id'] ?? '' );
		if ( '' === $id ) {
			return;
		}
		$sel = '#' . $id;
		$el  = $node['el'];
		$mqs = self::bp_map();
		$bps = array( 'base', 'xxl', 'xl', 'lg', 'md', 'sm' );
		$emit = function ( $bp, $rule ) use ( $mqs ) {
			$mq = $mqs[ $bp ] ?? null;
			self::$float_css .= $mq ? ( '@media ' . $mq . '{' . $rule . '}' ) : $rule;
		};

		if ( 'Ratiobox' === $el ) {
			$fit  = 'contain' === self::el_set( $node, 'arFit', 'cover' ) ? 'contain' : 'cover';
			$vals = self::el_set_bp( $node, 'arRatio', '16/9' );
			foreach ( $bps as $bp ) {
				if ( ! isset( $vals[ $bp ] ) ) {
					continue;
				}
				$r = (string) $vals[ $bp ];
				if ( 'custom' === $r ) {
					$r = trim( (string) self::el_set( $node, 'arCustom', '' ) );
				}
				// Only ever "number / number" reaches the stylesheet.
				if ( ! preg_match( '#^\s*\d+(\.\d+)?\s*/\s*\d+(\.\d+)?\s*$#', $r ) ) {
					$r = '16/9';
				}
				$emit( $bp, $sel . '{aspect-ratio:' . str_replace( ' ', '', $r ) . ';overflow:hidden}' );
			}
			self::$float_css .= $sel . '>*,' . $sel . ' img,' . $sel . ' video,' . $sel . ' iframe'
				. '{width:100%;height:100%;object-fit:' . $fit . ';display:block}';
			return;
		}

		if ( 'Trustrow' === $el || 'Paymenticons' === $el ) {
			$defH  = 'Trustrow' === $el ? 28 : 24;
			$defG  = 'Trustrow' === $el ? 24 : 12;
			$h     = self::el_set_bp( $node, 'bgHeight', $defH );
			$g     = self::el_set_bp( $node, 'bgGap', $defG );
			$align = self::el_set( $node, 'bgAlign', 'start' );
			foreach ( $bps as $bp ) {
				if ( ! isset( $h[ $bp ] ) && ! isset( $g[ $bp ] ) ) {
					continue;
				}
				// A breakpoint that only changes one of the two inherits the other
				// from the nearest wider screen, not the element default.
				$hh = $defH;
				$gg = $defG;
				foreach ( array_slice( $bps, 0, array_search( $bp, $bps, true ) + 1 ) as $b ) {
					if ( isset( $h[ $b ] ) && '' !== (string) $h[ $b ] ) { $hh = (int) $h[ $b ]; }
					if ( isset( $g[ $b ] ) && '' !== (string) $g[ $b ] ) { $gg = (int) $g[ $b ]; }
				}
				// A common height is the whole point: logos supplied at different
				// pixel sizes otherwise sit at wildly different scales.
				$emit( $bp, $sel . '{gap:' . $gg . 'px}'
					. $sel . ' img,' . $sel . ' svg{height:' . $hh . 'px;width:auto;display:block}' );
			}
			if ( in_array( $align, array( 'center', 'space-between' ), true ) ) {
				self::$float_css .= $sel . '{justify-content:' . $align . '}';
			}
			if ( self::el_set( $node, 'bgGrey', '' ) ) {
				self::$float_css .= $sel . ' img,' . $sel . ' svg{filter:grayscale(1);opacity:.6;transition:filter .2s,opacity .2s}'
					. $sel . ' img:hover,' . $sel . ' svg:hover{filter:grayscale(0);opacity:1}';
			}
			return;
		}

		if ( 'Pullquote' === $el ) {
			$mark = self::el_set( $node, 'pqMark', 'none' );
			$rule = self::el_set( $node, 'pqRule', 'none' );
			if ( 'before' === $mark || 'left' === $mark ) {
				$c = self::el_color( $node, 'pqMarkColor', '#2ab7f1' );
				// A decorative glyph, so it is hidden from assistive tech.
				self::$float_css .= $sel . '{position:relative}'
					. $sel . '::before{content:"\201C";color:' . esc_attr( $c ) . ';font-size:2.5em;line-height:1;'
					. ( 'left' === $mark
						? 'position:absolute;left:-.6em;top:-.1em'
						: 'display:block;margin-bottom:.1em' )
					. '}';
			}
			if ( 'left' === $rule ) {
				$c = self::el_color( $node, 'pqRuleColor', '#2ab7f1' );
				self::$float_css .= $sel . '{border-left:4px solid ' . $c . ';padding-left:20px}';
			} elseif ( 'top' === $rule ) {
				// "Above" was drawn with ::after, which put the line under the quote.
				$c = self::el_color( $node, 'pqRuleColor', '#2ab7f1' );
				self::$float_css .= $sel . '{background-image:linear-gradient(' . $c . ',' . $c . ');'
					. 'background-size:56px 3px;background-repeat:no-repeat;background-position:0 0;padding-top:19px}';
			}
			return;
		}

		if ( 'Eyebrow' === $el ) {
			$line = self::el_set( $node, 'ebLine', 'none' );
			if ( 'none' === $line ) {
				return;
			}
			$w   = max( 4, (int) self::el_set( $node, 'ebLineW', 24 ) );
			$c   = self::sanitize_value( (string) self::el_set( $node, 'ebLineColor', '' ) );
			$col = '' !== $c ? $c : 'currentColor';
			$pos = 'before' === $line ? '::before' : '::after';
			$mar = 'before' === $line ? 'margin-right:10px' : 'margin-left:10px';
			self::$float_css .= $sel . '{display:inline-flex;align-items:center}'
				. $sel . $pos . '{content:"";width:' . $w . 'px;height:2px;background:' . $col . ';'
				. $mar . ';flex:0 0 auto}';
		}
	}

	private static function el_set_bp( $node, $key, $default = '' ) {
		$s   = self::el_settings( $node );
		// Responsive settings are numbers and sizes, never toggles, so a stored
		// 0 is a real value here ("no gap") — mapping it to '' made every
		// zero fall back to the default or inherit the wider screen's value.
		$out = array( 'base' => array_key_exists( $key, $s ) ? $s[ $key ] : $default );
		foreach ( array( 'xxl', 'xl', 'lg', 'md', 'sm' ) as $bp ) {
			$k = $key . '@' . $bp;
			if ( array_key_exists( $k, $s ) ) {
				$out[ $bp ] = $s[ $k ];
			}
		}
		return $out;
	}

	/** A number setting: an emptied field means the default, not 0. */
	private static function el_int( $node, $key, $default ) {
		$s = self::el_settings( $node );
		$v = array_key_exists( $key, $s ) ? $s[ $key ] : $default;
		return ( is_numeric( $v ) ) ? (int) $v : (int) $default;
	}

	/** A raw setting with 0 kept as 0 (for numbers such as delays). */
	private static function el_raw( $node, $key, $default = '' ) {
		$s = self::el_settings( $node );
		return array_key_exists( $key, $s ) ? $s[ $key ] : $default;
	}

	/** A colour (or any single CSS value) from a setting, safe inside a rule. */
	private static function el_color( $node, $key, $default ) {
		$v = self::sanitize_value( (string) self::el_set( $node, $key, $default ) );
		return '' !== $v ? $v : $default;
	}

	/** A size for the stylesheet: a number (px) or number+unit, else ''. */
	private static function css_size( $v ) {
		$v = strtolower( trim( (string) $v ) );
		if ( preg_match( '/^\d+(\.\d+)?$/', $v ) ) {
			return $v . 'px';
		}
		if ( preg_match( '/^\d+(\.\d+)?(px|%|rem|em|vw|vh|svh|dvh|ch)$|^auto$/', $v ) ) {
			return $v;
		}
		return '';
	}

	/**
	 * Whether a stored child really holds content. Every item of a nestable
	 * element is created with an empty container, and treating that as content
	 * hid the text typed into the item's own field.
	 */
	private static function has_kid( $kid ) {
		return is_array( $kid ) && ! empty( $kid['children'] );
	}

	/** The trigger type that actually does something ('click' arms nothing). */
	private static function trig_type( $node, $default = '' ) {
		$t = (string) self::el_set( $node, 'trigType', $default );
		if ( 'click' === $t ) {
			return '';
		}
		// A floating button or bar shown on every page load needs no trigger at
		// all — rendering it hidden only to unhide it with JavaScript made it
		// flash in late (or never appear without scripts).
		$el = $node['el'] ?? '';
		if ( 'load' === $t && ! in_array( $el, array( 'Modal', 'Offcanvas' ), true )
			&& 'always' === (string) self::el_set( $node, 'trigOnce', 'always' ) ) {
			return '';
		}
		return $t;
	}

	private static function el_set( $node, $key, $default = '' ) {
		$s = self::el_settings( $node );
		// A stored key wins even when it is empty: '' is how a toggle says OFF,
		// and falling back to the default there turns every disabled toggle back
		// on. Only an absent key means "use the default".
		if ( ! array_key_exists( $key, $s ) ) {
			return $default;
		}
		return ( '0' === $s[ $key ] ) ? '' : $s[ $key ];
	}

	/**
	 * Accordion / FAQ.
	 *
	 * Markup follows the W3C APG Accordion pattern exactly: a heading element
	 * containing a button that owns aria-expanded and aria-controls, and a
	 * labelled region for the panel. Built correctly here so it is accessible by
	 * default rather than retrofitted.
	 */
	private static function render_accordion( $node, $doc, $classes ) {
		$items = (array) self::el_set( $node, 'items', array() );
		if ( ! is_array( $items ) || ! $items ) {
			return '';
		}
		self::$runtime_used['accordion'] = true;

		$is_faq   = isset( $node['el'] ) && 'Faq' === $node['el'];
		$mode     = self::el_set( $node, 'openMode', 'single' );
		$speed    = (int) self::el_set( $node, 'speed', 220 );
		$icon_pos = self::el_set( $node, 'iconPos', 'right' );
		$h_tag    = self::el_set( $node, 'headingTag', 'h3' );
		$h_tag    = in_array( $h_tag, array( 'h2', 'h3', 'h4', 'h5' ), true ) ? $h_tag : 'h3';
		$deeplink = self::el_set( $node, 'deepLink', $is_faq ? '' : '1' );
		$first    = self::el_set( $node, 'firstOpen', $is_faq ? '' : '1' );
		$base     = sanitize_html_class( $node['id'] ?? 'acc' );
		// "Icon turns" was ignored: the stylesheet always rotated 180°.
		$turn = self::el_int( $node, 'iconRotate', 180 );
		if ( ! $is_faq && 180 !== $turn && '' !== $base ) {
			self::$float_css .= '#' . $base . ' .vx-acc-item.is-open>.vx-acc-h .vx-acc-i{transform:rotate(' . $turn . 'deg)}';
		}

		$cls = $classes . ' vx-acc' . ( 'left' === $icon_pos ? ' vx-acc-left' : '' );
		$out = '<div id="' . esc_attr( $node['id'] ?? '' ) . '" class="' . esc_attr( trim( $cls ) ) . '"' .
			' data-vx-mode="' . esc_attr( $mode ) . '" data-vx-speed="' . (int) $speed . '"' .
			( $deeplink ? ' data-vx-deeplink' : '' ) . '>';

		$qa = array();
		foreach ( array_values( $items ) as $i => $item ) {
			$title = isset( $item['title'] ) ? (string) $item['title'] : '';
			$body  = isset( $item['body'] ) ? (string) $item['body'] : '';
			if ( '' === trim( $title ) && '' === trim( $body ) && ! self::has_kid( $node['children'][ $i ] ?? null ) ) {
				continue;
			}
			$open   = ( $first && 0 === $i );
			$btn_id = $base . '-b' . $i;
			$pan_id = $base . '-p' . $i;

			$out .= '<div class="vx-acc-item' . ( $open ? ' is-open' : '' ) . '" id="' . esc_attr( $pan_id ) . '-item">';
			$out .= '<' . $h_tag . ' class="vx-acc-h">';
			$out .= '<button class="vx-acc-btn" type="button" id="' . esc_attr( $btn_id ) . '"' .
				' aria-expanded="' . ( $open ? 'true' : 'false' ) . '" aria-controls="' . esc_attr( $pan_id ) . '">';
			$out .= '<span class="vx-acc-t">' . esc_html( $title ) . '</span>';
			$out .= '<span class="vx-acc-i" aria-hidden="true"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg></span>';
			$out .= '</button></' . $h_tag . '>';
			$out .= '<div class="vx-acc-p" id="' . esc_attr( $pan_id ) . '" role="region" aria-labelledby="' . esc_attr( $btn_id ) . '"' . ( $open ? '' : ' hidden' ) . '>';
			// A panel can hold real elements. children[i] belongs to item i; the
			// plain text field stays as a fallback for simple content.
			$kid  = $node['children'][ $i ] ?? null;
			$rich = self::has_kid( $kid ) ? self::render_node( $kid, $doc ) : '';
			// The text field and dropped-in elements both show: the text first.
			$out .= ( '' !== trim( $body ) ? wp_kses_post( wpautop( $body ) ) : '' ) . $rich;
			$out .= '</div></div>';

			$qa[] = array( 'q' => $title, 'a' => trim( wp_strip_all_tags( $body . ' ' . $rich ) ) );
		}
		$out .= '</div>';

		// FAQPage structured data. Google restricted FAQ rich results to
		// government/health sites in 2023 and retired them in Search in 2026, so
		// this is opt-in and off by default — it is no longer an SEO win.
		if ( $is_faq && self::el_set( $node, 'faqSchema', '' ) && $qa ) {
			$ld = array( '@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => array() );
			foreach ( $qa as $pair ) {
				$ld['mainEntity'][] = array(
					'@type'          => 'Question',
					'name'           => $pair['q'],
					'acceptedAnswer' => array( '@type' => 'Answer', 'text' => $pair['a'] ),
				);
			}
			$out .= '<script type="application/ld+json">' . wp_json_encode( $ld ) . '</script>';
		}
		return $out;
	}


	/**
	 * Tabs — W3C APG Tabs pattern.
	 *
	 * One set of markup serves both the tab layout and the mobile accordion
	 * fallback; only a class changes at the breakpoint. Duplicating the content
	 * for mobile would double it for screen readers and search engines.
	 */
	private static function render_tabs( $node, $doc, $classes ) {
		// Items keep their original index: children[i] belongs to item i, and
		// re-numbering after dropping an empty item gave a tab its neighbour's
		// content.
		$items = array();
		foreach ( (array) self::el_set( $node, 'items', array() ) as $orig => $it ) {
			if ( '' !== trim( (string) ( $it['title'] ?? '' ) ) || '' !== trim( (string) ( $it['body'] ?? '' ) )
				|| self::has_kid( $node['children'][ $orig ] ?? null ) ) {
				$it['__i'] = $orig;
				$items[]   = $it;
			}
		}
		if ( ! $items ) {
			return '';
		}
		self::$runtime_used['tabs'] = true;

		$orient   = self::el_set( $node, 'orient', 'top' );
		$activate = self::el_set( $node, 'activate', 'click' );
		$start    = max( 0, (int) self::el_set( $node, 'startTab', 1 ) - 1 );
		$start    = min( $start, count( $items ) - 1 );
		$to_acc   = self::el_set( $node, 'toAccordion', '1' );
		$deeplink = self::el_set( $node, 'deepLink', '' );
		$base     = sanitize_html_class( $node['id'] ?? 'tabs' );
		$ord      = count( $items );
		$bp       = class_exists( 'Velox_Builder' ) ? Velox_Builder::breakpoints() : array( 'mobile' => 767 );

		$cls = trim( $classes . ' vx-tabs' . ( 'left' === $orient ? ' vx-tabs-left' : '' ) );
		$out = '<div id="' . esc_attr( $node['id'] ?? '' ) . '" class="' . esc_attr( $cls ) . '"' .
			' data-vx-activate="' . esc_attr( $activate ) . '"' .
			( $to_acc ? ' data-vx-toacc data-vx-accbp="' . (int) $bp['mobile'] . '"' : '' ) .
			( $deeplink ? ' data-vx-deeplink' : '' ) . '>';

		$out .= '<div class="vx-tablist" role="tablist"' . ( 'left' === $orient ? ' aria-orientation="vertical"' : '' ) . '>';
		foreach ( $items as $i => $item ) {
			$on = ( $i === $start );
			// The order lets the mobile accordion put each tab above its own panel.
			$out .= '<button type="button" class="vx-tab' . ( $on ? ' is-active' : '' ) . '" role="tab" style="--vx-o:' . ( $i * 2 ) . '"' .
				' id="' . esc_attr( $base . '-t' . $i ) . '"' .
				' aria-selected="' . ( $on ? 'true' : 'false' ) . '"' .
				' aria-controls="' . esc_attr( $base . '-tp' . $i ) . '"' .
				' tabindex="' . ( $on ? '0' : '-1' ) . '">' . esc_html( '' !== trim( (string) ( $item['title'] ?? '' ) ) ? (string) $item['title'] : sprintf( __( 'Tab %d', 'velox' ), $i + 1 ) ) . '</button>';
		}
		$out .= '</div><div class="vx-tabpanels">';
		foreach ( $items as $i => $item ) {
			$on = ( $i === $start );
			$kid  = $node['children'][ $item['__i'] ] ?? null;
			$body = (string) ( $item['body'] ?? '' );
			$out .= '<div class="vx-tabp" role="tabpanel" style="--vx-o:' . ( $i * 2 + 1 ) . '" id="' . esc_attr( $base . '-tp' . $i ) . '"' .
				' aria-labelledby="' . esc_attr( $base . '-t' . $i ) . '" tabindex="0"' . ( $on ? '' : ' hidden' ) . '>' .
				( '' !== trim( $body ) ? wp_kses_post( wpautop( $body ) ) : '' ) .
				( self::has_kid( $kid ) ? self::render_node( $kid, $doc ) : '' ) . '</div>';
		}
		$out .= '</div></div>';
		return $out;
	}


	/**
	 * Offcanvas, modal and dropdown.
	 *
	 * All three are the same thing — a panel that opens, traps focus and gives it
	 * back — so they share one renderer and one runtime primitive. Only the
	 * chrome and positioning differ.
	 */
	private static function render_overlay( $node, $doc, $classes ) {
		$kind = strtolower( $node['el'] );      // offcanvas | modal | dropdown
		self::$runtime_used['overlay'] = true;
		self::$runtime_used[ $kind ]   = true;

		$id       = sanitize_html_class( $node['id'] ?? $kind );
		$ms       = self::el_int( $node, 'ms', 'dropdown' === $kind ? 160 : 250 );
		$backdrop = 'dropdown' === $kind ? '' : self::el_set( $node, 'backdrop', '1' );
		$kids     = self::render_tree( $node['children'] ?? array(), $doc );
		$labelled = $id . '-label';

		$attr = ' id="' . esc_attr( $id ) . '" data-vx-ms="' . $ms . '"';
		if ( ! self::el_set( $node, 'closeEsc', '1' ) ) { $attr .= ' data-vx-noesc'; }
		if ( ! self::el_set( $node, 'closeBack', '1' ) ) { $attr .= ' data-vx-nobackclose'; }
		// "When it appears" and "Opened by" did nothing: neither was output.
		if ( 'dropdown' !== $kind && self::trig_type( $node ) ) {
			$attr .= self::floating_attrs( $node );
		}
		$with = trim( (string) self::el_set( $node, 'openWith', '' ) );
		if ( 'offcanvas' === $kind && '' !== $with ) {
			$attr .= ' data-vx-openwith="' . esc_attr( $with ) . '"';
		}

		// Size belongs on the PANEL. Putting it on the container left the panel at
		// 100% of a full-viewport box, so it covered the backdrop entirely and
		// clicking outside could never close it.
		// The element's own classes (and its id styles, see generate_css) belong
		// to the PANEL. On the full-screen container they turned the backdrop
		// into a 520px white column at the edge of the page.
		$panel_cls = trim( 'vx-ov-panel ' . $classes );
		$cls       = 'vx-ov vx-' . $kind;
		$mqs       = self::bp_map();
		$psel      = '#' . $id . '>.vx-ov-panel';

		if ( 'offcanvas' === $kind ) {
			$edge  = self::el_set( $node, 'edge', 'right' );
			$edge  = in_array( $edge, array( 'left', 'right', 'top', 'bottom' ), true ) ? $edge : 'right';
			$cls  .= ' vx-oc-' . $edge;
			$side  = in_array( $edge, array( 'left', 'right' ), true );
			foreach ( self::el_set_bp( $node, 'size', 340 ) as $bp => $v ) {
				$v = ( 'base' === $bp && ! is_numeric( $v ) ) ? 340 : $v;
				if ( ! is_numeric( $v ) ) {
					continue;
				}
				$rule = $psel . ( $side ? '{width:' . (int) $v . 'px;max-width:100%}' : '{height:' . (int) $v . 'px;width:100%}' );
				self::$float_css .= isset( $mqs[ $bp ] ) && $mqs[ $bp ] ? '@media ' . $mqs[ $bp ] . '{' . $rule . '}' : $rule;
			}
			$attr .= ' data-vx-modal';
		} elseif ( 'modal' === $kind ) {
			$pos  = self::el_set( $node, 'pos', 'center' );
			$cls .= ' vx-modal-' . ( in_array( $pos, array( 'center', 'top', 'bottom' ), true ) ? $pos : 'center' );
			foreach ( self::el_set_bp( $node, 'width', 520 ) as $bp => $v ) {
				$v = ( 'base' === $bp && ! is_numeric( $v ) ) ? 520 : $v;
				if ( ! is_numeric( $v ) ) {
					continue;
				}
				$rule = $psel . '{max-width:' . (int) $v . 'px}';
				self::$float_css .= isset( $mqs[ $bp ] ) && $mqs[ $bp ] ? '@media ' . $mqs[ $bp ] . '{' . $rule . '}' : $rule;
			}
			$attr .= ' data-vx-modal';
		} else {
			$cls .= ' vx-dd-' . ( 'right' === self::el_set( $node, 'align', 'left' ) ? 'right' : 'left' );
		}
		$panel_style = '';

		$out = '';

		// A dropdown carries its own trigger button; the other two are opened by
		// something else on the page.
		if ( 'dropdown' === $kind ) {
			$out .= '<div class="vx-dd-wrap"' . ( 'hover' === self::el_set( $node, 'openOn', 'click' ) ? ' data-vx-hover' : '' ) . '>';
			$out .= '<button type="button" class="vx-dd-btn" aria-expanded="false" aria-haspopup="dialog"' .
				' aria-controls="' . esc_attr( $id ) . '" data-vx-toggle="' . esc_attr( $id ) . '">' .
				esc_html( self::el_set( $node, 'label', 'Menü' ) ) .
				'<span class="vx-dd-i" aria-hidden="true"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg></span></button>';
		}

		$out .= '<div class="' . esc_attr( $cls ) . '"' . $attr . ' role="dialog"' .
			( 'dropdown' === $kind ? '' : ' aria-modal="true"' ) .
			' aria-labelledby="' . esc_attr( $labelled ) . '" aria-hidden="true" hidden>';

		if ( $backdrop ) {
			$bc   = self::el_color( $node, 'backColor', 'modal' === $kind ? 'rgba(10,11,14,.6)' : 'rgba(0,0,0,.5)' );
			$out .= '<div class="vx-ov-back" style="background:' . esc_attr( $bc ) . '"></div>';
		}
		$out .= '<div class="' . esc_attr( $panel_cls ) . '"' . ( $panel_style ? ' style="' . esc_attr( $panel_style ) . '"' : '' ) . '>';
		if ( 'dropdown' !== $kind && self::el_set( $node, 'closeBtn', '1' ) ) {
			$out .= '<button type="button" class="vx-ov-close" data-vx-close aria-label="' . esc_attr__( 'Close', 'velox' ) . '">' .
				'<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg></button>';
		}
		// Screen readers need a name for the dialog even when the design has no
		// visible heading.
		$names = array( 'offcanvas' => __( 'Panel', 'velox' ), 'modal' => __( 'Dialog', 'velox' ), 'dropdown' => __( 'Menu', 'velox' ) );
		$name  = (string) self::el_set( $node, 'dlgLabel', '' );
		if ( '' === trim( $name ) ) {
			$name = 'dropdown' === $kind ? (string) self::el_set( $node, 'label', $names['dropdown'] ) : $names[ $kind ];
		}
		$out .= '<span id="' . esc_attr( $labelled ) . '" class="vx-sr">' . esc_html( $name ) . '</span>';
		$out .= $kids . '</div></div>';

		if ( 'dropdown' === $kind ) { $out .= '</div>'; }
		return $out;
	}


	/**
	 * Slider — a scroll-snap track with real slides in the markup.
	 *
	 * No carousel library: slides are ordinary HTML inside a scroll container,
	 * so they exist for search engines and screen readers with no JavaScript,
	 * nothing shifts while a script boots, and native scrolling gives touch and
	 * trackpad support for free. JS only wires the arrows, dots and autoplay.
	 * Roles follow the W3C APG Carousel pattern.
	 */
	private static function render_slider( $node, $doc, $classes ) {
		$items = array();
		foreach ( (array) self::el_set( $node, 'items', array() ) as $orig => $it ) {
			if ( '' !== trim( (string) ( $it['title'] ?? '' ) )
				|| '' !== trim( (string) ( $it['body'] ?? '' ) )
				|| '' !== trim( (string) ( $it['image'] ?? '' ) )
				|| self::has_kid( $node['children'][ $orig ] ?? null ) ) {
				$it['__i'] = $orig;
				$items[]   = $it;
			}
		}
		if ( ! $items ) {
			return '';
		}
		self::$runtime_used['slider'] = true;

		$per    = max( 1, (int) self::el_set( $node, 'perView', 1 ) );
		$gap    = (int) self::el_set( $node, 'gap', 16 );
		$loop   = self::el_set( $node, 'loop', '' );
		$auto   = self::el_set( $node, 'auto', '' );
		$automs = max( 1500, self::el_int( $node, 'autoMs', 5000 ) );
		$arrows = self::el_set( $node, 'arrows', '1' );
		$dots   = self::el_set( $node, 'dots', '1' );
		$count  = self::el_set( $node, 'counter', '' );
		$id     = sanitize_html_class( $node['id'] ?? 'slider' );
		$total  = count( $items );

		$attr = ' id="' . esc_attr( $id ) . '"' .
			( $loop ? ' data-vx-loop' : '' ) .
			( $auto ? ' data-vx-auto="' . $automs . '"' : '' );

		$out  = '<div class="' . esc_attr( trim( $classes . ' vx-slider' ) ) . '"' . $attr .
			' role="group" aria-roledescription="' . esc_attr__( 'carousel', 'velox' ) . '"' .
			' aria-label="' . esc_attr__( 'Slider', 'velox' ) . '">';

		// Slide width and gap are emitted as a real stylesheet rule per breakpoint,
		// not as an inline style. Inline styles cannot carry a media query, which
		// is why perView and gap were marked responsive in the editor but always
		// rendered at their base value; they also outrank every later rule, so
		// nothing downstream could override them.
		$resp = array();
		foreach ( array( 'perView', 'gap', 'slideWidth', 'slideMinWidth', 'slideMaxWidth', 'slideHeight', 'peek' ) as $k ) {
			$resp[ $k ] = self::el_set_bp( $node, $k, '' );
		}
		$equal = self::el_set( $node, 'equalHeight', '1' );
		$mqs   = self::bp_map();
		foreach ( array( 'base', 'xxl', 'xl', 'lg', 'md', 'sm' ) as $bp ) {
			$touched = false;
			foreach ( $resp as $vals ) {
				if ( isset( $vals[ $bp ] ) ) {
					$touched = true;
					break;
				}
			}
			if ( ! $touched ) {
				continue;
			}
			// Carry the widest set value down, so a breakpoint that only changes
			// the gap still computes its slide width against the right count.
			$val = function ( $k, $fallback ) use ( $resp, $bp ) {
				foreach ( array_reverse( array_slice( array( 'base', 'xxl', 'xl', 'lg', 'md', 'sm' ), 0, array_search( $bp, array( 'base', 'xxl', 'xl', 'lg', 'md', 'sm' ), true ) + 1 ) ) as $b ) {
					if ( isset( $resp[ $k ][ $b ] ) && '' !== $resp[ $k ][ $b ] ) {
						return $resp[ $k ][ $b ];
					}
				}
				return $fallback;
			};
			$p  = max( 1, (int) $val( 'perView', 1 ) );
			$g  = (int) $val( 'gap', 16 );
			$pk = (int) $val( 'peek', 0 );
			// Sizes reach a <style> block, so only plain lengths get through —
			// a typed "}</style><script>" went out as-is before.
			$w  = self::css_size( $val( 'slideWidth', '' ) );

			// An explicit card width wins over slides-shown; otherwise the width
			// is derived from the count, minus the gaps and any peek.
			$basis = '' !== $w
				? 'flex:0 0 ' . $w
				: 'flex:0 0 calc((100% - ' . ( ( $p - 1 ) * $g + $pk ) . 'px)/' . $p . ')';

			$slide = $basis;
			$mn = self::css_size( $val( 'slideMinWidth', '' ) );
			$mx = self::css_size( $val( 'slideMaxWidth', '' ) );
			$h  = self::css_size( $val( 'slideHeight', '' ) );
			if ( '' !== $mn ) { $slide .= ';min-width:' . $mn; }
			if ( '' !== $mx ) { $slide .= ';max-width:' . $mx; }
			if ( '' !== $h )  { $slide .= ';height:' . $h; }

			$rule = '#' . $id . ' .vx-track{gap:' . $g . 'px'
				. ( $equal ? ';align-items:stretch' : ';align-items:flex-start' ) . '}'
				. '#' . $id . ' .vx-slide{' . $slide . '}';
			$mq = $mqs[ $bp ] ?? null;
			self::$float_css .= $mq ? ( '@media ' . $mq . '{' . $rule . '}' ) : $rule;
		}

		// Snapping and movement are page-wide, not per breakpoint.
		$snap = self::el_set( $node, 'snap', 'mandatory' );
		$snap = in_array( $snap, array( 'mandatory', 'proximity', 'none' ), true ) ? $snap : 'mandatory';
		$beh  = 'auto' === self::el_set( $node, 'behavior', 'smooth' ) ? 'auto' : 'smooth';
		self::$float_css .= '#' . $id . ' .vx-track{scroll-snap-type:'
			. ( 'none' === $snap ? 'none' : 'x ' . $snap ) . ';scroll-behavior:' . $beh . '}';

		$apos = self::el_set( $node, 'arrowPos', 'below' );
		if ( 'inside' === $apos || 'outside' === $apos ) {
			// trim() drops the leading space when the element has no classes of its
			// own, so match the bare token rather than assuming one.
			$out = str_replace( 'vx-slider"', 'vx-slider vx-arrows-' . $apos . '"', $out );
		}

		$out .= '<div class="vx-track">';
		foreach ( $items as $i => $item ) {
			$out .= '<div class="vx-slide"' .
				' role="group" aria-roledescription="' . esc_attr__( 'slide', 'velox' ) . '"' .
				' aria-label="' . esc_attr( sprintf( '%d / %d', $i + 1, $total ) ) . '">';
			if ( ! empty( $item['image'] ) ) {
				// Width/height are unknown here, so lazy-load everything except the
				// first slide, which is often the LCP element.
				$out .= '<img src="' . esc_url( $item['image'] ) . '" alt=""' .
					( 0 === $i ? ' fetchpriority="high"' : ' loading="lazy" decoding="async"' ) . '>';
			}
			$kid = $node['children'][ $item['__i'] ] ?? null;
			if ( ! empty( $item['title'] ) ) { $out .= '<strong class="vx-slide-t">' . esc_html( $item['title'] ) . '</strong>'; }
			if ( ! empty( $item['body'] ) ) { $out .= wp_kses_post( wpautop( $item['body'] ) ); }
			if ( self::has_kid( $kid ) ) {
				$out .= self::render_node( $kid, $doc );
			}
			$out .= '</div>';
		}
		$out .= '</div>';

		$out .= '<div class="vx-live vx-sr" aria-live="polite" aria-atomic="true">1 / ' . $total . '</div>';
		// The counter was only printed inside the controls, so with arrows and
		// dots switched off it never showed.
		if ( $count && ! ( $arrows || $dots || $auto ) ) {
			$out .= '<span class="vx-count" aria-hidden="true">1 / ' . $total . '</span>';
		}

		if ( $arrows || $dots || $auto ) {
			$out .= '<div class="vx-controls">';
			if ( $auto ) {
				// APG requires a way to stop anything that moves by itself.
				$out .= '<button type="button" class="vx-play" aria-pressed="false"' .
					' data-vx-play-label="' . esc_attr__( 'Play', 'velox' ) . '"' .
					' data-vx-pause-label="' . esc_attr__( 'Pause', 'velox' ) . '"' .
					' aria-label="' . esc_attr__( 'Pause', 'velox' ) . '">' .
					'<span class="vx-play-i" aria-hidden="true"></span></button>';
			}
			if ( $dots ) {
				$out .= '<div class="vx-dots" role="tablist" aria-label="' . esc_attr__( 'Choose a slide', 'velox' ) . '">';
				foreach ( $items as $i => $item ) {
					$out .= '<button type="button" class="vx-dot' . ( 0 === $i ? ' is-active' : '' ) . '" role="tab"' .
						' data-vx-i="' . $i . '" aria-selected="' . ( 0 === $i ? 'true' : 'false' ) . '"' .
						' tabindex="' . ( 0 === $i ? '0' : '-1' ) . '"' .
						' aria-label="' . esc_attr( sprintf( '%d / %d', $i + 1, $total ) ) . '"></button>';
				}
				$out .= '</div>';
			}
			if ( $count ) { $out .= '<span class="vx-count" aria-hidden="true">1 / ' . $total . '</span>'; }
			if ( $arrows ) {
				$out .= '<div class="vx-arrows">' .
					'<button type="button" class="vx-prev" aria-label="' . esc_attr__( 'Previous slide', 'velox' ) . '"' . ( $loop ? '' : ' disabled' ) . '><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="m15 18-6-6 6-6"/></svg></button>' .
					'<button type="button" class="vx-next" aria-label="' . esc_attr__( 'Next slide', 'velox' ) . '"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="m9 18 6-6-6-6"/></svg></button>' .
					'</div>';
			}
			$out .= '</div>';
		}
		return $out . '</div>';
	}


	/** Placement + trigger attributes shared by every floating element. */
	private static function floating_attrs( $node ) {
		$out = '';
		// Back to top shows after scrolling 25% unless told otherwise — the
		// defaults the editor shows.
		$is_top = 'Backtotop' === ( $node['el'] ?? '' );
		$trig   = self::trig_type( $node, $is_top ? 'scroll' : '' );
		if ( $trig ) {
			$out .= ' data-vx-trig="' . esc_attr( $trig ) . '"' .
				' data-vx-once="' . esc_attr( $is_top ? 'always' : self::el_set( $node, 'trigOnce', in_array( $node['el'] ?? '', array( 'Modal', 'Offcanvas' ), true ) ? 'session' : 'always' ) ) . '"';
			$defs = array( 'trigDelay' => '', 'trigScroll' => $is_top ? '25' : '', 'trigIdle' => '', 'trigTarget' => '' );
			foreach ( array( 'trigDelay' => 'delay', 'trigScroll' => 'scroll', 'trigIdle' => 'idle', 'trigTarget' => 'target' ) as $k => $a ) {
				$v = (string) self::el_raw( $node, $k, $defs[ $k ] );
				if ( '' !== $v ) { $out .= ' data-vx-' . $a . '="' . esc_attr( $v ) . '"'; }
			}
		}
		return $out;
	}
	private static function floating_style( $node, $sel = '' ) {
		$ax = self::el_set( $node, 'anchorX', 'right' );
		$ay = self::el_set( $node, 'anchorY', 'bottom' );
		$ox = self::el_int( $node, 'offsetX', 24 );
		$oy = self::el_int( $node, 'offsetY', 24 );
		$z  = self::el_int( $node, 'zIndex', 9990 );
		$st = 'position:fixed;z-index:' . $z . ';';
		// Centred with auto margins, not a transform: an attention animation
		// animates transform and threw the button off centre.
		if ( 'center' === $ax )      { $st .= 'left:0;right:0;margin-left:auto;margin-right:auto;width:max-content;'; }
		elseif ( 'left' === $ax )    { $st .= 'left:' . $ox . 'px;'; }
		else                         { $st .= 'right:' . $ox . 'px;'; }
		if ( 'middle' === $ay )      { $st .= 'top:0;bottom:0;margin-top:auto;margin-bottom:auto;height:max-content;'; }
		elseif ( 'top' === $ay )     { $st .= 'top:' . $oy . 'px;'; }
		else                         { $st .= 'bottom:' . $oy . 'px;'; }
		// The offsets are responsive settings: narrower screens get their own.
		if ( '' !== $sel ) {
			$mqs = self::bp_map();
			$xs  = self::el_set_bp( $node, 'offsetX', 24 );
			$ys  = self::el_set_bp( $node, 'offsetY', 24 );
			foreach ( array( 'xxl', 'xl', 'lg', 'md', 'sm' ) as $bp ) {
				$r = '';
				if ( isset( $xs[ $bp ] ) && is_numeric( $xs[ $bp ] ) && 'center' !== $ax ) {
					$r .= ( 'left' === $ax ? 'left:' : 'right:' ) . (int) $xs[ $bp ] . 'px;';
				}
				if ( isset( $ys[ $bp ] ) && is_numeric( $ys[ $bp ] ) && 'middle' !== $ay ) {
					$r .= ( 'top' === $ay ? 'top:' : 'bottom:' ) . (int) $ys[ $bp ] . 'px;';
				}
				if ( '' !== $r && ! empty( $mqs[ $bp ] ) ) {
					self::$float_css .= '@media ' . $mqs[ $bp ] . '{' . $sel . '{' . $r . '}}';
				}
			}
		}
		return $st;
	}
	/** Per-device hiding, as real CSS rather than a JS check. */
	private static function visibility_css( $node, $sel ) {
		if ( ! class_exists( 'Velox_Builder' ) ) { return ''; }
		$bp  = Velox_Builder::breakpoints();
		$css = '';
		if ( self::el_set( $node, 'hideMobile', '' ) ) {
			$css .= '@media (max-width:' . (int) $bp['mobile'] . 'px){' . $sel . '{display:none!important}}';
		}
		if ( self::el_set( $node, 'hideTablet', '' ) ) {
			$css .= '@media (min-width:' . ( (int) $bp['mobile'] + 1 ) . 'px) and (max-width:' . (int) $bp['tablet'] . 'px){' . $sel . '{display:none!important}}';
		}
		if ( self::el_set( $node, 'hideDesktop', '' ) ) {
			$css .= '@media (min-width:' . ( (int) $bp['tablet'] + 1 ) . 'px){' . $sel . '{display:none!important}}';
		}
		return $css;
	}

	/** Floating buttons, bars and the reading-progress indicator. */
	private static function render_floating( $node, $doc, $classes ) {
		$el = $node['el'];
		self::$runtime_used['floating'] = true;
		$id  = sanitize_html_class( $node['id'] ?? 'fl' );
		$sel = '#' . $id;

		if ( 'Progressbar' === $el ) {
			$pos   = self::el_set( $node, 'pos', 'top' );
			$thick = max( 1, self::el_int( $node, 'thickness', 4 ) );
			$col   = self::el_color( $node, 'color', '#2ab7f1' );
			self::$float_css .= $sel . '{position:fixed;left:0;right:0;' . ( 'bottom' === $pos ? 'bottom:0' : 'top:0' ) .
				';height:' . $thick . 'px;z-index:9995;background:transparent}' .
				$sel . ' .vx-progress-fill{height:100%;width:0;background:' . $col . ';transition:width .1s linear}';
			return '<div id="' . esc_attr( $id ) . '" class="' . esc_attr( trim( $classes . ' vx-progress' ) ) . '"' .
				' role="progressbar" aria-label="' . esc_attr__( 'Reading progress', 'velox' ) . '"' .
				' aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><div class="vx-progress-fill"></div></div>';
		}

		if ( 'Announcebar' === $el || 'Stickybar' === $el ) {
			$pos    = self::el_set( $node, 'pos', 'Stickybar' === $el ? 'bottom' : 'top' );
			$sticky = 'Stickybar' === $el ? '1' : self::el_set( $node, 'sticky', '1' );
			$hidden = self::trig_type( $node ) ? ' hidden' : '';
			if ( $sticky ) {
				self::$float_css .= $sel . '{position:fixed;left:0;right:0;' . ( 'bottom' === $pos ? 'bottom:0' : 'top:0' ) . ';z-index:9992}';
			}
			if ( 'Stickybar' === $el && self::el_set( $node, 'mobileOnly', '1' ) && class_exists( 'Velox_Builder' ) ) {
				$bp = Velox_Builder::breakpoints();
				self::$float_css .= '@media (min-width:' . ( (int) $bp['mobile'] + 1 ) . 'px){' . $sel . '{display:none!important}}';
			}
			self::$float_css .= self::visibility_css( $node, $sel );

			$inner = '';
			if ( 'Announcebar' === $el ) {
				$inner .= '<span class="vx-bar-text">' . esc_html( self::el_set( $node, 'text', 'Kostenlose Beratung — jetzt anfragen' ) ) . '</span>';
				$lt = self::el_set( $node, 'linkText', '' );
				$lu = self::el_set( $node, 'linkUrl', '' );
				if ( $lt && $lu ) { $inner .= ' <a class="vx-bar-link" href="' . esc_url( $lu ) . '">' . esc_html( $lt ) . '</a>'; }
				if ( self::el_set( $node, 'dismiss', '1' ) ) {
					$inner .= '<button type="button" class="vx-bar-x" data-vx-dismiss aria-label="' . esc_attr__( 'Close', 'velox' ) . '">' .
						'<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg></button>';
				}
			} else {
				foreach ( (array) self::el_set( $node, 'items', array() ) as $item ) {
					if ( empty( $item['title'] ) && empty( $item['href'] ) ) { continue; }
					$ico = ( ! empty( $item['icon'] ) && class_exists( 'Velox_Icons' ) ) ? Velox_Icons::svg( $item['icon'], 18 ) : '';
					$inner .= '<a class="vx-bar-btn" href="' . esc_url( self::link_value( (string) ( $item['href'] ?? '' ) ) ) . '">' .
						( $ico ? '<span class="vx-bar-i" aria-hidden="true">' . $ico . '</span>' : '' ) .
						esc_html( $item['title'] ?? '' ) . '</a>';
				}
			}
			return '<div id="' . esc_attr( $id ) . '" class="' . esc_attr( trim( $classes . ' vx-bar' ) ) . '"' .
				self::floating_attrs( $node ) . $hidden . '>' . $inner . '</div>';
		}

		// Floating button / back to top
		self::$float_css .= $sel . '{' . self::floating_style( $node, $sel ) . '}' . self::visibility_css( $node, $sel );
		$idle = self::el_set( $node, 'idle', 'none' );
		if ( 'none' !== $idle ) { self::$runtime_used['fabanim'] = true; }

		$label  = self::el_set( $node, 'label', 'Backtotop' === $el ? 'Nach oben' : '' );
		$action = self::el_set( $node, 'action', 'link' );
		$target = self::el_set( $node, 'target', '' );
		$href   = '#';
		$extra  = '';
		if ( 'Backtotop' === $el || 'scrolltop' === $action ) {
			$extra = ' data-vx-scrollto="#top"';
		} elseif ( 'call' === $action )     { $href = 'tel:' . preg_replace( '/[^0-9+]/', '', $target ); }
		elseif ( 'whatsapp' === $action )   { $href = 'https://wa.me/' . preg_replace( '/[^0-9]/', '', $target ); }
		elseif ( 'mail' === $action )       { $href = 'mailto:' . sanitize_email( $target ); }
		elseif ( 'open' === $action )       { $extra = ' data-vx-open="' . esc_attr( self::el_set( $node, 'openId', '' ) ) . '"'; }
		else                                { $href = $target ? esc_url( $target ) : '#'; }

		$tag  = ( 'Backtotop' === $el || 'open' === $action || 'scrolltop' === $action ) ? 'button' : 'a';
		$attr = ( 'button' === $tag ) ? ' type="button"' : ' href="' . esc_url( $href ) . '"';
		$name = $label ? $label : ( 'Backtotop' === $el ? __( 'Back to top', 'velox' ) : __( 'Open', 'velox' ) );

		$hidden = self::trig_type( $node, 'Backtotop' === $el ? 'scroll' : '' ) ? ' hidden' : '';
		// Back to top's text is screen-reader text: it goes in the label only.
		$sr_only = 'Backtotop' === $el;
		$out  = '<' . $tag . ' id="' . esc_attr( $id ) . '" class="' . esc_attr( trim( $classes . ' vx-fab' . ( 'none' !== $idle ? ' vx-idle-' . $idle : '' ) ) ) . '"' .
			$attr . $extra . self::floating_attrs( $node ) . $hidden .
			( $label && ! $sr_only ? '' : ' aria-label="' . esc_attr( $sr_only && ! $label ? __( 'Back to top', 'velox' ) : $name ) . '"' ) . '>';
		// Use the chosen icon; fall back to something sensible for the element.
		$icon_name = self::el_set( $node, 'icon', '' );
		// Old defaults that were never icon names.
		$icon_name = array( 'chat' => 'message', 'chevron' => 'chevron-up' )[ $icon_name ] ?? $icon_name;
		$icon_svg  = ( $icon_name && class_exists( 'Velox_Icons' ) ) ? Velox_Icons::svg( $icon_name, 20 ) : '';
		if ( ! $icon_svg && class_exists( 'Velox_Icons' ) ) {
			$icon_svg = Velox_Icons::svg( 'Backtotop' === $el ? 'chevron-up' : 'message', 20 );
		}
		$out .= '<span class="vx-fab-i" aria-hidden="true">' . $icon_svg . '</span>';
		if ( $label && ! $sr_only ) { $out .= '<span class="vx-fab-l">' . esc_html( $label ) . '</span>'; }
		return $out . '</' . $tag . '>';
	}

	/** Per-instance CSS for floating elements, collected during render. */
	private static $float_css = '';


	/**
	 * Navigation.
	 *
	 * A <nav> landmark, an ordinary list of links, and a real <button
	 * aria-expanded> per submenu — the APG Disclosure Navigation pattern. The
	 * menubar/menu/menuitem roles are deliberately NOT used: they switch screen
	 * readers into application mode and promise keyboard behaviour that site
	 * navigation does not implement. aria-haspopup is omitted for the same
	 * reason.
	 */
	private static function render_nav( $node, $doc, $classes ) {
		$el = $node['el'];
		self::$runtime_used['nav'] = true;
		$id = sanitize_html_class( $node['id'] ?? 'nav' );

		if ( 'Breadcrumbs' === $el ) {
			$sep   = self::el_set( $node, 'sep', '/' );
			$home  = self::el_set( $node, 'homeLabel', 'Start' );
			$crumbs = array( array( 'name' => $home, 'url' => home_url( '/' ) ) );
			$qo     = get_queried_object();
			// On an archive the queried id is a TERM id: treating it as a post id
			// showed the title of an unrelated post.
			if ( $qo instanceof WP_Post ) {
				$pid = (int) $qo->ID;
				foreach ( array_reverse( get_post_ancestors( $pid ) ) as $anc ) {
					$crumbs[] = array( 'name' => get_the_title( $anc ), 'url' => get_permalink( $anc ) );
				}
				$crumbs[] = array( 'name' => get_the_title( $pid ), 'url' => '' );
			} elseif ( $qo instanceof WP_Term ) {
				foreach ( array_reverse( get_ancestors( (int) $qo->term_id, $qo->taxonomy, 'taxonomy' ) ) as $anc ) {
					$t = get_term( $anc, $qo->taxonomy );
					if ( $t && ! is_wp_error( $t ) ) {
						$l = get_term_link( $t );
						$crumbs[] = array( 'name' => $t->name, 'url' => is_wp_error( $l ) ? '' : $l );
					}
				}
				$crumbs[] = array( 'name' => $qo->name, 'url' => '' );
			} elseif ( $qo instanceof WP_User ) {
				$crumbs[] = array( 'name' => $qo->display_name, 'url' => '' );
			} elseif ( is_search() ) {
				/* translators: %s: the search query. */
				$crumbs[] = array( 'name' => sprintf( __( 'Search: %s', 'velox' ), get_search_query() ), 'url' => '' );
			} elseif ( is_404() ) {
				$crumbs[] = array( 'name' => __( 'Page not found', 'velox' ), 'url' => '' );
			}
			$out = '<nav id="' . esc_attr( $id ) . '" class="' . esc_attr( trim( $classes . ' vx-crumbs' ) ) . '"' .
				' aria-label="' . esc_attr__( 'Breadcrumb', 'velox' ) . '"><ol class="vx-crumb-list">';
			foreach ( $crumbs as $i => $c ) {
				$last = ( $i === count( $crumbs ) - 1 );
				$out .= '<li class="vx-crumb">';
				if ( $c['url'] && ! $last ) { $out .= '<a href="' . esc_url( $c['url'] ) . '">' . esc_html( $c['name'] ) . '</a>'; }
				else { $out .= '<span aria-current="page">' . esc_html( $c['name'] ) . '</span>'; }
				if ( ! $last ) { $out .= '<span class="vx-crumb-sep" aria-hidden="true">' . esc_html( $sep ) . '</span>'; }
				$out .= '</li>';
			}
			$out .= '</ol>';
			if ( self::el_set( $node, 'schema', '1' ) ) {
				$ld = array( '@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => array() );
				foreach ( $crumbs as $i => $c ) {
					$item = array( '@type' => 'ListItem', 'position' => $i + 1, 'name' => $c['name'] );
					if ( $c['url'] ) { $item['item'] = $c['url']; }
					$ld['itemListElement'][] = $item;
				}
				$out .= '<script type="application/ld+json">' . wp_json_encode( $ld ) . '</script>';
			}
			return $out . '</nav>';
		}

		$items  = (array) self::el_set( $node, 'items', array() );
		$anchor = ( 'Anchornav' === $el );
		// The WordPress-menu source was a setting the renderer ignored: choosing
		// it produced nothing at all. Pull the real menu and convert it into the
		// same item shape the manual list uses, so one renderer serves both.
		// Default to whichever the document actually has: a nav saved with manual
		// links but no explicit source must not silently lose them.
		$default_source = $items ? 'manual' : 'wp';
		if ( ! $anchor && 'wp' === self::el_set( $node, 'source', $default_source ) ) {
			$wp_items = self::wp_menu_items( self::el_set( $node, 'menu', 'primary' ) );
			// An empty or missing menu falls back to the manual list rather than
			// rendering an empty nav.
			if ( $wp_items ) { $items = $wp_items; }
		}
		$spy    = $anchor && self::el_set( $node, 'spy', '1' );
		$sticky = self::el_set( $node, 'sticky', '' );

		$attr = ' id="' . esc_attr( $id ) . '"';
		if ( ! $anchor ) {
			$collapse = self::el_set( $node, 'collapseAt', 'tablet' );
			$bp       = class_exists( 'Velox_Builder' ) ? Velox_Builder::breakpoints() : array( 'tablet' => 991, 'mobile' => 767 );
			$px       = array( 'tablet' => (int) $bp['tablet'], 'mobile' => (int) $bp['mobile'], 'always' => 99999, 'never' => 0 );
			if ( ! empty( $px[ $collapse ] ) ) { $attr .= ' data-vx-bp="' . (int) $px[ $collapse ] . '"'; }
			$attr .= ' data-vx-subtrigger="' . esc_attr( self::el_set( $node, 'subTrigger', 'hover' ) ) . '"';
		}
		if ( $spy )    { $attr .= ' data-vx-spy'; }
		if ( $sticky ) {
			$attr .= ' data-vx-sticky data-vx-shrink-at="' . (int) self::el_set( $node, 'shrinkAt', 60 ) . '"';
			if ( self::el_set( $node, 'hideDown', '' ) ) { $attr .= ' data-vx-hide-down'; }
			self::$float_css .= '#' . $id . '{position:sticky;top:0;z-index:9990}';
			if ( self::el_set( $node, 'shrink', '' ) ) { self::$runtime_used['navshrink'] = true; }
		}

		// Every navbar was called "Main", so a footer menu repeated the main
		// navigation's landmark name. A typed name wins; otherwise only the first
		// navbar on the page is "Main".
		$nav_name = trim( (string) self::el_set( $node, 'navLabel', '' ) );
		if ( '' === $nav_name ) {
			if ( $anchor ) {
				$nav_name = __( 'On this page', 'velox' );
			} else {
				$nav_name = self::$main_nav_done ? __( 'Secondary', 'velox' ) : __( 'Main', 'velox' );
				self::$main_nav_done = true;
			}
		}
		// "A full screen panel" was a choice the renderer never read.
		$full = ! $anchor && 'fullscreen' === self::el_set( $node, 'mobileStyle', 'dropdown' );
		$out  = '<nav class="' . esc_attr( trim( $classes . ' vx-nav' . ( $anchor ? ' vx-anchornav' : '' ) . ( $full ? ' vx-nav-full' : '' ) ) ) . '"' . $attr .
			' aria-label="' . esc_attr( $nav_name ) . '">';

		if ( ! $anchor ) {
			$out .= '<button type="button" class="vx-burger" aria-expanded="false" aria-controls="' . esc_attr( $id . '-list' ) . '"' .
				' aria-label="' . esc_attr__( 'Menu', 'velox' ) . '" hidden>' .
				'<span class="vx-burger-i" aria-hidden="true"></span></button>';
		}

		$out .= '<ul class="vx-nav-list" id="' . esc_attr( $id . '-list' ) . '">';
		$current = get_permalink( get_queried_object_id() );
		foreach ( array_values( $items ) as $i => $item ) {
			$title = (string) ( $item['title'] ?? '' );
			$href  = (string) ( $item['href'] ?? '#' );
			if ( '' === trim( $title ) ) { continue; }
			// "Section ID" on an anchor nav: a bare id became http://leistungen.
			if ( $anchor && '' !== $href && ! preg_match( '#^(\#|/|[a-z][a-z0-9+.-]*:)#i', $href ) ) {
				$href = '#' . $href;
			}
			$subs  = array();
			foreach ( array_filter( explode( ',', (string) ( $item['children'] ?? '' ) ) ) as $pair ) {
				$bits = explode( '|', $pair );
				if ( trim( $bits[0] ) ) { $subs[] = array( trim( $bits[0] ), trim( $bits[1] ?? '#' ) ); }
			}
			$out .= '<li class="vx-nav-item' . ( $subs ? ' vx-has-sub' : '' ) . '">';
			$is_current = ( $current && untrailingslashit( $href ) === untrailingslashit( $current ) );
			$out .= '<a href="' . esc_url( $href ) . '"' . ( $is_current ? ' aria-current="page"' : '' ) . '>' . esc_html( $title ) . '</a>';
			if ( $subs ) {
				$sid  = $id . '-s' . $i;
				$out .= '<button type="button" class="vx-sub-btn" aria-expanded="false" aria-controls="' . esc_attr( $sid ) . '"' .
					' aria-label="' . esc_attr( sprintf( __( 'Show submenu of %s', 'velox' ), $title ) ) . '">' .
					'<span aria-hidden="true"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><path d="m6 9 6 6 6-6"/></svg></span></button>';
				$out .= '<ul class="vx-submenu" id="' . esc_attr( $sid ) . '" hidden>';
				foreach ( $subs as $sub ) {
					$out .= '<li><a href="' . esc_url( $sub[1] ) . '">' . esc_html( $sub[0] ) . '</a></li>';
				}
				$out .= '</ul>';
			}
			$out .= '</li>';
		}
		return $out . '</ul></nav>';
	}


	/** Whether the page already has its "Main" navigation landmark. */
	private static $main_nav_done = false;

	/**
	 * A typed link: a phone number becomes tel:, an e-mail address mailto:.
	 * esc_url() turned "+49 2131 000000" into http://+492131000000.
	 */
	private static function link_value( $v ) {
		$v = trim( $v );
		if ( '' === $v ) {
			return '#';
		}
		if ( preg_match( '/^\+?[0-9][0-9 ()\/.-]{4,}$/', $v ) ) {
			return 'tel:' . preg_replace( '/[^0-9+]/', '', $v );
		}
		if ( is_email( $v ) ) {
			return 'mailto:' . $v;
		}
		return $v;
	}

	/** A WordPress menu, flattened to the item shape the nav renderer expects. */
	private static function wp_menu_items( $location ) {
		$out = array();
		$locations = get_nav_menu_locations();
		$menu_id   = 0;
		if ( $location && ! empty( $locations[ $location ] ) ) {
			$menu_id = (int) $locations[ $location ];
		} else {
			$obj = wp_get_nav_menu_object( $location );
			if ( $obj ) { $menu_id = (int) $obj->term_id; }
		}
		// Sites without theme menu locations (Oxygen, Velox-only) still have
		// menus: fall back to the first assigned one, then to the first menu.
		if ( ! $menu_id ) {
			foreach ( (array) $locations as $mid ) {
				if ( $mid ) { $menu_id = (int) $mid; break; }
			}
		}
		if ( ! $menu_id ) {
			$all = wp_get_nav_menus();
			if ( $all && ! is_wp_error( $all ) ) { $menu_id = (int) $all[0]->term_id; }
		}
		if ( ! $menu_id ) {
			return $out;
		}
		$objs = wp_get_nav_menu_items( $menu_id );
		if ( ! $objs ) {
			return $out;
		}
		$top = array();
		$kids = array();
		foreach ( $objs as $o ) {
			if ( (int) $o->menu_item_parent ) { $kids[ (int) $o->menu_item_parent ][] = $o; }
			else { $top[] = $o; }
		}
		foreach ( $top as $o ) {
			$children = '';
			if ( ! empty( $kids[ (int) $o->ID ] ) ) {
				$parts = array();
				foreach ( $kids[ (int) $o->ID ] as $k ) {
					// Children are packed as "title|url,…": a comma in a URL split it.
					$parts[] = str_replace( array( '|', ',' ), ' ', $k->title ) . '|' . str_replace( array( ',', '|' ), array( '%2C', '%7C' ), $k->url );
				}
				$children = implode( ',', $parts );
			}
			$out[] = array( 'title' => $o->title, 'href' => $o->url, 'children' => $children );
		}
		return $out;
	}


	/** Styling for a reviews block, from its settings. */
	private static function reviews_css( $node, $sel ) {
		$css    = '';
		// Every rule targets the example markup (vx-rv-*) AND the real reviews
		// (vxr-*): styling only ever reached the examples before.
		$s      = function ( $a, $b ) use ( $sel ) { return $sel . ' ' . $a . ',' . $sel . ' ' . $b; };
		$layout = self::el_set( $node, 'layout', 'grid' );
		$mqs    = self::bp_map();
		$cols   = self::el_set_bp( $node, 'columns', 3 );
		$gaps   = self::el_set_bp( $node, 'gap', 20 );
		$sizes  = self::el_set_bp( $node, 'textSize', 14 );
		$bps    = array( 'base', 'xxl', 'xl', 'lg', 'md', 'sm' );
		$cc = 3;
		$gg = 20;
		foreach ( $bps as $bp ) {
			if ( ! isset( $cols[ $bp ] ) && ! isset( $gaps[ $bp ] ) ) {
				continue;
			}
			if ( isset( $cols[ $bp ] ) && is_numeric( $cols[ $bp ] ) ) { $cc = max( 1, (int) $cols[ $bp ] ); }
			if ( isset( $gaps[ $bp ] ) && is_numeric( $gaps[ $bp ] ) ) { $gg = (int) $gaps[ $bp ]; }
			if ( 'grid' === $layout ) {
				$r = $s( '.vx-rv-list', '.velox-reviews-track' ) . '{display:grid;grid-template-columns:repeat(' . $cc . ',minmax(0,1fr));gap:' . $gg . 'px;transform:none}';
			} elseif ( 'slider' === $layout ) {
				$r = $sel . ' .vx-rv-list{display:flex;overflow-x:auto;scroll-snap-type:x mandatory;gap:' . $gg . 'px}' .
					$sel . ' .vx-rv-card{flex:0 0 min(320px,80%);scroll-snap-align:start}';
			} else {
				$r = $s( '.vx-rv-list', '.velox-reviews-track' ) . '{display:flex;flex-direction:column;gap:' . $gg . 'px;transform:none}';
			}
			$css .= ! empty( $mqs[ $bp ] ) ? '@media ' . $mqs[ $bp ] . '{' . $r . '}' : $r;
		}
		$shadow = self::el_set( $node, 'shadow', 'soft' );
		$shadows = array(
			'none'   => 'none',
			'soft'   => '0 2px 10px rgba(0,0,0,.07)',
			'strong' => '0 10px 30px rgba(0,0,0,.14)',
		);
		$card = 'background:' . self::el_color( $node, 'cardBg', '#ffffff' ) . ';' .
			'border-radius:' . self::el_int( $node, 'cardRadius', 14 ) . 'px;' .
			'padding:' . self::el_int( $node, 'cardPad', 20 ) . 'px;' .
			'box-shadow:' . ( $shadows[ $shadow ] ?? $shadows['soft'] ) . ';';
		$border = self::el_set( $node, 'cardBorder', '' );
		if ( $border ) { $card .= 'border:1px solid ' . self::sanitize_value( $border ) . ';'; }
		$css .= $s( '.vx-rv-card', '.vxr-card' ) . '{' . $card . '}';
		$star = self::el_color( $node, 'starColor', '#fbbc04' );
		$css .= $s( '.vx-rv-stars', '.vxr-stars' ) . '{color:' . $star . ';font-size:' . self::el_int( $node, 'starSize', 16 ) . 'px}'
			. $sel . ' .vxr-star.is-on{color:' . $star . '}';
		$nc = self::sanitize_value( (string) self::el_set( $node, 'nameColor', '' ) );
		if ( $nc ) { $css .= $s( '.vx-rv-name', '.vxr-name' ) . '{color:' . $nc . '}'; }
		$tc = self::sanitize_value( (string) self::el_set( $node, 'textColor', '' ) );
		if ( $tc ) { $css .= $s( '.vx-rv-text', '.vxr-text' ) . '{color:' . $tc . '}'; }
		foreach ( $bps as $bp ) {
			if ( isset( $sizes[ $bp ] ) && is_numeric( $sizes[ $bp ] ) ) {
				$r    = $s( '.vx-rv-text', '.vxr-text' ) . '{font-size:' . (int) $sizes[ $bp ] . 'px}';
				$css .= ! empty( $mqs[ $bp ] ) ? '@media ' . $mqs[ $bp ] . '{' . $r . '}' : $r;
			}
		}
		foreach ( array( 'showAvatar' => array( '.vx-rv-av', '.vxr-avatar' ), 'showDate' => array( '.vx-rv-date', '.vxr-date' ), 'showLogo' => array( '.vx-rv-logo', '.vxr-g' ) ) as $k => $part ) {
			if ( ! self::el_set( $node, $k, '1' ) ) { $css .= $s( $part[0], $part[1] ) . '{display:none}'; }
		}
		return $css;
	}

	/** Example reviews, so a block can be styled before the connection exists. */
	private static function reviews_demo( $node ) {
		$people = array(
			array( 'Anna Weber', 5, __( 'Sehr freundliches Team und schnelle Umsetzung. Jederzeit wieder!', 'velox' ), '2 Wochen' ),
			array( 'Michael Braun', 5, __( 'Top Beratung, faire Preise und alles pünktlich fertig geworden.', 'velox' ), '1 Monat' ),
			array( 'Sarah Klein', 4, __( 'Gute Arbeit, kleine Verzögerung — aber das Ergebnis stimmt.', 'velox' ), '1 Monat' ),
			array( 'Thomas Fischer', 5, __( 'Kompetent, zuverlässig und sehr sauber gearbeitet. Klare Empfehlung.', 'velox' ), '3 Monate' ),
			array( 'Julia Hoffmann', 5, __( 'Von der ersten Anfrage bis zur Übergabe alles reibungslos.', 'velox' ), '4 Monate' ),
			array( 'Daniel Schulz', 4, __( 'Sehr zufrieden mit dem Ergebnis und der Kommunikation.', 'velox' ), '6 Monate' ),
		);
		$count = max( 1, min( count( $people ), (int) self::el_set( $node, 'count', 6 ) ) );
		$min   = (int) self::el_set( $node, 'minStars', 0 );
		$trim  = (int) self::el_set( $node, 'trim', 0 );
		$out   = '<div class="vx-rv" data-vx-demo="1">' .
			'<p class="vx-rv-note">' . esc_html__( 'Example reviews — shown only while you design. Connect Google to show real ones.', 'velox' ) . '</p>' .
			'<div class="vx-rv-list">';
		$shown = 0;
		foreach ( $people as $p ) {
			if ( $shown >= $count ) { break; }
			if ( $min && $p[1] < $min ) { continue; }
			$shown++;
			$text = $p[2];
			if ( $trim && function_exists( 'mb_strimwidth' ) && mb_strlen( $text ) > $trim ) {
				$text = mb_strimwidth( $text, 0, $trim, '…' );
			}
			$initial = mb_substr( $p[0], 0, 1 );
			$out .= '<div class="vx-rv-card">' .
				'<div class="vx-rv-head">' .
					'<span class="vx-rv-av" aria-hidden="true">' . esc_html( $initial ) . '</span>' .
					'<span class="vx-rv-name">' . esc_html( $p[0] ) . '</span>' .
					'<span class="vx-rv-logo" aria-hidden="true">G</span>' .
				'</div>' .
				'<div class="vx-rv-stars" aria-label="' . esc_attr( sprintf( '%d/5', $p[1] ) ) . '">' . str_repeat( '★', $p[1] ) . str_repeat( '☆', 5 - $p[1] ) . '</div>' .
				'<p class="vx-rv-text">' . esc_html( $text ) . '</p>' .
				'<span class="vx-rv-date">' . esc_html( sprintf( __( 'vor %s', 'velox' ), $p[3] ) ) . '</span>' .
			'</div>';
		}
		return $out . '</div></div>';
	}

	/** Ship the element runtime, but only when the page actually uses it. */
	public static function print_element_runtime() {
		if ( ! self::$runtime_used ) {
			return;
		}
		$src = defined( 'VELOX_URL' ) ? VELOX_URL . 'assets/js/velox-elements.js' : '';
		$ver = defined( 'VELOX_VERSION' ) ? VELOX_VERSION : '1';
		if ( $src ) {
			echo "\n" . '<script src="' . esc_url( $src . '?ver=' . $ver ) . '" defer></script>' . "\n";
		}
	}

	/** Colour tokens and the spacing scale as CSS custom properties. */
	public static function token_vars() {
		$tokens = class_exists( 'Velox_Builder' ) ? Velox_Builder::tokens() : array();
		$vars   = '';
		foreach ( (array) ( $tokens['colors'] ?? array() ) as $c ) {
			$name = preg_replace( '/[^a-z0-9\-]/', '', strtolower( (string) ( $c['name'] ?? '' ) ) );
			$val  = self::sanitize_value( (string) ( $c['value'] ?? '' ) );
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
		return $vars;
	}

	/**
	 * What the editor canvas needs to look like the page: token variables and
	 * the global styles. Without it var(--primary) rendered as nothing and the
	 * global heading / body styles were missing while editing.
	 */
	public static function canvas_css() {
		$vars = self::token_vars();
		return ( '' !== $vars ? ':root{' . $vars . '}' : '' ) . self::canvas_fonts()['face'] . self::global_styles_css() . self::media_element_css();
	}

	/**
	 * The site's fonts for the editor canvas: stylesheet links (Google, hosted)
	 * and @font-face rules for self-hosted files — the same sources the page
	 * head uses, so text in the editor is set in the real font.
	 */
	public static function canvas_fonts() {
		$links = array();
		$face  = '';
		if ( ! class_exists( 'Velox_Builder' ) ) {
			return array( 'links' => $links, 'face' => $face );
		}
		foreach ( Velox_Builder::fonts() as $f ) {
			$type = $f['type'] ?? '';
			if ( 'local' === $type && ! empty( $f['files'] ) ) {
				foreach ( (array) $f['files'] as $w => $url ) {
					if ( ! $url ) {
						continue;
					}
					$fmt   = self::font_format( $url );
					$face .= "@font-face{font-family:'" . str_replace( "'", '', (string) $f['name'] ) . "';font-style:normal;font-weight:" . (int) $w
						. ";font-display:swap;src:url('" . esc_url( $url ) . "')" . ( $fmt ? " format('" . $fmt . "')" : '' ) . ';}';
				}
			} elseif ( 'google' === $type && ! empty( $f['name'] ) ) {
				$links[] = self::google_font_url( $f );
			} elseif ( 'url' === $type && ! empty( $f['url'] ) ) {
				$links[] = esc_url_raw( $f['url'] );
			}
		}
		return array( 'links' => array_values( array_filter( $links ) ), 'face' => $face );
	}

	/** Base CSS for the Video and Icon elements (also used by the canvas). */
	public static function media_element_css() {
		return '.vx-video{position:relative;width:100%;aspect-ratio:16/9;background:#0b0f14;overflow:hidden}'
			. '.vx-video iframe,.vx-video video{position:absolute;inset:0;width:100%;height:100%;border:0;display:block;object-fit:cover}'
			. '.vx-icon{display:inline-flex;align-items:center;justify-content:center;line-height:0}'
			. '.vx-icon svg{width:var(--vx-icon-size,32px);height:var(--vx-icon-size,32px)}';
	}

	/** Base look for the archive / search list and the 404 message. */
	private static function loop_css() {
		return '.velox-loop{display:flex;flex-direction:column;gap:28px}'
			. '.velox-loop-head h1{margin:0 0 6px}.velox-loop-desc{opacity:.8}'
			. '.velox-loop-items{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:28px}'
			. '.velox-loop-item{display:flex;flex-direction:column;gap:8px}'
			. '.velox-loop-img img{display:block;width:100%;height:auto;border-radius:10px;aspect-ratio:16/10;object-fit:cover}'
			. '.velox-loop-title{margin:4px 0 0;font-size:1.25em;line-height:1.3}.velox-loop-title a{color:inherit;text-decoration:none}'
			. '.velox-loop-meta{font-size:.85em;opacity:.65}.velox-loop-excerpt p{margin:0}'
			. '.velox-loop-pages ul{display:flex;gap:6px;list-style:none;padding:0;margin:0;flex-wrap:wrap}'
			. '.velox-loop-pages a,.velox-loop-pages span{display:inline-block;padding:6px 12px;border-radius:8px;border:1px solid rgba(0,0,0,.12);text-decoration:none;color:inherit}'
			. '.velox-loop-pages .current{background:rgba(0,0,0,.08)}'
			. '.velox-404{text-align:center;padding:80px 20px}.velox-404 h1{margin:0 0 10px}.velox-404 form{margin-top:20px}';
	}

	/**
	 * The per-element CSS the page would get for a model (text effects, grids,
	 * presets …), so the editor canvas shows those settings too. It used to be
	 * built only on the page, so changing those settings did nothing on screen.
	 */
	public static function ajax_preview_css() {
		$raw   = isset( $_POST['model'] ) ? wp_unslash( $_POST['model'] ) : '';
		$model = json_decode( (string) $raw, true );
		if ( ! is_array( $model ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid document data.', 'velox' ) ), 400 );
		}
		self::$float_css    = '';
		self::$runtime_used = array();
		// Only the CSS is wanted: no shortcodes, menus or post content are run.
		self::$css_only = true;
		ob_start();
		self::render_tree( $model['tree'] ?? array(), $model );
		ob_end_clean();
		self::$css_only = false;
		wp_send_json_success( array( 'css' => self::element_base_css() ) );
	}

	/** Embed URL for a YouTube / Vimeo link, or '' when it is neither. */
	public static function video_embed( $url, $autoplay = false ) {
		$url = trim( (string) $url );
		$q   = $autoplay ? '?autoplay=1&mute=1&loop=1&playsinline=1' : '';
		if ( preg_match( '~(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/)|youtu\.be/)([A-Za-z0-9_-]{6,})~', $url, $m ) ) {
			return 'https://www.youtube-nocookie.com/embed/' . $m[1] . ( $autoplay ? $q . '&playlist=' . $m[1] : '' );
		}
		if ( preg_match( '~vimeo\.com/(?:video/)?(\d+)~', $url, $m ) ) {
			return 'https://player.vimeo.com/video/' . $m[1] . ( $autoplay ? '?autoplay=1&muted=1&loop=1&background=1' : '' );
		}
		return '';
	}

	/** Baseline CSS for runtime elements, so they look right before any styling. */
	public static function element_base_css( $part = 'all' ) {
		// Per-element CSS ($float_css) is written by many elements that need no
		// runtime at all — returning early here dropped it on every simple page.
		if ( ! self::$runtime_used && '' === (string) self::$float_css ) {
			return '';
		}
		// The per-instance rules (#id …) only, for after the class stylesheet.
		if ( 'float' === $part ) {
			return (string) self::$float_css;
		}
		$css = '';
		if ( isset( self::$runtime_used['reviews'] ) ) {
			$css .= '.vx-rv-note{font-size:12px;opacity:.6;margin:0 0 10px}' .
				'.vx-rv-head{display:flex;align-items:center;gap:9px;margin-bottom:8px}' .
				'.vx-rv-av{width:32px;height:32px;border-radius:50%;display:grid;place-items:center;background:#e8eaed;color:#3c4043;font-weight:700;font-size:14px}' .
				'.vx-rv-name{font-weight:600;flex:1}' .
				'.vx-rv-logo{width:20px;height:20px;display:grid;place-items:center;border-radius:50%;background:#fff;border:1px solid #dadce0;font:700 12px/1 Arial,sans-serif;color:#4285f4}' .
				'.vx-rv-stars{letter-spacing:1px;margin-bottom:6px}' .
				'.vx-rv-text{margin:0 0 8px;line-height:1.55}' .
				'.vx-rv-date{font-size:12px;opacity:.6}';
		}
		if ( isset( self::$runtime_used['nav'] ) ) {
			$css .= '.vx-nav-list{display:flex;align-items:center;gap:22px;list-style:none;margin:0;padding:0;flex-wrap:wrap}' .
				'.vx-nav-item{position:relative;display:flex;align-items:center;gap:4px}' .
				'.vx-nav-item a{text-decoration:none;color:inherit}' .
				'.vx-nav-item a[aria-current="page"]{font-weight:700}' .
				'.vx-sub-btn{background:none;border:none;padding:2px;cursor:pointer;color:inherit;line-height:0}' .
				'.vx-sub-btn[aria-expanded="true"] svg{transform:rotate(180deg)}' .
				'.vx-submenu{position:absolute;top:100%;left:0;min-width:190px;margin:0;padding:8px;list-style:none;background:#fff;border-radius:10px;box-shadow:0 10px 30px rgba(0,0,0,.14);z-index:50}' .
				'.vx-submenu[hidden]{display:none}' .
				'.vx-submenu a{display:block;padding:8px 10px;border-radius:6px}' .
				'.vx-burger{background:none;border:none;padding:10px;cursor:pointer;color:inherit}' .
				'.vx-burger[hidden]{display:none}' .
				'.vx-burger-i,.vx-burger-i::before,.vx-burger-i::after{display:block;width:22px;height:2px;background:currentColor;content:""}' .
				'.vx-burger-i::before{transform:translateY(-7px)}.vx-burger-i::after{transform:translateY(5px)}' .
				'.vx-nav.is-mobile .vx-nav-list{position:absolute;top:100%;left:0;right:0;flex-direction:column;align-items:stretch;gap:0;background:#fff;padding:8px;box-shadow:0 10px 30px rgba(0,0,0,.14);z-index:49}' .
				'.vx-nav.is-mobile{position:relative}' .
				'.vx-nav.is-mobile .vx-nav-list[hidden]{display:none}' .
				'.vx-nav.is-mobile .vx-nav-item{padding:10px 8px}' .
				'.vx-nav.is-mobile .vx-submenu{position:static;box-shadow:none;padding-left:14px}' .
				'.vx-nav-full.is-mobile .vx-nav-list{position:fixed;inset:0;z-index:9996;justify-content:center;align-items:center;padding:72px 24px;overflow:auto;font-size:1.25em;box-shadow:none}' .
				'.vx-nav-full.is-mobile.is-open .vx-burger{position:fixed;top:14px;right:14px;z-index:9997}' .
				'.vx-crumb-list{display:flex;flex-wrap:wrap;align-items:center;gap:8px;list-style:none;margin:0;padding:0}' .
				'.vx-crumb{display:flex;align-items:center;gap:8px}' .
				'.vx-anchornav a.is-current{font-weight:700}' .
				'[data-vx-sticky]{transition:transform .2s ease,padding .2s ease}' .
				'[data-vx-sticky].is-hidden{transform:translateY(-100%)}' .
				'@media (prefers-reduced-motion:reduce){[data-vx-sticky]{transition:none}}';
			if ( isset( self::$runtime_used['navshrink'] ) ) {
				$css .= '[data-vx-sticky].is-stuck{padding-top:6px;padding-bottom:6px}';
			}
		}
		if ( isset( self::$runtime_used['floating'] ) ) {
			$css .= '.vx-fab{display:inline-flex;align-items:center;gap:8px;padding:14px;border-radius:999px;border:none;cursor:pointer;text-decoration:none;box-shadow:0 6px 20px rgba(0,0,0,.18);background:#111827;color:#fff}' .
				'.vx-fab[hidden]{display:none}' .
				'.vx-fab.is-shown{display:inline-flex}' .
				'.vx-bar{display:flex;align-items:center;justify-content:center;gap:12px;background:#111827;color:#fff}' .
				'.vx-bar[hidden]{display:none}.vx-bar.is-shown{display:flex}' .
				'.vx-bar-link{color:inherit;text-decoration:underline}' .
				'.vx-bar-btn{flex:1;display:flex;flex-direction:column;align-items:center;gap:3px;padding:12px 8px;color:inherit;text-decoration:none;font-size:12px}' .
				'.vx-bar-x{margin-left:auto;background:none;border:none;color:inherit;cursor:pointer;padding:6px;line-height:0}';
			if ( isset( self::$runtime_used['fabanim'] ) ) {
				$css .= '@keyframes vxPulse{0%,100%{transform:scale(1)}50%{transform:scale(1.08)}}' .
					'@keyframes vxBounce{0%,100%{transform:translateY(0)}50%{transform:translateY(-6px)}}' .
					'@keyframes vxShake{0%,100%{transform:rotate(0)}25%{transform:rotate(-8deg)}75%{transform:rotate(8deg)}}' .
					'.vx-idle-pulse{animation:vxPulse 2.4s ease-in-out infinite}' .
					'.vx-idle-bounce{animation:vxBounce 2.4s ease-in-out infinite}' .
					'.vx-idle-shake{animation:vxShake 3s ease-in-out infinite}' .
					'@media (prefers-reduced-motion:reduce){.vx-idle-pulse,.vx-idle-bounce,.vx-idle-shake{animation:none}}';
			}
		}
		// Per-element CSS is written by many elements — text effects, the utility
		// presets, the grid layouts, the ratio box, reviews styling — and has
		// nothing to do with navigation. It used to be emitted inside the block
		// above, so on a page with no navigation element every one of those
		// elements silently lost its styling.
		if ( self::$float_css && 'base' !== $part ) {
			$css .= self::$float_css;
		}
		if ( isset( self::$runtime_used['slider'] ) ) {
			$css .= '.vx-sr{position:absolute!important;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap;border:0}' .
				'.vx-slider{position:relative}' .
				'.vx-track{display:flex;overflow-x:auto;scroll-snap-type:x mandatory;-webkit-overflow-scrolling:touch;scrollbar-width:none}' .
				'.vx-track::-webkit-scrollbar{display:none}' .
				'.vx-slide{scroll-snap-align:start;min-width:0}' .
				'.vx-slide img{width:100%;height:auto;display:block}' .
				'.vx-controls{display:flex;align-items:center;gap:14px;margin-top:14px}' .
				'.vx-dots{display:flex;gap:8px;flex:1;justify-content:center}' .
				'.vx-dot{width:9px;height:9px;padding:0;border:none;border-radius:50%;background:currentColor;opacity:.25;cursor:pointer}' .
				'.vx-dot[aria-selected="true"]{opacity:1}' .
				'.vx-arrows{display:flex;gap:8px}' .
				// Arrows over or beside the track. Both lift the arrow row out of
				// the control strip and centre it vertically against the slides.
				'.vx-arrows-inside .vx-controls,.vx-arrows-outside .vx-controls{position:static}' .
				'.vx-arrows-inside .vx-arrows,.vx-arrows-outside .vx-arrows{position:absolute;top:50%;left:0;right:0;transform:translateY(-50%);justify-content:space-between;pointer-events:none;z-index:2}' .
				'.vx-arrows-inside .vx-arrows{padding:0 10px}' .
				'.vx-arrows-outside .vx-arrows{left:-46px;right:-46px}' .
				'.vx-arrows-inside .vx-prev,.vx-arrows-inside .vx-next,.vx-arrows-outside .vx-prev,.vx-arrows-outside .vx-next{pointer-events:auto;background:var(--vx-arrow-bg,rgba(255,255,255,.9))}' .
				'.vx-prev,.vx-next,.vx-play{width:36px;height:36px;border-radius:50%;border:1px solid currentColor;background:none;color:inherit;cursor:pointer;display:grid;place-items:center;opacity:.75}' .
				'.vx-prev:hover,.vx-next:hover,.vx-play:hover{opacity:1}' .
				'.vx-prev[disabled],.vx-next[disabled]{opacity:.25;cursor:default}' .
				'.vx-play-i{width:0;height:0;border-style:solid;border-width:6px 0 6px 9px;border-color:transparent transparent transparent currentColor}' .
				'.vx-slider.is-playing .vx-play-i{width:9px;height:11px;border:none;background:linear-gradient(to right,currentColor 0 3px,transparent 3px 6px,currentColor 6px 9px)}' .
				'@media (prefers-reduced-motion:reduce){.vx-track{scroll-behavior:auto}}';
		}
		if ( isset( self::$runtime_used['overlay'] ) ) {
			$css .= '.vx-sr{position:absolute!important;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap;border:0}' .
				'.vx-ov[hidden]{display:none}' .
				'.vx-ov{position:fixed;inset:0;z-index:9998;display:flex}' .
				'.vx-ov-back{position:absolute;inset:0;z-index:0;opacity:0;transition:opacity .25s ease}' .
				'.vx-ov.is-open .vx-ov-back{opacity:1}' .
				'.vx-ov-panel{position:relative;z-index:2;max-height:100%;overflow:auto;background:#fff;min-height:56px;transition:transform .25s ease,opacity .25s ease}' .
				'.vx-ov-close{position:absolute;z-index:3;top:12px;right:12px;background:none;border:none;padding:6px;cursor:pointer;color:inherit;line-height:0}' .
				// offcanvas: slides in from its edge
				'.vx-offcanvas .vx-ov-panel{height:100%}' .
				'.vx-oc-right{justify-content:flex-end}.vx-oc-left{justify-content:flex-start}' .
				'.vx-oc-top{align-items:flex-start}.vx-oc-bottom{align-items:flex-end}' .
				'.vx-oc-right .vx-ov-panel{transform:translateX(100%)}.vx-oc-left .vx-ov-panel{transform:translateX(-100%)}' .
				'.vx-oc-top .vx-ov-panel{transform:translateY(-100%)}.vx-oc-bottom .vx-ov-panel{transform:translateY(100%)}' .
				'.vx-ov.is-open .vx-ov-panel{transform:none}' .
				// modal: centred box that scales in
				'.vx-modal{align-items:center;justify-content:center;padding:24px}' .
				'.vx-modal-top{align-items:flex-start}.vx-modal-bottom{align-items:flex-end}' .
				'.vx-modal .vx-ov-panel{width:100%;transform:scale(.96);opacity:0}' . '.vx-modal .vx-ov-panel,.vx-offcanvas .vx-ov-panel{padding-top:44px}' .
				'.vx-modal.is-open .vx-ov-panel{transform:none;opacity:1}' .
				// dropdown: anchored to its button, not the viewport
				'.vx-dd-wrap{position:relative;display:inline-block}' .
				'.vx-dd-btn{display:inline-flex;align-items:center;gap:6px;font:inherit;background:none;border:none;cursor:pointer;color:inherit}' .
				'.vx-dropdown{position:absolute;inset:auto;top:100%;z-index:60;display:block}' .
				'.vx-dd-left{left:0}.vx-dd-right{right:0}' .
				'.vx-dropdown .vx-ov-panel{transform:translateY(-6px);opacity:0}' .
				'.vx-dropdown.is-open .vx-ov-panel{transform:none;opacity:1}' .
				'@media (prefers-reduced-motion:reduce){.vx-ov-panel,.vx-ov-back{transition:none}}';
		}
		if ( isset( self::$runtime_used['tabs'] ) ) {
			$bp   = class_exists( 'Velox_Builder' ) ? Velox_Builder::breakpoints() : array( 'mobile' => 767 );
			$css .= '.vx-tablist{display:flex;gap:4px;flex-wrap:wrap;border-bottom:1px solid rgba(0,0,0,.12)}' .
				'.vx-tabs-left{display:grid;grid-template-columns:minmax(140px,auto) 1fr;gap:24px}' .
				'.vx-tabs-left .vx-tablist{flex-direction:column;border-bottom:none;border-right:1px solid rgba(0,0,0,.12)}' .
				'.vx-tab{padding:10px 14px;background:none;border:none;font:inherit;font-weight:600;color:inherit;opacity:.65;cursor:pointer;border-bottom:2px solid transparent;margin-bottom:-1px}' .
				'.vx-tab[aria-selected="true"]{opacity:1;border-bottom-color:currentColor}' .
				'.vx-tabs-left .vx-tab{border-bottom:none;border-right:2px solid transparent;margin:0 -1px 0 0;text-align:left}' .
				'.vx-tabs-left .vx-tab[aria-selected="true"]{border-right-color:currentColor}' .
				'.vx-tabp{padding:18px 4px}.vx-tabp[hidden]{display:none}' .
				// Accordion fallback: the tab button moves above its own panel.
				'@media (max-width:' . (int) $bp['mobile'] . 'px){' .
					'.vx-tabs-acc{display:flex;flex-direction:column}' .
					'.vx-tabs-acc .vx-tablist,.vx-tabs-acc .vx-tabpanels{display:contents;border:none}' .
					'.vx-tabs-acc .vx-tab,.vx-tabs-acc .vx-tabp{order:var(--vx-o,0)}' .
					'.vx-tabs-acc .vx-tab{display:block;width:100%;border:none;border-top:1px solid rgba(0,0,0,.12);margin:0;text-align:left}' .
					'.vx-tabs-acc .vx-tabp{padding:0 4px 16px}' .
				'}';
		}
		if ( isset( self::$runtime_used['accordion'] ) ) {
			$css .= '.vx-acc-h{margin:0}' .
				'.vx-acc-btn{display:flex;align-items:center;gap:12px;width:100%;padding:14px 4px;background:none;border:none;font:inherit;font-weight:600;color:inherit;cursor:pointer;text-align:left}' .
				'.vx-acc-t{flex:1}' .
				'.vx-acc-i{flex:0 0 auto;transition:transform .2s}' .
				'.vx-acc-item.is-open .vx-acc-i{transform:rotate(180deg)}' .
				'.vx-acc-left .vx-acc-btn{flex-direction:row-reverse}' .
				'.vx-acc-p{overflow:hidden}' .
				'.vx-acc-p[hidden]{display:none}' .
				'@media (prefers-reduced-motion:reduce){.vx-acc-i{transition:none}}';
		}
		return $css;
	}

	/** Print the full standalone HTML document. */
	private static function output_page( $doc ) {
		self::$page_settings = isset( $doc['page'] ) && is_array( $doc['page'] ) ? $doc['page'] : array();
		// The template's own CSS and JS used to be dropped on the floor: only the
		// page being rendered was consulted. That forced every shell style to be
		// duplicated into every page. The template's assets are collected below
		// once the template is resolved, and emitted BEFORE the page's own, so a
		// page can still override the shell.
		// A template wraps the page: we render the TEMPLATE's tree, and wherever it
		// contains an Inner Content element we drop this page's own tree in. If the
		// template has no Inner Content the page would vanish, so we fall back to
		// appending it after the template rather than silently losing it.
		$template = null;
		if ( class_exists( 'Velox_Builder' ) ) {
			$tpl_id = isset( $doc['__tpl'] ) ? (int) $doc['__tpl'] : Velox_Builder::template_for_post( get_queried_object_id() );
			if ( $tpl_id ) {
				$template = Velox_Builder::doc_model( $tpl_id, self::$previewing );
			}
		}

		// Template-level CSS/JS: prepended, so page rules still win on conflict.
		if ( $template && ! empty( $template['page'] ) && is_array( $template['page'] ) ) {
			$tp = $template['page'];
			foreach ( array( 'css', 'js' ) as $k ) {
				if ( empty( $tp[ $k ] ) ) {
					continue;
				}
				self::$page_settings[ $k ] = trim( (string) $tp[ $k ] )
					. "\n" . ( isset( self::$page_settings[ $k ] ) ? (string) self::$page_settings[ $k ] : '' );
			}
		}

		// The older header/footer "roles" and the newer template system both wrap
		// the page. Running both gives you two navbars, so a template wins and the
		// roles stand down for this request.
		$header = null;
		$footer = null;
		// …and a page set to "No template" gets neither.
		$opted_out = is_singular() && '-1' === (string) get_post_meta( get_queried_object_id(), '_velox_template', true );
		if ( ! $template && ! $opted_out && class_exists( 'Velox_Builder' ) ) {
			$roles  = Velox_Builder::roles();
			$header = $roles['header'] ? Velox_Builder::doc_model( $roles['header'] ) : null;
			$footer = $roles['footer'] ? Velox_Builder::doc_model( $roles['footer'] ) : null;
		}

		// Build the effective CSS from the page + any reusables it references +
		// the active header/footer templates, so every class the visitor can see
		// is covered by exactly one stylesheet.
		$css_url = self::ensure_css_file( $doc, array( $header, $footer, $template ) );

		$body  = '';
		$body .= $header ? '<header class="velox-template-header">' . self::render_tree( $header['tree'], $header ) . '</header>' : '';
		if ( $template && ! empty( $template['tree'] ) ) {
			self::$inner_doc = $doc;
			self::$inner_html = isset( $doc['__html'] ) ? $doc['__html'] : '';
			self::$inner_used = false;
			self::$inner_depth = 0;
			$body .= self::render_tree( $template['tree'], $template );
			if ( ! self::$inner_used ) {
				$body .= self::render_tree( $doc['tree'], $doc );
				// A template without an Inner Content slot still shows the list of
				// posts (archives / search) rather than dropping it.
				if ( empty( $doc['tree'] ) && ! empty( $doc['__html'] ) ) {
					$body .= '<div class="velox-inner-content">' . $doc['__html'] . '</div>';
				}
			}
			self::$inner_doc = null;
		} else {
			$body .= self::render_tree( $doc['tree'], $doc );
		}
		$body .= $footer ? '<footer class="velox-template-footer">' . self::render_tree( $footer['tree'], $footer ) . '</footer>' : '';

		header( 'Content-Type: text/html; charset=' . get_bloginfo( 'charset' ) );
		?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php
	// Element defaults (.vx-fab, .vx-bar, .vx-video …) go BEFORE the class
	// stylesheet: printed after it, they beat the user's own class styling
	// (same specificity, later wins) — the editor showed it, the page did not.
	$base_css = self::element_base_css( 'base' ) . ( self::$media_used ? self::media_element_css() : '' );
	if ( '' !== $base_css ) {
		echo '<style id="velox-element-base">' . $base_css . '</style>' . "\n"; // phpcs:ignore
	}
	?>
	<?php if ( $css_url ) : ?>
		<link rel="stylesheet" id="velox-builder-css" href="<?php echo esc_url( $css_url ); ?>">
	<?php endif; ?>
	<?php self::print_global_head(); ?>
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'velox-built' ); ?>>
	<?php echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — built from sanitized model ?>
	<?php self::print_element_runtime(); ?>
	<?php self::print_aos(); ?>
	<?php
	if ( ! empty( self::$page_settings['js'] ) ) {
		echo "\n<script id=\"velox-page-js\">\n" . str_ireplace( '</script', '<\\/script', (string) self::$page_settings['js'] ) . "\n</script>\n"; // phpcs:ignore
	}
	?>
	<?php if ( method_exists( 'Velox_Builder', 'print_global_js' ) ) { Velox_Builder::print_global_js( 'footer' ); } ?>
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
		$fonts    = Velox_Builder::fonts();
		$has_goog = false;
		foreach ( $fonts as $f ) {
			if ( 'google' === ( $f['type'] ?? '' ) && ! empty( $f['name'] ) ) {
				$has_goog = true;
				break;
			}
		}
		// Google serves the CSS from one host and the font files from another, so
		// warming both saves a round trip before the text can paint.
		if ( $has_goog ) {
			echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
			echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
		}
		$face = '';
		foreach ( $fonts as $f ) {
			$type    = $f['type'] ?? '';
			$preload = (array) ( $f['preload'] ?? array() );

			// Self-hosted: one @font-face per weight, and preload the actual FILES
			// (which is what preload is for) rather than a stylesheet.
			if ( 'local' === $type && ! empty( $f['files'] ) ) {
				foreach ( (array) $f['files'] as $w => $url ) {
					if ( ! $url ) {
						continue;
					}
					$fmt   = self::font_format( $url );
					$face .= "@font-face{font-family:'" . str_replace( "'", '', $f['name'] ) . "';font-style:normal;font-weight:" . (int) $w .
						";font-display:" . Velox_Builder::font_display() . ";src:url('" . esc_url( $url ) . "')" . ( $fmt ? " format('" . $fmt . "')" : '' ) . ";}\n";
					if ( in_array( (string) $w, $preload, true ) ) {
						echo '<link rel="preload" as="font" type="font/' . esc_attr( $fmt ? $fmt : 'woff2' ) . '" href="' . esc_url( $url ) . '" crossorigin>' . "\n";
					}
				}
				continue;
			}

			$href = '';
			if ( 'google' === $type && ! empty( $f['name'] ) ) {
				$href = self::google_font_url( $f );
			} elseif ( 'url' === $type && ! empty( $f['url'] ) ) {
				$href = $f['url'];
			}
			if ( '' === $href ) {
				continue;
			}
			// For a hosted stylesheet we can only prioritise the sheet itself.
			if ( $preload ) {
				echo '<link rel="preload" as="style" href="' . esc_url( $href ) . '" onload="this.onload=null;this.rel=\'stylesheet\'">' . "\n";
				echo '<noscript><link rel="stylesheet" href="' . esc_url( $href ) . '"></noscript>' . "\n";
			} else {
				echo '<link rel="stylesheet" href="' . esc_url( $href ) . '">' . "\n";
			}
		}
		if ( '' !== $face ) {
			echo '<style id="velox-builder-fontface">' . $face . '</style>' . "\n"; // phpcs:ignore
		}
		$vars = self::token_vars();
		if ( '' !== $vars ) {
			echo '<style id="velox-builder-tokens">:root{' . $vars . '}</style>' . "\n"; // phpcs:ignore
		}
		// Per-page overrides sit after the global styles so a page can win.
		$page_css = '';
		if ( ! empty( self::$page_settings['width'] ) ) {
			$page_css .= ':where(.velox-built .section > *,.velox-inner-content){max-width:' . (int) self::$page_settings['width'] . 'px;margin-left:auto;margin-right:auto;}';
		}
		$ov = self::$page_settings['overlay'] ?? '';
		if ( $ov ) {
			// Lift the header out of the flow so the first section starts at the top.
			$rule = '.velox-template-header{position:absolute;top:0;left:0;right:0;z-index:50;}';
			if ( 'always' === $ov ) {
				$page_css .= $rule;
			} else {
				$b   = class_exists( 'Velox_Builder' ) ? Velox_Builder::breakpoints() : array( 'tablet' => 991 );
				$min = ( 'desktop' === $ov ) ? ( (int) $b['tablet'] + 1 ) : ( (int) $b['mobile'] + 1 );
				$page_css .= '@media (min-width:' . $min . 'px){' . $rule . '}';
			}
		}
		if ( ! empty( self::$page_settings['background'] ) ) {
			$page_css .= 'body.velox-built{background:' . self::sanitize_value( self::$page_settings['background'] ) . ';}';
		}
		// Raw per-page CSS goes last so it beats everything Velox generated.
		if ( ! empty( self::$page_settings['css'] ) ) {
			$page_css .= str_ireplace( array( '</style', '<script' ), '', (string) self::$page_settings['css'] );
		}
		$gs = self::element_base_css( 'float' ) . self::global_styles_css() . ( self::$loop_used ? self::loop_css() : '' ) . $page_css;
		if ( '' !== $gs ) {
			echo '<style id="velox-builder-global-styles">' . $gs . '</style>' . "\n"; // phpcs:ignore
		}
		// Global CSS files (apply to every Velox page).
		$gcss = Velox_Builder::global_css();
		if ( '' !== trim( $gcss ) ) {
			echo '<style id="velox-builder-global-css">' . $gcss . '</style>' . "\n"; // phpcs:ignore
		}
		// Global JS set to load in the head.
		if ( method_exists( 'Velox_Builder', 'print_global_js' ) ) {
			Velox_Builder::print_global_js( 'head' );
		}
	}




	/**
	 * Global styles as real CSS. Only properties that were actually set are
	 * emitted, so an untouched field never overrides an element's own styling.
	 */
	public static function global_styles_css() {
		if ( ! class_exists( 'Velox_Builder' ) ) {
			return '';
		}
		$g   = Velox_Builder::global_styles();
		$out = '';
		$px  = function ( $v ) { return ( '' === $v || null === $v ) ? '' : ( is_numeric( $v ) ? $v . 'px' : $v ); };

		$b = $g['body'];
		$decl = '';
		if ( '' !== $b['font'] ) { $decl .= "font-family:'" . str_replace( "'", '', $b['font'] ) . "',sans-serif;"; }
		if ( '' !== $b['size'] ) { $decl .= 'font-size:' . $px( $b['size'] ) . ';'; }
		if ( '' !== $b['weight'] ) { $decl .= 'font-weight:' . (int) $b['weight'] . ';'; }
		if ( '' !== $b['lineHeight'] ) { $decl .= 'line-height:' . $b['lineHeight'] . ';'; }
		if ( '' !== $b['color'] ) { $decl .= 'color:' . $b['color'] . ';'; }
		if ( '' !== $decl ) { $out .= 'body{' . $decl . '}'; }

		$hfont = $g['headings']['font'] ?? '';
		foreach ( array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ) as $tag ) {
			$h    = $g['headings'][ $tag ] ?? array();
			$decl = '';
			if ( '' !== $hfont ) { $decl .= "font-family:'" . str_replace( "'", '', $hfont ) . "',sans-serif;"; }
			if ( ! empty( $h['size'] ) ) { $decl .= 'font-size:' . $px( $h['size'] ) . ';'; }
			if ( ! empty( $h['weight'] ) ) { $decl .= 'font-weight:' . (int) $h['weight'] . ';'; }
			if ( ! empty( $h['lineHeight'] ) ) { $decl .= 'line-height:' . $h['lineHeight'] . ';'; }
			if ( ! empty( $h['color'] ) ) { $decl .= 'color:' . $h['color'] . ';'; }
			if ( '' !== $decl ) { $out .= ':where(' . $tag . '){' . $decl . '}'; }
		}

		// Global styles are defaults: wrapped in :where() they carry no weight, so
		// anything set on an element or class in the editor always wins — before,
		// "a:hover" beat a button's own hover colour and the section padding beat
		// the padding you set on a section.
		$l = $g['links'];
		$decl = '';
		if ( '' !== $l['color'] ) { $decl .= 'color:' . $l['color'] . ';'; }
		if ( '' !== $l['decoration'] ) { $decl .= 'text-decoration:' . $l['decoration'] . ';'; }
		if ( '' !== $l['weight'] ) { $decl .= 'font-weight:' . (int) $l['weight'] . ';'; }
		if ( '' !== $decl ) { $out .= ':where(a){' . $decl . '}'; }
		if ( '' !== $l['hover'] ) { $out .= ':where(a:hover){color:' . $l['hover'] . ';}'; }

		// "Leave empty to inherit": an empty page width means the default, not none.
		$pw = ( '' === (string) ( $g['width']['page'] ?? '' ) ) ? '1200' : $g['width']['page'];
		if ( ! empty( $pw ) ) {
			$out .= ':where(.velox-built .section > *,.velox-inner-content){max-width:' . $px( $pw ) . ';margin-left:auto;margin-right:auto;}';
		}
		foreach ( array( 'sections' => '.section', 'columns' => '.columns' ) as $key => $sel ) {
			$p = $g[ $key ];
			$decl = '';
			foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
				if ( '' !== ( $p[ $side ] ?? '' ) ) { $decl .= 'padding-' . $side . ':' . $px( $p[ $side ] ) . ';'; }
			}
			if ( '' !== $decl ) { $out .= ':where(' . $sel . '){' . $decl . '}'; }
		}
		return $out;
	}


	/**
	 * Animate-on-scroll runtime. Deliberately tiny and dependency-free: one
	 * IntersectionObserver, CSS does the animating. Printed only when the page
	 * actually contains an animated element, and skipped entirely for visitors
	 * who have asked for reduced motion.
	 */
	public static function print_aos() {
		if ( ! self::$aos_used || ! class_exists( 'Velox_Builder' ) ) {
			return;
		}
		$a        = Velox_Builder::global_styles()['aos'] ?? array();
		$duration = (int) ( ( '' !== ( self::$page_settings['aosDuration'] ?? '' ) ) ? self::$page_settings['aosDuration'] : ( $a['duration'] ?? 600 ) );
		$easing   = $a['easing'] ?? 'ease';
		$offset   = (int) ( $a['offset'] ?? 120 );
		$delay    = (int) ( ( '' !== ( self::$page_settings['aosDelay'] ?? '' ) ) ? self::$page_settings['aosDelay'] : ( $a['delay'] ?? 0 ) );
		$once     = ! empty( $a['once'] );
		$disable  = $a['disable'] ?? '';
		$bp       = Velox_Builder::breakpoints();
		$off_at   = 'mobile' === $disable ? (int) $bp['mobile'] : ( 'tablet' === $disable ? (int) $bp['tablet'] : 0 );

		$css = '[data-vx-aos]{opacity:0;will-change:opacity,transform;' .
			'transition:opacity ' . $duration . 'ms ' . $easing . ',transform ' . $duration . 'ms ' . $easing . ';}' .
			'[data-vx-aos="fade-up"]{transform:translateY(24px);}' .
			'[data-vx-aos="fade-down"]{transform:translateY(-24px);}' .
			'[data-vx-aos="fade-left"]{transform:translateX(-24px);}' .
			'[data-vx-aos="fade-right"]{transform:translateX(24px);}' .
			'[data-vx-aos="zoom-in"]{transform:scale(.94);}' .
			'[data-vx-aos="zoom-out"]{transform:scale(1.06);}' .
			'[data-vx-aos].vx-in{opacity:1;transform:none;}' .
			'@media (prefers-reduced-motion:reduce){[data-vx-aos]{opacity:1!important;transform:none!important;transition:none!important;}}';
		if ( $off_at ) {
			$css .= '@media (max-width:' . $off_at . 'px){[data-vx-aos]{opacity:1!important;transform:none!important;transition:none!important;}}';
		}
		echo '<style id="velox-aos-css">' . $css . '</style>' . "\n"; // phpcs:ignore
		?>
<script id="velox-aos-js">
(function(){
	var OFF = <?php echo $off_at; ?>, ONCE = <?php echo $once ? 'true' : 'false'; ?>;
	if ( window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches ) { return; }
	if ( OFF && window.innerWidth <= OFF ) { return; }
	var els = document.querySelectorAll('[data-vx-aos]');
	if ( ! els.length ) { return; }
	// No IntersectionObserver (or no JS at all): show everything rather than
	// leaving the page invisible.
	if ( ! ('IntersectionObserver' in window) ) {
		for ( var i = 0; i < els.length; i++ ) { els[i].classList.add('vx-in'); }
		return;
	}
	var io = new IntersectionObserver(function(entries){
		entries.forEach(function(en){
			if ( ! en.isIntersecting ) {
				if ( ! ONCE ) { en.target.classList.remove('vx-in'); }
				return;
			}
			var el = en.target;
			var d = parseInt(el.getAttribute('data-vx-aos-delay') || '<?php echo $delay; ?>', 10) || 0;
			var dur = el.getAttribute('data-vx-aos-duration');
			if ( dur ) { el.style.transitionDuration = parseInt(dur, 10) + 'ms'; }
			setTimeout(function(){ el.classList.add('vx-in'); }, d);
			if ( ONCE ) { io.unobserve(el); }
		});
	}, { rootMargin: '0px 0px -<?php echo $offset; ?>px 0px', threshold: 0.01 });
	for ( var j = 0; j < els.length; j++ ) { io.observe(els[j]); }
}());
</script>
		<?php
	}

	/** Map a font file extension to its CSS src format() keyword. */
	public static function font_format( $url ) {
		$ext = strtolower( pathinfo( wp_parse_url( $url, PHP_URL_PATH ) ?? '', PATHINFO_EXTENSION ) );
		$map = array( 'woff2' => 'woff2', 'woff' => 'woff', 'ttf' => 'truetype', 'otf' => 'opentype' );
		return $map[ $ext ] ?? '';
	}

	/** Build a Google Fonts URL from only the weights and options chosen. */
	public static function google_font_url( $f ) {
		$fam     = str_replace( ' ', '+', $f['name'] );
		$weights = ( ! empty( $f['weights'] ) && is_array( $f['weights'] ) ) ? $f['weights'] : array( '400', '700' );
		sort( $weights, SORT_NUMERIC );
		$display = class_exists( 'Velox_Builder' ) ? Velox_Builder::font_display() : ( $f['display'] ?? 'swap' );
		if ( ! empty( $f['italic'] ) ) {
			// The ital axis needs every weight listed twice, upright then italic,
			// and the pairs must be in ascending order or Google rejects the URL.
			$pairs = array();
			foreach ( $weights as $w ) {
				$pairs[] = '0,' . $w;
			}
			foreach ( $weights as $w ) {
				$pairs[] = '1,' . $w;
			}
			$axis = 'ital,wght@' . implode( ';', $pairs );
		} else {
			$axis = 'wght@' . implode( ';', $weights );
		}
		return 'https://fonts.googleapis.com/css2?family=' . $fam . ':' . $axis . '&display=' . $display;
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

	private static $inner_doc = null;
	private static $inner_used = false;
	private static $inner_depth = 0;
	private static $inner_html = '';
	private static $aos_used = false;
	private static $page_settings = array();

	private static function render_node( $node, $doc ) {
		// Elements hidden in the builder stay in the document but are never output
		// on the front end. The whole subtree goes with them.
		if ( ! empty( $node['hidden'] ) ) {
			return '';
		}
		if ( isset( $node['el'] ) && in_array( $node['el'], array( 'Navbar', 'Breadcrumbs', 'Anchornav' ), true ) ) {
			$nc = array();
			foreach ( (array) ( $node['classes'] ?? array() ) as $c ) { $nc[] = sanitize_html_class( ltrim( $c, '.' ) ); }
			return self::render_nav( $node, $doc, implode( ' ', array_filter( $nc ) ) );
		}
		if ( isset( $node['el'] ) && in_array( $node['el'], array( 'Fab', 'Backtotop', 'Announcebar', 'Stickybar', 'Progressbar' ), true ) ) {
			$fc = array();
			foreach ( (array) ( $node['classes'] ?? array() ) as $c ) { $fc[] = sanitize_html_class( ltrim( $c, '.' ) ); }
			return self::render_floating( $node, $doc, implode( ' ', array_filter( $fc ) ) );
		}
		if ( isset( $node['el'] ) && in_array( $node['el'], array( 'Gradienttext', 'Outlinetext', 'Highlighttext', 'Dropcap', 'Verticaltext' ), true ) ) {
			self::text_effect_css( $node );
		}
		if ( isset( $node['el'] ) && in_array( $node['el'], array( 'Sronly', 'Skiplink', 'Anchortarget', 'Avatargroup', 'Skeleton' ), true ) ) {
			self::utility_effect_css( $node );
		}
		if ( isset( $node['el'] ) && in_array( $node['el'], array( 'Bento', 'Statsrow', 'Featuregrid', 'Splitscreen', 'Sidebarlayout', 'Logostrip' ), true ) ) {
			self::layout_effect_css( $node );
		}
		if ( isset( $node['el'] ) && in_array( $node['el'], array( 'Ratiobox', 'Trustrow', 'Paymenticons', 'Pullquote', 'Eyebrow' ), true ) ) {
			self::trim_effect_css( $node );
		}
		if ( isset( $node['el'] ) && 'Slider' === $node['el'] ) {
			$sc = array();
			foreach ( (array) ( $node['classes'] ?? array() ) as $c ) { $sc[] = sanitize_html_class( ltrim( $c, '.' ) ); }
			return self::render_slider( $node, $doc, implode( ' ', array_filter( $sc ) ) );
		}
		if ( isset( $node['el'] ) && in_array( $node['el'], array( 'Offcanvas', 'Modal', 'Dropdown' ), true ) ) {
			$oc = array();
			foreach ( (array) ( $node['classes'] ?? array() ) as $c ) { $oc[] = sanitize_html_class( ltrim( $c, '.' ) ); }
			return self::render_overlay( $node, $doc, implode( ' ', array_filter( $oc ) ) );
		}
		if ( isset( $node['el'] ) && 'Tabs' === $node['el'] ) {
			$tc = array();
			foreach ( (array) ( $node['classes'] ?? array() ) as $c ) { $tc[] = sanitize_html_class( ltrim( $c, '.' ) ); }
			return self::render_tabs( $node, $doc, implode( ' ', array_filter( $tc ) ) );
		}
		if ( isset( $node['el'] ) && ( 'Accordion' === $node['el'] || 'Faq' === $node['el'] ) ) {
			$acc_cls = array();
			foreach ( (array) ( $node['classes'] ?? array() ) as $c ) {
				$acc_cls[] = sanitize_html_class( ltrim( $c, '.' ) );
			}
			return self::render_accordion( $node, $doc, implode( ' ', array_filter( $acc_cls ) ) );
		}
		// Inner Content: swap the template's placeholder for the actual page.
		if ( isset( $node['el'] ) && 'InnerContent' === $node['el'] ) {
			if ( ! self::$inner_doc ) {
				return ''; // Rendering a template on its own — nothing to inject.
			}
			// A document can end up being its own template (easy to do by flipping
			// a page's type). Injecting it into itself would recurse until PHP runs
			// out of memory and the page 500s, so the slot is consumed on first use.
			if ( self::$inner_depth > 0 ) {
				return '';
			}
			self::$inner_used = true;
			$classes = array();
			foreach ( (array) ( $node['classes'] ?? array() ) as $c ) {
				$classes[] = sanitize_html_class( ltrim( $c, '.' ) );
			}
			$classes[] = 'velox-inner-content';
			self::$inner_depth++;
			$inner = self::render_tree( self::$inner_doc['tree'], self::$inner_doc );
			self::$inner_depth--;
			// A page with no Velox layout contributes its post content instead.
			if ( '' === trim( $inner ) && '' !== self::$inner_html ) {
				$inner = '<div class="velox-legacy-content">' . self::$inner_html . '</div>';
			}
			return '<div id="' . esc_attr( $node['id'] ?? '' ) . '" class="' . esc_attr( implode( ' ', array_filter( $classes ) ) ) . '">' . $inner . '</div>';
		}
		// Only real layout/text tags. Any [a-z0-9] tag was accepted, so a node
		// saved as <script> ran its text as code — around the unfiltered_html rule.
		$tag = preg_replace( '/[^a-z0-9]/', '', strtolower( $node['tag'] ?? 'div' ) );
		if ( ! in_array( $tag, array( 'div', 'section', 'header', 'footer', 'main', 'article', 'aside', 'nav', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'span', 'a', 'ul', 'ol', 'li', 'figure', 'figcaption', 'blockquote', 'button', 'label', 'strong', 'em', 'small', 'time', 'address', 'mark', 'b', 'i', 'u', 's', 'cite', 'q', 'dl', 'dt', 'dd', 'details', 'summary' ), true ) ) {
			$tag = 'div';
		}
		$classes = array();
		foreach ( (array) ( $node['classes'] ?? array() ) as $c ) {
			$classes[] = sanitize_html_class( ltrim( $c, '.' ) );
		}
		// Animate-on-scroll: an element's own setting wins, otherwise the global
		// default animates everything. "none" opts a single element out.
		$aos_attr = '';
		$g_aos    = class_exists( 'Velox_Builder' ) ? ( Velox_Builder::global_styles()['aos'] ?? array() ) : array();
		$n_aos    = $node['aos'] ?? array();
		// Precedence: element, then this page, then the site default.
		$p_type   = self::$page_settings['aosType'] ?? '';
		$base     = ( '' !== $p_type ) ? ( 'none' === $p_type ? '' : $p_type ) : ( $g_aos['type'] ?? '' );
		$a_type   = $n_aos['type'] ?? $base;
		if ( 'none' === ( $n_aos['type'] ?? '' ) ) {
			$a_type = '';
		}
		if ( $a_type && isset( Velox_Builder::aos_types()[ $a_type ] ) ) {
			self::$aos_used = true;
			$aos_attr = ' data-vx-aos="' . esc_attr( $a_type ) . '"';
			foreach ( array( 'duration', 'delay' ) as $k ) {
				$v = $n_aos[ $k ] ?? '';
				if ( '' !== $v ) {
					$aos_attr .= ' data-vx-aos-' . $k . '="' . (int) $v . '"';
				}
			}
		}
		$id   = sanitize_html_class( $node['id'] ?? '' );
		$attr = ' id="' . esc_attr( $id ) . '"';
		if ( $classes ) {
			$attr .= ' class="' . esc_attr( implode( ' ', $classes ) ) . '"';
		}
		// Part of the attributes from here on, so images, videos, icons,
		// reusables and WordPress-data elements animate too (they returned early).
		$attr .= $aos_attr;

		// Reusable element: pull the referenced reusable document and render its
		// tree inline (by reference — editing the reusable updates every page).
		if ( isset( $node['el'] ) && 'Reusable' === $node['el'] ) {
			$ref = isset( $node['ref'] ) ? (int) $node['ref'] : 0;
			if ( ! $ref || ! class_exists( 'Velox_Builder' ) ) {
				return '';
			}
			// A reusable that contains itself (directly or through another one)
			// recursed until PHP ran out of memory — a white screen for the page.
			if ( isset( self::$reuse_stack[ $ref ] ) || count( self::$reuse_stack ) > 8 ) {
				return '';
			}
			$reuse = Velox_Builder::doc_model( $ref, self::$previewing );
			if ( ! $reuse || empty( $reuse['tree'] ) ) {
				return '';
			}
			// A reusable that was never published is not on the site yet.
			if ( isset( $reuse['__status'] ) && 'published' !== $reuse['__status'] && ! self::$previewing ) {
				return '';
			}
			self::$reuse_stack[ $ref ] = true;
			$inner = self::render_tree( $reuse['tree'], $reuse );
			unset( self::$reuse_stack[ $ref ] );
			return '<div' . $attr . ' data-velox-reusable="' . $ref . '">' . $inner . '</div>';
		}

		// Video: YouTube / Vimeo embed, or a self-hosted file.
		if ( isset( $node['el'] ) && 'Video' === $node['el'] ) {
			self::$media_used = true;
			$url  = (string) self::el_set( $node, 'url', '' );
			$auto = (bool) self::el_set( $node, 'autoplay', '' );
			$body = '';
			$emb  = self::video_embed( $url, $auto );
			if ( $emb ) {
				$title = (string) self::el_set( $node, 'title', '' );
				$body  = '<iframe src="' . esc_url( $emb ) . '" title="' . esc_attr( '' !== $title ? $title : __( 'Video', 'velox' ) ) . '" loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>';
			} elseif ( '' !== $url ) {
				$poster = (string) self::el_set( $node, 'poster', '' );
				$vt     = (string) self::el_set( $node, 'title', '' );
				$body   = '<video src="' . esc_url( $url ) . '"' . ( $poster ? ' poster="' . esc_url( $poster ) . '"' : '' )
					. ( '' !== $vt ? ' aria-label="' . esc_attr( $vt ) . '"' : '' )
					. ( $auto ? ' autoplay muted loop playsinline' : ' controls' ) . ' preload="metadata"></video>';
			}
			$attr = preg_replace( '/ class="/', ' class="vx-video ', $attr, 1, $hit );
			if ( ! $hit ) {
				$attr .= ' class="vx-video"';
			}
			return '<div' . $attr . '>' . $body . '</div>';
		}

		// Icon: one of the built-in icons, sized by a setting.
		if ( isset( $node['el'] ) && 'Icon' === $node['el'] ) {
			self::$media_used = true;
			$name = (string) self::el_set( $node, 'icon', 'star' );
			$size = (int) self::el_set( $node, 'size', '32' );
			$svg  = class_exists( 'Velox_Icons' ) ? Velox_Icons::svg( $name, $size > 0 ? $size : 32 ) : '';
			$attr = preg_replace( '/ class="/', ' class="vx-icon ', $attr, 1, $hit );
			if ( ! $hit ) {
				$attr .= ' class="vx-icon"';
			}
			$label = (string) self::el_set( $node, 'label', '' );
			$href  = isset( $node['href'] ) ? trim( (string) $node['href'] ) : '';
			if ( '' !== $href ) {
				// A link must stay a link for screen readers, with a name.
				$attr .= ' aria-label="' . esc_attr( '' !== $label ? $label : str_replace( '-', ' ', $name ) ) . '"';
			} else {
				$attr .= '' !== $label ? ' role="img" aria-label="' . esc_attr( $label ) . '"' : ' aria-hidden="true"';
			}
			$attr .= $size > 0 ? ' style="--vx-icon-size:' . $size . 'px"' : '';
			if ( '' !== $href ) {
				return '<a' . $attr . ' href="' . esc_url( $href ) . '"' . ( ! empty( $node['target'] ) && '_blank' === $node['target'] ? ' target="_blank" rel="noopener"' : '' ) . '>' . $svg . '</a>';
			}
			return '<span' . $attr . '>' . $svg . '</span>';
		}

		// Image element: emit a real <img> from the stored URL (empty = nothing).
		if ( isset( $node['el'] ) && 'Image' === $node['el'] ) {
			$src = isset( $doc['content'][ $node['id'] ] ) ? esc_url( $doc['content'][ $node['id'] ] ) : '';
			// Alt text is content, not decoration: it belongs to the element and has
			// to survive to the page. It was hardcoded empty, which made every
			// builder image invisible to search engines and screen readers alike.
			$alt = isset( $node['alt'] ) ? sanitize_text_field( (string) $node['alt'] ) : '';
			$att = isset( $node['att'] ) ? absint( $node['att'] ) : 0;
			$img = '';
			$size    = (string) self::el_set( $node, 'imgSize', 'large' );
			$size    = in_array( $size, array( 'medium', 'medium_large', 'large', 'full' ), true ) ? $size : 'large';
			$loading = (string) self::el_set( $node, 'loading', '' );
			if ( $att && wp_attachment_is_image( $att ) ) {
				// A media-library image: let WordPress build the tag, so the page gets
				// srcset/sizes (phones download a small file), real width/height (no
				// layout jump) and the library's alt text when none was typed here.
				$attrs = array( 'style' => 'display:block;max-width:100%;height:auto' );
				if ( '' !== $alt || ! empty( $node['altCustom'] ) ) {
					$attrs['alt'] = $alt;
				}
				// "Right away" for a hero image above the fold; WordPress would
				// otherwise lazy-load it and delay the largest paint.
				if ( 'eager' === $loading ) {
					$attrs['loading']       = 'eager';
					$attrs['fetchpriority'] = 'high';
				} elseif ( 'lazy' === $loading ) {
					$attrs['loading'] = 'lazy';
				}
				$img = wp_get_attachment_image( $att, $size, false, $attrs );
			}
			if ( '' === $img && $src ) {
				$img = '<img src="' . $src . '" alt="' . esc_attr( $alt ) . '" decoding="async"'
					. ( 'eager' === $loading ? ' loading="eager" fetchpriority="high"' : ' loading="lazy"' )
					. ' style="display:block;max-width:100%;height:auto">';
			}
			$href = isset( $node['href'] ) ? trim( (string) $node['href'] ) : '';
			if ( '' !== $img && '' !== $href ) {
				$img = '<a href="' . esc_url( $href ) . '"' . ( ! empty( $node['target'] ) && '_blank' === $node['target'] ? ' target="_blank" rel="noopener"' : '' ) . '>' . $img . '</a>';
			}
			$caption = trim( (string) self::el_set( $node, 'caption', '' ) );
			if ( '' !== $img && '' !== $caption ) {
				return '<figure' . $attr . ' style="margin:0">' . $img . '<figcaption>' . esc_html( $caption ) . '</figcaption></figure>';
			}
			return '<div' . $attr . '>' . $img . '</div>';
		}

		// Google Reviews: render the plugin's real reviews via its shortcode,
		// using the connection + preset chosen in the element settings.
		if ( isset( $node['el'] ) && 'Reviews' === $node['el'] ) {
			$conn   = isset( $node['conn'] ) ? sanitize_key( $node['conn'] ) : '';
			$preset = isset( $node['preset'] ) ? sanitize_key( $node['preset'] ) : '';
			// Styling is applied whether the reviews are real or the example set,
			// so what you design is what you get once the connection is live.
			$rid = sanitize_html_class( $node['id'] ?? 'rv' );
			self::$float_css .= self::reviews_css( $node, '#' . $rid );

			// Example reviews are a design aid. They were shown to every visitor —
			// invented reviews on a live site are a legal problem — so they only
			// ever appear in the editor and for logged-in admins.
			if ( self::el_set( $node, 'demo', '' ) && ( self::$previewing || self::$css_only || current_user_can( 'edit_pages' ) ) ) {
				self::$runtime_used['reviews'] = true;
				return '<div' . $attr . '>' . self::reviews_demo( $node ) . '</div>';
			}
			if ( ! $conn || ! shortcode_exists( 'velox_reviews' ) ) {
				return '<div' . $attr . '></div>';
			}
			$sc = '[velox_reviews connection="' . esc_attr( $conn ) . '" preset="' . esc_attr( $preset ) . '"';
			$cnt = (int) self::el_set( $node, 'count', 6 );
			if ( $cnt ) { $sc .= ' count="' . $cnt . '"'; }
			$min = (int) self::el_set( $node, 'minStars', 0 );
			if ( $min ) { $sc .= ' min_rating="' . $min . '"'; }
			$trim = (int) self::el_set( $node, 'trim', 0 );
			if ( $trim ) { $sc .= ' trim="' . $trim . '"'; }
			$lay = self::el_set( $node, 'layout', 'grid' );
			$sc .= ' layout="' . ( 'slider' === $lay ? 'slider' : 'static' ) . '"';
			$sc .= ']';
			if ( self::$css_only ) {
				return '';
			}
			return '<div' . $attr . '>' . do_shortcode( $sc ) . '</div>';
		}

		// WordPress-data elements: pull live data from the bound/current post.
		if ( isset( $node['el'] ) && 'WP' === $node['el'] ) {
			if ( self::$css_only ) {
				return '';
			}
			return self::render_wp_node( $node, $attr, $tag );
		}

		// Reading time and Last updated are computed from the post, not typed in.
		// Both previously had no branch at all, so they rendered as empty spans.
		if ( isset( $node['el'] ) && in_array( $node['el'], array( 'Readingtime', 'Lastupdated' ), true ) ) {
			// Same source the other WordPress-data elements use.
			$pid = get_the_ID();
			if ( ! $pid ) {
				$pid = get_queried_object_id();
			}
			if ( ! $pid ) {
				return '<' . $tag . $attr . '></' . $tag . '>';
			}

			if ( 'Readingtime' === $node['el'] ) {
				$wpm   = max( 50, (int) self::el_set( $node, 'wpm', 200 ) );
				$floor = max( 0, (int) self::el_set( $node, 'rtMin', 1 ) );
				$body  = (string) get_post_field( 'post_content', $pid );
				// Velox pages keep their text in the builder document, not in
				// post_content (which is empty), so count the document's text too.
				$src  = self::$inner_doc ? self::$inner_doc : $doc;
				if ( ! empty( $src['content'] ) && is_array( $src['content'] ) ) {
					foreach ( $src['content'] as $txt ) {
						if ( is_string( $txt ) && ! preg_match( '#^https?://\S+$#', $txt ) ) {
							$body .= ' ' . $txt;
						}
					}
				}
				// Strip shortcodes and tags first or markup inflates the count.
				// str_word_count() split German words at every umlaut.
				$plain = trim( wp_strip_all_tags( strip_shortcodes( $body ) ) );
				$words = '' === $plain ? 0 : count( preg_split( '/\s+/u', $plain ) );
				$mins  = (int) max( $floor, (int) ceil( $words / $wpm ) );
				$parts = array_filter( array(
					trim( (string) self::el_set( $node, 'rtBefore', '' ) ),
					(string) $mins,
					trim( (string) self::el_set( $node, 'rtAfter', 'min read' ) ),
				), function ( $v ) {
					return '' !== $v;
				} );
				return '<' . $tag . $attr . '>' . esc_html( implode( ' ', $parts ) ) . '</' . $tag . '>';
			}

			$mod = get_post_modified_time( 'U', true, $pid );
			$pub = get_post_time( 'U', true, $pid );
			if ( ! $mod || ( $mod <= $pub && ! self::el_set( $node, 'luFallback', '1' ) ) ) {
				return '<' . $tag . $attr . '></' . $tag . '>';
			}
			$stamp = $mod ? $mod : $pub;
			if ( self::el_set( $node, 'luRelative', '' ) ) {
				/* translators: %s: a length of time, e.g. "3 days". */
				$when = sprintf( __( '%s ago', 'velox' ), human_time_diff( $stamp, time() ) );
			} else {
				$fmt  = trim( (string) self::el_set( $node, 'luFormat', '' ) );
				$when = wp_date( '' !== $fmt ? $fmt : get_option( 'date_format' ), $stamp );
			}
			$before = trim( (string) self::el_set( $node, 'luBefore', __( 'Last updated', 'velox' ) ) );
			$text   = '' !== $before ? $before . ' ' . $when : $when;
			return '<' . $tag . $attr . '><time datetime="' . esc_attr( gmdate( 'c', $stamp ) ) . '">'
				. esc_html( $text ) . '</time></' . $tag . '>';
		}

		// Shortcodes typed into builder text were printed as literal text, so a
		// contact form, a gallery or anything else placed this way never ran.
		// do_shortcode() comes after sanitising, so the stored text is still
		// filtered but a registered shortcode is allowed to render.
		$content = ( isset( $doc['content'][ $node['id'] ] ) && ! self::$css_only )
			// Shortcodes first, then the data tokens: a value pulled in by a token
			// (an author bio, a custom field) must never run a shortcode.
			? self::resolve_tokens( do_shortcode( wp_kses_post( $doc['content'][ $node['id'] ] ) ) )
			: '';
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
			case 'post.meta':     return $arg ? self::scalar( get_post_meta( $pid, $arg, true ) ) : '';
			case 'post.terms':
				// Categories AND tags (the picker says so) — it showed categories only.
				$parts = array();
				foreach ( array( 'category', 'post_tag' ) as $tax ) {
					$l = get_the_term_list( $pid, $tax, '', ', ' );
					if ( $l && ! is_wp_error( $l ) ) {
						$parts[] = $l;
					}
				}
				return wp_kses_post( implode( ', ', $parts ) );
			case 'post.taxterms':
				// An unknown taxonomy returns a WP_Error — strip_tags() on that was a
				// fatal error that took the whole page down.
				$l = $arg ? get_the_term_list( $pid, $arg, '', ', ' ) : '';
				return ( $l && ! is_wp_error( $l ) ) ? esc_html( wp_strip_all_tags( $l ) ) : '';
			// With no featured image the id is 0, and get_the_title(0) is the PAGE title.
			case 'featured.title':   $f = get_post_thumbnail_id( $pid ); return $f ? esc_html( get_the_title( $f ) ) : '';
			case 'featured.caption': $f = get_post_thumbnail_id( $pid ); return $f ? esc_html( wp_get_attachment_caption( $f ) ) : '';
			case 'featured.alt':     $f = get_post_thumbnail_id( $pid ); return $f ? esc_html( get_post_meta( $f, '_wp_attachment_image_alt', true ) ) : '';
			case 'author.name':   return esc_html( get_the_author_meta( 'display_name', (int) get_post_field( 'post_author', $pid ) ) );
			case 'author.bio':    return esc_html( get_the_author_meta( 'description', (int) get_post_field( 'post_author', $pid ) ) );
			case 'author.meta':   return ( $arg && self::safe_user_key( $arg ) ) ? self::scalar( get_the_author_meta( $arg, (int) get_post_field( 'post_author', $pid ) ) ) : '';
			case 'user.name':     $u = wp_get_current_user(); return $u ? esc_html( $u->display_name ) : '';
			case 'user.bio':      $u = wp_get_current_user(); return $u ? esc_html( $u->description ) : '';
			case 'user.meta':     $u = wp_get_current_user(); return ( $u && $u->ID && $arg && self::safe_user_key( $arg ) ) ? self::scalar( get_user_meta( $u->ID, $arg, true ) ) : '';
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

	/** A meta value as safe text; arrays and objects print nothing. */
	private static function scalar( $v ) {
		return is_scalar( $v ) ? esc_html( (string) $v ) : '';
	}

	/** User fields a page may show — never passwords, sessions or keys. */
	private static function safe_user_key( $key ) {
		$key = strtolower( (string) $key );
		if ( in_array( $key, array( 'user_pass', 'user_activation_key', 'session_tokens', 'user_email', 'user_login' ), true ) ) {
			return false;
		}
		return ! preg_match( '/(pass|token|secret|session|capabilit|_key$|api)/', $key );
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
		foreach ( (array) $extra as $em ) {
			if ( is_array( $em ) ) {
				$models[] = $em;
			}
		}
		// Reusables referenced anywhere — including inside other reusables, whose
		// classes were missed before — each counted once.
		$seen  = array();
		$queue = $models;
		while ( $queue ) {
			$m = array_shift( $queue );
			foreach ( self::collect_reusable_ids( $m['tree'] ?? array() ) as $rid ) {
				if ( isset( $seen[ $rid ] ) || ! class_exists( 'Velox_Builder' ) ) {
					continue;
				}
				$seen[ $rid ] = true;
				$rm = Velox_Builder::doc_model( $rid, self::$previewing );
				if ( $rm ) {
					$models[] = $rm;
					$queue[]  = $rm;
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
		// The site-wide version of a class wins over each document's own copy, so
		// a class looks the same in the template, the page and every reusable. A
		// class this (previewed) page changed but has not published keeps its own.
		if ( class_exists( 'Velox_Builder' ) ) {
			$pending = array();
			foreach ( $models as $m ) {
				foreach ( (array) ( $m['changedClasses'] ?? array() ) as $pc ) {
					$pending[ (string) $pc ] = true;
				}
			}
			foreach ( Velox_Builder::class_registry() as $cname => $rules ) {
				if ( isset( $classes[ $cname ] ) && ! isset( $pending[ $cname ] ) ) {
					$classes[ $cname ] = $rules;
				}
			}
		}

		$out    = '';
		// Every state actually used (:hover, :active, custom ones like
		// :nth-child(2)) — a fixed list dropped everything else on the page.
		$states = array( 'normal' => true );
		$scan   = function ( $map ) use ( &$states ) {
			foreach ( array_keys( (array) $map ) as $k ) {
				$pos = strpos( (string) $k, ':' );
				if ( false !== $pos ) {
					$st = substr( (string) $k, $pos + 1 );
					if ( preg_match( '/^[a-z][a-z0-9-]*(\([a-z0-9+\- ]*\))?$/i', $st ) ) {
						$states[ $st ] = true;
					}
				}
			}
		};
		foreach ( $classes as $byKey ) {
			$scan( $byKey );
		}
		foreach ( $trees as $tree ) {
			self::walk( $tree, function ( $node ) use ( $scan ) {
				$scan( $node['overrides'] ?? array() );
			} );
		}
		$states = array_keys( $states );
		foreach ( self::bp_map() as $bp => $mq ) {
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
								// A popup / panel / dropdown is styled on its panel, not on
								// the full-screen container that carries the id.
								$panel = isset( $node['el'] ) && in_array( $node['el'], array( 'Offcanvas', 'Modal', 'Dropdown' ), true );
								$body .= '#' . sanitize_html_class( $node['id'] ) . ( $panel ? '>.vx-ov-panel' : '' ) . $pseudo . '{' . self::decls( $node['overrides'][ $key ] ) . '}';
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
		if ( preg_match( '/(expression|javascript:|@import|behaviou?r\s*:)/i', $v ) ) {
			return '';
		}
		// Background images: allow url() only around a plain http(s) or relative
		// address. Every url() used to be dropped, so a background image set in
		// the editor (which showed it) never reached the page.
		if ( preg_match_all( '/url\s*\(\s*([\'"]?)(.*?)\1\s*\)/i', $v, $m ) ) {
			foreach ( $m[2] as $u ) {
				if ( ! preg_match( '#^(https?://|/)[^\s\'"()\\\\]*$#i', $u ) ) {
					return '';
				}
			}
		} elseif ( preg_match( '/url\s*\(/i', $v ) ) {
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

	/** Strip whitespace and comments from generated CSS. */
	public static function minify_css( $css ) {
		$css = preg_replace( '#/\*.*?\*/#s', '', (string) $css );
		$css = preg_replace( '/\s*([{}:;,>])\s*/', '$1', $css );
		$css = preg_replace( '/;}/', '}', $css );
		return trim( preg_replace( '/\s+/', ' ', $css ) );
	}

	public static function ensure_css_file( $doc, $extra = array() ) {
		$css = self::generate_css( $doc, $extra );
		$set = class_exists( 'Velox_Builder' ) ? Velox_Builder::settings() : array();
		// A preview must never overwrite the shared CSS file the live (and cached)
		// page links to — print its CSS inline instead.
		if ( self::$previewing ) {
			return self::inline_fallback( $css );
		}

		// These three settings existed on the Settings screen but were never read
		// by anything — set them and nothing happened. Now they do.
		if ( ! empty( $set['minify'] ) ) {
			$css = self::minify_css( $css );
		}
		if ( 'inline' === ( $set['css_mode'] ?? 'file' ) ) {
			return self::inline_fallback( $css );
		}
		$up = wp_upload_dir();
		if ( ! empty( $up['error'] ) ) {
			return self::inline_fallback( $css );
		}
		$dir = trailingslashit( $up['basedir'] ) . 'velox/builder';
		if ( ! wp_mkdir_p( $dir ) ) {
			return self::inline_fallback( $css );
		}
		$id   = (int) ( $doc['__id'] ?? 0 );
		// Pages wrapped by a catch-all template have no document of their own
		// (id 0); they all shared doc-0.css and overwrote each other's CSS.
		$slug = $id ? (string) $id : preg_replace( '/[^a-z0-9-]/', '', (string) ( $doc['__file'] ?? '0-p' . (int) get_queried_object_id() ) );
		$file = $dir . '/doc-' . $slug . '.css';
		$hash = substr( md5( $css ), 0, 8 );

		// Only rewrite if content changed.
		if ( ! file_exists( $file ) || md5_file( $file ) !== md5( $css ) ) {
			if ( false === file_put_contents( $file, $css ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
				return self::inline_fallback( $css );
			}
		}
		return trailingslashit( $up['baseurl'] ) . 'velox/builder/doc-' . $slug . '.css?v=' . $hash;
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
		// The page's file also carries its template / header / footer CSS, which
		// only the page render knows. Rewriting it from the document alone dropped
		// that, so delete it and let the next view build it complete.
		$up = wp_upload_dir();
		if ( empty( $up['error'] ) ) {
			$file = trailingslashit( $up['basedir'] ) . 'velox/builder/doc-' . (int) $row['id'] . '.css';
			if ( file_exists( $file ) ) {
				wp_delete_file( $file );
			}
		}
		return true;
	}
}
