<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CLEFA_Settings_Page {

	public function register_settings() {
		$fields = array(
			'clefa_default_redirect_url',
			'clefa_default_success_message',
			'clefa_default_error_message',
			'clefa_upload_max_size',
			'clefa_upload_allowed_types',
			'clefa_temp_upload_expiry',
			'clefa_enable_cleanup_schedule',
			'clefa_enable_debug_console',
			'clefa_enable_submission_storage',
			'clefa_enable_antispam',
			'clefa_enable_nonce_refresh',
			'clefa_enable_debug_events',
			'clefa_enable_rate_limiting',
			'clefa_rate_limit_max',
			'clefa_rate_limit_window',
			'clefa_uninstall_remove_data',
			'clefa_default_style_mode',
		);

		foreach ( $fields as $field ) {
			register_setting( 'clefa_settings', $field, array( $this, 'sanitize_' . $field ) );
		}
	}

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'codelinden-elementor-form-addon' ) );
		}

		if ( isset( $_POST['clefa_settings_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['clefa_settings_nonce'] ) ), 'clefa_save_settings' ) ) {
			$this->save_settings();
		}

		$template = CLEFA_TEMPLATE_PATH . 'admin/settings.php';
		if ( file_exists( $template ) ) {
			include $template;
		}
	}

	private function save_settings() {
		$text_fields = array(
			'clefa_default_redirect_url',
			'clefa_default_success_message',
			'clefa_default_error_message',
			'clefa_upload_allowed_types',
			'clefa_default_style_mode',
		);
		foreach ( $text_fields as $field ) {
			if ( isset( $_POST[ $field ] ) ) {
				update_option( $field, sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) );
			}
		}

		$int_fields = array( 'clefa_upload_max_size', 'clefa_temp_upload_expiry', 'clefa_rate_limit_max', 'clefa_rate_limit_window' );
		foreach ( $int_fields as $field ) {
			if ( isset( $_POST[ $field ] ) ) {
				update_option( $field, absint( $_POST[ $field ] ) );
			}
		}

		$bool_fields = array(
			'clefa_enable_cleanup_schedule',
			'clefa_enable_debug_console',
			'clefa_enable_submission_storage',
			'clefa_enable_antispam',
			'clefa_enable_nonce_refresh',
			'clefa_enable_debug_events',
			'clefa_enable_rate_limiting',
			'clefa_uninstall_remove_data',
		);
		foreach ( $bool_fields as $field ) {
			update_option( $field, isset( $_POST[ $field ] ) ? true : false );
		}

		add_settings_error( 'clefa_settings', 'settings_saved', __( 'Settings saved.', 'codelinden-elementor-form-addon' ), 'updated' );
	}

	public static function get( $key, $default = '' ) {
		return get_option( 'clefa_' . $key, $default );
	}
}
