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
		return is_string( $sc ) && false !== strpos( $sc, '[ct_' );
	}

	/** Every post that holds an Oxygen layout (pages, posts, Oxygen templates). */
	public static function sources() {
		global $wpdb;
		$rows = $wpdb->get_results(
			"SELECT p.ID, p.post_title, p.post_type FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID
			 WHERE m.meta_key IN ('ct_builder_json','ct_builder_shortcodes') AND m.meta_value <> ''
			   AND p.post_status IN ('publish','draft','private','future')
			 GROUP BY p.ID ORDER BY p.post_type, p.post_title LIMIT 300",
			ARRAY_A
		);
		$out = array();
		foreach ( (array) $rows as $r ) {
			$out[] = array(
				'id'    => (int) $r['ID'],
				'title' => '' !== $r['post_title'] ? $r['post_title'] : __( '(no title)', 'velox' ),
				'type'  => 'ct_template' === $r['post_type'] ? __( 'Oxygen template', 'velox' ) : $r['post_type'],
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
			$c = self::node( $n, $model, $allow_inner );
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
				$html = self::maybe_b64( $orig['code-php'] ?? '' );
				// PHP cannot run in a Velox text block; keep the plain markup around it.
				$html = preg_replace( '/<\?(php|=)?.*?(\?>|$)/s', '', $html );
				$css  = self::maybe_b64( $orig['code-css'] ?? '' );
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
