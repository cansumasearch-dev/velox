<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Velox Site Scan — integrity checking, deliberately narrow.
 *
 * What this does:
 *
 *  1. VERIFIES CORE FILES against the checksums WordPress.org publishes for the
 *     exact version and locale installed. Anything altered, missing, or sitting
 *     in wp-admin/wp-includes without belonging there is reported.
 *
 *  2. FLAGS EXECUTABLE PHP IN THE UPLOADS FOLDER, which is for media. Nothing
 *     legitimate puts runnable code there, so a .php file in uploads is the one
 *     signal that is both cheap to check and rarely wrong.
 *
 * What this deliberately does NOT do, and why:
 *
 *     NO SIGNATURE MATCHING. Scanning file contents for "known bad" patterns
 *     reads as thorough and is worse than nothing on both ends: legitimate
 *     minified or obfuscated code trips every heuristic worth writing, so the
 *     report fills with false alarms nobody can act on — and any real infection
 *     that is not already in the list passes, handing the owner false
 *     confidence. A check that can only answer "this file is not what the
 *     project shipped" is narrower and honest about its own limits.
 *
 * The screen states this in the same terms. A clean result here means the core
 * files match and uploads holds no code — it is not, and must never be
 * presented as, proof that a site is uninfected.
 *
 * @package Velox
 */
class Velox_Site_Scan {

	const STATE    = 'velox_scan_state';
	const RESULT   = 'velox_scan_result';
	const MANIFEST = 'velox_scan_manifest';

	/** Core files hashed per step. Hashing is the only expensive part of the scan. */
	const BATCH = 250;

	/** Findings kept in the report. Anything past this is counted, never silently dropped. */
	const MAX_FINDINGS = 500;

	/** Files visited under uploads before the walk gives up and says so. */
	const MAX_UPLOAD_FILES = 200000;

	/**
	 * Core ships these and hardening guides routinely tell people to delete them.
	 * Missing is expected, not a finding worth alarming anyone about.
	 */
	private static $optional = array( 'readme.html', 'license.txt', 'wp-config-sample.php' );

	/* --------------------------------------------------------------- phases */

	private static function phases() {
		return array(
			'manifest' => array( 'label' => 'Fetching the checksums for your version', 'percent' => 5 ),
			'core'     => array( 'label' => 'Checking core files', 'percent' => 20 ),
			'extra'    => array( 'label' => 'Looking for files that do not belong', 'percent' => 80 ),
			'uploads'  => array( 'label' => 'Checking uploads for runnable code', 'percent' => 90 ),
		);
	}

	/* ---------------------------------------------------------------- start */

	public static function start() {
		delete_option( self::RESULT );
		delete_option( self::MANIFEST );
		$state = array(
			'phase'    => 'manifest',
			'cursor'   => 0,
			'started'  => time(),
			'total'    => 0,
			'findings' => array(),
			'extra'    => 0,
			'notes'    => array(),
			'counts'   => array( 'core' => 0, 'uploads' => 0 ),
			'version'  => get_bloginfo( 'version' ),
			'locale'   => '',
		);
		update_option( self::STATE, $state, false );
		return self::progress( $state );
	}

	public static function step() {
		$state = get_option( self::STATE );
		if ( ! is_array( $state ) || empty( $state['phase'] ) ) {
			return self::start();
		}

		switch ( $state['phase'] ) {
			case 'manifest':
				$more = self::do_manifest( $state );
				break;
			case 'core':
				$more = self::do_core( $state );
				break;
			case 'extra':
				$more = self::do_extra( $state );
				break;
			case 'uploads':
				$more = self::do_uploads( $state );
				break;
			default:
				$more = false;
		}

		if ( ! $more ) {
			$keys  = array_keys( self::phases() );
			$idx   = array_search( $state['phase'], $keys, true );
			$state['phase']  = ( false !== $idx && isset( $keys[ $idx + 1 ] ) ) ? $keys[ $idx + 1 ] : '';
			$state['cursor'] = 0;
		}

		update_option( self::STATE, $state, false );

		if ( '' === $state['phase'] ) {
			return self::finish( $state );
		}
		return self::progress( $state );
	}

	/* ------------------------------------------------------------- manifest */

	/**
	 * The published checksums for this exact version and locale. A localised
	 * build has its own list, so asking for the site's locale first and only
	 * then falling back to en_US avoids reporting every file as altered on a
	 * German install.
	 */
	private static function do_manifest( &$state ) {
		$version = (string) get_bloginfo( 'version' );
		$locale  = get_locale();

		$list = self::fetch_checksums( $version, $locale );
		if ( ! $list && 'en_US' !== $locale ) {
			$list = self::fetch_checksums( $version, 'en_US' );
			if ( $list ) {
				$locale = 'en_US';
				$state['notes'][] = sprintf(
					/* translators: %s: locale code, e.g. de_DE */
					__( 'WordPress.org publishes no checksums for %s, so the English list was used. Translation files are not covered by it.', 'velox' ),
					get_locale()
				);
			}
		}

		if ( ! $list ) {
			$state['notes'][] = __( 'The checksums for your WordPress version could not be fetched, so core files were not verified. This is usually a blocked outbound connection rather than a problem with the site.', 'velox' );
			$state['phase']   = 'uploads'; // Nothing to compare against; skip straight to uploads.
			$state['cursor']  = 0;
			return true;
		}

		// wp-content is never in the published list — themes and plugins are the
		// site's own. Dropping those entries keeps "missing" honest.
		foreach ( array_keys( $list ) as $rel ) {
			if ( 0 === strpos( $rel, 'wp-content/' ) ) {
				unset( $list[ $rel ] );
			}
		}

		update_option( self::MANIFEST, $list, false );
		$state['locale'] = $locale;
		$state['total']  = count( $list );
		return false;
	}

	private static function fetch_checksums( $version, $locale ) {
		$res = wp_remote_get(
			add_query_arg(
				array( 'version' => $version, 'locale' => $locale ),
				'https://api.wordpress.org/core/checksums/1.0/'
			),
			array( 'timeout' => 20 )
		);
		if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
			return array();
		}
		$body = json_decode( wp_remote_retrieve_body( $res ), true );
		$sums = isset( $body['checksums'] ) ? $body['checksums'] : null;
		return is_array( $sums ) ? $sums : array();
	}

	/* ----------------------------------------------------------------- core */

	private static function do_core( &$state ) {
		$list = get_option( self::MANIFEST );
		if ( ! is_array( $list ) || ! $list ) {
			return false;
		}
		$paths  = array_keys( $list );
		$cursor = (int) $state['cursor'];
		$slice  = array_slice( $paths, $cursor, self::BATCH );
		if ( ! $slice ) {
			return false;
		}

		foreach ( $slice as $rel ) {
			$abs = ABSPATH . $rel;
			if ( ! file_exists( $abs ) ) {
				if ( in_array( $rel, self::$optional, true ) ) {
					continue; // Routinely deleted on purpose.
				}
				self::add( $state, 'warn', 'core_missing', $rel, __( 'A core file that should be here is gone.', 'velox' ) );
				continue;
			}
			if ( ! is_readable( $abs ) ) {
				self::add( $state, 'info', 'unreadable', $rel, __( 'Could not be read, so it was not checked.', 'velox' ) );
				continue;
			}
			$state['counts']['core']++;
			if ( ! hash_equals( (string) $list[ $rel ], (string) md5_file( $abs ) ) ) {
				self::add( $state, 'danger', 'core_modified', $rel, __( 'Does not match the file WordPress published for this version.', 'velox' ) );
			}
		}

		$state['cursor'] = $cursor + count( $slice );
		return $state['cursor'] < count( $paths );
	}

	/* ---------------------------------------------------------------- extra */

	/**
	 * Files sitting in wp-admin or wp-includes that the published list does not
	 * mention. Those two folders belong entirely to WordPress, so anything else
	 * in them arrived from somewhere. The site root is left alone on purpose —
	 * wp-config.php, .htaccess and a hundred legitimate odds and ends live there.
	 */
	private static function do_extra( &$state ) {
		$list = get_option( self::MANIFEST );
		if ( ! is_array( $list ) || ! $list ) {
			return false;
		}
		foreach ( array( 'wp-admin', 'wp-includes' ) as $dir ) {
			$base = ABSPATH . $dir;
			if ( ! is_dir( $base ) ) {
				continue;
			}
			foreach ( self::walk( $base ) as $abs ) {
				$rel = $dir . '/' . ltrim( str_replace( '\\', '/', substr( $abs, strlen( $base ) ) ), '/' );
				if ( isset( $list[ $rel ] ) ) {
					continue;
				}
				self::add(
					$state,
					self::is_executable_name( basename( $rel ) ) ? 'danger' : 'warn',
					'core_unexpected',
					$rel,
					__( 'Not part of WordPress. This folder should hold nothing else.', 'velox' )
				);
			}
		}
		return false;
	}

	/* -------------------------------------------------------------- uploads */

	private static function do_uploads( &$state ) {
		$up   = wp_upload_dir();
		$base = isset( $up['basedir'] ) ? $up['basedir'] : '';
		if ( ! $base || ! is_dir( $base ) ) {
			$state['notes'][] = __( 'The uploads folder could not be located, so it was not checked.', 'velox' );
			return false;
		}

		$seen = 0;
		foreach ( self::walk( $base ) as $abs ) {
			if ( ++$seen > self::MAX_UPLOAD_FILES ) {
				$state['notes'][] = sprintf(
					/* translators: %s: number of files */
					__( 'Uploads holds more than %s files, so the check stopped there. Everything past that point was not looked at.', 'velox' ),
					number_format_i18n( self::MAX_UPLOAD_FILES )
				);
				break;
			}
			$state['counts']['uploads']++;
			$name = basename( $abs );
			$rel  = 'uploads/' . ltrim( str_replace( '\\', '/', substr( $abs, strlen( $base ) ) ), '/' );

			if ( self::is_executable_name( $name ) ) {
				if ( self::is_inert( $abs, $name ) ) {
					continue;
				}
				self::add( $state, 'danger', 'upload_php', $rel, __( 'Runnable code in the media folder. Nothing legitimate puts it there.', 'velox' ) );
				continue;
			}
			// x.php.jpg and friends: harmless on a correct server, executed on a
			// misconfigured one, and never something a media library produces.
			if ( preg_match( '/\.(php\d?|phtml|phtm|pht|phar)\./i', $name ) ) {
				self::add( $state, 'warn', 'upload_double_ext', $rel, __( 'A double extension hiding a code file. Some server setups will run it.', 'velox' ) );
				continue;
			}
			if ( '.htaccess' === $name ) {
				// Most of these deny access, which is the folder protecting itself
				// and the opposite of a problem — WooCommerce ships exactly that.
				// One that switches a handler on is the real thing to catch: it is
				// how an uploaded image gets executed as code.
				$rules = strtolower( (string) @file_get_contents( $abs ) );
				if ( preg_match( '/addhandler|addtype|sethandler|php_flag|php_value|execcgi|x-httpd-php/', $rules ) ) {
					self::add( $state, 'danger', 'upload_htaccess', $rel, __( 'Makes this folder run code. That is how an uploaded image becomes a back door.', 'velox' ) );
				}
			}
		}
		return false;
	}

	/* ---------------------------------------------------------------- utils */

	/**
	 * A guard file, not a threat.
	 *
	 * Plugins drop an inert index.php into their own upload folders so the
	 * directory cannot be listed — WP All Import, WooCommerce and most others
	 * do it, several folders deep. Reporting those fills the report with
	 * alarms nobody can act on, which is the exact failure this module exists
	 * to avoid.
	 *
	 * This asks whether the file DOES anything, not whether it resembles
	 * something known-bad. A file whose only tokens are an open tag and
	 * comments cannot execute, whatever it happens to be called, so skipping
	 * it costs nothing — an attacker gains no ground by hiding a payload in a
	 * file that runs no code.
	 */
	private static function is_inert( $abs, $name ) {
		if ( 'index.php' !== strtolower( $name ) ) {
			return false;
		}
		$size = @filesize( $abs );
		if ( false === $size || $size > 512 ) {
			return false;
		}
		$src = @file_get_contents( $abs );
		if ( false === $src ) {
			return false;
		}
		if ( '' === trim( $src ) ) {
			return true;
		}
		if ( ! function_exists( 'token_get_all' ) ) {
			// No tokenizer: accept only an open tag followed by line comments.
			return (bool) preg_match( '{^\s*<\?php\s*(//[^\n]*\s*)*$}', $src );
		}
		$harmless = array( T_OPEN_TAG, T_CLOSE_TAG, T_WHITESPACE, T_COMMENT, T_DOC_COMMENT, T_INLINE_HTML );
		foreach ( @token_get_all( $src ) as $t ) {
			if ( ! is_array( $t ) ) {
				return false; // a bare ';' or '{' is a statement, so this runs
			}
			if ( ! in_array( $t[0], $harmless, true ) ) {
				return false;
			}
			if ( T_INLINE_HTML === $t[0] && '' !== trim( $t[1] ) ) {
				return false;
			}
		}
		return true;
	}

	private static function is_executable_name( $name ) {
		return (bool) preg_match( '/\.(php\d?|phtml|phtm|pht|phps|phar)$/i', $name );
	}

	/**
	 * Every file below a directory. Symlinks are not followed: a link pointing
	 * back up its own tree would walk forever, and that is a worse failure than
	 * missing a linked folder.
	 */
	private static function walk( $base ) {
		$out = array();
		try {
			$it = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $base, FilesystemIterator::SKIP_DOTS ),
				RecursiveIteratorIterator::LEAVES_ONLY,
				RecursiveIteratorIterator::CATCH_GET_CHILD
			);
			foreach ( $it as $f ) {
				if ( $f->isFile() ) {
					$out[] = $f->getPathname();
				}
			}
		} catch ( Exception $e ) {
			return $out;
		}
		return $out;
	}

	private static function add( &$state, $level, $kind, $path, $note ) {
		if ( count( $state['findings'] ) >= self::MAX_FINDINGS ) {
			$state['extra']++;
			return;
		}
		$state['findings'][] = array(
			'level' => $level,
			'kind'  => $kind,
			'path'  => $path,
			'note'  => $note,
		);
	}

	private static function progress( $state ) {
		$phases = self::phases();
		$phase  = $state['phase'];
		$pct    = isset( $phases[ $phase ]['percent'] ) ? $phases[ $phase ]['percent'] : 0;

		// Inside the core phase the cursor gives a real percentage to show.
		if ( 'core' === $phase && ! empty( $state['total'] ) ) {
			$span = 80 - 20;
			$pct  = 20 + (int) floor( $span * min( 1, $state['cursor'] / max( 1, $state['total'] ) ) );
		}

		return array(
			'done'    => false,
			'percent' => $pct,
			'label'   => isset( $phases[ $phase ]['label'] ) ? $phases[ $phase ]['label'] : '',
			'phase'   => $phase,
			'found'   => count( $state['findings'] ) + (int) $state['extra'],
		);
	}

	private static function finish( $state ) {
		$order = array( 'danger' => 0, 'warn' => 1, 'info' => 2 );
		$found = $state['findings'];
		usort( $found, function ( $a, $b ) use ( $order ) {
			$d = ( $order[ $a['level'] ] ?? 3 ) <=> ( $order[ $b['level'] ] ?? 3 );
			return $d ? $d : strcmp( $a['path'], $b['path'] );
		} );

		$result = array(
			'findings' => $found,
			'extra'    => (int) $state['extra'],
			'notes'    => $state['notes'],
			'counts'   => $state['counts'],
			'version'  => $state['version'],
			'locale'   => $state['locale'],
			'ended'    => time(),
			'levels'   => array(
				'danger' => count( array_filter( $found, function ( $f ) { return 'danger' === $f['level']; } ) ),
				'warn'   => count( array_filter( $found, function ( $f ) { return 'warn' === $f['level']; } ) ),
				'info'   => count( array_filter( $found, function ( $f ) { return 'info' === $f['level']; } ) ),
			),
		);
		update_option( self::RESULT, $result, false );
		delete_option( self::MANIFEST );
		delete_option( self::STATE );

		$result['done']    = true;
		$result['percent'] = 100;
		return $result;
	}

	/** The last completed scan, or an empty array if there has never been one. */
	public static function last() {
		$r = get_option( self::RESULT );
		return is_array( $r ) ? $r : array();
	}

	public static function forget() {
		delete_option( self::RESULT );
		delete_option( self::MANIFEST );
		delete_option( self::STATE );
	}
}
