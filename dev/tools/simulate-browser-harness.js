/**
 * Simulate browser test harness + transformed unit scripts (Node + jsdom).
 * Usage: node dev/tools/simulate-browser-harness.js
 */
const fs = require( 'fs' );
const path = require( 'path' );
const { JSDOM } = require( 'jsdom' );

const pluginRoot = path.resolve( __dirname, '../..' );
const dom = new JSDOM( '<!DOCTYPE html><html><body><div id="clefa-test-mount"></div></body></html>', {
	url: 'http://localhost/',
	runScripts: 'dangerously',
	resources: 'usable',
} );

const { window } = dom;
global.window = window;
global.document = window.document;
global.HTMLElement = window.HTMLElement;
global.CustomEvent = window.CustomEvent;
global.fetch = () => Promise.resolve( { ok: true, json: () => Promise.resolve( {} ) } );

function runFile( relPath ) {
	const code = fs.readFileSync( path.join( pluginRoot, relPath ), 'utf8' );
	window.eval( code );
}

runFile( 'assets/admin/js/clefa-test-harness.js' );

const modules = [
	'assets/frontend/js/TransitionEngine.js',
	'assets/frontend/js/EventDispatcher.js',
	'assets/frontend/js/ValidationEngine.js',
	'assets/frontend/js/ConditionEngine.js',
	'assets/frontend/js/StepRouter.js',
	'assets/frontend/js/FormEngine.js',
	'assets/frontend/js/FilterEngine.js',
	'assets/frontend/js/LiveCheckManager.js',
	'assets/frontend/js/UploadManager.js',
];
modules.forEach( ( m ) => runFile( m ) );

// Minimal PHP transform replica for test loading.
function transform( content ) {
	content = content.replace( /^const \{[^}]+\} = require\(\s*['"]@jest\/globals['"]\s*\);\s*\r?\n/m, '' );
	content = content.replace( /^const path = require\(\s*['"]path['"]\s*\);\s*\r?\n/m, '' );
	content = content.replace( /^const \{ makeForm[^}]+\} = require\(\s*['"]\.\/helpers\/dom['"]\s*\);\s*\r?\n/m, '' );
	content = content.replace( /^const \{ buildFilterWidget \} = require\(\s*['"]\.\/helpers\/filter-dom\.js['"]\s*\);\s*\r?\n/m, '' );
	content = content.replace( /^require\(\s*['"][^'"]*assets\/frontend\/js\/[^'"]+['"]\s*\);\s*\r?\n/gm, '' );
	content = content.replace( /beforeAll\(\s*\(\)\s*=>\s*\{\s*(?:require\([^;]+;\s*)+\}\s*\);\s*\r?\n/s, '' );
	content = content.replace( /document\.body\.innerHTML\s*=/g, 'document.getElementById("clefa-test-mount").innerHTML =' );
	const helpersDir = path.join( pluginRoot, 'dev/tests/js/helpers' );
	let helpers = '';
	[ 'dom.js', 'real-dom.js', 'filter-dom.js' ].forEach( ( f ) => {
		let h = fs.readFileSync( path.join( helpersDir, f ), 'utf8' );
		h = h.replace( /module\.exports\s*=\s*\{[^}]+\};\s*/, '' );
		h = h.replace( /window\.CLEFATest[\s\S]*$/, '' );
		helpers += h + '\n';
	} );
	const globals = [
		'var global = window;',
		'var describe = window.describe;',
		'var test = window.test;',
		'var it = window.it;',
		'var expect = window.expect;',
		'var beforeEach = window.beforeEach;',
		'var afterEach = window.afterEach;',
		'var beforeAll = window.beforeAll;',
		'var afterAll = window.afterAll;',
		'var jest = window.jest;',
	].join( '\n' );
	return `(function(){\n'use strict';\n${globals}\n${helpers}\nfunction clearTestDom(){var m=document.getElementById('clefa-test-mount');if(m){m.innerHTML='';}}\n${content}\n})();`;
}

const unitFiles = fs.readdirSync( path.join( pluginRoot, 'dev/tests/js' ) )
	.filter( ( f ) => f.endsWith( '.test.js' ) && f !== 'RealDom.integration.test.js' );

let total = 0;
unitFiles.forEach( ( file ) => {
	window.CLEFATest.reset();
	const raw = fs.readFileSync( path.join( pluginRoot, 'dev/tests/js', file ), 'utf8' );
	const code = transform( raw );
	try {
		window.eval( code );
	} catch ( err ) {
		console.error( 'FAIL load', file, err.message );
		return;
	}
	const count = window.CLEFATest.countRegisteredTests();
	total += count;
	console.log( file, 'registered=', count );
} );

console.log( 'TOTAL registered unit tests:', total );
