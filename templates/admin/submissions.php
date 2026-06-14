<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
$per_page  = 20;
$total_pages = $total > 0 ? ceil( $total / $per_page ) : 1;
?>
<div class="wrap clefa-admin-page clefa-submissions-page">

	<h1 class="clefa-page-title">
		<span class="dashicons dashicons-list-view"></span>
		<?php esc_html_e( 'Submissions', 'codelinden-elementor-form-addon' ); ?>
	</h1>

	<div class="clefa-page-toolbar clefa-page-toolbar-multiline">
		<div class="clefa-page-header-actions">
			<?php if ( $form_id ) :
				$export_url = add_query_arg( array(
					'page'     => 'clefa-submissions',
					'action'   => 'export_csv',
					'form_id'  => $form_id,
					'_wpnonce' => wp_create_nonce( 'clefa_export_csv' ),
				), admin_url( 'admin.php' ) );
			?>
			<a href="<?php echo esc_url( $export_url ); ?>" class="clefa-btn clefa-btn-secondary clefa-btn-sm">
				<span class="dashicons dashicons-download"></span>
				<?php esc_html_e( 'Export CSV', 'codelinden-elementor-form-addon' ); ?>
			</a>
			<?php endif; ?>
			<form method="get" class="clefa-filter-form">
				<input type="hidden" name="page" value="clefa-submissions" />
				<select name="form_id" onchange="this.form.submit()">
					<option value=""><?php esc_html_e( '— All Forms —', 'codelinden-elementor-form-addon' ); ?></option>
					<?php foreach ( $forms as $f ) : ?>
					<option value="<?php echo esc_attr( $f['id'] ); ?>" <?php selected( $form_id, $f['id'] ); ?>>
						<?php echo esc_html( $f['form_name'] ); ?>
					</option>
					<?php endforeach; ?>
				</select>
			</form>
		</div>
	</div>

	<?php if ( empty( $submissions ) ) : ?>
	<div class="clefa-empty-state">
		<span class="dashicons dashicons-list-view clefa-empty-icon"></span>
		<h2><?php esc_html_e( 'No submissions yet', 'codelinden-elementor-form-addon' ); ?></h2>
		<p><?php esc_html_e( 'Submissions will appear here once forms are submitted.', 'codelinden-elementor-form-addon' ); ?></p>
	</div>
	<?php else : ?>
	<table class="wp-list-table widefat fixed striped clefa-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'ID', 'codelinden-elementor-form-addon' ); ?></th>
				<th><?php esc_html_e( 'Form', 'codelinden-elementor-form-addon' ); ?></th>
				<th><?php esc_html_e( 'User', 'codelinden-elementor-form-addon' ); ?></th>
				<th><?php esc_html_e( 'Status', 'codelinden-elementor-form-addon' ); ?></th>
				<th><?php esc_html_e( 'Source URL', 'codelinden-elementor-form-addon' ); ?></th>
				<th><?php esc_html_e( 'Date', 'codelinden-elementor-form-addon' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'codelinden-elementor-form-addon' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $submissions as $sub ) :
				$user = $sub['user_id'] ? get_userdata( absint( $sub['user_id'] ) ) : null;
			?>
			<tr>
				<td><?php echo esc_html( $sub['id'] ); ?></td>
				<td><?php echo esc_html( $sub['form_name'] ?? '—' ); ?></td>
				<td><?php echo $user ? esc_html( $user->user_login ) : esc_html__( 'Guest', 'codelinden-elementor-form-addon' ); ?></td>
				<td><span class="clefa-badge clefa-badge-<?php echo esc_attr( $sub['status'] ); ?>"><?php echo esc_html( ucfirst( $sub['status'] ) ); ?></span></td>
				<td>
					<?php if ( ! empty( $sub['source_url'] ) ) : ?>
						<a href="<?php echo esc_url( $sub['source_url'] ); ?>" target="_blank" rel="noopener" title="<?php echo esc_attr( $sub['source_url'] ); ?>">
							<?php echo esc_html( wp_parse_url( $sub['source_url'], PHP_URL_PATH ) ?: $sub['source_url'] ); ?>
						</a>
					<?php else : ?>—<?php endif; ?>
				</td>
				<td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $sub['created_at'] ) ) ); ?></td>
				<td>
					<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'clefa-submissions', 'action' => 'view', 'submission_id' => $sub['id'] ), admin_url( 'admin.php' ) ) ); ?>"
					   class="clefa-btn clefa-btn-xs clefa-btn-ghost">
						<?php esc_html_e( 'View', 'codelinden-elementor-form-addon' ); ?>
					</a>
				</td>
			</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<?php if ( $total_pages > 1 ) : ?>
	<div class="clefa-pagination">
		<?php for ( $p = 1; $p <= $total_pages; $p++ ) : ?>
			<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'clefa-submissions', 'paged' => $p, 'form_id' => $form_id ), admin_url( 'admin.php' ) ) ); ?>"
			   class="clefa-btn clefa-btn-sm <?php echo $p === $page_num ? 'clefa-btn-primary' : 'clefa-btn-ghost'; ?>">
				<?php echo esc_html( $p ); ?>
			</a>
		<?php endfor; ?>
	</div>
	<?php endif; ?>

	<?php endif; ?>
</div>
