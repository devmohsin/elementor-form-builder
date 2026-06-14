<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class CLEFA_Abstract_Action {

	abstract public function run( array $data, array $form_config, $submission_id, array $action_config = array() );

	/**
	 * Resolve {token} placeholders in a string.
	 *
	 * Supported tokens:
	 *   {field:field_id}         — submitted field value
	 *   {user:meta_key}          — current user property or user meta
	 *   {form:name|id}           — form properties
	 *   {site:name|url}          — site info
	 *   {date} {time}            — current date / time
	 *   {submission_id}          — submission ID (when available)
	 *   {random_token}           — random 20-char alphanumeric string
	 *   {query_param:key}        — URL query-string value
	 *   {post_meta:key}          — post meta of queried object
	 *
	 * @param string|mixed $value         Template string.
	 * @param array        $data          Sanitised submitted field data.
	 * @param array        $form_config   Form configuration array.
	 * @param int          $submission_id Optional submission ID (0 = not yet saved).
	 * @return mixed
	 */
	protected function resolve_token( $value, array $data, array $form_config, $submission_id = 0 ) {
		if ( ! is_string( $value ) ) { return $value; }

		// {field:field_id} tokens
		$value = preg_replace_callback(
			'/\{field:([a-zA-Z0-9_\-]+)\}/',
			function( $m ) use ( $data ) {
				return $data[ $m[1] ] ?? '';
			},
			$value
		);

		// {user:meta_key} tokens
		$value = preg_replace_callback(
			'/\{user:([a-zA-Z0-9_\-]+)\}/',
			function( $m ) {
				if ( ! is_user_logged_in() ) { return ''; }
				$key   = $m[1];
				$user  = wp_get_current_user();
				$props = array( 'user_login', 'user_email', 'display_name', 'ID', 'user_nicename' );
				if ( in_array( $key, $props, true ) ) {
					return (string) $user->$key;
				}
				return (string) get_user_meta( $user->ID, $key, true );
			},
			$value
		);

		// {query_param:key} tokens
		$value = preg_replace_callback(
			'/\{query_param:([a-zA-Z0-9_\-]+)\}/',
			function( $m ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				return sanitize_text_field( $_GET[ $m[1] ] ?? '' );
			},
			$value
		);

		// {post_meta:key} tokens
		$value = preg_replace_callback(
			'/\{post_meta:([a-zA-Z0-9_\-]+)\}/',
			function( $m ) {
				$post_id = get_queried_object_id();
				return $post_id ? (string) get_post_meta( $post_id, $m[1], true ) : '';
			},
			$value
		);

		// {form:name} {form:id}
		$form_name = $form_config['form_name'] ?? '';
		$form_id   = $form_config['id']        ?? '';
		$value     = str_replace( '{form:name}', $form_name, $value );
		$value     = str_replace( '{form:id}',   (string) $form_id, $value );

		// {site:name} {site:url}
		$value = str_replace( '{site:name}', get_bloginfo( 'name' ), $value );
		$value = str_replace( '{site:url}',  get_site_url(),         $value );

		// {date} {time}
		$value = str_replace( '{date}', current_time( 'Y-m-d' ), $value );
		$value = str_replace( '{time}', current_time( 'H:i:s' ), $value );

		// {submission_id}
		if ( $submission_id > 0 ) {
			$value = str_replace( '{submission_id}', (string) $submission_id, $value );
		}

		// {random_token} — generate once per request per unique placeholder
		if ( false !== strpos( $value, '{random_token}' ) ) {
			$value = str_replace( '{random_token}', wp_generate_password( 20, false ), $value );
		}

		return apply_filters( 'clefa_resolve_token', $value, $data, $form_config );
	}

	protected function resolve_all_tokens( array $config_item, array $data, array $form_config, $submission_id = 0 ) {
		$resolved = array();
		foreach ( $config_item as $key => $val ) {
			$resolved[ $key ] = is_string( $val ) ? $this->resolve_token( $val, $data, $form_config, $submission_id ) : $val;
		}
		return $resolved;
	}

	/**
	 * Shorthand used by Role_Action, WC_Product_Action, etc.
	 *
	 * @param mixed  $value
	 * @param array  $data
	 * @param array  $form_config
	 * @param int    $submission_id
	 * @return mixed
	 */
	protected function resolve( $value, array $data, array $form_config = array(), $submission_id = 0 ) {
		return $this->resolve_token( $value, $data, $form_config, $submission_id );
	}
}
