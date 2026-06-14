/**
 * Parent page bridge — mirrors iframe test output into the card pre block only.
 * All test execution lives inside the runner iframe.
 */
( function () {
	'use strict';

	window.addEventListener( 'message', function ( event ) {
		if ( event.origin !== window.location.origin ) {
			return;
		}
		var data = event.data;
		if ( ! data || data.type !== 'clefa-js-test-complete' || ! data.report ) {
			return;
		}

		var output = document.querySelector( '[data-clefa-js-test-output]' );
		if ( ! output ) {
			return;
		}

		var report = data.report;
		output.textContent = report.output || '';
		output.setAttribute(
			'data-clefa-status',
			report.summary && report.summary.perfect ? 'pass' : 'fail'
		);
	} );
}() );
