<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CLEFA_Installer {

	const DB_OPTION_KEY = 'clefa_db_version';

	public static function run_on_activation() {
		require_once CLEFA_PLUGIN_PATH . 'includes/Database/Tables.php';
		CLEFA_Tables::create();
		self::set_default_options();
		self::schedule_cleanup();
		flush_rewrite_rules();
	}

	public static function maybe_run() {
		$stored = get_option( self::DB_OPTION_KEY, '' );
		if ( version_compare( $stored, CLEFA_DB_VERSION, '<' ) ) {
			require_once CLEFA_PLUGIN_PATH . 'includes/Database/Tables.php';
			CLEFA_Tables::create();
			self::set_default_options();
			self::schedule_cleanup();
			update_option( self::DB_OPTION_KEY, CLEFA_DB_VERSION );
		}
	}

	public static function run_on_deactivation() {
		wp_clear_scheduled_hook( 'clefa_cleanup_temp_uploads' );
		flush_rewrite_rules();
	}

	private static function schedule_cleanup() {
		if ( ! wp_next_scheduled( 'clefa_cleanup_temp_uploads' ) ) {
			wp_schedule_event( time(), 'hourly', 'clefa_cleanup_temp_uploads' );
		}
	}

	private static function set_default_options() {
		$defaults = array(
			'default_redirect_url'          => '',
			'default_success_message'       => __( 'Your form has been submitted successfully.', 'codelinden-elementor-form-addon' ),
			'default_error_message'         => __( 'There was an error processing your form. Please try again.', 'codelinden-elementor-form-addon' ),
			'upload_max_size'               => 5,
			'upload_allowed_types'          => 'jpg,jpeg,png,gif,pdf,doc,docx',
			'temp_upload_expiry'            => 24,
			'enable_cleanup_schedule'       => true,
			'enable_debug_console'          => false,
			'enable_submission_storage'     => true,
			'enable_antispam'               => true,
			'enable_nonce_refresh'          => true,
			'default_style_mode'            => 'inherited',
		);

		foreach ( $defaults as $key => $value ) {
			$option_key = 'clefa_' . $key;
			if ( false === get_option( $option_key ) ) {
				add_option( $option_key, $value );
			}
		}

		update_option( self::DB_OPTION_KEY, CLEFA_DB_VERSION );
	}
}
