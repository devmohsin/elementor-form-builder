<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CLEFA_Nonce_Route {

	const NAMESPACE = 'clefa/v1';

	public function register() {
		register_rest_route( self::NAMESPACE, '/refresh-nonce', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'handle' ),
			'permission_callback' => '__return_true',
		) );
	}

	public function handle( WP_REST_Request $request ) {
		return rest_ensure_response( array(
			'nonce' => wp_create_nonce( 'wp_rest' ),
		) );
	}
}
