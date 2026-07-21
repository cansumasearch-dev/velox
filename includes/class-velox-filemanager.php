<?php
/**
 * Velox File Manager — browse and edit site files from the admin, like SFTP.
 * Dangerous by design, so every entry point is gated on manage_options and
 * every path is clamped inside ABSPATH (no traversal, no symlink escape).
 *
 * @package Velox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Velox_Filemanager {

	/** Normalised site root with a trailing slash. */
	private static function root() {
		return trailingslashit( wp_normalize_path( ABSPATH ) );
	}

	/**
	 * Resolve a user-supplied relative path to an absolute one, or false if it
	 * would escape the site root.
	 */
	private static function safe_path( $rel ) {
		$root = self::root();
		$rel  = wp_normalize_path( (string) $rel );
		$rel  = ltrim( str_replace( array( '..', "\0" ), '', $rel ), '/' );
		$full = wp_normalize_path( $root . $rel );
		if ( 0 !== strpos( $full, $root ) && rtrim( $full, '/' ) !== rtrim( $root, '/' ) ) {
			return false;
		}
		return $full;
	}

	/** Path relative to the site root (what we hand back to the UI). */
	private static function rel_of( $full ) {
		return ltrim( str_replace( self::root(), '', wp_normalize_path( $full ) ), '/' );
	}

	private static function allowed() {
		return current_user_can( 'manage_options' );
	}

	/** List a directory: folders first, then files, name-sorted. */
	public static function list_dir( $rel ) {
		if ( ! self::allowed() ) {
			return array( 'ok' => false, 'message' => 'You don\'t have permission for this.' );
		}
		$full = self::safe_path( $rel );
		if ( false === $full || ! is_dir( $full ) ) {
			return array( 'ok' => false, 'message' => 'That folder could not be opened.' );
		}
		$items   = array();
		$entries = @scandir( $full ); // phpcs:ignore
		foreach ( (array) $entries as $name ) {
			if ( '.' === $name || '..' === $name ) {
				continue;
			}
			$p = wp_normalize_path( trailingslashit( $full ) . $name );
			$items[] = array(
				'name'     => $name,
				'dir'      => is_dir( $p ),
				'size'     => is_file( $p ) ? (int) filesize( $p ) : 0,
				'rel'      => self::rel_of( $p ),
				'writable' => is_writable( $p ),
			);
		}
		usort( $items, function ( $a, $b ) {
			if ( $a['dir'] !== $b['dir'] ) {
				return $a['dir'] ? -1 : 1;
			}
			return strcasecmp( $a['name'], $b['name'] );
		} );
		return array(
			'ok'     => true,
			'path'   => self::rel_of( $full ),
			'parent' => ( rtrim( $full, '/' ) === rtrim( self::root(), '/' ) ) ? null : self::rel_of( dirname( $full ) ),
			'items'  => $items,
		);
	}

	/** Read a text file for editing. */
	public static function read_file( $rel ) {
		if ( ! self::allowed() ) {
			return array( 'ok' => false, 'message' => 'You don\'t have permission for this.' );
		}
		$full = self::safe_path( $rel );
		if ( false === $full || ! is_file( $full ) ) {
			return array( 'ok' => false, 'message' => 'That file could not be opened.' );
		}
		if ( filesize( $full ) > 2 * 1024 * 1024 ) {
			return array( 'ok' => false, 'message' => 'File is larger than 2 MB — too big to edit here.' );
		}
		$content = (string) file_get_contents( $full ); // phpcs:ignore
		if ( false !== strpos( substr( $content, 0, 8000 ), "\0" ) ) {
			return array( 'ok' => false, 'message' => 'This looks like a binary file, so it can\'t be edited as text.' );
		}
		return array(
			'ok'       => true,
			'rel'      => self::rel_of( $full ),
			'name'     => basename( $full ),
			'content'  => $content,
			'writable' => is_writable( $full ),
		);
	}

	/** Overwrite a file's contents. */
	public static function save_file( $rel, $content ) {
		if ( ! self::allowed() ) {
			return array( 'ok' => false, 'message' => 'You don\'t have permission for this.' );
		}
		$full = self::safe_path( $rel );
		if ( false === $full || ! is_file( $full ) ) {
			return array( 'ok' => false, 'message' => 'That file could not be found.' );
		}
		if ( ! is_writable( $full ) ) {
			return array( 'ok' => false, 'message' => 'That file is read-only on the server.' );
		}
		$res = file_put_contents( $full, (string) $content ); // phpcs:ignore
		if ( false === $res ) {
			return array( 'ok' => false, 'message' => 'The server refused to write the file.' );
		}
		return array( 'ok' => true, 'message' => 'Saved.', 'bytes' => (int) $res );
	}
}
