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

			case 'maint_reset':
				$defaults = Velox_Settings::defaults();
				foreach ( array( 'util_maintenance_title', 'util_maintenance_message', 'util_maintenance_logo', 'util_maintenance_bg', 'util_maintenance_text', 'util_maintenance_accent', 'util_maintenance_bgimage', 'util_maintenance_btn_text', 'util_maintenance_btn_url', 'util_maintenance_brand', 'util_maintenance_anim' ) as $mk ) {
					if ( isset( $defaults[ $mk ] ) ) {
						Velox_Settings::set( $mk, $defaults[ $mk ] );
					}
				}
				wp_send_json_success( array( 'ok' => true ) );
				break;

			case 'util_toggle':
				$key = isset( $_POST['key'] ) ? sanitize_key( wp_unslash( $_POST['key'] ) ) : '';
				$on  = ! empty( $_POST['on'] ) && 'false' !== $_POST['on'];
				// Allow any enable key declared in the utilities catalog.
				$allowed = array();
				foreach ( Velox_Utilities::catalog() as $tool ) {
					if ( ! empty( $tool['enable'] ) ) {
						$allowed[] = $tool['enable'];
					}
				}
				if ( ! in_array( $key, $allowed, true ) ) {
					wp_send_json_error( array( 'message' => 'Unknown tool.' ) );
				}
				Velox_Settings::set( $key, $on );
				wp_send_json_success( array( 'ok' => true, 'key' => $key, 'on' => $on ) );
				break;

			case 'media_scan':
				wp_send_json_success( array( 'items' => Velox_Utilities::find_unused() ) );
				break;

			case 'media_delete':
				$ids = isset( $_POST['ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['ids'] ) ) : array();
				wp_send_json_success( Velox_Utilities::delete_media( $ids ) );
				break;

			case 'installer_install':
				$source   = isset( $_POST['source'] ) ? trim( wp_unslash( $_POST['source'] ) )
					: ( isset( $_POST['slug'] ) ? sanitize_key( wp_unslash( $_POST['slug'] ) ) : '' );
				$activate = ! empty( $_POST['activate'] ) && 'false' !== $_POST['activate'];
				wp_send_json_success( Velox_Utilities::install_source( $source, $activate ) );
				break;

			case 'installer_upload':
				$activate = ! empty( $_POST['activate'] ) && 'false' !== $_POST['activate'];
				if ( empty( $_FILES['plugin_zip'] ) ) {
					wp_send_json_error( array( 'message' => 'No file uploaded.' ) );
				}
				wp_send_json_success( Velox_Utilities::install_zip( $_FILES['plugin_zip'], $activate ) );
				break;

			case 'blueprint_save':
				$name  = isset( $_POST['name'] ) ? wp_unslash( $_POST['name'] ) : '';
				$slugs = isset( $_POST['slugs'] ) ? (array) wp_unslash( $_POST['slugs'] ) : array();
				wp_send_json_success( Velox_Utilities::save_blueprint( $name, $slugs ) );
				break;

			case 'blueprint_delete':
				$name = isset( $_POST['name'] ) ? wp_unslash( $_POST['name'] ) : '';
				wp_send_json_success( Velox_Utilities::delete_blueprint( $name ) );
				break;

			case 'redirect_add':
				$src  = isset( $_POST['source'] ) ? wp_unslash( $_POST['source'] ) : '';
				$tgt  = isset( $_POST['target'] ) ? wp_unslash( $_POST['target'] ) : '';
				$type = isset( $_POST['type'] ) ? (int) $_POST['type'] : 301;
				wp_send_json_success( Velox_Redirects::add( $src, $tgt, $type ) );
				break;

			case 'snippet_save':
				$sdata = array(
					'id'          => isset( $_POST['id'] ) ? (int) $_POST['id'] : 0,
					'name'        => isset( $_POST['name'] ) ? wp_unslash( $_POST['name'] ) : '',
					'description' => isset( $_POST['description'] ) ? wp_unslash( $_POST['description'] ) : '',
					'type'        => isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : 'php',
					'code'        => isset( $_POST['code'] ) ? wp_unslash( $_POST['code'] ) : '',
					'scope'       => isset( $_POST['scope'] ) ? sanitize_key( wp_unslash( $_POST['scope'] ) ) : 'everywhere',
					'priority'    => isset( $_POST['priority'] ) ? (int) $_POST['priority'] : 10,
				);
				if ( isset( $_POST['active'] ) ) {
					$sdata['active'] = ( '1' === (string) $_POST['active'] || 'true' === (string) $_POST['active'] ) ? 1 : 0;
				}
				wp_send_json_success( Velox_Snippets::save( $sdata ) );
				break;

			case 'snippet_activate':
				wp_send_json_success( Velox_Snippets::set_active( isset( $_POST['id'] ) ? (int) $_POST['id'] : 0, true ) );
				break;

			case 'snippet_deactivate':
				wp_send_json_success( Velox_Snippets::set_active( isset( $_POST['id'] ) ? (int) $_POST['id'] : 0, false ) );
				break;

			case 'snippet_duplicate':
				wp_send_json_success( Velox_Snippets::duplicate( isset( $_POST['id'] ) ? (int) $_POST['id'] : 0 ) );
				break;

			case 'snippet_trash':
				wp_send_json_success( Velox_Snippets::trash( isset( $_POST['id'] ) ? (int) $_POST['id'] : 0 ) );
				break;

			case 'snippet_restore':
				wp_send_json_success( Velox_Snippets::restore( isset( $_POST['id'] ) ? (int) $_POST['id'] : 0 ) );
				break;

			case 'snippet_delete':
				wp_send_json_success( Velox_Snippets::delete_permanent( isset( $_POST['id'] ) ? (int) $_POST['id'] : 0 ) );
				break;

			case 'redirect_update':
				$rid  = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
				$src  = isset( $_POST['source'] ) ? wp_unslash( $_POST['source'] ) : '';
				$tgt  = isset( $_POST['target'] ) ? wp_unslash( $_POST['target'] ) : '';
				$type = isset( $_POST['type'] ) ? (int) $_POST['type'] : 301;
				wp_send_json_success( Velox_Redirects::update( $rid, $src, $tgt, $type ) );
				break;

			case 'redirect_delete':
				wp_send_json_success( Velox_Redirects::delete( isset( $_POST['id'] ) ? (int) $_POST['id'] : 0 ) );
				break;

			case 'log_clear':
				wp_send_json_success( Velox_Redirects::clear_404s() );
				break;

			case 'log_forget':
				wp_send_json_success( Velox_Redirects::forget_404( isset( $_POST['id'] ) ? (int) $_POST['id'] : 0 ) );
				break;

			case 'activity_clear':
				wp_send_json_success( Velox_Activity::clear() );
				break;

			case 'scripts_scan':
				wp_send_json_success( Velox_Scripts::scan() );
				break;

			case 'scripts_save':
				$rules = isset( $_POST['rules'] ) ? json_decode( wp_unslash( $_POST['rules'] ), true ) : array();
				wp_send_json_success( Velox_Scripts::save_rules( is_array( $rules ) ? $rules : array() ) );
				break;

			case 'scripts_clear':
				wp_send_json_success( Velox_Scripts::clear_seen() );
				break;

			case 'form_save':
				$form = isset( $_POST['form'] ) ? json_decode( wp_unslash( $_POST['form'] ), true ) : null;
				if ( ! is_array( $form ) ) {
					wp_send_json_error( array( 'message' => 'Invalid form data.' ) );
				}
				wp_send_json_success( Velox_Forms::save_form( $form ) );
				break;

			case 'form_delete':
				wp_send_json_success( Velox_Forms::delete_form( isset( $_POST['id'] ) ? (int) $_POST['id'] : 0 ) );
				break;

			case 'submission_delete':
				wp_send_json_success( Velox_Forms::delete_submission( isset( $_POST['id'] ) ? (int) $_POST['id'] : 0 ) );
				break;

			case 'mail_test':
				wp_send_json_success( Velox_Mail::send_test( isset( $_POST['to'] ) ? sanitize_email( wp_unslash( $_POST['to'] ) ) : '' ) );
				break;

			case 'mail_log_clear':
				wp_send_json_success( Velox_Mail::clear_log() );
				break;

			case 'cache_setup':
				if ( Velox_Settings::get( 'cache_enable', false ) ) {
					$st = Velox_Cache::install_dropin();
					wp_send_json_success( $st );
				}
				Velox_Cache::remove_dropin();
				wp_send_json_success( array( 'dropin' => false, 'wp_cache' => false, 'manual' => '' ) );
				break;

			case 'cache_purge':
				Velox_Cache::purge_all();
				wp_send_json_success( array( 'ok' => true ) );
				break;

			case 'cache_preload':
				wp_send_json_success( Velox_Cache::preload( 30 ) );
				break;

			case 'seo_robots_save':
				$content = isset( $_POST['content'] ) ? wp_unslash( $_POST['content'] ) : '';
				wp_send_json_success( Velox_Seo::save_robots( $content ) );
				break;

			case 'seo_robots_reset':
				wp_send_json_success( Velox_Seo::save_robots( Velox_Seo::default_robots() ) + array( 'content' => Velox_Seo::default_robots() ) );
				break;

			case 'seo_robots_physical':
				$content = isset( $_POST['content'] ) ? wp_unslash( $_POST['content'] ) : '';
				Velox_Seo::save_robots( $content );
				$ok = Velox_Seo::write_physical( $content );
				wp_send_json_success( array( 'ok' => $ok, 'physical' => Velox_Seo::physical_robots_exists() ) );
				break;

			case 'seo_robots_virtual':
				$ok = Velox_Seo::delete_physical();
				wp_send_json_success( array( 'ok' => $ok, 'physical' => Velox_Seo::physical_robots_exists() ) );
				break;

			case 'seo_sitemap_generate':
				$ok = Velox_Seo::generate_sitemap();
				wp_send_json_success( Velox_Seo::sitemap_stats() + array( 'generated' => $ok ) );
				break;

			case 'seo_apply_recommended':
				wp_send_json_success( Velox_Seo::apply_recommended() + array( 'content' => Velox_Seo::default_robots() ) );
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
		if ( isset( $clean['cache_ttl'] ) ) {
			$clean['cache_ttl'] = max( 0, (int) $clean['cache_ttl'] );
		}

		Velox_Settings::save( $clean );

		// Keep the page-cache drop-in config in step whenever a cache_* key was touched.
		if ( class_exists( 'Velox_Cache' ) ) {
			foreach ( $defaults as $key => $d ) {
				if ( 0 === strpos( $key, 'cache_' ) && array_key_exists( $key, $raw ) ) {
					Velox_Cache::write_config();
					break;
				}
			}
		}
		wp_send_json_success( array( 'message' => __( 'Settings saved.', 'velox' ) ) );
	}
}
