<?php
/**
 * Velox Builder — query loop.
 *
 * Any container can repeat its children once per item of a query: posts of
 * any public type, the page's own archive/search query, taxonomy terms or
 * users. Dynamic data inside the children ({post.title}, {term.name},
 * {member.name}, links to the item…) follows the current item.
 *
 * Data on the node: `loop` = settings (all strings, as the editor stores them):
 *   src      posts | main | terms | users
 *   type     post type (posts)            tax / terms   filter (posts) or taxonomy (terms)
 *   per      items per page (1–100)       offset        items to skip
 *   order    date | modified | title | menu_order | rand | comment_count | meta | meta_num
 *            (terms: name | count | id | slug; users: name | registered | posts)
 *   dir      desc | asc                   metaKey       for meta ordering
 *   author   '' | current | user id       exclude       '1' = leave out the current post
 *   hideEmpty / top (terms)              role (users)  hasPosts (users)
 *   pages    '' | numbers | prevnext      empty         text when nothing is found
 *
 * @package Velox
 */

defined( 'ABSPATH' ) || exit;

class Velox_Builder_Loop {

	/** Items being rendered, innermost last: array( type, obj, i ). */
	private static $stack = array();
	/** The post the editor preview is for ("current post" without a real request). */
	private static $ctx_post = 0;
	private static $css_done = false;

	const SOURCES  = array( 'posts', 'main', 'terms', 'users' );
	const MAX_PER  = 100;
	const MAX_DEPTH = 3;

	/* ------------------------------------------------------------ context */

	/** The innermost loop item of a type (post / term / user), or the page's own. */
	public static function current( $type ) {
		for ( $i = count( self::$stack ) - 1; $i >= 0; $i-- ) {
			if ( $type === self::$stack[ $i ]['type'] ) {
				return self::$stack[ $i ]['obj'];
			}
		}
		$q = function_exists( 'get_queried_object' ) ? get_queried_object() : null;
		if ( 'term' === $type && $q instanceof WP_Term ) {
			return $q;
		}
		if ( 'user' === $type && $q instanceof WP_User ) {
			return $q;
		}
		return null;
	}

	/** 1-based position in the innermost loop, 0 outside a loop. */
	public static function index() {
		$top = end( self::$stack );
		return $top ? (int) $top['i'] : 0;
	}

	public static function in_loop() {
		return ! empty( self::$stack );
	}

	/** The page's own post: the queried object, or the post the editor works on. */
	private static function page_post_id() {
		if ( self::$ctx_post ) {
			return self::$ctx_post;
		}
		$id = function_exists( 'get_queried_object_id' ) ? (int) get_queried_object_id() : 0;
		return ( $id && get_post( $id ) ) ? $id : 0;
	}

	/* ------------------------------------------------------------ settings */

	/** The loop settings with every value checked. Null when the node has no loop. */
	public static function config( $raw ) {
		if ( ! is_array( $raw ) || empty( $raw['src'] ) || ! in_array( $raw['src'], self::SOURCES, true ) ) {
			return null;
		}
		$s = function ( $k, $d = '' ) use ( $raw ) {
			return isset( $raw[ $k ] ) && is_scalar( $raw[ $k ] ) ? trim( (string) $raw[ $k ] ) : $d;
		};
		$per = (int) $s( 'per', '6' );
		return array(
			'src'       => $raw['src'],
			'type'      => sanitize_key( $s( 'type', 'post' ) ),
			'per'       => max( 1, min( self::MAX_PER, $per > 0 ? $per : 6 ) ),
			'offset'    => max( 0, min( 1000, (int) $s( 'offset', '0' ) ) ),
			'order'     => sanitize_key( $s( 'order', '' ) ),
			'dir'       => 'asc' === strtolower( $s( 'dir', 'desc' ) ) ? 'ASC' : 'DESC',
			'metaKey'   => sanitize_text_field( $s( 'metaKey' ) ),
			'tax'       => sanitize_key( $s( 'tax' ) ),
			'terms'     => sanitize_text_field( $s( 'terms' ) ),
			'author'    => sanitize_key( $s( 'author' ) ),
			'exclude'   => '' !== $s( 'exclude' ) && '0' !== $s( 'exclude' ),
			'hideEmpty' => '0' !== $s( 'hideEmpty', '1' ),
			'top'       => '' !== $s( 'top' ) && '0' !== $s( 'top' ),
			'role'      => sanitize_text_field( $s( 'role' ) ),
			'hasPosts'  => '' !== $s( 'hasPosts' ) && '0' !== $s( 'hasPosts' ),
			'pages'     => in_array( $s( 'pages' ), array( 'numbers', 'prevnext' ), true ) ? $s( 'pages' ) : '',
			'empty'     => sanitize_text_field( $s( 'empty' ) ),
		);
	}

	/** The URL parameter that holds this loop's page number (one per loop). */
	private static function page_key( $node ) {
		$id = preg_replace( '/[^a-z0-9]/', '', strtolower( (string) ( $node['id'] ?? '' ) ) );
		return 'vxp-' . ( '' !== $id ? substr( $id, 0, 24 ) : 'loop' );
	}

	/* ------------------------------------------------------------ queries */

	/**
	 * Run the query. Returns array( 'items' => [ [type, obj] ], 'total' => int, 'pages' => int, 'page' => int ).
	 * $limit caps the item count (editor preview).
	 */
	public static function fetch( $c, $node, $limit = 0, $page = 0 ) {
		$per  = $limit ? min( $limit, $c['per'] ) : $c['per'];
		$key  = self::page_key( $node );
		if ( ! $page ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- page number for a public list
			$page = $c['pages'] ? max( 1, absint( $_GET[ $key ] ?? 1 ) ) : 1;
		}
		$skip = $c['offset'] + ( $page - 1 ) * $per;
		$out  = array( 'items' => array(), 'total' => 0, 'pages' => 1, 'page' => $page, 'key' => $key );

		switch ( $c['src'] ) {
			case 'main':
				global $wp_query;
				// The editor has no archive to list: show the latest posts instead.
				if ( self::$ctx_post || ! ( $wp_query instanceof WP_Query ) || is_singular() ) {
					$c2        = $c;
					$c2['src'] = 'posts';
					$c2['type'] = 'post';
					return self::fetch( $c2, $node, $limit ? $limit : $c['per'], $page );
				}
				foreach ( $wp_query->posts as $p ) {
					$out['items'][] = array( 'post', $p );
				}
				$out['total'] = (int) $wp_query->found_posts;
				$out['pages'] = max( 1, (int) $wp_query->max_num_pages );
				$out['page']  = max( 1, (int) get_query_var( 'paged' ) );
				return $out;

			case 'posts':
				$type = $c['type'];
				$pto  = 'any' === $type ? true : get_post_type_object( $type );
				if ( ! $pto || ( true !== $pto && ! $pto->public ) ) {
					return $out;
				}
				$args = array(
					'post_type'           => 'any' === $type ? 'any' : $type,
					'post_status'         => 'publish',
					'posts_per_page'      => $per,
					'offset'              => $skip,
					'order'               => $c['dir'],
					'ignore_sticky_posts' => true,
					'has_password'        => false,
					'no_found_rows'       => '' === $c['pages'],
					'suppress_filters'    => false,
				);
				$orders = array( 'date' => 'date', 'modified' => 'modified', 'title' => 'title', 'menu_order' => 'menu_order', 'rand' => 'rand', 'comment_count' => 'comment_count' );
				if ( in_array( $c['order'], array( 'meta', 'meta_num' ), true ) && '' !== $c['metaKey'] ) {
					$args['meta_key'] = $c['metaKey']; // phpcs:ignore WordPress.DB.SlowDBQuery
					$args['orderby']  = 'meta_num' === $c['order'] ? 'meta_value_num' : 'meta_value';
				} else {
					$args['orderby'] = $orders[ $c['order'] ] ?? 'date';
				}
				if ( 'rand' === $args['orderby'] ) {
					// A cached page would show the same "random" set to everyone.
					self::no_cache();
				}
				$here = self::page_post_id();
				if ( $c['exclude'] && $here ) {
					$args['post__not_in'] = array( $here ); // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams
				}
				if ( 'current' === $c['author'] ) {
					$a = $here ? (int) get_post_field( 'post_author', $here ) : 0;
					$args['author'] = $a ? $a : -1;
				} elseif ( '' !== $c['author'] && ctype_digit( $c['author'] ) ) {
					$args['author'] = (int) $c['author'];
				}
				if ( '' !== $c['tax'] && taxonomy_exists( $c['tax'] ) && '' !== $c['terms'] ) {
					if ( 'current' === $c['terms'] ) {
						// The term of an outer term loop (each category with its posts),
						// the archive's term, or the terms of the post being viewed (related posts).
						$q   = self::current( 'term' );
						$ids = ( $q instanceof WP_Term && $q->taxonomy === $c['tax'] ) ? array( $q->term_id )
							: ( $here ? wp_get_post_terms( $here, $c['tax'], array( 'fields' => 'ids' ) ) : array() );
						$ids = is_wp_error( $ids ) ? array() : array_map( 'intval', $ids );
						if ( ! $ids ) {
							return $out;
						}
						$args['tax_query'] = array( array( 'taxonomy' => $c['tax'], 'field' => 'term_id', 'terms' => $ids ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
					} else {
						$list = array_values( array_filter( array_map( 'trim', explode( ',', $c['terms'] ) ), 'strlen' ) );
						$num  = count( array_filter( $list, 'ctype_digit' ) ) === count( $list );
						$args['tax_query'] = array( array( 'taxonomy' => $c['tax'], 'field' => $num ? 'term_id' : 'slug', 'terms' => $num ? array_map( 'intval', $list ) : array_map( 'sanitize_title', $list ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
					}
				}
				$args = apply_filters( 'velox_builder_loop_query_args', $args, $c, $node );
				$q    = new WP_Query( $args );
				foreach ( $q->posts as $p ) {
					$out['items'][] = array( 'post', $p );
				}
				$out['total'] = $q->found_posts ? max( 0, (int) $q->found_posts - $c['offset'] ) : count( $q->posts );
				$out['pages'] = max( 1, (int) ceil( $out['total'] / $per ) );
				return $out;

			case 'terms':
				$tax = $c['tax'] ? $c['tax'] : 'category';
				$tx  = get_taxonomy( $tax );
				if ( ! $tx || ! $tx->public ) {
					return $out;
				}
				$orders = array( 'name' => 'name', 'count' => 'count', 'id' => 'term_id', 'slug' => 'slug' );
				$args   = array(
					'taxonomy'   => $tax,
					'hide_empty' => $c['hideEmpty'],
					'number'     => $per,
					'offset'     => $skip,
					'orderby'    => $orders[ $c['order'] ] ?? 'name',
					'order'      => $c['dir'],
				);
				if ( $c['top'] ) {
					$args['parent'] = 0;
				}
				$terms = get_terms( $args );
				if ( is_wp_error( $terms ) ) {
					return $out;
				}
				foreach ( $terms as $t ) {
					$out['items'][] = array( 'term', $t );
				}
				if ( $c['pages'] ) {
					unset( $args['number'], $args['offset'] );
					$args['fields'] = 'count';
					$n              = get_terms( $args );
					$out['total']   = is_wp_error( $n ) ? count( $terms ) : max( 0, (int) $n - $c['offset'] );
					$out['pages']   = max( 1, (int) ceil( $out['total'] / $per ) );
				} else {
					$out['total'] = count( $terms );
				}
				return $out;

			case 'users':
				$orders = array( 'name' => 'display_name', 'registered' => 'registered', 'posts' => 'post_count' );
				$args   = array(
					'number'      => $per,
					'offset'      => $skip,
					'orderby'     => $orders[ $c['order'] ] ?? 'display_name',
					'order'       => $c['dir'],
					'count_total' => '' !== $c['pages'],
					'fields'      => 'all',
				);
				$roles = array_values( array_filter( array_map( 'sanitize_key', explode( ',', $c['role'] ) ) ) );
				if ( $roles ) {
					$args['role__in'] = $roles;
				}
				if ( $c['hasPosts'] ) {
					$args['has_published_posts'] = true;
				}
				$uq = new WP_User_Query( $args );
				foreach ( (array) $uq->get_results() as $u ) {
					$out['items'][] = array( 'user', $u );
				}
				$out['total'] = $c['pages'] ? max( 0, (int) $uq->get_total() - $c['offset'] ) : count( $out['items'] );
				$out['pages'] = max( 1, (int) ceil( $out['total'] / $per ) );
				return $out;
		}
		return $out;
	}

	/** Put an item in place: the global post for posts, the stack for all. */
	private static function enter( $item, $i ) {
		if ( 'post' === $item[0] ) {
			$GLOBALS['post'] = $item[1]; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
			setup_postdata( $item[1] );
		}
		self::$stack[] = array( 'type' => $item[0], 'obj' => $item[1], 'i' => $i );
	}

	private static function leave() {
		array_pop( self::$stack );
	}

	/* ------------------------------------------------------------ rendering */

	/**
	 * Render a looping container's children once per item.
	 * Returns array( 'inner' => items HTML, 'after' => pagination HTML ).
	 */
	public static function render( $node, $doc, $css_only = false ) {
		$c    = self::config( $node['loop'] ?? null );
		$kids = $node['children'] ?? array();
		// CSS only (stylesheet build), or nested too deep: one copy is enough.
		if ( ! $c || $css_only || count( self::$stack ) >= self::MAX_DEPTH ) {
			if ( $c && $c['pages'] ) {
				self::pages_css();
			}
			return array( 'inner' => Velox_Builder_Render::render_tree( $kids, $doc ), 'after' => '' );
		}
		$res = self::fetch( $c, $node );
		if ( ! $res['items'] ) {
			$msg = '' !== $c['empty'] ? $c['empty'] : '';
			return array( 'inner' => '' !== $msg ? '<p class="vx-loop-empty">' . esc_html( $msg ) . '</p>' : '', 'after' => '' );
		}
		$saved = $GLOBALS['post'] ?? null;
		$html  = '';
		$i     = 0;
		foreach ( $res['items'] as $item ) {
			self::enter( $item, ++$i );
			$html .= Velox_Builder_Render::render_tree( $kids, $doc );
			self::leave();
		}
		// Back to the page's own post for everything after the loop.
		$GLOBALS['post'] = $saved; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
		if ( $saved instanceof WP_Post ) {
			setup_postdata( $saved );
		} else {
			wp_reset_postdata();
		}
		return array( 'inner' => $html, 'after' => self::pagination( $c, $res ) );
	}

	/** Page links under the loop. Each loop has its own URL parameter. */
	private static function pagination( $c, $res ) {
		if ( ! $c['pages'] || $res['pages'] < 2 ) {
			return '';
		}
		self::pages_css();
		$main = 'main' === $c['src'] && ! self::$ctx_post && ! is_singular();
		$cur  = $res['page'];
		$link = function ( $n ) use ( $main, $res ) {
			return $main ? get_pagenum_link( $n ) : ( $n > 1 ? add_query_arg( $res['key'], $n ) : remove_query_arg( $res['key'] ) );
		};
		$label = esc_attr__( 'More pages', 'velox' );
		if ( 'prevnext' === $c['pages'] ) {
			$out = '';
			if ( $cur > 1 ) {
				$out .= '<a class="vx-pages-prev" href="' . esc_url( $link( $cur - 1 ) ) . '" rel="prev">' . esc_html__( '← Newer', 'velox' ) . '</a>';
			}
			if ( $cur < $res['pages'] ) {
				$out .= '<a class="vx-pages-next" href="' . esc_url( $link( $cur + 1 ) ) . '" rel="next">' . esc_html__( 'Older →', 'velox' ) . '</a>';
			}
			return '<nav class="vx-pages is-prevnext" aria-label="' . $label . '">' . $out . '</nav>';
		}
		$links = paginate_links( array(
			'base'      => $main ? str_replace( 999999999, '%#%', esc_url( get_pagenum_link( 999999999 ) ) ) : esc_url( add_query_arg( $res['key'], '%#%' ) ),
			'format'    => '',
			'current'   => $cur,
			'total'     => $res['pages'],
			'type'      => 'list',
			'prev_text' => '←',
			'next_text' => '→',
		) );
		return $links ? '<nav class="vx-pages" aria-label="' . $label . '">' . $links . '</nav>' : '';
	}

	private static function pages_css() {
		if ( self::$css_done ) {
			return;
		}
		self::$css_done = true;
		Velox_Builder_Render::add_css(
			'.vx-pages{margin-top:28px}.vx-pages ul{display:flex;flex-wrap:wrap;gap:6px;list-style:none;margin:0;padding:0}'
			. '.vx-pages a,.vx-pages span{display:inline-flex;align-items:center;justify-content:center;min-width:38px;height:38px;padding:0 12px;border-radius:8px;border:1px solid rgba(0,0,0,.12);color:inherit;text-decoration:none}'
			. '.vx-pages .current{background:rgba(0,0,0,.08);font-weight:600}'
			. '.vx-pages.is-prevnext{display:flex;justify-content:space-between;gap:12px}'
		);
	}

	private static function no_cache() {
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
	}

	/* ------------------------------------------------------------ dynamic values */

	/** Text value of a loop token, or null when the token is not a loop token. */
	public static function token( $token ) {
		switch ( $token ) {
			case 'loop.index':
				return (string) self::index();
			case 'post.url':
				$p = get_the_ID();
				return $p ? esc_url( get_permalink( $p ) ) : '';
			case 'term.name':
			case 'term.description':
			case 'term.count':
			case 'term.slug':
			case 'term.url':
				$t = self::current( 'term' );
				if ( ! $t ) {
					return '';
				}
				if ( 'term.url' === $token ) {
					$l = get_term_link( $t );
					return is_wp_error( $l ) ? '' : esc_url( $l );
				}
				$fields = array( 'term.name' => 'name', 'term.description' => 'description', 'term.count' => 'count', 'term.slug' => 'slug' );
				return esc_html( (string) $t->{ $fields[ $token ] } );
			case 'member.name':
			case 'member.bio':
			case 'member.posts':
			case 'member.url':
			case 'member.avatar':
				$u = self::current( 'user' );
				if ( ! $u ) {
					return '';
				}
				switch ( $token ) {
					case 'member.name':   return esc_html( $u->display_name );
					case 'member.bio':    return esc_html( (string) get_user_meta( $u->ID, 'description', true ) );
					case 'member.posts':  return (string) count_user_posts( $u->ID, 'post', true );
					case 'member.url':    return esc_url( get_author_posts_url( $u->ID ) );
					case 'member.avatar': return get_avatar( $u->ID, 96, '', $u->display_name );
				}
		}
		return null;
	}

	/**
	 * A link field may hold a dynamic value: {post.url}, {term.url},
	 * {member.url}, {author.url}, {site.url}. Anything else is returned as is.
	 */
	public static function href( $href ) {
		$href = trim( (string) $href );
		if ( ! preg_match( '/^\{([a-z]+\.url)\}$/', $href, $m ) ) {
			return $href;
		}
		switch ( $m[1] ) {
			case 'post.url':
			case 'term.url':
			case 'member.url':
				return html_entity_decode( (string) self::token( $m[1] ) );
			case 'author.url':
				$p = get_the_ID();
				return $p ? get_author_posts_url( (int) get_post_field( 'post_author', $p ) ) : '';
			case 'site.url':
				return home_url( '/' );
		}
		return '';
	}

	/* ------------------------------------------------------------ editor preview */

	/**
	 * AJAX: the items a loop would show, with the values of the dynamic data
	 * its children use, so the editor canvas can draw real content.
	 */
	public static function ajax_preview() {
		$loop   = isset( $_POST['loop'] ) ? json_decode( wp_unslash( $_POST['loop'] ), true ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce checked by the router
		$tokens = isset( $_POST['tokens'] ) ? json_decode( wp_unslash( $_POST['tokens'] ), true ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$nid    = isset( $_POST['node'] ) ? sanitize_text_field( wp_unslash( $_POST['node'] ) ) : 'loop'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$c      = self::config( $loop );
		if ( ! $c ) {
			wp_send_json_error( array( 'message' => __( 'Invalid loop settings.', 'velox' ) ), 400 );
		}
		self::$ctx_post = isset( $_POST['post'] ) ? absint( $_POST['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$tokens = array_slice( array_values( array_filter( (array) $tokens, function ( $t ) {
			return is_string( $t ) && preg_match( '/^[a-z]+\.[a-z_]+(:[A-Za-z0-9_\-]+)?$/', $t );
		} ) ), 0, 40 );
		$res   = self::fetch( $c, array( 'id' => $nid ), 12, 1 );
		$items = array();
		$i     = 0;
		$saved = $GLOBALS['post'] ?? null;
		foreach ( $res['items'] as $item ) {
			self::enter( $item, ++$i );
			$vals = array();
			foreach ( $tokens as $t ) {
				$v = Velox_Builder_Render::token_text( $t );
				$vals[ $t ] = wp_html_excerpt( html_entity_decode( wp_strip_all_tags( (string) $v ), ENT_QUOTES ), 400, '…' );
			}
			$o = $item[1];
			if ( 'post' === $item[0] ) {
				$title = get_the_title( $o );
				$img   = get_the_post_thumbnail_url( $o, 'medium_large' );
				$url   = get_permalink( $o );
			} elseif ( 'term' === $item[0] ) {
				$title = $o->name;
				$img   = '';
				$url   = get_term_link( $o );
			} else {
				$title = $o->display_name;
				$img   = get_avatar_url( $o->ID, array( 'size' => 192 ) );
				$url   = get_author_posts_url( $o->ID );
			}
			$items[] = array(
				'v'     => $vals,
				'title' => html_entity_decode( wp_strip_all_tags( (string) $title ), ENT_QUOTES ),
				'img'   => $img ? esc_url_raw( $img ) : '',
				'url'   => is_wp_error( $url ) ? '' : esc_url_raw( $url ),
			);
			self::leave();
		}
		$GLOBALS['post'] = $saved; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
		wp_reset_postdata();
		self::$ctx_post = 0;
		wp_send_json_success( array(
			'items' => $items,
			'total' => (int) $res['total'],
			'main'  => 'main' === $c['src'],
		) );
	}

	/** What the editor needs for the Query loop section. */
	public static function editor_data() {
		$types = array( array( 'v' => 'any', 'l' => __( 'Any type', 'velox' ) ) );
		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $pt ) {
			if ( 'attachment' !== $pt->name ) {
				$types[] = array( 'v' => $pt->name, 'l' => $pt->labels->name );
			}
		}
		$taxes = array();
		foreach ( get_taxonomies( array( 'public' => true ), 'objects' ) as $tx ) {
			if ( 'post_format' !== $tx->name ) {
				$taxes[] = array( 'v' => $tx->name, 'l' => $tx->labels->name );
			}
		}
		return array( 'postTypes' => $types, 'taxonomies' => $taxes );
	}
}
