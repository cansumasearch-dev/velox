<?php
/**
 * Velox uninstall.
 *
 * Runs only when the plugin is deleted from the WordPress admin (not on
 * deactivate). By default it removes Velox's own settings, tables and scheduled
 * events. If "Keep my settings if I delete Velox" is switched on in Settings,
 * everything is left in place so a reinstall picks up right where you left off.
 *
 * It never touches your media, your WebP files, or anything it converted.
 *
 * @package Velox
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Respect the "keep my settings if I delete Velox" option.
$velox_opts = get_option( 'velox_settings', array() );
$velox_keep = is_array( $velox_opts ) && ! empty( $velox_opts['keep_data_on_uninstall'] );

// Always clear scheduled events + the cache drop-in — the plugin is going away.
foreach ( array( 'velox_backup_run', 'velox_weekly_cleanup', 'velox_pagespeed_refresh', 'velox_cache_preload' ) as $velox_hook ) {
	$velox_ts = wp_next_scheduled( $velox_hook );
	while ( $velox_ts ) {
		wp_unschedule_event( $velox_ts, $velox_hook );
		$velox_ts = wp_next_scheduled( $velox_hook );
	}
}
if ( class_exists( 'Velox_Cache' ) ) {
	Velox_Cache::remove_dropin();
}
delete_transient( 'velox_latest_release' );

// Keep everything else in place when asked.
if ( $velox_keep ) {
	return;
}

// ---- Full wipe (default) ----

// Core options + version flags + logs.
$velox_options = array(
	'velox_settings', 'velox_settings_schema', 'velox_local_fonts', 'velox_blueprints',
	'velox_redirects_map', 'velox_redirects_db', 'velox_activity_db', 'velox_assets_seen',
	'velox_script_rules', 'velox_forms', 'velox_forms_db', 'velox_mail_db',
	'velox_snippets_db', 'velox_snippets_safe', 'velox_snippets_panic',
	'velox_traffic', 'velox_form_log', 'velox_pagespeed',
);
foreach ( $velox_options as $velox_opt ) {
	delete_option( $velox_opt );
}

// Backups folder.
$velox_bk = WP_CONTENT_DIR . '/velox-backups';
if ( is_dir( $velox_bk ) ) {
	$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $velox_bk, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
	foreach ( $it as $f ) {
		$f->isDir() ? @rmdir( $f->getPathname() ) : @unlink( $f->getPathname() );
	}
	@rmdir( $velox_bk );
}

// Per-page auto-learn data + override meta, and Velox's own tables.
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'velox_csslearn_%'" );
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_velox_overrides'" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}velox_redirects" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}velox_404s" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}velox_activity" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}velox_submissions" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}velox_mail_log" );

// Generated cache folders (trimmed CSS, local fonts, temporary downloads).
$uploads = wp_upload_dir();
if ( ! empty( $uploads['basedir'] ) ) {
	foreach ( array( 'velox-css', 'velox-fonts', 'velox-tmp' ) as $folder ) {
		$dir = trailingslashit( $uploads['basedir'] ) . $folder;
		if ( is_dir( $dir ) ) {
			$files = glob( trailingslashit( $dir ) . '*' );
			if ( is_array( $files ) ) {
				foreach ( $files as $f ) {
					if ( is_file( $f ) ) {
						@unlink( $f );
					}
				}
			}
			@rmdir( $dir );
		}
	}
}

// Note: your media, WebP files and the EXIF/resize work stay exactly where they are.
