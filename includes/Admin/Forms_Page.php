<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CLEFA_Forms_Page {

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'codelinden-elementor-form-addon' ) );
		}
		$template = CLEFA_TEMPLATE_PATH . 'admin/forms-list.php';
		if ( file_exists( $template ) ) {
			$forms      = CLEFA_Tables::get_forms();
			$form_count = CLEFA_Tables::count_forms();
			include $template;
		}
	}

	public function ajax_delete_form() {
		check_ajax_referer( 'clefa_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'codelinden-elementor-form-addon' ) ) );
		}
		$form_id = absint( $_POST['form_id'] ?? 0 );
		if ( ! $form_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid form ID.', 'codelinden-elementor-form-addon' ) ) );
		}
		global $wpdb;
		$result = $wpdb->delete( $wpdb->prefix . 'clefa_forms', array( 'id' => $form_id ), array( '%d' ) );
		if ( false === $result ) {
			wp_send_json_error( array( 'message' => __( 'Delete failed.', 'codelinden-elementor-form-addon' ) ) );
		}
		wp_send_json_success( array( 'message' => __( 'Form deleted.', 'codelinden-elementor-form-addon' ) ) );
	}

	public function ajax_duplicate_form() {
		check_ajax_referer( 'clefa_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'codelinden-elementor-form-addon' ) ) );
		}
		$form_id = absint( $_POST['form_id'] ?? 0 );
		$source  = CLEFA_Tables::get_form( $form_id );
		if ( ! $source ) {
			wp_send_json_error( array( 'message' => __( 'Form not found.', 'codelinden-elementor-form-addon' ) ) );
		}
		global $wpdb;
		$new_name = sprintf( '%s %s', $source['form_name'], __( '(Copy)', 'codelinden-elementor-form-addon' ) );
		$wpdb->insert(
			$wpdb->prefix . 'clefa_forms',
			array(
				'form_uuid'              => wp_generate_uuid4(),
				'form_name'              => $new_name,
				'form_type'              => $source['form_type'],
				'status'                 => 'draft',
				'description'            => $source['description'],
				'config_json'            => $source['config_json'],
				'normalized_config_json' => $source['normalized_config_json'],
				'feature_map_json'       => $source['feature_map_json'],
				'created_by'             => get_current_user_id(),
				'updated_by'             => get_current_user_id(),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d' )
		);
		$new_id = $wpdb->insert_id;
		if ( ! $new_id ) {
			wp_send_json_error( array( 'message' => __( 'Duplicate failed.', 'codelinden-elementor-form-addon' ) ) );
		}
		wp_send_json_success( array(
			'message'  => __( 'Form duplicated.', 'codelinden-elementor-form-addon' ),
			'new_id'   => $new_id,
			'edit_url' => admin_url( 'admin.php?page=clefa-edit-form&form_id=' . $new_id ),
		) );
	}
}
