/* global clefaAdminData, jQuery */
(function($) {
	'use strict';

	/* -------------------------------------------------------
	   Template Picker Modal
	   ------------------------------------------------------- */

	var selectedTemplate = null;

	function openPicker() {
		var modal = document.getElementById('clefa-template-picker-modal');
		if ( ! modal ) { return; }
		modal.hidden = false;
		document.body.style.overflow = 'hidden';
		// Reset state
		selectedTemplate = null;
		document.getElementById('clefa-tpl-name-row').hidden = true;
		document.getElementById('clefa-tpl-name-input').value = '';
		document.querySelectorAll('.clefa-tpl-card').forEach(function(c) {
			c.classList.remove('clefa-tpl-card-selected');
		});
	}

	function closePicker() {
		var modal = document.getElementById('clefa-template-picker-modal');
		if ( modal ) { modal.hidden = true; }
		document.body.style.overflow = '';
	}

	document.addEventListener('DOMContentLoaded', function() {
		var openBtn      = document.getElementById('clefa-open-template-picker');
		var openBtnEmpty = document.getElementById('clefa-open-template-picker-empty');
		var closeBtn     = document.getElementById('clefa-tpl-close');
		var backdrop     = document.getElementById('clefa-tpl-backdrop');
		var createBtn    = document.getElementById('clefa-tpl-create-btn');
		var nameRow      = document.getElementById('clefa-tpl-name-row');
		var nameInput    = document.getElementById('clefa-tpl-name-input');

		if ( openBtn )      { openBtn.addEventListener('click', openPicker); }
		if ( openBtnEmpty ) { openBtnEmpty.addEventListener('click', openPicker); }
		if ( closeBtn )     { closeBtn.addEventListener('click', closePicker); }
		if ( backdrop )     { backdrop.addEventListener('click', closePicker); }

		// Esc key
		document.addEventListener('keydown', function(e) {
			if ( e.key === 'Escape' ) { closePicker(); }
		});

		// Template card selection
		document.querySelectorAll('.clefa-tpl-card').forEach(function(card) {
			card.addEventListener('click', function() {
				document.querySelectorAll('.clefa-tpl-card').forEach(function(c) {
					c.classList.remove('clefa-tpl-card-selected');
				});
				card.classList.add('clefa-tpl-card-selected');
				selectedTemplate = card.getAttribute('data-clefa-template');

				var label = card.querySelector('.clefa-tpl-label');
				if ( nameInput && label ) {
					nameInput.value = label.textContent.trim();
				}
				if ( nameRow ) { nameRow.hidden = false; }
				if ( nameInput ) { nameInput.focus(); nameInput.select(); }
			});
		});

		// Create button
		if ( createBtn ) {
			createBtn.addEventListener('click', function() {
				if ( ! selectedTemplate ) { return; }
				var name = nameInput ? nameInput.value.trim() : '';
				createBtn.disabled = true;
				createBtn.textContent = 'Creating…';

				fetch( clefaAdminData.restUrl + '/forms', {
					method:  'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce':   clefaAdminData.nonce,
					},
					body: JSON.stringify({
						form_name: name,
						template:  selectedTemplate,
					}),
				})
				.then(function(r) { return r.json(); })
				.then(function(data) {
					if ( data.success && data.form_id ) {
						var editUrl = clefaAdminData.adminUrl + 'admin.php?page=clefa-edit-form&form_id=' + data.form_id;
						window.location.href = editUrl;
					} else {
						alert( (data.message || data.data && data.data.message) || 'Could not create form.' );
						createBtn.disabled = false;
						createBtn.textContent = 'Create Form';
					}
				})
				.catch(function() {
					alert('Network error — please try again.');
					createBtn.disabled = false;
					createBtn.textContent = 'Create Form';
				});
			});
		}
	});

	/* -------------------------------------------------------
	   Existing: Delete + Duplicate
	   ------------------------------------------------------- */

	$(document).on('click', '[data-clefa-action="delete-form"]', function(e) {
		e.preventDefault();
		const btn    = $(this);
		const formId = btn.data('clefa-form-id');
		const card   = btn.closest('.clefa-form-card');

		if ( ! confirm( clefaAdminData.i18n.confirmDelete ) ) { return; }

		btn.text( clefaAdminData.i18n.deleting ).prop('disabled', true);

		$.post( clefaAdminData.ajaxUrl, {
			action:  'clefa_delete_form',
			form_id: formId,
			nonce:   clefaAdminData.nonce,
		}, function(response) {
			if ( response.success ) {
				card.fadeOut(300, function() { $(this).remove(); });
				if ( $('.clefa-form-card').length === 1 ) {
					setTimeout(() => location.reload(), 400);
				}
			} else {
				alert( response.data.message || 'Delete failed.' );
				btn.text('Delete').prop('disabled', false);
			}
		});
	});

	$(document).on('click', '[data-clefa-action="duplicate-form"]', function(e) {
		e.preventDefault();
		const btn    = $(this);
		const formId = btn.data('clefa-form-id');

		if ( ! confirm( clefaAdminData.i18n.confirmDuplicate ) ) { return; }

		btn.text( clefaAdminData.i18n.duplicating ).prop('disabled', true);

		$.post( clefaAdminData.ajaxUrl, {
			action:  'clefa_duplicate_form',
			form_id: formId,
			nonce:   clefaAdminData.nonce,
		}, function(response) {
			if ( response.success ) {
				window.location.href = response.data.edit_url;
			} else {
				alert( response.data.message || 'Duplicate failed.' );
				btn.text('Duplicate').prop('disabled', false);
			}
		});
	});

})(jQuery);
