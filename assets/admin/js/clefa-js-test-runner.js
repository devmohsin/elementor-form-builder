/**

 * Self-contained JS test runner — single bundled test file, full results table.

 */

( function () {

	'use strict';



	if ( typeof window.clefaJsSuiteData === 'undefined' ) {

		return;

	}



	var i18n = window.clefaJsSuiteData.i18n || {};

	var FETCH_TIMEOUT_MS = 30000;

	var harnessReady = false;

	var nativeFetch = typeof window.fetch === 'function' ? window.fetch.bind( window ) : null;

	var resultsTableBody = null;

	var resultRowCount = 0;



	function escHtml( str ) {

		return String( str )

			.replace( /&/g, '&amp;' )

			.replace( /</g, '&lt;' )

			.replace( />/g, '&gt;' )

			.replace( /"/g, '&quot;' );

	}



	function yieldToUi() {

		return new Promise( function ( resolve ) {

			requestAnimationFrame( function () {

				setTimeout( resolve, 0 );

			} );

		} );

	}



	function scriptLabel( url ) {

		if ( ! url ) {

			return 'script';

		}

		try {

			var parsed = new URL( url, window.location.origin );

			var fileParam = parsed.searchParams.get( 'file' );

			if ( fileParam ) {

				return fileParam;

			}

		} catch ( e ) { /* fall through */ }

		var parts = String( url ).split( '?' )[ 0 ].split( '/' );

		return parts[ parts.length - 1 ] || url;

	}



	function getHarness() {

		if ( ! window.CLEFATest ) {

			throw new Error( 'Test harness not loaded' );

		}

		return window.CLEFATest;

	}



	function fetchWithTimeout( url, options, timeoutMs ) {

		timeoutMs = timeoutMs || FETCH_TIMEOUT_MS;

		var fetchFn = nativeFetch || window.fetch.bind( window );

		return Promise.race( [

			fetchFn( url, options ),

			new Promise( function ( _, reject ) {

				setTimeout( function () {

					reject( new Error( 'Timed out loading ' + scriptLabel( url ) + ' (' + timeoutMs + 'ms)' ) );

				}, timeoutMs );

			} ),

		] );

	}



	function injectScript( code ) {

		var script = document.createElement( 'script' );

		script.textContent = code;

		document.body.appendChild( script );

		script.remove();

	}



	function initScopeText() {

		var el = document.querySelector( '[data-clefa-js-runner-scope]' );

		if ( ! el || ! window.clefaJsSuiteData ) {

			return;

		}

		var files = window.clefaJsSuiteData.jsTestFileCount || 0;

		var php = window.clefaJsSuiteData.expectedPhpTests || 0;

		var scope = i18n.suiteScope || '';

		el.textContent = scope + ' One bundled run · ' + files + ' source files · full pass/fail table. PHPUnit: ' + php + ' (separate).';

	}



	function initResultsTable() {

		var container = document.querySelector( '[data-clefa-js-test-results]' );

		if ( ! container ) {

			return;

		}

		resultRowCount = 0;

		container.innerHTML = ''

			+ '<div class="clefa-js-runner-results-header">'

			+ escHtml( i18n.resultsTitle || 'All test results' )

			+ ' <span class="clefa-js-runner-badge clefa-js-runner-badge-neutral" data-clefa-js-results-badge>'

			+ escHtml( i18n.runningTests || 'Running…' )

			+ '</span></div>'

			+ '<div class="clefa-js-runner-results-meta" data-clefa-js-results-meta></div>'

			+ '<div class="clefa-dev-table-wrap clefa-js-runner-table-wrap">'

			+ '<table class="clefa-dev-results-table clefa-js-full-results-table">'

			+ '<thead><tr>'

			+ '<th>#</th>'

			+ '<th>' + escHtml( i18n.colFile || 'File' ) + '</th>'

			+ '<th>' + escHtml( i18n.colSuite || 'Suite' ) + '</th>'

			+ '<th>' + escHtml( i18n.colTest || 'Test' ) + '</th>'

			+ '<th>' + escHtml( i18n.colResult || 'Result' ) + '</th>'

			+ '<th>' + escHtml( i18n.colIssue || 'Issue' ) + '</th>'

			+ '</tr></thead><tbody data-clefa-js-results-tbody></tbody></table></div>';

		resultsTableBody = container.querySelector( '[data-clefa-js-results-tbody]' );

	}



	function appendResultRow( row ) {

		if ( ! resultsTableBody ) {

			initResultsTable();

		}

		if ( ! resultsTableBody ) {

			return;

		}

		resultRowCount++;

		var tr = document.createElement( 'tr' );

		tr.className = row.status === 'FAIL' ? 'clefa-row-fail' : 'clefa-row-pass';

		tr.innerHTML = '<td>' + resultRowCount + '</td>'

			+ '<td><code>' + escHtml( row.file || '—' ) + '</code></td>'

			+ '<td><code>' + escHtml( row.suite || '' ) + '</code></td>'

			+ '<td><code>' + escHtml( row.test || '' ) + '</code></td>'

			+ '<td><strong>' + escHtml( row.status ) + '</strong></td>'

			+ '<td class="clefa-js-issue-cell">' + ( row.issue ? escHtml( row.issue ) : '—' ) + '</td>';

		resultsTableBody.appendChild( tr );

	}



	function setResultsMeta( text ) {

		var meta = document.querySelector( '[data-clefa-js-results-meta]' );

		if ( meta ) {

			meta.textContent = text;

		}

	}



	function setResultsBadge( text, ok ) {

		var badge = document.querySelector( '[data-clefa-js-results-badge]' );

		if ( ! badge ) {

			return;

		}

		badge.textContent = text;

		badge.className = 'clefa-js-runner-badge '

			+ ( ok === true ? 'clefa-js-runner-badge-pass' : ( ok === false ? 'clefa-js-runner-badge-fail' : 'clefa-js-runner-badge-neutral' ) );

	}



	async function loadScriptUrl( url ) {

		var res = await fetchWithTimeout( url, { credentials: 'same-origin' } );

		if ( ! res || ! res.ok ) {

			throw new Error( 'Failed to load (' + ( res ? res.status : 'no response' ) + '): ' + scriptLabel( url ) );

		}

		var code = await res.text();

		if ( code.indexOf( '<' ) === 0 && code.indexOf( 'function' ) === -1 ) {

			throw new Error( 'Script returned HTML instead of JS: ' + scriptLabel( url ) );

		}

		injectScript( code );

	}



	async function ensureHarness() {

		if ( harnessReady ) {

			return getHarness();

		}

		await loadScriptUrl( window.clefaJsSuiteData.harnessUrl );

		getHarness().applySetup();

		harnessReady = true;

		return getHarness();

	}



	function setPhase( state ) {

		var el = document.querySelector( '[data-clefa-js-test-phase="run"]' );

		if ( el ) {

			el.setAttribute( 'data-clefa-state', state );

		}

	}



	function setSummary( text, ok ) {

		var summary = document.querySelector( '[data-clefa-js-test-summary]' );

		if ( summary ) {

			summary.textContent = text;

			if ( ok === 'idle' ) {

				summary.setAttribute( 'data-clefa-status', 'idle' );

			} else {

				summary.setAttribute( 'data-clefa-status', ok ? 'pass' : ( ok === false ? 'fail' : 'running' ) );

			}

		}

	}



	function resetRunnerUi() {

		var log = document.querySelector( '[data-clefa-js-test-live-list]' );

		if ( log ) {

			log.innerHTML = '';

		}

		var mount = document.getElementById( 'clefa-test-mount' );

		if ( mount ) {

			mount.innerHTML = '';

		}

		initResultsTable();

		setResultsMeta( '' );

		setResultsBadge( i18n.runningTests || 'Running…', null );

		setSummary( i18n.preparing || 'Preparing…', 'running' );

		setPhase( 'pending' );

	}



	function logStep( message ) {

		var list = document.querySelector( '[data-clefa-js-test-live-list]' );

		if ( ! list ) {

			return;

		}

		var item = document.createElement( 'div' );

		item.className = 'clefa-js-test-live-row clefa-js-test-live-info';

		item.textContent = message;

		list.appendChild( item );

	}



	function notifyParent( report ) {

		if ( ! window.parent || window.parent === window ) {

			return;

		}

		try {

			window.parent.postMessage( {

				type: 'clefa-js-test-complete',

				report: report,

			}, window.location.origin );

		} catch ( e ) { /* ignore */ }

	}



	async function runJsSuite() {

		var btn = document.querySelector( '[data-clefa-run-js-tests]' );



		resetRunnerUi();

		await yieldToUi();



		if ( btn ) {

			btn.disabled = true;

		}



		try {

			setPhase( 'running' );

			logStep( i18n.preparing || 'Preparing…' );



			var harness = await ensureHarness();

			harness.reset();

			harness.clearTestDom();



			var modules = window.clefaJsSuiteData.modules || [];

			logStep( ( i18n.loadingModules || 'Loading modules…' ) + ' (' + modules.length + ')' );

			for ( var m = 0; m < modules.length; m++ ) {

				setSummary( ( i18n.loadingModules || 'Loading modules…' ) + ' ' + ( m + 1 ) + '/' + modules.length, 'running' );

				await loadScriptUrl( modules[ m ] );

				await yieldToUi();

			}



			var bundleUrl = window.clefaJsSuiteData.testBundleUrl;

			if ( ! bundleUrl ) {

				throw new Error( 'Test bundle URL not configured' );

			}



			setSummary( i18n.loadingTests || 'Loading test bundle…', 'running' );

			logStep( i18n.loadingTests || 'Loading test bundle…' );

			await loadScriptUrl( bundleUrl );



			var registered = harness.countRegisteredTests();

			if ( registered === 0 ) {
				throw new Error( 'Test bundle registered 0 tests — check browser console for script errors.' );
			}



			setSummary( ( i18n.runningTests || 'Running tests…' ) + ' (' + registered + ')', 'running' );

			setResultsMeta( registered + ' tests registered — executing…' );

			logStep( registered + ' tests registered — running…' );



			var report = await harness.run( {

				onResult: function ( row ) {

					appendResultRow( row );

					setResultsMeta(

						reportPartialSummary()

					);

				},

			} );



			function reportPartialSummary() {

				var rows = resultsTableBody ? resultsTableBody.querySelectorAll( 'tr' ).length : 0;

				return rows + ' / ' + registered + ' executed';

			}



			var summaryText = report.summary.passed + ' PASS · ' + report.summary.failed + ' FAIL · ' + report.summary.total + ' TOTAL';

			setSummary( summaryText, report.summary.perfect );

			setResultsMeta( summaryText );

			setResultsBadge(

				report.summary.perfect ? ( i18n.perfect || 'ALL PASS' ) : ( i18n.fail || 'FAILURES' ),

				report.summary.perfect

			);

			setPhase( report.summary.failed ? 'fail' : 'pass' );

			notifyParent( report );

		} catch ( err ) {

			var msg = ( err && err.message ) ? err.message : String( err );

			setPhase( 'fail' );

			setSummary( msg, false );

			setResultsBadge( i18n.fail || 'ERROR', false );

			setResultsMeta( msg );

			logStep( 'ERROR: ' + msg );

		} finally {

			if ( btn ) {

				btn.disabled = false;

			}

		}

	}



	document.addEventListener( 'click', function ( e ) {

		if ( e.target.closest( '[data-clefa-run-js-tests]' ) ) {

			e.preventDefault();

			runJsSuite();

		}

	} );



	initScopeText();

}() );


