<?php
/**
 * Velox Builder — saved versions (revisions) of pages, templates and components.
 *
 * Every save keeps a version; saves by the same person within a few minutes
 * fold into one, so a working session does not bury the list. Every publish
 * and every restore is kept as its own version. Identical saves are skipped.
 * The newest versions per document are kept (40 by default).
 *
 * @package Velox
 */

defined( 'ABSPATH' ) || exit;

class Velox_Builder_Revisions {

	/** Saves by one person closer together than this fold into one version. */
	const FOLD_SECONDS = 600;

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'velox_builder_revisions';
	}

	/** Created with the document table (Velox_Builder::install). */
	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$t = self::table();
		dbDelta(
			"CREATE TABLE {$t} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				doc_id BIGINT UNSIGNED NOT NULL,
				kind VARCHAR(20) NOT NULL DEFAULT 'save',
				title VARCHAR(190) NOT NULL DEFAULT '',
				data LONGTEXT NULL,
				hash CHAR(32) NOT NULL DEFAULT '',
				user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
				created DATETIME NULL,
				PRIMARY KEY  (id),
				KEY doc_id (doc_id)
			) " . $wpdb->get_charset_collate() . ';'
		);
	}

	public static function keep() {
		return max( 5, (int) apply_filters( 'velox_builder_revisions_keep', 40 ) );
	}

	/**
	 * Keep a version of a document.
	 *
	 * @param int    $doc_id Document id.
	 * @param string $kind   save | publish | restore.
	 * @param string $data   The document JSON.
	 * @param string $title  Document title at that moment.
	 */
	public static function record( $doc_id, $kind, $data, $title = '' ) {
		$doc_id = (int) $doc_id;
		if ( $doc_id < 1 ) {
			return 0;
		}
		return self::write( $doc_id, $kind, $data, $title );
	}

	/** Store a version (id 0 holds the site-wide design system). */
	private static function write( $doc_id, $kind, $data, $title = '', $user = null ) {
		global $wpdb;
		if ( ! is_string( $data ) || '' === $data ) {
			return 0;
		}
		$t    = self::table();
		$kind = in_array( $kind, array( 'save', 'publish', 'restore', 'import', 'ai' ), true ) ? $kind : 'save';
		$hash = md5( self::canonical( $data ) );
		$user = null === $user ? get_current_user_id() : (int) $user;
		$now  = current_time( 'mysql' );
		$last = $wpdb->get_row( $wpdb->prepare( "SELECT id, kind, hash, user_id, created FROM {$t} WHERE doc_id = %d ORDER BY id DESC LIMIT 1", $doc_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $last && $last['hash'] === $hash ) {
			// Nothing changed. A publish of the same content still marks that version as published.
			if ( 'publish' === $kind && 'save' === $last['kind'] ) {
				$wpdb->update( $t, array( 'kind' => 'publish', 'created' => $now ), array( 'id' => (int) $last['id'] ) );
			}
			return (int) $last['id'];
		}
		if ( 'save' === $kind && $last && 'save' === $last['kind'] && (int) $last['user_id'] === $user
			&& ( strtotime( $now ) - strtotime( (string) $last['created'] ) ) < self::FOLD_SECONDS ) {
			$wpdb->update( $t, array( 'data' => $data, 'hash' => $hash, 'title' => $title, 'created' => $now ), array( 'id' => (int) $last['id'] ) );
			return (int) $last['id'];
		}
		$wpdb->insert( $t, array( 'doc_id' => $doc_id, 'kind' => $kind, 'title' => $title, 'data' => $data, 'hash' => $hash, 'user_id' => $user, 'created' => $now ) );
		$id = (int) $wpdb->insert_id;
		// Prune: keep the newest N.
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$t} WHERE doc_id = %d ORDER BY id DESC LIMIT 1000 OFFSET %d", $doc_id, self::keep() ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $ids ) {
			$wpdb->query( "DELETE FROM {$t} WHERE id IN (" . implode( ',', array_map( 'intval', $ids ) ) . ')' ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		return $id;
	}

	/** Selection, screen size and the like are not content: they must not make a new version. */
	private static function canonical( $data ) {
		$m = json_decode( $data, true );
		if ( ! is_array( $m ) ) {
			return $data;
		}
		unset( $m['selection'], $m['activeClass'], $m['breakpoint'], $m['state'], $m['changedClasses'] );
		return wp_json_encode( $m );
	}

	public static function forget( $doc_id ) {
		global $wpdb;
		$wpdb->delete( self::table(), array( 'doc_id' => (int) $doc_id ), array( '%d' ) );
	}

	/* ------------------------------------------------------------ what changed */

	/** Element id → a signature of everything that makes it look the way it does. */
	private static function signatures( $model ) {
		$out     = array();
		$content = is_array( $model['content'] ?? null ) ? $model['content'] : array();
		$walk    = function ( $nodes, $parent ) use ( &$walk, &$out, $content ) {
			foreach ( (array) $nodes as $i => $n ) {
				if ( ! is_array( $n ) || empty( $n['id'] ) ) {
					continue;
				}
				$kids = array();
				foreach ( (array) ( $n['children'] ?? array() ) as $k ) {
					$kids[] = $k['id'] ?? '';
				}
				$copy = $n;
				unset( $copy['children'] );
				$out[ $n['id'] ] = md5( wp_json_encode( array( $copy, $content[ $n['id'] ] ?? null, $kids, $parent, $i ) ) );
				$walk( $n['children'] ?? array(), $n['id'] );
			}
		};
		$walk( $model['tree'] ?? array(), '' );
		return $out;
	}

	/** Elements added, changed and removed going from $a to $b. */
	public static function diff( $a, $b ) {
		$sa = self::signatures( $a );
		$sb = self::signatures( $b );
		$d  = array( 'added' => array(), 'changed' => array(), 'removed' => array() );
		foreach ( $sb as $id => $sig ) {
			if ( ! isset( $sa[ $id ] ) ) {
				$d['added'][] = $id;
			} elseif ( $sa[ $id ] !== $sig ) {
				$d['changed'][] = $id;
			}
		}
		foreach ( $sa as $id => $sig ) {
			if ( ! isset( $sb[ $id ] ) ) {
				$d['removed'][] = $id;
			}
		}
		// Class rules changed in this document count as a change too.
		$ca = wp_json_encode( $a['classes'] ?? array() );
		$cb = wp_json_encode( $b['classes'] ?? array() );
		$d['styles'] = $ca !== $cb;
		return $d;
	}

	/* ------------------------------------------------------------ AJAX */

	private static function doc_row( $doc_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT id, kind, title, status, data, draft FROM ' . Velox_Builder::table() . ' WHERE id = %d', (int) $doc_id ), ARRAY_A );
	}

	public static function ajax_list() {
		global $wpdb;
		$doc = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- router checks the nonce
		if ( ! $doc || ! self::doc_row( $doc ) || ( class_exists( 'Velox_Builder_Roles' ) && ! Velox_Builder_Roles::can_edit_doc( $doc ) ) ) {
			wp_send_json_success( array( 'items' => array() ) );
		}
		$t    = self::table();
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, kind, title, data, user_id, created FROM {$t} WHERE doc_id = %d ORDER BY id DESC LIMIT 100", $doc ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$out  = array();
		$prev = null;
		// Oldest first to describe each version against the one before it.
		$rows = array_reverse( (array) $rows );
		foreach ( $rows as $r ) {
			$m    = json_decode( $r['data'], true );
			$m    = is_array( $m ) ? $m : array();
			$what = '';
			if ( $prev ) {
				$d     = self::diff( $prev, $m );
				$parts = array();
				if ( $d['added'] ) {
					/* translators: %d: number of elements. */
					$parts[] = sprintf( __( '%d added', 'velox' ), count( $d['added'] ) );
				}
				if ( $d['changed'] ) {
					/* translators: %d: number of elements. */
					$parts[] = sprintf( __( '%d changed', 'velox' ), count( $d['changed'] ) );
				}
				if ( $d['removed'] ) {
					/* translators: %d: number of elements. */
					$parts[] = sprintf( __( '%d removed', 'velox' ), count( $d['removed'] ) );
				}
				if ( $d['styles'] ) {
					$parts[] = __( 'styles', 'velox' );
				}
				$what = $parts ? implode( ', ', $parts ) : __( 'page settings', 'velox' );
			} else {
				$what = __( 'first saved version', 'velox' );
			}
			$u     = $r['user_id'] ? get_userdata( (int) $r['user_id'] ) : null;
			$out[] = array(
				'id'      => (int) $r['id'],
				'kind'    => $r['kind'],
				'title'   => $r['title'],
				'user'    => $u ? $u->display_name : '',
				'created' => $r['created'],
				'ago'     => sprintf( /* translators: %s: time difference. */ __( '%s ago', 'velox' ), human_time_diff( strtotime( $r['created'] ), current_time( 'timestamp' ) ) ),
				'when'    => mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $r['created'] ),
				'what'    => $what,
				'count'   => count( self::signatures( $m ) ),
			);
			$prev = $m;
		}
		wp_send_json_success( array( 'items' => array_reverse( $out ) ) );
	}

	public static function ajax_get() {
		global $wpdb;
		$id  = isset( $_POST['rev'] ) ? absint( $_POST['rev'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$row = $id ? $wpdb->get_row( $wpdb->prepare( 'SELECT id, doc_id, kind, title, data, created FROM ' . self::table() . ' WHERE id = %d', $id ), ARRAY_A ) : null;
		if ( ! $row || ( class_exists( 'Velox_Builder_Roles' ) && ! Velox_Builder_Roles::can_edit_doc( (int) $row['doc_id'] ) ) ) {
			wp_send_json_error( array( 'message' => __( 'That version no longer exists.', 'velox' ) ), 404 );
		}
		$model = json_decode( $row['data'] );
		if ( ! is_object( $model ) ) {
			wp_send_json_error( array( 'message' => __( 'That version cannot be read.', 'velox' ) ), 400 );
		}
		wp_send_json_success( array(
			'id'    => (int) $row['id'],
			'doc'   => (int) $row['doc_id'],
			'kind'  => $row['kind'],
			'title' => $row['title'],
			'when'  => mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $row['created'] ),
			'model' => $model,
		) );
	}
	/* ================================================== design system history
	 * Classes, design tokens and global styles are site-wide and change from
	 * many places (class editor, publishing a page, the token editor, global
	 * styles, import). Every change to their options is caught here and kept as
	 * one version per request, under document id 0. */

	const DESIGN_OPTS = array( 'velox_builder_classes' => 'classes', 'velox_builder_tokens' => 'tokens', 'velox_builder_global_styles' => 'styles' );
	private static $design_dirty = false;
	private static $design_quiet = false;

	public static function init() {
		foreach ( array_keys( self::DESIGN_OPTS ) as $o ) {
			add_action( 'update_option_' . $o, array( __CLASS__, 'design_changed' ), 10, 3 );
			add_action( 'add_option_' . $o, function ( $option, $value ) { self::design_changed( null, $value, $option ); }, 10, 2 );
		}
	}

	/** The design system as it is now. */
	public static function design_snapshot() {
		$out = array();
		foreach ( self::DESIGN_OPTS as $o => $k ) {
			$out[ $k ] = get_option( $o, null );
		}
		return $out;
	}

	public static function design_changed( $old, $new, $option ) {
		if ( self::$design_quiet || ! isset( self::DESIGN_OPTS[ $option ] ) ) {
			return;
		}
		global $wpdb;
		if ( ! self::$design_dirty ) {
			self::$design_dirty = true;
			// The very first change keeps what was there before, so it can be undone too.
			if ( ! $wpdb->get_var( 'SELECT id FROM ' . self::table() . ' WHERE doc_id = 0 LIMIT 1' ) ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$before = self::design_snapshot();
				$before[ self::DESIGN_OPTS[ $option ] ] = $old;
				// As nobody, so the change that follows is not folded into it.
				self::write( 0, 'save', wp_json_encode( $before ), '', 0 );
			}
			add_action( 'shutdown', array( __CLASS__, 'design_flush' ) );
		}
	}

	public static function design_flush() {
		self::write( 0, 'save', wp_json_encode( self::design_snapshot() ) );
	}

	/** What changed between two design snapshots, in words. */
	private static function design_diff( $new, $old ) {
		$out = array();
		$nc  = is_array( $new['classes'] ?? null ) ? $new['classes'] : array();
		$oc  = is_array( $old['classes'] ?? null ) ? $old['classes'] : array();
		$names = array();
		foreach ( array_unique( array_merge( array_keys( $nc ), array_keys( $oc ) ) ) as $c ) {
			if ( wp_json_encode( $nc[ $c ] ?? null ) !== wp_json_encode( $oc[ $c ] ?? null ) ) {
				$names[] = $c;
			}
		}
		if ( $names ) {
			/* translators: %s: class names. */
			$out[] = sprintf( __( 'Classes: %s', 'velox' ), implode( ', ', array_slice( $names, 0, 6 ) ) . ( count( $names ) > 6 ? ' …' : '' ) );
		}
		if ( wp_json_encode( $new['tokens'] ?? null ) !== wp_json_encode( $old['tokens'] ?? null ) ) {
			$out[] = __( 'Design tokens', 'velox' );
		}
		if ( wp_json_encode( $new['styles'] ?? null ) !== wp_json_encode( $old['styles'] ?? null ) ) {
			$out[] = __( 'Global styles', 'velox' );
		}
		return $out;
	}

	public static function ajax_design_list() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Not allowed.', 'velox' ) ), 403 );
		}
		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT id, kind, data, user_id, created FROM ' . self::table() . ' WHERE doc_id = 0 ORDER BY id DESC LIMIT 41', ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$cur  = wp_json_encode( self::design_snapshot() );
		$out  = array();
		$seen = false;
		foreach ( (array) $rows as $i => $r ) {
			if ( 40 === $i ) {
				break;
			}
			// Only the newest version that matches today is "current".
			$is_cur = ! $seen && md5( self::canonical( $cur ) ) === md5( self::canonical( (string) $r['data'] ) );
			$seen   = $seen || $is_cur;
			$data  = json_decode( (string) $r['data'], true );
			$older = isset( $rows[ $i + 1 ] ) ? json_decode( (string) $rows[ $i + 1 ]['data'], true ) : null;
			$u     = get_userdata( (int) $r['user_id'] );
			$out[] = array(
				'id'      => (int) $r['id'],
				'kind'    => $r['kind'],
				'when'    => mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $r['created'] ),
				'user'    => $u ? $u->display_name : '',
				'what'    => is_array( $older ) ? self::design_diff( (array) $data, $older ) : array( __( 'Starting point', 'velox' ) ),
				'current' => $is_cur,
			);
		}
		wp_send_json_success( array( 'versions' => $out ) );
	}

	public static function ajax_design_restore() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Not allowed.', 'velox' ) ), 403 );
		}
		global $wpdb;
		$id  = isset( $_POST['rev'] ) ? absint( $_POST['rev'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$row = $id ? $wpdb->get_row( $wpdb->prepare( 'SELECT data FROM ' . self::table() . ' WHERE id = %d AND doc_id = 0', $id ), ARRAY_A ) : null;
		$data = $row ? json_decode( (string) $row['data'], true ) : null;
		if ( ! is_array( $data ) ) {
			wp_send_json_error( array( 'message' => __( 'That version no longer exists.', 'velox' ) ), 404 );
		}
		// What is there now stays one click away.
		self::write( 0, 'save', wp_json_encode( self::design_snapshot() ) );
		self::$design_quiet = true;
		foreach ( self::DESIGN_OPTS as $o => $k ) {
			if ( ! array_key_exists( $k, $data ) ) {
				continue;
			}
			if ( null === $data[ $k ] ) {
				delete_option( $o );
			} else {
				update_option( $o, $data[ $k ], 'velox_builder_classes' === $o ? false : null );
			}
		}
		self::$design_quiet = false;
		self::write( 0, 'restore', wp_json_encode( self::design_snapshot() ) );
		if ( class_exists( 'Velox_Builder' ) ) {
			Velox_Builder::purge_cache_for();
		}
		wp_send_json_success( array( 'restored' => $id ) );
	}
}

Velox_Builder_Revisions::init();
