/* global clefaTestsData */
(function() {
	'use strict';

	if ( typeof clefaTestsData === 'undefined' ) { return; }

	var FIELDS    = clefaTestsData.fields || [];
	var testCount = 0;

	function esc( s ) {
		var d = document.createElement( 'div' );
		d.textContent = s || '';
		return d.innerHTML;
	}

	document.getElementById( 'clefa-add-test-case' ) && document.getElementById( 'clefa-add-test-case' ).addEventListener( 'click', addTestCase );

	function addTestCase() {
		testCount++;
		var tpl = document.getElementById( 'clefa-test-case-template' ).innerHTML
			.replace( /\{\{ID\}\}/g, testCount )
			.replace( /\{\{NUM\}\}/g, testCount );
		var wrap = document.getElementById( 'clefa-test-cases-wrap' );
		var hint = document.getElementById( 'clefa-no-tests-hint' );
		if ( hint ) { hint.style.display = 'none'; }
		var div = document.createElement( 'div' );
		div.innerHTML = tpl;
		var tc = div.firstElementChild;
		wrap.appendChild( tc );
		bindTestCase( tc );
	}

	function bindTestCase( tc ) {
		tc.querySelector( '.clefa-test-case-toggle' ).addEventListener( 'click', function() {
			var body = tc.querySelector( '.clefa-test-case-body' );
			var open = body.style.display !== 'none';
			body.style.display = open ? 'none' : '';
			this.querySelector( '.dashicons' ).className = 'dashicons ' + ( open ? 'dashicons-arrow-right-alt2' : 'dashicons-arrow-down-alt2' );
			this.setAttribute( 'aria-expanded', ! open );
		} );
		tc.querySelector( '.clefa-test-case-remove' ).addEventListener( 'click', function() { tc.remove(); } );
		tc.querySelector( '.clefa-add-test-field' ).addEventListener( 'click', function() { addFieldRow( tc ); } );
		tc.querySelector( '.clefa-add-assertion' ).addEventListener( 'click', function() { addAssertionRow( tc ); } );
	}

	function addFieldRow( tc ) {
		var wrap  = tc.querySelector( '.clefa-test-fields-wrap' );
		var empty = wrap.querySelector( '.clefa-test-fields-empty' );
		if ( empty ) { empty.style.display = 'none'; }
		var fieldOpts = FIELDS.map( function( f ) {
			return '<option value="' + esc( f.field_id ) + '">' + esc( f.label ) + ' (' + esc( f.field_id ) + ')</option>';
		} ).join( '' );
		var row = document.createElement( 'div' );
		row.className = 'clefa-test-field-row';
		row.innerHTML = '<select class="clefa-test-field-key">' + ( fieldOpts || '<option value="">field_id</option>' ) + '</select>'
			+ '<input type="text" class="clefa-test-field-value" placeholder="value">'
			+ '<button type="button" class="clefa-btn clefa-btn-ghost clefa-btn-xs clefa-remove-row"><span class="dashicons dashicons-no-alt"></span></button>';
		row.querySelector( '.clefa-remove-row' ).addEventListener( 'click', function() { row.remove(); } );
		wrap.appendChild( row );
	}

	function addAssertionRow( tc ) {
		var wrap  = tc.querySelector( '.clefa-test-assertions-wrap' );
		var empty = wrap.querySelector( '.clefa-assertions-empty' );
		if ( empty ) { empty.style.display = 'none'; }
		var fieldOpts = FIELDS.map( function( f ) {
			return '<option value="' + esc( f.field_id ) + '">' + esc( f.label ) + '</option>';
		} ).join( '' );
		var row = document.createElement( 'div' );
		row.className = 'clefa-assertion-row';
		row.innerHTML = '<select class="clefa-assertion-type">'
			+ '<option value="field_visible">Field is visible</option>'
			+ '<option value="field_hidden">Field is hidden</option>'
			+ '<option value="field_has_error">Field has error</option>'
			+ '<option value="field_no_error">Field has no error</option>'
			+ '<option value="action_success">Action succeeds</option>'
			+ '</select>'
			+ '<select class="clefa-assertion-field">' + ( fieldOpts || '<option value="">field_id</option>' ) + '</select>'
			+ '<button type="button" class="clefa-btn clefa-btn-ghost clefa-btn-xs clefa-remove-row"><span class="dashicons dashicons-no-alt"></span></button>';
		row.querySelector( '.clefa-remove-row' ).addEventListener( 'click', function() { row.remove(); } );
		wrap.appendChild( row );
	}

	function collectTestCases() {
		var cases = [];
		document.querySelectorAll( '.clefa-test-case' ).forEach( function( tc ) {
			var name        = ( tc.querySelector( '.clefa-test-case-name' ) || {} ).value || 'Test';
			var expectPass  = ( tc.querySelector( '.clefa-test-expect-pass' ) || {} ).checked !== false;
			var skipActions = ( tc.querySelector( '.clefa-test-skip-actions' ) || {} ).checked;
			var data        = {};
			tc.querySelectorAll( '.clefa-test-field-row' ).forEach( function( row ) {
				var k = ( row.querySelector( '.clefa-test-field-key' ) || {} ).value;
				var v = ( row.querySelector( '.clefa-test-field-value' ) || {} ).value;
				if ( k ) { data[ k ] = v; }
			} );
			var assertions = [];
			tc.querySelectorAll( '.clefa-assertion-row' ).forEach( function( row ) {
				assertions.push( {
					type : ( row.querySelector( '.clefa-assertion-type' ) || {} ).value,
					field: ( row.querySelector( '.clefa-assertion-field' ) || {} ).value,
				} );
			} );
			cases.push( { name: name, expect_pass: expectPass, skip_actions: skipActions, data: data, assertions: assertions } );
		} );
		return cases;
	}

	var form = document.getElementById( 'clefa-integration-form' );
	if ( form ) {
		form.addEventListener( 'submit', function( e ) {
			var testCases = collectTestCases();
			if ( ! testCases.length ) {
				e.preventDefault();
				window.alert( 'Add at least one test case first.' );
				return;
			}
			document.getElementById( 'clefa-test-cases-json' ).value = JSON.stringify( testCases );
			var btn = document.getElementById( 'clefa-run-tests' );
			if ( btn ) {
				btn.disabled = true;
			}
		} );
	}
})();
