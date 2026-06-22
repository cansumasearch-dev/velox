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

		/* quality slider live label */
		var qVal = $( '#velox-q-val' );
		if ( root && qVal ) {
			root.addEventListener( 'input', function () {
				qVal.textContent = root.value + '%';
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
		var check = $( '#velox-check-updates' );
		if ( ! btn && ! check ) {
			return;
		}

		if ( btn ) {
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
		}

		if ( check ) {
			check.addEventListener( 'click', function () {
				toast( 'Checking GitHub for the latest release…' );
				// Save token first so the updater can authenticate, then bounce
				// to the WordPress updates screen which forces a fresh check.
				var token = $( '[data-setting="gh_token"]' );
				saveSettings(
					token ? { gh_token: token.value } : {},
					'Saved — opening updates…'
				).then( function () {
					window.location.href = 'update-core.php?force-check=1';
				} );
			} );
		}
	}

	/* ----------------------------------------------------------------
	 * Boot
	 * ------------------------------------------------------------- */

	document.addEventListener( 'DOMContentLoaded', function () {
		initDashboard();
		initImages();
		initMedia();
		initPerformance();
		initDatabase();
		initSettings();
	} );
} )();
