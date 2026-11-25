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
		'objectFit' => 'object-fit', 'alignSelf' => 'align-self', 'justifySelf' => 'justify-self', 'justifyItems' => 'justify-items',
		'fontFamily' => 'font-family', 'fontStyle' => 'font-style', 'whiteSpace' => 'white-space',
		'textWrap' => 'text-wrap', 'gridColumn' => 'grid-column', 'gridRow' => 'grid-row',
		// 4.21 style panel additions (kept in step with CSS_PROP in velox-builder.js).
		'top' => 'top', 'right' => 'right', 'bottom' => 'bottom', 'left' => 'left',
		'order' => 'order', 'flexGrow' => 'flex-grow', 'flexShrink' => 'flex-shrink', 'flexBasis' => 'flex-basis',
		'rowGap' => 'row-gap', 'columnGap' => 'column-gap', 'gridTemplateRows' => 'grid-template-rows', 'alignContent' => 'align-content',
		'textShadow' => 'text-shadow', 'wordSpacing' => 'word-spacing',
		'backgroundImage' => 'background-image', 'backgroundSize' => 'background-size', 'backgroundPosition' => 'background-position', 'backgroundRepeat' => 'background-repeat',
		'transform' => 'transform', 'transition' => 'transition', 'filter' => 'filter', 'backdropFilter' => 'backdrop-filter', 'cursor' => 'cursor', 'mixBlendMode' => 'mix-blend-mode', 'objectPosition' => 'object-position',
		'borderTopLeftRadius' => 'border-top-left-radius', 'borderTopRightRadius' => 'border-top-right-radius',
		'borderBottomRightRadius' => 'border-bottom-right-radius', 'borderBottomLeftRadius' => 'border-bottom-left-radius',
	);
	private static $UNIT = array( 'gap', 'paddingTop', 'paddingRight', 'paddingBottom', 'paddingLeft', 'marginTop', 'marginRight', 'marginBottom', 'marginLeft', 'width', 'minWidth', 'maxWidth', 'height', 'minHeight', 'maxHeight', 'fontSize', 'letterSpacing', 'borderWidth', 'borderRadius', 'padding', 'margin',
		'top', 'right', 'bottom', 'left', 'flexBasis', 'rowGap', 'columnGap', 'wordSpacing', 'borderTopLeftRadius', 'borderTopRightRadius', 'borderBottomRightRadius', 'borderBottomLeftRadius' );
	/** Shorthands are written before the longhands they would otherwise reset. */
	private static $SHORTHAND = array( 'background' => 1, 'padding' => 1, 'margin' => 1, 'borderRadius' => 1 );
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

	/** Every style property the builder writes (camelCase model keys). */
	public static function css_props() {
		return array_keys( self::$CSS_PROP );
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
		// Saved changes are visible to the people who can edit them and to nobody
		// else: visitors keep the published version until Publish is pressed.
		// Someone who may edit sees the saved version on the real page (not only
		// in ?preview); ?velox_live=1 shows them exactly what visitors see.
		$can_edit = is_user_logged_in()
			&& ( is_singular() ? current_user_can( 'edit_post', get_queried_object_id() ) : current_user_can( 'manage_options' ) );
		self::$live_view  = $can_edit && isset( $_GET['velox_live'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		self::$previewing = $can_edit && ! self::$live_view;
		if ( $can_edit ) {
			// Never let a page cache keep an editor's view: with "cache logged-in
			// users" on, saved changes could otherwise reach visitors.
			if ( ! defined( 'DONOTCACHEPAGE' ) ) {
				define( 'DONOTCACHEPAGE', true );
			}
			if ( ! headers_sent() ) {
				nocache_headers();
			}
			add_action( 'admin_bar_menu', array( __CLASS__, 'admin_bar_view_switch' ), 91 );
		}
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
	/** An editor asked to see the page as visitors do (?velox_live=1). */
	private static $live_view = false;
	/** Set once a Velox document actually rendered this request. */
	private static $rendered_doc = false;

	/**
	 * Admin bar: which version an editor is looking at, and a switch to the other.
	 * Only on pages Velox renders.
	 */
	public static function admin_bar_view_switch( $bar ) {
		if ( ! self::$rendered_doc ) {
			return;
		}
		$here = remove_query_arg( array( 'velox_live', 'preview', 'preview_id', 'preview_nonce' ) );
		if ( self::$live_view ) {
			$bar->add_node( array(
				'id'    => 'velox-view',
				'title' => '<span class="ab-icon dashicons dashicons-visibility" style="margin-top:2px"></span>' . esc_html__( 'Live version', 'velox' ),
				'href'  => $here,
				'meta'  => array( 'title' => __( 'You see what visitors see. Click to show your saved, unpublished changes.', 'velox' ) ),
			) );
		} else {
			$bar->add_node( array(
				'id'    => 'velox-view',
				'title' => '<span class="ab-icon dashicons dashicons-edit" style="margin-top:2px"></span>' . esc_html__( 'Saved changes', 'velox' ),
				'href'  => add_query_arg( 'velox_live', '1', $here ),
				'meta'  => array( 'title' => __( 'You see your saved changes; visitors see them once you publish. Click to see the live version.', 'velox' ) ),
			) );
		}
	}

	/**
	 * A stand-in document for a 404, search or archive screen: the template made
	 * for it, with a list of posts (or a not-found message) in its Inner Content.
	 */
	private static function context_doc() {
		if ( ! class_exists( 'Velox_Builder' ) || is_feed() || is_embed() || is_admin() ) {
			return null;
		}
		// A template whose rules name this screen takes it, even a custom post
		// type archive or a shop category: someone asked for it explicitly.
		if ( class_exists( 'Velox_Builder_Templates' ) && ! ( function_exists( 'is_cart' ) && ( is_cart() || is_checkout() || is_account_page() ) ) ) {
			$ruled = Velox_Builder_Templates::match( Velox_Builder_Templates::ctx_request() );
			if ( $ruled ) {
				$name = is_404() ? 'error404' : ( is_search() ? 'search' : 'archive' );
				self::$loop_used = true;
				return array(
					'tree'    => array(),
					'classes' => array(),
					'content' => array(),
					'__id'    => 0,
					'__file'  => '0-' . $name . '-' . $ruled['id'],
					'__tpl'   => $ruled['id'],
					'__title' => wp_get_document_title(),
					'__html'  => self::loop_html( $name ),
				);
			}
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
		$rctx = class_exists( 'Velox_Builder_Templates' ) ? Velox_Builder_Templates::ctx_request() : null;
		$tpl  = Velox_Builder::template_for_context( $ctx, $rctx );
		// Catch-all templates only take over these screens when "wrap pages
		// without a Velox layout" is on — the same rule as for plain pages.
		if ( ! $tpl && Velox_Builder::wrap_legacy() ) {
			$tpl = Velox_Builder::template_for_context( array( 'catch_all' ), $rctx );
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
		// (On the normal URL too now — see maybe_render(); ?velox_live=1 opts out.)
		if ( self::$previewing && current_user_can( 'edit_post', $post_id ) ) {
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
		// A theme runs the_content inside the main loop, and plugins check for it
		// (in_the_loop() && is_main_query()): Velox Shop draws its product page
		// that way. Without the loop flag those pages came out empty.
		global $wp_query;
		$was_in_loop = $wp_query instanceof WP_Query ? $wp_query->in_the_loop : false;
		if ( $wp_query instanceof WP_Query ) {
			$wp_query->in_the_loop = true;
		}
		setup_postdata( $post );
		$html = apply_filters( 'the_content', $post->post_content );
		if ( $wp_query instanceof WP_Query ) {
			$wp_query->in_the_loop = $was_in_loop;
		}
		return array(
			'tree'      => array(),
			'classes'   => array(),
			'content'   => array(),
			'__id'      => 0,
			'__file'    => '0-p' . (int) $post_id,
			'__title'   => $post->post_title,
			'__html'    => $html,
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
					. $sel . '>* img{width:100%;height:100%!important;max-width:none;object-fit:cover;display:block}';
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
					. $sel . ' img{height:' . (int) $carry( $h, $bp, 32 ) . 'px!important;width:auto;max-width:100%;display:block}' ); // !important: each logo <img> carries an inline height:auto that beat the setting.
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
	/** A CSS colour: hex, a colour function, a CSS variable or a named colour. */
	private static function is_color( $v ) {
		return (bool) preg_match( '/^(#[0-9a-f]{3,8}|(rgba?|hsla?|hwb|lab|lch|oklab|oklch|color|color-mix)\([^;{}<>]*\)|var\(--[A-Za-z0-9_-]+(,[^;{}<>]*)?\)|[a-z]+)$/i', trim( (string) $v ) );
	}

	private static function el_color( $node, $key, $default ) {
		$v = self::sanitize_value( (string) self::el_set( $node, $key, $default ) );
		// Only something that is a colour: a typo ("blu e", "Test") used to go out
		// as-is and made the browser drop the whole rule (stroke width included).
		if ( '' !== $v && ! self::is_color( $v ) ) {
			return $default;
		}
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
		// "Show on: phones only" or "after N pages" still need the trigger: without
		// it both were silently ignored and the element showed everywhere.
		if ( 'load' === $t && ! in_array( $el, array( 'Modal', 'Offcanvas' ), true )
			&& 'always' === (string) self::el_set( $node, 'trigOnce', 'always' )
			&& (int) self::el_raw( $node, 'trigPages', '' ) <= 1
			&& ! in_array( (string) self::el_set( $node, 'trigDevice', '' ), array( 'desktop', 'mobile' ), true ) ) {
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


	/* ================= widgets =================
	 * Elements drawn from their settings: marquee, counter, countdown,
	 * before/after, gallery, rotating text, flip card, skill bars, star rating,
	 * social icons, map, HTML embed, table, timeline and checklist. The editor
	 * draws the same markup (velox-builder.js widgetHTML), both use
	 * widgets_css(), and per-instance values become CSS variables on #id, so a
	 * class can still restyle every one of them. */
	private static $WIDGETS = array( 'Marquee' => 1, 'Counter' => 1, 'Countdown' => 1, 'Compare' => 1, 'Gallery' => 1, 'Rotator' => 1,
		'Flipcard' => 1, 'Bars' => 1, 'Rating' => 1, 'Social' => 1, 'Map' => 1, 'Embed' => 1, 'Codeblock' => 1, 'Table' => 1, 'Timeline' => 1, 'Checklist' => 1,
		'Shapedivider' => 1, 'Progresscircle' => 1, 'Toc' => 1, 'Share' => 1, 'Copyright' => 1, 'Copybtn' => 1 );
	/** The document being rendered, for widgets that read the page (table of contents). */
	private static $wdoc = null;
	/** The shape divider's outlines (viewBox 0 0 1200 120, filled at the bottom). */
	private static $SHAPES = array( 'wave' => '<path d="M0,60 C150,110 350,10 600,60 C850,110 1050,10 1200,60 L1200,120 L0,120 Z"/>', 'waves' => '<path opacity=".35" d="M0,40 C200,100 400,0 600,50 C800,100 1000,20 1200,50 L1200,120 L0,120 Z"/><path d="M0,75 C150,110 350,35 600,75 C850,115 1050,45 1200,75 L1200,120 L0,120 Z"/>', 'curve' => '<path d="M0,120 Q600,-40 1200,120 Z"/>', 'tilt' => '<path d="M0,120 L1200,20 L1200,120 Z"/>', 'peak' => '<path d="M0,120 L600,20 L1200,120 Z"/>', 'zigzag' => '<path d="M0,120 L0,100 L40,60 L80,100 L120,60 L160,100 L200,60 L240,100 L280,60 L320,100 L360,60 L400,100 L440,60 L480,100 L520,60 L560,100 L600,60 L640,100 L680,60 L720,100 L760,60 L800,100 L840,60 L880,100 L920,60 L960,100 L1000,60 L1040,100 L1080,60 L1120,100 L1160,60 L1200,100 L1200,120 Z"/>' );
	private static $wicons = null;

	/** Icon paths shared with the editor (social networks, list marks, stars). */
	public static function widget_icons() {
		if ( null === self::$wicons ) {
			$f = defined( 'VELOX_PATH' ) ? VELOX_PATH . 'assets/icons/velox-widgets.json' : dirname( __DIR__ ) . '/assets/icons/velox-widgets.json';
			$j = is_readable( $f ) ? json_decode( (string) file_get_contents( $f ), true ) : null;
			self::$wicons = is_array( $j ) ? $j : array();
		}
		return self::$wicons;
	}
	private static function wsvg( $name, $extra = '' ) {
		$i = self::widget_icons();
		return isset( $i[ $name ] ) ? '<svg viewBox="0 0 16 16" aria-hidden="true" focusable="false"' . $extra . '>' . $i[ $name ] . '</svg>' : '';
	}
	/** The signature of a code block's code (made when a trusted person saves it). */
	public static function code_sig( $html, $css, $js ) {
		return hash_hmac( 'sha256', $html . "\0" . $css . "\0" . $js, wp_salt( 'auth' ) . '|velox-code' );
	}

	public static function code_signed( $html, $css, $js, $sig ) {
		return '' !== $sig && hash_equals( self::code_sig( $html, $css, $js ), $sig );
	}

	/** Add a class to an attribute string that may already carry one. */
	private static function wcls( $attr, $add ) {
		$out = preg_replace( '/ class="/', ' class="' . $add . ' ', $attr, 1, $hit );
		return $hit ? $out : $attr . ' class="' . $add . '"';
	}
	/** A setting value made safe for a CSS variable of the given kind. */
	private static function wval( $v, $kind ) {
		$v = trim( (string) $v );
		if ( '' === $v ) {
			return '';
		}
		switch ( $kind ) {
			case 'px':
				return self::css_size( $v );
			case 'int':
				return is_numeric( $v ) ? (string) max( 1, min( 12, (int) $v ) ) : '';
			case 's':
				return is_numeric( $v ) ? max( 2, (float) $v ) . 's' : '';
			case 'ratio':
				return preg_match( '~^\d+(\.\d+)?\s*/\s*\d+(\.\d+)?$~', $v ) ? str_replace( ' ', '', $v ) : ( 'auto' === $v ? 'auto' : '' );
			case 'color':
				$c = self::sanitize_value( $v );
				return self::is_color( $c ) ? $c : '';
			default:
				return self::sanitize_value( $v );
		}
	}
	/** Per-instance CSS variables, per screen size, as a rule on #id. */
	private static function wvars( $node, $map ) {
		$id = sanitize_html_class( $node['id'] ?? '' );
		if ( '' === $id ) {
			return;
		}
		$mqs = self::bp_map();
		$per = array();
		foreach ( $map as $k => $spec ) {
			foreach ( self::el_set_bp( $node, $k, $spec[2] ?? '' ) as $bp => $v ) {
				$v = self::wval( $v, $spec[1] );
				if ( '' !== $v ) {
					$per[ $bp ][] = $spec[0] . ':' . $v;
				}
			}
		}
		foreach ( array( 'base', 'xxl', 'xl', 'lg', 'md', 'sm' ) as $bp ) {
			if ( empty( $per[ $bp ] ) ) {
				continue;
			}
			$rule = '#' . $id . '{' . implode( ';', $per[ $bp ] ) . '}';
			self::$float_css .= ( 'base' === $bp || empty( $mqs[ $bp ] ) ) ? $rule : '@media ' . $mqs[ $bp ] . '{' . $rule . '}';
		}
	}
	/** Number with the chosen thousands separator. */
	private static function wnum( $n, $dec, $sep ) {
		$ds = ( ',' === $sep ) ? '.' : ',';
		if ( '' === $sep ) {
			$ds = ',';
		}
		return number_format( (float) $n, max( 0, min( 3, (int) $dec ) ), $ds, $sep );
	}
	private static function render_widget( $node, $attr ) {
		self::$runtime_used['widgets'] = true;
		$el = $node['el'];
		$s  = function ( $k, $d = '' ) use ( $node ) { return self::el_set( $node, $k, $d ); };
		$items = array();
		foreach ( (array) self::el_set( $node, 'items', array() ) as $it ) {
			if ( is_array( $it ) ) {
				$items[] = $it;
			}
		}
		switch ( $el ) {
			case 'Marquee':
				self::wvars( $node, array( 'speed' => array( '--vx-mq-speed', 's', '30' ), 'gap' => array( '--vx-mq-gap', 'px', '48' ), 'imgH' => array( '--vx-mq-img', 'px', '32' ) ) );
				$sep = trim( (string) $s( 'sep', '' ) );
				$set = '';
				foreach ( $items as $it ) {
					$t = trim( (string) ( $it['text'] ?? '' ) );
					$i = trim( (string) ( $it['image'] ?? '' ) );
					if ( '' === $t && '' === $i ) {
						continue;
					}
					$set .= '<span class="vx-mq-item">' . ( '' !== $i ? '<img src="' . esc_url( $i ) . '" alt="' . esc_attr( $t ) . '" loading="lazy">' : '' ) . ( '' !== $t && '' === $i ? esc_html( $t ) : ( '' !== $t ? '<span>' . esc_html( $t ) . '</span>' : '' ) ) . '</span>'
						. ( '' !== $sep ? '<span class="vx-mq-sep" aria-hidden="true">' . esc_html( $sep ) . '</span>' : '' );
				}
				if ( '' === $set ) {
					return '';
				}
				$c = 'vx-marquee' . ( 'right' === $s( 'dir', 'left' ) ? ' is-rev' : '' ) . ( $s( 'pause', '1' ) ? ' is-pause' : '' ) . ( $s( 'fade', '1' ) ? ' is-fade' : '' );
				return '<div' . self::wcls( $attr, $c ) . '><div class="vx-mq-track"><div class="vx-mq-set">' . $set . '</div><div class="vx-mq-set" aria-hidden="true">' . $set . '</div></div></div>';

			case 'Counter':
				self::wvars( $node, array( 'numSize' => array( '--vx-cnt-size', 'px', '48' ) ) );
				$to   = (float) str_replace( ',', '.', (string) $s( 'to', '1250' ) );
				$from = (float) str_replace( ',', '.', (string) self::el_raw( $node, 'from', '0' ) );
				$dec  = max( 0, min( 3, (int) self::el_raw( $node, 'dec', '0' ) ) );
				$sep  = (string) self::el_raw( $node, 'sep', '.' );
				$sep  = in_array( $sep, array( '.', ',', ' ', '' ), true ) ? $sep : '.';
				$dur  = max( 200, min( 10000, self::el_int( $node, 'dur', 1800 ) ) );
				$lab  = trim( (string) $s( 'label', '' ) );
				$al   = in_array( $s( 'align', 'left' ), array( 'left', 'center', 'right' ), true ) ? $s( 'align', 'left' ) : 'left';
				return '<div' . self::wcls( $attr, 'vx-counter vx-al-' . $al ) . '><span class="vx-cnt-num">'
					. ( '' !== (string) $s( 'prefix', '' ) ? '<span class="vx-cnt-pre">' . esc_html( $s( 'prefix', '' ) ) . '</span>' : '' )
					. '<span class="vx-cnt-val" data-vx-to="' . esc_attr( $to ) . '" data-vx-from="' . esc_attr( $from ) . '" data-vx-dec="' . $dec . '" data-vx-sep="' . esc_attr( $sep ) . '" data-vx-dur="' . $dur . '">' . esc_html( self::wnum( $to, $dec, $sep ) ) . '</span>'
					. ( '' !== (string) $s( 'suffix', '' ) ? '<span class="vx-cnt-suf">' . esc_html( $s( 'suffix', '' ) ) . '</span>' : '' )
					. '</span>' . ( '' !== $lab ? '<span class="vx-cnt-label">' . esc_html( $lab ) . '</span>' : '' ) . '</div>';

			case 'Countdown':
				self::wvars( $node, array( 'numSize' => array( '--vx-cd-size', 'px', '40' ) ) );
				$until = trim( (string) $s( 'until', '' ) );
				$tz    = function_exists( 'wp_timezone' ) ? wp_timezone() : new DateTimeZone( 'UTC' );
				$dt    = false;
				// "!" resets the parts the format leaves out: a date alone ends at midnight
				// (like the editor), not at whatever time of day the page was drawn.
				foreach ( array( '!Y-m-d\TH:i', '!Y-m-d H:i', '!Y-m-d\TH:i:s', '!Y-m-d H:i:s', '!Y-m-d' ) as $fmt ) {
					$dt = DateTime::createFromFormat( $fmt, $until, $tz );
					if ( $dt ) {
						break;
					}
				}
				$end  = $dt ? $dt->getTimestamp() : time();
				$left = max( 0, $end - time() );
				$vals = array( 'd' => intdiv( $left, 86400 ), 'h' => intdiv( $left % 86400, 3600 ), 'm' => intdiv( $left % 3600, 60 ), 's' => $left % 60 );
				$units = '';
				foreach ( array( 'd' => array( 'showD', 'lD', __( 'Days', 'velox' ) ), 'h' => array( 'showH', 'lH', __( 'Hours', 'velox' ) ), 'm' => array( 'showM', 'lM', __( 'Minutes', 'velox' ) ), 's' => array( 'showS', 'lS', __( 'Seconds', 'velox' ) ) ) as $u => $cfg ) {
					if ( ! $s( $cfg[0], '1' ) ) {
						continue;
					}
					$lbl    = (string) $s( $cfg[1], $cfg[2] );
					$units .= '<div class="vx-cd-u" data-u="' . $u . '"><b>' . str_pad( (string) $vals[ $u ], 2, '0', STR_PAD_LEFT ) . '</b><span>' . esc_html( $lbl ) . '</span></div>';
				}
				$done = trim( (string) $s( 'doneText', '' ) );
				return '<div' . self::wcls( $attr, 'vx-countdown' . ( $s( 'boxed', '1' ) ? ' is-boxed' : '' ) . ( $left <= 0 ? ' is-done' : '' ) ) . ' data-vx-until="' . ( $end * 1000 ) . '">'
					. '<div class="vx-cd-units">' . $units . '</div>'
					. ( '' !== $done ? '<p class="vx-cd-done"' . ( $left > 0 ? ' hidden' : '' ) . '>' . esc_html( $done ) . '</p>' : '' ) . '</div>';

			case 'Compare':
				$pos = max( 0, min( 100, self::el_int( $node, 'start', 50 ) ) );
				$rat = self::wval( $s( 'ratio', '16/9' ), 'ratio' );
				$b   = trim( (string) $s( 'before', '' ) );
				$a   = trim( (string) $s( 'after', '' ) );
				if ( '' === $a || '' === $b ) {
					return '';
				}
				$lb = (string) $s( 'lBefore', __( 'Before', 'velox' ) );
				$la = (string) $s( 'lAfter', __( 'After', 'velox' ) );
				return '<div' . self::wcls( $attr, 'vx-compare' ) . ' style="--vx-pos:' . $pos . '%' . ( $rat && 'auto' !== $rat ? ';--vx-cmp-ratio:' . esc_attr( $rat ) : '' ) . '">'
					. '<img class="vx-cmp-a" src="' . esc_url( $a ) . '" alt="' . esc_attr( $la ) . '" loading="lazy">'
					. '<div class="vx-cmp-b"><img src="' . esc_url( $b ) . '" alt="' . esc_attr( $lb ) . '" loading="lazy"></div>'
					. '<span class="vx-cmp-line" aria-hidden="true"><span class="vx-cmp-knob"></span></span>'
					. ( $s( 'labels', '1' ) ? '<span class="vx-cmp-l vx-cmp-lb">' . esc_html( $lb ) . '</span><span class="vx-cmp-l vx-cmp-la">' . esc_html( $la ) . '</span>' : '' )
					. '<input class="vx-cmp-r" type="range" min="0" max="100" value="' . $pos . '" aria-label="' . esc_attr( $lb . ' / ' . $la ) . '"></div>';

			case 'Gallery':
				self::wvars( $node, array( 'cols' => array( '--vx-gcols', 'int', '3' ), 'gap' => array( '--vx-ggap', 'px', '12' ), 'ratio' => array( '--vx-gratio', 'ratio', '4/3' ), 'radius' => array( '--vx-grad', 'px', '8' ) ) );
				$lb  = (bool) $s( 'lightbox', '1' );
				$cap = in_array( $s( 'captions', 'below' ), array( 'below', 'overlay', 'hidden' ), true ) ? $s( 'captions', 'below' ) : 'below';
				$out = '';
				foreach ( $items as $it ) {
					$i = trim( (string) ( $it['image'] ?? '' ) );
					if ( '' === $i ) {
						continue;
					}
					$c   = trim( (string) ( $it['caption'] ?? '' ) );
					$img = '<img src="' . esc_url( $i ) . '" alt="' . esc_attr( $c ) . '" loading="lazy" decoding="async">';
					$out .= '<figure class="vx-g-i">' . ( $lb ? '<a class="vx-g-a" href="' . esc_url( $i ) . '" data-vx-lb-i' . ( '' !== $c ? ' data-vx-cap="' . esc_attr( $c ) . '"' : '' ) . '>' . $img . '</a>' : $img )
						. ( '' !== $c && 'hidden' !== $cap ? '<figcaption class="vx-g-cap">' . esc_html( $c ) . '</figcaption>' : '' ) . '</figure>';
				}
				if ( '' === $out ) {
					return '';
				}
				return '<div' . self::wcls( $attr, 'vx-gallery cap-' . $cap ) . ( $lb ? ' data-vx-lb' : '' ) . '>' . $out . '</div>';

			case 'Rotator':
				self::wvars( $node, array( 'wColor' => array( '--vx-rot-c', 'color', '' ) ) );
				$words = array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n|\|/', (string) $s( 'words', '' ) ) ) ) );
				if ( ! $words ) {
					return '';
				}
				$fx  = in_array( $s( 'fx', 'slide' ), array( 'slide', 'fade', 'type' ), true ) ? $s( 'fx', 'slide' ) : 'slide';
				$ms  = max( 800, min( 20000, self::el_int( $node, 'ms', 2400 ) ) );
				$ws  = '';
				foreach ( $words as $i => $w ) {
					$ws .= '<span class="vx-rot-w' . ( 0 === $i ? ' is-on' : '' ) . '"' . ( $i ? ' aria-hidden="true"' : '' ) . '>' . esc_html( $w ) . '</span>';
				}
				$pre  = trim( (string) $s( 'pre', '' ) );
				$post = trim( (string) $s( 'post', '' ) );
				$tag  = in_array( $node['tag'] ?? 'h2', array( 'h1', 'h2', 'h3', 'h4', 'p', 'div', 'span' ), true ) ? $node['tag'] : 'h2';
				return '<' . $tag . self::wcls( $attr, 'vx-rotator' ) . ' data-vx-fx="' . $fx . '" data-vx-ms="' . $ms . '">'
					. ( '' !== $pre ? '<span class="vx-rot-pre">' . esc_html( $pre ) . '</span> ' : '' )
					. '<span class="vx-rot-ws">' . $ws . '</span>'
					. ( '' !== $post ? ' <span class="vx-rot-post">' . esc_html( $post ) . '</span>' : '' ) . '</' . $tag . '>';

			case 'Flipcard':
				self::wvars( $node, array( 'height' => array( '--vx-flip-h', 'px', '300' ), 'fBg' => array( '--vx-flip-fbg', 'color', '' ), 'fFg' => array( '--vx-flip-ffg', 'color', '' ), 'bBg' => array( '--vx-flip-bbg', 'color', '' ), 'bFg' => array( '--vx-flip-bfg', 'color', '' ) ) );
				$img  = trim( (string) $s( 'fImage', '' ) );
				$href = self::link_href( (string) $s( 'bHref', '' ) );
				$btn  = trim( (string) $s( 'bBtn', '' ) );
				$c    = 'vx-flip' . ( 'v' === $s( 'dir', 'h' ) ? ' is-v' : '' ) . ( 'click' === $s( 'trig', 'hover' ) ? ' is-click' : '' );
				return '<div' . self::wcls( $attr, $c ) . ' tabindex="0"><div class="vx-flip-in">'
					. '<div class="vx-flip-f">' . ( '' !== $img ? '<img class="vx-flip-img" src="' . esc_url( $img ) . '" alt="" loading="lazy">' : '' )
					. '<div class="vx-flip-c"><h3 class="vx-flip-t">' . esc_html( $s( 'fTitle', '' ) ) . '</h3>' . ( '' !== trim( (string) $s( 'fText', '' ) ) ? '<p class="vx-flip-p">' . esc_html( $s( 'fText', '' ) ) . '</p>' : '' ) . '</div></div>'
					. '<div class="vx-flip-b"><div class="vx-flip-c"><h3 class="vx-flip-t">' . esc_html( $s( 'bTitle', '' ) ) . '</h3>' . ( '' !== trim( (string) $s( 'bText', '' ) ) ? '<p class="vx-flip-p">' . esc_html( $s( 'bText', '' ) ) . '</p>' : '' )
					. ( '' !== $btn ? '<a class="vx-flip-btn" href="' . esc_url( '' !== $href ? $href : '#' ) . '">' . esc_html( $btn ) . '</a>' : '' ) . '</div></div></div></div>';

			case 'Bars':
				self::wvars( $node, array( 'barH' => array( '--vx-bh', 'px', '8' ), 'barC' => array( '--vx-bc', 'color', '' ), 'trackC' => array( '--vx-btc', 'color', '' ), 'gap' => array( '--vx-bgap', 'px', '18' ) ) );
				$out = '';
				foreach ( $items as $it ) {
					$l = trim( (string) ( $it['title'] ?? '' ) );
					$v = max( 0, min( 100, (int) ( $it['value'] ?? 0 ) ) );
					if ( '' === $l ) {
						continue;
					}
					$out .= '<div class="vx-skill"><div class="vx-skill-h"><span>' . esc_html( $l ) . '</span>' . ( $s( 'showVal', '1' ) ? '<b>' . $v . '%</b>' : '' ) . '</div>'
						. '<div class="vx-skill-t" role="progressbar" aria-valuenow="' . $v . '" aria-valuemin="0" aria-valuemax="100" aria-label="' . esc_attr( $l ) . '"><span class="vx-skill-f" style="--vx-v:' . $v . '%"></span></div></div>';
				}
				if ( '' === $out ) {
					return '';
				}
				return '<div' . self::wcls( $attr, 'vx-skills' ) . ( $s( 'anim', '1' ) ? ' data-vx-anim' : '' ) . '>' . $out . '</div>';

			case 'Shapedivider':
				self::wvars( $node, array( 'color' => array( '--vx-shc', 'color', '' ), 'height' => array( '--vx-shh', 'px', '80' ) ) );
				$shape = (string) $s( 'shape', 'wave' );
				$shape = isset( self::$SHAPES[ $shape ] ) ? $shape : 'wave';
				$c     = 'vx-shape' . ( $s( 'flipX', '' ) ? ' is-fx' : '' ) . ( $s( 'flipY', '' ) ? ' is-fy' : '' );
				return '<div' . self::wcls( $attr, $c ) . ' aria-hidden="true"><svg viewBox="0 0 1200 120" preserveAspectRatio="none" focusable="false">' . self::$SHAPES[ $shape ] . '</svg></div>';

			case 'Progresscircle':
				self::wvars( $node, array( 'size' => array( '--vx-pcz', 'px', '140' ), 'color' => array( '--vx-pcc', 'color', '' ), 'track' => array( '--vx-pctr', 'color', '' ) ) );
				$pv   = max( 0, min( 100, (float) str_replace( ',', '.', (string) $s( 'value', '75' ) ) ) );
				$pw   = max( 1, min( 24, self::el_int( $node, 'thick', 8 ) ) );
				$pr   = 50 - $pw / 2;
				$pc   = round( 2 * M_PI * $pr, 2 );
				$plab = trim( (string) $s( 'label', '' ) );
				$pnum = round( $pv ) . '%';
				return '<div' . self::wcls( $attr, 'vx-ring' ) . ( $s( 'anim', '1' ) ? ' data-vx-anim' : '' ) . ' role="img" aria-label="' . esc_attr( ( '' !== $plab ? $plab . ': ' : '' ) . $pnum ) . '">'
					. '<svg viewBox="0 0 100 100" aria-hidden="true" focusable="false"><circle class="vx-ring-t" cx="50" cy="50" r="' . $pr . '" stroke-width="' . $pw . '"/>'
					. '<circle class="vx-ring-f" cx="50" cy="50" r="' . $pr . '" stroke-width="' . $pw . '" style="--vx-c:' . $pc . ';--vx-o:' . round( $pc * ( 1 - $pv / 100 ), 2 ) . '"/></svg>'
					. '<span class="vx-ring-in" aria-hidden="true">' . ( $s( 'showVal', '1' ) ? '<b>' . esc_html( $pnum ) . '</b>' : '' ) . ( '' !== $plab ? '<span>' . esc_html( $plab ) . '</span>' : '' ) . '</span></div>';

			case 'Rating':
				self::wvars( $node, array( 'size' => array( '--vx-rate-z', 'px', '20' ), 'color' => array( '--vx-rate-c', 'color', '' ), 'empty' => array( '--vx-rate-e', 'color', '' ) ) );
				$max = max( 1, min( 10, self::el_int( $node, 'max', 5 ) ) );
				// A rating of 0 is a real value (el_set reads a stored '0' as off).
				$raw = (string) self::el_raw( $node, 'value', '4.8' );
				$raw = '' === trim( $raw ) ? '0' : $raw;
				$val = max( 0, min( $max, (float) str_replace( ',', '.', $raw ) ) );
				$st  = str_repeat( self::wsvg( 'star' ), $max );
				$lab = trim( (string) $s( 'label', '' ) );
				/* translators: 1: rating, 2: best possible rating. */
				$aria = sprintf( __( '%1$s out of %2$s', 'velox' ), $raw, $max );
				return '<div' . self::wcls( $attr, 'vx-rating' ) . ' role="img" aria-label="' . esc_attr( $aria ) . '">'
					. '<span class="vx-rate-s" style="--vx-rate:' . round( $val / $max * 100, 2 ) . '%"><span class="vx-rate-bg">' . $st . '</span><span class="vx-rate-fg">' . $st . '</span></span>'
					. ( $s( 'showText', '1' ) ? '<span class="vx-rate-t">' . esc_html( $raw . ' / ' . $max ) . '</span>' : '' )
					. ( '' !== $lab ? '<span class="vx-rate-l">' . esc_html( $lab ) . '</span>' : '' ) . '</div>';

			case 'Toc':
				$lv    = array( 'h2' => array( 'h2' ), 'h2h3' => array( 'h2', 'h3' ), 'h2h4' => array( 'h2', 'h3', 'h4' ) );
				$lv    = $lv[ (string) $s( 'tocLevels', 'h2h3' ) ] ?? array( 'h2', 'h3' );
				$heads = array();
				$walk  = function ( $list ) use ( &$walk, &$heads, $lv ) {
					foreach ( (array) $list as $x ) {
						if ( ! is_array( $x ) || ! empty( $x['hidden'] ) ) {
							continue;
						}
						if ( 'Heading' === ( $x['el'] ?? '' ) && in_array( $x['tag'] ?? '', $lv, true ) ) {
							$tx = trim( wp_strip_all_tags( (string) ( self::$wdoc['content'][ $x['id'] ] ?? '' ) ) );
							if ( '' !== $tx ) {
								$heads[] = array( 'id' => (string) $x['id'], 't' => $tx, 'l' => $x['tag'] );
							}
						}
						$walk( $x['children'] ?? array() );
					}
				};
				$walk( self::$wdoc['tree'] ?? array() );
				if ( ! $heads ) {
					return '';
				}
				$lt  = $s( 'tocNum', '' ) ? 'ol' : 'ul';
				$tt  = trim( (string) $s( 'tocTitle', __( 'Contents', 'velox' ) ) );
				$li  = '';
				foreach ( $heads as $h ) {
					$li .= '<li class="vx-toc-' . esc_attr( $h['l'] ) . '"><a href="#' . esc_attr( $h['id'] ) . '">' . esc_html( $h['t'] ) . '</a></li>';
				}
				return '<nav' . self::wcls( $attr, 'vx-toc' . ( $s( 'tocBoxed', '1' ) ? ' is-boxed' : '' ) ) . ' aria-label="' . esc_attr( '' !== $tt ? $tt : __( 'Contents', 'velox' ) ) . '">'
					. ( '' !== $tt ? '<p class="vx-toc-t">' . esc_html( $tt ) . '</p>' : '' ) . '<' . $lt . ' class="vx-toc-l">' . $li . '</' . $lt . '></nav>';

			case 'Share':
				self::wvars( $node, array( 'size' => array( '--vx-sz', 'px', '18' ), 'box' => array( '--vx-sbox', 'px', '40' ), 'gap' => array( '--vx-sgap', 'px', '10' ), 'color' => array( '--vx-sbg', 'color', '' ), 'iconC' => array( '--vx-sfg', 'color', '' ) ) );
				$url   = is_singular() ? (string) get_permalink() : home_url( '/' );
				$title = is_singular() ? (string) get_the_title() : (string) get_bloginfo( 'name' );
				$u     = rawurlencode( $url );
				$t     = rawurlencode( wp_strip_all_tags( html_entity_decode( $title, ENT_QUOTES, 'UTF-8' ) ) );
				$nets  = array(
					'facebook' => array( 'shFb', 'Facebook', 'https://www.facebook.com/sharer/sharer.php?u=' . $u, '#1877F2' ),
					'x'        => array( 'shX', 'X', 'https://x.com/intent/post?url=' . $u . '&text=' . $t, '#000000' ),
					'linkedin' => array( 'shLi', 'LinkedIn', 'https://www.linkedin.com/sharing/share-offsite/?url=' . $u, '#0A66C2' ),
					'whatsapp' => array( 'shWa', 'WhatsApp', 'https://wa.me/?text=' . $t . '%20' . $u, '#25D366' ),
					'email'    => array( 'shMail', __( 'Email', 'velox' ), 'mailto:?subject=' . $t . '&body=' . $u, '#64748b' ),
				);
				$out = '';
				foreach ( $nets as $n => $cfg ) {
					if ( ! $s( $cfg[0], '1' ) ) {
						continue;
					}
					/* translators: %s: network name, e.g. Facebook */
					$out .= '<a class="vx-soc vx-soc-' . $n . '" href="' . esc_url( $cfg[2], array( 'https', 'mailto' ) ) . '"' . ( 'email' !== $n ? ' target="_blank" rel="noopener"' : '' ) . ' aria-label="' . esc_attr( sprintf( __( 'Share on %s', 'velox' ), $cfg[1] ) ) . '" style="--b:' . $cfg[3] . '">' . self::wsvg( $n ) . '</a>';
				}
				if ( $s( 'shCopy', '1' ) ) {
					$out .= '<button type="button" class="vx-soc vx-soc-website" data-vx-copy="' . esc_attr( $url ) . '" data-vx-done="' . esc_attr__( 'Link copied', 'velox' ) . '" aria-label="' . esc_attr__( 'Copy link', 'velox' ) . '" style="--b:#64748b">' . self::wsvg( 'website' ) . '</button>';
				}
				if ( '' === $out ) {
					return '';
				}
				$shape = in_array( $s( 'shape', 'circle' ), array( 'circle', 'rounded', 'square', 'plain' ), true ) ? $s( 'shape', 'circle' ) : 'circle';
				$sty   = in_array( $s( 'style', 'mono' ), array( 'mono', 'brand', 'outline' ), true ) ? $s( 'style', 'mono' ) : 'mono';
				$lab   = trim( (string) $s( 'shLabel', '' ) );
				return '<div' . self::wcls( $attr, 'vx-social vx-share is-' . $shape . ' is-' . $sty ) . '>' . ( '' !== $lab ? '<span class="vx-share-l">' . esc_html( $lab ) . '</span>' : '' ) . $out . '</div>';

			case 'Copyright':
				$cy   = (int) wp_date( 'Y' );
				$from = (int) $s( 'cpFrom', '' );
				$yr   = ( $from > 1900 && $from < $cy ) ? $from . '–' . $cy : (string) $cy;
				$text = str_replace( array( '{year}', '{site}' ), array( $yr, (string) get_bloginfo( 'name' ) ), (string) $s( 'cpText', __( '© {year} {site}. All rights reserved.', 'velox' ) ) );
				$ctag = in_array( $node['tag'] ?? 'p', array( 'p', 'span', 'div', 'small' ), true ) ? $node['tag'] : 'p';
				return '<' . $ctag . self::wcls( $attr, 'vx-copyright' ) . '>' . esc_html( $text ) . '</' . $ctag . '>';

			case 'Copybtn':
				return '<button type="button"' . self::wcls( $attr, 'vx-copybtn' ) . ' data-vx-copy="' . esc_attr( (string) $s( 'cbText', '' ) ) . '" data-vx-done="' . esc_attr( (string) $s( 'cbDone', __( 'Copied!', 'velox' ) ) ) . '">'
					. ( $s( 'cbIcon', '1' ) ? '<svg viewBox="0 0 16 16" width="14" height="14" fill="currentColor" aria-hidden="true" focusable="false"><path d="M4 2a2 2 0 0 1 2-2h7a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2zm2-1a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h7a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1zM1 5a1 1 0 0 1 1-1v9a2 2 0 0 0 2 2h8a1 1 0 0 1-1 1H4a3 3 0 0 1-3-3z"/></svg>' : '' )
					. '<span>' . esc_html( (string) $s( 'cbLabel', __( 'Copy', 'velox' ) ) ) . '</span></button>';

			case 'Social':
				self::wvars( $node, array( 'size' => array( '--vx-sz', 'px', '18' ), 'box' => array( '--vx-sbox', 'px', '40' ), 'gap' => array( '--vx-sgap', 'px', '10' ), 'color' => array( '--vx-sbg', 'color', '' ), 'iconC' => array( '--vx-sfg', 'color', '' ) ) );
				$names = array( 'facebook' => 'Facebook', 'instagram' => 'Instagram', 'x' => 'X', 'linkedin' => 'LinkedIn', 'youtube' => 'YouTube', 'tiktok' => 'TikTok', 'pinterest' => 'Pinterest', 'whatsapp' => 'WhatsApp', 'github' => 'GitHub', 'threads' => 'Threads', 'telegram' => 'Telegram', 'discord' => 'Discord', 'spotify' => 'Spotify', 'email' => __( 'Email', 'velox' ), 'phone' => __( 'Phone', 'velox' ), 'website' => __( 'Website', 'velox' ) );
				$brand = array( 'facebook' => '#1877F2', 'instagram' => '#E4405F', 'x' => '#000000', 'linkedin' => '#0A66C2', 'youtube' => '#FF0000', 'tiktok' => '#000000', 'pinterest' => '#E60023', 'whatsapp' => '#25D366', 'github' => '#181717', 'threads' => '#000000', 'telegram' => '#26A5E4', 'discord' => '#5865F2', 'spotify' => '#1DB954', 'email' => '#475569', 'phone' => '#475569', 'website' => '#475569' );
				$nt  = (bool) $s( 'newTab', '1' );
				$out = '';
				foreach ( $items as $it ) {
					$n = (string) ( $it['net'] ?? '' );
					$u = trim( (string) ( $it['url'] ?? '' ) );
					if ( ! isset( $names[ $n ] ) || '' === $u ) {
						continue;
					}
					if ( 'email' === $n && false === strpos( $u, ':' ) ) {
						$u = 'mailto:' . $u;
					} elseif ( 'phone' === $n && false === strpos( $u, ':' ) ) {
						$u = 'tel:' . preg_replace( '/[^0-9+]/', '', $u );
					}
					$ext  = $nt && ! in_array( $n, array( 'email', 'phone' ), true );
					$out .= '<a class="vx-soc vx-soc-' . $n . '" href="' . esc_url( $u, array( 'http', 'https', 'mailto', 'tel' ) ) . '"' . ( $ext ? ' target="_blank" rel="noopener"' : '' ) . ' aria-label="' . esc_attr( $names[ $n ] ) . '" style="--b:' . $brand[ $n ] . '">' . self::wsvg( $n ) . '</a>';
				}
				if ( '' === $out ) {
					return '';
				}
				$shape = in_array( $s( 'shape', 'circle' ), array( 'circle', 'rounded', 'square', 'plain' ), true ) ? $s( 'shape', 'circle' ) : 'circle';
				$sty   = in_array( $s( 'style', 'mono' ), array( 'mono', 'brand', 'outline' ), true ) ? $s( 'style', 'mono' ) : 'mono';
				return '<div' . self::wcls( $attr, 'vx-social is-' . $shape . ' is-' . $sty ) . '>' . $out . '</div>';

			case 'Map':
				self::wvars( $node, array( 'height' => array( '--vx-map-h', 'px', '380' ) ) );
				$q = trim( (string) $s( 'query', '' ) );
				if ( '' === $q ) {
					return '';
				}
				$z   = max( 1, min( 20, self::el_int( $node, 'zoom', 14 ) ) );
				$src = 'https://maps.google.com/maps?q=' . rawurlencode( $q ) . '&z=' . $z . '&output=embed';
				$c   = 'vx-map' . ( $s( 'gray', '' ) ? ' is-gray' : '' );
				if ( $s( 'consent', '1' ) ) {
					return '<div' . self::wcls( $attr, $c ) . ' data-vx-map="' . esc_url( $src ) . '"><div class="vx-map-c"><p>' . esc_html( $s( 'cText', '' ) ) . '</p><button type="button" class="vx-map-btn">' . esc_html( $s( 'cBtn', __( 'Load map', 'velox' ) ) ) . '</button></div></div>';
				}
				return '<div' . self::wcls( $attr, $c ) . '><iframe src="' . esc_url( $src ) . '" title="' . esc_attr( $q ) . '" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe></div>';

			case 'Embed':
				$code = (string) $s( 'code', '' );
				if ( '' === trim( $code ) ) {
					return '';
				}
				$allowed = wp_kses_allowed_html( 'post' );
				$allowed['iframe'] = array( 'src' => true, 'width' => true, 'height' => true, 'title' => true, 'allow' => true, 'allowfullscreen' => true, 'loading' => true, 'frameborder' => true, 'style' => true, 'referrerpolicy' => true, 'class' => true, 'id' => true );
				foreach ( array( 'video', 'audio', 'source' ) as $t ) {
					$allowed[ $t ] = array( 'src' => true, 'controls' => true, 'autoplay' => true, 'muted' => true, 'loop' => true, 'playsinline' => true, 'poster' => true, 'type' => true, 'width' => true, 'height' => true, 'class' => true, 'style' => true );
				}
				return '<div' . self::wcls( $attr, 'vx-embed' ) . '>' . wp_kses( $code, $allowed ) . '</div>';

			case 'Codeblock':
				// HTML, CSS and JavaScript exactly as written, but only when someone
				// allowed to post unfiltered code saved this very code (the signature
				// is made on save with the site's secret). Anything else is shown as
				// filtered HTML without styles or scripts.
				$html = (string) $s( 'html', '' );
				$css  = (string) $s( 'css', '' );
				$js   = (string) $s( 'js', '' );
				if ( '' === trim( $html . $css . $js ) ) {
					return '';
				}
				if ( ! self::code_signed( $html, $css, $js, (string) $s( 'sig', '' ) ) ) {
					return '<div' . self::wcls( $attr, 'vx-code' ) . '>' . wp_kses_post( $html ) . '</div>';
				}
				$out = '<div' . self::wcls( $attr, 'vx-code' ) . '>' . $html;
				if ( '' !== trim( $css ) ) {
					$out .= '<style>' . str_ireplace( '</style', '<\/style', $css ) . '</style>';
				}
				if ( '' !== trim( $js ) ) {
					// `el` is this block, so scripts can find their own markup.
					$out .= '<script>(function(el){' . "\n" . str_ireplace( '</script', '<\/script', $js ) . "\n" . '}(document.currentScript.parentNode));</script>';
				}
				return $out . '</div>';

			case 'Table':
				$rows = array();
				foreach ( preg_split( '/\r\n|\r|\n/', (string) $s( 'data', '' ) ) as $line ) {
					if ( '' === trim( $line ) ) {
						continue;
					}
					$rows[] = array_map( 'trim', explode( '|', $line ) );
				}
				if ( ! $rows ) {
					return '';
				}
				$cols = max( array_map( 'count', $rows ) );
				$head = (bool) $s( 'head', '1' );
				$html = '<table>';
				foreach ( $rows as $ri => $r ) {
					$r = array_pad( $r, $cols, '' );
					$tagc = ( $head && 0 === $ri ) ? 'th' : 'td';
					$line = '<tr>' . implode( '', array_map( function ( $c ) use ( $tagc ) { return '<' . $tagc . ( 'th' === $tagc ? ' scope="col"' : '' ) . '>' . esc_html( $c ) . '</' . $tagc . '>'; }, $r ) ) . '</tr>';
					if ( $head && 0 === $ri ) {
						$html .= '<thead>' . $line . '</thead><tbody>';
					} else {
						$html .= ( ! $head && 0 === $ri ? '<tbody>' : '' ) . $line;
					}
				}
				$html .= '</tbody></table>';
				$c = 'vx-table' . ( $s( 'striped', '1' ) ? ' is-striped' : '' ) . ( $s( 'lined', '1' ) ? ' is-lined' : '' ) . ( $s( 'compact', '' ) ? ' is-compact' : '' ) . ( $s( 'firstBold', '' ) ? ' is-firstbold' : '' );
				return '<div' . self::wcls( $attr, $c ) . '>' . $html . '</div>';

			case 'Timeline':
				self::wvars( $node, array( 'lineC' => array( '--vx-tll', 'color', '' ), 'dotC' => array( '--vx-tld', 'color', '' ), 'gap' => array( '--vx-tlgap', 'px', '32' ) ) );
				$out = '';
				foreach ( $items as $it ) {
					$d = trim( (string) ( $it['date'] ?? '' ) );
					$t = trim( (string) ( $it['title'] ?? '' ) );
					$b = trim( (string) ( $it['body'] ?? '' ) );
					if ( '' === $d . $t . $b ) {
						continue;
					}
					$out .= '<div class="vx-tl-i"><span class="vx-tl-dot" aria-hidden="true"></span><div class="vx-tl-c">'
						. ( '' !== $d ? '<span class="vx-tl-d">' . esc_html( $d ) . '</span>' : '' )
						. ( '' !== $t ? '<h3 class="vx-tl-t">' . esc_html( $t ) . '</h3>' : '' )
						. ( '' !== $b ? '<p class="vx-tl-p">' . esc_html( $b ) . '</p>' : '' ) . '</div></div>';
				}
				if ( '' === $out ) {
					return '';
				}
				return '<div' . self::wcls( $attr, 'vx-timeline' . ( 'alt' === $s( 'layout', 'left' ) ? ' is-alt' : '' ) ) . '>' . $out . '</div>';

			case 'Checklist':
				self::wvars( $node, array( 'iconC' => array( '--vx-ck-c', 'color', '' ), 'gap' => array( '--vx-ck-gap', 'px', '12' ), 'iconSize' => array( '--vx-ck-z', 'px', '20' ) ) );
				$ic  = in_array( $s( 'icon', 'circle' ), array( 'check', 'circle', 'arrow', 'dot' ), true ) ? $s( 'icon', 'circle' ) : 'circle';
				$svg = self::wsvg( 'ck_' . $ic );
				$out = '';
				foreach ( $items as $it ) {
					$t = trim( (string) ( $it['title'] ?? '' ) );
					if ( '' !== $t ) {
						$out .= '<li class="vx-ck-i"><span class="vx-ck-ic">' . $svg . '</span><span class="vx-ck-t">' . esc_html( $t ) . '</span></li>';
					}
				}
				if ( '' === $out ) {
					return '';
				}
				return '<ul' . self::wcls( $attr, 'vx-check is-' . $ic ) . '>' . $out . '</ul>';
		}
		return '';
	}

	/** The look every widget starts with (classes and #id variables override it). */
	public static function widgets_css() {
		return
			// table of contents, share buttons, copy button, read more (4.67)
			'.vx-toc{line-height:1.5}:where(.vx-toc.is-boxed){padding:18px 20px;border:1px solid rgba(0,0,0,.1);border-radius:12px;background:rgba(0,0,0,.02)}'
			. '.vx-toc-t{margin:0 0 8px;font-weight:700}.vx-toc-l{margin:0;padding-left:1.25em;display:flex;flex-direction:column;gap:6px}ul.vx-toc-l{list-style:none;padding-left:0}'
			. '.vx-toc-l a{color:inherit;text-decoration:none}.vx-toc-l a:hover{text-decoration:underline}.vx-toc-h3{margin-left:1em}.vx-toc-h4{margin-left:2em}.vx-toc-empty{opacity:.6;font-style:italic}'
			. '.vx-share{align-items:center}.vx-share-l{font-weight:600;margin-right:4px}button.vx-soc{border:0;padding:0;font:inherit;cursor:pointer}.vx-soc.is-copied{outline:2px solid currentColor;outline-offset:2px}'
			. '.vx-copybtn{border:0;cursor:pointer;font:inherit;line-height:1.2}.vx-copybtn svg{width:1em;height:1em;flex:none;margin:0}'
			. '[data-vx-rm].vx-rm-on:not(.is-open){max-height:var(--vx-rm-h,160px);overflow:hidden}'
			. '[data-vx-rm="fade"].vx-rm-on:not(.is-open){-webkit-mask-image:linear-gradient(#000 45%,transparent);mask-image:linear-gradient(#000 45%,transparent)}'
			. '.vx-rm-btn{display:inline-flex;align-items:center;gap:6px;margin-top:10px;padding:0;border:0;background:none;font:inherit;font-weight:600;color:inherit;text-decoration:underline;text-underline-offset:3px;cursor:pointer}.vx-rm-btn[hidden]{display:none}'
			.
			// marquee
			'.vx-marquee{overflow:hidden;width:100%}.vx-mq-track{display:flex;width:max-content;animation:vx-mq var(--vx-mq-speed,30s) linear infinite}'
			. '.vx-mq-set{flex:0 0 auto;min-width:100%;display:flex;align-items:center;justify-content:space-around;gap:var(--vx-mq-gap,48px);padding-right:var(--vx-mq-gap,48px);box-sizing:border-box}'
			. '.vx-mq-item{display:inline-flex;align-items:center;gap:10px;white-space:nowrap}.vx-mq-item img{display:block;height:var(--vx-mq-img,32px);width:auto}'
			. '.vx-mq-sep{opacity:.4}.vx-marquee.is-rev .vx-mq-track{animation-direction:reverse}.vx-marquee.is-pause:hover .vx-mq-track{animation-play-state:paused}'
			. '.vx-marquee.is-fade{-webkit-mask-image:linear-gradient(90deg,transparent,#000 8%,#000 92%,transparent);mask-image:linear-gradient(90deg,transparent,#000 8%,#000 92%,transparent)}'
			. '@keyframes vx-mq{to{transform:translateX(-50%)}}@media (prefers-reduced-motion:reduce){.vx-mq-track{animation:none}.vx-mq-set[aria-hidden]{display:none}.vx-mq-set{flex-wrap:wrap;justify-content:center}}'
			// counter
			. '.vx-counter{display:flex;flex-direction:column;gap:6px}.vx-counter.vx-al-center{align-items:center;text-align:center}.vx-counter.vx-al-right{align-items:flex-end;text-align:right}'
			. '.vx-cnt-num{font-size:var(--vx-cnt-size,48px);font-weight:700;line-height:1;font-variant-numeric:tabular-nums;letter-spacing:-.02em}.vx-cnt-label{opacity:.72}'
			// countdown
			. '.vx-cd-units{display:flex;flex-wrap:wrap;gap:12px}.vx-cd-u{display:flex;flex-direction:column;align-items:center;min-width:72px}'
			. '.vx-cd-u b{font-size:var(--vx-cd-size,40px);font-weight:700;line-height:1.1;font-variant-numeric:tabular-nums}.vx-cd-u span{font-size:12px;letter-spacing:.06em;text-transform:uppercase;opacity:.7}'
			. '.vx-countdown.is-boxed .vx-cd-u{padding:14px 12px;border-radius:12px;background:rgba(15,23,42,.06)}.vx-countdown.is-done .vx-cd-units{opacity:.45}.vx-cd-done{margin:12px 0 0;font-weight:600}'
			// before / after
			. '.vx-compare{position:relative;overflow:hidden;aspect-ratio:var(--vx-cmp-ratio,16/9);border-radius:12px;user-select:none;-webkit-user-select:none;touch-action:pan-y}'
			. '.vx-cmp-a,.vx-cmp-b,.vx-cmp-b img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;display:block;margin:0;max-width:none}.vx-cmp-b{clip-path:inset(0 calc(100% - var(--vx-pos,50%)) 0 0)}'
			. '.vx-cmp-line{position:absolute;top:0;bottom:0;left:var(--vx-pos,50%);width:2px;margin-left:-1px;background:#fff;box-shadow:0 0 0 1px rgba(0,0,0,.12);pointer-events:none}'
			. '.vx-cmp-knob{position:absolute;top:50%;left:50%;width:42px;height:42px;margin:-21px 0 0 -21px;border-radius:50%;background:#fff;box-shadow:0 4px 14px rgba(0,0,0,.25)}'
			. '.vx-cmp-knob::before,.vx-cmp-knob::after{content:"";position:absolute;top:50%;width:0;height:0;margin-top:-6px;border:6px solid transparent}.vx-cmp-knob::before{left:7px;border-right-color:#334155}.vx-cmp-knob::after{right:7px;border-left-color:#334155}'
			. '.vx-cmp-l{position:absolute;top:12px;padding:4px 10px;border-radius:999px;background:rgba(15,23,42,.6);color:#fff;font-size:12px;font-weight:600;line-height:1.4;pointer-events:none}.vx-cmp-lb{left:12px}.vx-cmp-la{right:12px}'
			. '.vx-cmp-r{position:absolute;inset:0;width:100%;height:100%;margin:0;opacity:0;cursor:ew-resize;-webkit-appearance:none;appearance:none}'
			// gallery
			. '.vx-gallery{display:grid;grid-template-columns:repeat(var(--vx-gcols,3),minmax(0,1fr));gap:var(--vx-ggap,12px)}.vx-g-i{position:relative;margin:0;min-width:0}'
			. '.vx-g-i img{display:block;width:100%;height:auto;aspect-ratio:var(--vx-gratio,4/3);object-fit:cover;border-radius:var(--vx-grad,8px);transition:transform .45s cubic-bezier(.2,.7,.2,1),filter .3s}'
			. '.vx-g-a{display:block;overflow:hidden;border-radius:var(--vx-grad,8px);cursor:zoom-in}.vx-g-a:hover img{transform:scale(1.04)}.vx-g-cap{padding:8px 2px 0;font-size:14px;opacity:.75}'
			. '.vx-gallery.cap-overlay .vx-g-cap{position:absolute;left:0;right:0;bottom:0;padding:28px 12px 10px;border-radius:0 0 var(--vx-grad,8px) var(--vx-grad,8px);background:linear-gradient(transparent,rgba(0,0,0,.62));color:#fff;opacity:1;pointer-events:none}'
			. '.vx-lb{position:fixed;inset:0;z-index:2147483600;display:flex;align-items:center;justify-content:center;background:rgba(10,12,16,.92);opacity:0;transition:opacity .2s}.vx-lb.is-in{opacity:1}'
			. '.vx-lb img{max-width:92vw;max-height:84vh;display:block;border-radius:6px;box-shadow:0 20px 60px rgba(0,0,0,.5)}.vx-lb figcaption{position:absolute;left:0;right:0;bottom:22px;text-align:center;color:#e5e7eb;font-size:14px}'
			. '.vx-lb button{position:absolute;display:grid;place-items:center;width:46px;height:46px;border:0;border-radius:50%;background:rgba(255,255,255,.12);color:#fff;cursor:pointer}.vx-lb button:hover{background:rgba(255,255,255,.22)}.vx-lb button svg{width:20px;height:20px;fill:currentColor}'
			. '.vx-lb-x{top:18px;right:18px}.vx-lb-p{left:18px;top:50%;margin-top:-23px}.vx-lb-n{right:18px;top:50%;margin-top:-23px}'
			// rotating text
			. '.vx-rot-ws{display:inline-grid;vertical-align:bottom;color:var(--vx-rot-c,inherit)}.vx-rot-w{grid-area:1/1;opacity:0;transform:translateY(.35em);transition:opacity .45s,transform .45s;white-space:nowrap}'
			. '.vx-rot-w.is-on{opacity:1;transform:none}.vx-rotator[data-vx-fx="fade"] .vx-rot-w{transform:none}.vx-rotator[data-vx-fx="type"] .vx-rot-w{transition:none;transform:none}'
			. '.vx-rotator[data-vx-fx="type"] .vx-rot-w.is-on::after{content:"";display:inline-block;width:.08em;height:1em;margin-left:.06em;vertical-align:-.12em;background:currentColor;animation:vx-caret 1s steps(1) infinite}@keyframes vx-caret{50%{opacity:0}}'
			// flip card
			. '.vx-flip{perspective:1200px;height:var(--vx-flip-h,300px);border-radius:16px;outline:none}.vx-flip-in{position:relative;width:100%;height:100%;border-radius:inherit;transition:transform .7s cubic-bezier(.2,.7,.2,1);transform-style:preserve-3d}'
			. '.vx-flip-f,.vx-flip-b{position:absolute;inset:0;display:flex;align-items:flex-end;overflow:hidden;border-radius:inherit;-webkit-backface-visibility:hidden;backface-visibility:hidden}'
			. '.vx-flip-f{background:var(--vx-flip-fbg,#0f172a);color:var(--vx-flip-ffg,#fff)}.vx-flip-b{align-items:center;background:var(--vx-flip-bbg,#2ab7f1);color:var(--vx-flip-bfg,#04222f);transform:rotateY(180deg)}'
			. '.vx-flip.is-v .vx-flip-b{transform:rotateX(180deg)}.vx-flip:not(.is-click):hover .vx-flip-in,.vx-flip:not(.is-click):focus-within .vx-flip-in,.vx-flip.is-flipped .vx-flip-in{transform:rotateY(180deg)}'
			. '.vx-flip.is-v:not(.is-click):hover .vx-flip-in,.vx-flip.is-v:not(.is-click):focus-within .vx-flip-in,.vx-flip.is-v.is-flipped .vx-flip-in{transform:rotateX(180deg)}.vx-flip.is-click{cursor:pointer}'
			. '.vx-flip-img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;opacity:.5}.vx-flip-c{position:relative;padding:26px}.vx-flip-t{margin:0 0 8px;font-size:22px;line-height:1.2;color:inherit}.vx-flip-p{margin:0;line-height:1.55;opacity:.9}'
			. '.vx-flip-btn{display:inline-block;margin-top:16px;padding:10px 18px;border-radius:999px;background:var(--vx-flip-bfg,#04222f);color:var(--vx-flip-bbg,#2ab7f1);font-weight:600;text-decoration:none}'
			// skill bars
			. '.vx-skills{display:flex;flex-direction:column;gap:var(--vx-bgap,18px)}.vx-skill-h{display:flex;justify-content:space-between;gap:12px;margin-bottom:8px;font-size:15px;font-weight:500}.vx-skill-h b{font-variant-numeric:tabular-nums}'
			. '.vx-skill-t{height:var(--vx-bh,8px);border-radius:999px;background:var(--vx-btc,#e6eaf0);overflow:hidden}.vx-skill-f{display:block;height:100%;width:var(--vx-v);border-radius:inherit;background:var(--vx-bc,#2ab7f1);transition:width 1.2s cubic-bezier(.2,.7,.2,1)}'
			. '.vx-skills[data-vx-anim]:not(.is-in) .vx-skill-f{width:0}'
			// rating
			. '.vx-shape{display:block;line-height:0;color:var(--vx-shc,#f1f5f9);overflow:hidden}.vx-shape svg{display:block;width:100%;height:var(--vx-shh,80px);fill:currentColor}'
			. '.vx-shape.is-fx svg{transform:scaleX(-1)}.vx-shape.is-fy svg{transform:scaleY(-1)}.vx-shape.is-fx.is-fy svg{transform:scale(-1,-1)}'
			. '.vx-ring{position:relative;display:inline-grid;place-items:center;width:var(--vx-pcz,140px);height:var(--vx-pcz,140px)}.vx-ring svg{position:absolute;inset:0;width:100%;height:100%;transform:rotate(-90deg)}'
			. '.vx-ring circle{fill:none}.vx-ring-t{stroke:var(--vx-pctr,#e6eaf0)}.vx-ring-f{stroke:var(--vx-pcc,#2ab7f1);stroke-linecap:round;stroke-dasharray:var(--vx-c);stroke-dashoffset:var(--vx-o);transition:stroke-dashoffset 1.4s cubic-bezier(.2,.7,.2,1)}'
			. '.vx-ring[data-vx-anim]:not(.is-in) .vx-ring-f{stroke-dashoffset:var(--vx-c)}'
			. '.vx-ring-in{position:relative;display:flex;flex-direction:column;align-items:center;max-width:78%;text-align:center;line-height:1.2}.vx-ring-in b{font-size:calc(var(--vx-pcz,140px) * .22);font-weight:700;font-variant-numeric:tabular-nums}.vx-ring-in span{font-size:13px;opacity:.75}'
			. '@media (prefers-reduced-motion:reduce){.vx-ring-f{transition:none}}'
			. '.vx-rating{display:inline-flex;flex-wrap:wrap;align-items:center;gap:6px 10px}.vx-rate-s{position:relative;display:inline-flex;line-height:0}.vx-rate-bg,.vx-rate-fg{display:inline-flex;gap:2px;white-space:nowrap}'
			. '.vx-rate-fg{position:absolute;top:0;left:0;bottom:0;width:var(--vx-rate,100%);overflow:hidden}.vx-rate-s svg{flex:none;width:var(--vx-rate-z,20px);height:var(--vx-rate-z,20px)}.vx-rate-bg svg{fill:var(--vx-rate-e,#d6dbe3)}.vx-rate-fg svg{fill:var(--vx-rate-c,#f5b301)}'
			. '.vx-rate-t{font-weight:600}.vx-rate-l{opacity:.72}'
			// social
			. '.vx-social{display:flex;flex-wrap:wrap;align-items:center;gap:var(--vx-sgap,10px)}.vx-soc{display:inline-grid;place-items:center;width:var(--vx-sbox,40px);height:var(--vx-sbox,40px);border-radius:50%;color:var(--vx-sfg,#fff);background:var(--vx-sbg,#0f172a);text-decoration:none;transition:transform .15s,opacity .15s,background .15s}'
			. '.vx-soc:hover{transform:translateY(-2px)}.vx-soc svg{width:var(--vx-sz,18px);height:var(--vx-sz,18px);fill:currentColor}.vx-social.is-rounded .vx-soc{border-radius:10px}.vx-social.is-square .vx-soc{border-radius:0}'
			. '.vx-social.is-brand .vx-soc{background:var(--b)}.vx-social.is-outline .vx-soc{background:transparent;color:var(--vx-sbg,#0f172a);box-shadow:inset 0 0 0 1.5px currentColor}'
			. '.vx-social.is-plain .vx-soc{width:auto;height:auto;background:transparent;color:var(--vx-sbg,#0f172a);box-shadow:none}.vx-social.is-plain.is-brand .vx-soc{color:var(--b);background:transparent}'
			// map
			. '.vx-map{position:relative;height:var(--vx-map-h,380px);border-radius:12px;overflow:hidden;background:#e9eef3}.vx-map iframe{position:absolute;inset:0;width:100%;height:100%;border:0}.vx-map.is-gray iframe{filter:grayscale(1)}'
			. '.vx-map-c{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:14px;padding:24px;text-align:center;font-size:14px;line-height:1.5;color:#334155;background:repeating-linear-gradient(45deg,#eef2f6 0 14px,#e8edf2 14px 28px)}'
			. '.vx-map-c p{max-width:440px;margin:0}.vx-map-btn{padding:11px 20px;border:0;border-radius:999px;background:#0f172a;color:#fff;font:inherit;font-weight:600;cursor:pointer}.vx-map-btn:hover{background:#1e293b}'
			// embed + table
			. '.vx-embed{max-width:100%}.vx-embed iframe{max-width:100%}'
			. '.vx-table{overflow-x:auto;-webkit-overflow-scrolling:touch}.vx-table table{width:100%;border-collapse:collapse;font-size:15px}.vx-table th,.vx-table td{padding:12px 14px;text-align:left;vertical-align:top}'
			. '.vx-table th{font-weight:600;background:rgba(15,23,42,.05)}.vx-table.is-lined th,.vx-table.is-lined td{border-bottom:1px solid rgba(15,23,42,.1)}.vx-table.is-striped tbody tr:nth-child(even){background:rgba(15,23,42,.03)}'
			. '.vx-table.is-compact th,.vx-table.is-compact td{padding:7px 10px}.vx-table.is-firstbold tbody td:first-child{font-weight:600}'
			// timeline
			. '.vx-timeline{position:relative;display:flex;flex-direction:column;gap:var(--vx-tlgap,32px);padding-left:30px}.vx-timeline::before{content:"";position:absolute;left:7px;top:6px;bottom:6px;width:2px;background:var(--vx-tll,#d6dde6)}'
			. '.vx-tl-i{position:relative}.vx-tl-dot{position:absolute;left:-30px;top:3px;width:16px;height:16px;border-radius:50%;background:var(--vx-tld,#2ab7f1);box-shadow:0 0 0 4px #fff}'
			. '.vx-tl-d{display:block;font-size:13px;font-weight:600;letter-spacing:.03em;color:var(--vx-tld,#2ab7f1)}.vx-tl-t{margin:4px 0 6px;font-size:20px;line-height:1.3}.vx-tl-p{margin:0;line-height:1.6;opacity:.8}'
			. '@media (min-width:768px){.vx-timeline.is-alt{padding-left:0}.vx-timeline.is-alt::before{left:50%;margin-left:-1px}.vx-timeline.is-alt .vx-tl-i{width:50%;padding-right:44px;box-sizing:border-box;text-align:right}'
			. '.vx-timeline.is-alt .vx-tl-i:nth-child(even){margin-left:50%;padding:0 0 0 44px;text-align:left}.vx-timeline.is-alt .vx-tl-dot{left:auto;right:-8px}.vx-timeline.is-alt .vx-tl-i:nth-child(even) .vx-tl-dot{right:auto;left:-8px}}'
			// checklist
			. '.vx-check{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:var(--vx-ck-gap,12px)}.vx-ck-i{display:flex;align-items:flex-start;gap:10px;line-height:1.5}'
			. '.vx-ck-ic{flex:none;display:grid;place-items:center;width:var(--vx-ck-z,20px);height:1.5em;color:var(--vx-ck-c,#16a34a)}.vx-ck-ic svg{width:var(--vx-ck-z,20px);height:var(--vx-ck-z,20px);fill:currentColor}.vx-check.is-dot .vx-ck-ic svg{width:8px;height:8px}'
			// image crop (Image → "Fill the box")
			// Fit and position set on the image's box reach the photo (object-fit is not
			// inherited by itself, so "contain" did nothing); cover stays the default.
			. '.vx-img-cover{overflow:hidden}:where(.vx-img-cover){object-fit:cover;object-position:50% 50%}.vx-img-cover img,.vx-img-cover>a{display:block;width:100%!important;height:100%!important;max-width:none!important;object-fit:inherit;object-position:inherit}';
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
			$out .= '<span class="vx-acc-i" aria-hidden="true">' . Velox_Icons::svg( 'chevron-down', 15 ) . '</span>';
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
		// Look: underlined tabs, pills, or a switch between exactly two options.
		$look = in_array( self::el_set( $node, 'look', 'tabs' ), array( 'tabs', 'pills', 'switch' ), true ) ? self::el_set( $node, 'look', 'tabs' ) : 'tabs';
		if ( 'switch' === $look && 2 !== count( $items ) ) {
			$look = 'pills';
		}
		if ( 'tabs' !== $look ) {
			$to_acc = '';
			$orient = 'top';
		}
		$ord      = count( $items );
		$bp       = class_exists( 'Velox_Builder' ) ? Velox_Builder::breakpoints() : array( 'mobile' => 767 );

		$cls = trim( $classes . ' vx-tabs' . ( 'left' === $orient ? ' vx-tabs-left' : '' ) . ( 'tabs' !== $look ? ' vx-tabs-' . $look : '' ) );
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
			if ( 'switch' === $look && 0 === $i ) {
				$out .= '<button type="button" class="vx-sw-knob' . ( 1 === $start ? ' is-on' : '' ) . '" role="switch" aria-checked="' . ( 1 === $start ? 'true' : 'false' ) . '" aria-label="' . esc_attr( (string) ( $items[1]['title'] ?? '' ) ) . '"></button>';
			}
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
		// "Speed" drives the slide/fade itself (it only delayed hiding before: 800 ms still slid in 250).
		self::$float_css .= '#' . $id . '{--vx-ms:' . max( 0, min( 3000, (int) $ms ) ) . 'ms}';
		if ( ! self::el_set( $node, 'closeEsc', '1' ) ) { $attr .= ' data-vx-noesc'; }
		if ( ! self::el_set( $node, 'closeBack', '1' ) ) { $attr .= ' data-vx-nobackclose'; }
		// "When it appears" and "Opened by" did nothing: neither was output.
		if ( 'dropdown' !== $kind && self::trig_type( $node ) ) {
			$attr .= self::floating_attrs( $node );
		}
		// Never again after a click inside; close by itself after N seconds.
		if ( 'dropdown' !== $kind && self::el_set( $node, 'stopOnClick', '' ) ) {
			$attr .= ' data-vx-stopclick';
		}
		$auto = (float) self::el_raw( $node, 'autoClose', '' );
		if ( 'dropdown' !== $kind && $auto > 0 ) {
			$attr .= ' data-vx-autoclose="' . min( 600, $auto ) . '"';
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
				esc_html( self::el_set( $node, 'label', __( 'Menu', 'velox' ) ) ) .
				'<span class="vx-dd-i" aria-hidden="true">' . Velox_Icons::svg( 'chevron-down', 14 ) . '</span></button>';
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
				'' . Velox_Icons::svg( 'x-lg', 16 ) . '</button>';
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
					'<button type="button" class="vx-prev" aria-label="' . esc_attr__( 'Previous slide', 'velox' ) . '"' . ( $loop ? '' : ' disabled' ) . '>' . Velox_Icons::svg( 'chevron-left', 16 ) . '</button>' .
					'<button type="button" class="vx-next" aria-label="' . esc_attr__( 'Next slide', 'velox' ) . '">' . Velox_Icons::svg( 'chevron-right', 16 ) . '</button>' .
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
			// "For a number of days", "after N pages in this visit", "on these screens".
			$days = (int) self::el_raw( $node, 'trigDays', '7' );
			if ( 'days' === (string) self::el_set( $node, 'trigOnce', '' ) ) {
				$out .= ' data-vx-days="' . max( 1, min( 3650, $days ) ) . '"';
			}
			$pages = (int) self::el_raw( $node, 'trigPages', '' );
			if ( $pages > 1 ) {
				$out .= ' data-vx-pages="' . min( 100, $pages ) . '"';
			}
			$dev = (string) self::el_set( $node, 'trigDevice', '' );
			if ( in_array( $dev, array( 'desktop', 'mobile' ), true ) ) {
				$b    = class_exists( 'Velox_Builder' ) ? Velox_Builder::breakpoints() : array( 'tablet' => 991 );
				$tab  = (int) ( $b['tablet'] ?? 991 );
				$out .= ' data-vx-mq="' . esc_attr( 'desktop' === $dev ? '(min-width: ' . ( $tab + 1 ) . 'px)' : '(max-width: ' . $tab . 'px)' ) . '"';
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
				$inner .= '<span class="vx-bar-text">' . esc_html( self::el_set( $node, 'text', __( 'Free first consultation — get in touch', 'velox' ) ) ) . '</span>';
				$lt = self::el_set( $node, 'linkText', '' );
				$lu = self::el_set( $node, 'linkUrl', '' );
				if ( $lt && $lu ) { $inner .= ' <a class="vx-bar-link" href="' . esc_url( $lu ) . '">' . esc_html( $lt ) . '</a>'; }
				if ( self::el_set( $node, 'dismiss', '1' ) ) {
					$inner .= '<button type="button" class="vx-bar-x" data-vx-dismiss aria-label="' . esc_attr__( 'Close', 'velox' ) . '">' .
						'' . Velox_Icons::svg( 'x-lg', 14 ) . '</button>';
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

		$label  = self::el_set( $node, 'label', 'Backtotop' === $el ? __( 'Back to top', 'velox' ) : '' );
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
		// Back to top: a ring around it fills as the page is read.
		if ( 'Backtotop' === $el && self::el_set( $node, 'ring', '' ) ) {
			$rc   = self::sanitize_value( (string) self::el_set( $node, 'ringColor', '' ) );
			$out .= '<svg class="vx-btt-ring" viewBox="0 0 48 48" aria-hidden="true"><circle cx="24" cy="24" r="22.5" pathLength="100" style="' . ( '' !== $rc ? 'stroke:' . esc_attr( $rc ) : '' ) . '"/></svg>';
			$out  = preg_replace( '/^<' . $tag . ' /', '<' . $tag . ' data-vx-ring ', $out, 1 );
			if ( empty( self::$btt_ring_css ) ) {
				self::$btt_ring_css = true;
				self::$float_css   .= '[data-vx-ring] .vx-btt-ring{position:absolute;inset:-3px;width:calc(100% + 6px);height:calc(100% + 6px);transform:rotate(-90deg);pointer-events:none;overflow:visible}'
					. '[data-vx-ring] .vx-btt-ring circle{fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-dasharray:100;stroke-dashoffset:calc(100 - var(--vx-p,0) * 100)}';
			}
		}
		return $out . '</' . $tag . '>';
	}

	/** Per-instance CSS for floating elements, collected during render. */
	private static $float_css = '';
	/** The back-to-top ring CSS is printed once. */
	private static $btt_ring_css = false;


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
			$home  = self::el_set( $node, 'homeLabel', __( 'Home', 'velox' ) );
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
			if ( self::el_set( $node, 'shrink', '' ) ) { self::$runtime_used['navshrink'] = true; $attr .= ' data-vx-shrink'; } // only this bar shrinks
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
		$mstyle = self::el_set( $node, 'mobileStyle', 'dropdown' );
		$full   = ! $anchor && 'fullscreen' === $mstyle;
		$oc     = ! $anchor && 'offcanvas' === $mstyle;
		$out  = '<nav class="' . esc_attr( trim( $classes . ' vx-nav' . ( $anchor ? ' vx-anchornav' : '' ) . ( $full ? ' vx-nav-full' : '' ) . ( $oc ? ' vx-nav-oc' : '' ) ) ) . '"' . $attr .
			' aria-label="' . esc_attr( $nav_name ) . '">';

		if ( ! $anchor ) {
			$blabel = trim( (string) self::el_set( $node, 'burgerLabel', '' ) );
			$out .= '<button type="button" class="vx-burger' . ( 'none' === self::el_set( $node, 'burgerAnim', 'x' ) ? ' vx-burger-still' : '' ) . '" aria-expanded="false" aria-controls="' . esc_attr( $id . ( $oc ? '-oc' : '-list' ) ) . '"' .
				( '' === $blabel ? ' aria-label="' . esc_attr__( 'Menu', 'velox' ) . '"' : '' ) . ' hidden>' .
				'<span class="vx-burger-i" aria-hidden="true"></span>' . ( '' !== $blabel ? '<span class="vx-burger-l">' . esc_html( $blabel ) . '</span>' : '' ) . '</button>';
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
					'<span aria-hidden="true">' . Velox_Icons::svg( 'chevron-down', 12 ) . '</span></button>';
				$out .= '<ul class="vx-submenu" id="' . esc_attr( $sid ) . '" hidden>';
				foreach ( $subs as $sub ) {
					$out .= '<li><a href="' . esc_url( $sub[1] ) . '">' . esc_html( $sub[0] ) . '</a></li>';
				}
				$out .= '</ul>';
			}
			$out .= '</li>';
		}
		$out .= '</ul>';
		if ( $oc ) {
			$out .= self::offcanvas_panel( $node, $id );
		}
		return $out . '</nav>';
	}

	/**
	 * The mobile menu as a side panel. velox-elements.js moves it to <body>
	 * (a header that hides on scroll must not take it along) and moves the
	 * link list into it on small screens, so there is one set of links.
	 */
	private static function offcanvas_panel( $node, $id ) {
		$vars = '--vx-oc-w:' . max( 240, min( 720, (int) self::el_set( $node, 'ocWidth', 380 ) ) ) . 'px;';
		foreach ( array( 'ocBg' => '--vx-oc-bg', 'ocColor' => '--vx-oc-c', 'ocBackdrop' => '--vx-oc-bd', 'ocAccent' => '--vx-oc-ac' ) as $k => $var ) {
			$v = self::sanitize_value( (string) self::el_set( $node, $k, '' ) );
			if ( '' !== $v ) {
				$vars .= $var . ':' . $v . ';';
			}
		}
		$side = 'left' === self::el_set( $node, 'ocSide', 'right' ) ? 'left' : 'right';
		$cta  = trim( (string) self::el_set( $node, 'ocCta', '' ) );
		$note = trim( (string) self::el_set( $node, 'ocNote', '' ) );
		$out  = '<div class="vx-oc" id="' . esc_attr( $id . '-oc' ) . '" data-side="' . $side . '" style="' . esc_attr( $vars ) . '" hidden>'
			. '<div class="vx-oc-bg" data-vx-oc-close></div>'
			. '<div class="vx-oc-panel" role="dialog" aria-modal="true" aria-label="' . esc_attr__( 'Menu', 'velox' ) . '" tabindex="-1">'
			. '<div class="vx-oc-head"><button type="button" class="vx-oc-x" data-vx-oc-close aria-label="' . esc_attr__( 'Close menu', 'velox' ) . '">'
			. '<svg viewBox="0 0 16 16" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M2.15 2.15a.5.5 0 0 1 .7 0L8 7.29l5.15-5.14a.5.5 0 0 1 .7.7L8.71 8l5.14 5.15a.5.5 0 0 1-.7.7L8 8.71l-5.15 5.14a.5.5 0 0 1-.7-.7L7.29 8 2.15 2.85a.5.5 0 0 1 0-.7"/></svg></button></div>'
			. '<div class="vx-oc-slot"></div>';
		if ( '' !== $cta ) {
			$out .= '<a class="vx-oc-cta" href="' . esc_url( self::link_value( (string) self::el_set( $node, 'ocCtaHref', '#' ) ) ) . '">' . esc_html( $cta ) . '</a>';
		}
		if ( '' !== $note ) {
			$out .= '<p class="vx-oc-note">' . nl2br( esc_html( $note ) ) . '</p>';
		}
		return $out . '</div></div>';
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
	/**
	 * The WordPress menus for the editor canvas, so a Navigation set to
	 * "A WordPress menu" shows those links there as on the page. Keyed by
	 * location, slug and name (what the "Menu name" field accepts); '__first'
	 * is the fallback the page uses when the name matches nothing.
	 */
	public static function menu_previews() {
		$out  = array( '__first' => self::wp_menu_items( '' ) );
		$keys = array_keys( (array) get_nav_menu_locations() );
		$all  = wp_get_nav_menus();
		if ( $all && ! is_wp_error( $all ) ) {
			foreach ( array_slice( $all, 0, 50 ) as $m ) {
				$keys[] = $m->slug;
				$keys[] = $m->name;
			}
		}
		foreach ( array_unique( array_filter( array_map( 'strval', $keys ) ) ) as $k ) {
			$out[ $k ] = self::wp_menu_items( $k );
		}
		return $out;
	}

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

	/** Every design token as a CSS custom property: colours, fluid type and spacing steps, custom tokens. */
	public static function token_vars() {
		if ( ! class_exists( 'Velox_Builder' ) ) {
			return '';
		}
		$vars = '';
		foreach ( Velox_Builder::token_list() as $tk ) {
			$val = self::sanitize_value( (string) $tk['value'] );
			if ( '' !== $val ) {
				$vars .= $tk['name'] . ':' . $val . ';';
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
		return ( '' !== $vars ? ':root{' . $vars . '}' : '' ) . self::canvas_fonts()['face'] . self::global_styles_css() . self::media_element_css() . self::widgets_css() . self::canvas_runtime_css();
	}

	/**
	 * The page's own CSS for sliders, tabs, accordions and navigation, for the editor
	 * canvas. The canvas kept a hand-made copy that drifted (borders between
	 * accordion items, grey tab colours, rounded slide images) — the editor
	 * showed things the page never did.
	 */
	public static function canvas_runtime_css() {
		$rt = self::$runtime_used;
		$fl = self::$float_css;
		self::$runtime_used = array( 'slider' => true, 'tabs' => true, 'accordion' => true, 'nav' => true, 'wpmenu' => true );
		self::$float_css    = '';
		$css = self::element_base_css( 'base' );
		self::$runtime_used = $rt;
		self::$float_css    = $fl;
		// Post content keeps the browser's text spacing on the page; the canvas
		// zeroes every margin, so give it back there.
		return $css . '.vx-prose :where(p,h1,h2,h3,h4,h5,h6,ul,ol,dl,figure,blockquote,pre,hr){margin:revert}';
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
		// Some themes and builders give every svg a margin (Oxygen: svg{margin-right:10px}),
		// which pushed Velox's icons off centre. Its own icons start from zero.
		return ':is([class*="vx-"]) svg{margin:0}'
			. '.vx-video{position:relative;width:100%;aspect-ratio:16/9;background:#0b0f14;overflow:hidden}'
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
	/**
	 * The example reviews for the editor canvas, so a Reviews element with
	 * "example reviews" on shows its real cards (and every style setting)
	 * there too; it was a grey placeholder. The CSS arrives with the preview CSS.
	 */
	public static function ajax_reviews_preview() {
		$s    = json_decode( wp_unslash( (string) ( $_POST['settings'] ?? '{}' ) ), true );
		$node = array( 'id' => sanitize_html_class( wp_unslash( (string) ( $_POST['id'] ?? 'rv' ) ) ), 'el' => 'Reviews', 'settings' => is_array( $s ) ? $s : array() );
		wp_send_json_success( array( 'html' => self::reviews_demo( $node ) ) );
	}

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
	/** The per-element CSS was printed already (before the class stylesheet). */
	private static $float_done = false;

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
		if ( isset( self::$runtime_used['widgets'] ) ) {
			$css .= self::widgets_css();
		}
		if ( isset( self::$runtime_used['ix'] ) ) {
			// Interactions: hidden until shown, and a pointer on click triggers.
			$css .= '[data-vx-ixhidden]:not(.vx-ix-on),.vx-ix-off{display:none!important}.vx-ix-click{cursor:pointer}';
		}
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
		if ( isset( self::$runtime_used['wpmenu'] ) ) {
			$css .= ':where(.vx-wpmenu),:where(.vx-wpmenu) ul{list-style:none;margin:0;padding:0}'
				. ':where(.vx-wpmenu){display:flex;flex-wrap:wrap;align-items:center;gap:8px 24px}'
				. ':where(.vx-wpmenu) li{position:relative}'
				. ':where(.vx-wpmenu) a{color:inherit;text-decoration:none}'
				. ':where(.vx-wpmenu) a:hover,:where(.vx-wpmenu) .current-menu-item>a{text-decoration:underline;text-underline-offset:4px}'
				. ':where(.vx-wpmenu) .sub-menu{display:none;position:absolute;left:0;top:100%;z-index:50;min-width:190px;padding:8px;'
				. 'background:#fff;color:#1a1d21;border-radius:10px;box-shadow:0 8px 28px rgba(15,23,42,.14)}'
				. ':where(.vx-wpmenu) .sub-menu a{display:block;padding:7px 10px;border-radius:6px}'
				. ':where(.vx-wpmenu) li:hover>.sub-menu,:where(.vx-wpmenu) li:focus-within>.sub-menu{display:block}';
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
				'.vx-burger{background:none;border:none;padding:10px;cursor:pointer;color:inherit;display:inline-flex;align-items:center;gap:10px;font:inherit}' .
				'.vx-burger[hidden]{display:none}' .
				'.vx-burger-i{position:relative;display:block;width:22px;height:2px;background:currentColor;border-radius:2px;transition:background-color .2s ease}' .
				'.vx-burger-i::before,.vx-burger-i::after{content:"";position:absolute;left:0;width:100%;height:2px;border-radius:2px;background:currentColor;transition:transform .3s cubic-bezier(.2,.7,.2,1),top .3s cubic-bezier(.2,.7,.2,1)}' .
				'.vx-burger-i::before{top:-7px}.vx-burger-i::after{top:7px}' .
				'.vx-burger:not(.vx-burger-still)[aria-expanded="true"] .vx-burger-i{background-color:transparent}' .
				'.vx-burger:not(.vx-burger-still)[aria-expanded="true"] .vx-burger-i::before{top:0;transform:rotate(45deg)}' .
				'.vx-burger:not(.vx-burger-still)[aria-expanded="true"] .vx-burger-i::after{top:0;transform:rotate(-45deg)}' .
				'.vx-burger-l{font-weight:600;font-size:.95em}' .
				'.vx-oc{position:fixed;inset:0;z-index:99990;pointer-events:none;visibility:hidden;transition:visibility 0s linear .5s}' .
				'.vx-oc[hidden]{display:none}' .
				'.vx-oc.is-open{pointer-events:auto;visibility:visible;transition:none}' .
				'.vx-oc-bg{position:absolute;inset:0;background:var(--vx-oc-bd,rgba(12,12,14,.5));opacity:0;transition:opacity .4s ease;-webkit-backdrop-filter:blur(3px);backdrop-filter:blur(3px)}' .
				'.vx-oc-panel{position:absolute;top:0;bottom:0;right:0;width:min(var(--vx-oc-w,380px),88vw);display:flex;flex-direction:column;gap:28px;padding:18px 28px 32px;overflow-y:auto;overscroll-behavior:contain;background:var(--vx-oc-bg,#fff);color:var(--vx-oc-c,#111);box-shadow:-24px 0 60px rgba(0,0,0,.18);transform:translateX(100%);transition:transform .5s cubic-bezier(.2,.7,.2,1);outline:none}' .
				'.admin-bar .vx-oc-panel{top:var(--wp-admin--admin-bar--height,0px)}' .
				'.vx-oc[data-side="left"] .vx-oc-panel{right:auto;left:0;transform:translateX(-100%);box-shadow:24px 0 60px rgba(0,0,0,.18)}' .
				'.vx-oc.is-open .vx-oc-bg{opacity:1}.vx-oc.is-open .vx-oc-panel{transform:none}' .
				'.vx-oc-head{display:flex;justify-content:flex-end}' . // own name: vx-oc-top is the Offcanvas "from top" class
				'.vx-oc-x{display:grid;place-items:center;width:44px;height:44px;border-radius:50%;border:1px solid color-mix(in srgb,currentColor 20%,transparent);background:none;color:inherit;cursor:pointer;transition:transform .3s ease,background-color .2s ease}' .
				'.vx-oc-x:hover{transform:rotate(90deg);background:color-mix(in srgb,currentColor 8%,transparent)}' .
				'.vx-oc .vx-nav-list{position:static;display:flex;flex-direction:column;align-items:stretch;gap:0;padding:0;margin:0;background:none;box-shadow:none;font-size:1.3em;font-weight:600}' .
				'.vx-oc .vx-nav-item{flex-wrap:wrap;justify-content:space-between;padding:14px 0;border-bottom:1px solid color-mix(in srgb,currentColor 12%,transparent);opacity:0;transform:translateX(18px);transition:opacity .45s ease,transform .5s cubic-bezier(.2,.7,.2,1);transition-delay:calc(var(--i,0) * 50ms + 120ms)}' .
				'.vx-oc.is-open .vx-nav-item{opacity:1;transform:none}' .
				'.vx-oc .vx-nav-item a{flex:1}' .
				'.vx-oc .vx-submenu{position:static;width:100%;min-width:0;padding:8px 0 0 12px;background:none;box-shadow:none;font-size:.78em;font-weight:500}' .
				'.vx-oc-cta{display:flex;justify-content:center;align-items:center;padding:15px 22px;border-radius:999px;background:var(--vx-oc-ac,#111);color:var(--vx-oc-bg,#fff);font-weight:600;text-decoration:none;transition:transform .2s ease}' .
				'.vx-oc-cta:hover{transform:translateY(-2px)}' .
				'.vx-oc-note{margin:auto 0 0;font-size:14px;line-height:1.6;opacity:.7}' .
				'html.vx-lock,html.vx-lock body{overflow:hidden}' .
				'@media (prefers-reduced-motion:reduce){.vx-oc-panel,.vx-oc-bg,.vx-oc .vx-nav-item,.vx-burger-i::before,.vx-burger-i::after{transition:none}}' .
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
				$css .= '[data-vx-sticky][data-vx-shrink].is-stuck{padding-top:6px;padding-bottom:6px}';
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
				'.vx-ov-back{position:absolute;inset:0;z-index:0;opacity:0;transition:opacity var(--vx-ms,.25s) ease}' .
				'.vx-ov.is-open .vx-ov-back{opacity:1}' .
				'.vx-ov-panel{position:relative;z-index:2;max-height:100%;overflow:auto;background:#fff;min-height:56px;transition:transform var(--vx-ms,.25s) ease,opacity var(--vx-ms,.25s) ease}' .
				'.vx-ov-close{position:absolute;z-index:3;top:12px;right:12px;background:none;border:none;padding:6px;cursor:pointer;color:inherit;line-height:0}' .
				// offcanvas: slides in from its edge
				// Size and padding defaults weigh nothing (:where), so the panel's own
				// class can set width, height and padding (its padding-top lost to 44px).
				':where(.vx-offcanvas .vx-ov-panel){height:100%}' .
				'.vx-oc-right{justify-content:flex-end}.vx-oc-left{justify-content:flex-start}' .
				'.vx-oc-top{align-items:flex-start}.vx-oc-bottom{align-items:flex-end}' .
				'.vx-oc-right .vx-ov-panel{transform:translateX(100%)}.vx-oc-left .vx-ov-panel{transform:translateX(-100%)}' .
				'.vx-oc-top .vx-ov-panel{transform:translateY(-100%)}.vx-oc-bottom .vx-ov-panel{transform:translateY(100%)}' .
				'.vx-ov.is-open .vx-ov-panel{transform:none}' .
				// modal: centred box that scales in
				'.vx-modal{align-items:center;justify-content:center;padding:24px}' .
				'.vx-modal-top{align-items:flex-start}.vx-modal-bottom{align-items:flex-end}' .
				':where(.vx-modal .vx-ov-panel){width:100%}.vx-modal .vx-ov-panel{transform:scale(.96);opacity:0}' . ':where(.vx-modal .vx-ov-panel,.vx-offcanvas .vx-ov-panel){padding-top:44px}' .
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
				'.vx-tabs-pills .vx-tablist{display:inline-flex;gap:4px;padding:4px;border:none;border-radius:999px;background:rgba(0,0,0,.06)}' .
				'.vx-tabs-pills .vx-tab{margin:0;border:none;border-radius:999px;padding:8px 16px}.vx-tabs-pills .vx-tab[aria-selected="true"]{background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.14)}' .
				'.vx-tabs-switch .vx-tablist{justify-content:center;align-items:center;gap:14px;border:none}.vx-tabs-switch .vx-tab{margin:0;border:none;padding:6px 2px}' .
				'.vx-sw-knob{position:relative;flex:none;width:52px;height:30px;padding:0;border:none;border-radius:999px;background:var(--vx-sw,#2ab7f1);cursor:pointer}' .
				'.vx-sw-knob::after{content:"";position:absolute;top:3px;left:3px;width:24px;height:24px;border-radius:50%;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.25);transition:transform .2s}' .
				'.vx-sw-knob.is-on::after{transform:translateX(22px)}.vx-sw-knob:focus-visible{outline:2px solid currentColor;outline-offset:2px}' .
				'@media (prefers-reduced-motion:reduce){.vx-sw-knob::after{transition:none}}' .
				// Accordion fallback: the tab button moves above its own panel.
				'@media (max-width:' . (int) $bp['mobile'] . 'px){' .
					'.vx-tabs.vx-tabs-acc{display:flex;flex-direction:column}' .
					'.vx-tabs-acc .vx-tablist,.vx-tabs-acc .vx-tabpanels{display:contents;border:none}' .
					'.vx-tabs-acc .vx-tab,.vx-tabs-acc .vx-tabp{order:var(--vx-o,0)}' .
					'.vx-tabs-acc .vx-tab{display:block;width:100%;border:none;border-top:1px solid rgba(0,0,0,.12);margin:0;text-align:left}' .
					'.vx-tabs-acc .vx-tabp{padding:0 4px 16px}' .
				'}';
		}
		if ( isset( self::$runtime_used['accordion'] ) ) {
			// A thin line between items and a question size that suits a list
			// (it took the full h3 size). Class-level, so any class you set wins.
			$css .= '.vx-acc-item{border-bottom:1px solid rgba(0,0,0,.1)}' .
				'.vx-acc-h{margin:0;font-size:1.125rem;line-height:1.4}' .
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
	/**
	 * Velox pages are drawn by Velox, and the editor shows them without the
	 * theme. The theme's stylesheet and its global styles (theme.json) still
	 * came in through wp_head and overrode the global styles set in Velox —
	 * the body font, heading sizes and link colours on the page were the
	 * theme's, not the ones in the editor.
	 */
	public static function strip_theme_css() {
		if ( apply_filters( 'velox_builder_keep_theme_css', ! empty( Velox_Builder::global_styles()['theme']['keep'] ) ) ) {
			return;
		}
		// The theme's global styles set the body font, heading sizes and link
		// colours, over the ones set in Velox. Post content (blocks) still needs
		// WordPress's layout rules and colour/size presets from them, so a page
		// that shows it gets only those parts.
		wp_dequeue_style( 'global-styles' );
		remove_action( 'wp_footer', 'wp_enqueue_global_styles', 1 );
		remove_action( 'wp_head', 'wp_print_font_faces', 50 );
		if ( self::$prose_used && function_exists( 'wp_get_global_stylesheet' ) ) {
			$layout = wp_get_global_stylesheet( array( 'variables', 'presets', 'base-layout-styles' ) );
			if ( '' !== $layout ) {
				wp_register_style( 'velox-wp-layout', false, array(), null );
				wp_enqueue_style( 'velox-wp-layout' );
				wp_add_inline_style( 'velox-wp-layout', $layout );
			}
		} else {
			wp_dequeue_style( 'core-block-supports' );
		}
		wp_dequeue_style( 'classic-theme-styles' );
		$roots = array_unique( array( get_stylesheet_directory_uri(), get_template_directory_uri() ) );
		$q     = wp_styles();
		foreach ( (array) $q->queue as $h ) {
			$src = isset( $q->registered[ $h ] ) ? (string) $q->registered[ $h ]->src : '';
			foreach ( $roots as $root ) {
				if ( '' !== $src && 0 === strpos( $src, $root ) ) {
					wp_dequeue_style( $h );
				}
			}
		}
	}

	/** True once the page shows post content (blocks), which needs WordPress's layout CSS. */
	private static $prose_used = false;

	private static function output_page( $doc ) {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'strip_theme_css' ), 9999 );
		self::$prose_used = false;
		self::$rendered_doc = true;
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
					self::$prose_used = true;
					$body .= '<main class="velox-inner-content vx-prose">' . $doc['__html'] . '</main>';
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
	// Velox's icons start without the margin some themes give every svg (Oxygen: svg{margin-right:10px}).
	$base_css = ':is([class*="vx-"]) svg{margin:0}' . self::element_base_css( 'base' ) . ( self::$media_used ? self::media_element_css() : '' );
	if ( '' !== $base_css ) {
		echo '<style id="velox-element-base">' . $base_css . '</style>' . "\n"; // phpcs:ignore
	}
	// Per-element rules from element settings (#id …) also go BEFORE the class
	// and "This element" styles — the order the editor uses. After them, a
	// Pull quote's line padding beat the padding the user set on the element:
	// the editor showed 40px, visitors saw 20px.
	$float_css = self::element_base_css( 'float' );
	if ( '' !== $float_css ) {
		echo '<style id="velox-builder-float">' . $float_css . '</style>' . "\n"; // phpcs:ignore
	}
	self::$float_done = true;
	?>
	<?php if ( $css_url ) : ?>
		<link rel="stylesheet" id="velox-builder-css" href="<?php echo esc_url( $css_url ); ?>">
	<?php endif; ?>
	<?php
	// Inline mode (or no writable uploads folder): the class CSS goes right here
	// too, so the page's own custom CSS below still comes last.
	if ( '' !== self::$inline ) {
		self::print_inline();
		self::$inline = '';
	}
	self::print_global_head();
	?>
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'velox-built' ); ?><?php echo self::smooth_attr(); // phpcs:ignore ?>>
	<?php echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — built from sanitized model ?>
	<?php self::print_element_runtime(); ?>
	<?php self::print_aos(); ?>
	<?php self::print_motion(); ?>
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
		// Pages seen in this visit, for popups set to "after N pages".
		echo '<script>try{sessionStorage.setItem("vx_pv",(parseInt(sessionStorage.getItem("vx_pv"),10)||0)+1)}catch(e){}</script>' . "\n"; // phpcs:ignore
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
		// Velox Performance may already have printed the same two hints.
		if ( $has_goog && empty( $GLOBALS['velox_fonts_preconnected'] ) ) {
			$GLOBALS['velox_fonts_preconnected'] = true;
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
		$gs = ( self::$float_done ? '' : self::element_base_css( 'float' ) ) . self::global_styles_css() . ( self::$loop_used ? self::loop_css() : '' ) . $page_css;
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
	/** Adds a class to an attribute string that may already carry class="…". */
	private static function add_class_attr( $attr, $cls ) {
		if ( preg_match( '/\sclass="([^"]*)"/', $attr ) ) {
			return preg_replace( '/\sclass="([^"]*)"/', ' class="$1 ' . $cls . '"', $attr, 1 );
		}
		return ' class="' . $cls . '"' . $attr;
	}

	public static function global_styles_css() {
		if ( ! class_exists( 'Velox_Builder' ) ) {
			return '';
		}
		$g   = Velox_Builder::global_styles();
		// The editor canvas starts from box-sizing:border-box and no browser
		// margins; the page did not, so headings, paragraphs and cards sat
		// lower and wider than in the editor. Zero specificity, so any margin
		// set in the editor wins, and post content (.vx-prose) keeps its spacing.
		$out = ':where(.velox-built),:where(.velox-built) *,:where(.velox-built) ::before,:where(.velox-built) ::after{box-sizing:border-box}'
			. ':where(.velox-built) :where(h1,h2,h3,h4,h5,h6,p,figure,blockquote,ul,ol,dl,dd,pre,hr):not(:where(.vx-prose *)){margin:0}:where(figure.vx-fig){margin:0}'
			. ':where(body.velox-built){margin:0}';
		$px  = function ( $v ) { return ( '' === $v || null === $v ) ? '' : ( is_numeric( $v ) ? $v . 'px' : $v ); };

		$b = $g['body'];
		$decl = '';
		// No font chosen: the system font, as in the editor (the page fell back to the browser's serif).
		$decl .= '' !== $b['font'] ? "font-family:'" . str_replace( "'", '', $b['font'] ) . "',sans-serif;" : 'font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Inter,sans-serif;';
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
			if ( 'columns' === $key && '' !== ( $p['gap'] ?? '' ) ) { $decl .= 'gap:' . $px( $p['gap'] ) . ';'; }
			if ( '' !== $decl ) { $out .= ':where(' . $sel . '){' . $decl . '}'; }
		}
		// Div blocks: the space between children. A default, so a class always wins.
		if ( '' !== ( $g['blocks']['gap'] ?? '' ) ) {
			$out .= ':where(.block){gap:' . $px( $g['blocks']['gap'] ) . ';}';
		}
		return $out;
	}


	/**
	 * Animate-on-scroll runtime. Deliberately tiny and dependency-free: one
	 * IntersectionObserver, CSS does the animating. Printed only when the page
	 * actually contains an animated element, and skipped entirely for visitors
	 * who have asked for reduced motion.
	 */
	/** Motion is used on this page (set while rendering). */
	private static $motion_used = false;

	/**
	 * Motion settings on an element (node.mo) as data attributes for
	 * velox-motion.js: text reveal, children one after another, parallax,
	 * pointer effects, stacking cards, a pinned sideways row, particles.
	 */
	/**
	 * Motion settings with a Scroll section's own effect folded in: its
	 * "While scrolling" setting becomes the pinned sideways row or the
	 * stacking cards, so both run through the same runtime as node.mo.
	 */
	private static function eff_mo( $node ) {
		$m = isset( $node['mo'] ) && is_array( $node['mo'] ) ? $node['mo'] : array();
		if ( 'Scroller' !== ( $node['el'] ?? '' ) ) {
			return $m;
		}
		unset( $m['hscroll'], $m['hscrollOn'], $m['stack'], $m['stackTop'], $m['stackStep'] );
		if ( 'stack' === (string) self::el_set( $node, 'effect', 'side' ) ) {
			$m['stack']     = 1;
			$m['stackTop']  = (string) self::el_raw( $node, 'stackTop', '100' );
			$m['stackStep'] = (string) self::el_raw( $node, 'stackStep', '18' );
		} else {
			$m['hscroll']   = 1;
			$m['hscrollOn'] = (string) self::el_set( $node, 'pinOn', '' );
		}
		return $m;
	}

	/**
	 * What every element carries besides id and class: animate on scroll,
	 * custom attributes, the site header/footer part, show/hide with the
	 * header state, interactions and motion. Elements drawn by their own
	 * renderer (nav, floating bars, slider, popups, tabs, accordion) got none
	 * of it before 4.76; they now receive it on their root (see with_attrs).
	 */
	private static function shared_attrs( $node, $id, $own_only = false ) {
		// Animate-on-scroll: an element's own setting wins, otherwise the global
		// default animates everything. "none" opts a single element out.
		$aos_attr = '';
		$g_aos    = class_exists( 'Velox_Builder' ) ? ( Velox_Builder::global_styles()['aos'] ?? array() ) : array();
		$n_aos    = $node['aos'] ?? array();
		// Precedence: element, then this page, then the site default.
		$p_type   = self::$page_settings['aosType'] ?? '';
		$base     = ( '' !== $p_type ) ? ( 'none' === $p_type ? '' : $p_type ) : ( $g_aos['type'] ?? '' );
		$a_type   = $n_aos['type'] ?? ( $own_only ? '' : $base );
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
		$attr  = $aos_attr;
		$attr .= self::custom_attrs( $node );
		$attr .= self::site_part( $node, $id );
		// Inside a site header: show or hide with its state (scrolled, transparent).
		$hv = (string) ( $node['hdrVis'] ?? '' );
		if ( in_array( $hv, array( 'stuck', 'unstuck', 'over', 'unover' ), true ) ) {
			$attr .= ' data-vx-hv="' . $hv . '"';
		}
		$attr .= self::ix_attrs( $node );
		$attr .= self::motion_attrs( $node );
		return $attr;
	}

	/** Put the shared attributes on the root of HTML a special renderer built. */
	private static function with_attrs( $html, $node ) {
		$id    = sanitize_html_class( $node['id'] ?? '' );
		// Only the element's own animation: the site-wide "animate everything" never
		// reached these (floating bars, popups) and must not start hiding them now.
		$extra = self::shared_attrs( $node, $id, true );
		if ( '' === $extra || '' === (string) $html ) {
			return $html;
		}
		$add = function ( $m ) use ( $extra ) {
			return $m[0] . $extra;
		};
		// The element's own tag (the one carrying its id), else the first tag.
		if ( '' !== $id ) {
			$out = preg_replace_callback( '/<[a-z][a-z0-9]*(?=\s[^>]*\bid="' . preg_quote( $id, '/' ) . '")/i', $add, (string) $html, 1, $hit );
			if ( $hit ) {
				return $out;
			}
		}
		return preg_replace_callback( '/<[a-z][a-z0-9]*/i', $add, (string) $html, 1 );
	}

	private static function motion_attrs( $node ) {
		$m = self::eff_mo( $node );
		if ( ! is_array( $m ) || ! $m || self::$css_only ) {
			return '';
		}
		$a   = '';
		$int = function ( $k, $min, $max, $def ) use ( $m ) {
			$v = $m[ $k ] ?? '';
			return '' === $v ? $def : max( $min, min( $max, (int) $v ) );
		};
		$text = (string) ( $m['text'] ?? '' );
		if ( in_array( $text, array( 'words', 'chars', 'mask' ), true ) ) {
			$a .= ' data-vx-text="' . $text . '"';
			if ( 'load' === ( $m['textOn'] ?? '' ) ) {
				$a .= ' data-vx-text-on="load"';
			}
			if ( '' !== (string) ( $m['textStep'] ?? '' ) ) {
				$a .= ' data-vx-text-step="' . $int( 'textStep', 0, 1000, 70 ) . '"';
			}
			if ( '' !== (string) ( $m['textDur'] ?? '' ) ) {
				$a .= ' data-vx-text-dur="' . $int( 'textDur', 100, 4000, 700 ) . '"';
			}
		}
		$kids = (string) ( $m['kids'] ?? '' );
		if ( in_array( $kids, array( 'fade', 'fade-up', 'fade-left', 'fade-right', 'zoom-in', 'blur' ), true ) ) {
			$a .= ' data-vx-kids="' . $kids . '" data-vx-kids-step="' . $int( 'kidsStep', 0, 1000, 90 ) . '"';
		}
		$para = $int( 'para', -80, 80, 0 );
		if ( $para ) {
			$a .= ' data-vx-para="' . $para . '"' . ( ! empty( $m['paraDesk'] ) ? ' data-vx-para-desk' : '' );
		}
		if ( ! empty( $m['tilt'] ) ) {
			$a .= ' data-vx-tilt="' . $int( 'tiltMax', 2, 25, 8 ) . '"';
		}
		if ( ! empty( $m['glow'] ) ) {
			$gc = self::sanitize_value( (string) ( $m['glowColor'] ?? '' ) );
			$a .= ' data-vx-glow="' . esc_attr( '' !== $gc ? $gc : '1' ) . '"';
		}
		if ( ! empty( $m['magnet'] ) ) {
			$a .= ' data-vx-magnet="0.3"';
		}
		if ( ! empty( $m['stack'] ) ) {
			$a .= ' data-vx-stack="' . $int( 'stackTop', 0, 600, 100 ) . '" data-vx-stack-step="' . $int( 'stackStep', 0, 80, 18 ) . '"';
		}
		if ( 'Scroller' === ( $node['el'] ?? '' ) ) {
			$a .= ' data-vx-scroller="' . ( empty( $m['stack'] ) ? 'side' : 'stack' ) . '"';
		}
		if ( ! empty( $m['hscroll'] ) ) {
			$b  = class_exists( 'Velox_Builder' ) ? Velox_Builder::breakpoints() : array( 'tablet' => 991, 'mobile' => 767 );
			$a .= ' data-vx-hscroll="' . ( 'all' === ( $m['hscrollOn'] ?? '' ) ? 0 : (int) $b['tablet'] + 1 ) . '"';
		}
		if ( ! empty( $m['particles'] ) ) {
			$pc = self::sanitize_value( (string) ( $m['particlesColor'] ?? '' ) );
			$a .= ' data-vx-particles="' . $int( 'particlesDensity', 5, 300, 60 ) . '"' . ( '' !== $pc ? ' data-vx-particles-color="' . esc_attr( $pc ) . '"' : '' );
		}
		if ( '' !== $a ) {
			self::$motion_used = true;
		}
		return $a;
	}

	/** Smooth wheel scrolling, switched on in Global styles → Motion. */
	private static function smooth_attr() {
		if ( ! class_exists( 'Velox_Builder' ) ) {
			return '';
		}
		$m = Velox_Builder::global_styles()['motion'] ?? array();
		if ( empty( $m['smooth'] ) ) {
			return '';
		}
		self::$motion_used = true;
		return ' data-vx-smooth="' . esc_attr( (string) max( 0.04, min( 0.4, (float) ( $m['ease'] ?? 0.12 ) ) ) ) . '"';
	}

	/** The motion runtime and its CSS, only on pages that use motion. */
	public static function print_motion() {
		if ( ! self::$motion_used ) {
			return;
		}
		$ease = 'cubic-bezier(.2,.7,.2,1)';
		$css  = 'html.vx-mo [data-vx-text]:not(.vx-split){opacity:0;animation:vxMoShow 0s 2.5s forwards}'
			. '@keyframes vxMoShow{to{opacity:1}}'
			. '[data-vx-text] .vx-w{display:inline-block;vertical-align:top}'
			. '[data-vx-text="words"] .vx-w{overflow:hidden;padding-bottom:.12em;margin-bottom:-.12em}'
			. '[data-vx-text] .vx-wi{display:inline-block;transform:translateY(110%);transition:transform var(--vx-td,700ms) ' . $ease . '}'
			. '[data-vx-text] .vx-c{display:inline-block;opacity:0;transform:translateY(.45em);transition:opacity var(--vx-td,700ms) ease,transform var(--vx-td,700ms) ' . $ease . '}'
			. '[data-vx-text].vx-on .vx-wi,[data-vx-text].vx-on .vx-c{opacity:1;transform:none}'
			. '[data-vx-text="mask"].vx-split{overflow:clip;overflow-clip-margin:.18em}'
			. '[data-vx-text="mask"] .vx-mi{display:block;transform:translateY(110%);transition:transform var(--vx-td,800ms) ' . $ease . '}'
			. '[data-vx-text="mask"].vx-on .vx-mi{transform:none}'
			. '[data-vx-kids].vx-kids-ready>.vx-kid{opacity:0;transition:opacity .7s ease,transform .7s ' . $ease . ',filter .7s ease}'
			. '[data-vx-kids="fade-up"].vx-kids-ready>.vx-kid{transform:translateY(28px)}'
			. '[data-vx-kids="fade-left"].vx-kids-ready>.vx-kid{transform:translateX(-28px)}'
			. '[data-vx-kids="fade-right"].vx-kids-ready>.vx-kid{transform:translateX(28px)}'
			. '[data-vx-kids="zoom-in"].vx-kids-ready>.vx-kid{transform:scale(.94)}'
			. '[data-vx-kids="blur"].vx-kids-ready>.vx-kid{filter:blur(10px);transform:translateY(12px)}'
			. '[data-vx-kids].vx-on>.vx-kid{opacity:1;transform:none;filter:none}'
			. '[data-vx-para]{will-change:transform}'
			. '.vx-tilt{transition:transform .3s ease-out;will-change:transform}'
			. '.vx-magnet{transition:transform .3s ' . $ease . '}'
			. '.vx-glow{position:relative;isolation:isolate}'
			. '.vx-glow::after{content:"";position:absolute;inset:0;border-radius:inherit;pointer-events:none;z-index:1;opacity:0;transition:opacity .3s ease;background:radial-gradient(340px circle at var(--vx-gx,50%) var(--vx-gy,50%),var(--vx-glow,rgba(255,255,255,.16)),transparent 62%)}'
			. '.vx-glow:hover::after{opacity:1}'
			. '.vx-stack>.vx-stack-card{position:sticky;transform-origin:50% 0;will-change:transform}'
			. '[data-vx-hscroll]>.vx-hs-pin{gap:inherit;overflow-x:auto;scroll-snap-type:x mandatory;scrollbar-width:none;flex:1 1 100%;width:100%;min-width:0}'
			. '[data-vx-scroller="stack"]{display:flex;flex-direction:column}'
			. '[data-vx-hscroll]>.vx-hs-pin::-webkit-scrollbar{display:none}'
			. '[data-vx-hscroll]>.vx-hs-pin>.vx-hs-track{display:flex;gap:inherit;width:max-content}'
			. '[data-vx-hscroll]>.vx-hs-pin>.vx-hs-track>*{flex:none;scroll-snap-align:start}'
			. '[data-vx-hscroll].vx-hs-on>.vx-hs-pin{position:sticky;top:0;height:100vh;overflow:hidden;display:flex;align-items:center}'
			. '[data-vx-hscroll].vx-hs-on>.vx-hs-pin>.vx-hs-track{will-change:transform}'
			. '.vx-has-particles{position:relative;isolation:isolate}'
			. '.vx-particles{position:absolute;inset:0;z-index:-1;pointer-events:none}'
			. '@media (prefers-reduced-motion:reduce){[data-vx-text] .vx-wi,[data-vx-text] .vx-c,[data-vx-text] .vx-mi,[data-vx-kids]>.vx-kid{opacity:1!important;transform:none!important;clip-path:none!important;filter:none!important;transition:none!important}}';
		echo '<style id="velox-motion-css">' . $css . '</style>' . "\n"; // phpcs:ignore
		$src = defined( 'VELOX_URL' ) ? VELOX_URL . 'assets/js/velox-motion.js' : '';
		if ( $src ) {
			echo '<script src="' . esc_url( $src . '?ver=' . ( defined( 'VELOX_VERSION' ) ? VELOX_VERSION : '1' ) ) . '" defer></script>' . "\n";
		}
	}

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
		// Conditions (who, where, when) are checked here, on the server: an element
		// that does not pass is never sent to the browser, children included.
		// The editor's CSS pass (css_only) draws every element, whatever its conditions:
		// a Slider shown only to logged-out visitors still needs its CSS in the canvas.
		if ( ! self::$css_only && ! empty( $node['cond'] ) && class_exists( 'Velox_Builder_Conditions' ) && ! Velox_Builder_Conditions::passes( $node['cond'] ) ) {
			return '';
		}
		if ( isset( $node['el'] ) && in_array( $node['el'], array( 'Navbar', 'Breadcrumbs', 'Anchornav' ), true ) ) {
			$nc = array();
			foreach ( (array) ( $node['classes'] ?? array() ) as $c ) { $nc[] = sanitize_html_class( ltrim( $c, '.' ) ); }
			return self::with_attrs( self::render_nav( $node, $doc, implode( ' ', array_filter( $nc ) ) ), $node );
		}
		if ( isset( $node['el'] ) && in_array( $node['el'], array( 'Fab', 'Backtotop', 'Announcebar', 'Stickybar', 'Progressbar' ), true ) ) {
			$fc = array();
			foreach ( (array) ( $node['classes'] ?? array() ) as $c ) { $fc[] = sanitize_html_class( ltrim( $c, '.' ) ); }
			return self::with_attrs( self::render_floating( $node, $doc, implode( ' ', array_filter( $fc ) ) ), $node );
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
			return self::with_attrs( self::render_slider( $node, $doc, implode( ' ', array_filter( $sc ) ) ), $node );
		}
		if ( isset( $node['el'] ) && in_array( $node['el'], array( 'Offcanvas', 'Modal', 'Dropdown' ), true ) ) {
			$oc = array();
			foreach ( (array) ( $node['classes'] ?? array() ) as $c ) { $oc[] = sanitize_html_class( ltrim( $c, '.' ) ); }
			return self::with_attrs( self::render_overlay( $node, $doc, implode( ' ', array_filter( $oc ) ) ), $node );
		}
		if ( isset( $node['el'] ) && 'Tabs' === $node['el'] ) {
			$tc = array();
			foreach ( (array) ( $node['classes'] ?? array() ) as $c ) { $tc[] = sanitize_html_class( ltrim( $c, '.' ) ); }
			return self::with_attrs( self::render_tabs( $node, $doc, implode( ' ', array_filter( $tc ) ) ), $node );
		}
		if ( isset( $node['el'] ) && ( 'Accordion' === $node['el'] || 'Faq' === $node['el'] ) ) {
			$acc_cls = array();
			foreach ( (array) ( $node['classes'] ?? array() ) as $c ) {
				$acc_cls[] = sanitize_html_class( ltrim( $c, '.' ) );
			}
			return self::with_attrs( self::render_accordion( $node, $doc, implode( ' ', array_filter( $acc_cls ) ) ), $node );
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
				self::$prose_used = true;
				$inner = '<div class="velox-legacy-content vx-prose">' . self::$inner_html . '</div>';
			}
			// The page's own content is the document's main landmark.
			return '<main id="' . esc_attr( $node['id'] ?? '' ) . '" class="' . esc_attr( implode( ' ', array_filter( $classes ) ) ) . '">' . $inner . '</main>';
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
		$id   = sanitize_html_class( $node['id'] ?? '' );
		$attr = ' id="' . esc_attr( $id ) . '"';
		if ( $classes ) {
			$attr .= ' class="' . esc_attr( implode( ' ', $classes ) ) . '"';
		}
		// Part of the attributes from here on, so images, videos, icons,
		// reusables and WordPress-data elements animate too (they returned early).
		$attr .= self::shared_attrs( $node, $id );

		if ( isset( $node['el'] ) && isset( self::$WIDGETS[ $node['el'] ] ) ) {
			self::$wdoc = $doc;
			return self::render_widget( $node, $attr );
		}

		// Velox Shop elements: product grid, buy box, cart, checkout … drawn by the shop.
		if ( isset( $node['el'] ) && 0 === strpos( (string) $node['el'], 'Shop' ) ) {
			$inner = class_exists( 'Velox_Shop_Front' ) && Velox_Settings::get( 'util_shop' ) ? Velox_Shop_Front::element( $node['el'], (array) ( $node['settings'] ?? array() ) ) : '';
			return '' === $inner ? '' : '<div' . self::wcls( $attr, 'vx-shop' ) . '>' . $inner . '</div>';
		}

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
			// A component copy can change the parts the component marks as editable.
			if ( ! empty( $node['props'] ) && is_array( $node['props'] ) ) {
				$reuse = self::apply_props( $reuse, $node['props'] );
			}
			// A reusable that was never published is not on the site yet.
			if ( isset( $reuse['__status'] ) && 'published' !== $reuse['__status'] && ! self::$previewing ) {
				return '';
			}
			self::$reuse_stack[ $ref ] = true;
			$inner = self::render_tree( $reuse['tree'], $reuse );
			unset( self::$reuse_stack[ $ref ] );
			// A site header or footer inside a reusable: the wrapper must not make
			// a box of its own. A sticky header can only stick inside its parent,
			// and a wrapper exactly as tall as the header gave it no room at all.
			foreach ( $reuse['tree'] as $rn ) {
				if ( is_array( $rn ) && in_array( $rn['hdr']['mode'] ?? '', array( 'header', 'footer' ), true ) ) {
					$attr = self::wcls( $attr, 'vx-part-wrap' );
					if ( false === strpos( self::$float_css, '.vx-part-wrap{' ) ) {
						self::$float_css .= '.vx-part-wrap{display:contents}';
					}
					break;
				}
			}
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
			if ( '' === $body ) {
				return '';
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
			$href  = isset( $node['href'] ) ? self::link_href( $node['href'] ) : '';
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
			// The tooltip text set in the editor's Title field.
			$ititle = isset( $node['imgTitle'] ) ? sanitize_text_field( (string) $node['imgTitle'] ) : '';
			$img = '';
			$size    = (string) self::el_set( $node, 'imgSize', 'large' );
			$size    = in_array( $size, array( 'medium', 'medium_large', 'large', 'full' ), true ) ? $size : 'large';
			$loading = (string) self::el_set( $node, 'loading', '' );
			if ( $att && wp_attachment_is_image( $att ) ) {
				// A media-library image: let WordPress build the tag, so the page gets
				// srcset/sizes (phones download a small file), real width/height (no
				// layout jump) and the library's alt text when none was typed here.
				$attrs = array( 'style' => 'display:block;max-width:100%;height:auto' );
				if ( '' !== $ititle ) {
					$attrs['title'] = $ititle;
				}
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
				$img = '<img src="' . $src . '" alt="' . esc_attr( $alt ) . '"' . ( '' !== $ititle ? ' title="' . esc_attr( $ititle ) . '"' : '' ) . ' decoding="async"'
					. ( 'eager' === $loading ? ' loading="eager" fetchpriority="high"' : ' loading="lazy"' )
					. ' style="display:block;max-width:100%;height:auto">';
			}
			$href = isset( $node['href'] ) ? self::link_href( $node['href'] ) : '';
			if ( '' !== $img && '' !== $href ) {
				$img = '<a href="' . esc_url( $href ) . '"' . ( ! empty( $node['target'] ) && '_blank' === $node['target'] ? ' target="_blank" rel="noopener"' : '' ) . '>' . $img . '</a>';
			}
			if ( '' !== $img && self::el_set( $node, 'fit', '' ) ) {
				self::$runtime_used['widgets'] = true;
				$attr = self::wcls( $attr, 'vx-img-cover' );
			}
			$caption = trim( (string) self::el_set( $node, 'caption', '' ) );
			if ( '' !== $img && '' !== $caption ) {
				// No inline margin: it beat every margin set on the image's class.
				return '<figure' . self::wcls( $attr, 'vx-fig' ) . '>' . $img . '<figcaption>' . esc_html( $caption ) . '</figcaption></figure>';
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
		// A form from Velox → Mail, placed by the builder.
		if ( isset( $node['el'] ) && 'Form' === $node['el'] ) {
			$fid = (int) self::el_set( $node, 'form', '' );
			if ( self::$css_only || ! $fid || ! class_exists( 'Velox_Forms' ) ) {
				return '<div' . $attr . '></div>';
			}
			// Without the Mail utility the form cannot be sent, so it is not shown.
			if ( ! ( class_exists( 'Velox_Settings' ) && Velox_Settings::get( 'util_mail' ) ) ) {
				return current_user_can( 'manage_options' )
					? '<div' . $attr . '><p style="padding:12px 14px;border:1px dashed #d2d2d7;border-radius:10px;color:#6e6e73">' . esc_html__( 'This form is hidden: switch on Velox → Utilities → Mail to receive forms. (Only administrators see this.)', 'velox' ) . '</p></div>'
					: '';
			}
			return '<div' . $attr . '>' . Velox_Forms::render( $fid ) . '</div>';
		}
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
		// Query loop: the children repeat once per item; page links follow the box.
		$after = '';
		if ( ! empty( $node['loop'] ) && class_exists( 'Velox_Builder_Loop' ) ) {
			$looped = Velox_Builder_Loop::render( $node, $doc, self::$css_only );
			$kids   = $looped['inner'];
			$after  = $looped['after'];
		} else {
			$kids = self::render_tree( $node['children'] ?? array(), $doc );
		}
		// A row that moves sideways while pinned needs a pin and a track around its children.
		if ( ! empty( self::eff_mo( $node )['hscroll'] ) && '' !== $kids ) {
			$kids = '<div class="vx-hs-pin"><div class="vx-hs-track">' . $kids . '</div></div>';
		}
		if ( isset( $node['el'] ) && 'Readmore' === $node['el'] ) {
			self::$runtime_used['widgets'] = true;
			$rid = sanitize_html_class( $node['id'] ?? '' );
			if ( '' !== $rid ) {
				self::$float_css .= '#' . $rid . '{--vx-rm-h:' . max( 40, self::el_int( $node, 'rmHeight', 160 ) ) . 'px}';
			}
			$more   = (string) self::el_set( $node, 'rmMore', __( 'Read more', 'velox' ) );
			$less   = (string) self::el_set( $node, 'rmLess', __( 'Show less', 'velox' ) );
			$attr  .= ' data-vx-rm' . ( self::el_set( $node, 'rmFade', '1' ) ? '="fade"' : '' );
			// Shown by the runtime only when the content is taller than the closed height.
			$after .= '<button type="button" class="vx-rm-btn" aria-expanded="false" aria-controls="' . esc_attr( $node['id'] ?? '' ) . '" data-more="' . esc_attr( $more ) . '" data-less="' . esc_attr( $less ) . '" hidden>' . esc_html( $more ) . '</button>';
		}
		if ( 'a' === $tag ) {
			$href  = isset( $node['href'] ) ? esc_url( self::link_href( $node['href'] ) ) : '#';
			$attr .= ' href="' . $href . '"';
			if ( ! empty( $node['target'] ) && '_blank' === $node['target'] ) {
				$attr .= ' target="_blank" rel="noopener"';
			}
		}
		return '<' . $tag . $attr . '>' . $content . $kids . '</' . $tag . '>' . $after;
	}

	/**
	 * Site header / footer options on a section or box (node.hdr).
	 *
	 * Header: sticky from the top or appearing after a scroll distance (slide
	 * or fade, own speed), only above a breakpoint, hide on scroll down / show
	 * on scroll up, a different look once scrolled (background, glass, text
	 * colour, border, shadow, padding), a scroll progress line, transparent over
	 * the first section (with its own text colour), z-index. Elements inside
	 * can show or hide by state (data-vx-hv), e.g. a second logo when scrolled.
	 *
	 * Footer: stays at the bottom of short pages, or is revealed from behind
	 * the content as the page ends.
	 */
	private static function site_part( $node, $id ) {
		$h = $node['hdr'] ?? null;
		if ( ! is_array( $h ) || '' === $id || ! in_array( $h['mode'] ?? '', array( 'header', 'footer' ), true ) ) {
			return '';
		}
		// The same box twice on a page (a reusable used twice) needs its CSS once.
		static $done = array();
		$first        = ! isset( $done[ $id ] );
		$done[ $id ]  = true;
		$sel = '#' . $id;
		$bps = class_exists( 'Velox_Builder' ) ? Velox_Builder::breakpoints() : array( 'tablet' => 991, 'mobile' => 767 );
		if ( 'footer' === $h['mode'] ) {
			$attr = '';
			if ( ! empty( $h['reveal'] ) ) {
				// velox-elements.js pins it behind the content when it fits on screen.
				$attr .= ' data-vx-reveal';
				self::$runtime_used['header'] = true;
				if ( $first ) {
					self::$float_css .= 'html.vx-reveal-on ' . $sel . '{position:fixed;left:0;right:0;bottom:0;z-index:0}'
						. 'html.vx-reveal-on .vx-reveal-cover{position:relative;z-index:1;background-color:var(--vx-page-bg,#fff)}';
				}
			} elseif ( ! empty( $h['bottom'] ) && $first ) {
				// The page becomes a column; the footer's top margin soaks up the
				// spare height, so it sits at the bottom of short pages.
				self::$float_css .= 'body.velox-built{display:flex;flex-direction:column;min-height:calc(100vh - var(--wp-admin--admin-bar--height,0px))}'
					. 'body.velox-built>*{flex-shrink:0}' . $sel . '{margin-top:auto}';
			}
			return $attr;
		}
		$z      = isset( $h['z'] ) && '' !== $h['z'] ? max( 0, min( 99999, (int) $h['z'] ) ) : 100;
		$sticky = ! empty( $h['sticky'] );
		$appear = $sticky && 'appear' === ( $h['how'] ?? '' );
		$over   = in_array( $h['overlay'] ?? '', array( 'always', 'tablet', 'desktop' ), true ) ? $h['overlay'] : '';
		$speed  = isset( $h['speed'] ) && '' !== $h['speed'] ? max( 0, min( 2000, (int) $h['speed'] ) ) : 300;
		$anim   = in_array( $h['anim'] ?? '', array( 'slide', 'fade', 'none' ), true ) ? $h['anim'] : 'slide';
		// Sticky only from this width up (0 = everywhere).
		$min    = 'desktop' === ( $h['above'] ?? '' ) ? (int) $bps['tablet'] + 1 : ( 'tablet' === ( $h['above'] ?? '' ) ? (int) $bps['mobile'] + 1 : 0 );
		$mq     = function ( $rule ) use ( $min ) { return $min ? '@media (min-width:' . $min . 'px){' . $rule . '}' : $rule; };
		$attr   = ' data-vx-header';
		$css    = $sel . '{z-index:' . $z . ';--vx-hdr-speed:' . $speed . 'ms;transition:background-color var(--vx-hdr-speed) ease,color var(--vx-hdr-speed) ease,box-shadow var(--vx-hdr-speed) ease,padding var(--vx-hdr-speed) ease,border-color var(--vx-hdr-speed) ease,backdrop-filter var(--vx-hdr-speed) ease,transform var(--vx-hdr-speed) ease}';
		// Elements that show or hide with the header's state.
		$css   .= $sel . ':not(.is-stuck) [data-vx-hv="stuck"],' . $sel . '.is-stuck [data-vx-hv="unstuck"],'
			. $sel . ':not(.is-over) [data-vx-hv="over"],' . $sel . '.is-over [data-vx-hv="unover"]{display:none!important}';
		if ( $sticky ) {
			$attr .= ' data-vx-sticky data-vx-shrink-at="' . max( 0, (int) ( $h['at'] ?? ( $appear ? 300 : 60 ) ) ) . '"';
			if ( $min ) {
				$attr .= ' data-vx-sticky-min="' . $min . '"';
			}
			if ( ! empty( $h['up'] ) ) {
				$attr .= ' data-vx-hide-down';
				$css  .= $sel . '.is-hidden{transform:translateY(-110%)}';
			}
			if ( $appear ) {
				// In the flow until the distance is passed, then fixed and animated in.
				$attr .= ' data-vx-appear';
				$in    = 'slide' === $anim ? 'vxHdrSlide' : ( 'fade' === $anim ? 'vxHdrFade' : '' );
				$css  .= $mq( $sel . '.is-fixed{position:fixed;top:0;left:0;right:0' . ( $in ? ';animation:' . $in . ' var(--vx-hdr-speed) cubic-bezier(.2,.7,.2,1) both' : '' ) . '}'
					. '.admin-bar ' . $sel . '.is-fixed{top:var(--wp-admin--admin-bar--height,0px)}' );
				$css  .= '@keyframes vxHdrSlide{from{transform:translateY(-100%)}to{transform:none}}@keyframes vxHdrFade{from{opacity:0}to{opacity:1}}';
			} else {
				$css .= $mq( $sel . '{position:sticky;top:0}.admin-bar ' . $sel . '{top:var(--wp-admin--admin-bar--height,0px)}' );
			}
			$stuck = '';
			if ( isset( $h['pad'] ) && '' !== $h['pad'] ) {
				$p      = max( 0, min( 200, (int) $h['pad'] ) );
				$stuck .= 'padding-top:' . $p . 'px;padding-bottom:' . $p . 'px;';
			}
			$bg = self::sanitize_value( (string) ( $h['bg'] ?? '' ) );
			if ( '' !== $bg ) {
				$stuck .= 'background:' . $bg . ';';
			}
			$blur = isset( $h['blur'] ) && '' !== $h['blur'] ? max( 0, min( 60, (int) $h['blur'] ) ) : 0;
			if ( $blur ) {
				$stuck .= '-webkit-backdrop-filter:blur(' . $blur . 'px) saturate(1.4);backdrop-filter:blur(' . $blur . 'px) saturate(1.4);';
			}
			$col = self::sanitize_value( (string) ( $h['color'] ?? '' ) );
			if ( '' !== $col ) {
				$stuck .= 'color:' . $col . ';';
			}
			$line = self::sanitize_value( (string) ( $h['line'] ?? '' ) );
			if ( '' !== $line ) {
				$stuck .= 'border-bottom:1px solid ' . $line . ';';
			}
			$shadows = array( '1' => '0 6px 24px rgba(0,0,0,.08)', 'soft' => '0 6px 24px rgba(0,0,0,.08)', 'strong' => '0 12px 40px -8px rgba(0,0,0,.28)' );
			if ( ! empty( $h['shadow'] ) && isset( $shadows[ (string) $h['shadow'] ] ) ) {
				$stuck .= 'box-shadow:' . $shadows[ (string) $h['shadow'] ] . ';';
			}
			if ( '' !== $stuck ) {
				$css .= $sel . '.is-stuck{' . $stuck . '}';
			}
			$prog = self::sanitize_value( (string) ( $h['progress'] ?? '' ) );
			if ( '' !== $prog ) {
				$attr .= ' data-vx-progress';
				$css  .= $sel . ' .vx-hdr-progress{position:absolute;left:0;bottom:0;height:2px;width:100%;transform-origin:0 50%;transform:scaleX(var(--vx-p,0));background:' . $prog . ';pointer-events:none}';
			}
		}
		self::$runtime_used['header'] = true;
		if ( $over ) {
			// Out of the flow, so the first section starts at the very top. A
			// sticky header that floats over the hero has to be fixed instead.
			// :not(.is-stuck) outranks the box's own background (same #id, printed
			// later) and steps aside once the scrolled background takes over.
			$ocol = self::sanitize_value( (string) ( $h['overColor'] ?? '' ) );
			$rule = $sel . '{position:' . ( $sticky && ! $appear ? 'fixed' : 'absolute' ) . ';top:0;left:0;right:0}'
				. $sel . ':not(.is-stuck){background:transparent}'
				. ( '' !== $ocol ? $sel . '.is-over{color:' . $ocol . '}' : '' )
				. '.admin-bar ' . $sel . '{top:var(--wp-admin--admin-bar--height,0px)}';
			$omin = 'always' === $over ? 0 : ( 'desktop' === $over ? (int) $bps['tablet'] + 1 : (int) $bps['mobile'] + 1 );
			$attr .= ' data-vx-over="' . $omin . '"';
			$css  .= $omin ? '@media (min-width:' . $omin . 'px){' . $rule . '}' : $rule;
		}
		$css .= '@media (prefers-reduced-motion:reduce){' . $sel . '{transition:none;animation:none!important}}';
		if ( $first ) {
			self::$float_css .= $css;
		}
		return $attr;
	}

	/**
	 * Component properties: the component (a reusable) marks elements as
	 * editable per copy (node.cprop: text, image, link, show); a copy stores
	 * its own values in node.props keyed by element id. Only fields the
	 * component allows are taken; everything still goes through the normal
	 * escaping when rendered.
	 */
	public static function apply_props( $model, $props ) {
		if ( ! is_array( $props ) || ! $props ) {
			return $model;
		}
		$content = is_array( $model['content'] ?? null ) ? $model['content'] : array();
		$walk    = function ( $nodes ) use ( &$walk, $props, &$content ) {
			foreach ( (array) $nodes as $i => $n ) {
				$nid = (string) ( $n['id'] ?? '' );
				$cp  = $n['cprop'] ?? null;
				$p   = $props[ $nid ] ?? null;
				if ( is_array( $cp ) && is_array( $p ) ) {
					if ( ! empty( $cp['text'] ) && isset( $p['text'] ) && '' !== trim( (string) $p['text'] ) ) {
						$content[ $nid ] = (string) $p['text'];
					}
					if ( ! empty( $cp['image'] ) && ! empty( $p['img'] ) ) {
						$content[ $nid ] = esc_url_raw( (string) $p['img'] );
						$n['att']        = absint( $p['att'] ?? 0 );
						if ( isset( $p['alt'] ) ) {
							$n['alt']       = sanitize_text_field( (string) $p['alt'] );
							$n['altCustom'] = true;
						}
					}
					if ( ! empty( $cp['link'] ) && isset( $p['href'] ) && '' !== trim( (string) $p['href'] ) ) {
						$n['href'] = trim( (string) $p['href'] );
					}
					if ( ! empty( $cp['show'] ) && ! empty( $p['hide'] ) ) {
						$n['hidden'] = true;
					}
				}
				if ( ! empty( $n['children'] ) ) {
					$n['children'] = $walk( $n['children'] );
				}
				$nodes[ $i ] = $n;
			}
			return $nodes;
		};
		$model['tree']    = $walk( $model['tree'] ?? array() );
		$model['content'] = $content;
		return $model;
	}

	/**
	 * Interactions (node.ix): when something happens to this element (click,
	 * hover, scrolled into view, page load, page scrolled past a point), do
	 * something to it or another element (show, hide, classes, scroll to,
	 * open / close a popup, focus). Every value is checked here; the element
	 * runtime reads the result from data-vx-ix.
	 */
	private static function ix_attrs( $node ) {
		$out = '';
		if ( ! empty( $node['ixHidden'] ) ) {
			$out .= ' data-vx-ixhidden';
			self::$runtime_used['ix'] = true;
		}
		$list = $node['ix'] ?? null;
		if ( ! is_array( $list ) || ! $list ) {
			return $out;
		}
		$b     = class_exists( 'Velox_Builder' ) ? Velox_Builder::breakpoints() : array( 'tablet' => 991 );
		$tab   = (int) ( $b['tablet'] ?? 991 );
		$clean = array();
		foreach ( array_slice( $list, 0, 10 ) as $x ) {
			if ( ! is_array( $x ) ) {
				continue;
			}
			$on = (string) ( $x['on'] ?? '' );
			$do = (string) ( $x['do'] ?? '' );
			if ( ! in_array( $on, array( 'click', 'hover', 'inview', 'load', 'scroll' ), true )
				|| ! in_array( $do, array( 'show', 'hide', 'toggle', 'addClass', 'removeClass', 'toggleClass', 'scrollTo', 'open', 'close', 'focus' ), true ) ) {
				continue;
			}
			$t = trim( (string) ( $x['target'] ?? 'self' ) );
			if ( 'self' !== $t && ! preg_match( '/^[#.][A-Za-z][A-Za-z0-9_-]*$/', $t ) ) {
				continue;
			}
			$a = array( 'on' => $on, 'do' => $do, 'target' => $t );
			if ( in_array( $do, array( 'addClass', 'removeClass', 'toggleClass' ), true ) ) {
				$cls = sanitize_html_class( ltrim( (string) ( $x['cls'] ?? '' ), '.' ) );
				if ( '' === $cls ) {
					continue;
				}
				$a['cls'] = $cls;
			}
			$delay = (int) ( $x['delay'] ?? 0 );
			if ( $delay > 0 ) {
				$a['delay'] = min( 60000, $delay );
			}
			if ( 'scroll' === $on ) {
				$a['at'] = max( 0, min( 20000, (int) ( $x['at'] ?? 300 ) ) );
			}
			if ( ! empty( $x['once'] ) ) {
				$a['once'] = 1;
			}
			$bp = (string) ( $x['bp'] ?? '' );
			if ( 'desktop' === $bp ) {
				$a['mq'] = '(min-width: ' . ( $tab + 1 ) . 'px)';
			} elseif ( 'mobile' === $bp ) {
				$a['mq'] = '(max-width: ' . $tab . 'px)';
			}
			$clean[] = $a;
		}
		if ( $clean ) {
			$out .= " data-vx-ix='" . esc_attr( wp_json_encode( $clean ) ) . "'";
			self::$runtime_used['ix'] = true;
		}
		return $out;
	}

	/** A link field's address; {post.url} and the other dynamic links resolve to the current item. */
	private static function link_href( $href ) {
		$href = trim( (string) $href );
		return class_exists( 'Velox_Builder_Loop' ) ? Velox_Builder_Loop::href( $href ) : $href;
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

	/** A dynamic value as the page would print it (used by the loop preview). */
	public static function token_text( $token ) {
		return self::token_value( (string) $token );
	}

	/** Extra CSS that belongs to elements on this page (loop pagination and the like). */
	public static function add_css( $css ) {
		self::$float_css .= (string) $css;
	}

	/** How much element CSS has been collected so far (see css_trim). */
	public static function css_mark() {
		return strlen( self::$float_css );
	}

	/**
	 * Drop element CSS collected after a mark. A loop renders the same elements
	 * once per item; their CSS is the same every time, so only the first copy stays.
	 */
	public static function css_trim( $mark ) {
		self::$float_css = substr( self::$float_css, 0, (int) $mark );
	}

	private static function token_value( $token ) {
		$arg = '';
		if ( false !== strpos( $token, ':' ) ) {
			list( $token, $arg ) = array_pad( explode( ':', $token, 2 ), 2, '' );
		}
		// Loop items: post link, term and user fields, the item number.
		if ( class_exists( 'Velox_Builder_Loop' ) ) {
			$lv = Velox_Builder_Loop::token( $token );
			if ( null !== $lv ) {
				return $lv;
			}
		}
		$pid = get_the_ID();
		if ( 0 === strpos( $token, 'product.' ) ) {
			return class_exists( 'Velox_Shop_Front' ) ? Velox_Shop_Front::token( $token, $pid ) : '';
		}
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
			// For copyright lines that never go out of date.
			case 'date.year':     return esc_html( wp_date( 'Y' ) );
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
				$t = esc_html( get_the_title( $pid ) );
				if ( ! empty( $node['wpLink'] ) && $pid ) {
					$t = '<a href="' . esc_url( get_permalink( $pid ) ) . '">' . $t . '</a>';
				}
				return '<' . $tag . $attr . '>' . $t . '</' . $tag . '>';
			case 'content':
				self::$prose_used = true;
				return '<div' . self::add_class_attr( $attr, 'vx-prose' ) . '>' . apply_filters( 'the_content', get_post_field( 'post_content', $pid ) ) . '</div>';
			case 'featured':
				$img = $pid ? get_the_post_thumbnail( $pid, 'large', array( 'style' => 'display:block;max-width:100%;height:auto' ) ) : '';
				if ( '' !== $img && ! empty( $node['wpLink'] ) ) {
					// The title next to it is the accessible link; this one is a mouse shortcut.
					$img = '<a href="' . esc_url( get_permalink( $pid ) ) . '" tabindex="-1" aria-hidden="true">' . $img . '</a>';
				}
				return '<div' . $attr . '>' . $img . '</div>';
			case 'menu':
				// vx-wpmenu: a plain row of links with hover/focus submenus — WordPress's
				// own markup printed a bulleted list in browser blue. Kept at zero
				// specificity (:where) so the element's classes restyle it freely.
				$nav = wp_nav_menu( array( 'echo' => false, 'container' => false, 'fallback_cb' => false, 'menu_class' => 'menu vx-wpmenu' ) );
				if ( $nav ) {
					self::$runtime_used['wpmenu'] = true;
				}
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
					// Anything the editor accepts — not(:last-child), is(.active),
					// hover::after, nth-child(2n+1) — but nothing that could leave the
					// selector ({ } ; < @ \ or a comment).
					if ( preg_match( '/^[a-z:][a-z0-9_\-():.#+*>~=,\[\] ]*$/i', $st ) && false === strpos( $st, '/*' ) && substr_count( $st, '(' ) === substr_count( $st, ')' ) ) {
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
		// Element custom CSS (Settings → Custom CSS) last, so it refines the
		// class rules. Hidden elements are not on the page, so neither is theirs.
		foreach ( $trees as $tree ) {
			self::walk(
				$tree,
				function ( $node ) use ( &$out ) {
					if ( empty( $node['hidden'] ) ) {
						$out .= self::element_css( $node );
					}
				}
			);
		}
		return $out;
	}

	/**
	 * One element's custom CSS. %root% (or "selector") stands for the element;
	 * bare declarations apply to the element itself. Same rules as the page's
	 * own custom CSS: it is CSS, so it can't open a tag.
	 */
	private static function element_css( $node ) {
		$css = isset( $node['css'] ) && is_string( $node['css'] ) ? trim( $node['css'] ) : '';
		$id  = sanitize_html_class( $node['id'] ?? '' );
		if ( '' === $css || '' === $id ) {
			return '';
		}
		$css = str_ireplace( array( '</style', '<style', '</script', '<script' ), '', $css );
		$sel = '#' . $id;
		if ( false === strpos( $css, '{' ) ) {
			return $sel . '{' . $css . '}';
		}
		return preg_replace( '/%root%|\bselector\b/', $sel, $css );
	}

	/**
	 * Custom HTML attributes (Settings → Attributes). Plain names only, never an
	 * event handler, never id/class (Velox owns those); link-type values go
	 * through esc_url so a javascript: address can't sneak in.
	 */
	private static function custom_attrs( $node ) {
		if ( empty( $node['attrs'] ) || ! is_array( $node['attrs'] ) ) {
			return '';
		}
		$out = '';
		foreach ( array_slice( $node['attrs'], 0, 30 ) as $a ) {
			$a = (array) $a;
			$n = trim( (string) ( $a['n'] ?? '' ) );
			if ( ! preg_match( '/^[a-zA-Z_][a-zA-Z0-9_.:-]{0,60}$/', $n ) || preg_match( '/^on/i', $n ) || in_array( strtolower( $n ), array( 'id', 'class', 'data-node', 'contenteditable' ), true ) ) {
				continue;
			}
			$v = (string) ( $a['v'] ?? '' );
			if ( preg_match( '/^(href|src|action|formaction|xlink:href)$/i', $n ) ) {
				$v = esc_url( $v );
				if ( '' === $v ) {
					continue;
				}
			}
			$out .= ' ' . $n . ( '' === $v ? '' : '="' . esc_attr( $v ) . '"' );
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
		$out   = '';
		$rules = (array) $rules;
		// Shorthands first ("background" after "background-image" wipes the image).
		uksort(
			$rules,
			function ( $a, $b ) {
				return ( isset( self::$SHORTHAND[ $b ] ) ? 1 : 0 ) - ( isset( self::$SHORTHAND[ $a ] ) ? 1 : 0 );
			}
		);
		foreach ( $rules as $prop => $val ) {
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
	public static function sanitize_value( $v ) {
		$v = trim( $v );
		// Strip anything that could break out of the declaration/rule.
		$v = preg_replace( '/[{}<>;]/', '', $v );
		if ( preg_match( '/(expression\s*\(|javascript:|@import|behaviou?r\s*:)/i', $v ) ) { // "expression(" only: a font called "Expression Pro" is fine
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

	/**
	 * Readable CSS: one declaration per line, nested blocks indented. The
	 * generator writes compact CSS, so without this "Minify CSS: off" changed
	 * nothing. Braces and semicolons inside strings or url()/var() stay put.
	 */
	public static function pretty_css( $css ) {
		$css   = trim( (string) $css );
		$out   = '';
		$depth = 0;
		$paren = 0;
		$quote = '';
		$pad   = function ( $d ) { return str_repeat( "\t", max( 0, $d ) ); };
		$len   = strlen( $css );
		for ( $i = 0; $i < $len; $i++ ) {
			$c = $css[ $i ];
			if ( '' !== $quote ) {
				$out .= $c;
				if ( $c === $quote && '\\' !== $css[ $i - 1 ] ) { $quote = ''; }
				continue;
			}
			if ( '"' === $c || "'" === $c ) { $quote = $c; $out .= $c; continue; }
			if ( '(' === $c ) { $paren++; }
			if ( ')' === $c ) { $paren = max( 0, $paren - 1 ); }
			if ( $paren ) { $out .= $c; continue; }
			if ( '{' === $c ) {
				$depth++;
				$out = rtrim( $out ) . " {\n" . $pad( $depth );
			} elseif ( ';' === $c ) {
				$out = rtrim( $out ) . ";\n" . $pad( $depth );
			} elseif ( '}' === $c ) {
				$depth = max( 0, $depth - 1 );
				$body  = rtrim( $out );
				$out   = $body . ( '{' === substr( $body, -1 ) || ';' === substr( $body, -1 ) || '}' === substr( $body, -1 ) ? '' : ';' ) . "\n" . $pad( $depth ) . "}\n" . $pad( $depth );
			} elseif ( ( ' ' === $c || "\n" === $c || "\t" === $c ) && ( "\t" === substr( $out, -1 ) || "\n" === substr( $out, -1 ) ) ) {
				continue; // no stray spaces at line starts
			} else {
				$out .= $c;
			}
		}
		return trim( preg_replace( "/\n\s*\n+/", "\n", $out ) ) . "\n";
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
		$css = ! isset( $set['minify'] ) || ! empty( $set['minify'] ) ? self::minify_css( $css ) : self::pretty_css( $css );
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
