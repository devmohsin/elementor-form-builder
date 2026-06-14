/**
 * CLEFA LiveCheckManager
 *
 * Watches inputs with [data-clefa-live-check] and performs async API checks
 * (e.g. username availability, email existence) as the user types.
 *
 * Supported check types out of the box (server-side):
 *   username_available  – passes when the username is not yet taken
 *   email_available     – passes when the email is not yet registered
 *   email_exists        – passes when the email IS registered (for login/reset forms)
 *
 * Custom checks can be added via the PHP filter:
 *   add_filter( 'clefa_live_check_{type}', fn( $result, $value, $form_id, $field_id ) => ... );
 *
 * Markup:
 *   <input data-clefa-live-check="username_available" data-clefa-input data-clefa-field-id="username">
 */
( function () {
	'use strict';

	var DEBOUNCE_MS = 600;

	/**
	 * @param {Element} formEl   The form element
	 * @param {Object}  config   CLEFA form config (needs config.id)
	 * @param {string}  restUrl  REST base URL
	 * @param {string}  nonce    WP REST nonce
	 */
	function LiveCheckManager( formEl, config, restUrl, nonce ) {
		this.formEl   = formEl;
		this.config   = config;
		this.restUrl  = restUrl;
		this.nonce    = nonce;
		this._timers  = {};
		this._pending = {};
	}

	LiveCheckManager.prototype.init = function () {
		var self = this;
		this.formEl.querySelectorAll( '[data-clefa-live-check]' ).forEach( function ( input ) {
			self._bindInput( input );
		} );
	};

	LiveCheckManager.prototype._bindInput = function ( input ) {
		var self     = this;
		var fieldEl  = input.closest( '[data-clefa-field]' );
		var fieldId  = fieldEl
			? fieldEl.getAttribute( 'data-clefa-field' )
			: ( input.getAttribute( 'data-clefa-field-id' ) || '' );

		input.addEventListener( 'input', function () {
			clearTimeout( self._timers[ fieldId ] );
			self._timers[ fieldId ] = setTimeout( function () {
				self._runCheck( input, fieldEl, fieldId );
			}, DEBOUNCE_MS );
		} );

		// Also check on blur for accessibility
		input.addEventListener( 'blur', function () {
			clearTimeout( self._timers[ fieldId ] );
			self._runCheck( input, fieldEl, fieldId );
		} );
	};

	LiveCheckManager.prototype._runCheck = function ( input, fieldEl, fieldId ) {
		var self      = this;
		var value     = input.value.trim();
		var checkType = input.getAttribute( 'data-clefa-live-check' );

		if ( ! value ) {
			self._clearStatus( fieldEl );
			return;
		}

		// Avoid duplicate in-flight requests
		if ( self._pending[ fieldId ] === value ) { return; }
		self._pending[ fieldId ] = value;

		self._setStatus( fieldEl, 'checking', self._i18n( 'checking', 'Checking\u2026' ) );
		// Block next step while checking
		if ( fieldEl ) { fieldEl.setAttribute( 'data-clefa-block-next', 'live_check_pending' ); }

		if ( window.CLEFAEventDispatcher ) {
			CLEFAEventDispatcher.dispatch( 'clefa:live-check:started', { fieldId: fieldId, checkType: checkType, value: value }, self.formEl );
		}

		fetch( self.restUrl + '/live-check', {
			method  : 'POST',
			headers : { 'Content-Type': 'application/json', 'X-WP-Nonce': self.nonce },
			body    : JSON.stringify( {
				form_id    : self.config.id || 0,
				field_id   : fieldId,
				check_type : checkType,
				value      : value,
			} ),
		} )
		.then( function ( r ) { return r.json(); } )
		.then( function ( data ) {
			// Ignore stale responses if the value changed
			if ( self._pending[ fieldId ] !== value ) { return; }
			delete self._pending[ fieldId ];

			if ( null === data.available ) {
				self._clearStatus( fieldEl );
				return;
			}

			if ( data.available ) {
				self._setStatus( fieldEl, 'available', data.message || self._i18n( 'available', 'Available' ) );
				if ( fieldEl ) { fieldEl.removeAttribute( 'data-clefa-block-next' ); }
				if ( window.CLEFAEventDispatcher ) {
					CLEFAEventDispatcher.dispatch( 'clefa:live-check:success', { fieldId: fieldId, available: true, message: data.message }, self.formEl );
				}
			} else {
				self._setStatus( fieldEl, 'unavailable', data.message || self._i18n( 'unavailable', 'Not available' ) );
				// Keep next-step blocker
				if ( window.CLEFAEventDispatcher ) {
					CLEFAEventDispatcher.dispatch( 'clefa:live-check:failed', { fieldId: fieldId, available: false, message: data.message }, self.formEl );
				}
			}
		} )
		.catch( function () {
			delete self._pending[ fieldId ];
			self._clearStatus( fieldEl );
			if ( fieldEl ) { fieldEl.removeAttribute( 'data-clefa-block-next' ); }
		} );
	};

	LiveCheckManager.prototype._setStatus = function ( fieldEl, status, message ) {
		if ( ! fieldEl ) { return; }
		fieldEl.setAttribute( 'data-clefa-live-status', status );

		var msgEl = fieldEl.querySelector( '[data-clefa-live-msg]' );
		if ( ! msgEl ) {
			msgEl = document.createElement( 'span' );
			msgEl.setAttribute( 'data-clefa-live-msg', '' );
			msgEl.setAttribute( 'aria-live', 'polite' );
			fieldEl.appendChild( msgEl );
		}
		msgEl.textContent = message;
	};

	LiveCheckManager.prototype._clearStatus = function ( fieldEl ) {
		if ( ! fieldEl ) { return; }
		fieldEl.removeAttribute( 'data-clefa-live-status' );
		if ( fieldEl ) { fieldEl.removeAttribute( 'data-clefa-block-next' ); }
		var msgEl = fieldEl.querySelector( '[data-clefa-live-msg]' );
		if ( msgEl ) { msgEl.textContent = ''; }
	};

	LiveCheckManager.prototype._i18n = function ( key, fallback ) {
		return ( window.clefaFrontend && clefaFrontend.i18n && clefaFrontend.i18n[ key ] ) || fallback;
	};

	// Expose
	window.CLEFA = window.CLEFA || {};
	window.CLEFA.LiveCheckManager = LiveCheckManager;

} )();
