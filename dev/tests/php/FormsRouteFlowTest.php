<?php
/**
 * Programmatic admin-builder flows through CLEFA_Forms_Route.
 *
 * Covers create, update, reload, publish, duplicate, and stale-ID failures
 * that the submission-only suites never exercised.
 */

require_once __DIR__ . '/suite/BuilderAdminFlowTestRunner.php';

class FormsRouteFlowTest extends PHPUnit\Framework\TestCase {

	private CLEFA_Builder_Admin_Flow_Test_Runner $runner;

	protected function setUp(): void {
		global $clefa_test_current_user_id, $clefa_test_user_caps;

		clefa_test_reset_forms_store();
		clefa_test_reset_action_stores();
		CLEFA_Tables::$mock_form = null;

		$clefa_test_current_user_id = 1;
		$clefa_test_user_caps       = array(
			1 => array(
				'manage_options' => true,
			),
		);

		$this->runner = new CLEFA_Builder_Admin_Flow_Test_Runner();
	}

	protected function tearDown(): void {
		global $clefa_test_current_user_id, $clefa_test_user_caps;

		clefa_test_reset_forms_store();
		CLEFA_Tables::$mock_form        = null;
		$clefa_test_current_user_id     = 0;
		$clefa_test_user_caps           = array();
	}

	private function sample_config( array $overrides = array() ): array {
		$config = CLEFA_Programmatic_Form_Builder::form(
			'Admin Flow Form',
			array(
				CLEFA_Programmatic_Form_Builder::field( 'email', 'email', array( 'required' => true ) ),
				CLEFA_Programmatic_Form_Builder::field( 'notes', 'textarea' ),
			),
			array(
				CLEFA_Programmatic_Form_Builder::action( 'save_submission' ),
			)
		);

		return array_replace_recursive( $config, $overrides );
	}

	public function test_create_form_returns_persisted_id_and_default_step(): void {
		$result = $this->runner->create_form();

		$this->assertTrue( $result['success'] );
		$this->assertGreaterThan( 0, (int) $result['form_id'] );
		$this->assertSame( 'Programmatic Form', $result['form']['form_name'] );
		$this->assertNotEmpty( $result['form']['config']['steps'] );
	}

	public function test_new_form_save_flow_create_then_update(): void {
		$config = $this->sample_config();
		$flow   = $this->runner->simulate_new_form_save( $config );

		$this->assertTrue( $flow['create']['success'] );
		$this->assertTrue( $flow['save']['success'] );
		$this->assertTrue( $flow['form']['success'] );

		$form = $flow['form']['form'];
		$this->assertSame( 'Admin Flow Form', $form['form_name'] );
		$this->assertTrue( $form['config']['steps'][0]['fields'][0]['required'] );
		$this->assertSame( 2, (int) $form['version'] );
	}

	public function test_existing_form_save_updates_config_and_increments_version(): void {
		$create  = $this->runner->create_form( array( 'form_name' => 'Versioned Form' ) );
		$form_id = (int) $create['form_id'];

		$config = $this->sample_config( array( 'form_name' => 'Versioned Form Updated' ) );
		$save   = $this->runner->save_form( $form_id, $config );
		$reload = $this->runner->get_form( $form_id );

		$this->assertTrue( $save['success'] );
		$this->assertSame( 'Versioned Form Updated', $reload['form']['form_name'] );
		$this->assertSame( 2, (int) $reload['form']['version'] );
		$this->assertNotEmpty( $reload['form']['normalized_config_json'] );
		$this->assertNotEmpty( $reload['form']['feature_map_json'] );
	}

	public function test_save_missing_form_returns_not_found(): void {
		$result = $this->runner->save_form( 404, $this->sample_config() );

		$this->assertTrue( $result['_error'] );
		$this->assertSame( 'not_found', $result['code'] );
		$this->assertSame( 'Form not found.', $result['message'] );
	}

	public function test_get_missing_form_returns_not_found(): void {
		$result = $this->runner->get_form( 999 );

		$this->assertTrue( $result['_error'] );
		$this->assertSame( 'not_found', $result['code'] );
	}

	public function test_publish_toggles_draft_to_published(): void {
		$create  = $this->runner->create_form();
		$form_id = (int) $create['form_id'];

		$published = $this->runner->publish_form( $form_id );
		$reload    = $this->runner->get_form( $form_id );

		$this->assertSame( 'published', $published['status'] );
		$this->assertSame( 'published', $reload['form']['status'] );

		$draft = $this->runner->publish_form( $form_id );
		$this->assertSame( 'draft', $draft['status'] );
	}

	public function test_duplicate_form_creates_new_row(): void {
		$config  = $this->sample_config();
		$flow    = $this->runner->simulate_new_form_save( $config );
		$form_id = (int) $flow['create']['form_id'];

		$duplicate = $this->runner->duplicate_form( $form_id );

		$this->assertTrue( $duplicate['success'] );
		$this->assertGreaterThan( $form_id, (int) $duplicate['form_id'] );

		$copy = $this->runner->get_form( (int) $duplicate['form_id'] );
		$this->assertSame( 'Admin Flow Form (Copy)', $copy['form']['form_name'] );
		$this->assertSame( 2, count( $copy['form']['config']['steps'][0]['fields'] ) );
	}

	public function test_stale_url_form_id_can_be_recreated_after_not_found(): void {
		$missing = $this->runner->save_form( 77, $this->sample_config() );
		$this->assertSame( 'not_found', $missing['code'] );

		$created = $this->runner->create_form(
			array(
				'form_name' => 'Recovered Form',
				'form_type' => 'standard',
			)
		);

		$this->assertTrue( $created['success'] );
		$this->assertNotSame( 77, (int) $created['form_id'] );
	}

	public function test_required_field_toggle_persists_through_save_reload(): void {
		$create  = $this->runner->create_form( array( 'form_name' => 'Required Toggle Form' ) );
		$form_id = (int) $create['form_id'];

		$config = CLEFA_Programmatic_Form_Builder::form(
			'Required Toggle Form',
			array(
				CLEFA_Programmatic_Form_Builder::field( 'name', 'text', array( 'required' => true ) ),
			)
		);

		$this->runner->save_form( $form_id, $config );
		$reload = $this->runner->get_form( $form_id );

		$this->assertTrue( $reload['form']['config']['steps'][0]['fields'][0]['required'] );
	}

	public function test_save_to_invalid_form_id_returns_error(): void {
		$result = $this->runner->save_form( 0, $this->sample_config() );

		$this->assertTrue( $result['_error'] );
		$this->assertSame( 'invalid_id', $result['code'] );
	}

	public function test_create_with_config_persists_fields_in_one_request(): void {
		$config = $this->sample_config( array( 'form_name' => 'Single Shot Create' ) );
		$runner = new CLEFA_Builder_Admin_Flow_Test_Runner();
		$request = new WP_REST_Request();
		$request->set_body(
			wp_json_encode(
				array(
					'form_name' => 'Single Shot Create',
					'form_type' => 'standard',
					'config'    => $config,
				)
			)
		);

		require_once CLEFA_PLUGIN_PATH . 'includes/REST/REST_Helpers.php';
		require_once CLEFA_PLUGIN_PATH . 'includes/REST/Forms_Route.php';
		$route  = new CLEFA_Forms_Route();
		$result = $route->create_form( $request );
		$data   = rest_ensure_response( $result );
		if ( is_object( $data ) && method_exists( $data, 'get_data' ) ) {
			$data = $data->get_data();
		}

		$this->assertTrue( $data['success'] );
		$this->assertSame( 2, count( $data['form']['config']['steps'][0]['fields'] ) );
	}

	public function test_admin_permission_denied_without_manage_options(): void {
		global $clefa_test_user_caps;
		$clefa_test_user_caps = array( 1 => array() );

		$route = new CLEFA_Forms_Route();
		$this->assertFalse( $route->admin_permission() );
	}
}
