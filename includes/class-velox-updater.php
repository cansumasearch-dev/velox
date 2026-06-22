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
			'tested'        => '6.8',
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
		return '<p><strong>Velox</strong> is an all-in-one performance, image and media toolkit built for the Oxygen + WP Fastest Cache + Cloudflare stack. It complements your cache and CDN rather than fighting them — no second page cache, no CSS/JS combine.</p>'
			. '<h4>What it does</h4><ul>'
			. '<li><strong>Performance:</strong> defer &amp; delay JavaScript, fetchpriority on the LCP image, YouTube facades, lazy-render, Speculation Rules, plus a one-switch <em>Risky mode</em> that hides anything that might break a site.</li>'
			. '<li><strong>Strong CSS:</strong> non-render-blocking CSS delivery, critical CSS, and <em>Remove Unused CSS</em> with three engines — Auto-learn (zero-setup, learns from your visitors), Cloudflare Browser Run, or Local.</li>'
			. '<li><strong>Fonts:</strong> host Google Fonts locally in one click.</li>'
			. '<li><strong>Images:</strong> bulk WebP conversion with live before/after savings, EXIF stripping and max-width downscaling.</li>'
			. '<li><strong>Media editor:</strong> bulk alt text, titles and safe file renames.</li>'
			. '<li><strong>Database:</strong> cleanup and table optimization.</li>'
			. '</ul>';
	}

	private function section_installation() {
		return '<ol>'
			. '<li>Upload the plugin zip via <em>Plugins → Add New → Upload</em> (choose "Replace current with uploaded" when updating).</li>'
			. '<li>Activate it. All modules are on by default.</li>'
			. '<li>Open <em>Velox</em> in the admin menu and configure each module.</li>'
			. '<li>Updates arrive automatically from GitHub — no wp.org listing needed.</li>'
			. '</ol>';
	}

	private function section_faq() {
		return '<h4>Does it conflict with WP Fastest Cache?</h4>'
			. '<p>No — Velox never does page caching or CSS/JS combine. It only adds the front-end optimizations WPFC doesn\'t.</p>'
			. '<h4>What is Risky mode?</h4>'
			. '<p>By default only 100%-safe settings show. Turn on Risky mode to reveal aggressive options (delay-JS, unused-CSS removal, etc.) that might need testing.</p>'
			. '<h4>What is the Auto-learn CSS engine?</h4>'
			. '<p>It learns which CSS your pages actually use from real visitors\' browsers — no setup, no API key — and trims the rest. It can only ever keep more CSS, never break a layout.</p>';
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
