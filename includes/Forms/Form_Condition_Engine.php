<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CLEFA_Form_Condition_Engine {

	const OPERATORS = array(
		'equals', 'not_equals', 'contains', 'not_contains',
		'starts_with', 'ends_with', 'greater_than', 'less_than',
		'greater_than_or_equal', 'less_than_or_equal',
		'is_empty', 'is_not_empty', 'is_checked', 'is_not_checked',
		'date_after', 'date_before', 'date_equals',
		'age_over', 'age_under', 'file_uploaded',
		'api_check_passed', 'api_check_failed',
	);

	/** Actions that are handled purely on the frontend (no PHP effect). */
	const FRONTEND_ONLY_ACTIONS = array(
		'add_class', 'remove_class',
		'set_style', 'clear_style',
		'set_placeholder', 'restore_placeholder',
		'set_label', 'restore_label',
		'set_description', 'restore_description',
	);

	/**
	 * Evaluate a set of conditions against submitted data.
	 *
	 * Returns an associative array:
	 *   [ 'action' => string, 'value' => string ]
	 *
	 * The action is one of: show, hide, require, unrequire, add_class,
	 * remove_class, set_style, clear_style, set_placeholder,
	 * restore_placeholder, set_label, restore_label,
	 * set_description, restore_description.
	 */
	public static function evaluate_field_conditions( array $conditions, array $data ): array {
		if ( empty( $conditions ) ) {
			return array( 'action' => 'show', 'value' => '' );
		}

		$groups = array();
		foreach ( $conditions as $cond ) {
			$group            = $cond['logic_group'] ?? 'AND';
			$groups[ $group ][] = $cond;
		}

		foreach ( $groups as $group_conds ) {
			$all_pass = true;
			foreach ( $group_conds as $c ) {
				if ( ! self::evaluate_single( $c, $data ) ) {
					$all_pass = false;
					break;
				}
			}
			if ( $all_pass ) {
				$matched = end( $group_conds );
				return array(
					'action' => $matched['action']       ?? 'show',
					'value'  => $matched['action_value'] ?? '',
				);
			}
		}

		// No group matched — return the inverse of the last condition's action.
		$last = end( $conditions );
		return self::inverse_effect(
			$last['action']       ?? 'show',
			$last['action_value'] ?? ''
		);
	}

	public static function evaluate_single( array $cond, array $data ): bool {
		$source_id = $cond['source_field']  ?? '';
		$operator  = $cond['operator']      ?? 'equals';
		$compare   = $cond['compare_value'] ?? '';
		$actual    = $data[ $source_id ]    ?? '';

		return self::compare( $actual, $operator, $compare );
	}

	// -------------------------------------------------------------------------
	// Field ID collections
	// -------------------------------------------------------------------------

	/**
	 * Return an array of field IDs that should be visible given the current
	 * form data.  Fields with no conditions are always included.
	 */
	public static function get_visible_field_ids( array $config, array $data ): array {
		$visible = array();
		foreach ( ( $config['steps'] ?? array() ) as $step ) {
			foreach ( ( $step['fields'] ?? array() ) as $field ) {
				$field_id   = $field['field_id'] ?? '';
				$conditions = $field['conditions'] ?? array();
				if ( empty( $conditions ) ) {
					$visible[] = $field_id;
					continue;
				}
				$result = self::evaluate_field_conditions( $conditions, $data );
				if ( in_array( $result['action'], array( 'show', 'require', 'unrequire' ), true ) ) {
					$visible[] = $field_id;
				}
			}
		}
		return $visible;
	}

	/**
	 * Return an associative array of field_id => bool for fields whose
	 * "required" state is overridden by a condition (require / unrequire).
	 * Only fields that have an active require/unrequire condition are included.
	 */
	public static function get_required_overrides( array $config, array $data ): array {
		$overrides = array();
		foreach ( ( $config['steps'] ?? array() ) as $step ) {
			foreach ( ( $step['fields'] ?? array() ) as $field ) {
				$field_id   = $field['field_id'] ?? '';
				$conditions = $field['conditions'] ?? array();
				if ( empty( $conditions ) ) {
					continue;
				}
				$result = self::evaluate_field_conditions( $conditions, $data );
				if ( 'require' === $result['action'] ) {
					$overrides[ $field_id ] = true;
				} elseif ( 'unrequire' === $result['action'] ) {
					$overrides[ $field_id ] = false;
				}
			}
		}
		return $overrides;
	}

	// -------------------------------------------------------------------------
	// Comparison
	// -------------------------------------------------------------------------

	public static function compare( $actual, string $operator, string $compare ): bool {
		$actual  = is_array( $actual ) ? $actual : (string) $actual;
		$compare = (string) $compare;

		switch ( $operator ) {
			case 'equals':
				return is_array( $actual ) ? in_array( $compare, $actual, true ) : $actual === $compare;
			case 'not_equals':
				return is_array( $actual ) ? ! in_array( $compare, $actual, true ) : $actual !== $compare;
			case 'contains':
				return is_array( $actual ) ? in_array( $compare, $actual, true ) : false !== strpos( $actual, $compare );
			case 'not_contains':
				return is_array( $actual ) ? ! in_array( $compare, $actual, true ) : false === strpos( $actual, $compare );
			case 'starts_with':
				return ! is_array( $actual ) && 0 === strpos( $actual, $compare );
			case 'ends_with':
				return ! is_array( $actual ) && ( $compare === substr( $actual, -strlen( $compare ) ) );
			case 'greater_than':
				return is_numeric( $actual ) && is_numeric( $compare ) && (float) $actual > (float) $compare;
			case 'less_than':
				return is_numeric( $actual ) && is_numeric( $compare ) && (float) $actual < (float) $compare;
			case 'greater_than_or_equal':
				return is_numeric( $actual ) && is_numeric( $compare ) && (float) $actual >= (float) $compare;
			case 'less_than_or_equal':
				return is_numeric( $actual ) && is_numeric( $compare ) && (float) $actual <= (float) $compare;
			case 'is_empty':
				return is_array( $actual ) ? empty( $actual ) : '' === trim( $actual );
			case 'is_not_empty':
				return is_array( $actual ) ? ! empty( $actual ) : '' !== trim( $actual );
			case 'is_checked':
				return is_array( $actual ) ? ! empty( $actual ) : ( '1' === $actual || 'true' === $actual || 'on' === $actual );
			case 'is_not_checked':
				return is_array( $actual ) ? empty( $actual ) : ( '1' !== $actual && 'true' !== $actual && 'on' !== $actual );
			case 'date_after':
				return self::compare_dates( $actual, $compare, '>' );
			case 'date_before':
				return self::compare_dates( $actual, $compare, '<' );
			case 'date_equals':
				return self::compare_dates( $actual, $compare, '=' );
			case 'age_over':
				return self::check_age( $actual, (int) $compare, '>' );
			case 'age_under':
				return self::check_age( $actual, (int) $compare, '<' );
			case 'file_uploaded':
				return ! empty( $actual );
			case 'api_check_passed':
				return 'success' === (string) $actual;
			case 'api_check_failed':
				$s = (string) $actual;
				return 'fail' === $s || 'error' === $s;
			default:
				return false;
		}
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	private static function inverse_effect( string $action, string $value ): array {
		$inverses = array(
			'show'            => array( 'action' => 'hide',                'value' => $value ),
			'hide'            => array( 'action' => 'show',                'value' => $value ),
			// require/unrequire are one-directional overrides: when condition is unmet
			// the field reverts to its own config, not to the opposite state.
			'require'         => array( 'action' => 'noop',                'value' => '' ),
			'unrequire'       => array( 'action' => 'noop',                'value' => '' ),
			'add_class'       => array( 'action' => 'remove_class',        'value' => $value ),
			'remove_class'    => array( 'action' => 'add_class',           'value' => $value ),
			'set_style'       => array( 'action' => 'clear_style',         'value' => $value ),
			'clear_style'     => array( 'action' => 'set_style',           'value' => $value ),
			'set_placeholder' => array( 'action' => 'restore_placeholder', 'value' => '' ),
			'set_label'       => array( 'action' => 'restore_label',       'value' => '' ),
			'set_description' => array( 'action' => 'restore_description', 'value' => '' ),
		);
		return $inverses[ $action ] ?? array( 'action' => 'noop', 'value' => '' );
	}

	private static function compare_dates( $date1, string $date2, string $op ): bool {
		$ts1 = strtotime( $date1 );
		$ts2 = $date2 === 'today' ? strtotime( 'today' ) : strtotime( $date2 );
		if ( ! $ts1 || ! $ts2 ) { return false; }
		switch ( $op ) {
			case '>': return $ts1 > $ts2;
			case '<': return $ts1 < $ts2;
			case '=': return $ts1 === $ts2;
		}
		return false;
	}

	private static function check_age( $dob, int $threshold, string $op ): bool {
		$ts = strtotime( $dob );
		if ( ! $ts ) { return false; }
		$age_years = (int) floor( ( time() - $ts ) / 31536000 );
		switch ( $op ) {
			case '>': return $age_years > $threshold;
			case '<': return $age_years < $threshold;
		}
		return false;
	}
}
