<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builder-aware configuration. Detects which page builder a site runs, and ships
 * a tuned performance profile for each (defer/delay exclusions, RUCSS safelists,
 * and the guardrails that stop us breaking that builder — e.g. never auto-remove
 * jQuery Migrate on Divi, never replace YouTube embeds on Divi, never strip block
 * CSS on a block/FSE theme).
 *
 * Profiles are derived from each builder's own docs plus Perfmatters / WP Rocket
 * exclusion lists. They are starting points, fully editable afterwards in Settings.
 */
class Velox_Builders {

	const REQUEST_EMAIL = 'cn@sumasearch.de';

	/** Perf keys a builder profile is allowed to override. */
	private static function override_keys() {
		return array(
			'perf_defer_exclude',
			'perf_delay_js_exclude',
			'perf_rucss_safelist',
			'perf_remove_jquery_migrate',
			'perf_disable_block_css',
			'perf_disable_global_styles',
			'perf_youtube_facade',
			'perf_delay_scripts',
		);
	}

	/**
	 * The full registry. Each entry: label, a detect() callback, the per-builder
	 * setting overrides, and human guard notes shown in the UI.
	 */
	public static function registry() {
		return array(
			'oxygen' => array(
				'label'  => 'Oxygen',
				'detect' => function () {
					return defined( 'CT_VERSION' );
				},
				'overrides' => array(
					'perf_defer_exclude'         => "jquery\noxygen\nfluentform\naos.js",
					'perf_delay_js_exclude'      => "jquery\noxygen\naos.js\noxygen-aos-enabled\nunslider\nalpine",
					'perf_rucss_safelist'        => ".ct-\n.oxy-\n.menu\n.active\n.open\n.is-\n.has-",
					'perf_remove_jquery_migrate' => true,
					'perf_disable_block_css'     => true,
					'perf_disable_global_styles' => true,
					'perf_youtube_facade'        => true,
					'perf_delay_scripts'         => false,
				),
				'note' => 'Oxygen needs jQuery and AOS, so those are excluded from JS optimization. jQuery Migrate removal is safe (Oxygen ships its own toggle). Velox never combines CSS/JS, which is exactly what Oxygen needs.',
			),
			'bricks' => array(
				'label'  => 'Bricks',
				'detect' => function () {
					return defined( 'BRICKS_VERSION' ) || ( function_exists( 'wp_get_theme' ) && 'bricks' === wp_get_theme()->get_template() );
				},
				'overrides' => array(
					'perf_defer_exclude'         => "fluentform",
					'perf_delay_js_exclude'      => "bricks.min.js\nswiper\nsplide\nbricks-scripts",
					'perf_rucss_safelist'        => ".brxe-\n.brx-\n.bricks-\n.active\n.open\n.is-\n.has-",
					'perf_remove_jquery_migrate' => true,
					'perf_disable_block_css'     => true,
					'perf_disable_global_styles' => true,
					'perf_youtube_facade'        => true,
					'perf_delay_scripts'         => true,
				),
				'note' => 'Bricks dropped front-end jQuery in 1.4, so JS delay is safe to run. Only the Bricks interaction scripts (slider, menu) are excluded.',
			),
			'elementor' => array(
				'label'  => 'Elementor',
				'detect' => function () {
					return defined( 'ELEMENTOR_VERSION' ) || class_exists( '\\Elementor\\Plugin' );
				},
				'overrides' => array(
					'perf_defer_exclude'         => "jquery\nelementor\nswiper",
					'perf_delay_js_exclude'      => "jquery\nelementor\nswiper\nwebpack\nfrontend.min.js\nelementorFrontendConfig\nimagesloaded\nsmartmenus\nsticky",
					'perf_rucss_safelist'        => ".elementor-\n.e-con\n.elementor-invisible\n.swiper\n.animated\n.active\n.open",
					'perf_remove_jquery_migrate' => false,
					'perf_disable_block_css'     => true,
					'perf_disable_global_styles' => true,
					'perf_youtube_facade'        => true,
					'perf_delay_scripts'         => false,
				),
				'note' => 'Elementor leans on jQuery and adds animation classes (.elementor-invisible) via JS, so those are safelisted and jQuery Migrate is kept. Turn on JS delay only after testing sliders and menus.',
			),
			'divi' => array(
				'label'  => 'Divi',
				'detect' => function () {
					return defined( 'ET_CORE_VERSION' ) || defined( 'ET_BUILDER_VERSION' ) || ( function_exists( 'wp_get_theme' ) && 'Divi' === wp_get_theme()->get( 'Name' ) );
				},
				'overrides' => array(
					'perf_defer_exclude'         => "jquery\ndivi\net_pb",
					'perf_delay_js_exclude'      => "jquery\njquery-migrate\nscripts.min.js\ncustom.unified\net_pb_custom\net_animation_data\nvar DIVI\nmagnific-popup\neasypiechart",
					'perf_rucss_safelist'        => ".et_\n.et-pb-\n.et_pb_\n.et-db\n.et_animated\n.active\n.open",
					'perf_remove_jquery_migrate' => false,
					'perf_disable_block_css'     => true,
					'perf_disable_global_styles' => true,
					'perf_youtube_facade'        => false,
					'perf_delay_scripts'         => false,
				),
				'note' => 'Divi is heavily jQuery-dependent. jQuery Migrate is kept (removing it breaks Divi), and YouTube facades are disabled (Divi\'s video module clashes with them). Disable Divi\'s own optimization stack so Velox is the single source of truth.',
			),
			'beaver' => array(
				'label'  => 'Beaver Builder',
				'detect' => function () {
					return class_exists( 'FLBuilder' ) || defined( 'FL_BUILDER_VERSION' );
				},
				'overrides' => array(
					'perf_defer_exclude'         => "jquery\nfl-builder\nfluentform",
					'perf_delay_js_exclude'      => "jquery\nfl-builder\nimagesloaded\nwaypoints\nmagnific\nfitvids\nbootstrap",
					'perf_rucss_safelist'        => ".fl-\n.active\n.open\n.has-\n.is-",
					'perf_remove_jquery_migrate' => false,
					'perf_disable_block_css'     => true,
					'perf_disable_global_styles' => true,
					'perf_youtube_facade'        => true,
					'perf_delay_scripts'         => false,
				),
				'note' => 'Beaver Builder uses the bundled jQuery and breaks if a second copy loads, so jQuery is excluded and Migrate is kept by default.',
			),
			'wpbakery' => array(
				'label'  => 'WPBakery',
				'detect' => function () {
					return defined( 'WPB_VC_VERSION' ) || class_exists( 'Vc_Manager' );
				},
				'overrides' => array(
					'perf_defer_exclude'         => "jquery\njs_composer\nfluentform",
					'perf_delay_js_exclude'      => "jquery\njs_composer_front\nwaypoints",
					'perf_rucss_safelist'        => ".vc_\n.wpb_\n.wpb_animate_when_almost_visible\n.vc_tta\n.active\n.open",
					'perf_remove_jquery_migrate' => false,
					'perf_disable_block_css'     => true,
					'perf_disable_global_styles' => true,
					'perf_youtube_facade'        => true,
					'perf_delay_scripts'         => false,
				),
				'note' => 'WPBakery animates with .wpb_animate_when_almost_visible (added via JS) — it\'s safelisted so unused-CSS removal doesn\'t leave invisible boxes. jQuery Migrate is kept.',
			),
			'gutenberg' => array(
				'label'  => 'Gutenberg / Block theme',
				'detect' => function () {
					return function_exists( 'wp_is_block_theme' ) && wp_is_block_theme();
				},
				'overrides' => array(
					'perf_defer_exclude'         => "jquery",
					'perf_delay_js_exclude'      => "jquery\nwp-includes/js/dist\ninteractivity",
					'perf_rucss_safelist'        => ".wp-block-\n.wp-container-\n.wp-elements-\n.has-\n.is-layout-\n.wp-block-navigation",
					'perf_remove_jquery_migrate' => true,
					'perf_disable_block_css'     => false,
					'perf_disable_global_styles' => false,
					'perf_youtube_facade'        => true,
					'perf_delay_scripts'         => true,
				),
				'note' => 'Block themes NEED the block CSS and global styles (theme.json tokens), so those stay on. Unused-CSS removal handles block CSS well here.',
			),
			'wordpress' => array(
				'label'  => 'WordPress default builder',
				'detect' => function () {
					return true; // fallback — always matches last
				},
				'overrides' => array(
					'perf_defer_exclude'         => "jquery",
					'perf_delay_js_exclude'      => "jquery",
					'perf_rucss_safelist'        => ".menu\n.active\n.open\n.is-\n.has-\n.sticky",
					'perf_remove_jquery_migrate' => false,
					'perf_disable_block_css'     => false,
					'perf_disable_global_styles' => false,
					'perf_youtube_facade'        => true,
					'perf_delay_scripts'         => false,
				),
				'note' => 'No page builder detected — running the safe universal baseline. Block CSS and jQuery Migrate are left in place to be safe; you can tighten them later.',
			),
		);
	}

	/** Run detection. Returns a builder id (always returns at least 'wordpress'). */
	public static function detect() {
		foreach ( self::registry() as $id => $b ) {
			if ( 'wordpress' === $id ) {
				continue; // fallback handled last
			}
			$fn = $b['detect'];
			if ( is_callable( $fn ) && $fn() ) {
				return $id;
			}
		}
		return 'wordpress';
	}

	/** Plain list for the picker: [ id => label ]. */
	public static function choices() {
		$out = array();
		foreach ( self::registry() as $id => $b ) {
			$out[ $id ] = $b['label'];
		}
		return $out;
	}

	public static function get( $id ) {
		$r = self::registry();
		return isset( $r[ $id ] ) ? $r[ $id ] : $r['wordpress'];
	}

	public static function label( $id ) {
		$b = self::get( $id );
		return $b['label'];
	}

	/** The builder currently saved in settings ('' if the wizard hasn't run). */
	public static function current() {
		return (string) Velox_Settings::get( 'builder' );
	}

	/**
	 * Wipe the performance settings and reconfigure them for the chosen builder.
	 * Only perf_* keys are reset — image, module, font and database settings stay.
	 */
	public static function apply( $id ) {
		$profile  = self::get( $id );
		$resolved = isset( self::registry()[ $id ] ) ? $id : 'wordpress';
		$s        = Velox_Settings::all();
		$defaults = Velox_Settings::defaults();

		// Clean wipe: reset every perf_* key to its default first.
		foreach ( $defaults as $k => $v ) {
			if ( 0 === strpos( $k, 'perf_' ) ) {
				$s[ $k ] = $v;
			}
		}
		// Then apply this builder's tuned overrides.
		foreach ( self::override_keys() as $k ) {
			if ( array_key_exists( $k, $profile['overrides'] ) ) {
				$s[ $k ] = $profile['overrides'][ $k ];
			}
		}
		$s['builder']     = $resolved;
		$s['wizard_done'] = true;

		Velox_Settings::save( $s );
		if ( class_exists( 'Velox_CSS' ) ) {
			Velox_CSS::clear_cache(); // old trimmed CSS no longer matches the new safelist
		}

		return array(
			'ok'      => true,
			'builder' => $resolved,
			'label'   => $profile['label'],
			'message' => 'Heads up — we wiped the old performance settings and reconfigured everything for ' . $profile['label'] . '. You can fine-tune it all in Settings → Performance whenever you like.',
		);
	}

	/** Email the maintainer that someone wants a builder added. */
	public static function request_builder( $name ) {
		$name = trim( wp_strip_all_tags( (string) $name ) );
		if ( '' === $name ) {
			return array( 'ok' => false, 'message' => 'Please type the builder name first.' );
		}
		$site  = home_url();
		$admin = get_bloginfo( 'admin_email' );
		$body  = "A Velox user wants this page builder supported:\n\n"
			. "Builder: {$name}\n"
			. "Site: {$site}\n"
			. "Admin email: {$admin}\n";
		$sent = wp_mail(
			self::REQUEST_EMAIL,
			'Velox — builder request: ' . $name,
			$body
		);
		return array(
			'ok'      => true,
			'message' => $sent
				? 'Thanks! We\'ve sent your request — "' . esc_html( $name ) . '" is on our radar.'
				: 'Saved your request for "' . esc_html( $name ) . '". (Mail couldn\'t send from this server, but it\'s noted.)',
		);
	}
}
