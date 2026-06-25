<?php
/**
 * Velox uninstall.
 *
 * Runs only when the plugin is deleted from the WordPress admin (not on
 * deactivate). Removes Velox's own settings and scheduled event. It does
 * NOT touch your media, your WebP files, or anything it converted — those
 * stay exactly where they are.
 *
 * @package Velox
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Core options + version flags.
delete_option( 'velox_settings' );
delete_option( 'velox_settings_schema' );
delete_option( 'velox_local_fonts' );
delete_option( 'velox_blueprints' );
delete_option( 'velox_redirects_map' );
delete_option( 'velox_redirects_db' );
delete_option( 'velox_activity_db' );
delete_option( 'velox_assets_seen' );
delete_option( 'velox_script_rules' );
delete_option( 'velox_forms' );
delete_option( 'velox_forms_db' );
delete_option( 'velox_mail_db' );
delete_option( 'velox_snippets_db' );
delete_option( 'velox_snippets_safe' );
delete_option( 'velox_snippets_panic' );
// Unschedule the backup cron and remove the backups folder.
$ts = wp_next_scheduled( 'velox_backup_run' );
if ( $ts ) { wp_unschedule_event( $ts, 'velox_backup_run' ); }
$velox_bk = WP_CONTENT_DIR . '/velox-backups';
if ( is_dir( $velox_bk ) ) {
	$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $velox_bk, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
	foreach ( $it as $f ) { $f->isDir() ? @rmdir( $f->getPathname() ) : @unlink( $f->getPathname() ); }
	@rmdir( $velox_bk );
}
if ( class_exists( 'Velox_Cache' ) ) { Velox_Cache::remove_dropin(); }
delete_transient( 'velox_latest_release' );

// Per-page auto-learn data (one non-autoloaded option per URL).
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'velox_csslearn_%'" );

// Per-page override meta (the page-level "disable feature here" box).
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_velox_overrides'" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}velox_redirects" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}velox_404s" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}velox_activity" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}velox_submissions" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}velox_mail_log" );

// Generated cache folders (trimmed CSS + locally hosted fonts).
$uploads = wp_upload_dir();
if ( ! empty( $uploads['basedir'] ) ) {
	foreach ( array( 'velox-css', 'velox-fonts' ) as $folder ) {
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

// Scheduled cleanup event.
$timestamp = wp_next_scheduled( 'velox_weekly_cleanup' );
if ( $timestamp ) {
	wp_unschedule_event( $timestamp, 'velox_weekly_cleanup' );
}

// Note: your media, WebP files and the EXIF/resize work stay exactly where they are.
