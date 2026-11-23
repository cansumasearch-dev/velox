<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Velox Shop — the extras around buying: colour swatches, the wishlist,
 * "tell me when it is back", product reviews, cart reminders and the
 * product's structured data for search engines.
 *
 * Everything here is optional (Settings → Storefront) and works on cached
 * pages: what belongs to one visitor travels through the REST API.
 *
 * @package Velox
 */
class Velox_Shop_Extras {

	const WISH   = 'velox_wishlist';      // user meta: [ product id ]
	const ALERTS = '_vx_alerts';          // product meta: [ { e, v, t, ok, h } ]
	const REVIEW = 'velox_review';        // comment type
	const CARTS  = 'velox_shop_carts';    // option: hash => { e, cart, t, sent, user, k }
	const CSTATS = 'velox_shop_cart_stats';

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_action( 'template_redirect', array( __CLASS__, 'links' ), 3 );
		add_action( Velox_Shop::CRON, array( __CLASS__, 'cart_tick' ) );
		add_action( 'wp_head', array( __CLASS__, 'schema' ), 30 );
		add_action( 'wp_body_open', array( __CLASS__, 'msg_top' ), 6 );
		// Reviews are comments of their own type: the theme's comment form and
		// lists stay away from products, and blog widgets never show them.
		add_filter( 'comments_open', array( __CLASS__, 'no_theme_comments' ), 20, 2 );
		add_filter( 'comments_template_query_args', array( __CLASS__, 'hide_reviews' ) );
		add_filter( 'widget_comments_args', array( __CLASS__, 'hide_reviews' ) );
		add_filter( 'wp_count_comments', array( __CLASS__, 'count_comments' ), 10, 2 );
		add_action( 'wp_set_comment_status', array( __CLASS__, 'review_changed' ) );
		add_action( 'deleted_comment', array( __CLASS__, 'review_changed' ) );
		add_action( 'trashed_comment', array( __CLASS__, 'review_changed' ) );
		add_action( 'untrashed_comment', array( __CLASS__, 'review_changed' ) );
	}

	public static function on( $k, $default = '1' ) {
		return '1' === (string) Velox_Shop::get( $k, $default );
	}

	/* ===================================================================== colours */

	/** Colour words customers use, German and English, to a colour. */
	private static function named() {
		return array(
			'schwarz' => '#151515', 'black' => '#151515', 'weiß' => '#ffffff', 'weiss' => '#ffffff', 'white' => '#ffffff', 'grau' => '#8a8a8a', 'gray' => '#8a8a8a', 'grey' => '#8a8a8a',
			'anthrazit' => '#3a3d40', 'silber' => '#c0c3c7', 'silver' => '#c0c3c7', 'gold' => '#c9a227', 'rot' => '#c62828', 'red' => '#c62828', 'bordeaux' => '#6d1a2a', 'weinrot' => '#6d1a2a',
			'rosa' => '#f4b6c2', 'pink' => '#e85d9a', 'rose' => '#f4b6c2', 'lila' => '#7e57c2', 'violett' => '#7e57c2', 'purple' => '#7e57c2', 'flieder' => '#c8a2c8',
			'blau' => '#1e5bb8', 'blue' => '#1e5bb8', 'navy' => '#1d2a4d', 'marine' => '#1d2a4d', 'hellblau' => '#8ec5ef', 'türkis' => '#1aa6a0', 'tuerkis' => '#1aa6a0', 'turquoise' => '#1aa6a0', 'petrol' => '#1f5f6b',
			'grün' => '#2e7d32', 'gruen' => '#2e7d32', 'green' => '#2e7d32', 'oliv' => '#6b6b2a', 'olive' => '#6b6b2a', 'mint' => '#a8dcc5', 'salbei' => '#9caf88', 'sage' => '#9caf88',
			'gelb' => '#f2c400', 'yellow' => '#f2c400', 'senf' => '#c9a227', 'mustard' => '#c9a227', 'orange' => '#ef7d1a', 'koralle' => '#f08072', 'coral' => '#f08072', 'terrakotta' => '#c4643a', 'terracotta' => '#c4643a',
			'braun' => '#6d4c32', 'brown' => '#6d4c32', 'cognac' => '#9a5b2e', 'beige' => '#e3d5bd', 'sand' => '#d9c7a5', 'creme' => '#f3ead7', 'cream' => '#f3ead7', 'natur' => '#efe6d2', 'natural' => '#efe6d2', 'taupe' => '#8b7d6b',
		);
	}

	/** Is this option about colour? */
	public static function is_color_option( $name ) {
		return (bool) preg_match( '/farb|colou?r|couleur|kolor/iu', (string) $name );
	}

	/** The colour for an option value: set in the product, else from the word. */
	public static function color_of( $opt, $value ) {
		$set = (array) ( $opt['colors'] ?? array() );
		if ( ! empty( $set[ $value ] ) && preg_match( '/^#[0-9a-f]{6}$/i', $set[ $value ] ) ) {
			return $set[ $value ];
		}
		$words = self::named();
		$v     = mb_strtolower( trim( (string) $value ) );
		if ( isset( $words[ $v ] ) ) {
			return $words[ $v ];
		}
		foreach ( preg_split( '/[\s\/\-]+/u', $v ) as $w ) {
			if ( isset( $words[ $w ] ) ) {
				return $words[ $w ];
			}
		}
		return '';
	}

	/** Colour dots for a product card (up to six), each with its variant's photo for hover. */
	public static function card_swatches( $p ) {
		if ( ! self::on( 'card_swatches' ) || ! Velox_Shop::has_variants( $p ) ) {
			return '';
		}
		foreach ( $p['options'] as $i => $opt ) {
			if ( ! self::is_color_option( $opt['name'] ) ) {
				continue;
			}
			$vals = array();
			foreach ( $p['variants'] as $v ) {
				if ( ! empty( $v['off'] ) || ! isset( $v['opts'][ $i ] ) ) {
					continue;
				}
				$val = (string) $v['opts'][ $i ];
				if ( ! isset( $vals[ $val ] ) ) {
					$vals[ $val ] = ! empty( $v['image'] ) ? (string) wp_get_attachment_image_url( (int) $v['image'], 'large' ) : '';
				}
			}
			if ( count( $vals ) < 2 ) {
				return '';
			}
			$out = '';
			$n   = 0;
			foreach ( $vals as $val => $img ) {
				if ( ++$n > 6 ) {
					break;
				}
				$c    = self::color_of( $opt, $val );
				$out .= '<span class="vxs-sw' . ( $c ? '' : ' is-txt' ) . '"' . ( $c ? ' style="--c:' . esc_attr( $c ) . '"' : '' ) . ( $img ? ' data-vxs-swimg="' . esc_url( $img ) . '"' : '' ) . ' title="' . esc_attr( $val ) . '">' . ( $c ? '' : esc_html( mb_substr( $val, 0, 3 ) ) ) . '</span>';
			}
			$more = count( $vals ) - 6;
			return '<div class="vxs-sws"><span class="screen-reader-text">' . esc_html( $opt['name'] . ': ' . implode( ', ', array_keys( $vals ) ) ) . '</span>' . $out . ( $more > 0 ? '<span class="vxs-sw-more" aria-hidden="true">+' . (int) $more . '</span>' : '' ) . '</div>';
		}
		return '';
	}

	/* ===================================================================== wishlist */

	public static function wish_ids( $user_id = 0 ) {
		$user_id = $user_id ? $user_id : get_current_user_id();
		if ( ! $user_id ) {
			return array();
		}
		return array_values( array_unique( array_filter( array_map( 'intval', (array) get_user_meta( $user_id, self::WISH, true ) ) ) ) );
	}

	/** The heart: on cards it sits on the photo, on the product page beside the button. */
	public static function heart( $p, $pdp = false ) {
		if ( ! self::on( 'wishlist' ) ) {
			return '';
		}
		/* translators: %s: product name */
		$label = sprintf( __( 'Save %s to your wishlist', 'velox' ), $p['title'] );
		return '<button type="button" class="vxs-wish' . ( $pdp ? ' vxs-wish-pdp' : '' ) . '" data-vxs-wish="' . (int) $p['id'] . '" aria-pressed="false" aria-label="' . esc_attr( $label ) . '" title="' . esc_attr__( 'Wishlist', 'velox' ) . '"><svg viewBox="0 0 24 24" width="' . ( $pdp ? 22 : 18 ) . '" height="' . ( $pdp ? 22 : 18 ) . '" aria-hidden="true"><path d="M12 20.5s-7.5-4.6-7.5-10.4A4.4 4.4 0 0 1 12 7.3a4.4 4.4 0 0 1 7.5 2.8c0 5.8-7.5 10.4-7.5 10.4z"/></svg></button>';
	}

	/** The wishlist as product cards (account tab, or for guests by REST). */
	public static function wish_grid( $ids ) {
		$ids = array_values( array_filter( array_map( 'intval', (array) $ids ), function ( $id ) { return Velox_Shop::PRODUCT === get_post_type( $id ) && 'publish' === get_post_status( $id ); } ) );
		if ( ! $ids ) {
			return '<div class="vxs-wish-empty"><svg viewBox="0 0 24 24" width="28" height="28" aria-hidden="true"><path d="M12 20.5s-7.5-4.6-7.5-10.4A4.4 4.4 0 0 1 12 7.3a4.4 4.4 0 0 1 7.5 2.8c0 5.8-7.5 10.4-7.5 10.4z"/></svg><b>' . esc_html__( 'Nothing saved yet', 'velox' ) . '</b><p>' . esc_html__( 'Tap the heart on a product to keep it here for later.', 'velox' ) . '</p><a class="vxs-btn vxs-btn-sm" href="' . esc_url( Velox_Shop_Front::page_url( 'shop', home_url( '/' ) ) ) . '">' . esc_html__( 'Go to the shop', 'velox' ) . '</a></div>';
		}
		return Velox_Shop_Front::grid( array( 'ids' => implode( ',', array_slice( $ids, 0, 60 ) ), 'count' => 60, 'columns' => '', 'category' => '', 'sort' => 'menu', 'filter' => '' ) );
	}

	/* ===================================================================== back in stock */

	private static function alerts( $pid ) {
		$a = get_post_meta( $pid, self::ALERTS, true );
		return is_array( $a ) ? $a : array();
	}

	/** The "tell me" form under a sold-out product (or a sold-out variant, shown by velox-shop.js). */
	public static function alert_form( $p, $show ) {
		if ( ! self::on( 'stock_alerts' ) || 'inquiry' === $p['mode'] ) {
			return '';
		}
		$u    = wp_get_current_user();
		$priv = (int) Velox_Shop::get( 'page_privacy' );
		return '<form class="vxs-alert" data-vxs-alert' . ( $show ? '' : ' hidden' ) . ' method="post">'
			. '<input type="hidden" name="product" value="' . (int) $p['id'] . '"><input type="hidden" name="variant" value="">'
			. '<p class="vxs-alert-t"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 16V11a6 6 0 0 1 12 0v5l1.5 2h-15z"/><path d="M10 20.5a2 2 0 0 0 4 0"/></svg><b>' . esc_html__( 'Tell me when it is back', 'velox' ) . '</b></p>'
			. '<div class="vxs-alert-row"><label class="screen-reader-text" for="vxs-al-' . (int) $p['id'] . '">' . esc_html__( 'Email', 'velox' ) . '</label><input id="vxs-al-' . (int) $p['id'] . '" type="email" name="email" required autocomplete="email" value="' . esc_attr( $u && $u->ID ? $u->user_email : '' ) . '" placeholder="' . esc_attr__( 'Your email', 'velox' ) . '"><button type="submit" class="vxs-btn">' . esc_html__( 'Notify me', 'velox' ) . '</button></div>'
			. '<input type="text" name="website" value="" tabindex="-1" autocomplete="off" class="vxs-hp" aria-hidden="true">'
			. '<label class="vxs-check vxs-alert-ok"><input type="checkbox" name="consent" value="1" required> <span>' . ( $priv
				? sprintf( /* translators: %s: privacy policy link */ esc_html__( 'Email me once when it is available again. My address is used for nothing else and deleted afterwards (%s).', 'velox' ), '<a href="' . esc_url( get_permalink( $priv ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'privacy policy', 'velox' ) . '</a>' )
				: esc_html__( 'Email me once when it is available again. My address is used for nothing else and deleted afterwards.', 'velox' ) ) . '</span></label>'
			. '<p class="vxs-alert-msg" role="status" aria-live="polite"></p></form>';
	}

	/** Sign up; double opt-in, unless a signed-in customer uses their own address. */
	public static function alert_add( $pid, $vid, $email ) {
		$p = Velox_Shop::product( $pid );
		if ( ! $p || ! self::on( 'stock_alerts' ) ) {
			return new WP_Error( 'x', __( 'This product does not exist.', 'velox' ) );
		}
		$email = sanitize_email( $email );
		if ( ! is_email( $email ) ) {
			return new WP_Error( 'x', __( 'Please enter a valid email address.', 'velox' ) );
		}
		$v = Velox_Shop::variant( $p, $vid );
		if ( Velox_Shop::can_buy( $p, $v, 1 ) ) {
			return new WP_Error( 'x', __( 'Good news — it is available right now.', 'velox' ) );
		}
		$list = array_filter( self::alerts( $pid ), function ( $a ) { return ! empty( $a['ok'] ) || (int) $a['t'] > time() - 7 * DAY_IN_SECONDS; } );
		if ( count( $list ) >= 500 ) {
			return new WP_Error( 'x', __( 'Please try again later.', 'velox' ) );
		}
		$u     = wp_get_current_user();
		$trust = $u && $u->ID && 0 === strcasecmp( $u->user_email, $email );
		$tok   = wp_generate_password( 24, false, false );
		$key   = md5( strtolower( $email ) . '|' . ( $v ? $v['id'] : '' ) );
		$list[ $key ] = array( 'e' => $email, 'v' => $v ? (string) $v['id'] : '', 't' => time(), 'ok' => $trust ? 1 : 0, 'h' => hash( 'sha256', $tok ) );
		update_post_meta( $pid, self::ALERTS, $list );
		if ( $trust ) {
			return __( 'Done — we email you as soon as it is back.', 'velox' );
		}
		$store = (string) Velox_Shop::get( 'store_name', get_bloginfo( 'name' ) );
		$name  = $p['title'] . ( $v ? ' (' . implode( ', ', $v['opts'] ) . ')' : '' );
		$title = __( 'Please confirm your notification', 'velox' );
		/* translators: %s: product name */
		$body  = '<h1 style="margin:0 0 12px;font-size:22px">' . esc_html( $title ) . '</h1><p style="margin:0">' . esc_html( sprintf( __( 'You asked us to tell you when “%s” is available again. Please confirm with the button — then we send you exactly one email when it is back.', 'velox' ), $name ) ) . '</p>'
			. Velox_Shop::mail_button( add_query_arg( array( 'vxs_alert' => 'on', 'p' => $pid, 'k' => $key, 't' => $tok ), home_url( '/' ) ), __( 'Yes, notify me', 'velox' ) )
			. '<p style="margin:22px 0 0;color:#6b7280;font-size:13px">' . esc_html__( 'If you did not ask for this, ignore this email — you will not hear from us.', 'velox' ) . '</p>';
		wp_mail( $email, $title . ' — ' . $store, Velox_Shop::mail_layout( $body, $title ), array( 'Content-Type: text/html; charset=UTF-8' ) );
		return __( 'Almost done: please confirm the link we just emailed you.', 'velox' );
	}

	/** Called whenever a product's stock or settings change: tell whoever waits. */
	public static function alerts_check( $pid ) {
		$list = self::alerts( $pid );
		if ( ! $list ) {
			return;
		}
		$p = Velox_Shop::product( $pid );
		if ( ! $p ) {
			return;
		}
		$store   = (string) Velox_Shop::get( 'store_name', get_bloginfo( 'name' ) );
		$changed = false;
		foreach ( $list as $k => $a ) {
			if ( empty( $a['ok'] ) ) {
				if ( (int) $a['t'] < time() - 7 * DAY_IN_SECONDS ) {
					unset( $list[ $k ] );
					$changed = true;
				}
				continue;
			}
			$v = '' !== (string) $a['v'] ? Velox_Shop::variant( $p, $a['v'] ) : null;
			if ( ( '' !== (string) $a['v'] && ! $v ) || ! Velox_Shop::can_buy( $p, $v, 1 ) ) {
				continue;
			}
			$name  = $p['title'] . ( $v ? ' (' . implode( ', ', $v['opts'] ) . ')' : '' );
			/* translators: %s: product name */
			$title = sprintf( __( '%s is available again', 'velox' ), $name );
			$img   = get_the_post_thumbnail_url( $pid, 'medium' );
			$body  = '<h1 style="margin:0 0 12px;font-size:22px">' . esc_html( $title ) . '</h1>'
				. ( $img ? '<p style="margin:0 0 14px"><img src="' . esc_url( $img ) . '" alt="" width="240" style="max-width:240px;height:auto;border-radius:8px"></p>' : '' )
				. '<p style="margin:0">' . esc_html__( 'You asked us to tell you: it is back in the shop. Stock is limited, so do not wait too long.', 'velox' ) . '</p>'
				. Velox_Shop::mail_button( get_permalink( $pid ), __( 'View it now', 'velox' ) )
				. '<p style="margin:22px 0 0;color:#6b7280;font-size:13px">' . esc_html__( 'This was the one email you asked for; your address has been deleted.', 'velox' ) . '</p>';
			wp_mail( $a['e'], $title . ' — ' . $store, Velox_Shop::mail_layout( $body, $title ), array( 'Content-Type: text/html; charset=UTF-8' ) );
			unset( $list[ $k ] );
			$changed = true;
		}
		if ( $changed ) {
			update_post_meta( $pid, self::ALERTS, $list );
		}
	}

	/** How many confirmed notifications wait for a product (shown in the editor). */
	public static function alerts_waiting( $pid ) {
		return count( array_filter( self::alerts( $pid ), function ( $a ) { return ! empty( $a['ok'] ); } ) );
	}

	/* ===================================================================== reviews */

	public static function no_theme_comments( $open, $post_id ) {
		return Velox_Shop::PRODUCT === get_post_type( $post_id ) ? false : $open;
	}

	public static function hide_reviews( $args ) {
		$args['type__not_in'] = array_merge( (array) ( $args['type__not_in'] ?? array() ), array( self::REVIEW ) );
		return $args;
	}

	/**
	 * WordPress's "Comments (1)" badge counts every comment type. Reviews have
	 * their own place (Velox Shop → Reviews), so the site-wide count leaves them out.
	 */
	public static function count_comments( $stats, $post_id ) {
		if ( $post_id || ! empty( $stats ) ) {
			return $stats;
		}
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT comment_approved, COUNT(*) AS n FROM {$wpdb->comments} WHERE comment_type NOT IN ( %s, 'note' ) GROUP BY comment_approved", self::REVIEW ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$map  = array( '0' => 'moderated', '1' => 'approved', 'spam' => 'spam', 'trash' => 'trash', 'post-trashed' => 'post-trashed' );
		$out  = array_fill_keys( array( 'approved', 'moderated', 'spam', 'trash', 'post-trashed' ), 0 );
		foreach ( (array) $rows as $r ) {
			if ( isset( $map[ $r['comment_approved'] ] ) ) {
				$out[ $map[ $r['comment_approved'] ] ] = (int) $r['n'];
			}
		}
		$out['total_comments'] = $out['approved'] + $out['moderated'] + $out['spam'];
		$out['all']            = $out['approved'] + $out['moderated'];
		return (object) $out;
	}

	/** [ average, count ] of the approved reviews (kept in post meta). */
	public static function rating( $pid ) {
		return array( (float) get_post_meta( $pid, '_vx_rating', true ), (int) get_post_meta( $pid, '_vx_reviews', true ) );
	}

	public static function refresh_rating( $pid ) {
		$c   = get_comments( array( 'post_id' => $pid, 'type' => self::REVIEW, 'status' => 'approve', 'fields' => 'ids', 'number' => 0 ) );
		$sum = 0;
		foreach ( $c as $cid ) {
			$sum += max( 1, min( 5, (int) get_comment_meta( $cid, 'vx_rating', true ) ) );
		}
		update_post_meta( $pid, '_vx_reviews', count( $c ) );
		update_post_meta( $pid, '_vx_rating', $c ? round( $sum / count( $c ), 2 ) : 0 );
	}

	public static function review_changed( $cid ) {
		$c = get_comment( $cid );
		if ( $c && self::REVIEW === $c->comment_type ) {
			self::refresh_rating( (int) $c->comment_post_ID );
		}
	}

	public static function stars( $avg, $size = 15 ) {
		$out = '';
		for ( $i = 1; $i <= 5; $i++ ) {
			$fill = max( 0, min( 1, $avg - $i + 1 ) );
			$out .= '<i style="--f:' . round( $fill * 100 ) . '%"></i>';
		}
		/* translators: %s: rating like 4.5 */
		return '<span class="vxs-stars" style="--s:' . (int) $size . 'px" role="img" aria-label="' . esc_attr( sprintf( __( '%s out of 5 stars', 'velox' ), number_format_i18n( $avg, 1 ) ) ) . '">' . $out . '</span>';
	}

	/** Stars and count under a card's name. */
	public static function card_rating( $p ) {
		if ( ! self::on( 'reviews' ) || ! self::on( 'card_rating' ) ) {
			return '';
		}
		list( $avg, $n ) = self::rating( $p['id'] );
		return $n ? '<div class="vxs-card-rate">' . self::stars( $avg, 13 ) . '<span>(' . (int) $n . ')</span></div>' : '';
	}

	/** Under the product title: stars, average, link to the reviews. */
	public static function title_rating( $p ) {
		if ( ! self::on( 'reviews' ) ) {
			return '';
		}
		list( $avg, $n ) = self::rating( $p['id'] );
		if ( ! $n ) {
			return '<a class="vxs-pdp-rate is-none" href="#vxs-reviews" data-vxs-rvopen>' . esc_html__( 'Be the first to review', 'velox' ) . '</a>';
		}
		/* translators: %d: number of reviews */
		return '<a class="vxs-pdp-rate" href="#vxs-reviews">' . self::stars( $avg, 16 ) . '<b>' . esc_html( number_format_i18n( $avg, 1 ) ) . '</b><span>' . esc_html( sprintf( _n( '%d review', '%d reviews', $n, 'velox' ), $n ) ) . '</span></a>';
	}

	/** Did this address buy (and pay for) this product? */
	public static function bought( $email, $pid ) {
		if ( ! is_email( $email ) ) {
			return false;
		}
		$ids = get_posts( array( 'post_type' => Velox_Shop::ORDER, 'post_status' => 'publish', 'numberposts' => 50, 'fields' => 'ids', 'meta_query' => array( array( 'key' => '_vx_email', 'value' => $email ), array( 'key' => '_vxs_pay', 'value' => array( 'paid', 'partial' ), 'compare' => 'IN' ) ) ) );
		foreach ( $ids as $oid ) {
			$o = Velox_Shop::order( $oid );
			foreach ( (array) ( $o['items'] ?? array() ) as $i ) {
				if ( (int) ( $i['pid'] ?? 0 ) === (int) $pid ) {
					return true;
				}
			}
		}
		return false;
	}

	/** The reviews section on a product page: summary, how reviews are checked, list, form. */
	public static function reviews_html( $p ) {
		if ( ! self::on( 'reviews' ) ) {
			return '';
		}
		list( $avg, $n ) = self::rating( $p['id'] );
		$list = get_comments( array( 'post_id' => $p['id'], 'type' => self::REVIEW, 'status' => 'approve', 'number' => 30, 'orderby' => 'comment_date_gmt', 'order' => 'DESC' ) );
		$dist = array( 5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0 );
		foreach ( get_comments( array( 'post_id' => $p['id'], 'type' => self::REVIEW, 'status' => 'approve', 'fields' => 'ids', 'number' => 0 ) ) as $cid ) {
			$dist[ max( 1, min( 5, (int) get_comment_meta( $cid, 'vx_rating', true ) ) ) ]++;
		}
		$sum = '<div class="vxs-rv-sum">';
		if ( $n ) {
			/* translators: %d: number of reviews */
			$sum .= '<div class="vxs-rv-avg"><b>' . esc_html( number_format_i18n( $avg, 1 ) ) . '</b>' . self::stars( $avg, 18 ) . '<span>' . esc_html( sprintf( _n( '%d review', '%d reviews', $n, 'velox' ), $n ) ) . '</span></div><ul class="vxs-rv-dist">';
			foreach ( $dist as $s => $c ) {
				$sum .= '<li><span>' . (int) $s . '</span><i><em style="width:' . ( $n ? round( $c / $n * 100 ) : 0 ) . '%"></em></i><small>' . (int) $c . '</small></li>';
			}
			$sum .= '</ul>';
		} else {
			$sum .= '<p class="vxs-rv-none">' . esc_html__( 'No reviews yet. Tell others what you think of it.', 'velox' ) . '</p>';
		}
		$sum .= '</div>';
		// The law (§ 5b UWG) wants shops to say whether and how reviews are checked.
		$how = self::on( 'reviews_verified', '0' )
			? __( 'How we check reviews: only customers who bought this product with us can write one; we match the email address against our orders.', 'velox' )
			: __( 'How we check reviews: anyone can write one. Reviews marked “Verified purchase” come from an email address we found in a paid order for this product.', 'velox' );
		$items = '';
		foreach ( $list as $c ) {
			$r     = max( 1, min( 5, (int) get_comment_meta( $c->comment_ID, 'vx_rating', true ) ) );
			$t     = (string) get_comment_meta( $c->comment_ID, 'vx_title', true );
			$reply = (string) get_comment_meta( $c->comment_ID, 'vx_reply', true );
			$items .= '<li class="vxs-rv">' . self::stars( $r, 14 ) . ( '' !== $t ? '<h3>' . esc_html( $t ) . '</h3>' : '' ) . '<div class="vxs-rv-text">' . wpautop( esc_html( $c->comment_content ) ) . '</div>'
				. '<p class="vxs-rv-by"><b>' . esc_html( $c->comment_author ) . '</b> · ' . esc_html( Velox_Shop::date( strtotime( $c->comment_date_gmt . ' UTC' ) ) ) . ( get_comment_meta( $c->comment_ID, 'vx_verified', true ) ? ' · <span class="vxs-rv-ver">✓ ' . esc_html__( 'Verified purchase', 'velox' ) . '</span>' : '' ) . '</p>'
				. ( '' !== $reply ? '<div class="vxs-rv-reply"><b>' . esc_html( sprintf( /* translators: %s: shop name */ __( 'Reply from %s', 'velox' ), (string) Velox_Shop::get( 'store_name', get_bloginfo( 'name' ) ) ) ) . '</b>' . wpautop( esc_html( $reply ) ) . '</div>' : '' ) . '</li>';
		}
		$u    = wp_get_current_user();
		$priv = (int) Velox_Shop::get( 'page_privacy' );
		$star = '';
		for ( $i = 5; $i >= 1; $i-- ) {
			/* translators: %d: number of stars */
			$star .= '<input type="radio" id="vxs-rs' . $i . '" name="rating" value="' . $i . '" required><label for="vxs-rs' . $i . '" title="' . esc_attr( sprintf( _n( '%d star', '%d stars', $i, 'velox' ), $i ) ) . '"><span class="screen-reader-text">' . esc_html( sprintf( _n( '%d star', '%d stars', $i, 'velox' ), $i ) ) . '</span></label>';
		}
		$form = '<details class="vxs-rv-write"><summary class="vxs-btn vxs-btn-ghost">' . esc_html__( 'Write a review', 'velox' ) . '</summary>'
			. '<form class="vxs-rv-form" method="post" data-vxs-review><input type="hidden" name="vxs_review" value="1"><input type="hidden" name="product" value="' . (int) $p['id'] . '">'
			. '<fieldset class="vxs-rv-stars"><legend>' . esc_html__( 'Your rating', 'velox' ) . '</legend><div>' . $star . '</div></fieldset>'
			. '<label class="vxs-f"><span>' . esc_html__( 'Title (optional)', 'velox' ) . '</span><input type="text" name="title" maxlength="80"></label>'
			. '<label class="vxs-f"><span>' . esc_html__( 'Your review', 'velox' ) . '</span><textarea name="text" rows="4" required minlength="10" maxlength="2000"></textarea></label>'
			. '<div class="vxs-fg vxs-fg-2"><label class="vxs-f"><span>' . esc_html__( 'Name (shown)', 'velox' ) . '</span><input type="text" name="name" required maxlength="40" value="' . esc_attr( $u && $u->ID ? trim( $u->first_name . ' ' . mb_substr( (string) $u->last_name, 0, 1 ) . ( $u->last_name ? '.' : '' ) ) : '' ) . '" autocomplete="nickname"></label>'
			. '<label class="vxs-f"><span>' . esc_html__( 'Email (not shown)', 'velox' ) . '</span><input type="email" name="email" required value="' . esc_attr( $u && $u->ID ? $u->user_email : '' ) . '" autocomplete="email"></label></div>'
			. '<input type="text" name="website" value="" tabindex="-1" autocomplete="off" class="vxs-hp" aria-hidden="true">'
			. '<label class="vxs-check"><input type="checkbox" name="consent" value="1" required> <span>' . ( $priv
				? sprintf( /* translators: %s: privacy policy link */ esc_html__( 'My review and the name above may be published. The email is only used to check it (%s).', 'velox' ), '<a href="' . esc_url( get_permalink( $priv ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'privacy policy', 'velox' ) . '</a>' )
				: esc_html__( 'My review and the name above may be published. The email is only used to check it.', 'velox' ) ) . '</span></label>'
			. '<button type="submit" class="vxs-btn">' . esc_html__( 'Send review', 'velox' ) . '</button><p class="vxs-rv-msg" role="status" aria-live="polite"></p></form></details>';
		return '<section class="vxs-reviews" id="vxs-reviews"><h2>' . esc_html__( 'Reviews', 'velox' ) . '</h2>' . $sum . '<p class="vxs-rv-how">' . esc_html( $how ) . '</p>' . $form . ( $items ? '<ul class="vxs-rv-list">' . $items . '</ul>' : '' ) . '</section>';
	}

	/** A new review from the form (REST or plain post). Returns a message or WP_Error. */
	public static function review_add( $in ) {
		if ( ! self::on( 'reviews' ) ) {
			return new WP_Error( 'x', __( 'Reviews are switched off.', 'velox' ) );
		}
		if ( ! empty( $in['website'] ) ) {
			return __( 'Thank you!', 'velox' ); // a bot filled the hidden field
		}
		$pid    = (int) ( $in['product'] ?? 0 );
		$p      = Velox_Shop::product( $pid );
		$rating = (int) ( $in['rating'] ?? 0 );
		$text   = trim( sanitize_textarea_field( (string) ( $in['text'] ?? '' ) ) );
		$name   = trim( sanitize_text_field( (string) ( $in['name'] ?? '' ) ) );
		$email  = sanitize_email( (string) ( $in['email'] ?? '' ) );
		if ( ! $p || 'publish' !== $p['status'] ) {
			return new WP_Error( 'x', __( 'This product does not exist.', 'velox' ) );
		}
		if ( $rating < 1 || $rating > 5 ) {
			return new WP_Error( 'x', __( 'Please choose 1 to 5 stars.', 'velox' ) );
		}
		if ( mb_strlen( $text ) < 10 ) {
			return new WP_Error( 'x', __( 'Please write a few words (at least 10 characters).', 'velox' ) );
		}
		if ( '' === $name || ! is_email( $email ) ) {
			return new WP_Error( 'x', __( 'Please enter your name and a valid email address.', 'velox' ) );
		}
		if ( empty( $in['consent'] ) ) {
			return new WP_Error( 'x', __( 'Please confirm that your review may be published.', 'velox' ) );
		}
		$k = 'velox_rv_' . md5( Velox_Shop_Account::visitor_ip() );
		if ( (int) get_transient( $k ) >= 5 ) {
			return new WP_Error( 'x', __( 'Please try again later.', 'velox' ) );
		}
		set_transient( $k, (int) get_transient( $k ) + 1, HOUR_IN_SECONDS );
		$dupe = get_comments( array( 'post_id' => $pid, 'type' => self::REVIEW, 'author_email' => $email, 'count' => true, 'status' => 'all' ) );
		if ( $dupe ) {
			return new WP_Error( 'x', __( 'You have already reviewed this product. Thank you!', 'velox' ) );
		}
		$verified = self::bought( $email, $pid );
		if ( self::on( 'reviews_verified', '0' ) && ! $verified ) {
			return new WP_Error( 'x', __( 'Only customers who bought this product can review it. Use the email address from your order.', 'velox' ) );
		}
		$approve = self::on( 'reviews_approve' ) ? 0 : 1;
		$cid = wp_insert_comment( array(
			'comment_post_ID'      => $pid,
			'comment_author'       => mb_substr( $name, 0, 40 ),
			'comment_author_email' => $email,
			'comment_author_IP'    => Velox_Shop_Account::visitor_ip(),
			'comment_content'      => mb_substr( $text, 0, 2000 ),
			'comment_type'         => self::REVIEW,
			'comment_approved'     => $approve,
			'user_id'              => get_current_user_id(),
		) );
		if ( ! $cid ) {
			return new WP_Error( 'x', __( 'The review could not be saved. Please try again.', 'velox' ) );
		}
		add_comment_meta( $cid, 'vx_rating', $rating );
		add_comment_meta( $cid, 'vx_title', mb_substr( sanitize_text_field( (string) ( $in['title'] ?? '' ) ), 0, 80 ) );
		add_comment_meta( $cid, 'vx_verified', $verified ? 1 : 0 );
		self::refresh_rating( $pid );
		delete_transient( 'velox_shop_badge_rv' );
		return $approve ? __( 'Thank you! Your review is online.', 'velox' ) : __( 'Thank you! Your review appears as soon as we have checked it.', 'velox' );
	}

	public static function pending_reviews() {
		$n = get_transient( 'velox_shop_badge_rv' );
		if ( false === $n ) {
			$n = (int) get_comments( array( 'type' => self::REVIEW, 'status' => 'hold', 'count' => true ) );
			set_transient( 'velox_shop_badge_rv', $n, 5 * MINUTE_IN_SECONDS );
		}
		return (int) $n;
	}

	/* ===================================================================== cart reminders */

	private static function carts() {
		$c = get_option( self::CARTS, array() );
		return is_array( $c ) ? $c : array();
	}

	/**
	 * Remember the cart for one reminder — only after the customer ticked the
	 * box at checkout (in Germany, advertising email needs consent).
	 */
	public static function cart_remember( $email, $consent ) {
		if ( ! self::on( 'cart_remind', '0' ) ) {
			return false;
		}
		$email = sanitize_email( $email );
		if ( ! is_email( $email ) ) {
			return false;
		}
		$list = self::carts();
		$key  = hash( 'sha256', strtolower( $email ) );
		if ( ! $consent ) {
			unset( $list[ $key ] );
			update_option( self::CARTS, $list, false );
			return true;
		}
		$cart = Velox_Shop::get_cart();
		if ( ! array_filter( array_keys( $cart ), function ( $k ) { return '_c' !== $k; } ) ) {
			return false;
		}
		$old = $list[ $key ] ?? array();
		$list[ $key ] = array( 'e' => $email, 'cart' => $cart, 't' => time(), 'sent' => (int) ( $old['sent'] ?? 0 ), 'user' => get_current_user_id(), 'k' => $old['k'] ?? wp_generate_password( 24, false, false ) );
		update_option( self::CARTS, array_slice( $list, -2000, null, true ), false );
		return true;
	}

	/** An order with this address arrived: no reminder; count it if a reminder had gone out. */
	public static function cart_done( $o ) {
		$key  = hash( 'sha256', strtolower( (string) ( $o['customer']['email'] ?? '' ) ) );
		$list = self::carts();
		if ( ! isset( $list[ $key ] ) ) {
			return;
		}
		if ( ! empty( $list[ $key ]['sent'] ) ) {
			$s = get_option( self::CSTATS, array() );
			$s['recovered'] = (int) ( $s['recovered'] ?? 0 ) + 1;
			$s['revenue']   = round( (float) ( $s['revenue'] ?? 0 ) + (float) $o['total'], 2 );
			update_option( self::CSTATS, $s, false );
		}
		unset( $list[ $key ] );
		update_option( self::CARTS, $list, false );
	}

	/** Hourly: one reminder per cart after the chosen time, old entries removed. */
	public static function cart_tick() {
		if ( ! self::on( 'cart_remind', '0' ) ) {
			return;
		}
		$list  = self::carts();
		$wait  = max( 1, min( 72, (int) Velox_Shop::get( 'cart_remind_hours', 3 ) ) ) * HOUR_IN_SECONDS;
		$store = (string) Velox_Shop::get( 'store_name', get_bloginfo( 'name' ) );
		$sent  = 0;
		foreach ( $list as $key => $c ) {
			if ( (int) $c['t'] < time() - 7 * DAY_IN_SECONDS ) {
				unset( $list[ $key ] );
				continue;
			}
			if ( ! empty( $c['sent'] ) || (int) $c['t'] > time() - $wait || $sent >= 50 ) {
				continue;
			}
			$lines = Velox_Shop::lines( $c['cart'] );
			if ( ! $lines ) {
				unset( $list[ $key ] );
				continue;
			}
			$rows = '';
			foreach ( $lines as $l ) {
				$rows .= '<tr><td style="padding:8px 0;border-bottom:1px solid #eee">' . ( $l['thumb'] ? '<img src="' . esc_url( $l['thumb'] ) . '" alt="" width="48" height="48" style="border-radius:6px;vertical-align:middle;margin-right:10px">' : '' ) . esc_html( $l['qty'] . ' × ' . $l['name'] . ( $l['variant'] ? ' (' . $l['variant'] . ')' : '' ) ) . '</td><td style="padding:8px 0;border-bottom:1px solid #eee;text-align:right;white-space:nowrap">' . Velox_Shop::money( $l['total'] ) . '</td></tr>';
			}
			$code  = (string) Velox_Shop::get( 'cart_remind_coupon', '' );
			$title = __( 'Your cart is waiting for you', 'velox' );
			$body  = '<h1 style="margin:0 0 12px;font-size:22px">' . esc_html( $title ) . '</h1><p style="margin:0 0 14px">' . esc_html__( 'You left a few things in your cart. We kept them for you — continue right where you stopped:', 'velox' ) . '</p>'
				. '<table role="presentation" width="100%" style="border-collapse:collapse;font-size:14px">' . $rows . '</table>'
				. ( '' !== $code ? '<p style="margin:16px 0 0;padding:12px 14px;border-radius:8px;background:#f5f5f4">' . sprintf( /* translators: %s: coupon code */ esc_html__( 'With the code %s you get a little thank-you at checkout.', 'velox' ), '<b style="font-family:monospace;font-size:15px">' . esc_html( $code ) . '</b>' ) . '</p>' : '' )
				. Velox_Shop::mail_button( add_query_arg( array( 'vxs_cart' => $c['k'] ), home_url( '/' ) ), __( 'Complete my order', 'velox' ) )
				. '<p style="margin:22px 0 0;color:#6b7280;font-size:13px">' . esc_html__( 'You get this one reminder because you ticked the box at checkout. We will not send another.', 'velox' ) . ' <a href="' . esc_url( add_query_arg( array( 'vxs_cart_off' => $c['k'] ), home_url( '/' ) ) ) . '" style="color:#6b7280">' . esc_html__( 'Forget my cart', 'velox' ) . '</a></p>';
			wp_mail( $c['e'], $title . ' — ' . $store, Velox_Shop::mail_layout( $body, $title ), array( 'Content-Type: text/html; charset=UTF-8' ) );
			$list[ $key ]['sent'] = time();
			$sent++;
		}
		update_option( self::CARTS, $list, false );
		if ( $sent ) {
			$s         = get_option( self::CSTATS, array() );
			$s['sent'] = (int) ( $s['sent'] ?? 0 ) + $sent;
			update_option( self::CSTATS, $s, false );
		}
	}

	public static function cart_stats() {
		$s = get_option( self::CSTATS, array() );
		return array( 'sent' => (int) ( $s['sent'] ?? 0 ), 'recovered' => (int) ( $s['recovered'] ?? 0 ), 'revenue' => (float) ( $s['revenue'] ?? 0 ), 'waiting' => count( array_filter( self::carts(), function ( $c ) { return empty( $c['sent'] ); } ) ) );
	}

	/* ===================================================================== links from emails */

	public static function links() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- tokens from emails are the proof
		if ( isset( $_GET['vxs_alert'], $_GET['p'], $_GET['k'], $_GET['t'] ) ) {
			$pid  = (int) $_GET['p'];
			$k    = preg_replace( '/[^a-f0-9]/', '', (string) wp_unslash( $_GET['k'] ) );
			$t    = preg_replace( '/[^A-Za-z0-9]/', '', (string) wp_unslash( $_GET['t'] ) );
			$list = self::alerts( $pid );
			$ok   = isset( $list[ $k ] ) && hash_equals( (string) $list[ $k ]['h'], hash( 'sha256', $t ) );
			if ( $ok && 'on' === $_GET['vxs_alert'] ) {
				$list[ $k ]['ok'] = 1;
				update_post_meta( $pid, self::ALERTS, $list );
			} elseif ( $ok ) {
				unset( $list[ $k ] );
				update_post_meta( $pid, self::ALERTS, $list );
			}
			wp_safe_redirect( add_query_arg( 'vxs_msg', $ok ? ( 'on' === $_GET['vxs_alert'] ? 'alert-on' : 'alert-off' ) : 'link-bad', get_permalink( $pid ) ? get_permalink( $pid ) : home_url( '/' ) ) );
			exit;
		}
		foreach ( array( 'vxs_cart', 'vxs_cart_off' ) as $g ) {
			if ( ! isset( $_GET[ $g ] ) ) {
				continue;
			}
			$k = preg_replace( '/[^A-Za-z0-9]/', '', (string) wp_unslash( $_GET[ $g ] ) );
			$list = self::carts();
			foreach ( $list as $key => $c ) {
				if ( '' !== $k && hash_equals( (string) $c['k'], $k ) ) {
					if ( 'vxs_cart' === $g ) {
						Velox_Shop::save_cart( $c['cart'] );
						wp_safe_redirect( Velox_Shop_Front::page_url( 'checkout', home_url( '/' ) ) );
						exit;
					}
					unset( $list[ $key ] );
					update_option( self::CARTS, $list, false );
					wp_safe_redirect( add_query_arg( 'vxs_msg', 'cart-off', home_url( '/' ) ) );
					exit;
				}
			}
			wp_safe_redirect( add_query_arg( 'vxs_msg', 'link-bad', Velox_Shop_Front::page_url( 'shop', home_url( '/' ) ) ) );
			exit;
		}
		// Reviews without JavaScript: a plain form post to the product page.
		if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) && ! empty( $_POST['vxs_review'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$r   = self::review_add( wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$pid = (int) ( $_POST['product'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			wp_safe_redirect( add_query_arg( 'vxs_msg', is_wp_error( $r ) ? 'review-bad' : 'review-ok', get_permalink( $pid ) ? get_permalink( $pid ) : home_url( '/' ) ) . '#vxs-reviews' );
			exit;
		}
		// phpcs:enable
	}

	/** Product pages show the note inside their layout; every other page at the top. */
	public static function msg_top() {
		if ( ! is_singular( Velox_Shop::PRODUCT ) ) {
			$m = self::msg_html();
			if ( '' !== $m ) {
				echo '<div class="vxs-msg-top">' . $m . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in msg_html()
			}
		}
	}

	/** A one-line note at the top of the page after one of those links. */
	public static function msg_html() {
		$c = isset( $_GET['vxs_msg'] ) ? sanitize_key( wp_unslash( $_GET['vxs_msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$m = array(
			'alert-on'   => array( 'ok', __( 'Confirmed — we email you once as soon as it is available again.', 'velox' ) ),
			'alert-off'  => array( 'ok', __( 'Done — you will not get a notification.', 'velox' ) ),
			'cart-off'   => array( 'ok', __( 'Your saved cart has been deleted. You will not hear from us about it.', 'velox' ) ),
			'link-bad'   => array( 'err', __( 'This link is no longer valid.', 'velox' ) ),
			'review-ok'  => array( 'ok', __( 'Thank you for your review!', 'velox' ) ),
			'review-bad' => array( 'err', __( 'The review could not be sent. Please check the form and try again.', 'velox' ) ),
		);
		return isset( $m[ $c ] ) ? '<div class="vxs-note ' . ( 'ok' === $m[ $c ][0] ? 'is-ok' : 'is-err' ) . ' alignwide" role="status">' . esc_html( $m[ $c ][1] ) . '</div>' : '';
	}

	/* ===================================================================== REST */

	public static function routes() {
		$pub = '__return_true';
		register_rest_route( 'velox/v1', '/shop/wish', array(
			array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'rest_wish' ), 'permission_callback' => $pub ),
			array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'rest_wish' ), 'permission_callback' => $pub ),
		) );
		register_rest_route( 'velox/v1', '/shop/cards', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'rest_cards' ), 'permission_callback' => $pub ) );
		register_rest_route( 'velox/v1', '/shop/alert', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'rest_alert' ), 'permission_callback' => $pub ) );
		register_rest_route( 'velox/v1', '/shop/review', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'rest_review' ), 'permission_callback' => $pub ) );
		register_rest_route( 'velox/v1', '/shop/remember', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'rest_remember' ), 'permission_callback' => $pub ) );
	}

	/** Signed in: the list lives in the account (REST cookie auth needs the nonce). Guests keep theirs in the browser. */
	public static function rest_wish( WP_REST_Request $req ) {
		$uid = get_current_user_id();
		if ( ! $uid ) {
			return new WP_REST_Response( array( 'ids' => null ), 200 );
		}
		$ids = self::wish_ids( $uid );
		if ( 'POST' === $req->get_method() ) {
			$merge = (array) $req->get_param( 'merge' );
			$id    = (int) $req->get_param( 'id' );
			if ( $merge ) {
				$ids = array_merge( $ids, array_map( 'intval', $merge ) );
			} elseif ( $id ) {
				$ids = $req->get_param( 'on' ) ? array_merge( $ids, array( $id ) ) : array_diff( $ids, array( $id ) );
			}
			$ids = array_slice( array_values( array_unique( array_filter( array_map( 'intval', $ids ), function ( $x ) { return Velox_Shop::PRODUCT === get_post_type( $x ); } ) ) ), -200 );
			update_user_meta( $uid, self::WISH, $ids );
		}
		return new WP_REST_Response( array( 'ids' => $ids ), 200 );
	}

	public static function rest_cards( WP_REST_Request $req ) {
		$ids = array_slice( array_map( 'intval', explode( ',', (string) $req->get_param( 'ids' ) ) ), 0, 60 );
		return new WP_REST_Response( array( 'html' => self::wish_grid( $ids ) ), 200 );
	}

	public static function rest_alert( WP_REST_Request $req ) {
		if ( (string) $req->get_param( 'website' ) ) {
			return new WP_REST_Response( array( 'msg' => __( 'Thank you!', 'velox' ) ), 200 );
		}
		if ( ! $req->get_param( 'consent' ) ) {
			return new WP_REST_Response( array( 'msg' => __( 'Please tick the box so we may email you.', 'velox' ), 'bad' => 1 ), 200 );
		}
		$k = 'velox_al_' . md5( Velox_Shop_Account::visitor_ip() );
		if ( (int) get_transient( $k ) >= 8 ) {
			return new WP_REST_Response( array( 'msg' => __( 'Please try again later.', 'velox' ), 'bad' => 1 ), 200 );
		}
		set_transient( $k, (int) get_transient( $k ) + 1, HOUR_IN_SECONDS );
		$r = self::alert_add( (int) $req->get_param( 'product' ), sanitize_key( (string) $req->get_param( 'variant' ) ), (string) $req->get_param( 'email' ) );
		return new WP_REST_Response( is_wp_error( $r ) ? array( 'msg' => $r->get_error_message(), 'bad' => 1 ) : array( 'msg' => $r ), 200 );
	}

	public static function rest_review( WP_REST_Request $req ) {
		$r = self::review_add( $req->get_params() );
		return new WP_REST_Response( is_wp_error( $r ) ? array( 'msg' => $r->get_error_message(), 'bad' => 1 ) : array( 'msg' => $r ), 200 );
	}

	public static function rest_remember( WP_REST_Request $req ) {
		return new WP_REST_Response( array( 'ok' => self::cart_remember( (string) $req->get_param( 'email' ), (bool) $req->get_param( 'consent' ) ) ), 200 );
	}

	/* ===================================================================== structured data */

	/** Product data for search engines: price, availability, brand, rating. */
	public static function schema() {
		if ( ! is_singular( Velox_Shop::PRODUCT ) ) {
			return;
		}
		$p = Velox_Shop::product( get_queried_object_id() );
		if ( ! $p || 'inquiry' === $p['mode'] ) {
			return;
		}
		list( $lo, $hi ) = Velox_Shop::price_range( $p );
		$a     = Velox_Shop::availability( $p );
		$avail = in_array( $a[0], array( 'sold', 'out' ), true ) ? 'OutOfStock' : ( 'backorder' === $a[0] ? 'BackOrder' : 'InStock' );
		$cur   = strtoupper( (string) Velox_Shop::get( 'currency', 'EUR' ) );
		$img   = get_the_post_thumbnail_url( $p['id'], 'large' );
		$d     = array(
			'@context'    => 'https://schema.org',
			'@type'       => 'Product',
			'name'        => $p['title'],
			'url'         => get_permalink( $p['id'] ),
			'description' => wp_strip_all_tags( has_excerpt( $p['id'] ) ? get_the_excerpt( $p['id'] ) : wp_trim_words( (string) get_post_field( 'post_content', $p['id'] ), 40, '…' ) ),
			'brand'       => array( '@type' => 'Brand', 'name' => (string) Velox_Shop::get( 'store_name', get_bloginfo( 'name' ) ) ),
			'offers'      => $lo === $hi
				? array( '@type' => 'Offer', 'price' => number_format( $lo, 2, '.', '' ), 'priceCurrency' => $cur, 'availability' => 'https://schema.org/' . $avail, 'url' => get_permalink( $p['id'] ) )
				: array( '@type' => 'AggregateOffer', 'lowPrice' => number_format( $lo, 2, '.', '' ), 'highPrice' => number_format( $hi, 2, '.', '' ), 'priceCurrency' => $cur, 'offerCount' => count( $p['variants'] ), 'availability' => 'https://schema.org/' . $avail ),
		);
		if ( $img ) {
			$d['image'] = $img;
		}
		if ( '' !== $p['sku'] ) {
			$d['sku'] = $p['sku'];
		}
		if ( '' !== $p['gtin'] ) {
			$d['gtin'] = $p['gtin'];
		}
		list( $avg, $n ) = self::rating( $p['id'] );
		if ( $n && self::on( 'reviews' ) ) {
			$d['aggregateRating'] = array( '@type' => 'AggregateRating', 'ratingValue' => $avg, 'reviewCount' => $n, 'bestRating' => 5, 'worstRating' => 1 );
		}
		echo '<script type="application/ld+json">' . wp_json_encode( $d, JSON_UNESCAPED_UNICODE ) . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON
	}
}
