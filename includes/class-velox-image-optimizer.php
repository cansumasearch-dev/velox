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

	public function __construct() {
		// Auto-convert on new uploads.
		if ( Velox_Settings::enabled( 'webp_auto_convert', 'module_images' ) ) {
			add_filter( 'wp_generate_attachment_metadata', array( $this, 'on_upload' ), 20, 2 );
		}

		// Optional front-end serving.
		if ( ! is_admin() && Velox_Settings::enabled( 'webp_serve_rewrite', 'module_images' ) ) {
			add_filter( 'wp_get_attachment_image_attributes', array( $this, 'swap_attributes' ), 99, 3 );
			add_filter( 'wp_calculate_image_srcset', array( $this, 'swap_srcset' ), 99, 5 );
		}

		// Clean up webp twins when an attachment is deleted.
		add_action( 'delete_attachment', array( $this, 'on_delete' ) );
	}

	/* ----------------------------------------------------------------
	 * Capability detection
	 * ------------------------------------------------------------- */
	public static function engine() {
		if ( extension_loaded( 'imagick' ) && class_exists( 'Imagick' ) ) {
			$formats = @Imagick::queryFormats( 'WEBP' );
			if ( ! empty( $formats ) ) {
				return 'imagick';
			}
		}
		if ( function_exists( 'imagewebp' ) ) {
			return 'gd';
		}
		return false;
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
		if ( ! preg_match( '/\.(jpe?g|png)$/i', $file ) ) {
			return new WP_Error( 'unsupported', __( 'Only JPG and PNG files can be converted.', 'velox' ) );
		}

		$targets   = array( $file );
		$base_dir  = trailingslashit( dirname( $file ) );
		$meta      = wp_get_attachment_metadata( $attachment_id );

		if ( Velox_Settings::get( 'webp_convert_sizes', true ) && ! empty( $meta['sizes'] ) ) {
			foreach ( $meta['sizes'] as $size ) {
				if ( ! empty( $size['file'] ) ) {
					$targets[] = $base_dir . $size['file'];
				}
			}
		}

		$original_bytes = 0;
		$webp_bytes     = 0;
		$converted      = 0;

		foreach ( $targets as $source ) {
			if ( ! file_exists( $source ) ) {
				continue;
			}
			$dest = preg_replace( '/\.(jpe?g|png)$/i', '.webp', $source );
			$ok   = $this->encode_webp( $source, $dest, $quality );
			if ( $ok && file_exists( $dest ) ) {
				$original_bytes += filesize( $source );
				$webp_bytes     += filesize( $dest );
				$converted++;
			}
		}

		if ( ! $converted ) {
			return new WP_Error( 'failed', __( 'Conversion failed. Check that GD or Imagick supports WebP on this server.', 'velox' ) );
		}

		$saved_pct = $original_bytes > 0 ? round( ( 1 - ( $webp_bytes / $original_bytes ) ) * 100, 1 ) : 0;

		$stats = array(
			'original_bytes' => $original_bytes,
			'webp_bytes'     => $webp_bytes,
			'saved_pct'      => $saved_pct,
			'quality'        => $quality,
			'files'          => $converted,
			'time'           => time(),
		);
		update_post_meta( $attachment_id, self::META_KEY, $stats );

		return $stats;
	}

	/**
	 * Low-level encode. Tries Imagick first, falls back to GD.
	 */
	private function encode_webp( $source, $dest, $quality ) {
		$engine = self::engine();
		if ( 'imagick' === $engine ) {
			try {
				$img = new Imagick( $source );
				if ( $img->getImageColorspace() === Imagick::COLORSPACE_CMYK ) {
					$img->transformImageColorspace( Imagick::COLORSPACE_SRGB );
				}
				$img->setImageFormat( 'webp' );
				$img->setImageCompressionQuality( $quality );
				$img->setOption( 'webp:method', '6' );
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
			$result = imagewebp( $image, $dest, $quality );
			imagedestroy( $image );
			return $result;
		}

		return false;
	}

	/* ----------------------------------------------------------------
	 * Bulk helpers
	 * ------------------------------------------------------------- */
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
			$m = get_post_meta( $id, self::META_KEY, true );
			if ( ! empty( $m ) && ! empty( $m['files'] ) ) {
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

	public function on_upload( $metadata, $attachment_id ) {
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
		foreach ( $targets as $t ) {
			$webp = preg_replace( '/\.(jpe?g|png)$/i', '.webp', $t );
			if ( $webp !== $t && file_exists( $webp ) ) {
				@unlink( $webp );
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

	private function webp_url_if_exists( $url ) {
		if ( ! preg_match( '/\.(jpe?g|png)$/i', $url ) ) {
			return $url;
		}
		$webp_url  = preg_replace( '/\.(jpe?g|png)$/i', '.webp', $url );
		$uploads   = wp_upload_dir();
		$webp_path = str_replace( $uploads['baseurl'], $uploads['basedir'], $webp_url );
		return file_exists( $webp_path ) ? $webp_url : $url;
	}

	public function swap_attributes( $attr, $attachment, $size ) {
		if ( ! $this->browser_supports_webp() ) {
			return $attr;
		}
		if ( ! empty( $attr['src'] ) ) {
			$attr['src'] = $this->webp_url_if_exists( $attr['src'] );
		}
		return $attr;
	}

	public function swap_srcset( $sources, $size_array, $image_src, $image_meta, $attachment_id ) {
		if ( ! $this->browser_supports_webp() || ! is_array( $sources ) ) {
			return $sources;
		}
		foreach ( $sources as $w => $source ) {
			if ( ! empty( $source['url'] ) ) {
				$sources[ $w ]['url'] = $this->webp_url_if_exists( $source['url'] );
			}
		}
		return $sources;
	}
}
