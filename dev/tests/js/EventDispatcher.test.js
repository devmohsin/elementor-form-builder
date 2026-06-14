/**

 * EventDispatcher — dispatch, bubbling, debug mode.

 */

const { describe, test, expect, beforeEach } = require( '@jest/globals' );



require( '../../../assets/frontend/js/EventDispatcher.js' );



describe( 'EventDispatcher', () => {

	beforeEach( () => {

		window.CLEFAEventDispatcher.setDebug( false );

	} );



	test( 'dispatch fires CustomEvent on target element', () => {

		const el = document.createElement( 'div' );

		const handler = jest.fn();

		el.addEventListener( 'clefa:form:ready', handler );

		window.CLEFAEventDispatcher.dispatch( 'clefa:form:ready', { formId: 1 }, el );

		expect( handler ).toHaveBeenCalledTimes( 1 );

		expect( handler.mock.calls[ 0 ][ 0 ].detail.formId ).toBe( 1 );

	} );



	test( 'event bubbles to document', () => {

		const el = document.createElement( 'div' );

		document.body.appendChild( el );

		const handler = jest.fn();

		document.addEventListener( 'clefa:field:changed', handler );

		window.CLEFAEventDispatcher.dispatch( 'clefa:field:changed', { fieldId: 'email' }, el );

		expect( handler ).toHaveBeenCalled();

		el.remove();

	} );



	test( 'dispatch falls back to document when no target', () => {

		const handler = jest.fn();

		document.addEventListener( 'clefa:form:success', handler );

		window.CLEFAEventDispatcher.dispatch( 'clefa:form:success', { ok: true } );

		expect( handler ).toHaveBeenCalled();

	} );



	test( 'setDebug enables console logging without throwing', () => {

		const spy = jest.spyOn( console, 'groupCollapsed' ).mockImplementation( () => {} );

		const infoSpy = jest.spyOn( console, 'info' ).mockImplementation( () => {} );

		const endSpy = jest.spyOn( console, 'groupEnd' ).mockImplementation( () => {} );

		window.CLEFAEventDispatcher.setDebug( true );

		window.CLEFAEventDispatcher.dispatch( 'clefa:form:init', { formId: 2 } );

		expect( spy ).toHaveBeenCalled();

		spy.mockRestore();

		infoSpy.mockRestore();

		endSpy.mockRestore();

	} );

} );


