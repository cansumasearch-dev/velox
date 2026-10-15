<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** Velox Builder — Reusables: blocks you build once and use anywhere. */
$new_url = Velox_Builder::edit_url( 0, 'reusable' );
$docs    = Velox_Builder::list_docs( 'reusable', 100 );
$usage   = Velox_Builder::reusable_usage();
$kind_label = array(
	'page'     => __( 'Page', 'velox' ),
	'template' => __( 'Template', 'velox' ),
);
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
			<p><?php esc_html_e( 'Editing one updates every page and template it appears in. Only published reusables are shown on the site.', 'velox' ); ?></p></div>
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
					$id        = (int) $d['id'];
					$used      = $usage[ $id ] ?? array();
					$n_used    = count( $used );
					$edit      = Velox_Builder::edit_url( $id, 'reusable' );
					$published = 'published' === $d['status'];
					?>
					<div class="vba-docrow" data-doc="<?php echo $id; ?>">
						<span class="vba-doc-ic"><?php echo Velox_Admin::icon( 'globe', 15 ); // phpcs:ignore ?></span>
						<span class="vba-doc-main">
							<a class="vba-doc-title" href="<?php echo esc_url( $edit ); ?>"><?php echo esc_html( $d['title'] ? $d['title'] : __( 'Untitled', 'velox' ) ); ?></a>
							<?php if ( $used ) : ?>
								<span class="vba-used-list">
									<span class="vba-used-lead"><?php esc_html_e( 'Used in:', 'velox' ); ?></span>
									<?php foreach ( $used as $i => $u ) :
										$u_kind = (string) ( $u['kind'] ?? 'page' );
										?><?php echo $i ? '<span class="vba-used-sep">, </span>' : ''; ?><a href="<?php echo esc_url( Velox_Builder::edit_url( (int) $u['id'], $u_kind ) ); ?>"><?php echo esc_html( $u['title'] ); ?></a><?php if ( 'page' !== $u_kind ) : ?> <span class="vba-used-kind">(<?php echo esc_html( $kind_label[ $u_kind ] ?? $u_kind ); ?>)</span><?php endif; ?><?php endforeach; ?>
								</span>
							<?php endif; ?>
						</span>

						<?php if ( $used ) : ?>
							<span class="vba-used">
								<?php
								echo esc_html( 1 === $n_used
									? __( 'Used in 1 place', 'velox' )
									/* translators: %d: number of pages and templates using this reusable. */
									: sprintf( __( 'Used in %d places', 'velox' ), $n_used ) );
								?>
							</span>
						<?php else : ?>
							<span class="vba-used vba-used-none"><?php esc_html_e( 'Not used yet', 'velox' ); ?></span>
						<?php endif; ?>

						<?php if ( $published ) : ?>
							<span class="vba-doc-status vba-status-published"><?php esc_html_e( 'Published', 'velox' ); ?></span>
							<?php if ( ! empty( $d['has_draft'] ) ) : ?>
								<span class="vba-badge-changes" title="<?php esc_attr_e( 'Saved changes that visitors do not see yet. Open it and press Publish to put them live.', 'velox' ); ?>"><?php esc_html_e( 'Unpublished changes', 'velox' ); ?></span>
							<?php endif; ?>
						<?php else : ?>
							<span class="vba-doc-status vba-status-draft vba-status-note"><?php esc_html_e( 'Draft — not shown on the site until published', 'velox' ); ?></span>
						<?php endif; ?>

						<span class="vba-doc-meta"><?php
							/* translators: %s: human-readable time difference, e.g. "5 mins". */
							echo esc_html( sprintf( __( '%s ago', 'velox' ), human_time_diff( strtotime( $d['updated'] ), current_time( 'timestamp' ) ) ) );
						?></span>
						<span class="vba-doc-actions">
							<a class="vba-mini" href="<?php echo esc_url( $edit ); ?>"><?php esc_html_e( 'Edit', 'velox' ); ?></a>
							<?php if ( $published ) : ?>
								<button type="button" class="vba-mini" data-doc-unpublish="<?php echo $id; ?>"><?php esc_html_e( 'Unpublish', 'velox' ); ?></button>
							<?php else : ?>
								<button type="button" class="vba-mini vba-mini-go" data-doc-publish="<?php echo $id; ?>"><?php esc_html_e( 'Publish', 'velox' ); ?></button>
							<?php endif; ?>
							<button type="button" class="vba-mini" data-doc-rename="<?php echo $id; ?>"><?php esc_html_e( 'Rename', 'velox' ); ?></button>
							<button type="button" class="vba-mini" data-doc-duplicate="<?php echo $id; ?>"><?php esc_html_e( 'Duplicate', 'velox' ); ?></button>
							<button type="button" class="vba-mini vba-mini-del" data-doc-delete="<?php echo $id; ?>" data-used="<?php echo (int) $n_used; ?>"><?php esc_html_e( 'Delete', 'velox' ); ?></button>
						</span>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
</div>
<script>
( function () {
	var T = {
		renameTo: '<?php echo esc_js( __( 'Rename to:', 'velox' ) ); ?>',
		renameFailed: '<?php echo esc_js( __( 'Rename failed', 'velox' ) ); ?>',
		duplicateFailed: '<?php echo esc_js( __( 'Duplicate failed', 'velox' ) ); ?>',
		deleteFailed: '<?php echo esc_js( __( 'Delete failed', 'velox' ) ); ?>',
		publishFailed: '<?php echo esc_js( __( 'Publish failed', 'velox' ) ); ?>',
		unpublishFailed: '<?php echo esc_js( __( 'Unpublish failed', 'velox' ) ); ?>',
		unpublishAsk: '<?php echo esc_js( __( 'Unpublish this reusable? Pages that place it stop showing it until you publish it again.', 'velox' ) ); ?>',
		deleteAsk: '<?php echo esc_js( __( 'Delete this reusable?', 'velox' ) ); ?>',
		deleteUsedOne: '<?php echo esc_js( __( 'This reusable is used in 1 place. Deleting it removes the block from there. Continue?', 'velox' ) ); ?>',
		deleteUsedMany: '<?php /* translators: %s: number of pages and templates. */ echo esc_js( __( 'This reusable is used in %s places. Deleting it removes the block from all of them. Continue?', 'velox' ) ); ?>'
	};
	// The config is printed in the footer, after this script, so read it when used.
	function cfg() { return window.VELOX_BUILDER || {}; }
	function post( action, data, cb ) {
		var C = cfg();
		var body = new URLSearchParams();
		body.set( 'action', 'velox' ); body.set( 'do', action ); body.set( 'nonce', C.nonce || '' );
		Object.keys( data ).forEach( function ( k ) { body.set( k, data[ k ] ); } );
		fetch( C.ajaxurl || window.ajaxurl, { method:'POST', headers:{ 'Content-Type':'application/x-www-form-urlencoded' }, body:body.toString() } )
			.then( function ( r ) { return r.json(); } ).then( cb ).catch( function () { cb( { success:false } ); } );
	}
	function errMsg( res, fallback ) { return ( res && res.data && res.data.message ) || fallback; }
	document.addEventListener( 'click', function ( e ) {
		var pb = e.target.closest( '[data-doc-publish]' );
		if ( pb ) {
			pb.disabled = true;
			post( 'builder_publish', { id:pb.getAttribute( 'data-doc-publish' ) }, function ( r ) {
				if ( r && r.success ) { location.reload(); } else { pb.disabled = false; alert( errMsg( r, T.publishFailed ) ); }
			} );
			return;
		}
		var up = e.target.closest( '[data-doc-unpublish]' );
		if ( up ) {
			if ( ! confirm( T.unpublishAsk ) ) { return; }
			post( 'builder_unpublish', { id:up.getAttribute( 'data-doc-unpublish' ) }, function ( r ) {
				if ( r && r.success ) { location.reload(); } else { alert( errMsg( r, T.unpublishFailed ) ); }
			} );
			return;
		}
		var rn = e.target.closest( '[data-doc-rename]' );
		if ( rn ) {
			var row = rn.closest( '.vba-docrow' ), cur = row.querySelector( '.vba-doc-title' ).textContent.trim();
			var nn = prompt( T.renameTo, cur );
			if ( ! nn || nn === cur ) { return; }
			post( 'builder_doc_rename', { id:rn.getAttribute( 'data-doc-rename' ), title:nn }, function ( r ) {
				if ( r && r.success ) { location.reload(); } else { alert( errMsg( r, T.renameFailed ) ); }
			} );
			return;
		}
		var dp = e.target.closest( '[data-doc-duplicate]' );
		if ( dp ) {
			post( 'builder_doc_duplicate', { id:dp.getAttribute( 'data-doc-duplicate' ) }, function ( r ) {
				if ( r && r.success ) { location.reload(); } else { alert( errMsg( r, T.duplicateFailed ) ); }
			} );
			return;
		}
		var dl = e.target.closest( '[data-doc-delete]' );
		if ( dl ) {
			// Deleting a reusable that is placed somewhere leaves a gap there, so
			// say how many places before asking.
			var n = parseInt( dl.getAttribute( 'data-used' ) || '0', 10 );
			var msg = n ? ( 1 === n ? T.deleteUsedOne : T.deleteUsedMany.replace( '%s', String( n ) ) ) : T.deleteAsk;
			if ( ! confirm( msg ) ) { return; }
			post( 'builder_doc_delete', { id:dl.getAttribute( 'data-doc-delete' ) }, function ( r ) {
				if ( r && r.success ) { location.reload(); } else { alert( errMsg( r, T.deleteFailed ) ); }
			} );
		}
	} );
}() );
</script>
