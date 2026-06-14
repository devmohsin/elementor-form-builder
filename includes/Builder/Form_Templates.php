<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Built-in form templates.
 *
 * Each template supplies a ready-to-use config that is merged into the
 * form record on creation.  Fields that carry `"locked": true` cannot be
 * deleted by the builder — only hidden/required-state-toggled.
 */
class CLEFA_Form_Templates {

	/**
	 * Return all template definitions (name, description, icon, config).
	 *
	 * @return array<string, array>
	 */
	public static function all() {
		return array(
			'blank'    => self::blank(),
			'login'    => self::login(),
			'signup'   => self::signup(),
			'contact'  => self::contact(),
			'survey'   => self::survey(),
			'feedback' => self::feedback(),
		);
	}

	/**
	 * Return one template by key, or null if unknown.
	 *
	 * @param  string $key
	 * @return array|null
	 */
	public static function get( $key ) {
		$all = self::all();
		return $all[ sanitize_key( $key ) ] ?? null;
	}

	// -----------------------------------------------------------------------
	// Templates
	// -----------------------------------------------------------------------

	private static function blank() {
		return array(
			'label'       => __( 'Blank Form', 'codelinden-elementor-form-addon' ),
			'description' => __( 'Start from scratch with a single empty step.', 'codelinden-elementor-form-addon' ),
			'icon'        => 'dashicons-plus-alt2',
			'form_type'   => 'standard',
			'config'      => array(
				'steps' => array(
					array(
						'step_id'   => 'step_1',
						'step_name' => 'Step 1',
						'fields'    => array(),
					),
				),
				'settings'      => array(),
				'notifications' => array(),
				'actions'       => array(
					array(
						'action_id'   => 'act_save',
						'action_type' => 'save_submission',
						'label'       => 'Save Submission',
						'enabled'     => true,
						'order'       => 0,
						'conditions'  => array(),
						'config'      => array(),
					),
				),
			),
		);
	}

	private static function login() {
		return array(
			'label'       => __( 'Login Form', 'codelinden-elementor-form-addon' ),
			'description' => __( 'Email + password login with locked core fields.', 'codelinden-elementor-form-addon' ),
			'icon'        => 'dashicons-lock',
			'form_type'   => 'login',
			'config'      => array(
				'steps' => array(
					array(
						'step_id'          => 'step_1',
						'step_name'        => 'Login',
						'submit_button_text' => __( 'Log In', 'codelinden-elementor-form-addon' ),
						'fields'           => array(
							array(
								'field_id'    => 'f_email',
								'field_type'  => 'email',
								'label'       => __( 'Email', 'codelinden-elementor-form-addon' ),
								'required'    => true,
								'locked'      => true,
								'placeholder' => '',
								'autocomplete'=> 'email',
							),
							array(
								'field_id'    => 'f_password',
								'field_type'  => 'password',
								'label'       => __( 'Password', 'codelinden-elementor-form-addon' ),
								'required'    => true,
								'locked'      => true,
								'placeholder' => '',
								'autocomplete'=> 'current-password',
							),
						),
					),
				),
				'settings'      => array(),
				'notifications' => array(),
				'actions'       => array(
					array(
						'action_id'   => 'act_login',
						'action_type' => 'login_user',
						'label'       => 'Log In User',
						'enabled'     => true,
						'order'       => 0,
						'conditions'  => array(),
						'config'      => array(
							'username_field' => 'f_email',
							'password_field' => 'f_password',
						),
					),
				),
			),
		);
	}

	private static function signup() {
		return array(
			'label'       => __( 'Sign Up Form', 'codelinden-elementor-form-addon' ),
			'description' => __( 'Email + password + confirm password registration.', 'codelinden-elementor-form-addon' ),
			'icon'        => 'dashicons-groups',
			'form_type'   => 'registration',
			'config'      => array(
				'steps' => array(
					array(
						'step_id'          => 'step_1',
						'step_name'        => 'Create Account',
						'submit_button_text' => __( 'Create Account', 'codelinden-elementor-form-addon' ),
						'fields'           => array(
							array(
								'field_id'    => 'f_email',
								'field_type'  => 'email',
								'label'       => __( 'Email Address', 'codelinden-elementor-form-addon' ),
								'required'    => true,
								'locked'      => true,
								'placeholder' => '',
								'autocomplete'=> 'email',
							),
							array(
								'field_id'    => 'f_username',
								'field_type'  => 'text',
								'label'       => __( 'Username', 'codelinden-elementor-form-addon' ),
								'required'    => true,
								'locked'      => true,
								'placeholder' => '',
								'autocomplete'=> 'username',
							),
							array(
								'field_id'    => 'f_password',
								'field_type'  => 'password',
								'label'       => __( 'Password', 'codelinden-elementor-form-addon' ),
								'required'    => true,
								'locked'      => true,
								'placeholder' => '',
								'autocomplete'=> 'new-password',
							),
							array(
								'field_id'    => 'f_confirm_password',
								'field_type'  => 'confirm_password',
								'label'       => __( 'Confirm Password', 'codelinden-elementor-form-addon' ),
								'required'    => true,
								'locked'      => true,
								'placeholder' => '',
								'autocomplete'=> 'new-password',
							),
						),
					),
				),
				'settings'      => array(),
				'notifications' => array(),
				'actions'       => array(
					array(
						'action_id'   => 'act_register',
						'action_type' => 'register_user',
						'label'       => 'Register User',
						'enabled'     => true,
						'order'       => 0,
						'conditions'  => array(),
						'config'      => array(
							'email_field'    => 'f_email',
							'username_field' => 'f_username',
							'password_field' => 'f_password',
						),
					),
				),
			),
		);
	}

	private static function contact() {
		return array(
			'label'       => __( 'Contact Form', 'codelinden-elementor-form-addon' ),
			'description' => __( 'Name, email and message — the classic contact form.', 'codelinden-elementor-form-addon' ),
			'icon'        => 'dashicons-email-alt',
			'form_type'   => 'standard',
			'config'      => array(
				'steps' => array(
					array(
						'step_id'   => 'step_1',
						'step_name' => 'Contact Us',
						'fields'    => array(
							array(
								'field_id'   => 'f_name',
								'field_type' => 'text',
								'label'      => __( 'Your Name', 'codelinden-elementor-form-addon' ),
								'required'   => true,
								'locked'     => false,
								'placeholder'=> '',
								'autocomplete'=> 'name',
							),
							array(
								'field_id'   => 'f_email',
								'field_type' => 'email',
								'label'      => __( 'Email Address', 'codelinden-elementor-form-addon' ),
								'required'   => true,
								'locked'     => false,
								'placeholder'=> '',
								'autocomplete'=> 'email',
							),
							array(
								'field_id'   => 'f_subject',
								'field_type' => 'text',
								'label'      => __( 'Subject', 'codelinden-elementor-form-addon' ),
								'required'   => false,
								'locked'     => false,
								'placeholder'=> '',
							),
							array(
								'field_id'   => 'f_message',
								'field_type' => 'textarea',
								'label'      => __( 'Message', 'codelinden-elementor-form-addon' ),
								'required'   => true,
								'locked'     => false,
								'placeholder'=> '',
							),
						),
					),
				),
				'settings'      => array(),
				'notifications' => array(),
				'actions'       => array(
					array(
						'action_id'   => 'act_save',
						'action_type' => 'save_submission',
						'label'       => 'Save Submission',
						'enabled'     => true,
						'order'       => 0,
						'conditions'  => array(),
						'config'      => array(),
					),
					array(
						'action_id'   => 'act_email',
						'action_type' => 'send_email',
						'label'       => 'Send Email Notification',
						'enabled'     => true,
						'order'       => 1,
						'conditions'  => array(),
						'config'      => array(
							'to'      => get_option( 'admin_email' ),
							'subject' => 'New contact form submission',
						),
					),
				),
			),
		);
	}

	private static function survey() {
		return array(
			'label'       => __( 'Multi-Step Survey', 'codelinden-elementor-form-addon' ),
			'description' => __( '2-step survey with personal info then questions.', 'codelinden-elementor-form-addon' ),
			'icon'        => 'dashicons-list-view',
			'form_type'   => 'standard',
			'config'      => array(
				'steps' => array(
					array(
						'step_id'         => 'step_1',
						'step_name'       => 'About You',
						'next_button_text'=> __( 'Continue →', 'codelinden-elementor-form-addon' ),
						'fields'          => array(
							array(
								'field_id'   => 'f_name',
								'field_type' => 'text',
								'label'      => __( 'Full Name', 'codelinden-elementor-form-addon' ),
								'required'   => true,
								'locked'     => false,
							),
							array(
								'field_id'   => 'f_email',
								'field_type' => 'email',
								'label'      => __( 'Email', 'codelinden-elementor-form-addon' ),
								'required'   => true,
								'locked'     => false,
							),
						),
					),
					array(
						'step_id'          => 'step_2',
						'step_name'        => 'Your Answers',
						'prev_button_text' => __( '← Back', 'codelinden-elementor-form-addon' ),
						'submit_button_text'=> __( 'Submit Survey', 'codelinden-elementor-form-addon' ),
						'fields'           => array(
							array(
								'field_id'   => 'f_rating',
								'field_type' => 'range',
								'label'      => __( 'How would you rate us? (1–10)', 'codelinden-elementor-form-addon' ),
								'required'   => false,
								'locked'     => false,
							),
							array(
								'field_id'   => 'f_comments',
								'field_type' => 'textarea',
								'label'      => __( 'Additional Comments', 'codelinden-elementor-form-addon' ),
								'required'   => false,
								'locked'     => false,
							),
						),
					),
				),
				'settings'      => array(),
				'notifications' => array(),
				'actions'       => array(
					array(
						'action_id'   => 'act_save',
						'action_type' => 'save_submission',
						'label'       => 'Save Submission',
						'enabled'     => true,
						'order'       => 0,
						'conditions'  => array(),
						'config'      => array(),
					),
				),
			),
		);
	}

	private static function feedback() {
		return array(
			'label'       => __( 'Feedback Form', 'codelinden-elementor-form-addon' ),
			'description' => __( 'Quick satisfaction rating + optional comment.', 'codelinden-elementor-form-addon' ),
			'icon'        => 'dashicons-thumbs-up',
			'form_type'   => 'standard',
			'config'      => array(
				'steps' => array(
					array(
						'step_id'   => 'step_1',
						'step_name' => 'Feedback',
						'fields'    => array(
							array(
								'field_id'   => 'f_name',
								'field_type' => 'text',
								'label'      => __( 'Your Name', 'codelinden-elementor-form-addon' ),
								'required'   => false,
								'locked'     => false,
							),
							array(
								'field_id'   => 'f_rating',
								'field_type' => 'radio',
								'label'      => __( 'Overall Satisfaction', 'codelinden-elementor-form-addon' ),
								'required'   => true,
								'locked'     => false,
								'options'    => array(
									array( 'label' => 'Excellent', 'value' => 'excellent' ),
									array( 'label' => 'Good',      'value' => 'good' ),
									array( 'label' => 'Fair',      'value' => 'fair' ),
									array( 'label' => 'Poor',      'value' => 'poor' ),
								),
							),
							array(
								'field_id'   => 'f_comments',
								'field_type' => 'textarea',
								'label'      => __( 'Comments (optional)', 'codelinden-elementor-form-addon' ),
								'required'   => false,
								'locked'     => false,
							),
						),
					),
				),
				'settings'      => array(),
				'notifications' => array(),
				'actions'       => array(
					array(
						'action_id'   => 'act_save',
						'action_type' => 'save_submission',
						'label'       => 'Save Submission',
						'enabled'     => true,
						'order'       => 0,
						'conditions'  => array(),
						'config'      => array(),
					),
				),
			),
		);
	}
}
