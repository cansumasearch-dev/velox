<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** Velox Builder — Templates: what wraps your pages, and what each one is for. */
$docs     = Velox_Builder::list_docs( 'template', 100 );
$purposes = Velox_Builder::template_purposes();
$default  = (int) Velox_Builder::default_template();
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
			<button type="button" class="vba-btn vba-btn-primary" id="vba-new-template">
				<?php echo Velox_Admin::icon( 'plug', 15 ); // phpcs:ignore ?> <?php esc_html_e( 'New template', 'velox' ); ?>
			</button>
		</div>
	</header>

	<div class="vba-card">
		<div class="vba-card-h">
			<div><h2><?php esc_html_e( 'Your templates', 'velox' ); ?></h2>
			<p><?php esc_html_e( 'Which template a page gets: the one chosen on the page itself; otherwise a template made for it (Front page, then Posts or Pages); otherwise the site default; otherwise a Catch-all template. 404, Search and Archives templates are used for those screens.', 'velox' ); ?></p>
			<p><?php esc_html_e( 'Only published templates are used on the site.', 'velox' ); ?></p></div>
		</div>

		<?php if ( empty( $docs ) ) : ?>
			<div class="vba-empty">
				<span class="vba-empty-ic"><?php echo Velox_Admin::icon( 'grid', 24 ); // phpcs:ignore ?></span>
				<b><?php esc_html_e( 'No templates yet', 'velox' ); ?></b>
				<p><?php esc_html_e( 'Create one to share a navbar and footer across your pages.', 'velox' ); ?></p>
				<button type="button" class="vba-btn vba-btn-primary" id="vba-new-template-2"><?php esc_html_e( 'New template', 'velox' ); ?></button>
			</div>
		<?php else : ?>
			<div class="vba-doclist">
				<?php foreach ( $docs as $d ) :
					$id        = (int) $d['id'];
					$pur       = Velox_Builder::template_purpose( $id );
					$published = 'published' === $d['status'];
					$is_def    = $id === $default;
					?>
					<div class="vba-docrow" data-doc="<?php echo $id; ?>">
						<span class="vba-doc-ic"><?php echo Velox_Admin::icon( 'grid', 15 ); // phpcs:ignore ?></span>
						<a class="vba-doc-title" href="<?php echo esc_url( Velox_Builder::edit_url( $id, 'template' ) ); ?>"><?php echo esc_html( $d['title'] ? $d['title'] : __( 'Untitled', 'velox' ) ); ?></a>

						<select class="vba-tpl-purpose" data-tpl-purpose="<?php echo $id; ?>" data-prev="<?php echo esc_attr( $pur ); ?>"
							title="<?php echo esc_attr( $purposes[ $pur ] ?? __( 'What this template is for', 'velox' ) ); ?>"
							aria-label="<?php esc_attr_e( 'What this template is for', 'velox' ); ?>">
							<?php foreach ( $purposes as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" title="<?php echo esc_attr( $label ); ?>" <?php selected( $pur, $key ); ?>><?php echo esc_html( $short[ $key ] ?? $label ); ?></option>
							<?php endforeach; ?>
						</select>
						<span class="vba-inline-msg" data-tpl-msg aria-live="polite"></span>

						<span class="vba-doc-type vba-type-template vba-tpl-default" title="<?php esc_attr_e( 'Used for any page no other template claims.', 'velox' ); ?>"<?php echo $is_def ? '' : ' hidden'; ?>><?php esc_html_e( 'Site default', 'velox' ); ?></span>

						<?php if ( $published ) : ?>
							<span class="vba-doc-status vba-status-published" title="<?php esc_attr_e( 'Used on the site wherever this template applies.', 'velox' ); ?>"><?php esc_html_e( 'Published', 'velox' ); ?></span>
							<?php if ( ! empty( $d['has_draft'] ) ) : ?>
								<span class="vba-badge-changes" title="<?php esc_attr_e( 'Saved changes that visitors do not see yet. Open it and press Publish to put them live.', 'velox' ); ?>"><?php esc_html_e( 'Unpublished changes', 'velox' ); ?></span>
							<?php endif; ?>
						<?php else : ?>
							<span class="vba-doc-status vba-status-draft vba-status-note"><?php esc_html_e( 'Draft — not used on the site until published', 'velox' ); ?></span>
						<?php endif; ?>
						<span class="vba-doc-meta"><?php
							/* translators: %s: human-readable time difference, e.g. "5 mins". */
							echo esc_html( sprintf( __( '%s ago', 'velox' ), human_time_diff( strtotime( $d['updated'] ), current_time( 'timestamp' ) ) ) );
						?></span>

						<span class="vba-doc-actions">
							<a class="vba-mini" href="<?php echo esc_url( Velox_Builder::edit_url( $id, 'template' ) ); ?>"><?php esc_html_e( 'Edit', 'velox' ); ?></a>
							<?php if ( $published ) : ?>
								<button type="button" class="vba-mini" data-doc-unpublish="<?php echo $id; ?>"><?php esc_html_e( 'Unpublish', 'velox' ); ?></button>
							<?php else : ?>
								<button type="button" class="vba-mini vba-mini-go" data-doc-publish="<?php echo $id; ?>"><?php esc_html_e( 'Publish', 'velox' ); ?></button>
							<?php endif; ?>
							<button type="button" class="vba-mini" data-tpl-default="<?php echo $id; ?>" title="<?php esc_attr_e( 'Use this template for any page no other template claims.', 'velox' ); ?>"<?php echo $is_def ? ' hidden' : ''; ?>><?php esc_html_e( 'Make default', 'velox' ); ?></button>
							<button type="button" class="vba-mini" data-doc-rename="<?php echo $id; ?>"><?php esc_html_e( 'Rename', 'velox' ); ?></button>
							<button type="button" class="vba-mini" data-doc-duplicate="<?php echo $id; ?>"><?php esc_html_e( 'Duplicate', 'velox' ); ?></button>
							<button type="button" class="vba-mini vba-mini-del" data-doc-delete="<?php echo $id; ?>"><?php esc_html_e( 'Delete', 'velox' ); ?></button>
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
			<button type="button" class="vba-modal-x" data-close-modal aria-label="<?php esc_attr_e( 'Close', 'velox' ); ?>">&times;</button>
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
			<p class="vba-hint"><?php esc_html_e( 'New templates start as a draft. Publish it once it is ready — until then the site does not use it.', 'velox' ); ?></p>
		</div>
		<div class="vba-modal-f">
			<span class="vba-modal-msg" id="vba-tpl-msg"></span>
			<button type="button" class="vba-btn" data-close-modal><?php esc_html_e( 'Cancel', 'velox' ); ?></button>
			<button type="button" class="vba-btn vba-btn-primary" id="vba-tpl-create"><?php esc_html_e( 'Create and open', 'velox' ); ?></button>
		</div>
	</div>
</div>

<script>
( function () {
	var T = {
		nameFirst: '<?php echo esc_js( __( 'Give it a name first.', 'velox' ) ); ?>',
		creating: '<?php echo esc_js( __( 'Creating…', 'velox' ) ); ?>',
		createFailed: '<?php echo esc_js( __( 'Could not create it', 'velox' ) ); ?>',
		renameTo: '<?php echo esc_js( __( 'Rename to:', 'velox' ) ); ?>',
		renameFailed: '<?php echo esc_js( __( 'Rename failed', 'velox' ) ); ?>',
		duplicateFailed: '<?php echo esc_js( __( 'Duplicate failed', 'velox' ) ); ?>',
		deleteFailed: '<?php echo esc_js( __( 'Delete failed', 'velox' ) ); ?>',
		deleteAsk: '<?php echo esc_js( __( 'Delete this template? Pages using it fall back to whatever matches next.', 'velox' ) ); ?>',
		publishFailed: '<?php echo esc_js( __( 'Publish failed', 'velox' ) ); ?>',
		unpublishFailed: '<?php echo esc_js( __( 'Unpublish failed', 'velox' ) ); ?>',
		unpublishAsk: '<?php echo esc_js( __( 'Unpublish this template? Pages stop using it until you publish it again. The layout is kept.', 'velox' ) ); ?>',
		defaultFailed: '<?php echo esc_js( __( 'Could not make it the default', 'velox' ) ); ?>',
		saving: '<?php echo esc_js( __( 'Saving…', 'velox' ) ); ?>',
		saved: '<?php echo esc_js( __( 'Saved', 'velox' ) ); ?>',
		notSaved: '<?php echo esc_js( __( 'Not saved', 'velox' ) ); ?>'
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
			if ( ! name ) { msg.textContent = T.nameFirst; return; }
			msg.textContent = T.creating;
			post( 'builder_template_create', { title:name, purpose:document.getElementById( 'vba-tpl-purpose' ).value }, function ( res ) {
				if ( res && res.success ) { window.location.href = res.data.url; }
				else { msg.textContent = errMsg( res, T.createFailed ); }
			} );
			return;
		}
		var df = e.target.closest( '[data-tpl-default]' );
		if ( df ) {
			var id = df.getAttribute( 'data-tpl-default' );
			df.disabled = true;
			post( 'builder_template_default', { id:id }, function ( res ) {
				df.disabled = false;
				if ( ! res || ! res.success ) { alert( errMsg( res, T.defaultFailed ) ); return; }
				// Move the badge: exactly one row is the default at a time.
				document.querySelectorAll( '.vba-docrow[data-doc]' ).forEach( function ( row ) {
					var isDef = row.getAttribute( 'data-doc' ) === String( ( res.data && res.data.id ) || id );
					var badge = row.querySelector( '.vba-tpl-default' );
					var btn = row.querySelector( '[data-tpl-default]' );
					if ( badge ) { badge.hidden = ! isDef; }
					if ( btn ) { btn.hidden = isDef; }
				} );
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
			if ( ! confirm( T.unpublishAsk ) ) { return; }
			post( 'builder_unpublish', { id:up.getAttribute( 'data-doc-unpublish' ) }, function ( res ) {
				if ( res && res.success ) { location.reload(); } else { alert( errMsg( res, T.unpublishFailed ) ); }
			} );
			return;
		}
		var rn = e.target.closest( '[data-doc-rename]' );
		if ( rn ) {
			var row = rn.closest( '.vba-docrow' ), cur = row.querySelector( '.vba-doc-title' ).textContent.trim();
			var nn = prompt( T.renameTo, cur );
			if ( ! nn || nn === cur ) { return; }
			post( 'builder_doc_rename', { id:rn.getAttribute( 'data-doc-rename' ), title:nn }, function ( r2 ) {
				if ( r2 && r2.success ) { location.reload(); } else { alert( errMsg( r2, T.renameFailed ) ); }
			} );
			return;
		}
		var dp = e.target.closest( '[data-doc-duplicate]' );
		if ( dp ) {
			post( 'builder_doc_duplicate', { id:dp.getAttribute( 'data-doc-duplicate' ) }, function ( r2 ) {
				if ( r2 && r2.success ) { location.reload(); } else { alert( errMsg( r2, T.duplicateFailed ) ); }
			} );
			return;
		}
		var dl = e.target.closest( '[data-doc-delete]' );
		if ( dl ) {
			if ( ! confirm( T.deleteAsk ) ) { return; }
			post( 'builder_doc_delete', { id:dl.getAttribute( 'data-doc-delete' ) }, function ( r2 ) {
				if ( r2 && r2.success ) { location.reload(); } else { alert( errMsg( r2, T.deleteFailed ) ); }
			} );
		}
	} );
	document.addEventListener( 'change', function ( e ) {
		var p = e.target.closest( '[data-tpl-purpose]' );
		if ( ! p ) { return; }
		var msg = p.parentElement.querySelector( '[data-tpl-msg]' );
		var prev = p.getAttribute( 'data-prev' );
		var opt = p.options[ p.selectedIndex ];
		function say( text, cls ) {
			if ( ! msg ) { return; }
			msg.textContent = text;
			msg.className = 'vba-inline-msg' + ( cls ? ' ' + cls : '' );
			clearTimeout( msg._t );
			if ( 'is-ok' === cls ) { msg._t = setTimeout( function () { msg.textContent = ''; msg.className = 'vba-inline-msg'; }, 2200 ); }
		}
		say( T.saving, '' );
		p.disabled = true;
		post( 'builder_template_purpose', { id:p.getAttribute( 'data-tpl-purpose' ), purpose:p.value }, function ( res ) {
			p.disabled = false;
			if ( res && res.success ) {
				p.setAttribute( 'data-prev', p.value );
				if ( opt ) { p.title = opt.title; }
				say( T.saved, 'is-ok' );
			} else {
				if ( prev ) { p.value = prev; }
				say( errMsg( res, T.notSaved ), 'is-err' );
			}
		} );
	} );
	document.addEventListener( 'keydown', function ( e ) { if ( e.key === 'Escape' ) { modal.hidden = true; } } );
}() );
</script>
