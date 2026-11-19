<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Velox Builder — Keyboard shortcuts: every editor shortcut, changeable. Says
 * what a key normally does in the browser, refuses keys the browser keeps for
 * itself, and flags two actions on the same keys with a link to the other one.
 */
$vk_defs  = Velox_Builder::shortcut_defs();
$vk_user  = Velox_Builder::shortcut_user();
$vk_fixed = Velox_Builder::shortcut_fixed();
$vk_brow  = Velox_Builder::browser_keys();
?>
<div class="vba-shell vba-keys">
	<header class="vba-head">
		<div><h1><?php esc_html_e( 'Keyboard shortcuts', 'velox' ); ?></h1>
		<p><?php esc_html_e( 'The keys the editor listens to. An action can have several: add keys, or remove one with its ×.', 'velox' ); ?></p></div>
		<div class="vba-head-actions">
			<button type="button" class="vba-btn vba-btn-ghost" id="vk-reset-all"><?php esc_html_e( 'Reset all', 'velox' ); ?></button>
			<button type="button" class="vba-btn vba-btn-primary" id="vk-save"><?php esc_html_e( 'Save changes', 'velox' ); ?></button>
		</div>
	</header>
	<div class="vk-bar">
		<label class="vk-find"><input type="search" id="vk-find" placeholder="<?php esc_attr_e( 'Find a shortcut or a key, e.g. "save" or "Ctrl+D"', 'velox' ); ?>"></label>
		<span class="vk-status" id="vk-status" aria-live="polite"></span>
	</div>
	<div id="vk-list"></div>
	<div class="vba-card">
		<div class="vba-card-h"><div><h2><?php esc_html_e( 'Always the same', 'velox' ); ?></h2>
		<p><?php esc_html_e( 'These keys work the same everywhere in the editor and can’t be changed. A shortcut set to one of them is flagged above.', 'velox' ); ?></p></div></div>
		<?php foreach ( $vk_fixed as $f ) : ?>
			<div class="vba-setting vk-row"><div class="vba-setting-tx"><b><?php echo esc_html( $f['l'] ); ?></b></div><span class="vk-caps"><kbd><?php echo esc_html( $f['k'] ); ?></kbd></span></div>
		<?php endforeach; ?>
	</div>
</div>
<style>
.vba-keys .vk-bar{display:flex;align-items:center;gap:14px;margin:0 0 16px}
.vba-keys .vk-find{flex:1;max-width:460px}
.vba-keys .vk-find input{width:100%;background:var(--vbb-void);border:1px solid var(--vbb-line-2);border-radius:9px;padding:10px 12px;color:var(--vbb-text);font:inherit;font-size:13px;outline:none}
.vba-keys .vk-find input:focus{border-color:var(--vbb-accent)}
.vba-keys .vk-status{font-size:12.5px;color:var(--vbb-text-2)}
.vba-keys .vk-status.is-err{color:var(--vbb-red)}
.vba-keys .vk-row{scroll-margin-top:90px;transition:background .3s}
.vba-keys .vk-row.is-flash{background:var(--vbb-accent-soft)}
.vba-keys .vk-row.is-hidden{display:none}
.vba-keys .vk-row .vba-setting-tx{display:flex;flex-direction:column;gap:5px;min-width:0}
.vba-keys .vk-note{font-size:12px;line-height:1.45;color:var(--vbb-text-2)}
.vba-keys .vk-note.is-warn{color:var(--vbb-orange)}
.vba-keys .vk-note.is-err{color:var(--vbb-red)}
.vba-keys .vk-note a{color:inherit;text-decoration:underline;cursor:pointer}
.vba-keys .vk-ctrl{display:flex;align-items:center;gap:8px;flex:0 0 auto}
.vba-keys .vk-caps{display:inline-flex;gap:6px;flex-wrap:wrap;justify-content:flex-end}
.vba-keys kbd{display:inline-flex;align-items:center;min-height:26px;padding:3px 9px;border-radius:7px;border:1px solid var(--vbb-line-2);border-bottom-width:2px;background:var(--vbb-raised);color:var(--vbb-text);font:500 12px/1 var(--vbb-mono);white-space:nowrap}
.vba-keys kbd.is-off{color:var(--vbb-text-3)}
.vba-keys .vk-rec{min-width:150px;justify-content:center}
.vba-keys .vk-rec[disabled]{opacity:.45;cursor:default}
.vba-keys .vk-chip{display:inline-flex;align-items:center}
.vba-keys .vk-chip kbd{border-top-right-radius:0;border-bottom-right-radius:0;border-right:0}
.vba-keys .vk-x{display:inline-grid;place-items:center;min-height:26px;width:24px;padding:0;border:1px solid var(--vbb-line-2);border-bottom-width:2px;border-radius:0 7px 7px 0;background:var(--vbb-raised);color:var(--vbb-text-3);font:600 14px/1 var(--vbb-font);cursor:pointer}
.vba-keys .vk-x:hover{background:rgba(245,106,92,.16);color:var(--vbb-red);border-color:rgba(245,106,92,.4)}
.vba-keys .vk-rec.is-rec{border-color:var(--vbb-accent);color:var(--vbb-accent);box-shadow:0 0 0 3px var(--vbb-accent-soft)}
.vba-keys .vk-mini{background:none;border:0;padding:6px;border-radius:6px;color:var(--vbb-text-3);cursor:pointer;font:inherit;font-size:12px}
.vba-keys .vk-mini:hover{background:var(--vbb-raised-2);color:var(--vbb-text)}
.vba-keys .vk-dot{display:inline-block;width:6px;height:6px;border-radius:50%;background:var(--vbb-accent);margin-left:6px;vertical-align:middle}
@media (max-width:782px){.vba-keys .vk-row{flex-direction:column;align-items:flex-start}.vba-keys .vk-ctrl{width:100%;justify-content:space-between}}
</style>
<script>
( function () {
	var DEFS = <?php echo wp_json_encode( $vk_defs ); ?>;
	var USER = <?php echo wp_json_encode( (object) $vk_user ); ?>;
	var FIXED = <?php echo wp_json_encode( $vk_fixed ); ?>;
	var BROWSER = <?php echo wp_json_encode( $vk_brow ); ?>;
	var T = <?php echo wp_json_encode( array(
		'add'      => __( 'Set keys', 'velox' ),
		'addMore'  => __( '+ Add keys', 'velox' ),
		'removeOne'=> __( 'Remove', 'velox' ),
		'press'    => __( 'Press the keys… (Esc cancels)', 'velox' ),
		'none'     => __( 'No shortcut', 'velox' ),
		'default'  => __( 'Default', 'velox' ),
		'changed'  => __( 'Changed from the default', 'velox' ),
		'usually'  => __( 'In the browser this usually %s. In the editor Velox uses it instead.', 'velox' ),
		'blocked'  => __( 'Your browser keeps this one for itself (it %s), so the editor never gets it. Choose other keys.', 'velox' ),
		'clash'    => __( 'Clashes with', 'velox' ),
		'clashFix' => __( 'Also used by: %s (can’t be changed)', 'velox' ),
		'needMod'  => __( 'A key without Ctrl, ⌘ or Alt only works while you are not typing.', 'velox' ),
		'saved'    => __( 'Saved. Reload the editor to use the new keys.', 'velox' ),
		'unsaved'  => __( 'Unsaved changes', 'velox' ),
		'fixFirst' => __( 'Fix the shortcuts marked in red first.', 'velox' ),
		'resetAsk' => __( 'Put every shortcut back to its default?', 'velox' ),
		'failed'   => __( 'Something went wrong. Try again.', 'velox' ),
		'nothing'  => __( 'No shortcut matches.', 'velox' ),
		'ctrl'     => __( 'Ctrl', 'velox' ),
	) ); ?>;
	var IS_MAC = /Mac|iPhone|iPad/.test( navigator.platform || navigator.userAgent );
	var user = JSON.parse( JSON.stringify( USER || {} ) ), dirty = false, recFor = null;
	function cfg() { return window.VELOX_BUILDER || {}; }
	function esc( s ) { var d = document.createElement( 'div' ); d.textContent = String( s == null ? '' : s ); return d.innerHTML; }
	function fmt( s, a ) { return String( s ).replace( '%s', a ); }
	function combos( id ) {
		if ( Object.prototype.hasOwnProperty.call( user, id ) ) { return [].concat( user[ id ] || [] ).filter( Boolean ); }
		var d = DEFS.filter( function ( x ) { return x.id === id; } )[ 0 ];
		return d ? d.keys : [];
	}
	function isDefault( id ) { return ! Object.prototype.hasOwnProperty.call( user, id ); }
	var NAMES = { ArrowUp:'↑', ArrowDown:'↓', ArrowLeft:'←', ArrowRight:'→', Delete:'Del', Escape:'Esc', Backspace:'⌫', Enter:'↵' };
	function label( c ) {
		var parts = String( c ).split( '+' ), key = parts.pop();
		key = NAMES[ key ] || key;
		if ( IS_MAC ) { return parts.map( function ( m ) { return { Mod:'⌘', Alt:'⌥', Shift:'⇧' }[ m ] || m; } ).join( '' ) + key; }
		return parts.map( function ( m ) { return 'Mod' === m ? T.ctrl : m; } ).concat( [ key ] ).join( '+' );
	}
	function comboOf( e ) {
		var k = e.key || '', c = e.code || '', key;
		if ( /^(Control|Shift|Alt|Meta|OS|AltGraph|CapsLock)$/.test( k ) ) { return ''; }
		if ( /^Key[A-Z]$/.test( c ) ) { key = c.slice( 3 ); }
		else if ( /^Digit\d$/.test( c ) ) { key = c.slice( 5 ); }
		else if ( 'Backslash' === c || 'IntlBackslash' === c ) { key = '\\'; }
		else if ( ' ' === k ) { key = 'Space'; }
		else if ( 1 === k.length ) { key = k.toUpperCase(); }
		else { key = k; }
		return ( e.ctrlKey || e.metaKey ? 'Mod+' : '' ) + ( e.altKey ? 'Alt+' : '' ) + ( e.shiftKey ? 'Shift+' : '' ) + key;
	}
	// Other actions (and fixed keys) that use one of these keys.
	function clashes( id ) {
		var mine = combos( id ), out = [], fixed = [];
		DEFS.forEach( function ( d ) {
			if ( d.id === id ) { return; }
			if ( combos( d.id ).some( function ( c ) { return mine.indexOf( c ) > -1; } ) ) { out.push( d ); }
		} );
		FIXED.forEach( function ( f ) { if ( ( f.c || [] ).some( function ( c ) { return mine.indexOf( c ) > -1; } ) ) { fixed.push( f.l ); } } );
		return { acts:out, fixed:fixed };
	}
	function blocked( id ) { return combos( id ).filter( function ( c ) { return BROWSER[ c ] && BROWSER[ c ][ 1 ]; } ); }
	function notes( id ) {
		var n = [], ks = combos( id );
		blocked( id ).forEach( function ( c ) { n.push( '<span class="vk-note is-err">' + esc( label( c ) ) + ' — ' + esc( fmt( T.blocked, BROWSER[ c ][ 0 ] ) ) + '</span>' ); } );
		var cl = clashes( id );
		if ( cl.acts.length ) {
			n.push( '<span class="vk-note is-err">' + esc( T.clash ) + ' ' + cl.acts.map( function ( d ) { return '<a data-vk-go="' + esc( d.id ) + '">' + esc( d.l ) + '</a>'; } ).join( ', ' ) + '</span>' );
		}
		if ( cl.fixed.length ) { n.push( '<span class="vk-note is-warn">' + esc( fmt( T.clashFix, cl.fixed.join( ', ' ) ) ) + '</span>' ); }
		ks.forEach( function ( c ) {
			if ( BROWSER[ c ] && ! BROWSER[ c ][ 1 ] ) { n.push( '<span class="vk-note">' + esc( label( c ) ) + ': ' + esc( fmt( T.usually, BROWSER[ c ][ 0 ] ) ) + '</span>' ); }
			if ( ! /^(Mod|Alt)\+/.test( c ) && ! /^F\d/.test( c ) && 'Delete' !== c ) { n.push( '<span class="vk-note">' + esc( T.needMod ) + '</span>' ); }
		} );
		return n.join( '' );
	}
	function rowHTML( d ) {
		var ks = combos( d.id ), rec = recFor === d.id;
		// Each key combination is its own chip with its own ×.
		var caps = ks.length ? ks.map( function ( c ) {
			return '<span class="vk-chip"><kbd>' + esc( label( c ) ) + '</kbd><button type="button" class="vk-x" data-vk-del="' + esc( d.id ) + '" data-vk-combo="' + esc( c ) + '" title="' + esc( T.removeOne ) + ' ' + esc( label( c ) ) + '" aria-label="' + esc( T.removeOne ) + ' ' + esc( label( c ) ) + '">×</button></span>';
		} ).join( '' ) : '<kbd class="is-off">' + esc( T.none ) + '</kbd>';
		return '<div class="vba-setting vk-row" id="key-' + esc( d.id ) + '" data-vk-row="' + esc( d.id ) + '" data-vk-find="' + esc( ( d.l + ' ' + d.g + ' ' + ks.map( label ).join( ' ' ) ).toLowerCase() ) + '">' +
			'<div class="vba-setting-tx"><b>' + esc( d.l ) + ( isDefault( d.id ) ? '' : '<span class="vk-dot" title="' + esc( T.changed ) + '"></span>' ) + '</b>' + notes( d.id ) + '</div>' +
			'<div class="vk-ctrl"><span class="vk-caps">' + caps + '</span>' +
				'<button type="button" class="vba-btn vba-btn-ghost vba-btn-sm vk-rec' + ( rec ? ' is-rec' : '' ) + '" data-vk-rec="' + esc( d.id ) + '"' + ( ks.length >= 4 && ! rec ? ' disabled' : '' ) + '>' + esc( rec ? T.press : ( ks.length ? T.addMore : T.add ) ) + '</button>' +
				( isDefault( d.id ) ? '' : '<button type="button" class="vk-mini" data-vk-def="' + esc( d.id ) + '">' + esc( T.default ) + '</button>' ) +
			'</div></div>';
	}
	var list = document.getElementById( 'vk-list' ), status = document.getElementById( 'vk-status' ), find = document.getElementById( 'vk-find' );
	function render() {
		var groups = [], by = {};
		DEFS.forEach( function ( d ) { if ( ! by[ d.g ] ) { by[ d.g ] = []; groups.push( d.g ); } by[ d.g ].push( d ); } );
		list.innerHTML = groups.map( function ( g ) {
			return '<div class="vba-card"><div class="vba-card-h"><div><h2>' + esc( g ) + '</h2></div></div>' + by[ g ].map( rowHTML ).join( '' ) + '</div>';
		} ).join( '' );
		filter();
		var bad = DEFS.some( function ( d ) { return blocked( d.id ).length || clashes( d.id ).acts.length; } );
		status.className = 'vk-status' + ( bad ? ' is-err' : '' );
		status.textContent = bad ? T.fixFirst : ( dirty ? T.unsaved : '' );
	}
	function filter() {
		var q = ( find.value || '' ).trim().toLowerCase(), shown = 0;
		[].forEach.call( list.querySelectorAll( '.vk-row' ), function ( r ) {
			var hit = ! q || r.getAttribute( 'data-vk-find' ).indexOf( q ) > -1;
			r.classList.toggle( 'is-hidden', ! hit ); if ( hit ) { shown++; }
		} );
		[].forEach.call( list.querySelectorAll( '.vba-card' ), function ( c ) { c.style.display = c.querySelector( '.vk-row:not(.is-hidden)' ) ? '' : 'none'; } );
	}
	function set( id, list ) {
		var d = DEFS.filter( function ( x ) { return x.id === id; } )[ 0 ];
		if ( d && JSON.stringify( list ) === JSON.stringify( d.keys ) ) { delete user[ id ]; } else { user[ id ] = list; }
		dirty = true; render();
	}
	list.addEventListener( 'click', function ( e ) {
		var r = e.target.closest( '[data-vk-rec]' );
		if ( r ) { recFor = recFor === r.getAttribute( 'data-vk-rec' ) ? null : r.getAttribute( 'data-vk-rec' ); render(); var b = list.querySelector( '.vk-rec.is-rec' ); if ( b ) { b.focus(); } return; }
		var x = e.target.closest( '[data-vk-del]' );
		if ( x ) {
			var xid = x.getAttribute( 'data-vk-del' ), xc = x.getAttribute( 'data-vk-combo' );
			set( xid, combos( xid ).filter( function ( c ) { return c !== xc; } ) );
			return;
		}
		var df = e.target.closest( '[data-vk-def]' ); if ( df ) { delete user[ df.getAttribute( 'data-vk-def' ) ]; dirty = true; render(); return; }
		var go = e.target.closest( '[data-vk-go]' );
		if ( go ) {
			find.value = ''; filter();
			var row = document.getElementById( 'key-' + go.getAttribute( 'data-vk-go' ) );
			if ( row ) { row.scrollIntoView( { behavior:'smooth', block:'center' } ); row.classList.add( 'is-flash' ); setTimeout( function () { row.classList.remove( 'is-flash' ); }, 1600 ); }
		}
	} );
	// Recording: the next key combination becomes the shortcut. Escape cancels.
	document.addEventListener( 'keydown', function ( e ) {
		if ( ! recFor ) { return; }
		e.preventDefault(); e.stopPropagation();
		if ( 'Escape' === e.key ) { recFor = null; render(); return; }
		var c = comboOf( e ); if ( ! c ) { return; }
		var id = recFor; recFor = null;
		// The new keys are added to the ones the action already has.
		var now = combos( id );
		if ( now.indexOf( c ) > -1 ) { render(); return; }
		set( id, now.concat( [ c ] ) );
	}, true );
	find.addEventListener( 'input', filter );
	function api( action, fields ) {
		var C = cfg(), body = new URLSearchParams();
		body.set( 'action', 'velox' ); body.set( 'do', action ); body.set( 'nonce', C.nonce || '' );
		Object.keys( fields || {} ).forEach( function ( k ) { body.set( k, fields[ k ] ); } );
		return fetch( C.ajaxurl || window.ajaxurl, { method:'POST', headers:{ 'Content-Type':'application/x-www-form-urlencoded' }, body:body.toString() } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( res ) { if ( ! res || ! res.success ) { throw new Error( ( res && res.data && res.data.message ) || T.failed ); } return res.data; } );
	}
	function save() {
		if ( DEFS.some( function ( d ) { return blocked( d.id ).length || clashes( d.id ).acts.length; } ) ) { status.className = 'vk-status is-err'; status.textContent = T.fixFirst; return; }
		var btn = document.getElementById( 'vk-save' ); btn.disabled = true;
		api( 'builder_keys_save', { keys:JSON.stringify( user ) } ).then( function ( d ) {
			user = d.keys && ! Array.isArray( d.keys ) ? d.keys : {}; dirty = false; render();
			status.className = 'vk-status'; status.textContent = T.saved;
		} ).catch( function ( err ) { status.className = 'vk-status is-err'; status.textContent = err.message; } )
			.then( function () { btn.disabled = false; } );
	}
	document.getElementById( 'vk-save' ).addEventListener( 'click', save );
	document.getElementById( 'vk-reset-all' ).addEventListener( 'click', function () {
		var go = function () { user = {}; dirty = true; render(); save(); };
		if ( window.vbaDialog ) { vbaDialog.confirm( { title:<?php echo wp_json_encode( __( 'Reset all', 'velox' ) ); ?>, text:T.resetAsk, ok:<?php echo wp_json_encode( __( 'Reset all', 'velox' ) ); ?> }, go ); } else if ( window.confirm( T.resetAsk ) ) { go(); }
	} );
	window.addEventListener( 'beforeunload', function ( e ) { if ( dirty ) { e.preventDefault(); e.returnValue = ''; } } );
	// Coming from the editor's "Change shortcuts" link with #key-…: show that row.
	render();
	if ( location.hash && 0 === location.hash.indexOf( '#key-' ) ) { var hr = document.getElementById( location.hash.slice( 1 ) ); if ( hr ) { hr.scrollIntoView( { block:'center' } ); hr.classList.add( 'is-flash' ); setTimeout( function () { hr.classList.remove( 'is-flash' ); }, 1600 ); } }
}() );
</script>
