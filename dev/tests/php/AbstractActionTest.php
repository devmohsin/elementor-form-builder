<?php

use PHPUnit\Framework\TestCase;

/** Exposes protected token helpers for unit testing. */
class CLEFA_Testable_Redirect_Action extends CLEFA_Redirect_Action {
	public function expose_resolve_token( $value, array $data, array $form_config, $submission_id = 0 ) {
		return $this->resolve_token( $value, $data, $form_config, $submission_id );
	}
	public function expose_resolve_all_tokens( array $config_item, array $data, array $form_config, $submission_id = 0 ) {
		return $this->resolve_all_tokens( $config_item, $data, $form_config, $submission_id );
	}
}

class AbstractActionTest extends TestCase {

	private function action() : CLEFA_Testable_Redirect_Action {
		return new CLEFA_Testable_Redirect_Action();
	}

	private function form_config() : array {
		return array(
			'form_name' => 'Contact',
			'id'        => 42,
		);
	}

	public function test_resolve_field_token() : void {
		$url = $this->action()->expose_resolve_token(
			'https://example.com/?email={field:email}',
			array( 'email' => 'user@test.com' ),
			$this->form_config()
		);
		$this->assertSame( 'https://example.com/?email=user@test.com', $url );
	}

	public function test_resolve_form_and_site_tokens() : void {
		$text = $this->action()->expose_resolve_token(
			'{form:name} on {site:name} ({form:id})',
			array(),
			$this->form_config()
		);
		$this->assertSame( 'Contact on Test Site (42)', $text );
	}

	public function test_resolve_date_time_tokens() : void {
		$text = $this->action()->expose_resolve_token( '{date} {time}', array(), $this->form_config() );
		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $text );
	}

	public function test_resolve_submission_id_token() : void {
		$text = $this->action()->expose_resolve_token( 'sub-{submission_id}', array(), $this->form_config(), 99 );
		$this->assertSame( 'sub-99', $text );
	}

	public function test_resolve_random_token() : void {
		$text = $this->action()->expose_resolve_token( 'tok-{random_token}', array(), $this->form_config() );
		$this->assertStringStartsWith( 'tok-', $text );
		$this->assertGreaterThan( 10, strlen( $text ) );
	}

	public function test_resolve_query_param_token() : void {
		$_GET['ref'] = 'campaign';
		$text = $this->action()->expose_resolve_token( 'ref={query_param:ref}', array(), $this->form_config() );
		$this->assertSame( 'ref=campaign', $text );
		unset( $_GET['ref'] );
	}

	public function test_resolve_all_tokens_on_config_array() : void {
		$result = $this->action()->expose_resolve_all_tokens(
			array( 'url' => 'https://x.com/{field:name}', 'count' => 3 ),
			array( 'name' => 'Ada' ),
			$this->form_config()
		);
		$this->assertSame( 'https://x.com/Ada', $result['url'] );
		$this->assertSame( 3, $result['count'] );
	}

	public function test_redirect_action_run_resolves_url() : void {
		$action = new CLEFA_Redirect_Action();
		$result = $action->run(
			array( 'id' => '7' ),
			array( 'id' => 1 ),
			0,
			array( 'redirect_url' => 'https://example.com/thanks/{field:id}' )
		);
		$this->assertTrue( $result['success'] );
		$this->assertSame( 'https://example.com/thanks/7', $result['redirect_url'] );
	}

	public function test_redirect_action_fails_without_url() : void {
		$result = ( new CLEFA_Redirect_Action() )->run( array(), array(), 0, array() );
		$this->assertFalse( $result['success'] );
	}
}
