<?php
/**
 * Velox Builder — Oxygen import.
 *
 * A site built with Oxygen (classic, 2.x – 4.x) keeps every layout in Oxygen's
 * own post meta, not in post_content. To Velox those pages looked empty: the
 * editor opened a blank canvas and "View as" showed nothing. This converts an
 * Oxygen layout into a Velox document model so it can be opened, edited and
 * served by Velox.
 *
 * Sources, newest first:
 *   _oxygen_data           Oxygen 6 (built on the Breakdance engine) — a JSON
 *   _breakdance_data       tree of typed elements with nested properties.
 *   ct_builder_json        Oxygen 4.x — a JSON tree.
 *   ct_builder_shortcodes  Oxygen 2.x/3.x (and 4.x keeps it too) — nested
 *                          [ct_*] shortcodes, each carrying ct_options JSON.
 * Global classes come from the ct_components_classes option, global colours
 * ("color(3)") from oxygen_vsb_global_colors.
 *
 * Nothing here needs Oxygen to be active — it reads the stored data only, so a
 * site that already switched Oxygen off can still bring its pages over.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Velox_Builder_Oxygen {

	/** Oxygen media names → Velox breakpoint keys. */
	private static $MEDIA = array(
		'page-width'      => 'xl',
		'tablet'          => 'lg',
		'phone-landscape' => 'md',
		'phone-portrait'  => 'sm',
	);

	/** Oxygen option keys that are not CSS (or need special handling). */
	private static $SKIP = array( 'ct_id', 'ct_parent', 'selector', 'nicename', 'classes', 'activeselector', 'ct_content', 'tag', 'url', 'target', 'src', 'alt', 'attachment_id', 'image_type', 'custom-css', 'custom-js', 'code-php', 'code-css', 'code-js', 'full_shortcode', 'media', 'globalconditions', 'conditionstype', 'ct_depth', 'embed_src', 'menu_id', 'section-width', 'custom-width', 'background-size', 'background-repeat', 'background-position-left', 'background-position-top' );

	/** Shorthand Oxygen keys → Velox model keys. */
	private static $ALIAS = array(
		'background-color'  => 'background',
		'border-all-width'  => 'borderWidth',
		'border-all-style'  => 'borderStyle',
		'border-all-color'  => 'borderColor',
		'flex-wrap'         => 'flexWrap',
		'font-family'       => 'fontFamily',
	);

	private static $colors  = null;
	private static $skipped = array();
	private static $count   = 0;
	private static $page_css = '';
	private static $prefix   = 'ox';

	/* ---------------------------------------------------------- detection */

	/** True when the post carries an Oxygen layout. */
	public static function has_layout( $post_id ) {
		$post_id = (int) $post_id;
		if ( ! $post_id ) {
			return false;
		}
		$json = get_post_meta( $post_id, 'ct_builder_json', true );
		if ( is_string( $json ) && strlen( $json ) > 20 ) {
			return true;
		}
		$sc = get_post_meta( $post_id, 'ct_builder_shortcodes', true );
		if ( is_string( $sc ) && false !== strpos( $sc, '[ct_' ) ) {
			return true;
		}
		return null !== self::v6_root( $post_id );
	}

	/**
	 * Whether the site has any Oxygen / Breakdance layout at all. Asked on every
	 * editor load, so the answer is cached for 12 hours.
	 */
	public static function any() {
		$c = get_transient( 'velox_oxygen_any' );
		if ( false !== $c ) {
			return (bool) $c;
		}
		$any = (bool) self::sources();
		set_transient( 'velox_oxygen_any', $any ? 1 : 0, 12 * HOUR_IN_SECONDS );
		return $any;
	}

	/** Every post that holds an Oxygen layout (pages, posts, Oxygen templates). */
	public static function sources() {
		global $wpdb;
		$rows = $wpdb->get_results(
			"SELECT p.ID, p.post_title, p.post_type FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID
			 WHERE ( m.meta_key IN ('ct_builder_json','ct_builder_shortcodes','_oxygen_data','_breakdance_data')
			         OR ( m.meta_key LIKE '\\_%data' AND m.meta_value LIKE '{\"tree\\_json\\_string%' ) )
			   AND m.meta_value <> ''
			   AND p.post_status IN ('publish','draft','private','future')
			 GROUP BY p.ID ORDER BY p.post_type, p.post_title LIMIT 300",
			ARRAY_A
		);
		$out = array();
		foreach ( (array) $rows as $r ) {
			$out[] = array(
				'id'    => (int) $r['ID'],
				'title' => '' !== $r['post_title'] ? $r['post_title'] : __( '(no title)', 'velox' ),
				'type'  => in_array( $r['post_type'], array( 'ct_template', 'oxygen_template', 'oxygen_header', 'oxygen_footer', 'breakdance_template', 'breakdance_header', 'breakdance_footer' ), true ) ? __( 'Oxygen template', 'velox' ) : $r['post_type'],
			);
		}
		return $out;
	}

	/* ---------------------------------------------------------- conversion */

	/**
	 * Convert a post's Oxygen layout. Returns
	 *   array( 'model' => {tree, classes, content, page}, 'count' => n, 'skipped' => [types] )
	 * or null when the post has no Oxygen data.
	 */
	public static function convert( $post_id, $allow_inner = false ) {
		$nodes = self::read_tree( (int) $post_id );
		if ( null === $nodes ) {
			return null;
		}
		self::$skipped  = array();
		self::$count    = 0;
		self::$page_css = '';
		// A component that points back at this very post must not be inlined.
		self::$block_stack = array( (int) $post_id => true );
		// Ids must not collide with anything already on the page, including an
		// earlier import, so every import gets its own short random prefix.
		self::$prefix   = 'x' . substr( md5( uniqid( '', true ) ), 0, 4 );
		$model = array(
			'tree'    => array(),
			'classes' => array(),
			'content' => array(),
		);
		// Oxygen's look starts from a few defaults every element inherits: div
		// blocks and section wraps are flex columns aligned to the start.
		$model['classes']['.oxy-section'] = array( 'base' => array( 'paddingTop' => '75', 'paddingBottom' => '75', 'paddingLeft' => '20', 'paddingRight' => '20' ) );
		$model['classes']['.oxy-wrap']    = array( 'base' => array( 'width' => '100%', 'maxWidth' => '1120', 'marginLeft' => 'auto', 'marginRight' => 'auto', 'display' => 'flex', 'flexDirection' => 'column', 'alignItems' => 'flex-start' ) );
		$model['classes']['.oxy-div']     = array( 'base' => array( 'display' => 'flex', 'flexDirection' => 'column', 'alignItems' => 'flex-start' ) );
		$model['classes']['.oxy-columns'] = array( 'base' => array( 'display' => 'flex', 'flexWrap' => 'wrap', 'width' => '100%' ) );
		$model['classes']['.oxy-column']  = array( 'base' => array( 'display' => 'flex', 'flexDirection' => 'column', 'alignItems' => 'flex-start', 'paddingTop' => '20', 'paddingBottom' => '20', 'paddingLeft' => '20', 'paddingRight' => '20' ) );
		$model['classes']['.oxy-heading'] = array( 'base' => array() );
		$model['classes']['.oxy-text']    = array( 'base' => array() );
		$model['classes']['.oxy-link']    = array( 'base' => array() );
		$model['classes']['.oxy-button']  = array( 'base' => array( 'display' => 'inline-block', 'background' => '#1e73be', 'color' => '#ffffff', 'paddingTop' => '10', 'paddingBottom' => '10', 'paddingLeft' => '16', 'paddingRight' => '16', 'textDecoration' => 'none', 'borderRadius' => '3' ) );
		$model['classes']['.oxy-linkbox'] = array( 'base' => array( 'display' => 'flex', 'flexDirection' => 'column', 'alignItems' => 'flex-start', 'textDecoration' => 'none', 'color' => 'inherit' ) );
		$model['classes']['.oxy-image']   = array( 'base' => array() );

		foreach ( $nodes as $n ) {
			$c = isset( $n['__v6'] ) ? self::node_v6( $n['raw'], $model, $allow_inner ) : self::node( $n, $model, $allow_inner );
			if ( $c ) {
				$model['tree'][] = $c;
			}
		}
		// Global Oxygen classes used by the imported elements.
		self::import_classes( $model );
		if ( '' !== trim( self::$page_css ) ) {
			$model['page'] = array( 'css' => trim( self::$page_css ) );
		}
		// Drop the helper classes nothing ended up using.
		$used = array();
		self::walk( $model['tree'], function ( $n ) use ( &$used ) {
			foreach ( $n['classes'] as $c ) {
				$used[ $c ] = true;
			}
		} );
		foreach ( array_keys( $model['classes'] ) as $c ) {
			if ( ! isset( $used[ $c ] ) ) {
				unset( $model['classes'][ $c ] );
			}
		}
		return array(
			'model'   => self::objectify( $model ),
			'count'   => self::$count,
			'skipped' => array_values( array_unique( self::$skipped ) ),
		);
	}

	/**
	 * Read the Oxygen tree of a post as a list of
	 *   array( 'name' => 'ct_section', 'options' => array, 'content' => string, 'children' => array )
	 */
	private static function read_tree( $post_id ) {
		// Oxygen 6 / Breakdance first: a site upgraded from classic Oxygen can
		// still carry the old classic meta next to the current layout.
		$root = self::v6_root( $post_id );
		if ( null !== $root ) {
			$out = array();
			foreach ( (array) ( $root['children'] ?? array() ) as $child ) {
				$out[] = array( '__v6' => true, 'raw' => $child );
			}
			return $out;
		}
		$json = get_post_meta( $post_id, 'ct_builder_json', true );
		if ( is_string( $json ) && '' !== $json ) {
			$data = json_decode( $json, true );
			if ( ! is_array( $data ) ) {
				$data = json_decode( wp_unslash( $json ), true );
			}
			if ( is_array( $data ) ) {
				$kids = isset( $data['children'] ) ? $data['children'] : ( isset( $data[0] ) ? $data : array() );
				return self::from_json( $kids );
			}
		}
		$sc = get_post_meta( $post_id, 'ct_builder_shortcodes', true );
		if ( is_string( $sc ) && false !== strpos( $sc, '[ct_' ) ) {
			return self::parse_shortcodes( $sc );
		}
		return null;
	}

	/* ---------------------------------------------------- Oxygen 6 / Breakdance
	 * Format (Breakdance engine, which Oxygen 6 runs on with BREAKDANCE_MODE
	 * 'oxygen'): post meta `_oxygen_data` (Oxygen 6) / `_breakdance_data`
	 * holding {"tree_json_string": "<json>"}; the tree is {root:{id, data:{type,
	 * properties}, children}}. Text at content.content.text, tag at
	 * settings.advanced.tag (Breakdance Heading: content.content.tags), images at
	 * content.image {from, media{id,url,alt,sizes}, url, alt (a MODE), custom_alt}.
	 * Oxygen 6 elements carry no design of their own — they are styled by
	 * selectors (option oxygen_oxy_selectors_json_string), referenced by id in
	 * meta.classes, with properties keyed breakpoint first.
	 * ------------------------------------------------------------------------- */

	/** Breakpoint names in Oxygen 6 / Breakdance → Velox breakpoint keys. */
	private static $V6_BP = array(
		'breakpoint_base'             => 'base',
		'breakpoint_tablet_landscape' => 'xl',
		'breakpoint_tablet_portrait'  => 'lg',
		'breakpoint_phone_landscape'  => 'md',
		'breakpoint_phone_portrait'   => 'sm',
	);

	/** Oxygen 6 selectors (id → selector) and Breakdance classes (name → class). */
	private static $selectors = null;
	private static $bd_classes = null;
	/** Components / global blocks being inlined (recursion guard). */
	private static $block_stack = array();

	/** Decode a JSON option that may be stored encoded twice. */
	private static function json_option( $name ) {
		$v = get_option( $name );
		for ( $i = 0; $i < 3 && is_string( $v ); $i++ ) {
			$d = json_decode( $v, true );
			if ( null === $d ) {
				$d = json_decode( wp_unslash( $v ), true );
			}
			if ( null === $d ) {
				return array();
			}
			$v = $d;
		}
		return is_array( $v ) ? $v : array();
	}

	private static function load_selectors() {
		if ( null !== self::$selectors ) {
			return;
		}
		self::$selectors  = array();
		self::$bd_classes = array();
		foreach ( self::json_option( 'oxygen_oxy_selectors_json_string' ) as $sel ) {
			if ( is_array( $sel ) && isset( $sel['id'] ) ) {
				self::$selectors[ (string) $sel['id'] ] = $sel;
			}
		}
		foreach ( array( 'oxygen_breakdance_classes_json_string', 'breakdance_breakdance_classes_json_string' ) as $opt ) {
			foreach ( self::json_option( $opt ) as $cls ) {
				if ( is_array( $cls ) && ! empty( $cls['name'] ) ) {
					self::$bd_classes[ (string) $cls['name'] ] = $cls;
				}
			}
		}
	}

	/** The root node of an Oxygen 6 / Breakdance layout, or null. */
	private static function v6_root( $post_id ) {
		// Any other "…data" meta holding the same {"tree_json_string": …} structure
		// is accepted as well (prefixed installs).
		$keys = array( '_oxygen_data', '_breakdance_data' );
		foreach ( array_keys( (array) get_post_meta( (int) $post_id ) ) as $mk ) {
			if ( ! in_array( $mk, $keys, true ) && preg_match( '/^_.*data$/', $mk ) ) {
				$keys[] = $mk;
			}
		}
		foreach ( $keys as $key ) {
			$raw = get_post_meta( (int) $post_id, $key, true );
			if ( is_string( $raw ) && false === strpos( $raw, 'tree_json_string' ) && '_oxygen_data' !== $key && '_breakdance_data' !== $key ) {
				continue;
			}
			if ( is_array( $raw ) ) {
				$data = $raw;
			} elseif ( is_string( $raw ) && '' !== $raw ) {
				$data = json_decode( $raw, true );
				if ( ! is_array( $data ) ) {
					$data = json_decode( wp_unslash( $raw ), true );
				}
			} else {
				continue;
			}
			if ( ! is_array( $data ) ) {
				continue;
			}
			if ( isset( $data['tree_json_string'] ) && is_string( $data['tree_json_string'] ) ) {
				$tree = json_decode( $data['tree_json_string'], true );
				$data = is_array( $tree ) ? $tree : array();
			}
			$root = $data['root'] ?? ( isset( $data['children'] ) ? $data : null );
			if ( is_array( $root ) && ! empty( $root['children'] ) ) {
				return $root;
			}
		}
		return null;
	}

	/** Value at a dotted path, or null. */
	private static function at( $arr, $path ) {
		foreach ( explode( '.', $path ) as $k ) {
			if ( ! is_array( $arr ) || ! array_key_exists( $k, $arr ) ) {
				return null;
			}
			$arr = $arr[ $k ];
		}
		return $arr;
	}

	/** First string (or array, with $want_array) found under one of $keys, depth-first. */
	private static function v6_find( $props, $keys, $want_array = false ) {
		if ( ! is_array( $props ) ) {
			return null;
		}
		foreach ( $keys as $k ) {
			if ( array_key_exists( $k, $props ) ) {
				$v = $props[ $k ];
				if ( $want_array ? is_array( $v ) : ( is_string( $v ) || is_numeric( $v ) ) ) {
					return $v;
				}
			}
		}
		foreach ( $props as $k => $v ) {
			if ( is_array( $v ) && 'design' !== $k ) {
				$hit = self::v6_find( $v, $keys, $want_array );
				if ( null !== $hit ) {
					return $hit;
				}
			}
		}
		return null;
	}

	/** One Oxygen 6 / Breakdance element → Velox node. */
	private static function node_v6( $n, &$model, $allow_inner ) {
		if ( ! is_array( $n ) ) {
			return null;
		}
		self::load_selectors();
		$type  = (string) ( $n['data']['type'] ?? '' );
		$short = strtolower( preg_replace( '/[^a-z0-9]/i', '', substr( $type, (int) strrpos( $type, '\\' ) ) ) );
		$props = isset( $n['data']['properties'] ) && is_array( $n['data']['properties'] ) ? $n['data']['properties'] : array();
		$id    = self::$prefix . base_convert( (string) self::$count, 10, 36 );
		self::$count++;
		$kids = function ( $children = null ) use ( $n, &$model, $allow_inner ) {
			$out = array();
			foreach ( (array) ( null === $children ? ( $n['children'] ?? array() ) : $children ) as $c ) {
				$cn = self::node_v6( $c, $model, $allow_inner );
				if ( $cn ) {
					$out[] = $cn;
				}
			}
			return $out;
		};
		$tg = (string) ( self::at( $props, 'content.content.tags' ) ?? self::at( $props, 'settings.advanced.tag' ) ?? self::v6_find( $props, array( 'tags', 'tag', 'htmlTag' ) ) );
		$tag = preg_match( '/^(h[1-6]|p|div|span|section|header|footer|nav|article|aside|main|figure|ul|ol|li|blockquote)$/', $tg ) ? $tg : '';
		$txt = self::at( $props, 'content.content.text' );
		$txt = is_string( $txt ) ? $txt : (string) self::v6_find( $props, array( 'text', 'richText' ) );
		$node = null;

		switch ( $short ) {
			case 'section':
				$wrap = array( 'id' => $id . 'w', 'el' => 'Div', 'tag' => 'div', 'classes' => array( '.oxy-wrap' ), 'overrides' => array(), 'children' => $kids() );
				$node = array( 'id' => $id, 'el' => 'Section', 'tag' => $tag ? $tag : 'section', 'classes' => array( '.oxy-section' ), 'children' => array( $wrap ) );
				break;
			case 'container':
			case 'containershortcode':
			case 'div':
			case 'column':
			case 'columns':
			case 'containerlink':
			case 'wrapperlink':
			case 'linkwrapper':
				$is_link = in_array( $short, array( 'containerlink', 'wrapperlink', 'linkwrapper' ), true );
				$cls  = 'columns' === $short ? '.oxy-columns' : ( 'column' === $short ? '.oxy-column' : ( $is_link ? '.oxy-linkbox' : '.oxy-div' ) );
				$node = array( 'id' => $id, 'el' => 'Div', 'tag' => $is_link ? 'a' : ( $tag ? $tag : 'div' ), 'classes' => array( $cls ), 'children' => $kids() );
				break;
			case 'heading':
				// Breakdance's heading defaults to h1.
				$node = array( 'id' => $id, 'el' => 'Heading', 'tag' => $tag && 'h' === $tag[0] ? $tag : 'h1', 'classes' => array( '.oxy-heading' ), 'children' => array() );
				$model['content'][ $id ] = $txt;
				break;
			case 'text':
			case 'richtext':
			case 'paragraph':
				// Oxygen 6 has no heading element: a Text set to h1–h6 is one. Both
				// builders default a Text to <div>.
				$is_h = $tag && 'h' === $tag[0];
				$node = array( 'id' => $id, 'el' => $is_h ? 'Heading' : 'Text', 'tag' => $tag ? $tag : 'div', 'classes' => array( $is_h ? '.oxy-heading' : '.oxy-text' ), 'children' => array() );
				$model['content'][ $id ] = $txt;
				break;
			case 'textlink':
			case 'button':
				$node = array( 'id' => $id, 'el' => 'Button', 'tag' => 'a', 'classes' => array( 'button' === $short ? '.oxy-button' : '.oxy-link' ), 'children' => array() );
				$model['content'][ $id ] = '' !== $txt ? $txt : (string) self::v6_find( $props, array( 'label', 'title' ) );
				break;
			case 'image':
			case 'image2':
			case 'fimage':
				$node = array( 'id' => $id, 'el' => 'Image', 'tag' => 'div', 'classes' => array( '.oxy-image' ), 'children' => array() );
				self::v6_image( $props, $node, $model, $id );
				break;
			case 'video':
			case 'youtube':
			case 'html5video':
			case 'oembed':
				$node = array( 'id' => $id, 'el' => 'Video', 'tag' => 'div', 'classes' => array(), 'children' => array() );
				$vid  = self::v6_find( $props, array( 'video' ), true );
				$url  = is_array( $vid ) ? (string) ( $vid['url'] ?? '' ) : (string) self::v6_find( $props, array( 'video_file_url', 'url', 'src', 'embed_url' ) );
				$node['settings'] = array( 'url' => esc_url_raw( $url ) );
				break;
			case 'icon':
			case 'svgicon':
				$node = array( 'id' => $id, 'el' => 'Icon', 'tag' => 'span', 'classes' => array( '.icon' ), 'children' => array(), 'settings' => array( 'icon' => 'star' ) );
				$model['classes']['.icon'] = $model['classes']['.icon'] ?? array( 'base' => array() );
				self::$skipped[] = $type . ' (' . __( 'replaced by a star icon', 'velox' ) . ')';
				break;
			case 'htmlcode':
			case 'code':
				$html = (string) ( self::at( $props, 'content.content.html_code' ) ?? self::v6_find( $props, array( 'html_code', 'html', 'code' ) ) );
				$html = preg_replace( '/<\?(php|=)?.*?(\?>|$)/s', '', $html );
				if ( '' === trim( $html ) ) {
					return null;
				}
				$node = array( 'id' => $id, 'el' => 'Text', 'tag' => 'div', 'classes' => array( '.oxy-text' ), 'children' => array() );
				$model['content'][ $id ] = $html;
				break;
			case 'csscode':
				$css = (string) ( self::at( $props, 'content.content.css_code' ) ?? self::v6_find( $props, array( 'css_code', 'css', 'code' ) ) );
				if ( '' !== trim( $css ) ) {
					self::$page_css .= "\n" . $css;
				}
				return null;
			case 'phpcode':
			case 'codeblock':
			case 'javascriptcode':
				self::$skipped[] = $type;
				return null;
			case 'shortcode':
				$sc = (string) self::v6_find( $props, array( 'shortcode', 'code', 'text' ) );
				if ( '' === trim( $sc ) ) {
					return null;
				}
				$node = array( 'id' => $id, 'el' => 'Text', 'tag' => 'div', 'classes' => array( '.oxy-text' ), 'children' => array() );
				$model['content'][ $id ] = $sc;
				break;
			case 'component':
			case 'globalblock':
				// A component / global block is another post with its own tree:
				// bring its elements in place.
				$ref = self::at( $props, 'content.content.block.componentId' ) ?? self::at( $props, 'content.content.block' );
				$bid = is_array( $ref ) ? (int) ( $ref['componentId'] ?? ( $ref['id'] ?? 0 ) ) : (int) $ref;
				$broot = ( $bid && ! isset( self::$block_stack[ $bid ] ) && count( self::$block_stack ) < 5 ) ? self::v6_root( $bid ) : null;
				if ( ! $broot ) {
					self::$skipped[] = $type;
					return null;
				}
				self::$block_stack[ $bid ] = true;
				$node = array( 'id' => $id, 'el' => 'Div', 'tag' => 'div', 'classes' => array( '.oxy-div' ), 'children' => $kids( $broot['children'] ?? array() ) );
				unset( self::$block_stack[ $bid ] );
				break;
			case 'templatecontentarea':
			case 'postcontent':
			case 'innercontent':
				if ( ! $allow_inner ) {
					return null;
				}
				$node = array( 'id' => $id, 'el' => 'InnerContent', 'tag' => 'div', 'classes' => array( '.inner-content' ), 'children' => array() );
				$model['classes']['.inner-content'] = array( 'base' => array() );
				break;
			default:
				self::$skipped[] = $type;
				if ( ! empty( $n['children'] ) ) {
					$node = array( 'id' => $id, 'el' => 'Div', 'tag' => 'div', 'classes' => array( '.oxy-div' ), 'children' => $kids() );
				} elseif ( '' !== trim( wp_strip_all_tags( $txt ) ) ) {
					$node = array( 'id' => $id, 'el' => 'Text', 'tag' => 'div', 'classes' => array( '.oxy-text' ), 'children' => array() );
					$model['content'][ $id ] = $txt;
				} else {
					return null;
				}
		}

		// Breakdance elements carry their own design; Oxygen 6 elements don't.
		$node['overrides'] = self::v6_styles( $props['design'] ?? array() );
		if ( 'a' === $node['tag'] ) {
			$link = self::at( $props, 'content.content.link' );
			$link = is_array( $link ) ? $link : self::v6_find( $props, array( 'link' ), true );
			$url  = is_array( $link ) ? (string) ( $link['url'] ?? '' ) : (string) ( self::at( $props, 'content.content.url' ) ?? self::v6_find( $props, array( 'url', 'href' ) ) );
			$node['href'] = '' !== $url ? esc_url_raw( $url ) : '#';
			$blank = ( is_array( $link ) && ( ! empty( $link['openInNewTab'] ) || '_blank' === ( $link['target'] ?? '' ) ) ) || ! empty( self::at( $props, 'content.content.open_in_new_tab' ) );
			if ( $blank ) {
				$node['target'] = '_blank';
			}
		}
		// Oxygen 6 selectors (by id) and Breakdance classes (by name), with styles.
		foreach ( (array) ( self::at( $props, 'meta.classes' ) ?? array() ) as $sid ) {
			$sel = self::$selectors[ (string) ( is_array( $sid ) ? ( $sid['id'] ?? '' ) : $sid ) ] ?? null;
			if ( $sel ) {
				self::add_selector_class( $node, $model, $sel );
			}
		}
		foreach ( (array) ( self::at( $props, 'settings.advanced.classes' ) ?? array() ) as $cname ) {
			$cname = (string) ( is_array( $cname ) ? ( $cname['name'] ?? '' ) : $cname );
			$sel   = self::$bd_classes[ $cname ] ?? array( 'name' => $cname );
			self::add_selector_class( $node, $model, $sel );
		}
		if ( empty( $node['classes'] ) ) {
			$node['classes'] = array( '.oxy-div' );
		}
		return $node;
	}

	/** Image source and alt text (Oxygen Image / Breakdance Image2 / legacy Image). */
	private static function v6_image( $props, &$node, &$model, $id ) {
		$im    = self::at( $props, 'content.image' );
		$media = is_array( $im ) && is_array( $im['media'] ?? null ) ? $im['media'] : null;
		if ( ! $media ) {
			// Legacy Breakdance Image: content.content.image {id, url, alt}.
			$legacy = self::at( $props, 'content.content.image' );
			$media  = is_array( $legacy ) ? $legacy : null;
		}
		$from = is_array( $im ) ? (string) ( $im['from'] ?? '' ) : '';
		$att  = $media ? (int) ( $media['id'] ?? 0 ) : 0;
		$src  = ( 'url' === $from && is_array( $im ) ) ? (string) ( $im['url'] ?? '' ) : ( $media ? (string) ( $media['url'] ?? ( $media['sizes']['full']['url'] ?? '' ) ) : '' );
		if ( '' === $src && is_array( $im ) && ! empty( $im['url'] ) ) {
			$src = (string) $im['url'];
		}
		if ( $att && wp_attachment_is_image( $att ) && 'url' !== $from ) {
			$node['att'] = $att;
			$u = wp_get_attachment_image_url( $att, 'large' );
			if ( $u ) {
				$src = $u;
			}
		}
		if ( '' !== $src ) {
			$model['content'][ $id ] = esc_url_raw( $src );
		}
		// content.image.alt is a MODE (from_media_library / custom / decorative),
		// not the text — the text is in custom_alt.
		$mode = is_array( $im ) ? (string) ( $im['alt'] ?? '' ) : '';
		$alt  = null;
		if ( 'custom' === $mode ) {
			$alt = (string) ( $im['custom_alt'] ?? '' );
		} elseif ( 'decorative' === $mode ) {
			$alt = '';
		} elseif ( 'url' === $from && is_array( $im ) && 'custom' === ( $im['alt_when_from_url'] ?? '' ) ) {
			$alt = (string) ( $im['custom_alt_when_from_url'] ?? '' );
		} elseif ( $media && ! empty( $media['alt'] ) && is_string( $media['alt'] ) ) {
			$alt = (string) $media['alt'];
		}
		$legacy_alt = self::at( $props, 'content.content.alt' );
		if ( null === $alt && is_array( $legacy_alt ) ) {
			$alt = (string) ( $legacy_alt['custom_alt'] ?? ( $legacy_alt['alt'] ?? '' ) );
		}
		if ( null !== $alt ) {
			$node['alt']       = sanitize_text_field( $alt );
			$node['altCustom'] = true;
		}
	}

	/** Bring one Oxygen selector / Breakdance class over, with its styles. */
	private static function add_selector_class( &$node, &$model, $sel ) {
		$name = sanitize_html_class( (string) ( $sel['name'] ?? '' ) );
		if ( '' === $name ) {
			return;
		}
		$key = '.' . $name;
		if ( ! in_array( $key, $node['classes'], true ) ) {
			$node['classes'][] = $key;
		}
		if ( isset( $model['classes'][ $key ] ) && ! empty( $model['classes'][ $key ]['base'] ) ) {
			return;
		}
		$rules = self::selector_rules( $sel['properties'] ?? array(), '' );
		foreach ( (array) ( $sel['children'] ?? array() ) as $child ) {
			// "&:hover" style children become :hover rules.
			if ( is_array( $child ) && preg_match( '/^&:(hover|focus|active)$/', (string) ( $child['name'] ?? '' ), $m ) ) {
				foreach ( self::selector_rules( $child['properties'] ?? array(), $m[1] ) as $k => $r ) {
					$rules[ $k ] = array_merge( $rules[ $k ] ?? array(), $r );
				}
			}
		}
		$model['classes'][ $key ] = $rules ? $rules : array( 'base' => array() );
	}

	/**
	 * Selector properties are breakpoint FIRST: properties.breakpoint_base.
	 * typography.color — the reverse of element design. Returns Velox rules.
	 */
	private static function selector_rules( $props, $state ) {
		$out = array();
		foreach ( (array) $props as $bpk => $groups ) {
			if ( ! isset( self::$V6_BP[ $bpk ] ) || ! is_array( $groups ) ) {
				continue;
			}
			$bp   = self::$V6_BP[ $bpk ];
			$key  = '' === $state ? $bp : $bp . ':' . $state;
			$r    = array();
			$val  = function ( $p ) use ( $groups ) {
				return self::v6_value( self::at( $groups, $p ) );
			};
			$map = array(
				'layout.display' => 'display', 'layout.flex_direction' => 'flexDirection',
				'layout.flex_align.primary_axis' => 'justifyContent', 'layout.flex_align.cross_axis' => 'alignItems',
				'layout.gap.column' => 'gap', 'layout.gap.row' => 'gap',
				'size.width' => 'width', 'size.max_width' => 'maxWidth', 'size.min_width' => 'minWidth',
				'size.height' => 'height', 'size.min_height' => 'minHeight', 'size.max_height' => 'maxHeight',
				'typography.color' => 'color', 'typography.font_size' => 'fontSize', 'typography.font_weight' => 'fontWeight',
				'typography.line_height' => 'lineHeight', 'typography.letter_spacing' => 'letterSpacing',
				'typography.text_align' => 'textAlign', 'typography.font_family' => 'fontFamily',
				'background.background_color' => 'background', 'borders.border_radius.all' => 'borderRadius',
			);
			foreach ( $map as $path => $prop ) {
				if ( isset( $r[ $prop ] ) ) {
					continue;
				}
				$v = $val( $path );
				if ( '' !== $v ) {
					$r[ $prop ] = 'fontFamily' === $prop ? $v : $v;
				}
			}
			foreach ( array( 'padding', 'margin' ) as $box ) {
				foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
					$v = $val( 'spacing.spacing.' . $box . '.' . $side );
					if ( '' !== $v ) {
						$r[ $box . ucfirst( $side ) ] = $v;
					}
				}
			}
			if ( $r ) {
				$out[ $key ] = $r;
			}
		}
		return $out;
	}

	/**
	 * Breakdance element design → Velox rules per breakpoint. Values sit either
	 * under a breakpoint key (spacing.padding.breakpoint_base.{top…}) or plain
	 * (background.color) — plain values count for every screen.
	 */
	private static function v6_styles( $design ) {
		$out  = array();
		$walk = function ( $node, $path ) use ( &$walk, &$out ) {
			if ( ! is_array( $node ) ) {
				if ( $path && ( is_string( $node ) || is_numeric( $node ) ) ) {
					self::v6_put( $out, 'base', $path, $node );
				}
				return;
			}
			$bpkeys = array_intersect( array_keys( $node ), array_keys( self::$V6_BP ) );
			if ( $bpkeys ) {
				foreach ( $bpkeys as $bk ) {
					self::v6_put( $out, self::$V6_BP[ $bk ], $path, $node[ $bk ] );
				}
				return;
			}
			// A {number, unit, style} value with no breakpoint: base.
			if ( isset( $node['style'] ) || isset( $node['number'] ) ) {
				self::v6_put( $out, 'base', $path, $node );
				return;
			}
			foreach ( $node as $k => $v ) {
				$walk( $v, array_merge( $path, array( (string) $k ) ) );
			}
		};
		$walk( $design, array() );
		return $out;
	}

	/** Store one design value under its Velox property name. */
	private static function v6_put( &$out, $bp, $path, $val ) {
		$last = (string) end( $path );
		// Hover variants (color_hover …) belong to :hover.
		if ( '_hover' === substr( $last, -6 ) ) {
			$last = substr( $last, 0, -6 );
			$bp  .= ':hover';
		}
		$name = strtolower( preg_replace( '/[^a-z]/i', '', $last ) );
		$all  = strtolower( implode( '.', $path ) );
		$map  = array(
			'fontsize' => 'fontSize', 'size' => 'fontSize', 'fontweight' => 'fontWeight', 'weight' => 'fontWeight',
			'lineheight' => 'lineHeight', 'letterspacing' => 'letterSpacing', 'textalign' => 'textAlign', 'align' => 'textAlign',
			'width' => 'width', 'maxwidth' => 'maxWidth', 'minwidth' => 'minWidth', 'height' => 'height', 'minheight' => 'minHeight',
			'gap' => 'gap', 'radius' => 'borderRadius', 'borderradius' => 'borderRadius', 'color' => 'color',
			'textcolor' => 'color', 'backgroundcolor' => 'background',
			'margintop' => 'marginTop', 'marginbottom' => 'marginBottom', 'marginleft' => 'marginLeft', 'marginright' => 'marginRight',
			'paddingtop' => 'paddingTop', 'paddingbottom' => 'paddingBottom', 'paddingleft' => 'paddingLeft', 'paddingright' => 'paddingRight',
		);
		if ( in_array( $name, array( 'padding', 'margin' ), true ) && is_array( $val ) ) {
			foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
				if ( isset( $val[ $side ] ) ) {
					$v = self::v6_value( $val[ $side ] );
					if ( '' !== $v ) {
						$out[ $bp ][ $name . ucfirst( $side ) ] = $v;
					}
				}
			}
			return;
		}
		if ( 'color' === $name && false !== strpos( $all, 'background' ) ) {
			$name = 'backgroundcolor';
		}
		if ( ! isset( $map[ $name ] ) ) {
			return;
		}
		$v = self::v6_value( $val );
		if ( '' !== $v ) {
			$out[ $bp ][ $map[ $name ] ] = $v;
		}
	}

	/** A Breakdance value: a plain string, or {number, unit, style}. */
	private static function v6_value( $v ) {
		if ( is_string( $v ) || is_numeric( $v ) ) {
			return trim( (string) $v );
		}
		if ( is_array( $v ) ) {
			if ( isset( $v['style'] ) && is_scalar( $v['style'] ) && '' !== (string) $v['style'] ) {
				return trim( (string) $v['style'] );
			}
			if ( isset( $v['number'] ) && is_numeric( $v['number'] ) ) {
				$u = isset( $v['unit'] ) ? (string) $v['unit'] : 'px';
				return $v['number'] . ( '' === $u ? 'px' : $u );
			}
		}
		return '';
	}

	private static function from_json( $kids ) {
		$out = array();
		foreach ( (array) $kids as $k ) {
			if ( ! is_array( $k ) || empty( $k['name'] ) ) {
				continue;
			}
			$opt = isset( $k['options'] ) && is_array( $k['options'] ) ? $k['options'] : array();
			$out[] = array(
				'name'     => (string) $k['name'],
				'options'  => $opt,
				'content'  => isset( $opt['ct_content'] ) ? (string) $opt['ct_content'] : '',
				'children' => self::from_json( isset( $k['children'] ) ? $k['children'] : array() ),
			);
		}
		return $out;
	}

	/**
	 * Oxygen nests shortcodes of the same name (a div block in a div block), which
	 * WordPress's own shortcode parser cannot do, so this walks the text with a
	 * stack. Attribute values are single-quoted and may contain "]".
	 */
	public static function parse_shortcodes( $text ) {
		$root  = array( 'name' => 'root', 'options' => array(), 'content' => '', 'children' => array() );
		$stack = array( &$root );
		$len   = strlen( $text );
		$i     = 0;
		$buf   = '';
		while ( $i < $len ) {
			$ch = $text[ $i ];
			if ( '[' === $ch && preg_match( '/\G\[(\/?)((?:ct|oxy)_[a-z0-9_]+)/A', $text, $m, 0, $i ) ) {
				$top = count( $stack ) - 1;
				$stack[ $top ]['content'] .= $buf;
				$buf = '';
				if ( '/' === $m[1] ) {
					// Close: pop back to the matching open element.
					$name = $m[2];
					$i   += strlen( $m[0] );
					$end  = strpos( $text, ']', $i );
					$i    = false === $end ? $len : $end + 1;
					for ( $s = count( $stack ) - 1; $s > 0; $s-- ) {
						if ( $stack[ $s ]['name'] === $name ) {
							array_splice( $stack, $s );
							break;
						}
					}
					continue;
				}
				// Open: read attributes up to the "]" outside quotes.
				$name = $m[2];
				$j    = $i + strlen( $m[0] );
				$q    = '';
				$attr = '';
				while ( $j < $len ) {
					$c = $text[ $j ];
					if ( '' !== $q ) {
						if ( '\\' === $c && $j + 1 < $len ) {
							$attr .= $c . $text[ $j + 1 ];
							$j    += 2;
							continue;
						}
						if ( $c === $q ) {
							$q = '';
						}
					} elseif ( "'" === $c || '"' === $c ) {
						$q = $c;
					} elseif ( ']' === $c ) {
						break;
					}
					$attr .= $c;
					$j++;
				}
				$i       = $j + 1;
				$options = array();
				if ( preg_match( "/ct_options='(.*?)'(?=\s+\w+=|\s*\/?$)/s", $attr, $om ) || preg_match( '/ct_options="(.*?)"(?=\s+\w+=|\s*\/?$)/s', $attr, $om ) ) {
					$dec = json_decode( $om[1], true );
					if ( ! is_array( $dec ) ) {
						$dec = json_decode( stripslashes( $om[1] ), true );
					}
					if ( is_array( $dec ) ) {
						$options = $dec;
					}
				}
				$el    = array( 'name' => preg_replace( '/_\d+$/', '', $name ), 'tag_name' => $name, 'options' => $options, 'content' => '', 'children' => array() );
				$top   = count( $stack ) - 1;
				$stack[ $top ]['children'][] = $el;
				$last  = count( $stack[ $top ]['children'] ) - 1;
				$stack[] = &$stack[ $top ]['children'][ $last ];
				// The stack holds references; the matching close ends it. Every
				// Oxygen element is written with a closing tag.
				$stack[ count( $stack ) - 1 ]['name'] = $name;
				continue;
			}
			$buf .= $ch;
			$i++;
		}
		$fix = function ( $nodes ) use ( &$fix ) {
			$out = array();
			foreach ( $nodes as $n ) {
				$n['name']     = preg_replace( '/_\d+$/', '', $n['name'] );
				$n['children'] = $fix( $n['children'] );
				if ( '' === trim( $n['content'] ) && isset( $n['options']['ct_content'] ) ) {
					$n['content'] = (string) $n['options']['ct_content'];
				}
				$out[] = $n;
			}
			return $out;
		};
		return $fix( $root['children'] );
	}

	/* ---------------------------------------------------------- one element */

	private static function node( $n, &$model, $allow_inner ) {
		$name = preg_replace( '/^(ct|oxy)_/', '', $n['name'] );
		$o    = $n['options'];
		$kids = function ( $parent ) use ( $n, &$model, $allow_inner ) {
			$out = array();
			foreach ( $n['children'] as $c ) {
				if ( 'ct_span' === $c['name'] ) {
					continue; // folded into the parent's text below
				}
				$cn = self::node( $c, $model, $allow_inner );
				if ( $cn ) {
					$out[] = $cn;
				}
			}
			return $out;
		};
		$tg   = (string) self::opt( $o, 'tag' );
		$tag  = preg_match( '/^(h[1-6]|p|div|span|section|header|footer|nav|article|aside|main|figure|ul|ol|li|blockquote|a)$/', $tg ) ? $tg : '';
		$id   = self::$prefix . base_convert( (string) self::$count, 10, 36 );
		self::$count++;
		$node = null;

		switch ( $name ) {
			case 'section':
				$wrap = array( 'id' => $id . 'w', 'el' => 'Div', 'tag' => 'div', 'classes' => array( '.oxy-wrap' ), 'overrides' => array(), 'children' => $kids( null ) );
				$node = array( 'id' => $id, 'el' => 'Section', 'tag' => $tag ? $tag : 'section', 'classes' => array( '.oxy-section' ), 'children' => array( $wrap ) );
				// Section spacing and width live on the inner wrap in Oxygen.
				$sec = self::styles( $o, $model );
				foreach ( array_merge( array( 'base' ), array_values( self::$MEDIA ) ) as $key ) {
					foreach ( array( 'Top', 'Right', 'Bottom', 'Left' ) as $side ) {
						$ck = 'container-padding-' . strtolower( $side );
						$src = 'base' === $key ? ( $o['original'] ?? array() ) : self::media_original( $o, $key );
						if ( isset( $src[ $ck ] ) && '' !== $src[ $ck ] ) {
							$sec[ $key ][ 'padding' . $side ] = self::with_unit( $src, $ck );
						}
					}
				}
				$node['overrides'] = $sec;
				$sw = (string) self::opt( $o, 'section-width' );
				if ( 'full-width' === $sw ) {
					$node['children'][0]['overrides'] = array( 'base' => array( 'maxWidth' => 'none' ) );
				} elseif ( 'custom' === $sw && '' !== (string) self::opt( $o, 'custom-width' ) ) {
					$node['children'][0]['overrides'] = array( 'base' => array( 'maxWidth' => self::with_unit( $o['original'], 'custom-width' ) ) );
				}
				break;
			case 'div_block':
			case 'column':
			case 'new_columns':
			case 'link':
				$cls  = array( 'div_block' => '.oxy-div', 'column' => '.oxy-column', 'new_columns' => '.oxy-columns', 'link' => '.oxy-linkbox' );
				$node = array( 'id' => $id, 'el' => 'Div', 'tag' => 'link' === $name ? 'a' : ( $tag ? $tag : 'div' ), 'classes' => array( $cls[ $name ] ), 'children' => $kids( null ) );
				$node['overrides'] = self::styles( $o, $model );
				if ( 'column' === $name && isset( $o['original']['width'] ) ) {
					$node['overrides']['base']['width'] = self::with_unit( $o['original'], 'width', '%' );
				}
				break;
			case 'headline':
				$node = array( 'id' => $id, 'el' => 'Heading', 'tag' => $tag ? $tag : 'h1', 'classes' => array( '.oxy-heading' ), 'children' => array() );
				$model['content'][ $id ] = self::text( $n );
				$node['overrides'] = self::styles( $o, $model );
				break;
			case 'text_block':
			case 'rich_text':
				$node = array( 'id' => $id, 'el' => 'Text', 'tag' => $tag ? $tag : 'div', 'classes' => array( '.oxy-text' ), 'children' => array() );
				$model['content'][ $id ] = self::text( $n );
				$node['overrides'] = self::styles( $o, $model );
				break;
			case 'link_text':
			case 'link_button':
				$node = array( 'id' => $id, 'el' => 'Button', 'tag' => 'a', 'classes' => array( 'link_button' === $name ? '.oxy-button' : '.oxy-link' ), 'children' => array() );
				$model['content'][ $id ] = self::text( $n );
				$node['overrides'] = self::styles( $o, $model );
				break;
			case 'image':
				$node = array( 'id' => $id, 'el' => 'Image', 'tag' => 'div', 'classes' => array( '.oxy-image' ), 'children' => array() );
				$att  = (int) self::opt( $o, 'attachment_id' );
				$src  = (string) self::opt( $o, 'src' );
				if ( $att && wp_attachment_is_image( $att ) ) {
					$node['att'] = $att;
					$u = wp_get_attachment_image_url( $att, 'large' );
					if ( $u ) {
						$src = $u;
					}
				}
				if ( '' !== $src ) {
					$model['content'][ $id ] = esc_url_raw( $src );
				}
				if ( '' !== (string) self::opt( $o, 'alt' ) ) {
					$node['alt']       = sanitize_text_field( (string) self::opt( $o, 'alt' ) );
					$node['altCustom'] = true;
				}
				$node['overrides'] = self::styles( $o, $model );
				break;
			case 'video':
				$node = array( 'id' => $id, 'el' => 'Video', 'tag' => 'div', 'classes' => array(), 'children' => array() );
				$url  = self::opt( $o, 'src' );
				if ( '' === (string) $url ) {
					$url = self::opt( $o, 'embed_src' );
				}
				$node['settings']  = array( 'url' => esc_url_raw( (string) $url ) );
				$node['overrides'] = self::styles( $o, $model );
				break;
			case 'code_block':
				$orig = $o['original'] ?? array();
				$html = self::maybe_b64( $orig['code-php'] ?? ( $orig['code_php'] ?? '' ) );
				// PHP cannot run in a Velox text block; keep the plain markup around it.
				$html = preg_replace( '/<\?(php|=)?.*?(\?>|$)/s', '', $html );
				$css  = self::maybe_b64( $orig['code-css'] ?? ( $orig['code_css'] ?? '' ) );
				if ( '' !== trim( $css ) ) {
					self::$page_css .= "\n" . str_replace( '%%ELEMENT_ID%%', $id, $css );
				}
				if ( '' === trim( $html ) ) {
					return null;
				}
				$node = array( 'id' => $id, 'el' => 'Text', 'tag' => 'div', 'classes' => array( '.oxy-text' ), 'children' => array() );
				$model['content'][ $id ] = $html;
				$node['overrides'] = self::styles( $o, $model );
				break;
			case 'shortcode':
				$sc = self::opt( $o, 'full_shortcode' );
				if ( '' === trim( (string) $sc ) ) {
					return null;
				}
				$node = array( 'id' => $id, 'el' => 'Text', 'tag' => 'div', 'classes' => array( '.oxy-text' ), 'children' => array() );
				$model['content'][ $id ] = (string) $sc;
				break;
			case 'inner_content':
				if ( ! $allow_inner ) {
					return null;
				}
				$node = array( 'id' => $id, 'el' => 'InnerContent', 'tag' => 'div', 'classes' => array( '.inner-content' ), 'children' => array() );
				$model['classes']['.inner-content'] = array( 'base' => array() );
				break;
			case 'nav_menu':
				$node = array( 'id' => $id, 'el' => 'WP', 'wp' => 'menu', 'tag' => 'nav', 'classes' => array( '.wp-menu' ), 'children' => array() );
				$model['classes']['.wp-menu'] = array( 'base' => array() );
				if ( self::opt( $o, 'menu_id' ) ) {
					$node['settings'] = array( 'menu' => (string) (int) self::opt( $o, 'menu_id' ) );
				}
				break;
			case 'separator':
				return null;
			default:
				// Anything else (sliders, tabs, Woo elements, third-party add-ons):
				// keep its children if it has any, otherwise its text if it has
				// any, and report the type so the import can say what was left.
				self::$skipped[] = $n['name'];
				if ( ! empty( $n['children'] ) ) {
					$node = array( 'id' => $id, 'el' => 'Div', 'tag' => 'div', 'classes' => array( '.oxy-div' ), 'children' => $kids( null ) );
					$node['overrides'] = self::styles( $o, $model );
				} elseif ( '' !== trim( wp_strip_all_tags( self::text( $n ) ) ) ) {
					$node = array( 'id' => $id, 'el' => 'Text', 'tag' => 'div', 'classes' => array( '.oxy-text' ), 'children' => array() );
					$model['content'][ $id ] = self::text( $n );
				} else {
					return null;
				}
		}

		// Links: url + target on anything rendered as <a>.
		if ( 'a' === $node['tag'] ) {
			$url = (string) self::opt( $o, 'url' );
			$node['href'] = '' !== $url ? esc_url_raw( $url ) : '#';
			$tgt = self::opt( $o, 'target' );
			if ( '_blank' === $tgt ) {
				$node['target'] = '_blank';
			}
		}
		// Oxygen classes on this element.
		if ( ! empty( $o['classes'] ) && is_array( $o['classes'] ) ) {
			foreach ( $o['classes'] as $c ) {
				$c = sanitize_html_class( (string) $c );
				if ( '' !== $c ) {
					$node['classes'][] = '.' . $c;
					if ( ! isset( $model['classes'][ '.' . $c ] ) ) {
						$model['classes'][ '.' . $c ] = array( 'base' => array() );
					}
				}
			}
		}
		if ( ! isset( $node['overrides'] ) ) {
			$node['overrides'] = array();
		}
		// Every element needs at least one class the editor can style.
		if ( empty( $node['classes'] ) ) {
			$node['classes'] = array( '.oxy-div' );
		}
		// Element-level custom CSS written against Oxygen's own id selector.
		if ( ! empty( $o['original']['custom-css'] ) && ! empty( $o['selector'] ) ) {
			self::$page_css .= "\n" . str_replace( '#' . $o['selector'], '#' . $id, (string) $o['original']['custom-css'] );
		}
		return $node;
	}

	/** An element setting, wherever this Oxygen version keeps it. */
	private static function opt( $o, $k ) {
		if ( isset( $o['original'][ $k ] ) && '' !== $o['original'][ $k ] ) {
			return $o['original'][ $k ];
		}
		return isset( $o[ $k ] ) && ! is_array( $o[ $k ] ) ? $o[ $k ] : '';
	}

	/** Text of an element, with Oxygen's inline span placeholders filled in. */
	private static function text( $n ) {
		$t = (string) $n['content'];
		if ( '' === trim( $t ) && isset( $n['options']['ct_content'] ) ) {
			$t = (string) $n['options']['ct_content'];
		}
		foreach ( $n['children'] as $c ) {
			if ( 'ct_span' !== $c['name'] ) {
				continue;
			}
			$cid  = isset( $c['options']['ct_id'] ) ? (int) $c['options']['ct_id'] : 0;
			$span = '<span>' . self::text( $c ) . '</span>';
			$re   = '/<span id="ct-placeholder-' . $cid . '"><\/span>/';
			$t    = $cid && preg_match( $re, $t ) ? preg_replace( $re, $span, $t ) : $t . $span;
		}
		return $t;
	}

	/* ---------------------------------------------------------- styles */

	/** All styles of one element: base, media breakpoints and hover. */
	private static function styles( $o, &$model ) {
		$out  = array();
		$base = self::decls( isset( $o['original'] ) ? $o['original'] : array() );
		if ( $base ) {
			$out['base'] = $base;
		}
		if ( ! empty( $o['hover'] ) && is_array( $o['hover'] ) ) {
			$h = self::decls( $o['hover'] );
			if ( $h ) {
				$out['base:hover'] = $h;
			}
		}
		if ( ! empty( $o['media'] ) && is_array( $o['media'] ) ) {
			foreach ( self::$MEDIA as $ox => $bp ) {
				if ( empty( $o['media'][ $ox ]['original'] ) ) {
					continue;
				}
				$d = self::decls( $o['media'][ $ox ]['original'] );
				if ( $d ) {
					$out[ $bp ] = $d;
				}
			}
		}
		return $out;
	}

	private static function media_original( $o, $bp ) {
		$ox = array_search( $bp, self::$MEDIA, true );
		return ( $ox && isset( $o['media'][ $ox ]['original'] ) ) ? $o['media'][ $ox ]['original'] : array();
	}

	/** Oxygen option map → Velox rules (only properties Velox can emit). */
	private static function decls( $src ) {
		$out = array();
		if ( ! is_array( $src ) ) {
			return $out;
		}
		foreach ( $src as $k => $v ) {
			if ( ! is_string( $v ) && ! is_numeric( $v ) ) {
				continue;
			}
			$v = trim( (string) $v );
			if ( '' === $v || in_array( $k, self::$SKIP, true ) || '-unit' === substr( $k, -5 ) ) {
				continue;
			}
			if ( 0 === strpos( $k, 'container-padding' ) ) {
				continue; // sections only, handled by the caller
			}
			$key = self::$ALIAS[ $k ] ?? ( class_exists( 'Velox_Builder_Render' ) ? Velox_Builder_Render::model_prop_name( $k ) : '' );
			if ( 'background-image' === $k ) {
				$url = esc_url_raw( $v );
				if ( $url ) {
					$size = isset( $src['background-size'] ) ? (string) $src['background-size'] : 'cover';
					$pos  = isset( $src['background-position-left'], $src['background-position-top'] ) ? self::with_unit( $src, 'background-position-left', '%' ) . ' ' . self::with_unit( $src, 'background-position-top', '%' ) : 'center';
					$rep  = isset( $src['background-repeat'] ) ? (string) $src['background-repeat'] : 'no-repeat';
					$col  = isset( $src['background-color'] ) ? self::color( (string) $src['background-color'] ) . ' ' : '';
					$out['background'] = $col . 'url("' . $url . '") ' . $pos . ' / ' . ( 'manual' === $size ? 'auto' : $size ) . ' ' . $rep;
				}
				continue;
			}
			if ( 'background-color' === $k && isset( $out['background'] ) ) {
				continue;
			}
			if ( '' === $key ) {
				continue;
			}
			if ( in_array( $key, array( 'color', 'background', 'borderColor' ), true ) ) {
				$out[ $key ] = self::color( $v );
				continue;
			}
			$out[ $key ] = self::with_unit( $src, $k );
		}
		return $out;
	}

	/** "20" + "20-unit":"em" → "20em"; bare numbers stay bare (Velox adds px). */
	private static function with_unit( $src, $k, $default = '' ) {
		$v = trim( (string) $src[ $k ] );
		$u = isset( $src[ $k . '-unit' ] ) ? trim( (string) $src[ $k . '-unit' ] ) : $default;
		if ( preg_match( '/^-?\d+(\.\d+)?$/', $v ) && '' !== $u && 'px' !== $u && 'auto' !== $u ) {
			return $v . $u;
		}
		if ( 'auto' === $u ) {
			return 'auto';
		}
		return $v;
	}

	/** Resolve Oxygen global colours: "color(3)" → the stored value. */
	private static function color( $v ) {
		if ( ! preg_match( '/^color\((\d+)\)$/', $v, $m ) ) {
			return $v;
		}
		if ( null === self::$colors ) {
			self::$colors = array();
			$g = get_option( 'oxygen_vsb_global_colors' );
			if ( is_array( $g ) && ! empty( $g['colors'] ) ) {
				foreach ( $g['colors'] as $c ) {
					if ( isset( $c['id'], $c['value'] ) ) {
						self::$colors[ (int) $c['id'] ] = (string) $c['value'];
					}
				}
			}
		}
		return self::$colors[ (int) $m[1] ] ?? '';
	}

	/** Bring over the global Oxygen classes the imported elements use. */
	private static function import_classes( &$model ) {
		$all = get_option( 'ct_components_classes' );
		if ( ! is_array( $all ) ) {
			return;
		}
		foreach ( $model['classes'] as $cls => $rules ) {
			$name = ltrim( $cls, '.' );
			if ( 0 === strpos( $name, 'oxy-' ) || ! isset( $all[ $name ] ) || ! is_array( $all[ $name ] ) ) {
				continue;
			}
			$model['classes'][ $cls ] = self::styles( $all[ $name ], $model );
			if ( empty( $model['classes'][ $cls ] ) ) {
				$model['classes'][ $cls ] = array( 'base' => array() );
			}
		}
	}

	private static function maybe_b64( $v ) {
		$v = (string) $v;
		if ( '' === $v ) {
			return '';
		}
		if ( preg_match( '/^[A-Za-z0-9+\/=\r\n]+$/', $v ) ) {
			$d = base64_decode( $v, true );
			if ( false !== $d ) {
				return $d;
			}
		}
		return $v;
	}

	private static function walk( $nodes, $fn ) {
		foreach ( (array) $nodes as $n ) {
			$fn( $n );
			if ( ! empty( $n['children'] ) ) {
				self::walk( $n['children'], $fn );
			}
		}
	}

	/**
	 * Empty maps must reach the editor as {} — a PHP empty array encodes as [],
	 * and keys set on a JS array are silently dropped when it is saved.
	 */
	private static function objectify( $model ) {
		$fix_node = function ( $n ) use ( &$fix_node ) {
			$ov = array();
			foreach ( (array) ( $n['overrides'] ?? array() ) as $k => $r ) {
				$ov[ $k ] = (object) $r;
			}
			$n['overrides'] = (object) $ov;
			if ( isset( $n['settings'] ) ) {
				$n['settings'] = (object) $n['settings'];
			}
			$n['children'] = array_map( $fix_node, (array) $n['children'] );
			return $n;
		};
		$classes = array();
		foreach ( $model['classes'] as $c => $byKey ) {
			$m = array();
			foreach ( (array) $byKey as $k => $r ) {
				$m[ $k ] = (object) $r;
			}
			$classes[ $c ] = (object) $m;
		}
		$model['classes'] = (object) $classes;
		$model['content'] = (object) $model['content'];
		$model['tree']    = array_map( $fix_node, $model['tree'] );
		if ( isset( $model['page'] ) ) {
			$model['page'] = (object) $model['page'];
		}
		return $model;
	}

	/* ---------------------------------------------------------- AJAX */

	/** builder_oxygen_sources — list of posts that hold an Oxygen layout. */
	public static function ajax_sources() {
		wp_send_json_success( array( 'sources' => self::sources() ) );
	}

	/** builder_oxygen_import — convert one post's layout for the editor. */
	public static function ajax_import() {
		$post_id = isset( $_POST['post'] ) ? absint( $_POST['post'] ) : 0;
		$inner   = ! empty( $_POST['inner'] );
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'That page could not be loaded.', 'velox' ) ), 400 );
		}
		$res = self::convert( $post_id, $inner );
		if ( null === $res ) {
			wp_send_json_error( array( 'message' => __( 'This page has no Oxygen layout to import.', 'velox' ) ), 404 );
		}
		wp_send_json_success( $res );
	}
}
