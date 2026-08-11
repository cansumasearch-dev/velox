<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/** Velox Builder — Templates: reusable header/footer/full-page layouts. */
$new_url = Velox_Builder::edit_url( 0, 'template' );
$docs    = Velox_Builder::list_docs( 'template', 100 );
$roles   = Velox_Builder::roles();
?>
<div class="vba-shell">
	<header class="vba-head">
		<div><h1><?php esc_html_e( 'Templates', 'velox' ); ?></h1>
		<p><?php esc_html_e( 'Headers and footers that apply across your built pages, plus full-page layouts.', 'velox' ); ?></p></div>
		<div class="vba-head-actions">
			<a class="vba-btn vba-btn-primary" href="<?php echo esc_url( $new_url ); ?>"><?php echo Velox_Admin::icon( 'plug', 15 ); ?> <?php esc_html_e( 'New template', 'velox' ); ?></a>
		</div>
	</header>
	<div class="vba-card">
		<?php if ( empty( $docs ) ) : ?>
			<div class="vba-empty">
				<span class="vba-empty-ic"><?php echo Velox_Admin::icon( 'file', 24 ); ?></span>
				<b><?php esc_html_e( 'No templates yet', 'velox' ); ?></b>
				<p><?php esc_html_e( 'Create a template, then set it as your site header or footer.', 'velox' ); ?></p>
				<a class="vba-btn vba-btn-primary" href="<?php echo esc_url( $new_url ); ?>"><?php esc_html_e( 'New template', 'velox' ); ?></a>
			</div>
		<?php else : ?>
			<div class="vba-doclist">
				<?php foreach ( $docs as $d ) :
					$id   = (int) $d['id'];
					$role = ( (int) $roles['header'] === $id ) ? 'header' : ( ( (int) $roles['footer'] === $id ) ? 'footer' : '' );
					?>
					<div class="vba-docrow" data-tpl="<?php echo $id; ?>">
						<span class="vba-doc-ic"><?php echo Velox_Admin::icon( 'file', 15 ); ?></span>
						<a class="vba-doc-title" href="<?php echo esc_url( Velox_Builder::edit_url( $id, 'template' ) ); ?>"><?php echo esc_html( $d['title'] ? $d['title'] : __( 'Untitled', 'velox' ) ); ?></a>
						<?php if ( $role ) : ?><span class="vba-doc-status vba-status-published"><?php echo esc_html( 'header' === $role ? __( 'Site header', 'velox' ) : __( 'Site footer', 'velox' ) ); ?></span><?php endif; ?>
						<span class="vba-tpl-roles">
							<button class="vba-mini <?php echo 'header' === $role ? 'is-on' : ''; ?>" data-role="header" data-id="<?php echo $id; ?>"><?php esc_html_e( 'Header', 'velox' ); ?></button>
							<button class="vba-mini <?php echo 'footer' === $role ? 'is-on' : ''; ?>" data-role="footer" data-id="<?php echo $id; ?>"><?php esc_html_e( 'Footer', 'velox' ); ?></button>
						</span>
						<button class="vba-doc-del" data-del-doc="<?php echo $id; ?>" title="<?php esc_attr_e( 'Delete', 'velox' ); ?>"><?php echo Velox_Admin::icon( 'trash', 14 ); ?></button>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
</div>
<script>
( function () {
	var CFG = window.VELOX_BUILDER || {};
	function post( action, data, cb ) {
		var body = new URLSearchParams();
		body.set( 'action', 'velox' ); body.set( 'do', action ); body.set( 'nonce', CFG.nonce || '' );
		Object.keys( data ).forEach( function ( k ) { body.set( k, data[ k ] ); } );
		fetch( CFG.ajaxurl, { method:'POST', headers:{ 'Content-Type':'application/x-www-form-urlencoded' }, body:body.toString() } )
			.then( function ( r ) { return r.json(); } ).then( cb ).catch( function () { cb( { success:false } ); } );
	}
	document.addEventListener( 'click', function ( e ) {
		var rb = e.target.closest( '[data-role]' );
		if ( rb ) {
			var id = rb.getAttribute( 'data-id' ), role = rb.getAttribute( 'data-role' );
			var turningOff = rb.classList.contains( 'is-on' );
			post( 'builder_template_role', { id:id, role: turningOff ? 'none' : role }, function ( res ) { if ( res && res.success ) { location.reload(); } } );
			return;
		}
		var d = e.target.closest( '[data-del-doc]' );
		if ( d ) {
			if ( ! confirm( 'Delete this template permanently?' ) ) { return; }
			post( 'builder_doc_delete', { id: d.getAttribute( 'data-del-doc' ) }, function ( res ) { if ( res && res.success ) { d.closest( '.vba-docrow' ).remove(); } } );
		}
	} );
}() );
</script>
