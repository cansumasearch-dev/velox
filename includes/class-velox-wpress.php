<?php
/**
 * Velox — All-in-One WP Migration (.wpress) importer.
 *
 * Unpacks an AiO ".wpress" backup and turns it into a normal Velox backup
 * (a SQL dump + a wp-content files zip) that restores through the existing
 * restore path. Two hard parts handled here:
 *
 *  1. The .wpress binary container. It is not a zip. It is a flat stream of
 *     file blocks, each led by a fixed 4377-byte header:
 *        name   255 bytes   (the file's own name, null-padded)
 *        size    14 bytes   (ASCII decimal byte length, null-padded)
 *        mtime   12 bytes   (ASCII decimal unix mtime)
 *        prefix 4096 bytes  (the directory path the file sits in)
 *     followed by exactly <size> bytes of file content. The stream ends with
 *     a header of 4377 null bytes (EOF marker). We stream through it so a large
 *     backup never has to sit in memory at once.
 *
 *  2. URL rewrite. An AiO backup is almost always from another domain, so the
 *     SQL is full of the old site URL — including inside PHP-serialized values,
 *     where a naive str_replace corrupts the data (the s:LEN: byte counts stop
 *     matching). rewrite_serialized() swaps the URL and fixes those lengths.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Velox_Wpress {

	const HEADER_SIZE = 4377;
	const NAME_SIZE   = 255;
	const SIZE_SIZE   = 14;
	const MTIME_SIZE  = 12;
	const PREFIX_SIZE = 4096;

	/**
	 * Import a .wpress upload. Unpacks it to a SQL dump + a wp-content zip,
	 * rewrites the source URL to this site, and returns the two file paths plus
	 * some counts. Does NOT register the backup — the caller (Velox_Backup::import)
	 * does that so registration stays in one place.
	 *
	 * @param string $wpress_path Path to the uploaded .wpress file.
	 * @param string $dir         Velox backup directory.
	 * @param string $id          Backup id to name the output files with.
	 * @return array{ok:bool,message?:string,db_file?:string,zip_file?:string,tables?:int,files?:int,source_url?:string}
	 */
	public static function import( $wpress_path, $dir, $id ) {
		if ( ! is_readable( $wpress_path ) ) {
			return array( 'ok' => false, 'message' => __( 'The .wpress file could not be read.', 'velox' ) );
		}
		if ( ! class_exists( 'ZipArchive' ) ) {
			return array( 'ok' => false, 'message' => __( 'PHP-zip is unavailable on this server, so .wpress files cannot be imported.', 'velox' ) );
		}

		$work = $dir . '/wpress-' . $id;
		if ( ! wp_mkdir_p( $work ) ) {
			return array( 'ok' => false, 'message' => __( 'Could not create a temporary folder for the import.', 'velox' ) );
		}

		// 1) Extract the container to a working folder.
		$extracted = self::extract( $wpress_path, $work );
		if ( ! $extracted['ok'] ) {
			self::rrmdir( $work );
			return $extracted;
		}

		// 2) Locate the SQL dump (AiO names it database.sql) and read the source URL.
		$sql_src = $work . '/database.sql';
		if ( ! is_readable( $sql_src ) ) {
			self::rrmdir( $work );
			return array( 'ok' => false, 'message' => __( 'This .wpress backup has no database.sql inside it, so it can’t be imported.', 'velox' ) );
		}
		$source_url = self::detect_source_url( $work );
		$target_url = untrailingslashit( home_url() );

		// 3) Rewrite the SQL (source URL -> this site) into the final dump.
		$db_dest = $dir . '/velox-db-' . $id . '.sql';
		$tables  = self::rewrite_sql_file( $sql_src, $db_dest, $source_url, $target_url );
		if ( false === $tables ) {
			self::rrmdir( $work );
			return array( 'ok' => false, 'message' => __( 'Could not write the converted database dump (folder permissions?).', 'velox' ) );
		}

		// 4) Zip the wp-content files (everything except the AiO metadata + the sql).
		$zip_dest = $dir . '/velox-files-' . $id . '.zip';
		$files    = self::zip_content( $work, $zip_dest );

		self::rrmdir( $work );

		$out = array(
			'ok'         => true,
			'db_file'    => basename( $db_dest ),
			'db_size'    => (int) ( @filesize( $db_dest ) ?: 0 ),
			'tables'     => (int) $tables,
			'source_url' => $source_url,
		);
		if ( $files > 0 ) {
			$out['zip_file'] = basename( $zip_dest );
			$out['zip_size'] = (int) ( @filesize( $zip_dest ) ?: 0 );
			$out['files']    = (int) $files;
		} else {
			@unlink( $zip_dest );
		}
		return $out;
	}

	/* ------------------------------------------------------- container parse */

	/**
	 * Walk the .wpress stream and write each embedded file to $work, preserving
	 * its relative path. Streamed in chunks so memory stays flat on big backups.
	 */
	private static function extract( $wpress_path, $work ) {
		$fh = @fopen( $wpress_path, 'rb' );
		if ( ! $fh ) {
			return array( 'ok' => false, 'message' => __( 'The .wpress file could not be opened.', 'velox' ) );
		}
		$count = 0;
		while ( ! feof( $fh ) ) {
			$header = self::read_exact( $fh, self::HEADER_SIZE );
			if ( false === $header || '' === $header ) {
				break; // clean end
			}
			// EOF marker: an all-null header.
			if ( '' === trim( $header, "\0" ) ) {
				break;
			}
			if ( strlen( $header ) < self::HEADER_SIZE ) {
				fclose( $fh );
				return array( 'ok' => false, 'message' => __( 'The .wpress file looks truncated or corrupt.', 'velox' ) );
			}

			$name   = self::field( $header, 0, self::NAME_SIZE );
			$size   = (int) self::field( $header, self::NAME_SIZE, self::SIZE_SIZE );
			$prefix = self::field( $header, self::NAME_SIZE + self::SIZE_SIZE + self::MTIME_SIZE, self::PREFIX_SIZE );

			if ( '' === $name ) {
				break;
			}

			$rel = self::safe_rel_path( $prefix, $name );
			if ( '' === $rel ) {
				// Path failed the safety check — skip its bytes and move on.
				self::skip( $fh, $size );
				continue;
			}
			$dest = $work . '/' . $rel;
			wp_mkdir_p( dirname( $dest ) );

			$out = @fopen( $dest, 'wb' );
			if ( ! $out ) {
				self::skip( $fh, $size );
				continue;
			}
			$remaining = $size;
			while ( $remaining > 0 ) {
				$chunk = self::read_exact( $fh, min( 512000, $remaining ) );
				if ( false === $chunk || '' === $chunk ) {
					break;
				}
				fwrite( $out, $chunk );
				$remaining -= strlen( $chunk );
			}
			fclose( $out );
			$count++;
		}
		fclose( $fh );

		if ( 0 === $count ) {
			return array( 'ok' => false, 'message' => __( 'No files could be read from the .wpress backup.', 'velox' ) );
		}
		return array( 'ok' => true, 'count' => $count );
	}

	/** Read exactly $len bytes (fread can return short), or as many as remain. */
	private static function read_exact( $fh, $len ) {
		$buf = '';
		while ( strlen( $buf ) < $len && ! feof( $fh ) ) {
			$part = fread( $fh, $len - strlen( $buf ) );
			if ( false === $part || '' === $part ) {
				break;
			}
			$buf .= $part;
		}
		return $buf;
	}

	private static function skip( $fh, $len ) {
		// fseek can't be relied on for streams; read-and-discard is safe.
		$remaining = $len;
		while ( $remaining > 0 && ! feof( $fh ) ) {
			$chunk = fread( $fh, min( 512000, $remaining ) );
			if ( false === $chunk || '' === $chunk ) {
				break;
			}
			$remaining -= strlen( $chunk );
		}
	}

	private static function field( $header, $offset, $len ) {
		return trim( substr( $header, $offset, $len ), "\0" );
	}

	/**
	 * Build a safe relative path from the AiO prefix + name, rejecting anything
	 * that tries to escape the working folder.
	 */
	private static function safe_rel_path( $prefix, $name ) {
		$prefix = str_replace( '\\', '/', $prefix );
		$rel    = ltrim( trim( $prefix, '/' ) . '/' . $name, '/' );
		if ( '' === $rel || false !== strpos( $rel, '..' ) || 0 === strpos( $rel, '/' ) ) {
			return '';
		}
		return $rel;
	}

	/* --------------------------------------------------------- source URL */

	/**
	 * Find the URL the backup was taken from. AiO stores it in package.json
	 * (SiteURL / HomeURL) and/or as options in the SQL; we try both.
	 */
	private static function detect_source_url( $work ) {
		$pkg = $work . '/package.json';
		if ( is_readable( $pkg ) ) {
			$meta = json_decode( (string) file_get_contents( $pkg ), true );
			if ( is_array( $meta ) ) {
				foreach ( array( 'SiteURL', 'siteurl', 'HomeURL', 'homeurl', 'URL' ) as $k ) {
					if ( ! empty( $meta[ $k ] ) && filter_var( $meta[ $k ], FILTER_VALIDATE_URL ) ) {
						return untrailingslashit( $meta[ $k ] );
					}
				}
			}
		}
		// Fallback: sniff siteurl from the SQL options insert.
		$sql = $work . '/database.sql';
		$fh  = @fopen( $sql, 'r' );
		if ( $fh ) {
			$scanned = 0;
			while ( false !== ( $line = fgets( $fh ) ) && $scanned < 5000 ) {
				$scanned++;
				if ( preg_match( "/'(siteurl|home)',\s*'(https?:\\/\\/[^']+)'/i", $line, $m ) ) {
					fclose( $fh );
					return untrailingslashit( $m[2] );
				}
			}
			fclose( $fh );
		}
		return '';
	}

	/* ----------------------------------------------------------- SQL rewrite */

	/**
	 * Copy the source SQL to $dest, replacing $from with $to on every line and
	 * fixing PHP-serialized string lengths as it goes. Returns the CREATE TABLE
	 * count, or false on write failure. Line-buffered, so memory stays flat.
	 */
	private static function rewrite_sql_file( $src, $dest, $from, $to ) {
		$in = @fopen( $src, 'r' );
		if ( ! $in ) {
			return false;
		}
		$out = @fopen( $dest, 'w' );
		if ( ! $out ) {
			fclose( $in );
			return false;
		}
		$do_url = ( '' !== $from && $from !== $to );
		$tables = 0;
		while ( false !== ( $line = fgets( $in ) ) ) {
			if ( false !== stripos( $line, 'CREATE TABLE' ) ) {
				$tables++;
			}
			if ( $do_url ) {
				$line = self::rewrite_serialized( $line, $from, $to );
				// Also cover the scheme-relative and escaped-slash variants AiO/WP emit.
				$line = str_replace( self::esc_slashes( $from ), self::esc_slashes( $to ), $line );
			}
			fwrite( $out, $line );
		}
		fclose( $in );
		fclose( $out );
		return $tables;
	}

	/** Escaped-slash form used inside JSON/serialized blobs in the dump. */
	private static function esc_slashes( $url ) {
		return str_replace( '/', '\\/', $url );
	}

	/**
	 * Replace $from with $to across a string while keeping PHP-serialized string
	 * lengths correct. Finds every s:<len>:"...."; whose payload contains $from,
	 * swaps it, and rewrites <len> to the new byte length. Plain (non-serialized)
	 * occurrences are then replaced normally.
	 */
	public static function rewrite_serialized( $data, $from, $to ) {
		if ( '' === $from || false === strpos( $data, $from ) ) {
			return $data;
		}
		// Fix serialized string tokens: s:LEN:"PAYLOAD";
		$data = preg_replace_callback(
			'/s:(\d+):"((?:[^"\\\\]|\\\\.)*?)";/s',
			function ( $m ) use ( $from, $to ) {
				$payload = $m[2];
				if ( false === strpos( $payload, $from ) ) {
					return $m[0];
				}
				$new = str_replace( $from, $to, $payload );
				// Length is the byte length of the actual (unescaped) string. The
				// payload here is still in its dump-escaped form for quotes/backslashes,
				// but URL swaps don't change escaping, so recomputing on the raw new
				// payload with escaping normalised keeps it correct.
				$unescaped = stripcslashes( $new );
				return 's:' . strlen( $unescaped ) . ':"' . $new . '";';
			},
			$data
		);
		// Now any remaining plain occurrences.
		return str_replace( $from, $to, $data );
	}

	/* ----------------------------------------------------------- files zip */

	/**
	 * Zip everything under $work that belongs in wp-content, with paths relative
	 * to wp-content (so the existing restore, which extracts into WP_CONTENT_DIR,
	 * drops them in the right place). Skips AiO's own metadata + the SQL dump.
	 */
	private static function zip_content( $work, $zip_dest ) {
		$zip = new ZipArchive();
		if ( true !== $zip->open( $zip_dest, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			return 0;
		}
		$skip_top = array( 'database.sql', 'package.json', 'multisite.json' );
		$count    = 0;
		$it = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $work, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::LEAVES_ONLY
		);
		foreach ( $it as $file ) {
			if ( ! $file->isFile() ) {
				continue;
			}
			$full = $file->getPathname();
			$rel  = ltrim( str_replace( '\\', '/', substr( $full, strlen( $work ) ) ), '/' );
			$top  = explode( '/', $rel )[0];
			if ( in_array( $top, $skip_top, true ) ) {
				continue;
			}
			// AiO lays wp-content files at the root of the archive (uploads/, plugins/,
			// themes/…). If a backup nests them under a wp-content/ folder, strip it so
			// paths stay relative to WP_CONTENT_DIR.
			if ( 0 === strpos( $rel, 'wp-content/' ) ) {
				$rel = substr( $rel, strlen( 'wp-content/' ) );
			}
			$zip->addFile( $full, $rel );
			$count++;
		}
		$zip->close();
		return $count;
	}

	/* ------------------------------------------------------------- utils */

	private static function rrmdir( $path ) {
		if ( ! is_dir( $path ) ) {
			@unlink( $path );
			return;
		}
		$items = @scandir( $path );
		if ( is_array( $items ) ) {
			foreach ( $items as $item ) {
				if ( '.' === $item || '..' === $item ) {
					continue;
				}
				self::rrmdir( $path . '/' . $item );
			}
		}
		@rmdir( $path );
	}
}
