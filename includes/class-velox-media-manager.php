<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Media manager — the careful stuff.
 *
 * Renaming an uploaded file is the part that normally breaks a site, because the
 * old URL is referenced all over post_content and (serialized) meta. This class
 * renames the main file AND every thumbnail size AND any .webp twins, rewrites
 * _wp_attached_file + _wp_attachment_metadata, and then does a serialization-safe
 * search/replace of every old URL across posts and postmeta. The attachment guid
 * is updated too so the media library URL stays consistent.
 */
class Velox_Media_Manager {

	public function __construct() {
		// This class is also instantiated inside AJAX handlers; only wire the
		// attachment UI on real admin page loads.
		if ( is_admin() && ! wp_doing_ajax() ) {
			add_filter( 'attachment_fields_to_edit', array( $this, 'attachment_resize_field' ), 20, 2 );
			add_action( 'admin_enqueue_scripts', array( $this, 'modal_assets' ) );
		}
	}

	/**
	 * Adds the resize controls to WordPress's own Attachment details panel, so
	 * they're available in the media library modal as well as Velox's own grid.
	 */
	public function attachment_resize_field( $fields, $post ) {
		// The resize endpoint is gated on manage_options, so don't offer the
		// controls to users whose request would just be refused.
		if ( ! current_user_can( 'manage_options' ) ) {
			return $fields;
		}
		if ( 0 !== strpos( (string) $post->post_mime_type, 'image/' ) || 'image/svg+xml' === $post->post_mime_type ) {
			return $fields;
		}
		$meta = wp_get_attachment_metadata( $post->ID );
		$w    = isset( $meta['width'] ) ? (int) $meta['width'] : 0;
		$h    = isset( $meta['height'] ) ? (int) $meta['height'] : 0;
		if ( $w < 1 || $h < 1 ) {
			return $fields;
		}

		ob_start();
		?>
		<div class="velox-rz" data-id="<?php echo (int) $post->ID; ?>" data-w="<?php echo $w; ?>" data-h="<?php echo $h; ?>">
			<div class="velox-rz-row">
				<input type="number" class="velox-rz-w" min="1" max="12000" value="<?php echo $w; ?>" aria-label="Width">
				<button type="button" class="velox-rz-lock is-on" aria-pressed="true" title="Keep proportions">&#128279;</button>
				<input type="number" class="velox-rz-h" min="1" max="12000" value="<?php echo $h; ?>" aria-label="Height">
			</div>
			<div class="velox-rz-row velox-rz-presets">
				<button type="button" data-scale="0.5">50%</button>
				<button type="button" data-scale="0.75">75%</button>
				<button type="button" data-scale="1">100%</button>
				<button type="button" data-scale="1.5">150%</button>
				<button type="button" data-scale="2">200%</button>
			</div>
			<button type="button" class="button button-primary velox-rz-go">Resize image</button>
			<span class="velox-rz-msg"></span>
			<p class="description">Replaces the file in place and rebuilds its thumbnails — the filename and every link to it stay the same. Cannot be undone.</p>
		</div>
		<?php
		$fields['velox_resize'] = array(
			'label' => 'Resize (Velox)',
			'input' => 'html',
			'html'  => ob_get_clean(),
		);
		return $fields;
	}

	/** Small script + styles for the controls above, only on media screens. */
	public function modal_assets( $hook ) {
		if ( ! in_array( $hook, array( 'upload.php', 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		wp_enqueue_script( 'velox-media-modal', VELOX_ASSETS . 'js/velox-media-modal.js', array(), VELOX_VERSION, true );
		wp_localize_script( 'velox-media-modal', 'VELOX_RZ', array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'velox_nonce' ),
		) );
		wp_add_inline_style( 'wp-admin', '
			.velox-rz-row{display:flex;align-items:center;gap:6px;margin-bottom:8px}
			.velox-rz-row input[type=number]{width:88px}
			.velox-rz-lock{width:32px;height:30px;border:1px solid #c3c4c7;border-radius:4px;background:#fff;cursor:pointer;line-height:1}
			.velox-rz-lock.is-on{background:#e8f6fd;border-color:#8ed4f5}
			.velox-rz-presets button{flex:1;height:26px;border:1px solid #c3c4c7;border-radius:4px;background:#fff;cursor:pointer;font-size:11px}
			.velox-rz-presets button:hover{background:#f0f0f1}
			.velox-rz-msg{margin-left:8px;font-size:12px}
			.velox-rz-msg.ok{color:#1d8a4e}.velox-rz-msg.err{color:#c8362f}
		' );
	}

	/* ----------------------------------------------------------------
	 * Alt / Title / Caption
	 * ------------------------------------------------------------- */
	public function update_meta_fields( $attachment_id, $fields ) {
		$attachment_id = (int) $attachment_id;
		if ( 'attachment' !== get_post_type( $attachment_id ) ) {
			return new WP_Error( 'not_attachment', __( 'Not a media item.', 'velox' ) );
		}

		if ( isset( $fields['alt'] ) ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $fields['alt'] ) );
		}

		$post_update = array( 'ID' => $attachment_id );
		$dirty       = false;
		if ( isset( $fields['title'] ) ) {
			$post_update['post_title'] = sanitize_text_field( $fields['title'] );
			$dirty = true;
		}
		if ( isset( $fields['caption'] ) ) {
			$post_update['post_excerpt'] = sanitize_text_field( $fields['caption'] );
			$dirty = true;
		}
		if ( isset( $fields['description'] ) ) {
			$post_update['post_content'] = wp_kses_post( $fields['description'] );
			$dirty = true;
		}
		if ( $dirty ) {
			wp_update_post( $post_update );
		}

		return true;
	}

	/* ----------------------------------------------------------------
	 * Rename file (and fix every reference)
	 * ------------------------------------------------------------- */
	public function rename_file( $attachment_id, $new_basename ) {
		$attachment_id = (int) $attachment_id;
		$old_path      = get_attached_file( $attachment_id );
		if ( ! $old_path || ! file_exists( $old_path ) ) {
			return new WP_Error( 'no_file', __( 'Source file not found.', 'velox' ) );
		}

		$dir       = trailingslashit( dirname( $old_path ) );
		$old_name  = wp_basename( $old_path );
		$ext       = pathinfo( $old_path, PATHINFO_EXTENSION );

		// Sanitise the requested name into a clean kebab-case base (no extension).
		$new_basename = preg_replace( '/\.[a-z0-9]{2,5}$/i', '', $new_basename ); // strip any ext the user typed
		$new_basename = sanitize_title( $new_basename );
		if ( '' === $new_basename ) {
			return new WP_Error( 'bad_name', __( 'Please provide a valid file name.', 'velox' ) );
		}

		$new_name = $new_basename . '.' . strtolower( $ext );

		// Ensure uniqueness within the folder.
		$new_name = wp_unique_filename( $dir, $new_name );
		$new_base = preg_replace( '/\.' . preg_quote( $ext, '/' ) . '$/i', '', $new_name );
		if ( $new_name === $old_name ) {
			return new WP_Error( 'same_name', __( 'New name matches the current name.', 'velox' ) );
		}

		$meta       = wp_get_attachment_metadata( $attachment_id );
		$uploads    = wp_upload_dir();
		$rename_map = array(); // absolute old path => absolute new path
		$url_map    = array(); // old URL => new URL  (for content rewrite)

		// Main file.
		$rename_map[ $old_path ] = $dir . $new_name;

		$old_main_base = preg_replace( '/\.' . preg_quote( $ext, '/' ) . '$/i', '', $old_name );

		// Thumbnail sizes.
		if ( ! empty( $meta['sizes'] ) ) {
			foreach ( $meta['sizes'] as $key => $size ) {
				if ( empty( $size['file'] ) ) {
					continue;
				}
				$old_size_file = $size['file'];
				// e.g. old-name-150x150.jpg -> new-name-150x150.jpg
				$suffix        = substr( $old_size_file, strlen( $old_main_base ) ); // -150x150.jpg
				$new_size_file = $new_base . $suffix;
				$rename_map[ $dir . $old_size_file ] = $dir . $new_size_file;
				$meta['sizes'][ $key ]['file']       = $new_size_file;
			}
		}

		// Add .webp twins for everything we are about to move.
		foreach ( array_keys( $rename_map ) as $abs_old ) {
			$webp_old = preg_replace( '/\.(jpe?g|png)$/i', '.webp', $abs_old );
			if ( $webp_old !== $abs_old && file_exists( $webp_old ) ) {
				$webp_new = preg_replace( '/\.(jpe?g|png)$/i', '.webp', $rename_map[ $abs_old ] );
				$rename_map[ $webp_old ] = $webp_new;
			}
		}

		// Perform the physical renames. Roll back on any failure.
		$done = array();
		foreach ( $rename_map as $from => $to ) {
			if ( ! file_exists( $from ) ) {
				continue;
			}
			if ( ! @rename( $from, $to ) ) {
				// rollback
				foreach ( array_reverse( $done, true ) as $f => $t ) {
					@rename( $t, $f );
				}
				return new WP_Error( 'rename_failed', sprintf( __( 'Could not rename %s. Check file permissions.', 'velox' ), wp_basename( $from ) ) );
			}
			$done[ $from ] = $to;
			// Build URL map (skip webp twins — they are not referenced in content as <img src>).
			if ( preg_match( '/\.(jpe?g|png|webp)$/i', $from ) ) {
				$url_map[ $this->path_to_url( $from, $uploads ) ] = $this->path_to_url( $to, $uploads );
			}
		}

		// Update _wp_attached_file (relative path).
		$new_relative = _wp_relative_upload_path( $dir . $new_name );
		update_post_meta( $attachment_id, '_wp_attached_file', $new_relative );

		// Update metadata file pointer + sizes.
		if ( is_array( $meta ) ) {
			$meta['file'] = $new_relative;
			wp_update_attachment_metadata( $attachment_id, $meta );
		}

		// Update the attachment's own guid so the library URL is consistent.
		$old_guid = get_post_field( 'guid', $attachment_id );
		$new_guid = str_replace( $old_main_base . '.' . $ext, $new_name, $old_guid );
		if ( $new_guid !== $old_guid ) {
			global $wpdb;
			$wpdb->update( $wpdb->posts, array( 'guid' => $new_guid ), array( 'ID' => $attachment_id ) );
		}

		// The valuable part: rewrite every reference site-wide.
		$replaced = $this->replace_urls_everywhere( $url_map );

		clean_post_cache( $attachment_id );

		return array(
			'old_name'     => $old_name,
			'new_name'     => $new_name,
			'files_moved'  => count( $done ),
			'refs_updated' => $replaced,
			'new_url'      => $this->path_to_url( $dir . $new_name, $uploads ),
		);
	}

	/**
	 * Resize an attachment's actual file, in place.
	 *
	 * The filename and URL are deliberately left alone so nothing that already
	 * points at this image breaks. Uses crop() rather than resize() because
	 * WordPress refuses to scale an image *up* through resize().
	 *
	 * @param int $attachment_id Attachment.
	 * @param int $width         Target width in pixels.
	 * @param int $height        Target height in pixels.
	 * @return array|WP_Error
	 */
	public function resize_image( $attachment_id, $width, $height ) {
		$attachment_id = (int) $attachment_id;
		$width         = (int) $width;
		$height        = (int) $height;

		if ( $width < 1 || $height < 1 ) {
			return new WP_Error( 'bad_size', 'Give a width and height of at least 1 pixel.' );
		}
		if ( $width > 12000 || $height > 12000 ) {
			return new WP_Error( 'bad_size', 'That is larger than 12000 pixels — pick something smaller.' );
		}
		$post = get_post( $attachment_id );
		if ( ! $post || 'attachment' !== $post->post_type ) {
			return new WP_Error( 'not_found', 'That image no longer exists.' );
		}
		if ( 'image/svg+xml' === $post->post_mime_type ) {
			return new WP_Error( 'vector', 'SVGs are vectors — they scale on their own, no resizing needed.' );
		}
		$file = get_attached_file( $attachment_id );
		if ( ! $file || ! file_exists( $file ) ) {
			return new WP_Error( 'missing', 'The file is missing from the uploads folder.' );
		}

		$editor = wp_get_image_editor( $file );
		if ( is_wp_error( $editor ) ) {
			return $editor;
		}
		$size = $editor->get_size();
		$ow   = isset( $size['width'] ) ? (int) $size['width'] : 0;
		$oh   = isset( $size['height'] ) ? (int) $size['height'] : 0;
		if ( $ow < 1 || $oh < 1 ) {
			return new WP_Error( 'unreadable', 'Could not read the image dimensions.' );
		}
		if ( $ow === $width && $oh === $height ) {
			return array( 'ok' => true, 'width' => $ow, 'height' => $oh, 'unchanged' => true );
		}

		// Full-source crop == a straight scale, and unlike resize() it will enlarge.
		$done = $editor->crop( 0, 0, $ow, $oh, $width, $height, false );
		if ( is_wp_error( $done ) ) {
			return $done;
		}
		$saved = $editor->save( $file );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		// Rebuild the intermediate sizes so thumbnails and srcset match the new file.
		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}
		$meta = wp_generate_attachment_metadata( $attachment_id, $file );
		if ( is_array( $meta ) ) {
			wp_update_attachment_metadata( $attachment_id, $meta );
		}
		clean_post_cache( $attachment_id );

		return array(
			'ok'     => true,
			'width'  => $width,
			'height' => $height,
			'bytes'  => file_exists( $file ) ? (int) filesize( $file ) : 0,
			'url'    => wp_get_attachment_image_url( $attachment_id, 'full' ),
			'thumb'  => wp_get_attachment_image_url( $attachment_id, 'thumbnail' ),
		);
	}

	private function path_to_url( $path, $uploads ) {
		return str_replace( $uploads['basedir'], $uploads['baseurl'], $path );
	}

	/**
	 * Serialization-safe search/replace of a URL map across posts + postmeta.
	 * Returns the number of rows touched.
	 */
	private function replace_urls_everywhere( $url_map ) {
		if ( empty( $url_map ) ) {
			return 0;
		}
		global $wpdb;
		$touched = 0;
		$search  = array_keys( $url_map );
		$replace = array_values( $url_map );

		// 1) Post content / excerpt — plain string replace is safe here.
		$like_clauses = array();
		foreach ( $search as $needle ) {
			$like_clauses[] = $wpdb->prepare( 'post_content LIKE %s OR post_excerpt LIKE %s', '%' . $wpdb->esc_like( $needle ) . '%', '%' . $wpdb->esc_like( $needle ) . '%' );
		}
		$where    = implode( ' OR ', $like_clauses );
		$post_ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE {$where}" );
		foreach ( $post_ids as $pid ) {
			$post     = get_post( $pid );
			$content  = str_replace( $search, $replace, $post->post_content );
			$excerpt  = str_replace( $search, $replace, $post->post_excerpt );
			if ( $content !== $post->post_content || $excerpt !== $post->post_excerpt ) {
				$wpdb->update( $wpdb->posts, array( 'post_content' => $content, 'post_excerpt' => $excerpt ), array( 'ID' => $pid ) );
				clean_post_cache( $pid );
				$touched++;
			}
		}

		// 2) Postmeta — may be serialized (Oxygen / Gutenberg builders store JSON or arrays).
		$meta_like = array();
		foreach ( $search as $needle ) {
			$meta_like[] = $wpdb->prepare( 'meta_value LIKE %s', '%' . $wpdb->esc_like( $needle ) . '%' );
		}
		$meta_where = implode( ' OR ', $meta_like );
		$rows = $wpdb->get_results( "SELECT meta_id, meta_value FROM {$wpdb->postmeta} WHERE {$meta_where}" );
		foreach ( $rows as $row ) {
			$value = $this->deep_replace( $search, $replace, maybe_unserialize( $row->meta_value ) );
			$new   = maybe_serialize( $value );
			if ( $new !== $row->meta_value ) {
				$wpdb->update( $wpdb->postmeta, array( 'meta_value' => $new ), array( 'meta_id' => $row->meta_id ) );
				$touched++;
			}
		}

		// 3) Options — some builders cache global CSS in options (e.g. Oxygen universal css).
		$opt_like = array();
		foreach ( $search as $needle ) {
			$opt_like[] = $wpdb->prepare( 'option_value LIKE %s', '%' . $wpdb->esc_like( $needle ) . '%' );
		}
		$opt_where = implode( ' OR ', $opt_like );
		$opts = $wpdb->get_results( "SELECT option_id, option_value FROM {$wpdb->options} WHERE {$opt_where} AND option_value NOT LIKE '%velox_settings%'" );
		foreach ( $opts as $opt ) {
			$value = $this->deep_replace( $search, $replace, maybe_unserialize( $opt->option_value ) );
			$new   = maybe_serialize( $value );
			if ( $new !== $opt->option_value ) {
				$wpdb->update( $wpdb->options, array( 'option_value' => $new ), array( 'option_id' => $opt->option_id ) );
				$touched++;
			}
		}

		return $touched;
	}

	/**
	 * Recursive str_replace that walks arrays and objects without corrupting them.
	 */
	private function deep_replace( $search, $replace, $data ) {
		if ( is_string( $data ) ) {
			return str_replace( $search, $replace, $data );
		}
		if ( is_array( $data ) ) {
			foreach ( $data as $k => $v ) {
				$data[ $k ] = $this->deep_replace( $search, $replace, $v );
			}
			return $data;
		}
		if ( is_object( $data ) ) {
			foreach ( get_object_vars( $data ) as $k => $v ) {
				$data->$k = $this->deep_replace( $search, $replace, $v );
			}
			return $data;
		}
		return $data;
	}

	/* ----------------------------------------------------------------
	 * Listing for the grid editor
	 * ------------------------------------------------------------- */
	public function list_media( $page = 1, $per_page = 40, $search = '', $type = 'all' ) {
		$mime = 'image';
		$map  = array(
			'jpg'  => array( 'image/jpeg' ),
			'png'  => array( 'image/png' ),
			'webp' => array( 'image/webp' ),
			'gif'  => array( 'image/gif' ),
			'svg'  => array( 'image/svg+xml' ),
		);
		if ( isset( $map[ $type ] ) ) {
			$mime = $map[ $type ];
		}

		$args = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'post_mime_type' => $mime,
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);
		if ( $search ) {
			$args['s'] = $search;
		}
		$q     = new WP_Query( $args );
		$items = array();
		foreach ( $q->posts as $post ) {
			$file = get_attached_file( $post->ID );
			$meta = wp_get_attachment_metadata( $post->ID );
			$bytes = ( $file && file_exists( $file ) ) ? filesize( $file ) : 0;
			$ext   = $file ? strtolower( pathinfo( $file, PATHINFO_EXTENSION ) ) : '';
			$wstats = get_post_meta( $post->ID, Velox_Image_Optimizer::META_KEY, true );
			$items[] = array(
				'id'        => $post->ID,
				'thumb'     => wp_get_attachment_image_url( $post->ID, 'thumbnail' ),
				'full'      => wp_get_attachment_image_url( $post->ID, 'full' ),
				'filename'  => $file ? wp_basename( $file ) : '',
				'title'     => $post->post_title,
				'alt'       => get_post_meta( $post->ID, '_wp_attachment_image_alt', true ),
				'caption'   => $post->post_excerpt,
				'webp'      => ! empty( $wstats ),
				'webp_bytes'=> ! empty( $wstats['webp_bytes'] ) ? (int) $wstats['webp_bytes'] : 0,
				'orig_bytes'=> ! empty( $wstats['original_bytes'] ) ? (int) $wstats['original_bytes'] : 0,
				'width'     => isset( $meta['width'] ) ? (int) $meta['width'] : 0,
				'height'    => isset( $meta['height'] ) ? (int) $meta['height'] : 0,
				'bytes'     => (int) $bytes,
				'ext'       => $ext,
				'mime'      => $post->post_mime_type,
			);
		}
		return array(
			'items'       => $items,
			'total'       => (int) $q->found_posts,
			'total_pages' => (int) $q->max_num_pages,
			'page'        => $page,
		);
	}

	/* ----------------------------------------------------------------
	 * Pipe-delimited bulk:  Dateiname | Alt-Text | Titel
	 * ------------------------------------------------------------- */
	public function export_pipe() {
		$all   = $this->list_media( 1, 9999 );
		$lines = array( 'Dateiname | Alt-Text | Titel' );
		foreach ( $all['items'] as $it ) {
			$lines[] = $it['filename'] . ' | ' . $it['alt'] . ' | ' . $it['title'];
		}
		return implode( "\n", $lines );
	}

	/**
	 * Build a downloadable zip of the selected images' original files, plus a
	 * pipe-format metadata file (Dateiname | Alt-Text | Titel) so alt text and
	 * titles survive the round-trip and can be re-applied via Bulk import.
	 *
	 * @param int[] $ids Attachment ids.
	 * @return array{ok:bool,url?:string,filename?:string,count?:int,message?:string}
	 */
	public function build_zip( $ids ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return array( 'ok' => false, 'message' => 'Zip support (ZipArchive) is not available on this server.' );
		}
		$ids = array_values( array_unique( array_filter( array_map( 'intval', (array) $ids ) ) ) );
		if ( empty( $ids ) ) {
			return array( 'ok' => false, 'message' => 'No images selected.' );
		}

		$up  = wp_upload_dir();
		$dir = trailingslashit( $up['basedir'] ) . 'velox-tmp';
		if ( ! wp_mkdir_p( $dir ) ) {
			return array( 'ok' => false, 'message' => 'Could not create a temporary folder in uploads.' );
		}
		if ( ! file_exists( $dir . '/index.html' ) ) {
			@file_put_contents( $dir . '/index.html', '' ); // no directory listing
		}
		// Tidy zips older than an hour so this folder never piles up.
		foreach ( (array) glob( $dir . '/velox-images-*.zip' ) as $old ) {
			if ( is_file( $old ) && filemtime( $old ) < time() - HOUR_IN_SECONDS ) {
				@unlink( $old );
			}
		}

		$name = 'velox-images-' . gmdate( 'Ymd-His' ) . '-' . wp_generate_password( 6, false ) . '.zip';
		$path = $dir . '/' . $name;
		$zip  = new ZipArchive();
		if ( true !== $zip->open( $path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			return array( 'ok' => false, 'message' => 'Could not create the zip file.' );
		}

		$rows  = array( 'Dateiname | Alt-Text | Titel' );
		$used  = array();
		$count = 0;
		foreach ( $ids as $id ) {
			if ( 'attachment' !== get_post_type( $id ) ) {
				continue;
			}
			$file = get_attached_file( $id );
			if ( ! $file || ! file_exists( $file ) ) {
				continue;
			}
			$base = wp_basename( $file );
			if ( isset( $used[ $base ] ) ) {
				$base = $id . '-' . $base; // avoid collisions between same-named files
			}
			if ( $zip->addFile( $file, $base ) ) {
				$used[ $base ] = 1;
				$count++;
				$alt   = (string) get_post_meta( $id, '_wp_attachment_image_alt', true );
				$title = (string) get_the_title( $id );
				$clean = function ( $v ) { return trim( str_replace( array( "\r", "\n", '|' ), ' ', $v ) ); };
				$rows[] = $base . ' | ' . $clean( $alt ) . ' | ' . $clean( $title );
			}
		}

		if ( ! $count ) {
			$zip->close();
			@unlink( $path );
			return array( 'ok' => false, 'message' => 'None of the selected images could be read from disk.' );
		}
		$zip->addFromString( 'alt-text-and-titles.txt', implode( "\n", $rows ) . "\n" );
		$zip->close();

		return array(
			'ok'       => true,
			'url'      => trailingslashit( $up['baseurl'] ) . 'velox-tmp/' . $name,
			'filename' => $name,
			'count'    => $count,
		);
	}

	public function import_pipe( $text ) {
		$lines   = preg_split( '/\r\n|\r|\n/', trim( $text ) );
		$updated = 0;
		$skipped = 0;
		$missing = array();

		// Build filename -> attachment id lookup once.
		$lookup = $this->filename_lookup();

		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}
			// Skip a header row.
			if ( stripos( $line, 'Dateiname' ) === 0 && stripos( $line, 'Alt' ) !== false ) {
				continue;
			}
			$parts = array_map( 'trim', explode( '|', $line ) );
			if ( count( $parts ) < 2 ) {
				$skipped++;
				continue;
			}
			$filename = $parts[0];
			$alt      = isset( $parts[1] ) ? $parts[1] : '';
			$title    = isset( $parts[2] ) ? $parts[2] : '';

			$key = strtolower( $filename );
			if ( ! isset( $lookup[ $key ] ) ) {
				$missing[] = $filename;
				continue;
			}
			$id     = $lookup[ $key ];
			$fields = array( 'alt' => $alt );
			if ( '' !== $title ) {
				$fields['title'] = $title;
			}
			$this->update_meta_fields( $id, $fields );
			$updated++;
		}

		return array(
			'updated' => $updated,
			'skipped' => $skipped,
			'missing' => $missing,
		);
	}

	private function filename_lookup() {
		global $wpdb;
		$rows   = $wpdb->get_results( "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file'" );
		$lookup = array();
		foreach ( $rows as $row ) {
			$lookup[ strtolower( wp_basename( $row->meta_value ) ) ] = (int) $row->post_id;
		}
		return $lookup;
	}
}
