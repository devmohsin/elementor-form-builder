<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CLEFA_Audit_Log {

	public static function write( $event_type, array $context = array() ) {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'clefa_audit_logs',
			array(
				'event_type'         => sanitize_text_field( $event_type ),
				'form_id'            => absint( $context['form_id'] ?? 0 ),
				'user_id'            => get_current_user_id(),
				'ip_address'         => self::get_ip(),
				'event_context_json' => wp_json_encode( $context ),
				'created_at'         => current_time( 'mysql', true ),
			),
			array( '%s', '%d', '%d', '%s', '%s', '%s' )
		);
	}

	private static function get_ip() {
		$keys = array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' );
		foreach ( $keys as $k ) {
			if ( ! empty( $_SERVER[ $k ] ) ) {
				$ip = trim( explode( ',', sanitize_text_field( $_SERVER[ $k ] ) )[0] );
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}
		return '';
	}
}
