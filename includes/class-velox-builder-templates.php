<?php
/**
 * Velox Builder — template conditions.
 *
 * A template can say exactly where it is used: "Use on" rules, "But not on"
 * exceptions, a priority, and optionally only for logged-in or logged-out
 * visitors. Rules are checked after a page's own template choice and before
 * the template purposes (Front page / Posts / Pages / Archives …), so a site
 * without rules behaves exactly as before.
 *
 * Stored in one option, keyed by template id:
 *   { inc: [ {t, v, v2} ], exc: [ {t, v, v2} ], pri: int, who: '' | in | out }
 *
 * @package Velox
 */

defined( 'ABSPATH' ) || exit;

class Velox_Builder_Templates {

	const OPT = 'velox_builder_template_conditions';

	/** Rule types and how specific they are: the more specific match wins a tie. */
	const TYPES = array(
		'entire'    => 10,
		'singular'  => 30,
		'archive'   => 30,
		'posttype'  => 50,
		'ptarchive' => 55,
		'author'    => 50,
		'date'      => 50,
		'tax'       => 60,
		'interm'    => 70,
		'url'       => 80,
		'blog'      => 85,
		'search'    => 90,
		'child'     => 90,
		'front'     => 95,
		'404'       => 95,
		'page'      => 100,
	);

	/* ------------------------------------------------------------ storage */

	public static function all() {
		$o = get_option( self::OPT, array() );
		return is_array( $o ) ? $o : array();
	}

	public static function get( $id ) {
		$all = self::all();
		return self::clean( $all[ (int) $id ] ?? array() );
	}

	public static function has_rules( $id ) {
		return (bool) self::get( $id )['inc'];
	}

	/** Rules with every value checked. */
	public static function clean( $in ) {
		$in   = is_array( $in ) ? $in : array();
		$list = function ( $rules ) {
			$out = array();
			foreach ( array_slice( (array) $rules, 0, 30 ) as $r ) {
				if ( ! is_array( $r ) || empty( $r['t'] ) || ! isset( self::TYPES[ $r['t'] ] ) ) {
					continue;
				}
				$v  = sanitize_text_field( (string) ( $r['v'] ?? '' ) );
				$v2 = sanitize_text_field( (string) ( $r['v2'] ?? '' ) );
				if ( in_array( $r['t'], array( 'posttype', 'ptarchive', 'tax', 'interm' ), true ) ) {
					$v = sanitize_key( $v );
				}
				$out[] = array( 't' => $r['t'], 'v' => $v, 'v2' => $v2 );
			}
			return $out;
		};
		return array(
			'inc' => $list( $in['inc'] ?? array() ),
			'exc' => $list( $in['exc'] ?? array() ),
			'pri' => max( -100, min( 100, (int) ( $in['pri'] ?? 0 ) ) ),
			'who' => in_array( $in['who'] ?? '', array( 'in', 'out' ), true ) ? $in['who'] : '',
		);
	}

	public static function save( $id, $data ) {
		$id  = (int) $id;
		$all = self::all();
		$c   = self::clean( $data );
		if ( ! $c['inc'] && ! $c['exc'] && ! $c['pri'] && '' === $c['who'] ) {
			unset( $all[ $id ] );
		} else {
			$all[ $id ] = $c;
		}
		update_option( self::OPT, $all );
		return $c;
	}

	public static function forget( $id ) {
		$all = self::all();
		if ( isset( $all[ (int) $id ] ) ) {
			unset( $all[ (int) $id ] );
			update_option( self::OPT, $all );
		}
	}

	/* ------------------------------------------------------------ context */

	/** What the current request shows, in the terms the rules use. */
	public static function ctx_request() {
		$path = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_parse_url( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH ) : '/';
		$c    = array( 'kind' => 'archive', 'front' => false, 'path' => self::rel_path( $path ) );
		if ( is_404() ) {
			$c['kind'] = '404';
		} elseif ( is_search() ) {
			$c['kind'] = 'search';
		} elseif ( is_home() ) {
			$c['kind']  = 'home';
			$c['front'] = is_front_page();
		} elseif ( is_singular() ) {
			$c         = self::ctx_post( get_queried_object_id() );
			$c['path'] = self::rel_path( $path );
		} else {
			$q = get_queried_object();
			if ( is_category() || is_tag() || is_tax() ) {
				$c['arch']     = 'tax';
				$c['taxonomy'] = $q instanceof WP_Term ? $q->taxonomy : '';
				$c['term']     = $q instanceof WP_Term ? $q : null;
			} elseif ( is_post_type_archive() ) {
				$pt          = get_query_var( 'post_type' );
				$c['arch']   = 'ptarchive';
				$c['pt']     = is_array( $pt ) ? (string) reset( $pt ) : (string) $pt;
			} elseif ( is_author() ) {
				$c['arch'] = 'author';
			} elseif ( is_date() ) {
				$c['arch'] = 'date';
			}
		}
		return $c;
	}

	/** The same description for one post (page editor, tester). */
	public static function ctx_post( $post_id ) {
		$post = get_post( (int) $post_id );
		if ( ! $post ) {
			return array( 'kind' => '404', 'front' => false, 'path' => '' );
		}
		$front = 'page' === get_option( 'show_on_front' ) && (int) get_option( 'page_on_front' ) === (int) $post->ID;
		if ( (int) get_option( 'page_for_posts' ) === (int) $post->ID ) {
			return array( 'kind' => 'home', 'front' => false, 'path' => self::rel_path( (string) wp_parse_url( get_permalink( $post ), PHP_URL_PATH ) ) );
		}
		return array(
			'kind'      => 'singular',
			'front'     => $front,
			'post_id'   => (int) $post->ID,
			'post_type' => $post->post_type,
			'ancestors' => array_map( 'intval', get_post_ancestors( $post ) ),
			'path'      => self::rel_path( (string) wp_parse_url( get_permalink( $post ), PHP_URL_PATH ) ),
		);
	}

	/** A path relative to the site's home ("/shop/shoes/"), so rules survive a move. */
	private static function rel_path( $path ) {
		$home = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		$path = '/' . ltrim( (string) $path, '/' );
		if ( '' !== $home && '/' !== $home && 0 === strpos( $path, $home ) ) {
			$path = '/' . ltrim( substr( $path, strlen( $home ) ), '/' );
		}
		return $path;
	}

	/* ------------------------------------------------------------ matching */

	private static function csv( $v ) {
		return array_values( array_filter( array_map( 'trim', explode( ',', (string) $v ) ), 'strlen' ) );
	}

	/** Does one rule match? Returns its specificity, or 0. */
	public static function rule_score( $r, $c ) {
		$t    = $r['t'];
		$sing = 'singular' === $c['kind'];
		$ok   = false;
		switch ( $t ) {
			case 'entire':   $ok = true; break;
			case 'singular': $ok = $sing; break;
			case 'archive':  $ok = in_array( $c['kind'], array( 'archive', 'home' ), true ); break;
			case 'front':    $ok = ! empty( $c['front'] ); break;
			case 'blog':     $ok = 'home' === $c['kind']; break;
			case 'search':   $ok = 'search' === $c['kind']; break;
			case '404':      $ok = '404' === $c['kind']; break;
			case 'author':   $ok = 'author' === ( $c['arch'] ?? '' ); break;
			case 'date':     $ok = 'date' === ( $c['arch'] ?? '' ); break;
			case 'posttype': $ok = $sing && '' !== $r['v'] && $r['v'] === $c['post_type']; break;
			case 'ptarchive': $ok = 'ptarchive' === ( $c['arch'] ?? '' ) && $r['v'] === ( $c['pt'] ?? '' ); break;
			case 'page':
				$ok = $sing && in_array( $c['post_id'], array_map( 'intval', self::csv( $r['v'] ) ), true );
				break;
			case 'child':
				$ok = $sing && array_intersect( $c['ancestors'], array_map( 'intval', self::csv( $r['v'] ) ) );
				break;
			case 'interm':
				$terms = self::csv( $r['v2'] );
				$ok    = $sing && '' !== $r['v'] && taxonomy_exists( $r['v'] ) && $terms
					&& has_term( array_map( function ( $x ) { return ctype_digit( $x ) ? (int) $x : $x; }, $terms ), $r['v'], $c['post_id'] );
				break;
			case 'tax':
				$term = $c['term'] ?? null;
				$ok   = 'tax' === ( $c['arch'] ?? '' ) && $r['v'] === ( $c['taxonomy'] ?? '' );
				if ( $ok && '' !== $r['v2'] ) {
					$want = self::csv( $r['v2'] );
					$ok   = $term && ( in_array( (string) $term->term_id, $want, true ) || in_array( $term->slug, $want, true ) );
					return $ok ? self::TYPES['interm'] : 0;
				}
				break;
			case 'url':
				$ok = '' !== $r['v'] && self::path_matches( $r['v'], $c['path'] ?? '' );
				break;
		}
		return $ok ? self::TYPES[ $t ] : 0;
	}

	/** "/shop/*" style patterns; * is anything, a trailing slash does not matter. */
	public static function path_matches( $pattern, $path ) {
		$norm = function ( $p ) {
			$p = '/' . trim( (string) $p, '/' );
			return '/' === $p ? '/' : $p;
		};
		$re = '#^' . str_replace( '\*', '.*', preg_quote( $norm( $pattern ), '#' ) ) . '$#i';
		return (bool) preg_match( $re, $norm( $path ) );
	}

	/**
	 * A template's "But not on" rules name this context. They count for every
	 * way a template can apply (its purpose, site default, catch-all), not only
	 * for its "Use on" rules: an excluded page used to get it anyway as the default.
	 */
	public static function excluded( $id, $c ) {
		if ( ! $c ) {
			return false;
		}
		$rules = self::get( (int) $id );
		foreach ( (array) ( $rules['exc'] ?? array() ) as $r ) {
			if ( self::rule_score( $r, $c ) ) {
				return true;
			}
		}
		return false;
	}

	/** The best include score of a rule set, after exceptions. 0 = does not apply. */
	public static function score( $rules, $c ) {
		$best = 0;
		foreach ( $rules['inc'] as $r ) {
			$best = max( $best, self::rule_score( $r, $c ) );
		}
		if ( ! $best ) {
			return 0;
		}
		foreach ( $rules['exc'] as $r ) {
			if ( self::rule_score( $r, $c ) ) {
				return 0;
			}
		}
		if ( 'in' === $rules['who'] && ! is_user_logged_in() ) {
			return 0;
		}
		if ( 'out' === $rules['who'] && is_user_logged_in() ) {
			return 0;
		}
		return $best;
	}

	/**
	 * The published template whose rules fit best: highest priority, then the
	 * most specific matching rule, then the oldest template.
	 *
	 * @return array|null array( 'id', 'score', 'pri' ) or null.
	 */
	public static function match( $c ) {
		$live = Velox_Builder::live_templates();
		$best = null;
		foreach ( self::all() as $id => $raw ) {
			$id = (int) $id;
			if ( ! in_array( $id, $live, true ) ) {
				continue;
			}
			$rules = self::clean( $raw );
			if ( ! $rules['inc'] ) {
				continue;
			}
			$s = self::score( $rules, $c );
			if ( ! $s ) {
				continue;
			}
			// Logged-in/out rules decide per visitor: keep the page cache out of it.
			if ( '' !== $rules['who'] && class_exists( 'Velox_Settings' ) && Velox_Settings::get( 'cache_logged_in', false ) && ! defined( 'DONOTCACHEPAGE' ) ) {
				define( 'DONOTCACHEPAGE', true );
			}
			$cand = array( 'id' => $id, 'score' => $s, 'pri' => $rules['pri'] );
			if ( ! $best || $cand['pri'] > $best['pri'] || ( $cand['pri'] === $best['pri'] && ( $cand['score'] > $best['score'] || ( $cand['score'] === $best['score'] && $id < $best['id'] ) ) ) ) {
				$best = $cand;
			}
		}
		return $best;
	}

	/* ------------------------------------------------------------ words */

	public static function type_labels() {
		return array(
			'entire'    => __( 'Entire site', 'velox' ),
			'front'     => __( 'Front page', 'velox' ),
			'blog'      => __( 'Blog page (latest posts)', 'velox' ),
			'singular'  => __( 'Any single post or page', 'velox' ),
			'posttype'  => __( 'Single items of a post type', 'velox' ),
			'page'      => __( 'Specific posts or pages', 'velox' ),
			'child'     => __( 'Child pages of', 'velox' ),
			'interm'    => __( 'Posts in a category or term', 'velox' ),
			'archive'   => __( 'Any archive', 'velox' ),
			'tax'       => __( 'Category, tag or term archive', 'velox' ),
			'ptarchive' => __( 'Post type archive', 'velox' ),
			'author'    => __( 'Author archives', 'velox' ),
			'date'      => __( 'Date archives', 'velox' ),
			'search'    => __( 'Search results', 'velox' ),
			'404'       => __( 'Page not found (404)', 'velox' ),
			'url'       => __( 'Address (URL) matches', 'velox' ),
		);
	}

	/** One rule in words: "Posts in a category or term: category = news". */
	public static function rule_text( $r ) {
		$l = self::type_labels()[ $r['t'] ] ?? $r['t'];
		$v = trim( $r['v'] . ( '' !== $r['v2'] ? ' = ' . $r['v2'] : '' ) );
		return '' !== $v ? $l . ': ' . $v : $l;
	}

	public static function summary( $id ) {
		$c = self::get( $id );
		if ( ! $c['inc'] ) {
			return '';
		}
		$s = implode( ', ', array_map( array( __CLASS__, 'rule_text' ), $c['inc'] ) );
		if ( $c['exc'] ) {
			/* translators: %s: list of exceptions. */
			$s .= ' — ' . sprintf( __( 'not on %s', 'velox' ), implode( ', ', array_map( array( __CLASS__, 'rule_text' ), $c['exc'] ) ) );
		}
		return $s;
	}

	/* ------------------------------------------------------------ resolution with reasons */

	/**
	 * Which template a context gets, and why. Mirrors the runtime order:
	 * page choice → rules → purpose → site default → catch-all.
	 */
	public static function explain( $c ) {
		$live  = Velox_Builder::live_templates();
		$title = function ( $id ) {
			$m = Velox_Builder::doc_model( $id );
			return $m && ! empty( $m['__title'] ) ? $m['__title'] : sprintf( '#%d', $id );
		};
		$out = array( 'id' => 0, 'title' => '', 'why' => '', 'note' => '', 'matches' => array() );
		// Every template whose rules match, for the "also matched" list.
		foreach ( self::all() as $id => $raw ) {
			$rules = self::clean( $raw );
			if ( $rules['inc'] && self::score( $rules, $c ) ) {
				$out['matches'][] = array( 'id' => (int) $id, 'title' => $title( (int) $id ), 'live' => in_array( (int) $id, $live, true ), 'pri' => $rules['pri'] );
			}
		}
		$set = function ( $id, $why ) use ( &$out, $title ) {
			$out['id']    = (int) $id;
			$out['title'] = $id ? $title( (int) $id ) : '';
			$out['why']   = $why;
			return $out;
		};
		if ( 'singular' === $c['kind'] ) {
			$choice = get_post_meta( $c['post_id'], '_velox_template', true );
			if ( '-1' === (string) $choice ) {
				return $set( 0, __( 'This page is set to “No template”.', 'velox' ) );
			}
			if ( (int) $choice > 0 && in_array( (int) $choice, $live, true ) ) {
				return $set( (int) $choice, __( 'Chosen on the page itself.', 'velox' ) );
			}
			if ( ! Velox_Builder::doc_id_for_post( $c['post_id'] ) && ! Velox_Builder::wrap_legacy() ) {
				$out['note'] = __( 'This page has no Velox layout, so the theme shows it. Templates wrap it only when “Wrap pages without a Velox layout” is on.', 'velox' );
			}
		}
		$m = self::match( $c );
		if ( $m ) {
			$rules = self::get( $m['id'] );
			$hit   = '';
			foreach ( $rules['inc'] as $r ) {
				if ( self::rule_score( $r, $c ) === $m['score'] ) {
					$hit = self::rule_text( $r );
					break;
				}
			}
			/* translators: 1: the rule that matched, 2: priority number. */
			return $set( $m['id'], sprintf( __( 'Its rule “%1$s” matches (priority %2$d).', 'velox' ), $hit, $m['pri'] ) );
		}
		$purposes = Velox_Builder::template_purposes();
		if ( 'singular' === $c['kind'] ) {
			$tp = Velox_Builder::template_by_purpose( $c['post_id'], false, $c );
			if ( $tp ) {
				$p = Velox_Builder::template_purpose( $tp );
				/* translators: %s: what the template is for. */
				return $set( $tp, sprintf( __( 'It is made for: %s', 'velox' ), $purposes[ $p ] ?? $p ) );
			}
			$def = (int) Velox_Builder::default_template();
			if ( $def && in_array( $def, $live, true ) && ! self::excluded( $def, $c ) ) {
				return $set( $def, __( 'It is the site default.', 'velox' ) );
			}
			$ca = Velox_Builder::template_for_context( array( 'catch_all' ), $c );
			return $ca ? $set( $ca, __( 'It is a Catch-all template.', 'velox' ) ) : $set( 0, __( 'No template applies.', 'velox' ) );
		}
		$want = array();
		if ( '404' === $c['kind'] ) {
			$want = array( 'error404' );
		} elseif ( 'search' === $c['kind'] ) {
			$want = array( 'search', 'archive' );
		} elseif ( 'home' === $c['kind'] && ! empty( $c['front'] ) ) {
			$want = array( 'front_page', 'archive' );
		} elseif ( 'home' === $c['kind'] || in_array( $c['arch'] ?? '', array( 'tax', 'author', 'date' ), true ) ) {
			$want = array( 'archive' );
		}
		$tp = $want ? Velox_Builder::template_for_context( $want, $c ) : 0;
		if ( $tp ) {
			$p = Velox_Builder::template_purpose( $tp );
			/* translators: %s: what the template is for. */
			return $set( $tp, sprintf( __( 'It is made for: %s', 'velox' ), $purposes[ $p ] ?? $p ) );
		}
		if ( Velox_Builder::wrap_legacy() ) {
			$ca = Velox_Builder::template_for_context( array( 'catch_all' ), $c );
			if ( $ca ) {
				return $set( $ca, __( 'It is a Catch-all template.', 'velox' ) );
			}
		}
		return $set( 0, __( 'No template applies; the theme shows this screen.', 'velox' ) );
	}

	/** Describe any address on this site the way the rules see it. */
	public static function ctx_for_url( $url ) {
		$url  = trim( (string) $url );
		$home = wp_parse_url( home_url( '/' ) );
		if ( '' === $url ) {
			return null;
		}
		if ( 0 === strpos( $url, '/' ) ) {
			$url = home_url( $url );
		}
		$p = wp_parse_url( $url );
		if ( ! $p || ( isset( $p['host'] ) && strtolower( $p['host'] ) !== strtolower( $home['host'] ?? '' ) ) ) {
			return null;
		}
		$path = $p['path'] ?? '/';
		$id   = url_to_postid( $url );
		$home_path = (string) ( $home['path'] ?? '/' );
		if ( ! $id && rtrim( $path, '/' ) === rtrim( $home_path, '/' ) && empty( $p['query'] ) ) {
			// The home page: a static front page or the latest posts.
			if ( 'page' === get_option( 'show_on_front' ) && get_option( 'page_on_front' ) ) {
				$id = (int) get_option( 'page_on_front' );
			} else {
				return array( 'kind' => 'home', 'front' => true, 'path' => '/' );
			}
		}
		if ( $id ) {
			return self::ctx_post( $id );
		}
		// Anything else: let WordPress parse the address and run the query.
		$saved = array( $_SERVER['REQUEST_URI'] ?? '', $GLOBALS['wp_query'] ?? null, $GLOBALS['wp_the_query'] ?? null, $_GET );
		$_SERVER['REQUEST_URI'] = $path . ( isset( $p['query'] ) ? '?' . $p['query'] : '' );
		$_GET = array();
		if ( isset( $p['query'] ) ) {
			parse_str( $p['query'], $_GET ); // phpcs:ignore WordPress.Security.NonceVerification
		}
		$wp = new WP();
		$wp->parse_request();
		$vars = $wp->query_vars;
		// In an admin request WordPress matches the rewrite rule but keeps the
		// variables to itself, so read them from the matched rule and the
		// address's own query string (public query variables only).
		if ( ! $vars ) {
			$found = array();
			if ( $wp->matched_query ) {
				parse_str( $wp->matched_query, $found );
			}
			$found = array_merge( $found, $_GET ); // phpcs:ignore WordPress.Security.NonceVerification
			$vars  = array_intersect_key( $found, array_flip( (array) $wp->public_query_vars ) );
			if ( ! $vars && '' !== trim( (string) $wp->request, '/' ) ) {
				$vars = array( 'error' => '404' );
			}
		}
		$q = new WP_Query();
		$q->query( $vars );
		if ( ! $q->is_archive() && ! $q->is_search() && ! $q->is_home() && ! $q->have_posts() ) {
			$q->set_404();
		}
		$GLOBALS['wp_query']     = $q; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
		$GLOBALS['wp_the_query'] = $q; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
		$c = self::ctx_request();
		$c['path'] = self::rel_path( $path );
		$_SERVER['REQUEST_URI'] = $saved[0];
		$GLOBALS['wp_query']     = $saved[1]; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
		$GLOBALS['wp_the_query'] = $saved[2]; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
		$_GET = $saved[3];
		return $c;
	}

	/* ------------------------------------------------------------ AJAX */

	public static function ajax_save() {
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- router checks the nonce
		if ( ! $id || ! in_array( $id, self::template_ids(), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Template not found.', 'velox' ) ), 404 );
		}
		$data = isset( $_POST['rules'] ) ? json_decode( wp_unslash( $_POST['rules'] ), true ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! is_array( $data ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid data.', 'velox' ) ), 400 );
		}
		$c = self::save( $id, $data );
		if ( class_exists( 'Velox_Cache' ) && method_exists( 'Velox_Cache', 'purge_all' ) ) {
			Velox_Cache::purge_all();
		}
		wp_send_json_success( array( 'rules' => $c, 'summary' => self::summary( $id ) ) );
	}

	public static function ajax_test() {
		$url = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$c   = self::ctx_for_url( $url );
		if ( ! $c ) {
			wp_send_json_error( array( 'message' => __( 'Enter an address on this site.', 'velox' ) ), 400 );
		}
		$kinds = array(
			'singular' => __( 'a single post or page', 'velox' ),
			'home'     => __( 'the blog page', 'velox' ),
			'archive'  => __( 'an archive', 'velox' ),
			'search'   => __( 'search results', 'velox' ),
			'404'      => __( 'a page that does not exist (404)', 'velox' ),
		);
		$what = $kinds[ $c['kind'] ] ?? $c['kind'];
		if ( 'singular' === $c['kind'] ) {
			/* translators: 1: post title, 2: post type label. */
			$what = sprintf( __( '“%1$s” (%2$s)', 'velox' ), get_the_title( $c['post_id'] ), get_post_type_object( $c['post_type'] ) ? get_post_type_object( $c['post_type'] )->labels->singular_name : $c['post_type'] );
		} elseif ( ! empty( $c['term'] ) ) {
			/* translators: %s: term name. */
			$what = sprintf( __( 'the archive of “%s”', 'velox' ), $c['term']->name );
		}
		$e = self::explain( $c );
		$e['what'] = $what;
		wp_send_json_success( $e );
	}

	private static function template_ids() {
		global $wpdb;
		return array_map( 'intval', (array) $wpdb->get_col( 'SELECT id FROM ' . Velox_Builder::table() . " WHERE kind = 'template'" ) );
	}

	/** Choices for the rule editor. */
	public static function admin_data() {
		$pts = array();
		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $pt ) {
			if ( 'attachment' !== $pt->name ) {
				$pts[] = array( 'v' => $pt->name, 'l' => $pt->labels->singular_name, 'archive' => (bool) $pt->has_archive );
			}
		}
		$tax = array();
		foreach ( get_taxonomies( array( 'public' => true ), 'objects' ) as $tx ) {
			if ( 'post_format' !== $tx->name ) {
				$tax[] = array( 'v' => $tx->name, 'l' => $tx->labels->singular_name );
			}
		}
		$types = array();
		foreach ( self::type_labels() as $k => $l ) {
			$types[] = array( 'v' => $k, 'l' => $l );
		}
		return array( 'types' => $types, 'postTypes' => $pts, 'taxonomies' => $tax, 'home' => home_url( '/' ) );
	}
}
