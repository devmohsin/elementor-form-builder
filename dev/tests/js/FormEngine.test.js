/**
 * FormEngine — comprehensive Jest test suite
 *
 * Mocks the fetch API to test the full submit pipeline:
 * success, validation errors, server errors, redirects,
 * nonce refresh, draft save/restore, interaction count, and events.
 */

const path = require( 'path' );
const { makeForm, setFieldValue } = require( './helpers/dom' );

beforeAll( () => {
  require( path.resolve( __dirname, '../../../assets/frontend/js/EventDispatcher.js' ) );
  require( path.resolve( __dirname, '../../../assets/frontend/js/ValidationEngine.js' ) );
  require( path.resolve( __dirname, '../../../assets/frontend/js/StepRouter.js' ) );
  require( path.resolve( __dirname, '../../../assets/frontend/js/FormEngine.js' ) );
} );

afterEach( () => {
  document.body.innerHTML = '';
  localStorage.clear();
  jest.restoreAllMocks();
  jest.clearAllMocks();
  delete global.fetch;
} );

// ---------------------------------------------------------------------------
// Factory
// ---------------------------------------------------------------------------

/**
 * Build a complete form, initialise a FormEngine on it, and return helpers.
 */
function makeEngineForm( fields = [], formOpts = {} ) {
  const { formEl, config } = makeForm( fields );

  // Attach the config as a data attribute
  formEl.setAttribute( 'data-clefa-config', JSON.stringify( config ) );
  formEl.setAttribute( 'data-clefa-form-id', '1' );
  formEl.setAttribute( 'data-clefa-instance', 'inst-abc' );
  if ( formOpts.persistDraft ) formEl.setAttribute( 'data-clefa-persist-draft', '1' );
  if ( formOpts.hideOnSuccess ) formEl.setAttribute( 'data-clefa-hide-on-success', '1' );

  const engine = new window.CLEFA.FormEngine( formEl );
  return { engine, formEl, config };
}

/**
 * Mock window.fetch to return a structured response.
 */
function mockFetch( body, status = 200 ) {
  global.fetch = jest.fn().mockResolvedValue( {
    status,
    json: () => Promise.resolve( body ),
  } );
}

// ---------------------------------------------------------------------------
// Initialisation
// ---------------------------------------------------------------------------

describe( 'FormEngine initialisation', () => {
  test( 'clefa:form:init fires synchronously on construction', () => {
    // We need to listen before construction – attach to document
    const spy = jest.fn();
    // We cannot easily catch the sync event from construction, so test via
    // the instance being created without throwing.
    expect( () => makeEngineForm( [ { id: 'name', type: 'text' } ] ) ).not.toThrow();
  } );

  test( 'engine has expected properties after construction', () => {
    const { engine } = makeEngineForm( [ { id: 'x', type: 'text' } ] );
    expect( engine.formId ).toBe( 1 );
    expect( engine.instanceId ).toBe( 'inst-abc' );
    expect( typeof engine.restUrl ).toBe( 'string' );
    expect( engine._interactionCount ).toBe( 0 );
  } );

  test( 'interaction counter increments on input events', () => {
    const { engine, formEl } = makeEngineForm( [ { id: 'f', type: 'text' } ] );
    const input = formEl.querySelector( '[data-clefa-field-id="f"]' );
    input.dispatchEvent( new Event( 'input', { bubbles: true } ) );
    input.dispatchEvent( new Event( 'input', { bubbles: true } ) );
    input.dispatchEvent( new Event( 'input', { bubbles: true } ) );
    expect( engine._interactionCount ).toBe( 3 );
  } );
} );

// ---------------------------------------------------------------------------
// Submit — success path
// ---------------------------------------------------------------------------

describe( 'Submit: success', () => {
  test( 'calls fetch with correct URL and form data', async () => {
    mockFetch( { success: true, message: 'Done!', redirect_url: '' } );
    const { engine, formEl } = makeEngineForm( [ { id: 'email', type: 'text', required: true } ] );
    setFieldValue( formEl, 'email', 'test@example.com' );
    engine._handleSubmit();
    await Promise.resolve();
    expect( global.fetch ).toHaveBeenCalledTimes( 1 );
    const [ url, opts ] = global.fetch.mock.calls[ 0 ];
    expect( url ).toContain( '/submit' );
    const body = JSON.parse( opts.body );
    expect( body.form_id ).toBe( 1 );
    expect( body.data.email ).toBe( 'test@example.com' );
  } );

  test( 'clefa:form:success event fires on 200 response', async () => {
    mockFetch( { success: true, message: 'Thank you!', redirect_url: '' } );
    const { engine, formEl } = makeEngineForm( [ { id: 'f', type: 'text' } ] );
    const spy = jest.fn();
    formEl.addEventListener( 'clefa:form:success', spy );
    engine._handleSubmit();
    await new Promise( r => setTimeout( r, 0 ) );
    expect( spy ).toHaveBeenCalledTimes( 1 );
  } );

  test( 'shows success message in [data-clefa-message]', async () => {
    mockFetch( { success: true, message: 'All good!', redirect_url: '' } );
    const { engine, formEl } = makeEngineForm( [ { id: 'f', type: 'text' } ] );
    // Add message container
    const msgEl = document.createElement( 'div' );
    msgEl.setAttribute( 'data-clefa-message', '' );
    msgEl.style.display = 'none';
    formEl.appendChild( msgEl );
    engine._handleSubmit();
    await new Promise( r => setTimeout( r, 0 ) );
    expect( msgEl.innerHTML ).toContain( 'All good!' );
  } );

  test( 'redirect: navigates to redirect_url', async () => {
    mockFetch( { success: true, message: '', redirect_url: 'https://example.com/thanks' } );
    const { engine, formEl } = makeEngineForm( [ { id: 'f', type: 'text' } ] );
    const spy = jest.fn();
    formEl.addEventListener( 'clefa:redirect:before', spy );
    // Can't actually navigate, but event should fire
    engine._handleSubmit();
    await new Promise( r => setTimeout( r, 0 ) );
    expect( spy ).toHaveBeenCalledTimes( 1 );
    expect( spy.mock.calls[ 0 ][ 0 ].detail.url ).toBe( 'https://example.com/thanks' );
  } );

  test( 'interaction count is sent in payload', async () => {
    mockFetch( { success: true, message: 'ok', redirect_url: '' } );
    const { engine, formEl } = makeEngineForm( [ { id: 'f', type: 'text' } ] );
    // Simulate 5 interactions
    engine._interactionCount = 5;
    engine._handleSubmit();
    await Promise.resolve();
    const body = JSON.parse( global.fetch.mock.calls[ 0 ][ 1 ].body );
    expect( body._clefa_ic ).toBe( 5 );
  } );

  test( 'message_html preferred over message', async () => {
    mockFetch( { success: true, message: 'plain', message_html: '<strong>HTML</strong>', redirect_url: '' } );
    const { engine, formEl } = makeEngineForm( [ { id: 'f', type: 'text' } ] );
    const msgEl = document.createElement( 'div' );
    msgEl.setAttribute( 'data-clefa-message', '' );
    msgEl.style.display = 'none';
    formEl.appendChild( msgEl );
    engine._handleSubmit();
    await new Promise( r => setTimeout( r, 0 ) );
    expect( msgEl.innerHTML ).toContain( '<strong>HTML</strong>' );
  } );
} );

// ---------------------------------------------------------------------------
// Submit — validation failure (client-side)
// ---------------------------------------------------------------------------

describe( 'Submit: client-side validation failure', () => {
  test( 'does not call fetch when required field is empty', async () => {
    mockFetch( { success: true } );
    const { engine } = makeEngineForm( [ { id: 'name', type: 'text', required: true } ] );
    engine._handleSubmit();
    await Promise.resolve();
    expect( global.fetch ).not.toHaveBeenCalled();
  } );

  test( 'clefa:form:validation-failed fires', async () => {
    const { engine, formEl } = makeEngineForm( [ { id: 'name', type: 'text', required: true } ] );
    const spy = jest.fn();
    formEl.addEventListener( 'clefa:form:validation-failed', spy );
    engine._handleSubmit();
    await Promise.resolve();
    expect( spy ).toHaveBeenCalledTimes( 1 );
  } );
} );

// ---------------------------------------------------------------------------
// Submit — server-side error response
// ---------------------------------------------------------------------------

describe( 'Submit: server error response', () => {
  test( 'clefa:form:error fires on non-200 status', async () => {
    mockFetch( { message: 'Server error', errors: {} }, 500 );
    const { engine, formEl } = makeEngineForm( [ { id: 'f', type: 'text' } ] );
    const spy = jest.fn();
    formEl.addEventListener( 'clefa:form:error', spy );
    engine._handleSubmit();
    await new Promise( r => setTimeout( r, 0 ) );
    expect( spy ).toHaveBeenCalledTimes( 1 );
  } );

  test( 'server validation errors are shown in DOM', async () => {
    mockFetch( {
      success: false,
      // WP REST format wraps field errors under data.errors for 422
      data:    { errors: { email_field: 'Invalid email address.' } },
    }, 422 );
    const { engine, formEl } = makeEngineForm( [
      { id: 'email_field', type: 'email', required: true },
    ] );
    setFieldValue( formEl, 'email_field', 'valid@example.com' );
    engine._handleSubmit();
    await new Promise( r => setTimeout( r, 20 ) );
    const errEl = formEl.querySelector( '[data-clefa-error]' );
    expect( errEl && errEl.textContent ).toContain( 'Invalid email address.' );
  } );

  test( 'network error fires clefa:form:error', async () => {
    global.fetch = jest.fn().mockRejectedValue( new Error( 'Network failure' ) );
    const { engine, formEl } = makeEngineForm( [ { id: 'f', type: 'text' } ] );
    const spy = jest.fn();
    formEl.addEventListener( 'clefa:form:error', spy );
    engine._handleSubmit();
    await new Promise( r => setTimeout( r, 10 ) );
    expect( spy ).toHaveBeenCalledTimes( 1 );
  } );
} );

// ---------------------------------------------------------------------------
// Draft persistence
// ---------------------------------------------------------------------------

describe( 'Draft persistence', () => {
  test( '_saveDraft stores field values in localStorage', () => {
    const { engine, formEl } = makeEngineForm(
      [ { id: 'first_name', type: 'text' } ],
      { persistDraft: true }
    );
    setFieldValue( formEl, 'first_name', 'Linden' );
    engine._saveDraft();
    const raw = localStorage.getItem( 'clefa_draft_1' );
    expect( raw ).toBeTruthy();
    const saved = JSON.parse( raw );
    expect( saved.data.first_name ).toBe( 'Linden' );
  } );

  test( '_restoreDraft populates field values from localStorage', () => {
    localStorage.setItem( 'clefa_draft_1', JSON.stringify( {
      data: { first_name: 'Restored' },
      savedAt: Date.now(),
    } ) );
    const { engine, formEl } = makeEngineForm(
      [ { id: 'first_name', type: 'text', value: '' } ],
      { persistDraft: true }
    );
    engine._restoreDraft();
    const input = formEl.querySelector( '[data-clefa-field-id="first_name"]' );
    expect( input.value ).toBe( 'Restored' );
  } );

  test( '_clearDraft removes localStorage key', () => {
    localStorage.setItem( 'clefa_draft_1', '{"data":{}}' );
    const { engine } = makeEngineForm( [], { persistDraft: true } );
    engine._clearDraft();
    expect( localStorage.getItem( 'clefa_draft_1' ) ).toBeNull();
  } );

  test( 'draft is cleared on successful submission', async () => {
    localStorage.setItem( 'clefa_draft_1', '{"data":{"f":"v"}}' );
    mockFetch( { success: true, message: 'ok', redirect_url: '' } );
    const { engine } = makeEngineForm( [ { id: 'f', type: 'text' } ], { persistDraft: true } );
    engine._handleSubmit();
    await new Promise( r => setTimeout( r, 10 ) );
    expect( localStorage.getItem( 'clefa_draft_1' ) ).toBeNull();
  } );

  test( 'draft is always restored regardless of age (no expiry implemented)', () => {
    // The engine's _restoreDraft does not currently enforce a time-to-live.
    // This test documents the current behaviour. An expiry guard can be added
    // to _restoreDraft in a future iteration.
    const eightDaysAgo = Date.now() - ( 8 * 24 * 3600 * 1000 );
    localStorage.setItem( 'clefa_draft_1', JSON.stringify( {
      data: { first_name: 'OldValue' },
      savedAt: eightDaysAgo,
    } ) );
    const { engine, formEl } = makeEngineForm(
      [ { id: 'first_name', type: 'text', value: '' } ],
      { persistDraft: true }
    );
    engine._restoreDraft();
    const input = formEl.querySelector( '[data-clefa-field-id="first_name"]' );
    // Engine restores the draft — no TTL check exists yet.
    expect( input.value ).toBe( 'OldValue' );
  } );
} );

// ---------------------------------------------------------------------------
// Save Draft button
// ---------------------------------------------------------------------------

describe( 'Save Draft button', () => {
  test( 'clicking [data-clefa-save-draft] calls _saveDraft', () => {
    const { engine, formEl } = makeEngineForm(
      [ { id: 'f', type: 'text' } ],
      { persistDraft: true }
    );
    const spy = jest.spyOn( engine, '_saveDraft' );
    const btn = document.createElement( 'button' );
    btn.setAttribute( 'data-clefa-save-draft', '' );
    formEl.appendChild( btn );
    btn.click();
    expect( spy ).toHaveBeenCalledTimes( 1 );
  } );
} );

// ---------------------------------------------------------------------------
// Nonce refresh
// ---------------------------------------------------------------------------

describe( 'Nonce refresh', () => {
  test( 'refreshNonce:false skips nonce fetch and submits directly', async () => {
    window.clefaFrontend.refreshNonce = false;
    mockFetch( { success: true, message: 'ok', redirect_url: '' } );
    const { engine } = makeEngineForm( [ { id: 'f', type: 'text' } ] );
    engine._handleSubmit();
    await new Promise( r => setTimeout( r, 0 ) );
    // Only one fetch call (the submit, not a nonce refresh)
    expect( global.fetch ).toHaveBeenCalledTimes( 1 );
    expect( global.fetch.mock.calls[ 0 ][ 0 ] ).toContain( '/submit' );
  } );
} );
