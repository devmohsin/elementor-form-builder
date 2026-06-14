<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CLEFA_Submissions_Page {

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'codelinden-elementor-form-addon' ) );
		}

		$action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : '';

		if ( 'view' === $action ) {
			$this->render_view();
			return;
		}

		if ( 'export_csv' === $action ) {
			$this->export_csv();
			return;
		}

		$template = CLEFA_TEMPLATE_PATH . 'admin/submissions.php';
		if ( file_exists( $template ) ) {
			$form_id     = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0;
			$page_num    = isset( $_GET['paged'] )   ? absint( $_GET['paged'] )   : 1;
			$submissions = CLEFA_Tables::get_submissions( array( 'form_id' => $form_id, 'page' => $page_num ) );
			$total       = CLEFA_Tables::count_submissions( $form_id );
			$forms       = CLEFA_Tables::get_forms( array( 'per_page' => 100 ) );
			include $template;
		}
	}

	private function render_view() {
		$submission_id = isset( $_GET['submission_id'] ) ? absint( $_GET['submission_id'] ) : 0;
		if ( ! $submission_id ) {
			wp_die( esc_html__( 'Invalid submission ID.', 'codelinden-elementor-form-addon' ) );
		}

		global $wpdb;
		$submission = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}clefa_submissions WHERE id = %d LIMIT 1", $submission_id ),
			ARRAY_A
		);

		if ( ! $submission ) {
			wp_die( esc_html__( 'Submission not found.', 'codelinden-elementor-form-addon' ) );
		}

		$form = CLEFA_Tables::get_form( $submission['form_id'] );
		$template = CLEFA_TEMPLATE_PATH . 'admin/submission-view.php';
		if ( file_exists( $template ) ) {
			include $template;
		}
	}

	private function export_csv() {
		if ( ! check_admin_referer( 'clefa_export_csv' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'codelinden-elementor-form-addon' ) );
		}

		$form_id = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0;
		$form    = $form_id ? CLEFA_Tables::get_form( $form_id ) : null;

		$submissions = CLEFA_Tables::get_submissions( array(
			'form_id'  => $form_id,
			'per_page' => 9999,
			'page'     => 1,
		) );

		// Build headers from form config
		$field_headers = array();
		if ( $form ) {
			$config = is_array( $form['config'] ?? null ) ? $form['config'] : array();
			foreach ( ( $config['steps'] ?? array() ) as $step ) {
				foreach ( ( $step['fields'] ?? array() ) as $field ) {
					$ftype = $field['field_type'] ?? '';
					if ( in_array( $ftype, array( 'html', 'notice', 'grid_break', 'heading' ), true ) ) continue;
					$field_headers[ $field['field_id'] ?? '' ] = $field['label'] ?? $field['field_id'] ?? '';
				}
			}
		}

		$filename = 'clefa-submissions-' . ( $form_id ?: 'all' ) . '-' . current_time( 'Y-m-d' ) . '.csv';

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
		header( 'Pragma: no-cache' );

		$out = fopen( 'php://output', 'w' );

		// UTF-8 BOM for Excel compatibility
		fputs( $out, "\xEF\xBB\xBF" );

		// Header row
		$header_row = array( 'ID', 'Form', 'Date', 'Status', 'User ID', 'IP' );
		foreach ( $field_headers as $label ) {
			$header_row[] = $label;
		}
		fputcsv( $out, $header_row );

		foreach ( $submissions as $sub ) {
			$sub_data = is_array( $sub['submitted_data_json'] ?? null )
				? $sub['submitted_data_json']
				: ( json_decode( $sub['submitted_data_json'] ?? '{}', true ) ?: array() );
			$form_name= $form ? $form['form_name'] : ( 'Form #' . $sub['form_id'] );

			$row = array(
				$sub['id'],
				$form_name,
				$sub['created_at'],
				$sub['status'],
				$sub['user_id'],
				$sub['source_url'] ?? '',
			);

			foreach ( array_keys( $field_headers ) as $fid ) {
				$val = $sub_data[ $fid ] ?? '';
				if ( is_array( $val ) ) $val = implode( '; ', $val );
				$row[] = (string) $val;
			}

			fputcsv( $out, $row );
		}

		fclose( $out );
		exit;
	}
}
