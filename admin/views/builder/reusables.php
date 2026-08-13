<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** Velox Builder — Reusables: blocks you build once and use anywhere. */
$new_url = Velox_Builder::edit_url( 0, 'reusable' );
$docs    = Velox_Builder::list_docs( 'reusable', 100 );
$usage   = Velox_Builder::reusable_usage();
?>
<div class="vba-shell">
	<header class="vba-head">
		<div><h1><?php esc_html_e( 'Reusables', 'velox' ); ?></h1>
		<p><?php esc_html_e( 'Build a block once, drop it into any page, and edit it in one place to update it everywhere.', 'velox' ); ?></p></div>
		<div class="vba-head-actions">
			<a class="vba-btn vba-btn-primary" href="<?php echo esc_url( $new_url ); ?>"><?php echo Velox_Admin::icon( 'plug', 15 ); // phpcs:ignore ?> <?php esc_html_e( 'New reusable', 'velox' ); ?></a>
		</div>
	</header>
	<div class="vba-card">
		<div class="vba-card-h">
			<div><h2><?php esc_html_e( 'Your reusables', 'velox' ); ?></h2>
			<p><?php esc_html_e( 'Editing one updates every page it appears on.', 'velox' ); ?></p></div>
		</div>
		<?php if ( empty( $docs ) ) : ?>
			<div class="vba-empty">
				<span class="vba-empty-ic"><?php echo Velox_Admin::icon( 'globe', 24 ); // phpcs:ignore ?></span>
				<b><?php esc_html_e( 'No reusables yet', 'velox' ); ?></b>
				<p><?php esc_html_e( 'In the builder, right-click any element and choose "Make re-usable" — or start one from scratch.', 'velox' ); ?></p>
				<a class="vba-btn vba-btn-primary" href="<?php echo esc_url( $new_url ); ?>"><?php esc_html_e( 'New reusable', 'velox' ); ?></a>
			</div>
		<?php else : ?>
			<div class="vba-doclist">
				<?php foreach ( $docs as $d ) :
					$id   = (int) $d['id'];
					$used = $usage[ $id ] ?? array();
					$edit = Velox_Builder::edit_url( $id, 'reusable' );
					?>
					<div class="vba-docrow" data-doc="<?php echo $id; ?>">
						<span class="vba-doc-ic"><?php echo Velox_Admin::icon( 'globe', 15 ); // phpcs:ignore ?></span>
						<a class="vba-doc-title" href="<?php echo esc_url( $edit ); ?>"><?php echo esc_html( $d['title'] ? $d['title'] : __( 'Untitled', 'velox' ) ); ?></a>

						<?php if ( $used ) : ?>
							<span class="vba-used" title="<?php echo esc_attr( implode( ', ', wp_list_pluck( $used, 'title' ) ) ); ?>">
								<?php
								/* translators: %d: number of pages. */
								echo esc_html( sprintf( _n( 'Used on %d page', 'Used on %d pages', count( $used ), 'velox' ), count( $used ) ) );
								?>
							</span>
						<?php else : ?>
							<span class="vba-used vba-used-none"><?php esc_html_e( 'Not used yet', 'velox' ); ?></span>
						<?php endif; ?>

						<span class="vba-doc-meta"><?php echo esc_html( human_time_diff( strtotime( $d['updated'] ), current_time( 'timestamp' ) ) ); ?> <?php esc_html_e( 'ago', 'velox' ); ?></span>
						<span class="vba-doc-actions">
							<a class="vba-mini" href="<?php echo esc_url( $edit ); ?>"><?php esc_html_e( 'Edit', 'velox' ); ?></a>
							<button class="vba-mini" data-doc-rename="<?php echo $id; ?>"><?php esc_html_e( 'Rename', 'velox' ); ?></button>
							<button class="vba-mini" data-doc-duplicate="<?php echo $id; ?>"><?php esc_html_e( 'Duplicate', 'velox' ); ?></button>
							<button class="vba-mini vba-mini-del" data-doc-delete="<?php echo $id; ?>" data-used="<?php echo (int) count( $used ); ?>"><?php esc_html_e( 'Delete', 'velox' ); ?></button>
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
		var rn = e.target.closest( '[data-doc-rename]' );
		if ( rn ) {
			var row = rn.closest( '.vba-docrow' ), cur = row.querySelector( '.vba-doc-title' ).textContent.trim();
			var nn = prompt( 'Rename to:', cur );
			if ( ! nn || nn === cur ) { return; }
			post( 'builder_doc_rename', { id:rn.getAttribute( 'data-doc-rename' ), title:nn }, function ( r ) {
				if ( r && r.success ) { location.reload(); } else { alert( 'Rename failed' ); }
			} );
			return;
		}
		var dp = e.target.closest( '[data-doc-duplicate]' );
		if ( dp ) {
			post( 'builder_doc_duplicate', { id:dp.getAttribute( 'data-doc-duplicate' ) }, function ( r ) {
				if ( r && r.success ) { location.reload(); } else { alert( 'Duplicate failed' ); }
			} );
			return;
		}
		var dl = e.target.closest( '[data-doc-delete]' );
		if ( dl ) {
			// Deleting a reusable that is live somewhere leaves a gap on those
			// pages, so say how many before asking.
			var n = parseInt( dl.getAttribute( 'data-used' ) || '0', 10 );
			var msg = n
				? 'This reusable is used on ' + n + ' page' + ( 1 === n ? '' : 's' ) + '. Deleting it removes the block from ' + ( 1 === n ? 'that page' : 'those pages' ) + '. Continue?'
				: 'Delete this reusable?';
			if ( ! confirm( msg ) ) { return; }
			post( 'builder_doc_delete', { id:dl.getAttribute( 'data-doc-delete' ) }, function ( r ) {
				if ( r && r.success ) { location.reload(); } else { alert( 'Delete failed' ); }
			} );
		}
	} );
}() );
</script>
