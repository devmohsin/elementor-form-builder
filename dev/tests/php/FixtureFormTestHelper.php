<?php

/**

 * Validates a full form config the same way submission handling does:

 * condition visibility, require overrides, top-level fields, and repeater rows.

 */



class CLEFA_Fixture_Form_Test_Helper {



	public static function validate( array $config, array $data ): array {

		$required_overrides = CLEFA_Form_Condition_Engine::get_required_overrides( $config, $data );

		if ( ! empty( $required_overrides ) ) {

			$config = self::apply_required_overrides( $config, $required_overrides );

		}



		$visible_field_ids = CLEFA_Form_Condition_Engine::get_visible_field_ids( $config, $data );

		$errors            = array();

		$validator         = new CLEFA_Form_Validator();



		foreach ( ( $config['steps'] ?? array() ) as $step ) {

			foreach ( ( $step['fields'] ?? array() ) as $field ) {

				$field_id = $field['field_id'] ?? '';

				if ( ! $field_id ) {

					continue;

				}



				if ( $visible_field_ids !== null && ! in_array( $field_id, $visible_field_ids, true ) ) {

					continue;

				}



				if ( 'repeater' === ( $field['field_type'] ?? '' ) ) {

					$errors = array_merge(

						$errors,

						self::validate_repeater_field( $field, $data, $validator )

					);

					continue;

				}



				$value      = $data[ $field_id ] ?? '';

				$sub_config = array(

					'steps' => array(

						array(

							'step_id' => 's1',

							'fields'  => array( $field ),

						),

					),

				);

				$sub_errors = $validator->validate( $data, $sub_config, null );

				foreach ( $sub_errors as $key => $message ) {

					$errors[ $key ] = $message;

				}

			}

		}



		return $errors;

	}



	private static function validate_repeater_field( array $field, array $data, CLEFA_Form_Validator $validator ): array {

		$field_id = $field['field_id'];

		$rows     = $data[ $field_id ] ?? array();

		if ( ! is_array( $rows ) ) {

			$rows = array();

		}



		$errors = array();



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



		foreach ( $rows as $index => $row_data ) {

			if ( ! is_array( $row_data ) ) {

				continue;

			}



			foreach ( ( $field['sub_fields'] ?? array() ) as $sub_field ) {

				$sub_id = $sub_field['field_id'] ?? '';

				if ( ! $sub_id ) {

					continue;

				}



				$sub_field = self::apply_row_required_overrides( $sub_field, $field, $row_data );

				$result    = CLEFA_Form_Condition_Engine::evaluate_field_conditions(

					$sub_field['conditions'] ?? array(),

					$row_data

				);



				if ( 'hide' === ( $result['action'] ?? 'show' ) ) {

					continue;

				}



				$error_key = $field_id . '[' . $index . '][' . $sub_id . ']';

				$value     = $row_data[ $sub_id ] ?? '';

				$sub_config = array(

					'steps' => array(

						array(

							'step_id' => 's1',

							'fields'  => array( $sub_field ),

						),

					),

				);

				$sub_errors = $validator->validate( $row_data, $sub_config, null );

				if ( ! empty( $sub_errors[ $sub_id ] ) ) {

					$errors[ $error_key ] = $sub_errors[ $sub_id ];

				}

			}

		}



		return $errors;

	}



	private static function apply_row_required_overrides( array $sub_field, array $repeater_field, array $row_data ): array {

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



	private static function apply_required_overrides( array $config, array $overrides ): array {

		foreach ( ( $config['steps'] ?? array() ) as $si => $step ) {

			foreach ( ( $step['fields'] ?? array() ) as $fi => $field ) {

				$id = $field['field_id'] ?? '';

				if ( isset( $overrides[ $id ] ) ) {

					$config['steps'][ $si ]['fields'][ $fi ]['required'] = $overrides[ $id ];

				}

			}

		}

		return $config;

	}



	public static function load_fixture_config( string $slug ): array {

		$path = CLEFA_DEV_PATH . 'fixtures/forms/' . $slug . '.json';

		if ( ! file_exists( $path ) ) {

			throw new InvalidArgumentException( 'Fixture not found: ' . $slug );

		}



		$json = json_decode( (string) file_get_contents( $path ), true );

		if ( ! is_array( $json ) || empty( $json['config'] ) ) {

			throw new RuntimeException( 'Invalid fixture JSON: ' . $slug );

		}



		return $json['config'];

	}

}

