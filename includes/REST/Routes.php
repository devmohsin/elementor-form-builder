<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CLEFA_Routes {

	public function register() {
		require_once CLEFA_PLUGIN_PATH . 'includes/REST/REST_Helpers.php';
		require_once CLEFA_PLUGIN_PATH . 'includes/REST/Forms_Route.php';
		$forms_route = new CLEFA_Forms_Route();
		$forms_route->register();

		require_once CLEFA_PLUGIN_PATH . 'includes/REST/Upload_Route.php';
		$upload_route = new CLEFA_Upload_Route();
		$upload_route->register();

		require_once CLEFA_PLUGIN_PATH . 'includes/REST/Preview_Route.php';
		$preview_route = new CLEFA_Preview_Route();
		$preview_route->register();

		require_once CLEFA_PLUGIN_PATH . 'includes/REST/Nonce_Route.php';
		$nonce_route = new CLEFA_Nonce_Route();
		$nonce_route->register();

		require_once CLEFA_PLUGIN_PATH . 'includes/REST/Live_Check_Route.php';
		$live_check_route = new CLEFA_Live_Check_Route();
		$live_check_route->register();

		// Draft persistence REST routes (registered directly from the state manager)
		CLEFA_Form_State_Manager::register_rest_routes();

		require_once CLEFA_PLUGIN_PATH . 'includes/REST/Filter_Route.php';
		$filter_route = new CLEFA_Filter_Route();
		$filter_route->register();

		require_once CLEFA_PLUGIN_PATH . 'includes/REST/Select2_Route.php';
		$select2_route = new CLEFA_Select2_Route();
		$select2_route->register();
	}
}
