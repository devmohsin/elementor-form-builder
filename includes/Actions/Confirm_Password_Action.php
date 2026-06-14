<?php
/**
 * Action: Confirm Password
 *
 * Validates that a password field meets strength requirements and matches
 * a confirmation field. This action is used on password-change and
 * password-reset forms where you want standalone validation beyond what
 * the field-level validator covers.
 *
 * If validation fails the action returns a non-fatal error so subsequent
 * actions still run, unless 'fail_hard' is set to true.
 *
 * Action config keys:
 *   password_field   string   Field ID containing the new password.
 *   confirm_field    string   Field ID containing the confirmation value.
 *   min_length       int      Minimum password length (default 8).
 *   require_upper    bool     Must contain at least one uppercase letter.
 *   require_lower    bool     Must contain at least one lowercase letter.
 *   require_number   bool     Must contain at least one digit.
 *   require_special  bool     Must contain at least one special character.
 *   mismatch_message string   Custom message when passwords do not match.
 *   strength_message string   Custom message when strength rules fail.
 *   fail_hard        bool     If true, sets fatal flag to stop further actions.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CLEFA_Confirm_Password_Action extends CLEFA_Abstract_Action {

	public function run( array $data, array $form_config, $submission_id, array $action_config = array() ) {
		$pw_field  = sanitize_key( $action_config['password_field'] ?? 'password' );
		$cf_field  = sanitize_key( $action_config['confirm_field']  ?? 'confirm_password' );
		$password  = $data[ $pw_field ]  ?? '';
		$confirm   = $data[ $cf_field ]  ?? '';
		$fail_hard = ! empty( $action_config['fail_hard'] );

		$errors = array();

		// Match check
		if ( $password !== $confirm ) {
			$errors['mismatch'] = sanitize_text_field(
				$action_config['mismatch_message'] ?? __( 'Passwords do not match.', 'codelinden-elementor-form-addon' )
			);
		}

		// Strength checks
		$min_length = max( 1, absint( $action_config['min_length'] ?? 8 ) );
		$strength_errors = array();

		if ( strlen( $password ) < $min_length ) {
			/* translators: %d: minimum length */
			$strength_errors[] = sprintf( __( 'Password must be at least %d characters.', 'codelinden-elementor-form-addon' ), $min_length );
		}
		if ( ! empty( $action_config['require_upper'] ) && ! preg_match( '/[A-Z]/', $password ) ) {
			$strength_errors[] = __( 'Password must contain at least one uppercase letter.', 'codelinden-elementor-form-addon' );
		}
		if ( ! empty( $action_config['require_lower'] ) && ! preg_match( '/[a-z]/', $password ) ) {
			$strength_errors[] = __( 'Password must contain at least one lowercase letter.', 'codelinden-elementor-form-addon' );
		}
		if ( ! empty( $action_config['require_number'] ) && ! preg_match( '/[0-9]/', $password ) ) {
			$strength_errors[] = __( 'Password must contain at least one number.', 'codelinden-elementor-form-addon' );
		}
		if ( ! empty( $action_config['require_special'] ) && ! preg_match( '/[^a-zA-Z0-9]/', $password ) ) {
			$strength_errors[] = __( 'Password must contain at least one special character.', 'codelinden-elementor-form-addon' );
		}

		if ( ! empty( $strength_errors ) ) {
			$errors['strength'] = ! empty( $action_config['strength_message'] )
				? sanitize_text_field( $action_config['strength_message'] )
				: implode( ' ', $strength_errors );
		}

		if ( ! empty( $errors ) ) {
			return array(
				'success' => false,
				'errors'  => $errors,
				'fatal'   => $fail_hard,
			);
		}

		return array( 'success' => true );
	}
}
