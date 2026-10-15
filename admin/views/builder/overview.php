<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Velox Builder — Overview.
 *
 * The landing screen of the Builder section: real counts, the FULL page
 * inventory (Velox documents plus every WordPress page Velox has never
 * touched), filters, and per-row actions.
 */
$new_url   = Velox_Builder::edit_url();
$stats     = Velox_Builder::stats();
$inventory = Velox_Builder::page_inventory();

$counts = array( 'all' => count( $inventory ), 'page' => 0, 'template' => 0, 'reusable' => 0, 'legacy' => 0 );
foreach ( $inventory as $row ) {
	if ( isset( $counts[ $row['type'] ] ) ) {
		$counts[ $row['type'] ]++;
	}
}
$filters = array(
	'all'      => __( 'All', 'velox' ),
	'page'     => __( 'Velox pages', 'velox' ),
	'template' => __( 'Templates', 'velox' ),
	'reusable' => __( 'Reusables', 'velox' ),
	'legacy'   => __( 'Without Velox', 'velox' ),
);
$type_icon = array( 'page' => 'file', 'template' => 'grid', 'reusable' => 'globe', 'legacy' => 'file' );
// Velox documents say "published"/"draft"; untouched WordPress pages carry the
// core statuses. Both get a translated label and one shared colour class.
$status_label = array(
	'published' => __( 'Published', 'velox' ),
	'publish'   => __( 'Published', 'velox' ),
	'draft'     => __( 'Draft', 'velox' ),
	'pending'   => __( 'Pending', 'velox' ),
	'private'   => __( 'Private', 'velox' ),
	'future'    => __( 'Scheduled', 'velox' ),
);
?>
<div class="vba-shell">
	<header class="vba-head">
		<div>
			<h1><?php esc_html_e( 'Overview', 'velox' ); ?></h1>
			<p><?php esc_html_e( 'Every page on this site, built with Velox or not.', 'velox' ); ?></p>
		</div>
		<div class="vba-head-actions">
			<a class="vba-btn vba-btn-primary" href="<?php echo esc_url( $new_url ); ?>">
				<?php echo Velox_Admin::icon( 'plug', 15 ); // phpcs:ignore ?> <?php esc_html_e( 'New page', 'velox' ); ?>
			</a>
		</div>
	</header>

	<div class="vba-stats">
		<div class="vba-stat"><span class="vba-stat-ic"><?php echo Velox_Admin::icon( 'file', 17 ); // phpcs:ignore ?></span><span class="vba-stat-v"><?php echo (int) $stats['pages']; ?></span><span class="vba-stat-l"><?php esc_html_e( 'Velox pages', 'velox' ); ?></span></div>
		<div class="vba-stat"><span class="vba-stat-ic"><?php echo Velox_Admin::icon( 'grid', 17 ); // phpcs:ignore ?></span><span class="vba-stat-v"><?php echo (int) $stats['templates']; ?></span><span class="vba-stat-l"><?php esc_html_e( 'Templates', 'velox' ); ?></span></div>
		<div class="vba-stat"><span class="vba-stat-ic"><?php echo Velox_Admin::icon( 'globe', 17 ); // phpcs:ignore ?></span><span class="vba-stat-v"><?php echo (int) $stats['reusables']; ?></span><span class="vba-stat-l"><?php esc_html_e( 'Reusables', 'velox' ); ?></span></div>
		<div class="vba-stat"><span class="vba-stat-ic"><?php echo Velox_Admin::icon( 'tag', 17 ); // phpcs:ignore ?></span><span class="vba-stat-v"><?php echo (int) $stats['classes']; ?></span><span class="vba-stat-l"><?php esc_html_e( 'Global classes', 'velox' ); ?></span></div>
	</div>

	<div class="vba-card">
		<div class="vba-card-h">
			<div><h2><?php esc_html_e( 'Pages', 'velox' ); ?></h2><p><?php esc_html_e( 'Open, rename, duplicate or delete without leaving this screen.', 'velox' ); ?></p></div>
			<a class="vba-btn vba-btn-primary vba-btn-sm" href="<?php echo esc_url( $new_url ); ?>"><?php echo Velox_Admin::icon( 'plug', 14 ); // phpcs:ignore ?> <?php esc_html_e( 'New page', 'velox' ); ?></a>
		</div>

		<div class="vba-toolbar">
			<div class="vba-filters" id="vba-filters" role="group" aria-label="<?php esc_attr_e( 'Filter by type', 'velox' ); ?>">
				<?php foreach ( $filters as $key => $label ) : ?>
					<button type="button" class="vba-filter<?php echo 'all' === $key ? ' on' : ''; ?>" data-filter="<?php echo esc_attr( $key ); ?>" aria-pressed="<?php echo 'all' === $key ? 'true' : 'false'; ?>">
						<?php echo esc_html( $label ); ?><span class="vba-filter-n"><?php echo (int) $counts[ $key ]; ?></span>
					</button>
				<?php endforeach; ?>
			</div>
			<div class="vba-search">
				<?php echo Velox_Admin::icon( 'search', 14 ); // phpcs:ignore ?>
				<label class="screen-reader-text" for="vba-page-search"><?php esc_html_e( 'Search pages', 'velox' ); ?></label>
				<input type="search" id="vba-page-search" placeholder="<?php esc_attr_e( 'Search pages…', 'velox' ); ?>">
			</div>
		</div>

		<?php if ( empty( $inventory ) ) : ?>
			<div class="vba-empty">
				<span class="vba-empty-ic"><?php echo Velox_Admin::icon( 'grid', 24 ); // phpcs:ignore ?></span>
				<b><?php esc_html_e( 'Nothing here yet', 'velox' ); ?></b>
				<p><?php esc_html_e( 'Create a new page, or open any page and choose "Edit with Velox".', 'velox' ); ?></p>
				<a class="vba-btn vba-btn-primary" href="<?php echo esc_url( $new_url ); ?>"><?php esc_html_e( 'New page', 'velox' ); ?></a>
			</div>
		<?php else : ?>
			<div class="vba-doclist" id="vba-doclist">
				<?php foreach ( $inventory as $d ) :
					$type     = (string) $d['type'];
					$status   = (string) $d['status'];
					$is_block = in_array( $type, array( 'template', 'reusable' ), true );
					$st_class = 'publish' === $status ? 'published' : $status;
					?>
					<div class="vba-docrow"
						data-type="<?php echo esc_attr( $type ); ?>"
						data-doc="<?php echo (int) $d['doc_id']; ?>"
						data-title="<?php echo esc_attr( strtolower( $d['title'] ) ); ?>">
						<span class="vba-doc-ic"><?php echo Velox_Admin::icon( $type_icon[ $type ] ?? 'file', 15 ); // phpcs:ignore ?></span>
						<a class="vba-doc-title" href="<?php echo esc_url( $d['edit'] ); ?>"><?php echo esc_html( $d['title'] ); ?></a>
						<?php if ( $d['doc_id'] ) : ?>
							<select class="vba-doc-kind vba-type-<?php echo esc_attr( $type ); ?>"
								data-doc-kind="<?php echo (int) $d['doc_id']; ?>"
								data-kind="<?php echo esc_attr( $type ); ?>"
								data-status="<?php echo esc_attr( $status ); ?>"
								title="<?php esc_attr_e( 'Change document type', 'velox' ); ?>"
								aria-label="<?php esc_attr_e( 'Change document type', 'velox' ); ?>">
								<option value="page"<?php selected( 'page', $type ); ?>><?php esc_html_e( 'Page', 'velox' ); ?></option>
								<option value="template"<?php selected( 'template', $type ); ?>><?php esc_html_e( 'Template', 'velox' ); ?></option>
								<option value="reusable"<?php selected( 'reusable', $type ); ?>><?php esc_html_e( 'Reusable', 'velox' ); ?></option>
							</select>
						<?php endif; ?>
						<span class="vba-doc-status vba-status-<?php echo esc_attr( $st_class ); ?>"><?php echo esc_html( $status_label[ $status ] ?? ucfirst( $status ) ); ?></span>
						<?php if ( $d['doc_id'] && 'published' === $status && ! empty( $d['has_draft'] ) ) : ?>
							<span class="vba-badge-changes" title="<?php esc_attr_e( 'Saved changes that visitors do not see yet. Open it and press Publish to put them live.', 'velox' ); ?>"><?php esc_html_e( 'Unpublished changes', 'velox' ); ?></span>
						<?php endif; ?>
						<?php
						// "Not live" on its own tells you nothing you can act on, so each
						// state says what it means and, where there is one, links to the fix.
						$is_live = ! empty( $d['live'] );
						if ( $is_block ) {
							// Templates and reusables are only used on the site once published.
							if ( 'published' === $status ) {
								echo '<span class="vba-live vba-live-yes" title="' . esc_attr( 'template' === $type ? __( 'Published — Velox uses this template wherever it applies.', 'velox' ) : __( 'Published — shown wherever it is placed.', 'velox' ) ) . '">' . esc_html__( 'In use', 'velox' ) . '</span>';
							} else {
								echo '<span class="vba-live vba-live-no" title="' . esc_attr__( 'Drafts are not used on the site. Publish it to put it to work.', 'velox' ) . '">' . esc_html__( 'Not used until published', 'velox' ) . '</span>';
							}
						} elseif ( $is_live ) {
							echo '<span class="vba-live vba-live-yes" title="' . esc_attr( $d['why'] ?? '' ) . '">' . esc_html__( 'On your site', 'velox' ) . '</span>';
						} elseif ( 'legacy' === $type ) {
							echo '<a class="vba-live vba-live-fix" href="' . esc_url( $d['edit'] ) . '" title="' . esc_attr( $d['why'] ?? '' ) . '">' . esc_html__( 'Build this page →', 'velox' ) . '</a>';
						} elseif ( 'draft' === $status ) {
							echo '<a class="vba-live vba-live-fix" href="' . esc_url( $d['edit'] ) . '" title="' . esc_attr( $d['why'] ?? '' ) . '">' . esc_html__( 'Draft — publish it →', 'velox' ) . '</a>';
						} else {
							echo '<span class="vba-live vba-live-no" title="' . esc_attr( $d['why'] ?? '' ) . '">' . esc_html__( 'Not on your site', 'velox' ) . '</span>';
						}
						?>
						<span class="vba-doc-meta"><?php
							/* translators: %s: human-readable time difference, e.g. "5 mins". */
							echo esc_html( sprintf( __( '%s ago', 'velox' ), human_time_diff( strtotime( $d['updated'] ), current_time( 'timestamp' ) ) ) );
						?></span>
						<span class="vba-doc-actions">
							<a class="vba-mini" href="<?php echo esc_url( $d['edit'] ); ?>"><?php echo esc_html( $d['doc_id'] ? __( 'Edit', 'velox' ) : __( 'Build', 'velox' ) ); ?></a>
							<?php if ( $d['doc_id'] ) : ?>
								<button type="button" class="vba-mini" data-doc-rename="<?php echo (int) $d['doc_id']; ?>"><?php esc_html_e( 'Rename', 'velox' ); ?></button>
								<button type="button" class="vba-mini" data-doc-duplicate="<?php echo (int) $d['doc_id']; ?>"><?php esc_html_e( 'Duplicate', 'velox' ); ?></button>
							<?php endif; ?>
							<?php if ( $d['view'] ) : ?>
								<a class="vba-mini" href="<?php echo esc_url( $d['view'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View', 'velox' ); ?></a>
							<?php endif; ?>
							<?php if ( $d['wp_edit'] ) : ?>
								<a class="vba-mini" href="<?php echo esc_url( $d['wp_edit'] ); ?>"><?php esc_html_e( 'WP', 'velox' ); ?></a>
							<?php endif; ?>
							<?php if ( $d['doc_id'] && $is_block && 'published' !== $status ) : ?>
								<button type="button" class="vba-mini vba-mini-go" data-doc-publish="<?php echo (int) $d['doc_id']; ?>"><?php esc_html_e( 'Publish', 'velox' ); ?></button>
							<?php endif; ?>
							<?php if ( $d['doc_id'] && 'published' === $status ) : ?>
								<button type="button" class="vba-mini" data-doc-unpublish="<?php echo (int) $d['doc_id']; ?>" data-kind="<?php echo esc_attr( $type ); ?>"><?php esc_html_e( 'Unpublish', 'velox' ); ?></button>
							<?php endif; ?>
							<?php if ( $d['doc_id'] ) : ?>
								<button type="button" class="vba-mini vba-mini-del" data-doc-delete="<?php echo (int) $d['doc_id']; ?>" data-bound="<?php echo ! empty( $d['post_id'] ) ? '1' : '0'; ?>"><?php esc_html_e( 'Delete', 'velox' ); ?></button>
							<?php endif; ?>
						</span>
					</div>
				<?php endforeach; ?>
				<div class="vba-nores" id="vba-nores" hidden><?php esc_html_e( 'No pages match that search.', 'velox' ); ?></div>
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
		publishFailed: '<?php echo esc_js( __( 'Publish failed', 'velox' ) ); ?>',
		unpublishFailed: '<?php echo esc_js( __( 'Unpublish failed', 'velox' ) ); ?>',
		deleteFailed: '<?php echo esc_js( __( 'Delete failed', 'velox' ) ); ?>',
		kindFailed: '<?php echo esc_js( __( 'Could not change type', 'velox' ) ); ?>',
		unpublishPage: '<?php echo esc_js( __( 'Take this page off your site? The layout is kept and can be published again.', 'velox' ) ); ?>',
		unpublishTemplate: '<?php echo esc_js( __( 'Unpublish this template? Pages stop using it until you publish it again. The layout is kept.', 'velox' ) ); ?>',
		unpublishReusable: '<?php echo esc_js( __( 'Unpublish this reusable? Pages that place it stop showing it until you publish it again.', 'velox' ) ); ?>',
		deleteBound: '<?php /* translators: %s: document title. */ echo esc_js( __( 'Delete “%s”? This also trashes the WordPress page it is bound to.', 'velox' ) ); ?>',
		deleteDoc: '<?php /* translators: %s: document title. */ echo esc_js( __( 'Delete “%s”? This cannot be undone.', 'velox' ) ); ?>',
		kindLive: '<?php /* translators: %s: document title. */ echo esc_js( __( '“%s” is a published page. Changing its type unlinks it from its WordPress page (visitors see your theme at that address) and turns it into an unpublished draft — publish it again to use it. Change the type anyway?', 'velox' ) ); ?>'
	};
	// The config is printed in the footer, after this script, so read it when used.
	function cfg() { return window.VELOX_BUILDER || {}; }
	function fmt( s, v ) { return s.replace( '%s', v ); }
	function post( action, data, cb ) {
		var C = cfg();
		var body = new URLSearchParams();
		body.set( 'action', 'velox' ); body.set( 'do', action ); body.set( 'nonce', C.nonce || '' );
		Object.keys( data ).forEach( function ( k ) { body.set( k, data[ k ] ); } );
		fetch( C.ajaxurl || window.ajaxurl, { method:'POST', headers:{ 'Content-Type':'application/x-www-form-urlencoded' }, body:body.toString() } )
			.then( function ( r ) { return r.json(); } ).then( cb ).catch( function () { cb( { success:false } ); } );
	}
	function errMsg( res, fallback ) { return ( res && res.data && res.data.message ) || fallback; }
	function applyFilter() {
		var active = document.querySelector( '[data-filter].on' );
		var f = active ? active.getAttribute( 'data-filter' ) : 'all';
		var si = document.getElementById( 'vba-page-search' );
		var q = si ? si.value.trim().toLowerCase() : '';
		var shown = 0;
		document.querySelectorAll( '.vba-docrow' ).forEach( function ( row ) {
			var okF = ( f === 'all' ) || row.getAttribute( 'data-type' ) === f;
			var okQ = ! q || row.getAttribute( 'data-title' ).indexOf( q ) > -1;
			var on = okF && okQ;
			row.style.display = on ? '' : 'none';
			if ( on ) { shown++; }
		} );
		var nr = document.getElementById( 'vba-nores' ); if ( nr ) { nr.hidden = shown > 0; }
	}
	document.addEventListener( 'click', function ( e ) {
		var fb = e.target.closest( '[data-filter]' );
		if ( fb ) {
			document.querySelectorAll( '[data-filter]' ).forEach( function ( b ) { b.classList.remove( 'on' ); b.setAttribute( 'aria-pressed', 'false' ); } );
			fb.classList.add( 'on' ); fb.setAttribute( 'aria-pressed', 'true' ); applyFilter(); return;
		}
		var rn = e.target.closest( '[data-doc-rename]' );
		if ( rn ) {
			var row = rn.closest( '.vba-docrow' );
			var cur = row.querySelector( '.vba-doc-title' ).textContent.trim();
			var name = prompt( T.renameTo, cur );
			if ( ! name || name === cur ) { return; }
			post( 'builder_doc_rename', { id:rn.getAttribute( 'data-doc-rename' ), title:name }, function ( res ) {
				if ( res && res.success ) { location.reload(); } else { alert( errMsg( res, T.renameFailed ) ); }
			} );
			return;
		}
		var dp = e.target.closest( '[data-doc-duplicate]' );
		if ( dp ) {
			post( 'builder_doc_duplicate', { id:dp.getAttribute( 'data-doc-duplicate' ) }, function ( res ) {
				if ( res && res.success ) { location.reload(); } else { alert( errMsg( res, T.duplicateFailed ) ); }
			} );
			return;
		}
		var pb = e.target.closest( '[data-doc-publish]' );
		if ( pb ) {
			pb.disabled = true;
			post( 'builder_publish', { id:pb.getAttribute( 'data-doc-publish' ) }, function ( res ) {
				if ( res && res.success ) { location.reload(); } else { pb.disabled = false; alert( errMsg( res, T.publishFailed ) ); }
			} );
			return;
		}
		var up = e.target.closest( '[data-doc-unpublish]' );
		if ( up ) {
			var kind = up.getAttribute( 'data-kind' );
			var q = 'template' === kind ? T.unpublishTemplate : ( 'reusable' === kind ? T.unpublishReusable : T.unpublishPage );
			if ( ! confirm( q ) ) { return; }
			post( 'builder_unpublish', { id:up.getAttribute( 'data-doc-unpublish' ) }, function ( res ) {
				if ( res && res.success ) { location.reload(); } else { alert( errMsg( res, T.unpublishFailed ) ); }
			} );
			return;
		}
		var dl = e.target.closest( '[data-doc-delete]' );
		if ( dl ) {
			var r2 = dl.closest( '.vba-docrow' );
			var t = r2.querySelector( '.vba-doc-title' ).textContent.trim();
			if ( ! confirm( fmt( '1' === dl.getAttribute( 'data-bound' ) ? T.deleteBound : T.deleteDoc, t ) ) ) { return; }
			post( 'builder_doc_delete', { id:dl.getAttribute( 'data-doc-delete' ) }, function ( res ) {
				if ( res && res.success ) { location.reload(); } else { alert( errMsg( res, T.deleteFailed ) ); }
			} );
		}
	} );
	document.addEventListener( 'change', function ( e ) {
		var k = e.target.closest( '[data-doc-kind]' );
		if ( ! k ) { return; }
		var was = k.getAttribute( 'data-kind' );
		// Turning a live page into a building block takes it off its URL — ask first.
		if ( 'page' === was && 'published' === k.getAttribute( 'data-status' ) && 'page' !== k.value ) {
			var title = k.closest( '.vba-docrow' ).querySelector( '.vba-doc-title' ).textContent.trim();
			if ( ! confirm( fmt( T.kindLive, title ) ) ) { k.value = was; return; }
		}
		k.disabled = true;
		post( 'builder_doc_kind', { id:k.getAttribute( 'data-doc-kind' ), kind:k.value }, function ( res ) {
			if ( res && res.success ) { location.reload(); } else { k.disabled = false; k.value = was; alert( errMsg( res, T.kindFailed ) ); }
		} );
	} );
	var s = document.getElementById( 'vba-page-search' );
	if ( s ) { s.addEventListener( 'input', applyFilter ); }
}() );
</script>
