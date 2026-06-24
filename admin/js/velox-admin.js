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
		$$( '[data-setting]', root ).forEach( function ( el ) {
			var key = el.getAttribute( 'data-setting' );
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
		if ( ! overlay ) {
			return;
		}
		var sel = $( '#velox-wizard-select' );

		function show( step ) {
			$$( '.velox-wizard-step', overlay ).forEach( function ( s ) {
				s.hidden = s.getAttribute( 'data-step' ) !== step;
			} );
		}
		function open( step ) {
			overlay.hidden = false;
			show( step || 'intro' );
		}
		function close() {
			overlay.hidden = true;
		}
		function dismiss() {
			api( 'wizard_dismiss', {} ).catch( function () {} );
			close();
		}
		function syncNote() {
			var note = $( '#velox-wizard-dnote' );
			var opt = sel ? sel.options[ sel.selectedIndex ] : null;
			if ( note && opt ) {
				note.textContent = opt.getAttribute( 'data-note' ) || '';
			}
		}
		function on( id, ev, fn ) {
			var el = $( id );
			if ( el ) {
				el.addEventListener( ev, fn );
			}
		}

		if ( '1' === overlay.getAttribute( 'data-autoopen' ) ) {
			open( 'intro' );
		}

		on( '#velox-open-wizard', 'click', function ( e ) { e.preventDefault(); open( 'intro' ); } );
		on( '#velox-wizard-close', 'click', dismiss );
		on( '#velox-wizard-skip', 'click', dismiss );
		on( '#velox-wizard-back', 'click', function () { show( 'intro' ); } );

		on( '#velox-wizard-check', 'click', function () {
			var btn = this;
			btn.disabled = true;
			btn.textContent = 'Checking…';
			api( 'builder_detect', {} )
				.then( function ( d ) {
					var title = $( '#velox-wizard-dtitle' );
					if ( title ) {
						title.textContent = d.is_default ? 'No builder detected' : ( 'Detected ' + d.label + ' — correct?' );
					}
					if ( sel ) {
						for ( var i = 0; i < sel.options.length; i++ ) {
							if ( sel.options[ i ].value === d.builder ) { sel.selectedIndex = i; break; }
						}
						syncNote();
					}
					show( 'detected' );
				} )
				.catch( function ( e ) { toast( e.message, 'error' ); } )
				.then( function () { btn.disabled = false; btn.textContent = 'Run builder check'; } );
		} );

		if ( sel ) {
			sel.addEventListener( 'change', syncNote );
		}

		on( '#velox-wizard-apply', 'click', function () {
			var btn = this;
			btn.disabled = true;
			btn.textContent = 'Configuring…';
			api( 'builder_apply', { builder: sel ? sel.value : '' } )
				.then( function ( d ) {
					var msg = $( '#velox-wizard-donemsg' );
					if ( msg ) { msg.textContent = d.message || 'Configured.'; }
					show( 'done' );
				} )
				.catch( function ( e ) { toast( e.message, 'error' ); } )
				.then( function () { btn.disabled = false; btn.textContent = 'Configure for this builder'; } );
		} );

		on( '#velox-wizard-finish', 'click', function () { close(); location.reload(); } );

		on( '#velox-wizard-req-open', 'click', function ( e ) {
			e.preventDefault();
			var r = $( '#velox-wizard-req' );
			if ( r ) { r.hidden = false; }
		} );
		on( '#velox-wizard-req-send', 'click', function () {
			var input = $( '#velox-wizard-req-name' );
			var btn = this;
			btn.disabled = true;
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
				var frame = wp.media( { title: 'Choose image', button: { text: 'Use image' }, multiple: false, library: { type: 'image' } } );
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
				var row = btn.closest( '.velox-mail-formrow' );
				if ( ! window.confirm( 'Delete this form? Its submissions stay in the log.' ) ) { return; }
				api( 'form_delete', { id: row.getAttribute( 'data-id' ) } )
					.then( function () { row.remove(); toast( 'Form deleted.' ); } )
					.catch( function ( e ) { toast( e.message, 'error' ); } );
			} );
		} );
		$$( '.velox-mail-sub-del' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var sub = btn.closest( '.velox-mail-sub' );
				api( 'submission_delete', { id: sub.getAttribute( 'data-id' ) } )
					.then( function () { sub.remove(); toast( 'Removed.' ); } )
					.catch( function ( e ) { toast( e.message, 'error' ); } );
			} );
		} );
		var testBtn = $( '#vmail-test' );
		if ( testBtn ) {
			testBtn.addEventListener( 'click', function () {
				var to = $( '#vmail-test-to' ).value;
				testBtn.disabled = true;
				api( 'mail_test', { to: to } )
					.then( function ( r ) { toast( r.message, r.ok ? 'success' : 'error' ); } )
					.catch( function ( e ) { toast( e.message, 'error' ); } )
					.then( function () { testBtn.disabled = false; } );
			} );
		}
		var logClear = $( '#vmail-log-clear' );
		if ( logClear ) {
			logClear.addEventListener( 'click', function () {
				api( 'mail_log_clear', {} ).then( function () { location.reload(); } );
			} );
		}
	}

	function initMailBuilder() {
		var dataEl = $( '#vmail-data' );
		if ( ! dataEl ) { return; }
		var form, meta;
		try { form = JSON.parse( dataEl.textContent ); } catch ( e ) { return; }
		try { meta = JSON.parse( ( $( '#vmail-meta' ) || {} ).textContent || '{}' ); } catch ( e2 ) { meta = {}; }
		form.fields = form.fields || [];
		form.emails = form.emails || [];

		var TYPES = {
			text:        { label: 'Single line', icon: 'T', opts: false, cat: 'general' },
			email:       { label: 'Email',       icon: '@', opts: false, cat: 'general' },
			tel:         { label: 'Phone',       icon: '\u260E', opts: false, cat: 'general' },
			number:      { label: 'Number',      icon: '#', opts: false, cat: 'general' },
			textarea:    { label: 'Paragraph',   icon: '\u00B6', opts: false, cat: 'general' },
			select:      { label: 'Dropdown',    icon: '\u25BE', opts: true,  cat: 'general' },
			radio:       { label: 'Radio',       icon: '\u25C9', opts: true,  cat: 'general' },
			checkbox:    { label: 'Checkbox',    icon: '\u2611', opts: false, cat: 'general' },
			name:        { label: 'Name',        icon: 'Aa', opts: false, cat: 'advanced' },
			multiselect: { label: 'Multi-select',icon: '\u2630', opts: true,  cat: 'advanced' },
			country:     { label: 'Country',     icon: '\u25C8', opts: false, cat: 'advanced' },
			url:         { label: 'Website URL', icon: '\u29C9', opts: false, cat: 'advanced' },
			date:        { label: 'Date',        icon: '\u25A6', opts: false, cat: 'advanced' },
			consent:     { label: 'Consent',     icon: '\u2713', opts: false, cat: 'advanced' },
			captcha:     { label: 'CAPTCHA',     icon: '\u26E8', opts: false, cat: 'advanced' },
			html:        { label: 'Custom HTML',  icon: '\u2039\u203A', opts: false, cat: 'layout' }
		};
		var CATS = { general: 'General fields', advanced: 'Advanced fields', layout: 'Layout' };

		var canvas     = $( '#vmail-canvas' );
		var palette    = $( '#vmail-palette' );
		var inspector  = $( '#vmail-inspector' );
		var emailsWrap = $( '#vmail-emails' );
		var selected   = form.fields.length ? 0 : -1;
		var dragFrom   = -1;

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
				_lockKey: !! ( f.key && /^[a-z0-9_]+$/.test( f.key ) )
			};
		}
		form.fields = form.fields.map( normalize );
		reKey();

		function optList( f ) { return ( f.options || '' ).split( '\n' ).map( function ( s ) { return s.trim(); } ).filter( Boolean ); }

		/* ---------- palette ---------- */
		function renderPalette( filter ) {
			filter = ( filter || '' ).toLowerCase();
			palette.innerHTML = '';
			Object.keys( CATS ).forEach( function ( cat ) {
				var keys = Object.keys( TYPES ).filter( function ( t ) {
					return TYPES[ t ].cat === cat && ( ! filter || TYPES[ t ].label.toLowerCase().indexOf( filter ) !== -1 );
				} );
				if ( ! keys.length ) { return; }
				var group = document.createElement( 'div' ); group.className = 'vmail-pal-group';
				group.innerHTML = '<div class="vmail-pal-cat">' + CATS[ cat ] + '</div>';
				var grid = document.createElement( 'div' ); grid.className = 'vmail-pal-grid';
				keys.forEach( function ( t ) {
					var b = document.createElement( 'button' );
					b.type = 'button'; b.className = 'vmail-pal-item';
					b.innerHTML = '<span class="vmail-pal-ic">' + TYPES[ t ].icon + '</span><span>' + TYPES[ t ].label + '</span>';
					b.addEventListener( 'click', function () { addField( t ); } );
					grid.appendChild( b );
				} );
				group.appendChild( grid );
				palette.appendChild( group );
			} );
			if ( ! palette.children.length ) {
				palette.innerHTML = '<p class="velox-hint" style="padding:6px 2px;">No fields match "' + escapeHtml( filter ) + '".</p>';
			}
		}
		function hasType( t ) { return form.fields.some( function ( f ) { return f.type === t; } ); }
		function addField( type ) {
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
			form.fields.push( f );
			reKey();
			selected = form.fields.length - 1;
			renderCanvas(); renderInspector();
		}

		/* ---------- canvas ---------- */
		function fieldPreview( f ) {
			var star = f.required ? ' <span class="velox-req">*</span>' : '';
			var lbl  = f.label ? '<span class="velox-form-label">' + escapeHtml( f.label ) + star + '</span>' : '';
			var help = f.help ? '<span class="velox-form-help">' + escapeHtml( f.help ) + '</span>' : '';
			if ( f.type === 'html' ) { return '<div class="vmail-html-prev">' + ( f.content || '<em>Custom HTML</em>' ) + '</div>'; }
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
				canvas.innerHTML = '<div class="vmail-empty"><strong>Empty form</strong><span>Click a field on the left to add it.</span></div>';
				return;
			}
			form.fields.forEach( function ( f, i ) {
				var card = document.createElement( 'div' );
				card.className = 'vmail-fcard' + ( i === selected ? ' is-selected' : '' ) + ( f.width === 'half' ? ' is-half' : ( f.width === 'third' ? ' is-third' : '' ) );
				card.setAttribute( 'draggable', 'true' );
				card.innerHTML =
					'<div class="vmail-fcard-handle" title="Drag to reorder">\u22EE\u22EE</div>' +
					'<div class="vmail-fcard-body">' + fieldPreview( f ) + '</div>' +
					'<div class="vmail-fcard-tools"><span class="vmail-fcard-type">' + ( TYPES[ f.type ] ? TYPES[ f.type ].label : f.type ) + '</span><button type="button" class="vmail-fcard-del" title="Remove">\u2715</button></div>';
				card.addEventListener( 'click', function ( e ) {
					if ( e.target.closest( '.vmail-fcard-del' ) ) { return; }
					selected = i; renderCanvas(); renderInspector();
				} );
				card.querySelector( '.vmail-fcard-del' ).addEventListener( 'click', function () {
					form.fields.splice( i, 1 );
					if ( selected >= form.fields.length ) { selected = form.fields.length - 1; }
					renderCanvas(); renderInspector();
				} );
				card.addEventListener( 'dragstart', function () { dragFrom = i; card.classList.add( 'is-drag' ); } );
				card.addEventListener( 'dragend', function () { card.classList.remove( 'is-drag' ); } );
				card.addEventListener( 'dragover', function ( e ) { e.preventDefault(); card.classList.add( 'is-over' ); } );
				card.addEventListener( 'dragleave', function () { card.classList.remove( 'is-over' ); } );
				card.addEventListener( 'drop', function ( e ) {
					e.preventDefault(); card.classList.remove( 'is-over' );
					if ( dragFrom < 0 || dragFrom === i ) { return; }
					var moved = form.fields.splice( dragFrom, 1 )[ 0 ];
					form.fields.splice( i, 0, moved );
					selected = i; dragFrom = -1;
					renderCanvas(); renderInspector();
				} );
				canvas.appendChild( card );
			} );
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
		function renderInspector() {
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

			if ( t === 'html' ) {
				rows += inspText( 'Field key', 'key', f.key, 'Merge tag: {inputs.' + escapeHtml( f.key ) + '}' );
				rows += '<div class="velox-field"><span class="velox-field-label">HTML content</span><textarea class="velox-textarea velox-mono" rows="6" data-k="content">' + escapeHtml( f.content || '' ) + '</textarea><span class="velox-hint">Rendered as-is in the form. Basic HTML is allowed.</span></div>';
				rows += widthSelect( f ) + inspText( 'CSS class', 'css', f.css );
			} else if ( t === 'captcha' ) {
				rows += '<div class="vmail-insp-note">This drops your CAPTCHA widget into the form. Configure the provider and keys under <strong>Mail &amp; forms \u2192 CAPTCHA</strong>. A form uses either a consent box or CAPTCHA \u2014 not both.</div>';
				rows += widthSelect( f ) + inspText( 'CSS class', 'css', f.css );
			} else {
				rows += inspText( 'Label', 'label', f.label );
				rows += inspText( 'Field key', 'key', f.key, 'Merge tag: {inputs.' + escapeHtml( f.key ) + '}' );
				rows += '<label class="vmail-insp-check"><input type="checkbox" data-k="required"' + ( f.required ? ' checked' : '' ) + '> Required field</label>';
				if ( noPlaceholder.indexOf( t ) === -1 ) { rows += inspText( 'Placeholder', 'placeholder', f.placeholder ); }
				if ( t === 'name' ) { rows += inspArea( 'Sub-labels (first line = first name, second = last name)', 'options', f.options ); }
				else if ( hasOpts ) { rows += inspArea( 'Options (one per line)', 'options', f.options ); }
				if ( noDefault.indexOf( t ) === -1 ) { rows += inspText( 'Default value', 'default', f['default'] ); }
				rows += inspText( 'Help text', 'help', f.help );
				rows += widthSelect( f );
				rows += inspText( 'CSS class', 'css', f.css );
			}
			inspector.innerHTML = '<div class="vmail-insp-head">' + ( TYPES[ t ] ? TYPES[ t ].label : t ) + ' field</div><div class="vmail-insp-body">' + rows + '</div>';

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
		}

		/* ---------- notifications ---------- */
		function fieldTags() {
			var skip = { consent: 1, html: 1, captcha: 1 };
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
					'<div class="vmail-email-head"><label class="vmail-email-toggle"><input type="checkbox" data-e="enabled"' + ( e.enabled ? ' checked' : '' ) + '><span><strong>' + cfg.title + '</strong><em>' + cfg.desc + '</em></span></label></div>' +
					'<div class="vmail-email-grid">' +
						toRow +
						field2( 'From name', '<input type="text" class="velox-input" data-e="from_name" value="' + escapeHtml( e.from_name ) + '" placeholder="' + escapeHtml( meta.site_name || '' ) + '">' ) +
						field2( 'From email', '<input type="text" class="velox-input" data-e="from_email" value="' + escapeHtml( e.from_email ) + '" placeholder="blank = site default">' ) +
						field2( 'Reply-To', '<input type="text" class="velox-input" data-e="reply_to" value="' + escapeHtml( e.reply_to ) + '" placeholder="e.g. {inputs.email}">' ) +
						field2( 'CC', '<input type="text" class="velox-input" data-e="cc" value="' + escapeHtml( e.cc ) + '" placeholder="comma,separated">' ) +
						field2( 'BCC', '<input type="text" class="velox-input" data-e="bcc" value="' + escapeHtml( e.bcc ) + '" placeholder="comma,separated">' ) +
					'</div>' +
					field2( 'Subject', '<div class="vmail-mergewrap"><input type="text" class="velox-input" data-e="subject" value="' + escapeHtml( e.subject ) + '">' + mergeBtn() + '</div>' ) +
					field2( 'Email body', '<div class="vmail-mergewrap"><textarea class="velox-textarea" rows="6" data-e="body">' + escapeHtml( e.body ) + '</textarea>' + mergeBtn() + '</div>' );
				emailsWrap.appendChild( block );
				$$( '[data-e]', block ).forEach( function ( el ) {
					var ev = el.type === 'checkbox' ? 'change' : 'input';
					el.addEventListener( ev, function () { var k = el.getAttribute( 'data-e' ); e[ k ] = el.type === 'checkbox' ? el.checked : el.value; } );
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
		$( '#vmail-save' ).addEventListener( 'click', save );

		var palSearch = $( '#vmail-palette-search' );
		if ( palSearch ) { palSearch.addEventListener( 'input', function () { renderPalette( palSearch.value ); } ); }

		renderPalette();
		renderCanvas();
		renderInspector();
		bindSettings();
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
				scanBtn.textContent = 'Scanning…';
				api( 'scripts_scan', {} )
					.then( function ( r ) {
						if ( ! r.ok ) { toast( r.message || 'Scan failed.', 'error' ); return; }
						toast( 'Found ' + r.scripts + ' scripts, ' + r.styles + ' styles.' );
						setTimeout( function () { location.reload(); }, 700 );
					} )
					.catch( function ( e ) { toast( e.message, 'error' ); } )
					.then( function () { scanBtn.disabled = false; scanBtn.textContent = 'Scan front page'; } );
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

		scanBtn.addEventListener( 'click', function () {
			scanBtn.disabled = true;
			scanBtn.textContent = 'Scanning…';
			summary.textContent = '';
			results.innerHTML = '';
			api( 'media_scan', {} )
				.then( function ( d ) {
					var items = d.items || [];
					if ( ! items.length ) {
						results.innerHTML = '<p class="velox-hint">Nothing flagged — every image looks referenced. 🎉</p>';
						summary.textContent = '';
						return;
					}
					var total = 0;
					items.forEach( function ( it ) {
						total += it.bytes || 0;
						var card = document.createElement( 'label' );
						card.className = 'velox-media-item';
						card.innerHTML =
							'<input type="checkbox" class="velox-media-pick" value="' + it.id + '">' +
							'<img src="' + it.thumb + '" alt="" loading="lazy">' +
							'<span class="velox-media-name">' + ( it.title || ( '#' + it.id ) ) + '</span>' +
							'<span class="velox-media-size">' + fmtBytes( it.bytes ) + '</span>';
						results.appendChild( card );
					} );
					summary.textContent = items.length + ' possibly-unused image' + ( items.length === 1 ? '' : 's' ) + ' · ' + fmtBytes( total ) + ' reclaimable';
					$$( '.velox-media-pick', results ).forEach( function ( c ) { c.addEventListener( 'change', refreshSelection ); } );
				} )
				.catch( function ( e ) { toast( e.message, 'error' ); } )
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
					$$( '.velox-media-pick:checked', results ).forEach( function ( c ) { c.closest( '.velox-media-item' ).remove(); } );
					refreshSelection();
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
		var addBtn = $( '#velox-redir-add' );
		if ( ! addBtn ) {
			return;
		}
		var srcEl   = $( '#velox-redir-source' );
		var tgtEl   = $( '#velox-redir-target' );
		var typeEl  = $( '#velox-redir-type' );
		var cancelBtn = $( '#velox-redir-cancel' );
		var editingId = null;

		function resetForm() {
			editingId = null;
			srcEl.value = ''; tgtEl.value = ''; typeEl.value = '301';
			addBtn.textContent = 'Add';
			if ( cancelBtn ) { cancelBtn.hidden = true; }
		}
		function startEdit( row ) {
			editingId = row.getAttribute( 'data-id' );
			srcEl.value = row.getAttribute( 'data-source' ) || '';
			tgtEl.value = row.getAttribute( 'data-target' ) || '';
			typeEl.value = row.getAttribute( 'data-type' ) || '301';
			addBtn.textContent = 'Update';
			if ( cancelBtn ) { cancelBtn.hidden = false; }
			srcEl.focus();
			addBtn.scrollIntoView( { behavior: 'smooth', block: 'center' } );
		}
		if ( cancelBtn ) { cancelBtn.addEventListener( 'click', resetForm ); }

		addBtn.addEventListener( 'click', function () {
			if ( ! srcEl.value.trim() ) { toast( 'Enter a source path.', 'error' ); return; }
			addBtn.disabled = true;
			var action = editingId ? 'redirect_update' : 'redirect_add';
			var data   = { source: srcEl.value, target: tgtEl.value, type: typeEl.value };
			if ( editingId ) { data.id = editingId; }
			api( action, data )
				.then( function ( r ) {
					if ( ! r.ok ) { toast( r.message || 'Could not save.', 'error' ); return; }
					toast( editingId ? 'Redirect updated.' : 'Redirect added.' );
					setTimeout( function () { location.reload(); }, 400 );
				} )
				.catch( function ( e ) { toast( e.message, 'error' ); } )
				.then( function () { addBtn.disabled = false; } );
		} );

		var redirList = $( '#velox-redir-list' );
		if ( redirList ) {
			redirList.addEventListener( 'click', function ( e ) {
				var row = e.target.closest( '.velox-redir-row' );
				if ( ! row ) { return; }
				if ( e.target.classList.contains( 'velox-redir-del' ) ) {
					api( 'redirect_delete', { id: row.getAttribute( 'data-id' ) } )
						.then( function () { row.remove(); toast( 'Removed.' ); if ( editingId === row.getAttribute( 'data-id' ) ) { resetForm(); } } )
						.catch( function ( er ) { toast( er.message, 'error' ); } );
				} else if ( e.target.classList.contains( 'velox-redir-edit' ) ) {
					startEdit( row );
				} else if ( e.target.classList.contains( 'velox-redir-visit' ) ) {
					var url = row.getAttribute( 'data-visit' );
					if ( url ) { window.open( url, '_blank', 'noopener' ); }
				}
			} );
		}

		var logToggle = $( '#velox-log-toggle' );
		if ( logToggle ) {
			logToggle.addEventListener( 'change', function () {
				saveSettings( { util_redirects_log_404: logToggle.checked ? 1 : 0 }, logToggle.checked ? 'Logging 404s.' : 'Stopped logging.' );
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
					srcEl.value = row.getAttribute( 'data-path' );
					tgtEl.focus();
					srcEl.scrollIntoView( { behavior: 'smooth', block: 'center' } );
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

	document.addEventListener( 'DOMContentLoaded', function () {
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
	} );
} )();
