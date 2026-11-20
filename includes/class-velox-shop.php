<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Velox Shop — the store core: settings, products, money and tax, the cart,
 * shipping, coupons, orders and their lifecycle, stock, numbers and emails.
 *
 * Payment providers live in Velox_Shop_Pay, everything a visitor sees in
 * Velox_Shop_Front and the shop editor in Velox_Shop_Admin.
 *
 * Ground rules (kept from the first version, because they are what keeps a
 * shop out of trouble):
 *  - Accounts are WordPress users. Nothing here stores or compares passwords.
 *  - No card data ever touches this code. Cards go through Stripe's or
 *    PayPal's own hosted pages; Velox only creates the order and is told by
 *    the provider (server to server) when it has been paid.
 *  - The cart is a signed cookie. Prices are always re-read from the product,
 *    so a forged cookie cannot change what anything costs.
 *  - An order is two things at once: is it paid, and has it been sent. Two
 *    separate statuses instead of WooCommerce's one overloaded one.
 *
 * @package Velox
 */
class Velox_Shop {

	const PRODUCT  = 'velox_product';
	const ORDER    = 'velox_order';
	const CAT      = 'velox_product_cat';
	const COOKIE   = 'velox_cart';
	const OPT      = 'velox_shop';
	const OPT_SEQ  = 'velox_shop_seq';
	const OPT_INV  = 'velox_shop_inv_seq';
	const OPT_CPN  = 'velox_shop_coupons';
	const META     = '_vxs';        // product data (JSON)
	const OMETA    = '_vxs_order';  // order data (JSON)
	const CRON     = 'velox_shop_tick';

	/* ------------------------------------------------------------------ boot */

	public static function init() {
		if ( ! Velox_Settings::get( 'util_shop' ) ) {
			return;
		}
		add_action( 'init', array( __CLASS__, 'register_types' ) );
		add_action( 'init', array( __CLASS__, 'register_meta' ) );
		add_action( self::CRON, array( __CLASS__, 'tick' ) );
		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time() + 300, 'hourly', self::CRON );
		}
		// Cart, checkout and account output is per-visitor: those pages, and any
		// page while something is in the cart, skip the page cache.
		add_filter( 'velox_cache_skip', array( __CLASS__, 'skip_cache' ) );
		// Product pages bring their whole layout through the_content, so a
		// Velox Builder template can frame them like any page (header, footer…).
		add_filter( 'velox_builder_wrap_post_types', array( __CLASS__, 'wrap_types' ) );

		if ( class_exists( 'Velox_Shop_Pay' ) ) {
			Velox_Shop_Pay::init();
		}
		if ( class_exists( 'Velox_Shop_Front' ) ) {
			Velox_Shop_Front::init();
			Velox_Shop_Account::init();
		}
		if ( is_admin() && class_exists( 'Velox_Shop_Admin' ) ) {
			Velox_Shop_Admin::init();
		}
	}

	public static function skip_cache( $skip ) {
		if ( $skip ) {
			return $skip;
		}
		if ( ! empty( $_COOKIE[ self::COOKIE ] ) ) {
			return true;
		}
		$post = get_post();
		if ( $post ) {
			$c = (string) $post->post_content;
			foreach ( array( 'velox_cart', 'velox_checkout', 'velox_account', 'velox_withdraw' ) as $sc ) {
				if ( has_shortcode( $c, $sc ) ) {
					return true;
				}
			}
			$s = self::settings();
			if ( in_array( (int) $post->ID, array( (int) $s['page_cart'], (int) $s['page_checkout'], (int) $s['page_account'] ), true ) ) {
				return true;
			}
		}
		return $skip;
	}

	/* -------------------------------------------------------------- settings */

	/** Every shop setting with its default. One place, one option. */
	public static function defaults() {
		$legacy_cur = (string) Velox_Settings::get( 'shop_currency', '€' );
		return array(
			// Store
			'store_name'    => get_bloginfo( 'name' ),
			'store_email'   => get_option( 'admin_email' ),
			'store_address' => '',
			'store_country' => 'DE',
			'store_vat_id'  => '',
			'store_tax_no'  => '',
			'logo'          => 0,
			'accent'        => '#0a6f9f',
			// Money
			'currency'      => 'EUR',
			'symbol'        => '' !== $legacy_cur ? $legacy_cur : '€',
			'symbol_pos'    => 'left' === Velox_Settings::get( 'shop_currency_pos', 'right' ) ? 'left' : 'right',
			// Tax: 'standard' = prices include VAT; 'small' = Kleinunternehmer (§ 19 UStG), no VAT.
			'tax_mode'      => 'standard',
			'rate_standard' => '19',
			'rate_reduced'  => '7',
			'small_note'    => __( 'According to § 19 UStG no VAT is charged.', 'velox' ),
			// Shipping: zones are matched by the delivery country, first match wins.
			'ship_countries' => array( 'DE', 'AT', 'CH' ),
			'zones'          => array(
				array(
					'id'        => 'z1',
					'name'      => __( 'Germany', 'velox' ),
					'countries' => array( 'DE' ),
					'rates'     => array(
						array( 'id' => 'r1', 'type' => 'flat', 'name' => __( 'Standard shipping', 'velox' ), 'cost' => '4.90', 'free_above' => '50', 'days' => '2–4' ),
					),
				),
				array(
					'id'        => 'z2',
					'name'      => __( 'Everywhere else', 'velox' ),
					'countries' => array(),
					'rates'     => array(
						array( 'id' => 'r2', 'type' => 'flat', 'name' => __( 'International shipping', 'velox' ), 'cost' => '14.90', 'free_above' => '', 'days' => '5–10' ),
					),
				),
			),
			'pickup'         => '',  // '1' = offer pickup
			'pickup_name'    => __( 'Pick up in store', 'velox' ),
			'pickup_address' => '',
			// Payments (Velox_Shop_Pay::methods() lists them; order = display order)
			'pay_order'       => array( 'stripe', 'paypal', 'bank', 'invoice', 'cod' ),
			'pay_bank'        => '1',
			'bank_owner'      => '',
			'bank_iban'       => '',
			'bank_bic'        => '',
			'bank_name'       => '',
			'bank_days'       => '10',  // cancel unpaid bank-transfer orders after N days (0 = never)
			'pay_invoice'     => '',
			'invoice_known'   => '1',   // invoice only for customers who ordered and paid before
			'invoice_days'    => '14',
			'pay_cod'         => '',
			'cod_fee'         => '',
			'pay_stripe'      => '',
			'stripe_secret'   => '',
			'stripe_whsec'    => '',
			'pay_paypal'      => '',
			'paypal_id'       => '',
			'paypal_secret'   => '',
			'paypal_sandbox'  => '1',
			// Checkout
			'guest'           => '1',
			'f_phone'         => 'optional', // hidden | optional | required
			'f_company'       => 'optional',
			'f_note'          => '1',
			'terms_check'     => '1',
			'button_text'     => '',  // empty = the legal default ("Buy now" / "Zahlungspflichtig bestellen")
			'hold_minutes'    => '60', // card/PayPal orders not paid by then are cancelled and their stock released
			'min_order'       => '',
			// Stock
			'low_stock'       => '3',
			'show_stock'      => 'low', // always | low | never
			// Pages
			'page_shop'       => 0,
			'page_cart'       => 0,
			'page_checkout'   => 0,
			'page_account'    => 0,
			'page_terms'      => 0,
			'page_withdrawal' => 0,
			'page_privacy'    => 0,
			'page_shipping'   => 0,
			'page_withdraw'   => 0,
			'product_base'    => '',
			'auto_layout'     => '1', // product pages get gallery, buy box and details automatically
			// Emails
			'mail_from_name'  => get_bloginfo( 'name' ),
			'mail_from'       => '',
			'mail_admin'      => get_option( 'admin_email' ),
			'mail_off'        => array(), // keys of emails that are switched off
			'mail_footer'     => '',
			// Numbers
			'order_prefix'    => '',
			'invoice_prefix'  => 'RE-{Y}-',
			// Withdrawal button (§ 356a BGB)
			'withdraw_on'     => '1',
			'withdraw_days'   => '14',
			'setup_done'      => '',
			'card_ratio'      => '', // '' = 4/5 (or the photos' own shape in shops from before 4.53) | 4/5 | 1/1 | natural
			// Design: a template plus any choice on top ('' = the template's value). See design().
			'design'          => 'velox',
			'd_layout' => '', 'd_cols' => '', 'd_cols_t' => '', 'd_cols_m' => '', 'd_gap' => '', 'd_card' => '', 'd_fit' => '', 'd_hover' => '', 'd_align' => '',
			'd_button' => '', 'd_btn_shape' => '', 'd_btn_style' => '', 'd_radius' => '', 'd_ink' => '', 'd_surface' => '', 'd_line' => '', 'd_soft' => '', 'd_bg' => '',
			'd_show_cat' => '', 'd_show_text' => '', 'd_product' => '', 'd_gallery' => '', 'd_sticky' => '', 'd_details' => '', 'd_checkout' => '',
			'd_cart_icon' => '', 'd_cart_count' => '', 'd_cart_total' => '',
			'custom_css'      => '', // the shop's own CSS, printed after the shop stylesheet
			// Customer accounts: sign-up on the account page, and while ordering.
			'acc_register'    => '1',
			'acc_checkout'    => '1',
		);
	}

	private static $settings = null;

	public static function settings() {
		if ( null === self::$settings ) {
			$saved = get_option( self::OPT, array() );
			$d     = self::defaults();
			self::$settings = is_array( $saved ) ? array_merge( $d, array_intersect_key( $saved, $d ) ) : $d;
			if ( '' === self::$settings['product_base'] ) {
				// Shops from before 4.53 sold art at /werk/…; keep their links.
				$old = get_posts( array( 'post_type' => self::PRODUCT, 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids' ) );
				self::$settings['product_base'] = $old ? 'werk' : 'product';
			}
		}
		return self::$settings;
	}

	public static function get( $key, $fallback = '' ) {
		$s = self::settings();
		return array_key_exists( $key, $s ) ? $s[ $key ] : $fallback;
	}

	/** Use these values for the rest of this request only (the design preview). */
	public static function override( $vals ) {
		self::settings();
		foreach ( (array) $vals as $k => $v ) {
			if ( array_key_exists( $k, self::$settings ) ) {
				self::$settings[ $k ] = $v;
			}
		}
	}

	/** Save some settings (already cleaned by the caller). */
	public static function save_settings( $changes ) {
		$cur = self::settings();
		$new = array_merge( $cur, array_intersect_key( (array) $changes, self::defaults() ) );
		update_option( self::OPT, $new, false );
		self::$settings = null;
		return self::settings();
	}

	/** A shop from before 4.53 that has not been set up in the shop editor yet. */
	public static function legacy_upgrade() {
		static $is = null;
		if ( null === $is ) {
			$is = ! get_option( self::OPT ) && (bool) get_posts( array( 'post_type' => self::PRODUCT, 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids' ) );
		}
		return $is;
	}

	/** How product photos sit in the grid. */
	/** velox_builder_wrap_post_types: products can sit in a Builder template. */
	public static function wrap_types( $types ) {
		$types   = (array) $types;
		$types[] = self::PRODUCT;
		return $types;
	}

	/* ------------------------------------------------------------- design */

	/** Settings keys the design system reads ('' = take the template's value). */
	const DESIGN_KEYS = array( 'layout', 'cols', 'cols_t', 'cols_m', 'gap', 'card', 'fit', 'hover', 'align', 'button', 'btn_shape', 'btn_style', 'radius', 'ink', 'surface', 'line', 'soft', 'bg', 'show_cat', 'show_text', 'product', 'gallery', 'sticky', 'details', 'checkout', 'cart_icon', 'cart_count', 'cart_total' );

	/**
	 * Ready-made shop designs. Each one is a complete set of structure and
	 * colour choices; every choice can be changed on top in Settings → Design.
	 * "own" is a neutral base that inherits the site's colours and fonts, for
	 * styling everything yourself (the vxs-* classes and --vxs-* variables).
	 */
	public static function presets() {
		$base = array(
			'layout' => 'grid', 'cols' => '3', 'cols_t' => '2', 'cols_m' => '2', 'gap' => '28',
			'card' => 'plain', 'fit' => 'cover', 'hover' => 'swap', 'align' => 'left', 'button' => 'button',
			'btn_shape' => 'pill', 'btn_style' => 'solid', 'radius' => '12',
			'ink' => '#111827', 'surface' => '#ffffff', 'line' => '#e5e7eb', 'soft' => '#f5f6f8', 'bg' => '#ffffff',
			'show_cat' => '0', 'show_text' => '0',
			'product' => 'split', 'gallery' => 'below', 'sticky' => '1', 'details' => 'sections',
			'checkout' => 'two', 'cart_icon' => 'bag', 'cart_count' => 'badge', 'cart_total' => '0',
		);
		return array(
			'velox'     => array( 'name' => __( 'Velox', 'velox' ), 'desc' => __( 'Clean and calm: photos in front, a clear button under each product.', 'velox' ), 'v' => $base ),
			'gallery'   => array( 'name' => __( 'Gallery', 'velox' ), 'desc' => __( 'For art and objects: every work uncropped on a quiet tile, like on a museum wall.', 'velox' ), 'v' => array_merge( $base, array(
				'cols_m' => '1', 'gap' => '40', 'card' => 'tile', 'fit' => 'contain', 'hover' => 'lift', 'button' => 'link', 'btn_style' => 'outline', 'radius' => '4',
				'ink' => '#17140f', 'line' => '#e7e1d7', 'soft' => '#efe9df', 'bg' => '#faf8f4', 'surface' => '#faf8f4', 'show_cat' => '1', 'product' => 'wide', 'gallery' => 'stack', 'details' => 'accordion',
			) ) ),
			'boutique'  => array( 'name' => __( 'Boutique', 'velox' ), 'desc' => __( 'Soft cards with centred text and small details — fashion, cosmetics, gifts.', 'velox' ), 'v' => array_merge( $base, array(
				'cols' => '4', 'gap' => '20', 'card' => 'surface', 'hover' => 'lift', 'align' => 'center', 'btn_shape' => 'rounded', 'btn_style' => 'outline', 'radius' => '18',
				'ink' => '#1c1917', 'line' => '#ece7e2', 'soft' => '#f7f3ef', 'show_cat' => '1', 'gallery' => 'side', 'details' => 'accordion',
			) ) ),
			'minimal'   => array( 'name' => __( 'Minimal', 'velox' ), 'desc' => __( 'Sharp edges, lots of air, text links instead of buttons.', 'velox' ), 'v' => array_merge( $base, array(
				'gap' => '48', 'hover' => 'zoom', 'button' => 'link', 'btn_shape' => 'square', 'btn_style' => 'outline', 'radius' => '0',
				'ink' => '#0a0a0a', 'line' => '#e5e5e5', 'soft' => '#f4f4f4', 'gallery' => 'grid', 'sticky' => '0', 'checkout' => 'one', 'cart_count' => 'inline', 'cart_total' => '1',
			) ) ),
			'editorial' => array( 'name' => __( 'Editorial', 'velox' ), 'desc' => __( 'Big rows like a magazine: image on one side, the story on the other.', 'velox' ), 'v' => array_merge( $base, array(
				'layout' => 'list', 'cols' => '1', 'cols_t' => '1', 'cols_m' => '1', 'gap' => '56', 'card' => 'split', 'hover' => 'zoom', 'radius' => '6',
				'show_cat' => '1', 'show_text' => '1', 'product' => 'wide', 'gallery' => 'stack',
			) ) ),
			'night'     => array( 'name' => __( 'Night', 'velox' ), 'desc' => __( 'Dark and rich — products glow on deep surfaces.', 'velox' ), 'v' => array_merge( $base, array(
				'cols_m' => '1', 'gap' => '22', 'card' => 'surface', 'hover' => 'lift', 'radius' => '16',
				'ink' => '#f5f5f4', 'surface' => '#171717', 'line' => '#2a2a2a', 'soft' => '#1f1f1f', 'bg' => '#0e0e0e',
			) ) ),
			'own'       => array( 'name' => __( 'Your own design', 'velox' ), 'desc' => __( 'A neutral base that takes your site’s colours and fonts. Style everything yourself — with the choices below, your own CSS or Velox Builder.', 'velox' ), 'v' => array_merge( $base, array(
				'card' => 'plain', 'hover' => 'none', 'radius' => '0', 'btn_shape' => 'square', 'ink' => '', 'surface' => '', 'line' => '', 'soft' => '', 'bg' => '',
			) ) ),
		);
	}

	/**
	 * The design in effect: the template's values with every choice made in
	 * the settings on top, then any per-element choice ($over, e.g. from a
	 * Velox Builder product grid) on top of that.
	 */
	public static function design( $over = array() ) {
		$all  = self::presets();
		$name = (string) self::get( 'design', 'velox' );
		if ( ! isset( $all[ $name ] ) ) {
			$name = 'velox';
		}
		$d = $all[ $name ]['v'];
		foreach ( self::DESIGN_KEYS as $k ) {
			$v = (string) self::get( 'd_' . $k, '' );
			if ( '' !== $v ) {
				$d[ $k ] = $v;
			}
			if ( isset( $over[ $k ] ) && '' !== (string) $over[ $k ] ) {
				$d[ $k ] = (string) $over[ $k ];
			}
		}
		$d['preset'] = $name;
		return $d;
	}

	/** Colour and size variables for the shop, from the design in effect. */
	public static function design_vars( $d = null ) {
		$d    = $d ? $d : self::design();
		$css  = '';
		$col  = function ( $v ) { return preg_match( '/^#[0-9a-f]{3,8}$/i', (string) $v ) ? $v : ''; };
		$own  = 'own' === $d['preset'];
		$ink  = $col( $d['ink'] );
		if ( $ink ) {
			$css .= '--vxs-ink:' . $ink . ';--vxs-ink-2:color-mix(in srgb,' . $ink . ' 76%,transparent);--vxs-ink-3:color-mix(in srgb,' . $ink . ' 58%,transparent);';
		} elseif ( $own ) {
			$css .= '--vxs-ink:currentColor;--vxs-ink-2:currentColor;--vxs-ink-3:color-mix(in srgb,currentColor 62%,transparent);';
		}
		foreach ( array( 'line' => '--vxs-line', 'soft' => '--vxs-soft', 'bg' => '--vxs-bg', 'surface' => '--vxs-surface' ) as $k => $var ) {
			$v = $col( $d[ $k ] );
			if ( $v ) {
				$css .= $var . ':' . $v . ';';
			} elseif ( $own ) {
				$css .= $var . ':' . ( 'line' === $k ? 'color-mix(in srgb,currentColor 16%,transparent)' : ( 'soft' === $k ? 'color-mix(in srgb,currentColor 5%,transparent)' : 'transparent' ) ) . ';';
			}
		}
		$r    = max( 0, min( 40, (int) $d['radius'] ) );
		$css .= '--vxs-r:' . $r . 'px;--vxs-r-sm:' . max( 0, min( 12, (int) round( $r * 0.66 ) ) ) . 'px;';
		$css .= '--vxs-btn-r:' . ( 'pill' === $d['btn_shape'] ? '999px' : ( 'rounded' === $d['btn_shape'] ? '10px' : '0' ) ) . ';';
		$css .= '--vxs-gap:' . max( 0, min( 120, (int) $d['gap'] ) ) . 'px;';
		$acc  = sanitize_hex_color( (string) self::get( 'accent', '#0a6f9f' ) );
		if ( $acc ) {
			$css .= '--vxs-accent:' . $acc . ';';
		}
		// A dark design needs light text on the accent and on dark chips.
		if ( $ink && self::is_light( $ink ) ) {
			$css .= '--vxs-on-ink:#111;';
		}
		return $css;
	}

	/** Light colour? (for picking readable text on top of it) */
	public static function is_light( $hex ) {
		$h = ltrim( (string) $hex, '#' );
		if ( 3 === strlen( $h ) ) {
			$h = $h[0] . $h[0] . $h[1] . $h[1] . $h[2] . $h[2];
		}
		if ( strlen( $h ) < 6 ) {
			return false;
		}
		$l = ( 0.2126 * hexdec( substr( $h, 0, 2 ) ) + 0.7152 * hexdec( substr( $h, 2, 2 ) ) + 0.0722 * hexdec( substr( $h, 4, 2 ) ) ) / 255;
		return $l > 0.6;
	}

	/** Classes that switch the storefront's structure for this design. */
	public static function design_classes( $d = null ) {
		$d = $d ? $d : self::design();
		return 'vxs-d vxs-d-' . sanitize_html_class( $d['preset'] ) . ' vxs-l-' . sanitize_html_class( $d['layout'] ) . ' vxs-k-' . sanitize_html_class( $d['card'] )
			. ' vxs-hv-' . sanitize_html_class( $d['hover'] ) . ' vxs-al-' . sanitize_html_class( $d['align'] ) . ' vxs-fit-' . sanitize_html_class( $d['fit'] )
			. ' vxs-bst-' . sanitize_html_class( $d['btn_style'] ) . ' vxs-pl-' . sanitize_html_class( $d['product'] ) . ' vxs-gl-' . sanitize_html_class( $d['gallery'] )
			. ( '1' === (string) $d['sticky'] ? ' vxs-sticky' : '' ) . ' vxs-co-' . sanitize_html_class( $d['checkout'] );
	}

	public static function card_ratio() {
		$r = (string) self::get( 'card_ratio' );
		if ( '' === $r ) {
			$r = self::legacy_upgrade() ? 'natural' : '4/5';
		}
		return in_array( $r, array( '4/5', '1/1', '3/4', 'natural' ), true ) ? $r : '4/5';
	}

	public static function small_business() {
		return 'small' === self::get( 'tax_mode' );
	}

	/* -------------------------------------------------------------- post types */

	public static function register_types() {
		$base = sanitize_title( self::get( 'product_base', 'product' ) );
		register_post_type( self::PRODUCT, array(
			'labels'       => array(
				'name'          => __( 'Products', 'velox' ),
				'singular_name' => __( 'Product', 'velox' ),
				'add_new_item'  => __( 'Add product', 'velox' ),
				'edit_item'     => __( 'Edit product', 'velox' ),
			),
			'public'       => true,
			'has_archive'  => false,
			// The shop editor is the place to manage products; the classic list
			// stays reachable for bulk actions and imports.
			'show_in_menu' => false,
			'supports'     => array( 'title', 'editor', 'thumbnail', 'excerpt', 'page-attributes', 'custom-fields' ),
			'rewrite'      => array( 'slug' => $base ? $base : 'product', 'with_front' => false ),
			'show_in_rest' => true,
		) );
		register_taxonomy( self::CAT, self::PRODUCT, array(
			'labels'            => array( 'name' => __( 'Product categories', 'velox' ), 'singular_name' => __( 'Category', 'velox' ) ),
			'hierarchical'      => true,
			'public'            => true,
			'show_in_rest'      => true,
			'show_admin_column' => true,
			'rewrite'           => array( 'slug' => ( $base ? $base : 'product' ) . '-category', 'with_front' => false ),
		) );
		register_post_type( self::ORDER, array(
			'labels'          => array( 'name' => __( 'Orders', 'velox' ), 'singular_name' => __( 'Order', 'velox' ) ),
			'public'          => false,
			'show_ui'         => false,
			'capability_type' => 'post',
			'map_meta_cap'    => true,
			'supports'        => array( 'title' ),
		) );
		// Product addresses work right after the shop is switched on, and after
		// the product base changes: the rules are rebuilt once when they differ.
		$rw = ( $base ? $base : 'product' ) . '|1';
		if ( get_option( 'velox_shop_rw' ) !== $rw ) {
			update_option( 'velox_shop_rw', $rw );
			flush_rewrite_rules( false );
		}
	}

	public static function register_meta() {
		foreach ( array( '_vx_price' => 'number', '_vx_status' => 'string', '_vx_sku' => 'string' ) as $key => $type ) {
			register_post_meta( self::PRODUCT, $key, array(
				'type'          => $type,
				'single'        => true,
				'show_in_rest'  => true,
				'auth_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			) );
		}
	}

	/* ------------------------------------------------------------------ money */

	/** Round to cents. Every stored amount goes through this. */
	public static function r( $n ) {
		return round( (float) $n + 0.0, 2 );
	}

	/** "12,50" or "12.5" → 12.5. Empty → null. */
	public static function num( $v ) {
		$v = trim( str_replace( array( ' ', ' ' ), '', (string) $v ) );
		if ( '' === $v ) {
			return null;
		}
		if ( false !== strpos( $v, ',' ) && false !== strpos( $v, '.' ) ) {
			$v = str_replace( '.', '', $v );
		}
		$v = str_replace( ',', '.', $v );
		return is_numeric( $v ) ? (float) $v : null;
	}

	public static function currency() {
		return (string) self::get( 'symbol', '€' );
	}

	public static function money( $amount ) {
		$n = number_format_i18n( (float) $amount, 2 );
		return 'left' === self::get( 'symbol_pos' ) ? self::currency() . ' ' . $n : $n . ' ' . self::currency();
	}

	public static function rate( $class ) {
		if ( self::small_business() ) {
			return 0.0;
		}
		if ( 'zero' === $class ) {
			return 0.0;
		}
		$r = self::num( 'reduced' === $class ? self::get( 'rate_reduced' ) : self::get( 'rate_standard' ) );
		return null === $r ? 0.0 : max( 0.0, min( 50.0, $r ) );
	}

	/** VAT contained in a gross amount. */
	public static function tax_in( $gross, $rate ) {
		return $rate > 0 ? self::r( $gross - $gross / ( 1 + $rate / 100 ) ) : 0.0;
	}

	/* ---------------------------------------------------------------- products */

	/** Normalised product data. Older shops (one-off works) are read from their old fields. */
	public static function product( $id ) {
		$id = (int) $id;
		if ( ! $id || self::PRODUCT !== get_post_type( $id ) ) {
			return null;
		}
		$raw = json_decode( (string) get_post_meta( $id, self::META, true ), true );
		if ( ! is_array( $raw ) ) {
			$raw    = array();
			$status = (string) get_post_meta( $id, '_vx_status', true );
			$raw['price'] = (string) get_post_meta( $id, '_vx_price', true );
			$raw['type']  = 'physical';
			if ( 'sold' === $status ) {
				$raw['unique'] = 1;
				$raw['track']  = 1;
				$raw['stock']  = 0;
			} elseif ( 'commission' === $status ) {
				$raw['mode'] = 'inquiry';
			} else {
				$raw['unique'] = 1;
				$raw['track']  = 1;
				$raw['stock']  = 1;
			}
			$raw['sku'] = (string) get_post_meta( $id, '_vx_sku', true );
			$specs = array();
			foreach ( array( '_vx_dims' => __( 'Dimensions', 'velox' ), '_vx_medium' => __( 'Medium', 'velox' ), '_vx_year' => __( 'Year', 'velox' ) ) as $k => $l ) {
				$v = (string) get_post_meta( $id, $k, true );
				if ( '' !== $v ) {
					$specs[] = array( 'k' => $l, 'v' => $v );
				}
			}
			$raw['specs'] = $specs;
		}
		$d = array_merge( array(
			'type'       => 'physical', // physical | digital | service
			'mode'       => 'buy',      // buy | inquiry (price on request / "ask about this")
			'price'      => '',
			'compare'    => '',         // was-price, shown crossed out
			'sale_until' => '',         // Y-m-d: compare price ends after this day
			'sku'        => '',
			'gtin'       => '',
			'track'      => 0,          // count stock
			'stock'      => '',
			'backorder'  => 0,
			'unique'     => 0,          // one of a kind: sells once, then "Sold"
			'max_qty'    => '',
			'tax'        => 'standard', // standard | reduced | zero
			'weight'     => '',
			'delivery'   => '',         // "2–4 working days"
			'unit_qty'   => '',         // contents, e.g. 250 (g)
			'unit'       => '',         // g | kg | ml | l | m | m2 | m3
			'unit_base'  => '',         // 1 kg …
			'options'    => array(),    // [ { name, values[] } ] up to 3
			'variants'   => array(),    // [ { id, opts[], price, compare, sku, stock, image, off } ]
			'files'      => array(),    // [ attachment id ] for digital products
			'specs'      => array(),    // [ { k, v } ] shown as a details table
			'gallery'    => array(),    // [ attachment id ]
			'gpsr'       => '',         // manufacturer / responsible person (EU GPSR)
			'badge'      => '',
			'upsell'     => array(),    // product ids
			'button'     => '',         // own button text
		), $raw );
		$d['id']     = $id;
		$d['title']  = get_the_title( $id );
		$d['status'] = get_post_status( $id );
		$d['options']  = array_values( array_filter( (array) $d['options'], function ( $o ) { return is_array( $o ) && ! empty( $o['name'] ); } ) );
		$d['variants'] = array_values( array_filter( (array) $d['variants'], 'is_array' ) );
		return $d;
	}

	/** The variant matching an id, or null. */
	public static function variant( $p, $vid ) {
		if ( ! $p || '' === (string) $vid ) {
			return null;
		}
		foreach ( $p['variants'] as $v ) {
			if ( isset( $v['id'] ) && (string) $v['id'] === (string) $vid ) {
				return $v;
			}
		}
		return null;
	}

	public static function has_variants( $p ) {
		return $p && $p['options'] && $p['variants'];
	}

	/** Current price of a product or one of its variants (gross). */
	public static function price_of( $p, $v = null ) {
		$price = $v && '' !== (string) ( $v['price'] ?? '' ) ? self::num( $v['price'] ) : self::num( $p['price'] );
		return null === $price ? 0.0 : self::r( max( 0, $price ) );
	}

	/** The crossed-out "was" price while a sale runs, else null. */
	public static function compare_of( $p, $v = null ) {
		if ( ! empty( $p['sale_until'] ) && strtotime( $p['sale_until'] . ' 23:59:59' ) < current_time( 'timestamp' ) ) {
			return null;
		}
		$c = $v && '' !== (string) ( $v['compare'] ?? '' ) ? self::num( $v['compare'] ) : self::num( $p['compare'] );
		$now = self::price_of( $p, $v );
		return ( null !== $c && $c > $now ) ? self::r( $c ) : null;
	}

	/** Lowest and highest price over the variants (for "from 19,00 €"). */
	public static function price_range( $p ) {
		if ( ! self::has_variants( $p ) ) {
			$x = self::price_of( $p );
			return array( $x, $x );
		}
		$all = array();
		foreach ( $p['variants'] as $v ) {
			if ( empty( $v['off'] ) ) {
				$all[] = self::price_of( $p, $v );
			}
		}
		return $all ? array( min( $all ), max( $all ) ) : array( self::price_of( $p ), self::price_of( $p ) );
	}

	/** Units in stock (null = not counted). Variants count their own stock. */
	public static function stock_of( $p, $v = null ) {
		if ( ! $p ) {
			return 0;
		}
		if ( ! empty( $p['unique'] ) ) {
			return max( 0, (int) $p['stock'] );
		}
		if ( empty( $p['track'] ) ) {
			return null;
		}
		if ( $v ) {
			return '' === (string) ( $v['stock'] ?? '' ) ? null : max( 0, (int) $v['stock'] );
		}
		if ( self::has_variants( $p ) ) {
			$sum = 0;
			$any = false;
			foreach ( $p['variants'] as $vv ) {
				if ( empty( $vv['off'] ) && '' !== (string) ( $vv['stock'] ?? '' ) ) {
					$sum += max( 0, (int) $vv['stock'] );
					$any  = true;
				}
			}
			return $any ? $sum : null;
		}
		return '' === (string) $p['stock'] ? null : max( 0, (int) $p['stock'] );
	}

	/** Can this be bought now, in this quantity? */
	public static function can_buy( $p, $v = null, $qty = 1 ) {
		if ( ! $p || 'publish' !== $p['status'] || 'inquiry' === $p['mode'] ) {
			return false;
		}
		if ( self::has_variants( $p ) && ( ! $v || ! empty( $v['off'] ) ) ) {
			return false;
		}
		$st = self::stock_of( $p, $v );
		if ( null === $st || ! empty( $p['backorder'] ) && empty( $p['unique'] ) ) {
			return true;
		}
		return $st >= $qty;
	}

	/** Short availability word for cards and the product page. */
	public static function availability( $p, $v = null ) {
		if ( 'inquiry' === $p['mode'] ) {
			return array( 'inquiry', __( 'On request', 'velox' ) );
		}
		$st = self::stock_of( $p, $v );
		if ( null !== $st && $st <= 0 ) {
			if ( ! empty( $p['unique'] ) ) {
				return array( 'sold', __( 'Sold', 'velox' ) );
			}
			return ! empty( $p['backorder'] ) ? array( 'backorder', __( 'Available to order', 'velox' ) ) : array( 'out', __( 'Sold out', 'velox' ) );
		}
		if ( ! empty( $p['unique'] ) ) {
			return array( 'in', __( 'One of a kind', 'velox' ) );
		}
		$low = (int) self::get( 'low_stock', 3 );
		if ( null !== $st && $st <= $low && 'never' !== self::get( 'show_stock' ) ) {
			/* translators: %d: units left */
			return array( 'low', sprintf( _n( 'Only %d left', 'Only %d left', $st, 'velox' ), $st ) );
		}
		if ( null !== $st && 'always' === self::get( 'show_stock' ) ) {
			/* translators: %d: units in stock */
			return array( 'in', sprintf( __( '%d in stock', 'velox' ), $st ) );
		}
		return array( 'in', __( 'In stock', 'velox' ) );
	}

	/** Unit price line required in Germany (PAngV): "1 kg = 12,00 €". */
	public static function unit_price( $p, $v = null ) {
		$q = self::num( $p['unit_qty'] );
		if ( ! $q || '' === $p['unit'] ) {
			return '';
		}
		$base_units = array( 'g' => array( 1000, 'kg' ), 'kg' => array( 1, 'kg' ), 'ml' => array( 1000, 'l' ), 'l' => array( 1, 'l' ), 'm' => array( 1, 'm' ), 'm2' => array( 1, 'm²' ), 'm3' => array( 1, 'm³' ) );
		if ( ! isset( $base_units[ $p['unit'] ] ) ) {
			return '';
		}
		list( $per, $label ) = $base_units[ $p['unit'] ];
		$val = self::price_of( $p, $v ) / $q * $per;
		return '1 ' . $label . ' = ' . self::money( $val );
	}

	/** Save product data from the shop editor (input already decoded). */
	public static function save_product( $id, $in ) {
		$p   = self::product( $id );
		$out = array();
		$str = function ( $k, $max = 200 ) use ( $in ) {
			return isset( $in[ $k ] ) ? mb_substr( sanitize_text_field( (string) $in[ $k ] ), 0, $max ) : '';
		};
		$money = function ( $v ) {
			$n = self::num( $v );
			return null === $n ? '' : (string) self::r( max( 0, $n ) );
		};
		$out['type']       = in_array( $in['type'] ?? '', array( 'physical', 'digital', 'service' ), true ) ? $in['type'] : 'physical';
		$out['mode']       = 'inquiry' === ( $in['mode'] ?? '' ) ? 'inquiry' : 'buy';
		$out['price']      = $money( $in['price'] ?? '' );
		$out['compare']    = $money( $in['compare'] ?? '' );
		$out['sale_until'] = preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $in['sale_until'] ?? '' ) ) ? $in['sale_until'] : '';
		$out['sku']        = $str( 'sku', 64 );
		$out['gtin']       = preg_replace( '/[^0-9]/', '', $str( 'gtin', 14 ) );
		$out['unique']     = empty( $in['unique'] ) ? 0 : 1;
		$out['track']      = ( $out['unique'] || ! empty( $in['track'] ) ) ? 1 : 0;
		$out['stock']      = '' === (string) ( $in['stock'] ?? '' ) ? '' : (string) max( 0, (int) $in['stock'] );
		if ( $out['unique'] ) {
			$out['stock'] = '' === $out['stock'] ? '1' : (string) min( 1, (int) $out['stock'] );
		}
		$out['backorder']  = empty( $in['backorder'] ) ? 0 : 1;
		$out['max_qty']    = '' === (string) ( $in['max_qty'] ?? '' ) ? '' : (string) max( 1, (int) $in['max_qty'] );
		$out['tax']        = in_array( $in['tax'] ?? '', array( 'standard', 'reduced', 'zero' ), true ) ? $in['tax'] : 'standard';
		$out['weight']     = $str( 'weight', 20 );
		$out['delivery']   = $str( 'delivery', 80 );
		$out['unit_qty']   = $str( 'unit_qty', 12 );
		$out['unit']       = in_array( $in['unit'] ?? '', array( 'g', 'kg', 'ml', 'l', 'm', 'm2', 'm3' ), true ) ? $in['unit'] : '';
		$out['badge']      = $str( 'badge', 24 );
		$out['button']     = $str( 'button', 40 );
		$out['gpsr']       = isset( $in['gpsr'] ) ? mb_substr( sanitize_textarea_field( (string) $in['gpsr'] ), 0, 1000 ) : '';
		$out['options']    = array();
		foreach ( array_slice( (array) ( $in['options'] ?? array() ), 0, 3 ) as $o ) {
			$name = mb_substr( sanitize_text_field( (string) ( $o['name'] ?? '' ) ), 0, 40 );
			$vals = array_values( array_unique( array_filter( array_map( function ( $x ) { return mb_substr( sanitize_text_field( (string) $x ), 0, 40 ); }, (array) ( $o['values'] ?? array() ) ), 'strlen' ) ) );
			if ( '' !== $name && $vals ) {
				$out['options'][] = array( 'name' => $name, 'values' => array_slice( $vals, 0, 30 ) );
			}
		}
		$out['variants'] = array();
		if ( $out['options'] ) {
			foreach ( array_slice( (array) ( $in['variants'] ?? array() ), 0, 300 ) as $v ) {
				$opts = array_map( function ( $x ) { return mb_substr( sanitize_text_field( (string) $x ), 0, 40 ); }, (array) ( $v['opts'] ?? array() ) );
				if ( count( $opts ) !== count( $out['options'] ) ) {
					continue;
				}
				$out['variants'][] = array(
					'id'      => preg_replace( '/[^a-z0-9]/i', '', (string) ( $v['id'] ?? '' ) ) ?: substr( md5( implode( '|', $opts ) ), 0, 8 ),
					'opts'    => $opts,
					'price'   => $money( $v['price'] ?? '' ),
					'compare' => $money( $v['compare'] ?? '' ),
					'sku'     => mb_substr( sanitize_text_field( (string) ( $v['sku'] ?? '' ) ), 0, 64 ),
					'stock'   => '' === (string) ( $v['stock'] ?? '' ) ? '' : (string) max( 0, (int) $v['stock'] ),
					'image'   => (int) ( $v['image'] ?? 0 ),
					'off'     => empty( $v['off'] ) ? 0 : 1,
				);
			}
		}
		$out['files']   = array_values( array_filter( array_map( 'intval', (array) ( $in['files'] ?? array() ) ) ) );
		$out['gallery'] = array_values( array_filter( array_map( 'intval', (array) ( $in['gallery'] ?? array() ) ) ) );
		$out['upsell']  = array_values( array_filter( array_map( 'intval', (array) ( $in['upsell'] ?? array() ) ) ) );
		$out['specs']   = array();
		foreach ( array_slice( (array) ( $in['specs'] ?? array() ), 0, 30 ) as $sp ) {
			$k = mb_substr( sanitize_text_field( (string) ( $sp['k'] ?? '' ) ), 0, 60 );
			$v = mb_substr( sanitize_text_field( (string) ( $sp['v'] ?? '' ) ), 0, 200 );
			if ( '' !== $k && '' !== $v ) {
				$out['specs'][] = array( 'k' => $k, 'v' => $v );
			}
		}
		update_post_meta( $id, self::META, wp_slash( wp_json_encode( $out ) ) );
		self::sync_index( $id );
		return self::product( $id );
	}

	/** Plain meta the WordPress list, REST and sorting can read. */
	public static function sync_index( $id ) {
		$p = self::product( $id );
		if ( ! $p ) {
			return;
		}
		list( $min ) = self::price_range( $p );
		update_post_meta( $id, '_vx_price', $min );
		update_post_meta( $id, '_vx_sku', $p['sku'] );
		$a = self::availability( $p );
		update_post_meta( $id, '_vx_status', 'sold' === $a[0] || 'out' === $a[0] ? 'sold' : ( 'inquiry' === $a[0] ? 'commission' : 'available' ) );
	}

	/**
	 * Take stock out (negative) or put it back (positive). Variants keep their
	 * own count. Returns the new count or null when nothing is counted.
	 */
	public static function adjust_stock( $pid, $vid, $delta ) {
		$p = self::product( $pid );
		if ( ! $p || ( empty( $p['track'] ) && empty( $p['unique'] ) ) ) {
			return null;
		}
		$raw = json_decode( (string) get_post_meta( $pid, self::META, true ), true );
		if ( ! is_array( $raw ) ) {
			// A product from the first shop: write the full record once.
			$raw = $p;
			unset( $raw['id'], $raw['title'], $raw['status'] );
		}
		$new = null;
		if ( $vid && ! empty( $raw['variants'] ) ) {
			foreach ( $raw['variants'] as &$v ) {
				if ( (string) ( $v['id'] ?? '' ) === (string) $vid && '' !== (string) ( $v['stock'] ?? '' ) ) {
					$v['stock'] = (string) max( 0, (int) $v['stock'] + $delta );
					$new        = (int) $v['stock'];
				}
			}
			unset( $v );
		} elseif ( '' !== (string) ( $raw['stock'] ?? '' ) ) {
			$raw['stock'] = (string) max( 0, (int) $raw['stock'] + $delta );
			$new          = (int) $raw['stock'];
		}
		if ( null !== $new ) {
			update_post_meta( $pid, self::META, wp_slash( wp_json_encode( $raw ) ) );
			self::sync_index( $pid );
			if ( $delta < 0 && $new <= (int) self::get( 'low_stock', 3 ) ) {
				Velox_Shop::mail_admin_low_stock( $pid, $new );
			}
		}
		return $new;
	}

	/* ------------------------------------------------------------------- cart */

	private static function sign( $json ) {
		return wp_hash( $json, 'nonce' );
	}

	/**
	 * The cart cookie: { "123" or "123:v1": qty, "_c": coupon code }. Returns
	 * the raw map; lines() turns it into priced lines.
	 */
	public static function get_cart() {
		if ( empty( $_COOKIE[ self::COOKIE ] ) ) {
			return array();
		}
		$parts = explode( '|', (string) wp_unslash( $_COOKIE[ self::COOKIE ] ), 2 );
		if ( 2 !== count( $parts ) ) {
			return array();
		}
		$json = base64_decode( $parts[0], true );
		if ( false === $json || ! hash_equals( self::sign( $json ), $parts[1] ) ) {
			return array();
		}
		$data = json_decode( $json, true );
		if ( ! is_array( $data ) ) {
			return array();
		}
		$out = array();
		foreach ( $data as $key => $qty ) {
			if ( '_c' === $key ) {
				$out['_c'] = strtoupper( preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $qty ) );
				continue;
			}
			if ( ! preg_match( '/^(\d+)(?::([a-z0-9]+))?$/i', (string) $key ) ) {
				continue;
			}
			$out[ (string) $key ] = max( 1, min( 999, (int) $qty ) );
		}
		return $out;
	}

	public static function save_cart( $cart ) {
		$cart = array_filter( (array) $cart, function ( $v, $k ) { return '_c' === $k ? '' !== (string) $v : (int) $v > 0; }, ARRAY_FILTER_USE_BOTH );
		if ( ! $cart ) {
			setcookie( self::COOKIE, '', time() - 3600, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), false );
			unset( $_COOKIE[ self::COOKIE ] );
			return;
		}
		$json = wp_json_encode( $cart );
		$val  = base64_encode( $json ) . '|' . self::sign( $json );
		if ( ! headers_sent() ) {
			setcookie( self::COOKIE, $val, time() + 14 * DAY_IN_SECONDS, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), false );
		}
		$_COOKIE[ self::COOKIE ] = $val;
	}

	/** Add, set or remove a line. Returns '' or an error message for the visitor. */
	public static function cart_set( $pid, $vid, $qty, $add = false ) {
		$p = self::product( $pid );
		if ( ! $p ) {
			return __( 'This product no longer exists.', 'velox' );
		}
		$v = self::variant( $p, $vid );
		if ( self::has_variants( $p ) && ! $v ) {
			return __( 'Please choose an option first.', 'velox' );
		}
		$key  = (string) (int) $pid . ( $v ? ':' . $v['id'] : '' );
		$cart = self::get_cart();
		$qty  = (int) $qty;
		$new  = $add ? ( isset( $cart[ $key ] ) ? (int) $cart[ $key ] : 0 ) + $qty : $qty;
		if ( $new <= 0 ) {
			unset( $cart[ $key ] );
			self::save_cart( $cart );
			return '';
		}
		if ( ! empty( $p['unique'] ) ) {
			$new = 1;
		}
		if ( '' !== (string) $p['max_qty'] ) {
			$new = min( $new, (int) $p['max_qty'] );
		}
		if ( ! self::can_buy( $p, $v, $new ) ) {
			$st = self::stock_of( $p, $v );
			if ( null !== $st && $st > 0 ) {
				$cart[ $key ] = $st;
				self::save_cart( $cart );
				/* translators: %d: units in stock */
				return sprintf( __( 'Only %d available — your cart has been adjusted.', 'velox' ), $st );
			}
			return __( 'This is no longer available.', 'velox' );
		}
		$cart[ $key ] = $new;
		self::save_cart( $cart );
		return '';
	}

	/** Priced lines for the cart and checkout. Anything unbuyable is dropped. */
	public static function lines( $cart = null ) {
		$cart  = null === $cart ? self::get_cart() : $cart;
		$lines = array();
		foreach ( $cart as $key => $qty ) {
			if ( '_c' === $key ) {
				continue;
			}
			list( $pid, $vid ) = array_pad( explode( ':', (string) $key, 2 ), 2, '' );
			$p = self::product( $pid );
			if ( ! $p ) {
				continue;
			}
			$v = self::variant( $p, $vid );
			if ( ! self::can_buy( $p, $v, 1 ) ) {
				continue;
			}
			$unit    = self::price_of( $p, $v );
			$rate    = self::rate( $p['tax'] );
			$total   = self::r( $unit * $qty );
			$img     = $v && ! empty( $v['image'] ) ? (int) $v['image'] : (int) get_post_thumbnail_id( $p['id'] );
			$label   = array();
			if ( $v ) {
				foreach ( $p['options'] as $i => $o ) {
					$label[] = $o['name'] . ': ' . ( $v['opts'][ $i ] ?? '' );
				}
			}
			$lines[] = array(
				'key'      => (string) $key,
				'pid'      => (int) $p['id'],
				'vid'      => $v ? (string) $v['id'] : '',
				'name'     => $p['title'],
				'variant'  => implode( ', ', $label ),
				'sku'      => $v && '' !== (string) $v['sku'] ? $v['sku'] : $p['sku'],
				'qty'      => (int) $qty,
				'unit'     => $unit,
				'compare'  => self::compare_of( $p, $v ),
				'total'    => $total,
				'rate'     => $rate,
				'tax'      => self::tax_in( $total, $rate ),
				'type'     => $p['type'],
				'ship'     => 'physical' === $p['type'],
				'weight'   => (float) str_replace( ',', '.', (string) $p['weight'] ),
				'image'    => $img,
				'thumb'    => $img ? wp_get_attachment_image_url( $img, 'thumbnail' ) : '',
				'url'      => get_permalink( $p['id'] ),
				'delivery' => $p['delivery'],
				'unique'   => ! empty( $p['unique'] ),
				'max'      => '' !== (string) $p['max_qty'] ? (int) $p['max_qty'] : ( null !== self::stock_of( $p, $v ) && empty( $p['backorder'] ) ? self::stock_of( $p, $v ) : 999 ),
			);
		}
		return $lines;
	}

	/** The cart's value after discounts, formatted ('' when empty). */
	public static function cart_subtotal() {
		$lines = self::lines();
		if ( ! $lines ) {
			return '';
		}
		$t = self::totals( $lines );
		return self::money( $t['subtotal'] - $t['discount'] );
	}

	public static function cart_count() {
		$n = 0;
		foreach ( self::get_cart() as $k => $q ) {
			if ( '_c' !== $k ) {
				$n += (int) $q;
			}
		}
		return $n;
	}

	/* --------------------------------------------------------------- shipping */

	public static function countries() {
		return array(
			'DE' => __( 'Germany', 'velox' ), 'AT' => __( 'Austria', 'velox' ), 'CH' => __( 'Switzerland', 'velox' ), 'NL' => __( 'Netherlands', 'velox' ),
			'BE' => __( 'Belgium', 'velox' ), 'LU' => __( 'Luxembourg', 'velox' ), 'FR' => __( 'France', 'velox' ), 'IT' => __( 'Italy', 'velox' ),
			'ES' => __( 'Spain', 'velox' ), 'PT' => __( 'Portugal', 'velox' ), 'DK' => __( 'Denmark', 'velox' ), 'SE' => __( 'Sweden', 'velox' ),
			'FI' => __( 'Finland', 'velox' ), 'NO' => __( 'Norway', 'velox' ), 'PL' => __( 'Poland', 'velox' ), 'CZ' => __( 'Czechia', 'velox' ),
			'SK' => __( 'Slovakia', 'velox' ), 'HU' => __( 'Hungary', 'velox' ), 'SI' => __( 'Slovenia', 'velox' ), 'HR' => __( 'Croatia', 'velox' ),
			'IE' => __( 'Ireland', 'velox' ), 'GB' => __( 'United Kingdom', 'velox' ), 'GR' => __( 'Greece', 'velox' ), 'RO' => __( 'Romania', 'velox' ),
			'BG' => __( 'Bulgaria', 'velox' ), 'EE' => __( 'Estonia', 'velox' ), 'LV' => __( 'Latvia', 'velox' ), 'LT' => __( 'Lithuania', 'velox' ),
			'US' => __( 'United States', 'velox' ), 'CA' => __( 'Canada', 'velox' ), 'AU' => __( 'Australia', 'velox' ), 'TR' => __( 'Turkey', 'velox' ),
		);
	}

	/** Countries the shop delivers to (empty setting = all known countries). */
	public static function ship_countries() {
		$all  = self::countries();
		$list = array_filter( (array) self::get( 'ship_countries', array() ) );
		if ( ! $list ) {
			return $all;
		}
		$out = array();
		foreach ( $list as $c ) {
			if ( isset( $all[ $c ] ) ) {
				$out[ $c ] = $all[ $c ];
			}
		}
		return $out;
	}

	/** The zone for a country: first zone listing it, else the first zone without countries. */
	public static function zone_for( $country ) {
		$fallback = null;
		foreach ( (array) self::get( 'zones', array() ) as $z ) {
			$cs = array_filter( (array) ( $z['countries'] ?? array() ) );
			if ( ! $cs && ! $fallback ) {
				$fallback = $z;
			}
			if ( in_array( $country, $cs, true ) ) {
				return $z;
			}
		}
		return $fallback;
	}

	/**
	 * Shipping choices for a cart and country: [ id => { name, cost, days, free } ].
	 * Nothing to ship (only digital products or services) → one free "no shipping" line.
	 */
	public static function shipping_options( $lines, $country, $goods_total ) {
		$needs = false;
		foreach ( $lines as $l ) {
			if ( $l['ship'] ) {
				$needs = true;
			}
		}
		if ( ! $needs ) {
			return array( 'none' => array( 'id' => 'none', 'name' => __( 'No shipping needed', 'velox' ), 'cost' => 0.0, 'days' => '', 'type' => 'none' ) );
		}
		$out = array();
		$z   = self::zone_for( $country );
		if ( $z ) {
			foreach ( (array) ( $z['rates'] ?? array() ) as $r ) {
				$cost = self::num( $r['cost'] ?? '' );
				$free = self::num( $r['free_above'] ?? '' );
				$c    = null === $cost ? 0.0 : $cost;
				$is_free = null !== $free && $goods_total >= $free;
				$out[ $r['id'] ] = array(
					'id'   => (string) $r['id'],
					'name' => (string) ( $r['name'] ?? __( 'Shipping', 'velox' ) ),
					'cost' => $is_free ? 0.0 : self::r( $c ),
					'days' => (string) ( $r['days'] ?? '' ),
					'type' => 'flat',
					'free_above' => $free,
				);
			}
		}
		if ( self::get( 'pickup' ) ) {
			$out['pickup'] = array( 'id' => 'pickup', 'name' => (string) self::get( 'pickup_name' ), 'cost' => 0.0, 'days' => '', 'type' => 'pickup', 'address' => (string) self::get( 'pickup_address' ) );
		}
		return $out;
	}

	/* ---------------------------------------------------------------- coupons */

	public static function coupons() {
		$c = get_option( self::OPT_CPN, array() );
		return is_array( $c ) ? $c : array();
	}

	public static function save_coupons( $list ) {
		update_option( self::OPT_CPN, array_values( $list ), false );
	}

	/** A usable coupon for this subtotal, or [ null, reason ]. */
	public static function coupon_check( $code, $subtotal, $email = '' ) {
		$code = strtoupper( trim( (string) $code ) );
		if ( '' === $code ) {
			return array( null, '' );
		}
		foreach ( self::coupons() as $c ) {
			if ( strtoupper( (string) $c['code'] ) !== $code ) {
				continue;
			}
			if ( ! empty( $c['off'] ) ) {
				break;
			}
			if ( ! empty( $c['until'] ) && strtotime( $c['until'] . ' 23:59:59' ) < current_time( 'timestamp' ) ) {
				return array( null, __( 'This code has expired.', 'velox' ) );
			}
			if ( '' !== (string) ( $c['limit'] ?? '' ) && (int) ( $c['used'] ?? 0 ) >= (int) $c['limit'] ) {
				return array( null, __( 'This code has been used up.', 'velox' ) );
			}
			$min = self::num( $c['min'] ?? '' );
			if ( null !== $min && $subtotal < $min ) {
				/* translators: %s: minimum order value */
				return array( null, sprintf( __( 'This code works from %s.', 'velox' ), self::money( $min ) ) );
			}
			return array( $c, '' );
		}
		return array( null, __( 'This code is not valid.', 'velox' ) );
	}

	/* ----------------------------------------------------------------- totals */

	/**
	 * Everything the checkout and the order need, from one function so the
	 * screen and the stored order can never disagree.
	 */
	public static function totals( $lines, $country = '', $ship_id = '', $coupon = '', $pay = '' ) {
		$country  = $country ? $country : (string) self::get( 'store_country', 'DE' );
		$subtotal = 0.0;
		foreach ( $lines as $l ) {
			$subtotal += $l['total'];
		}
		$subtotal = self::r( $subtotal );
		list( $cpn, $cpn_msg ) = self::coupon_check( $coupon, $subtotal );
		$discount = 0.0;
		$free_ship = false;
		if ( $cpn ) {
			$val = (float) self::num( $cpn['value'] ?? 0 );
			if ( 'percent' === $cpn['type'] ) {
				$discount = self::r( $subtotal * min( 100, $val ) / 100 );
			} elseif ( 'fixed' === $cpn['type'] ) {
				$discount = self::r( min( $subtotal, $val ) );
			} elseif ( 'ship' === $cpn['type'] ) {
				$free_ship = true;
			}
		}
		$goods = self::r( $subtotal - $discount );
		$opts  = self::shipping_options( $lines, $country, $goods );
		if ( ! isset( $opts[ $ship_id ] ) ) {
			$ship_id = $opts ? (string) key( $opts ) : '';
		}
		$ship = $ship_id && isset( $opts[ $ship_id ] ) ? $opts[ $ship_id ] : null;
		$ship_cost = $ship ? ( $free_ship ? 0.0 : $ship['cost'] ) : 0.0;
		$fee = 0.0;
		if ( 'cod' === $pay ) {
			$f = self::num( self::get( 'cod_fee' ) );
			$fee = null === $f ? 0.0 : self::r( $f );
		}
		// VAT: each line carries its rate. A discount lowers every line by the
		// same share; shipping and fees are taxed at the standard rate.
		$tax_lines = array();
		$share     = $subtotal > 0 ? $goods / $subtotal : 0;
		foreach ( $lines as $l ) {
			$k = (string) $l['rate'];
			if ( $l['rate'] > 0 ) {
				$tax_lines[ $k ] = ( $tax_lines[ $k ] ?? 0 ) + self::tax_in( $l['total'] * $share, $l['rate'] );
			}
		}
		$std = self::rate( 'standard' );
		if ( $std > 0 && ( $ship_cost + $fee ) > 0 ) {
			$k = (string) $std;
			$tax_lines[ $k ] = ( $tax_lines[ $k ] ?? 0 ) + self::tax_in( $ship_cost + $fee, $std );
		}
		$tax_lines = array_map( array( __CLASS__, 'r' ), $tax_lines );
		$total     = self::r( $goods + $ship_cost + $fee );
		return array(
			'country'   => $country,
			'subtotal'  => $subtotal,
			'discount'  => $discount,
			'coupon'    => $cpn ? strtoupper( $cpn['code'] ) : '',
			'coupon_msg'=> $cpn_msg,
			'coupon_ship' => $free_ship,
			'shipping'  => $ship ? array_merge( $ship, array( 'cost' => $ship_cost ) ) : null,
			'ship_opts' => $opts,
			'fee'       => $fee,
			'tax_lines' => $tax_lines,
			'tax'       => self::r( array_sum( $tax_lines ) ),
			'total'     => $total,
			'needs_ship'=> ! isset( $opts['none'] ),
			'digital'   => (bool) array_filter( $lines, function ( $l ) { return 'digital' === $l['type']; } ),
		);
	}

	/* ------------------------------------------------------------------ orders */

	/** Next order number. A short lock keeps two checkouts from getting the same one. */
	public static function next_number( $opt = self::OPT_SEQ, $start = 1001 ) {
		global $wpdb;
		for ( $i = 0; $i < 20; $i++ ) {
			if ( add_option( $opt . '_lock', time(), '', false ) ) {
				break;
			}
			if ( (int) get_option( $opt . '_lock' ) < time() - 10 ) {
				delete_option( $opt . '_lock' );
			}
			usleep( 100000 );
		}
		wp_cache_delete( $opt, 'options' );
		$n = (int) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $opt ) );
		$n = $n ? $n + 1 : $start;
		update_option( $opt, $n, false );
		delete_option( $opt . '_lock' );
		return $n;
	}

	/** Normalised order data. Orders from the first shop are read from their old fields. */
	public static function order( $id ) {
		$id = (int) $id;
		if ( ! $id || self::ORDER !== get_post_type( $id ) ) {
			return null;
		}
		$o = json_decode( (string) get_post_meta( $id, self::OMETA, true ), true );
		if ( ! is_array( $o ) ) {
			$items = json_decode( (string) get_post_meta( $id, '_vx_items', true ), true );
			$state = (string) get_post_meta( $id, '_vx_state', true );
			$name  = (string) get_post_meta( $id, '_vx_name', true );
			$o = array(
				'number'   => (string) get_post_meta( $id, '_vx_ref', true ),
				'key'      => '',
				'created'  => get_post_time( 'U', true, $id ),
				'customer' => array( 'email' => (string) get_post_meta( $id, '_vx_email', true ), 'first' => $name, 'last' => '', 'phone' => '', 'company' => '',
					'address' => array( 'line1' => (string) get_post_meta( $id, '_vx_address', true ), 'line2' => '', 'zip' => '', 'city' => '', 'country' => '' ) ),
				'note'     => (string) get_post_meta( $id, '_vx_note', true ),
				'items'    => array_map( function ( $i ) {
					return array( 'pid' => (int) ( $i['id'] ?? 0 ), 'vid' => '', 'name' => (string) ( $i['title'] ?? '' ), 'variant' => '', 'sku' => '', 'qty' => (int) ( $i['qty'] ?? 1 ), 'unit' => (float) ( $i['price'] ?? 0 ), 'total' => (float) ( $i['total'] ?? 0 ), 'rate' => 0, 'tax' => 0, 'type' => 'physical' );
				}, is_array( $items ) ? $items : array() ),
				'subtotal' => (float) get_post_meta( $id, '_vx_total', true ),
				'total'    => (float) get_post_meta( $id, '_vx_total', true ),
				'payment'  => array( 'method' => 'bank', 'status' => in_array( $state, array( 'paid', 'shipped' ), true ) ? 'paid' : ( 'cancelled' === $state ? 'cancelled' : 'pending' ) ),
				'fulfil'   => array( 'status' => 'shipped' === $state ? 'shipped' : ( 'cancelled' === $state ? 'cancelled' : 'unfulfilled' ) ),
				'legacy'   => 1,
			);
		}
		$o = array_merge( array(
			'number' => '', 'key' => '', 'created' => 0, 'customer' => array(), 'ship_to' => null, 'note' => '', 'items' => array(),
			'subtotal' => 0, 'discount' => 0, 'coupon' => '', 'shipping' => null, 'fee' => 0, 'tax_lines' => array(), 'tax' => 0, 'total' => 0,
			'currency' => self::get( 'currency', 'EUR' ), 'small' => 0, 'payment' => array(), 'fulfil' => array(), 'notes' => array(),
			'invoice' => null, 'withdrawal' => null, 'user' => 0, 'stock_out' => 0, 'source' => '',
		), $o );
		$o['payment'] = array_merge( array( 'method' => '', 'status' => 'pending', 'ref' => '', 'paid_at' => 0, 'refunded' => 0 ), (array) $o['payment'] );
		$o['fulfil']  = array_merge( array( 'status' => 'unfulfilled', 'carrier' => '', 'tracking' => '', 'at' => 0 ), (array) $o['fulfil'] );
		$o['customer'] = array_merge( array( 'email' => '', 'first' => '', 'last' => '', 'phone' => '', 'company' => '', 'address' => array() ), (array) $o['customer'] );
		$o['customer']['address'] = array_merge( array( 'line1' => '', 'line2' => '', 'zip' => '', 'city' => '', 'country' => '' ), (array) $o['customer']['address'] );
		$o['id'] = $id;
		return $o;
	}

	public static function save_order( $id, $o ) {
		unset( $o['id'] );
		update_post_meta( $id, self::OMETA, wp_slash( wp_json_encode( $o ) ) );
		// Flat copies for searching, sorting and the old list.
		update_post_meta( $id, '_vx_ref', $o['number'] );
		update_post_meta( $id, '_vx_email', $o['customer']['email'] );
		update_post_meta( $id, '_vx_name', trim( $o['customer']['first'] . ' ' . $o['customer']['last'] ) );
		update_post_meta( $id, '_vx_total', $o['total'] );
		update_post_meta( $id, '_vxs_pay', $o['payment']['status'] );
		update_post_meta( $id, '_vxs_ful', $o['fulfil']['status'] );
		update_post_meta( $id, '_vxs_key', $o['key'] );
		if ( ! empty( $o['user'] ) ) {
			update_post_meta( $id, '_vx_user', (int) $o['user'] );
		}
	}

	public static function order_by_key( $key ) {
		$key = preg_replace( '/[^a-z0-9]/i', '', (string) $key );
		if ( strlen( $key ) < 20 ) {
			return null;
		}
		$ids = get_posts( array( 'post_type' => self::ORDER, 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids', 'meta_key' => '_vxs_key', 'meta_value' => $key ) );
		return $ids ? self::order( $ids[0] ) : null;
	}

	public static function order_by_number( $number, $email ) {
		$ids = get_posts( array( 'post_type' => self::ORDER, 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids', 'meta_key' => '_vx_ref', 'meta_value' => trim( (string) $number ) ) );
		if ( ! $ids ) {
			return null;
		}
		$o = self::order( $ids[0] );
		return ( $o && strtolower( trim( $email ) ) === strtolower( $o['customer']['email'] ) ) ? $o : null;
	}

	/** Add a line to the order's timeline. */
	public static function note( &$o, $text, $customer = false ) {
		$u = wp_get_current_user();
		$o['notes'][] = array( 't' => time(), 'text' => (string) $text, 'by' => $u && $u->ID ? $u->display_name : '', 'c' => $customer ? 1 : 0 );
	}

	/**
	 * Create the order from checkout data that Velox_Shop_Pay has validated.
	 * Stock is taken out right away so the last unit can't be sold twice; an
	 * order that is never paid gives it back (see tick()).
	 */
	public static function create_order( $c, $lines, $t, $pay ) {
		$num = (string) self::get( 'order_prefix', '' ) . self::next_number();
		$oid = wp_insert_post( array( 'post_type' => self::ORDER, 'post_status' => 'publish', 'post_title' => $num ), true );
		if ( is_wp_error( $oid ) ) {
			return $oid;
		}
		$items = array();
		foreach ( $lines as $l ) {
			$items[] = array_intersect_key( $l, array_flip( array( 'pid', 'vid', 'name', 'variant', 'sku', 'qty', 'unit', 'total', 'rate', 'tax', 'type', 'delivery' ) ) );
		}
		$o = array(
			'number'    => $num,
			'key'       => wp_generate_password( 32, false, false ),
			'created'   => time(),
			'customer'  => $c['customer'],
			'ship_to'   => $c['ship_to'],
			'note'      => $c['note'],
			'items'     => $items,
			'subtotal'  => $t['subtotal'],
			'discount'  => $t['discount'],
			'coupon'    => $t['coupon'],
			'shipping'  => $t['shipping'] ? array( 'id' => $t['shipping']['id'], 'name' => $t['shipping']['name'], 'cost' => $t['shipping']['cost'], 'type' => $t['shipping']['type'] ) : null,
			'fee'       => $t['fee'],
			'tax_lines' => $t['tax_lines'],
			'tax'       => $t['tax'],
			'total'     => $t['total'],
			'currency'  => self::get( 'currency', 'EUR' ),
			'small'     => self::small_business() ? 1 : 0,
			'payment'   => array( 'method' => $pay, 'status' => 'pending' ),
			'fulfil'    => array( 'status' => $t['needs_ship'] ? 'unfulfilled' : 'none' ),
			'notes'     => array(),
			'user'      => get_current_user_id(),
			'source'    => $c['source'] ?? '',
			'consent'   => $c['consent'] ?? array(),
		);
		foreach ( $items as $i ) {
			self::adjust_stock( $i['pid'], $i['vid'], -$i['qty'] );
		}
		$o['stock_out'] = 1;
		if ( $t['coupon'] ) {
			$list = self::coupons();
			foreach ( $list as &$cp ) {
				if ( strtoupper( $cp['code'] ) === $t['coupon'] ) {
					$cp['used'] = (int) ( $cp['used'] ?? 0 ) + 1;
				}
			}
			unset( $cp );
			self::save_coupons( $list );
		}
		self::note( $o, __( 'Order placed.', 'velox' ) );
		self::save_order( $oid, $o );
		return self::order( $oid );
	}

	/** Put the stock of an order back (cancelled, never paid, withdrawn and returned). */
	public static function restock( &$o ) {
		if ( empty( $o['stock_out'] ) ) {
			return;
		}
		foreach ( $o['items'] as $i ) {
			if ( ! empty( $i['pid'] ) ) {
				self::adjust_stock( $i['pid'], $i['vid'] ?? '', (int) $i['qty'] );
			}
		}
		$o['stock_out'] = 0;
	}

	/** Payment arrived (provider webhook, or marked by hand). */
	public static function mark_paid( $id, $ref = '', $by_hand = false, $send = true ) {
		$o = self::order( $id );
		if ( ! $o || 'paid' === $o['payment']['status'] ) {
			return $o;
		}
		if ( 'cancelled' === $o['payment']['status'] && empty( $o['stock_out'] ) ) {
			// Paid after it was cancelled for being late: take the stock again.
			foreach ( $o['items'] as $i ) {
				self::adjust_stock( $i['pid'], $i['vid'] ?? '', -$i['qty'] );
			}
			$o['stock_out'] = 1;
			if ( 'cancelled' === $o['fulfil']['status'] ) {
				$o['fulfil']['status'] = 'unfulfilled';
			}
		}
		$o['payment']['status']  = 'paid';
		$o['payment']['paid_at'] = time();
		if ( $ref ) {
			$o['payment']['ref'] = (string) $ref;
		}
		self::assign_invoice( $o );
		self::note( $o, ( $by_hand ? __( 'Marked as paid.', 'velox' ) : __( 'Payment received.', 'velox' ) ) . ( $ref ? ' (' . $ref . ')' : '' ) );
		if ( 'none' === $o['fulfil']['status'] ) {
			$o['fulfil']['status'] = 'done';
		}
		self::save_order( $id, $o );
		if ( $send ) {
			self::mail( 'paid', $o );
		}
		return self::order( $id );
	}

	public static function assign_invoice( &$o ) {
		if ( ! empty( $o['invoice']['number'] ) ) {
			return;
		}
		$prefix = strtr( (string) self::get( 'invoice_prefix', 'RE-{Y}-' ), array( '{Y}' => gmdate( 'Y' ), '{M}' => gmdate( 'm' ) ) );
		$o['invoice'] = array( 'number' => $prefix . self::next_number( self::OPT_INV, 1 ), 'date' => time() );
	}

	public static function mark_shipped( $id, $carrier = '', $tracking = '' ) {
		$o = self::order( $id );
		if ( ! $o ) {
			return null;
		}
		$pickup = $o['shipping'] && 'pickup' === ( $o['shipping']['type'] ?? '' );
		$o['fulfil'] = array( 'status' => $pickup ? 'ready' : 'shipped', 'carrier' => sanitize_text_field( $carrier ), 'tracking' => sanitize_text_field( $tracking ), 'at' => time() );
		self::note( $o, $pickup ? __( 'Ready for pickup.', 'velox' ) : __( 'Shipped.', 'velox' ) . ( $tracking ? ' ' . $carrier . ' ' . $tracking : '' ) );
		self::save_order( $id, $o );
		self::mail( 'shipped', $o );
		return self::order( $id );
	}

	public static function mark_delivered( $id ) {
		$o = self::order( $id );
		if ( ! $o ) {
			return null;
		}
		$o['fulfil']['status'] = 'delivered';
		self::note( $o, __( 'Delivered.', 'velox' ) );
		self::save_order( $id, $o );
		return self::order( $id );
	}

	public static function cancel( $id, $reason = '' ) {
		$o = self::order( $id );
		if ( ! $o || 'cancelled' === $o['payment']['status'] ) {
			return $o;
		}
		self::restock( $o );
		$was_paid = in_array( $o['payment']['status'], array( 'paid', 'partial' ), true );
		if ( ! $was_paid ) {
			$o['payment']['status'] = 'cancelled';
		}
		$o['fulfil']['status']  = 'cancelled';
		self::note( $o, __( 'Cancelled.', 'velox' ) . ( $reason ? ' ' . $reason : '' ) . ( $was_paid ? ' ' . __( 'The payment still has to be refunded.', 'velox' ) : '' ) );
		self::save_order( $id, $o );
		self::mail( 'cancelled', $o );
		return self::order( $id );
	}

	/** Record a refund (the provider refund itself is done by Velox_Shop_Pay). */
	public static function record_refund( $id, $amount, $restock = false, $ref = '' ) {
		$o = self::order( $id );
		if ( ! $o ) {
			return null;
		}
		$amount = self::r( min( $o['total'] - (float) $o['payment']['refunded'], max( 0, $amount ) ) );
		if ( $amount <= 0 ) {
			return $o;
		}
		$o['payment']['refunded'] = self::r( (float) $o['payment']['refunded'] + $amount );
		$o['payment']['status']   = $o['payment']['refunded'] >= $o['total'] ? 'refunded' : 'partial';
		if ( $restock ) {
			self::restock( $o );
		}
		self::note( $o, sprintf( /* translators: %s: amount */ __( 'Refunded %s.', 'velox' ), self::money( $amount ) ) . ( $ref ? ' (' . $ref . ')' : '' ) );
		self::save_order( $id, $o );
		self::mail( 'refunded', $o, array( 'amount' => $amount ) );
		return self::order( $id );
	}

	/**
	 * Hourly: cancel card / PayPal orders that were never paid (their stock
	 * goes back), and unpaid bank transfers after the set number of days.
	 */
	public static function tick() {
		$hold = max( 15, (int) self::get( 'hold_minutes', 60 ) ) * 60;
		$bank = (int) self::get( 'bank_days', 10 ) * DAY_IN_SECONDS;
		$ids  = get_posts( array( 'post_type' => self::ORDER, 'post_status' => 'publish', 'numberposts' => 200, 'fields' => 'ids', 'meta_key' => '_vxs_pay', 'meta_value' => 'pending' ) );
		foreach ( $ids as $id ) {
			$o = self::order( $id );
			if ( ! $o || ! empty( $o['legacy'] ) ) {
				continue;
			}
			$age = time() - (int) $o['created'];
			$m   = $o['payment']['method'];
			if ( in_array( $m, array( 'stripe', 'paypal' ), true ) && $age > $hold ) {
				self::cancel( $id, __( 'The payment was not completed in time.', 'velox' ) );
			} elseif ( 'bank' === $m && $bank > 0 && $age > $bank ) {
				self::cancel( $id, __( 'The bank transfer did not arrive in time.', 'velox' ) );
			}
		}
	}

	/* ----------------------------------------------------------------- emails */

	public static function mail_types() {
		return array(
			'placed'    => array( __( 'Order confirmation', 'velox' ), __( 'To the customer right after ordering, with what to pay and how, plus your terms and withdrawal policy.', 'velox' ) ),
			'paid'      => array( __( 'Payment received', 'velox' ), __( 'To the customer when the payment has arrived.', 'velox' ) ),
			'shipped'   => array( __( 'Shipped', 'velox' ), __( 'To the customer when you mark the order as shipped (with tracking).', 'velox' ) ),
			'cancelled' => array( __( 'Cancelled', 'velox' ), __( 'To the customer when an order is cancelled.', 'velox' ) ),
			'refunded'  => array( __( 'Refund', 'velox' ), __( 'To the customer when money goes back.', 'velox' ) ),
			'withdrawn' => array( __( 'Withdrawal received', 'velox' ), __( 'To the customer as confirmation when they withdraw from the contract (required by law).', 'velox' ) ),
			'reminder'  => array( __( 'Payment reminder', 'velox' ), __( 'To the customer, sent by you from an unpaid order.', 'velox' ) ),
			'admin'     => array( __( 'New order (to you)', 'velox' ), __( 'To the shop address for every new order and withdrawal.', 'velox' ) ),
		);
	}

	public static function mail_on( $type ) {
		if ( 'withdrawn' === $type ) {
			return true; // the law requires it
		}
		return ! in_array( $type, (array) self::get( 'mail_off', array() ), true );
	}

	/** Send one of the shop emails. */
	public static function mail( $type, $o, $extra = array() ) {
		if ( ! self::mail_on( $type ) || ! $o ) {
			return false;
		}
		$to = 'admin' === $type ? (string) self::get( 'mail_admin', get_option( 'admin_email' ) ) : $o['customer']['email'];
		if ( ! is_email( $to ) ) {
			return false;
		}
		list( $subject, $html ) = self::mail_body( $type, $o, $extra );
		$from = (string) self::get( 'mail_from', '' );
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		if ( is_email( $from ) ) {
			$headers[] = 'From: ' . wp_specialchars_decode( (string) self::get( 'mail_from_name', get_bloginfo( 'name' ) ), ENT_QUOTES ) . ' <' . $from . '>';
		}
		$reply = (string) self::get( 'store_email', '' );
		if ( 'admin' !== $type && is_email( $reply ) ) {
			$headers[] = 'Reply-To: ' . $reply;
		}
		return wp_mail( $to, $subject, $html, $headers );
	}

	/** [ subject, html ] for a shop email. Also used for the preview in the shop editor. */
	public static function mail_body( $type, $o, $extra = array() ) {
		$store = (string) self::get( 'store_name', get_bloginfo( 'name' ) );
		$name  = trim( (string) $o['customer']['first'] );
		$hi    = '' !== $name ? sprintf( /* translators: %s: first name */ __( 'Hello %s,', 'velox' ), esc_html( $name ) ) : __( 'Hello,', 'velox' );
		$num   = $o['number'];
		$view  = Velox_Shop_Front::order_url( $o );
		$intro = '';
		$after = '';
		switch ( $type ) {
			case 'placed':
				/* translators: 1: order number 2: store */
				$subject = sprintf( __( 'Your order %1$s at %2$s', 'velox' ), $num, $store );
				$intro   = __( 'thank you for your order. Here is everything at a glance.', 'velox' );
				$after   = Velox_Shop_Pay::instructions_html( $o, true ) . self::legal_html();
				break;
			case 'paid':
				/* translators: %s: order number */
				$subject = sprintf( __( 'Payment received for %s', 'velox' ), $num );
				$intro   = ( 'none' === $o['fulfil']['status'] || 'done' === $o['fulfil']['status'] )
					? __( 'your payment has arrived — thank you. Your order is complete.', 'velox' )
					: __( 'your payment has arrived — thank you. We are getting your order ready.', 'velox' );
				$after   = self::downloads_html( $o );
				break;
			case 'shipped':
				$pickup  = 'ready' === $o['fulfil']['status'];
				/* translators: %s: order number */
				$subject = $pickup ? sprintf( __( 'Your order %s is ready for pickup', 'velox' ), $num ) : sprintf( __( 'Your order %s is on its way', 'velox' ), $num );
				$intro   = $pickup ? __( 'your order is ready and waiting for you.', 'velox' ) : __( 'good news: your order has been shipped.', 'velox' );
				if ( $o['fulfil']['tracking'] ) {
					$after = '<p style="margin:0 0 16px"><b>' . esc_html__( 'Tracking', 'velox' ) . ':</b> ' . esc_html( trim( $o['fulfil']['carrier'] . ' ' . $o['fulfil']['tracking'] ) ) . '</p>';
					$link  = self::tracking_url( $o['fulfil']['carrier'], $o['fulfil']['tracking'] );
					if ( $link ) {
						$after .= self::mail_button( $link, __( 'Track the parcel', 'velox' ) );
					}
				}
				if ( $pickup && self::get( 'pickup_address' ) ) {
					$after .= '<p style="margin:0 0 16px">' . nl2br( esc_html( (string) self::get( 'pickup_address' ) ) ) . '</p>';
				}
				break;
			case 'cancelled':
				/* translators: %s: order number */
				$subject = sprintf( __( 'Your order %s has been cancelled', 'velox' ), $num );
				$intro   = __( 'your order has been cancelled. If you have already paid, the money will be returned to you.', 'velox' );
				break;
			case 'refunded':
				/* translators: %s: order number */
				$subject = sprintf( __( 'Refund for your order %s', 'velox' ), $num );
				/* translators: %s: amount */
				$intro   = sprintf( __( 'we have refunded %s to you. Depending on your bank it can take a few days to show.', 'velox' ), self::money( (float) ( $extra['amount'] ?? $o['payment']['refunded'] ) ) );
				break;
			case 'withdrawn':
				/* translators: %s: order number */
				$subject = sprintf( __( 'We have received your withdrawal for %s', 'velox' ), $num );
				/* translators: %s: date and time */
				$intro   = sprintf( __( 'we confirm that we received your withdrawal on %s. We will be in touch about the return and the refund.', 'velox' ), esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) ( $o['withdrawal']['at'] ?? time() ) ) ) );
				break;
			case 'reminder':
				/* translators: %s: order number */
				$subject = sprintf( __( 'Reminder: payment for order %s', 'velox' ), $num );
				$intro   = __( 'we have not received the payment for your order yet. Here are the details again — if you have already paid, please ignore this email.', 'velox' );
				$after   = Velox_Shop_Pay::instructions_html( $o, true );
				break;
			case 'admin':
			default:
				$w = ! empty( $extra['withdrawal'] );
				/* translators: 1: order number 2: total */
				$subject = $w ? sprintf( __( 'Withdrawal for order %s', 'velox' ), $num ) : sprintf( __( 'New order %1$s — %2$s', 'velox' ), $num, html_entity_decode( self::money( $o['total'] ) ) );
				$hi      = '';
				$intro   = $w ? __( 'A customer has withdrawn from this order. Confirm the return and refund in the shop editor.', 'velox' ) : sprintf( /* translators: %s: payment method */ __( 'A new order has come in, paid with: %s.', 'velox' ), esc_html( Velox_Shop_Pay::label( $o['payment']['method'] ) ) );
				$view    = Velox_Shop_Admin::order_admin_url( $o['id'] );
				break;
		}
		$html = self::mail_layout(
			( $hi ? '<p style="margin:0 0 6px">' . $hi . '</p>' : '' ) . '<p style="margin:0 0 22px">' . $intro . '</p>' .
			$after . self::summary_html( $o ) .
			self::mail_button( $view, 'admin' === $type ? __( 'Open in the shop editor', 'velox' ) : __( 'View your order', 'velox' ) ),
			$subject
		);
		return array( wp_specialchars_decode( $subject, ENT_QUOTES ), $html );
	}

	public static function tracking_url( $carrier, $no ) {
		$no = rawurlencode( trim( (string) $no ) );
		$c  = strtolower( (string) $carrier );
		if ( '' === $no ) {
			return '';
		}
		if ( false !== strpos( $c, 'dhl' ) ) {
			return 'https://www.dhl.de/de/privatkunden/pakete-empfangen/verfolgen.html?piececode=' . $no;
		}
		if ( false !== strpos( $c, 'dpd' ) ) {
			return 'https://tracking.dpd.de/status/de_DE/parcel/' . $no;
		}
		if ( false !== strpos( $c, 'hermes' ) ) {
			return 'https://www.myhermes.de/empfangen/sendungsverfolgung/sendungsinformation#' . $no;
		}
		if ( false !== strpos( $c, 'gls' ) ) {
			return 'https://gls-group.com/DE/de/paketverfolgung?match=' . $no;
		}
		if ( false !== strpos( $c, 'ups' ) ) {
			return 'https://www.ups.com/track?tracknum=' . $no;
		}
		return '';
	}

	public static function mail_button( $url, $label ) {
		$acc = sanitize_hex_color( (string) self::get( 'accent', '#0a6f9f' ) ) ?: '#0a6f9f';
		return '<p style="margin:26px 0 0"><a href="' . esc_url( $url ) . '" style="display:inline-block;padding:12px 22px;border-radius:8px;background:' . $acc . ';color:#ffffff;text-decoration:none;font-weight:600">' . esc_html( $label ) . '</a></p>';
	}

	public static function summary_html( $o ) {
		$cell = 'padding:10px 0;border-bottom:1px solid #eceef2;vertical-align:top';
		$rows = '';
		foreach ( $o['items'] as $i ) {
			$rows .= '<tr><td style="' . $cell . '">' . esc_html( $i['name'] ) . ( ! empty( $i['variant'] ) ? '<br><span style="color:#6b7280;font-size:13px">' . esc_html( $i['variant'] ) . '</span>' : '' ) . '</td>'
				. '<td style="' . $cell . ';text-align:center;color:#6b7280;white-space:nowrap">× ' . (int) $i['qty'] . '</td>'
				. '<td style="' . $cell . ';text-align:right;white-space:nowrap">' . esc_html( self::money( $i['total'] ) ) . '</td></tr>';
		}
		$sum = function ( $l, $v, $b = false ) {
			return '<tr><td colspan="2" style="padding:5px 0;color:' . ( $b ? '#111827;font-weight:700' : '#6b7280' ) . '">' . $l . '</td><td style="padding:5px 0;text-align:right;white-space:nowrap' . ( $b ? ';font-weight:700' : '' ) . '">' . $v . '</td></tr>';
		};
		$rows .= '<tr><td colspan="3" style="height:8px"></td></tr>';
		$rows .= $sum( esc_html__( 'Subtotal', 'velox' ), esc_html( self::money( $o['subtotal'] ) ) );
		if ( $o['discount'] > 0 ) {
			$rows .= $sum( esc_html__( 'Discount', 'velox' ) . ( $o['coupon'] ? ' (' . esc_html( $o['coupon'] ) . ')' : '' ), '− ' . esc_html( self::money( $o['discount'] ) ) );
		}
		if ( $o['shipping'] ) {
			$rows .= $sum( esc_html( $o['shipping']['name'] ), $o['shipping']['cost'] > 0 ? esc_html( self::money( $o['shipping']['cost'] ) ) : esc_html__( 'Free', 'velox' ) );
		}
		if ( $o['fee'] > 0 ) {
			$rows .= $sum( esc_html__( 'Cash on delivery fee', 'velox' ), esc_html( self::money( $o['fee'] ) ) );
		}
		$rows .= $sum( esc_html__( 'Total', 'velox' ), esc_html( self::money( $o['total'] ) ), true );
		if ( ! empty( $o['small'] ) ) {
			$rows .= '<tr><td colspan="3" style="padding:4px 0;color:#6b7280;font-size:12px">' . esc_html( (string) self::get( 'small_note' ) ) . '</td></tr>';
		} else {
			foreach ( (array) $o['tax_lines'] as $rate => $amt ) {
				/* translators: %s: VAT rate */
				$rows .= '<tr><td colspan="2" style="padding:2px 0;color:#6b7280;font-size:12px">' . sprintf( esc_html__( 'Includes %s%% VAT', 'velox' ), esc_html( rtrim( rtrim( number_format_i18n( (float) $rate, 2 ), '0' ), ',.' ) ) ) . '</td><td style="padding:2px 0;text-align:right;color:#6b7280;font-size:12px">' . esc_html( self::money( $amt ) ) . '</td></tr>';
			}
		}
		$addr = self::address_html( $o['ship_to'] ? $o['ship_to'] : $o['customer'] );
		return '<p style="margin:26px 0 6px;font-weight:700">' . esc_html__( 'Order', 'velox' ) . ' ' . esc_html( $o['number'] ) . '</p>'
			. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:14px">' . $rows . '</table>'
			. ( $addr && $o['shipping'] && 'pickup' !== ( $o['shipping']['type'] ?? '' ) ? '<p style="margin:20px 0 4px;font-weight:700">' . esc_html__( 'Delivery address', 'velox' ) . '</p><p style="margin:0;color:#374151">' . $addr . '</p>' : '' );
	}

	public static function address_html( $c ) {
		if ( ! $c ) {
			return '';
		}
		$a = (array) ( $c['address'] ?? array() );
		$cs = self::countries();
		$parts = array_filter( array(
			esc_html( trim( ( $c['company'] ?? '' ) ) ),
			esc_html( trim( ( $c['first'] ?? '' ) . ' ' . ( $c['last'] ?? '' ) ) ),
			esc_html( $a['line1'] ?? '' ),
			esc_html( $a['line2'] ?? '' ),
			esc_html( trim( ( $a['zip'] ?? '' ) . ' ' . ( $a['city'] ?? '' ) ) ),
			esc_html( $cs[ $a['country'] ?? '' ] ?? ( $a['country'] ?? '' ) ),
		) );
		return implode( '<br>', $parts );
	}

	public static function downloads_html( $o ) {
		$links = Velox_Shop_Front::download_links( $o );
		if ( ! $links ) {
			return '';
		}
		$out = '<p style="margin:0 0 6px;font-weight:700">' . esc_html__( 'Your downloads', 'velox' ) . '</p><ul style="margin:0 0 18px;padding-left:18px">';
		foreach ( $links as $l ) {
			$out .= '<li><a href="' . esc_url( $l['url'] ) . '">' . esc_html( $l['name'] ) . '</a></li>';
		}
		return $out . '</ul>';
	}

	/** Terms and withdrawal policy in the confirmation email (a "durable medium", § 312f BGB). */
	public static function legal_html() {
		$out = '';
		foreach ( array( 'page_withdrawal' => __( 'Withdrawal policy', 'velox' ), 'page_terms' => __( 'Terms and conditions', 'velox' ) ) as $k => $label ) {
			$pid = (int) self::get( $k );
			if ( ! $pid || 'publish' !== get_post_status( $pid ) ) {
				continue;
			}
			$txt = wp_strip_all_tags( strip_shortcodes( (string) get_post_field( 'post_content', $pid ) ) );
			$txt = trim( preg_replace( "/\n{3,}/", "\n\n", $txt ) );
			if ( '' === $txt ) {
				continue;
			}
			$out .= '<details style="margin:22px 0 0"><summary style="font-weight:700;cursor:pointer">' . esc_html( $label ) . '</summary><div style="margin-top:8px;font-size:12px;line-height:1.55;color:#4b5563;white-space:pre-line">' . esc_html( mb_substr( $txt, 0, 12000 ) ) . '</div></details>';
		}
		return $out;
	}

	public static function mail_layout( $body, $title ) {
		$store = (string) self::get( 'store_name', get_bloginfo( 'name' ) );
		$logo  = (int) self::get( 'logo' ) ? wp_get_attachment_image_url( (int) self::get( 'logo' ), 'medium' ) : '';
		$head  = $logo ? '<img src="' . esc_url( $logo ) . '" alt="' . esc_attr( $store ) . '" style="max-height:44px;max-width:200px;display:block">' : '<b style="font-size:18px;color:#111827">' . esc_html( $store ) . '</b>';
		$foot  = trim( (string) self::get( 'mail_footer', '' ) );
		if ( '' === $foot ) {
			$foot = $store . "\n" . (string) self::get( 'store_address', '' );
		}
		return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>' . esc_html( $title ) . '</title></head>'
			. '<body style="margin:0;padding:0;background:#f3f4f6;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;color:#111827;line-height:1.55">'
			. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:28px 12px"><tr><td align="center">'
			. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:14px;overflow:hidden">'
			. '<tr><td style="padding:26px 32px;border-bottom:1px solid #eceef2">' . $head . '</td></tr>'
			. '<tr><td style="padding:30px 32px 34px;font-size:15px">' . $body . '</td></tr>'
			. '<tr><td style="padding:20px 32px;background:#f9fafb;color:#6b7280;font-size:12px;white-space:pre-line">' . esc_html( $foot ) . '</td></tr>'
			. '</table></td></tr></table></body></html>';
	}

	public static function mail_admin_low_stock( $pid, $left ) {
		if ( get_transient( 'velox_shop_low_' . $pid ) ) {
			return;
		}
		set_transient( 'velox_shop_low_' . $pid, 1, DAY_IN_SECONDS );
		$to = (string) self::get( 'mail_admin', get_option( 'admin_email' ) );
		if ( ! is_email( $to ) ) {
			return;
		}
		/* translators: 1: product 2: units left */
		$subject = sprintf( __( 'Low stock: %1$s (%2$d left)', 'velox' ), get_the_title( $pid ), $left );
		$html    = self::mail_layout( '<p>' . esc_html( $subject ) . '</p>' . self::mail_button( Velox_Shop_Admin::product_admin_url( $pid ), __( 'Open the product', 'velox' ) ), $subject );
		wp_mail( $to, $subject, $html, array( 'Content-Type: text/html; charset=UTF-8' ) );
	}

	/* ------------------------------------------------------------- withdrawal */

	/**
	 * The withdrawal function (§ 356a BGB): the customer names the order and
	 * their email, confirms, and gets a receipt by email. The order is marked;
	 * nothing is cancelled or refunded automatically — that stays your call.
	 */
	public static function withdraw( $o, $message = '' ) {
		if ( ! $o || ! empty( $o['withdrawal'] ) ) {
			return $o;
		}
		$o['withdrawal'] = array( 'at' => time(), 'message' => mb_substr( sanitize_textarea_field( $message ), 0, 2000 ), 'status' => 'open' );
		self::note( $o, __( 'The customer has withdrawn from the contract.', 'velox' ) );
		self::save_order( $o['id'], $o );
		$o = self::order( $o['id'] );
		self::mail( 'withdrawn', $o );
		self::mail( 'admin', $o, array( 'withdrawal' => 1 ) );
		return $o;
	}

	/** Is the order still inside the withdrawal period? */
	public static function can_withdraw( $o ) {
		if ( ! $o || ! empty( $o['withdrawal'] ) || in_array( $o['payment']['status'], array( 'cancelled', 'refunded' ), true ) ) {
			return false;
		}
		$days = max( 14, (int) self::get( 'withdraw_days', 14 ) );
		$from = (int) ( $o['fulfil']['at'] ?: $o['created'] );
		return time() < $from + ( $days + 7 ) * DAY_IN_SECONDS; // a week's grace for delivery
	}
}
