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

			case 'dash_widgets':
				$hidden = isset( $_POST['hidden'] ) ? (array) wp_unslash( $_POST['hidden'] ) : array();
				$hidden = array_values( array_unique( array_filter( array_map( 'sanitize_key', $hidden ) ) ) );
				Velox_Settings::set( 'dash_hidden', $hidden );
				if ( isset( $_POST['order'] ) ) {
					$order = (array) wp_unslash( $_POST['order'] );
					$order = array_values( array_unique( array_filter( array_map( 'sanitize_key', $order ) ) ) );
					Velox_Settings::set( 'dash_order', $order );
				}
				if ( isset( $_POST['sizes'] ) && is_array( $_POST['sizes'] ) ) {
					$sizes = array();
					foreach ( (array) wp_unslash( $_POST['sizes'] ) as $sid => $sz ) {
						$sid = sanitize_key( $sid );
						if ( '' === $sid || ! is_array( $sz ) ) {
							continue;
						}
						$c = isset( $sz['c'] ) ? (int) $sz['c'] : 4;
						$r = isset( $sz['r'] ) ? (int) $sz['r'] : 1;
						$sizes[ $sid ] = array(
							'c' => max( 3, min( 12, $c ) ),
							'r' => max( 1, min( 3, $r ) ),
						);
					}
					Velox_Settings::set( 'dash_sizes', $sizes );
				}
				wp_send_json_success( array( 'hidden' => $hidden ) );
				break;

			case 'dash_traffic':
				$days = isset( $_POST['days'] ) ? (int) $_POST['days'] : 7;
				$days = in_array( $days, array( 7, 14, 30 ), true ) ? $days : 7;
				$cur  = Velox_Stats::traffic_summary( $days );
				$dbl  = Velox_Stats::traffic_summary( $days * 2 );
				$cur['prev_visitors'] = max( 0, (int) $dbl['visitors'] - (int) $cur['visitors'] );
				$cur['days'] = $days;
				wp_send_json_success( $cur );
				break;

			case 'ps_refresh':
				if ( ! class_exists( 'Velox_Pagespeed' ) ) {
					wp_send_json_error( array( 'message' => 'PageSpeed module unavailable.' ) );
				}
				$res = Velox_Pagespeed::fetch_and_store();
				Velox_Pagespeed::sync_schedule();
				if ( empty( $res['ok'] ) ) {
					wp_send_json_error( array( 'message' => ! empty( $res['error'] ) ? $res['error'] : 'PageSpeed check failed.' ) );
				}
				wp_send_json_success( $res );
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
					if ( ! Velox_Image_Optimizer::is_done( $id ) ) {
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

			case 'media_zip':
				$ids = isset( $_POST['ids'] ) ? (array) wp_unslash( $_POST['ids'] ) : array();
				$res = ( new Velox_Media_Manager() )->build_zip( $ids );
				if ( empty( $res['ok'] ) ) {
					wp_send_json_error( array( 'message' => isset( $res['message'] ) ? $res['message'] : 'Could not build the download.' ) );
				}
				wp_send_json_success( $res );
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

			case 'builder_plan':
				$id = isset( $_POST['builder'] ) ? sanitize_key( wp_unslash( $_POST['builder'] ) ) : '';
				if ( '' === $id ) { $id = Velox_Builders::detect(); }
				wp_send_json_success( Velox_Builders::plan( $id ) );
				break;

			case 'builder_apply':
				$id = isset( $_POST['builder'] ) ? sanitize_key( wp_unslash( $_POST['builder'] ) ) : '';
				$keep = null;
				if ( isset( $_POST['keep'] ) ) {
					$decoded = json_decode( wp_unslash( $_POST['keep'] ), true );
					if ( is_array( $decoded ) ) { $keep = array_map( 'sanitize_key', $decoded ); }
				}
				wp_send_json_success( Velox_Builders::apply( $id, $keep ) );
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
				// Allow any enable key declared in the utilities catalog, plus the core module toggles.
				$allowed = array( 'module_performance', 'module_seo', 'module_images' );
				foreach ( Velox_Utilities::catalog() as $tool ) {
					if ( ! empty( $tool['enable'] ) ) {
						$allowed[] = $tool['enable'];
					}
				}
				if ( ! in_array( $key, $allowed, true ) ) {
					wp_send_json_error( array( 'message' => 'Unknown tool.' ) );
				}
				Velox_Settings::set( $key, $on );
				// Front-end output may have changed (cookie banner, script rules, …) —
				// drop the page cache so the toggle takes effect immediately instead of
				// waiting for cached pages to expire.
				if ( method_exists( 'Velox_Cache', 'purge_all' ) ) { Velox_Cache::purge_all(); }
				wp_send_json_success( array( 'ok' => true, 'key' => $key, 'on' => $on ) );
				break;

			case 'media_scan':
				wp_send_json_success( array( 'items' => Velox_Utilities::scan_media() ) );
				break;

			case 'media_scan_start':
				wp_send_json_success( Velox_MediaScan::start() );
				break;

			case 'media_scan_step':
				wp_send_json_success( Velox_MediaScan::step() );
				break;

			case 'media_crawl_report':
				wp_send_json_success( Velox_MediaScan::crawl_report(
					isset( $_POST['paths'] ) ? (array) json_decode( wp_unslash( $_POST['paths'] ), true ) : array(),
					isset( $_POST['label'] ) ? wp_unslash( $_POST['label'] ) : ''
				) );
				break;

			case 'media_crawl_done':
				wp_send_json_success( Velox_MediaScan::crawl_done(
					isset( $_POST['pages'] ) ? (int) $_POST['pages'] : 0
				) );
				break;

			case 'media_scan_results':
				wp_send_json_success( Velox_MediaScan::results(
					isset( $_POST['filter'] ) ? sanitize_key( wp_unslash( $_POST['filter'] ) ) : 'all'
				) );
				break;

			case 'media_resize':
				$vx_mm  = new Velox_Media_Manager();
				$vx_res = $vx_mm->resize_image(
					isset( $_POST['id'] ) ? (int) $_POST['id'] : 0,
					isset( $_POST['w'] ) ? (int) $_POST['w'] : 0,
					isset( $_POST['h'] ) ? (int) $_POST['h'] : 0
				);
				if ( is_wp_error( $vx_res ) ) {
					wp_send_json_error( array( 'message' => $vx_res->get_error_message() ) );
				}
				wp_send_json_success( $vx_res );
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
				wp_send_json_success( Velox_Redirects::add( $src, $tgt, $type, self::redirect_opts() ) );
				break;

			case 'snippet_save':
				$sdata = array(
					'id'          => isset( $_POST['id'] ) ? (int) $_POST['id'] : 0,
					'name'        => isset( $_POST['name'] ) ? wp_unslash( $_POST['name'] ) : '',
					'description' => isset( $_POST['description'] ) ? wp_unslash( $_POST['description'] ) : '',
					'type'        => isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : 'php',
					'code'        => isset( $_POST['code'] ) ? wp_unslash( $_POST['code'] ) : '',
					'scope'       => isset( $_POST['scope'] ) ? sanitize_key( wp_unslash( $_POST['scope'] ) ) : 'everywhere',
					'location'    => isset( $_POST['location'] ) ? sanitize_key( wp_unslash( $_POST['location'] ) ) : '',
					'location_num'=> isset( $_POST['location_num'] ) ? (int) $_POST['location_num'] : 1,
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

			case 'snippet_clear_panic':
				wp_send_json_success( Velox_Snippets::clear_panic() );
				break;

			case 'snippet_disable_all':
				wp_send_json_success( Velox_Snippets::disable_all_php() );
				break;

			case 'velox_import':
				$source = isset( $_POST['source'] ) ? sanitize_key( wp_unslash( $_POST['source'] ) ) : '';
				wp_send_json_success( Velox_Import::run( $source ) );
				break;

			case 'backup_create':
				$what = isset( $_POST['what'] ) ? sanitize_key( wp_unslash( $_POST['what'] ) ) : 'both';
				$r = Velox_Backup::create( $what, 'Manual' );
				Velox_Backup::enforce_retention();
				if ( empty( $r['ok'] ) ) {
					wp_send_json_error( array( 'message' => isset( $r['message'] ) ? $r['message'] : 'Backup failed.' ) );
				}
				wp_send_json_success( $r );
				break;

			case 'backup_delete':
				$id = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
				wp_send_json_success( Velox_Backup::delete( $id ) );
				break;

			case 'backup_history_delete':
				$when = isset( $_POST['when'] ) ? sanitize_text_field( wp_unslash( $_POST['when'] ) ) : '';
				wp_send_json_success( Velox_Backup::delete_history( $when ) );
				break;

			case 'backup_history_clear':
				wp_send_json_success( Velox_Backup::clear_history() );
				break;

			case 'backup_restore':
				$id   = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
				$what = isset( $_POST['what'] ) ? sanitize_key( wp_unslash( $_POST['what'] ) ) : 'both';
				$safety = ! isset( $_POST['safety'] ) || '0' !== (string) $_POST['safety'];
				$r = Velox_Backup::restore( $id, $what, $safety );
				if ( empty( $r['ok'] ) ) {
					wp_send_json_error( array( 'message' => isset( $r['message'] ) ? $r['message'] : 'Restore failed.' ) );
				}
				wp_send_json_success( $r );
				break;

			case 'backup_import':
				$file = isset( $_FILES['file'] ) ? $_FILES['file'] : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
				$r = Velox_Backup::import( $file );
				if ( empty( $r['ok'] ) ) {
					wp_send_json_error( array( 'message' => isset( $r['message'] ) ? $r['message'] : 'Import failed.' ) );
				}
				// The user expects upload = restore. Immediately restore the freshly
				// imported backup (whatever it contained), keeping a safety backup so
				// the restore itself can be rolled back.
				$imp_what = ( ! empty( $r['entry']['what'] ) ) ? $r['entry']['what'] : 'both';
				$restore  = Velox_Backup::restore( $r['id'], $imp_what, true );
				if ( empty( $restore['ok'] ) ) {
					wp_send_json_error( array( 'message' => 'The backup uploaded, but restoring it failed: ' . ( isset( $restore['message'] ) ? $restore['message'] : 'unknown error' ) . ' It is saved in your backup list — you can retry Restore there.' ) );
				}
				$r['restored'] = true;
				$r['restore']  = $restore;
				$r['message']  = 'Backup imported and restored.';
				wp_send_json_success( $r );
				break;

			case 'backup_schedule':
				$all = Velox_Settings::all();
				if ( isset( $_POST['backup_schedule'] ) ) {
					$f = sanitize_key( wp_unslash( $_POST['backup_schedule'] ) );
					$all['backup_schedule'] = in_array( $f, array( 'off', 'daily', 'weekly', 'monthly' ), true ) ? $f : 'off';
				}
				if ( isset( $_POST['backup_schedule_what'] ) ) {
					$w = sanitize_key( wp_unslash( $_POST['backup_schedule_what'] ) );
					$all['backup_schedule_what'] = in_array( $w, array( 'db', 'files', 'both' ), true ) ? $w : 'both';
				}
				if ( isset( $_POST['backup_keep'] ) ) {
					$all['backup_keep'] = max( 1, min( 50, (int) $_POST['backup_keep'] ) );
				}
				Velox_Settings::save( $all );
				Velox_Backup::sync_schedule();
				wp_send_json_success( array( 'message' => 'Schedule saved.', 'next_run' => wp_next_scheduled( Velox_Backup::HOOK ) ) );
				break;

			case 'redirect_update':
				$rid  = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
				$src  = isset( $_POST['source'] ) ? wp_unslash( $_POST['source'] ) : '';
				$tgt  = isset( $_POST['target'] ) ? wp_unslash( $_POST['target'] ) : '';
				$type = isset( $_POST['type'] ) ? (int) $_POST['type'] : 301;
				wp_send_json_success( Velox_Redirects::update( $rid, $src, $tgt, $type, self::redirect_opts() ) );
				break;

			case 'redirect_delete':
				wp_send_json_success( Velox_Redirects::delete( isset( $_POST['id'] ) ? (int) $_POST['id'] : 0 ) );
				break;

			case 'redirect_toggle':
				wp_send_json_success( Velox_Redirects::set_active(
					isset( $_POST['id'] ) ? (int) $_POST['id'] : 0,
					isset( $_POST['on'] ) && ( '1' === (string) $_POST['on'] || 'true' === $_POST['on'] )
				) );
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

			case 'fields_save':
				$group = isset( $_POST['group'] ) ? json_decode( wp_unslash( $_POST['group'] ), true ) : null;
				if ( ! is_array( $group ) ) {
					wp_send_json_error( array( 'message' => 'Invalid field group data.' ) );
				}
				wp_send_json_success( Velox_Fields::save( $group ) );
				break;

			case 'fields_delete':
				wp_send_json_success( Velox_Fields::delete( isset( $_POST['id'] ) ? (int) $_POST['id'] : 0 ) );
				break;

			case 'posttype_save':
				$pt = isset( $_POST['post_type'] ) ? json_decode( wp_unslash( $_POST['post_type'] ), true ) : null;
				if ( ! is_array( $pt ) ) { wp_send_json_error( array( 'message' => 'Invalid post type data.' ) ); }
				$r = Velox_Post_Types::save_post_type( $pt );
				if ( empty( $r['ok'] ) ) { wp_send_json_error( array( 'message' => isset( $r['message'] ) ? $r['message'] : 'Could not save.' ) ); }
				wp_send_json_success( $r );
				break;

			case 'posttype_delete':
				wp_send_json_success( Velox_Post_Types::delete_post_type( isset( $_POST['slug'] ) ? Velox_Post_Types::clean_slug( wp_unslash( $_POST['slug'] ), 20 ) : '' ) );
				break;

			case 'taxonomy_save':
				$tx = isset( $_POST['taxonomy'] ) ? json_decode( wp_unslash( $_POST['taxonomy'] ), true ) : null;
				if ( ! is_array( $tx ) ) { wp_send_json_error( array( 'message' => 'Invalid taxonomy data.' ) ); }
				$r = Velox_Post_Types::save_taxonomy( $tx );
				if ( empty( $r['ok'] ) ) { wp_send_json_error( array( 'message' => isset( $r['message'] ) ? $r['message'] : 'Could not save.' ) ); }
				wp_send_json_success( $r );
				break;

			case 'taxonomy_delete':
				wp_send_json_success( Velox_Post_Types::delete_taxonomy( isset( $_POST['slug'] ) ? Velox_Post_Types::clean_slug( wp_unslash( $_POST['slug'] ), 32 ) : '' ) );
				break;

			case 'optionspage_save':
				$op = isset( $_POST['option_page'] ) ? json_decode( wp_unslash( $_POST['option_page'] ), true ) : null;
				if ( ! is_array( $op ) ) { wp_send_json_error( array( 'message' => 'Invalid options page data.' ) ); }
				$r = Velox_Fields::save_option_page( $op );
				if ( empty( $r['ok'] ) ) { wp_send_json_error( array( 'message' => isset( $r['message'] ) ? $r['message'] : 'Could not save.' ) ); }
				wp_send_json_success( $r );
				break;

			case 'optionspage_delete':
				wp_send_json_success( Velox_Fields::delete_option_page( isset( $_POST['slug'] ) ? sanitize_key( wp_unslash( $_POST['slug'] ) ) : '' ) );
				break;

			case 'vfx_toggle':
				$vtype  = isset( $_POST['vtype'] ) ? sanitize_key( wp_unslash( $_POST['vtype'] ) ) : '';
				$vid    = isset( $_POST['id'] ) ? wp_unslash( $_POST['id'] ) : '';
				$vact   = ! empty( $_POST['active'] );
				$vok    = false;
				if ( 'group' === $vtype ) {
					$vok = Velox_Fields::set_group_active( (int) $vid, $vact );
				} elseif ( 'optionpage' === $vtype ) {
					$vok = Velox_Fields::set_option_page_active( sanitize_key( $vid ), $vact );
				} elseif ( 'posttype' === $vtype ) {
					$vok = Velox_Post_Types::set_post_type_active( Velox_Post_Types::clean_slug( $vid, 20 ), $vact );
				} elseif ( 'taxonomy' === $vtype ) {
					$vok = Velox_Post_Types::set_taxonomy_active( Velox_Post_Types::clean_slug( $vid, 32 ), $vact );
				}
				if ( $vok ) { wp_send_json_success( array( 'active' => $vact ) ); }
				wp_send_json_error( array( 'message' => 'Could not update.' ) );
				break;

			case 'submission_delete':
				wp_send_json_success( Velox_Forms::delete_submission( isset( $_POST['id'] ) ? (int) $_POST['id'] : 0 ) );
				break;

			case 'submission_restore':
				wp_send_json_success( Velox_Forms::restore_submission( isset( $_POST['id'] ) ? (int) $_POST['id'] : 0 ) );
				break;

			case 'submission_purge':
				wp_send_json_success( Velox_Forms::purge_submission( isset( $_POST['id'] ) ? (int) $_POST['id'] : 0 ) );
				break;

			case 'submission_bulk':
				$ids = isset( $_POST['ids'] ) ? array_map( 'intval', (array) json_decode( wp_unslash( $_POST['ids'] ), true ) ) : array();
				wp_send_json_success( Velox_Forms::bulk_submissions(
					$ids,
					isset( $_POST['bulk'] ) ? sanitize_key( wp_unslash( $_POST['bulk'] ) ) : ''
				) );
				break;

			case 'submission_deleted_list':
				wp_send_json_success( array( 'items' => Velox_Forms::inbox( 300, 0, 0, 'deleted' ) ) );
				break;

			case 'inbox_sync':
				// Lightweight poll used to keep the inbox live without a page reload.
				wp_send_json_success( array( 'items' => Velox_Forms::inbox( 60, 0, 0, 'active' ) ) );
				break;

			case 'entries_sync':
				$vx_fid  = isset( $_POST['form'] ) ? (int) $_POST['form'] : 0;
				$vx_rows = Velox_Forms::submissions( $vx_fid, 500 );
				$vx_out  = array();
				foreach ( $vx_rows as $vx_r ) {
					$vx_d = json_decode( $vx_r['data'], true );
					$vx_out[] = array(
						'id'      => (int) $vx_r['id'],
						'created' => $vx_r['created'],
						'ip'      => isset( $vx_r['ip'] ) ? $vx_r['ip'] : '',
						'data'    => is_array( $vx_d ) ? $vx_d : array(),
					);
				}
				wp_send_json_success( array( 'labels' => Velox_Forms::field_labels( $vx_fid ), 'items' => $vx_out ) );
				break;

			case 'fm_list':
				wp_send_json_success( Velox_Filemanager::list_dir( isset( $_POST['path'] ) ? wp_unslash( $_POST['path'] ) : '' ) );
				break;

			case 'fm_read':
				wp_send_json_success( Velox_Filemanager::read_file( isset( $_POST['path'] ) ? wp_unslash( $_POST['path'] ) : '' ) );
				break;

			case 'fm_save':
				wp_send_json_success( Velox_Filemanager::save_file(
					isset( $_POST['path'] ) ? wp_unslash( $_POST['path'] ) : '',
					isset( $_POST['content'] ) ? wp_unslash( $_POST['content'] ) : ''
				) );
				break;

			case 'mail_folders_save':
				$folders = isset( $_POST['folders'] ) ? json_decode( wp_unslash( $_POST['folders'] ), true ) : array();
				wp_send_json_success( Velox_Forms::save_folders( is_array( $folders ) ? $folders : array() ) );
				break;

			case 'submission_set_folder':
				wp_send_json_success( Velox_Forms::set_folder(
					isset( $_POST['id'] ) ? (int) $_POST['id'] : 0,
					isset( $_POST['folder'] ) ? sanitize_key( wp_unslash( $_POST['folder'] ) ) : ''
				) );
				break;

			case 'submission_get':
				$sub = Velox_Forms::submission( isset( $_POST['id'] ) ? (int) $_POST['id'] : 0 );
				if ( ! $sub ) {
					wp_send_json_error( array( 'message' => 'Submission not found.' ) );
				}
				wp_send_json_success( $sub );
				break;

			case 'submission_flag':
				$res = Velox_Forms::set_flag(
					isset( $_POST['id'] ) ? (int) $_POST['id'] : 0,
					isset( $_POST['flag'] ) ? sanitize_key( wp_unslash( $_POST['flag'] ) ) : '',
					isset( $_POST['on'] ) && ( '1' === (string) $_POST['on'] || 'true' === $_POST['on'] )
				);
				if ( false === $res ) {
					wp_send_json_error( array( 'message' => 'Unknown flag.' ) );
				}
				wp_send_json_success( $res );
				break;

			case 'submission_reply':
				$res = Velox_Forms::reply(
					isset( $_POST['id'] ) ? (int) $_POST['id'] : 0,
					isset( $_POST['subject'] ) ? wp_unslash( $_POST['subject'] ) : '',
					isset( $_POST['body'] ) ? wp_unslash( $_POST['body'] ) : '',
					isset( $_POST['from_email'] ) ? sanitize_email( wp_unslash( $_POST['from_email'] ) ) : '',
					isset( $_POST['from_name'] ) ? wp_unslash( $_POST['from_name'] ) : ''
				);
				$this->respond( $res );
				break;

			case 'mail_template_save':
				$this->respond( Velox_Forms::save_reply_template(
					isset( $_POST['name'] ) ? wp_unslash( $_POST['name'] ) : '',
					isset( $_POST['subject'] ) ? wp_unslash( $_POST['subject'] ) : '',
					isset( $_POST['body'] ) ? wp_unslash( $_POST['body'] ) : ''
				) );
				break;

			case 'mail_template_delete':
				wp_send_json_success( Velox_Forms::delete_reply_template(
					isset( $_POST['id'] ) ? wp_unslash( $_POST['id'] ) : ''
				) );
				break;

			case 'mail_test':
				wp_send_json_success( Velox_Mail::send_test(
					isset( $_POST['to'] ) ? sanitize_email( wp_unslash( $_POST['to'] ) ) : '',
					isset( $_POST['conn'] ) ? sanitize_key( wp_unslash( $_POST['conn'] ) ) : ''
				) );
				break;

			case 'mail_deliverability':
				wp_send_json_success( Velox_Mail::deliverability_report() );
				break;

			case 'mail_conn_test':
				wp_send_json_success( Velox_Mail::test_connection( array(
					'host'   => isset( $_POST['host'] ) ? sanitize_text_field( wp_unslash( $_POST['host'] ) ) : '',
					'port'   => isset( $_POST['port'] ) ? (int) $_POST['port'] : 587,
					'secure' => isset( $_POST['secure'] ) ? sanitize_key( wp_unslash( $_POST['secure'] ) ) : 'tls',
					'user'   => isset( $_POST['user'] ) ? wp_unslash( $_POST['user'] ) : '',
					'pass'   => isset( $_POST['pass'] ) ? wp_unslash( $_POST['pass'] ) : '',
				) ) );
				break;

			case 'mail_resend':
				wp_send_json_success( Velox_Mail::resend( isset( $_POST['id'] ) ? (int) $_POST['id'] : 0 ) );
				break;

			case 'mail_save_routing':
				$conns    = isset( $_POST['connections'] ) ? json_decode( wp_unslash( $_POST['connections'] ), true ) : array();
				$routes   = isset( $_POST['routes'] ) ? json_decode( wp_unslash( $_POST['routes'] ), true ) : array();
				$primary  = isset( $_POST['primary'] ) ? sanitize_key( wp_unslash( $_POST['primary'] ) ) : '';
				$fallback = isset( $_POST['fallback'] ) ? sanitize_key( wp_unslash( $_POST['fallback'] ) ) : '';
				wp_send_json_success( Velox_Mail::save_routing(
					is_array( $conns ) ? $conns : array(),
					is_array( $routes ) ? $routes : array(),
					$primary,
					$fallback
				) );
				break;

			case 'mail_log_clear':
				wp_send_json_success( Velox_Mail::clear_log() );
				break;

			case 'cookie_preview':
				$opts_raw = isset( $_POST['opts'] ) ? json_decode( wp_unslash( $_POST['opts'] ), true ) : array();
				$opts_raw = is_array( $opts_raw ) ? $opts_raw : array();
				// Coerce a few numerics/bools the JS sends as strings.
				foreach ( array( 'cookie_border_width', 'cookie_radius', 'cookie_offset', 'cookie_width', 'cookie_font_size', 'cookie_gap', 'cookie_grid_cols', 'cookie_pad_y', 'cookie_pad_x', 'cookie_margin', 'cookie_heading_size', 'cookie_heading_weight', 'cookie_body_size', 'cookie_btn_gap', 'cookie_btn_font_size', 'cookie_btn_font_weight', 'cookie_backdrop_blur', 'cookie_max_height', 'cookie_z_index' ) as $nk ) {
					if ( isset( $opts_raw[ $nk ] ) ) { $opts_raw[ $nk ] = (int) $opts_raw[ $nk ]; }
				}
				foreach ( array( 'cookie_shadow', 'cookie_overlay', 'cookie_cat_analytics', 'cookie_cat_marketing', 'cookie_btn_full_mobile', 'cookie_link_underline' ) as $bk ) {
					if ( isset( $opts_raw[ $bk ] ) ) { $opts_raw[ $bk ] = ( '1' === (string) $opts_raw[ $bk ] || 1 === $opts_raw[ $bk ] || true === $opts_raw[ $bk ] ); }
				}
				$opts = Velox_Cookies::options( $opts_raw );
				wp_send_json_success( array(
					'css'  => Velox_Cookies::style_block( $opts, '#vxck-stage' ),
					'html' => Velox_Cookies::markup( $opts, true ),
				) );
				break;

			case 'october_build':
				Velox_October::maybe_install();
				$r = Velox_October::build( isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '' );
				if ( empty( $r['ok'] ) ) {
					wp_send_json_error( array( 'message' => isset( $r['message'] ) ? $r['message'] : 'Build failed.', 'report' => isset( $r['report'] ) ? $r['report'] : array() ) );
				}
				wp_send_json_success( $r );
				break;

			case 'october_rescan':
				Velox_October::maybe_install();
				$r = Velox_October::build( '', isset( $_POST['project'] ) ? sanitize_title( wp_unslash( $_POST['project'] ) ) : '' );
				if ( empty( $r['ok'] ) ) {
					wp_send_json_error( array( 'message' => isset( $r['message'] ) ? $r['message'] : 'Re-scan failed.', 'report' => isset( $r['report'] ) ? $r['report'] : array() ) );
				}
				wp_send_json_success( $r );
				break;

			case 'october_diag':
				Velox_October::maybe_install();
				wp_send_json_success( Velox_October::diagnose() );
				break;

			case 'october_edit_payload':
				$r = Velox_October::edit_payload( isset( $_POST['id'] ) ? (int) $_POST['id'] : 0 );
				if ( empty( $r['ok'] ) ) {
					wp_send_json_error( array( 'message' => isset( $r['message'] ) ? $r['message'] : 'Could not load build.' ) );
				}
				wp_send_json_success( $r );
				break;

			case 'october_apply_renames':
				$map = isset( $_POST['map'] ) ? json_decode( wp_unslash( $_POST['map'] ), true ) : array();
				$r   = Velox_October::apply_renames( isset( $_POST['id'] ) ? (int) $_POST['id'] : 0, is_array( $map ) ? $map : array() );
				if ( empty( $r['ok'] ) ) {
					wp_send_json_error( array( 'message' => isset( $r['message'] ) ? $r['message'] : 'Rename failed.' ) );
				}
				wp_send_json_success( $r );
				break;

			case 'october_delete':
				wp_send_json_success( Velox_October::delete( isset( $_POST['id'] ) ? (int) $_POST['id'] : 0 ) );
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

			case 'seo_health':
				wp_send_json_success( Velox_SEO_Health::scan() );
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

			case 'seo_htaccess_unlock':
				wp_send_json_success( Velox_Seo::htaccess_unlock() );
				break;

			case 'seo_htaccess_save':
				$content = isset( $_POST['content'] ) ? wp_unslash( $_POST['content'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				wp_send_json_success( Velox_Seo::htaccess_save( $content ) );
				break;

			case 'seo_htaccess_reset':
				wp_send_json_success( Velox_Seo::htaccess_reset() );
				break;

			case 'seo_sitemap_generate':
				$ok = Velox_Seo::generate_sitemap();
				wp_send_json_success( Velox_Seo::sitemap_stats() + array( 'generated' => $ok ) );

			case 'seo_sitemap_preview':
				$all = Velox_Seo::sitemap_entries( 200 );
				wp_send_json_success( array(
					'entries' => array_slice( $all, 0, 150 ),
					'total'   => count( $all ),
					'home'    => trailingslashit( home_url( '/' ) ),
				) );
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

			case 'detect_fonts':
				$fonts = new Velox_Fonts();
				$this->respond( $fonts->detect() );
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

		// Keep the PageSpeed cron in step when its enable/interval changed.
		if ( class_exists( 'Velox_Pagespeed' ) && ( array_key_exists( 'ps_enable', $raw ) || array_key_exists( 'ps_interval', $raw ) ) ) {
			Velox_Pagespeed::sync_schedule();
		}
		wp_send_json_success( array( 'message' => __( 'Settings saved.', 'velox' ) ) );
	}

	/** Collect the extra redirect-rule options from the modal POST. */
	private static function redirect_opts() {
		return array(
			'match_type'   => isset( $_POST['match_type'] ) ? sanitize_key( wp_unslash( $_POST['match_type'] ) ) : 'exact',
			'priority'     => isset( $_POST['priority'] ) ? (int) $_POST['priority'] : 0,
			'category'     => isset( $_POST['category'] ) ? wp_unslash( $_POST['category'] ) : '',
			'description'  => isset( $_POST['description'] ) ? wp_unslash( $_POST['description'] ) : '',
			'active'       => isset( $_POST['active'] ) ? ( '1' === (string) $_POST['active'] || 'true' === (string) $_POST['active'] ) : true,
			'ignore_case'  => isset( $_POST['ignore_case'] ) ? ( '1' === (string) $_POST['ignore_case'] || 'true' === (string) $_POST['ignore_case'] ) : true,
			'ignore_query' => isset( $_POST['ignore_query'] ) ? ( '1' === (string) $_POST['ignore_query'] || 'true' === (string) $_POST['ignore_query'] ) : true,
			'ignore_slash' => isset( $_POST['ignore_slash'] ) ? ( '1' === (string) $_POST['ignore_slash'] || 'true' === (string) $_POST['ignore_slash'] ) : true,
		);
	}
}
