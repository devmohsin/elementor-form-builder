<?php

use PHPUnit\Framework\TestCase;

class FormSanitizerTest extends TestCase {

	public function test_sanitize_email_field() : void {
		$field = array( 'field_id' => 'e', 'field_type' => 'email' );
		$result = CLEFA_Form_Sanitizer::sanitize_field( '  User@Example.COM  ', $field );
		$this->assertSame( 'User@Example.COM', $result );
	}

	public function test_sanitize_number_integer() : void {
		$field = array( 'field_id' => 'n', 'field_type' => 'number', 'validation' => array( 'integer_only' => true ) );
		$this->assertSame( 42, CLEFA_Form_Sanitizer::sanitize_field( '42.9', $field ) );
	}

	public function test_sanitize_checkbox_array() : void {
		$field = array( 'field_id' => 'cb', 'field_type' => 'checkbox' );
		$result = CLEFA_Form_Sanitizer::sanitize_field( array( 'a', 'b' ), $field );
		$this->assertSame( array( 'a', 'b' ), $result );
	}

	public function test_sanitize_strips_script_from_textarea() : void {
		$field = array( 'field_id' => 't', 'field_type' => 'textarea' );
		$result = CLEFA_Form_Sanitizer::sanitize_field( '<script>x</script>hello', $field );
		$this->assertStringNotContainsString( '<script>', $result );
	}

	public function test_sanitize_full_form_skips_unknown_fields() : void {
		$config = array(
			'steps' => array( array(
				'fields' => array(
					array( 'field_id' => 'known', 'field_type' => 'text' ),
				),
			) ),
		);
		$result = CLEFA_Form_Sanitizer::sanitize( array( 'known' => 'val', 'unknown' => 'x' ), $config );
		$this->assertArrayHasKey( 'known', $result );
		$this->assertArrayNotHasKey( 'unknown', $result );
	}

	public function test_invalid_date_returns_empty() : void {
		$field = array( 'field_id' => 'd', 'field_type' => 'date' );
		$this->assertSame( '', CLEFA_Form_Sanitizer::sanitize_field( 'not-a-date', $field ) );
	}

	public function test_valid_date_preserved() : void {
		$field = array( 'field_id' => 'd', 'field_type' => 'date' );
		$this->assertSame( '2024-06-01', CLEFA_Form_Sanitizer::sanitize_field( '2024-06-01', $field ) );
	}
}
