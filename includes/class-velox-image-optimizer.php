<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WebP conversion engine.
 *
 * Design rules:
 *  - Never deletes original files (keeps JPG/PNG fallbacks).
 *  - Generates a .webp twin next to each source (and next to every thumbnail size).
 *  - Stores before/after byte counts in attachment meta for the comparator UI.
 *  - Front-end serving is OPT-IN and surgical: it only rewrites WordPress-rendered
 *    <img> attributes (which is what Oxygen Image elements output) and only when the
 *    browser advertises webp support. Oxygen CSS background-images are not auto-swapped.
 */
class Velox_Image_Optimizer {

	const META_KEY = '_velox_webp';

	/** Guards against re-entering conversion while we regenerate thumbnails. */
	private static $busy = false;

	public function __construct() {
		// Auto-convert on new uploads.
		if ( Velox_Settings::enabled( 'webp_auto_convert', 'module_images' ) ) {
			add_filter( 'wp_generate_attachment_metadata', array( $this, 'on_upload' ), 20, 2 );
		}

		// Front-end serving (on by default): swap image URLs to WebP/AVIF.
		if ( ! is_admin() && Velox_Settings::enabled( 'webp_serve_rewrite', 'module_images' ) ) {
			// Fast path for WordPress-rendered images.
			add_filter( 'wp_get_attachment_image_attributes', array( $this, 'swap_attributes' ), 99, 3 );
			add_filter( 'wp_calculate_image_srcset', array( $this, 'swap_srcset' ), 99, 5 );
			// Catch-all for Oxygen elements, CSS background-images, hard-coded and
			// content <img> tags — anything WordPress doesn't render itself.
			add_action( 'template_redirect', array( $this, 'start_buffer' ), 1 );
		}

		// Clean up webp twins when an attachment is deleted.
		add_action( 'delete_attachment', array( $this, 'on_delete' ) );

		// Show a Velox line under the "Add media files" uploader.
		if ( is_admin() && Velox_Settings::enabled( 'image_webp', 'module_images' ) ) {
			add_action( 'post-upload-ui', array( $this, 'upload_hint' ) );
			add_action( 'admin_head-upload.php', array( $this, 'media_library_button' ) );
			add_action( 'admin_menu', array( $this, 'media_submenu' ), 20 );
		}

		// Downscale oversized uploads to the configured max width (0 = off).
		$max_w = (int) Velox_Settings::get( 'image_max_width', 2560 );
		if ( $max_w > 0 ) {
			add_filter( 'big_image_size_threshold', function () use ( $max_w ) {
				return $max_w;
			}, 99 );
		}
	}

	/* ----------------------------------------------------------------
	 * Capability detection
	 * ------------------------------------------------------------- */
	public static function engine() {
		return self::resolve_engine( 'WEBP', 'imagewebp' );
	}

	/** AVIF support is rarer than WebP — Imagick needs an AVIF delegate, GD needs PHP 8.1+. */
	public static function avif_engine() {
		return self::resolve_engine( 'AVIF', 'imageavif' );
	}

	/** Resolve the encoder, honouring the user's engine preference when it can do the job. */
	private static function resolve_engine( $imagick_format, $gd_func ) {
		$pref    = Velox_Settings::get( 'image_engine', 'auto' );
		$imagick = extension_loaded( 'imagick' ) && class_exists( 'Imagick' ) && ! empty( @Imagick::queryFormats( $imagick_format ) );
		$gd      = function_exists( $gd_func );
		if ( 'imagick' === $pref && $imagick ) {
			return 'imagick';
		}
		if ( 'gd' === $pref && $gd ) {
			return 'gd';
		}
		if ( $imagick ) {
			return 'imagick';
		}
		if ( $gd ) {
			return 'gd';
		}
		return false;
	}

	/** Per-engine availability + format support, for the compatibility panel. */
	public static function capabilities() {
		$im = extension_loaded( 'imagick' ) && class_exists( 'Imagick' );
		return array(
			'imagick' => array(
				'label'     => 'Imagick',
				'available' => $im,
				'webp'      => $im && ! empty( @Imagick::queryFormats( 'WEBP' ) ),
				'avif'      => $im && ! empty( @Imagick::queryFormats( 'AVIF' ) ),
				'note'      => 'Best quality and format support. Recommended when available.',
			),
			'gd' => array(
				'label'     => 'GD',
				'available' => function_exists( 'imagecreatetruecolor' ),
				'webp'      => function_exists( 'imagewebp' ),
				'avif'      => function_exists( 'imageavif' ),
				'note'      => 'Bundled with most PHP installs. Solid WebP; AVIF on PHP 8.1+.',
			),
			'vips' => array(
				'label'     => 'libvips',
				'available' => extension_loaded( 'vips' ),
				'webp'      => extension_loaded( 'vips' ),
				'avif'      => extension_loaded( 'vips' ),
				'note'      => 'Fastest and lowest memory, but the PHP extension is rarely installed on shared hosts.',
			),
		);
	}

	/** Is AVIF generation switched on AND supported by this server? */
	public static function avif_active() {
		return Velox_Settings::get( 'image_avif' ) && false !== self::avif_engine();
	}

	/* ----------------------------------------------------------------
	 * Single attachment conversion
	 * ------------------------------------------------------------- */
	public function convert_attachment( $attachment_id, $quality = null ) {
		$quality = null === $quality ? (int) Velox_Settings::get( 'webp_quality', 80 ) : (int) $quality;
		$quality = max( 1, min( 100, $quality ) );

		$file = get_attached_file( $attachment_id );
		if ( ! $file || ! file_exists( $file ) ) {
			return new WP_Error( 'no_file', __( 'Source file not found.', 'velox' ) );
		}
		// Already a WebP attachment (e.g. converted in a previous run) — nothing to do.
		if ( preg_match( '/\.webp$/i', $file ) ) {
			$existing = get_post_meta( $attachment_id, self::META_KEY, true );
			if ( ! empty( $existing['files'] ) ) {
				return $existing;
			}
		}
		if ( ! preg_match( '/\.(jpe?g|png)$/i', $file ) ) {
			return new WP_Error( 'unsupported', __( 'Only JPG and PNG files can be converted.', 'velox' ) );
		}

		// Default path: replace the original with a real WebP so the media library
		// shows WebP and the reported size matches the file on disk.
		if ( (bool) Velox_Settings::get( 'image_replace', true ) ) {
			return $this->replace_inplace( $attachment_id, $file, $quality );
		}

		return $this->convert_twins( $attachment_id, $file, $quality );
	}

	/**
	 * Replace the attachment's file (and thumbnails) with WebP in place.
	 * The image is resized to the configured width first (down-only, height auto).
	 */
	private function replace_inplace( $attachment_id, $file, $quality ) {
		$do_webp = (bool) Velox_Settings::get( 'image_webp', true );
		if ( ! $do_webp ) {
			return new WP_Error( 'no_format', __( 'Enable WebP output in Images settings first.', 'velox' ) );
		}

		$orig_bytes = (int) filesize( $file );
		$dest       = preg_replace( '/\.(jpe?g|png)$/i', '.webp', $file );

		if ( ! $this->encode_webp( $file, $dest, $quality ) || ! file_exists( $dest ) ) {
			return new WP_Error( 'failed', __( 'Conversion failed. Check that GD or Imagick supports WebP on this server.', 'velox' ) );
		}
		$webp_bytes = (int) filesize( $dest );

		// Optional AVIF twin next to the new WebP, for capable browsers.
		$avif_bytes = 0;
		$avif_files = 0;
		if ( self::avif_active() ) {
			$avif_dest = preg_replace( '/\.webp$/i', '.avif', $dest );
			if ( $this->encode_avif( $file, $avif_dest, $quality ) && file_exists( $avif_dest ) ) {
				$avif_bytes = (int) filesize( $avif_dest );
				$avif_files = 1;
			}
		}

		// Remove the old thumbnails (they're rebuilt as WebP below).
		$old_meta = wp_get_attachment_metadata( $attachment_id );
		$base_dir = trailingslashit( dirname( $file ) );
		if ( ! empty( $old_meta['sizes'] ) ) {
			foreach ( $old_meta['sizes'] as $size ) {
				if ( ! empty( $size['file'] ) && file_exists( $base_dir . $size['file'] ) ) {
					@unlink( $base_dir . $size['file'] );
				}
			}
		}
		// Keep the original file on disk as a fallback (default) so hard-coded links
		// and browsers without WebP support still resolve; only delete it if the user
		// explicitly turned the fallback off.
		if ( ! (bool) Velox_Settings::get( 'webp_keep_original', true ) && $file !== $dest && file_exists( $file ) ) {
			@unlink( $file );
		}

		// Point the attachment at the WebP, fix its mime type, and rebuild thumbnails.
		update_attached_file( $attachment_id, $dest );
		wp_update_post( array( 'ID' => $attachment_id, 'post_mime_type' => 'image/webp' ) );

		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}
		self::$busy = true;
		$new_meta   = wp_generate_attachment_metadata( $attachment_id, $dest );
		self::$busy = false;
		if ( is_array( $new_meta ) ) {
			wp_update_attachment_metadata( $attachment_id, $new_meta );
		}

		$saved_pct = $orig_bytes > 0 ? round( ( 1 - ( $webp_bytes / $orig_bytes ) ) * 100, 1 ) : 0;
		$stats     = array(
			'original_bytes' => $orig_bytes,
			'webp_bytes'     => $webp_bytes,
			'saved_pct'      => max( 0, $saved_pct ),
			'quality'        => $quality,
			'files'          => 1,
			'avif_bytes'     => $avif_bytes,
			'avif_files'     => $avif_files,
			'replaced'       => true,
			'time'           => time(),
		);
		update_post_meta( $attachment_id, self::META_KEY, $stats );
		delete_post_meta( $attachment_id, '_velox_webp_estimate' );
		return $stats;
	}

	/**
	 * Legacy behaviour: keep the original and generate .webp/.avif twins beside it.
	 * Reports the MAIN image's before/after bytes (not the sum of every thumbnail),
	 * so the numbers match what the media library shows.
	 */
	private function convert_twins( $attachment_id, $file, $quality ) {
		$base_dir  = trailingslashit( dirname( $file ) );
		$meta      = wp_get_attachment_metadata( $attachment_id );
		$targets   = array( $file );

		if ( Velox_Settings::get( 'webp_convert_sizes', true ) && ! empty( $meta['sizes'] ) ) {
			foreach ( $meta['sizes'] as $size ) {
				if ( ! empty( $size['file'] ) ) {
					$targets[] = $base_dir . $size['file'];
				}
			}
		}

		$main_orig  = 0; // main image only — matches what the media library shows
		$main_webp  = 0;
		$converted  = 0;
		$avif_bytes = 0;
		$avif_files = 0;
		$do_webp    = (bool) Velox_Settings::get( 'image_webp', true );
		$do_avif    = self::avif_active();

		if ( ! $do_webp && ! $do_avif ) {
			return new WP_Error( 'no_format', __( 'No output format selected — enable WebP and/or AVIF in Images settings.', 'velox' ) );
		}

		foreach ( $targets as $i => $source ) {
			if ( ! file_exists( $source ) ) {
				continue;
			}
			$is_main = ( 0 === $i );
			if ( $do_webp ) {
				$dest = preg_replace( '/\.(jpe?g|png)$/i', '.webp', $source );
				$ok   = $this->encode_webp( $source, $dest, $quality );
				if ( $ok && file_exists( $dest ) ) {
					if ( $is_main ) {
						$main_orig = (int) filesize( $source );
						$main_webp = (int) filesize( $dest );
					}
					$converted++;
				}
			}
			if ( $do_avif ) {
				$avif_dest = preg_replace( '/\.(jpe?g|png)$/i', '.avif', $source );
				if ( $this->encode_avif( $source, $avif_dest, $quality ) && file_exists( $avif_dest ) ) {
					if ( $is_main ) {
						$avif_bytes = (int) filesize( $avif_dest );
						$avif_files = 1;
					}
				}
			}
		}

		if ( ! $converted ) {
			return new WP_Error( 'failed', __( 'Conversion failed. Check that GD or Imagick supports WebP on this server.', 'velox' ) );
		}

		$saved_pct = $main_orig > 0 ? round( ( 1 - ( $main_webp / $main_orig ) ) * 100, 1 ) : 0;

		$stats = array(
			'original_bytes' => $main_orig,
			'webp_bytes'     => $main_webp,
			'saved_pct'      => max( 0, $saved_pct ),
			'quality'        => $quality,
			'files'          => $converted,
			'avif_bytes'     => $avif_bytes,
			'avif_files'     => $avif_files,
			'replaced'       => false,
			'time'           => time(),
		);
		update_post_meta( $attachment_id, self::META_KEY, $stats );

		return $stats;
	}

	/**
	 * Low-level AVIF encode — mirrors encode_webp. Tries Imagick, falls back to GD (PHP 8.1+).
	 */
	private function encode_avif( $source, $dest, $quality ) {
		$keep_exif = (bool) Velox_Settings::get( 'image_keep_exif', false );
		$max_w     = (int) Velox_Settings::get( 'image_max_width', 2560 );
		$engine    = self::avif_engine();

		if ( 'imagick' === $engine ) {
			try {
				$img = new Imagick( $source );
				if ( $img->getImageColorspace() === Imagick::COLORSPACE_CMYK ) {
					$img->transformImageColorspace( Imagick::COLORSPACE_SRGB );
				}
				if ( $max_w > 0 && $img->getImageWidth() > $max_w ) {
					$img->resizeImage( $max_w, 0, Imagick::FILTER_LANCZOS, 1 );
				}
				if ( ! $keep_exif ) {
					$profiles = $img->getImageProfiles( 'icc', true );
					$img->stripImage();
					if ( ! empty( $profiles['icc'] ) ) {
						$img->profileImage( 'icc', $profiles['icc'] );
					}
				}
				$img->setImageFormat( 'avif' );
				$img->setImageCompressionQuality( $quality );
				$result = $img->writeImage( $dest );
				$img->clear();
				$img->destroy();
				return $result;
			} catch ( Exception $e ) {
				// fall through to GD
			}
		}

		if ( function_exists( 'imageavif' ) ) {
			$info = @getimagesize( $source );
			if ( ! $info ) {
				return false;
			}
			switch ( $info[2] ) {
				case IMAGETYPE_JPEG:
					$image = @imagecreatefromjpeg( $source );
					break;
				case IMAGETYPE_PNG:
					$image = @imagecreatefrompng( $source );
					if ( $image ) {
						imagepalettetotruecolor( $image );
						imagealphablending( $image, true );
						imagesavealpha( $image, true );
					}
					break;
				default:
					return false;
			}
			if ( ! $image ) {
				return false;
			}
			if ( $max_w > 0 && imagesx( $image ) > $max_w ) {
				$scaled = imagescale( $image, $max_w );
				if ( $scaled ) {
					imagedestroy( $image );
					$image = $scaled;
				}
			}
			$result = imageavif( $image, $dest, $quality );
			imagedestroy( $image );
			return $result;
		}

		return false;
	}

	/**
	 * Low-level encode. Tries Imagick first, falls back to GD.
	 */
	private function encode_webp( $source, $dest, $quality ) {
		$keep_exif = (bool) Velox_Settings::get( 'image_keep_exif', false );
		$max_w     = (int) Velox_Settings::get( 'image_max_width', 2560 );
		$engine    = self::engine();
		if ( 'imagick' === $engine ) {
			try {
				$img = new Imagick( $source );
				if ( $img->getImageColorspace() === Imagick::COLORSPACE_CMYK ) {
					$img->transformImageColorspace( Imagick::COLORSPACE_SRGB );
				}
				if ( $max_w > 0 && $img->getImageWidth() > $max_w ) {
					$img->resizeImage( $max_w, 0, Imagick::FILTER_LANCZOS, 1 );
				}
				if ( ! $keep_exif ) {
					$profiles = $img->getImageProfiles( 'icc', true );
					$img->stripImage(); // removes EXIF/GPS/etc.
					if ( ! empty( $profiles['icc'] ) ) {
						$img->profileImage( 'icc', $profiles['icc'] ); // keep colour accuracy
					}
				}
				$img->setImageFormat( 'webp' );
				$img->setImageCompressionQuality( $quality );
				$img->setOption( 'webp:method', '6' );
				if ( Velox_Settings::get( 'image_lossless', false ) ) {
					$img->setOption( 'webp:lossless', 'true' );
				}
				$result = $img->writeImage( $dest );
				$img->clear();
				$img->destroy();
				return $result;
			} catch ( Exception $e ) {
				// fall through to GD
			}
		}

		if ( function_exists( 'imagewebp' ) ) {
			$info = @getimagesize( $source );
			if ( ! $info ) {
				return false;
			}
			switch ( $info[2] ) {
				case IMAGETYPE_JPEG:
					$image = @imagecreatefromjpeg( $source );
					break;
				case IMAGETYPE_PNG:
					$image = @imagecreatefrompng( $source );
					if ( $image ) {
						imagepalettetotruecolor( $image );
						imagealphablending( $image, true );
						imagesavealpha( $image, true );
					}
					break;
				default:
					return false;
			}
			if ( ! $image ) {
				return false;
			}
			// GD always drops metadata, so EXIF is stripped here regardless.
			if ( $max_w > 0 && imagesx( $image ) > $max_w ) {
				$scaled = imagescale( $image, $max_w );
				if ( $scaled ) {
					imagedestroy( $image );
					$image = $scaled;
				}
			}
			$result = imagewebp( $image, $dest, $quality );
			imagedestroy( $image );
			return $result;
		}

		return false;
	}

	/**
	 * Estimate (or read) the before/after byte sizes for one attachment's full image.
	 * Converted images report their real saved bytes; others are test-encoded once and cached.
	 */
	public function estimate( $attachment_id, $quality = null ) {
		$quality = null === $quality ? (int) Velox_Settings::get( 'webp_quality', 80 ) : (int) $quality;
		$quality = max( 1, min( 100, $quality ) );
		$max_w   = (int) Velox_Settings::get( 'image_max_width', 2560 );

		$file = get_attached_file( $attachment_id );
		if ( ! $file || ! file_exists( $file ) ) {
			return new WP_Error( 'no_file', __( 'Source file not found.', 'velox' ) );
		}
		$orig = (int) filesize( $file );

		// Already optimized (in replace mode: main file is WebP) → return stored numbers.
		// Old twin-mode conversions still on PNG/JPG fall through to be replaced.
		if ( self::is_done( $attachment_id ) ) {
			$done = get_post_meta( $attachment_id, self::META_KEY, true );
			return array( 'original' => (int) $done['original_bytes'], 'webp' => (int) $done['webp_bytes'], 'converted' => true );
		}

		if ( ! preg_match( '/\.(jpe?g|png)$/i', $file ) ) {
			return array( 'original' => $orig, 'webp' => 0, 'converted' => false, 'unsupported' => true );
		}

		// Cached estimate still valid for this quality + max width?
		$cache = get_post_meta( $attachment_id, '_velox_webp_estimate', true );
		if ( is_array( $cache ) && (int) $cache['q'] === $quality && (int) $cache['w'] === $max_w && (int) $cache['orig'] === $orig ) {
			return array( 'original' => $orig, 'webp' => (int) $cache['webp'], 'converted' => false );
		}

		$tmp = wp_tempnam( 'velox-est' ) . '.webp';
		$ok  = $this->encode_webp( $file, $tmp, $quality );
		$webp_bytes = ( $ok && file_exists( $tmp ) ) ? (int) filesize( $tmp ) : 0;
		if ( file_exists( $tmp ) ) {
			@unlink( $tmp );
		}
		if ( $webp_bytes > 0 ) {
			update_post_meta( $attachment_id, '_velox_webp_estimate', array( 'q' => $quality, 'w' => $max_w, 'orig' => $orig, 'webp' => $webp_bytes ) );
		}
		return array( 'original' => $orig, 'webp' => $webp_bytes, 'converted' => false );
	}

	/* ----------------------------------------------------------------
	 * Bulk helpers
	 * ------------------------------------------------------------- */
	/**
	 * Has this attachment actually been optimized? In replace mode that means its
	 * main file is now WebP — old twin-mode conversions (still PNG/JPG) count as
	 * not done, so they get picked up and replaced on the next run.
	 */
	public static function is_done( $attachment_id ) {
		$m = get_post_meta( $attachment_id, self::META_KEY, true );
		if ( empty( $m ) || empty( $m['files'] ) ) {
			return false;
		}
		if ( ! (bool) Velox_Settings::get( 'image_replace', true ) ) {
			return true;
		}
		$file = get_attached_file( $attachment_id );
		return $file && preg_match( '/\.webp$/i', $file );
	}

	public static function get_convertible_ids() {
		$q = new WP_Query( array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'post_mime_type' => array( 'image/jpeg', 'image/png' ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		) );
		return $q->posts;
	}

	public static function library_stats() {
		$ids   = self::get_convertible_ids();
		$total = count( $ids );
		$done  = 0;
		$saved = 0;
		foreach ( $ids as $id ) {
			if ( self::is_done( $id ) ) {
				$m = get_post_meta( $id, self::META_KEY, true );
				$done++;
				$saved += ( (int) $m['original_bytes'] - (int) $m['webp_bytes'] );
			}
		}
		return array(
			'total'       => $total,
			'done'        => $done,
			'pending'     => max( 0, $total - $done ),
			'saved_bytes' => $saved,
		);
	}

	/** All attachments Velox has converted, newest first — powers the converted-images screen. */
	public static function get_converted() {
		$q = new WP_Query( array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'post_mime_type' => array( 'image/webp', 'image/jpeg', 'image/png' ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'meta_key'       => self::META_KEY, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		) );
		$out = array();
		foreach ( $q->posts as $id ) {
			$m = get_post_meta( $id, self::META_KEY, true );
			if ( empty( $m['files'] ) ) {
				continue;
			}
			$out[] = array(
				'id'        => (int) $id,
				'title'     => get_the_title( $id ),
				'thumb'     => wp_get_attachment_image_url( $id, 'thumbnail' ),
				'url'       => wp_get_attachment_image_url( $id, 'full' ),
				'orig'      => (int) $m['original_bytes'],
				'webp'      => (int) $m['webp_bytes'],
				'saved_pct' => isset( $m['saved_pct'] ) ? (float) $m['saved_pct'] : 0,
				'replaced'  => ! empty( $m['replaced'] ),
				'time'      => isset( $m['time'] ) ? (int) $m['time'] : 0,
			);
		}
		usort( $out, function ( $a, $b ) {
			return $b['time'] <=> $a['time'];
		} );
		return $out;
	}

	/** Adds "Optimize Images" under the WordPress Media menu, linking to the Velox optimizer. */
	public function media_submenu() {
		if ( ! current_user_can( 'upload_files' ) ) {
			return;
		}
		add_submenu_page(
			'upload.php',
			__( 'Optimize Images', 'velox' ),
			__( 'Optimize Images', 'velox' ),
			'upload_files',
			'admin.php?page=velox-images'
		);
	}

	/** Adds an "Optimize images" action button to the Media Library screen header. */
	public function media_library_button() {
		if ( ! current_user_can( 'upload_files' ) ) {
			return;
		}
		$url = admin_url( 'admin.php?page=velox-images' );
		?>
		<script>
		( function () {
			function add() {
				if ( document.getElementById( 'velox-optimize-link' ) ) { return; }
				var heading = document.querySelector( '.wrap .wp-heading-inline' );
				if ( ! heading ) { return; }
				var link = document.createElement( 'a' );
				link.id = 'velox-optimize-link';
				link.href = <?php echo wp_json_encode( $url ); ?>;
				link.className = 'page-title-action';
				link.textContent = 'Optimize images';
				var actions = document.querySelectorAll( '.wrap .page-title-action' );
				if ( actions.length ) { actions[ actions.length - 1 ].insertAdjacentElement( 'afterend', link ); }
				else { heading.insertAdjacentElement( 'afterend', link ); }
			}
			if ( document.readyState !== 'loading' ) { add(); }
			else { document.addEventListener( 'DOMContentLoaded', add ); }
		} )();
		</script>
		<?php
	}

	/** Line shown under the "Add media files" uploader. */
	public function upload_hint() {
		$replace = (bool) Velox_Settings::get( 'image_replace', true );
		$auto    = Velox_Settings::enabled( 'webp_auto_convert', 'module_images' );
		$url     = admin_url( 'admin.php?page=velox-images' );
		echo '<p class="velox-upload-note">';
		echo '<span class="velox-upload-badge">Velox</span> ';
		if ( $auto ) {
			echo esc_html( $replace ? 'New images are converted to WebP automatically.' : 'New images get a WebP copy automatically.' );
		} else {
			echo 'Convert images to WebP after uploading — ';
			echo '<a href="' . esc_url( $url ) . '">open the optimizer</a>.';
		}
		echo '</p>';
	}

	public function on_upload( $metadata, $attachment_id ) {
		if ( self::$busy ) {
			return $metadata; // we're mid-regeneration — don't recurse
		}
		$this->convert_attachment( $attachment_id );
		return $metadata;
	}

	public function on_delete( $attachment_id ) {
		$file = get_attached_file( $attachment_id );
		if ( ! $file ) {
			return;
		}
		$base_dir = trailingslashit( dirname( $file ) );
		$meta     = wp_get_attachment_metadata( $attachment_id );
		$targets  = array( $file );
		if ( ! empty( $meta['sizes'] ) ) {
			foreach ( $meta['sizes'] as $size ) {
				if ( ! empty( $size['file'] ) ) {
					$targets[] = $base_dir . $size['file'];
				}
			}
		}
		// Remove every format sibling: the served file, its webp/avif twins, and any
		// kept original .jpg/.png fallback — whatever extension the attachment now has.
		foreach ( $targets as $t ) {
			$stem = preg_replace( '/\.(jpe?g|png|webp|avif)$/i', '', $t );
			if ( $stem === $t ) {
				continue;
			}
			foreach ( array( '.jpg', '.jpeg', '.png', '.webp', '.avif' ) as $ext ) {
				$sib = $stem . $ext;
				if ( file_exists( $sib ) ) {
					@unlink( $sib );
				}
			}
		}
		delete_post_meta( $attachment_id, self::META_KEY );
	}

	/* ----------------------------------------------------------------
	 * Front-end serving (opt-in)
	 * ------------------------------------------------------------- */
	private function browser_supports_webp() {
		return isset( $_SERVER['HTTP_ACCEPT'] ) && false !== strpos( $_SERVER['HTTP_ACCEPT'], 'image/webp' );
	}

	private function browser_supports_avif() {
		return isset( $_SERVER['HTTP_ACCEPT'] ) && false !== strpos( $_SERVER['HTTP_ACCEPT'], 'image/avif' );
	}

	/** Return the best available twin for this URL: AVIF, then WebP, else the original. */
	private function best_url_if_exists( $url ) {
		if ( ! preg_match( '/\.(jpe?g|png|webp)$/i', $url ) ) {
			return $url;
		}
		$uploads = wp_upload_dir();
		if ( empty( $uploads['baseurl'] ) ) {
			return $url;
		}
		// Scheme-tolerant URL→path (handles http, https and protocol-relative //).
		$to_path = function ( $u ) use ( $uploads ) {
			$u2 = preg_replace( '#^https?:#', '', $u );
			$b2 = preg_replace( '#^https?:#', '', $uploads['baseurl'] );
			return str_replace( $b2, $uploads['basedir'], $u2 );
		};
		// Prefer AVIF when the browser accepts it and a twin exists.
		if ( $this->browser_supports_avif() ) {
			$avif_url = preg_replace( '/\.(jpe?g|png|webp)$/i', '.avif', $url );
			if ( file_exists( $to_path( $avif_url ) ) ) {
				return $avif_url;
			}
		}
		// WebP twin applies to jpg/png sources (a .webp is already served as-is).
		if ( $this->browser_supports_webp() && preg_match( '/\.(jpe?g|png)$/i', $url ) ) {
			$webp_url = preg_replace( '/\.(jpe?g|png)$/i', '.webp', $url );
			if ( file_exists( $to_path( $webp_url ) ) ) {
				return $webp_url;
			}
		}
		return $url;
	}

	public function swap_attributes( $attr, $attachment, $size ) {
		if ( ! $this->browser_supports_webp() && ! $this->browser_supports_avif() ) {
			return $attr;
		}
		if ( ! empty( $attr['src'] ) ) {
			$attr['src'] = $this->best_url_if_exists( $attr['src'] );
		}
		return $attr;
	}

	public function swap_srcset( $sources, $size_array, $image_src, $image_meta, $attachment_id ) {
		if ( ( ! $this->browser_supports_webp() && ! $this->browser_supports_avif() ) || ! is_array( $sources ) ) {
			return $sources;
		}
		foreach ( $sources as $w => $source ) {
			if ( ! empty( $source['url'] ) ) {
				$sources[ $w ]['url'] = $this->best_url_if_exists( $source['url'] );
			}
		}
		return $sources;
	}

	/**
	 * Start buffering the page so we can swap EVERY uploads image URL to WebP/AVIF —
	 * including Oxygen <img> elements, CSS background-images and hard-coded links that
	 * WordPress never renders itself. Only runs for browsers that accept the format.
	 */
	public function start_buffer() {
		if ( is_admin() || is_feed() || is_embed() ) {
			return;
		}
		if ( ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return;
		}
		if ( ! $this->browser_supports_webp() && ! $this->browser_supports_avif() ) {
			return; // no point rewriting for a browser that can't display the result
		}
		ob_start( array( $this, 'rewrite_html' ) );
	}

	/** Swap uploads .jpg/.jpeg/.png URLs to their WebP/AVIF twin when one exists on disk. */
	public function rewrite_html( $html ) {
		if ( ! is_string( $html ) || '' === $html || stripos( $html, '<img' ) === false && stripos( $html, 'url(' ) === false ) {
			return $html;
		}
		$uploads = wp_upload_dir();
		if ( empty( $uploads['baseurl'] ) ) {
			return $html;
		}
		// Match any uploads-dir image URL, protocol-relative or absolute.
		$base    = preg_quote( preg_replace( '#^https?:#', '', $uploads['baseurl'] ), '#' );
		$pattern = '#(https?:)?' . $base . '[^\s"\'\\\\)]+?\.(?:jpe?g|png)#i';
		$cache   = array();

		return preg_replace_callback(
			$pattern,
			function ( $m ) use ( &$cache ) {
				$url = $m[0];
				if ( ! isset( $cache[ $url ] ) ) {
					$cache[ $url ] = $this->best_url_if_exists( $url );
				}
				return $cache[ $url ];
			},
			$html
		);
	}
}
