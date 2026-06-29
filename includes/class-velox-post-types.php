<?php
/**
 * Velox Post Types — register custom post types and taxonomies, ACF-style.
 *
 * Post types live in the `velox_post_types` option (array keyed by slug) and
 * taxonomies in `velox_taxonomies`. Active ones are registered on `init` so
 * they show up in the admin sidebar next to Posts and Pages, support
 * Gutenberg/REST, and can carry Velox field groups.
 *
 * @package Velox
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Velox_Post_Types {

	const PT_OPTION  = 'velox_post_types';
	const TAX_OPTION = 'velox_taxonomies';

	/** Things a post type can support (register_post_type `supports`). */
	public static function supports_options() {
		return array(
			'title'           => 'Title',
			'editor'          => 'Editor (content)',
			'thumbnail'       => 'Featured image',
			'excerpt'         => 'Excerpt',
			'author'          => 'Author',
			'comments'        => 'Comments',
			'revisions'       => 'Revisions',
			'page-attributes' => 'Page attributes (order/parent)',
			'custom-fields'   => 'Custom fields',
		);
	}

	public static function init() {
		// Priority 0 so the types exist before menus, field groups and the REST API.
		add_action( 'init', array( __CLASS__, 'register_all' ), 0 );
	}

	/* ------------------------------------------------------------------ *
	 * Storage / CRUD
	 * ------------------------------------------------------------------ */

	public static function all_post_types() {
		$v = get_option( self::PT_OPTION, array() );
		return is_array( $v ) ? $v : array();
	}
	public static function all_taxonomies() {
		$v = get_option( self::TAX_OPTION, array() );
		return is_array( $v ) ? $v : array();
	}
	public static function get_post_type( $slug ) {
		$all = self::all_post_types();
		return isset( $all[ $slug ] ) ? $all[ $slug ] : null;
	}
	public static function get_taxonomy( $slug ) {
		$all = self::all_taxonomies();
		return isset( $all[ $slug ] ) ? $all[ $slug ] : null;
	}

	public static function blank_post_type() {
		return array(
			'slug'         => '',
			'singular'     => '',
			'plural'       => '',
			'active'       => true,
			'public'       => true,
			'show_in_menu' => true,
			'show_in_rest' => true,
			'has_archive'  => true,
			'hierarchical' => false,
			'menu_icon'    => 'dashicons-admin-post',
			'menu_position'=> 25,
			'supports'     => array( 'title', 'editor', 'thumbnail', 'custom-fields' ),
			'taxonomies'   => array(),
		);
	}

	public static function blank_taxonomy() {
		return array(
			'slug'             => '',
			'singular'         => '',
			'plural'           => '',
			'active'           => true,
			'public'           => true,
			'hierarchical'     => true,  // true = category-like, false = tag-like
			'show_in_rest'     => true,
			'show_admin_column'=> true,
			'object_types'     => array( 'post' ),
		);
	}

	/** Save (insert or update) a post type, keyed by its sanitized slug. */
	public static function save_post_type( $pt ) {
		$pt  = self::sanitize_post_type( $pt );
		if ( '' === $pt['slug'] ) { return array( 'ok' => false, 'message' => 'A slug is required.' ); }
		if ( self::is_reserved( $pt['slug'] ) ) { return array( 'ok' => false, 'message' => 'That slug is reserved by WordPress. Pick another.' ); }
		$all = self::all_post_types();
		// Allow rename: if an old slug was passed and differs, drop the old key.
		if ( ! empty( $pt['_old_slug'] ) && $pt['_old_slug'] !== $pt['slug'] ) {
			unset( $all[ $pt['_old_slug'] ] );
		}
		unset( $pt['_old_slug'] );
		$all[ $pt['slug'] ] = $pt;
		update_option( self::PT_OPTION, $all );
		return array( 'ok' => true, 'slug' => $pt['slug'], 'post_type' => $pt );
	}

	public static function save_taxonomy( $tx ) {
		$tx = self::sanitize_taxonomy( $tx );
		if ( '' === $tx['slug'] ) { return array( 'ok' => false, 'message' => 'A slug is required.' ); }
		if ( self::is_reserved( $tx['slug'] ) ) { return array( 'ok' => false, 'message' => 'That slug is reserved by WordPress. Pick another.' ); }
		$all = self::all_taxonomies();
		if ( ! empty( $tx['_old_slug'] ) && $tx['_old_slug'] !== $tx['slug'] ) {
			unset( $all[ $tx['_old_slug'] ] );
		}
		unset( $tx['_old_slug'] );
		$all[ $tx['slug'] ] = $tx;
		update_option( self::TAX_OPTION, $all );
		return array( 'ok' => true, 'slug' => $tx['slug'], 'taxonomy' => $tx );
	}

	public static function delete_post_type( $slug ) {
		$all = self::all_post_types();
		if ( isset( $all[ $slug ] ) ) { unset( $all[ $slug ] ); update_option( self::PT_OPTION, $all ); return true; }
		return false;
	}
	public static function delete_taxonomy( $slug ) {
		$all = self::all_taxonomies();
		if ( isset( $all[ $slug ] ) ) { unset( $all[ $slug ] ); update_option( self::TAX_OPTION, $all ); return true; }
		return false;
	}

	/* ------------------------------------------------------------------ *
	 * Sanitization
	 * ------------------------------------------------------------------ */

	/** Post-type slugs: lowercase, max 20 chars, [a-z0-9_-]. */
	public static function clean_slug( $s, $max = 20 ) {
		$s = strtolower( (string) $s );
		$s = preg_replace( '/[^a-z0-9_\-]/', '', $s );
		return substr( $s, 0, $max );
	}

	private static function is_reserved( $slug ) {
		$reserved = array(
			'post', 'page', 'attachment', 'revision', 'nav_menu_item', 'custom_css',
			'customize_changeset', 'oembed_cache', 'user_request', 'wp_block', 'wp_template',
			'wp_template_part', 'wp_global_styles', 'wp_navigation', 'action', 'order', 'theme',
			'category', 'post_tag', 'nav_menu', 'link_category', 'post_format', 'type', 'fields',
		);
		return in_array( $slug, $reserved, true );
	}

	private static function sanitize_post_type( $pt ) {
		$pt = is_array( $pt ) ? $pt : array();
		$supports_keys = array_keys( self::supports_options() );
		$icon = isset( $pt['menu_icon'] ) ? sanitize_text_field( $pt['menu_icon'] ) : 'dashicons-admin-post';
		// Only allow a dashicons-* token or a URL.
		if ( '' !== $icon && 0 !== strpos( $icon, 'dashicons-' ) && ! preg_match( '#^https?://#', $icon ) ) {
			$icon = 'dashicons-admin-post';
		}
		$out = array(
			'slug'          => self::clean_slug( $pt['slug'] ?? '', 20 ),
			'singular'      => sanitize_text_field( $pt['singular'] ?? '' ),
			'plural'        => sanitize_text_field( $pt['plural'] ?? '' ),
			'active'        => ! empty( $pt['active'] ),
			'public'        => ! empty( $pt['public'] ),
			'show_in_menu'  => ! empty( $pt['show_in_menu'] ),
			'show_in_rest'  => ! empty( $pt['show_in_rest'] ),
			'has_archive'   => ! empty( $pt['has_archive'] ),
			'hierarchical'  => ! empty( $pt['hierarchical'] ),
			'menu_icon'     => $icon,
			'menu_position' => isset( $pt['menu_position'] ) && '' !== $pt['menu_position'] ? (int) $pt['menu_position'] : 25,
			'supports'      => array(),
			'taxonomies'    => array(),
		);
		if ( '' === $out['singular'] ) { $out['singular'] = ucfirst( str_replace( array( '_', '-' ), ' ', $out['slug'] ) ); }
		if ( '' === $out['plural'] )   { $out['plural']   = $out['singular'] . 's'; }
		$sup = isset( $pt['supports'] ) && is_array( $pt['supports'] ) ? $pt['supports'] : array();
		foreach ( $sup as $s ) { if ( in_array( $s, $supports_keys, true ) ) { $out['supports'][] = $s; } }
		if ( ! $out['supports'] ) { $out['supports'] = array( 'title', 'editor' ); }
		$tax = isset( $pt['taxonomies'] ) && is_array( $pt['taxonomies'] ) ? $pt['taxonomies'] : array();
		foreach ( $tax as $t ) { $t = self::clean_slug( $t, 32 ); if ( $t ) { $out['taxonomies'][] = $t; } }
		if ( isset( $pt['_old_slug'] ) ) { $out['_old_slug'] = self::clean_slug( $pt['_old_slug'], 20 ); }
		return $out;
	}

	private static function sanitize_taxonomy( $tx ) {
		$tx = is_array( $tx ) ? $tx : array();
		$out = array(
			'slug'              => self::clean_slug( $tx['slug'] ?? '', 32 ),
			'singular'          => sanitize_text_field( $tx['singular'] ?? '' ),
			'plural'            => sanitize_text_field( $tx['plural'] ?? '' ),
			'active'            => ! empty( $tx['active'] ),
			'public'            => ! empty( $tx['public'] ),
			'hierarchical'      => ! empty( $tx['hierarchical'] ),
			'show_in_rest'      => ! empty( $tx['show_in_rest'] ),
			'show_admin_column' => ! empty( $tx['show_admin_column'] ),
			'object_types'      => array(),
		);
		if ( '' === $out['singular'] ) { $out['singular'] = ucfirst( str_replace( array( '_', '-' ), ' ', $out['slug'] ) ); }
		if ( '' === $out['plural'] )   { $out['plural']   = $out['singular'] . 's'; }
		$ot = isset( $tx['object_types'] ) && is_array( $tx['object_types'] ) ? $tx['object_types'] : array();
		foreach ( $ot as $o ) { $o = self::clean_slug( $o, 20 ); if ( $o ) { $out['object_types'][] = $o; } }
		if ( ! $out['object_types'] ) { $out['object_types'] = array( 'post' ); }
		if ( isset( $tx['_old_slug'] ) ) { $out['_old_slug'] = self::clean_slug( $tx['_old_slug'], 32 ); }
		return $out;
	}

	/* ------------------------------------------------------------------ *
	 * Registration
	 * ------------------------------------------------------------------ */

	public static function register_all() {
		foreach ( self::all_post_types() as $pt ) {
			if ( empty( $pt['active'] ) || empty( $pt['slug'] ) ) { continue; }
			register_post_type( $pt['slug'], self::build_pt_args( $pt ) );
		}
		foreach ( self::all_taxonomies() as $tx ) {
			if ( empty( $tx['active'] ) || empty( $tx['slug'] ) ) { continue; }
			$objects = ! empty( $tx['object_types'] ) ? $tx['object_types'] : array( 'post' );
			register_taxonomy( $tx['slug'], $objects, self::build_tax_args( $tx ) );
		}
	}

	public static function build_pt_args( $pt ) {
		$singular = $pt['singular'] ?: ucfirst( $pt['slug'] );
		$plural   = $pt['plural'] ?: ( $singular . 's' );
		$args = array(
			'labels'        => self::pt_labels( $singular, $plural ),
			'public'        => ! empty( $pt['public'] ),
			'show_ui'       => true,
			'show_in_menu'  => ! empty( $pt['show_in_menu'] ),
			'show_in_rest'  => ! empty( $pt['show_in_rest'] ),
			'has_archive'   => ! empty( $pt['has_archive'] ),
			'hierarchical'  => ! empty( $pt['hierarchical'] ),
			'menu_position' => isset( $pt['menu_position'] ) ? (int) $pt['menu_position'] : 25,
			'supports'      => ! empty( $pt['supports'] ) ? $pt['supports'] : array( 'title', 'editor' ),
			'rewrite'       => array( 'slug' => $pt['slug'] ),
		);
		if ( ! empty( $pt['menu_icon'] ) ) { $args['menu_icon'] = $pt['menu_icon']; }
		if ( ! empty( $pt['taxonomies'] ) ) { $args['taxonomies'] = $pt['taxonomies']; }
		return $args;
	}

	public static function build_tax_args( $tx ) {
		$singular = $tx['singular'] ?: ucfirst( $tx['slug'] );
		$plural   = $tx['plural'] ?: ( $singular . 's' );
		return array(
			'labels'            => self::tax_labels( $singular, $plural ),
			'public'            => ! empty( $tx['public'] ),
			'hierarchical'      => ! empty( $tx['hierarchical'] ),
			'show_ui'           => true,
			'show_in_rest'      => ! empty( $tx['show_in_rest'] ),
			'show_admin_column' => ! empty( $tx['show_admin_column'] ),
			'rewrite'           => array( 'slug' => $tx['slug'] ),
		);
	}

	private static function pt_labels( $singular, $plural ) {
		$ls = strtolower( $singular );
		$lp = strtolower( $plural );
		return array(
			'name'               => $plural,
			'singular_name'      => $singular,
			'menu_name'          => $plural,
			'add_new'            => 'Add New',
			'add_new_item'       => 'Add New ' . $singular,
			'edit_item'          => 'Edit ' . $singular,
			'new_item'           => 'New ' . $singular,
			'view_item'          => 'View ' . $singular,
			'view_items'         => 'View ' . $plural,
			'search_items'       => 'Search ' . $plural,
			'not_found'          => 'No ' . $lp . ' found',
			'not_found_in_trash' => 'No ' . $lp . ' found in Trash',
			'all_items'          => 'All ' . $plural,
			'archives'           => $singular . ' Archives',
			'attributes'         => $singular . ' Attributes',
			'item_published'     => $singular . ' published.',
			'item_updated'       => $singular . ' updated.',
		);
	}

	private static function tax_labels( $singular, $plural ) {
		$lp = strtolower( $plural );
		return array(
			'name'              => $plural,
			'singular_name'     => $singular,
			'menu_name'         => $plural,
			'all_items'         => 'All ' . $plural,
			'edit_item'         => 'Edit ' . $singular,
			'view_item'         => 'View ' . $singular,
			'update_item'       => 'Update ' . $singular,
			'add_new_item'      => 'Add New ' . $singular,
			'new_item_name'     => 'New ' . $singular . ' Name',
			'search_items'      => 'Search ' . $plural,
			'not_found'         => 'No ' . $lp . ' found',
			'parent_item'       => 'Parent ' . $singular,
			'parent_item_colon' => 'Parent ' . $singular . ':',
		);
	}

	/** Post types available to attach taxonomies / field groups to (built-in + Velox). */
	public static function selectable_post_types() {
		$out = array( 'post' => 'Posts', 'page' => 'Pages' );
		foreach ( self::all_post_types() as $pt ) {
			if ( ! empty( $pt['slug'] ) ) { $out[ $pt['slug'] ] = ( $pt['plural'] ?: $pt['slug'] ); }
		}
		return $out;
	}
}
