<?php
/**
 * Velox Builder — AI agents.
 *
 * Registers what the builder can do as WordPress abilities (the Abilities API,
 * WordPress 6.9+). The official MCP Adapter plugin turns them into MCP tools,
 * so Claude, ChatGPT, Cursor and other AI tools can read the design system and
 * build pages.
 *
 * Rules an agent cannot get around:
 *  - It is off until an administrator switches it on (Velox Builder → AI agents).
 *  - It acts as the WordPress user it signed in as (an application password),
 *    with that user's builder rights: what that user may not do, it may not do.
 *  - It only writes drafts. Publishing stays with a person.
 *  - Every change is a saved version marked "By AI", one click to undo.
 *
 * @package Velox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Velox_Builder_Agents {

	const OPT      = 'velox_builder_agents';
	const CATEGORY = 'velox-builder';
	const MAX_NODES = 600;

	/**
	 * What a connector (Claude with the person's own account) may work on:
	 * null outside a connector call, 'all', or a list of document ids.
	 */
	public static $scope = null;

	/** Element types an agent can create: the editor's basic elements, same defaults. */
	const TYPES = array(
		'section'   => array( 'el' => 'Section', 'tag' => 'section', 'cls' => '.section', 'rules' => array( 'paddingTop' => '48', 'paddingBottom' => '48', 'paddingLeft' => '32', 'paddingRight' => '32' ), 'box' => 1, 'tags' => array( 'section', 'header', 'footer', 'main', 'article', 'aside', 'nav', 'div' ) ),
		'div'       => array( 'el' => 'Div', 'tag' => 'div', 'cls' => '.block', 'rules' => array(), 'box' => 1, 'tags' => array( 'div', 'section', 'article', 'aside', 'nav', 'header', 'footer', 'figure', 'ul', 'ol', 'li' ) ),
		'columns'   => array( 'el' => 'Columns', 'tag' => 'div', 'cls' => '.columns', 'rules' => array( 'display' => 'grid', 'gridTemplateColumns' => '1fr 1fr', 'gap' => '20' ), 'box' => 1 ),
		'grid'      => array( 'el' => 'Div', 'tag' => 'div', 'cls' => '.grid', 'rules' => array( 'display' => 'grid', 'gridTemplateColumns' => '1fr 1fr 1fr', 'gap' => '20' ), 'box' => 1 ),
		'spacer'    => array( 'el' => 'Div', 'tag' => 'div', 'cls' => '.spacer', 'rules' => array( 'height' => '40' ) ),
		'divider'   => array( 'el' => 'Div', 'tag' => 'div', 'cls' => '.divider', 'rules' => array( 'height' => '1', 'background' => '#e2e8f0' ) ),
		'heading'   => array( 'el' => 'Heading', 'tag' => 'h2', 'cls' => '.heading', 'rules' => array( 'fontSize' => '32', 'fontWeight' => '700' ), 'text' => 1, 'tags' => array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ) ),
		'text'      => array( 'el' => 'Text', 'tag' => 'p', 'cls' => '.text', 'rules' => array( 'fontSize' => '16' ), 'text' => 1, 'tags' => array( 'p', 'span', 'div', 'blockquote', 'small' ) ),
		'list'      => array( 'el' => 'Text', 'tag' => 'ul', 'cls' => '.list', 'rules' => array( 'fontSize' => '16' ), 'text' => 1, 'tags' => array( 'ul', 'ol' ) ),
		'button'    => array( 'el' => 'Button', 'tag' => 'a', 'cls' => '.btn', 'rules' => array( 'display' => 'inline-block', 'paddingTop' => '12', 'paddingBottom' => '12', 'paddingLeft' => '20', 'paddingRight' => '20', 'borderRadius' => '8', 'background' => '#2ab7f1', 'color' => '#04222f', 'textDecoration' => 'none' ), 'text' => 1, 'link' => 1 ),
		'link'      => array( 'el' => 'Button', 'tag' => 'a', 'cls' => '.link', 'rules' => array( 'color' => '#2ab7f1' ), 'text' => 1, 'link' => 1 ),
		'image'     => array( 'el' => 'Image', 'tag' => 'div', 'cls' => '.image', 'rules' => array() ),
		'video'     => array( 'el' => 'Video', 'tag' => 'div', 'cls' => '.video', 'rules' => array( 'borderRadius' => '12' ) ),
		'icon'      => array( 'el' => 'Icon', 'tag' => 'span', 'cls' => '.icon', 'rules' => array( 'color' => '#2ab7f1' ) ),
		'form'      => array( 'el' => 'Form', 'tag' => 'div', 'cls' => '.form-wrap', 'rules' => array() ),
		'component' => array( 'el' => 'Reusable', 'tag' => 'div', 'cls' => '', 'rules' => array() ),
	);

	/** Settings each type accepts from an agent, and how they are cleaned. */
	const SETTINGS = array(
		'image' => array( 'imgSize' => array( 'medium', 'medium_large', 'large', 'full' ), 'loading' => array( '', 'eager', 'lazy' ), 'caption' => 'text' ),
		'video' => array( 'url' => 'url', 'title' => 'text', 'autoplay' => 'bool' ),
		'icon'  => array( 'icon' => 'key', 'size' => 'num', 'label' => 'text' ),
		'form'  => array( 'form' => 'int' ),
	);

	public static function init() {
		add_action( 'wp_abilities_api_categories_init', array( __CLASS__, 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( __CLASS__, 'register' ) );
	}

	public static function enabled() {
		return (bool) get_option( self::OPT, 0 );
	}

	/** Is the Abilities API there (WordPress 6.9+)? */
	public static function supported() {
		return function_exists( 'wp_register_ability' );
	}

	public static function register_category() {
		if ( ! self::enabled() || ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}
		wp_register_ability_category( self::CATEGORY, array(
			'label'       => __( 'Velox Builder', 'velox' ),
			'description' => __( 'Read the design system and build pages, templates and components with Velox Builder. Changes are saved as drafts.', 'velox' ),
		) );
	}

	/** All abilities, for registration and for the AI agents screen. */
	public static function abilities() {
		$doc_id = array( 'type' => 'integer', 'description' => 'The id of a page, template or component (from velox/list-documents).', 'minimum' => 1 );
		$el_doc = 'Element spec: {"type": one of ' . implode( ', ', array_keys( self::TYPES ) ) . ', "tag"?, "text"? (HTML for heading/text/list/button/link; list = <li> items), "href"? (button/link), "src"? / "image_id"? / "alt"? (image), "settings"? (video: url,title,autoplay; icon: icon,size,label; form: form id; image: imgSize,loading,caption), "component_id"? (component), "classes"? ([".class-name"]), "styles"? ({"fontSize":"48px"} or per screen size {"base":{...},"md":{...},"base:hover":{...}}; camelCase or CSS names; use design tokens as var(--name)), "name"? (label in the editor), "children"? (section/div/columns/grid only)}.';
		return array(
			'velox/get-design-system' => array(
				'label'       => __( 'Get the design system', 'velox' ),
				'description' => 'Read this site\'s Velox Builder design system before building: design tokens (use them as var(--name)), the site-wide classes and their styles, global styles, fonts, screen sizes, the element types you can create and the style properties you can set. Call this first.',
				'input'       => array( 'type' => 'object', 'properties' => array( 'include_class_rules' => array( 'type' => 'boolean', 'description' => 'Also return every class\'s style rules (longer).', 'default' => false ) ), 'default' => array() ),
				'run'         => array( __CLASS__, 'run_design_system' ),
				'perm'        => 'read',
				'ann'         => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ),
			),
			'velox/list-documents' => array(
				'label'       => __( 'List pages, templates and components', 'velox' ),
				'description' => 'List the pages, templates and components (reusable blocks) built with Velox Builder, with their ids, status and addresses.',
				'input'       => array( 'type' => 'object', 'properties' => array( 'kind' => array( 'type' => 'string', 'enum' => array( 'page', 'template', 'reusable', 'all' ), 'default' => 'all' ), 'search' => array( 'type' => 'string', 'description' => 'Only titles containing this text.' ) ), 'default' => array() ),
				'run'         => array( __CLASS__, 'run_list' ),
				'perm'        => 'read',
				'ann'         => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ),
			),
			'velox/get-document' => array(
				'label'       => __( 'Read a page, template or component', 'velox' ),
				'description' => 'Read one document as a tree of elements: id, type, tag, classes, text, links, images, settings and element styles. Shows the newest saved draft.',
				'input'       => array( 'type' => 'object', 'properties' => array( 'document_id' => $doc_id ), 'required' => array( 'document_id' ) ),
				'run'         => array( __CLASS__, 'run_get' ),
				'perm'        => 'read_doc',
				'ann'         => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ),
			),
			'velox/create-document' => array(
				'label'       => __( 'Create a page, template or component', 'velox' ),
				'description' => 'Create a new draft page (or, for administrators, a template or component), optionally with its content. ' . $el_doc . ' Returns the new id, the editor link and the preview address. Nothing is published.',
				'input'       => array( 'type' => 'object', 'properties' => array(
					'title'    => array( 'type' => 'string', 'minLength' => 1, 'maxLength' => 190 ),
					'kind'     => array( 'type' => 'string', 'enum' => array( 'page', 'template', 'reusable' ), 'default' => 'page' ),
					'elements' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ), 'description' => 'Top-level elements, usually sections.' ),
				), 'required' => array( 'title' ) ),
				'run'         => array( __CLASS__, 'run_create' ),
				'perm'        => 'create',
				'ann'         => array( 'readonly' => false, 'destructive' => false, 'idempotent' => false ),
			),
			'velox/add-elements' => array(
				'label'       => __( 'Add elements', 'velox' ),
				'description' => 'Add elements (with children) to a document\'s draft: at the top level, or inside a section, div, columns or grid. ' . $el_doc,
				'input'       => array( 'type' => 'object', 'properties' => array(
					'document_id' => $doc_id,
					'parent_id'   => array( 'type' => 'string', 'description' => 'Add inside this element. Leave out for the top level.' ),
					'position'    => array( 'type' => 'integer', 'minimum' => 0, 'description' => 'Index among the parent\'s children. Leave out to add at the end.' ),
					'elements'    => array( 'type' => 'array', 'items' => array( 'type' => 'object' ), 'minItems' => 1 ),
				), 'required' => array( 'document_id', 'elements' ) ),
				'run'         => array( __CLASS__, 'run_add' ),
				'perm'        => 'write',
				'ann'         => array( 'readonly' => false, 'destructive' => false, 'idempotent' => false ),
			),
			'velox/update-element' => array(
				'label'       => __( 'Change an element', 'velox' ),
				'description' => 'Change one element in a document\'s draft: its text, link, image, alt text, tag, name, settings, classes (add_classes / remove_classes) or its own styles ("styles" are merged; set a property to "" to remove it).',
				'input'       => array( 'type' => 'object', 'properties' => array(
					'document_id'    => $doc_id,
					'element_id'     => array( 'type' => 'string' ),
					'text'           => array( 'type' => 'string' ),
					'href'           => array( 'type' => 'string' ),
					'src'            => array( 'type' => 'string' ),
					'image_id'       => array( 'type' => 'integer' ),
					'alt'            => array( 'type' => 'string' ),
					'tag'            => array( 'type' => 'string' ),
					'name'           => array( 'type' => 'string' ),
					'settings'       => array( 'type' => 'object' ),
					'add_classes'    => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
					'remove_classes' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
					'styles'         => array( 'type' => 'object' ),
				), 'required' => array( 'document_id', 'element_id' ) ),
				'run'         => array( __CLASS__, 'run_update' ),
				'perm'        => 'write',
				'ann'         => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ),
			),
			'velox/remove-element' => array(
				'label'       => __( 'Remove an element', 'velox' ),
				'description' => 'Remove an element (and everything inside it) from a document\'s draft. It can be brought back from the saved versions.',
				'input'       => array( 'type' => 'object', 'properties' => array( 'document_id' => $doc_id, 'element_id' => array( 'type' => 'string' ) ), 'required' => array( 'document_id', 'element_id' ) ),
				'run'         => array( __CLASS__, 'run_remove' ),
				'perm'        => 'write',
				'ann'         => array( 'readonly' => false, 'destructive' => true, 'idempotent' => true ),
			),
			'velox/set-class-styles' => array(
				'label'       => __( 'Style a class', 'velox' ),
				'description' => 'Set the styles of a class in a document (merged; "" removes a property). Like in the editor, the change applies to every element with that class, and becomes the site-wide version when the document is published.',
				'input'       => array( 'type' => 'object', 'properties' => array(
					'document_id' => $doc_id,
					'class'       => array( 'type' => 'string', 'description' => 'For example ".hero-title".' ),
					'styles'      => array( 'type' => 'object', 'description' => '{"fontSize":"48px"} or per screen size {"base":{...},"md":{...},"base:hover":{...}}.' ),
				), 'required' => array( 'document_id', 'class', 'styles' ) ),
				'run'         => array( __CLASS__, 'run_class' ),
				'perm'        => 'write',
				'ann'         => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ),
			),
			'velox/find-images' => array(
				'label'       => __( 'Find images in the media library', 'velox' ),
				'description' => 'Search the media library of this site for images by title, file name, alt text or caption. Returns ids, addresses, sizes and alt texts. Use the id as image_id for image elements; never make up image addresses.',
				'input'       => array( 'type' => 'object', 'properties' => array(
					'search' => array( 'type' => 'string', 'description' => 'Words to look for. Leave out for the newest images.' ),
					'limit'  => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 40, 'default' => 20 ),
				) ),
				'run'         => array( __CLASS__, 'run_images' ),
				'perm'        => 'read',
				'ann'         => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ),
			),
			'velox/get-site-info' => array(
				'label'       => __( 'About this site', 'velox' ),
				'description' => 'The site\'s name, address and language, who you act as, and which documents you may change in this connection. Call this first.',
				'input'       => array( 'type' => 'object', 'properties' => array(), 'default' => array() ),
				'run'         => array( __CLASS__, 'run_site' ),
				'perm'        => 'read',
				'ann'         => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ),
				'only'        => 'connector',
			),
			'velox/get-open-document' => array(
				'label'       => __( 'The page open in the editor', 'velox' ),
				'description' => 'Which page, template or component the person has open in the Velox Builder editor right now. Use it when they say "this page" or "the page I have open".',
				'input'       => array( 'type' => 'object', 'properties' => array(), 'default' => array() ),
				'run'         => array( __CLASS__, 'run_open' ),
				'perm'        => 'read',
				'ann'         => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ),
				'only'        => 'connector',
			),
		);
	}

	/** Is this document within the connection's pages? (Always yes outside a connector.) */
	public static function in_scope( $doc ) {
		return null === self::$scope || 'all' === self::$scope || in_array( (int) $doc, array_map( 'intval', (array) self::$scope ), true );
	}

	public static function run_site( $in ) {
		$u    = wp_get_current_user();
		$docs = array();
		if ( is_array( self::$scope ) ) {
			foreach ( self::docs() as $d ) {
				if ( self::in_scope( (int) $d['id'] ) ) {
					$docs[] = array( 'id' => (int) $d['id'], 'kind' => $d['kind'], 'title' => $d['title'] );
				}
			}
		}
		return array(
			'name'        => get_bloginfo( 'name' ),
			'tagline'     => get_bloginfo( 'description' ),
			'url'         => home_url( '/' ),
			'language'    => get_locale(),
			'you_act_as'  => array( 'name' => $u->display_name, 'role' => class_exists( 'Velox_Builder_Roles' ) ? Velox_Builder_Roles::level() : '' ),
			'may_change'  => is_array( self::$scope ) ? $docs : 'every document this person may edit; new draft pages too',
			'rules'       => 'Everything you change is a draft and a saved version marked "By AI". Publishing is done by the person in the editor. Write texts in the site\'s language.',
			'velox'       => defined( 'VELOX_VERSION' ) ? VELOX_VERSION : '',
		);
	}

	public static function run_open( $in ) {
		$o = get_user_meta( get_current_user_id(), 'velox_open_doc', true );
		if ( ! is_array( $o ) || empty( $o['id'] ) || time() - (int) $o['t'] > 2 * HOUR_IN_SECONDS ) {
			return array( 'document' => null, 'note' => 'No page is open in the editor right now. Ask which page is meant, or use velox_list_documents.' );
		}
		$id = (int) $o['id'];
		if ( ! self::in_scope( $id ) ) {
			return array( 'document' => null, 'note' => 'The page open in the editor (#' . $id . ') is not among the documents of this connection. The person can change that under Velox Builder → AI agents.' );
		}
		$doc = self::load( $id );
		if ( is_wp_error( $doc ) ) {
			return array( 'document' => null, 'note' => $doc->get_error_message() );
		}
		return array(
			'document' => array( 'id' => $id, 'kind' => $doc['kind'], 'title' => $doc['title'], 'status' => $doc['status'], 'edit' => Velox_Builder::edit_url( $id, 'page' === $doc['kind'] ? '' : $doc['kind'] ), 'preview' => self::preview( $doc ) ),
			'open_since' => human_time_diff( (int) $o['t'] ) . ' ago',
			'note'       => 'Read it with velox_get_document.',
		);
	}

	/**
	 * The abilities as tools for the built-in assistant (Claude's tool format).
	 * Tool names may not contain "/", so velox/add-elements becomes velox_add_elements.
	 */
	public static function tool_defs() {
		$out = array();
		foreach ( self::abilities() as $name => $a ) {
			if ( 'connector' === ( $a['only'] ?? '' ) ) {
				continue;
			}
			$schema = $a['input'];
			unset( $schema['default'] );
			if ( empty( $schema['properties'] ) ) {
				$schema['properties'] = new stdClass();
			}
			$out[] = array( 'name' => self::tool_name( $name ), 'description' => $a['description'], 'input_schema' => $schema );
		}
		return $out;
	}

	public static function tool_name( $ability ) {
		return str_replace( array( '/', '-' ), '_', $ability );
	}

	/** Ability for a tool name, or ''. */
	public static function ability_for( $tool ) {
		foreach ( array_keys( self::abilities() ) as $name ) {
			if ( self::tool_name( $name ) === $tool ) {
				return $name;
			}
		}
		return '';
	}

	/**
	 * Run an ability for the built-in assistant: the same permission rules as
	 * for outside tools, without the outside-tools switch.
	 *
	 * @return array|WP_Error
	 */
	public static function run_tool( $ability, $input, $via = 'assistant' ) {
		$all = self::abilities();
		if ( ! isset( $all[ $ability ] ) ) {
			return new WP_Error( 'velox_tool', 'Unknown tool.' );
		}
		$input = is_array( $input ) ? $input : array();
		if ( 'connector' === $via && is_array( self::$scope ) ) {
			$perm = $all[ $ability ]['perm'];
			if ( 'create' === $perm ) {
				return new WP_Error( 'velox_scope', 'This connection may only change the pages the person chose, so it cannot create new ones. They can allow the whole site under Velox Builder → AI agents → Connected apps.' );
			}
			if ( in_array( $perm, array( 'read_doc', 'write' ), true ) && ! self::in_scope( (int) ( $input['document_id'] ?? 0 ) ) ) {
				return new WP_Error( 'velox_scope', 'That document is not among the pages of this connection (see velox_get_site_info). The person can add it under Velox Builder → AI agents → Connected apps.' );
			}
		}
		if ( ! self::allowed( $all[ $ability ]['perm'], $input, $via ) ) {
			return new WP_Error( 'velox_denied', __( 'You may not do that with your role, or on this document.', 'velox' ) );
		}
		foreach ( (array) ( $all[ $ability ]['input']['required'] ?? array() ) as $req ) {
			if ( ! isset( $input[ $req ] ) ) {
				return new WP_Error( 'velox_input', sprintf( 'Missing "%s".', $req ) );
			}
		}
		return call_user_func( $all[ $ability ]['run'], $input );
	}

	public static function register() {
		if ( ! self::enabled() || ! self::supported() ) {
			return;
		}
		foreach ( self::abilities() as $name => $a ) {
			if ( ! empty( $a['only'] ) ) {
				continue;
			}
			$perm = $a['perm'];
			wp_register_ability( $name, array(
				'label'               => $a['label'],
				'description'         => $a['description'],
				'category'            => self::CATEGORY,
				'input_schema'        => $a['input'],
				'output_schema'       => array( 'type' => 'object' ),
				'execute_callback'    => function ( $input = array() ) use ( $a ) {
					return call_user_func( $a['run'], is_array( $input ) ? $input : array() );
				},
				'permission_callback' => function ( $input = array() ) use ( $perm ) {
					return self::allowed( $perm, is_array( $input ) ? $input : array() );
				},
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => $a['ann'],
					'mcp'          => array( 'public' => true ),
				),
			) );
		}
	}

	/* ========================================================== permissions */

	public static function allowed( $perm, $input, $via = 'mcp' ) {
		// Outside tools need the switch on this screen; the built-in assistant
		// has its own setting (an API key) and is checked before it runs.
		if ( ( in_array( $via, array( 'mcp', 'connector' ), true ) && ! self::enabled() ) || ! current_user_can( 'velox_builder' ) ) {
			return false;
		}
		$doc = (int) ( $input['document_id'] ?? 0 );
		$can_doc = function () use ( $doc ) {
			return $doc && class_exists( 'Velox_Builder_Roles' ) ? Velox_Builder_Roles::can_edit_doc( $doc ) : current_user_can( 'manage_options' );
		};
		switch ( $perm ) {
			case 'read':
				return true;
			case 'read_doc':
				return $can_doc();
			case 'create':
				$kind = (string) ( $input['kind'] ?? 'page' );
				return 'page' === $kind ? ( current_user_can( 'velox_builder_design' ) && current_user_can( 'edit_pages' ) ) : current_user_can( 'manage_options' );
			case 'write':
				// Layout and styles: designers and administrators, on documents they may edit.
				return current_user_can( 'velox_builder_design' ) && $can_doc();
		}
		return false;
	}

	/* ============================================================ read side */

	public static function run_design_system( $in ) {
		$reg   = Velox_Builder::class_registry();
		$rules = ! empty( $in['include_class_rules'] );
		$classes = array();
		foreach ( $reg as $name => $by ) {
			$classes[ $name ] = $rules ? $by : array_keys( (array) $by );
		}
		$tokens = array();
		foreach ( (array) Velox_Builder::token_list() as $t ) {
			if ( is_array( $t ) && ! empty( $t['name'] ) ) {
				$tokens[] = array( 'var' => 'var(--' . ltrim( (string) $t['name'], '-' ) . ')', 'value' => (string) ( $t['value'] ?? '' ), 'group' => (string) ( $t['group'] ?? '' ) );
			}
		}
		$types = array();
		foreach ( self::TYPES as $k => $t ) {
			$types[ $k ] = array( 'default_class' => $t['cls'], 'tag' => $t['tag'], 'tags' => $t['tags'] ?? array( $t['tag'] ), 'holds_children' => ! empty( $t['box'] ), 'has_text' => ! empty( $t['text'] ), 'settings' => array_keys( self::SETTINGS[ $k ] ?? array() ) );
		}
		$forms = array();
		if ( class_exists( 'Velox_Forms' ) ) {
			foreach ( Velox_Forms::forms() as $id => $f ) {
				$forms[] = array( 'id' => (int) $id, 'title' => (string) ( $f['title'] ?? '' ) );
			}
		}
		return array(
			'how_to'        => 'Build with sections at the top level and divs, columns or grids inside. Prefer the site\'s classes and design tokens over fixed values, so the page matches the rest of the site. Reuse a class for repeated elements (cards, buttons) instead of styling each element. Screen sizes: base (all), xxl, xl, lg (tablet), md (phone landscape), sm (phone); a value on a smaller size overrides the larger. States: add ":hover", ":focus" or ":active" to a size, e.g. "base:hover". Everything you do is saved as a draft; tell the person to review and publish it.',
			'tokens'        => $tokens,
			'classes'       => (object) $classes,
			'global_styles' => Velox_Builder::global_styles(),
			'fonts'         => array_values( array_map( function ( $f ) { return (string) ( $f['name'] ?? '' ); }, (array) get_option( Velox_Builder::OPT_FONTS, array() ) ) ),
			'screen_sizes'  => array( 'base', 'xxl', 'xl', 'lg', 'md', 'sm' ),
			'element_types' => $types,
			'style_properties' => Velox_Builder_Render::css_props(),
			'forms'         => $forms,
			'components'    => array_map( function ( $d ) { return array( 'id' => (int) $d['id'], 'title' => $d['title'] ); }, array_values( array_filter( self::docs(), function ( $d ) { return 'reusable' === $d['kind']; } ) ) ),
		);
	}

	public static function run_images( $in ) {
		$args = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'post_mime_type' => 'image',
			'posts_per_page' => max( 1, min( 40, (int) ( $in['limit'] ?? 20 ) ) ),
			'orderby'        => 'date',
			'order'          => 'DESC',
		);
		$q = trim( sanitize_text_field( (string) ( $in['search'] ?? '' ) ) );
		if ( '' !== $q ) {
			$args['s'] = $q;
		}
		$out = array();
		foreach ( get_posts( $args ) as $a ) {
			$src  = wp_get_attachment_image_src( $a->ID, 'large' );
			$meta = wp_get_attachment_metadata( $a->ID );
			$out[] = array(
				'image_id' => (int) $a->ID,
				'title'    => get_the_title( $a ),
				'alt'      => (string) get_post_meta( $a->ID, '_wp_attachment_image_alt', true ),
				'caption'  => wp_strip_all_tags( (string) $a->post_excerpt ),
				'url'      => $src ? $src[0] : wp_get_attachment_url( $a->ID ),
				'width'    => (int) ( $meta['width'] ?? 0 ),
				'height'   => (int) ( $meta['height'] ?? 0 ),
			);
		}
		return array( 'images' => $out, 'note' => $out ? '' : 'No images found. Use a placeholder section without an image and tell the person to add one.' );
	}

	public static function docs( $kind = 'all', $search = '' ) {
		global $wpdb;
		$t   = Velox_Builder::table();
		$sql = "SELECT id, kind, title, status, post_id, updated, draft IS NOT NULL AND draft <> '' AS has_draft FROM {$t} WHERE kind IN ('page','template','reusable')";
		if ( 'all' !== $kind ) {
			$sql .= $wpdb->prepare( ' AND kind = %s', $kind );
		}
		if ( '' !== $search ) {
			$sql .= $wpdb->prepare( ' AND title LIKE %s', '%' . $wpdb->esc_like( $search ) . '%' );
		}
		$rows = $wpdb->get_results( $sql . ' ORDER BY kind, title LIMIT 500', ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return is_array( $rows ) ? $rows : array();
	}

	public static function run_list( $in ) {
		$kind = in_array( $in['kind'] ?? 'all', array( 'page', 'template', 'reusable', 'all' ), true ) ? ( $in['kind'] ?? 'all' ) : 'all';
		$out  = array();
		foreach ( self::docs( $kind, sanitize_text_field( (string) ( $in['search'] ?? '' ) ) ) as $d ) {
			if ( ( class_exists( 'Velox_Builder_Roles' ) && ! Velox_Builder_Roles::can_edit_doc( (int) $d['id'] ) ) || ! self::in_scope( (int) $d['id'] ) ) {
				continue;
			}
			$out[] = array(
				'id'      => (int) $d['id'],
				'kind'    => $d['kind'],
				'title'   => $d['title'],
				'status'  => 'published' === $d['status'] ? ( $d['has_draft'] ? 'published, with unpublished changes' : 'published' ) : 'draft',
				'url'     => ( 'page' === $d['kind'] && (int) $d['post_id'] ) ? get_permalink( (int) $d['post_id'] ) : '',
				'edit'    => Velox_Builder::edit_url( (int) $d['id'], 'page' === $d['kind'] ? '' : $d['kind'] ),
				'updated' => (string) $d['updated'],
			);
		}
		return array( 'documents' => $out );
	}

	public static function run_get( $in ) {
		$doc = self::load( (int) $in['document_id'] );
		if ( is_wp_error( $doc ) ) {
			return $doc;
		}
		$m = $doc['model'];
		$classes = array();
		foreach ( (array) ( $m->classes ?? array() ) as $name => $by ) {
			$classes[ $name ] = $by;
		}
		return array(
			'id'       => $doc['id'],
			'kind'     => $doc['kind'],
			'title'    => $doc['title'],
			'status'   => $doc['status'],
			'preview'  => self::preview( $doc ),
			'elements' => self::outline( $m->tree ?? array(), $m ),
			'classes'  => (object) $classes,
		);
	}

	/** The tree as an agent reads it. */
	private static function outline( $nodes, $m ) {
		$out = array();
		foreach ( (array) $nodes as $n ) {
			if ( ! is_object( $n ) ) {
				continue;
			}
			$o = array( 'id' => (string) $n->id, 'type' => self::type_of( $n ), 'tag' => (string) ( $n->tag ?? '' ), 'classes' => array_values( (array) ( $n->classes ?? array() ) ) );
			$c = $m->content->{$n->id} ?? null;
			if ( null !== $c && is_scalar( $c ) ) {
				$o[ 'Image' === ( $n->el ?? '' ) ? 'src' : 'text' ] = (string) $c;
			}
			foreach ( array( 'href', 'alt', 'name', 'ref', 'hidden', 'locked' ) as $k ) {
				if ( isset( $n->$k ) && '' !== $n->$k ) {
					$o[ 'ref' === $k ? 'component_id' : $k ] = $n->$k;
				}
			}
			if ( ! empty( $n->att ) ) {
				$o['image_id'] = (int) $n->att;
			}
			if ( ! empty( $n->settings ) ) {
				$o['settings'] = $n->settings;
			}
			$ov = (array) ( $n->overrides ?? array() );
			if ( $ov ) {
				$o['styles'] = (object) $ov;
			}
			if ( ! empty( $n->children ) ) {
				$o['children'] = self::outline( $n->children, $m );
			}
			$out[] = $o;
		}
		return $out;
	}

	private static function type_of( $n ) {
		$el  = (string) ( $n->el ?? '' );
		$cls = (array) ( $n->classes ?? array() );
		foreach ( self::TYPES as $k => $t ) {
			if ( $t['el'] === $el && ( '' === $t['cls'] || in_array( $t['cls'], $cls, true ) ) ) {
				return $k;
			}
		}
		foreach ( self::TYPES as $k => $t ) {
			if ( $t['el'] === $el ) {
				return $k;
			}
		}
		return strtolower( $el ? $el : 'element' );
	}

	/* =========================================================== write side */

	public static function run_create( $in ) {
		global $wpdb;
		$kind  = in_array( $in['kind'] ?? 'page', array( 'page', 'template', 'reusable' ), true ) ? ( $in['kind'] ?? 'page' ) : 'page';
		$title = sanitize_text_field( (string) ( $in['title'] ?? '' ) );
		if ( '' === $title ) {
			return new WP_Error( 'velox_title', __( 'Give it a title.', 'velox' ) );
		}
		$m = (object) array( 'tree' => array(), 'classes' => new stdClass(), 'content' => new stdClass() );
		if ( 'template' === $kind ) {
			// Like the editor: a template starts with the slot for the page's content.
			$m->tree[] = (object) array( 'id' => self::uid( 'innercontent', $m ), 'el' => 'InnerContent', 'tag' => 'div', 'classes' => array( '.inner-content' ), 'overrides' => new stdClass(), 'children' => array() );
			$m->classes->{'.inner-content'} = (object) array( 'base' => (object) array( 'minHeight' => '200' ) );
		}
		$made = array();
		if ( ! empty( $in['elements'] ) ) {
			$nodes = self::build_list( (array) $in['elements'], $m, $made );
			if ( is_wp_error( $nodes ) ) {
				return $nodes;
			}
			$m->tree = array_merge( $m->tree, $nodes );
		}
		$now = current_time( 'mysql' );
		$wpdb->insert( Velox_Builder::table(), array( 'kind' => $kind, 'title' => $title, 'data' => wp_json_encode( $m ), 'css_size' => 0, 'status' => 'draft', 'post_id' => null, 'updated' => $now, 'created' => $now ), array( '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' ) );
		$id = (int) $wpdb->insert_id;
		if ( ! $id ) {
			return new WP_Error( 'velox_db', __( 'Could not save.', 'velox' ) );
		}
		if ( 'template' === $kind ) {
			Velox_Builder::set_template_purpose( $id, 'catch_all' );
		}
		$post_id = 0;
		if ( 'page' === $kind ) {
			// A draft WordPress page right away: it has a preview address, and the
			// person who asked (and their agent) may keep editing it.
			$post_id = wp_insert_post( array( 'post_title' => $title, 'post_type' => 'page', 'post_status' => 'draft', 'post_content' => '' ), true );
			if ( ! is_wp_error( $post_id ) && $post_id ) {
				update_post_meta( $post_id, '_velox_builder_doc', $id );
				$wpdb->update( Velox_Builder::table(), array( 'post_id' => $post_id ), array( 'id' => $id ), array( '%d' ), array( '%d' ) );
			} else {
				$post_id = 0;
			}
		}
		self::version( $id, wp_json_encode( $m ), $title );
		return array( 'document_id' => $id, 'created_elements' => $made, 'edit' => Velox_Builder::edit_url( $id, 'page' === $kind ? '' : $kind ), 'preview' => self::preview( array( 'kind' => $kind, 'post_id' => (int) $post_id ) ), 'note' => 'Saved as a draft. Ask the person to open it in the editor, check it and publish it.' );
	}

	public static function run_add( $in ) {
		$doc = self::load( (int) $in['document_id'] );
		if ( is_wp_error( $doc ) ) {
			return $doc;
		}
		$m    = $doc['model'];
		$made = array();
		$nodes = self::build_list( (array) ( $in['elements'] ?? array() ), $m, $made );
		if ( is_wp_error( $nodes ) ) {
			return $nodes;
		}
		$pid = (string) ( $in['parent_id'] ?? '' );
		if ( '' === $pid ) {
			$list = (array) $m->tree;
		} else {
			$parent = self::find( $m->tree, $pid );
			if ( ! $parent ) {
				return new WP_Error( 'velox_parent', __( 'There is no element with that parent_id in this document.', 'velox' ) );
			}
			if ( self::locked_at( $m->tree, $pid ) ) {
				return self::locked_error();
			}
			if ( ! self::holds_children( $parent ) ) {
				return new WP_Error( 'velox_parent', __( 'That element cannot hold other elements. Use a section, div, columns or grid.', 'velox' ) );
			}
			$list = (array) ( $parent->children ?? array() );
		}
		$pos = isset( $in['position'] ) ? max( 0, min( count( $list ), (int) $in['position'] ) ) : count( $list );
		array_splice( $list, $pos, 0, $nodes );
		if ( '' === $pid ) {
			$m->tree = $list;
		} else {
			$parent->children = $list;
		}
		self::save( $doc, $m );
		return array( 'created_elements' => $made, 'preview' => self::preview( $doc ) );
	}

	public static function run_update( $in ) {
		$doc = self::load( (int) $in['document_id'] );
		if ( is_wp_error( $doc ) ) {
			return $doc;
		}
		$m = $doc['model'];
		$n = self::find( $m->tree, (string) $in['element_id'] );
		if ( ! $n ) {
			return new WP_Error( 'velox_element', __( 'There is no element with that id in this document.', 'velox' ) );
		}
		if ( self::locked_at( $m->tree, (string) $in['element_id'] ) ) {
			return self::locked_error();
		}
		$type = self::type_of( $n );
		$err  = self::apply( $n, $type, $in, $m, false );
		if ( is_wp_error( $err ) ) {
			return $err;
		}
		self::save( $doc, $m );
		return array( 'element' => self::outline( array( $n ), $m )[0], 'preview' => self::preview( $doc ) );
	}

	public static function run_remove( $in ) {
		$doc = self::load( (int) $in['document_id'] );
		if ( is_wp_error( $doc ) ) {
			return $doc;
		}
		$m  = $doc['model'];
		$id = (string) $in['element_id'];
		$gone = self::find( $m->tree, $id );
		if ( ! $gone ) {
			return new WP_Error( 'velox_element', __( 'There is no element with that id in this document.', 'velox' ) );
		}
		$inside = false;
		self::walk( array( $gone ), function ( $x ) use ( &$inside ) { if ( ! empty( $x->locked ) ) { $inside = true; } } );
		if ( $inside || self::locked_at( $m->tree, $id ) ) {
			return self::locked_error();
		}
		if ( 'InnerContent' === ( $gone->el ?? '' ) ) {
			return new WP_Error( 'velox_inner', __( 'A template needs its Inner Content element; it cannot be removed.', 'velox' ) );
		}
		$m->tree = self::without( $m->tree, $id );
		self::walk( array( $gone ), function ( $x ) use ( $m ) { unset( $m->content->{$x->id} ); } );
		self::save( $doc, $m );
		return array( 'removed' => $id, 'preview' => self::preview( $doc ) );
	}

	public static function run_class( $in ) {
		$doc = self::load( (int) $in['document_id'] );
		if ( is_wp_error( $doc ) ) {
			return $doc;
		}
		$m    = $doc['model'];
		$name = self::class_name( (string) $in['class'] );
		if ( '' === $name ) {
			return new WP_Error( 'velox_class', __( 'Class names start with a dot and use letters, numbers, - and _.', 'velox' ) );
		}
		self::ensure_class( $m, $name, array() );
		$by = self::styles( $in['styles'] ?? array() );
		if ( is_wp_error( $by ) ) {
			return $by;
		}
		self::merge_styles( $m->classes->$name, $by );
		$changed = isset( $m->changedClasses ) && is_array( $m->changedClasses ) ? $m->changedClasses : array();
		if ( ! in_array( $name, $changed, true ) ) {
			$changed[] = $name;
		}
		$m->changedClasses = $changed;
		self::save( $doc, $m );
		return array( 'class' => $name, 'styles' => $m->classes->$name, 'note' => 'Applies to every element with this class in this document now, and site-wide once the document is published.', 'preview' => self::preview( $doc ) );
	}

	/* ============================================================ building */

	private static function build_list( $specs, $m, &$made, $depth = 0 ) {
		$out = array();
		foreach ( $specs as $spec ) {
			$n = self::build( $spec, $m, $made, $depth );
			if ( is_wp_error( $n ) ) {
				return $n;
			}
			$out[] = $n;
		}
		return $out;
	}

	private static function build( $spec, $m, &$made, $depth ) {
		$spec = is_object( $spec ) ? (array) $spec : (array) $spec;
		if ( $depth > 30 || count( $made ) >= self::MAX_NODES ) {
			return new WP_Error( 'velox_size', __( 'Too many elements at once. Add them in smaller parts.', 'velox' ) );
		}
		$type = strtolower( (string) ( $spec['type'] ?? '' ) );
		if ( ! isset( self::TYPES[ $type ] ) ) {
			/* translators: %s: list of element types. */
			return new WP_Error( 'velox_type', sprintf( __( 'Unknown element type. Use one of: %s', 'velox' ), implode( ', ', array_keys( self::TYPES ) ) ) );
		}
		$t = self::TYPES[ $type ];
		$n = (object) array( 'id' => self::uid( $type, $m ), 'el' => $t['el'], 'tag' => $t['tag'], 'classes' => array(), 'overrides' => new stdClass(), 'children' => array() );
		if ( '' !== $t['cls'] ) {
			$n->classes[] = $t['cls'];
			self::ensure_class( $m, $t['cls'], $t['rules'] );
		}
		if ( 'component' === $type ) {
			$cid = (int) ( $spec['component_id'] ?? 0 );
			if ( ! $cid || ! self::is_component( $cid ) ) {
				return new WP_Error( 'velox_component', __( 'component_id must be the id of a component (see velox/list-documents).', 'velox' ) );
			}
			$n->ref = $cid;
		}
		$err = self::apply( $n, $type, $spec, $m, true );
		if ( is_wp_error( $err ) ) {
			return $err;
		}
		$made[] = array( 'id' => $n->id, 'type' => $type );
		if ( ! empty( $spec['children'] ) ) {
			if ( empty( $t['box'] ) ) {
				/* translators: %s: element type. */
				return new WP_Error( 'velox_children', sprintf( __( 'A %s cannot hold other elements. Use a section, div, columns or grid.', 'velox' ), $type ) );
			}
			$kids = self::build_list( (array) $spec['children'], $m, $made, $depth + 1 );
			if ( is_wp_error( $kids ) ) {
				return $kids;
			}
			$n->children = $kids;
		}
		return $n;
	}

	/** Apply the fields of a spec (or of an update) to a node. */
	private static function apply( $n, $type, $in, $m, $is_new ) {
		$t = self::TYPES[ $type ] ?? array();
		if ( isset( $in['tag'] ) && '' !== $in['tag'] ) {
			$tag = strtolower( (string) $in['tag'] );
			if ( ! in_array( $tag, $t['tags'] ?? array( $t['tag'] ?? '' ), true ) ) {
				/* translators: %s: allowed tags. */
				return new WP_Error( 'velox_tag', sprintf( __( 'This element can use these tags: %s', 'velox' ), implode( ', ', $t['tags'] ?? array( $t['tag'] ?? '' ) ) ) );
			}
			$n->tag = $tag;
		}
		if ( isset( $in['text'] ) && ! empty( $t['text'] ) ) {
			$m->content->{$n->id} = wp_kses_post( (string) $in['text'] );
		}
		if ( isset( $in['href'] ) && ! empty( $t['link'] ) ) {
			$href = esc_url_raw( trim( (string) $in['href'] ) );
			if ( '' === $href && '' !== trim( (string) $in['href'] ) ) {
				return new WP_Error( 'velox_href', __( 'That link is not allowed. Use an address (https://…, /page/ or #section), mailto: or tel:.', 'velox' ) );
			}
			$n->href = $href;
		}
		if ( 'image' === $type ) {
			if ( ! empty( $in['image_id'] ) && wp_attachment_is_image( (int) $in['image_id'] ) ) {
				$n->att = (int) $in['image_id'];
				$m->content->{$n->id} = (string) wp_get_attachment_url( (int) $in['image_id'] );
			} elseif ( isset( $in['src'] ) ) {
				unset( $n->att );
				$m->content->{$n->id} = esc_url_raw( (string) $in['src'] );
			}
			if ( isset( $in['alt'] ) ) {
				$n->alt = sanitize_text_field( (string) $in['alt'] );
				$n->altCustom = true;
			}
		}
		if ( isset( $in['name'] ) ) {
			$name = sanitize_text_field( (string) $in['name'] );
			if ( '' === $name ) {
				unset( $n->name );
			} else {
				$n->name = $name;
			}
		}
		if ( isset( $in['settings'] ) ) {
			$set = self::settings( $type, (array) $in['settings'] );
			if ( is_wp_error( $set ) ) {
				return $set;
			}
			$cur = isset( $n->settings ) && is_object( $n->settings ) ? $n->settings : new stdClass();
			foreach ( $set as $k => $v ) {
				$cur->$k = $v;
			}
			$n->settings = $cur;
		}
		$add = array_merge( (array) ( $in['classes'] ?? array() ), (array) ( $in['add_classes'] ?? array() ) );
		foreach ( $add as $c ) {
			$c = self::class_name( (string) $c );
			if ( '' !== $c && ! in_array( $c, $n->classes, true ) ) {
				$n->classes[] = $c;
				self::ensure_class( $m, $c, array() );
			}
		}
		foreach ( (array) ( $in['remove_classes'] ?? array() ) as $c ) {
			$c = self::class_name( (string) $c );
			$n->classes = array_values( array_diff( (array) $n->classes, array( $c ) ) );
		}
		if ( isset( $in['styles'] ) ) {
			$by = self::styles( $in['styles'] );
			if ( is_wp_error( $by ) ) {
				return $by;
			}
			if ( ! is_object( $n->overrides ) ) {
				$n->overrides = (object) (array) $n->overrides;
			}
			self::merge_styles( $n->overrides, $by );
		}
		return true;
	}

	private static function settings( $type, $in ) {
		$spec = self::SETTINGS[ $type ] ?? array();
		$out  = array();
		foreach ( $in as $k => $v ) {
			if ( ! isset( $spec[ $k ] ) ) {
				continue;
			}
			$rule = $spec[ $k ];
			if ( is_array( $rule ) ) {
				if ( in_array( (string) $v, $rule, true ) ) {
					$out[ $k ] = (string) $v;
				}
			} elseif ( 'url' === $rule ) {
				$out[ $k ] = esc_url_raw( (string) $v );
			} elseif ( 'text' === $rule ) {
				$out[ $k ] = sanitize_text_field( (string) $v );
			} elseif ( 'bool' === $rule ) {
				$out[ $k ] = $v ? '1' : '';
			} elseif ( 'key' === $rule ) {
				$out[ $k ] = sanitize_key( (string) $v );
			} elseif ( 'num' === $rule ) {
				$out[ $k ] = preg_replace( '/[^0-9.]/', '', (string) $v );
			} elseif ( 'int' === $rule ) {
				$fid = (int) $v;
				if ( 'form' === $k && ( ! class_exists( 'Velox_Forms' ) || ! Velox_Forms::get_form( $fid ) ) ) {
					return new WP_Error( 'velox_form', __( 'There is no form with that id (see the forms in velox/get-design-system).', 'velox' ) );
				}
				$out[ $k ] = (string) $fid;
			}
		}
		return $out;
	}

	/** Styles from an agent: flat {prop: value} = all screens, or {screen[:state]: {prop: value}}. */
	private static function styles( $in ) {
		$in = json_decode( wp_json_encode( $in ), true );
		if ( ! is_array( $in ) ) {
			return new WP_Error( 'velox_styles', __( 'styles must be an object.', 'velox' ) );
		}
		$keys = '/^(base|xxl|xl|lg|md|sm)(:(hover|focus|active|focus-visible|focus-within))?$/';
		// A screen size (or size:state) key holds an object; any other key is a property for all screens.
		$by = array();
		foreach ( $in as $k => $v ) {
			if ( preg_match( $keys, (string) $k ) && is_array( $v ) ) {
				$by[ $k ] = array_merge( $by[ $k ] ?? array(), $v );
			} else {
				$by['base'][ $k ] = $v;
			}
		}
		$out = array();
		$bad = array();
		foreach ( $by as $bp => $props ) {
			foreach ( (array) $props as $p => $v ) {
				$prop = Velox_Builder_Render::css_prop_name( (string) $p ) ? (string) $p : Velox_Builder_Render::model_prop_name( (string) $p );
				if ( '' === $prop ) {
					$bad[] = (string) $p;
					continue;
				}
				$out[ $bp ][ $prop ] = '' === (string) $v ? '' : Velox_Builder_Render::sanitize_value( is_scalar( $v ) ? (string) $v : '' );
			}
		}
		if ( $bad ) {
			/* translators: %s: property names. */
			return new WP_Error( 'velox_styles', sprintf( __( 'Unknown style properties: %s. See style_properties in velox/get-design-system.', 'velox' ), implode( ', ', $bad ) ) );
		}
		return $out;
	}

	private static function merge_styles( $target, $by ) {
		foreach ( $by as $bp => $props ) {
			$cur = isset( $target->$bp ) ? (array) $target->$bp : array();
			foreach ( $props as $p => $v ) {
				if ( '' === $v ) {
					unset( $cur[ $p ] );
				} else {
					$cur[ $p ] = $v;
				}
			}
			if ( $cur ) {
				$target->$bp = (object) $cur;
			} else {
				unset( $target->$bp );
			}
		}
	}

	/** A class in the document: this site's version if there is one, else the starter rules. */
	private static function ensure_class( $m, $name, $rules ) {
		if ( ! isset( $m->classes ) || ! is_object( $m->classes ) ) {
			$m->classes = (object) (array) ( $m->classes ?? array() );
		}
		if ( isset( $m->classes->$name ) ) {
			return;
		}
		$reg = Velox_Builder::class_registry();
		$src = isset( $reg[ $name ] ) ? $reg[ $name ] : array( 'base' => $rules );
		$cls = new stdClass();
		foreach ( (array) $src as $k => $props ) {
			$cls->$k = (object) (array) $props;
		}
		$m->classes->$name = $cls;
	}

	private static function class_name( $c ) {
		$c = trim( $c );
		if ( '' !== $c && '.' !== $c[0] ) {
			$c = '.' . $c;
		}
		return preg_match( '/^\.[a-zA-Z_-][a-zA-Z0-9_-]*$/', $c ) ? $c : '';
	}

	private static function uid( $base, $m ) {
		$base = preg_replace( '/[^a-z0-9]/', '', strtolower( $base ) );
		do {
			$id = $base . '-' . strtolower( wp_generate_password( 6, false, false ) );
		} while ( self::find( $m->tree ?? array(), $id ) );
		return $id;
	}

	/* ========================================================== documents */

	/** The document's newest saved version, for reading and writing. */
	private static function load( $id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT id, kind, title, status, post_id, data, draft FROM ' . Velox_Builder::table() . ' WHERE id = %d', $id ), ARRAY_A );
		if ( ! $row || ! in_array( $row['kind'], array( 'page', 'template', 'reusable' ), true ) ) {
			return new WP_Error( 'velox_document', __( 'There is no page, template or component with that id.', 'velox' ) );
		}
		$raw = ( null !== $row['draft'] && '' !== $row['draft'] ) ? $row['draft'] : $row['data'];
		$m   = json_decode( (string) $raw );
		if ( ! is_object( $m ) ) {
			$m = new stdClass();
		}
		$m->tree    = isset( $m->tree ) && is_array( $m->tree ) ? $m->tree : array();
		$m->content = isset( $m->content ) && is_object( $m->content ) ? $m->content : (object) (array) ( $m->content ?? array() );
		$m->classes = isset( $m->classes ) && is_object( $m->classes ) ? $m->classes : (object) (array) ( $m->classes ?? array() );
		return array( 'id' => (int) $row['id'], 'kind' => $row['kind'], 'title' => $row['title'], 'status' => $row['status'], 'post_id' => (int) $row['post_id'], 'model' => $m );
	}

	/** Save as the draft: what visitors see changes only when a person publishes. */
	private static function save( $doc, $m ) {
		global $wpdb;
		$data  = wp_json_encode( $m );
		$field = 'published' === $doc['status'] ? 'draft' : 'data';
		$wpdb->update( Velox_Builder::table(), array( $field => $data, 'updated' => current_time( 'mysql' ) ), array( 'id' => $doc['id'] ) );
		self::version( $doc['id'], $data, $doc['title'] );
	}

	private static function version( $id, $data, $title ) {
		if ( class_exists( 'Velox_Builder_Revisions' ) ) {
			Velox_Builder_Revisions::record( $id, 'ai', $data, $title );
		}
	}

	/** Where a person who may edit sees the saved draft (a draft page has only its preview link). */
	private static function preview( $doc ) {
		if ( 'page' !== $doc['kind'] || ! $doc['post_id'] ) {
			return '';
		}
		return 'publish' === get_post_status( $doc['post_id'] ) ? (string) get_permalink( $doc['post_id'] ) : (string) get_preview_post_link( $doc['post_id'] );
	}

	/** Is the element locked, or inside a locked element? */
	private static function locked_at( $nodes, $id, $in = false ) {
		foreach ( (array) $nodes as $n ) {
			if ( ! is_object( $n ) ) {
				continue;
			}
			$l = $in || ! empty( $n->locked );
			if ( (string) ( $n->id ?? '' ) === $id ) {
				return $l;
			}
			if ( ! empty( $n->children ) && self::locked_at( $n->children, $id, $l ) ) {
				return true;
			}
		}
		return false;
	}

	private static function locked_error() {
		return new WP_Error( 'velox_locked', __( 'That element is locked in the editor. Ask the person to unlock it (right-click → Unlock) if it should change.', 'velox' ) );
	}

	private static function is_component( $id ) {
		global $wpdb;
		return 'reusable' === $wpdb->get_var( $wpdb->prepare( 'SELECT kind FROM ' . Velox_Builder::table() . ' WHERE id = %d', $id ) );
	}

	private static function holds_children( $n ) {
		$t = self::TYPES[ self::type_of( $n ) ] ?? null;
		return $t ? ! empty( $t['box'] ) : in_array( $n->el ?? '', array( 'Section', 'Div', 'Columns' ), true );
	}

	private static function find( $nodes, $id ) {
		foreach ( (array) $nodes as $n ) {
			if ( ! is_object( $n ) ) {
				continue;
			}
			if ( (string) ( $n->id ?? '' ) === $id ) {
				return $n;
			}
			if ( ! empty( $n->children ) ) {
				$f = self::find( $n->children, $id );
				if ( $f ) {
					return $f;
				}
			}
		}
		return null;
	}

	private static function without( $nodes, $id ) {
		$out = array();
		foreach ( (array) $nodes as $n ) {
			if ( is_object( $n ) && (string) ( $n->id ?? '' ) === $id ) {
				continue;
			}
			if ( is_object( $n ) && ! empty( $n->children ) ) {
				$n->children = self::without( $n->children, $id );
			}
			$out[] = $n;
		}
		return $out;
	}

	private static function walk( $nodes, $fn ) {
		foreach ( (array) $nodes as $n ) {
			if ( is_object( $n ) ) {
				$fn( $n );
				if ( ! empty( $n->children ) ) {
					self::walk( $n->children, $fn );
				}
			}
		}
	}

	/** Switch agents on or off (Velox Builder → AI agents). */
	public static function ajax_toggle() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Not allowed.', 'velox' ) ), 403 );
		}
		update_option( self::OPT, empty( $_POST['on'] ) ? 0 : 1 );
		wp_send_json_success( array( 'on' => self::enabled() ) );
	}
}

Velox_Builder_Agents::init();
