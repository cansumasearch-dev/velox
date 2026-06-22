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
delete_transient( 'velox_latest_release' );

// Per-page auto-learn data (one non-autoloaded option per URL).
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'velox_csslearn_%'" );

// Per-page override meta (the page-level "disable feature here" box).
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_velox_overrides'" );

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
