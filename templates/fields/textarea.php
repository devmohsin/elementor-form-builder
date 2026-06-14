<?php
/**
 * Field template: textarea
 *
 * Available vars: $field, $form, $form_id, $step_id, $value, $errors
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

$field_id    = $field['field_id']    ?? '';
$placeholder = $field['placeholder'] ?? '';
$rows        = absint( $field['rows'] ?? 4 );
$max_length  = $field['validation']['max_length'] ?? '';
$has_error   = ! empty( $errors[ $field_id ] );
?>
<textarea
	id="clefa-field-<?php echo esc_attr( $field_id ); ?>"
	name="clefa_field[<?php echo esc_attr( $field_id ); ?>]"
	data-clefa-input
	data-clefa-field-id="<?php echo esc_attr( $field_id ); ?>"
	rows="<?php echo esc_attr( $rows ); ?>"
	class="clefa-input clefa-textarea<?php echo $has_error ? ' clefa-input-error' : ''; ?>"
	placeholder="<?php echo esc_attr( $placeholder ); ?>"
	<?php if ( $max_length ) : ?>maxlength="<?php echo esc_attr( $max_length ); ?>"<?php endif; ?>
	<?php if ( ! empty( $field['required'] ) ) : ?>required aria-required="true"<?php endif; ?>
	<?php if ( $has_error ) : ?>aria-describedby="clefa-error-<?php echo esc_attr( $field_id ); ?>" aria-invalid="true"<?php endif; ?>
	<?php if ( ! empty( $field['readonly'] ) ) : ?>readonly<?php endif; ?>
><?php echo esc_textarea( (string) $value ); ?></textarea>
<?php if ( $max_length && ! empty( $field['show_char_count'] ) ) : ?>
<div class="clefa-char-count" data-clefa-char-count data-clefa-max="<?php echo esc_attr( $max_length ); ?>" aria-live="polite">
	<span data-clefa-char-current>0</span> / <?php echo esc_html( $max_length ); ?>
</div>
<?php endif; ?>
