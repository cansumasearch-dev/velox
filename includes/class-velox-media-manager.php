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
	public function list_media( $page = 1, $per_page = 40, $search = '' ) {
		$args = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'post_mime_type' => 'image',
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
			$items[] = array(
				'id'        => $post->ID,
				'thumb'     => wp_get_attachment_image_url( $post->ID, 'thumbnail' ),
				'full'      => wp_get_attachment_image_url( $post->ID, 'full' ),
				'filename'  => $file ? wp_basename( $file ) : '',
				'title'     => $post->post_title,
				'alt'       => get_post_meta( $post->ID, '_wp_attachment_image_alt', true ),
				'caption'   => $post->post_excerpt,
				'webp'      => (bool) get_post_meta( $post->ID, Velox_Image_Optimizer::META_KEY, true ),
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
