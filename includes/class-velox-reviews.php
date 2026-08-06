<?php
/**
 * Velox — Google Reviews.
 *
 * Pull Google reviews via the Featurable API (free, caches many reviews) or the
 * Google Places API (official, max 5 reviews) and render them on the front end as
 * a slider or a static grid, fully styleable, with reusable presets.
 *
 * Data model (all under one option, VELOX_Reviews::OPTION):
 *   connections: [ id => { id, name, provider, api_key, place_id/widget_id, … } ]
 *   presets:     [ id => { id, name, type(slider|static), style{…} } ]
 * Reviews themselves are cached per connection in a transient so we never hammer
 * the API on page loads.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Velox_Reviews {

	const OPTION     = 'velox_reviews';
	const CACHE_TTL  = 21600; // 6h cache of fetched reviews

	public static function init() {
		if ( ! Velox_Settings::get( 'util_reviews', false ) ) {
			return;
		}
		add_shortcode( 'velox_reviews', array( __CLASS__, 'shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_front_assets' ) );
	}

	/* --------------------------------------------------------------- store */

	public static function store() {
		$s = get_option( self::OPTION, array() );
		if ( ! is_array( $s ) ) {
			$s = array();
		}
		return wp_parse_args( $s, array(
			'connections' => array(),
			'presets'     => array(),
		) );
	}

	public static function save_store( $store ) {
		update_option( self::OPTION, $store, false );
	}

	/* --------------------------------------------------- connections CRUD */

	/**
	 * Create/update a connection. A NAME is required — without it we refuse to
	 * save (as specified). Returns [ ok, id|error ].
	 */
	public static function save_connection( $data ) {
		$name = isset( $data['name'] ) ? trim( wp_strip_all_tags( (string) $data['name'] ) ) : '';
		if ( '' === $name ) {
			return array( 'ok' => false, 'error' => __( 'Please name this connection before saving.', 'velox' ) );
		}
		$provider = ( isset( $data['provider'] ) && 'google' === $data['provider'] ) ? 'google' : 'featurable';

		// Provider-specific required field.
		if ( 'google' === $provider ) {
			$api_key  = isset( $data['api_key'] ) ? trim( (string) $data['api_key'] ) : '';
			$place_id = isset( $data['place_id'] ) ? trim( (string) $data['place_id'] ) : '';
			if ( '' === $api_key || '' === $place_id ) {
				return array( 'ok' => false, 'error' => __( 'Google needs both an API key and a Place ID.', 'velox' ) );
			}
		} else {
			$widget_id = isset( $data['widget_id'] ) ? trim( (string) $data['widget_id'] ) : '';
			if ( '' === $widget_id ) {
				return array( 'ok' => false, 'error' => __( 'Featurable needs a widget ID.', 'velox' ) );
			}
		}

		$store = self::store();
		$id    = isset( $data['id'] ) && $data['id'] ? sanitize_key( $data['id'] ) : 'conn_' . wp_generate_password( 8, false, false );

		$store['connections'][ $id ] = array(
			'id'        => $id,
			'name'      => $name,
			'provider'  => $provider,
			'api_key'   => isset( $data['api_key'] ) ? trim( (string) $data['api_key'] ) : '',
			'place_id'  => isset( $data['place_id'] ) ? trim( (string) $data['place_id'] ) : '',
			'widget_id' => isset( $data['widget_id'] ) ? trim( (string) $data['widget_id'] ) : '',
		);
		self::save_store( $store );
		delete_transient( 'velox_reviews_' . $id ); // fresh fetch next time
		return array( 'ok' => true, 'id' => $id );
	}

	public static function delete_connection( $id ) {
		$id    = sanitize_key( $id );
		$store = self::store();
		unset( $store['connections'][ $id ] );
		self::save_store( $store );
		delete_transient( 'velox_reviews_' . $id );
		return true;
	}

	/* -------------------------------------------------------- presets CRUD */

	public static function save_preset( $data ) {
		$name = isset( $data['name'] ) ? trim( wp_strip_all_tags( (string) $data['name'] ) ) : '';
		if ( '' === $name ) {
			return array( 'ok' => false, 'error' => __( 'Please name this preset before saving.', 'velox' ) );
		}
		$store = self::store();
		$id    = isset( $data['id'] ) && $data['id'] ? sanitize_key( $data['id'] ) : 'preset_' . wp_generate_password( 8, false, false );
		$store['presets'][ $id ] = array(
			'id'    => $id,
			'name'  => $name,
			'type'  => ( isset( $data['type'] ) && 'static' === $data['type'] ) ? 'static' : 'slider',
			'style' => isset( $data['style'] ) && is_array( $data['style'] ) ? self::sanitize_style( $data['style'] ) : self::default_style(),
		);
		self::save_store( $store );
		return array( 'ok' => true, 'id' => $id );
	}

	public static function delete_preset( $id ) {
		$id    = sanitize_key( $id );
		$store = self::store();
		unset( $store['presets'][ $id ] );
		self::save_store( $store );
		return true;
	}

	/* --------------------------------------------------------------- style */

	public static function default_style() {
		return array(
			'count'          => 6,
			'min_rating'     => 4,
			'show_avatar'    => true,
			'show_date'      => true,
			'show_rating'    => true,
			'columns'        => 3,
			'card_bg'        => '#ffffff',
			'card_radius'    => 12,
			'card_padding'   => 20,
			'card_gap'       => 16,
			'card_shadow'    => true,
			'text_color'     => '#1d2327',
			'meta_color'     => '#6b7280',
			'star_color'     => '#fbbf24',
			'name_size'      => 15,
			'text_size'      => 14,
			'avatar_size'    => 44,
			'autoplay'       => true,
			'autoplay_speed' => 4000,
			'slides_desktop' => 3,
			'slides_tablet'  => 2,
			'slides_mobile'  => 1,
		);
	}

	public static function sanitize_style( $style ) {
		$d   = self::default_style();
		$out = array();
		foreach ( $d as $k => $dv ) {
			if ( ! isset( $style[ $k ] ) ) {
				$out[ $k ] = $dv;
				continue;
			}
			$v = $style[ $k ];
			if ( is_bool( $dv ) ) {
				$out[ $k ] = ( '1' === (string) $v || 'true' === $v || true === $v || 1 === $v );
			} elseif ( is_int( $dv ) ) {
				$out[ $k ] = (int) $v;
			} else {
				// colour / string
				$out[ $k ] = preg_match( '/^#[0-9a-fA-F]{3,8}$/', (string) $v ) ? (string) $v : sanitize_text_field( (string) $v );
			}
		}
		return $out;
	}

	/* --------------------------------------------------------- API fetch */

	/** Return an array of normalised reviews for a connection (cached). */
	public static function get_reviews( $conn_id, $force = false ) {
		$conn = self::store()['connections'][ $conn_id ] ?? null;
		if ( ! $conn ) {
			return array();
		}
		$cache_key = 'velox_reviews_' . $conn_id;
		if ( ! $force ) {
			$cached = get_transient( $cache_key );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}
		$reviews = ( 'google' === $conn['provider'] )
			? self::fetch_google( $conn )
			: self::fetch_featurable( $conn );

		if ( is_array( $reviews ) ) {
			set_transient( $cache_key, $reviews, self::CACHE_TTL );
			return $reviews;
		}
		return array();
	}

	protected static function fetch_featurable( $conn ) {
		$wid = $conn['widget_id'];
		if ( '' === $wid ) {
			return array();
		}
		// Featurable's public widget JSON endpoint.
		$url  = 'https://featurable.com/api/v1/widgets/' . rawurlencode( $wid );
		$resp = wp_remote_get( $url, array( 'timeout' => 12 ) );
		if ( is_wp_error( $resp ) || 200 !== wp_remote_retrieve_response_code( $resp ) ) {
			return array();
		}
		$body = json_decode( wp_remote_retrieve_body( $resp ), true );
		$list = isset( $body['reviews'] ) && is_array( $body['reviews'] ) ? $body['reviews'] : array();
		$out  = array();
		foreach ( $list as $r ) {
			$out[] = self::normalise( array(
				'author' => $r['reviewer']['displayName'] ?? ( $r['author_name'] ?? '' ),
				'avatar' => $r['reviewer']['profilePhotoUrl'] ?? ( $r['profile_photo_url'] ?? '' ),
				'rating' => $r['starRating'] ?? ( $r['rating'] ?? 5 ),
				'text'   => $r['comment'] ?? ( $r['text'] ?? '' ),
				'time'   => $r['createTime'] ?? ( $r['time'] ?? '' ),
			) );
		}
		return $out;
	}

	protected static function fetch_google( $conn ) {
		$key = $conn['api_key'];
		$pid = $conn['place_id'];
		if ( '' === $key || '' === $pid ) {
			return array();
		}
		$url = add_query_arg( array(
			'place_id' => $pid,
			'fields'   => 'reviews',
			'reviews_sort' => 'newest',
			'key'      => $key,
		), 'https://maps.googleapis.com/maps/api/place/details/json' );
		$resp = wp_remote_get( $url, array( 'timeout' => 12 ) );
		if ( is_wp_error( $resp ) || 200 !== wp_remote_retrieve_response_code( $resp ) ) {
			return array();
		}
		$body = json_decode( wp_remote_retrieve_body( $resp ), true );
		$list = $body['result']['reviews'] ?? array();
		$out  = array();
		foreach ( (array) $list as $r ) {
			$out[] = self::normalise( array(
				'author' => $r['author_name'] ?? '',
				'avatar' => $r['profile_photo_url'] ?? '',
				'rating' => $r['rating'] ?? 5,
				'text'   => $r['text'] ?? '',
				'time'   => isset( $r['time'] ) ? gmdate( 'c', (int) $r['time'] ) : '',
			) );
		}
		return $out;
	}

	/** Normalise one review + coerce the star rating (Google sends 1–5; Featurable
	 * sometimes sends the word "FIVE"). */
	protected static function normalise( $r ) {
		$rating = $r['rating'];
		if ( is_string( $rating ) ) {
			$words  = array( 'ONE' => 1, 'TWO' => 2, 'THREE' => 3, 'FOUR' => 4, 'FIVE' => 5 );
			$rating = $words[ strtoupper( $rating ) ] ?? (int) $rating;
		}
		return array(
			'author' => sanitize_text_field( (string) $r['author'] ),
			'avatar' => esc_url_raw( (string) $r['avatar'] ),
			'rating' => max( 0, min( 5, (int) $rating ) ),
			'text'   => wp_strip_all_tags( (string) $r['text'] ),
			'time'   => (string) $r['time'],
		);
	}

	/* ------------------------------------------------------------ render */

	/** Shortcode: [velox_reviews connection="conn_x" preset="preset_y"] */
	public static function shortcode( $atts ) {
		$atts = shortcode_atts( array(
			'connection' => '',
			'preset'     => '',
		), $atts, 'velox_reviews' );

		$store = self::store();
		$conn  = $store['connections'][ sanitize_key( $atts['connection'] ) ] ?? null;
		if ( ! $conn ) {
			return current_user_can( 'manage_options' ) ? '<!-- velox_reviews: unknown connection -->' : '';
		}
		$preset = $store['presets'][ sanitize_key( $atts['preset'] ) ] ?? null;
		$style  = $preset ? $preset['style'] : self::default_style();
		$type   = $preset ? $preset['type'] : 'slider';

		$reviews = self::get_reviews( $conn['id'] );
		// Filter by min rating, cap by count.
		$reviews = array_filter( $reviews, function ( $r ) use ( $style ) {
			return $r['rating'] >= (int) $style['min_rating'];
		} );
		$reviews = array_slice( array_values( $reviews ), 0, max( 1, (int) $style['count'] ) );
		if ( empty( $reviews ) ) {
			return current_user_can( 'manage_options' ) ? '<!-- velox_reviews: no reviews yet (cached) -->' : '';
		}

		self::$need_assets = true;
		return self::render( $reviews, $style, $type, $conn['id'] );
	}

	public static $need_assets = false;

	protected static function render( $reviews, $style, $type, $conn_id ) {
		$uid = 'vxr_' . substr( md5( $conn_id . wp_json_encode( $style ) ), 0, 8 );
		$vars = self::css_vars( $style );
		$is_slider = ( 'slider' === $type );

		$cards = '';
		foreach ( $reviews as $r ) {
			$cards .= self::card( $r, $style );
		}

		$cls = 'velox-reviews' . ( $is_slider ? ' is-slider' : ' is-static' );
		$data = $is_slider ? sprintf(
			' data-autoplay="%d" data-speed="%d" data-d="%d" data-t="%d" data-m="%d"',
			$style['autoplay'] ? 1 : 0,
			(int) $style['autoplay_speed'],
			(int) $style['slides_desktop'],
			(int) $style['slides_tablet'],
			(int) $style['slides_mobile']
		) : '';

		return sprintf(
			'<div class="%1$s" id="%2$s" style="%3$s"%4$s><div class="velox-reviews-track">%5$s</div></div>',
			esc_attr( $cls ),
			esc_attr( $uid ),
			esc_attr( $vars ),
			$data,
			$cards
		);
	}

	protected static function card( $r, $style ) {
		$stars = '';
		if ( $style['show_rating'] ) {
			$stars = '<div class="vxr-stars">';
			for ( $i = 1; $i <= 5; $i++ ) {
				$stars .= '<span class="vxr-star' . ( $i <= $r['rating'] ? ' is-on' : '' ) . '">★</span>';
			}
			$stars .= '</div>';
		}
		$avatar = '';
		if ( $style['show_avatar'] && $r['avatar'] ) {
			$avatar = '<img class="vxr-avatar" src="' . esc_url( $r['avatar'] ) . '" alt="" loading="lazy" referrerpolicy="no-referrer">';
		} elseif ( $style['show_avatar'] ) {
			$avatar = '<span class="vxr-avatar vxr-avatar--ph">' . esc_html( mb_substr( $r['author'], 0, 1 ) ) . '</span>';
		}
		$date = '';
		if ( $style['show_date'] && $r['time'] ) {
			$ts = strtotime( $r['time'] );
			if ( $ts ) {
				$date = '<span class="vxr-date">' . esc_html( date_i18n( get_option( 'date_format' ), $ts ) ) . '</span>';
			}
		}
		return sprintf(
			'<div class="vxr-card"><div class="vxr-head">%1$s<div class="vxr-who"><span class="vxr-name">%2$s</span>%3$s</div><span class="vxr-g" title="Google">G</span></div>%4$s<div class="vxr-text">%5$s</div></div>',
			$avatar,
			esc_html( $r['author'] ),
			$date,
			$stars,
			esc_html( $r['text'] )
		);
	}

	/** Turn the style array into CSS custom properties on the wrapper. */
	protected static function css_vars( $s ) {
		$map = array(
			'--vxr-cols'      => (int) $s['columns'],
			'--vxr-card-bg'   => $s['card_bg'],
			'--vxr-radius'    => (int) $s['card_radius'] . 'px',
			'--vxr-pad'       => (int) $s['card_padding'] . 'px',
			'--vxr-gap'       => (int) $s['card_gap'] . 'px',
			'--vxr-text'      => $s['text_color'],
			'--vxr-meta'      => $s['meta_color'],
			'--vxr-star'      => $s['star_color'],
			'--vxr-name-size' => (int) $s['name_size'] . 'px',
			'--vxr-text-size' => (int) $s['text_size'] . 'px',
			'--vxr-avatar'    => (int) $s['avatar_size'] . 'px',
			'--vxr-shadow'    => $s['card_shadow'] ? '0 2px 10px rgba(17,24,39,.08)' : 'none',
		);
		$out = '';
		foreach ( $map as $k => $v ) {
			$out .= $k . ':' . $v . ';';
		}
		return $out;
	}

	/* ------------------------------------------------------------ assets */

	public static function maybe_front_assets() {
		// We can't know before render, so always register; enqueue lazily in footer
		// only if a shortcode ran. Simpler: enqueue when the util is on.
		$ver = defined( 'VELOX_VERSION' ) ? VELOX_VERSION : '1';
		wp_register_style( 'velox-reviews', VELOX_URL . 'assets/reviews.css', array(), $ver );
		wp_register_script( 'velox-reviews', VELOX_URL . 'assets/reviews.js', array(), $ver, true );
		wp_enqueue_style( 'velox-reviews' );
		wp_enqueue_script( 'velox-reviews' );
	}
}
