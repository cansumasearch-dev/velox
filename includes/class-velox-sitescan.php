<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Velox Security scan — is this site hacked, what, how, and what to do.
 *
 * Built like Wordfence's scanner, around one rule: a finding has to be worth
 * acting on. Checks are ordered by how trustworthy their answer is:
 *
 *  1. CORE + PLUGIN INTEGRITY (strongest). WordPress.org publishes a checksum
 *     for every file of every core release and every wp.org plugin version. A
 *     file that doesn't match, or a PHP file the release never shipped, is a
 *     yes/no answer — no guessing, almost no false alarms — and it can be put
 *     right by restoring the verified original.
 *
 *  2. CODE PATTERNS, deliberately narrow. Only files nobody can verify (themes,
 *     premium plugins like Oxygen, mu-plugins, root files) are read, and only
 *     for constructs real backdoors use and ordinary code practically never
 *     does: eval() of decoded strings, code executed straight from a request,
 *     known web-shell names, hex-encoded includes, PHP hidden in .ico files.
 *     Broad heuristics ("minified = suspicious") are left out on purpose: they
 *     flood the report with alarms nobody can act on.
 *
 *  3. DATABASE, .HTACCESS, ACCOUNTS. Where the common SEO-spam and redirect
 *     hacks actually live: injected scripts / hidden iframes in posts, site URL
 *     hijacks, payloads in options, Google/mobile-only redirects, handlers that
 *     make images run as code, and administrator accounts nobody remembers.
 *
 *  4. HARDENING TIPS. Not hacks — the doors hacks come through (outdated
 *     plugins above all). Shown separately so they never read as an infection.
 *
 * The screen says plainly what no scanner running inside a site can promise:
 * a clean result is strong evidence, not proof (server-level malware, or a
 * scanner the attacker has tampered with, can hide).
 *
 * Fixing lives in Velox_Site_Scan_Fix. Every action is addressed by the index
 * of a finding stored here — never by a path sent from the browser.
 *
 * @package Velox
 */
class Velox_Site_Scan {

	const STATE    = 'velox_scan_state';
	const RESULT   = 'velox_scan_result';
	const MANIFEST = 'velox_scan_manifest';
	const FILES    = 'velox_scan_files';
	const CRON     = 'velox_scan_cron';
	const CONTINUE_HOOK = 'velox_scan_continue';

	/** Core files hashed per step. */
	const BATCH = 250;

	/** Files read for code patterns per step (also capped by time). */
	const CODE_BATCH = 300;

	/** Biggest file read for code patterns. */
	const MAX_READ = 3145728; // 3 MB

	/** Findings kept in the report. Anything past this is counted, never silently dropped. */
	const MAX_FINDINGS = 500;

	/** Files visited under uploads before the walk gives up and says so. */
	const MAX_UPLOAD_FILES = 200000;

	/** An administrator account newer than this is pointed out. */
	const NEW_ADMIN_DAYS = 14;

	/**
	 * Core ships these and hardening guides routinely tell people to delete them.
	 * Missing is expected, not a finding worth alarming anyone about.
	 */
	private static $optional = array( 'readme.html', 'license.txt', 'wp-config-sample.php' );

	/* ----------------------------------------------------------------- hooks */

	public static function init() {
		add_action( self::CRON, array( __CLASS__, 'cron_start' ) );
		add_action( self::CONTINUE_HOOK, array( __CLASS__, 'cron_continue' ) );
		$sched = (string) Velox_Settings::get( 'scan_schedule', 'weekly' );
		$on    = Velox_Settings::get( 'util_sitescan', false ) && in_array( $sched, array( 'daily', 'weekly' ), true );
		$next  = wp_next_scheduled( self::CRON );
		if ( ! $on ) {
			if ( $next ) {
				wp_clear_scheduled_hook( self::CRON );
			}
			return;
		}
		$want = 'daily' === $sched ? 'daily' : 'weekly';
		if ( $next && wp_get_schedule( self::CRON ) !== $want ) {
			wp_clear_scheduled_hook( self::CRON );
			$next = false;
		}
		if ( ! $next ) {
			// Small hours of the night, site time, so the scan never competes with visitors.
			wp_schedule_event( self::next_night(), $want, self::CRON );
		}
	}

	private static function next_night() {
		$tz  = wp_timezone();
		$now = new DateTime( 'now', $tz );
		$at  = new DateTime( 'tomorrow 03:00', $tz );
		return $at > $now ? $at->getTimestamp() : time() + DAY_IN_SECONDS;
	}

	/* --------------------------------------------------------------- phases */

	private static function phases() {
		return array(
			'manifest'  => array( 'label' => __( 'Fetching the checksums for your version', 'velox' ), 'percent' => 3 ),
			'core'      => array( 'label' => __( 'Checking WordPress core files', 'velox' ), 'percent' => 8 ),
			'extra'     => array( 'label' => __( 'Looking for files that do not belong', 'velox' ), 'percent' => 30 ),
			'plugins'   => array( 'label' => __( 'Checking plugins against WordPress.org', 'velox' ), 'percent' => 33 ),
			'code'      => array( 'label' => __( 'Reading themes and other code for backdoors', 'velox' ), 'percent' => 55 ),
			'uploads'   => array( 'label' => __( 'Checking uploads for runnable code', 'velox' ), 'percent' => 85 ),
			'htaccess'  => array( 'label' => __( 'Checking server rules (.htaccess)', 'velox' ), 'percent' => 90 ),
			'database'  => array( 'label' => __( 'Checking posts, settings and accounts', 'velox' ), 'percent' => 93 ),
			'hardening' => array( 'label' => __( 'Looking for open doors', 'velox' ), 'percent' => 98 ),
		);
	}

	/* ---------------------------------------------------------------- start */

	public static function start( $source = 'manual' ) {
		delete_option( self::MANIFEST );
		delete_option( self::FILES );
		$state = array(
			'phase'      => 'manifest',
			'cursor'     => 0,
			'started'    => time(),
			'source'     => $source,
			'total'      => 0,
			'findings'   => array(),
			'extra'      => 0,
			'notes'      => array(),
			'counts'     => array( 'core' => 0, 'plugins' => 0, 'plugin_files' => 0, 'code' => 0, 'uploads' => 0, 'posts' => 0 ),
			'version'    => get_bloginfo( 'version' ),
			'locale'     => '',
			'verified'   => array(), // plugin folders proven clean-or-reported by checksums
			'unverified' => array(), // plugins WordPress.org has no checksums for
			'big'        => 0,
		);
		update_option( self::STATE, $state, false );
		return self::progress( $state );
	}

	public static function step() {
		$state = get_option( self::STATE );
		if ( ! is_array( $state ) || empty( $state['phase'] ) ) {
			return self::start();
		}

		switch ( $state['phase'] ) {
			case 'manifest':
				$more = self::do_manifest( $state );
				break;
			case 'core':
				$more = self::do_core( $state );
				break;
			case 'extra':
				$more = self::do_extra( $state );
				break;
			case 'plugins':
				$more = self::do_plugins( $state );
				break;
			case 'code':
				$more = self::do_code( $state );
				break;
			case 'uploads':
				$more = self::do_uploads( $state );
				break;
			case 'htaccess':
				$more = self::do_htaccess( $state );
				break;
			case 'database':
				$more = self::do_database( $state );
				break;
			case 'hardening':
				$more = self::do_hardening( $state );
				break;
			default:
				$more = false;
		}

		if ( ! $more ) {
			$keys  = array_keys( self::phases() );
			$idx   = array_search( $state['phase'], $keys, true );
			$state['phase']  = ( false !== $idx && isset( $keys[ $idx + 1 ] ) ) ? $keys[ $idx + 1 ] : '';
			$state['cursor'] = 0;
		}

		update_option( self::STATE, $state, false );

		if ( '' === $state['phase'] ) {
			return self::finish( $state );
		}
		return self::progress( $state );
	}

	/* ------------------------------------------------------------- manifest */

	/**
	 * The published checksums for this exact version and locale. A localised
	 * build has its own list, so asking for the site's locale first and only
	 * then falling back to en_US avoids reporting every file as altered on a
	 * German install.
	 */
	private static function do_manifest( &$state ) {
		$version = (string) get_bloginfo( 'version' );
		$locale  = get_locale();

		$list = self::fetch_checksums( $version, $locale );
		if ( ! $list && 'en_US' !== $locale ) {
			$list = self::fetch_checksums( $version, 'en_US' );
			if ( $list ) {
				$locale = 'en_US';
				$state['notes'][] = sprintf(
					/* translators: %s: locale code, e.g. de_DE */
					__( 'WordPress.org publishes no checksums for %s, so the English list was used. Translation files are not covered by it.', 'velox' ),
					get_locale()
				);
			}
		}

		if ( ! $list ) {
			$state['notes'][] = __( 'The checksums for your WordPress version could not be fetched, so core files were not verified. This is usually a blocked outbound connection rather than a problem with the site.', 'velox' );
			$state['phase']   = 'plugins'; // Nothing to compare core against.
			$state['cursor']  = 0;
			return true;
		}

		// wp-content is never in the published list — themes and plugins are the
		// site's own. Dropping those entries keeps "missing" honest.
		foreach ( array_keys( $list ) as $rel ) {
			if ( 0 === strpos( $rel, 'wp-content/' ) ) {
				unset( $list[ $rel ] );
			}
		}

		update_option( self::MANIFEST, $list, false );
		$state['locale'] = $locale;
		$state['total']  = count( $list );
		return false;
	}

	public static function fetch_checksums( $version, $locale ) {
		$res = wp_remote_get(
			add_query_arg(
				array( 'version' => $version, 'locale' => $locale ),
				'https://api.wordpress.org/core/checksums/1.0/'
			),
			array( 'timeout' => 20 )
		);
		if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
			return array();
		}
		$body = json_decode( wp_remote_retrieve_body( $res ), true );
		$sums = isset( $body['checksums'] ) ? $body['checksums'] : null;
		return is_array( $sums ) ? $sums : array();
	}

	/* ----------------------------------------------------------------- core */

	private static function do_core( &$state ) {
		$list = get_option( self::MANIFEST );
		if ( ! is_array( $list ) || ! $list ) {
			return false;
		}
		$paths  = array_keys( $list );
		$cursor = (int) $state['cursor'];
		$slice  = array_slice( $paths, $cursor, self::BATCH );
		if ( ! $slice ) {
			return false;
		}

		foreach ( $slice as $rel ) {
			$abs = ABSPATH . $rel;
			if ( ! file_exists( $abs ) ) {
				if ( in_array( $rel, self::$optional, true ) ) {
					continue; // Routinely deleted on purpose.
				}
				self::add( $state, 'warn', 'core_missing', $rel, __( 'A core file that should be here is gone.', 'velox' ), array( 'fix' => 'restore_core', 'md5' => (string) $list[ $rel ] ) );
				continue;
			}
			if ( ! is_readable( $abs ) ) {
				self::add( $state, 'info', 'unreadable', $rel, __( 'Could not be read, so it was not checked.', 'velox' ) );
				continue;
			}
			$state['counts']['core']++;
			if ( ! hash_equals( (string) $list[ $rel ], (string) md5_file( $abs ) ) ) {
				self::add( $state, 'danger', 'core_modified', $rel, __( 'Does not match the file WordPress published for this version.', 'velox' ), array( 'fix' => 'restore_core', 'md5' => (string) $list[ $rel ] ) + self::first_hit( $abs ) );
			}
		}

		$state['cursor'] = $cursor + count( $slice );
		return $state['cursor'] < count( $paths );
	}

	/* ---------------------------------------------------------------- extra */

	/**
	 * Files sitting in wp-admin or wp-includes that the published list does not
	 * mention. Those two folders belong entirely to WordPress, so anything else
	 * in them arrived from somewhere. The site root is checked by the code phase
	 * instead — wp-config.php, .htaccess and a hundred legitimate odds and ends
	 * live there.
	 */
	private static function do_extra( &$state ) {
		$list = get_option( self::MANIFEST );
		if ( ! is_array( $list ) || ! $list ) {
			return false;
		}
		foreach ( array( 'wp-admin', 'wp-includes' ) as $dir ) {
			$base = ABSPATH . $dir;
			if ( ! is_dir( $base ) ) {
				continue;
			}
			foreach ( self::walk( $base ) as $abs ) {
				$rel = $dir . '/' . ltrim( str_replace( '\\', '/', substr( $abs, strlen( $base ) ) ), '/' );
				if ( isset( $list[ $rel ] ) ) {
					continue;
				}
				$exec = self::is_executable_name( basename( $rel ) );
				self::add(
					$state,
					$exec ? 'danger' : 'warn',
					'core_unexpected',
					$rel,
					__( 'Not part of WordPress. This folder should hold nothing else.', 'velox' ),
					array( 'fix' => 'quarantine' ) + ( $exec ? self::first_hit( $abs ) : array() )
				);
			}
		}
		return false;
	}

	/* -------------------------------------------------------------- plugins */

	/**
	 * One plugin per step: fetch WordPress.org's checksums for the installed
	 * version, compare every file, and report PHP files the release never had.
	 * Plugins that aren't on WordPress.org (Oxygen, premium plugins) have no
	 * checksums; their code is read for backdoor patterns in the next phase.
	 */
	private static function do_plugins( &$state ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$all  = get_plugins();
		$keys = array_keys( $all );
		$i    = (int) $state['cursor'];
		if ( $i >= count( $keys ) ) {
			return false;
		}
		$file  = $keys[ $i ];
		$data  = $all[ $file ];
		$state['cursor'] = $i + 1;
		$slug  = dirname( $file );
		if ( '.' === $slug ) {
			return $state['cursor'] < count( $keys ); // single-file plugin: read by the code phase
		}
		$state['counts']['plugins']++;
		$sums = self::plugin_checksums( $slug, (string) $data['Version'] );
		if ( ! $sums ) {
			$state['unverified'][] = (string) $data['Name'];
			return $state['cursor'] < count( $keys );
		}

		$base = WP_PLUGIN_DIR . '/' . $slug;
		foreach ( $sums as $rel => $info ) {
			$abs = $base . '/' . $rel;
			if ( ! is_file( $abs ) ) {
				continue; // a missing plugin file breaks the plugin, it doesn't hack the site
			}
			$state['counts']['plugin_files']++;
			$want = isset( $info['md5'] ) ? (array) $info['md5'] : array();
			if ( $want && ! in_array( md5_file( $abs ), $want, true ) ) {
				self::add(
					$state,
					self::is_executable_name( $rel ) ? 'danger' : 'warn',
					'plugin_modified',
					'wp-content/plugins/' . $slug . '/' . $rel,
					sprintf(
						/* translators: 1: plugin name 2: version */
						__( 'Differs from %1$s %2$s as published on WordPress.org.', 'velox' ),
						$data['Name'],
						$data['Version']
					),
					array( 'fix' => 'restore_plugin', 'slug' => $slug, 'version' => (string) $data['Version'], 'rel' => $rel, 'md5' => $want ) + self::first_hit( $abs )
				);
			}
		}
		foreach ( self::walk( $base ) as $abs ) {
			$rel = ltrim( str_replace( '\\', '/', substr( $abs, strlen( $base ) ) ), '/' );
			if ( isset( $sums[ $rel ] ) || ! self::is_executable_name( $rel ) ) {
				continue;
			}
			self::add(
				$state,
				'danger',
				'plugin_unexpected',
				'wp-content/plugins/' . $slug . '/' . $rel,
				sprintf(
					/* translators: %s: plugin name */
					__( 'A PHP file that %s never shipped with.', 'velox' ),
					$data['Name']
				),
				array( 'fix' => 'quarantine' ) + self::first_hit( $abs )
			);
		}
		$state['verified'][] = $slug;
		return $state['cursor'] < count( $keys );
	}

	/** WordPress.org's per-file checksums for a plugin version, or array() if it has none. */
	public static function plugin_checksums( $slug, $version ) {
		$res = wp_remote_get(
			'https://downloads.wordpress.org/plugin-checksums/' . rawurlencode( $slug ) . '/' . rawurlencode( $version ) . '.json',
			array( 'timeout' => 20 )
		);
		if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
			return array();
		}
		$body = json_decode( wp_remote_retrieve_body( $res ), true );
		return ( isset( $body['files'] ) && is_array( $body['files'] ) ) ? $body['files'] : array();
	}

	/* ----------------------------------------------------------------- code */

	/**
	 * Code nobody can verify: themes, plugins without checksums, mu-plugins,
	 * drop-ins and the site root. The file list is built once, then read in
	 * batches so a large site never hits a time limit.
	 */
	private static function do_code( &$state ) {
		$files = get_option( self::FILES );
		if ( ! is_array( $files ) ) {
			$files = self::code_files( $state['verified'] );
			update_option( self::FILES, $files, false );
		}
		$cursor = (int) $state['cursor'];
		$t0     = microtime( true );
		$n      = count( $files );
		while ( $cursor < $n && $cursor - (int) $state['cursor'] < self::CODE_BATCH && microtime( true ) - $t0 < 8 ) {
			$rel = $files[ $cursor++ ];
			$abs = ABSPATH . $rel;
			$size = @filesize( $abs ); // phpcs:ignore
			if ( false === $size ) {
				continue;
			}
			if ( $size > self::MAX_READ ) {
				$state['big']++;
				continue;
			}
			$state['counts']['code']++;
			$hit = self::match_file( $abs );
			if ( $hit ) {
				self::add( $state, $hit['level'], 'code_suspicious', $rel, $hit['why'], array( 'fix' => 'quarantine', 'line' => $hit['line'], 'snippet' => $hit['snippet'], 'rule' => $hit['rule'], 'hits' => $hit['hits'] ) );
			}
		}
		$state['cursor'] = $cursor;
		if ( $cursor >= $n ) {
			delete_option( self::FILES );
			if ( $state['big'] ) {
				$state['notes'][] = sprintf(
					/* translators: %s: number of files */
					__( '%s very large code files (over 3 MB) were skipped.', 'velox' ),
					number_format_i18n( $state['big'] )
				);
			}
			return false;
		}
		return true;
	}

	/** Relative paths of the code files to read. */
	private static function code_files( array $verified_plugins ) {
		$out  = array();
		$root = wp_normalize_path( ABSPATH );
		$skip = array( wp_normalize_path( __FILE__ ) ); // holds the signatures themselves
		$add  = function ( $abs ) use ( &$out, $root, $skip ) {
			$n = wp_normalize_path( $abs );
			if ( in_array( $n, $skip, true ) || 0 !== strpos( $n, $root ) ) {
				return;
			}
			$out[] = substr( $n, strlen( $root ) );
		};
		// Site root (not recursive): index.php, wp-config.php, stray files.
		foreach ( (array) glob( ABSPATH . '*' ) as $f ) {
			if ( is_file( $f ) && self::is_code_name( basename( $f ) ) ) {
				$add( $f );
			}
		}
		$content = wp_normalize_path( WP_CONTENT_DIR );
		$plugins = wp_normalize_path( WP_PLUGIN_DIR );
		$up      = wp_upload_dir();
		$uploads = wp_normalize_path( $up['basedir'] );
		foreach ( self::walk( WP_CONTENT_DIR ) as $abs ) {
			$n = wp_normalize_path( $abs );
			if ( 0 === strpos( $n, $uploads . '/' ) || 0 === strpos( $n, $content . '/cache/' ) || 0 === strpos( $n, $content . '/upgrade/' ) ) {
				continue; // uploads has its own phase; caches are rebuilt output
			}
			if ( 0 === strpos( $n, $plugins . '/' ) ) {
				$slug = strtok( substr( $n, strlen( $plugins ) + 1 ), '/' );
				if ( in_array( $slug, $verified_plugins, true ) ) {
					continue; // already verified file-by-file against WordPress.org
				}
			}
			if ( self::is_code_name( basename( $n ) ) ) {
				$add( $abs );
			}
		}
		return $out;
	}

	/** PHP, plus .ico — a favourite hiding place for included PHP. */
	private static function is_code_name( $name ) {
		return self::is_executable_name( $name ) || (bool) preg_match( '/\.(inc|ico)$/i', $name );
	}

	/**
	 * The backdoor patterns. Each must be something real malware does and
	 * ordinary code (WordPress, Oxygen, the big plugins) practically never
	 * does. 'danger' alone is enough; two different 'warn' rules in one file
	 * make it a finding; a single 'warn' is ignored as too weak on its own.
	 */
	private static function signatures() {
		return array(
			'eval_decode' => array(
				'level' => 'danger',
				're'    => '/\b(?:eval|assert)\s*\(\s*(?:@\s*)?(?:base64_decode|gzinflate|gzuncompress|gzdecode|str_rot13|strrev|rawurldecode|hex2bin|convert_uudecode)\s*\(/i',
				'why'   => __( 'Runs code that is hidden by encoding (eval of decoded text). This is how almost every PHP backdoor disguises itself.', 'velox' ),
			),
			'request_exec' => array(
				'level' => 'danger',
				're'    => '/\b(?:eval|assert|system|exec|shell_exec|passthru|popen|proc_open|create_function)\s*\(\s*(?:@\s*)?(?:stripslashes\s*\(\s*)?\$_(?:GET|POST|REQUEST|COOKIE|SERVER|FILES)\b/i',
				'why'   => __( 'Runs whatever a visitor sends in the address or a form. That lets anyone on the internet run commands on your server.', 'velox' ),
			),
			'request_call' => array(
				'level' => 'danger',
				're'    => '/@?\$_(?:GET|POST|REQUEST|COOKIE)\s*\[[^\]]{1,40}\]\s*\(\s*@?\$_(?:GET|POST|REQUEST|COOKIE)/i',
				'why'   => __( 'Calls a function whose name AND input both come from the visitor. A classic one-line backdoor.', 'velox' ),
			),
			'preg_e' => array(
				'level' => 'danger',
				're'    => '/preg_replace\s*\(\s*([\'"])(.).{0,200}?\2[imsxuADU]*e[imsxuADU]*\1\s*,/s',
				'why'   => __( 'Uses the old "/e" trick to make a text replacement run code — only ever used to hide a backdoor.', 'velox' ),
			),
			'webshell' => array(
				'level' => 'danger',
				're'    => '/\b(?:FilesMan|WSO\s?[0-9]\.[0-9]|c99shell|c99_buff|r57shell|b374k|IndoXploit|AnonymousFox|Alfa\s?Shell|ALFA_DATA|0byt3m1n1|Marion001)\b/i',
				'why'   => __( 'Contains the signature of a known web shell — a full remote-control panel for attackers.', 'velox' ),
			),
			'hex_include' => array(
				'level' => 'danger',
				're'    => '/\b(?:include|require)(?:_once)?\s*\(?\s*[\'"](?:\\\\x[0-9a-f]{2}){4,}/i',
				'why'   => __( 'Loads another file whose name is written in hidden hex code, so nobody reading the file can see what it loads.', 'velox' ),
			),
			'upload_backdoor' => array(
				'level' => 'danger',
				're'    => '/move_uploaded_file\s*\(\s*\$_FILES\s*\[[^\]]+\]\s*\[\s*[\'"]tmp_name[\'"]\s*\]\s*,\s*\$_(?:GET|POST|REQUEST)/i',
				'why'   => __( 'Lets a visitor upload a file to any place on the server they name — how attackers install more malware.', 'velox' ),
			),
			'globals_hex' => array(
				'level' => 'warn',
				're'    => '/\$\{\s*[\'"]\\\\x47\\\\x4c/i',
				'why'   => __( 'Hides variable names behind hex codes.', 'velox' ),
			),
			'chr_chain' => array(
				'level' => 'warn',
				're'    => '/(?:chr\s*\(\s*\d{2,3}\s*\)\s*\.\s*){12,}/i',
				'why'   => __( 'Builds text out of a long chain of character codes — a way to hide words like "eval".', 'velox' ),
			),
			'long_blob' => array(
				'level' => 'warn',
				're'    => '/[A-Za-z0-9+\/]{3000,}={0,2}/',
				'why'   => __( 'Holds a very long block of encoded data.', 'velox' ),
			),
			'str_obfuscate' => array(
				'level' => 'warn',
				're'    => '/\$\w+\s*=\s*str_replace\s*\(\s*[\'"][^\'"]{1,5}[\'"]\s*,\s*[\'"][\'"]\s*,\s*[\'"][a-z]{1,4}[^\'"]{0,6}(?:se|ev|as)/i',
				'why'   => __( 'Assembles a function name by stripping junk characters out of a string — a common way to hide "base64_decode" or "eval".', 'velox' ),
			),
			'error_silence' => array(
				'level' => 'warn',
				're'    => '/error_reporting\s*\(\s*0\s*\)\s*;\s*@?(?:ini_set\s*\(\s*[\'"]display_errors|set_time_limit\s*\(\s*0)/i',
				'why'   => __( 'Switches off all error messages and time limits right at the start — typical of shells that must not be noticed.', 'velox' ),
			),
			'request_var_func' => array(
				'level' => 'warn',
				're'    => '/\$_(?:GET|POST|REQUEST|COOKIE)\s*\[[^\]]{1,40}\]\s*\(/i',
				'why'   => __( 'Calls a function named by the visitor.', 'velox' ),
			),
		);
	}

	/**
	 * Read one file and decide. Returns the strongest hit (with line + snippet)
	 * or null. .ico files only count when they carry PHP at all.
	 */
	public static function match_file( $abs ) {
		$src = (string) @file_get_contents( $abs ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( '' === $src ) {
			return null;
		}
		if ( preg_match( '/\.ico$/i', $abs ) ) {
			if ( false === stripos( $src, '<?php' ) && ! preg_match( '/\b(?:eval|base64_decode|gzinflate)\s*\(/i', $src ) ) {
				return null;
			}
			return array(
				'level'   => 'danger',
				'rule'    => 'php_in_ico',
				'why'     => __( 'An icon file that contains PHP code. Hackers hide their code in .ico files and load it from elsewhere, because nobody opens icons.', 'velox' ),
				'line'    => 1,
				'snippet' => self::snippet( $src, max( 0, (int) stripos( $src, '<?php' ) ) ),
				'hits'    => 1,
			);
		}
		$best  = null;
		$warns = array();
		foreach ( self::signatures() as $id => $sig ) {
			if ( ! @preg_match( $sig['re'], $src, $m, PREG_OFFSET_CAPTURE ) ) { // phpcs:ignore
				continue;
			}
			$hit = array(
				'level'   => $sig['level'],
				'rule'    => $id,
				'why'     => $sig['why'],
				'line'    => substr_count( $src, "\n", 0, $m[0][1] ) + 1,
				'snippet' => self::snippet( $src, $m[0][1] ),
				'hits'    => 1,
			);
			if ( 'danger' === $sig['level'] ) {
				if ( ! $best ) {
					$best = $hit;
				} else {
					$best['hits']++;
				}
			} else {
				$warns[] = $hit;
			}
		}
		if ( $best ) {
			return $best;
		}
		if ( count( $warns ) >= 2 ) {
			$first        = $warns[0];
			$first['why'] = implode( ' ', array_unique( wp_list_pluck( $warns, 'why' ) ) );
			$first['hits'] = count( $warns );
			return $first;
		}
		return null;
	}

	/** The danger-level hit in a file we already flagged for another reason (for its "how" text). */
	private static function first_hit( $abs ) {
		if ( ! self::is_executable_name( $abs ) || (int) @filesize( $abs ) > self::MAX_READ ) { // phpcs:ignore
			return array();
		}
		$hit = self::match_file( $abs );
		return $hit ? array( 'line' => $hit['line'], 'snippet' => $hit['snippet'], 'rule' => $hit['rule'], 'how' => $hit['why'] ) : array();
	}

	/** ~160 printable characters around an offset, one line, for the report. */
	private static function snippet( $src, $offset ) {
		$start = max( 0, $offset - 40 );
		$s     = substr( $src, $start, 200 );
		$s     = preg_replace( '/[\x00-\x08\x0B-\x1F\x7F]/', '', (string) $s );
		$s     = preg_replace( '/\s+/', ' ', (string) $s );
		return ( $start > 0 ? '…' : '' ) . trim( mb_substr( (string) $s, 0, 160 ) ) . '…';
	}

	/* -------------------------------------------------------------- uploads */

	private static function do_uploads( &$state ) {
		$up   = wp_upload_dir();
		$base = isset( $up['basedir'] ) ? $up['basedir'] : '';
		if ( ! $base || ! is_dir( $base ) ) {
			$state['notes'][] = __( 'The uploads folder could not be located, so it was not checked.', 'velox' );
			return false;
		}

		$seen = 0;
		foreach ( self::walk( $base ) as $abs ) {
			if ( ++$seen > self::MAX_UPLOAD_FILES ) {
				$state['notes'][] = sprintf(
					/* translators: %s: number of files */
					__( 'Uploads holds more than %s files, so the check stopped there. Everything past that point was not looked at.', 'velox' ),
					number_format_i18n( self::MAX_UPLOAD_FILES )
				);
				break;
			}
			$state['counts']['uploads']++;
			$name = basename( $abs );
			$rel  = 'uploads/' . ltrim( str_replace( '\\', '/', substr( $abs, strlen( $base ) ) ), '/' );
			// Velox's own sealed folders (originals backup, quarantine) are not uploads content.
			if ( preg_match( '#^uploads/velox-(?:originals|quarantine)-[a-z0-9]+/#', $rel ) ) {
				continue;
			}

			if ( self::is_executable_name( $name ) ) {
				if ( self::is_inert( $abs, $name ) ) {
					continue;
				}
				self::add( $state, 'danger', 'upload_php', $rel, __( 'Runnable code in the media folder. Nothing legitimate puts it there.', 'velox' ), array( 'fix' => 'quarantine' ) + self::first_hit( $abs ) );
				continue;
			}
			// x.php.jpg and friends: harmless on a correct server, executed on a
			// misconfigured one, and never something a media library produces.
			if ( preg_match( '/\.(php\d?|phtml|phtm|pht|phar)\./i', $name ) ) {
				self::add( $state, 'warn', 'upload_double_ext', $rel, __( 'A double extension hiding a code file. Some server setups will run it.', 'velox' ), array( 'fix' => 'quarantine' ) );
				continue;
			}
			if ( '.htaccess' === $name ) {
				// Most of these deny access, which is the folder protecting itself
				// and the opposite of a problem — WooCommerce ships exactly that.
				// One that switches a handler on is the real thing to catch: it is
				// how an uploaded image gets executed as code.
				$rules = strtolower( (string) @file_get_contents( $abs ) );
				if ( preg_match( '/addhandler|addtype|sethandler|php_flag|php_value|execcgi|x-httpd-php/', $rules ) ) {
					self::add( $state, 'danger', 'upload_htaccess', $rel, __( 'Makes this folder run code. That is how an uploaded image becomes a back door.', 'velox' ), array( 'fix' => 'quarantine' ) );
				}
			}
		}
		return false;
	}

	/* ------------------------------------------------------------- htaccess */

	/**
	 * The redirect and handler tricks that live in .htaccess: send visitors who
	 * arrive from Google, or on a phone, to a spam site (the owner, typing the
	 * address directly on a laptop, never sees it); run images as PHP; prepend
	 * a malware file to every request.
	 */
	private static function do_htaccess( &$state ) {
		$own = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		foreach ( array( '.htaccess', 'wp-content/.htaccess', 'wp-includes/.htaccess', 'wp-admin/.htaccess' ) as $rel ) {
			$abs = ABSPATH . $rel;
			if ( ! is_readable( $abs ) ) {
				continue;
			}
			$txt   = (string) @file_get_contents( $abs ); // phpcs:ignore
			$lines = preg_split( '/\r\n|\r|\n/', $txt );
			$cond  = '';
			foreach ( $lines as $i => $line ) {
				$l = trim( $line );
				if ( '' === $l || '#' === $l[0] ) {
					continue;
				}
				if ( preg_match( '/^RewriteCond\s+%\{HTTP_(REFERER|USER_AGENT)\}\s+(\S+)/i', $l, $m ) && preg_match( '/google|bing|yahoo|yandex|baidu|duckduckgo|facebook|android|iphone|mobile|ipad|blackberry|opera\s?mini/i', $m[2] ) ) {
					$cond = $m[1];
					continue;
				}
				if ( preg_match( '/^RewriteRule\s+\S+\s+(https?:\/\/[^\s\[]+)/i', $l, $m ) ) {
					$host = strtolower( (string) wp_parse_url( $m[1], PHP_URL_HOST ) );
					// %{HTTP_HOST} etc. is this site (the normal "force https / www" rules).
					if ( $host && false === strpos( $host, '%{' ) && $host !== $own && substr( $host, -strlen( '.' . $own ) ) !== '.' . $own && '' !== $cond ) {
						self::add( $state, 'danger', 'htaccess_redirect', $rel, sprintf(
							/* translators: 1: line number 2: what the condition looks at 3: target host */
							__( 'Line %1$d sends visitors to %3$s, but only when their %2$s matches — so you never see it yourself.', 'velox' ),
							$i + 1,
							'REFERER' === strtoupper( $cond ) ? __( 'referring search engine', 'velox' ) : __( 'device', 'velox' ),
							$host
						), array( 'fix' => 'htaccess', 'line' => $i + 1, 'snippet' => mb_substr( $l, 0, 160 ) ) );
					}
				}
				// A RewriteCond only applies to the very next RewriteRule.
				if ( ! preg_match( '/^RewriteCond/i', $l ) ) {
					$cond = '';
				}
				if ( preg_match( '/^php_value\s+auto_(?:prepend|append)_file\s+(\S+)/i', $l ) ) {
					self::add( $state, 'danger', 'htaccess_prepend', $rel, sprintf(
						/* translators: %d: line number */
						__( 'Line %d makes the server run an extra file before every page — a way to load malware on every request.', 'velox' ),
						$i + 1
					), array( 'fix' => 'htaccess', 'line' => $i + 1, 'snippet' => mb_substr( $l, 0, 160 ) ) );
				}
				if ( preg_match( '/^(?:AddHandler|AddType|SetHandler)\b.*(?:php|x-httpd)/i', $l ) && preg_match( '/\.(?:jpe?g|png|gif|ico|txt|css|js|html?)\b/i', $l ) ) {
					self::add( $state, 'danger', 'htaccess_handler', $rel, sprintf(
						/* translators: %d: line number */
						__( 'Line %d tells the server to run image or text files as PHP. That is how a "picture" becomes a back door.', 'velox' ),
						$i + 1
					), array( 'fix' => 'htaccess', 'line' => $i + 1, 'snippet' => mb_substr( $l, 0, 160 ) ) );
				}
				if ( preg_match( '/^ErrorDocument\s+\d{3}\s+(https?:\/\/\S+)/i', $l, $m ) ) {
					$host = strtolower( (string) wp_parse_url( $m[1], PHP_URL_HOST ) );
					if ( $host && $host !== $own ) {
						self::add( $state, 'warn', 'htaccess_redirect', $rel, sprintf(
							/* translators: 1: line number 2: host */
							__( 'Line %1$d sends visitors who hit a missing page to %2$s.', 'velox' ),
							$i + 1,
							$host
						), array( 'fix' => 'htaccess', 'line' => $i + 1, 'snippet' => mb_substr( $l, 0, 160 ) ) );
					}
				}
			}
		}
		return false;
	}

	/* ------------------------------------------------------------- database */

	/**
	 * Injected scripts / hidden iframes in posts, a hijacked site address,
	 * encoded payloads in settings, and administrator accounts nobody expects.
	 */
	private static function do_database( &$state ) {
		global $wpdb;
		$own = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );

		// Posts.
		$rows = $wpdb->get_results(
			"SELECT ID, post_title, post_type, post_content FROM {$wpdb->posts}
			 WHERE post_type NOT IN ('revision','attachment','nav_menu_item','customize_changeset','oembed_cache')
			 AND post_status IN ('publish','future','draft','pending','private')
			 AND ( post_content LIKE '%<script%' OR post_content LIKE '%<iframe%' OR post_content LIKE '%fromCharCode%' OR post_content LIKE '%eval(%' )
			 LIMIT 2000"
		);
		foreach ( (array) $rows as $r ) {
			$state['counts']['posts']++;
			$bad = self::bad_markup( (string) $r->post_content, $own );
			if ( ! $bad ) {
				continue;
			}
			self::add(
				$state,
				$bad['level'],
				'db_script',
				sprintf( '%s #%d — %s', $r->post_type, $r->ID, wp_strip_all_tags( $r->post_title ) ),
				$bad['why'],
				array( 'fix' => 'danger' === $bad['level'] ? 'clean_post' : 'edit_post', 'post_id' => (int) $r->ID, 'snippet' => $bad['snippet'] )
			);
		}

		// Site address hijack.
		$siteurl = strtolower( (string) wp_parse_url( get_option( 'siteurl' ), PHP_URL_HOST ) );
		$home    = strtolower( (string) wp_parse_url( get_option( 'home' ), PHP_URL_HOST ) );
		if ( $siteurl && $home && $siteurl !== $home && substr( $siteurl, -strlen( '.' . $home ) ) !== '.' . $home && substr( $home, -strlen( '.' . $siteurl ) ) !== '.' . $siteurl ) {
			self::add( $state, 'danger', 'db_siteurl', 'Settings → General', sprintf(
				/* translators: 1: WordPress address host 2: site address host */
				__( 'The WordPress address (%1$s) and the site address (%2$s) point at different domains. Hackers change one of them to send your visitors elsewhere.', 'velox' ),
				$siteurl,
				$home
			), array( 'fix' => 'link', 'url' => admin_url( 'options-general.php' ) ) );
		}

		// Encoded payloads hidden in settings (never transients).
		$opts = $wpdb->get_col(
			"SELECT option_name FROM {$wpdb->options}
			 WHERE option_name NOT LIKE '\\_transient%' AND option_name NOT LIKE '\\_site\\_transient%'
			 AND ( option_value LIKE '%eval(%base64_decode%' OR option_value LIKE '%eval(%gzinflate%' OR option_value LIKE '%String.fromCharCode%eval(%' )
			 LIMIT 20"
		);
		foreach ( (array) $opts as $name ) {
			self::add( $state, 'danger', 'db_option', 'option: ' . $name, __( 'This setting stores encoded code that runs itself — a payload hidden in the database, where file scanners never look.', 'velox' ), array( 'fix' => 'none' ) );
		}

		// Administrator accounts.
		$admins = get_users( array( 'role' => 'administrator', 'fields' => array( 'ID', 'user_login', 'user_email', 'user_registered' ) ) );
		$cut    = time() - self::NEW_ADMIN_DAYS * DAY_IN_SECONDS;
		foreach ( (array) $admins as $u ) {
			if ( strtotime( $u->user_registered . ' UTC' ) > $cut ) {
				self::add( $state, 'warn', 'admin_new', $u->user_login . ' <' . $u->user_email . '>', sprintf(
					/* translators: %s: how long ago */
					__( 'A new administrator account, created %s ago. If you did not add it, an attacker did — it is how they keep coming back.', 'velox' ),
					human_time_diff( strtotime( $u->user_registered . ' UTC' ) )
				), array( 'fix' => 'link', 'url' => admin_url( 'user-edit.php?user_id=' . (int) $u->ID ) ) );
			}
		}
		$state['counts']['admins'] = count( (array) $admins );
		return false;
	}

	/**
	 * Is there markup in this content that attacks visitors? Danger: inline
	 * script that decodes and runs code, or an invisible iframe. Warn: a script
	 * loaded from a domain that isn't a well-known embed provider.
	 */
	public static function bad_markup( $html, $own ) {
		if ( preg_match( '#<script\b[^>]*>(.*?)</script\s*>#is', $html ) ) {
			preg_match_all( '#<script\b([^>]*)>(.*?)</script\s*>#is', $html, $all, PREG_SET_ORDER | PREG_OFFSET_CAPTURE );
			foreach ( $all as $m ) {
				$body = $m[2][0];
				if ( preg_match( '/\beval\s*\(|String\.fromCharCode\s*\(.{0,40}\d+\s*,\s*\d+|document\.write\s*\(\s*unescape|atob\s*\(\s*[\'"][A-Za-z0-9+\/=]{40,}/is', $body ) ) {
					return array( 'level' => 'danger', 'why' => __( 'Contains a hidden script that decodes and runs code in your visitors\' browsers — usually a redirect to spam or a fake "update your browser" page.', 'velox' ), 'snippet' => self::snippet( $html, $m[0][1] ) );
				}
				if ( preg_match( '/\bsrc\s*=\s*[\'"]?(?:https?:)?\/\/([^\/\'"\s>]+)/i', $m[1][0], $s ) ) {
					$host = strtolower( $s[1] );
					if ( $host !== $own && ! self::known_embed_host( $host ) ) {
						return array( 'level' => 'warn', 'why' => sprintf(
							/* translators: %s: host */
							__( 'Loads a script from %s. If you did not put it there, it was injected.', 'velox' ),
							$host
						), 'snippet' => self::snippet( $html, $m[0][1] ) );
					}
				}
			}
		}
		if ( preg_match( '#<iframe\b[^>]*(?:width\s*=\s*[\'"]?[01]\b|height\s*=\s*[\'"]?[01]\b|display\s*:\s*none|visibility\s*:\s*hidden)[^>]*>#i', $html, $m, PREG_OFFSET_CAPTURE ) ) {
			return array( 'level' => 'danger', 'why' => __( 'Contains an invisible frame that silently loads another website in your visitors\' browsers.', 'velox' ), 'snippet' => self::snippet( $html, $m[0][1] ) );
		}
		return null;
	}

	private static function known_embed_host( $host ) {
		$ok = array( 'googletagmanager.com', 'google-analytics.com', 'google.com', 'gstatic.com', 'googleapis.com', 'youtube.com', 'youtube-nocookie.com', 'vimeo.com', 'player.vimeo.com', 'facebook.net', 'facebook.com', 'instagram.com', 'twitter.com', 'x.com', 'platform.twitter.com', 'cloudflare.com', 'cdnjs.cloudflare.com', 'jsdelivr.net', 'unpkg.com', 'calendly.com', 'hubspot.com', 'hs-scripts.com', 'typeform.com', 'tiktok.com', 'pinterest.com', 'linkedin.com', 'cookiebot.com', 'usercentrics.eu', 'trustedshops.com', 'paypal.com', 'stripe.com', 'recaptcha.net', 'hcaptcha.com', 'challenges.cloudflare.com', 'maps.googleapis.com', 'elfsight.com', 'provenexpert.com', 'etracker.com', 'matomo.cloud' );
		foreach ( $ok as $d ) {
			if ( $host === $d || substr( $host, -strlen( '.' . $d ) ) === '.' . $d ) {
				return true;
			}
		}
		return false;
	}

	/* ------------------------------------------------------------ hardening */

	/** The doors hacks come through. 'info' — never presented as an infection. */
	private static function do_hardening( &$state ) {
		if ( ! function_exists( 'get_plugin_updates' ) ) {
			require_once ABSPATH . 'wp-admin/includes/update.php';
		}
		$plugins = function_exists( 'get_plugin_updates' ) ? count( (array) get_plugin_updates() ) : 0;
		$themes  = function_exists( 'get_theme_updates' ) ? count( (array) get_theme_updates() ) : 0;
		$core    = function_exists( 'get_core_updates' ) ? array_filter( (array) get_core_updates(), function ( $u ) {
			return isset( $u->response ) && 'upgrade' === $u->response;
		} ) : array();
		if ( $plugins || $themes || $core ) {
			self::add( $state, 'info', 'outdated', __( 'Updates', 'velox' ), sprintf(
				/* translators: 1: plugins 2: themes */
				__( '%1$d plugin and %2$d theme updates are waiting%3$s. Out-of-date plugins are the most common way WordPress sites get hacked.', 'velox' ),
				$plugins,
				$themes,
				$core ? __( ', plus a WordPress update', 'velox' ) : ''
			), array( 'fix' => 'link', 'url' => admin_url( 'update-core.php' ) ) );
		}
		if ( ! ( defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT ) ) {
			self::add( $state, 'info', 'file_edit', 'wp-config.php', __( 'The built-in theme and plugin editor is switched on. Anyone who steals an admin login can write PHP through it. Add define( \'DISALLOW_FILE_EDIT\', true ); to wp-config.php to close it.', 'velox' ) );
		}
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && ( ! defined( 'WP_DEBUG_DISPLAY' ) || WP_DEBUG_DISPLAY ) ) {
			self::add( $state, 'info', 'debug_display', 'wp-config.php', __( 'Error messages are shown to visitors. They reveal file paths and versions that help an attacker.', 'velox' ) );
		}
		if ( username_exists( 'admin' ) ) {
			self::add( $state, 'info', 'admin_user', 'admin', __( 'An account is called "admin" — the first name every password-guessing bot tries. Login protection (Security → Login protection) blocks those bots.', 'velox' ) );
		}
		return false;
	}

	/* ---------------------------------------------------------------- utils */

	/**
	 * A guard file, not a threat.
	 *
	 * Plugins drop an inert index.php into their own upload folders so the
	 * directory cannot be listed — WP All Import, WooCommerce and most others
	 * do it, several folders deep. This asks whether the file DOES anything:
	 * a file whose only tokens are an open tag and comments cannot execute.
	 */
	private static function is_inert( $abs, $name ) {
		if ( 'index.php' !== strtolower( $name ) ) {
			return false;
		}
		$size = @filesize( $abs );
		if ( false === $size || $size > 512 ) {
			return false;
		}
		$src = @file_get_contents( $abs );
		if ( false === $src ) {
			return false;
		}
		if ( '' === trim( $src ) ) {
			return true;
		}
		if ( ! function_exists( 'token_get_all' ) ) {
			return (bool) preg_match( '{^\s*<\?php\s*(//[^\n]*\s*)*$}', $src );
		}
		$harmless = array( T_OPEN_TAG, T_CLOSE_TAG, T_WHITESPACE, T_COMMENT, T_DOC_COMMENT, T_INLINE_HTML );
		foreach ( @token_get_all( $src ) as $t ) {
			if ( ! is_array( $t ) ) {
				return false;
			}
			if ( ! in_array( $t[0], $harmless, true ) ) {
				return false;
			}
			if ( T_INLINE_HTML === $t[0] && '' !== trim( $t[1] ) ) {
				return false;
			}
		}
		return true;
	}

	private static function is_executable_name( $name ) {
		return (bool) preg_match( '/\.(php\d?|phtml|phtm|pht|phps|phar)$/i', $name );
	}

	/**
	 * Every file below a directory. Symlinks are not followed: a link pointing
	 * back up its own tree would walk forever.
	 */
	private static function walk( $base ) {
		$out = array();
		try {
			$it = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $base, FilesystemIterator::SKIP_DOTS ),
				RecursiveIteratorIterator::LEAVES_ONLY,
				RecursiveIteratorIterator::CATCH_GET_CHILD
			);
			foreach ( $it as $f ) {
				if ( $f->isFile() ) {
					$out[] = $f->getPathname();
				}
			}
		} catch ( Exception $e ) {
			return $out;
		}
		return $out;
	}

	private static function add( &$state, $level, $kind, $path, $note, $meta = array() ) {
		if ( count( $state['findings'] ) >= self::MAX_FINDINGS ) {
			$state['extra']++;
			return;
		}
		$state['findings'][] = array(
			'level' => $level,
			'kind'  => $kind,
			'path'  => $path,
			'note'  => $note,
			'meta'  => $meta,
		);
	}

	private static function progress( $state ) {
		$phases = self::phases();
		$phase  = $state['phase'];
		$pct    = isset( $phases[ $phase ]['percent'] ) ? $phases[ $phase ]['percent'] : 0;

		// Inside the long phases the cursor gives a real percentage to show.
		if ( 'core' === $phase && ! empty( $state['total'] ) ) {
			$pct = 8 + (int) floor( 22 * min( 1, $state['cursor'] / max( 1, $state['total'] ) ) );
		} elseif ( 'code' === $phase ) {
			$files = get_option( self::FILES );
			if ( is_array( $files ) && $files ) {
				$pct = 55 + (int) floor( 30 * min( 1, $state['cursor'] / count( $files ) ) );
			}
		}

		return array(
			'done'    => false,
			'percent' => $pct,
			'label'   => isset( $phases[ $phase ]['label'] ) ? $phases[ $phase ]['label'] : '',
			'phase'   => $phase,
			'found'   => count( array_filter( $state['findings'], function ( $f ) { return 'info' !== $f['level']; } ) ) + (int) $state['extra'],
		);
	}

	private static function finish( $state ) {
		$order = array( 'danger' => 0, 'warn' => 1, 'info' => 2 );
		$found = $state['findings'];
		usort( $found, function ( $a, $b ) use ( $order ) {
			$d = ( $order[ $a['level'] ] ?? 3 ) <=> ( $order[ $b['level'] ] ?? 3 );
			return $d ? $d : strcmp( $a['path'], $b['path'] );
		} );

		if ( $state['unverified'] ) {
			$state['notes'][] = sprintf(
				/* translators: %s: comma-separated plugin names */
				__( 'Not on WordPress.org, so checked by reading their code instead of by checksum: %s.', 'velox' ),
				implode( ', ', array_slice( $state['unverified'], 0, 12 ) ) . ( count( $state['unverified'] ) > 12 ? ' …' : '' )
			);
		}

		$prev   = self::last();
		$result = array(
			'findings' => $found,
			'extra'    => (int) $state['extra'],
			'notes'    => $state['notes'],
			'counts'   => $state['counts'],
			'version'  => $state['version'],
			'locale'   => $state['locale'],
			'source'   => isset( $state['source'] ) ? $state['source'] : 'manual',
			'started'  => (int) $state['started'],
			'ended'    => time(),
			'levels'   => array(
				'danger' => count( array_filter( $found, function ( $f ) { return 'danger' === $f['level']; } ) ),
				'warn'   => count( array_filter( $found, function ( $f ) { return 'warn' === $f['level']; } ) ),
				'info'   => count( array_filter( $found, function ( $f ) { return 'info' === $f['level']; } ) ),
			),
		);
		update_option( self::RESULT, $result, false );
		delete_option( self::MANIFEST );
		delete_option( self::FILES );
		delete_option( self::STATE );

		if ( 'cron' === $result['source'] ) {
			self::maybe_alert( $result, $prev );
		}

		$result['done']    = true;
		$result['percent'] = 100;
		return $result;
	}

	/* ------------------------------------------------------------ scheduled */

	/** Nightly / weekly: start a scan and work through as much as fits. */
	public static function cron_start() {
		Velox::$admin_text = true; // findings + the alert email are read in wp-admin's language
		$s = get_option( self::STATE );
		if ( is_array( $s ) && ! empty( $s['phase'] ) && time() - (int) $s['started'] < HOUR_IN_SECONDS ) {
			return; // one is already running
		}
		self::start( 'cron' );
		self::cron_continue();
	}

	/** Run steps for ~20 seconds, then hand over to the next cron tick. */
	public static function cron_continue() {
		Velox::$admin_text = true;
		$t0 = microtime( true );
		do {
			$r = self::step();
			if ( ! empty( $r['done'] ) ) {
				return;
			}
		} while ( microtime( true ) - $t0 < 20 );
		wp_schedule_single_event( time() + 30, self::CONTINUE_HOOK );
	}

	/** Email the admin when a scheduled scan finds something serious that wasn't there before. */
	private static function maybe_alert( $result, $prev ) {
		if ( ! Velox_Settings::get( 'scan_email', true ) || empty( $result['levels']['danger'] ) ) {
			return;
		}
		$old = array();
		foreach ( isset( $prev['findings'] ) ? (array) $prev['findings'] : array() as $f ) {
			$old[ $f['kind'] . '|' . $f['path'] ] = true;
		}
		$new = array();
		foreach ( $result['findings'] as $f ) {
			if ( 'danger' === $f['level'] && ! isset( $old[ $f['kind'] . '|' . $f['path'] ] ) ) {
				$new[] = $f;
			}
		}
		if ( ! $new ) {
			return; // already reported last time
		}
		$site  = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$lines = array();
		foreach ( array_slice( $new, 0, 15 ) as $f ) {
			$lines[] = '• ' . $f['path'] . "\n  " . $f['note'];
		}
		$body = sprintf(
			/* translators: 1: site name 2: count */
			__( 'The automatic Velox security scan of %1$s found %2$d new serious problem(s):', 'velox' ),
			$site,
			count( $new )
		) . "\n\n" . implode( "\n\n", $lines ) . "\n\n" .
		__( 'Open the report to see how each one works and fix it:', 'velox' ) . "\n" . Velox_Utilities::tool_url( 'sitescan' );
		wp_mail(
			get_option( 'admin_email' ),
			sprintf(
				/* translators: 1: site name 2: count */
				__( '[%1$s] Security scan: %2$d serious problem(s) found', 'velox' ),
				$site,
				count( $new )
			),
			$body
		);
	}

	/* --------------------------------------------------------------- result */

	/** The last completed scan, or an empty array if there has never been one. */
	public static function last() {
		$r = get_option( self::RESULT );
		return is_array( $r ) ? $r : array();
	}

	/** Mark one finding as dealt with (after a successful fix) and recount. */
	public static function resolve( $index, $how ) {
		$r = self::last();
		if ( ! isset( $r['findings'][ $index ] ) ) {
			return;
		}
		$r['findings'][ $index ]['resolved'] = $how;
		$r['levels'] = array( 'danger' => 0, 'warn' => 0, 'info' => 0 );
		foreach ( $r['findings'] as $f ) {
			if ( empty( $f['resolved'] ) && isset( $r['levels'][ $f['level'] ] ) ) {
				$r['levels'][ $f['level'] ]++;
			}
		}
		update_option( self::RESULT, $r, false );
	}

	public static function running() {
		$s = get_option( self::STATE );
		return is_array( $s ) && ! empty( $s['phase'] );
	}

	public static function forget() {
		delete_option( self::RESULT );
		delete_option( self::MANIFEST );
		delete_option( self::FILES );
		delete_option( self::STATE );
	}
}
