<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Velox Shop — checkout and payments.
 *
 * The order is always created HERE first, on the shop's own page, with the
 * legally required "Buy now" button (§ 312j BGB). Only then do Stripe or
 * PayPal collect the money on their own hosted pages — they never decide
 * whether a contract exists, and no card number ever reaches this site.
 * "Paid" comes only from the provider (webhook or a server-side check),
 * never from the visitor's browser.
 *
 * @package Velox
 */
class Velox_Shop_Pay {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'handle_post' ), 20 );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	/* ---------------------------------------------------------------- methods */

	public static function methods() {
		return array(
			// Only offered while nothing else is set up in a shop from before 4.53,
			// so its checkout keeps taking orders the way it always did.
			'manual'  => array( __( 'Payment details by email', 'velox' ), __( 'We confirm your order and send you the payment details by email.', 'velox' ) ),
			'stripe'  => array( __( 'Card, Apple Pay, Google Pay & more', 'velox' ), __( 'Paid securely on Stripe’s page. Cards, wallets, SEPA direct debit, Klarna and more — whatever you switch on in Stripe.', 'velox' ) ),
			'paypal'  => array( __( 'PayPal', 'velox' ), __( 'Paid on PayPal’s page.', 'velox' ) ),
			'bank'    => array( __( 'Bank transfer', 'velox' ), __( 'You get the bank details right after ordering. We ship once the money has arrived.', 'velox' ) ),
			'invoice' => array( __( 'Invoice', 'velox' ), __( 'Pay after receiving the goods.', 'velox' ) ),
			'cod'     => array( __( 'Cash on delivery', 'velox' ), __( 'Pay when the parcel arrives.', 'velox' ) ),
		);
	}

	/** The method's name: the long one for the checkout, a short one for receipts. */
	public static function label( $m, $short = true ) {
		if ( $short ) {
			$s = array( 'manual' => __( 'Payment details by email', 'velox' ), 'stripe' => __( 'Card or wallet (Stripe)', 'velox' ), 'paypal' => 'PayPal', 'bank' => __( 'Bank transfer', 'velox' ), 'invoice' => __( 'Invoice', 'velox' ), 'cod' => __( 'Cash on delivery', 'velox' ) );
			if ( isset( $s[ $m ] ) ) {
				return $s[ $m ];
			}
		}
		$all = self::methods();
		return isset( $all[ $m ] ) ? $all[ $m ][0] : (string) $m;
	}

	/** Switched on AND set up. */
	public static function ready( $m ) {
		$s = Velox_Shop::settings();
		switch ( $m ) {
			case 'stripe':
				return ! empty( $s['pay_stripe'] ) && 0 === strpos( (string) $s['stripe_secret'], 'sk_' );
			case 'paypal':
				return ! empty( $s['pay_paypal'] ) && '' !== $s['paypal_id'] && '' !== $s['paypal_secret'];
			case 'bank':
				return ! empty( $s['pay_bank'] ) && '' !== trim( (string) $s['bank_iban'] );
			case 'invoice':
				return ! empty( $s['pay_invoice'] );
			case 'cod':
				return ! empty( $s['pay_cod'] );
			case 'manual':
				return false; // see available()
		}
		return false;
	}

	/** Methods offered at checkout, in the shop's order. */
	public static function available( $t = null ) {
		$out = array();
		$order = array_unique( array_merge( (array) Velox_Shop::get( 'pay_order', array() ), array_keys( self::methods() ) ) );
		foreach ( $order as $m ) {
			if ( ! isset( self::methods()[ $m ] ) || ! self::ready( $m ) ) {
				continue;
			}
			if ( 'cod' === $m && $t && empty( $t['needs_ship'] ) ) {
				continue; // nothing is delivered
			}
			// Invoice and cash on delivery only up to a value the shop chose.
			if ( $t && in_array( $m, array( 'invoice', 'cod' ), true ) ) {
				$max = Velox_Shop::num( Velox_Shop::get( $m . '_max', '' ) );
				if ( null !== $max && $max > 0 && (float) $t['total'] > $max ) {
					continue;
				}
			}
			$out[] = $m;
		}
		if ( ! $out && Velox_Shop::legacy_upgrade() ) {
			$out[] = 'manual';
		}
		return $out;
	}

	/** Invoice only for customers who have ordered and paid before (when set). */
	private static function invoice_allowed( $email ) {
		if ( ! Velox_Shop::get( 'invoice_known' ) ) {
			return true;
		}
		$ids = get_posts( array( 'post_type' => Velox_Shop::ORDER, 'post_status' => 'publish', 'numberposts' => 1, 'fields' => 'ids',
			'meta_query' => array( array( 'key' => '_vx_email', 'value' => $email ), array( 'key' => '_vxs_pay', 'value' => 'paid' ) ) ) );
		return (bool) $ids;
	}

	/* ------------------------------------------------------------- the checkout */

	public static function handle_post() {
		if ( empty( $_POST['velox_shop_action'] ) ) {
			return;
		}
		$action = sanitize_key( wp_unslash( $_POST['velox_shop_action'] ) );
		// Cart changes without JavaScript (the AJAX path is the REST route below).
		if ( in_array( $action, array( 'add', 'set', 'remove', 'coupon' ), true ) ) {
			$pid = (int) ( $_POST['product'] ?? 0 );
			$vid = sanitize_key( wp_unslash( $_POST['variant'] ?? '' ) );
			$msg = '';
			if ( 'add' === $action ) {
				$msg = Velox_Shop::cart_set( $pid, $vid, max( 1, (int) ( $_POST['qty'] ?? 1 ) ), true );
			} elseif ( 'set' === $action ) {
				$msg = Velox_Shop::cart_set( $pid, $vid, (int) ( $_POST['qty'] ?? 0 ) );
			} elseif ( 'remove' === $action ) {
				$msg = Velox_Shop::cart_set( $pid, $vid, 0 );
			} else {
				$cart = Velox_Shop::get_cart();
				$cart['_c'] = strtoupper( sanitize_text_field( wp_unslash( $_POST['coupon'] ?? '' ) ) );
				Velox_Shop::save_cart( $cart );
			}
			self::back( '' === $msg ? ( 'add' === $action ? 'added' : 'updated' ) : 'error', $msg );
		}
		if ( 'checkout' === $action ) {
			self::checkout();
		}
		if ( 'withdraw' === $action ) {
			Velox_Shop_Front::handle_withdraw();
		}
	}

	private static function back( $flag, $msg = '' ) {
		$url = self::return_url();
		$args = array( 'vx' => $flag );
		if ( '' !== $msg ) {
			$args['vxm'] = rawurlencode( $msg );
		}
		wp_safe_redirect( add_query_arg( $args, $url ) );
		exit;
	}

	private static function return_url() {
		if ( ! empty( $_POST['vx_return'] ) ) {
			$raw = esc_url_raw( wp_unslash( $_POST['vx_return'] ) );
			if ( $raw && 0 === strpos( $raw, untrailingslashit( home_url() ) ) ) {
				return remove_query_arg( array( 'vx', 'vxm' ), $raw );
			}
		}
		$ref = wp_get_referer();
		return $ref ? remove_query_arg( array( 'vx', 'vxm' ), $ref ) : home_url( '/' );
	}

	/** Read and check the checkout form. Returns [ data, errors[] ]. */
	public static function read_checkout( $src ) {
		$g = function ( $k, $max = 120 ) use ( $src ) {
			return isset( $src[ $k ] ) ? mb_substr( trim( sanitize_text_field( wp_unslash( $src[ $k ] ) ) ), 0, $max ) : '';
		};
		$err = array();
		$countries = Velox_Shop::ship_countries();
		$c = array(
			'email'   => sanitize_email( wp_unslash( $src['vx_email'] ?? '' ) ),
			'first'   => $g( 'vx_first', 60 ),
			'last'    => $g( 'vx_last', 60 ),
			'company' => $g( 'vx_company', 100 ),
			'phone'   => preg_replace( '/[^0-9+()\/ -]/', '', $g( 'vx_phone', 30 ) ),
			'address' => array(
				'line1'   => $g( 'vx_line1', 120 ),
				'line2'   => $g( 'vx_line2', 120 ),
				'zip'     => $g( 'vx_zip', 12 ),
				'city'    => $g( 'vx_city', 80 ),
				'country' => strtoupper( $g( 'vx_country', 2 ) ),
			),
		);
		if ( ! is_email( $c['email'] ) ) {
			$err['vx_email'] = __( 'Please enter a valid email address.', 'velox' );
		}
		foreach ( array( 'first' => __( 'Please enter your first name.', 'velox' ), 'last' => __( 'Please enter your last name.', 'velox' ) ) as $k => $m ) {
			if ( '' === $c[ $k ] ) {
				$err[ 'vx_' . $k ] = $m;
			}
		}
		if ( 'required' === Velox_Shop::get( 'f_phone' ) && '' === $c['phone'] ) {
			$err['vx_phone'] = __( 'Please enter a phone number.', 'velox' );
		}
		if ( 'required' === Velox_Shop::get( 'f_company' ) && '' === $c['company'] ) {
			$err['vx_company'] = __( 'Please enter your company.', 'velox' );
		}
		$needs_addr = ! empty( $src['vx_needs_addr'] );
		if ( $needs_addr ) {
			foreach ( array( 'line1' => __( 'Please enter street and number.', 'velox' ), 'zip' => __( 'Please enter the postcode.', 'velox' ), 'city' => __( 'Please enter the town.', 'velox' ) ) as $k => $m ) {
				if ( '' === $c['address'][ $k ] ) {
					$err[ 'vx_' . $k ] = $m;
				}
			}
		}
		if ( ! isset( $countries[ $c['address']['country'] ] ) ) {
			$c['address']['country'] = (string) key( $countries );
		}
		$ship_to = null;
		if ( ! empty( $src['vx_other'] ) ) {
			$ship_to = array(
				'first'   => $g( 'vx_s_first', 60 ), 'last' => $g( 'vx_s_last', 60 ), 'company' => $g( 'vx_s_company', 100 ),
				'address' => array( 'line1' => $g( 'vx_s_line1', 120 ), 'line2' => $g( 'vx_s_line2', 120 ), 'zip' => $g( 'vx_s_zip', 12 ), 'city' => $g( 'vx_s_city', 80 ), 'country' => strtoupper( $g( 'vx_s_country', 2 ) ) ),
			);
			foreach ( array( 'first', 'last' ) as $k ) {
				if ( '' === $ship_to[ $k ] ) {
					$err[ 'vx_s_' . $k ] = __( 'Please fill in the delivery address.', 'velox' );
				}
			}
			foreach ( array( 'line1', 'zip', 'city' ) as $k ) {
				if ( '' === $ship_to['address'][ $k ] ) {
					$err[ 'vx_s_' . $k ] = __( 'Please fill in the delivery address.', 'velox' );
				}
			}
			if ( ! isset( $countries[ $ship_to['address']['country'] ] ) ) {
				$ship_to['address']['country'] = (string) key( $countries );
			}
		}
		$data = array(
			'customer' => $c,
			'ship_to'  => $ship_to,
			'note'     => isset( $src['vx_note'] ) ? mb_substr( sanitize_textarea_field( wp_unslash( $src['vx_note'] ) ), 0, 1000 ) : '',
			'ship'     => sanitize_key( wp_unslash( $src['vx_ship'] ?? '' ) ),
			'pay'      => sanitize_key( wp_unslash( $src['vx_pay'] ?? '' ) ),
			'coupon'   => strtoupper( $g( 'vx_coupon', 40 ) ),
			'terms'    => ! empty( $src['vx_terms'] ),
			'digital'  => ! empty( $src['vx_digital'] ),
			'source'   => $g( 'vx_src', 120 ),
		);
		return array( $data, $err );
	}

	private static function checkout() {
		if ( ! isset( $_POST['_vxs'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_vxs'] ) ), 'velox_shop' ) ) {
			self::back( 'error', __( 'The page had expired. Please check your details and order again.', 'velox' ) );
		}
		// A hidden field people never fill in: bots do.
		if ( ! empty( $_POST['vx_website'] ) ) {
			self::back( 'error', __( 'Something went wrong. Please try again.', 'velox' ) );
		}
		// At most 6 checkouts per visitor in 10 minutes (stops card-testing floods).
		$ipk = 'velox_shop_rl_' . md5( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) );
		$n   = (int) get_transient( $ipk );
		if ( $n >= 6 ) {
			self::back( 'error', __( 'Too many attempts. Please wait a few minutes and try again.', 'velox' ) );
		}
		set_transient( $ipk, $n + 1, 10 * MINUTE_IN_SECONDS );

		$lines = Velox_Shop::lines();
		if ( ! $lines ) {
			self::back( 'error', __( 'Your cart is empty.', 'velox' ) );
		}
		list( $d, $err ) = self::read_checkout( $_POST );
		$country = $d['ship_to'] ? $d['ship_to']['address']['country'] : $d['customer']['address']['country'];
		$t = Velox_Shop::totals( $lines, $country, $d['ship'], $d['coupon'], $d['pay'] );
		if ( $t['needs_ship'] && empty( $_POST['vx_needs_addr'] ) ) {
			$err['vx_line1'] = __( 'Please enter the delivery address.', 'velox' );
		}
		if ( ! in_array( $d['pay'], self::available( $t ), true ) ) {
			$err['vx_pay'] = __( 'Please choose how you would like to pay.', 'velox' );
		} elseif ( 'invoice' === $d['pay'] && ! self::invoice_allowed( $d['customer']['email'] ) ) {
			$err['vx_pay'] = __( 'Paying by invoice is available from your second order. Please choose another way to pay.', 'velox' );
		}
		if ( ! is_user_logged_in() && ! empty( $_POST['vx_account'] ) && Velox_Shop_Account::enabled() ) {
			if ( strlen( (string) wp_unslash( $_POST['vx_acc_pass'] ?? '' ) ) < 8 ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
				$err['vx_acc_pass'] = __( 'Please choose a password with at least 8 characters.', 'velox' );
			} elseif ( email_exists( $d['customer']['email'] ) ) {
				$err['vx_acc_pass'] = __( 'There is already an account for this email. Sign in or reset your password.', 'velox' );
			}
		}
		if ( Velox_Shop::get( 'terms_check' ) && ! $d['terms'] ) {
			$err['vx_terms'] = __( 'Please accept the terms and conditions.', 'velox' );
		}
		if ( $t['digital'] && ! $d['digital'] ) {
			$err['vx_digital'] = __( 'Please confirm that the download can start right away.', 'velox' );
		}
		$min = Velox_Shop::num( Velox_Shop::get( 'min_order' ) );
		if ( null !== $min && $t['subtotal'] < $min ) {
			/* translators: %s: amount */
			$err['vx_total'] = sprintf( __( 'The minimum order value is %s.', 'velox' ), Velox_Shop::money( $min ) );
		}
		if ( $err ) {
			// Hand the form back filled in, with the messages next to the fields (never the password).
			set_transient( 'velox_shop_form_' . md5( (string) ( $_COOKIE[ Velox_Shop::COOKIE ] ?? '' ) . ( $_SERVER['REMOTE_ADDR'] ?? '' ) ), array( 'post' => array_diff_key( wp_unslash( $_POST ), array( 'vx_acc_pass' => 1 ) ), 'err' => $err ), 10 * MINUTE_IN_SECONDS );
			self::back( 'invalid', reset( $err ) );
		}
		$d['consent'] = array_filter( array( 'terms' => $d['terms'] ? time() : 0, 'digital' => $d['digital'] ? time() : 0 ) );
		$o = Velox_Shop::create_order( $d, $lines, $t, $d['pay'] );
		if ( is_wp_error( $o ) ) {
			self::back( 'error', __( 'The order could not be saved. Please try again.', 'velox' ) );
		}
		Velox_Shop::save_cart( array() );
		// Signed in: remember the address. Guest who asked for an account: create it.
		Velox_Shop_Account::after_order( $o );
		if ( in_array( $d['pay'], array( 'stripe', 'paypal' ), true ) ) {
			$url = self::start_payment( $o );
			if ( is_wp_error( $url ) ) {
				$ox = Velox_Shop::order( $o['id'] );
				Velox_Shop::note( $ox, __( 'Could not start the payment:', 'velox' ) . ' ' . $url->get_error_message() );
				Velox_Shop::save_order( $o['id'], $ox );
				wp_safe_redirect( add_query_arg( 'vx', 'payfail', Velox_Shop_Front::order_url( $o ) ) );
				exit;
			}
			wp_redirect( $url ); // the provider's own page
			exit;
		}
		// Bank transfer, invoice, cash on delivery: the order is confirmed now.
		self::confirmed( $o );
		wp_safe_redirect( Velox_Shop_Front::order_url( $o ) );
		exit;
	}

	/** The confirmation emails, once per order. */
	public static function confirmed( $o ) {
		$o = Velox_Shop::order( $o['id'] );
		if ( ! $o || ! empty( $o['mailed'] ) ) {
			return;
		}
		$o['mailed'] = 1;
		Velox_Shop::save_order( $o['id'], $o );
		Velox_Shop::mail( 'placed', $o );
		Velox_Shop::mail( 'admin', $o );
	}

	/** Pay instructions for the thank-you page and the confirmation email. */
	public static function instructions_html( $o, $email = false ) {
		$m  = $o['payment']['method'];
		$st = $o['payment']['status'];
		$box = function ( $html ) use ( $email ) {
			return $email ? '<div style="margin:0 0 22px;padding:16px 18px;border-radius:10px;background:#f3f6f9">' . $html . '</div>' : '<div class="vxs-pay-box">' . $html . '</div>';
		};
		if ( 'paid' === $st ) {
			return $box( '<b>' . esc_html__( 'Paid', 'velox' ) . '</b> — ' . esc_html( self::label( $m ) ) . '.' );
		}
		if ( 'cancelled' === $st ) {
			return '';
		}
		$s = Velox_Shop::settings();
		switch ( $m ) {
			case 'bank':
				$rows = array(
					__( 'Account holder', 'velox' ) => $s['bank_owner'] ? $s['bank_owner'] : $s['store_name'],
					'IBAN' => trim( chunk_split( strtoupper( str_replace( ' ', '', (string) $s['bank_iban'] ) ), 4, ' ' ) ),
					'BIC'  => $s['bank_bic'],
					__( 'Bank', 'velox' ) => $s['bank_name'],
					__( 'Amount', 'velox' ) => Velox_Shop::money( $o['total'] ),
					__( 'Reference', 'velox' ) => $o['number'],
				);
				$t = '';
				foreach ( $rows as $k => $v ) {
					if ( '' !== trim( (string) $v ) ) {
						$t .= $email ? '<tr><td style="padding:3px 16px 3px 0;color:#6b7280">' . esc_html( $k ) . '</td><td style="padding:3px 0;font-weight:600">' . esc_html( $v ) . '</td></tr>' : '<div><dt>' . esc_html( $k ) . '</dt><dd>' . esc_html( $v ) . '</dd></div>';
					}
				}
				return $box( '<b>' . esc_html__( 'Please transfer the amount to:', 'velox' ) . '</b>' . ( $email ? '<table role="presentation" style="margin-top:8px;font-size:14px">' . $t . '</table>' : '<dl class="vxs-bank">' . $t . '</dl>' )
					. '<p' . ( $email ? ' style="margin:10px 0 0;font-size:13px;color:#4b5563"' : '' ) . '>' . esc_html__( 'Please use the reference exactly as shown so we can match your payment. We ship as soon as it arrives.', 'velox' ) . '</p>' );
			case 'invoice':
				/* translators: %d: days */
				return $box( '<b>' . esc_html__( 'Pay by invoice', 'velox' ) . '</b> — ' . sprintf( esc_html__( 'the invoice comes with your order. Please pay within %d days.', 'velox' ), (int) $s['invoice_days'] ) );
			case 'manual':
				return $box( '<b>' . esc_html__( 'We will email you the payment details.', 'velox' ) . '</b> ' . esc_html__( 'Your order is reserved until then.', 'velox' ) );
			case 'cod':
				/* translators: %s: amount */
				return $box( '<b>' . esc_html__( 'Cash on delivery', 'velox' ) . '</b> — ' . sprintf( esc_html__( 'please have %s ready when the parcel arrives.', 'velox' ), esc_html( Velox_Shop::money( $o['total'] ) ) ) );
			case 'stripe':
			case 'paypal':
				$pay = Velox_Shop_Front::pay_url( $o );
				return $box( '<b>' . esc_html__( 'Payment not completed yet.', 'velox' ) . '</b> ' . esc_html__( 'Your order is reserved for a short time.', 'velox' )
					. ( $email ? Velox_Shop::mail_button( $pay, __( 'Pay now', 'velox' ) ) : ' <a class="vxs-btn" href="' . esc_url( $pay ) . '">' . esc_html__( 'Pay now', 'velox' ) . '</a>' ) );
		}
		return '';
	}

	/* ------------------------------------------------------- hosted payments */

	/** Where to send the visitor to pay (Stripe or PayPal page), or WP_Error. */
	public static function start_payment( $o ) {
		return 'paypal' === $o['payment']['method'] ? self::paypal_start( $o ) : self::stripe_start( $o );
	}

	private static function cents( $amount ) {
		return (int) round( (float) $amount * 100 );
	}

	/* ---- Stripe Checkout (hosted). Talks to the REST API directly: no SDK. */

	private static function stripe( $method, $path, $body = null ) {
		$args = array(
			'method'  => $method,
			'timeout' => 25,
			'headers' => array( 'Authorization' => 'Bearer ' . Velox_Shop::get( 'stripe_secret' ), 'Stripe-Version' => '2024-06-20' ),
		);
		if ( null !== $body ) {
			$args['body'] = $body;
		}
		$r = wp_remote_request( 'https://api.stripe.com/v1/' . $path, $args );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$j = json_decode( wp_remote_retrieve_body( $r ), true );
		if ( wp_remote_retrieve_response_code( $r ) >= 400 || ! is_array( $j ) ) {
			return new WP_Error( 'stripe', is_array( $j ) && isset( $j['error']['message'] ) ? $j['error']['message'] : 'Stripe ' . wp_remote_retrieve_response_code( $r ) );
		}
		return $j;
	}

	private static function stripe_start( $o ) {
		$store = (string) Velox_Shop::get( 'store_name', get_bloginfo( 'name' ) );
		$names = array();
		foreach ( $o['items'] as $i ) {
			$names[] = $i['qty'] . ' × ' . $i['name'];
		}
		$body = array(
			'mode'                 => 'payment',
			'client_reference_id'  => (string) $o['id'],
			'customer_email'       => $o['customer']['email'],
			'success_url'          => add_query_arg( 'vxsid', '{CHECKOUT_SESSION_ID}', Velox_Shop_Front::order_url( $o ) ),
			'cancel_url'           => add_query_arg( 'vx', 'paycancel', Velox_Shop_Front::order_url( $o ) ),
			'expires_at'           => time() + max( 30, (int) Velox_Shop::get( 'hold_minutes', 60 ) ) * 60,
			'metadata[order]'      => (string) $o['id'],
			'metadata[number]'     => $o['number'],
			'payment_intent_data[metadata][order]' => (string) $o['id'],
			'payment_intent_data[description]'     => $store . ' ' . $o['number'],
			// One line with the final amount: the order already holds every detail,
			// and rounding can never make Stripe's total differ from ours.
			'line_items[0][quantity]'                               => 1,
			'line_items[0][price_data][currency]'                   => strtolower( (string) Velox_Shop::get( 'currency', 'EUR' ) ),
			'line_items[0][price_data][unit_amount]'                => self::cents( $o['total'] ),
			/* translators: 1: order number 2: store */
			'line_items[0][price_data][product_data][name]'         => sprintf( __( 'Order %1$s — %2$s', 'velox' ), $o['number'], $store ),
			'line_items[0][price_data][product_data][description]'  => mb_substr( implode( ', ', $names ), 0, 300 ),
		);
		$types = array_values( array_intersect( (array) Velox_Shop::get( 'stripe_methods', array() ), array_keys( self::stripe_types() ) ) );
		$s     = null;
		if ( $types ) {
			$typed = $body;
			foreach ( $types as $i => $t ) {
				$typed[ 'payment_method_types[' . $i . ']' ] = $t;
			}
			$s = self::stripe( 'POST', 'checkout/sessions', $typed );
			// A method not active in Stripe (or not for this currency) must not stop the sale.
			if ( is_wp_error( $s ) ) {
				$s = null;
			}
		}
		if ( null === $s ) {
			$s = self::stripe( 'POST', 'checkout/sessions', $body );
		}
		if ( is_wp_error( $s ) ) {
			return $s;
		}
		$o = Velox_Shop::order( $o['id'] );
		$o['payment']['session'] = (string) $s['id'];
		Velox_Shop::save_order( $o['id'], $o );
		return (string) $s['url'];
	}

	/** Back from Stripe: ask Stripe (not the browser) whether it is paid. */
	public static function stripe_verify( $o, $sid ) {
		if ( ! $o || 'paid' === $o['payment']['status'] || ! preg_match( '/^cs_[A-Za-z0-9_]+$/', (string) $sid ) ) {
			return $o;
		}
		if ( ( $o['payment']['session'] ?? '' ) !== $sid ) {
			return $o;
		}
		$s = self::stripe( 'GET', 'checkout/sessions/' . rawurlencode( $sid ) );
		if ( ! is_wp_error( $s ) && 'paid' === ( $s['payment_status'] ?? '' ) && (string) ( $s['client_reference_id'] ?? '' ) === (string) $o['id'] ) {
			$o = self::paid( $o['id'], (string) ( $s['payment_intent'] ?? $sid ) );
		}
		return $o;
	}

	/** Stripe webhook: checkout.session.completed / async_payment_succeeded. */
	public static function stripe_webhook( WP_REST_Request $req ) {
		$secret = (string) Velox_Shop::get( 'stripe_whsec' );
		$body   = $req->get_body();
		$sig    = (string) $req->get_header( 'stripe-signature' );
		if ( '' === $secret || ! self::stripe_sig_ok( $body, $sig, $secret ) ) {
			return new WP_REST_Response( array( 'ok' => false ), 400 );
		}
		$ev = json_decode( $body, true );
		$type = (string) ( $ev['type'] ?? '' );
		$obj  = $ev['data']['object'] ?? array();
		if ( in_array( $type, array( 'checkout.session.completed', 'checkout.session.async_payment_succeeded' ), true ) && 'paid' === ( $obj['payment_status'] ?? '' ) ) {
			$oid = (int) ( $obj['client_reference_id'] ?? 0 );
			$o   = Velox_Shop::order( $oid );
			if ( $o && ( $o['payment']['session'] ?? '' ) === ( $obj['id'] ?? '' ) ) {
				self::paid( $oid, (string) ( $obj['payment_intent'] ?? $obj['id'] ) );
			}
		}
		if ( 'checkout.session.async_payment_failed' === $type ) {
			$oid = (int) ( $obj['client_reference_id'] ?? 0 );
			$o   = Velox_Shop::order( $oid );
			if ( $o && 'pending' === $o['payment']['status'] ) {
				Velox_Shop::note( $o, __( 'The payment failed at Stripe.', 'velox' ) );
				Velox_Shop::save_order( $oid, $o );
			}
		}
		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	public static function stripe_sig_ok( $body, $header, $secret ) {
		$t = '';
		$v = array();
		foreach ( explode( ',', $header ) as $part ) {
			$kv = explode( '=', trim( $part ), 2 );
			if ( 2 === count( $kv ) ) {
				if ( 't' === $kv[0] ) {
					$t = $kv[1];
				} elseif ( 'v1' === $kv[0] ) {
					$v[] = $kv[1];
				}
			}
		}
		if ( '' === $t || ! $v || abs( time() - (int) $t ) > 600 ) {
			return false;
		}
		$expect = hash_hmac( 'sha256', $t . '.' . $body, $secret );
		foreach ( $v as $sig ) {
			if ( hash_equals( $expect, $sig ) ) {
				return true;
			}
		}
		return false;
	}

	/* ---- PayPal Orders API v2. */

	private static function paypal_base() {
		return Velox_Shop::get( 'paypal_sandbox' ) ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
	}

	private static function paypal_token() {
		$k = 'velox_shop_pp_' . md5( Velox_Shop::get( 'paypal_id' ) . Velox_Shop::get( 'paypal_sandbox' ) );
		$t = get_transient( $k );
		if ( $t ) {
			return $t;
		}
		$r = wp_remote_post( self::paypal_base() . '/v1/oauth2/token', array(
			'timeout' => 20,
			'headers' => array( 'Authorization' => 'Basic ' . base64_encode( Velox_Shop::get( 'paypal_id' ) . ':' . Velox_Shop::get( 'paypal_secret' ) ) ),
			'body'    => array( 'grant_type' => 'client_credentials' ),
		) );
		$j = is_wp_error( $r ) ? null : json_decode( wp_remote_retrieve_body( $r ), true );
		if ( empty( $j['access_token'] ) ) {
			return new WP_Error( 'paypal', __( 'PayPal did not accept the API credentials.', 'velox' ) );
		}
		set_transient( $k, $j['access_token'], max( 60, (int) ( $j['expires_in'] ?? 3000 ) - 120 ) );
		return $j['access_token'];
	}

	private static function paypal( $method, $path, $body = null ) {
		$tok = self::paypal_token();
		if ( is_wp_error( $tok ) ) {
			return $tok;
		}
		$args = array( 'method' => $method, 'timeout' => 25, 'headers' => array( 'Authorization' => 'Bearer ' . $tok, 'Content-Type' => 'application/json', 'PayPal-Request-Id' => wp_generate_uuid4() ) );
		if ( null !== $body ) {
			$args['body'] = wp_json_encode( $body );
		}
		$r = wp_remote_request( self::paypal_base() . $path, $args );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$j = json_decode( wp_remote_retrieve_body( $r ), true );
		if ( wp_remote_retrieve_response_code( $r ) >= 400 ) {
			return new WP_Error( 'paypal', is_array( $j ) && isset( $j['message'] ) ? $j['message'] : 'PayPal ' . wp_remote_retrieve_response_code( $r ) );
		}
		return is_array( $j ) ? $j : array();
	}

	private static function paypal_start( $o ) {
		$cur = strtoupper( (string) Velox_Shop::get( 'currency', 'EUR' ) );
		$res = self::paypal( 'POST', '/v2/checkout/orders', array(
			'intent'         => 'CAPTURE',
			'purchase_units' => array( array(
				'reference_id' => (string) $o['id'],
				'invoice_id'   => $o['number'],
				'custom_id'    => (string) $o['id'],
				/* translators: %s: order number */
				'description'  => mb_substr( sprintf( __( 'Order %s', 'velox' ), $o['number'] ), 0, 120 ),
				'amount'       => array( 'currency_code' => $cur, 'value' => number_format( $o['total'], 2, '.', '' ) ),
			) ),
			'payment_source' => array( 'paypal' => array( 'experience_context' => array(
				'brand_name'          => mb_substr( (string) Velox_Shop::get( 'store_name' ), 0, 120 ),
				'user_action'         => 'PAY_NOW',
				'shipping_preference' => 'NO_SHIPPING',
				'return_url'          => add_query_arg( 'vxpp', '1', Velox_Shop_Front::order_url( $o ) ),
				'cancel_url'          => add_query_arg( 'vx', 'paycancel', Velox_Shop_Front::order_url( $o ) ),
			) ) ),
		) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$url = '';
		foreach ( (array) ( $res['links'] ?? array() ) as $l ) {
			if ( in_array( $l['rel'] ?? '', array( 'payer-action', 'approve' ), true ) ) {
				$url = $l['href'];
			}
		}
		if ( ! $url ) {
			return new WP_Error( 'paypal', __( 'PayPal did not return a payment page.', 'velox' ) );
		}
		$ox = Velox_Shop::order( $o['id'] );
		$ox['payment']['session'] = (string) $res['id'];
		Velox_Shop::save_order( $o['id'], $ox );
		return $url;
	}

	/** Back from PayPal: capture the approved payment server-side. */
	public static function paypal_capture( $o, $token ) {
		if ( ! $o || 'paid' === $o['payment']['status'] || ( $o['payment']['session'] ?? '' ) !== $token ) {
			return $o;
		}
		$res = self::paypal( 'POST', '/v2/checkout/orders/' . rawurlencode( $token ) . '/capture', new stdClass() );
		if ( is_wp_error( $res ) ) {
			// Already captured (reload) — look the order up instead.
			$res = self::paypal( 'GET', '/v2/checkout/orders/' . rawurlencode( $token ) );
		}
		if ( ! is_wp_error( $res ) && 'COMPLETED' === ( $res['status'] ?? '' ) ) {
			$cap = $res['purchase_units'][0]['payments']['captures'][0] ?? array();
			$amt = (float) ( $cap['amount']['value'] ?? 0 );
			if ( abs( $amt - (float) $o['total'] ) < 0.01 ) {
				$o = self::paid( $o['id'], (string) ( $cap['id'] ?? $token ) );
			}
		}
		return $o;
	}

	/** Paid by card or PayPal: mark it, and send the confirmation that waited for the money. */
	private static function paid( $oid, $ref ) {
		$o = Velox_Shop::order( $oid );
		$first = $o && empty( $o['mailed'] );
		if ( $first ) {
			$o['mailed'] = 1;
			Velox_Shop::save_order( $oid, $o );
		}
		// One email, not two: the first confirmation already says "paid".
		$o = Velox_Shop::mark_paid( $oid, $ref, false, ! $first );
		if ( $first ) {
			Velox_Shop::mail( 'placed', $o );
			Velox_Shop::mail( 'admin', $o );
		}
		return $o;
	}

	/** Payment methods the shop can choose for Stripe Checkout. */
	public static function stripe_types() {
		return array(
			'card'       => __( 'Cards (with Apple Pay and Google Pay)', 'velox' ),
			'sepa_debit' => __( 'SEPA Direct Debit', 'velox' ),
			'klarna'     => 'Klarna',
			'paypal'     => __( 'PayPal through Stripe', 'velox' ),
			'eps'        => 'EPS',
			'ideal'      => 'iDEAL',
			'bancontact' => 'Bancontact',
			'p24'        => 'Przelewy24',
			'link'       => 'Link',
		);
	}

	/**
	 * The Stripe balance, the payout schedule and the latest payouts to the
	 * shop's bank. Cached five minutes; null while Stripe is not set up.
	 */
	public static function stripe_money( $fresh = false ) {
		if ( ! self::ready( 'stripe' ) ) {
			return null;
		}
		$k = 'velox_shop_stripe_money';
		$c = $fresh ? false : get_transient( $k );
		if ( is_array( $c ) ) {
			return $c;
		}
		$b = self::stripe( 'GET', 'balance' );
		if ( is_wp_error( $b ) ) {
			return array( 'error' => $b->get_error_message() );
		}
		$sum = function ( $list ) {
			$o = array();
			foreach ( (array) $list as $x ) {
				$o[] = array( 'amount' => ( (int) ( $x['amount'] ?? 0 ) ) / 100, 'currency' => strtoupper( (string) ( $x['currency'] ?? '' ) ) );
			}
			return $o;
		};
		$p     = self::stripe( 'GET', 'payouts?limit=8' );
		$a     = self::stripe( 'GET', 'account' );
		$sched = is_wp_error( $a ) ? null : ( $a['settings']['payouts']['schedule'] ?? null );
		$bank  = '';
		if ( ! is_wp_error( $a ) && ! empty( $a['external_accounts']['data'][0] ) ) {
			$e    = $a['external_accounts']['data'][0];
			$bank = trim( ( $e['bank_name'] ?? '' ) . ' ···· ' . ( $e['last4'] ?? '' ) );
		}
		$list = array();
		foreach ( is_wp_error( $p ) ? array() : (array) ( $p['data'] ?? array() ) as $x ) {
			$list[] = array( 'amount' => ( (int) $x['amount'] ) / 100, 'currency' => strtoupper( (string) $x['currency'] ), 'arrival' => (int) ( $x['arrival_date'] ?? 0 ), 'status' => (string) ( $x['status'] ?? '' ), 'auto' => ! empty( $x['automatic'] ) );
		}
		$out = array(
			'available' => $sum( $b['available'] ?? array() ),
			'pending'   => $sum( $b['pending'] ?? array() ),
			'payouts'   => $list,
			'schedule'  => is_array( $sched ) ? array( 'interval' => (string) ( $sched['interval'] ?? '' ), 'delay' => (int) ( $sched['delay_days'] ?? 0 ), 'weekly' => (string) ( $sched['weekly_anchor'] ?? '' ), 'monthly' => (int) ( $sched['monthly_anchor'] ?? 0 ) ) : null,
			'bank'      => $bank,
			'test'      => false !== strpos( (string) Velox_Shop::get( 'stripe_secret' ), '_test_' ),
			'at'        => time(),
		);
		set_transient( $k, $out, 5 * MINUTE_IN_SECONDS );
		return $out;
	}

	/** Pay the whole available Stripe balance out to the bank (only with a manual schedule). */
	public static function stripe_payout() {
		$m = self::stripe_money( true );
		if ( ! is_array( $m ) || ! empty( $m['error'] ) ) {
			return new WP_Error( 'x', is_array( $m ) ? $m['error'] : __( 'Stripe is not set up.', 'velox' ) );
		}
		if ( 'manual' !== ( $m['schedule']['interval'] ?? '' ) ) {
			return new WP_Error( 'x', __( 'Stripe pays out automatically on your schedule — there is nothing to start by hand.', 'velox' ) );
		}
		$done = array();
		foreach ( $m['available'] as $a ) {
			if ( $a['amount'] <= 0 ) {
				continue;
			}
			$r = self::stripe( 'POST', 'payouts', array( 'amount' => (int) round( $a['amount'] * 100 ), 'currency' => strtolower( $a['currency'] ) ) );
			if ( is_wp_error( $r ) ) {
				return $r;
			}
			$done[] = Velox_Shop::money( $a['amount'] ) . ( strtoupper( (string) Velox_Shop::get( 'currency', 'EUR' ) ) !== $a['currency'] ? ' ' . $a['currency'] : '' );
		}
		delete_transient( 'velox_shop_stripe_money' );
		/* translators: %s: amounts */
		return $done ? sprintf( __( 'Payout started: %s. It usually reaches your bank in 1–3 working days.', 'velox' ), implode( ', ', $done ) ) : __( 'There is no available balance to pay out yet.', 'velox' );
	}

	/** The PayPal balance, when the app may read it (PayPal calls it "Balances"). */
	public static function paypal_money( $fresh = false ) {
		if ( ! self::ready( 'paypal' ) ) {
			return null;
		}
		$k = 'velox_shop_paypal_money';
		$c = $fresh ? false : get_transient( $k );
		if ( is_array( $c ) ) {
			return $c;
		}
		$r   = self::paypal( 'GET', '/v1/reporting/balances?currency_code=' . rawurlencode( strtoupper( (string) Velox_Shop::get( 'currency', 'EUR' ) ) ) );
		$out = array( 'sandbox' => (bool) Velox_Shop::get( 'paypal_sandbox' ), 'at' => time() );
		if ( is_wp_error( $r ) ) {
			$out['error'] = $r->get_error_message();
		} else {
			$out['balances'] = array();
			foreach ( (array) ( $r['balances'] ?? array() ) as $b ) {
				$out['balances'][] = array( 'currency' => (string) ( $b['currency'] ?? '' ), 'total' => (float) ( $b['total_balance']['value'] ?? 0 ), 'available' => (float) ( $b['available_balance']['value'] ?? ( $b['total_balance']['value'] ?? 0 ) ), 'held' => (float) ( $b['withheld_balance']['value'] ?? 0 ) );
			}
		}
		set_transient( $k, $out, 5 * MINUTE_IN_SECONDS );
		return $out;
	}

	/* ---- refunds through the provider */

	public static function refund( $o, $amount ) {
		$ref = (string) $o['payment']['ref'];
		if ( 'stripe' === $o['payment']['method'] && 0 === strpos( $ref, 'pi_' ) ) {
			$r = self::stripe( 'POST', 'refunds', array( 'payment_intent' => $ref, 'amount' => self::cents( $amount ) ) );
			return is_wp_error( $r ) ? $r : (string) $r['id'];
		}
		if ( 'paypal' === $o['payment']['method'] && '' !== $ref ) {
			$r = self::paypal( 'POST', '/v2/payments/captures/' . rawurlencode( $ref ) . '/refund', array( 'amount' => array( 'value' => number_format( $amount, 2, '.', '' ), 'currency_code' => strtoupper( (string) Velox_Shop::get( 'currency', 'EUR' ) ) ) ) );
			return is_wp_error( $r ) ? $r : (string) ( $r['id'] ?? '' );
		}
		return new WP_Error( 'manual', __( 'This payment has to be refunded by hand (bank transfer).', 'velox' ) );
	}

	/** Test the provider credentials from the settings screen. */
	public static function test( $m ) {
		if ( 'stripe' === $m ) {
			$r = self::stripe( 'GET', 'balance' );
			return is_wp_error( $r ) ? $r : ( false !== strpos( (string) Velox_Shop::get( 'stripe_secret' ), '_test_' ) ? __( 'Connected to Stripe (test mode).', 'velox' ) : __( 'Connected to Stripe (live).', 'velox' ) );
		}
		if ( 'paypal' === $m ) {
			delete_transient( 'velox_shop_pp_' . md5( Velox_Shop::get( 'paypal_id' ) . Velox_Shop::get( 'paypal_sandbox' ) ) );
			$t = self::paypal_token();
			return is_wp_error( $t ) ? $t : ( Velox_Shop::get( 'paypal_sandbox' ) ? __( 'Connected to PayPal (sandbox).', 'velox' ) : __( 'Connected to PayPal (live).', 'velox' ) );
		}
		return new WP_Error( 'x', '' );
	}

	/* ---------------------------------------------------------------- REST */

	public static function routes() {
		register_rest_route( 'velox/v1', '/shop/cart', array(
			array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'rest_cart' ), 'permission_callback' => '__return_true' ),
			array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'rest_cart' ), 'permission_callback' => '__return_true' ),
		) );
		register_rest_route( 'velox/v1', '/shop/quote', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'rest_quote' ), 'permission_callback' => '__return_true' ) );
		register_rest_route( 'velox/v1', '/shop/stripe', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'stripe_webhook' ), 'permission_callback' => '__return_true' ) );
	}

	/**
	 * Cart for the mini cart: read, add, change, remove, coupon. Changing the
	 * cart cross-site is harmless (it holds no money), so it needs no nonce —
	 * which also keeps it working on cached pages.
	 */
	public static function rest_cart( WP_REST_Request $req ) {
		$msg = '';
		if ( 'POST' === $req->get_method() ) {
			$op  = sanitize_key( (string) $req->get_param( 'op' ) );
			$pid = (int) $req->get_param( 'product' );
			$vid = sanitize_key( (string) $req->get_param( 'variant' ) );
			$qty = (int) $req->get_param( 'qty' );
			if ( 'add' === $op ) {
				$msg = Velox_Shop::cart_set( $pid, $vid, max( 1, $qty ), true );
			} elseif ( 'set' === $op ) {
				$msg = Velox_Shop::cart_set( $pid, $vid, $qty );
			} elseif ( 'coupon' === $op ) {
				$cart = Velox_Shop::get_cart();
				$cart['_c'] = strtoupper( sanitize_text_field( (string) $req->get_param( 'code' ) ) );
				Velox_Shop::save_cart( $cart );
			}
		}
		return new WP_REST_Response( Velox_Shop_Front::cart_state( $msg ), 200 );
	}

	/** Live totals while the checkout form is filled in. */
	public static function rest_quote( WP_REST_Request $req ) {
		$lines = Velox_Shop::lines();
		$t     = Velox_Shop::totals( $lines, strtoupper( sanitize_key( (string) $req->get_param( 'country' ) ) ), sanitize_key( (string) $req->get_param( 'ship' ) ), (string) $req->get_param( 'coupon' ), sanitize_key( (string) $req->get_param( 'pay' ) ) );
		return new WP_REST_Response( array(
			'html'     => Velox_Shop_Front::totals_html( $lines, $t ),
			'ship'     => Velox_Shop_Front::ship_options_html( $t ),
			'pay'      => self::available( $t ),
			'total'    => Velox_Shop::money( $t['total'] ),
			'coupon'   => $t['coupon'],
			'couponMsg'=> $t['coupon_msg'],
		), 200 );
	}
}
