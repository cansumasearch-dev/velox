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
$type_label = array(
	'page'     => __( 'Page', 'velox' ),
	'template' => __( 'Template', 'velox' ),
	'reusable' => __( 'Reusable', 'velox' ),
	'legacy'   => __( 'No Velox layout', 'velox' ),
);
$type_icon = array( 'page' => 'file', 'template' => 'grid', 'reusable' => 'globe', 'legacy' => 'file' );
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
			<div class="vba-filters" id="vba-filters">
				<?php foreach ( $filters as $key => $label ) : ?>
					<button class="vba-filter<?php echo 'all' === $key ? ' on' : ''; ?>" data-filter="<?php echo esc_attr( $key ); ?>">
						<?php echo esc_html( $label ); ?><span class="vba-filter-n"><?php echo (int) $counts[ $key ]; ?></span>
					</button>
				<?php endforeach; ?>
			</div>
			<div class="vba-search">
				<?php echo Velox_Admin::icon( 'search', 14 ); // phpcs:ignore ?>
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
				<?php foreach ( $inventory as $d ) : ?>
					<div class="vba-docrow"
						data-type="<?php echo esc_attr( $d['type'] ); ?>"
						data-doc="<?php echo (int) $d['doc_id']; ?>"
						data-title="<?php echo esc_attr( strtolower( $d['title'] ) ); ?>">
						<span class="vba-doc-ic"><?php echo Velox_Admin::icon( $type_icon[ $d['type'] ], 15 ); // phpcs:ignore ?></span>
						<a class="vba-doc-title" href="<?php echo esc_url( $d['edit'] ); ?>"><?php echo esc_html( $d['title'] ); ?></a>
						<?php if ( $d['doc_id'] ) : ?>
							<select class="vba-doc-kind vba-type-<?php echo esc_attr( $d['type'] ); ?>" data-doc-kind="<?php echo (int) $d['doc_id']; ?>" title="<?php esc_attr_e( 'Change document type', 'velox' ); ?>">
								<option value="page"<?php selected( 'page', $d['type'] ); ?>><?php esc_html_e( 'Page', 'velox' ); ?></option>
								<option value="template"<?php selected( 'template', $d['type'] ); ?>><?php esc_html_e( 'Template', 'velox' ); ?></option>
								<option value="reusable"<?php selected( 'reusable', $d['type'] ); ?>><?php esc_html_e( 'Reusable', 'velox' ); ?></option>
							</select>
						<?php else : ?>
							<span class="vba-doc-type vba-type-legacy"><?php echo esc_html( $type_label[ $d['type'] ] ); ?></span>
						<?php endif; ?>
						<span class="vba-doc-status vba-status-<?php echo esc_attr( $d['status'] ); ?>"><?php echo esc_html( ucfirst( $d['status'] ) ); ?></span>
						<span class="vba-live vba-live-<?php echo $d['live'] ? 'yes' : 'no'; ?>" title="<?php echo esc_attr( $d['why'] ); ?>">
							<?php echo esc_html( $d['live'] ? __( 'Live', 'velox' ) : __( 'Not live', 'velox' ) ); ?>
						</span>
						<span class="vba-doc-meta"><?php echo esc_html( human_time_diff( strtotime( $d['updated'] ), current_time( 'timestamp' ) ) ); ?> <?php esc_html_e( 'ago', 'velox' ); ?></span>
						<span class="vba-doc-actions">
							<a class="vba-mini" href="<?php echo esc_url( $d['edit'] ); ?>"><?php echo esc_html( $d['doc_id'] ? __( 'Edit', 'velox' ) : __( 'Build', 'velox' ) ); ?></a>
							<?php if ( $d['doc_id'] ) : ?>
								<button class="vba-mini" data-doc-rename="<?php echo (int) $d['doc_id']; ?>"><?php esc_html_e( 'Rename', 'velox' ); ?></button>
								<button class="vba-mini" data-doc-duplicate="<?php echo (int) $d['doc_id']; ?>"><?php esc_html_e( 'Duplicate', 'velox' ); ?></button>
							<?php endif; ?>
							<?php if ( $d['view'] ) : ?>
								<a class="vba-mini" href="<?php echo esc_url( $d['view'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View', 'velox' ); ?></a>
							<?php endif; ?>
							<?php if ( $d['wp_edit'] ) : ?>
								<a class="vba-mini" href="<?php echo esc_url( $d['wp_edit'] ); ?>"><?php esc_html_e( 'WP', 'velox' ); ?></a>
							<?php endif; ?>
							<?php if ( $d['doc_id'] ) : ?>
								<button class="vba-mini vba-mini-del" data-doc-delete="<?php echo (int) $d['doc_id']; ?>"><?php esc_html_e( 'Delete', 'velox' ); ?></button>
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
	var CFG = window.VELOX_BUILDER || {};
	function post( action, data, cb ) {
		var body = new URLSearchParams();
		body.set( 'action', 'velox' ); body.set( 'do', action ); body.set( 'nonce', CFG.nonce || '' );
		Object.keys( data ).forEach( function ( k ) { body.set( k, data[ k ] ); } );
		fetch( CFG.ajaxurl, { method:'POST', headers:{ 'Content-Type':'application/x-www-form-urlencoded' }, body:body.toString() } )
			.then( function ( r ) { return r.json(); } ).then( cb ).catch( function () { cb( { success:false } ); } );
	}
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
			document.querySelectorAll( '[data-filter]' ).forEach( function ( b ) { b.classList.remove( 'on' ); } );
			fb.classList.add( 'on' ); applyFilter(); return;
		}
		var rn = e.target.closest( '[data-doc-rename]' );
		if ( rn ) {
			var row = rn.closest( '.vba-docrow' );
			var cur = row.querySelector( '.vba-doc-title' ).textContent.trim();
			var name = prompt( 'Rename to:', cur );
			if ( ! name || name === cur ) { return; }
			post( 'builder_doc_rename', { id:rn.getAttribute( 'data-doc-rename' ), title:name }, function ( res ) {
				if ( res && res.success ) { location.reload(); } else { alert( ( res && res.data && res.data.message ) || 'Rename failed' ); }
			} );
			return;
		}
		var dp = e.target.closest( '[data-doc-duplicate]' );
		if ( dp ) {
			post( 'builder_doc_duplicate', { id:dp.getAttribute( 'data-doc-duplicate' ) }, function ( res ) {
				if ( res && res.success ) { location.reload(); } else { alert( 'Duplicate failed' ); }
			} );
			return;
		}
		var dl = e.target.closest( '[data-doc-delete]' );
		if ( dl ) {
			var r2 = dl.closest( '.vba-docrow' );
			var t = r2.querySelector( '.vba-doc-title' ).textContent.trim();
			if ( ! confirm( 'Delete "' + t + '"? This also trashes the WordPress page it is bound to.' ) ) { return; }
			post( 'builder_doc_delete', { id:dl.getAttribute( 'data-doc-delete' ) }, function ( res ) {
				if ( res && res.success ) { location.reload(); } else { alert( 'Delete failed' ); }
			} );
		}
	} );
	document.addEventListener( 'change', function ( e ) {
		var k = e.target.closest( '[data-doc-kind]' );
		if ( ! k ) { return; }
		post( 'builder_doc_kind', { id:k.getAttribute( 'data-doc-kind' ), kind:k.value }, function ( res ) {
			if ( res && res.success ) { location.reload(); } else { alert( ( res && res.data && res.data.message ) || 'Could not change type' ); }
		} );
	} );
	var s = document.getElementById( 'vba-page-search' );
	if ( s ) { s.addEventListener( 'input', applyFilter ); }
}() );
</script>
