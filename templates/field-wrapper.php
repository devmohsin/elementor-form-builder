<?php
/**
 * Field wrapper template.
 *
 * Wraps every rendered field with the outer container, label, notices,
 * and error area. Override in child theme at:
 *   clefa-forms/field-wrapper.php
 *
 * Variables passed from step.php:
 *
 * @var array  $field   Full field config array.
 * @var string $fid     Sanitised field_id attribute value.
 * @var string $ftype   Sanitised field_type value.
 * @var mixed  $value   Current / default value.
 * @var array  $errors  Keyed error messages from server-side validation.
 * @var array  $form    Full form row (DB row).
 * @var int    $form_id Form ID.
 * @var string $step_id Step ID.
 * @var array  $config  Full decoded form config.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

// Fields that should never receive a wrapper (invisible or layout-only).
$no_wrap_types = array( 'hidden', 'grid_break' );
if ( in_array( $ftype, $no_wrap_types, true ) ) {
	echo CLEFA_Form_Renderer::render_field( $field, $form, $step_id, $value, $errors ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	return;
}

$field_id        = $fid;
$label_text      = $field['label']         ?? '';
$required        = ! empty( $field['required'] );
$hide_label      = ! empty( $field['hide_label'] );
$notice_before   = $field['notice_before'] ?? '';   // Notice shown above the label
$notice_after    = $field['notice_after']  ?? '';   // Notice shown below the input
$help_text       = $field['help_text'] ?? $field['description'] ?? '';   // Small helper text below input
$has_error       = ! empty( $errors[ $field_id ] );
$error_msg       = $has_error ? (string) reset( $errors[ $field_id ] ) : '';
$custom_class    = sanitize_html_class( $field['custom_class'] ?? '' );
$conditions      = ! empty( $field['conditions'] ) ? $field['conditions'] : array();
$is_visible      = empty( $conditions ); // Fields with conditions start hidden if JS manages them
$live_check      = ! empty( $field['live_check'] );

// Data-attribute encoding for conditions
$cond_json = ! empty( $conditions ) ? esc_attr( wp_json_encode( $conditions ) ) : '';
?>
<div
	class="clefa-field-wrap<?php
		echo $has_error    ? ' clefa-has-error'              : '';
		echo $required     ? ' clefa-field-required'         : '';
		echo $live_check   ? ' clefa-has-live-check'         : '';
		echo $custom_class ? ' ' . $custom_class             : '';
	?>"
	data-clefa-field="<?php echo esc_attr( $field_id ); ?>"
	data-clefa-field-type="<?php echo esc_attr( $ftype ); ?>"
	<?php if ( $cond_json ) : ?>data-clefa-conditions="<?php echo $cond_json; ?>"<?php endif; ?>
	data-clefa-visible="<?php echo $is_visible ? '1' : '0'; ?>"
	<?php if ( $live_check ) : ?>data-clefa-live-check-field="<?php echo esc_attr( $field_id ); ?>"<?php endif; ?>
>
	<?php /* ── Notice BEFORE the label (22.1: "Before field") ── */ ?>
	<?php if ( $notice_before ) : ?>
	<div class="clefa-field-notice clefa-field-notice-before" role="note">
		<?php echo wp_kses_post( $notice_before ); ?>
	</div>
	<?php endif; ?>

	<?php /* ── Label ── */ ?>
	<?php if ( $label_text && ! $hide_label ) : ?>
	<label
		class="clefa-label"
		for="clefa-field-<?php echo esc_attr( $field_id ); ?>"
	>
		<?php echo esc_html( $label_text ); ?>
		<?php if ( $required ) : ?>
		<span class="clefa-required" aria-hidden="true">*</span>
		<?php endif; ?>
	</label>
	<?php endif; ?>

	<?php /* ── Input area ── */ ?>
	<div class="clefa-input-wrap">
		<?php
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo CLEFA_Form_Renderer::render_field( $field, $form, $step_id, $value, $errors );
		?>

		<?php /* Live-check spinner and status (22.1: "On API check") */ ?>
		<?php if ( $live_check ) : ?>
		<span class="clefa-live-check-spinner" data-clefa-live-spinner aria-hidden="true"></span>
		<span class="clefa-live-check-status" data-clefa-live-status aria-live="polite"></span>
		<?php endif; ?>
	</div>

	<?php /* ── Help / hint text (22.1: "Below input") ── */ ?>
	<?php if ( $help_text ) : ?>
	<p class="clefa-field-help" id="clefa-help-<?php echo esc_attr( $field_id ); ?>">
		<?php echo wp_kses_post( $help_text ); ?>
	</p>
	<?php endif; ?>

	<?php /* ── Validation error (22.1: inline, "Below input") ── */ ?>
	<span
		class="clefa-error-msg<?php echo $has_error ? ' clefa-error-visible' : ''; ?>"
		id="clefa-error-<?php echo esc_attr( $field_id ); ?>"
		data-clefa-error
		role="alert"
		aria-live="polite"
		<?php if ( ! $has_error ) : ?>aria-hidden="true"<?php endif; ?>
	><?php echo esc_html( $error_msg ); ?></span>

	<?php /* ── Notice AFTER the input (22.1: "After field") ── */ ?>
	<?php if ( $notice_after ) : ?>
	<div class="clefa-field-notice clefa-field-notice-after" role="note">
		<?php echo wp_kses_post( $notice_after ); ?>
	</div>
	<?php endif; ?>

	<?php do_action( 'clefa_after_field', $field_id, $field, $form_id ); ?>
</div>
