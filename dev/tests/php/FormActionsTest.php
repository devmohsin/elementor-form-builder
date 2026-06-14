<?php
/**
 * PHPUnit tests for every built-in form submission action:
 * create post, register, login, meta updates, ACF, taxonomy, role, product, etc.
 */

class FormActionsTest extends \PHPUnit\Framework\TestCase {

	private array $form_config = array(
		'form_name' => 'Action Test Form',
		'id'        => 99,
	);

	protected function setUp(): void {
		clefa_test_reset_action_stores();
		wp_set_current_user( 0 );
	}

	// ------------------------------------------------------------------
	// Create Post
	// ------------------------------------------------------------------

	public function test_create_post_inserts_post_with_field_title_and_meta() : void {
		$action = new CLEFA_Create_Post_Action();
		$result = $action->run(
			array(
				'title'   => 'My Post Title',
				'body'    => '<p>Hello</p>',
				'color'   => 'red',
				'cat'     => '3',
			),
			$this->form_config,
			10,
			array(
				'post_title_field'   => 'title',
				'post_content_field' => 'body',
				'post_type'          => 'post',
				'post_status'        => 'publish',
				'meta_mappings'      => array(
					array( 'meta_key' => 'color', 'field_id' => 'color' ),
				),
				'taxonomy_mappings'  => array(
					array( 'taxonomy' => 'category', 'field_id' => 'cat' ),
				),
			)
		);

		$this->assertTrue( $result['success'] );
		$post = get_post( $result['post_id'] );
		$this->assertSame( 'My Post Title', $post->post_title );
		$this->assertSame( 'publish', $post->post_status );
		$this->assertSame( 'red', get_post_meta( $result['post_id'], 'color', true ) );

		global $clefa_test_post_terms;
		$this->assertContains( 3, $clefa_test_post_terms[ $result['post_id'] . ':category' ] );
	}

	public function test_create_post_fails_when_insert_returns_error() : void {
		$result = ( new CLEFA_Create_Post_Action() )->run(
			array( 'title' => 'FAIL_INSERT' ),
			$this->form_config,
			0,
			array( 'post_title_field' => 'title' )
		);
		$this->assertFalse( $result['success'] );
	}

	// ------------------------------------------------------------------
	// Register user
	// ------------------------------------------------------------------

	public function test_register_action_creates_user_and_meta() : void {
		$result = ( new CLEFA_Register_Action() )->run(
			array(
				'username' => 'newuser',
				'email'    => 'new@example.com',
				'password' => 'Secret123!',
				'phone'    => '555-0100',
			),
			$this->form_config,
			0,
			array(
				'username_field' => 'username',
				'email_field'    => 'email',
				'password_field' => 'password',
				'role'           => 'subscriber',
				'meta_fields'    => array(
					array( 'meta_key' => 'phone', 'field_id' => 'phone' ),
				),
			)
		);

		$this->assertTrue( $result['success'] );
		$user = get_user_by( 'id', $result['user_id'] );
		$this->assertSame( 'newuser', $user->user_login );
		$this->assertSame( '555-0100', get_user_meta( $result['user_id'], 'phone', true ) );
	}

	public function test_register_action_fails_on_duplicate_email() : void {
		wp_insert_user(
			array(
				'user_login' => 'existing',
				'user_email' => 'dup@example.com',
				'user_pass'  => 'x',
			)
		);

		$result = ( new CLEFA_Register_Action() )->run(
			array( 'email' => 'dup@example.com' ),
			$this->form_config,
			0,
			array( 'email_field' => 'email' )
		);

		$this->assertFalse( $result['success'] );
	}

	public function test_register_action_auto_login_sets_current_user() : void {
		$result = ( new CLEFA_Register_Action() )->run(
			array( 'email' => 'auto@example.com', 'password' => 'Pass1!' ),
			$this->form_config,
			0,
			array(
				'email_field'    => 'email',
				'password_field' => 'password',
				'auto_login'     => '1',
			)
		);

		$this->assertTrue( $result['success'] );
		$this->assertSame( $result['user_id'], get_current_user_id() );
	}

	// ------------------------------------------------------------------
	// Login
	// ------------------------------------------------------------------

	public function test_login_action_signs_in_user() : void {
		wp_insert_user(
			array(
				'user_login' => 'loginuser',
				'user_email' => 'login@example.com',
				'user_pass'  => 'Pass123!',
			)
		);

		$result = ( new CLEFA_Login_Action() )->run(
			array(
				'username' => 'loginuser',
				'password' => 'Pass123!',
			),
			$this->form_config,
			0,
			array(
				'username_field' => 'username',
				'password_field' => 'password',
			)
		);

		$this->assertTrue( $result['success'] );
		$this->assertSame( 'loginuser', wp_get_current_user()->user_login );
	}

	public function test_login_action_returns_already_logged_in() : void {
		wp_set_current_user( 1 );
		$result = ( new CLEFA_Login_Action() )->run( array(), $this->form_config, 0, array() );
		$this->assertTrue( $result['success'] );
		$this->assertSame( 'already_logged_in', $result['message'] );
	}

	public function test_login_action_fails_without_credentials() : void {
		$result = ( new CLEFA_Login_Action() )->run( array(), $this->form_config, 0, array() );
		$this->assertFalse( $result['success'] );
	}

	public function test_login_action_fails_on_bad_password() : void {
		wp_insert_user(
			array(
				'user_login' => 'badpw',
				'user_email' => 'bad@example.com',
				'user_pass'  => 'correct',
			)
		);

		$result = ( new CLEFA_Login_Action() )->run(
			array( 'username' => 'badpw', 'password' => 'wrong' ),
			$this->form_config,
			0,
			array(
				'username_field' => 'username',
				'password_field' => 'password',
			)
		);

		$this->assertFalse( $result['success'] );
	}

	// ------------------------------------------------------------------
	// Update post meta
	// ------------------------------------------------------------------

	public function test_update_post_meta_writes_meta_keys() : void {
		wp_set_current_user( 1 );
		$post_id = wp_insert_post( array( 'post_title' => 'Existing', 'post_status' => 'publish' ) );

		$result = ( new CLEFA_Update_Post_Meta_Action() )->run(
			array( 'score' => '95' ),
			$this->form_config,
			0,
			array(
				'post_id'       => (string) $post_id,
				'meta_mappings' => array(
					array( 'meta_key' => 'score', 'field_id' => 'score' ),
				),
			)
		);

		$this->assertTrue( $result['success'] );
		$this->assertSame( '95', get_post_meta( $post_id, 'score', true ) );
	}

	public function test_update_post_meta_fails_when_post_missing() : void {
		wp_set_current_user( 1 );
		$result = ( new CLEFA_Update_Post_Meta_Action() )->run(
			array(),
			$this->form_config,
			0,
			array( 'post_id' => '9999' )
		);
		$this->assertFalse( $result['success'] );
	}

	// ------------------------------------------------------------------
	// Update user meta
	// ------------------------------------------------------------------

	public function test_update_user_meta_requires_login_or_user_id_field() : void {
		$result = ( new CLEFA_Update_User_Meta_Action() )->run( array(), $this->form_config, 0, array() );
		$this->assertFalse( $result['success'] );
	}

	public function test_update_user_meta_updates_logged_in_user() : void {
		$user_id = wp_insert_user(
			array(
				'user_login' => 'metauser',
				'user_email' => 'meta@example.com',
				'user_pass'  => 'x',
			)
		);
		wp_set_current_user( $user_id );

		$result = ( new CLEFA_Update_User_Meta_Action() )->run(
			array( 'company' => 'Acme Corp' ),
			$this->form_config,
			0,
			array(
				'meta_mappings' => array(
					array( 'meta_key' => 'company', 'field_id' => 'company' ),
				),
			)
		);

		$this->assertTrue( $result['success'] );
		$this->assertSame( 'Acme Corp', get_user_meta( $user_id, 'company', true ) );
	}

	public function test_update_user_meta_updates_core_display_name() : void {
		$user_id = wp_insert_user(
			array(
				'user_login'   => 'coreuser',
				'user_email'   => 'core@example.com',
				'user_pass'    => 'x',
				'display_name' => 'Old Name',
			)
		);
		wp_set_current_user( $user_id );

		$result = ( new CLEFA_Update_User_Meta_Action() )->run(
			array( 'name' => 'New Name' ),
			$this->form_config,
			0,
			array(
				'core_field_mappings' => array(
					array( 'core_field' => 'display_name', 'field_id' => 'name' ),
				),
			)
		);

		$this->assertTrue( $result['success'] );
		$this->assertSame( 'New Name', get_user_by( 'id', $user_id )->display_name );
	}

	// ------------------------------------------------------------------
	// ACF
	// ------------------------------------------------------------------

	public function test_acf_action_updates_post_field() : void {
		$post_id = wp_insert_post( array( 'post_title' => 'ACF Post', 'post_status' => 'publish' ) );

		$result = ( new CLEFA_ACF_Action() )->run(
			array( 'post_id' => (string) $post_id, 'bio' => 'Hello ACF' ),
			$this->form_config,
			0,
			array(
				'target_type'     => 'post',
				'target_id_field' => 'post_id',
				'acf_field_key'   => 'bio_field',
				'value_field'     => 'bio',
			)
		);

		$this->assertTrue( $result['success'] );
		global $clefa_test_acf_fields;
		$this->assertSame( 'Hello ACF', $clefa_test_acf_fields[ (string) $post_id ]['bio_field'] );
	}

	public function test_acf_action_updates_user_field() : void {
		$user_id = wp_insert_user(
			array(
				'user_login' => 'acfuser',
				'user_email' => 'acf@example.com',
				'user_pass'  => 'x',
			)
		);
		wp_set_current_user( $user_id );

		$result = ( new CLEFA_ACF_Action() )->run(
			array( 'tier' => 'gold' ),
			$this->form_config,
			0,
			array(
				'target_type'   => 'user',
				'acf_field_key' => 'membership',
				'value_field'   => 'tier',
			)
		);

		$this->assertTrue( $result['success'] );
		global $clefa_test_acf_fields;
		$this->assertSame( 'gold', $clefa_test_acf_fields[ 'user_' . $user_id ]['membership'] );
	}

	public function test_acf_action_updates_option_field() : void {
		$result = ( new CLEFA_ACF_Action() )->run(
			array(),
			$this->form_config,
			0,
			array(
				'target_type'   => 'option',
				'acf_field_key' => 'site_note',
				'value'         => 'Updated from form',
			)
		);

		$this->assertTrue( $result['success'] );
		global $clefa_test_acf_fields;
		$this->assertSame( 'Updated from form', $clefa_test_acf_fields['option']['site_note'] );
	}

	public function test_acf_action_fails_without_field_key() : void {
		$result = ( new CLEFA_ACF_Action() )->run( array(), $this->form_config, 0, array() );
		$this->assertFalse( $result['success'] );
	}

	// ------------------------------------------------------------------
	// Taxonomy
	// ------------------------------------------------------------------

	public function test_taxonomy_action_assigns_terms_replace_mode() : void {
		wp_set_current_user( 1 );
		$post_id = wp_insert_post( array( 'post_title' => 'Tax Post', 'post_status' => 'publish' ) );
		wp_insert_term( 'News', 'category' );
		$term = get_term_by( 'name', 'News', 'category' );

		$result = ( new CLEFA_Taxonomy_Action() )->run(
			array( 'post_id' => (string) $post_id, 'terms' => 'News' ),
			$this->form_config,
			0,
			array(
				'post_id_field' => 'post_id',
				'taxonomy'      => 'category',
				'terms_field'   => 'terms',
				'mode'          => 'replace',
			)
		);

		$this->assertTrue( $result['success'] );
		global $clefa_test_post_terms;
		$this->assertContains( $term->term_id, $clefa_test_post_terms[ $post_id . ':category' ] );
	}

	public function test_taxonomy_action_creates_missing_terms_when_enabled() : void {
		wp_set_current_user( 1 );
		$post_id = wp_insert_post( array( 'post_title' => 'New Term Post', 'post_status' => 'publish' ) );

		$result = ( new CLEFA_Taxonomy_Action() )->run(
			array( 'post_id' => (string) $post_id, 'terms' => 'Brand New Term' ),
			$this->form_config,
			0,
			array(
				'post_id_field' => 'post_id',
				'taxonomy'      => 'category',
				'terms_field'   => 'terms',
				'create_terms'  => true,
			)
		);

		$this->assertTrue( $result['success'] );
		$this->assertNotEmpty( $result['term_ids'] );
	}

	public function test_taxonomy_action_fails_for_unknown_taxonomy() : void {
		$result = ( new CLEFA_Taxonomy_Action() )->run(
			array(),
			$this->form_config,
			0,
			array( 'taxonomy' => 'does_not_exist' )
		);
		$this->assertFalse( $result['success'] );
	}

	// ------------------------------------------------------------------
	// Role assignment
	// ------------------------------------------------------------------

	public function test_role_action_replaces_user_role() : void {
		$user_id = wp_insert_user(
			array(
				'user_login' => 'roleuser',
				'user_email' => 'role@example.com',
				'user_pass'  => 'x',
				'role'       => 'subscriber',
			)
		);
		wp_set_current_user( $user_id );

		$result = ( new CLEFA_Role_Action() )->run(
			array(),
			$this->form_config,
			0,
			array(
				'target' => 'current_user',
				'role'   => 'editor',
				'mode'   => 'replace',
			)
		);

		$this->assertTrue( $result['success'] );
		$this->assertContains( 'editor', get_user_by( 'id', $user_id )->roles );
	}

	public function test_role_action_adds_role_in_add_mode() : void {
		$user_id = wp_insert_user(
			array(
				'user_login' => 'multirole',
				'user_email' => 'multi@example.com',
				'user_pass'  => 'x',
				'role'       => 'subscriber',
			)
		);
		wp_set_current_user( $user_id );

		$result = ( new CLEFA_Role_Action() )->run(
			array(),
			$this->form_config,
			0,
			array(
				'target'   => 'current_user',
				'role'     => 'customer',
				'mode'     => 'add',
				'meta_key' => 'onboarding',
				'meta_value' => 'complete',
			)
		);

		$this->assertTrue( $result['success'] );
		$user = get_user_by( 'id', $user_id );
		$this->assertContains( 'subscriber', $user->roles );
		$this->assertContains( 'customer', $user->roles );
		$this->assertSame( 'complete', get_user_meta( $user_id, 'onboarding', true ) );
	}

	// ------------------------------------------------------------------
	// Lost password
	// ------------------------------------------------------------------

	public function test_lost_password_action_sends_reset() : void {
		wp_insert_user(
			array(
				'user_login' => 'resetme',
				'user_email' => 'reset@example.com',
				'user_pass'  => 'x',
			)
		);

		$result = ( new CLEFA_Lost_Password_Action() )->run(
			array( 'email' => 'reset@example.com' ),
			$this->form_config,
			0,
			array( 'login_field' => 'email' )
		);

		$this->assertTrue( $result['success'] );
		global $clefa_test_password_resets;
		$this->assertContains( 'resetme', $clefa_test_password_resets );
	}

	public function test_lost_password_action_fails_when_user_not_found() : void {
		$result = ( new CLEFA_Lost_Password_Action() )->run(
			array( 'email' => 'missing@example.com' ),
			$this->form_config,
			0,
			array( 'login_field' => 'email' )
		);
		$this->assertFalse( $result['success'] );
	}

	// ------------------------------------------------------------------
	// WooCommerce product
	// ------------------------------------------------------------------

	public function test_create_product_action_creates_simple_product() : void {
		wp_set_current_user( 1 );

		$result = ( new CLEFA_WC_Product_Action() )->run(
			array(
				'name'  => 'Test Product',
				'price' => '19.99',
				'sku'   => 'SKU-001',
			),
			$this->form_config,
			0,
			array(
				'title_field' => 'name',
				'price_field' => 'price',
				'sku_field'   => 'sku',
				'status'      => 'publish',
			)
		);

		$this->assertTrue( $result['success'] );
		$product = wc_get_product( $result['product_id'] );
		$this->assertInstanceOf( WC_Product_Simple::class, $product );
	}

	public function test_create_product_action_fails_without_permission() : void {
		wp_set_current_user( 0 );
		$result = ( new CLEFA_WC_Product_Action() )->run(
			array( 'name' => 'Blocked Product' ),
			$this->form_config,
			0,
			array( 'title_field' => 'name' )
		);
		$this->assertFalse( $result['success'] );
	}

	// ------------------------------------------------------------------
	// Send email
	// ------------------------------------------------------------------

	public function test_send_email_action_sends_with_resolved_tokens() : void {
		$result = ( new CLEFA_Send_Email_Action() )->run(
			array( 'name' => 'Ada', 'email' => 'ada@example.com' ),
			$this->form_config,
			12,
			array(
				'to'      => '{field:email}',
				'subject' => 'Hello {field:name}',
				'body'    => 'Thanks {field:name} from {form:name}',
			)
		);
		$this->assertTrue( $result['success'] );
	}

	// ------------------------------------------------------------------
	// Webhook + save submission
	// ------------------------------------------------------------------

	public function test_webhook_action_posts_json_payload() : void {
		$result = ( new CLEFA_Webhook_Action() )->run(
			array( 'name' => 'Ada', 'email' => 'ada@example.com' ),
			$this->form_config,
			55,
			array(
				'webhook_url'    => 'https://hooks.example.com/form',
				'method'         => 'POST',
				'payload_format' => 'json',
				'include_meta'   => true,
			)
		);

		$this->assertTrue( $result['success'] );
		global $clefa_test_last_http_request;
		$this->assertSame( 'https://hooks.example.com/form', $clefa_test_last_http_request['url'] );
	}

	public function test_webhook_action_fails_on_invalid_url() : void {
		$result = ( new CLEFA_Webhook_Action() )->run(
			array(),
			$this->form_config,
			0,
			array( 'webhook_url' => 'not-a-url' )
		);
		$this->assertFalse( $result['success'] );
	}

	public function test_save_submission_action_returns_submission_id() : void {
		$result = ( new CLEFA_Save_Submission_Action() )->run(
			array( 'field' => 'value' ),
			$this->form_config,
			123,
			array()
		);
		$this->assertTrue( $result['success'] );
		$this->assertSame( 123, $result['submission_id'] );
	}

	// ------------------------------------------------------------------
	// Form action runner integration
	// ------------------------------------------------------------------

	public function test_runner_dispatches_create_post_and_register_actions() : void {
		wp_set_current_user( 1 );

		$results = CLEFA_Form_Action_Runner::run_actions(
			array(
				array(
					'action_type'      => 'create_post',
					'enabled'          => true,
					'post_title_field' => 'title',
					'post_status'      => 'draft',
				),
				array(
					'action_type'    => 'register',
					'enabled'        => true,
					'email_field'    => 'email',
					'password_field' => 'password',
				),
			),
			array(
				'title'    => 'Runner Post',
				'email'    => 'runner@example.com',
				'password' => 'RunnerPass1!',
			),
			$this->form_config,
			7
		);

		$this->assertTrue( $results['create_post']['success'] );
		$this->assertTrue( $results['register']['success'] );
	}
}
