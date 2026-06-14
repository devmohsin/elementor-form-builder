/**
 * CLEFA FormEngine
 * Main frontend controller. Initialises sub-modules and handles submission.
 */
( function () {
	'use strict';

	window.CLEFA = window.CLEFA || {};

	/* ------------------------------------------------------------------ */
	/* helpers                                                               */
	/* ------------------------------------------------------------------ */

	function dispatch( el, name, detail ) {
		if ( window.CLEFAEventDispatcher ) {
			window.CLEFAEventDispatcher.dispatch( name, detail, el );
		} else {
			el.dispatchEvent( new CustomEvent( name, { bubbles: true, cancelable: true, detail: detail || {} } ) );
		}
	}

	function collectFormData( formEl ) {
		var data = {};
		var inputs = formEl.querySelectorAll( '[data-clefa-input]:not([disabled])' );
		inputs.forEach( function ( input ) {
			var id = input.getAttribute( 'data-clefa-field-id' );
			if ( ! id ) return;

			var wrapper = input.closest( '[data-clefa-field]' );
			if ( wrapper && wrapper.getAttribute( 'data-clefa-visible' ) === '0' ) return;

			if ( input.type === 'checkbox' ) {
				if ( ! data[ id ] ) data[ id ] = [];
				if ( input.checked ) data[ id ].push( input.value );
			} else if ( input.type === 'radio' ) {
				if ( input.checked ) data[ id ] = input.value;
			} else if ( input.tagName === 'SELECT' && input.multiple ) {
				data[ id ] = Array.from( input.selectedOptions ).map( function ( o ) { return o.value; } );
			} else if ( input.type === 'file' ) {
				data[ id ] = input.value ? input.value : '';
			} else {
				data[ id ] = input.value;
			}
		} );
		return data;
	}

	function setFormState( formEl, state ) {
		formEl.setAttribute( 'data-clefa-state', state );
		var submitBtn = formEl.querySelector( '[data-clefa-submit]' );
		if ( submitBtn ) {
			submitBtn.disabled = ( state === 'submitting' );
		}
	}

	function showFormMessage( formEl, message, type ) {
		var msgEl = formEl.querySelector( '[data-clefa-message]' );
		if ( ! msgEl ) return;
		msgEl.innerHTML   = message;
		msgEl.setAttribute( 'data-clefa-message-type', type );
		msgEl.style.display = '';
		msgEl.scrollIntoView( { behavior: 'smooth', block: 'nearest' } );

		// Wire dismiss button inside the rendered notice template
		var dismissBtn = msgEl.querySelector( '[data-clefa-notice-dismiss]' );
		if ( dismissBtn ) {
			dismissBtn.addEventListener( 'click', function () {
				msgEl.style.display = 'none';
			} );
		}
	}

	function hideFormMessage( formEl ) {
		var msgEl = formEl.querySelector( '[data-clefa-message]' );
		if ( msgEl ) msgEl.style.display = 'none';
	}

	/* ------------------------------------------------------------------ */
	/* password toggle                                                       */
	/* ------------------------------------------------------------------ */

	function initPasswordToggles( formEl ) {
		formEl.addEventListener( 'click', function ( e ) {
			var btn = e.target.closest( '[data-clefa-password-toggle]' );
			if ( ! btn ) return;
			var wrap  = btn.closest( '[data-clefa-password-wrap]' );
			var input = wrap && wrap.querySelector( 'input' );
			if ( ! input ) return;
			var isHidden = input.type === 'password';
			input.type   = isHidden ? 'text' : 'password';
			btn.setAttribute( 'aria-pressed', isHidden ? 'true' : 'false' );
			var showEl = btn.querySelector( '[data-clefa-pw-show]' );
			var hideEl = btn.querySelector( '[data-clefa-pw-hide]' );
			if ( showEl ) showEl.style.display = isHidden ? 'none' : '';
			if ( hideEl ) hideEl.style.display = isHidden ? ''     : 'none';
		} );
	}

	/* ------------------------------------------------------------------ */
	/* character counter                                                     */
	/* ------------------------------------------------------------------ */

	function initCharCounters( formEl ) {
		formEl.addEventListener( 'input', function ( e ) {
			var input = e.target.closest( 'textarea' );
			if ( ! input ) return;
			var wrapper = input.closest( '[data-clefa-field]' );
			if ( ! wrapper ) return;
			var counter = wrapper.querySelector( '[data-clefa-char-count]' );
			if ( ! counter ) return;
			var current = counter.querySelector( '[data-clefa-char-current]' );
			if ( current ) current.textContent = input.value.length;
		} );
	}

	/* ------------------------------------------------------------------ */
	/* range output                                                          */
	/* ------------------------------------------------------------------ */

	function initRangeOutputs( formEl ) {
		// Sync fill gradient on page load for all single-range inputs
		formEl.querySelectorAll( 'input[type="range"].clefa-range' ).forEach( function( input ) {
			updateRangeFill( input );
		} );

		formEl.addEventListener( 'input', function ( e ) {
			if ( e.target.type !== 'range' ) return;
			var id  = e.target.id;
			var out = id && formEl.querySelector( 'output[for="' + id + '"][data-clefa-range-output]' );
			if ( out ) out.textContent = e.target.value;
			updateRangeFill( e.target );
		} );
	}

	function updateRangeFill( input ) {
		var min = parseFloat( input.min ) || 0;
		var max = parseFloat( input.max ) || 100;
		var val = parseFloat( input.value );
		var pct = max > min ? ( ( val - min ) / ( max - min ) ) * 100 : 0;
		input.style.setProperty( '--clefa-range-pct', pct.toFixed( 2 ) + '%' );
	}

	/* ------------------------------------------------------------------ */
	/* Span token replacement (Section 21.3)                                */
	/* Live-replace {field_id} tokens in HTML blocks as users type          */
	/* ------------------------------------------------------------------ */

	function initSpanReplacements( formEl ) {
		// Populate tokens from current input values on load
		function syncAll() {
			formEl.querySelectorAll( '[data-clefa-token]' ).forEach( function ( span ) {
				var tokenId = span.getAttribute( 'data-clefa-token' );
				var inputs  = formEl.querySelectorAll( '[data-clefa-field-id="' + tokenId + '"]' );
				if ( ! inputs.length ) return;
				var first   = inputs[ 0 ];
				var val;
				if ( first.type === 'checkbox' || first.type === 'radio' ) {
					var checked = Array.from( inputs ).filter( function ( i ) { return i.checked; } ).map( function ( i ) { return i.value; } );
					val = checked.join( ', ' );
				} else if ( first.tagName === 'SELECT' && first.multiple ) {
					val = Array.from( first.selectedOptions ).map( function ( o ) { return o.text; } ).join( ', ' );
				} else {
					val = first.value;
				}
				span.textContent = val || ( '{' + tokenId + '}' );
			} );
		}

		formEl.addEventListener( 'input',  syncAll );
		formEl.addEventListener( 'change', syncAll );
		syncAll();
	}

	/* ------------------------------------------------------------------ */
	/* nonce refresh                                                         */
	/* ------------------------------------------------------------------ */

	function refreshNonce( restUrl, nonce ) {
		return fetch( restUrl + '/refresh-nonce', {
			method:  'GET',
			headers: { 'X-WP-Nonce': nonce },
		} )
		.then( function ( r ) { return r.json(); } )
		.then( function ( data ) { return data.nonce || nonce; } )
		.catch( function () { return nonce; } );
	}

	/* ------------------------------------------------------------------ */
	/* FormEngine constructor                                                */
	/* ------------------------------------------------------------------ */

	function FormEngine( formEl ) {
		this.formEl        = formEl;
		this.formId        = parseInt( formEl.getAttribute( 'data-clefa-form-id' ), 10 ) || 0;
		this.instanceId    = formEl.getAttribute( 'data-clefa-instance' ) || '';
		this.config        = this._parseConfig();
		this.restUrl       = ( window.clefaFrontend && window.clefaFrontend.restUrl ) || '';
		this.nonce         = ( window.clefaFrontend && window.clefaFrontend.nonce )   || '';
		this.refreshNonce  = ( window.clefaFrontend && window.clefaFrontend.refreshNonce ) !== false;
		this.persistDraft  = formEl.getAttribute( 'data-clefa-persist-draft' ) === '1';
		this._draftKey     = 'clefa_draft_' + this.formId;
		this._interactionCount = 0;

		this.condEngine  = null;
		this.validEngine = null;
		this.stepRouter  = null;
		this.liveCheck   = null;

		this._init();
	}

	FormEngine.prototype._parseConfig = function () {
		var raw = this.formEl.getAttribute( 'data-clefa-config' );
		if ( ! raw ) return { steps: [] };
		try { return JSON.parse( raw ); } catch ( e ) { return { steps: [] }; }
	};

	FormEngine.prototype._init = function () {
		var self = this;

		dispatch( self.formEl, 'clefa:form:init', { formId: self.formId } );

		initPasswordToggles( self.formEl );
		initCharCounters(    self.formEl );
		initDualRanges(      self.formEl );
		initRepeaters(       self.formEl );		initRangeOutputs(    self.formEl );
		initSpanReplacements( self.formEl );

		var hasConditions = self._formHasConditions();
		var isMultiStep   = ( self.config.steps || [] ).length > 1;

		// Dispatch clefa:field:changed on any input/change inside the form
		self.formEl.addEventListener( 'change', function ( e ) {
			if ( e.target.hasAttribute( 'data-clefa-input' ) ) {
				dispatch( self.formEl, 'clefa:field:changed', {
					formId : self.formId,
					fieldId: e.target.getAttribute( 'data-clefa-field-id' ),
					value  : e.target.type === 'checkbox' ? e.target.checked : e.target.value,
				} );
			}
		} );

		if ( hasConditions && window.CLEFA.ConditionEngine ) {
			self.condEngine = new CLEFA.ConditionEngine( self.formEl, self.config );
			self.condEngine.init();
		}

		if ( window.CLEFA.ValidationEngine ) {
			self.validEngine = new CLEFA.ValidationEngine( self.formEl, self.config );
			self.validEngine.init();
		}

		// Initialize step router if: config has >1 steps OR the DOM has [data-clefa-next]
		// buttons. The second check guards against stale feature_map not loading the script.
		var domHasSteps = !! self.formEl.querySelector( '[data-clefa-next]' );
		if ( ( isMultiStep || domHasSteps ) && window.CLEFA.StepRouter ) {
			self.stepRouter = new CLEFA.StepRouter( self.formEl, self.config, self.validEngine );
			self.stepRouter.init();
		}

		// Init live-check manager if any field uses it
		if ( window.CLEFA.LiveCheckManager && self.formEl.querySelector( '[data-clefa-live-check]' ) ) {
			self.liveCheck = new CLEFA.LiveCheckManager( self.formEl, self.config, self.restUrl, self.nonce );
			self.liveCheck.init();
		}

		// Init Select2 on tagged selects (requires Select2 + jQuery to be loaded)
		var select2Els = self.formEl.querySelectorAll( '[data-clefa-select2]' );
		if ( select2Els.length && window.jQuery && jQuery.fn.select2 ) {
			select2Els.forEach( function( el ) {
				var jEl      = jQuery( el );
				var ajaxSrc  = el.getAttribute( 'data-clefa-select2-ajax' );  // e.g. "posts:post" or "taxonomy:category"
				var minInput = parseInt( el.getAttribute( 'data-clefa-select2-min-input' ), 10 ) || 0;
				var opts = {
					width       : '100%',
					placeholder : el.getAttribute( 'data-clefa-select2-placeholder' ) || '',
					allowClear  : ! el.hasAttribute( 'multiple' ),
				};
				var maxItems = parseInt( el.getAttribute( 'data-clefa-select2-max' ), 10 );
				if ( maxItems > 0 ) { opts.maximumSelectionLength = maxItems; }

				// AJAX source configuration (Section 31.1)
				if ( ajaxSrc && self.restUrl ) {
					var parts    = ajaxSrc.split( ':' );
					var source   = parts[0] || 'posts';
					var subParam = parts[1] || '';
					var extraKey = ( source === 'taxonomy' ) ? 'taxonomy' : 'post_type';

					opts.minimumInputLength = minInput;
					opts.ajax = {
						url      : self.restUrl + '/select2',
						type     : 'GET',
						dataType : 'json',
						delay    : 300,
						headers  : { 'X-WP-Nonce': self.nonce },
						data     : function ( params ) {
							var q = {
								source   : source,
								search   : params.term || '',
								page     : params.page  || 1,
								per_page : 20,
								form_id  : self.formId,
								field_id : el.getAttribute( 'data-clefa-field-id' ) || '',
							};
							if ( subParam ) { q[ extraKey ] = subParam; }
							return q;
						},
						processResults : function ( data, params ) {
							return {
								results    : data.results || [],
								pagination : { more: ( data.pagination || {} ).more || false },
							};
						},
					};
				}

				jEl.select2( opts );
			} );
		}

		self._bindSubmit();

		// Draft persistence
		// Interaction count — track user activity for anti-spam checks
		self.formEl.addEventListener( 'input', function() {
			self._interactionCount++;
		} );

		if ( self.persistDraft ) {
			self._restoreDraft();
			self.formEl.addEventListener( 'input', function() {
				self._saveDraft();
			} );
			self.formEl.addEventListener( 'change', function() {
				self._saveDraft();
			} );
		}

		dispatch( self.formEl, 'clefa:form:ready', { formId: self.formId, instance: self } );
	};

	FormEngine.prototype._formHasConditions = function () {
		var steps = this.config.steps || [];
		for ( var i = 0; i < steps.length; i++ ) {
			var fields = steps[ i ].fields || [];
			for ( var j = 0; j < fields.length; j++ ) {
				if ( fields[ j ].conditions && fields[ j ].conditions.length ) return true;
			}
		}
		return false;
	};

	FormEngine.prototype._bindSubmit = function () {
		var self      = this;
		var submitEl  = self.formEl.querySelector( '[data-clefa-submit]' );
		// Bind submit on the actual <form> element (not the outer wrapper div) so
		// the event is caught before the browser performs native form submission.
		var innerForm = self.formEl.querySelector( '[data-clefa-form-inner]' ) || self.formEl;

		if ( submitEl ) {
			submitEl.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				self._handleSubmit();
			} );
		}

		// Catches Enter-key submission and any path the click handler misses.
		innerForm.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			self._handleSubmit();
		} );

		// Save Draft button (per step, via buttons.php)
		self.formEl.addEventListener( 'click', function ( e ) {
			if ( e.target.closest( '[data-clefa-save-draft]' ) ) {
				e.preventDefault();
				self._saveDraft();
				dispatch( self.formEl, 'clefa:form:draft-saved', { formId: self.formId } );
			}
		} );
	};

	FormEngine.prototype._handleSubmit = function () {
		var self = this;

		// If multi-step and not on last step, delegate to StepRouter
		if ( self.stepRouter && ! self.stepRouter.isLastStep() ) {
			self.stepRouter.goNext();
			return;
		}

		// Validate all (or current step)
		if ( self.validEngine ) {
			var errors = self.validEngine.validateAll();
			if ( Object.keys( errors ).length ) {
				self.validEngine.showServerErrors( errors );
				showFormMessage( self.formEl, 'Please correct the errors in the form before submitting.', 'error' );
				dispatch( self.formEl, 'clefa:form:validation-failed', { errors: errors } );
				return;
			}
		}

		var data = collectFormData( self.formEl );
		dispatch( self.formEl, 'clefa:form:before-submit', { data: data } );

		setFormState( self.formEl, 'submitting' );
		hideFormMessage( self.formEl );

		var doSubmit = function ( nonce ) {
			fetch( self.restUrl + '/submit', {
				method:  'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce':   nonce,
				},
				body: JSON.stringify( {
					form_id:     self.formId,
					instance_id: self.instanceId,
					data:        data,
					_clefa_ic:   self._interactionCount,
				} ),
			} )
			.then( function ( r ) { return r.json().then( function ( body ) { return { status: r.status, body: body }; } ); } )
			.then( function ( res ) { self._handleResponse( res.status, res.body, data ); } )
			.catch( function ( err ) {
				setFormState( self.formEl, 'idle' );
				showFormMessage( self.formEl, 'A network error occurred. Please try again.', 'error' );
				dispatch( self.formEl, 'clefa:form:error', { error: err } );
			} );
		};

		if ( self.refreshNonce ) {
			refreshNonce( self.restUrl, self.nonce ).then( doSubmit );
		} else {
			doSubmit( self.nonce );
		}
	};

	FormEngine.prototype._handleResponse = function ( status, body, sentData ) {
		var self = this;
		setFormState( self.formEl, 'idle' );

		if ( status === 422 && body.data && body.data.errors ) {
			if ( self.validEngine ) {
				self.validEngine.showServerErrors( body.data.errors );
			}
			showFormMessage( self.formEl, body.message || 'Please correct the errors in the form before submitting.', 'error' );
			dispatch( self.formEl, 'clefa:form:validation-failed', { errors: body.data.errors } );
			return;
		}

		if ( ! body.success ) {
			var errMsg = ( body.message ) || 'An error occurred. Please try again.';
			showFormMessage( self.formEl, errMsg, 'error' );
			dispatch( self.formEl, 'clefa:form:error', { message: errMsg } );
			return;
		}

		dispatch( self.formEl, 'clefa:form:success', { response: body, submittedData: sentData } );

		if ( self.persistDraft ) { self._clearDraft(); }

		if ( body.redirect_url ) {
			dispatch( self.formEl, 'clefa:redirect:before', { url: body.redirect_url, formId: self.formId } );
			// Dev Hub test iframe: never navigate away from the runner document.
			if ( ! window.CLEFA_TESTING ) {
				window.location.href = body.redirect_url;
			}
			return;
		}

		var successMsg = body.message_html || body.message || 'Form submitted successfully.';
		var showForm   = self.formEl.getAttribute( 'data-clefa-hide-on-success' ) !== '1';

		if ( ! showForm ) {
			self.formEl.setAttribute( 'data-clefa-state', 'success' );
		}

		showFormMessage( self.formEl, successMsg, 'success' );

		if ( self.formEl.getAttribute( 'data-clefa-reset-on-success' ) !== '0' ) {
			self.formEl.querySelectorAll( 'input:not([type="hidden"]), textarea, select' ).forEach( function ( el ) {
				if ( el.type === 'checkbox' || el.type === 'radio' ) {
					el.checked = false;
				} else {
					el.value = el.defaultValue || '';
				}
			} );
			if ( self.stepRouter ) self.stepRouter._showStep( 0 );
			if ( self.condEngine ) self.condEngine.evaluateAll();
		}
	};

	/* ------------------------------------------------------------------ */
	/* Repeater field helpers                                               */
	/* ------------------------------------------------------------------ */

	function initRepeaters( scope ) {
		scope.querySelectorAll( '[data-clefa-repeater]' ).forEach( function( wrap ) {
			var fieldId  = wrap.getAttribute( 'data-clefa-repeater' );
			var minRows  = parseInt( wrap.getAttribute( 'data-clefa-repeater-min' ), 10 ) || 0;
			var maxRows  = parseInt( wrap.getAttribute( 'data-clefa-repeater-max' ), 10 ) || 0;
			var rowsWrap = wrap.querySelector( '[data-clefa-repeater-rows]' );
			var addBtn   = wrap.querySelector( '[data-clefa-repeater-add]' );
			var countInp = wrap.querySelector( '[data-clefa-repeater-count]' );
			var tplEl    = wrap.querySelector( '[data-clefa-repeater-template]' );

			if ( ! rowsWrap || ! tplEl ) { return; }

			function getRows() { return rowsWrap.querySelectorAll( '[data-clefa-repeater-row]' ); }

			function updateState() {
				var rows = getRows();
				var count = rows.length;
				if ( countInp ) { countInp.value = count; }
				rows.forEach( function( row, idx ) {
					row.setAttribute( 'data-clefa-row-index', idx );
					row.querySelectorAll( '[name]' ).forEach( function( inp ) {
						inp.setAttribute( 'name', inp.getAttribute( 'name' ).replace( /\[__ROW_INDEX__\]|\[\d+\](?=\[)/, '[' + idx + ']' ) );
					} );
					var rmBtn = row.querySelector( '[data-clefa-repeater-remove]' );
					if ( rmBtn ) { rmBtn.style.display = count <= minRows ? 'none' : ''; }
				} );
				if ( addBtn ) { addBtn.disabled = maxRows > 0 && count >= maxRows; }
			}

			if ( addBtn ) {
				addBtn.addEventListener( 'click', function() {
					var rows = getRows();
					if ( maxRows > 0 && rows.length >= maxRows ) { return; }
					var newIndex = rows.length;
					var html = tplEl.innerHTML.replace( /__ROW_INDEX__/g, String( newIndex ) );
					var temp = document.createElement( 'div' );
					temp.innerHTML = html;
					var newRow = temp.firstElementChild;
					if ( newRow ) {
						rowsWrap.appendChild( newRow );
						initDualRanges( newRow );
						updateState();
						// Notify condition engine a new row exists
						wrap.dispatchEvent( new CustomEvent( 'clefa:repeater:rowAdded', { bubbles: true, detail: { repeaterId: fieldId, rowIndex: newIndex } } ) );
					}
				} );
			}

			rowsWrap.addEventListener( 'click', function( e ) {
				var rmBtn = e.target.closest( '[data-clefa-repeater-remove]' );
				if ( ! rmBtn ) { return; }
				var row = rmBtn.closest( '[data-clefa-repeater-row]' );
				if ( ! row ) { return; }
				if ( getRows().length <= minRows ) { return; }
				row.remove();
				updateState();
				wrap.dispatchEvent( new CustomEvent( 'clefa:repeater:rowRemoved', { bubbles: true, detail: { repeaterId: fieldId } } ) );
			} );

			updateState();
		} );
	}

	/* ------------------------------------------------------------------ */
	/* Dual-range slider helpers                                            */
	/* ------------------------------------------------------------------ */

	function initDualRanges( scope ) {
		scope.querySelectorAll( '[data-clefa-range-dual]' ).forEach( function( wrap ) {
			var inputMin  = wrap.querySelector( '[data-clefa-range-dual-input="min"]' );
			var inputMax  = wrap.querySelector( '[data-clefa-range-dual-input="max"]' );
			var fill      = wrap.querySelector( '[data-clefa-range-dual-fill]' );
			var valMinEl  = wrap.querySelector( '[data-clefa-range-dual-value="min"]' );
			var valMaxEl  = wrap.querySelector( '[data-clefa-range-dual-value="max"]' );
			var hiddenMin = wrap.querySelector( '[data-clefa-field-id][name*="[0]"]' );
			var hiddenMax = wrap.querySelector( '[name*="[1]"]' );

			if ( ! inputMin || ! inputMax ) { return; }

			var sliderMin = parseFloat( wrap.getAttribute( 'data-min' ) ) || 0;
			var sliderMax = parseFloat( wrap.getAttribute( 'data-max' ) ) || 100;

			function updateFill() {
				var a = parseFloat( inputMin.value );
				var b = parseFloat( inputMax.value );
				var pLeft  = ( ( a - sliderMin ) / ( sliderMax - sliderMin ) ) * 100;
				var pRight = ( ( b - sliderMin ) / ( sliderMax - sliderMin ) ) * 100;
				if ( fill ) {
					fill.style.left  = pLeft  + '%';
					fill.style.width = ( pRight - pLeft ) + '%';
				}
				if ( valMinEl ) { valMinEl.textContent = a; }
				if ( valMaxEl ) { valMaxEl.textContent = b; }
				if ( hiddenMin ) { hiddenMin.value = a; }
				if ( hiddenMax ) { hiddenMax.value = b; }
			}

			inputMin.addEventListener( 'input', function() {
				if ( parseFloat( inputMin.value ) > parseFloat( inputMax.value ) ) {
					inputMin.value = inputMax.value;
				}
				updateFill();
			} );

			inputMax.addEventListener( 'input', function() {
				if ( parseFloat( inputMax.value ) < parseFloat( inputMin.value ) ) {
					inputMax.value = inputMin.value;
				}
				updateFill();
			} );

			updateFill();
		} );
	}

	/* ------------------------------------------------------------------ */
	/* Draft persistence                                                      */
	/* ------------------------------------------------------------------ */

	FormEngine.prototype._saveDraft = function() {
		try {
			var data = collectFormData( this.formEl );
			localStorage.setItem( this._draftKey, JSON.stringify( { data: data, savedAt: Date.now() } ) );
		} catch ( e ) { /* localStorage may be unavailable */ }
	};

	FormEngine.prototype._restoreDraft = function() {
		try {
			var raw   = localStorage.getItem( this._draftKey );
			if ( ! raw ) { return; }
			var draft = JSON.parse( raw );
			if ( ! draft || ! draft.data ) { return; }
			var self  = this;
			Object.entries( draft.data ).forEach( function( entry ) {
				var fieldId = entry[0], val = entry[1];
				var inputs  = self.formEl.querySelectorAll( '[data-clefa-field-id="' + fieldId + '"]' );
				inputs.forEach( function( input ) {
					if ( input.type === 'checkbox' || input.type === 'radio' ) {
						var vals = Array.isArray( val ) ? val : [ val ];
						input.checked = vals.indexOf( input.value ) > -1;
					} else if ( input.tagName === 'SELECT' && input.multiple ) {
						var vals2 = Array.isArray( val ) ? val : [ val ];
						Array.from( input.options ).forEach( function( opt ) {
							opt.selected = vals2.indexOf( opt.value ) > -1;
						} );
					} else {
						input.value = Array.isArray( val ) ? val.join( ', ' ) : ( val || '' );
					}
				} );
			} );
			if ( self.condEngine ) { self.condEngine.evaluateAll(); }
			dispatch( self.formEl, 'clefa:form:draft-restored', { formId: self.formId } );
		} catch ( e ) { /* ignore */ }
	};

	FormEngine.prototype._clearDraft = function() {
		try { localStorage.removeItem( this._draftKey ); } catch ( e ) { /* ignore */ }
	};

	/* ------------------------------------------------------------------ */
	/* Auto-init                                                             */
	/* ------------------------------------------------------------------ */

	function initAll() {
		document.querySelectorAll( '[data-clefa-form]' ).forEach( function ( el ) {
			if ( ! el._cleaFormEngine ) {
				el._cleaFormEngine = new FormEngine( el );
			}
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', initAll );
	} else {
		initAll();
	}

	window.CLEFA.FormEngine = FormEngine;
	window.CLEFA.initForms  = initAll;

} () );
