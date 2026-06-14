<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Server-side counterpart to the JS draft persistence logic.
 *
 * For logged-in users, drafts can optionally be stored server-side in user meta
 * (useful on public computers where localStorage is cleared).
 *
 * For guests, localStorage (client-side) is the only persistence layer.
 */
class CLEFA_Form_State_Manager {

	const META_KEY_PREFIX = '_clefa_draft_';

	/**
	 * Save a form draft for the current user.
	 *
	 * @param int   $form_id
	 * @param array $data     Sanitised field data.
	 * @return bool
	 */
	public static function save_draft( $form_id, array $data ) {
		$user_id = get_current_user_id();
		if ( ! $user_id ) { return false; }

		$key = self::META_KEY_PREFIX . absint( $form_id );
		update_user_meta( $user_id, $key, array(
			'data'       => $data,
			'saved_at'   => current_time( 'mysql', true ),
			'form_id'    => absint( $form_id ),
		) );
		return true;
	}

	/**
	 * Retrieve a draft for the current user.
	 *
	 * @param int $form_id
	 * @return array|null  Draft data array or null if none.
	 */
	public static function get_draft( $form_id ) {
		$user_id = get_current_user_id();
		if ( ! $user_id ) { return null; }

		$key   = self::META_KEY_PREFIX . absint( $form_id );
		$draft = get_user_meta( $user_id, $key, true );
		return is_array( $draft ) ? $draft : null;
	}

	/**
	 * Delete a saved draft (called after successful submission).
	 *
	 * @param int $form_id
	 */
	public static function clear_draft( $form_id ) {
		$user_id = get_current_user_id();
		if ( ! $user_id ) { return; }

		$key = self::META_KEY_PREFIX . absint( $form_id );
		delete_user_meta( $user_id, $key );
	}

	/**
	 * Register REST routes for server-side draft persistence.
	 */
	public static function register_rest_routes() {
		register_rest_route( 'clefa/v1', '/forms/(?P<id>\d+)/draft', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'rest_get_draft' ),
				'permission_callback' => 'is_user_logged_in',
				'args'                => array( 'id' => array( 'validate_callback' => 'clefa_rest_validate_numeric_param' ) ),
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'rest_save_draft' ),
				'permission_callback' => 'is_user_logged_in',
				'args'                => array( 'id' => array( 'validate_callback' => 'clefa_rest_validate_numeric_param' ) ),
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( __CLASS__, 'rest_clear_draft' ),
				'permission_callback' => 'is_user_logged_in',
				'args'                => array( 'id' => array( 'validate_callback' => 'clefa_rest_validate_numeric_param' ) ),
			),
		) );
	}

	public static function rest_get_draft( WP_REST_Request $request ) {
		$draft = self::get_draft( absint( $request->get_param( 'id' ) ) );
		return rest_ensure_response( array( 'success' => true, 'draft' => $draft ) );
	}

	public static function rest_save_draft( WP_REST_Request $request ) {
		$form_id = absint( $request->get_param( 'id' ) );
		$data    = $request->get_param( 'data' );
		if ( ! is_array( $data ) ) { $data = array(); }
		$saved   = self::save_draft( $form_id, array_map( 'sanitize_text_field', $data ) );
		return rest_ensure_response( array( 'success' => $saved ) );
	}

	public static function rest_clear_draft( WP_REST_Request $request ) {
		self::clear_draft( absint( $request->get_param( 'id' ) ) );
		return rest_ensure_response( array( 'success' => true ) );
	}
}
