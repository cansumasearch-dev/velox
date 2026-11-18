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
		global $wpdb;
		$doc_id = (int) $doc_id;
		if ( ! $doc_id || ! is_string( $data ) || '' === $data ) {
			return 0;
		}
		$t    = self::table();
		$kind = in_array( $kind, array( 'save', 'publish', 'restore' ), true ) ? $kind : 'save';
		$hash = md5( self::canonical( $data ) );
		$user = get_current_user_id();
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
}
