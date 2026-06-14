<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<header class="clefa-js-runner-header">
	<h1 class="clefa-js-runner-title"><?php esc_html_e( 'JavaScript Test Runner', 'codelinden-elementor-form-addon' ); ?></h1>
	<button type="button" class="clefa-js-runner-btn" data-clefa-run-js-tests>
		<?php esc_html_e( 'Run JS Tests', 'codelinden-elementor-form-addon' ); ?>
	</button>
</header>

<p class="clefa-js-runner-scope" data-clefa-js-runner-scope></p>

<div class="clefa-js-runner-phases">
	<div class="clefa-js-runner-phase clefa-js-runner-phase-single" data-clefa-js-test-phase="run" data-clefa-state="pending">
		<span class="clefa-js-runner-phase-num">1</span>
		<?php esc_html_e( 'Load all modules + one test bundle → full pass/fail table', 'codelinden-elementor-form-addon' ); ?>
	</div>
</div>

<p class="clefa-js-runner-summary" data-clefa-js-test-summary data-clefa-status="idle">
	<?php esc_html_e( 'Click Run. Every test appears in the table below with PASS or FAIL.', 'codelinden-elementor-form-addon' ); ?>
</p>

<div class="clefa-js-runner-results" data-clefa-js-test-results></div>

<details class="clefa-js-runner-log-details">
	<summary><?php esc_html_e( 'Load log', 'codelinden-elementor-form-addon' ); ?></summary>
	<div class="clefa-js-runner-log" data-clefa-js-test-live-list></div>
</details>

<div class="clefa-js-runner-mount-wrap" hidden>
	<div id="clefa-test-mount"></div>
</div>
