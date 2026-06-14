/**
 * Real DOM helpers — load production-rendered fixture HTML (no mocks).
 */

function ensureMount() {
	var mount = document.getElementById( 'clefa-test-mount' );
	if ( ! mount ) {
		mount = document.createElement( 'div' );
		mount.id = 'clefa-test-mount';
		document.body.appendChild( mount );
	}
	return mount;
}

async function loadFixture( slug ) {
	var base = window.clefaJsSuiteData && window.clefaJsSuiteData.fixtureUrl;
	if ( ! base ) {
		throw new Error( 'Fixture URL not configured' );
	}
	var res = await fetch( base + '&slug=' + encodeURIComponent( slug ), { credentials: 'same-origin' } );
	if ( ! res.ok ) {
		throw new Error( 'Fixture load failed: ' + slug );
	}
	var payload = await res.json();
	var data = payload.data || payload;
	var mount = ensureMount();
	mount.innerHTML = data.html || '';
	var wrap = mount.querySelector( '[data-clefa-form-wrap]' ) || mount.firstElementChild;
	var formEl = wrap ? ( wrap.querySelector( '[data-clefa-form-inner]' ) || wrap.querySelector( 'form' ) ) : null;
	if ( ! formEl ) {
		throw new Error( 'No form element in fixture: ' + slug );
	}
	return {
		wrap: wrap,
		formEl: formEl,
		config: data.js_config || data.config || {},
	};
}

function getFieldWrap( formEl, fieldId ) {
	return formEl.querySelector( '[data-clefa-field="' + fieldId + '"]' );
}

function setFieldValue( formEl, fieldId, value ) {
	var inputs = formEl.querySelectorAll( '[data-clefa-field-id="' + fieldId + '"]' );
	if ( ! inputs.length ) {
		return;
	}
	var first = inputs[ 0 ];
	if ( first.type === 'checkbox' ) {
		var values = [].concat( value );
		inputs.forEach( function ( inp ) {
			inp.checked = values.indexOf( inp.value ) !== -1;
			inp.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		} );
	} else if ( first.type === 'radio' ) {
		inputs.forEach( function ( inp ) {
			inp.checked = inp.value === value;
			if ( inp.checked ) {
				inp.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			}
		} );
	} else {
		first.value = value;
		first.dispatchEvent( new Event( 'input', { bubbles: true } ) );
		first.dispatchEvent( new Event( 'change', { bubbles: true } ) );
	}
}

function getErrorText( formEl, fieldId ) {
	var wrapper = getFieldWrap( formEl, fieldId );
	if ( ! wrapper ) {
		return null;
	}
	var errEl = wrapper.querySelector( '[data-clefa-error]' );
	return errEl ? errEl.textContent : null;
}

function hasErrorClass( formEl, fieldId ) {
	var input = formEl.querySelector( '[data-clefa-field-id="' + fieldId + '"]' );
	return input ? input.classList.contains( 'clefa-input-error' ) : false;
}

function isFieldVisible( wrapper ) {
	if ( ! wrapper ) {
		return false;
	}
	if ( wrapper.getAttribute( 'data-clefa-visible' ) === '0' ) {
		return false;
	}
	return ! wrapper.classList.contains( 'clefa-field-hidden' );
}

function layoutHeight( el ) {
	return el ? el.getBoundingClientRect().height : 0;
}

function listenOnce( el, eventName ) {
	return new Promise( function ( resolve ) {
		function handler( e ) {
			el.removeEventListener( eventName, handler );
			resolve( e );
		}
		el.addEventListener( eventName, handler );
	} );
}

async function waitTransition( el ) {
	if ( window.CLEFA && window.CLEFA.TransitionEngine ) {
		await window.CLEFA.TransitionEngine.whenSettled( el );
	}
}

function parseFormConfig( formEl ) {
	var wrap = formEl.closest( '[data-clefa-config]' );
	if ( ! wrap ) {
		return { steps: [] };
	}
	try {
		return JSON.parse( wrap.getAttribute( 'data-clefa-config' ) || '{}' );
	} catch ( e ) {
		return { steps: [] };
	}
}

window.CLEFATest = window.CLEFATest || {};
window.CLEFATest.helpers.loadFixture = loadFixture;
window.CLEFATest.helpers.getFieldWrap = getFieldWrap;
window.CLEFATest.helpers.setFieldValue = setFieldValue;
window.CLEFATest.helpers.getErrorText = getErrorText;
window.CLEFATest.helpers.hasErrorClass = hasErrorClass;
window.CLEFATest.helpers.isFieldVisible = isFieldVisible;
window.CLEFATest.helpers.layoutHeight = layoutHeight;
window.CLEFATest.helpers.listenOnce = listenOnce;
window.CLEFATest.helpers.waitTransition = waitTransition;
window.CLEFATest.helpers.parseFormConfig = parseFormConfig;
