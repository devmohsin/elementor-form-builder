/**
 * CLEFA ValidationEngine + ValidationRegistry
 *
 * ValidationRegistry: central store of client-side rule handlers.
 *   CLEFA.ValidationRegistry.register( key, handler ) to add custom rules.
 *   handler signature: function( value, param, field, engine ) → string|null
 *     Return an error string on failure, null on pass.
 *
 * ValidationEngine: reads field.validation_rules (array of {rule,value,message})
 *   and runs each rule through the registry.
 */
( function () {
	'use strict';

	window.CLEFA = window.CLEFA || {};

	// =========================================================================
	// ValidationRegistry
	// =========================================================================

	var Registry = {

		_rules: {},

		/**
		 * Register a client-side rule handler.
		 *
		 * @param {string}   key     Rule key matching the registry (e.g. 'min_length').
		 * @param {Function} handler fn(value, param, field, engine) → string|null
		 */
		register: function ( key, handler ) {
			this._rules[ key ] = handler;
		},

		/**
		 * Run a rule. Returns an error string or null.
		 *
		 * @param {string} key
		 * @param {*}      value
		 * @param {*}      param   Rule parameter (may be null).
		 * @param {Object} field   Field definition object.
		 * @param {Object} engine  ValidationEngine instance (for cross-field reads).
		 */
		check: function ( key, value, param, field, engine ) {
			var handler = this._rules[ key ];
			if ( ! handler ) {
				return null; // Unknown rules are silently skipped
			}
			try {
				return handler( value, param, field, engine ) || null;
			} catch ( e ) {
				return null;
			}
		},

		_registerCoreRules: function () {
			var R = this;

			// ----------------------------------------------------------------
			// Text length
			// ----------------------------------------------------------------

			R.register( 'min_length', function ( value, param ) {
				var min = parseInt( param, 10 );
				if ( isNaN( min ) || min <= 0 ) { return null; }
				return String( value ).length < min
					? 'Minimum ' + min + ' characters required.'
					: null;
			} );

			R.register( 'max_length', function ( value, param ) {
				var max = parseInt( param, 10 );
				if ( isNaN( max ) || max <= 0 ) { return null; }
				return String( value ).length > max
					? 'Maximum ' + max + ' characters allowed.'
					: null;
			} );

			R.register( 'exact_length', function ( value, param ) {
				var len = parseInt( param, 10 );
				if ( isNaN( len ) || len <= 0 ) { return null; }
				return String( value ).length !== len
					? 'Must be exactly ' + len + ' characters.'
					: null;
			} );

			// ----------------------------------------------------------------
			// Pattern / block
			// ----------------------------------------------------------------

			R.register( 'regex', function ( value, param ) {
				if ( ! param ) { return null; }
				try {
					var re = new RegExp( param );
					return re.test( String( value ) ) ? null : 'Invalid format.';
				} catch ( e ) {
					return null; // Invalid regex — skip silently
				}
			} );

			R.register( 'blocked_values', function ( value, param ) {
				if ( ! param ) { return null; }
				var blocked = param.split( ',' ).map( function ( s ) { return s.trim(); } );
				return blocked.indexOf( String( value ) ) !== -1
					? 'This value is not allowed.'
					: null;
			} );

			R.register( 'equals', function ( value, param ) {
				return String( value ) !== String( param || '' )
					? 'Value does not match.'
					: null;
			} );

			R.register( 'not_equals', function ( value, param ) {
				return String( value ) === String( param || '' )
					? 'This value is not permitted.'
					: null;
			} );

			// ----------------------------------------------------------------
			// Format
			// ----------------------------------------------------------------

			R.register( 'email', function ( value ) {
				return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( String( value ) )
					? null
					: 'Please enter a valid email address.';
			} );

			R.register( 'url', function ( value ) {
				try {
					new URL( String( value ) );
					return null;
				} catch ( e ) {
					return 'Please enter a valid URL.';
				}
			} );

			R.register( 'numeric', function ( value ) {
				return isNaN( parseFloat( value ) ) || ! isFinite( value )
					? 'Please enter a valid number.'
					: null;
			} );

			R.register( 'integer', function ( value ) {
				var str = String( value ).trim();
				return /^-?\d+$/.test( str )
					? null
					: 'Please enter a whole number.';
			} );

			// ----------------------------------------------------------------
			// Numeric range
			// ----------------------------------------------------------------

			R.register( 'min_value', function ( value, param ) {
				if ( param === '' || param === null || param === undefined ) { return null; }
				var num = parseFloat( value );
				if ( isNaN( num ) ) { return null; }
				return num < parseFloat( param )
					? 'Minimum value is ' + param + '.'
					: null;
			} );

			R.register( 'max_value', function ( value, param ) {
				if ( param === '' || param === null || param === undefined ) { return null; }
				var num = parseFloat( value );
				if ( isNaN( num ) ) { return null; }
				return num > parseFloat( param )
					? 'Maximum value is ' + param + '.'
					: null;
			} );

			// ----------------------------------------------------------------
			// Date rules
			// ----------------------------------------------------------------

			R.register( 'date_valid', function ( value ) {
				var ts = Date.parse( String( value ) );
				return isNaN( ts )
					? 'Please enter a valid date.'
					: null;
			} );

			R.register( 'date_after_today', function ( value ) {
				var ts = Date.parse( String( value ) );
				if ( isNaN( ts ) ) { return null; }
				var today = new Date();
				today.setHours( 0, 0, 0, 0 );
				return ts <= today.getTime()
					? 'Date must be after today.'
					: null;
			} );

			R.register( 'date_before_today', function ( value ) {
				var ts = Date.parse( String( value ) );
				if ( isNaN( ts ) ) { return null; }
				var today = new Date();
				today.setHours( 0, 0, 0, 0 );
				return ts >= today.getTime()
					? 'Date must be before today.'
					: null;
			} );

			R.register( 'date_after', function ( value, param ) {
				if ( ! param ) { return null; }
				var ts        = Date.parse( String( value ) );
				var threshold = Date.parse( String( param ) );
				if ( isNaN( ts ) || isNaN( threshold ) ) { return null; }
				return ts <= threshold
					? 'Date must be after ' + param + '.'
					: null;
			} );

			R.register( 'date_before', function ( value, param ) {
				if ( ! param ) { return null; }
				var ts        = Date.parse( String( value ) );
				var threshold = Date.parse( String( param ) );
				if ( isNaN( ts ) || isNaN( threshold ) ) { return null; }
				return ts >= threshold
					? 'Date must be before ' + param + '.'
					: null;
			} );

			// ----------------------------------------------------------------
			// Age rules (date of birth)
			// ----------------------------------------------------------------

			R.register( 'age_over', function ( value, param ) {
				var minAge = parseInt( param, 10 );
				if ( isNaN( minAge ) || minAge <= 0 ) { return null; }
				var ts = Date.parse( String( value ) );
				if ( isNaN( ts ) ) { return null; }
				// 365.25 days per year
				var age = Math.floor( ( Date.now() - ts ) / ( 365.25 * 24 * 3600 * 1000 ) );
				return age < minAge
					? 'You must be at least ' + minAge + ' years old.'
					: null;
			} );

			R.register( 'age_under', function ( value, param ) {
				var maxAge = parseInt( param, 10 );
				if ( isNaN( maxAge ) || maxAge <= 0 ) { return null; }
				var ts = Date.parse( String( value ) );
				if ( isNaN( ts ) ) { return null; }
				var age = Math.floor( ( Date.now() - ts ) / ( 365.25 * 24 * 3600 * 1000 ) );
				return age >= maxAge
					? 'You must be under ' + maxAge + ' years old.'
					: null;
			} );

			// ----------------------------------------------------------------
			// Time-elapsed rules
			// ----------------------------------------------------------------

			R.register( 'time_since', function ( value, param ) {
				var minDays = parseFloat( param );
				if ( isNaN( minDays ) || minDays <= 0 ) { return null; }
				var ts = Date.parse( String( value ) );
				if ( isNaN( ts ) ) { return null; }
				var daysElapsed = ( Date.now() - ts ) / ( 24 * 3600 * 1000 );
				return daysElapsed < minDays
					? 'Date must be at least ' + param + ' days in the past.'
					: null;
			} );

			R.register( 'time_passed', function ( value, param ) {
				var minHours = parseFloat( param );
				if ( isNaN( minHours ) || minHours <= 0 ) { return null; }
				var ts = Date.parse( String( value ) );
				if ( isNaN( ts ) ) { return null; }
				var hoursElapsed = ( Date.now() - ts ) / ( 3600 * 1000 );
				return hoursElapsed < minHours
					? 'At least ' + param + ' hours must have elapsed since that date.'
					: null;
			} );

			// ----------------------------------------------------------------
			// Password complexity
			// ----------------------------------------------------------------

			R.register( 'require_uppercase', function ( value ) {
				return /[A-Z]/.test( String( value ) )
					? null
					: 'Must contain at least one uppercase letter.';
			} );

			R.register( 'require_number_char', function ( value ) {
				return /[0-9]/.test( String( value ) )
					? null
					: 'Must contain at least one number.';
			} );

			R.register( 'require_special', function ( value ) {
				return /[^A-Za-z0-9]/.test( String( value ) )
					? null
					: 'Must contain at least one special character.';
			} );

			R.register( 'password_strength', function ( value, param ) {
				var pw    = String( value );
				var level = param || 'weak';
				if ( 'strong' === level ) {
					if ( pw.length < 10 || ! /[A-Z]/.test( pw ) || ! /[a-z]/.test( pw ) ||
						 ! /[0-9]/.test( pw ) || ! /[^A-Za-z0-9]/.test( pw ) ) {
						return 'Password is not strong enough.';
					}
				} else if ( 'medium' === level ) {
					if ( pw.length < 8 || ! /[A-Z]/.test( pw ) || ! /[a-z]/.test( pw ) ||
						 ! /[0-9]/.test( pw ) ) {
						return 'Password is not strong enough.';
					}
				} else {
					if ( pw.length < 6 ) {
						return 'Password is not strong enough.';
					}
				}
				return null;
			} );

			// ----------------------------------------------------------------
			// Cross-field match
			// ----------------------------------------------------------------

			R.register( 'confirm_password', function ( value, param, field, engine ) {
				if ( ! param ) { return null; }
				var mainValue = engine ? engine._getFieldValue( param ) : '';
				return String( value ) !== String( mainValue )
					? 'Passwords do not match.'
					: null;
			} );

			// ----------------------------------------------------------------
			// Checkbox
			// ----------------------------------------------------------------

			R.register( 'checkbox_min', function ( value, param ) {
				var min = parseInt( param, 10 );
				if ( isNaN( min ) ) { return null; }
				var arr   = Array.isArray( value ) ? value : ( value ? [ value ] : [] );
				var count = arr.filter( Boolean ).length;
				return count < min
					? 'Please select at least ' + min + ' option(s).'
					: null;
			} );

			R.register( 'checkbox_max', function ( value, param ) {
				var max = parseInt( param, 10 );
				if ( isNaN( max ) ) { return null; }
				var arr   = Array.isArray( value ) ? value : ( value ? [ value ] : [] );
				var count = arr.filter( Boolean ).length;
				return count > max
					? 'Please select no more than ' + max + ' option(s).'
					: null;
			} );
		},
	};

	Registry._registerCoreRules();

	window.CLEFA.ValidationRegistry = Registry;

	// =========================================================================
	// ValidationEngine
	// =========================================================================

	/** Implicit base-type rules evaluated before validation_rules. */
	var BASE_RULES = {
		email:  'email',
		url:    'url',
		number: 'numeric',
		range:  'numeric',
		date:   'date_valid',
	};

	var DISPLAY_TYPES = [ 'html', 'notice', 'grid_break', 'heading' ];

	function ValidationEngine( formEl, config ) {
		this.formEl    = formEl;
		this.config    = config;
		this.fieldDefs = {};
		this._buildFieldDefs();
	}

	ValidationEngine.prototype._buildFieldDefs = function () {
		var self = this;
		( self.config.steps || [] ).forEach( function ( step ) {
			( step.fields || [] ).forEach( function ( field ) {
				self.fieldDefs[ field.field_id ] = field;
			} );
		} );
	};

	ValidationEngine.prototype.init = function () {
		var self = this;

		self.formEl.addEventListener( 'blur', function ( e ) {
			if ( e.target.hasAttribute( 'data-clefa-input' ) ) {
				var fieldId = e.target.getAttribute( 'data-clefa-field-id' );
				if ( fieldId ) {
					self._validateField( fieldId, self._getFieldValue( fieldId ) );
					self._updateStepButtonStates( e.target.closest( '[data-clefa-step]' ) );
				}
			}
		}, true );

		self.formEl.addEventListener( 'input', function ( e ) {
			if ( e.target.hasAttribute( 'data-clefa-input' ) ) {
				self._updateStepButtonStates( e.target.closest( '[data-clefa-step]' ) );
			}
		} );

		self.formEl.addEventListener( 'change', function ( e ) {
			if ( e.target.hasAttribute( 'data-clefa-input' ) ) {
				self._updateStepButtonStates( e.target.closest( '[data-clefa-step]' ) );
			}
		} );
	};

	ValidationEngine.prototype._updateStepButtonStates = function ( stepEl ) {
		if ( ! stepEl ) { return; }
		var mode = stepEl.getAttribute( 'data-clefa-btn-mode' );
		if ( mode !== 'disable-until-valid' && mode !== 'hide-until-valid' ) { return; }

		var self     = this;
		var fields   = stepEl.querySelectorAll( '[data-clefa-field]' );
		var allValid = true;

		fields.forEach( function ( wrapper ) {
			if ( wrapper.getAttribute( 'data-clefa-visible' ) === '0' ) { return; }
			var fieldId = wrapper.getAttribute( 'data-clefa-field' );
			if ( ! fieldId ) { return; }
			var def = self.fieldDefs[ fieldId ];
			if ( ! def || ! def.required ) { return; }
			var val = self._getFieldValue( fieldId );
			if ( val === '' || val === null || ( Array.isArray( val ) && ! val.length ) ) {
				allValid = false;
			}
		} );

		var btn = stepEl.querySelector( '[data-clefa-next], [data-clefa-submit]' );
		if ( ! btn ) { return; }

		if ( mode === 'disable-until-valid' ) {
			btn.disabled = ! allValid;
		} else {
			btn.style.display = allValid ? '' : 'none';
		}
	};

	ValidationEngine.prototype.validateStep = function ( stepEl ) {
		var self   = this;
		var errors = {};
		var fields = stepEl.querySelectorAll( '[data-clefa-field]' );

		fields.forEach( function ( wrapper ) {
			var fieldId = wrapper.getAttribute( 'data-clefa-field' );
			if ( ! fieldId ) { return; }
			if ( wrapper.getAttribute( 'data-clefa-visible' ) === '0' ) { return; }
			var err = self._validateField( fieldId, self._getFieldValue( fieldId ) );
			if ( err ) { errors[ fieldId ] = err; }
		} );

		return errors;
	};

	ValidationEngine.prototype.validateAll = function () {
		var self    = this;
		var errors  = {};
		var allData = this._collectAllData();

		Object.keys( self.fieldDefs ).forEach( function ( fieldId ) {
			var wrapper = self.formEl.querySelector( '[data-clefa-field="' + fieldId + '"]' );
			if ( wrapper && wrapper.getAttribute( 'data-clefa-visible' ) === '0' ) { return; }
			var err = self._validateField( fieldId, allData[ fieldId ] );
			if ( err ) { errors[ fieldId ] = err; }
		} );

		return errors;
	};

	ValidationEngine.prototype._validateField = function ( fieldId, value ) {
		var self  = this;
		var field = self.fieldDefs[ fieldId ];
		if ( ! field ) { return null; }

		var type  = field.field_type || 'text';
		var rules = field.validation_rules || [];

		// Skip display-only fields
		if ( DISPLAY_TYPES.indexOf( type ) !== -1 ) {
			self._clearError( fieldId );
			return null;
		}

		var isEmpty = self._isEmpty( value );

		// Required check
		if ( field.required && isEmpty ) {
			var reqDef = self._findRuleDef( rules, 'required' );
			var reqMsg = ( reqDef && reqDef.message ) || ( ( field.label || fieldId ) + ' is required.' );
			self._showError( fieldId, reqMsg );
			return reqMsg;
		}

		if ( isEmpty ) {
			self._clearError( fieldId );
			return null;
		}

		var error = null;

		// Auto base-type check
		var baseRuleKey = BASE_RULES[ type ] || null;
		if ( baseRuleKey ) {
			error = Registry.check( baseRuleKey, value, null, field, self );
			if ( error ) {
				self._showError( fieldId, error );
				return error;
			}
		}

		// validation_rules array
		for ( var i = 0; i < rules.length; i++ ) {
			var ruleDef = rules[ i ];
			var ruleKey = ruleDef.rule || '';
			var param   = ruleDef.value !== undefined ? ruleDef.value : null;
			var custom  = ruleDef.message || '';

			if ( ! ruleKey || ruleKey === 'required' ) { continue; }

			var ruleError = Registry.check( ruleKey, value, param, field, self );
			if ( ruleError !== null ) {
				error = custom || ruleError;
				break;
			}
		}

		if ( error ) {
			self._showError( fieldId, error );
		} else {
			self._clearError( fieldId );
		}

		return error;
	};

	/** Find a rule definition by key in the validation_rules array. */
	ValidationEngine.prototype._findRuleDef = function ( rules, key ) {
		for ( var i = 0; i < rules.length; i++ ) {
			if ( rules[ i ].rule === key ) { return rules[ i ]; }
		}
		return null;
	};

	ValidationEngine.prototype._showError = function ( fieldId, message ) {
		var self  = this;
		var el    = self.formEl.querySelector( '[data-clefa-field="' + fieldId + '"]' );
		var input = el && el.querySelector( '[data-clefa-input]' );
		var errEl = el && el.querySelector( '[data-clefa-error]' );

		if ( input ) {
			input.classList.add( 'clefa-input-error' );
			input.setAttribute( 'aria-invalid', 'true' );
		}
		if ( errEl ) {
			errEl.textContent = message;
			errEl.classList.add( 'clefa-error-visible' );
			errEl.removeAttribute( 'aria-hidden' );
		}
		if ( el ) {
			el.setAttribute( 'data-clefa-has-error', '1' );
			el.dispatchEvent( new CustomEvent( 'clefa:validation:failed', { bubbles: true, detail: { fieldId: fieldId, message: message } } ) );
		}
	};

	ValidationEngine.prototype._clearError = function ( fieldId ) {
		var self     = this;
		var el       = self.formEl.querySelector( '[data-clefa-field="' + fieldId + '"]' );
		var input    = el && el.querySelector( '[data-clefa-input]' );
		var errEl    = el && el.querySelector( '[data-clefa-error]' );
		var hadError = el && el.hasAttribute( 'data-clefa-has-error' );

		if ( input ) {
			input.classList.remove( 'clefa-input-error' );
			input.removeAttribute( 'aria-invalid' );
		}
		if ( errEl ) {
			errEl.textContent = '';
			errEl.classList.remove( 'clefa-error-visible' );
			errEl.setAttribute( 'aria-hidden', 'true' );
		}
		if ( el ) {
			el.removeAttribute( 'data-clefa-has-error' );
			if ( hadError ) {
				el.dispatchEvent( new CustomEvent( 'clefa:validation:passed', { bubbles: true, detail: { fieldId: fieldId } } ) );
			}
		}
	};

	ValidationEngine.prototype._isEmpty = function ( value ) {
		if ( Array.isArray( value ) ) { return value.filter( Boolean ).length === 0; }
		return String( value ).trim() === '';
	};

	ValidationEngine.prototype._getFieldValue = function ( fieldId ) {
		var inputs = this.formEl.querySelectorAll( '[data-clefa-field-id="' + fieldId + '"]' );
		if ( ! inputs.length ) { return ''; }

		var first = inputs[ 0 ];
		if ( first.type === 'checkbox' ) {
			var checked = [];
			inputs.forEach( function ( inp ) { if ( inp.checked ) { checked.push( inp.value ); } } );
			return checked;
		}
		if ( first.type === 'radio' ) {
			var selected = Array.from( inputs ).find( function ( inp ) { return inp.checked; } );
			return selected ? selected.value : '';
		}
		if ( first.tagName === 'SELECT' && first.multiple ) {
			return Array.from( first.selectedOptions ).map( function ( o ) { return o.value; } );
		}
		return first.value;
	};

	ValidationEngine.prototype._collectAllData = function () {
		var self = this;
		var data = {};
		Object.keys( self.fieldDefs ).forEach( function ( id ) {
			data[ id ] = self._getFieldValue( id );
		} );
		return data;
	};

	ValidationEngine.prototype.showServerErrors = function ( errors ) {
		var self = this;
		Object.keys( errors ).forEach( function ( fieldId ) {
			self._showError( fieldId, errors[ fieldId ] );
		} );
		var firstErr = Object.keys( errors )[ 0 ];
		if ( firstErr ) {
			var wrapper = self.formEl.querySelector( '[data-clefa-field="' + firstErr + '"]' );
			if ( wrapper ) {
				wrapper.scrollIntoView( { behavior: 'smooth', block: 'center' } );
				var input = wrapper.querySelector( '[data-clefa-input]' );
				if ( input ) { input.focus(); }
			}
		}
	};

	window.CLEFA.ValidationEngine = ValidationEngine;

} () );
