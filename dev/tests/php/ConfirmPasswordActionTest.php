<?php
/**
 * Tests for CLEFA_Confirm_Password_Action
 *
 * Covers: matching passwords, mismatch error, every strength requirement,
 * fail_hard propagation, custom messages, and the happy-path return.
 */

class ConfirmPasswordActionTest extends \PHPUnit\Framework\TestCase {

	private CLEFA_Confirm_Password_Action $action;

	protected function setUp(): void {
		$this->action = new CLEFA_Confirm_Password_Action();
	}

	// -----------------------------------------------------------------------
	// Happy path
	// -----------------------------------------------------------------------

	public function test_matching_passwords_pass(): void {
		$result = $this->action->run(
			array( 'pw' => 'MyPass1!', 'pw2' => 'MyPass1!' ),
			array(),
			0,
			array( 'password_field' => 'pw', 'confirm_field' => 'pw2' )
		);

		$this->assertTrue( $result['success'] );
		$this->assertArrayNotHasKey( 'errors', $result );
	}

	// -----------------------------------------------------------------------
	// Mismatch
	// -----------------------------------------------------------------------

	public function test_mismatching_passwords_return_mismatch_error(): void {
		$result = $this->action->run(
			array( 'pw' => 'abc', 'pw2' => 'xyz' ),
			array(),
			0,
			array( 'password_field' => 'pw', 'confirm_field' => 'pw2' )
		);

		$this->assertFalse( $result['success'] );
		$this->assertArrayHasKey( 'mismatch', $result['errors'] );
	}

	public function test_custom_mismatch_message_is_used(): void {
		$result = $this->action->run(
			array( 'pw' => 'a', 'pw2' => 'b' ),
			array(),
			0,
			array(
				'password_field'   => 'pw',
				'confirm_field'    => 'pw2',
				'mismatch_message' => 'They must match!',
			)
		);

		$this->assertSame( 'They must match!', $result['errors']['mismatch'] );
	}

	// -----------------------------------------------------------------------
	// Min length
	// -----------------------------------------------------------------------

	public function test_default_min_length_8_fails_for_short_password(): void {
		$result = $this->action->run(
			array( 'password' => 'abc', 'confirm_password' => 'abc' ),
			array(),
			0,
			array()
		);

		$this->assertFalse( $result['success'] );
		$this->assertArrayHasKey( 'strength', $result['errors'] );
	}

	public function test_custom_min_length_respected(): void {
		$result = $this->action->run(
			array( 'pw' => 'abcde', 'pw2' => 'abcde' ),
			array(),
			0,
			array( 'password_field' => 'pw', 'confirm_field' => 'pw2', 'min_length' => 4 )
		);

		// 'abcde' is 5 chars, min is 4 → should pass length check.
		// No other strength requirements set, so success.
		$this->assertTrue( $result['success'] );
	}

	public function test_password_exactly_at_min_length_passes(): void {
		$result = $this->action->run(
			array( 'password' => '12345678', 'confirm_password' => '12345678' ),
			array(),
			0,
			array() // default min_length = 8
		);

		$this->assertTrue( $result['success'] );
	}

	// -----------------------------------------------------------------------
	// require_upper
	// -----------------------------------------------------------------------

	public function test_require_upper_fails_when_no_uppercase(): void {
		$result = $this->action->run(
			array( 'pw' => 'alllower1!', 'pw2' => 'alllower1!' ),
			array(),
			0,
			array( 'password_field' => 'pw', 'confirm_field' => 'pw2', 'require_upper' => true )
		);

		$this->assertFalse( $result['success'] );
		$this->assertArrayHasKey( 'strength', $result['errors'] );
	}

	public function test_require_upper_passes_with_uppercase(): void {
		$result = $this->action->run(
			array( 'pw' => 'HasUpper1!', 'pw2' => 'HasUpper1!' ),
			array(),
			0,
			array( 'password_field' => 'pw', 'confirm_field' => 'pw2', 'require_upper' => true )
		);

		$this->assertTrue( $result['success'] );
	}

	// -----------------------------------------------------------------------
	// require_lower
	// -----------------------------------------------------------------------

	public function test_require_lower_fails_when_no_lowercase(): void {
		$result = $this->action->run(
			array( 'pw' => 'ALLUPPER1!', 'pw2' => 'ALLUPPER1!' ),
			array(),
			0,
			array( 'password_field' => 'pw', 'confirm_field' => 'pw2', 'require_lower' => true )
		);

		$this->assertFalse( $result['success'] );
	}

	// -----------------------------------------------------------------------
	// require_number
	// -----------------------------------------------------------------------

	public function test_require_number_fails_when_no_digit(): void {
		$result = $this->action->run(
			array( 'pw' => 'NoDigitHere!', 'pw2' => 'NoDigitHere!' ),
			array(),
			0,
			array( 'password_field' => 'pw', 'confirm_field' => 'pw2', 'require_number' => true )
		);

		$this->assertFalse( $result['success'] );
	}

	public function test_require_number_passes_with_digit(): void {
		$result = $this->action->run(
			array( 'pw' => 'Has1Number!', 'pw2' => 'Has1Number!' ),
			array(),
			0,
			array( 'password_field' => 'pw', 'confirm_field' => 'pw2', 'require_number' => true )
		);

		$this->assertTrue( $result['success'] );
	}

	// -----------------------------------------------------------------------
	// require_special
	// -----------------------------------------------------------------------

	public function test_require_special_fails_without_special_char(): void {
		$result = $this->action->run(
			array( 'pw' => 'NoSpecial1a', 'pw2' => 'NoSpecial1a' ),
			array(),
			0,
			array( 'password_field' => 'pw', 'confirm_field' => 'pw2', 'require_special' => true )
		);

		$this->assertFalse( $result['success'] );
	}

	public function test_require_special_passes_with_special_char(): void {
		$result = $this->action->run(
			array( 'pw' => 'Has@Special1', 'pw2' => 'Has@Special1' ),
			array(),
			0,
			array( 'password_field' => 'pw', 'confirm_field' => 'pw2', 'require_special' => true )
		);

		$this->assertTrue( $result['success'] );
	}

	// -----------------------------------------------------------------------
	// All strength rules together
	// -----------------------------------------------------------------------

	public function test_all_strength_rules_pass_for_strong_password(): void {
		$result = $this->action->run(
			array( 'pw' => 'Str0ng!Pass', 'pw2' => 'Str0ng!Pass' ),
			array(),
			0,
			array(
				'password_field'  => 'pw',
				'confirm_field'   => 'pw2',
				'min_length'      => 8,
				'require_upper'   => true,
				'require_lower'   => true,
				'require_number'  => true,
				'require_special' => true,
			)
		);

		$this->assertTrue( $result['success'] );
	}

	// -----------------------------------------------------------------------
	// Custom strength message
	// -----------------------------------------------------------------------

	public function test_custom_strength_message_overrides_default(): void {
		$result = $this->action->run(
			array( 'pw' => 'weak', 'pw2' => 'weak' ),
			array(),
			0,
			array(
				'password_field'   => 'pw',
				'confirm_field'    => 'pw2',
				'strength_message' => 'Your password is not strong enough.',
			)
		);

		$this->assertSame( 'Your password is not strong enough.', $result['errors']['strength'] );
	}

	// -----------------------------------------------------------------------
	// fail_hard flag
	// -----------------------------------------------------------------------

	public function test_fail_hard_false_does_not_set_fatal(): void {
		$result = $this->action->run(
			array( 'pw' => 'abc', 'pw2' => 'xyz' ),
			array(),
			0,
			array( 'password_field' => 'pw', 'confirm_field' => 'pw2', 'fail_hard' => false )
		);

		$this->assertEmpty( $result['fatal'] ?? false );
	}

	public function test_fail_hard_true_sets_fatal(): void {
		$result = $this->action->run(
			array( 'pw' => 'abc', 'pw2' => 'xyz' ),
			array(),
			0,
			array( 'password_field' => 'pw', 'confirm_field' => 'pw2', 'fail_hard' => true )
		);

		$this->assertTrue( $result['fatal'] );
	}

	// -----------------------------------------------------------------------
	// Default field names
	// -----------------------------------------------------------------------

	public function test_default_field_names_password_and_confirm_password(): void {
		$result = $this->action->run(
			array( 'password' => 'LongEnough1', 'confirm_password' => 'LongEnough1' ),
			array(),
			0,
			array() // uses defaults
		);

		$this->assertTrue( $result['success'] );
	}

	public function test_missing_confirm_field_value_is_mismatch(): void {
		$result = $this->action->run(
			array( 'password' => 'LongEnough1' ),
			array(),
			0,
			array()
		);

		$this->assertFalse( $result['success'] );
		$this->assertArrayHasKey( 'mismatch', $result['errors'] );
	}

	// -----------------------------------------------------------------------
	// Both mismatch and strength errors present simultaneously
	// -----------------------------------------------------------------------

	public function test_both_mismatch_and_strength_errors_can_coexist(): void {
		$result = $this->action->run(
			array( 'pw' => 'short', 'pw2' => 'different' ),
			array(),
			0,
			array( 'password_field' => 'pw', 'confirm_field' => 'pw2' )
		);

		$this->assertFalse( $result['success'] );
		$this->assertArrayHasKey( 'mismatch', $result['errors'] );
		$this->assertArrayHasKey( 'strength', $result['errors'] );
	}
}
