/* global clefaBuilderData */
/**
 * CodeLinden Form Addon — Admin Builder JS
 *
 * Manages the drag-drop form builder state, rendering, and REST API save/load.
 * All DOM interactions use data-clefa-* attributes as hooks (no class-based JS hooks).
 * No !important in any injected styles.
 */
(function() {
	'use strict';

	if ( typeof clefaBuilderData === 'undefined' ) { return; }

	/* ---- State ---- */
	const cfg = clefaBuilderData.formConfig && ! Array.isArray( clefaBuilderData.formConfig )
		? clefaBuilderData.formConfig
		: null;

	function resolvePersistedFormId() {
		if ( cfg && cfg.id && Number( cfg.id ) > 0 ) {
			return Number( cfg.id );
		}
		return 0;
	}

	const state = {
		formId:    resolvePersistedFormId(),
		isDirty:   false,
		saving:    false,
		form: {
			form_name:     '',
			form_type:     'standard',
			description:   '',
			steps:         [],
			settings:      {
				store_submissions:    true,
				enable_ajax:          true,
				enable_nonce_refresh: true,
				enable_antispam:      true,
				enable_events:        true,
				enable_debug_console: false,
				require_login:        false,
				redirect_url:         '',
				success_message:      '',
				error_message:        '',
				form_theme:           '',
				custom_styles:        {},
			},
			notifications: [],
			actions:       [],
		},
		ui: {
			activeTab:      'builder',
			selectedItem:   null,
			dragSource:     null,
			openAccordions: {},
		},
	};

	/* ---- Bootstrap from localized data ---- */
	const i18n      = clefaBuilderData.i18n || {};
	const fieldDefs = clefaBuilderData.fieldTypes || {};
	const actionDefs = clefaBuilderData.actionTypes || [];
	const deps      = clefaBuilderData.dependencies || {};

	function isDefinitionAvailable( def ) {
		if ( ! def ) { return true; }
		if ( typeof def.available === 'boolean' ) { return def.available; }
		const requires = def.requires || [];
		if ( ! requires.length ) { return true; }
		return requires.every( key => deps[ key ] === true );
	}

	function getDisabledReason( def ) {
		if ( ! def ) { return i18n.pluginRequired || 'Plugin required'; }
		return def.disabled_reason || i18n.pluginRequired || 'Plugin required';
	}

	if ( cfg && cfg.config ) {
		const c = cfg.config;
		state.form.form_name   = c.form_name   || cfg.form_name  || i18n.newFormName || 'Untitled Form';
		state.form.form_type   = c.form_type   || cfg.form_type  || 'standard';
		state.form.description = c.description || cfg.description|| '';
		state.form.steps         = c.steps         || [];
		state.form.settings      = Object.assign( {}, state.form.settings, c.settings || {} );
		state.form.notifications = c.notifications || [];
		state.form.actions       = c.actions       || [];
		// Restore form_theme and custom_styles from settings
		if ( c.settings ) {
			if ( c.settings.form_theme    !== undefined ) { state.form.settings.form_theme    = c.settings.form_theme; }
			if ( c.settings.custom_styles !== undefined ) { state.form.settings.custom_styles = c.settings.custom_styles || {}; }
		}
	} else if ( cfg && cfg.form_name ) {
		state.form.form_name = cfg.form_name;
		state.form.form_type = cfg.form_type || 'standard';
	}

	if ( ! state.form.steps.length ) {
		state.form.steps = [ makeStep( 'step_1', 'Step 1' ) ];
	}

	if ( ! state.form.form_name ) {
		state.form.form_name = i18n.newFormName || 'Untitled Form';
	}

	/* ---- Helpers ---- */
	function hasPersistedFormId() {
		return Number( state.formId ) > 0;
	}

	function uid() {
		return 'f_' + Math.random().toString(36).slice(2,9);
	}

	function makeStep( id, name ) {
		return { step_id: id || uid(), step_name: name || 'Step', fields: [], routing: [], conditions: [], step_heading: '', step_description: '', next_button_text: '', prev_button_text: '', submit_button_text: '' };
	}

	function makeField( type ) {
		const id    = uid();
		const label = getFieldLabel( type );
		const base  = {
			field_id: id, field_type: type, label: label,
			placeholder: '', description: '', default_value: '',
			required: false, readonly: false, disabled: false, hidden: false,
			css_class: '', wrapper_class: '',
			validation_rules: [], conditions: [], mapping: {}, options: [], live_check: {}, advanced: {},
		};
		if ( type === 'repeater' ) {
			base.sub_fields = [];
			base.min_rows   = 1;
			base.max_rows   = 10;
			base.layout     = 'stack';
		}
		return base;
	}

	function makeSubField( type ) {
		return { field_id: uid(), field_type: type || 'text', label: 'Field', placeholder: '', required: false };
	}

	function getFieldLabel( type ) {
		for ( const groupKey in fieldDefs ) {
			const group = fieldDefs[ groupKey ];
			if ( group.fields ) {
				const match = group.fields.find( f => f.type === type );
				if ( match ) { return match.label; }
			}
		}
		return type.replace(/_/g,' ').replace(/\b\w/g,c=>c.toUpperCase());
	}

	function getFieldIcon( type ) {
		for ( const groupKey in fieldDefs ) {
			const group = fieldDefs[ groupKey ];
			if ( group.fields ) {
				const match = group.fields.find( f => f.type === type );
				if ( match ) { return match.icon; }
			}
		}
		return 'dashicons-forms';
	}

	function getActionDef( type ) {
		return actionDefs.find( a => a.type === type ) || { label: type, icon: 'dashicons-admin-generic' };
	}

	function markDirty() {
		if ( ! state.isDirty ) {
			state.isDirty = true;
			const indicator = document.querySelector('[data-clefa-role="unsaved-indicator"]');
			if ( indicator ) { indicator.setAttribute('data-clefa-visible','true'); }
		}
	}

	function markClean() {
		state.isDirty = false;
		const indicator = document.querySelector('[data-clefa-role="unsaved-indicator"]');
		if ( indicator ) { indicator.removeAttribute('data-clefa-visible'); }
	}

	function esc( str ) {
		const d = document.createElement('div');
		d.appendChild( document.createTextNode( str || '' ) );
		return d.innerHTML;
	}

	/* ---- Toast ---- */
	let toastTimer = null;

	function showToast( message, type ) {
		let toast = document.querySelector('.clefa-save-toast');
		if ( ! toast ) {
			toast = document.createElement('div');
			toast.className = 'clefa-save-toast';
			document.body.appendChild( toast );
		}
		toast.setAttribute('data-clefa-type', type);
		toast.innerHTML = '<span class="dashicons dashicons-' + ( type === 'success' ? 'yes-alt' : type === 'error' ? 'warning' : 'update' ) + '"></span> ' + esc( message );
		toast.setAttribute('data-clefa-visible','true');
		clearTimeout( toastTimer );
		if ( type !== 'saving' ) {
			toastTimer = setTimeout(() => toast.removeAttribute('data-clefa-visible'), 3000);
		}
	}

	/* =============================================
	   Rendering
	   ============================================= */

	function render() {
		renderFieldsSidebar();
		renderCanvas();
		renderSettingsCanvasTab();
		renderActionsTab();
		renderNotificationsTab();
		syncFormNameInput();
	}

	function syncFormNameInput() {
		const input = document.getElementById('clefa-form-name');
		if ( input && input.value !== state.form.form_name ) {
			input.value = state.form.form_name;
		}
	}

	/* ---- Field Types Sidebar ---- */
	function renderFieldsSidebar() {
		const container = document.querySelector('[data-clefa-role="field-groups"]');
		if ( ! container ) { return; }

		let html = '';
		for ( const groupKey in fieldDefs ) {
			const group = fieldDefs[ groupKey ];
			html += '<div class="clefa-field-group">';
			html += '<div class="clefa-field-group-title">' + esc( group.label ) + '</div>';
			( group.fields || [] ).forEach( field => {
				const available = isDefinitionAvailable( field );
				const reason    = getDisabledReason( field );
				html += '<div class="clefa-field-type-item"'
					+ ' draggable="' + ( available ? 'true' : 'false' ) + '"'
					+ ' data-clefa-draggable="field-type"'
					+ ' data-clefa-field-type="' + esc( field.type ) + '"'
					+ ' data-clefa-available="' + ( available ? 'true' : 'false' ) + '"'
					+ ( available ? '' : ' data-clefa-disabled="true" title="' + esc( reason ) + '"' )
					+ '>';
				html += '<span class="dashicons ' + esc( field.icon ) + '"></span>';
				html += '<span class="clefa-field-type-label">' + esc( field.label ) + '</span>';
				if ( ! available ) {
					html += '<span class="clefa-dep-badge">' + esc( reason ) + '</span>';
				}
				html += '</div>';
			});
			html += '</div>';
		}
		container.innerHTML = html;
		bindFieldTypeDragEvents( container );
	}

	/* ---- Canvas (Builder Tab) ---- */
	function renderCanvas() {
		const wrap = document.querySelector('[data-clefa-role="steps-wrap"]');
		if ( ! wrap ) { return; }

		wrap.innerHTML = '';
		state.form.steps.forEach( (step, stepIdx) => {
			wrap.appendChild( renderStepBlock(step, stepIdx) );
		});
	}

	function renderStepBlock( step, stepIdx ) {
		const div = document.createElement('div');
		div.className = 'clefa-step-block';
		div.setAttribute('data-clefa-step-id', step.step_id);
		div.setAttribute('data-clefa-step-index', stepIdx);

		const isCollapsed = state.ui.openAccordions['step_' + step.step_id] === false;
		if ( isCollapsed ) { div.setAttribute('data-clefa-collapsed','true'); }

		div.innerHTML = `
			<div class="clefa-step-header" data-clefa-role="step-header" data-clefa-action="toggle-step" data-clefa-step-id="${esc(step.step_id)}">
				<span class="clefa-step-header-grip dashicons dashicons-menu" data-clefa-role="step-drag-handle"></span>
				<span class="clefa-step-number">${stepIdx + 1}</span>
				<input type="text" class="clefa-step-name-input" value="${esc(step.step_name)}" data-clefa-state-path="step.${esc(step.step_id)}.step_name" placeholder="Step name..." />
				<div class="clefa-step-header-actions">
					<button type="button" class="clefa-btn clefa-btn-xs clefa-btn-ghost" data-clefa-action="edit-step" data-clefa-step-id="${esc(step.step_id)}" title="Step settings">
						<span class="dashicons dashicons-admin-settings"></span>
					</button>
					<button type="button" class="clefa-btn clefa-btn-xs clefa-btn-ghost" data-clefa-action="duplicate-step" data-clefa-step-id="${esc(step.step_id)}" title="Duplicate step">
						<span class="dashicons dashicons-admin-page"></span>
					</button>
					<button type="button" class="clefa-btn clefa-btn-xs clefa-btn-danger-ghost" data-clefa-action="delete-step" data-clefa-step-id="${esc(step.step_id)}" title="Delete step">
						<span class="dashicons dashicons-trash"></span>
					</button>
					<button type="button" class="clefa-step-toggle" data-clefa-action="collapse-step" data-clefa-step-id="${esc(step.step_id)}">
						<span class="dashicons dashicons-arrow-down-alt2 clefa-accordion-chevron"></span>
					</button>
				</div>
			</div>
			<div class="clefa-step-body">
				<div class="clefa-fields-canvas" data-clefa-role="fields-canvas" data-clefa-step-id="${esc(step.step_id)}" data-clefa-drop-zone="step">
					${renderFieldItems(step.fields)}
				</div>
				<button type="button" class="clefa-step-add-field-btn" data-clefa-action="add-field-to-step" data-clefa-step-id="${esc(step.step_id)}">
					<span class="dashicons dashicons-plus-alt2"></span> Add Field
				</button>
			</div>`;

		bindFieldCanvasDrop( div.querySelector('[data-clefa-role="fields-canvas"]') );
		bindFieldItemEvents( div );
		return div;
	}

	function renderFieldItems( fields ) {
		if ( ! fields || ! fields.length ) {
			return '<div class="clefa-add-field-zone" data-clefa-role="drop-empty"><span class="dashicons dashicons-plus-alt2"></span> Drag a field here or click Add Field</div>';
		}
		return fields.map( field => renderFieldItem(field) ).join('');
	}

	function renderFieldItem( field ) {
		const icon     = getFieldIcon( field.field_type );
		const label    = field.label || field.field_type;
		const isSel    = state.ui.selectedItem && state.ui.selectedItem.type === 'field' && state.ui.selectedItem.id === field.field_id;
		const isLocked = !! field.locked;
		return `<div class="clefa-field-item${isLocked ? ' clefa-field-item-locked' : ''}" draggable="${isLocked ? 'false' : 'true'}"
		             data-clefa-field-id="${esc(field.field_id)}"
		             data-clefa-field-type="${esc(field.field_type)}"
		             data-clefa-selected="${isSel ? 'true' : 'false'}"
		             data-clefa-action="select-field">
			<span class="clefa-field-grip dashicons ${isLocked ? 'dashicons-lock' : 'dashicons-menu'}" style="${isLocked ? 'cursor:default;opacity:.4;' : ''}"></span>
			<span class="clefa-field-item-type-icon dashicons ${esc(icon)}"></span>
			<div class="clefa-field-item-body">
				<div class="clefa-field-item-label">${esc(label)}</div>
				<div class="clefa-field-item-type">${esc(field.field_type)}</div>
			</div>
			${field.required ? '<span class="clefa-badge clefa-badge-warning" style="font-size:.65rem;">required</span>' : ''}
			${isLocked ? '<span class="clefa-badge clefa-badge-info" style="font-size:.65rem;" title="Core field — cannot be deleted">locked</span>' : ''}
			<div class="clefa-field-item-actions">
				${! isLocked ? `<button type="button" class="clefa-field-item-btn" data-clefa-action="duplicate-field" data-clefa-field-id="${esc(field.field_id)}" title="Duplicate">
					<span class="dashicons dashicons-admin-page"></span>
				</button>` : ''}
				${! isLocked ? `<button type="button" class="clefa-field-item-btn" data-clefa-action="delete-field" data-clefa-field-id="${esc(field.field_id)}" title="Delete">
					<span class="dashicons dashicons-trash"></span>
				</button>` : `<span class="clefa-field-item-btn" style="opacity:.35;cursor:default;" title="Core field — cannot be deleted">
					<span class="dashicons dashicons-lock"></span>
				</span>`}
			</div>
		</div>`;
	}

	/* ---- Settings Tab ---- */
	function renderSettingsCanvasTab() {
		const s = state.form.settings;

		// Standard [data-clefa-setting] inputs / selects / toggles
		document.querySelectorAll('[data-clefa-setting]').forEach( el => {
			const key = el.getAttribute('data-clefa-setting');
			if ( key === 'form_theme' ) { return; } // handled separately below
			const val = s[ key ];
			if ( val === undefined ) { return; }
			if ( el.tagName === 'INPUT' || el.tagName === 'TEXTAREA' ) {
				el.value = val || '';
			} else if ( el.tagName === 'SELECT' ) {
				el.value = val || '';
			} else if ( el.classList.contains('clefa-toggle') ) {
				el.setAttribute('data-clefa-value', val ? 'true' : 'false');
			}
		});

		const typeSelect = document.querySelector('[data-clefa-setting="form_type"]');
		if ( typeSelect ) { typeSelect.value = state.form.form_type || 'standard'; }

		const descTA = document.querySelector('[data-clefa-setting="description"]');
		if ( descTA ) { descTA.value = state.form.description || ''; }

		// Theme picker: activate the right card
		syncThemePickerUI( s.form_theme || '' );

		// Custom style inputs
		const cs = s.custom_styles || {};
		document.querySelectorAll('[data-clefa-custom-style]').forEach( el => {
			const key = el.getAttribute('data-clefa-custom-style');
			const val = cs[ key ] || '';
			if ( el.type === 'color' ) {
				el.value = /^#[0-9a-fA-F]{6}$/.test( val ) ? val : '#4f46e5';
			} else if ( el.type === 'range' ) {
				el.value = val || el.getAttribute('min') || '0';
				updateRangeLabel( key, el.value );
			} else {
				el.value = val || '';
			}
		});
		document.querySelectorAll('[data-clefa-custom-style-text]').forEach( el => {
			const key = el.getAttribute('data-clefa-custom-style-text');
			el.value = cs[ key ] || '';
		});
		document.querySelectorAll('[data-clefa-range-label]').forEach( el => {
			const key = el.getAttribute('data-clefa-range-label');
			const rangeEl = document.querySelector('[data-clefa-custom-style="' + key + '"]');
			if ( rangeEl ) { updateRangeLabel( key, rangeEl.value ); }
		});
	}

	function syncThemePickerUI( activeKey ) {
		document.querySelectorAll('.clefa-theme-pick-card').forEach( card => {
			card.classList.toggle( 'clefa-theme-pick-active', card.getAttribute('data-clefa-theme-key') === activeKey );
		});
		const hidden = document.getElementById('clefa-theme-value');
		if ( hidden ) { hidden.value = activeKey; }
	}

	function updateRangeLabel( key, val ) {
		const label = document.querySelector('[data-clefa-range-label="' + key + '"]');
		if ( label ) { label.textContent = val ? val + 'px' : '—'; }
	}

	/* ---- Actions Tab ---- */
	function renderActionsTab() {
		const list = document.querySelector('[data-clefa-role="actions-list"]');
		if ( ! list ) { return; }

		if ( ! state.form.actions || ! state.form.actions.length ) {
			list.innerHTML = '<div class="clefa-empty-state clefa-empty-state-sm"><p style="color:var(--clefa-text-muted);font-size:.875rem;">No actions yet. Click "Add Action" to add a submit action.</p></div>';
			return;
		}

		let html = '';
		state.form.actions.forEach( action => {
			const def    = getActionDef( action.action_type );
			const isOpen = state.ui.openAccordions[ 'action_' + action.action_id ];
			html += `<div class="clefa-action-item" data-clefa-action-id="${esc(action.action_id)}" data-clefa-open="${isOpen ? 'true' : 'false'}">
				<div class="clefa-action-item-header" data-clefa-action="toggle-action-item" data-clefa-action-id="${esc(action.action_id)}">
					<span class="clefa-action-icon"><span class="dashicons ${esc(def.icon)}"></span></span>
					<div>
						<div class="clefa-action-item-name">${esc(action.label || def.label)}</div>
						<div class="clefa-action-item-type">${esc(def.label)}</div>
					</div>
					<div class="clefa-action-item-controls">
						${action.conditions && action.conditions.length ? '<span class="clefa-action-condition-badge"><span class="dashicons dashicons-randomize" style="font-size:.8rem;width:.8rem;height:.8rem;"></span> Conditional</span>' : ''}
						<div class="clefa-toggle" data-clefa-action-toggle-id="${esc(action.action_id)}" data-clefa-value="${action.enabled !== false ? 'true' : 'false'}">
							<span class="clefa-toggle-track"></span>
						</div>
						<button type="button" class="clefa-field-item-btn" data-clefa-action="delete-action" data-clefa-action-id="${esc(action.action_id)}" title="Remove">
							<span class="dashicons dashicons-trash"></span>
						</button>
					</div>
				</div>
				<div class="clefa-action-item-body">
					${renderActionConfig(action)}
				</div>
			</div>`;
		});
		list.innerHTML = html;
		bindActionEvents( list );
	}

	function renderActionConfig( action ) {
		const type = action.action_type;
		const cfg  = action.config || {};

		const fieldOptions = getFieldOptions();

		let html = '<div class="clefa-action-config" data-clefa-action-id="' + esc(action.action_id) + '">';
		html += renderPanelField( 'Label (internal)', 'text', cfg.label || action.label || '', 'action-config-label', action.action_id );

		switch ( type ) {
			case 'save_submission':
				html += renderToggleRow( 'Store submission data', 'action-config-store', action.action_id, cfg.store !== false );
				break;
			case 'register_user':
				html += renderSelectField( 'Email field', 'action-config-email_field', action.action_id, fieldOptions, cfg.email_field || '' );
				html += renderSelectField( 'Username field', 'action-config-username_field', action.action_id, fieldOptions, cfg.username_field || '' );
				html += renderSelectField( 'Password field', 'action-config-password_field', action.action_id, fieldOptions, cfg.password_field || '' );
				html += renderPanelField( 'Default role', 'text', cfg.default_role || 'subscriber', 'action-config-default_role', action.action_id );
				html += renderToggleRow( 'Auto-login after registration', 'action-config-auto_login', action.action_id, cfg.auto_login || false );
				break;
			case 'login_user':
				html += renderSelectField( 'Username/Email field', 'action-config-username_field', action.action_id, fieldOptions, cfg.username_field || '' );
				html += renderSelectField( 'Password field', 'action-config-password_field', action.action_id, fieldOptions, cfg.password_field || '' );
				html += renderToggleRow( 'Remember me', 'action-config-remember_me', action.action_id, cfg.remember_me || false );
				break;
			case 'update_user_meta':
				html += renderPanelField( 'Meta key', 'text', cfg.meta_key || '', 'action-config-meta_key', action.action_id );
				html += renderSelectField( 'Source field', 'action-config-source_field', action.action_id, fieldOptions, cfg.source_field || '' );
				html += renderSelectField( 'Target user', 'action-config-user_target', action.action_id, [
					{value:'current',label:'Current User'},{value:'created',label:'Just Registered User'},
				], cfg.user_target || 'current' );
				break;
			case 'update_post_meta':
				html += renderPanelField( 'Meta key', 'text', cfg.meta_key || '', 'action-config-meta_key', action.action_id );
				html += renderSelectField( 'Source field', 'action-config-source_field', action.action_id, fieldOptions, cfg.source_field || '' );
				html += renderSelectField( 'Target post', 'action-config-post_target', action.action_id, [
					{value:'current',label:'Current Post'},{value:'created',label:'Just Created Post'},
				], cfg.post_target || 'current' );
				break;
			case 'create_post':
				html += renderPanelField( 'Post type', 'text', cfg.post_type || 'post', 'action-config-post_type', action.action_id );
				html += renderSelectField( 'Title field', 'action-config-title_field', action.action_id, fieldOptions, cfg.title_field || '' );
				html += renderSelectField( 'Content field', 'action-config-content_field', action.action_id, fieldOptions, cfg.content_field || '' );
				html += renderPanelField( 'Post status', 'text', cfg.post_status || 'draft', 'action-config-post_status', action.action_id );
				break;
			case 'send_email':
				html += renderPanelField( 'To', 'text', cfg.to || '{admin_email}', 'action-config-to', action.action_id );
				html += renderPanelField( 'Subject', 'text', cfg.subject || '', 'action-config-subject', action.action_id );
				html += renderPanelField( 'Message', 'textarea', cfg.message || '', 'action-config-message', action.action_id );
				break;
			case 'redirect':
				html += renderPanelField( 'Redirect URL', 'url', cfg.redirect_url || '', 'action-config-redirect_url', action.action_id );
				break;
			case 'set_user_role':
				html += renderPanelField( 'Role', 'text', cfg.role || '', 'action-config-role', action.action_id );
				html += renderSelectField( 'Mode', 'action-config-role_mode', action.action_id, [
					{value:'replace',label:'Replace existing role'},{value:'add',label:'Add role'},
				], cfg.role_mode || 'replace' );
				break;
			case 'webhook':
				html += renderPanelField( 'URL', 'url', cfg.url || '', 'action-config-url', action.action_id );
				html += renderSelectField( 'Method', 'action-config-method', action.action_id, [
					{value:'POST',label:'POST'},{value:'GET',label:'GET'},{value:'PUT',label:'PUT'},
				], cfg.method || 'POST' );
				break;
			case 'update_acf_field':
				html += renderPanelField( 'ACF field key', 'text', cfg.acf_field || '', 'action-config-acf_field', action.action_id );
				html += renderSelectField( 'Source field', 'action-config-source_field', action.action_id, fieldOptions, cfg.source_field || '' );
				html += renderSelectField( 'Object type', 'action-config-object_type', action.action_id, [
					{value:'user',label:'Current User'},{value:'post',label:'Current Post'},{value:'option',label:'Options Page'},
				], cfg.object_type || 'user' );
				break;
			default:
				html += '<p style="color:var(--clefa-text-muted);font-size:.8125rem;">Configuration for this action type will be available in a future update.</p>';
		}

		html += '</div>';
		return html;
	}

	function getFieldOptions() {
		const opts = [ {value:'',label:'— Select a field —'} ];
		state.form.steps.forEach( step => {
			( step.fields || [] ).forEach( field => {
				opts.push({ value: field.field_id, label: field.label || field.field_id });
			});
		});
		return opts;
	}

	function renderPanelField( label, type, value, key, actionId ) {
		if ( type === 'textarea' ) {
			return `<div class="clefa-panel-field-row"><label>${esc(label)}</label><textarea rows="3" data-clefa-action-config-key="${esc(key)}" data-clefa-action-id="${esc(actionId)}">${esc(value)}</textarea></div>`;
		}
		return `<div class="clefa-panel-field-row"><label>${esc(label)}</label><input type="${esc(type)}" value="${esc(value)}" data-clefa-action-config-key="${esc(key)}" data-clefa-action-id="${esc(actionId)}" /></div>`;
	}

	function renderSelectField( label, key, actionId, options, selected ) {
		let opts = options.map( o => `<option value="${esc(o.value)}"${o.value===selected?' selected':''}>${esc(o.label)}</option>` ).join('');
		return `<div class="clefa-panel-field-row"><label>${esc(label)}</label><select data-clefa-action-config-key="${esc(key)}" data-clefa-action-id="${esc(actionId)}">${opts}</select></div>`;
	}

	function renderToggleRow( label, key, actionId, value ) {
		return `<div class="clefa-panel-field-row clefa-panel-field-row-toggle">
			<label>${esc(label)}</label>
			<div class="clefa-toggle" data-clefa-action-config-key="${esc(key)}" data-clefa-action-id="${esc(actionId)}" data-clefa-value="${value ? 'true' : 'false'}">
				<span class="clefa-toggle-track"></span>
			</div>
		</div>`;
	}

	/* ---- Notifications Tab ---- */
	function renderNotificationsTab() {
		const list = document.querySelector('[data-clefa-role="notifications-list"]');
		if ( ! list ) { return; }

		if ( ! state.form.notifications || ! state.form.notifications.length ) {
			list.innerHTML = '<div class="clefa-empty-state clefa-empty-state-sm"><p style="color:var(--clefa-text-muted);font-size:.875rem;">No notifications yet. Click "Add Notification" to configure email notifications.</p></div>';
			return;
		}

		const fieldOptions = getFieldOptions();
		let html = '';
		state.form.notifications.forEach( notif => {
			const isOpen = state.ui.openAccordions[ 'notif_' + notif.notification_id ];
			html += `<div class="clefa-notification-item" data-clefa-notif-id="${esc(notif.notification_id)}" data-clefa-open="${isOpen ? 'true' : 'false'}">
				<div class="clefa-notification-header" data-clefa-action="toggle-notif" data-clefa-notif-id="${esc(notif.notification_id)}">
					<span class="dashicons dashicons-email-alt" style="color:var(--clefa-primary);"></span>
					<div style="flex:1;">
						<div style="font-weight:600;font-size:.875rem;">${esc(notif.label || 'Notification')}</div>
						<div style="font-size:.75rem;color:var(--clefa-text-muted);">To: ${esc(notif.to || '—')}</div>
					</div>
					<div class="clefa-action-item-controls">
						<div class="clefa-toggle" data-clefa-notif-toggle-id="${esc(notif.notification_id)}" data-clefa-value="${notif.enabled !== false ? 'true' : 'false'}">
							<span class="clefa-toggle-track"></span>
						</div>
						<button type="button" class="clefa-field-item-btn" data-clefa-action="delete-notification" data-clefa-notif-id="${esc(notif.notification_id)}" title="Remove">
							<span class="dashicons dashicons-trash"></span>
						</button>
					</div>
				</div>
				<div class="clefa-notification-body">
					<div class="clefa-panel-field-row"><label>Label (internal)</label><input type="text" value="${esc(notif.label)}" data-clefa-notif-key="label" data-clefa-notif-id="${esc(notif.notification_id)}" /></div>
					<div class="clefa-panel-field-row"><label>To</label><input type="text" value="${esc(notif.to)}" data-clefa-notif-key="to" data-clefa-notif-id="${esc(notif.notification_id)}" placeholder="{admin_email} or email@example.com" /></div>
					<div class="clefa-panel-field-row"><label>CC</label><input type="text" value="${esc(notif.cc||'')}" data-clefa-notif-key="cc" data-clefa-notif-id="${esc(notif.notification_id)}" /></div>
					<div class="clefa-panel-field-row"><label>BCC</label><input type="text" value="${esc(notif.bcc||'')}" data-clefa-notif-key="bcc" data-clefa-notif-id="${esc(notif.notification_id)}" /></div>
					<div class="clefa-panel-field-row"><label>Subject</label><input type="text" value="${esc(notif.subject)}" data-clefa-notif-key="subject" data-clefa-notif-id="${esc(notif.notification_id)}" /></div>
					<div class="clefa-panel-field-row"><label>Message <small style="color:var(--clefa-text-muted);">Use {field_id} tokens</small></label><textarea rows="5" data-clefa-notif-key="message" data-clefa-notif-id="${esc(notif.notification_id)}">${esc(notif.message||'')}</textarea></div>
				</div>
			</div>`;
		});
		list.innerHTML = html;
		bindNotificationEvents( list );
	}

	/* ---- Right Panel ---- */
	function openFieldPanel( fieldId ) {
		let field = null;
		let stepId = null;
		state.form.steps.forEach( step => {
			const f = ( step.fields || [] ).find( f => f.field_id === fieldId );
			if ( f ) { field = f; stepId = step.step_id; }
		});
		if ( ! field ) { return; }

		state.ui.selectedItem = { type: 'field', id: fieldId };
		highlightSelectedField();

		const empty   = document.querySelector('[data-clefa-role="panel-empty"]');
		const content = document.querySelector('[data-clefa-role="panel-content"]');
		const title   = document.querySelector('[data-clefa-role="panel-title"]');
		if ( ! content ) { return; }

		if ( empty ) { empty.style.display = 'none'; }
		content.style.display = 'flex';
		if ( title ) { title.textContent = ( field.label || field.field_type ) + ' Settings'; }

		const sections = document.querySelector('[data-clefa-role="panel-sections"]');
		if ( ! sections ) { return; }

		sections.innerHTML = renderFieldPanelSections( field );
		bindPanelEvents( sections, field, stepId );
	}

	function openStepPanel( stepId ) {
		const step = state.form.steps.find( s => s.step_id === stepId );
		if ( ! step ) { return; }

		state.ui.selectedItem = { type: 'step', id: stepId };

		const empty   = document.querySelector('[data-clefa-role="panel-empty"]');
		const content = document.querySelector('[data-clefa-role="panel-content"]');
		const title   = document.querySelector('[data-clefa-role="panel-title"]');

		if ( empty ) { empty.style.display = 'none'; }
		if ( content ) { content.style.display = 'flex'; }
		if ( title ) { title.textContent = 'Step: ' + step.step_name; }

		const sections = document.querySelector('[data-clefa-role="panel-sections"]');
		if ( sections ) {
			sections.innerHTML = renderStepPanelSections( step );
			bindStepPanelEvents( sections, step );
		}
	}

	function closePanel() {
		state.ui.selectedItem = null;
		highlightSelectedField();
		const empty   = document.querySelector('[data-clefa-role="panel-empty"]');
		const content = document.querySelector('[data-clefa-role="panel-content"]');
		if ( empty ) { empty.style.display = ''; }
		if ( content ) { content.style.display = 'none'; }
	}

	function highlightSelectedField() {
		document.querySelectorAll('.clefa-field-item').forEach( el => {
			const id = el.getAttribute('data-clefa-field-id');
			el.setAttribute('data-clefa-selected', ( state.ui.selectedItem && state.ui.selectedItem.type === 'field' && state.ui.selectedItem.id === id ) ? 'true' : 'false');
		});
	}

	function renderFieldPanelSections( field ) {
		const sections = [
			{ key: 'general',    label: 'General',    content: renderGeneralSection(field) },
			{ key: 'validation', label: 'Validation', content: renderValidationSection(field) },
			{ key: 'conditions', label: 'Conditions', content: renderConditionsSection(field) },
			{ key: 'mapping',    label: 'Mapping',    content: renderMappingSection(field) },
			{ key: 'advanced',   label: 'Advanced',   content: renderAdvancedSection(field) },
		];

		let html = '';
		sections.forEach( s => {
			const isOpen = state.ui.openAccordions[ 'panel_' + s.key ] !== false;
			html += `<div class="clefa-accordion-section" data-clefa-section="${esc(s.key)}" data-clefa-open="${isOpen ? 'true' : 'false'}">
				<button type="button" class="clefa-accordion-header" data-clefa-action="toggle-accordion" data-clefa-accordion="${esc(s.key)}">
					${esc(s.label)}
					<span class="dashicons dashicons-arrow-down-alt2 clefa-accordion-chevron"></span>
				</button>
				<div class="clefa-accordion-body">
					${s.content}
				</div>
			</div>`;
		});
		return html;
	}

	function renderStepPanelSections( step ) {
		let html = `<div class="clefa-accordion-section" data-clefa-open="true">
			<button type="button" class="clefa-accordion-header" data-clefa-action="toggle-accordion" data-clefa-accordion="step-identity">
				Identity <span class="dashicons dashicons-arrow-down-alt2 clefa-accordion-chevron"></span>
			</button>
			<div class="clefa-accordion-body">
				<div class="clefa-panel-field-row"><label>Step Name</label><input type="text" value="${esc(step.step_name)}" data-clefa-step-key="step_name" data-clefa-step-id="${esc(step.step_id)}" /></div>
				<div class="clefa-panel-field-row"><label>Heading (shown above fields)</label><input type="text" value="${esc(step.step_heading||'')}" data-clefa-step-key="step_heading" data-clefa-step-id="${esc(step.step_id)}" /></div>
				<div class="clefa-panel-field-row"><label>Description</label><textarea rows="2" data-clefa-step-key="step_description" data-clefa-step-id="${esc(step.step_id)}">${esc(step.step_description||'')}</textarea></div>
			</div>
		</div>
		<div class="clefa-accordion-section" data-clefa-open="false">
			<button type="button" class="clefa-accordion-header" data-clefa-action="toggle-accordion" data-clefa-accordion="step-buttons">
				Buttons <span class="dashicons dashicons-arrow-down-alt2 clefa-accordion-chevron"></span>
			</button>
			<div class="clefa-accordion-body">
				<div class="clefa-panel-field-row"><label>Next Button Text</label><input type="text" value="${esc(step.next_button_text||'')}" placeholder="Next →" data-clefa-step-key="next_button_text" data-clefa-step-id="${esc(step.step_id)}" /></div>
				<div class="clefa-panel-field-row"><label>Previous Button Text</label><input type="text" value="${esc(step.prev_button_text||'')}" placeholder="← Previous" data-clefa-step-key="prev_button_text" data-clefa-step-id="${esc(step.step_id)}" /></div>
				<div class="clefa-panel-field-row"><label>Submit Button Text</label><input type="text" value="${esc(step.submit_button_text||'')}" placeholder="Submit" data-clefa-step-key="submit_button_text" data-clefa-step-id="${esc(step.step_id)}" /></div>
			</div>
		</div>`;
		return html;
	}

	function renderGeneralSection( field ) {
		let html = `
			<div class="clefa-panel-field-row"><label>Field ID <small>(stable, used in logic)</small></label><input type="text" value="${esc(field.field_id)}" data-clefa-field-key="field_id" data-clefa-field-id="${esc(field.field_id)}" /></div>
			<div class="clefa-panel-field-row"><label>Label</label><input type="text" value="${esc(field.label||'')}" data-clefa-field-key="label" data-clefa-field-id="${esc(field.field_id)}" /></div>
			${ ! [ 'checkbox', 'radio', 'html', 'range', 'range_dual', 'file', 'multi_file', 'heading', 'grid_break' ].includes( field.field_type ) ? `<div class="clefa-panel-field-row"><label>Placeholder</label><input type="text" value="${esc(field.placeholder||'')}" data-clefa-field-key="placeholder" data-clefa-field-id="${esc(field.field_id)}" /></div>` : '' }
			<div class="clefa-panel-field-row"><label>Description (below input)</label><input type="text" value="${esc(field.description||'')}" data-clefa-field-key="description" data-clefa-field-id="${esc(field.field_id)}" /></div>
			<div class="clefa-panel-field-row"><label>Default Value</label><input type="text" value="${esc(field.default_value||'')}" data-clefa-field-key="default_value" data-clefa-field-id="${esc(field.field_id)}" /></div>
			<div class="clefa-panel-field-row clefa-panel-field-row-toggle">
				<label>Required</label>
				<div class="clefa-toggle" data-clefa-field-key="required" data-clefa-field-id="${esc(field.field_id)}" data-clefa-value="${field.required ? 'true' : 'false'}">
					<span class="clefa-toggle-track"></span>
				</div>
			</div>
			<div class="clefa-panel-field-row clefa-panel-field-row-toggle">
				<label>Hidden</label>
				<div class="clefa-toggle" data-clefa-field-key="hidden" data-clefa-field-id="${esc(field.field_id)}" data-clefa-value="${field.hidden ? 'true' : 'false'}">
					<span class="clefa-toggle-track"></span>
				</div>
			</div>`;

		if ( ['select','checkbox','radio','select2'].includes(field.field_type) ) {
			html += renderOptionsRepeater( field );
		}

		if ( field.field_type === 'hidden' ) {
			html += `<div class="clefa-panel-field-row"><label>Token / Value</label><input type="text" value="${esc((field.advanced||{}).token||'')}" data-clefa-field-key="advanced.token" data-clefa-field-id="${esc(field.field_id)}" placeholder="{current_user_id}" /></div>`;
		}

		if ( field.field_type === 'html' || field.field_type === 'notice' ) {
			html += `<div class="clefa-panel-field-row"><label>Content <small>(HTML allowed)</small></label><textarea rows="6" style="font-family:monospace;font-size:.8rem;" data-clefa-field-key="content" data-clefa-field-id="${esc(field.field_id)}">${esc(field.content||'')}</textarea></div>`;
		}

		if ( field.field_type === 'repeater' ) {
			html += renderRepeaterSubFieldsSection( field );
		}

		return html;
	}

	function renderOptionsRepeater( field ) {
		let opts = ( field.options || [] );
		let html = '<div class="clefa-panel-field-row"><label>Options</label>';
		html += '<div class="clefa-options-list" data-clefa-role="options-list" data-clefa-field-id="' + esc(field.field_id) + '">';
		opts.forEach( (opt, i) => {
			const label = typeof opt === 'string' ? opt : opt.label || '';
			const value = typeof opt === 'string' ? opt : opt.value || '';
			html += `<div class="clefa-option-row" data-clefa-option-index="${i}">
				<input type="text" placeholder="Label (display text)" value="${esc(label)}" data-clefa-option-label="${i}" />
				<input type="text" placeholder="Value (optional)" value="${esc(value)}" data-clefa-option-value="${i}" />
				<button type="button" class="clefa-option-row-delete" data-clefa-action="delete-option" data-clefa-option-index="${i}"><span class="dashicons dashicons-trash"></span></button>
			</div>`;
		});
		html += '</div>';
		html += '<button type="button" class="clefa-btn clefa-btn-xs clefa-btn-outline" style="margin-top:6px;" data-clefa-action="add-option" data-clefa-field-id="' + esc(field.field_id) + '"><span class="dashicons dashicons-plus-alt2"></span> Add Option</button>';
		html += '</div>';
		return html;
	}

	function renderRepeaterSubFieldsSection( field ) {
		const subs  = field.sub_fields || [];
		const fid   = esc( field.field_id );
		const types = ['text','email','number','textarea','select','checkbox','radio','date','phone','url'];

		let html = `<div class="clefa-panel-field-row"><label>Layout</label>
			<select data-clefa-field-key="layout" data-clefa-field-id="${fid}">
				<option value="stack"${(field.layout||'stack')==='stack'?' selected':''}>Stack (vertical)</option>
				<option value="inline"${field.layout==='inline'?' selected':''}>Inline (horizontal)</option>
			</select></div>`;

		html += `<div class="clefa-panel-field-row"><label>Min rows</label>
			<input type="number" min="0" max="20" value="${esc(String(field.min_rows||1))}" data-clefa-field-key="min_rows" data-clefa-field-id="${fid}" /></div>`;

		html += `<div class="clefa-panel-field-row"><label>Max rows <small>(0 = unlimited)</small></label>
			<input type="number" min="0" max="100" value="${esc(String(field.max_rows||10))}" data-clefa-field-key="max_rows" data-clefa-field-id="${fid}" /></div>`;

		html += `<div class="clefa-panel-field-row"><label>Sub-fields</label>`;
		html += `<div data-clefa-role="subfields-list" data-clefa-field-id="${fid}" style="display:flex;flex-direction:column;gap:6px;margin-bottom:6px;">`;

		subs.forEach( ( sf, i ) => {
			const typeOpts = types.map( t => `<option value="${t}"${sf.field_type===t?' selected':''}>${t}</option>` ).join('');
			html += `<div class="clefa-subfield-row" data-clefa-subfield-index="${i}" style="display:flex;gap:6px;align-items:center;background:var(--clefa-surface-alt,#f8fafc);border:1px solid var(--clefa-border,#e2e8f0);border-radius:5px;padding:6px 8px;">
				<select data-clefa-subfield-key="field_type" data-clefa-subfield-index="${i}" data-clefa-field-id="${fid}" style="flex:0 0 90px;font-size:.75rem;">${typeOpts}</select>
				<input type="text" placeholder="Label" value="${esc(sf.label||'')}" data-clefa-subfield-key="label" data-clefa-subfield-index="${i}" data-clefa-field-id="${fid}" style="flex:1;min-width:0;" />
				<input type="text" placeholder="Placeholder" value="${esc(sf.placeholder||'')}" data-clefa-subfield-key="placeholder" data-clefa-subfield-index="${i}" data-clefa-field-id="${fid}" style="flex:1;min-width:0;" />
				<button type="button" class="clefa-btn clefa-btn-xs clefa-btn-danger-ghost" data-clefa-action="delete-repeater-subfield" data-clefa-subfield-index="${i}" data-clefa-field-id="${fid}" title="Remove sub-field"><span class="dashicons dashicons-trash"></span></button>
			</div>`;
		} );

		html += `</div>`;
		html += `<button type="button" class="clefa-btn clefa-btn-xs clefa-btn-outline" data-clefa-action="add-repeater-subfield" data-clefa-field-id="${fid}"><span class="dashicons dashicons-plus-alt2"></span> Add Sub-field</button>`;
		html += `</div>`;
		return html;
	}

	function renderValidationSection( field ) {
		const vrules    = field.validation_rules || [];
		const schema    = ( typeof clefaBuilderData !== 'undefined' && clefaBuilderData.validationRules ) || {};
		const fieldType = field.field_type || 'text';
		const fid       = esc( field.field_id );

		// Helper: get applicable rules for this field type
		function getApplicableRules() {
			return Object.values( schema ).filter( r => {
				const a = r.applies_to || [];
				return a.includes( '*' ) || a.includes( fieldType );
			} );
		}

		// Helper: render one rule row
		function renderRuleRow( ruleDef, idx ) {
			const ruleKey   = ruleDef.rule    || '';
			const ruleValue = ruleDef.value   !== undefined ? ruleDef.value : '';
			const ruleMsg   = ruleDef.message || '';
			const schemaDef = schema[ ruleKey ] || null;
			const applicable = getApplicableRules();

			let rowHtml = `<div class="clefa-vrule-item" style="background:var(--clefa-surface-alt);border:1px solid var(--clefa-border);border-radius:6px;padding:10px;margin-bottom:8px;">`;

			// Rule selector
			rowHtml += `<div class="clefa-panel-field-row" style="margin-bottom:6px;"><label style="font-size:.75rem;font-weight:600;text-transform:uppercase;letter-spacing:.03em;">Rule</label>
				<div style="display:flex;gap:6px;align-items:center;">
					<select data-clefa-vrule-rule data-clefa-vrule-index="${idx}" data-clefa-field-id="${fid}" style="flex:1;">
						${applicable.map( r => `<option value="${esc(r.key)}"${r.key===ruleKey?' selected':''}>${esc(r.label)}</option>` ).join('')}
					</select>
					<button type="button" class="clefa-btn clefa-btn-xs clefa-btn-danger-ghost" title="Remove" data-clefa-action="delete-validation-rule" data-clefa-vrule-index="${idx}" data-clefa-field-id="${fid}"><span class="dashicons dashicons-trash"></span></button>
				</div></div>`;

			// Value input — depends on rule schema
			if ( schemaDef && schemaDef.has_value ) {
				let valueInput = '';
				if ( schemaDef.value_type === 'number' ) {
					valueInput = `<input type="number" value="${esc(String(ruleValue))}" placeholder="${esc(schemaDef.value_placeholder||'')}" data-clefa-vrule-key="value" data-clefa-vrule-index="${idx}" data-clefa-field-id="${fid}" />`;
				} else if ( schemaDef.value_type === 'select' && schemaDef.value_options && schemaDef.value_options.length ) {
					valueInput = `<select data-clefa-vrule-key="value" data-clefa-vrule-index="${idx}" data-clefa-field-id="${fid}">
						${schemaDef.value_options.map( o => `<option value="${esc(o.value)}"${String(ruleValue)===o.value?' selected':''}>${esc(o.label)}</option>` ).join('')}
					</select>`;
				} else {
					valueInput = `<input type="text" value="${esc(String(ruleValue))}" placeholder="${esc(schemaDef.value_placeholder||'')}" data-clefa-vrule-key="value" data-clefa-vrule-index="${idx}" data-clefa-field-id="${fid}" />`;
				}
				rowHtml += `<div class="clefa-panel-field-row"><label>${esc(schemaDef.value_label||'Value')}</label>${valueInput}</div>`;
			}

			// Custom message
			rowHtml += `<div class="clefa-panel-field-row"><label>Custom message <span style="font-weight:400;opacity:.65;">(optional)</span></label><input type="text" value="${esc(ruleMsg)}" placeholder="Leave blank for default…" data-clefa-vrule-key="message" data-clefa-vrule-index="${idx}" data-clefa-field-id="${fid}" /></div>`;

			rowHtml += `</div>`;
			return rowHtml;
		}

		const applicable = getApplicableRules();
		if ( ! applicable.length ) {
			return `<p style="color:var(--clefa-text-muted);font-size:.8125rem;">No validation rules available for this field type.</p>`;
		}

		let html = `<p style="color:var(--clefa-text-muted);font-size:.8rem;margin-bottom:10px;">Add one or more validation rules. Rules run in order and stop at the first failure.</p>`;

		// Active rules list
		vrules.forEach( ( r, i ) => { html += renderRuleRow( r, i ); } );

		// Add rule button (adds first applicable rule as default)
		const firstRuleKey = applicable[ 0 ]?.key || '';
		html += `<button type="button" class="clefa-btn clefa-btn-sm clefa-btn-outline" data-clefa-action="add-validation-rule" data-clefa-field-id="${fid}" data-clefa-default-rule="${esc(firstRuleKey)}" style="margin-top:4px;">
			<span class="dashicons dashicons-plus-alt2"></span> Add Rule
		</button>`;

		return html;
	}

	function addValidationRuleToField( fieldId, defaultRuleKey ) {
		const result = findField( fieldId );
		if ( ! result ) { return; }
		result.field.validation_rules = result.field.validation_rules || [];
		result.field.validation_rules.push( { rule: defaultRuleKey || '', value: '', message: '' } );
		markDirty();
		openFieldPanel( fieldId );
	}

	function deleteValidationRuleFromField( fieldId, idx ) {
		const result = findField( fieldId );
		if ( ! result ) { return; }
		result.field.validation_rules = result.field.validation_rules || [];
		result.field.validation_rules.splice( idx, 1 );
		markDirty();
		openFieldPanel( fieldId );
	}

	function renderConditionsSection( field ) {
		const conditions = field.conditions || [];

		// Actions that require a value input in the builder
		const actionsMeta = {
			show:             { label: 'Show field',            needsValue: false },
			hide:             { label: 'Hide field',            needsValue: false },
			require:          { label: 'Make required',         needsValue: false },
			unrequire:        { label: 'Make optional',         needsValue: false },
			add_class:        { label: 'Add CSS class',         needsValue: true,  placeholder: 'e.g. highlight gold-bg' },
			remove_class:     { label: 'Remove CSS class',      needsValue: true,  placeholder: 'e.g. highlight' },
			set_style:        { label: 'Set inline style',      needsValue: true,  placeholder: 'e.g. background-color:#fff3e0' },
			set_placeholder:  { label: 'Change placeholder',    needsValue: true,  placeholder: 'e.g. Enter your company name…' },
			set_label:        { label: 'Change label',          needsValue: true,  placeholder: 'e.g. Company name' },
			set_description:  { label: 'Change description',    needsValue: true,  placeholder: 'e.g. Required for business accounts.' },
		};

		let html = `<p style="color:var(--clefa-text-muted);font-size:.8rem;margin-bottom:10px;">Control this field based on other field values. Supports show/hide, required state, CSS classes, inline styles, and text changes.</p>`;

		if ( conditions.length ) {
			conditions.forEach( (cond, i) => {
				const meta     = actionsMeta[ cond.action ] || { label: cond.action };
				const valPart  = cond.action_value ? ` <code style="background:var(--clefa-surface-alt);padding:1px 4px;border-radius:3px;">${esc(cond.action_value)}</code>` : '';
				html += `<div style="background:var(--clefa-surface-alt);border:1px solid var(--clefa-border);border-radius:6px;padding:10px;margin-bottom:8px;font-size:.8125rem;">
					<strong>${esc(cond.source_field || '?')}</strong> <span style="color:var(--clefa-text-muted)">${esc(cond.operator || '=')}</span> <em>${esc(String(cond.compare_value || ''))}</em>
					→ <span style="color:var(--clefa-primary)">${esc(meta.label || cond.action)}</span>${valPart}
					<button type="button" class="clefa-btn clefa-btn-xs clefa-btn-danger-ghost" style="float:right;" data-clefa-action="delete-condition" data-clefa-condition-index="${i}" data-clefa-field-id="${esc(field.field_id)}"><span class="dashicons dashicons-trash"></span></button>
				</div>`;
			});
		}

		const fieldOptions = getFieldOptions();
		const actionOptions = Object.entries( actionsMeta )
			.map( ([v, m]) => `<option value="${esc(v)}">${esc(m.label)}</option>` ).join('');

		html += `<div style="border:1px solid var(--clefa-border);border-radius:6px;padding:12px;background:var(--clefa-surface);">
			<div class="clefa-panel-field-row"><label>Source field</label><select data-clefa-new-condition-key="source_field" data-clefa-field-id="${esc(field.field_id)}">
				${fieldOptions.map( o => `<option value="${esc(o.value)}">${esc(o.label)}</option>` ).join('')}
			</select></div>
			<div class="clefa-panel-field-row"><label>Operator</label><select data-clefa-new-condition-key="operator" data-clefa-field-id="${esc(field.field_id)}">
				<option value="equals">Equals</option>
				<option value="not_equals">Not equals</option>
				<option value="contains">Contains</option>
				<option value="not_contains">Not contains</option>
				<option value="starts_with">Starts with</option>
				<option value="ends_with">Ends with</option>
				<option value="is_empty">Is empty</option>
				<option value="is_not_empty">Is not empty</option>
				<option value="is_checked">Is checked</option>
				<option value="is_not_checked">Is not checked</option>
				<option value="greater_than">Greater than</option>
				<option value="less_than">Less than</option>
				<option value="greater_than_or_equal">Greater than or equal</option>
				<option value="less_than_or_equal">Less than or equal</option>
				<option value="date_after">Date after</option>
				<option value="date_before">Date before</option>
				<option value="age_over">Age over</option>
				<option value="age_under">Age under</option>
				<option value="file_uploaded">File uploaded</option>
			</select></div>
			<div class="clefa-panel-field-row"><label>Compare value</label><input type="text" data-clefa-new-condition-key="compare_value" data-clefa-field-id="${esc(field.field_id)}" /></div>
			<div class="clefa-panel-field-row"><label>Action</label><select data-clefa-new-condition-key="action" data-clefa-field-id="${esc(field.field_id)}">
				${actionOptions}
			</select></div>
			<div class="clefa-panel-field-row" data-clefa-condition-value-row style="display:none;">
				<label>Action value</label>
				<input type="text" data-clefa-new-condition-key="action_value" data-clefa-field-id="${esc(field.field_id)}" placeholder="" />
			</div>
			<button type="button" class="clefa-btn clefa-btn-sm clefa-btn-primary" data-clefa-action="add-condition" data-clefa-field-id="${esc(field.field_id)}" style="margin-top:4px;">
				<span class="dashicons dashicons-plus-alt2"></span> Add Condition
			</button>
		</div>`;
		return html;
	}

	function renderMappingSection( field ) {
		const mapping = field.mapping || {};
		const acfAvailable = deps.acf === true;
		const wcAvailable  = deps.woocommerce === true;
		return `<p style="color:var(--clefa-text-muted);font-size:.8rem;margin-bottom:10px;">Map this field value to a WordPress data target on submit.</p>
			<div class="clefa-panel-field-row"><label>Mapping target</label><select data-clefa-field-key="mapping.target" data-clefa-field-id="${esc(field.field_id)}">
				<option value=""${!mapping.target?' selected':''}>No mapping</option>
				<option value="user_meta"${mapping.target==='user_meta'?' selected':''}>User Meta</option>
				<option value="post_meta"${mapping.target==='post_meta'?' selected':''}>Post Meta</option>
				<option value="post_title"${mapping.target==='post_title'?' selected':''}>Post Title</option>
				<option value="post_content"${mapping.target==='post_content'?' selected':''}>Post Content</option>
				<option value="taxonomy"${mapping.target==='taxonomy'?' selected':''}>Taxonomy Terms</option>
				<option value="acf"${mapping.target==='acf'?' selected':''}${acfAvailable ? '' : ' disabled'}>${acfAvailable ? 'ACF Field' : 'ACF Field (requires ACF)'}</option>
				<option value="wc_product"${mapping.target==='wc_product'?' selected':''}${wcAvailable ? '' : ' disabled'}>${wcAvailable ? 'WooCommerce Product' : 'WooCommerce Product (requires WooCommerce)'}</option>
			</select></div>
			<div class="clefa-panel-field-row"><label>Key / Field Name</label><input type="text" value="${esc(mapping.key||'')}" data-clefa-field-key="mapping.key" data-clefa-field-id="${esc(field.field_id)}" placeholder="meta_key, acf_field_key..." /></div>
			<div class="clefa-panel-field-row"><label>Mode</label><select data-clefa-field-key="mapping.mode" data-clefa-field-id="${esc(field.field_id)}">
				<option value="replace"${mapping.mode!=='append'?' selected':''}>Replace</option>
				<option value="append"${mapping.mode==='append'?' selected':''}>Append</option>
			</select></div>`;
	}

	function renderAdvancedSection( field ) {
		const adv = field.advanced || {};
		return `
			<div class="clefa-panel-field-row"><label>CSS class (input)</label><input type="text" value="${esc(field.css_class||'')}" data-clefa-field-key="css_class" data-clefa-field-id="${esc(field.field_id)}" /></div>
			<div class="clefa-panel-field-row"><label>CSS class (wrapper)</label><input type="text" value="${esc(field.wrapper_class||'')}" data-clefa-field-key="wrapper_class" data-clefa-field-id="${esc(field.field_id)}" /></div>
			<div class="clefa-panel-field-row"><label>Custom data attributes</label><textarea rows="2" data-clefa-field-key="advanced.data_attrs" data-clefa-field-id="${esc(field.field_id)}" placeholder='data-custom="value"'>${esc((adv.data_attrs)||'')}</textarea></div>
			<div class="clefa-panel-field-row clefa-panel-field-row-toggle">
				<label>Readonly</label>
				<div class="clefa-toggle" data-clefa-field-key="readonly" data-clefa-field-id="${esc(field.field_id)}" data-clefa-value="${field.readonly ? 'true' : 'false'}">
					<span class="clefa-toggle-track"></span>
				</div>
			</div>
			<div class="clefa-panel-field-row clefa-panel-field-row-toggle">
				<label>Disabled</label>
				<div class="clefa-toggle" data-clefa-field-key="disabled" data-clefa-field-id="${esc(field.field_id)}" data-clefa-value="${field.disabled ? 'true' : 'false'}">
					<span class="clefa-toggle-track"></span>
				</div>
			</div>`;
	}

	/* =============================================
	   Event Binding
	   ============================================= */

	function bindAll() {
		// Tab navigation
		document.querySelectorAll('[data-clefa-tab]').forEach( btn => {
			btn.addEventListener('click', e => {
				const tab = e.currentTarget.getAttribute('data-clefa-tab');
				switchTab( tab );
			});
		});

		// Form name input
		const nameInput = document.getElementById('clefa-form-name');
		if ( nameInput ) {
			nameInput.addEventListener('input', e => {
				state.form.form_name = e.target.value;
				markDirty();
			});
		}

		// Top bar action buttons
		document.addEventListener('click', handleGlobalClick );

		// Settings form changes (also handles theme picker card clicks & clear buttons)
		document.addEventListener('change', handleSettingsChange );
		document.addEventListener('input',  handleSettingsInput );
		document.addEventListener('click',  handleSettingsChange );
	}

	function handleToggleClick( e ) {
		const fieldToggle = e.target.closest( '.clefa-toggle[data-clefa-field-key]' );
		if ( fieldToggle ) {
			const key     = fieldToggle.getAttribute( 'data-clefa-field-key' );
			const fieldId = fieldToggle.getAttribute( 'data-clefa-field-id' );
			const cur     = fieldToggle.getAttribute( 'data-clefa-value' ) === 'true';
			fieldToggle.setAttribute( 'data-clefa-value', cur ? 'false' : 'true' );
			updateFieldKeyValue( fieldId, key, ! cur );
			if ( key === 'required' ) {
				refreshFieldRequiredBadge( fieldId );
			}
			markDirty();
			return true;
		}

		const actionConfigToggle = e.target.closest( '.clefa-toggle[data-clefa-action-config-key]' );
		if ( actionConfigToggle ) {
			const key      = actionConfigToggle.getAttribute( 'data-clefa-action-config-key' );
			const actionId = actionConfigToggle.getAttribute( 'data-clefa-action-id' );
			const cur      = actionConfigToggle.getAttribute( 'data-clefa-value' ) === 'true';
			actionConfigToggle.setAttribute( 'data-clefa-value', cur ? 'false' : 'true' );
			updateActionConfigKey( actionId, key, ! cur );
			markDirty();
			return true;
		}

		const actionToggle = e.target.closest( '.clefa-toggle[data-clefa-action-toggle-id]' );
		if ( actionToggle ) {
			const id  = actionToggle.getAttribute( 'data-clefa-action-toggle-id' );
			const cur = actionToggle.getAttribute( 'data-clefa-value' ) === 'true';
			actionToggle.setAttribute( 'data-clefa-value', cur ? 'false' : 'true' );
			const action = state.form.actions.find( a => a.action_id === id );
			if ( action ) {
				action.enabled = ! cur;
				markDirty();
			}
			return true;
		}

		const notifToggle = e.target.closest( '.clefa-toggle[data-clefa-notif-toggle-id]' );
		if ( notifToggle ) {
			const id  = notifToggle.getAttribute( 'data-clefa-notif-toggle-id' );
			const cur = notifToggle.getAttribute( 'data-clefa-value' ) === 'true';
			notifToggle.setAttribute( 'data-clefa-value', cur ? 'false' : 'true' );
			const notif = state.form.notifications.find( n => n.notification_id === id );
			if ( notif ) {
				notif.enabled = ! cur;
				markDirty();
			}
			return true;
		}

		const settingToggle = e.target.closest( '.clefa-toggle[data-clefa-setting]' );
		if ( settingToggle ) {
			const key = settingToggle.getAttribute( 'data-clefa-setting' );
			const cur = settingToggle.getAttribute( 'data-clefa-value' ) === 'true';
			settingToggle.setAttribute( 'data-clefa-value', cur ? 'false' : 'true' );
			state.form.settings[ key ] = ! cur;
			markDirty();
			return true;
		}

		return false;
	}

	function refreshFieldRequiredBadge( fieldId ) {
		const result = findField( fieldId );
		const item   = document.querySelector( '.clefa-field-item[data-clefa-field-id="' + fieldId + '"]' );
		if ( ! result || ! item ) {
			return;
		}

		const actions = item.querySelector( '.clefa-field-item-actions' );
		let badge     = item.querySelector( '.clefa-badge-warning' );

		if ( result.field.required ) {
			if ( ! badge ) {
				badge = document.createElement( 'span' );
				badge.className = 'clefa-badge clefa-badge-warning';
				badge.style.fontSize = '.65rem';
				badge.textContent = 'required';
				if ( actions ) {
					item.insertBefore( badge, actions );
				} else {
					item.appendChild( badge );
				}
			}
		} else if ( badge ) {
			badge.remove();
		}
	}

	function handleGlobalClick( e ) {
		if ( handleToggleClick( e ) ) {
			return;
		}

		const pickerItem = e.target.closest( '.clefa-action-picker-item' );
		if ( pickerItem ) {
			if ( pickerItem.getAttribute( 'data-clefa-disabled' ) === 'true' ) {
				return;
			}
			addAction( pickerItem.getAttribute( 'data-clefa-action-type' ) );
			return;
		}

		const deviceBtn = e.target.closest( '.clefa-simulate-device' );
		if ( deviceBtn ) {
			setSimulateDevice( deviceBtn.getAttribute( 'data-clefa-device' ) );
			return;
		}

		const target = e.target.closest( '[data-clefa-action]' );
		if ( ! target ) {
			return;
		}
		const action = target.getAttribute( 'data-clefa-action' );

		switch ( action ) {
			case 'save-form':        saveForm(); break;
			case 'publish-form':     publishForm(); break;
			case 'simulate-form':    openSimulate(); break;
			case 'close-simulate':   closeSimulate(); break;
			case 'add-step':         addStep(); break;
			case 'delete-step':      deleteStep( target.getAttribute('data-clefa-step-id') ); break;
			case 'duplicate-step':   duplicateStep( target.getAttribute('data-clefa-step-id') ); break;
			case 'edit-step':        openStepPanel( target.getAttribute('data-clefa-step-id') ); break;
			case 'collapse-step':    toggleStepCollapse( target.getAttribute('data-clefa-step-id') ); break;
			case 'toggle-step':      /* clicking header — handled by collapse */ break;
			case 'select-field':     openFieldPanel( target.closest('[data-clefa-field-id]')?.getAttribute('data-clefa-field-id') ); break;
			case 'delete-field':     deleteField( target.getAttribute('data-clefa-field-id') ); break;
			case 'duplicate-field':  duplicateField( target.getAttribute('data-clefa-field-id') ); break;
			case 'close-panel':      closePanel(); break;
			case 'add-field-to-step': openAddFieldToStep( target.getAttribute('data-clefa-step-id') ); break;
			case 'open-action-picker': openActionPicker(); break;
			case 'close-modal':      closeModal(); break;
			case 'add-notification': addNotification(); break;
			case 'toggle-accordion': toggleAccordion( target.getAttribute('data-clefa-accordion') ); break;
			case 'toggle-action-item': toggleActionItem( target.getAttribute('data-clefa-action-id') ); break;
			case 'delete-action':    deleteAction( target.getAttribute('data-clefa-action-id') ); break;
			case 'toggle-notif':     toggleNotifItem( target.getAttribute('data-clefa-notif-id') ); break;
			case 'delete-notification': deleteNotification( target.getAttribute('data-clefa-notif-id') ); break;
			case 'add-condition':    addConditionToField( target.getAttribute('data-clefa-field-id'), target.closest('.clefa-accordion-body') ); break;
			case 'delete-condition': deleteConditionFromField( target.getAttribute('data-clefa-field-id'), parseInt( target.getAttribute('data-clefa-condition-index'), 10 ) ); break;
			case 'add-validation-rule':    addValidationRuleToField( target.getAttribute('data-clefa-field-id'), target.getAttribute('data-clefa-default-rule') ); break;
			case 'delete-validation-rule': deleteValidationRuleFromField( target.getAttribute('data-clefa-field-id'), parseInt( target.getAttribute('data-clefa-vrule-index'), 10 ) ); break;
			case 'add-option':       addOptionToField( target.getAttribute('data-clefa-field-id') ); break;
			case 'delete-option':    deleteOption( target ); break;
			case 'add-repeater-subfield':    addRepeaterSubField( target.getAttribute('data-clefa-field-id') ); break;
			case 'delete-repeater-subfield': deleteRepeaterSubField( target.getAttribute('data-clefa-field-id'), parseInt( target.getAttribute('data-clefa-subfield-index'), 10 ) ); break;
		}
	}

	function handleSettingsChange( e ) {
		const el = e.target;

		// Theme picker card click
		const themeCard = el.closest('.clefa-theme-pick-card');
		if ( themeCard ) {
			state.form.settings.form_theme = themeCard.getAttribute('data-clefa-theme-key') || '';
			syncThemePickerUI( state.form.settings.form_theme );
			markDirty();
			return;
		}

		// Clear custom style button
		const clearBtn = el.closest('[data-clefa-clear-style]');
		if ( clearBtn ) {
			const key = clearBtn.getAttribute('data-clefa-clear-style');
			state.form.settings.custom_styles = state.form.settings.custom_styles || {};
			delete state.form.settings.custom_styles[ key ];
			// Reset paired inputs
			const picker = document.querySelector('[data-clefa-custom-style="' + key + '"]');
			const textEl = document.querySelector('[data-clefa-custom-style-text="' + key + '"]');
			if ( picker ) { picker.value = '#4f46e5'; }
			if ( textEl ) { textEl.value = ''; }
			markDirty();
			return;
		}

		// Custom style color picker
		if ( el.matches('[data-clefa-custom-style]') ) {
			const key = el.getAttribute('data-clefa-custom-style');
			state.form.settings.custom_styles = state.form.settings.custom_styles || {};
			if ( el.type === 'range' ) {
				const val = el.value;
				state.form.settings.custom_styles[ key ] = val || '';
				updateRangeLabel( key, val );
			} else {
				state.form.settings.custom_styles[ key ] = el.value;
				// Mirror to text input
				const textEl = document.querySelector('[data-clefa-custom-style-text="' + key + '"]');
				if ( textEl ) { textEl.value = el.value; }
			}
			markDirty();
			return;
		}

		// Custom style text (hex) input
		if ( el.matches('[data-clefa-custom-style-text]') ) {
			const key = el.getAttribute('data-clefa-custom-style-text');
			const val = el.value.trim();
			state.form.settings.custom_styles = state.form.settings.custom_styles || {};
			if ( val ) {
				state.form.settings.custom_styles[ key ] = val;
				// If valid hex, mirror to color picker
				if ( /^#[0-9a-fA-F]{6}$/.test( val ) ) {
					const picker = document.querySelector('[data-clefa-custom-style="' + key + '"]');
					if ( picker && picker.type === 'color' ) { picker.value = val; }
				}
			} else {
				delete state.form.settings.custom_styles[ key ];
			}
			markDirty();
			return;
		}

		if ( el.matches('[data-clefa-setting]') ) {
			const key = el.getAttribute('data-clefa-setting');
			if ( key === 'form_theme' ) {
				state.form.settings.form_theme = el.value;
			} else if ( key === 'form_type' ) {
				state.form.form_type = el.value;
			} else if ( key === 'description' ) {
				state.form.description = el.value;
			} else {
				state.form.settings[ key ] = el.value;
			}
			markDirty();
		}

		if ( el.matches( '[data-clefa-field-key]' ) && ! el.classList.contains( 'clefa-toggle' ) ) {
			updateFieldKeyValue( el.getAttribute( 'data-clefa-field-id' ), el.getAttribute( 'data-clefa-field-key' ), el.type === 'checkbox' ? el.checked : el.value );
			markDirty();
		}

		// Validation rule — rule-key selector (triggers re-render to update value input)
		if ( el.matches('[data-clefa-vrule-rule]') ) {
			const fieldId = el.getAttribute('data-clefa-field-id');
			const idx     = parseInt( el.getAttribute('data-clefa-vrule-index'), 10 );
			const result  = findField( fieldId );
			if ( result ) {
				result.field.validation_rules[ idx ].rule  = el.value;
				result.field.validation_rules[ idx ].value = '';
				markDirty();
				openFieldPanel( fieldId );
			}
			return;
		}

		// Condition action selector — show/hide the action_value row live
		if ( el.matches('[data-clefa-new-condition-key="action"]') ) {
			const needsValue = ['add_class','remove_class','set_style','set_placeholder','set_label','set_description'];
			const placeholders = {
				add_class:       'e.g. highlight gold-bg',
				remove_class:    'e.g. highlight',
				set_style:       'e.g. background-color:#fff3e0',
				set_placeholder: 'e.g. Enter your company name…',
				set_label:       'e.g. Company name',
				set_description: 'e.g. Required for business accounts.',
			};
			const valueRow   = el.closest('[style*="padding"]')?.querySelector('[data-clefa-condition-value-row]');
			const valueInput = valueRow?.querySelector('[data-clefa-new-condition-key="action_value"]');
			if ( valueRow ) {
				const show = needsValue.includes( el.value );
				valueRow.style.display = show ? '' : 'none';
				if ( valueInput ) {
					valueInput.placeholder = placeholders[ el.value ] || '';
					if ( ! show ) { valueInput.value = ''; }
				}
			}
			return;
		}

		// Validation rule — value / message fields (inline update, no re-render)
		if ( el.matches('[data-clefa-vrule-key]') ) {
			const fieldId = el.getAttribute('data-clefa-field-id');
			const idx     = parseInt( el.getAttribute('data-clefa-vrule-index'), 10 );
			const key     = el.getAttribute('data-clefa-vrule-key');
			const result  = findField( fieldId );
			if ( result && result.field.validation_rules[ idx ] ) {
				result.field.validation_rules[ idx ][ key ] = el.value;
				markDirty();
			}
			return;
		}

		if ( el.matches('[data-clefa-step-key]') ) {
			updateStepKeyValue( el.getAttribute('data-clefa-step-id'), el.getAttribute('data-clefa-step-key'), el.value );
			markDirty();
		}

		if ( el.matches( '[data-clefa-action-config-key]' ) && ! el.classList.contains( 'clefa-toggle' ) ) {
			updateActionConfigKey( el.getAttribute( 'data-clefa-action-id' ), el.getAttribute( 'data-clefa-action-config-key' ), el.type === 'checkbox' ? el.checked : el.value );
			markDirty();
		}

		if ( el.matches('[data-clefa-notif-key]') ) {
			updateNotifKey( el.getAttribute('data-clefa-notif-id'), el.getAttribute('data-clefa-notif-key'), el.value );
			markDirty();
		}

		if ( el.matches('[data-clefa-option-value]') || el.matches('[data-clefa-option-label]') ) {
			updateFieldOptions( el );
			markDirty();
		}
	}

	function handleSettingsInput( e ) {
		const el = e.target;
		if ( el.matches('.clefa-step-name-input') ) {
			const stepId = el.closest('[data-clefa-step-id]')?.getAttribute('data-clefa-step-id');
			if ( stepId ) { updateStepKeyValue( stepId, 'step_name', el.value ); markDirty(); }
		}

		// Live update for range sliders and color pickers in style overrides
		if ( el.matches('[data-clefa-custom-style]') ) {
			const key = el.getAttribute('data-clefa-custom-style');
			state.form.settings.custom_styles = state.form.settings.custom_styles || {};
			if ( el.type === 'range' ) {
				state.form.settings.custom_styles[ key ] = el.value;
				updateRangeLabel( key, el.value );
				markDirty();
			} else if ( el.type === 'color' ) {
				state.form.settings.custom_styles[ key ] = el.value;
				const textEl = document.querySelector('[data-clefa-custom-style-text="' + key + '"]');
				if ( textEl ) { textEl.value = el.value; }
				markDirty();
			}
		}
	}

	function bindFieldTypeDragEvents( container ) {
		container.querySelectorAll('[data-clefa-draggable="field-type"]').forEach( item => {
			item.addEventListener('dragstart', e => {
				if ( item.getAttribute('data-clefa-disabled') === 'true' ) {
					e.preventDefault();
					return;
				}
				state.ui.dragSource = { source: 'sidebar', fieldType: item.getAttribute('data-clefa-field-type') };
				e.dataTransfer.effectAllowed = 'copy';
				item.setAttribute('data-clefa-dragging','true');
			});
			item.addEventListener('dragend', () => {
				state.ui.dragSource = null;
				item.removeAttribute('data-clefa-dragging');
			});
		});
	}

	function bindFieldCanvasDrop( canvas ) {
		if ( ! canvas ) { return; }
		canvas.addEventListener('dragover', e => {
			e.preventDefault();
			e.dataTransfer.dropEffect = 'copy';
			canvas.setAttribute('data-clefa-drag-over','true');
		});
		canvas.addEventListener('dragleave', e => {
			if ( ! canvas.contains(e.relatedTarget) ) {
				canvas.removeAttribute('data-clefa-drag-over');
			}
		});
		canvas.addEventListener('drop', e => {
			e.preventDefault();
			canvas.removeAttribute('data-clefa-drag-over');
			const stepId = canvas.getAttribute('data-clefa-step-id');
			if ( ! state.ui.dragSource ) { return; }

			if ( state.ui.dragSource.source === 'sidebar' ) {
				addFieldToStep( stepId, makeField( state.ui.dragSource.fieldType ) );
			} else if ( state.ui.dragSource.source === 'field' ) {
				moveField( state.ui.dragSource.fieldId, state.ui.dragSource.fromStepId, stepId );
			}
			state.ui.dragSource = null;
		});
	}

	function bindFieldItemEvents( stepBlock ) {
		stepBlock.querySelectorAll('.clefa-field-item[draggable]').forEach( item => {
			item.addEventListener('dragstart', e => {
				const fieldId = item.getAttribute('data-clefa-field-id');
				const stepId  = item.closest('[data-clefa-step-id]')?.getAttribute('data-clefa-step-id');
				state.ui.dragSource = { source: 'field', fieldId, fromStepId: stepId };
				e.dataTransfer.effectAllowed = 'move';
				item.setAttribute('data-clefa-dragging','true');
			});
			item.addEventListener('dragend', () => {
				item.removeAttribute('data-clefa-dragging');
				state.ui.dragSource = null;
			});
		});
	}

	function bindActionEvents( list ) {
		list.querySelectorAll('[data-clefa-action-config-key]').forEach( el => {
			el.addEventListener('change', e => {
				updateActionConfigKey( el.getAttribute('data-clefa-action-id'), el.getAttribute('data-clefa-action-config-key'), el.value );
				markDirty();
			});
		});
	}

	function bindNotificationEvents( list ) {
		list.querySelectorAll('[data-clefa-notif-key]').forEach( el => {
			el.addEventListener('input', e => {
				updateNotifKey( el.getAttribute('data-clefa-notif-id'), el.getAttribute('data-clefa-notif-key'), el.value );
				markDirty();
			});
		});
	}

	function bindPanelEvents( sections, field, stepId ) {
		sections.querySelectorAll( '[data-clefa-field-key]' ).forEach( el => {
			if ( el.classList.contains( 'clefa-toggle' ) ) {
				return;
			}
			el.addEventListener( 'input', e => {
				updateFieldKeyValue( field.field_id, el.getAttribute('data-clefa-field-key'), el.value );
				markDirty();
				if ( el.getAttribute('data-clefa-field-key') === 'label' ) {
					const item = document.querySelector('.clefa-field-item[data-clefa-field-id="' + field.field_id + '"] .clefa-field-item-label');
					if ( item ) { item.textContent = el.value || field.field_type; }
				}
			});
			el.addEventListener('change', e => {
				updateFieldKeyValue( field.field_id, el.getAttribute('data-clefa-field-key'), el.tagName === 'SELECT' ? el.value : ( el.type === 'checkbox' ? el.checked : el.value ) );
				markDirty();
			});
		});

		// Repeater sub-field inline inputs
		sections.querySelectorAll( '[data-clefa-subfield-key]' ).forEach( el => {
			const handler = () => {
				syncRepeaterSubFields( field.field_id, sections );
				markDirty();
			};
			el.addEventListener( 'input', handler );
			el.addEventListener( 'change', handler );
		} );
	}

	function bindStepPanelEvents( sections, step ) {
		sections.querySelectorAll('[data-clefa-step-key]').forEach( el => {
			el.addEventListener('input', () => { updateStepKeyValue( step.step_id, el.getAttribute('data-clefa-step-key'), el.value ); markDirty(); });
			el.addEventListener('change', () => { updateStepKeyValue( step.step_id, el.getAttribute('data-clefa-step-key'), el.value ); markDirty(); });
		});
	}

	/* =============================================
	   State mutations
	   ============================================= */

	function findField( fieldId ) {
		for ( const step of state.form.steps ) {
			const field = ( step.fields || [] ).find( f => f.field_id === fieldId );
			if ( field ) { return { field, step }; }
		}
		return null;
	}

	function updateFieldKeyValue( fieldId, keyPath, value ) {
		const result = findField( fieldId );
		if ( ! result ) { return; }
		setNestedValue( result.field, keyPath, value );
	}

	function updateStepKeyValue( stepId, key, value ) {
		const step = state.form.steps.find( s => s.step_id === stepId );
		if ( step ) { step[ key ] = value; }
	}

	function updateActionConfigKey( actionId, keyPath, value ) {
		const action = state.form.actions.find( a => a.action_id === actionId );
		if ( ! action ) { return; }
		const cleanKey = keyPath.replace('action-config-','');
		setNestedValue( action.config || (action.config = {}), cleanKey, value );
		if ( cleanKey === 'label' ) { action.label = value; }
	}

	function updateNotifKey( notifId, key, value ) {
		const notif = state.form.notifications.find( n => n.notification_id === notifId );
		if ( notif ) { notif[ key ] = value; }
	}

	function setNestedValue( obj, keyPath, value ) {
		const parts = keyPath.split('.');
		let cur = obj;
		for ( let i = 0; i < parts.length - 1; i++ ) {
			if ( ! cur[ parts[i] ] ) { cur[ parts[i] ] = {}; }
			cur = cur[ parts[i] ];
		}
		cur[ parts[ parts.length - 1 ] ] = value;
	}

	function updateFieldOptions( el ) {
		const list    = el.closest('[data-clefa-role="options-list"]');
		if ( ! list ) { return; }
		const fieldId = list.getAttribute('data-clefa-field-id');
		const result  = findField( fieldId );
		if ( ! result ) { return; }
		const rows  = list.querySelectorAll('.clefa-option-row');
		const opts  = [];
		rows.forEach( row => {
			const valEl   = row.querySelector('[data-clefa-option-value]');
			const labelEl = row.querySelector('[data-clefa-option-label]');
			opts.push({ value: valEl ? valEl.value : '', label: labelEl ? labelEl.value : '' });
		});
		result.field.options = opts;
	}

	/* ---- Step actions ---- */
	function addStep() {
		const num  = state.form.steps.length + 1;
		const step = makeStep( 'step_' + num + '_' + uid(), 'Step ' + num );
		state.form.steps.push( step );
		markDirty();
		renderCanvas();
	}

	function deleteStep( stepId ) {
		if ( state.form.steps.length <= 1 ) { alert('A form must have at least one step.'); return; }
		if ( ! confirm('Delete this step and all its fields?') ) { return; }
		state.form.steps = state.form.steps.filter( s => s.step_id !== stepId );
		markDirty();
		renderCanvas();
	}

	function duplicateStep( stepId ) {
		const idx  = state.form.steps.findIndex( s => s.step_id === stepId );
		if ( idx < 0 ) { return; }
		const copy = JSON.parse( JSON.stringify( state.form.steps[ idx ] ) );
		copy.step_id   = uid();
		copy.step_name = copy.step_name + ' (Copy)';
		copy.fields    = copy.fields.map( f => ({ ...f, field_id: uid() }) );
		state.form.steps.splice( idx + 1, 0, copy );
		markDirty();
		renderCanvas();
	}

	function toggleStepCollapse( stepId ) {
		const key = 'step_' + stepId;
		state.ui.openAccordions[ key ] = state.ui.openAccordions[ key ] === false ? true : false;
		const block = document.querySelector('[data-clefa-step-id="' + stepId + '"].clefa-step-block');
		if ( block ) {
			if ( state.ui.openAccordions[ key ] === false ) {
				block.setAttribute('data-clefa-collapsed','true');
			} else {
				block.removeAttribute('data-clefa-collapsed');
			}
		}
	}

	/* ---- Field actions ---- */
	function addFieldToStep( stepId, field ) {
		const step = state.form.steps.find( s => s.step_id === stepId );
		if ( ! step ) { return; }
		step.fields = step.fields || [];
		step.fields.push( field );
		markDirty();
		const canvas = document.querySelector('[data-clefa-role="fields-canvas"][data-clefa-step-id="' + stepId + '"]');
		if ( canvas ) {
			canvas.innerHTML = renderFieldItems( step.fields );
			const stepBlock = canvas.closest('.clefa-step-block');
			if ( stepBlock ) { bindFieldItemEvents( stepBlock ); }
			bindFieldCanvasDrop( canvas );
		}
		openFieldPanel( field.field_id );
	}

	function deleteField( fieldId ) {
		if ( ! fieldId ) { return; }
		// Protect locked (core template) fields from deletion.
		const result = findField( fieldId );
		if ( result && result.field.locked ) {
			alert( i18n.lockedField || 'This is a core field and cannot be deleted. You can hide it using conditions.' );
			return;
		}
		if ( ! confirm( i18n.confirmDelete || 'Delete this field?' ) ) { return; }
		state.form.steps.forEach( step => {
			step.fields = ( step.fields || [] ).filter( f => f.field_id !== fieldId );
		});
		markDirty();
		if ( state.ui.selectedItem && state.ui.selectedItem.id === fieldId ) { closePanel(); }
		renderCanvas();
	}

	function duplicateField( fieldId ) {
		state.form.steps.forEach( step => {
			const idx = ( step.fields || [] ).findIndex( f => f.field_id === fieldId );
			if ( idx >= 0 ) {
				const copy = JSON.parse( JSON.stringify( step.fields[ idx ] ) );
				copy.field_id = uid();
				copy.label    = ( copy.label || '' ) + ' (Copy)';
				step.fields.splice( idx + 1, 0, copy );
			}
		});
		markDirty();
		renderCanvas();
	}

	function moveField( fieldId, fromStepId, toStepId ) {
		let field = null;
		const fromStep = state.form.steps.find( s => s.step_id === fromStepId );
		if ( fromStep ) {
			const idx = ( fromStep.fields || [] ).findIndex( f => f.field_id === fieldId );
			if ( idx >= 0 ) { field = fromStep.fields.splice( idx, 1 )[0]; }
		}
		if ( field ) {
			const toStep = state.form.steps.find( s => s.step_id === toStepId );
			if ( toStep ) { ( toStep.fields = toStep.fields || [] ).push( field ); }
		}
		markDirty();
		renderCanvas();
	}

	function openAddFieldToStep( stepId ) {
		const type = prompt('Field type? (text, email, password, textarea, select, checkbox, radio, date, file, hidden, html, notice, range, repeater)');
		if ( type && type.trim() ) {
			addFieldToStep( stepId, makeField( type.trim().toLowerCase() ) );
		}
	}

	/* ---- Conditions ---- */
	function addConditionToField( fieldId, bodyEl ) {
		const result = findField( fieldId );
		if ( ! result ) { return; }
		const newCond = {
			source_field:  bodyEl.querySelector('[data-clefa-new-condition-key="source_field"]')?.value || '',
			operator:      bodyEl.querySelector('[data-clefa-new-condition-key="operator"]')?.value || 'equals',
			compare_value: bodyEl.querySelector('[data-clefa-new-condition-key="compare_value"]')?.value || '',
			action:        bodyEl.querySelector('[data-clefa-new-condition-key="action"]')?.value || 'show',
			action_value:  bodyEl.querySelector('[data-clefa-new-condition-key="action_value"]')?.value || '',
			logic_group:   'AND',
		};
		if ( ! newCond.source_field ) { alert('Select a source field first.'); return; }
		result.field.conditions = result.field.conditions || [];
		result.field.conditions.push( newCond );
		markDirty();
		openFieldPanel( fieldId );
	}

	function deleteConditionFromField( fieldId, index ) {
		const result = findField( fieldId );
		if ( ! result ) { return; }
		result.field.conditions.splice( index, 1 );
		markDirty();
		openFieldPanel( fieldId );
	}

	/* ---- Options ---- */
	function addOptionToField( fieldId ) {
		const result = findField( fieldId );
		if ( ! result ) { return; }
		result.field.options = result.field.options || [];
		result.field.options.push({ label: 'Option ' + ( result.field.options.length + 1 ), value: '' });
		markDirty();
		openFieldPanel( fieldId );
	}

	function addRepeaterSubField( fieldId ) {
		const result = findField( fieldId );
		if ( ! result ) { return; }
		result.field.sub_fields = result.field.sub_fields || [];
		result.field.sub_fields.push( makeSubField( 'text' ) );
		markDirty();
		openFieldPanel( fieldId );
	}

	function deleteRepeaterSubField( fieldId, index ) {
		const result = findField( fieldId );
		if ( ! result ) { return; }
		if ( ! result.field.sub_fields ) { return; }
		result.field.sub_fields.splice( index, 1 );
		markDirty();
		openFieldPanel( fieldId );
	}

	function syncRepeaterSubFields( fieldId, sections ) {
		const result = findField( fieldId );
		if ( ! result ) { return; }
		const rows = sections.querySelectorAll( '[data-clefa-subfield-index]' );
		const byIndex = {};
		rows.forEach( el => {
			const idx = parseInt( el.getAttribute('data-clefa-subfield-index'), 10 );
			const key = el.getAttribute('data-clefa-subfield-key');
			if ( isNaN(idx) || ! key ) { return; }
			if ( ! byIndex[ idx ] ) { byIndex[ idx ] = {}; }
			byIndex[ idx ][ key ] = el.value;
		} );
		Object.keys( byIndex ).forEach( idx => {
			const sf = ( result.field.sub_fields || [] )[ idx ];
			if ( sf ) { Object.assign( sf, byIndex[ idx ] ); }
		} );
	}

	function deleteOption( btn ) {
		const row = btn.closest('.clefa-option-row');
		if ( ! row ) { return; }
		const list = row.closest('[data-clefa-role="options-list"]');
		if ( ! list ) { return; }
		const fieldId = list.getAttribute('data-clefa-field-id');
		updateFieldOptions( btn );
		const result = findField( fieldId );
		if ( ! result ) { return; }
		const idx = parseInt( row.getAttribute('data-clefa-option-index'), 10 );
		if ( ! isNaN(idx) ) { result.field.options.splice(idx, 1); }
		markDirty();
		row.remove();
	}

	/* ---- Actions ---- */
	function openActionPicker() {
		const modal = document.querySelector('[data-clefa-role="action-picker-modal"]');
		if ( ! modal ) { return; }
		const grid = modal.querySelector('[data-clefa-role="action-picker-grid"]');
		if ( grid ) {
			let html = '';
			actionDefs.forEach( def => {
				const available = isDefinitionAvailable( def );
				const reason    = getDisabledReason( def );
				html += `<button type="button" class="clefa-action-picker-item"`
					+ ` data-clefa-action-type="${esc(def.type)}"`
					+ ` data-clefa-available="${available ? 'true' : 'false'}"`
					+ ( available ? '' : ` data-clefa-disabled="true" disabled title="${esc(reason)}"` )
					+ `>`;
				html += `<span class="dashicons ${esc(def.icon)}"></span>`;
				html += `<span class="clefa-action-picker-label">${esc(def.label)}</span>`;
				if ( ! available ) {
					html += `<span class="clefa-dep-badge">${esc(reason)}</span>`;
				}
				html += `</button>`;
			});
			grid.innerHTML = html;
		}
		modal.setAttribute('data-clefa-open','true');
	}

	function closeModal() {
		document.querySelectorAll('.clefa-modal').forEach( m => m.removeAttribute('data-clefa-open') );
	}

	function addAction( type ) {
		if ( ! type ) { return; }
		const def = getActionDef( type );
		if ( ! isDefinitionAvailable( def ) ) { return; }
		const action = {
			action_id:   uid(),
			action_type: type,
			label:       def.label,
			enabled:     true,
			order:       state.form.actions.length,
			conditions:  [],
			config:      {},
		};
		state.form.actions.push( action );
		markDirty();
		closeModal();
		switchTab('actions');
		renderActionsTab();
	}

	function deleteAction( actionId ) {
		if ( ! confirm('Remove this action?') ) { return; }
		state.form.actions = state.form.actions.filter( a => a.action_id !== actionId );
		markDirty();
		renderActionsTab();
	}

	function toggleActionItem( actionId ) {
		const key = 'action_' + actionId;
		state.ui.openAccordions[ key ] = ! state.ui.openAccordions[ key ];
		const item = document.querySelector('.clefa-action-item[data-clefa-action-id="' + actionId + '"]');
		if ( item ) { item.setAttribute('data-clefa-open', state.ui.openAccordions[ key ] ? 'true' : 'false'); }
	}

	/* ---- Notifications ---- */
	function addNotification() {
		const notif = {
			notification_id: uid(),
			label:           'New Notification',
			enabled:         true,
			to:              '{admin_email}',
			cc:              '',
			bcc:             '',
			subject:         'New form submission',
			message:         '',
			conditions:      [],
		};
		state.form.notifications.push( notif );
		markDirty();
		renderNotificationsTab();
	}

	function deleteNotification( notifId ) {
		if ( ! confirm('Remove this notification?') ) { return; }
		state.form.notifications = state.form.notifications.filter( n => n.notification_id !== notifId );
		markDirty();
		renderNotificationsTab();
	}

	function toggleNotifItem( notifId ) {
		const key = 'notif_' + notifId;
		state.ui.openAccordions[ key ] = ! state.ui.openAccordions[ key ];
		const item = document.querySelector('.clefa-notification-item[data-clefa-notif-id="' + notifId + '"]');
		if ( item ) { item.setAttribute('data-clefa-open', state.ui.openAccordions[key] ? 'true' : 'false'); }
	}

	/* ---- Accordion ---- */
	function toggleAccordion( key ) {
		if ( ! key ) { return; }
		const section = document.querySelector('[data-clefa-section="' + key + '"]');
		if ( section ) {
			const isOpen = section.getAttribute('data-clefa-open') === 'true';
			section.setAttribute('data-clefa-open', isOpen ? 'false' : 'true');
			state.ui.openAccordions[ 'panel_' + key ] = !isOpen;
		} else {
			const btn = document.querySelector('[data-clefa-accordion="' + key + '"]');
			if ( btn ) {
				const section2 = btn.closest('.clefa-accordion-section');
				if ( section2 ) {
					const isOpen = section2.getAttribute('data-clefa-open') === 'true';
					section2.setAttribute('data-clefa-open', isOpen ? 'false' : 'true');
				}
			}
		}
	}

	/* ---- Tab switching ---- */
	function switchTab( tab ) {
		state.ui.activeTab = tab;
		document.querySelectorAll('[data-clefa-tab]').forEach( btn => {
			btn.classList.toggle('clefa-tab-active', btn.getAttribute('data-clefa-tab') === tab );
		});
		document.querySelectorAll('[data-clefa-panel]').forEach( panel => {
			panel.classList.toggle('clefa-tab-panel-active', panel.getAttribute('data-clefa-panel') === tab );
		});
		if ( tab === 'actions' )       { renderActionsTab(); }
		if ( tab === 'notifications' ) { renderNotificationsTab(); }
		if ( tab === 'settings' )      { renderSettingsCanvasTab(); }
		if ( tab === 'submissions' )   { loadInlineSubmissions(); }
	}

	/* ---- Simulate ---- */
	function openSimulate() {
		const modal = document.querySelector('[data-clefa-role="simulate-modal"]');
		if ( ! modal ) { return; }
		modal.setAttribute('data-clefa-open','true');
		document.body.style.overflow = 'hidden';
		const preview = modal.querySelector('[data-clefa-role="form-preview"]');
		if ( ! preview ) { return; }
		preview.innerHTML = '<p style="text-align:center;padding:40px;color:var(--clefa-text-muted)">Loading preview…</p>';

		fetch( clefaBuilderData.restUrl + '/forms/' + ( hasPersistedFormId() ? state.formId : 0 ) + '/preview', {
			method  : 'POST',
			headers : { 'Content-Type': 'application/json', 'X-WP-Nonce': clefaBuilderData.nonce },
			body    : JSON.stringify({ config: {
				form_name:     state.form.form_name,
				form_type:     state.form.form_type,
				description:   state.form.description,
				steps:         state.form.steps,
				settings:      state.form.settings,
				notifications: state.form.notifications,
				actions:       state.form.actions,
			}}),
		})
		.then( r => r.json() )
		.then( data => {
			if ( data.html ) {
				preview.innerHTML = data.html;
				// Inject frontend scripts if not yet loaded
				if ( window.CLEFAFormEngine ) { window.CLEFAFormEngine.initAll(); }
			} else {
				preview.innerHTML = renderSimulatePreview();
			}
		})
		.catch( () => { preview.innerHTML = renderSimulatePreview(); } );
	}

	function closeSimulate() {
		const modal = document.querySelector('[data-clefa-role="simulate-modal"]');
		if ( modal ) { modal.removeAttribute('data-clefa-open'); }
		document.body.style.overflow = '';
	}

	function setSimulateDevice( device ) {
		const wrap = document.querySelector('.clefa-simulate-frame-wrap');
		if ( wrap ) { wrap.setAttribute('data-clefa-device', device); }
		document.querySelectorAll('.clefa-simulate-device').forEach( btn => {
			btn.classList.toggle('clefa-simulate-device-active', btn.getAttribute('data-clefa-device') === device );
		});
	}

	function renderSimulatePreview() {
		const form = state.form;
		let html = '<div data-clefa-form-preview="1">';
		html += '<h2 style="margin-top:0;margin-bottom:20px;">' + esc(form.form_name || 'Form Preview') + '</h2>';

		( form.steps || [] ).forEach( (step, idx) => {
			if ( idx > 0 ) { return; }
			if ( step.step_heading ) { html += '<h3>' + esc(step.step_heading) + '</h3>'; }
			if ( step.step_description ) { html += '<p style="color:var(--clefa-text-muted);">' + esc(step.step_description) + '</p>'; }

			( step.fields || [] ).forEach( field => {
				if ( field.hidden ) { return; }
				html += '<div style="margin-bottom:16px;" data-clefa-field="' + esc(field.field_id) + '">';
				if ( field.label ) {
					html += '<label style="display:block;font-weight:500;margin-bottom:5px;">' + esc(field.label) + ( field.required ? ' <span style="color:#dc2626;">*</span>' : '' ) + '</label>';
				}
				html += renderSimulateInput( field );
				if ( field.description ) { html += '<p style="font-size:.8rem;color:var(--clefa-text-muted);margin-top:4px;">' + esc(field.description) + '</p>'; }
				html += '</div>';
			});

			html += '<div style="margin-top:20px;">';
			if ( form.steps.length > 1 ) {
				html += '<button type="button" style="padding:10px 24px;background:var(--clefa-primary);color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:.9375rem;" disabled>' + esc(step.next_button_text || 'Next →') + '</button>';
			} else {
				html += '<button type="button" style="padding:10px 24px;background:var(--clefa-primary);color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:.9375rem;">' + esc(step.submit_button_text || 'Submit') + '</button>';
			}
			html += '</div>';
		});

		if ( form.steps.length > 1 ) {
			html += '<p style="color:var(--clefa-text-muted);font-size:.8rem;margin-top:12px;">This form has ' + form.steps.length + ' steps. Only Step 1 shown in preview.</p>';
		}

		html += '</div>';
		return html;
	}

	function renderSimulateInput( field ) {
		const style = 'width:100%;padding:8px 10px;border:1px solid #e2e8f0;border-radius:6px;font-size:.9375rem;box-sizing:border-box;';
		const ph    = esc( field.placeholder || '' );
		switch ( field.field_type ) {
			case 'textarea':
				return `<textarea placeholder="${ph}" style="${style}min-height:80px;" rows="3"></textarea>`;
			case 'select':
			case 'select2': {
				let opts = '<option value="">Select...</option>';
				( field.options || [] ).forEach( o => { opts += '<option value="' + esc(o.value||o) + '">' + esc(o.label||o) + '</option>'; });
				return `<select style="${style}">${opts}</select>`;
			}
			case 'checkbox': {
				if ( ! field.options || ! field.options.length ) { return `<input type="checkbox" />`; }
				return ( field.options || [] ).map( o => `<label style="display:flex;align-items:center;gap:6px;margin-bottom:4px;"><input type="checkbox" />${esc(o.label||o)}</label>` ).join('');
			}
			case 'radio':
				return ( field.options || [] ).map( o => `<label style="display:flex;align-items:center;gap:6px;margin-bottom:4px;"><input type="radio" name="${esc(field.field_id)}" />${esc(o.label||o)}</label>` ).join('');
			case 'date':
				return `<input type="date" style="${style}" />`;
			case 'number':
			case 'range':
				return `<input type="number" placeholder="${ph}" style="${style}" />`;
			case 'file':
			case 'multi_file':
				return `<input type="file" style="display:block;" />`;
			case 'password':
				return `<input type="password" placeholder="${ph}" style="${style}" />`;
			case 'email':
				return `<input type="email" placeholder="${ph}" style="${style}" />`;
			case 'url':
				return `<input type="url" placeholder="${ph}" style="${style}" />`;
			case 'html':
				return `<div style="background:var(--clefa-surface-alt);border:1px dashed var(--clefa-border);padding:12px;border-radius:6px;font-size:.8rem;color:var(--clefa-text-muted);">[HTML Block: ${esc(field.label)}]</div>`;
			case 'notice':
				return `<div style="background:#fef3c7;border-left:4px solid #d97706;padding:10px 14px;border-radius:0 6px 6px 0;font-size:.875rem;">${esc(field.label)}</div>`;
			case 'grid_break':
			case 'heading':
				return `<hr style="border:none;border-top:1px solid var(--clefa-border);margin:8px 0;" />`;
			case 'hidden':
				return `<span style="color:var(--clefa-text-muted);font-size:.8rem;font-style:italic;">[Hidden field]</span>`;
			default:
				return `<input type="text" placeholder="${ph}" style="${style}" />`;
		}
	}

	/* ---- Inline submissions ---- */
	function loadInlineSubmissions() {
		if ( ! hasPersistedFormId() ) { return; }
		const container = document.querySelector('[data-clefa-role="inline-submissions"]');
		if ( ! container ) { return; }
		container.innerHTML = '<p style="color:var(--clefa-text-muted);">Loading...</p>';

		fetch( clefaBuilderData.restUrl + '/submissions?form_id=' + state.formId, {
			headers: { 'X-WP-Nonce': clefaBuilderData.nonce }
		})
		.then( r => r.json() )
		.then( data => {
			if ( ! data.submissions || ! data.submissions.length ) {
				container.innerHTML = '<p style="color:var(--clefa-text-muted);">No submissions yet.</p>';
				return;
			}
			let html = '<table style="width:100%;border-collapse:collapse;font-size:.875rem;">';
			html += '<tr><th style="text-align:left;padding:6px 8px;border-bottom:1px solid var(--clefa-border);">ID</th><th style="text-align:left;padding:6px 8px;border-bottom:1px solid var(--clefa-border);">Status</th><th style="text-align:left;padding:6px 8px;border-bottom:1px solid var(--clefa-border);">Date</th></tr>';
			data.submissions.forEach( sub => {
				html += `<tr><td style="padding:6px 8px;">${esc(sub.id)}</td><td style="padding:6px 8px;">${esc(sub.status)}</td><td style="padding:6px 8px;">${esc(sub.created_at||'')}</td></tr>`;
			});
			html += '</table>';
			container.innerHTML = html;
		})
		.catch( () => { container.innerHTML = '<p style="color:var(--clefa-danger);">Could not load submissions.</p>'; });
	}

	/* ---- Publish ---- */
	function publishForm() {
		if ( ! hasPersistedFormId() ) { saveForm( true ); return; }
		fetch( clefaBuilderData.restUrl + '/forms/' + state.formId + '/publish', {
			method: 'POST',
			headers: { 'X-WP-Nonce': clefaBuilderData.nonce }
		})
		.then( r => r.json() )
		.then( data => {
			const btn = document.querySelector('[data-clefa-action="publish-form"]');
			if ( btn && data.status ) {
				const isNowPublished = data.status === 'published';
				btn.setAttribute('data-clefa-published', isNowPublished ? '1' : '0');
				btn.className = 'clefa-btn clefa-btn-sm ' + ( isNowPublished ? 'clefa-btn-warning' : 'clefa-btn-outline' );
				btn.querySelector('.dashicons').className = 'dashicons ' + ( isNowPublished ? 'dashicons-hidden' : 'dashicons-visibility' );
				btn.lastChild.textContent = isNowPublished ? ' Unpublish' : ' Publish';
				showToast( isNowPublished ? 'Form published' : 'Form unpublished', 'success' );
			}
		})
		.catch( () => showToast('Publish failed', 'error') );
	}

	function getRestErrorMessage( data, fallback ) {
		if ( ! data || typeof data !== 'object' ) {
			return fallback;
		}
		return data.message || ( data.data && data.data.message ) || fallback;
	}

	function finishSaveUi( btn ) {
		state.saving = false;
		if ( btn ) {
			btn.innerHTML = '<span class="dashicons dashicons-saved"></span> Save';
			btn.disabled = false;
		}
	}
	/* ---- Save ---- */
	function saveForm( thenPublish ) {
		if ( state.saving ) { return; }
		state.saving = true;
		const btn = document.querySelector('[data-clefa-action="save-form"]');
		if ( btn ) { btn.textContent = i18n.saving || 'Saving...'; btn.disabled = true; }
		showToast( i18n.saving || 'Saving...', 'saving' );

		const payload = {
			form_name: state.form.form_name,
			config: {
				form_name:     state.form.form_name,
				form_type:     state.form.form_type,
				description:   state.form.description,
				steps:         state.form.steps,
				settings:      state.form.settings,
				notifications: state.form.notifications,
				actions:       state.form.actions,
			},
		};

		const isNew  = ! hasPersistedFormId();
		const url    = isNew
			? clefaBuilderData.restUrl + '/forms'
			: clefaBuilderData.restUrl + '/forms/' + state.formId;
		const method = isNew ? 'POST' : 'PUT';

		if ( isNew ) {
			payload.form_name = state.form.form_name;
			payload.form_type = state.form.form_type;
		}

		fetch( url, {
			method,
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': clefaBuilderData.nonce },
			body: JSON.stringify( payload ),
		})
		.then( r => r.json().then( data => ( { ok: r.ok, status: r.status, data } ) ) )
		.then( result => {
			const data = result.data || {};
			finishSaveUi( btn );

			if ( data.success ) {
				const newFormId = data.form_id || ( data.form && data.form.id ? Number( data.form.id ) : 0 );
				if ( isNew && newFormId ) {
					state.formId = newFormId;
					const newUrl = new URL( window.location.href );
					newUrl.searchParams.set('form_id', newFormId);
					history.replaceState( null, '', newUrl.toString() );
				}
				markClean();
				showToast( i18n.saved || 'Saved', 'success' );
				if ( thenPublish ) { publishForm(); }
				return;
			}

			showToast( getRestErrorMessage( data, i18n.saveError || 'Save failed' ), 'error' );
		})
		.catch( () => {
			finishSaveUi( btn );
			showToast( i18n.saveError || 'Save failed', 'error' );
		});
	}

	/* ---- Keyboard shortcuts ---- */
	document.addEventListener('keydown', e => {
		if ( (e.ctrlKey || e.metaKey) && e.key === 's' ) {
			e.preventDefault();
			saveForm();
		}
		if ( e.key === 'Escape' ) {
			closeSimulate();
			closeModal();
		}
	});

	/* ---- Unload warning ---- */
	window.addEventListener('beforeunload', e => {
		if ( state.isDirty ) {
			e.preventDefault();
			e.returnValue = i18n.unsavedChanges || 'You have unsaved changes.';
		}
	});

	/* ---- Testing API (Dev Hub JS test runner) ---- */
	window.CLEFA = window.CLEFA || {};
	window.CLEFA.BuilderTest = {
		uid: uid,
		makeStep: makeStep,
		makeField: makeField,
		esc: esc,
		setNestedValue: setNestedValue,
		isDefinitionAvailable: isDefinitionAvailable,
		getDisabledReason: getDisabledReason,
		getFieldLabel: getFieldLabel,
		getFieldIcon: getFieldIcon,
		getActionDef: getActionDef,
		markDirty: markDirty,
		markClean: markClean,
		getState: function () { return state; },
		render: render,
		findField: findField,
		addFieldToStep: addFieldToStep,
		deleteField: deleteField,
		addStep: addStep,
		deleteStep: deleteStep,
	};

	/* ---- Boot ---- */
	if ( ! window.CLEFA_TESTING ) {
		document.addEventListener('DOMContentLoaded', () => {
			render();
			bindAll();
		});

		if ( document.readyState !== 'loading' ) {
			render();
			bindAll();
		}
	}

})();
