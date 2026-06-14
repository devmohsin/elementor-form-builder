<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Context-aware output escaping for CLEFA form data.
 *
 * Chooses the correct WordPress escape function based on the rendering context
 * so templates stay clean and the right sanitisation is always applied.
 *
 * Usage in templates:
 *   echo CLEFA_Form_Escaper::esc( $value, 'attr' );
 *   echo CLEFA_Form_Escaper::esc( $value, 'html' );
 *   echo CLEFA_Form_Escaper::esc( $value, 'textarea' );
 *   echo CLEFA_Form_Escaper::esc( $value, 'url' );
 *   echo CLEFA_Form_Escaper::esc( $value, 'js' );
 *   echo CLEFA_Form_Escaper::esc( $value, 'kses' );  // rich HTML
 *   echo CLEFA_Form_Escaper::esc( $value, 'json' );
 */
class CLEFA_Form_Escaper {

	/**
	 * Supported contexts and the escaping strategy for each.
	 *
	 * @var string[]
	 */
	const CONTEXTS = array(
		'html',
		'attr',
		'url',
		'textarea',
		'js',
		'kses',
		'json',
		'raw',
	);

	/**
	 * Escape a value for the given output context.
	 *
	 * @param mixed  $value
	 * @param string $context  One of: html, attr, url, textarea, js, kses, json, raw.
	 * @return string
	 */
	public static function esc( $value, $context = 'html' ) {
		switch ( $context ) {
			case 'attr':
				return esc_attr( (string) $value );

			case 'url':
				return esc_url( (string) $value );

			case 'textarea':
				return esc_textarea( (string) $value );

			case 'js':
				return esc_js( (string) $value );

			case 'kses':
				return wp_kses_post( (string) $value );

			case 'json':
				return wp_json_encode( $value );

			case 'raw':
				// Use sparingly — only when the caller is 100% certain the value is safe.
				return (string) $value;

			case 'html':
			default:
				return esc_html( (string) $value );
		}
	}

	/**
	 * Escape and echo.
	 *
	 * @param mixed  $value
	 * @param string $context
	 */
	public static function e( $value, $context = 'html' ) {
		echo self::esc( $value, $context ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Determine the appropriate escape context for a given field type.
	 *
	 * @param string $field_type  e.g. 'text', 'url', 'textarea', 'html'.
	 * @return string
	 */
	public static function context_for_field( $field_type ) {
		$map = array(
			'url'      => 'url',
			'email'    => 'attr',
			'textarea' => 'textarea',
			'html'     => 'kses',
			'hidden'   => 'attr',
			'number'   => 'attr',
			'range'    => 'attr',
			'date'     => 'attr',
		);

		return apply_filters( 'clefa_escape_context_for_field', $map[ $field_type ] ?? 'html', $field_type );
	}

	/**
	 * Escape a field value using the appropriate context for its type.
	 *
	 * @param mixed  $value
	 * @param string $field_type
	 * @return string
	 */
	public static function esc_field( $value, $field_type ) {
		return self::esc( $value, self::context_for_field( $field_type ) );
	}
}
