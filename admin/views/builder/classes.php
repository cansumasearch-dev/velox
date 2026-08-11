<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** Velox Builder — Classes: every class used across your pages. */
$classes = Velox_Builder::all_classes();
?>
<div class="vba-shell">
	<header class="vba-head">
		<div><h1><?php esc_html_e( 'Classes', 'velox' ); ?></h1>
		<p><?php esc_html_e( 'Every styling class used across your Velox Builder pages. Rename or delete site-wide.', 'velox' ); ?></p></div>
	</header>
	<div class="vba-card">
		<?php if ( empty( $classes ) ) : ?>
			<div class="vba-empty">
				<span class="vba-empty-ic"><?php echo Velox_Admin::icon( 'tag', 24 ); ?></span>
				<b><?php esc_html_e( 'No classes yet', 'velox' ); ?></b>
				<p><?php esc_html_e( 'Classes appear here as soon as you style elements in the editor.', 'velox' ); ?></p>
			</div>
		<?php else : ?>
			<div class="vba-classlist" id="vba-classlist">
				<div class="vba-class-head">
					<span><?php esc_html_e( 'Class', 'velox' ); ?></span>
					<span><?php esc_html_e( 'Used in', 'velox' ); ?></span>
					<span><?php esc_html_e( 'Properties', 'velox' ); ?></span>
					<span></span>
				</div>
				<?php foreach ( $classes as $name => $info ) : ?>
					<div class="vba-class-row" data-class="<?php echo esc_attr( $name ); ?>">
						<span class="vba-class-name"><?php echo esc_html( $name ); ?></span>
						<span class="vba-class-meta"><?php echo (int) $info['count']; ?> <?php echo esc_html( _n( 'page', 'pages', (int) $info['count'], 'velox' ) ); ?></span>
						<span class="vba-class-meta"><?php echo (int) $info['props']; ?></span>
						<span class="vba-class-actions">
							<button class="vba-mini" data-rename="<?php echo esc_attr( $name ); ?>"><?php esc_html_e( 'Rename', 'velox' ); ?></button>
							<button class="vba-mini vba-mini-del" data-delclass="<?php echo esc_attr( $name ); ?>"><?php esc_html_e( 'Delete', 'velox' ); ?></button>
						</span>
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
		var rn = e.target.closest( '[data-rename]' );
		if ( rn ) {
			var from = rn.getAttribute( 'data-rename' );
			var to = prompt( 'Rename ' + from + ' to:', from );
			if ( ! to || to === from ) { return; }
			post( 'builder_class_rename', { from:from, to:to }, function ( res ) {
				if ( res && res.success ) { location.reload(); } else { alert( ( res && res.data && res.data.message ) || 'Rename failed' ); }
			} );
		}
		var dl = e.target.closest( '[data-delclass]' );
		if ( dl ) {
			var cls = dl.getAttribute( 'data-delclass' );
			if ( ! confirm( 'Delete ' + cls + ' from every page? This removes its styles.' ) ) { return; }
			post( 'builder_class_delete', { 'class':cls }, function ( res ) {
				if ( res && res.success ) { location.reload(); } else { alert( 'Delete failed' ); }
			} );
		}
	} );
}() );
</script>
