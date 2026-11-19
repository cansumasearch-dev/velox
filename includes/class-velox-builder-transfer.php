<?php
/**
 * Velox Builder — import and export.
 *
 * Moves pages, templates and components (with the components and forms they
 * use) and the design system (classes, design tokens, global styles, fonts,
 * global CSS / JS) from one site to another as one JSON file.
 *
 * Import is two steps: a preview that says what is new and what already
 * exists here, then the import itself with the choices made in the preview.
 * Everything arrives as a draft (components are live, so imported pages can
 * use them), nothing that is live here is overwritten without a saved version
 * to go back to, and images can be copied into this site's media library.
 *
 * @package Velox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Velox_Builder_Transfer {

	const FORMAT    = 'velox-builder-export';
	const VERSION   = 1;
	const MAX_BYTES = 31457280; // 30 MB
	const IMG_EXT   = 'jpe?g|png|gif|webp|avif';

	/* ================================================================ export */

	/** Every document, for the export list. */
	public static function doc_list() {
		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT id, kind, title, status, post_id FROM ' . Velox_Builder::table() . " WHERE kind IN ('page','template','reusable') ORDER BY kind ASC, title ASC", ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	public static function ajax_export() {
		self::must_admin();
		$ids   = json_decode( isset( $_POST['ids'] ) ? wp_unslash( $_POST['ids'] ) : '[]', true );
		$parts = json_decode( isset( $_POST['parts'] ) ? wp_unslash( $_POST['parts'] ) : '[]', true );
		$ids   = array_values( array_filter( array_map( 'absint', (array) $ids ) ) );
		$parts = array_values( array_intersect( (array) $parts, array( 'classes', 'tokens', 'styles', 'fonts', 'css', 'js' ) ) );
		if ( ! $ids && ! $parts ) {
			wp_send_json_error( array( 'message' => __( 'Choose what to export.', 'velox' ) ), 400 );
		}
		$name = 'velox-' . sanitize_title( get_bloginfo( 'name' ) ? get_bloginfo( 'name' ) : 'site' ) . '-' . gmdate( 'Y-m-d' ) . '.json';
		wp_send_json_success( array( 'file' => self::build( $ids, $parts ), 'name' => $name ) );
	}

	/** The export file for these documents and parts of the design system. */
	public static function build( $ids, $parts ) {
		global $wpdb;
		$t      = Velox_Builder::table();
		$queue  = array_values( $ids );
		$picked = array_flip( $ids );
		$seen   = array();
		$docs   = array();
		$forms  = array();
		while ( $queue ) {
			$id = (int) array_shift( $queue );
			if ( isset( $seen[ $id ] ) ) {
				continue;
			}
			$seen[ $id ] = 1;
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT id, kind, title, status, post_id, data, draft FROM {$t} WHERE id = %d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( ! $row || ! in_array( $row['kind'], array( 'page', 'template', 'reusable' ), true ) ) {
				continue;
			}
			// The newest saved work, published or not.
			$raw   = ( null !== $row['draft'] && '' !== $row['draft'] ) ? $row['draft'] : $row['data'];
			$model = json_decode( (string) $raw );
			if ( ! is_object( $model ) ) {
				continue;
			}
			unset( $model->changedClasses, $model->selection, $model->activeClass );
			// What it needs comes along: the components it places and the forms it shows.
			self::walk_nodes( $model->tree ?? array(), function ( $n ) use ( &$queue, &$forms ) {
				if ( 'Reusable' === ( $n->el ?? '' ) && ! empty( $n->ref ) ) {
					$queue[] = (int) $n->ref;
				}
				if ( 'Form' === ( $n->el ?? '' ) && ! empty( $n->settings->form ) ) {
					$forms[ (int) $n->settings->form ] = 1;
				}
			} );
			$entry = array(
				'id'     => (int) $row['id'],
				'kind'   => $row['kind'],
				'title'  => (string) $row['title'],
				'status' => (string) $row['status'],
				'auto'   => isset( $picked[ (int) $row['id'] ] ) ? 0 : 1,
				'model'  => $model,
			);
			if ( 'template' === $row['kind'] ) {
				$entry['purpose'] = Velox_Builder::template_purpose( (int) $row['id'] );
				if ( class_exists( 'Velox_Builder_Templates' ) ) {
					$entry['cond'] = Velox_Builder_Templates::get( (int) $row['id'] );
				}
			}
			if ( 'page' === $row['kind'] && (int) $row['post_id'] && ( $p = get_post( (int) $row['post_id'] ) ) ) {
				$entry['post'] = array( 'title' => $p->post_title, 'slug' => $p->post_name, 'type' => $p->post_type );
			}
			$docs[] = $entry;
		}

		$out_forms = array();
		if ( $forms && class_exists( 'Velox_Forms' ) ) {
			foreach ( array_keys( $forms ) as $fid ) {
				$f = Velox_Forms::get_form( $fid );
				if ( $f ) {
					unset( $f['webhook'] ); // may carry a secret in its address
					$out_forms[] = $f;
				}
			}
		}

		$design = array();
		if ( in_array( 'classes', $parts, true ) ) {
			$design['classes'] = (object) Velox_Builder::class_registry();
		}
		if ( in_array( 'tokens', $parts, true ) ) {
			$design['tokens'] = Velox_Builder::tokens();
		}
		if ( in_array( 'styles', $parts, true ) ) {
			$design['styles'] = Velox_Builder::global_styles();
		}
		if ( in_array( 'fonts', $parts, true ) ) {
			$design['fonts']        = get_option( Velox_Builder::OPT_FONTS, array() );
			$design['font_display'] = get_option( Velox_Builder::OPT_FONT_DISPLAY, 'swap' );
		}
		if ( in_array( 'css', $parts, true ) ) {
			$design['css'] = Velox_Builder::global_css_files();
		}
		if ( in_array( 'js', $parts, true ) && current_user_can( 'unfiltered_html' ) ) {
			$design['js'] = Velox_Builder::global_js_files();
		}
		$up = wp_upload_dir();
		return array(
			'format'  => self::FORMAT,
			'version' => self::VERSION,
			'velox'   => defined( 'VELOX_VERSION' ) ? VELOX_VERSION : '',
			'created' => gmdate( 'c' ),
			'site'    => array( 'url' => home_url( '/' ), 'uploads' => (string) ( $up['baseurl'] ?? '' ), 'name' => get_bloginfo( 'name' ) ),
			'docs'    => $docs,
			'forms'   => $out_forms,
			'design'  => (object) $design,
		);
	}

	/* ================================================================ import */

	public static function ajax_preview() {
		self::must_admin();
		$f = self::read_file();
		wp_send_json_success( self::plan( $f ) );
	}

	/** The posted file, checked. Objects stay objects so empty maps stay {}. */
	private static function read_file() {
		$raw = isset( $_POST['file'] ) ? wp_unslash( $_POST['file'] ) : '';
		if ( '' === $raw && ! empty( $_SERVER['CONTENT_LENGTH'] ) && (int) $_SERVER['CONTENT_LENGTH'] > wp_convert_hr_to_bytes( (string) ini_get( 'post_max_size' ) ) ) {
			/* translators: %s: a size such as 8M. */
			wp_send_json_error( array( 'message' => sprintf( __( 'This file is larger than this server accepts (%s). Export fewer items at a time, or ask your host to raise post_max_size.', 'velox' ), (string) ini_get( 'post_max_size' ) ) ), 400 );
		}
		if ( strlen( $raw ) > self::MAX_BYTES ) {
			wp_send_json_error( array( 'message' => __( 'This file is too large (more than 30 MB).', 'velox' ) ), 400 );
		}
		$f = json_decode( $raw );
		if ( ! is_object( $f ) || self::FORMAT !== ( $f->format ?? '' ) ) {
			wp_send_json_error( array( 'message' => __( 'This is not a Velox Builder export file.', 'velox' ) ), 400 );
		}
		if ( (int) ( $f->version ?? 0 ) > self::VERSION ) {
			wp_send_json_error( array( 'message' => __( 'This file was made by a newer Velox. Update Velox on this site first.', 'velox' ) ), 400 );
		}
		$f->docs  = array_values( array_filter( (array) ( $f->docs ?? array() ), function ( $d ) {
			return is_object( $d ) && in_array( $d->kind ?? '', array( 'page', 'template', 'reusable' ), true ) && isset( $d->model ) && is_object( $d->model );
		} ) );
		$f->forms  = array_values( array_filter( (array) ( $f->forms ?? array() ), 'is_object' ) );
		$f->design = isset( $f->design ) && is_object( $f->design ) ? $f->design : new stdClass();
		$f->site   = isset( $f->site ) && is_object( $f->site ) ? $f->site : new stdClass();
		return $f;
	}

	/** Same site (a backup coming home): ids and image addresses are still right. */
	private static function same_site( $f ) {
		return untrailingslashit( (string) ( $f->site->url ?? '' ) ) === untrailingslashit( home_url( '/' ) );
	}

	/** What the file holds and what of it exists here already. */
	public static function plan( $f ) {
		global $wpdb;
		$t     = Velox_Builder::table();
		$same  = self::same_site( $f );
		$docs  = array();
		$js    = 0;
		foreach ( $f->docs as $d ) {
			$ex = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t} WHERE kind = %s AND title = %s ORDER BY id ASC LIMIT 1", $d->kind, (string) $d->title ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$has_js = ! empty( $d->model->page->js );
			$js    += $has_js ? 1 : 0;
			$docs[] = array( 'key' => (int) $d->id, 'kind' => $d->kind, 'title' => (string) $d->title, 'auto' => ! empty( $d->auto ), 'exists' => $ex, 'js' => $has_js );
		}
		$forms = array();
		foreach ( $f->forms as $fm ) {
			$forms[] = array( 'key' => (int) ( $fm->id ?? 0 ), 'title' => (string) ( $fm->title ?? '' ), 'exists' => self::matching_form( $fm ) );
		}
		$ds = $f->design;
		$design = array();
		if ( isset( $ds->classes ) ) {
			$mine = Velox_Builder::class_registry();
			$n = $diff = 0;
			foreach ( (array) $ds->classes as $name => $rules ) {
				if ( ! isset( $mine[ $name ] ) ) {
					$n++;
				} elseif ( self::norm( $mine[ $name ] ) !== self::norm( $rules ) ) {
					$diff++;
				}
			}
			$design['classes'] = array( 'total' => count( (array) $ds->classes ), 'new' => $n, 'differ' => $diff );
		}
		if ( isset( $ds->tokens ) ) {
			$cmp = self::token_compare( self::arr( $ds->tokens ), Velox_Builder::tokens() );
			$design['tokens'] = $cmp;
		}
		if ( isset( $ds->styles ) ) {
			$design['styles'] = array( 'differ' => self::norm( self::arr( $ds->styles ) ) !== self::norm( Velox_Builder::global_styles() ) );
		}
		if ( isset( $ds->fonts ) ) {
			$have = array_map( function ( $x ) { return strtolower( (string) ( $x['name'] ?? '' ) ); }, (array) get_option( Velox_Builder::OPT_FONTS, array() ) );
			$names = array();
			foreach ( (array) $ds->fonts as $fo ) {
				$fo = (array) $fo;
				if ( ! in_array( strtolower( (string) ( $fo['name'] ?? '' ) ), $have, true ) ) {
					$names[] = (string) ( $fo['name'] ?? '' );
				}
			}
			$design['fonts'] = array( 'new' => $names );
		}
		if ( isset( $ds->css ) ) {
			$here = array_map( function ( $x ) { return (string) ( $x['css'] ?? '' ); }, Velox_Builder::global_css_files() );
			$new  = 0;
			foreach ( (array) $ds->css as $cf ) {
				$new += in_array( (string) ( ( (array) $cf )['css'] ?? '' ), $here, true ) ? 0 : 1;
			}
			$design['css'] = array( 'files' => $new );
		}
		if ( isset( $ds->js ) ) {
			$design['js'] = array( 'files' => count( (array) $ds->js ), 'allowed' => current_user_can( 'unfiltered_html' ) );
		}
		return array(
			'site'   => array( 'name' => (string) ( $f->site->name ?? '' ), 'url' => (string) ( $f->site->url ?? '' ), 'same' => $same, 'velox' => (string) ( $f->velox ?? '' ), 'created' => (string) ( $f->created ?? '' ) ),
			'docs'   => $docs,
			'forms'  => $forms,
			'design' => (object) $design,
			'images' => $same ? 0 : count( self::image_urls( $f ) ),
			'links'  => ! $same && '' !== (string) ( $f->site->url ?? '' ),
			'js'     => $js,
			'mayJs'  => current_user_can( 'unfiltered_html' ),
		);
	}

	public static function ajax_run() {
		self::must_admin();
		$f   = self::read_file();
		$ch  = json_decode( isset( $_POST['choices'] ) ? wp_unslash( $_POST['choices'] ) : '{}', true );
		$ch  = is_array( $ch ) ? $ch : array();
		wp_send_json_success( self::run( $f, $ch ) );
	}

	/**
	 * Import with the preview's choices:
	 *  docs    => [ key => new | replace | skip ]
	 *  classes / tokens => keep | file | skip   (keep: this site's version wins where both have one)
	 *  styles, fonts, css, js, images, links => bool
	 */
	public static function run( $f, $ch ) {
		global $wpdb;
		$t      = Velox_Builder::table();
		$same   = self::same_site( $f );
		$now    = current_time( 'mysql' );
		$may_js = current_user_can( 'unfiltered_html' );
		$src    = untrailingslashit( (string) ( $f->site->url ?? '' ) );
		$src_up = untrailingslashit( (string) ( $f->site->uploads ?? '' ) );
		$links  = ! $same && '' !== $src && ! empty( $ch['links'] );
		$images = ! $same && ! empty( $ch['images'] );
		$out    = array( 'docs' => array(), 'forms' => array(), 'design' => array(), 'skipped' => 0 );

		// 1. Forms first: imported pages point at them.
		$fmap = array();
		foreach ( $f->forms as $fm ) {
			$key = (int) ( $fm->id ?? 0 );
			$ex  = self::matching_form( $fm );
			if ( $ex ) {
				$fmap[ $key ] = $ex;
				continue;
			}
			if ( ! class_exists( 'Velox_Forms' ) ) {
				continue;
			}
			$a = self::arr( $fm );
			$a['id']      = 0;
			$a['webhook'] = '';
			// Notifications go to this site's address, not to the old site's owner.
			foreach ( (array) ( $a['emails'] ?? array() ) as $i => $em ) {
				if ( 'admin' === ( $em['type'] ?? '' ) ) {
					$a['emails'][ $i ]['to'] = get_option( 'admin_email' );
				}
			}
			if ( $links && ! empty( $a['redirect'] ) ) {
				$a['redirect'] = self::swap_url( $a['redirect'], $src, $src_up );
			}
			$r = Velox_Forms::save_form( $a );
			if ( ! empty( $r['id'] ) ) {
				$fmap[ $key ]    = (int) $r['id'];
				$out['forms'][] = array( 'title' => (string) ( $a['title'] ?? '' ), 'id' => (int) $r['id'] );
			}
		}

		// 2. Every document gets its id here first, so references can be rewritten.
		$dmap = array();
		$todo = array();
		foreach ( $f->docs as $d ) {
			$key = (int) $d->id;
			$act = $ch['docs'][ $key ] ?? 'new';
			$ex  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t} WHERE kind = %s AND title = %s ORDER BY id ASC LIMIT 1", $d->kind, (string) $d->title ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( 'skip' === $act ) {
				if ( $ex ) {
					$dmap[ $key ] = $ex; // what it uses is this site's version of the same name
				}
				$out['skipped']++;
				continue;
			}
			if ( 'replace' === $act && $ex ) {
				$dmap[ $key ] = $ex;
				$todo[]       = array( $d, $ex, 'replace' );
				continue;
			}
			$title = (string) $d->title;
			if ( $ex ) {
				/* translators: %s: name of the page, template or component. */
				$title = sprintf( __( '%s (imported)', 'velox' ), $title );
			}
			$status = 'reusable' === $d->kind ? 'published' : 'draft';
			$wpdb->insert( $t, array( 'kind' => $d->kind, 'title' => $title, 'data' => '{"tree":[],"classes":{},"content":{}}', 'css_size' => 0, 'status' => $status, 'post_id' => null, 'updated' => $now, 'created' => $now ), array( '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' ) );
			$nid = (int) $wpdb->insert_id;
			if ( ! $nid ) {
				continue;
			}
			$dmap[ $key ] = $nid;
			$todo[]       = array( $d, $nid, 'new' );
		}

		// 3. Write each model with its references pointing here.
		$written      = array();
		$written_keys = array();
		foreach ( $todo as $job ) {
			list( $d, $id, $how ) = $job;
			$m = json_decode( wp_json_encode( $d->model ) ); // a copy
			if ( ! $may_js && isset( $m->page ) && is_object( $m->page ) ) {
				unset( $m->page->js );
			}
			unset( $m->changedClasses );
			self::walk_nodes( $m->tree ?? array(), function ( $n ) use ( $dmap, $fmap, $same ) {
				if ( 'Reusable' === ( $n->el ?? '' ) && isset( $n->ref ) ) {
					$n->ref = isset( $dmap[ (int) $n->ref ] ) ? $dmap[ (int) $n->ref ] : 0;
				}
				if ( 'Form' === ( $n->el ?? '' ) && isset( $n->settings ) && is_object( $n->settings ) && ! empty( $n->settings->form ) ) {
					$n->settings->form = isset( $fmap[ (int) $n->settings->form ] ) ? (string) $fmap[ (int) $n->settings->form ] : '';
				}
				// A media-library id from another site means another image here.
				if ( ! $same && isset( $n->att ) ) {
					unset( $n->att );
				}
				if ( ! $same && isset( $n->props ) && is_object( $n->props ) ) {
					foreach ( $n->props as $p ) {
						if ( is_object( $p ) && isset( $p->att ) ) {
							unset( $p->att );
						}
					}
				}
			} );
			if ( $links ) {
				$m = self::map_strings( $m, function ( $s ) use ( $src, $src_up ) { return self::swap_url( $s, $src, $src_up ); } );
			}
			$data = wp_json_encode( $m );
			if ( 'replace' === $how ) {
				$row = $wpdb->get_row( $wpdb->prepare( "SELECT status, data, draft FROM {$t} WHERE id = %d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				if ( class_exists( 'Velox_Builder_Revisions' ) && $row ) {
					// What was here stays one click away in History → Saved versions.
					$old = ( null !== $row['draft'] && '' !== $row['draft'] ) ? $row['draft'] : $row['data'];
					Velox_Builder_Revisions::record( $id, 'save', (string) $old, (string) $d->title );
				}
				// Something live is not changed for visitors until it is published again.
				$field = ( $row && 'published' === $row['status'] ) ? 'draft' : 'data';
				$wpdb->update( $t, array( $field => $data, 'updated' => $now ), array( 'id' => $id ) );
			} else {
				$wpdb->update( $t, array( 'data' => $data, 'updated' => $now ), array( 'id' => $id ) );
				if ( 'template' === $d->kind ) {
					if ( ! empty( $d->purpose ) ) {
						Velox_Builder::set_template_purpose( $id, (string) $d->purpose );
					}
					if ( ! empty( $d->cond ) && class_exists( 'Velox_Builder_Templates' ) ) {
						Velox_Builder_Templates::save( $id, self::arr( $d->cond ) );
					}
				}
			}
			if ( class_exists( 'Velox_Builder_Revisions' ) ) {
				Velox_Builder_Revisions::record( $id, 'import', $data, (string) $d->title );
			}
			if ( 'reusable' === $d->kind && class_exists( 'Velox_Builder_Render' ) ) {
				Velox_Builder_Render::write_css_for( $id );
			}
			$written[]     = $id;
			$written_keys[] = (int) $d->id;
			$out['docs'][] = array( 'id' => $id, 'kind' => $d->kind, 'title' => (string) $wpdb->get_var( $wpdb->prepare( "SELECT title FROM {$t} WHERE id = %d", $id ) ), 'how' => $how, 'edit' => Velox_Builder::edit_url( $id, 'page' === $d->kind ? '' : $d->kind ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		// 4. The design system.
		$ds = $f->design;
		$classes_in = array();
		if ( isset( $ds->classes ) && in_array( $ch['classes'] ?? 'keep', array( 'keep', 'file' ), true ) ) {
			$mine = Velox_Builder::class_registry();
			$put  = array();
			foreach ( self::arr( $ds->classes ) as $name => $rules ) {
				if ( isset( $mine[ $name ] ) && 'keep' === $ch['classes'] ) {
					continue;
				}
				$put[ $name ] = $links ? self::map_strings( $rules, function ( $s ) use ( $src, $src_up ) { return self::swap_url( $s, $src, $src_up ); } ) : $rules;
			}
			if ( $put ) {
				Velox_Builder::registry_put( $put );
			}
			$classes_in = array_keys( $put );
			$out['design']['classes'] = count( $put );
		}
		if ( isset( $ds->tokens ) && in_array( $ch['tokens'] ?? 'keep', array( 'keep', 'file' ), true ) ) {
			$before = Velox_Builder::tokens();
			$merged = Velox_Builder::clean_tokens( self::token_merge( $before, self::arr( $ds->tokens ), 'file' === $ch['tokens'] ) );
			if ( self::norm( $merged ) !== self::norm( Velox_Builder::clean_tokens( $before ) ) ) {
				update_option( Velox_Builder::OPT_TOKENS, $merged );
				$out['design']['tokens'] = 1;
			}
		}
		if ( isset( $ds->styles ) && ! empty( $ch['styles'] ) ) {
			update_option( Velox_Builder::OPT_GLOBAL_STYLES, Velox_Builder::clean_global_styles( self::arr( $ds->styles ) ) );
			$out['design']['styles'] = 1;
		}
		if ( isset( $ds->fonts ) && ! empty( $ch['fonts'] ) ) {
			$have = (array) get_option( Velox_Builder::OPT_FONTS, array() );
			$names = array_map( function ( $x ) { return strtolower( (string) ( $x['name'] ?? '' ) ); }, $have );
			$added = 0;
			foreach ( self::arr( $ds->fonts ) as $fo ) {
				if ( ! in_array( strtolower( (string) ( $fo['name'] ?? '' ) ), $names, true ) ) {
					$have[] = $fo;
					$added++;
				}
			}
			update_option( Velox_Builder::OPT_FONTS, Velox_Builder::clean_fonts( $have ) );
			$out['design']['fonts'] = $added;
		}
		if ( isset( $ds->css ) && ! empty( $ch['css'] ) ) {
			$have  = Velox_Builder::global_css_files();
			$added = 0;
			foreach ( self::arr( $ds->css ) as $cf ) {
				$dupe = false;
				foreach ( $have as $h ) {
					if ( (string) ( $h['css'] ?? '' ) === (string) ( $cf['css'] ?? '' ) ) {
						$dupe = true;
					}
				}
				if ( ! $dupe ) {
					$have[] = $cf;
					$added++;
				}
			}
			update_option( Velox_Builder::OPT_CSS, Velox_Builder::clean_css_files( $have ) );
			$out['design']['css'] = $added;
		}
		if ( isset( $ds->js ) && ! empty( $ch['js'] ) && $may_js ) {
			$have  = Velox_Builder::global_js_files();
			$added = 0;
			foreach ( self::arr( $ds->js ) as $jf ) {
				$jf['on'] = 0; // scripts from a file start switched off
				$have[]   = $jf;
				$added++;
			}
			update_option( Velox_Builder::OPT_JS, Velox_Builder::clean_js_files( $have ) );
			$out['design']['js'] = $added;
		}
		Velox_Builder::purge_cache_for();

		// 5. Images: copied a few at a time by ajax_images, then the documents point at the copies.
		$out['images'] = 0;
		if ( $images && ( $written || $classes_in ) ) {
			$urls = self::image_urls( $f, $written_keys, $classes_in );
			if ( $urls ) {
				$token = wp_generate_password( 20, false, false );
				set_transient( 'velox_bimport_' . $token, array( 'user' => get_current_user_id(), 'docs' => $written, 'classes' => $classes_in, 'todo' => $urls, 'map' => array(), 'total' => count( $urls ) ), DAY_IN_SECONDS );
				$out['images'] = count( $urls );
				$out['token']  = $token;
			}
		}
		return $out;
	}

	/** Copy the next few images, and when all are done point the documents at them. */
	public static function ajax_images() {
		self::must_admin();
		if ( ! function_exists( 'media_handle_sideload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}
		$token = isset( $_POST['token'] ) ? preg_replace( '/[^a-zA-Z0-9]/', '', wp_unslash( $_POST['token'] ) ) : '';
		$job   = $token ? get_transient( 'velox_bimport_' . $token ) : false;
		if ( ! is_array( $job ) || (int) $job['user'] !== get_current_user_id() ) {
			wp_send_json_error( array( 'message' => __( 'This import has expired. Run it again.', 'velox' ) ), 400 );
		}
		$t0 = time();
		while ( $job['todo'] && time() - $t0 < 15 ) {
			$url = array_shift( $job['todo'] );
			$job['map'][ $url ] = self::sideload( $url );
		}
		if ( $job['todo'] ) {
			set_transient( 'velox_bimport_' . $token, $job, DAY_IN_SECONDS );
			wp_send_json_success( array( 'done' => $job['total'] - count( $job['todo'] ), 'total' => $job['total'], 'finished' => false ) );
		}
		$ok = array_filter( $job['map'] );
		self::apply_images( $job['docs'], $job['classes'], $ok );
		delete_transient( 'velox_bimport_' . $token );
		wp_send_json_success( array( 'done' => $job['total'], 'total' => $job['total'], 'finished' => true, 'copied' => count( $ok ), 'failed' => count( $job['map'] ) - count( $ok ) ) );
	}

	/** One image into the media library: [ id, url ] or false. */
	private static function sideload( $url ) {
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		if ( ! preg_match( '/\.(' . self::IMG_EXT . ')$/i', $path ) ) {
			return false;
		}
		$tmp = download_url( $url, 20 ); // wp_safe_remote_get: no local or private addresses
		if ( is_wp_error( $tmp ) ) {
			return false;
		}
		$id = media_handle_sideload( array( 'name' => wp_basename( $path ), 'tmp_name' => $tmp ), 0 );
		if ( is_wp_error( $id ) ) {
			@unlink( $tmp ); // phpcs:ignore
			return false;
		}
		return array( (int) $id, (string) wp_get_attachment_url( $id ) );
	}

	/** Point the imported documents (and classes) at the copied images. */
	private static function apply_images( $doc_ids, $class_names, $map ) {
		global $wpdb;
		if ( ! $map ) {
			return;
		}
		$t    = Velox_Builder::table();
		$swap = function ( $s ) use ( $map ) {
			foreach ( $map as $from => $to ) {
				if ( false !== strpos( $s, $from ) ) {
					$s = str_replace( $from, $to[1], $s );
				}
			}
			return $s;
		};
		foreach ( $doc_ids as $id ) {
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT data, draft FROM {$t} WHERE id = %d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( ! $row ) {
				continue;
			}
			$upd = array();
			foreach ( array( 'data', 'draft' ) as $col ) {
				$m = ( null !== $row[ $col ] && '' !== $row[ $col ] ) ? json_decode( $row[ $col ] ) : null;
				if ( ! is_object( $m ) ) {
					continue;
				}
				// An image element whose picture was copied is a media-library image again (sizes, srcset).
				$content = isset( $m->content ) && is_object( $m->content ) ? $m->content : new stdClass();
				self::walk_nodes( $m->tree ?? array(), function ( $n ) use ( $content, $map ) {
					if ( 'Image' === ( $n->el ?? '' ) && isset( $content->{$n->id} ) && is_string( $content->{$n->id} ) && isset( $map[ $content->{$n->id} ] ) ) {
						$n->att = $map[ $content->{$n->id} ][0];
					}
				} );
				$upd[ $col ] = wp_json_encode( self::map_strings( $m, $swap ) );
			}
			if ( $upd ) {
				$wpdb->update( $t, $upd, array( 'id' => $id ) );
				if ( class_exists( 'Velox_Builder_Render' ) ) {
					Velox_Builder_Render::write_css_for( $id );
				}
			}
		}
		if ( $class_names ) {
			$reg = Velox_Builder::class_registry();
			$put = array();
			foreach ( $class_names as $c ) {
				if ( isset( $reg[ $c ] ) ) {
					$put[ $c ] = self::map_strings( $reg[ $c ], $swap );
				}
			}
			if ( $put ) {
				Velox_Builder::registry_put( $put );
			}
		}
		Velox_Builder::purge_cache_for();
	}

	/* =============================================================== helpers */

	private static function must_admin() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Not allowed.', 'velox' ) ), 403 );
		}
	}

	/** Visit every node of a tree (objects), children too. */
	public static function walk_nodes( $nodes, $fn ) {
		foreach ( (array) $nodes as $n ) {
			if ( ! is_object( $n ) ) {
				continue;
			}
			$fn( $n );
			if ( ! empty( $n->children ) ) {
				self::walk_nodes( $n->children, $fn );
			}
		}
	}

	/** Apply $fn to every string inside a value (objects and arrays alike). */
	public static function map_strings( $v, $fn ) {
		if ( is_string( $v ) ) {
			return $fn( $v );
		}
		if ( is_array( $v ) ) {
			foreach ( $v as $k => $x ) {
				$v[ $k ] = self::map_strings( $x, $fn );
			}
			return $v;
		}
		if ( is_object( $v ) ) {
			foreach ( get_object_vars( $v ) as $k => $x ) {
				$v->$k = self::map_strings( $x, $fn );
			}
		}
		return $v;
	}

	/** Links to the old site point here; its uploads are left alone (they are copied, or still load from there). */
	private static function swap_url( $s, $src, $src_up ) {
		if ( '' === $src || false === strpos( $s, $src ) ) {
			return $s;
		}
		$keep = '' !== $src_up ? "\x00VXUP\x00" : '';
		if ( $keep ) {
			$s = str_replace( $src_up, $keep, $s );
		}
		$s = str_replace( $src . '/', trailingslashit( home_url() ), $s );
		$s = str_replace( $src, untrailingslashit( home_url() ), $s );
		return $keep ? str_replace( $keep, $src_up, $s ) : $s;
	}

	/** Every image address from the old site's uploads in the imported documents. */
	private static function image_urls( $f, $doc_keys = null, $class_names = null ) {
		$up = untrailingslashit( (string) ( $f->site->uploads ?? '' ) );
		// Only finds addresses; the download itself refuses local and private hosts (download_url).
		if ( '' === $up || ! preg_match( '#^https?://[^/\s]+#i', $up ) ) {
			return array();
		}
		$found = array();
		$re    = '~' . preg_quote( $up, '~' ) . '/[^\s"\'()<>]+?\.(?:' . self::IMG_EXT . ')(?=[\s"\'()<>?#]|$)~i';
		$scan  = function ( $s ) use ( $re, &$found ) {
			if ( preg_match_all( $re, $s, $m ) ) {
				foreach ( $m[0] as $u ) {
					$found[ $u ] = 1;
				}
			}
			return $s;
		};
		// Only what is imported: a skipped page's images are not copied.
		foreach ( $f->docs as $d ) {
			if ( null === $doc_keys || in_array( (int) $d->id, $doc_keys, true ) ) {
				self::map_strings( json_decode( wp_json_encode( $d->model ) ), $scan );
			}
		}
		if ( isset( $f->design->classes ) ) {
			$cls = self::arr( $f->design->classes );
			if ( null !== $class_names ) {
				$cls = array_intersect_key( $cls, array_flip( $class_names ) );
			}
			self::map_strings( $cls, $scan );
		}
		return array_slice( array_keys( $found ), 0, 500 );
	}

	/** A form here with the same name and the same fields. */
	private static function matching_form( $fm ) {
		if ( ! class_exists( 'Velox_Forms' ) ) {
			return 0;
		}
		$keys = function ( $f ) { return implode( ',', array_map( function ( $x ) { return (string) ( $x['key'] ?? '' ); }, (array) ( $f['fields'] ?? array() ) ) ); };
		$want = self::arr( $fm );
		foreach ( Velox_Forms::forms() as $id => $f ) {
			if ( (string) ( $f['title'] ?? '' ) === (string) ( $want['title'] ?? '' ) && $keys( $f ) === $keys( $want ) ) {
				return (int) $id;
			}
		}
		return 0;
	}

	private static function token_compare( $in, $mine ) {
		$new = $diff = 0;
		foreach ( array( 'colors', 'custom' ) as $g ) {
			$have = array();
			foreach ( (array) ( $mine[ $g ] ?? array() ) as $x ) {
				$have[ (string) ( $x['name'] ?? '' ) ] = (string) ( $x['value'] ?? '' );
			}
			foreach ( (array) ( $in[ $g ] ?? array() ) as $x ) {
				$n = (string) ( $x['name'] ?? '' );
				if ( ! isset( $have[ $n ] ) ) {
					$new++;
				} elseif ( $have[ $n ] !== (string) ( $x['value'] ?? '' ) ) {
					$diff++;
				}
			}
		}
		$scales = self::norm( $in['scales'] ?? array() ) !== self::norm( $mine['scales'] ?? array() );
		return array( 'new' => $new, 'differ' => $diff, 'scales' => $scales );
	}

	/** This site's tokens plus the file's; on a clash $file_wins decides. */
	private static function token_merge( $mine, $in, $file_wins ) {
		$out = $mine;
		foreach ( array( 'colors', 'custom' ) as $g ) {
			$idx = array();
			foreach ( (array) ( $out[ $g ] ?? array() ) as $i => $x ) {
				$idx[ (string) ( $x['name'] ?? '' ) ] = $i;
			}
			foreach ( (array) ( $in[ $g ] ?? array() ) as $x ) {
				$n = (string) ( $x['name'] ?? '' );
				if ( ! isset( $idx[ $n ] ) ) {
					$out[ $g ][] = $x;
				} elseif ( $file_wins ) {
					$out[ $g ][ $idx[ $n ] ] = $x;
				}
			}
		}
		$out['spacing'] = array_values( array_unique( array_merge( (array) ( $mine['spacing'] ?? array() ), (array) ( $in['spacing'] ?? array() ) ) ) );
		if ( $file_wins && ! empty( $in['scales'] ) ) {
			$out['scales'] = $in['scales'];
		}
		return $out;
	}

	/** Objects to arrays, all the way down. */
	private static function arr( $v ) {
		return json_decode( wp_json_encode( $v ), true );
	}

	/** A comparable form of a value: keys sorted, all the way down. */
	private static function norm( $v ) {
		$v = self::arr( $v );
		$sort = function ( $x ) use ( &$sort ) {
			if ( is_array( $x ) ) {
				ksort( $x );
				foreach ( $x as $k => $y ) {
					$x[ $k ] = $sort( $y );
				}
			}
			return $x;
		};
		return wp_json_encode( $sort( $v ) );
	}
}
