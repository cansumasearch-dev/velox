<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** Velox Builder — Templates: what wraps your pages, and what each one is for. */
$docs     = Velox_Builder::list_docs( 'template', 100 );
$purposes = Velox_Builder::template_purposes();
$default  = Velox_Builder::default_template();
// Short labels for the badge; the full sentence goes in the picker.
$short = array(
	'front_page' => __( 'Front page', 'velox' ),
	'error404'   => __( '404', 'velox' ),
	'search'     => __( 'Search', 'velox' ),
	'archive'    => __( 'Archives', 'velox' ),
	'posts'      => __( 'Posts', 'velox' ),
	'pages'      => __( 'Pages', 'velox' ),
	'catch_all'  => __( 'Catch-all', 'velox' ),
);
?>
<div class="vba-shell">
	<header class="vba-head">
		<div>
			<h1><?php esc_html_e( 'Templates', 'velox' ); ?></h1>
			<p><?php esc_html_e( 'A template is the frame around your pages — navbar, footer, anything shared. Each one says what it is for, and the page content drops into its Inner Content element.', 'velox' ); ?></p>
		</div>
		<div class="vba-head-actions">
			<button class="vba-btn vba-btn-primary" id="vba-new-template">
				<?php echo Velox_Admin::icon( 'plug', 15 ); // phpcs:ignore ?> <?php esc_html_e( 'New template', 'velox' ); ?>
			</button>
		</div>
	</header>

	<div class="vba-card">
		<div class="vba-card-h">
			<div><h2><?php esc_html_e( 'Your templates', 'velox' ); ?></h2>
			<p><?php esc_html_e( 'When more than one could apply, the most specific wins: Front page beats Pages, which beats Catch-all.', 'velox' ); ?></p></div>
		</div>

		<?php if ( empty( $docs ) ) : ?>
			<div class="vba-empty">
				<span class="vba-empty-ic"><?php echo Velox_Admin::icon( 'grid', 24 ); // phpcs:ignore ?></span>
				<b><?php esc_html_e( 'No templates yet', 'velox' ); ?></b>
				<p><?php esc_html_e( 'Create one to share a navbar and footer across your pages.', 'velox' ); ?></p>
				<button class="vba-btn vba-btn-primary" id="vba-new-template-2"><?php esc_html_e( 'New template', 'velox' ); ?></button>
			</div>
		<?php else : ?>
			<div class="vba-doclist">
				<?php foreach ( $docs as $d ) :
					$id  = (int) $d['id'];
					$pur = Velox_Builder::template_purpose( $id );
					?>
					<div class="vba-docrow" data-doc="<?php echo $id; ?>">
						<span class="vba-doc-ic"><?php echo Velox_Admin::icon( 'grid', 15 ); // phpcs:ignore ?></span>
						<a class="vba-doc-title" href="<?php echo esc_url( Velox_Builder::edit_url( $id, 'template' ) ); ?>"><?php echo esc_html( $d['title'] ? $d['title'] : __( 'Untitled', 'velox' ) ); ?></a>

						<select class="vba-tpl-purpose" data-tpl-purpose="<?php echo $id; ?>" title="<?php esc_attr_e( 'What this template is for', 'velox' ); ?>">
							<?php foreach ( $purposes as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $pur, $key ); ?>><?php echo esc_html( $short[ $key ] ); ?></option>
							<?php endforeach; ?>
						</select>

						<?php if ( $id === $default ) : ?>
							<span class="vba-doc-type vba-type-template"><?php esc_html_e( 'Site default', 'velox' ); ?></span>
						<?php endif; ?>

						<span class="vba-doc-status vba-status-<?php echo esc_attr( $d['status'] ); ?>"><?php echo esc_html( ucfirst( $d['status'] ) ); ?></span>
						<span class="vba-doc-meta"><?php echo esc_html( human_time_diff( strtotime( $d['updated'] ), current_time( 'timestamp' ) ) ); ?> <?php esc_html_e( 'ago', 'velox' ); ?></span>

						<span class="vba-doc-actions">
							<a class="vba-mini" href="<?php echo esc_url( Velox_Builder::edit_url( $id, 'template' ) ); ?>"><?php esc_html_e( 'Edit', 'velox' ); ?></a>
							<button class="vba-mini" data-doc-rename="<?php echo $id; ?>"><?php esc_html_e( 'Rename', 'velox' ); ?></button>
							<button class="vba-mini" data-doc-duplicate="<?php echo $id; ?>"><?php esc_html_e( 'Duplicate', 'velox' ); ?></button>
							<button class="vba-mini vba-mini-del" data-doc-delete="<?php echo $id; ?>"><?php esc_html_e( 'Delete', 'velox' ); ?></button>
						</span>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
</div>

<!-- New template: name and purpose up front, so it is never an untitled catch-all by accident. -->
<div class="vba-modal" id="vba-tpl-modal" hidden>
	<div class="vba-modal-back" data-close-modal></div>
	<div class="vba-modal-box" style="width:min(520px,92vw);height:auto">
		<div class="vba-modal-h">
			<b><?php esc_html_e( 'New template', 'velox' ); ?></b>
			<button class="vba-modal-x" data-close-modal aria-label="<?php esc_attr_e( 'Close', 'velox' ); ?>">&times;</button>
		</div>
		<div class="vba-modal-b">
			<label class="vba-fl" for="vba-tpl-name"><?php esc_html_e( 'Name', 'velox' ); ?></label>
			<input type="text" id="vba-tpl-name" class="vba-input" placeholder="<?php esc_attr_e( 'Main layout', 'velox' ); ?>">
			<label class="vba-fl" for="vba-tpl-purpose"><?php esc_html_e( 'What is it for?', 'velox' ); ?></label>
			<select id="vba-tpl-purpose" class="vba-input">
				<?php foreach ( $purposes as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( 'catch_all', $key ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<p class="vba-hint"><?php esc_html_e( 'The template starts with an Inner Content element already in place — that is the slot each page renders into. Add your navbar above it and your footer below.', 'velox' ); ?></p>
		</div>
		<div class="vba-modal-f">
			<span class="vba-modal-msg" id="vba-tpl-msg"></span>
			<button class="vba-btn" data-close-modal><?php esc_html_e( 'Cancel', 'velox' ); ?></button>
			<button class="vba-btn vba-btn-primary" id="vba-tpl-create"><?php esc_html_e( 'Create and open', 'velox' ); ?></button>
		</div>
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
	var modal = document.getElementById( 'vba-tpl-modal' );
	document.addEventListener( 'click', function ( e ) {
		if ( e.target.closest( '#vba-new-template' ) || e.target.closest( '#vba-new-template-2' ) ) {
			modal.hidden = false;
			document.getElementById( 'vba-tpl-msg' ).textContent = '';
			document.getElementById( 'vba-tpl-name' ).focus();
			return;
		}
		if ( e.target.closest( '[data-close-modal]' ) ) { modal.hidden = true; return; }
		if ( e.target.closest( '#vba-tpl-create' ) ) {
			var name = document.getElementById( 'vba-tpl-name' ).value.trim();
			var msg = document.getElementById( 'vba-tpl-msg' );
			if ( ! name ) { msg.textContent = 'Give it a name first.'; return; }
			msg.textContent = 'Creating…';
			post( 'builder_template_create', { title:name, purpose:document.getElementById( 'vba-tpl-purpose' ).value }, function ( res ) {
				if ( res && res.success ) { window.location.href = res.data.url; }
				else { msg.textContent = ( res && res.data && res.data.message ) || 'Could not create it'; }
			} );
			return;
		}
		var rn = e.target.closest( '[data-doc-rename]' );
		if ( rn ) {
			var row = rn.closest( '.vba-docrow' ), cur = row.querySelector( '.vba-doc-title' ).textContent.trim();
			var nn = prompt( 'Rename to:', cur );
			if ( ! nn || nn === cur ) { return; }
			post( 'builder_doc_rename', { id:rn.getAttribute( 'data-doc-rename' ), title:nn }, function ( r2 ) {
				if ( r2 && r2.success ) { location.reload(); } else { alert( 'Rename failed' ); }
			} );
			return;
		}
		var dp = e.target.closest( '[data-doc-duplicate]' );
		if ( dp ) {
			post( 'builder_doc_duplicate', { id:dp.getAttribute( 'data-doc-duplicate' ) }, function ( r2 ) {
				if ( r2 && r2.success ) { location.reload(); } else { alert( 'Duplicate failed' ); }
			} );
			return;
		}
		var dl = e.target.closest( '[data-doc-delete]' );
		if ( dl ) {
			if ( ! confirm( 'Delete this template? Pages using it fall back to whatever matches next.' ) ) { return; }
			post( 'builder_doc_delete', { id:dl.getAttribute( 'data-doc-delete' ) }, function ( r2 ) {
				if ( r2 && r2.success ) { location.reload(); } else { alert( 'Delete failed' ); }
			} );
		}
	} );
	document.addEventListener( 'change', function ( e ) {
		var p = e.target.closest( '[data-tpl-purpose]' );
		if ( ! p ) { return; }
		post( 'builder_template_purpose', { id:p.getAttribute( 'data-tpl-purpose' ), purpose:p.value }, function ( res ) {
			if ( ! res || ! res.success ) { alert( ( res && res.data && res.data.message ) || 'Could not save that' ); }
		} );
	} );
	document.addEventListener( 'keydown', function ( e ) { if ( e.key === 'Escape' ) { modal.hidden = true; } } );
}() );
</script>
