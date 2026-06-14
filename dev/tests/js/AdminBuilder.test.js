/** Admin builder — pure helpers and state mutations (admin-builder.js). */
const { describe, test, expect, beforeEach } = require( '@jest/globals' );

require( '../../../assets/admin/js/admin-builder.js' );

const BT = () => window.CLEFA.BuilderTest;

describe( 'Admin builder module loaded', () => {
	test( 'CLEFA.BuilderTest is exposed', () => {
		expect( BT() ).toBeDefined();
		expect( typeof BT().makeStep ).toBe( 'function' );
	} );
} );

describe( 'BuilderTest.uid', () => {
	test( 'returns string starting with f_', () => {
		const id = BT().uid();
		expect( typeof id ).toBe( 'string' );
		expect( id.indexOf( 'f_' ) ).toBe( 0 );
	} );

	test( 'generates unique ids', () => {
		expect( BT().uid() ).not.toBe( BT().uid() );
	} );
} );

describe( 'BuilderTest.makeStep', () => {
	test( 'creates step with fields array', () => {
		const step = BT().makeStep( 'step_a', 'Step A' );
		expect( step.step_id ).toBe( 'step_a' );
		expect( step.step_name ).toBe( 'Step A' );
		expect( Array.isArray( step.fields ) ).toBe( true );
		expect( step.fields ).toHaveLength( 0 );
	} );

	test( 'defaults step name when omitted', () => {
		expect( BT().makeStep( 'x' ).step_name ).toBe( 'Step' );
	} );
} );

describe( 'BuilderTest.makeField', () => {
	test( 'creates text field with validation_rules array', () => {
		const field = BT().makeField( 'text' );
		expect( field.field_type ).toBe( 'text' );
		expect( field.field_id ).toMatch( /^f_/ );
		expect( Array.isArray( field.validation_rules ) ).toBe( true );
		expect( Array.isArray( field.conditions ) ).toBe( true );
	} );

	test( 'uses field type label from fieldTypes config', () => {
		expect( BT().makeField( 'email' ).label ).toBe( 'Email' );
	} );
} );

describe( 'BuilderTest.esc', () => {
	test( 'escapes HTML entities', () => {
		expect( BT().esc( '<b>"x"&</b>' ) ).toBe( '&lt;b&gt;"x"&amp;&lt;/b&gt;' );
	} );

	test( 'handles empty string', () => {
		expect( BT().esc( '' ) ).toBe( '' );
	} );
} );

describe( 'BuilderTest.setNestedValue', () => {
	test( 'sets dot-path keys on object', () => {
		const obj = { a: { b: 1 } };
		BT().setNestedValue( obj, 'a.c', 2 );
		expect( obj.a.c ).toBe( 2 );
	} );

	test( 'creates intermediate objects', () => {
		const obj = {};
		BT().setNestedValue( obj, 'x.y.z', 'ok' );
		expect( obj.x.y.z ).toBe( 'ok' );
	} );
} );

describe( 'BuilderTest.isDefinitionAvailable', () => {
	test( 'returns true when no requires', () => {
		expect( BT().isDefinitionAvailable( { available: true } ) ).toBe( true );
	} );

	test( 'returns false when dependency missing', () => {
		expect( BT().isDefinitionAvailable( { requires: [ 'woocommerce' ] } ) ).toBe( false );
	} );

	test( 'returns true when definition.available is true', () => {
		expect( BT().isDefinitionAvailable( { available: true, requires: [ 'missing' ] } ) ).toBe( true );
	} );
} );

describe( 'BuilderTest.getDisabledReason', () => {
	test( 'uses disabled_reason from definition', () => {
		expect( BT().getDisabledReason( { disabled_reason: 'Need WooCommerce' } ) ).toBe( 'Need WooCommerce' );
	} );
} );

describe( 'BuilderTest.getFieldLabel / getFieldIcon', () => {
	test( 'getFieldLabel resolves from fieldTypes', () => {
		expect( BT().getFieldLabel( 'text' ) ).toBe( 'Text' );
	} );

	test( 'getFieldIcon returns dashicons class', () => {
		expect( BT().getFieldIcon( 'email' ) ).toBe( 'dashicons-email' );
	} );

	test( 'getFieldLabel title-cases unknown types', () => {
		expect( BT().getFieldLabel( 'phone_number' ) ).toMatch( /Phone/ );
	} );
} );

describe( 'BuilderTest.getActionDef', () => {
	test( 'returns matching action definition', () => {
		const def = BT().getActionDef( 'email' );
		expect( def.label ).toBe( 'Email' );
	} );

	test( 'falls back for unknown action type', () => {
		expect( BT().getActionDef( 'unknown_xyz' ).label ).toBe( 'unknown_xyz' );
	} );
} );

describe( 'BuilderTest state mutations', () => {
	beforeEach( () => {
		window.confirm = jest.fn( () => true );
		BT().markClean();
		const st = BT().getState();
		st.form.steps = [ BT().makeStep( 'step_1', 'One' ) ];
		st.form.steps[ 0 ].fields = [];
	} );

	test( 'addStep appends to form.steps', () => {
		const before = BT().getState().form.steps.length;
		BT().addStep();
		expect( BT().getState().form.steps.length ).toBe( before + 1 );
	} );

	test( 'addFieldToStep adds field to step', () => {
		BT().addFieldToStep( 'step_1', BT().makeField( 'text' ) );
		expect( BT().getState().form.steps[ 0 ].fields ).toHaveLength( 1 );
	} );

	test( 'deleteField removes field by id', () => {
		const field = BT().makeField( 'text' );
		BT().addFieldToStep( 'step_1', field );
		BT().deleteField( field.field_id );
		expect( BT().findField( field.field_id ) ).toBeNull();
	} );

	test( 'deleteStep removes step when confirmed', () => {
		BT().addStep();
		const id = BT().getState().form.steps[ 1 ].step_id;
		BT().deleteStep( id );
		expect( BT().getState().form.steps.find( ( s ) => s.step_id === id ) ).toBeUndefined();
	} );

	test( 'markDirty sets isDirty true', () => {
		BT().markClean();
		BT().markDirty();
		expect( BT().getState().isDirty ).toBe( true );
	} );

	test( 'markClean clears isDirty', () => {
		BT().markDirty();
		BT().markClean();
		expect( BT().getState().isDirty ).toBe( false );
	} );
} );

describe( 'BuilderTest.render', () => {
	beforeEach( () => {
		document.body.innerHTML = [
			'<div data-clefa-role="field-groups"></div>',
			'<div data-clefa-role="steps-wrap"></div>',
			'<div data-clefa-role="settings-form"></div>',
			'<div data-clefa-role="actions-list"></div>',
			'<div data-clefa-role="notifications-list"></div>',
			'<input id="clefa-form-name" />',
		].join( '' );
	} );

	test( 'render populates field-groups sidebar', () => {
		BT().render();
		expect( document.querySelector( '[data-clefa-role="field-groups"]' ).innerHTML ).toContain( 'Text' );
	} );

	test( 'render populates form canvas with default step', () => {
		BT().render();
		expect( document.querySelector( '[data-clefa-role="steps-wrap"]' ).innerHTML ).toMatch( /step/i );
	} );
} );
