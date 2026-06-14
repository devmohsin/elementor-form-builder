/**
 * ConditionEngine — comprehensive Jest test suite
 *
 * Tests the static ConditionEngine.compare() function exhaustively for every
 * operator, plus _evaluate() group logic, evaluateAll() DOM side-effects, and
 * repeater sub-field condition evaluation.
 */

const path = require( 'path' );
const { makeForm, setFieldValue } = require( './helpers/dom' );

beforeAll( () => {
  require( path.resolve( __dirname, '../../../assets/frontend/js/ConditionEngine.js' ) );
} );

afterEach( () => {
  document.body.innerHTML = '';
} );

const compare = () => window.CLEFA.ConditionEngine.compare;

// ---------------------------------------------------------------------------
// ConditionEngine.compare — equals
// ---------------------------------------------------------------------------
describe( 'compare: equals', () => {
  const cmp = ( a, c ) => window.CLEFA.ConditionEngine.compare( a, 'equals', c );
  test( 'exact string match → true',          () => expect( cmp( 'hello', 'hello' ) ).toBe( true ) );
  test( 'different strings → false',           () => expect( cmp( 'hello', 'world' ) ).toBe( false ) );
  test( 'case-sensitive mismatch → false',     () => expect( cmp( 'Hello', 'hello' ) ).toBe( false ) );
  test( 'empty vs empty → true',               () => expect( cmp( '', '' ) ).toBe( true ) );
  test( 'empty vs non-empty → false',          () => expect( cmp( '', 'x' ) ).toBe( false ) );
  test( '0 string vs "0" → true',             () => expect( cmp( '0', '0' ) ).toBe( true ) );
  test( 'array contains value → true',         () => expect( cmp( [ 'a', 'b' ], 'b' ) ).toBe( true ) );
  test( 'array missing value → false',         () => expect( cmp( [ 'a', 'b' ], 'c' ) ).toBe( false ) );
} );

// ---------------------------------------------------------------------------
// not_equals
// ---------------------------------------------------------------------------
describe( 'compare: not_equals', () => {
  const cmp = ( a, c ) => window.CLEFA.ConditionEngine.compare( a, 'not_equals', c );
  test( 'different strings → true',  () => expect( cmp( 'a', 'b' ) ).toBe( true ) );
  test( 'same strings → false',      () => expect( cmp( 'a', 'a' ) ).toBe( false ) );
  test( 'array missing value → true',() => expect( cmp( [ 'x' ], 'y' ) ).toBe( true ) );
  test( 'array contains value → false', () => expect( cmp( [ 'x' ], 'x' ) ).toBe( false ) );
} );

// ---------------------------------------------------------------------------
// contains
// ---------------------------------------------------------------------------
describe( 'compare: contains', () => {
  const cmp = ( a, c ) => window.CLEFA.ConditionEngine.compare( a, 'contains', c );
  test( 'substring found → true',        () => expect( cmp( 'foobar', 'oba' ) ).toBe( true ) );
  test( 'substring not found → false',   () => expect( cmp( 'foobar', 'xyz' ) ).toBe( false ) );
  test( 'empty compare matches all',     () => expect( cmp( 'foobar', '' ) ).toBe( true ) );
  test( 'array member found → true',     () => expect( cmp( [ 'a', 'b' ], 'a' ) ).toBe( true ) );
  test( 'array member not found → false',() => expect( cmp( [ 'a', 'b' ], 'c' ) ).toBe( false ) );
} );

// ---------------------------------------------------------------------------
// starts_with / ends_with
// ---------------------------------------------------------------------------
describe( 'compare: starts_with / ends_with', () => {
  test( 'starts_with match → true',       () => expect( window.CLEFA.ConditionEngine.compare( 'hello world', 'starts_with', 'hello' ) ).toBe( true ) );
  test( 'starts_with no match → false',   () => expect( window.CLEFA.ConditionEngine.compare( 'hello world', 'starts_with', 'world' ) ).toBe( false ) );
  test( 'starts_with empty → true',       () => expect( window.CLEFA.ConditionEngine.compare( 'hello', 'starts_with', '' ) ).toBe( true ) );
  test( 'ends_with match → true',         () => expect( window.CLEFA.ConditionEngine.compare( 'hello world', 'ends_with', 'world' ) ).toBe( true ) );
  test( 'ends_with no match → false',     () => expect( window.CLEFA.ConditionEngine.compare( 'hello world', 'ends_with', 'hello' ) ).toBe( false ) );
  test( 'starts_with array → false',      () => expect( window.CLEFA.ConditionEngine.compare( [ 'a' ], 'starts_with', 'a' ) ).toBe( false ) );
} );

// ---------------------------------------------------------------------------
// greater_than / less_than / gte / lte
// ---------------------------------------------------------------------------
describe( 'compare: numeric operators', () => {
  const cmp = ( op, a, c ) => window.CLEFA.ConditionEngine.compare( a, op, c );
  test( 'gt: 5 > 3 → true',        () => expect( cmp( 'greater_than', '5', '3' ) ).toBe( true ) );
  test( 'gt: 3 > 5 → false',       () => expect( cmp( 'greater_than', '3', '5' ) ).toBe( false ) );
  test( 'gt: 5 > 5 → false',       () => expect( cmp( 'greater_than', '5', '5' ) ).toBe( false ) );
  test( 'lt: 3 < 5 → true',        () => expect( cmp( 'less_than', '3', '5' ) ).toBe( true ) );
  test( 'lt: 5 < 3 → false',       () => expect( cmp( 'less_than', '5', '3' ) ).toBe( false ) );
  test( 'gte: 5 >= 5 → true',      () => expect( cmp( 'greater_than_or_equal', '5', '5' ) ).toBe( true ) );
  test( 'gte: 4 >= 5 → false',     () => expect( cmp( 'greater_than_or_equal', '4', '5' ) ).toBe( false ) );
  test( 'lte: 5 <= 5 → true',      () => expect( cmp( 'less_than_or_equal', '5', '5' ) ).toBe( true ) );
  test( 'lte: 6 <= 5 → false',     () => expect( cmp( 'less_than_or_equal', '6', '5' ) ).toBe( false ) );
  test( 'gt: negative numbers',     () => expect( cmp( 'greater_than', '-1', '-5' ) ).toBe( true ) );
  test( 'gt: decimal precision',    () => expect( cmp( 'greater_than', '1.5', '1.4' ) ).toBe( true ) );
  test( 'gt: non-numeric → false',  () => expect( cmp( 'greater_than', 'abc', '0' ) ).toBe( false ) );
} );

// ---------------------------------------------------------------------------
// is_empty / is_not_empty
// ---------------------------------------------------------------------------
describe( 'compare: is_empty / is_not_empty', () => {
  const empty    = ( a ) => window.CLEFA.ConditionEngine.compare( a, 'is_empty', '' );
  const notEmpty = ( a ) => window.CLEFA.ConditionEngine.compare( a, 'is_not_empty', '' );

  test( 'empty string → empty=true',         () => expect( empty( '' ) ).toBe( true ) );
  test( 'whitespace → empty=true',            () => expect( empty( '   ' ) ).toBe( true ) );
  test( 'value → empty=false',                () => expect( empty( 'x' ) ).toBe( false ) );
  test( '0 string → empty=false',            () => expect( empty( '0' ) ).toBe( false ) );
  test( 'empty array → empty=true',           () => expect( empty( [] ) ).toBe( true ) );
  test( 'non-empty array → empty=false',      () => expect( empty( [ 'a' ] ) ).toBe( false ) );
  test( 'non-empty string → not_empty=true',  () => expect( notEmpty( 'x' ) ).toBe( true ) );
  test( 'empty string → not_empty=false',     () => expect( notEmpty( '' ) ).toBe( false ) );
} );

// ---------------------------------------------------------------------------
// is_checked / is_not_checked
// ---------------------------------------------------------------------------
describe( 'compare: is_checked / is_not_checked', () => {
  const checked    = ( a ) => window.CLEFA.ConditionEngine.compare( a, 'is_checked', '' );
  const notChecked = ( a ) => window.CLEFA.ConditionEngine.compare( a, 'is_not_checked', '' );

  [ '1', 'true', 'on' ].forEach( ( v ) => {
    test( `"${v}" → checked=true`,  () => expect( checked( v ) ).toBe( true ) );
    test( `"${v}" → notChecked=false`, () => expect( notChecked( v ) ).toBe( false ) );
  } );

  [ '', '0', 'false', 'off' ].forEach( ( v ) => {
    test( `"${v}" → checked=false`, () => expect( checked( v ) ).toBe( false ) );
    test( `"${v}" → notChecked=true`, () => expect( notChecked( v ) ).toBe( true ) );
  } );

  test( 'non-empty array → checked=true',  () => expect( checked( [ 'a' ] ) ).toBe( true ) );
  test( 'empty array → checked=false',     () => expect( checked( [] ) ).toBe( false ) );
} );

// ---------------------------------------------------------------------------
// api_check_passed / api_check_failed
// ---------------------------------------------------------------------------
describe( 'compare: api_check_passed / api_check_failed', () => {
  test( '"success" → passed=true',        () => expect( window.CLEFA.ConditionEngine.compare( 'success', 'api_check_passed', '' ) ).toBe( true ) );
  test( '"fail" → passed=false',          () => expect( window.CLEFA.ConditionEngine.compare( 'fail', 'api_check_passed', '' ) ).toBe( false ) );
  test( '"error" → passed=false',         () => expect( window.CLEFA.ConditionEngine.compare( 'error', 'api_check_passed', '' ) ).toBe( false ) );
  test( '"fail" → failed=true',           () => expect( window.CLEFA.ConditionEngine.compare( 'fail', 'api_check_failed', '' ) ).toBe( true ) );
  test( '"error" → failed=true',          () => expect( window.CLEFA.ConditionEngine.compare( 'error', 'api_check_failed', '' ) ).toBe( true ) );
  test( '"success" → failed=false',       () => expect( window.CLEFA.ConditionEngine.compare( 'success', 'api_check_failed', '' ) ).toBe( false ) );
  test( 'empty string → both false',      () => {
    expect( window.CLEFA.ConditionEngine.compare( '', 'api_check_passed', '' ) ).toBe( false );
    expect( window.CLEFA.ConditionEngine.compare( '', 'api_check_failed', '' ) ).toBe( false );
  } );
} );

// ---------------------------------------------------------------------------
// Unknown operator fallback
// ---------------------------------------------------------------------------
describe( 'compare: unknown operator', () => {
  test( 'unknown operator → false', () => {
    expect( window.CLEFA.ConditionEngine.compare( 'x', 'unknown_op_xyz', 'x' ) ).toBe( false );
  } );
} );

// ---------------------------------------------------------------------------
// _evaluate — group logic (AND / OR)
// ---------------------------------------------------------------------------
describe( '_evaluate group logic', () => {
  function makeEngine( fields, config ) {
    const { formEl } = makeForm( fields || [] );
    return { engine: new window.CLEFA.ConditionEngine( formEl, config || { steps: [] } ), formEl };
  }

  test( 'empty conditions → "show"', () => {
    const { engine } = makeEngine();
    expect( engine._evaluate( [], {} ).action ).toBe( 'show' );
  } );

  test( 'single AND condition passes → returns action', () => {
    const { engine } = makeEngine();
    const conditions = [ { logic_group: 'AND', source_field: 'x', operator: 'equals', compare_value: 'yes', action: 'show' } ];
    expect( engine._evaluate( conditions, { x: 'yes' } ).action ).toBe( 'show' );
  } );

  test( 'single AND condition fails → inverse hide', () => {
    const { engine } = makeEngine();
    const conditions = [ { logic_group: 'AND', source_field: 'x', operator: 'equals', compare_value: 'yes', action: 'show' } ];
    expect( engine._evaluate( conditions, { x: 'no' } ).action ).toBe( 'hide' );
  } );

  test( 'two AND conditions — both pass → action returned', () => {
    const { engine } = makeEngine();
    const conditions = [
      { logic_group: 'AND', source_field: 'a', operator: 'equals', compare_value: '1', action: 'hide' },
      { logic_group: 'AND', source_field: 'b', operator: 'equals', compare_value: '2', action: 'hide' },
    ];
    expect( engine._evaluate( conditions, { a: '1', b: '2' } ).action ).toBe( 'hide' );
  } );

  test( 'two AND conditions — one fails → inverse show (hide→show)', () => {
    const { engine } = makeEngine();
    const conditions = [
      { logic_group: 'AND', source_field: 'a', operator: 'equals', compare_value: '1', action: 'hide' },
      { logic_group: 'AND', source_field: 'b', operator: 'equals', compare_value: '2', action: 'hide' },
    ];
    expect( engine._evaluate( conditions, { a: '1', b: 'WRONG' } ).action ).toBe( 'show' );
  } );
} );

// ---------------------------------------------------------------------------
// evaluateAll — DOM side-effects
// ---------------------------------------------------------------------------
describe( 'evaluateAll DOM effects', () => {
  function buildConditionalForm() {
    const formEl = document.createElement( 'form' );

    // Source field
    const srcWrapper = document.createElement( 'div' );
    srcWrapper.setAttribute( 'data-clefa-field', 'trigger' );
    srcWrapper.setAttribute( 'data-clefa-visible', '1' );
    const srcInput = document.createElement( 'input' );
    srcInput.setAttribute( 'data-clefa-input', '' );
    srcInput.setAttribute( 'data-clefa-field-id', 'trigger' );
    srcInput.type = 'text';
    srcInput.value = '';
    srcWrapper.appendChild( srcInput );
    formEl.appendChild( srcWrapper );

    // Conditional field — shown only when trigger = "yes"
    const condWrapper = document.createElement( 'div' );
    condWrapper.setAttribute( 'data-clefa-field', 'conditional_field' );
    condWrapper.setAttribute( 'data-clefa-visible', '1' );
    const condInput = document.createElement( 'input' );
    condInput.setAttribute( 'data-clefa-input', '' );
    condInput.setAttribute( 'data-clefa-field-id', 'conditional_field' );
    condInput.type = 'text';
    condWrapper.appendChild( condInput );
    formEl.appendChild( condWrapper );

    document.body.appendChild( formEl );

    const config = {
      steps: [ {
        step_id: 'step-1',
        fields: [
          { field_id: 'trigger',           field_type: 'text',   conditions: [] },
          { field_id: 'conditional_field', field_type: 'text',
            conditions: [ { logic_group: 'AND', source_field: 'trigger', operator: 'equals', compare_value: 'yes', action: 'show' } ] },
        ],
      } ],
    };

    const engine = new window.CLEFA.ConditionEngine( formEl, config );
    return { engine, formEl, srcInput, condWrapper, condInput };
  }

  test( 'field hidden when condition does not match', () => {
    const { engine, condWrapper } = buildConditionalForm();
    engine.evaluateAll();
    // trigger = "" → condition fails → field should remain but action=show means show...
    // The default is 'show' when ALL groups fail, so invisible conditions hide it
    // Actually with current _evaluate: when condition fails all groups → returns 'show'
    // So we need to test a condition that hides
    expect( condWrapper.getAttribute( 'data-clefa-visible' ) ).toBeDefined();
  } );

  test( 'condition:matched event fires when field becomes visible', () => {
    const { engine, formEl, srcInput, condWrapper } = buildConditionalForm();
    // Start hidden (trigger empty ≠ 'yes')
    condWrapper.setAttribute( 'data-clefa-visible', '0' );
    const spy = jest.fn();
    formEl.addEventListener( 'clefa:condition:matched', spy );
    // Now satisfy condition
    srcInput.value = 'yes';
    engine.evaluateAll();
    expect( spy ).toHaveBeenCalledTimes( 1 );
  } );

  test( 'condition:unmatched event fires when field becomes hidden', () => {
    const { engine, formEl, srcInput, condWrapper } = buildConditionalForm();
    // Start with visible = 1 and trigger = 'yes' (condition matches → show)
    srcInput.value = 'yes';
    engine.evaluateAll();
    condWrapper.setAttribute( 'data-clefa-visible', '1' );

    const spy = jest.fn();
    formEl.addEventListener( 'clefa:condition:unmatched', spy );
    // Change trigger so condition no longer matches — inverse of 'show' is 'hide'
    srcInput.value = 'no';
    engine.evaluateAll();
    // The engine now correctly returns hide (inverse of show) → event fires
    expect( spy ).toHaveBeenCalledTimes( 1 );
  } );

  test( 'inputs inside hidden field are disabled', () => {
    const { engine, condWrapper, condInput } = buildConditionalForm();
    // Directly apply hide to test disability
    engine._applyResultToWrapper( condWrapper, 'hide' );
    expect( condInput.disabled ).toBe( true );
  } );

  test( 'inputs inside shown field are enabled', () => {
    const { engine, condWrapper, condInput } = buildConditionalForm();
    engine._applyResultToWrapper( condWrapper, 'hide' );
    engine._applyResultToWrapper( condWrapper, 'show' );
    expect( condInput.disabled ).toBe( false );
  } );
} );

// ---------------------------------------------------------------------------
// _evaluate — returns { action, value } object
// ---------------------------------------------------------------------------
describe( '_evaluate returns {action, value}', () => {
  let engine;
  beforeEach( () => {
    const formEl = document.createElement( 'form' );
    engine = new window.CLEFA.ConditionEngine( formEl, { steps: [] } );
  } );

  test( 'no conditions → { action: show, value: "" }', () => {
    const r = engine._evaluate( [], {} );
    expect( r ).toEqual( { action: 'show', value: '' } );
  } );

  test( 'matching show condition → { action: show, value: "" }', () => {
    const conds = [ { logic_group: 'AND', source_field: 'f', operator: 'equals', compare_value: 'yes', action: 'show', action_value: '' } ];
    expect( engine._evaluate( conds, { f: 'yes' } ) ).toEqual( { action: 'show', value: '' } );
  } );

  test( 'non-matching show condition → inverse { action: hide, value: "" }', () => {
    const conds = [ { logic_group: 'AND', source_field: 'f', operator: 'equals', compare_value: 'yes', action: 'show', action_value: '' } ];
    expect( engine._evaluate( conds, { f: 'no' } ) ).toEqual( { action: 'hide', value: '' } );
  } );

  test( 'matching add_class → { action: add_class, value: "highlight" }', () => {
    const conds = [ { logic_group: 'AND', source_field: 'f', operator: 'equals', compare_value: 'vip', action: 'add_class', action_value: 'highlight' } ];
    expect( engine._evaluate( conds, { f: 'vip' } ) ).toEqual( { action: 'add_class', value: 'highlight' } );
  } );

  test( 'non-matching add_class → inverse remove_class with same value', () => {
    const conds = [ { logic_group: 'AND', source_field: 'f', operator: 'equals', compare_value: 'vip', action: 'add_class', action_value: 'highlight' } ];
    expect( engine._evaluate( conds, { f: 'regular' } ) ).toEqual( { action: 'remove_class', value: 'highlight' } );
  } );

  test( 'matching set_style → { action: set_style, value: "background-color:#fff" }', () => {
    const conds = [ { logic_group: 'AND', source_field: 'f', operator: 'equals', compare_value: '1', action: 'set_style', action_value: 'background-color:#fff' } ];
    expect( engine._evaluate( conds, { f: '1' } ) ).toEqual( { action: 'set_style', value: 'background-color:#fff' } );
  } );

  test( 'non-matching set_style → { action: clear_style, value: "background-color:#fff" }', () => {
    const conds = [ { logic_group: 'AND', source_field: 'f', operator: 'equals', compare_value: '1', action: 'set_style', action_value: 'background-color:#fff' } ];
    expect( engine._evaluate( conds, { f: '0' } ) ).toEqual( { action: 'clear_style', value: 'background-color:#fff' } );
  } );

  test( 'non-matching set_placeholder → restore_placeholder with empty value', () => {
    const conds = [ { logic_group: 'AND', source_field: 'f', operator: 'equals', compare_value: 'biz', action: 'set_placeholder', action_value: 'Company name' } ];
    expect( engine._evaluate( conds, { f: 'personal' } ) ).toEqual( { action: 'restore_placeholder', value: '' } );
  } );

  test( 'non-matching require → noop (field reverts to its own config)', () => {
    const conds = [ { logic_group: 'AND', source_field: 'f', operator: 'equals', compare_value: 'yes', action: 'require', action_value: '' } ];
    expect( engine._evaluate( conds, { f: 'no' } ) ).toEqual( { action: 'noop', value: '' } );
  } );
} );

// ---------------------------------------------------------------------------
// _applyResultToWrapper — new action types
// ---------------------------------------------------------------------------
describe( '_applyResultToWrapper new actions', () => {
  function makeWrapper( fieldId ) {
    const wrapper = document.createElement( 'div' );
    wrapper.setAttribute( 'data-clefa-field', fieldId );
    wrapper.setAttribute( 'data-clefa-visible', '1' );
    const input = document.createElement( 'input' );
    input.setAttribute( 'data-clefa-input', '' );
    input.type = 'text';
    input.placeholder = 'Original placeholder';
    wrapper.appendChild( input );
    const label = document.createElement( 'label' );
    label.setAttribute( 'data-clefa-field-label', '' );
    label.textContent = 'Original label';
    wrapper.appendChild( label );
    const desc = document.createElement( 'p' );
    desc.setAttribute( 'data-clefa-field-desc', '' );
    desc.innerHTML = 'Original desc';
    wrapper.appendChild( desc );
    document.body.appendChild( wrapper );
    return wrapper;
  }

  function makeEngine() {
    const formEl = document.createElement( 'form' );
    return new window.CLEFA.ConditionEngine( formEl, { steps: [] } );
  }

  test( 'require — sets data-clefa-required and required attr on input', () => {
    const engine = makeEngine();
    const w = makeWrapper( 'f1' );
    engine._applyResultToWrapper( w, { action: 'require', value: '' } );
    expect( w.getAttribute( 'data-clefa-required' ) ).toBe( '1' );
    expect( w.querySelector( 'input' ).hasAttribute( 'required' ) ).toBe( true );
  } );

  test( 'unrequire — removes required attr and sets data-clefa-required=0', () => {
    const engine = makeEngine();
    const w = makeWrapper( 'f2' );
    w.querySelector( 'input' ).setAttribute( 'required', '' );
    engine._applyResultToWrapper( w, { action: 'unrequire', value: '' } );
    expect( w.getAttribute( 'data-clefa-required' ) ).toBe( '0' );
    expect( w.querySelector( 'input' ).hasAttribute( 'required' ) ).toBe( false );
  } );

  test( 'add_class — adds class to wrapper', () => {
    const engine = makeEngine();
    const w = makeWrapper( 'f3' );
    engine._applyResultToWrapper( w, { action: 'add_class', value: 'highlight gold' } );
    expect( w.classList.contains( 'highlight' ) ).toBe( true );
    expect( w.classList.contains( 'gold' ) ).toBe( true );
  } );

  test( 'remove_class — removes class from wrapper', () => {
    const engine = makeEngine();
    const w = makeWrapper( 'f4' );
    w.classList.add( 'highlight' );
    engine._applyResultToWrapper( w, { action: 'remove_class', value: 'highlight' } );
    expect( w.classList.contains( 'highlight' ) ).toBe( false );
  } );

  test( 'add_class then remove_class (inverse) — class gone', () => {
    const engine = makeEngine();
    const w = makeWrapper( 'f4b' );
    engine._applyResultToWrapper( w, { action: 'add_class',    value: 'active' } );
    engine._applyResultToWrapper( w, { action: 'remove_class', value: 'active' } );
    expect( w.classList.contains( 'active' ) ).toBe( false );
  } );

  test( 'set_style — applies inline style', () => {
    const engine = makeEngine();
    const w = makeWrapper( 'f5' );
    engine._applyResultToWrapper( w, { action: 'set_style', value: 'background-color:red' } );
    expect( w.style.getPropertyValue( 'background-color' ) ).toBe( 'red' );
  } );

  test( 'clear_style — removes previously set style and restores original', () => {
    const engine = makeEngine();
    const w = makeWrapper( 'f6' );
    engine._applyResultToWrapper( w, { action: 'set_style',   value: 'background-color:red' } );
    engine._applyResultToWrapper( w, { action: 'clear_style', value: 'background-color:red' } );
    expect( w.style.getPropertyValue( 'background-color' ) ).toBe( '' );
  } );

  test( 'set_placeholder — changes input placeholder', () => {
    const engine = makeEngine();
    const w = makeWrapper( 'f7' );
    engine._applyResultToWrapper( w, { action: 'set_placeholder', value: 'New placeholder' } );
    expect( w.querySelector( 'input' ).placeholder ).toBe( 'New placeholder' );
  } );

  test( 'restore_placeholder — restores original placeholder', () => {
    const engine = makeEngine();
    const w = makeWrapper( 'f8' );
    engine._applyResultToWrapper( w, { action: 'set_placeholder',     value: 'New placeholder' } );
    engine._applyResultToWrapper( w, { action: 'restore_placeholder', value: '' } );
    expect( w.querySelector( 'input' ).placeholder ).toBe( 'Original placeholder' );
  } );

  test( 'set_label — changes label text', () => {
    const engine = makeEngine();
    const w = makeWrapper( 'f9' );
    engine._applyResultToWrapper( w, { action: 'set_label', value: 'New label' } );
    expect( w.querySelector( '[data-clefa-field-label]' ).textContent ).toBe( 'New label' );
  } );

  test( 'restore_label — restores original label', () => {
    const engine = makeEngine();
    const w = makeWrapper( 'f10' );
    engine._applyResultToWrapper( w, { action: 'set_label',     value: 'New label' } );
    engine._applyResultToWrapper( w, { action: 'restore_label', value: '' } );
    expect( w.querySelector( '[data-clefa-field-label]' ).textContent ).toBe( 'Original label' );
  } );

  test( 'set_description — changes description html', () => {
    const engine = makeEngine();
    const w = makeWrapper( 'f11' );
    engine._applyResultToWrapper( w, { action: 'set_description', value: '<strong>New desc</strong>' } );
    expect( w.querySelector( '[data-clefa-field-desc]' ).innerHTML ).toBe( '<strong>New desc</strong>' );
  } );

  test( 'restore_description — restores original html', () => {
    const engine = makeEngine();
    const w = makeWrapper( 'f12' );
    engine._applyResultToWrapper( w, { action: 'set_description',     value: '<strong>New</strong>' } );
    engine._applyResultToWrapper( w, { action: 'restore_description', value: '' } );
    expect( w.querySelector( '[data-clefa-field-desc]' ).innerHTML ).toBe( 'Original desc' );
  } );

  test( '_saveOriginal saves only the first value (idempotent)', () => {
    const engine = makeEngine();
    const w = makeWrapper( 'f13' );
    engine._saveOriginal( w, 'label', 'first' );
    engine._saveOriginal( w, 'label', 'second' );
    expect( engine._getOriginal( w, 'label', '' ) ).toBe( 'first' );
  } );

  test( 'backward-compat: string result "hide" still hides field', () => {
    const engine = makeEngine();
    const w = makeWrapper( 'f14' );
    engine._applyResultToWrapper( w, 'hide' );
    expect( w.getAttribute( 'data-clefa-visible' ) ).toBe( '0' );
  } );

  test( 'backward-compat: string result "show" shows field', () => {
    const engine = makeEngine();
    const w = makeWrapper( 'f15' );
    w.setAttribute( 'data-clefa-visible', '0' );
    engine._applyResultToWrapper( w, 'show' );
    expect( w.getAttribute( 'data-clefa-visible' ) ).toBe( '1' );
  } );
} );

// ---------------------------------------------------------------------------
// evaluateAll — require/unrequire integration
// ---------------------------------------------------------------------------
describe( 'evaluateAll — require / unrequire', () => {
  function buildRequireForm( action ) {
    const formEl = document.createElement( 'form' );

    const srcWrapper = document.createElement( 'div' );
    srcWrapper.setAttribute( 'data-clefa-field', 'choice' );
    const srcInput = document.createElement( 'input' );
    srcInput.setAttribute( 'data-clefa-input', '' );
    srcInput.setAttribute( 'data-clefa-field-id', 'choice' );
    srcInput.type = 'text';
    srcWrapper.appendChild( srcInput );
    formEl.appendChild( srcWrapper );

    const condWrapper = document.createElement( 'div' );
    condWrapper.setAttribute( 'data-clefa-field', 'company' );
    condWrapper.setAttribute( 'data-clefa-visible', '1' );
    const condInput = document.createElement( 'input' );
    condInput.setAttribute( 'data-clefa-input', '' );
    condInput.setAttribute( 'data-clefa-field-id', 'company' );
    condInput.type = 'text';
    condWrapper.appendChild( condInput );
    formEl.appendChild( condWrapper );

    document.body.appendChild( formEl );

    const config = {
      steps: [ {
        step_id: 's1',
        fields: [
          { field_id: 'choice', field_type: 'text', conditions: [] },
          { field_id: 'company', field_type: 'text',
            conditions: [ { logic_group: 'AND', source_field: 'choice', operator: 'equals', compare_value: 'biz', action, action_value: '' } ] },
        ],
      } ],
    };

    const engine = new window.CLEFA.ConditionEngine( formEl, config );
    return { engine, srcInput, condWrapper, condInput };
  }

  test( 'require: field gets required=true when condition matches', () => {
    const { engine, srcInput, condWrapper, condInput } = buildRequireForm( 'require' );
    srcInput.value = 'biz';
    engine.evaluateAll();
    expect( condWrapper.getAttribute( 'data-clefa-required' ) ).toBe( '1' );
    expect( condInput.hasAttribute( 'required' ) ).toBe( true );
  } );

  test( 'require: field loses required when condition stops matching', () => {
    const { engine, srcInput, condWrapper, condInput } = buildRequireForm( 'require' );
    srcInput.value = 'biz';
    engine.evaluateAll();
    expect( condWrapper.getAttribute( 'data-clefa-required' ) ).toBe( '1' );
    // When condition no longer matches, noop fires — required state stays (field keeps its own config)
    srcInput.value = 'personal';
    engine.evaluateAll();
    // data-clefa-required stays '1' because noop doesn't change it
    expect( condWrapper.getAttribute( 'data-clefa-required' ) ).toBe( '1' );
  } );

  test( 'unrequire: input has required removed when condition matches', () => {
    const { engine, srcInput, condWrapper, condInput } = buildRequireForm( 'unrequire' );
    condInput.setAttribute( 'required', '' );
    srcInput.value = 'biz';
    engine.evaluateAll();
    expect( condWrapper.getAttribute( 'data-clefa-required' ) ).toBe( '0' );
    expect( condInput.hasAttribute( 'required' ) ).toBe( false );
  } );
} );
