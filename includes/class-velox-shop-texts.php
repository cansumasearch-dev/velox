<?php
/**
 * Velox Shop — every text customers see, editable.
 *
 * The catalogue is read from the shop's own source files (each __() /
 * esc_html__() / _n() call with the 'velox' text domain in the storefront,
 * cart, checkout, account, extras and emails), so a new text appears here by
 * itself. An own text replaces the original through WordPress's gettext
 * filter, in every place that text is used — page, script and email.
 *
 * @package Velox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Velox_Shop_Texts {

	const OPT = 'velox_shop_texts';

	/** Source file → the area its texts belong to. */
	const FILES = array(
		'includes/class-velox-shop-front.php'   => 'store',
		'includes/class-velox-shop-pay.php'     => 'checkout',
		'includes/class-velox-shop-account.php' => 'account',
		'includes/class-velox-shop-extras.php'  => 'extras',
		'includes/class-velox-shop.php'         => 'emails',
	);

	private static $map = null;

	public static function init() {
		if ( self::overrides() ) {
			add_filter( 'gettext', array( __CLASS__, 'swap' ), 99, 3 );
			add_filter( 'ngettext', array( __CLASS__, 'swap_n' ), 99, 5 );
		}
	}

	public static function overrides() {
		if ( null === self::$map ) {
			$m = get_option( self::OPT, array() );
			self::$map = is_array( $m ) ? $m : array();
		}
		return self::$map;
	}

	public static function swap( $translation, $text, $domain ) {
		if ( 'velox' !== $domain || ! isset( self::$map[ $text ] ) ) {
			return $translation;
		}
		return self::$map[ $text ];
	}

	public static function swap_n( $translation, $single, $plural, $n, $domain ) {
		if ( 'velox' !== $domain ) {
			return $translation;
		}
		$key = 1 === (int) $n ? $single : $plural;
		return isset( self::$map[ $key ] ) ? self::$map[ $key ] : $translation;
	}

	/** Labels for the areas, in the order the screen shows them. */
	public static function areas() {
		return array(
			'store'    => __( 'Shop and product pages', 'velox' ),
			'checkout' => __( 'Cart and checkout', 'velox' ),
			'account'  => __( 'Customer account', 'velox' ),
			'extras'   => __( 'Wishlist, reviews and alerts', 'velox' ),
			'emails'   => __( 'Emails and order states', 'velox' ),
		);
	}

	/**
	 * Every shop text: original (English), area, and how it reads now.
	 * Read from the files once per Velox version.
	 */
	public static function catalog() {
		$key  = 'velox_shop_textcat_' . ( defined( 'VELOX_VERSION' ) ? VELOX_VERSION : '0' );
		$list = get_transient( $key );
		if ( ! is_array( $list ) ) {
			$list = array();
			$seen = array();
			foreach ( self::FILES as $file => $area ) {
				$src = @file_get_contents( VELOX_PATH . $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions,WordPress.PHP.NoSilencedErrors
				if ( ! $src ) {
					continue;
				}
				// __() and its relatives with the velox text domain …
				preg_match_all( "/\\b(?:__|_e|esc_html__|esc_html_e|esc_attr__|esc_attr_e)\\(\\s*'((?:[^'\\\\]|\\\\.)*)'\\s*,\\s*'velox'\\s*\\)/", $src, $m1 );
				// … and _n( 'one', 'many', $n, 'velox' ).
				preg_match_all( "/\\b_n\\(\\s*'((?:[^'\\\\]|\\\\.)*)'\\s*,\\s*'((?:[^'\\\\]|\\\\.)*)'\\s*,[^,]+,\\s*'velox'\\s*\\)/", $src, $m2 );
				$found = array_merge( $m1[1], $m2[1], $m2[2] );
				foreach ( $found as $raw ) {
					// Single-quoted PHP: only \' and \\ are escapes.
					$t = str_replace( array( "\\'", '\\\\' ), array( "'", '\\' ), $raw );
					if ( '' === trim( $t ) || isset( $seen[ $t ] ) || strlen( $t ) > 400 ) {
						continue;
					}
					$seen[ $t ] = 1;
					$list[]     = array( 'o' => $t, 'a' => $area );
				}
			}
			set_transient( $key, $list, WEEK_IN_SECONDS );
		}
		$own = self::overrides();
		$out = array();
		// What each text says without an own text, in the language of this screen.
		self::$map = array();
		foreach ( $list as $row ) {
			$now   = __( $row['o'], 'velox' ); // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
			$out[] = array( 'o' => $row['o'], 'a' => $row['a'], 'd' => $now, 'v' => isset( $own[ $row['o'] ] ) ? $own[ $row['o'] ] : '' );
		}
		self::$map = $own;
		return $out;
	}

	/** The placeholders a text uses (%s, %1$s, %d …), sorted. */
	public static function placeholders( $t ) {
		preg_match_all( '/%(?:\d+\$)?[sdf]/', (string) $t, $m );
		$p = $m[0];
		sort( $p );
		return $p;
	}

	/**
	 * Save own texts. $changes: original => own text ('' = back to the original).
	 *
	 * @return true|WP_Error
	 */
	public static function save( $changes ) {
		$known = array();
		foreach ( self::catalog() as $row ) {
			$known[ $row['o'] ] = 1;
		}
		$map = self::overrides();
		foreach ( (array) $changes as $orig => $own ) {
			$orig = (string) $orig;
			if ( ! isset( $known[ $orig ] ) ) {
				continue;
			}
			$own = trim( wp_kses( (string) $own, array( 'a' => array( 'href' => true ), 'b' => array(), 'strong' => array(), 'em' => array(), 'br' => array() ) ) );
			if ( '' === $own ) {
				unset( $map[ $orig ] );
				continue;
			}
			if ( self::placeholders( $orig ) !== self::placeholders( $own ) ) {
				/* translators: 1: the original text, 2: its placeholders */
				return new WP_Error( 'velox_texts', sprintf( __( '“%1$s” must keep its placeholders (%2$s): they are filled in with a number or a name.', 'velox' ), $orig, implode( ' ', self::placeholders( $orig ) ) ) );
			}
			$map[ $orig ] = mb_substr( $own, 0, 600 );
		}
		update_option( self::OPT, $map, true );
		self::$map = $map;
		return true;
	}
}
