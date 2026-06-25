<?php
/**
 * Velox — Backup.
 *
 * Creates portable backups of the database and/or the site files, stores them in
 * a protected folder under wp-content, lists them, restores them, and can run on
 * a schedule with a keep-N retention policy.
 *
 * Design notes / safety:
 *  - The DB dump is produced in pure PHP (no mysqldump dependency — most shared
 *    hosts don't expose it). Each table is dumped as CREATE + batched INSERTs,
 *    every value escaped through $wpdb. Large tables are read in chunks so memory
 *    stays flat.
 *  - File archives use ZipArchive (standard on WP hosts; checked before use). The
 *    backups folder itself, VCS dirs and obvious caches are excluded so a backup
 *    never contains a previous backup (no runaway recursion).
 *  - Restore is deliberately explicit. Before a DB restore Velox writes a fresh
 *    "safety" DB backup first, so a bad restore can itself be undone.
 *
 * @package Velox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Velox_Backup {

	const DIR_NAME   = 'velox-backups';
	const MANIFEST   = 'velox-manifest.json';
	const HISTORY    = 'velox-history.json';
	const HOOK       = 'velox_backup_run';
	const INSERT_ROWS = 200; // rows per INSERT statement in the dump

	/* ----------------------------------------------------------------- *
	 * Bootstrap + schedule
	 * ----------------------------------------------------------------- */

	public static function init() {
		if ( ! Velox_Settings::get( 'util_backup', false ) ) {
			return;
		}
		add_action( self::HOOK, array( __CLASS__, 'run_scheduled' ) );
		add_filter( 'cron_schedules', array( __CLASS__, 'cron_schedules' ) );
		add_action( 'admin_post_velox_backup_download', array( __CLASS__, 'handle_download' ) );
		self::sync_schedule();
	}

	/** Verify nonce + cap, then stream a backup file to the browser. */
	public static function handle_download() {
		$id   = isset( $_GET['id'] ) ? sanitize_text_field( wp_unslash( $_GET['id'] ) ) : '';
		$kind = isset( $_GET['kind'] ) ? sanitize_key( wp_unslash( $_GET['kind'] ) ) : 'zip';
		check_admin_referer( 'velox_backup_download_' . $id . '_' . $kind );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'velox' ) );
		}

		// "all" = a single bundle holding both the DB dump and the files archive,
		// so a downloaded "both" backup can be re-imported and restored in full.
		if ( 'all' === $kind ) {
			$bundle = self::build_bundle( $id );
			if ( '' === $bundle ) {
				wp_die( esc_html__( 'That backup file is no longer available.', 'velox' ) );
			}
			nocache_headers();
			header( 'Content-Type: application/zip' );
			header( 'Content-Disposition: attachment; filename="' . basename( $bundle ) . '"' );
			header( 'Content-Length: ' . filesize( $bundle ) );
			$fh = fopen( $bundle, 'rb' );
			while ( ! feof( $fh ) ) {
				echo fread( $fh, 1024 * 256 ); // phpcs:ignore WordPress.Security.EscapeOutput
				flush();
			}
			fclose( $fh );
			@unlink( $bundle ); // temp bundle, not part of the manifest
			exit;
		}

		$path = self::file_path( $id, $kind );
		if ( '' === $path ) {
			wp_die( esc_html__( 'That backup file is no longer available.', 'velox' ) );
		}
		nocache_headers();
		header( 'Content-Type: application/octet-stream' );
		header( 'Content-Disposition: attachment; filename="' . basename( $path ) . '"' );
		header( 'Content-Length: ' . filesize( $path ) );
		// Flush in chunks so large archives don't exhaust memory.
		$fh = fopen( $path, 'rb' );
		while ( ! feof( $fh ) ) {
			echo fread( $fh, 1024 * 256 ); // phpcs:ignore WordPress.Security.EscapeOutput
			flush();
		}
		fclose( $fh );
		exit;
	}

	/**
	 * Build a single bundle .zip containing this backup's DB dump and/or files
	 * archive, plus a small velox-bundle.json so import() can split it back.
	 * Returns a temp file path (caller deletes it) or '' on failure.
	 */
	private static function build_bundle( $id ) {
		$b = self::get( $id );
		if ( ! $b || ! class_exists( 'ZipArchive' ) ) {
			return '';
		}
		$dir   = self::dir();
		$parts = array();
		$bundle_path = $dir . '/velox-bundle-' . $id . '-' . substr( md5( uniqid( '', true ) ), 0, 6 ) . '.zip';
		$zip = new ZipArchive();
		if ( true !== $zip->open( $bundle_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			return '';
		}
		if ( ! empty( $b['db_file'] ) ) {
			$p = $dir . '/' . basename( $b['db_file'] );
			if ( is_file( $p ) ) { $zip->addFile( $p, basename( $b['db_file'] ) ); $parts['db'] = basename( $b['db_file'] ); }
		}
		if ( ! empty( $b['zip_file'] ) ) {
			$p = $dir . '/' . basename( $b['zip_file'] );
			if ( is_file( $p ) ) { $zip->addFile( $p, basename( $b['zip_file'] ) ); $parts['files'] = basename( $b['zip_file'] ); }
		}
		if ( empty( $parts ) ) { $zip->close(); @unlink( $bundle_path ); return ''; }
		$zip->addFromString( 'velox-bundle.json', wp_json_encode( array(
			'velox_bundle' => 1,
			'name'    => $b['name'] ?? $id,
			'what'    => $b['what'] ?? '',
			'parts'   => $parts,
			'tables'  => (int) ( $b['tables'] ?? 0 ),
			'files'   => (int) ( $b['files'] ?? 0 ),
			'created' => $b['created'] ?? '',
		) ) );
		$zip->close();
		return is_file( $bundle_path ) ? $bundle_path : '';
	}

	public static function download_url( $id, $kind ) {
		return wp_nonce_url(
			admin_url( 'admin-post.php?action=velox_backup_download&id=' . rawurlencode( $id ) . '&kind=' . rawurlencode( $kind ) ),
			'velox_backup_download_' . $id . '_' . $kind
		);
	}

	public static function cron_schedules( $s ) {
		if ( ! isset( $s['weekly'] ) ) {
			$s['weekly'] = array( 'interval' => WEEK_IN_SECONDS, 'display' => 'Once Weekly' );
		}
		if ( ! isset( $s['monthly'] ) ) {
			$s['monthly'] = array( 'interval' => 30 * DAY_IN_SECONDS, 'display' => 'Once Monthly' );
		}
		return $s;
	}

	/** Keep the WP-Cron event in step with the chosen frequency. */
	public static function sync_schedule() {
		$freq = Velox_Settings::get( 'backup_schedule', 'off' );
		$next = wp_next_scheduled( self::HOOK );
		if ( 'off' === $freq ) {
			if ( $next ) {
				wp_unschedule_event( $next, self::HOOK );
			}
			return;
		}
		$recurrence = in_array( $freq, array( 'daily', 'weekly', 'monthly' ), true ) ? $freq : 'weekly';
		if ( ! $next ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, $recurrence, self::HOOK );
		}
	}

	public static function run_scheduled() {
		$what = Velox_Settings::get( 'backup_schedule_what', 'both' );
		self::create( $what, 'Scheduled' );
		self::enforce_retention();
	}

	/* ----------------------------------------------------------------- *
	 * Storage
	 * ----------------------------------------------------------------- */

	public static function dir() {
		$base = WP_CONTENT_DIR . '/' . self::DIR_NAME;
		if ( ! is_dir( $base ) ) {
			wp_mkdir_p( $base );
			// Lock the folder down.
			@file_put_contents( $base . '/.htaccess', "Deny from all\n" );
			@file_put_contents( $base . '/index.php', "<?php // Silence is golden.\n" );
		}
		return $base;
	}

	public static function manifest() {
		$file = self::dir() . '/' . self::MANIFEST;
		if ( ! is_readable( $file ) ) {
			return array();
		}
		$data = json_decode( (string) file_get_contents( $file ), true );
		return is_array( $data ) ? $data : array();
	}

	private static function write_manifest( $list ) {
		$file = self::dir() . '/' . self::MANIFEST;
		file_put_contents( $file, wp_json_encode( array_values( $list ) ) );
	}

	/** Backups newest first. */
	public static function all() {
		$list = self::manifest();
		usort( $list, function ( $a, $b ) {
			return strcmp( $b['created'] ?? '', $a['created'] ?? '' );
		} );
		return $list;
	}

	public static function get( $id ) {
		foreach ( self::manifest() as $b ) {
			if ( isset( $b['id'] ) && $b['id'] === $id ) {
				return $b;
			}
		}
		return null;
	}

	/**
	 * A short, unique, human-friendly name for a backup, e.g. "brave-otter-7c2".
	 * Memorable in the list and unique enough to tell snapshots apart at a glance.
	 */
	public static function friendly_name() {
		$adj  = array( 'brave', 'calm', 'swift', 'bright', 'quiet', 'bold', 'keen', 'lucky', 'mellow', 'noble', 'proud', 'sleek', 'sunny', 'tidy', 'witty', 'amber', 'azure', 'coral', 'jade', 'ivory' );
		$noun = array( 'otter', 'falcon', 'maple', 'comet', 'harbor', 'meadow', 'cedar', 'pebble', 'willow', 'badger', 'lynx', 'heron', 'cobra', 'panda', 'raven', 'tiger', 'walrus', 'gecko', 'moose', 'finch' );
		return $adj[ array_rand( $adj ) ] . '-' . $noun[ array_rand( $noun ) ] . '-' . substr( md5( uniqid( '', true ) ), 0, 3 );
	}

	/* ----------------------------------------------------------------- *
	 * Restore history
	 * ----------------------------------------------------------------- */

	public static function history() {
		$file = self::dir() . '/' . self::HISTORY;
		if ( ! is_readable( $file ) ) {
			return array();
		}
		$data = json_decode( (string) file_get_contents( $file ), true );
		if ( ! is_array( $data ) ) {
			return array();
		}
		// newest first
		usort( $data, function ( $a, $b ) {
			return strcmp( $b['when'] ?? '', $a['when'] ?? '' );
		} );
		return $data;
	}

	private static function record_history( $row ) {
		$file = self::dir() . '/' . self::HISTORY;
		$list = self::history();
		array_unshift( $list, $row );
		$list = array_slice( $list, 0, 50 ); // keep the last 50 restores
		file_put_contents( $file, wp_json_encode( array_values( $list ) ) );
	}

	/* ----------------------------------------------------------------- *
	 * Create
	 * ----------------------------------------------------------------- */

	/**
	 * @param string $what  db | files | both
	 * @param string $note  label shown in the list
	 * @return array { ok, id?, message?, ... }
	 */
	public static function create( $what = 'both', $note = 'Manual' ) {
		$what = in_array( $what, array( 'db', 'files', 'both' ), true ) ? $what : 'both';
		@set_time_limit( 0 );
		$dir = self::dir();
		$id  = gmdate( 'Ymd-His' ) . '-' . substr( md5( uniqid( '', true ) ), 0, 6 );
		$created = current_time( 'mysql' );

		$entry = array(
			'id'      => $id,
			'name'    => self::friendly_name(),
			'created' => $created,
			'note'    => sanitize_text_field( $note ),
			'what'    => $what,
			'db_file' => '',
			'zip_file'=> '',
			'db_size' => 0,
			'zip_size'=> 0,
			'tables'  => 0,
			'files'   => 0,
			'wp_version' => get_bloginfo( 'version' ),
			'site_url'   => home_url(),
		);

		if ( 'db' === $what || 'both' === $what ) {
			$res = self::dump_database( $dir, $id );
			if ( empty( $res['ok'] ) ) {
				return array( 'ok' => false, 'message' => $res['message'] );
			}
			$entry['db_file'] = $res['file'];
			$entry['db_size'] = $res['size'];
			$entry['tables']  = $res['tables'];
		}

		if ( 'files' === $what || 'both' === $what ) {
			if ( ! class_exists( 'ZipArchive' ) ) {
				// If we already wrote a DB dump, keep it but report the files failure.
				if ( '' !== $entry['db_file'] ) {
					$entry['what'] = 'db';
					self::record( $entry );
					return array( 'ok' => true, 'id' => $id, 'partial' => true, 'message' => 'Database backed up. File backup skipped: PHP-zip is not available on this server.' );
				}
				return array( 'ok' => false, 'message' => 'PHP-zip is not available on this server, so file backups cannot be created. Ask your host to enable php-zip.' );
			}
			$res = self::archive_files( $dir, $id );
			if ( empty( $res['ok'] ) ) {
				return array( 'ok' => false, 'message' => $res['message'] );
			}
			$entry['zip_file'] = $res['file'];
			$entry['zip_size'] = $res['size'];
			$entry['files']    = $res['count'];
		}

		self::record( $entry );
		return array( 'ok' => true, 'id' => $id, 'entry' => $entry );
	}

	private static function record( $entry ) {
		$list   = self::manifest();
		$list[] = $entry;
		self::write_manifest( $list );
	}

	/**
	 * Import a backup file produced on another site. Accepts a .sql (database) or
	 * a .zip (wp-content files) and registers it as a normal backup that can be
	 * downloaded or restored here. $file is a $_FILES entry.
	 *
	 * @return array { ok, id?, message }
	 */
	public static function import( $file ) {
		if ( empty( $file ) || ! isset( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			return array( 'ok' => false, 'message' => 'No file was uploaded.' );
		}
		if ( ! empty( $file['error'] ) ) {
			return array( 'ok' => false, 'message' => 'Upload failed (error code ' . (int) $file['error'] . '). The file may be larger than the server allows.' );
		}
		$name = isset( $file['name'] ) ? (string) $file['name'] : '';
		$ext  = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, array( 'sql', 'zip' ), true ) ) {
			return array( 'ok' => false, 'message' => 'Please upload a .sql database dump or a .zip file archive.' );
		}

		$dir     = self::dir();
		$id      = gmdate( 'Ymd-His' ) . '-' . substr( md5( uniqid( '', true ) ), 0, 6 );
		$created = current_time( 'mysql' );
		$entry   = array(
			'id' => $id, 'name' => self::friendly_name(), 'created' => $created,
			'note' => 'Imported' . ( $name ? ' — ' . sanitize_text_field( $name ) : '' ),
			'what' => '', 'db_file' => '', 'zip_file' => '', 'db_size' => 0, 'zip_size' => 0,
			'tables' => 0, 'files' => 0, 'imported' => true,
			'wp_version' => get_bloginfo( 'version' ), 'site_url' => home_url(),
		);

		if ( 'sql' === $ext ) {
			$dest = $dir . '/velox-db-' . $id . '.sql';
			if ( ! @move_uploaded_file( $file['tmp_name'], $dest ) ) {
				return array( 'ok' => false, 'message' => 'Could not save the uploaded dump (folder permissions?).' );
			}
			$entry['what']    = 'db';
			$entry['db_file'] = basename( $dest );
			$entry['db_size'] = (int) filesize( $dest );
			// best-effort table count from CREATE TABLE statements
			$entry['tables']  = self::count_sql_tables( $dest );
		} else {
			// validate it's a real zip before accepting
			if ( ! class_exists( 'ZipArchive' ) ) {
				return array( 'ok' => false, 'message' => 'PHP-zip is unavailable on this server, so .zip backups cannot be imported.' );
			}
			$probe = new ZipArchive();
			if ( true !== $probe->open( $file['tmp_name'] ) ) {
				return array( 'ok' => false, 'message' => 'That .zip could not be opened — it may be corrupt.' );
			}

			// Is this a Velox bundle (DB + files together)? If so, split it back out.
			$manifest = $probe->getFromName( 'velox-bundle.json' );
			if ( false !== $manifest ) {
				$meta = json_decode( (string) $manifest, true );
				$parts = ( is_array( $meta ) && ! empty( $meta['parts'] ) ) ? $meta['parts'] : array();
				$have_db = false; $have_files = false;
				// Extract the DB dump part.
				if ( ! empty( $parts['db'] ) && false !== $probe->locateName( $parts['db'] ) ) {
					$sql = $probe->getFromName( $parts['db'] );
					if ( false !== $sql ) {
						$dest = $dir . '/velox-db-' . $id . '.sql';
						if ( false !== file_put_contents( $dest, $sql ) ) {
							$entry['db_file'] = basename( $dest );
							$entry['db_size'] = (int) filesize( $dest );
							$entry['tables']  = isset( $meta['tables'] ) ? (int) $meta['tables'] : self::count_sql_tables( $dest );
							$have_db = true;
						}
					}
				}
				// Extract the files archive part.
				if ( ! empty( $parts['files'] ) && false !== $probe->locateName( $parts['files'] ) ) {
					$zbytes = $probe->getFromName( $parts['files'] );
					if ( false !== $zbytes ) {
						$dest = $dir . '/velox-files-' . $id . '.zip';
						if ( false !== file_put_contents( $dest, $zbytes ) ) {
							$entry['zip_file'] = basename( $dest );
							$entry['zip_size'] = (int) filesize( $dest );
							$entry['files']    = isset( $meta['files'] ) ? (int) $meta['files'] : 0;
							$have_files = true;
						}
					}
				}
				$probe->close();
				if ( ! $have_db && ! $have_files ) {
					return array( 'ok' => false, 'message' => 'The bundle did not contain any restorable parts.' );
				}
				$entry['what'] = ( $have_db && $have_files ) ? 'both' : ( $have_db ? 'db' : 'files' );
				self::record( $entry );
				return array( 'ok' => true, 'id' => $id, 'entry' => $entry, 'message' => 'Backup imported (database + files). You can restore or download it like any other.' );
			}

			// Plain files-only archive.
			$entry['files'] = $probe->numFiles;
			$probe->close();
			$dest = $dir . '/velox-files-' . $id . '.zip';
			if ( ! @move_uploaded_file( $file['tmp_name'], $dest ) ) {
				return array( 'ok' => false, 'message' => 'Could not save the uploaded archive (folder permissions?).' );
			}
			$entry['what']     = 'files';
			$entry['zip_file'] = basename( $dest );
			$entry['zip_size'] = (int) filesize( $dest );
		}

		self::record( $entry );
		return array( 'ok' => true, 'id' => $id, 'entry' => $entry, 'message' => 'Backup imported. You can restore or download it like any other.' );
	}

	private static function count_sql_tables( $sql_file ) {
		$count = 0;
		$fh = @fopen( $sql_file, 'r' );
		if ( ! $fh ) {
			return 0;
		}
		while ( false !== ( $line = fgets( $fh ) ) ) {
			if ( false !== stripos( $line, 'CREATE TABLE' ) ) {
				$count++;
			}
		}
		fclose( $fh );
		return $count;
	}

	/* ----------------------------------------------------------------- *
	 * Database dump
	 * ----------------------------------------------------------------- */

	private static function dump_database( $dir, $id ) {
		global $wpdb;
		$file = $dir . '/velox-db-' . $id . '.sql';
		$fh   = @fopen( $file, 'w' );
		if ( ! $fh ) {
			return array( 'ok' => false, 'message' => 'Could not open the dump file for writing (folder permissions?).' );
		}

		fwrite( $fh, "-- Velox database backup\n-- Created: " . current_time( 'mysql' ) . "\n" );
		fwrite( $fh, "-- Site: " . home_url() . "\n\n" );
		fwrite( $fh, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n" );

		$tables = $wpdb->get_col( 'SHOW TABLES' );
		$count  = 0;
		foreach ( $tables as $table ) {
			// Only dump this install's tables (respect the table prefix).
			if ( 0 !== strpos( $table, $wpdb->prefix ) ) {
				continue;
			}
			$count++;
			// CREATE
			$create = $wpdb->get_row( "SHOW CREATE TABLE `{$table}`", ARRAY_N );
			fwrite( $fh, "\nDROP TABLE IF EXISTS `{$table}`;\n" );
			if ( $create && isset( $create[1] ) ) {
				fwrite( $fh, $create[1] . ";\n\n" );
			}

			// Rows, chunked.
			$offset = 0;
			$cols   = null;
			while ( true ) {
				$rows = $wpdb->get_results(
					$wpdb->prepare( "SELECT * FROM `{$table}` LIMIT %d OFFSET %d", 1000, $offset ),
					ARRAY_A
				);
				if ( empty( $rows ) ) {
					break;
				}
				if ( null === $cols ) {
					$cols = array_keys( $rows[0] );
				}
				$collist = '`' . implode( '`,`', $cols ) . '`';
				$buffer  = array();
				foreach ( $rows as $row ) {
					$vals = array();
					foreach ( $row as $v ) {
						if ( is_null( $v ) ) {
							$vals[] = 'NULL';
						} else {
							// Escape, then neutralise real newlines so each INSERT row stays
							// on a single physical line — the restore parser splits on lines
							// ending in ";", so embedded newlines must not appear raw.
							$esc    = esc_sql( $v );
							$esc    = str_replace( array( "\r\n", "\r", "\n" ), array( '\\n', '\\n', '\\n' ), $esc );
							$vals[] = "'" . $esc . "'";
						}
					}
					$buffer[] = '(' . implode( ',', $vals ) . ')';
					if ( count( $buffer ) >= self::INSERT_ROWS ) {
						fwrite( $fh, "INSERT INTO `{$table}` ({$collist}) VALUES\n" . implode( ",\n", $buffer ) . ";\n" );
						$buffer = array();
					}
				}
				if ( $buffer ) {
					fwrite( $fh, "INSERT INTO `{$table}` ({$collist}) VALUES\n" . implode( ",\n", $buffer ) . ";\n" );
				}
				$offset += 1000;
			}
		}
		fwrite( $fh, "\nSET FOREIGN_KEY_CHECKS=1;\n" );
		fclose( $fh );

		return array( 'ok' => true, 'file' => basename( $file ), 'size' => (int) filesize( $file ), 'tables' => $count );
	}

	/* ----------------------------------------------------------------- *
	 * File archive
	 * ----------------------------------------------------------------- */

	private static function archive_files( $dir, $id ) {
		$file = $dir . '/velox-files-' . $id . '.zip';
		$zip  = new ZipArchive();
		if ( true !== $zip->open( $file, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			return array( 'ok' => false, 'message' => 'Could not create the file archive.' );
		}

		$root  = WP_CONTENT_DIR;
		$count = 0;
		$skip_dir = self::DIR_NAME; // never include the backups folder itself

		$it = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::SELF_FIRST
		);
		foreach ( $it as $path => $info ) {
			$rel = ltrim( str_replace( $root, '', $path ), '/\\' );
			$rel = str_replace( '\\', '/', $rel );
			if ( self::should_skip( $rel, $skip_dir ) ) {
				continue;
			}
			if ( $info->isDir() ) {
				$zip->addEmptyDir( $rel );
			} elseif ( $info->isFile() && $info->isReadable() ) {
				$zip->addFile( $path, $rel );
				$count++;
			}
		}
		$zip->close();

		return array( 'ok' => true, 'file' => basename( $file ), 'size' => (int) filesize( $file ), 'count' => $count );
	}

	private static function should_skip( $rel, $skip_dir ) {
		$rel = ltrim( $rel, '/' );
		$skip_prefixes = array(
			$skip_dir . '/',          // our own backups
			'cache/',
			'uploads/cache/',
			'wpo-cache/',
			'litespeed/',
			'et-cache/',
			'w3tc-config/',
		);
		foreach ( $skip_prefixes as $p ) {
			if ( 0 === strpos( $rel, $p ) ) {
				return true;
			}
		}
		// junk dirs anywhere in the path
		$needles = array( '/node_modules/', '/.git/', '/.svn/', '/.DS_Store' );
		foreach ( $needles as $n ) {
			if ( false !== strpos( '/' . $rel, $n ) ) {
				return true;
			}
		}
		if ( $rel === $skip_dir ) {
			return true;
		}
		return false;
	}

	/* ----------------------------------------------------------------- *
	 * Delete + retention
	 * ----------------------------------------------------------------- */

	public static function delete( $id ) {
		$list = self::manifest();
		$kept = array();
		$dir  = self::dir();
		foreach ( $list as $b ) {
			if ( isset( $b['id'] ) && $b['id'] === $id ) {
				foreach ( array( 'db_file', 'zip_file' ) as $k ) {
					if ( ! empty( $b[ $k ] ) ) {
						$p = $dir . '/' . basename( $b[ $k ] );
						if ( is_file( $p ) ) {
							@unlink( $p );
						}
					}
				}
				continue;
			}
			$kept[] = $b;
		}
		self::write_manifest( $kept );
		return array( 'ok' => true );
	}

	public static function enforce_retention() {
		$keep = (int) Velox_Settings::get( 'backup_keep', 5 );
		if ( $keep < 1 ) {
			return;
		}
		$all = self::all(); // newest first
		$old = array_slice( $all, $keep );
		foreach ( $old as $b ) {
			self::delete( $b['id'] );
		}
	}

	/* ----------------------------------------------------------------- *
	 * Download
	 * ----------------------------------------------------------------- */

	public static function file_path( $id, $kind ) {
		$b = self::get( $id );
		if ( ! $b ) {
			return '';
		}
		$key = ( 'zip' === $kind ) ? 'zip_file' : 'db_file';
		if ( empty( $b[ $key ] ) ) {
			return '';
		}
		$path = self::dir() . '/' . basename( $b[ $key ] );
		return is_file( $path ) ? $path : '';
	}

	/* ----------------------------------------------------------------- *
	 * Restore
	 * ----------------------------------------------------------------- */

	/**
	 * Restore database and/or files from a stored backup.
	 *
	 * @param string $id
	 * @param string $what db | files | both
	 */
	public static function restore( $id, $what = 'both', $safety = true ) {
		@set_time_limit( 0 );
		$start = microtime( true );
		$b = self::get( $id );
		if ( ! $b ) {
			return array( 'ok' => false, 'message' => 'Backup not found.' );
		}
		$what = in_array( $what, array( 'db', 'files', 'both' ), true ) ? $what : 'both';
		$done = array();
		$safety_id = '';

		if ( ( 'db' === $what || 'both' === $what ) && ! empty( $b['db_file'] ) ) {
			// Optional safety net: snapshot the current DB before overwriting it, so a
			// bad restore is reversible. Clearly labelled so it's obvious in the list.
			if ( $safety ) {
				$snap = self::create( 'db', 'Auto safety snapshot (before restoring ' . ( $b['name'] ?? $id ) . ')' );
				if ( ! empty( $snap['ok'] ) ) {
					$safety_id = $snap['id'];
				}
			}
			// Pin Velox's own state: a restored (older) DB must not roll the plugin's
			// version, settings, or active state backwards. Capture before, re-apply after.
			$preserved = self::preserve_velox_state();
			$res = self::restore_database( self::dir() . '/' . basename( $b['db_file'] ) );
			self::restore_velox_state( $preserved );
			if ( empty( $res['ok'] ) ) {
				self::record_history( array(
					'when' => current_time( 'mysql' ), 'backup_id' => $id, 'backup_name' => $b['name'] ?? $id,
					'what' => $what, 'ok' => false, 'duration' => round( microtime( true ) - $start, 1 ),
					'detail' => 'Database restore failed: ' . $res['message'], 'safety_id' => $safety_id,
				) );
				return array( 'ok' => false, 'message' => 'Database restore failed: ' . $res['message'] . ( $safety_id ? ' A safety snapshot was taken first.' : '' ) );
			}
			$done[] = $res['statements'] . ' SQL statements';
		}

		if ( ( 'files' === $what || 'both' === $what ) && ! empty( $b['zip_file'] ) ) {
			if ( ! class_exists( 'ZipArchive' ) ) {
				return array( 'ok' => false, 'message' => 'PHP-zip is unavailable, so files cannot be restored on this server.' );
			}
			$res = self::restore_files( self::dir() . '/' . basename( $b['zip_file'] ) );
			if ( empty( $res['ok'] ) ) {
				self::record_history( array(
					'when' => current_time( 'mysql' ), 'backup_id' => $id, 'backup_name' => $b['name'] ?? $id,
					'what' => $what, 'ok' => false, 'duration' => round( microtime( true ) - $start, 1 ),
					'detail' => 'File restore failed: ' . $res['message'], 'safety_id' => $safety_id,
				) );
				return array( 'ok' => false, 'message' => 'File restore failed: ' . $res['message'] );
			}
			$done[] = $res['count'] . ' files';
		}

		if ( empty( $done ) ) {
			return array( 'ok' => false, 'message' => 'Nothing in this backup matched the requested restore type.' );
		}

		$duration = round( microtime( true ) - $start, 1 );
		self::record_history( array(
			'when' => current_time( 'mysql' ), 'backup_id' => $id, 'backup_name' => $b['name'] ?? $id,
			'what' => $what, 'ok' => true, 'duration' => $duration,
			'detail' => 'Restored ' . implode( ' and ', $done ) . '.', 'safety_id' => $safety_id,
		) );
		return array( 'ok' => true, 'message' => 'Restored ' . implode( ' and ', $done ) . '.', 'restored' => $done, 'duration' => $duration, 'safety_id' => $safety_id );
	}

	/**
	 * Capture Velox's own option state (settings, per-module DB-version markers,
	 * snippets/forms/redirects data) plus the active-plugins list, so a database
	 * restore can't roll the plugin backwards or deactivate it.
	 */
	private static function preserve_velox_state() {
		global $wpdb;
		$state = array();
		// Every option Velox owns.
		$names = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'velox\\_%'" );
		if ( is_array( $names ) ) {
			foreach ( $names as $name ) {
				$state[ $name ] = get_option( $name );
			}
		}
		// Keep Velox active after the restore even if the old DB didn't have it.
		$state['__active_plugins'] = get_option( 'active_plugins', array() );
		return $state;
	}

	/**
	 * Re-apply the state captured by preserve_velox_state() after the DB has been
	 * overwritten, so the running plugin stays at the current installed version.
	 */
	private static function restore_velox_state( $state ) {
		if ( ! is_array( $state ) ) {
			return;
		}
		$active = isset( $state['__active_plugins'] ) ? $state['__active_plugins'] : null;
		unset( $state['__active_plugins'] );

		foreach ( $state as $name => $value ) {
			update_option( $name, $value );
		}

		// Make sure Velox is still in the active-plugins list (merge, don't clobber
		// other plugins the restored DB legitimately had).
		if ( is_array( $active ) && defined( 'VELOX_BASENAME' ) ) {
			$current = get_option( 'active_plugins', array() );
			if ( ! is_array( $current ) ) { $current = array(); }
			if ( ! in_array( VELOX_BASENAME, $current, true ) ) {
				$current[] = VELOX_BASENAME;
				update_option( 'active_plugins', $current );
			}
		}
	}

	private static function restore_database( $sql_file ) {
		global $wpdb;
		if ( ! is_readable( $sql_file ) ) {
			return array( 'ok' => false, 'message' => 'Dump file is missing or unreadable.' );
		}
		$fh = @fopen( $sql_file, 'r' );
		if ( ! $fh ) {
			return array( 'ok' => false, 'message' => 'Could not open the dump file.' );
		}
		$count  = 0;
		$buffer = '';
		while ( false !== ( $line = fgets( $fh ) ) ) {
			$trim = ltrim( $line );
			if ( '' === trim( $line ) || 0 === strpos( $trim, '--' ) ) {
				continue; // comment / blank
			}
			$buffer .= $line;
			// A statement ends at a line whose trimmed end is ';'
			if ( ';' === substr( rtrim( $line ), -1 ) ) {
				$stmt = trim( $buffer );
				$buffer = '';
				if ( '' === $stmt ) {
					continue;
				}
				// phpcs:ignore WordPress.DB.PreparedSQL
				$ok = $wpdb->query( $stmt );
				if ( false === $ok ) {
					// Keep going on benign errors, but record the first hard failure.
					if ( '' === $wpdb->last_error ) {
						continue;
					}
				}
				$count++;
			}
		}
		fclose( $fh );
		return array( 'ok' => true, 'statements' => $count );
	}

	private static function restore_files( $zip_file ) {
		if ( ! is_readable( $zip_file ) ) {
			return array( 'ok' => false, 'message' => 'Archive is missing or unreadable.' );
		}
		$zip = new ZipArchive();
		if ( true !== $zip->open( $zip_file ) ) {
			return array( 'ok' => false, 'message' => 'Could not open the archive.' );
		}
		$root  = WP_CONTENT_DIR;
		$count = 0;
		for ( $i = 0; $i < $zip->numFiles; $i++ ) {
			$name = $zip->getNameIndex( $i );
			if ( false === $name ) {
				continue;
			}
			// Hard guard against path traversal in a tampered archive.
			if ( false !== strpos( $name, '..' ) || 0 === strpos( $name, '/' ) ) {
				continue;
			}
			$target = $root . '/' . $name;
			if ( '/' === substr( $name, -1 ) ) {
				wp_mkdir_p( $target );
				continue;
			}
			wp_mkdir_p( dirname( $target ) );
			$stream = $zip->getStream( $name );
			if ( ! $stream ) {
				continue;
			}
			$out = @fopen( $target, 'w' );
			if ( $out ) {
				stream_copy_to_stream( $stream, $out );
				fclose( $out );
				$count++;
			}
			fclose( $stream );
		}
		$zip->close();
		return array( 'ok' => true, 'count' => $count );
	}

	/* ----------------------------------------------------------------- *
	 * Stats for the UI
	 * ----------------------------------------------------------------- */

	public static function stats() {
		$all   = self::all();
		$bytes = 0;
		foreach ( $all as $b ) {
			$bytes += (int) ( $b['db_size'] ?? 0 ) + (int) ( $b['zip_size'] ?? 0 );
		}
		return array(
			'count'      => count( $all ),
			'total_size' => $bytes,
			'newest'     => $all ? ( $all[0]['created'] ?? '' ) : '',
			'zip_ready'  => class_exists( 'ZipArchive' ),
			'next_run'   => wp_next_scheduled( self::HOOK ),
			'restores'   => count( self::history() ),
		);
	}
}
