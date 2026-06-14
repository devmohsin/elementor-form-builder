<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CLEFA_Form_Sanitizer {

	public static function sanitize( array $data, array $config ) {
		$sanitized = array();

		foreach ( ( $config['steps'] ?? array() ) as $step ) {
			foreach ( ( $step['fields'] ?? array() ) as $field ) {
				$field_id = $field['field_id'] ?? '';
				if ( ! $field_id || ! array_key_exists( $field_id, $data ) ) {
					continue;
				}
				$value = $data[ $field_id ];
				$sanitized[ $field_id ] = self::sanitize_field( $value, $field );
			}
		}

		return apply_filters( 'clefa_sanitized_submission_data', $sanitized, $data, $config );
	}

	public static function sanitize_field( $value, array $field ) {
		$type = $field['field_type'] ?? 'text';
		$san  = $field['sanitization_type'] ?? '';

		if ( $san ) {
			return self::apply_named_sanitizer( $value, $san );
		}

		switch ( $type ) {
			case 'email':
				return sanitize_email( (string) $value );

			case 'url':
				return esc_url_raw( (string) $value );

			case 'number':
			case 'range': {
				$v = $field['validation']['integer_only'] ?? false ? intval( $value ) : floatval( $value );
				return $v;
			}

			case 'textarea': {
				$allow_html = ! empty( $field['allow_html'] );
				return $allow_html ? wp_kses_post( (string) $value ) : sanitize_textarea_field( (string) $value );
			}

			case 'html':
				return wp_kses_post( (string) $value );

			case 'checkbox':
			case 'select2': {
				if ( is_array( $value ) ) {
					return array_map( 'sanitize_text_field', $value );
				}
				return sanitize_text_field( (string) $value );
			}

			case 'radio':
			case 'select':
				return sanitize_text_field( (string) $value );

			case 'date': {
				$date = sanitize_text_field( (string) $value );
				return self::validate_date_format( $date ) ? $date : '';
			}

			case 'hidden': {
				$adv = $field['advanced'] ?? array();
				if ( ! empty( $adv['signed'] ) ) {
					return self::verify_signed_token( $value );
				}
				return sanitize_text_field( (string) $value );
			}

			case 'password':
			case 'confirm_password':
				return (string) $value;

			case 'repeater': {
				if ( ! is_array( $value ) ) {
					return array();
				}
				$rows = array();
				foreach ( $value as $row ) {
					if ( ! is_array( $row ) ) {
						continue;
					}
					$clean_row = array();
					foreach ( (array) ( $field['sub_fields'] ?? array() ) as $sub_field ) {
						$sub_id = $sub_field['field_id'] ?? '';
						if ( $sub_id && array_key_exists( $sub_id, $row ) ) {
							$clean_row[ $sub_id ] = self::sanitize_field( $row[ $sub_id ], $sub_field );
						}
					}
					if ( ! empty( $clean_row ) ) {
						$rows[] = $clean_row;
					}
				}
				return $rows;
			}

			case 'file':
			case 'multi_file':
				return sanitize_text_field( (string) $value );

			default:
				return sanitize_text_field( (string) $value );
		}
	}

	private static function apply_named_sanitizer( $value, $type ) {
		switch ( $type ) {
			case 'sanitize_text_field':     return sanitize_text_field( (string) $value );
			case 'sanitize_textarea_field': return sanitize_textarea_field( (string) $value );
			case 'sanitize_email':          return sanitize_email( (string) $value );
			case 'esc_url_raw':             return esc_url_raw( (string) $value );
			case 'absint':                  return absint( $value );
			case 'floatval':                return floatval( $value );
			case 'wp_kses_post':            return wp_kses_post( (string) $value );
			default:                        return sanitize_text_field( (string) $value );
		}
	}

	private static function validate_date_format( $date ) {
		if ( empty( $date ) ) { return false; }
		$formats = array( 'Y-m-d', 'd/m/Y', 'm/d/Y', 'd-m-Y' );
		foreach ( $formats as $format ) {
			$d = DateTime::createFromFormat( $format, $date );
			if ( $d && $d->format( $format ) === $date ) { return true; }
		}
		return false;
	}

	private static function verify_signed_token( $value ) {
		if ( empty( $value ) ) { return ''; }
		$parts = explode( ':', (string) $value, 2 );
		if ( count( $parts ) !== 2 ) { return ''; }
		list( $data, $sig ) = $parts;
		$expected = hash_hmac( 'sha256', $data, wp_salt( 'auth' ) );
		if ( hash_equals( $expected, $sig ) ) {
			return sanitize_text_field( $data );
		}
		return '';
	}

	public static function sign_hidden_value( $value ) {
		$sig = hash_hmac( 'sha256', $value, wp_salt( 'auth' ) );
		return $value . ':' . $sig;
	}
}
