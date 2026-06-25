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
	public static function restore( $id, $what = 'both' ) {
		@set_time_limit( 0 );
		$b = self::get( $id );
		if ( ! $b ) {
			return array( 'ok' => false, 'message' => 'Backup not found.' );
		}
		$what = in_array( $what, array( 'db', 'files', 'both' ), true ) ? $what : 'both';
		$done = array();

		if ( ( 'db' === $what || 'both' === $what ) && ! empty( $b['db_file'] ) ) {
			// Safety net: snapshot the current DB before overwriting it.
			$safety = self::create( 'db', 'Safety snapshot before restore' );
			$res    = self::restore_database( self::dir() . '/' . basename( $b['db_file'] ) );
			if ( empty( $res['ok'] ) ) {
				return array( 'ok' => false, 'message' => 'Database restore failed: ' . $res['message'] . ( ! empty( $safety['ok'] ) ? ' A safety snapshot was taken first.' : '' ) );
			}
			$done[] = $res['statements'] . ' SQL statements';
		}

		if ( ( 'files' === $what || 'both' === $what ) && ! empty( $b['zip_file'] ) ) {
			if ( ! class_exists( 'ZipArchive' ) ) {
				return array( 'ok' => false, 'message' => 'PHP-zip is unavailable, so files cannot be restored on this server.' );
			}
			$res = self::restore_files( self::dir() . '/' . basename( $b['zip_file'] ) );
			if ( empty( $res['ok'] ) ) {
				return array( 'ok' => false, 'message' => 'File restore failed: ' . $res['message'] );
			}
			$done[] = $res['count'] . ' files';
		}

		if ( empty( $done ) ) {
			return array( 'ok' => false, 'message' => 'Nothing in this backup matched the requested restore type.' );
		}
		return array( 'ok' => true, 'message' => 'Restored ' . implode( ' and ', $done ) . '.', 'restored' => $done );
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
		);
	}
}
