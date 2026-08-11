<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/** Velox Builder — Reusables: reusable header/footer/full-page layouts. */
$new_url = Velox_Builder::edit_url( 0, 'reusable' );
$docs    = Velox_Builder::list_docs( 'reusable', 100 );
?>
<div class="vba-shell">
	<header class="vba-head">
		<div><h1><?php esc_html_e( 'Reusables', 'velox' ); ?></h1>
		<p><?php esc_html_e( 'Global blocks you can drop into any page and update everywhere at once.', 'velox' ); ?></p></div>
		<div class="vba-head-actions">
			<a class="vba-btn vba-btn-primary" href="<?php echo esc_url( $new_url ); ?>"><?php echo Velox_Admin::icon( 'plug', 15 ); ?> <?php esc_html_e( 'New reusable', 'velox' ); ?></a>
		</div>
	</header>
	<div class="vba-card">
		<?php if ( empty( $docs ) ) : ?>
			<div class="vba-empty">
				<span class="vba-empty-ic"><?php echo Velox_Admin::icon( 'file', 24 ); ?></span>
				<b><?php esc_html_e( 'No reusables yet', 'velox' ); ?></b>
				<p><?php esc_html_e( 'Create a reusable block once, then reuse it anywhere.', 'velox' ); ?></p>
				<a class="vba-btn vba-btn-primary" href="<?php echo esc_url( $new_url ); ?>"><?php esc_html_e( 'New reusable', 'velox' ); ?></a>
			</div>
		<?php else : ?>
			<div class="vba-doclist">
				<?php foreach ( $docs as $d ) : ?>
					<div class="vba-docrow">
						<span class="vba-doc-ic"><?php echo Velox_Admin::icon( 'file', 15 ); ?></span>
						<a class="vba-doc-title" href="<?php echo esc_url( Velox_Builder::edit_url( (int) $d['id'], 'reusable' ) ); ?>"><?php echo esc_html( $d['title'] ? $d['title'] : __( 'Untitled', 'velox' ) ); ?></a>
						<span class="vba-doc-status vba-status-<?php echo esc_attr( $d['status'] ); ?>"><?php echo esc_html( 'published' === $d['status'] ? __( 'Active', 'velox' ) : __( 'Draft', 'velox' ) ); ?></span>
						<button class="vba-doc-del" data-del-doc="<?php echo (int) $d['id']; ?>" title="<?php esc_attr_e( 'Delete', 'velox' ); ?>"><?php echo Velox_Admin::icon( 'trash', 14 ); ?></button>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
</div>
<script>
( function () {
	var CFG = window.VELOX_BUILDER || {};
	document.addEventListener( 'click', function ( e ) {
		var d = e.target.closest( '[data-del-doc]' ); if ( ! d ) { return; }
		if ( ! confirm( 'Delete this permanently?' ) ) { return; }
		var body = new URLSearchParams();
		body.set( 'action', 'velox' ); body.set( 'do', 'builder_doc_delete' ); body.set( 'nonce', CFG.nonce || '' ); body.set( 'id', d.getAttribute( 'data-del-doc' ) );
		fetch( CFG.ajaxurl, { method:'POST', headers:{ 'Content-Type':'application/x-www-form-urlencoded' }, body:body.toString() } )
			.then( function ( r ) { return r.json(); } ).then( function ( res ) { if ( res && res.success ) { d.closest( '.vba-docrow' ).remove(); } } );
	} );
}() );
</script>
