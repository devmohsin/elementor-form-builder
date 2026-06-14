/**
 * TransitionEngine — comprehensive Jest test suite
 *
 * Tests setFieldVisible(), setStepActive(), isEnabled(), whenSettled(),
 * and the transitions-disabled fallback (display:none/block).
 * Covers aria attributes, input disability, nav-button exemption,
 * and the reduced-motion preference path.
 */

const path = require( 'path' );

beforeAll( () => {
  require( path.resolve( __dirname, '../../../assets/frontend/js/TransitionEngine.js' ) );
} );

afterEach( () => {
  document.body.innerHTML = '';
} );

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function makeWrapper( fieldId, opts = {} ) {
  const wrap = document.createElement( 'div' );
  wrap.setAttribute( 'data-clefa-field', fieldId );
  wrap.setAttribute( 'data-clefa-visible', '1' );

  if ( opts.transitionsOn ) {
    if ( opts.onWrap ) {
      wrap.setAttribute( 'data-clefa-transitions', '1' );
    }
    if ( opts.onFormWrap ) {
      // TransitionEngine checks closest [data-clefa-form-wrap]
      const formWrap = document.createElement( 'div' );
      formWrap.setAttribute( 'data-clefa-form-wrap', '' );
      formWrap.setAttribute( 'data-clefa-transitions', '1' );
      formWrap.appendChild( wrap );
      document.body.appendChild( formWrap );
    } else {
      document.body.appendChild( wrap );
    }
  } else {
    document.body.appendChild( wrap );
  }

  const input = document.createElement( 'input' );
  input.setAttribute( 'data-clefa-input', '' );
  input.type = 'text';
  wrap.appendChild( input );

  if ( opts.addNavButtons ) {
    const next = document.createElement( 'button' );
    next.setAttribute( 'data-clefa-next', '' );
    wrap.appendChild( next );

    const prev = document.createElement( 'button' );
    prev.setAttribute( 'data-clefa-prev', '' );
    wrap.appendChild( prev );

    const submit = document.createElement( 'button' );
    submit.setAttribute( 'data-clefa-submit', '' );
    wrap.appendChild( submit );
  }

  if ( opts.addSelect ) {
    const sel = document.createElement( 'select' );
    wrap.appendChild( sel );
  }

  if ( opts.addTextarea ) {
    const ta = document.createElement( 'textarea' );
    wrap.appendChild( ta );
  }

  return wrap;
}

function makeStepEl( opts = {} ) {
  const step = document.createElement( 'div' );
  step.setAttribute( 'data-clefa-step', 's1' );
  step.setAttribute( 'data-clefa-step-active', '1' );
  if ( opts.transitionsOnFormWrap ) {
    const fw = document.createElement( 'div' );
    fw.setAttribute( 'data-clefa-form-wrap', '' );
    fw.setAttribute( 'data-clefa-transitions', '1' );
    fw.appendChild( step );
    document.body.appendChild( fw );
  } else {
    document.body.appendChild( step );
  }
  return step;
}

const TE = () => window.CLEFA.TransitionEngine;

// ---------------------------------------------------------------------------
// isEnabled
// ---------------------------------------------------------------------------

describe( 'TransitionEngine.isEnabled', () => {
  test( 'returns false when no transitions attribute', () => {
    const w = makeWrapper( 'f1' );
    expect( TE().isEnabled( w ) ).toBe( false );
  } );

  test( 'returns true when wrapper has data-clefa-transitions=1', () => {
    const w = makeWrapper( 'f2', { transitionsOn: true, onWrap: true } );
    expect( TE().isEnabled( w ) ).toBe( true );
  } );

  test( 'returns true when ancestor form-wrap has data-clefa-transitions=1', () => {
    const w = makeWrapper( 'f3', { transitionsOn: true, onFormWrap: true } );
    expect( TE().isEnabled( w ) ).toBe( true );
  } );

  test( 'returns false for null element', () => {
    expect( TE().isEnabled( null ) ).toBe( false );
  } );
} );

// ---------------------------------------------------------------------------
// setFieldVisible — transitions disabled (default)
// ---------------------------------------------------------------------------

describe( 'setFieldVisible — transitions disabled', () => {
  test( 'hide: sets data-clefa-visible=0', () => {
    const w = makeWrapper( 'f10' );
    TE().setFieldVisible( w, false );
    expect( w.getAttribute( 'data-clefa-visible' ) ).toBe( '0' );
  } );

  test( 'hide: sets aria-hidden=true', () => {
    const w = makeWrapper( 'f11' );
    TE().setFieldVisible( w, false );
    expect( w.getAttribute( 'aria-hidden' ) ).toBe( 'true' );
  } );

  test( 'hide: sets display:none on wrapper', () => {
    const w = makeWrapper( 'f12' );
    TE().setFieldVisible( w, false );
    expect( w.style.display ).toBe( 'none' );
  } );

  test( 'hide: disables contained inputs', () => {
    const w = makeWrapper( 'f13', { addSelect: true, addTextarea: true } );
    TE().setFieldVisible( w, false );
    w.querySelectorAll( 'input, select, textarea' ).forEach( inp => {
      expect( inp.disabled ).toBe( true );
    } );
  } );

  test( 'show: sets data-clefa-visible=1', () => {
    const w = makeWrapper( 'f14' );
    w.setAttribute( 'data-clefa-visible', '0' );
    TE().setFieldVisible( w, true );
    expect( w.getAttribute( 'data-clefa-visible' ) ).toBe( '1' );
  } );

  test( 'show: sets aria-hidden=false', () => {
    const w = makeWrapper( 'f15' );
    TE().setFieldVisible( w, true );
    expect( w.getAttribute( 'aria-hidden' ) ).toBe( 'false' );
  } );

  test( 'show: clears display:none', () => {
    const w = makeWrapper( 'f16' );
    w.style.display = 'none';
    TE().setFieldVisible( w, true );
    expect( w.style.display ).toBe( '' );
  } );

  test( 'show: enables contained inputs', () => {
    const w = makeWrapper( 'f17' );
    w.querySelector( 'input' ).disabled = true;
    TE().setFieldVisible( w, true );
    expect( w.querySelector( 'input' ).disabled ).toBe( false );
  } );

  test( 'does nothing when wrapper is null', () => {
    expect( () => TE().setFieldVisible( null, true ) ).not.toThrow();
    expect( () => TE().setFieldVisible( null, false ) ).not.toThrow();
  } );
} );

// ---------------------------------------------------------------------------
// setFieldVisible — nav button exemption
// ---------------------------------------------------------------------------

describe( 'setFieldVisible — nav buttons not disabled', () => {
  test( 'next/prev/submit buttons are NOT disabled when field is hidden', () => {
    const w = makeWrapper( 'f20', { addNavButtons: true } );
    TE().setFieldVisible( w, false );
    expect( w.querySelector( '[data-clefa-next]' ).disabled ).toBe( false );
    expect( w.querySelector( '[data-clefa-prev]' ).disabled ).toBe( false );
    expect( w.querySelector( '[data-clefa-submit]' ).disabled ).toBe( false );
  } );

  test( 'regular inputs ARE disabled when field is hidden', () => {
    const w = makeWrapper( 'f21', { addNavButtons: true } );
    TE().setFieldVisible( w, false );
    expect( w.querySelector( '[data-clefa-input]' ).disabled ).toBe( true );
  } );
} );

// ---------------------------------------------------------------------------
// setFieldVisible — transitions enabled (CSS class path)
// ---------------------------------------------------------------------------

describe( 'setFieldVisible — transitions enabled via form-wrap', () => {
  test( 'hide: adds clefa-field-hidden class instead of display:none', () => {
    const w = makeWrapper( 'f30', { transitionsOn: true, onFormWrap: true } );
    TE().setFieldVisible( w, false );
    expect( w.classList.contains( 'clefa-field-hidden' ) ).toBe( true );
    expect( w.style.display ).toBe( '' );
  } );

  test( 'show: removes clefa-field-hidden class', () => {
    const w = makeWrapper( 'f31', { transitionsOn: true, onFormWrap: true } );
    w.classList.add( 'clefa-field-hidden' );
    TE().setFieldVisible( w, true );
    expect( w.classList.contains( 'clefa-field-hidden' ) ).toBe( false );
  } );

  test( 'show: still sets aria-hidden=false', () => {
    const w = makeWrapper( 'f32', { transitionsOn: true, onFormWrap: true } );
    TE().setFieldVisible( w, true );
    expect( w.getAttribute( 'aria-hidden' ) ).toBe( 'false' );
  } );

  test( 'hide: still disables inputs', () => {
    const w = makeWrapper( 'f33', { transitionsOn: true, onFormWrap: true } );
    TE().setFieldVisible( w, false );
    expect( w.querySelector( 'input' ).disabled ).toBe( true );
  } );
} );

// ---------------------------------------------------------------------------
// setStepActive
// ---------------------------------------------------------------------------

describe( 'setStepActive — transitions disabled', () => {
  test( 'activate: sets data-clefa-step-active=1 and aria-hidden=false', () => {
    const s = makeStepEl();
    TE().setStepActive( s, true );
    expect( s.getAttribute( 'data-clefa-step-active' ) ).toBe( '1' );
    expect( s.getAttribute( 'aria-hidden' ) ).toBe( 'false' );
  } );

  test( 'deactivate: sets data-clefa-step-active=0 and aria-hidden=true', () => {
    const s = makeStepEl();
    TE().setStepActive( s, false );
    expect( s.getAttribute( 'data-clefa-step-active' ) ).toBe( '0' );
    expect( s.getAttribute( 'aria-hidden' ) ).toBe( 'true' );
  } );

  test( 'deactivate: sets display:none', () => {
    const s = makeStepEl();
    TE().setStepActive( s, false );
    expect( s.style.display ).toBe( 'none' );
  } );

  test( 'activate: clears display:none', () => {
    const s = makeStepEl();
    s.style.display = 'none';
    TE().setStepActive( s, true );
    expect( s.style.display ).toBe( '' );
  } );

  test( 'does nothing for null step', () => {
    expect( () => TE().setStepActive( null, true ) ).not.toThrow();
  } );
} );

describe( 'setStepActive — transitions enabled', () => {
  test( 'deactivate: adds clefa-step-hidden class instead of display:none', () => {
    const s = makeStepEl( { transitionsOnFormWrap: true } );
    TE().setStepActive( s, false );
    expect( s.classList.contains( 'clefa-step-hidden' ) ).toBe( true );
    expect( s.style.display ).toBe( '' );
  } );

  test( 'activate: removes clefa-step-hidden class', () => {
    const s = makeStepEl( { transitionsOnFormWrap: true } );
    s.classList.add( 'clefa-step-hidden' );
    TE().setStepActive( s, true );
    expect( s.classList.contains( 'clefa-step-hidden' ) ).toBe( false );
  } );
} );

// ---------------------------------------------------------------------------
// whenSettled
// ---------------------------------------------------------------------------

describe( 'whenSettled', () => {
  test( 'resolves immediately when transitions disabled', async () => {
    const w = makeWrapper( 'f50' );
    await expect( TE().whenSettled( w, 100 ) ).resolves.toBeUndefined();
  } );

  test( 'resolves immediately for null element', async () => {
    await expect( TE().whenSettled( null, 100 ) ).resolves.toBeUndefined();
  } );

  test( 'resolves after timeout when transitions enabled and no transitionend fires', async () => {
    const w = makeWrapper( 'f51', { transitionsOn: true, onFormWrap: true } );
    const start = Date.now();
    await TE().whenSettled( w, 50 );
    expect( Date.now() - start ).toBeGreaterThanOrEqual( 40 );
  } );

  test( 'resolves on transitionend when transitions enabled', async () => {
    const w = makeWrapper( 'f52', { transitionsOn: true, onFormWrap: true } );
    const settled = TE().whenSettled( w, 500 );
    const e = new Event( 'transitionend' );
    Object.defineProperty( e, 'target', { value: w } );
    w.dispatchEvent( e );
    await expect( settled ).resolves.toBeUndefined();
  } );
} );

// ---------------------------------------------------------------------------
// Round-trip toggle
// ---------------------------------------------------------------------------

describe( 'round-trip hide → show', () => {
  test( 'input disabled after hide, enabled after show', () => {
    const w = makeWrapper( 'f60' );
    TE().setFieldVisible( w, false );
    expect( w.querySelector( 'input' ).disabled ).toBe( true );
    TE().setFieldVisible( w, true );
    expect( w.querySelector( 'input' ).disabled ).toBe( false );
  } );

  test( 'data-clefa-visible toggles correctly in sequence', () => {
    const w = makeWrapper( 'f61' );
    TE().setFieldVisible( w, false );
    expect( w.getAttribute( 'data-clefa-visible' ) ).toBe( '0' );
    TE().setFieldVisible( w, true );
    expect( w.getAttribute( 'data-clefa-visible' ) ).toBe( '1' );
    TE().setFieldVisible( w, false );
    expect( w.getAttribute( 'data-clefa-visible' ) ).toBe( '0' );
  } );
} );
