<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Server-side step routing engine.
 *
 * Mirrors the client-side StepRouter logic so the submission handler can
 * determine which steps a user would have reached given their submitted data.
 * Fields belonging to skipped steps are excluded from validation.
 *
 * Routing rule format (stored in each step's 'routing' array):
 * [
 *   [ 'source_field' => 'user_type', 'operator' => 'equals', 'compare_value' => 'creator',  'target_step' => 'step_creator' ],
 *   [ 'source_field' => 'user_type', 'operator' => 'equals', 'compare_value' => 'customer', 'target_step' => 'step_customer' ],
 * ]
 *
 * If no rule matches on a step the engine continues to the next sequential step.
 */
class CLEFA_Form_Routing_Engine {

	/**
	 * Compute the ordered list of step IDs that would be visited during this submission.
	 *
	 * @param array $config  Decoded form config.
	 * @param array $data    Sanitised submitted data.
	 * @return string[]      Ordered step IDs that were (or would be) visited.
	 */
	public static function compute_active_steps( array $config, array $data ) {
		$steps = $config['steps'] ?? array();
		if ( empty( $steps ) ) {
			return array();
		}

		// Index steps by step_id for quick lookup
		$step_by_id = array();
		$step_ids   = array();
		foreach ( $steps as $step ) {
			$id = $step['step_id'] ?? '';
			if ( $id ) {
				$step_by_id[ $id ] = $step;
				$step_ids[]        = $id;
			}
		}

		$visited   = array();
		$current   = $step_ids[0] ?? null;
		$max_hops  = count( $step_ids ) + 5; // Guard against infinite loops
		$hop       = 0;

		while ( $current && $hop < $max_hops ) {
			$hop++;
			if ( in_array( $current, $visited, true ) ) { break; } // Cycle guard
			$visited[] = $current;
			$step      = $step_by_id[ $current ] ?? null;
			if ( ! $step ) { break; }

			$routing = $step['routing'] ?? array();
			$result  = self::evaluate_routing( $routing, $data, $step_ids, $current );

			if ( $result['via_routing'] ) {
				$current = $result['next'];
				if ( $current && ! in_array( $current, $visited, true ) ) {
					$visited[] = $current;
				}
				break;
			}

			$current = $result['next'];
		}

		return $visited;
	}

	/**
	 * Given a step's routing rules and current data, return the next step ID.
	 * Returns null when the current step is the last visited step.
	 *
	 * @param array    $routing    Routing rules for current step.
	 * @param array    $data       Submitted data.
	 * @param string[] $all_ids    All step IDs in order.
	 * @param string   $current_id Current step ID.
	 * @return array{next:?string,via_routing:bool}
	 */
	private static function evaluate_routing( array $routing, array $data, array $all_ids, $current_id ) {
		if ( ! empty( $routing ) ) {
			foreach ( $routing as $rule ) {
				if ( self::rule_matches( $rule, $data ) ) {
					$target = $rule['target_step'] ?? '';
					if ( $target && $target !== $current_id ) {
						return array(
							'next'         => $target,
							'via_routing'  => true,
						);
					}
					if ( ! $target || 'end' === $target ) {
						return array(
							'next'         => null,
							'via_routing'  => true,
						);
					}
				}
			}
		}

		// Default: next sequential step when no routing rule matched.
		$pos = array_search( $current_id, $all_ids, true );
		if ( false !== $pos && isset( $all_ids[ $pos + 1 ] ) ) {
			return array(
				'next'        => $all_ids[ $pos + 1 ],
				'via_routing' => false,
			);
		}

		return array(
			'next'        => null,
			'via_routing' => false,
		);
	}

	/**
	 * Test whether a single routing rule matches.
	 *
	 * @param array $rule  { source_field, operator, compare_value }
	 * @param array $data  Submitted data.
	 * @return bool
	 */
	private static function rule_matches( array $rule, array $data ) {
		$field_id = $rule['source_field'] ?? '';
		$actual   = $data[ $field_id ] ?? null;

		// Delegate to the shared condition engine operators
		return CLEFA_Form_Condition_Engine::compare(
			$actual,
			$rule['operator'] ?? 'equals',
			$rule['compare_value'] ?? ''
		);
	}

	/**
	 * Return the IDs of all fields that belong to active (visited) steps.
	 *
	 * @param array    $config
	 * @param string[] $active_step_ids
	 * @return string[]
	 */
	public static function get_active_field_ids( array $config, array $active_step_ids ) {
		$ids = array();
		foreach ( ( $config['steps'] ?? array() ) as $step ) {
			$sid = $step['step_id'] ?? '';
			if ( ! in_array( $sid, $active_step_ids, true ) ) { continue; }
			foreach ( ( $step['fields'] ?? array() ) as $field ) {
				$fid = $field['field_id'] ?? '';
				if ( $fid ) { $ids[] = $fid; }
			}
		}
		return $ids;
	}
}
