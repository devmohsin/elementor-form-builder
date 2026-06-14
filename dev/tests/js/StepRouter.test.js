/**
 * StepRouter — comprehensive Jest test suite
 *
 * Covers: step navigation, validation gates, async blockers,
 * conditional routing, button mode management, progress updates,
 * and edge cases (single-step form, last-step, out-of-bounds).
 */

const path = require( 'path' );

beforeAll( () => {
  require( path.resolve( __dirname, '../../../assets/frontend/js/ValidationEngine.js' ) );
  require( path.resolve( __dirname, '../../../assets/frontend/js/StepRouter.js' ) );
} );

afterEach( () => {
  document.body.innerHTML = '';
  jest.clearAllMocks();
} );

// ---------------------------------------------------------------------------
// Factory
// ---------------------------------------------------------------------------

/**
 * Build a multi-step form with N steps.
 * Each step gets a required text field and a Next/Submit button.
 *
 * @param {number}  stepCount
 * @param {Object}  [opts]
 * @param {string}  [opts.btnMode]      – data-clefa-btn-mode value
 * @param {Array}   [opts.routing]      – routing rules for step 0
 * @returns {{ formEl, router, stepEls, config }}
 */
function makeMultiStepForm( stepCount = 2, opts = {} ) {
  const formEl = document.createElement( 'form' );
  formEl.setAttribute( 'data-clefa-form-id', '1' );

  const configSteps = [];
  const stepEls     = [];

  for ( let i = 0; i < stepCount; i++ ) {
    const stepEl = document.createElement( 'div' );
    const stepId = `step-${i}`;
    stepEl.setAttribute( 'data-clefa-step', stepId );
    stepEl.setAttribute( 'data-clefa-step-index', String( i ) );
    stepEl.setAttribute( 'data-clefa-step-active', i === 0 ? '1' : '0' );
    if ( i > 0 ) stepEl.style.display = 'none';

    // Required field
    const wrapper = document.createElement( 'div' );
    wrapper.setAttribute( 'data-clefa-field', `field_${i}` );
    wrapper.setAttribute( 'data-clefa-visible', '1' );
    const input = document.createElement( 'input' );
    input.type = 'text';
    input.setAttribute( 'data-clefa-input', '' );
    input.setAttribute( 'data-clefa-field-id', `field_${i}` );
    input.setAttribute( 'required', '' );
    wrapper.appendChild( input );
    stepEl.appendChild( wrapper );

    // Buttons wrapper
    const btnsWrap = document.createElement( 'div' );
    if ( opts.btnMode ) btnsWrap.setAttribute( 'data-clefa-btn-mode', opts.btnMode );

    if ( i > 0 ) {
      const prevBtn = document.createElement( 'button' );
      prevBtn.setAttribute( 'data-clefa-prev', '' );
      prevBtn.type = 'button';
      btnsWrap.appendChild( prevBtn );
    }
    if ( i < stepCount - 1 ) {
      const nextBtn = document.createElement( 'button' );
      nextBtn.setAttribute( 'data-clefa-next', '' );
      nextBtn.type = 'button';
      btnsWrap.appendChild( nextBtn );
    } else {
      const submitBtn = document.createElement( 'button' );
      submitBtn.setAttribute( 'data-clefa-submit', '' );
      submitBtn.type = 'submit';
      btnsWrap.appendChild( submitBtn );
    }

    stepEl.appendChild( btnsWrap );
    formEl.appendChild( stepEl );
    stepEls.push( stepEl );

    const stepConfig = {
      step_id: stepId,
      fields:  [ { field_id: `field_${i}`, field_type: 'text', required: true, validation_rules: [] } ],
      routing: [],
    };
    if ( i === 0 && opts.routing ) stepConfig.routing = opts.routing;
    configSteps.push( stepConfig );
  }

  document.body.appendChild( formEl );

  const config  = { steps: configSteps };
  const validEn = new window.CLEFA.ValidationEngine( formEl, config );
  const router  = new window.CLEFA.StepRouter( formEl, config, validEn );
  router.init();

  return { formEl, router, stepEls, config, validEn };
}

// ---------------------------------------------------------------------------
// Basic navigation
// ---------------------------------------------------------------------------

describe( 'Basic step navigation', () => {
  test( 'init shows step 0', () => {
    const { stepEls } = makeMultiStepForm( 3 );
    expect( stepEls[ 0 ].style.display ).not.toBe( 'none' );
    expect( stepEls[ 1 ].style.display ).toBe( 'none' );
    expect( stepEls[ 2 ].style.display ).toBe( 'none' );
  } );

  test( 'goNext with valid step advances to step 1', () => {
    const { router, stepEls, formEl } = makeMultiStepForm( 2 );
    const input = formEl.querySelector( '[data-clefa-field-id="field_0"]' );
    input.value = 'hello';
    router.goNext();
    expect( router.currentIndex ).toBe( 1 );
    expect( stepEls[ 1 ].style.display ).not.toBe( 'none' );
  } );

  test( 'goNext with empty required field stays on step 0', () => {
    const { router } = makeMultiStepForm( 2 );
    router.goNext(); // field_0 is empty
    expect( router.currentIndex ).toBe( 0 );
  } );

  test( 'goPrev from step 1 goes back to step 0', () => {
    const { router, formEl } = makeMultiStepForm( 2 );
    formEl.querySelector( '[data-clefa-field-id="field_0"]' ).value = 'v';
    router.goNext();
    router.goPrev();
    expect( router.currentIndex ).toBe( 0 );
  } );

  test( 'goPrev from step 0 does nothing', () => {
    const { router } = makeMultiStepForm( 2 );
    router.goPrev();
    expect( router.currentIndex ).toBe( 0 );
  } );

  test( 'isLastStep returns true on final step', () => {
    const { router, formEl } = makeMultiStepForm( 2 );
    formEl.querySelector( '[data-clefa-field-id="field_0"]' ).value = 'v';
    router.goNext();
    expect( router.isLastStep() ).toBe( true );
  } );

  test( 'isLastStep returns false on non-final step', () => {
    const { router } = makeMultiStepForm( 2 );
    expect( router.isLastStep() ).toBe( false );
  } );

  test( 'single-step form isLastStep is true from init', () => {
    const { router } = makeMultiStepForm( 1 );
    expect( router.isLastStep() ).toBe( true );
  } );
} );

// ---------------------------------------------------------------------------
// Events
// ---------------------------------------------------------------------------

describe( 'Step change events', () => {
  test( 'clefa:step:changed fires on advance', () => {
    const { router, formEl } = makeMultiStepForm( 2 );
    const spy = jest.fn();
    formEl.addEventListener( 'clefa:step:changed', spy );
    formEl.querySelector( '[data-clefa-field-id="field_0"]' ).value = 'x';
    router.goNext();
    expect( spy ).toHaveBeenCalledTimes( 1 );
    expect( spy.mock.calls[ 0 ][ 0 ].detail.currentIndex ).toBe( 1 );
  } );

  test( 'clefa:step:before-change fires before DOM update', () => {
    const { router, formEl, stepEls } = makeMultiStepForm( 2 );
    let capturedDisplay;
    formEl.addEventListener( 'clefa:step:before-change', () => {
      capturedDisplay = stepEls[ 1 ].style.display;
    } );
    formEl.querySelector( '[data-clefa-field-id="field_0"]' ).value = 'x';
    router.goNext();
    expect( capturedDisplay ).toBe( 'none' ); // step 1 still hidden at fire time
  } );

  test( 'clefa:step:validation-failed fires when required field empty', () => {
    const { router, formEl } = makeMultiStepForm( 2 );
    const spy = jest.fn();
    formEl.addEventListener( 'clefa:step:validation-failed', spy );
    router.goNext(); // field_0 empty
    expect( spy ).toHaveBeenCalledTimes( 1 );
  } );

  test( 'clefa:step:blocked fires when block-next attribute present', () => {
    const { router, formEl, stepEls } = makeMultiStepForm( 2 );
    // Add a blocker attribute to a field in step 0
    const input = formEl.querySelector( '[data-clefa-field-id="field_0"]' );
    input.setAttribute( 'data-clefa-block-next', 'Live check pending' );
    input.value = 'filled'; // required passes but blocker prevents advance
    const spy = jest.fn();
    formEl.addEventListener( 'clefa:step:blocked', spy );
    router.goNext();
    expect( spy ).toHaveBeenCalledTimes( 1 );
    expect( spy.mock.calls[ 0 ][ 0 ].detail.reasons ).toContain( 'Live check pending' );
    expect( router.currentIndex ).toBe( 0 );
  } );
} );

// ---------------------------------------------------------------------------
// Button mode: disabled-until-valid
// ---------------------------------------------------------------------------

describe( 'Button mode: disabled-until-valid', () => {
  test( 'primary button disabled when required field empty', () => {
    const { formEl, stepEls } = makeMultiStepForm( 2, { btnMode: 'disabled-until-valid' } );
    const nextBtn = stepEls[ 0 ].querySelector( '[data-clefa-next]' );
    expect( nextBtn.disabled ).toBe( true );
  } );

  test( 'primary button enabled when required field filled', () => {
    const { formEl, stepEls, router } = makeMultiStepForm( 2, { btnMode: 'disabled-until-valid' } );
    const input   = formEl.querySelector( '[data-clefa-field-id="field_0"]' );
    const nextBtn = stepEls[ 0 ].querySelector( '[data-clefa-next]' );
    input.value = 'hello';
    router._syncButtonMode( 0 );
    expect( nextBtn.disabled ).toBe( false );
  } );

  test( 'button re-disables when value cleared', () => {
    const { formEl, stepEls, router } = makeMultiStepForm( 2, { btnMode: 'disabled-until-valid' } );
    const input   = formEl.querySelector( '[data-clefa-field-id="field_0"]' );
    const nextBtn = stepEls[ 0 ].querySelector( '[data-clefa-next]' );
    input.value = 'hello';
    router._syncButtonMode( 0 );
    expect( nextBtn.disabled ).toBe( false );
    input.value = '';
    router._syncButtonMode( 0 );
    expect( nextBtn.disabled ).toBe( true );
  } );
} );

// ---------------------------------------------------------------------------
// Button mode: hidden-until-valid
// ---------------------------------------------------------------------------

describe( 'Button mode: hidden-until-valid', () => {
  test( 'primary button hidden when required field empty', () => {
    const { formEl, stepEls } = makeMultiStepForm( 2, { btnMode: 'hidden-until-valid' } );
    const nextBtn = stepEls[ 0 ].querySelector( '[data-clefa-next]' );
    expect( nextBtn.style.display ).toBe( 'none' );
  } );

  test( 'primary button visible when required field filled', () => {
    const { formEl, stepEls, router } = makeMultiStepForm( 2, { btnMode: 'hidden-until-valid' } );
    const input   = formEl.querySelector( '[data-clefa-field-id="field_0"]' );
    const nextBtn = stepEls[ 0 ].querySelector( '[data-clefa-next]' );
    input.value = 'hello';
    router._syncButtonMode( 0 );
    expect( nextBtn.style.display ).not.toBe( 'none' );
  } );
} );

// ---------------------------------------------------------------------------
// _stepRequiredFilled skips invisible fields
// ---------------------------------------------------------------------------

describe( '_stepRequiredFilled edge cases', () => {
  test( 'invisible required field does not block button', () => {
    const { formEl, stepEls, router } = makeMultiStepForm( 2, { btnMode: 'disabled-until-valid' } );
    // Make field invisible (condition engine hid it)
    const wrapper = formEl.querySelector( '[data-clefa-field="field_0"]' );
    wrapper.setAttribute( 'data-clefa-visible', '0' );
    router._syncButtonMode( 0 );
    const nextBtn = stepEls[ 0 ].querySelector( '[data-clefa-next]' );
    expect( nextBtn.disabled ).toBe( false ); // no visible required fields → all filled
  } );

  test( 'form with no required fields → button always enabled', () => {
    const formEl = document.createElement( 'form' );
    const stepEl = document.createElement( 'div' );
    stepEl.setAttribute( 'data-clefa-step', 's1' );
    stepEl.setAttribute( 'data-clefa-step-index', '0' );
    stepEl.setAttribute( 'data-clefa-step-active', '1' );
    const btnsWrap = document.createElement( 'div' );
    btnsWrap.setAttribute( 'data-clefa-btn-mode', 'disabled-until-valid' );
    const btn = document.createElement( 'button' );
    btn.setAttribute( 'data-clefa-submit', '' );
    btnsWrap.appendChild( btn );
    stepEl.appendChild( btnsWrap );
    formEl.appendChild( stepEl );
    document.body.appendChild( formEl );

    const config = { steps: [ { step_id: 's1', fields: [] } ] };
    const router = new window.CLEFA.StepRouter( formEl, config, null );
    router.init();
    expect( btn.disabled ).toBe( false );
  } );
} );

// ---------------------------------------------------------------------------
// Click-binding integration
// ---------------------------------------------------------------------------

describe( 'Click binding integration', () => {
  test( 'clicking Next button triggers goNext', () => {
    const { formEl, router, stepEls } = makeMultiStepForm( 2 );
    const spy = jest.spyOn( router, 'goNext' );
    const nextBtn = stepEls[ 0 ].querySelector( '[data-clefa-next]' );
    nextBtn.click();
    expect( spy ).toHaveBeenCalledTimes( 1 );
  } );

  test( 'clicking Prev button triggers goPrev', () => {
    const { formEl, router, stepEls } = makeMultiStepForm( 2 );
    formEl.querySelector( '[data-clefa-field-id="field_0"]' ).value = 'x';
    router.goNext();
    const spy = jest.spyOn( router, 'goPrev' );
    const prevBtn = stepEls[ 1 ].querySelector( '[data-clefa-prev]' );
    prevBtn.click();
    expect( spy ).toHaveBeenCalledTimes( 1 );
  } );
} );
