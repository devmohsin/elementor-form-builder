<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Server-side form validator.
 *
 * Reads `validation_rules` (array of rule objects) from each field config
 * and executes them through CLEFA_Validation_Registry.
 *
 * Field rule object schema:
 *   rule    string  Registry rule key (e.g. 'min_length')
 *   value   mixed   Rule parameter (e.g. '5')
 *   message string  Custom error message; leave blank for registry default
 */
class CLEFA_Form_Validator {

	private array $errors = array();

	/** Implicit base-type rules applied automatically before validation_rules. */
	private const BASE_RULES = array(
		'email'  => 'email',
		'url'    => 'url',
		'number' => 'numeric',
		'range'  => 'numeric',
		'date'   => 'date_valid',
	);

	/** Field types that produce no output and are always skipped. */
	private const DISPLAY_TYPES = array( 'html', 'notice', 'grid_break', 'heading' );

	/**
	 * Validate all visible fields in a form submission.
	 *
	 * @param array      $data             Raw submitted data keyed by field_id.
	 * @param array      $config           Parsed form config (has 'steps' key).
	 * @param array|null $visible_field_ids Null = validate all; array = skip hidden fields.
	 * @return array  Errors keyed by field_id.
	 */
	public function validate( array $data, array $config, ?array $visible_field_ids = null ): array {
		$this->errors = array();

		foreach ( ( $config['steps'] ?? array() ) as $step ) {
			foreach ( ( $step['fields'] ?? array() ) as $field ) {
				$field_id = $field['field_id'] ?? '';
				if ( ! $field_id ) {
					continue;
				}

				if ( $visible_field_ids !== null && ! in_array( $field_id, $visible_field_ids, true ) ) {
					continue;
				}

				if ( in_array( $field['field_type'] ?? '', self::DISPLAY_TYPES, true ) ) {
					continue;
				}

				if ( 'repeater' === ( $field['field_type'] ?? '' ) ) {
					foreach ( $this->validate_repeater_field( $field, $data ) as $error_key => $message ) {
						$this->errors[ $error_key ] = $message;
					}
					continue;
				}

				$value = $data[ $field_id ] ?? '';
				$this->validate_field( $field, $value, $data );
			}
		}

		return $this->errors;
	}

	// -----------------------------------------------------------------------
	// Private helpers
	// -----------------------------------------------------------------------

	private function validate_field( array $field, $value, array $all_data ): void {
		$field_id = $field['field_id'];
		$field    = apply_filters( 'clefa_field_config', $field, $field_id );
		$type     = $field['field_type'] ?? 'text';
		$required = ! empty( $field['required'] );
		$rules    = $field['validation_rules'] ?? array();

		// Required check
		if ( $required && $this->is_empty( $value ) ) {
			$req_msg = $this->find_rule_message( $rules, 'required' );
			$this->add_error(
				$field_id,
				$req_msg ?: sprintf(
					/* translators: %s = field label */
					__( '%s is required.', 'codelinden-elementor-form-addon' ),
					$field['label'] ?? $field_id
				)
			);
			return;
		}

		// Nothing more to check for empty optional fields
		if ( $this->is_empty( $value ) ) {
			return;
		}

		// Auto base-type check (email format, url format, numeric, date)
		$base_rule = self::BASE_RULES[ $type ] ?? null;
		if ( $base_rule ) {
			$base_error = CLEFA_Validation_Registry::execute( $base_rule, $value, null, $field, $all_data );
			if ( $base_error !== null ) {
				$this->add_error( $field_id, $base_error );
				return;
			}
		}

		// Run validation_rules array
		foreach ( $rules as $rule_def ) {
			$rule_key  = $rule_def['rule']    ?? '';
			$param     = $rule_def['value']   ?? null;
			$custom    = $rule_def['message'] ?? '';

			if ( ! $rule_key || 'required' === $rule_key ) {
				continue;
			}

			$error = CLEFA_Validation_Registry::execute( $rule_key, $value, $param, $field, $all_data );
			if ( $error !== null ) {
				$this->add_error( $field_id, $custom ?: $error );
				return; // Stop at first error per field
			}
		}
	}

	/** Return custom message from validation_rules for a given rule key, or empty string. */
	private function find_rule_message( array $rules, string $key ): string {
		foreach ( $rules as $r ) {
			if ( ( $r['rule'] ?? '' ) === $key ) {
				return $r['message'] ?? '';
			}
		}
		return '';
	}

	private function add_error( string $field_id, string $message ): void {
		if ( empty( $this->errors[ $field_id ] ) ) {
			$this->errors[ $field_id ] = $message;
		}
	}

	private function is_empty( $value ): bool {
		if ( is_array( $value ) ) {
			return empty( array_filter( $value ) );
		}
		return '' === trim( (string) $value );
	}

	/**
	 * Validate repeater rows and sub-fields.
	 *
	 * @return array<string,string> Errors keyed by field_id or field_id[index][sub_id].
	 */
	private function validate_repeater_field( array $field, array $data ): array {
		$field_id = $field['field_id'];
		$rows     = $data[ $field_id ] ?? array();
		if ( ! is_array( $rows ) ) {
			$rows = array();
		}

		$errors = array();

		if ( ! empty( $field['required'] ) && empty( $rows ) ) {
			$errors[ $field_id ] = sprintf(
				/* translators: %s = field label */
				__( '%s is required.', 'codelinden-elementor-form-addon' ),
				$field['label'] ?? $field_id
			);
		}

		if ( ! empty( $field['min_rows'] ) ) {
			$min = (int) $field['min_rows'];
			if ( count( $rows ) < $min ) {
				$errors[ $field_id ] = sprintf( 'At least %d row(s) required.', $min );
			}
		}

		if ( ! empty( $field['max_rows'] ) ) {
			$max = (int) $field['max_rows'];
			if ( count( $rows ) > $max ) {
				$errors[ $field_id ] = sprintf( 'Maximum %d row(s) allowed.', $max );
			}
		}

		foreach ( ( $field['validation_rules'] ?? array() ) as $rule_def ) {
			$rule_key = $rule_def['rule'] ?? '';
			$param    = $rule_def['value'] ?? null;
			$custom   = $rule_def['message'] ?? '';

			if ( ! $rule_key || 'required' === $rule_key ) {
				continue;
			}

			$error = CLEFA_Validation_Registry::execute( $rule_key, $rows, $param, $field, $data );
			if ( null !== $error ) {
				$errors[ $field_id ] = $custom ?: $error;
				break;
			}
		}

		foreach ( $rows as $index => $row_data ) {
			if ( ! is_array( $row_data ) ) {
				continue;
			}

			foreach ( ( $field['sub_fields'] ?? array() ) as $sub_field ) {
				$sub_id = $sub_field['field_id'] ?? '';
				if ( ! $sub_id ) {
					continue;
				}

				$sub_field = $this->apply_repeater_row_required_overrides( $sub_field, $row_data );
				$result    = CLEFA_Form_Condition_Engine::evaluate_field_conditions(
					$sub_field['conditions'] ?? array(),
					$row_data
				);

				if ( 'hide' === ( $result['action'] ?? 'show' ) ) {
					continue;
				}

				$error_key  = $field_id . '[' . $index . '][' . $sub_id . ']';
				$sub_config = array(
					'steps' => array(
						array(
							'step_id' => 's1',
							'fields'  => array( $sub_field ),
						),
					),
				);
				$sub_errors = ( new self() )->validate( $row_data, $sub_config, null );
				if ( ! empty( $sub_errors[ $sub_id ] ) ) {
					$errors[ $error_key ] = $sub_errors[ $sub_id ];
				}
			}
		}

		return $errors;
	}

	private function apply_repeater_row_required_overrides( array $sub_field, array $row_data ): array {
		$result = CLEFA_Form_Condition_Engine::evaluate_field_conditions(
			$sub_field['conditions'] ?? array(),
			$row_data
		);

		if ( 'require' === ( $result['action'] ?? '' ) ) {
			$sub_field['required'] = true;
		} elseif ( 'unrequire' === ( $result['action'] ?? '' ) ) {
			$sub_field['required'] = false;
		}

		return $sub_field;
	}
}
