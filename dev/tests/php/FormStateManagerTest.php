<?php
/**
 * Tests for CLEFA_Form_State_Manager
 *
 * Covers: save/get/clear draft for logged-in users, guest (user_id=0) guard,
 * draft structure, sequential saves overwrite previous drafts.
 */

class FormStateManagerTest extends \PHPUnit\Framework\TestCase {

	protected function setUp(): void {
		global $clefa_test_current_user_id, $clefa_test_user_meta;
		$clefa_test_current_user_id = 0;
		$clefa_test_user_meta       = array();
	}

	protected function tearDown(): void {
		global $clefa_test_current_user_id, $clefa_test_user_meta;
		$clefa_test_current_user_id = 0;
		$clefa_test_user_meta       = array();
	}

	// -----------------------------------------------------------------------
	// META_KEY_PREFIX
	// -----------------------------------------------------------------------

	public function test_meta_key_prefix_constant_is_defined(): void {
		$this->assertSame( '_clefa_draft_', CLEFA_Form_State_Manager::META_KEY_PREFIX );
	}

	// -----------------------------------------------------------------------
	// save_draft — guest guard
	// -----------------------------------------------------------------------

	public function test_save_draft_returns_false_for_guest(): void {
		global $clefa_test_current_user_id;
		$clefa_test_current_user_id = 0;

		$result = CLEFA_Form_State_Manager::save_draft( 1, array( 'name' => 'Alice' ) );

		$this->assertFalse( $result );
	}

	// -----------------------------------------------------------------------
	// save_draft — logged-in user
	// -----------------------------------------------------------------------

	public function test_save_draft_returns_true_for_logged_in_user(): void {
		global $clefa_test_current_user_id;
		$clefa_test_current_user_id = 3;

		$result = CLEFA_Form_State_Manager::save_draft( 10, array( 'field_a' => 'value_a' ) );

		$this->assertTrue( $result );
	}

	public function test_save_draft_stores_data_in_user_meta(): void {
		global $clefa_test_current_user_id, $clefa_test_user_meta;
		$clefa_test_current_user_id = 3;

		CLEFA_Form_State_Manager::save_draft( 10, array( 'field_a' => 'hello' ) );

		$meta_key = '_clefa_draft_10';
		$this->assertArrayHasKey( 3, $clefa_test_user_meta );
		$this->assertArrayHasKey( $meta_key, $clefa_test_user_meta[3] );

		$stored = $clefa_test_user_meta[3][ $meta_key ];
		$this->assertSame( array( 'field_a' => 'hello' ), $stored['data'] );
		$this->assertSame( 10, $stored['form_id'] );
	}

	public function test_save_draft_includes_saved_at_timestamp(): void {
		global $clefa_test_current_user_id;
		$clefa_test_current_user_id = 3;

		CLEFA_Form_State_Manager::save_draft( 10, array() );

		global $clefa_test_user_meta;
		$stored = $clefa_test_user_meta[3]['_clefa_draft_10'];
		$this->assertArrayHasKey( 'saved_at', $stored );
		$this->assertNotEmpty( $stored['saved_at'] );
	}

	public function test_save_draft_overwrites_previous_draft(): void {
		global $clefa_test_current_user_id;
		$clefa_test_current_user_id = 3;

		CLEFA_Form_State_Manager::save_draft( 10, array( 'step' => '1' ) );
		CLEFA_Form_State_Manager::save_draft( 10, array( 'step' => '2' ) );

		global $clefa_test_user_meta;
		$stored = $clefa_test_user_meta[3]['_clefa_draft_10'];
		$this->assertSame( '2', $stored['data']['step'] );
	}

	// -----------------------------------------------------------------------
	// get_draft — guest guard
	// -----------------------------------------------------------------------

	public function test_get_draft_returns_null_for_guest(): void {
		global $clefa_test_current_user_id;
		$clefa_test_current_user_id = 0;

		$this->assertNull( CLEFA_Form_State_Manager::get_draft( 1 ) );
	}

	// -----------------------------------------------------------------------
	// get_draft — logged-in user
	// -----------------------------------------------------------------------

	public function test_get_draft_returns_null_when_no_draft_saved(): void {
		global $clefa_test_current_user_id;
		$clefa_test_current_user_id = 3;

		$this->assertNull( CLEFA_Form_State_Manager::get_draft( 99 ) );
	}

	public function test_get_draft_returns_saved_data(): void {
		global $clefa_test_current_user_id;
		$clefa_test_current_user_id = 3;

		$data = array( 'first_name' => 'Alice', 'email' => 'alice@example.com' );
		CLEFA_Form_State_Manager::save_draft( 10, $data );

		$draft = CLEFA_Form_State_Manager::get_draft( 10 );

		$this->assertNotNull( $draft );
		$this->assertSame( $data, $draft['data'] );
	}

	public function test_get_draft_returns_null_for_different_form_id(): void {
		global $clefa_test_current_user_id;
		$clefa_test_current_user_id = 3;

		CLEFA_Form_State_Manager::save_draft( 10, array( 'x' => '1' ) );

		$this->assertNull( CLEFA_Form_State_Manager::get_draft( 11 ) );
	}

	public function test_get_draft_is_isolated_per_user(): void {
		global $clefa_test_current_user_id;

		$clefa_test_current_user_id = 3;
		CLEFA_Form_State_Manager::save_draft( 10, array( 'user' => 'three' ) );

		$clefa_test_current_user_id = 4;
		$this->assertNull( CLEFA_Form_State_Manager::get_draft( 10 ) );
	}

	// -----------------------------------------------------------------------
	// clear_draft
	// -----------------------------------------------------------------------

	public function test_clear_draft_does_nothing_for_guest(): void {
		global $clefa_test_current_user_id;
		$clefa_test_current_user_id = 0;

		// Should not throw.
		CLEFA_Form_State_Manager::clear_draft( 1 );
		$this->assertTrue( true );
	}

	public function test_clear_draft_removes_saved_draft(): void {
		global $clefa_test_current_user_id;
		$clefa_test_current_user_id = 3;

		CLEFA_Form_State_Manager::save_draft( 10, array( 'field' => 'value' ) );
		CLEFA_Form_State_Manager::clear_draft( 10 );

		$this->assertNull( CLEFA_Form_State_Manager::get_draft( 10 ) );
	}

	public function test_clear_draft_does_not_affect_other_forms(): void {
		global $clefa_test_current_user_id;
		$clefa_test_current_user_id = 3;

		CLEFA_Form_State_Manager::save_draft( 10, array( 'a' => '1' ) );
		CLEFA_Form_State_Manager::save_draft( 11, array( 'b' => '2' ) );
		CLEFA_Form_State_Manager::clear_draft( 10 );

		$this->assertNull( CLEFA_Form_State_Manager::get_draft( 10 ) );
		$this->assertNotNull( CLEFA_Form_State_Manager::get_draft( 11 ) );
	}

	// -----------------------------------------------------------------------
	// Round-trip: save → get → clear → get returns null
	// -----------------------------------------------------------------------

	public function test_full_draft_lifecycle(): void {
		global $clefa_test_current_user_id;
		$clefa_test_current_user_id = 5;

		$data = array( 'step_1_field' => 'hello', 'step_2_field' => 'world' );

		$saved = CLEFA_Form_State_Manager::save_draft( 42, $data );
		$this->assertTrue( $saved );

		$retrieved = CLEFA_Form_State_Manager::get_draft( 42 );
		$this->assertSame( $data, $retrieved['data'] );

		CLEFA_Form_State_Manager::clear_draft( 42 );
		$this->assertNull( CLEFA_Form_State_Manager::get_draft( 42 ) );
	}
}
