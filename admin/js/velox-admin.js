/**
 * Velox admin JS
 * One small jQuery-free core. Talks to the single `velox` ajax action.
 * Every tab boots itself only if its root element is on the page.
 */
( function () {
	'use strict';

	if ( typeof VELOX === 'undefined' ) {
		return;
	}

	/* ----------------------------------------------------------------
	 * Core: ajax + helpers
	 * ------------------------------------------------------------- */

	function api( doAction, data ) {
		var body = new FormData();
		body.append( 'action', 'velox' );
		body.append( 'nonce', VELOX.nonce );
		body.append( 'do', doAction );
		if ( data ) {
			Object.keys( data ).forEach( function ( k ) {
				body.append( k, data[ k ] );
			} );
		}
		return fetch( VELOX.ajaxurl, {
			method: 'POST',
			credentials: 'same-origin',
			body: body,
		} )
			.then( function ( r ) {
				return r.json();
			} )
			.then( function ( json ) {
				if ( ! json || ! json.success ) {
					var msg = json && json.data && json.data.message
						? json.data.message
						: 'Something went wrong.';
					throw new Error( msg );
				}
				return json.data;
			} );
	}

	var $ = function ( sel, root ) {
		return ( root || document ).querySelector( sel );
	};
	var $$ = function ( sel, root ) {
		return Array.prototype.slice.call( ( root || document ).querySelectorAll( sel ) );
	};

	function bytes( n ) {
		n = parseInt( n, 10 ) || 0;
		if ( n < 1024 ) {
			return n + ' B';
		}
		var units = [ 'KB', 'MB', 'GB' ];
		var i = -1;
		do {
			n /= 1024;
			i++;
		} while ( n >= 1024 && i < units.length - 1 );
		return n.toFixed( n < 10 ? 1 : 0 ) + ' ' + units[ i ];
	}

	function escapeHtml( s ) {
		return String( s == null ? '' : s )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' );
	}

	function debounce( fn, wait ) {
		var t;
		return function () {
			var args = arguments,
				ctx = this;
			clearTimeout( t );
			t = setTimeout( function () {
				fn.apply( ctx, args );
			}, wait );
		};
	}

	/* Toast ------------------------------------------------------- */

	var toastTimer;
	function toast( message, type ) {
		var el = $( '#velox-toast' );
		if ( ! el ) {
			return;
		}
		var t = type || 'success';
		var icons = { success: '\u2713', error: '\u2715', warn: '!', info: '\u2139' };
		el.innerHTML = '<span class="velox-toast-ic"></span><span class="velox-toast-msg"></span>';
		el.querySelector( '.velox-toast-ic' ).textContent = icons[ t ] || icons.success;
		el.querySelector( '.velox-toast-msg' ).textContent = message;
		el.className = 'velox-toast is-visible velox-toast--' + t;
		clearTimeout( toastTimer );
		toastTimer = setTimeout( function () {
			el.className = 'velox-toast';
		}, 3200 );
	}

	/* Settings collection (shared by performance + settings tabs) - */

	function collectSettings( root ) {
		var out = {};
		var segDone = {};
		$$( '[data-setting]', root ).forEach( function ( el ) {
			var key = el.getAttribute( 'data-setting' );
			// Segmented control: a group of buttons sharing one data-setting; the
			// active one's data-value is the value. Record once per key.
			if ( el.classList && el.classList.contains( 'vxck-seg-btn' ) ) {
				if ( segDone[ key ] ) { return; }
				var active = root.querySelector( '.vxck-seg-btn.is-active[data-setting="' + key + '"]' );
				if ( active ) { out[ key ] = active.getAttribute( 'data-value' ); }
				segDone[ key ] = true;
				return;
			}
			if ( el.type === 'checkbox' ) {
				out[ key ] = el.checked ? 1 : 0;
			} else {
				out[ key ] = el.value;
			}
		} );
		return out;
	}

	function saveSettings( payload, okMsg ) {
		return api( 'save_settings', payload )
			.then( function () {
				toast( okMsg || 'Saved.', 'success' );
			} )
			.catch( function ( e ) {
				toast( e.message, 'error' );
			} );
	}

	// One-time confirmation before enabling a dangerous tool (e.g. File Manager).
	function veloxDangerModal( tool, onProceed ) {
		var overlay = document.createElement( 'div' );
		overlay.className = 'velox-danger-overlay';
		overlay.innerHTML =
			'<div class="velox-danger-modal">' +
				'<div class="velox-danger-ic" aria-hidden="true">&#9888;</div>' +
				'<h3 class="velox-danger-title">Turn on the File Manager?</h3>' +
				'<p class="velox-danger-lead">This lets you open and edit any file on your site directly from here. It\u2019s great for debugging \u2014 but there are real risks:</p>' +
				'<ul class="velox-danger-list">' +
					'<li>Editing the wrong file (like <code>wp-config.php</code> or a theme\u2019s <code>functions.php</code>) can take the whole site down \u2014 including this dashboard.</li>' +
					'<li>There\u2019s no undo. Saving overwrites the file on the server immediately.</li>' +
					'<li>Only touch files you understand. When in doubt, make a backup first (Utilities \u2192 Backup &amp; restore).</li>' +
				'</ul>' +
				'<div class="velox-danger-acts">' +
					'<button type="button" class="velox-btn velox-btn--ghost" data-danger="cancel">Cancel</button>' +
					'<button type="button" class="velox-btn velox-danger-go" data-danger="go">I understand \u2014 enable it</button>' +
				'</div>' +
			'</div>';
		document.body.appendChild( overlay );
		function close() { overlay.remove(); }
		overlay.addEventListener( 'click', function ( e ) {
			var act = e.target.getAttribute ? e.target.getAttribute( 'data-danger' ) : null;
			if ( e.target === overlay || 'cancel' === act ) { close(); }
			else if ( 'go' === act ) { close(); onProceed(); }
		} );
	}

	// Subtle corner pill used by auto-save so we don't spam a toast on every toggle.
	function flashSaved() {
		var el = document.getElementById( 'velox-autosave-flag' );
		if ( ! el ) {
			el = document.createElement( 'div' );
			el.id = 'velox-autosave-flag';
			el.className = 'velox-autosave-flag';
			el.innerHTML = '<span class="velox-autosave-dot"></span>Saved';
			document.body.appendChild( el );
		}
		el.classList.add( 'is-show' );
		clearTimeout( el._t );
		el._t = setTimeout( function () { el.classList.remove( 'is-show' ); }, 1300 );
	}

	/* ----------------------------------------------------------------
	 * Dashboard
	 * ------------------------------------------------------------- */

	function initDashboard() {
		var fields = $$( '[data-dash]' );
		if ( ! fields.length ) {
			return;
		}
		api( 'image_stats' )
			.then( function ( s ) {
				var map = { done: s.done, total: s.total, pending: s.pending, saved: bytes( s.saved_bytes ) };
				fields.forEach( function ( el ) {
					var k = el.getAttribute( 'data-dash' );
					if ( map[ k ] !== undefined ) { el.textContent = map[ k ]; }
				} );
			} )
			.catch( function () {} );
	}

	/* ----------------------------------------------------------------
	 * Images: bulk convert + library ring + comparator
	 * ------------------------------------------------------------- */

	function initImages() {
		var root = $( '#velox-quality' );
		var grid = $( '#velox-img-stats' );
		if ( ! root && ! grid ) {
			return;
		}

		/* quality: slider <-> editable numeric, live label, persist on release */
		var qVal = $( '#velox-q-val' );
		var qNum = $( '#velox-quality-num' );
		function setQuality( v, from ) {
			v = Math.max( 1, Math.min( 100, parseInt( v, 10 ) || 80 ) );
			if ( root && 'slider' !== from ) { root.value = v; }
			if ( qNum && 'num' !== from ) { qNum.value = v; }
			if ( qVal ) { qVal.textContent = v + '%'; }
			return v;
		}
		if ( root ) {
			root.addEventListener( 'input', function () { setQuality( root.value, 'slider' ); } );
			root.addEventListener( 'change', function () { saveSettings( { webp_quality: setQuality( root.value, 'slider' ) }, null ); } );
		}
		if ( qNum ) {
			qNum.addEventListener( 'input', function () { setQuality( qNum.value, 'num' ); } );
			qNum.addEventListener( 'change', function () { saveSettings( { webp_quality: setQuality( qNum.value, 'num' ) }, 'Quality saved.' ); } );
		}

		/* persist EXIF + max-width the moment they change */
		var keepExif = $( '#velox-keep-exif' );
		if ( keepExif ) {
			keepExif.addEventListener( 'change', function () {
				saveSettings( { image_keep_exif: keepExif.checked ? 1 : 0 }, 'Saved.' );
			} );
		}
		var maxWidth = $( '#velox-max-width' );
		if ( maxWidth ) {
			maxWidth.addEventListener( 'change', function () {
				saveSettings( { image_max_width: maxWidth.value }, 'Saved.' );
			} );
		}
		var avif = $( '#velox-avif' );
		if ( avif ) {
			avif.addEventListener( 'change', function () {
				saveSettings( { image_avif: avif.checked ? 1 : 0 }, avif.checked ? 'AVIF on — reconvert images to build the twins.' : 'AVIF off.' );
			} );
		}

		var RING_LEN = 327; // 2 * PI * r(52)
		function paintStats( s ) {
			if ( grid ) {
				grid.innerHTML = [
					miniStat( s.done + ' / ' + s.total, 'Optimized' ),
					miniStat( s.pending, 'Pending' ),
					miniStat( bytes( s.saved_bytes ), 'Saved' ),
				].join( '' );
			}
			var pct = s.total ? Math.round( ( s.done / s.total ) * 100 ) : 0;
			var fg = $( '#velox-ring-fg' );
			var lbl = $( '#velox-ring-label' );
			if ( fg ) {
				fg.style.strokeDasharray = RING_LEN;
				fg.style.strokeDashoffset = RING_LEN - ( RING_LEN * pct ) / 100;
			}
			if ( lbl ) {
				lbl.textContent = pct + '%';
			}
		}
		function miniStat( value, label ) {
			return (
				'<div class="velox-mini-stat"><span class="velox-mini-num">' +
				escapeHtml( value ) +
				'</span><span class="velox-mini-label">' +
				escapeHtml( label ) +
				'</span></div>'
			);
		}

		function refreshStats() {
			return api( 'image_stats' ).then( function ( s ) {
				paintStats( s );
				return s;
			} );
		}
		refreshStats().then( loadCompareOptions );
		// A single-image convert (from the library grid) asks the stats to refresh live.
		document.addEventListener( 'velox:refresh-stats', function () { refreshStats(); } );

		/* ---- bulk convert ---- */
		var startBtn = $( '#velox-bulk-start' );
		var stopBtn = $( '#velox-bulk-stop' );
		var progWrap = $( '#velox-bulk-progress' );
		var bar = $( '#velox-bulk-bar' );
		var text = $( '#velox-bulk-text' );
		var summary = $( '#velox-bulk-summary' );
		var stopFlag = false;

		if ( startBtn ) {
			startBtn.addEventListener( 'click', function () {
				if ( ! VELOX.webp_engine ) {
					toast( 'No WebP engine available on this server.', 'error' );
					return;
				}
				runBulk();
			} );
		}
		if ( stopBtn ) {
			stopBtn.addEventListener( 'click', function () {
				stopFlag = true;
				stopBtn.disabled = true;
				stopBtn.textContent = 'Stopping…';
			} );
		}

		function runBulk() {
			stopFlag = false;
			var quality = root ? root.value : 80;
			startBtn.disabled = true;
			startBtn.textContent = 'Working…';
			if ( stopBtn ) {
				stopBtn.hidden = false;
				stopBtn.disabled = false;
				stopBtn.textContent = 'Stop';
			}
			if ( summary ) {
				summary.textContent = '';
			}

			api( 'pending_ids' )
				.then( function ( d ) {
					var ids = d.ids || [];
					var total = ids.length;
					if ( ! total ) {
						finish( 0, 0, 'Everything is already optimized. ✨' );
						return;
					}
					if ( progWrap ) {
						progWrap.hidden = false;
					}
					var done = 0;
					var saved = 0;
					var failed = 0;
					var lastErr = '';

					function next() {
						if ( stopFlag || ! ids.length ) {
							finish( done, saved, stopFlag ? 'Stopped.' : null, failed, lastErr );
							return;
						}
						var id = ids.shift();
						api( 'convert_one', { id: id, quality: quality } )
							.then( function ( res ) {
								done++;
								if ( res && res.original_bytes ) {
									saved += res.original_bytes - res.webp_bytes;
								}
							} )
							.catch( function ( e ) {
								failed++;
								if ( e && e.message ) { lastErr = e.message; }
							} )
							.then( function () {
								var handled = done + failed;
								var pct = Math.round( ( handled / total ) * 100 );
								if ( bar ) {
									bar.style.width = pct + '%';
								}
								if ( text ) {
									text.textContent = handled + ' / ' + total + ' · ' + bytes( saved ) + ' saved' + ( failed ? ' · ' + failed + ' failed' : '' );
								}
								next();
							} );
					}
					next();
				} )
				.catch( function ( e ) {
					toast( e.message, 'error' );
					resetBulkButtons();
				} );
		}

		function finish( done, saved, customMsg, failed, lastErr ) {
			resetBulkButtons();
			if ( summary ) {
				if ( customMsg ) {
					summary.textContent = customMsg;
				} else if ( failed ) {
					summary.textContent = 'Done — ' + done + ' converted, ' + failed + ' failed. ' + (
						done === 0
							? 'Your server could not encode any images to WebP — ask your host to enable WebP support in GD or Imagick.'
							: ( lastErr || 'Some images could not be converted.' )
					);
				} else {
					summary.textContent = 'Done — ' + done + ' images, ' + bytes( saved ) + ' saved.';
				}
			}
			refreshStats().then( loadCompareOptions );
		}
		function resetBulkButtons() {
			startBtn.disabled = false;
			startBtn.textContent = 'Convert pending images';
			if ( stopBtn ) {
				stopBtn.hidden = true;
			}
		}

		/* ---- comparator ---- */
		var compareSelect = $( '#velox-compare-select' );
		var compareStage = $( '#velox-compare' );
		var imgWebp = $( '#velox-compare-webp' );
		var imgOrig = $( '#velox-compare-orig' );
		var top = $( '#velox-compare-top' );
		var handle = $( '#velox-compare-handle' );
		var cStats = $( '#velox-compare-stats' );

		function loadCompareOptions() {
			if ( ! compareSelect ) {
				return;
			}
			// Pull a page of media, keep ones that already have webp.
			return api( 'list_media', { page: 1 } ).then( function ( d ) {
				var conv = ( d.items || [] ).filter( function ( it ) {
					return it.webp;
				} );
				if ( ! conv.length ) {
					compareSelect.innerHTML =
						'<option value="">No optimized images yet</option>';
					return;
				}
				compareSelect.innerHTML =
					'<option value="">Pick an image…</option>' +
					conv
						.map( function ( it ) {
							return (
								'<option value="' +
								it.id +
								'">' +
								escapeHtml( it.filename || ( 'Image #' + it.id ) ) +
								'</option>'
							);
						} )
						.join( '' );
			} );
		}

		if ( compareSelect ) {
			compareSelect.addEventListener( 'change', function () {
				var id = compareSelect.value;
				if ( ! id ) {
					if ( compareStage ) {
						compareStage.hidden = true;
					}
					return;
				}
				api( 'compare_data', { id: id } ).then( function ( d ) {
					if ( imgOrig ) {
						imgOrig.src = d.original || '';
					}
					if ( imgWebp ) {
						imgWebp.src = d.webp || d.original || '';
					}
					if ( compareStage ) {
						compareStage.hidden = false;
					}
					setHandle( 50 );
					if ( cStats && d.stats ) {
						cStats.innerHTML =
							'<span>Original <strong>' +
							bytes( d.stats.original_bytes ) +
							'</strong></span>' +
							'<span>WebP <strong>' +
							bytes( d.stats.webp_bytes ) +
							'</strong></span>' +
							'<span class="velox-compare-save">−' +
							d.stats.saved_pct +
							'%</span>';
					} else if ( cStats ) {
						cStats.innerHTML = '';
					}
				} ).catch( function ( e ) {
					toast( e.message, 'error' );
				} );
			} );
		}

		function setHandle( pct ) {
			pct = Math.max( 0, Math.min( 100, pct ) );
			if ( top ) {
				top.style.width = pct + '%';
			}
			if ( handle ) {
				handle.style.left = pct + '%';
			}
		}

		if ( compareStage ) {
			var dragging = false;
			function moveFromEvent( e ) {
				var rect = compareStage.getBoundingClientRect();
				var x = ( e.touches ? e.touches[ 0 ].clientX : e.clientX ) - rect.left;
				setHandle( ( x / rect.width ) * 100 );
			}
			var startDrag = function ( e ) {
				dragging = true;
				moveFromEvent( e );
			};
			compareStage.addEventListener( 'mousedown', startDrag );
			compareStage.addEventListener( 'touchstart', startDrag, { passive: true } );
			window.addEventListener( 'mousemove', function ( e ) {
				if ( dragging ) {
					moveFromEvent( e );
				}
			} );
			window.addEventListener( 'touchmove', function ( e ) {
				if ( dragging ) {
					moveFromEvent( e );
				}
			}, { passive: true } );
			window.addEventListener( 'mouseup', function () {
				dragging = false;
			} );
			window.addEventListener( 'touchend', function () {
				dragging = false;
			} );
		}
	}

	/* ----------------------------------------------------------------
	 * Media: grid, inline meta editing, rename, pipe import/export
	 * ------------------------------------------------------------- */

	function initMedia() {
		var gridEl = $( '#velox-media-grid' );
		if ( ! gridEl ) {
			return;
		}

		var state = { page: 1, pages: 1, search: '' };
		var prev = $( '#velox-media-prev' );
		var next = $( '#velox-media-next' );
		var pageInfo = $( '#velox-media-pageinfo' );
		var searchEl = $( '#velox-media-search' );

		function load() {
			gridEl.innerHTML = '<div class="velox-loading">Loading media…</div>';
			api( 'list_media', { page: state.page, s: state.search } )
				.then( function ( d ) {
					state.pages = d.total_pages || 1;
					render( d.items || [] );
					if ( pageInfo ) {
						pageInfo.textContent =
							'Page ' + d.page + ' / ' + state.pages + ' · ' + d.total + ' images';
					}
					if ( prev ) {
						prev.disabled = d.page <= 1;
					}
					if ( next ) {
						next.disabled = d.page >= state.pages;
					}
				} )
				.catch( function ( e ) {
					gridEl.innerHTML =
						'<div class="velox-loading">' + escapeHtml( e.message ) + '</div>';
				} );
		}

		function render( items ) {
			if ( ! items.length ) {
				gridEl.innerHTML = '<div class="velox-loading">No images found.</div>';
				return;
			}
			gridEl.innerHTML = items
				.map( function ( it ) {
					return (
						'<div class="velox-media-card" data-id="' +
						it.id +
						'" data-w="' + ( it.width || 0 ) + '" data-h="' + ( it.height || 0 ) +
						'" data-full="' + escapeHtml( it.full || it.thumb || '' ) +
						'" data-name="' + escapeHtml( it.filename || '' ) + '">' +
						'<div class="velox-media-thumb">' +
						'<input type="checkbox" class="velox-media-select" value="' + it.id + '" aria-label="Select image">' +
						'<img src="' +
						escapeHtml( it.thumb || it.full ) +
						'" alt="" loading="lazy" class="velox-media-open" title="Click to resize">' +
						'<span class="velox-media-dims">' + ( it.width ? it.width + ' × ' + it.height : '' ) + '</span>' +
						( it.webp
							? '<span class="velox-media-badge">WebP</span>'
							: '' ) +
						'</div>' +
						'<div class="velox-media-body">' +
						'<code class="velox-media-name">' +
						escapeHtml( it.filename ) +
						'</code>' +
						field( 'title', 'Title', it.title ) +
						field( 'alt', 'Alt text', it.alt ) +
						field( 'caption', 'Caption', it.caption ) +
						'<div class="velox-media-actions">' +
						'<button class="velox-btn velox-btn--primary velox-media-save">Save</button>' +
						'<button class="velox-btn velox-btn--ghost velox-media-rename" data-name="' +
						escapeHtml( it.filename ) +
						'">Rename file</button>' +
						'</div>' +
						'</div>' +
						'</div>'
					);
				} )
				.join( '' );
			if ( typeof selecting !== 'undefined' && selecting ) { updateSel(); }
		}

		function field( key, label, value ) {
			return (
				'<label class="velox-media-field"><span>' +
				label +
				'</span>' +
				'<input type="text" data-field="' +
				key +
				'" value="' +
				escapeHtml( value ) +
				'"></label>'
			);
		}

		/* delegated events on the grid */
		gridEl.addEventListener( 'click', function ( e ) {
			var saveBtn = e.target.closest( '.velox-media-save' );
			var renameBtn = e.target.closest( '.velox-media-rename' );
			var openImg = e.target.closest( '.velox-media-open' );
			if ( saveBtn ) {
				var card = saveBtn.closest( '.velox-media-card' );
				saveCard( card, saveBtn );
			} else if ( renameBtn ) {
				var card2 = renameBtn.closest( '.velox-media-card' );
				openRename( card2.getAttribute( 'data-id' ), renameBtn.getAttribute( 'data-name' ) );
			} else if ( openImg ) {
				var card3 = openImg.closest( '.velox-media-card' );
				openResize(
					card3.getAttribute( 'data-id' ),
					card3.getAttribute( 'data-name' ),
					card3.getAttribute( 'data-w' ),
					card3.getAttribute( 'data-h' ),
					card3.getAttribute( 'data-full' )
				);
			}
		} );

		function saveCard( card, btn ) {
			var id = card.getAttribute( 'data-id' );
			var payload = { id: id };
			$$( '[data-field]', card ).forEach( function ( inp ) {
				payload[ inp.getAttribute( 'data-field' ) ] = inp.value;
			} );
			btn.disabled = true;
			btn.textContent = 'Saving…';
			api( 'save_meta', payload )
				.then( function () {
					toast( 'Saved.', 'success' );
				} )
				.catch( function ( err ) {
					toast( err.message, 'error' );
				} )
				.then( function () {
					btn.disabled = false;
					btn.textContent = 'Save';
				} );
		}

		/* ----- bulk download (select mode) ----- */
		var selecting  = false;
		var dlBtn      = $( '#velox-media-download' );
		var selBar     = $( '#velox-media-selectbar' );
		var selAllBtn  = $( '#velox-media-selectall' );
		var selCancel  = $( '#velox-media-selectcancel' );
		var dlGo       = $( '#velox-media-dl-go' );
		var selCount   = $( '#velox-media-selcount' );

		function selectedIds() { return $$( '.velox-media-select:checked', gridEl ).map( function ( c ) { return c.value; } ); }
		function updateSel() {
			var n = selectedIds().length;
			if ( dlGo ) { dlGo.disabled = n === 0; dlGo.textContent = 'Download selected' + ( n ? ' (' + n + ')' : '' ); }
			if ( selCount ) { selCount.textContent = n ? ( n + ' image' + ( n === 1 ? '' : 's' ) + ' selected · alt text & titles come along in a text file' ) : 'Tick the images you want, then download. Alt text & titles come along in a text file.'; }
			if ( selAllBtn ) {
				var boxes = $$( '.velox-media-select', gridEl );
				selAllBtn.textContent = ( boxes.length && boxes.every( function ( b ) { return b.checked; } ) ) ? 'Deselect all' : 'Select all';
			}
		}
		function enterSelect() { selecting = true; gridEl.classList.add( 'is-selecting' ); if ( selBar ) { selBar.hidden = false; } if ( dlBtn ) { dlBtn.hidden = true; } updateSel(); }
		function exitSelect() {
			selecting = false; gridEl.classList.remove( 'is-selecting' );
			if ( selBar ) { selBar.hidden = true; }
			if ( dlBtn ) { dlBtn.hidden = false; }
			$$( '.velox-media-select', gridEl ).forEach( function ( c ) { c.checked = false; } );
			$$( '.velox-media-card', gridEl ).forEach( function ( c ) { c.classList.remove( 'is-selected' ); } );
		}
		if ( dlBtn ) { dlBtn.addEventListener( 'click', enterSelect ); }
		if ( selCancel ) { selCancel.addEventListener( 'click', exitSelect ); }
		if ( selAllBtn ) {
			selAllBtn.addEventListener( 'click', function () {
				var boxes = $$( '.velox-media-select', gridEl );
				var allOn = boxes.length && boxes.every( function ( b ) { return b.checked; } );
				boxes.forEach( function ( b ) { b.checked = ! allOn; b.closest( '.velox-media-card' ).classList.toggle( 'is-selected', b.checked ); } );
				updateSel();
			} );
		}
		gridEl.addEventListener( 'change', function ( e ) {
			var b = e.target.closest ? e.target.closest( '.velox-media-select' ) : null;
			if ( ! b ) { return; }
			b.closest( '.velox-media-card' ).classList.toggle( 'is-selected', b.checked );
			updateSel();
		} );
		if ( dlGo ) {
			dlGo.addEventListener( 'click', function () {
				var ids = selectedIds();
				if ( ! ids.length ) { return; }
				dlGo.disabled = true;
				var orig = dlGo.textContent;
				dlGo.textContent = 'Preparing…';
				api( 'media_zip', { ids: ids } )
					.then( function ( r ) {
						if ( r && r.url ) {
							var a = document.createElement( 'a' );
							a.href = r.url; a.download = r.filename || '';
							document.body.appendChild( a ); a.click(); a.remove();
							toast( 'Download ready — ' + ( r.count || ids.length ) + ' image' + ( ( r.count || ids.length ) === 1 ? '' : 's' ) + '.', 'success' );
						} else {
							toast( 'Could not build the download.', 'error' );
						}
					} )
					.catch( function ( e ) { toast( e.message, 'error' ); } )
					.then( function () { dlGo.disabled = false; dlGo.textContent = orig; } );
			} );
		}

		/* paging + search */
		if ( prev ) {
			prev.addEventListener( 'click', function () {
				if ( state.page > 1 ) {
					state.page--;
					load();
				}
			} );
		}
		if ( next ) {
			next.addEventListener( 'click', function () {
				if ( state.page < state.pages ) {
					state.page++;
					load();
				}
			} );
		}
		if ( searchEl ) {
			searchEl.addEventListener(
				'input',
				debounce( function () {
					state.search = searchEl.value.trim();
					state.page = 1;
					load();
				}, 350 )
			);
		}

		/* rename modal */
		// ---- resize ----
		var rzModal = $( '#velox-resize-modal' );
		var rzW = $( '#velox-resize-w' ), rzH = $( '#velox-resize-h' );
		var rzLock = $( '#velox-resize-lock' ), rzImg = $( '#velox-resize-img' );
		var rzCur = $( '#velox-resize-current' );
		var rzId = 0, rzOrigW = 0, rzOrigH = 0, rzRatio = 1, rzKeep = true;

		function openResize( id, name, w, h, src ) {
			rzId = parseInt( id, 10 );
			rzOrigW = parseInt( w, 10 ) || 0;
			rzOrigH = parseInt( h, 10 ) || 0;
			rzRatio = ( rzOrigW && rzOrigH ) ? ( rzOrigH / rzOrigW ) : 1;
			if ( rzImg ) { rzImg.src = src || ''; }
			if ( rzCur ) {
				rzCur.textContent = name + ( rzOrigW ? ' — currently ' + rzOrigW + ' × ' + rzOrigH + ' px' : '' );
			}
            if ( rzW ) { rzW.value = rzOrigW || ''; }
			if ( rzH ) { rzH.value = rzOrigH || ''; }
			if ( rzModal ) { rzModal.hidden = false; }
		}
		if ( rzLock ) {
			rzLock.addEventListener( 'click', function () {
				rzKeep = ! rzKeep;
				rzLock.classList.toggle( 'is-on', rzKeep );
				rzLock.setAttribute( 'aria-pressed', rzKeep ? 'true' : 'false' );
			} );
		}
		if ( rzW ) {
			rzW.addEventListener( 'input', function () {
				if ( ! rzKeep || ! rzRatio ) { return; }
				var v = parseInt( rzW.value, 10 );
				if ( v > 0 ) { rzH.value = Math.max( 1, Math.round( v * rzRatio ) ); }
			} );
		}
		if ( rzH ) {
			rzH.addEventListener( 'input', function () {
				if ( ! rzKeep || ! rzRatio ) { return; }
				var v = parseInt( rzH.value, 10 );
				if ( v > 0 ) { rzW.value = Math.max( 1, Math.round( v / rzRatio ) ); }
			} );
		}
		$$( '#velox-resize-presets button' ).forEach( function ( b ) {
			b.addEventListener( 'click', function () {
				var sc = parseFloat( b.getAttribute( 'data-scale' ) ) || 1;
				if ( ! rzOrigW ) { return; }
				rzW.value = Math.max( 1, Math.round( rzOrigW * sc ) );
				rzH.value = Math.max( 1, Math.round( rzOrigH * sc ) );
			} );
		} );
		var rzCancel = $( '#velox-resize-cancel' );
		if ( rzCancel ) { rzCancel.addEventListener( 'click', function () { rzModal.hidden = true; } ); }
		if ( rzModal ) {
			rzModal.addEventListener( 'click', function ( e ) { if ( e.target === rzModal ) { rzModal.hidden = true; } } );
		}
		var rzGo = $( '#velox-resize-go' );
		if ( rzGo ) {
			rzGo.addEventListener( 'click', function () {
				var w = parseInt( rzW.value, 10 ), h = parseInt( rzH.value, 10 );
				if ( ! ( w > 0 && h > 0 ) ) { toast( 'Enter a width and height.', 'error' ); return; }
				rzGo.disabled = true;
				rzGo.textContent = 'Resizing…';
				api( 'media_resize', { id: rzId, w: w, h: h } )
					.then( function ( r ) {
						toast( r.unchanged ? 'Already that size.' : 'Resized to ' + r.width + ' × ' + r.height + '.' );
						rzModal.hidden = true;
						loadMedia();
					} )
					.catch( function ( e ) { toast( e.message, 'error' ); } )
					.then( function () { rzGo.disabled = false; rzGo.textContent = 'Resize image'; } );
			} );
		}

		var renameModal = $( '#velox-rename-modal' );
		var renameCurrent = $( '#velox-rename-current' );
		var renameInput = $( '#velox-rename-input' );
		var renameGo = $( '#velox-rename-go' );
		var renameCancel = $( '#velox-rename-cancel' );
		var renameId = null;

		function openRename( id, name ) {
			renameId = id;
			if ( renameCurrent ) {
				renameCurrent.textContent = 'Current: ' + name;
			}
			if ( renameInput ) {
				// strip extension for the editable base
				renameInput.value = name.replace( /\.[a-z0-9]+$/i, '' );
			}
			if ( renameModal ) {
				renameModal.hidden = false;
			}
		}
		function closeRename() {
			if ( renameModal ) {
				renameModal.hidden = true;
			}
			renameId = null;
		}
		if ( renameCancel ) {
			renameCancel.addEventListener( 'click', closeRename );
		}
		if ( renameModal ) {
			renameModal.addEventListener( 'click', function ( e ) {
				if ( e.target === renameModal ) {
					closeRename();
				}
			} );
		}
		if ( renameGo ) {
			renameGo.addEventListener( 'click', function () {
				if ( ! renameId || ! renameInput.value.trim() ) {
					return;
				}
				renameGo.disabled = true;
				renameGo.textContent = 'Renaming…';
				api( 'rename', { id: renameId, name: renameInput.value.trim() } )
					.then( function ( res ) {
						toast(
							'Renamed → ' + res.new_name + ' · ' + res.refs_updated + ' refs fixed',
							'success'
						);
						closeRename();
						load();
					} )
					.catch( function ( err ) {
						toast( err.message, 'error' );
					} )
					.then( function () {
						renameGo.disabled = false;
						renameGo.textContent = 'Rename & fix references';
					} );
			} );
		}

		/* pipe export / import */
		var pipeExport = $( '#velox-pipe-export' );
		var pipeOpen = $( '#velox-pipe-open' );
		var pipeModal = $( '#velox-pipe-modal' );
		var pipeText = $( '#velox-pipe-text' );
		var pipeApply = $( '#velox-pipe-apply' );
		var pipeCancel = $( '#velox-pipe-cancel' );
		var pipeResult = $( '#velox-pipe-result' );

		if ( pipeExport ) {
			pipeExport.addEventListener( 'click', function () {
				api( 'export_pipe' ).then( function ( d ) {
					var blob = new Blob( [ d.text ], { type: 'text/plain' } );
					var url = URL.createObjectURL( blob );
					var a = document.createElement( 'a' );
					a.href = url;
					a.download = 'velox-alt-texts.txt';
					document.body.appendChild( a );
					a.click();
					document.body.removeChild( a );
					URL.revokeObjectURL( url );
					toast( 'Exported.', 'success' );
				} ).catch( function ( e ) {
					toast( e.message, 'error' );
				} );
			} );
		}
		if ( pipeOpen ) {
			pipeOpen.addEventListener( 'click', function () {
				if ( pipeModal ) {
					pipeModal.hidden = false;
				}
			} );
		}
		function closePipe() {
			if ( pipeModal ) {
				pipeModal.hidden = true;
			}
		}
		if ( pipeCancel ) {
			pipeCancel.addEventListener( 'click', closePipe );
		}
		if ( pipeModal ) {
			pipeModal.addEventListener( 'click', function ( e ) {
				if ( e.target === pipeModal ) {
					closePipe();
				}
			} );
		}
		if ( pipeApply ) {
			pipeApply.addEventListener( 'click', function () {
				pipeApply.disabled = true;
				pipeApply.textContent = 'Applying…';
				api( 'import_pipe', { text: pipeText ? pipeText.value : '' } )
					.then( function ( res ) {
						var msg =
							res.updated + ' updated · ' + res.skipped + ' skipped';
						if ( res.missing && res.missing.length ) {
							msg += ' · ' + res.missing.length + ' not found';
						}
						if ( pipeResult ) {
							pipeResult.textContent = msg;
							if ( res.missing && res.missing.length ) {
								pipeResult.textContent +=
									' (' + res.missing.slice( 0, 6 ).join( ', ' ) +
									( res.missing.length > 6 ? '…' : '' ) + ')';
							}
						}
						toast( 'Import done — ' + msg, 'success' );
						load();
					} )
					.catch( function ( err ) {
						toast( err.message, 'error' );
					} )
					.then( function () {
						pipeApply.disabled = false;
						pipeApply.textContent = 'Apply to library';
					} );
			} );
		}

		load();
	}

	/* ----------------------------------------------------------------
	 * Performance tab — save all [data-setting]
	 * ------------------------------------------------------------- */

	function initPerformance() {
		/* left-rail sub-navigation: show one panel at a time */
		var nav = $( '#velox-perf-nav' );
		if ( nav ) {
			var items = $$( '.velox-perf-navitem', nav );
			var panels = $$( '.velox-perf-panel' );
			nav.addEventListener( 'click', function ( e ) {
				var item = e.target.closest( '.velox-perf-navitem' );
				if ( ! item ) {
					return;
				}
				var section = item.getAttribute( 'data-section' );
				items.forEach( function ( i ) {
					i.classList.toggle( 'is-active', i === item );
				} );
				panels.forEach( function ( p ) {
					p.classList.toggle( 'is-active', p.getAttribute( 'data-section' ) === section );
				} );
			} );
		}

		/* Risky mode: reveal/hide the "might break" settings */
		var riskyToggle = $( '#velox-risky-toggle' );
		function applyRisky() {
			var on = riskyToggle && riskyToggle.checked;
			$$( '[data-risky="1"]' ).forEach( function ( el ) {
				el.style.display = on ? '' : 'none';
			} );
		}
		if ( riskyToggle ) {
			applyRisky();
			riskyToggle.addEventListener( 'change', function () {
				applyRisky();
				saveSettings( { perf_risky_mode: riskyToggle.checked ? 1 : 0 }, null );
			} );
		}

		/* Clear-cache buttons inside the General section */
		$$( '.velox-cache-btn' ).forEach( function ( cb ) {
			cb.addEventListener( 'click', function () {
				var which = cb.getAttribute( 'data-which' ) || 'all';
				var orig = cb.textContent;
				cb.disabled = true;
				cb.textContent = 'Clearing…';
				api( 'clear_cache', { which: which } )
					.then( function ( d ) {
						toast( ( d && d.message ) || 'Cache purged.' );
					} )
					.catch( function ( e ) {
						toast( e.message || 'Error clearing cache.', 'error' );
					} )
					.then( function () {
						cb.disabled = false;
						cb.textContent = orig;
					} );
			} );
		} );

		/* Page cache: enable → install drop-in; purge; preload */
		var cacheEnable = $( '[data-setting="cache_enable"]' );
		var cachePill = $( '#velox-cache-pill' );
		var cacheNote = $( '#velox-cache-note' );
		function cachePillSet( on, r ) {
			if ( ! cachePill ) { return; }
			if ( ! on ) {
				cachePill.textContent = 'Off';
				cachePill.className = 'velox-pill velox-pill--muted';
				if ( cacheNote ) { cacheNote.hidden = true; }
				return;
			}
			// Enabled = active. The drop-in just makes it serve even earlier.
			if ( r && r.dropin && r.wp_cache ) {
				cachePill.textContent = 'Active · early serve';
				cachePill.className = 'velox-pill velox-pill--ok';
				if ( cacheNote ) { cacheNote.hidden = true; }
			} else {
				cachePill.textContent = 'Active';
				cachePill.className = 'velox-pill velox-pill--ok';
				if ( cacheNote ) {
					cacheNote.hidden = false;
					cacheNote.className = 'velox-alert velox-alert--info velox-cache-note';
					cacheNote.innerHTML = 'Serving cached pages through WordPress. For the absolute fastest path (serving <em>before</em> WordPress loads), Velox needs a writable <code>wp-config.php</code>' + ( r && r.manual ? ' — add <code>' + escapeHtml( r.manual ) + '</code> near the top' : '' ) + '. Optional — the cache is already working.';
				}
			}
		}
		if ( cacheEnable ) {
			cacheEnable.addEventListener( 'change', function () {
				var on = cacheEnable.checked;
				if ( cachePill ) { cachePill.textContent = '…'; }
				saveSettings( { cache_enable: on ? 1 : 0 } )
					.then( function () { return api( 'cache_setup' ); } )
					.then( function ( r ) { cachePillSet( on, r ); toast( on ? 'Page cache on.' : 'Page cache off.' ); } )
					.catch( function ( e ) { toast( e.message, 'error' ); } );
			} );
		}
		var cachePurge = $( '#velox-cache-purge' );
		if ( cachePurge ) {
			cachePurge.addEventListener( 'click', function () {
				cachePurge.disabled = true;
				api( 'cache_purge' )
					.then( function () { toast( 'Page cache purged.' ); } )
					.catch( function ( e ) { toast( e.message, 'error' ); } )
					.then( function () { cachePurge.disabled = false; } );
			} );
		}
		var cachePreload = $( '#velox-cache-preload' );
		if ( cachePreload ) {
			cachePreload.addEventListener( 'click', function () {
				cachePreload.disabled = true;
				cachePreload.textContent = 'Preloading…';
				api( 'cache_preload' )
					.then( function ( r ) { toast( 'Warmed ' + ( ( r && r.warmed ) || 0 ) + ' pages.' ); } )
					.catch( function ( e ) { toast( e.message, 'error' ); } )
					.then( function () { cachePreload.disabled = false; cachePreload.textContent = 'Preload now'; } );
			} );
		}

		/* Local fonts: scan & download / remove */
		var fScan = $( '#velox-fonts-scan' );
		var fClear = $( '#velox-fonts-clear' );
		var fStatus = $( '#velox-fonts-status' );
		if ( fScan ) {
			fScan.addEventListener( 'click', function () {
				fScan.disabled = true;
				fScan.textContent = 'Scanning…';
				if ( fStatus ) {
					fStatus.innerHTML = '<span class="velox-hint">Loading your front page and downloading fonts…</span>';
				}
				api( 'localize_fonts', {} )
					.then( function ( d ) {
						toast( ( d && d.message ) || 'Fonts hosted locally.' );
						if ( fStatus ) {
							var fam = d && d.families && d.families.length ? ' — ' + d.families.join( ', ' ) : '';
							fStatus.innerHTML = '<span class="velox-fonts-ok">✓ ' + ( ( d && d.files ) || 0 ) + ' font file(s) hosted locally' + escapeHtml( fam ) + '</span>';
						}
					} )
					.catch( function ( e ) {
						toast( e.message, 'error' );
						if ( fStatus ) {
							fStatus.innerHTML = '<span class="velox-hint">' + escapeHtml( e.message ) + '</span>';
						}
					} )
					.then( function () {
						fScan.disabled = false;
						fScan.textContent = 'Scan & download fonts';
					} );
			} );
		}
		if ( fClear ) {
			fClear.addEventListener( 'click', function () {
				fClear.disabled = true;
				api( 'clear_local_fonts', {} )
					.then( function ( d ) {
						toast( ( d && d.message ) || 'Local fonts removed.' );
						if ( fStatus ) {
							fStatus.innerHTML = '<span class="velox-hint">No fonts hosted locally yet. Enable the toggle, then scan.</span>';
						}
					} )
					.catch( function ( e ) {
						toast( e.message, 'error' );
					} )
					.then( function () {
						fClear.disabled = false;
					} );
			} );
		}

		/* Font detector (9b): list every @font-face, toggle preload per file. */
		var fDetect = $( '#velox-font-detect' );
		var fDList  = $( '#velox-font-detect-list' );
		function fontPreloadList() {
			var ta = $( '[data-setting="perf_preload_fonts"]' );
			return ( ta ? ta.value : '' ).split( '\n' ).map( function ( s ) { return s.trim(); } ).filter( Boolean );
		}
		function setFontPreload( urls ) {
			var ta = $( '[data-setting="perf_preload_fonts"]' );
			if ( ta ) { ta.value = urls.join( '\n' ); }
			saveSettings( { perf_preload_fonts: urls.join( '\n' ) } );
		}
		function fontBlockList() {
			var ta = $( '[data-setting="perf_font_block"]' );
			return ( ta ? ta.value : '' ).split( '\n' ).map( function ( s ) { return s.trim(); } ).filter( Boolean );
		}
		function setFontBlock( fams ) {
			var ta = $( '[data-setting="perf_font_block"]' );
			if ( ta ) { ta.value = fams.join( '\n' ); }
			saveSettings( { perf_font_block: fams.join( '\n' ) } );
		}
		if ( fDetect && fDList ) {
			fDetect.addEventListener( 'click', function () {
				fDetect.disabled = true;
				fDetect.textContent = 'Scanning…';
				api( 'detect_fonts', {} )
					.then( function ( d ) {
						var fonts = ( d && d.fonts ) || [];
						fDList.hidden = false;
						if ( ! fonts.length ) {
							fDList.innerHTML = '<p class="velox-hint">No fonts were detected. Velox scans your front page and your builder\'s CSS cache (Oxygen/Bricks/Elementor) — fonts injected purely by JavaScript may still not show here.</p>';
							return;
						}
						var pre  = fontPreloadList();
						var blk  = fontBlockList();
						var html = '<div class="velox-font-legend">' +
							'<span class="velox-font-legend-l">Detected fonts</span>' +
							'<span class="velox-font-legend-r"><strong>Preload</strong> loads a font early (above-the-fold only) · <strong>Block</strong> stops it loading</span>' +
							'</div>';
						fonts.forEach( function ( f ) {
							var on    = pre.indexOf( f.url ) !== -1;
							var isBlk = blk.some( function ( b ) { return b.toLowerCase() === ( f.family || '' ).toLowerCase(); } );
							var file  = f.url.split( '/' ).pop().split( '?' )[0];
							var meta  = f.weight + ( f.style && 'normal' !== f.style ? ' · ' + f.style : '' );
							var src   = 'google' === f.source
								? '<span class="velox-font-src is-google">Google</span>'
								: '<span class="velox-font-src">Local</span>';
							html += '<div class="velox-font-row' + ( isBlk ? ' is-blocked' : '' ) + '">' +
								'<div class="velox-font-info"><span class="velox-font-fam">' + escapeHtml( f.family ) + ' ' + src + '</span>' +
								'<span class="velox-font-meta">' + escapeHtml( meta ) + ' · ' + escapeHtml( file ) + '</span></div>' +
								'<div class="velox-font-acts">' +
									'<label class="velox-font-pre-lbl" title="Preload this font so it starts loading immediately — use only for fonts visible above the fold"><span>Preload</span><span class="velox-switch"><input type="checkbox" class="velox-font-pre" data-url="' + escapeHtml( f.url ) + '"' + ( on ? ' checked' : '' ) + '><span class="velox-switch-track"></span></span></label>' +
									'<button type="button" class="velox-font-block' + ( isBlk ? ' is-on' : '' ) + '" data-fam="' + escapeHtml( f.family ) + '" title="Stop this font from loading at all">' + ( isBlk ? 'Blocked' : 'Block' ) + '</button>' +
								'</div>' +
								'</div>';
						} );
						fDList.innerHTML = html;
						$$( '.velox-font-pre', fDList ).forEach( function ( cb ) {
							cb.addEventListener( 'change', function () {
								var urls = fontPreloadList();
								var u    = cb.getAttribute( 'data-url' );
								var i    = urls.indexOf( u );
								if ( cb.checked && -1 === i ) { urls.push( u ); }
								else if ( ! cb.checked && -1 !== i ) { urls.splice( i, 1 ); }
								setFontPreload( urls );
								toast( cb.checked ? 'Added to preload.' : 'Removed from preload.' );
							} );
						} );
						$$( '.velox-font-block', fDList ).forEach( function ( bb ) {
							bb.addEventListener( 'click', function () {
								var fams = fontBlockList();
								var fam  = bb.getAttribute( 'data-fam' );
								var idx  = fams.map( function ( x ) { return x.toLowerCase(); } ).indexOf( fam.toLowerCase() );
								var nowBlocked;
								if ( -1 === idx ) { fams.push( fam ); nowBlocked = true; }
								else { fams.splice( idx, 1 ); nowBlocked = false; }
								setFontBlock( fams );
								bb.classList.toggle( 'is-on', nowBlocked );
								bb.textContent = nowBlocked ? 'Blocked' : 'Block';
								var row = bb.closest( '.velox-font-row' );
								if ( row ) { row.classList.toggle( 'is-blocked', nowBlocked ); }
								toast( nowBlocked ? 'Font blocked — it won\u2019t load on the site.' : 'Font unblocked.' );
							} );
						} );
					} )
					.catch( function ( e ) { toast( e.message, 'error' ); } )
					.then( function () { fDetect.disabled = false; fDetect.textContent = 'Detect fonts'; } );
			} );
		}

		/* Remove-unused-CSS: scan & build (saves settings first, then renders each page) */
		var rucssScan = $( '#velox-rucss-scan' );
		var rucssStatus = $( '#velox-rucss-status' );
		if ( rucssScan ) {
			rucssScan.addEventListener( 'click', function () {
				var urlsEl = $( '[data-setting="perf_rucss_urls"]' );
				var paths = ( urlsEl ? urlsEl.value : '/' )
					.split( /\n+/ )
					.map( function ( s ) { return s.trim(); } )
					.filter( Boolean );
				if ( ! paths.length ) {
					toast( 'Add at least one page path to scan.', 'error' );
					return;
				}
				rucssScan.disabled = true;
				rucssScan.textContent = 'Saving…';
				// Persist current settings (engine, token, urls, safelist) before rendering.
				saveSettings( collectSettings( document.querySelector( '.velox-main' ) || document ), null ).then( function () {
					var done = 0, ok = 0, fail = 0, msgs = [];
					function step() {
						if ( done >= paths.length ) {
							rucssScan.disabled = false;
							rucssScan.textContent = 'Scan & build used-CSS';
							toast( 'Scan complete: ' + ok + ' built, ' + fail + ' failed.' );
							if ( rucssStatus ) {
								rucssStatus.innerHTML = '<span class="' + ( fail ? 'velox-hint' : 'velox-fonts-ok' ) + '">' + ( fail ? '' : '✓ ' ) + escapeHtml( msgs.join( ' · ' ) ) + '</span>';
							}
							return;
						}
						var path = paths[ done ];
						rucssScan.textContent = 'Scanning ' + ( done + 1 ) + '/' + paths.length + '…';
						api( 'rucss_scan_one', { path: path } )
							.then( function ( d ) {
								ok++;
								msgs.push( ( d && d.message ) || path );
							} )
							.catch( function ( e ) {
								fail++;
								msgs.push( path + ': ' + e.message );
							} )
							.then( function () {
								done++;
								setTimeout( step, 300 );
							} );
					}
					step();
				} );
			} );
		}

		/* Reset auto-learn data */
		var rsBtn = $( '#velox-rucss-reset' );
		if ( rsBtn ) {
			rsBtn.addEventListener( 'click', function () {
				rsBtn.disabled = true;
				api( 'rucss_reset_learn', {} )
					.then( function ( d ) {
						toast( ( d && d.message ) || 'Auto-learn reset.' );
					} )
					.catch( function ( e ) {
						toast( e.message, 'error' );
					} )
					.then( function () {
						rsBtn.disabled = false;
					} );
			} );
		}

		/* Clear used-CSS cache */
		var ucBtn = $( '#velox-clear-usedcss' );
		if ( ucBtn ) {
			ucBtn.addEventListener( 'click', function () {
				ucBtn.disabled = true;
				api( 'clear_used_css', {} )
					.then( function ( d ) {
						toast( ( d && d.message ) || 'Used-CSS cache cleared.' );
					} )
					.catch( function ( e ) {
						toast( e.message, 'error' );
					} )
					.then( function () {
						ucBtn.disabled = false;
					} );
			} );
		}

		var btn = $( '#velox-perf-save' );
		if ( ! btn ) {
			return;
		}
		btn.addEventListener( 'click', function () {
			btn.disabled = true;
			btn.textContent = 'Saving…';
			saveSettings(
				collectSettings( document.querySelector( '.velox-main' ) || document ),
				'Performance settings saved.'
			).then( function () {
				btn.disabled = false;
				btn.textContent = 'Save performance settings';
			} );
		} );
	}

	/* ----------------------------------------------------------------
	 * Database tab
	 * ------------------------------------------------------------- */

	function initDatabase() {
		var list = $( '#velox-db-list' );
		if ( ! list ) {
			return;
		}

		function paint( counts ) {
			Object.keys( counts ).forEach( function ( key ) {
				var span = list.querySelector( '[data-count="' + key + '"]' );
				if ( span ) {
					span.textContent = counts[ key ];
					var row = span.closest( '.velox-db-row' );
					if ( row ) {
						row.classList.toggle( 'is-zero', ! counts[ key ] );
					}
				}
			} );
		}
		function refresh() {
			return api( 'db_counts' ).then( paint );
		}
		refresh();

		list.addEventListener( 'click', function ( e ) {
			var btn = e.target.closest( '.velox-db-clean' );
			if ( ! btn ) {
				return;
			}
			var item = btn.getAttribute( 'data-item' );
			btn.disabled = true;
			btn.textContent = 'Cleaning…';
			api( 'db_clean', { item: item } )
				.then( function ( res ) {
					toast( 'Cleaned ' + res.cleaned + ' rows.', 'success' );
					return refresh();
				} )
				.catch( function ( err ) {
					toast( err.message, 'error' );
				} )
				.then( function () {
					btn.disabled = false;
					btn.textContent = 'Clean';
				} );
		} );

		var allBtn = $( '#velox-db-all' );
		if ( allBtn ) {
			allBtn.addEventListener( 'click', function () {
				allBtn.disabled = true;
				allBtn.textContent = 'Cleaning…';
				api( 'db_clean', { item: 'all' } )
					.then( function ( res ) {
						toast( 'Cleaned ' + res.cleaned + ' rows total.', 'success' );
						return refresh();
					} )
					.catch( function ( err ) {
						toast( err.message, 'error' );
					} )
					.then( function () {
						allBtn.disabled = false;
						allBtn.textContent = 'Clean everything';
					} );
			} );
		}

		var optBtn = $( '#velox-db-optimize' );
		if ( optBtn ) {
			optBtn.addEventListener( 'click', function () {
				optBtn.disabled = true;
				optBtn.textContent = 'Optimizing…';
				api( 'db_clean', { item: 'optimize_tables' } )
					.then( function () {
						toast( 'Tables optimized.', 'success' );
					} )
					.catch( function ( err ) {
						toast( err.message, 'error' );
					} )
					.then( function () {
						optBtn.disabled = false;
						optBtn.textContent = 'Optimize tables';
					} );
			} );
		}

		var saveAuto = $( '#velox-db-save-auto' );
		if ( saveAuto ) {
			saveAuto.addEventListener( 'click', function () {
				var sched = $( '#velox-db-schedule' );
				saveAuto.disabled = true;
				saveAuto.textContent = 'Saving…';
				saveSettings(
					{ db_schedule_cleanup: sched && sched.checked ? 1 : 0 },
					'Automation saved.'
				).then( function () {
					saveAuto.disabled = false;
					saveAuto.textContent = 'Save automation';
				} );
			} );
		}
	}

	/* ----------------------------------------------------------------
	 * Settings tab
	 * ------------------------------------------------------------- */

	function initSettings() {
		// Importer cards only exist on the Settings page — use them as the guard now
		// that the Save button is gone.
		if ( ! document.querySelector( '.velox-import-src' ) ) {
			return;
		}

		/* Migrate-from-another-plugin importers */
		$$( '.velox-import-run' ).forEach( function ( runBtn ) {
			runBtn.addEventListener( 'click', function () {
				var card = runBtn.closest( '.velox-import-src' );
				if ( ! card ) { return; }
				var source = card.getAttribute( 'data-source' );
				var out = card.querySelector( '.velox-import-result' );
				runBtn.disabled = true;
				var label = runBtn.textContent;
				runBtn.textContent = 'Importing…';
				api( 'velox_import', { source: source } )
					.then( function ( r ) {
						out.hidden = false;
						if ( ! r || ! r.ok ) {
							out.className = 'velox-import-result is-err';
							out.textContent = ( r && r.message ) || 'Nothing to import.';
							runBtn.disabled = false; runBtn.textContent = label;
							return;
						}
						var html = '<strong>' + escapeHtml( r.message ) + '</strong>';
						if ( r.imported && r.imported.length ) {
							html += '<ul>' + r.imported.map( function ( i ) { return '<li>' + escapeHtml( i ) + '</li>'; } ).join( '' ) + '</ul>';
						}
						if ( r.note ) { html += '<em>' + escapeHtml( r.note ) + '</em>'; }
						out.className = 'velox-import-result is-ok';
						out.innerHTML = html;
						runBtn.textContent = 'Imported';
						toast( 'Imported from ' + source + '.', 'success' );
					} )
					.catch( function ( e ) {
						out.hidden = false;
						out.className = 'velox-import-result is-err';
						out.textContent = e.message;
						runBtn.disabled = false; runBtn.textContent = label;
					} );
			} );
		} );

		// Auto-save: every setting saves the instant you change it — no Save button.
		var sroot   = document.querySelector( '.velox-main' ) || document;
		var atimers = {};
		function autoSaveEl( el ) {
			var key = el.getAttribute( 'data-setting' );
			if ( ! key ) { return; }
			var val = ( 'checkbox' === el.type ) ? ( el.checked ? 1 : 0 ) : el.value;
			var payload = {};
			payload[ key ] = val;
			api( 'save_settings', payload )
				.then( flashSaved )
				.catch( function ( e ) { toast( ( e && e.message ) || 'Could not save', 'error' ); } );
		}
		sroot.addEventListener( 'change', function ( e ) {
			var el = e.target.closest ? e.target.closest( '[data-setting]' ) : null;
			if ( el ) { autoSaveEl( el ); }
		} );
		sroot.addEventListener( 'input', function ( e ) {
			var el = e.target.closest ? e.target.closest( '[data-setting]' ) : null;
			if ( ! el ) { return; }
			var t = el.tagName;
			if ( 'TEXTAREA' !== t && ! ( 'INPUT' === t && /^(text|number|url|email|search|password)$/.test( el.type ) ) ) { return; }
			var key = el.getAttribute( 'data-setting' );
			clearTimeout( atimers[ key ] );
			atimers[ key ] = setTimeout( function () { autoSaveEl( el ); }, 700 );
		} );

		/* Quick-setup presets */
		[ 'safe', 'aggressive' ].forEach( function ( name ) {
			var pb = $( '#velox-preset-' + name );
			if ( ! pb ) {
				return;
			}
			pb.addEventListener( 'click', function () {
				if ( name === 'aggressive' && ! confirm( 'Apply the aggressive preset? This enables async CSS, unused-CSS removal, JS delay and bloat removal. Test your site afterwards.' ) ) {
					return;
				}
				pb.disabled = true;
				pb.textContent = 'Applying…';
				api( 'apply_preset', { preset: name } )
					.then( function ( d ) {
						toast( ( d && d.message ) || 'Preset applied.' );
						setTimeout( function () { location.reload(); }, 600 );
					} )
					.catch( function ( e ) {
						toast( e.message, 'error' );
						pb.disabled = false;
						pb.textContent = name === 'safe' ? 'Apply safe defaults' : 'Apply aggressive preset';
					} );
			} );
		} );

		/* Import / Export */
		var box = $( '#velox-import-box' );
		var importActions = $( '#velox-import-actions' );
		var exportBtn = $( '#velox-export' );
		var importOpen = $( '#velox-import-open' );
		if ( exportBtn && box ) {
			exportBtn.addEventListener( 'click', function () {
				api( 'export_settings', {} ).then( function ( d ) {
					box.hidden = false;
					if ( importActions ) {
						importActions.hidden = true;
					}
					box.value = ( d && d.json ) || '';
					box.select();
					toast( 'Settings exported — copy the JSON.' );
				} ).catch( function ( e ) {
					toast( e.message, 'error' );
				} );
			} );
		}
		if ( importOpen && box ) {
			importOpen.addEventListener( 'click', function () {
				box.hidden = false;
				box.value = '';
				box.placeholder = 'Paste a Velox settings JSON here…';
				box.focus();
				if ( importActions ) {
					importActions.hidden = false;
				}
			} );
		}
		var importApply = $( '#velox-import-apply' );
		if ( importApply && box ) {
			importApply.addEventListener( 'click', function () {
				importApply.disabled = true;
				api( 'import_settings', { json: box.value } )
					.then( function ( d ) {
						toast( ( d && d.message ) || 'Imported.' );
						setTimeout( function () {
							window.location.reload();
						}, 700 );
					} )
					.catch( function ( e ) {
						toast( e.message, 'error' );
						importApply.disabled = false;
					} );
			} );
		}
		var importCancel = $( '#velox-import-cancel' );
		if ( importCancel && box ) {
			importCancel.addEventListener( 'click', function () {
				box.hidden = true;
				if ( importActions ) {
					importActions.hidden = true;
				}
			} );
		}
	}

	/* ----------------------------------------------------------------
	 * Boot
	 * ------------------------------------------------------------- */

	/* ----------------------------------------------------------------
	 * Image library (Images tab) — filter, browse, live + bulk rename,
	 * browser-persisted draft names, lightbox preview.
	 * ------------------------------------------------------------- */

	var PENDING_KEY = 'veloxPendingNames';

	function loadPending() {
		try {
			return JSON.parse( window.localStorage.getItem( PENDING_KEY ) || '{}' ) || {};
		} catch ( e ) {
			return {};
		}
	}
	function savePending( map ) {
		try {
			window.localStorage.setItem( PENDING_KEY, JSON.stringify( map ) );
		} catch ( e ) {}
	}

	function typeLabel( mime ) {
		var m = {
			'image/jpeg': 'JPG',
			'image/png': 'PNG',
			'image/webp': 'WEBP',
			'image/gif': 'GIF',
			'image/svg+xml': 'SVG',
			'image/avif': 'AVIF',
		};
		if ( m[ mime ] ) {
			return m[ mime ];
		}
		return ( mime || '' ).split( '/' ).pop().toUpperCase().slice( 0, 5 );
	}
	function splitName( filename ) {
		var i = filename.lastIndexOf( '.' );
		if ( i <= 0 ) {
			return { base: filename, ext: '' };
		}
		return { base: filename.slice( 0, i ), ext: filename.slice( i ) };
	}

	function initLibrary() {
		var grid = $( '#velox-lib-grid' );
		if ( ! grid ) {
			return;
		}

		var state = { page: 1, pages: 1, search: '', filter: 'all' };
		var prev = $( '#velox-lib-prev' );
		var next = $( '#velox-lib-next' );
		var pageInfo = $( '#velox-lib-pageinfo' );
		var searchEl = $( '#velox-lib-search' );
		var chips = $( '#velox-lib-filters' );
		var applyAll = $( '#velox-lib-apply-all' );
		var bulkBtn = $( '#velox-lib-bulk' );

		/* lightbox */
		var lightbox = $( '#velox-lightbox' );
		var lbImg = $( '#velox-lightbox-img' );
		var lbMeta = $( '#velox-lightbox-meta' );
		var lbClose = $( '#velox-lightbox-close' );
		function openLightbox( src, meta ) {
			if ( ! lightbox ) {
				return;
			}
			lbImg.src = src;
			lbMeta.textContent = meta || '';
			lightbox.hidden = false;
			lightbox.classList.add( 'is-open' );
		}
		function closeLightbox() {
			if ( lightbox ) {
				lightbox.classList.remove( 'is-open' );
				lightbox.hidden = true;
				lbImg.src = '';
			}
		}
		if ( lbClose ) {
			lbClose.addEventListener( 'click', closeLightbox );
		}
		if ( lightbox ) {
			lightbox.addEventListener( 'click', function ( e ) {
				if ( e.target === lightbox ) {
					closeLightbox();
				}
			} );
		}
		document.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Escape' ) {
				closeLightbox();
			}
		} );

		function refreshApplyAll() {
			if ( ! applyAll ) {
				return;
			}
			var n = Object.keys( loadPending() ).length;
			applyAll.hidden = n === 0;
			applyAll.textContent = 'Apply all names (' + n + ')';
		}

		function load() {
			grid.innerHTML = '<div class="velox-loading">Loading images…</div>';
			api( 'list_media', { page: state.page, search: state.search, type: state.filter } )
				.then( function ( d ) {
					state.pages = d.total_pages || 1;
					render( d.items || [] );
					if ( pageInfo ) {
						pageInfo.textContent = 'Page ' + d.page + ' / ' + state.pages + ' · ' + d.total + ' images';
					}
					if ( prev ) {
						prev.disabled = d.page <= 1;
					}
					if ( next ) {
						next.disabled = d.page >= state.pages;
					}
					refreshApplyAll();
				} )
				.catch( function ( e ) {
					grid.innerHTML = '<div class="velox-loading">' + escapeHtml( e.message ) + '</div>';
				} );
		}

		function pctSaved( before, after ) {
			if ( ! before || ! after ) {
				return 0;
			}
			return Math.max( 0, Math.round( ( 1 - after / before ) * 100 ) );
		}

		function savingHtml( it ) {
			if ( it.webp && it.webp_bytes > 0 && it.orig_bytes > 0 ) {
				var p = pctSaved( it.orig_bytes, it.webp_bytes );
				return (
					'<div class="velox-lib-saving is-done">' +
						'<span class="velox-lib-before">' + bytes( it.orig_bytes ) + '</span>' +
						'<span class="velox-lib-arrow">→</span>' +
						'<span class="velox-lib-after">' + bytes( it.webp_bytes ) + '</span>' +
						'<span class="velox-lib-pct">−' + p + '%</span>' +
					'</div>'
				);
			}
			return '<div class="velox-lib-saving" data-estimate="' + it.id + '"><span class="velox-lib-est-wait">Estimating WebP…</span></div>';
		}

		function runEstimates() {
			var slots = $$( '.velox-lib-saving[data-estimate]', grid );
			var i = 0;
			function nextOne() {
				if ( i >= slots.length ) {
					return;
				}
				var el = slots[ i++ ];
				var id = el.getAttribute( 'data-estimate' );
				api( 'estimate_webp', { id: id } )
					.then( function ( d ) {
						if ( d && d.webp > 0 ) {
							var p = pctSaved( d.original, d.webp );
							el.innerHTML =
								'<span class="velox-lib-before">' + bytes( d.original ) + '</span>' +
								'<span class="velox-lib-arrow">→</span>' +
								'<span class="velox-lib-after">≈ ' + bytes( d.webp ) + '</span>' +
								'<span class="velox-lib-pct">−' + p + '%</span>';
							el.classList.add( 'is-est' );
						} else {
							el.innerHTML = '<span class="velox-lib-est-wait">—</span>';
						}
					} )
					.catch( function () {
						el.innerHTML = '<span class="velox-lib-est-wait">—</span>';
					} )
					.then( function () {
						setTimeout( nextOne, 120 );
					} );
			}
			nextOne();
		}

		function render( items ) {
			if ( ! items.length ) {
				grid.innerHTML = '<div class="velox-loading">No images match this filter.</div>';
				return;
			}
			var pending = loadPending();
			grid.innerHTML = items.map( function ( it ) {
				var parts = splitName( it.filename || '' );
				var draft = pending[ it.id ];
				var value = ( draft !== undefined ) ? draft : parts.base;
				var dirty = ( draft !== undefined && draft !== parts.base ) ? ' is-dirty' : '';
				var dims = ( it.width && it.height ) ? ( it.width + ' × ' + it.height ) : '—';
				return (
					'<div class="velox-lib-card" data-id="' + it.id + '">' +
					'<div class="velox-lib-thumb" data-full="' + escapeHtml( it.full || it.thumb ) + '" ' +
						'data-meta="' + escapeHtml( ( it.filename || '' ) + '  ·  ' + dims + '  ·  ' + bytes( it.bytes ) ) + '">' +
						'<img src="' + escapeHtml( it.thumb || it.full ) + '" loading="lazy" alt="">' +
						'<div class="velox-lib-badges">' +
							( ( /webp/i.test( it.mime || '' ) || it.webp )
								? '<span class="velox-lib-badge velox-lib-badge--webp">WebP</span>'
								: '<span class="velox-lib-badge">' + escapeHtml( typeLabel( it.mime ) ) + '</span>' ) +
						'</div>' +
					'</div>' +
					'<div class="velox-lib-body">' +
						'<div class="velox-lib-meta"><span>' + dims + '</span><span>' + bytes( it.bytes ) + '</span></div>' +
						savingHtml( it ) +
						( ( /webp/i.test( it.mime || '' ) || it.webp )
							? ''
							: '<button class="velox-btn velox-btn--ghost velox-lib-convert" type="button">Convert to WebP</button>' ) +
						'<div class="velox-lib-rename">' +
							'<input type="text" data-id="' + it.id + '" data-orig="' + escapeHtml( parts.base ) + '" ' +
								'value="' + escapeHtml( value ) + '" class="' + dirty.trim() + '" spellcheck="false">' +
							'<span class="velox-lib-ext">' + escapeHtml( parts.ext ) + '</span>' +
							'<button class="velox-btn velox-btn--primary velox-lib-apply">Apply</button>' +
						'</div>' +
					'</div>' +
					'</div>'
				);
			} ).join( '' );
			runEstimates();
		}

		/* open lightbox */
		grid.addEventListener( 'click', function ( e ) {
			var thumb = e.target.closest( '.velox-lib-thumb' );
			if ( thumb ) {
				openLightbox( thumb.getAttribute( 'data-full' ), thumb.getAttribute( 'data-meta' ) );
				return;
			}
			var applyBtn = e.target.closest( '.velox-lib-apply' );
			if ( applyBtn ) {
				var card = applyBtn.closest( '.velox-lib-card' );
				applySingle( card, applyBtn );
				return;
			}
			var convertBtn = e.target.closest( '.velox-lib-convert' );
			if ( convertBtn ) {
				convertSingle( convertBtn.closest( '.velox-lib-card' ), convertBtn );
			}
		} );

		/* persist typed names as the user types */
		grid.addEventListener( 'input', function ( e ) {
			var inp = e.target.closest( '.velox-lib-rename input' );
			if ( ! inp ) {
				return;
			}
			var id = inp.getAttribute( 'data-id' );
			var orig = inp.getAttribute( 'data-orig' );
			var pending = loadPending();
			if ( inp.value.trim() === orig || ! inp.value.trim() ) {
				delete pending[ id ];
				inp.classList.remove( 'is-dirty' );
			} else {
				pending[ id ] = inp.value.trim();
				inp.classList.add( 'is-dirty' );
			}
			savePending( pending );
			refreshApplyAll();
		} );

		function convertSingle( card, btn ) {
			var id = card.getAttribute( 'data-id' );
			if ( ! id ) {
				return;
			}
			btn.disabled = true;
			var orig = btn.textContent;
			btn.textContent = 'Converting…';
			// No quality passed → the endpoint uses your saved WebP quality (same as bulk).
			api( 'convert_one', { id: id } )
				.then( function ( res ) {
					var saved = res && res.original_bytes ? ( res.original_bytes - ( res.webp_bytes || 0 ) ) : 0;
					toast( saved > 0 ? 'Converted · ' + bytes( saved ) + ' saved' : 'Converted to WebP', 'success' );
					document.dispatchEvent( new CustomEvent( 'velox:refresh-stats' ) );
					load(); // re-fetch the grid so this image now shows as WebP, no manual refresh
				} )
				.catch( function ( err ) {
					toast( ( err && err.message ) || 'Conversion failed', 'error' );
					btn.disabled = false;
					btn.textContent = orig;
				} );
		}

		function applySingle( card, btn ) {
			var input = $( '.velox-lib-rename input', card );
			var id = card.getAttribute( 'data-id' );
			var name = input.value.trim();
			if ( ! name ) {
				return;
			}
			btn.disabled = true;
			btn.textContent = '…';
			api( 'rename', { id: id, name: name } )
				.then( function ( res ) {
					var pending = loadPending();
					delete pending[ id ];
					savePending( pending );
					var np = splitName( res.new_name );
					input.value = np.base;
					input.setAttribute( 'data-orig', np.base );
					input.classList.remove( 'is-dirty' );
					toast( '→ ' + res.new_name + ' · ' + res.refs_updated + ' refs fixed', 'success' );
					refreshApplyAll();
				} )
				.catch( function ( err ) {
					toast( err.message, 'error' );
				} )
				.then( function () {
					btn.disabled = false;
					btn.textContent = 'Apply';
				} );
		}

		/* apply every pending draft name at once */
		if ( applyAll ) {
			applyAll.addEventListener( 'click', function () {
				var pending = loadPending();
				var ids = Object.keys( pending );
				if ( ! ids.length ) {
					return;
				}
				applyAll.disabled = true;
				var done = 0,
					failed = 0;
				function step() {
					if ( ! ids.length ) {
						savePending( loadPending() );
						applyAll.disabled = false;
						toast( 'Renamed ' + done + ( failed ? ' · ' + failed + ' failed' : '' ), failed ? 'error' : 'success' );
						load();
						return;
					}
					var id = ids.shift();
					api( 'rename', { id: id, name: pending[ id ] } )
						.then( function () {
							done++;
							var p = loadPending();
							delete p[ id ];
							savePending( p );
						} )
						.catch( function () {
							failed++;
						} )
						.then( function () {
							applyAll.textContent = 'Applying… ' + done;
							step();
						} );
				}
				step();
			} );
		}

		/* bulk find & replace across the loaded names (fills inputs, you review then Apply all) */
		if ( bulkBtn ) {
			bulkBtn.addEventListener( 'click', function () {
				var existing = $( '#velox-bulk-bar' );
				if ( existing ) {
					existing.remove();
					bulkBtn.classList.remove( 'is-active' );
					return;
				}
				bulkBtn.classList.add( 'is-active' );
				var bar = document.createElement( 'div' );
				bar.className = 'velox-bulk-bar';
				bar.id = 'velox-bulk-bar';
				bar.innerHTML =
					'<span class="velox-bulk-label">Find &amp; replace in names:</span>' +
					'<input type="text" id="velox-bulk-find" placeholder="find (e.g. IMG_)">' +
					'<input type="text" id="velox-bulk-replace" placeholder="replace with">' +
					'<button class="velox-btn velox-btn--ghost" id="velox-bulk-fill">Fill names</button>' +
					'<span class="velox-hint">Fills the inputs below — review, then “Apply all names”.</span>';
				grid.parentNode.insertBefore( bar, grid );
				$( '#velox-bulk-fill', bar ).addEventListener( 'click', function () {
					var find = $( '#velox-bulk-find' ).value;
					var repl = $( '#velox-bulk-replace' ).value;
					if ( ! find ) {
						return;
					}
					var pending = loadPending();
					$$( '.velox-lib-rename input', grid ).forEach( function ( inp ) {
						var nv = inp.value.split( find ).join( repl );
						if ( nv !== inp.value ) {
							inp.value = nv;
							var id = inp.getAttribute( 'data-id' );
							var orig = inp.getAttribute( 'data-orig' );
							if ( nv.trim() && nv.trim() !== orig ) {
								pending[ id ] = nv.trim();
								inp.classList.add( 'is-dirty' );
							}
						}
					} );
					savePending( pending );
					refreshApplyAll();
					toast( 'Names filled — review and Apply all.' );
				} );
			} );
		}

		/* filters */
		if ( chips ) {
			chips.addEventListener( 'click', function ( e ) {
				var chip = e.target.closest( '.velox-chip' );
				if ( ! chip ) {
					return;
				}
				$$( '.velox-chip', chips ).forEach( function ( c ) {
					c.classList.toggle( 'is-active', c === chip );
				} );
				state.filter = chip.getAttribute( 'data-filter' );
				state.page = 1;
				load();
			} );
		}

		if ( searchEl ) {
			searchEl.addEventListener( 'input', debounce( function () {
				state.search = searchEl.value.trim();
				state.page = 1;
				load();
			}, 350 ) );
		}
		if ( prev ) {
			prev.addEventListener( 'click', function () {
				if ( state.page > 1 ) {
					state.page--;
					load();
				}
			} );
		}
		if ( next ) {
			next.addEventListener( 'click', function () {
				if ( state.page < state.pages ) {
					state.page++;
					load();
				}
			} );
		}

		load();
	}

	function initWizard() {
		var overlay = $( '#velox-wizard' );
		if ( ! overlay ) { return; }

		var picked = '';           // chosen builder id
		var pickedLabel = '';
		var path = 'auto';         // auto | manual
		var plan = null;           // server plan for review step
		var STEPS = [ 'builder', 'path', 'review', 'done' ];

		function show( step ) {
			$$( '.velox-wizard-step', overlay ).forEach( function ( s ) {
				s.hidden = s.getAttribute( 'data-step' ) !== step;
			} );
			$$( '.velox-wiz-dot', overlay ).forEach( function ( d ) {
				var di = STEPS.indexOf( d.getAttribute( 'data-dot' ) );
				var ci = STEPS.indexOf( step );
				d.classList.toggle( 'is-on', di <= ci );
			} );
		}
		function open( step ) { overlay.hidden = false; show( step || 'builder' ); }
		function close() { overlay.hidden = true; }
		function dismiss() { api( 'wizard_dismiss', {} ).catch( function () {} ); close(); }
		function on( id, ev, fn ) { var el = $( id ); if ( el ) { el.addEventListener( ev, fn ); } }
		function esc( s ) { return ( s == null ? '' : String( s ) ).replace( /[&<>"]/g, function ( c ) { return { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;' }[ c ]; } ); }

		if ( '1' === overlay.getAttribute( 'data-autoopen' ) ) { open( 'builder' ); }
		on( '#velox-open-wizard', 'click', function ( e ) { e.preventDefault(); open( 'builder' ); } );
		on( '#velox-wizard-close', 'click', dismiss );
		on( '#velox-wizard-skip', 'click', dismiss );

		/* ---- Step 1: builder grid ---- */
		function selectBuilder( btn ) {
			$$( '.velox-wiz-builder', overlay ).forEach( function ( b ) { b.classList.toggle( 'is-selected', b === btn ); } );
			picked = btn.getAttribute( 'data-builder' );
			pickedLabel = ( btn.querySelector( '.velox-wiz-builder-name' ) || {} ).textContent || picked;
			var next = $( '#velox-wiz-to-path' ); if ( next ) { next.disabled = false; }
		}
		$$( '.velox-wiz-builder', overlay ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () { selectBuilder( btn ); } );
		} );
		on( '#velox-wiz-detect', 'click', function ( e ) {
			e.preventDefault();
			var link = this; link.textContent = 'Detecting…';
			api( 'builder_detect', {} )
				.then( function ( d ) {
					var btn = overlay.querySelector( '.velox-wiz-builder[data-builder="' + d.builder + '"]' );
					if ( btn ) { selectBuilder( btn ); btn.scrollIntoView( { block: 'nearest' } ); }
					toast( d.is_default ? 'No builder detected — picked the safe default.' : ( 'Detected ' + d.label + '.' ) );
				} )
				.catch( function ( e2 ) { toast( e2.message, 'error' ); } )
				.then( function () { link.textContent = 'Detect it for me →'; } );
		} );
		on( '#velox-wiz-to-path', 'click', function () {
			if ( ! picked ) { return; }
			var bl = $( '#velox-wiz-blabel' ); if ( bl ) { bl.textContent = pickedLabel; }
			show( 'path' );
		} );

		/* ---- Step 2: path ---- */
		$$( '.velox-wiz-path', overlay ).forEach( function ( p ) {
			p.addEventListener( 'click', function () {
				$$( '.velox-wiz-path', overlay ).forEach( function ( x ) { x.classList.toggle( 'is-selected', x === p ); } );
				path = p.getAttribute( 'data-path' );
			} );
		} );
		on( '#velox-wiz-back-builder', 'click', function () { show( 'builder' ); } );
		on( '#velox-wiz-path-next', 'click', function () {
			if ( path === 'manual' ) {
				// configure-it-yourself: just record the builder and head to Performance.
				api( 'builder_apply', { builder: picked, keep: JSON.stringify( [] ) } )
					.then( function () { window.location = ( $( '#velox-wizard-toperf' ) || {} ).href || location.href; } )
					.catch( function ( e ) { toast( e.message, 'error' ); } );
				return;
			}
			loadReview();
			show( 'review' );
		} );

		/* ---- Step 3: review ---- */
		function loadReview() {
			var box = $( '#velox-wiz-review' );
			if ( box ) { box.innerHTML = '<p class="velox-hint" style="padding:14px;">Scanning your builder and plugins…</p>'; }
			api( 'builder_plan', { builder: picked } )
				.then( function ( d ) {
					plan = d;
					var rl = $( '#velox-wiz-rlabel' ); if ( rl ) { rl.textContent = d.label; }
					var rn = $( '#velox-wiz-rnote' ); if ( rn ) { rn.textContent = d.note || ''; }
					// advisories
					var adv = $( '#velox-wiz-advisories' );
					if ( adv ) {
						adv.innerHTML = ( d.advisories || [] ).map( function ( a ) {
							return '<div class="velox-wiz-adv velox-wiz-adv--' + esc( a.type ) + '">' + esc( a.text ) + '</div>';
						} ).join( '' );
					}
					// detected plugins
					var pl = $( '#velox-wiz-plugins' );
					if ( pl ) {
						pl.innerHTML = ( d.plugins && d.plugins.length )
							? '<span class="velox-wiz-plugins-l">Detected:</span> ' + d.plugins.map( function ( p ) { return '<span class="velox-wiz-chip">' + esc( p.label ) + '</span>'; } ).join( '' )
							: '';
					}
					// toggleable recommendations
					box.innerHTML = ( d.items || [] ).map( function ( it ) {
						var val = it.is_bool ? ( it.on ? 'On' : 'Off' ) : esc( String( it.value ).replace( /\n/g, ', ' ) );
						return '<label class="velox-wiz-rec">' +
							'<span class="velox-switch"><input type="checkbox" data-key="' + esc( it.key ) + '" checked><span class="velox-switch-track"></span></span>' +
							'<span class="velox-wiz-rec-body"><span class="velox-wiz-rec-t">' + esc( it.label ) + '</span>' +
							'<span class="velox-wiz-rec-d">' + esc( it.note ) + '</span>' +
							'<span class="velox-wiz-rec-v">' + val + '</span></span></label>';
					} ).join( '' );
				} )
				.catch( function ( e ) { if ( box ) { box.innerHTML = '<p class="velox-hint" style="padding:14px;">Couldn\'t scan: ' + esc( e.message ) + '</p>'; } } );
		}
		on( '#velox-wiz-back-path', 'click', function () { show( 'path' ); } );
		on( '#velox-wizard-apply', 'click', function () {
			var btn = this; btn.disabled = true; btn.textContent = 'Applying…';
			var keep = [];
			$$( '#velox-wiz-review input[type="checkbox"]', overlay ).forEach( function ( c ) {
				if ( c.checked ) { keep.push( c.getAttribute( 'data-key' ) ); }
			} );
			api( 'builder_apply', { builder: picked, keep: JSON.stringify( keep ) } )
				.then( function ( d ) {
					var msg = $( '#velox-wizard-donemsg' ); if ( msg ) { msg.textContent = d.message || 'Configured.'; }
					show( 'done' );
				} )
				.catch( function ( e ) { toast( e.message, 'error' ); } )
				.then( function () { btn.disabled = false; btn.textContent = 'Apply selected'; } );
		} );

		/* ---- Step 4: done ---- */
		on( '#velox-wizard-finish', 'click', function () { close(); location.reload(); } );

		/* ---- request a builder ---- */
		on( '#velox-wizard-req-open', 'click', function ( e ) { e.preventDefault(); var r = $( '#velox-wizard-req' ); if ( r ) { r.hidden = false; } } );
		on( '#velox-wizard-req-send', 'click', function () {
			var input = $( '#velox-wizard-req-name' ); var btn = this; btn.disabled = true;
			api( 'builder_request', { name: input ? input.value : '' } )
				.then( function ( d ) { toast( d.message || 'Sent.' ); if ( input ) { input.value = ''; } } )
				.catch( function ( e ) { toast( e.message, 'error' ); } )
				.then( function () { btn.disabled = false; } );
		} );
	}

	function initUtilities() {
		$$( '.velox-util-toggle' ).forEach( function ( box ) {
			function commit( on ) {
				box.disabled = true;
				api( 'util_toggle', { key: box.getAttribute( 'data-key' ), on: on ? '1' : 'false' } )
					.then( function () {
						toast( on ? 'Turned on — added to the sidebar.' : 'Turned off.' );
						setTimeout( function () { location.reload(); }, 450 );
					} )
					.catch( function ( e ) { box.checked = ! on; box.disabled = false; toast( e.message, 'error' ); } );
			}
			box.addEventListener( 'change', function () {
				var on        = box.checked;
				var dangerous = '1' === box.getAttribute( 'data-dangerous' );
				var acked     = '1' === box.getAttribute( 'data-acked' );
				// First time enabling a dangerous tool → confirm. After that, never again.
				if ( on && dangerous && ! acked ) {
					box.checked = false;
					veloxDangerModal( box.getAttribute( 'data-tool' ), function () {
						box.checked = true;
						box.setAttribute( 'data-acked', '1' );
						var p = {};
						p[ box.getAttribute( 'data-key' ) + '_ack' ] = 1;
						api( 'save_settings', p ).catch( function () {} );
						commit( true );
					} );
					return;
				}
				commit( on );
			} );
		} );
		// Tool sub-page Save buttons (Maintenance, Custom login URL, …)
		$$( '.velox-util-save' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var scope = btn.closest( '.velox-tool-form' ) || document;
				btn.disabled = true;
				saveSettings( collectSettings( scope ), 'Saved.' )
					.then( function () { setTimeout( function () { location.reload(); }, 500 ); } )
					.catch( function () {} )
					.then( function () { btn.disabled = false; } );
			} );
		} );
		initUnusedMedia();
		initInstaller();
		initRedirects();
		initActivity();
		initScripts();
		initMaintenance();
		initFileManager();
		initMail();
		initMailBuilder();
		initFieldsEditor();
		initFieldsList();
		initCookies();
		initOctober();
		initBackup();
		initOctoberEditor();
	}

	function initOctoberEditor() {
		var root = $( '#oct-editor' );
		if ( ! root ) { return; }
		var buildId = root.getAttribute( 'data-build' );
		var dlNonce = root.getAttribute( 'data-dlnonce' );
		var ajaxUrl = root.getAttribute( 'data-ajaxurl' );
		var listEl  = $( '#oct-tok-list' );
		var frame   = $( '#oct-preview' );
		var statusEl= $( '#oct-edit-status' );
		var filterEl= $( '#oct-tok-filter' );
		var data    = null;
		var tab     = 'classes';
		var map     = { classes: {}, ids: {} };
		var timer   = null;

		function esc( s ) { return ( s == null ? '' : String( s ) ).replace( /[&<>"]/g, function ( c ) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[ c ]; } ); }
		function reEsc( s ) { return s.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' ); }

		function renameCss( css ) {
			[ [ 'classes', '.' ], [ 'ids', '#' ] ].forEach( function ( pair ) {
				var m = map[ pair[ 0 ] ], sig = pair[ 1 ];
				Object.keys( m ).sort( function ( a, b ) { return b.length - a.length; } ).forEach( function ( oldN ) {
					var newN = m[ oldN ];
					if ( ! newN || newN === oldN ) { return; }
					css = css.replace( new RegExp( reEsc( sig + oldN ) + '(?![\\w-])', 'g' ), sig + newN );
				} );
			} );
			return css;
		}
		function renameHtml( html ) {
			var cm = map.classes;
			html = html.replace( /(\sclass\s*=\s*)("|')(.*?)\2/gi, function ( full, pre, q, val ) {
				var toks = val.split( /\s+/ ).map( function ( t ) { return ( t && cm[ t ] ) ? cm[ t ] : t; } );
				return pre + q + toks.filter( Boolean ).join( ' ' ) + q;
			} );
			Object.keys( map.ids ).forEach( function ( oldN ) {
				var newN = map.ids[ oldN ];
				if ( ! newN || newN === oldN ) { return; }
				html = html.replace( new RegExp( '(\\sid\\s*=\\s*("|\'))' + reEsc( oldN ) + '(\\2)', 'gi' ), '$1' + newN + '$3' );
				html = html.replace( new RegExp( '(["\']|=)#' + reEsc( oldN ) + '(?![\\w-])', 'g' ), '$1#' + newN );
			} );
			return html;
		}
		function renderPreview() {
			if ( ! data ) { return; }
			var html = '<!doctype html><html><head><meta charset="utf-8"><base target="_blank">' +
				'<style>' + renameCss( data.css ) + '</style></head><body>' + renameHtml( data.preview ) + '</body></html>';
			frame.srcdoc = html;
		}
		function schedulePreview() {
			if ( timer ) { clearTimeout( timer ); }
			timer = setTimeout( renderPreview, 350 );
		}

		function renderList() {
			if ( ! data ) { return; }
			var tokens = data[ tab ] || {};
			var filter = ( filterEl.value || '' ).toLowerCase();
			var names  = Object.keys( tokens ).filter( function ( n ) { return ! filter || n.toLowerCase().indexOf( filter ) > -1; } );
			if ( ! names.length ) { listEl.innerHTML = '<p class="velox-hint" style="padding:18px;">No ' + tab + ' found.</p>'; return; }
			var rows = names.map( function ( n ) {
				var cur = map[ tab ][ n ] || '';
				return '<div class="oct-tok-row"><code class="oct-tok-name" title="' + esc( n ) + '">' + esc( n ) + '</code>' +
					'<span class="oct-tok-count">' + tokens[ n ] + '\u00d7</span>' +
					'<span class="oct-tok-arrow">\u2192</span>' +
					'<input class="velox-input velox-input--sm oct-tok-input" data-token="' + esc( n ) + '" value="' + esc( cur ) + '" placeholder="' + esc( n ) + '" spellcheck="false">' +
					'</div>';
			} ).join( '' );
			listEl.innerHTML = rows;
			$$( '.oct-tok-input', listEl ).forEach( function ( inp ) {
				inp.addEventListener( 'input', function () {
					var t = inp.getAttribute( 'data-token' );
					var v = inp.value.trim();
					if ( v ) { map[ tab ][ t ] = v; } else { delete map[ tab ][ t ]; }
					updateCount();
					schedulePreview();
				} );
			} );
		}
		function updateCount() {
			var n = Object.keys( map.classes ).length + Object.keys( map.ids ).length;
			statusEl.textContent = n ? ( n + ' rename' + ( n === 1 ? '' : 's' ) + ' pending' ) : '';
		}

		$$( '.oct-tok-tab', root ).forEach( function ( b ) {
			b.addEventListener( 'click', function () {
				$$( '.oct-tok-tab', root ).forEach( function ( x ) { x.classList.remove( 'is-active' ); } );
				b.classList.add( 'is-active' );
				tab = b.getAttribute( 'data-tab' );
				renderList();
			} );
		} );
		if ( filterEl ) { filterEl.addEventListener( 'input', renderList ); }

		var applyBtn = $( '#oct-apply' );
		if ( applyBtn ) {
			applyBtn.addEventListener( 'click', function () {
				if ( ! Object.keys( map.classes ).length && ! Object.keys( map.ids ).length ) {
					toast( 'No renames yet — edit some names first.', 'error' ); return;
				}
				applyBtn.disabled = true; applyBtn.textContent = 'Building…';
				api( 'october_apply_renames', { id: buildId, map: JSON.stringify( map ) } )
					.then( function ( d ) {
						toast( 'Renamed v' + d.version + ' (' + d.renamed + ' names). Downloading…', 'success' );
						window.location = ajaxUrl + '?action=velox_october_download&id=' + d.id + '&_wpnonce=' + dlNonce;
						setTimeout( function () { applyBtn.disabled = false; applyBtn.textContent = 'Download renamed'; }, 1500 );
					} )
					.catch( function ( e ) {
						toast( e.message, 'error' ); applyBtn.disabled = false; applyBtn.textContent = 'Download renamed';
					} );
			} );
		}

		listEl.innerHTML = '<p class="velox-hint" style="padding:18px;">Loading…</p>';
		api( 'october_edit_payload', { id: buildId } )
			.then( function ( d ) {
				data = d;
				renderList();
				renderPreview();
			} )
			.catch( function ( e ) {
				listEl.innerHTML = '<p class="velox-hint" style="padding:18px;">Could not load: ' + esc( e.message ) + '</p>';
			} );
	}

	/* ---- Field-type registry: categories, icons, descriptions (ACF-style picker) ---- */
	var VFX_CATS = [
		{ id: 'basic', label: 'Basic' },
		{ id: 'content', label: 'Content' },
		{ id: 'choice', label: 'Choice' },
		{ id: 'relational', label: 'Relational' },
		{ id: 'picker', label: 'Pickers' },
		{ id: 'layout', label: 'Layout' }
	];
	var VFX_ICONS = {
		text: '<path d="M4 7V5h16v2M9 19h6M12 5v14"/>',
		lines: '<path d="M4 6h16M4 12h16M4 18h10"/>',
		hash: '<path d="M5 9h14M5 15h14M10 4 8 20M16 4l-2 16"/>',
		slider: '<path d="M4 12h16"/><circle cx="9" cy="12" r="2.5"/>',
		at: '<circle cx="12" cy="12" r="4"/><path d="M16 12v1.5a2.5 2.5 0 0 0 5 0V12a9 9 0 1 0-3.5 7.1"/>',
		link: '<path d="M10 13a5 5 0 0 0 7 0l2-2a5 5 0 0 0-7-7l-1 1"/><path d="M14 11a5 5 0 0 0-7 0l-2 2a5 5 0 0 0 7 7l1-1"/>',
		image: '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="9" r="1.5"/><path d="m3 17 5-4 4 3 3-2 6 4"/>',
		images: '<rect x="7" y="3" width="14" height="14" rx="2"/><path d="M3 7v12a2 2 0 0 0 2 2h12"/><circle cx="12" cy="8" r="1.4"/>',
		file: '<path d="M14 3v5h5"/><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>',
		edit: '<path d="M4 20h16"/><path d="M14 6l4 4L8 20H4v-4z"/>',
		play: '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m10 9 5 3-5 3z"/>',
		chevrons: '<rect x="4" y="5" width="16" height="14" rx="2"/><path d="m9 11 3 3 3-3"/>',
		checksquare: '<rect x="4" y="4" width="16" height="16" rx="2"/><path d="m8 12 3 3 5-6"/>',
		radio: '<circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="3" fill="currentColor"/>',
		buttons: '<rect x="3" y="7" width="7" height="10" rx="2"/><rect x="14" y="7" width="7" height="10" rx="2"/>',
		toggle: '<rect x="3" y="7" width="18" height="10" rx="5"/><circle cx="16" cy="12" r="3" fill="currentColor"/>',
		post: '<rect x="4" y="4" width="16" height="16" rx="2"/><path d="M8 9h8M8 13h8M8 17h5"/>',
		posts: '<rect x="3" y="5" width="11" height="14" rx="2"/><path d="M17 7h4v12a2 2 0 0 1-2 2H8"/>',
		tag: '<path d="M3 11V5a2 2 0 0 1 2-2h6l9 9-8 8z"/><circle cx="8" cy="8" r="1.4" fill="currentColor"/>',
		user: '<circle cx="12" cy="8" r="4"/><path d="M4 20a8 8 0 0 1 16 0"/>',
		calendar: '<rect x="4" y="5" width="16" height="16" rx="2"/><path d="M4 10h16M9 3v4M15 3v4"/>',
		swatch: '<circle cx="12" cy="12" r="8"/><circle cx="9" cy="10" r="1.2" fill="currentColor"/><circle cx="15" cy="10" r="1.2" fill="currentColor"/><circle cx="12" cy="15" r="1.2" fill="currentColor"/>',
		group: '<rect x="3" y="3" width="8" height="8" rx="1.5"/><rect x="13" y="3" width="8" height="8" rx="1.5"/><rect x="3" y="13" width="8" height="8" rx="1.5"/><rect x="13" y="13" width="8" height="8" rx="1.5"/>',
		rows: '<rect x="4" y="4" width="16" height="5" rx="1.5"/><rect x="4" y="11" width="16" height="5" rx="1.5"/><path d="M8 19h8"/>',
		layers: '<path d="m12 3 9 5-9 5-9-5z"/><path d="m3 13 9 5 9-5"/>',
		lock: '<rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>',
		clock: '<circle cx="12" cy="12" r="8"/><path d="M12 8v4l3 2"/>',
		message: '<path d="M21 12a8 8 0 0 1-11.3 7.3L4 21l1.7-5.7A8 8 0 1 1 21 12z"/>'
	};
	function vfxIcon( name ) {
		var p = VFX_ICONS[ name ] || VFX_ICONS.text;
		return '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">' + p + '</svg>';
	}
	var VFX_META = {
		text: { cat: 'basic', icon: 'text', label: 'Text', desc: 'A single line of text.' },
		textarea: { cat: 'basic', icon: 'lines', label: 'Text Area', desc: 'Multiple lines of plain text.' },
		number: { cat: 'basic', icon: 'hash', label: 'Number', desc: 'A numeric value.' },
		range: { cat: 'basic', icon: 'slider', label: 'Range', desc: 'A slider between min and max.' },
		email: { cat: 'basic', icon: 'at', label: 'Email', desc: 'An email address.' },
		url: { cat: 'basic', icon: 'link', label: 'URL', desc: 'A web address.' },
		password: { cat: 'basic', icon: 'lock', label: 'Password', desc: 'A masked password input.' },
		image: { cat: 'content', icon: 'image', label: 'Image', desc: 'Pick an image from the media library.' },
		gallery: { cat: 'content', icon: 'images', label: 'Gallery', desc: 'A collection of images.' },
		file: { cat: 'content', icon: 'file', label: 'File', desc: 'Pick any file from the library.' },
		wysiwyg: { cat: 'content', icon: 'edit', label: 'WYSIWYG', desc: 'A rich, visual text editor.' },
		oembed: { cat: 'content', icon: 'play', label: 'oEmbed', desc: 'Embed a video or media URL.' },
		select: { cat: 'choice', icon: 'chevrons', label: 'Select', desc: 'A dropdown of choices.' },
		checkbox: { cat: 'choice', icon: 'checksquare', label: 'Checkbox', desc: 'Tick one or more choices.' },
		radio: { cat: 'choice', icon: 'radio', label: 'Radio', desc: 'Pick one of several choices.' },
		button_group: { cat: 'choice', icon: 'buttons', label: 'Button Group', desc: 'Pick one, shown as buttons.' },
		truefalse: { cat: 'choice', icon: 'toggle', label: 'True / False', desc: 'A yes / no toggle.' },
		link: { cat: 'relational', icon: 'link', label: 'Link', desc: 'A link: URL, text and target.' },
		post_object: { cat: 'relational', icon: 'post', label: 'Post Object', desc: 'Select a post or page.' },
		page_link: { cat: 'relational', icon: 'link', label: 'Page Link', desc: 'Select a post; use its permalink.' },
		relationship: { cat: 'relational', icon: 'posts', label: 'Relationship', desc: 'Select multiple posts/pages.' },
		taxonomy: { cat: 'relational', icon: 'tag', label: 'Taxonomy', desc: 'Select a taxonomy term.' },
		user: { cat: 'relational', icon: 'user', label: 'User', desc: 'Select a user.' },
		date: { cat: 'picker', icon: 'calendar', label: 'Date Picker', desc: 'Pick a date.' },
		datetime: { cat: 'picker', icon: 'calendar', label: 'Date & Time', desc: 'Pick a date and time.' },
		time: { cat: 'picker', icon: 'clock', label: 'Time Picker', desc: 'Pick a time.' },
		color: { cat: 'picker', icon: 'swatch', label: 'Color Picker', desc: 'Pick a color.' },
		message: { cat: 'layout', icon: 'message', label: 'Message', desc: 'Show a note to editors (no value).' },
		group: { cat: 'layout', icon: 'group', label: 'Group', desc: 'Bundle sub-fields into one block.' },
		repeater: { cat: 'layout', icon: 'rows', label: 'Repeater', desc: 'Repeatable rows of sub-fields.' },
		flexible: { cat: 'layout', icon: 'layers', label: 'Flexible Content', desc: 'Stack rows of chosen layouts.' }
	};
	function vfxMeta( t ) { return VFX_META[ t ] || { cat: 'basic', icon: 'text', label: t, desc: '' }; }

	function initFieldsEditor() {
		var root = $( '#vfg-editor' );
		if ( ! root ) { return; }
		var group, TYPES, PARAMS;
		try { group = JSON.parse( $( '#vfg-data' ).textContent ); } catch ( e ) { return; }
		try { TYPES = JSON.parse( $( '#vfg-types' ).textContent ); } catch ( e2 ) { TYPES = {}; }
		try { PARAMS = JSON.parse( $( '#vfg-params' ).textContent ); } catch ( e3 ) { PARAMS = {}; }
		var PARAM_CHOICES = {};
		try { PARAM_CHOICES = JSON.parse( $( '#vfg-paramchoices' ).textContent ); } catch ( e4 ) { PARAM_CHOICES = {}; }
		group.fields = group.fields || [];
		group.location = group.location || [ [ { param: 'post_type', operator: 'is', value: 'post' } ] ];
		group.presentation = group.presentation || { label_placement: 'top', position: 'normal', order: 0 };

		var fieldsWrap = $( '#vfg-fields' );
		var locWrap    = $( '#vfg-location' );
		var openIdx    = group.fields.length ? 0 : -1;

		function slugify( s ) { return ( s || 'field' ).toLowerCase().replace( /[^a-z0-9]+/g, '_' ).replace( /^_|_$/g, '' ) || 'field'; }
		function reName() {
			var used = {};
			group.fields.forEach( function ( f ) {
				var base = ( f.name && /^[a-z0-9_]+$/.test( f.name ) ) ? f.name : slugify( f.label );
				var n = base, i = 2;
				while ( used[ n ] ) { n = base + '_' + ( i++ ); }
				used[ n ] = 1; f.name = n;
			} );
		}
		function typeIcon() {
			return '<svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18"/></svg>';
		}
		function updateSub() {
			var n = group.fields.length;
			var loc = '';
			if ( group.location[0] && group.location[0][0] ) {
				var r = group.location[0][0];
				loc = ' · shows where ' + ( PARAMS[ r.param ] || r.param ) + ' ' + ( r.operator === 'is_not' ? 'is not' : 'is' ) + ' ' + ( r.value || '…' );
			}
			$( '#vfg-sub' ).textContent = n + ' field' + ( n === 1 ? '' : 's' ) + loc;
		}

		function renderFields() {
			reName();
			fieldsWrap.innerHTML = '';
			if ( ! group.fields.length ) {
				fieldsWrap.innerHTML = '<div class="vfg-empty">No fields yet — click “Add field”.</div>';
				updateSub(); return;
			}
			group.fields.forEach( function ( f, i ) {
				var open = i === openIdx;
				var card = document.createElement( 'div' );
				card.className = 'vfg-field' + ( open ? ' is-open' : '' ) + ( f.active === false ? ' is-off' : '' );
				var meta = '<code>' + escapeHtml( f.name || slugify( f.label ) ) + '</code>' + ( f.required ? ' · required' : '' ) + ( f.active === false ? ' · off' : '' );
				var head =
					'<div class="vfg-field-row">' +
						'<span class="vfg-handle" title="Drag to reorder"><svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><circle cx="9" cy="6" r="1.5"/><circle cx="15" cy="6" r="1.5"/><circle cx="9" cy="12" r="1.5"/><circle cx="15" cy="12" r="1.5"/><circle cx="9" cy="18" r="1.5"/><circle cx="15" cy="18" r="1.5"/></svg></span>' +
						'<span class="vfg-type-pill">' + typeIcon() + ' ' + ( TYPES[ f.type ] ? TYPES[ f.type ].label : f.type ) + '</span>' +
						'<span class="vfg-field-main"><span class="vfg-field-label">' + escapeHtml( f.label || 'Untitled' ) + '</span><span class="vfg-field-meta">' + meta + '</span></span>' +
						'<span class="vfg-field-acts">' +
							'<label class="vfg-field-onoff velox-switch" title="Enable or disable this field"><input type="checkbox" data-act="active"' + ( f.active === false ? '' : ' checked' ) + '><span class="velox-switch-track"></span></label>' +
							'<button type="button" class="vfg-iconbtn" data-act="dup" title="Duplicate"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="9" y="9" width="11" height="11" rx="2.5"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/></svg></button>' +
							'<button type="button" class="vfg-iconbtn vfg-del" data-act="del" title="Delete"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"><path d="M4 7h16M10 11v6M14 11v6M5 7l1 13a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2l1-13M9 7V4h6v3"/></svg></button>' +
							'<button type="button" class="vfg-iconbtn" data-act="toggle" title="' + ( open ? 'Collapse' : 'Expand' ) + '"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.7"><path d="' + ( open ? 'M18 15l-6-6-6 6' : 'M6 9l6 6 6-6' ) + '"/></svg></button>' +
						'</span>' +
					'</div>';
				var body = '';
				if ( open ) {
					var hasOpts = TYPES[ f.type ] && TYPES[ f.type ].opts;
					var tMeta = vfxMeta( f.type );
					var generalGrid = '<div class="vfg-fbody-grid">' +
						mini( 'Field label', '<input class="velox-input" data-fk="label" value="' + escapeHtml( f.label || '' ) + '">' ) +
						mini( 'Field name', '<input class="velox-input vfg-mono" data-fk="name" value="' + escapeHtml( f.name || '' ) + '">' ) +
						mini( 'Field type', '<button type="button" class="vfg-typepick" data-typepick><span class="vfg-typepick-ic">' + vfxIcon( tMeta.icon ) + '</span><span class="vfg-typepick-tx">' + escapeHtml( tMeta.label ) + '</span><svg class="vfg-typepick-chev" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M6 9l6 6 6-6"/></svg></button>' ) +
						mini( 'Default value', '<input class="velox-input" data-fk="default" value="' + escapeHtml( f['default'] || '' ) + '">' ) +
						typeSettingsUi( f ) +
						( hasOpts ? minifull( 'Choices (one per line)', '<textarea class="velox-textarea" rows="3" data-fk="options">' + escapeHtml( f.options || '' ) + '</textarea>' ) : '' ) +
						minifull( 'Placeholder', '<input class="velox-input" data-fk="placeholder" value="' + escapeHtml( f.placeholder || '' ) + '">' ) +
						minifull( 'Instructions', '<input class="velox-input" data-fk="instructions" value="' + escapeHtml( f.instructions || '' ) + '" placeholder="Shown to editors below the field">' ) +
						'<label class="vfg-check"><input type="checkbox" data-fk="required"' + ( f.required ? ' checked' : '' ) + '> Required field</label>' +
					'</div>';
					var structural = ( f.type === 'repeater' || f.type === 'group' ) ? subFieldsUi( f ) : ( f.type === 'flexible' ? flexibleUi( f ) : '' );
					var curW = f.wrapper_width ? parseInt( f.wrapper_width, 10 ) : 100;
					var widthOpts = [ 100, 75, 66, 50, 33, 25 ].map( function ( w ) { return '<option value="' + w + '"' + ( curW === w ? ' selected' : '' ) + '>' + w + '%</option>'; } ).join( '' );
					var presGrid = '<div class="vfg-fbody-grid">' +
						mini( 'Field width', '<select class="velox-select" data-fk="wrapper_width">' + widthOpts + '</select>' ) +
						minifull( 'Wrapper CSS class', '<input class="velox-input vfg-mono" data-fk="wrapper_class" value="' + escapeHtml( f.wrapper_class || '' ) + '" placeholder="optional">' ) +
					'</div>';
					body = '<div class="vfg-field-body">' +
						'<div class="vfg-ftabs">' +
							'<button type="button" class="vfg-ftab is-on" data-ftab="general">General</button>' +
							'<button type="button" class="vfg-ftab" data-ftab="presentation">Presentation</button>' +
							'<button type="button" class="vfg-ftab" data-ftab="conditional">Conditional Logic</button>' +
						'</div>' +
						'<div class="vfg-ftab-panel" data-ftab-panel="general">' + generalGrid + structural + '</div>' +
						'<div class="vfg-ftab-panel" data-ftab-panel="presentation" hidden>' + presGrid + '</div>' +
						'<div class="vfg-ftab-panel" data-ftab-panel="conditional" hidden>' + conditionalUi( f, i ) + '</div>' +
					'</div>';
				}
				card.innerHTML = head + body;
				// row click toggles (except buttons + handle)
				card.querySelector( '.vfg-field-row' ).addEventListener( 'click', function ( e ) {
					if ( e.target.closest( '.vfg-iconbtn' ) || e.target.closest( '.vfg-handle' ) || e.target.closest( '.vfg-field-onoff' ) ) { return; }
					openIdx = ( openIdx === i ) ? -1 : i; renderFields();
				} );
				card.querySelectorAll( '.vfg-iconbtn' ).forEach( function ( b ) {
					b.addEventListener( 'click', function ( e ) {
						e.stopPropagation();
						var act = b.getAttribute( 'data-act' );
						if ( act === 'del' ) { group.fields.splice( i, 1 ); if ( openIdx >= group.fields.length ) { openIdx = group.fields.length - 1; } }
						else if ( act === 'dup' ) { var c = JSON.parse( JSON.stringify( f ) ); c.name = ''; group.fields.splice( i + 1, 0, c ); openIdx = i + 1; }
						else if ( act === 'toggle' ) { openIdx = ( openIdx === i ) ? -1 : i; }
						renderFields();
					} );
				} );
				var onoff = card.querySelector( '.vfg-field-onoff input' );
				if ( onoff ) {
					onoff.addEventListener( 'click', function ( e ) { e.stopPropagation(); } );
					onoff.addEventListener( 'change', function () {
						f.active = onoff.checked;
						card.classList.toggle( 'is-off', ! onoff.checked );
						var mEl = card.querySelector( '.vfg-field-meta' );
						if ( mEl ) { mEl.innerHTML = '<code>' + escapeHtml( f.name || slugify( f.label ) ) + '</code>' + ( f.required ? ' · required' : '' ) + ( f.active === false ? ' · off' : '' ); }
						updateSub();
					} );
				}
				// inputs in the body
				var ftabs = card.querySelectorAll( '.vfg-ftab' );
				ftabs.forEach( function ( tb ) {
					tb.addEventListener( 'click', function ( e ) {
						e.stopPropagation();
						var which = tb.getAttribute( 'data-ftab' );
						ftabs.forEach( function ( x ) { x.classList.toggle( 'is-on', x === tb ); } );
						card.querySelectorAll( '.vfg-ftab-panel' ).forEach( function ( pnl ) {
							pnl.hidden = pnl.getAttribute( 'data-ftab-panel' ) !== which;
						} );
					} );
				} );
				var typePickBtn = card.querySelector( '[data-typepick]' );
				if ( typePickBtn ) {
					typePickBtn.addEventListener( 'click', function ( e ) {
						e.stopPropagation();
						openTypeModal( i );
					} );
				}
				card.querySelectorAll( '[data-fk]' ).forEach( function ( el ) {
					var ev = ( el.type === 'checkbox' || el.tagName === 'SELECT' ) ? 'change' : 'input';
					el.addEventListener( ev, function () {
						var k = el.getAttribute( 'data-fk' );
						if ( k === 'name' ) { f._lockName = true; f.name = slugify( el.value ); }
						else { f[ k ] = ( el.type === 'checkbox' ) ? el.checked : el.value; }
						if ( k === 'label' && ! f._lockName ) { f.name = ''; reName(); }
						// Type swaps the whole settings panel → a full re-render is needed.
						if ( k === 'type' ) { renderFields(); return; }
						// Label / name / required update the card header IN PLACE so the
						// input is never torn out from under you mid-type (focus stays put).
						var lblEl = card.querySelector( '.vfg-field-label' );
						if ( lblEl ) { lblEl.textContent = f.label || 'Untitled'; }
						var metaEl = card.querySelector( '.vfg-field-meta' );
						if ( metaEl ) { metaEl.innerHTML = '<code>' + escapeHtml( f.name || slugify( f.label ) ) + '</code>' + ( f.required ? ' · required' : '' ); }
						if ( k === 'label' && ! f._lockName ) {
							var nameIn = card.querySelector( '[data-fk="name"]' );
							if ( nameIn && document.activeElement !== nameIn ) { nameIn.value = f.name; }
						}
						updateSub();
					} );
				} );
				// repeater sub-fields
				f.sub_fields = f.sub_fields || [];
				card.querySelectorAll( '.vfg-sub-in' ).forEach( function ( el ) {
					var ev = el.tagName === 'SELECT' ? 'change' : 'input';
					el.addEventListener( ev, function () {
						var si = parseInt( el.getAttribute( 'data-si' ), 10 );
						var sk = el.getAttribute( 'data-sk' );
						if ( ! f.sub_fields[ si ] ) { return; }
						f.sub_fields[ si ][ sk ] = el.value;
					} );
				} );
				card.querySelectorAll( '.vfg-sub-del' ).forEach( function ( b ) {
					b.addEventListener( 'click', function ( e ) {
						e.stopPropagation();
						f.sub_fields.splice( parseInt( b.getAttribute( 'data-si' ), 10 ), 1 );
						renderFields();
					} );
				} );
				var subAdd = card.querySelector( '.vfg-sub-add' );
				if ( subAdd ) {
					subAdd.addEventListener( 'click', function ( e ) {
						e.stopPropagation();
						f.sub_fields.push( { label: 'Sub field', name: '', type: 'text', options: '' } );
						renderFields();
					} );
				}
				// flexible content layouts
				f.layouts = f.layouts || [];
				card.querySelectorAll( '.vfg-flex-lin' ).forEach( function ( el ) {
					el.addEventListener( 'input', function () {
						var li = parseInt( el.getAttribute( 'data-li' ), 10 );
						if ( f.layouts[ li ] ) { f.layouts[ li ][ el.getAttribute( 'data-lk' ) ] = el.value; }
					} );
				} );
				card.querySelectorAll( '.vfg-flex-in' ).forEach( function ( el ) {
					var ev = el.tagName === 'SELECT' ? 'change' : 'input';
					el.addEventListener( ev, function () {
						var li = parseInt( el.getAttribute( 'data-li' ), 10 );
						var si = parseInt( el.getAttribute( 'data-si' ), 10 );
						if ( f.layouts[ li ] && f.layouts[ li ].sub_fields[ si ] ) { f.layouts[ li ].sub_fields[ si ][ el.getAttribute( 'data-sk' ) ] = el.value; }
					} );
				} );
				card.querySelectorAll( '.vfg-flex-subadd' ).forEach( function ( b ) {
					b.addEventListener( 'click', function ( e ) {
						e.stopPropagation();
						var li = parseInt( b.getAttribute( 'data-li' ), 10 );
						f.layouts[ li ].sub_fields = f.layouts[ li ].sub_fields || [];
						f.layouts[ li ].sub_fields.push( { label: 'Sub field', name: '', type: 'text', options: '' } );
						renderFields();
					} );
				} );
				card.querySelectorAll( '.vfg-flex-subdel' ).forEach( function ( b ) {
					b.addEventListener( 'click', function ( e ) {
						e.stopPropagation();
						var li = parseInt( b.getAttribute( 'data-li' ), 10 );
						var si = parseInt( b.getAttribute( 'data-si' ), 10 );
						f.layouts[ li ].sub_fields.splice( si, 1 );
						renderFields();
					} );
				} );
				card.querySelectorAll( '.vfg-layout-del' ).forEach( function ( b ) {
					b.addEventListener( 'click', function ( e ) {
						e.stopPropagation();
						f.layouts.splice( parseInt( b.getAttribute( 'data-li' ), 10 ), 1 );
						renderFields();
					} );
				} );
				var layAdd = card.querySelector( '.vfg-layout-add' );
				if ( layAdd ) {
					layAdd.addEventListener( 'click', function ( e ) {
						e.stopPropagation();
						f.layouts.push( { name: '', label: 'New layout', sub_fields: [] } );
						renderFields();
					} );
				}
				// conditional logic
				f.conditional = f.conditional || { enabled: false, groups: [] };
				var condEnable = card.querySelector( '.vfg-cond-enable' );
				if ( condEnable ) {
					condEnable.addEventListener( 'change', function () {
						f.conditional.enabled = condEnable.checked;
						if ( condEnable.checked && ( ! f.conditional.groups[0] || ! f.conditional.groups[0].length ) ) {
							f.conditional.groups = [ [ { field: '', operator: '==', value: '' } ] ];
						}
						renderFields();
					} );
				}
				card.querySelectorAll( '.vfg-cond-in' ).forEach( function ( el ) {
					var ev = el.tagName === 'SELECT' ? 'change' : 'input';
					el.addEventListener( ev, function () {
						var ri = parseInt( el.getAttribute( 'data-ri' ), 10 );
						if ( f.conditional.groups[0] && f.conditional.groups[0][ ri ] ) { f.conditional.groups[0][ ri ][ el.getAttribute( 'data-ck' ) ] = el.value; }
					} );
				} );
				card.querySelectorAll( '.vfg-cond-del' ).forEach( function ( b ) {
					b.addEventListener( 'click', function ( e ) {
						e.stopPropagation();
						f.conditional.groups[0].splice( parseInt( b.getAttribute( 'data-ri' ), 10 ), 1 );
						renderFields();
					} );
				} );
				var condAdd = card.querySelector( '.vfg-cond-add' );
				if ( condAdd ) {
					condAdd.addEventListener( 'click', function ( e ) {
						e.stopPropagation();
						f.conditional.groups[0] = f.conditional.groups[0] || [];
						f.conditional.groups[0].push( { field: '', operator: '==', value: '' } );
						renderFields();
					} );
				}
				// drag to reorder
				card.setAttribute( 'draggable', 'true' );
				card.addEventListener( 'dragstart', function () { card._from = i; window.__vfgDrag = i; card.classList.add( 'is-drag' ); } );
				card.addEventListener( 'dragend', function () { card.classList.remove( 'is-drag' ); } );
				card.addEventListener( 'dragover', function ( e ) { e.preventDefault(); } );
				card.addEventListener( 'drop', function ( e ) {
					e.preventDefault();
					var from = window.__vfgDrag;
					if ( from == null || from === i ) { return; }
					var moved = group.fields.splice( from, 1 )[0];
					group.fields.splice( i, 0, moved );
					openIdx = i; window.__vfgDrag = null; renderFields();
				} );
				fieldsWrap.appendChild( card );
			} );
			updateSub();
		}
		function mini( label, inner ) { return '<div class="vfg-mini"><span class="vfg-mini-label">' + label + '</span>' + inner + '</div>'; }
		function minifull( label, inner ) { return '<div class="vfg-mini vfg-mini--full"><span class="vfg-mini-label">' + label + '</span>' + inner + '</div>'; }
		function typeSettingsUi( f ) {
			var t = f.type;
			function num( k, label, ph ) {
				return mini( label, '<input class="velox-input" type="number" data-fk="' + k + '" value="' + escapeHtml( f[ k ] != null ? f[ k ] : '' ) + '"' + ( ph ? ' placeholder="' + ph + '"' : '' ) + '>' );
			}
			function chk( k, label, onByDefault ) {
				var on = ( f[ k ] === undefined ) ? !! onByDefault : !! f[ k ];
				return '<label class="vfg-check"><input type="checkbox" data-fk="' + k + '"' + ( on ? ' checked' : '' ) + '> ' + label + '</label>';
			}
			function pick( k, label, opts ) {
				var cur = f[ k ] || opts[0][0];
				var o = opts.map( function ( x ) { return '<option value="' + x[0] + '"' + ( cur === x[0] ? ' selected' : '' ) + '>' + x[1] + '</option>'; } ).join( '' );
				return mini( label, '<select class="velox-select" data-fk="' + k + '">' + o + '</select>' );
			}
			function txt( k, label, ph ) {
				return mini( label, '<input class="velox-input" data-fk="' + k + '" value="' + escapeHtml( f[ k ] || '' ) + '"' + ( ph ? ' placeholder="' + ph + '"' : '' ) + '>' );
			}
			var addons = txt( 'prepend', 'Prepend', 'e.g. $' ) + txt( 'append', 'Append', 'e.g. px' );
			if ( t === 'number' || t === 'range' ) { return num( 'min', 'Minimum value' ) + num( 'max', 'Maximum value' ) + num( 'step', 'Step' ) + addons + chk( 'readonly', 'Read-only' ); }
			if ( t === 'textarea' ) { return num( 'rows', 'Rows', '4' ) + num( 'maxlength', 'Character limit' ) + chk( 'readonly', 'Read-only' ); }
			if ( t === 'text' || t === 'email' || t === 'url' || t === 'password' ) { return num( 'maxlength', 'Character limit' ) + addons + chk( 'readonly', 'Read-only' ); }
			if ( t === 'select' ) { return chk( 'multiple', 'Allow multiple selections' ) + chk( 'allow_null', 'Allow null (empty choice)', true ); }
			if ( t === 'checkbox' || t === 'radio' ) { return pick( 'layout', 'Layout', [ [ 'vertical', 'Vertical' ], [ 'horizontal', 'Horizontal' ] ] ); }
			if ( t === 'button_group' ) { return pick( 'layout', 'Layout', [ [ 'horizontal', 'Horizontal' ], [ 'vertical', 'Vertical' ] ] ); }
			if ( t === 'wysiwyg' ) { return pick( 'toolbar', 'Toolbar', [ [ 'full', 'Full' ], [ 'basic', 'Basic' ] ] ) + num( 'rows', 'Editor rows', '8' ) + chk( 'media_upload', 'Show media-upload button', true ); }
			if ( t === 'image' || t === 'file' ) { return pick( 'return_format', 'Return format', [ [ 'id', t === 'file' ? 'Attachment ID' : 'Image ID' ], [ 'url', 'File URL' ], [ 'array', 'Attachment array' ] ] ); }
			if ( t === 'date' || t === 'datetime' || t === 'time' ) { return txt( 'return_format', 'Return format (PHP date)', t === 'time' ? 'e.g. g:i a' : 'e.g. F j, Y' ); }
			return '';
		}
		function subFieldsUi( f ) {
			var subTypes = { text: 'Text', textarea: 'Text area', number: 'Number', email: 'Email', url: 'URL', image: 'Image', file: 'File', truefalse: 'True / False', color: 'Color', date: 'Date' };
			var subs = f.sub_fields || [];
			var rows = subs.map( function ( s, si ) {
				var topts = Object.keys( subTypes ).map( function ( t ) { return '<option value="' + t + '"' + ( t === s.type ? ' selected' : '' ) + '>' + subTypes[ t ] + '</option>'; } ).join( '' );
				return '<div class="vfg-sub-row">' +
					'<input class="velox-input vfg-sub-in" data-si="' + si + '" data-sk="label" value="' + escapeHtml( s.label || '' ) + '" placeholder="Label">' +
					'<input class="velox-input vfg-sub-in vfg-mono" data-si="' + si + '" data-sk="name" value="' + escapeHtml( s.name || '' ) + '" placeholder="name (auto)">' +
					'<select class="velox-select vfg-sub-in" data-si="' + si + '" data-sk="type">' + topts + '</select>' +
					'<button type="button" class="vfg-sub-del" data-si="' + si + '" title="Remove sub-field"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg></button>' +
				'</div>';
			} ).join( '' );
			return '<div class="vfg-subfields"><div class="vfg-sub-h">Sub-fields</div>' +
				( rows || '<div class="vfg-sub-empty">No sub-fields yet.</div>' ) +
				'<button type="button" class="vfg-sub-add">+ Add sub-field</button></div>';
		}
		function flexibleUi( f ) {
			var subTypes = { text: 'Text', textarea: 'Text area', number: 'Number', email: 'Email', url: 'URL', image: 'Image', file: 'File', truefalse: 'True / False', color: 'Color', date: 'Date' };
			f.layouts = f.layouts || [];
			var layoutsHtml = f.layouts.map( function ( L, li ) {
				L.sub_fields = L.sub_fields || [];
				var subRows = L.sub_fields.map( function ( s, si ) {
					var topts = Object.keys( subTypes ).map( function ( t ) { return '<option value="' + t + '"' + ( t === s.type ? ' selected' : '' ) + '>' + subTypes[ t ] + '</option>'; } ).join( '' );
					return '<div class="vfg-sub-row">' +
						'<input class="velox-input vfg-flex-in" data-li="' + li + '" data-si="' + si + '" data-sk="label" value="' + escapeHtml( s.label || '' ) + '" placeholder="Label">' +
						'<input class="velox-input vfg-flex-in vfg-mono" data-li="' + li + '" data-si="' + si + '" data-sk="name" value="' + escapeHtml( s.name || '' ) + '" placeholder="name (auto)">' +
						'<select class="velox-select vfg-flex-in" data-li="' + li + '" data-si="' + si + '" data-sk="type">' + topts + '</select>' +
						'<button type="button" class="vfg-sub-del vfg-flex-subdel" data-li="' + li + '" data-si="' + si + '" title="Remove sub-field"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg></button>' +
					'</div>';
				} ).join( '' );
				return '<div class="vfg-layout">' +
					'<div class="vfg-layout-head">' +
						'<input class="velox-input vfg-flex-lin" data-li="' + li + '" data-lk="label" value="' + escapeHtml( L.label || '' ) + '" placeholder="Layout label">' +
						'<input class="velox-input vfg-mono vfg-flex-lin" data-li="' + li + '" data-lk="name" value="' + escapeHtml( L.name || '' ) + '" placeholder="name (auto)">' +
						'<button type="button" class="vfg-layout-del" data-li="' + li + '" title="Remove layout"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg></button>' +
					'</div>' +
					'<div class="vfg-layout-subs">' + ( subRows || '<div class="vfg-sub-empty">No sub-fields.</div>' ) + '<button type="button" class="vfg-sub-add vfg-flex-subadd" data-li="' + li + '">+ Add sub-field</button></div>' +
				'</div>';
			} ).join( '' );
			return '<div class="vfg-subfields"><div class="vfg-sub-h">Layouts</div>' +
				( layoutsHtml || '<div class="vfg-sub-empty">No layouts yet.</div>' ) +
				'<button type="button" class="vfg-layout-add vfg-sub-add">+ Add layout</button></div>';
		}
		function conditionalUi( f, idx ) {
			f.conditional = f.conditional || { enabled: false, groups: [] };
			var on = !! f.conditional.enabled;
			var choices = group.fields.filter( function ( x, xi ) { return xi !== idx && ( x.name || x.label ); } );
			var head = '<div class="vfg-subfields"><label class="vfg-check vfg-cond-enable-row"><input type="checkbox" class="vfg-cond-enable"' + ( on ? ' checked' : '' ) + '> Conditional logic — only show this field when\u2026</label>';
			if ( ! on ) { return head + '</div>'; }
			var rules = ( f.conditional.groups[0] || [] );
			var ops = { '==': 'is equal to', '!=': 'is not equal to', 'empty': 'has no value', '!empty': 'has any value' };
			var rows = rules.map( function ( r, ri ) {
				var fopts = choices.map( function ( c ) { return '<option value="' + escapeHtml( c.name ) + '"' + ( c.name === r.field ? ' selected' : '' ) + '>' + escapeHtml( c.label || c.name ) + '</option>'; } ).join( '' );
				var oopts = Object.keys( ops ).map( function ( o ) { return '<option value="' + o + '"' + ( o === r.operator ? ' selected' : '' ) + '>' + ops[ o ] + '</option>'; } ).join( '' );
				return '<div class="vfg-cond-row">' +
					'<select class="velox-select vfg-cond-in" data-ri="' + ri + '" data-ck="field"><option value="">— field —</option>' + fopts + '</select>' +
					'<select class="velox-select vfg-cond-in" data-ri="' + ri + '" data-ck="operator">' + oopts + '</select>' +
					'<input class="velox-input vfg-cond-in" data-ri="' + ri + '" data-ck="value" value="' + escapeHtml( r.value || '' ) + '" placeholder="value">' +
					'<button type="button" class="vfg-sub-del vfg-cond-del" data-ri="' + ri + '" title="Remove rule"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg></button>' +
				'</div>';
			} ).join( '' );
			return head + '<div class="vfg-cond-rules">' + ( rows || '<div class="vfg-sub-empty">No rules yet.</div>' ) +
				'<button type="button" class="vfg-cond-add vfg-sub-add">+ Add rule</button></div></div>';
		}

		// ---- Browse Fields modal (type picker) ----
		var typeModalEl = null, typeModalFor = -1, typeCat = 'basic', typeQuery = '';
		function availableTypes() { return Object.keys( VFX_META ).filter( function ( t ) { return TYPES[ t ]; } ); }
		function renderTypeCats() {
			typeModalEl.querySelector( '.vfx-tcats' ).innerHTML = VFX_CATS.map( function ( c ) {
				return '<button type="button" class="vfx-tcat' + ( c.id === typeCat ? ' is-on' : '' ) + '" data-cat="' + c.id + '">' + c.label + '</button>';
			} ).join( '' );
		}
		function renderTypeGrid() {
			var cur = ( typeModalFor >= 0 && group.fields[ typeModalFor ] ) ? group.fields[ typeModalFor ].type : '';
			var list = availableTypes().filter( function ( t ) {
				var m = vfxMeta( t );
				if ( typeQuery ) { return ( m.label + ' ' + m.desc ).toLowerCase().indexOf( typeQuery ) !== -1; }
				return m.cat === typeCat;
			} );
			var grid = typeModalEl.querySelector( '.vfx-tgrid' );
			if ( ! list.length ) { grid.innerHTML = '<div class="vfx-tempty">No fields match \u201c' + escapeHtml( typeQuery ) + '\u201d.</div>'; return; }
			grid.innerHTML = list.map( function ( t ) {
				var m = vfxMeta( t );
				return '<button type="button" class="vfx-tcard' + ( t === cur ? ' is-on' : '' ) + '" data-type="' + t + '">' +
					'<span class="vfx-tcard-ic">' + vfxIcon( m.icon ) + '</span>' +
					'<span class="vfx-tcard-tx"><span class="vfx-tcard-name">' + escapeHtml( m.label ) + '</span><span class="vfx-tcard-desc">' + escapeHtml( m.desc ) + '</span></span></button>';
			} ).join( '' );
		}
		function ensureTypeModal() {
			if ( typeModalEl ) { return; }
			typeModalEl = document.createElement( 'div' );
			typeModalEl.className = 'vfx-typemodal';
			typeModalEl.innerHTML =
				'<div class="vfx-modal-overlay" data-close></div>' +
				'<div class="vfx-modal" role="dialog" aria-modal="true" aria-label="Select a field type">' +
					'<div class="vfx-modal-head"><div class="vfx-modal-title">Select a field type</div>' +
						'<button type="button" class="vfx-modal-x" data-close aria-label="Close"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg></button></div>' +
					'<div class="vfx-modal-search"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3-3"/></svg><input type="search" class="vfx-tsearch" placeholder="Search fields\u2026"></div>' +
					'<div class="vfx-modal-body"><div class="vfx-tcats"></div><div class="vfx-tgrid"></div></div>' +
				'</div>';
			document.body.appendChild( typeModalEl );
			typeModalEl.querySelectorAll( '[data-close]' ).forEach( function ( el ) { el.addEventListener( 'click', closeTypeModal ); } );
			var search = typeModalEl.querySelector( '.vfx-tsearch' );
			search.addEventListener( 'input', function () { typeQuery = search.value.trim().toLowerCase(); renderTypeGrid(); } );
			typeModalEl.querySelector( '.vfx-tgrid' ).addEventListener( 'click', function ( e ) {
				var c = e.target.closest( '.vfx-tcard' ); if ( c ) { pickType( c.getAttribute( 'data-type' ) ); }
			} );
			typeModalEl.querySelector( '.vfx-tcats' ).addEventListener( 'click', function ( e ) {
				var b = e.target.closest( '.vfx-tcat' );
				if ( b ) { typeCat = b.getAttribute( 'data-cat' ); typeQuery = ''; search.value = ''; renderTypeCats(); renderTypeGrid(); }
			} );
			document.addEventListener( 'keydown', function ( e ) {
				if ( e.key === 'Escape' && typeModalEl.classList.contains( 'is-open' ) ) { closeTypeModal(); }
			} );
		}
		function openTypeModal( idx ) {
			ensureTypeModal();
			typeModalFor = idx; typeQuery = '';
			typeCat = vfxMeta( group.fields[ idx ] ? group.fields[ idx ].type : 'text' ).cat;
			typeModalEl.querySelector( '.vfx-tsearch' ).value = '';
			renderTypeCats(); renderTypeGrid();
			typeModalEl.classList.add( 'is-open' );
			setTimeout( function () { typeModalEl.querySelector( '.vfx-tsearch' ).focus(); }, 30 );
		}
		function closeTypeModal() { if ( typeModalEl ) { typeModalEl.classList.remove( 'is-open' ); } typeModalFor = -1; }
		function pickType( t ) {
			if ( typeModalFor >= 0 && group.fields[ typeModalFor ] ) { group.fields[ typeModalFor ].type = t; openIdx = typeModalFor; }
			closeTypeModal();
			renderFields();
		}

		// ---- location rules ----
		function renderLocation() {
			locWrap.innerHTML = '';
			group.location.forEach( function ( rg, gi ) {
				if ( gi > 0 ) { locWrap.insertAdjacentHTML( 'beforeend', '<div class="vfg-or"><span>or</span></div>' ); }
				var box = document.createElement( 'div' ); box.className = 'vfg-rulegroup';
				rg.forEach( function ( rule, ri ) {
					if ( ri > 0 ) { box.insertAdjacentHTML( 'beforeend', '<div class="vfg-and">and</div>' ); }
					var paramOpts = Object.keys( PARAMS ).map( function ( p ) {
						return '<option value="' + p + '"' + ( p === rule.param ? ' selected' : '' ) + '>' + PARAMS[ p ] + '</option>';
					} ).join( '' );
					var row = document.createElement( 'div' ); row.className = 'vfg-rule';
					var choices = PARAM_CHOICES[ rule.param ];
					var valueCtrl;
					if ( choices && Object.keys( choices ).length ) {
						var vOpts = Object.keys( choices ).map( function ( v ) {
							return '<option value="' + escapeHtml( v ) + '"' + ( v === rule.value ? ' selected' : '' ) + '>' + escapeHtml( choices[ v ] ) + '</option>';
						} ).join( '' );
						valueCtrl = '<select class="velox-select" data-r="value">' + vOpts + '</select>';
					} else {
						valueCtrl = '<input class="velox-input" data-r="value" value="' + escapeHtml( rule.value || '' ) + '" placeholder="value">';
					}
					row.innerHTML =
						'<div class="vfg-rule-top">' +
							'<select class="velox-select" data-r="param">' + paramOpts + '</select>' +
							'<button type="button" class="vfg-rule-del" title="Remove rule"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg></button>' +
						'</div>' +
						'<div class="vfg-rule-bot">' +
							'<select class="velox-select" data-r="operator"><option value="is"' + ( rule.operator !== 'is_not' ? ' selected' : '' ) + '>is</option><option value="is_not"' + ( rule.operator === 'is_not' ? ' selected' : '' ) + '>is not</option></select>' +
							valueCtrl +
						'</div>';
					row.querySelectorAll( '[data-r]' ).forEach( function ( el ) {
						var ev = el.tagName === 'SELECT' ? 'change' : 'input';
						el.addEventListener( ev, function () {
							var k = el.getAttribute( 'data-r' );
							rule[ k ] = el.value;
							if ( k === 'param' ) {
								var ch = PARAM_CHOICES[ rule.param ];
								rule.value = ch ? ( Object.keys( ch )[ 0 ] || '' ) : '';
								renderLocation();
							}
							updateSub();
						} );
					} );
					row.querySelector( '.vfg-rule-del' ).addEventListener( 'click', function () {
						rg.splice( ri, 1 );
						if ( ! rg.length ) { group.location.splice( gi, 1 ); }
						if ( ! group.location.length ) { group.location = [ [ { param: 'post_type', operator: 'is', value: 'post' } ] ]; }
						renderLocation(); updateSub();
					} );
					box.appendChild( row );
				} );
				var addRule = document.createElement( 'button' );
				addRule.type = 'button'; addRule.className = 'vfg-addrule'; addRule.textContent = '+ Add rule';
				addRule.addEventListener( 'click', function () { rg.push( { param: 'post_type', operator: 'is', value: '' } ); renderLocation(); } );
				box.appendChild( addRule );
				locWrap.appendChild( box );
			} );
		}
		$( '#vfg-addgroup' ).addEventListener( 'click', function () {
			group.location.push( [ { param: 'post_type', operator: 'is', value: '' } ] );
			renderLocation();
		} );

		// ---- presentation ----
		$$( '.vfg-seg' ).forEach( function ( seg ) {
			seg.querySelectorAll( 'button' ).forEach( function ( b ) {
				b.addEventListener( 'click', function () {
					seg.querySelectorAll( 'button' ).forEach( function ( x ) { x.classList.toggle( 'is-on', x === b ); } );
					group.presentation[ seg.getAttribute( 'data-seg' ) ] = b.getAttribute( 'data-v' );
				} );
			} );
		} );
		var orderEl = $( '#vfg-order' );
		if ( orderEl ) { orderEl.addEventListener( 'input', function () { group.presentation.order = parseInt( orderEl.value, 10 ) || 0; } ); }

		// ---- title / active ----
		var titleEl = $( '#vfg-title' );
		titleEl.addEventListener( 'input', function () { group.title = titleEl.value; } );
		var activeEl = $( '#vfg-active' );
		activeEl.addEventListener( 'change', function () {
			group.active = activeEl.checked;
			$( '#vfg-active-label' ).textContent = activeEl.checked ? 'Active' : 'Inactive';
		} );

		// ---- add field + save ----
		$( '#vfg-addfield' ).addEventListener( 'click', function () {
			group.fields.push( { key: '', label: 'New field', name: '', type: 'text', required: false, instructions: '', 'default': '', options: '', placeholder: '' } );
			openIdx = group.fields.length - 1; renderFields();
		} );
		$( '#vfg-save' ).addEventListener( 'click', function () {
			reName();
			group.active = activeEl.checked;
			var btn = $( '#vfg-save' ); btn.disabled = true;
			api( 'fields_save', { group: JSON.stringify( group ) } )
				.then( function () { toast( 'Field group saved.' ); setTimeout( function () { location.href = 'admin.php?page=velox-utilities&tool=fields'; }, 500 ); } )
				.catch( function ( err ) { toast( err.message, 'error' ); btn.disabled = false; } );
		} );

		renderFields();
		renderLocation();
		updateSub();
	}

	function initFieldsList() {
		$$( '.vfg-list-del' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				if ( ! window.confirm( 'Delete the field group “' + btn.getAttribute( 'data-title' ) + '”? This cannot be undone.' ) ) { return; }
				api( 'fields_delete', { id: btn.getAttribute( 'data-id' ) } )
					.then( function () { location.reload(); } )
					.catch( function ( err ) { toast( err.message, 'error' ); } );
			} );
		} );
		initFieldsTypes();
	}

	/* Post types + taxonomies (Custom Fields → tabs) */
	function initFieldsTypes() {
		var checks = function ( sel, root ) { return $$( sel + ' input:checked', root || document ).map( function ( c ) { return c.value; } ); };
		function setChecks( containerId, values ) {
			values = values || [];
			$$( '#' + containerId + ' input' ).forEach( function ( c ) { c.checked = values.indexOf( c.value ) !== -1; } );
		}
		function v( id ) { var el = $( '#' + id ); return el ? el.value : ''; }
		function chk( id ) { var el = $( '#' + id ); return el ? el.checked : false; }
		function set( id, val ) { var el = $( '#' + id ); if ( el ) { el.value = val == null ? '' : val; if ( el.tagName === 'SELECT' ) { el.dispatchEvent( new Event( 'change', { bubbles: false } ) ); } } }
		function setOn( id, on ) { var el = $( '#' + id ); if ( el ) { el.checked = !! on; } }

		/* ---- Post types ---- */
		var ptEditor = $( '#vpt-editor' );
		if ( ptEditor ) {
			var ptOldSlug = '';
			function ptShow( pt ) {
				ptOldSlug = pt ? ( pt.slug || '' ) : '';
				$( '#vpt-editor-title' ).textContent = pt ? 'Edit post type' : 'Add post type';
				set( 'vpt-singular', pt ? pt.singular : '' );
				set( 'vpt-plural', pt ? pt.plural : '' );
				set( 'vpt-slug', pt ? pt.slug : '' );
				set( 'vpt-icon', pt ? pt.menu_icon : 'dashicons-admin-post' );
				set( 'vpt-menupos', pt && pt.menu_position != null ? pt.menu_position : 25 );
				setChecks( 'vpt-supports', pt ? pt.supports : [ 'title', 'editor', 'thumbnail', 'custom-fields' ] );
				setChecks( 'vpt-taxonomies', pt ? pt.taxonomies : [] );
				setOn( 'vpt-active', pt ? pt.active : true );
				setOn( 'vpt-public', pt ? pt.public : true );
				setOn( 'vpt-menu', pt ? pt.show_in_menu : true );
				setOn( 'vpt-rest', pt ? pt.show_in_rest : true );
				setOn( 'vpt-archive', pt ? pt.has_archive : true );
				setOn( 'vpt-hier', pt ? pt.hierarchical : false );
				ptEditor.hidden = false;
				ptEditor.scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
			}
			var ptAdd = $( '#vpt-add' ); if ( ptAdd ) { ptAdd.addEventListener( 'click', function () { ptShow( null ); } ); }
			$$( '.vpt-edit' ).forEach( function ( b ) {
				b.addEventListener( 'click', function () {
					var row = b.closest( '.vfx-row' ); var pt = {};
					try { pt = JSON.parse( row.getAttribute( 'data-json' ) ); } catch ( e ) {}
					ptShow( pt );
				} );
			} );
			var ptCancel = $( '#vpt-cancel' ); if ( ptCancel ) { ptCancel.addEventListener( 'click', function () { ptEditor.hidden = true; } ); }
			$$( '.vpt-del' ).forEach( function ( b ) {
				b.addEventListener( 'click', function () {
					if ( ! window.confirm( 'Delete this post type? Its content stays in the database but will no longer be registered.' ) ) { return; }
					api( 'posttype_delete', { slug: b.getAttribute( 'data-slug' ) } ).then( function () { location.reload(); } ).catch( function ( e ) { toast( e.message, 'error' ); } );
				} );
			} );
			var ptSave = $( '#vpt-save' );
			if ( ptSave ) {
				ptSave.addEventListener( 'click', function () {
					var pt = {
						_old_slug: ptOldSlug, slug: v( 'vpt-slug' ), singular: v( 'vpt-singular' ), plural: v( 'vpt-plural' ),
						menu_icon: v( 'vpt-icon' ), menu_position: v( 'vpt-menupos' ),
						supports: checks( '#vpt-supports' ), taxonomies: checks( '#vpt-taxonomies' ),
						active: chk( 'vpt-active' ), 'public': chk( 'vpt-public' ), show_in_menu: chk( 'vpt-menu' ),
						show_in_rest: chk( 'vpt-rest' ), has_archive: chk( 'vpt-archive' ), hierarchical: chk( 'vpt-hier' )
					};
					if ( ! pt.slug && ! pt.singular ) { toast( 'Give it at least a singular label.', 'error' ); return; }
					ptSave.disabled = true;
					api( 'posttype_save', { post_type: JSON.stringify( pt ) } )
						.then( function () { toast( 'Post type saved.' ); setTimeout( function () { location.reload(); }, 500 ); } )
						.catch( function ( e ) { toast( e.message, 'error' ); ptSave.disabled = false; } );
				} );
			}
		}

		/* ---- Taxonomies ---- */
		var txEditor = $( '#vtx-editor' );
		if ( txEditor ) {
			var txOldSlug = '';
			function txShow( tx ) {
				txOldSlug = tx ? ( tx.slug || '' ) : '';
				$( '#vtx-editor-title' ).textContent = tx ? 'Edit taxonomy' : 'Add taxonomy';
				set( 'vtx-singular', tx ? tx.singular : '' );
				set( 'vtx-plural', tx ? tx.plural : '' );
				set( 'vtx-slug', tx ? tx.slug : '' );
				setChecks( 'vtx-objects', tx ? tx.object_types : [ 'post' ] );
				setOn( 'vtx-active', tx ? tx.active : true );
				setOn( 'vtx-public', tx ? tx.public : true );
				setOn( 'vtx-hier', tx ? tx.hierarchical : true );
				setOn( 'vtx-rest', tx ? tx.show_in_rest : true );
				setOn( 'vtx-col', tx ? tx.show_admin_column : true );
				txEditor.hidden = false;
				txEditor.scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
			}
			var txAdd = $( '#vtx-add' ); if ( txAdd ) { txAdd.addEventListener( 'click', function () { txShow( null ); } ); }
			$$( '.vtx-edit' ).forEach( function ( b ) {
				b.addEventListener( 'click', function () {
					var row = b.closest( '.vfx-row' ); var tx = {};
					try { tx = JSON.parse( row.getAttribute( 'data-json' ) ); } catch ( e ) {}
					txShow( tx );
				} );
			} );
			var txCancel = $( '#vtx-cancel' ); if ( txCancel ) { txCancel.addEventListener( 'click', function () { txEditor.hidden = true; } ); }
			$$( '.vtx-del' ).forEach( function ( b ) {
				b.addEventListener( 'click', function () {
					if ( ! window.confirm( 'Delete this taxonomy? Its terms stay in the database but will no longer be registered.' ) ) { return; }
					api( 'taxonomy_delete', { slug: b.getAttribute( 'data-slug' ) } ).then( function () { location.reload(); } ).catch( function ( e ) { toast( e.message, 'error' ); } );
				} );
			} );
			var txSave = $( '#vtx-save' );
			if ( txSave ) {
				txSave.addEventListener( 'click', function () {
					var tx = {
						_old_slug: txOldSlug, slug: v( 'vtx-slug' ), singular: v( 'vtx-singular' ), plural: v( 'vtx-plural' ),
						object_types: checks( '#vtx-objects' ),
						active: chk( 'vtx-active' ), 'public': chk( 'vtx-public' ), hierarchical: chk( 'vtx-hier' ),
						show_in_rest: chk( 'vtx-rest' ), show_admin_column: chk( 'vtx-col' )
					};
					if ( ! tx.slug && ! tx.singular ) { toast( 'Give it at least a singular label.', 'error' ); return; }
					txSave.disabled = true;
					api( 'taxonomy_save', { taxonomy: JSON.stringify( tx ) } )
						.then( function () { toast( 'Taxonomy saved.' ); setTimeout( function () { location.reload(); }, 500 ); } )
						.catch( function ( e ) { toast( e.message, 'error' ); txSave.disabled = false; } );
				} );
			}
		}

		/* ---- Active toggles on entity cards (field groups, post types, taxonomies, options pages) ---- */
		document.addEventListener( 'change', function ( e ) {
			var tgl = e.target.closest ? e.target.closest( '.vfx-row-toggle' ) : null;
			if ( ! tgl || ! e.target.matches( 'input[type="checkbox"]' ) ) { return; }
			var vtype = tgl.getAttribute( 'data-vtype' ), vid = tgl.getAttribute( 'data-id' ), on = e.target.checked;
			var statusEl = tgl.parentNode ? tgl.parentNode.querySelector( '.vfx-row-status, .vfg-list-status' ) : null;
			function paint( active ) { if ( statusEl ) { statusEl.textContent = active ? 'Active' : 'Inactive'; statusEl.classList.toggle( 'is-active', active ); } }
			paint( on );
			api( 'vfx_toggle', { vtype: vtype, id: vid, active: on ? 1 : 0 } )
				.then( function () { toast( on ? 'Activated.' : 'Deactivated.' ); } )
				.catch( function ( err ) { toast( err.message || 'Could not update.', 'error' ); e.target.checked = ! on; paint( ! on ); } );
		} );

		/* ---- Options pages ---- */
		var opEditor = $( '#vop-editor' );
		if ( opEditor ) {
			var opOldSlug = '';
			var opSlugLocked = false;
			function opSlugify( s ) { return ( s || '' ).toLowerCase().replace( /[^a-z0-9]+/g, '-' ).replace( /^-+|-+$/g, '' ).slice( 0, 32 ); }
			function opShow( op ) {
				opOldSlug = op ? ( op.slug || '' ) : '';
				opSlugLocked = !! ( op && op.slug );
				$( '#vop-editor-title' ).textContent = op ? 'Edit options page' : 'Add options page';
				set( 'vop-title', op ? op.title : '' );
				set( 'vop-menu', op ? op.menu_title : '' );
				set( 'vop-slug', op ? op.slug : '' );
				set( 'vop-parent', op ? ( op.parent || '' ) : '' );
				set( 'vop-icon', op ? op.icon : 'dashicons-admin-generic' );
				set( 'vop-position', op && op.position != null ? op.position : 80 );
				setOn( 'vop-active', op ? ( op.active !== false ) : true );
				if ( typeof updateIconPreview === 'function' ) { updateIconPreview(); }
				opEditor.hidden = false;
				opEditor.scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
			}
			var opTitleEl = $( '#vop-title' ), opSlugEl = $( '#vop-slug' );
			if ( opTitleEl && opSlugEl ) {
				opTitleEl.addEventListener( 'input', function () { if ( ! opSlugLocked ) { opSlugEl.value = opSlugify( opTitleEl.value ); } } );
				opSlugEl.addEventListener( 'input', function () { opSlugLocked = ( opSlugEl.value.trim() !== '' ); } );
			}
			var opAdd = $( '#vop-add' ); if ( opAdd ) { opAdd.addEventListener( 'click', function () { opShow( null ); } ); }
			$$( '.vop-edit' ).forEach( function ( b ) {
				b.addEventListener( 'click', function () {
					var row = b.closest( '.vfx-row' ); var op = {};
					try { op = JSON.parse( row.getAttribute( 'data-json' ) ); } catch ( e ) {}
					opShow( op );
				} );
			} );
			var opCancel = $( '#vop-cancel' ); if ( opCancel ) { opCancel.addEventListener( 'click', function () { opEditor.hidden = true; } ); }
			$$( '.vop-del' ).forEach( function ( b ) {
				b.addEventListener( 'click', function () {
					if ( ! window.confirm( 'Delete this options page? Saved option values stay in the database.' ) ) { return; }
					api( 'optionspage_delete', { slug: b.getAttribute( 'data-slug' ) } ).then( function () { location.reload(); } ).catch( function ( e ) { toast( e.message, 'error' ); } );
				} );
			} );
			var opSave = $( '#vop-save' );
			if ( opSave ) {
				opSave.addEventListener( 'click', function () {
					var op = {
						_old_slug: opOldSlug, slug: v( 'vop-slug' ), title: v( 'vop-title' ), menu_title: v( 'vop-menu' ),
						parent: v( 'vop-parent' ), icon: v( 'vop-icon' ), position: v( 'vop-position' ), active: chk( 'vop-active' )
					};
					if ( ! op.slug && ! op.title ) { toast( 'Give it at least a title.', 'error' ); return; }
					opSave.disabled = true;
					api( 'optionspage_save', { option_page: JSON.stringify( op ) } )
						.then( function () { toast( 'Options page saved.' ); setTimeout( function () { location.reload(); }, 500 ); } )
						.catch( function ( e ) { toast( e.message, 'error' ); opSave.disabled = false; } );
				} );
			}

			/* ----- Bootstrap-icon picker ----- */
			var BI_ICONS = ( 'gift gear gear-fill house house-door person person-fill people person-badge envelope envelope-fill bell bell-fill star star-fill heart heart-fill calendar calendar-event clock clock-history cart cart-fill bag bag-fill shop basket tag tags bookmark bookmark-star flag image images camera camera-fill film play-circle music-note-beamed mic headphones file-earmark file-earmark-text folder folder-fill book journal-text briefcase building buildings bank globe globe2 map geo-alt geo-alt-fill pin-map telephone telephone-fill chat chat-dots chat-square-text reply send inbox shield shield-check shield-lock lock unlock key search trophy award gem diamond lightning lightning-charge fire sun moon moon-stars droplet cloud cloud-arrow-up snow tree flower1 graph-up graph-up-arrow bar-chart pie-chart speedometer2 bullseye rocket rocket-takeoff box box-seam boxes truck credit-card cash cash-stack coin wallet2 receipt clipboard clipboard-check pencil pencil-square pen eraser sliders toggles ui-checks grid grid-3x3-gap list list-ul columns-gap layout-text-window window code-slash terminal bug cpu hdd server wifi link-45deg share trash archive download upload printer puzzle lightbulb magic stars palette brush tools wrench wrench-adjustable hammer megaphone emoji-smile hand-thumbs-up question-circle info-circle exclamation-triangle check-circle x-circle plus-circle dash-circle three-dots gear-wide-connected sticky journal-bookmark' ).split( ' ' );
			var iconModal  = $( '#vop-icon-modal' );
			var iconGrid   = $( '#vop-icon-grid' );
			var iconSearch = $( '#vop-icon-search' );
			var iconPrev   = $( '#vop-icon-prev' );
			var iconInput  = $( '#vop-icon' );

			function updateIconPreview() {
				if ( ! iconPrev ) { return; }
				var val = ( iconInput.value || '' ).trim();
				iconPrev.innerHTML = '';
				iconPrev.className = 'vop-icon-prev';
				if ( val.indexOf( 'bi:' ) === 0 ) {
					iconPrev.innerHTML = '<i class="bi bi-' + val.slice( 3 ).replace( /[^a-z0-9-]/g, '' ) + '"></i>';
				} else if ( val.indexOf( 'dashicons-' ) === 0 ) {
					iconPrev.innerHTML = '<span class="dashicons ' + val.replace( /[^a-z0-9-]/g, '' ) + '"></span>';
				} else if ( /^https?:\/\//.test( val ) ) {
					iconPrev.innerHTML = '<img src="' + val + '" alt="" style="width:16px;height:16px;object-fit:contain;">';
				} else {
					iconPrev.className = 'vop-icon-prev is-empty';
				}
			}
			function buildIconGrid( filter ) {
				if ( ! iconGrid ) { return; }
				filter = ( filter || '' ).toLowerCase().trim();
				iconGrid.innerHTML = '';
				var shown = 0;
				BI_ICONS.forEach( function ( name ) {
					if ( filter && name.indexOf( filter ) === -1 ) { return; }
					shown++;
					var b = document.createElement( 'button' );
					b.type = 'button';
					b.className = 'vop-ic';
					b.title = name;
					b.setAttribute( 'data-bi', name );
					b.innerHTML = '<i class="bi bi-' + name + '"></i>';
					iconGrid.appendChild( b );
				} );
				if ( ! shown ) { iconGrid.innerHTML = '<p class="velox-hint" style="grid-column:1/-1;">No icons match “' + filter + '”.</p>'; }
			}
			function openIconModal() { if ( iconModal ) { buildIconGrid( '' ); iconSearch.value = ''; iconModal.hidden = false; setTimeout( function () { iconSearch.focus(); }, 30 ); } }
			function closeIconModal() { if ( iconModal ) { iconModal.hidden = true; } }

			var iconPickBtn = $( '#vop-icon-pick' );
			if ( iconPickBtn ) { iconPickBtn.addEventListener( 'click', openIconModal ); }
			var iconCloseBtn = $( '#vop-icon-close' );
			if ( iconCloseBtn ) { iconCloseBtn.addEventListener( 'click', closeIconModal ); }
			if ( iconModal ) { iconModal.addEventListener( 'click', function ( e ) { if ( e.target === iconModal ) { closeIconModal(); } } ); }
			if ( iconSearch ) { iconSearch.addEventListener( 'input', function () { buildIconGrid( iconSearch.value ); } ); }
			if ( iconGrid ) {
				iconGrid.addEventListener( 'click', function ( e ) {
					var b = e.target.closest( '.vop-ic' );
					if ( ! b ) { return; }
					iconInput.value = 'bi:' + b.getAttribute( 'data-bi' );
					updateIconPreview();
					closeIconModal();
				} );
			}
			if ( iconInput ) { iconInput.addEventListener( 'input', updateIconPreview ); }
			updateIconPreview();
		}
	}

	function initCookies() {
		var t = $( '#velox-cookies-toggle' );
		if ( ! t ) { return; }
		t.addEventListener( 'change', function () {
			saveSettings( { util_cookies: t.checked ? 1 : 0 }, t.checked ? 'Cookie banner on.' : 'Cookie banner off.' )
				.then( function () { setTimeout( function () { location.reload(); }, 400 ); } );
		} );
	}

	function initBackup() {
		var toggle = $( '#velox-backup-toggle' );
		if ( toggle ) {
			toggle.addEventListener( 'change', function () {
				saveSettings( { util_backup: toggle.checked ? 1 : 0 }, toggle.checked ? 'Backup on.' : 'Backup off.' )
					.then( function () { setTimeout( function () { location.reload(); }, 400 ); } );
			} );
		}

		// ---- progress modal with a running time estimate ----
		var modal = $( '#vbk-modal' );
		var fill  = $( '#vbk-modal-fill' );
		var etaEl = $( '#vbk-modal-eta' );
		var titleEl = $( '#vbk-modal-title' );
		var msgEl = $( '#vbk-modal-msg' );
		var progTimer = null, progStart = 0, progEstimate = 30;

		function openProgress( title, msg, estimateSec ) {
			if ( ! modal ) { return; }
			progStart = Date.now();
			progEstimate = estimateSec || 30;
			if ( titleEl ) { titleEl.textContent = title; }
			if ( msgEl ) { msgEl.textContent = msg; }
			if ( fill ) { fill.style.width = '4%'; }
			if ( etaEl ) { etaEl.textContent = 'Estimated ~' + progEstimate + 's. Keep this tab open.'; }
			modal.hidden = false;
			clearInterval( progTimer );
			progTimer = setInterval( function () {
				var elapsed = ( Date.now() - progStart ) / 1000;
				// Asymptotic fill: approaches but never reaches 100% until done.
				var pct = Math.min( 95, ( elapsed / progEstimate ) * 100 );
				if ( fill ) { fill.style.width = pct.toFixed( 0 ) + '%'; }
				var remain = Math.max( 0, progEstimate - elapsed );
				if ( etaEl ) {
					etaEl.textContent = elapsed < progEstimate
						? 'About ' + Math.ceil( remain ) + 's left. Keep this tab open.'
						: 'Almost done — finishing up…';
				}
			}, 300 );
		}
		function closeProgress( done ) {
			clearInterval( progTimer );
			if ( done && fill ) { fill.style.width = '100%'; }
			setTimeout( function () { if ( modal ) { modal.hidden = true; } }, done ? 350 : 0 );
		}

		// ---- export type segment ----
		var chosenWhat = 'both';
		var seg = $( '#vbk-what-seg' );
		if ( seg ) {
			seg.addEventListener( 'click', function ( e ) {
				var btn = e.target.closest( '.vbk-seg-btn' );
				if ( ! btn || btn.disabled ) { return; }
				seg.querySelectorAll( '.vbk-seg-btn' ).forEach( function ( b ) { b.classList.toggle( 'is-active', b === btn ); } );
				chosenWhat = btn.getAttribute( 'data-what' );
			} );
		}

		// ---- create ----
		var createBtn = $( '#vbk-create' );
		if ( createBtn ) {
			createBtn.addEventListener( 'click', function () {
				createBtn.disabled = true;
				var est = chosenWhat === 'db' ? 12 : ( chosenWhat === 'files' ? 35 : 45 );
				openProgress( 'Creating backup…', 'Packing up your ' + ( chosenWhat === 'both' ? 'database and files' : chosenWhat ) + '.', est );
				api( 'backup_create', { what: chosenWhat } )
					.then( function ( r ) {
						closeProgress( true );
						toast( r && r.message ? r.message : 'Backup created.', r && r.partial ? 'warn' : 'success' );
						setTimeout( function () { location.reload(); }, 700 );
					} )
					.catch( function ( e ) {
						closeProgress( false );
						toast( e.message, 'error' );
						createBtn.disabled = false;
					} );
			} );
		}

		// ---- import from another site ----
		var importBtn = $( '#vbk-import-btn' );
		var importFile = $( '#vbk-import-file' );
		if ( importBtn && importFile ) {
			importBtn.addEventListener( 'click', function () {
				if ( ! importFile.files || ! importFile.files.length ) { toast( 'Choose a .sql or .zip file first.', 'error' ); return; }
				var fd = new FormData();
				fd.append( 'action', 'velox' );
				fd.append( 'do', 'backup_import' );
				fd.append( 'nonce', VELOX.nonce );
				fd.append( 'file', importFile.files[0] );
				importBtn.disabled = true;
				openProgress( 'Importing & restoring backup…', 'Uploading the file, then restoring it onto this site.', 20 );
				fetch( VELOX.ajaxurl, { method: 'POST', credentials: 'same-origin', body: fd } )
					.then( function ( r ) { return r.json(); } )
					.then( function ( j ) {
						closeProgress( true );
						if ( j && j.success ) {
							toast( ( j.data && j.data.message ) || 'Backup imported.' );
							setTimeout( function () { location.reload(); }, 800 );
						} else {
							toast( ( j && j.data && j.data.message ) || 'Import failed.', 'error' );
							importBtn.disabled = false;
						}
					} )
					.catch( function () { closeProgress( false ); toast( 'Import failed.', 'error' ); importBtn.disabled = false; } );
			} );
		}

		// ---- restore (confirm modal → progress modal) ----
		var restoreModal = $( '#vbk-restore-modal' );
		var rmGo = $( '#vbk-rm-go' );
		var rmSafety = $( '#vbk-rm-safety' );
		var rmMsg = $( '#vbk-rm-msg' );
		var pendingRow = null;

		if ( restoreModal ) {
			restoreModal.addEventListener( 'click', function ( e ) {
				if ( e.target.closest( '[data-close]' ) ) { restoreModal.hidden = true; pendingRow = null; }
			} );
		}
		if ( rmGo ) {
			rmGo.addEventListener( 'click', function () {
				if ( ! pendingRow ) { return; }
				var id = pendingRow.getAttribute( 'data-id' );
				restoreModal.hidden = true;
				openProgress( 'Restoring…', 'Putting your site back to this backup.', 40 );
				api( 'backup_restore', { id: id, what: 'both', safety: rmSafety && rmSafety.checked ? 1 : 0 } )
					.then( function ( r ) {
						closeProgress( true );
						toast( ( r && r.message ? r.message : 'Restored.' ) + ( r && r.duration ? ' (' + r.duration + 's)' : '' ), 'success' );
						setTimeout( function () { location.reload(); }, 1200 );
					} )
					.catch( function ( er ) { closeProgress( false ); toast( er.message, 'error' ); } );
			} );
		}

		var list = $( '#vbk-list' );
		if ( list ) {
			list.addEventListener( 'click', function ( e ) {
				var btn = e.target.closest( 'button' );
				if ( ! btn ) { return; }
				var row = e.target.closest( '.vbk-row' );
				if ( ! row ) { return; }
				var id = row.getAttribute( 'data-id' );

				if ( btn.classList.contains( 'vbk-delete' ) ) {
					if ( ! window.confirm( 'Delete this backup permanently? Downloaded copies (if any) are not affected.' ) ) { return; }
					btn.disabled = true;
					api( 'backup_delete', { id: id } )
						.then( function () { row.remove(); toast( 'Backup deleted.' ); } )
						.catch( function ( er ) { toast( er.message, 'error' ); btn.disabled = false; } );
					return;
				}

				if ( btn.classList.contains( 'vbk-restore' ) ) {
					var hasDb = row.getAttribute( 'data-hasdb' ) === '1';
					var hasZip = row.getAttribute( 'data-haszip' ) === '1';
					var parts = [];
					if ( hasDb ) { parts.push( 'the database' ); }
					if ( hasZip ) { parts.push( 'your files (wp-content)' ); }
					if ( rmMsg ) { rmMsg.textContent = 'This replaces ' + parts.join( ' and ' ) + ' with the contents of this backup.'; }
					// DB-less backups can't take a DB safety snapshot — hide the option.
					var safetyRow = rmSafety ? rmSafety.closest( '.velox-toggle-row' ) : null;
					if ( safetyRow ) { safetyRow.style.display = hasDb ? '' : 'none'; }
					pendingRow = row;
					if ( restoreModal ) { restoreModal.hidden = false; }
					return;
				}
			} );
		}

		// Restore-history: per-row remove + clear-all (1a).
		var histTable = $( '.vbk-hist-table' );
		if ( histTable ) {
			histTable.addEventListener( 'click', function ( e ) {
				var del = e.target.closest( '.vbk-hist-del' );
				if ( ! del ) { return; }
				var row = del.closest( 'tr' );
				if ( ! row ) { return; }
				del.disabled = true;
				api( 'backup_history_delete', { when: row.getAttribute( 'data-when' ) } )
					.then( function () {
						var body = row.parentNode;
						row.remove();
						toast( 'History entry removed.' );
						if ( body && ! body.querySelector( 'tr' ) ) {
							var sec = histTable.closest( '.velox-panel' );
							var head = document.getElementById( 'vbk-hist-clear' );
							if ( sec ) { sec.remove(); }
							if ( head && head.closest( '.vbk-hist-head' ) ) { head.closest( '.vbk-hist-head' ).remove(); }
						}
					} )
					.catch( function ( er ) { toast( er.message, 'error' ); del.disabled = false; } );
			} );
		}
		var histClear = $( '#vbk-hist-clear' );
		if ( histClear ) {
			histClear.addEventListener( 'click', function () {
				if ( ! window.confirm( 'Clear the entire restore history? This does not delete any backups.' ) ) { return; }
				histClear.disabled = true;
				api( 'backup_history_clear', {} )
					.then( function () {
						var sec = histTable ? histTable.closest( '.velox-panel' ) : null;
						if ( sec ) { sec.remove(); }
						var head = histClear.closest( '.vbk-hist-head' );
						if ( head ) { head.remove(); }
						toast( 'History cleared.' );
					} )
					.catch( function ( er ) { toast( er.message, 'error' ); histClear.disabled = false; } );
			} );
		}

		var schedSave = $( '#vbk-sched-save' );
		if ( schedSave ) {
			schedSave.addEventListener( 'click', function () {
				schedSave.disabled = true;
				api( 'backup_schedule', {
					backup_schedule: ( $( '#vbk-sched-freq' ) || {} ).value || 'off',
					backup_schedule_what: ( $( '#vbk-sched-what' ) || {} ).value || 'both',
					backup_keep: ( $( '#vbk-keep' ) || {} ).value || 5
				} )
					.then( function () { toast( 'Schedule saved.' ); setTimeout( function () { location.reload(); }, 600 ); } )
					.catch( function ( e ) { toast( e.message, 'error' ); schedSave.disabled = false; } );
			} );
		}
	}

	function initOctober() {
		var toggle = $( '#velox-october-toggle' );
		if ( toggle ) {
			toggle.addEventListener( 'change', function () {
				saveSettings( { util_october: toggle.checked ? 1 : 0 }, toggle.checked ? 'Builder on.' : 'Builder off.' )
					.then( function () { setTimeout( function () { location.reload(); }, 400 ); } );
			} );
		}
		var build = $( '#oct-build' );
		var status = $( '#oct-status' );
		function run( action, data, label ) {
			if ( build ) { build.disabled = true; }
			if ( status ) { status.style.display = ''; status.textContent = label; }
			document.querySelectorAll( '.oct-rescan' ).forEach( function ( b ) { b.disabled = true; } );
			return api( action, data )
				.then( function ( d ) {
					var css = d.css_bytes ? ( Math.round( d.css_bytes / 1024 ) + 'KB CSS' ) : 'no CSS found';
					var msg = 'Built v' + d.version + ' · ' + d.pages + ' pages · ' + ( d.images != null ? d.images : 0 ) + ' images · ' + css;
					if ( d.is_rescan ) { msg = ( d.new_pages && d.new_pages.length ) ? ( d.new_pages.length + ' new page(s) — v' + d.version ) : ( 'Re-scanned — v' + d.version + ' (' + d.pages + ' pages, ' + css + ')' ); }
					toast( msg, 'success' );
					setTimeout( function () { location.reload(); }, 900 );
				} )
				.catch( function ( e ) {
					toast( e.message || 'Build failed.', 'error' );
					if ( build ) { build.disabled = false; }
					if ( status ) { status.style.display = 'none'; }
					document.querySelectorAll( '.oct-rescan' ).forEach( function ( b ) { b.disabled = false; } );
				} );
		}
		if ( build ) {
			build.addEventListener( 'click', function () {
				var name = ( $( '#oct-name' ) || {} ).value || '';
				run( 'october_build', { name: name }, 'Scanning the site… this can take a minute.' );
			} );
		}
		var diag = $( '#oct-diag' );
		if ( diag ) {
			diag.addEventListener( 'click', function () {
				var out = $( '#oct-diag-out' );
				diag.disabled = true; diag.textContent = 'Testing…';
				api( 'october_diag', {} ).then( function ( d ) {
					diag.disabled = false; diag.textContent = 'Test connection';
					if ( out ) {
						out.style.display = '';
						out.innerHTML =
							'<div><span>Velox version</span><b>' + escapeHtml( String( d.version || '?' ) ) + '</b></div>' +
							'<div><span>Home URL</span><b>' + escapeHtml( d.home ) + '</b></div>' +
							'<div><span>Public request</span><b>' + escapeHtml( d.public ) + '</b></div>' +
							'<div><span>Origin fallback</span><b>' + escapeHtml( d.origin ) + '</b></div>' +
							'<div><span>Pages found</span><b>' + escapeHtml( String( d.pages ) ) + '</b></div>' +
							'<div><span>Published by type</span><b>' + escapeHtml( String( d.types || '' ) ) + '</b></div>' +
							'<div><span>Images on homepage</span><b>' + escapeHtml( String( d.media || '—' ) ) + '</b></div>' +
							'<div><span>Sample images</span><b>' + escapeHtml( String( d.samples || '—' ) ) + '</b></div>' +
							'<div><span>PHP DOM</span><b>' + escapeHtml( d.dom ) + '</b></div>' +
							'<div><span>PHP Zip</span><b>' + escapeHtml( d.zip ) + '</b></div>';
					}
				} ).catch( function ( e ) {
					diag.disabled = false; diag.textContent = 'Test connection';
					toast( e.message, 'error' );
				} );
			} );
		}
		$$( '.oct-rescan' ).forEach( function ( b ) {
			b.addEventListener( 'click', function () {
				run( 'october_rescan', { project: b.getAttribute( 'data-project' ) }, 'Re-scanning…' );
			} );
		} );
		$$( '.oct-del' ).forEach( function ( b ) {
			b.addEventListener( 'click', function () {
				var row = b.closest( '.oct-row' );
				if ( ! row || ! window.confirm( 'Delete this build and its zip?' ) ) { return; }
				api( 'october_delete', { id: row.getAttribute( 'data-id' ) } )
					.then( function () { row.remove(); toast( 'Build deleted.' ); } )
					.catch( function ( e ) { toast( e.message, 'error' ); } );
			} );
		} );
	}

	function initMaintenance() {
		var form = $( '[data-tool="maintenance"]' );
		if ( ! form ) { return; }
		var prev = $( '#velox-maint-preview' );
		var defaultLogo = prev ? prev.getAttribute( 'data-default-logo' ) : '';
		function g( key ) { return $( '[data-setting="' + key + '"]', form ); }
		var elTitle = g( 'util_maintenance_title' ), elMsg = g( 'util_maintenance_message' ),
			elLogo = g( 'util_maintenance_logo' ), elBg = g( 'util_maintenance_bg' ),
			elText = g( 'util_maintenance_text' ), elAccent = g( 'util_maintenance_accent' ),
			elBgImg = g( 'util_maintenance_bgimage' ), elBtnT = g( 'util_maintenance_btn_text' ),
			elBtnU = g( 'util_maintenance_btn_url' );
		var pvLogo = $( '#vmp-logo' ), pvTitle = $( '#vmp-title' ), pvMsg = $( '#vmp-msg' ),
			pvBtn = $( '#vmp-btn' ), pvAnim = $( '#vmp-anim' ), pvBrand = $( '#vmp-brand' );
		var elBrand = g( 'util_maintenance_brand' ), elAnim = g( 'util_maintenance_anim' );

		// Lottie file field is only relevant when the animation type is "lottie".
		var lottieField = $( '#velox-maint-lottie-field' );
		function syncLottieField() { if ( lottieField && elAnim ) { lottieField.hidden = ( elAnim.value !== 'lottie' ); } }
		if ( elAnim ) { elAnim.addEventListener( 'change', syncLottieField ); }
		syncLottieField();

		function animHtml( type, accent ) {
			if ( type === 'none' ) { return ''; }
			if ( type === 'pulse' ) { return '<div class="vmp-a-pulse" style="background:' + accent + '"></div>'; }
			if ( type === 'dots' ) { return '<div class="vmp-a-dots"><i style="background:' + accent + '"></i><i style="background:' + accent + '"></i><i style="background:' + accent + '"></i></div>'; }
			if ( type === 'spinner' ) { return '<div class="vmp-a-spin" style="border-top-color:' + accent + '"></div>'; }
			return '<div class="vmp-a-bar"><i style="background:' + accent + '"></i></div>';
		}

		function hexa( hex, a ) {
			hex = ( hex || '' ).replace( '#', '' );
			if ( hex.length === 3 ) { hex = hex[0] + hex[0] + hex[1] + hex[1] + hex[2] + hex[2]; }
			if ( hex.length !== 6 ) { return 'rgba(0,0,0,' + a + ')'; }
			return 'rgba(' + parseInt( hex.substr( 0, 2 ), 16 ) + ',' + parseInt( hex.substr( 2, 2 ), 16 ) + ',' + parseInt( hex.substr( 4, 2 ), 16 ) + ',' + a + ')';
		}
		function render() {
			if ( ! prev ) { return; }
			var bg = elBg.value, text = elText.value, accent = elAccent.value, bgimg = ( elBgImg.value || '' ).trim();
			prev.style.color = text;
			prev.style.background = bgimg
				? 'linear-gradient(' + hexa( bg, 0.86 ) + ',' + hexa( bg, 0.94 ) + '),url("' + bgimg + '") center/cover no-repeat'
				: bg;
			var logoVal = ( elLogo.value || '' ).trim() || defaultLogo;
			if ( /\.(json|lottie)(\?|$)/i.test( logoVal ) ) {
				pvLogo.style.display = 'none';
				if ( ! prev.querySelector( '.vmp-a-lottie-logo' ) ) {
					var ph = document.createElement( 'div' ); ph.className = 'vmp-a-lottie vmp-a-lottie-logo'; ph.textContent = 'Lottie animation'; pvLogo.parentNode.insertBefore( ph, pvLogo );
				}
			} else {
				var ex = prev.querySelector( '.vmp-a-lottie-logo' ); if ( ex ) { ex.remove(); }
				pvLogo.style.display = 'block'; pvLogo.src = logoVal;
			}
			pvTitle.textContent = elTitle.value || "We'll be right back";
			pvMsg.textContent = elMsg.value || 'The site is undergoing maintenance. Please check back soon.';
			pvMsg.style.color = hexa( text, 0.62 );
			if ( pvAnim ) { pvAnim.innerHTML = animHtml( elAnim ? elAnim.value : 'bar', accent ); }
			if ( pvBrand ) {
				var bv = ( elBrand && elBrand.value || '' ).trim();
				if ( bv ) { pvBrand.textContent = bv; pvBrand.style.display = 'block'; pvBrand.style.color = hexa( text, 0.45 ); }
				else { pvBrand.style.display = 'none'; }
			}
			if ( ( elBtnT.value || '' ).trim() && ( elBtnU.value || '' ).trim() ) {
				pvBtn.textContent = elBtnT.value; pvBtn.style.display = 'inline-block'; pvBtn.style.background = accent;
			} else {
				pvBtn.style.display = 'none';
			}
		}
		[ elTitle, elMsg, elLogo, elBg, elText, elAccent, elBgImg, elBtnT, elBtnU, elBrand, elAnim ].forEach( function ( el ) {
			if ( el ) { el.addEventListener( 'input', render ); el.addEventListener( 'change', render ); }
		} );

		var resetBtn = $( '#velox-maint-reset' );
		if ( resetBtn ) {
			resetBtn.addEventListener( 'click', function () {
				if ( ! window.confirm( 'Reset the maintenance page to its default look? Your text and colours here will be cleared.' ) ) { return; }
				resetBtn.disabled = true;
				api( 'maint_reset' )
					.then( function () { toast( 'Reset to default.' ); setTimeout( function () { location.reload(); }, 500 ); } )
					.catch( function ( e ) { toast( e.message, 'error' ); resetBtn.disabled = false; } );
			} );
		}

		$$( '.velox-media-pick', form ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				if ( typeof wp === 'undefined' || ! wp.media ) { toast( 'Media library unavailable here.', 'error' ); return; }
				var target = g( btn.getAttribute( 'data-target' ) );
				var mt     = btn.getAttribute( 'data-mediatype' ) || 'image';
				var lib    = ( mt === 'any' || mt === '' ) ? {} : { type: mt };
				var frame = wp.media( { title: 'Choose file', button: { text: 'Use file' }, multiple: false, library: lib } );
				frame.on( 'select', function () {
					var att = frame.state().get( 'selection' ).first().toJSON();
					target.value = att.url; render();
				} );
				frame.open();
			} );
		} );
		$$( '.velox-media-clear', form ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () { g( btn.getAttribute( 'data-target' ) ).value = ''; render(); } );
		} );
		render();
	}

	function initFileManager() {
		var root = document.getElementById( 'velox-fm' );
		if ( ! root ) { return; }
		var crumbs = document.getElementById( 'velox-fm-crumbs' );
		var listEl = document.getElementById( 'velox-fm-list' );
		var editor = document.getElementById( 'velox-fm-editor' );

		var IC = {
			folder: '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z"/></svg>',
			file: '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 22a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h8a2.4 2.4 0 0 1 1.7.7l3.6 3.6A2.4 2.4 0 0 1 20 8v12a2 2 0 0 1-2 2z"/><path d="M14 2v5a1 1 0 0 0 1 1h5"/></svg>',
			up: '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 14 4 9l5-5"/><path d="M4 9h11a5 5 0 0 1 5 5v6"/></svg>',
			chev: '<svg class="velox-fm-chev" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>'
		};

		function fmtSize( n ) {
			if ( ! n ) { return '0 B'; }
			var u = [ 'B', 'KB', 'MB', 'GB' ], i = 0;
			while ( n >= 1024 && i < u.length - 1 ) { n /= 1024; i++; }
			return ( n < 10 && i > 0 ? n.toFixed( 1 ) : Math.round( n ) ) + ' ' + u[ i ];
		}
		function renderCrumbs( path ) {
			var parts = path ? path.split( '/' ) : [];
			var html = '<button type="button" class="velox-fm-crumb" data-path="">site root</button>';
			var acc = '';
			parts.forEach( function ( p ) {
				acc = acc ? acc + '/' + p : p;
				html += '<span class="velox-fm-crumb-sep">/</span><button type="button" class="velox-fm-crumb" data-path="' + escapeHtml( acc ) + '">' + escapeHtml( p ) + '</button>';
			} );
			crumbs.innerHTML = html;
		}
		function load( path ) {
			listEl.innerHTML = '<div class="velox-loading">Loading…</div>';
			api( 'fm_list', { path: path } )
				.then( function ( r ) {
					if ( ! r || ! r.ok ) { listEl.innerHTML = '<div class="velox-fm-msg">' + escapeHtml( ( r && r.message ) || 'Could not open folder.' ) + '</div>'; return; }
					renderCrumbs( r.path );
					var html = '';
					if ( null !== r.parent ) {
						html += '<button type="button" class="velox-fm-row is-up" data-path="' + escapeHtml( r.parent ) + '" data-dir="1"><span class="velox-fm-ic">' + IC.up + '</span><span class="velox-fm-rowname">Up a level</span></button>';
					}
					r.items.forEach( function ( it ) {
						html += '<button type="button" class="velox-fm-row' + ( it.dir ? ' is-dir' : '' ) + '" data-path="' + escapeHtml( it.rel ) + '" data-dir="' + ( it.dir ? '1' : '0' ) + '">' +
							'<span class="velox-fm-ic">' + ( it.dir ? IC.folder : IC.file ) + '</span>' +
							'<span class="velox-fm-rowname">' + escapeHtml( it.name ) + '</span>' +
							'<span class="velox-fm-rowmeta">' + ( it.dir ? '' : fmtSize( it.size ) ) + ( it.writable ? '' : ' · read-only' ) + '</span>' +
							( it.dir ? IC.chev : '' ) +
							'</button>';
					} );
					listEl.innerHTML = html || '<div class="velox-fm-msg">This folder is empty.</div>';
				} )
				.catch( function ( e ) { listEl.innerHTML = '<div class="velox-fm-msg">' + escapeHtml( e.message ) + '</div>'; } );
		}
		function openFile( path ) {
			editor.innerHTML = '<div class="velox-loading">Opening…</div>';
			api( 'fm_read', { path: path } )
				.then( function ( r ) {
					if ( ! r || ! r.ok ) { editor.innerHTML = '<div class="velox-fm-empty">' + escapeHtml( ( r && r.message ) || 'Could not open file.' ) + '</div>'; return; }
					editor.innerHTML =
						'<div class="velox-fm-edhead">' +
							'<span class="velox-fm-edname">' + escapeHtml( r.rel ) + '</span>' +
							( r.writable ? '<button type="button" class="velox-btn velox-btn--primary velox-btn--sm" id="velox-fm-save">Save</button>' : '<span class="velox-fm-ro">read-only</span>' ) +
						'</div>' +
						'<textarea class="velox-fm-code" id="velox-fm-code" spellcheck="false"' + ( r.writable ? '' : ' readonly' ) + '></textarea>';
					var ta = document.getElementById( 'velox-fm-code' );
					ta.value = r.content;
					var saveBtn = document.getElementById( 'velox-fm-save' );
					if ( saveBtn ) {
						saveBtn.addEventListener( 'click', function () {
							saveBtn.disabled = true;
							saveBtn.textContent = 'Saving…';
							api( 'fm_save', { path: r.rel, content: ta.value } )
								.then( function ( s ) { toast( ( s && s.ok ) ? 'Saved.' : ( ( s && s.message ) || 'Save failed' ), ( s && s.ok ) ? 'success' : 'error' ); } )
								.catch( function ( e ) { toast( e.message, 'error' ); } )
								.then( function () { saveBtn.disabled = false; saveBtn.textContent = 'Save'; } );
						} );
					}
				} )
				.catch( function ( e ) { editor.innerHTML = '<div class="velox-fm-empty">' + escapeHtml( e.message ) + '</div>'; } );
		}
		listEl.addEventListener( 'click', function ( e ) {
			var row = e.target.closest( '.velox-fm-row' );
			if ( ! row ) { return; }
			if ( '1' === row.getAttribute( 'data-dir' ) ) { load( row.getAttribute( 'data-path' ) ); }
			else { openFile( row.getAttribute( 'data-path' ) ); }
		} );
		crumbs.addEventListener( 'click', function ( e ) {
			var c = e.target.closest( '.velox-fm-crumb' );
			if ( c ) { load( c.getAttribute( 'data-path' ) ); }
		} );
		load( '' );
	}

	function initMail() {
		// Deliverability check.
		var delivBtn = $( '#vmail-deliv-btn' );
		var delivOut = $( '#vmail-deliv' );
		if ( delivBtn && delivOut ) {
			delivBtn.addEventListener( 'click', function () {
				delivBtn.disabled = true;
				var prev = delivBtn.textContent;
				delivBtn.textContent = 'Checking…';
				api( 'mail_deliverability' )
					.then( function ( r ) {
						var icon = { pass: '✓', warn: '!', fail: '✕', unknown: '?' };
						var html = '<div class="vmail-deliv-head">Checking <strong>' + escapeHtml( r.domain ) + '</strong> · sending as <strong>' + escapeHtml( r.from ) + '</strong></div>';
						( r.checks || [] ).forEach( function ( c ) {
							html += '<div class="vmail-deliv-row">' +
								'<span class="vmail-deliv-mark vmail-deliv-mark--' + c.status + '">' + ( icon[ c.status ] || '?' ) + '</span>' +
								'<div class="vmail-deliv-tx"><span class="vmail-deliv-label">' + escapeHtml( c.label ) + '</span>' +
								'<span class="vmail-deliv-detail">' + escapeHtml( c.detail ) + '</span></div></div>';
						} );
						delivOut.innerHTML = html;
						delivOut.hidden = false;
					} )
					.catch( function ( e ) { toast( e.message, 'error' ); } )
					.then( function () { delivBtn.disabled = false; delivBtn.textContent = prev; } );
			} );
		}
		// Sender identity (From name / email): save on change, stay in place.
		$$( '[data-setting="mail_from_name"], [data-setting="mail_from_email"]' ).forEach( function ( el ) {
			el.addEventListener( 'change', function () {
				var p = {};
				p[ el.getAttribute( 'data-setting' ) ] = el.value;
				saveSettings( p, 'Sender saved.' );
			} );
		} );
		var toggle = $( '#velox-mail-toggle' );
		if ( toggle ) {
			toggle.addEventListener( 'change', function () {
				saveSettings( { util_mail: toggle.checked ? 1 : 0 }, toggle.checked ? 'Mail & forms on.' : 'Mail & forms off.' )
					.then( function () { setTimeout( function () { location.reload(); }, 400 ); } );
			} );
		}
		$$( '.velox-mail-formdel' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var row = btn.closest( '.vmail-trow' ) || btn.closest( '.velox-mail-formrow' );
				if ( ! row || ! window.confirm( 'Delete this form? Its entries stay stored.' ) ) { return; }
				api( 'form_delete', { id: row.getAttribute( 'data-id' ) } )
					.then( function () { row.remove(); toast( 'Form deleted.' ); } )
					.catch( function ( e ) { toast( e.message, 'error' ); } );
			} );
		} );
		document.addEventListener( 'click', function ( e ) {
			var btn = e.target.closest ? e.target.closest( '.velox-mail-sub-del' ) : null;
			if ( ! btn ) { return; }
			e.preventDefault();
			var sub = btn.closest( '.vmail-entry' ) || btn.closest( '.velox-mail-sub' );
			var id  = btn.getAttribute( 'data-id' ) || ( sub && sub.getAttribute( 'data-id' ) );
			if ( ! window.confirm( 'Move this entry to Deleted? You can restore it from the inbox.' ) ) { return; }
			api( 'submission_delete', { id: id } )
				.then( function () {
					if ( sub ) { sub.remove(); }
					var eb = document.getElementById( 'vmail-entries-blank' ), ew = document.getElementById( 'vmail-entries' );
					if ( eb && ew ) { eb.hidden = !! ew.querySelector( '.vmail-entry' ); }
					toast( 'Moved to Deleted.' );
				} )
				.catch( function ( er ) { toast( er.message, 'error' ); } );
		} );
		initMailSmtp();
		initMailInbox();
		initEntriesLive();
		initMailCaptchaGate();

		var logClear = $( '#vmail-log-clear' );
		if ( logClear ) {
			logClear.addEventListener( 'click', function () {
				api( 'mail_log_clear', {} ).then( function () { location.reload(); } );
			} );
		}
		$$( '.velox-mail-resend' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				btn.disabled = true;
				var prev = btn.textContent;
				btn.textContent = 'Sending…';
				api( 'mail_resend', { id: btn.getAttribute( 'data-id' ) } )
					.then( function ( r ) { toast( r.message, r.ok ? 'success' : 'error' ); } )
					.catch( function ( e ) { toast( e.message, 'error' ); } )
					.then( function () { btn.disabled = false; btn.textContent = prev; } );
			} );
		} );
	}

	/* ---- Submissions inbox: master list + detail panel ---- */
	function initEntriesLive() {
		var wrap = document.getElementById( 'vmail-entries' );
		if ( ! wrap ) { return; }
		var formId = wrap.getAttribute( 'data-form' ) || '0';
		var blank = document.getElementById( 'vmail-entries-blank' );
		var busy = false;

		function entryRow( it, labels ) {
			var el = document.createElement( 'details' );
			el.className = 'vmail-entry';
			el.setAttribute( 'data-id', it.id );
			var prev = [];
			Object.keys( it.data || {} ).forEach( function ( k ) {
				var v = it.data[ k ];
				if ( prev.length < 2 && v && 'object' !== typeof v && String( v ).trim() ) { prev.push( String( v ).trim() ); }
			} );
			var when = '';
			var d = new Date( String( it.created ).replace( ' ', 'T' ) );
			if ( ! isNaN( d.getTime() ) ) {
				when = d.toLocaleDateString( undefined, { month: 'short', day: 'numeric', year: 'numeric' } ) +
					' · ' + d.toLocaleTimeString( undefined, { hour: '2-digit', minute: '2-digit' } );
			}
			var dl = Object.keys( it.data || {} ).map( function ( k ) {
				var v = it.data[ k ];
				var lbl = ( labels && labels[ k ] ) ? labels[ k ] : k.replace( /[_-]/g, ' ' );
				return '<dt>' + escapeHtml( lbl ) + '</dt><dd>' + escapeHtml( Array.isArray( v ) ? v.join( ', ' ) : String( v == null ? '' : v ) ) + '</dd>';
			} ).join( '' );
			el.innerHTML =
				'<summary class="vmail-entry-sum">' +
					'<span class="vmail-entry-date">' + escapeHtml( when ) + '</span>' +
					'<span class="vmail-entry-preview">' + escapeHtml( prev.join( '  ·  ' ) ) + '</span>' +
					'<span class="vmail-entry-chev" aria-hidden="true">\u25be</span>' +
				'</summary>' +
				'<div class="vmail-entry-body"><dl class="vmail-entry-dl">' + dl + '</dl>' +
					'<div class="vmail-entry-foot"><span class="vmail-entry-meta">#' + it.id + ( it.ip ? ' · IP ' + escapeHtml( it.ip ) : '' ) + '</span>' +
					'<button class="velox-btn velox-btn--ghost velox-mail-sub-del" data-id="' + it.id + '">Delete entry</button></div>' +
				'</div>';
			return el;
		}

		function sync() {
			if ( busy || document.hidden ) { return; }
			busy = true;
			api( 'entries_sync', { form: formId } )
				.then( function ( r ) {
					var items = ( r && r.items ) || [], labels = ( r && r.labels ) || {};
					var seen = {};
					items.forEach( function ( it ) { seen[ String( it.id ) ] = 1; } );
					$$( '.vmail-entry', wrap ).forEach( function ( el ) {
						if ( ! seen[ el.getAttribute( 'data-id' ) ] ) { el.remove(); }
					} );
					items.slice().reverse().forEach( function ( it ) {
						if ( wrap.querySelector( '.vmail-entry[data-id="' + it.id + '"]' ) ) { return; }
						wrap.insertBefore( entryRow( it, labels ), wrap.firstChild );
					} );
					if ( blank ) { blank.hidden = items.length > 0; }
				} )
				.catch( function () {} )
				.then( function () { busy = false; } );
		}
		setInterval( sync, 12000 );
		document.addEventListener( 'visibilitychange', function () { if ( ! document.hidden ) { sync(); } } );
	}

	function initMailInbox() {
		var list   = $( '#vmail-inbox-list' );
		var detail = $( '#vmail-inbox-detail' );
		if ( ! list || ! detail ) { return; }

		function deleteSubmission( id, itemEl ) {
			if ( ! window.confirm( 'Move this submission to Deleted? You can restore it later.' ) ) { return; }
			api( 'submission_delete', { id: id } )
				.then( function () {
					var item = itemEl || list.querySelector( '.vmail-inbox-item[data-id="' + id + '"]' );
					var wasActive = item && item.classList.contains( 'is-active' );
					if ( item ) { item.remove(); }
					// If we deleted the open one, fall back to the first remaining row.
					if ( wasActive ) {
						var next = list.querySelector( '.vmail-inbox-item' );
						if ( next ) { load( next.getAttribute( 'data-id' ), next ); }
						else { detail.innerHTML = '<div class="vmail-inbox-empty-detail">No submissions left.</div>'; }
					}
					toast( 'Moved to Deleted.' );
				} )
				.catch( function ( e ) { toast( e.message, 'error' ); } );
		}

		function initials( name ) {
			name = ( name || '' ).trim();
			if ( ! name ) { return '?'; }
			var p = name.split( /\s+/ );
			return ( p[0].charAt( 0 ) + ( p.length > 1 ? p[ p.length - 1 ].charAt( 0 ) : '' ) ).toUpperCase();
		}

		var current = null; // the submission currently open in the reading pane

		function renderDetail( sub ) {
			current = sub;
			var rows = '';
			var data = sub.data || {};
			var labels = sub.labels || {};
			Object.keys( data ).forEach( function ( k ) {
				var v = data[ k ];
				if ( v && typeof v === 'object' ) { v = Object.keys( v ).map( function ( i ) { return v[ i ]; } ).join( ', ' ); }
				var label = labels[ k ] || k.replace( /[_-]/g, ' ' );
				rows += '<div class="vmail-d-row"><dt>' + escapeHtml( label ) + '</dt><dd>' + escapeHtml( String( v == null ? '' : v ) ).replace( /\n/g, '<br>' ) + '</dd></div>';
			} );
			if ( ! rows ) { rows = '<p class="velox-hint">This submission has no stored fields.</p>'; }
			var meta = [];
			if ( sub.form_title ) { meta.push( escapeHtml( sub.form_title ) ); }
			if ( sub.created ) { meta.push( escapeHtml( sub.created ) ); }
			if ( sub.ip ) { meta.push( 'IP ' + escapeHtml( sub.ip ) ); }
			var email = sub.email || '';
			var pinned = !! sub.pinned;
			var done = sub.status === 'done';
			var canReply = email && /.+@.+\..+/.test( email );

			detail.innerHTML =
				'<div class="vmail-d-head">' +
					'<span class="vmail-avatar vmail-avatar--lg" aria-hidden="true">' + escapeHtml( initials( sub.who ) ) + '</span>' +
					'<div style="flex:1;min-width:0">' +
						'<div class="vmail-d-who">' + escapeHtml( sub.who || 'Submission' ) + '</div>' +
						( email ? '<a class="vmail-d-email" href="mailto:' + escapeHtml( email ) + '">' + escapeHtml( email ) + '</a>' : '' ) +
						'<div class="vmail-d-meta">' + meta.join( '  ·  ' ) + '</div>' +
					'</div>' +
				'</div>' +
				'<div class="vmail-d-actions">' +
					'<button type="button" class="velox-btn velox-btn--primary vmail-act" data-act="reply"' + ( canReply ? '' : ' disabled title="No email address to reply to"' ) + '>Reply</button>' +
					'<button type="button" class="velox-btn velox-btn--ghost vmail-act" data-act="pin">' + ( pinned ? 'Unpin' : 'Pin' ) + '</button>' +
					'<button type="button" class="velox-btn velox-btn--ghost vmail-act" data-act="done">' + ( done ? 'Reopen' : 'Mark done' ) + '</button>' +
					'<button type="button" class="velox-btn velox-btn--ghost vmail-act" data-act="delete">Delete</button>' +
					( folders.length ? '<select class="velox-select velox-select--sm vmail-d-folder" title="Move to folder">' + folderOptions( sub.folder || '' ) + '</select>' : '' ) +
				'</div>' +
				'<dl class="vmail-d-dl">' + rows + '</dl>';
		}

		function updateUnreadCount() {
			var n = list.querySelectorAll( '.vmail-inbox-item[data-read="0"]' ).length;
			var btn = document.querySelector( '.vmail-filter[data-filter="unread"]' );
			if ( ! btn ) { return; }
			var badge = btn.querySelector( '.vmail-filter-count' );
			if ( n > 0 ) {
				if ( ! badge ) { badge = document.createElement( 'span' ); badge.className = 'vmail-filter-count'; btn.appendChild( badge ); }
				badge.textContent = n;
			} else if ( badge ) { badge.remove(); }
		}

		function markRead( id, itemEl ) {
			var item = itemEl || list.querySelector( '.vmail-inbox-item[data-id="' + id + '"]' );
			if ( ! item || item.getAttribute( 'data-read' ) === '1' ) { return; }
			item.setAttribute( 'data-read', '1' );
			item.classList.remove( 'is-unread' );
			updateUnreadCount();
			api( 'submission_flag', { id: id, flag: 'read', on: '1' } ).catch( function () {} );
		}

		var activeFilter = 'all';
		function syncRail() {
			var railInbox = document.querySelector( '.vmail-rail-item[data-rail="inbox"]' );
			if ( ! railInbox ) { return; }
			var onDeleted = !! document.querySelector( '.vmail-filter[data-filter="deleted"].is-on' );
			var onFolder  = !! document.querySelector( '.vmail-folder-chip.is-on' );
			railInbox.classList.toggle( 'is-on', ! onDeleted && ! onFolder );
		}
		function refreshBlank() {
			var b = document.getElementById( 'vmail-inbox-blank' );
			if ( ! b ) { return; }
			if ( document.querySelector( '.vmail-filter[data-filter="deleted"].is-on' ) ) { b.hidden = true; return; }
			b.hidden = !! list.querySelector( '.vmail-inbox-item' );
		}
		function applyFilter() {
			var shown = 0;
			list.querySelectorAll( '.vmail-inbox-item' ).forEach( function ( it ) {
				if ( it.classList.contains( 'vmail-del-row' ) ) { return; }
				var ok = 'all' === activeFilter
					|| ( 'unread' === activeFilter && it.getAttribute( 'data-read' ) === '0' )
					|| ( 'pinned' === activeFilter && it.getAttribute( 'data-pinned' ) === '1' )
					|| ( 'done' === activeFilter && it.getAttribute( 'data-status' ) === 'done' )
					|| ( 0 === activeFilter.indexOf( 'folder:' ) && it.getAttribute( 'data-folder' ) === activeFilter.slice( 7 ) );
				it.style.display = ok ? '' : 'none';
				if ( ok ) { shown++; }
			} );
			var nomatch = list.querySelector( '.vmail-inbox-nomatch' );
			if ( nomatch ) { nomatch.hidden = shown > 0; }
			refreshBlank();
			syncRail();
		}

		function load( id, itemEl ) {
			list.querySelectorAll( '.vmail-inbox-item' ).forEach( function ( el ) {
				el.classList.toggle( 'is-active', el === itemEl );
				el.setAttribute( 'aria-selected', el === itemEl ? 'true' : 'false' );
			} );
			detail.innerHTML = '<div class="vmail-inbox-empty-detail">Loading…</div>';
			api( 'submission_get', { id: id } )
				.then( function ( sub ) { renderDetail( sub ); markRead( id, itemEl ); } )
				.catch( function ( e ) { detail.innerHTML = '<div class="vmail-inbox-empty-detail">' + escapeHtml( e.message ) + '</div>'; } );
		}

		list.addEventListener( 'click', function ( e ) {
			var item = e.target.closest( '.vmail-inbox-item' );
			if ( ! item ) { return; }
			if ( e.target.closest( '.vmail-inbox-del' ) ) {
				e.stopPropagation();
				deleteSubmission( item.getAttribute( 'data-id' ), item );
				return;
			}
			// Row hover actions: pin / done / read, without opening the message.
			var act = e.target.closest( '.vmail-act' );
			if ( act ) {
				e.stopPropagation();
				var id = item.getAttribute( 'data-id' ), kind = act.getAttribute( 'data-act' );
				if ( 'pin' === kind ) {
					var pon = '1' !== item.getAttribute( 'data-pinned' );
					item.setAttribute( 'data-pinned', pon ? '1' : '0' );
					item.classList.toggle( 'is-pinned', pon );
					if ( pon ) { list.insertBefore( item, list.firstChild ); }
					api( 'submission_flag', { id: id, flag: 'pinned', on: pon ? '1' : '0' } ).catch( function () {} );
					toast( pon ? 'Pinned.' : 'Unpinned.' );
				} else if ( 'done' === kind ) {
					var don = 'done' !== item.getAttribute( 'data-status' );
					item.setAttribute( 'data-status', don ? 'done' : 'open' );
					api( 'submission_flag', { id: id, flag: 'done', on: don ? '1' : '0' } ).catch( function () {} );
					toast( don ? 'Marked done.' : 'Reopened.' );
				} else if ( 'read' === kind ) {
					var ron = '1' !== item.getAttribute( 'data-read' );
					item.setAttribute( 'data-read', ron ? '1' : '0' );
					item.classList.toggle( 'is-unread', ! ron );
					api( 'submission_flag', { id: id, flag: 'read', on: ron ? '1' : '0' } ).catch( function () {} );
				}
				applyFilter();
				return;
			}
			load( item.getAttribute( 'data-id' ), item );
		} );

		// ===== Live sync: keep the inbox current without reloading the page =====
		function fmtWhen( created ) {
			if ( ! created ) { return ''; }
			var d = new Date( String( created ).replace( ' ', 'T' ) );
			if ( isNaN( d.getTime() ) ) { return ''; }
			return d.toLocaleDateString( undefined, { month: 'short', day: 'numeric' } );
		}
		function liveRow( it ) {
			var row = document.createElement( 'div' );
			row.className = 'vmail-inbox-item' + ( it.read ? '' : ' is-unread' ) + ( it.pinned ? ' is-pinned' : '' );
			row.setAttribute( 'data-id', it.id );
			row.setAttribute( 'data-read', it.read ? '1' : '0' );
			row.setAttribute( 'data-pinned', it.pinned ? '1' : '0' );
			row.setAttribute( 'data-status', it.status || 'open' );
			row.setAttribute( 'data-folder', it.folder || '' );
			row.setAttribute( 'data-email', it.email || '' );
			row.setAttribute( 'role', 'option' );
			function actBtn( kind, title, path ) {
				return '<button type="button" class="vmail-act vmail-act--' + kind + '" data-act="' + kind + '" data-id="' + it.id + '" title="' + title + '" aria-label="' + title + '">' +
					'<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">' + path + '</svg></button>';
			}
			row.innerHTML =
				'<label class="vmail-inbox-check" title="Select"><input type="checkbox" class="vmail-check" data-id="' + it.id + '"></label>' +
				'<button type="button" class="vmail-inbox-open" aria-label="Open submission">' +
					'<span class="vmail-avatar" aria-hidden="true">' + escapeHtml( initials( it.who ) ) + '</span>' +
					'<span class="vmail-inbox-body">' +
						'<span class="vmail-inbox-line1"><span class="vmail-inbox-who">' + escapeHtml( it.who || 'Anonymous' ) + '</span>' +
						'<span class="vmail-inbox-when">' + escapeHtml( fmtWhen( it.created ) ) + '</span></span>' +
						'<span class="vmail-inbox-form">' + escapeHtml( it.form_title || '' ) + '</span>' +
						'<span class="vmail-inbox-prev">' + escapeHtml( it.preview || '' ) + '</span>' +
					'</span>' +
					'<span class="vmail-inbox-marks" aria-hidden="true">' +
						'<svg class="vmail-pin-ic" viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 4v6l-2 4h10l-2-4V4M12 14v6M8 4h8"/></svg>' +
						'<span class="vmail-unread-dot"></span>' +
					'</span>' +
				'</button>' +
				'<div class="vmail-inbox-acts">' +
					actBtn( 'pin', 'Pin to top', '<path d="M9 4v6l-2 4h10l-2-4V4M12 14v6M8 4h8"/>' ) +
					actBtn( 'done', 'Mark as done', '<circle cx="12" cy="12" r="9"/><path d="m8.5 12.5 2.5 2.5 4.5-5"/>' ) +
					actBtn( 'read', 'Mark read or unread', '<rect x="2.5" y="5" width="19" height="14" rx="2.5"/><path d="m3 7 9 6 9-6"/>' ) +
					'<button type="button" class="vmail-act vmail-act--del vmail-inbox-del" data-id="' + it.id + '" title="Move to Deleted" aria-label="Move to Deleted">' +
						'<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>' +
					'</button>' +
				'</div>';
			return row;
		}
		var syncing = false;
		function syncInbox() {
			// Only sync the live inbox — the Deleted view has its own loader.
			if ( syncing || document.hidden ) { return; }
			var onDeleted = document.querySelector( '.vmail-filter[data-filter="deleted"].is-on' );
			if ( onDeleted ) { return; }
			syncing = true;
			api( 'inbox_sync', {} )
				.then( function ( r ) {
					var items = ( r && r.items ) || [];
					var seen = {}, added = 0;
					items.forEach( function ( it ) { seen[ String( it.id ) ] = it; } );
					// Drop rows that no longer exist (deleted elsewhere).
					$$( '.vmail-inbox-item', list ).forEach( function ( el ) {
						var id = el.getAttribute( 'data-id' );
						if ( ! seen[ id ] ) { el.remove(); }
					} );
					// Add rows we don't have yet, newest first.
					items.slice().reverse().forEach( function ( it ) {
						if ( list.querySelector( '.vmail-inbox-item[data-id="' + it.id + '"]' ) ) { return; }
						list.insertBefore( liveRow( it ), list.firstChild );
						added++;
					} );
					var blank = document.getElementById( 'vmail-inbox-blank' );
					if ( blank ) { blank.hidden = items.length > 0; }
					if ( added ) { applyFilter(); }
				} )
				.catch( function () {} )
				.then( function () { syncing = false; } );
		}
		setInterval( syncInbox, 12000 );
		document.addEventListener( 'visibilitychange', function () { if ( ! document.hidden ) { syncInbox(); } } );

		// Reading-pane actions: reply / pin / done / delete.
		detail.addEventListener( 'click', function ( e ) {
			var b = e.target.closest( '[data-act]' );
			if ( ! b || ! current ) { return; }
			var act = b.getAttribute( 'data-act' );
			var id = current.id;
			var itemEl = list.querySelector( '.vmail-inbox-item[data-id="' + id + '"]' );
			if ( 'reply' === act ) {
				openReplyModal( current );
			} else if ( 'pin' === act ) {
				var pon = ! current.pinned; current.pinned = pon;
				b.textContent = pon ? 'Unpin' : 'Pin';
				if ( itemEl ) { itemEl.setAttribute( 'data-pinned', pon ? '1' : '0' ); itemEl.classList.toggle( 'is-pinned', pon ); if ( pon ) { list.insertBefore( itemEl, list.firstChild ); } }
				api( 'submission_flag', { id: id, flag: 'pinned', on: pon ? '1' : '0' } ).catch( function () {} );
				applyFilter();
			} else if ( 'done' === act ) {
				var don = current.status !== 'done'; current.status = don ? 'done' : 'open';
				b.textContent = don ? 'Reopen' : 'Mark done';
				if ( itemEl ) { itemEl.setAttribute( 'data-status', current.status ); }
				api( 'submission_flag', { id: id, flag: 'done', on: don ? '1' : '0' } ).catch( function () {} );
				applyFilter();
			} else if ( 'delete' === act ) {
				deleteSubmission( id, itemEl );
			}
		} );

		// Filters live in two places now: the status bar on top and the folder rail
		// on the left. Listen on the whole inbox so both work, and clear the "on"
		// state across both so only one scope is ever active.
		var inboxRoot = document.getElementById( 'vmail-inbox' );
		if ( inboxRoot ) {
			inboxRoot.addEventListener( 'click', function ( e ) {
				var b = e.target.closest( '.vmail-filter' ); if ( ! b ) { return; }
				inboxRoot.querySelectorAll( '.vmail-filter' ).forEach( function ( x ) { x.classList.toggle( 'is-on', x === b ); } );
				activeFilter = b.getAttribute( 'data-filter' );
				if ( 'deleted' === activeFilter ) {
					showDeleted();
					syncRail();
				} else {
					hideDeleted();
					applyFilter();
				}
			} );
		}

		function hideDeleted() {
			list.querySelectorAll( '.vmail-del-row' ).forEach( function ( r ) { r.remove(); } );
			list.querySelectorAll( '.vmail-inbox-item' ).forEach( function ( it ) { it.style.display = ''; } );
		}

		function checkDeletedEmpty() {
			if ( ! list.querySelector( '.vmail-del-row' ) ) {
				var empty = document.createElement( 'div' );
				empty.className = 'vmail-del-row vmail-del-empty';
				empty.textContent = 'Nothing in the deleted bin.';
				list.appendChild( empty );
			}
		}

		function delRow( it ) {
			var row = document.createElement( 'div' );
			row.className = 'vmail-inbox-item vmail-del-row';
			row.setAttribute( 'data-id', it.id );
			row.innerHTML =
				'<div class="vmail-inbox-open" style="cursor:default">' +
					'<span class="vmail-avatar" aria-hidden="true">' + escapeHtml( initials( it.who ) ) + '</span>' +
					'<span class="vmail-inbox-body">' +
						'<span class="vmail-inbox-line1"><span class="vmail-inbox-who">' + escapeHtml( it.who || 'Anonymous' ) + '</span><span class="vmail-inbox-when">' + escapeHtml( it.form_title || '' ) + '</span></span>' +
						'<span class="vmail-inbox-prev">' + escapeHtml( it.preview || '' ) + '</span>' +
					'</span>' +
				'</div>' +
				'<div class="vmail-del-acts">' +
					'<button type="button" class="vmail-iact vmail-del-restore" title="Restore to inbox" aria-label="Restore to inbox">' +
						'<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M9 14 4 9l5-5"/><path d="M4 9h11a5 5 0 0 1 5 5v6"/></svg></button>' +
					'<button type="button" class="vmail-iact vmail-iact--del vmail-del-purge" title="Delete permanently" aria-label="Delete permanently">' +
						'<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg></button>' +
				'</div>';
			row.querySelector( '.vmail-del-restore' ).addEventListener( 'click', function () {
				api( 'submission_restore', { id: it.id } )
					.then( function () { row.remove(); toast( 'Restored to inbox.' ); checkDeletedEmpty(); } )
					.catch( function ( e ) { toast( e.message, 'error' ); } );
			} );
			row.querySelector( '.vmail-del-purge' ).addEventListener( 'click', function () {
				if ( ! window.confirm( 'Permanently delete this submission? This cannot be undone.' ) ) { return; }
				api( 'submission_purge', { id: it.id } )
					.then( function () { row.remove(); toast( 'Deleted permanently.' ); checkDeletedEmpty(); } )
					.catch( function ( e ) { toast( e.message, 'error' ); } );
			} );
			return row;
		}

		function showDeleted() {
			list.querySelectorAll( '.vmail-inbox-item' ).forEach( function ( it ) { it.style.display = 'none'; } );
			var nomatch = list.querySelector( '.vmail-inbox-nomatch' ); if ( nomatch ) { nomatch.hidden = true; }
			// The "no submissions yet" panel belongs to the live inbox, not this view.
			var blankEl = document.getElementById( 'vmail-inbox-blank' ); if ( blankEl ) { blankEl.hidden = true; }
			list.querySelectorAll( '.vmail-del-row' ).forEach( function ( r ) { r.remove(); } );
			var loading = document.createElement( 'div' );
			loading.className = 'vmail-del-row vmail-del-empty';
			loading.textContent = 'Loading deleted…';
			list.appendChild( loading );
			detail.innerHTML = '<div class="vmail-inbox-empty-detail">Deleted submissions live here. Restore any to send it back to the inbox, or delete it forever.</div>';
			api( 'submission_deleted_list', {} )
				.then( function ( r ) {
					loading.remove();
					var items = ( r && r.items ) || [];
					if ( ! items.length ) { checkDeletedEmpty(); return; }
					items.forEach( function ( it ) { list.appendChild( delRow( it ) ); } );
				} )
				.catch( function ( e ) { loading.remove(); toast( e.message, 'error' ); } );
		}

		// ===== Folders =====
		var folders = [];
		try { folders = JSON.parse( ( document.getElementById( 'vmail-folders-data' ) || {} ).textContent || '[]' ); } catch ( e ) {}
		folders = Array.isArray( folders ) ? folders : [];
		var folderChips = document.getElementById( 'vmail-folder-chips' );
		function renderFolderChips() {
			if ( ! folderChips ) { return; }
			var counts = {};
			$$( '.vmail-inbox-item', list ).forEach( function ( it ) {
				var fid = it.getAttribute( 'data-folder' ) || '';
				if ( fid ) { counts[ fid ] = ( counts[ fid ] || 0 ) + 1; }
			} );
			folderChips.innerHTML = folders.map( function ( f ) {
				var n = counts[ f.id ] || 0;
				return '<button type="button" class="vmail-filter vmail-rail-item vmail-folder-chip" data-filter="folder:' + f.id + '">' +
					'<span class="ic"><span class="vmail-folder-dot" style="background:' + escapeHtml( f.color ) + '"></span></span>' +
					'<span class="lb">' + escapeHtml( f.name ) + '</span>' +
					( n ? '<span class="ct">' + n + '</span>' : '' ) + '</button>';
			} ).join( '' );
		}
		function folderOptions( sel ) {
			return '<option value="">No folder</option>' + folders.map( function ( f ) {
				return '<option value="' + f.id + '"' + ( sel === f.id ? ' selected' : '' ) + '>' + escapeHtml( f.name ) + '</option>';
			} ).join( '' );
		}
		renderFolderChips();

		var folderManageBtn = document.getElementById( 'vmail-folder-manage' );
		if ( folderManageBtn ) {
			folderManageBtn.addEventListener( 'click', function () {
				var overlay = document.createElement( 'div' );
				overlay.className = 'vmail-fm-overlay';
				overlay.innerHTML =
					'<div class="vmail-fm">' +
						'<div class="vmail-fm-head">' +
							'<div><strong>Folders</strong><span class="vmail-fm-sub">Colour-code submissions in your inbox.</span></div>' +
							'<button type="button" class="vmail-fm-x" aria-label="Close">' +
								'<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>' +
							'</button>' +
						'</div>' +
						'<div class="vmail-fm-body">' +
							'<div class="vmail-fm-list" id="vmail-fm-list"></div>' +
							'<button type="button" class="vmail-fm-add" id="vmail-fm-add">' +
								'<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>' +
								'Add folder</button>' +
						'</div>' +
						'<div class="vmail-fm-foot">' +
							'<button type="button" class="velox-btn velox-btn--ghost" id="vmail-fm-cancel">Cancel</button>' +
							'<button type="button" class="velox-btn velox-btn--primary" id="vmail-fm-save">Save folders</button>' +
						'</div>' +
					'</div>';
				document.body.appendChild( overlay );
				var fmList = overlay.querySelector( '#vmail-fm-list' );
				var working = folders.map( function ( f ) { return { id: f.id, name: f.name, color: f.color }; } );
				function drawRows() {
					fmList.innerHTML = '';
					if ( ! working.length ) {
						fmList.innerHTML = '<div class="vmail-fm-empty">No folders yet. Add one to start sorting submissions.</div>';
					}
					working.forEach( function ( f, idx ) {
						var row = document.createElement( 'div' );
						row.className = 'vmail-fm-row';
						row.innerHTML =
							'<span class="vmail-fm-sw" style="background:' + escapeHtml( f.color || '#2ab7f1' ) + '">' +
								'<input type="color" class="vmail-fm-color" value="' + escapeHtml( f.color || '#2ab7f1' ) + '" aria-label="Folder colour"></span>' +
							'<input type="text" class="vmail-fm-name" value="' + escapeHtml( f.name || '' ) + '" placeholder="Folder ' + ( idx + 1 ) + '">' +
							'<button type="button" class="vmail-fm-del" title="Remove folder" aria-label="Remove folder">' +
								'<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>';
						row.querySelector( '.vmail-fm-color' ).addEventListener( 'input', function () {
							working[ idx ].color = this.value;
							this.parentNode.style.background = this.value;
						} );
						row.querySelector( '.vmail-fm-name' ).addEventListener( 'input', function () { working[ idx ].name = this.value; } );
						row.querySelector( '.vmail-fm-del' ).addEventListener( 'click', function () { working.splice( idx, 1 ); drawRows(); } );
						fmList.appendChild( row );
					} );
				}
				drawRows();
				overlay.querySelector( '#vmail-fm-add' ).addEventListener( 'click', function () { working.push( { id: '', name: '', color: '#2ab7f1' } ); drawRows(); } );
				function closeFm() { overlay.remove(); }
				overlay.querySelector( '.vmail-fm-x' ).addEventListener( 'click', closeFm );
				overlay.querySelector( '#vmail-fm-cancel' ).addEventListener( 'click', closeFm );
				overlay.addEventListener( 'click', function ( e ) { if ( e.target === overlay ) { closeFm(); } } );
				overlay.querySelector( '#vmail-fm-save' ).addEventListener( 'click', function () {
					var sv = overlay.querySelector( '#vmail-fm-save' );
					sv.disabled = true;
					api( 'mail_folders_save', { folders: JSON.stringify( working ) } )
						.then( function ( r ) { folders = ( r && r.folders ) || []; renderFolderChips(); toast( 'Folders saved.' ); closeFm(); } )
						.catch( function ( er ) { sv.disabled = false; toast( er.message, 'error' ); } );
				} );
			} );
		}

		// Assign the open submission to a folder (change handler on the detail pane).
		detail.addEventListener( 'change', function ( e ) {
			var sel = e.target.closest ? e.target.closest( '.vmail-d-folder' ) : null;
			if ( ! sel || ! current ) { return; }
			var fid = sel.value;
			api( 'submission_set_folder', { id: current.id, folder: fid } )
				.then( function () {
					current.folder = fid;
					var it = list.querySelector( '.vmail-inbox-item[data-id="' + current.id + '"]' );
					if ( it ) { it.setAttribute( 'data-folder', fid ); }
					toast( fid ? 'Moved to folder.' : 'Removed from folder.' );
				} )
				.catch( function ( er ) { toast( er.message, 'error' ); } );
		} );

		// ===== Bulk selection =====
		var bulkbar   = document.getElementById( 'vmail-bulkbar' );
		var bulkCount = document.getElementById( 'vmail-bulk-count' );
		var checkAll  = document.getElementById( 'vmail-check-all' );
		function checkedIds() {
			return Array.prototype.slice.call( list.querySelectorAll( '.vmail-check:checked' ) )
				.map( function ( c ) { return parseInt( c.getAttribute( 'data-id' ), 10 ); } );
		}
		function refreshBulk() {
			var ids = checkedIds();
			if ( bulkbar ) { bulkbar.hidden = 0 === ids.length; }
			if ( bulkCount ) { bulkCount.textContent = ids.length + ' selected'; }
		}
		list.addEventListener( 'change', function ( e ) {
			if ( e.target && e.target.classList && e.target.classList.contains( 'vmail-check' ) ) { refreshBulk(); }
		} );
		if ( checkAll ) {
			checkAll.addEventListener( 'change', function () {
				list.querySelectorAll( '.vmail-inbox-item' ).forEach( function ( it ) {
					if ( 'none' === it.style.display || it.classList.contains( 'vmail-del-row' ) ) { return; }
					var cb = it.querySelector( '.vmail-check' );
					if ( cb ) { cb.checked = checkAll.checked; }
				} );
				refreshBulk();
			} );
		}
		var bulkClear = document.getElementById( 'vmail-bulk-clear' );
		if ( bulkClear ) {
			bulkClear.addEventListener( 'click', function () {
				list.querySelectorAll( '.vmail-check:checked' ).forEach( function ( c ) { c.checked = false; } );
				if ( checkAll ) { checkAll.checked = false; }
				refreshBulk();
			} );
		}
		if ( bulkbar ) {
			bulkbar.addEventListener( 'click', function ( e ) {
				var b = e.target.closest( '[data-bulk]' ); if ( ! b ) { return; }
				var action = b.getAttribute( 'data-bulk' );
				var ids = checkedIds();
				if ( ! ids.length ) { return; }
				if ( 'delete' === action && ! window.confirm( 'Move ' + ids.length + ' submission(s) to Deleted?' ) ) { return; }
				api( 'submission_bulk', { ids: JSON.stringify( ids ), bulk: action } )
					.then( function ( r ) {
						ids.forEach( function ( id ) {
							var it = list.querySelector( '.vmail-inbox-item[data-id="' + id + '"]' );
							if ( ! it ) { return; }
							if ( 'delete' === action ) { it.remove(); }
							else if ( 'read' === action ) { it.setAttribute( 'data-read', '1' ); it.classList.remove( 'is-unread' ); }
							else if ( 'done' === action ) { it.setAttribute( 'data-status', 'done' ); }
						} );
						if ( 'read' === action ) { updateUnreadCount(); }
						if ( checkAll ) { checkAll.checked = false; }
						refreshBulk();
						applyFilter();
						toast( ( r && r.count ? r.count : ids.length ) + ' updated.' );
					} )
					.catch( function ( e2 ) { toast( e2.message, 'error' ); } );
			} );
		}

		// ===== Reply composer modal =====
		var replyModal = $( '#vmail-reply-modal' );
		var replyFor = null;
		function openReplyModal( sub ) {
			if ( ! replyModal ) { return; }
			replyFor = sub;
			var t = $( '#vmail-reply-title' ); if ( t ) { t.textContent = 'Reply to ' + ( sub.who || 'submission' ); }
			var s = $( '#vmail-reply-sub' ); if ( s ) { s.textContent = ( sub.form_title || '' ) + ( sub.email ? '  ·  ' + sub.email : '' ); }
			var av = $( '#vmail-reply-avatar' ); if ( av ) { av.textContent = initials( sub.who ); }
			var to = $( '#vmail-reply-to' ); if ( to ) { to.value = sub.email || ''; }
			var subj = $( '#vmail-reply-subject' ); if ( subj ) { subj.value = 'Re: ' + ( sub.form_title || '' ); }
			var body = $( '#vmail-reply-body' ); if ( body ) { body.innerHTML = ''; }
			var tpl = $( '#vmail-reply-tpl' ); if ( tpl ) { tpl.value = ''; }
			var from = $( '#vmail-reply-from' ); if ( from ) { from.value = 'account'; }
			var cr = $( '#vmail-reply-customrow' ); if ( cr ) { cr.hidden = true; }
			replyModal.hidden = false;
			setTimeout( function () { if ( body ) { body.focus(); } }, 30 );
		}
		function closeReplyModal() { if ( replyModal ) { replyModal.hidden = true; } }
		function insertReplyImage() {
			var body = $( '#vmail-reply-body' );
			if ( window.wp && window.wp.media ) {
				var frame = window.wp.media( { title: 'Insert image', multiple: false, library: { type: 'image' }, button: { text: 'Insert' } } );
				frame.on( 'select', function () {
					var att = frame.state().get( 'selection' ).first().toJSON();
					var url = ( att.sizes && att.sizes.large ) ? att.sizes.large.url : att.url;
					if ( url && body ) { body.focus(); document.execCommand( 'insertImage', false, url ); }
				} );
				frame.open();
			} else {
				var url = window.prompt( 'Image URL (must be publicly accessible)' );
				if ( url && body ) { body.focus(); document.execCommand( 'insertImage', false, url ); }
			}
		}
		if ( replyModal ) {
			replyModal.querySelectorAll( '.vmail-tb-btn[data-cmd]' ).forEach( function ( btn ) {
				btn.addEventListener( 'click', function ( e ) {
					e.preventDefault();
					var cmd = btn.getAttribute( 'data-cmd' );
					var body = $( '#vmail-reply-body' ); if ( body ) { body.focus(); }
					if ( 'createLink' === cmd ) {
						var url = window.prompt( 'Link URL' ); if ( url ) { document.execCommand( 'createLink', false, url ); }
					} else if ( 'insertImage' === cmd ) {
						insertReplyImage();
					} else {
						document.execCommand( cmd, false, null );
					}
				} );
			} );
			var colorInput = $( '#vmail-reply-color' );
			if ( colorInput ) {
				colorInput.addEventListener( 'input', function () {
					var body = $( '#vmail-reply-body' ); if ( body ) { body.focus(); }
					document.execCommand( 'foreColor', false, colorInput.value );
				} );
			}
			var fromSel = $( '#vmail-reply-from' );
			if ( fromSel ) {
				fromSel.addEventListener( 'change', function () {
					var cr = $( '#vmail-reply-customrow' );
					if ( cr ) { cr.hidden = ( 'custom' !== fromSel.value ); }
					if ( 'custom' === fromSel.value ) { var ci = $( '#vmail-reply-fromcustom' ); if ( ci ) { ci.focus(); } }
				} );
			}
			var tplSel = $( '#vmail-reply-tpl' );
			if ( tplSel ) {
				tplSel.addEventListener( 'change', function () {
					var opt = tplSel.options[ tplSel.selectedIndex ];
					if ( ! opt || ! opt.value ) { return; }
					var subj = opt.getAttribute( 'data-subject' ) || '';
					var body = opt.getAttribute( 'data-body' ) || '';
					if ( subj ) { var s = $( '#vmail-reply-subject' ); if ( s ) { s.value = subj; } }
					var b = $( '#vmail-reply-body' ); if ( b ) { b.innerHTML = body; }
				} );
			}
			var saveTpl = $( '#vmail-reply-savetpl' );
			if ( saveTpl ) {
				saveTpl.addEventListener( 'click', function () {
					var name = window.prompt( 'Template name' ); if ( ! name ) { return; }
					var subj = ( $( '#vmail-reply-subject' ) || {} ).value || '';
					var body = ( $( '#vmail-reply-body' ) || {} ).innerHTML || '';
					saveTpl.disabled = true;
					api( 'mail_template_save', { name: name, subject: subj, body: body } )
						.then( function ( r ) {
							toast( 'Template saved.' );
							var sel = $( '#vmail-reply-tpl' );
							var tpls = r.templates || [];
							var last = tpls[ tpls.length - 1 ];
							if ( sel && last ) {
								var o = document.createElement( 'option' );
								o.value = last.id; o.textContent = last.name;
								o.setAttribute( 'data-subject', last.subject || '' );
								o.setAttribute( 'data-body', last.body || '' );
								sel.appendChild( o );
							}
						} )
						.catch( function ( e ) { toast( e.message, 'error' ); } )
						.then( function () { saveTpl.disabled = false; } );
				} );
			}
			var sendBtn = $( '#vmail-reply-send' );
			if ( sendBtn ) {
				sendBtn.addEventListener( 'click', function () {
					if ( ! replyFor ) { return; }
					var bodyEl = $( '#vmail-reply-body' );
					if ( ! bodyEl || ! bodyEl.textContent.trim() ) { toast( 'Write a reply first.', 'error' ); return; }
					var payload = {
						id: replyFor.id,
						subject: ( $( '#vmail-reply-subject' ) || {} ).value || '',
						body: bodyEl.innerHTML || ''
					};
					var fromChoice = ( $( '#vmail-reply-from' ) || {} ).value || 'account';
					if ( 'account' === fromChoice ) {
						var opt = $( '#vmail-reply-from' ).options[ 0 ];
						payload.from_email = opt.getAttribute( 'data-email' ) || '';
						payload.from_name = opt.getAttribute( 'data-name' ) || '';
					} else {
						payload.from_email = ( $( '#vmail-reply-fromcustom' ) || {} ).value || '';
						payload.from_name = '';
					}
					var id = replyFor.id;
					var itemEl = list.querySelector( '.vmail-inbox-item[data-id="' + id + '"]' );
					sendBtn.disabled = true;
					api( 'submission_reply', payload )
						.then( function ( r ) {
							toast( 'Reply sent to ' + ( r.to || 'recipient' ) + '.' );
							if ( itemEl ) { itemEl.setAttribute( 'data-read', '1' ); itemEl.classList.remove( 'is-unread' ); itemEl.setAttribute( 'data-status', 'done' ); }
							if ( current && current.id === id ) { current.status = 'done'; var dn = detail.querySelector( '[data-act="done"]' ); if ( dn ) { dn.textContent = 'Reopen'; } }
							updateUnreadCount(); applyFilter();
							closeReplyModal();
						} )
						.catch( function ( e ) { toast( e.message, 'error' ); } )
						.then( function () { sendBtn.disabled = false; } );
				} );
			}
			var closeX = $( '#vmail-reply-close' ), cancelBtn = $( '#vmail-reply-cancel' );
			if ( closeX ) { closeX.addEventListener( 'click', closeReplyModal ); }
			if ( cancelBtn ) { cancelBtn.addEventListener( 'click', closeReplyModal ); }
			replyModal.addEventListener( 'click', function ( e ) { if ( e.target === replyModal ) { closeReplyModal(); } } );
			document.addEventListener( 'keydown', function ( e ) { if ( 'Escape' === e.key && ! replyModal.hidden ) { closeReplyModal(); } } );
		}

		// Auto-open the first submission so the panel isn't empty on load.
		var first = list.querySelector( '.vmail-inbox-item' );
		if ( first ) { load( first.getAttribute( 'data-id' ), first ); }
	}

	/* ---- CAPTCHA master gate: lock the key fields when disabled ---- */
	function initMailCaptchaGate() {
		var toggle = $( '#vmail-captcha-enabled' );
		var body   = $( '#vmail-captcha-body' );
		if ( ! toggle || ! body ) { return; }
		toggle.addEventListener( 'change', function () {
			body.classList.toggle( 'is-locked', ! toggle.checked );
		} );
	}

	/* ---- SMTP: multiple connections + From-based routing + fallback ---- */
	function initMailSmtp() {
		var root = $( '#vmail-smtp' );
		if ( ! root ) { return; }

		var data = { connections: [], routes: [], primary: '', fallback: '' };
		try { data = JSON.parse( ( $( '#vmail-smtp-data' ) || {} ).textContent || '{}' ); } catch ( e ) {}
		var conns  = Array.isArray( data.connections ) ? data.connections : [];
		var routes = Array.isArray( data.routes ) ? data.routes : [];
		var primary = data.primary || '';
		var fallback = data.fallback || '';

		var listEl   = $( '#vmail-conn-list' );
		var routingEl = $( '#vmail-routing' );
		var routeList = $( '#vmail-route-list' );
		var primarySel = $( '#vmail-primary' );
		var fallbackSel = $( '#vmail-fallback' );
		var testConn = $( '#vmail-test-conn' );

		function uid() { return 'conn_' + Math.random().toString( 36 ).slice( 2, 10 ); }

		var SECURE = { tls: 'TLS', ssl: 'SSL', none: 'None' };

		// Provider presets — pick one to fill host/port/encryption (FluentSMTP-style).
		var SMTP_PRESETS = {
			'':         { label: 'Custom / other host', hint: 'Enter the SMTP host, username and password your mail provider gave you.' },
			ionos:      { label: 'IONOS',                 host: 'smtp.ionos.de',                        port: 587, secure: 'tls', hint: 'Username = your full IONOS mailbox address, password = that mailbox\u2019s password.' },
			gmail:      { label: 'Gmail / Google Workspace', host: 'smtp.gmail.com',                    port: 587, secure: 'tls', hint: 'Username = your Gmail address, password = a Google App Password (not your normal login) with 2FA on.' },
			outlook:    { label: 'Outlook / Office 365',  host: 'smtp.office365.com',                   port: 587, secure: 'tls', hint: 'Username = your email address, password = your account or app password.' },
			sendgrid:   { label: 'SendGrid',              host: 'smtp.sendgrid.net',                    port: 587, secure: 'tls', hint: 'Username = the literal word "apikey", password = your SendGrid API key.' },
			mailgun:    { label: 'Mailgun',               host: 'smtp.mailgun.org',                     port: 587, secure: 'tls', hint: 'Username = your Mailgun SMTP login, password = its SMTP password (from Domain settings).' },
			ses:        { label: 'Amazon SES (eu-central-1)', host: 'email-smtp.eu-central-1.amazonaws.com', port: 587, secure: 'tls', hint: 'Username & password = your SES SMTP credentials (not your AWS keys). Change the region in the host if needed.' },
			brevo:      { label: 'Brevo (Sendinblue)',    host: 'smtp-relay.brevo.com',                 port: 587, secure: 'tls', hint: 'Username = your Brevo account email, password = your SMTP key (SMTP & API settings).' },
			postmark:   { label: 'Postmark',              host: 'smtp.postmarkapp.com',                 port: 587, secure: 'tls', hint: 'Username and password are both your Postmark Server API token.' },
			zoho:       { label: 'Zoho Mail',             host: 'smtp.zoho.com',                        port: 587, secure: 'tls', hint: 'Username = your Zoho email, password = an app-specific password.' },
			gmx:        { label: 'GMX',                   host: 'mail.gmx.net',                         port: 587, secure: 'tls', hint: 'First enable POP3/IMAP in GMX settings (Home \u2192 Settings \u2192 POP3/IMAP). Then: username = your full GMX address, password = your normal GMX password.' },
			webde:      { label: 'web.de',                host: 'smtp.web.de',                          port: 587, secure: 'tls', hint: 'First enable POP3/IMAP in web.de settings. Then: username = your full web.de address, password = your normal password.' }
		};
		function providerFor( host ) {
			host = ( host || '' ).toLowerCase();
			for ( var k in SMTP_PRESETS ) {
				if ( k && SMTP_PRESETS[ k ].host && SMTP_PRESETS[ k ].host.toLowerCase() === host ) { return k; }
			}
			return '';
		}

		var SMTP_GUIDES = {
			gmail: { title: 'Gmail / Google Workspace', note: 'Gmail needs an App Password, not your normal login. Free Gmail sends roughly 500 emails/day.', steps: [
				'Turn on 2-Step Verification: myaccount.google.com/security \u2192 2-Step Verification.',
				'Open myaccount.google.com/apppasswords and create an app password (name it "Velox").',
				'Copy the 16-character code Google gives you.',
				'Here, set Provider = Gmail (fills smtp.gmail.com, port 587, TLS).',
				'Username = your full Gmail address.',
				'Password = that 16-character App Password (spaces are fine).',
				'From address = the same Gmail address (Gmail forces the sender to match).',
				'Save connections, then Test connection, then Send test.'
			] },
			gmx: { title: 'GMX', note: 'GMX works with your normal password once IMAP/POP is enabled.', steps: [
				'Log in to GMX webmail \u2192 Home \u2192 Settings \u2192 POP3/IMAP.',
				'Turn on POP3/IMAP access and save.',
				'Here, set Provider = GMX (fills mail.gmx.net, port 587, TLS).',
				'Username = your full GMX address. Password = your normal GMX password.',
				'From address = your GMX address.',
				'Save connections, then Test connection, then Send test.'
			] },
			webde: { title: 'web.de', note: 'web.de works with your normal password once IMAP/POP is enabled.', steps: [
				'Log in to web.de \u2192 Settings \u2192 enable POP3/IMAP access.',
				'Here, set Provider = web.de (fills smtp.web.de, port 587, TLS).',
				'Username = your full web.de address. Password = your normal password.',
				'From address = your web.de address.',
				'Save connections, then Test connection, then Send test.'
			] },
			ionos: { title: 'IONOS', note: 'Uses the mailbox password directly.', steps: [
				'Make sure the mailbox exists in your IONOS control panel.',
				'Here, set Provider = IONOS (fills smtp.ionos.de, port 587, TLS).',
				'Username = your full IONOS mailbox address. Password = that mailbox\u2019s password.',
				'From address = the same mailbox address.',
				'Save connections, then Test connection, then Send test.'
			] },
			outlook: { title: 'Outlook / Hotmail (personal)', note: 'Not supported.', steps: [
				'Microsoft has retired plain SMTP (basic auth) for personal Outlook.com accounts \u2014 even app passwords no longer work; it needs OAuth, which Velox does not do.',
				'Use Gmail, GMX or web.de instead, or a free sending service like Brevo (300/day) which also lets you send from your own domain.'
			] },
			sendgrid: { title: 'SendGrid', note: 'Free tier available; verify a sender first.', steps: [
				'Create a SendGrid account and verify a Single Sender or your domain.',
				'Settings \u2192 API Keys \u2192 Create API Key with "Mail Send" permission. Copy it.',
				'Here, set Provider = SendGrid (fills smtp.sendgrid.net, port 587, TLS).',
				'Username = the literal word apikey. Password = your API key.',
				'From address = your verified sender.',
				'Save, then Test connection, then Send test.'
			] },
			mailgun: { title: 'Mailgun', note: 'Requires a verified domain.', steps: [
				'Add and verify your sending domain in Mailgun (DNS records).',
				'Open the domain \u2192 SMTP credentials to get the SMTP login + password.',
				'Here, set Provider = Mailgun (fills smtp.mailgun.org, port 587, TLS).',
				'Username = the SMTP login (postmaster@your-domain). Password = its SMTP password.',
				'From address = an address on the verified domain.',
				'Save, then Test connection, then Send test.'
			] },
			ses: { title: 'Amazon SES', note: 'Use SES SMTP credentials, not your AWS keys.', steps: [
				'Verify a domain or email in SES and request production access (out of sandbox).',
				'SES \u2192 SMTP settings \u2192 Create SMTP credentials \u2192 copy the username + password.',
				'Here, set Provider = Amazon SES, and change the region in the host if you are not on eu-central-1.',
				'Username + Password = the SES SMTP credentials you just created.',
				'From address = your verified sender.',
				'Save, then Test connection, then Send test.'
			] },
			brevo: { title: 'Brevo (Sendinblue)', note: 'Free 300 emails/day; good for client sites.', steps: [
				'Create a Brevo account.',
				'SMTP & API \u2192 SMTP tab \u2192 note your login email and generate an SMTP key.',
				'Here, set Provider = Brevo (fills smtp-relay.brevo.com, port 587, TLS).',
				'Username = your Brevo account email. Password = the SMTP key.',
				'From address = a verified sender (verify your domain for best delivery).',
				'Save, then Test connection, then Send test.'
			] },
			postmark: { title: 'Postmark', note: 'Token is used for both username and password.', steps: [
				'Create a Postmark server and verify a Sender Signature or domain.',
				'Copy the Server API Token.',
				'Here, set Provider = Postmark (fills smtp.postmarkapp.com, port 587, TLS).',
				'Username AND Password = your Server API Token (the same value in both).',
				'From address = your verified signature.',
				'Save, then Test connection, then Send test.'
			] },
			zoho: { title: 'Zoho Mail', note: 'Uses an app-specific password.', steps: [
				'Enable IMAP/SMTP access in Zoho Mail settings.',
				'Generate an app-specific password in your Zoho account security settings.',
				'Here, set Provider = Zoho (fills smtp.zoho.com, port 587, TLS).',
				'Username = your Zoho email. Password = the app-specific password.',
				'Save, then Test connection, then Send test.'
			] },
			'': { title: 'Custom / other host', note: '', steps: [
				'Get the SMTP host, port, username and password from your mail provider or hosting panel.',
				'Port 587 = TLS (STARTTLS), port 465 = SSL. Pick the matching Encryption.',
				'Username is usually your full email address; password is that mailbox\u2019s password.',
				'From address = an address on the same account or domain.',
				'Save, then Test connection, then Send test \u2014 the test message tells you exactly what is wrong if it fails.'
			] }
		};

		function connCard( c, idx ) {
			var card = document.createElement( 'div' );
			card.className = 'vmail-conn';
			card.setAttribute( 'data-id', c.id );
			var secOpts = Object.keys( SECURE ).map( function ( v ) {
				return '<option value="' + v + '"' + ( c.secure === v ? ' selected' : '' ) + '>' + SECURE[ v ] + '</option>';
			} ).join( '' );
			var curProv = providerFor( c.host );
			var provOpts = Object.keys( SMTP_PRESETS ).map( function ( v ) {
				return '<option value="' + v + '"' + ( curProv === v ? ' selected' : '' ) + '>' + SMTP_PRESETS[ v ].label + '</option>';
			} ).join( '' );
			card.innerHTML =
				'<div class="vmail-conn-top">' +
					'<select class="velox-select vmail-c-provider" title="Pick your provider to fill the server settings">' + provOpts + '</select>' +
					'<input type="text" class="velox-input vmail-c-label" value="' + escapeHtml( c.label || '' ) + '" placeholder="Connection name (e.g. Transactional)">' +
					'<button type="button" class="vmail-conn-del" title="Remove connection" aria-label="Remove connection">&times;</button>' +
				'</div>' +
				'<div class="vmail-conn-grid">' +
					'<label class="vmail-cf vmail-cf--host"><span>Host</span><input type="text" class="velox-input vmail-c-host" value="' + escapeHtml( c.host || '' ) + '" placeholder="smtp.example.com"></label>' +
					'<label class="vmail-cf vmail-cf--port"><span>Port</span><input type="number" class="velox-input vmail-c-port" value="' + escapeHtml( String( c.port || 587 ) ) + '"></label>' +
					'<label class="vmail-cf vmail-cf--sec"><span>Encryption</span><select class="velox-select vmail-c-secure">' + secOpts + '</select></label>' +
					'<label class="vmail-cf"><span>Username</span><input type="text" class="velox-input vmail-c-user" value="' + escapeHtml( c.user || '' ) + '" autocomplete="off"></label>' +
					'<label class="vmail-cf"><span>Password</span><input type="password" class="velox-input vmail-c-pass" value="' + escapeHtml( c.pass || '' ) + '" autocomplete="new-password"></label>' +
					'<label class="vmail-cf"><span>From address</span><input type="email" class="velox-input vmail-c-from" value="' + escapeHtml( c.from || '' ) + '" placeholder="hello@example.com"></label>' +
					'<label class="vmail-cf"><span>From name</span><input type="text" class="velox-input vmail-c-fromname" value="' + escapeHtml( c.from_name || '' ) + '"></label>' +
					'<label class="vmail-cf"><span>Reply-To</span><input type="email" class="velox-input vmail-c-replyto" value="' + escapeHtml( c.reply_to || '' ) + '" placeholder="replies@example.com"></label>' +
				'</div>' +
				'<p class="velox-hint vmail-conn-hint">' + escapeHtml( ( SMTP_PRESETS[ curProv ] || SMTP_PRESETS[''] ).hint ) + '</p>';
			card.querySelector( '.vmail-conn-del' ).addEventListener( 'click', function () {
				collect();
				conns = conns.filter( function ( x ) { return x.id !== c.id; } );
				render();
			} );
			// keep label in sync into the routing/select dropdowns live
			card.querySelector( '.vmail-c-label' ).addEventListener( 'input', function () { collect(); syncSelects(); } );
			card.querySelector( '.vmail-c-host' ).addEventListener( 'input', function () { collect(); syncSelects(); } );
			card.querySelector( '.vmail-c-provider' ).addEventListener( 'change', function () {
				var p = SMTP_PRESETS[ this.value ];
				var h = card.querySelector( '.vmail-conn-hint' );
				if ( h && p ) { h.textContent = p.hint; }
				if ( p && p.host ) {
					card.querySelector( '.vmail-c-host' ).value = p.host;
					card.querySelector( '.vmail-c-port' ).value = p.port;
					card.querySelector( '.vmail-c-secure' ).value = p.secure;
					if ( ! card.querySelector( '.vmail-c-label' ).value ) {
						card.querySelector( '.vmail-c-label' ).value = p.label;
					}
					collect();
					syncSelects();
				}
			} );
			return card;
		}

		function routeRow( r ) {
			var row = document.createElement( 'div' );
			row.className = 'vmail-route';
			var matchOpts = [ [ 'from_email', 'From address is' ], [ 'from_name', 'From name is' ], [ 'all', 'All other mail' ] ]
				.map( function ( m ) { return '<option value="' + m[0] + '"' + ( r.match === m[0] ? ' selected' : '' ) + '>' + m[1] + '</option>'; } ).join( '' );
			row.innerHTML =
				'<select class="velox-select velox-select--sm vmail-r-match">' + matchOpts + '</select>' +
				'<input type="text" class="velox-input vmail-r-value" value="' + escapeHtml( r.value || '' ) + '" placeholder="value">' +
				'<span class="vmail-r-arrow">→</span>' +
				'<select class="velox-select velox-select--sm vmail-r-conn"></select>' +
				'<button type="button" class="vmail-route-del" title="Remove rule" aria-label="Remove rule">&times;</button>';
			fillConnSelect( row.querySelector( '.vmail-r-conn' ), r.conn );
			var matchSel = row.querySelector( '.vmail-r-match' );
			var valInput = row.querySelector( '.vmail-r-value' );
			function toggleVal() { valInput.style.visibility = ( matchSel.value === 'all' ) ? 'hidden' : 'visible'; }
			matchSel.addEventListener( 'change', toggleVal );
			toggleVal();
			row.querySelector( '.vmail-route-del' ).addEventListener( 'click', function () {
				collectRoutes();
				var i = Array.prototype.indexOf.call( routeList.children, row );
				if ( i > -1 ) { routes.splice( i, 1 ); }
				row.remove();
			} );
			return row;
		}

		function fillConnSelect( sel, selected ) {
			sel.innerHTML = conns.map( function ( c ) {
				var name = c.label || c.host || c.id;
				return '<option value="' + c.id + '"' + ( c.id === selected ? ' selected' : '' ) + '>' + escapeHtml( name ) + '</option>';
			} ).join( '' );
		}

		function syncSelects() {
			fillConnSelect( primarySel, primary );
			var fOpts = '<option value="">None</option>' + conns.map( function ( c ) {
				var name = c.label || c.host || c.id;
				return '<option value="' + c.id + '"' + ( c.id === fallback ? ' selected' : '' ) + '>' + escapeHtml( name ) + '</option>';
			} ).join( '' );
			fallbackSel.innerHTML = fOpts;
			if ( testConn ) { fillConnSelect( testConn, primary ); }
			// refresh each route's connection dropdown, preserving selection
			$$( '.vmail-r-conn', routeList ).forEach( function ( sel, i ) {
				var cur = routes[ i ] ? routes[ i ].conn : '';
				fillConnSelect( sel, cur );
			} );
		}

		function collect() {
			var out = [];
			$$( '.vmail-conn', listEl ).forEach( function ( card ) {
				out.push( {
					id:        card.getAttribute( 'data-id' ),
					label:     card.querySelector( '.vmail-c-label' ).value,
					host:      card.querySelector( '.vmail-c-host' ).value,
					port:      card.querySelector( '.vmail-c-port' ).value,
					secure:    card.querySelector( '.vmail-c-secure' ).value,
					user:      card.querySelector( '.vmail-c-user' ).value,
					pass:      card.querySelector( '.vmail-c-pass' ).value,
					from:      card.querySelector( '.vmail-c-from' ).value,
					from_name: card.querySelector( '.vmail-c-fromname' ).value,
					reply_to:  ( card.querySelector( '.vmail-c-replyto' ) || {} ).value || ''
				} );
			} );
			conns = out;
		}

		function collectRoutes() {
			var out = [];
			$$( '.vmail-route', routeList ).forEach( function ( row ) {
				out.push( {
					match: row.querySelector( '.vmail-r-match' ).value,
					value: row.querySelector( '.vmail-r-value' ).value,
					conn:  row.querySelector( '.vmail-r-conn' ).value
				} );
			} );
			routes = out;
		}

		function render() {
			listEl.innerHTML = '';
			conns.forEach( function ( c, i ) { listEl.appendChild( connCard( c, i ) ); } );
			routingEl.hidden = conns.length < 1;
			routeList.innerHTML = '';
			routes.forEach( function ( r ) { routeList.appendChild( routeRow( r ) ); } );
			syncSelects();
		}

		$( '#vmail-conn-add' ).addEventListener( 'click', function () {
			collect();
			conns.push( { id: uid(), label: '', host: '', port: 587, secure: 'tls', user: '', pass: '', from: '', from_name: '', reply_to: '' } );
			render();
		} );

		var guideBtn = $( '#vmail-conn-guide' );
		if ( guideBtn ) {
			guideBtn.addEventListener( 'click', function () {
				collect();
				var def = 'gmail';
				if ( conns[0] && conns[0].host ) { def = providerFor( conns[0].host ); }
				var overlay = document.createElement( 'div' );
				overlay.className = 'vmail-fm-overlay';
				var provOpts = Object.keys( SMTP_GUIDES ).map( function ( k ) {
					return '<option value="' + k + '"' + ( k === def ? ' selected' : '' ) + '>' + escapeHtml( SMTP_GUIDES[ k ].title ) + '</option>';
				} ).join( '' );
				overlay.innerHTML =
					'<div class="vmail-fm vmail-guide">' +
						'<div class="vmail-fm-head"><strong>SMTP setup guide</strong><button type="button" class="vmail-fm-x" aria-label="Close">&times;</button></div>' +
						'<select class="velox-select vmail-guide-sel">' + provOpts + '</select>' +
						'<div class="vmail-guide-body"></div>' +
					'</div>';
				document.body.appendChild( overlay );
				var gbody = overlay.querySelector( '.vmail-guide-body' );
				function drawGuide( k ) {
					var g = SMTP_GUIDES[ k ] || SMTP_GUIDES[''];
					gbody.innerHTML =
						( g.note ? '<p class="vmail-guide-note">' + escapeHtml( g.note ) + '</p>' : '' ) +
						'<ol class="vmail-guide-steps">' + g.steps.map( function ( s ) { return '<li>' + escapeHtml( s ) + '</li>'; } ).join( '' ) + '</ol>';
				}
				drawGuide( def );
				overlay.querySelector( '.vmail-guide-sel' ).addEventListener( 'change', function () { drawGuide( this.value ); } );
				function closeGuide() { overlay.remove(); }
				overlay.querySelector( '.vmail-fm-x' ).addEventListener( 'click', closeGuide );
				overlay.addEventListener( 'click', function ( e ) { if ( e.target === overlay ) { closeGuide(); } } );
			} );
		}

		var routeAdd = $( '#vmail-route-add' );
		if ( routeAdd ) {
			routeAdd.addEventListener( 'click', function () {
				collectRoutes();
				if ( ! conns.length ) { toast( 'Add a connection first.', 'error' ); return; }
				var row = routeRow( { match: 'from_email', value: '', conn: conns[0].id } );
				routeList.appendChild( row );
			} );
		}

		primarySel.addEventListener( 'change', function () { primary = primarySel.value; } );
		fallbackSel.addEventListener( 'change', function () { fallback = fallbackSel.value; } );

		var saveBtn = $( '#vmail-smtp-save' );
		saveBtn.addEventListener( 'click', function () {
			collect();
			collectRoutes();
			primary = primarySel.value;
			fallback = fallbackSel.value;
			saveBtn.disabled = true;
			api( 'mail_save_routing', {
				connections: JSON.stringify( conns ),
				routes:      JSON.stringify( routes ),
				primary:     primary,
				fallback:    fallback
			} )
				.then( function ( r ) {
					toast( r.message || 'Saved.', 'success' );
					if ( r.connections ) { conns = r.connections; }
					if ( r.routes ) { routes = r.routes; }
					primary = r.primary || ''; fallback = r.fallback || '';
					render();
				} )
				.catch( function ( e ) { toast( e.message, 'error' ); } )
				.then( function () { saveBtn.disabled = false; } );
		} );

		var testBtn = $( '#vmail-test' );
		if ( testBtn ) {
			testBtn.addEventListener( 'click', function () {
				var to = $( '#vmail-test-to' ).value;
				var conn = testConn ? testConn.value : '';
				testBtn.disabled = true;
				api( 'mail_test', { to: to, conn: conn } )
					.then( function ( r ) { toast( r.message, r.ok ? 'success' : 'error' ); } )
					.catch( function ( e ) { toast( e.message, 'error' ); } )
					.then( function () { testBtn.disabled = false; } );
			} );
		}

		var connTestBtn = $( '#vmail-conn-test' );
		if ( connTestBtn ) {
			connTestBtn.addEventListener( 'click', function () {
				collect();
				var id = testConn ? testConn.value : ( conns[0] && conns[0].id );
				var c = conns.filter( function ( x ) { return x.id === id; } )[0] || conns[0];
				if ( ! c || ! c.host ) { toast( 'Add a connection with a host first.', 'error' ); return; }
				connTestBtn.disabled = true;
				var orig = connTestBtn.textContent;
				connTestBtn.textContent = 'Testing…';
				api( 'mail_conn_test', { host: c.host, port: c.port, secure: c.secure, user: c.user, pass: c.pass } )
					.then( function ( r ) { toast( r.message, r.ok ? 'success' : 'error' ); } )
					.catch( function ( e ) { toast( e.message, 'error' ); } )
					.then( function () { connTestBtn.disabled = false; connTestBtn.textContent = orig; } );
			} );
		}

		// The Mail page has its own settings toggles (Send-through-SMTP, sender identity,
		// CAPTCHA) that aren't on the Settings page, so auto-save them here on change.
		var mailPage = document.querySelector( '.velox-main' );
		if ( mailPage && ! mailPage._veloxMailAutosave ) {
			mailPage._veloxMailAutosave = true;
			var mtimers = {};
			function mailSaveEl( el ) {
				var key = el.getAttribute( 'data-setting' );
				if ( ! key ) { return; }
				var val = ( 'checkbox' === el.type ) ? ( el.checked ? 1 : 0 ) : el.value;
				var p = {};
				p[ key ] = val;
				api( 'save_settings', p ).then( flashSaved ).catch( function ( er ) { toast( ( er && er.message ) || 'Could not save', 'error' ); } );
			}
			mailPage.addEventListener( 'change', function ( e ) {
				var el = e.target.closest ? e.target.closest( '[data-setting]' ) : null;
				if ( el ) { mailSaveEl( el ); }
			} );
			mailPage.addEventListener( 'input', function ( e ) {
				var el = e.target.closest ? e.target.closest( '[data-setting]' ) : null;
				if ( ! el ) { return; }
				var t = el.tagName;
				if ( 'TEXTAREA' !== t && ! ( 'INPUT' === t && /^(text|email|url|password|search)$/.test( el.type ) ) ) { return; }
				var key = el.getAttribute( 'data-setting' );
				clearTimeout( mtimers[ key ] );
				mtimers[ key ] = setTimeout( function () { mailSaveEl( el ); }, 700 );
			} );
		}

		render();
	}

	function initMailBuilder() {
		var dataEl = $( '#vmail-data' );
		if ( ! dataEl ) { return; }
		var form, meta;
		try { form = JSON.parse( dataEl.textContent ); } catch ( e ) { return; }
		try { meta = JSON.parse( ( $( '#vmail-meta' ) || {} ).textContent || '{}' ); } catch ( e2 ) { meta = {}; }
		form.fields = form.fields || [];
		form.emails = form.emails || [];
		// PHP encodes an empty style as a JSON array []; JS then tacks properties onto
		// that array, which JSON.stringify silently drops on save. Force a plain object.
		if ( ! form.style || typeof form.style !== 'object' || Array.isArray( form.style ) ) { form.style = {}; }

		function svgIcon( p ) { return '<svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">' + p + '</svg>'; }
		var TYPES = {
			text:        { label: 'Single line', short: 'Text', icon: svgIcon('<path d="M4 7h16M4 12h11M4 17h7"/>'), opts: false, cat: 'general' },
			email:       { label: 'Email',       icon: svgIcon('<rect x="2.5" y="5" width="19" height="14" rx="2.5"/><path d="M3 7l9 6 9-6"/>'), opts: false, cat: 'general' },
			tel:         { label: 'Phone',       icon: svgIcon('<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L16 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z"/>'), opts: false, cat: 'general' },
			number:      { label: 'Number',      icon: svgIcon('<path d="M4 9h16M4 15h16M9 4l-2 16M17 4l-2 16"/>'), opts: false, cat: 'general' },
			textarea:    { label: 'Paragraph', short: 'Textarea',   icon: svgIcon('<rect x="3" y="5" width="18" height="14" rx="2.5"/><path d="M3 10h18"/>'), opts: false, cat: 'general' },
			select:      { label: 'Dropdown', short: 'Dropdown',    icon: svgIcon('<path d="M6 9l6 6 6-6"/>'), opts: true,  cat: 'general' },
			radio:       { label: 'Radio',       icon: svgIcon('<circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="3.2" fill="currentColor" stroke="none"/>'), opts: true,  cat: 'general' },
			checkbox:    { label: 'Checkbox', short: 'Checkbox',    icon: svgIcon('<rect x="4" y="4" width="16" height="16" rx="4"/><path d="M8.5 12l2.5 2.5 5-5"/>'), opts: false, cat: 'general' },
			name:        { label: 'Name',        icon: svgIcon('<circle cx="12" cy="8" r="4"/><path d="M5 21a7 7 0 0 1 14 0"/>'), opts: false, cat: 'advanced' },
			multiselect: { label: 'Multi-select', short: 'Multi',icon: svgIcon('<path d="M9 6h11M9 12h11M9 18h11M4 6h.01M4 12h.01M4 18h.01"/>'), opts: true,  cat: 'advanced' },
			country:     { label: 'Country',     icon: svgIcon('<circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3a14 14 0 0 1 0 18a14 14 0 0 1 0-18z"/>'), opts: false, cat: 'advanced' },
			url:         { label: 'Website URL', short: 'URL', icon: svgIcon('<path d="M10 13a5 5 0 0 0 7 0l2-2a5 5 0 0 0-7-7l-1 1"/><path d="M14 11a5 5 0 0 0-7 0l-2 2a5 5 0 0 0 7 7l1-1"/>'), opts: false, cat: 'advanced' },
			date:        { label: 'Date',        icon: svgIcon('<rect x="3.5" y="5" width="17" height="16" rx="3"/><path d="M16 3v4M8 3v4M3.5 10h17"/>'), opts: false, cat: 'advanced' },
			time:        { label: 'Time',        icon: svgIcon('<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>'), opts: false, cat: 'advanced' },
			range:       { label: 'Slider', short: 'Slider', icon: svgIcon('<path d="M3 12h18"/><circle cx="9" cy="12" r="3.2" fill="currentColor" stroke="none"/>'), opts: false, cat: 'advanced' },
			rating:      { label: 'Star rating', short: 'Rating', icon: svgIcon('<path d="M12 2.5l2.9 6 6.6.9-4.8 4.6 1.2 6.5L12 17.8 6.1 20.5l1.2-6.5L2.5 9.4l6.6-.9z"/>'), opts: false, cat: 'advanced' },
			address:     { label: 'Address',     icon: svgIcon('<path d="M12 21s7-6 7-11a7 7 0 1 0-14 0c0 5 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/>'), opts: false, cat: 'advanced' },
			file:        { label: 'File upload', short: 'File', icon: svgIcon('<path d="M14 3v4a1 1 0 0 0 1 1h4"/><path d="M17 21H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h7l5 5v11a2 2 0 0 1-2 2z"/><path d="M12 11v6M9.5 13.5 12 11l2.5 2.5"/>'), opts: false, cat: 'advanced' },
			consent:     { label: 'Consent',     icon: svgIcon('<path d="M9 12l2 2 4-4"/><path d="M21 11.5V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>'), opts: false, cat: 'advanced' },
			heading:     { label: 'Section heading', short: 'Heading', icon: svgIcon('<path d="M6 4v16M18 4v16M6 12h12"/>'), opts: false, cat: 'layout' },
			captcha:     { label: 'CAPTCHA',     icon: svgIcon('<path d="M12 3l8 4v5c0 5-3.5 8-8 9c-4.5-1-8-4-8-9V7z"/><path d="M9 12l2 2 4-4"/>'), opts: false, cat: 'advanced' }
		};
		var CATS = { general: 'General fields', advanced: 'Advanced fields', layout: 'Layout' };

		var canvas     = $( '#vmail-canvas' );
		var palette    = $( '#vmail-palette' );
		var inspector  = $( '#vmail-inspector' );
		var emailsWrap = $( '#vmail-emails' );
		var selected   = -1;
		var dragFrom   = -1;
		var palDrag    = null;   // field type currently dragged from the palette
		var clipboard  = null;
		var openPreviewOverlay = null;   // set by initPreview, called from the style editor
		var openStyleEditor    = null;   // set by initStyleEditor, called from the preview
		var treeTab    = 'all';  // style-editor structure panel filter
		var inspZone   = inspector ? inspector.closest( '.vmail-sb-zone--insp' ) : null;
		if ( inspZone ) { inspZone.classList.add( 'is-collapsed' ); }
		function openInspector() { if ( inspZone ) { inspZone.classList.remove( 'is-collapsed' ); } }
		function closeInspector() { if ( inspZone ) { inspZone.classList.add( 'is-collapsed' ); } selected = -1; renderCanvas(); }

		function slugify( s ) { return ( s || 'field' ).toLowerCase().replace( /[^a-z0-9]+/g, '_' ).replace( /^_|_$/g, '' ) || 'field'; }
		function reKey() {
			var used = {};
			form.fields.forEach( function ( f ) {
				var base = ( f.key && /^[a-z0-9_]+$/.test( f.key ) ) ? f.key : slugify( f.label );
				var k = base, n = 2;
				while ( used[ k ] ) { k = base + '_' + ( n++ ); }
				used[ k ] = 1; f.key = k;
			} );
		}
		function normalize( f ) {
			return {
				key: f.key || '', type: f.type || 'text', label: f.label != null ? f.label : '',
				required: !! f.required, placeholder: f.placeholder || '', options: f.options || '',
				'default': f['default'] || '', help: f.help || '',
				width: ( f.width === 'half' || f.width === 'third' ) ? f.width : 'full',
				css: f.css || '', content: f.content || '',
				min: f.min != null ? f.min : '', max: f.max != null ? f.max : '', step: f.step != null ? f.step : '',
				accept: f.accept != null ? f.accept : 'images,pdf,docs', maxsize: f.maxsize != null ? f.maxsize : '5',
				pattern: f.pattern || '', pattern_msg: f.pattern_msg || '',
				calc: f.calc || '', calc_prefix: f.calc_prefix || '', calc_suffix: f.calc_suffix || '',
				cond: ( f.cond && f.cond.rules && f.cond.rules.length ) ? f.cond : null,
				_lockKey: !! ( f.key && /^[a-z0-9_]+$/.test( f.key ) )
			};
		}
		form.fields = form.fields.map( normalize );
		reKey();

		function optList( f ) { return ( f.options || '' ).split( '\n' ).map( function ( s ) { return s.trim(); } ).filter( Boolean ); }

		/* =========================================================
		   Shared live preview — the real, typeable form. Used by BOTH
		   the Style editor stage and the Preview button so they always
		   match each other and the front-end output.
		   ========================================================= */
		function pfTypeAttr( t ) { return [ 'email', 'tel', 'number', 'url', 'date' ].indexOf( t ) >= 0 ? t : 'text'; }
		function previewFieldHtml( f ) {
			var key  = escapeHtml( f.key );
			var star = f.required ? ' <span class="rq">*</span>' : '';
			var ph   = escapeHtml( f.placeholder || '' );
			var lbl  = f.label ? '<label class="vse-pf-label">' + escapeHtml( f.label ) + star + '</label>' : '';
			var help = f.help ? '<span class="vse-pf-help">' + escapeHtml( f.help ) + '</span>' : '';
			if ( f.type === 'consent' || f.type === 'checkbox' ) {
				return '<div class="vse-pf-field vse-pf-consent" data-fkey="' + key + '"><label class="vse-pf-check"><input type="checkbox"> <span>' + escapeHtml( f.label || '' ) + star + '</span></label>' + help + '</div>';
			}
			var inner;
			if ( f.type === 'textarea' ) {
				inner = '<textarea class="vse-pf-input ta" data-fkey="' + key + '" placeholder="' + ph + '"></textarea>';
			} else if ( f.type === 'select' || f.type === 'country' ) {
				var os = f.type === 'country' ? [ 'Germany', 'Switzerland', 'Austria' ] : optList( f );
				inner = '<select class="vse-pf-input" data-fkey="' + key + '"><option value="">' + ( f.type === 'country' ? 'Select a country\u2026' : '\u2014' ) + '</option>' +
					os.map( function ( o ) { return '<option>' + escapeHtml( o ) + '</option>'; } ).join( '' ) + '</select>';
			} else if ( f.type === 'radio' || f.type === 'multiselect' ) {
				var it = f.type === 'radio' ? 'radio' : 'checkbox';
				var nm = 'vmp_' + key;
				inner = '<div class="vse-pf-choices" data-fkey="' + key + '">' + optList( f ).map( function ( o ) {
					return '<label class="vse-pf-choice"><input type="' + it + '" name="' + nm + '"> <span>' + escapeHtml( o ) + '</span></label>';
				} ).join( '' ) + '</div>';
			} else if ( f.type === 'name' ) {
				var ol = optList( f );
				inner = '<div class="vse-pf-name" data-fkey="' + key + '"><input class="vse-pf-input" placeholder="' + escapeHtml( ol[0] || 'First name' ) + '"><input class="vse-pf-input" placeholder="' + escapeHtml( ol[1] || 'Last name' ) + '"></div>';
			} else {
				inner = '<input class="vse-pf-input" type="' + pfTypeAttr( f.type ) + '" data-fkey="' + key + '" placeholder="' + ph + '" value="' + escapeHtml( f['default'] || '' ) + '">';
			}
			return '<div class="vse-pf-field" data-fkey="' + key + '">' + lbl + inner + help + '</div>';
		}
		function buildFormPreviewHtml() {
			var rows = '';
			form.fields.forEach( function ( f ) {
				if ( f.type === 'step' || f.type === 'html' || f.type === 'captcha' ) { return; }
				rows += previewFieldHtml( f );
			} );
			if ( ! rows ) { rows = '<div class="vse-pf-empty">Add a field to see it here.</div>'; }
			var header = form.show_title && ( form.title || '' ).trim()
				? '<div class="vse-pf-header"><h3>' + escapeHtml( form.title ) + '</h3></div>'
				: '';
			return header +
				rows +
				'<div class="vse-pf-submit-wrap"><button type="button" class="vse-pf-submit">' + escapeHtml( form.submit_label || 'Submit' ) + '</button></div>';
		}

		/* ---- CSS generation (front-end-accurate, incl. per-field overrides) ---- */
		function pfPx( v ) { return ( v === '' || v == null ) ? '' : ( /[a-z%]/i.test( String( v ) ) ? v : v + 'px' ); }
		function pfShadow( k ) {
			return { none: 'none', soft: '0 1px 3px rgba(16,24,40,.10)', medium: '0 8px 20px -6px rgba(16,24,40,.22)', strong: '0 16px 40px -8px rgba(16,24,40,.32)' }[ k ] || '';
		}
		function pfRule( sel, o ) {
			var d = '';
			var mode = o.bgMode || 'color';
			if ( 'gradient' === mode && o.gradFrom && o.gradTo ) {
				d += 'radial' === ( o.gradType || 'linear' )
					? 'background-image:radial-gradient(circle,' + o.gradFrom + ',' + o.gradTo + ');'
					: 'background-image:linear-gradient(' + ( isNaN( parseFloat( o.gradAngle ) ) ? 90 : parseFloat( o.gradAngle ) ) + 'deg,' + o.gradFrom + ',' + o.gradTo + ');';
			} else if ( 'image' === mode && o.imgUrl ) {
				d += 'background-image:url(' + o.imgUrl + ');';
				d += 'background-size:' + ( o.imgSize || 'cover' ) + ';';
				d += 'background-position:' + ( o.imgPos || 'center' ) + ';';
				d += 'background-repeat:' + ( o.imgRepeat || 'no-repeat' ) + ';';
				if ( o.bg ) { d += 'background-color:' + o.bg + ';'; }
			} else if ( o.bg ) {
				d += 'background:' + o.bg + ';';
			}
			if ( o.color ) { d += 'color:' + o.color + ';'; }
			if ( o.fs ) { d += 'font-size:' + pfPx( o.fs ) + ';'; }
			if ( o.fw ) { d += 'font-weight:' + o.fw + ';'; }
			if ( o.lh != null && '' !== o.lh ) { d += 'line-height:' + o.lh + ';'; }
			if ( o.ls != null && '' !== o.ls ) { d += 'letter-spacing:' + pfPx( o.ls ) + ';'; }
			if ( o.radius ) { d += 'border-radius:' + pfPx( o.radius ) + ';'; }
			if ( o.border ) { d += 'border-width:' + pfPx( o.border ) + ';border-style:solid;'; }
			if ( o.borderColor ) { d += 'border-color:' + o.borderColor + ';'; }
			if ( o.w ) { d += 'width:' + pfPx( o.w ) + ';'; }
			if ( o.h ) { d += 'height:' + pfPx( o.h ) + ';'; }
			if ( o.minh ) { d += 'min-height:' + pfPx( o.minh ) + ';'; }
			if ( o.maxw ) { d += 'max-width:' + pfPx( o.maxw ) + ';'; }
			if ( o.shadow ) {
				if ( 'custom' === o.shadow ) {
					d += 'box-shadow:' + ( o.shInset ? 'inset ' : '' ) + pfPx( o.shX || 0 ) + ' ' + pfPx( o.shY || 0 ) + ' ' +
						pfPx( o.shBlur || 0 ) + ' ' + pfPx( o.shSpread || 0 ) + ' ' + ( o.shColor || 'rgba(16,24,40,.22)' ) + ';';
				} else {
					d += 'box-shadow:' + pfShadow( o.shadow ) + ';';
				}
			}
			if ( o.pt != null || o.pr != null || o.pb != null || o.pl != null ) {
				d += 'padding:' + pfPx( o.pt || 0 ) + ' ' + pfPx( o.pr || 0 ) + ' ' + pfPx( o.pb || 0 ) + ' ' + pfPx( o.pl || 0 ) + ';';
			}
			if ( o.mt != null || o.mr != null || o.mb != null || o.ml != null ) {
				d += 'margin:' + pfPx( o.mt || 0 ) + ' ' + pfPx( o.mr || 0 ) + ' ' + pfPx( o.mb || 0 ) + ' ' + pfPx( o.ml || 0 ) + ';';
			}
			return d ? ( sel + '{' + d + '}' ) : '';
		}
		function formPreviewCss( scope ) {
			var S = form.style || {};
			var css = '';
			var f = S.form || {}, h = S.header || {}, l = S.labels || {}, inp = S.inputs || {}, sub = S.submit || {};
			css += pfRule( scope, f );
			css += pfRule( scope + ' h3', h );
			css += pfRule( scope + ' .vse-pf-label', l );
			css += pfRule( scope + ' .vse-pf-input', inp );
			var wrapJust = { left: 'flex-start', center: 'center', right: 'flex-end', full: 'stretch' }[ sub.align || 'center' ];
			css += scope + ' .vse-pf-submit-wrap{justify-content:' + wrapJust + ';}';
			if ( sub.align === 'full' ) { css += scope + ' .vse-pf-submit{width:100%;}'; }
			css += pfRule( scope + ' .vse-pf-submit', sub );
			if ( sub.hoverBg ) { css += scope + ' .vse-pf-submit:hover{background:' + sub.hoverBg + ';}'; }
			// per-field overrides
			Object.keys( S ).forEach( function ( t ) {
				if ( t.indexOf( 'field:' ) !== 0 ) { return; }
				var key = t.slice( 6 ), o = S[ t ] || {};
				var fs = scope + ' .vse-pf-field[data-fkey="' + key + '"]';
				css += pfRule( fs + ' .vse-pf-label', { color: o.labelColor, fs: o.labelFs, fw: o.labelFw } );
				css += pfRule( fs + ' .vse-pf-input', o );
			} );
			return css;
		}

		/* ---------- palette ---------- */
		var palOpen = { general: true, advanced: true, layout: true };
		function renderPalette( filter ) {
			filter = ( filter || '' ).toLowerCase();
			palette.innerHTML = '';
			Object.keys( CATS ).forEach( function ( cat ) {
				var keys = Object.keys( TYPES ).filter( function ( t ) {
					return TYPES[ t ].cat === cat && ( ! filter || TYPES[ t ].label.toLowerCase().indexOf( filter ) !== -1 );
				} );
				if ( ! keys.length ) { return; }
				// When searching, force-open every group that has matches.
				var open = filter ? true : palOpen[ cat ];
				var group = document.createElement( 'div' );
				group.className = 'vmail-pal-group' + ( open ? ' is-open' : '' );

				var head = document.createElement( 'button' );
				head.type = 'button'; head.className = 'vmail-pal-cat';
				head.innerHTML = '<span>' + CATS[ cat ] + '</span><span class="vmail-pal-chev" aria-hidden="true">' +
					'<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg></span>';
				head.addEventListener( 'click', function () {
					palOpen[ cat ] = ! palOpen[ cat ];
					group.classList.toggle( 'is-open', palOpen[ cat ] );
				} );
				group.appendChild( head );

				var body = document.createElement( 'div' ); body.className = 'vmail-pal-body';
				var grid = document.createElement( 'div' ); grid.className = 'vmail-pal-grid';
				keys.forEach( function ( t ) {
					var b = document.createElement( 'button' );
					b.type = 'button'; b.className = 'vmail-pal-item';
					var locked = ( t === 'captcha' && ! meta.captcha_enabled );
					if ( locked ) { b.className += ' is-locked'; b.title = 'CAPTCHA is switched off in Mail settings'; }
					b.innerHTML = '<span class="vmail-pal-ic">' + TYPES[ t ].icon + '</span><span class="vmail-pal-lbl">' + ( TYPES[ t ].short || TYPES[ t ].label ) + ( locked ? ' \uD83D\uDD12' : '' ) + '</span>';
					b.addEventListener( 'click', function () { addField( t ); } );
					if ( ! locked ) {
						b.setAttribute( 'draggable', 'true' );
						b.addEventListener( 'dragstart', function ( e ) {
							palDrag = t; dragFrom = -1; b.classList.add( 'is-dragging' );
							e.dataTransfer.effectAllowed = 'copy';
							try { e.dataTransfer.setData( 'text/plain', t ); } catch ( err ) {}
						} );
						b.addEventListener( 'dragend', function () { palDrag = null; b.classList.remove( 'is-dragging' ); clearDropMarks(); } );
					}
					grid.appendChild( b );
				} );
				body.appendChild( grid );
				group.appendChild( body );
				palette.appendChild( group );
			} );
			if ( ! palette.children.length ) {
				palette.innerHTML = '<p class="velox-hint" style="padding:6px 2px;">No fields match "' + escapeHtml( filter ) + '".</p>';
			}
		}
		function hasType( t ) { return form.fields.some( function ( f ) { return f.type === t; } ); }
		function addField( type, atIndex ) {
			// CAPTCHA is gated by the global Mail-settings toggle.
			if ( type === 'captcha' && ! meta.captcha_enabled ) {
				toast( 'CAPTCHA is switched off. Enable it under Mail settings → CAPTCHA first.', 'error' );
				return;
			}
			// Consent and CAPTCHA are mutually exclusive — pick one spam/consent gate.
			if ( type === 'captcha' && hasType( 'consent' ) ) { toast( 'Use either a consent box or CAPTCHA — not both. Remove the consent field first.', 'error' ); return; }
			if ( type === 'consent' && hasType( 'captcha' ) ) { toast( 'Use either a consent box or CAPTCHA — not both. Remove the CAPTCHA field first.', 'error' ); return; }
			if ( type === 'captcha' && hasType( 'captcha' ) ) { toast( 'There is already a CAPTCHA on this form.', 'error' ); return; }
			var f = normalize( { type: type, label: TYPES[ type ].label, required: false } );
			if ( type === 'select' || type === 'radio' || type === 'multiselect' ) { f.options = 'Option one\nOption two\nOption three'; }
			if ( type === 'name' ) { f.label = 'Name'; f.options = 'First name\nLast name'; f.width = 'full'; }
			if ( type === 'consent' ) { f.label = 'I accept the privacy policy.'; f.required = true; }
			if ( type === 'captcha' ) { f.label = ''; f.required = false; }
			if ( type === 'html' ) { f.label = ''; f.content = '<p>Your custom HTML here.</p>'; }
			if ( type === 'step' ) { f.label = 'Step ' + ( form.fields.filter( function ( x ) { return x.type === 'step'; } ).length + 1 ); f.width = 'full'; }
			if ( type === 'calc' ) { f.label = 'Total'; f.calc = ''; f.width = 'full'; }
			if ( type === 'time' ) { f.label = 'Time'; }
			if ( type === 'range' ) { f.label = 'Choose a value'; f.min = '0'; f.max = '100'; f.step = '1'; f['default'] = '50'; }
			if ( type === 'rating' ) { f.label = 'Your rating'; f.max = '5'; }
			if ( type === 'address' ) { f.label = 'Address'; f.width = 'full'; }
			if ( type === 'file' ) { f.label = 'Upload a file'; f.accept = 'images,pdf,docs'; f.maxsize = '5'; f.width = 'full'; }
			if ( type === 'heading' ) { f.label = 'Section title'; f.help = 'Optional description for this section.'; f.width = 'full'; }
			if ( atIndex == null || atIndex < 0 || atIndex > form.fields.length ) { atIndex = form.fields.length; }
			form.fields.splice( atIndex, 0, f );
			reKey();
			selected = atIndex;
			openInspector();
			renderCanvas(); renderInspector();
		}

		/* ---------- canvas ---------- */
		function clearDropMarks() {
			if ( ! canvas ) { return; }
			$$( '.vmail-fcard', canvas ).forEach( function ( c ) { c.classList.remove( 'is-drop-before' ); } );
			canvas.classList.remove( 'is-drop-end', 'is-drop-active' );
		}
		function dropIndexFromY( y ) {
			var cards = $$( '.vmail-fcard', canvas );
			for ( var i = 0; i < cards.length; i++ ) {
				var r = cards[ i ].getBoundingClientRect();
				if ( y < r.top + r.height / 2 ) { return i; }
			}
			return cards.length;
		}
		function markDrop( idx ) {
			clearDropMarks();
			canvas.classList.add( 'is-drop-active' );
			var cards = $$( '.vmail-fcard', canvas );
			if ( idx >= cards.length ) { canvas.classList.add( 'is-drop-end' ); }
			else { cards[ idx ].classList.add( 'is-drop-before' ); }
		}
		function bindCanvasDnD() {
			canvas.addEventListener( 'dragover', function ( e ) {
				if ( palDrag == null && dragFrom < 0 ) { return; }
				e.preventDefault();
				try { e.dataTransfer.dropEffect = palDrag != null ? 'copy' : 'move'; } catch ( err ) {}
				markDrop( dropIndexFromY( e.clientY ) );
			} );
			canvas.addEventListener( 'dragleave', function ( e ) {
				if ( ! canvas.contains( e.relatedTarget ) ) { clearDropMarks(); }
			} );
			canvas.addEventListener( 'drop', function ( e ) {
				if ( palDrag == null && dragFrom < 0 ) { return; }
				e.preventDefault();
				var idx = dropIndexFromY( e.clientY );
				clearDropMarks();
				if ( palDrag != null ) {
					var t = palDrag; palDrag = null;
					addField( t, idx );
				} else if ( dragFrom >= 0 ) {
					var from = dragFrom; dragFrom = -1;
					if ( idx > from ) { idx--; }
					if ( idx === from ) { renderCanvas(); return; }
					var moved = form.fields.splice( from, 1 )[ 0 ];
					form.fields.splice( idx, 0, moved );
					selected = idx;
					renderCanvas(); renderInspector();
				}
			} );
		}

		function fieldPreview( f ) {
			var star = f.required ? ' <span class="velox-req">*</span>' : '';
			var lbl  = f.label ? '<span class="velox-form-label">' + escapeHtml( f.label ) + star + '</span>' : '';
			var help = f.help ? '<span class="velox-form-help">' + escapeHtml( f.help ) + '</span>' : '';
			if ( f.type === 'html' ) { return '<div class="vmail-html-prev">' + ( f.content || '<em>Custom HTML</em>' ) + '</div>'; }
			if ( f.type === 'step' ) { return '<div class="vmail-step-prev"><span>\u2398 Page break</span><small>' + escapeHtml( f.label || '' ) + '</small></div>'; }
			if ( f.type === 'calc' ) {
				return lbl + '<div class="vmail-calc-prev"><code>' + escapeHtml( f.calc || 'formula…' ) + '</code></div>' + help;
			}
			if ( f.type === 'captcha' ) { return '<div class="vmail-cap-prev">\u26E8 CAPTCHA widget<small>shown to visitors in place of a consent box</small></div>'; }
			if ( f.type === 'consent' || f.type === 'checkbox' ) {
				return '<label class="velox-form-consent"><input type="checkbox" disabled><span>' + escapeHtml( f.label ) + star + '</span></label>' + help;
			}
			if ( f.type === 'radio' || f.type === 'multiselect' ) {
				var it = f.type === 'radio' ? 'radio' : 'checkbox';
				var rs = optList( f ).map( function ( o ) { return '<label class="velox-form-radio"><input type="' + it + '" disabled> <span>' + escapeHtml( o ) + '</span></label>'; } ).join( '' );
				return lbl + '<div class="velox-form-radios">' + rs + '</div>' + help;
			}
			if ( f.type === 'name' ) {
				var ol = optList( f );
				return lbl + '<div class="velox-form-name-row"><input disabled placeholder="' + escapeHtml( ol[0] || 'First name' ) + '"><input disabled placeholder="' + escapeHtml( ol[1] || 'Last name' ) + '"></div>' + help;
			}
			if ( f.type === 'textarea' ) { return lbl + '<textarea rows="3" disabled placeholder="' + escapeHtml( f.placeholder || '' ) + '">' + escapeHtml( f['default'] || '' ) + '</textarea>' + help; }
			if ( f.type === 'select' || f.type === 'country' ) {
				var inner = f.type === 'country' ? '<option>Germany</option><option>Switzerland</option><option>Austria</option><option>\u2026</option>' : optList( f ).map( function ( o ) { return '<option>' + escapeHtml( o ) + '</option>'; } ).join( '' );
				return lbl + '<select disabled><option>\u2014</option>' + inner + '</select>' + help;
			}
			if ( f.type === 'file' ) {
				return lbl + '<div class="vmail-file-prev"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 11v6M9.5 13.5 12 11l2.5 2.5"/><path d="M20 16.7V19a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-2.3"/></svg><span>Click to upload or drag a file</span></div>' + help;
			}
			if ( f.type === 'heading' ) {
				return '<div class="vmail-heading-prev"><strong>' + escapeHtml( f.label || 'Section heading' ) + '</strong>' + ( f.help ? '<span>' + escapeHtml( f.help ) + '</span>' : '' ) + '</div>';
			}
			if ( f.type === 'rating' ) {
				var rmax = parseInt( f.max, 10 ) || 5; var stars = '';
				for ( var si = 0; si < rmax; si++ ) { stars += '\u2605'; }
				return lbl + '<div class="vmail-rating-prev">' + stars + '</div>' + help;
			}
			if ( f.type === 'address' ) {
				return lbl + '<div class="vmail-addr-prev"><input disabled placeholder="Street address"><input disabled placeholder="City"><input disabled placeholder="ZIP / Postal code"><input disabled placeholder="Country"></div>' + help;
			}
			if ( f.type === 'range' ) {
				return lbl + '<div class="vmail-range-prev"><input type="range" disabled><span class="vmail-range-val">' + escapeHtml( f['default'] || f.min || '0' ) + '</span></div>' + help;
			}
			return lbl + '<input type="' + escapeHtml( f.type ) + '" disabled placeholder="' + escapeHtml( f.placeholder || '' ) + '" value="' + escapeHtml( f['default'] || '' ) + '">' + help;
		}
		function renderCanvas() {
			canvas.innerHTML = '';
			canvas.style.setProperty( '--vf-accent', form.accent || '#2ab7f1' );
			if ( ! form.fields.length ) {
				canvas.innerHTML = '<div class="vmail-empty"><strong>Empty form</strong><span>Click a field on the left, or drag one in, to get started.</span></div>';
				return;
			}
			form.fields.forEach( function ( f, i ) {
				var card = document.createElement( 'div' );
				card.className = 'vmail-fcard' + ( i === selected ? ' is-selected' : '' ) + ( f.width === 'half' ? ' is-half' : ( f.width === 'third' ? ' is-third' : '' ) );
				card.setAttribute( 'draggable', 'true' );
				var ico = function ( p ) { return '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">' + p + '</svg>'; };
				card.innerHTML =
					'<div class="vmail-fcard-body">' + fieldPreview( f ) + '</div>' +
					'<div class="vmail-fcard-toolbar">' +
						'<button type="button" class="vmail-ft" data-act="up" title="Move up">' + ico('<path d="M12 19V5M5 12l7-7 7 7"/>') + '</button>' +
						'<button type="button" class="vmail-ft" data-act="down" title="Move down">' + ico('<path d="M12 5v14M19 12l-7 7-7-7"/>') + '</button>' +
						'<button type="button" class="vmail-ft" data-act="edit" title="Edit">' + ico('<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>') + '</button>' +
						'<button type="button" class="vmail-ft" data-act="copy" title="Copy">' + ico('<rect x="9" y="9" width="11" height="11" rx="2.5"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/>') + '</button>' +
						'<button type="button" class="vmail-ft" data-act="paste" title="Paste after">' + ico('<path d="M9 4h6a1 1 0 0 1 1 1v1H8V5a1 1 0 0 1 1-1z"/><rect x="5" y="6" width="14" height="15" rx="2.5"/>') + '</button>' +
						'<button type="button" class="vmail-ft" data-act="dup" title="Duplicate">' + ico('<rect x="8" y="8" width="12" height="12" rx="2.5"/><path d="M16 8V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h3"/>') + '</button>' +
						'<button type="button" class="vmail-ft vmail-ft-del" data-act="del" title="Delete">' + ico('<path d="M4 7h16M10 11v6M14 11v6M5 7l1 13a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2l1-13M9 7V4h6v3"/>') + '</button>' +
					'</div>';
				card.addEventListener( 'click', function ( e ) {
					if ( e.target.closest( '.vmail-ft' ) ) { return; }
					selected = i; openInspector(); renderCanvas(); renderInspector();
				} );
				card.querySelectorAll( '.vmail-ft' ).forEach( function ( tb ) {
					tb.addEventListener( 'click', function ( e ) {
						e.stopPropagation();
						var act = tb.getAttribute( 'data-act' );
						if ( act === 'del' ) {
							form.fields.splice( i, 1 );
							if ( selected >= form.fields.length ) { selected = form.fields.length - 1; }
							if ( ! form.fields.length ) { selected = -1; if ( inspZone ) { inspZone.classList.add( 'is-collapsed' ); } }
						} else if ( act === 'copy' ) {
							clipboard = JSON.parse( JSON.stringify( f ) );
							toast( 'Field copied.' );
							return;
						} else if ( act === 'paste' ) {
							if ( ! clipboard ) { toast( 'Nothing to paste — copy a field first.', 'error' ); return; }
							var pasted = JSON.parse( JSON.stringify( clipboard ) );
							form.fields.splice( i + 1, 0, pasted ); reKey(); selected = i + 1;
						} else if ( act === 'dup' ) {
							var copy = JSON.parse( JSON.stringify( f ) ); form.fields.splice( i + 1, 0, copy ); reKey(); selected = i + 1;
						} else if ( act === 'edit' ) {
							selected = i; openInspector();
						} else if ( act === 'up' ) {
							if ( i <= 0 ) { return; }
							var fu = form.fields.splice( i, 1 )[ 0 ]; form.fields.splice( i - 1, 0, fu ); selected = i - 1;
						} else if ( act === 'down' ) {
							if ( i >= form.fields.length - 1 ) { return; }
							var fd = form.fields.splice( i, 1 )[ 0 ]; form.fields.splice( i + 1, 0, fd ); selected = i + 1;
						}
						renderCanvas(); renderInspector();
					} );
				} );
				card.addEventListener( 'dragstart', function ( e ) { dragFrom = i; palDrag = null; card.classList.add( 'is-drag' ); try { e.dataTransfer.effectAllowed = 'move'; } catch ( err ) {} } );
				card.addEventListener( 'dragend', function () { card.classList.remove( 'is-drag' ); clearDropMarks(); } );
				canvas.appendChild( card );
			} );
			// Submit button as a real, selectable element (only shows when fields exist).
			var sub = document.createElement( 'div' );
			sub.className = 'vmail-sb-submit' + ( selected === 'submit' ? ' is-selected' : '' );
			sub.innerHTML = '<button type="button" class="vmail-sb-submit-btn">' + escapeHtml( form.submit_label || 'Submit' ) + '</button>' +
				'<span class="vmail-sb-submit-tag">Submit button · click to edit</span>';
			sub.addEventListener( 'click', function () { selected = 'submit'; openInspector(); renderCanvas(); renderInspector(); } );
			canvas.appendChild( sub );
		}

		/* ---------- inspector ---------- */
		function inspText( label, k, val, hint ) {
			return '<div class="velox-field"><span class="velox-field-label">' + label + '</span>' +
				'<input type="text" class="velox-input" data-k="' + k + '" value="' + escapeHtml( val || '' ) + '">' +
				( hint ? '<span class="velox-hint">' + hint + '</span>' : '' ) + '</div>';
		}
		function inspArea( label, k, val ) {
			return '<div class="velox-field"><span class="velox-field-label">' + label + '</span>' +
				'<textarea class="velox-textarea" rows="4" data-k="' + k + '">' + escapeHtml( val || '' ) + '</textarea></div>';
		}
		function widthSelect( f ) {
			return '<div class="velox-field"><span class="velox-field-label">Width</span><select class="velox-select" data-k="width">' +
				'<option value="full"' + ( f.width === 'full' ? ' selected' : '' ) + '>Full width</option>' +
				'<option value="half"' + ( f.width === 'half' ? ' selected' : '' ) + '>Half (1/2)</option>' +
				'<option value="third"' + ( f.width === 'third' ? ' selected' : '' ) + '>Third (1/3)</option>' +
				'</select></div>';
		}
		/* ---- validation + conditional logic UI ---- */
		function validationRows( f, t ) {
			var rows = '';
			var isNum = ( t === 'number' || t === 'date' );
			var isLen = ( t === 'text' || t === 'tel' || t === 'url' );
			if ( ! isNum && ! isLen ) { return ''; }
			var minMaxLabel = isNum ? ( t === 'date' ? 'Earliest / latest date' : 'Min / max value' ) : 'Min / max length';
			rows += '<div class="vmail-insp-sub">Validation</div>';
			rows += '<div class="velox-field"><span class="velox-field-label">' + minMaxLabel + '</span>' +
				'<div class="vmail-minmax">' +
					'<input type="' + ( t === 'date' ? 'date' : 'number' ) + '" class="velox-input" data-k="min" value="' + escapeHtml( f.min || '' ) + '" placeholder="min">' +
					'<input type="' + ( t === 'date' ? 'date' : 'number' ) + '" class="velox-input" data-k="max" value="' + escapeHtml( f.max || '' ) + '" placeholder="max">' +
				'</div></div>';
			if ( isLen ) {
				rows += '<div class="velox-field"><span class="velox-field-label">Pattern (regex)</span>' +
					'<input type="text" class="velox-input velox-mono" data-k="pattern" value="' + escapeHtml( f.pattern || '' ) + '" placeholder="e.g. [0-9]{5}">' +
					'<span class="velox-hint">Whole value must match. Leave blank for none.</span></div>';
				rows += inspText( 'Pattern error message', 'pattern_msg', f.pattern_msg, 'Shown when the pattern doesn\'t match.' );
			}
			return rows;
		}

		function condFieldOptions( exceptKey, sel ) {
			return form.fields.filter( function ( x ) {
				return x.key !== exceptKey && [ 'html', 'captcha', 'step', 'calc' ].indexOf( x.type ) === -1;
			} ).map( function ( x ) {
				return '<option value="' + escapeHtml( x.key ) + '"' + ( x.key === sel ? ' selected' : '' ) + '>' + escapeHtml( x.label || x.key ) + '</option>';
			} ).join( '' );
		}
		var COND_OPS = [ [ 'is', 'is' ], [ 'is_not', 'is not' ], [ 'contains', 'contains' ], [ 'gt', 'greater than' ], [ 'lt', 'less than' ], [ 'empty', 'is empty' ], [ 'not_empty', 'is not empty' ] ];
		function conditionalRows( f ) {
			var others = form.fields.filter( function ( x ) { return x.key !== f.key && [ 'html', 'captcha', 'step', 'calc' ].indexOf( x.type ) === -1; } );
			var rows = '<div class="vmail-insp-sub">Conditional logic</div>';
			if ( ! others.length ) {
				return rows + '<p class="velox-hint" style="margin:0;">Add another field first to show or hide this one based on its answer.</p>';
			}
			var on = !! ( f.cond && f.cond.rules && f.cond.rules.length );
			rows += '<label class="vmail-insp-check"><input type="checkbox" data-cond="enable"' + ( on ? ' checked' : '' ) + '> Show/hide this field based on other answers</label>';
			if ( ! on ) { return rows; }
			var c = f.cond;
			rows += '<div class="vmail-cond">';
			rows += '<div class="vmail-cond-head">' +
				'<select class="velox-select velox-select--sm" data-cond="action">' +
					'<option value="show"' + ( c.action !== 'hide' ? ' selected' : '' ) + '>Show this field</option>' +
					'<option value="hide"' + ( c.action === 'hide' ? ' selected' : '' ) + '>Hide this field</option>' +
				'</select>' +
				'<span class="vmail-cond-when">when</span>' +
				'<select class="velox-select velox-select--sm" data-cond="logic">' +
					'<option value="all"' + ( c.logic !== 'any' ? ' selected' : '' ) + '>all rules match</option>' +
					'<option value="any"' + ( c.logic === 'any' ? ' selected' : '' ) + '>any rule matches</option>' +
				'</select>' +
			'</div>';
			rows += '<div class="vmail-cond-rules">';
			c.rules.forEach( function ( r, i ) {
				var needsVal = [ 'empty', 'not_empty' ].indexOf( r.op ) === -1;
				rows += '<div class="vmail-cond-rule" data-i="' + i + '">' +
					'<select class="velox-select velox-select--sm" data-cr="field">' + condFieldOptions( f.key, r.field ) + '</select>' +
					'<select class="velox-select velox-select--sm" data-cr="op">' + COND_OPS.map( function ( o ) { return '<option value="' + o[0] + '"' + ( r.op === o[0] ? ' selected' : '' ) + '>' + o[1] + '</option>'; } ).join( '' ) + '</select>' +
					'<input type="text" class="velox-input vmail-cond-val" data-cr="value" value="' + escapeHtml( r.value || '' ) + '" placeholder="value"' + ( needsVal ? '' : ' style="visibility:hidden"' ) + '>' +
					'<button type="button" class="vmail-cond-del" data-i="' + i + '" title="Remove rule" aria-label="Remove rule">&times;</button>' +
				'</div>';
			} );
			rows += '</div>';
			rows += '<button type="button" class="velox-btn velox-btn--ghost velox-btn--sm" data-cond="add">+ Add rule</button>';
			rows += '</div>';
			return rows;
		}

		function bindConditional( f ) {
			var enable = $( '[data-cond="enable"]', inspector );
			if ( enable ) {
				enable.addEventListener( 'change', function () {
					if ( enable.checked ) {
						var first = form.fields.filter( function ( x ) { return x.key !== f.key && [ 'html', 'captcha', 'step', 'calc' ].indexOf( x.type ) === -1; } )[0];
						f.cond = { action: 'show', logic: 'all', rules: [ { field: first ? first.key : '', op: 'is', value: '' } ] };
					} else {
						f.cond = null;
					}
					renderInspector();
				} );
			}
			[ 'action', 'logic' ].forEach( function ( k ) {
				var el = $( '[data-cond="' + k + '"]', inspector );
				if ( el ) { el.addEventListener( 'change', function () { if ( f.cond ) { f.cond[ k ] = el.value; } } ); }
			} );
			var add = $( '[data-cond="add"]', inspector );
			if ( add ) {
				add.addEventListener( 'click', function () {
					if ( ! f.cond ) { return; }
					var first = form.fields.filter( function ( x ) { return x.key !== f.key && [ 'html', 'captcha', 'step', 'calc' ].indexOf( x.type ) === -1; } )[0];
					f.cond.rules.push( { field: first ? first.key : '', op: 'is', value: '' } );
					renderInspector();
				} );
			}
			$$( '.vmail-cond-rule', inspector ).forEach( function ( row ) {
				var i = parseInt( row.getAttribute( 'data-i' ), 10 );
				$$( '[data-cr]', row ).forEach( function ( el ) {
					var ev = el.tagName === 'SELECT' ? 'change' : 'input';
					el.addEventListener( ev, function () {
						if ( ! f.cond || ! f.cond.rules[ i ] ) { return; }
						f.cond.rules[ i ][ el.getAttribute( 'data-cr' ) ] = el.value;
						if ( el.getAttribute( 'data-cr' ) === 'op' ) { renderInspector(); }
					} );
				} );
				var del = row.querySelector( '.vmail-cond-del' );
				if ( del ) {
					del.addEventListener( 'click', function () {
						if ( ! f.cond ) { return; }
						f.cond.rules.splice( i, 1 );
						if ( ! f.cond.rules.length ) { f.cond = null; }
						renderInspector();
					} );
				}
			} );
		}

		function renderInspector() {
			if ( selected === 'submit' ) {
				var srows = inspText( 'Button text', 'submit_label', form.submit_label || 'Submit' );
				srows += '<div class="vmail-insp-note">Want to fully style this button — colours, padding, alignment, shadow? Open the <strong>Style editor</strong> from the top bar.</div>';
				inspector.innerHTML = '<div class="vmail-insp-head"><span>Submit button</span><button type="button" class="vmail-sb-insp-x" id="vmail-insp-close" title="Close"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M18 6 6 18M6 6l12 12"/></svg></button></div><div class="vmail-insp-body">' + srows + '</div>';
				var sx = $( '#vmail-insp-close', inspector ); if ( sx ) { sx.addEventListener( 'click', closeInspector ); }
				var sl = $( '[data-k="submit_label"]', inspector );
				if ( sl ) { sl.addEventListener( 'input', function () { form.submit_label = sl.value; renderCanvas(); } ); }
				return;
			}
			if ( selected < 0 || ! form.fields[ selected ] ) {
				inspector.innerHTML = '<div class="vmail-insp-empty">Select a field on the canvas to edit its settings.</div>';
				return;
			}
			var f = form.fields[ selected ];
			var t = f.type;
			var hasOpts = TYPES[ t ] && TYPES[ t ].opts;
			var noPlaceholder = [ 'consent', 'checkbox', 'radio', 'multiselect', 'name', 'country', 'select', 'date', 'captcha', 'html' ];
			var noDefault     = [ 'consent', 'checkbox', 'name', 'captcha', 'html', 'multiselect' ];
			var rows = '';

			if ( t === 'step' ) {
				rows += inspText( 'Step title', 'label', f.label, 'Shown in the progress bar. The first page break titles step 1.' );
				rows += '<div class="vmail-insp-note">A page break splits the form into steps. Visitors get Next / Back buttons and a progress bar. Add more page breaks for more steps.</div>';
			} else if ( t === 'calc' ) {
				rows += inspText( 'Label', 'label', f.label );
				rows += inspText( 'Field key', 'key', f.key, 'Merge tag: {inputs.' + escapeHtml( f.key ) + '}' );
				rows += '<div class="velox-field"><span class="velox-field-label">Formula</span>' +
					'<textarea class="velox-textarea velox-mono" rows="2" data-k="calc" placeholder="{quantity} * {price}">' + escapeHtml( f.calc || '' ) + '</textarea>' +
					'<span class="velox-hint">Reference fields with <code>{field_key}</code>. Use + - * / and ( ). Example: <code>{quantity} * {price}</code></span></div>';
				rows += '<div class="velox-field"><span class="velox-field-label">Prefix / suffix</span><div class="vmail-minmax">' +
					'<input type="text" class="velox-input" data-k="calc_prefix" value="' + escapeHtml( f.calc_prefix || '' ) + '" placeholder="e.g. €">' +
					'<input type="text" class="velox-input" data-k="calc_suffix" value="' + escapeHtml( f.calc_suffix || '' ) + '" placeholder="e.g. /mo">' +
				'</div></div>';
				rows += widthSelect( f ) + inspText( 'CSS class', 'css', f.css );
				rows += conditionalRows( f );
			} else if ( t === 'file' ) {
				rows += '<div class="vmail-insp-sub">Basics</div>';
				rows += inspText( 'Label', 'label', f.label );
				rows += inspText( 'Field key', 'key', f.key, 'Merge tag: {inputs.' + escapeHtml( f.key ) + '}' );
				rows += '<label class="vmail-insp-check"><input type="checkbox" data-k="required"' + ( f.required ? ' checked' : '' ) + '> Required field</label>';
				rows += '<div class="velox-field"><span class="velox-field-label">Allowed file types</span><select class="velox-select" data-k="accept">' +
					'<option value="images,pdf,docs"' + ( f.accept === 'images,pdf,docs' ? ' selected' : '' ) + '>Images, PDF &amp; documents</option>' +
					'<option value="images"' + ( f.accept === 'images' ? ' selected' : '' ) + '>Images only (JPG, PNG, GIF, WebP)</option>' +
					'<option value="pdf"' + ( f.accept === 'pdf' ? ' selected' : '' ) + '>PDF only</option>' +
					'<option value="docs"' + ( f.accept === 'docs' ? ' selected' : '' ) + '>Documents (PDF, Word, text)</option>' +
					'</select></div>';
				rows += '<div class="velox-field"><span class="velox-field-label">Max size (MB)</span><input type="number" class="velox-input" data-k="maxsize" min="1" max="64" value="' + escapeHtml( f.maxsize || '5' ) + '"></div>';
				rows += inspText( 'Help text', 'help', f.help );
				rows += '<div class="vmail-insp-sub">Advanced</div>';
				rows += widthSelect( f ) + inspText( 'CSS class', 'css', f.css );
				rows += conditionalRows( f );
			} else if ( t === 'html' ) {
				rows += inspText( 'Field key', 'key', f.key, 'Merge tag: {inputs.' + escapeHtml( f.key ) + '}' );
				rows += '<div class="velox-field"><span class="velox-field-label">HTML content</span><textarea class="velox-textarea velox-mono" rows="6" data-k="content">' + escapeHtml( f.content || '' ) + '</textarea><span class="velox-hint">Rendered as-is in the form. Basic HTML is allowed.</span></div>';
				rows += widthSelect( f ) + inspText( 'CSS class', 'css', f.css );
				rows += conditionalRows( f );
			} else if ( t === 'captcha' ) {
				rows += '<div class="vmail-insp-note">This drops your CAPTCHA widget into the form. Configure the provider and keys under <strong>Mail &amp; forms \u2192 CAPTCHA</strong>. A form uses either a consent box or CAPTCHA \u2014 not both.</div>';
				rows += widthSelect( f ) + inspText( 'CSS class', 'css', f.css );
				rows += conditionalRows( f );
			} else {
				rows += '<div class="vmail-insp-sub">Basics</div>';
				rows += inspText( 'Label', 'label', f.label );
				rows += inspText( 'Field key', 'key', f.key, 'Merge tag: {inputs.' + escapeHtml( f.key ) + '}' );
				rows += '<label class="vmail-insp-check"><input type="checkbox" data-k="required"' + ( f.required ? ' checked' : '' ) + '> Required field</label>';
				if ( noPlaceholder.indexOf( t ) === -1 ) { rows += inspText( 'Placeholder', 'placeholder', f.placeholder ); }
				if ( t === 'name' ) { rows += inspArea( 'Sub-labels (first line = first name, second = last name)', 'options', f.options ); }
				else if ( hasOpts ) { rows += inspArea( 'Options (one per line)', 'options', f.options ); }
				if ( noDefault.indexOf( t ) === -1 ) { rows += inspText( 'Default value', 'default', f['default'] ); }
				rows += inspText( 'Help text', 'help', f.help );
				rows += validationRows( f, t );
				rows += '<div class="vmail-insp-sub">Advanced</div>';
				rows += widthSelect( f );
				rows += inspText( 'CSS class', 'css', f.css );
			}
			rows += conditionalRows( f );
			inspector.innerHTML = '<div class="vmail-insp-head"><span>' + ( TYPES[ t ] ? TYPES[ t ].label : t ) + ' field</span><button type="button" class="vmail-sb-insp-x" id="vmail-insp-close" title="Close"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M18 6 6 18M6 6l12 12"/></svg></button></div><div class="vmail-insp-body">' + rows + '</div>';
			var ix = $( '#vmail-insp-close', inspector ); if ( ix ) { ix.addEventListener( 'click', closeInspector ); }

			$$( '[data-k]', inspector ).forEach( function ( el ) {
				var ev = ( el.type === 'checkbox' || el.tagName === 'SELECT' ) ? 'change' : 'input';
				el.addEventListener( ev, function () {
					var k = el.getAttribute( 'data-k' );
					if ( k === 'key' ) {
						f._lockKey = true; f.key = slugify( el.value ); renderCanvas(); autosave(); return;
					}
					f[ k ] = ( el.type === 'checkbox' ) ? el.checked : el.value;
					if ( k === 'label' && ! f._lockKey ) {
						f.key = ''; reKey();
						var ke = $( '[data-k="key"]', inspector ); if ( ke ) { ke.value = f.key; }
					}
					renderCanvas();
					autosave();
				} );
			} );

			bindConditional( f );
		}

		/* ---------- notifications ---------- */
		function fieldTags() {
			var skip = { consent: 1, html: 1, captcha: 1, step: 1 };
			var tags = form.fields.filter( function ( f ) { return ! skip[ f.type ]; } ).map( function ( f ) {
				return { tag: '{inputs.' + f.key + '}', label: f.label || f.key };
			} );
			tags.push( { tag: '{all_fields}', label: 'All fields' } );
			tags.push( { tag: '{site_name}', label: 'Site name' } );
			tags.push( { tag: '{date}', label: 'Date' } );
			return tags;
		}
		function emailFieldOptions( sel ) {
			return form.fields.filter( function ( f ) { return f.type === 'email' || f.type === 'text'; } ).map( function ( f ) {
				return '<option value="' + escapeHtml( f.key ) + '"' + ( f.key === sel ? ' selected' : '' ) + '>' + escapeHtml( f.label || f.key ) + '</option>';
			} ).join( '' );
		}
		function getEmail( kind ) {
			var e = form.emails.filter( function ( x ) { return x.type === kind; } )[ 0 ];
			if ( ! e ) { e = { type: kind, enabled: kind === 'admin', to: ( meta.admin_email || '' ), to_field: 'email' }; form.emails.push( e ); }
			[ 'to', 'to_field', 'cc', 'bcc', 'from_name', 'from_email', 'reply_to', 'subject', 'body' ].forEach( function ( k ) { if ( e[ k ] == null ) { e[ k ] = ''; } } );
			return e;
		}
		function field2( label, inner ) { return '<div class="velox-field"><span class="velox-field-label">' + label + '</span>' + inner + '</div>'; }
		function mergeBtn() { return '<div class="vmail-merge"><button type="button" class="velox-btn velox-btn--ghost vmail-merge-btn">Insert field \u25BE</button><div class="vmail-merge-menu" hidden></div></div>'; }
		function insertAtCursor( el, text ) {
			if ( el.selectionStart != null ) {
				var s = el.selectionStart, en = el.selectionEnd;
				el.value = el.value.slice( 0, s ) + text + el.value.slice( en );
				el.selectionStart = el.selectionEnd = s + text.length; el.focus();
			} else { el.value += text; }
		}
		function bindMerge( block ) {
			$$( '.vmail-merge', block ).forEach( function ( m ) {
				var btn = m.querySelector( '.vmail-merge-btn' );
				var menu = m.querySelector( '.vmail-merge-menu' );
				var target = m.parentNode.querySelector( '[data-e]' );
				menu.innerHTML = fieldTags().map( function ( t ) { return '<button type="button" data-tag="' + escapeHtml( t.tag ) + '"><span>' + escapeHtml( t.label ) + '</span><code>' + escapeHtml( t.tag ) + '</code></button>'; } ).join( '' );
				btn.addEventListener( 'click', function ( e ) { e.preventDefault(); menu.hidden = ! menu.hidden; } );
				menu.addEventListener( 'click', function ( e ) {
					var b = e.target.closest( 'button[data-tag]' ); if ( ! b ) { return; }
					insertAtCursor( target, b.getAttribute( 'data-tag' ) );
					target.dispatchEvent( new Event( 'input', { bubbles: true } ) );
					menu.hidden = true;
				} );
			} );
		}
		function renderEmails() {
			emailsWrap.innerHTML = '';
			[ { kind: 'admin', title: 'Admin notification', desc: 'Sent to your team when someone submits.' },
			  { kind: 'customer', title: 'Auto-reply to customer', desc: 'A confirmation sent back to the person who submitted.' } ].forEach( function ( cfg ) {
				var e = getEmail( cfg.kind );
				var block = document.createElement( 'div' );
				block.className = 'vmail-email'; block.setAttribute( 'data-type', cfg.kind );
				var toRow = cfg.kind === 'admin'
					? field2( 'Send to', '<input type="text" class="velox-input" data-e="to" value="' + escapeHtml( e.to ) + '" placeholder="you@agency.com, team@agency.com">' )
					: field2( 'Send to the value of', '<select class="velox-select" data-e="to_field">' + emailFieldOptions( e.to_field ) + '</select>' );
				block.innerHTML =
					'<div class="vmail-email-bar">' +
						'<div class="vmail-email-titlewrap"><span class="vmail-email-title">' + cfg.title + '</span><span class="vmail-email-sub">' + cfg.desc + '</span></div>' +
						'<label class="vmail-email-switch"><span class="vmail-email-state">' + ( e.enabled ? 'Enabled' : 'Off' ) + '</span>' +
						'<span class="velox-switch"><input type="checkbox" data-e="enabled"' + ( e.enabled ? ' checked' : '' ) + '><span class="velox-switch-track"></span></span></label>' +
					'</div>' +
					'<div class="vmail-email-body">' +
						toRow +
						field2( 'Subject', '<div class="vmail-mergewrap"><input type="text" class="velox-input" data-e="subject" value="' + escapeHtml( e.subject ) + '">' + mergeBtn() + '</div>' ) +
						field2( 'Email body', '<div class="vmail-mergewrap"><textarea class="velox-textarea" rows="6" data-e="body">' + escapeHtml( e.body ) + '</textarea>' + mergeBtn() + '</div>' ) +
						'<div class="vmail-email-adv">' +
							'<button type="button" class="vmail-email-adv-toggle" aria-expanded="false">' +
								'<svg class="vmail-email-adv-chev" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>' +
								'<span>Advanced — sender, reply-to, CC &amp; BCC</span>' +
							'</button>' +
							'<div class="vmail-email-adv-body" hidden>' +
								'<div class="vmail-email-grid">' +
									field2( 'From name', '<input type="text" class="velox-input" data-e="from_name" value="' + escapeHtml( e.from_name ) + '" placeholder="' + escapeHtml( meta.site_name || '' ) + '">' ) +
									field2( 'From email', '<input type="text" class="velox-input" data-e="from_email" value="' + escapeHtml( e.from_email ) + '" placeholder="blank = site default">' ) +
									field2( 'Reply-To', '<input type="text" class="velox-input" data-e="reply_to" value="' + escapeHtml( e.reply_to ) + '" placeholder="e.g. {inputs.email}">' ) +
									field2( 'CC', '<input type="text" class="velox-input" data-e="cc" value="' + escapeHtml( e.cc ) + '" placeholder="comma,separated">' ) +
									field2( 'BCC', '<input type="text" class="velox-input" data-e="bcc" value="' + escapeHtml( e.bcc ) + '" placeholder="comma,separated">' ) +
								'</div>' +
							'</div>' +
						'</div>' +
					'</div>';
				emailsWrap.appendChild( block );
				// Advanced collapsible
				var advT = block.querySelector( '.vmail-email-adv-toggle' );
				var advB = block.querySelector( '.vmail-email-adv-body' );
				if ( advT && advB ) {
					advT.addEventListener( 'click', function () {
						var open = advB.hidden;
						advB.hidden = ! open;
						advT.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
						advT.classList.toggle( 'is-open', open );
					} );
				}
				// Live "Enabled/Off" label + dim when off
				var sw = block.querySelector( '[data-e="enabled"]' );
				var stateEl = block.querySelector( '.vmail-email-state' );
				function syncState() {
					if ( stateEl ) { stateEl.textContent = sw.checked ? 'Enabled' : 'Off'; }
					block.classList.toggle( 'is-off', ! sw.checked );
				}
				if ( sw ) { sw.addEventListener( 'change', syncState ); syncState(); }
				$$( '[data-e]', block ).forEach( function ( el ) {
					var ev = el.type === 'checkbox' ? 'change' : 'input';
					el.addEventListener( ev, function () { var k = el.getAttribute( 'data-e' ); e[ k ] = el.type === 'checkbox' ? el.checked : el.value; autosave(); } );
				} );
				bindMerge( block );
			} );
		}
		document.addEventListener( 'click', function ( e ) {
			if ( ! e.target.closest( '.vmail-merge' ) ) { $$( '.vmail-merge-menu' ).forEach( function ( mn ) { mn.hidden = true; } ); }
		} );

		/* ---------- tabs ---------- */
		$$( '.vmail-tab' ).forEach( function ( tab ) {
			tab.addEventListener( 'click', function () {
				$$( '.vmail-tab' ).forEach( function ( t ) { t.classList.toggle( 'is-active', t === tab ); } );
				var name = tab.getAttribute( 'data-tab' );
				$$( '.vmail-panel' ).forEach( function ( p ) { p.hidden = p.getAttribute( 'data-panel' ) !== name; } );
				if ( name === 'notify' ) { renderEmails(); }
			} );
		} );

		/* ---------- settings + save ---------- */
		function bindSettings() {
			var t = $( '#vmail-title' ); if ( t ) { t.addEventListener( 'input', function () { form.title = t.value; } ); }
			var sub = $( '#vmail-submit' ); if ( sub ) { sub.addEventListener( 'input', function () { form.submit_label = sub.value; } ); }
			var suc = $( '#vmail-success' ); if ( suc ) { suc.addEventListener( 'input', function () { form.success = suc.value; } ); }
			var ac = $( '#vmail-accent' ); if ( ac ) { ac.addEventListener( 'input', function () { form.accent = ac.value; renderCanvas(); } ); }
			var cap = $( '#vmail-captcha' ); if ( cap ) { cap.addEventListener( 'change', function () { form.captcha = cap.checked; } ); }
			var sht = $( '#vmail-show-title' );
			if ( sht ) {
				sht.addEventListener( 'change', function () {
					form.show_title = sht.checked;
					if ( typeof renderCanvas === 'function' ) { renderCanvas(); }
					autosave();
				} );
			}
			// Per-form on/off toggle (7a): reflects in the label + persists immediately.
			var en = $( '#vmail-enabled' ), enLbl = $( '#vmail-onoff-label' );
			if ( en ) {
				var syncEnabled = function () { form.enabled = en.checked; if ( enLbl ) { enLbl.textContent = en.checked ? 'On' : 'Off'; enLbl.classList.toggle( 'is-on', en.checked ); } };
				en.addEventListener( 'change', function () { syncEnabled(); autosave(); toast( en.checked ? 'Form is on.' : 'Form is off — hidden from visitors.' ); } );
				syncEnabled();
			}
			// Mode switcher (Build / Style / Preview) active-state highlight — scoped to
			// the builder's own navbar so it never touches the style-editor's copy.
			var navRoot  = $( '#vmail-builder' ) || document;
			var modebtns = $$( '.vmail-modebtn', navRoot );
			var ghosts   = $$( '.vmail-nav-ghost', navRoot );
			modebtns.forEach( function ( b ) {
				b.addEventListener( 'click', function () {
					modebtns.forEach( function ( x ) { x.classList.remove( 'is-active' ); } );
					b.classList.add( 'is-active' );
					ghosts.forEach( function ( x ) { x.classList.remove( 'is-active' ); } );
				} );
			} );
			// Notifications / Settings ghost buttons: clear the mode highlight while active.
			ghosts.forEach( function ( b ) {
				b.addEventListener( 'click', function () {
					modebtns.forEach( function ( x ) { x.classList.remove( 'is-active' ); } );
					ghosts.forEach( function ( x ) { x.classList.remove( 'is-active' ); } );
					b.classList.add( 'is-active' );
				} );
			} );
			// Return to the Build highlight when an overlay (style/preview) closes.
			window.veloxMailHighlightBuild = function () {
				modebtns.forEach( function ( x ) { x.classList.remove( 'is-active' ); } );
				ghosts.forEach( function ( x ) { x.classList.remove( 'is-active' ); } );
				var bb = navRoot.querySelector ? navRoot.querySelector( '.vmail-modebtn[data-tab="build"]' ) : null;
				if ( bb ) { bb.classList.add( 'is-active' ); }
			};
		}
		function save() {
			reKey();
			var btn = $( '#vmail-save' ); btn.disabled = true;
			api( 'form_save', { form: JSON.stringify( form ) } )
				.then( function () { toast( 'Form saved.' ); btn.disabled = false; } )
				.catch( function ( err ) { toast( err.message, 'error' ); btn.disabled = false; } );
		}
		// Persist silently in place — used by the Notifications toggles so a change
		// sticks immediately without the full Save (which navigates away). Debounced
		// so rapid toggles collapse into one request.
		var autosaveTimer = null;
		function autosave() {
			if ( autosaveTimer ) { clearTimeout( autosaveTimer ); }
			autosaveTimer = setTimeout( function () {
				api( 'form_save', { form: JSON.stringify( form ) } )
					.then( function () { toast( 'Saved' ); } )
					.catch( function ( err ) { toast( err.message, 'error' ); } );
			}, 600 );
		}
		$( '#vmail-save' ).addEventListener( 'click', save );

		// Any element carrying data-code copies it (forms table shortcode cell).
		document.addEventListener( 'click', function ( e ) {
			var c = e.target.closest ? e.target.closest( '.velox-copy' ) : null;
			if ( ! c ) { return; }
			e.preventDefault();
			var code = c.getAttribute( 'data-code' ) || '';
			var done = function () {
				toast( 'Shortcode copied.' );
				c.classList.add( 'is-copied' );
				setTimeout( function () { c.classList.remove( 'is-copied' ); }, 1200 );
			};
			if ( navigator.clipboard && navigator.clipboard.writeText ) {
				navigator.clipboard.writeText( code ).then( done ).catch( done );
			} else {
				var t = document.createElement( 'textarea' ); t.value = code; document.body.appendChild( t ); t.select();
				try { document.execCommand( 'copy' ); } catch ( er ) {}
				document.body.removeChild( t ); done();
			}
		} );

		// Shortcode displays (build + style + preview toolbars): click to copy.
		// Delegated so it also catches the preview overlay's chip, which is built lazily.
		document.addEventListener( 'click', function ( e ) {
			var chip = e.target.closest ? e.target.closest( '.vmail-nav-sc' ) : null;
			if ( ! chip ) { return; }
			var code = chip.getAttribute( 'data-code' ) || '';
			var done = function () { toast( 'Shortcode copied.' ); chip.classList.add( 'is-copied' ); setTimeout( function () { chip.classList.remove( 'is-copied' ); }, 1200 ); };
			if ( navigator.clipboard && navigator.clipboard.writeText ) {
				navigator.clipboard.writeText( code ).then( done ).catch( function () { done(); } );
			} else {
				var t = document.createElement( 'textarea' ); t.value = code; document.body.appendChild( t ); t.select();
				try { document.execCommand( 'copy' ); } catch ( er ) {}
				document.body.removeChild( t ); done();
			}
		} );

		var palSearch = $( '#vmail-palette-search' );
		if ( palSearch ) { palSearch.addEventListener( 'input', function () { renderPalette( palSearch.value ); } ); }

		renderPalette();
		renderCanvas();
		renderInspector();
		bindSettings();
		bindCanvasDnD();
		initPreview();

		/* =========================================================
		   Preview button — the real, interactive form in an overlay
		   ========================================================= */
		function initPreview() {
			var btn = $( '#vmail-preview-btn' );
			if ( ! btn ) { return; }
			var overlay = null;
			function ensure() {
				if ( overlay ) { return; }
				overlay = document.createElement( 'div' );
				overlay.className = 'vmp'; overlay.hidden = true;
				overlay.innerHTML =
					'<div class="vmail-nav vmail-nav--vmp">' +
						'<div class="vmail-nav-left">' +
							'<a class="vmail-nav-back" id="vmp-back" title="Back to Build" style="cursor:pointer"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg></a>' +
							'<div class="vmail-nav-crumb">Utilities <span>/</span> <b>Mail &amp; forms</b></div>' +
							'<div class="vmail-nav-vsep"></div>' +
							'<span class="vmail-nav-title vmail-nav-title--static">' + escapeHtml( form.title || 'Form' ) + '</span>' +
							'<label class="vmail-nav-switch" title="Turn this form on or off"><input type="checkbox" id="vmp-enabled"' + ( form.enabled !== false ? ' checked' : '' ) + '><span class="vmail-switch-track"></span></label>' +
							'<span class="vmail-nav-onoff' + ( form.enabled !== false ? ' is-on' : '' ) + '" id="vmp-onoff-label">' + ( form.enabled !== false ? 'On' : 'Off' ) + '</span>' +
							'<button type="button" class="vmail-nav-sc" data-code=\'[velox_form id="' + form.id + '"]\' title="Form shortcode — click to copy"><span class="vmail-nav-sc-tag">Shortcode</span><code>[velox_form id="' + form.id + '"]</code></button>' +
						'</div>' +
						'<div class="vmail-nav-mode">' +
							'<button type="button" class="vmail-modebtn" id="vmp-to-build"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 5h18M3 12h18M3 19h12"/></svg> Build</button>' +
							'<button type="button" class="vmail-modebtn" id="vmp-to-style"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg> Style</button>' +
							'<button type="button" class="vmail-modebtn is-active"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg> Preview</button>' +
						'</div>' +
						'<div class="vmail-nav-right">' +
							'<div class="vmail-nav-devs" id="vmp-dev"><button class="is-on" type="button" data-d="desktop"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="2" y="4" width="20" height="13" rx="2"/><path d="M8 21h8"/></svg> Desktop</button><button type="button" data-d="mobile"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="7" y="2" width="10" height="20" rx="2.5"/></svg> Mobile</button></div>' +
							'<button class="velox-btn velox-btn--primary" id="vmp-close" type="button">Close</button>' +
						'</div>' +
					'</div>' +
					'<div class="vmp-note-bar">Live preview — type in it, nothing is submitted.</div>' +
					'<div class="vmp-stage"><div class="vmp-frame" id="vmp-frame"><div class="vse-pf" id="vmp-form"></div></div></div>' +
					'<style id="vmp-css"></style>';
				document.body.appendChild( overlay );
				$( '#vmp-close', overlay ).addEventListener( 'click', close );
				var pBack = $( '#vmp-back', overlay ), pBuild = $( '#vmp-to-build', overlay );
				if ( pBack ) { pBack.addEventListener( 'click', close ); }
				if ( pBuild ) { pBuild.addEventListener( 'click', close ); }
				$( '#vmp-to-style', overlay ).addEventListener( 'click', function () { close(); if ( openStyleEditor ) { openStyleEditor(); } } );
				var pEn = $( '#vmp-enabled', overlay ), pEnLbl = $( '#vmp-onoff-label', overlay );
				if ( pEn ) {
					pEn.addEventListener( 'change', function () {
						form.enabled = pEn.checked;
						if ( pEnLbl ) { pEnLbl.textContent = pEn.checked ? 'On' : 'Off'; pEnLbl.classList.toggle( 'is-on', pEn.checked ); }
						var ben = $( '#vmail-enabled' ), benLbl = $( '#vmail-onoff-label' );
						if ( ben ) { ben.checked = pEn.checked; }
						if ( benLbl ) { benLbl.textContent = pEn.checked ? 'On' : 'Off'; benLbl.classList.toggle( 'is-on', pEn.checked ); }
						autosave(); toast( pEn.checked ? 'Form is on.' : 'Form is off — hidden from visitors.' );
					} );
				}
				$( '#vmp-form', overlay ).addEventListener( 'submit', function ( e ) { e.preventDefault(); } );
				$$( '#vmp-dev button', overlay ).forEach( function ( d ) {
					d.addEventListener( 'click', function () {
						$$( '#vmp-dev button', overlay ).forEach( function ( x ) { x.classList.toggle( 'is-on', x === d ); } );
						$( '#vmp-frame', overlay ).className = 'vmp-frame' + ( d.getAttribute( 'data-d' ) === 'mobile' ? ' is-mobile' : '' );
					} );
				} );
				document.addEventListener( 'keydown', function ( e ) { if ( overlay && ! overlay.hidden && e.key === 'Escape' ) { close(); } } );
			}
			function open() {
				ensure();
				$( '#vmp-form', overlay ).innerHTML = buildFormPreviewHtml();
				$( '#vmp-css', overlay ).textContent = formPreviewCss( '#vmp-form' );
				overlay.hidden = false; document.body.style.overflow = 'hidden';
			}
			function close() { if ( overlay ) { overlay.hidden = true; document.body.style.overflow = ''; } if ( window.veloxMailHighlightBuild ) { window.veloxMailHighlightBuild(); } }
			openPreviewOverlay = open;
			btn.addEventListener( 'click', open );
		}

		/* =========================================================
		   Full-screen style editor
		   ========================================================= */
		initStyleEditor();

		function initStyleEditor() {
			var root = $( '#vmail-style-editor' );
			if ( ! root ) { return; }
			form.style = form.style || {};
			var S = form.style;
			// targets: form, header, labels, inputs, submit, + per-field by key
			function defaults( target ) {
				return {}; // empty = inherit theme; only set keys override
			}
			function st( target ) { S[ target ] = S[ target ] || {}; return S[ target ]; }

			var ICONS = {
				form:   '<rect x="3" y="3" width="18" height="18" rx="3"/>',
				header: '<path d="M4 7h16M4 12h9M4 17h6"/>',
				labels: '<path d="M4 6h9M4 12h16M4 18h7"/>',
				inputs: '<rect x="3" y="8" width="18" height="8" rx="2.5"/>',
				submit: '<rect x="3" y="8.5" width="18" height="7" rx="3.5"/><path d="M12 19v2"/>'
			};
			var TARGETS = [
				{ k: 'form', n: 'Form' }, { k: 'header', n: 'Title' }, { k: 'labels', n: 'Labels' },
				{ k: 'inputs', n: 'Inputs' }, { k: 'submit', n: 'Button' }
			];
			// curTarget = which kind of thing is being styled; curScope = '' for all of
			// them, or a single field key. `current` is the resulting style bucket.
			var curTarget = 'form', curScope = '', current = 'form';
			function syncCurrent() { current = curScope ? 'field:' + curScope : curTarget; }
			function scopeable( t ) { return 'inputs' === t || 'labels' === t; }
			function styleableFields() {
				return form.fields.filter( function ( f ) {
					return [ 'step', 'html', 'captcha', 'heading' ].indexOf( f.type ) === -1;
				} );
			}
			var openGroups = { content: true, colour: true, hover: false, size: false, text: true, shape: true, spacing: false, shadow: false };

			// ---- live preview ----
			var pf = $( '#vse-form' );
			function buildPreview() {
				pf.innerHTML = buildFormPreviewHtml();
				markTarget();
			}
			function markTarget() {
				$$( '.vse-pf-target', pf ).forEach( function ( e ) { e.classList.remove( 'vse-pf-target' ); } );
				$$( '.is-target', pf ).forEach( function ( e ) { e.classList.remove( 'is-target' ); } );
				var el = null;
				if ( current === 'submit' ) { el = $( '.vse-pf-submit-wrap', pf ); if ( el ) { el.classList.add( 'is-target' ); } return; }
				if ( current === 'header' ) { el = $( '.vse-pf-header', pf ); }
				else if ( current === 'form' ) { el = pf; }
				else if ( current.indexOf( 'field:' ) === 0 ) { el = $( '.vse-pf-field[data-fkey="' + current.slice( 6 ) + '"]', pf ); }
				if ( el ) { el.classList.add( 'vse-pf-target' ); }
			}

			// ---- CSS generation ----
			function px( v ) { return ( v === '' || v == null ) ? '' : ( /[a-z%]/i.test( String( v ) ) ? v : v + 'px' ); }
			function shadowVal( k ) {
				return { none: 'none', soft: '0 1px 3px rgba(16,24,40,.10)', medium: '0 8px 20px -6px rgba(16,24,40,.22)', strong: '0 16px 40px -8px rgba(16,24,40,.32)' }[ k ] || '';
			}
			var SCOPE = '#vse-form';
			function liveCss() { return formPreviewCss( SCOPE ); }
			function gradCss( o ) {
				var from = o.gradFrom || '#2ab7f1', to = o.gradTo || '#7b5cff';
				var ang = parseFloat( o.gradAngle );
				if ( isNaN( ang ) ) { ang = 90; }
				return 'radial' === ( o.gradType || 'linear' )
					? 'radial-gradient(circle,' + from + ',' + to + ')'
					: 'linear-gradient(' + ang + 'deg,' + from + ',' + to + ')';
			}
			function refreshGradBar() {
				var bar = $( '#vse-controls .vse-gradbar' );
				if ( bar ) { bar.style.background = gradCss( st( current ) ); }
			}
			function applyLive() {
				var styleTag = $( '#vse-live-css' ); if ( styleTag ) { styleTag.textContent = liveCss(); }
				refreshGradBar();
				autosave(); // persist style edits (debounced) so they actually reach the front end
			}

			// ---- control builders ----
			function svgi( p, s, sw ) {
				return '<svg viewBox="0 0 24 24" width="' + ( s || 14 ) + '" height="' + ( s || 14 ) + '" fill="none" stroke="currentColor" stroke-width="' + ( sw || 2 ) + '" stroke-linecap="round" stroke-linejoin="round">' + p + '</svg>';
			}
			var CHEV = '<path d="m6 9 6 6 6-6"/>';
			var REVERT = '<path d="M9 14 4 9l5-5"/><path d="M4 9h11a5 5 0 0 1 5 5v6"/>';
			var UNITS = [ 'px', 'rem', 'em', '%' ];
			// Values are stored with their unit baked in ("11px", "0.95rem", "auto").
			// Both the PHP and JS CSS builders pass anything containing a letter or %
			// through untouched, so no unit conversion is needed on either side.
			function splitUnit( v, def ) {
				def = def == null ? 'px' : def;
				v = ( v == null ? '' : String( v ) ).trim();
				if ( 'auto' === v ) { return { n: '', u: 'auto' }; }
				var m = v.match( /^(-?[0-9.]+)\s*(px|rem|em|%|deg)?$/ );
				if ( ! m ) { return { n: v, u: def }; }
				return { n: m[1], u: m[2] || def };
			}
			function joinUnit( n, u ) {
				if ( 'auto' === u ) { return 'auto'; }
				n = String( n == null ? '' : n ).trim();
				return '' === n ? '' : n + u;
			}
			function ctrlText( label, target, key, val ) {
				return '<div class="vse-fld"><div class="vse-fk">' + label + '</div>' +
					'<input class="vse-in" data-t="' + target + '" data-k="' + key + '" value="' + escapeHtml( val || '' ) + '"></div>';
			}
			function ctrlSelect( label, target, key, val, opts ) {
				var o = opts.map( function ( x ) {
					return '<option value="' + x.v + '"' + ( String( val == null ? '' : val ) === x.v ? ' selected' : '' ) + '>' + x.l + '</option>';
				} ).join( '' );
				return '<div class="vse-fld"><div class="vse-fk">' + label + '</div>' +
					'<select class="vse-in" data-t="' + target + '" data-k="' + key + '">' + o + '</select></div>';
			}
			var STEP = '<span class="vse-step"><button type="button" data-step="up" tabindex="-1">' + svgi( '<path d="m6 15 6-6 6 6"/>', 9, 2.6 ) + '</button>' +
				'<button type="button" data-step="down" tabindex="-1">' + svgi( CHEV, 9, 2.6 ) + '</button></span>';
			function ctrlNum( label, target, key, val, units ) {
				var arr = units || UNITS;
				var p = splitUnit( val, arr[0] );
				var list = arr.join( ',' );
				return '<div class="vse-fld"><div class="vse-fk">' + label + '</div>' +
					'<div class="vse-num"><input class="vse-nv" type="text" inputmode="decimal" data-t="' + target + '" data-k="' + key + '" data-unit="' + p.u + '" value="' + escapeHtml( 'auto' === p.u ? '' : p.n ) + '" placeholder="\u2014">' +
					STEP +
					'<button type="button" class="vse-unit' + ( 'px' === p.u ? '' : ' is-alt' ) + '" data-t="' + target + '" data-k="' + key + '" data-units="' + list + '">' + ( '' === p.u ? '\u2014' : p.u ) + svgi( CHEV, 8, 2.6 ) + '</button>' +
					'</div></div>';
			}
			// Label on the left, controls hard right — they never collide.
			function ctrlColor( label, target, key, val ) {
				var v = ( val == null ? '' : String( val ) ).trim();
				var has = '' !== v;
				var hexOk = /^#([0-9a-f]{6})$/i.test( v );
				return '<div class="vse-row"><span class="vse-rk">' + label + '</span><span class="vse-rc">' +
					'<span class="vse-sw' + ( has ? '' : ' is-inherit' ) + '"' + ( has ? ' style="background:' + escapeHtml( v ) + '"' : '' ) + '>' +
					'<input type="color" data-t="' + target + '" data-k="' + key + '" data-color="1" value="' + ( hexOk ? v : '#2ab7f1' ) + '"></span>' +
					'<input class="vse-hex' + ( has ? '' : ' is-inherit' ) + '" data-t="' + target + '" data-k="' + key + '" value="' + escapeHtml( v ) + '" placeholder="Inherit">' +
					'<button type="button" class="vse-revert' + ( has ? '' : ' is-hidden' ) + '" data-t="' + target + '" data-k="' + key + '" title="Reset to inherit">' + svgi( REVERT, 13, 1.8 ) + '</button>' +
					'</span></div>';
			}
			function ctrlToggle( label, target, key, on ) {
				return '<div class="vse-row"><span class="vse-rk">' + label + '</span><span class="vse-rc">' +
					'<span class="vse-seg vse-seg--sm"><button type="button" data-t="' + target + '" data-k="' + key + '" data-v=""' + ( on ? '' : ' class="is-on"' ) + '>Off</button>' +
					'<button type="button" data-t="' + target + '" data-k="' + key + '" data-v="1"' + ( on ? ' class="is-on"' : '' ) + '>On</button></span>' +
					'</span></div>';
			}
			function ctrlUrl( label, target, key, val ) {
				return '<div class="vse-fld"><div class="vse-fk">' + label + '</div>' +
					'<input class="vse-in" data-t="' + target + '" data-k="' + key + '" value="' + escapeHtml( val || '' ) + '" placeholder="https://\u2026"></div>';
			}
			function ctrlSeg( label, target, key, val, opts ) {
				var btns = opts.map( function ( o ) {
					return '<button type="button" data-t="' + target + '" data-k="' + key + '" data-v="' + o.v + '"' + ( ( val || opts[0].v ) === o.v ? ' class="is-on"' : '' ) + '>' + o.l + '</button>';
				} ).join( '' );
				return '<div class="vse-ctrl">' + ( label ? '<div class="vse-cl">' + label + '</div>' : '' ) + '<div class="vse-seg">' + btns + '</div></div>';
			}
			function ctrlSides( target, prefix ) {
				var o = st( target );
				function pick( a, b ) {
					var x = o[ a ];
					if ( x == null || '' === x ) { x = o[ b ]; }
					return x == null ? '' : x;
				}
				return '<div class="vse-two">' +
					ctrlNum( 'Top', target, prefix + 't', pick( prefix + 't', prefix + 'tb' ) ) +
					ctrlNum( 'Right', target, prefix + 'r', pick( prefix + 'r', prefix + 'lr' ) ) +
					ctrlNum( 'Bottom', target, prefix + 'b', pick( prefix + 'b', prefix + 'tb' ) ) +
					ctrlNum( 'Left', target, prefix + 'l', pick( prefix + 'l', prefix + 'lr' ) ) +
					'</div>';
			}
			function grp( key, title, inner ) {
				var open = false !== openGroups[ key ];
				return '<div class="vse-grp' + ( open ? ' is-open' : '' ) + '" data-grp="' + key + '">' +
					'<button type="button" class="vse-grp-h"><span>' + title + '</span>' + svgi( CHEV, 14, 2 ) + '</button>' +
					'<div class="vse-grp-b">' + inner + '</div></div>';
			}

			var WEIGHTS = [ { v: '', l: 'Inherit' }, { v: '400', l: 'Regular' }, { v: '500', l: 'Medium' },
				{ v: '600', l: 'Semibold' }, { v: '700', l: 'Bold' } ];
			var SHADOWS = [ { v: 'none', l: 'None' }, { v: 'soft', l: 'Soft' }, { v: 'medium', l: 'Med' },
				{ v: 'strong', l: 'Strong' }, { v: 'custom', l: 'Custom' } ];
			var DIM = [ 'px', 'rem', 'em', '%', 'auto' ];
			var PLAIN = [ 'px', 'rem', 'em', '%' ];

			function grpBackground( t, o, rich ) {
				var mode = o.bgMode || 'color';
				var inner = '';
				if ( rich ) {
					inner += '<span class="vse-seg vse-seg--full">' +
						[ [ 'color', 'Colour' ], [ 'gradient', 'Gradient' ], [ 'image', 'Image' ] ].map( function ( m ) {
							return '<button type="button" data-t="' + t + '" data-k="bgMode" data-v="' + m[0] + '"' +
								( mode === m[0] ? ' class="is-on"' : '' ) + '>' + m[1] + '</button>';
						} ).join( '' ) + '</span>';
				}
				if ( ! rich || 'color' === mode ) {
					inner += ctrlColor( 'Fill', t, 'bg', o.bg );
				} else if ( 'gradient' === mode ) {
					inner += '<div class="vse-gradbar" style="background:' + gradCss( o ) + '"></div>';
					inner += '<div class="vse-two">' +
						ctrlSelect( 'Type', t, 'gradType', o.gradType || 'linear', [ { v: 'linear', l: 'Linear' }, { v: 'radial', l: 'Radial' } ] ) +
						ctrlNum( 'Angle', t, 'gradAngle', o.gradAngle, [ 'deg' ] ) + '</div>';
					inner += ctrlColor( 'From', t, 'gradFrom', o.gradFrom ) + ctrlColor( 'To', t, 'gradTo', o.gradTo );
				} else {
					inner += ctrlUrl( 'Image URL', t, 'imgUrl', o.imgUrl );
					inner += '<div class="vse-two">' +
						ctrlSelect( 'Size', t, 'imgSize', o.imgSize || 'cover', [ { v: 'cover', l: 'Cover' }, { v: 'contain', l: 'Contain' }, { v: 'auto', l: 'Auto' } ] ) +
						ctrlSelect( 'Position', t, 'imgPos', o.imgPos || 'center', [ { v: 'center', l: 'Center' }, { v: 'top', l: 'Top' }, { v: 'bottom', l: 'Bottom' }, { v: 'left', l: 'Left' }, { v: 'right', l: 'Right' } ] ) + '</div>';
					inner += '<div class="vse-two">' +
						ctrlSelect( 'Repeat', t, 'imgRepeat', o.imgRepeat || 'no-repeat', [ { v: 'no-repeat', l: 'No repeat' }, { v: 'repeat', l: 'Tile' }, { v: 'repeat-x', l: 'Tile X' }, { v: 'repeat-y', l: 'Tile Y' } ] ) +
						'</div>';
					inner += ctrlColor( 'Behind image', t, 'bg', o.bg );
				}
				return grp( 'colour', 'Background', inner );
			}
			function grpSize( t, o ) {
				return grp( 'size', 'Size', '<div class="vse-two">' + ctrlNum( 'Width', t, 'w', o.w, DIM ) + ctrlNum( 'Height', t, 'h', o.h, DIM ) + '</div>' +
					'<div class="vse-two">' + ctrlNum( 'Min height', t, 'minh', o.minh, DIM ) + ctrlNum( 'Max width', t, 'maxw', o.maxw, DIM ) + '</div>' );
			}
			function grpText( t, o, withColor ) {
				var inner = '<div class="vse-two">' + ctrlNum( 'Size', t, 'fs', o.fs, PLAIN ) + ctrlSelect( 'Weight', t, 'fw', o.fw, WEIGHTS ) + '</div>' +
					'<div class="vse-two">' + ctrlNum( 'Line height', t, 'lh', o.lh, [ '', 'px', 'rem', 'em' ] ) + ctrlNum( 'Letter spacing', t, 'ls', o.ls, PLAIN ) + '</div>';
				if ( withColor ) { inner += ctrlColor( 'Colour', t, 'color', o.color ); }
				return grp( 'text', 'Text', inner );
			}
			function grpShape( t, o ) {
				return grp( 'shape', 'Shape', '<div class="vse-two">' + ctrlNum( 'Corner', t, 'radius', o.radius, PLAIN ) +
					ctrlNum( 'Border', t, 'border', o.border, PLAIN ) + '</div>' + ctrlColor( 'Border colour', t, 'borderColor', o.borderColor ) );
			}
			function grpSpacing( t, both ) {
				var inner = '<div class="vse-sk">Padding</div>' + ctrlSides( t, 'p' );
				if ( both ) { inner += '<div class="vse-sk">Margin</div>' + ctrlSides( t, 'm' ); }
				return grp( 'spacing', 'Spacing', inner );
			}
			function grpShadow( t, o ) {
				var inner = ctrlSeg( '', t, 'shadow', o.shadow || 'none', SHADOWS );
				if ( 'custom' === o.shadow ) {
					inner += '<div class="vse-shprev" style="box-shadow:' + ( o.shInset ? 'inset ' : '' ) +
						( o.shX || 0 ) + 'px ' + ( o.shY || 0 ) + 'px ' + ( o.shBlur || 0 ) + 'px ' + ( o.shSpread || 0 ) + 'px ' +
						( o.shColor || 'rgba(16,24,40,.22)' ) + '"></div>';
					inner += '<div class="vse-two">' + ctrlNum( 'X', t, 'shX', o.shX, PLAIN ) + ctrlNum( 'Y', t, 'shY', o.shY, PLAIN ) + '</div>';
					inner += '<div class="vse-two">' + ctrlNum( 'Blur', t, 'shBlur', o.shBlur, PLAIN ) + ctrlNum( 'Spread', t, 'shSpread', o.shSpread, PLAIN ) + '</div>';
					inner += ctrlColor( 'Colour', t, 'shColor', o.shColor );
					inner += ctrlToggle( 'Inset', t, 'shInset', !! o.shInset );
				}
				return grp( 'shadow', 'Shadow', inner );
			}

			function renderControls() {
				var o = st( current ), t = curTarget, body = '';
				var bucket = current;

				if ( 'submit' === t ) {
					body += grp( 'content', 'Content', ctrlText( 'Button text', 'submit', 'text', form.submit_label || 'Submit' ) +
						ctrlSeg( 'Alignment', 'submit', 'align', o.align || 'center',
							[ { v: 'left', l: 'Left' }, { v: 'center', l: 'Center' }, { v: 'right', l: 'Right' }, { v: 'full', l: 'Full' } ] ) );
					body += grpBackground( 'submit', o, true );
					body += grp( 'hover', 'Hover', ctrlColor( 'Hover fill', 'submit', 'hoverBg', o.hoverBg ) );
					body += grpSize( 'submit', o );
					body += grpText( 'submit', o, true );
					body += grpShape( 'submit', o );
					body += grpSpacing( 'submit', true );
					body += grpShadow( 'submit', o );
				} else if ( 'form' === t ) {
					body += grpBackground( 'form', o, true );
					body += grpSize( 'form', o );
					body += grpShape( 'form', o );
					body += grpSpacing( 'form', true );
					body += grpShadow( 'form', o );
				} else if ( 'header' === t ) {
					body += grpText( 'header', o, true );
					body += grpSpacing( 'header', true );
				} else if ( 'labels' === t ) {
					var kc = curScope ? 'labelColor' : 'color';
					var ks = curScope ? 'labelFs' : 'fs';
					var kw = curScope ? 'labelFw' : 'fw';
					body += grp( 'colour', 'Colour', ctrlColor( 'Text', bucket, kc, o[ kc ] ) );
					body += grp( 'text', 'Text', '<div class="vse-two">' + ctrlNum( 'Size', bucket, ks, o[ ks ], PLAIN ) +
						ctrlSelect( 'Weight', bucket, kw, o[ kw ], WEIGHTS ) + '</div>' +
						( curScope ? '' : '<div class="vse-two">' + ctrlNum( 'Line height', 'labels', 'lh', o.lh, [ '', 'px', 'rem', 'em' ] ) +
							ctrlNum( 'Letter spacing', 'labels', 'ls', o.ls, PLAIN ) + '</div>' ) );
					if ( ! curScope ) { body += grpSpacing( 'labels', true ); }
				} else {
					body += grpBackground( bucket, o, false );
					body += grpSize( bucket, o );
					body += grpText( bucket, o, true );
					body += grpShape( bucket, o );
					body += grpSpacing( bucket, false );
					body += grpShadow( bucket, o );
				}
				$( '#vse-controls' ).innerHTML = body;
				bindControls();
			}

			function fieldByKey( k ) { return form.fields.filter( function ( f ) { return f.key === k; } )[ 0 ]; }

			function bindControls() {
				var wrap = $( '#vse-controls' );

				// Collapsible groups.
				$$( '.vse-grp-h', wrap ).forEach( function ( h ) {
					h.addEventListener( 'click', function () {
						var g = h.parentNode, key = g.getAttribute( 'data-grp' );
						var open = ! g.classList.contains( 'is-open' );
						g.classList.toggle( 'is-open', open );
						openGroups[ key ] = open;
					} );
				} );

				// Text, select and colour inputs.
				$$( '.vse-in, .vse-hex, input[data-color]', wrap ).forEach( function ( el ) {
					var ev = 'SELECT' === el.tagName ? 'change' : 'input';
					el.addEventListener( ev, function () {
						var t = el.getAttribute( 'data-t' ), k = el.getAttribute( 'data-k' ), v = el.value;
						if ( 'submit' === t && 'text' === k ) { form.submit_label = v; buildPreview(); applyLive(); return; }
						var o = st( t );
						if ( '' === String( v ).trim() ) { delete o[ k ]; } else { o[ k ] = v; }
						if ( el.getAttribute( 'data-color' ) ) {
							var hex = $( '.vse-hex[data-k="' + k + '"][data-t="' + t + '"]', wrap );
							if ( hex ) { hex.value = v; hex.classList.remove( 'is-inherit' ); }
							var sw = el.parentNode;
							if ( sw ) { sw.classList.remove( 'is-inherit' ); sw.style.background = v; }
						}
						applyLive();
					} );
					// Re-render on blur so the revert arrow and inherit state catch up.
					if ( el.classList.contains( 'vse-hex' ) ) {
						el.addEventListener( 'change', function () { renderControls(); } );
					}
				} );

				// Number + unit fields.
				$$( '.vse-nv', wrap ).forEach( function ( el ) {
					el.addEventListener( 'input', function () {
						var t = el.getAttribute( 'data-t' ), k = el.getAttribute( 'data-k' ), u = el.getAttribute( 'data-unit' ) || 'px';
						var o = st( t ), v = joinUnit( el.value, u );
						if ( '' === v ) { delete o[ k ]; } else { o[ k ] = v; }
						applyLive();
					} );
				} );

				// Unit chips: click to swap px / rem / em / % / auto.
				$$( '.vse-unit', wrap ).forEach( function ( btn ) {
					btn.addEventListener( 'click', function ( e ) {
						e.stopPropagation();
						closeUnitMenus();
						var units = ( btn.getAttribute( 'data-units' ) || 'px' ).split( ',' );
						var t = btn.getAttribute( 'data-t' ), k = btn.getAttribute( 'data-k' );
						var cur = splitUnit( st( t )[ k ], units[0] ).u;
						var menu = document.createElement( 'div' );
						menu.className = 'vse-unit-menu';
						menu.innerHTML = units.map( function ( u ) {
							return '<button type="button" data-u="' + u + '"' + ( u === cur ? ' class="is-on"' : '' ) + '>' + ( '' === u ? '\u2014' : u ) + '</button>';
						} ).join( '' );
						btn.parentNode.appendChild( menu );
						$$( 'button', menu ).forEach( function ( b ) {
							b.addEventListener( 'click', function ( ev2 ) {
								ev2.stopPropagation();
								var u = b.getAttribute( 'data-u' ), o = st( t );
								var num = splitUnit( o[ k ] ).n;
								var v = joinUnit( 'auto' === u ? '' : num, u );
								if ( '' === v ) { delete o[ k ]; } else { o[ k ] = v; }
								closeUnitMenus();
								renderControls();
								applyLive();
							} );
						} );
					} );
				} );

				// Revert a single property back to inherit.
				$$( '.vse-revert', wrap ).forEach( function ( b ) {
					b.addEventListener( 'click', function () {
						delete st( b.getAttribute( 'data-t' ) )[ b.getAttribute( 'data-k' ) ];
						renderControls();
						applyLive();
					} );
				} );

				// Segmented options (alignment, background mode, shadow preset, inset).
				var RERENDER = { bgMode: 1, shadow: 1, gradType: 1 };
				$$( '.vse-seg button', wrap ).forEach( function ( btn ) {
					btn.addEventListener( 'click', function () {
						var t = btn.getAttribute( 'data-t' ), k = btn.getAttribute( 'data-k' ), v = btn.getAttribute( 'data-v' );
						var o = st( t );
						if ( '' === v ) { delete o[ k ]; } else { o[ k ] = v; }
						if ( 'bgMode' === k && 'gradient' === v ) {
							if ( ! o.gradFrom ) { o.gradFrom = o.bg || '#2ab7f1'; }
							if ( ! o.gradTo ) { o.gradTo = '#7b5cff'; }
						}
						btn.parentNode.querySelectorAll( 'button' ).forEach( function ( b ) { b.classList.toggle( 'is-on', b === btn ); } );
						applyLive();
						if ( RERENDER[ k ] ) { renderControls(); }
					} );
				} );

				// Stepper buttons + arrow keys on every number field.
				function bump( input, delta ) {
					var cur = parseFloat( input.value );
					if ( isNaN( cur ) ) { cur = 0; }
					var next = Math.round( ( cur + delta ) * 100 ) / 100;
					input.value = String( next );
					input.dispatchEvent( new Event( 'input', { bubbles: true } ) );
				}
				$$( '.vse-step button', wrap ).forEach( function ( b ) {
					b.addEventListener( 'click', function ( e ) {
						e.preventDefault();
						var input = b.closest( '.vse-num' ).querySelector( '.vse-nv' );
						bump( input, 'up' === b.getAttribute( 'data-step' ) ? 1 : -1 );
					} );
				} );
				$$( '.vse-nv', wrap ).forEach( function ( input ) {
					input.addEventListener( 'keydown', function ( e ) {
						if ( 'ArrowUp' !== e.key && 'ArrowDown' !== e.key ) { return; }
						e.preventDefault();
						var mult = e.shiftKey ? 10 : 1;
						bump( input, ( 'ArrowUp' === e.key ? 1 : -1 ) * mult );
					} );
				} );
			}
			function closeUnitMenus() { $$( '.vse-unit-menu' ).forEach( function ( m ) { m.parentNode.removeChild( m ); } ); }

			// ---- sticky target strip + "applies to" scope ----
			function renderStrip() {
				var el = $( '#vse-strip' );
				if ( ! el ) { return; }
				el.innerHTML = TARGETS.map( function ( t ) {
					return '<button type="button" class="vse-tab' + ( t.k === curTarget ? ' is-on' : '' ) + '" data-target="' + t.k + '">' +
						svgi( ICONS[ t.k ], 17, 1.7 ) + '<span>' + t.n + '</span></button>';
				} ).join( '' );
				$$( '.vse-tab', el ).forEach( function ( b ) {
					b.addEventListener( 'click', function () {
						curTarget = b.getAttribute( 'data-target' );
						curScope = '';
						syncCurrent();
						renderStrip(); renderScope(); renderControls(); markTarget();
					} );
				} );
			}
			function scopeLabel() {
				if ( ! curScope ) { return 'inputs' === curTarget ? 'All inputs' : 'All labels'; }
				var f = fieldByKey( curScope );
				return ( f && f.label ) ? f.label : curScope;
			}
			function renderScope() {
				var el = $( '#vse-scope' );
				if ( ! el ) { return; }
				if ( ! scopeable( curTarget ) ) { el.hidden = true; el.innerHTML = ''; return; }
				el.hidden = false;
				var all = 'inputs' === curTarget ? 'All inputs' : 'All labels';
				var fields = styleableFields();
				var items = '<div class="vse-scope-sec">Everything</div>' +
					'<button type="button" class="vse-scope-i' + ( curScope ? '' : ' is-on' ) + '" data-key="">' +
					'<span class="nm">' + all + '</span>' + ( curScope ? '' : svgi( '<path d="m5 13 4 4L19 7"/>', 14, 2.2 ) ) + '</button>';
				if ( fields.length ) {
					items += '<div class="vse-scope-dv"></div><div class="vse-scope-sec">Individual fields</div>';
					items += fields.map( function ( f ) {
						var ty = TYPES[ f.type ] ? TYPES[ f.type ].label : f.type;
						return '<button type="button" class="vse-scope-i' + ( curScope === f.key ? ' is-on' : '' ) + '" data-key="' + escapeHtml( f.key ) + '">' +
							'<span class="nm">' + escapeHtml( f.label || f.key ) + '</span><span class="ty">' + escapeHtml( ty ) + '</span>' +
							( curScope === f.key ? svgi( '<path d="m5 13 4 4L19 7"/>', 14, 2.2 ) : '' ) + '</button>';
					} ).join( '' );
				}
				el.innerHTML = '<span class="vse-scope-ic">' + svgi( '<circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="3"/>', 14, 1.7 ) + '</span>' +
					'<span class="vse-scope-l">Applies to</span>' +
					'<button type="button" class="vse-scope-sel" id="vse-scope-btn"><span>' + escapeHtml( scopeLabel() ) + '</span>' + svgi( CHEV, 13, 2 ) + '</button>' +
					'<div class="vse-scope-pop" id="vse-scope-pop" hidden>' + items + '</div>';

				var btn = $( '#vse-scope-btn', el ), pop = $( '#vse-scope-pop', el );
				btn.addEventListener( 'click', function ( e ) {
					e.stopPropagation();
					pop.hidden = ! pop.hidden;
					btn.classList.toggle( 'is-open', ! pop.hidden );
				} );
				$$( '.vse-scope-i', pop ).forEach( function ( it ) {
					it.addEventListener( 'click', function () {
						curScope = it.getAttribute( 'data-key' ) || '';
						syncCurrent();
						renderScope(); renderControls(); markTarget();
					} );
				} );
			}
			document.addEventListener( 'click', function () {
				var pop = $( '#vse-scope-pop' ), btn = $( '#vse-scope-btn' );
				if ( pop ) { pop.hidden = true; }
				if ( btn ) { btn.classList.remove( 'is-open' ); }
				closeUnitMenus();
			} );

			// ---- open / close / save ----
			var leftCol = $( '.vse-left', root ), stickyEl = $( '.vse-sticky', root );
			if ( leftCol && stickyEl ) {
				leftCol.addEventListener( 'scroll', function () {
					stickyEl.classList.toggle( 'is-pinned', leftCol.scrollTop > 2 );
				} );
			}
			function open() {
				syncCurrent(); buildPreview(); renderStrip(); renderScope(); renderControls(); applyLive();
				var ven = $( '#vse-enabled' ), venLbl = $( '#vse-onoff-label' );
				if ( ven ) { ven.checked = ( form.enabled !== false ); if ( venLbl ) { venLbl.textContent = ven.checked ? 'On' : 'Off'; venLbl.classList.toggle( 'is-on', ven.checked ); } }
				root.hidden = false; document.body.style.overflow = 'hidden';
			}
			function close() { root.hidden = true; document.body.style.overflow = ''; if ( window.veloxMailHighlightBuild ) { window.veloxMailHighlightBuild(); } }
			openStyleEditor = open;
			$( '#vmail-style-btn' ).addEventListener( 'click', open );
			// Shared-navbar mode switcher inside the style editor: Build + back close it.
			var toBuild = $( '#vse-to-build' ), backBtn = $( '#vse-back' );
			if ( toBuild ) { toBuild.addEventListener( 'click', close ); }
			if ( backBtn ) { backBtn.addEventListener( 'click', close ); }
			// On/off toggle in the style-editor navbar, kept in sync with the build one.
			var ven = $( '#vse-enabled' ), venLbl = $( '#vse-onoff-label' );
			if ( ven ) {
				ven.addEventListener( 'change', function () {
					form.enabled = ven.checked;
					if ( venLbl ) { venLbl.textContent = ven.checked ? 'On' : 'Off'; venLbl.classList.toggle( 'is-on', ven.checked ); }
					var ben = $( '#vmail-enabled' ), benLbl = $( '#vmail-onoff-label' );
					if ( ben ) { ben.checked = ven.checked; }
					if ( benLbl ) { benLbl.textContent = ven.checked ? 'On' : 'Off'; benLbl.classList.toggle( 'is-on', ven.checked ); }
					autosave(); toast( ven.checked ? 'Form is on.' : 'Form is off — hidden from visitors.' );
				} );
				if ( venLbl ) { venLbl.classList.toggle( 'is-on', ven.checked ); }
			}
			var toPrev = $( '#vse-to-preview' );
			if ( toPrev ) { toPrev.addEventListener( 'click', function () { close(); if ( openPreviewOverlay ) { openPreviewOverlay(); } } ); }
			$( '#vse-save' ).addEventListener( 'click', function () {
				// Persist the whole form (styles included) but STAY in the style editor.
				var b = this; b.disabled = true;
				api( 'form_save', { form: JSON.stringify( form ) } )
					.then( function () { toast( 'Styles saved.' ); } )
					.catch( function ( e ) { toast( e.message, 'error' ); } )
					.then( function () { b.disabled = false; } );
			} );
			$( '#vse-reset' ).addEventListener( 'click', function () {
				form.style = {}; S = form.style; curTarget = 'form'; curScope = ''; syncCurrent(); buildPreview(); renderStrip(); renderScope(); renderControls(); applyLive();
			} );
			$$( '#vse-device button' ).forEach( function ( d ) {
				d.addEventListener( 'click', function () {
					$$( '#vse-device button' ).forEach( function ( x ) { x.classList.toggle( 'is-on', x === d ); } );
					var c = $( '#vse-canvas' ); c.className = 'vse-canvas' + ( d.getAttribute( 'data-dev' ) === 'tablet' ? ' is-tablet' : d.getAttribute( 'data-dev' ) === 'mobile' ? ' is-mobile' : '' );
				} );
			} );
		}
	}

	function initScripts() {
		var toggle = $( '#velox-sm-toggle' );
		if ( toggle ) {
			toggle.addEventListener( 'change', function () {
				saveSettings( { util_scripts: toggle.checked ? 1 : 0 }, toggle.checked ? 'Script Manager on.' : 'Script Manager off.' )
					.then( function () { setTimeout( function () { location.reload(); }, 400 ); } );
			} );
		}
		var list = $( '.velox-sm-list' );
		if ( ! list ) {
			return;
		}
		// Show/hide the ids box when the mode needs targets.
		$$( '.velox-sm-row' ).forEach( function ( row ) {
			var mode = row.querySelector( '.velox-sm-mode' );
			var ids  = row.querySelector( '.velox-sm-ids' );
			mode.addEventListener( 'change', function () {
				ids.hidden = ! ( mode.value === 'except' || mode.value === 'only' );
			} );
		} );

		function collectRules() {
			var rules = {};
			$$( '.velox-sm-row' ).forEach( function ( row ) {
				var mode = row.querySelector( '.velox-sm-mode' ).value;
				if ( mode === 'off' ) { return; }
				var handle = row.getAttribute( 'data-handle' );
				var type   = row.getAttribute( 'data-type' );
				rules[ type + ':' + handle ] = {
					handle: handle,
					type: type,
					mode: mode,
					ids: row.querySelector( '.velox-sm-ids' ).value,
				};
			} );
			return rules;
		}

		function save( btn ) {
			btn.disabled = true;
			api( 'scripts_save', { rules: JSON.stringify( collectRules() ) } )
				.then( function ( r ) { toast( 'Saved ' + ( r.count || 0 ) + ' rule(s).' ); } )
				.catch( function ( e ) { toast( e.message, 'error' ); } )
				.then( function () { btn.disabled = false; } );
		}
		[ '#velox-sm-save', '#velox-sm-save-2' ].forEach( function ( sel ) {
			var b = $( sel );
			if ( b ) { b.addEventListener( 'click', function () { save( b ); } ); }
		} );

		var scanBtn = $( '#velox-sm-scan' );
		if ( scanBtn ) {
			scanBtn.addEventListener( 'click', function () {
				scanBtn.disabled = true;
				scanBtn.innerHTML = '<span class="velox-btn-spin"></span>Scanning…';
				api( 'scripts_scan', {} )
					.then( function ( r ) {
						if ( ! r.ok ) {
							toast( r.message || 'Scan failed.', 'error' );
							scanBtn.disabled = false;
							scanBtn.textContent = 'Scan site';
							return;
						}
						var pages = r.pages ? ( r.pages + ' page' + ( r.pages === 1 ? '' : 's' ) + ' · ' ) : '';
						scanBtn.innerHTML = '<span class="velox-btn-spin"></span>Loading results…';
						toast( 'Scanned ' + pages + r.scripts + ' scripts, ' + r.styles + ' styles.' );
						// Reload so the freshly discovered handles appear immediately.
						setTimeout( function () { location.reload(); }, 600 );
					} )
					.catch( function ( e ) {
						toast( e.message, 'error' );
						scanBtn.disabled = false;
						scanBtn.textContent = 'Scan site';
					} );
			} );
		}

		var clearBtn = $( '#velox-sm-clear' );
		if ( clearBtn ) {
			clearBtn.addEventListener( 'click', function () {
				if ( ! window.confirm( 'Clear the discovered handle list? Your rules stay saved.' ) ) { return; }
				api( 'scripts_clear', {} )
					.then( function () { location.reload(); } )
					.catch( function ( e ) { toast( e.message, 'error' ); } );
			} );
		}
	}

	function initActivity() {
		var toggle = $( '#velox-activity-toggle' );
		if ( toggle ) {
			toggle.addEventListener( 'change', function () {
				saveSettings( { util_activity: toggle.checked ? 1 : 0 }, toggle.checked ? 'Recording activity.' : 'Stopped recording.' )
					.then( function () { setTimeout( function () { location.reload(); }, 400 ); } );
			} );
		}
		var clearBtn = $( '#velox-activity-clear' );
		if ( clearBtn ) {
			clearBtn.addEventListener( 'click', function () {
				if ( ! window.confirm( 'Clear the entire activity log?' ) ) { return; }
				api( 'activity_clear', {} )
					.then( function () { location.reload(); } )
					.catch( function ( e ) { toast( e.message, 'error' ); } );
			} );
		}
	}

	function initUnusedMedia() {
		var scanBtn = $( '#velox-media-scan' );
		if ( ! scanBtn ) {
			return;
		}
		var delBtn  = $( '#velox-media-delete' );
		var results = $( '#velox-media-results' );
		var summary = $( '#velox-media-summary' );
		var filterWrap = $( '#velox-media-filter' );
		var mediaItems = [];
		var mediaMode  = 'unused';

		// Lightbox (shared by both tabs).
		var lb = null;
		function lightbox( src, name ) {
			if ( ! lb ) {
				lb = document.createElement( 'div' );
				lb.className = 'velox-lightbox';
				lb.innerHTML = '<div class="velox-lightbox-inner"><button type="button" class="velox-lightbox-x" aria-label="Close">&times;</button><img alt=""><div class="velox-lightbox-cap"></div></div>';
				lb.addEventListener( 'click', function ( e ) {
					if ( e.target === lb || e.target.classList.contains( 'velox-lightbox-x' ) ) { lb.classList.remove( 'is-open' ); }
				} );
				document.addEventListener( 'keydown', function ( e ) { if ( e.key === 'Escape' && lb ) { lb.classList.remove( 'is-open' ); } } );
				document.body.appendChild( lb );
			}
			lb.querySelector( 'img' ).src = src;
			lb.querySelector( '.velox-lightbox-cap' ).textContent = name || '';
			lb.classList.add( 'is-open' );
		}

		function fmtBytes( b ) {
			if ( ! b ) { return '0 KB'; }
			var u = [ 'B', 'KB', 'MB', 'GB' ], i = Math.floor( Math.log( b ) / Math.log( 1024 ) );
			return ( b / Math.pow( 1024, i ) ).toFixed( 1 ) + ' ' + u[ i ];
		}
		function refreshSelection() {
			var checked = $$( '.velox-media-pick:checked', results );
			delBtn.hidden = checked.length === 0;
			delBtn.textContent = 'Delete selected (' + checked.length + ')';
		}

		function renderMedia() {
			results.innerHTML = '';
			delBtn.hidden = true;
			var subset = mediaItems.filter( function ( it ) { return ( it.state || 'unused' ) === mediaMode; } );
			if ( ! subset.length ) {
				results.innerHTML = 'used' === mediaMode
					? '<p class="velox-hint">Nothing is confirmed in use yet — run a scan.</p>'
					: '<p class="velox-hint">Nothing flagged — every image looks referenced. 🎉</p>';
				summary.textContent = '';
				return;
			}
			var total = 0;
			subset.forEach( function ( it ) {
				total += it.bytes || 0;
				var pickable = ( 'unused' === mediaMode );
				var card = document.createElement( pickable ? 'label' : 'div' );
				card.className = 'velox-media-item' + ( pickable ? '' : ' is-used' );
				card.innerHTML =
					( pickable ? '<input type="checkbox" class="velox-media-pick" value="' + it.id + '">' : '' ) +
					'<img src="' + it.thumb + '" data-full="' + ( it.url || it.thumb ) + '" data-name="' + ( it.title || ( '#' + it.id ) ) + '" alt="" loading="lazy">' +
					'<span class="velox-media-name">' + ( it.title || ( '#' + it.id ) ) + '</span>' +
					'<span class="velox-media-size">' + fmtBytes( it.bytes ) + '</span>' +
					( it.where ? '<span class="velox-media-where" title="' + escapeHtml( it.where ) + '">' + escapeHtml( it.where ) + '</span>' : '' ) +
					'';
				results.appendChild( card );
			} );
			$$( '.velox-media-item img', results ).forEach( function ( img ) {
				img.addEventListener( 'click', function ( e ) {
					e.preventDefault(); e.stopPropagation();
					lightbox( img.getAttribute( 'data-full' ), img.getAttribute( 'data-name' ) );
				} );
			} );
			if ( mediaMode !== 'unused' ) {
				summary.textContent = subset.length + ' image' + ( subset.length === 1 ? '' : 's' ) + ' · ' + fmtBytes( total ) + ' total';
			} else {
				summary.textContent = subset.length + ' possibly-unused image' + ( subset.length === 1 ? '' : 's' ) + ' · ' + fmtBytes( total ) + ' reclaimable';
				$$( '.velox-media-pick', results ).forEach( function ( c ) { c.addEventListener( 'change', refreshSelection ); } );
			}
		}

		$$( '[data-mediafilter]', filterWrap ).forEach( function ( b ) {
			b.addEventListener( 'click', function () {
				mediaMode = b.getAttribute( 'data-mediafilter' );
				$$( '[data-mediafilter]', filterWrap ).forEach( function ( x ) { x.classList.toggle( 'is-on', x === b ); } );
				renderMedia();
			} );
		} );

		// The scan runs in batches: each step walks a slice of one source, so a
		// big library never blows the PHP time limit and progress is visible.
		function scanProgress( pct, label ) {
			results.innerHTML =
				'<div class="velox-scan">' +
					'<div class="velox-scan-label">' + escapeHtml( label || 'Scanning…' ) + '</div>' +
					'<div class="velox-scan-bar"><span style="width:' + pct + '%"></span></div>' +
					'<div class="velox-scan-pct">' + pct + '%</div>' +
				'</div>';
		}
		// Pull every uploads reference out of a blob of HTML or CSS. Works the same
		// for any builder, because by this point it's all just markup and url().
		function extractUploads( text ) {
			var out = [], re = /uploads\/((?:\d{4}\/\d{2}\/)?[^\s"'\\)<>\[\]]+?\.[a-z0-9]{2,5})/gi, m;
			var flat = text.indexOf( '\\/' ) !== -1 ? text.replace( /\\\//g, '/' ) : text;
			while ( ( m = re.exec( flat ) ) !== null ) { out.push( m[1] ); }
			return out;
		}
		var cssDone = {};
		function crawlOne( url ) {
			return fetch( url, { credentials: 'same-origin' } )
				.then( function ( r ) { return r.ok ? r.text() : ''; } )
				.then( function ( html ) {
					if ( ! html ) { return []; }
					var found = extractUploads( html );
					// Background images live in stylesheets, not the markup — this is
					// the part a database scan can never see. Each sheet once.
					var sheets = [];
					try {
						var doc = new DOMParser().parseFromString( html, 'text/html' );
						$$( 'link[rel="stylesheet"]', doc ).forEach( function ( l ) {
							var href = l.getAttribute( 'href' ) || '';
							if ( ! href ) { return; }
							var abs = new URL( href, url ).href;
							if ( abs.indexOf( window.location.origin ) !== 0 ) { return; }
							if ( cssDone[ abs ] ) { return; }
							cssDone[ abs ] = 1;
							sheets.push( abs );
						} );
					} catch ( e ) {}
					if ( ! sheets.length ) { return found; }
					return Promise.all( sheets.map( function ( href ) {
						return fetch( href, { credentials: 'same-origin' } )
							.then( function ( r ) { return r.ok ? r.text() : ''; } )
							.then( function ( css ) { return css ? extractUploads( css ) : []; } )
							.catch( function () { return []; } );
					} ) ).then( function ( lists ) {
						lists.forEach( function ( l ) { found = found.concat( l ); } );
						return found;
					} );
				} )
				.catch( function () { return []; } );
		}
		function crawlPages( urls ) {
			var i = 0, total = urls.length;
			function next() {
				if ( i >= total ) { return api( 'media_crawl_done', { pages: total } ); }
				var url = urls[ i++ ];
				var label = url.replace( window.location.origin, '' ) || '/';
				scanProgress( 70 + Math.floor( ( i / total ) * 13 ), 'Reading page ' + i + ' of ' + total );
				return crawlOne( url ).then( function ( paths ) {
					if ( ! paths.length ) { return null; }
					return api( 'media_crawl_report', { paths: JSON.stringify( paths ), label: label } );
				} ).then( next, next );
			}
			return next();
		}
		function runScan() {
			return api( 'media_scan_step', {} ).then( function ( p ) {
				scanProgress( p.percent || 0, p.label );
				if ( p.crawl ) {
					return crawlPages( p.urls || [] ).then( runScan );
				}
				if ( p.done ) { return p; }
				return runScan();
			} );
		}
		scanBtn.addEventListener( 'click', function () {
			scanBtn.disabled = true;
			scanBtn.textContent = 'Scanning…';
			summary.textContent = '';
			scanProgress( 1, 'Starting…' );
			api( 'media_scan_start', {} )
				.then( runScan )
				.then( function () { return api( 'media_scan_results', { filter: 'all' } ); } )
				.then( function ( d ) {
					mediaItems = ( d.items || [] ).map( function ( it ) {
						it.used = ( 'unused' !== it.state );
						return it;
					} );
					filterWrap.hidden = mediaItems.length === 0;
					var c = d.counts || {};
					var cov = d.crawled ? ( ' · read ' + d.crawled + ' page' + ( 1 === d.crawled ? '' : 's' ) ) : '';
					summary.textContent = ( c.used || 0 ) + ' in use · ' + ( c.unused || 0 ) + ' not in use' + cov;
					renderMedia();
				} )
				.catch( function ( e ) { toast( e.message, 'error' ); results.innerHTML = ''; } )
				.then( function () { scanBtn.disabled = false; scanBtn.textContent = 'Scan media library'; } );
		} );

		delBtn.addEventListener( 'click', function () {
			var ids = $$( '.velox-media-pick:checked', results ).map( function ( c ) { return c.value; } );
			if ( ! ids.length || ! window.confirm( 'Permanently delete ' + ids.length + ' file(s)? This cannot be undone.' ) ) {
				return;
			}
			delBtn.disabled = true;
			api( 'media_delete', { ids: ids } )
				.then( function ( r ) {
					toast( 'Deleted ' + ( r.deleted || 0 ) + ' file(s), freed ' + fmtBytes( r.freed || 0 ) + '.' );
					var gone = {};
					ids.forEach( function ( i ) { gone[ i ] = true; } );
					mediaItems = mediaItems.filter( function ( it ) { return ! gone[ it.id ]; } );
					renderMedia();
				} )
				.catch( function ( e ) { toast( e.message, 'error' ); } )
				.then( function () { delBtn.disabled = false; } );
		} );
	}

	function initInstaller() {
		var runBtn = $( '#velox-installer-run' );
		if ( ! runBtn ) {
			return;
		}
		var slugsEl  = $( '#velox-installer-slugs' );
		var actEl    = $( '#velox-installer-activate' );
		var log      = $( '#velox-installer-log' );
		var saveBtn  = $( '#velox-blueprint-save' );
		var nameEl   = $( '#velox-blueprint-name' );
		var list     = $( '#velox-blueprint-list' );
		var uploadBtn = $( '#velox-installer-upload' );
		var zipEl     = $( '#velox-installer-zip' );

		function parseSources() {
			return ( slugsEl.value || '' )
				.split( /[\n,]+/ )
				.map( function ( s ) { return s.trim(); } )
				.filter( Boolean );
		}
		function logLine( label, state, msg ) {
			var row = document.createElement( 'div' );
			row.className = 'velox-install-row is-' + state;
			row.innerHTML = '<span class="velox-install-slug"></span><span class="velox-install-msg"></span>';
			row.querySelector( '.velox-install-slug' ).textContent = label;
			row.querySelector( '.velox-install-msg' ).textContent = msg;
			log.appendChild( row );
			return row;
		}
		// Run a list of {label, send} jobs one at a time so progress is visible and we avoid timeouts.
		function runQueue( jobs, btn, btnLabel, done ) {
			log.hidden = false;
			log.innerHTML = '';
			btn.disabled = true;
			var origText = btn.textContent;
			btn.textContent = 'Working…';
			var i = 0;
			function next() {
				if ( i >= jobs.length ) {
					btn.disabled = false;
					btn.textContent = origText;
					toast( 'Done.' );
					if ( done ) { done(); }
					return;
				}
				var job = jobs[ i++ ];
				var row = logLine( job.label, 'pending', 'Working…' );
				job.send()
					.then( function ( r ) {
						row.className = 'velox-install-row is-' + ( r.ok ? 'ok' : 'fail' );
						row.querySelector( '.velox-install-msg' ).textContent = r.message || ( r.ok ? 'Done.' : 'Failed.' );
					} )
					.catch( function ( e ) {
						row.className = 'velox-install-row is-fail';
						row.querySelector( '.velox-install-msg' ).textContent = e.message || 'Failed.';
					} )
					.then( next );
			}
			next();
		}

		runBtn.addEventListener( 'click', function () {
			var sources = parseSources();
			if ( ! sources.length ) {
				toast( 'Add at least one slug or link.', 'error' );
				return;
			}
			var activate = actEl.checked;
			runQueue( sources.map( function ( src ) {
				return { label: src, send: function () { return api( 'installer_install', { source: src, activate: activate ? '1' : 'false' } ); } };
			} ), runBtn );
		} );

		if ( uploadBtn && zipEl ) {
			uploadBtn.addEventListener( 'click', function () {
				var files = zipEl.files ? Array.prototype.slice.call( zipEl.files ) : [];
				if ( ! files.length ) {
					toast( 'Choose at least one .zip file first.', 'error' );
					return;
				}
				var activate = actEl.checked;
				runQueue( files.map( function ( f ) {
					return { label: f.name, send: function () { return api( 'installer_upload', { plugin_zip: f, activate: activate ? '1' : 'false' } ); } };
				} ), uploadBtn, null, function () { zipEl.value = ''; } );
			} );
		}

		saveBtn.addEventListener( 'click', function () {
			var slugs = parseSources();
			var name  = ( nameEl.value || '' ).trim();
			if ( ! name ) { toast( 'Name the blueprint first.', 'error' ); return; }
			if ( ! slugs.length ) { toast( 'Add some plugins to save.', 'error' ); return; }
			saveBtn.disabled = true;
			api( 'blueprint_save', { name: name, slugs: slugs } )
				.then( function () { toast( 'Blueprint saved.' ); setTimeout( function () { location.reload(); }, 500 ); } )
				.catch( function ( e ) { toast( e.message, 'error' ); saveBtn.disabled = false; } );
		} );

		if ( list ) {
			list.addEventListener( 'click', function ( e ) {
				var item = e.target.closest( '.velox-bp-item' );
				if ( ! item ) { return; }
				if ( e.target.classList.contains( 'velox-bp-load' ) ) {
					slugsEl.value = item.getAttribute( 'data-slugs' );
					nameEl.value  = item.getAttribute( 'data-name' );
					slugsEl.scrollIntoView( { behavior: 'smooth', block: 'center' } );
					toast( 'Loaded into the installer.' );
				} else if ( e.target.classList.contains( 'velox-bp-del' ) ) {
					if ( ! window.confirm( 'Delete this blueprint?' ) ) { return; }
					api( 'blueprint_delete', { name: item.getAttribute( 'data-name' ) } )
						.then( function () { item.remove(); toast( 'Deleted.' ); } )
						.catch( function ( e2 ) { toast( e2.message, 'error' ); } );
				}
			} );
		}
	}

	function initRedirects() {
		var list = $( '#velox-redir-list' );
		if ( ! list ) {
			return;
		}
		var modal   = $( '#velox-redir-modal' );
		var openBtn = $( '#velox-redir-open' );
		var saveBtn = $( '#velox-redir-save' );
		var titleEl = $( '#velox-redir-modal-title' );

		var f = {
			id:       $( '#velox-redir-id' ),
			source:   $( '#velox-redir-source' ),
			target:   $( '#velox-redir-target' ),
			type:     $( '#velox-redir-type' ),
			match:    $( '#velox-redir-match' ),
			priority: $( '#velox-redir-priority' ),
			category: $( '#velox-redir-category' ),
			desc:     $( '#velox-redir-desc' ),
			active:   $( '#velox-redir-active' ),
			ic:       $( '#velox-redir-ic' ),
			iq:       $( '#velox-redir-iq' ),
			is:       $( '#velox-redir-is' )
		};
		var targetField = $( '#velox-redir-target-field' );

		function syncTargetVisibility() {
			if ( targetField ) {
				targetField.style.display = ( '410' === String( f.type.value ) ) ? 'none' : '';
			}
		}
		if ( f.type ) { f.type.addEventListener( 'change', syncTargetVisibility ); }

		function openModal() {
			if ( ! modal ) { return; }
			modal.removeAttribute( 'hidden' );
			modal.classList.add( 'is-open' );
			document.addEventListener( 'keydown', onKey );
			if ( f.source ) { setTimeout( function () { f.source.focus(); }, 30 ); }
		}
		function closeModal() {
			if ( ! modal ) { return; }
			modal.classList.remove( 'is-open' );
			modal.setAttribute( 'hidden', '' );
			document.removeEventListener( 'keydown', onKey );
		}
		function onKey( e ) { if ( 'Escape' === e.key ) { closeModal(); } }

		function newRedirect() {
			f.id.value = '0';
			f.source.value = ''; f.target.value = '';
			f.type.value = '301'; f.match.value = 'exact';
			f.priority.value = '0'; f.category.value = ''; f.desc.value = '';
			f.active.checked = true; f.ic.checked = true; f.iq.checked = true; f.is.checked = true;
			if ( titleEl ) { titleEl.textContent = 'New redirect'; }
			syncTargetVisibility();
			openModal();
		}
		function editRedirect( row ) {
			f.id.value = row.getAttribute( 'data-id' ) || '0';
			f.source.value = row.getAttribute( 'data-source' ) || '';
			f.target.value = row.getAttribute( 'data-target' ) || '';
			f.type.value = row.getAttribute( 'data-type' ) || '301';
			f.match.value = row.getAttribute( 'data-match' ) || 'exact';
			f.priority.value = row.getAttribute( 'data-priority' ) || '0';
			f.category.value = row.getAttribute( 'data-category' ) || '';
			f.desc.value = row.getAttribute( 'data-description' ) || '';
			f.active.checked = '0' !== row.getAttribute( 'data-active' );
			f.ic.checked = '0' !== row.getAttribute( 'data-ignore-case' );
			f.iq.checked = '0' !== row.getAttribute( 'data-ignore-query' );
			f.is.checked = '0' !== row.getAttribute( 'data-ignore-slash' );
			if ( titleEl ) { titleEl.textContent = 'Edit redirect'; }
			syncTargetVisibility();
			openModal();
		}

		if ( openBtn ) { openBtn.addEventListener( 'click', newRedirect ); }
		if ( modal ) {
			modal.querySelectorAll( '[data-redir-close]' ).forEach( function ( el ) {
				el.addEventListener( 'click', closeModal );
			} );
			modal.addEventListener( 'click', function ( e ) {
				if ( e.target === modal ) { closeModal(); }
			} );
		}

		if ( saveBtn ) {
			saveBtn.addEventListener( 'click', function () {
				if ( ! f.source.value.trim() ) { toast( 'Enter a source path or pattern.', 'error' ); f.source.focus(); return; }
				var editing = '0' !== String( f.id.value );
				saveBtn.disabled = true;
				var data = {
					source:       f.source.value,
					target:       f.target.value,
					type:         f.type.value,
					match_type:   f.match.value,
					priority:     f.priority.value,
					category:     f.category.value,
					description:  f.desc.value,
					active:       f.active.checked ? 1 : 0,
					ignore_case:  f.ic.checked ? 1 : 0,
					ignore_query: f.iq.checked ? 1 : 0,
					ignore_slash: f.is.checked ? 1 : 0
				};
				if ( editing ) { data.id = f.id.value; }
				api( editing ? 'redirect_update' : 'redirect_add', data )
					.then( function ( r ) {
						if ( ! r.ok ) { toast( r.message || 'Could not save.', 'error' ); return; }
						toast( editing ? 'Redirect updated.' : 'Redirect added.' );
						closeModal();
						setTimeout( function () { location.reload(); }, 400 );
					} )
					.catch( function ( e ) { toast( e.message, 'error' ); } )
					.then( function () { saveBtn.disabled = false; } );
			} );
		}

		list.addEventListener( 'click', function ( e ) {
			var row = e.target.closest( '.velox-redir-row' );
			if ( ! row ) { return; }
			if ( e.target.classList.contains( 'velox-redir-del' ) ) {
				api( 'redirect_delete', { id: row.getAttribute( 'data-id' ) } )
					.then( function () { row.remove(); toast( 'Removed.' ); } )
					.catch( function ( er ) { toast( er.message, 'error' ); } );
			} else if ( e.target.classList.contains( 'velox-redir-edit' ) ) {
				editRedirect( row );
			} else if ( e.target.classList.contains( 'velox-redir-visit' ) ) {
				var url = row.getAttribute( 'data-visit' );
				if ( url ) { window.open( url, '_blank', 'noopener' ); }
			}
		} );

		// Per-redirect on/off toggle.
		list.addEventListener( 'change', function ( e ) {
			if ( ! e.target.classList.contains( 'velox-redir-active' ) ) { return; }
			var row = e.target.closest( '.velox-redir-row' );
			if ( ! row ) { return; }
			var on = e.target.checked;
			row.classList.toggle( 'is-off', ! on );
			row.setAttribute( 'data-active', on ? '1' : '0' );
			api( 'redirect_toggle', { id: row.getAttribute( 'data-id' ), on: on ? '1' : '0' } )
				.then( function () { toast( on ? 'Redirect enabled.' : 'Redirect disabled.' ); } )
				.catch( function ( er ) {
					toast( er.message, 'error' );
					e.target.checked = ! on; // revert on failure
					row.classList.toggle( 'is-off', on );
					row.setAttribute( 'data-active', on ? '0' : '1' );
				} );
		} );

		var logToggle = $( '#velox-log-toggle' );
		if ( logToggle ) {
			logToggle.addEventListener( 'change', function () {
				saveSettings( { util_redirects_log_404: logToggle.checked ? 1 : 0 }, logToggle.checked ? 'Logging 404s.' : 'Stopped logging.' )
					.then( function () { setTimeout( function () { location.reload(); }, 350 ); } );
			} );
		}

		var clearBtn = $( '#velox-log-clear' );
		if ( clearBtn ) {
			clearBtn.addEventListener( 'click', function () {
				if ( ! window.confirm( 'Clear the whole 404 log?' ) ) { return; }
				api( 'log_clear', {} )
					.then( function () { location.reload(); } )
					.catch( function ( e ) { toast( e.message, 'error' ); } );
			} );
		}

		var logList = $( '#velox-log-list' );
		if ( logList ) {
			logList.addEventListener( 'click', function ( e ) {
				var row = e.target.closest( '.velox-log-row' );
				if ( ! row ) { return; }
				if ( e.target.classList.contains( 'velox-log-fix' ) ) {
					newRedirect();
					f.source.value = row.getAttribute( 'data-path' ) || '';
					if ( f.target ) { f.target.focus(); }
				} else if ( e.target.classList.contains( 'velox-log-forget' ) ) {
					api( 'log_forget', { id: row.getAttribute( 'data-id' ) } )
						.then( function () { row.remove(); toast( 'Removed.' ); } )
						.catch( function ( er ) { toast( er.message, 'error' ); } );
				}
			} );
		}
	}

	function initSeo() {
		var robots = $( '#velox-seo-robots' );
		var applyBtn = $( '#velox-seo-apply' );
		if ( ! robots && ! applyBtn ) {
			return;
		}
		var genBtn = $( '#velox-seo-smap-gen' );

		// Persist the robots.txt + sitemap enable toggles the moment they change.
		// (Previously these had data-setting but no handler, so they never saved
		// and snapped back on reload.)
		var robotsEnable = $( '#velox-seo-robots-enable' );
		if ( robotsEnable ) {
			robotsEnable.addEventListener( 'change', function () {
				saveSettings( { seo_robots_enable: robotsEnable.checked ? 1 : 0 }, robotsEnable.checked ? 'robots.txt enabled.' : 'robots.txt disabled.' );
			} );
		}
		var sitemapEnable = $( '#velox-seo-sitemap-enable' );
		if ( sitemapEnable ) {
			sitemapEnable.addEventListener( 'change', function () {
				saveSettings( { seo_sitemap_enable: sitemapEnable.checked ? 1 : 0 }, sitemapEnable.checked ? 'Sitemap enabled.' : 'Sitemap disabled.' );
			} );
		}
		var ogEnable = $( '#velox-seo-og-enable' );
		if ( ogEnable ) {
			ogEnable.addEventListener( 'change', function () {
				saveSettings( { seo_og_enable: ogEnable.checked ? 1 : 0 }, ogEnable.checked ? 'Social cards on.' : 'Social cards off.' );
			} );
		}

		// Sitemap settings: persist on change + live preview with example URLs.
		var smapPreview = $( '#velox-smap-preview' );
		if ( smapPreview ) {
			function smapUrl( loc, priority, cf, lastmod ) {
				var o = '  <url>\n    <loc>' + loc + '</loc>\n';
				if ( lastmod ) { o += '    <lastmod>' + lastmod + '</lastmod>\n'; }
				if ( cf ) { o += '    <changefreq>' + cf + '</changefreq>\n'; }
				if ( priority ) { o += '    <priority>' + priority + '</priority>\n'; }
				return o + '  </url>\n';
			}
			var smapEntries = null; // null until loaded from the server; then the real entries
			var smapTotal = 0;
			function smapG( id ) { return document.getElementById( id ); }
			function smapExample() {
				var today = new Date().toISOString().slice( 0, 10 );
				var cf = ( smapG( 'velox-smap-changefreq' ) || {} ).value || 'weekly';
				var pr = ( smapG( 'velox-smap-priority' ) || {} ).value || '0.7';
				var out = [];
				if ( smapG( 'velox-smap-home' ) && smapG( 'velox-smap-home' ).checked ) { out.push( { loc: 'https://example.com/', priority: '1.0', changefreq: cf, lastmod: today } ); }
				if ( smapG( 'velox-smap-pages' ) && smapG( 'velox-smap-pages' ).checked ) { out.push( { loc: 'https://example.com/about/', priority: pr, changefreq: cf, lastmod: today }, { loc: 'https://example.com/contact/', priority: pr, changefreq: cf, lastmod: today } ); }
				if ( smapG( 'velox-smap-posts' ) && smapG( 'velox-smap-posts' ).checked ) { out.push( { loc: 'https://example.com/blog/sample-post/', priority: pr, changefreq: cf, lastmod: today } ); }
				if ( smapG( 'velox-smap-products' ) && smapG( 'velox-smap-products' ).checked ) { out.push( { loc: 'https://example.com/product/sample-product/', priority: pr, changefreq: cf, lastmod: today } ); }
				return out;
			}
			function smapList() { return ( null !== smapEntries ) ? smapEntries : smapExample(); }
			function loadSmapEntries() {
				api( 'seo_sitemap_preview', {} )
					.then( function ( r ) { smapEntries = ( r && r.entries ) ? r.entries : []; smapTotal = r ? ( r.total || smapEntries.length ) : smapEntries.length; updateSmapView(); } )
					.catch( function () { updateSmapView(); } );
			}
			function buildSmap() {
				var list = smapList();
				var xml = '<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n';
				list.forEach( function ( e ) { xml += smapUrl( e.loc, e.priority, e.changefreq, ( e.lastmod || '' ).slice( 0, 10 ) ); } );
				if ( ! list.length ) { xml += '  <!-- no URLs: turn on a section above -->\n'; }
				xml += '</urlset>';
				var esc = xml.replace( /&/g, '&amp;' ).replace( /</g, '&lt;' ).replace( />/g, '&gt;' );
				esc = esc.replace( /(&lt;\?[^]*?\?&gt;)/g, '<span class="vx-xml-decl">$1</span>' );
				esc = esc.replace( /(&lt;!--[^]*?--&gt;)/g, '<span class="vx-xml-val">$1</span>' );
				esc = esc.replace( /(&lt;\/?[a-zA-Z][^&]*?&gt;)/g, '<span class="vx-xml-tag">$1</span>' );
				esc = esc.replace( /(&gt;<\/span>)([^<\n]+)(<span class="vx-xml-tag">&lt;\/)/g, '$1<span class="vx-xml-val">$2</span>$3' );
				smapPreview.innerHTML = esc;
			}
			[ 'velox-smap-home', 'velox-smap-posts', 'velox-smap-pages', 'velox-smap-products', 'velox-smap-changefreq', 'velox-smap-priority' ].forEach( function ( id ) {
				var elc = document.getElementById( id );
				if ( ! elc ) { return; }
				elc.addEventListener( 'change', function () {
					var key = elc.getAttribute( 'data-setting' );
					var val = 'checkbox' === elc.type ? ( elc.checked ? 1 : 0 ) : elc.value;
					var p = {}; p[ key ] = val;
					saveSettings( p, 'Sitemap settings saved.' ).then( loadSmapEntries );
				} );
				if ( 'checkbox' !== elc.type ) { elc.addEventListener( 'input', function () { updateSmapView(); } ); }
			} );

			// ---- Sitemap appearance (style picker + custom + styled preview) ----
			var smapStyled = document.getElementById( 'velox-smap-styled' );
			var smapWrap = smapPreview ? smapPreview.parentNode : null;
			var currentStyle = 'none';
			var activeCard = document.querySelector( '.velox-smap-style.is-active' );
			if ( activeCard ) { currentStyle = activeCard.getAttribute( 'data-style' ); }

			function smapIsDark( hex ) {
				hex = ( hex || '' ).replace( '#', '' );
				if ( hex.length === 3 ) { hex = hex[0] + hex[0] + hex[1] + hex[1] + hex[2] + hex[2]; }
				if ( hex.length < 6 ) { return false; }
				var r = parseInt( hex.substr( 0, 2 ), 16 ), g = parseInt( hex.substr( 2, 2 ), 16 ), b = parseInt( hex.substr( 4, 2 ), 16 );
				return ( 0.299 * r + 0.587 * g + 0.114 * b ) < 128;
			}
			function styledPalette( style ) {
				var accent = ( document.getElementById( 'velox-smap-accent' ) || {} ).value || '#2ab7f1';
				if ( 'dark' === style ) { return { layout: 'table', mono: true, bg: '#0d1117', fg: '#e6edf3', muted: '#7d8590', border: 'rgba(255,255,255,.08)', thbg: '#161b22', link: '#58a6ff', bar: 'none', card: 'rgba(255,255,255,.04)' }; }
				if ( 'minimal' === style ) { return { layout: 'list', bg: '#fff', fg: '#111', muted: '#9a9a9e', border: '#f0f0f0', thbg: '', link: '#111', bar: 'none', card: '#fff' }; }
				if ( 'cards' === style ) { return { layout: 'cards', bg: '#f6f8fa', fg: '#1d1d1f', muted: '#6e6e73', border: '#e6e8eb', thbg: '#eef1f4', link: '#0f7ab5', bar: accent, card: '#fff' }; }
				if ( 'custom' === style ) {
					var bg = ( document.getElementById( 'velox-smap-bg' ) || {} ).value || '#ffffff';
					var fg = ( document.getElementById( 'velox-smap-fg' ) || {} ).value || '#1d1d1f';
					var lay = ( document.getElementById( 'velox-smap-layout' ) || {} ).value || 'table';
					var dark = smapIsDark( bg );
					return { layout: lay, bg: bg, fg: fg, muted: dark ? 'rgba(255,255,255,.55)' : 'rgba(0,0,0,.5)', border: dark ? 'rgba(255,255,255,.12)' : 'rgba(0,0,0,.08)', thbg: dark ? 'rgba(255,255,255,.05)' : 'rgba(0,0,0,.03)', link: accent, bar: accent, card: dark ? 'rgba(255,255,255,.04)' : '#fff' };
				}
				return { layout: 'table', bg: '#fff', fg: '#1d1d1f', muted: '#6e6e73', border: '#eee', thbg: '#f5f7f9', link: '#0f7ab5', bar: accent, card: '#fff' };
			}
			function renderStyledPreview( style ) {
				if ( ! smapStyled ) { return; }
				var p = styledPalette( style );
				var heading = ( document.getElementById( 'velox-smap-heading' ) || {} ).value || 'XML Sitemap';
				var list = smapList();
				var total = ( null !== smapEntries ) ? smapTotal : list.length;
				var font = p.mono ? 'ui-monospace,SFMono-Regular,Menlo,Consolas,monospace' : '-apple-system,Segoe UI,Roboto,sans-serif';
				function tag( t ) { return t ? '<span style="font-size:11px;color:' + p.muted + ';background:' + p.thbg + ';border:1px solid ' + p.border + ';border-radius:999px;padding:2px 9px;">' + escapeHtml( t ) + '</span>' : ''; }
				var logoOn = ( ( document.getElementById( 'velox-smap-logo' ) || {} ).checked );
				var brand = '';
				if ( logoOn ) {
					var lUrl = smapStyled.getAttribute( 'data-logo-url' ) || '';
					var bName = smapStyled.getAttribute( 'data-brand-name' ) || '';
					brand = lUrl
						? '<img src="' + escapeHtml( lUrl ) + '" alt="" style="height:28px;width:auto;margin-bottom:8px;display:block;"/>'
						: ( bName ? '<div style="font-size:12px;font-weight:600;color:' + p.muted + ';margin-bottom:5px;letter-spacing:.02em;">' + escapeHtml( bName ) + '</div>' : '' );
				}
				var head = '<div style="padding:' + ( p.layout === 'list' ? '22px 16px 14px' : '18px 16px 16px' ) + ';' + ( p.bar === 'none' ? '' : 'border-bottom:3px solid ' + p.bar + ';' ) + '">' + brand +
					'<div style="font-size:' + ( p.layout === 'list' ? '15px' : '18px' ) + ';font-weight:600;">' + escapeHtml( heading ) + '</div>' +
					'<div style="font-size:12px;color:' + p.muted + ';margin-top:3px;">Generated by Velox &#183; ' + total + ' URLs</div></div>';
				var bodyHtml;
				if ( ! list.length ) {
					bodyHtml = '<div style="padding:16px;color:' + p.muted + ';">No URLs — turn on a section above.</div>';
				} else if ( p.layout === 'cards' ) {
					bodyHtml = '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px;padding:16px;">' + list.map( function ( e ) {
						return '<div style="background:' + p.card + ';border:1px solid ' + p.border + ';border-radius:12px;padding:14px;">' +
							'<a href="' + escapeHtml( e.loc ) + '" style="color:' + p.link + ';text-decoration:none;font-size:13px;font-weight:500;word-break:break-all;display:block;margin-bottom:10px;">' + escapeHtml( e.loc ) + '</a>' +
							'<div style="display:flex;gap:5px;flex-wrap:wrap;">' + tag( e.priority ? 'P ' + e.priority : '' ) + tag( e.changefreq ) + tag( ( e.lastmod || '' ).slice( 0, 10 ) ) + '</div></div>';
					} ).join( '' ) + '</div>';
				} else if ( p.layout === 'list' ) {
					bodyHtml = '<div style="padding:0 16px;">' + list.map( function ( e ) {
						var meta = [];
						if ( e.priority ) { meta.push( 'Priority ' + e.priority ); }
						if ( e.changefreq ) { meta.push( e.changefreq ); }
						if ( e.lastmod ) { meta.push( ( e.lastmod || '' ).slice( 0, 10 ) ); }
						return '<div style="padding:14px 2px;border-bottom:1px solid ' + p.border + ';">' +
							'<a href="' + escapeHtml( e.loc ) + '" style="color:' + p.link + ';text-decoration:none;font-size:14px;word-break:break-all;">' + escapeHtml( e.loc ) + '</a>' +
							'<div style="color:' + p.muted + ';font-size:12px;margin-top:5px;display:flex;gap:14px;flex-wrap:wrap;">' + meta.map( function ( m ) { return '<span>' + escapeHtml( m ) + '</span>'; } ).join( '' ) + '</div></div>';
					} ).join( '' ) + '</div>';
				} else {
					var rows = list.map( function ( e ) {
						var lm = ( e.lastmod || '' ).slice( 0, 10 );
						return '<tr><td style="padding:9px 14px;border-top:1px solid ' + p.border + ';word-break:break-all;"><a href="' + escapeHtml( e.loc ) + '" style="color:' + p.link + ';text-decoration:none;">' + escapeHtml( e.loc ) + '</a></td>' +
							'<td style="padding:9px 12px;border-top:1px solid ' + p.border + ';color:' + p.muted + ';">' + escapeHtml( e.priority || '' ) + '</td>' +
							'<td style="padding:9px 12px;border-top:1px solid ' + p.border + ';color:' + p.muted + ';">' + escapeHtml( e.changefreq || '' ) + '</td>' +
							'<td style="padding:9px 14px;border-top:1px solid ' + p.border + ';color:' + p.muted + ';white-space:nowrap;">' + escapeHtml( lm ) + '</td></tr>';
					} ).join( '' );
					bodyHtml = '<table style="width:100%;border-collapse:collapse;font-size:12.5px;table-layout:fixed;"><thead><tr style="background:' + p.thbg + ';color:' + p.muted + ';text-align:left;"><th style="padding:9px 14px;">URL</th><th style="padding:9px 12px;width:80px;">Priority</th><th style="padding:9px 12px;width:110px;">Change freq.</th><th style="padding:9px 14px;width:110px;">Last modified</th></tr></thead><tbody>' + rows + '</tbody></table>';
				}
				var moreNote = ( total > list.length ) ? '<div style="padding:10px 16px;font-size:12px;color:' + p.muted + ';">Showing first ' + list.length + ' of ' + total + ' URLs.</div>' : '';
				smapStyled.style.background = p.bg;
				smapStyled.innerHTML = '<div style="font-family:' + font + ';color:' + p.fg + ';">' + head + bodyHtml + moreNote + '</div>';
			}
			function updateSmapView() {
				if ( 'none' === currentStyle ) {
					if ( smapPreview ) { smapPreview.hidden = false; }
					if ( smapStyled ) { smapStyled.hidden = true; }
					if ( smapWrap ) { smapWrap.style.background = '#1d1f21'; }
					buildSmap();
				} else {
					if ( smapPreview ) { smapPreview.hidden = true; }
					if ( smapStyled ) { smapStyled.hidden = false; }
					if ( smapWrap ) { smapWrap.style.background = '#fff'; }
					renderStyledPreview( currentStyle );
				}
			}

			var customBox = document.getElementById( 'velox-smap-custom' );
			document.querySelectorAll( '.velox-smap-style' ).forEach( function ( card ) {
				card.addEventListener( 'click', function () {
					document.querySelectorAll( '.velox-smap-style' ).forEach( function ( c ) { c.classList.remove( 'is-active' ); } );
					card.classList.add( 'is-active' );
					currentStyle = card.getAttribute( 'data-style' );
					if ( customBox ) { customBox.hidden = ( 'custom' !== currentStyle ); }
					saveSettings( { seo_sitemap_style: currentStyle }, 'Sitemap style saved.' )
						.then( function () { api( 'seo_sitemap_generate', {} ).catch( function () {} ); } );
					updateSmapView();
				} );
			} );
			[ 'velox-smap-accent', 'velox-smap-bg', 'velox-smap-fg', 'velox-smap-layout', 'velox-smap-spacing', 'velox-smap-heading', 'velox-smap-logo' ].forEach( function ( id ) {
				var el = document.getElementById( id );
				if ( ! el ) { return; }
				var handler = function () {
					var key = el.getAttribute( 'data-setting' );
					var val = 'checkbox' === el.type ? ( el.checked ? 1 : 0 ) : el.value;
					var p = {}; p[ key ] = val;
					saveSettings( p, 'Sitemap style saved.' ).then( function () { api( 'seo_sitemap_generate', {} ).catch( function () {} ); } );
					updateSmapView();
				};
				el.addEventListener( 'change', handler );
				if ( 'text' === el.type || 'color' === el.type ) { el.addEventListener( 'input', function () { updateSmapView(); } ); }
			} );

			loadSmapEntries();
		}

		var saveBtn = $( '#velox-seo-robots-save' );
		if ( saveBtn ) {
			saveBtn.addEventListener( 'click', function () {
				saveBtn.disabled = true;
				api( 'seo_robots_save', { content: robots.value } )
					.then( function ( r ) { toast( r && r.physical ? 'Saved — but a physical robots.txt still overrides this.' : 'robots.txt saved.', r && r.physical ? 'warn' : undefined ); } )
					.catch( function ( e ) { toast( e.message, 'error' ); } )
					.then( function () { saveBtn.disabled = false; } );
			} );
		}
		var resetBtn = $( '#velox-seo-robots-reset' );
		if ( resetBtn ) {
			resetBtn.addEventListener( 'click', function () {
				resetBtn.disabled = true;
				api( 'seo_robots_reset' )
					.then( function ( r ) { if ( r && r.content && robots ) { robots.value = r.content; } toast( 'Reset to recommended.' ); } )
					.catch( function ( e ) { toast( e.message, 'error' ); } )
					.then( function () { resetBtn.disabled = false; } );
			} );
		}

		// Quick-add snippet chips.
		var robotsHome = ( window.location.origin || '' );
		var robotsSnips = {
			sitemap: 'Sitemap: ' + robotsHome + '/sitemap.xml',
			admin: 'User-agent: *\nDisallow: /wp-admin/\nAllow: /wp-admin/admin-ajax.php',
			ai: '# Block common AI crawlers\nUser-agent: GPTBot\nUser-agent: ChatGPT-User\nUser-agent: CCBot\nUser-agent: ClaudeBot\nUser-agent: anthropic-ai\nUser-agent: Google-Extended\nUser-agent: PerplexityBot\nDisallow: /',
			allow: 'User-agent: *\nDisallow:'
		};
		$$( '[data-robots-snip]' ).forEach( function ( chip ) {
			chip.addEventListener( 'click', function () {
				if ( ! robots ) { return; }
				var block = robotsSnips[ chip.getAttribute( 'data-robots-snip' ) ];
				if ( ! block ) { return; }
				if ( robots.value.indexOf( block.split( '\n' )[0] ) !== -1 && 'sitemap' !== chip.getAttribute( 'data-robots-snip' ) ) {
					toast( 'That block looks like it is already there.' );
					return;
				}
				robots.value = robots.value.replace( /\s*$/, '' ) + '\n\n' + block + '\n';
				robots.focus();
				robots.scrollTop = robots.scrollHeight;
				toast( 'Added — review and save.' );
			} );
		} );
		var physBtn = $( '#velox-seo-robots-physical' );
		if ( physBtn ) {
			physBtn.addEventListener( 'click', function () {
				physBtn.disabled = true;
				api( 'seo_robots_physical', { content: robots ? robots.value : '' } )
					.then( function ( r ) { toast( ( r && r.physical ) ? 'Written to physical robots.txt — most reliable behind a CDN.' : 'Could not write the file (permissions?).', ( r && r.physical ) ? undefined : 'error' ); if ( r && r.physical ) { setTimeout( function () { location.reload(); }, 700 ); } } )
					.catch( function ( e ) { toast( e.message, 'error' ); } )
					.then( function () { physBtn.disabled = false; } );
			} );
		}
		var virtBtn = $( '#velox-seo-robots-virtual' );
		if ( virtBtn ) {
			virtBtn.addEventListener( 'click', function () {
				virtBtn.disabled = true;
				api( 'seo_robots_virtual' )
					.then( function () { toast( 'Physical file removed — back to the virtual robots.txt.' ); setTimeout( function () { location.reload(); }, 700 ); } )
					.catch( function ( e ) { toast( e.message, 'error' ); } )
					.then( function () { virtBtn.disabled = false; } );
			} );
		}
		var viewBtn = $( '#velox-seo-robots-view' );
		if ( viewBtn ) {
			viewBtn.addEventListener( 'click', function () {
				var url = viewBtn.getAttribute( 'data-url' );
				window.open( url, '_blank', 'noopener' ); // open the real /robots.txt
				var box = $( '#velox-seo-robots-live' ), out = $( '#velox-seo-live-out' ),
					badge = $( '#velox-seo-live-badge' ), cf = $( '#velox-seo-live-cf' );
				viewBtn.disabled = true; box.hidden = false; out.textContent = 'Fetching…'; badge.textContent = ''; cf.hidden = true;
				fetch( url + '?_=' + Date.now(), { credentials: 'omit', cache: 'no-store' } )
					.then( function ( r ) { return r.text(); } )
					.then( function ( txt ) {
						out.textContent = txt || '(empty response)';
						var isCf = /content[-\s]signal|ai-train|ai-input|DIRECTIVE 2019\/790/i.test( txt );
						var hasVelox = /Disallow:\s*\/wp-admin\//i.test( txt );
						cf.hidden = ! isCf;
						badge.innerHTML = isCf
							? '<span class="velox-pill velox-pill--warn">Cloudflare text detected</span>'
							: ( hasVelox ? '<span class="velox-pill velox-pill--ok">Velox robots.txt is live</span>' : '' );
					} )
					.catch( function () { out.textContent = 'Could not fetch /robots.txt from the browser (it may be blocked by the CDN). Open it directly in a new tab instead.'; } )
					.then( function () { viewBtn.disabled = false; } );
			} );
		}
		if ( genBtn ) {
			genBtn.addEventListener( 'click', function () {
				genBtn.disabled = true;
				genBtn.textContent = 'Generating…';
				api( 'seo_sitemap_generate' )
					.then( function ( r ) {
						var c = $( '#velox-seo-smap-count' );
						if ( c && r ) { c.textContent = r.urls; }
						toast( 'Sitemap regenerated — ' + ( ( r && r.urls ) || 0 ) + ' URLs.' );
					} )
					.catch( function ( e ) { toast( e.message, 'error' ); } )
					.then( function () { genBtn.disabled = false; genBtn.textContent = 'Regenerate sitemap'; } );
			} );
		}
		if ( applyBtn ) {
			applyBtn.addEventListener( 'click', function () {
				applyBtn.disabled = true;
				applyBtn.textContent = 'Applying…';
				api( 'seo_apply_recommended' )
					.then( function ( r ) {
						if ( r && r.content && robots ) { robots.value = r.content; }
						var en = $( '#velox-seo-robots-enable' ), se = $( '#velox-seo-sitemap-enable' );
						if ( en ) { en.checked = true; }
						if ( se ) { se.checked = true; }
						toast( ( r && r.physical_robots ) ? 'Applied — heads up: a physical robots.txt still overrides the virtual one.' : 'Recommended SEO setup applied.', ( r && r.physical_robots ) ? 'warn' : undefined );
					} )
					.catch( function ( e ) { toast( e.message, 'error' ); } )
					.then( function () { applyBtn.disabled = false; applyBtn.textContent = 'Apply recommended setup'; } );
			} );
		}

		// ---- .htaccess editor (locked until unlocked; unlock snapshots the file) ----
		var htUnlock = $( '#velox-ht-unlock' );
		var htArea   = $( '#velox-ht-content' );
		var htSave   = $( '#velox-ht-save' );
		var htReset  = $( '#velox-ht-reset' );
		if ( htUnlock && htArea ) {
			function htSetLocked( locked ) {
				htArea.readOnly = locked;
				htArea.classList.toggle( 'is-locked', locked );
				if ( htSave ) { htSave.disabled = locked; }
				if ( htReset ) { htReset.disabled = locked; }
			}
			htSetLocked( true );

			htUnlock.addEventListener( 'change', function () {
				if ( htUnlock.checked ) {
					if ( ! window.confirm( 'Unlock .htaccess for editing? A snapshot of the current file is taken now so you can reset to it.' ) ) {
						htUnlock.checked = false;
						return;
					}
					api( 'seo_htaccess_unlock' )
						.then( function () { htSetLocked( false ); htArea.focus(); toast( 'Editing unlocked — snapshot saved.' ); } )
						.catch( function ( e ) { htUnlock.checked = false; toast( e.message, 'error' ); } );
				} else {
					htSetLocked( true );
				}
			} );

			if ( htSave ) {
				htSave.addEventListener( 'click', function () {
					if ( ! htArea.value.trim() ) { toast( 'Refusing to save an empty .htaccess.', 'error' ); return; }
					if ( ! window.confirm( 'Write this to your live .htaccess now? A wrong rule can 500 your site — you can still Reset to the snapshot afterwards.' ) ) { return; }
					htSave.disabled = true;
					api( 'seo_htaccess_save', { content: htArea.value } )
						.then( function ( r ) {
							if ( ! r.ok ) { toast( r.message || 'Could not save.', 'error' ); return; }
							toast( '.htaccess saved.' );
						} )
						.catch( function ( e ) { toast( e.message, 'error' ); } )
						.then( function () { htSave.disabled = false; } );
				} );
			}

			if ( htReset ) {
				htReset.addEventListener( 'click', function () {
					if ( ! window.confirm( 'Reset .htaccess to the snapshot taken when you unlocked?' ) ) { return; }
					htReset.disabled = true;
					api( 'seo_htaccess_reset' )
						.then( function ( r ) {
							if ( ! r.ok ) { toast( r.message || 'Could not reset.', 'error' ); return; }
							if ( typeof r.content === 'string' ) { htArea.value = r.content; }
							toast( '.htaccess reset to snapshot.' );
						} )
						.catch( function ( e ) { toast( e.message, 'error' ); } )
						.then( function () { htReset.disabled = false; } );
				} );
			}
		}
	}

	function initSidebar() {
		var app = $( '.velox-app' );
		var btn = $( '#velox-side-collapse' );
		if ( ! app || ! btn ) { return; }
		try {
			if ( window.localStorage && localStorage.getItem( 'veloxSidebarCollapsed' ) === '1' ) {
				app.classList.add( 'is-collapsed' );
			}
		} catch ( e ) {}
		btn.addEventListener( 'click', function () {
			var collapsed = app.classList.toggle( 'is-collapsed' );
			try { if ( window.localStorage ) { localStorage.setItem( 'veloxSidebarCollapsed', collapsed ? '1' : '0' ); } } catch ( e ) {}
		} );
	}

	function veloxInit() {
		initSidebar();
		initWizard();
		initUtilities();
		initDashboard();
		initImages();
		initLibrary();
		initMedia();
		initPerformance();
		initDatabase();
		initSeo();
		initSettings();
	}
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', veloxInit );
	} else {
		veloxInit();
	}
} )();

/* =====================================================================
 * Velox custom <select> — replaces native dropdowns across the admin.
 * Progressive enhancement: the native select stays in the DOM (hidden)
 * as the source of truth, so all existing value/change logic keeps working.
 * ===================================================================== */
( function () {
	'use strict';
	function closeAll( except ) {
		document.querySelectorAll( '.vxs.is-open' ).forEach( function ( w ) {
			if ( w === except ) { return; }
			w.classList.remove( 'is-open' );
			var b = w.querySelector( '.vxs-btn' );
			if ( b ) { b.setAttribute( 'aria-expanded', 'false' ); }
		} );
	}
	function enhance( sel ) {
		if ( sel.dataset.vxsDone || sel.multiple || sel.classList.contains( 'vxs-skip' ) ) { return; }
		sel.dataset.vxsDone = '1';
		var wrap = document.createElement( 'div' );
		wrap.className = 'vxs';
		var btn = document.createElement( 'button' );
		btn.type = 'button';
		btn.className = 'vxs-btn';
		btn.setAttribute( 'aria-haspopup', 'listbox' );
		btn.setAttribute( 'aria-expanded', 'false' );
		btn.innerHTML = '<span class="vxs-label"></span><svg class="vxs-chev" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>';
		var menu = document.createElement( 'div' );
		menu.className = 'vxs-menu';
		menu.setAttribute( 'role', 'listbox' );
		sel.parentNode.insertBefore( wrap, sel.nextSibling );
		wrap.appendChild( sel );          // native select lives inside, hidden
		wrap.appendChild( btn );
		wrap.appendChild( menu );
		if ( sel.disabled ) { wrap.classList.add( 'is-disabled' ); }

		function label() {
			var o = sel.options[ sel.selectedIndex ];
			wrap.querySelector( '.vxs-label' ).textContent = o ? o.textContent : '';
			btn.classList.toggle( 'is-placeholder', !! o && '' === o.value );
		}
		function syncOptions() {
			menu.innerHTML = '';
			Array.prototype.forEach.call( sel.options, function ( o, i ) {
				var it = document.createElement( 'div' );
				it.className = 'vxs-opt' + ( o.selected ? ' is-sel' : '' ) + ( o.disabled ? ' is-dis' : '' );
				it.setAttribute( 'role', 'option' );
				it.dataset.i = i;
				it.textContent = o.textContent;
				menu.appendChild( it );
			} );
			label();
		}
		function place() {
			var r = btn.getBoundingClientRect();
			menu.style.left  = r.left + 'px';
			menu.style.width = r.width + 'px';
			var below = window.innerHeight - r.bottom;
			var mh    = Math.min( 260, menu.scrollHeight + 12 );
			if ( below < mh + 8 && r.top > below ) {
				menu.style.top = Math.max( 8, r.top - mh - 5 ) + 'px';
			} else {
				menu.style.top = ( r.bottom + 5 ) + 'px';
			}
		}
		function open() {
			closeAll( wrap );
			wrap.classList.add( 'is-open' );
			btn.setAttribute( 'aria-expanded', 'true' );
			place();
			var s = menu.querySelector( '.is-sel' );
			if ( s ) { s.scrollIntoView( { block: 'nearest' } ); }
		}
		function onWinChange() { if ( wrap.classList.contains( 'is-open' ) ) { place(); } }
		window.addEventListener( 'scroll', onWinChange, true );
		window.addEventListener( 'resize', onWinChange );
		function close() { wrap.classList.remove( 'is-open' ); btn.setAttribute( 'aria-expanded', 'false' ); }
		function choose( i ) {
			if ( i < 0 || i >= sel.options.length || sel.options[ i ].disabled ) { return; }
			sel.selectedIndex = i;
			sel.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		}
		btn.addEventListener( 'click', function ( e ) {
			e.stopPropagation();
			if ( sel.disabled ) { return; }
			wrap.classList.contains( 'is-open' ) ? close() : open();
		} );
		menu.addEventListener( 'click', function ( e ) {
			var it = e.target.closest( '.vxs-opt' );
			if ( ! it || it.classList.contains( 'is-dis' ) ) { return; }
			choose( parseInt( it.dataset.i, 10 ) );
		} );
		btn.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'ArrowDown' || e.key === 'ArrowUp' ) {
				e.preventDefault();
				var n = sel.selectedIndex + ( e.key === 'ArrowDown' ? 1 : -1 );
				while ( n >= 0 && n < sel.options.length && sel.options[ n ].disabled ) { n += ( e.key === 'ArrowDown' ? 1 : -1 ); }
				if ( n >= 0 && n < sel.options.length ) { choose( n ); }
			} else if ( e.key === 'Enter' || e.key === ' ' ) {
				e.preventDefault();
				wrap.classList.contains( 'is-open' ) ? close() : open();
			} else if ( e.key === 'Escape' ) { close(); }
		} );
		// reflect changes (whether from us, native, or other JS)
		sel.addEventListener( 'change', function () {
			menu.querySelectorAll( '.vxs-opt' ).forEach( function ( el ) {
				el.classList.toggle( 'is-sel', parseInt( el.dataset.i, 10 ) === sel.selectedIndex );
			} );
			label();
			close();
		} );
		// re-sync if some other script rewrites the option list
		new MutationObserver( syncOptions ).observe( sel, { childList: true } );
		syncOptions();
	}
	function scan( root ) {
		( root || document ).querySelectorAll( 'select.velox-select:not([data-vxs-done])' ).forEach( enhance );
	}
	function boot() {
		scan();
		new MutationObserver( function ( muts ) {
			muts.forEach( function ( m ) {
				m.addedNodes.forEach( function ( n ) {
					if ( n.nodeType !== 1 ) { return; }
					if ( n.matches && n.matches( 'select.velox-select' ) ) { enhance( n ); }
					if ( n.querySelectorAll ) { scan( n ); }
				} );
			} );
		} ).observe( document.body, { childList: true, subtree: true } );
		document.addEventListener( 'click', function ( e ) { if ( ! e.target.closest( '.vxs' ) ) { closeAll(); } } );
	}
	if ( document.readyState === 'loading' ) { document.addEventListener( 'DOMContentLoaded', boot ); } else { boot(); }
} )();

/* ===== Dashboard: customizable widgets (add / edit / batch-remove) ===== */
( function ( $ ) {
	var cockpit = document.getElementById( 'velox-cockpit' );
	if ( ! cockpit ) { return; }
	var editBtn     = document.getElementById( 'velox-dash-edit' );
	var doneBtn     = document.getElementById( 'velox-dash-done' );
	var newWrap     = document.getElementById( 'velox-newwidget' );
	var newBtn      = document.getElementById( 'velox-newwidget-btn' );
	var newMenu     = document.getElementById( 'velox-newwidget-menu' );
	var batchbar    = document.getElementById( 'velox-batchbar' );
	var batchCnt    = document.getElementById( 'velox-batch-count' );
	var batchWord   = document.getElementById( 'velox-batch-word' );
	var batchCancel = document.getElementById( 'velox-batch-cancel' );
	var batchRemove = document.getElementById( 'velox-batch-remove' );

	function widgets()  { return Array.prototype.slice.call( cockpit.querySelectorAll( '.velox-w' ) ); }
	function hiddenIds(){ return widgets().filter( function ( w ) { return w.classList.contains( 'is-hidden' ); } ).map( function ( w ) { return w.getAttribute( 'data-widget' ); } ); }
	function orderIds() { return widgets().map( function ( w ) { return w.getAttribute( 'data-widget' ); } ); }
	function selected() { return widgets().filter( function ( w ) { return w.classList.contains( 'sel' ); } ); }

	// Per-widget grid span (columns 3-12, rows 1-3) read from the inline CSS vars.
	function clampC( n ) { return Math.max( 3, Math.min( 12, n ) ); }
	function clampR( n ) { return Math.max( 1, Math.min( 3, n ) ); }
	function sizeOf( w ) {
		return {
			c: clampC( parseInt( w.style.getPropertyValue( '--vx-w-cols' ), 10 ) || 4 ),
			r: clampR( parseInt( w.style.getPropertyValue( '--vx-w-rows' ), 10 ) || 1 )
		};
	}
	function sizesMap() {
		var m = {};
		widgets().forEach( function ( w ) { var z = sizeOf( w ); m[ w.getAttribute( 'data-widget' ) ] = { c: z.c, r: z.r }; } );
		return m;
	}

	// Apply the saved order on load (re-append each known widget in saved order;
	// anything not in the list keeps its place at the end). The CSS grid lays out
	// from DOM order, so this is all the "autolayout" needed.
	( function applySavedOrder() {
		var raw = cockpit.getAttribute( 'data-order' ) || '';
		if ( ! raw ) { return; }
		raw.split( ',' ).forEach( function ( id ) {
			id = id.trim();
			if ( ! id ) { return; }
			var w = cockpit.querySelector( '.velox-w[data-widget="' + id + '"]' );
			if ( w ) { cockpit.appendChild( w ); }
		} );
	}() );

	function save() {
		if ( ! window.VELOX || ! VELOX.ajaxurl ) { return; }
		$.post( VELOX.ajaxurl, { action: 'velox', 'do': 'dash_widgets', nonce: VELOX.nonce, hidden: hiddenIds(), order: orderIds(), sizes: sizesMap() } );
	}

	function updateBatch() {
		var n = selected().length;
		batchbar.hidden = ( n === 0 || ! cockpit.classList.contains( 'editing' ) );
		if ( n ) { batchCnt.textContent = n; batchWord.textContent = ( n === 1 ? 'widget' : 'widgets' ); }
	}
	function clearSel() { selected().forEach( function ( w ) { w.classList.remove( 'sel' ); } ); updateBatch(); }

	/* ----- grid-size popover (edit mode) ----- */
	var pop = null, sizingEl = null;
	function closePop() {
		if ( pop && pop.parentNode ) { pop.parentNode.removeChild( pop ); }
		pop = null;
		if ( sizingEl ) { sizingEl.classList.remove( 'is-sizing' ); sizingEl = null; }
	}
	function positionPop( btn ) {
		var r = btn.getBoundingClientRect();
		var left = window.pageXOffset + r.right - 180;
		if ( left < 8 ) { left = 8; }
		pop.style.top  = ( window.pageYOffset + r.bottom + 6 ) + 'px';
		pop.style.left = left + 'px';
	}
	function openPop( w, btn ) {
		closePop();
		sizingEl = w;
		w.classList.add( 'is-sizing' );
		var z = sizeOf( w );
		pop = document.createElement( 'div' );
		pop.className = 'velox-wsize-pop';
		pop.innerHTML =
			'<h4>Grid size</h4>' +
			'<div class="velox-wsize-row"><span>Width</span><span class="velox-wsize-step" data-axis="c">' +
				'<button type="button" data-d="-1" aria-label="Narrower">\u2212</button><b>' + z.c + '</b><button type="button" data-d="1" aria-label="Wider">+</button></span></div>' +
			'<div class="velox-wsize-row"><span>Height</span><span class="velox-wsize-step" data-axis="r">' +
				'<button type="button" data-d="-1" aria-label="Shorter">\u2212</button><b>' + z.r + '</b><button type="button" data-d="1" aria-label="Taller">+</button></span></div>';
		document.body.appendChild( pop );
		positionPop( btn );
		function refresh() {
			var cur = sizeOf( w );
			pop.querySelector( '[data-axis="c"] [data-d="-1"]' ).disabled = cur.c <= 3;
			pop.querySelector( '[data-axis="c"] [data-d="1"]' ).disabled  = cur.c >= 12;
			pop.querySelector( '[data-axis="r"] [data-d="-1"]' ).disabled = cur.r <= 1;
			pop.querySelector( '[data-axis="r"] [data-d="1"]' ).disabled  = cur.r >= 3;
		}
		refresh();
		pop.addEventListener( 'click', function ( e ) {
			e.stopPropagation();
			var b = e.target.closest ? e.target.closest( 'button[data-d]' ) : null;
			if ( ! b || b.disabled ) { return; }
			var step = b.parentNode, axis = step.getAttribute( 'data-axis' ), d = parseInt( b.getAttribute( 'data-d' ), 10 );
			var cur = sizeOf( w );
			if ( axis === 'c' ) { var nc = clampC( cur.c + d ); w.style.setProperty( '--vx-w-cols', nc ); step.querySelector( 'b' ).textContent = nc; }
			else                { var nr = clampR( cur.r + d ); w.style.setProperty( '--vx-w-rows', nr ); step.querySelector( 'b' ).textContent = nr; }
			refresh();
			save();
		} );
	}

	function buildPicker() {
		newMenu.innerHTML = '';
		var hidden = widgets().filter( function ( w ) { return w.classList.contains( 'is-hidden' ); } );
		if ( ! hidden.length ) {
			var e = document.createElement( 'div' );
			e.className = 'velox-newwidget-empty';
			e.textContent = 'All widgets are on the dashboard.';
			newMenu.appendChild( e );
			return;
		}
		hidden.forEach( function ( w ) {
			var b = document.createElement( 'button' );
			b.type = 'button';
			b.className = 'velox-newwidget-item';
			b.textContent = w.getAttribute( 'data-widget-label' ) || w.getAttribute( 'data-widget' );
			b.addEventListener( 'click', function () {
				w.classList.remove( 'is-hidden' );
				newMenu.hidden = true;
				save(); buildPicker();
			} );
			newMenu.appendChild( b );
		} );
	}

	function enterEdit() { cockpit.classList.add( 'editing' ); editBtn.hidden = true; doneBtn.hidden = false; newWrap.hidden = false; setDraggable( true ); buildPicker(); }
	function exitEdit()  { cockpit.classList.remove( 'editing' ); editBtn.hidden = false; doneBtn.hidden = true; newWrap.hidden = true; newMenu.hidden = true; setDraggable( false ); clearSel(); closePop(); }

	/* ----- smooth pointer drag-to-reorder (edit mode only) ----- */
	var drag = null, justDragged = false;
	function setDraggable() { /* pointer-based — nothing to toggle */ }
	function visibleWidgets() { return widgets().filter( function ( w ) { return ! w.classList.contains( 'is-hidden' ); } ); }
	function dragTarget( x, y ) {
		var els = Array.prototype.slice.call( cockpit.querySelectorAll( '.velox-w' ) ).filter( function ( w ) { return ! w.classList.contains( 'is-hidden' ); } );
		var best = null, bestD = Infinity, before = true;
		els.forEach( function ( w ) {
			var r = w.getBoundingClientRect();
			var cx = r.left + r.width / 2, cy = r.top + r.height / 2;
			var d = Math.abs( x - cx ) + Math.abs( y - cy );
			if ( d < bestD ) { bestD = d; best = w; before = ( y < r.top ) || ( y < r.bottom && x < cx ); }
		} );
		return best ? { el: best, before: before } : null;
	}
	// FLIP: animate the widgets sliding to their new slots after a reorder.
	function flip( mutate ) {
		var els = visibleWidgets();
		var first = els.map( function ( w ) { return w.getBoundingClientRect(); } );
		mutate();
		els.forEach( function ( w, i ) {
			var last = w.getBoundingClientRect();
			var dx = first[ i ].left - last.left, dy = first[ i ].top - last.top;
			if ( dx || dy ) {
				w.style.transition = 'none';
				w.style.transform = 'translate(' + dx + 'px,' + dy + 'px)';
				requestAnimationFrame( function () {
					w.style.transition = 'transform 180ms cubic-bezier(.2,.7,.3,1)';
					w.style.transform = '';
				} );
			}
		} );
	}
	cockpit.addEventListener( 'pointerdown', function ( e ) {
		if ( ! cockpit.classList.contains( 'editing' ) || e.button !== 0 ) { return; }
		var w = e.target.closest ? e.target.closest( '.velox-w' ) : null;
		if ( ! w || w.classList.contains( 'is-hidden' ) ) { return; }
		if ( e.target.closest( 'a, button, .velox-w-x, .velox-w-act' ) ) { return; }
		var r = w.getBoundingClientRect();
		drag = { el: w, sx: e.clientX, sy: e.clientY, ox: e.clientX - r.left, oy: e.clientY - r.top, w: r.width, h: r.height, moved: false, ph: null };
	} );
	document.addEventListener( 'pointermove', function ( e ) {
		if ( ! drag ) { return; }
		if ( ! drag.moved ) {
			if ( Math.abs( e.clientX - drag.sx ) + Math.abs( e.clientY - drag.sy ) < 5 ) { return; }
			drag.moved = true;
			closePop();
			var z = sizeOf( drag.el );
			var ph = document.createElement( 'div' );
			ph.className = 'velox-w-ph';
			ph.style.cssText = 'grid-column:span ' + z.c + ';grid-row:span ' + z.r + ';min-width:0;';
			drag.ph = ph;
			drag.el.parentNode.insertBefore( ph, drag.el );
			drag.el.classList.add( 'is-dragging' );
			drag.el.style.cssText += ';position:fixed;margin:0;width:' + drag.w + 'px;height:' + drag.h + 'px;z-index:9999;pointer-events:none;';
			document.body.appendChild( drag.el );
		}
		e.preventDefault();
		drag.el.style.left = ( e.clientX - drag.ox ) + 'px';
		drag.el.style.top  = ( e.clientY - drag.oy ) + 'px';
		var t = dragTarget( e.clientX, e.clientY );
		if ( t && t.el !== drag.ph ) {
			flip( function () {
				if ( t.before ) { cockpit.insertBefore( drag.ph, t.el ); }
				else { cockpit.insertBefore( drag.ph, t.el.nextSibling ); }
			} );
		}
	} );
	document.addEventListener( 'pointerup', function () {
		if ( ! drag ) { return; }
		var d = drag; drag = null;
		if ( ! d.moved ) { return; }
		d.el.classList.remove( 'is-dragging' );
		d.el.style.cssText = '';
		if ( d.ph && d.ph.parentNode ) { cockpit.insertBefore( d.el, d.ph ); d.ph.remove(); }
		justDragged = true;
		setTimeout( function () { justDragged = false; }, 0 );
		save();
	} );

	editBtn.addEventListener( 'click', enterEdit );
	doneBtn.addEventListener( 'click', exitEdit );
	newBtn.addEventListener( 'click', function ( e ) { e.stopPropagation(); newMenu.hidden = ! newMenu.hidden; if ( ! newMenu.hidden ) { buildPicker(); } } );
	document.addEventListener( 'click', function ( e ) { if ( newWrap && ! newWrap.contains( e.target ) ) { newMenu.hidden = true; } } );

	batchCancel.addEventListener( 'click', clearSel );
	batchRemove.addEventListener( 'click', function () {
		selected().forEach( function ( w ) { w.classList.add( 'is-hidden' ); w.classList.remove( 'sel' ); } );
		save(); buildPicker(); updateBatch();
	} );

	cockpit.addEventListener( 'click', function ( e ) {
		if ( ! cockpit.classList.contains( 'editing' ) || justDragged ) { return; }
		var w = e.target.closest ? e.target.closest( '.velox-w' ) : null;
		if ( ! w ) { return; }
		if ( e.target.closest( '.velox-w-size' ) ) {
			e.preventDefault(); e.stopPropagation();
			var szbtn = e.target.closest( '.velox-w-size' );
			if ( sizingEl === w ) { closePop(); } else { openPop( w, szbtn ); }
			return;
		}
		if ( e.target.closest( '.velox-w-x' ) ) {
			e.preventDefault();
			w.classList.add( 'is-hidden' ); w.classList.remove( 'sel' );
			if ( sizingEl === w ) { closePop(); }
			save(); buildPicker(); updateBatch();
			return;
		}
		if ( e.target.closest( 'a, .velox-w-act' ) ) { return; }
		w.classList.toggle( 'sel' );
		updateBatch();
	} );

	document.addEventListener( 'click', function ( e ) {
		if ( pop && ! pop.contains( e.target ) && ! ( e.target.closest && e.target.closest( '.velox-w-size' ) ) ) { closePop(); }
	} );
	window.addEventListener( 'resize', function () { closePop(); } );
}( jQuery ) );

/* ===== Dashboard: PageSpeed device switch + details toggle ===== */
( function () {
	function bindSwitch() {
		// Mobile / desktop segmented switch — swaps the visible panel client-side.
		// Works on the dashboard widget (.velox-w) and the full report (.velox-psf).
		Array.prototype.forEach.call( document.querySelectorAll( '[data-ps-view]' ), function ( seg ) {
			seg.addEventListener( 'click', function ( e ) {
				e.stopPropagation();
				var view = seg.getAttribute( 'data-ps-view' );
				var scope = seg.closest ? seg.closest( '.velox-w, .velox-psf' ) : null;
				if ( ! scope ) { return; }
				Array.prototype.forEach.call( scope.querySelectorAll( '[data-ps-view]' ), function ( b ) {
					var on = b === seg;
					b.classList.toggle( 'is-active', on );
					b.setAttribute( 'aria-selected', on ? 'true' : 'false' );
				} );
				Array.prototype.forEach.call( scope.querySelectorAll( '[data-ps-panel]' ), function ( p ) {
					var on = p.getAttribute( 'data-ps-panel' ) === view;
					p.classList.toggle( 'is-active', on );
					p.hidden = ! on;
				} );
			} );
		} );
		// "See what's wrong & right" — expand/collapse the details for this panel.
		Array.prototype.forEach.call( document.querySelectorAll( '[data-ps-details]' ), function ( btn ) {
			btn.addEventListener( 'click', function ( e ) {
				e.stopPropagation();
				var panel = btn.closest ? btn.closest( '.velox-ps-panel' ) : null;
				var body = panel ? panel.querySelector( '[data-ps-detailsbody]' ) : null;
				if ( ! body ) { return; }
				var open = body.hasAttribute( 'hidden' );
				body.hidden = ! open;
				btn.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
				var tx = btn.querySelector( '.velox-ps-detailsbtn-tx' );
				if ( tx ) { tx.lastChild.textContent = open ? 'Hide details' : 'See what\u2019s wrong & right'; }
			} );
		} );
		// Full report — per-audit accordion rows.
		Array.prototype.forEach.call( document.querySelectorAll( '[data-psf-acc]' ), function ( head ) {
			head.addEventListener( 'click', function () {
				var acc = head.closest ? head.closest( '.velox-psi-row, .velox-psf-acc' ) : null;
				var body = acc ? acc.querySelector( '[data-psf-body]' ) : null;
				if ( ! body ) { return; }
				var open = body.hasAttribute( 'hidden' );
				body.hidden = ! open;
				head.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
			} );
		} );
		// Full report — "Passed audits" disclosure per category.
		Array.prototype.forEach.call( document.querySelectorAll( '[data-psf-passtoggle]' ), function ( btn ) {
			btn.addEventListener( 'click', function () {
				var sec = btn.closest ? btn.closest( '.velox-psi-cat, .velox-psi-section, .velox-psf-sec' ) : null;
				var body = sec ? sec.querySelector( '[data-psf-passbody]' ) : null;
				if ( ! body ) { return; }
				var open = body.hasAttribute( 'hidden' );
				body.hidden = ! open;
				btn.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
			} );
		} );
	}
	if ( document.readyState === 'loading' ) { document.addEventListener( 'DOMContentLoaded', bindSwitch ); } else { bindSwitch(); }
}() );

/* ===== Dashboard: PageSpeed refresh ===== */
( function () {
	if ( typeof VELOX === 'undefined' ) { return; }
	function bind() {
		var btns = document.querySelectorAll( '[data-ps-refresh]' );
		if ( ! btns.length ) { return; }
		Array.prototype.forEach.call( btns, function ( btn ) {
			btn.addEventListener( 'click', function () {
				var w = btn.closest ? btn.closest( '.velox-w' ) : null;
				var orig = btn.textContent;
				btn.textContent = 'Checking\u2026 (~60s)';
				btn.disabled = true;
				if ( w ) { w.classList.add( 'velox-ps-refreshing' ); }
				var body = 'action=velox&do=ps_refresh&nonce=' + encodeURIComponent( VELOX.nonce );
				fetch( VELOX.ajaxurl, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body } )
					.then( function ( r ) { return r.json(); } )
					.then( function ( j ) {
						if ( j && j.success ) { location.reload(); return; }
						btn.textContent = orig; btn.disabled = false;
						if ( w ) { w.classList.remove( 'velox-ps-refreshing' ); }
						if ( typeof toast === 'function' ) { toast( ( j && j.data && j.data.message ) || 'PageSpeed check failed.', 'error' ); }
					} )
					.catch( function () {
						btn.textContent = orig; btn.disabled = false;
						if ( w ) { w.classList.remove( 'velox-ps-refreshing' ); }
						if ( typeof toast === 'function' ) { toast( 'PageSpeed check failed.', 'error' ); }
					} );
			} );
		} );
	}
	if ( document.readyState === 'loading' ) { document.addEventListener( 'DOMContentLoaded', bind ); } else { bind(); }
}() );
