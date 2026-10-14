<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Velox Security scan — putting things right.
 *
 *  - RESTORE: a changed core or WordPress.org plugin file is replaced with the
 *    original, downloaded from WordPress.org's SVN and only written if its
 *    checksum matches the published one. The changed file is kept in
 *    quarantine first, so even a restore can be undone.
 *  - QUARANTINE: a file that shouldn't be there is moved into a sealed folder
 *    under a name that can't run (.velox-quarantine), never deleted on a
 *    guess. One click puts it back if it was needed after all.
 *  - CLEAN POST: injected scripts / hidden iframes are cut out of a post; the
 *    old content stays as a revision.
 *  - SIGN EVERYONE OUT: after a hack, every stolen login cookie is worthless.
 *
 * Every action takes the index of a finding in the stored report — never a path
 * from the browser — and re-checks the path stays inside the site.
 *
 * @package Velox
 */
class Velox_Site_Scan_Fix {

	const QUARANTINE = 'velox_quarantine';      // list of quarantined files
	const Q_TOKEN    = 'velox_quarantine_token';

	/** Never moved: without these the site (and this screen) can't load. */
	private static $untouchable = array( 'wp-config.php', 'wp-settings.php', 'wp-load.php', 'wp-blog-header.php' );

	/* ------------------------------------------------------------ dispatch */

	/**
	 * Apply the fix a finding offers.
	 *
	 * @return array|WP_Error
	 */
	public static function apply( $index ) {
		$last = Velox_Site_Scan::last();
		$f    = isset( $last['findings'][ $index ] ) ? $last['findings'][ $index ] : null;
		if ( ! $f ) {
			return new WP_Error( 'gone', __( 'That finding is no longer in the report. Scan again.', 'velox' ) );
		}
		if ( ! empty( $f['resolved'] ) ) {
			return new WP_Error( 'done', __( 'This one has already been dealt with.', 'velox' ) );
		}
		$fix = isset( $f['meta']['fix'] ) ? $f['meta']['fix'] : '';
		switch ( $fix ) {
			case 'quarantine':
				$res = self::quarantine_finding( $f );
				break;
			case 'restore_core':
				$res = self::restore_core( $f, isset( $last['version'] ) ? $last['version'] : get_bloginfo( 'version' ) );
				break;
			case 'restore_plugin':
				$res = self::restore_plugin( $f );
				break;
			case 'clean_post':
				$res = self::clean_post( (int) $f['meta']['post_id'] );
				break;
			default:
				return new WP_Error( 'manual', __( 'This one needs to be fixed by hand — follow the steps shown with it.', 'velox' ) );
		}
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		Velox_Site_Scan::resolve( $index, $fix );
		$res['levels'] = Velox_Site_Scan::last()['levels'];
		return $res;
	}

	/* ------------------------------------------------------------ paths */

	/** Absolute path for a file finding, or null if it isn't a file inside this site. */
	private static function abs_for( $f ) {
		$path = (string) $f['path'];
		if ( 0 === strpos( $path, 'uploads/' ) ) {
			$up  = wp_upload_dir();
			$abs = trailingslashit( $up['basedir'] ) . substr( $path, 8 );
		} else {
			$abs = ABSPATH . $path;
		}
		return self::inside_site( $abs ) ? $abs : null;
	}

	/** Real path under ABSPATH (or the uploads folder, wherever that lives) — no ../ tricks. */
	private static function inside_site( $abs ) {
		if ( false !== strpos( $abs, '..' ) ) {
			return false;
		}
		$dir  = realpath( dirname( $abs ) );
		$up   = wp_upload_dir();
		$ok   = array( realpath( ABSPATH ), realpath( $up['basedir'] ) );
		foreach ( array_filter( $ok ) as $root ) {
			if ( $dir && 0 === strpos( wp_normalize_path( $dir ) . '/', trailingslashit( wp_normalize_path( $root ) ) ) ) {
				return true;
			}
		}
		return false;
	}

	/* ------------------------------------------------------------ quarantine */

	public static function quarantine_dir() {
		$token = get_option( self::Q_TOKEN );
		if ( ! $token ) {
			$token = strtolower( wp_generate_password( 16, false ) );
			update_option( self::Q_TOKEN, $token, false );
		}
		$up  = wp_upload_dir();
		$dir = trailingslashit( $up['basedir'] ) . 'velox-quarantine-' . $token;
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		if ( ! file_exists( $dir . '/.htaccess' ) ) {
			@file_put_contents( $dir . '/.htaccess', "# Velox quarantine — nothing in here is ever served or run.\n<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n\tOrder allow,deny\n\tDeny from all\n</IfModule>\n" ); // phpcs:ignore
		}
		if ( ! file_exists( $dir . '/index.php' ) ) {
			@file_put_contents( $dir . '/index.php', "<?php\n// Silence is golden.\n" ); // phpcs:ignore
		}
		return $dir;
	}

	public static function quarantined() {
		$q = get_option( self::QUARANTINE, array() );
		return is_array( $q ) ? $q : array();
	}

	/**
	 * Move (or with $keep, copy) a file into quarantine under a name that can't
	 * execute, and remember where it came from.
	 *
	 * @return array|WP_Error The quarantine record.
	 */
	private static function stash( $abs, $label, $why, $keep = false ) {
		$q  = self::quarantined();
		$id = substr( md5( $abs . microtime() ), 0, 12 );
		$to = self::quarantine_dir() . '/' . $id . '.velox-quarantine';
		$ok = $keep ? @copy( $abs, $to ) : ( @rename( $abs, $to ) || ( @copy( $abs, $to ) && @unlink( $abs ) ) ); // phpcs:ignore
		if ( ! $ok ) {
			return new WP_Error( 'move', __( 'Could not move the file — the server did not allow it. Ask your host to remove it, or delete it over FTP.', 'velox' ) );
		}
		$rec = array(
			'id'    => $id,
			'path'  => $label,
			'abs'   => $abs,
			'why'   => $why,
			'size'  => (int) @filesize( $to ), // phpcs:ignore
			'time'  => time(),
			'kept'  => (bool) $keep,
		);
		array_unshift( $q, $rec );
		update_option( self::QUARANTINE, $q, false );
		return $rec;
	}

	private static function quarantine_finding( $f ) {
		$abs = self::abs_for( $f );
		if ( ! $abs || ! is_file( $abs ) ) {
			return new WP_Error( 'missing', __( 'The file is no longer there.', 'velox' ) );
		}
		if ( in_array( strtolower( basename( $abs ) ), self::$untouchable, true ) && dirname( wp_normalize_path( $abs ) ) === untrailingslashit( wp_normalize_path( ABSPATH ) ) ) {
			return new WP_Error( 'untouchable', __( 'This file is needed for the site to start, so Velox won\'t move it. Open it over FTP or your host\'s file manager and remove the code shown here.', 'velox' ) );
		}
		$rec = self::stash( $abs, $f['path'], $f['note'] );
		if ( is_wp_error( $rec ) ) {
			return $rec;
		}
		return array( 'message' => __( 'Moved to quarantine. It can no longer run — and you can put it back below if something stops working.', 'velox' ) );
	}

	public static function restore_quarantined( $id ) {
		$q = self::quarantined();
		foreach ( $q as $i => $rec ) {
			if ( $rec['id'] !== $id ) {
				continue;
			}
			$from = self::quarantine_dir() . '/' . $id . '.velox-quarantine';
			if ( ! is_file( $from ) ) {
				return new WP_Error( 'gone', __( 'The quarantined copy is gone.', 'velox' ) );
			}
			if ( ! self::inside_site( $rec['abs'] ) ) {
				return new WP_Error( 'path', __( 'The original location is not inside this site any more.', 'velox' ) );
			}
			// A moved-out file never overwrites something that has appeared in its
			// place since. A "kept" copy (the live file was replaced by the clean
			// original) is meant to swap back over it — e.g. a core file you had
			// changed on purpose.
			if ( empty( $rec['kept'] ) && file_exists( $rec['abs'] ) ) {
				return new WP_Error( 'exists', __( 'A file with that name is back in that place already, so nothing was overwritten.', 'velox' ) );
			}
			wp_mkdir_p( dirname( $rec['abs'] ) );
			if ( ! @copy( $from, $rec['abs'] ) ) { // phpcs:ignore
				return new WP_Error( 'move', __( 'Could not put the file back — the server did not allow it.', 'velox' ) );
			}
			@unlink( $from ); // phpcs:ignore
			unset( $q[ $i ] );
			update_option( self::QUARANTINE, array_values( $q ), false );
			return array( 'message' => __( 'Put back where it was.', 'velox' ) );
		}
		return new WP_Error( 'gone', __( 'Not found in quarantine.', 'velox' ) );
	}

	public static function delete_quarantined( $id ) {
		$q = self::quarantined();
		foreach ( $q as $i => $rec ) {
			if ( $rec['id'] === $id ) {
				@unlink( self::quarantine_dir() . '/' . $id . '.velox-quarantine' ); // phpcs:ignore
				unset( $q[ $i ] );
				update_option( self::QUARANTINE, array_values( $q ), false );
				return array( 'message' => __( 'Deleted for good.', 'velox' ) );
			}
		}
		return new WP_Error( 'gone', __( 'Not found in quarantine.', 'velox' ) );
	}

	/* ------------------------------------------------------------ restore */

	/** Download a URL's body, or null. */
	private static function fetch( $url ) {
		$res = wp_remote_get( $url, array( 'timeout' => 25 ) );
		if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
			return null;
		}
		return (string) wp_remote_retrieve_body( $res );
	}

	/** Write a verified clean copy over a file, keeping the old one in quarantine. */
	private static function replace_with( $abs, $label, $clean, $why ) {
		if ( file_exists( $abs ) ) {
			$rec = self::stash( $abs, $label, $why, true );
			if ( is_wp_error( $rec ) ) {
				return $rec;
			}
		}
		wp_mkdir_p( dirname( $abs ) );
		$tmp = $abs . '.velox-' . wp_generate_password( 6, false );
		if ( false === @file_put_contents( $tmp, $clean ) || ! @rename( $tmp, $abs ) ) { // phpcs:ignore
			@unlink( $tmp ); // phpcs:ignore
			return new WP_Error( 'write', __( 'Could not write the clean file — the server did not allow it.', 'velox' ) );
		}
		return array( 'message' => __( 'Replaced with the verified original from WordPress.org. The changed file was kept in quarantine.', 'velox' ) );
	}

	private static function restore_core( $f, $version ) {
		$rel = (string) $f['path'];
		$abs = ABSPATH . $rel;
		if ( ! self::inside_site( $abs ) ) {
			return new WP_Error( 'path', __( 'Unexpected path.', 'velox' ) );
		}
		$clean = self::fetch( 'https://core.svn.wordpress.org/tags/' . rawurlencode( $version ) . '/' . str_replace( '%2F', '/', rawurlencode( $rel ) ) );
		if ( null === $clean ) {
			return new WP_Error( 'download', __( 'Could not download the original from WordPress.org. Try again later, or reinstall WordPress from Dashboard → Updates.', 'velox' ) );
		}
		$md5  = md5( $clean );
		$want = isset( $f['meta']['md5'] ) ? (string) $f['meta']['md5'] : '';
		if ( $md5 !== $want ) {
			// Localised builds publish their own checksums; the SVN copy is the English one.
			$en = Velox_Site_Scan::fetch_checksums( $version, 'en_US' );
			if ( ! isset( $en[ $rel ] ) || $en[ $rel ] !== $md5 ) {
				return new WP_Error( 'verify', __( 'The downloaded file did not match WordPress.org\'s checksum, so it was not used. Reinstall WordPress from Dashboard → Updates instead.', 'velox' ) );
			}
		}
		return self::replace_with( $abs, $rel, $clean, $f['note'] );
	}

	private static function restore_plugin( $f ) {
		$m    = $f['meta'];
		$abs  = WP_PLUGIN_DIR . '/' . $m['slug'] . '/' . $m['rel'];
		if ( ! self::inside_site( $abs ) || false !== strpos( $m['slug'] . $m['rel'], '..' ) ) {
			return new WP_Error( 'path', __( 'Unexpected path.', 'velox' ) );
		}
		$want = (array) $m['md5'];
		$enc  = str_replace( '%2F', '/', rawurlencode( $m['rel'] ) );
		foreach ( array( 'tags/' . rawurlencode( $m['version'] ), 'trunk' ) as $where ) {
			$clean = self::fetch( 'https://plugins.svn.wordpress.org/' . rawurlencode( $m['slug'] ) . '/' . $where . '/' . $enc );
			if ( null !== $clean && in_array( md5( $clean ), $want, true ) ) {
				return self::replace_with( $abs, $f['path'], $clean, $f['note'] );
			}
		}
		return new WP_Error( 'verify', __( 'Could not get a verified copy of this file from WordPress.org. Delete the plugin and install it again from Plugins → Add New — that replaces every file.', 'velox' ) );
	}

	/* ------------------------------------------------------------ posts */

	/** Cut injected scripts / hidden iframes out of a post. The old version stays as a revision. */
	public static function clean_post( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'gone', __( 'That post no longer exists.', 'velox' ) );
		}
		$html  = (string) $post->post_content;
		$clean = preg_replace_callback(
			'#<script\b[^>]*>(.*?)</script\s*>#is',
			function ( $m ) {
				return preg_match( '/\beval\s*\(|String\.fromCharCode\s*\(.{0,40}\d+\s*,\s*\d+|document\.write\s*\(\s*unescape|atob\s*\(\s*[\'"][A-Za-z0-9+\/=]{40,}/is', $m[1] ) ? '' : $m[0];
			},
			$html
		);
		$clean = preg_replace( '#<iframe\b[^>]*(?:width\s*=\s*[\'"]?[01]\b|height\s*=\s*[\'"]?[01]\b|display\s*:\s*none|visibility\s*:\s*hidden)[^>]*>.*?</iframe\s*>#is', '', (string) $clean );
		if ( null === $clean || $clean === $html ) {
			return new WP_Error( 'nothing', __( 'Nothing could be removed automatically — open the post and delete the code shown here by hand.', 'velox' ) );
		}
		$ok = wp_update_post( array( 'ID' => $post_id, 'post_content' => $clean ), true );
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		return array( 'message' => __( 'Injected code removed. The previous version is saved as a revision.', 'velox' ) );
	}

	/* ------------------------------------------------------------ sessions */

	/** Sign every user out everywhere — except this browser, so you stay in. */
	public static function logout_all() {
		$ids   = get_users( array( 'fields' => 'ID', 'meta_key' => 'session_tokens' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		$me    = get_current_user_id();
		$count = 0;
		foreach ( (array) $ids as $id ) {
			$m = WP_Session_Tokens::get_instance( (int) $id );
			if ( (int) $id === $me ) {
				$m->destroy_others( wp_get_session_token() );
			} else {
				$m->destroy_all();
			}
			$count++;
		}
		return array( 'message' => sprintf(
			/* translators: %d: number of accounts */
			__( 'Signed out %d account(s) everywhere — every stolen login is now useless. You stay signed in here.', 'velox' ),
			$count
		) );
	}
}
