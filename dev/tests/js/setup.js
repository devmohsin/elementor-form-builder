/**
 * Global setup injected before every test file.
 * Provides the minimal browser globals the CLEFA IIFE modules need.
 */

// Stub WordPress REST localization object
global.clefaFrontend = {
  restUrl:      'http://localhost/wp-json/clefa/v1',
  nonce:        'test-nonce-123',
  refreshNonce: false,
  ajaxUrl:      'http://localhost/wp-admin/admin-ajax.php',
};

global.CLEFA_TESTING = true;
global.clefaBuilderData = {
  formId: 0,
  formConfig: null,
  i18n: {
    newFormName: 'Test Form',
    pluginRequired: 'Plugin required',
    saving: 'Saving…',
    saved: 'Saved',
    saveError: 'Save failed',
    unsavedChanges: 'Unsaved changes',
  },
  fieldTypes: {
    basic: {
      label: 'Basic',
      fields: [
        { type: 'text', label: 'Text', icon: 'dashicons-editor-textcolor' },
        { type: 'email', label: 'Email', icon: 'dashicons-email' },
        { type: 'select', label: 'Select', icon: 'dashicons-list-view' },
      ],
    },
  },
  actionTypes: [
    { type: 'email', label: 'Email', icon: 'dashicons-email-alt', available: true },
  ],
  dependencies: {},
  restUrl: 'http://localhost/wp-json/clefa/v1',
  nonce: 'test-nonce-builder',
};

// Provide global CLEFA namespace
global.CLEFA = {};

// jsdom doesn't implement scrollIntoView — stub it silently
window.HTMLElement.prototype.scrollIntoView = jest.fn();

// CustomEvent works in jsdom but ensure it bubbles properly
global.CustomEvent = CustomEvent;

// Suppress jsdom "Not implemented: navigation" noise from redirect tests.
// The clefa:redirect:before event still fires and is asserted in tests.
const originalConsoleError = console.error;
console.error = ( ...args ) => {
  if ( typeof args[ 0 ] === 'object' && args[ 0 ] && args[ 0 ].type === 'not implemented' ) return;
  originalConsoleError( ...args );
};
