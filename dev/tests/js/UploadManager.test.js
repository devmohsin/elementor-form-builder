/** UploadManager — exhaustive mocked unit tests. */
const { describe, test, expect, beforeEach, afterEach } = require( '@jest/globals' );

require( '../../../assets/frontend/js/EventDispatcher.js' );
require( '../../../assets/frontend/js/UploadManager.js' );

function buildUploadWrap( opts ) {
	opts = opts || {};
	const maxMb = opts.maxMb != null ? opts.maxMb : 10;
	const multiple = opts.multiple ? ' multiple' : '';
	document.body.innerHTML = [
		'<form data-clefa-form data-clefa-form-id="5" data-clefa-instance="inst-1">',
		'  <div data-clefa-file-wrap data-clefa-field-id="' + ( opts.fieldId || 'resume' ) + '" data-clefa-max-size="' + maxMb + '">',
		'    <div data-clefa-drop-zone></div>',
		'    <input type="file"' + multiple + ' />',
		'    <ul data-clefa-file-list></ul>',
		'  </div>',
		'</form>',
	].join( '' );
	return document.querySelector( '[data-clefa-file-wrap]' );
}

function mockXhr( response, status ) {
	const instances = [];
	global.XMLHttpRequest = jest.fn( function MockXHR() {
		const xhr = {
			open: jest.fn(),
			setRequestHeader: jest.fn(),
			send: jest.fn( function () {
				setTimeout( () => {
					xhr.status = status != null ? status : 200;
					xhr.responseText = JSON.stringify( response );
					if ( xhr.onload ) xhr.onload();
				}, 0 );
			} ),
			upload: { addEventListener: jest.fn() },
			addEventListener: jest.fn( function ( ev, fn ) {
				if ( ev === 'load' ) xhr.onload = fn;
				if ( ev === 'error' ) xhr.onerror = fn;
			} ),
		};
		instances.push( xhr );
		return xhr;
	} );
	return instances;
}

describe( 'UploadManager.FileUpload basics', () => {
	beforeEach( () => {
		document.body.innerHTML = '';
		global.fetch = jest.fn();
		window.clefaFrontend = { restUrl: 'http://example.com/wp-json/clefa/v1', nonce: 'nonce-1' };
	} );

	test( 'constructor reads field id and max size from wrap', () => {
		const upload = new window.CLEFA.UploadManager.FileUpload( buildUploadWrap( { maxMb: 2 } ) );
		expect( upload.fieldId ).toBe( 'resume' );
		expect( upload.maxSize ).toBe( 2 * 1024 * 1024 );
	} );

	test( 'defaults max size to 10 MB when attribute missing', () => {
		const wrap = buildUploadWrap( { maxMb: 10 } );
		wrap.removeAttribute( 'data-clefa-max-size' );
		const upload = new window.CLEFA.UploadManager.FileUpload( wrap );
		expect( upload.maxSize ).toBe( 10 * 1024 * 1024 );
	} );

	test( 'multiple flag reads from file input', () => {
		const upload = new window.CLEFA.UploadManager.FileUpload( buildUploadWrap( { multiple: true } ) );
		expect( upload.multiple ).toBe( true );
	} );

	test( 'oversized file shows error item without uploading', () => {
		const upload = new window.CLEFA.UploadManager.FileUpload( buildUploadWrap( { maxMb: 1 } ) );
		const bigFile = new File( [ 'x'.repeat( 1024 * 1024 + 100 ) ], 'big.pdf', { type: 'application/pdf' } );
		upload._processFiles( [ bigFile ] );
		expect( upload.wrapEl.querySelector( '[data-clefa-file-list]' ).textContent ).toMatch( /maximum size/i );
		expect( global.fetch ).not.toHaveBeenCalled();
	} );

	test( '_esc escapes HTML in file names', () => {
		const upload = new window.CLEFA.UploadManager.FileUpload( buildUploadWrap() );
		expect( upload._esc( '<script>"&' ) ).toBe( '&lt;script&gt;&quot;&amp;' );
	} );
} );

describe( 'UploadManager.FileUpload events', () => {
	beforeEach( () => {
		document.body.innerHTML = '';
		global.fetch = jest.fn();
		window.clefaFrontend = { restUrl: 'http://example.com/wp-json/clefa/v1', nonce: 'nonce-1' };
	} );

	test( 'drop zone click triggers file input click', () => {
		const wrap = buildUploadWrap();
		const upload = new window.CLEFA.UploadManager.FileUpload( wrap );
		upload.input.click = jest.fn();
		wrap.querySelector( '[data-clefa-drop-zone]' ).click();
		expect( upload.input.click ).toHaveBeenCalled();
	} );

	test( 'dragover sets dragover attribute', () => {
		const wrap = buildUploadWrap();
		new window.CLEFA.UploadManager.FileUpload( wrap );
		const zone = wrap.querySelector( '[data-clefa-drop-zone]' );
		zone.dispatchEvent( Object.assign( new Event( 'dragover', { bubbles: true } ), { preventDefault: jest.fn() } ) );
		expect( zone.hasAttribute( 'data-clefa-dragover' ) ).toBe( true );
	} );

	test( 'dragleave removes dragover attribute', () => {
		const wrap = buildUploadWrap();
		new window.CLEFA.UploadManager.FileUpload( wrap );
		const zone = wrap.querySelector( '[data-clefa-drop-zone]' );
		zone.setAttribute( 'data-clefa-dragover', '1' );
		zone.dispatchEvent( new Event( 'dragleave', { bubbles: true } ) );
		expect( zone.hasAttribute( 'data-clefa-dragover' ) ).toBe( false );
	} );

	test( 'drop processes transferred files', () => {
		const wrap = buildUploadWrap( { maxMb: 1 } );
		const upload = new window.CLEFA.UploadManager.FileUpload( wrap );
		upload._processFiles = jest.fn();
		const zone = wrap.querySelector( '[data-clefa-drop-zone]' );
		const file = new File( [ 'a' ], 'a.txt', { type: 'text/plain' } );
		zone.dispatchEvent( Object.assign( new Event( 'drop', { bubbles: true } ), {
			preventDefault: jest.fn(),
			dataTransfer: { files: [ file ] },
		} ) );
		expect( upload._processFiles ).toHaveBeenCalledWith( [ file ] );
	} );

	test( 'input change clears value after processing', () => {
		const wrap = buildUploadWrap( { maxMb: 1 } );
		const upload = new window.CLEFA.UploadManager.FileUpload( wrap );
		upload._processFiles = jest.fn();
		const file = new File( [ 'a' ], 'a.txt', { type: 'text/plain' } );
		Object.defineProperty( upload.input, 'files', { value: [ file ] } );
		upload.input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		expect( upload._processFiles ).toHaveBeenCalled();
		expect( upload.input.value ).toBe( '' );
	} );
} );

describe( 'UploadManager.FileUpload upload pipeline', () => {
	beforeEach( () => {
		document.body.innerHTML = '';
		global.fetch = jest.fn().mockResolvedValue( { ok: true } );
		window.clefaFrontend = { restUrl: 'http://example.com/wp-json/clefa/v1', nonce: 'nonce-1' };
	} );

	test( 'successful upload creates list item and hidden input', async () => {
		mockXhr( { success: true, temp_id: 'tmp-1', mime_type: 'text/plain', file_url: '' } );
		const upload = new window.CLEFA.UploadManager.FileUpload( buildUploadWrap( { maxMb: 5 } ) );
		const file = new File( [ 'hello' ], 'doc.txt', { type: 'text/plain' } );
		upload._uploadFile( file );
		await new Promise( ( r ) => setTimeout( r, 5 ) );
		expect( upload.wrapEl.querySelector( '[data-clefa-upload-value]' ) ).toBeTruthy();
		expect( Object.keys( upload.uploads ).length ).toBeGreaterThan( 0 );
	} );

	test( 'failed upload marks item error', async () => {
		mockXhr( { success: false, message: 'Rejected' }, 400 );
		const upload = new window.CLEFA.UploadManager.FileUpload( buildUploadWrap( { maxMb: 5 } ) );
		const file = new File( [ 'x' ], 'bad.exe', { type: 'application/octet-stream' } );
		upload._uploadFile( file );
		await new Promise( ( r ) => setTimeout( r, 5 ) );
		expect( upload.wrapEl.querySelector( '.clefa-file-item-error' ) ).toBeTruthy();
	} );

	test( 'network error marks item error', async () => {
		const instances = mockXhr( {}, 200 );
		const upload = new window.CLEFA.UploadManager.FileUpload( buildUploadWrap( { maxMb: 5 } ) );
		const file = new File( [ 'x' ], 'a.txt', { type: 'text/plain' } );
		upload._uploadFile( file );
		await new Promise( ( r ) => setTimeout( r, 0 ) );
		instances[ 0 ].onerror();
		expect( upload.wrapEl.querySelector( '.clefa-file-item-error' ) ).toBeTruthy();
	} );

	test( 'clefa:upload:started fires before xhr send', () => {
		mockXhr( { success: true, temp_id: 't1', mime_type: 'text/plain' } );
		const wrap = buildUploadWrap( { maxMb: 5 } );
		const upload = new window.CLEFA.UploadManager.FileUpload( wrap );
		const spy = jest.fn();
		wrap.addEventListener( 'clefa:upload:started', spy );
		upload._uploadFile( new File( [ 'a' ], 'a.txt', { type: 'text/plain' } ) );
		expect( spy ).toHaveBeenCalled();
	} );

	test( 'image upload shows thumbnail on success', async () => {
		mockXhr( { success: true, temp_id: 'img-1', mime_type: 'image/png', file_url: 'http://x/p.png' } );
		const upload = new window.CLEFA.UploadManager.FileUpload( buildUploadWrap( { maxMb: 5 } ) );
		upload._uploadFile( new File( [ 'png' ], 'p.png', { type: 'image/png' } ) );
		await new Promise( ( r ) => setTimeout( r, 5 ) );
		const thumb = upload.wrapEl.querySelector( '[data-clefa-thumb]' );
		expect( thumb.src ).toContain( 'p.png' );
		expect( thumb.style.display ).not.toBe( 'none' );
	} );

	test( '_removeUpload deletes temp file via fetch', async () => {
		mockXhr( { success: true, temp_id: 'del-1', mime_type: 'text/plain' } );
		const upload = new window.CLEFA.UploadManager.FileUpload( buildUploadWrap( { maxMb: 5 } ) );
		upload._uploadFile( new File( [ 'a' ], 'a.txt', { type: 'text/plain' } ) );
		await new Promise( ( r ) => setTimeout( r, 5 ) );
		const fileId = Object.keys( upload.uploads )[ 0 ];
		upload._removeUpload( fileId );
		expect( fetch ).toHaveBeenCalledWith(
			expect.stringContaining( '/upload/del-1' ),
			expect.objectContaining( { method: 'DELETE' } )
		);
	} );

	test( 'single mode clears previous uploads before new file', () => {
		const upload = new window.CLEFA.UploadManager.FileUpload( buildUploadWrap( { maxMb: 5 } ) );
		upload._removeUpload = jest.fn();
		upload.uploads = { old: { temp_id: '1' } };
		upload._processFiles( [ new File( [ 'b' ], 'b.txt', { type: 'text/plain' } ) ] );
		expect( upload._removeUpload ).toHaveBeenCalledWith( 'old' );
	} );

	test( 'remove button click removes list item', async () => {
		mockXhr( { success: true, temp_id: 'r1', mime_type: 'text/plain' } );
		const upload = new window.CLEFA.UploadManager.FileUpload( buildUploadWrap( { maxMb: 5 } ) );
		upload._uploadFile( new File( [ 'a' ], 'a.txt', { type: 'text/plain' } ) );
		await new Promise( ( r ) => setTimeout( r, 5 ) );
		const btn = upload.wrapEl.querySelector( '[data-clefa-remove-file]' );
		btn.click();
		expect( upload.wrapEl.querySelector( '[data-clefa-file-item]' ) ).toBeNull();
	} );
} );

describe( 'UploadManager.initAll', () => {
	beforeEach( () => {
		document.body.innerHTML = '';
		global.fetch = jest.fn();
	} );

	test( 'initAll attaches FileUpload to each wrap', () => {
		buildUploadWrap( { maxMb: 10 } );
		window.CLEFA.UploadManager.initAll();
		expect( document.querySelector( '[data-clefa-file-wrap]' )._cleaUploadManager ).toBeDefined();
	} );

	test( 'initAll does not re-init existing instances', () => {
		const wrap = buildUploadWrap( { maxMb: 10 } );
		window.CLEFA.UploadManager.initAll();
		const first = wrap._cleaUploadManager;
		window.CLEFA.UploadManager.initAll();
		expect( wrap._cleaUploadManager ).toBe( first );
	} );
} );
