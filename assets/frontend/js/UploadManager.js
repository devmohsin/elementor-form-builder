/**
 * CLEFA UploadManager
 * Handles file inputs with drag-drop, instant upload to REST, and temp-id tracking.
 */
( function () {
	'use strict';

	window.CLEFA = window.CLEFA || {};

	var restUrl    = ( window.clefaFrontend && window.clefaFrontend.restUrl ) || '';
	var restNonce  = ( window.clefaFrontend && window.clefaFrontend.nonce )   || '';

	function formatSize( bytes ) {
		if ( bytes < 1024 )       return bytes + ' B';
		if ( bytes < 1024 * 1024 ) return ( bytes / 1024 ).toFixed( 1 ) + ' KB';
		return ( bytes / ( 1024 * 1024 ) ).toFixed( 1 ) + ' MB';
	}

	function getExt( name ) {
		var parts = name.split( '.' );
		return parts.length > 1 ? parts.pop().toLowerCase() : '';
	}

	function isImage( mime ) {
		return mime && mime.indexOf( 'image/' ) === 0;
	}

	/* ------------------------------------------------------------------ */
	/* FileUpload — manages one file input                                   */
	/* ------------------------------------------------------------------ */

	function FileUpload( wrapEl ) {
		this.wrapEl    = wrapEl;
		this.fieldId   = wrapEl.getAttribute( 'data-clefa-field-id' ) || '';
		this.formEl    = wrapEl.closest( '[data-clefa-form]' );
		this.input     = wrapEl.querySelector( 'input[type="file"]' );
		this.dropZone  = wrapEl.querySelector( '[data-clefa-drop-zone]' );
		this.list      = wrapEl.querySelector( '[data-clefa-file-list]' );
		this.maxSize   = parseFloat( wrapEl.getAttribute( 'data-clefa-max-size' ) || '10' ) * 1024 * 1024;
		this.multiple  = this.input ? this.input.multiple : false;
		this.uploads   = {};
		this._bindEvents();
	}

	FileUpload.prototype._bindEvents = function () {
		var self = this;
		if ( ! self.input ) return;

		// Click on drop zone opens file picker
		if ( self.dropZone ) {
			self.dropZone.addEventListener( 'click', function () { self.input.click(); } );

			self.dropZone.addEventListener( 'dragover', function ( e ) {
				e.preventDefault();
				self.dropZone.setAttribute( 'data-clefa-dragover', '1' );
			} );
			self.dropZone.addEventListener( 'dragleave', function () {
				self.dropZone.removeAttribute( 'data-clefa-dragover' );
			} );
			self.dropZone.addEventListener( 'drop', function ( e ) {
				e.preventDefault();
				self.dropZone.removeAttribute( 'data-clefa-dragover' );
				if ( e.dataTransfer && e.dataTransfer.files.length ) {
					self._processFiles( e.dataTransfer.files );
				}
			} );
		}

		self.input.addEventListener( 'change', function () {
			if ( self.input.files && self.input.files.length ) {
				self._processFiles( self.input.files );
				self.input.value = '';
			}
		} );
	};

	FileUpload.prototype._processFiles = function ( files ) {
		var self = this;
		if ( ! self.multiple ) {
			// Remove all existing uploads first
			Object.keys( self.uploads ).forEach( function ( k ) { self._removeUpload( k ); } );
		}
		for ( var i = 0; i < files.length; i++ ) {
			self._uploadFile( files[ i ] );
		}
	};

	FileUpload.prototype._uploadFile = function ( file ) {
		var self   = this;
		var fileId = 'f_' + Date.now() + '_' + Math.random().toString( 36 ).slice( 2 );

		if ( file.size > self.maxSize ) {
			self._showItemError( fileId, file.name, 'File exceeds maximum size of ' + formatSize( self.maxSize ) );
			return;
		}

		var formEl    = self.formEl;
		var formId    = formEl ? parseInt( formEl.getAttribute( 'data-clefa-form-id' ), 10 ) : 0;
		var instanceId= formEl ? formEl.getAttribute( 'data-clefa-instance' ) : '';

		var itemEl = self._createListItem( fileId, file.name, file.size );
		self.list.appendChild( itemEl );

		var fd = new FormData();
		fd.append( 'file',        file );
		fd.append( 'form_id',     formId );
		fd.append( 'field_id',    self.fieldId );
		fd.append( 'instance_id', instanceId );

		var xhr = new XMLHttpRequest();
		xhr.open( 'POST', restUrl + '/upload' );
		xhr.setRequestHeader( 'X-WP-Nonce', restNonce );

		self.wrapEl.dispatchEvent( new CustomEvent( 'clefa:upload:started', {
			bubbles: true, detail: { fileId: fileId, fileName: file.name, fieldId: self.fieldId }
		} ) );

		xhr.upload.addEventListener( 'progress', function ( e ) {
			if ( e.lengthComputable ) {
				var pct = Math.round( ( e.loaded / e.total ) * 100 );
				var bar = itemEl.querySelector( '[data-clefa-upload-bar]' );
				if ( bar ) bar.style.width = pct + '%';
				self.wrapEl.dispatchEvent( new CustomEvent( 'clefa:upload:progress', {
					bubbles: true, detail: { fileId: fileId, percent: pct, fieldId: self.fieldId }
				} ) );
			}
		} );

		xhr.addEventListener( 'load', function () {
			var progressWrap = itemEl.querySelector( '[data-clefa-upload-progress]' );
			if ( progressWrap ) progressWrap.style.display = 'none';

			var data = {};
			try { data = JSON.parse( xhr.responseText ); } catch ( e ) {}

			if ( xhr.status === 200 && data.success ) {
				self.uploads[ fileId ] = data;
				itemEl.setAttribute( 'data-clefa-upload-id', data.temp_id );

				if ( isImage( data.mime_type ) ) {
					var thumb = itemEl.querySelector( '[data-clefa-thumb]' );
					if ( thumb ) {
						thumb.src   = data.file_url;
						thumb.style.display = '';
					}
				}

				self._syncHiddenInputs();
				self.wrapEl.dispatchEvent( new CustomEvent( 'clefa:upload:complete', {
					bubbles: true, detail: { fileId: fileId, data: data }
				} ) );
			} else {
				var msg = ( data && data.message ) || 'Upload failed.';
				self._markItemError( itemEl, msg );
				self.wrapEl.dispatchEvent( new CustomEvent( 'clefa:upload:failed', {
					bubbles: true, detail: { fileId: fileId, message: msg, fieldId: self.fieldId }
				} ) );
			}
		} );

		xhr.addEventListener( 'error', function () {
			self._markItemError( itemEl, 'Network error during upload.' );
			self.wrapEl.dispatchEvent( new CustomEvent( 'clefa:upload:failed', {
				bubbles: true, detail: { fileId: fileId, message: 'Network error during upload.', fieldId: self.fieldId }
			} ) );
		} );

		xhr.send( fd );
	};

	FileUpload.prototype._createListItem = function ( fileId, fileName, fileSize ) {
		var li = document.createElement( 'li' );
		li.className = 'clefa-file-item';
		li.setAttribute( 'data-clefa-file-item', fileId );

		var ext = getExt( fileName );
		li.innerHTML =
			'<div class="clefa-file-thumb-wrap">' +
				'<img src="" class="clefa-file-thumb" data-clefa-thumb style="display:none" alt="">' +
				'<span class="clefa-file-ext" aria-hidden="true">' + ext.toUpperCase() + '</span>' +
			'</div>' +
			'<div class="clefa-file-info">' +
				'<span class="clefa-file-name">' + this._esc( fileName ) + '</span>' +
				'<span class="clefa-file-size">' + formatSize( fileSize ) + '</span>' +
				'<div class="clefa-upload-progress" data-clefa-upload-progress>' +
					'<div class="clefa-upload-bar" data-clefa-upload-bar style="width:0%"></div>' +
				'</div>' +
			'</div>' +
			'<button type="button" class="clefa-file-remove" data-clefa-remove-file="' + fileId + '" aria-label="Remove file">&times;</button>';

		var self = this;
		li.querySelector( '[data-clefa-remove-file]' ).addEventListener( 'click', function () {
			self._removeUpload( fileId );
		} );

		return li;
	};

	FileUpload.prototype._removeUpload = function ( fileId ) {
		var self  = this;
		var itemEl = self.list.querySelector( '[data-clefa-file-item="' + fileId + '"]' );
		if ( itemEl ) itemEl.remove();

		if ( self.uploads[ fileId ] ) {
			var tempId = self.uploads[ fileId ].temp_id;
			if ( tempId ) {
				fetch( restUrl + '/upload/' + tempId, {
					method:  'DELETE',
					headers: { 'X-WP-Nonce': restNonce },
				} ).catch( function () {} );
			}
			delete self.uploads[ fileId ];
		}

		self._syncHiddenInputs();
	};

	FileUpload.prototype._syncHiddenInputs = function () {
		var self = this;
		// Remove existing hidden inputs for this field
		var existing = self.wrapEl.querySelectorAll( 'input[type="hidden"][data-clefa-upload-value]' );
		existing.forEach( function ( el ) { el.remove(); } );

		// Add one per uploaded file
		var ids = Object.keys( self.uploads );
		ids.forEach( function ( fileId ) {
			var data  = self.uploads[ fileId ];
			if ( ! data || ! data.temp_id ) return;
			var inp   = document.createElement( 'input' );
			inp.type  = 'hidden';
			inp.name  = self.multiple
				? 'clefa_field[' + self.fieldId + '][]'
				: 'clefa_field[' + self.fieldId + ']';
			inp.value = data.temp_id;
			inp.setAttribute( 'data-clefa-input', '' );
			inp.setAttribute( 'data-clefa-field-id', self.fieldId );
			inp.setAttribute( 'data-clefa-upload-value', fileId );
			self.wrapEl.appendChild( inp );
		} );
	};

	FileUpload.prototype._showItemError = function ( fileId, fileName, msg ) {
		var li       = document.createElement( 'li' );
		li.className = 'clefa-file-item clefa-file-item-error';
		li.innerHTML = '<span class="clefa-file-name">' + this._esc( fileName ) + '</span>' +
			'<span class="clefa-file-error-msg">' + this._esc( msg ) + '</span>';
		this.list.appendChild( li );
		setTimeout( function () { li.remove(); }, 5000 );
	};

	FileUpload.prototype._markItemError = function ( itemEl, msg ) {
		itemEl.classList.add( 'clefa-file-item-error' );
		var progressWrap = itemEl.querySelector( '[data-clefa-upload-progress]' );
		if ( progressWrap ) progressWrap.style.display = 'none';
		var info = itemEl.querySelector( '.clefa-file-info' );
		if ( info ) {
			var errSpan = document.createElement( 'span' );
			errSpan.className   = 'clefa-file-error-msg';
			errSpan.textContent = msg;
			info.appendChild( errSpan );
		}
	};

	FileUpload.prototype._esc = function ( str ) {
		return String( str )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' );
	};

	/* ------------------------------------------------------------------ */
	/* Auto-init                                                             */
	/* ------------------------------------------------------------------ */

	function initAll( root ) {
		var base = root || document;
		base.querySelectorAll( '[data-clefa-file-wrap]' ).forEach( function ( el ) {
			if ( ! el._cleaUploadManager ) {
				el._cleaUploadManager = new FileUpload( el );
			}
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', function () { initAll(); } );
	} else {
		initAll();
	}

	window.CLEFA.UploadManager = { FileUpload: FileUpload, initAll: initAll };

} () );
