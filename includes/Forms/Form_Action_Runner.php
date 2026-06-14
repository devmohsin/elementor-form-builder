<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CLEFA_Form_Action_Runner {

	private static $registry = array();

	public static function register_action( $type, $class_name ) {
		self::$registry[ $type ] = $class_name;
	}

	public static function run_actions( array $actions, array $sanitized_data, array $form_config, $submission_id ) {
		$results = array();

		$actions = apply_filters( 'clefa_form_actions', $actions, $form_config );

		foreach ( $actions as $action ) {
			if ( empty( $action['enabled'] ) ) { continue; }
			$type = $action['action_type'] ?? '';
			if ( ! $type ) { continue; }

			$instance = self::resolve_action_class( $type );
			if ( ! $instance ) { continue; }

			$action = apply_filters( 'clefa_action_config', $action, $type, $form_config );

			try {
				do_action( 'clefa_before_action_run', $type, $action, $sanitized_data, $form_config, $submission_id );

				$result = $instance->run( $sanitized_data, $form_config, $submission_id, $action );
				$results[ $type ] = $result;

				do_action( 'clefa_after_action_run', $type, $result, $action, $sanitized_data, $form_config, $submission_id );

				if ( ! empty( $result['fatal'] ) ) {
					break;
				}
			} catch ( Exception $e ) {
				$results[ $type ] = array(
					'success' => false,
					'message' => $e->getMessage(),
				);
			}
		}

		return $results;
	}

	private static function normalize_action_type( $type ) {
		$aliases = array(
			'register_user'       => 'register',
			'login_user'          => 'login',
			'set_user_role'       => 'assign_role',
			'update_acf_field'    => 'update_acf',
			'update_taxonomy'     => 'set_taxonomy',
			'create_wc_product'   => 'create_product',
			'update_wc_product'   => 'create_product',
		);

		return $aliases[ $type ] ?? $type;
	}

	private static function resolve_action_class( $type ) {
		$type = self::normalize_action_type( $type );

		if ( isset( self::$registry[ $type ] ) ) {
			$class = self::$registry[ $type ];
			if ( class_exists( $class ) ) {
				return new $class();
			}
		}

		$built_in = array(
			'save_submission'    => 'CLEFA_Save_Submission_Action',
			'login'              => 'CLEFA_Login_Action',
			'register'           => 'CLEFA_Register_Action',
			'lost_password'      => 'CLEFA_Lost_Password_Action',
			'confirm_password'   => 'CLEFA_Confirm_Password_Action',
			'change_password'    => 'CLEFA_Change_Password_Action',
			'redirect'           => 'CLEFA_Redirect_Action',
			'update_user_meta'   => 'CLEFA_Update_User_Meta_Action',
			'update_post_meta'   => 'CLEFA_Update_Post_Meta_Action',
			'create_post'        => 'CLEFA_Create_Post_Action',
			'send_email'         => 'CLEFA_Send_Email_Action',
			'set_taxonomy'       => 'CLEFA_Taxonomy_Action',
			'update_acf'         => 'CLEFA_ACF_Action',
			'update_acf_repeater'=> 'CLEFA_ACF_Repeater_Action',
			'assign_role'        => 'CLEFA_Role_Action',
			'create_product'     => 'CLEFA_WC_Product_Action',
			'webhook'            => 'CLEFA_Webhook_Action',
		);

		if ( ! isset( $built_in[ $type ] ) ) { return null; }

		$file_map = array(
			'save_submission'    => 'Save_Submission_Action.php',
			'login'              => 'Login_Action.php',
			'register'           => 'Register_Action.php',
			'lost_password'      => 'Lost_Password_Action.php',
			'confirm_password'   => 'Confirm_Password_Action.php',
			'change_password'    => 'Change_Password_Action.php',
			'redirect'           => 'Redirect_Action.php',
			'update_user_meta'   => 'Update_User_Meta_Action.php',
			'update_post_meta'   => 'Update_Post_Meta_Action.php',
			'create_post'        => 'Create_Post_Action.php',
			'send_email'         => 'Send_Email_Action.php',
			'set_taxonomy'       => 'Taxonomy_Action.php',
			'update_acf'         => 'ACF_Action.php',
			'update_acf_repeater'=> 'ACF_Repeater_Action.php',
			'assign_role'        => 'Role_Action.php',
			'create_product'     => 'WC_Product_Action.php',
			'webhook'            => 'Webhook_Action.php',
		);

		$file = CLEFA_PLUGIN_PATH . 'includes/Actions/' . $file_map[ $type ];
		if ( file_exists( $file ) ) {
			require_once $file;
		}

		$class = $built_in[ $type ];
		return class_exists( $class ) ? new $class() : null;
	}
}
