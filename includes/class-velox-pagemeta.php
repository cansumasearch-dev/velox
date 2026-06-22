<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Per-page overrides. Adds a small "Velox" box to the post/page editor so you can
 * switch specific optimizations off on a single page when one misbehaves — the
 * standard escape hatch. Front-end classes call Velox_PageMeta::disabled() to honour it.
 */
class Velox_PageMeta {

	const META = '_velox_overrides';

	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_box' ) );
		add_action( 'save_post', array( $this, 'save' ), 10, 2 );
	}

	/** Is a feature switched off for the page currently being rendered? */
	public static function disabled( $feature ) {
		if ( is_admin() || ! is_singular() ) {
			return false;
		}
		$id = get_queried_object_id();
		if ( ! $id ) {
			return false;
		}
		$ov = get_post_meta( $id, self::META, true );
		if ( ! is_array( $ov ) ) {
			return false;
		}
		return ! empty( $ov['all'] ) || ! empty( $ov[ $feature ] );
	}

	private function fields() {
		return array(
			'all'  => 'Disable all Velox optimizations on this page',
			'js'   => 'Don\'t defer or delay JavaScript here',
			'css'  => 'Don\'t trim or async CSS here (unused-CSS, critical CSS, lazy-render)',
			'lazy' => 'Don\'t lazy-load images or iframes here',
		);
	}

	public function add_box() {
		foreach ( get_post_types( array( 'public' => true ), 'names' ) as $pt ) {
			add_meta_box( 'velox_overrides', 'Velox', array( $this, 'render' ), $pt, 'side', 'default' );
		}
	}

	public function render( $post ) {
		$ov = get_post_meta( $post->ID, self::META, true );
		$ov = is_array( $ov ) ? $ov : array();
		wp_nonce_field( 'velox_overrides', 'velox_overrides_nonce' );
		echo '<p style="margin:0 0 8px;color:#666">Turn optimizations off just for this page if something looks wrong.</p>';
		foreach ( $this->fields() as $key => $label ) {
			printf(
				'<p style="margin:6px 0"><label><input type="checkbox" name="velox_ov[%1$s]" value="1" %2$s> %3$s</label></p>',
				esc_attr( $key ),
				checked( ! empty( $ov[ $key ] ), true, false ),
				esc_html( $label )
			);
		}
	}

	public function save( $post_id, $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! isset( $_POST['velox_overrides_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['velox_overrides_nonce'] ), 'velox_overrides' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$in  = isset( $_POST['velox_ov'] ) && is_array( $_POST['velox_ov'] ) ? $_POST['velox_ov'] : array();
		$out = array();
		foreach ( array_keys( $this->fields() ) as $key ) {
			if ( ! empty( $in[ $key ] ) ) {
				$out[ $key ] = 1;
			}
		}
		if ( $out ) {
			update_post_meta( $post_id, self::META, $out );
		} else {
			delete_post_meta( $post_id, self::META );
		}
	}
}
