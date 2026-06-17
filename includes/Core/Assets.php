<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CLEFA_Assets {

	private static $builder_pages = array( 'clefa-edit-form', 'clefa-forms', 'clefa-submissions', 'clefa-settings', 'clefa-dev', 'clefa-tests', 'clefa-logs' );

	public function enqueue_admin_assets( $hook ) {
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

		if ( ! in_array( $page, self::$builder_pages, true ) ) {
			return;
		}

		wp_enqueue_style(
			'clefa-admin-global',
			CLEFA_ASSET_URL . 'admin/css/admin-global.css',
			array(),
			CLEFA_PLUGIN_VERSION
		);

		if ( 'clefa-edit-form' === $page ) {
			wp_enqueue_style(
				'clefa-admin-builder',
				CLEFA_ASSET_URL . 'admin/css/admin-builder.css',
				array( 'clefa-admin-global' ),
				CLEFA_PLUGIN_VERSION
			);

			wp_enqueue_script(
				'clefa-admin-builder',
				CLEFA_ASSET_URL . 'admin/js/admin-builder.js',
				array( 'wp-util', 'jquery' ),
				CLEFA_PLUGIN_VERSION,
				true
			);

			$form_id     = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0;
			$form_config = array();
			if ( $form_id > 0 ) {
				$form_config = CLEFA_Tables::get_form( $form_id );
				if ( ! $form_config ) {
					$form_id = 0;
				}
			}

			wp_localize_script(
				'clefa-admin-builder',
				'clefaBuilderData',
				array(
					'restUrl'         => esc_url_raw( rest_url( 'clefa/v1' ) ),
					'nonce'           => wp_create_nonce( 'wp_rest' ),
					'formConfig'      => $form_config ? $form_config : null,
					'fieldTypes'      => self::get_field_type_definitions(),
					'actionTypes'     => self::get_action_type_definitions(),
					'dependencies'    => CLEFA_Plugin_Dependencies::get_status(),
					'validationRules' => CLEFA_Validation_Registry::get_builder_schema(),
					'wpRoles'         => self::get_wp_roles(),
					'i18n'            => self::get_builder_i18n(),
				)
			);
		} elseif ( in_array( $page, array( 'clefa-forms', 'clefa-submissions' ), true ) ) {
			wp_enqueue_script(
				'clefa-admin-forms',
				CLEFA_ASSET_URL . 'admin/js/admin-forms.js',
				array( 'jquery' ),
				CLEFA_PLUGIN_VERSION,
				true
			);
			wp_localize_script(
				'clefa-admin-forms',
				'clefaAdminData',
				array(
					'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
					'restUrl'  => esc_url_raw( rest_url( 'clefa/v1' ) ),
					'adminUrl' => esc_url_raw( admin_url() ),
					'nonce'    => wp_create_nonce( 'wp_rest' ),
					'i18n'     => array(
						'confirmDelete'    => __( 'Are you sure you want to delete this form? This cannot be undone.', 'codelinden-elementor-form-addon' ),
						'confirmDuplicate' => __( 'Duplicate this form?', 'codelinden-elementor-form-addon' ),
						'deleting'         => __( 'Deleting...', 'codelinden-elementor-form-addon' ),
						'duplicating'      => __( 'Duplicating...', 'codelinden-elementor-form-addon' ),
					),
				)
			);
		} elseif ( in_array( $page, array( 'clefa-dev', 'clefa-tests' ), true ) ) {
			$tab = isset( $_GET['tab'] ) // phpcs:ignore WordPress.Security.NonceVerification
				? sanitize_key( wp_unslash( $_GET['tab'] ) ) // phpcs:ignore WordPress.Security.NonceVerification
				: ( 'clefa-tests' === $page ? 'integration' : 'suites' );

			if ( 'integration' === $tab ) {
				wp_enqueue_script(
					'clefa-admin-tests',
					CLEFA_ASSET_URL . 'admin/js/admin-tests.js',
					array(),
					CLEFA_PLUGIN_VERSION,
					true
				);
			}

			if ( 'clefa-dev' === $page && 'suites' === $tab ) {
				wp_enqueue_script(
					'clefa-admin-js-suite',
					CLEFA_ASSET_URL . 'admin/js/admin-js-suite.js',
					array(),
					CLEFA_PLUGIN_VERSION,
					true
				);
			}
		}
	}

	public function enqueue_frontend_assets() {
		// Always register scripts/styles so Elementor's get_script_depends() /
		// get_style_depends() can enqueue them even when our page-detection
		// heuristic misses the page (e.g. form lives in a Theme Builder template).
		$this->register_frontend_assets();

		global $post;
		if ( ! $post ) {
			return;
		}

		$has_shortcode  = has_shortcode( $post->post_content, 'clefa_form' );
		$has_widget     = $this->page_has_elementor_form_widget();
		$elementor_page = function_exists( 'elementor_load_plugin_textdomain' )
			&& 'builder' === get_post_meta( $post->ID, '_elementor_edit_mode', true );

		if ( ! $has_shortcode && ! $has_widget && ! $elementor_page ) {
			return;
		}

		// Enqueue now that we know a form is on this page.
		wp_enqueue_style( 'clefa-form-engine' );
		wp_enqueue_script( 'clefa-transition-engine' );
		wp_enqueue_script( 'clefa-event-dispatcher' );
		wp_enqueue_script( 'clefa-condition-engine' );
		wp_enqueue_script( 'clefa-validation-engine' );
		wp_enqueue_script( 'clefa-form-engine' );
	}

	/**
	 * Register (but do not enqueue) all frontend scripts and styles.
	 * Safe to call multiple times — runs only once per request.
	 * Called unconditionally from enqueue_frontend_assets() so that
	 * the handles exist for Elementor's get_script_depends() mechanism.
	 */
	public function register_frontend_assets() {
		static $registered = false;
		if ( $registered ) {
			return;
		}
		$registered = true;

		wp_register_style(
			'clefa-form-engine',
			CLEFA_ASSET_URL . 'frontend/css/form-engine.css',
			array(),
			CLEFA_PLUGIN_VERSION
		);

		wp_register_script(
			'clefa-transition-engine',
			CLEFA_ASSET_URL . 'frontend/js/TransitionEngine.js',
			array(),
			CLEFA_PLUGIN_VERSION,
			true
		);
		wp_register_script(
			'clefa-event-dispatcher',
			CLEFA_ASSET_URL . 'frontend/js/EventDispatcher.js',
			array(),
			CLEFA_PLUGIN_VERSION,
			true
		);
		wp_register_script(
			'clefa-condition-engine',
			CLEFA_ASSET_URL . 'frontend/js/ConditionEngine.js',
			array( 'clefa-event-dispatcher', 'clefa-transition-engine' ),
			CLEFA_PLUGIN_VERSION,
			true
		);
		wp_register_script(
			'clefa-validation-engine',
			CLEFA_ASSET_URL . 'frontend/js/ValidationEngine.js',
			array( 'clefa-event-dispatcher' ),
			CLEFA_PLUGIN_VERSION,
			true
		);
		wp_register_script(
			'clefa-form-engine',
			CLEFA_ASSET_URL . 'frontend/js/FormEngine.js',
			array( 'clefa-event-dispatcher', 'clefa-condition-engine', 'clefa-validation-engine' ),
			CLEFA_PLUGIN_VERSION,
			true
		);

		// Localize at registration time — safe to call on a registered-but-not-yet-enqueued
		// script; the data is stored and output when the script is eventually printed.
		wp_localize_script(
			'clefa-form-engine',
			'clefaFrontend',
			array(
				'restUrl'      => esc_url_raw( rest_url( 'clefa/v1' ) ),
				'nonce'        => wp_create_nonce( 'wp_rest' ),
				'refreshNonce' => (bool) CLEFA_Settings_Page::get( 'enable_nonce_refresh', true ),
				'debugEvents'  => (bool) CLEFA_Settings_Page::get( 'enable_debug_events', false ),
				'i18n'         => array(
					'checking'    => __( 'Checking...', 'codelinden-elementor-form-addon' ),
					'available'   => __( 'Available', 'codelinden-elementor-form-addon' ),
					'unavailable' => __( 'Not available', 'codelinden-elementor-form-addon' ),
				),
			)
		);

		// Conditional modules — registered here, enqueued by Form_Renderer based on feature_map
		wp_register_script(
			'clefa-step-router',
			CLEFA_ASSET_URL . 'frontend/js/StepRouter.js',
			array( 'clefa-event-dispatcher', 'clefa-transition-engine' ),
			CLEFA_PLUGIN_VERSION,
			true
		);
		wp_register_script(
			'clefa-upload-manager',
			CLEFA_ASSET_URL . 'frontend/js/UploadManager.js',
			array( 'clefa-event-dispatcher', 'clefa-form-engine' ),
			CLEFA_PLUGIN_VERSION,
			true
		);
		wp_register_script(
			'clefa-live-check',
			CLEFA_ASSET_URL . 'frontend/js/LiveCheckManager.js',
			array( 'clefa-event-dispatcher' ),
			CLEFA_PLUGIN_VERSION,
			true
		);

		// Select2 — only enqueued when a rendered form has select2 fields
		wp_register_style(
			'clefa-select2',
			apply_filters( 'clefa_select2_css_url', CLEFA_ASSET_URL . 'vendor/select2/select2.min.css' ),
			array(),
			'4.1.0'
		);
		wp_register_script(
			'clefa-select2',
			apply_filters( 'clefa_select2_js_url', CLEFA_ASSET_URL . 'vendor/select2/select2.full.min.js' ),
			array( 'jquery' ),
			'4.1.0',
			true
		);

		// Filter engine — enqueued on-demand by Filter_Widget::render
		wp_register_style(
			'clefa-filter-engine',
			CLEFA_ASSET_URL . 'frontend/css/filter-engine.css',
			array(),
			CLEFA_PLUGIN_VERSION
		);
		wp_register_script(
			'clefa-filter-engine',
			CLEFA_ASSET_URL . 'frontend/js/FilterEngine.js',
			array(),
			CLEFA_PLUGIN_VERSION,
			true
		);
	}

	private function page_has_elementor_form_widget() {
		if ( ! function_exists( 'elementor_load_plugin_textdomain' ) ) {
			return false;
		}
		global $post;
		if ( ! $post ) {
			return false;
		}
		$elementor_data = get_post_meta( $post->ID, '_elementor_data', true );
		if ( empty( $elementor_data ) ) {
			return false;
		}
		return false !== strpos( $elementor_data, 'clefa-form' ) || false !== strpos( $elementor_data, 'clefa-filter' );
	}

	public static function get_field_type_definitions() {
		$groups = array(
			'basic' => array(
				'label'  => __( 'Basic Fields', 'codelinden-elementor-form-addon' ),
				'fields' => array(
					array( 'type' => 'text',     'label' => __( 'Text', 'codelinden-elementor-form-addon' ),     'icon' => 'dashicons-text' ),
					array( 'type' => 'email',    'label' => __( 'Email', 'codelinden-elementor-form-addon' ),    'icon' => 'dashicons-email' ),
					array( 'type' => 'password', 'label' => __( 'Password', 'codelinden-elementor-form-addon' ), 'icon' => 'dashicons-lock' ),
					array( 'type' => 'textarea', 'label' => __( 'Textarea', 'codelinden-elementor-form-addon' ), 'icon' => 'dashicons-editor-paragraph' ),
					array( 'type' => 'number',   'label' => __( 'Number', 'codelinden-elementor-form-addon' ),   'icon' => 'dashicons-sort' ),
					array( 'type' => 'url',      'label' => __( 'URL', 'codelinden-elementor-form-addon' ),      'icon' => 'dashicons-admin-site' ),
					array( 'type' => 'phone',    'label' => __( 'Phone', 'codelinden-elementor-form-addon' ),    'icon' => 'dashicons-phone' ),
				),
			),
			'choice' => array(
				'label'  => __( 'Choice Fields', 'codelinden-elementor-form-addon' ),
				'fields' => array(
					array( 'type' => 'select',   'label' => __( 'Select', 'codelinden-elementor-form-addon' ),        'icon' => 'dashicons-menu' ),
					array( 'type' => 'select2',  'label' => __( 'Select2', 'codelinden-elementor-form-addon' ),       'icon' => 'dashicons-search' ),
					array( 'type' => 'checkbox', 'label' => __( 'Checkbox Group', 'codelinden-elementor-form-addon' ), 'icon' => 'dashicons-yes-alt' ),
					array( 'type' => 'radio',    'label' => __( 'Radio Group', 'codelinden-elementor-form-addon' ),    'icon' => 'dashicons-marker' ),
				),
			),
			'date_media' => array(
				'label'  => __( 'Date & Media', 'codelinden-elementor-form-addon' ),
				'fields' => array(
					array( 'type' => 'date',       'label' => __( 'Date', 'codelinden-elementor-form-addon' ),             'icon' => 'dashicons-calendar-alt' ),
					array( 'type' => 'range',      'label' => __( 'Range Slider', 'codelinden-elementor-form-addon' ),      'icon' => 'dashicons-leftright' ),
					array( 'type' => 'file',       'label' => __( 'File Upload', 'codelinden-elementor-form-addon' ),       'icon' => 'dashicons-upload' ),
					array( 'type' => 'multi_file', 'label' => __( 'Multi-File Upload', 'codelinden-elementor-form-addon' ), 'icon' => 'dashicons-media-archive' ),
				),
			),
			'advanced' => array(
				'label'  => __( 'Advanced', 'codelinden-elementor-form-addon' ),
				'fields' => array(
					array( 'type' => 'hidden',   'label' => __( 'Hidden Field', 'codelinden-elementor-form-addon' ), 'icon' => 'dashicons-hidden' ),
					array( 'type' => 'html',     'label' => __( 'HTML Block', 'codelinden-elementor-form-addon' ),   'icon' => 'dashicons-editor-code' ),
					array( 'type' => 'notice',   'label' => __( 'Notice Block', 'codelinden-elementor-form-addon' ), 'icon' => 'dashicons-info' ),
					array( 'type' => 'confirm_password', 'label' => __( 'Confirm Password', 'codelinden-elementor-form-addon' ), 'icon' => 'dashicons-privacy' ),
				),
			),
			'structure' => array(
				'label'  => __( 'Structure', 'codelinden-elementor-form-addon' ),
				'fields' => array(
					// Native repeater — does not require ACF; maps to ACF Repeater via actions when ACF Pro is active.
					array( 'type' => 'repeater',   'label' => __( 'Repeater', 'codelinden-elementor-form-addon' ),          'icon' => 'dashicons-controls-repeat' ),
					array( 'type' => 'grid_break', 'label' => __( 'Grid / Column Break', 'codelinden-elementor-form-addon' ), 'icon' => 'dashicons-grid-view' ),
					array( 'type' => 'heading',    'label' => __( 'Section Heading', 'codelinden-elementor-form-addon' ),    'icon' => 'dashicons-heading' ),
				),
			),
		);

		return self::enrich_field_groups( $groups );
	}

	public static function get_action_type_definitions() {
		$actions = array(
			array( 'type' => 'save_submission',    'label' => __( 'Save Submission', 'codelinden-elementor-form-addon' ),            'icon' => 'dashicons-database' ),
			array( 'type' => 'register_user',      'label' => __( 'Register User', 'codelinden-elementor-form-addon' ),              'icon' => 'dashicons-admin-users' ),
			array( 'type' => 'login_user',         'label' => __( 'Login User', 'codelinden-elementor-form-addon' ),                 'icon' => 'dashicons-lock' ),
			array( 'type' => 'lost_password',      'label' => __( 'Lost Password', 'codelinden-elementor-form-addon' ),              'icon' => 'dashicons-privacy' ),
			array( 'type' => 'set_user_role',      'label' => __( 'Set User Role', 'codelinden-elementor-form-addon' ),              'icon' => 'dashicons-shield' ),
			array( 'type' => 'update_user_meta',   'label' => __( 'Update User Meta', 'codelinden-elementor-form-addon' ),           'icon' => 'dashicons-id' ),
			array( 'type' => 'update_post_meta',   'label' => __( 'Update Post Meta', 'codelinden-elementor-form-addon' ),           'icon' => 'dashicons-admin-post' ),
			array( 'type' => 'update_post_title',  'label' => __( 'Update Post Title', 'codelinden-elementor-form-addon' ),          'icon' => 'dashicons-edit' ),
			array( 'type' => 'update_post_content','label' => __( 'Update Post Content', 'codelinden-elementor-form-addon' ),        'icon' => 'dashicons-editor-paragraph' ),
			array( 'type' => 'create_post',        'label' => __( 'Create Post', 'codelinden-elementor-form-addon' ),                'icon' => 'dashicons-plus-alt' ),
			array( 'type' => 'update_taxonomy',    'label' => __( 'Update Taxonomy Terms', 'codelinden-elementor-form-addon' ),      'icon' => 'dashicons-tag' ),
			array(
				'type'            => 'update_acf_field',
				'label'           => __( 'Update ACF Field', 'codelinden-elementor-form-addon' ),
				'icon'            => 'dashicons-forms',
				'requires'        => array( CLEFA_Plugin_Dependencies::DEP_ACF ),
				'disabled_reason' => CLEFA_Plugin_Dependencies::get_label( CLEFA_Plugin_Dependencies::DEP_ACF ),
			),
			array(
				'type'            => 'update_acf_repeater',
				'label'           => __( 'Update ACF Repeater', 'codelinden-elementor-form-addon' ),
				'icon'            => 'dashicons-controls-repeat',
				'requires'        => array( CLEFA_Plugin_Dependencies::DEP_ACF_PRO ),
				'disabled_reason' => CLEFA_Plugin_Dependencies::get_label( CLEFA_Plugin_Dependencies::DEP_ACF_PRO ),
			),
			array(
				'type'            => 'update_wc_product',
				'label'           => __( 'Update WooCommerce Product', 'codelinden-elementor-form-addon' ),
				'icon'            => 'dashicons-cart',
				'requires'        => array( CLEFA_Plugin_Dependencies::DEP_WOOCOMMERCE ),
				'disabled_reason' => CLEFA_Plugin_Dependencies::get_label( CLEFA_Plugin_Dependencies::DEP_WOOCOMMERCE ),
			),
			array(
				'type'            => 'create_wc_product',
				'label'           => __( 'Create WooCommerce Product', 'codelinden-elementor-form-addon' ),
				'icon'            => 'dashicons-store',
				'requires'        => array( CLEFA_Plugin_Dependencies::DEP_WOOCOMMERCE ),
				'disabled_reason' => CLEFA_Plugin_Dependencies::get_label( CLEFA_Plugin_Dependencies::DEP_WOOCOMMERCE ),
			),
			array( 'type' => 'send_email',         'label' => __( 'Send Email', 'codelinden-elementor-form-addon' ),                 'icon' => 'dashicons-email-alt' ),
			array( 'type' => 'webhook',            'label' => __( 'Webhook', 'codelinden-elementor-form-addon' ),                   'icon' => 'dashicons-rest-api' ),
			array( 'type' => 'redirect',           'label' => __( 'Redirect', 'codelinden-elementor-form-addon' ),                  'icon' => 'dashicons-external' ),
			array( 'type' => 'custom_hook',        'label' => __( 'Custom PHP Hook', 'codelinden-elementor-form-addon' ),           'icon' => 'dashicons-code-standards' ),
		);

		return array_map( array( 'CLEFA_Plugin_Dependencies', 'enrich_definition' ), $actions );
	}

	/**
	 * Apply dependency metadata to every field in each sidebar group.
	 */
	private static function enrich_field_groups( array $groups ) {
		foreach ( $groups as $group_key => $group ) {
			if ( empty( $group['fields'] ) || ! is_array( $group['fields'] ) ) {
				continue;
			}
			$groups[ $group_key ]['fields'] = array_map(
				array( 'CLEFA_Plugin_Dependencies', 'enrich_definition' ),
				$group['fields']
			);
		}
		return $groups;
	}

	private static function get_wp_roles() {
		$roles  = wp_roles()->roles;
		$result = array();
		foreach ( $roles as $key => $role ) {
			$result[] = array(
				'value' => $key,
				'label' => translate_user_role( $role['name'] ),
			);
		}
		return $result;
	}

	private static function get_builder_i18n() {
		return array(
			'saveForm'          => __( 'Save Form', 'codelinden-elementor-form-addon' ),
			'saving'            => __( 'Saving...', 'codelinden-elementor-form-addon' ),
			'saved'             => __( 'Saved', 'codelinden-elementor-form-addon' ),
			'saveError'         => __( 'Save failed. Please try again.', 'codelinden-elementor-form-addon' ),
			'unsavedChanges'    => __( 'You have unsaved changes.', 'codelinden-elementor-form-addon' ),
			'addStep'           => __( 'Add Step', 'codelinden-elementor-form-addon' ),
			'addField'          => __( 'Add Field', 'codelinden-elementor-form-addon' ),
			'addAction'         => __( 'Add Action', 'codelinden-elementor-form-addon' ),
			'deleteStep'        => __( 'Delete Step', 'codelinden-elementor-form-addon' ),
			'deleteField'       => __( 'Delete Field', 'codelinden-elementor-form-addon' ),
			'deleteAction'      => __( 'Delete Action', 'codelinden-elementor-form-addon' ),
			'confirmDelete'     => __( 'Are you sure? This cannot be undone.', 'codelinden-elementor-form-addon' ),
			'lockedField'       => __( 'This is a core field and cannot be deleted. You can hide it using conditions instead.', 'codelinden-elementor-form-addon' ),
			'duplicateField'    => __( 'Duplicate Field', 'codelinden-elementor-form-addon' ),
			'simulate'          => __( 'Simulate Form', 'codelinden-elementor-form-addon' ),
			'closeSimulate'     => __( 'Close Preview', 'codelinden-elementor-form-addon' ),
			'newFormName'       => __( 'Untitled Form', 'codelinden-elementor-form-addon' ),
			'stepLabel'         => __( 'Step', 'codelinden-elementor-form-addon' ),
			'selectField'       => __( 'Select a field to edit its settings', 'codelinden-elementor-form-addon' ),
			'formSettings'      => __( 'Form Settings', 'codelinden-elementor-form-addon' ),
			'dropHere'          => __( 'Drop field here', 'codelinden-elementor-form-addon' ),
			'addNotification'   => __( 'Add Notification', 'codelinden-elementor-form-addon' ),
			'pluginRequired'    => __( 'Plugin required', 'codelinden-elementor-form-addon' ),
		);
	}
}
