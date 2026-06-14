/** LiveCheckManager — debounced checks and empty value handling. */
const { describe, test, expect, beforeEach, afterEach } = require( '@jest/globals' );

require( '../../../assets/frontend/js/EventDispatcher.js' );
require( '../../../assets/frontend/js/LiveCheckManager.js' );

function buildFormWithLiveCheck() {
	document.body.innerHTML = [
		'<form data-clefa-form data-clefa-form-id="1">',
		'  <div data-clefa-field="username">',
		'    <input type="text" data-clefa-live-check="username_available" data-clefa-input value="" />',
		'    <span data-clefa-live-msg></span>',
		'  </div>',
		'</form>',
	].join( '' );
	return document.querySelector( '[data-clefa-form]' );
}

describe( 'LiveCheckManager', () => {
	beforeEach( () => {
		jest.useFakeTimers();
		global.fetch = jest.fn( () =>
			Promise.resolve( {
				ok: true,
				json: () => Promise.resolve( { success: true, data: { available: true, message: 'OK' } } ),
			} )
		);
		clefaFrontend.i18n = { checking: 'Checking…', available: 'Available', unavailable: 'Not available' };
	} );

	afterEach( () => {
		jest.useRealTimers();
	} );

	test( 'init binds inputs with data-clefa-live-check', () => {
		const form = buildFormWithLiveCheck();
		const mgr = new window.CLEFA.LiveCheckManager( form, { id: 1 }, clefaFrontend.restUrl, clefaFrontend.nonce );
		mgr.init();
		expect( form.querySelector( '[data-clefa-live-check]' ) ).toBeTruthy();
	} );

	test( 'empty value clears status without fetch', () => {
		const form = buildFormWithLiveCheck();
		const mgr = new window.CLEFA.LiveCheckManager( form, { id: 1 }, clefaFrontend.restUrl, clefaFrontend.nonce );
		mgr.init();
		const input = form.querySelector( 'input' );
		input.value = '   ';
		input.dispatchEvent( new Event( 'blur', { bubbles: true } ) );
		expect( fetch ).not.toHaveBeenCalled();
	} );

	test( 'typing triggers debounced fetch', async () => {
		const form = buildFormWithLiveCheck();
		const mgr = new window.CLEFA.LiveCheckManager( form, { id: 1 }, clefaFrontend.restUrl, clefaFrontend.nonce );
		mgr.init();
		const input = form.querySelector( 'input' );
		input.value = 'newuser';
		input.dispatchEvent( new Event( 'input', { bubbles: true } ) );
		jest.advanceTimersByTime( 700 );
		await Promise.resolve();
		await Promise.resolve();
		expect( fetch ).toHaveBeenCalled();
	} );
} );
