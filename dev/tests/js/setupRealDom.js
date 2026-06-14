/**
 * setupRealDom.js — Jest setupFilesAfterFramework for RealDom integration tests.
 *
 * Loads all frontend JS engines, exposes the real-dom helper functions as
 * globals, and replaces the server-dependent loadFixture() with a
 * filesystem reader + minimal HTML builder so RealDom.integration.test.js
 * runs without a live WordPress server.
 */

const path = require( 'path' );
const fs   = require( 'fs' );

const ROOT     = path.resolve( __dirname, '../../..' );
const JS_DIR   = path.join( ROOT, 'assets/frontend/js' );
const FIX_DIR  = path.join( ROOT, 'dev/fixtures/forms' );

// ---------------------------------------------------------------------------
// 1. Load frontend engine scripts into the jsdom global scope
// ---------------------------------------------------------------------------

[
	'EventDispatcher.js',
	'TransitionEngine.js',
	'ConditionEngine.js',
	'ValidationEngine.js',
	'StepRouter.js',
].forEach( ( file ) => {
	require( path.join( JS_DIR, file ) );
} );

// ---------------------------------------------------------------------------
// 2. Minimal fixture HTML builder (mirrors Fixture_Html_Renderer logic)
// ---------------------------------------------------------------------------

function escAttr( s ) {
	return String( s || '' )
		.replace( /&/g, '&amp;' )
		.replace( /</g, '&lt;' )
		.replace( />/g, '&gt;' )
		.replace( /"/g, '&quot;' );
}

function buildFieldHtml( field ) {
	const { field_id, field_type, label, required, placeholder, options, description, content } = field;
	const req  = required ? ' required' : '';
	const ph   = placeholder ? ` placeholder="${escAttr( placeholder )}"` : '';
	const fid  = escAttr( field_id );
	const type = escAttr( field_type );
	let   inputHtml = '';

	switch ( field_type ) {
		case 'radio':
			inputHtml = ( options || [] ).map( ( o ) =>
				`<label><input type="radio" data-clefa-input data-clefa-field-id="${fid}" name="${fid}" value="${escAttr( o.value )}"${req}> ${escAttr( o.label )}</label>`
			).join( '' );
			break;

		case 'checkbox':
			inputHtml = ( options || [] ).map( ( o ) =>
				`<label><input type="checkbox" data-clefa-input data-clefa-field-id="${fid}" name="${fid}" value="${escAttr( o.value )}"> ${escAttr( o.label )}</label>`
			).join( '' );
			break;

		case 'select': {
			const optHtml = ( options || [] ).map( ( o ) =>
				`<option value="${escAttr( o.value )}">${escAttr( o.label )}</option>`
			).join( '' );
			inputHtml = `<select data-clefa-input data-clefa-field-id="${fid}"${req}><option value=""></option>${optHtml}</select>`;
			break;
		}

		case 'textarea':
			inputHtml = `<textarea data-clefa-input data-clefa-field-id="${fid}"${req}${ph}></textarea>`;
			break;

		case 'notice':
		case 'html':
			inputHtml = `<div class="clefa-notice-content">${content || label || ''}</div>`;
			break;

		case 'hidden':
			inputHtml = `<input type="hidden" data-clefa-input data-clefa-field-id="${fid}">`;
			break;

		default: // text, email, number, password, date, url …
			inputHtml = `<input type="${type}" data-clefa-input data-clefa-field-id="${fid}"${req}${ph}>`;
			break;
	}

	const labelHtml = label
		? `<label class="clefa-label" data-clefa-label for="${fid}">${escAttr( label )}</label>`
		: '';
	const descHtml = description
		? `<p class="clefa-description" data-clefa-description>${escAttr( description )}</p>`
		: '';

	return (
		`<div class="clefa-field-wrap" data-clefa-field="${fid}" data-clefa-visible="1" aria-hidden="false">` +
		labelHtml + inputHtml + descHtml +
		`<span data-clefa-error class="clefa-error"></span>` +
		`</div>`
	);
}

function buildStepHtml( step, idx, total ) {
	const active   = idx === 0 ? '1' : '0';
	const ariaHide = idx === 0 ? 'false' : 'true';
	const fields   = ( step.fields || [] ).map( buildFieldHtml ).join( '' );

	let nav = '';
	if ( total > 1 ) {
		if ( idx > 0 )         nav += `<button type="button" data-clefa-prev>Previous</button>`;
		if ( idx < total - 1 ) nav += `<button type="button" data-clefa-next>Next</button>`;
		else                   nav += `<button type="submit" data-clefa-submit>Submit</button>`;
	}

	return (
		`<div class="clefa-step" data-clefa-step="${escAttr( step.step_id )}" ` +
		`data-clefa-step-active="${active}" aria-hidden="${ariaHide}">` +
		`<div class="clefa-fields-wrap">${fields}</div>${nav}</div>`
	);
}

function buildJsConfig( config ) {
	return {
		settings: config.settings || {},
		steps: ( config.steps || [] ).map( ( step ) => ( {
			step_id:   step.step_id   || '',
			step_name: step.step_name || '',
			routing:   step.routing   || [],
			fields: ( step.fields || [] ).map( ( f ) => ( {
				field_id:         f.field_id         || '',
				field_type:       f.field_type        || 'text',
				label:            f.label             || '',
				required:         !! f.required,
				validation_rules: f.validation_rules  || [],
				conditions:       f.conditions        || [],
			} ) ),
		} ) ),
	};
}

function buildFixtureHtml( slug, config, jsConfig ) {
	const total    = ( config.steps || [] ).length;
	const stepsHtml = ( config.steps || [] )
		.map( ( step, i ) => buildStepHtml( step, i, total ) )
		.join( '' );
	const cfgJson  = JSON.stringify( jsConfig ).replace( /"/g, '&quot;' );

	return (
		`<div class="clefa-form-wrap" data-clefa-form-wrap ` +
		`data-clefa-form-id="9000" data-clefa-instance="test-${escAttr( slug )}" ` +
		`data-clefa-config="${cfgJson}">` +
		`<form class="clefa-form" data-clefa-form-inner data-clefa-form-id="9000" ` +
		`data-clefa-instance="test-${escAttr( slug )}" novalidate>` +
		`<div class="clefa-steps-wrap">${stepsHtml}</div>` +
		`</form></div>`
	);
}

// ---------------------------------------------------------------------------
// 3. Filesystem-backed loadFixture (replaces the server-fetch version)
// ---------------------------------------------------------------------------

global.loadFixture = async function loadFixture( slug ) {
	const filePath = path.join( FIX_DIR, slug + '.json' );
	if ( ! fs.existsSync( filePath ) ) {
		throw new Error( 'Fixture file not found: ' + filePath );
	}

	const data     = JSON.parse( fs.readFileSync( filePath, 'utf8' ) );
	const config   = data.config || {};
	const jsConfig = buildJsConfig( config );
	const html     = buildFixtureHtml( slug, config, jsConfig );

	let mount = document.getElementById( 'clefa-test-mount' );
	if ( ! mount ) {
		mount = document.createElement( 'div' );
		mount.id = 'clefa-test-mount';
		document.body.appendChild( mount );
	}
	mount.innerHTML = html;

	const wrap   = mount.querySelector( '[data-clefa-form-wrap]' ) || mount.firstElementChild;
	const formEl = wrap
		? ( wrap.querySelector( '[data-clefa-form-inner]' ) || wrap.querySelector( 'form' ) )
		: null;

	if ( ! formEl ) {
		throw new Error( 'No form element built for fixture: ' + slug );
	}

	return { wrap, formEl, config: jsConfig };
};

// ---------------------------------------------------------------------------
// 4. Expose the remaining real-dom helpers as globals
//    (test files call these as bare identifiers, not via window.CLEFATest)
// ---------------------------------------------------------------------------

global.getFieldWrap = function getFieldWrap( formEl, fieldId ) {
	return formEl.querySelector( `[data-clefa-field="${fieldId}"]` );
};

global.setFieldValue = function setFieldValue( formEl, fieldId, value ) {
	const inputs = formEl.querySelectorAll( `[data-clefa-field-id="${fieldId}"]` );
	if ( ! inputs.length ) return;
	const first = inputs[ 0 ];
	if ( first.type === 'checkbox' ) {
		const vals = [].concat( value );
		inputs.forEach( ( inp ) => {
			inp.checked = vals.includes( inp.value );
			inp.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		} );
	} else if ( first.type === 'radio' ) {
		inputs.forEach( ( inp ) => {
			inp.checked = inp.value === value;
			if ( inp.checked ) inp.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		} );
	} else {
		first.value = value;
		first.dispatchEvent( new Event( 'input',  { bubbles: true } ) );
		first.dispatchEvent( new Event( 'change', { bubbles: true } ) );
	}
};

global.getErrorText = function getErrorText( formEl, fieldId ) {
	const wrapper = formEl.querySelector( `[data-clefa-field="${fieldId}"]` );
	if ( ! wrapper ) return null;
	const errEl = wrapper.querySelector( '[data-clefa-error]' );
	return errEl ? errEl.textContent : null;
};

global.hasErrorClass = function hasErrorClass( formEl, fieldId ) {
	const input = formEl.querySelector( `[data-clefa-field-id="${fieldId}"]` );
	return input ? input.classList.contains( 'clefa-input-error' ) : false;
};

global.isFieldVisible = function isFieldVisible( wrapper ) {
	if ( ! wrapper ) return false;
	if ( wrapper.getAttribute( 'data-clefa-visible' ) === '0' ) return false;
	return ! wrapper.classList.contains( 'clefa-field-hidden' );
};

global.layoutHeight = function layoutHeight( el ) {
	return el ? el.getBoundingClientRect().height : 0;
};

global.listenOnce = function listenOnce( el, eventName ) {
	return new Promise( ( resolve ) => {
		function handler( e ) {
			el.removeEventListener( eventName, handler );
			resolve( e );
		}
		el.addEventListener( eventName, handler );
	} );
};

global.waitTransition = async function waitTransition( el ) {
	if ( window.CLEFA && window.CLEFA.TransitionEngine ) {
		await window.CLEFA.TransitionEngine.whenSettled( el );
	}
};
