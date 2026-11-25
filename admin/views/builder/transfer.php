<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** Velox Builder — Import & export: move pages, templates, components and the design system between sites. */
$docs   = Velox_Builder_Transfer::doc_list();
$groups = array(
	'page'     => __( 'Pages', 'velox' ),
	'template' => __( 'Templates', 'velox' ),
	'reusable' => __( 'Reusables', 'velox' ), // the same word as the menu and the editor
);
$by = array( 'page' => array(), 'template' => array(), 'reusable' => array() );
foreach ( $docs as $d ) {
	$by[ $d['kind'] ][] = $d;
}
$parts = array(
	'classes' => array( __( 'Classes', 'velox' ), __( 'Every class edited on this site', 'velox' ) ),
	'tokens'  => array( __( 'Design tokens', 'velox' ), __( 'Colours, type and spacing scales, custom tokens', 'velox' ) ),
	'styles'  => array( __( 'Global styles', 'velox' ), __( 'Body text, headings, links, page width, animations', 'velox' ) ),
	'fonts'   => array( __( 'Fonts', 'velox' ), __( 'Font families and their settings', 'velox' ) ),
	'css'     => array( __( 'Global CSS', 'velox' ), __( 'The CSS files that load on every page', 'velox' ) ),
);
if ( current_user_can( 'unfiltered_html' ) ) {
	$parts['js'] = array( __( 'Global JavaScript', 'velox' ), __( 'Scripts that run on every page (they arrive switched off)', 'velox' ) );
}
?>
<div class="vba-shell vba-xf">
	<header class="vba-head">
		<div>
			<h1><?php esc_html_e( 'Import & export', 'velox' ); ?></h1>
			<p><?php esc_html_e( 'Move pages, templates, components and your design system to another Velox site as one file, or keep a copy. To move a single element, copy it in the editor and paste it on the other site.', 'velox' ); ?></p>
		</div>
	</header>

	<section class="vba-card" id="vba-xf-export">
		<div class="vba-card-h"><div>
			<h2><?php esc_html_e( 'Export', 'velox' ); ?></h2>
			<p><?php esc_html_e( 'Choose what goes into the file. Components and forms that your choice uses come along by themselves.', 'velox' ); ?></p>
		</div></div>
		<div class="vba-xf-cols">
			<?php foreach ( $groups as $kind => $label ) : ?>
				<div class="vba-xf-group">
					<label class="vba-xf-gh">
						<input type="checkbox" data-xf-all="<?php echo esc_attr( $kind ); ?>" <?php disabled( ! $by[ $kind ] ); ?>>
						<b><?php echo esc_html( $label ); ?></b>
						<span class="vba-xf-n"><?php echo (int) count( $by[ $kind ] ); ?></span>
					</label>
					<?php if ( ! $by[ $kind ] ) : ?>
						<p class="vba-xf-none"><?php esc_html_e( 'None yet', 'velox' ); ?></p>
					<?php else : ?>
						<div class="vba-xf-list">
							<?php foreach ( $by[ $kind ] as $d ) : ?>
								<label class="vba-xf-item">
									<input type="checkbox" data-xf-doc="<?php echo (int) $d['id']; ?>" data-kind="<?php echo esc_attr( $kind ); ?>">
									<span class="vba-xf-t"><?php echo esc_html( '' !== $d['title'] ? $d['title'] : __( 'Untitled', 'velox' ) ); ?></span>
									<?php
									// Seven pages called "Untitled" could not be told apart: show where each one lives.
									$where = ! empty( $d['post_id'] ) ? wp_make_link_relative( (string) get_permalink( (int) $d['post_id'] ) ) : '';
									if ( '' !== $where ) :
										?><span class="vba-xf-where"><?php echo esc_html( $where ); ?></span><?php
									endif;
									?>
									<?php if ( 'published' !== $d['status'] ) : ?><span class="vba-xf-draft"><?php esc_html_e( 'Draft', 'velox' ); ?></span><?php endif; ?>
								</label>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
			<div class="vba-xf-group">
				<label class="vba-xf-gh"><input type="checkbox" data-xf-all="design"><b><?php esc_html_e( 'Design system', 'velox' ); ?></b></label>
				<div class="vba-xf-list">
					<?php foreach ( $parts as $key => $p ) : ?>
						<label class="vba-xf-item vba-xf-item-2">
							<input type="checkbox" data-xf-part="<?php echo esc_attr( $key ); ?>" data-kind="design">
							<span class="vba-xf-t"><?php echo esc_html( $p[0] ); ?><small><?php echo esc_html( $p[1] ); ?></small></span>
						</label>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
		<div class="vba-xf-foot">
			<span class="vba-xf-sum" id="vba-xf-sum"><?php esc_html_e( 'Nothing chosen yet', 'velox' ); ?></span>
			<button type="button" class="vba-btn vba-btn-primary" id="vba-xf-download" disabled><?php esc_html_e( 'Download file', 'velox' ); ?></button>
		</div>
	</section>

	<section class="vba-card" id="vba-xf-import">
		<div class="vba-card-h"><div>
			<h2><?php esc_html_e( 'Import', 'velox' ); ?></h2>
			<p><?php esc_html_e( 'Bring in a file made with Export on any Velox site. You see what is in it before anything changes.', 'velox' ); ?></p>
		</div></div>
		<div class="vba-xf-step" data-step="pick">
			<label class="vba-xf-drop" id="vba-xf-drop">
				<input type="file" accept=".json,application/json" id="vba-xf-file">
				<b><?php esc_html_e( 'Choose a file', 'velox' ); ?></b>
				<span><?php esc_html_e( 'or drop it here (.json)', 'velox' ); ?></span>
			</label>
			<p class="vba-xf-err" id="vba-xf-err" hidden></p>
		</div>
		<div class="vba-xf-step" data-step="review" hidden>
			<p class="vba-xf-from" id="vba-xf-from"></p>
			<div id="vba-xf-review"></div>
			<div class="vba-xf-foot">
				<span class="vba-xf-sum"><?php esc_html_e( 'Pages and templates arrive as drafts. Nothing on the site changes for visitors until you publish.', 'velox' ); ?></span>
				<button type="button" class="vba-btn vba-btn-ghost" id="vba-xf-cancel"><?php esc_html_e( 'Cancel', 'velox' ); ?></button>
				<button type="button" class="vba-btn vba-btn-primary" id="vba-xf-go"><?php esc_html_e( 'Import', 'velox' ); ?></button>
			</div>
		</div>
		<div class="vba-xf-step" data-step="done" hidden>
			<p class="vba-xf-progress" id="vba-xf-progress"></p>
			<div id="vba-xf-result"></div>
			<div class="vba-xf-foot">
				<span class="vba-xf-sum"></span>
				<button type="button" class="vba-btn vba-btn-ghost" id="vba-xf-again"><?php esc_html_e( 'Import another file', 'velox' ); ?></button>
			</div>
		</div>
	</section>
</div>
<script>
( function () {
	var T = <?php echo wp_json_encode( array(
		'none'      => __( 'Nothing chosen yet', 'velox' ),
		'chosen'    => __( '%s chosen', 'velox' ),
		'preparing' => __( 'Preparing…', 'velox' ),
		'download'  => __( 'Download file', 'velox' ),
		'failed'    => __( 'Something went wrong. Try again.', 'velox' ),
		'reading'   => __( 'Reading the file…', 'velox' ),
		'notjson'   => __( 'This file could not be read. Choose the .json file that Export made.', 'velox' ),
		'from'      => __( 'From %1$s, made on %2$s.', 'velox' ),
		'same'      => __( 'This file was made on this site, so images and links stay as they are.', 'velox' ),
		'Page'      => __( 'Page', 'velox' ),
		'Template'  => __( 'Template', 'velox' ),
		'Component' => __( 'Component', 'velox' ),
		'Form'      => __( 'Form', 'velox' ),
		'new'       => __( 'New', 'velox' ),
		'asCopy'    => __( 'Add as a copy', 'velox' ),
		'replace'   => __( 'Replace this site’s', 'velox' ),
		'skip'      => __( 'Skip (use this site’s)', 'velox' ),
		'exists'    => __( 'This site has one with this name', 'velox' ),
		'needed'    => __( 'used by the others', 'velox' ),
		'formHere'  => __( 'Already here, yours is used', 'velox' ),
		'formNew'   => __( 'Added as a new form. Notifications go to this site’s admin address.', 'velox' ),
		'content'   => __( 'Content', 'velox' ),
		'design'    => __( 'Design system', 'velox' ),
		'classes'   => __( 'Classes', 'velox' ),
		'classesN'  => __( '%1$s in the file: %2$s new, %3$s different from yours', 'velox' ),
		'tokens'    => __( 'Design tokens', 'velox' ),
		'tokensN'   => __( '%1$s new, %2$s different from yours', 'velox' ),
		'scales'    => __( 'The type and spacing scales differ too.', 'velox' ),
		'keep'      => __( 'Add new ones, keep yours where both have one', 'velox' ),
		'file'      => __( 'Add new ones, use the file’s where both have one', 'velox' ),
		'dont'      => __( 'Don’t import', 'velox' ),
		'styles'    => __( 'Replace this site’s global styles', 'velox' ),
		'stylesSame'=> __( 'Global styles are the same as here.', 'velox' ),
		'classesSame'=> __( 'Classes are the same as here.', 'velox' ),
		'cssSame'   => __( 'The global CSS files are here already.', 'velox' ),
		'tokensSame'=> __( 'Design tokens are the same as here.', 'velox' ),
		'fonts'     => __( 'Add these font families: %s', 'velox' ),
		'fontsNone' => __( 'All font families are here already.', 'velox' ),
		'css'       => __( 'Add the global CSS files (%s)', 'velox' ),
		'js'        => __( 'Add the global JavaScript files (%s), switched off', 'velox' ),
		'jsNo'      => __( 'The file has global JavaScript. Your account may not add scripts, so it is left out.', 'velox' ),
		'jsHint'    => __( 'Check them in the editor’s Code panel, then switch them on.', 'velox' ),
		'media'     => __( 'Images and links', 'velox' ),
		'images'    => __( 'Copy %s images into this site’s media library', 'velox' ),
		'imagesH'   => __( 'Otherwise they keep loading from the old site, and break when it goes away.', 'velox' ),
		'links'     => __( 'Point links to the old site at this site', 'velox' ),
		'pageJs'    => __( '%s of the pages have their own JavaScript.', 'velox' ),
		'pageJsNo'  => __( '%s of the pages have their own JavaScript. Your account may not add scripts, so it is left out.', 'velox' ),
		'importing' => __( 'Importing…', 'velox' ),
		'copying'   => __( 'Copying images: %1$s of %2$s…', 'velox' ),
		'done'      => __( 'Done.', 'velox' ),
		'copied'    => __( '%s images copied.', 'velox' ),
		'notCopied' => __( '%s could not be copied and still load from the old site.', 'velox' ),
		'added'     => __( 'Added', 'velox' ),
		'replaced'  => __( 'Replaced (the old version is in Saved versions)', 'velox' ),
		'open'      => __( 'Open', 'velox' ),
		'skipped'   => __( '%s skipped.', 'velox' ),
		'designIn'  => __( 'Design system updated.', 'velox' ),
		'nothing'   => __( 'Nothing was imported.', 'velox' ),
		'publish'   => __( 'Open each page or template, check it, then publish it.', 'velox' ),
	) ); ?>;
	function cfg() { return window.VELOX_BUILDER || {}; }
	function q( s, r ) { return ( r || document ).querySelector( s ); }
	function qa( s, r ) { return [].slice.call( ( r || document ).querySelectorAll( s ) ); }
	function fmt( s ) { var a = [].slice.call( arguments, 1 ), i = 0; return s.replace( /%(\d)\$s|%s/g, function ( m, n ) { return String( n ? a[ n - 1 ] : a[ i++ ] ); } ); }
	function esc( s ) { var d = document.createElement( 'div' ); d.textContent = String( s == null ? '' : s ); return d.innerHTML; }
	function api( action, fields ) {
		var C = cfg(), body = new FormData();
		body.append( 'action', 'velox' ); body.append( 'do', action ); body.append( 'nonce', C.nonce || '' );
		Object.keys( fields ).forEach( function ( k ) { body.append( k, fields[ k ] ); } );
		return fetch( C.ajaxurl || window.ajaxurl, { method:'POST', body:body, credentials:'same-origin' } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( res ) { if ( ! res || ! res.success ) { throw new Error( ( res && res.data && res.data.message ) || T.failed ); } return res.data; } );
	}

	/* ---------------- export ---------------- */
	var ex = q( '#vba-xf-export' ), dl = q( '#vba-xf-download' );
	function exCount() {
		var n = qa( '[data-xf-doc]:checked, [data-xf-part]:checked', ex ).length;
		q( '#vba-xf-sum' ).textContent = n ? fmt( T.chosen, n ) : T.none;
		dl.disabled = ! n;
		qa( '[data-xf-all]', ex ).forEach( function ( a ) {
			var k = a.getAttribute( 'data-xf-all' ), box = qa( '[data-kind="' + k + '"]', ex ), on = box.filter( function ( b ) { return b.checked; } ).length;
			a.checked = !! box.length && on === box.length; a.indeterminate = on > 0 && on < box.length;
		} );
	}
	ex.addEventListener( 'change', function ( e ) {
		var all = e.target.getAttribute( 'data-xf-all' );
		if ( all ) { qa( '[data-kind="' + all + '"]', ex ).forEach( function ( b ) { b.checked = e.target.checked; } ); }
		exCount();
	} );
	dl.addEventListener( 'click', function () {
		var ids = qa( '[data-xf-doc]:checked', ex ).map( function ( b ) { return +b.getAttribute( 'data-xf-doc' ); } );
		var parts = qa( '[data-xf-part]:checked', ex ).map( function ( b ) { return b.getAttribute( 'data-xf-part' ); } );
		dl.disabled = true; dl.textContent = T.preparing;
		api( 'builder_export', { ids:JSON.stringify( ids ), parts:JSON.stringify( parts ) } ).then( function ( d ) {
			var blob = new Blob( [ JSON.stringify( d.file ) ], { type:'application/json' } ), a = document.createElement( 'a' );
			a.href = URL.createObjectURL( blob ); a.download = d.name; document.body.appendChild( a ); a.click();
			setTimeout( function () { URL.revokeObjectURL( a.href ); a.remove(); }, 1000 );
		} ).catch( function ( err ) { window.vbaDialog ? vbaDialog.alert( { title:T.download, text:err.message } ) : alert( err.message ); } )
			.then( function () { dl.textContent = T.download; exCount(); } );
	} );

	/* ---------------- import ---------------- */
	var im = q( '#vba-xf-import' ), fileText = '', plan = null;
	function step( name ) { qa( '.vba-xf-step', im ).forEach( function ( s ) { s.hidden = s.getAttribute( 'data-step' ) !== name; } ); }
	function err( msg ) { var e = q( '#vba-xf-err' ); e.textContent = msg || ''; e.hidden = ! msg; }
	function readFile( f ) {
		if ( ! f ) { return; }
		err( T.reading );
		var r = new FileReader();
		r.onload = function () {
			fileText = String( r.result || '' );
			try { JSON.parse( fileText ); } catch ( e ) { err( T.notjson ); return; }
			api( 'builder_import_preview', { file:fileText } ).then( function ( p ) { plan = p; err( '' ); review(); step( 'review' ); } ).catch( function ( e ) { err( e.message ); } );
		};
		r.onerror = function () { err( T.notjson ); };
		r.readAsText( f );
	}
	q( '#vba-xf-file' ).addEventListener( 'change', function () { readFile( this.files[ 0 ] ); this.value = ''; } );
	var drop = q( '#vba-xf-drop' );
	[ 'dragenter', 'dragover' ].forEach( function ( t ) { drop.addEventListener( t, function ( e ) { e.preventDefault(); drop.classList.add( 'is-over' ); } ); } );
	[ 'dragleave', 'drop' ].forEach( function ( t ) { drop.addEventListener( t, function () { drop.classList.remove( 'is-over' ); } ); } );
	drop.addEventListener( 'drop', function ( e ) { e.preventDefault(); readFile( e.dataTransfer.files[ 0 ] ); } );

	var KIND = { page:T.Page, template:T.Template, reusable:T.Component };
	function sw( id, on, label, hint, off ) {
		return '<label class="vba-setting vba-xf-opt"><div class="vba-setting-tx"><b>' + esc( label ) + '</b>' + ( hint ? '<span>' + esc( hint ) + '</span>' : '' ) + '</div>' +
			'<span class="vba-switch"><input type="checkbox" id="' + id + '"' + ( on ? ' checked' : '' ) + ( off ? ' disabled' : '' ) + '><span></span></span></label>';
	}
	function sel( id, label, hint ) {
		return '<div class="vba-setting vba-xf-opt"><div class="vba-setting-tx"><b>' + esc( label ) + '</b>' + ( hint ? '<span>' + esc( hint ) + '</span>' : '' ) + '</div>' +
			'<select class="vba-setting-ctrl" id="' + id + '"><option value="keep">' + esc( T.keep ) + '</option><option value="file">' + esc( T.file ) + '</option><option value="skip">' + esc( T.dont ) + '</option></select></div>';
	}
	function review() {
		var p = plan, h = '', site = p.site || {};
		q( '#vba-xf-from' ).textContent = fmt( T.from, site.name || site.url || '?', site.created ? new Date( site.created ).toLocaleDateString() : '?' ) + ( site.same ? ' ' + T.same : '' );
		if ( p.docs.length || p.forms.length ) {
			h += '<h3 class="vba-xf-h3">' + esc( T.content ) + '</h3><div class="vba-xf-rows">';
			p.docs.forEach( function ( d ) {
				h += '<div class="vba-xf-row"><span class="vba-xf-kind">' + esc( KIND[ d.kind ] ) + '</span><span class="vba-xf-t">' + esc( d.title ) + ( d.auto ? ' <small>' + esc( T.needed ) + '</small>' : '' ) + '</span>';
				h += d.exists
					? '<select class="vba-setting-ctrl" data-doc="' + d.key + '" title="' + esc( T.exists ) + '"><option value="new">' + esc( T.asCopy ) + '</option><option value="replace">' + esc( T.replace ) + '</option><option value="skip"' + ( d.auto ? ' selected' : '' ) + '>' + esc( T.skip ) + '</option></select>'
					: '<span class="vba-xf-new">' + esc( T.new ) + '</span>';
				h += '</div>';
			} );
			p.forms.forEach( function ( f ) {
				h += '<div class="vba-xf-row"><span class="vba-xf-kind">' + esc( T.Form ) + '</span><span class="vba-xf-t">' + esc( f.title ) + '<small>' + esc( f.exists ? T.formHere : T.formNew ) + '</small></span><span class="vba-xf-new">' + esc( f.exists ? '' : T.new ) + '</span></div>';
			} );
			h += '</div>';
		}
		var ds = p.design || {}, dh = '';
		if ( ds.classes ) { dh += ( ds.classes.new || ds.classes.differ ) ? sel( 'vba-xf-classes', T.classes, fmt( T.classesN, ds.classes.total, ds.classes.new, ds.classes.differ ) ) : '<p class="vba-xf-note">' + esc( T.classesSame ) + '</p>'; }
		if ( ds.tokens ) { dh += ( ds.tokens.new || ds.tokens.differ || ds.tokens.scales ) ? sel( 'vba-xf-tokens', T.tokens, fmt( T.tokensN, ds.tokens.new, ds.tokens.differ ) + ( ds.tokens.scales ? ' ' + T.scales : '' ) ) : '<p class="vba-xf-note">' + esc( T.tokensSame ) + '</p>'; }
		if ( ds.styles ) { dh += ds.styles.differ ? sw( 'vba-xf-styles', false, T.styles ) : '<p class="vba-xf-note">' + esc( T.stylesSame ) + '</p>'; }
		if ( ds.fonts ) { dh += ds.fonts.new.length ? sw( 'vba-xf-fonts', true, fmt( T.fonts, ds.fonts.new.join( ', ' ) ) ) : '<p class="vba-xf-note">' + esc( T.fontsNone ) + '</p>'; }
		if ( ds.css ) { dh += ds.css.files ? sw( 'vba-xf-css', true, fmt( T.css, ds.css.files ) ) : '<p class="vba-xf-note">' + esc( T.cssSame ) + '</p>'; }
		if ( ds.js && ds.js.files ) { dh += ds.js.allowed ? sw( 'vba-xf-js', false, fmt( T.js, ds.js.files ), T.jsHint ) : '<p class="vba-xf-note">' + esc( T.jsNo ) + '</p>'; }
		if ( dh ) { h += '<h3 class="vba-xf-h3">' + esc( T.design ) + '</h3><div class="vba-xf-opts">' + dh + '</div>'; }
		var mh = '';
		if ( p.images ) { mh += sw( 'vba-xf-images', true, fmt( T.images, p.images ), T.imagesH ); }
		if ( p.links ) { mh += sw( 'vba-xf-links', true, T.links ); }
		if ( mh ) { h += '<h3 class="vba-xf-h3">' + esc( T.media ) + '</h3><div class="vba-xf-opts">' + mh + '</div>'; }
		if ( p.js ) { h += '<p class="vba-xf-note vba-xf-warn">' + esc( fmt( p.mayJs ? T.pageJs : T.pageJsNo, p.js ) ) + '</p>'; }
		q( '#vba-xf-review' ).innerHTML = h;
	}
	function val( id ) { var e = q( '#' + id ); return e ? ( 'checkbox' === e.type ? e.checked : e.value ) : undefined; }
	q( '#vba-xf-cancel' ).addEventListener( 'click', function () { plan = null; fileText = ''; step( 'pick' ); } );
	q( '#vba-xf-again' ).addEventListener( 'click', function () { plan = null; fileText = ''; q( '#vba-xf-result' ).innerHTML = ''; step( 'pick' ); } );
	q( '#vba-xf-go' ).addEventListener( 'click', function () {
		var docs = {};
		qa( '[data-doc]', im ).forEach( function ( s ) { docs[ s.getAttribute( 'data-doc' ) ] = s.value; } );
		var ch = { docs:docs, classes:val( 'vba-xf-classes' ) || 'skip', tokens:val( 'vba-xf-tokens' ) || 'skip', styles:!! val( 'vba-xf-styles' ), fonts:!! val( 'vba-xf-fonts' ), css:!! val( 'vba-xf-css' ), js:!! val( 'vba-xf-js' ), images:!! val( 'vba-xf-images' ), links:!! val( 'vba-xf-links' ) };
		var go = this; go.disabled = true;
		step( 'done' ); q( '#vba-xf-progress' ).textContent = T.importing;
		api( 'builder_import_run', { file:fileText, choices:JSON.stringify( ch ) } ).then( function ( r ) {
			showResult( r );
			if ( r.token ) { return copyImages( r.token, r.images ); }
			q( '#vba-xf-progress' ).textContent = T.done;
		} ).catch( function ( e ) { q( '#vba-xf-progress' ).textContent = e.message; } ).then( function () { go.disabled = false; } );
	} );
	function copyImages( token, total ) {
		q( '#vba-xf-progress' ).textContent = fmt( T.copying, 0, total );
		return api( 'builder_import_images', { token:token } ).then( function ( r ) {
			if ( ! r.finished ) { q( '#vba-xf-progress' ).textContent = fmt( T.copying, r.done, r.total ); return copyImages( token, total ); }
			q( '#vba-xf-progress' ).textContent = T.done + ' ' + fmt( T.copied, r.copied ) + ( r.failed ? ' ' + fmt( T.notCopied, r.failed ) : '' );
		} );
	}
	function showResult( r ) {
		var h = '';
		if ( r.docs.length ) {
			h += '<div class="vba-xf-rows">';
			r.docs.forEach( function ( d ) {
				h += '<div class="vba-xf-row"><span class="vba-xf-kind">' + esc( KIND[ d.kind ] ) + '</span><span class="vba-xf-t">' + esc( d.title ) + '<small>' + esc( 'replace' === d.how ? T.replaced : T.added ) + '</small></span><a class="vba-mini" href="' + esc( d.edit ) + '">' + esc( T.open ) + '</a></div>';
			} );
			h += '</div><p class="vba-xf-note">' + esc( T.publish ) + '</p>';
		}
		if ( r.skipped ) { h += '<p class="vba-xf-note">' + esc( fmt( T.skipped, r.skipped ) ) + '</p>'; }
		if ( r.design && Object.keys( r.design ).some( function ( k ) { return +r.design[ k ] > 0; } ) ) { h += '<p class="vba-xf-note">' + esc( T.designIn ) + '</p>'; }
		if ( ! h ) { h = '<p class="vba-xf-note">' + esc( T.nothing ) + '</p>'; }
		q( '#vba-xf-result' ).innerHTML = h;
	}
}() );
</script>
