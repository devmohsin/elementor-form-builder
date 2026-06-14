/**
 * ValidationEngine + ValidationRegistry — comprehensive in-browser test suite
 *
 * Uses the new validation_rules array format:
 *   field.validation_rules = [ { rule: 'min_length', value: '5', message: '' } ]
 *
 * Every client-side rule registered in ValidationEngine.js is tested here.
 */

const path = require( 'path' );
const { makeForm, setFieldValue, getErrorText, hasErrorClass } = require( './helpers/dom' );

beforeAll( () => {
  require( path.resolve( __dirname, '../../../assets/frontend/js/ValidationEngine.js' ) );
} );

afterEach( () => {
  document.body.innerHTML = '';
} );

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function rule( key, value = null, message = '' ) {
  return { rule: key, value, message };
}

function makeEngine( fields, stepOpts ) {
  const { formEl, config } = makeForm( fields, stepOpts );
  const engine = new window.CLEFA.ValidationEngine( formEl, config );
  return { engine, formEl };
}

// Build a field spec for makeForm using validation_rules
function vField( id, type, rules = [], required = false ) {
  return { id, type, required, validation_rules: rules };
}

// ---------------------------------------------------------------------------
// ValidationRegistry — direct rule tests
// ---------------------------------------------------------------------------

describe( 'ValidationRegistry — min_length', () => {
  const R = () => window.CLEFA.ValidationRegistry;
  test( 'passes when meets minimum',  () => expect( R().check( 'min_length', 'abcde', '5' ) ).toBeNull() );
  test( 'fails when below minimum',   () => expect( R().check( 'min_length', 'abc', '5' ) ).toBeTruthy() );
  test( 'zero param skipped',         () => expect( R().check( 'min_length', '', '0' ) ).toBeNull() );
} );

describe( 'ValidationRegistry — max_length', () => {
  const R = () => window.CLEFA.ValidationRegistry;
  test( 'passes within limit',    () => expect( R().check( 'max_length', 'abcde', '5' ) ).toBeNull() );
  test( 'fails when exceeds',     () => expect( R().check( 'max_length', 'abcdef', '5' ) ).toBeTruthy() );
} );

describe( 'ValidationRegistry — exact_length', () => {
  const R = () => window.CLEFA.ValidationRegistry;
  test( 'passes exact',         () => expect( R().check( 'exact_length', 'four', '4' ) ).toBeNull() );
  test( 'fails too short',      () => expect( R().check( 'exact_length', 'ab', '4' ) ).toBeTruthy() );
  test( 'fails too long',       () => expect( R().check( 'exact_length', 'toolong', '4' ) ).toBeTruthy() );
} );

describe( 'ValidationRegistry — regex', () => {
  const R = () => window.CLEFA.ValidationRegistry;
  test( 'matches passes',       () => expect( R().check( 'regex', 'abc123', '^[a-z0-9]+$' ) ).toBeNull() );
  test( 'no match fails',       () => expect( R().check( 'regex', 'ABC!',   '^[a-z0-9]+$' ) ).toBeTruthy() );
  test( 'bad pattern skipped',  () => expect( () => R().check( 'regex', 'x', '[invalid(' ) ).not.toThrow() );
} );

describe( 'ValidationRegistry — blocked_values', () => {
  const R = () => window.CLEFA.ValidationRegistry;
  test( 'blocked value fails',  () => expect( R().check( 'blocked_values', 'admin', 'spam,admin,test' ) ).toBeTruthy() );
  test( 'non-blocked passes',   () => expect( R().check( 'blocked_values', 'hello', 'spam,admin,test' ) ).toBeNull() );
} );

describe( 'ValidationRegistry — equals / not_equals', () => {
  const R = () => window.CLEFA.ValidationRegistry;
  test( 'equals pass',         () => expect( R().check( 'equals',     'exact', 'exact' ) ).toBeNull() );
  test( 'equals fail',         () => expect( R().check( 'equals',     'other', 'exact' ) ).toBeTruthy() );
  test( 'not_equals pass',     () => expect( R().check( 'not_equals', 'ok',    'banned' ) ).toBeNull() );
  test( 'not_equals fail',     () => expect( R().check( 'not_equals', 'banned', 'banned' ) ).toBeTruthy() );
} );

describe( 'ValidationRegistry — email', () => {
  const R = () => window.CLEFA.ValidationRegistry;
  test( 'valid email passes',   () => expect( R().check( 'email', 'user@example.com' ) ).toBeNull() );
  test( 'invalid email fails',  () => expect( R().check( 'email', 'notanemail' ) ).toBeTruthy() );
} );

describe( 'ValidationRegistry — url', () => {
  const R = () => window.CLEFA.ValidationRegistry;
  test( 'valid URL passes',  () => expect( R().check( 'url', 'https://example.com' ) ).toBeNull() );
  test( 'no scheme fails',   () => expect( R().check( 'url', 'example.com' ) ).toBeTruthy() );
} );

describe( 'ValidationRegistry — numeric / integer', () => {
  const R = () => window.CLEFA.ValidationRegistry;
  test( 'integer is numeric',      () => expect( R().check( 'numeric', '42' ) ).toBeNull() );
  test( 'decimal is numeric',      () => expect( R().check( 'numeric', '3.14' ) ).toBeNull() );
  test( 'letters fail numeric',    () => expect( R().check( 'numeric', 'abc' ) ).toBeTruthy() );
  test( 'integer passes integer',  () => expect( R().check( 'integer', '7' ) ).toBeNull() );
  test( 'decimal fails integer',   () => expect( R().check( 'integer', '3.14' ) ).toBeTruthy() );
} );

describe( 'ValidationRegistry — min_value / max_value', () => {
  const R = () => window.CLEFA.ValidationRegistry;
  test( 'at min passes',   () => expect( R().check( 'min_value', '5', '5' ) ).toBeNull() );
  test( 'below min fails', () => expect( R().check( 'min_value', '4', '5' ) ).toBeTruthy() );
  test( 'at max passes',   () => expect( R().check( 'max_value', '10', '10' ) ).toBeNull() );
  test( 'above max fails', () => expect( R().check( 'max_value', '11', '10' ) ).toBeTruthy() );
  test( 'empty param skipped', () => expect( R().check( 'min_value', '-999', '' ) ).toBeNull() );
} );

describe( 'ValidationRegistry — date_valid', () => {
  const R = () => window.CLEFA.ValidationRegistry;
  test( 'ISO date passes',   () => expect( R().check( 'date_valid', '2000-01-15' ) ).toBeNull() );
  test( 'garbage fails',     () => expect( R().check( 'date_valid', 'not-a-date' ) ).toBeTruthy() );
} );

describe( 'ValidationRegistry — date_after_today', () => {
  const R = () => window.CLEFA.ValidationRegistry;
  test( 'future date passes', () => {
    const future = new Date( Date.now() + 864e5 * 10 ).toISOString().slice( 0, 10 );
    expect( R().check( 'date_after_today', future ) ).toBeNull();
  } );
  test( 'past date fails', () => {
    expect( R().check( 'date_after_today', '2000-01-01' ) ).toBeTruthy();
  } );
} );

describe( 'ValidationRegistry — date_before_today', () => {
  const R = () => window.CLEFA.ValidationRegistry;
  test( 'past date passes',   () => expect( R().check( 'date_before_today', '2000-01-01' ) ).toBeNull() );
  test( 'future date fails',  () => {
    const future = new Date( Date.now() + 864e5 * 10 ).toISOString().slice( 0, 10 );
    expect( R().check( 'date_before_today', future ) ).toBeTruthy();
  } );
} );

describe( 'ValidationRegistry — date_after / date_before', () => {
  const R = () => window.CLEFA.ValidationRegistry;
  test( 'date_after pass',  () => expect( R().check( 'date_after', '2025-06-01', '2020-01-01' ) ).toBeNull() );
  test( 'date_after fail',  () => expect( R().check( 'date_after', '2019-12-31', '2020-01-01' ) ).toBeTruthy() );
  test( 'date_before pass', () => expect( R().check( 'date_before', '2025-01-01', '2030-12-31' ) ).toBeNull() );
  test( 'date_before fail', () => expect( R().check( 'date_before', '2031-01-01', '2030-12-31' ) ).toBeTruthy() );
} );

describe( 'ValidationRegistry — age_over / age_under', () => {
  const R = () => window.CLEFA.ValidationRegistry;
  function dob( yearsAgo ) {
    const d = new Date();
    d.setFullYear( d.getFullYear() - yearsAgo );
    return d.toISOString().slice( 0, 10 );
  }
  test( 'age_over adult passes',  () => expect( R().check( 'age_over',  dob( 25 ), '18' ) ).toBeNull() );
  test( 'age_over minor fails',   () => expect( R().check( 'age_over',  dob( 10 ), '18' ) ).toBeTruthy() );
  test( 'age_under young passes', () => expect( R().check( 'age_under', dob( 30 ), '65' ) ).toBeNull() );
  test( 'age_under old fails',    () => expect( R().check( 'age_under', dob( 70 ), '65' ) ).toBeTruthy() );
} );

describe( 'ValidationRegistry — time_since / time_passed', () => {
  const R = () => window.CLEFA.ValidationRegistry;
  function daysAgo( n ) {
    return new Date( Date.now() - n * 864e5 ).toISOString().slice( 0, 10 );
  }
  function hoursAgo( n ) {
    return new Date( Date.now() - n * 36e5 ).toISOString();
  }
  test( 'time_since old date passes',    () => expect( R().check( 'time_since', daysAgo( 60 ), '30' ) ).toBeNull() );
  test( 'time_since recent date fails',  () => expect( R().check( 'time_since', daysAgo( 5 ),  '30' ) ).toBeTruthy() );
  test( 'time_passed enough hours pass', () => expect( R().check( 'time_passed', hoursAgo( 48 ), '24' ) ).toBeNull() );
  test( 'time_passed too recent fails',  () => expect( R().check( 'time_passed', hoursAgo( 1 ),  '24' ) ).toBeTruthy() );
} );

describe( 'ValidationRegistry — require_uppercase / require_number_char / require_special', () => {
  const R = () => window.CLEFA.ValidationRegistry;
  test( 'uppercase present passes', () => expect( R().check( 'require_uppercase',   'Abcde' ) ).toBeNull() );
  test( 'no uppercase fails',       () => expect( R().check( 'require_uppercase',   'abcde' ) ).toBeTruthy() );
  test( 'number present passes',    () => expect( R().check( 'require_number_char', 'abc1'  ) ).toBeNull() );
  test( 'no number fails',          () => expect( R().check( 'require_number_char', 'Abcde' ) ).toBeTruthy() );
  test( 'special present passes',   () => expect( R().check( 'require_special',     'abc!'  ) ).toBeNull() );
  test( 'no special fails',         () => expect( R().check( 'require_special',     'Abc123') ).toBeTruthy() );
} );

describe( 'ValidationRegistry — password_strength', () => {
  const R = () => window.CLEFA.ValidationRegistry;
  test( 'weak 6 chars passes',       () => expect( R().check( 'password_strength', 'abcdef',     'weak'   ) ).toBeNull() );
  test( 'weak 3 chars fails',        () => expect( R().check( 'password_strength', 'abc',         'weak'   ) ).toBeTruthy() );
  test( 'medium valid passes',       () => expect( R().check( 'password_strength', 'Abcde1fg',    'medium' ) ).toBeNull() );
  test( 'medium no upper fails',     () => expect( R().check( 'password_strength', 'abcde1fg',    'medium' ) ).toBeTruthy() );
  test( 'strong valid passes',       () => expect( R().check( 'password_strength', 'Abcde1fg!X',  'strong' ) ).toBeNull() );
  test( 'strong no special fails',   () => expect( R().check( 'password_strength', 'Abcde1fgXY',  'strong' ) ).toBeTruthy() );
} );

describe( 'ValidationRegistry — checkbox_min / checkbox_max', () => {
  const R = () => window.CLEFA.ValidationRegistry;
  test( 'min met passes',     () => expect( R().check( 'checkbox_min', [ 'a', 'b' ], '2' ) ).toBeNull() );
  test( 'below min fails',    () => expect( R().check( 'checkbox_min', [ 'a' ],      '2' ) ).toBeTruthy() );
  test( 'max met passes',     () => expect( R().check( 'checkbox_max', [ 'a', 'b' ], '2' ) ).toBeNull() );
  test( 'above max fails',    () => expect( R().check( 'checkbox_max', [ 'a','b','c'], '2' ) ).toBeTruthy() );
} );

describe( 'ValidationRegistry — unknown rule returns null', () => {
  test( 'unknown key skipped', () => {
    expect( window.CLEFA.ValidationRegistry.check( 'nonexistent_xyz', 'value', null ) ).toBeNull();
  } );
} );

// ---------------------------------------------------------------------------
// ValidationEngine._isEmpty
// ---------------------------------------------------------------------------

describe( 'ValidationEngine._isEmpty', () => {
  test( 'empty string true',         () => { const { engine } = makeEngine( [] ); expect( engine._isEmpty( '' ) ).toBe( true ); } );
  test( 'whitespace true',           () => { const { engine } = makeEngine( [] ); expect( engine._isEmpty( '   ' ) ).toBe( true ); } );
  test( '"0" false',                 () => { const { engine } = makeEngine( [] ); expect( engine._isEmpty( '0' ) ).toBe( false ); } );
  test( 'numeric 0 false',           () => { const { engine } = makeEngine( [] ); expect( engine._isEmpty( 0 ) ).toBe( false ); } );
  test( 'empty array true',          () => { const { engine } = makeEngine( [] ); expect( engine._isEmpty( [] ) ).toBe( true ); } );
  test( 'array with falsy item true',() => { const { engine } = makeEngine( [] ); expect( engine._isEmpty( [''] ) ).toBe( true ); } );
  test( 'array with value false',    () => { const { engine } = makeEngine( [] ); expect( engine._isEmpty( ['a'] ) ).toBe( false ); } );
} );

// ---------------------------------------------------------------------------
// Required field validation through ValidationEngine
// ---------------------------------------------------------------------------

describe( 'Required field', () => {
  function req( value ) {
    const { engine, formEl } = makeEngine( [ vField( 'f', 'text', [], true ) ] );
    setFieldValue( formEl, 'f', value );
    return { error: engine._validateField( 'f', value ), formEl };
  }

  test( 'empty fails',      () => expect( req( '' ).error ).toBeTruthy() );
  test( 'whitespace fails', () => expect( req( '   ' ).error ).toBeTruthy() );
  test( 'non-empty passes', () => expect( req( 'hello' ).error ).toBeNull() );
  test( '"0" passes',       () => expect( req( '0' ).error ).toBeNull() );

  test( 'sets aria-invalid on error', () => {
    const { formEl } = req( '' );
    const inp = formEl.querySelector( '[data-clefa-field-id="f"]' );
    expect( inp.getAttribute( 'aria-invalid' ) ).toBe( 'true' );
  } );

  test( 'adds error class on error', () => {
    const { formEl } = req( '' );
    expect( hasErrorClass( formEl, 'f' ) ).toBe( true );
  } );

  test( 'optional empty field returns null', () => {
    const { engine } = makeEngine( [ vField( 'f', 'text' ) ] );
    expect( engine._validateField( 'f', '' ) ).toBeNull();
  } );

  test( 'custom message on required rule', () => {
    const { engine, formEl } = makeEngine( [
      { id: 'f', type: 'text', required: true, validation_rules: [ rule( 'required', null, 'Fill this in!' ) ] },
    ] );
    engine._validateField( 'f', '' );
    expect( getErrorText( formEl, 'f' ) ).toBe( 'Fill this in!' );
  } );
} );

// ---------------------------------------------------------------------------
// Auto base-type rules
// ---------------------------------------------------------------------------

describe( 'Auto base-type rules', () => {
  test( 'email field auto-validates as email', () => {
    const { engine } = makeEngine( [ vField( 'e', 'email' ) ] );
    expect( engine._validateField( 'e', 'bad' ) ).toBeTruthy();
    expect( engine._validateField( 'e', 'ok@example.com' ) ).toBeNull();
  } );

  test( 'url field auto-validates as url', () => {
    const { engine } = makeEngine( [ vField( 'u', 'url' ) ] );
    expect( engine._validateField( 'u', 'not-a-url' ) ).toBeTruthy();
    expect( engine._validateField( 'u', 'https://example.com' ) ).toBeNull();
  } );

  test( 'number field auto-validates as numeric', () => {
    const { engine } = makeEngine( [ vField( 'n', 'number' ) ] );
    expect( engine._validateField( 'n', 'abc' ) ).toBeTruthy();
    expect( engine._validateField( 'n', '42' ) ).toBeNull();
  } );

  test( 'date field auto-validates as date_valid', () => {
    const { engine } = makeEngine( [ vField( 'd', 'date' ) ] );
    expect( engine._validateField( 'd', 'not-a-date' ) ).toBeTruthy();
    expect( engine._validateField( 'd', '2000-01-15' ) ).toBeNull();
  } );
} );

// ---------------------------------------------------------------------------
// validation_rules through ValidationEngine._validateField
// ---------------------------------------------------------------------------

describe( 'validation_rules array', () => {
  test( 'min_length rule through engine', () => {
    const { engine } = makeEngine( [ vField( 'f', 'text', [ rule( 'min_length', '5' ) ] ) ] );
    expect( engine._validateField( 'f', 'abc' ) ).toBeTruthy();
    expect( engine._validateField( 'f', 'abcde' ) ).toBeNull();
  } );

  test( 'max_length rule through engine', () => {
    const { engine } = makeEngine( [ vField( 'f', 'text', [ rule( 'max_length', '5' ) ] ) ] );
    expect( engine._validateField( 'f', 'abcdef' ) ).toBeTruthy();
    expect( engine._validateField( 'f', 'abcde' ) ).toBeNull();
  } );

  test( 'custom message overrides default', () => {
    const { engine, formEl } = makeEngine( [ vField( 'f', 'text', [ rule( 'min_length', '5', 'Too short!' ) ] ) ] );
    engine._validateField( 'f', 'abc' );
    expect( getErrorText( formEl, 'f' ) ).toBe( 'Too short!' );
  } );

  test( 'stops at first failing rule', () => {
    const { engine } = makeEngine( [ vField( 'f', 'text', [
      rule( 'min_length', '10' ),
      rule( 'regex', '^[0-9]+$' ),
    ] ) ] );
    const err = engine._validateField( 'f', 'abc' );
    expect( err ).toContain( '10' ); // min_length fires first
  } );

  test( 'required rule in array uses custom message', () => {
    const { engine, formEl } = makeEngine( [
      { id: 'f', type: 'text', required: true, validation_rules: [ rule( 'required', null, 'Please fill in this field.' ) ] },
    ] );
    engine._validateField( 'f', '' );
    expect( getErrorText( formEl, 'f' ) ).toBe( 'Please fill in this field.' );
  } );

  test( 'optional empty field skips rules', () => {
    const { engine } = makeEngine( [ vField( 'f', 'text', [ rule( 'min_length', '5' ) ] ) ] );
    expect( engine._validateField( 'f', '' ) ).toBeNull();
  } );

  test( 'unknown rule key is silently skipped', () => {
    const { engine } = makeEngine( [ vField( 'f', 'text', [ rule( 'nonexistent_rule_xyz' ) ] ) ] );
    expect( engine._validateField( 'f', 'anything' ) ).toBeNull();
  } );
} );

// ---------------------------------------------------------------------------
// confirm_password cross-field
// ---------------------------------------------------------------------------

describe( 'confirm_password rule', () => {
  test( 'matching values pass', () => {
    const { engine, formEl } = makeEngine( [
      vField( 'pw', 'password' ),
      vField( 'pw2', 'text', [ rule( 'confirm_password', 'pw' ) ] ),
    ] );
    setFieldValue( formEl, 'pw',  'Secret1!' );
    setFieldValue( formEl, 'pw2', 'Secret1!' );
    expect( engine._validateField( 'pw2', 'Secret1!' ) ).toBeNull();
  } );

  test( 'mismatched values fail', () => {
    const { engine, formEl } = makeEngine( [
      vField( 'pw', 'password' ),
      vField( 'pw2', 'text', [ rule( 'confirm_password', 'pw' ) ] ),
    ] );
    setFieldValue( formEl, 'pw',  'Secret1!' );
    setFieldValue( formEl, 'pw2', 'Different!' );
    expect( engine._validateField( 'pw2', 'Different!' ) ).toBeTruthy();
  } );

  test( 'no source field set returns null', () => {
    const { engine } = makeEngine( [ vField( 'pw2', 'text', [ rule( 'confirm_password', null ) ] ) ] );
    expect( engine._validateField( 'pw2', 'anything' ) ).toBeNull();
  } );
} );

// ---------------------------------------------------------------------------
// Display-only fields
// ---------------------------------------------------------------------------

describe( 'Display-only fields skip validation', () => {
  [ 'html', 'notice', 'grid_break', 'heading' ].forEach( ( ftype ) => {
    test( `${ftype} with required returns null`, () => {
      const { engine } = makeEngine( [ { id: 'x', type: ftype, required: true, validation_rules: [] } ] );
      expect( engine._validateField( 'x', '' ) ).toBeNull();
    } );
  } );
} );

// ---------------------------------------------------------------------------
// validateStep / validateAll
// ---------------------------------------------------------------------------

describe( 'validateStep', () => {
  test( 'returns error for visible required field', () => {
    const { engine, formEl } = makeEngine( [
      vField( 'a', 'text', [], true ),
      vField( 'b', 'text', [], true ),
    ] );
    formEl.querySelector( '[data-clefa-field="b"]' ).setAttribute( 'data-clefa-visible', '0' );
    const stepEl = formEl.querySelector( '[data-clefa-step]' );
    const errors = engine.validateStep( stepEl );
    expect( errors ).toHaveProperty( 'a' );
    expect( errors ).not.toHaveProperty( 'b' );
  } );

  test( 'returns empty when all pass', () => {
    const { engine, formEl } = makeEngine( [ vField( 'a', 'text', [], true ) ] );
    setFieldValue( formEl, 'a', 'hello' );
    const stepEl = formEl.querySelector( '[data-clefa-step]' );
    expect( engine.validateStep( stepEl ) ).toEqual( {} );
  } );
} );

describe( 'validateAll', () => {
  test( 'aggregates errors from all fields', () => {
    const { engine } = makeEngine( [
      vField( 'name',  'text',  [], true ),
      vField( 'email', 'email', [], true ),
    ] );
    expect( Object.keys( engine.validateAll() ).length ).toBe( 2 );
  } );

  test( 'returns empty when all valid', () => {
    const { engine, formEl } = makeEngine( [
      vField( 'name',  'text',  [], true ),
      vField( 'email', 'email', [], true ),
    ] );
    setFieldValue( formEl, 'name',  'Linden' );
    setFieldValue( formEl, 'email', 'linden@example.com' );
    expect( engine.validateAll() ).toEqual( {} );
  } );
} );

// ---------------------------------------------------------------------------
// showServerErrors
// ---------------------------------------------------------------------------

describe( 'showServerErrors', () => {
  test( 'renders server errors into DOM', () => {
    const { engine, formEl } = makeEngine( [ vField( 'username', 'text' ) ] );
    engine.showServerErrors( { username: 'Username taken.' } );
    expect( getErrorText( formEl, 'username' ) ).toBe( 'Username taken.' );
  } );
} );

// ---------------------------------------------------------------------------
// Validation events
// ---------------------------------------------------------------------------

describe( 'Validation events', () => {
  test( 'clefa:validation:failed fires on error', () => {
    const { engine, formEl } = makeEngine( [ vField( 'f', 'text', [], true ) ] );
    const spy = jest.fn();
    formEl.addEventListener( 'clefa:validation:failed', spy );
    engine._validateField( 'f', '' );
    expect( spy ).toHaveBeenCalledTimes( 1 );
    expect( spy.mock.calls[ 0 ][ 0 ].detail.fieldId ).toBe( 'f' );
  } );

  test( 'clefa:validation:passed fires after error cleared', () => {
    const { engine, formEl } = makeEngine( [ vField( 'f', 'text', [], true ) ] );
    engine._validateField( 'f', '' ); // Trigger error
    const spy = jest.fn();
    formEl.addEventListener( 'clefa:validation:passed', spy );
    engine._validateField( 'f', 'valid' );
    expect( spy ).toHaveBeenCalledTimes( 1 );
  } );

  test( 'clefa:validation:passed does not fire if no prior error', () => {
    const { engine, formEl } = makeEngine( [ vField( 'f', 'text' ) ] );
    const spy = jest.fn();
    formEl.addEventListener( 'clefa:validation:passed', spy );
    engine._validateField( 'f', 'value' );
    expect( spy ).not.toHaveBeenCalled();
  } );
} );

// ---------------------------------------------------------------------------
// Registry extensibility — register a custom rule
// ---------------------------------------------------------------------------

describe( 'Custom rule registration', () => {
  test( 'custom rule is invoked and returns error', () => {
    window.CLEFA.ValidationRegistry.register( 'test_always_fail', () => 'always fails' );
    const { engine } = makeEngine( [ vField( 'f', 'text', [ rule( 'test_always_fail' ) ] ) ] );
    expect( engine._validateField( 'f', 'anything' ) ).toBe( 'always fails' );
  } );

  test( 'custom rule returning null passes', () => {
    window.CLEFA.ValidationRegistry.register( 'test_always_pass', () => null );
    const { engine } = makeEngine( [ vField( 'f', 'text', [ rule( 'test_always_pass' ) ] ) ] );
    expect( engine._validateField( 'f', 'anything' ) ).toBeNull();
  } );
} );
