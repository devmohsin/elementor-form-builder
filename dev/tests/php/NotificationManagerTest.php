<?php

use PHPUnit\Framework\TestCase;

class NotificationManagerTest extends TestCase {

	private function invoke_private_static( string $method, array $args = array() ) {
		$ref = new ReflectionMethod( CLEFA_Notification_Manager::class, $method );
		$ref->setAccessible( true );
		return $ref->invokeArgs( null, $args );
	}

	private function invoke_private( string $method, array $args = array() ) {
		$obj = new CLEFA_Notification_Manager();
		$ref = new ReflectionMethod( CLEFA_Notification_Manager::class, $method );
		$ref->setAccessible( true );
		return $ref->invokeArgs( $obj, $args );
	}

	public function test_build_default_body_lists_fields() : void {
		$form_config = array(
			'form_name' => 'Signup',
			'steps'     => array(
				array(
					'fields' => array(
						array( 'field_id' => 'name', 'label' => 'Name', 'field_type' => 'text' ),
						array( 'field_id' => 'note', 'label' => 'Note', 'field_type' => 'html' ),
						array( 'field_id' => 'tags', 'label' => 'Tags', 'field_type' => 'checkbox' ),
					),
				),
			),
		);
		$data = array( 'name' => 'Ada', 'tags' => array( 'a', 'b' ) );
		$body = $this->invoke_private_static( 'build_default_body', array( $data, $form_config ) );
		$this->assertStringContainsString( 'Signup', $body );
		$this->assertStringContainsString( 'Name:', $body );
		$this->assertStringContainsString( 'Ada', $body );
		$this->assertStringContainsString( 'a, b', $body );
		$this->assertStringNotContainsString( 'Note:', $body );
	}

	public function test_resolve_replaces_field_tokens() : void {
		$text = $this->invoke_private( 'resolve', array(
			'Hello {field:name}, form {form:name}',
			array( 'name' => 'Bob' ),
			array( 'form_name' => 'Contact', 'id' => 5 ),
			12,
		) );
		$this->assertSame( 'Hello Bob, form Contact', $text );
	}

	public function test_resolve_replaces_static_tokens() : void {
		$text = $this->invoke_private( 'resolve', array(
			'{site:name} / {admin:email} / sub {submission:id}',
			array(),
			array( 'id' => 1 ),
			88,
		) );
		$this->assertStringContainsString( 'Test Site', $text );
		$this->assertStringContainsString( 'admin@example.com', $text );
		$this->assertStringContainsString( '88', $text );
	}

	public function test_resolve_recipients_admin() : void {
		$list = $this->invoke_private_static( 'resolve_recipients', array(
			array( 'recipient_type' => 'admin' ),
			array(),
			array(),
		) );
		$this->assertSame( array( 'admin@example.com' ), $list );
	}

	public function test_resolve_recipients_custom() : void {
		$list = $this->invoke_private_static( 'resolve_recipients', array(
			array( 'recipient_type' => 'custom', 'custom_emails' => 'a@test.com, b@test.com' ),
			array(),
			array(),
		) );
		$this->assertCount( 2, $list );
		$this->assertContains( 'a@test.com', $list );
	}
}
