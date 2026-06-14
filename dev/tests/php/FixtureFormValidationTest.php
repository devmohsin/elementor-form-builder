<?php
/**
 * End-to-end validation tests using real seeded form fixture JSON files.
 *
 * Each case loads a fixture config, submits realistic pass/fail payloads,
 * and asserts validation behaves like a real form submission.
 */

class FixtureFormValidationTest extends \PHPUnit\Framework\TestCase {

	public static function validationScenarioProvider(): array {
		$tomorrow = date( 'Y-m-d', strtotime( '+1 day' ) );
		$yesterday = date( 'Y-m-d', strtotime( '-1 day' ) );

		$speaker_bio = 'Experienced engineer with a long career building reliable systems for global audiences worldwide today.';

		return array(
			// validation-basic
			'validation-basic: empty submission fails required fields' => array(
				'validation-basic',
				array(),
				false,
				array( 'full_name', 'email', 'country' ),
			),
			'validation-basic: valid complete submission passes' => array(
				'validation-basic',
				array(
					'full_name' => 'Jane Smith',
					'email'     => 'jane@example.com',
					'age'       => '30',
					'bio'       => 'I enjoy building great products for people every day.',
					'country'   => 'au',
					'interests' => array( 'tech', 'design' ),
					'website'   => 'https://example.com',
				),
				true,
				array(),
			),
			'validation-basic: invalid email fails' => array(
				'validation-basic',
				array(
					'full_name' => 'Jane Smith',
					'email'     => 'not-an-email',
					'country'   => 'au',
				),
				false,
				array( 'email' ),
			),
			'validation-basic: age under minimum fails' => array(
				'validation-basic',
				array(
					'full_name' => 'Jane Smith',
					'email'     => 'jane@example.com',
					'country'   => 'au',
					'age'       => '16',
				),
				false,
				array( 'age' ),
			),
			'validation-basic: bio with too few words fails' => array(
				'validation-basic',
				array(
					'full_name' => 'Jane Smith',
					'email'     => 'jane@example.com',
					'country'   => 'au',
					'bio'       => 'Too short bio here.',
				),
				false,
				array( 'bio' ),
			),
			'validation-basic: too many interests checked fails' => array(
				'validation-basic',
				array(
					'full_name' => 'Jane Smith',
					'email'     => 'jane@example.com',
					'country'   => 'au',
					'interests' => array( 'tech', 'design', 'business', 'other' ),
				),
				false,
				array( 'interests' ),
			),
			'validation-basic: invalid website url fails' => array(
				'validation-basic',
				array(
					'full_name' => 'Jane Smith',
					'email'     => 'jane@example.com',
					'country'   => 'au',
					'website'   => 'not-a-url',
				),
				false,
				array( 'website' ),
			),
			'validation-basic: letters-only name passes alpha rule' => array(
				'validation-basic',
				array(
					'full_name' => 'Jane Smith',
					'email'     => 'jane@example.com',
					'country'   => 'nz',
				),
				true,
				array(),
			),
			'validation-basic: numeric characters in name fail alpha rule' => array(
				'validation-basic',
				array(
					'full_name' => 'Jane123',
					'email'     => 'jane@example.com',
					'country'   => 'nz',
				),
				false,
				array( 'full_name' ),
			),

			// registration-form (brainstorm-style password + confirm)
			'registration-form: missing terms fails' => array(
				'registration-form',
				array(
					'username'         => 'john_doe',
					'email'            => 'john@example.com',
					'password'         => 'BrainPass1',
					'confirm_password' => 'BrainPass1',
					'account_type'     => 'personal',
				),
				false,
				array( 'agree_terms' ),
			),
			'registration-form: weak password fails strength rule' => array(
				'registration-form',
				array(
					'username'         => 'john_doe',
					'email'            => 'john@example.com',
					'password'         => 'password',
					'confirm_password' => 'password',
					'account_type'     => 'personal',
					'agree_terms'      => array( 'yes' ),
				),
				false,
				array( 'password' ),
			),
			'registration-form: password mismatch fails confirm rule' => array(
				'registration-form',
				array(
					'username'         => 'john_doe',
					'email'            => 'john@example.com',
					'password'         => 'BrainPass1',
					'confirm_password' => 'BrainPass2',
					'account_type'     => 'personal',
					'agree_terms'      => array( 'yes' ),
				),
				false,
				array( 'confirm_password' ),
			),
			'registration-form: valid personal account passes' => array(
				'registration-form',
				array(
					'username'         => 'john_doe',
					'email'            => 'john@example.com',
					'password'         => 'BrainPass1',
					'confirm_password' => 'BrainPass1',
					'account_type'     => 'personal',
					'agree_terms'      => array( 'yes' ),
				),
				true,
				array(),
			),
			'registration-form: business account without company fails conditional required' => array(
				'registration-form',
				array(
					'username'         => 'biz_user',
					'email'            => 'biz@example.com',
					'password'         => 'BrainPass1',
					'confirm_password' => 'BrainPass1',
					'account_type'     => 'business',
					'agree_terms'      => array( 'yes' ),
				),
				false,
				array( 'company_name' ),
			),
			'registration-form: valid business account passes' => array(
				'registration-form',
				array(
					'username'         => 'biz_user',
					'email'            => 'biz@example.com',
					'password'         => 'BrainPass1',
					'confirm_password' => 'BrainPass1',
					'account_type'     => 'business',
					'company_name'     => 'Acme Ltd',
					'company_size'     => 'small',
					'agree_terms'      => array( 'yes' ),
				),
				true,
				array(),
			),
			'registration-form: username with spaces fails' => array(
				'registration-form',
				array(
					'username'         => 'john doe',
					'email'            => 'john@example.com',
					'password'         => 'BrainPass1',
					'confirm_password' => 'BrainPass1',
					'account_type'     => 'personal',
					'agree_terms'      => array( 'yes' ),
				),
				false,
				array( 'username' ),
			),

			// validation-advanced
			'validation-advanced: valid account details pass' => array(
				'validation-advanced',
				array(
					'username'         => 'john_doe99',
					'email'            => 'you@example.com',
					'phone'            => '+61400000000',
					'website'          => 'https://yoursite.com',
					'promo_code'       => 'SAVE-1234',
					'bio'              => 'A plain text biography without links or markup here.',
					'password'         => 'BrainPass1',
					'confirm_password' => 'BrainPass1',
				),
				true,
				array(),
			),
			'validation-advanced: invalid promo code regex fails' => array(
				'validation-advanced',
				array(
					'username'         => 'john_doe99',
					'email'            => 'you@example.com',
					'promo_code'       => 'bad-code',
					'password'         => 'BrainPass1',
					'confirm_password' => 'BrainPass1',
				),
				false,
				array( 'promo_code' ),
			),
			'validation-advanced: html in bio fails no_html rule' => array(
				'validation-advanced',
				array(
					'username'         => 'john_doe99',
					'email'            => 'you@example.com',
					'bio'              => '<b>Hello</b> world',
					'password'         => 'BrainPass1',
					'confirm_password' => 'BrainPass1',
				),
				false,
				array( 'bio' ),
			),
			'validation-advanced: url in bio fails no_urls rule' => array(
				'validation-advanced',
				array(
					'username'         => 'john_doe99',
					'email'            => 'you@example.com',
					'bio'              => 'Visit https://example.com for more',
					'password'         => 'BrainPass1',
					'confirm_password' => 'BrainPass1',
				),
				false,
				array( 'bio' ),
			),
			'validation-advanced: invalid phone fails' => array(
				'validation-advanced',
				array(
					'username'         => 'john_doe99',
					'email'            => 'you@example.com',
					'phone'            => 'abc',
					'password'         => 'BrainPass1',
					'confirm_password' => 'BrainPass1',
				),
				false,
				array( 'phone' ),
			),

			// repeater-basic
			'repeater-basic: valid event with attendee passes' => array(
				'repeater-basic',
				array(
					'event_name' => 'Company Conference 2026',
					'event_date' => $tomorrow,
					'attendees'  => array(
						array(
							'attendee_name'  => 'Ada Lovelace',
							'attendee_email' => 'ada@example.com',
							'attendee_role'  => 'attendee',
							'dietary'        => 'none',
						),
					),
				),
				true,
				array(),
			),
			'repeater-basic: past event date fails' => array(
				'repeater-basic',
				array(
					'event_name' => 'Company Conference 2026',
					'event_date' => $yesterday,
					'attendees'  => array(
						array(
							'attendee_name'  => 'Ada Lovelace',
							'attendee_email' => 'ada@example.com',
							'attendee_role'  => 'attendee',
						),
					),
				),
				false,
				array( 'event_date' ),
			),
			'repeater-basic: speaker without required bio fails' => array(
				'repeater-basic',
				array(
					'event_name' => 'Company Conference 2026',
					'event_date' => $tomorrow,
					'attendees'  => array(
						array(
							'attendee_name'  => 'Ada Lovelace',
							'attendee_email' => 'ada@example.com',
							'attendee_role'  => 'speaker',
						),
					),
				),
				false,
				array( 'attendees[0][speaker_bio]' ),
			),
			'repeater-basic: speaker with valid bio passes' => array(
				'repeater-basic',
				array(
					'event_name' => 'Company Conference 2026',
					'event_date' => $tomorrow,
					'attendees'  => array(
						array(
							'attendee_name'  => 'Ada Lovelace',
							'attendee_email' => 'ada@example.com',
							'attendee_role'  => 'speaker',
							'speaker_bio'    => $speaker_bio,
						),
					),
				),
				true,
				array(),
			),
			'repeater-basic: dietary other without note fails' => array(
				'repeater-basic',
				array(
					'event_name' => 'Company Conference 2026',
					'event_date' => $tomorrow,
					'attendees'  => array(
						array(
							'attendee_name'  => 'Grace Hopper',
							'attendee_email' => 'grace@example.com',
							'attendee_role'  => 'attendee',
							'dietary'        => 'other',
						),
					),
				),
				false,
				array( 'attendees[0][dietary_other]' ),
			),
			'repeater-basic: dietary other with note passes' => array(
				'repeater-basic',
				array(
					'event_name' => 'Company Conference 2026',
					'event_date' => $tomorrow,
					'attendees'  => array(
						array(
							'attendee_name'  => 'Grace Hopper',
							'attendee_email' => 'grace@example.com',
							'attendee_role'  => 'attendee',
							'dietary'        => 'other',
							'dietary_other'  => 'No peanuts please',
						),
					),
				),
				true,
				array(),
			),
			'repeater-basic: missing attendee email fails' => array(
				'repeater-basic',
				array(
					'event_name' => 'Company Conference 2026',
					'event_date' => $tomorrow,
					'attendees'  => array(
						array(
							'attendee_name' => 'No Email Person',
							'attendee_role' => 'attendee',
						),
					),
				),
				false,
				array( 'attendees[0][attendee_email]' ),
			),
		);
	}

	/** @dataProvider validationScenarioProvider */
	public function test_fixture_form_validation(
		string $fixture_slug,
		array $data,
		bool $expect_pass,
		array $expected_error_fields
	): void {
		$config = CLEFA_Fixture_Form_Test_Helper::load_fixture_config( $fixture_slug );
		$errors = CLEFA_Fixture_Form_Test_Helper::validate( $config, $data );

		if ( $expect_pass ) {
			$this->assertEmpty(
				$errors,
				'Expected validation to pass but got errors: ' . wp_json_encode( $errors )
			);
			return;
		}

		$this->assertNotEmpty( $errors, 'Expected validation to fail but no errors were returned.' );

		foreach ( $expected_error_fields as $field_key ) {
			$this->assertArrayHasKey(
				$field_key,
				$errors,
				'Expected error on "' . $field_key . '" but errors were: ' . wp_json_encode( $errors )
			);
		}
	}

	public function test_validation_basic_minimal_required_only_passes(): void {
		$config = CLEFA_Fixture_Form_Test_Helper::load_fixture_config( 'validation-basic' );
		$errors = CLEFA_Fixture_Form_Test_Helper::validate(
			$config,
			array(
				'full_name' => 'Jane Smith',
				'email'     => 'jane@example.com',
				'country'   => 'au',
			)
		);

		$this->assertEmpty( $errors );
	}

	public function test_registration_form_personal_hides_company_fields_from_validation(): void {
		$config = CLEFA_Fixture_Form_Test_Helper::load_fixture_config( 'registration-form' );
		$errors = CLEFA_Fixture_Form_Test_Helper::validate(
			$config,
			array(
				'username'         => 'solo_user',
				'email'            => 'solo@example.com',
				'password'         => 'BrainPass1',
				'confirm_password' => 'BrainPass1',
				'account_type'     => 'personal',
				'agree_terms'      => array( 'yes' ),
			)
		);

		$this->assertEmpty( $errors );
		$this->assertArrayNotHasKey( 'company_name', $errors );
	}

	public function test_repeater_basic_vip_guest_can_submit_without_speaker_bio(): void {
		$config = CLEFA_Fixture_Form_Test_Helper::load_fixture_config( 'repeater-basic' );
		$errors = CLEFA_Fixture_Form_Test_Helper::validate(
			$config,
			array(
				'event_name' => 'VIP Summit',
				'event_date' => date( 'Y-m-d', strtotime( '+2 days' ) ),
				'attendees'  => array(
					array(
						'attendee_name'      => 'VIP Guest',
						'attendee_email'     => 'vip@example.com',
						'attendee_role'      => 'vip',
						'vip_requirements'   => 'Wheelchair access',
						'dietary'            => 'vegan',
					),
				),
			)
		);

		$this->assertEmpty( $errors );
	}
}
