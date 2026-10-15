<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** Velox Builder — Classes: every class used across your pages. */
$classes = Velox_Builder::all_classes();
// Classes Velox creates itself when you drop an element in (.section, .heading …)
// versus ones you named. Knowing which is which is the whole point of the filter.
$starters = Velox_Builder::starter_class_names();
// The media queries the class editor maps back to the site breakpoints.
$bp_queries = array();
if ( class_exists( 'Velox_Builder_Render' ) && method_exists( 'Velox_Builder_Render', 'media_queries' ) ) {
	foreach ( (array) Velox_Builder_Render::media_queries() as $bp_key => $bp_mq ) {
		if ( $bp_mq && in_array( $bp_key, array( 'xxl', 'xl', 'lg', 'md', 'sm' ), true ) ) {
			$bp_queries[ $bp_mq ] = true;
		}
	}
}
$mine     = 0;
$default  = 0;
foreach ( $classes as $name => $info ) {
	if ( in_array( $name, $starters, true ) ) {
		$default++;
	} else {
		$mine++;
	}
}
?>
<div class="vba-shell">
	<header class="vba-head">
		<div><h1><?php esc_html_e( 'Classes', 'velox' ); ?></h1>
		<p><?php esc_html_e( 'Every styling class across your Velox Builder pages. Edit the CSS, rename, or delete site-wide.', 'velox' ); ?></p></div>
	</header>
	<div class="vba-card">
		<?php if ( empty( $classes ) ) : ?>
			<div class="vba-empty">
				<span class="vba-empty-ic"><?php echo Velox_Admin::icon( 'tag', 24 ); // phpcs:ignore ?></span>
				<b><?php esc_html_e( 'No classes yet', 'velox' ); ?></b>
				<p><?php esc_html_e( 'Classes appear here as soon as you style elements in the editor.', 'velox' ); ?></p>
			</div>
		<?php else : ?>
			<div class="vba-toolbar">
				<div class="vba-filters" id="vba-class-filters" role="group" aria-label="<?php esc_attr_e( 'Filter classes', 'velox' ); ?>">
					<button type="button" class="vba-filter on" data-cfilter="all" aria-pressed="true"><?php esc_html_e( 'All', 'velox' ); ?><span class="vba-filter-n"><?php echo (int) count( $classes ); ?></span></button>
					<button type="button" class="vba-filter" data-cfilter="mine" aria-pressed="false"><?php esc_html_e( 'My classes', 'velox' ); ?><span class="vba-filter-n"><?php echo (int) $mine; ?></span></button>
					<button type="button" class="vba-filter" data-cfilter="default" aria-pressed="false"><?php esc_html_e( 'Velox defaults', 'velox' ); ?><span class="vba-filter-n"><?php echo (int) $default; ?></span></button>
				</div>
				<div class="vba-search">
					<?php echo Velox_Admin::icon( 'search', 14 ); // phpcs:ignore ?>
					<label class="screen-reader-text" for="vba-class-search"><?php esc_html_e( 'Search classes', 'velox' ); ?></label>
					<input type="search" id="vba-class-search" placeholder="<?php esc_attr_e( 'Search classes…', 'velox' ); ?>">
				</div>
			</div>
			<div class="vba-classlist" id="vba-classlist">
				<div class="vba-class-head">
					<span><?php esc_html_e( 'Class', 'velox' ); ?></span>
					<span><?php esc_html_e( 'Origin', 'velox' ); ?></span>
					<span><?php esc_html_e( 'Used in', 'velox' ); ?></span>
					<span><?php esc_html_e( 'Properties', 'velox' ); ?></span>
					<span></span>
				</div>
				<?php foreach ( $classes as $name => $info ) : ?>
					<?php $is_default = in_array( $name, $starters, true ); ?>
					<div class="vba-class-row" data-class="<?php echo esc_attr( $name ); ?>" data-origin="<?php echo $is_default ? 'default' : 'mine'; ?>" data-name="<?php echo esc_attr( strtolower( $name ) ); ?>">
						<span class="vba-class-name"><?php echo esc_html( $name ); ?></span>
						<span class="vba-class-meta"><span class="vba-origin vba-origin-<?php echo $is_default ? 'default' : 'mine' ; ?>"><?php echo esc_html( $is_default ? __( 'Velox', 'velox' ) : __( 'Yours', 'velox' ) ); ?></span></span>
						<span class="vba-class-meta"><?php
							echo esc_html( 1 === (int) $info['count']
								? __( '1 page', 'velox' )
								/* translators: %d: number of pages. */
								: sprintf( __( '%d pages', 'velox' ), (int) $info['count'] ) );
						?></span>
						<span class="vba-class-meta"><?php echo (int) $info['props']; ?></span>
						<span class="vba-class-actions">
							<button type="button" class="vba-mini vba-mini-go" data-editclass="<?php echo esc_attr( $name ); ?>"><?php esc_html_e( 'Edit', 'velox' ); ?></button>
							<button type="button" class="vba-mini vba-mini-del" data-delclass="<?php echo esc_attr( $name ); ?>" data-starter="<?php echo $is_default ? '1' : '0'; ?>"><?php esc_html_e( 'Delete', 'velox' ); ?></button>
						</span>
					</div>
				<?php endforeach; ?>
				<div class="vba-nores" id="vba-class-nores" hidden><?php esc_html_e( 'No classes match that search.', 'velox' ); ?></div>
			</div>
		<?php endif; ?>
	</div>
</div>

<!-- Class editor: name + raw CSS for that class, including states and breakpoints. -->
<div class="vba-modal" id="vba-class-modal" hidden>
	<div class="vba-modal-back" data-close-modal></div>
	<div class="vba-modal-box">
		<div class="vba-modal-h">
			<b id="vba-cm-title"><?php esc_html_e( 'Edit class', 'velox' ); ?></b>
			<button type="button" class="vba-modal-x" data-close-modal aria-label="<?php esc_attr_e( 'Close', 'velox' ); ?>">&times;</button>
		</div>
		<div class="vba-modal-b">
			<label class="vba-fl" for="vba-cm-name"><?php esc_html_e( 'Class name', 'velox' ); ?></label>
			<input type="text" class="vba-input" id="vba-cm-name" spellcheck="false">
			<label class="vba-fl" for="vba-cm-css"><?php esc_html_e( 'CSS', 'velox' ); ?></label>
			<textarea class="vba-code" id="vba-cm-css" spellcheck="false" rows="16"></textarea>
			<p class="vba-hint"><?php esc_html_e( 'Rules for this class only, including states such as :hover. Properties the builder does not understand are dropped on save.', 'velox' ); ?></p>
			<p class="vba-hint"><?php esc_html_e( 'Responsive rules are kept when they use one of the site breakpoints:', 'velox' ); ?>
				<?php if ( $bp_queries ) : ?>
					<?php foreach ( array_keys( $bp_queries ) as $bp_mq ) : ?><code class="vba-mq">@media <?php echo esc_html( $bp_mq ); ?></code> <?php endforeach; ?>
				<?php else : ?>
					<code class="vba-mq">@media (max-width: 991.98px)</code> <code class="vba-mq">@media (max-width: 767.98px)</code>
				<?php endif; ?>
				<?php esc_html_e( 'Media queries at other widths are not understood and are dropped.', 'velox' ); ?>
			</p>
		</div>
		<div class="vba-modal-f">
			<span class="vba-modal-msg" id="vba-cm-msg"></span>
			<button type="button" class="vba-btn" data-close-modal><?php esc_html_e( 'Cancel', 'velox' ); ?></button>
			<button type="button" class="vba-btn vba-btn-primary" id="vba-cm-save"><?php esc_html_e( 'Save changes', 'velox' ); ?></button>
		</div>
	</div>
</div>

<script>
( function () {
	var T = {
		loading: '<?php echo esc_js( __( '/* loading… */', 'velox' ) ); ?>',
		loadFailed: '<?php echo esc_js( __( '/* could not load */', 'velox' ) ); ?>',
		saving: '<?php echo esc_js( __( 'Saving…', 'velox' ) ); ?>',
		saveFailed: '<?php echo esc_js( __( 'Save failed', 'velox' ) ); ?>',
		renameFailed: '<?php echo esc_js( __( 'Rename failed', 'velox' ) ); ?>',
		deleteFailed: '<?php echo esc_js( __( 'Delete failed', 'velox' ) ); ?>',
		deleteAsk: '<?php /* translators: %s: class name, e.g. .hero */ echo esc_js( __( 'Delete %s from every page? This removes its styles.', 'velox' ) ); ?>',
		deleteStarter: '<?php /* translators: %s: class name, e.g. .section */ echo esc_js( __( '%s is a Velox starter class: elements get it automatically when you add them, and it carries their default look. Deleting it strips those styles from every page that uses it. To confirm, type the class name:', 'velox' ) ); ?>',
		noMatch: '<?php echo esc_js( __( 'The name did not match, so nothing was deleted.', 'velox' ) ); ?>'
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
	var modal = document.getElementById( 'vba-class-modal' );
	var original = '';
	function openModal( name ) {
		original = name;
		document.getElementById( 'vba-cm-title' ).textContent = name;
		document.getElementById( 'vba-cm-name' ).value = name;
		var ta = document.getElementById( 'vba-cm-css' );
		ta.value = T.loading;
		document.getElementById( 'vba-cm-msg' ).textContent = '';
		modal.hidden = false;
		post( 'builder_class_css', { name:name }, function ( res ) {
			ta.value = ( res && res.success ) ? ( res.data.css || '' ) : T.loadFailed;
		} );
	}
	function closeModal() { modal.hidden = true; }

	document.addEventListener( 'click', function ( e ) {
		var ed = e.target.closest( '[data-editclass]' );
		if ( ed ) { openModal( ed.getAttribute( 'data-editclass' ) ); return; }
		if ( e.target.closest( '[data-close-modal]' ) ) { closeModal(); return; }

		if ( e.target.closest( '#vba-cm-save' ) ) {
			var newName = document.getElementById( 'vba-cm-name' ).value.trim();
			var css = document.getElementById( 'vba-cm-css' ).value;
			var msg = document.getElementById( 'vba-cm-msg' );
			msg.textContent = T.saving;
			// Save the CSS against the CURRENT name, then rename if it changed —
			// doing it the other way round would write to a class that no longer exists.
			post( 'builder_class_css_save', { name:original, css:css }, function ( res ) {
				if ( ! res || ! res.success ) { msg.textContent = ( res && res.data && res.data.message ) || T.saveFailed; return; }
				if ( newName && newName !== original ) {
					post( 'builder_class_rename', { from:original, to:newName }, function ( r2 ) {
						if ( r2 && r2.success ) { location.reload(); } else { msg.textContent = ( r2 && r2.data && r2.data.message ) || T.renameFailed; }
					} );
				} else { location.reload(); }
			} );
			return;
		}
		var dl = e.target.closest( '[data-delclass]' );
		if ( dl ) {
			var cls = dl.getAttribute( 'data-delclass' );
			if ( '1' === dl.getAttribute( 'data-starter' ) ) {
				// Starter classes style every element of their kind, so a plain
				// OK/Cancel is too easy to click through: make them type the name.
				var typed = prompt( T.deleteStarter.replace( '%s', cls ), '' );
				if ( null === typed ) { return; }
				typed = typed.trim();
				if ( typed !== cls && '.' + typed !== cls ) { alert( T.noMatch ); return; }
			} else if ( ! confirm( T.deleteAsk.replace( '%s', cls ) ) ) { return; }
			post( 'builder_class_delete', { 'class':cls }, function ( res ) {
				if ( res && res.success ) { location.reload(); } else { alert( ( res && res.data && res.data.message ) || T.deleteFailed ); }
			} );
			return;
		}
		var cf = e.target.closest( '[data-cfilter]' );
		if ( cf ) {
			document.querySelectorAll( '[data-cfilter]' ).forEach( function ( b ) { b.classList.remove( 'on' ); b.setAttribute( 'aria-pressed', 'false' ); } );
			cf.classList.add( 'on' ); cf.setAttribute( 'aria-pressed', 'true' );
			applyClassFilter();
		}
	} );
	document.addEventListener( 'keydown', function ( e ) { if ( e.key === 'Escape' && ! modal.hidden ) { closeModal(); } } );

	function applyClassFilter() {
		var active = document.querySelector( '[data-cfilter].on' );
		var f = active ? active.getAttribute( 'data-cfilter' ) : 'all';
		var si = document.getElementById( 'vba-class-search' );
		var q = si ? si.value.trim().toLowerCase() : '';
		var shown = 0;
		document.querySelectorAll( '.vba-class-row' ).forEach( function ( row ) {
			var okF = ( f === 'all' ) || row.getAttribute( 'data-origin' ) === f;
			var okQ = ! q || row.getAttribute( 'data-name' ).indexOf( q ) > -1;
			var on = okF && okQ;
			row.style.display = on ? '' : 'none';
			if ( on ) { shown++; }
		} );
		var nr = document.getElementById( 'vba-class-nores' ); if ( nr ) { nr.hidden = shown > 0; }
	}
	var cs = document.getElementById( 'vba-class-search' );
	if ( cs ) { cs.addEventListener( 'input', applyClassFilter ); }
}() );
</script>
