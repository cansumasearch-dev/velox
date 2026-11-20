<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Velox Shop — everything a visitor sees: product grid, product page, buy
 * box, mini cart, cart, checkout, the order page, the customer account, the
 * withdrawal form, downloads and the printable invoice. The same functions
 * serve the shortcodes and the Velox Builder shop elements.
 *
 * Markup is plain and class-based (vxs-*), coloured through a few CSS custom
 * properties, so a site's own design can take over without fighting it.
 *
 * @package Velox
 */
class Velox_Shop_Front {

	public static function init() {
		foreach ( array(
			'velox_shop'        => 'sc_grid',
			'velox_product'     => 'sc_product',
			'velox_buy'         => 'sc_buy',
			'velox_cart'        => 'sc_cart',
			'velox_checkout'    => 'sc_checkout',
			'velox_account'     => 'sc_account',
			'velox_withdraw'    => 'sc_withdraw',
			'velox_cart_button' => 'sc_cart_button',
		) as $tag => $fn ) {
			add_shortcode( $tag, array( __CLASS__, $fn ) );
		}
		add_action( 'init', array( __CLASS__, 'returns' ), 30 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'the_content', array( __CLASS__, 'product_page' ), 20 );
		add_action( 'wp_footer', array( __CLASS__, 'footer' ) );
	}

	/* ------------------------------------------------------------- urls */

	public static function page_url( $key, $fallback = '' ) {
		$id = (int) Velox_Shop::get( 'page_' . $key );
		if ( ! $id || 'publish' !== get_post_status( $id ) ) {
			$id = self::find_page( $key );
		}
		if ( $id ) {
			return get_permalink( $id );
		}
		return $fallback ? $fallback : home_url( '/' );
	}

	/** A published page that already holds the shop part (shops from before 4.53 set none). */
	private static function find_page( $key ) {
		static $found = array();
		$sc = array( 'shop' => 'velox_shop', 'cart' => 'velox_cart', 'checkout' => 'velox_checkout', 'account' => 'velox_account', 'withdraw' => 'velox_withdraw' );
		if ( ! isset( $sc[ $key ] ) ) {
			return 0;
		}
		if ( ! array_key_exists( $key, $found ) ) {
			global $wpdb;
			$found[ $key ] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'page' AND post_status = 'publish' AND post_content LIKE %s ORDER BY ID LIMIT 1", '%[' . $wpdb->esc_like( $sc[ $key ] ) . '%' ) );
		}
		return $found[ $key ];
	}

	public static function order_url( $o ) {
		return add_query_arg( 'vxo', $o['key'], self::page_url( 'checkout', self::page_url( 'account' ) ) );
	}

	public static function pay_url( $o ) {
		return add_query_arg( 'vxpay', '1', self::order_url( $o ) );
	}

	/**
	 * Coming back from Stripe or PayPal, "pay again", downloads and the
	 * invoice: handled before the page is drawn (and before the builder takes
	 * over the request).
	 */
	public static function returns() {
		if ( is_admin() ) {
			return;
		}
		if ( isset( $_GET['vxdl'] ) ) {
			self::serve_download();
		}
		if ( isset( $_GET['vxinv'] ) ) {
			$o = Velox_Shop::order_by_key( sanitize_text_field( wp_unslash( $_GET['vxinv'] ) ) );
			if ( $o && ! empty( $o['invoice']['number'] ) ) {
				nocache_headers();
				echo self::invoice_html( $o ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside
				exit;
			}
		}
		if ( empty( $_GET['vxo'] ) ) {
			return;
		}
		$o = Velox_Shop::order_by_key( sanitize_text_field( wp_unslash( $_GET['vxo'] ) ) );
		if ( ! $o ) {
			return;
		}
		if ( ! empty( $_GET['vxsid'] ) ) {
			Velox_Shop_Pay::stripe_verify( $o, sanitize_text_field( wp_unslash( $_GET['vxsid'] ) ) );
			wp_safe_redirect( remove_query_arg( 'vxsid' ) );
			exit;
		}
		if ( ! empty( $_GET['vxpp'] ) && ! empty( $_GET['token'] ) ) {
			Velox_Shop_Pay::paypal_capture( $o, sanitize_text_field( wp_unslash( $_GET['token'] ) ) );
			wp_safe_redirect( remove_query_arg( array( 'vxpp', 'token', 'PayerID' ) ) );
			exit;
		}
		if ( ! empty( $_GET['vxpay'] ) && 'pending' === $o['payment']['status'] && in_array( $o['payment']['method'], array( 'stripe', 'paypal' ), true ) ) {
			$url = Velox_Shop_Pay::start_payment( $o );
			if ( ! is_wp_error( $url ) ) {
				wp_redirect( $url );
				exit;
			}
			wp_safe_redirect( add_query_arg( 'vx', 'payfail', remove_query_arg( 'vxpay' ) ) );
			exit;
		}
	}

	/* --------------------------------------------------------- formatting */

	public static function money( $n ) {
		return esc_html( Velox_Shop::money( $n ) );
	}

	/** "inkl. MwSt., zzgl. Versand" with links (PAngV). */
	public static function tax_note( $p = null ) {
		$ship = '';
		$sid  = (int) Velox_Shop::get( 'page_shipping' );
		$needs_ship = ! $p || 'physical' === $p['type'];
		if ( $needs_ship ) {
			$ship = $sid ? '<a href="' . esc_url( get_permalink( $sid ) ) . '">' . esc_html__( 'plus shipping', 'velox' ) . '</a>' : esc_html__( 'plus shipping', 'velox' );
		}
		$tax = Velox_Shop::small_business() ? esc_html__( 'No VAT (§ 19 UStG)', 'velox' ) : esc_html__( 'incl. VAT', 'velox' );
		return '<span class="vxs-taxnote">' . $tax . ( $ship ? ', ' . $ship : '' ) . '</span>';
	}

	public static function price_html( $p, $v = null ) {
		if ( 'inquiry' === $p['mode'] ) {
			return '<span class="vxs-price is-req">' . esc_html__( 'Price on request', 'velox' ) . '</span>';
		}
		if ( ! $v && Velox_Shop::has_variants( $p ) ) {
			list( $lo, $hi ) = Velox_Shop::price_range( $p );
			if ( $lo !== $hi ) {
				/* translators: %s: lowest price */
				return '<span class="vxs-price"><span class="vxs-now">' . sprintf( esc_html__( 'from %s', 'velox' ), self::money( $lo ) ) . '</span></span>';
			}
		}
		$now = Velox_Shop::price_of( $p, $v );
		$was = Velox_Shop::compare_of( $p, $v );
		return '<span class="vxs-price' . ( $was ? ' is-sale' : '' ) . '">' . ( $was ? '<del aria-label="' . esc_attr__( 'Original price', 'velox' ) . '">' . self::money( $was ) . '</del> ' : '' ) . '<span class="vxs-now">' . self::money( $now ) . '</span></span>';
	}

	public static function badge_html( $p ) {
		$a = Velox_Shop::availability( $p );
		if ( in_array( $a[0], array( 'sold', 'out' ), true ) ) {
			return '<span class="vxs-badge is-out">' . esc_html( $a[1] ) . '</span>';
		}
		if ( 'low' === $a[0] ) {
			return '<span class="vxs-badge is-low">' . esc_html( $a[1] ) . '</span>';
		}
		if ( '' !== $p['badge'] ) {
			return '<span class="vxs-badge">' . esc_html( $p['badge'] ) . '</span>';
		}
		$was = Velox_Shop::compare_of( $p );
		if ( $was && ! Velox_Shop::has_variants( $p ) ) {
			$now = Velox_Shop::price_of( $p );
			return '<span class="vxs-badge is-sale">−' . (int) round( ( 1 - $now / $was ) * 100 ) . '%</span>';
		}
		return '';
	}

	/* ------------------------------------------------------------- grid */

	public static function sc_grid( $atts = array() ) {
		$a = shortcode_atts( array( 'count' => 12, 'columns' => '', 'category' => '', 'sort' => 'menu', 'filter' => '', 'ids' => '', 'layout' => '', 'card' => '', 'button' => '' ), $atts, 'velox_shop' );
		return self::grid( $a );
	}

	public static function grid( $a ) {
		$args = array( 'post_type' => Velox_Shop::PRODUCT, 'post_status' => 'publish', 'posts_per_page' => max( 1, min( 60, (int) $a['count'] ) ), 'no_found_rows' => true );
		$cat  = isset( $_GET['vxcat'] ) && $a['filter'] ? sanitize_title( wp_unslash( $_GET['vxcat'] ) ) : sanitize_title( (string) $a['category'] );
		if ( $cat ) {
			$args['tax_query'] = array( array( 'taxonomy' => Velox_Shop::CAT, 'field' => 'slug', 'terms' => $cat ) );
		}
		if ( '' !== (string) $a['ids'] ) {
			$args['post__in'] = array_map( 'intval', explode( ',', (string) $a['ids'] ) );
			$args['orderby']  = 'post__in';
		}
		switch ( $a['sort'] ) {
			case 'new':
				$args['orderby'] = 'date';
				$args['order']   = 'DESC';
				break;
			case 'price':
			case 'price-desc':
				$args['meta_key'] = '_vx_price';
				$args['orderby']  = 'meta_value_num';
				$args['order']    = 'price' === $a['sort'] ? 'ASC' : 'DESC';
				break;
			case 'name':
				$args['orderby'] = 'title';
				$args['order']   = 'ASC';
				break;
			default:
				if ( empty( $args['orderby'] ) ) {
					$args['orderby'] = array( 'menu_order' => 'ASC', 'date' => 'DESC' );
				}
		}
		$sold = (string) Velox_Shop::get( 'sold_items', 'show' );
		if ( 'hide' === $sold ) {
			$args['meta_query'] = array( 'relation' => 'OR', array( 'key' => '_vx_status', 'value' => 'sold', 'compare' => '!=' ), array( 'key' => '_vx_status', 'compare' => 'NOT EXISTS' ) );
		}
		$q   = new WP_Query( $args );
		if ( 'last' === $sold && $q->posts ) {
			// Keep the order, but sold pieces go behind everything still for sale.
			$for_sale = array();
			$gone     = array();
			foreach ( $q->posts as $post ) {
				if ( 'sold' === get_post_meta( $post->ID, '_vx_status', true ) ) {
					$gone[] = $post;
				} else {
					$for_sale[] = $post;
				}
			}
			$q->posts = array_merge( $for_sale, $gone );
		}
		$out = '';
		if ( $a['filter'] ) {
			$terms = get_terms( array( 'taxonomy' => Velox_Shop::CAT, 'hide_empty' => true ) );
			if ( $terms && ! is_wp_error( $terms ) ) {
				$base = remove_query_arg( 'vxcat' );
				$out .= '<nav class="vxs-cats" aria-label="' . esc_attr__( 'Categories', 'velox' ) . '"><a href="' . esc_url( $base ) . '"' . ( $cat ? '' : ' aria-current="true"' ) . '>' . esc_html__( 'All', 'velox' ) . '</a>';
				foreach ( $terms as $t ) {
					$out .= '<a href="' . esc_url( add_query_arg( 'vxcat', $t->slug, $base ) ) . '"' . ( $cat === $t->slug ? ' aria-current="true"' : '' ) . '>' . esc_html( $t->name ) . '</a>';
				}
				$out .= '</nav>';
			}
		}
		if ( ! $q->have_posts() ) {
			return $out . '<p class="vxs-empty">' . esc_html__( 'No products here yet.', 'velox' ) . '</p>';
		}
		$over = array();
		foreach ( array( 'layout', 'card', 'button', 'hover', 'align', 'cols_t', 'cols_m', 'gap', 'show_cat', 'show_text', 'fit' ) as $k ) {
			if ( isset( $a[ $k ] ) && '' !== (string) $a[ $k ] ) {
				$over[ $k ] = $a[ $k ];
			}
		}
		if ( '' !== (string) ( $a['columns'] ?? '' ) ) {
			$over['cols'] = $a['columns'];
		}
		$d     = Velox_Shop::design( $over );
		$ratio = Velox_Shop::card_ratio();
		// A masonry wall shows every photo in its own shape.
		if ( 'masonry' === $d['layout'] ) {
			$ratio = 'natural';
		}
		$style = '--vxs-cols:' . max( 1, min( 6, (int) $d['cols'] ) ) . ';--vxs-cols-t:' . max( 1, min( 4, (int) $d['cols_t'] ) ) . ';--vxs-cols-m:' . max( 1, min( 2, (int) $d['cols_m'] ) ) . ';--vxs-gap:' . max( 0, min( 120, (int) $d['gap'] ) ) . 'px' . ( 'natural' === $ratio ? '' : ';--vxs-ratio:' . $ratio );
		$out  .= '<div class="vxs-grid alignwide ' . esc_attr( Velox_Shop::design_classes( $d ) ) . ( 'natural' === $ratio ? ' is-natural' : '' ) . '" style="' . esc_attr( $style ) . '">';
		while ( $q->have_posts() ) {
			$q->the_post();
			$out .= self::card( Velox_Shop::product( get_the_ID() ), $d );
		}
		wp_reset_postdata();
		return $out . '</div>';
	}

	public static function card( $p, $d = null ) {
		if ( ! $p ) {
			return '';
		}
		$d = $d ? $d : Velox_Shop::design();
		$url   = get_permalink( $p['id'] );
		$img   = get_the_post_thumbnail( $p['id'], 'large', array( 'loading' => 'lazy', 'class' => 'vxs-card-im' ) );
		$alt   = $p['gallery'] ? wp_get_attachment_image( (int) $p['gallery'][0], 'large', false, array( 'loading' => 'lazy', 'class' => 'vxs-card-im2', 'alt' => '' ) ) : '';
		$a     = Velox_Shop::availability( $p );
		$out   = '<article class="vxs-card is-' . esc_attr( $a[0] ) . '">';
		$out  .= '<a class="vxs-card-media" href="' . esc_url( $url ) . '" tabindex="-1" aria-hidden="true">' . ( $img ? $img : '<span class="vxs-noimg"></span>' ) . $alt . self::badge_html( $p ) . '</a>';
		$out  .= '<div class="vxs-card-body">';
		if ( '1' === (string) $d['show_cat'] ) {
			$terms = get_the_terms( $p['id'], Velox_Shop::CAT );
			if ( $terms && ! is_wp_error( $terms ) ) {
				$out .= '<span class="vxs-card-cat">' . esc_html( $terms[0]->name ) . '</span>';
			}
		}
		$out  .= '<h3 class="vxs-card-t"><a href="' . esc_url( $url ) . '">' . esc_html( $p['title'] ) . '</a></h3>';
		if ( '1' === (string) $d['show_text'] && has_excerpt( $p['id'] ) ) {
			$out .= '<p class="vxs-card-text">' . esc_html( wp_trim_words( get_the_excerpt( $p['id'] ), 22, '…' ) ) . '</p>';
		}
		$out  .= '<div class="vxs-card-price">' . self::price_html( $p ) . '</div>';
		if ( 'inquiry' !== $p['mode'] ) {
			$out .= self::tax_note( $p );
		}
		$unit  = Velox_Shop::unit_price( $p );
		if ( $unit ) {
			$out .= '<div class="vxs-unit">' . esc_html( $unit ) . '</div>';
		}
		$out .= '</div>';
		$mode = (string) $d['button'];
		$bag  = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 7h12l-1 12.2a1 1 0 0 1-1 .8H8a1 1 0 0 1-1-.8z"/><path d="M9 7V6a3 3 0 0 1 6 0v1"/><path d="M12 11v5M9.5 13.5h5"/></svg>';
		$lbl  = '' !== $p['button'] ? $p['button'] : __( 'Add to cart', 'velox' );
		if ( 'none' === $mode ) {
			// No button: the whole card links to the product.
			$out .= Velox_Shop::can_buy( $p, null, 1 ) ? '' : '<span class="vxs-state">' . esc_html( $a[1] ) . '</span>';
		} elseif ( 'link' === $mode ) {
			$out .= Velox_Shop::can_buy( $p, null, 1 ) || 'inquiry' === $p['mode']
				? '<a class="vxs-more-link" href="' . esc_url( $url ) . '" tabindex="-1" aria-hidden="true">' . esc_html( 'inquiry' === $p['mode'] ? __( 'Ask about it', 'velox' ) : __( 'View', 'velox' ) ) . ' <span aria-hidden="true">→</span></a>'
				: '<span class="vxs-state">' . esc_html( $a[1] ) . '</span>';
		} elseif ( Velox_Shop::can_buy( $p, null, 1 ) && ! Velox_Shop::has_variants( $p ) ) {
			$out .= self::add_form( $p, '', 'icon' === $mode
				? '<button type="submit" class="vxs-btn vxs-btn-icon" aria-label="' . esc_attr( $lbl . ': ' . $p['title'] ) . '">' . $bag . '</button>'
				: '<button type="submit" class="vxs-btn vxs-btn-sm">' . esc_html( $lbl ) . '</button>' );
		} elseif ( Velox_Shop::has_variants( $p ) && 'inquiry' !== $p['mode'] ) {
			$out .= '<a class="vxs-btn vxs-btn-sm vxs-btn-ghost' . ( 'icon' === $mode ? ' vxs-btn-icon' : '' ) . '" href="' . esc_url( $url ) . '"' . ( 'icon' === $mode ? ' aria-label="' . esc_attr( __( 'Choose options', 'velox' ) . ': ' . $p['title'] ) . '">' . $bag : '>' . esc_html__( 'Choose options', 'velox' ) ) . '</a>';
		} else {
			$out .= '<span class="vxs-state">' . esc_html( $a[1] ) . '</span>';
		}
		return $out . '</article>';
	}

	private static function add_form( $p, $vid, $inner, $qty = false ) {
		return '<form method="post" class="vxs-add" data-vxs-add>'
			. '<input type="hidden" name="velox_shop_action" value="add">'
			. '<input type="hidden" name="product" value="' . (int) $p['id'] . '">'
			. '<input type="hidden" name="variant" value="' . esc_attr( $vid ) . '">'
			. '<input type="hidden" name="vx_return" value="' . esc_url( home_url( add_query_arg( array() ) ) ) . '">'
			. ( $qty ? '' : '<input type="hidden" name="qty" value="1">' )
			. $inner . '</form>';
	}

	/* ----------------------------------------------------- product page */

	/** Single product pages get the product layout around their text. */
	public static function product_page( $content ) {
		if ( ! is_singular( Velox_Shop::PRODUCT ) || ! in_the_loop() || ! is_main_query() || doing_filter( 'velox_shop_inner' ) ) {
			return $content;
		}
		if ( ! Velox_Shop::get( 'auto_layout', '1' ) || false !== strpos( $content, 'vxs-buy' ) ) {
			return $content;
		}
		return self::product_layout( Velox_Shop::product( get_the_ID() ), $content, false );
	}

	public static function sc_product( $atts = array() ) {
		$a  = shortcode_atts( array( 'id' => 0 ), $atts, 'velox_product' );
		$id = (int) $a['id'] ? (int) $a['id'] : get_the_ID();
		$p  = Velox_Shop::product( $id );
		if ( ! $p ) {
			return '';
		}
		return self::product_layout( $p, apply_filters( 'velox_shop_inner', (string) get_post_field( 'post_content', $id ) ) );
	}

	public static function product_layout( $p, $content, $title = true ) {
		if ( ! $p ) {
			return $content;
		}
		$ids = array_values( array_filter( array_merge( array( (int) get_post_thumbnail_id( $p['id'] ) ), $p['gallery'] ) ) );
		// alignwide: block themes give it their wide width instead of the text column.
		$d   = Velox_Shop::design();
		$out = '<div class="vxs-product alignwide ' . esc_attr( Velox_Shop::design_classes( $d ) ) . '">' . self::gallery_html( $ids, $p, $d ) . '<div class="vxs-product-side">'
			. ( $title ? '<h1 class="vxs-product-t">' . esc_html( $p['title'] ) . '</h1>' : '' )
			. ( has_excerpt( $p['id'] ) ? '<div class="vxs-lead">' . wp_kses_post( wpautop( get_the_excerpt( $p['id'] ) ) ) . '</div>' : '' )
			. self::buy_box( $p ) . '</div></div>';
		$tabs = '';
		if ( '' !== trim( wp_strip_all_tags( $content ) ) ) {
			$tabs .= '<section class="vxs-desc"><h2>' . esc_html__( 'Description', 'velox' ) . '</h2>' . $content . '</section>';
		}
		$tabs .= self::specs_html( $p );
		if ( '' !== $p['gpsr'] ) {
			$tabs .= '<section class="vxs-gpsr"><h2>' . esc_html__( 'Manufacturer & safety', 'velox' ) . '</h2><p>' . nl2br( esc_html( $p['gpsr'] ) ) . '</p></section>';
		}
		// Details as an accordion: each section folds; the first one starts open.
		if ( 'accordion' === $d['details'] && '' !== $tabs ) {
			$first = true;
			$tabs  = preg_replace_callback( '#<section class="(vxs-[a-z]+)"><h2>(.*?)</h2>(.*?)</section>#s', function ( $m ) use ( &$first ) {
				$o     = '<details class="' . $m[1] . ' vxs-acc"' . ( $first ? ' open' : '' ) . '><summary><h2>' . $m[2] . '</h2></summary><div class="vxs-acc-b">' . $m[3] . '</div></details>';
				$first = false;
				return $o;
			}, $tabs );
		}
		if ( $p['upsell'] ) {
			$tabs .= '<section class="vxs-more"><h2>' . esc_html__( 'You might also like', 'velox' ) . '</h2>' . self::grid( array( 'ids' => implode( ',', $p['upsell'] ), 'count' => 4, 'columns' => 4, 'category' => '', 'sort' => 'menu', 'filter' => '' ) ) . '</section>';
		}
		return $out . ( $tabs ? '<div class="vxs-product-more alignwide">' . $tabs . '</div>' : '' );
	}

	public static function gallery_html( $ids, $p, $d = null ) {
		if ( ! $ids ) {
			return '<div class="vxs-gallery is-empty"><span class="vxs-noimg"></span></div>';
		}
		$d = $d ? $d : Velox_Shop::design();
		// Stacked or grid: every photo shown large, no thumbnails to click.
		if ( in_array( $d['gallery'], array( 'stack', 'grid' ), true ) && count( $ids ) > 0 ) {
			$all = '';
			foreach ( $ids as $i => $id ) {
				$all .= '<figure class="vxs-gal-item">' . wp_get_attachment_image( $id, 'large', false, array( 'loading' => $i ? 'lazy' : 'eager', 'fetchpriority' => $i ? 'auto' : 'high' ) ) . ( 0 === $i ? self::badge_html( $p ) : '' ) . '</figure>';
			}
			return '<div class="vxs-gallery vxs-gallery-' . $d['gallery'] . '">' . $all . '</div>';
		}
		$main = wp_get_attachment_image( $ids[0], 'large', false, array( 'class' => 'vxs-gal-main', 'loading' => 'eager', 'fetchpriority' => 'high', 'data-vxs-main' => '1' ) );
		$th   = '';
		if ( count( $ids ) > 1 ) {
			foreach ( $ids as $i => $id ) {
				$th .= '<button type="button" class="vxs-gal-th' . ( 0 === $i ? ' is-on' : '' ) . '" data-vxs-img="' . esc_url( (string) wp_get_attachment_image_url( $id, 'large' ) ) . '" data-vxs-id="' . (int) $id . '" aria-label="' . esc_attr( sprintf( /* translators: %d: image number */ __( 'Image %d', 'velox' ), $i + 1 ) ) . '">' . wp_get_attachment_image( $id, 'thumbnail', false, array( 'loading' => 'lazy', 'alt' => '' ) ) . '</button>';
			}
		}
		return '<div class="vxs-gallery"><div class="vxs-gal-stage">' . $main . self::badge_html( $p ) . '</div>' . ( $th ? '<div class="vxs-gal-thumbs">' . $th . '</div>' : '' ) . '</div>';
	}

	public static function specs_html( $p ) {
		$rows = $p['specs'];
		if ( '' !== $p['sku'] ) {
			$rows[] = array( 'k' => __( 'Article number', 'velox' ), 'v' => $p['sku'] );
		}
		if ( '' !== $p['weight'] && 'physical' === $p['type'] ) {
			$rows[] = array( 'k' => __( 'Weight', 'velox' ), 'v' => $p['weight'] . ( is_numeric( str_replace( ',', '.', $p['weight'] ) ) ? ' kg' : '' ) );
		}
		if ( '' !== $p['gtin'] ) {
			$rows[] = array( 'k' => 'EAN / GTIN', 'v' => $p['gtin'] );
		}
		if ( ! $rows ) {
			return '';
		}
		$t = '';
		foreach ( $rows as $r ) {
			$t .= '<tr><th scope="row">' . esc_html( $r['k'] ) . '</th><td>' . esc_html( $r['v'] ) . '</td></tr>';
		}
		return '<section class="vxs-specs"><h2>' . esc_html__( 'Details', 'velox' ) . '</h2><table>' . $t . '</table></section>';
	}

	public static function sc_buy( $atts = array() ) {
		$a = shortcode_atts( array( 'id' => 0 ), $atts, 'velox_buy' );
		$p = Velox_Shop::product( (int) $a['id'] ? (int) $a['id'] : get_the_ID() );
		return $p ? self::buy_box( $p ) : '';
	}

	/**
	 * Price, the legal lines under it, options, quantity and the button.
	 * Variants travel as JSON so choosing one updates everything without a
	 * page load (velox-shop.js); without JavaScript the select still works.
	 */
	public static function buy_box( $p, $opts = array() ) {
		$o = array_merge( array( 'qty' => true, 'button' => '' ), $opts );
		$a = Velox_Shop::availability( $p );
		$out = '<div class="vxs-buy" data-vxs-buy>';
		$out .= '<div class="vxs-buy-price" data-vxs-price>' . self::price_html( $p ) . '</div>';
		if ( 'inquiry' !== $p['mode'] ) {
			$out .= '<div class="vxs-buy-legal">' . self::tax_note( $p ) . '<span class="vxs-unit" data-vxs-unit>' . esc_html( Velox_Shop::has_variants( $p ) ? '' : Velox_Shop::unit_price( $p ) ) . '</span></div>';
		}
		if ( 'inquiry' === $p['mode'] ) {
			$mail = (string) Velox_Shop::get( 'store_email' );
			/* translators: %s: product name */
			$subj = rawurlencode( sprintf( __( 'Question about %s', 'velox' ), $p['title'] ) );
			return $out . '<a class="vxs-btn vxs-btn-lg" href="' . esc_url( 'mailto:' . $mail . '?subject=' . $subj ) . '">' . esc_html( '' !== $p['button'] ? $p['button'] : __( 'Ask about this', 'velox' ) ) . '</a></div>';
		}
		$vjson = '';
		$sel   = '';
		if ( Velox_Shop::has_variants( $p ) ) {
			$vars = array();
			foreach ( $p['variants'] as $v ) {
				if ( ! empty( $v['off'] ) ) {
					continue;
				}
				$va = Velox_Shop::availability( $p, $v );
				$vars[] = array(
					'id'    => $v['id'],
					'opts'  => $v['opts'],
					'price' => wp_strip_all_tags( self::price_html( $p, $v ) ) ? self::price_html( $p, $v ) : '',
					'unit'  => Velox_Shop::unit_price( $p, $v ),
					'can'   => Velox_Shop::can_buy( $p, $v, 1 ),
					'av'    => $va[1],
					'avk'   => $va[0],
					'img'   => ! empty( $v['image'] ) ? wp_get_attachment_image_url( (int) $v['image'], 'large' ) : '',
					'max'   => null !== Velox_Shop::stock_of( $p, $v ) && empty( $p['backorder'] ) ? Velox_Shop::stock_of( $p, $v ) : 99,
				);
			}
			$vjson = ' data-vxs-variants="' . esc_attr( wp_json_encode( $vars ) ) . '"';
			foreach ( $p['options'] as $i => $opt ) {
				$sel .= '<div class="vxs-opt"><span class="vxs-opt-l" id="vxs-o' . (int) $p['id'] . '-' . $i . '">' . esc_html( $opt['name'] ) . '</span><div class="vxs-opt-vals" role="radiogroup" aria-labelledby="vxs-o' . (int) $p['id'] . '-' . $i . '">';
				foreach ( $opt['values'] as $val ) {
					$sel .= '<label class="vxs-chip"><input type="radio" name="vxs_opt_' . $i . '" value="' . esc_attr( $val ) . '" data-vxs-opt="' . $i . '"><span>' . esc_html( $val ) . '</span></label>';
				}
				$sel .= '</div></div>';
			}
		}
		$can  = Velox_Shop::can_buy( $p, null, 1 ) || Velox_Shop::has_variants( $p );
		$label = '' !== $o['button'] ? $o['button'] : ( '' !== $p['button'] ? $p['button'] : __( 'Add to cart', 'velox' ) );
		$max  = ! empty( $p['unique'] ) ? 1 : ( '' !== (string) $p['max_qty'] ? (int) $p['max_qty'] : 99 );
		$inner = $sel
			. '<div class="vxs-buy-row">'
			. ( $o['qty'] && empty( $p['unique'] ) ? '<div class="vxs-qty"><button type="button" data-vxs-step="-1" aria-label="' . esc_attr__( 'Fewer', 'velox' ) . '">−</button><input type="number" name="qty" value="1" min="1" max="' . (int) $max . '" inputmode="numeric" aria-label="' . esc_attr__( 'Quantity', 'velox' ) . '"><button type="button" data-vxs-step="1" aria-label="' . esc_attr__( 'More', 'velox' ) . '">+</button></div>' : '<input type="hidden" name="qty" value="1">' )
			. '<button type="submit" class="vxs-btn vxs-btn-lg vxs-buy-btn"' . ( $can ? '' : ' disabled' ) . ' data-vxs-label="' . esc_attr( $label ) . '">' . esc_html( $can ? $label : $a[1] ) . '</button></div>';
		$out .= '<div' . $vjson . ' class="vxs-buy-form">' . self::add_form( $p, '', $inner, true ) . '</div>';
		$out .= '<div class="vxs-buy-meta"><span class="vxs-avail is-' . esc_attr( $a[0] ) . '" data-vxs-avail>' . esc_html( Velox_Shop::has_variants( $p ) ? __( 'Choose your options', 'velox' ) : $a[1] ) . '</span>';
		if ( '' !== $p['delivery'] && 'physical' === $p['type'] ) {
			/* translators: %s: delivery time */
			$out .= '<span class="vxs-delivery">' . sprintf( esc_html__( 'Delivery: %s', 'velox' ), esc_html( $p['delivery'] ) ) . '</span>';
		} elseif ( 'digital' === $p['type'] ) {
			$out .= '<span class="vxs-delivery">' . esc_html__( 'Instant download after payment', 'velox' ) . '</span>';
		}
		return $out . '</div></div>';
	}

	/* ------------------------------------------------------------ mini cart */

	/** The cart as JSON for the drawer and the badge. */
	public static function cart_state( $msg = '' ) {
		$lines = Velox_Shop::lines();
		$cart  = Velox_Shop::get_cart();
		$t     = Velox_Shop::totals( $lines, '', '', (string) ( $cart['_c'] ?? '' ) );
		$out   = array();
		foreach ( $lines as $l ) {
			$out[] = array(
				'key' => $l['key'], 'pid' => $l['pid'], 'vid' => $l['vid'], 'name' => $l['name'], 'variant' => $l['variant'], 'qty' => $l['qty'],
				'unit' => Velox_Shop::money( $l['unit'] ), 'total' => Velox_Shop::money( $l['total'] ), 'thumb' => $l['thumb'], 'url' => $l['url'], 'max' => $l['max'], 'unique' => $l['unique'],
			);
		}
		$free = null;
		foreach ( (array) $t['ship_opts'] as $s ) {
			if ( isset( $s['free_above'] ) && null !== $s['free_above'] ) {
				$free = max( 0, $s['free_above'] - ( $t['subtotal'] - $t['discount'] ) );
				break;
			}
		}
		return array(
			'count'    => Velox_Shop::cart_count(),
			'lines'    => $out,
			'subtotal' => Velox_Shop::money( $t['subtotal'] - $t['discount'] ),
			'discount' => $t['discount'] > 0 ? Velox_Shop::money( $t['discount'] ) : '',
			'coupon'   => $t['coupon'],
			'freeLeft' => null !== $free && $free > 0 ? Velox_Shop::money( $free ) : ( null !== $free && $t['needs_ship'] ? '0' : '' ),
			'freePct'  => null !== $free && $t['subtotal'] > 0 ? max( 0, min( 100, round( 100 - $free / max( 0.01, $free + $t['subtotal'] - $t['discount'] ) * 100 ) ) ) : 0,
			'checkout' => self::page_url( 'checkout' ),
			'cart'     => self::page_url( 'cart' ),
			'msg'      => $msg,
		);
	}

	public static function sc_cart_button( $atts = array() ) {
		$a     = shortcode_atts( array( 'label' => '', 'icon' => '', 'count' => '', 'total' => '' ), $atts, 'velox_cart_button' );
		$d     = Velox_Shop::design( array( 'cart_icon' => $a['icon'], 'cart_count' => $a['count'], 'cart_total' => $a['total'] ) );
		$n     = Velox_Shop::cart_count();
		$icons = array(
			'bag'    => '<path d="M6 7h12l-1 12.2a1 1 0 0 1-1 .8H8a1 1 0 0 1-1-.8z"/><path d="M9 7V6a3 3 0 0 1 6 0v1"/>',
			'cart'   => '<circle cx="9.5" cy="19.5" r="1.3"/><circle cx="17" cy="19.5" r="1.3"/><path d="M3 4h2.2l2.2 10.2a1.5 1.5 0 0 0 1.5 1.2h8.2a1.5 1.5 0 0 0 1.5-1.1L20.5 8H6.3"/>',
			'basket' => '<path d="M3.5 10h17l-1.7 8.4a1.5 1.5 0 0 1-1.5 1.1H6.7a1.5 1.5 0 0 1-1.5-1.1z"/><path d="M8 10l3-5.5M16 10l-3-5.5M9 14v2.5M12 14v2.5M15 14v2.5"/>',
		);
		$icon  = $icons[ $d['cart_icon'] ] ?? $icons['bag'];
		$style = in_array( $d['cart_count'], array( 'badge', 'inline', 'dot' ), true ) ? $d['cart_count'] : 'badge';
		$total = '1' === (string) $d['cart_total'] ? '<span class="vxs-cartbtn-total" data-vxs-cart-total>' . esc_html( $n ? Velox_Shop::cart_subtotal() : '' ) . '</span>' : '';
		return '<a class="vxs-cartbtn vxs-cc-' . $style . '" href="' . esc_url( self::page_url( 'cart' ) ) . '" data-vxs-cartbtn aria-label="' . esc_attr__( 'Cart', 'velox' ) . '">'
			. '<span class="vxs-cartbtn-i"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $icon . '</svg>'
			. ( 'inline' !== $style ? '<span class="vxs-count" data-vxs-count' . ( $n ? '' : ' hidden' ) . '>' . ( 'dot' === $style ? '' : (int) $n ) . '</span>' : '' ) . '</span>'
			. ( '' !== $a['label'] ? '<span class="vxs-cartbtn-l">' . esc_html( $a['label'] ) . '</span>' : '' )
			. ( 'inline' === $style ? '<span class="vxs-count vxs-count-inline" data-vxs-count' . ( $n ? '' : ' hidden' ) . '>' . (int) $n . '</span>' : '' )
			. $total . '</a>';
	}

	/* ----------------------------------------------------------------- cart */

	public static function sc_cart() {
		$lines = Velox_Shop::lines();
		$out   = self::flash();
		if ( ! $lines ) {
			return $out . '<div class="vxs-empty-state"><p>' . esc_html__( 'Your cart is empty.', 'velox' ) . '</p><a class="vxs-btn" href="' . esc_url( self::page_url( 'shop' ) ) . '">' . esc_html__( 'Continue shopping', 'velox' ) . '</a></div>';
		}
		$cart = Velox_Shop::get_cart();
		$t    = Velox_Shop::totals( $lines, '', '', (string) ( $cart['_c'] ?? '' ) );
		$out .= '<div class="vxs-cartpage alignwide ' . esc_attr( Velox_Shop::design_classes() ) . '"><ul class="vxs-lines">';
		foreach ( $lines as $l ) {
			$out .= '<li class="vxs-line" data-vxs-line="' . esc_attr( $l['key'] ) . '">'
				. '<a class="vxs-line-im" href="' . esc_url( $l['url'] ) . '">' . ( $l['thumb'] ? '<img src="' . esc_url( $l['thumb'] ) . '" alt="" loading="lazy">' : '' ) . '</a>'
				. '<div class="vxs-line-b"><a class="vxs-line-t" href="' . esc_url( $l['url'] ) . '">' . esc_html( $l['name'] ) . '</a>'
				. ( $l['variant'] ? '<span class="vxs-line-v">' . esc_html( $l['variant'] ) . '</span>' : '' )
				. '<span class="vxs-line-u">' . self::money( $l['unit'] ) . ( $l['delivery'] && $l['ship'] ? ' · ' . esc_html( $l['delivery'] ) : '' ) . '</span></div>'
				. '<form method="post" class="vxs-line-q">'
				. '<input type="hidden" name="velox_shop_action" value="set"><input type="hidden" name="product" value="' . (int) $l['pid'] . '"><input type="hidden" name="variant" value="' . esc_attr( $l['vid'] ) . '">'
				. '<input type="hidden" name="vx_return" value="' . esc_url( home_url( add_query_arg( array() ) ) ) . '">'
				. ( $l['unique'] ? '<input type="hidden" name="qty" value="1"><span class="vxs-one">1</span>' : '<div class="vxs-qty"><button type="button" data-vxs-step="-1" aria-label="' . esc_attr__( 'Fewer', 'velox' ) . '">−</button><input type="number" name="qty" value="' . (int) $l['qty'] . '" min="0" max="' . (int) $l['max'] . '" aria-label="' . esc_attr__( 'Quantity', 'velox' ) . '" data-vxs-autosubmit><button type="button" data-vxs-step="1" aria-label="' . esc_attr__( 'More', 'velox' ) . '">+</button></div>' )
				. '<noscript><button type="submit">' . esc_html__( 'Update', 'velox' ) . '</button></noscript></form>'
				. '<span class="vxs-line-p">' . self::money( $l['total'] ) . '</span>'
				. '<form method="post" class="vxs-line-x"><input type="hidden" name="velox_shop_action" value="remove"><input type="hidden" name="product" value="' . (int) $l['pid'] . '"><input type="hidden" name="variant" value="' . esc_attr( $l['vid'] ) . '"><input type="hidden" name="vx_return" value="' . esc_url( home_url( add_query_arg( array() ) ) ) . '">'
				. '<button type="submit" aria-label="' . esc_attr( sprintf( /* translators: %s: product */ __( 'Remove %s', 'velox' ), $l['name'] ) ) . '">&times;</button></form></li>';
		}
		$out .= '</ul><aside class="vxs-cart-sum">';
		$out .= '<form method="post" class="vxs-coupon"><input type="hidden" name="velox_shop_action" value="coupon"><input type="hidden" name="vx_return" value="' . esc_url( home_url( add_query_arg( array() ) ) ) . '">'
			. '<input type="text" name="coupon" value="' . esc_attr( $t['coupon'] ? $t['coupon'] : (string) ( $cart['_c'] ?? '' ) ) . '" placeholder="' . esc_attr__( 'Discount code', 'velox' ) . '" aria-label="' . esc_attr__( 'Discount code', 'velox' ) . '"><button type="submit" class="vxs-btn vxs-btn-ghost">' . esc_html__( 'Apply', 'velox' ) . '</button></form>'
			. ( $t['coupon_msg'] ? '<p class="vxs-err">' . esc_html( $t['coupon_msg'] ) . '</p>' : '' );
		$out .= self::totals_html( $lines, $t, true );
		$out .= '<a class="vxs-btn vxs-btn-lg vxs-btn-block" href="' . esc_url( self::page_url( 'checkout' ) ) . '">' . esc_html__( 'Go to checkout', 'velox' ) . '</a>';
		$out .= '<a class="vxs-link" href="' . esc_url( self::page_url( 'shop' ) ) . '">' . esc_html__( 'Continue shopping', 'velox' ) . '</a>';
		return $out . '</aside></div>';
	}

	/** Totals block shared by cart, checkout and the order page. */
	public static function totals_html( $lines, $t, $estimate = false ) {
		$row = function ( $l, $v, $cls = '' ) {
			return '<div class="vxs-t-row' . ( $cls ? ' ' . $cls : '' ) . '"><dt>' . $l . '</dt><dd>' . $v . '</dd></div>';
		};
		$out = '<dl class="vxs-totals">' . $row( esc_html__( 'Subtotal', 'velox' ), self::money( $t['subtotal'] ) );
		if ( $t['discount'] > 0 ) {
			$out .= $row( esc_html__( 'Discount', 'velox' ) . ' <code>' . esc_html( $t['coupon'] ) . '</code>', '− ' . self::money( $t['discount'] ), 'is-disc' );
		}
		if ( $t['needs_ship'] && $t['shipping'] ) {
			$label = $estimate ? esc_html__( 'Shipping', 'velox' ) . ' <small>' . esc_html( $t['shipping']['name'] ) . '</small>' : esc_html( $t['shipping']['name'] );
			$out .= $row( $label, $t['shipping']['cost'] > 0 ? self::money( $t['shipping']['cost'] ) : esc_html__( 'Free', 'velox' ) );
		}
		if ( $t['fee'] > 0 ) {
			$out .= $row( esc_html__( 'Cash on delivery fee', 'velox' ), self::money( $t['fee'] ) );
		}
		$out .= $row( esc_html__( 'Total', 'velox' ), self::money( $t['total'] ), 'is-total' );
		if ( Velox_Shop::small_business() ) {
			$out .= '<div class="vxs-t-tax">' . esc_html( (string) Velox_Shop::get( 'small_note' ) ) . '</div>';
		} else {
			foreach ( (array) $t['tax_lines'] as $rate => $amt ) {
				/* translators: %s: VAT rate */
				$out .= '<div class="vxs-t-tax"><span>' . sprintf( esc_html__( 'Includes %s%% VAT', 'velox' ), esc_html( rtrim( rtrim( number_format_i18n( (float) $rate, 2 ), '0' ), ',.' ) ) ) . '</span><span>' . self::money( $amt ) . '</span></div>';
			}
		}
		if ( $estimate && $t['needs_ship'] ) {
			$out .= '<div class="vxs-t-tax">' . esc_html__( 'Shipping to', 'velox' ) . ' ' . esc_html( Velox_Shop::countries()[ $t['country'] ] ?? $t['country'] ) . ' — ' . esc_html__( 'other countries at checkout.', 'velox' ) . '</div>';
		}
		return $out . '</dl>';
	}

	/** One line under the page after a cart action without JavaScript. */
	private static function flash() {
		if ( empty( $_GET['vx'] ) ) {
			return '';
		}
		$f = sanitize_key( wp_unslash( $_GET['vx'] ) );
		$m = isset( $_GET['vxm'] ) ? sanitize_text_field( rawurldecode( wp_unslash( $_GET['vxm'] ) ) ) : '';
		if ( 'added' === $f ) {
			return '<div class="vxs-note is-ok" role="status">' . esc_html__( 'Added to your cart.', 'velox' ) . ' <a href="' . esc_url( self::page_url( 'cart' ) ) . '">' . esc_html__( 'View cart', 'velox' ) . '</a></div>';
		}
		if ( in_array( $f, array( 'error', 'invalid' ), true ) && '' !== $m ) {
			return '<div class="vxs-note is-err" role="alert">' . esc_html( $m ) . '</div>';
		}
		if ( 'paycancel' === $f ) {
			return '<div class="vxs-note is-err" role="alert">' . esc_html__( 'The payment was cancelled. Your order is reserved — you can pay now or choose another way.', 'velox' ) . '</div>';
		}
		if ( 'payfail' === $f ) {
			return '<div class="vxs-note is-err" role="alert">' . esc_html__( 'The payment page could not be opened. Please try again in a moment or contact us.', 'velox' ) . '</div>';
		}
		return '';
	}

	/* ------------------------------------------------------------- checkout */

	public static function sc_checkout() {
		if ( ! empty( $_GET['vxo'] ) ) {
			$o = Velox_Shop::order_by_key( sanitize_text_field( wp_unslash( $_GET['vxo'] ) ) );
			if ( $o ) {
				return self::flash() . self::order_view( $o, true );
			}
		}
		$lines = Velox_Shop::lines();
		if ( ! $lines ) {
			return self::sc_cart();
		}
		// A form sent back with errors keeps what was typed.
		$back = get_transient( 'velox_shop_form_' . md5( (string) ( $_COOKIE[ Velox_Shop::COOKIE ] ?? '' ) . ( $_SERVER['REMOTE_ADDR'] ?? '' ) ) );
		$P    = is_array( $back ) ? (array) $back['post'] : array();
		$E    = is_array( $back ) ? (array) $back['err'] : array();
		$u    = wp_get_current_user();
		$last = array();
		if ( $u && $u->ID ) {
			$last = array_filter( (array) get_user_meta( $u->ID, 'velox_shop_address', true ) );
			$last += array( 'vx_email' => $u->user_email, 'vx_first' => $u->first_name, 'vx_last' => $u->last_name );
		}
		$v = function ( $k, $def = '' ) use ( $P, $last ) {
			return esc_attr( isset( $P[ $k ] ) ? (string) $P[ $k ] : ( $last[ $k ] ?? $def ) );
		};
		$cart = Velox_Shop::get_cart();
		$countries = Velox_Shop::ship_countries();
		$country   = isset( $P['vx_country'] ) ? strtoupper( (string) $P['vx_country'] ) : ( $last['vx_country'] ?? (string) Velox_Shop::get( 'store_country', 'DE' ) );
		if ( ! isset( $countries[ $country ] ) ) {
			$country = (string) key( $countries );
		}
		$t = Velox_Shop::totals( $lines, $country, (string) ( $P['vx_ship'] ?? '' ), (string) ( $P['vx_coupon'] ?? ( $cart['_c'] ?? '' ) ), (string) ( $P['vx_pay'] ?? '' ) );
		$methods = Velox_Shop_Pay::available( $t );
		$err = function ( $k ) use ( $E ) {
			return isset( $E[ $k ] ) ? '<span class="vxs-ferr" id="' . esc_attr( $k ) . '-e">' . esc_html( $E[ $k ] ) . '</span>' : '';
		};
		$field = function ( $name, $label, $type = 'text', $req = true, $auto = '', $extra = '' ) use ( $v, $err, $E ) {
			return '<label class="vxs-f' . ( isset( $E[ $name ] ) ? ' is-err' : '' ) . '"><span>' . esc_html( $label ) . ( $req ? '' : ' <small>' . esc_html__( 'optional', 'velox' ) . '</small>' ) . '</span>'
				. '<input type="' . esc_attr( $type ) . '" name="' . esc_attr( $name ) . '" value="' . $v( $name ) . '"' . ( $req ? ' required' : '' ) . ( $auto ? ' autocomplete="' . esc_attr( $auto ) . '"' : '' ) . ( isset( $E[ $name ] ) ? ' aria-invalid="true" aria-describedby="' . esc_attr( $name ) . '-e"' : '' ) . $extra . '>' . $err( $name ) . '</label>';
		};
		$sel_country = function ( $name, $cur ) use ( $countries ) {
			$o = '';
			foreach ( $countries as $c => $l ) {
				$o .= '<option value="' . esc_attr( $c ) . '"' . selected( $cur, $c, false ) . '>' . esc_html( $l ) . '</option>';
			}
			return '<label class="vxs-f"><span>' . esc_html__( 'Country', 'velox' ) . '</span><select name="' . esc_attr( $name ) . '" autocomplete="country" data-vxs-quote>' . $o . '</select></label>';
		};
		$phone   = (string) Velox_Shop::get( 'f_phone' );
		$company = (string) Velox_Shop::get( 'f_company' );
		$btn     = trim( (string) Velox_Shop::get( 'button_text' ) );
		$btn     = '' !== $btn ? $btn : __( 'Buy now', 'velox' );
		$out = self::flash() . '<form method="post" class="vxs-checkout alignwide ' . esc_attr( Velox_Shop::design_classes() ) . '" data-vxs-checkout novalidate>'
			. wp_nonce_field( 'velox_shop', '_vxs', true, false )
			. '<input type="hidden" name="velox_shop_action" value="checkout">'
			. '<input type="hidden" name="vx_return" value="' . esc_url( home_url( add_query_arg( array() ) ) ) . '">'
			. '<input type="hidden" name="vx_needs_addr" value="' . ( $t['needs_ship'] ? '1' : '' ) . '" data-vxs-needs>'
			. '<input type="hidden" name="vx_src" value="" data-vxs-src>'
			. '<div class="vxs-hp" aria-hidden="true"><label>Website <input type="text" name="vx_website" tabindex="-1" autocomplete="off"></label></div>'
			. '<div class="vxs-co-main">';
		// 1 — contact
		$out .= '<section class="vxs-step"><h2><span class="vxs-step-n">1</span>' . esc_html__( 'Contact', 'velox' ) . '</h2>';
		if ( ! ( $u && $u->ID ) && (int) Velox_Shop::get( 'page_account' ) ) {
			$out .= '<p class="vxs-hint">' . esc_html__( 'Ordered before?', 'velox' ) . ' <a href="' . esc_url( add_query_arg( 'redirect_to', rawurlencode( self::page_url( 'checkout' ) ), self::page_url( 'account' ) ) ) . '">' . esc_html__( 'Sign in', 'velox' ) . '</a></p>';
		}
		$out .= '<div class="vxs-fg">' . $field( 'vx_email', __( 'Email', 'velox' ), 'email', true, 'email' ) . '</div>';
		if ( 'hidden' !== $phone ) {
			$out .= '<div class="vxs-fg">' . $field( 'vx_phone', __( 'Phone', 'velox' ), 'tel', 'required' === $phone, 'tel' ) . '</div>';
		}
		$out .= Velox_Shop_Account::checkout_box( $P, $E );
		if ( ! is_user_logged_in() ) {
			$out .= '<p class="vxs-co-signin">' . esc_html__( 'Already a customer?', 'velox' ) . ' <a href="' . esc_url( Velox_Shop_Account::url( 'login', array( 'redirect_to' => rawurlencode( get_permalink() ) ) ) ) . '">' . esc_html__( 'Sign in', 'velox' ) . '</a></p>';
		}
		$out .= '</section>';
		// 2 — address
		$out .= '<section class="vxs-step"><h2><span class="vxs-step-n">2</span>' . ( $t['needs_ship'] ? esc_html__( 'Delivery', 'velox' ) : esc_html__( 'Billing address', 'velox' ) ) . '</h2>'
			. '<div class="vxs-fg vxs-fg-2">' . $field( 'vx_first', __( 'First name', 'velox' ), 'text', true, 'given-name' ) . $field( 'vx_last', __( 'Last name', 'velox' ), 'text', true, 'family-name' ) . '</div>';
		if ( 'hidden' !== $company ) {
			$out .= '<div class="vxs-fg">' . $field( 'vx_company', __( 'Company', 'velox' ), 'text', 'required' === $company, 'organization' ) . '</div>';
		}
		$addr_req = $t['needs_ship'];
		$out .= '<div class="vxs-addr" data-vxs-addr>'
			. '<div class="vxs-fg">' . $field( 'vx_line1', __( 'Street and number', 'velox' ), 'text', $addr_req, 'address-line1' ) . '</div>'
			. '<div class="vxs-fg">' . $field( 'vx_line2', __( 'Address line 2', 'velox' ), 'text', false, 'address-line2' ) . '</div>'
			. '<div class="vxs-fg vxs-fg-zip">' . $field( 'vx_zip', __( 'Postcode', 'velox' ), 'text', $addr_req, 'postal-code' ) . $field( 'vx_city', __( 'Town', 'velox' ), 'text', $addr_req, 'address-level2' ) . '</div>'
			. '<div class="vxs-fg">' . $sel_country( 'vx_country', $country ) . '</div></div>';
		if ( $t['needs_ship'] ) {
			$other = ! empty( $P['vx_other'] );
			$out .= '<label class="vxs-check"><input type="checkbox" name="vx_other" value="1" data-vxs-other' . checked( $other, true, false ) . '><span>' . esc_html__( 'Deliver to a different address', 'velox' ) . '</span></label>'
				. '<div class="vxs-other" data-vxs-otherbox' . ( $other ? '' : ' hidden' ) . '>'
				. '<div class="vxs-fg vxs-fg-2">' . $field( 'vx_s_first', __( 'First name', 'velox' ), 'text', false, 'shipping given-name' ) . $field( 'vx_s_last', __( 'Last name', 'velox' ), 'text', false, 'shipping family-name' ) . '</div>'
				. '<div class="vxs-fg">' . $field( 'vx_s_company', __( 'Company', 'velox' ), 'text', false, 'shipping organization' ) . '</div>'
				. '<div class="vxs-fg">' . $field( 'vx_s_line1', __( 'Street and number', 'velox' ), 'text', false, 'shipping address-line1' ) . '</div>'
				. '<div class="vxs-fg">' . $field( 'vx_s_line2', __( 'Address line 2', 'velox' ), 'text', false, 'shipping address-line2' ) . '</div>'
				. '<div class="vxs-fg vxs-fg-zip">' . $field( 'vx_s_zip', __( 'Postcode', 'velox' ), 'text', false, 'shipping postal-code' ) . $field( 'vx_s_city', __( 'Town', 'velox' ), 'text', false, 'shipping address-level2' ) . '</div>'
				. '<div class="vxs-fg">' . $sel_country( 'vx_s_country', strtoupper( (string) ( $P['vx_s_country'] ?? $country ) ) ) . '</div></div>';
			$out .= '<h3 class="vxs-sub">' . esc_html__( 'Shipping method', 'velox' ) . '</h3><div data-vxs-ship>' . self::ship_options_html( $t ) . '</div>';
		}
		$out .= '</section>';
		// 3 — payment
		$out .= '<section class="vxs-step"><h2><span class="vxs-step-n">3</span>' . esc_html__( 'Payment', 'velox' ) . '</h2>';
		if ( ! $methods ) {
			$out .= '<p class="vxs-note is-err">' . esc_html__( 'Ordering is not possible right now. Please contact us.', 'velox' ) . '</p>';
		}
		$cur_pay = in_array( (string) ( $P['vx_pay'] ?? '' ), $methods, true ) ? (string) $P['vx_pay'] : ( $methods ? $methods[0] : '' );
		$out .= '<div class="vxs-pays" role="radiogroup" aria-label="' . esc_attr__( 'Payment method', 'velox' ) . '">';
		foreach ( Velox_Shop_Pay::methods() as $m => $info ) {
			if ( ! in_array( $m, $methods, true ) && 'cod' !== $m ) {
				continue;
			}
			$hide = ! in_array( $m, $methods, true );
			if ( $hide && ! Velox_Shop_Pay::ready( 'cod' ) ) {
				continue;
			}
			$out .= '<label class="vxs-pay" data-vxs-pay="' . esc_attr( $m ) . '"' . ( $hide ? ' hidden' : '' ) . '><input type="radio" name="vx_pay" value="' . esc_attr( $m ) . '"' . checked( $cur_pay, $m, false ) . ' data-vxs-quote>'
				. '<span class="vxs-pay-b"><b>' . esc_html( $info[0] ) . '</b><small>' . esc_html( $info[1] ) . ( 'invoice' === $m && Velox_Shop::get( 'invoice_known' ) ? ' ' . esc_html__( 'For returning customers.', 'velox' ) : '' ) . '</small></span>' . self::pay_icon( $m ) . '</label>';
		}
		$out .= '</div>' . $err( 'vx_pay' );
		if ( Velox_Shop::get( 'f_note' ) ) {
			$out .= '<details class="vxs-notebox"' . ( ! empty( $P['vx_note'] ) ? ' open' : '' ) . '><summary>' . esc_html__( 'Add a note to your order', 'velox' ) . '</summary><textarea name="vx_note" rows="3" maxlength="1000">' . esc_textarea( (string) ( $P['vx_note'] ?? '' ) ) . '</textarea></details>';
		}
		$out .= '</section></div>';
		// Summary + the legally required overview directly above the button.
		$out .= '<aside class="vxs-co-sum"><div class="vxs-co-card"><h2>' . esc_html__( 'Your order', 'velox' ) . '</h2><ul class="vxs-mini">';
		foreach ( $lines as $l ) {
			$out .= '<li><span class="vxs-mini-im">' . ( $l['thumb'] ? '<img src="' . esc_url( $l['thumb'] ) . '" alt="" loading="lazy">' : '' ) . '<i>' . (int) $l['qty'] . '</i></span><span class="vxs-mini-b"><b>' . esc_html( $l['name'] ) . '</b>' . ( $l['variant'] ? '<small>' . esc_html( $l['variant'] ) . '</small>' : '' ) . ( $l['delivery'] && $l['ship'] ? '<small>' . esc_html__( 'Delivery', 'velox' ) . ': ' . esc_html( $l['delivery'] ) . '</small>' : '' ) . '</span><span class="vxs-mini-p">' . self::money( $l['total'] ) . '</span></li>';
		}
		$out .= '</ul><div class="vxs-coupon"><input type="text" name="vx_coupon" value="' . esc_attr( $t['coupon'] ? $t['coupon'] : (string) ( $P['vx_coupon'] ?? ( $cart['_c'] ?? '' ) ) ) . '" placeholder="' . esc_attr__( 'Discount code', 'velox' ) . '" aria-label="' . esc_attr__( 'Discount code', 'velox' ) . '" data-vxs-coupon><button type="button" class="vxs-btn vxs-btn-ghost" data-vxs-couponbtn>' . esc_html__( 'Apply', 'velox' ) . '</button></div><p class="vxs-err" data-vxs-couponmsg>' . esc_html( $t['coupon_msg'] ) . '</p>'
			. '<div data-vxs-totals>' . self::totals_html( $lines, $t ) . '</div>' . $err( 'vx_total' );
		$terms = (int) Velox_Shop::get( 'page_terms' );
		$wd    = (int) Velox_Shop::get( 'page_withdrawal' );
		$priv  = (int) Velox_Shop::get( 'page_privacy' );
		$link  = function ( $id, $label ) {
			return $id ? '<a href="' . esc_url( get_permalink( $id ) ) . '" target="_blank" rel="noopener">' . esc_html( $label ) . '</a>' : esc_html( $label );
		};
		if ( Velox_Shop::get( 'terms_check' ) ) {
			/* translators: 1: terms link 2: withdrawal policy link */
			$out .= '<label class="vxs-check' . ( isset( $E['vx_terms'] ) ? ' is-err' : '' ) . '"><input type="checkbox" name="vx_terms" value="1" required' . checked( ! empty( $P['vx_terms'] ), true, false ) . '><span>' . sprintf( esc_html__( 'I accept the %1$s and have read the %2$s.', 'velox' ), $link( $terms, __( 'terms and conditions', 'velox' ) ), $link( $wd, __( 'withdrawal policy', 'velox' ) ) ) . '</span></label>' . $err( 'vx_terms' );
		}
		if ( $t['digital'] ) {
			$out .= '<label class="vxs-check' . ( isset( $E['vx_digital'] ) ? ' is-err' : '' ) . '"><input type="checkbox" name="vx_digital" value="1" required><span>' . esc_html__( 'I agree that the download starts before the withdrawal period ends, and I know that this ends my right of withdrawal for the download.', 'velox' ) . '</span></label>' . $err( 'vx_digital' );
		}
		$out .= '<button type="submit" class="vxs-btn vxs-btn-lg vxs-btn-block vxs-order-btn" data-vxs-submit' . ( $methods ? '' : ' disabled' ) . '>' . esc_html( $btn ) . '<span class="vxs-order-total" data-vxs-total>' . self::money( $t['total'] ) . '</span></button>';
		if ( $priv ) {
			/* translators: %s: privacy policy link */
			$out .= '<p class="vxs-fine">' . sprintf( esc_html__( 'How we handle your data: %s.', 'velox' ), $link( $priv, __( 'privacy policy', 'velox' ) ) ) . '</p>';
		}
		// Only when card or PayPal can actually be chosen: a shop with bank
		// transfer and invoice only would otherwise promise something it doesn't offer.
		if ( array_intersect( $methods, array( 'stripe', 'paypal' ) ) ) {
			$out .= '<p class="vxs-fine vxs-secure"><svg viewBox="0 0 16 16" width="13" height="13" fill="currentColor" aria-hidden="true"><path d="M8 1a3 3 0 0 0-3 3v2H4a1 1 0 0 0-1 1v6a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V7a1 1 0 0 0-1-1h-1V4a3 3 0 0 0-3-3m-2 3a2 2 0 1 1 4 0v2H6z"/></svg> ' . esc_html__( 'Card and PayPal payments happen on the provider’s secure page. We never see your card details.', 'velox' ) . '</p>';
		}
		return $out . '</div></aside></form>';
	}

	public static function pay_icon( $m ) {
		$i = array(
			'stripe'  => '<svg viewBox="0 0 24 16" width="30" height="20" aria-hidden="true"><rect x=".5" y=".5" width="23" height="15" rx="3" fill="none" stroke="currentColor"/><path d="M1 5h22" stroke="currentColor" stroke-width="2"/><rect x="4" y="10" width="6" height="2" rx="1" fill="currentColor"/></svg>',
			'paypal'  => '<svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path fill="currentColor" d="M8.3 21H5.2a.6.6 0 0 1-.6-.7L7.4 3.5A.9.9 0 0 1 8.3 3h6c3.4 0 5.3 1.7 4.8 4.8-.6 3.6-2.9 5.3-6.2 5.3h-1.7a.9.9 0 0 0-.9.8z"/></svg>',
			'bank'    => '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M3 10h18L12 4zM5 10v8m4-8v8m6-8v8m4-8v8M3 20h18"/></svg>',
			'invoice' => '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M6 3h9l4 4v14H6z"/><path d="M9 11h7M9 15h7M9 7h3"/></svg>',
			'cod'     => '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><rect x="3" y="6" width="18" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/></svg>',
		);
		return '<span class="vxs-pay-ic">' . ( $i[ $m ] ?? '' ) . '</span>';
	}

	public static function ship_options_html( $t ) {
		if ( empty( $t['needs_ship'] ) ) {
			return '';
		}
		if ( empty( $t['ship_opts'] ) ) {
			return '<p class="vxs-note is-err">' . esc_html__( 'We do not deliver to this country yet.', 'velox' ) . '</p>';
		}
		$cur = $t['shipping'] ? $t['shipping']['id'] : '';
		$out = '<div class="vxs-ships">';
		foreach ( $t['ship_opts'] as $s ) {
			$cost = $t['coupon_ship'] ? 0 : $s['cost'];
			$out .= '<label class="vxs-shipopt"><input type="radio" name="vx_ship" value="' . esc_attr( $s['id'] ) . '"' . checked( $cur, $s['id'], false ) . ' data-vxs-quote><span class="vxs-ship-b"><b>' . esc_html( $s['name'] ) . '</b>'
				. ( $s['days'] ? '<small>' . sprintf( /* translators: %s: days */ esc_html__( '%s working days', 'velox' ), esc_html( $s['days'] ) ) . '</small>' : '' )
				. ( 'pickup' === $s['type'] && ! empty( $s['address'] ) ? '<small>' . esc_html( $s['address'] ) . '</small>' : '' )
				. ( 'flat' === $s['type'] && isset( $s['free_above'] ) && null !== $s['free_above'] && $cost > 0 ? '<small>' . sprintf( /* translators: %s: amount */ esc_html__( 'Free from %s', 'velox' ), self::money( $s['free_above'] ) ) . '</small>' : '' )
				. '</span><span class="vxs-ship-p">' . ( $cost > 0 ? self::money( $cost ) : esc_html__( 'Free', 'velox' ) ) . '</span></label>';
		}
		return $out . '</div>';
	}

	/* ----------------------------------------------------------- the order */

	/** The thank-you page, and the order in the account area. */
	public static function order_view( $o, $fresh = false ) {
		$pay = $o['payment']['status'];
		$ful = $o['fulfil']['status'];
		$name = trim( $o['customer']['first'] );
		$out  = '<div class="vxs-order">';
		if ( $fresh && 'cancelled' !== $pay ) {
			$out .= '<div class="vxs-order-hero"><span class="vxs-tick" aria-hidden="true"><svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12.5 4.5 4.5L19 7.5"/></svg></span>'
				. '<h2>' . ( '' !== $name ? sprintf( /* translators: %s: first name */ esc_html__( 'Thank you, %s!', 'velox' ), esc_html( $name ) ) : esc_html__( 'Thank you!', 'velox' ) ) . '</h2>'
				/* translators: 1: order number 2: email */
				. '<p>' . sprintf( esc_html__( 'Your order %1$s is in. A confirmation is on its way to %2$s.', 'velox' ), '<b>' . esc_html( $o['number'] ) . '</b>', esc_html( $o['customer']['email'] ) ) . '</p></div>';
		} else {
			$out .= '<h2 class="vxs-order-h">' . esc_html__( 'Order', 'velox' ) . ' ' . esc_html( $o['number'] ) . ' <small>' . esc_html( wp_date( get_option( 'date_format' ), (int) $o['created'] ) ) . '</small></h2>';
		}
		// "Being prepared" only once it is paid (or paid on delivery / by invoice).
		$show_ful = 'none' !== $ful && ( 'unfulfilled' !== $ful || in_array( $pay, array( 'paid', 'partial' ), true ) || in_array( $o['payment']['method'], array( 'cod', 'invoice' ), true ) );
		$out .= '<div class="vxs-chips">' . self::status_chip( 'pay', $pay ) . ( $show_ful ? self::status_chip( 'ful', $ful ) : '' ) . '</div>';
		$out .= Velox_Shop_Pay::instructions_html( $o );
		if ( 'shipped' === $ful && $o['fulfil']['tracking'] ) {
			$tu = Velox_Shop::tracking_url( $o['fulfil']['carrier'], $o['fulfil']['tracking'] );
			$out .= '<div class="vxs-pay-box"><b>' . esc_html__( 'Tracking', 'velox' ) . ':</b> ' . esc_html( trim( $o['fulfil']['carrier'] . ' ' . $o['fulfil']['tracking'] ) ) . ( $tu ? ' <a href="' . esc_url( $tu ) . '" target="_blank" rel="noopener">' . esc_html__( 'Track the parcel', 'velox' ) . '</a>' : '' ) . '</div>';
		}
		$dl = 'paid' === $pay ? self::download_links( $o ) : array();
		if ( $dl ) {
			$out .= '<div class="vxs-pay-box"><b>' . esc_html__( 'Your downloads', 'velox' ) . '</b><ul class="vxs-dl">';
			foreach ( $dl as $l ) {
				$out .= '<li><a href="' . esc_url( $l['url'] ) . '">' . esc_html( $l['name'] ) . '</a></li>';
			}
			$out .= '</ul></div>';
		}
		$out .= '<ul class="vxs-mini">';
		foreach ( $o['items'] as $i ) {
			$out .= '<li><span class="vxs-mini-b"><b>' . esc_html( $i['name'] ) . '</b>' . ( ! empty( $i['variant'] ) ? '<small>' . esc_html( $i['variant'] ) . '</small>' : '' ) . '</span><span class="vxs-mini-q">× ' . (int) $i['qty'] . '</span><span class="vxs-mini-p">' . self::money( $i['total'] ) . '</span></li>';
		}
		$t = array( 'subtotal' => $o['subtotal'], 'discount' => $o['discount'], 'coupon' => $o['coupon'], 'needs_ship' => (bool) $o['shipping'] && 'none' !== ( $o['shipping']['id'] ?? '' ), 'shipping' => $o['shipping'], 'fee' => $o['fee'], 'total' => $o['total'], 'tax_lines' => $o['tax_lines'], 'country' => '' );
		$out .= '</ul>' . self::totals_html( $o['items'], $t );
		$addr = Velox_Shop::address_html( $o['ship_to'] ? $o['ship_to'] : $o['customer'] );
		$out .= '<div class="vxs-addrs"><div><h3>' . esc_html__( 'Billing address', 'velox' ) . '</h3><p>' . Velox_Shop::address_html( $o['customer'] ) . '</p></div>'
			. ( $o['shipping'] && 'pickup' !== ( $o['shipping']['type'] ?? '' ) && 'none' !== ( $o['shipping']['id'] ?? '' ) ? '<div><h3>' . esc_html__( 'Delivery address', 'velox' ) . '</h3><p>' . $addr . '</p></div>' : '' ) . '</div>';
		$links = array();
		if ( ! empty( $o['invoice']['number'] ) ) {
			$links[] = '<a href="' . esc_url( add_query_arg( 'vxinv', $o['key'], home_url( '/' ) ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'Invoice', 'velox' ) . ' ' . esc_html( $o['invoice']['number'] ) . '</a>';
		}
		if ( Velox_Shop::get( 'withdraw_on' ) && Velox_Shop::can_withdraw( $o ) ) {
			$links[] = '<a href="' . esc_url( add_query_arg( array( 'vxw' => $o['key'] ), self::withdraw_url() ) ) . '">' . esc_html__( 'Withdraw from the contract', 'velox' ) . '</a>';
		}
		if ( ! empty( $o['withdrawal'] ) ) {
			/* translators: %s: date */
			$links[] = '<span>' . sprintf( esc_html__( 'Withdrawn on %s', 'velox' ), esc_html( wp_date( get_option( 'date_format' ), (int) $o['withdrawal']['at'] ) ) ) . '</span>';
		}
		if ( $links ) {
			$out .= '<p class="vxs-order-links">' . implode( ' · ', $links ) . '</p>';
		}
		// A guest who just ordered: one click to an account with the same email.
		$email = (string) ( $o['customer']['email'] ?? '' );
		if ( $fresh && ! is_user_logged_in() && Velox_Shop_Account::enabled() && is_email( $email ) && ! email_exists( $email ) ) {
			$out .= '<div class="vxs-acc-cta"><div><b>' . esc_html__( 'Keep track of your orders', 'velox' ) . '</b><span>' . esc_html__( 'Create an account with this email — this order shows up in it right away.', 'velox' ) . '</span></div><a class="vxs-btn vxs-btn-sm" href="' . esc_url( Velox_Shop_Account::url( 'register', array( 'email' => rawurlencode( $email ) ) ) ) . '">' . esc_html__( 'Create an account', 'velox' ) . '</a></div>';
		}
		if ( $fresh ) {
			$out .= '<a class="vxs-btn vxs-btn-ghost" href="' . esc_url( self::page_url( 'shop' ) ) . '">' . esc_html__( 'Continue shopping', 'velox' ) . '</a>';
		}
		return $out . '</div>';
	}

	public static function status_chip( $axis, $s ) {
		$pay = array( 'pending' => __( 'Awaiting payment', 'velox' ), 'paid' => __( 'Paid', 'velox' ), 'partial' => __( 'Partly refunded', 'velox' ), 'refunded' => __( 'Refunded', 'velox' ), 'cancelled' => __( 'Cancelled', 'velox' ), 'failed' => __( 'Payment failed', 'velox' ) );
		$ful = array( 'unfulfilled' => __( 'Being prepared', 'velox' ), 'shipped' => __( 'Shipped', 'velox' ), 'ready' => __( 'Ready for pickup', 'velox' ), 'delivered' => __( 'Delivered', 'velox' ), 'cancelled' => __( 'Cancelled', 'velox' ), 'done' => __( 'Complete', 'velox' ), 'none' => '' );
		$l = 'pay' === $axis ? ( $pay[ $s ] ?? $s ) : ( $ful[ $s ] ?? $s );
		$tone = in_array( $s, array( 'paid', 'shipped', 'delivered', 'done', 'ready' ), true ) ? 'ok' : ( in_array( $s, array( 'cancelled', 'refunded', 'failed' ), true ) ? 'off' : 'wait' );
		return '' === $l ? '' : '<span class="vxs-chip-s is-' . $tone . '">' . esc_html( $l ) . '</span>';
	}

	/* -------------------------------------------------------------- account */

	public static function sc_account() {
		if ( ! empty( $_GET['vxo'] ) ) {
			$o = Velox_Shop::order_by_key( sanitize_text_field( wp_unslash( $_GET['vxo'] ) ) );
			$u = wp_get_current_user();
			$own = $o && $u->exists() && ( (int) ( $o['user'] ?? 0 ) === (int) $u->ID || strtolower( (string) ( $o['customer']['email'] ?? '' ) ) === strtolower( $u->user_email ) );
			if ( $own ) {
				// The customer's own order: show it inside the account, with its navigation.
				$_GET['vxa'] = 'orders';
			} elseif ( $o ) {
				return '<p><a href="' . esc_url( remove_query_arg( 'vxo' ) ) . '">← ' . esc_html__( 'All orders', 'velox' ) . '</a></p>' . self::order_view( $o );
			}
		}
		return Velox_Shop_Account::render();
	}

	/* ----------------------------------------------------------- withdrawal */

	public static function withdraw_url() {
		$id = (int) Velox_Shop::get( 'page_withdraw', 0 );
		if ( $id && 'publish' === get_post_status( $id ) ) {
			return get_permalink( $id );
		}
		$found = get_posts( array( 'post_type' => 'page', 'post_status' => 'publish', 'numberposts' => 1, 'fields' => 'ids', 's' => '[velox_withdraw' ) );
		if ( $found ) {
			return get_permalink( $found[0] );
		}
		return self::page_url( 'account' );
	}

	/**
	 * The withdrawal function (§ 356a BGB). Step 1: which order (number +
	 * email, or straight from the order link). Step 2: confirm. Works without
	 * an account; a receipt goes out by email immediately.
	 */
	public static function sc_withdraw() {
		if ( ! empty( $_GET['vxwdone'] ) ) {
			return '<div class="vxs-note is-ok" role="status"><b>' . esc_html__( 'Your withdrawal has been received.', 'velox' ) . '</b> ' . esc_html__( 'A confirmation with date and time has been sent to your email address.', 'velox' ) . '</div>';
		}
		$o = null;
		if ( ! empty( $_GET['vxw'] ) ) {
			$o = Velox_Shop::order_by_key( sanitize_text_field( wp_unslash( $_GET['vxw'] ) ) );
		}
		$err = isset( $_GET['vxwerr'] ) ? '<p class="vxs-note is-err" role="alert">' . esc_html__( 'We could not find an order with this number and email address, or it can no longer be withdrawn.', 'velox' ) . '</p>' : '';
		$head = '<form method="post" class="vxs-withdraw">' . wp_nonce_field( 'velox_shop', '_vxs', true, false ) . '<input type="hidden" name="velox_shop_action" value="withdraw"><input type="hidden" name="vx_return" value="' . esc_url( home_url( add_query_arg( array() ) ) ) . '">';
		if ( $o && Velox_Shop::can_withdraw( $o ) ) {
			return $head . '<input type="hidden" name="vx_key" value="' . esc_attr( $o['key'] ) . '"><h2>' . esc_html__( 'Withdraw from the contract', 'velox' ) . '</h2>'
				/* translators: 1: order number 2: date */
				. '<p>' . sprintf( esc_html__( 'Order %1$s from %2$s', 'velox' ), '<b>' . esc_html( $o['number'] ) . '</b>', esc_html( wp_date( get_option( 'date_format' ), (int) $o['created'] ) ) ) . '</p>'
				. '<label class="vxs-f"><span>' . esc_html__( 'Message', 'velox' ) . ' <small>' . esc_html__( 'optional', 'velox' ) . '</small></span><textarea name="vx_msg" rows="3" maxlength="2000"></textarea></label>'
				. '<button type="submit" class="vxs-btn vxs-btn-lg">' . esc_html__( 'Confirm withdrawal', 'velox' ) . '</button></form>';
		}
		return $head . '<h2>' . esc_html__( 'Withdraw from the contract', 'velox' ) . '</h2><p>' . esc_html__( 'Enter your order number and the email address you ordered with. You confirm on the next step.', 'velox' ) . '</p>' . $err
			. '<div class="vxs-fg vxs-fg-2"><label class="vxs-f"><span>' . esc_html__( 'Order number', 'velox' ) . '</span><input type="text" name="vx_number" required></label><label class="vxs-f"><span>' . esc_html__( 'Email', 'velox' ) . '</span><input type="email" name="vx_email" required autocomplete="email"></label></div>'
			. '<button type="submit" class="vxs-btn">' . esc_html__( 'Continue', 'velox' ) . '</button></form>';
	}

	public static function handle_withdraw() {
		if ( ! isset( $_POST['_vxs'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_vxs'] ) ), 'velox_shop' ) ) {
			return;
		}
		$back = ! empty( $_POST['vx_return'] ) ? esc_url_raw( wp_unslash( $_POST['vx_return'] ) ) : home_url( '/' );
		$back = remove_query_arg( array( 'vxw', 'vxwerr', 'vxwdone' ), $back );
		if ( ! empty( $_POST['vx_key'] ) ) {
			$o = Velox_Shop::order_by_key( sanitize_text_field( wp_unslash( $_POST['vx_key'] ) ) );
			if ( $o && Velox_Shop::can_withdraw( $o ) ) {
				Velox_Shop::withdraw( $o, (string) wp_unslash( $_POST['vx_msg'] ?? '' ) );
				wp_safe_redirect( add_query_arg( 'vxwdone', '1', $back ) );
				exit;
			}
			wp_safe_redirect( add_query_arg( 'vxwerr', '1', $back ) );
			exit;
		}
		$o = Velox_Shop::order_by_number( sanitize_text_field( wp_unslash( $_POST['vx_number'] ?? '' ) ), sanitize_email( wp_unslash( $_POST['vx_email'] ?? '' ) ) );
		if ( $o && Velox_Shop::can_withdraw( $o ) ) {
			wp_safe_redirect( add_query_arg( 'vxw', $o['key'], $back ) );
			exit;
		}
		wp_safe_redirect( add_query_arg( 'vxwerr', '1', $back ) );
		exit;
	}

	/* ------------------------------------------------------------ downloads */

	public static function download_links( $o ) {
		if ( 'paid' !== $o['payment']['status'] || ! $o['key'] ) {
			return array();
		}
		$out = array();
		foreach ( $o['items'] as $i ) {
			if ( 'digital' !== ( $i['type'] ?? '' ) ) {
				continue;
			}
			$p = Velox_Shop::product( $i['pid'] );
			foreach ( $p ? $p['files'] : array() as $f ) {
				$out[] = array(
					'name' => get_the_title( $f ) ? get_the_title( $f ) : basename( (string) get_attached_file( $f ) ),
					'url'  => add_query_arg( array( 'vxdl' => $o['key'], 'f' => (int) $f, 's' => substr( wp_hash( $o['key'] . '|' . $f, 'nonce' ), 0, 16 ) ), home_url( '/' ) ),
				);
			}
		}
		return $out;
	}

	private static function serve_download() {
		$key = sanitize_text_field( wp_unslash( $_GET['vxdl'] ) );
		$f   = (int) ( $_GET['f'] ?? 0 );
		$sig = sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) );
		$o   = Velox_Shop::order_by_key( $key );
		if ( ! $o || 'paid' !== $o['payment']['status'] || ! hash_equals( substr( wp_hash( $o['key'] . '|' . $f, 'nonce' ), 0, 16 ), $sig ) ) {
			wp_die( esc_html__( 'This download link is not valid.', 'velox' ), '', array( 'response' => 403 ) );
		}
		$ok = false;
		foreach ( $o['items'] as $i ) {
			$p = Velox_Shop::product( $i['pid'] );
			if ( $p && in_array( $f, $p['files'], true ) ) {
				$ok = true;
			}
		}
		$path = $ok ? get_attached_file( $f ) : '';
		if ( ! $path || ! is_readable( $path ) ) {
			wp_die( esc_html__( 'This file is no longer available.', 'velox' ), '', array( 'response' => 404 ) );
		}
		nocache_headers();
		header( 'Content-Type: ' . ( get_post_mime_type( $f ) ? get_post_mime_type( $f ) : 'application/octet-stream' ) );
		header( 'Content-Disposition: attachment; filename="' . rawurlencode( basename( $path ) ) . '"' );
		header( 'Content-Length: ' . filesize( $path ) );
		header( 'X-Robots-Tag: noindex' );
		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}

	/* -------------------------------------------------------------- invoice */

	/** A printable invoice (§ 14 UStG fields). "Print / save as PDF" from the browser. */
	public static function invoice_html( $o ) {
		$s   = Velox_Shop::settings();
		$cs  = Velox_Shop::countries();
		$logo = (int) $s['logo'] ? wp_get_attachment_image_url( (int) $s['logo'], 'medium' ) : '';
		$rows = '';
		foreach ( $o['items'] as $i ) {
			$rows .= '<tr><td>' . esc_html( $i['name'] ) . ( ! empty( $i['variant'] ) ? '<br><small>' . esc_html( $i['variant'] ) . '</small>' : '' ) . ( ! empty( $i['sku'] ) ? '<br><small>' . esc_html( $i['sku'] ) . '</small>' : '' ) . '</td><td class="n">' . (int) $i['qty'] . '</td><td class="n">' . self::money( $i['unit'] ) . '</td>' . ( empty( $o['small'] ) ? '<td class="n">' . esc_html( rtrim( rtrim( number_format_i18n( (float) $i['rate'], 2 ), '0' ), ',.' ) ) . ' %</td>' : '' ) . '<td class="n">' . self::money( $i['total'] ) . '</td></tr>';
		}
		$cols = empty( $o['small'] ) ? 5 : 4;
		if ( $o['discount'] > 0 ) {
			$rows .= '<tr><td colspan="' . ( $cols - 1 ) . '">' . esc_html__( 'Discount', 'velox' ) . ' ' . esc_html( $o['coupon'] ) . '</td><td class="n">− ' . self::money( $o['discount'] ) . '</td></tr>';
		}
		if ( $o['shipping'] && $o['shipping']['cost'] > 0 ) {
			$rows .= '<tr><td colspan="' . ( $cols - 1 ) . '">' . esc_html( $o['shipping']['name'] ) . '</td><td class="n">' . self::money( $o['shipping']['cost'] ) . '</td></tr>';
		}
		if ( $o['fee'] > 0 ) {
			$rows .= '<tr><td colspan="' . ( $cols - 1 ) . '">' . esc_html__( 'Cash on delivery fee', 'velox' ) . '</td><td class="n">' . self::money( $o['fee'] ) . '</td></tr>';
		}
		$sum = '';
		if ( empty( $o['small'] ) ) {
			$tax = 0;
			foreach ( (array) $o['tax_lines'] as $r => $a ) {
				$tax += $a;
				/* translators: %s: rate */
				$sum .= '<tr><td>' . sprintf( esc_html__( 'VAT %s %%', 'velox' ), esc_html( rtrim( rtrim( number_format_i18n( (float) $r, 2 ), '0' ), ',.' ) ) ) . '</td><td class="n">' . self::money( $a ) . '</td></tr>';
			}
			$sum = '<tr><td>' . esc_html__( 'Net amount', 'velox' ) . '</td><td class="n">' . self::money( $o['total'] - $tax ) . '</td></tr>' . $sum;
		}
		$sum .= '<tr class="tot"><td>' . esc_html__( 'Total', 'velox' ) . '</td><td class="n">' . self::money( $o['total'] ) . '</td></tr>';
		$seller = array_filter( array( esc_html( $s['store_name'] ), nl2br( esc_html( $s['store_address'] ) ) ) );
		$ids    = array_filter( array( $s['store_vat_id'] ? esc_html__( 'VAT ID', 'velox' ) . ': ' . esc_html( $s['store_vat_id'] ) : '', $s['store_tax_no'] ? esc_html__( 'Tax number', 'velox' ) . ': ' . esc_html( $s['store_tax_no'] ) : '' ) );
		$cust   = Velox_Shop::address_html( $o['customer'] );
		$date   = wp_date( get_option( 'date_format' ), (int) $o['invoice']['date'] );
		$paid   = 'paid' === $o['payment']['status'] || 'partial' === $o['payment']['status'];
		return '<!doctype html><html lang="' . esc_attr( get_bloginfo( 'language' ) ) . '"><head><meta charset="utf-8"><title>' . esc_html__( 'Invoice', 'velox' ) . ' ' . esc_html( $o['invoice']['number'] ) . '</title><meta name="robots" content="noindex"><style>'
			. 'body{margin:0;background:#eef0f3;font:14px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;color:#111827}'
			. '.pg{max-width:780px;margin:30px auto;background:#fff;padding:56px 60px;box-shadow:0 2px 20px rgba(0,0,0,.06)}'
			. '.top{display:flex;justify-content:space-between;gap:30px;margin-bottom:46px}.top img{max-height:52px;max-width:220px}.seller{text-align:right;color:#4b5563;font-size:12.5px}'
			. '.to{font-size:13.5px;margin-bottom:34px}.small{font-size:10.5px;color:#6b7280;text-decoration:underline;margin-bottom:6px}'
			. 'h1{font-size:24px;margin:0 0 4px;letter-spacing:-.02em}.meta{display:grid;grid-template-columns:repeat(4,auto);gap:4px 26px;justify-content:start;color:#4b5563;font-size:12.5px;margin-bottom:26px}.meta b{display:block;color:#111827;font-weight:600}'
			. 'table{width:100%;border-collapse:collapse}th{text-align:left;font-size:11px;letter-spacing:.06em;text-transform:uppercase;color:#6b7280;border-bottom:1px solid #e5e7eb;padding:8px 6px}td{padding:10px 6px;border-bottom:1px solid #f0f1f3;vertical-align:top}td small{color:#6b7280}.n{text-align:right;white-space:nowrap}'
			. '.sum{width:300px;margin:18px 0 0 auto}.sum td{border:0;padding:4px 6px}.sum .tot td{font-weight:700;font-size:16px;border-top:2px solid #111827;padding-top:10px}'
			. '.foot{margin-top:40px;color:#4b5563;font-size:12.5px}.bar{max-width:780px;margin:20px auto 0;text-align:right}.bar button{font:inherit;padding:9px 18px;border-radius:8px;border:0;background:#111827;color:#fff;cursor:pointer}'
			. '@media print{body{background:#fff}.pg{margin:0;box-shadow:none;padding:0}.bar{display:none}}</style></head><body>'
			. '<div class="bar"><button onclick="window.print()">' . esc_html__( 'Print / save as PDF', 'velox' ) . '</button></div>'
			. '<div class="pg"><div class="top"><div>' . ( $logo ? '<img src="' . esc_url( $logo ) . '" alt="">' : '<b style="font-size:20px">' . esc_html( $s['store_name'] ) . '</b>' ) . '</div><div class="seller">' . implode( '<br>', $seller ) . ( $ids ? '<br>' . implode( '<br>', $ids ) : '' ) . '</div></div>'
			. '<div class="to"><div class="small">' . esc_html( $s['store_name'] ) . ' · ' . esc_html( str_replace( "\n", ', ', trim( (string) $s['store_address'] ) ) ) . '</div>' . $cust . '</div>'
			. '<h1>' . esc_html__( 'Invoice', 'velox' ) . '</h1>'
			. '<div class="meta"><span>' . esc_html__( 'Invoice number', 'velox' ) . '<b>' . esc_html( $o['invoice']['number'] ) . '</b></span><span>' . esc_html__( 'Invoice date', 'velox' ) . '<b>' . esc_html( $date ) . '</b></span><span>' . esc_html__( 'Order', 'velox' ) . '<b>' . esc_html( $o['number'] ) . '</b></span><span>' . esc_html__( 'Delivery date', 'velox' ) . '<b>' . esc_html__( 'same as invoice date', 'velox' ) . '</b></span></div>'
			. '<table><thead><tr><th>' . esc_html__( 'Item', 'velox' ) . '</th><th class="n">' . esc_html__( 'Qty', 'velox' ) . '</th><th class="n">' . esc_html__( 'Unit price', 'velox' ) . '</th>' . ( empty( $o['small'] ) ? '<th class="n">' . esc_html__( 'VAT', 'velox' ) . '</th>' : '' ) . '<th class="n">' . esc_html__( 'Amount', 'velox' ) . '</th></tr></thead><tbody>' . $rows . '</tbody></table>'
			. '<table class="sum">' . $sum . '</table>'
			. '<div class="foot">' . ( ! empty( $o['small'] ) ? '<p>' . esc_html( (string) $s['small_note'] ) . '</p>' : '' )
			. '<p>' . ( $paid ? esc_html__( 'Paid with thanks', 'velox' ) . ' — ' . esc_html( Velox_Shop_Pay::label( $o['payment']['method'] ) ) . '.' : sprintf( /* translators: %d: days */ esc_html__( 'Please pay within %d days, quoting the invoice number.', 'velox' ), (int) $s['invoice_days'] ) ) . '</p>'
			. ( ! $paid && $s['bank_iban'] ? '<p>IBAN ' . esc_html( $s['bank_iban'] ) . ( $s['bank_bic'] ? ' · BIC ' . esc_html( $s['bank_bic'] ) : '' ) . ( $s['bank_name'] ? ' · ' . esc_html( $s['bank_name'] ) : '' ) . '</p>' : '' )
			. '</div></div></body></html>';
	}

	/* ---------------------------------------------------------- dynamic data */

	/** {product.…} tokens for loops over products and for product pages. */
	public static function token( $token, $pid ) {
		$p = Velox_Shop::product( $pid );
		if ( ! $p ) {
			return '';
		}
		switch ( $token ) {
			case 'product.price':    return self::price_html( $p );
			case 'product.compare':  $c = Velox_Shop::compare_of( $p ); return $c ? self::money( $c ) : '';
			case 'product.badge':    return self::badge_html( $p );
			case 'product.stock':    $a = Velox_Shop::availability( $p ); return esc_html( $a[1] );
			case 'product.unit':     return esc_html( Velox_Shop::unit_price( $p ) );
			case 'product.delivery': return esc_html( $p['delivery'] );
			case 'product.sku':      return esc_html( $p['sku'] );
			case 'product.taxnote':  return self::tax_note( $p );
			case 'product.button':
				if ( Velox_Shop::can_buy( $p, null, 1 ) && ! Velox_Shop::has_variants( $p ) ) {
					return self::add_form( $p, '', '<button type="submit" class="vxs-btn vxs-btn-sm">' . esc_html( '' !== $p['button'] ? $p['button'] : __( 'Add to cart', 'velox' ) ) . '</button>' );
				}
				return '<a class="vxs-btn vxs-btn-sm vxs-btn-ghost" href="' . esc_url( get_permalink( $p['id'] ) ) . '">' . esc_html__( 'View', 'velox' ) . '</a>';
		}
		return '';
	}

	/** Velox Builder shop elements (also drawn in the editor through an AJAX preview). */
	public static function element( $el, $s ) {
		$g = function ( $k, $d = '' ) use ( $s ) {
			return isset( $s[ $k ] ) && '' !== (string) $s[ $k ] ? $s[ $k ] : $d;
		};
		switch ( $el ) {
			case 'Shopgrid':
				return self::grid( array( 'count' => $g( 'count', 12 ), 'columns' => $g( 'columns', '' ), 'category' => $g( 'category' ), 'sort' => $g( 'sort', 'menu' ), 'filter' => $g( 'filter' ), 'ids' => '', 'layout' => $g( 'layout' ), 'card' => $g( 'card' ), 'button' => $g( 'button' ), 'hover' => $g( 'hover' ) ) );
			case 'Shopbuy':
				$pid = (int) $g( 'product', 0 );
				$p   = Velox_Shop::product( $pid ? $pid : get_the_ID() );
				if ( ! $p ) {
					$any = get_posts( array( 'post_type' => Velox_Shop::PRODUCT, 'post_status' => 'publish', 'numberposts' => 1, 'fields' => 'ids' ) );
					$p   = $any && ( is_admin() || wp_doing_ajax() ) ? Velox_Shop::product( $any[0] ) : null;
				}
				return $p ? self::buy_box( $p, array( 'qty' => '0' !== (string) $g( 'qty', '1' ), 'button' => (string) $g( 'button' ) ) ) : '<p class="vxs-empty">' . esc_html__( 'Choose a product, or place this on a product page.', 'velox' ) . '</p>';
			case 'Shopproduct':
				$pid = (int) $g( 'product', 0 );
				$p   = Velox_Shop::product( $pid ? $pid : get_the_ID() );
				if ( ! $p && ( is_admin() || wp_doing_ajax() ) ) {
					$any = get_posts( array( 'post_type' => Velox_Shop::PRODUCT, 'post_status' => 'publish', 'numberposts' => 1, 'fields' => 'ids' ) );
					$p   = $any ? Velox_Shop::product( $any[0] ) : null;
				}
				return $p ? self::product_layout( $p, apply_filters( 'velox_shop_inner', (string) get_post_field( 'post_content', $p['id'] ) ) ) : '';
			case 'Shopcart':
				return self::sc_cart();
			case 'Shopcheckout':
				return self::sc_checkout();
			case 'Shopaccount':
				return self::sc_account();
			case 'Shopwithdraw':
				return self::sc_withdraw();
			case 'Shopaccountbtn':
				return Velox_Shop_Account::sc_button( array( 'label' => (string) $g( 'label' ), 'menu' => '0' === (string) $g( 'menu' ) ? '0' : '1' ) );
			case 'Shopcartbtn':
				return self::sc_cart_button( array( 'label' => (string) $g( 'label' ), 'icon' => (string) $g( 'icon' ), 'count' => (string) $g( 'count' ), 'total' => (string) $g( 'total' ) ) );
		}
		return '';
	}

	/** What the editor needs for the shop elements. */
	public static function editor_data() {
		$products = array();
		foreach ( get_posts( array( 'post_type' => Velox_Shop::PRODUCT, 'post_status' => 'publish', 'numberposts' => 200, 'orderby' => 'title', 'order' => 'ASC' ) ) as $p ) {
			$products[] = array( (string) $p->ID, $p->post_title );
		}
		$cats  = array();
		$terms = get_terms( array( 'taxonomy' => Velox_Shop::CAT, 'hide_empty' => false ) );
		if ( $terms && ! is_wp_error( $terms ) ) {
			foreach ( $terms as $t ) {
				$cats[] = array( $t->slug, $t->name );
			}
		}
		return array( 'css' => VELOX_URL . 'assets/css/velox-shop.css?v=' . VELOX_VERSION, 'products' => $products, 'cats' => $cats, 'admin' => admin_url( 'admin.php?page=velox-shop' ) );
	}

	/** The editor's live preview of a shop element: the real markup, nothing clickable. */
	public static function ajax_preview() {
		$el = sanitize_key( wp_unslash( $_POST['el'] ?? '' ) );
		$el = ucfirst( $el );
		$s  = json_decode( wp_unslash( (string) ( $_POST['settings'] ?? '{}' ) ), true );
		$post = (int) ( $_POST['post'] ?? 0 );
		if ( $post && get_post( $post ) ) {
			$GLOBALS['post'] = get_post( $post ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride
			setup_postdata( $GLOBALS['post'] );
		}
		// Cart and checkout show a sample line so their layout can be styled.
		if ( in_array( $el, array( 'Shopcart', 'Shopcheckout' ), true ) && ! Velox_Shop::lines() ) {
			$any = get_posts( array( 'post_type' => Velox_Shop::PRODUCT, 'post_status' => 'publish', 'numberposts' => 2, 'fields' => 'ids' ) );
			$fake = array();
			foreach ( $any as $id ) {
				$p = Velox_Shop::product( $id );
				if ( $p && Velox_Shop::can_buy( $p, null, 1 ) && ! Velox_Shop::has_variants( $p ) ) {
					$fake[ (string) $id ] = 1;
				}
			}
			$_COOKIE[ Velox_Shop::COOKIE ] = '';
			if ( $fake ) {
				$json = wp_json_encode( $fake );
				$_COOKIE[ Velox_Shop::COOKIE ] = base64_encode( $json ) . '|' . wp_hash( $json, 'nonce' );
			}
		}
		$html = self::element( $el, is_array( $s ) ? $s : array() );
		if ( '' === trim( $html ) ) {
			$html = '<p class="vxs-empty">' . esc_html__( 'Nothing to show yet — add products in Velox Shop.', 'velox' ) . '</p>';
		}
		wp_send_json_success( array( 'html' => $html ) );
	}

	/* --------------------------------------------------------------- assets */

	public static function assets() {
		wp_enqueue_style( 'velox-shop', VELOX_URL . 'assets/css/velox-shop.css', array(), VELOX_VERSION );
		// The design's colours and sizes, then the shop's own CSS (Settings → Design).
		$own = (string) Velox_Shop::get( 'custom_css', '' );
		wp_add_inline_style( 'velox-shop', ':root{' . Velox_Shop::design_vars() . '}' . ( '' !== trim( $own ) ? "\n" . str_ireplace( '</style', '', $own ) : '' ) );
		wp_enqueue_script( 'velox-shop', VELOX_URL . 'assets/js/velox-shop.js', array(), VELOX_VERSION, true );
		wp_localize_script( 'velox-shop', 'VELOX_SHOP', array(
			'rest'  => esc_url_raw( rest_url( 'velox/v1/shop/' ) ),
			'i18n'  => array(
				'cart'      => __( 'Your cart', 'velox' ),
				'empty'     => __( 'Your cart is empty.', 'velox' ),
				'subtotal'  => __( 'Subtotal', 'velox' ),
				'discount'  => __( 'Discount', 'velox' ),
				'checkout'  => __( 'Go to checkout', 'velox' ),
				'viewCart'  => __( 'View cart', 'velox' ),
				'close'     => __( 'Close', 'velox' ),
				'remove'    => __( 'Remove', 'velox' ),
				'added'     => __( 'Added to your cart', 'velox' ),
				'freeLeft'  => __( '%s more for free shipping', 'velox' ),
				'freeYes'   => __( 'You get free shipping', 'velox' ),
				'choose'    => __( 'Choose your options', 'velox' ),
				'unavail'   => __( 'This combination is not available', 'velox' ),
				'wait'      => __( 'One moment…', 'velox' ),
				'taxNote'   => __( 'Prices incl. VAT, shipping calculated at checkout.', 'velox' ),
				'fewer'     => __( 'Fewer', 'velox' ),
				'more'      => __( 'More', 'velox' ),
			),
		) );
	}

	/** The mini cart drawer, filled by velox-shop.js. */
	public static function footer() {
		echo '<div class="vxs-drawer" data-vxs-drawer hidden><div class="vxs-drawer-bg" data-vxs-close></div><aside class="vxs-drawer-p" role="dialog" aria-modal="true" aria-labelledby="vxs-drawer-t" tabindex="-1"></aside></div><div class="vxs-toast" data-vxs-toast role="status" aria-live="polite" hidden></div>';
	}
}
