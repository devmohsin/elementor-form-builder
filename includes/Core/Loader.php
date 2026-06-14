<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CLEFA_Loader {

	public static function init() {
		$assets = new CLEFA_Assets();
		add_action( 'admin_enqueue_scripts', array( $assets, 'enqueue_admin_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $assets, 'enqueue_frontend_assets' ) );

		$routes = new CLEFA_Routes();
		add_action( 'rest_api_init', array( $routes, 'register' ) );

		if ( is_admin() ) {
			CLEFA_Admin_UI::init();

			$menu = new CLEFA_Menu();
			add_action( 'admin_menu', array( $menu, 'register_menus' ) );

			$forms_page = new CLEFA_Forms_Page();
			add_action( 'wp_ajax_clefa_delete_form', array( $forms_page, 'ajax_delete_form' ) );
			add_action( 'wp_ajax_clefa_duplicate_form', array( $forms_page, 'ajax_duplicate_form' ) );

			$settings_page = new CLEFA_Settings_Page();
			add_action( 'admin_init', array( $settings_page, 'register_settings' ) );
		}
	}
}
