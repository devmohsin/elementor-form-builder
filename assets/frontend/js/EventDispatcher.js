/* global clefaFrontend */
/**
 * CLEFA EventDispatcher
 *
 * Dispatches CustomEvents on the form element (bubbling) so developers
 * can hook into the form lifecycle.
 *
 * Usage (from any CLEFA module):
 *   CLEFAEventDispatcher.dispatch( 'clefa:submit:success', { formId, submissionId }, formEl );
 *
 * Usage (from external code):
 *   document.addEventListener( 'clefa:submit:success', e => console.log( e.detail ) );
 *   formEl.addEventListener( 'clefa:step:changed', e => console.log( e.detail ) );
 *
 * Complete event reference
 * ─────────────────────────────────────────────────────────────────────────────
 * Form lifecycle
 *   clefa:form:init            FormEngine just constructed (before sub-engines init)
 *   clefa:form:ready           All sub-engines initialised
 *   clefa:form:before-submit   About to send the submission request; cancelable
 *   clefa:form:success         Submission accepted by server
 *   clefa:form:error           Submission rejected or network error
 *   clefa:form:validation-failed  Client-side pre-submit validation failed
 *   clefa:form:draft-restored  Draft data reloaded from localStorage
 *   clefa:form:draft-saved     Draft manually saved via Save Draft button
 *
 * Step navigation
 *   clefa:step:before-change   Before step index changes (fromIndex, toIndex, total)
 *   clefa:step:changed         After step index changes  (currentIndex, total, stepId)
 *   clefa:step:validation-failed  Step-level validation failed (step, errors)
 *   clefa:step:blocked         Step navigation blocked by async blocker (step, reasons)
 *
 * Field events
 *   clefa:field:changed        Any input value changed (formId, fieldId, value)
 *
 * Conditions
 *   clefa:condition:matched    A field became visible  (fieldId)
 *   clefa:condition:unmatched  A field became hidden   (fieldId)
 *
 * Validation
 *   clefa:validation:failed    A field has an error    (fieldId, message)
 *   clefa:validation:passed    A field error cleared   (fieldId)
 *
 * Uploads
 *   clefa:upload:started       Upload XHR opened       (fileId, fileName, fieldId)
 *   clefa:upload:progress      Upload XHR progress     (fileId, percent, fieldId)
 *   clefa:upload:complete      Upload succeeded        (fileId, data)
 *   clefa:upload:failed        Upload failed           (fileId, message, fieldId)
 *
 * Live checks
 *   clefa:live-check:started   Live-check request sent (fieldId, checkType, value)
 *   clefa:live-check:success   Live-check passed       (fieldId, available, message)
 *   clefa:live-check:failed    Live-check failed       (fieldId, available, message)
 *
 * Repeater
 *   clefa:repeater:rowAdded    A new row was added     (repeaterId, rowIndex)
 *   clefa:repeater:rowRemoved  A row was removed       (repeaterId)
 *
 * Redirect
 *   clefa:redirect:before      About to redirect       (url, formId)
 * ─────────────────────────────────────────────────────────────────────────────
 */
( function() {
	'use strict';

	var debugEnabled = false;

	/** Usage hints shown in debug mode alongside each event name. */
	var EVENT_HINTS = {
		'clefa:form:init'              : 'Fired before sub-engines load. Use to set up early form state.',
		'clefa:form:ready'             : 'All engines initialised. Safe to interact with the form instance.',
		'clefa:form:before-submit'     : 'About to POST to the REST endpoint. Access e.detail.data to inspect payload.',
		'clefa:form:success'           : 'Submission accepted. e.detail.response contains the server response.',
		'clefa:form:error'             : 'Submission rejected or network error. e.detail.message contains the reason.',
		'clefa:form:validation-failed' : 'Client-side pre-submit validation failed. e.detail.errors is an object of fieldId → message.',
		'clefa:form:draft-restored'    : 'Draft data reloaded from localStorage. e.detail.formId is the form ID.',
		'clefa:step:before-change'     : 'Before step index changes. e.detail: { fromIndex, toIndex, total }.',
		'clefa:step:changed'           : 'After step index changes. Use to update progress UI or analytics.',
		'clefa:step:validation-failed' : 'Step-level validation blocked navigation. e.detail.errors lists field errors.',
		'clefa:field:changed'          : 'A field value changed. e.detail: { formId, fieldId, value }.',
		'clefa:condition:matched'      : 'A field became visible. e.detail.fieldId is the shown field.',
		'clefa:condition:unmatched'    : 'A field became hidden. e.detail.fieldId is the hidden field.',
		'clefa:validation:failed'      : 'A field has a new error. e.detail: { fieldId, message }.',
		'clefa:validation:passed'      : 'A field error was cleared. e.detail.fieldId is the now-valid field.',
		'clefa:upload:started'         : 'File XHR opened. e.detail: { fileId, fileName, fieldId }.',
		'clefa:upload:progress'        : 'Upload progress updated. e.detail: { fileId, percent, fieldId }.',
		'clefa:upload:complete'        : 'Upload succeeded. e.detail: { fileId, data } (data includes temp_id, file_url).',
		'clefa:upload:failed'          : 'Upload failed. e.detail: { fileId, message, fieldId }.',
		'clefa:live-check:started'     : 'Live API check request sent. e.detail: { fieldId, checkType, value }.',
		'clefa:live-check:success'     : 'Live check passed (e.g. username available). e.detail: { fieldId, available, message }.',
		'clefa:live-check:failed'      : 'Live check failed (e.g. username taken). e.detail: { fieldId, available, message }.',
		'clefa:repeater:rowAdded'      : 'A new repeater row was added. e.detail: { repeaterId, rowIndex }.',
		'clefa:repeater:rowRemoved'    : 'A repeater row was removed. e.detail.repeaterId is the repeater field ID.',
		'clefa:redirect:before'        : 'About to redirect. e.detail.url is the destination URL. Set location.href to override.',
	};

	window.CLEFAEventDispatcher = {

		/**
		 * @param {string}      eventName  Full event name, e.g. 'clefa:submit:success'
		 * @param {Object}      detail     Arbitrary payload
		 * @param {Element|null} target    Element to dispatch on; falls back to document
		 */
		dispatch: function( eventName, detail, target ) {
			var event;
			try {
				event = new CustomEvent( eventName, {
					bubbles    : true,
					cancelable : true,
					detail     : detail || {},
				} );
			} catch ( e ) {
				// IE 11 fallback
				event = document.createEvent( 'CustomEvent' );
				event.initCustomEvent( eventName, true, true, detail || {} );
			}

			( target || document ).dispatchEvent( event );

			if ( debugEnabled ) {
				var hint = EVENT_HINTS[ eventName ] || '';
				console.groupCollapsed( '[CLEFA] Event fired: ' + eventName );
				if ( hint ) {
					console.info( 'Hint:', hint );
				}
				if ( detail && Object.keys( detail ).length ) {
					console.info( 'Detail:', detail );
				}
				console.groupEnd();
			}
		},

		/**
		 * Enable / disable console logging of all events.
		 */
		setDebug: function( enabled ) {
			debugEnabled = !! enabled;
		},
	};

	// Auto-enable debug if the wp-localized config says so
	document.addEventListener( 'DOMContentLoaded', function() {
		if ( window.clefaFrontend && clefaFrontend.debugEvents ) {
			window.CLEFAEventDispatcher.setDebug( true );
		}
	} );

} )();
