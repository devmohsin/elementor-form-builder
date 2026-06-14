<?php
/**
 * Field template: radio
 *
 * Available vars: $field, $form, $form_id, $step_id, $value, $errors
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

$field_id  = $field['field_id'] ?? '';
$options   = $field['options']  ?? array();
$has_error = ! empty( $errors[ $field_id ] );
$selected  = (string) $value;
$inline    = ! empty( $field['inline'] );
?>
<div
	class="clefa-radio-group<?php echo $inline ? ' clefa-radio-group-inline' : ''; ?><?php echo $has_error ? ' clefa-field-error' : ''; ?>"
	data-clefa-input
	data-clefa-field-id="<?php echo esc_attr( $field_id ); ?>"
	role="radiogroup"
	aria-labelledby="clefa-label-<?php echo esc_attr( $field_id ); ?>"
	<?php if ( $has_error ) : ?>aria-describedby="clefa-error-<?php echo esc_attr( $field_id ); ?>"<?php endif; ?>
>
	<?php foreach ( $options as $i => $opt ) :
		$opt_val   = $opt['value'] ?? $opt['label'] ?? '';
		$opt_label = $opt['label'] ?? $opt_val;
		$is_sel    = $selected === (string) $opt_val;
		$input_id  = 'clefa-field-' . esc_attr( $field_id ) . '-' . $i;
	?>
	<label class="clefa-radio-label" for="<?php echo esc_attr( $input_id ); ?>">
		<input
			type="radio"
			id="<?php echo esc_attr( $input_id ); ?>"
			name="clefa_field[<?php echo esc_attr( $field_id ); ?>]"
			value="<?php echo esc_attr( $opt_val ); ?>"
			class="clefa-radio"
			<?php checked( $is_sel ); ?>
			<?php if ( $i === 0 && ! empty( $field['required'] ) ) : ?>required aria-required="true"<?php endif; ?>
		/>
		<span class="clefa-radio-indicator"></span>
		<span class="clefa-radio-text"><?php echo esc_html( $opt_label ); ?></span>
	</label>
	<?php endforeach; ?>
</div>
