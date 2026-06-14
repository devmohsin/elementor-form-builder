<?php
if ( ! defined( 'ABSPATH' ) || empty( $parsed ) ) {
	return;
}
$summary = $parsed['summary'] ?? array();
$rows    = $parsed['rows'] ?? array();
$issues  = $parsed['issues'] ?? array();
$perfect = ! empty( $summary['perfect'] );
$label   = $suite_label ?? __( 'Test Results (strict)', 'codelinden-elementor-form-addon' );
?>
<div class="clefa-card clefa-suite-results" style="margin-top:20px;grid-column:1/-1">
	<div class="clefa-card-header">
		<?php echo esc_html( $label ); ?>
		<span class="clefa-badge <?php echo $perfect ? 'clefa-badge-success' : 'clefa-badge-error'; ?>">
			<?php
			echo esc_html(
				$perfect
					? __( '100% PERFECT PASS', 'codelinden-elementor-form-addon' )
					: __( 'FULL FAIL — issues found', 'codelinden-elementor-form-addon' )
			);
			?>
		</span>
		<span class="clefa-dev-hint">
			<?php
			printf(
				esc_html__( '%1$d pass · %2$d fail · %3$d total', 'codelinden-elementor-form-addon' ),
				(int) ( $summary['passed'] ?? 0 ),
				(int) ( $summary['failed'] ?? 0 ),
				(int) ( $summary['total'] ?? 0 )
			);
			?>
		</span>
	</div>
	<div class="clefa-card-body">
		<div class="clefa-dev-table-wrap">
			<table class="clefa-dev-results-table">
				<thead>
					<tr>
						<th>#</th>
						<th><?php esc_html_e( 'Suite', 'codelinden-elementor-form-addon' ); ?></th>
						<th><?php esc_html_e( 'Test', 'codelinden-elementor-form-addon' ); ?></th>
						<th><?php esc_html_e( 'Result', 'codelinden-elementor-form-addon' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rows as $row ) : ?>
					<tr class="<?php echo 'FAIL' === $row['status'] ? 'clefa-row-fail' : 'clefa-row-pass'; ?>">
						<td><?php echo esc_html( (string) $row['num'] ); ?></td>
						<td><code><?php echo esc_html( $row['suite'] ); ?></code></td>
						<td><code><?php echo esc_html( $row['test'] ); ?></code></td>
						<td><strong><?php echo esc_html( $row['status'] ); ?></strong></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<?php if ( ! empty( $issues ) ) : ?>
		<h3 style="margin-top:20px"><?php esc_html_e( 'Issues log', 'codelinden-elementor-form-addon' ); ?></h3>
		<table class="clefa-dev-results-table clefa-dev-issues-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Type', 'codelinden-elementor-form-addon' ); ?></th>
					<th><?php esc_html_e( 'Source', 'codelinden-elementor-form-addon' ); ?></th>
					<th><?php esc_html_e( 'Detail', 'codelinden-elementor-form-addon' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $issues as $issue ) : ?>
				<tr class="clefa-row-fail">
					<td><?php echo esc_html( $issue['type'] ?? '' ); ?></td>
					<td><code><?php echo esc_html( $issue['source'] ?? '' ); ?></code></td>
					<td><?php echo esc_html( $issue['detail'] ?? '' ); ?></td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php endif; ?>
	</div>
</div>
