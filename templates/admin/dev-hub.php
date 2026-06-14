<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

$page_slug  = sanitize_key( wp_unslash( $_GET['page'] ?? 'clefa-dev' ) ); // phpcs:ignore WordPress.Security.NonceVerification
$base_url   = admin_url( 'admin.php?page=' . $page_slug );
$active_tab = $tab ?? 'suites';
$tabs       = array(
	'suites'      => __( 'Unit Suites', 'codelinden-elementor-form-addon' ),
	'integration' => __( 'Form Integration', 'codelinden-elementor-form-addon' ),
	'fixtures'    => __( 'Test Forms', 'codelinden-elementor-form-addon' ),
	'coverage'    => __( 'Coverage', 'codelinden-elementor-form-addon' ),
);

$phpunit_parsed = ( $flash && 'phpunit' === ( $flash['type'] ?? '' ) && ! empty( $flash['parsed'] ) ) ? $flash['parsed'] : null;
$phpunit_output = ( $flash && 'phpunit' === ( $flash['type'] ?? '' ) ) ? array(
	'success' => ! empty( $flash['success'] ),
	'output'  => $flash['output'] ?? $flash['message'] ?? '',
) : null;
$seed_output = ( $flash && in_array( $flash['type'] ?? '', array( 'seed', 'cleanup_all' ), true ) ) ? $flash : null;
?>
<div class="wrap clefa-admin-page clefa-dev-hub" data-clefa-dev-page>

	<h1 class="clefa-page-title"><?php esc_html_e( 'Dev / Test Suite', 'codelinden-elementor-form-addon' ); ?></h1>

	<div class="clefa-page-intro">
		<p class="clefa-page-subtitle">
			<?php esc_html_e( 'Run PHPUnit in PHP and JavaScript unit tests in the browser. Seed test forms, run integration tests, and review coverage gaps.', 'codelinden-elementor-form-addon' ); ?>
		</p>
		<?php if ( CLEFA_Testing::is_active() ) : ?>
			<p class="clefa-dev-badge" data-clefa-testing="1"><?php esc_html_e( 'CLEFA_TESTING mode is ON — test DB records are marked for cleanup.', 'codelinden-elementor-form-addon' ); ?></p>
		<?php endif; ?>
	</div>

	<nav class="clefa-dev-tabs">
		<?php foreach ( $tabs as $key => $label ) : ?>
			<a href="<?php echo esc_url( add_query_arg( 'tab', $key, $base_url ) ); ?>"
			   class="clefa-dev-tab<?php echo $active_tab === $key ? ' clefa-dev-tab-active' : ''; ?>">
				<?php echo esc_html( $label ); ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<?php if ( 'suites' === $active_tab ) : ?>
	<div class="clefa-dev-panel">
		<?php if ( ! $can_run_shell ) : ?>
			<div class="clefa-dev-notice clefa-dev-notice-warn">
				<?php esc_html_e( 'Shell test execution requires WP_DEBUG=true or the clefa_allow_dev_suite filter. You can still run tests from CLI:', 'codelinden-elementor-form-addon' ); ?>
				<code>vendor/bin/phpunit</code> · <code>npm test</code>
			</div>
		<?php else : ?>
			<p class="clefa-dev-hint">
				<?php esc_html_e( 'PHPUnit runs on the server (page reload). JavaScript tests run entirely inside the iframe on this page.', 'codelinden-elementor-form-addon' ); ?>
			</p>
		<?php endif; ?>

		<div class="clefa-dev-grid">
			<div class="clefa-card">
				<div class="clefa-card-header"><?php esc_html_e( 'PHPUnit (PHP)', 'codelinden-elementor-form-addon' ); ?></div>
				<div class="clefa-card-body">
					<p class="clefa-dev-hint"><?php esc_html_e( 'Runs dev/tests/php — validators, conditions, sanitizer, routing, dependencies.', 'codelinden-elementor-form-addon' ); ?></p>
					<form method="post">
						<?php wp_nonce_field( CLEFA_Dev_Page::NONCE_ACTION ); ?>
						<input type="hidden" name="clefa_dev_action" value="run_phpunit">
						<input type="hidden" name="clefa_tab" value="suites">
						<input type="hidden" name="clefa_page" value="<?php echo esc_attr( $page_slug ); ?>">
						<button type="submit" class="clefa-btn clefa-btn-primary" <?php disabled( ! $can_run_shell ); ?>>
							<span class="dashicons dashicons-controls-play"></span>
							<?php esc_html_e( 'Run PHPUnit', 'codelinden-elementor-form-addon' ); ?>
						</button>
					</form>
					<pre class="clefa-dev-output" data-clefa-status="<?php echo $phpunit_output ? ( $phpunit_output['success'] ? 'pass' : 'fail' ) : 'idle'; ?>"><?php
						if ( $phpunit_output ) {
							echo esc_html( $phpunit_output['output'] ?? '' );
						}
					?></pre>
				</div>
			</div>
			<div class="clefa-card">
				<div class="clefa-card-header"><?php esc_html_e( 'JavaScript Unit Tests', 'codelinden-elementor-form-addon' ); ?></div>
				<div class="clefa-card-body">
					<p class="clefa-dev-hint"><?php esc_html_e( 'This iframe runs all ~309 JavaScript tests (10 files: ValidationEngine, ConditionEngine, FormEngine, etc.). PHP tests (~391) run separately via the PHPUnit button — they are not duplicated here.', 'codelinden-elementor-form-addon' ); ?></p>
					<?php
					require_once CLEFA_PLUGIN_PATH . 'includes/Dev/Js_Test_Loader.php';
					$js_runner_url = CLEFA_Js_Test_Loader::get_runner_url();
					?>
					<iframe
						src="<?php echo esc_url( $js_runner_url ); ?>"
						class="clefa-js-test-runner-frame"
						title="<?php esc_attr_e( 'JavaScript test runner', 'codelinden-elementor-form-addon' ); ?>"
						sandbox="allow-scripts allow-same-origin"
					></iframe>
					<pre class="clefa-dev-output clefa-dev-output-compact" data-clefa-js-test-output data-clefa-status="idle"><?php esc_html_e( 'Run tests inside the iframe above.', 'codelinden-elementor-form-addon' ); ?></pre>
				</div>
			</div>
		</div>
		<?php
		if ( $phpunit_parsed ) {
			$parsed      = $phpunit_parsed;
			$suite_label = __( 'PHPUnit Results (strict)', 'codelinden-elementor-form-addon' );
			include CLEFA_TEMPLATE_PATH . 'admin/suite-results.php';
		}
		?>
	</div>

	<?php elseif ( 'integration' === $active_tab ) : ?>
	<div class="clefa-dev-panel">
		<?php
		include CLEFA_TEMPLATE_PATH . 'admin/tests.php';
		?>
	</div>

	<?php elseif ( 'fixtures' === $active_tab ) : ?>
	<div class="clefa-dev-panel">
		<div class="clefa-card">
			<div class="clefa-card-header"><?php esc_html_e( 'Seed Test Forms', 'codelinden-elementor-form-addon' ); ?></div>
			<div class="clefa-card-body">
				<p><?php esc_html_e( 'Creates published forms from dev/fixtures/forms/*.json for integration testing.', 'codelinden-elementor-form-addon' ); ?></p>
				<table class="widefat striped" style="margin-bottom:12px;">
					<thead><tr>
						<th><?php esc_html_e( 'File', 'codelinden-elementor-form-addon' ); ?></th>
						<th><?php esc_html_e( 'Covers', 'codelinden-elementor-form-addon' ); ?></th>
					</tr></thead>
					<tbody>
						<tr><td><code>validation-basic.json</code></td><td>required, email, text/number/textarea, min/max length, min/max value, url, checkbox min/max_checked, min_words, alpha_only</td></tr>
						<tr><td><code>validation-advanced.json</code></td><td>username (no_spaces, alphanumeric), phone, url, regex, no_html, no_urls, max_words, password_strength, confirm_password</td></tr>
						<tr><td><code>validation-file.json</code></td><td>file_type, max_file_size, max_files, single and multi-upload, min_words / max_words on textarea</td></tr>
						<tr><td><code>conditions-show-hide.json</code></td><td>show/hide via equals, is_checked, greater_than; radio, checkbox, number source fields</td></tr>
						<tr><td><code>conditions-style.json</code></td><td>add_class, set_style, set_label, set_placeholder, set_description; multiple logic groups</td></tr>
						<tr><td><code>conditions-require.json</code></td><td>require, unrequire, chained conditions, show+require combined</td></tr>
						<tr><td><code>multi-step-routing.json</code></td><td>4-step flow, conditional routing (path A / B), per-step validation, date age_over, review step</td></tr>
						<tr><td><code>all-field-types.json</code></td><td>text, email, password, number, textarea, select, radio, checkbox, date, range, file, hidden, html, notice — one of each</td></tr>
						<tr><td><code>repeater-basic.json</code></td><td>repeater with 7 sub-fields, per-row conditions (show/require based on role), dietary logic</td></tr>
						<tr><td><code>registration-form.json</code></td><td>realistic registration — username, email, password+confirm, account type, conditional company, notifications, terms</td></tr>
					</tbody>
				</table>
				<div class="clefa-dev-actions">
					<form method="post" style="display:inline">
						<?php wp_nonce_field( CLEFA_Dev_Page::NONCE_ACTION ); ?>
						<input type="hidden" name="clefa_dev_action" value="seed_forms">
						<input type="hidden" name="clefa_tab" value="fixtures">
						<input type="hidden" name="clefa_page" value="<?php echo esc_attr( $page_slug ); ?>">
						<button type="submit" class="clefa-btn clefa-btn-primary">
							<span class="dashicons dashicons-database-add"></span> <?php esc_html_e( 'Seed All Test Forms', 'codelinden-elementor-form-addon' ); ?>
						</button>
					</form>
					<form method="post" style="display:inline" onsubmit="return confirm('<?php echo esc_js( __( 'Delete all test submissions, logs, and seeded forms?', 'codelinden-elementor-form-addon' ) ); ?>');">
						<?php wp_nonce_field( CLEFA_Dev_Page::NONCE_ACTION ); ?>
						<input type="hidden" name="clefa_dev_action" value="cleanup_all">
						<input type="hidden" name="clefa_tab" value="fixtures">
						<input type="hidden" name="clefa_page" value="<?php echo esc_attr( $page_slug ); ?>">
						<button type="submit" class="clefa-btn clefa-btn-danger">
							<span class="dashicons dashicons-trash"></span> <?php esc_html_e( 'Cleanup All Test Data', 'codelinden-elementor-form-addon' ); ?>
						</button>
					</form>
				</div>
				<pre class="clefa-dev-output" data-clefa-status="<?php echo $seed_output && ! empty( $seed_output['success'] ) ? 'pass' : ( $seed_output ? 'fail' : 'idle' ); ?>"><?php
					if ( $seed_output && 'seed' === ( $seed_output['type'] ?? '' ) ) {
						$data = $seed_output['data'] ?? array();
						echo esc_html(
							'Created: ' . count( $data['created'] ?? array() )
							. ', Skipped: ' . count( $data['skipped'] ?? array() )
							. ( ! empty( $data['errors'] ) ? "\nErrors:\n" . implode( "\n", $data['errors'] ) : '' )
						);
					} elseif ( $seed_output && 'cleanup_all' === ( $seed_output['type'] ?? '' ) ) {
						$data = $seed_output['data'] ?? array();
						echo esc_html(
							'Cleaned submissions: ' . ( $data['submissions_deleted'] ?? 0 )
							. ', logs: ' . ( $data['test_logs_deleted'] ?? 0 )
							. ', forms: ' . ( $data['forms_deleted'] ?? 0 )
						);
					}
				?></pre>
			</div>
		</div>
	</div>

	<?php elseif ( 'coverage' === $active_tab && is_array( $coverage ) ) : ?>
	<div class="clefa-dev-panel">
		<div class="clefa-card">
			<div class="clefa-card-header">
				<?php esc_html_e( 'Test Coverage Map', 'codelinden-elementor-form-addon' ); ?>
				<a href="<?php echo esc_url( add_query_arg( 'tab', 'coverage', $base_url ) ); ?>" class="clefa-btn clefa-btn-ghost clefa-btn-sm">
					<span class="dashicons dashicons-update"></span> <?php esc_html_e( 'Refresh', 'codelinden-elementor-form-addon' ); ?>
				</a>
			</div>
			<div class="clefa-card-body">
				<p><strong><?php esc_html_e( 'PHP:', 'codelinden-elementor-form-addon' ); ?></strong>
					<?php echo esc_html( $coverage['php']['tested'] . '/' . $coverage['php']['total'] ); ?>
					<?php esc_html_e( 'classes have tests.', 'codelinden-elementor-form-addon' ); ?>
					<strong><?php esc_html_e( 'JS:', 'codelinden-elementor-form-addon' ); ?></strong>
					<?php echo esc_html( $coverage['js']['tested'] . '/' . $coverage['js']['total'] ); ?>
					<?php esc_html_e( 'modules have tests.', 'codelinden-elementor-form-addon' ); ?>
				</p>
				<p><code>CLEFA_TESTING</code> = <?php echo $coverage['testing_mode'] ? 'true' : 'false'; ?></p>

				<h3><?php esc_html_e( 'PHP — missing tests', 'codelinden-elementor-form-addon' ); ?></h3>
				<ul class="clefa-coverage-list">
					<?php foreach ( array_slice( array_filter( $coverage['php']['items'], function( $i ) { return ! $i['has_test']; } ), 0, 30 ) as $item ) : ?>
						<li><code><?php echo esc_html( $item['class'] ); ?></code></li>
					<?php endforeach; ?>
				</ul>

				<h3><?php esc_html_e( 'JS — missing tests', 'codelinden-elementor-form-addon' ); ?></h3>
				<ul class="clefa-coverage-list">
					<?php foreach ( array_slice( array_filter( $coverage['js']['items'], function( $i ) { return ! $i['has_test']; } ), 0, 30 ) as $item ) : ?>
						<li><code><?php echo esc_html( $item['module'] ); ?></code></li>
					<?php endforeach; ?>
				</ul>
			</div>
		</div>
		<p class="clefa-dev-hint">
			<?php esc_html_e( 'Not every function has a dedicated test yet — this panel shows which PHP classes and JS modules still need test files. Aim for behaviour coverage on critical paths first (validation, conditions, submission).', 'codelinden-elementor-form-addon' ); ?>
		</p>
	</div>
	<?php endif; ?>
</div>

<style>
.clefa-dev-tabs{display:flex;gap:4px;margin-bottom:20px;border-bottom:1px solid var(--clefa-border);padding-bottom:0}
.clefa-dev-tab{padding:10px 16px;text-decoration:none;color:var(--clefa-text-muted);border-bottom:2px solid transparent;margin-bottom:-1px;font-size:.875rem}
.clefa-dev-tab-active{color:var(--clefa-primary);border-bottom-color:var(--clefa-primary);font-weight:600}
.clefa-dev-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px}
.clefa-dev-table-wrap{max-height:480px;overflow:auto;border:1px solid var(--clefa-border);border-radius:6px}
.clefa-dev-results-table{width:100%;border-collapse:collapse;font-size:.78rem}
.clefa-dev-results-table th,.clefa-dev-results-table td{padding:6px 10px;border-bottom:1px solid var(--clefa-border);text-align:left;vertical-align:top}
.clefa-dev-results-table th{background:var(--clefa-bg);position:sticky;top:0;z-index:1}
.clefa-row-pass td:last-child{color:#16a34a}
.clefa-row-fail td:last-child{color:#dc2626}
.clefa-row-fail{background:#fef2f2}
.clefa-dev-issues-table{margin-top:8px}
.clefa-dev-output{background:#1e1e1e;color:#d4d4d4;padding:12px;border-radius:6px;font-size:.75rem;max-height:400px;overflow:auto;margin-top:12px;white-space:pre-wrap;min-height:2em}
.clefa-dev-output[data-clefa-status="fail"]{border:1px solid #dc2626}
.clefa-dev-output[data-clefa-status="running"]{border:1px solid #d97706;color:#fcd34d}
.clefa-dev-output[data-clefa-status="pass"]{border:1px solid #16a34a}
.clefa-dev-hint{color:var(--clefa-text-muted);font-size:.85rem}
.clefa-dev-badge{background:#f0f6fc;color:#1d4f91;padding:8px 12px;border-radius:6px;font-size:.85rem;margin-top:8px}
.clefa-dev-notice-warn{background:#fffbeb;border:1px solid #fcd34d;padding:10px 14px;border-radius:6px;margin-bottom:16px;font-size:.875rem}
.clefa-dev-actions{display:flex;gap:10px;margin-top:12px;flex-wrap:wrap}
.clefa-js-test-runner-frame{width:100%;min-height:560px;border:1px solid var(--clefa-border);border-radius:8px;background:#fff}
.clefa-dev-output-compact{max-height:120px;margin-top:10px}
.clefa-coverage-list{columns:2;font-size:.82rem}
@media(max-width:900px){.clefa-dev-grid{grid-template-columns:1fr}.clefa-coverage-list{columns:1}}
</style>
