<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Every front-of-house action funnels through here. One nonce, one capability
 * gate, then routed by 'do' parameter. Keeps the surface small and auditable.
 */
class Velox_Ajax {

	public function __construct() {
		add_action( 'wp_ajax_velox', array( $this, 'dispatch' ) );
	}

	private function guard() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'velox' ) ), 403 );
		}
		check_ajax_referer( 'velox_nonce', 'nonce' );
	}

	public function dispatch() {
		$this->guard();
		$do = isset( $_POST['do'] ) ? sanitize_key( $_POST['do'] ) : '';

		switch ( $do ) {
			/* -------- Images -------- */
			case 'convert_one':
				$id    = (int) ( $_POST['id'] ?? 0 );
				$q     = isset( $_POST['quality'] ) ? (int) $_POST['quality'] : null;
				$opt   = new Velox_Image_Optimizer();
				$res   = $opt->convert_attachment( $id, $q );
				$this->respond( $res );
				break;

			case 'image_stats':
				wp_send_json_success( Velox_Image_Optimizer::library_stats() );
				break;

			case 'pending_ids':
				$ids  = Velox_Image_Optimizer::get_convertible_ids();
				$todo = array();
				foreach ( $ids as $id ) {
					$m = get_post_meta( $id, Velox_Image_Optimizer::META_KEY, true );
					if ( empty( $m ) ) {
						$todo[] = $id;
					}
				}
				wp_send_json_success( array( 'ids' => $todo ) );
				break;

			case 'compare_data':
				$id   = (int) ( $_POST['id'] ?? 0 );
				$stats= get_post_meta( $id, Velox_Image_Optimizer::META_KEY, true );
				wp_send_json_success( array(
					'original' => wp_get_attachment_image_url( $id, 'full' ),
					'webp'     => $this->webp_preview_url( $id ),
					'stats'    => $stats ?: null,
				) );
				break;

			/* -------- Media -------- */
			case 'list_media':
				$mm   = new Velox_Media_Manager();
				$page = max( 1, (int) ( $_POST['page'] ?? 1 ) );
				$s    = sanitize_text_field( $_POST['search'] ?? '' );
				wp_send_json_success( $mm->list_media( $page, 40, $s ) );
				break;

			case 'save_meta':
				$mm     = new Velox_Media_Manager();
				$id     = (int) ( $_POST['id'] ?? 0 );
				$fields = array(
					'alt'     => sanitize_text_field( $_POST['alt'] ?? '' ),
					'title'   => sanitize_text_field( $_POST['title'] ?? '' ),
					'caption' => sanitize_text_field( $_POST['caption'] ?? '' ),
				);
				$this->respond( $mm->update_meta_fields( $id, $fields ) );
				break;

			case 'rename':
				$mm  = new Velox_Media_Manager();
				$id  = (int) ( $_POST['id'] ?? 0 );
				$new = sanitize_text_field( $_POST['name'] ?? '' );
				$this->respond( $mm->rename_file( $id, $new ) );
				break;

			case 'export_pipe':
				$mm = new Velox_Media_Manager();
				wp_send_json_success( array( 'text' => $mm->export_pipe() ) );
				break;

			case 'import_pipe':
				$mm   = new Velox_Media_Manager();
				$text = wp_unslash( $_POST['text'] ?? '' );
				wp_send_json_success( $mm->import_pipe( $text ) );
				break;

			/* -------- Database -------- */
			case 'db_counts':
				$db = new Velox_Database();
				wp_send_json_success( $db->counts() );
				break;

			case 'db_clean':
				$db   = new Velox_Database();
				$item = sanitize_key( $_POST['item'] ?? '' );
				$n    = $db->clean( $item );
				wp_send_json_success( array( 'cleaned' => $n, 'item' => $item ) );
				break;

			/* -------- Settings -------- */
			case 'save_settings':
				$this->save_settings();
				break;

			default:
				wp_send_json_error( array( 'message' => __( 'Unknown action.', 'velox' ) ), 400 );
		}
	}

	private function respond( $res ) {
		if ( is_wp_error( $res ) ) {
			wp_send_json_error( array( 'message' => $res->get_error_message() ) );
		}
		wp_send_json_success( $res );
	}

	private function webp_preview_url( $id ) {
		$url  = wp_get_attachment_image_url( $id, 'full' );
		if ( ! $url ) {
			return '';
		}
		$webp = preg_replace( '/\.(jpe?g|png)$/i', '.webp', $url );
		$up   = wp_upload_dir();
		$path = str_replace( $up['baseurl'], $up['basedir'], $webp );
		return file_exists( $path ) ? $webp : '';
	}

	private function save_settings() {
		$raw      = isset( $_POST['settings'] ) ? (array) $_POST['settings'] : array();
		$defaults = Velox_Settings::defaults();
		$clean    = array();

		foreach ( $defaults as $key => $default ) {
			if ( is_bool( $default ) ) {
				$clean[ $key ] = ! empty( $raw[ $key ] ) && 'false' !== $raw[ $key ] && '0' !== $raw[ $key ];
			} elseif ( is_int( $default ) ) {
				$clean[ $key ] = isset( $raw[ $key ] ) ? (int) $raw[ $key ] : $default;
			} else {
				$clean[ $key ] = isset( $raw[ $key ] ) ? sanitize_textarea_field( wp_unslash( $raw[ $key ] ) ) : $default;
			}
		}

		// Clamp the quality slider.
		$clean['webp_quality']    = max( 1, min( 100, (int) $clean['webp_quality'] ) );
		$clean['perf_revisions_keep'] = max( 0, (int) $clean['perf_revisions_keep'] );

		Velox_Settings::save( $clean );
		wp_send_json_success( array( 'message' => __( 'Settings saved.', 'velox' ) ) );
	}
}
