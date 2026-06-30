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

					function next() {
						if ( stopFlag || ! ids.length ) {
							finish( done, saved, stopFlag ? 'Stopped.' : null );
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
							.catch( function () {
								/* skip a bad image, keep going */
							} )
							.then( function () {
								var pct = Math.round( ( done / total ) * 100 );
								if ( bar ) {
									bar.style.width = pct + '%';
								}
								if ( text ) {
									text.textContent = done + ' / ' + total + ' · ' + bytes( saved ) + ' saved';
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

		function finish( done, saved, customMsg ) {
			resetBulkButtons();
			if ( summary ) {
				summary.textContent =
					customMsg || 'Done — ' + done + ' images, ' + bytes( saved ) + ' saved.';
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
						'">' +
						'<div class="velox-media-thumb">' +
						'<img src="' +
						escapeHtml( it.thumb || it.full ) +
						'" alt="" loading="lazy">' +
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
			if ( saveBtn ) {
				var card = saveBtn.closest( '.velox-media-card' );
				saveCard( card, saveBtn );
			} else if ( renameBtn ) {
				var card2 = renameBtn.closest( '.velox-media-card' );
				openRename( card2.getAttribute( 'data-id' ), renameBtn.getAttribute( 'data-name' ) );
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
		var btn = $( '#velox-settings-save' );
		if ( ! btn ) {
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

		btn.addEventListener( 'click', function () {
			btn.disabled = true;
			btn.textContent = 'Saving…';
			saveSettings(
				collectSettings( document.querySelector( '.velox-main' ) || document ),
				'Settings saved.'
			).then( function () {
				btn.disabled = false;
				btn.textContent = 'Save settings';
			} );
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
		}
		function closeLightbox() {
			if ( lightbox ) {
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
							'<span class="velox-lib-badge">' + escapeHtml( typeLabel( it.mime ) ) + '</span>' +
							( it.webp ? '<span class="velox-lib-badge velox-lib-badge--webp">WebP</span>' : '' ) +
						'</div>' +
					'</div>' +
					'<div class="velox-lib-body">' +
						'<div class="velox-lib-meta"><span>' + dims + '</span><span>' + bytes( it.bytes ) + '</span></div>' +
						savingHtml( it ) +
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
					return;
				}
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
			box.addEventListener( 'change', function () {
				var key = box.getAttribute( 'data-key' );
				var on  = box.checked;
				box.disabled = true;
				api( 'util_toggle', { key: key, on: on ? '1' : 'false' } )
					.then( function () {
						toast( on ? 'Turned on — added to the sidebar.' : 'Turned off.' );
						// Re-render the sidebar (and any Open buttons) with the new state.
						setTimeout( function () { location.reload(); }, 450 );
					} )
					.catch( function ( e ) { box.checked = ! on; box.disabled = false; toast( e.message, 'error' ); } );
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
		function set( id, val ) { var el = $( '#' + id ); if ( el ) { el.value = val == null ? '' : val; } }
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

	function initMail() {
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
		$$( '.velox-mail-sub-del' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				var sub = btn.closest( '.vmail-entry' ) || btn.closest( '.velox-mail-sub' );
				var id  = btn.getAttribute( 'data-id' ) || ( sub && sub.getAttribute( 'data-id' ) );
				if ( ! window.confirm( 'Delete this entry permanently?' ) ) { return; }
				api( 'submission_delete', { id: id } )
					.then( function () { if ( sub ) { sub.remove(); } toast( 'Removed.' ); } )
					.catch( function ( er ) { toast( er.message, 'error' ); } );
			} );
		} );
		initMailSmtp();
		initMailInbox();
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
	function initMailInbox() {
		var list   = $( '#vmail-inbox-list' );
		var detail = $( '#vmail-inbox-detail' );
		if ( ! list || ! detail ) { return; }

		function deleteSubmission( id, itemEl ) {
			if ( ! window.confirm( 'Delete this submission permanently?' ) ) { return; }
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
					toast( 'Submission deleted.' );
				} )
				.catch( function ( e ) { toast( e.message, 'error' ); } );
		}

		function renderDetail( sub ) {
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
			detail.innerHTML =
				'<div class="vmail-d-head">' +
					'<div><div class="vmail-d-who">' + escapeHtml( sub.who || 'Submission' ) + '</div>' +
					'<div class="vmail-d-meta">' + meta.join( '  ·  ' ) + '</div></div>' +
				'</div>' +
				'<dl class="vmail-d-dl">' + rows + '</dl>';
		}

		function load( id, itemEl ) {
			list.querySelectorAll( '.vmail-inbox-item' ).forEach( function ( el ) {
				el.classList.toggle( 'is-active', el === itemEl );
				el.setAttribute( 'aria-selected', el === itemEl ? 'true' : 'false' );
			} );
			detail.innerHTML = '<div class="vmail-inbox-empty-detail">Loading…</div>';
			api( 'submission_get', { id: id } )
				.then( renderDetail )
				.catch( function ( e ) { detail.innerHTML = '<div class="vmail-inbox-empty-detail">' + escapeHtml( e.message ) + '</div>'; } );
		}

		list.addEventListener( 'click', function ( e ) {
			var item = e.target.closest( '.vmail-inbox-item' );
			if ( ! item ) { return; }
			// Per-row trash takes priority over opening.
			if ( e.target.closest( '.vmail-inbox-del' ) ) {
				e.stopPropagation();
				deleteSubmission( item.getAttribute( 'data-id' ), item );
				return;
			}
			load( item.getAttribute( 'data-id' ), item );
		} );

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

		function connCard( c, idx ) {
			var card = document.createElement( 'div' );
			card.className = 'vmail-conn';
			card.setAttribute( 'data-id', c.id );
			var secOpts = Object.keys( SECURE ).map( function ( v ) {
				return '<option value="' + v + '"' + ( c.secure === v ? ' selected' : '' ) + '>' + SECURE[ v ] + '</option>';
			} ).join( '' );
			card.innerHTML =
				'<div class="vmail-conn-top">' +
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
				'</div>';
			card.querySelector( '.vmail-conn-del' ).addEventListener( 'click', function () {
				collect();
				conns = conns.filter( function ( x ) { return x.id !== c.id; } );
				render();
			} );
			// keep label in sync into the routing/select dropdowns live
			card.querySelector( '.vmail-c-label' ).addEventListener( 'input', function () { collect(); syncSelects(); } );
			card.querySelector( '.vmail-c-host' ).addEventListener( 'input', function () { collect(); syncSelects(); } );
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
					from_name: card.querySelector( '.vmail-c-fromname' ).value
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
			conns.push( { id: uid(), label: '', host: '', port: 587, secure: 'tls', user: '', pass: '', from: '', from_name: '' } );
			render();
		} );

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
			consent:     { label: 'Consent',     icon: svgIcon('<path d="M9 12l2 2 4-4"/><path d="M21 11.5V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>'), opts: false, cat: 'advanced' },
			captcha:     { label: 'CAPTCHA',     icon: svgIcon('<path d="M12 3l8 4v5c0 5-3.5 8-8 9c-4.5-1-8-4-8-9V7z"/><path d="M9 12l2 2 4-4"/>'), opts: false, cat: 'advanced' },
			calc:        { label: 'Calculation', short: 'Calc', icon: svgIcon('<rect x="4" y="3" width="16" height="18" rx="2.5"/><path d="M8 7h8M8 12h2M8 16h2M14 12h2M14 16h2"/>'), opts: false, cat: 'advanced' },
			step:        { label: 'Page break',  icon: svgIcon('<path d="M4 7h16M4 17h16M9 11l3 3 3-3"/>'), opts: false, cat: 'layout' },
			html:        { label: 'Custom HTML', short: 'HTML',  icon: svgIcon('<path d="M9 8l-4 4 4 4M15 8l4 4-4 4"/>'), opts: false, cat: 'layout' }
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
				min: f.min != null ? f.min : '', max: f.max != null ? f.max : '',
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
			return '<div class="vse-pf-header"><h3>' + escapeHtml( form.title || 'Form' ) + '</h3></div>' +
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
			if ( o.bg ) { d += 'background:' + o.bg + ';'; }
			if ( o.color ) { d += 'color:' + o.color + ';'; }
			if ( o.fs ) { d += 'font-size:' + pfPx( o.fs ) + ';'; }
			if ( o.fw ) { d += 'font-weight:' + o.fw + ';'; }
			if ( o.radius ) { d += 'border-radius:' + pfPx( o.radius ) + ';'; }
			if ( o.border ) { d += 'border-width:' + pfPx( o.border ) + ';border-style:solid;'; }
			if ( o.borderColor ) { d += 'border-color:' + o.borderColor + ';'; }
			if ( o.shadow ) { d += 'box-shadow:' + pfShadow( o.shadow ) + ';'; }
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
			css += pfRule( scope, { bg: f.bg, radius: f.radius, shadow: f.shadow, pt: f.pt, pr: f.pr, pb: f.pb, pl: f.pl, border: f.border, borderColor: f.borderColor } );
			css += pfRule( scope + ' h3', { color: h.color, fs: h.fs, fw: h.fw } );
			css += pfRule( scope + ' .vse-pf-label', { color: l.color, fs: l.fs, fw: l.fw } );
			css += pfRule( scope + ' .vse-pf-input', { bg: inp.bg, color: inp.color, fs: inp.fs, radius: inp.radius, border: inp.border, borderColor: inp.borderColor } );
			var wrapJust = { left: 'flex-start', center: 'center', right: 'flex-end', full: 'stretch' }[ sub.align || 'center' ];
			css += scope + ' .vse-pf-submit-wrap{justify-content:' + wrapJust + ';}';
			if ( sub.align === 'full' ) { css += scope + ' .vse-pf-submit{width:100%;}'; }
			css += pfRule( scope + ' .vse-pf-submit', {
				bg: sub.bg, color: sub.color, fs: sub.fs, fw: sub.fw, radius: sub.radius, shadow: sub.shadow,
				pt: sub.pt, pr: sub.pr, pb: sub.pb, pl: sub.pl, mt: sub.mt, mb: sub.mb,
				border: sub.border, borderColor: sub.borderColor
			} );
			if ( sub.hoverBg ) { css += scope + ' .vse-pf-submit:hover{background:' + sub.hoverBg + ';}'; }
			// per-field overrides
			Object.keys( S ).forEach( function ( t ) {
				if ( t.indexOf( 'field:' ) !== 0 ) { return; }
				var key = t.slice( 6 ), o = S[ t ] || {};
				var fs = scope + ' .vse-pf-field[data-fkey="' + key + '"]';
				css += pfRule( fs + ' .vse-pf-label', { color: o.labelColor, fs: o.labelFs, fw: o.labelFw } );
				css += pfRule( fs + ' .vse-pf-input', { bg: o.bg, color: o.color, fs: o.fs, radius: o.radius, border: o.border, borderColor: o.borderColor } );
			} );
			return css;
		}

		/* ---------- palette ---------- */
		var palOpen = { general: true, advanced: false, layout: false };
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
				rows += inspText( 'Label', 'label', f.label );
				rows += inspText( 'Field key', 'key', f.key, 'Merge tag: {inputs.' + escapeHtml( f.key ) + '}' );
				rows += '<label class="vmail-insp-check"><input type="checkbox" data-k="required"' + ( f.required ? ' checked' : '' ) + '> Required field</label>';
				if ( noPlaceholder.indexOf( t ) === -1 ) { rows += inspText( 'Placeholder', 'placeholder', f.placeholder ); }
				if ( t === 'name' ) { rows += inspArea( 'Sub-labels (first line = first name, second = last name)', 'options', f.options ); }
				else if ( hasOpts ) { rows += inspArea( 'Options (one per line)', 'options', f.options ); }
				if ( noDefault.indexOf( t ) === -1 ) { rows += inspText( 'Default value', 'default', f['default'] ); }
				rows += inspText( 'Help text', 'help', f.help );
				rows += validationRows( f, t );
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
						f._lockKey = true; f.key = slugify( el.value ); renderCanvas(); return;
					}
					f[ k ] = ( el.type === 'checkbox' ) ? el.checked : el.value;
					if ( k === 'label' && ! f._lockKey ) {
						f.key = ''; reKey();
						var ke = $( '[data-k="key"]', inspector ); if ( ke ) { ke.value = f.key; }
					}
					renderCanvas();
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
		}
		function save() {
			reKey();
			var btn = $( '#vmail-save' ); btn.disabled = true;
			api( 'form_save', { form: JSON.stringify( form ) } )
				.then( function () { toast( 'Form saved.' ); setTimeout( function () { location.href = meta.base || ( location.pathname + '?page=velox-utilities&tool=mail' ); }, 500 ); } )
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
					'<div class="vmp-top">' +
						'<span class="vmp-ttl"><span class="vmp-dot"></span> Live preview</span>' +
						'<span class="vmp-note">Exactly what visitors see \u2014 type in it, nothing is submitted.</span>' +
						'<span class="vmp-sp"></span>' +
						'<div class="vmp-dev" id="vmp-dev"><button class="is-on" type="button" data-d="desktop">Desktop</button><button type="button" data-d="mobile">Mobile</button></div>' +
						'<button class="velox-btn velox-btn--ghost" id="vmp-to-style" type="button"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px;"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>Edit styles</button>' +
						'<button class="velox-btn velox-btn--ghost" id="vmp-close" type="button">Close</button>' +
					'</div>' +
					'<div class="vmp-stage"><div class="vmp-frame" id="vmp-frame"><div class="vse-pf" id="vmp-form"></div></div></div>' +
					'<style id="vmp-css"></style>';
				document.body.appendChild( overlay );
				$( '#vmp-close', overlay ).addEventListener( 'click', close );
				$( '#vmp-to-style', overlay ).addEventListener( 'click', function () { close(); if ( openStyleEditor ) { openStyleEditor(); } } );
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
			function close() { if ( overlay ) { overlay.hidden = true; document.body.style.overflow = ''; } }
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
				form: '<rect x="3" y="3" width="18" height="18" rx="3"/>',
				header: '<path d="M4 5h16v5H4z"/>',
				labels: '<path d="M4 7h16M4 12h16M4 17h10"/>',
				inputs: '<rect x="3" y="8" width="18" height="8" rx="2"/>',
				submit: '<rect x="3" y="8" width="18" height="9" rx="4"/>'
			};
			var current = 'submit';

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
			function rule( sel, o ) {
				var d = '';
				if ( o.bg ) { d += 'background:' + o.bg + ';'; }
				if ( o.color ) { d += 'color:' + o.color + ';'; }
				if ( o.fs ) { d += 'font-size:' + px( o.fs ) + ';'; }
				if ( o.fw ) { d += 'font-weight:' + o.fw + ';'; }
				if ( o.radius ) { d += 'border-radius:' + px( o.radius ) + ';'; }
				if ( o.border ) { d += 'border-width:' + px( o.border ) + ';border-style:solid;'; }
				if ( o.borderColor ) { d += 'border-color:' + o.borderColor + ';'; }
				if ( o.shadow ) { d += 'box-shadow:' + shadowVal( o.shadow ) + ';'; }
				if ( o.pt != null || o.pr != null || o.pb != null || o.pl != null ) {
					d += 'padding:' + px( o.pt || 0 ) + ' ' + px( o.pr || 0 ) + ' ' + px( o.pb || 0 ) + ' ' + px( o.pl || 0 ) + ';';
				}
				if ( o.mt != null || o.mr != null || o.mb != null || o.ml != null ) {
					d += 'margin:' + px( o.mt || 0 ) + ' ' + px( o.mr || 0 ) + ' ' + px( o.mb || 0 ) + ' ' + px( o.ml || 0 ) + ';';
				}
				return d ? ( sel + '{' + d + '}' ) : '';
			}
			var SCOPE = '#vse-form';
			function liveCss() { return formPreviewCss( SCOPE ); }
			function applyLive() {
				var styleTag = $( '#vse-live-css' ); if ( styleTag ) { styleTag.textContent = liveCss(); }
			}

			// ---- control builders ----
			function ctrlText( label, target, key, val ) {
				return '<div class="vse-ctrl"><div class="vse-cl">' + label + '</div><input class="vse-in" data-t="' + target + '" data-k="' + key + '" value="' + escapeHtml( val || '' ) + '"></div>';
			}
			function ctrlUnit( label, target, key, val ) {
				return '<div class="vse-ctrl" style="margin:0;"><div class="vse-cl">' + label + '</div><div class="vse-in-unit"><input data-t="' + target + '" data-k="' + key + '" value="' + escapeHtml( val == null ? '' : val ) + '"><span class="u">px</span></div></div>';
			}
			function ctrlColor( label, target, key, val ) {
				var v = val || '';
				return '<div class="vse-ctrl"><div class="vse-cl">' + label + '</div><div class="vse-color">' +
					'<input type="color" class="vse-swatch" data-t="' + target + '" data-k="' + key + '" data-color="1" value="' + ( /^#([0-9a-f]{6})$/i.test( v ) ? v : '#2ab7f1' ) + '">' +
					'<input class="vse-hex" data-t="' + target + '" data-k="' + key + '" value="' + escapeHtml( v ) + '" placeholder="inherit"></div></div>';
			}
			function ctrlSeg( label, target, key, val, opts ) {
				var btns = opts.map( function ( o ) {
					return '<button type="button" data-t="' + target + '" data-k="' + key + '" data-v="' + o.v + '"' + ( ( val || opts[0].v ) === o.v ? ' class="is-on"' : '' ) + '>' + o.l + '</button>';
				} ).join( '' );
				return '<div class="vse-ctrl"><div class="vse-cl">' + label + '</div><div class="vse-seg">' + btns + '</div></div>';
			}
			function ctrlSpacing( label, target, prefix ) {
				var o = st( target );
				var four = o[ '_' + prefix + 'four' ];
				var t = o[ prefix + 't' ], r = o[ prefix + 'r' ], b = o[ prefix + 'b' ], l = o[ prefix + 'l' ];
				var body;
				if ( four ) {
					body = '<div class="vse-box4">' +
						cell( target, prefix + 't', t, 'Top' ) + cell( target, prefix + 'r', r, 'Right' ) +
						cell( target, prefix + 'b', b, 'Bot' ) + cell( target, prefix + 'l', l, 'Left' ) + '</div>';
				} else {
					body = '<div class="vse-box2">' +
						cell( target, prefix + 'tb', ( t != null ? t : '' ), 'Top / Bottom' ) +
						cell( target, prefix + 'lr', ( l != null ? l : '' ), 'Left / Right' ) + '</div>';
				}
				return '<div class="vse-ctrl"><div class="vse-spacing-head"><span class="vse-cl" style="margin:0;">' + label + '</span>' +
					'<button type="button" class="vse-sides-toggle' + ( four ? ' is-on' : '' ) + '" data-sides="' + target + ':' + prefix + '" title="Edit each side"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="4" y="4" width="16" height="16" rx="2"/><path d="M4 9h16M9 4v16"/></svg></button></div>' + body + '</div>';
			}
			function cell( target, key, val, lbl ) {
				return '<div class="vse-cell"><input data-t="' + target + '" data-k="' + key + '" value="' + escapeHtml( val == null ? '' : val ) + '"><span class="cl">' + lbl + '</span></div>';
			}

			function renderControls() {
				var o = st( current );
				var head, body = '';
				var titles = { form: 'Whole form', header: 'Header', labels: 'All labels', inputs: 'All inputs', submit: 'Submit button' };
				var subs = { form: 'Background, padding, radius', header: 'Title typography', labels: 'Field label text', inputs: 'Field input boxes', submit: 'Style every detail' };
				var nm = current.indexOf( 'field:' ) === 0 ? ( fieldByKey( current.slice( 6 ) ) || {} ).label : titles[ current ];
				head = '<div class="vse-left-head"><span class="ic"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.7">' + ( ICONS[ current ] || ICONS.inputs ) + '</svg></span>' +
					'<span><span class="tt">' + escapeHtml( nm || 'Element' ) + '</span><br><span class="ts">' + ( subs[ current ] || 'Edit styles' ) + '</span></span></div>';

				if ( current === 'submit' ) {
					body += '<div class="vse-sec"><div class="vse-sec-t">Content &amp; placement</div>';
					body += ctrlText( 'Button text', 'submit', 'text', form.submit_label || 'Submit' );
					body += ctrlSeg( 'Alignment', 'submit', 'align', o.align || 'center', [
						{ v: 'left', l: 'Left' }, { v: 'center', l: 'Center' }, { v: 'right', l: 'Right' }, { v: 'full', l: 'Full' } ] );
					body += '</div>';
					body += '<div class="vse-sec"><div class="vse-sec-t">Colors</div>' +
						ctrlColor( 'Background', 'submit', 'bg', o.bg ) + ctrlColor( 'Text', 'submit', 'color', o.color ) + ctrlColor( 'Hover background', 'submit', 'hoverBg', o.hoverBg ) + '</div>';
					body += '<div class="vse-sec"><div class="vse-sec-t">Typography</div><div class="vse-two">' +
						ctrlUnit( 'Font size', 'submit', 'fs', o.fs ) + ctrlText( 'Font weight', 'submit', 'fw', o.fw ) + '</div></div>';
					body += '<div class="vse-sec"><div class="vse-sec-t">Spacing</div>' + ctrlSpacing( 'Padding', 'submit', 'p' ) + ctrlSpacing( 'Margin', 'submit', 'm' ) + '</div>';
					body += '<div class="vse-sec"><div class="vse-sec-t">Border &amp; shadow</div><div class="vse-two">' +
						ctrlUnit( 'Radius', 'submit', 'radius', o.radius ) + ctrlUnit( 'Border', 'submit', 'border', o.border ) + '</div>' +
						ctrlColor( 'Border color', 'submit', 'borderColor', o.borderColor ) +
						ctrlSeg( 'Box shadow', 'submit', 'shadow', o.shadow || 'medium', [ { v: 'none', l: 'None' }, { v: 'soft', l: 'Soft' }, { v: 'medium', l: 'Med' }, { v: 'strong', l: 'Strong' } ] ) + '</div>';
				} else if ( current === 'form' ) {
					body += '<div class="vse-sec"><div class="vse-sec-t">Colors</div>' + ctrlColor( 'Background', 'form', 'bg', o.bg ) + '</div>';
					body += '<div class="vse-sec"><div class="vse-sec-t">Spacing</div>' + ctrlSpacing( 'Padding', 'form', 'p' ) + '</div>';
					body += '<div class="vse-sec"><div class="vse-sec-t">Border &amp; shadow</div><div class="vse-two">' +
						ctrlUnit( 'Radius', 'form', 'radius', o.radius ) + ctrlUnit( 'Border', 'form', 'border', o.border ) + '</div>' +
						ctrlColor( 'Border color', 'form', 'borderColor', o.borderColor ) +
						ctrlSeg( 'Box shadow', 'form', 'shadow', o.shadow || 'medium', [ { v: 'none', l: 'None' }, { v: 'soft', l: 'Soft' }, { v: 'medium', l: 'Med' }, { v: 'strong', l: 'Strong' } ] ) + '</div>';
				} else if ( current === 'header' ) {
					body += '<div class="vse-sec"><div class="vse-sec-t">Title</div>' + ctrlColor( 'Color', 'header', 'color', o.color ) + '<div class="vse-two">' +
						ctrlUnit( 'Font size', 'header', 'fs', o.fs ) + ctrlText( 'Font weight', 'header', 'fw', o.fw ) + '</div></div>';
				} else if ( current === 'labels' ) {
					body += '<div class="vse-sec"><div class="vse-sec-t">Label text</div>' + ctrlColor( 'Color', 'labels', 'color', o.color ) + '<div class="vse-two">' +
						ctrlUnit( 'Font size', 'labels', 'fs', o.fs ) + ctrlText( 'Font weight', 'labels', 'fw', o.fw ) + '</div></div>';
				} else if ( current === 'inputs' ) {
					body += '<div class="vse-sec"><div class="vse-sec-t">Colors</div>' + ctrlColor( 'Background', 'inputs', 'bg', o.bg ) + ctrlColor( 'Text', 'inputs', 'color', o.color ) + ctrlColor( 'Border color', 'inputs', 'borderColor', o.borderColor ) + '</div>';
					body += '<div class="vse-sec"><div class="vse-sec-t">Shape</div><div class="vse-two">' +
						ctrlUnit( 'Font size', 'inputs', 'fs', o.fs ) + ctrlUnit( 'Radius', 'inputs', 'radius', o.radius ) + '</div>' + ctrlUnit( 'Border width', 'inputs', 'border', o.border ) + '</div>';
				} else if ( current.indexOf( 'field:' ) === 0 ) {
					body += '<div class="vse-sec"><div class="vse-sec-t">This field\u2019s label</div>' + ctrlColor( 'Color', current, 'labelColor', o.labelColor ) +
						'<div class="vse-two">' + ctrlUnit( 'Font size', current, 'labelFs', o.labelFs ) + ctrlText( 'Font weight', current, 'labelFw', o.labelFw ) + '</div></div>';
					body += '<div class="vse-sec"><div class="vse-sec-t">This field\u2019s input</div>' + ctrlColor( 'Background', current, 'bg', o.bg ) + ctrlColor( 'Text', current, 'color', o.color ) + ctrlColor( 'Border color', current, 'borderColor', o.borderColor ) + '</div>';
					body += '<div class="vse-sec"><div class="vse-sec-t">Shape</div><div class="vse-two">' +
						ctrlUnit( 'Font size', current, 'fs', o.fs ) + ctrlUnit( 'Radius', current, 'radius', o.radius ) + '</div>' + ctrlUnit( 'Border width', current, 'border', o.border ) + '</div>';
				}
				$( '#vse-controls' ).innerHTML = head + body;
				bindControls();
			}
			function fieldByKey( k ) { return form.fields.filter( function ( f ) { return f.key === k; } )[ 0 ]; }

			function bindControls() {
				var wrap = $( '#vse-controls' );
				$$( '[data-k]', wrap ).forEach( function ( el ) {
					var ev = el.getAttribute( 'data-color' ) ? 'input' : 'input';
					el.addEventListener( ev, function () {
						var t = el.getAttribute( 'data-t' ), k = el.getAttribute( 'data-k' ), v = el.value;
						if ( t === 'submit' && k === 'text' ) { form.submit_label = v; buildPreview(); applyLive(); return; }
						var o = st( t );
						if ( k === 'ptb' ) { o.pt = v; o.pb = v; }
						else if ( k === 'plr' ) { o.pl = v; o.pr = v; }
						else if ( k === 'mtb' ) { o.mt = v; o.mb = v; }
						else if ( k === 'mlr' ) { o.ml = v; o.mr = v; }
						else { o[ k ] = v; }
						// keep hex<->swatch in sync
						if ( el.getAttribute( 'data-color' ) ) {
							var hex = $( '.vse-hex[data-k="' + k + '"][data-t="' + t + '"]', wrap ); if ( hex ) { hex.value = v; }
						} else if ( el.classList.contains( 'vse-hex' ) ) {
							var sw = $( '.vse-swatch[data-k="' + k + '"][data-t="' + t + '"]', wrap ); if ( sw && /^#([0-9a-f]{6})$/i.test( v ) ) { sw.value = v; }
						}
						applyLive();
					} );
				} );
				$$( '.vse-seg button', wrap ).forEach( function ( btn ) {
					btn.addEventListener( 'click', function () {
						var t = btn.getAttribute( 'data-t' ), k = btn.getAttribute( 'data-k' ), v = btn.getAttribute( 'data-v' );
						st( t )[ k ] = v;
						btn.parentNode.querySelectorAll( 'button' ).forEach( function ( b ) { b.classList.toggle( 'is-on', b === btn ); } );
						applyLive();
					} );
				} );
				$$( '.vse-sides-toggle', wrap ).forEach( function ( tg ) {
					tg.addEventListener( 'click', function () {
						var pair = tg.getAttribute( 'data-sides' ).split( ':' ); var target = pair[0], prefix = pair[1];
						var o = st( target ); var key = '_' + prefix + 'four';
						if ( ! o[ key ] ) {
							// expand 2 -> 4: seed all sides from the tb/lr values
							var tb = o[ prefix + 'tb' ], lr = o[ prefix + 'lr' ];
							if ( tb != null ) { o[ prefix + 't' ] = tb; o[ prefix + 'b' ] = tb; }
							if ( lr != null ) { o[ prefix + 'l' ] = lr; o[ prefix + 'r' ] = lr; }
						} else {
							// collapse 4 -> 2
							o[ prefix + 'tb' ] = o[ prefix + 't' ] || '';
							o[ prefix + 'lr' ] = o[ prefix + 'l' ] || '';
						}
						o[ key ] = ! o[ key ];
						renderControls();
					} );
				} );
			}

			// ---- selector tree (filtered by the tab bar) ----
			function renderTree() {
				var tree = $( '#vse-tree' );
				var tab = treeTab;
				var html = '';
				var styleable = form.fields.filter( function ( f ) { return [ 'step', 'html', 'captcha' ].indexOf( f.type ) === -1; } );
				if ( tab === 'all' ) {
					html += '<div class="vse-tree-glabel">Form</div>' + node( 'form', 'Whole form', '', ICONS.form );
				}
				if ( tab === 'all' || tab === 'text' ) {
					html += '<div class="vse-tree-glabel">Text</div>' + node( 'header', 'Header', '', ICONS.header ) + node( 'labels', 'All labels', '', ICONS.labels );
				}
				if ( tab === 'all' || tab === 'inputs' ) {
					html += '<div class="vse-tree-glabel">Inputs</div>' + node( 'inputs', 'All inputs', '', ICONS.inputs );
					styleable.forEach( function ( f ) {
						html += node( 'field:' + f.key, f.label || f.key, ( TYPES[ f.type ] ? TYPES[ f.type ].label : f.type ), ICONS.inputs );
					} );
				}
				if ( tab === 'all' || tab === 'buttons' ) {
					html += '<div class="vse-tree-glabel">Button</div>' + node( 'submit', 'Submit button', '', ICONS.submit );
				}
				if ( ! html ) { html = '<div class="vse-tree-empty">Nothing to style in this tab yet.</div>'; }
				tree.innerHTML = html;
				$$( '.vse-node', tree ).forEach( function ( n ) {
					n.addEventListener( 'click', function () {
						current = n.getAttribute( 'data-target' );
						$$( '.vse-node', tree ).forEach( function ( x ) { x.classList.toggle( 'is-on', x === n ); } );
						$( '#vse-target-name' ).textContent = n.querySelector( '.nm' ).textContent;
						renderControls(); markTarget();
					} );
				} );
			}
			function bindTreeTabs() {
				$$( '#vse-tabs button' ).forEach( function ( t ) {
					t.addEventListener( 'click', function () {
						treeTab = t.getAttribute( 'data-tab' );
						$$( '#vse-tabs button' ).forEach( function ( x ) { x.classList.toggle( 'is-on', x === t ); } );
						renderTree();
					} );
				} );
			}
			function node( target, name, type, icon ) {
				return '<div class="vse-node' + ( target === current ? ' is-on' : '' ) + '" data-target="' + target + '">' +
					'<span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">' + icon + '</svg></span>' +
					'<span class="nm">' + escapeHtml( name ) + '</span>' + ( type ? '<span class="ty">' + escapeHtml( type ) + '</span>' : '' ) + '</div>';
			}

			// ---- open / close / save ----
			function open() {
				buildPreview(); renderTree(); renderControls(); applyLive();
				root.hidden = false; document.body.style.overflow = 'hidden';
			}
			function close() { root.hidden = true; document.body.style.overflow = ''; }
			openStyleEditor = open;
			bindTreeTabs();
			$( '#vmail-style-btn' ).addEventListener( 'click', open );
			var toPrev = $( '#vse-to-preview' );
			if ( toPrev ) { toPrev.addEventListener( 'click', function () { close(); if ( openPreviewOverlay ) { openPreviewOverlay(); } } ); }
			$( '#vse-save' ).addEventListener( 'click', function () { close(); renderCanvas(); toast( 'Styles applied. Remember to Save the form.' ); } );
			$( '#vse-reset' ).addEventListener( 'click', function () {
				form.style = {}; S = form.style; current = 'submit'; buildPreview(); renderTree(); renderControls(); applyLive();
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
			var subset = mediaItems.filter( function ( it ) { return mediaMode === 'used' ? it.used : ! it.used; } );
			if ( ! subset.length ) {
				results.innerHTML = mediaMode === 'used'
					? '<p class="velox-hint">No used images in the scanned set.</p>'
					: '<p class="velox-hint">Nothing flagged — every image looks referenced. 🎉</p>';
				summary.textContent = '';
				return;
			}
			var total = 0;
			subset.forEach( function ( it ) {
				total += it.bytes || 0;
				var card = document.createElement( mediaMode === 'used' ? 'div' : 'label' );
				card.className = 'velox-media-item' + ( mediaMode === 'used' ? ' is-used' : '' );
				card.innerHTML =
					( mediaMode === 'used' ? '' : '<input type="checkbox" class="velox-media-pick" value="' + it.id + '">' ) +
					'<img src="' + it.thumb + '" alt="" loading="lazy">' +
					'<span class="velox-media-name">' + ( it.title || ( '#' + it.id ) ) + '</span>' +
					'<span class="velox-media-size">' + fmtBytes( it.bytes ) + '</span>';
				results.appendChild( card );
			} );
			if ( mediaMode === 'used' ) {
				summary.textContent = subset.length + ' used image' + ( subset.length === 1 ? '' : 's' ) + ' · ' + fmtBytes( total ) + ' total';
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

		scanBtn.addEventListener( 'click', function () {
			scanBtn.disabled = true;
			scanBtn.textContent = 'Scanning…';
			summary.textContent = '';
			results.innerHTML = '<p class="velox-hint">Scanning library, crawling pages and reading builder CSS… this can take a moment on large sites.</p>';
			api( 'media_scan', {} )
				.then( function ( d ) {
					mediaItems = d.items || [];
					filterWrap.hidden = mediaItems.length === 0;
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
		function open() {
			closeAll( wrap );
			wrap.classList.add( 'is-open' );
			btn.setAttribute( 'aria-expanded', 'true' );
			var s = menu.querySelector( '.is-sel' );
			if ( s ) { s.scrollIntoView( { block: 'nearest' } ); }
		}
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
		$.post( VELOX.ajaxurl, { action: 'velox', 'do': 'dash_widgets', nonce: VELOX.nonce, hidden: hiddenIds(), order: orderIds() } );
	}

	function updateBatch() {
		var n = selected().length;
		batchbar.hidden = ( n === 0 );
		if ( n ) { batchCnt.textContent = n; batchWord.textContent = ( n === 1 ? 'widget' : 'widgets' ); }
	}
	function clearSel() { selected().forEach( function ( w ) { w.classList.remove( 'sel' ); } ); updateBatch(); }

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
	function exitEdit()  { cockpit.classList.remove( 'editing' ); editBtn.hidden = false; doneBtn.hidden = true; newWrap.hidden = true; newMenu.hidden = true; setDraggable( false ); clearSel(); }

	/* ----- drag to reorder (edit mode only) ----- */
	var dragEl = null;
	function setDraggable( on ) { widgets().forEach( function ( w ) { w.draggable = on; } ); }
	function dragTarget( x, y ) {
		var els = widgets().filter( function ( w ) { return w !== dragEl && ! w.classList.contains( 'is-hidden' ); } );
		var best = null, bestD = Infinity, before = true;
		els.forEach( function ( w ) {
			var r = w.getBoundingClientRect();
			var cx = r.left + r.width / 2, cy = r.top + r.height / 2;
			var d = Math.abs( x - cx ) + Math.abs( y - cy );
			if ( d < bestD ) { bestD = d; best = w; before = ( y < r.top ) || ( y < r.bottom && x < cx ); }
		} );
		return best ? { el: best, before: before } : null;
	}
	cockpit.addEventListener( 'dragstart', function ( e ) {
		if ( ! cockpit.classList.contains( 'editing' ) ) { return; }
		if ( e.target.closest && e.target.closest( 'a, button' ) ) { e.preventDefault(); return; }
		var w = e.target.closest ? e.target.closest( '.velox-w' ) : null;
		if ( ! w ) { return; }
		dragEl = w; w.classList.add( 'is-dragging' );
		if ( e.dataTransfer ) { e.dataTransfer.effectAllowed = 'move'; try { e.dataTransfer.setData( 'text/plain', w.getAttribute( 'data-widget' ) || '' ); } catch ( err ) {} }
	} );
	cockpit.addEventListener( 'dragover', function ( e ) {
		if ( ! dragEl ) { return; }
		e.preventDefault();
		if ( e.dataTransfer ) { e.dataTransfer.dropEffect = 'move'; }
		var t = dragTarget( e.clientX, e.clientY );
		if ( ! t || t.el === dragEl ) { return; }
		if ( t.before ) { cockpit.insertBefore( dragEl, t.el ); }
		else { cockpit.insertBefore( dragEl, t.el.nextSibling ); }
	} );
	cockpit.addEventListener( 'drop', function ( e ) { if ( dragEl ) { e.preventDefault(); } } );
	cockpit.addEventListener( 'dragend', function () {
		if ( ! dragEl ) { return; }
		dragEl.classList.remove( 'is-dragging' );
		dragEl = null;
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
		if ( ! cockpit.classList.contains( 'editing' ) ) { return; }
		var w = e.target.closest ? e.target.closest( '.velox-w' ) : null;
		if ( ! w ) { return; }
		if ( e.target.closest( '.velox-w-x' ) ) {
			e.preventDefault();
			w.classList.add( 'is-hidden' ); w.classList.remove( 'sel' );
			save(); buildPicker(); updateBatch();
			return;
		}
		if ( e.target.closest( 'a, .velox-w-act' ) ) { return; }
		w.classList.toggle( 'sel' );
		updateBatch();
	} );
}( jQuery ) );
