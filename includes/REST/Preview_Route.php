<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CLEFA_Preview_Route {

	const NAMESPACE = 'clefa/v1';

	public function register() {
		register_rest_route( self::NAMESPACE, '/forms/(?P<id>\d+)/preview', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'render_preview' ),
			'permission_callback' => array( $this, 'admin_permission' ),
			'args'                => array(
				'id' => array( 'validate_callback' => 'clefa_rest_validate_numeric_param' ),
			),
		) );
	}

	public function admin_permission() {
		return current_user_can( 'manage_options' );
	}

	public function render_preview( WP_REST_Request $request ) {
		$form_id = absint( $request->get_param( 'id' ) );
		$config  = $request->get_param( 'config' );

		if ( $form_id && empty( $config ) ) {
			$form   = CLEFA_Tables::get_form( $form_id );
			$config = $form ? ( is_array( $form['config'] ?? null ) ? $form['config'] : array() ) : array();
		}

		if ( empty( $config ) ) {
			return new WP_Error( 'no_config', __( 'No form configuration provided.', 'codelinden-elementor-form-addon' ), array( 'status' => 400 ) );
		}

		$dummy_form = array(
			'id'        => $form_id,
			'form_uuid' => 'preview-' . wp_generate_uuid4(),
			'form_name' => $config['form_name'] ?? __( 'Form Preview', 'codelinden-elementor-form-addon' ),
			'config'    => $config,
			'status'    => 'preview',
		);

		$instance_id = 'preview-' . wp_generate_uuid4();

		ob_start();
		$form        = $dummy_form;
		$form_id_var = $form_id;
		$template    = CLEFA_TEMPLATE_PATH . 'form.php';
		if ( file_exists( $template ) ) {
			extract( array(
				'form'        => $dummy_form,
				'config'      => $config,
				'form_id'     => $form_id,
				'instance_id' => $instance_id,
			), EXTR_SKIP );
			include $template;
		}
		$html = ob_get_clean();

		return rest_ensure_response( array(
			'success' => true,
			'html'    => $html,
		) );
	}
}
