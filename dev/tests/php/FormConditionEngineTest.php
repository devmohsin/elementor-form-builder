<?php
/**
 * Form_Condition_Engine — comprehensive PHPUnit test suite
 *
 * Tests every operator in CLEFA_Form_Condition_Engine::compare(),
 * evaluate_single(), evaluate_field_conditions() group logic (AND/OR),
 * get_visible_field_ids(), and edge cases (invalid dates, arrays, etc.).
 */

use PHPUnit\Framework\TestCase;

class FormConditionEngineTest extends TestCase {

	// -----------------------------------------------------------------------
	// Helper
	// -----------------------------------------------------------------------

	private function cmp( $actual, string $operator, $compare = '' ) : bool {
		return CLEFA_Form_Condition_Engine::compare( $actual, $operator, $compare );
	}

	private function cond( string $field, string $op, string $val, string $action = 'show', string $group = 'AND', string $action_value = '' ) : array {
		return [
			'source_field'  => $field,
			'operator'      => $op,
			'compare_value' => $val,
			'action'        => $action,
			'action_value'  => $action_value,
			'logic_group'   => $group,
		];
	}

	// -----------------------------------------------------------------------
	// equals
	// -----------------------------------------------------------------------

	public function test_equals_matching_string() : void {
		$this->assertTrue( $this->cmp( 'hello', 'equals', 'hello' ) );
	}

	public function test_equals_non_matching_string() : void {
		$this->assertFalse( $this->cmp( 'hello', 'equals', 'world' ) );
	}

	public function test_equals_case_sensitive() : void {
		$this->assertFalse( $this->cmp( 'Hello', 'equals', 'hello' ) );
	}

	public function test_equals_empty_to_empty() : void {
		$this->assertTrue( $this->cmp( '', 'equals', '' ) );
	}

	public function test_equals_zero_string() : void {
		$this->assertTrue( $this->cmp( '0', 'equals', '0' ) );
	}

	public function test_equals_array_contains() : void {
		$this->assertTrue( $this->cmp( [ 'a', 'b' ], 'equals', 'b' ) );
	}

	public function test_equals_array_missing() : void {
		$this->assertFalse( $this->cmp( [ 'a', 'b' ], 'equals', 'c' ) );
	}

	// -----------------------------------------------------------------------
	// not_equals
	// -----------------------------------------------------------------------

	public function test_not_equals_different() : void {
		$this->assertTrue( $this->cmp( 'a', 'not_equals', 'b' ) );
	}

	public function test_not_equals_same() : void {
		$this->assertFalse( $this->cmp( 'a', 'not_equals', 'a' ) );
	}

	public function test_not_equals_array_missing() : void {
		$this->assertTrue( $this->cmp( [ 'x' ], 'not_equals', 'y' ) );
	}

	public function test_not_equals_array_contains() : void {
		$this->assertFalse( $this->cmp( [ 'x' ], 'not_equals', 'x' ) );
	}

	// -----------------------------------------------------------------------
	// contains
	// -----------------------------------------------------------------------

	public function test_contains_substring() : void {
		$this->assertTrue( $this->cmp( 'foobar', 'contains', 'oba' ) );
	}

	public function test_contains_substring_not_found() : void {
		$this->assertFalse( $this->cmp( 'foobar', 'contains', 'xyz' ) );
	}

	public function test_contains_empty_compare_matches_all() : void {
		$this->assertTrue( $this->cmp( 'foobar', 'contains', '' ) );
	}

	public function test_contains_array_member_found() : void {
		$this->assertTrue( $this->cmp( [ 'a', 'b' ], 'contains', 'a' ) );
	}

	public function test_contains_array_member_not_found() : void {
		$this->assertFalse( $this->cmp( [ 'a', 'b' ], 'contains', 'c' ) );
	}

	// -----------------------------------------------------------------------
	// not_contains
	// -----------------------------------------------------------------------

	public function test_not_contains_not_present() : void {
		$this->assertTrue( $this->cmp( 'foobar', 'not_contains', 'xyz' ) );
	}

	public function test_not_contains_present() : void {
		$this->assertFalse( $this->cmp( 'foobar', 'not_contains', 'oba' ) );
	}

	// -----------------------------------------------------------------------
	// starts_with / ends_with
	// -----------------------------------------------------------------------

	public function test_starts_with_match() : void {
		$this->assertTrue( $this->cmp( 'hello world', 'starts_with', 'hello' ) );
	}

	public function test_starts_with_no_match() : void {
		$this->assertFalse( $this->cmp( 'hello world', 'starts_with', 'world' ) );
	}

	public function test_starts_with_empty_compare() : void {
		$this->assertTrue( $this->cmp( 'hello', 'starts_with', '' ) );
	}

	public function test_starts_with_array_returns_false() : void {
		$this->assertFalse( $this->cmp( [ 'a' ], 'starts_with', 'a' ) );
	}

	public function test_ends_with_match() : void {
		$this->assertTrue( $this->cmp( 'hello world', 'ends_with', 'world' ) );
	}

	public function test_ends_with_no_match() : void {
		$this->assertFalse( $this->cmp( 'hello world', 'ends_with', 'hello' ) );
	}

	// -----------------------------------------------------------------------
	// Numeric operators
	// -----------------------------------------------------------------------

	public function test_greater_than_true() : void {
		$this->assertTrue( $this->cmp( '5', 'greater_than', '3' ) );
	}

	public function test_greater_than_equal_false() : void {
		$this->assertFalse( $this->cmp( '5', 'greater_than', '5' ) );
	}

	public function test_greater_than_false() : void {
		$this->assertFalse( $this->cmp( '3', 'greater_than', '5' ) );
	}

	public function test_less_than_true() : void {
		$this->assertTrue( $this->cmp( '3', 'less_than', '5' ) );
	}

	public function test_less_than_false() : void {
		$this->assertFalse( $this->cmp( '5', 'less_than', '3' ) );
	}

	public function test_greater_than_or_equal_exact() : void {
		$this->assertTrue( $this->cmp( '5', 'greater_than_or_equal', '5' ) );
	}

	public function test_greater_than_or_equal_above() : void {
		$this->assertTrue( $this->cmp( '6', 'greater_than_or_equal', '5' ) );
	}

	public function test_less_than_or_equal_exact() : void {
		$this->assertTrue( $this->cmp( '5', 'less_than_or_equal', '5' ) );
	}

	public function test_numeric_negative_values() : void {
		$this->assertTrue( $this->cmp( '-1', 'greater_than', '-5' ) );
	}

	public function test_numeric_decimal_precision() : void {
		$this->assertTrue( $this->cmp( '1.5', 'greater_than', '1.4' ) );
	}

	public function test_non_numeric_actual_returns_false() : void {
		$this->assertFalse( $this->cmp( 'abc', 'greater_than', '0' ) );
	}

	// -----------------------------------------------------------------------
	// is_empty / is_not_empty
	// -----------------------------------------------------------------------

	public function test_is_empty_empty_string() : void {
		$this->assertTrue( $this->cmp( '', 'is_empty', '' ) );
	}

	public function test_is_empty_whitespace() : void {
		$this->assertTrue( $this->cmp( '   ', 'is_empty', '' ) );
	}

	public function test_is_empty_non_empty() : void {
		$this->assertFalse( $this->cmp( 'x', 'is_empty', '' ) );
	}

	public function test_is_empty_empty_array() : void {
		$this->assertTrue( $this->cmp( [], 'is_empty', '' ) );
	}

	public function test_is_empty_non_empty_array() : void {
		$this->assertFalse( $this->cmp( [ 'a' ], 'is_empty', '' ) );
	}

	public function test_is_not_empty_non_empty_string() : void {
		$this->assertTrue( $this->cmp( 'x', 'is_not_empty', '' ) );
	}

	public function test_is_not_empty_empty_string() : void {
		$this->assertFalse( $this->cmp( '', 'is_not_empty', '' ) );
	}

	// -----------------------------------------------------------------------
	// is_checked / is_not_checked
	// -----------------------------------------------------------------------

	/** @dataProvider checkedTruthyProvider */
	public function test_is_checked_truthy( string $val ) : void {
		$this->assertTrue( $this->cmp( $val, 'is_checked', '' ) );
	}

	public static function checkedTruthyProvider() : array {
		return [ [ '1' ], [ 'true' ], [ 'on' ] ];
	}

	/** @dataProvider checkedFalsyProvider */
	public function test_is_checked_falsy( string $val ) : void {
		$this->assertFalse( $this->cmp( $val, 'is_checked', '' ) );
	}

	public static function checkedFalsyProvider() : array {
		return [ [ '' ], [ '0' ], [ 'false' ], [ 'off' ] ];
	}

	public function test_is_checked_non_empty_array() : void {
		$this->assertTrue( $this->cmp( [ 'a' ], 'is_checked', '' ) );
	}

	public function test_is_checked_empty_array() : void {
		$this->assertFalse( $this->cmp( [], 'is_checked', '' ) );
	}

	public function test_is_not_checked_empty_array() : void {
		$this->assertTrue( $this->cmp( [], 'is_not_checked', '' ) );
	}

	// -----------------------------------------------------------------------
	// date_after / date_before / date_equals
	// -----------------------------------------------------------------------

	public function test_date_after_true() : void {
		$this->assertTrue( $this->cmp( '2025-01-01', 'date_after', '2024-01-01' ) );
	}

	public function test_date_after_false() : void {
		$this->assertFalse( $this->cmp( '2023-01-01', 'date_after', '2024-01-01' ) );
	}

	public function test_date_before_true() : void {
		$this->assertTrue( $this->cmp( '2023-01-01', 'date_before', '2024-01-01' ) );
	}

	public function test_date_before_false() : void {
		$this->assertFalse( $this->cmp( '2025-01-01', 'date_before', '2024-01-01' ) );
	}

	public function test_date_equals_same_day() : void {
		$this->assertTrue( $this->cmp( '2024-06-01', 'date_equals', '2024-06-01' ) );
	}

	public function test_date_equals_different_day() : void {
		$this->assertFalse( $this->cmp( '2024-06-01', 'date_equals', '2024-06-02' ) );
	}

	public function test_date_after_today_keyword() : void {
		$future = date( 'Y-m-d', strtotime( '+10 days' ) );
		$this->assertTrue( $this->cmp( $future, 'date_after', 'today' ) );
	}

	public function test_date_before_today_keyword() : void {
		$past = date( 'Y-m-d', strtotime( '-10 days' ) );
		$this->assertTrue( $this->cmp( $past, 'date_before', 'today' ) );
	}

	public function test_invalid_date_returns_false() : void {
		$this->assertFalse( $this->cmp( 'not-a-date', 'date_after', '2020-01-01' ) );
	}

	// -----------------------------------------------------------------------
	// age_over / age_under
	// -----------------------------------------------------------------------

	public function test_age_over_adult() : void {
		$dob = date( 'Y-m-d', strtotime( '-25 years' ) );
		$this->assertTrue( $this->cmp( $dob, 'age_over', '18' ) );
	}

	public function test_age_over_child() : void {
		$dob = date( 'Y-m-d', strtotime( '-10 years' ) );
		$this->assertFalse( $this->cmp( $dob, 'age_over', '18' ) );
	}

	public function test_age_under_child() : void {
		$dob = date( 'Y-m-d', strtotime( '-10 years' ) );
		$this->assertTrue( $this->cmp( $dob, 'age_under', '18' ) );
	}

	public function test_age_under_adult() : void {
		$dob = date( 'Y-m-d', strtotime( '-25 years' ) );
		$this->assertFalse( $this->cmp( $dob, 'age_under', '18' ) );
	}

	public function test_age_invalid_dob_returns_false() : void {
		$this->assertFalse( $this->cmp( 'not-a-date', 'age_over', '18' ) );
	}

	// -----------------------------------------------------------------------
	// file_uploaded
	// -----------------------------------------------------------------------

	public function test_file_uploaded_with_value() : void {
		$this->assertTrue( $this->cmp( 'https://example.com/file.pdf', 'file_uploaded', '' ) );
	}

	public function test_file_uploaded_empty_string() : void {
		$this->assertFalse( $this->cmp( '', 'file_uploaded', '' ) );
	}

	// -----------------------------------------------------------------------
	// api_check_passed / api_check_failed
	// -----------------------------------------------------------------------

	public function test_api_check_passed_success() : void {
		$this->assertTrue( $this->cmp( 'success', 'api_check_passed', '' ) );
	}

	public function test_api_check_passed_fail() : void {
		$this->assertFalse( $this->cmp( 'fail', 'api_check_passed', '' ) );
	}

	public function test_api_check_passed_empty() : void {
		$this->assertFalse( $this->cmp( '', 'api_check_passed', '' ) );
	}

	public function test_api_check_failed_fail() : void {
		$this->assertTrue( $this->cmp( 'fail', 'api_check_failed', '' ) );
	}

	public function test_api_check_failed_error() : void {
		$this->assertTrue( $this->cmp( 'error', 'api_check_failed', '' ) );
	}

	public function test_api_check_failed_success() : void {
		$this->assertFalse( $this->cmp( 'success', 'api_check_failed', '' ) );
	}

	// -----------------------------------------------------------------------
	// Unknown operator
	// -----------------------------------------------------------------------

	public function test_unknown_operator_returns_false() : void {
		$this->assertFalse( $this->cmp( 'x', 'totally_unknown_op', 'x' ) );
	}

	// -----------------------------------------------------------------------
	// evaluate_field_conditions — AND group logic
	// -----------------------------------------------------------------------

	public function test_empty_conditions_returns_show() : void {
		$result = CLEFA_Form_Condition_Engine::evaluate_field_conditions( [], [ 'f' => 'x' ] );
		$this->assertSame( 'show', $result['action'] );
		$this->assertSame( '', $result['value'] );
	}

	public function test_single_and_condition_passes() : void {
		$conds  = [ $this->cond( 'f', 'equals', 'yes', 'show' ) ];
		$result = CLEFA_Form_Condition_Engine::evaluate_field_conditions( $conds, [ 'f' => 'yes' ] );
		$this->assertSame( 'show', $result['action'] );
	}

	public function test_single_and_condition_fails_returns_show_default() : void {
		$conds  = [ $this->cond( 'f', 'equals', 'yes', 'show' ) ];
		$result = CLEFA_Form_Condition_Engine::evaluate_field_conditions( $conds, [ 'f' => 'no' ] );
		// show's inverse is hide
		$this->assertSame( 'hide', $result['action'] );
	}

	public function test_two_and_conditions_both_pass() : void {
		$conds  = [
			$this->cond( 'a', 'equals', '1', 'hide' ),
			$this->cond( 'b', 'equals', '2', 'hide' ),
		];
		$result = CLEFA_Form_Condition_Engine::evaluate_field_conditions( $conds, [ 'a' => '1', 'b' => '2' ] );
		$this->assertSame( 'hide', $result['action'] );
	}

	public function test_two_and_conditions_one_fails() : void {
		$conds  = [
			$this->cond( 'a', 'equals', '1', 'hide' ),
			$this->cond( 'b', 'equals', '2', 'hide' ),
		];
		$result = CLEFA_Form_Condition_Engine::evaluate_field_conditions( $conds, [ 'a' => '1', 'b' => 'WRONG' ] );
		// hide's inverse is show
		$this->assertSame( 'show', $result['action'] );
	}

	// -----------------------------------------------------------------------
	// evaluate_single
	// -----------------------------------------------------------------------

	public function test_evaluate_single_passes_condition() : void {
		$cond   = $this->cond( 'status', 'equals', 'active' );
		$result = CLEFA_Form_Condition_Engine::evaluate_single( $cond, [ 'status' => 'active' ] );
		$this->assertTrue( $result );
	}

	public function test_evaluate_single_missing_field_returns_false() : void {
		$cond   = $this->cond( 'status', 'equals', 'active' );
		$result = CLEFA_Form_Condition_Engine::evaluate_single( $cond, [] );
		$this->assertFalse( $result );
	}

	// -----------------------------------------------------------------------
	// get_visible_field_ids
	// -----------------------------------------------------------------------

	public function test_get_visible_field_ids_no_conditions() : void {
		$config = [
			'steps' => [ [ 'step_id' => 's', 'fields' => [
				[ 'field_id' => 'f1', 'conditions' => [] ],
				[ 'field_id' => 'f2', 'conditions' => [] ],
			] ] ],
		];
		$visible = CLEFA_Form_Condition_Engine::get_visible_field_ids( $config, [] );
		$this->assertContains( 'f1', $visible );
		$this->assertContains( 'f2', $visible );
	}

	public function test_get_visible_field_ids_condition_show() : void {
		$config = [
			'steps' => [ [ 'step_id' => 's', 'fields' => [
				[ 'field_id' => 'trigger', 'conditions' => [] ],
				[
					'field_id'   => 'conditional',
					'conditions' => [ $this->cond( 'trigger', 'equals', 'yes', 'show' ) ],
				],
			] ] ],
		];
		$visible = CLEFA_Form_Condition_Engine::get_visible_field_ids( $config, [ 'trigger' => 'yes' ] );
		$this->assertContains( 'conditional', $visible );
	}

	public function test_get_visible_field_ids_condition_hide_not_included() : void {
		// The engine returns 'show' as default even when conditions fail,
		// so the field is still shown unless action='hide' is returned.
		// To test a hidden field, we need a condition that returns 'hide'.
		// With the current PHP engine logic, when all groups fail, the
		// default return is 'show'. So only explicitly matched 'hide' removes it.
		$config = [
			'steps' => [ [ 'step_id' => 's', 'fields' => [
				[ 'field_id' => 'trigger', 'conditions' => [] ],
				[
					'field_id'   => 'conditional',
					'conditions' => [ $this->cond( 'trigger', 'equals', 'HIDE_ME', 'hide' ) ],
				],
			] ] ],
		];
		// When trigger = 'HIDE_ME', condition matches and returns 'hide'
		$visible = CLEFA_Form_Condition_Engine::get_visible_field_ids( $config, [ 'trigger' => 'HIDE_ME' ] );
		$this->assertNotContains( 'conditional', $visible );
	}

	public function test_get_visible_field_ids_missing_step_fields_graceful() : void {
		$config = [ 'steps' => [ [ 'step_id' => 's' ] ] ]; // no 'fields' key
		$visible = CLEFA_Form_Condition_Engine::get_visible_field_ids( $config, [] );
		$this->assertIsArray( $visible );
		$this->assertEmpty( $visible );
	}

	// -----------------------------------------------------------------------
	// evaluate_field_conditions — returns array, not string
	// -----------------------------------------------------------------------

	public function test_evaluate_returns_array_with_action_and_value() : void {
		$conds  = [ $this->cond( 'f', 'equals', 'x', 'show' ) ];
		$result = CLEFA_Form_Condition_Engine::evaluate_field_conditions( $conds, [ 'f' => 'x' ] );
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'action', $result );
		$this->assertArrayHasKey( 'value', $result );
	}

	public function test_evaluate_action_value_passed_through() : void {
		$conds  = [ $this->cond( 'f', 'equals', 'vip', 'add_class', 'AND', 'gold-border' ) ];
		$result = CLEFA_Form_Condition_Engine::evaluate_field_conditions( $conds, [ 'f' => 'vip' ] );
		$this->assertSame( 'add_class', $result['action'] );
		$this->assertSame( 'gold-border', $result['value'] );
	}

	// -----------------------------------------------------------------------
	// Inverse logic — all action pairs
	// -----------------------------------------------------------------------

	public function test_inverse_show_gives_hide() : void {
		$conds  = [ $this->cond( 'f', 'equals', 'match', 'show' ) ];
		$result = CLEFA_Form_Condition_Engine::evaluate_field_conditions( $conds, [ 'f' => 'no-match' ] );
		$this->assertSame( 'hide', $result['action'] );
	}

	public function test_inverse_hide_gives_show() : void {
		$conds  = [ $this->cond( 'f', 'equals', 'match', 'hide' ) ];
		$result = CLEFA_Form_Condition_Engine::evaluate_field_conditions( $conds, [ 'f' => 'no-match' ] );
		$this->assertSame( 'show', $result['action'] );
	}

	public function test_inverse_require_gives_noop() : void {
		$conds  = [ $this->cond( 'f', 'equals', 'biz', 'require' ) ];
		$result = CLEFA_Form_Condition_Engine::evaluate_field_conditions( $conds, [ 'f' => 'personal' ] );
		$this->assertSame( 'noop', $result['action'] );
	}

	public function test_inverse_unrequire_gives_noop() : void {
		$conds  = [ $this->cond( 'f', 'equals', 'biz', 'unrequire' ) ];
		$result = CLEFA_Form_Condition_Engine::evaluate_field_conditions( $conds, [ 'f' => 'personal' ] );
		$this->assertSame( 'noop', $result['action'] );
	}

	public function test_inverse_add_class_gives_remove_class_same_value() : void {
		$conds  = [ $this->cond( 'f', 'equals', 'vip', 'add_class', 'AND', 'highlight' ) ];
		$result = CLEFA_Form_Condition_Engine::evaluate_field_conditions( $conds, [ 'f' => 'regular' ] );
		$this->assertSame( 'remove_class', $result['action'] );
		$this->assertSame( 'highlight', $result['value'] );
	}

	public function test_inverse_set_style_gives_clear_style_same_value() : void {
		$conds  = [ $this->cond( 'f', 'equals', '1', 'set_style', 'AND', 'background-color:#fff' ) ];
		$result = CLEFA_Form_Condition_Engine::evaluate_field_conditions( $conds, [ 'f' => '0' ] );
		$this->assertSame( 'clear_style', $result['action'] );
		$this->assertSame( 'background-color:#fff', $result['value'] );
	}

	public function test_inverse_set_placeholder_gives_restore_placeholder_empty_value() : void {
		$conds  = [ $this->cond( 'f', 'equals', 'biz', 'set_placeholder', 'AND', 'Company name' ) ];
		$result = CLEFA_Form_Condition_Engine::evaluate_field_conditions( $conds, [ 'f' => 'personal' ] );
		$this->assertSame( 'restore_placeholder', $result['action'] );
		$this->assertSame( '', $result['value'] );
	}

	public function test_inverse_set_label_gives_restore_label() : void {
		$conds  = [ $this->cond( 'f', 'equals', 'x', 'set_label', 'AND', 'New label' ) ];
		$result = CLEFA_Form_Condition_Engine::evaluate_field_conditions( $conds, [ 'f' => 'y' ] );
		$this->assertSame( 'restore_label', $result['action'] );
	}

	// -----------------------------------------------------------------------
	// require action — matched condition
	// -----------------------------------------------------------------------

	public function test_require_action_returns_require_when_matched() : void {
		$conds  = [ $this->cond( 'user_type', 'equals', 'business', 'require' ) ];
		$result = CLEFA_Form_Condition_Engine::evaluate_field_conditions( $conds, [ 'user_type' => 'business' ] );
		$this->assertSame( 'require', $result['action'] );
	}

	public function test_unrequire_action_returns_unrequire_when_matched() : void {
		$conds  = [ $this->cond( 'user_type', 'equals', 'guest', 'unrequire' ) ];
		$result = CLEFA_Form_Condition_Engine::evaluate_field_conditions( $conds, [ 'user_type' => 'guest' ] );
		$this->assertSame( 'unrequire', $result['action'] );
	}

	// -----------------------------------------------------------------------
	// get_required_overrides
	// -----------------------------------------------------------------------

	private function makeConfig( array $fields ) : array {
		return [ 'steps' => [ [ 'step_id' => 's', 'fields' => $fields ] ] ];
	}

	public function test_get_required_overrides_empty_when_no_conditions() : void {
		$config    = $this->makeConfig( [
			[ 'field_id' => 'f1', 'conditions' => [] ],
		] );
		$overrides = CLEFA_Form_Condition_Engine::get_required_overrides( $config, [] );
		$this->assertEmpty( $overrides );
	}

	public function test_get_required_overrides_sets_true_for_require() : void {
		$config    = $this->makeConfig( [ [
			'field_id'   => 'company',
			'conditions' => [ $this->cond( 'type', 'equals', 'biz', 'require' ) ],
		] ] );
		$overrides = CLEFA_Form_Condition_Engine::get_required_overrides( $config, [ 'type' => 'biz' ] );
		$this->assertSame( true, $overrides['company'] );
	}

	public function test_get_required_overrides_sets_false_for_unrequire() : void {
		$config    = $this->makeConfig( [ [
			'field_id'   => 'company',
			'conditions' => [ $this->cond( 'type', 'equals', 'personal', 'unrequire' ) ],
		] ] );
		$overrides = CLEFA_Form_Condition_Engine::get_required_overrides( $config, [ 'type' => 'personal' ] );
		$this->assertSame( false, $overrides['company'] );
	}

	public function test_get_required_overrides_excludes_show_hide_actions() : void {
		$config    = $this->makeConfig( [ [
			'field_id'   => 'f1',
			'conditions' => [ $this->cond( 'x', 'equals', 'yes', 'show' ) ],
		] ] );
		$overrides = CLEFA_Form_Condition_Engine::get_required_overrides( $config, [ 'x' => 'yes' ] );
		$this->assertArrayNotHasKey( 'f1', $overrides );
	}

	public function test_get_required_overrides_multiple_fields() : void {
		$config    = $this->makeConfig( [
			[
				'field_id'   => 'company',
				'conditions' => [ $this->cond( 'type', 'equals', 'biz', 'require' ) ],
			],
			[
				'field_id'   => 'vat',
				'conditions' => [ $this->cond( 'type', 'equals', 'biz', 'require' ) ],
			],
			[
				'field_id'   => 'casual',
				'conditions' => [ $this->cond( 'type', 'equals', 'personal', 'unrequire' ) ],
			],
		] );
		$overrides = CLEFA_Form_Condition_Engine::get_required_overrides( $config, [ 'type' => 'biz' ] );
		$this->assertTrue( $overrides['company'] );
		$this->assertTrue( $overrides['vat'] );
		$this->assertArrayNotHasKey( 'casual', $overrides );
	}
}
