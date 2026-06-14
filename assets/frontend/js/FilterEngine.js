/**
 * CLEFA FilterEngine
 *
 * Manages the client-side state of the sidebar filter widget:
 *  - Collects filter values from inputs
 *  - Sends AJAX (REST) requests to /clefa/v1/filter
 *  - Replaces the results container HTML
 *  - Updates active filter chips bar
 *  - Syncs filter state to URL query params
 *  - Handles pagination (numbered, load-more, infinite)
 *  - Initialises dual-range sliders inside filter sections
 *  - Handles collapsible sections
 *
 * Depends on: nothing (vanilla JS, no jQuery required)
 */
( function () {
	'use strict';

	var DEBOUNCE_MS = 400;

	/* ------------------------------------------------------------------ */
	/* FilterEngine constructor                                               */
	/* ------------------------------------------------------------------ */

	function FilterEngine( widgetEl ) {
		this.widgetEl    = widgetEl;
		this.config      = this._parseConfig();
		this.formEl      = widgetEl.querySelector( '[data-clefa-filter-form]' );
		this.chipsBar    = widgetEl.querySelector( '[data-clefa-chips-bar]' );
		this.chipsWrap   = widgetEl.querySelector( '[data-clefa-chips]' );
		this.loadingEl   = widgetEl.querySelector( '[data-clefa-filter-loading]' );
		this.resultsEl   = this._findResultsEl();
		this._debounceTimer = null;
		this._abortCtrl     = null;
		this._page          = 1;
		this._restoring     = false;

		if ( ! this.formEl || ! this.config ) { return; }

		this._initDualRanges();
		this._initSingleRanges();
		this._initCollapsibles();
		this._bindInputs();
		this._bindActions();
		this._bindPagination();
		this._bindPopState();
		this._restoreFromUrl();
	}

	/* ------------------------------------------------------------------ */
	/* Config                                                                */
	/* ------------------------------------------------------------------ */

	FilterEngine.prototype._parseConfig = function () {
		var raw = this.widgetEl.getAttribute( 'data-clefa-filter-config' );
		if ( ! raw ) return null;
		try { return JSON.parse( raw ); } catch ( e ) { return null; }
	};

	FilterEngine.prototype._findResultsEl = function () {
		var targetId = ( this.config && this.config.resultsTarget ) ? this.config.resultsTarget : '';
		if ( targetId ) {
			var el = document.getElementById( targetId );
			if ( el ) return el;
		}
		// Fallback: next sibling with data-clefa-filter-results
		var next = this.widgetEl.nextElementSibling;
		while ( next ) {
			if ( next.hasAttribute( 'data-clefa-filter-results' ) ) return next;
			next = next.nextElementSibling;
		}
		return null;
	};

	/* ------------------------------------------------------------------ */
	/* Dual-range slider init (filter context)                               */
	/* ------------------------------------------------------------------ */

	FilterEngine.prototype._initDualRanges = function () {
		var self = this;
		this.formEl.querySelectorAll( '[data-clefa-range-dual]' ).forEach( function( wrap ) {
			var inputMin   = wrap.querySelector( '[data-clefa-range-dual-input="min"]' );
			var inputMax   = wrap.querySelector( '[data-clefa-range-dual-input="max"]' );
			var fill       = wrap.querySelector( '[data-clefa-range-dual-fill]' );
			var valMinEl   = wrap.querySelector( '[data-clefa-range-dual-value="min"]' );
			var valMaxEl   = wrap.querySelector( '[data-clefa-range-dual-value="max"]' );
			var hiddenMin  = wrap.querySelector( '[data-clefa-range-hidden="min"]' );
			var hiddenMax  = wrap.querySelector( '[data-clefa-range-hidden="max"]' );
			if ( ! inputMin || ! inputMax ) return;

			var sliderMin = parseFloat( wrap.getAttribute( 'data-min' ) ) || 0;
			var sliderMax = parseFloat( wrap.getAttribute( 'data-max' ) ) || 100;

			function update() {
				var a = parseFloat( inputMin.value );
				var b = parseFloat( inputMax.value );
				var pl = ( ( a - sliderMin ) / ( sliderMax - sliderMin ) ) * 100;
				var pr = ( ( b - sliderMin ) / ( sliderMax - sliderMin ) ) * 100;
				if ( fill ) { fill.style.left = pl + '%'; fill.style.width = ( pr - pl ) + '%'; }
				if ( valMinEl ) valMinEl.textContent = a;
				if ( valMaxEl ) valMaxEl.textContent = b;
				if ( hiddenMin ) hiddenMin.value = a;
				if ( hiddenMax ) hiddenMax.value = b;
			}

			inputMin.addEventListener( 'input', function() {
				if ( parseFloat( inputMin.value ) > parseFloat( inputMax.value ) ) inputMin.value = inputMax.value;
				update();
				self._scheduleRequest();
			} );
			inputMax.addEventListener( 'input', function() {
				if ( parseFloat( inputMax.value ) < parseFloat( inputMin.value ) ) inputMax.value = inputMin.value;
				update();
				self._scheduleRequest();
			} );
			update();
		} );
	};

	/* ------------------------------------------------------------------ */
	/* Single-handle range slider init (filter context)                     */
	/* ------------------------------------------------------------------ */

	FilterEngine.prototype._initSingleRanges = function () {
		var self = this;
		this.formEl.querySelectorAll( '[data-clefa-filter-range]' ).forEach( function( wrap ) {
			var input    = wrap.querySelector( '[data-clefa-filter-input]' );
			var fill     = wrap.querySelector( '[data-clefa-filter-range-fill]' );
			var valueEl  = wrap.querySelector( '[data-clefa-filter-range-value]' );
			if ( ! input ) return;

			var sliderMin = parseFloat( wrap.getAttribute( 'data-min' ) ) || 0;
			var sliderMax = parseFloat( wrap.getAttribute( 'data-max' ) ) || 100;

			function update() {
				var val = parseFloat( input.value );
				var pct = sliderMax > sliderMin ? ( ( val - sliderMin ) / ( sliderMax - sliderMin ) ) * 100 : 0;
				if ( fill ) { fill.style.width = pct + '%'; }
				if ( valueEl ) valueEl.textContent = val;
			}

			input.addEventListener( 'input', function() {
				update();
				self._scheduleRequest();
			} );
			update();
		} );
	};

	/* ------------------------------------------------------------------ */
	/* Collapsible sections                                                  */
	/* ------------------------------------------------------------------ */

	FilterEngine.prototype._initCollapsibles = function () {
		this.widgetEl.querySelectorAll( '.clefa-filter-section-collapsible' ).forEach( function( section ) {
			var toggle = section.querySelector( '.clefa-filter-section-toggle' );
			var body   = section.querySelector( '.clefa-filter-section-body' );
			if ( ! toggle || ! body ) return;

			toggle.addEventListener( 'click', function() {
				var expanded = toggle.getAttribute( 'aria-expanded' ) === 'true';
				toggle.setAttribute( 'aria-expanded', expanded ? 'false' : 'true' );
				body.style.display = expanded ? 'none' : '';
				section.toggleAttribute( 'data-clefa-collapsed', expanded );
			} );
		} );
	};

	/* ------------------------------------------------------------------ */
	/* Input binding                                                         */
	/* ------------------------------------------------------------------ */

	FilterEngine.prototype._bindInputs = function () {
		var self = this;
		var autoApply = this.config && this.config.autoApply !== false;

		// Checkboxes, radios, selects, date inputs, search
		this.formEl.addEventListener( 'change', function( e ) {
			var inp = e.target;
			if ( ! inp.hasAttribute( 'data-clefa-filter-input' ) &&
			     ! inp.hasAttribute( 'data-clefa-filter-date' ) ) return;
			if ( autoApply ) {
				self._page = 1;
				self._scheduleRequest();
			}
		} );

		// Debounced text/search input
		this.formEl.addEventListener( 'input', function( e ) {
			var inp = e.target;
			if ( inp.type !== 'search' && inp.type !== 'text' ) return;
			if ( ! inp.hasAttribute( 'data-clefa-filter-input' ) ) return;
			if ( autoApply ) {
				self._page = 1;
				self._scheduleRequest();
			}
		} );
	};

	FilterEngine.prototype._bindActions = function () {
		var self = this;

		// Apply button (non-auto-apply mode)
		this.widgetEl.addEventListener( 'click', function( e ) {
			if ( e.target.closest( '[data-clefa-filter-apply]' ) ) {
				self._page = 1;
				self._sendRequest();
			}
			// Reset button(s)
			if ( e.target.closest( '[data-clefa-filter-reset]' ) ) {
				self._resetFilters();
			}
			// Mobile drawer open toggle
			if ( e.target.closest( '[data-clefa-filter-mobile-open]' ) ) {
				self._openDrawer();
			}
			// Mobile drawer close button
			if ( e.target.closest( '[data-clefa-filter-mobile-close]' ) ) {
				self._closeDrawer();
			}
		} );

		// Backdrop click closes drawer
		var backdrop = this.widgetEl.querySelector( '[data-clefa-filter-backdrop]' );
		if ( backdrop ) {
			backdrop.addEventListener( 'click', function() {
				self._closeDrawer();
			} );
		}

		// Chip remove button
		if ( this.chipsWrap ) {
			this.chipsWrap.addEventListener( 'click', function( e ) {
				var btn = e.target.closest( '[data-clefa-chip-remove]' );
				if ( ! btn ) return;
				var sid = btn.getAttribute( 'data-clefa-chip-remove' );
				self._clearSection( sid );
				self._page = 1;
				self._sendRequest();
			} );
		}
	};

	FilterEngine.prototype._openDrawer = function () {
		this.widgetEl.setAttribute( 'data-clefa-mobile-open', '1' );
		document.body.style.overflow = 'hidden';
		var toggleBtn = this.widgetEl.querySelector( '[data-clefa-filter-mobile-open]' );
		if ( toggleBtn ) { toggleBtn.setAttribute( 'aria-expanded', 'true' ); }
	};

	FilterEngine.prototype._closeDrawer = function () {
		this.widgetEl.removeAttribute( 'data-clefa-mobile-open' );
		document.body.style.overflow = '';
		var toggleBtn = this.widgetEl.querySelector( '[data-clefa-filter-mobile-open]' );
		if ( toggleBtn ) { toggleBtn.setAttribute( 'aria-expanded', 'false' ); }
	};

	/* ------------------------------------------------------------------ */
	/* Pagination                                                            */
	/* ------------------------------------------------------------------ */

	FilterEngine.prototype._bindPagination = function () {
		var self = this;
		var results = this.resultsEl;
		if ( ! results ) return;

		results.addEventListener( 'click', function( e ) {
			var btn = e.target.closest( '[data-clefa-page]' );
			if ( ! btn ) return;
			self._page = parseInt( btn.getAttribute( 'data-clefa-page' ), 10 ) || 1;
			self._sendRequest( false );
		} );

		// Load More — append posts rather than replace
		results.addEventListener( 'click', function( e ) {
			var btn = e.target.closest( '[data-clefa-load-more]' );
			if ( ! btn ) return;
			self._page = parseInt( btn.getAttribute( 'data-clefa-page' ), 10 ) || 1;
			self._sendRequest( true /* append */ );
		} );
	};

	/* ------------------------------------------------------------------ */
	/* URL sync                                                              */
	/* ------------------------------------------------------------------ */

	FilterEngine.prototype._restoreFromUrl = function () {
		if ( ! this.config || ! this.config.syncUrl ) return;
		var search = window.location.search;
		if ( ! search || search.indexOf( 'clefa_filter' ) === -1 ) return;
		this._restoring = true;
		this._sendRequest();
		this._restoring = false;
	};

	FilterEngine.prototype._bindPopState = function () {
		var self = this;
		if ( ! this.config || ! this.config.syncUrl ) return;
		window.addEventListener( 'popstate', function( e ) {
			// Only act if the state carries our marker
			if ( e.state && e.state.clefaFilter === self.config.widgetId ) {
				self._restoring = true;
				self._sendRequest();
				self._restoring = false;
			}
		} );
	};

	FilterEngine.prototype._syncUrl = function ( urlParams ) {
		if ( ! this.config || ! this.config.syncUrl ) return;
		var base = window.location.pathname + window.location.search;
		// Remove old clefa_ params
		base = base.replace( /[?&]clefa_[^&]*/g, '' ).replace( /^([^?]*)&/, '$1?' );
		var sep   = base.indexOf( '?' ) !== -1 ? '&' : '?';
		var url   = urlParams ? base + sep + urlParams : base;
		var state = { clefaFilter: this.config.widgetId, page: this._page };
		history.pushState( state, '', url );
	};

	/* ------------------------------------------------------------------ */
	/* Collect state                                                         */
	/* ------------------------------------------------------------------ */

	FilterEngine.prototype._collectState = function () {
		var state = {};
		if ( ! this.formEl ) return state;

		// Checkboxes
		this.formEl.querySelectorAll( 'input[type="checkbox"][data-clefa-filter-input]:checked' ).forEach( function( el ) {
			var sid = el.getAttribute( 'data-clefa-filter-input' );
			if ( ! state[ sid ] ) state[ sid ] = [];
			state[ sid ].push( el.value );
		} );

		// Radios
		this.formEl.querySelectorAll( 'input[type="radio"][data-clefa-filter-input]:checked' ).forEach( function( el ) {
			state[ el.getAttribute( 'data-clefa-filter-input' ) ] = el.value;
		} );

		// Selects
		this.formEl.querySelectorAll( 'select[data-clefa-filter-input]' ).forEach( function( el ) {
			if ( el.value ) {
				state[ el.getAttribute( 'data-clefa-filter-input' ) ] = el.value;
			}
		} );

		// Search / text inputs
		this.formEl.querySelectorAll( 'input[type="search"][data-clefa-filter-input], input[type="text"][data-clefa-filter-input]' ).forEach( function( el ) {
			if ( el.value.trim() ) {
				state[ el.getAttribute( 'data-clefa-filter-input' ) ] = el.value.trim();
			}
		} );

		// Range dual (hidden min/max)
		this.formEl.querySelectorAll( '[data-clefa-range-dual][data-clefa-filter-input]' ).forEach( function( wrap ) {
			var sid     = wrap.getAttribute( 'data-clefa-filter-input' );
			var minEl   = wrap.querySelector( '[data-clefa-range-hidden="min"]' );
			var maxEl   = wrap.querySelector( '[data-clefa-range-hidden="max"]' );
			var sliderMin = parseFloat( wrap.getAttribute( 'data-min' ) ) || 0;
			var sliderMax = parseFloat( wrap.getAttribute( 'data-max' ) ) || 100;
			var minVal = parseFloat( ( minEl && minEl.value ) || sliderMin );
			var maxVal = parseFloat( ( maxEl && maxEl.value ) || sliderMax );
			// Only add if not at default bounds
			if ( minVal > sliderMin || maxVal < sliderMax ) {
				state[ sid ] = { min: minVal, max: maxVal };
			}
		} );

		// Date range
		this.formEl.querySelectorAll( '[data-clefa-filter-input]' ).forEach( function( wrap ) {
			if ( ! wrap.hasAttribute( 'data-clefa-filter-input' ) || wrap.tagName !== 'DIV' ) return;
			var sid  = wrap.getAttribute( 'data-clefa-filter-input' );
			var from = wrap.querySelector( '[data-clefa-filter-date="from"]' );
			var to   = wrap.querySelector( '[data-clefa-filter-date="to"]' );
			if ( from || to ) {
				var v = {};
				if ( from && from.value ) v.from = from.value;
				if ( to   && to.value   ) v.to   = to.value;
				if ( Object.keys( v ).length ) state[ sid ] = v;
			}
		} );

		return state;
	};

	/* ------------------------------------------------------------------ */
	/* Reset                                                                 */
	/* ------------------------------------------------------------------ */

	FilterEngine.prototype._resetFilters = function () {
		if ( ! this.formEl ) return;
		this.formEl.reset();
		// Reset dual ranges to bounds
		this.formEl.querySelectorAll( '[data-clefa-range-dual]' ).forEach( function( wrap ) {
			var inputMin  = wrap.querySelector( '[data-clefa-range-dual-input="min"]' );
			var inputMax  = wrap.querySelector( '[data-clefa-range-dual-input="max"]' );
			var hiddenMin = wrap.querySelector( '[data-clefa-range-hidden="min"]' );
			var hiddenMax = wrap.querySelector( '[data-clefa-range-hidden="max"]' );
			var sliderMin = wrap.getAttribute( 'data-min' ) || 0;
			var sliderMax = wrap.getAttribute( 'data-max' ) || 100;
			if ( inputMin ) inputMin.value = sliderMin;
			if ( inputMax ) inputMax.value = sliderMax;
			if ( hiddenMin ) hiddenMin.value = sliderMin;
			if ( hiddenMax ) hiddenMax.value = sliderMax;
			// Trigger update for fill bar
			var valMinEl = wrap.querySelector( '[data-clefa-range-dual-value="min"]' );
			var valMaxEl = wrap.querySelector( '[data-clefa-range-dual-value="max"]' );
			if ( valMinEl ) valMinEl.textContent = sliderMin;
			if ( valMaxEl ) valMaxEl.textContent = sliderMax;
			var fill = wrap.querySelector( '[data-clefa-range-dual-fill]' );
			if ( fill ) { fill.style.left = '0%'; fill.style.width = '100%'; }
		} );
		this._page = 1;
		this._sendRequest();
	};

	FilterEngine.prototype._clearSection = function ( sid ) {
		if ( ! this.formEl ) return;
		// Checkboxes/radios
		this.formEl.querySelectorAll( '[data-clefa-filter-input="' + sid + '"]' ).forEach( function( el ) {
			if ( el.type === 'checkbox' || el.type === 'radio' ) {
				el.checked = false;
			} else if ( el.tagName === 'SELECT' ) {
				el.value = '';
			} else {
				el.value = '';
			}
		} );
	};

	/* ------------------------------------------------------------------ */
	/* Request                                                               */
	/* ------------------------------------------------------------------ */

	FilterEngine.prototype._scheduleRequest = function () {
		var self = this;
		clearTimeout( self._debounceTimer );
		self._debounceTimer = setTimeout( function() { self._sendRequest(); }, DEBOUNCE_MS );
	};

	FilterEngine.prototype._sendRequest = function ( appendMode ) {
		var self       = this;
		var cfg        = this.config;
		if ( ! cfg ) return;

		var filterState = this._collectState();

		// Build widget config payload
		var widgetConfig = {
			filter_post_type  : cfg.postType || 'post',
			posts_per_page    : cfg.postsPerPage || 9,
			filter_sections   : cfg.sections || [],
			pagination_type   : cfg.paginationType || 'numbered',
			no_results_text   : cfg.noResultsText || '',
		};

		var payload = {
			config  : widgetConfig,
			filter  : filterState,
			page    : self._page,
			orderby : 'date',
			order   : 'DESC',
		};

		// Abort previous request
		if ( self._abortCtrl ) { self._abortCtrl.abort(); }
		self._abortCtrl = typeof AbortController !== 'undefined' ? new AbortController() : null;

		self._setLoading( true );

		fetch( cfg.restUrl, {
			method  : 'POST',
			headers : { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
			body    : JSON.stringify( payload ),
			signal  : self._abortCtrl ? self._abortCtrl.signal : undefined,
		} )
		.then( function( r ) { return r.json(); } )
		.then( function( data ) {
			self._setLoading( false );
			if ( ! data.success ) return;
			self._updateResults( data, appendMode );
			self._updateChips( data.active_filters || [] );
			self._syncUrl( data.url_params || '' );
		} )
		.catch( function( err ) {
			if ( err.name !== 'AbortError' ) { self._setLoading( false ); }
		} );
	};

	/* ------------------------------------------------------------------ */
	/* DOM updates                                                           */
	/* ------------------------------------------------------------------ */

	FilterEngine.prototype._updateResults = function ( data, appendMode ) {
		if ( ! this.resultsEl ) return;

		var postsArea = this.resultsEl.querySelector( '[data-clefa-posts]' ) || this.resultsEl;
		var paginArea = this.resultsEl.querySelector( '[data-clefa-pagination-wrap]' );

		if ( appendMode ) {
			postsArea.insertAdjacentHTML( 'beforeend', data.posts_html || '' );
		} else {
			postsArea.innerHTML = data.posts_html || '';
		}

		if ( paginArea ) {
			paginArea.innerHTML = data.pagination_html || '';
		} else if ( data.pagination_html ) {
			postsArea.insertAdjacentHTML( 'afterend', data.pagination_html );
		}

		// Dispatch event for third-party integrations
		var evt = new CustomEvent( 'clefa:filter:updated', {
			bubbles : true,
			detail  : { found: data.found_posts, page: this._page },
		} );
		this.resultsEl.dispatchEvent( evt );
	};

	FilterEngine.prototype._updateChips = function ( chips ) {
		if ( ! this.chipsBar || ! this.chipsWrap ) return;
		if ( ! chips.length ) {
			this.chipsBar.style.display = 'none';
			this.chipsWrap.innerHTML = '';
			return;
		}
		this.chipsBar.style.display = '';
		var html = '';
		chips.forEach( function( chip ) {
			html += '<span class="clefa-filter-chip">'
				+ '<span class="clefa-filter-chip-label">' + escHtml( chip.label ) + ': ' + escHtml( chip.value_label ) + '</span>'
				+ '<button type="button" class="clefa-filter-chip-remove" data-clefa-chip-remove="' + escAttr( chip.section_id ) + '" aria-label="Remove filter">&#x2715;</button>'
				+ '</span>';
		} );
		this.chipsWrap.innerHTML = html;
	};

	FilterEngine.prototype._setLoading = function ( loading ) {
		if ( this.loadingEl ) {
			this.loadingEl.style.display = loading ? '' : 'none';
			this.loadingEl.setAttribute( 'aria-hidden', loading ? 'false' : 'true' );
		}
		if ( this.resultsEl ) {
			this.resultsEl.setAttribute( 'data-clefa-loading', loading ? '1' : '0' );
		}
	};

	/* ------------------------------------------------------------------ */
	/* Utilities                                                             */
	/* ------------------------------------------------------------------ */

	function escHtml( str ) {
		return String( str ).replace( /&/g, '&amp;' ).replace( /</g, '&lt;' ).replace( />/g, '&gt;' ).replace( /"/g, '&quot;' );
	}
	function escAttr( str ) { return escHtml( str ); }

	/* ------------------------------------------------------------------ */
	/* Auto-init                                                             */
	/* ------------------------------------------------------------------ */

	function initAll() {
		document.querySelectorAll( '[data-clefa-filter-widget]' ).forEach( function( el ) {
			if ( ! el._clefaFilterEngine ) {
				el._clefaFilterEngine = new FilterEngine( el );
			}
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', initAll );
	} else {
		initAll();
	}

	window.CLEFAFilterEngine = FilterEngine;
	window.CLEFAInitFilters  = initAll;

} () );
