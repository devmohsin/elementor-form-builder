<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders fixture JSON through the same templates as production forms (for browser tests).
 */
class CLEFA_Fixture_Html_Renderer {

	/**
	 * @param string $slug Fixture filename without .json (e.g. conditions-show-hide).
	 * @return array{html:string, config:array, js_config:array}|null
	 */
	public static function render( $slug ) {
		$slug = sanitize_file_name( $slug );
		$path = CLEFA_DEV_PATH . 'fixtures/forms/' . $slug . '.json';

		if ( ! is_file( $path ) ) {
			return null;
		}

		$data = json_decode( (string) file_get_contents( $path ), true );
		if ( ! is_array( $data ) || empty( $data['config'] ) ) {
			return null;
		}

		$config = $data['config'];
		$config['settings'] = array_merge(
			array(
				'enable_transitions' => true,
			),
			$config['settings'] ?? array()
		);

		// Normalize routing keys for StepRouter (target_step_id).
		foreach ( $config['steps'] ?? array() as $si => $step ) {
			foreach ( $step['routing'] ?? array() as $ri => $rule ) {
				if ( empty( $rule['target_step_id'] ) && ! empty( $rule['target_step'] ) ) {
					$config['steps'][ $si ]['routing'][ $ri ]['target_step_id'] = $rule['target_step'];
				}
			}
		}

		$form = array(
			'id'         => 9000,
			'form_uuid'  => 'test-' . $slug,
			'form_name'  => $data['form_name'] ?? $slug,
			'config'     => $config,
			'feature_map'=> self::build_feature_map( $config ),
		);

		$form_id     = 9000;
		$instance_id = 'test-' . wp_generate_uuid4();

		$template = CLEFA_Form_Renderer::locate_template( 'form.php' );
		if ( ! $template ) {
			return null;
		}

		ob_start();
		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		extract(
			array(
				'form'        => $form,
				'config'      => $config,
				'form_id'     => $form_id,
				'instance_id' => $instance_id,
			),
			EXTR_SKIP
		);
		include $template;
		$html = ob_get_clean();

		return array(
			'html'      => $html,
			'config'    => $config,
			'js_config' => self::build_js_config( $config ),
		);
	}

	public static function list_slugs() {
		$files = glob( CLEFA_DEV_PATH . 'fixtures/forms/*.json' );
		if ( ! is_array( $files ) ) {
			return array();
		}
		return array_map(
			function ( $file ) {
				return basename( $file, '.json' );
			},
			$files
		);
	}

	private static function build_js_config( array $config ) {
		return array(
			'steps' => array_map(
				function ( $step ) {
					return array(
						'step_id'   => $step['step_id'] ?? '',
						'step_name' => $step['step_name'] ?? '',
						'fields'    => array_map(
							function ( $f ) {
								$fd = array(
									'field_id'   => $f['field_id'] ?? '',
									'field_type' => $f['field_type'] ?? 'text',
									'label'      => $f['label'] ?? '',
									'required'   => ! empty( $f['required'] ),
									'validation' => $f['validation'] ?? array(),
									'conditions' => $f['conditions'] ?? array(),
								);
								if ( 'repeater' === ( $f['field_type'] ?? '' ) && ! empty( $f['sub_fields'] ) ) {
									$fd['sub_fields'] = array_map(
										function ( $sf ) {
											return array(
												'field_id'   => $sf['field_id'] ?? '',
												'conditions' => $sf['conditions'] ?? array(),
											);
										},
										$f['sub_fields']
									);
								}
								return $fd;
							},
							$step['fields'] ?? array()
						),
						'routing' => $step['routing'] ?? array(),
					);
				},
				$config['steps'] ?? array()
			),
		);
	}

	private static function build_feature_map( array $config ) {
		$has_steps = count( $config['steps'] ?? array() ) > 1;
		$has_uploads = false;
		$has_live    = false;

		foreach ( $config['steps'] ?? array() as $step ) {
			foreach ( $step['fields'] ?? array() as $field ) {
				if ( in_array( $field['field_type'] ?? '', array( 'file', 'multi_file' ), true ) ) {
					$has_uploads = true;
				}
				if ( ! empty( $field['live_check'] ) ) {
					$has_live = true;
				}
			}
		}

		return array(
			'has_steps'       => $has_steps,
			'has_uploads'     => $has_uploads,
			'has_live_checks' => $has_live,
		);
	}
}
