<?php
/**
 * Velox_Conflicts — spots other active plugins that overlap a Velox feature
 * you've switched on (two caching plugins, two SEO plugins, etc.) and flags it,
 * since running both usually means they fight over the same files/output.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Velox_Conflicts {

	/** Folder slug => [ name, area ]. Area maps to a Velox feature toggle. */
	private static function known() {
		return array(
			// Caching / performance
			'wp-rocket'              => array( 'WP Rocket', 'performance' ),
			'w3-total-cache'         => array( 'W3 Total Cache', 'performance' ),
			'wp-super-cache'         => array( 'WP Super Cache', 'performance' ),
			'litespeed-cache'        => array( 'LiteSpeed Cache', 'performance' ),
			'wp-fastest-cache'       => array( 'WP Fastest Cache', 'performance' ),
			'autoptimize'            => array( 'Autoptimize', 'performance' ),
			'cache-enabler'          => array( 'Cache Enabler', 'performance' ),
			'comet-cache'            => array( 'Comet Cache', 'performance' ),
			'breeze'                 => array( 'Breeze', 'performance' ),
			'sg-cachepress'          => array( 'SiteGround Optimizer', 'performance' ),
			'swift-performance-lite' => array( 'Swift Performance', 'performance' ),
			'wp-optimize'            => array( 'WP-Optimize', 'performance' ),
			'hummingbird-performance'=> array( 'Hummingbird', 'performance' ),
			'nitropack'              => array( 'NitroPack', 'performance' ),
			'flying-press'           => array( 'FlyingPress', 'performance' ),
			'perfmatters'            => array( 'Perfmatters', 'performance' ),
			// SEO
			'wordpress-seo'          => array( 'Yoast SEO', 'seo' ),
			'seo-by-rank-math'       => array( 'Rank Math', 'seo' ),
			'all-in-one-seo-pack'    => array( 'All in One SEO', 'seo' ),
			'wp-seopress'            => array( 'SEOPress', 'seo' ),
			'autodescription'        => array( 'The SEO Framework', 'seo' ),
			'squirrly-seo'           => array( 'Squirrly SEO', 'seo' ),
			'slim-seo'               => array( 'Slim SEO', 'seo' ),
			// Forms
			'contact-form-7'         => array( 'Contact Form 7', 'forms' ),
			'wpforms-lite'           => array( 'WPForms', 'forms' ),
			'wpforms'                => array( 'WPForms', 'forms' ),
			'fluentform'             => array( 'Fluent Forms', 'forms' ),
			'gravityforms'           => array( 'Gravity Forms', 'forms' ),
			'ninja-forms'            => array( 'Ninja Forms', 'forms' ),
			'formidable'             => array( 'Formidable Forms', 'forms' ),
			'forminator'             => array( 'Forminator', 'forms' ),
			// Maintenance / coming soon
			'coming-soon'            => array( 'SeedProd', 'maintenance' ),
			'wp-maintenance-mode'    => array( 'WP Maintenance Mode', 'maintenance' ),
			'under-construction-page'=> array( 'UnderConstructionPage', 'maintenance' ),
			// Hide login
			'wps-hide-login'         => array( 'WPS Hide Login', 'login' ),
		);
	}

	private static function area_label( $area ) {
		$labels = array(
			'performance' => 'caching &amp; optimization',
			'seo'         => 'SEO',
			'forms'       => 'forms &amp; mail',
			'maintenance' => 'maintenance mode',
			'login'       => 'the custom login URL',
		);
		return isset( $labels[ $area ] ) ? $labels[ $area ] : $area;
	}

	private static function area_active( $area ) {
		switch ( $area ) {
			case 'performance': return (bool) Velox_Settings::get( 'module_performance', true );
			case 'seo':         return (bool) Velox_Settings::get( 'module_seo', true );
			case 'forms':       return (bool) Velox_Settings::get( 'util_mail', false );
			case 'maintenance': return (bool) Velox_Settings::get( 'util_maintenance', false );
			case 'login':       return (bool) Velox_Settings::get( 'util_loginurl', false );
		}
		return false;
	}

	private static function active_slugs() {
		$active = (array) get_option( 'active_plugins', array() );
		if ( is_multisite() ) {
			$active = array_merge( $active, array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
		}
		$slugs = array();
		foreach ( $active as $file ) {
			$slugs[] = strtok( (string) $file, '/' ); // folder slug (or single-file name)
		}
		return $slugs;
	}

	/** @return array list of [ name, area, label ] conflicts that are actually live. */
	public static function detect() {
		$active = self::active_slugs();
		$known  = self::known();
		$out    = array();
		$seen   = array();
		foreach ( $known as $slug => $info ) {
			if ( ! in_array( $slug, $active, true ) ) {
				continue;
			}
			if ( ! self::area_active( $info[1] ) ) {
				continue;
			}
			$dedupe = $info[0] . '|' . $info[1];
			if ( isset( $seen[ $dedupe ] ) ) {
				continue;
			}
			$seen[ $dedupe ] = 1;
			$out[] = array( 'name' => $info[0], 'area' => $info[1], 'label' => self::area_label( $info[1] ) );
		}
		return $out;
	}

	/* ----------------------------------------------------------------- admin */

	public static function init() {
		add_action( 'admin_notices', array( __CLASS__, 'notice' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_dismiss' ) );
	}

	/** A stable signature of the current conflict set, so a dismissal only lasts
	 *  until the set of conflicting plugins actually changes. */
	private static function signature( $conflicts ) {
		$names = array_map( function ( $c ) { return $c['name'] . ':' . $c['area']; }, $conflicts );
		sort( $names );
		return md5( implode( ',', $names ) );
	}

	public static function maybe_dismiss() {
		if ( empty( $_GET['velox_dismiss_clash'] ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ), 'velox_dismiss_clash' ) ) {
			return;
		}
		update_user_meta( get_current_user_id(), 'velox_clash_dismissed', sanitize_text_field( wp_unslash( $_GET['velox_dismiss_clash'] ) ) );
		wp_safe_redirect( remove_query_arg( array( 'velox_dismiss_clash', '_wpnonce' ) ) );
		exit;
	}

	public static function notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		// Only on Velox screens + the main dashboard, to avoid nagging everywhere.
		$on_velox = $screen && ( false !== strpos( (string) $screen->id, 'velox' ) || 'dashboard' === $screen->base );
		if ( ! $on_velox ) {
			return;
		}
		$conflicts = self::detect();
		if ( empty( $conflicts ) ) {
			return;
		}
		$sig = self::signature( $conflicts );
		if ( get_user_meta( get_current_user_id(), 'velox_clash_dismissed', true ) === $sig ) {
			return;
		}
		$names = array();
		foreach ( $conflicts as $c ) {
			$names[] = '<strong>' . esc_html( $c['name'] ) . '</strong> (' . $c['label'] . ')';
		}
		$dismiss = wp_nonce_url( add_query_arg( 'velox_dismiss_clash', $sig ), 'velox_dismiss_clash' );
		echo '<div class="notice notice-warning"><p style="font-size:13px;">'
			. '<strong>Velox: turf war detected.</strong> '
			. wp_kses_post( implode( ', ', $names ) )
			. ' ' . ( count( $conflicts ) > 1 ? 'are' : 'is' ) . ' covering the same ground as Velox. Running two plugins for the same job tends to make them fight over the same output — pick one. Velox already has it handled. '
			. '<a href="' . esc_url( admin_url( 'admin.php?page=velox' ) ) . '">Review in Velox</a> &middot; '
			. '<a href="' . esc_url( $dismiss ) . '">Dismiss</a>'
			. '</p></div>';
	}
}
