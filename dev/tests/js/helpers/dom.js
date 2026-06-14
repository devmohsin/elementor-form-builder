/**
 * DOM factory helpers shared across test files.
 */

/**
 * Build a minimal form element containing field wrappers.
 *
 * @param {Array<{id, type, required?, validation_rules?, value?}>} fields
 * @param {Object} [stepOpts]  – extra step attributes
 * @returns {{ formEl, config }}
 */
function makeForm( fields, stepOpts = {} ) {
  const formEl = document.createElement( 'form' );
  formEl.setAttribute( 'data-clefa-form-id', '1' );
  formEl.setAttribute( 'data-clefa-instance', 'test-instance' );

  const stepEl = document.createElement( 'div' );
  stepEl.setAttribute( 'data-clefa-step', 'step-1' );
  stepEl.setAttribute( 'data-clefa-step-index', '0' );
  stepEl.setAttribute( 'data-clefa-step-active', '1' );
  Object.entries( stepOpts ).forEach( ( [ k, v ] ) => stepEl.setAttribute( k, v ) );

  const configFields = [];

  fields.forEach( ( f ) => {
    const wrapper = document.createElement( 'div' );
    wrapper.setAttribute( 'data-clefa-field', f.id );
    wrapper.setAttribute( 'data-clefa-field-type', f.type || 'text' );
    wrapper.setAttribute( 'data-clefa-visible', '1' );

    const tag  = f.type === 'textarea' ? 'textarea' : ( f.type === 'select' ? 'select' : 'input' );
    const input = document.createElement( tag );
    input.setAttribute( 'data-clefa-input', '' );
    input.setAttribute( 'data-clefa-field-id', f.id );
    input.id = `clefa-field-${f.id}`;

    if ( tag === 'input' ) {
      input.type  = f.type || 'text';
      input.value = f.value ?? '';
    } else if ( tag === 'textarea' ) {
      input.value = f.value ?? '';
    }

    if ( f.required ) input.setAttribute( 'required', '' );

    const errEl = document.createElement( 'span' );
    errEl.setAttribute( 'data-clefa-error', '' );
    errEl.style.display = 'none';

    wrapper.appendChild( input );
    wrapper.appendChild( errEl );
    stepEl.appendChild( wrapper );

    configFields.push( {
      field_id:         f.id,
      field_type:       f.type || 'text',
      label:            f.label || f.id,
      required:         !! f.required,
      validation_rules: f.validation_rules || [],
    } );
  } );

  // Submit button
  const submitBtn = document.createElement( 'button' );
  submitBtn.setAttribute( 'data-clefa-submit', '' );
  submitBtn.type = 'submit';
  stepEl.appendChild( submitBtn );

  formEl.appendChild( stepEl );
  var mount = document.getElementById( 'clefa-test-mount' );
  if ( ! mount ) {
    mount = document.createElement( 'div' );
    mount.id = 'clefa-test-mount';
    document.body.appendChild( mount );
  }
  mount.appendChild( formEl );

  const config = {
    form_id: 1,
    steps:   [ { step_id: 'step-1', fields: configFields } ],
  };

  return { formEl, stepEl, config };
}

/**
 * Set the value of a field input inside a form.
 */
function setFieldValue( formEl, fieldId, value ) {
  const inputs = formEl.querySelectorAll( `[data-clefa-field-id="${fieldId}"]` );
  if ( ! inputs.length ) return;
  const first = inputs[ 0 ];
  if ( first.type === 'checkbox' ) {
    const values = [].concat( value );
    inputs.forEach( ( inp ) => { inp.checked = values.includes( inp.value ); } );
  } else if ( first.type === 'radio' ) {
    inputs.forEach( ( inp ) => { inp.checked = inp.value === value; } );
  } else {
    first.value = value;
  }
}

/**
 * Get the visible error message for a field.
 */
function getErrorText( formEl, fieldId ) {
  const wrapper = formEl.querySelector( `[data-clefa-field="${fieldId}"]` );
  if ( ! wrapper ) return null;
  const errEl = wrapper.querySelector( '[data-clefa-error]' );
  return errEl ? errEl.textContent : null;
}

/**
 * Check whether the input for a field carries the error class.
 */
function hasErrorClass( formEl, fieldId ) {
  const input = formEl.querySelector( `[data-clefa-field-id="${fieldId}"]` );
  return input ? input.classList.contains( 'clefa-input-error' ) : false;
}

module.exports = { makeForm, setFieldValue, getErrorText, hasErrorClass };
