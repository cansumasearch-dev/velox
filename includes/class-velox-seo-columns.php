<?php
/**
 * Velox — SEO columns in the Posts & Pages list tables.
 *
 * Adds "SEO Title" and "SEO Description" columns to the edit.php list for posts
 * and pages (only when the SEO module is on). The columns:
 *   - show the current _velox_seo_title / _velox_seo_desc, with a clear
 *     placeholder when empty so you can spot what still needs writing;
 *   - are toggled on/off from the native Screen Options ("Ansicht anpassen")
 *     panel, like any other column — no separate Velox setting;
 *   - are editable two ways: WordPress Quick Edit, and click-the-cell inline
 *     editing that saves over AJAX.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Velox_Seo_Columns {

	/** Only these list tables get the columns. */
	const TYPES = array( 'post', 'page' );

	public static function init() {
		if ( ! Velox_Settings::get( 'module_seo', true ) ) {
			return;
		}
		foreach ( self::TYPES as $pt ) {
			add_filter( "manage_{$pt}_posts_columns", array( __CLASS__, 'columns' ) );
			add_action( "manage_{$pt}_posts_custom_column", array( __CLASS__, 'render_column' ), 10, 2 );
		}
		add_action( 'quick_edit_custom_box', array( __CLASS__, 'quick_edit_box' ), 10, 2 );
		add_action( 'save_post', array( __CLASS__, 'save_quick_edit' ), 10, 1 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	/* --------------------------------------------------------------- columns */

	public static function columns( $cols ) {
		// Insert after the title column so they read naturally.
		$out = array();
		foreach ( $cols as $key => $label ) {
			$out[ $key ] = $label;
			if ( 'title' === $key ) {
				$out['velox_seo_title'] = __( 'SEO Title', 'velox' );
				$out['velox_seo_desc']  = __( 'SEO Description', 'velox' );
			}
		}
		// If for some reason there was no title column, append.
		if ( ! isset( $out['velox_seo_title'] ) ) {
			$out['velox_seo_title'] = __( 'SEO Title', 'velox' );
			$out['velox_seo_desc']  = __( 'SEO Description', 'velox' );
		}
		return $out;
	}

	public static function render_column( $column, $post_id ) {
		if ( 'velox_seo_title' !== $column && 'velox_seo_desc' !== $column ) {
			return;
		}
		$key   = 'velox_seo_title' === $column ? '_velox_seo_title' : '_velox_seo_desc';
		$value = (string) get_post_meta( $post_id, $key, true );
		$empty = '' === trim( $value );

		printf(
			'<span class="velox-seocol %1$s" data-post="%2$d" data-key="%3$s" data-value="%4$s" tabindex="0" role="button" title="%5$s">%6$s</span>',
			$empty ? 'is-empty' : '',
			(int) $post_id,
			esc_attr( 'velox_seo_title' === $column ? 'title' : 'desc' ),
			esc_attr( $value ),
			esc_attr__( 'Click to edit', 'velox' ),
			$empty ? esc_html__( '— add —', 'velox' ) : esc_html( $value )
		);
	}

	/* ------------------------------------------------------------ quick edit */

	public static function quick_edit_box( $column, $post_type ) {
		if ( ! in_array( $post_type, self::TYPES, true ) ) {
			return;
		}
		if ( 'velox_seo_title' === $column ) {
			// Nonce once, on the first of our two boxes.
			wp_nonce_field( 'velox_seocol', 'velox_seocol_nonce' );
			?>
			<fieldset class="inline-edit-col-left">
				<div class="inline-edit-col velox-qe">
					<label class="inline-edit-group">
						<span class="title"><?php esc_html_e( 'SEO Title', 'velox' ); ?></span>
						<input type="text" name="velox_seo_title" class="ptitle velox-qe-title" value="">
					</label>
				</div>
			</fieldset>
			<?php
		} elseif ( 'velox_seo_desc' === $column ) {
			?>
			<fieldset class="inline-edit-col-left">
				<div class="inline-edit-col velox-qe">
					<label class="inline-edit-group">
						<span class="title"><?php esc_html_e( 'SEO Description', 'velox' ); ?></span>
						<textarea name="velox_seo_desc" rows="2" class="velox-qe-desc"></textarea>
					</label>
				</div>
			</fieldset>
			<?php
		}
	}

	public static function save_quick_edit( $post_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! isset( $_POST['velox_seocol_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['velox_seocol_nonce'] ) ), 'velox_seocol' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( isset( $_POST['velox_seo_title'] ) ) {
			update_post_meta( $post_id, '_velox_seo_title', sanitize_text_field( wp_unslash( $_POST['velox_seo_title'] ) ) );
		}
		if ( isset( $_POST['velox_seo_desc'] ) ) {
			update_post_meta( $post_id, '_velox_seo_desc', sanitize_textarea_field( wp_unslash( $_POST['velox_seo_desc'] ) ) );
		}
	}

	/* --------------------------------------------------------- inline (ajax) */

	public static function assets( $hook ) {
		if ( 'edit.php' !== $hook ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->post_type, self::TYPES, true ) ) {
			return;
		}
		$ver = defined( 'VELOX_VERSION' ) ? VELOX_VERSION : '1';
		wp_enqueue_style( 'velox-seocol', VELOX_URL . 'assets/seo-columns.css', array(), $ver );
		wp_enqueue_script( 'velox-seocol', VELOX_URL . 'assets/seo-columns.js', array(), $ver, true );
		wp_localize_script( 'velox-seocol', 'VELOX_SEOCOL', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'velox_nonce' ),
			'i18n'    => array(
				'add'     => __( '— add —', 'velox' ),
				'saving'  => __( 'Saving…', 'velox' ),
				'saved'   => __( 'Saved', 'velox' ),
				'failed'  => __( 'Save failed', 'velox' ),
				'hint'    => __( 'Enter to save · Esc to cancel', 'velox' ),
			),
		) );
	}

	/** AJAX: save one field inline. Routed from the dispatcher. */
	public static function ajax_save() {
		$post_id = isset( $_POST['post'] ) ? (int) $_POST['post'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$field   = isset( $_POST['field'] ) ? sanitize_key( $_POST['field'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$value   = isset( $_POST['value'] ) ? wp_unslash( $_POST['value'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Not allowed.', 'velox' ) ), 403 );
		}
		if ( 'title' === $field ) {
			$clean = sanitize_text_field( $value );
			update_post_meta( $post_id, '_velox_seo_title', $clean );
		} elseif ( 'desc' === $field ) {
			$clean = sanitize_textarea_field( $value );
			update_post_meta( $post_id, '_velox_seo_desc', $clean );
		} else {
			wp_send_json_error( array( 'message' => __( 'Unknown field.', 'velox' ) ), 400 );
		}
		wp_send_json_success( array( 'value' => $clean ) );
	}
}
