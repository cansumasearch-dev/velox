<?php
/**
 * Velox Builder — who may use the builder, and how much.
 *
 * Levels per WordPress role:
 *   client   — change text, images, links and component fields on pages they
 *              may edit. Layout, styles and code stay as they are.
 *   designer — everything inside a page (layout, styles, element settings),
 *              but not custom CSS / JavaScript.
 * Administrators (manage_options) can do everything, always.
 * "May publish" is a separate switch per role.
 *
 * Stored as real capabilities on the roles (velox_builder, velox_builder_design,
 * velox_builder_publish), so WordPress and other plugins see them too.
 * Templates, components and everything site-wide stay with administrators.
 *
 * @package Velox
 */

defined( 'ABSPATH' ) || exit;

class Velox_Builder_Roles {

	const OPT  = 'velox_builder_roles';
	const CAPS = array( 'velox_builder', 'velox_builder_design', 'velox_builder_publish' );

	/** What a non-admin may ask the server for (each handler still checks the document). */
	const CLIENT_ACTIONS   = array( 'builder_save', 'builder_load', 'builder_publish', 'builder_discard_draft', 'builder_preview_css', 'builder_loop_preview', 'builder_shop_preview', 'builder_reviews_preview', 'builder_revisions', 'builder_revision' );
	// Designers may also chat with the AI assistant (it checks the document and their rights again).
	const DESIGNER_ACTIONS = array( 'builder_class_css', 'ai_send', 'ai_continue', 'ai_poll', 'ai_stop', 'ai_reset', 'mcp_watch' );

	public static function init() {
		// Administrators always have every builder capability.
		add_filter( 'user_has_cap', array( __CLASS__, 'grant_admins' ), 10, 4 );
	}

	public static function grant_admins( $allcaps, $caps, $args, $user ) {
		if ( ! empty( $allcaps['manage_options'] ) ) {
			foreach ( self::CAPS as $c ) {
				$allcaps[ $c ] = true;
			}
		}
		return $allcaps;
	}

	/* ------------------------------------------------------------ settings */

	public static function settings() {
		$o = get_option( self::OPT, array() );
		return is_array( $o ) ? $o : array();
	}

	/** Roles that can be given access (administrators always have it). */
	public static function roles() {
		$out = array();
		if ( ! function_exists( 'wp_roles' ) ) {
			return $out;
		}
		foreach ( wp_roles()->roles as $slug => $r ) {
			if ( 'administrator' === $slug || ! empty( $r['capabilities']['manage_options'] ) ) {
				continue;
			}
			$out[ $slug ] = translate_user_role( $r['name'] );
		}
		return $out;
	}

	/** Save the levels and put the matching capabilities on each role. */
	public static function save( $in ) {
		$clean = array();
		foreach ( self::roles() as $slug => $name ) {
			$row   = is_array( $in[ $slug ] ?? null ) ? $in[ $slug ] : array();
			$level = in_array( $row['level'] ?? '', array( 'client', 'designer' ), true ) ? $row['level'] : '';
			if ( '' === $level ) {
				$clean[ $slug ] = array( 'level' => '', 'publish' => 0 );
			} else {
				$clean[ $slug ] = array( 'level' => $level, 'publish' => empty( $row['publish'] ) ? 0 : 1 );
			}
			$role = get_role( $slug );
			if ( ! $role ) {
				continue;
			}
			$want = array(
				'velox_builder'         => '' !== $level,
				'velox_builder_design'  => 'designer' === $level,
				'velox_builder_publish' => '' !== $level && ! empty( $clean[ $slug ]['publish'] ),
			);
			foreach ( $want as $cap => $on ) {
				if ( $on ) {
					$role->add_cap( $cap );
				} else {
					$role->remove_cap( $cap );
				}
			}
		}
		update_option( self::OPT, $clean );
		return $clean;
	}

	/* ------------------------------------------------------------ the current user */

	/** admin | designer | client | '' (no access). */
	public static function level() {
		if ( current_user_can( 'manage_options' ) ) {
			return 'admin';
		}
		if ( current_user_can( 'velox_builder_design' ) ) {
			return 'designer';
		}
		if ( current_user_can( 'velox_builder' ) ) {
			return 'client';
		}
		return '';
	}

	public static function can_publish() {
		return current_user_can( 'manage_options' ) || ( current_user_can( 'velox_builder' ) && current_user_can( 'velox_builder_publish' ) );
	}

	/** May this AJAX action be called at all by the current user? */
	public static function may_call( $do ) {
		$level = self::level();
		if ( 'admin' === $level ) {
			return true;
		}
		if ( '' === $level ) {
			return false;
		}
		if ( in_array( $do, self::CLIENT_ACTIONS, true ) ) {
			return true;
		}
		return 'designer' === $level && in_array( $do, self::DESIGNER_ACTIONS, true );
	}

	/**
	 * May the current user open / save this document? Non-admins only work on
	 * pages bound to a post they may edit in WordPress. Templates and
	 * components are site-wide and stay with administrators.
	 */
	public static function can_edit_doc( $doc_id ) {
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}
		if ( '' === self::level() ) {
			return false;
		}
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT kind, post_id FROM ' . Velox_Builder::table() . ' WHERE id = %d', (int) $doc_id ), ARRAY_A );
		return $row && 'page' === $row['kind'] && (int) $row['post_id'] && current_user_can( 'edit_post', (int) $row['post_id'] );
	}

	/** May the current user start a new Velox layout for this post? (Designers and admins.) */
	public static function can_create_for_post( $post_id ) {
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}
		return 'designer' === self::level() && (int) $post_id && current_user_can( 'edit_post', (int) $post_id )
			&& in_array( get_post_type( (int) $post_id ), array( 'page', 'post' ), true );
	}

	/** Should "Edit with Velox" be offered for this post? */
	public static function offer_for_post( $post_id ) {
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}
		$level = self::level();
		if ( '' === $level || ! current_user_can( 'edit_post', (int) $post_id ) ) {
			return false;
		}
		return 'designer' === $level || (bool) Velox_Builder::doc_id_for_post( (int) $post_id );
	}

	/* ------------------------------------------------------------ what a save may change */

	/**
	 * Limit a posted document to what the current user may change.
	 *
	 * @param object      $posted The document as sent by the editor (decoded as objects).
	 * @param object|null $stored The current stored version (draft or live), or null for a new document.
	 * @return object The document to store.
	 */
	public static function filter_model( $posted, $stored ) {
		$level = self::level();
		if ( 'admin' === $level || ! is_object( $posted ) ) {
			return $posted;
		}
		if ( 'client' === $level ) {
			// Clients change content only: start from the stored document and copy
			// over text, images, links and component fields of existing elements.
			if ( ! is_object( $stored ) ) {
				return null;
			}
			$out    = json_decode( wp_json_encode( $stored ) );
			$byid   = array();
			$collect = function ( $nodes ) use ( &$collect, &$byid ) {
				foreach ( (array) $nodes as $n ) {
					if ( is_object( $n ) && isset( $n->id ) ) {
						$byid[ (string) $n->id ] = $n;
						$collect( $n->children ?? array() );
					}
				}
			};
			$collect( $posted->tree ?? array() );
			// Locked elements (and what is inside them) keep their stored content.
			$locked = array();
			$lockwalk = function ( $nodes, $in ) use ( &$lockwalk, &$locked ) {
				foreach ( (array) $nodes as $n ) {
					if ( is_object( $n ) && isset( $n->id ) ) {
						$l = $in || ! empty( $n->locked );
						if ( $l ) {
							$locked[ (string) $n->id ] = true;
						}
						$lockwalk( $n->children ?? array(), $l );
					}
				}
			};
			$lockwalk( $stored->tree ?? array(), false );
			$apply = function ( $nodes ) use ( &$apply, $byid, $locked ) {
				foreach ( (array) $nodes as $n ) {
					if ( ! is_object( $n ) || ! isset( $n->id ) ) {
						continue;
					}
					$p = isset( $locked[ (string) $n->id ] ) ? null : ( $byid[ (string) $n->id ] ?? null );
					if ( $p ) {
						foreach ( array( 'href', 'target', 'alt', 'altCustom', 'att', 'props' ) as $f ) {
							if ( property_exists( $p, $f ) ) {
								$n->{$f} = $p->{$f};
							} else {
								unset( $n->{$f} );
							}
						}
					}
					$apply( $n->children ?? array() );
				}
			};
			$apply( $out->tree ?? array() );
			// Text and image content of elements that exist in the stored document.
			$ids = array();
			$all = function ( $nodes ) use ( &$all, &$ids ) {
				foreach ( (array) $nodes as $n ) {
					if ( is_object( $n ) && isset( $n->id ) ) {
						$ids[ (string) $n->id ] = true;
						$all( $n->children ?? array() );
					}
				}
			};
			$all( $out->tree ?? array() );
			if ( ! isset( $out->content ) || ! is_object( $out->content ) ) {
				$out->content = new stdClass();
			}
			if ( isset( $posted->content ) && is_object( $posted->content ) ) {
				foreach ( get_object_vars( $posted->content ) as $k => $v ) {
					if ( isset( $ids[ (string) $k ] ) && ! isset( $locked[ (string) $k ] ) && is_scalar( $v ) ) {
						$out->content->{$k} = (string) $v;
					}
				}
			}
			return $out;
		}
		// Designers: everything except custom code, which keeps its stored value.
		$code = array();
		$page = is_object( $stored ) && isset( $stored->page ) && is_object( $stored->page ) ? $stored->page : null;
		$grab = function ( $nodes ) use ( &$grab, &$code ) {
			foreach ( (array) $nodes as $n ) {
				if ( is_object( $n ) && isset( $n->id ) ) {
					if ( isset( $n->css ) ) {
						$code[ (string) $n->id ] = $n->css;
					}
					$grab( $n->children ?? array() );
				}
			}
		};
		if ( is_object( $stored ) ) {
			$grab( $stored->tree ?? array() );
		}
		$put = function ( $nodes ) use ( &$put, $code ) {
			foreach ( (array) $nodes as $n ) {
				if ( is_object( $n ) && isset( $n->id ) ) {
					if ( isset( $code[ (string) $n->id ] ) ) {
						$n->css = $code[ (string) $n->id ];
					} else {
						unset( $n->css );
					}
					$put( $n->children ?? array() );
				}
			}
		};
		$put( $posted->tree ?? array() );
		if ( $page && ( isset( $page->css ) || isset( $page->js ) ) && ( ! isset( $posted->page ) || ! is_object( $posted->page ) ) ) {
			$posted->page = new stdClass();
		}
		if ( isset( $posted->page ) && is_object( $posted->page ) ) {
			foreach ( array( 'css', 'js' ) as $k ) {
				if ( $page && isset( $page->{$k} ) ) {
					$posted->page->{$k} = $page->{$k};
				} else {
					unset( $posted->page->{$k} );
				}
			}
		}
		return $posted;
	}

	/** For the editor: what the current user may do. */
	public static function editor_data() {
		return array( 'level' => self::level(), 'publish' => self::can_publish() );
	}
}

Velox_Builder_Roles::init();
