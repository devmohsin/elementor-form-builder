/**
 * CLEFA ConditionEngine
 *
 * Evaluates field conditions and applies effects to DOM wrappers.
 * Supported actions: show, hide, require, unrequire,
 *   add_class, remove_class, set_style, set_placeholder, set_label, set_description.
 *
 * The engine saves original values before overwriting them so that the inverse
 * action can restore the DOM to its initial state when conditions stop matching.
 */
( function () {
	'use strict';

	window.CLEFA = window.CLEFA || {};

	// =========================================================================
	// Helpers
	// =========================================================================

	/** Actions that do NOT involve field visibility. */
	var NON_VISIBILITY_ACTIONS = [
		'require', 'unrequire',
		'add_class', 'remove_class',
		'set_style', 'clear_style',
		'set_placeholder', 'restore_placeholder',
		'set_label', 'restore_label',
		'set_description', 'restore_description',
	];

	/**
	 * Given an action + its value, return the inverse action + value that
	 * should fire when the condition stops matching.
	 */
	function inverseEffect( action, value ) {
		var inv = {
			'show':            { action: 'hide',                value: value },
			'hide':            { action: 'show',                value: value },
			// require/unrequire are one-directional: when condition is unmet the
			// field reverts to its own config (noop), not to the opposite state.
			'require':         { action: 'noop',                value: ''    },
			'unrequire':       { action: 'noop',                value: ''    },
			'add_class':       { action: 'remove_class',        value: value },
			'remove_class':    { action: 'add_class',           value: value },
			'set_style':       { action: 'clear_style',         value: value },
			'clear_style':     { action: 'set_style',           value: value },
			'set_placeholder': { action: 'restore_placeholder', value: ''    },
			'set_label':       { action: 'restore_label',       value: ''    },
			'set_description': { action: 'restore_description', value: ''    },
		};
		return inv[ action ] || { action: 'noop', value: '' };
	}

	// =========================================================================
	// ConditionEngine
	// =========================================================================

	function ConditionEngine( formEl, config ) {
		this.formEl      = formEl;
		this.config      = config;
		this.fieldMap    = {};
		this.repeaterMap = {};
		this._originals  = {}; // { fieldId: { key: originalValue } }

		this._buildFieldMap();
	}

	ConditionEngine.prototype._buildFieldMap = function () {
		var self = this;
		( self.config.steps || [] ).forEach( function ( step ) {
			( step.fields || [] ).forEach( function ( field ) {
				if ( field.conditions && field.conditions.length ) {
					self.fieldMap[ field.field_id ] = field.conditions;
				}
				if ( field.field_type === 'repeater' && field.sub_fields && field.sub_fields.length ) {
					var subMap = {};
					field.sub_fields.forEach( function ( sf ) {
						if ( sf.conditions && sf.conditions.length ) {
							subMap[ sf.field_id ] = sf.conditions;
						}
					} );
					if ( Object.keys( subMap ).length ) {
						self.repeaterMap[ field.field_id ] = subMap;
					}
				}
			} );
		} );
	};

	ConditionEngine.prototype.init = function () {
		var self = this;
		self.formEl.addEventListener( 'change', function ( e ) {
			if ( e.target.hasAttribute( 'data-clefa-input' ) ) { self.evaluateAll(); }
		} );
		self.formEl.addEventListener( 'input', function ( e ) {
			if ( e.target.hasAttribute( 'data-clefa-input' ) ) { self.evaluateAll(); }
		} );
		self.formEl.addEventListener( 'clefa:repeater:rowAdded',   function () { self.evaluateAll(); } );
		self.formEl.addEventListener( 'clefa:repeater:rowRemoved', function () { self.evaluateAll(); } );
		self.evaluateAll();
	};

	ConditionEngine.prototype.evaluateAll = function () {
		var self        = this;
		var currentData = self._collectData( self.formEl );

		Object.keys( self.fieldMap ).forEach( function ( fieldId ) {
			var result = self._evaluate( self.fieldMap[ fieldId ], currentData );
			self._applyResult( fieldId, result );
		} );

		Object.keys( self.repeaterMap ).forEach( function ( repeaterId ) {
			var subMap     = self.repeaterMap[ repeaterId ];
			var repeaterEl = self.formEl.querySelector( '[data-clefa-repeater="' + repeaterId + '"]' );
			if ( ! repeaterEl ) { return; }

			repeaterEl.querySelectorAll( '[data-clefa-repeater-row]' ).forEach( function ( rowEl ) {
				var rowIndex = rowEl.getAttribute( 'data-clefa-row-index' );
				var rowData  = self._collectRepeaterRowData( repeaterId, rowIndex, rowEl );

				Object.keys( subMap ).forEach( function ( subFieldId ) {
					var result     = self._evaluate( subMap[ subFieldId ], rowData );
					var subWrapper = rowEl.querySelector( '[data-clefa-repeater-sub-field="' + subFieldId + '"]' );
					if ( subWrapper ) { self._applyResultToWrapper( subWrapper, result ); }
				} );
			} );
		} );
	};

	// -------------------------------------------------------------------------
	// Data collection
	// -------------------------------------------------------------------------

	ConditionEngine.prototype._collectData = function ( scope ) {
		var data   = {};
		var inputs = ( scope || this.formEl ).querySelectorAll( '[data-clefa-input]' );
		inputs.forEach( function ( input ) {
			var id = input.getAttribute( 'data-clefa-field-id' );
			if ( ! id ) { return; }
			if ( input.type === 'checkbox' ) {
				if ( ! data[ id ] ) { data[ id ] = []; }
				if ( input.checked ) { data[ id ].push( input.value ); }
			} else if ( input.type === 'radio' ) {
				if ( input.checked ) { data[ id ] = input.value; }
			} else if ( input.tagName === 'SELECT' && input.multiple ) {
				data[ id ] = Array.from( input.selectedOptions ).map( function ( o ) { return o.value; } );
			} else {
				data[ id ] = input.value;
			}
		} );

		( scope || this.formEl ).querySelectorAll( '[data-clefa-field][data-clefa-live-status]' ).forEach( function ( wrapper ) {
			var fieldId    = wrapper.getAttribute( 'data-clefa-field' );
			var liveStatus = wrapper.getAttribute( 'data-clefa-live-status' );
			if ( fieldId ) { data[ fieldId + '_live' ] = liveStatus || ''; }
		} );

		return data;
	};

	ConditionEngine.prototype._collectRepeaterRowData = function ( repeaterId, rowIndex, rowEl ) {
		var data   = {};
		var prefix = repeaterId + '[' + rowIndex + '][';
		rowEl.querySelectorAll( '[data-clefa-input]' ).forEach( function ( input ) {
			var rawId = input.getAttribute( 'data-clefa-field-id' );
			if ( ! rawId ) { return; }
			var subFieldId;
			if ( rawId.indexOf( prefix ) === 0 && rawId.charAt( rawId.length - 1 ) === ']' ) {
				subFieldId = rawId.slice( prefix.length, -1 );
			} else {
				return;
			}
			if ( input.type === 'checkbox' ) {
				if ( ! data[ subFieldId ] ) { data[ subFieldId ] = []; }
				if ( input.checked ) { data[ subFieldId ].push( input.value ); }
			} else if ( input.type === 'radio' ) {
				if ( input.checked ) { data[ subFieldId ] = input.value; }
			} else if ( input.tagName === 'SELECT' && input.multiple ) {
				data[ subFieldId ] = Array.from( input.selectedOptions ).map( function ( o ) { return o.value; } );
			} else {
				data[ subFieldId ] = input.value;
			}
		} );
		return data;
	};

	// -------------------------------------------------------------------------
	// Evaluation
	// -------------------------------------------------------------------------

	/**
	 * Evaluate conditions against data.
	 * Returns { action: string, value: string } — the effect to apply.
	 */
	ConditionEngine.prototype._evaluate = function ( conditions, data ) {
		if ( ! conditions || ! conditions.length ) {
			return { action: 'show', value: '' };
		}

		var groups = {};
		conditions.forEach( function ( c ) {
			var g = c.logic_group || 'AND';
			if ( ! groups[ g ] ) { groups[ g ] = []; }
			groups[ g ].push( c );
		} );

		var last       = conditions[ conditions.length - 1 ];
		var lastAction = last.action       || 'show';
		var lastValue  = last.action_value || '';

		for ( var g in groups ) {
			var allPass = groups[ g ].every( function ( c ) {
				return ConditionEngine.compare( data[ c.source_field ] || '', c.operator, c.compare_value || '' );
			} );
			if ( allPass ) {
				var matched = groups[ g ][ groups[ g ].length - 1 ];
				return { action: matched.action || 'show', value: matched.action_value || '' };
			}
		}

		return inverseEffect( lastAction, lastValue );
	};

	ConditionEngine.prototype._applyResult = function ( fieldId, result ) {
		var wrapper = this.formEl.querySelector( '[data-clefa-field="' + fieldId + '"]' );
		if ( wrapper ) { this._applyResultToWrapper( wrapper, result ); }
	};

	// -------------------------------------------------------------------------
	// Effect application
	// -------------------------------------------------------------------------

	/**
	 * Apply an effect { action, value } to a wrapper element.
	 * Accepts legacy string results for backward compatibility.
	 */
	ConditionEngine.prototype._applyResultToWrapper = function ( wrapper, result ) {
		// Backward compat: accept plain string
		if ( typeof result === 'string' ) {
			result = { action: result, value: '' };
		}

		var action  = result.action || 'show';
		var value   = result.value  || '';
		var fieldId = wrapper.getAttribute( 'data-clefa-field' ) ||
		              wrapper.getAttribute( 'data-clefa-repeater-sub-field' ) || '';

		switch ( action ) {

			// ---- Visibility ------------------------------------------------

			case 'show':
			case 'hide': {
				var visible = action === 'show';
				var prev    = wrapper.getAttribute( 'data-clefa-visible' );
				var TE      = window.CLEFA && window.CLEFA.TransitionEngine;

				if ( TE ) {
					TE.setFieldVisible( wrapper, visible );
				} else {
					wrapper.setAttribute( 'data-clefa-visible', visible ? '1' : '0' );
					wrapper.style.display = visible ? '' : 'none';
					wrapper.querySelectorAll( 'input, textarea, select' ).forEach( function ( inp ) {
						inp.disabled = ! visible;
					} );
				}

				if ( prev !== null && prev !== ( visible ? '1' : '0' ) ) {
					var evtName = visible ? 'clefa:condition:matched' : 'clefa:condition:unmatched';
					wrapper.dispatchEvent( new CustomEvent( evtName, {
						bubbles: true,
						detail:  { fieldId: fieldId },
					} ) );
				}
				break;
			}

			// ---- Required state --------------------------------------------

			case 'require':
			case 'unrequire': {
				var req   = action === 'require';
				var input = wrapper.querySelector( '[data-clefa-input]' );
				wrapper.setAttribute( 'data-clefa-required', req ? '1' : '0' );
				if ( input ) {
					if ( req ) { input.setAttribute( 'required', '' ); }
					else       { input.removeAttribute( 'required' ); }
				}
				wrapper.dispatchEvent( new CustomEvent( 'clefa:condition:required-changed', {
					bubbles: true,
					detail:  { fieldId: fieldId, required: req },
				} ) );
				break;
			}

			// ---- CSS classes -----------------------------------------------

			case 'add_class': {
				if ( value ) {
					value.split( /\s+/ ).filter( Boolean ).forEach( function ( cls ) {
						wrapper.classList.add( cls );
					} );
				}
				break;
			}
			case 'remove_class': {
				if ( value ) {
					value.split( /\s+/ ).filter( Boolean ).forEach( function ( cls ) {
						wrapper.classList.remove( cls );
					} );
				}
				break;
			}

			// ---- Inline CSS ------------------------------------------------

			case 'set_style': {
				// action_value format: "property:value"   e.g. "background-color:#fff3e0"
				var colonIdx = value.indexOf( ':' );
				if ( colonIdx > 0 ) {
					var prop = value.slice( 0, colonIdx ).trim();
					var val  = value.slice( colonIdx + 1 ).trim();
					this._saveOriginal( wrapper, '_style_' + prop, wrapper.style.getPropertyValue( prop ) );
					wrapper.style.setProperty( prop, val );
				}
				break;
			}
			case 'clear_style': {
				// Restore the style saved when set_style was applied
				var colonIdx2 = value.indexOf( ':' );
				var prop2     = colonIdx2 > 0 ? value.slice( 0, colonIdx2 ).trim() : value.trim();
				var origVal   = this._getOriginal( wrapper, '_style_' + prop2, '' );
				if ( origVal ) {
					wrapper.style.setProperty( prop2, origVal );
				} else {
					wrapper.style.removeProperty( prop2 );
				}
				break;
			}

			// ---- Placeholder -----------------------------------------------

			case 'set_placeholder': {
				var inp = wrapper.querySelector( '[data-clefa-input]' );
				if ( inp && 'placeholder' in inp ) {
					this._saveOriginal( wrapper, '_placeholder', inp.placeholder );
					inp.placeholder = value;
				}
				break;
			}
			case 'restore_placeholder': {
				var inp2 = wrapper.querySelector( '[data-clefa-input]' );
				if ( inp2 ) { inp2.placeholder = this._getOriginal( wrapper, '_placeholder', '' ); }
				break;
			}

			// ---- Label -----------------------------------------------------

			case 'set_label': {
				var lbl = wrapper.querySelector( '[data-clefa-field-label]' );
				if ( lbl ) {
					this._saveOriginal( wrapper, '_label', lbl.textContent );
					lbl.textContent = value;
				}
				break;
			}
			case 'restore_label': {
				var lbl2 = wrapper.querySelector( '[data-clefa-field-label]' );
				if ( lbl2 ) { lbl2.textContent = this._getOriginal( wrapper, '_label', '' ); }
				break;
			}

			// ---- Description -----------------------------------------------

			case 'set_description': {
				var dsc = wrapper.querySelector( '[data-clefa-field-desc]' );
				if ( dsc ) {
					this._saveOriginal( wrapper, '_desc', dsc.innerHTML );
					dsc.innerHTML = value;
				}
				break;
			}
			case 'restore_description': {
				var dsc2 = wrapper.querySelector( '[data-clefa-field-desc]' );
				if ( dsc2 ) { dsc2.innerHTML = this._getOriginal( wrapper, '_desc', '' ); }
				break;
			}
		}
	};

	// -------------------------------------------------------------------------
	// Originals store
	// -------------------------------------------------------------------------

	ConditionEngine.prototype._saveOriginal = function ( wrapper, key, value ) {
		var fid = wrapper.getAttribute( 'data-clefa-field' ) ||
		          wrapper.getAttribute( 'data-clefa-repeater-sub-field' ) || '__root__';
		if ( ! this._originals[ fid ] ) { this._originals[ fid ] = {}; }
		// Only save once — we want the truly original DOM value, not a subsequent override.
		if ( ! ( key in this._originals[ fid ] ) ) {
			this._originals[ fid ][ key ] = value !== undefined ? value : '';
		}
	};

	ConditionEngine.prototype._getOriginal = function ( wrapper, key, defaultVal ) {
		var fid = wrapper.getAttribute( 'data-clefa-field' ) ||
		          wrapper.getAttribute( 'data-clefa-repeater-sub-field' ) || '__root__';
		if ( this._originals[ fid ] && key in this._originals[ fid ] ) {
			return this._originals[ fid ][ key ];
		}
		return defaultVal !== undefined ? defaultVal : '';
	};

	// -------------------------------------------------------------------------
	// Static compare helper
	// -------------------------------------------------------------------------

	ConditionEngine.compare = function ( actual, operator, compare ) {
		var a = Array.isArray( actual ) ? actual : String( actual );
		var c = String( compare );

		switch ( operator ) {
			case 'equals':
				return Array.isArray( a ) ? a.indexOf( c ) !== -1 : a === c;
			case 'not_equals':
				return Array.isArray( a ) ? a.indexOf( c ) === -1 : a !== c;
			case 'contains':
				return Array.isArray( a ) ? a.indexOf( c ) !== -1 : a.indexOf( c ) !== -1;
			case 'not_contains':
				return Array.isArray( a ) ? a.indexOf( c ) === -1 : a.indexOf( c ) === -1;
			case 'starts_with':
				return ! Array.isArray( a ) && a.indexOf( c ) === 0;
			case 'ends_with':
				return ! Array.isArray( a ) && a.slice( -c.length ) === c;
			case 'greater_than':
				return parseFloat( a ) > parseFloat( c );
			case 'less_than':
				return parseFloat( a ) < parseFloat( c );
			case 'greater_than_or_equal':
				return parseFloat( a ) >= parseFloat( c );
			case 'less_than_or_equal':
				return parseFloat( a ) <= parseFloat( c );
			case 'is_empty':
				return Array.isArray( a ) ? a.length === 0 : a.trim() === '';
			case 'is_not_empty':
				return Array.isArray( a ) ? a.length > 0 : a.trim() !== '';
			case 'is_checked':
				return Array.isArray( a ) ? a.length > 0 : ( a === '1' || a === 'true' || a === 'on' );
			case 'is_not_checked':
				return Array.isArray( a ) ? a.length === 0 : ( a !== '1' && a !== 'true' && a !== 'on' );
			case 'date_after': {
				var d1 = new Date( String( a ) ), d2 = c === 'today' ? new Date() : new Date( c );
				return d1 > d2;
			}
			case 'date_before': {
				var d1b = new Date( String( a ) ), d2b = c === 'today' ? new Date() : new Date( c );
				return d1b < d2b;
			}
			case 'date_equals': {
				var d1e = new Date( String( a ) ), d2e = c === 'today' ? new Date() : new Date( c );
				return d1e.toDateString() === d2e.toDateString();
			}
			case 'age_over': {
				var dob  = new Date( String( a ) );
				var age  = Math.floor( ( Date.now() - dob.getTime() ) / 3.15576e10 );
				return age > parseInt( c, 10 );
			}
			case 'age_under': {
				var dobu = new Date( String( a ) );
				var ageu = Math.floor( ( Date.now() - dobu.getTime() ) / 3.15576e10 );
				return ageu < parseInt( c, 10 );
			}
			case 'file_uploaded':
				return !! a;
			case 'api_check_passed':
				return String( a ) === 'success';
			case 'api_check_failed': {
				var s = String( a );
				return s === 'fail' || s === 'error';
			}
			default:
				return false;
		}
	};

	window.CLEFA.ConditionEngine = ConditionEngine;

} () );
