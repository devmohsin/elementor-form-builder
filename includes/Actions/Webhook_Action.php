<?php
/**
 * Action: Webhook
 *
 * POSTs sanitized submission data (or a custom payload) to an external URL
 * using wp_remote_post(). Supports custom headers, authentication, retry,
 * and payload format selection.
 *
 * Action config keys:
 *   webhook_url      string   The endpoint URL. Supports {field:*} tokens.
 *   method           string   'POST' | 'GET' | 'PUT' | 'PATCH' (default 'POST').
 *   payload_format   string   'json' | 'form' (default 'json').
 *   payload_fields   string   Comma-separated list of field IDs to include.
 *                             Empty = all fields.
 *   custom_headers   array    [{ key, value }] Additional HTTP headers.
 *   auth_type        string   'none' | 'basic' | 'bearer' (default 'none').
 *   auth_user        string   Basic auth username.
 *   auth_pass        string   Basic auth password.
 *   auth_token       string   Bearer token.
 *   timeout          int      Request timeout in seconds (default 10).
 *   include_meta     bool     Whether to include form_id, submission_id, timestamp.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CLEFA_Webhook_Action extends CLEFA_Abstract_Action {

	public function run( array $data, array $form_config, $submission_id, array $action_config = array() ) {
		$url = trim( $this->resolve_token( $action_config['webhook_url'] ?? '', $data, $form_config, $submission_id ) );

		if ( empty( $url ) || ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return array( 'success' => false, 'error' => 'Invalid or empty webhook URL.' );
		}

		$url = esc_url_raw( $url );

		$method  = strtoupper( $action_config['method'] ?? 'POST' );
		$allowed_methods = array( 'POST', 'GET', 'PUT', 'PATCH' );
		if ( ! in_array( $method, $allowed_methods, true ) ) {
			$method = 'POST';
		}

		$timeout = absint( $action_config['timeout'] ?? 10 );
		$timeout = max( 1, min( $timeout, 60 ) );

		$payload = $this->build_payload( $data, $action_config, $form_config, $submission_id );

		$headers = array( 'Content-Type' => 'application/json' );

		// Authentication headers
		$auth_type = $action_config['auth_type'] ?? 'none';
		if ( 'basic' === $auth_type ) {
			$user  = $action_config['auth_user'] ?? '';
			$pass  = $action_config['auth_pass'] ?? '';
			if ( $user ) {
				$headers['Authorization'] = 'Basic ' . base64_encode( $user . ':' . $pass );
			}
		} elseif ( 'bearer' === $auth_type ) {
			$token = trim( $action_config['auth_token'] ?? '' );
			if ( $token ) {
				$headers['Authorization'] = 'Bearer ' . $token;
			}
		}

		// Custom headers from config
		foreach ( (array) ( $action_config['custom_headers'] ?? array() ) as $header ) {
			$hkey = sanitize_text_field( $header['key'] ?? '' );
			$hval = sanitize_text_field( $header['value'] ?? '' );
			if ( $hkey ) {
				$headers[ $hkey ] = $hval;
			}
		}

		$format = $action_config['payload_format'] ?? 'json';

		if ( 'form' === $format ) {
			unset( $headers['Content-Type'] );
			$body = $payload;
		} else {
			$body = wp_json_encode( $payload );
		}

		$args = array(
			'method'      => $method,
			'headers'     => $headers,
			'body'        => $body,
			'timeout'     => $timeout,
			'sslverify'   => (bool) apply_filters( 'clefa_webhook_sslverify', true ),
			'user-agent'  => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_site_url(),
		);

		$args = apply_filters( 'clefa_webhook_args', $args, $url, $payload, $form_config, $submission_id );

		$response = wp_remote_post( $url, $args );

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'error'   => $response->get_error_message(),
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		$result = array(
			'success'  => $code >= 200 && $code < 300,
			'http_code'=> $code,
			'body'     => substr( $body, 0, 2048 ), // Truncate for log safety
		);

		do_action( 'clefa_webhook_sent', $url, $payload, $result, $form_config, $submission_id );

		return $result;
	}

	/**
	 * Build the outgoing payload array.
	 */
	private function build_payload( array $data, array $action_config, array $form_config, $submission_id ) {
		$allowed_fields = array();
		if ( ! empty( $action_config['payload_fields'] ) ) {
			$allowed_fields = array_map( 'trim', explode( ',', $action_config['payload_fields'] ) );
			$allowed_fields = array_filter( $allowed_fields );
		}

		if ( ! empty( $allowed_fields ) ) {
			$payload = array();
			foreach ( $allowed_fields as $fid ) {
				if ( array_key_exists( $fid, $data ) ) {
					$payload[ $fid ] = $data[ $fid ];
				}
			}
		} else {
			$payload = $data;
		}

		if ( ! empty( $action_config['include_meta'] ) ) {
			$payload['_meta'] = array(
				'form_id'       => $form_config['id'] ?? 0,
				'form_name'     => $form_config['form_name'] ?? '',
				'submission_id' => $submission_id,
				'site_url'      => get_site_url(),
				'timestamp'     => current_time( 'c' ),
			);
		}

		return apply_filters( 'clefa_webhook_payload', $payload, $data, $action_config, $form_config, $submission_id );
	}
}
