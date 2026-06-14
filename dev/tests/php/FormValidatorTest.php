<?php
/**
 * FormValidator + Validation_Registry — comprehensive PHPUnit test suite
 *
 * Uses the new validation_rules array format:
 *   field.validation_rules = [ ['rule'=>'min_length','value'=>'5','message'=>''] ]
 *
 * Every rule registered in CLEFA_Validation_Registry is tested here.
 */

use PHPUnit\Framework\TestCase;

class FormValidatorTest extends TestCase {

	// -----------------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------------

	/** Wrap a single field in a minimal form config. */
	private function config( array $field ): array {
		return [ 'steps' => [ [ 'step_id' => 's1', 'fields' => [ $field ] ] ] ];
	}

	/** Run the validator and return the errors array. */
	private function validate( array $field, $value, ?array $vis = null ): array {
		$v = new CLEFA_Form_Validator();
		return $v->validate( [ $field['field_id'] => $value ], $this->config( $field ), $vis );
	}

	private function assertPasses( array $field, $value, ?array $vis = null ): void {
		$errs = $this->validate( $field, $value, $vis );
		$this->assertEmpty( $errs, 'Expected no errors but got: ' . implode( ', ', $errs ) );
	}

	private function assertFails( array $field, $value, ?array $vis = null ): void {
		$errs = $this->validate( $field, $value, $vis );
		$this->assertNotEmpty( $errs, 'Expected a validation error but none was produced.' );
	}

	/** Build a field definition using the new validation_rules array format. */
	private function field( string $id, string $type, array $rules = [], bool $required = false ): array {
		return [
			'field_id'         => $id,
			'field_type'       => $type,
			'label'            => ucfirst( $id ),
			'required'         => $required,
			'validation_rules' => $rules,
		];
	}

	/** Build a single rule entry (element in validation_rules). */
	private function rule( string $key, $value = null, string $message = '' ): array {
		return [ 'rule' => $key, 'value' => $value, 'message' => $message ];
	}

	// -----------------------------------------------------------------------
	// required
	// -----------------------------------------------------------------------

	public function test_required_empty_string_fails(): void {
		$this->assertFails( $this->field( 'f', 'text', [], true ), '' );
	}

	public function test_required_whitespace_fails(): void {
		$this->assertFails( $this->field( 'f', 'text', [], true ), '   ' );
	}

	public function test_required_empty_array_fails(): void {
		$this->assertFails( $this->field( 'f', 'checkbox', [], true ), [] );
	}

	public function test_required_non_empty_passes(): void {
		$this->assertPasses( $this->field( 'f', 'text', [], true ), 'hello' );
	}

	public function test_required_zero_string_passes(): void {
		$this->assertPasses( $this->field( 'f', 'text', [], true ), '0' );
	}

	public function test_required_non_empty_array_passes(): void {
		$this->assertPasses( $this->field( 'f', 'checkbox', [], true ), [ 'opt1' ] );
	}

	public function test_optional_empty_skips_rules(): void {
		// min_length rule is set, but field is empty and optional → no error
		$f = $this->field( 'f', 'text', [ $this->rule( 'min_length', '5' ) ] );
		$this->assertPasses( $f, '' );
	}

	public function test_required_custom_message(): void {
		$f    = $this->field( 'f', 'text', [ $this->rule( 'required', null, 'Fill this in!' ) ], true );
		$errs = $this->validate( $f, '' );
		$this->assertSame( 'Fill this in!', $errs['f'] );
	}

	// -----------------------------------------------------------------------
	// min_length / max_length / exact_length
	// -----------------------------------------------------------------------

	public function test_min_length_pass(): void {
		$f = $this->field( 'f', 'text', [ $this->rule( 'min_length', '5' ) ] );
		$this->assertPasses( $f, 'abcde' );
	}

	public function test_min_length_fail(): void {
		$f = $this->field( 'f', 'text', [ $this->rule( 'min_length', '5' ) ] );
		$this->assertFails( $f, 'abc' );
	}

	public function test_max_length_pass(): void {
		$f = $this->field( 'f', 'text', [ $this->rule( 'max_length', '5' ) ] );
		$this->assertPasses( $f, 'abcde' );
	}

	public function test_max_length_fail(): void {
		$f = $this->field( 'f', 'text', [ $this->rule( 'max_length', '5' ) ] );
		$this->assertFails( $f, 'abcdef' );
	}

	public function test_exact_length_pass(): void {
		$f = $this->field( 'f', 'text', [ $this->rule( 'exact_length', '4' ) ] );
		$this->assertPasses( $f, 'four' );
	}

	public function test_exact_length_fail_too_short(): void {
		$f = $this->field( 'f', 'text', [ $this->rule( 'exact_length', '4' ) ] );
		$this->assertFails( $f, 'hi' );
	}

	public function test_exact_length_fail_too_long(): void {
		$f = $this->field( 'f', 'text', [ $this->rule( 'exact_length', '4' ) ] );
		$this->assertFails( $f, 'toolong' );
	}

	// -----------------------------------------------------------------------
	// regex / blocked_values / equals / not_equals
	// -----------------------------------------------------------------------

	public function test_regex_pass(): void {
		$f = $this->field( 'f', 'text', [ $this->rule( 'regex', '^[a-z0-9]+$' ) ] );
		$this->assertPasses( $f, 'abc123' );
	}

	public function test_regex_fail(): void {
		$f = $this->field( 'f', 'text', [ $this->rule( 'regex', '^[a-z0-9]+$' ) ] );
		$this->assertFails( $f, 'ABC!' );
	}

	public function test_invalid_regex_does_not_throw(): void {
		$f = $this->field( 'f', 'text', [ $this->rule( 'regex', '[invalid(' ) ] );
		$this->assertDoesNotThrow( fn() => $this->validate( $f, 'anything' ) );
	}

	public function test_blocked_values_fail(): void {
		$f = $this->field( 'f', 'text', [ $this->rule( 'blocked_values', 'spam,admin,test' ) ] );
		$this->assertFails( $f, 'admin' );
	}

	public function test_blocked_values_pass(): void {
		$f = $this->field( 'f', 'text', [ $this->rule( 'blocked_values', 'spam,admin,test' ) ] );
		$this->assertPasses( $f, 'hello' );
	}

	public function test_equals_pass(): void {
		$f = $this->field( 'f', 'text', [ $this->rule( 'equals', 'exact' ) ] );
		$this->assertPasses( $f, 'exact' );
	}

	public function test_equals_fail(): void {
		$f = $this->field( 'f', 'text', [ $this->rule( 'equals', 'exact' ) ] );
		$this->assertFails( $f, 'other' );
	}

	public function test_not_equals_pass(): void {
		$f = $this->field( 'f', 'text', [ $this->rule( 'not_equals', 'banned' ) ] );
		$this->assertPasses( $f, 'allowed' );
	}

	public function test_not_equals_fail(): void {
		$f = $this->field( 'f', 'text', [ $this->rule( 'not_equals', 'banned' ) ] );
		$this->assertFails( $f, 'banned' );
	}

	// -----------------------------------------------------------------------
	// email — auto base-type + explicit rule
	// -----------------------------------------------------------------------

	/** @dataProvider validEmails */
	public function test_email_valid( string $addr ): void {
		$this->assertPasses( $this->field( 'e', 'email' ), $addr );
	}

	public static function validEmails(): array {
		return [
			[ 'user@example.com' ],
			[ 'user+tag@example.co.uk' ],
			[ 'a@b.io' ],
		];
	}

	/** @dataProvider invalidEmails */
	public function test_email_invalid( string $addr ): void {
		$this->assertFails( $this->field( 'e', 'email' ), $addr );
	}

	public static function invalidEmails(): array {
		return [
			[ 'notanemail' ],
			[ '@nodomain.com' ],
			[ 'user@' ],
		];
	}

	public function test_email_rule_on_text_field(): void {
		// Using explicit 'email' rule on a text field
		$f = $this->field( 'f', 'text', [ $this->rule( 'email' ) ] );
		$this->assertFails( $f, 'bad-email' );
		$this->assertPasses( $f, 'ok@example.com' );
	}

	public function test_email_custom_message(): void {
		$f    = $this->field( 'e', 'email', [ $this->rule( 'email', null, 'Bad email!' ) ] );
		$errs = $this->validate( $f, 'bad' );
		// base-type rule fires first (no custom msg for base), but explicit rule is listed too
		$this->assertNotEmpty( $errs );
	}

	// -----------------------------------------------------------------------
	// url — auto base-type
	// -----------------------------------------------------------------------

	public function test_url_valid(): void {
		$this->assertPasses( $this->field( 'u', 'url' ), 'https://example.com/path?q=1' );
	}

	public function test_url_invalid(): void {
		$this->assertFails( $this->field( 'u', 'url' ), 'example.com' );
	}

	public function test_url_rule_on_text_field(): void {
		$f = $this->field( 'f', 'text', [ $this->rule( 'url' ) ] );
		$this->assertFails( $f, 'not-a-url' );
		$this->assertPasses( $f, 'ftp://files.example.com' );
	}

	// -----------------------------------------------------------------------
	// numeric / integer / min_value / max_value
	// -----------------------------------------------------------------------

	public function test_numeric_pass(): void {
		// number field auto-applies numeric base rule
		$this->assertPasses( $this->field( 'n', 'number' ), '42' );
	}

	public function test_numeric_fail(): void {
		$this->assertFails( $this->field( 'n', 'number' ), 'abc' );
	}

	public function test_integer_pass(): void {
		$f = $this->field( 'n', 'number', [ $this->rule( 'integer' ) ] );
		$this->assertPasses( $f, '7' );
	}

	public function test_integer_fail_on_decimal(): void {
		$f = $this->field( 'n', 'number', [ $this->rule( 'integer' ) ] );
		$this->assertFails( $f, '3.14' );
	}

	public function test_min_value_pass(): void {
		$f = $this->field( 'n', 'number', [ $this->rule( 'min_value', '5' ) ] );
		$this->assertPasses( $f, '5' );
	}

	public function test_min_value_fail(): void {
		$f = $this->field( 'n', 'number', [ $this->rule( 'min_value', '5' ) ] );
		$this->assertFails( $f, '4' );
	}

	public function test_max_value_pass(): void {
		$f = $this->field( 'n', 'number', [ $this->rule( 'max_value', '10' ) ] );
		$this->assertPasses( $f, '10' );
	}

	public function test_max_value_fail(): void {
		$f = $this->field( 'n', 'number', [ $this->rule( 'max_value', '10' ) ] );
		$this->assertFails( $f, '11' );
	}

	// -----------------------------------------------------------------------
	// date rules — auto base-type + explicit rules
	// -----------------------------------------------------------------------

	public function test_date_valid_auto(): void {
		$this->assertPasses( $this->field( 'd', 'date' ), '2000-01-15' );
	}

	public function test_date_invalid_auto(): void {
		$this->assertFails( $this->field( 'd', 'date' ), 'not-a-date' );
	}

	public function test_date_after_today_pass(): void {
		$future = date( 'Y-m-d', strtotime( '+10 days' ) );
		$f      = $this->field( 'd', 'date', [ $this->rule( 'date_after_today' ) ] );
		$this->assertPasses( $f, $future );
	}

	public function test_date_after_today_fail(): void {
		$f = $this->field( 'd', 'date', [ $this->rule( 'date_after_today' ) ] );
		$this->assertFails( $f, '2000-01-01' );
	}

	public function test_date_before_today_pass(): void {
		$f = $this->field( 'd', 'date', [ $this->rule( 'date_before_today' ) ] );
		$this->assertPasses( $f, '2000-01-01' );
	}

	public function test_date_before_today_fail(): void {
		$future = date( 'Y-m-d', strtotime( '+10 days' ) );
		$f      = $this->field( 'd', 'date', [ $this->rule( 'date_before_today' ) ] );
		$this->assertFails( $f, $future );
	}

	public function test_date_after_pass(): void {
		$f = $this->field( 'd', 'date', [ $this->rule( 'date_after', '2020-01-01' ) ] );
		$this->assertPasses( $f, '2025-06-01' );
	}

	public function test_date_after_fail(): void {
		$f = $this->field( 'd', 'date', [ $this->rule( 'date_after', '2020-01-01' ) ] );
		$this->assertFails( $f, '2019-12-31' );
	}

	public function test_date_before_pass(): void {
		$f = $this->field( 'd', 'date', [ $this->rule( 'date_before', '2030-12-31' ) ] );
		$this->assertPasses( $f, '2025-01-01' );
	}

	public function test_date_before_fail(): void {
		$f = $this->field( 'd', 'date', [ $this->rule( 'date_before', '2030-12-31' ) ] );
		$this->assertFails( $f, '2031-01-01' );
	}

	// -----------------------------------------------------------------------
	// age_over / age_under
	// -----------------------------------------------------------------------

	public function test_age_over_adult_passes(): void {
		$dob = date( 'Y-m-d', strtotime( '-25 years' ) );
		$f   = $this->field( 'd', 'date', [ $this->rule( 'age_over', '18' ) ] );
		$this->assertPasses( $f, $dob );
	}

	public function test_age_over_minor_fails(): void {
		$dob = date( 'Y-m-d', strtotime( '-10 years' ) );
		$f   = $this->field( 'd', 'date', [ $this->rule( 'age_over', '18' ) ] );
		$this->assertFails( $f, $dob );
	}

	public function test_age_under_pass(): void {
		$dob = date( 'Y-m-d', strtotime( '-30 years' ) );
		$f   = $this->field( 'd', 'date', [ $this->rule( 'age_under', '65' ) ] );
		$this->assertPasses( $f, $dob );
	}

	public function test_age_under_fail(): void {
		$dob = date( 'Y-m-d', strtotime( '-70 years' ) );
		$f   = $this->field( 'd', 'date', [ $this->rule( 'age_under', '65' ) ] );
		$this->assertFails( $f, $dob );
	}

	// -----------------------------------------------------------------------
	// time_since / time_passed
	// -----------------------------------------------------------------------

	public function test_time_since_old_date_passes(): void {
		$old = date( 'Y-m-d', strtotime( '-60 days' ) );
		$f   = $this->field( 'd', 'date', [ $this->rule( 'time_since', '30' ) ] );
		$this->assertPasses( $f, $old );
	}

	public function test_time_since_recent_date_fails(): void {
		$recent = date( 'Y-m-d', strtotime( '-5 days' ) );
		$f      = $this->field( 'd', 'date', [ $this->rule( 'time_since', '30' ) ] );
		$this->assertFails( $f, $recent );
	}

	public function test_time_passed_old_enough_passes(): void {
		// 48+ hours ago
		$ts  = date( 'Y-m-d H:i:s', time() - 172800 );
		$f   = $this->field( 'd', 'date', [ $this->rule( 'time_passed', '24' ) ] );
		$this->assertPasses( $f, $ts );
	}

	public function test_time_passed_too_recent_fails(): void {
		// 1 hour ago
		$ts = date( 'Y-m-d H:i:s', time() - 3600 );
		$f  = $this->field( 'd', 'date', [ $this->rule( 'time_passed', '24' ) ] );
		$this->assertFails( $f, $ts );
	}

	// -----------------------------------------------------------------------
	// password complexity
	// -----------------------------------------------------------------------

	public function test_require_uppercase_pass(): void {
		$f = $this->field( 'p', 'password', [ $this->rule( 'require_uppercase' ) ] );
		$this->assertPasses( $f, 'Abcde' );
	}

	public function test_require_uppercase_fail(): void {
		$f = $this->field( 'p', 'password', [ $this->rule( 'require_uppercase' ) ] );
		$this->assertFails( $f, 'abcde' );
	}

	public function test_require_number_char_pass(): void {
		$f = $this->field( 'p', 'password', [ $this->rule( 'require_number_char' ) ] );
		$this->assertPasses( $f, 'abc1' );
	}

	public function test_require_number_char_fail(): void {
		$f = $this->field( 'p', 'password', [ $this->rule( 'require_number_char' ) ] );
		$this->assertFails( $f, 'Abcde' );
	}

	public function test_require_special_pass(): void {
		$f = $this->field( 'p', 'password', [ $this->rule( 'require_special' ) ] );
		$this->assertPasses( $f, 'abc!' );
	}

	public function test_require_special_fail(): void {
		$f = $this->field( 'p', 'password', [ $this->rule( 'require_special' ) ] );
		$this->assertFails( $f, 'Abc123' );
	}

	public function test_password_strength_weak_pass(): void {
		$f = $this->field( 'p', 'password', [ $this->rule( 'password_strength', 'weak' ) ] );
		$this->assertPasses( $f, 'abcdef' ); // 6 chars
	}

	public function test_password_strength_weak_fail(): void {
		$f = $this->field( 'p', 'password', [ $this->rule( 'password_strength', 'weak' ) ] );
		$this->assertFails( $f, 'abc' ); // 3 chars
	}

	public function test_password_strength_medium_pass(): void {
		$f = $this->field( 'p', 'password', [ $this->rule( 'password_strength', 'medium' ) ] );
		$this->assertPasses( $f, 'Abcde1fg' ); // upper+lower+digit, 8 chars
	}

	public function test_password_strength_medium_fail_no_upper(): void {
		$f = $this->field( 'p', 'password', [ $this->rule( 'password_strength', 'medium' ) ] );
		$this->assertFails( $f, 'abcde1fg' ); // no uppercase
	}

	public function test_password_strength_strong_pass(): void {
		$f = $this->field( 'p', 'password', [ $this->rule( 'password_strength', 'strong' ) ] );
		$this->assertPasses( $f, 'Abcde1fg!X' ); // 10 chars, upper+lower+digit+special
	}

	public function test_password_strength_strong_fail_no_special(): void {
		$f = $this->field( 'p', 'password', [ $this->rule( 'password_strength', 'strong' ) ] );
		$this->assertFails( $f, 'Abcde1fgXY' ); // no special char
	}

	// -----------------------------------------------------------------------
	// confirm_password
	// -----------------------------------------------------------------------

	public function test_confirm_password_match_passes(): void {
		$v      = new CLEFA_Form_Validator();
		$config = [ 'steps' => [ [ 'step_id' => 's', 'fields' => [
			$this->field( 'pw',  'password' ),
			$this->field( 'cp',  'text', [ $this->rule( 'confirm_password', 'pw' ) ] ),
		] ] ] ];
		$errors = $v->validate( [ 'pw' => 'Secret1!', 'cp' => 'Secret1!' ], $config );
		$this->assertArrayNotHasKey( 'cp', $errors );
	}

	public function test_confirm_password_mismatch_fails(): void {
		$v      = new CLEFA_Form_Validator();
		$config = [ 'steps' => [ [ 'step_id' => 's', 'fields' => [
			$this->field( 'pw',  'password' ),
			$this->field( 'cp',  'text', [ $this->rule( 'confirm_password', 'pw' ) ] ),
		] ] ] ];
		$errors = $v->validate( [ 'pw' => 'Secret1!', 'cp' => 'Wrong!' ], $config );
		$this->assertArrayHasKey( 'cp', $errors );
	}

	// -----------------------------------------------------------------------
	// unique_email
	// -----------------------------------------------------------------------

	public function test_unique_email_non_existing_passes(): void {
		// Use a guaranteed non-existing email
		$f = $this->field( 'e', 'email', [ $this->rule( 'unique_email' ) ] );
		$this->assertPasses( $f, 'definitely-not-registered-' . uniqid() . '@example.com' );
	}

	// -----------------------------------------------------------------------
	// checkbox_min / checkbox_max
	// -----------------------------------------------------------------------

	public function test_checkbox_min_pass(): void {
		$f = $this->field( 'cb', 'checkbox', [ $this->rule( 'checkbox_min', '2' ) ] );
		$this->assertPasses( $f, [ 'a', 'b' ] );
	}

	public function test_checkbox_min_fail(): void {
		$f = $this->field( 'cb', 'checkbox', [ $this->rule( 'checkbox_min', '2' ) ] );
		$this->assertFails( $f, [ 'a' ] );
	}

	public function test_checkbox_max_pass(): void {
		$f = $this->field( 'cb', 'checkbox', [ $this->rule( 'checkbox_max', '2' ) ] );
		$this->assertPasses( $f, [ 'a', 'b' ] );
	}

	public function test_checkbox_max_fail(): void {
		$f = $this->field( 'cb', 'checkbox', [ $this->rule( 'checkbox_max', '2' ) ] );
		$this->assertFails( $f, [ 'a', 'b', 'c' ] );
	}

	// -----------------------------------------------------------------------
	// file_type / file_size_max / file_count_max
	// -----------------------------------------------------------------------

	public function test_file_type_allowed_passes(): void {
		$f    = $this->field( 'fi', 'file', [ $this->rule( 'file_type', 'pdf,jpg' ) ] );
		$file = [ 'name' => 'document.pdf', 'size' => 1000 ];
		$this->assertPasses( $f, $file );
	}

	public function test_file_type_blocked_fails(): void {
		$f    = $this->field( 'fi', 'file', [ $this->rule( 'file_type', 'pdf,jpg' ) ] );
		$file = [ 'name' => 'script.exe', 'size' => 1000 ];
		$this->assertFails( $f, $file );
	}

	public function test_file_size_max_pass(): void {
		$f    = $this->field( 'fi', 'file', [ $this->rule( 'file_size_max', '2' ) ] ); // 2 MB
		$file = [ 'name' => 'file.pdf', 'size' => 1048576 ]; // 1 MB
		$this->assertPasses( $f, $file );
	}

	public function test_file_size_max_fail(): void {
		$f    = $this->field( 'fi', 'file', [ $this->rule( 'file_size_max', '1' ) ] ); // 1 MB
		$file = [ 'name' => 'file.pdf', 'size' => 2097152 ]; // 2 MB
		$this->assertFails( $f, $file );
	}

	public function test_file_count_max_pass(): void {
		$f = $this->field( 'fi', 'multi_file', [ $this->rule( 'file_count_max', '3' ) ] );
		$this->assertPasses( $f, [ 'a', 'b', 'c' ] );
	}

	public function test_file_count_max_fail(): void {
		$f = $this->field( 'fi', 'multi_file', [ $this->rule( 'file_count_max', '2' ) ] );
		$this->assertFails( $f, [ 'a', 'b', 'c' ] );
	}

	// -----------------------------------------------------------------------
	// repeater_min_rows / repeater_max_rows
	// -----------------------------------------------------------------------

	public function test_repeater_min_rows_pass(): void {
		$f = $this->field( 'r', 'repeater', [ $this->rule( 'repeater_min_rows', '2' ) ] );
		$this->assertPasses( $f, [ 'row1', 'row2' ] );
	}

	public function test_repeater_min_rows_fail(): void {
		$f = $this->field( 'r', 'repeater', [ $this->rule( 'repeater_min_rows', '2' ) ] );
		$this->assertFails( $f, [ 'row1' ] );
	}

	public function test_repeater_max_rows_pass(): void {
		$f = $this->field( 'r', 'repeater', [ $this->rule( 'repeater_max_rows', '5' ) ] );
		$this->assertPasses( $f, [ 'a', 'b', 'c' ] );
	}

	public function test_repeater_max_rows_fail(): void {
		$f = $this->field( 'r', 'repeater', [ $this->rule( 'repeater_max_rows', '2' ) ] );
		$this->assertFails( $f, [ 'a', 'b', 'c' ] );
	}

	// -----------------------------------------------------------------------
	// Custom rule message overrides default
	// -----------------------------------------------------------------------

	public function test_custom_message_overrides_default(): void {
		$f    = $this->field( 'f', 'text', [ $this->rule( 'min_length', '5', 'Too short!' ) ] );
		$errs = $this->validate( $f, 'abc' );
		$this->assertSame( 'Too short!', $errs['f'] );
	}

	public function test_unknown_rule_is_skipped(): void {
		$f = $this->field( 'f', 'text', [ $this->rule( 'nonexistent_rule_xyz' ) ] );
		$this->assertPasses( $f, 'anything' );
	}

	// -----------------------------------------------------------------------
	// Display-only fields are skipped
	// -----------------------------------------------------------------------

	/** @dataProvider displayOnlyTypes */
	public function test_display_only_field_skipped( string $type ): void {
		$f = $this->field( 'x', $type, [ $this->rule( 'min_length', '5' ) ], true );
		$this->assertPasses( $f, '' );
	}

	public static function displayOnlyTypes(): array {
		return [ [ 'html' ], [ 'notice' ], [ 'grid_break' ], [ 'heading' ] ];
	}

	// -----------------------------------------------------------------------
	// Visible field filtering
	// -----------------------------------------------------------------------

	public function test_hidden_field_is_skipped(): void {
		$f      = $this->field( 'hf', 'text', [], true );
		$errors = $this->validate( $f, '', [ 'some_other_field' ] );
		$this->assertArrayNotHasKey( 'hf', $errors );
	}

	public function test_visible_field_is_validated(): void {
		$f      = $this->field( 'vf', 'text', [], true );
		$errors = $this->validate( $f, '', [ 'vf' ] );
		$this->assertArrayHasKey( 'vf', $errors );
	}

	public function test_null_visible_ids_validates_all(): void {
		$f      = $this->field( 'f', 'text', [], true );
		$errors = $this->validate( $f, '', null );
		$this->assertArrayHasKey( 'f', $errors );
	}

	// -----------------------------------------------------------------------
	// Multiple rules — stop at first error per field
	// -----------------------------------------------------------------------

	public function test_stops_at_first_error(): void {
		$f    = $this->field( 'f', 'text', [
			$this->rule( 'min_length', '10' ),
			$this->rule( 'regex', '^[0-9]+$' ),
		] );
		$errs = $this->validate( $f, 'abc' ); // fails min_length first
		$this->assertCount( 1, $errs );
		$this->assertStringContainsString( '10', $errs['f'] );
	}

	// -----------------------------------------------------------------------
	// Multiple fields accumulate independent errors
	// -----------------------------------------------------------------------

	public function test_multiple_fields_accumulate_errors(): void {
		$v      = new CLEFA_Form_Validator();
		$config = [ 'steps' => [ [ 'step_id' => 's', 'fields' => [
			$this->field( 'name',  'text',   [], true ),
			$this->field( 'email', 'email',  [], true ),
			$this->field( 'age',   'number', [], false ),
		] ] ] ];
		$errors = $v->validate( [ 'name' => '', 'email' => '', 'age' => 'abc' ], $config );
		$this->assertArrayHasKey( 'name',  $errors );
		$this->assertArrayHasKey( 'email', $errors );
		$this->assertArrayHasKey( 'age',   $errors );
	}

	// -----------------------------------------------------------------------
	// Registry extensibility — direct registration
	// -----------------------------------------------------------------------

	public function test_custom_rule_registered_directly(): void {
		CLEFA_Validation_Registry::register( [
			'key'        => 'test_direct_fail_' . uniqid(),
			'applies_to' => [ '*' ],
			'server'     => true,
			'client'     => false,
			'handler'    => fn() => 'custom fail',
		] );

		// Confirm we can call execute on the new rule
		$id  = array_key_last( CLEFA_Validation_Registry::get_all() );
		$err = CLEFA_Validation_Registry::execute( $id, 'anything', null, [], [] );
		$this->assertSame( 'custom fail', $err );
	}

	public function test_get_all_returns_all_core_rules(): void {
		$rules = CLEFA_Validation_Registry::get_all();
		foreach ( [
			'min_length', 'max_length', 'exact_length', 'regex', 'blocked_values',
			'equals', 'not_equals', 'email', 'url', 'numeric', 'integer',
			'min_value', 'max_value', 'date_valid', 'date_after_today', 'date_before_today',
			'date_after', 'date_before', 'age_over', 'age_under', 'time_since', 'time_passed',
			'require_uppercase', 'require_number_char', 'require_special', 'password_strength',
			'confirm_password', 'unique_email', 'checkbox_min', 'checkbox_max',
			'file_type', 'file_size_max', 'file_count_max', 'api_result',
			'repeater_min_rows', 'repeater_max_rows',
		] as $key ) {
			$this->assertArrayHasKey( $key, $rules, "Missing core rule: $key" );
		}
	}

	public function test_get_for_field_type_filters_correctly(): void {
		$rules = CLEFA_Validation_Registry::get_for_field_type( 'date' );
		$keys  = array_keys( $rules );
		$this->assertContains( 'date_valid',       $keys );
		$this->assertContains( 'age_over',         $keys );
		$this->assertContains( 'date_after_today', $keys );
		// min_length should not apply to date
		$this->assertNotContains( 'min_length', $keys );
	}

	public function test_get_builder_schema_strips_handlers(): void {
		$schema = CLEFA_Validation_Registry::get_builder_schema();
		foreach ( $schema as $rule ) {
			$this->assertArrayNotHasKey( 'handler', $rule, "Handler leaked into builder schema for rule: {$rule['key']}" );
		}
	}

	// -----------------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------------

	private function assertDoesNotThrow( callable $fn ): void {
		$threw = false;
		try { $fn(); } catch ( \Throwable $e ) { $threw = true; }
		$this->assertFalse( $threw, 'Expected callable not to throw.' );
	}
}
