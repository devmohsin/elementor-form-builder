<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

$integration_result = ( $flash && 'integration' === ( $flash['type'] ?? '' ) ) ? $flash : null;
$cleanup_message    = ( $flash && 'cleanup_group' === ( $flash['type'] ?? '' ) && ! empty( $flash['message'] ) ) ? $flash['message'] : '';
$page_slug          = $page_slug ?? sanitize_key( wp_unslash( $_GET['page'] ?? 'clefa-dev' ) ); // phpcs:ignore WordPress.Security.NonceVerification
?>
<div class="clefa-admin-page" data-clefa-tests-page>
	<?php if ( $cleanup_message ) : ?>
		<p class="clefa-success-msg" style="margin-bottom:16px"><?php echo esc_html( $cleanup_message ); ?></p>
	<?php endif; ?>
	<div class="clefa-tests-layout">
		<aside class="clefa-tests-sidebar">
			<div class="clefa-card">
				<div class="clefa-card-header"><?php esc_html_e( 'Select Form', 'codelinden-elementor-form-addon' ); ?></div>
				<div class="clefa-card-body">
					<form method="get">
						<input type="hidden" name="page" value="<?php echo esc_attr( $page_slug ); ?>">
						<input type="hidden" name="tab" value="integration">
						<select name="form_id" class="clefa-select-full" onchange="this.form.submit()">
							<option value=""><?php esc_html_e( '— Choose a form —', 'codelinden-elementor-form-addon' ); ?></option>
							<?php foreach ( $forms as $f ) : ?>
							<option value="<?php echo esc_attr( $f['id'] ); ?>" <?php selected( $form_id, $f['id'] ); ?>>
								<?php echo esc_html( $f['form_name'] ); ?>
								(<?php echo esc_html( $f['status'] ); ?>)
							</option>
							<?php endforeach; ?>
						</select>
					</form>

					<?php if ( $form ) : ?>
					<hr style="margin:16px 0">
					<div class="clefa-form-meta-mini">
						<span class="clefa-badge clefa-badge-<?php echo esc_attr( $form['status'] ); ?>"><?php echo esc_html( ucfirst( $form['status'] ) ); ?></span>
						<strong><?php echo esc_html( $form['form_name'] ); ?></strong>
					</div>
					<div style="margin-top:12px;font-size:.82rem;color:var(--clefa-text-muted)">
						<?php printf( esc_html__( '%d fields found', 'codelinden-elementor-form-addon' ), count( $form_fields ) ); ?>
					</div>
					<?php endif; ?>
				</div>
			</div>

			<?php if ( $form ) : ?>
			<div class="clefa-card" style="margin-top:16px">
				<div class="clefa-card-header"><?php esc_html_e( 'Available Fields', 'codelinden-elementor-form-addon' ); ?></div>
				<div class="clefa-card-body clefa-tests-field-list">
					<?php foreach ( $form_fields as $f ) : ?>
					<div class="clefa-tests-field-chip"
					     data-field-id="<?php echo esc_attr( $f['field_id'] ); ?>"
					     data-field-type="<?php echo esc_attr( $f['field_type'] ); ?>"
					     title="<?php echo esc_attr( $f['field_id'] ); ?>">
						<span class="clefa-tests-field-label"><?php echo esc_html( $f['label'] ); ?></span>
						<span class="clefa-tests-field-type"><?php echo esc_html( $f['field_type'] ); ?></span>
						<?php if ( $f['required'] ) : ?><span class="clefa-required-dot" title="Required">•</span><?php endif; ?>
					</div>
					<?php endforeach; ?>
				</div>
			</div>
			<?php endif; ?>
		</aside>

		<main class="clefa-tests-main">
			<?php if ( ! $form ) : ?>
			<div class="clefa-empty-state">
				<span class="dashicons dashicons-clipboard" style="font-size:3rem;height:auto;width:auto;color:var(--clefa-border)"></span>
				<p><?php esc_html_e( 'Select a form to start building test cases.', 'codelinden-elementor-form-addon' ); ?></p>
			</div>
			<?php else : ?>

			<form method="post" id="clefa-integration-form">
				<?php wp_nonce_field( CLEFA_Dev_Page::NONCE_ACTION ); ?>
				<input type="hidden" name="clefa_dev_action" value="run_integration">
				<input type="hidden" name="clefa_tab" value="integration">
				<input type="hidden" name="clefa_page" value="<?php echo esc_attr( $page_slug ); ?>">
				<input type="hidden" name="form_id" value="<?php echo esc_attr( $form_id ); ?>">
				<input type="hidden" name="test_cases" id="clefa-test-cases-json" value="">

				<div class="clefa-card" id="clefa-test-builder">
					<div class="clefa-card-header clefa-tests-builder-header">
						<span><?php esc_html_e( 'Test Cases', 'codelinden-elementor-form-addon' ); ?></span>
						<div class="clefa-tests-header-actions">
							<button type="button" class="clefa-btn clefa-btn-secondary clefa-btn-sm" id="clefa-add-test-case">
								<span class="dashicons dashicons-plus-alt2"></span>
								<?php esc_html_e( 'Add Test Case', 'codelinden-elementor-form-addon' ); ?>
							</button>
							<button type="submit" class="clefa-btn clefa-btn-primary clefa-btn-sm" id="clefa-run-tests">
								<span class="dashicons dashicons-controls-play"></span>
								<?php esc_html_e( 'Run All Tests', 'codelinden-elementor-form-addon' ); ?>
							</button>
						</div>
					</div>
					<div class="clefa-card-body">
						<div id="clefa-test-cases-wrap">
							<p class="clefa-tests-empty-hint" id="clefa-no-tests-hint">
								<?php esc_html_e( 'No test cases yet. Click "Add Test Case" to create your first test.', 'codelinden-elementor-form-addon' ); ?>
							</p>
						</div>
					</div>
				</div>
			</form>

			<?php if ( $integration_result ) : ?>
				<?php if ( ! $integration_result['success'] && ! empty( $integration_result['message'] ) ) : ?>
				<div class="clefa-dev-notice clefa-dev-notice-warn" style="margin-bottom:16px">
					<?php echo esc_html( $integration_result['message'] ); ?>
				</div>
				<?php elseif ( ! empty( $integration_result['data'] ) ) : ?>
					<?php
					$data    = $integration_result['data'];
					$summary = $data['summary'] ?? array( 'total' => 0, 'passed' => 0, 'failed' => 0 );
					$group_id = $data['group_id'] ?? '';
					?>
				<div class="clefa-card" id="clefa-test-results">
					<div class="clefa-card-header clefa-tests-results-header">
						<span><?php esc_html_e( 'Test Results', 'codelinden-elementor-form-addon' ); ?></span>
						<div class="clefa-tests-summary">
							<span class="clefa-badge clefa-badge-success"><?php echo esc_html( (string) ( $summary['passed'] ?? 0 ) ); ?> <?php esc_html_e( 'Passed', 'codelinden-elementor-form-addon' ); ?></span>
							<?php if ( ! empty( $summary['failed'] ) ) : ?>
								<span class="clefa-badge clefa-badge-error"><?php echo esc_html( (string) $summary['failed'] ); ?> <?php esc_html_e( 'Failed', 'codelinden-elementor-form-addon' ); ?></span>
							<?php endif; ?>
							/ <?php echo esc_html( (string) ( $summary['total'] ?? 0 ) ); ?> <?php esc_html_e( 'total', 'codelinden-elementor-form-addon' ); ?>
						</div>
						<?php if ( $group_id ) : ?>
						<form method="post" style="display:inline" onsubmit="return confirm('<?php echo esc_js( __( 'This will delete all test submissions for this run. Continue?', 'codelinden-elementor-form-addon' ) ); ?>');">
							<?php wp_nonce_field( CLEFA_Dev_Page::NONCE_ACTION ); ?>
							<input type="hidden" name="clefa_dev_action" value="cleanup_group">
							<input type="hidden" name="clefa_tab" value="integration">
							<input type="hidden" name="clefa_page" value="<?php echo esc_attr( $page_slug ); ?>">
							<input type="hidden" name="form_id" value="<?php echo esc_attr( $form_id ); ?>">
							<input type="hidden" name="group_id" value="<?php echo esc_attr( $group_id ); ?>">
							<button type="submit" class="clefa-btn clefa-btn-ghost clefa-btn-sm">
								<span class="dashicons dashicons-trash"></span>
								<?php esc_html_e( 'Cleanup Test Data', 'codelinden-elementor-form-addon' ); ?>
							</button>
						</form>
						<?php endif; ?>
					</div>
					<div class="clefa-card-body">
						<?php foreach ( $data['results'] ?? array() as $r ) : ?>
							<?php
							$cls  = ! empty( $r['passed'] ) ? 'clefa-test-result-pass' : 'clefa-test-result-fail';
							$icon = ! empty( $r['passed'] ) ? 'dashicons-yes-alt' : 'dashicons-dismiss';
							?>
						<div class="clefa-test-result <?php echo esc_attr( $cls ); ?>">
							<div class="clefa-test-result-header">
								<span class="dashicons <?php echo esc_attr( $icon ); ?>"></span>
								<?php echo esc_html( $r['test_name'] ?? '' ); ?>
							</div>
							<?php if ( empty( $r['passed'] ) && ! empty( $r['errors'] ) ) : ?>
							<div class="clefa-test-result-errors">
								<strong><?php esc_html_e( 'Validation errors:', 'codelinden-elementor-form-addon' ); ?></strong>
								<ul>
									<?php foreach ( $r['errors'] as $field_id => $error ) : ?>
									<li><code><?php echo esc_html( $field_id ); ?></code>: <?php echo esc_html( $error ); ?></li>
									<?php endforeach; ?>
								</ul>
							</div>
							<?php endif; ?>
							<?php if ( ! empty( $r['assertion_results'] ) ) : ?>
							<div class="clefa-test-result-assertions">
								<strong><?php esc_html_e( 'Assertions:', 'codelinden-elementor-form-addon' ); ?></strong>
								<ul>
									<?php foreach ( $r['assertion_results'] as $a ) : ?>
									<li class="<?php echo ! empty( $a['passed'] ) ? 'clefa-assertion-pass' : 'clefa-assertion-fail'; ?>">
										<?php echo esc_html( $a['message'] ?? '' ); ?>
									</li>
									<?php endforeach; ?>
								</ul>
							</div>
							<?php endif; ?>
							<?php if ( ! empty( $r['submission_id'] ) ) : ?>
							<div class="clefa-test-result-meta">
								<?php printf( esc_html__( 'Submission #%d created (test)', 'codelinden-elementor-form-addon' ), (int) $r['submission_id'] ); ?>
							</div>
							<?php endif; ?>
						</div>
						<?php endforeach; ?>
					</div>
				</div>
				<?php endif; ?>
			<?php endif; ?>

			<div class="clefa-card" id="clefa-test-history" style="margin-top:24px">
				<div class="clefa-card-header">
					<?php esc_html_e( 'Test History', 'codelinden-elementor-form-addon' ); ?>
					<a href="<?php echo esc_url( add_query_arg( array( 'page' => $page_slug, 'tab' => 'integration', 'form_id' => $form_id ), admin_url( 'admin.php' ) ) ); ?>" class="clefa-btn clefa-btn-ghost clefa-btn-sm">
						<span class="dashicons dashicons-update"></span>
						<?php esc_html_e( 'Refresh', 'codelinden-elementor-form-addon' ); ?>
					</a>
				</div>
				<div class="clefa-card-body">
					<?php if ( empty( $test_history ) ) : ?>
						<p class="clefa-tests-empty-hint"><?php esc_html_e( 'No test history yet.', 'codelinden-elementor-form-addon' ); ?></p>
					<?php else : ?>
						<?php foreach ( $test_history as $gid => $logs ) : ?>
							<?php
							$passed = count( array_filter( $logs, function( $l ) { return 'passed' === ( $l['status'] ?? '' ); } ) );
							$total  = count( $logs );
							?>
						<div class="clefa-history-group">
							<div class="clefa-history-group-header">
								<span class="clefa-badge <?php echo $passed === $total ? 'clefa-badge-success' : 'clefa-badge-error'; ?>"><?php echo esc_html( (string) $passed . '/' . (string) $total ); ?></span>
								<code><?php echo esc_html( $gid ); ?></code>
								<span class="clefa-history-date"><?php echo esc_html( $logs[0]['created_at'] ?? '' ); ?></span>
							</div>
							<ul class="clefa-history-items">
								<?php foreach ( $logs as $l ) : ?>
								<li class="<?php echo 'passed' === ( $l['status'] ?? '' ) ? 'clefa-pass' : 'clefa-fail'; ?>">
									<span class="dashicons <?php echo 'passed' === ( $l['status'] ?? '' ) ? 'dashicons-yes-alt' : 'dashicons-dismiss'; ?>"></span>
									<?php echo esc_html( $l['test_name'] ?? '' ); ?>
								</li>
								<?php endforeach; ?>
							</ul>
						</div>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>
			</div>

			<?php endif; ?>
		</main>
	</div>
</div>

<script type="text/template" id="clefa-test-case-template">
<div class="clefa-test-case" data-test-id="{{ID}}">
	<div class="clefa-test-case-header">
		<button type="button" class="clefa-test-case-toggle" aria-expanded="true">
			<span class="dashicons dashicons-arrow-down-alt2"></span>
		</button>
		<input type="text" class="clefa-test-case-name" placeholder="<?php esc_attr_e( 'Test name…', 'codelinden-elementor-form-addon' ); ?>" value="Test Case {{NUM}}">
		<label class="clefa-test-inline-label">
			<input type="checkbox" class="clefa-test-expect-pass" checked>
			<?php esc_html_e( 'Expect validation to pass', 'codelinden-elementor-form-addon' ); ?>
		</label>
		<label class="clefa-test-inline-label">
			<input type="checkbox" class="clefa-test-skip-actions">
			<?php esc_html_e( 'Skip actions', 'codelinden-elementor-form-addon' ); ?>
		</label>
		<button type="button" class="clefa-test-case-remove clefa-btn clefa-btn-ghost clefa-btn-xs">
			<span class="dashicons dashicons-no-alt"></span>
		</button>
	</div>
	<div class="clefa-test-case-body">
		<div class="clefa-test-section">
			<div class="clefa-test-section-header">
				<?php esc_html_e( 'Input Data', 'codelinden-elementor-form-addon' ); ?>
				<button type="button" class="clefa-add-test-field clefa-btn clefa-btn-ghost clefa-btn-xs">
					<span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e( 'Add Field', 'codelinden-elementor-form-addon' ); ?>
				</button>
			</div>
			<div class="clefa-test-fields-wrap">
				<p class="clefa-tests-empty-hint clefa-test-fields-empty"><?php esc_html_e( 'No input fields set. Click "Add Field" to define test data.', 'codelinden-elementor-form-addon' ); ?></p>
			</div>
		</div>
		<div class="clefa-test-section">
			<div class="clefa-test-section-header">
				<?php esc_html_e( 'Assertions', 'codelinden-elementor-form-addon' ); ?>
				<button type="button" class="clefa-add-assertion clefa-btn clefa-btn-ghost clefa-btn-xs">
					<span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e( 'Add Assertion', 'codelinden-elementor-form-addon' ); ?>
				</button>
			</div>
			<div class="clefa-test-assertions-wrap">
				<p class="clefa-tests-empty-hint clefa-assertions-empty"><?php esc_html_e( 'No assertions. Optionally add field-level checks.', 'codelinden-elementor-form-addon' ); ?></p>
			</div>
		</div>
	</div>
</div>
</script>

<script>
window.clefaTestsData = <?php echo wp_json_encode( array(
	'fields' => $form_fields,
) ); ?>;
</script>

<style>
.clefa-tests-layout{display:grid;grid-template-columns:280px 1fr;gap:24px;align-items:start}
.clefa-tests-sidebar{position:sticky;top:32px}
.clefa-tests-builder-header,.clefa-tests-results-header{display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap}
.clefa-tests-header-actions{display:flex;gap:8px}
.clefa-tests-field-list{display:flex;flex-wrap:wrap;gap:6px}
.clefa-tests-field-chip{background:var(--clefa-bg);border:1px solid var(--clefa-border);border-radius:6px;padding:4px 8px;font-size:.8rem;cursor:default;display:flex;gap:6px;align-items:center}
.clefa-tests-field-type{color:var(--clefa-text-muted);font-size:.72rem}
.clefa-required-dot{color:var(--clefa-danger);font-size:1rem;line-height:1}
.clefa-test-case{border:1px solid var(--clefa-border);border-radius:8px;margin-bottom:12px;overflow:hidden}
.clefa-test-case-header{display:flex;align-items:center;gap:8px;padding:10px 14px;background:var(--clefa-bg);flex-wrap:wrap}
.clefa-test-case-toggle{background:none;border:none;cursor:pointer;padding:0;color:var(--clefa-text-muted)}
.clefa-test-case-name{flex:1;border:1px solid var(--clefa-border);border-radius:6px;padding:5px 8px;font-size:.9rem;min-width:0}
.clefa-test-inline-label{display:flex;align-items:center;gap:5px;font-size:.82rem;white-space:nowrap;cursor:pointer}
.clefa-test-case-body{padding:16px;display:grid;grid-template-columns:1fr 1fr;gap:16px}
.clefa-test-section-header{display:flex;align-items:center;justify-content:space-between;font-weight:600;font-size:.85rem;margin-bottom:8px}
.clefa-test-field-row,.clefa-assertion-row{display:flex;gap:8px;align-items:center;margin-bottom:6px}
.clefa-test-field-row select,.clefa-test-field-row input,.clefa-assertion-row select{flex:1;font-size:.82rem;padding:5px 8px;border:1px solid var(--clefa-border);border-radius:6px;min-width:0}
.clefa-test-result{border:1px solid var(--clefa-border);border-radius:8px;padding:12px 16px;margin-bottom:10px}
.clefa-test-result-pass{border-color:#16a34a;background:#f0fdf4}
.clefa-test-result-fail{border-color:#dc2626;background:#fef2f2}
.clefa-test-result-header{display:flex;align-items:center;gap:8px;font-weight:600;margin-bottom:8px}
.clefa-test-result-pass .clefa-test-result-header .dashicons{color:#16a34a}
.clefa-test-result-fail .clefa-test-result-header .dashicons{color:#dc2626}
.clefa-test-result-errors,.clefa-test-result-assertions{font-size:.85rem;margin-top:8px}
.clefa-test-result-errors ul,.clefa-test-result-assertions ul{margin:.4rem 0 0 1rem;padding:0}
.clefa-assertion-pass{color:#16a34a}
.clefa-assertion-fail{color:#dc2626}
.clefa-test-result-meta{font-size:.8rem;color:var(--clefa-text-muted);margin-top:6px}
.clefa-history-group{border:1px solid var(--clefa-border);border-radius:8px;margin-bottom:12px;overflow:hidden}
.clefa-history-group-header{display:flex;align-items:center;gap:8px;padding:8px 14px;background:var(--clefa-bg);font-size:.85rem}
.clefa-history-date{color:var(--clefa-text-muted);font-size:.8rem;margin-left:auto}
.clefa-history-items{list-style:none;margin:0;padding:8px 14px 10px;display:flex;flex-direction:column;gap:4px}
.clefa-history-items li{display:flex;align-items:center;gap:6px;font-size:.85rem}
.clefa-history-items .clefa-pass .dashicons{color:#16a34a}
.clefa-history-items .clefa-fail .dashicons{color:#dc2626}
.clefa-tests-summary{display:flex;align-items:center;gap:8px}
.clefa-empty-state{text-align:center;padding:60px 24px;color:var(--clefa-text-muted)}
.clefa-tests-empty-hint{color:var(--clefa-text-muted);font-size:.85rem;margin:0}
.clefa-success-msg{color:#16a34a;font-weight:600;margin:0 0 12px}
.clefa-select-full{width:100%}
@media(max-width:900px){.clefa-tests-layout{grid-template-columns:1fr}.clefa-tests-sidebar{position:static}.clefa-test-case-body{grid-template-columns:1fr}}
</style>
