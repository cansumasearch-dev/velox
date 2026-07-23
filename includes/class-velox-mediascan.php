<?php
/**
 * Batched media usage scanner.
 *
 * The old scanner asked "for each image, does it appear anywhere?", which meant
 * building giant content blobs and capping the work with arbitrary limits. This
 * one runs the other way round: walk every place a reference can live, pull the
 * upload references out of it into an index, then check attachments against that
 * index. It's linear, it batches cleanly, and it can say *where* a file is used.
 *
 * Deliberately does no HTTP: loopback requests are blocked on plenty of hosts,
 * which made the old front-end pass silently do nothing at all.
 *
 * @package Velox
 */

defined( 'ABSPATH' ) || exit;

class Velox_MediaScan {

	const STATE  = 'velox_mscan_state';
	const REFS   = 'velox_mscan_refs';
	const RESULT = 'velox_mscan_result';

	/** Phases run in order. Batch size is tuned per table, not global. */
	private static function phases() {
		return array(
			'posts'    => array( 'label' => 'Posts & pages', 'batch' => 40 ),
			'postmeta' => array( 'label' => 'Custom fields & builders', 'batch' => 400 ),
			'options'  => array( 'label' => 'Theme & plugin settings', 'batch' => 200 ),
			'meta'     => array( 'label' => 'Categories & users', 'batch' => 300 ),
			'files'    => array( 'label' => 'Theme & builder CSS', 'batch' => 1 ),
			'crawl'    => array( 'label' => 'Reading your pages', 'batch' => 1 ),
			'compare'  => array( 'label' => 'Checking media', 'batch' => 60 ),
		);
	}

	/**
	 * Every public URL worth reading. The browser crawls these, not the server —
	 * loopback HTTP is blocked on a lot of hosts, and the browser is already
	 * authenticated and on the right origin.
	 */
	public static function crawl_urls( $max = 400 ) {
		$urls  = array( home_url( '/' ) );
		$types = get_post_types( array( 'public' => true ), 'names' );
		unset( $types['attachment'] );
		$ids = get_posts( array(
			'post_type'      => array_values( $types ),
			'post_status'    => 'publish',
			'posts_per_page' => (int) $max,
			'fields'         => 'ids',
			'orderby'        => 'modified',
			'order'          => 'DESC',
		) );
		foreach ( $ids as $pid ) {
			$link = get_permalink( $pid );
			if ( $link ) { $urls[] = $link; }
		}
		return array_values( array_unique( array_filter( $urls ) ) );
	}

	/** Record upload paths a crawled page actually rendered. */
	public static function crawl_report( $paths, $label ) {
		$refs  = self::refs();
		$label = sanitize_text_field( (string) $label );
		$n     = 0;
		foreach ( (array) $paths as $rel ) {
			$key = self::path_key( wp_unslash( (string) $rel ) );
			if ( '' === $key ) { continue; }
			// A crawl hit is proof: the page really rendered this file.
			if ( ! isset( $refs['seen'][ $key ] ) ) { $refs['seen'][ $key ] = 'Seen on: ' . $label; }
			$n++;
		}
		self::save_refs( $refs );
		$state = get_option( self::STATE );
		if ( is_array( $state ) ) {
			$state['crawled'] = ( isset( $state['crawled'] ) ? (int) $state['crawled'] : 0 ) + 1;
			update_option( self::STATE, $state, false );
		}
		return array( 'ok' => true, 'indexed' => $n );
	}

	/** Crawl finished (or was skipped) — move on to matching. */
	public static function crawl_done( $pages = 0 ) {
		$state = get_option( self::STATE );
		if ( ! is_array( $state ) ) { return self::start(); }
		$state['phase']       = 'compare';
		$state['cursor']      = 0;
		$state['crawl_total'] = (int) $pages;
		update_option( self::STATE, $state, false );
		return array( 'ok' => true );
	}

	/* ------------------------------------------------------------------ job */

	public static function start() {
		delete_option( self::REFS );
		delete_option( self::RESULT );
		$state = array(
			'phase'   => 'posts',
			'cursor'  => 0,
			'started' => time(),
			'total'   => self::count_attachments(),
			'done'    => 0,
		);
		update_option( self::STATE, $state, false );
		update_option( self::REFS, array( 'seen' => array(), 'paths' => array(), 'ids' => array(), 'weak' => array() ), false );
		return self::progress( $state, 'Starting…' );
	}

	public static function step() {
		$state = get_option( self::STATE );
		if ( ! is_array( $state ) || empty( $state['phase'] ) ) {
			return self::start();
		}
		$phases = self::phases();
		$phase  = $state['phase'];
		if ( ! isset( $phases[ $phase ] ) ) {
			return self::finish( $state );
		}
		$batch = $phases[ $phase ]['batch'];

		// The crawl is driven by the browser: hand it the URL list and wait.
		if ( 'crawl' === $phase ) {
			return array(
				'done'    => false,
				'crawl'   => true,
				'urls'    => self::crawl_urls(),
				'percent' => 70,
				'label'   => 'Reading your pages',
				'phase'   => 'crawl',
			);
		}

		switch ( $phase ) {
			case 'posts':
				$more = self::scan_posts( $state, $batch );
				break;
			case 'postmeta':
				$more = self::scan_postmeta( $state, $batch );
				break;
			case 'options':
				$more = self::scan_options( $state, $batch );
				break;
			case 'meta':
				$more = self::scan_term_user_meta( $state, $batch );
				break;
			case 'files':
				$more = self::scan_files( $state );
				break;
			case 'compare':
				$more = self::compare( $state, $batch );
				break;
			default:
				$more = false;
		}

		if ( ! $more ) {
			$keys  = array_keys( $phases );
			$idx   = array_search( $phase, $keys, true );
			$next  = isset( $keys[ $idx + 1 ] ) ? $keys[ $idx + 1 ] : '';
			$state['phase']  = $next;
			$state['cursor'] = 0;
		}
		update_option( self::STATE, $state, false );

		if ( empty( $state['phase'] ) ) {
			return self::finish( $state );
		}
		return self::progress( $state, $phases[ $state['phase'] ]['label'] ?? '' );
	}

	private static function finish( $state ) {
		$state['phase'] = '';
		$state['ended'] = time();
		update_option( self::STATE, $state, false );
		$res = get_option( self::RESULT );
		$res = is_array( $res ) ? $res : array();
		return array(
			'done'     => true,
			'percent'  => 100,
			'label'    => 'Finished',
			'counts'   => self::counts( $res ),
			'crawled'  => isset( $state['crawled'] ) ? (int) $state['crawled'] : 0,
			'crawlable'=> isset( $state['crawl_total'] ) ? (int) $state['crawl_total'] : 0,
		);
	}

	private static function progress( $state, $label ) {
		// Rough but honest: the compare phase is the one with a known total.
		$phases = array_keys( self::phases() );
		$pos    = array_search( $state['phase'], $phases, true );
		$pos    = false === $pos ? count( $phases ) : $pos;
		$base   = (int) floor( ( $pos / count( $phases ) ) * 100 );
		if ( 'compare' === $state['phase'] && $state['total'] > 0 ) {
			$base = 83 + (int) floor( ( $state['cursor'] / max( 1, $state['total'] ) ) * 17 );
		}
		return array(
			'done'    => false,
			'percent' => min( 99, max( 1, $base ) ),
			'label'   => $label,
			'phase'   => $state['phase'],
		);
	}

	/* -------------------------------------------------------------- sources */

	private static function scan_posts( &$state, $batch ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT ID, post_title, post_content FROM {$wpdb->posts}
			 WHERE post_type NOT IN ('revision','attachment')
			   AND post_status NOT IN ('trash','auto-draft')
			   AND post_content <> ''
			 ORDER BY ID ASC LIMIT %d OFFSET %d",
			$batch, (int) $state['cursor']
		), ARRAY_A );
		if ( ! $rows ) { return false; }
		$refs = self::refs();
		foreach ( $rows as $r ) {
			$label = $r['post_title'] ? $r['post_title'] : ( 'Post #' . $r['ID'] );
			self::extract( $r['post_content'], $label, $refs );
		}
		self::save_refs( $refs );
		$state['cursor'] += count( $rows );
		return count( $rows ) === (int) $batch;
	}

	private static function scan_postmeta( &$state, $batch ) {
		global $wpdb;
		// Crucially: skip meta belonging to attachments. Every attachment stores its
		// own path in _wp_attached_file and every size filename in
		// _wp_attachment_metadata, so scanning those makes every image "reference
		// itself" and the whole library looks used.
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT pm.post_id, pm.meta_key, pm.meta_value
			 FROM {$wpdb->postmeta} pm
			 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			 WHERE pm.meta_value <> ''
			   AND p.post_type <> 'attachment'
			   AND p.post_type <> 'revision'
			   AND p.post_status NOT IN ('trash','auto-draft')
			 ORDER BY pm.meta_id ASC LIMIT %d OFFSET %d",
			$batch, (int) $state['cursor']
		), ARRAY_A );
		if ( ! $rows ) { return false; }
		$refs = self::refs();
		foreach ( $rows as $r ) {
			$key = $r['meta_key'];
			$val = (string) $r['meta_value'];

			// Hard references: WordPress and WooCommerce store bare IDs here.
			if ( '_thumbnail_id' === $key && ctype_digit( $val ) ) {
				$refs['ids'][ (int) $val ] = 'Featured image';
				continue;
			}
			if ( '_product_image_gallery' === $key ) {
				foreach ( array_filter( array_map( 'trim', explode( ',', $val ) ) ) as $gid ) {
					if ( ctype_digit( $gid ) ) { $refs['ids'][ (int) $gid ] = 'Product gallery'; }
				}
				continue;
			}
			// A bare integer on its own means nothing — prices, counts and timestamps
			// all look like attachment ids. Only trust it when ACF has registered a
			// matching field key alongside it, which is a real image-field signal.
			if ( ctype_digit( $val ) && strlen( $val ) < 10 && '_' !== substr( $key, 0, 1 ) ) {
				$sib = get_post_meta( (int) $r['post_id'], '_' . $key, true );
				if ( is_string( $sib ) && 0 === strpos( $sib, 'field_' ) ) {
					$id = (int) $val;
					if ( $id > 0 && ! isset( $refs['weak'][ $id ] ) ) {
						$refs['weak'][ $id ] = 'Custom field: ' . $key;
					}
				}
			}
			self::extract( $val, 'Field: ' . $key, $refs );
		}
		self::save_refs( $refs );
		$state['cursor'] += count( $rows );
		return count( $rows ) === (int) $batch;
	}

	private static function scan_options( &$state, $batch ) {
		global $wpdb;
		// Only options that can plausibly hold a reference — the table is huge.
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT option_name, option_value FROM {$wpdb->options}
			 WHERE ( option_value LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s )
			   AND option_name NOT LIKE 'velox_mscan%'
			   AND option_name NOT LIKE 'velox_blueprints%'
			   AND option_name NOT LIKE '_transient%'
			   AND option_name NOT LIKE '_site_transient%'
			 ORDER BY option_id ASC LIMIT %d OFFSET %d",
			'%wp-content/uploads%', 'theme_mods_%', 'widget_%', 'oxygen_%',
			$batch, (int) $state['cursor']
		), ARRAY_A );
		if ( ! $rows ) { return false; }
		$refs = self::refs();
		foreach ( $rows as $r ) {
			self::extract( (string) $r['option_value'], 'Setting: ' . $r['option_name'], $refs );
		}
		self::save_refs( $refs );
		$state['cursor'] += count( $rows );
		return count( $rows ) === (int) $batch;
	}

	private static function scan_term_user_meta( &$state, $batch ) {
		global $wpdb;
		$half = max( 1, (int) floor( $batch / 2 ) );
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT meta_key, meta_value FROM {$wpdb->termmeta} WHERE meta_value <> '' ORDER BY meta_id ASC LIMIT %d OFFSET %d",
			$half, (int) $state['cursor']
		), ARRAY_A );
		$rows2 = $wpdb->get_results( $wpdb->prepare(
			"SELECT meta_key, meta_value FROM {$wpdb->usermeta} WHERE meta_value <> '' ORDER BY umeta_id ASC LIMIT %d OFFSET %d",
			$half, (int) $state['cursor']
		), ARRAY_A );
		if ( ! $rows && ! $rows2 ) { return false; }
		$refs = self::refs();
		foreach ( array( array( $rows, 'Category' ), array( $rows2, 'User' ) ) as $set ) {
			foreach ( (array) $set[0] as $r ) {
				$v = (string) $r['meta_value'];
				// Only keys that actually name an image are trusted here.
				if ( ctype_digit( $v ) && strlen( $v ) < 10 && preg_match( '/(image|thumb|logo|icon|photo|avatar|banner|cover)/i', $r['meta_key'] ) ) {
					$id = (int) $v;
					if ( $id > 0 && ! isset( $refs['weak'][ $id ] ) ) {
						$refs['weak'][ $id ] = $set[1] . ' field: ' . $r['meta_key'];
					}
				}
				self::extract( $v, $set[1] . ': ' . $r['meta_key'], $refs );
			}
		}
		self::save_refs( $refs );
		$state['cursor'] += $half;
		return ( count( $rows ) === $half || count( $rows2 ) === $half );
	}

	/**
	 * Theme files, child theme and builder CSS caches. Background images defined
	 * in a stylesheet are the classic false positive — nothing in the database
	 * mentions them.
	 */
	private static function scan_files( &$state ) {
		$refs  = self::refs();
		$roots = array( get_stylesheet_directory(), get_template_directory() );
		// Deliberately NOT scanning builder CSS caches any more: they keep stale
		// files for pages that no longer exist, which marked deleted-page images as
		// used. The crawl fetches the stylesheets pages really load instead.
		foreach ( array_unique( array_filter( $roots ) ) as $root ) {
			self::scan_dir( $root, $refs );
		}
		// Custom CSS from the customizer.
		$css_post = function_exists( 'wp_get_custom_css' ) ? wp_get_custom_css() : '';
		if ( $css_post ) { self::extract( $css_post, 'Additional CSS', $refs ); }
		self::save_refs( $refs );
		return false; // single pass
	}

	private static function scan_dir( $root, &$refs, $depth = 0 ) {
		if ( $depth > 4 || ! is_dir( $root ) ) { return; }
		$items = @scandir( $root ); // phpcs:ignore
		if ( ! $items ) { return; }
		foreach ( $items as $item ) {
			if ( '.' === $item || '..' === $item || 'node_modules' === $item ) { continue; }
			$path = $root . '/' . $item;
			if ( is_dir( $path ) ) {
				self::scan_dir( $path, $refs, $depth + 1 );
				continue;
			}
			$ext = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
			if ( ! in_array( $ext, array( 'css', 'php', 'js', 'json', 'scss' ), true ) ) { continue; }
			if ( ! is_readable( $path ) || filesize( $path ) > 3000000 ) { continue; }
			$c = @file_get_contents( $path ); // phpcs:ignore
			if ( $c ) { self::extract( $c, 'File: ' . basename( $path ), $refs ); }
		}
	}

	/* ------------------------------------------------------------ extraction */

	/**
	 * Pull every upload reference out of a chunk of text.
	 * Paths are normalised so a resized, scaled or WebP-converted variant maps to
	 * the same key as the original — otherwise an optimiser that rewrites
	 * hero.jpg to hero.webp makes the original look unused.
	 */
	private static function extract( $text, $label, &$refs ) {
		if ( ! is_string( $text ) || '' === $text ) { return; }
		// JSON-encoded builder data escapes its slashes (wp-content\/uploads\/…),
		// and theme CSS often uses a relative ../uploads/… path, so normalise the
		// slashes first and match on "uploads/" rather than the full path.
		$flat = ( false !== strpos( $text, '\\/' ) ) ? str_replace( '\\/', '/', $text ) : $text;
		if ( false !== stripos( $flat, 'uploads/' ) ) {
			if ( preg_match_all( '#uploads/((?:[0-9]{4}/[0-9]{2}/)?[^\s"\'\\\\)<>\[\]]+?\.[a-z0-9]{2,5})#i', $flat, $m ) ) {
				foreach ( $m[1] as $rel ) {
					$key = self::path_key( $rel );
					if ( '' !== $key && ! isset( $refs['paths'][ $key ] ) ) {
						$refs['paths'][ $key ] = $label;
					}
				}
			}
		}
		// Editor-inserted images carry the attachment id in a class.
		if ( false !== strpos( $flat, 'wp-image-' ) && preg_match_all( '/wp-image-(\d+)/', $flat, $mi ) ) {
			foreach ( $mi[1] as $id ) {
				if ( ! isset( $refs['ids'][ (int) $id ] ) ) { $refs['ids'][ (int) $id ] = $label; }
			}
		}
		// Builder/block JSON stores {"id":123,"url":"…uploads…"}. Matching a bare
		// "id" anywhere would tag menus, settings and form fields as image use, so
		// only accept an id that sits next to an uploads URL.
		if ( false !== strpos( $flat, '"id"' ) && false !== stripos( $flat, 'uploads/' ) ) {
			if ( preg_match_all( '/"id"\s*:\s*"?(\d{1,9})"?\s*,\s*"url"\s*:\s*"[^"]*uploads\//i', $flat, $mj ) ) {
				foreach ( $mj[1] as $id ) {
					if ( ! isset( $refs['ids'][ (int) $id ] ) ) { $refs['ids'][ (int) $id ] = $label; }
				}
			}
			if ( preg_match_all( '/"url"\s*:\s*"[^"]*uploads\/[^"]*"\s*,\s*"id"\s*:\s*"?(\d{1,9})"?/i', $flat, $mk ) ) {
				foreach ( $mk[1] as $id ) {
					if ( ! isset( $refs['ids'][ (int) $id ] ) ) { $refs['ids'][ (int) $id ] = $label; }
				}
			}
		}
	}

	/**
	 * Normalise an upload-relative path to a comparison key:
	 * 2024/03/hero-1024x768.webp → 2024/03/hero
	 */
	public static function path_key( $rel ) {
		$rel = strtok( (string) $rel, '?#' );
		$rel = ltrim( str_replace( '\\', '/', $rel ), '/' );
		$dir = trim( (string) dirname( $rel ), '.' );
		$stem = pathinfo( $rel, PATHINFO_FILENAME );
		if ( '' === $stem ) { return ''; }
		$stem = preg_replace( '/-\d+x\d+$/', '', $stem );   // resized variant
		$stem = preg_replace( '/-scaled$/', '', $stem );     // big-image variant
		$stem = preg_replace( '/-rotated$/', '', $stem );
		$key  = ( $dir ? trim( $dir, '/' ) . '/' : '' ) . $stem;
		return strtolower( $key );
	}

	/* -------------------------------------------------------------- compare */

	private static function compare( &$state, $batch ) {
		global $wpdb;
		$ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' ORDER BY ID ASC LIMIT %d OFFSET %d",
			$batch, (int) $state['cursor']
		) );
		if ( ! $ids ) { return false; }
		$refs = self::refs();
		$res  = get_option( self::RESULT );
		$res  = is_array( $res ) ? $res : array();

		$logo = (int) get_theme_mod( 'custom_logo' );
		$icon = (int) get_option( 'site_icon' );

		foreach ( $ids as $id ) {
			$id   = (int) $id;
			$file = get_post_meta( $id, '_wp_attached_file', true );
			$key  = $file ? self::path_key( $file ) : '';
			$state_ = 'unused';
			$where  = '';

			// 1. Proof — the crawl rendered it, or WordPress structurally points at it.
			if ( '' !== $key && isset( $refs['seen'][ $key ] ) ) {
				$state_ = 'used'; $where = $refs['seen'][ $key ];
			} elseif ( $id === $logo ) {
				$state_ = 'used'; $where = 'Site logo';
			} elseif ( $id === $icon ) {
				$state_ = 'used'; $where = 'Site icon';
			} elseif ( isset( $refs['ids'][ $id ] ) ) {
				$state_ = 'used'; $where = $refs['ids'][ $id ];
			// 2. Mentioned somewhere, but never seen on a page. Could be a draft, an
			//    old setting or a theme file — worth a look, not proof of use.
			} elseif ( '' !== $key && isset( $refs['paths'][ $key ] ) ) {
				$state_ = 'maybe'; $where = $refs['paths'][ $key ];
			} elseif ( isset( $refs['weak'][ $id ] ) ) {
				$state_ = 'maybe'; $where = $refs['weak'][ $id ];
			}

			$res[ $id ] = array( 's' => $state_, 'w' => $where );
		}
		update_option( self::RESULT, $res, false );
		$state['cursor'] += count( $ids );
		return count( $ids ) === (int) $batch;
	}

	/* ---------------------------------------------------------------- output */

	public static function results( $filter = 'all', $limit = 500 ) {
		$res = get_option( self::RESULT );
		$res = is_array( $res ) ? $res : array();
		$out = array();
		foreach ( $res as $id => $r ) {
			if ( 'all' !== $filter && $r['s'] !== $filter ) { continue; }
			if ( count( $out ) >= $limit ) { break; }
			$file  = get_attached_file( $id );
			$out[] = array(
				'id'    => (int) $id,
				'title' => get_the_title( $id ),
				'url'   => wp_get_attachment_url( $id ),
				'thumb' => wp_get_attachment_image_url( $id, 'thumbnail' ),
				'size'  => ( $file && file_exists( $file ) ) ? size_format( filesize( $file ), 1 ) : '',
				'state' => $r['s'],
				'where' => $r['w'],
			);
		}
		$st = get_option( self::STATE );
		return array(
			'items'     => $out,
			'counts'    => self::counts( $res ),
			'crawled'   => is_array( $st ) && isset( $st['crawled'] ) ? (int) $st['crawled'] : 0,
			'crawlable' => is_array( $st ) && isset( $st['crawl_total'] ) ? (int) $st['crawl_total'] : 0,
		);
	}

	private static function counts( $res ) {
		$c = array( 'used' => 0, 'maybe' => 0, 'unused' => 0 );
		foreach ( (array) $res as $r ) {
			if ( isset( $c[ $r['s'] ] ) ) { $c[ $r['s'] ]++; }
		}
		return $c;
	}

	private static function count_attachments() {
		global $wpdb;
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment'" );
	}

	private static function refs() {
		$r = get_option( self::REFS );
		if ( ! is_array( $r ) ) { $r = array(); }
		if ( ! isset( $r['seen'] ) ) { $r['seen'] = array(); }
		if ( ! isset( $r['paths'] ) ) { $r['paths'] = array(); }
		if ( ! isset( $r['ids'] ) ) { $r['ids'] = array(); }
		if ( ! isset( $r['weak'] ) ) { $r['weak'] = array(); }
		return $r;
	}

	private static function save_refs( $refs ) {
		update_option( self::REFS, $refs, false );
	}
}
