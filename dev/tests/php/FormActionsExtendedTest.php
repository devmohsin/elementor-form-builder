<?php

/**

 * Extended PHPUnit coverage for product edit, user profile/password, and ACF field types.

 */



class FormActionsExtendedTest extends \PHPUnit\Framework\TestCase {



	private array $form_config = array(

		'form_name' => 'Extended Action Test Form',

		'id'        => 100,

	);



	protected function setUp(): void {

		clefa_test_reset_action_stores();

		wp_set_current_user( 0 );

	}



	// ------------------------------------------------------------------

	// WooCommerce product create + edit (price only)

	// ------------------------------------------------------------------



	public function test_create_product_sets_name_price_and_sku() : void {

		wp_set_current_user( 1 );



		$result = ( new CLEFA_WC_Product_Action() )->run(

			array(

				'name'  => 'Blue Widget',

				'price' => '29.95',

				'sku'   => 'WIDGET-01',

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

		$this->assertSame( 'Blue Widget', $product->get_name() );

		$this->assertSame( '29.95', (string) $product->get_regular_price() );

		$this->assertSame( 'WIDGET-01', $product->get_sku() );

	}



	public function test_edit_product_price_only_preserves_existing_title() : void {

		wp_set_current_user( 1 );



		$create = ( new CLEFA_WC_Product_Action() )->run(

			array( 'name' => 'Original Title', 'price' => '10.00' ),

			$this->form_config,

			0,

			array(

				'title_field' => 'name',

				'price_field' => 'price',

				'status'      => 'publish',

			)

		);



		$product_id = $create['product_id'];

		$edit       = ( new CLEFA_WC_Product_Action() )->run(

			array(

				'product_id' => (string) $product_id,

				'price'      => '49.99',

			),

			$this->form_config,

			0,

			array(

				'product_id_field' => 'product_id',

				'price_field'      => 'price',

				'status'           => 'publish',

			)

		);



		$this->assertTrue( $edit['success'] );

		$product = wc_get_product( $product_id );

		$this->assertSame( 'Original Title', $product->get_name() );

		$this->assertSame( '49.99', (string) $product->get_regular_price() );

	}



	public function test_update_wc_product_alias_edits_existing_product() : void {

		wp_set_current_user( 1 );



		$create = ( new CLEFA_WC_Product_Action() )->run(

			array( 'name' => 'Alias Product', 'price' => '5.00' ),

			$this->form_config,

			0,

			array(

				'title_field' => 'name',

				'price_field' => 'price',

			)

		);



		$results = CLEFA_Form_Action_Runner::run_actions(

			array(

				array(

					'action_type'      => 'update_wc_product',

					'enabled'          => true,

					'product_id_field' => 'product_id',

					'price_field'      => 'price',

				),

			),

			array(

				'product_id' => (string) $create['product_id'],

				'price'      => '12.50',

			),

			$this->form_config,

			0

		);



		$this->assertTrue( $results['update_wc_product']['success'] );

		$product = wc_get_product( $create['product_id'] );

		$this->assertSame( 'Alias Product', $product->get_name() );

		$this->assertEquals( 12.5, (float) $product->get_regular_price() );

	}



	// ------------------------------------------------------------------

	// Edit user profile fields

	// ------------------------------------------------------------------



	public function test_update_user_meta_edits_email_first_and_last_name() : void {

		$user_id = wp_insert_user(

			array(

				'user_login' => 'profileuser',

				'user_email' => 'old@example.com',

				'user_pass'  => 'OldPass1!',

				'first_name' => 'Old',

				'last_name'  => 'Name',

			)

		);

		wp_set_current_user( $user_id );



		$result = ( new CLEFA_Update_User_Meta_Action() )->run(

			array(

				'email'      => 'new@example.com',

				'first'      => 'Jane',

				'last'       => 'Doe',

				'department' => 'Sales',

			),

			$this->form_config,

			0,

			array(

				'core_field_mappings' => array(

					array( 'core_field' => 'user_email', 'field_id' => 'email' ),

					array( 'core_field' => 'first_name', 'field_id' => 'first' ),

					array( 'core_field' => 'last_name',  'field_id' => 'last' ),

				),

				'meta_mappings' => array(

					array( 'meta_key' => 'department', 'field_id' => 'department' ),

				),

			)

		);



		$this->assertTrue( $result['success'] );

		$user = get_user_by( 'id', $user_id );

		$this->assertSame( 'new@example.com', $user->user_email );

		$this->assertSame( 'Jane', $user->first_name );

		$this->assertSame( 'Doe', $user->last_name );

		$this->assertSame( 'Sales', get_user_meta( $user_id, 'department', true ) );

	}



	public function test_update_user_meta_targets_user_by_id_field() : void {

		$user_id = wp_insert_user(

			array(

				'user_login' => 'targetuser',

				'user_email' => 'target@example.com',

				'user_pass'  => 'x',

			)

		);

		wp_set_current_user( 1 );



		$result = ( new CLEFA_Update_User_Meta_Action() )->run(

			array(

				'uid'     => (string) $user_id,

				'company' => 'Target Corp',

			),

			$this->form_config,

			0,

			array(

				'user_id_field' => 'uid',

				'meta_mappings' => array(

					array( 'meta_key' => 'company', 'field_id' => 'company' ),

				),

			)

		);



		$this->assertTrue( $result['success'] );

		$this->assertSame( 'Target Corp', get_user_meta( $user_id, 'company', true ) );

	}



	// ------------------------------------------------------------------

	// Password change (brainstorm-style registration + profile change)

	// ------------------------------------------------------------------



	public function test_change_password_updates_logged_in_user_password() : void {

		$user_id = wp_insert_user(

			array(

				'user_login' => 'pwchange',

				'user_email' => 'pw@example.com',

				'user_pass'  => 'OldSecret1!',

			)

		);

		wp_set_current_user( $user_id );



		$result = ( new CLEFA_Change_Password_Action() )->run(

			array( 'password' => 'NewSecret9!' ),

			$this->form_config,

			0,

			array( 'password_field' => 'password' )

		);



		$this->assertTrue( $result['success'] );

		$this->assertSame( 'NewSecret9!', get_user_by( 'id', $user_id )->user_pass );

	}



	public function test_confirm_password_then_change_password_action_chain() : void {

		$user_id = wp_insert_user(

			array(

				'user_login' => 'chainuser',

				'user_email' => 'chain@example.com',

				'user_pass'  => 'OldChain1!',

			)

		);

		wp_set_current_user( $user_id );



		$results = CLEFA_Form_Action_Runner::run_actions(

			array(

				array(

					'action_type'    => 'confirm_password',

					'enabled'        => true,

					'password_field' => 'password',

					'confirm_field'  => 'confirm_password',

					'min_length'     => 8,

					'require_upper'  => true,

					'require_lower'  => true,

					'require_number' => true,

					'fail_hard'      => true,

				),

				array(

					'action_type'    => 'change_password',

					'enabled'        => true,

					'password_field' => 'password',

				),

			),

			array(

				'password'         => 'Brainstorm9!',

				'confirm_password' => 'Brainstorm9!',

			),

			$this->form_config,

			0

		);



		$this->assertTrue( $results['confirm_password']['success'] );

		$this->assertTrue( $results['change_password']['success'] );

		$this->assertSame( 'Brainstorm9!', get_user_by( 'id', $user_id )->user_pass );

	}



	public function test_confirm_password_chain_fails_on_mismatch_and_skips_password_change() : void {

		$user_id = wp_insert_user(

			array(

				'user_login' => 'mismatchuser',

				'user_email' => 'mis@example.com',

				'user_pass'  => 'KeepThis1!',

			)

		);

		wp_set_current_user( $user_id );



		$results = CLEFA_Form_Action_Runner::run_actions(

			array(

				array(

					'action_type'    => 'confirm_password',

					'enabled'        => true,

					'password_field' => 'password',

					'confirm_field'  => 'confirm_password',

					'fail_hard'      => true,

				),

				array(

					'action_type'    => 'change_password',

					'enabled'        => true,

					'password_field' => 'password',

				),

			),

			array(

				'password'         => 'NewPass9!',

				'confirm_password' => 'Different9!',

			),

			$this->form_config,

			0

		);



		$this->assertFalse( $results['confirm_password']['success'] );

		$this->assertArrayNotHasKey( 'change_password', $results );

		$this->assertSame( 'KeepThis1!', get_user_by( 'id', $user_id )->user_pass );

	}



	public function test_register_user_alias_creates_account_with_password() : void {

		$results = CLEFA_Form_Action_Runner::run_actions(

			array(

				array(

					'action_type'    => 'register_user',

					'enabled'        => true,

					'username_field' => 'username',

					'email_field'    => 'email',

					'password_field' => 'password',

					'role'           => 'subscriber',

				),

			),

			array(

				'username' => 'brainstorm',

				'email'    => 'brain@example.com',

				'password' => 'BrainPass1!',

			),

			$this->form_config,

			0

		);



		$this->assertTrue( $results['register_user']['success'] );

		$user = get_user_by( 'login', 'brainstorm' );

		$this->assertSame( 'brain@example.com', $user->user_email );

	}



	// ------------------------------------------------------------------

	// ACF date, select, option, repeater

	// ------------------------------------------------------------------



	public function test_acf_action_stores_date_field_value() : void {

		$post_id = wp_insert_post( array( 'post_title' => 'Date Post', 'post_status' => 'publish' ) );



		$result = ( new CLEFA_ACF_Action() )->run(

			array(

				'post_id'    => (string) $post_id,

				'event_date' => '2026-12-25',

			),

			$this->form_config,

			0,

			array(

				'target_type'     => 'post',

				'target_id_field' => 'post_id',

				'acf_field_key'   => 'event_date',

				'value_field'     => 'event_date',

			)

		);



		$this->assertTrue( $result['success'] );

		global $clefa_test_acf_fields;

		$this->assertSame( '2026-12-25', $clefa_test_acf_fields[ (string) $post_id ]['event_date'] );

	}



	public function test_acf_action_stores_select_option_value() : void {

		$post_id = wp_insert_post( array( 'post_title' => 'Select Post', 'post_status' => 'publish' ) );



		$result = ( new CLEFA_ACF_Action() )->run(

			array(

				'post_id' => (string) $post_id,

				'tier'    => 'gold',

			),

			$this->form_config,

			0,

			array(

				'target_type'     => 'post',

				'target_id_field' => 'post_id',

				'acf_field_key'   => 'membership_tier',

				'value_field'     => 'tier',

			)

		);



		$this->assertTrue( $result['success'] );

		global $clefa_test_acf_fields;

		$this->assertSame( 'gold', $clefa_test_acf_fields[ (string) $post_id ]['membership_tier'] );

	}



	public function test_acf_action_stores_option_page_select_value() : void {

		$result = ( new CLEFA_ACF_Action() )->run(

			array( 'site_mode' => 'maintenance' ),

			$this->form_config,

			0,

			array(

				'target_type'   => 'option',

				'acf_field_key' => 'site_mode',

				'value_field'   => 'site_mode',

			)

		);



		$this->assertTrue( $result['success'] );

		global $clefa_test_acf_fields;

		$this->assertSame( 'maintenance', $clefa_test_acf_fields['option']['site_mode'] );

	}



	public function test_acf_repeater_action_writes_mapped_rows_to_post() : void {

		$post_id = wp_insert_post( array( 'post_title' => 'Repeater Post', 'post_status' => 'publish' ) );



		$result = ( new CLEFA_ACF_Repeater_Action() )->run(

			array(

				'post_id'   => (string) $post_id,

				'attendees' => array(

					array(

						'attendee_name'  => 'Ada Lovelace',

						'attendee_email' => 'ada@example.com',

						'attendee_role'  => 'speaker',

					),

					array(

						'attendee_name'  => 'Grace Hopper',

						'attendee_email' => 'grace@example.com',

						'attendee_role'  => 'vip',

					),

				),

			),

			$this->form_config,

			0,

			array(

				'target_type'     => 'post',

				'target_id_field' => 'post_id',

				'acf_field_key'   => 'event_attendees',

				'repeater_field'  => 'attendees',

				'row_mappings'    => array(

					array( 'acf_sub_key' => 'name',  'field_id' => 'attendee_name' ),

					array( 'acf_sub_key' => 'email', 'field_id' => 'attendee_email' ),

					array( 'acf_sub_key' => 'role',  'field_id' => 'attendee_role' ),

				),

			)

		);



		$this->assertTrue( $result['success'] );

		$this->assertSame( 2, $result['rows'] );

		global $clefa_test_acf_fields;

		$rows = $clefa_test_acf_fields[ (string) $post_id ]['event_attendees'];

		$this->assertSame( 'Ada Lovelace', $rows[0]['name'] );

		$this->assertSame( 'grace@example.com', $rows[1]['email'] );

	}



	public function test_acf_repeater_action_passthrough_rows_without_mappings() : void {

		$user_id = wp_insert_user(

			array(

				'user_login' => 'repeateruser',

				'user_email' => 'rep@example.com',

				'user_pass'  => 'x',

			)

		);

		wp_set_current_user( $user_id );



		$result = ( new CLEFA_ACF_Repeater_Action() )->run(

			array(

				'lines' => array(

					array( 'label' => 'Line A', 'qty' => '2' ),

					array( 'label' => 'Line B', 'qty' => '5' ),

				),

			),

			$this->form_config,

			0,

			array(

				'target_type'    => 'user',

				'acf_field_key'  => 'order_lines',

				'repeater_field' => 'lines',

			)

		);



		$this->assertTrue( $result['success'] );

		global $clefa_test_acf_fields;

		$this->assertSame(

			array(

				array( 'label' => 'Line A', 'qty' => '2' ),

				array( 'label' => 'Line B', 'qty' => '5' ),

			),

			$clefa_test_acf_fields[ 'user_' . $user_id ]['order_lines']

		);

	}



	public function test_runner_resolves_update_acf_repeater_action_type() : void {

		$post_id = wp_insert_post( array( 'post_title' => 'Runner Repeater', 'post_status' => 'publish' ) );



		$results = CLEFA_Form_Action_Runner::run_actions(

			array(

				array(

					'action_type'     => 'update_acf_repeater',

					'enabled'         => true,

					'target_type'     => 'post',

					'target_id_field' => 'post_id',

					'acf_field_key'   => 'items',

					'repeater_field'  => 'items',

				),

			),

			array(

				'post_id' => (string) $post_id,

				'items'   => array(

					array( 'title' => 'One' ),

					array( 'title' => 'Two' ),

				),

			),

			$this->form_config,

			0

		);



		$this->assertTrue( $results['update_acf_repeater']['success'] );

		global $clefa_test_acf_fields;

		$this->assertCount( 2, $clefa_test_acf_fields[ (string) $post_id ]['items'] );

	}

}

