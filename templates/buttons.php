<?php
/**
 * Step navigation buttons template.
 *
 * Rendered by step.php inside every form step. Child themes can override
 * this file at:
 *   clefa-forms/buttons.php
 *
 * Available variables:
 *   $step        array   The current step config array.
 *   $step_index  int     Zero-based index of the current step.
 *   $is_last     bool    Whether this is the final (submit) step.
 *   $step_count  int     Total number of steps in the form.
 *   $form_id     int     Form ID.
 *   $config      array   Full decoded form config.
 *
 * Button data attributes used by FormEngine.js:
 *   data-clefa-prev   — triggers navigation to the previous step.
 *   data-clefa-next   — triggers validation + navigation to the next step.
 *   data-clefa-submit — triggers final form submission.
 *
 * The step wrapper carries data-clefa-btn-mode (disable-until-valid |
 * hide-until-valid | always) which ValidationEngine.js reads to manage
 * the enabled/visible state of the primary button automatically.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$save_draft_enabled  = ! empty( $step['enable_save_draft'] );
$save_draft_text     = esc_html( ( $step['save_draft_text']    ?? '' ) ?: __( 'Save draft', 'codelinden-elementor-form-addon' ) );
$prev_text           = esc_html( ( $step['prev_button_text']   ?? '' ) ?: __( '← Previous', 'codelinden-elementor-form-addon' ) );
$next_text           = esc_html( ( $step['next_button_text']   ?? '' ) ?: __( 'Next →', 'codelinden-elementor-form-addon' ) );
$submit_text         = esc_html( ( $step['submit_button_text'] ?? '' ) ?: __( 'Submit', 'codelinden-elementor-form-addon' ) );
$notice_above_submit = $step['notice_above_submit'] ?? '';
// always | disabled-until-valid | always-validate (clickable but greyed + shows errors)
$btn_mode            = sanitize_key( $step['button_mode'] ?? 'always' );
?>
<div class="clefa-step-buttons" data-clefa-btn-mode="<?php echo esc_attr( $btn_mode ); ?>">
	<?php do_action( 'clefa_before_step_buttons', $step_index, $step, $form_id, $config ); ?>

	<?php if ( $notice_above_submit ) : ?>
	<div class="clefa-notice-above-submit" role="note">
		<?php echo wp_kses_post( $notice_above_submit ); ?>
	</div>
	<?php endif; ?>

	<?php if ( $step_index > 0 ) : ?>
	<button
		type="button"
		class="clefa-btn clefa-btn-secondary clefa-btn-prev"
		data-clefa-prev
	>
		<?php echo $prev_text; ?>
	</button>
	<?php endif; ?>

	<?php if ( $save_draft_enabled ) : ?>
	<button
		type="button"
		class="clefa-btn clefa-btn-ghost clefa-btn-save-draft"
		data-clefa-save-draft
	>
		<?php echo $save_draft_text; ?>
	</button>
	<?php endif; ?>

	<?php if ( ! $is_last ) : ?>
	<button
		type="button"
		class="clefa-btn clefa-btn-primary clefa-btn-next"
		data-clefa-next
	>
		<?php echo $next_text; ?>
	</button>
	<?php else : ?>
	<button
		type="submit"
		class="clefa-btn clefa-btn-primary clefa-btn-submit"
		data-clefa-submit
	>
		<?php echo $submit_text; ?>
	</button>
	<?php endif; ?>

	<?php do_action( 'clefa_after_step_buttons', $step_index, $step, $form_id, $config ); ?>
</div>
