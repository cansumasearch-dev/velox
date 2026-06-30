<?php
/**
 * Velox Fields — ACF-style custom fields.
 *
 * Field groups are stored in a single option as an array. Each group has a
 * title, a list of fields, location rules (AND within a group of rules, OR
 * between rule-groups), and presentation settings. Values are saved to post
 * meta keyed by the field's `name`. A get_field()-style API and {field:name}
 * merge tags read them back.
 *
 * @package Velox
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Velox_Fields {

	const OPTION = 'velox_field_groups';
	const OPT_PAGES = 'velox_options_pages';   // registered options pages
	const OPT_STORE = 'velox_field_options';   // option-page field values (name => value)

	/** Supported field types and their metadata (label, icon hint, has-options). */
	public static function types() {
		return array(
			'text'         => array( 'label' => 'Text',            'opts' => false ),
			'textarea'     => array( 'label' => 'Text area',       'opts' => false ),
			'number'       => array( 'label' => 'Number',          'opts' => false ),
			'email'        => array( 'label' => 'Email',           'opts' => false ),
			'url'          => array( 'label' => 'URL',             'opts' => false ),
			'password'     => array( 'label' => 'Password',        'opts' => false ),
			'select'       => array( 'label' => 'Select',          'opts' => true ),
			'checkbox'     => array( 'label' => 'Checkbox',        'opts' => true ),
			'radio'        => array( 'label' => 'Radio',           'opts' => true ),
			'button_group' => array( 'label' => 'Button group',    'opts' => true ),
			'truefalse'    => array( 'label' => 'True / False',    'opts' => false ),
			'range'        => array( 'label' => 'Range slider',    'opts' => false ),
			'link'         => array( 'label' => 'Link',            'opts' => false ),
			'oembed'       => array( 'label' => 'oEmbed',          'opts' => false ),
			'post_object'  => array( 'label' => 'Post object',     'opts' => true ),
			'page_link'    => array( 'label' => 'Page link',       'opts' => true ),
			'relationship' => array( 'label' => 'Relationship',    'opts' => true ),
			'taxonomy'     => array( 'label' => 'Taxonomy term',   'opts' => true ),
			'user'         => array( 'label' => 'User',            'opts' => false ),
			'image'        => array( 'label' => 'Image',           'opts' => false ),
			'gallery'      => array( 'label' => 'Gallery',         'opts' => false ),
			'file'         => array( 'label' => 'File',            'opts' => false ),
			'wysiwyg'      => array( 'label' => 'WYSIWYG editor',  'opts' => false ),
			'date'         => array( 'label' => 'Date picker',     'opts' => false ),
			'datetime'     => array( 'label' => 'Date & time',     'opts' => false ),
			'time'         => array( 'label' => 'Time picker',     'opts' => false ),
			'color'        => array( 'label' => 'Color picker',    'opts' => false ),
			'message'      => array( 'label' => 'Message',         'opts' => false ),
			'repeater'     => array( 'label' => 'Repeater',        'opts' => false ),
			'flexible'     => array( 'label' => 'Flexible Content', 'opts' => false ),
			'group'        => array( 'label' => 'Group',           'opts' => false ),
		);
	}

	/** Location rule parameters. */
	/** Choices for each location param, so the value can be a dropdown. */
	public static function location_choices() {
		$out = array();
		$pts = array();
		foreach ( get_post_types( array( 'show_ui' => true ), 'objects' ) as $pt ) { $pts[ $pt->name ] = $pt->label; }
		$out['post_type'] = $pts;
		$tx = array();
		foreach ( get_taxonomies( array( 'show_ui' => true ), 'objects' ) as $t ) { $tx[ $t->name ] = $t->label; }
		$out['taxonomy'] = $tx;
		$out['post_status'] = array( 'publish' => 'Published', 'draft' => 'Draft', 'pending' => 'Pending review', 'private' => 'Private', 'future' => 'Scheduled' );
		$roles = array();
		if ( ! function_exists( 'get_editable_roles' ) ) { require_once ABSPATH . 'wp-admin/includes/user.php'; }
		foreach ( get_editable_roles() as $k => $r ) { $roles[ $k ] = $r['name']; }
		$out['user_role'] = $roles;
		$op = array();
		foreach ( self::all_option_pages() as $p ) { $op[ $p['slug'] ] = $p['title']; }
		$out['options_page'] = $op;
		$tpls  = array( 'default' => 'Default template' );
		$theme = wp_get_theme();
		if ( $theme ) { foreach ( (array) $theme->get_page_templates() as $file => $tname ) { $tpls[ $file ] = $tname; } }
		$out['page_template'] = $tpls;
		return $out;
	}

	public static function location_params() {
		return array(
			'post_type'   => 'Post type',
			'post_status' => 'Post status',
			'page_template' => 'Page template',
			'taxonomy'    => 'Taxonomy',
			'user_role'   => 'User role',
			'options_page' => 'Options page',
		);
	}

	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'register_meta_boxes' ) );
		add_action( 'save_post', array( __CLASS__, 'save_post_meta' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_edit_assets' ) );
		add_action( 'admin_menu', array( __CLASS__, 'register_option_pages' ) );
		add_action( 'admin_post_velox_save_options', array( __CLASS__, 'handle_save_options' ) );
	}

	/* ------------------------------------------------------------------ *
	 * Options pages
	 * ------------------------------------------------------------------ */

	public static function all_option_pages() {
		$v = get_option( self::OPT_PAGES, array() );
		return is_array( $v ) ? $v : array();
	}
	public static function get_option_page( $slug ) {
		$all = self::all_option_pages();
		return isset( $all[ $slug ] ) ? $all[ $slug ] : null;
	}
	public static function blank_option_page() {
		return array(
			'slug'       => '',
			'title'      => '',
			'menu_title' => '',
			'icon'       => 'dashicons-admin-generic',
			'position'   => 80,
			'parent'     => '',           // '' = top-level; or a parent menu slug
			'capability' => 'manage_options',
			'active'     => ! isset( $p['active'] ) || ! empty( $p['active'] ),
		);
	}
	public static function save_option_page( $p ) {
		$p = self::sanitize_option_page( $p );
		if ( '' === $p['slug'] ) { return array( 'ok' => false, 'message' => 'A slug is required.' ); }
		$all = self::all_option_pages();
		if ( ! empty( $p['_old_slug'] ) && $p['_old_slug'] !== $p['slug'] ) { unset( $all[ $p['_old_slug'] ] ); }
		unset( $p['_old_slug'] );
		$all[ $p['slug'] ] = $p;
		update_option( self::OPT_PAGES, $all );
		return array( 'ok' => true, 'slug' => $p['slug'], 'page' => $p );
	}
	public static function delete_option_page( $slug ) {
		$all = self::all_option_pages();
		if ( isset( $all[ $slug ] ) ) { unset( $all[ $slug ] ); update_option( self::OPT_PAGES, $all ); return true; }
		return false;
	}
	private static function sanitize_option_page( $p ) {
		$p    = is_array( $p ) ? $p : array();
		$slug = sanitize_key( $p['slug'] ?? '' );
		$icon = sanitize_text_field( $p['icon'] ?? 'dashicons-admin-generic' );
		if ( '' !== $icon && 0 !== strpos( $icon, 'dashicons-' ) && ! preg_match( '#^https?://#', $icon ) ) { $icon = 'dashicons-admin-generic'; }
		$parents = array( '', 'options-general.php', 'themes.php', 'tools.php', 'edit.php', 'upload.php', 'velox' );
		$out = array(
			'slug'       => $slug,
			'title'      => sanitize_text_field( $p['title'] ?? '' ),
			'menu_title' => sanitize_text_field( $p['menu_title'] ?? '' ),
			'icon'       => $icon,
			'position'   => isset( $p['position'] ) && '' !== $p['position'] ? (int) $p['position'] : 80,
			'parent'     => in_array( ( $p['parent'] ?? '' ), $parents, true ) ? $p['parent'] : '',
			'capability' => 'manage_options',
		);
		if ( '' === $out['title'] ) { $out['title'] = ucwords( str_replace( array( '-', '_' ), ' ', $slug ) ); }
		if ( '' === $out['menu_title'] ) { $out['menu_title'] = $out['title']; }
		if ( isset( $p['_old_slug'] ) ) { $out['_old_slug'] = sanitize_key( $p['_old_slug'] ); }
		return $out;
	}

	public static function register_option_pages() {
		foreach ( self::all_option_pages() as $p ) {
			if ( empty( $p['slug'] ) ) { continue; }
			if ( isset( $p['active'] ) && ! $p['active'] ) { continue; }
			$menu_slug = 'velox-opt-' . $p['slug'];
			$cb        = function () use ( $p ) { Velox_Fields::render_option_page( $p ); };
			if ( '' === $p['parent'] ) {
				add_menu_page( $p['title'], $p['menu_title'], $p['capability'], $menu_slug, $cb, $p['icon'], (int) $p['position'] );
			} else {
				add_submenu_page( $p['parent'], $p['title'], $p['menu_title'], $p['capability'], $menu_slug, $cb );
			}
		}
	}

	/** Field groups whose location targets a given options page. */
	public static function groups_for_options_page( $slug ) {
		$out = array();
		foreach ( self::all() as $group ) {
			if ( empty( $group['active'] ) ) { continue; }
			foreach ( (array) ( $group['location'] ?? array() ) as $rule_group ) {
				foreach ( (array) $rule_group as $rule ) {
					if ( 'options_page' === ( $rule['param'] ?? '' ) && 'is' === ( $rule['operator'] ?? 'is' ) && $slug === $rule['value'] ) {
						$out[] = $group;
						break 2;
					}
				}
			}
		}
		return $out;
	}

	public static function render_option_page( $p ) {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$store  = get_option( self::OPT_STORE, array() );
		$store  = is_array( $store ) ? $store : array();
		$groups = self::groups_for_options_page( $p['slug'] );
		echo '<div class="wrap velox-optpage"><h1>' . esc_html( $p['title'] ) . '</h1>';
		if ( isset( $_GET['updated'] ) ) { echo '<div class="notice notice-success is-dismissible"><p>Options saved.</p></div>'; }
		if ( ! $groups ) {
			echo '<p>No field groups target this options page yet. In <strong>Custom fields → a field group</strong>, set a location rule of <em>Options page is ' . esc_html( $p['slug'] ) . '</em>.</p></div>';
			return;
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="velox_save_options">';
		echo '<input type="hidden" name="velox_opt_slug" value="' . esc_attr( $p['slug'] ) . '">';
		wp_nonce_field( 'velox_save_options_' . $p['slug'], 'velox_opt_nonce' );
		foreach ( $groups as $group ) {
			echo '<div class="velox-optpage-group"><h2>' . esc_html( $group['title'] ) . '</h2><div class="velox-fields-meta">';
			foreach ( $group['fields'] as $f ) {
				if ( isset( $f['active'] ) && ! $f['active'] ) { continue; }
				$value = isset( $store[ $f['name'] ] ) ? $store[ $f['name'] ] : '';
				if ( '' === $value && '' !== ( $f['default'] ?? '' ) ) { $value = $f['default']; }
				self::render_field_row( $f, $value );
			}
			echo '</div></div>';
		}
		submit_button( 'Save options' );
		echo '</form></div>';
	}

	public static function handle_save_options() {
		$slug = isset( $_POST['velox_opt_slug'] ) ? sanitize_key( wp_unslash( $_POST['velox_opt_slug'] ) ) : '';
		if ( ! current_user_can( 'manage_options' ) || '' === $slug ) { wp_die( 'Not allowed.' ); }
		if ( empty( $_POST['velox_opt_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['velox_opt_nonce'] ) ), 'velox_save_options_' . $slug ) ) {
			wp_die( 'Security check failed.' );
		}
		$submitted = isset( $_POST['velox_field'] ) ? (array) wp_unslash( $_POST['velox_field'] ) : array();
		$store     = get_option( self::OPT_STORE, array() );
		$store     = is_array( $store ) ? $store : array();
		foreach ( self::groups_for_options_page( $slug ) as $group ) {
			foreach ( $group['fields'] as $f ) {
				if ( isset( $f['active'] ) && ! $f['active'] ) { continue; }
				$store[ $f['name'] ] = self::field_value_from_submitted( $f, $submitted );
			}
		}
		update_option( self::OPT_STORE, $store );
		wp_safe_redirect( add_query_arg( array( 'page' => 'velox-opt-' . $slug, 'updated' => '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/** Media library + field-editor assets on the post add/edit screens. */
	public static function enqueue_edit_assets( $hook ) {
		$is_post = in_array( $hook, array( 'post.php', 'post-new.php' ), true );
		$is_opt  = false !== strpos( (string) $hook, 'velox-opt-' );
		if ( ! $is_post && ! $is_opt ) { return; }
		wp_enqueue_media();
		wp_enqueue_style( 'velox-fields-edit', VELOX_ASSETS . 'css/velox-fields-edit.css', array(), VELOX_VERSION );
		wp_enqueue_script( 'velox-fields-edit', VELOX_ASSETS . 'js/velox-fields-edit.js', array( 'jquery' ), VELOX_VERSION, true );
	}

	/* ------------------------------------------------------------------ *
	 * Storage / CRUD
	 * ------------------------------------------------------------------ */

	/** All field groups (array keyed by id). */
	public static function all() {
		$groups = get_option( self::OPTION, array() );
		return is_array( $groups ) ? $groups : array();
	}

	public static function get( $id ) {
		$all = self::all();
		return isset( $all[ $id ] ) ? $all[ $id ] : null;
	}

	public static function blank() {
		return array(
			'id'      => 0,
			'title'   => 'New field group',
			'active'  => true,
			'fields'  => array(),
			'location' => array(
				// one rule-group with one rule by default
				array( array( 'param' => 'post_type', 'operator' => 'is', 'value' => 'post' ) ),
			),
			'presentation' => array(
				'label_placement' => 'top',  // top | left
				'position'        => 'normal', // normal | side
				'order'           => 0,
			),
		);
	}

	public static function blank_field() {
		return array(
			'key'      => '',
			'label'    => 'New field',
			'name'     => '',
			'type'     => 'text',
			'required' => false,
			'instructions' => '',
			'default'  => '',
			'options'  => '',
			'placeholder' => '',
		);
	}

	/** Save (insert or update) a field group; returns the stored group. */
	public static function save( $group ) {
		$all = self::all();
		$group = self::sanitize( $group );
		if ( empty( $group['id'] ) ) {
			$group['id'] = self::next_id( $all );
		}
		$all[ $group['id'] ] = $group;
		update_option( self::OPTION, $all );
		return $group;
	}

	public static function delete( $id ) {
		$all = self::all();
		if ( isset( $all[ $id ] ) ) {
			unset( $all[ $id ] );
			update_option( self::OPTION, $all );
			return true;
		}
		return false;
	}

	private static function next_id( $all ) {
		$max = 0;
		foreach ( array_keys( $all ) as $k ) { $max = max( $max, (int) $k ); }
		return $max + 1;
	}

	/* ------------------------------------------------------------------ *
	 * Sanitization
	 * ------------------------------------------------------------------ */

	public static function sanitize( $group ) {
		$types = array_keys( self::types() );
		$params = array_keys( self::location_params() );
		$out = array(
			'id'     => (int) ( $group['id'] ?? 0 ),
			'title'  => sanitize_text_field( $group['title'] ?? 'Field group' ),
			'active' => ! empty( $group['active'] ),
			'fields' => array(),
			'location' => array(),
			'presentation' => array(
				'label_placement' => ( ( $group['presentation']['label_placement'] ?? 'top' ) === 'left' ) ? 'left' : 'top',
				'position'        => ( ( $group['presentation']['position'] ?? 'normal' ) === 'side' ) ? 'side' : 'normal',
				'order'           => (int) ( $group['presentation']['order'] ?? 0 ),
			),
		);
		$used_names = array();
		foreach ( (array) ( $group['fields'] ?? array() ) as $f ) {
			$type = in_array( ( $f['type'] ?? 'text' ), $types, true ) ? $f['type'] : 'text';
			$label = sanitize_text_field( $f['label'] ?? '' );
			$name  = sanitize_key( $f['name'] ?? '' );
			if ( '' === $name ) { $name = self::slugify( $label ); }
			// ensure unique meta names within the group
			$base = $name ? $name : 'field'; $n = 2;
			while ( isset( $used_names[ $name ] ) ) { $name = $base . '_' . ( $n++ ); }
			$used_names[ $name ] = 1;
			$field = array(
				'key'          => sanitize_key( $f['key'] ?? ( 'field_' . $name ) ),
				'label'        => $label,
				'name'         => $name,
				'type'         => $type,
				'required'     => ! empty( $f['required'] ),
				'active'       => ! isset( $f['active'] ) || ! empty( $f['active'] ),
				'instructions' => sanitize_text_field( $f['instructions'] ?? '' ),
				'default'      => sanitize_text_field( $f['default'] ?? '' ),
				'options'      => sanitize_textarea_field( $f['options'] ?? '' ),
				'placeholder'  => sanitize_text_field( $f['placeholder'] ?? '' ),
			);
			if ( in_array( $type, array( 'repeater', 'group' ), true ) ) {
				$field['sub_fields'] = self::sanitize_sub_fields( $f['sub_fields'] ?? array() );
			}
			if ( 'flexible' === $type ) {
				$field['layouts'] = self::sanitize_layouts( $f['layouts'] ?? array() );
			}
			$field['conditional'] = self::sanitize_conditional( $f['conditional'] ?? array() );
			// Per-type settings (only the relevant ones are honoured at render/save time).
			foreach ( array( 'min', 'max', 'step', 'rows', 'maxlength' ) as $nk ) {
				if ( isset( $f[ $nk ] ) && '' !== $f[ $nk ] && is_numeric( $f[ $nk ] ) ) { $field[ $nk ] = $f[ $nk ] + 0; }
			}
			$field['multiple']   = ! empty( $f['multiple'] );
			foreach ( array( 'readonly', 'allow_null', 'media_upload' ) as $bk ) {
				if ( isset( $f[ $bk ] ) ) { $field[ $bk ] = ! empty( $f[ $bk ] ); }
			}
			foreach ( array( 'layout', 'toolbar' ) as $sk ) {
				if ( isset( $f[ $sk ] ) && '' !== $f[ $sk ] ) { $field[ $sk ] = sanitize_key( $f[ $sk ] ); }
			}
			foreach ( array( 'prepend', 'append' ) as $tk ) {
				if ( isset( $f[ $tk ] ) && '' !== $f[ $tk ] ) { $field[ $tk ] = sanitize_text_field( $f[ $tk ] ); }
			}
			if ( isset( $f['return_format'] ) && '' !== $f['return_format'] ) { $field['return_format'] = sanitize_text_field( $f['return_format'] ); }
			$ww = isset( $f['wrapper_width'] ) ? (int) $f['wrapper_width'] : 100;
			$field['wrapper_width'] = ( $ww >= 10 && $ww <= 100 ) ? $ww : 100;
			$field['wrapper_class'] = sanitize_html_class( $f['wrapper_class'] ?? '' );
			$out['fields'][] = $field;
		}
		foreach ( (array) ( $group['location'] ?? array() ) as $rule_group ) {
			$rg = array();
			foreach ( (array) $rule_group as $rule ) {
				$param = in_array( ( $rule['param'] ?? 'post_type' ), $params, true ) ? $rule['param'] : 'post_type';
				$rg[] = array(
					'param'    => $param,
					'operator' => ( ( $rule['operator'] ?? 'is' ) === 'is_not' ) ? 'is_not' : 'is',
					'value'    => sanitize_text_field( $rule['value'] ?? '' ),
				);
			}
			if ( $rg ) { $out['location'][] = $rg; }
		}
		if ( empty( $out['location'] ) ) {
			$out['location'] = array( array( array( 'param' => 'post_type', 'operator' => 'is', 'value' => 'post' ) ) );
		}
		return $out;
	}

	/** Sub-fields for a repeater (simple types only; no nesting in v1). */
	private static function sanitize_sub_fields( $subs ) {
		$allowed = array( 'text', 'textarea', 'number', 'email', 'url', 'select', 'image', 'file', 'truefalse', 'color', 'date' );
		$out = array();
		$used = array();
		foreach ( (array) $subs as $s ) {
			$type  = in_array( ( $s['type'] ?? 'text' ), $allowed, true ) ? $s['type'] : 'text';
			$label = sanitize_text_field( $s['label'] ?? '' );
			$name  = sanitize_key( $s['name'] ?? '' );
			if ( '' === $name ) { $name = self::slugify( $label ); }
			$base = $name ? $name : 'sub';
			$n = 2;
			while ( isset( $used[ $name ] ) ) { $name = $base . '_' . ( $n++ ); }
			$used[ $name ] = 1;
			$out[] = array(
				'label'   => $label,
				'name'    => $name,
				'type'    => $type,
				'options' => sanitize_textarea_field( $s['options'] ?? '' ),
			);
		}
		return $out;
	}

	/** Flexible-content layouts: each is a named bundle of sub-fields. */
	private static function sanitize_layouts( $layouts ) {
		$out  = array();
		$used = array();
		foreach ( (array) $layouts as $l ) {
			$label = sanitize_text_field( $l['label'] ?? '' );
			$name  = sanitize_key( $l['name'] ?? '' );
			if ( '' === $name ) { $name = self::slugify( $label ); }
			$base = $name ? $name : 'layout';
			$n = 2;
			while ( isset( $used[ $name ] ) ) { $name = $base . '_' . ( $n++ ); }
			$used[ $name ] = 1;
			$out[] = array(
				'name'       => $name,
				'label'      => $label ? $label : $name,
				'sub_fields' => self::sanitize_sub_fields( $l['sub_fields'] ?? array() ),
			);
		}
		return $out;
	}

	/** Conditional logic: show a field when rules match. groups = OR of AND-rule arrays. */
	private static function sanitize_conditional( $c ) {
		$c   = is_array( $c ) ? $c : array();
		$ops = array( '==', '!=', 'empty', '!empty' );
		$out = array( 'enabled' => ! empty( $c['enabled'] ), 'groups' => array() );
		foreach ( (array) ( $c['groups'] ?? array() ) as $group ) {
			$g = array();
			foreach ( (array) $group as $rule ) {
				$field = sanitize_key( $rule['field'] ?? '' );
				if ( '' === $field ) { continue; }
				$g[] = array(
					'field'    => $field,
					'operator' => in_array( ( $rule['operator'] ?? '==' ), $ops, true ) ? $rule['operator'] : '==',
					'value'    => sanitize_text_field( $rule['value'] ?? '' ),
				);
			}
			if ( $g ) { $out['groups'][] = $g; }
		}
		if ( empty( $out['groups'] ) ) { $out['enabled'] = false; }
		return $out;
	}

	public static function slugify( $s ) {
		$s = strtolower( (string) $s );
		$s = preg_replace( '/[^a-z0-9]+/', '_', $s );
		$s = trim( $s, '_' );
		return $s ? $s : 'field';
	}

	/* ------------------------------------------------------------------ *
	 * Location matching
	 * ------------------------------------------------------------------ */

	/** Does this group apply to the given post? (OR between rule-groups, AND within.) */
	public static function group_matches( $group, $post ) {
		if ( empty( $group['active'] ) ) { return false; }
		$location = $group['location'] ?? array();
		if ( empty( $location ) ) { return false; }
		foreach ( $location as $rule_group ) {
			$all_match = true;
			foreach ( $rule_group as $rule ) {
				if ( ! self::rule_matches( $rule, $post ) ) { $all_match = false; break; }
			}
			if ( $all_match ) { return true; }
		}
		return false;
	}

	private static function rule_matches( $rule, $post ) {
		$is = ( ( $rule['operator'] ?? 'is' ) === 'is' );
		$val = $rule['value'] ?? '';
		$actual = '';
		switch ( $rule['param'] ?? 'post_type' ) {
			case 'post_type':   $actual = $post->post_type; break;
			case 'post_status': $actual = $post->post_status; break;
			case 'page_template': $actual = get_page_template_slug( $post->ID ); break;
			case 'user_role':
				$user = wp_get_current_user();
				$roles = (array) ( $user->roles ?? array() );
				$match = in_array( $val, $roles, true );
				return $is ? $match : ! $match;
			case 'taxonomy':
				$match = has_term( '', $val, $post );
				return $is ? (bool) $match : ! $match;
			case 'options_page':
				return ! $is; // matched only on the options page render, never on a post
		}
		$match = ( $actual === $val );
		return $is ? $match : ! $match;
	}

	/** Groups that apply to a post, sorted by presentation order. */
	public static function groups_for_post( $post ) {
		$out = array();
		foreach ( self::all() as $group ) {
			if ( self::group_matches( $group, $post ) ) { $out[] = $group; }
		}
		usort( $out, function ( $a, $b ) {
			return ( $a['presentation']['order'] ?? 0 ) <=> ( $b['presentation']['order'] ?? 0 );
		} );
		return $out;
	}

	/* ------------------------------------------------------------------ *
	 * Meta boxes on the post edit screen
	 * ------------------------------------------------------------------ */

	public static function register_meta_boxes() {
		global $post;
		if ( ! $post ) { return; }
		foreach ( self::groups_for_post( $post ) as $group ) {
			$context = ( ( $group['presentation']['position'] ?? 'normal' ) === 'side' ) ? 'side' : 'normal';
			add_meta_box(
				'velox_fields_' . $group['id'],
				$group['title'],
				array( __CLASS__, 'render_meta_box' ),
				null,
				$context,
				'default',
				array( 'group' => $group )
			);
		}
	}

	public static function render_meta_box( $post, $box ) {
		$group = $box['args']['group'];
		$placement = $group['presentation']['label_placement'] ?? 'top';
		wp_nonce_field( 'velox_fields_save_' . $group['id'], 'velox_fields_nonce_' . $group['id'] );
		echo '<div class="velox-fields-meta velox-fields-meta--' . esc_attr( $placement ) . '">';
		foreach ( $group['fields'] as $f ) {
			if ( isset( $f['active'] ) && ! $f['active'] ) { continue; }
			$value = get_post_meta( $post->ID, $f['name'], true );
			if ( '' === $value && '' !== ( $f['default'] ?? '' ) ) { $value = $f['default']; }
			self::render_field_row( $f, $value );
		}
		echo '</div>';
	}

	/** Render one field row (label + control + instructions). Shared by meta box + options pages. */
	private static function render_field_row( $f, $value ) {
		$cond = isset( $f['conditional'] ) && ! empty( $f['conditional']['enabled'] ) && ! empty( $f['conditional']['groups'] ) ? $f['conditional'] : null;
		$w = isset( $f['wrapper_width'] ) ? (int) $f['wrapper_width'] : 100;
		$cls = 'velox-fields-row';
		if ( ! empty( $f['wrapper_class'] ) ) { $cls .= ' ' . sanitize_html_class( $f['wrapper_class'] ); }
		$attrs = ' class="' . esc_attr( $cls ) . '"';
		if ( $w > 0 && $w < 100 ) { $attrs .= ' style="width:' . $w . '%"'; }
		$attrs .= ' data-vfx-field="' . esc_attr( $f['name'] ) . '"';
		if ( $cond ) { $attrs .= ' data-vfx-cond="' . esc_attr( wp_json_encode( $cond ) ) . '"'; }
		echo '<div' . $attrs . '>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<label class="velox-fields-label" for="velox_field_' . esc_attr( $f['name'] ) . '">' . esc_html( $f['label'] );
		if ( ! empty( $f['required'] ) ) { echo ' <span class="velox-fields-req">*</span>'; }
		echo '</label>';
		echo '<div class="velox-fields-control">';
		self::render_field_input( $f, $value );
		if ( ! empty( $f['instructions'] ) ) {
			echo '<p class="velox-fields-instructions">' . esc_html( $f['instructions'] ) . '</p>';
		}
		echo '</div></div>';
	}

	private static function minmax_attrs( $f ) {
		$a = '';
		if ( isset( $f['min'] ) && '' !== $f['min'] ) { $a .= ' min="' . esc_attr( $f['min'] ) . '"'; }
		if ( isset( $f['max'] ) && '' !== $f['max'] ) { $a .= ' max="' . esc_attr( $f['max'] ) . '"'; }
		if ( isset( $f['step'] ) && '' !== $f['step'] ) { $a .= ' step="' . esc_attr( $f['step'] ) . '"'; }
		return $a;
	}

	private static function wrap_addon( $f, $input_html ) {
		$pre = trim( (string) ( $f['prepend'] ?? '' ) );
		$app = trim( (string) ( $f['append'] ?? '' ) );
		if ( '' === $pre && '' === $app ) { return $input_html; }
		$h = '<div class="velox-input-addon">';
		if ( '' !== $pre ) { $h .= '<span class="velox-input-addon-pre">' . esc_html( $pre ) . '</span>'; }
		$h .= $input_html;
		if ( '' !== $app ) { $h .= '<span class="velox-input-addon-app">' . esc_html( $app ) . '</span>'; }
		return $h . '</div>';
	}

	private static function render_field_input( $f, $value ) {
		$name = 'velox_field[' . esc_attr( $f['name'] ) . ']';
		$id   = 'velox_field_' . esc_attr( $f['name'] );
		$ph   = esc_attr( $f['placeholder'] ?? '' );
		$opts = array_filter( array_map( 'trim', explode( "\n", (string) ( $f['options'] ?? '' ) ) ) );
		$ro   = ! empty( $f['readonly'] ) ? ' readonly' : '';
		switch ( $f['type'] ) {
			case 'textarea':
				$rows = isset( $f['rows'] ) ? (int) $f['rows'] : 5;
				$ml   = isset( $f['maxlength'] ) ? ' maxlength="' . (int) $f['maxlength'] . '"' : '';
				echo '<textarea id="' . $id . '" name="' . $name . '" rows="' . esc_attr( $rows ) . '" class="widefat" placeholder="' . $ph . '"' . $ml . $ro . '>' . esc_textarea( $value ) . '</textarea>';
				break;
			case 'wysiwyg':
				wp_editor(
					(string) $value,
					$id,
					array(
						'textarea_name' => 'velox_field[' . $f['name'] . ']',
						'textarea_rows' => isset( $f['rows'] ) ? (int) $f['rows'] : 8,
						'media_buttons' => ! isset( $f['media_upload'] ) || ! empty( $f['media_upload'] ),
						'teeny'         => isset( $f['toolbar'] ) && 'basic' === $f['toolbar'],
					)
				);
				break;
			case 'image':
				$img_id = (int) $value;
				$thumb  = $img_id ? wp_get_attachment_image( $img_id, 'medium', false, array( 'class' => 'velox-fld-media-img' ) ) : '';
				echo '<div class="velox-fld-media" data-mode="single">';
				echo '<input type="hidden" id="' . $id . '" name="' . $name . '" value="' . esc_attr( $img_id ? $img_id : '' ) . '" class="velox-fld-media-input">';
				echo '<div class="velox-fld-media-preview">' . $thumb . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
				echo '<div class="velox-fld-media-btns"><button type="button" class="button velox-fld-media-pick" data-title="Select image" data-multiple="0">' . esc_html__( 'Select image' ) . '</button> ';
				echo '<button type="button" class="button-link velox-fld-media-clear"' . ( $img_id ? '' : ' style="display:none"' ) . '>' . esc_html__( 'Remove' ) . '</button></div>';
				echo '</div>';
				break;
			case 'gallery':
				$ids = array_filter( array_map( 'intval', explode( ',', (string) $value ) ) );
				echo '<div class="velox-fld-gallery">';
				echo '<input type="hidden" id="' . $id . '" name="' . $name . '" value="' . esc_attr( implode( ',', $ids ) ) . '" class="velox-fld-gallery-input">';
				echo '<ul class="velox-fld-gallery-list">';
				foreach ( $ids as $gid ) {
					echo '<li class="velox-fld-gallery-item" data-id="' . esc_attr( $gid ) . '">' . wp_get_attachment_image( $gid, 'thumbnail' ) . '<button type="button" class="velox-fld-gallery-rm" aria-label="Remove">&times;</button></li>'; // phpcs:ignore WordPress.Security.EscapeOutput
				}
				echo '</ul>';
				echo '<button type="button" class="button velox-fld-gallery-add">' . esc_html__( 'Add images' ) . '</button>';
				echo '</div>';
				break;
			case 'repeater':
				$subs = isset( $f['sub_fields'] ) && is_array( $f['sub_fields'] ) ? $f['sub_fields'] : array();
				$rows = is_array( $value ) ? $value : array();
				echo '<div class="velox-rep" data-name="' . esc_attr( $f['name'] ) . '">';
				echo '<div class="velox-rep-rows">';
				$ri = 0;
				foreach ( $rows as $row ) {
					self::render_rep_row( $f['name'], $subs, $ri, is_array( $row ) ? $row : array() );
					$ri++;
				}
				echo '</div>';
				echo '<script type="text/html" class="velox-rep-tpl">';
				self::render_rep_row( $f['name'], $subs, '__i__', array() );
				echo '</script>';
				echo '<button type="button" class="button velox-rep-add">' . esc_html__( 'Add row' ) . '</button>';
				echo '</div>';
				break;
			case 'flexible':
				$layouts = isset( $f['layouts'] ) && is_array( $f['layouts'] ) ? $f['layouts'] : array();
				$rows    = is_array( $value ) ? $value : array();
				$lmap    = array();
				foreach ( $layouts as $l ) { $lmap[ $l['name'] ] = $l; }
				echo '<div class="velox-flex" data-name="' . esc_attr( $f['name'] ) . '">';
				echo '<div class="velox-flex-rows">';
				$ri = 0;
				foreach ( $rows as $row ) {
					$lname = is_array( $row ) && isset( $row['_layout'] ) ? $row['_layout'] : '';
					if ( isset( $lmap[ $lname ] ) ) {
						self::render_flex_row( $f['name'], $lmap[ $lname ], $ri, $row );
						$ri++;
					}
				}
				echo '</div>';
				foreach ( $layouts as $l ) {
					echo '<script type="text/html" class="velox-flex-tpl" data-layout="' . esc_attr( $l['name'] ) . '">';
					self::render_flex_row( $f['name'], $l, '__i__', array() );
					echo '</script>';
				}
				echo '<div class="velox-flex-add"><button type="button" class="button velox-flex-toggle">' . esc_html__( 'Add row' ) . ' &#9662;</button><div class="velox-flex-menu" hidden>';
				foreach ( $layouts as $l ) {
					echo '<button type="button" class="velox-flex-pick" data-layout="' . esc_attr( $l['name'] ) . '">' . esc_html( $l['label'] ) . '</button>';
				}
				echo '</div></div>';
				echo '</div>';
				break;
			case 'file':
				$file_id  = (int) $value;
				$file_url = $file_id ? wp_get_attachment_url( $file_id ) : '';
				echo '<div class="velox-fld-media" data-mode="file">';
				echo '<input type="hidden" id="' . $id . '" name="' . $name . '" value="' . esc_attr( $file_id ? $file_id : '' ) . '" class="velox-fld-media-input">';
				echo '<div class="velox-fld-media-file"' . ( $file_id ? '' : ' style="display:none"' ) . '>' . esc_html( $file_url ? basename( $file_url ) : '' ) . '</div>';
				echo '<div class="velox-fld-media-btns"><button type="button" class="button velox-fld-media-pick" data-title="Select file" data-multiple="0" data-file="1">' . esc_html__( 'Select file' ) . '</button> ';
				echo '<button type="button" class="button-link velox-fld-media-clear"' . ( $file_id ? '' : ' style="display:none"' ) . '>' . esc_html__( 'Remove' ) . '</button></div>';
				echo '</div>';
				break;
			case 'select':
				$multi = ! empty( $f['multiple'] );
				$vals  = $multi ? ( is_array( $value ) ? $value : array_filter( array_map( 'trim', explode( ',', (string) $value ) ) ) ) : array();
				echo '<select id="' . $id . '" name="' . $name . ( $multi ? '[]' : '' ) . '" class="widefat"' . ( $multi ? ' multiple size="5"' : '' ) . '>';
				if ( ! $multi && ( ! isset( $f['allow_null'] ) || ! empty( $f['allow_null'] ) ) ) { echo '<option value="">— Select —</option>'; }
				foreach ( $opts as $o ) {
					$on = $multi ? in_array( $o, $vals, true ) : ( (string) $value === (string) $o );
					echo '<option value="' . esc_attr( $o ) . '"' . ( $on ? ' selected' : '' ) . '>' . esc_html( $o ) . '</option>';
				}
				echo '</select>';
				break;
			case 'radio':
				echo '<div class="velox-fields-choices velox-fields-choices--' . esc_attr( ( isset( $f['layout'] ) && 'horizontal' === $f['layout'] ) ? 'horizontal' : 'vertical' ) . '">';
				foreach ( $opts as $o ) {
					echo '<label class="velox-fields-choice"><input type="radio" name="' . $name . '" value="' . esc_attr( $o ) . '"' . checked( $value, $o, false ) . '> ' . esc_html( $o ) . '</label>';
				}
				echo '</div>';
				break;
			case 'checkbox':
				$vals = is_array( $value ) ? $value : array_filter( array_map( 'trim', explode( ',', (string) $value ) ) );
				echo '<div class="velox-fields-choices velox-fields-choices--' . esc_attr( ( isset( $f['layout'] ) && 'horizontal' === $f['layout'] ) ? 'horizontal' : 'vertical' ) . '">';
				foreach ( $opts as $o ) {
					echo '<label class="velox-fields-choice"><input type="checkbox" name="' . $name . '[]" value="' . esc_attr( $o ) . '"' . ( in_array( $o, $vals, true ) ? ' checked' : '' ) . '> ' . esc_html( $o ) . '</label>';
				}
				echo '</div>';
				break;
			case 'truefalse':
				echo '<label class="velox-fields-choice"><input type="checkbox" name="' . $name . '" value="1"' . checked( $value, '1', false ) . '> ' . esc_html( $f['label'] ) . '</label>';
				break;
			case 'number':
				echo self::wrap_addon( $f, '<input type="number" id="' . $id . '" name="' . $name . '" class="widefat" value="' . esc_attr( $value ) . '" placeholder="' . $ph . '"' . self::minmax_attrs( $f ) . $ro . '>' );
				break;
			case 'email':
				echo self::wrap_addon( $f, '<input type="email" id="' . $id . '" name="' . $name . '" class="widefat" value="' . esc_attr( $value ) . '" placeholder="' . $ph . '"' . ( isset( $f['maxlength'] ) ? ' maxlength="' . (int) $f['maxlength'] . '"' : '' ) . $ro . '>' );
				break;
			case 'url':
				echo self::wrap_addon( $f, '<input type="url" id="' . $id . '" name="' . $name . '" class="widefat" value="' . esc_attr( $value ) . '" placeholder="' . $ph . '"' . ( isset( $f['maxlength'] ) ? ' maxlength="' . (int) $f['maxlength'] . '"' : '' ) . $ro . '>' );
				break;
			case 'date':
				echo '<input type="date" id="' . $id . '" name="' . $name . '" class="widefat" value="' . esc_attr( $value ) . '">';
				break;
			case 'datetime':
				echo '<input type="datetime-local" id="' . $id . '" name="' . $name . '" class="widefat" value="' . esc_attr( $value ) . '">';
				break;
			case 'time':
				echo '<input type="time" id="' . $id . '" name="' . $name . '" class="widefat" value="' . esc_attr( $value ) . '">';
				break;
			case 'password':
				echo self::wrap_addon( $f, '<input type="password" id="' . $id . '" name="' . $name . '" class="widefat" value="' . esc_attr( $value ) . '" placeholder="' . $ph . '" autocomplete="new-password"' . ( isset( $f['maxlength'] ) ? ' maxlength="' . (int) $f['maxlength'] . '"' : '' ) . $ro . '>' );
				break;
			case 'message':
				echo '<div class="velox-fld-message">' . wp_kses_post( wpautop( (string) ( $f['default'] ?? '' ) ) ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
				break;
			case 'color':
				echo '<input type="color" id="' . $id . '" name="' . $name . '" value="' . esc_attr( $value ? $value : '#000000' ) . '">';
				break;
			case 'button_group':
				echo '<div class="velox-fld-btngroup velox-fld-btngroup--' . esc_attr( ( isset( $f['layout'] ) && 'vertical' === $f['layout'] ) ? 'vertical' : 'horizontal' ) . '">';
				foreach ( $opts as $o ) {
					echo '<label class="velox-fld-btn' . ( (string) $value === (string) $o ? ' is-on' : '' ) . '"><input type="radio" name="' . $name . '" value="' . esc_attr( $o ) . '"' . checked( $value, $o, false ) . '> ' . esc_html( $o ) . '</label>';
				}
				echo '</div>';
				break;
			case 'range':
				$min = isset( $f['min'] ) ? $f['min'] : 0;
				$max = isset( $f['max'] ) ? $f['max'] : 100;
				$step = isset( $f['step'] ) ? $f['step'] : 1;
				$cur = ( '' === $value ) ? $min : $value;
				echo '<div class="velox-fld-range"><input type="range" id="' . $id . '" name="' . $name . '" min="' . esc_attr( $min ) . '" max="' . esc_attr( $max ) . '" step="' . esc_attr( $step ) . '" value="' . esc_attr( $cur ) . '"><output class="velox-fld-range-val">' . esc_html( $cur ) . '</output></div>';
				break;
			case 'oembed':
				echo '<input type="url" id="' . $id . '" name="' . $name . '" class="widefat" value="' . esc_attr( $value ) . '" placeholder="https://youtube.com/watch?v=…">';
				if ( $value && function_exists( 'wp_oembed_get' ) ) {
					$html = wp_oembed_get( $value );
					if ( $html ) { echo '<div class="velox-fld-oembed">' . $html . '</div>'; } // phpcs:ignore WordPress.Security.EscapeOutput
				}
				break;
			case 'link':
				$lv  = is_array( $value ) ? $value : array();
				$url = isset( $lv['url'] ) ? $lv['url'] : '';
				$txt = isset( $lv['title'] ) ? $lv['title'] : '';
				$tgt = isset( $lv['target'] ) ? $lv['target'] : '';
				echo '<div class="velox-fld-link">';
				echo '<input type="url" name="' . $name . '[url]" class="widefat" value="' . esc_attr( $url ) . '" placeholder="https://…">';
				echo '<input type="text" name="' . $name . '[title]" class="widefat" value="' . esc_attr( $txt ) . '" placeholder="Link text">';
				echo '<label class="velox-fields-choice"><input type="checkbox" name="' . $name . '[target]" value="_blank"' . checked( $tgt, '_blank', false ) . '> Open in new tab</label>';
				echo '</div>';
				break;
			case 'post_object':
			case 'page_link':
			case 'relationship':
				$multiple = 'relationship' === $f['type'];
				$ptypes   = $opts ? $opts : array( 'post', 'page' );
				$posts    = get_posts( array( 'post_type' => $ptypes, 'numberposts' => 100, 'orderby' => 'title', 'order' => 'ASC', 'suppress_filters' => false ) );
				$sel      = $multiple ? array_filter( array_map( 'intval', is_array( $value ) ? $value : explode( ',', (string) $value ) ) ) : array( (int) $value );
				echo '<select id="' . $id . '" name="' . $name . ( $multiple ? '[]' : '' ) . '" class="widefat"' . ( $multiple ? ' multiple size="6"' : '' ) . '>';
				if ( ! $multiple ) { echo '<option value="">— Select —</option>'; }
				foreach ( $posts as $pp ) {
					echo '<option value="' . esc_attr( $pp->ID ) . '"' . ( in_array( (int) $pp->ID, $sel, true ) ? ' selected' : '' ) . '>' . esc_html( $pp->post_title ? $pp->post_title : ( '#' . $pp->ID ) ) . '</option>';
				}
				echo '</select>';
				break;
			case 'taxonomy':
				$tax   = isset( $opts[0] ) ? $opts[0] : 'category';
				$terms = get_terms( array( 'taxonomy' => $tax, 'hide_empty' => false ) );
				echo '<select id="' . $id . '" name="' . $name . '" class="widefat"><option value="">— Select —</option>';
				if ( ! is_wp_error( $terms ) ) {
					foreach ( $terms as $t ) {
						echo '<option value="' . esc_attr( $t->term_id ) . '"' . selected( (int) $value, (int) $t->term_id, false ) . '>' . esc_html( $t->name ) . '</option>';
					}
				}
				echo '</select>';
				break;
			case 'user':
				$users = get_users( array( 'fields' => array( 'ID', 'display_name' ), 'number' => 200 ) );
				echo '<select id="' . $id . '" name="' . $name . '" class="widefat"><option value="">— Select —</option>';
				foreach ( $users as $u ) {
					echo '<option value="' . esc_attr( $u->ID ) . '"' . selected( (int) $value, (int) $u->ID, false ) . '>' . esc_html( $u->display_name ) . '</option>';
				}
				echo '</select>';
				break;
			case 'group':
				$subs = isset( $f['sub_fields'] ) && is_array( $f['sub_fields'] ) ? $f['sub_fields'] : array();
				$gval = is_array( $value ) ? $value : array();
				echo '<div class="velox-fld-group">';
				foreach ( $subs as $s ) {
					$sv    = isset( $gval[ $s['name'] ] ) ? $gval[ $s['name'] ] : '';
					$iname = 'velox_field[' . $f['name'] . '][' . $s['name'] . ']';
					$iid   = 'velox_' . $f['name'] . '_' . $s['name'];
					echo '<div class="velox-fld-group-field"><label class="velox-rep-flabel">' . esc_html( $s['label'] ? $s['label'] : $s['name'] ) . '</label>';
					self::render_sub_input( $s, $iname, $iid, $sv );
					echo '</div>';
				}
				echo '</div>';
				break;
			default: // text, and any not-yet-specialised type
				echo self::wrap_addon( $f, '<input type="text" id="' . $id . '" name="' . $name . '" class="widefat" value="' . esc_attr( $value ) . '" placeholder="' . $ph . '"' . ( isset( $f['maxlength'] ) ? ' maxlength="' . (int) $f['maxlength'] . '"' : '' ) . $ro . '>' );
		}
	}

	/** One repeater row: each sub-field named velox_field[rep][i][sub]. */
	private static function render_rep_row( $rep_name, $subs, $index, $row ) {
		echo '<div class="velox-rep-row" data-i="' . esc_attr( $index ) . '">';
		echo '<span class="velox-rep-handle" title="Drag to reorder"><svg viewBox="0 0 24 24" width="15" height="15" fill="currentColor"><circle cx="9" cy="6" r="1.4"/><circle cx="15" cy="6" r="1.4"/><circle cx="9" cy="12" r="1.4"/><circle cx="15" cy="12" r="1.4"/><circle cx="9" cy="18" r="1.4"/><circle cx="15" cy="18" r="1.4"/></svg></span>';
		echo '<div class="velox-rep-fields">';
		foreach ( $subs as $s ) {
			$val   = isset( $row[ $s['name'] ] ) ? $row[ $s['name'] ] : '';
			$iname = 'velox_field[' . $rep_name . '][' . $index . '][' . $s['name'] . ']';
			$iid   = 'velox_' . $rep_name . '_' . $index . '_' . $s['name'];
			echo '<div class="velox-rep-field">';
			echo '<label class="velox-rep-flabel">' . esc_html( $s['label'] ? $s['label'] : $s['name'] ) . '</label>';
			self::render_sub_input( $s, $iname, $iid, $val );
			echo '</div>';
		}
		echo '</div>';
		echo '<button type="button" class="velox-rep-rm" aria-label="Remove row">&times;</button>';
		echo '</div>';
	}

	/** A single sub-field input inside a repeater row. */
	private static function render_sub_input( $s, $name, $id, $value ) {
		$opts = array_filter( array_map( 'trim', explode( "\n", (string) ( $s['options'] ?? '' ) ) ) );
		switch ( $s['type'] ) {
			case 'textarea':
				echo '<textarea name="' . esc_attr( $name ) . '" rows="3" class="widefat">' . esc_textarea( $value ) . '</textarea>';
				break;
			case 'select':
				echo '<select name="' . esc_attr( $name ) . '" class="widefat"><option value="">— Select —</option>';
				foreach ( $opts as $o ) { echo '<option value="' . esc_attr( $o ) . '"' . selected( $value, $o, false ) . '>' . esc_html( $o ) . '</option>'; }
				echo '</select>';
				break;
			case 'truefalse':
				echo '<label class="velox-fields-choice"><input type="checkbox" name="' . esc_attr( $name ) . '" value="1"' . checked( $value, '1', false ) . '> ' . esc_html__( 'Yes' ) . '</label>';
				break;
			case 'number': case 'email': case 'url': case 'date':
				echo '<input type="' . esc_attr( $s['type'] ) . '" name="' . esc_attr( $name ) . '" class="widefat" value="' . esc_attr( $value ) . '">';
				break;
			case 'color':
				echo '<input type="color" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ? $value : '#000000' ) . '">';
				break;
			case 'image': case 'file':
				$aid   = (int) $value;
				$isimg = 'image' === $s['type'];
				$thumb = ( $isimg && $aid ) ? wp_get_attachment_image( $aid, 'thumbnail', false, array( 'class' => 'velox-fld-media-img' ) ) : '';
				$fname = ( ! $isimg && $aid ) ? basename( (string) wp_get_attachment_url( $aid ) ) : '';
				echo '<div class="velox-fld-media" data-mode="' . ( $isimg ? 'single' : 'file' ) . '">';
				echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( $aid ? $aid : '' ) . '" class="velox-fld-media-input">';
				if ( $isimg ) {
					echo '<div class="velox-fld-media-preview">' . $thumb . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
				} else {
					echo '<div class="velox-fld-media-file"' . ( $aid ? '' : ' style="display:none"' ) . '>' . esc_html( $fname ) . '</div>';
				}
				echo '<div class="velox-fld-media-btns"><button type="button" class="button velox-fld-media-pick" data-title="' . ( $isimg ? 'Select image' : 'Select file' ) . '"' . ( $isimg ? '' : ' data-file="1"' ) . '>' . ( $isimg ? esc_html__( 'Select image' ) : esc_html__( 'Select file' ) ) . '</button> ';
				echo '<button type="button" class="button-link velox-fld-media-clear"' . ( $aid ? '' : ' style="display:none"' ) . '>' . esc_html__( 'Remove' ) . '</button></div>';
				echo '</div>';
				break;
			default:
				echo '<input type="text" name="' . esc_attr( $name ) . '" class="widefat" value="' . esc_attr( $value ) . '">';
		}
	}

	/** One flexible-content row: tagged with its layout + that layout's sub-fields. */
	private static function render_flex_row( $field_name, $layout, $index, $row ) {
		$subs = isset( $layout['sub_fields'] ) && is_array( $layout['sub_fields'] ) ? $layout['sub_fields'] : array();
		echo '<div class="velox-rep-row velox-flex-row" data-i="' . esc_attr( $index ) . '" data-layout="' . esc_attr( $layout['name'] ) . '">';
		echo '<span class="velox-rep-handle" title="Drag to reorder"><svg viewBox="0 0 24 24" width="15" height="15" fill="currentColor"><circle cx="9" cy="6" r="1.4"/><circle cx="15" cy="6" r="1.4"/><circle cx="9" cy="12" r="1.4"/><circle cx="15" cy="12" r="1.4"/><circle cx="9" cy="18" r="1.4"/><circle cx="15" cy="18" r="1.4"/></svg></span>';
		echo '<input type="hidden" name="velox_field[' . esc_attr( $field_name ) . '][' . esc_attr( $index ) . '][_layout]" value="' . esc_attr( $layout['name'] ) . '">';
		echo '<div class="velox-rep-fields"><div class="velox-flex-lname">' . esc_html( $layout['label'] ) . '</div>';
		foreach ( $subs as $s ) {
			$val   = isset( $row[ $s['name'] ] ) ? $row[ $s['name'] ] : '';
			$iname = 'velox_field[' . $field_name . '][' . $index . '][' . $s['name'] . ']';
			$iid   = 'velox_' . $field_name . '_' . $index . '_' . $s['name'];
			echo '<div class="velox-rep-field"><label class="velox-rep-flabel">' . esc_html( $s['label'] ? $s['label'] : $s['name'] ) . '</label>';
			self::render_sub_input( $s, $iname, $iid, $val );
			echo '</div>';
		}
		echo '</div>';
		echo '<button type="button" class="velox-rep-rm" aria-label="Remove row">&times;</button>';
		echo '</div>';
	}

	public static function save_post_meta( $post_id, $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
		if ( ! current_user_can( 'edit_post', $post_id ) ) { return; }
		foreach ( self::groups_for_post( $post ) as $group ) {
			$nonce_key = 'velox_fields_nonce_' . $group['id'];
			if ( empty( $_POST[ $nonce_key ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ $nonce_key ] ) ), 'velox_fields_save_' . $group['id'] ) ) {
				continue;
			}
			$submitted = isset( $_POST['velox_field'] ) ? (array) wp_unslash( $_POST['velox_field'] ) : array();
			foreach ( $group['fields'] as $f ) {
				if ( isset( $f['active'] ) && ! $f['active'] ) { continue; }
				update_post_meta( $post_id, $f['name'], self::field_value_from_submitted( $f, $submitted ) );
			}
		}
	}

	/** Sanitize one field's submitted value by type (shared by post meta + options pages). */
	public static function field_value_from_submitted( $f, $submitted ) {
		$name = $f['name'];
		if ( 'checkbox' === $f['type'] ) {
			return isset( $submitted[ $name ] ) ? array_map( 'sanitize_text_field', (array) $submitted[ $name ] ) : array();
		}
		if ( 'select' === $f['type'] && ! empty( $f['multiple'] ) ) {
			return isset( $submitted[ $name ] ) ? array_map( 'sanitize_text_field', (array) $submitted[ $name ] ) : array();
		}
		if ( 'wysiwyg' === $f['type'] ) {
			return isset( $submitted[ $name ] ) ? wp_kses_post( $submitted[ $name ] ) : '';
		}
		if ( 'textarea' === $f['type'] ) {
			return isset( $submitted[ $name ] ) ? sanitize_textarea_field( $submitted[ $name ] ) : '';
		}
		if ( in_array( $f['type'], array( 'image', 'file', 'post_object', 'page_link', 'taxonomy', 'user' ), true ) ) {
			return isset( $submitted[ $name ] ) ? (int) $submitted[ $name ] : 0;
		}
		if ( 'message' === $f['type'] ) {
			return ''; // display-only, stores nothing
		}
		if ( 'relationship' === $f['type'] ) {
			$ids = isset( $submitted[ $name ] ) ? array_filter( array_map( 'intval', (array) $submitted[ $name ] ) ) : array();
			return array_values( $ids );
		}
		if ( 'oembed' === $f['type'] ) {
			return isset( $submitted[ $name ] ) ? esc_url_raw( $submitted[ $name ] ) : '';
		}
		if ( 'link' === $f['type'] ) {
			$l = isset( $submitted[ $name ] ) && is_array( $submitted[ $name ] ) ? $submitted[ $name ] : array();
			return array(
				'url'    => isset( $l['url'] ) ? esc_url_raw( $l['url'] ) : '',
				'title'  => isset( $l['title'] ) ? sanitize_text_field( $l['title'] ) : '',
				'target' => ( isset( $l['target'] ) && '_blank' === $l['target'] ) ? '_blank' : '',
			);
		}
		if ( 'gallery' === $f['type'] ) {
			$ids = isset( $submitted[ $name ] ) ? array_filter( array_map( 'intval', explode( ',', (string) $submitted[ $name ] ) ) ) : array();
			return implode( ',', $ids );
		}
		if ( 'group' === $f['type'] ) {
			$subs = isset( $f['sub_fields'] ) && is_array( $f['sub_fields'] ) ? $f['sub_fields'] : array();
			$row  = ( isset( $submitted[ $name ] ) && is_array( $submitted[ $name ] ) ) ? $submitted[ $name ] : array();
			return self::clean_row( $subs, $row );
		}
		if ( 'repeater' === $f['type'] ) {
			$subs    = isset( $f['sub_fields'] ) && is_array( $f['sub_fields'] ) ? $f['sub_fields'] : array();
			$rows_in = ( isset( $submitted[ $name ] ) && is_array( $submitted[ $name ] ) ) ? $submitted[ $name ] : array();
			$clean   = array();
			foreach ( $rows_in as $row ) {
				if ( ! is_array( $row ) ) { continue; }
				$clean[] = self::clean_row( $subs, $row );
			}
			return array_values( $clean );
		}
		if ( 'flexible' === $f['type'] ) {
			$layouts = isset( $f['layouts'] ) && is_array( $f['layouts'] ) ? $f['layouts'] : array();
			$lmap    = array();
			foreach ( $layouts as $l ) { $lmap[ $l['name'] ] = $l; }
			$rows_in = ( isset( $submitted[ $name ] ) && is_array( $submitted[ $name ] ) ) ? $submitted[ $name ] : array();
			$clean   = array();
			foreach ( $rows_in as $row ) {
				if ( ! is_array( $row ) ) { continue; }
				$lname = isset( $row['_layout'] ) ? sanitize_key( $row['_layout'] ) : '';
				if ( ! isset( $lmap[ $lname ] ) ) { continue; }
				$r = self::clean_row( $lmap[ $lname ]['sub_fields'], $row );
				$r = array( '_layout' => $lname ) + $r;
				$clean[] = $r;
			}
			return array_values( $clean );
		}
		return isset( $submitted[ $name ] ) ? sanitize_text_field( $submitted[ $name ] ) : '';
	}

	/** Sanitize one repeater/flexible row against its sub-field defs. */
	private static function clean_row( $subs, $row ) {
		$r = array();
		foreach ( (array) $subs as $s ) {
			$sv = isset( $row[ $s['name'] ] ) ? $row[ $s['name'] ] : '';
			if ( in_array( $s['type'], array( 'image', 'file' ), true ) ) {
				$r[ $s['name'] ] = (int) $sv;
			} elseif ( 'textarea' === $s['type'] ) {
				$r[ $s['name'] ] = sanitize_textarea_field( is_array( $sv ) ? '' : $sv );
			} else {
				$r[ $s['name'] ] = sanitize_text_field( is_array( $sv ) ? '' : $sv );
			}
		}
		return $r;
	}

	/* ------------------------------------------------------------------ *
	 * Front-end API
	 * ------------------------------------------------------------------ */

	/** get_field()-style retrieval. Pass 'option' as $post_id for options-page values. */
	private static $field_cfg_cache = null;

	/** Resolve a field's saved config by its meta name (first match across all groups). */
	public static function field_config( $name ) {
		if ( null === self::$field_cfg_cache ) {
			self::$field_cfg_cache = array();
			foreach ( self::all() as $group ) {
				foreach ( (array) ( $group['fields'] ?? array() ) as $f ) {
					if ( ! empty( $f['name'] ) && ! isset( self::$field_cfg_cache[ $f['name'] ] ) ) {
						self::$field_cfg_cache[ $f['name'] ] = $f;
					}
				}
			}
		}
		return isset( self::$field_cfg_cache[ $name ] ) ? self::$field_cfg_cache[ $name ] : null;
	}

	/** Apply a field's return format to a stored value. No-op unless a return format is set. */
	private static function format_value( $value, $name ) {
		$cfg = self::field_config( $name );
		if ( ! is_array( $cfg ) || empty( $cfg['type'] ) ) { return $value; }
		$type = $cfg['type'];
		$rf   = isset( $cfg['return_format'] ) ? (string) $cfg['return_format'] : '';
		if ( ( 'image' === $type || 'file' === $type ) && in_array( $rf, array( 'id', 'url', 'array' ), true ) ) {
			$id = (int) $value;
			if ( ! $id ) { return 'array' === $rf ? false : ''; }
			if ( 'url' === $rf ) { return wp_get_attachment_url( $id ); }
			if ( 'array' === $rf ) {
				return array(
					'ID'    => $id,
					'id'    => $id,
					'url'   => wp_get_attachment_url( $id ),
					'title' => get_the_title( $id ),
					'alt'   => get_post_meta( $id, '_wp_attachment_image_alt', true ),
					'mime'  => get_post_mime_type( $id ),
				);
			}
			return $id;
		}
		if ( in_array( $type, array( 'date', 'datetime', 'time' ), true ) && '' !== $rf && '' !== (string) $value ) {
			$ts = strtotime( (string) $value );
			if ( $ts ) { return date_i18n( $rf, $ts ); }
		}
		return $value;
	}

	public static function get_field( $name, $post_id = null ) {
		if ( 'option' === $post_id || 'options' === $post_id ) {
			$store = get_option( self::OPT_STORE, array() );
			$raw   = ( is_array( $store ) && isset( $store[ $name ] ) ) ? $store[ $name ] : '';
			return self::format_value( $raw, $name );
		}
		if ( null === $post_id ) { $post_id = get_the_ID(); }
		return self::format_value( get_post_meta( $post_id, $name, true ), $name );
	}

	/* ---- Repeater loop API (single-level), mirrors ACF have_rows()/the_row()/get_sub_field() ---- */
	private static $loops = array();
	private static $current_row = null;

	/** Begin/advance a repeater loop. Returns true while rows remain. */
	public static function have_rows( $name, $post_id = null ) {
		if ( null === $post_id ) { $post_id = get_the_ID(); }
		$key = $post_id . '|' . $name;
		if ( ! isset( self::$loops[ $key ] ) ) {
			$rows = self::get_field( $name, $post_id );
			self::$loops[ $key ] = array( 'rows' => is_array( $rows ) ? array_values( $rows ) : array(), 'i' => -1 );
		}
		$loop = &self::$loops[ $key ];
		if ( $loop['i'] + 1 < count( $loop['rows'] ) ) {
			$loop['i']++;
			self::$current_row = $loop['rows'][ $loop['i'] ];
			return true;
		}
		unset( self::$loops[ $key ] );
		self::$current_row = null;
		return false;
	}

	/** The current row (assoc array of sub-field name => value). */
	public static function the_row() {
		return self::$current_row;
	}

	/** Value of a sub-field in the current repeater/flexible row. */
	public static function get_sub_field( $name ) {
		return ( is_array( self::$current_row ) && isset( self::$current_row[ $name ] ) ) ? self::$current_row[ $name ] : '';
	}

	/** Layout name of the current flexible-content row (empty for repeaters). */
	public static function get_row_layout() {
		return ( is_array( self::$current_row ) && isset( self::$current_row['_layout'] ) ) ? self::$current_row['_layout'] : '';
	}

	/** Replace {field:name} merge tags in a string for a given post. */
	public static function apply_merge_tags( $content, $post_id = null ) {
		if ( null === $post_id ) { $post_id = get_the_ID(); }
		return preg_replace_callback( '/\{field:([a-z0-9_]+)\}/i', function ( $m ) use ( $post_id ) {
			$v = self::get_field( $m[1], $post_id );
			return is_array( $v ) ? implode( ', ', $v ) : (string) $v;
		}, $content );
	}
}
