<?php
/**
 * Velox Error Logger.
 *
 * Captures PHP errors + fatals and failed HTTP/API requests, stores a capped
 * list in a WordPress option, and classifies each into a type with plain-English
 * "what it is / how to fix it" guidance drawn from a built-in knowledge base.
 *
 * Storage is a single option (no DB table): a rolling list, newest first, capped
 * at self::MAX. Duplicate errors are de-duplicated by a fingerprint and counted
 * instead of stored twice, so a noisy warning doesn't flood the log.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Velox_Error_Logger {

	const OPTION = 'velox_error_log';
	const MAX    = 200;

	/** Recursion guard so logging an error can't trigger another log. */
	private static $busy = false;

	/** Previously-registered PHP error handler, so we chain instead of clobber. */
	private static $prev_handler = null;

	/* ------------------------------------------------------------------ boot */

	public static function init() {
		if ( ! Velox_Settings::get( 'util_errorlog', false ) ) {
			return;
		}

		// PHP notices / warnings / deprecations (non-fatal).
		self::$prev_handler = set_error_handler( array( __CLASS__, 'on_php_error' ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler

		// Fatals — caught on shutdown.
		register_shutdown_function( array( __CLASS__, 'on_shutdown' ) );

		// Failed outgoing HTTP / API requests (wp_remote_*).
		add_action( 'http_api_debug', array( __CLASS__, 'on_http' ), 10, 5 );
	}

	/* -------------------------------------------------------------- captures */

	/**
	 * Non-fatal PHP errors. We record, then chain to any previous handler and
	 * return false so PHP's normal handling still runs.
	 */
	public static function on_php_error( $errno, $errstr, $errfile = '', $errline = 0 ) {
		// Respect the current error_reporting level (honours @-silenced calls).
		if ( ! ( error_reporting() & $errno ) ) {
			return false;
		}
		self::record( array(
			'group'   => 'php',
			'level'   => self::php_level_name( $errno ),
			'message' => (string) $errstr,
			'file'    => (string) $errfile,
			'line'    => (int) $errline,
			'code'    => (int) $errno,
		) );
		if ( self::$prev_handler ) {
			return call_user_func( self::$prev_handler, $errno, $errstr, $errfile, $errline );
		}
		return false;
	}

	/**
	 * Fatal errors (parse/compile/E_ERROR) surface only via error_get_last()
	 * during shutdown.
	 */
	public static function on_shutdown() {
		$e = error_get_last();
		if ( ! $e || ! in_array( $e['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR ), true ) ) {
			return;
		}
		self::record( array(
			'group'   => 'fatal',
			'level'   => self::php_level_name( $e['type'] ),
			'message' => (string) $e['message'],
			'file'    => (string) $e['file'],
			'line'    => (int) $e['line'],
			'code'    => (int) $e['type'],
		) );
	}

	/**
	 * Outgoing HTTP requests. Logs transport failures (WP_Error) and HTTP
	 * responses with a 4xx/5xx status.
	 */
	public static function on_http( $response, $context, $class, $args, $url ) {
		if ( is_wp_error( $response ) ) {
			self::record( array(
				'group'   => 'http',
				'level'   => 'Request failed',
				'message' => $response->get_error_message(),
				'file'    => self::host_of( $url ),
				'line'    => 0,
				'code'    => 0,
				'url'     => (string) $url,
			) );
			return;
		}
		$status = isset( $response['response']['code'] ) ? (int) $response['response']['code'] : 0;
		if ( $status >= 400 ) {
			self::record( array(
				'group'   => 'http',
				'level'   => 'HTTP ' . $status,
				'message' => isset( $response['response']['message'] ) ? (string) $response['response']['message'] : '',
				'file'    => self::host_of( $url ),
				'line'    => 0,
				'code'    => $status,
				'url'     => (string) $url,
			) );
		}
	}

	/* ----------------------------------------------------------- storage */

	/**
	 * Record one error. De-dupes by fingerprint (group+code+message+file+line):
	 * a repeat bumps the count + last-seen timestamp instead of adding a row.
	 */
	private static function record( $data ) {
		if ( self::$busy ) {
			return;
		}
		self::$busy = true;

		$data = wp_parse_args( $data, array(
			'group'   => 'php',
			'level'   => 'Error',
			'message' => '',
			'file'    => '',
			'line'    => 0,
			'code'    => 0,
			'url'     => '',
		) );

		// Trim noisy absolute paths to something readable (relative to ABSPATH).
		$data['file'] = self::short_path( $data['file'] );
		$data['message'] = self::clip( $data['message'], 500 );

		$fp = md5( $data['group'] . '|' . $data['code'] . '|' . $data['message'] . '|' . $data['file'] . '|' . $data['line'] );

		$log = get_option( self::OPTION, array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}

		$now = time();
		if ( isset( $log[ $fp ] ) ) {
			$log[ $fp ]['count']    = (int) $log[ $fp ]['count'] + 1;
			$log[ $fp ]['last']     = $now;
		} else {
			$data['fp']    = $fp;
			$data['count'] = 1;
			$data['first'] = $now;
			$data['last']  = $now;
			$log = array( $fp => $data ) + $log; // newest first
		}

		// Cap: keep the most recently-seen MAX entries.
		if ( count( $log ) > self::MAX ) {
			uasort( $log, function ( $a, $b ) {
				return (int) $b['last'] - (int) $a['last'];
			} );
			$log = array_slice( $log, 0, self::MAX, true );
		}

		update_option( self::OPTION, $log, false );
		self::$busy = false;
	}

	/* ------------------------------------------------------------- read API */

	/** All logged errors, newest-seen first. */
	public static function all() {
		$log = get_option( self::OPTION, array() );
		if ( ! is_array( $log ) ) {
			return array();
		}
		uasort( $log, function ( $a, $b ) {
			return (int) $b['last'] - (int) $a['last'];
		} );
		return array_values( $log );
	}

	/** Errors grouped by their display group (fatal, php, http). */
	public static function grouped() {
		$out = array( 'fatal' => array(), 'php' => array(), 'http' => array() );
		foreach ( self::all() as $e ) {
			$g = isset( $e['group'] ) ? $e['group'] : 'php';
			if ( ! isset( $out[ $g ] ) ) {
				$out[ $g ] = array();
			}
			$out[ $g ][] = $e;
		}
		return $out;
	}

	public static function count() {
		return count( self::all() );
	}

	public static function clear() {
		delete_option( self::OPTION );
		return true;
	}

	public static function delete_one( $fp ) {
		$log = get_option( self::OPTION, array() );
		if ( is_array( $log ) && isset( $log[ $fp ] ) ) {
			unset( $log[ $fp ] );
			update_option( self::OPTION, $log, false );
			return true;
		}
		return false;
	}

	/* ------------------------------------------- knowledge base (how to fix) */

	/**
	 * Map an error to plain-English guidance: a friendly type name, a short
	 * explanation of what it means, and concrete steps to fix it. Rule-based,
	 * offline, matched from the message + group. Returns array( title, what, fix ).
	 */
	public static function guidance( $e ) {
		$msg   = isset( $e['message'] ) ? $e['message'] : '';
		$group = isset( $e['group'] ) ? $e['group'] : 'php';
		$code  = isset( $e['code'] ) ? (int) $e['code'] : 0;
		$m     = strtolower( $msg );

		// --- HTTP errors -------------------------------------------------
		if ( 'http' === $group ) {
			if ( 0 === $code ) {
				return array(
					__( 'Outgoing request failed', 'velox' ),
					__( 'WordPress tried to reach another server and the connection itself failed — before any HTTP status came back.', 'velox' ),
					__( 'Usually a DNS, firewall or timeout issue. Check the host is reachable from your server, that outbound connections aren’t blocked, and that any API key or URL is correct. If it’s a loopback to your own site, your host may be blocking server-to-self requests.', 'velox' ),
				);
			}
			if ( 401 === $code || 403 === $code ) {
				return array(
					__( 'Request rejected (auth)', 'velox' ),
					/* translators: %d is the HTTP status code. */
					sprintf( __( 'The remote server refused the request with a %d — an authentication or permission problem.', 'velox' ), $code ),
					__( 'Check the API key, token or credentials for that service, and that they haven’t expired or lost the required scope.', 'velox' ),
				);
			}
			if ( 404 === $code ) {
				return array(
					__( 'Remote endpoint not found (404)', 'velox' ),
					__( 'The URL WordPress requested doesn’t exist on the remote server.', 'velox' ),
					__( 'Verify the endpoint URL is correct and current — an API may have moved or changed its version path.', 'velox' ),
				);
			}
			if ( 429 === $code ) {
				return array(
					__( 'Rate limited (429)', 'velox' ),
					__( 'The remote service is throttling your requests because too many arrived too fast.', 'velox' ),
					__( 'Slow the request rate, add caching so you call the API less often, or upgrade the plan/quota with that provider.', 'velox' ),
				);
			}
			if ( $code >= 500 ) {
				return array(
					/* translators: %d is the HTTP status code. */
					sprintf( __( 'Remote server error (%d)', 'velox' ), $code ),
					__( 'The other server had an internal problem — this is on their end, not yours.', 'velox' ),
					__( 'Usually temporary. Retry later; if it persists, check that provider’s status page or contact them.', 'velox' ),
				);
			}
			return array(
				/* translators: %d is the HTTP status code. */
				sprintf( __( 'HTTP error (%d)', 'velox' ), $code ),
				__( 'An outgoing request returned an error status.', 'velox' ),
				__( 'Check the request URL and parameters for that service.', 'velox' ),
			);
		}

		// --- Common PHP messages, matched by phrase ---------------------
		if ( false !== strpos( $m, 'allowed memory size' ) ) {
			return array(
				__( 'Out of memory', 'velox' ),
				__( 'A script tried to use more memory than PHP allows, so it was killed mid-run.', 'velox' ),
				__( 'Raise the PHP memory limit (WP_MEMORY_LIMIT in wp-config.php, or php.ini), and look at whichever plugin/theme is named in the file path — it may be loading too much at once.', 'velox' ),
			);
		}
		if ( false !== strpos( $m, 'maximum execution time' ) ) {
			return array(
				__( 'Script timed out', 'velox' ),
				__( 'A script ran longer than the allowed time limit and PHP stopped it.', 'velox' ),
				__( 'Increase max_execution_time, or fix the slow operation (a heavy query, an external API with no timeout, or an import running on the main request).', 'velox' ),
			);
		}
		if ( false !== strpos( $m, 'call to undefined function' ) || false !== strpos( $m, 'call to undefined method' ) ) {
			return array(
				__( 'Missing function or method', 'velox' ),
				__( 'Code called something that doesn’t exist — usually a plugin was updated, deactivated, or a required library isn’t loaded.', 'velox' ),
				__( 'Check the plugin/theme named in the file path is active and up to date. If it appeared right after an update, roll that update back and report it to the author.', 'velox' ),
			);
		}
		if ( false !== strpos( $m, 'undefined' ) && ( false !== strpos( $m, 'array key' ) || false !== strpos( $m, 'index' ) || false !== strpos( $m, 'offset' ) || false !== strpos( $m, 'variable' ) || false !== strpos( $m, 'property' ) ) ) {
			return array(
				__( 'Undefined value', 'velox' ),
				__( 'Code read a variable, array key or property that was never set. Often harmless, but it can hint at a logic bug.', 'velox' ),
				__( 'Usually safe to ignore if the page still works. If it’s from a plugin/theme, update it; the author should guard the value with isset() before using it.', 'velox' ),
			);
		}
		if ( false !== strpos( $m, 'trying to access array offset' ) || false !== strpos( $m, 'on null' ) || false !== strpos( $m, 'on bool' ) ) {
			return array(
				__( 'Value was not what code expected', 'velox' ),
				__( 'Code treated something as an array or object when it was actually null or a boolean — often a failed lookup that returned nothing.', 'velox' ),
				__( 'Update the plugin/theme named in the path. It usually means an earlier step returned nothing and the result wasn’t checked before use.', 'velox' ),
			);
		}
		if ( false !== strpos( $m, 'headers already sent' ) ) {
			return array(
				__( 'Headers already sent', 'velox' ),
				__( 'Something printed output (often a stray space or a notice) before WordPress tried to send HTTP headers — this breaks redirects and cookies.', 'velox' ),
				__( 'Find the file/line named as the output source: usually whitespace after a closing ?> tag, or an error being printed. Remove trailing blank lines from that file.', 'velox' ),
			);
		}
		if ( false !== strpos( $m, 'syntax error' ) || E_PARSE === $code ) {
			return array(
				__( 'PHP syntax error', 'velox' ),
				__( 'A file has invalid PHP and can’t be parsed — this usually takes the whole site down (white screen).', 'velox' ),
				__( 'Open the file at the exact line shown and fix the syntax (often a missing semicolon, bracket or quote). If it came from an edit or a snippet, undo that change.', 'velox' ),
			);
		}
		if ( false !== strpos( $m, 'deprecated' ) || E_DEPRECATED === $code || E_USER_DEPRECATED === $code ) {
			return array(
				__( 'Deprecated code', 'velox' ),
				__( 'A plugin or theme is using something PHP or WordPress plans to remove. It still works today but will break on a future update.', 'velox' ),
				__( 'Not urgent. Update the plugin/theme named in the path to a version that no longer uses the old code. If you’re on a new PHP version, the author may not have caught up yet.', 'velox' ),
			);
		}

		// --- Fallback by group ------------------------------------------
		if ( 'fatal' === $group ) {
			return array(
				__( 'Fatal error', 'velox' ),
				__( 'A fatal error stops the current request completely — often the cause of a white screen or a broken page.', 'velox' ),
				__( 'Look at the file and line named. If it points at a plugin or theme, deactivate that one to confirm, then update or replace it. Enabling WP_DEBUG_LOG gives a fuller stack trace.', 'velox' ),
			);
		}
		return array(
			__( 'PHP notice', 'velox' ),
			__( 'A low-severity message from PHP. The page usually still works, but it can point at a small bug in a plugin or theme.', 'velox' ),
			__( 'Safe to ignore if everything works. If it’s frequent and from one plugin/theme, updating it usually clears it.', 'velox' ),
		);
	}

	/* ---------------------------------------------------------------- utils */

	private static function php_level_name( $type ) {
		$map = array(
			E_ERROR             => 'Fatal error',
			E_WARNING           => 'Warning',
			E_PARSE             => 'Parse error',
			E_NOTICE            => 'Notice',
			E_CORE_ERROR        => 'Core error',
			E_CORE_WARNING      => 'Core warning',
			E_COMPILE_ERROR     => 'Compile error',
			E_COMPILE_WARNING   => 'Compile warning',
			E_USER_ERROR        => 'User error',
			E_USER_WARNING      => 'User warning',
			E_USER_NOTICE       => 'User notice',
			E_STRICT            => 'Strict notice',
			E_RECOVERABLE_ERROR => 'Recoverable error',
			E_DEPRECATED        => 'Deprecated',
			E_USER_DEPRECATED   => 'Deprecated',
		);
		return isset( $map[ $type ] ) ? $map[ $type ] : 'Error';
	}

	private static function short_path( $path ) {
		if ( '' === $path ) {
			return '';
		}
		if ( defined( 'ABSPATH' ) && 0 === strpos( $path, ABSPATH ) ) {
			return ltrim( substr( $path, strlen( ABSPATH ) ), '/' );
		}
		return $path;
	}

	private static function host_of( $url ) {
		$h = wp_parse_url( (string) $url, PHP_URL_HOST );
		return $h ? $h : (string) $url;
	}

	private static function clip( $s, $len ) {
		$s = trim( preg_replace( '/\s+/', ' ', (string) $s ) );
		if ( strlen( $s ) > $len ) {
			$s = substr( $s, 0, $len - 1 ) . '…';
		}
		return $s;
	}
}
