<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CLEFA_Edit_Form_Page {

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'codelinden-elementor-form-addon' ) );
		}
		$template = CLEFA_TEMPLATE_PATH . 'admin/edit-form.php';
		if ( file_exists( $template ) ) {
			$form_id      = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0;
			$form_data    = null;
			$form_missing = false;

			if ( $form_id > 0 ) {
				$form_data = CLEFA_Tables::get_form( $form_id );
				if ( ! $form_data ) {
					$form_missing = true;
					$form_id      = 0;
				}
			}

			include $template;
		}
	}
}
