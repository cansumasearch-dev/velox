<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Self-hosted updater. Points WordPress at GitHub releases instead of wp.org, so
 * the plugin stays private (never listed in the public directory) while still
 * showing a normal "Update available" notice in Plugins → Installed.
 *
 * Public repo  : works out of the box, no token.
 * Private repo : set a fine-grained personal access token in Velox → Settings.
 *
 * Release a new version by bumping the Version header, committing, and tagging
 * (e.g. v1.0.1) — see the included UPDATING.md.
 */
class Velox_Updater {

	private $user;
	private $repo;
	private $slug;       // plugin folder name
	private $basename;   // folder/file.php

	public function __construct() {
		$this->user     = VELOX_GH_USER;
		$this->repo     = VELOX_GH_REPO;
		$this->basename = VELOX_BASENAME;
		$this->slug     = dirname( VELOX_BASENAME );

		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_info' ), 20, 3 );
		add_filter( 'upgrader_source_selection', array( $this, 'fix_folder_name' ), 10, 4 );
	}

	private function api_args() {
		$args  = array( 'timeout' => 15, 'headers' => array( 'Accept' => 'application/vnd.github+json', 'User-Agent' => 'Velox-Updater' ) );
		$token = trim( (string) Velox_Settings::get( 'gh_token', '' ) );
		if ( '' !== $token ) {
			$args['headers']['Authorization'] = 'Bearer ' . $token;
		}
		return $args;
	}

	private function get_latest_release( $force = false ) {
		$cache_key = 'velox_latest_release';
		if ( $force ) {
			delete_transient( $cache_key );
		}
		$cached = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}
		$url      = sprintf( 'https://api.github.com/repos/%s/%s/releases/latest', $this->user, $this->repo );
		$response = wp_remote_get( $url, $this->api_args() );
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			set_transient( $cache_key, null, HOUR_IN_SECONDS );
			return null;
		}
		$data = json_decode( wp_remote_retrieve_body( $response ) );
		set_transient( $cache_key, $data, 6 * HOUR_IN_SECONDS );
		return $data;
	}

	private function normalize_version( $tag ) {
		return ltrim( (string) $tag, 'vV' );
	}

	private function download_url( $release ) {
		// Prefer a zip asset attached to the release; fall back to the auto zipball.
		if ( ! empty( $release->assets ) ) {
			foreach ( $release->assets as $asset ) {
				if ( substr( $asset->name, -4 ) === '.zip' ) {
					return $asset->browser_download_url;
				}
			}
		}
		return $release->zipball_url;
	}

	public function check_update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}
		// When the user clicks "Check again", honour it and skip our 6h cache.
		$force   = ! empty( $_GET['force-check'] ) || ( defined( 'DOING_AJAX' ) && DOING_AJAX && isset( $_REQUEST['velox_force'] ) );
		$release = $this->get_latest_release( $force );
		if ( ! $release || empty( $release->tag_name ) ) {
			return $transient;
		}

		$new_version = $this->normalize_version( $release->tag_name );
		$current     = $transient->checked[ $this->basename ] ?? VELOX_VERSION;

		if ( version_compare( $new_version, $current, '>' ) ) {
			$transient->response[ $this->basename ] = (object) array(
				'slug'        => $this->slug,
				'plugin'      => $this->basename,
				'new_version' => $new_version,
				'url'         => sprintf( 'https://github.com/%s/%s', $this->user, $this->repo ),
				'package'     => $this->download_url( $release ),
				'tested'      => '7.0',
				'requires'    => '6.0',
				'requires_php'=> '7.4',
				'icons'       => array(
					'1x'      => VELOX_URL . 'assets/icon-128x128.png',
					'2x'      => VELOX_URL . 'assets/icon-256x256.png',
					'default' => VELOX_URL . 'assets/icon-256x256.png',
				),
			);
		} else {
			$transient->no_update[ $this->basename ] = (object) array(
				'slug'        => $this->slug,
				'plugin'      => $this->basename,
				'new_version' => $current,
				'url'         => sprintf( 'https://github.com/%s/%s', $this->user, $this->repo ),
				'package'     => '',
			);
		}
		return $transient;
	}

	public function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || $args->slug !== $this->slug ) {
			return $result;
		}
		$release = $this->get_latest_release();
		if ( ! $release ) {
			return $result;
		}
		return (object) array(
			'name'          => 'Velox',
			'slug'          => $this->slug,
			'version'       => $this->normalize_version( $release->tag_name ),
			'author'        => '<a href="https://www.sumasearch.de/">Sumasearch</a>',
			'author_profile'=> 'https://www.sumasearch.de/',
			'homepage'      => sprintf( 'https://github.com/%s/%s', $this->user, $this->repo ),
			'download_link' => $this->download_url( $release ),
			'requires'      => '6.0',
			'tested'        => '7.0',
			'requires_php'  => '7.4',
			'last_updated'  => isset( $release->published_at ) ? $release->published_at : '',
			'banners'       => array(
				'low'  => VELOX_URL . 'assets/banner-772x250.png',
				'high' => VELOX_URL . 'assets/banner-1544x500.png',
			),
			'icons'         => array(
				'1x'      => VELOX_URL . 'assets/icon-128x128.png',
				'2x'      => VELOX_URL . 'assets/icon-256x256.png',
				'default' => VELOX_URL . 'assets/icon-256x256.png',
			),
			'sections'      => array(
				'description'  => $this->section_description(),
				'installation' => $this->section_installation(),
				'changelog'    => $this->format_changelog( $release ),
				'faq'          => $this->section_faq(),
			),
		);
	}

	private function section_description() {
		return '<p>Hey — I\'m the developer behind Velox. I built it because every "all-in-one" speed plugin I tried either fought with WP Fastest Cache, broke Oxygen, or buried me in 200 settings I didn\'t understand. So I made the one I actually wanted: it sits <em>on top</em> of the stack we already run (Oxygen + WP Fastest Cache + Cloudflare) and only adds the things those tools don\'t already do.</p>'
			. '<p>It never page-caches and never combines your CSS/JS — that\'s your cache plugin\'s job, and combining breaks Oxygen anyway. Everything risky is off until you flip a single "Risky mode" switch, so you can\'t accidentally nuke your own site.</p>'
			. '<h4>What\'s inside</h4><ul>'
			. '<li><strong>Speed:</strong> defer &amp; delay JavaScript, prioritise the hero image (LCP), YouTube facades, lazy-render, Speculation Rules, and a long list of WordPress bloat you can switch off.</li>'
			. '<li><strong>Smart CSS:</strong> async (non-blocking) delivery, critical CSS, and "Remove Unused CSS" with three engines — <em>Auto-learn</em> (zero setup, learns from your real visitors), <em>Cloudflare</em> (accurate from day one), or <em>Local</em>.</li>'
			. '<li><strong>Fonts:</strong> pull your Google Fonts local in one click.</li>'
			. '<li><strong>Images:</strong> bulk WebP conversion with live before/after savings, EXIF stripping and max-width downscaling.</li>'
			. '<li><strong>Media:</strong> edit alt text and titles in bulk, rename files safely.</li>'
			. '<li><strong>Database:</strong> clear the junk, optimise the tables.</li>'
			. '<li><strong>Per-page control:</strong> switch any optimisation off on a single page when something acts up.</li>'
			. '<li><strong>Builder-aware setup:</strong> Velox detects your page builder (Oxygen, Bricks, Elementor, Divi, Beaver Builder, WPBakery, Gutenberg or none) and auto-configures the right exclusions and safelists so it speeds the site up without breaking the builder.</li>'
			. '</ul>'
			. '<p>Not sure where to start? Hit <strong>Settings → Quick setup → Safe defaults</strong> and you\'re good.</p>';
	}

	private function section_installation() {
		return '<ol>'
			. '<li>Upload the zip under <em>Plugins → Add New → Upload Plugin</em>. Updating? Choose "Replace current with uploaded" — your settings are kept.</li>'
			. '<li>Activate it. Every module is on by default; nothing aggressive runs until you say so.</li>'
			. '<li>Open <strong>Velox</strong> in the admin sidebar. Go to <em>Settings → Quick setup</em> and apply Safe defaults.</li>'
			. '<li>Want more? Flip <em>Risky mode</em> in Performance, or apply the Aggressive preset, then test your site and exclude anything that misbehaves.</li>'
			. '<li>Updates come straight from GitHub — no wp.org needed. When a new version drops you\'ll see the usual "update available" notice.</li>'
			. '</ol>';
	}

	private function section_faq() {
		$faq = array(
			'Does it work with my page builder?' =>
				'Yes — on first run a quick wizard detects your builder (Oxygen, Bricks, Elementor, Divi, Beaver Builder, WPBakery, Gutenberg, or none) and configures the right JS exclusions and unused-CSS safelist for it, so it speeds things up without breaking the builder. Switch builders later and it reconfigures itself. Using something exotic? There\'s a one-click "request my builder" button.',
			'Will it clash with WP Fastest Cache?' =>
				'No. Velox deliberately doesn\'t do page caching or CSS/JS combining — that\'s WPFC\'s job. They\'re built to run together.',
			'Do I need Cloudflare or an API key?' =>
				'Nope. The default CSS engine (Auto-learn) needs zero setup — no account, no token. Cloudflare is just an optional alternative engine if you want instant accuracy on a low-traffic site.',
			'What is Risky mode?' =>
				'A single switch in Performance. Off, you only see settings that can\'t break a site. On, it reveals the aggressive stuff (JS delay, unused-CSS removal, etc.) that\'s worth testing first.',
			'How does the Auto-learn CSS engine work?' =>
				'It watches which CSS your pages actually use — measured in your real visitors\' browsers, so it even catches classes added by JavaScript — and trims the rest. Important bit: it can only ever keep <em>more</em> CSS, never less, so it can\'t break your layout.',
			'Something looks broken on one page. What now?' =>
				'Open that page in the editor and use the Velox box in the sidebar to switch off JS, CSS or lazy-load just for that page. The rest of your site stays optimised.',
			'Is it safe on Oxygen?' =>
				'Yes — it was built on an Oxygen stack. It won\'t combine CSS/JS (which breaks Oxygen) and it leaves jQuery Migrate alone by default, since Oxygen leans on it.',
			'Does it optimise images automatically?' =>
				'It converts to WebP in bulk from the Images tab and keeps your originals. New uploads can auto-convert if you turn that on. You stay in control.',
			'Where are my settings stored, and what happens if I delete the plugin?' =>
				'Everything lives in one tidy option. Delete the plugin and Velox cleans up after itself — its settings, learned data and cache folders all go. Your media and WebP files stay exactly where they are.',
		);
		$out = '';
		foreach ( $faq as $q => $a ) {
			$out .= '<h4>' . esc_html( $q ) . '</h4><p>' . $a . '</p>';
		}
		return $out;
	}

	/** Render the GitHub release notes (lightweight Markdown) for the changelog tab. */
	private function format_changelog( $release ) {
		$body = isset( $release->body ) ? (string) $release->body : '';
		if ( '' === trim( $body ) ) {
			return '<p>See the <a href="' . esc_url( sprintf( 'https://github.com/%s/%s/releases', $this->user, $this->repo ) ) . '">GitHub releases page</a> for the full history.</p>';
		}
		$out = esc_html( $body );
		$out = preg_replace( '/^#{1,6}\s*(.+)$/m', '<h4>$1</h4>', $out );          // headings
		$out = preg_replace( '/\*\*(.+?)\*\*/', '<strong>$1</strong>', $out );      // bold
		$out = preg_replace( '/`([^`]+)`/', '<code>$1</code>', $out );              // inline code
		$out = preg_replace( '/^\s*[\*\-]\s+(.+)$/m', '<li>$1</li>', $out );        // bullets
		$out = preg_replace( '#(?:<li>.*?</li>\s*)+#s', '<ul>$0</ul>', $out );      // wrap bullets
		$out = preg_replace( '#\n{2,}#', '</p><p>', $out );                         // paragraphs
		$out = preg_replace( '#(?<!>)\n(?!<)#', '<br>', $out );                     // soft breaks
		return '<p>' . $out . '</p>';
	}

	/**
	 * GitHub zipballs unpack to a folder like "JustKyrix-velox-a1b2c3". Rename it
	 * back to the plugin slug so the update lands in the right place.
	 */
	public function fix_folder_name( $source, $remote_source, $upgrader, $hook_extra = array() ) {
		if ( empty( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->basename ) {
			return $source;
		}
		global $wp_filesystem;
		$desired = trailingslashit( $remote_source ) . $this->slug;
		if ( $source === trailingslashit( $remote_source ) . $this->slug . '/' ) {
			return $source;
		}
		if ( $wp_filesystem && $wp_filesystem->move( $source, $desired ) ) {
			return trailingslashit( $desired );
		}
		return $source;
	}
}
