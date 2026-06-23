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
		el.textContent = message;
		el.className = 'velox-toast is-visible' + ( type ? ' velox-toast--' + type : '' );
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
			if ( r && r.dropin && r.wp_cache ) {
				cachePill.textContent = 'Active · early serve';
				cachePill.className = 'velox-pill velox-pill--ok';
				if ( cacheNote ) { cacheNote.hidden = true; }
			} else {
				cachePill.textContent = 'On · manual step needed';
				cachePill.className = 'velox-pill velox-pill--warn';
				if ( cacheNote && r && r.manual ) {
					cacheNote.hidden = false;
					cacheNote.innerHTML = 'Almost there — add this one line near the top of <code>wp-config.php</code> to enable early serving: <code>' + escapeHtml( r.manual ) + '</code>';
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
					.then( function () { toast( on ? 'Turned on.' : 'Turned off.' ); } )
					.catch( function ( e ) { box.checked = ! on; toast( e.message, 'error' ); } )
					.then( function () { box.disabled = false; } );
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
		initMail();
		initMailBuilder();
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
					.then( function () { sub.remove(); } )
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
		if ( ! dataEl ) {
			return;
		}
		var form;
		try { form = JSON.parse( dataEl.textContent ); } catch ( e ) { return; }
		form.fields = form.fields || [];
		form.emails = form.emails || [];

		var fieldsWrap = $( '#vmail-fields' );
		var emailsWrap = $( '#vmail-emails' );
		var preview    = $( '#vmail-preview' );
		var TYPES = { text: 'Text', email: 'Email', tel: 'Phone', textarea: 'Text area', select: 'Dropdown', checkbox: 'Checkbox', consent: 'Consent (Datenschutz)' };

		function typeOptions( sel ) {
			return Object.keys( TYPES ).map( function ( v ) {
				return '<option value="' + v + '"' + ( v === sel ? ' selected' : '' ) + '>' + TYPES[ v ] + '</option>';
			} ).join( '' );
		}

		function renderFields() {
			fieldsWrap.innerHTML = '';
			form.fields.forEach( function ( f, i ) {
				var row = document.createElement( 'div' );
				row.className = 'vmail-field';
				var hasOpts = f.type === 'select';
				row.innerHTML =
					'<div class="vmail-field-top">' +
						'<input type="text" class="velox-input vmail-f-label" placeholder="Label" value="' + escapeHtml( f.label || '' ) + '">' +
						'<select class="velox-select vmail-f-type">' + typeOptions( f.type ) + '</select>' +
						'<label class="vmail-f-req"><input type="checkbox" class="vmail-f-required"' + ( f.required ? ' checked' : '' ) + '> required</label>' +
						'<button type="button" class="velox-btn velox-btn--ghost vmail-f-up">↑</button>' +
						'<button type="button" class="velox-btn velox-btn--ghost vmail-f-del">✕</button>' +
					'</div>' +
					'<input type="text" class="velox-input vmail-f-ph" placeholder="Placeholder (optional)" value="' + escapeHtml( f.placeholder || '' ) + '">' +
					'<textarea class="velox-textarea vmail-f-opts" rows="3" placeholder="One option per line"' + ( hasOpts ? '' : ' hidden' ) + '>' + escapeHtml( f.options || '' ) + '</textarea>';
				fieldsWrap.appendChild( row );

				row.querySelector( '.vmail-f-type' ).addEventListener( 'change', function ( e ) {
					row.querySelector( '.vmail-f-opts' ).hidden = e.target.value !== 'select';
					sync(); renderPreview();
				} );
				row.querySelectorAll( 'input, textarea' ).forEach( function ( el ) {
					el.addEventListener( 'input', function () { sync(); renderPreview(); } );
				} );
				row.querySelector( '.vmail-f-del' ).addEventListener( 'click', function () { form.fields.splice( i, 1 ); renderFields(); renderPreview(); } );
				row.querySelector( '.vmail-f-up' ).addEventListener( 'click', function () {
					if ( i > 0 ) { var t = form.fields[ i - 1 ]; form.fields[ i - 1 ] = form.fields[ i ]; form.fields[ i ] = t; renderFields(); renderPreview(); }
				} );
			} );
		}

		function renderEmails() {
			emailsWrap.innerHTML = '';
			[ 'admin', 'customer' ].forEach( function ( kind ) {
				var e = form.emails.filter( function ( x ) { return x.type === kind; } )[ 0 ];
				if ( ! e ) { e = { type: kind, enabled: false, to: '', to_field: 'email', cc: '', subject: '', body: '' }; form.emails.push( e ); }
				var block = document.createElement( 'div' );
				block.className = 'vmail-email';
				block.setAttribute( 'data-type', kind );
				var toRow = kind === 'admin'
					? '<div class="velox-field"><span class="velox-field-label">Send to</span><input type="text" class="velox-input vm-to" value="' + escapeHtml( e.to || '' ) + '" placeholder="you@agency.com"></div>'
					: '<div class="velox-field"><span class="velox-field-label">Send to the value of field</span><input type="text" class="velox-input vm-tofield" value="' + escapeHtml( e.to_field || 'email' ) + '" placeholder="email"></div>';
				block.innerHTML =
					'<label class="vmail-email-head"><input type="checkbox" class="vm-enabled"' + ( e.enabled ? ' checked' : '' ) + '> <strong>' + ( kind === 'admin' ? 'Admin notification (to you)' : 'Customer auto-reply' ) + '</strong></label>' +
					toRow +
					'<div class="velox-field"><span class="velox-field-label">CC</span><input type="text" class="velox-input vm-cc" value="' + escapeHtml( e.cc || '' ) + '" placeholder="comma,separated"></div>' +
					'<div class="velox-field"><span class="velox-field-label">Subject</span><input type="text" class="velox-input vm-subject" value="' + escapeHtml( e.subject || '' ) + '"></div>' +
					'<div class="velox-field"><span class="velox-field-label">Body</span><textarea class="velox-textarea vm-body" rows="5">' + escapeHtml( e.body || '' ) + '</textarea></div>';
				emailsWrap.appendChild( block );
				block.querySelectorAll( 'input, textarea' ).forEach( function ( el ) { el.addEventListener( 'input', sync ); } );
			} );
		}

		function sync() {
			// fields
			var rows = $$( '.vmail-field', fieldsWrap );
			form.fields = rows.map( function ( row ) {
				return {
					key: '',
					label: row.querySelector( '.vmail-f-label' ).value,
					type: row.querySelector( '.vmail-f-type' ).value,
					required: row.querySelector( '.vmail-f-required' ).checked,
					placeholder: row.querySelector( '.vmail-f-ph' ).value,
					options: row.querySelector( '.vmail-f-opts' ).value,
				};
			} );
			// derive keys from labels (stable, unique-ish)
			var used = {};
			form.fields.forEach( function ( f ) {
				var base = ( f.label || 'field' ).toLowerCase().replace( /[^a-z0-9]+/g, '_' ).replace( /^_|_$/g, '' ) || 'field';
				var k = base, n = 2;
				while ( used[ k ] ) { k = base + '_' + ( n++ ); }
				used[ k ] = 1; f.key = k;
			} );
			// emails
			$$( '.vmail-email', emailsWrap ).forEach( function ( block ) {
				var kind = block.getAttribute( 'data-type' );
				var e = form.emails.filter( function ( x ) { return x.type === kind; } )[ 0 ];
				e.enabled = block.querySelector( '.vm-enabled' ).checked;
				if ( kind === 'admin' ) { e.to = block.querySelector( '.vm-to' ).value; }
				else { e.to_field = block.querySelector( '.vm-tofield' ).value; }
				e.cc = block.querySelector( '.vm-cc' ).value;
				e.subject = block.querySelector( '.vm-subject' ).value;
				e.body = block.querySelector( '.vm-body' ).value;
			} );
			// settings
			form.title = $( '#vmail-title' ).value;
			form.submit_label = $( '#vmail-submit' ).value;
			form.success = $( '#vmail-success' ).value;
			form.accent = $( '#vmail-accent' ).value;
			form.captcha = $( '#vmail-captcha' ).checked;
		}

		function renderPreview() {
			var html = '<form class="velox-form" style="--vf-accent:' + escapeHtml( form.accent || '#2ab7f1' ) + '" onsubmit="return false">';
			form.fields.forEach( function ( f ) {
				var star = f.required ? ' <span class="velox-req">*</span>' : '';
				if ( f.type === 'consent' || f.type === 'checkbox' ) {
					html += '<label class="velox-form-consent"><input type="checkbox"><span>' + escapeHtml( f.label ) + star + '</span></label>';
				} else if ( f.type === 'textarea' ) {
					html += '<label class="velox-form-field"><span class="velox-form-label">' + escapeHtml( f.label ) + star + '</span><textarea rows="4" placeholder="' + escapeHtml( f.placeholder || '' ) + '"></textarea></label>';
				} else if ( f.type === 'select' ) {
					var opts = ( f.options || '' ).split( '\n' ).filter( Boolean ).map( function ( o ) { return '<option>' + escapeHtml( o.trim() ) + '</option>'; } ).join( '' );
					html += '<label class="velox-form-field"><span class="velox-form-label">' + escapeHtml( f.label ) + star + '</span><select><option>—</option>' + opts + '</select></label>';
				} else {
					html += '<label class="velox-form-field"><span class="velox-form-label">' + escapeHtml( f.label ) + star + '</span><input type="' + f.type + '" placeholder="' + escapeHtml( f.placeholder || '' ) + '"></label>';
				}
			} );
			html += '<button type="button" class="velox-form-submit">' + escapeHtml( form.submit_label || 'Send' ) + '</button></form>';
			preview.innerHTML = html;
		}

		// add field
		$( '#vmail-addfield' ).addEventListener( 'click', function () {
			var type = $( '#vmail-newtype' ).value;
			form.fields.push( { key: '', type: type, label: TYPES[ type ], required: false, placeholder: '', options: '' } );
			renderFields(); renderPreview();
		} );
		[ '#vmail-title', '#vmail-submit', '#vmail-success', '#vmail-accent', '#vmail-captcha' ].forEach( function ( sel ) {
			var el = $( sel );
			if ( el ) { el.addEventListener( 'input', function () { sync(); renderPreview(); } ); }
		} );

		$( '#vmail-save' ).addEventListener( 'click', function () {
			sync();
			var btn = $( '#vmail-save' );
			btn.disabled = true;
			api( 'form_save', { form: JSON.stringify( form ) } )
				.then( function ( r ) {
					toast( 'Form saved.' );
					setTimeout( function () {
						location.href = location.pathname + '?page=velox-utilities&tool=mail';
					}, 500 );
				} )
				.catch( function ( e ) { toast( e.message, 'error' ); btn.disabled = false; } );
		} );

		renderFields();
		renderEmails();
		renderPreview();
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

		function parseSlugs() {
			return ( slugsEl.value || '' )
				.split( /[\n,]+/ )
				.map( function ( s ) { return s.trim().toLowerCase(); } )
				.filter( Boolean );
		}
		function logLine( slug, state, msg ) {
			var row = document.createElement( 'div' );
			row.className = 'velox-install-row is-' + state;
			row.innerHTML = '<span class="velox-install-slug">' + slug + '</span><span class="velox-install-msg">' + msg + '</span>';
			log.appendChild( row );
			return row;
		}

		runBtn.addEventListener( 'click', function () {
			var slugs = parseSlugs();
			if ( ! slugs.length ) {
				toast( 'Add at least one plugin slug.', 'error' );
				return;
			}
			var activate = actEl.checked;
			log.hidden = false;
			log.innerHTML = '';
			runBtn.disabled = true;
			runBtn.textContent = 'Installing…';

			// Install one at a time so the user sees real progress and we avoid timeouts.
			var i = 0;
			function next() {
				if ( i >= slugs.length ) {
					runBtn.disabled = false;
					runBtn.textContent = 'Install all';
					toast( 'Done.' );
					return;
				}
				var slug = slugs[ i++ ];
				var row  = logLine( slug, 'pending', 'Installing…' );
				api( 'installer_install', { slug: slug, activate: activate ? '1' : 'false' } )
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
		} );

		saveBtn.addEventListener( 'click', function () {
			var slugs = parseSlugs();
			var name  = ( nameEl.value || '' ).trim();
			if ( ! name ) { toast( 'Name the blueprint first.', 'error' ); return; }
			if ( ! slugs.length ) { toast( 'Add some slugs to save.', 'error' ); return; }
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
		var srcEl  = $( '#velox-redir-source' );
		var tgtEl  = $( '#velox-redir-target' );
		var typeEl = $( '#velox-redir-type' );

		addBtn.addEventListener( 'click', function () {
			if ( ! srcEl.value.trim() ) { toast( 'Enter a source path.', 'error' ); return; }
			addBtn.disabled = true;
			api( 'redirect_add', { source: srcEl.value, target: tgtEl.value, type: typeEl.value } )
				.then( function ( r ) {
					if ( ! r.ok ) { toast( r.message || 'Could not add.', 'error' ); return; }
					toast( 'Redirect added.' );
					setTimeout( function () { location.reload(); }, 400 );
				} )
				.catch( function ( e ) { toast( e.message, 'error' ); } )
				.then( function () { addBtn.disabled = false; } );
		} );

		var redirList = $( '#velox-redir-list' );
		if ( redirList ) {
			redirList.addEventListener( 'click', function ( e ) {
				if ( ! e.target.classList.contains( 'velox-redir-del' ) ) { return; }
				var row = e.target.closest( '.velox-redir-row' );
				api( 'redirect_delete', { id: row.getAttribute( 'data-id' ) } )
					.then( function () { row.remove(); toast( 'Deleted.' ); } )
					.catch( function ( er ) { toast( er.message, 'error' ); } );
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
						.then( function () { row.remove(); } )
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
		var genBtn = $( '#velox-seo-smap-gen' );
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

	document.addEventListener( 'DOMContentLoaded', function () {
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
