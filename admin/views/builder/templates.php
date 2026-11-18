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
				<?php echo Velox_Admin::icon( 'plus', 15 ); // phpcs:ignore ?> <?php esc_html_e( 'New template', 'velox' ); ?>
			</button>
		</div>
	</header>

	<div class="vba-card vba-tpltest">
		<div class="vba-card-h">
			<div><h2><?php esc_html_e( 'Which template is used here?', 'velox' ); ?></h2>
			<p><?php esc_html_e( 'Paste any address on this site to see which template it gets, and why.', 'velox' ); ?></p></div>
		</div>
		<form class="vba-tpltest-row" id="vba-tpltest">
			<input type="text" class="vba-input" id="vba-tpltest-url" placeholder="<?php echo esc_attr( home_url( '/' ) ); ?>…" aria-label="<?php esc_attr_e( 'Address to check', 'velox' ); ?>" spellcheck="false">
			<button type="submit" class="vba-btn vba-btn-primary"><?php esc_html_e( 'Check', 'velox' ); ?></button>
		</form>
		<div class="vba-tpltest-out" id="vba-tpltest-out" aria-live="polite" hidden></div>
	</div>

	<div class="vba-card">
		<div class="vba-card-h">
			<div><h2><?php esc_html_e( 'Your templates', 'velox' ); ?></h2>
			<p><?php esc_html_e( 'Which template a page gets: the one chosen on the page itself; otherwise a template whose “Where to use” rules match; otherwise a template made for it (Front page, then Posts or Pages); otherwise the site default; otherwise a Catch-all template. 404, Search and Archives templates are used for those screens.', 'velox' ); ?></p>
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
						<?php $rsum = class_exists( 'Velox_Builder_Templates' ) ? Velox_Builder_Templates::summary( $id ) : ''; ?>
						<span class="vba-tpl-rulesum" data-tpl-rulesum="<?php echo $id; ?>" title="<?php echo esc_attr( $rsum ); ?>"<?php echo '' === $rsum ? ' hidden' : ''; ?>><?php echo Velox_Admin::icon( 'check', 12 ); // phpcs:ignore ?> <span><?php echo esc_html( $rsum ); ?></span></span>

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
							<button type="button" class="vba-mini" data-tpl-rules="<?php echo $id; ?>" data-keep title="<?php esc_attr_e( 'Choose exactly where this template is used', 'velox' ); ?>"><?php esc_html_e( 'Where to use', 'velox' ); ?></button>
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
			<button type="button" class="vba-btn vba-btn-ghost" data-close-modal><?php esc_html_e( 'Cancel', 'velox' ); ?></button>
			<button type="button" class="vba-btn vba-btn-primary" id="vba-tpl-create"><?php esc_html_e( 'Create and open', 'velox' ); ?></button>
		</div>
	</div>
</div>

<!-- Where to use: include rules, exceptions, priority, visitors. -->
<div class="vba-modal" id="vba-rules-modal" hidden>
	<div class="vba-modal-back" data-close-rules></div>
	<div class="vba-modal-box vba-rules-box" style="width:min(640px,94vw);height:auto;max-height:90vh">
		<div class="vba-modal-h">
			<b id="vba-rules-title"><?php esc_html_e( 'Where to use this template', 'velox' ); ?></b>
			<button type="button" class="vba-modal-x" data-close-rules aria-label="<?php esc_attr_e( 'Close', 'velox' ); ?>">&times;</button>
		</div>
		<div class="vba-modal-b vba-rules-b">
			<div class="vba-fl"><?php esc_html_e( 'Use on', 'velox' ); ?></div>
			<div class="vba-rules-list" id="vba-rules-inc"></div>
			<button type="button" class="vba-btn vba-btn-ghost vba-rules-add" data-rule-add="inc"><?php echo Velox_Admin::icon( 'plus', 14 ); // phpcs:ignore ?> <?php esc_html_e( 'Add a place', 'velox' ); ?></button>
			<div class="vba-fl"><?php esc_html_e( 'But not on', 'velox' ); ?></div>
			<div class="vba-rules-list" id="vba-rules-exc"></div>
			<button type="button" class="vba-btn vba-btn-ghost vba-rules-add" data-rule-add="exc"><?php echo Velox_Admin::icon( 'plus', 14 ); // phpcs:ignore ?> <?php esc_html_e( 'Add an exception', 'velox' ); ?></button>
			<div class="vba-rules-2col">
				<div>
					<label class="vba-fl" for="vba-rules-who"><?php esc_html_e( 'For', 'velox' ); ?></label>
					<select id="vba-rules-who" class="vba-input">
						<option value=""><?php esc_html_e( 'Everyone', 'velox' ); ?></option>
						<option value="in"><?php esc_html_e( 'Logged-in visitors only', 'velox' ); ?></option>
						<option value="out"><?php esc_html_e( 'Logged-out visitors only', 'velox' ); ?></option>
					</select>
				</div>
				<div>
					<label class="vba-fl" for="vba-rules-pri"><?php esc_html_e( 'Priority', 'velox' ); ?></label>
					<input type="number" id="vba-rules-pri" class="vba-input" value="0" min="-100" max="100" step="1">
				</div>
			</div>
			<p class="vba-hint"><?php esc_html_e( 'When two templates match the same page, the higher priority wins; on a tie, the more specific rule wins (a single page beats a post type, which beats the entire site). A template chosen on the page itself always wins.', 'velox' ); ?></p>
			<p class="vba-hint"><?php esc_html_e( 'Rules apply once the template is published. With no “Use on” rules, the template works by its purpose as before.', 'velox' ); ?></p>
		</div>
		<div class="vba-modal-f">
			<span class="vba-modal-msg" id="vba-rules-msg"></span>
			<button type="button" class="vba-btn vba-btn-ghost" data-close-rules><?php esc_html_e( 'Cancel', 'velox' ); ?></button>
			<button type="button" class="vba-btn vba-btn-primary" id="vba-rules-save"><?php esc_html_e( 'Save', 'velox' ); ?></button>
		</div>
	</div>
</div>
<?php
$vba_rules = array();
foreach ( $docs as $d ) {
	$vba_rules[ (int) $d['id'] ] = class_exists( 'Velox_Builder_Templates' ) ? Velox_Builder_Templates::get( (int) $d['id'] ) : null;
}
?>
<script>
window.VBA_TPL = <?php echo wp_json_encode( array(
	'rules' => (object) $vba_rules,
	'data'  => class_exists( 'Velox_Builder_Templates' ) ? Velox_Builder_Templates::admin_data() : array(),
	'i18n'  => array(
		'ids'       => __( 'IDs, e.g. 12, 40', 'velox' ),
		'terms'     => __( 'slugs or IDs, e.g. news, 12', 'velox' ),
		'allTerms'  => __( 'all (or slugs / IDs)', 'velox' ),
		'url'       => __( '/shop/*', 'velox' ),
		'remove'    => __( 'Remove', 'velox' ),
		'none'      => __( 'No rules: the template works by its purpose.', 'velox' ),
		'noExc'     => __( 'No exceptions.', 'velox' ),
		'checking'  => __( 'Checking…', 'velox' ),
		'uses'      => __( 'uses', 'velox' ),
		'noTpl'     => __( 'gets no Velox template.', 'velox' ),
		'why'       => __( 'Why:', 'velox' ),
		'also'      => __( 'Rules of these templates also match:', 'velox' ),
		'draft'     => __( 'draft, not used', 'velox' ),
		'priority'  => __( 'priority', 'velox' ),
		'edit'      => __( 'Edit', 'velox' ),
		'title'     => __( 'Where to use “%s”', 'velox' ),
		'needValue' => __( 'Fill in the highlighted fields first.', 'velox' ),
	),
) ); ?>;
</script>
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
		notSaved: '<?php echo esc_js( __( 'Not saved', 'velox' ) ); ?>',
		dlgCancel: '<?php echo esc_js( __( 'Cancel', 'velox' ) ); ?>',
		dlgOk: '<?php echo esc_js( __( 'OK', 'velox' ) ); ?>',
		dlgError: '<?php echo esc_js( __( 'Something went wrong', 'velox' ) ); ?>',
		dlgRename: '<?php echo esc_js( __( 'Rename', 'velox' ) ); ?>',
		dlgUnpublishTitle: '<?php echo esc_js( __( 'Unpublish?', 'velox' ) ); ?>',
		dlgUnpublish: '<?php echo esc_js( __( 'Unpublish', 'velox' ) ); ?>',
		dlgDeleteTitle: '<?php echo esc_js( __( 'Delete template?', 'velox' ) ); ?>',
		dlgDelete: '<?php echo esc_js( __( 'Delete', 'velox' ) ); ?>'
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
	// In-app dialogs. velox-builder-admin.js loads in the footer, after this
	// script, so resolve it when used; native dialogs only if it is missing.
	function dlg() {
		return window.vbaDialog || {
			confirm: function ( o, yes, no ) { if ( window.confirm( o.text ) ) { yes(); } else if ( no ) { no(); } },
			prompt: function ( o, cb ) { var v = window.prompt( o.text, o.value || '' ); v = null === v ? '' : String( v ).trim(); if ( v ) { cb( v ); } },
			alert: function ( o ) { window.alert( o.text ); }
		};
	}
	function fail( text ) { dlg().alert( { title:T.dlgError, text:text, ok:T.dlgOk } ); }
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
				if ( ! res || ! res.success ) { fail( errMsg( res, T.defaultFailed ) ); return; }
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
				if ( res && res.success ) { location.reload(); } else { pb.disabled = false; fail( errMsg( res, T.publishFailed ) ); }
			} );
			return;
		}
		var up = e.target.closest( '[data-doc-unpublish]' );
		if ( up ) {
			dlg().confirm( { title:T.dlgUnpublishTitle, text:T.unpublishAsk, ok:T.dlgUnpublish, cancel:T.dlgCancel, danger:true }, function () {
				post( 'builder_unpublish', { id:up.getAttribute( 'data-doc-unpublish' ) }, function ( res ) {
					if ( res && res.success ) { location.reload(); } else { fail( errMsg( res, T.unpublishFailed ) ); }
				} );
			} );
			return;
		}
		var rn = e.target.closest( '[data-doc-rename]' );
		if ( rn ) {
			var row = rn.closest( '.vba-docrow' ), cur = row.querySelector( '.vba-doc-title' ).textContent.trim();
			dlg().prompt( { title:T.dlgRename, text:T.renameTo, value:cur, ok:T.dlgRename, cancel:T.dlgCancel }, function ( nn ) {
				if ( ! nn || nn === cur ) { return; }
				post( 'builder_doc_rename', { id:rn.getAttribute( 'data-doc-rename' ), title:nn }, function ( r2 ) {
					if ( r2 && r2.success ) { location.reload(); } else { fail( errMsg( r2, T.renameFailed ) ); }
				} );
			} );
			return;
		}
		var dp = e.target.closest( '[data-doc-duplicate]' );
		if ( dp ) {
			post( 'builder_doc_duplicate', { id:dp.getAttribute( 'data-doc-duplicate' ) }, function ( r2 ) {
				if ( r2 && r2.success ) { location.reload(); } else { fail( errMsg( r2, T.duplicateFailed ) ); }
			} );
			return;
		}
		var dl = e.target.closest( '[data-doc-delete]' );
		if ( dl ) {
			dlg().confirm( { title:T.dlgDeleteTitle, text:T.deleteAsk, ok:T.dlgDelete, cancel:T.dlgCancel, danger:true }, function () {
				post( 'builder_doc_delete', { id:dl.getAttribute( 'data-doc-delete' ) }, function ( r2 ) {
					if ( r2 && r2.success ) { location.reload(); } else { fail( errMsg( r2, T.deleteFailed ) ); }
				} );
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
	document.addEventListener( 'keydown', function ( e ) { if ( e.key === 'Escape' ) { modal.hidden = true; rmodal.hidden = true; } } );

	/* ---- Where to use: rule editor ---- */
	var TPL = window.VBA_TPL || { rules:{}, data:{ types:[], postTypes:[], taxonomies:[] }, i18n:{} }, L = TPL.i18n;
	var rmodal = document.getElementById( 'vba-rules-modal' ), editing = 0, draft = null;
	function esc( s ) { return String( s == null ? '' : s ).replace( /[&<>"]/g, function ( c ) { return { '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;' }[ c ]; } ); }
	function opts( list, cur, filter ) {
		return list.filter( filter || function () { return true; } ).map( function ( o ) { return '<option value="' + esc( o.v ) + '"' + ( String( cur ) === o.v ? ' selected' : '' ) + '>' + esc( o.l ) + '</option>'; } ).join( '' );
	}
	function needs( t ) {
		return { posttype:'pt', ptarchive:'pta', page:'ids', child:'ids', interm:'tax+terms', tax:'tax+terms?', url:'url' }[ t ] || '';
	}
	function ruleOk( r ) {
		var n = needs( r.t );
		if ( ! n ) { return true; }
		if ( 'tax+terms' === n ) { return !! r.v && !! String( r.v2 || '' ).trim(); }
		return !! String( r.v || '' ).trim();
	}
	function rowHTML( r, list, i ) {
		var n = needs( r.t ), D = TPL.data, val = '';
		if ( 'pt' === n ) { val = '<select class="vba-input" data-rv="v">' + opts( D.postTypes, r.v ) + '</select>'; }
		else if ( 'pta' === n ) { val = '<select class="vba-input" data-rv="v">' + opts( D.postTypes, r.v, function ( o ) { return o.archive; } ) + '</select>'; }
		else if ( 'ids' === n ) { val = '<input class="vba-input" data-rv="v" value="' + esc( r.v ) + '" placeholder="' + esc( L.ids ) + '">'; }
		else if ( 'url' === n ) { val = '<input class="vba-input" data-rv="v" value="' + esc( r.v ) + '" placeholder="' + esc( L.url ) + '" spellcheck="false">'; }
		else if ( 0 === n.indexOf( 'tax' ) ) {
			val = '<select class="vba-input" data-rv="v">' + opts( D.taxonomies, r.v ) + '</select>' +
				'<input class="vba-input" data-rv="v2" value="' + esc( r.v2 ) + '" placeholder="' + esc( 'tax+terms' === n ? L.terms : L.allTerms ) + '" spellcheck="false">';
		}
		return '<div class="vba-rule-row' + ( ruleOk( r ) ? '' : ' is-bad' ) + '" data-rule="' + list + ':' + i + '">' +
			'<select class="vba-input vba-rule-t" data-rv="t">' + opts( D.types, r.t ) + '</select>' +
			( val ? '<span class="vba-rule-val">' + val + '</span>' : '<span class="vba-rule-val"></span>' ) +
			'<button type="button" class="vba-mini vba-mini-del" data-rule-del="' + list + ':' + i + '" aria-label="' + esc( L.remove ) + '">&times;</button></div>';
	}
	function drawRules() {
		[ 'inc', 'exc' ].forEach( function ( k ) {
			var box = document.getElementById( 'vba-rules-' + k );
			box.innerHTML = draft[ k ].length ? draft[ k ].map( function ( r, i ) { return rowHTML( r, k, i ); } ).join( '' )
				: '<p class="vba-hint">' + esc( 'inc' === k ? L.none : L.noExc ) + '</p>';
		} );
	}
	function firstVal( t ) {
		var D = TPL.data;
		if ( 'posttype' === t ) { return ( D.postTypes[ 0 ] || {} ).v || 'post'; }
		if ( 'ptarchive' === t ) { var a = D.postTypes.filter( function ( o ) { return o.archive; } )[ 0 ]; return a ? a.v : ''; }
		if ( 'interm' === t || 'tax' === t ) { return ( D.taxonomies[ 0 ] || {} ).v || 'category'; }
		return '';
	}
	document.addEventListener( 'click', function ( e ) {
		var ob = e.target.closest( '[data-tpl-rules]' );
		if ( ob ) {
			editing = +ob.getAttribute( 'data-tpl-rules' );
			var cur = TPL.rules[ editing ] || {};
			draft = { inc:( cur.inc || [] ).map( function ( r ) { return Object.assign( {}, r ); } ), exc:( cur.exc || [] ).map( function ( r ) { return Object.assign( {}, r ); } ) };
			var name = ( ob.closest( '.vba-docrow' ).querySelector( '.vba-doc-title' ) || {} ).textContent || '';
			document.getElementById( 'vba-rules-title' ).textContent = String( L.title || '%s' ).replace( '%s', name.trim() );
			document.getElementById( 'vba-rules-who' ).value = cur.who || '';
			document.getElementById( 'vba-rules-pri' ).value = cur.pri || 0;
			document.getElementById( 'vba-rules-msg' ).textContent = '';
			drawRules();
			rmodal.hidden = false;
			var f = rmodal.querySelector( '.vba-rule-t, [data-rule-add]' ); if ( f ) { f.focus(); }
			return;
		}
		if ( e.target.closest( '[data-close-rules]' ) ) { rmodal.hidden = true; return; }
		var ad = e.target.closest( '[data-rule-add]' );
		if ( ad ) {
			var k = ad.getAttribute( 'data-rule-add' ), t = 'inc' === k && ! draft.inc.length ? 'entire' : 'page';
			draft[ k ].push( { t:t, v:firstVal( t ), v2:'' } );
			drawRules();
			var rows = document.querySelectorAll( '#vba-rules-' + k + ' .vba-rule-t' ); if ( rows.length ) { rows[ rows.length - 1 ].focus(); }
			return;
		}
		var rd = e.target.closest( '[data-rule-del]' );
		if ( rd ) {
			var pp = rd.getAttribute( 'data-rule-del' ).split( ':' );
			draft[ pp[ 0 ] ].splice( +pp[ 1 ], 1 );
			drawRules();
			return;
		}
		if ( e.target.closest( '#vba-rules-save' ) ) {
			var msg = document.getElementById( 'vba-rules-msg' );
			if ( draft.inc.concat( draft.exc ).some( function ( r ) { return ! ruleOk( r ); } ) ) { msg.textContent = L.needValue; drawRules(); return; }
			msg.textContent = T.saving;
			var data = { inc:draft.inc, exc:draft.exc, who:document.getElementById( 'vba-rules-who' ).value, pri:+document.getElementById( 'vba-rules-pri' ).value || 0 };
			post( 'builder_template_rules_save', { id:editing, rules:JSON.stringify( data ) }, function ( res ) {
				if ( ! res || ! res.success ) { msg.textContent = errMsg( res, T.notSaved ); return; }
				TPL.rules[ editing ] = res.data.rules;
				var sum = document.querySelector( '[data-tpl-rulesum="' + editing + '"]' );
				if ( sum ) { sum.hidden = ! res.data.summary; sum.title = res.data.summary; sum.querySelector( 'span' ).textContent = res.data.summary; }
				rmodal.hidden = true;
			} );
		}
	} );
	function onRuleField( e ) {
		var row = e.target.closest( '[data-rule]' ), f = e.target.getAttribute( 'data-rv' );
		if ( ! row || ! f ) { return; }
		var pp = row.getAttribute( 'data-rule' ).split( ':' ), r = draft[ pp[ 0 ] ][ +pp[ 1 ] ];
		if ( 't' === f ) {
			r.t = e.target.value; r.v = firstVal( r.t ); r.v2 = '';
			drawRules();
			var again = document.querySelector( '[data-rule="' + pp[ 0 ] + ':' + pp[ 1 ] + '"] [data-rv="t"]' ); if ( again ) { again.focus(); }
			return;
		}
		r[ f ] = e.target.value;
		row.classList.toggle( 'is-bad', ! ruleOk( r ) );
	}
	rmodal.addEventListener( 'change', onRuleField );
	rmodal.addEventListener( 'input', function ( e ) { if ( 'INPUT' === e.target.tagName ) { onRuleField( e ); } } );

	/* ---- Which template is used here? ---- */
	var tform = document.getElementById( 'vba-tpltest' );
	if ( tform ) {
		tform.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			var out = document.getElementById( 'vba-tpltest-out' ), url = document.getElementById( 'vba-tpltest-url' ).value.trim();
			if ( ! url ) { return; }
			out.hidden = false; out.className = 'vba-tpltest-out'; out.textContent = L.checking;
			post( 'builder_template_test', { url:url }, function ( res ) {
				if ( ! res || ! res.success ) { out.className = 'vba-tpltest-out is-err'; out.textContent = errMsg( res, T.dlgError ); return; }
				var d = res.data, h = '<div class="vba-tt-main"><b>' + esc( d.what ) + '</b> ';
				h += d.id ? esc( L.uses ) + ' <a href="#" data-tt-go="' + d.id + '"><b>' + esc( d.title ) + '</b></a>.' : esc( L.noTpl );
				h += '</div><div class="vba-tt-why">' + esc( L.why ) + ' ' + esc( d.why ) + '</div>';
				if ( d.note ) { h += '<div class="vba-tt-note">' + esc( d.note ) + '</div>'; }
				var others = ( d.matches || [] ).filter( function ( m ) { return m.id !== d.id; } );
				if ( others.length ) {
					h += '<div class="vba-tt-also">' + esc( L.also ) + ' ' + others.map( function ( m ) {
						return esc( m.title ) + ' (' + esc( L.priority ) + ' ' + m.pri + ( m.live ? '' : ', ' + esc( L.draft ) ) + ')';
					} ).join( ', ' ) + '</div>';
				}
				out.innerHTML = h;
			} );
		} );
		document.addEventListener( 'click', function ( e ) {
			var go = e.target.closest( '[data-tt-go]' );
			if ( ! go ) { return; }
			e.preventDefault();
			var row = document.querySelector( '.vba-docrow[data-doc="' + go.getAttribute( 'data-tt-go' ) + '"]' );
			if ( row ) { row.scrollIntoView( { block:'center', behavior:'smooth' } ); row.classList.add( 'is-flash' ); setTimeout( function () { row.classList.remove( 'is-flash' ); }, 1600 ); }
		} );
	}
}() );
</script>
