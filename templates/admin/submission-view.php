<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Access denied.', 'codelinden-elementor-form-addon' ) ); }

// $submission and $form are set by Submissions_Page::render_view()
$sub_data  = array();
$sub_raw   = $submission['submitted_data_json'] ?? $submission['sanitized_data_json'] ?? '{}';
if ( is_string( $sub_raw ) ) {
	$sub_data = json_decode( $sub_raw, true ) ?: array();
} elseif ( is_array( $sub_raw ) ) {
	$sub_data = $sub_raw;
}

$meta = array(
	'source_url' => $submission['source_url'] ?? '',
	'user_ip'    => '',
	'user_agent' => '',
);

$config    = $form ? ( is_array( $form['config'] ?? null ) ? $form['config'] : array() ) : array();
$form_name = $form ? $form['form_name'] : ( 'Form #' . $submission['form_id'] );

// Build field label map
$field_labels = array();
foreach ( ( $config['steps'] ?? array() ) as $step ) {
	foreach ( ( $step['fields'] ?? array() ) as $field ) {
		$fid  = $field['field_id'] ?? '';
		$type = $field['field_type'] ?? '';
		if ( $fid && ! in_array( $type, array( 'html', 'notice', 'grid_break', 'heading' ), true ) ) {
			$field_labels[ $fid ] = array(
				'label' => $field['label']      ?? $fid,
				'type'  => $type,
			);
		}
	}
}

$back_url = admin_url( 'admin.php?page=clefa-submissions&form_id=' . absint( $submission['form_id'] ) );
$export_one_url = add_query_arg( array(
	'page'          => 'clefa-submissions',
	'action'        => 'export_csv',
	'form_id'       => absint( $submission['form_id'] ),
	'_wpnonce'      => wp_create_nonce( 'clefa_export_csv' ),
), admin_url( 'admin.php' ) );
?>
<div class="wrap clefa-wrap">
	<div class="clefa-page-header">
		<div class="clefa-page-header-left">
			<a href="<?php echo esc_url( $back_url ); ?>" class="clefa-btn clefa-btn-ghost clefa-btn-sm">
				&larr; <?php esc_html_e( 'Back to Submissions', 'codelinden-elementor-form-addon' ); ?>
			</a>
			<h1><?php esc_html_e( 'Submission Detail', 'codelinden-elementor-form-addon' ); ?> #<?php echo esc_html( $submission['id'] ); ?></h1>
		</div>
		<div class="clefa-page-header-actions">
			<a href="<?php echo esc_url( $export_one_url ); ?>" class="clefa-btn clefa-btn-secondary clefa-btn-sm">
				<?php esc_html_e( 'Export CSV', 'codelinden-elementor-form-addon' ); ?>
			</a>
		</div>
	</div>

	<div class="clefa-submission-view">

		<div class="clefa-card">
			<div class="clefa-card-header"><?php esc_html_e( 'Meta', 'codelinden-elementor-form-addon' ); ?></div>
			<table class="clefa-table clefa-table-meta">
				<tbody>
					<tr><th><?php esc_html_e( 'Form', 'codelinden-elementor-form-addon' ); ?></th><td><?php echo esc_html( $form_name ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Date', 'codelinden-elementor-form-addon' ); ?></th><td><?php echo esc_html( $submission['created_at'] ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Status', 'codelinden-elementor-form-addon' ); ?></th><td><span class="clefa-status-badge clefa-status-<?php echo esc_attr( $submission['status'] ); ?>"><?php echo esc_html( ucfirst( $submission['status'] ) ); ?></span></td></tr>
					<?php if ( $submission['user_id'] ) : $user = get_user_by( 'id', $submission['user_id'] ); ?>
					<tr><th><?php esc_html_e( 'User', 'codelinden-elementor-form-addon' ); ?></th>
						<td><?php echo $user ? esc_html( $user->display_name . ' (' . $user->user_email . ')' ) : esc_html( '#' . $submission['user_id'] ); ?></td>
					</tr>
					<?php endif; ?>
					<?php if ( ! empty( $meta['source_url'] ) ) : ?>
					<tr><th><?php esc_html_e( 'Source URL', 'codelinden-elementor-form-addon' ); ?></th>
						<td><a href="<?php echo esc_url( $meta['source_url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $meta['source_url'] ); ?></a></td>
					</tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>

		<div class="clefa-card">
			<div class="clefa-card-header"><?php esc_html_e( 'Submission Data', 'codelinden-elementor-form-addon' ); ?></div>
			<table class="clefa-table clefa-table-data">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Field', 'codelinden-elementor-form-addon' ); ?></th>
						<th><?php esc_html_e( 'Value', 'codelinden-elementor-form-addon' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( ! empty( $field_labels ) ) : ?>
						<?php foreach ( $field_labels as $fid => $info ) : ?>
							<?php
							$val = $sub_data[ $fid ] ?? null;
							if ( $val === null ) continue;
							$display = is_array( $val ) ? implode( ', ', array_map( 'esc_html', $val ) ) : esc_html( (string) $val );
							?>
							<tr>
								<th scope="row"><?php echo esc_html( $info['label'] ); ?></th>
								<td>
									<?php if ( in_array( $info['type'], array( 'file', 'multi_file' ), true ) ) : ?>
										<?php
										$temp_ids = is_array( $val ) ? $val : array( $val );
										global $wpdb;
										foreach ( $temp_ids as $tid ) :
											if ( ! $tid ) continue;
											$upload = $wpdb->get_row(
												$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}clefa_uploads WHERE temp_id = %s LIMIT 1", $tid ),
												ARRAY_A
											);
											if ( $upload ) :
										?>
										<div class="clefa-uploaded-file">
											<?php if ( false !== strpos( $upload['file_type'], 'image/' ) ) : ?>
											<img src="<?php echo esc_url( $upload['file_url'] ); ?>" alt="<?php echo esc_attr( $upload['file_name'] ); ?>" style="max-width:120px;max-height:80px;vertical-align:middle;">
											<?php endif; ?>
											<a href="<?php echo esc_url( $upload['file_url'] ); ?>" target="_blank" rel="noopener noreferrer">
												<?php echo esc_html( $upload['file_name'] ); ?>
											</a>
											<span class="clefa-muted">(<?php echo esc_html( size_format( $upload['file_size'] ) ); ?>)</span>
										</div>
										<?php endif; endforeach; ?>
									<?php else : ?>
										<?php echo wp_kses_post( nl2br( $display ) ); ?>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php else : ?>
						<?php foreach ( $sub_data as $key => $val ) : ?>
							<?php $display = is_array( $val ) ? implode( ', ', array_map( 'esc_html', $val ) ) : esc_html( (string) $val ); ?>
							<tr>
								<th scope="row"><?php echo esc_html( $key ); ?></th>
								<td><?php echo wp_kses_post( nl2br( $display ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>

					<?php if ( empty( $sub_data ) ) : ?>
					<tr><td colspan="2" class="clefa-empty"><?php esc_html_e( 'No data recorded.', 'codelinden-elementor-form-addon' ); ?></td></tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>

		<?php
		// Show uploads attachments
		global $wpdb;
		$uploads = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}clefa_uploads WHERE submission_id = %d ORDER BY id ASC", $submission['id'] ),
			ARRAY_A
		);
		if ( ! empty( $uploads ) ) :
		?>
		<div class="clefa-card">
			<div class="clefa-card-header"><?php esc_html_e( 'Uploaded Files', 'codelinden-elementor-form-addon' ); ?></div>
			<div class="clefa-uploads-grid">
				<?php foreach ( $uploads as $up ) : ?>
				<div class="clefa-upload-item">
					<?php if ( false !== strpos( $up['file_type'], 'image/' ) ) : ?>
					<a href="<?php echo esc_url( $up['file_url'] ); ?>" target="_blank" rel="noopener noreferrer">
						<img src="<?php echo esc_url( $up['file_url'] ); ?>" alt="<?php echo esc_attr( $up['file_name'] ); ?>" />
					</a>
					<?php endif; ?>
					<div class="clefa-upload-meta">
						<a href="<?php echo esc_url( $up['file_url'] ); ?>" target="_blank" rel="noopener noreferrer" class="clefa-upload-name">
							<?php echo esc_html( $up['file_name'] ); ?>
						</a>
						<span class="clefa-muted"><?php echo esc_html( size_format( $up['file_size'] ) ); ?></span>
					</div>
				</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php endif; ?>

	</div>
</div>
