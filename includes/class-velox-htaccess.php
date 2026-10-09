<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * .htaccess editor (Performance → .htaccess, risky mode only).
 *
 * A broken .htaccess takes the whole site down — wp-admin included — so editing
 * is guarded three ways:
 *  1. Locked by default. Unlocking opens a short window (UNLOCK_SECONDS) for the
 *     current admin only; it's enforced here on the server, not just in the UI.
 *  2. Every save first stores the previous version (last BACKUPS kept, in a
 *     non-autoloaded option — never a web-reachable file).
 *  3. After writing, Velox loads the home page; a 500 means the new rules broke
 *     the server, so the previous version is put back automatically.
 */
final class Velox_Htaccess {

	const UNLOCK_SECONDS = 600; // 10 minutes
	const BACKUP_OPTION  = 'velox_htaccess_backups';
	const BACKUPS        = 5;
	const MAX_BYTES      = 262144; // 256 KB — real .htaccess files are a few KB

	/** Absolute path of the site's .htaccess (it may not exist yet). */
	public static function path() {
		if ( ! function_exists( 'get_home_path' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		return trailingslashit( get_home_path() ) . '.htaccess';
	}

	public static function read() {
		$p = self::path();
		return is_readable( $p ) ? (string) file_get_contents( $p ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions
	}

	/** Can this server have its .htaccess edited from here at all? '' = yes, else the reason. */
	public static function blocked_reason() {
		if ( defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT ) {
			return __( 'File editing is switched off on this site (DISALLOW_FILE_EDIT in wp-config.php).', 'velox' );
		}
		// One .htaccess serves the whole network — a single sub-site's admin must not change it.
		if ( function_exists( 'is_multisite' ) && is_multisite() && ! is_super_admin() ) {
			return __( 'On a multisite network only a network administrator can edit .htaccess.', 'velox' );
		}
		$p = self::path();
		if ( file_exists( $p ) ? ! is_writable( $p ) : ! is_writable( dirname( $p ) ) ) {
			return __( 'WordPress is not allowed to write the .htaccess file on this server (file permissions).', 'velox' );
		}
		return '';
	}

	/** Does the web server read .htaccess at all? (nginx alone ignores it.) */
	public static function server_reads_htaccess() {
		$sw = isset( $_SERVER['SERVER_SOFTWARE'] ) ? strtolower( (string) $_SERVER['SERVER_SOFTWARE'] ) : '';
		return '' === $sw || false !== strpos( $sw, 'apache' ) || false !== strpos( $sw, 'litespeed' );
	}

	/* ------------------------------------------------------------------ lock */

	private static function lock_key() {
		return 'velox_htaccess_unlock_' . get_current_user_id();
	}

	/** Seconds left in the current user's unlock window (0 = locked). */
	public static function seconds_left() {
		$until = (int) get_transient( self::lock_key() );
		return max( 0, $until - time() );
	}

	public static function unlock() {
		$until = time() + self::UNLOCK_SECONDS;
		set_transient( self::lock_key(), $until, self::UNLOCK_SECONDS );
		return array( 'seconds_left' => self::UNLOCK_SECONDS, 'content' => self::read() );
	}

	public static function lock() {
		delete_transient( self::lock_key() );
		return array( 'seconds_left' => 0 );
	}

	/* -------------------------------------------------------------- backups */

	public static function backups() {
		$b = get_option( self::BACKUP_OPTION, array() );
		return is_array( $b ) ? $b : array();
	}

	private static function push_backup( $content ) {
		$b = self::backups();
		array_unshift( $b, array( 'time' => time(), 'user' => get_current_user_id(), 'content' => (string) $content ) );
		update_option( self::BACKUP_OPTION, array_slice( $b, 0, self::BACKUPS ), false );
	}

	/* ----------------------------------------------------------------- save */

	/**
	 * Write new .htaccess content (unlock window + risky mode required).
	 *
	 * @return array|WP_Error
	 */
	public static function save( $content ) {
		$guard = self::guard();
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}
		$content = str_replace( "\r\n", "\n", (string) $content );
		if ( strlen( $content ) > self::MAX_BYTES ) {
			return new WP_Error( 'too_big', __( 'That is far too large for an .htaccess file — nothing was saved.', 'velox' ) );
		}
		$before = self::read();
		if ( $before === $content ) {
			return array( 'message' => __( 'No changes to save.', 'velox' ), 'changed' => false );
		}
		// Baseline first: if the home page loads now and fails afterwards (500, or a
		// 403/404 from an over-eager deny rule), the new rules are to blame.
		$baseline = self::health_check();
		self::push_backup( $before );
		if ( ! self::write( $content ) ) {
			return new WP_Error( 'write_failed', __( 'Could not write the .htaccess file. Nothing was changed.', 'velox' ) );
		}

		$check = self::health_check();
		$broke = 'broken' === $check['state']
			|| ( 'ok' === $baseline['state'] && $baseline['code'] < 400 && $check['code'] >= 400 );
		if ( $broke ) {
			self::write( $before );
			return new WP_Error(
				'rolled_back',
				sprintf(
					/* translators: %d: HTTP status code */
					__( 'Your change made the site return an error (HTTP %d), so Velox put the previous version back. Nothing is broken.', 'velox' ),
					(int) $check['code']
				)
			);
		}
		$msg = 'unknown' === $check['state']
			? __( 'Saved. Velox could not load your home page to double-check it, so open the site in a new tab to make sure it still works.', 'velox' )
			: __( 'Saved — your site still loads fine.', 'velox' );
		return array( 'message' => $msg, 'changed' => true, 'checked' => $check['state'], 'backups' => count( self::backups() ) );
	}

	/** Put the most recent backup back (also requires the unlock window). */
	public static function restore_latest() {
		$guard = self::guard();
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}
		$b = self::backups();
		if ( ! $b ) {
			return new WP_Error( 'no_backup', __( 'There is no earlier version to restore.', 'velox' ) );
		}
		$prev = array_shift( $b );
		if ( ! self::write( (string) $prev['content'] ) ) {
			return new WP_Error( 'write_failed', __( 'Could not write the .htaccess file. Nothing was changed.', 'velox' ) );
		}
		update_option( self::BACKUP_OPTION, $b, false );
		return array(
			'message' => __( 'Previous version restored.', 'velox' ),
			'content' => (string) $prev['content'],
			'backups' => count( $b ),
		);
	}

	/** Every write path goes through here: risky mode on, file editable, window open. */
	private static function guard() {
		if ( ! Velox_Settings::get( 'perf_risky_mode', false ) ) {
			return new WP_Error( 'risky_off', __( 'Turn on Risky mode in Performance to edit .htaccess.', 'velox' ) );
		}
		$reason = self::blocked_reason();
		if ( '' !== $reason ) {
			return new WP_Error( 'blocked', $reason );
		}
		if ( self::seconds_left() <= 0 ) {
			return new WP_Error( 'locked', __( 'The editor locked itself again. Unlock it to keep editing — your text is still in the box.', 'velox' ) );
		}
		return true;
	}

	/** Atomic-ish write: temp file in the same folder, then rename over the original. */
	private static function write( $content ) {
		$p   = self::path();
		$tmp = $p . '.velox-' . wp_generate_password( 8, false ) . '.tmp';
		if ( false === @file_put_contents( $tmp, $content ) ) { // phpcs:ignore
			return false;
		}
		if ( file_exists( $p ) ) {
			@chmod( $tmp, fileperms( $p ) & 0777 ); // phpcs:ignore
		}
		if ( ! @rename( $tmp, $p ) ) { // phpcs:ignore
			@unlink( $tmp ); // phpcs:ignore
			// Some hosts refuse rename over an existing file; fall back to a direct write.
			return false !== @file_put_contents( $p, $content ); // phpcs:ignore
		}
		return true;
	}

	/**
	 * Load the home page from the server itself. 'broken' on a 5xx; anything we
	 * can't reach (firewall, loopback blocked) is 'unknown' — never a rollback on
	 * its own.
	 *
	 * @return array{state:string,code:int}
	 */
	public static function health_check() {
		$res = wp_remote_get(
			add_query_arg( 'velox_htcheck', time(), home_url( '/' ) ),
			array( 'timeout' => 12, 'redirection' => 3, 'sslverify' => false, 'headers' => array( 'Cache-Control' => 'no-cache' ) )
		);
		if ( is_wp_error( $res ) ) {
			return array( 'state' => 'unknown', 'code' => 0 );
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		return array( 'state' => $code >= 500 ? 'broken' : 'ok', 'code' => $code );
	}
}
