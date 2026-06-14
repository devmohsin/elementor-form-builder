<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

$base_url = admin_url( 'admin.php?page=clefa-logs' );
?>
<div class="wrap clefa-admin-page">

	<h1 class="clefa-page-title"><?php esc_html_e( 'Audit Logs', 'codelinden-elementor-form-addon' ); ?></h1>

	<div class="clefa-page-intro">
		<p class="clefa-page-subtitle"><?php esc_html_e( 'Track form events, submissions, uploads, and admin actions.', 'codelinden-elementor-form-addon' ); ?></p>
	</div>

	<!-- Filters -->
	<div class="clefa-card clefa-logs-filter-card">
		<form method="get" class="clefa-logs-filter-form">
			<input type="hidden" name="page" value="clefa-logs">
			<div class="clefa-filter-row">
				<select name="form_id" class="clefa-select">
					<option value=""><?php esc_html_e( 'All Forms', 'codelinden-elementor-form-addon' ); ?></option>
					<?php foreach ( $forms as $f ) : ?>
					<option value="<?php echo esc_attr( $f['id'] ); ?>" <?php selected( $form_id, $f['id'] ); ?>>
						<?php echo esc_html( $f['form_name'] ); ?>
					</option>
					<?php endforeach; ?>
				</select>

				<select name="event_type" class="clefa-select">
					<option value=""><?php esc_html_e( 'All Event Types', 'codelinden-elementor-form-addon' ); ?></option>
					<?php foreach ( $event_types as $et ) : ?>
					<option value="<?php echo esc_attr( $et ); ?>" <?php selected( $event_type, $et ); ?>>
						<?php echo esc_html( str_replace( '_', ' ', $et ) ); ?>
					</option>
					<?php endforeach; ?>
				</select>

				<button type="submit" class="clefa-btn clefa-btn-primary clefa-btn-sm">
					<span class="dashicons dashicons-search"></span>
					<?php esc_html_e( 'Filter', 'codelinden-elementor-form-addon' ); ?>
				</button>

				<?php if ( $form_id || $event_type ) : ?>
				<a href="<?php echo esc_url( $base_url ); ?>" class="clefa-btn clefa-btn-ghost clefa-btn-sm">
					<?php esc_html_e( 'Reset', 'codelinden-elementor-form-addon' ); ?>
				</a>
				<?php endif; ?>

				<span class="clefa-logs-total">
					<?php
					/* translators: %d total log count */
					printf( esc_html__( '%d events', 'codelinden-elementor-form-addon' ), $total );
					?>
				</span>
			</div>
		</form>
	</div>

	<?php if ( empty( $logs ) ) : ?>
	<div class="clefa-empty-state">
		<span class="dashicons dashicons-list-view" style="font-size:3rem;height:auto;width:auto;color:var(--clefa-border)"></span>
		<p><?php esc_html_e( 'No audit log entries found.', 'codelinden-elementor-form-addon' ); ?></p>
	</div>
	<?php else : ?>

	<div class="clefa-card">
		<div class="clefa-table-wrap">
			<table class="clefa-table clefa-logs-table">
				<thead>
					<tr>
						<th>#</th>
						<th><?php esc_html_e( 'Date', 'codelinden-elementor-form-addon' ); ?></th>
						<th><?php esc_html_e( 'Event', 'codelinden-elementor-form-addon' ); ?></th>
						<th><?php esc_html_e( 'Form', 'codelinden-elementor-form-addon' ); ?></th>
						<th><?php esc_html_e( 'User', 'codelinden-elementor-form-addon' ); ?></th>
						<th><?php esc_html_e( 'IP Address', 'codelinden-elementor-form-addon' ); ?></th>
						<th><?php esc_html_e( 'Context', 'codelinden-elementor-form-addon' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $logs as $log ) :
						$user    = $log['user_id'] ? get_user_by( 'id', $log['user_id'] ) : null;
						$context = json_decode( $log['event_context_json'] ?? '{}', true ) ?: array();
						unset( $context['form_id'] );
					?>
					<tr>
						<td><?php echo esc_html( $log['id'] ); ?></td>
						<td><time datetime="<?php echo esc_attr( $log['created_at'] ); ?>"><?php echo esc_html( $log['created_at'] ); ?></time></td>
						<td>
							<span class="clefa-log-event-badge clefa-log-event-<?php echo esc_attr( str_replace( '_', '-', $log['event_type'] ) ); ?>">
								<?php echo esc_html( str_replace( '_', ' ', $log['event_type'] ) ); ?>
							</span>
						</td>
						<td>
							<?php if ( $log['form_id'] ) :
								$fname = $log['form_name'] ?? '';
							?>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=clefa-edit-form&form_id=' . absint( $log['form_id'] ) ) ); ?>">
								<?php echo $fname ? esc_html( $fname ) : esc_html( '#' . $log['form_id'] ); ?>
							</a>
							<?php else : ?>
							<span class="clefa-text-muted">—</span>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( $user ) : ?>
							<a href="<?php echo esc_url( get_edit_user_link( $user->ID ) ); ?>">
								<?php echo esc_html( $user->user_login ); ?>
							</a>
							<?php elseif ( $log['user_id'] ) : ?>
							<?php echo esc_html( '#' . $log['user_id'] ); ?>
							<?php else : ?>
							<span class="clefa-text-muted"><?php esc_html_e( 'Guest', 'codelinden-elementor-form-addon' ); ?></span>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( $log['ip_address'] ?? '—' ); ?></td>
						<td>
							<?php if ( ! empty( $context ) ) : ?>
							<details class="clefa-log-context">
								<summary><?php esc_html_e( 'View', 'codelinden-elementor-form-addon' ); ?></summary>
								<pre><?php echo esc_html( wp_json_encode( $context, JSON_PRETTY_PRINT ) ); ?></pre>
							</details>
							<?php else : ?>
							<span class="clefa-text-muted">—</span>
							<?php endif; ?>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>

	<?php if ( $total_pages > 1 ) :
		$prev_url = $page > 1 ? add_query_arg( array_filter( array( 'page' => 'clefa-logs', 'paged' => $page - 1, 'form_id' => $form_id ?: null, 'event_type' => $event_type ?: null ) ), admin_url( 'admin.php' ) ) : '';
		$next_url = $page < $total_pages ? add_query_arg( array_filter( array( 'page' => 'clefa-logs', 'paged' => $page + 1, 'form_id' => $form_id ?: null, 'event_type' => $event_type ?: null ) ), admin_url( 'admin.php' ) ) : '';
	?>
	<div class="clefa-pagination">
		<?php if ( $prev_url ) : ?>
		<a href="<?php echo esc_url( $prev_url ); ?>" class="clefa-btn clefa-btn-ghost clefa-btn-sm">
			&larr; <?php esc_html_e( 'Previous', 'codelinden-elementor-form-addon' ); ?>
		</a>
		<?php endif; ?>
		<span class="clefa-pagination-info">
			<?php
			/* translators: 1: current page, 2: total pages */
			printf( esc_html__( 'Page %1$d of %2$d', 'codelinden-elementor-form-addon' ), $page, $total_pages );
			?>
		</span>
		<?php if ( $next_url ) : ?>
		<a href="<?php echo esc_url( $next_url ); ?>" class="clefa-btn clefa-btn-ghost clefa-btn-sm">
			<?php esc_html_e( 'Next', 'codelinden-elementor-form-addon' ); ?> &rarr;
		</a>
		<?php endif; ?>
	</div>
	<?php endif; ?>

	<?php endif; // end if logs not empty ?>
</div>

<style>
.clefa-logs-filter-card .clefa-card-body,.clefa-logs-filter-form{padding:0}
.clefa-filter-row{display:flex;align-items:center;gap:10px;flex-wrap:wrap;padding:16px}
.clefa-logs-total{margin-left:auto;color:var(--clefa-text-muted);font-size:.85rem}
.clefa-logs-table{width:100%;border-collapse:collapse;font-size:.875rem}
.clefa-logs-table th{background:var(--clefa-bg);font-weight:600;text-align:left;padding:8px 12px;border-bottom:2px solid var(--clefa-border)}
.clefa-logs-table td{padding:8px 12px;border-bottom:1px solid var(--clefa-border);vertical-align:top}
.clefa-logs-table tr:hover td{background:var(--clefa-bg-hover, rgba(0,0,0,.02))}
.clefa-log-event-badge{display:inline-block;padding:2px 8px;border-radius:9999px;font-size:.78rem;font-weight:600;text-transform:capitalize;background:var(--clefa-bg);border:1px solid var(--clefa-border)}
.clefa-log-event-form-submitted{background:#eff6ff;border-color:#bfdbfe;color:#1d4ed8}
.clefa-log-event-file-uploaded{background:#f0fdf4;border-color:#bbf7d0;color:#15803d}
.clefa-log-event-live-check{background:#fffbeb;border-color:#fde68a;color:#92400e}
.clefa-log-event-test-run{background:#fdf4ff;border-color:#e9d5ff;color:#7e22ce}
.clefa-log-context summary{cursor:pointer;color:var(--clefa-primary);font-size:.8rem}
.clefa-log-context pre{margin:6px 0 0;font-size:.75rem;background:var(--clefa-bg);padding:8px;border-radius:4px;border:1px solid var(--clefa-border);white-space:pre-wrap;word-break:break-all;max-height:200px;overflow-y:auto}
.clefa-text-muted{color:var(--clefa-text-muted)}
</style>
