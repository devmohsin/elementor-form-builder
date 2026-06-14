<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CLEFA_Upload_Route {

	const NAMESPACE = 'clefa/v1';

	public function register() {
		register_rest_route( self::NAMESPACE, '/upload', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'handle_upload' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( self::NAMESPACE, '/upload/(?P<temp_id>[a-f0-9\-]+)', array(
			'methods'             => WP_REST_Server::DELETABLE,
			'callback'            => array( $this, 'delete_upload' ),
			'permission_callback' => '__return_true',
		) );
	}

	public function handle_upload( WP_REST_Request $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'invalid_nonce', __( 'Security check failed.', 'codelinden-elementor-form-addon' ), array( 'status' => 403 ) );
		}

		require_once CLEFA_PLUGIN_PATH . 'includes/Upload/Upload_Handler.php';
		$handler = new CLEFA_Upload_Handler();
		return $handler->handle_upload( $request );
	}

	public function delete_upload( WP_REST_Request $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'invalid_nonce', __( 'Security check failed.', 'codelinden-elementor-form-addon' ), array( 'status' => 403 ) );
		}

		$temp_id = sanitize_text_field( $request->get_param( 'temp_id' ) );
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}clefa_uploads WHERE temp_token = %s AND upload_status = 'temp' LIMIT 1", $temp_id ),
			ARRAY_A
		);

		if ( ! $row ) {
			return new WP_Error( 'not_found', __( 'Upload not found.', 'codelinden-elementor-form-addon' ), array( 'status' => 404 ) );
		}

		if ( ! empty( $row['file_path'] ) && file_exists( $row['file_path'] ) ) {
			wp_delete_file( $row['file_path'] );
		}

		$wpdb->delete( $wpdb->prefix . 'clefa_uploads', array( 'temp_token' => $temp_id ), array( '%s' ) );

		return rest_ensure_response( array( 'success' => true ) );
	}
}
