/**
 * CLEFA TransitionEngine
 * Attribute-driven visibility for fields and steps (CSS transitions, no layout thrashing when enabled).
 */
( function () {
	'use strict';

	window.CLEFA = window.CLEFA || {};

	function getWrap( el ) {
		return el && el.closest ? el.closest( '[data-clefa-form-wrap]' ) : null;
	}

	function transitionsEnabled( el ) {
		if ( ! el ) {
			return false;
		}
		var wrap = getWrap( el );
		if ( wrap && wrap.getAttribute( 'data-clefa-transitions' ) === '1' ) {
			return true;
		}
		return el.getAttribute( 'data-clefa-transitions' ) === '1';
	}

	function prefersReducedMotion() {
		return window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
	}

	function useTransitions( el ) {
		return transitionsEnabled( el ) && ! prefersReducedMotion();
	}

	function setInputsDisabled( wrapper, disabled ) {
		if ( ! wrapper ) {
			return;
		}
		wrapper.querySelectorAll( 'input, textarea, select, button' ).forEach( function ( inp ) {
			if ( inp.hasAttribute( 'data-clefa-next' ) || inp.hasAttribute( 'data-clefa-prev' ) || inp.hasAttribute( 'data-clefa-submit' ) ) {
				return;
			}
			inp.disabled = !! disabled;
		} );
	}

	var TransitionEngine = {
		isEnabled: useTransitions,

		setFieldVisible: function ( wrapper, visible ) {
			if ( ! wrapper ) {
				return;
			}
			var next = visible ? '1' : '0';
			wrapper.setAttribute( 'data-clefa-visible', next );
			wrapper.setAttribute( 'aria-hidden', visible ? 'false' : 'true' );

			if ( useTransitions( wrapper ) ) {
				wrapper.style.display = '';
				wrapper.classList.toggle( 'clefa-field-hidden', ! visible );
			} else {
				wrapper.classList.remove( 'clefa-field-hidden' );
				wrapper.style.display = visible ? '' : 'none';
			}

			setInputsDisabled( wrapper, ! visible );
		},

		setStepActive: function ( stepEl, active ) {
			if ( ! stepEl ) {
				return;
			}
			stepEl.setAttribute( 'data-clefa-step-active', active ? '1' : '0' );
			stepEl.setAttribute( 'aria-hidden', active ? 'false' : 'true' );

			if ( useTransitions( stepEl ) ) {
				stepEl.style.display = '';
				stepEl.classList.toggle( 'clefa-step-hidden', ! active );
			} else {
				stepEl.classList.remove( 'clefa-step-hidden' );
				stepEl.style.display = active ? '' : 'none';
			}
		},

		/** Wait for CSS transition end (for tests and async hooks). */
		whenSettled: function ( el, timeoutMs ) {
			timeoutMs = timeoutMs || 400;
			return new Promise( function ( resolve ) {
				if ( ! el || ! useTransitions( el ) ) {
					resolve();
					return;
				}
				var done = false;
				function finish() {
					if ( done ) {
						return;
					}
					done = true;
					el.removeEventListener( 'transitionend', onEnd );
					resolve();
				}
				function onEnd( e ) {
					if ( e.target === el ) {
						finish();
					}
				}
				el.addEventListener( 'transitionend', onEnd );
				window.setTimeout( finish, timeoutMs );
			} );
		},
	};

	window.CLEFA.TransitionEngine = TransitionEngine;
}() );
