<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CLEFA_Menu {

	public function register_menus() {
		add_menu_page(
			__( 'Form Addon', 'codelinden-elementor-form-addon' ),
			__( 'Form Addon', 'codelinden-elementor-form-addon' ),
			'manage_options',
			'clefa-forms',
			array( $this, 'render_forms_page' ),
			'dashicons-feedback',
			30
		);

		add_submenu_page(
			'clefa-forms',
			__( 'All Forms', 'codelinden-elementor-form-addon' ),
			__( 'All Forms', 'codelinden-elementor-form-addon' ),
			'manage_options',
			'clefa-forms',
			array( $this, 'render_forms_page' )
		);

		add_submenu_page(
			'clefa-forms',
			__( 'Edit Form', 'codelinden-elementor-form-addon' ),
			__( 'Add New', 'codelinden-elementor-form-addon' ),
			'manage_options',
			'clefa-edit-form',
			array( $this, 'render_edit_form_page' )
		);

		add_submenu_page(
			'clefa-forms',
			__( 'Submissions', 'codelinden-elementor-form-addon' ),
			__( 'Submissions', 'codelinden-elementor-form-addon' ),
			'manage_options',
			'clefa-submissions',
			array( $this, 'render_submissions_page' )
		);

		add_submenu_page(
			'clefa-forms',
			__( 'Dev / Tests', 'codelinden-elementor-form-addon' ),
			__( 'Dev', 'codelinden-elementor-form-addon' ),
			'manage_options',
			'clefa-dev',
			array( $this, 'render_dev_page' )
		);

		// Legacy slug redirect — keep old bookmarks working.
		add_submenu_page(
			null,
			__( 'Tests', 'codelinden-elementor-form-addon' ),
			__( 'Tests', 'codelinden-elementor-form-addon' ),
			'manage_options',
			'clefa-tests',
			array( $this, 'render_dev_page' )
		);

		add_submenu_page(
			'clefa-forms',
			__( 'Audit Logs', 'codelinden-elementor-form-addon' ),
			__( 'Logs', 'codelinden-elementor-form-addon' ),
			'manage_options',
			'clefa-logs',
			array( $this, 'render_logs_page' )
		);

		add_submenu_page(
			'clefa-forms',
			__( 'Settings', 'codelinden-elementor-form-addon' ),
			__( 'Settings', 'codelinden-elementor-form-addon' ),
			'manage_options',
			'clefa-settings',
			array( $this, 'render_settings_page' )
		);
	}

	public function render_forms_page() {
		$page = new CLEFA_Forms_Page();
		$page->render();
	}

	public function render_edit_form_page() {
		$page = new CLEFA_Edit_Form_Page();
		$page->render();
	}

	public function render_submissions_page() {
		$page = new CLEFA_Submissions_Page();
		$page->render();
	}

	public function render_dev_page() {
		$page = new CLEFA_Dev_Page();
		$page->render();
	}

	public function render_tests_page() {
		$this->render_dev_page();
	}

	public function render_logs_page() {
		$page = new CLEFA_Logs_Page();
		$page->render();
	}

	public function render_settings_page() {
		$page = new CLEFA_Settings_Page();
		$page->render();
	}
}
