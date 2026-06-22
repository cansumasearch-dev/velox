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
		var wrap = $( '#velox-dash-stats' );
		if ( ! wrap ) {
			return;
		}
		api( 'image_stats' )
			.then( function ( s ) {
				wrap.innerHTML = [
					stat( s.total, 'Images total' ),
					stat( s.done, 'Optimized' ),
					stat( s.pending, 'Pending' ),
					stat( bytes( s.saved_bytes ), 'Saved' ),
				].join( '' );
			} )
			.catch( function () {
				wrap.innerHTML = '';
			} );

		function stat( value, label ) {
			return (
				'<div class="velox-stat"><span class="velox-stat-num">' +
				escapeHtml( value ) +
				'</span><span class="velox-stat-label">' +
				escapeHtml( label ) +
				'</span></div>'
			);
		}
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

		/* quality slider live label + persist on release */
		var qVal = $( '#velox-q-val' );
		if ( root && qVal ) {
			root.addEventListener( 'input', function () {
				qVal.textContent = root.value + '%';
			} );
			root.addEventListener( 'change', function () {
				saveSettings( { webp_quality: root.value }, null );
			} );
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
						toast( ( d && d.message ) || 'Cache cleared.' );
					} )
					.catch( function ( e ) {
						toast( e.message );
					} )
					.then( function () {
						cb.disabled = false;
						cb.textContent = orig;
					} );
			} );
		} );

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

	document.addEventListener( 'DOMContentLoaded', function () {
		initDashboard();
		initImages();
		initLibrary();
		initMedia();
		initPerformance();
		initDatabase();
		initSettings();
	} );
} )();
