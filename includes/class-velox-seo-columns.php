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
			add_filter( "bulk_actions-edit-{$pt}", array( __CLASS__, 'bulk_actions' ) );
			add_filter( "handle_bulk_actions-edit-{$pt}", array( __CLASS__, 'handle_bulk' ), 10, 3 );
		}
		add_action( 'admin_notices', array( __CLASS__, 'bulk_notice' ) );
		add_action( 'quick_edit_custom_box', array( __CLASS__, 'quick_edit_box' ), 10, 2 );
		add_action( 'save_post', array( __CLASS__, 'save_quick_edit' ), 10, 1 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	/* --------------------------------------------------------------- columns */

	public static function columns( $cols ) {
		// Insert after the title column so they read naturally.
		$new = array(
			'velox_seo_title' => __( 'SEO Title', 'velox' ),
			'velox_seo_desc'  => __( 'SEO Description', 'velox' ),
			'velox_seo_kw'    => __( 'Focus keyword', 'velox' ),
			'velox_seo_index' => __( 'Index, Links & Sitemap', 'velox' ),
		);
		$out = array();
		foreach ( $cols as $key => $label ) {
			$out[ $key ] = $label;
			if ( 'title' === $key ) {
				$out += $new;
			}
		}
		// If for some reason there was no title column, append.
		if ( ! isset( $out['velox_seo_title'] ) ) {
			$out += $new;
		}
		return $out;
	}

	public static function render_column( $column, $post_id ) {
		switch ( $column ) {
			case 'velox_seo_title':
			case 'velox_seo_desc':
				self::render_meta_cell( $column, $post_id );
				break;
			case 'velox_seo_kw':
				self::render_keyword_cell( $post_id );
				break;
			case 'velox_seo_index':
				self::render_index_cell( $post_id );
				break;
		}
	}

	/** Editable SEO title / description cell, now with a length badge. */
	private static function render_meta_cell( $column, $post_id ) {
		$is_title = ( 'velox_seo_title' === $column );
		$key      = $is_title ? '_velox_seo_title' : '_velox_seo_desc';
		$value    = (string) get_post_meta( $post_id, $key, true );
		$empty    = '' === trim( $value );

		printf(
			'<span class="velox-seocol %1$s" data-post="%2$d" data-key="%3$s" data-value="%4$s" tabindex="0" role="button" title="%5$s">%6$s</span>',
			$empty ? 'is-empty' : '',
			(int) $post_id,
			esc_attr( $is_title ? 'title' : 'desc' ),
			esc_attr( $value ),
			esc_attr__( 'Click to edit', 'velox' ),
			$empty ? esc_html__( '— add —', 'velox' ) : esc_html( $value )
		);

		if ( ! $empty ) {
			$len   = function_exists( 'mb_strlen' ) ? mb_strlen( $value ) : strlen( $value );
			$grade = self::length_grade( $is_title ? 'title' : 'desc', $len );
			printf(
				'<span class="velox-seolen is-%1$s" title="%3$s">%2$d</span>',
				esc_attr( $grade ),
				(int) $len,
				esc_attr( self::length_hint( $is_title ? 'title' : 'desc' ) )
			);
		}
	}

	/** Focus keyword — display only. */
	private static function render_keyword_cell( $post_id ) {
		$kw = (string) get_post_meta( $post_id, '_velox_seo_focus_kw', true );
		if ( '' === trim( $kw ) ) {
			echo '<span class="velox-seocol-muted">' . esc_html__( '— none —', 'velox' ) . '</span>';
			return;
		}
		echo '<span class="velox-seo-kw">' . esc_html( $kw ) . '</span>';
	}

	/** Index + Follow + Sitemap status — clickable toggles that save inline. */
	private static function render_index_cell( $post_id ) {
		$noindex  = '1' === (string) get_post_meta( $post_id, '_velox_seo_noindex', true );
		$nofollow = '1' === (string) get_post_meta( $post_id, '_velox_seo_nofollow', true );
		$excluded = '1' === (string) get_post_meta( $post_id, 'sitemap_exclude', true );
		echo '<span class="velox-seo-idx" data-post="' . (int) $post_id . '">';
		printf(
			'<button type="button" class="velox-seo-toggle %1$s" data-flag="noindex" data-on="%2$d" title="%3$s">%4$s</button>',
			$noindex ? 'is-off' : 'is-on',
			$noindex ? 0 : 1,
			esc_attr__( 'Click to toggle indexing', 'velox' ),
			$noindex ? esc_html__( 'Noindex', 'velox' ) : esc_html__( 'Index', 'velox' )
		);
		printf(
			'<button type="button" class="velox-seo-toggle %1$s" data-flag="nofollow" data-on="%2$d" title="%3$s">%4$s</button>',
			$nofollow ? 'is-off' : 'is-on',
			$nofollow ? 0 : 1,
			esc_attr__( 'Click to toggle link following', 'velox' ),
			$nofollow ? esc_html__( 'Nofollow', 'velox' ) : esc_html__( 'Follow', 'velox' )
		);
		printf(
			'<button type="button" class="velox-seo-toggle %1$s" data-flag="sitemap" data-on="%2$d" title="%3$s">%4$s</button>',
			$excluded ? 'is-off' : 'is-on',
			$excluded ? 0 : 1,
			esc_attr__( 'Click to include or exclude this page from the sitemap', 'velox' ),
			$excluded ? esc_html__( 'Excluded', 'velox' ) : esc_html__( 'Included', 'velox' )
		);
		echo '</span>';
	}

	/** Sitemap in/excluded badge — display only. */
	private static function render_sitemap_cell( $post_id ) {
		$excluded = '1' === (string) get_post_meta( $post_id, 'sitemap_exclude', true );
		// Sitemap inclusion is controlled ONLY by the exclude toggle — noindex has
		// no effect, so a noindex page still shows "In sitemap" unless excluded.
		if ( $excluded ) {
			echo '<span class="velox-seo-badge is-muted" title="' . esc_attr__( 'Excluded from the sitemap in this page’s Velox panel.', 'velox' ) . '">' . esc_html__( 'Excluded', 'velox' ) . '</span>';
		} else {
			echo '<span class="velox-seo-badge is-ok">' . esc_html__( 'In sitemap', 'velox' ) . '</span>';
		}
	}

	/* ---- length grading ---- */

	private static function length_grade( $field, $len ) {
		if ( 'title' === $field ) {
			if ( $len >= 30 && $len <= 60 ) { return 'good'; }
			if ( $len > 70 ) { return 'bad'; }
			return 'warn'; // <30 or 60–70
		}
		// description
		if ( $len >= 120 && $len <= 160 ) { return 'good'; }
		if ( $len > 180 || $len < 70 ) { return 'bad'; }
		return 'warn'; // 70–120 or 160–180
	}

	private static function length_hint( $field ) {
		return 'title' === $field
			? __( 'Aim for 30–60 characters. Google truncates longer titles.', 'velox' )
			: __( 'Aim for 120–160 characters. Google truncates longer descriptions.', 'velox' );
	}

	/* ------------------------------------------------------ bulk actions */

	/** Add Velox SEO options to the list-table Bulk Actions dropdown. */
	public static function bulk_actions( $actions ) {
		$actions['velox_seo_noindex']   = __( 'Velox: set Noindex', 'velox' );
		$actions['velox_seo_index']     = __( 'Velox: set Index', 'velox' );
		$actions['velox_seo_nofollow']  = __( 'Velox: set Nofollow', 'velox' );
		$actions['velox_seo_follow']    = __( 'Velox: set Follow', 'velox' );
		$actions['velox_seo_smap_out']  = __( 'Velox: exclude from sitemap', 'velox' );
		$actions['velox_seo_smap_in']   = __( 'Velox: include in sitemap', 'velox' );
		return $actions;
	}

	/** Apply the chosen Velox bulk action to the selected posts. */
	public static function handle_bulk( $redirect, $action, $ids ) {
		$map = array(
			'velox_seo_noindex'  => array( '_velox_seo_noindex', '1' ),
			'velox_seo_index'    => array( '_velox_seo_noindex', '0' ),
			'velox_seo_nofollow' => array( '_velox_seo_nofollow', '1' ),
			'velox_seo_follow'   => array( '_velox_seo_nofollow', '0' ),
			'velox_seo_smap_out' => array( 'sitemap_exclude', '1' ),
			'velox_seo_smap_in'  => array( 'sitemap_exclude', '0' ),
		);
		if ( ! isset( $map[ $action ] ) ) {
			return $redirect;
		}
		list( $key, $value ) = $map[ $action ];
		$done = 0;
		foreach ( (array) $ids as $id ) {
			$id = (int) $id;
			if ( ! current_user_can( 'edit_post', $id ) ) {
				continue;
			}
			update_post_meta( $id, $key, $value );
			$done++;
		}
		// If we touched the sitemap flag, rebuild once at the end.
		if ( 'sitemap_exclude' === $key && class_exists( 'Velox_Seo' ) && method_exists( 'Velox_Seo', 'generate_sitemap' ) ) {
			Velox_Seo::generate_sitemap();
		}
		return add_query_arg( array( 'velox_bulk' => $action, 'velox_bulk_n' => $done ), $redirect );
	}

	/** Confirmation notice after a Velox bulk action. */
	public static function bulk_notice() {
		if ( empty( $_GET['velox_bulk'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$n = isset( $_GET['velox_bulk_n'] ) ? (int) $_GET['velox_bulk_n'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$labels = array(
			'velox_seo_noindex'  => __( 'set to Noindex', 'velox' ),
			'velox_seo_index'    => __( 'set to Index', 'velox' ),
			'velox_seo_nofollow' => __( 'set to Nofollow', 'velox' ),
			'velox_seo_follow'   => __( 'set to Follow', 'velox' ),
			'velox_seo_smap_out' => __( 'excluded from the sitemap', 'velox' ),
			'velox_seo_smap_in'  => __( 'included in the sitemap', 'velox' ),
		);
		$action = sanitize_key( wp_unslash( $_GET['velox_bulk'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$what   = isset( $labels[ $action ] ) ? $labels[ $action ] : __( 'updated', 'velox' );
		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html( sprintf(
				/* translators: 1: number of items, 2: what was done */
				_n( '%1$d item %2$s.', '%1$d items %2$s.', $n, 'velox' ),
				$n,
				$what
			) )
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
				'add'      => __( '— add —', 'velox' ),
				'saving'   => __( 'Saving…', 'velox' ),
				'saved'    => __( 'Saved', 'velox' ),
				'failed'   => __( 'Save failed', 'velox' ),
				'hint'     => __( 'Enter to save · Esc to cancel', 'velox' ),
				'index'    => __( 'Index', 'velox' ),
				'noindex'  => __( 'Noindex', 'velox' ),
				'follow'   => __( 'Follow', 'velox' ),
				'nofollow' => __( 'Nofollow', 'velox' ),
				'included' => __( 'Included', 'velox' ),
				'excluded' => __( 'Excluded', 'velox' ),
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

	/** AJAX: toggle the noindex, nofollow, or sitemap flag from the list. */
	public static function ajax_toggle_flag() {
		$post_id = isset( $_POST['post'] ) ? (int) $_POST['post'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$flag    = isset( $_POST['flag'] ) ? sanitize_key( $_POST['flag'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$on      = ! empty( $_POST['on'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Not allowed.', 'velox' ) ), 403 );
		}
		if ( 'sitemap' === $flag ) {
			// Sitemap is inverted: "on" from the button means EXCLUDE the page.
			update_post_meta( $post_id, 'sitemap_exclude', $on ? '1' : '0' );
			// Refresh the sitemap immediately so the change reflects without a save.
			if ( class_exists( 'Velox_Seo' ) && method_exists( 'Velox_Seo', 'generate_sitemap' ) ) {
				Velox_Seo::generate_sitemap();
			}
			wp_send_json_success( array( 'flag' => $flag, 'on' => $on ) );
		}
		$key = 'noindex' === $flag ? '_velox_seo_noindex' : ( 'nofollow' === $flag ? '_velox_seo_nofollow' : '' );
		if ( '' === $key ) {
			wp_send_json_error( array( 'message' => __( 'Unknown field.', 'velox' ) ), 400 );
		}
		// Meta is boolean-registered; store canonical '1'/'0'. "on" here means the
		// flag is ACTIVE (noindex/nofollow). The button sends the target state.
		update_post_meta( $post_id, $key, $on ? '1' : '0' );
		wp_send_json_success( array( 'flag' => $flag, 'on' => $on ) );
	}
}
