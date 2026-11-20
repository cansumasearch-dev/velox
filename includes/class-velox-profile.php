<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A boot timeline for finding slow page loads.
 *
 * Velox notes the time at each step of its own start (cheap: one microtime()
 * per step) and at the main WordPress stages. An administrator who opens any
 * page with ?velox_profile=1 sees the timeline at the end of the page source
 * and in the browser console. Nobody else ever sees anything.
 *
 * @package Velox
 */
class Velox_Profile {

	/** @var array<int,array{0:string,1:float}> */
	private static $marks = array();

	public static function mark( $label ) {
		self::$marks[] = array( (string) $label, microtime( true ) );
	}

	public static function init() {
		if ( empty( $_GET['velox_profile'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		foreach ( array( 'after_setup_theme', 'init', 'wp_loaded', 'parse_request', 'wp', 'template_redirect', 'wp_head', 'wp_footer' ) as $h ) {
			add_action( $h, function () use ( $h ) { self::mark( 'WordPress: ' . $h . ' starts' ); }, PHP_INT_MIN );
			add_action( $h, function () use ( $h ) { self::mark( 'WordPress: ' . $h . ' done' ); }, PHP_INT_MAX );
		}
		add_filter( 'template_include', function ( $t ) { self::mark( 'WordPress: template chosen' ); return $t; }, PHP_INT_MAX );
		add_action( 'shutdown', array( __CLASS__, 'output' ), PHP_INT_MAX );
	}

	public static function output() {
		if ( ! current_user_can( 'manage_options' ) || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}
		global $timestart, $wpdb;
		self::mark( 'end' );
		$t0   = isset( $_SERVER['REQUEST_TIME_FLOAT'] ) ? (float) $_SERVER['REQUEST_TIME_FLOAT'] : (float) $timestart;
		$rows = array( array( 'request received', 0, 0 ) );
		$prev = $t0;
		if ( $timestart ) {
			$rows[] = array( 'WordPress starts loading', round( ( $timestart - $t0 ) * 1000, 1 ), round( ( $timestart - $t0 ) * 1000, 1 ) );
			$prev   = $timestart;
		}
		foreach ( self::$marks as $m ) {
			$rows[] = array( $m[0], round( ( $m[1] - $t0 ) * 1000, 1 ), round( ( $m[1] - $prev ) * 1000, 1 ) );
			$prev   = $m[1];
		}
		$slow = $rows;
		usort( $slow, function ( $a, $b ) { return $b[2] <=> $a[2]; } );
		$lines = array();
		foreach ( $rows as $r ) {
			$lines[] = str_pad( number_format( $r[1], 1 ), 8, ' ', STR_PAD_LEFT ) . ' ms  +' . str_pad( number_format( $r[2], 1 ), 7, ' ', STR_PAD_LEFT ) . '  ' . $r[0];
		}
		$sum = sprintf( 'Velox profile: %s ms total, %d queries, %s MB peak memory, %d PHP files', number_format( ( microtime( true ) - $t0 ) * 1000, 1 ), (int) $wpdb->num_queries, number_format( memory_get_peak_usage() / 1048576, 1 ), count( get_included_files() ) );
		echo "\n<!--\n" . esc_html( $sum ) . "\n\nSlowest steps:\n";
		foreach ( array_slice( $slow, 0, 8 ) as $r ) {
			echo esc_html( '  +' . number_format( $r[2], 1 ) . ' ms  ' . $r[0] ) . "\n";
		}
		echo "\nTimeline (since the request arrived, and since the step before):\n" . esc_html( implode( "\n", $lines ) ) . "\n-->\n";
		echo '<script>console.log(' . wp_json_encode( $sum ) . ');console.table(' . wp_json_encode( array_map( function ( $r ) { return array( 'step' => $r[0], 'at ms' => $r[1], '+ms' => $r[2] ); }, $rows ) ) . ');</script>' . "\n";
	}
}
