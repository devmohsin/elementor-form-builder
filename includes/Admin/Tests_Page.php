<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CLEFA_Tests_Page {

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'codelinden-elementor-form-addon' ) );
		}

		$forms      = CLEFA_Tables::get_forms( array( 'per_page' => 200 ) );
		$form_id    = absint( $_GET['form_id'] ?? 0 );
		$form       = $form_id ? CLEFA_Tables::get_form( $form_id ) : null;
		$form_fields= array();

		if ( $form ) {
			$config     = is_array( $form['config'] ?? null ) ? $form['config'] : array();
			foreach ( $config['steps'] ?? array() as $step ) {
				foreach ( $step['fields'] ?? array() as $field ) {
					if ( ! empty( $field['field_id'] ) ) {
						$form_fields[] = array(
							'field_id'   => $field['field_id'],
							'label'      => $field['label'] ?? $field['field_id'],
							'field_type' => $field['field_type'] ?? 'text',
							'required'   => ! empty( $field['required'] ),
						);
					}
				}
			}
		}

		$template = CLEFA_TEMPLATE_PATH . 'admin/tests.php';
		if ( file_exists( $template ) ) {
			include $template;
		}
	}
}
