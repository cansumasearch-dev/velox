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

	/** Supported field types and their metadata (label, icon hint, has-options). */
	public static function types() {
		return array(
			'text'         => array( 'label' => 'Text',            'opts' => false ),
			'textarea'     => array( 'label' => 'Text area',       'opts' => false ),
			'number'       => array( 'label' => 'Number',          'opts' => false ),
			'email'        => array( 'label' => 'Email',           'opts' => false ),
			'url'          => array( 'label' => 'URL',             'opts' => false ),
			'select'       => array( 'label' => 'Select',          'opts' => true ),
			'checkbox'     => array( 'label' => 'Checkbox',        'opts' => true ),
			'radio'        => array( 'label' => 'Radio',           'opts' => true ),
			'truefalse'    => array( 'label' => 'True / False',    'opts' => false ),
			'image'        => array( 'label' => 'Image',           'opts' => false ),
			'file'         => array( 'label' => 'File',            'opts' => false ),
			'wysiwyg'      => array( 'label' => 'WYSIWYG editor',  'opts' => false ),
			'date'         => array( 'label' => 'Date picker',     'opts' => false ),
			'color'        => array( 'label' => 'Color picker',    'opts' => false ),
			'relationship' => array( 'label' => 'Relationship',    'opts' => false ),
			'repeater'     => array( 'label' => 'Repeater',        'opts' => false ),
			'group'        => array( 'label' => 'Group',           'opts' => false ),
		);
	}

	/** Location rule parameters. */
	public static function location_params() {
		return array(
			'post_type'   => 'Post type',
			'post_status' => 'Post status',
			'page_template' => 'Page template',
			'taxonomy'    => 'Taxonomy',
			'user_role'   => 'User role',
		);
	}

	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'register_meta_boxes' ) );
		add_action( 'save_post', array( __CLASS__, 'save_post_meta' ), 10, 2 );
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
			$out['fields'][] = array(
				'key'          => sanitize_key( $f['key'] ?? ( 'field_' . $name ) ),
				'label'        => $label,
				'name'         => $name,
				'type'         => $type,
				'required'     => ! empty( $f['required'] ),
				'instructions' => sanitize_text_field( $f['instructions'] ?? '' ),
				'default'      => sanitize_text_field( $f['default'] ?? '' ),
				'options'      => sanitize_textarea_field( $f['options'] ?? '' ),
				'placeholder'  => sanitize_text_field( $f['placeholder'] ?? '' ),
			);
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
			$value = get_post_meta( $post->ID, $f['name'], true );
			if ( '' === $value && '' !== ( $f['default'] ?? '' ) ) { $value = $f['default']; }
			echo '<div class="velox-fields-row">';
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
		echo '</div>';
	}

	private static function render_field_input( $f, $value ) {
		$name = 'velox_field[' . esc_attr( $f['name'] ) . ']';
		$id   = 'velox_field_' . esc_attr( $f['name'] );
		$ph   = esc_attr( $f['placeholder'] ?? '' );
		$opts = array_filter( array_map( 'trim', explode( "\n", (string) ( $f['options'] ?? '' ) ) ) );
		switch ( $f['type'] ) {
			case 'textarea':
			case 'wysiwyg':
				echo '<textarea id="' . $id . '" name="' . $name . '" rows="5" class="widefat" placeholder="' . $ph . '">' . esc_textarea( $value ) . '</textarea>';
				break;
			case 'select':
				echo '<select id="' . $id . '" name="' . $name . '" class="widefat">';
				echo '<option value="">— Select —</option>';
				foreach ( $opts as $o ) {
					echo '<option value="' . esc_attr( $o ) . '"' . selected( $value, $o, false ) . '>' . esc_html( $o ) . '</option>';
				}
				echo '</select>';
				break;
			case 'radio':
				foreach ( $opts as $o ) {
					echo '<label class="velox-fields-choice"><input type="radio" name="' . $name . '" value="' . esc_attr( $o ) . '"' . checked( $value, $o, false ) . '> ' . esc_html( $o ) . '</label>';
				}
				break;
			case 'checkbox':
				$vals = is_array( $value ) ? $value : array_filter( array_map( 'trim', explode( ',', (string) $value ) ) );
				foreach ( $opts as $o ) {
					echo '<label class="velox-fields-choice"><input type="checkbox" name="' . $name . '[]" value="' . esc_attr( $o ) . '"' . ( in_array( $o, $vals, true ) ? ' checked' : '' ) . '> ' . esc_html( $o ) . '</label>';
				}
				break;
			case 'truefalse':
				echo '<label class="velox-fields-choice"><input type="checkbox" name="' . $name . '" value="1"' . checked( $value, '1', false ) . '> ' . esc_html( $f['label'] ) . '</label>';
				break;
			case 'number':
				echo '<input type="number" id="' . $id . '" name="' . $name . '" class="widefat" value="' . esc_attr( $value ) . '" placeholder="' . $ph . '">';
				break;
			case 'email':
				echo '<input type="email" id="' . $id . '" name="' . $name . '" class="widefat" value="' . esc_attr( $value ) . '" placeholder="' . $ph . '">';
				break;
			case 'url':
				echo '<input type="url" id="' . $id . '" name="' . $name . '" class="widefat" value="' . esc_attr( $value ) . '" placeholder="' . $ph . '">';
				break;
			case 'date':
				echo '<input type="date" id="' . $id . '" name="' . $name . '" class="widefat" value="' . esc_attr( $value ) . '">';
				break;
			case 'color':
				echo '<input type="color" id="' . $id . '" name="' . $name . '" value="' . esc_attr( $value ? $value : '#000000' ) . '">';
				break;
			case 'image':
			case 'file':
				echo '<input type="text" id="' . $id . '" name="' . $name . '" class="widefat" value="' . esc_attr( $value ) . '" placeholder="' . esc_attr__( 'Attachment ID or URL' ) . '">';
				break;
			default: // text, and any not-yet-specialised type
				echo '<input type="text" id="' . $id . '" name="' . $name . '" class="widefat" value="' . esc_attr( $value ) . '" placeholder="' . $ph . '">';
		}
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
				$name = $f['name'];
				if ( 'checkbox' === $f['type'] ) {
					$val = isset( $submitted[ $name ] ) ? array_map( 'sanitize_text_field', (array) $submitted[ $name ] ) : array();
				} elseif ( in_array( $f['type'], array( 'textarea', 'wysiwyg' ), true ) ) {
					$val = isset( $submitted[ $name ] ) ? sanitize_textarea_field( $submitted[ $name ] ) : '';
				} else {
					$val = isset( $submitted[ $name ] ) ? sanitize_text_field( $submitted[ $name ] ) : '';
				}
				update_post_meta( $post_id, $name, $val );
			}
		}
	}

	/* ------------------------------------------------------------------ *
	 * Front-end API
	 * ------------------------------------------------------------------ */

	/** get_field()-style retrieval. */
	public static function get_field( $name, $post_id = null ) {
		if ( null === $post_id ) { $post_id = get_the_ID(); }
		return get_post_meta( $post_id, $name, true );
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
