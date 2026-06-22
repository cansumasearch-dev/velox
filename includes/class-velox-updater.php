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

	private function get_latest_release() {
		$cache_key = 'velox_latest_release';
		$cached    = get_transient( $cache_key );
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
		$release = $this->get_latest_release();
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
			'author'        => 'Kyrix',
			'homepage'      => sprintf( 'https://github.com/%s/%s', $this->user, $this->repo ),
			'download_link' => $this->download_url( $release ),
			'sections'      => array(
				'description' => 'All-in-one performance + WebP + media toolkit for the Oxygen / WP Fastest Cache / Cloudflare stack.',
				'changelog'   => nl2br( esc_html( $release->body ?? 'See GitHub releases.' ) ),
			),
		);
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
