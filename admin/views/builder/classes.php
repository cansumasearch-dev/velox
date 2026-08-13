<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** Velox Builder — Classes: every class used across your pages. */
$classes = Velox_Builder::all_classes();
// Classes Velox creates itself when you drop an element in (.section, .heading …)
// versus ones you named. Knowing which is which is the whole point of the filter.
$starters = Velox_Builder::starter_class_names();
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
				<div class="vba-filters" id="vba-class-filters">
					<button class="vba-filter on" data-cfilter="all"><?php esc_html_e( 'All', 'velox' ); ?><span class="vba-filter-n"><?php echo (int) count( $classes ); ?></span></button>
					<button class="vba-filter" data-cfilter="mine"><?php esc_html_e( 'My classes', 'velox' ); ?><span class="vba-filter-n"><?php echo (int) $mine; ?></span></button>
					<button class="vba-filter" data-cfilter="default"><?php esc_html_e( 'Velox defaults', 'velox' ); ?><span class="vba-filter-n"><?php echo (int) $default; ?></span></button>
				</div>
				<div class="vba-search">
					<?php echo Velox_Admin::icon( 'search', 14 ); // phpcs:ignore ?>
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
						<span class="vba-class-meta"><?php echo (int) $info['count']; ?> <?php echo esc_html( _n( 'page', 'pages', (int) $info['count'], 'velox' ) ); ?></span>
						<span class="vba-class-meta"><?php echo (int) $info['props']; ?></span>
						<span class="vba-class-actions">
							<button class="vba-mini vba-mini-go" data-editclass="<?php echo esc_attr( $name ); ?>"><?php esc_html_e( 'Edit', 'velox' ); ?></button>
							<button class="vba-mini vba-mini-del" data-delclass="<?php echo esc_attr( $name ); ?>"><?php esc_html_e( 'Delete', 'velox' ); ?></button>
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
			<button class="vba-modal-x" data-close-modal aria-label="<?php esc_attr_e( 'Close', 'velox' ); ?>">&times;</button>
		</div>
		<div class="vba-modal-b">
			<label class="vba-fl"><?php esc_html_e( 'Class name', 'velox' ); ?></label>
			<input type="text" class="vba-input" id="vba-cm-name" spellcheck="false">
			<label class="vba-fl"><?php esc_html_e( 'CSS', 'velox' ); ?></label>
			<textarea class="vba-code" id="vba-cm-css" spellcheck="false" rows="16"></textarea>
			<p class="vba-hint"><?php esc_html_e( 'Rules for this class only, including :hover and media queries. Properties the builder does not understand are dropped on save.', 'velox' ); ?></p>
		</div>
		<div class="vba-modal-f">
			<span class="vba-modal-msg" id="vba-cm-msg"></span>
			<button class="vba-btn" data-close-modal><?php esc_html_e( 'Cancel', 'velox' ); ?></button>
			<button class="vba-btn vba-btn-primary" id="vba-cm-save"><?php esc_html_e( 'Save changes', 'velox' ); ?></button>
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
	var modal = document.getElementById( 'vba-class-modal' );
	var original = '';
	function openModal( name ) {
		original = name;
		document.getElementById( 'vba-cm-title' ).textContent = name;
		document.getElementById( 'vba-cm-name' ).value = name;
		var ta = document.getElementById( 'vba-cm-css' );
		ta.value = '/* loading… */';
		document.getElementById( 'vba-cm-msg' ).textContent = '';
		modal.hidden = false;
		post( 'builder_class_css', { name:name }, function ( res ) {
			ta.value = ( res && res.success ) ? ( res.data.css || '' ) : '/* could not load */';
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
			msg.textContent = 'Saving…';
			// Save the CSS against the CURRENT name, then rename if it changed —
			// doing it the other way round would write to a class that no longer exists.
			post( 'builder_class_css_save', { name:original, css:css }, function ( res ) {
				if ( ! res || ! res.success ) { msg.textContent = 'Save failed'; return; }
				if ( newName && newName !== original ) {
					post( 'builder_class_rename', { from:original, to:newName }, function ( r2 ) {
						if ( r2 && r2.success ) { location.reload(); } else { msg.textContent = ( r2 && r2.data && r2.data.message ) || 'Rename failed'; }
					} );
				} else { location.reload(); }
			} );
			return;
		}
		var dl = e.target.closest( '[data-delclass]' );
		if ( dl ) {
			var cls = dl.getAttribute( 'data-delclass' );
			if ( ! confirm( 'Delete ' + cls + ' from every page? This removes its styles.' ) ) { return; }
			post( 'builder_class_delete', { 'class':cls }, function ( res ) {
				if ( res && res.success ) { location.reload(); } else { alert( 'Delete failed' ); }
			} );
		}
		var cf = e.target.closest( '[data-cfilter]' );
		if ( cf ) {
			document.querySelectorAll( '[data-cfilter]' ).forEach( function ( b ) { b.classList.remove( 'on' ); } );
			cf.classList.add( 'on' );
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
