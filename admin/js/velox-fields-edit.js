/**
 * Velox Fields — post-edit screen behaviour: image/file media pickers + gallery.
 * Runs on post.php / post-new.php where jQuery and wp.media are present.
 */
( function ( $ ) {
	'use strict';
	if ( typeof window.wp === 'undefined' || ! window.wp.media ) { return; }

	/* ---- single image / file picker ---- */
	$( document ).on( 'click', '.velox-fld-media-pick', function () {
		var btn    = $( this );
		var wrap   = btn.closest( '.velox-fld-media' );
		var input  = wrap.find( '.velox-fld-media-input' );
		var isFile = btn.attr( 'data-file' ) === '1';
		var frame  = wp.media( {
			title: btn.attr( 'data-title' ) || 'Select',
			multiple: false,
			library: isFile ? {} : { type: 'image' },
			button: { text: 'Use this' }
		} );
		frame.on( 'select', function () {
			var a = frame.state().get( 'selection' ).first().toJSON();
			input.val( a.id );
			if ( isFile ) {
				wrap.find( '.velox-fld-media-file' ).text( a.filename || a.url ).show();
			} else {
				var url = ( a.sizes && a.sizes.medium ) ? a.sizes.medium.url : a.url;
				wrap.find( '.velox-fld-media-preview' ).html( '<img class="velox-fld-media-img" src="' + url + '">' );
			}
			wrap.find( '.velox-fld-media-clear' ).show();
		} );
		frame.open();
	} );

	$( document ).on( 'click', '.velox-fld-media-clear', function () {
		var wrap = $( this ).closest( '.velox-fld-media' );
		wrap.find( '.velox-fld-media-input' ).val( '' );
		wrap.find( '.velox-fld-media-preview' ).empty();
		wrap.find( '.velox-fld-media-file' ).hide().text( '' );
		$( this ).hide();
	} );

	/* ---- gallery (multiple) ---- */
	$( document ).on( 'click', '.velox-fld-gallery-add', function () {
		var wrap  = $( this ).closest( '.velox-fld-gallery' );
		var input = wrap.find( '.velox-fld-gallery-input' );
		var list  = wrap.find( '.velox-fld-gallery-list' );
		var frame = wp.media( { title: 'Add images', multiple: true, library: { type: 'image' }, button: { text: 'Add to gallery' } } );
		frame.on( 'select', function () {
			var ids = input.val() ? input.val().split( ',' ).filter( Boolean ) : [];
			frame.state().get( 'selection' ).each( function ( m ) {
				var a  = m.toJSON();
				var id = String( a.id );
				if ( ids.indexOf( id ) === -1 ) {
					ids.push( id );
					var url = ( a.sizes && a.sizes.thumbnail ) ? a.sizes.thumbnail.url : a.url;
					list.append( '<li class="velox-fld-gallery-item" data-id="' + id + '"><img src="' + url + '"><button type="button" class="velox-fld-gallery-rm" aria-label="Remove">&times;</button></li>' );
				}
			} );
			input.val( ids.join( ',' ) );
		} );
		frame.open();
	} );

	$( document ).on( 'click', '.velox-fld-gallery-rm', function () {
		var li    = $( this ).closest( '.velox-fld-gallery-item' );
		var wrap  = li.closest( '.velox-fld-gallery' );
		var input = wrap.find( '.velox-fld-gallery-input' );
		var id    = String( li.attr( 'data-id' ) );
		var ids   = input.val() ? input.val().split( ',' ).filter( Boolean ) : [];
		ids = ids.filter( function ( x ) { return x !== id; } );
		input.val( ids.join( ',' ) );
		li.remove();
	} );

	/* ---- repeater rows ---- */
	$( document ).on( 'click', '.velox-rep-add', function () {
		var rep = $( this ).closest( '.velox-rep' );
		var tpl = rep.find( '.velox-rep-tpl' ).html();
		if ( ! tpl ) { return; }
		var idx = 'r' + Date.now().toString( 36 ) + Math.floor( Math.random() * 1000 );
		rep.find( '.velox-rep-rows' ).append( tpl.replace( /__i__/g, idx ) );
	} );
	$( document ).on( 'click', '.velox-rep-rm', function () {
		$( this ).closest( '.velox-rep-row' ).remove();
	} );

	/* drag to reorder rows */
	var dragRow = null;
	$( document ).on( 'mousedown', '.velox-rep-handle', function () {
		$( this ).closest( '.velox-rep-row' ).attr( 'draggable', 'true' );
	} );
	$( document ).on( 'dragstart', '.velox-rep-row', function ( e ) {
		dragRow = this; $( this ).addClass( 'is-drag' );
		if ( e.originalEvent && e.originalEvent.dataTransfer ) { e.originalEvent.dataTransfer.effectAllowed = 'move'; }
	} );
	$( document ).on( 'dragend', '.velox-rep-row', function () {
		$( this ).removeClass( 'is-drag' ).removeAttr( 'draggable' ); dragRow = null;
	} );
	$( document ).on( 'dragover', '.velox-rep-row', function ( e ) {
		e.preventDefault();
		if ( ! dragRow || dragRow === this ) { return; }
		var rows = $( this ).closest( '.velox-rep-rows, .velox-flex-rows' );
		if ( rows[0] !== $( dragRow ).closest( '.velox-rep-rows, .velox-flex-rows' )[0] ) { return; }
		var box = this.getBoundingClientRect();
		var after = ( e.originalEvent.clientY - box.top ) > ( box.height / 2 );
		this.parentNode.insertBefore( dragRow, after ? this.nextSibling : this );
	} );

	/* ---- flexible content (rows share .velox-rep-row classes for remove/reorder) ---- */
	$( document ).on( 'click', '.velox-flex-toggle', function ( e ) {
		e.stopPropagation();
		var menu = $( this ).siblings( '.velox-flex-menu' );
		menu.prop( 'hidden', ! menu.prop( 'hidden' ) );
	} );
	$( document ).on( 'click', '.velox-flex-pick', function () {
		var flex  = $( this ).closest( '.velox-flex' );
		var lname = $( this ).attr( 'data-layout' );
		var tpl   = flex.find( '.velox-flex-tpl[data-layout="' + lname + '"]' ).html();
		if ( ! tpl ) { return; }
		var idx = 'r' + Date.now().toString( 36 ) + Math.floor( Math.random() * 1000 );
		flex.find( '.velox-flex-rows' ).append( tpl.replace( /__i__/g, idx ) );
		$( this ).closest( '.velox-flex-menu' ).prop( 'hidden', true );
	} );
	$( document ).on( 'click', function ( e ) {
		if ( ! $( e.target ).closest( '.velox-flex-add' ).length ) { $( '.velox-flex-menu' ).prop( 'hidden', true ); }
	} );
} )( jQuery );

/* ---- conditional logic (show/hide top-level fields by another field's value) ---- */
( function ( $ ) {
	'use strict';
	function fieldVal( scope, field ) {
		var base = 'velox_field[' + field + ']';
		var single = $( scope ).find( '[name="' + base + '"]' );
		if ( single.length ) {
			if ( single.length > 1 ) { var r = single.filter( ':checked' ); return r.length ? r.val() : ''; }
			var el = single.get( 0 );
			if ( el.type === 'checkbox' ) { return el.checked ? ( el.value || '1' ) : ''; }
			return single.val();
		}
		var multi = $( scope ).find( '[name="' + base + '[]"]' );
		if ( multi.length ) { return multi.filter( ':checked' ).map( function () { return this.value; } ).get(); }
		return '';
	}
	function ruleHit( val, op, target ) {
		var empty = ( val == null ) || ( Array.isArray( val ) ? val.length === 0 : String( val ) === '' );
		if ( op === 'empty' ) { return empty; }
		if ( op === '!empty' ) { return ! empty; }
		var has = Array.isArray( val ) ? val.indexOf( String( target ) ) !== -1 : String( val ) === String( target );
		return op === '!=' ? ! has : has;
	}
	function groupsHit( groups, scope ) {
		if ( ! groups || ! groups.length ) { return true; }
		return groups.some( function ( g ) {
			return g.every( function ( rule ) { return ruleHit( fieldVal( scope, rule.field ), rule.operator, rule.value ); } );
		} );
	}
	function apply( scope ) {
		$( scope ).find( '[data-vfx-cond]' ).each( function () {
			var cond; try { cond = JSON.parse( this.getAttribute( 'data-vfx-cond' ) ); } catch ( e ) { return; }
			this.style.display = groupsHit( cond.groups, scope ) ? '' : 'none';
		} );
	}
	function run() { $( '.velox-fields-meta' ).each( function () { apply( this ); } ); }
	$( document ).on( 'change keyup', '.velox-fields-meta input, .velox-fields-meta select, .velox-fields-meta textarea', function () {
		var scope = $( this ).closest( '.velox-fields-meta' ).get( 0 );
		if ( scope ) { apply( scope ); }
	} );
	$( run );
} )( jQuery );

/* ---- range slider live value + button-group active state ---- */
( function ( $ ) {
	'use strict';
	$( document ).on( 'input', '.velox-fld-range input[type=range]', function () {
		$( this ).siblings( '.velox-fld-range-val' ).text( this.value );
	} );
	$( document ).on( 'change', '.velox-fld-btngroup input[type=radio]', function () {
		$( this ).closest( '.velox-fld-btngroup' ).find( '.velox-fld-btn' ).removeClass( 'is-on' );
		$( this ).closest( '.velox-fld-btn' ).addClass( 'is-on' );
	} );
} )( jQuery );
