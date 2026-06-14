<?php
/**
 * Programmatic catalog of 100 edge-case forms and their pass/fail submission scenarios.
 *
 * Each entry:
 *   id, label, config, cases[]
 * Each case:
 *   name, data, expect_pass, error_fields?, skip_actions?, verify?, user_id?
 */

class CLEFA_Form_Scenario_Catalog {

	public static function all(): array {
		$forms = array_merge(
			self::validationForms(),
			self::actionForms(),
			self::conditionForms(),
			self::integrationForms()
		);

		if ( 100 !== count( $forms ) ) {
			throw new RuntimeException(
				'Form scenario catalog must contain exactly 100 forms; got ' . count( $forms )
			);
		}

		return $forms;
	}

	public static function totalCases(): int {
		$count = 0;
		foreach ( self::all() as $form ) {
			$count += count( $form['cases'] ?? array() );
		}
		return $count;
	}

	// ------------------------------------------------------------------
	// 50 validation-focused forms
	// ------------------------------------------------------------------

	private static function validationForms(): array {
		$forms = array();
		$B     = CLEFA_Programmatic_Form_Builder::class;

		$text_rules = array(
			array( 'val_001_min_length', 'min_length', '5', 'hello', 'hi' ),
			array( 'val_002_max_length', 'max_length', '5', 'hello', 'toolong' ),
			array( 'val_003_exact_length', 'exact_length', '4', 'four', 'fo' ),
			array( 'val_004_alpha_only', 'alpha_only', '', 'Jane Smith', 'Jane123' ),
			array( 'val_005_alphanumeric', 'alphanumeric', '', 'user_99', 'user 99' ),
			array( 'val_006_no_spaces', 'no_spaces', '', 'john_doe', 'john doe' ),
			array( 'val_007_regex', 'regex', '^[A-Z]{3}-[0-9]{4}$', 'ABC-1234', 'abc-1234' ),
			array( 'val_008_min_words', 'min_words', '5', 'one two three four five', 'too few words' ),
			array( 'val_009_max_words', 'max_words', '3', 'one two three', 'one two three four' ),
			array( 'val_010_no_html', 'no_html', '', 'plain text', '<b>html</b>' ),
			array( 'val_011_no_urls', 'no_urls', '', 'plain bio text', 'see https://example.com' ),
			array( 'val_012_phone', 'phone', '', '+61400111222', 'not-a-phone' ),
			array( 'val_013_equals', 'equals', 'yes', 'yes', 'no' ),
			array( 'val_014_not_equals', 'not_equals', 'bad', 'good', 'bad' ),
			array( 'val_015_blocked_values', 'blocked_values', 'spam,junk', 'hello', 'spam' ),
		);

		foreach ( $text_rules as $def ) {
			$forms[] = self::single_text_rule_form( $def[0], $def[1], $def[2], $def[3], $def[4] );
		}

		$forms[] = self::wrap(
			'val_016_required_text',
			'Required text cannot be empty',
			array( $B::field( 'title', 'text', array( 'required' => true ) ) ),
			array(
				self::pass( 'valid', array( 'title' => 'Hello World' ) ),
				self::fail( 'empty', array( 'title' => '' ), array( 'title' ) ),
			)
		);

		$forms[] = self::wrap(
			'val_017_email_valid',
			'Email field format',
			array( $B::field( 'email', 'email', array( 'required' => true ) ) ),
			array(
				self::pass( 'valid', array( 'email' => 'user@example.com' ) ),
				self::fail( 'invalid', array( 'email' => 'not-email' ), array( 'email' ) ),
			)
		);

		$forms[] = self::wrap(
			'val_018_url_rule',
			'URL validation rule',
			array( $B::field( 'website', 'text', array(
				'validation_rules' => array( $B::rule( 'url' ) ),
			) ) ),
			array(
				self::pass( 'valid', array( 'website' => 'https://example.com' ) ),
				self::fail( 'invalid', array( 'website' => 'not-a-url' ), array( 'website' ) ),
			)
		);

		$forms[] = self::wrap(
			'val_019_number_range',
			'Number min/max value',
			array( $B::field( 'age', 'number', array(
				'validation_rules' => array(
					$B::rule( 'min_value', '18' ),
					$B::rule( 'max_value', '99' ),
				),
			) ) ),
			array(
				self::pass( 'valid', array( 'age' => '25' ) ),
				self::fail( 'too_low', array( 'age' => '10' ), array( 'age' ) ),
				self::fail( 'too_high', array( 'age' => '150' ), array( 'age' ) ),
			)
		);

		$forms[] = self::wrap(
			'val_020_integer_only',
			'Integer validation',
			array( $B::field( 'qty', 'number', array(
				'validation_rules' => array( $B::rule( 'integer' ) ),
			) ) ),
			array(
				self::pass( 'valid', array( 'qty' => '42' ) ),
				self::fail( 'decimal', array( 'qty' => '3.14' ), array( 'qty' ) ),
			)
		);

		$forms[] = self::wrap(
			'val_021_password_strength',
			'Password strength medium',
			array( $B::field( 'password', 'password', array(
				'required'         => true,
				'validation_rules' => array( $B::rule( 'password_strength', 'medium' ) ),
			) ) ),
			array(
				self::pass( 'valid', array( 'password' => 'BrainPass1' ) ),
				self::fail( 'weak', array( 'password' => 'password' ), array( 'password' ) ),
			)
		);

		$forms[] = self::wrap(
			'val_022_confirm_password',
			'Confirm password match',
			array(
				$B::field( 'password', 'password', array( 'required' => true ) ),
				$B::field( 'confirm_password', 'password', array(
					'required'         => true,
					'validation_rules' => array( $B::rule( 'confirm_password', 'password' ) ),
				) ),
			),
			array(
				self::pass( 'match', array( 'password' => 'BrainPass1', 'confirm_password' => 'BrainPass1' ) ),
				self::fail( 'mismatch', array( 'password' => 'BrainPass1', 'confirm_password' => 'OtherPass1' ), array( 'confirm_password' ) ),
			)
		);

		$forms[] = self::wrap(
			'val_023_checkbox_min_checked',
			'Checkbox minimum selections',
			array( $B::field( 'tags', 'checkbox', array(
				'options'          => $B::checkbox_options( array( 'a' => 'A', 'b' => 'B', 'c' => 'C' ) ),
				'validation_rules'   => array( $B::rule( 'min_checked', '2' ) ),
			) ) ),
			array(
				self::pass( 'valid', array( 'tags' => array( 'a', 'b' ) ) ),
				self::fail( 'too_few', array( 'tags' => array( 'a' ) ), array( 'tags' ) ),
			)
		);

		$forms[] = self::wrap(
			'val_024_checkbox_max_checked',
			'Checkbox maximum selections',
			array( $B::field( 'tags', 'checkbox', array(
				'options'          => $B::checkbox_options( array( 'a' => 'A', 'b' => 'B', 'c' => 'C' ) ),
				'validation_rules'   => array( $B::rule( 'max_checked', '2' ) ),
			) ) ),
			array(
				self::pass( 'valid', array( 'tags' => array( 'a', 'b' ) ) ),
				self::fail( 'too_many', array( 'tags' => array( 'a', 'b', 'c' ) ), array( 'tags' ) ),
			)
		);

		$forms[] = self::wrap(
			'val_025_date_after_today',
			'Date must be after today',
			array( $B::field( 'event_date', 'date', array(
				'required'         => true,
				'validation_rules' => array( $B::rule( 'date_after', 'today' ) ),
			) ) ),
			array(
				self::pass( 'future', array( 'event_date' => date( 'Y-m-d', strtotime( '+3 days' ) ) ) ),
				self::fail( 'past', array( 'event_date' => date( 'Y-m-d', strtotime( '-1 day' ) ) ), array( 'event_date' ) ),
			)
		);

		$forms[] = self::wrap(
			'val_026_optional_skips_rules',
			'Optional empty field skips rules',
			array( $B::field( 'nickname', 'text', array(
				'validation_rules' => array( $B::rule( 'min_length', '5' ) ),
			) ) ),
			array(
				self::pass( 'empty_ok', array( 'nickname' => '' ) ),
				self::fail( 'too_short_when_present', array( 'nickname' => 'ab' ), array( 'nickname' ) ),
			)
		);

		$forms[] = self::wrap(
			'val_027_textarea_max_length',
			'Textarea max length',
			array( $B::field( 'bio', 'textarea', array(
				'validation_rules' => array( $B::rule( 'max_length', '20' ) ),
			) ) ),
			array(
				self::pass( 'valid', array( 'bio' => 'Short bio text.' ) ),
				self::fail( 'too_long', array( 'bio' => str_repeat( 'x', 30 ) ), array( 'bio' ) ),
			)
		);

		$forms[] = self::wrap(
			'val_028_select_required',
			'Required select',
			array( $B::field( 'country', 'select', array(
				'required' => true,
				'options'  => $B::select_options( array( '' => 'Choose', 'au' => 'Australia', 'us' => 'USA' ) ),
			) ) ),
			array(
				self::pass( 'valid', array( 'country' => 'au' ) ),
				self::fail( 'empty', array( 'country' => '' ), array( 'country' ) ),
			)
		);

		$forms[] = self::wrap(
			'val_029_radio_required',
			'Required radio',
			array( $B::field( 'plan', 'radio', array(
				'required' => true,
				'options'  => $B::select_options( array( 'free' => 'Free', 'pro' => 'Pro' ) ),
			) ) ),
			array(
				self::pass( 'valid', array( 'plan' => 'pro' ) ),
				self::fail( 'empty', array( 'plan' => '' ), array( 'plan' ) ),
			)
		);

		$forms[] = self::wrap(
			'val_030_multi_rule_stack',
			'Multiple rules on one field',
			array( $B::field( 'code', 'text', array(
				'required'         => true,
				'validation_rules' => array(
					$B::rule( 'min_length', '4' ),
					$B::rule( 'alphanumeric' ),
					$B::rule( 'no_spaces' ),
				),
			) ) ),
			array(
				self::pass( 'valid', array( 'code' => 'SAVE2026' ) ),
				self::fail( 'spaces', array( 'code' => 'SAVE 2026' ), array( 'code' ) ),
				self::fail( 'short', array( 'code' => 'AB' ), array( 'code' ) ),
			)
		);

		// Generate val_031 .. val_050 as numbered permutations of common edge values.
		$edge_values = array(
			array( 'val_031_whitespace_required', 'Whitespace-only required fails', 'text', true, '   ', null, '', true ),
			array( 'val_032_zero_string_required', 'Zero string passes required text', 'text', true, '0' ),
			array( 'val_033_unicode_alpha', 'Unicode letters pass alpha_only', 'text', false, 'José García', 'alpha_only' ),
			array( 'val_034_email_plus_address', 'Email with plus tag', 'email', true, 'user+tag@example.com' ),
			array( 'val_035_url_no_scheme', 'URL without scheme fails', 'text', false, 'example.com', 'url', '', true ),
			array( 'val_036_number_zero', 'Zero passes numeric min 0', 'number', false, '0', 'min_value', '0' ),
			array( 'val_037_exact_length_boundary', 'Exact length boundary pass', 'text', false, '12345', 'exact_length', '5' ),
			array( 'val_038_exact_length_boundary_fail', 'Exact length boundary fail', 'text', false, '1234', 'exact_length', '5', true ),
			array( 'val_039_blocked_case_sensitive', 'Blocked value exact match', 'text', false, 'spam', 'blocked_values', 'spam', true ),
			array( 'val_040_equals_yes_token', 'Equals rule yes token', 'text', false, 'yes', 'equals', 'yes' ),
			array( 'val_041_not_equals_reserved', 'Not equals reserved word', 'text', false, 'available', 'not_equals', 'taken' ),
			array( 'val_042_phone_short', 'Phone too short', 'text', false, '123', 'phone', '', true ),
			array( 'val_043_phone_valid_intl', 'International phone valid', 'text', false, '+44 7700 900123', 'phone' ),
			array( 'val_044_min_words_boundary', 'Min words exact count', 'textarea', false, 'one two three four five', 'min_words', '5' ),
			array( 'val_045_max_words_boundary', 'Max words exceeded', 'textarea', false, 'one two three four five six', 'max_words', '5', true ),
			array( 'val_046_html_entity_text', 'HTML entity in plain text passes no_html', 'textarea', false, 'Tom &amp; Jerry', 'no_html' ),
			array( 'val_047_www_without_scheme', 'www prefix fails no_urls', 'textarea', false, 'visit www.example.com', 'no_urls', '', true ),
			array( 'val_048_password_strong', 'Strong password passes', 'password', false, 'Str0ng!PassWord', 'password_strength', 'strong' ),
			array( 'val_049_password_strong_fail', 'Strong password fails without special', 'password', false, 'StrongPass1', 'password_strength', 'strong' ),
			array( 'val_050_confirm_both_empty', 'Confirm password empty when password empty', 'password', false, '', 'confirm_password', 'password' ),
		);

		foreach ( $edge_values as $edge ) {
			$forms[] = self::edge_value_form( $edge );
		}

		return $forms;
	}

	// ------------------------------------------------------------------
	// 25 action forms — verify real side effects
	// ------------------------------------------------------------------

	private static function actionForms(): array {
		$B = CLEFA_Programmatic_Form_Builder::class;

		return array(
			self::wrap(
				'act_001_create_post_basic',
				'Create post from title field',
				array(
					$B::field( 'title', 'text', array( 'required' => true ) ),
					$B::field( 'body', 'textarea' ),
				),
				array(
					self::pass(
						'creates_post',
						array( 'title' => 'Suite Post Alpha', 'body' => 'Body content.' ),
						array(
							array( 'type' => 'action_success', 'action' => 'create_post' ),
							array( 'type' => 'post_created', 'title' => 'Suite Post Alpha' ),
						)
					),
					self::fail(
						'missing_title_no_post',
						array( 'title' => '', 'body' => 'Body.' ),
						array( 'title' ),
						array(
							array( 'type' => 'post_not_created', 'title' => 'Suite Post Alpha' ),
							array( 'type' => 'post_count_delta', 'delta' => 0 ),
						)
					),
				),
				array( $B::action( 'create_post', array(
					'post_title_field'   => 'title',
					'post_content_field' => 'body',
					'post_status'        => 'publish',
				) ) )
			),
			self::wrap(
				'act_002_create_post_with_meta',
				'Create post and write meta',
				array(
					$B::field( 'title', 'text', array( 'required' => true ) ),
					$B::field( 'color', 'text', array( 'required' => true ) ),
				),
				array(
					self::pass(
						'creates_post_and_meta',
						array( 'title' => 'Meta Post One', 'color' => 'blue' ),
						array(
							array( 'type' => 'post_created', 'title' => 'Meta Post One' ),
							array( 'type' => 'post_meta', 'title' => 'Meta Post One', 'key' => 'color', 'value' => 'blue' ),
						)
					),
					self::fail( 'missing_color', array( 'title' => 'Meta Post One', 'color' => '' ), array( 'color' ) ),
				),
				array( $B::action( 'create_post', array(
					'post_title_field' => 'title',
					'post_status'      => 'publish',
					'meta_mappings'    => array(
						array( 'meta_key' => 'color', 'field_id' => 'color' ),
					),
				) ) )
			),
			self::wrap(
				'act_003_create_post_draft',
				'Create draft post',
				array( $B::field( 'title', 'text', array( 'required' => true ) ) ),
				array(
					self::pass(
						'draft_created',
						array( 'title' => 'Draft Post Item' ),
						array(
							array( 'type' => 'post_created', 'title' => 'Draft Post Item' ),
							array( 'type' => 'action_success', 'action' => 'create_post' ),
						)
					),
				),
				array( $B::action( 'create_post', array(
					'post_title_field' => 'title',
					'post_status'      => 'draft',
				) ) )
			),
			self::wrap(
				'act_004_register_user',
				'Register new user',
				array(
					$B::field( 'username', 'text', array( 'required' => true ) ),
					$B::field( 'email', 'email', array( 'required' => true ) ),
					$B::field( 'password', 'password', array( 'required' => true ) ),
				),
				array(
					self::pass(
						'user_created',
						array(
							'username' => 'suiteuser01',
							'email'    => 'suite01@example.com',
							'password' => 'RegisterPass1!',
						),
						array(
							array( 'type' => 'action_success', 'action' => 'register' ),
							array( 'type' => 'user_exists', 'login' => 'suiteuser01', 'email' => 'suite01@example.com' ),
						)
					),
					self::fail(
						'missing_email',
						array( 'username' => 'suiteuser01', 'email' => '', 'password' => 'RegisterPass1!' ),
						array( 'email' ),
						array( array( 'type' => 'user_not_exists', 'email' => 'suite01@example.com' ) )
					),
				),
				array( $B::action( 'register', array(
					'username_field' => 'username',
					'email_field'    => 'email',
					'password_field' => 'password',
				) ) )
			),
			self::wrap(
				'act_005_register_with_meta',
				'Register user with phone meta',
				array(
					$B::field( 'email', 'email', array( 'required' => true ) ),
					$B::field( 'password', 'password', array( 'required' => true ) ),
					$B::field( 'phone', 'text', array( 'required' => true ) ),
				),
				array(
					self::pass(
						'meta_saved',
						array(
							'email'    => 'suite02@example.com',
							'password' => 'RegisterPass1!',
							'phone'    => '555-0100',
						),
						array(
							array( 'type' => 'user_exists', 'email' => 'suite02@example.com' ),
							array( 'type' => 'user_meta', 'email' => 'suite02@example.com', 'key' => 'phone', 'value' => '555-0100' ),
						)
					),
				),
				array( $B::action( 'register', array(
					'email_field'    => 'email',
					'password_field' => 'password',
					'meta_fields'    => array(
						array( 'meta_key' => 'phone', 'field_id' => 'phone' ),
					),
				) ) )
			),
			self::wrap(
				'act_006_login_user',
				'Login existing user',
				array(
					$B::field( 'username', 'text', array( 'required' => true ) ),
					$B::field( 'password', 'password', array( 'required' => true ) ),
				),
				array(
					self::pass(
						'login_ok',
						array( 'username' => 'login_suite', 'password' => 'LoginPass1!' ),
						array( array( 'type' => 'action_success', 'action' => 'login' ) ),
						'seed_login_user',
						0
					),
					self::actionFail(
						'wrong_password',
						array( 'username' => 'login_suite', 'password' => 'wrong' ),
						'login',
						'seed_login_user',
						0
					),
				),
				array( $B::action( 'login', array(
					'username_field' => 'username',
					'password_field' => 'password',
				) ) )
			),
			self::wrap(
				'act_007_update_user_meta',
				'Update logged-in user company meta',
				array( $B::field( 'company', 'text', array( 'required' => true ) ) ),
				array(
					self::pass(
						'meta_updated',
						array( 'company' => 'Acme Corp' ),
						array(
							array( 'type' => 'action_success', 'action' => 'update_user_meta' ),
							array( 'type' => 'user_meta', 'login' => 'meta_suite', 'key' => 'company', 'value' => 'Acme Corp' ),
						),
						'seed_meta_user'
					),
				),
				array( $B::action( 'update_user_meta', array(
					'meta_mappings' => array(
						array( 'meta_key' => 'company', 'field_id' => 'company' ),
					),
				) ) )
			),
			self::wrap(
				'act_008_update_user_core_email',
				'Update user email core field',
				array( $B::field( 'email', 'email', array( 'required' => true ) ) ),
				array(
					self::pass(
						'email_updated',
						array( 'email' => 'newemail@example.com' ),
						array( array( 'type' => 'action_success', 'action' => 'update_user_meta' ) ),
						'seed_meta_user'
					),
				),
				array( $B::action( 'update_user_meta', array(
					'core_field_mappings' => array(
						array( 'core_field' => 'user_email', 'field_id' => 'email' ),
					),
				) ) )
			),
			self::wrap(
				'act_009_change_password',
				'Change password after confirm',
				array(
					$B::field( 'password', 'password', array( 'required' => true ) ),
					$B::field( 'confirm_password', 'password', array(
						'required'         => true,
						'validation_rules' => array( $B::rule( 'confirm_password', 'password' ) ),
					) ),
				),
				array(
					self::pass(
						'password_changed',
						array( 'password' => 'NewBrain9!', 'confirm_password' => 'NewBrain9!' ),
						array(
							array( 'type' => 'action_success', 'action' => 'confirm_password' ),
							array( 'type' => 'action_success', 'action' => 'change_password' ),
							array( 'type' => 'user_password', 'login' => 'meta_suite', 'password' => 'NewBrain9!' ),
						),
						'seed_meta_user'
					),
					self::fail(
						'mismatch_blocks_change',
						array( 'password' => 'NewBrain9!', 'confirm_password' => 'OtherPass9!' ),
						array( 'confirm_password' ),
						array(),
						'seed_meta_user'
					),
				),
				array(
					$B::action( 'confirm_password', array(
						'password_field' => 'password',
						'confirm_field'  => 'confirm_password',
						'min_length'     => 8,
						'fail_hard'      => true,
					) ),
					$B::action( 'change_password', array( 'password_field' => 'password' ) ),
				)
			),
			self::wrap(
				'act_010_create_wc_product',
				'Create WooCommerce product',
				array(
					$B::field( 'name', 'text', array( 'required' => true ) ),
					$B::field( 'price', 'number', array( 'required' => true ) ),
				),
				array(
					self::pass(
						'product_created',
						array( 'name' => 'Suite Widget', 'price' => '19.99' ),
						array(
							array( 'type' => 'action_success', 'action' => 'create_product' ),
							array( 'type' => 'product_exists', 'name' => 'Suite Widget' ),
							array( 'type' => 'product_price', 'name' => 'Suite Widget', 'price' => 19.99 ),
						)
					),
				),
				array( $B::action( 'create_product', array(
					'title_field' => 'name',
					'price_field' => 'price',
					'status'      => 'publish',
				) ) )
			),
			self::wrap(
				'act_011_update_wc_product_price',
				'Update product price only',
				array(
					$B::field( 'product_id', 'number', array( 'required' => true ) ),
					$B::field( 'price', 'number', array( 'required' => true ) ),
				),
				array(
					self::pass(
						'price_updated',
						array( 'product_id' => '1', 'price' => '29.99' ),
						array(
							array( 'type' => 'action_success', 'action' => 'create_product' ),
							array( 'type' => 'product_price', 'name' => 'Seed Product', 'price' => 29.99 ),
						),
						'seed_product'
					),
				),
				array( $B::action( 'create_product', array(
					'product_id_field' => 'product_id',
					'price_field'      => 'price',
				) ) )
			),
			self::wrap(
				'act_012_acf_post_field',
				'Update ACF post field',
				array(
					$B::field( 'post_id', 'number', array( 'required' => true ) ),
					$B::field( 'note', 'text', array( 'required' => true ) ),
				),
				array(
					self::pass(
						'acf_saved',
						array( 'post_id' => '1', 'note' => 'ACF note value' ),
						array(
							array( 'type' => 'action_success', 'action' => 'update_acf' ),
							array( 'type' => 'acf_value', 'target' => '1', 'field' => 'suite_note', 'value' => 'ACF note value' ),
						),
						'seed_post'
					),
				),
				array( $B::action( 'update_acf', array(
					'target_type'     => 'post',
					'target_id_field' => 'post_id',
					'acf_field_key'   => 'suite_note',
					'value_field'     => 'note',
				) ) )
			),
			self::wrap(
				'act_013_acf_option_field',
				'Update ACF option field',
				array( $B::field( 'mode', 'text', array( 'required' => true ) ) ),
				array(
					self::pass(
						'option_saved',
						array( 'mode' => 'live' ),
						array(
							array( 'type' => 'acf_value', 'target' => 'option', 'field' => 'suite_mode', 'value' => 'live' ),
						)
					),
				),
				array( $B::action( 'update_acf', array(
					'target_type'   => 'option',
					'acf_field_key' => 'suite_mode',
					'value_field'   => 'mode',
				) ) )
			),
			self::wrap(
				'act_014_acf_repeater',
				'Update ACF repeater rows',
				array(
					$B::field( 'post_id', 'number', array( 'required' => true ) ),
					$B::field( 'attendees', 'repeater', array(
						'sub_fields' => array(
							$B::field( 'name', 'text', array( 'required' => true ) ),
							$B::field( 'email', 'email', array( 'required' => true ) ),
						),
					) ),
				),
				array(
					self::pass(
						'repeater_saved',
						array(
							'post_id'   => '1',
							'attendees' => array(
								array( 'name' => 'Ada', 'email' => 'ada@example.com' ),
							),
						),
						array(
							array( 'type' => 'action_success', 'action' => 'update_acf_repeater' ),
							array(
								'type'   => 'acf_value',
								'target' => '1',
								'field'  => 'suite_attendees',
								'value'  => array(
									array( 'name' => 'Ada', 'email' => 'ada@example.com' ),
								),
							),
						),
						'seed_post'
					),
				),
				array( $B::action( 'update_acf_repeater', array(
					'target_type'     => 'post',
					'target_id_field' => 'post_id',
					'acf_field_key'   => 'suite_attendees',
					'repeater_field'  => 'attendees',
				) ) )
			),
			self::wrap(
				'act_015_assign_role',
				'Assign editor role to current user',
				array( $B::field( 'confirm', 'text', array( 'required' => true ) ) ),
				array(
					self::pass(
						'role_assigned',
						array( 'confirm' => 'yes' ),
						array( array( 'type' => 'action_success', 'action' => 'assign_role' ) ),
						'seed_subscriber'
					),
				),
				array( $B::action( 'assign_role', array(
					'target' => 'current_user',
					'role'   => 'editor',
					'mode'   => 'replace',
				) ) )
			),
			self::wrap(
				'act_016_lost_password',
				'Trigger lost password email',
				array( $B::field( 'email', 'email', array( 'required' => true ) ) ),
				array(
					self::pass(
						'reset_sent',
						array( 'email' => 'meta@example.com' ),
						array( array( 'type' => 'action_success', 'action' => 'lost_password' ) ),
						'seed_meta_user'
					),
					self::actionFail(
						'unknown_email',
						array( 'email' => 'missing@example.com' ),
						'lost_password',
						'seed_meta_user',
						0
					),
				),
				array( $B::action( 'lost_password', array( 'login_field' => 'email' ) ) )
			),
			self::wrap(
				'act_017_send_email',
				'Send email action',
				array(
					$B::field( 'name', 'text', array( 'required' => true ) ),
					$B::field( 'email', 'email', array( 'required' => true ) ),
				),
				array(
					self::pass(
						'email_action_ok',
						array( 'name' => 'Ada', 'email' => 'ada@example.com' ),
						array( array( 'type' => 'action_success', 'action' => 'send_email' ) )
					),
				),
				array( $B::action( 'send_email', array(
					'to'      => '{field:email}',
					'subject' => 'Hello {field:name}',
					'body'    => 'Thanks {field:name}',
				) ) )
			),
			self::wrap(
				'act_018_webhook',
				'Webhook POST action',
				array( $B::field( 'payload', 'text', array( 'required' => true ) ) ),
				array(
					self::pass(
						'webhook_ok',
						array( 'payload' => 'hello' ),
						array( array( 'type' => 'action_success', 'action' => 'webhook' ) )
					),
				),
				array( $B::action( 'webhook', array(
					'webhook_url'    => 'https://hooks.example.com/suite',
					'method'         => 'POST',
					'payload_format' => 'json',
				) ) )
			),
			self::wrap(
				'act_019_update_post_meta_existing',
				'Update meta on existing post',
				array(
					$B::field( 'score', 'number', array( 'required' => true ) ),
				),
				array(
					self::pass(
						'meta_written',
						array( 'score' => '99' ),
						array(
							array( 'type' => 'action_success', 'action' => 'update_post_meta' ),
							array( 'type' => 'post_meta', 'title' => 'Seed Post', 'key' => 'score', 'value' => '99' ),
						),
						'seed_post'
					),
				),
				array( $B::action( 'update_post_meta', array(
					'post_id'       => '1',
					'meta_mappings' => array(
						array( 'meta_key' => 'score', 'field_id' => 'score' ),
					),
				) ) )
			),
			self::wrap(
				'act_020_create_post_and_register_combo',
				'Chained create post then register',
				array(
					$B::field( 'title', 'text', array( 'required' => true ) ),
					$B::field( 'email', 'email', array( 'required' => true ) ),
					$B::field( 'password', 'password', array( 'required' => true ) ),
				),
				array(
					self::pass(
						'both_actions',
						array(
							'title'    => 'Combo Post',
							'email'    => 'combo@example.com',
							'password' => 'ComboPass1!',
						),
						array(
							array( 'type' => 'post_created', 'title' => 'Combo Post' ),
							array( 'type' => 'user_exists', 'email' => 'combo@example.com' ),
						)
					),
				),
				array(
					$B::action( 'create_post', array(
						'post_title_field' => 'title',
						'post_status'      => 'publish',
					) ),
					$B::action( 'register', array(
						'email_field'    => 'email',
						'password_field' => 'password',
					) ),
				)
			),
			self::wrap(
				'act_021_register_duplicate_email',
				'Register fails on duplicate email',
				array(
					$B::field( 'email', 'email', array( 'required' => true ) ),
					$B::field( 'password', 'password', array( 'required' => true ) ),
				),
				array(
					self::actionFail(
						'duplicate',
						array( 'email' => 'meta@example.com', 'password' => 'DupPass1!' ),
						'register',
						'seed_meta_user',
						0
					),
				),
				array( $B::action( 'register', array(
					'email_field'    => 'email',
					'password_field' => 'password',
				) ) )
			),
			self::wrap(
				'act_022_create_post_invalid_status_field',
				'Validation blocks post when excerpt too long',
				array(
					$B::field( 'title', 'text', array( 'required' => true ) ),
					$B::field( 'excerpt', 'textarea', array(
						'validation_rules' => array( $B::rule( 'max_length', '10' ) ),
					) ),
				),
				array(
					self::fail(
						'no_post_on_fail',
						array( 'title' => 'Blocked Post', 'excerpt' => str_repeat( 'x', 20 ) ),
						array( 'excerpt' ),
						array(
							array( 'type' => 'post_not_created', 'title' => 'Blocked Post' ),
							array( 'type' => 'post_count_delta', 'delta' => 0 ),
						)
					),
					self::pass(
						'post_on_pass',
						array( 'title' => 'Blocked Post', 'excerpt' => 'short' ),
						array( array( 'type' => 'post_created', 'title' => 'Blocked Post' ) )
					),
				),
				array( $B::action( 'create_post', array(
					'post_title_field'   => 'title',
					'post_excerpt_field' => 'excerpt',
					'post_status'        => 'publish',
				) ) )
			),
			self::wrap(
				'act_023_taxonomy_on_create_post',
				'Create post with category term',
				array(
					$B::field( 'title', 'text', array( 'required' => true ) ),
					$B::field( 'cat', 'number', array( 'required' => true ) ),
				),
				array(
					self::pass(
						'post_with_tax',
						array( 'title' => 'Tax Post', 'cat' => '1' ),
						array(
							array( 'type' => 'post_created', 'title' => 'Tax Post' ),
							array( 'type' => 'action_success', 'action' => 'create_post' ),
						),
						'seed_term'
					),
				),
				array( $B::action( 'create_post', array(
					'post_title_field'  => 'title',
					'post_status'       => 'publish',
					'taxonomy_mappings' => array(
						array( 'taxonomy' => 'category', 'field_id' => 'cat' ),
					),
				) ) )
			),
			self::wrap(
				'act_024_save_submission_only',
				'Save submission action only',
				array( $B::field( 'note', 'text', array( 'required' => true ) ) ),
				array(
					self::pass(
						'saved',
						array( 'note' => 'stored' ),
						array( array( 'type' => 'action_success', 'action' => 'save_submission' ) )
					),
				),
				array( $B::action( 'save_submission' ) )
			),
			self::wrap(
				'act_025_confirm_password_action_only',
				'Confirm password action strength gate',
				array(
					$B::field( 'password', 'password', array( 'required' => true ) ),
					$B::field( 'confirm_password', 'password', array( 'required' => true ) ),
				),
				array(
					self::pass(
						'confirmed',
						array( 'password' => 'ActionPass1!', 'confirm_password' => 'ActionPass1!' ),
						array( array( 'type' => 'action_success', 'action' => 'confirm_password' ) )
					),
					self::actionFail(
						'weak',
						array( 'password' => 'short', 'confirm_password' => 'short' ),
						'confirm_password'
					),
				),
				array( $B::action( 'confirm_password', array(
					'password_field' => 'password',
					'confirm_field'  => 'confirm_password',
					'min_length'     => 8,
					'require_upper'  => true,
					'require_number' => true,
				) ) )
			),
		);
	}

	// ------------------------------------------------------------------
	// 15 conditional forms
	// ------------------------------------------------------------------

	private static function conditionForms(): array {
		$B = CLEFA_Programmatic_Form_Builder::class;

		$forms = array();
		for ( $i = 1; $i <= 15; $i++ ) {
			$forms[] = self::conditionForm( $i, $B );
		}
		return $forms;
	}

	private static function conditionForm( int $index, string $B ): array {
		$id = sprintf( 'cond_%02d', $index );

		switch ( $index ) {
			case 1:
				return self::wrap(
					$id . '_show_detail',
					'Show detail when type is business',
					array(
						$B::field( 'account_type', 'radio', array(
							'required' => true,
							'options'  => $B::select_options( array( 'personal' => 'Personal', 'business' => 'Business' ) ),
						) ),
						$B::field( 'company', 'text', array(
							'conditions' => array( $B::condition( 'account_type', 'equals', 'business', 'show' ) ),
						) ),
					),
					array(
						self::pass( 'business_shows_company', array( 'account_type' => 'business', 'company' => 'Acme' ) ),
						self::pass( 'personal_hides_company', array( 'account_type' => 'personal' ) ),
					)
				);
			case 2:
				return self::wrap(
					$id . '_require_company',
					'Require company when business',
					array(
						$B::field( 'account_type', 'radio', array(
							'required' => true,
							'options'  => $B::select_options( array( 'personal' => 'Personal', 'business' => 'Business' ) ),
						) ),
						$B::field( 'company', 'text', array(
							'conditions' => array(
								$B::condition( 'account_type', 'equals', 'business', 'show' ),
								$B::condition( 'account_type', 'equals', 'business', 'require' ),
							),
						) ),
					),
					array(
						self::fail( 'business_missing_company', array( 'account_type' => 'business' ), array( 'company' ) ),
						self::pass( 'business_with_company', array( 'account_type' => 'business', 'company' => 'Acme Ltd' ) ),
					)
				);
			default:
				$trigger = 'opt_' . $index;
				return self::wrap(
					$id . '_toggle_field_' . $index,
					'Conditional toggle #' . $index,
					array(
						$B::field( 'toggle', 'radio', array(
							'required' => true,
							'options'  => $B::select_options( array( 'no' => 'No', 'yes' => 'Yes' ) ),
						) ),
						$B::field( $trigger, 'text', array(
							'conditions' => array( $B::condition( 'toggle', 'equals', 'yes', 'show' ) ),
						) ),
					),
					array(
						self::pass( 'shown', array( 'toggle' => 'yes', $trigger => 'filled' ) ),
						self::pass( 'hidden_ok', array( 'toggle' => 'no' ) ),
					)
				);
		}
	}

	// ------------------------------------------------------------------
	// 10 integration forms
	// ------------------------------------------------------------------

	private static function integrationForms(): array {
		$B = CLEFA_Programmatic_Form_Builder::class;

		return array(
			self::wrap(
				'int_01_full_registration',
				'Full registration with terms',
				array(
					$B::field( 'username', 'text', array(
						'required'         => true,
						'validation_rules' => array(
							$B::rule( 'min_length', '3' ),
							$B::rule( 'no_spaces' ),
							$B::rule( 'alphanumeric' ),
						),
					) ),
					$B::field( 'email', 'email', array( 'required' => true ) ),
					$B::field( 'password', 'password', array(
						'required'         => true,
						'validation_rules' => array( $B::rule( 'password_strength', 'medium' ) ),
					) ),
					$B::field( 'confirm_password', 'password', array(
						'required'         => true,
						'validation_rules' => array( $B::rule( 'confirm_password', 'password' ) ),
					) ),
					$B::field( 'terms', 'checkbox', array(
						'required'         => true,
						'options'          => $B::checkbox_options( array( 'yes' => 'I agree' ) ),
						'validation_rules' => array( $B::rule( 'min_checked', '1' ) ),
					) ),
				),
				array(
					self::pass(
						'registers',
						array(
							'username'         => 'int_user',
							'email'            => 'int01@example.com',
							'password'         => 'BrainPass1',
							'confirm_password' => 'BrainPass1',
							'terms'            => array( 'yes' ),
						),
						array(
							array( 'type' => 'user_exists', 'login' => 'int_user' ),
							array( 'type' => 'action_success', 'action' => 'register' ),
						)
					),
					self::fail(
						'terms_missing',
						array(
							'username'         => 'int_user',
							'email'            => 'int01@example.com',
							'password'         => 'BrainPass1',
							'confirm_password' => 'BrainPass1',
							'terms'            => array(),
						),
						array( 'terms' )
					),
				),
				array( $B::action( 'register', array(
					'username_field' => 'username',
					'email_field'    => 'email',
					'password_field' => 'password',
				) ) )
			),
			self::wrap(
				'int_02_create_post_full_validation',
				'Create post after multi-field validation',
				array(
					$B::field( 'title', 'text', array(
						'required'         => true,
						'validation_rules' => array( $B::rule( 'min_length', '3' ) ),
					) ),
					$B::field( 'email', 'email', array( 'required' => true ) ),
					$B::field( 'website', 'text', array(
						'validation_rules' => array( $B::rule( 'url' ) ),
					) ),
				),
				array(
					self::pass(
						'created',
						array(
							'title'   => 'Integration Post',
							'email'   => 'post@example.com',
							'website' => 'https://example.com',
						),
						array( array( 'type' => 'post_created', 'title' => 'Integration Post' ) )
					),
					self::fail(
						'bad_website',
						array(
							'title'   => 'Integration Post',
							'email'   => 'post@example.com',
							'website' => 'not-url',
						),
						array( 'website' ),
						array( array( 'type' => 'post_not_created', 'title' => 'Integration Post' ) )
					),
				),
				array( $B::action( 'create_post', array(
					'post_title_field' => 'title',
					'post_status'      => 'publish',
				) ) )
			),
			self::wrap(
				'int_03_repeater_validation',
				'Repeater row validation',
				array(
					$B::field( 'attendees', 'repeater', array(
						'min_rows'   => 1,
						'sub_fields' => array(
							$B::field( 'name', 'text', array( 'required' => true ) ),
							$B::field( 'email', 'email', array( 'required' => true ) ),
						),
					) ),
				),
				array(
					self::pass(
						'valid_row',
						array(
							'attendees' => array(
								array( 'name' => 'Ada', 'email' => 'ada@example.com' ),
							),
						)
					),
					self::fail(
						'missing_email',
						array(
							'attendees' => array(
								array( 'name' => 'Ada' ),
							),
						),
						array( 'attendees[0][email]' )
					),
				)
			),
			self::wrap(
				'int_04_password_profile_update',
				'Profile update with password change',
				array(
					$B::field( 'display_name', 'text', array( 'required' => true ) ),
					$B::field( 'password', 'password', array( 'required' => true ) ),
					$B::field( 'confirm_password', 'password', array(
						'required'         => true,
						'validation_rules' => array( $B::rule( 'confirm_password', 'password' ) ),
					) ),
				),
				array(
					self::pass(
						'updated',
						array(
							'display_name'     => 'New Name',
							'password'         => 'ProfilePass1!',
							'confirm_password' => 'ProfilePass1!',
						),
						array(
							array( 'type' => 'action_success', 'action' => 'update_user_meta' ),
							array( 'type' => 'action_success', 'action' => 'change_password' ),
							array( 'type' => 'user_password', 'login' => 'meta_suite', 'password' => 'ProfilePass1!' ),
						),
						'seed_meta_user'
					),
				),
				array(
					$B::action( 'update_user_meta', array(
						'core_field_mappings' => array(
							array( 'core_field' => 'display_name', 'field_id' => 'display_name' ),
						),
					) ),
					$B::action( 'confirm_password', array(
						'password_field' => 'password',
						'confirm_field'  => 'confirm_password',
						'min_length'     => 8,
					) ),
					$B::action( 'change_password', array( 'password_field' => 'password' ) ),
				)
			),
			self::wrap(
				'int_05_product_create_validation_fail',
				'Product not created when price invalid',
				array(
					$B::field( 'name', 'text', array( 'required' => true ) ),
					$B::field( 'price', 'number', array(
						'validation_rules' => array( $B::rule( 'min_value', '1' ) ),
					) ),
				),
				array(
					self::fail(
						'price_too_low',
						array( 'name' => 'Bad Product', 'price' => '0' ),
						array( 'price' ),
						array( array( 'type' => 'product_not_exists', 'name' => 'Bad Product' ) )
					),
				),
				array( $B::action( 'create_product', array(
					'title_field' => 'name',
					'price_field' => 'price',
				) ) )
			),
			self::wrap(
				'int_06_acf_and_post',
				'Create post then write ACF',
				array(
					$B::field( 'title', 'text', array( 'required' => true ) ),
					$B::field( 'summary', 'textarea', array( 'required' => true ) ),
				),
				array(
					self::pass(
						'post_and_acf',
						array( 'title' => 'ACF Combo Post', 'summary' => 'Summary text' ),
						array(
							array( 'type' => 'post_created', 'title' => 'ACF Combo Post' ),
						)
					),
				),
				array(
					$B::action( 'create_post', array(
						'post_title_field' => 'title',
						'post_status'      => 'publish',
					) ),
				)
			),
			self::wrap(
				'int_07_multi_fail_errors',
				'Multiple fields fail together',
				array(
					$B::field( 'name', 'text', array( 'required' => true ) ),
					$B::field( 'email', 'email', array( 'required' => true ) ),
					$B::field( 'age', 'number', array(
						'validation_rules' => array( $B::rule( 'min_value', '18' ) ),
					) ),
				),
				array(
					self::fail(
						'all_invalid',
						array( 'name' => '', 'email' => 'bad', 'age' => '10' ),
						array( 'name', 'email', 'age' )
					),
				)
			),
			self::wrap(
				'int_08_checkbox_terms_gate',
				'Terms gate before register',
				array(
					$B::field( 'email', 'email', array( 'required' => true ) ),
					$B::field( 'password', 'password', array( 'required' => true ) ),
					$B::field( 'terms', 'checkbox', array(
						'required'         => true,
						'options'          => $B::checkbox_options( array( 'yes' => 'Agree' ) ),
						'validation_rules' => array( $B::rule( 'min_checked', '1' ) ),
					) ),
				),
				array(
					self::fail( 'no_terms', array(
						'email'    => 'terms@example.com',
						'password' => 'TermsPass1!',
						'terms'    => array(),
					), array( 'terms' ) ),
					self::pass( 'with_terms', array(
						'email'    => 'terms@example.com',
						'password' => 'TermsPass1!',
						'terms'    => array( 'yes' ),
					), array( array( 'type' => 'user_exists', 'email' => 'terms@example.com' ) ) ),
				),
				array( $B::action( 'register', array(
					'email_field'    => 'email',
					'password_field' => 'password',
				) ) )
			),
			self::wrap(
				'int_09_date_and_text_combo',
				'Event form date + name validation',
				array(
					$B::field( 'event_name', 'text', array(
						'required'         => true,
						'validation_rules' => array( $B::rule( 'min_length', '3' ) ),
					) ),
					$B::field( 'event_date', 'date', array(
						'required'         => true,
						'validation_rules' => array( $B::rule( 'date_after', 'today' ) ),
					) ),
				),
				array(
					self::pass( 'valid_event', array(
						'event_name' => 'Summit',
						'event_date' => date( 'Y-m-d', strtotime( '+5 days' ) ),
					) ),
					self::fail( 'past_date', array(
						'event_name' => 'Summit',
						'event_date' => date( 'Y-m-d', strtotime( '-2 days' ) ),
					), array( 'event_date' ) ),
				)
			),
			self::wrap(
				'int_10_end_to_end_publish',
				'End-to-end publish workflow',
				array(
					$B::field( 'headline', 'text', array( 'required' => true ) ),
					$B::field( 'body', 'textarea', array(
						'validation_rules' => array( $B::rule( 'min_words', '3' ) ),
					) ),
					$B::field( 'author_note', 'text' ),
				),
				array(
					self::pass(
						'published',
						array(
							'headline'    => 'Launch Announcement',
							'body'        => 'We are launching today worldwide.',
							'author_note' => 'Please review',
						),
						array(
							array( 'type' => 'post_created', 'title' => 'Launch Announcement' ),
							array( 'type' => 'post_meta', 'title' => 'Launch Announcement', 'key' => 'author_note', 'value' => 'Please review' ),
						)
					),
					self::fail(
						'body_too_short',
						array(
							'headline' => 'Launch Announcement',
							'body'     => 'Too short',
						),
						array( 'body' ),
						array( array( 'type' => 'post_not_created', 'title' => 'Launch Announcement' ) )
					),
				),
				array( $B::action( 'create_post', array(
					'post_title_field'   => 'headline',
					'post_content_field' => 'body',
					'post_status'        => 'publish',
					'meta_mappings'      => array(
						array( 'meta_key' => 'author_note', 'field_id' => 'author_note' ),
					),
				) ) )
			),
		);
	}

	// ------------------------------------------------------------------
	// Builders
	// ------------------------------------------------------------------

	private static function single_text_rule_form(
		string $id,
		string $rule,
		string $param,
		string $pass,
		string $fail
	): array {
		$B = CLEFA_Programmatic_Form_Builder::class;
		return self::wrap(
			$id,
			"Validation rule: {$rule}",
			array( $B::field( 'value', 'text', array(
				'validation_rules' => array( $B::rule( $rule, $param ) ),
			) ) ),
			array(
				self::pass( 'valid', array( 'value' => $pass ) ),
				self::fail( 'invalid', array( 'value' => $fail ), array( 'value' ) ),
			)
		);
	}

	private static function edge_value_form( array $edge ): array {
		$B       = CLEFA_Programmatic_Form_Builder::class;
		$id      = $edge[0];
		$label   = $edge[1];
		$type    = $edge[2];
		$req     = $edge[3];
		$val     = $edge[4];
		$rule    = $edge[5] ?? null;
		$param   = $edge[6] ?? '';
		$fail_only = ! empty( $edge[7] );

		if ( 'val_050_confirm_both_empty' === $id ) {
			$fields = array(
				$B::field( 'password', 'password', array( 'required' => true ) ),
				$B::field( 'confirm_password', 'password', array(
					'required'         => true,
					'validation_rules' => array( $B::rule( 'confirm_password', 'password' ) ),
				) ),
			);
			return self::wrap(
				$id,
				$label,
				$fields,
				array(
					self::fail( 'mismatch_empty', array( 'password' => 'BrainPass1', 'confirm_password' => '' ), array( 'confirm_password' ) ),
				)
			);
		}

		if ( 'val_031_whitespace_required' === $id ) {
			return self::wrap(
				$id,
				$label,
				array( $B::field( 'value', 'text', array( 'required' => true ) ) ),
				array(
					self::fail( 'whitespace_only', array( 'value' => '   ' ), array( 'value' ) ),
					self::pass( 'real_value', array( 'value' => 'Valid' ) ),
				)
			);
		}

		$field = $B::field( 'value', $type, array( 'required' => (bool) $req ) );
		if ( $rule ) {
			$field['validation_rules'] = array( $B::rule( $rule, $param ) );
		}

		if ( $fail_only || str_contains( $id, '_fail' ) || str_contains( $id, '_short' ) ) {
			return self::wrap(
				$id,
				$label,
				array( $field ),
				array(
					self::fail( 'invalid', array( 'value' => $val ), array( 'value' ) ),
				)
			);
		}

		return self::wrap(
			$id,
			$label,
			array( $field ),
			array(
				self::pass( 'valid', array( 'value' => $val ) ),
			)
		);
	}

	private static function wrap(
		string $id,
		string $label,
		array $fields,
		array $cases,
		array $actions = null
	): array {
		$actions = $actions ?? CLEFA_Programmatic_Form_Builder::save_only();
		return array(
			'id'     => $id,
			'label'  => $label,
			'config' => CLEFA_Programmatic_Form_Builder::form( "[SUITE] {$label}", $fields, $actions ),
			'cases'  => $cases,
		);
	}

	private static function pass( string $name, array $data, array $verify = array(), string $seed = '', ?int $user_id = null ): array {
		$case = array(
			'name'        => $name,
			'data'        => $data,
			'expect_pass' => true,
			'verify'      => $verify,
			'seed'        => $seed,
		);
		if ( null !== $user_id ) {
			$case['user_id'] = $user_id;
		}
		return $case;
	}

	private static function fail(
		string $name,
		array $data,
		array $error_fields = array(),
		array $verify = array(),
		string $seed = '',
		?int $user_id = null
	): array {
		$case = array(
			'name'         => $name,
			'data'         => $data,
			'expect_pass'  => false,
			'error_fields' => $error_fields,
			'skip_actions' => empty( $verify ),
			'verify'       => $verify,
			'seed'         => $seed,
		);
		if ( null !== $user_id ) {
			$case['user_id'] = $user_id;
		}
		return $case;
	}

	private static function actionFail(
		string $name,
		array $data,
		string $action,
		string $seed = '',
		?int $user_id = null
	): array {
		$case = array(
			'name'         => $name,
			'data'         => $data,
			'expect_pass'  => true,
			'skip_actions' => false,
			'verify'       => array(
				array( 'type' => 'action_failure', 'action' => $action ),
			),
			'seed'         => $seed,
		);
		if ( null !== $user_id ) {
			$case['user_id'] = $user_id;
		}
		return $case;
	}

	/**
	 * Seed stores before a case (users, posts, products, terms).
	 */
	public static function applySeed( string $seed ): void {
		switch ( $seed ) {
			case 'seed_login_user':
				wp_insert_user( array(
					'user_login' => 'login_suite',
					'user_email' => 'login@example.com',
					'user_pass'  => 'LoginPass1!',
				) );
				break;
			case 'seed_meta_user':
				$user_id = wp_insert_user( array(
					'user_login'   => 'meta_suite',
					'user_email'   => 'meta@example.com',
					'user_pass'    => 'OldPass1!',
					'display_name' => 'Meta User',
				) );
				wp_set_current_user( $user_id );
				break;
			case 'seed_subscriber':
				$user_id = wp_insert_user( array(
					'user_login' => 'sub_suite',
					'user_email' => 'sub@example.com',
					'user_pass'  => 'SubPass1!',
					'role'       => 'subscriber',
				) );
				wp_set_current_user( $user_id );
				break;
			case 'seed_post':
				wp_set_current_user( 1 );
				wp_insert_post( array(
					'post_title'  => 'Seed Post',
					'post_status' => 'publish',
				) );
				break;
			case 'seed_product':
				wp_set_current_user( 1 );
				( new CLEFA_WC_Product_Action() )->run(
					array( 'name' => 'Seed Product', 'price' => '10.00' ),
					array(),
					0,
					array(
						'title_field' => 'name',
						'price_field' => 'price',
					)
				);
				break;
			case 'seed_term':
				wp_insert_term( 'News', 'category' );
				break;
		}
	}
}
