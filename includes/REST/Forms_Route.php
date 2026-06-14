<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CLEFA_Forms_Route {

	const NAMESPACE = 'clefa/v1';

	public function register() {
		register_rest_route( self::NAMESPACE, '/forms/templates', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_templates' ),
			'permission_callback' => array( $this, 'admin_permission' ),
		) );

		register_rest_route( self::NAMESPACE, '/forms', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_forms' ),
				'permission_callback' => array( $this, 'admin_permission' ),
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_form' ),
				'permission_callback' => array( $this, 'admin_permission' ),
			),
		) );

		register_rest_route( self::NAMESPACE, '/forms/(?P<id>\d+)', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_form' ),
				'permission_callback' => array( $this, 'admin_permission' ),
				'args'                => array( 'id' => array( 'validate_callback' => 'clefa_rest_validate_numeric_param' ) ),
			),
			array(
				'methods'             => 'PUT, PATCH',
				'callback'            => array( $this, 'save_form' ),
				'permission_callback' => array( $this, 'admin_permission' ),
				'args'                => array( 'id' => array( 'validate_callback' => 'clefa_rest_validate_numeric_param' ) ),
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_form' ),
				'permission_callback' => array( $this, 'admin_permission' ),
				'args'                => array( 'id' => array( 'validate_callback' => 'clefa_rest_validate_numeric_param' ) ),
			),
		) );

		register_rest_route( self::NAMESPACE, '/forms/(?P<id>\d+)/publish', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'publish_form' ),
			'permission_callback' => array( $this, 'admin_permission' ),
		) );

		register_rest_route( self::NAMESPACE, '/forms/(?P<id>\d+)/duplicate', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'duplicate_form' ),
			'permission_callback' => array( $this, 'admin_permission' ),
		) );

		register_rest_route( self::NAMESPACE, '/submissions', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_submissions' ),
			'permission_callback' => array( $this, 'admin_permission' ),
		) );

		register_rest_route( self::NAMESPACE, '/submit', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'handle_submit' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( self::NAMESPACE, '/refresh-nonce', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'refresh_nonce' ),
			'permission_callback' => '__return_true',
		) );
	}

	public function admin_permission() {
		return current_user_can( 'manage_options' );
	}

	public function get_forms( WP_REST_Request $request ) {
		$forms = CLEFA_Tables::get_forms();
		return rest_ensure_response( array( 'success' => true, 'forms' => $forms ) );
	}

	public function get_templates( WP_REST_Request $request ) {
		$templates = array();
		foreach ( CLEFA_Form_Templates::all() as $key => $tpl ) {
			$templates[] = array(
				'key'         => $key,
				'label'       => $tpl['label'],
				'description' => $tpl['description'],
				'icon'        => $tpl['icon'],
				'form_type'   => $tpl['form_type'],
			);
		}
		return rest_ensure_response( array( 'success' => true, 'templates' => $templates ) );
	}

	public function get_form( WP_REST_Request $request ) {
		$form_id = absint( $request->get_param( 'id' ) );
		$form    = CLEFA_Tables::get_form( $form_id );
		if ( ! $form ) {
			return new WP_Error( 'not_found', __( 'Form not found.', 'codelinden-elementor-form-addon' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( array( 'success' => true, 'form' => $form ) );
	}

	public function create_form( WP_REST_Request $request ) {
		global $wpdb;
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = array();
		}

		$form_name = sanitize_text_field( $params['form_name'] ?? '' );
		$form_type = sanitize_text_field( $params['form_type'] ?? 'standard' );

		// If a template key is supplied, load its config as the starting point.
		$template_key = sanitize_key( $params['template'] ?? '' );
		if ( $template_key ) {
			$tpl = CLEFA_Form_Templates::get( $template_key );
			if ( $tpl ) {
				if ( '' === $form_name ) {
					$form_name = sanitize_text_field( $tpl['label'] );
				}
				$form_type = $tpl['form_type'];
				// Merge template config into params so update_existing_form picks it up.
				if ( empty( $params['config'] ) || ! is_array( $params['config'] ) ) {
					$params['config'] = $tpl['config'];
				}
			}
		}

		if ( '' === $form_name ) {
			$form_name = __( 'Untitled Form', 'codelinden-elementor-form-addon' );
		}

		$uuid = wp_generate_uuid4();

		$default_config = array(
			'form_name' => $form_name,
			'form_type' => $form_type,
			'steps'     => array(
				array(
					'step_id'   => 'step_1',
					'step_name' => __( 'Step 1', 'codelinden-elementor-form-addon' ),
					'fields'    => array(),
				),
			),
			'settings'      => array(),
			'notifications' => array(),
			'actions'       => array(),
		);

		$wpdb->insert(
			$wpdb->prefix . 'clefa_forms',
			array(
				'form_uuid'   => $uuid,
				'form_name'   => $form_name,
				'form_type'   => $form_type,
				'status'      => 'draft',
				'config_json' => wp_json_encode( $default_config ),
				'version'     => 1,
				'created_by'  => get_current_user_id(),
				'updated_by'  => get_current_user_id(),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d' )
		);

		$new_id = $wpdb->insert_id;
		if ( ! $new_id ) {
			return new WP_Error( 'create_failed', __( 'Could not create form.', 'codelinden-elementor-form-addon' ), array( 'status' => 500 ) );
		}

		if ( ! empty( $params['config'] ) && is_array( $params['config'] ) ) {
			// Re-create a synthetic request so update_existing_form gets the merged params.
			$inner_request = new WP_REST_Request( 'PUT', '' );
			$inner_request->set_header( 'Content-Type', 'application/json' );
			$inner_request->set_param( 'id', $new_id );
			$inner_request->set_body( wp_json_encode( $params ) );
			return $this->update_existing_form( $inner_request, CLEFA_Tables::get_form( $new_id ), true );
		}

		return rest_ensure_response( array(
			'success' => true,
			'form_id' => $new_id,
			'form'    => CLEFA_Tables::get_form( $new_id ),
		) );
	}

	public function save_form( WP_REST_Request $request ) {
		$form_id = absint( $request->get_param( 'id' ) );
		$params  = $request->get_json_params();

		if ( ! $params ) {
			return new WP_Error( 'invalid_data', __( 'Invalid request data.', 'codelinden-elementor-form-addon' ), array( 'status' => 400 ) );
		}

		if ( $form_id <= 0 ) {
			return new WP_Error( 'invalid_id', __( 'A valid form ID is required.', 'codelinden-elementor-form-addon' ), array( 'status' => 400 ) );
		}

		$existing = CLEFA_Tables::get_form( $form_id );
		if ( ! $existing ) {
			return new WP_Error( 'not_found', __( 'Form not found.', 'codelinden-elementor-form-addon' ), array( 'status' => 404 ) );
		}

		return $this->update_existing_form( $request, $existing, false );
	}

	private function update_existing_form( WP_REST_Request $request, array $existing, $is_initial_create = false ) {
		global $wpdb;

		$form_id = absint( $existing['id'] ?? $request->get_param( 'id' ) );
		$params  = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			return new WP_Error( 'invalid_data', __( 'Invalid request data.', 'codelinden-elementor-form-addon' ), array( 'status' => 400 ) );
		}

		require_once CLEFA_PLUGIN_PATH . 'includes/Builder/Config_Normalizer.php';
		$normalizer  = new CLEFA_Config_Normalizer();
		$config      = $params['config'] ?? array();
		$normalized  = $normalizer->normalize( $config );
		$feature_map = $normalizer->generate_feature_map( $normalized );

		$form_name = isset( $params['form_name'] ) ? sanitize_text_field( $params['form_name'] ) : $existing['form_name'];
		if ( '' === $form_name ) {
			$form_name = __( 'Untitled Form', 'codelinden-elementor-form-addon' );
		}

		$wpdb->update(
			$wpdb->prefix . 'clefa_forms',
			array(
				'form_name'                => $form_name,
				'config_json'              => wp_json_encode( $config ),
				'normalized_config_json'   => wp_json_encode( $normalized ),
				'feature_map_json'         => wp_json_encode( $feature_map ),
				'version'                  => absint( $existing['version'] ) + 1,
				'updated_by'               => get_current_user_id(),
			),
			array( 'id' => $form_id ),
			array( '%s', '%s', '%s', '%s', '%d', '%d' ),
			array( '%d' )
		);

		if ( ! $is_initial_create ) {
			$this->save_version_snapshot( $form_id, $existing );
		}

		CLEFA_Tables::invalidate_form_cache( $form_id );

		do_action( 'clefa_after_form_save', $form_id, $config, $normalized, $feature_map );

		$response = array(
			'success'     => true,
			'form'        => CLEFA_Tables::get_form( $form_id ),
			'feature_map' => $feature_map,
		);

		if ( $is_initial_create ) {
			$response['form_id'] = $form_id;
		}

		return rest_ensure_response( $response );
	}

	public function publish_form( WP_REST_Request $request ) {
		global $wpdb;
		$form_id = absint( $request->get_param( 'id' ) );

		$existing = CLEFA_Tables::get_form( $form_id );
		if ( ! $existing ) {
			return new WP_Error( 'not_found', __( 'Form not found.', 'codelinden-elementor-form-addon' ), array( 'status' => 404 ) );
		}

		$status = ( 'published' === $existing['status'] ) ? 'draft' : 'published';

		$wpdb->update(
			$wpdb->prefix . 'clefa_forms',
			array( 'status' => $status, 'updated_by' => get_current_user_id() ),
			array( 'id' => $form_id ),
			array( '%s', '%d' ),
			array( '%d' )
		);

		return rest_ensure_response( array(
			'success' => true,
			'status'  => $status,
		) );
	}

	public function delete_form( WP_REST_Request $request ) {
		global $wpdb;
		$form_id = absint( $request->get_param( 'id' ) );
		$result  = $wpdb->delete( $wpdb->prefix . 'clefa_forms', array( 'id' => $form_id ), array( '%d' ) );
		if ( false === $result ) {
			return new WP_Error( 'delete_failed', __( 'Delete failed.', 'codelinden-elementor-form-addon' ), array( 'status' => 500 ) );
		}
		return rest_ensure_response( array( 'success' => true ) );
	}

	public function duplicate_form( WP_REST_Request $request ) {
		global $wpdb;
		$form_id = absint( $request->get_param( 'id' ) );
		$source  = CLEFA_Tables::get_form( $form_id );
		if ( ! $source ) {
			return new WP_Error( 'not_found', __( 'Form not found.', 'codelinden-elementor-form-addon' ), array( 'status' => 404 ) );
		}
		$new_name = sprintf( '%s %s', $source['form_name'], __( '(Copy)', 'codelinden-elementor-form-addon' ) );
		$wpdb->insert(
			$wpdb->prefix . 'clefa_forms',
			array(
				'form_uuid'              => wp_generate_uuid4(),
				'form_name'              => $new_name,
				'form_type'              => $source['form_type'],
				'status'                 => 'draft',
				'config_json'            => $source['config_json'],
				'normalized_config_json' => $source['normalized_config_json'],
				'feature_map_json'       => $source['feature_map_json'],
				'created_by'             => get_current_user_id(),
				'updated_by'             => get_current_user_id(),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d' )
		);
		$new_id = $wpdb->insert_id;
		return rest_ensure_response( array(
			'success'  => true,
			'form_id'  => $new_id,
			'edit_url' => admin_url( 'admin.php?page=clefa-edit-form&form_id=' . $new_id ),
		) );
	}

	public function get_submissions( WP_REST_Request $request ) {
		$form_id     = absint( $request->get_param( 'form_id' ) );
		$submissions = CLEFA_Tables::get_submissions( array( 'form_id' => $form_id ) );
		return rest_ensure_response( array( 'success' => true, 'submissions' => $submissions ) );
	}

	public function handle_submit( WP_REST_Request $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'invalid_nonce', __( 'Security check failed.', 'codelinden-elementor-form-addon' ), array( 'status' => 403 ) );
		}
		$handler = new CLEFA_Form_Submission_Handler();
		return $handler->handle( $request );
	}

	public function refresh_nonce( WP_REST_Request $request ) {
		$form_id = absint( $request->get_param( 'form_id' ) );
		return rest_ensure_response( array(
			'success' => true,
			'nonce'   => wp_create_nonce( 'wp_rest' ),
		) );
	}

	private function save_version_snapshot( $form_id, $existing ) {
		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'clefa_form_versions',
			array(
				'form_id'                => $form_id,
				'version'                => absint( $existing['version'] ),
				'config_json'            => $existing['config_json'],
				'normalized_config_json' => $existing['normalized_config_json'],
				'created_by'             => get_current_user_id(),
			),
			array( '%d', '%d', '%s', '%s', '%d' )
		);
	}
}
