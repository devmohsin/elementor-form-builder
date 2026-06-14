<?php
/**
 * Builds form configs in memory for the programmatic test suite.
 */

class CLEFA_Programmatic_Form_Builder {

	public static function form( string $name, array $fields, array $actions = array(), array $settings = array() ): array {
		return array(
			'form_name'     => $name,
			'form_type'     => 'standard',
			'settings'      => array_merge( array( 'store_submissions' => false ), $settings ),
			'notifications' => array(),
			'actions'       => $actions,
			'steps'         => array(
				array(
					'step_id'   => 'step_1',
					'step_name' => 'Main',
					'routing'   => array(),
					'fields'    => $fields,
				),
			),
		);
	}

	public static function field( string $id, string $type, array $overrides = array() ): array {
		return array_merge(
			array(
				'field_id'         => $id,
				'field_type'       => $type,
				'label'            => ucwords( str_replace( '_', ' ', $id ) ),
				'placeholder'      => '',
				'required'         => false,
				'validation_rules' => array(),
				'conditions'       => array(),
			),
			$overrides
		);
	}

	public static function rule( string $rule, $value = '', string $message = '' ): array {
		return array(
			'rule'    => $rule,
			'value'   => $value,
			'message' => $message,
		);
	}

	public static function condition(
		string $source,
		string $operator,
		string $compare,
		string $action,
		string $group = 'AND'
	): array {
		return array(
			'logic_group'    => $group,
			'source_field'   => $source,
			'operator'       => $operator,
			'compare_value'  => $compare,
			'action'         => $action,
			'action_value'   => '',
		);
	}

	public static function action( string $type, array $config = array() ): array {
		return array_merge(
			array(
				'action_id'   => 'a_' . sanitize_key( $type ),
				'action_type' => $type,
				'enabled'     => true,
			),
			$config
		);
	}

	public static function save_only(): array {
		return array( self::action( 'save_submission' ) );
	}

	public static function checkbox_options( array $pairs ): array {
		$options = array();
		foreach ( $pairs as $value => $label ) {
			$options[] = array( 'value' => $value, 'label' => $label );
		}
		return $options;
	}

	public static function select_options( array $pairs ): array {
		return self::checkbox_options( $pairs );
	}
}
