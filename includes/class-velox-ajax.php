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

			case 'estimate_webp':
				$id  = (int) ( $_POST['id'] ?? 0 );
				$q   = isset( $_POST['quality'] ) ? (int) $_POST['quality'] : null;
				$opt = new Velox_Image_Optimizer();
				$this->respond( $opt->estimate( $id, $q ) );
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
				$type = sanitize_key( $_POST['type'] ?? 'all' );
				wp_send_json_success( $mm->list_media( $page, 40, $s, $type ) );
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

			/* -------- Cache -------- */
			case 'clear_cache':
				$which = sanitize_key( $_POST['which'] ?? 'all' );
				$res   = Velox_Admin::clear_cache( $which );
				if ( empty( $res['ok'] ) ) {
					wp_send_json_error( $res );
				}
				wp_send_json_success( $res );
				break;

			case 'clear_used_css':
				wp_send_json_success( Velox_CSS::clear_cache() );
				break;

			case 'rucss_scan_one':
				$path = isset( $_POST['path'] ) ? wp_unslash( $_POST['path'] ) : '/';
				$css  = new Velox_CSS();
				$this->respond( $css->build_for_path( $path ) );
				break;

			case 'rucss_reset_learn':
				wp_send_json_success( Velox_CSS::reset_learning() );
				break;

			case 'apply_preset':
				$preset = isset( $_POST['preset'] ) && 'aggressive' === $_POST['preset'] ? 'aggressive' : 'safe';
				wp_send_json_success( Velox_Settings::apply_preset( $preset ) );
				break;

			case 'builder_detect':
				$id = Velox_Builders::detect();
				wp_send_json_success( array(
					'builder' => $id,
					'label'   => Velox_Builders::label( $id ),
					'is_default' => 'wordpress' === $id,
				) );
				break;

			case 'builder_apply':
				$id = isset( $_POST['builder'] ) ? sanitize_key( wp_unslash( $_POST['builder'] ) ) : '';
				wp_send_json_success( Velox_Builders::apply( $id ) );
				break;

			case 'builder_request':
				wp_send_json_success( Velox_Builders::request_builder( $_POST['name'] ?? '' ) );
				break;

			case 'wizard_dismiss':
				Velox_Settings::set( 'wizard_done', true );
				wp_send_json_success( array( 'ok' => true ) );
				break;

			case 'localize_fonts':
				$fonts = new Velox_Fonts();
				$this->respond( $fonts->localize() );
				break;

			case 'clear_local_fonts':
				$fonts = new Velox_Fonts();
				wp_send_json_success( $fonts->clear() );
				break;

			case 'export_settings':
				wp_send_json_success( array( 'json' => wp_json_encode( Velox_Settings::all() ) ) );
				break;

			case 'import_settings':
				$raw = isset( $_POST['json'] ) ? wp_unslash( $_POST['json'] ) : '';
				$in  = json_decode( $raw, true );
				if ( ! is_array( $in ) ) {
					wp_send_json_error( array( 'message' => __( 'That doesn\'t look like valid Velox settings JSON.', 'velox' ) ), 400 );
				}
				$defaults = Velox_Settings::defaults();
				$clean    = Velox_Settings::all();
				foreach ( $defaults as $key => $default ) {
					if ( ! array_key_exists( $key, $in ) ) {
						continue;
					}
					if ( is_bool( $default ) ) {
						$clean[ $key ] = (bool) $in[ $key ];
					} elseif ( is_int( $default ) ) {
						$clean[ $key ] = (int) $in[ $key ];
					} else {
						$clean[ $key ] = sanitize_textarea_field( (string) $in[ $key ] );
					}
				}
				Velox_Settings::save( $clean );
				wp_send_json_success( array( 'message' => __( 'Settings imported.', 'velox' ) ) );
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
		// The admin JS posts each setting as a flat top-level field (module_images=1,
		// perf_defer_scripts=0, …) — not nested under settings[]. We update only the
		// keys that were actually posted on this screen and leave the rest untouched,
		// so saving one tab never wipes another tab's values.
		$raw      = wp_unslash( $_POST );
		$defaults = Velox_Settings::defaults();
		$clean    = Velox_Settings::all(); // start from the current saved values

		foreach ( $defaults as $key => $default ) {
			if ( ! array_key_exists( $key, $raw ) ) {
				continue; // not present on this screen → keep existing value
			}
			$val = $raw[ $key ];
			if ( is_bool( $default ) ) {
				$clean[ $key ] = ( '1' === (string) $val || 'true' === $val || 1 === $val || true === $val || 'on' === $val );
			} elseif ( is_int( $default ) ) {
				$clean[ $key ] = (int) $val;
			} else {
				$clean[ $key ] = sanitize_textarea_field( $val );
			}
		}

		// Clamp numeric fields to sane ranges.
		$clean['webp_quality']        = max( 1, min( 100, (int) $clean['webp_quality'] ) );
		$clean['perf_revisions_keep'] = max( 0, (int) $clean['perf_revisions_keep'] );
		if ( isset( $clean['perf_lazy_skip_count'] ) ) {
			$clean['perf_lazy_skip_count'] = max( 0, min( 20, (int) $clean['perf_lazy_skip_count'] ) );
		}

		Velox_Settings::save( $clean );
		wp_send_json_success( array( 'message' => __( 'Settings saved.', 'velox' ) ) );
	}
}
