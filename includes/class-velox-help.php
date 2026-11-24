<?php
/**
 * Velox — first-run tours and built-in help for the shop and the builder.
 *
 * Switching the shop or the builder on marks a tour for the person who did
 * it. Their next admin page takes them to that dashboard once, and the tour
 * starts there. Skipping it lands on the place where it can be started again
 * (shop: Settings → Help & tour, builder: Settings → Help & tour). The help
 * (questions and answers) is searchable, in the shop settings and on the
 * builder's Help page.
 *
 * @package Velox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Velox_Help {

	const META = 'velox_tours';

	public static function init() {
		add_action( 'update_option_velox_settings', array( __CLASS__, 'switched' ), 10, 2 );
		add_action( 'admin_init', array( __CLASS__, 'go_to_tour' ), 1 );
		add_action( 'wp_ajax_velox_tour', array( __CLASS__, 'ajax' ) );
		add_action( 'wp_ajax_velox_guide', array( __CLASS__, 'ajax_guide' ) );
	}

	/**
	 * The complete guide (chapters → sections → blocks), from
	 * includes/guide/{shop,builder}.php. Loaded only when Help is opened.
	 */
	public static function guide( $which ) {
		$which = 'shop' === $which ? 'shop' : 'builder';
		$file  = VELOX_PATH . 'includes/guide/' . $which . '.php';
		if ( ! is_readable( $file ) ) {
			return array();
		}
		$g = include $file;
		return is_array( $g ) ? $g : array();
	}

	public static function ajax_guide() {
		check_ajax_referer( 'velox_tour', 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array(), 403 );
		}
		$which = isset( $_POST['which'] ) ? sanitize_key( wp_unslash( $_POST['which'] ) ) : '';
		wp_send_json_success( self::guide( $which ) );
	}

	/** Shop or builder switched on just now: a tour waits for this person. */
	public static function switched( $old, $new ) {
		$uid = get_current_user_id();
		if ( ! $uid || ! is_array( $new ) ) {
			$new = array();
		}
		$old   = is_array( $old ) ? $old : array();
		$tours = self::state( $uid );
		$set   = false;
		foreach ( array( 'shop' => 'util_shop', 'builder' => 'module_builder' ) as $tour => $key ) {
			if ( empty( $old[ $key ] ) && ! empty( $new[ $key ] ) && empty( $tours[ $tour ] ) ) {
				$tours[ $tour ] = 'pending';
				$set            = true;
			}
		}
		if ( $set && $uid ) {
			update_user_meta( $uid, self::META, $tours );
		}
	}

	public static function state( $uid = 0 ) {
		$t = get_user_meta( $uid ? $uid : get_current_user_id(), self::META, true );
		return is_array( $t ) ? $t : array();
	}

	/** The next admin page after switching on opens the dashboard with the tour. */
	public static function go_to_tour() {
		if ( wp_doing_ajax() || ! current_user_can( 'manage_options' ) || 'GET' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			return;
		}
		$tours = self::state();
		foreach ( array( 'shop', 'builder' ) as $tour ) {
			if ( 'pending' !== ( $tours[ $tour ] ?? '' ) ) {
				continue;
			}
			if ( 'shop' === $tour && ! Velox_Settings::get( 'util_shop' ) ) {
				continue;
			}
			if ( 'builder' === $tour && ! Velox_Settings::get( 'module_builder' ) ) {
				continue;
			}
			$tours[ $tour ] = 'started';
			update_user_meta( get_current_user_id(), self::META, $tours );
			wp_safe_redirect( self::start_url( $tour ) );
			exit;
		}
	}

	public static function start_url( $tour ) {
		return 'shop' === $tour ? admin_url( 'admin.php?page=velox-shop&vx_tour=shop' ) : admin_url( 'admin.php?page=velox-builder&vx_tour=builder' );
	}

	/** Where "Skip" leads: the place the tour can be started again. */
	public static function skip_url( $tour ) {
		return 'shop' === $tour ? admin_url( 'admin.php?page=velox-shop#/settings/help?hint=tour' ) : admin_url( 'admin.php?page=velox-builder-settings&vx_hint=tour#vx-help' );
	}

	public static function ajax() {
		check_ajax_referer( 'velox_tour', 'nonce' );
		$tour  = isset( $_POST['tour'] ) ? sanitize_key( wp_unslash( $_POST['tour'] ) ) : '';
		$state = isset( $_POST['state'] ) ? sanitize_key( wp_unslash( $_POST['state'] ) ) : '';
		if ( ! in_array( $tour, array( 'shop', 'builder' ), true ) || ! in_array( $state, array( 'done', 'skipped' ), true ) ) {
			wp_send_json_error( array(), 400 );
		}
		$t          = self::state();
		$t[ $tour ] = $state;
		update_user_meta( get_current_user_id(), self::META, $t );
		wp_send_json_success();
	}

	/** Load the tour (and help) on the shop and builder screens. */
	public static function enqueue( $which ) {
		wp_enqueue_style( 'velox-tour', VELOX_ASSETS . 'css/velox-tour.css', array(), VELOX_VERSION );
		wp_enqueue_script( 'velox-tour', VELOX_ASSETS . 'js/velox-tour.js', array(), VELOX_VERSION, true );
		wp_localize_script( 'velox-tour', 'VXTOUR', array(
			'ajax'  => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'velox_tour' ),
			'tours' => array( $which => self::steps( $which ) ),
			'skip'  => array( $which => self::skip_url( $which ) ),
			'docs'  => array( $which => self::docs( $which ) ),
			't'     => array(
				'next'  => __( 'Next', 'velox' ),
				'back'  => __( 'Back', 'velox' ),
				'skip'  => __( 'Skip the tour', 'velox' ),
				'done'  => __( 'Done', 'velox' ),
				'of'    => __( '%1$d of %2$d', 'velox' ),
				'hint'  => __( 'You can start the tour again here at any time.', 'velox' ),
				'find'  => __( 'Search the help', 'velox' ),
				'none'  => __( 'Nothing found. Try another word.', 'velox' ),
				'close' => __( 'Close', 'velox' ),
				'guide' => __( 'Guide', 'velox' ),
				'faq'   => __( 'Questions', 'velox' ),
				'toc'   => __( 'Contents', 'velox' ),
				'prev'  => __( 'Previous', 'velox' ),
				'nextc' => __( 'Next chapter', 'velox' ),
				'where' => __( 'Where', 'velox' ),
				'tip'   => __( 'Tip', 'velox' ),
				'note'  => __( 'Note', 'velox' ),
				'load'  => __( 'Loading the guide…', 'velox' ),
				'findg' => __( 'Search the guide and the questions', 'velox' ),
				'hits'  => __( 'Results', 'velox' ),
				'inq'   => __( 'In the questions', 'velox' ),
			),
		) );
	}

	/**
	 * Tour steps: sel = what to point at (none: a card in the middle), hash =
	 * a shop screen to open first.
	 */
	public static function steps( $which ) {
		if ( 'shop' === $which ) {
			return array(
				array( 'title' => __( 'Your shop is on', 'velox' ), 'text' => __( 'This short tour shows where everything is. It takes about a minute; you can skip it and start it again later.', 'velox' ) ),
				array( 'sel' => '.vsa-nav', 'title' => __( 'Everything in one bar', 'velox' ), 'text' => __( 'Overview, revenue, payouts, orders, products, coupons, customers, reviews and settings: the whole shop is here, without the WordPress lists.', 'velox' ), 'hash' => '#/' ),
				array( 'sel' => '.vsa-pages', 'title' => __( 'The pages your shop needs', 'velox' ), 'text' => __( 'Shop, cart, checkout and account. Missing ones are created with one click; the legal pages are linked at checkout.', 'velox' ), 'hash' => '#/' ),
				array( 'sel' => '.vsa-nav [data-nav="products"]', 'title' => __( 'Add your products', 'velox' ), 'text' => __( 'Photos, price, options like size or colour, stock and shipping weight. A product is live as soon as you publish it.', 'velox' ) ),
				array( 'sel' => '.vsa-nav [data-nav="orders"]', 'title' => __( 'Orders', 'velox' ), 'text' => __( 'New orders show up with a number here and by email. Mark them as shipped with a tracking link; invoices are made for you.', 'velox' ) ),
				array( 'sel' => '.vsa-setnav a[href="#/settings/payments"]', 'hash' => '#/settings/store', 'title' => __( 'How customers pay', 'velox' ), 'text' => __( 'Stripe (cards, Apple Pay, Google Pay, Klarna), PayPal, bank transfer or invoice. Switch on what you want to offer.', 'velox' ) ),
				array( 'sel' => '.vsa-setnav a[href="#/settings/shipping"]', 'title' => __( 'Shipping', 'velox' ), 'text' => __( 'Where you deliver, what it costs and from which amount it is free.', 'velox' ) ),
				array( 'sel' => '.vsa-tpls', 'hash' => '#/settings/design', 'title' => __( 'Your shop’s design', 'velox' ), 'text' => __( 'Pick a template, try it on the real shop before saving, then fine-tune colours, fonts, cards and pages.', 'velox' ) ),
				array( 'sel' => '.vsa-setnav a[href="#/settings/texts"]', 'title' => __( 'Your own words', 'velox' ), 'text' => __( 'Every text customers see can be changed here: buttons, notes, emails.', 'velox' ) ),
				array( 'sel' => '.vsa-setnav a[href="#/settings/help"]', 'title' => __( 'Help and this tour', 'velox' ), 'text' => __( 'Answers to common questions, and the button to take this tour again.', 'velox' ) ),
			);
		}
		$sub = function ( $slug ) {
			return '#adminmenu a[href$="page=' . $slug . '"]';
		};
		return array(
			array( 'title' => __( 'Velox Builder is on', 'velox' ), 'text' => __( 'A visual editor for your pages, templates and reusable blocks. This tour shows the main places; it takes about a minute.', 'velox' ) ),
			array( 'sel' => '.vba-head-actions .vba-btn-primary', 'title' => __( 'Start a page', 'velox' ), 'text' => __( 'Create a page and open it in the editor. Pages you already have can be opened from the list below.', 'velox' ) ),
			array( 'sel' => $sub( 'velox-builder-templates' ), 'title' => __( 'Templates', 'velox' ), 'text' => __( 'Header, footer and layout for many pages at once. Each page fills the template’s Inner Content.', 'velox' ) ),
			array( 'sel' => $sub( 'velox-builder-reusables' ), 'title' => __( 'Reusables', 'velox' ), 'text' => __( 'Blocks you build once and place anywhere. Change them in one place, they change everywhere.', 'velox' ) ),
			array( 'sel' => $sub( 'velox-builder-classes' ), 'title' => __( 'Your design system', 'velox' ), 'text' => __( 'Classes, global styles and fonts: style once, use on every page.', 'velox' ) ),
			array( 'sel' => $sub( 'velox-builder-agents' ), 'title' => __( 'Build with Claude', 'velox' ), 'text' => __( 'Connect Claude with your own Claude account and let it build and change drafts for you.', 'velox' ) ),
			array( 'title' => __( 'In the editor', 'velox' ), 'text' => __( 'Add elements with + Add, click to select, double-click to edit text. Accordions, tabs and sliders work right on the canvas. Ctrl+K opens every command.', 'velox' ) ),
			array( 'sel' => $sub( 'velox-builder-help' ), 'title' => __( 'Help and this tour', 'velox' ), 'text' => __( 'Answers to common questions, and the button to take this tour again.', 'velox' ) ),
		);
	}

	/** Questions and answers, grouped. */
	public static function docs( $which ) {
		if ( 'shop' === $which ) {
			return array(
				array( __( 'Getting started', 'velox' ), array(
					array( __( 'What do I need before the first sale?', 'velox' ), __( 'The shop pages (Overview → Pages), at least one payment method (Settings → Payments), shipping costs (Settings → Shipping), your business details (Settings → Store) and your legal pages: terms, withdrawal policy, privacy (Settings → Pages & legal).', 'velox' ) ),
					array( __( 'How do I see the shop like a customer?', 'velox' ), __( '“View shop” at the top right opens it. Signed in as an administrator you see everything customers see; test orders work with Stripe’s test mode.', 'velox' ) ),
				) ),
				array( __( 'Products', 'velox' ), array(
					array( __( 'How do I add a product with sizes or colours?', 'velox' ), __( 'Products → New product. Under Options add for example “Size” with S, M, L. Every combination becomes a variant with its own price, stock and photo if you want.', 'velox' ) ),
					array( __( 'What happens when something is sold out?', 'velox' ), __( 'It is marked as sold. Under Settings → Stock & products you choose whether sold products stay visible, move to the end or disappear. Customers can ask to be told when it is back (Storefront).', 'velox' ) ),
				) ),
				array( __( 'Orders and shipping', 'velox' ), array(
					array( __( 'How do I ship an order?', 'velox' ), __( 'Open the order and click “Mark as shipped”. Pick the carrier (or any other with your own tracking link) and enter the tracking number. The customer gets an email with the link.', 'velox' ) ),
					array( __( 'Where are the invoices?', 'velox' ), __( 'Every paid order gets an invoice number and a printable invoice with all the details German law asks for. Open it in the order; your browser saves it as a PDF.', 'velox' ) ),
					array( __( 'A customer wants to withdraw. What now?', 'velox' ), __( 'Withdrawals arrive by email and are flagged on the order. Refund it in the order: for Stripe and PayPal payments Velox sends the money back through the provider.', 'velox' ) ),
				) ),
				array( __( 'Payments', 'velox' ), array(
					array( __( 'Which payment methods are there?', 'velox' ), __( 'Stripe (cards, Apple Pay, Google Pay, SEPA, Klarna), PayPal, bank transfer and invoice. Card and PayPal payments happen on the provider’s page: no card data touches your site.', 'velox' ) ),
					array( __( 'When do I get the money?', 'velox' ), __( 'Stripe and PayPal pay out on their schedule (see Payouts). Bank transfers and invoices you mark as paid in the order.', 'velox' ) ),
				) ),
				array( __( 'Design and texts', 'velox' ), array(
					array( __( 'How do I change how the shop looks?', 'velox' ), __( 'Settings → Design: pick a template, try it on the real shop before saving, then change colours, fonts, cards, product pages and the account. “Back to the pure template” undoes your changes.', 'velox' ) ),
					array( __( 'Can I change the words on buttons and in emails?', 'velox' ), __( 'Yes, every text customers see: Settings → Texts. Search for it, type your own wording, save.', 'velox' ) ),
					array( __( 'Can I place the shop parts myself?', 'velox' ), __( 'Yes. In Velox Builder the product grid, cart, checkout and account are elements you can place and style on any page. Settings → Design → Build it yourself opens the shop pages there.', 'velox' ) ),
				) ),
				array( __( 'Law in Germany', 'velox' ), array(
					array( __( 'Is the checkout legally sound?', 'velox' ), __( 'It shows prices with VAT, shipping costs, the order button with “zahlungspflichtig bestellen” wording, links to terms, withdrawal policy and privacy, and the online withdrawal button. Your legal texts themselves must come from you or a lawyer.', 'velox' ) ),
				) ),
			);
		}
		return array(
			array( __( 'The editor', 'velox' ), array(
				array( __( 'How do I add and arrange elements?', 'velox' ), __( 'Click + Add and pick an element; it goes into the selected container or after the selected element. Drag elements on the canvas or in the Structure panel to move them.', 'velox' ) ),
				array( __( 'How do I edit text?', 'velox' ), __( 'Double-click it. The text tools appear: bold, italic, links, alignment, “Style selection” for a few words in their own colour or size, and Insert Data for dynamic values.', 'velox' ) ),
				array( __( 'How do accordions, tabs and sliders work?', 'velox' ), __( 'Like on the page: click a header, a tab or the arrows. Double-click a title to rename it. The selected one has “+ Add” for the next item, and every item can hold any elements.', 'velox' ) ),
			) ),
			array( __( 'Styling', 'velox' ), array(
				array( __( 'Class or element styles?', 'velox' ), __( 'Styles on a class apply to every element with that class, on every page. Use them for anything repeated (cards, buttons). Element styles are for one-offs.', 'velox' ) ),
				array( __( 'How do I style for phones?', 'velox' ), __( 'Switch the screen size in the top bar. A value set on a smaller size only applies there and below.', 'velox' ) ),
				array( __( 'Where are colours and fonts for the whole site?', 'velox' ), __( 'Global styles (colours as design tokens, headings, links) and Fonts. Use tokens in any colour field, so a change there changes the whole site.', 'velox' ) ),
			) ),
			array( __( 'Pages and publishing', 'velox' ), array(
				array( __( 'Is my work live right away?', 'velox' ), __( 'No. Saving keeps a draft; visitors see the page only after Publish. Every save is a version under History, one click to go back.', 'velox' ) ),
				array( __( 'How do templates work?', 'velox' ), __( 'A template is the frame (header, footer, layout). Its Inner Content element is where each page’s own content goes. Conditions decide which pages use which template.', 'velox' ) ),
				array( __( 'What is a reusable?', 'velox' ), __( 'A block you build once and place on many pages. Change it once, it changes everywhere.', 'velox' ) ),
			) ),
			array( __( 'Claude and AI', 'velox' ), array(
				array( __( 'Can Claude build pages for me?', 'velox' ), __( 'Yes. Under AI agents add the site to Claude as a connector, with your own Claude account. Then ask in Claude, or use “Work on this page in Claude” in the editor. Claude only writes drafts; you check and publish.', 'velox' ) ),
			) ),
			array( __( 'Help', 'velox' ), array(
				array( __( 'Keyboard shortcuts?', 'velox' ), __( 'Ctrl+K opens every command, Ctrl+S saves, Ctrl+Z undoes. The full list (and your own keys) is under Keyboard shortcuts.', 'velox' ) ),
				array( __( 'Something looks different on the page than in the editor?', 'velox' ), __( 'Check the template the page uses (View as), styles on a smaller screen size, and your theme’s CSS. Velox shows the template’s CSS in the editor too, so they should match.', 'velox' ) ),
			) ),
		);
	}
}

Velox_Help::init();
