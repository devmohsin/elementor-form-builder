/**
 * Minimal Jest-like test harness for in-browser JS unit tests (Dev Hub).
 */
( function () {
	'use strict';

	const spies = [];
	const mocks = [];
	let rootSuites = [];
	let currentSuite = null;
	let results = [];
	let fakeTimersActive = false;
	let fakeNow = 0;
	let timerId = 0;
	const timerQueue = [];
	const origSetTimeout = window.setTimeout.bind( window );
	const origClearTimeout = window.clearTimeout.bind( window );
	const origFetch = typeof window.fetch === 'function' ? window.fetch.bind( window ) : null;
	let fileBeforeEach = [];
	let fileAfterEach = [];
	let fileBeforeAll = [];
	let fileAfterAll = [];

	function createSuite( name ) {
		return {
			name,
			parent: null,
			tests: [],
			beforeAll: [],
			afterAll: [],
			beforeEach: [],
			afterEach: [],
			children: [],
		};
	}

	function getAncestors( suite ) {
		const chain = [];
		let node = suite;
		while ( node ) {
			chain.unshift( node );
			node = node.parent;
		}
		return chain;
	}

	function createMockFn( impl ) {
		const mock = function mockFn() {
			mock.mock.calls.push( Array.prototype.slice.call( arguments ) );
			mock.mock.instances.push( this );
			if ( mock._impl ) {
				return mock._impl.apply( this, arguments );
			}
			if ( Object.prototype.hasOwnProperty.call( mock, '_returnValue' ) ) {
				return mock._returnValue;
			}
			if ( Object.prototype.hasOwnProperty.call( mock, '_resolvedValue' ) ) {
				return Promise.resolve( mock._resolvedValue );
			}
			if ( Object.prototype.hasOwnProperty.call( mock, '_rejectedValue' ) ) {
				return Promise.reject( mock._rejectedValue );
			}
		};
		mock.mock = { calls: [], instances: [] };
		mock.mockReturnValue = function ( v ) { mock._returnValue = v; return mock; };
		mock.mockResolvedValue = function ( v ) { mock._resolvedValue = v; return mock; };
		mock.mockRejectedValue = function ( v ) { mock._rejectedValue = v; return mock; };
		mock.mockImplementation = function ( fn ) { mock._impl = fn; return mock; };
		if ( impl ) {
			mock.mockImplementation( impl );
		}
		mocks.push( mock );
		return mock;
	}

	const jestApi = {
		fn: createMockFn,
		spyOn: function ( obj, method ) {
			const original = obj[ method ];
			const spy = createMockFn( function () {
				return original.apply( obj, arguments );
			} );
			obj[ method ] = spy;
			spy.mockRestore = function () {
				obj[ method ] = original;
			};
			spies.push( { obj: obj, method: method, original: original, spy: spy } );
			return spy;
		},
		clearAllMocks: function () {
			mocks.forEach( function ( mock ) {
				mock.mock.calls = [];
				mock.mock.instances = [];
			} );
		},
		restoreAllMocks: function () {
			spies.slice().reverse().forEach( function ( entry ) {
				entry.obj[ entry.method ] = entry.original;
			} );
			spies.length = 0;
		},
		useFakeTimers: function () {
			fakeTimersActive = true;
			fakeNow = 0;
			timerId = 0;
			timerQueue.length = 0;
			window.setTimeout = function ( fn, delay ) {
				const id = ++timerId;
				timerQueue.push( { id: id, fn: fn, at: fakeNow + ( delay || 0 ) } );
				return id;
			};
			window.clearTimeout = function ( id ) {
				for ( let i = timerQueue.length - 1; i >= 0; i-- ) {
					if ( timerQueue[ i ].id === id ) {
						timerQueue.splice( i, 1 );
					}
				}
			};
		},
		useRealTimers: function () {
			fakeTimersActive = false;
			timerQueue.length = 0;
			window.setTimeout = origSetTimeout;
			window.clearTimeout = origClearTimeout;
		},
		advanceTimersByTime: function ( ms ) {
			if ( ! fakeTimersActive ) {
				return;
			}
			fakeNow += ms;
			const due = timerQueue.filter( function ( t ) { return t.at <= fakeNow; } );
			timerQueue.splice( 0, timerQueue.length, ...timerQueue.filter( function ( t ) { return t.at > fakeNow; } ) );
			due.sort( function ( a, b ) { return a.at - b.at; } );
			due.forEach( function ( t ) { t.fn(); } );
		},
	};

	function matcher( actual, negated ) {
		const m = {
			_not: negated,
			toBe: function ( expected ) {
				assert( actual === expected, 'Expected ' + stringify( actual ) + ' to be ' + stringify( expected ) );
			},
			toEqual: function ( expected ) {
				assert( deepEqual( actual, expected ), 'Expected ' + stringify( actual ) + ' to equal ' + stringify( expected ) );
			},
			toBeNull: function () {
				assert( actual === null, 'Expected ' + stringify( actual ) + ' to be null' );
			},
			toBeTruthy: function () {
				assert( !! actual, 'Expected value to be truthy' );
			},
			toBeFalsy: function () {
				assert( ! actual, 'Expected value to be falsy' );
			},
			toBeDefined: function () {
				assert( typeof actual !== 'undefined', 'Expected value to be defined' );
			},
			toBeUndefined: function () {
				assert( typeof actual === 'undefined', 'Expected value to be undefined but got ' + stringify( actual ) );
			},
			toBeGreaterThan: function ( expected ) {
				assert( actual > expected, 'Expected ' + stringify( actual ) + ' to be greater than ' + stringify( expected ) );
			},
			toBeGreaterThanOrEqual: function ( expected ) {
				assert( actual >= expected, 'Expected ' + stringify( actual ) + ' to be >= ' + stringify( expected ) );
			},
			toBeLessThan: function ( expected ) {
				assert( actual < expected, 'Expected ' + stringify( actual ) + ' to be less than ' + stringify( expected ) );
			},
			toBeLessThanOrEqual: function ( expected ) {
				assert( actual <= expected, 'Expected ' + stringify( actual ) + ' to be <= ' + stringify( expected ) );
			},
			toThrow: function ( expected ) {
				let threw = false;
				let errMsg = '';
				try {
					if ( typeof actual !== 'function' ) {
						throw new Error( 'Expected a function' );
					}
					actual();
				} catch ( err ) {
					threw = true;
					errMsg = err && err.message ? err.message : String( err );
				}
				if ( typeof expected === 'undefined' ) {
					assert( threw, 'Expected function to throw' );
					return;
				}
				const ok = expected instanceof RegExp
					? threw && expected.test( errMsg )
					: threw && errMsg.indexOf( String( expected ) ) !== -1;
				assert( ok, 'Expected function to throw matching ' + String( expected ) + ( threw ? ' but got: ' + errMsg : '' ) );
			},
			toContain: function ( expected ) {
				const ok = Array.isArray( actual )
					? actual.indexOf( expected ) !== -1
					: String( actual ).indexOf( expected ) !== -1;
				assert( ok, 'Expected ' + stringify( actual ) + ' to contain ' + stringify( expected ) );
			},
			toMatch: function ( pattern ) {
				const ok = pattern instanceof RegExp ? pattern.test( String( actual ) ) : String( actual ).indexOf( pattern ) !== -1;
				assert( ok, 'Expected ' + stringify( actual ) + ' to match ' + String( pattern ) );
			},
			toHaveLength: function ( len ) {
				assert( actual && actual.length === len, 'Expected length ' + len + ' but got ' + ( actual ? actual.length : 'none' ) );
			},
			toHaveProperty: function ( key ) {
				assert( actual && Object.prototype.hasOwnProperty.call( actual, key ), 'Expected property ' + key );
			},
			toHaveBeenCalled: function () {
				assert( actual && actual.mock && actual.mock.calls.length > 0, 'Expected mock to have been called' );
			},
			toHaveBeenCalledTimes: function ( n ) {
				assert( actual && actual.mock && actual.mock.calls.length === n, 'Expected ' + n + ' calls but got ' + ( actual && actual.mock ? actual.mock.calls.length : 0 ) );
			},
			toHaveBeenCalledWith: function () {
				const args = Array.prototype.slice.call( arguments );
				const found = actual && actual.mock && actual.mock.calls.some( function ( call ) {
					return deepEqual( call, args );
				} );
				assert( found, 'Expected mock to have been called with ' + stringify( args ) );
			},
		};
		if ( actual && typeof actual.then === 'function' ) {
			Object.defineProperty( m, 'resolves', {
				get: function () {
					return buildPromiseMatcher( actual, negated );
				},
				configurable: true,
			} );
		}
		Object.defineProperty( m, 'not', {
			get: function () {
				return matcher( actual, ! negated );
			},
			configurable: true,
		} );
		return m;

		function assert( ok, msg ) {
			if ( negated ? ok : ! ok ) {
				throw new Error( msg );
			}
		}
	}

	function buildPromiseMatcher( promise, negated ) {
		function check( value, ok, msg ) {
			if ( negated ? ok : ! ok ) {
				throw new Error( msg );
			}
		}

		function wrap( fn ) {
			return function () {
				const args = arguments;
				return promise.then( function ( value ) {
					fn.apply( null, [ value ].concat( Array.prototype.slice.call( args ) ) );
				} );
			};
		}

		return {
			toBeUndefined: wrap( function ( value ) {
				check( value, typeof value === 'undefined', 'Expected promise to resolve to undefined but got ' + stringify( value ) );
			} ),
			toBe: wrap( function ( value, expected ) {
				check( value, value === expected, 'Expected promise to resolve to ' + stringify( expected ) + ' but got ' + stringify( value ) );
			} ),
			toEqual: wrap( function ( value, expected ) {
				check( value, deepEqual( value, expected ), 'Expected promise to resolve to ' + stringify( expected ) + ' but got ' + stringify( value ) );
			} ),
		};
	}

	function countTestsInSuites( suites ) {
		let total = 0;
		suites.forEach( function ( suite ) {
			total += suite.tests.length;
			total += countTestsInSuites( suite.children );
		} );
		return total;
	}

	function stringify( v ) {
		try {
			return JSON.stringify( v );
		} catch ( e ) {
			return String( v );
		}
	}

	function deepEqual( a, b ) {
		if ( a === b ) {
			return true;
		}
		if ( a === null || b === null || typeof a !== 'object' || typeof b !== 'object' ) {
			return false;
		}
		if ( Array.isArray( a ) ) {
			if ( ! Array.isArray( b ) || a.length !== b.length ) {
				return false;
			}
			for ( let i = 0; i < a.length; i++ ) {
				if ( ! deepEqual( a[ i ], b[ i ] ) ) {
					return false;
				}
			}
			return true;
		}
		const keysA = Object.keys( a );
		const keysB = Object.keys( b );
		if ( keysA.length !== keysB.length ) {
			return false;
		}
		for ( let i = 0; i < keysA.length; i++ ) {
			const key = keysA[ i ];
			if ( ! deepEqual( a[ key ], b[ key ] ) ) {
				return false;
			}
		}
		return true;
	}

	function describe( name, fn ) {
		const suite = createSuite( name );
		suite.parent = currentSuite;
		if ( currentSuite ) {
			currentSuite.children.push( suite );
		} else {
			rootSuites.push( suite );
		}
		const prev = currentSuite;
		currentSuite = suite;
		try {
			fn();
		} catch ( err ) {
			throw new Error( 'Error registering suite "' + name + '": ' + ( err && err.message ? err.message : String( err ) ) );
		} finally {
			currentSuite = prev;
		}
	}

	function registerHook( list, fn, fileHooks ) {
		if ( ! fn || typeof fn !== 'function' ) {
			return;
		}
		if ( currentSuite ) {
			list.push( fn );
		} else {
			fileHooks.push( fn );
		}
	}

	function test( name, fn ) {
		if ( ! currentSuite ) {
			throw new Error( 'test() must be inside describe()' );
		}
		currentSuite.tests.push( {
			name: name,
			fn: fn,
			file: typeof window.__clefaCurrentTestFile === 'string' ? window.__clefaCurrentTestFile : '',
		} );
	}

	function clearTestDom() {
		var mount = document.getElementById( 'clefa-test-mount' );
		if ( mount ) {
			mount.innerHTML = '';
		}
	}

	function applySetup() {
		if ( origFetch ) {
			window.fetch = origFetch;
		}
		window.clefaFrontend = window.clefaFrontend || {
			restUrl: window.location.origin + '/wp-json/clefa/v1',
			nonce: 'test-nonce-123',
			refreshNonce: false,
			ajaxUrl: window.location.origin + '/wp-admin/admin-ajax.php',
			i18n: {},
		};
		window.CLEFA = window.CLEFA || {};
		if ( ! HTMLElement.prototype.scrollIntoView || ! HTMLElement.prototype.scrollIntoView.mock ) {
			HTMLElement.prototype.scrollIntoView = createMockFn();
		}
		window.CLEFA_TESTING = true;
		window.clefaBuilderData = window.clefaBuilderData || {
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
			restUrl: window.location.origin + '/wp-json/clefa/v1',
			nonce: 'test-nonce-builder',
		};
	}

	async function runHooks( hooks ) {
		for ( let i = 0; i < hooks.length; i++ ) {
			await hooks[ i ]();
		}
	}

	let runOptions = {};

	async function runSuite( suite, parentNames ) {
		const names = parentNames.concat( [ suite.name ] );
		const suiteLabel = names.join( ' › ' );

		await runHooks( suite.beforeAll );

		for ( let i = 0; i < suite.children.length; i++ ) {
			await runSuite( suite.children[ i ], names );
		}

		for ( let t = 0; t < suite.tests.length; t++ ) {
			const testCase = suite.tests[ t ];
			const ancestors = getAncestors( suite );
			const beforeEachHooks = fileBeforeEach.slice();
			const afterEachHooks = fileAfterEach.slice();
			ancestors.forEach( function ( s ) {
				beforeEachHooks.push.apply( beforeEachHooks, s.beforeEach );
				afterEachHooks.unshift.apply( afterEachHooks, s.afterEach );
			} );

			try {
				await runHooks( beforeEachHooks );
				await testCase.fn();
				const row = {
					file: testCase.file || '',
					suite: suiteLabel,
					test: testCase.name,
					full: suiteLabel + ' › ' + testCase.name,
					status: 'PASS',
					issue: '',
				};
				results.push( row );
				if ( runOptions.onResult ) {
					runOptions.onResult( row );
				}
			} catch ( err ) {
				const row = {
					file: testCase.file || '',
					suite: suiteLabel,
					test: testCase.name,
					full: suiteLabel + ' › ' + testCase.name,
					status: 'FAIL',
					issue: err && err.message ? err.message : String( err ),
				};
				results.push( row );
				if ( runOptions.onResult ) {
					runOptions.onResult( row );
				}
			} finally {
				await runHooks( afterEachHooks );
			}
		}

		await runHooks( suite.afterAll );
	}

	function buildReport() {
		const passed = results.filter( function ( r ) { return r.status === 'PASS'; } ).length;
		const failed = results.filter( function ( r ) { return r.status === 'FAIL'; } ).length;
		const issues = results.filter( function ( r ) { return r.status === 'FAIL'; } ).map( function ( r ) {
			return { type: 'test', source: r.full, detail: r.issue };
		} );
		return {
			summary: {
				total: results.length,
				passed: passed,
				failed: failed,
				perfect: failed === 0 && results.length > 0,
			},
			rows: results.map( function ( r, i ) {
				return {
					num: i + 1,
					file: r.file || '',
					suite: r.suite,
					test: r.test,
					full: r.full,
					status: r.status,
					issue: r.issue,
				};
			} ),
			issues: issues,
			output: results.map( function ( r ) {
				return ( r.status === 'PASS' ? 'PASS' : 'FAIL' ) + '  ' + r.full + ( r.issue ? '\n  ' + r.issue : '' );
			} ).join( '\n' ),
		};
	}

	window.CLEFATest = {
		describe: describe,
		test: test,
		it: test,
		expect: function ( actual ) { return matcher( actual, false ); },
		beforeEach: function ( fn ) { registerHook( currentSuite ? currentSuite.beforeEach : null, fn, fileBeforeEach ); },
		afterEach: function ( fn ) { registerHook( currentSuite ? currentSuite.afterEach : null, fn, fileAfterEach ); },
		beforeAll: function ( fn ) { registerHook( currentSuite ? currentSuite.beforeAll : null, fn, fileBeforeAll ); },
		afterAll: function ( fn ) { registerHook( currentSuite ? currentSuite.afterAll : null, fn, fileAfterAll ); },
		jest: jestApi,
		helpers: {},
		clearTestDom: clearTestDom,
		countRegisteredTests: function () {
			return countTestsInSuites( rootSuites );
		},
		reset: function () {
			rootSuites = [];
			currentSuite = null;
			results = [];
			fileBeforeEach = [];
			fileAfterEach = [];
			fileBeforeAll = [];
			fileAfterAll = [];
			mocks.length = 0;
			jestApi.restoreAllMocks();
			jestApi.useRealTimers();
			clearTestDom();
			try { localStorage.clear(); } catch ( e ) { /* ignore */ }
			applySetup();
		},
		run: async function ( options ) {
			runOptions = options || {};
			results = [];
			await runHooks( fileBeforeAll );
			for ( let i = 0; i < rootSuites.length; i++ ) {
				await runSuite( rootSuites[ i ], [] );
			}
			await runHooks( fileAfterAll );
			const report = buildReport();
			runOptions = {};
			return report;
		},
		applySetup: applySetup,
	};

	applySetup();

	// Expose globals for transformed test scripts.
	window.describe = describe;
	window.test = test;
	window.it = test;
	window.expect = window.CLEFATest.expect;
	window.beforeEach = window.CLEFATest.beforeEach;
	window.afterEach = window.CLEFATest.afterEach;
	window.beforeAll = window.CLEFATest.beforeAll;
	window.afterAll = window.CLEFATest.afterAll;
	window.jest = jestApi;
}() );
