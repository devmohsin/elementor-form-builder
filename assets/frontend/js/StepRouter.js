/**
 * CLEFA StepRouter
 * Manages multi-step form navigation with validation gates and conditional routing.
 */
( function () {
	'use strict';

	window.CLEFA = window.CLEFA || {};

	function StepRouter( formEl, config, validationEngine ) {
		this.formEl           = formEl;
		this.config           = config;
		this.validationEngine = validationEngine;
		this.steps            = [];
		this.stepEls          = [];
		this.currentIndex     = 0;

		this._buildStepIndex();
	}

	StepRouter.prototype._buildStepIndex = function () {
		var self = this;
		self.steps   = self.config.steps || [];
		self.stepEls = Array.from( self.formEl.querySelectorAll( '[data-clefa-step]' ) );
	};

	StepRouter.prototype.init = function () {
		var self = this;

		self.formEl.addEventListener( 'click', function ( e ) {
			var btn = e.target.closest( '[data-clefa-next]' );
			if ( btn ) { e.preventDefault(); self.goNext(); return; }

			btn = e.target.closest( '[data-clefa-prev]' );
			if ( btn ) { e.preventDefault(); self.goPrev(); return; }
		} );

		self._showStep( 0 );

		// Button mode wiring: disabled-until-valid / hidden-until-valid
		self._bindButtonModes();
		self.formEl.addEventListener( 'input',  function () { self._syncButtonMode( self.currentIndex ); } );
		self.formEl.addEventListener( 'change', function () { self._syncButtonMode( self.currentIndex ); } );
		// Re-check when conditions show/hide fields
		self.formEl.addEventListener( 'clefa:condition:matched',   function () { self._syncButtonMode( self.currentIndex ); } );
		self.formEl.addEventListener( 'clefa:condition:unmatched', function () { self._syncButtonMode( self.currentIndex ); } );
	};

	StepRouter.prototype.goNext = function () {
		var self    = this;
		var current = self.stepEls[ self.currentIndex ];
		if ( ! current ) return;

		// Check for async blockers (live-check pending or failed)
		var blockers = current.querySelectorAll( '[data-clefa-block-next]' );
		if ( blockers.length ) {
			var blockerMessages = [];
			blockers.forEach( function ( el ) {
				var reason = el.getAttribute( 'data-clefa-block-next' ) || '';
				if ( reason ) blockerMessages.push( reason );
			} );
			self.formEl.dispatchEvent( new CustomEvent( 'clefa:step:blocked', {
				bubbles: true,
				detail: { step: self.currentIndex, reasons: blockerMessages }
			} ) );
			return;
		}

		// Validate current step – always show errors, regardless of button mode
		if ( self.validationEngine ) {
			var errors = self.validationEngine.validateStep( current );
			if ( Object.keys( errors ).length ) {
				self.validationEngine.showServerErrors( errors );

				// Shake the primary forward button to give tactile feedback
				var btnsWrap   = current.querySelector( '[data-clefa-btn-mode]' );
				var primaryBtn = btnsWrap && btnsWrap.querySelector( '[data-clefa-next], [data-clefa-submit]' );
				if ( primaryBtn ) {
					primaryBtn.classList.remove( 'clefa-btn-shake' );
					// Force reflow so the animation re-triggers on repeat clicks
					void primaryBtn.offsetWidth;
					primaryBtn.classList.add( 'clefa-btn-shake' );
					primaryBtn.addEventListener( 'animationend', function onEnd() {
						primaryBtn.classList.remove( 'clefa-btn-shake' );
						primaryBtn.removeEventListener( 'animationend', onEnd );
					} );
				}

				self.formEl.dispatchEvent( new CustomEvent( 'clefa:step:validation-failed', {
					bubbles: true, detail: { step: self.currentIndex, errors: errors }
				} ) );
				return;
			}
		}

		// Check conditional routing
		var stepConfig = self.steps[ self.currentIndex ] || {};
		var routing    = stepConfig.routing || [];
		var targetIdx  = null;

		if ( routing.length && window.CLEFA && window.CLEFA.ConditionEngine ) {
			var allData = self._collectData();
			for ( var i = 0; i < routing.length; i++ ) {
				var rule = routing[ i ];
				if ( CLEFA.ConditionEngine.compare( allData[ rule.source_field ] || '', rule.operator, rule.compare_value ) ) {
					targetIdx = self._findStepIndexById( rule.target_step_id );
					break;
				}
			}
		}

		if ( targetIdx !== null && targetIdx !== undefined && targetIdx !== self.currentIndex ) {
			self._showStep( targetIdx );
		} else if ( self.currentIndex < self.stepEls.length - 1 ) {
			self._showStep( self.currentIndex + 1 );
		}
	};

	StepRouter.prototype.goPrev = function () {
		if ( this.currentIndex > 0 ) {
			this._showStep( this.currentIndex - 1 );
		}
	};

	/**
	 * Bind initial button mode state for each step.
	 * Steps with data-clefa-btn-mode="disabled-until-valid" or
	 * "hidden-until-valid" will have their primary action button managed.
	 */
	StepRouter.prototype._bindButtonModes = function () {
		var self = this;
		self.stepEls.forEach( function ( stepEl, i ) {
			self._syncButtonModeForStep( stepEl, i );
		} );
	};

	/**
	 * Re-evaluate the primary button state for the currently shown step.
	 *
	 * @param {number} stepIndex
	 */
	StepRouter.prototype._syncButtonMode = function ( stepIndex ) {
		var el = this.stepEls[ stepIndex ];
		if ( el ) { this._syncButtonModeForStep( el, stepIndex ); }
	};

	StepRouter.prototype._syncButtonModeForStep = function ( stepEl, stepIndex ) {
		var btnsWrap = stepEl.querySelector( '[data-clefa-btn-mode]' );
		if ( ! btnsWrap ) return;

		var mode = btnsWrap.getAttribute( 'data-clefa-btn-mode' ) || 'always';
		if ( mode === 'always' ) return;

		// Identify the primary forward button (next or submit)
		var primaryBtn = btnsWrap.querySelector( '[data-clefa-next], [data-clefa-submit]' );
		if ( ! primaryBtn ) return;

		// Check if all required visible fields in this step are non-empty
		var allFilled = this._stepRequiredFilled( stepEl );

		if ( mode === 'disabled-until-valid' ) {
			// Fully disable – user cannot click at all
			primaryBtn.disabled = ! allFilled;
			primaryBtn.setAttribute( 'aria-disabled', allFilled ? 'false' : 'true' );
		} else if ( mode === 'always-validate' ) {
			// Visual grey only – button stays clickable, errors shown on click
			primaryBtn.disabled = false;
			primaryBtn.setAttribute( 'aria-disabled', 'false' );
			primaryBtn.setAttribute( 'data-clefa-btn-state', allFilled ? 'ready' : 'pending' );
		} else if ( mode === 'hidden-until-valid' ) {
			primaryBtn.style.display = allFilled ? '' : 'none';
		}
	};

	/**
	 * Returns true when all required, visible inputs in the step have a value.
	 *
	 * @param {Element} stepEl
	 * @returns {boolean}
	 */
	StepRouter.prototype._stepRequiredFilled = function ( stepEl ) {
		var inputs = stepEl.querySelectorAll( '[data-clefa-input][required]:not([disabled])' );
		for ( var i = 0; i < inputs.length; i++ ) {
			var inp     = inputs[ i ];
			var wrapper = inp.closest( '[data-clefa-field]' );
			// Skip invisible fields (hidden by condition engine)
			if ( wrapper && wrapper.getAttribute( 'data-clefa-visible' ) === '0' ) { continue; }

			if ( inp.type === 'checkbox' || inp.type === 'radio' ) {
				var name   = inp.getAttribute( 'name' );
				var checked = name
					? stepEl.querySelector( '[name="' + name + '"]:checked' )
					: inp.checked;
				if ( ! checked ) { return false; }
			} else {
				if ( ! inp.value.trim() ) { return false; }
			}
		}
		return true;
	};

	StepRouter.prototype._showStep = function ( index ) {
		var self = this;
		if ( index < 0 || index >= self.stepEls.length ) return;

		// Fire before-change so developers can intercept
		self.formEl.dispatchEvent( new CustomEvent( 'clefa:step:before-change', {
			bubbles: true,
			detail: {
				fromIndex: self.currentIndex,
				toIndex:   index,
				total:     self.stepEls.length,
			}
		} ) );

		self.stepEls.forEach( function ( el, i ) {
			var isActive = i === index;
			if ( window.CLEFA && window.CLEFA.TransitionEngine ) {
				window.CLEFA.TransitionEngine.setStepActive( el, isActive );
			} else {
				el.setAttribute( 'data-clefa-step-active', isActive ? '1' : '0' );
				el.style.display = isActive ? '' : 'none';
			}
		} );

		self.currentIndex = index;

		// Update progress
		self._updateProgress();

		// Re-check button mode for the newly visible step
		self._syncButtonMode( index );

		// Scroll form to top
		self.formEl.scrollIntoView( { behavior: 'smooth', block: 'start' } );

		self.formEl.dispatchEvent( new CustomEvent( 'clefa:step:changed', {
			bubbles: true,
			detail: {
				currentIndex: index,
				total:        self.stepEls.length,
				stepId:       ( self.steps[ index ] || {} ).step_id || '',
			}
		} ) );
	};

	StepRouter.prototype._updateProgress = function () {
		var self      = this;
		var total     = self.stepEls.length;
		var current   = self.currentIndex + 1;
		var pct       = Math.round( ( self.currentIndex / ( total - 1 ) ) * 100 );
		var progressEl = self.formEl.querySelector( '[data-clefa-progress-bar]' );
		var countEl    = self.formEl.querySelector( '[data-clefa-step-count]' );

		if ( progressEl ) {
			progressEl.style.width = pct + '%';
			progressEl.setAttribute( 'aria-valuenow', pct );
		}
		if ( countEl ) {
			countEl.textContent = current + ' / ' + total;
		}

		// Update nav dots
		self.formEl.querySelectorAll( '[data-clefa-step-dot]' ).forEach( function ( dot, i ) {
			dot.setAttribute( 'data-clefa-active', i === self.currentIndex ? '1' : '0' );
			dot.setAttribute( 'aria-current', i === self.currentIndex ? 'step' : 'false' );
		} );
	};

	StepRouter.prototype._findStepIndexById = function ( stepId ) {
		for ( var i = 0; i < this.steps.length; i++ ) {
			if ( this.steps[ i ].step_id === stepId ) return i;
		}
		return null;
	};

	StepRouter.prototype._collectData = function () {
		var data = {};
		var inputs = this.formEl.querySelectorAll( '[data-clefa-input]' );
		inputs.forEach( function ( input ) {
			var id = input.getAttribute( 'data-clefa-field-id' );
			if ( ! id ) return;
			if ( input.type === 'checkbox' ) {
				if ( ! data[ id ] ) data[ id ] = [];
				if ( input.checked ) data[ id ].push( input.value );
			} else if ( input.type === 'radio' ) {
				if ( input.checked ) data[ id ] = input.value;
			} else {
				data[ id ] = input.value;
			}
		} );
		return data;
	};

	StepRouter.prototype.isLastStep = function () {
		return this.currentIndex >= this.stepEls.length - 1;
	};

	StepRouter.prototype.getCurrentIndex = function () {
		return this.currentIndex;
	};

	window.CLEFA.StepRouter = StepRouter;

} () );
