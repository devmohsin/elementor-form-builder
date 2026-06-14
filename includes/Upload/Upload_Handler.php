<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CLEFA_Upload_Handler {

	const TEMP_DIR_NAME  = 'clefa-temp';
	const PERM_DIR_NAME  = 'clefa-uploads';
	const MAX_MB_DEFAULT = 10;

	public function handle_upload( WP_REST_Request $request ) {
		if ( empty( $_FILES['file'] ) ) {
			return new WP_Error( 'no_file', __( 'No file received.', 'codelinden-elementor-form-addon' ), array( 'status' => 400 ) );
		}

		$file        = $_FILES['file'];
		$form_id     = absint( $request->get_param( 'form_id' ) );
		$field_id    = sanitize_key( $request->get_param( 'field_id' ) ?? '' );
		$instance_id = sanitize_text_field( $request->get_param( 'instance_id' ) ?? '' );

		$allowed_types = array();
		$max_size_mb   = absint( CLEFA_Settings_Page::get( 'upload_max_size', self::MAX_MB_DEFAULT ) );

		if ( $form_id && $field_id ) {
			$form = CLEFA_Tables::get_form( $form_id );
			if ( $form ) {
				$config = is_array( $form['config'] ?? null ) ? $form['config'] : array();
				$field  = $this->find_field( $config, $field_id );
				if ( $field ) {
					if ( ! empty( $field['allowed_types'] ) ) {
						$allowed_types = array_map( 'trim', explode( ',', $field['allowed_types'] ) );
					}
					if ( ! empty( $field['max_file_size'] ) ) {
						$max_size_mb = absint( $field['max_file_size'] );
					}
				}
			}
		}

		if ( empty( $allowed_types ) ) {
			$global        = CLEFA_Settings_Page::get( 'upload_allowed_types', 'jpg,jpeg,png,gif,pdf,doc,docx' );
			$allowed_types = array_map( 'trim', explode( ',', $global ) );
		}

		$allowed_types = apply_filters( 'clefa_upload_allowed_mimes', $allowed_types, $field, $form_id );

		$max_bytes = $max_size_mb * 1024 * 1024;
		if ( $file['size'] > $max_bytes ) {
			/* translators: %d is the maximum size in MB */
			return new WP_Error( 'file_too_large', sprintf( __( 'File exceeds the maximum size of %dMB.', 'codelinden-elementor-form-addon' ), $max_size_mb ), array( 'status' => 400 ) );
		}

		if ( $file['error'] !== UPLOAD_ERR_OK ) {
			return new WP_Error( 'upload_error', __( 'File upload error.', 'codelinden-elementor-form-addon' ), array( 'status' => 400 ) );
		}

		$ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, $allowed_types, true ) ) {
			/* translators: %s is the file extension */
			return new WP_Error( 'invalid_type', sprintf( __( 'File type .%s is not allowed.', 'codelinden-elementor-form-addon' ), $ext ), array( 'status' => 400 ) );
		}

		$finfo     = new finfo( FILEINFO_MIME_TYPE );
		$mime_type = $finfo->file( $file['tmp_name'] );

		$upload_dir = wp_upload_dir();
		$temp_dir   = trailingslashit( $upload_dir['basedir'] ) . self::TEMP_DIR_NAME . '/' . $instance_id;

		if ( ! wp_mkdir_p( $temp_dir ) ) {
			return new WP_Error( 'mkdir_failed', __( 'Could not create upload directory.', 'codelinden-elementor-form-addon' ), array( 'status' => 500 ) );
		}

		self::protect_directory( $temp_dir );

		$temp_token = wp_generate_uuid4();
		$safe_name  = $temp_token . '.' . $ext;
		$dest_path  = $temp_dir . '/' . $safe_name;
		$temp_url   = trailingslashit( $upload_dir['baseurl'] ) . self::TEMP_DIR_NAME . '/' . $instance_id . '/' . $safe_name;

		if ( ! move_uploaded_file( $file['tmp_name'], $dest_path ) ) {
			return new WP_Error( 'move_failed', __( 'Could not save uploaded file.', 'codelinden-elementor-form-addon' ), array( 'status' => 500 ) );
		}

		global $wpdb;
		$expiry_hours = absint( CLEFA_Settings_Page::get( 'temp_upload_expiry', 24 ) );
		$expires_at   = gmdate( 'Y-m-d H:i:s', time() + $expiry_hours * HOUR_IN_SECONDS );

		$wpdb->insert(
			$wpdb->prefix . 'clefa_uploads',
			array(
				'form_id'          => $form_id,
				'form_instance_id' => $instance_id,
				'submission_id'    => 0,
				'field_id'         => $field_id,
				'user_id'          => get_current_user_id(),
				'temp_token'       => $temp_token,
				'original_filename'=> sanitize_file_name( $file['name'] ),
				'file_path'        => $dest_path,
				'file_url'         => $temp_url,
				'mime_type'        => $mime_type,
				'file_size'        => $file['size'],
				'upload_status'    => 'temp',
				'is_committed'     => 0,
				'expires_at'       => $expires_at,
				'created_at'       => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s' )
		);

		CLEFA_Audit_Log::write( 'file_uploaded', array(
			'form_id'   => $form_id,
			'field_id'  => $field_id,
			'temp_token'=> $temp_token,
			'file_name' => sanitize_file_name( $file['name'] ),
			'file_size' => $file['size'],
		) );

		do_action( 'clefa_upload_completed', $temp_token, $field_id, $form_id, $file );

		return rest_ensure_response( array(
			'file_name'  => sanitize_file_name( $file['name'] ),
			'file_size'  => $file['size'],
			'file_url'   => $temp_url,
			'mime_type'  => $mime_type,
			'ext'        => $ext,
		) );
	}

	public static function commit_uploads( $form_id, $submission_id, array $field_temp_tokens ) {
		global $wpdb;
		$upload_dir = wp_upload_dir();
		$perm_base  = trailingslashit( $upload_dir['basedir'] ) . self::PERM_DIR_NAME . '/' . $form_id . '/' . $submission_id;
		wp_mkdir_p( $perm_base );
		self::protect_directory( $perm_base );

		foreach ( $field_temp_tokens as $field_id => $temp_token ) {
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}clefa_uploads WHERE temp_token = %s AND upload_status = 'temp' LIMIT 1",
					$temp_token
				),
				ARRAY_A
			);
			if ( ! $row ) { continue; }

			$ext      = pathinfo( $row['file_path'] ?? '', PATHINFO_EXTENSION );
			$new_name = sanitize_file_name( $row['original_filename'] );
			$dest     = $perm_base . '/' . $new_name;
			if ( file_exists( $dest ) ) {
				$dest = $perm_base . '/' . pathinfo( $new_name, PATHINFO_FILENAME ) . '-' . time() . '.' . $ext;
			}

			if ( $row['file_path'] && rename( $row['file_path'], $dest ) ) {
				$perm_url = trailingslashit( $upload_dir['baseurl'] ) . self::PERM_DIR_NAME . '/' . $form_id . '/' . $submission_id . '/' . basename( $dest );
				$wpdb->update(
					$wpdb->prefix . 'clefa_uploads',
					array(
						'submission_id' => $submission_id,
						'file_path'     => $dest,
						'file_url'      => $perm_url,
						'upload_status' => 'permanent',
						'is_committed'  => 1,
						'committed_at'  => current_time( 'mysql', true ),
						'expires_at'    => null,
					),
					array( 'temp_token' => $temp_token ),
					array( '%d', '%s', '%s', '%s', '%d', '%s', '%s' ),
					array( '%s' )
				);
			}
		}
	}

	public static function cleanup_expired_temp_files() {
		global $wpdb;
		$expired = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}clefa_uploads WHERE upload_status = 'temp' AND expires_at < %s",
				current_time( 'mysql', true )
			),
			ARRAY_A
		);
		foreach ( $expired as $row ) {
			if ( ! empty( $row['file_path'] ) && file_exists( $row['file_path'] ) ) {
				wp_delete_file( $row['file_path'] );
			}
			$wpdb->delete( $wpdb->prefix . 'clefa_uploads', array( 'id' => $row['id'] ), array( '%d' ) );
		}
	}

	private function find_field( array $config, $field_id ) {
		foreach ( ( $config['steps'] ?? array() ) as $step ) {
			foreach ( ( $step['fields'] ?? array() ) as $field ) {
				if ( ( $field['field_id'] ?? '' ) === $field_id ) {
					return $field;
				}
			}
		}
		return null;
	}

	private static function protect_directory( $dir ) {
		$htaccess = $dir . '/.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			file_put_contents( $htaccess, "Options -Indexes\nDeny from all\n" );
		}
		$index = $dir . '/index.php';
		if ( ! file_exists( $index ) ) {
			file_put_contents( $index, '<?php // Silence is golden.' );
		}
	}
}
