<?php
/**
 * Field template: checkbox
 *
 * Available vars: $field, $form, $form_id, $step_id, $value, $errors
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

$field_id  = $field['field_id'] ?? '';
$options   = $field['options']  ?? array();
$has_error = ! empty( $errors[ $field_id ] );
$selected  = is_array( $value ) ? $value : ( '' !== $value ? array( (string) $value ) : array() );
$inline    = ! empty( $field['inline'] );
?>
<div
	class="clefa-checkbox-group<?php echo $inline ? ' clefa-checkbox-group-inline' : ''; ?><?php echo $has_error ? ' clefa-field-error' : ''; ?>"
	data-clefa-input
	data-clefa-field-id="<?php echo esc_attr( $field_id ); ?>"
	role="group"
	aria-labelledby="clefa-label-<?php echo esc_attr( $field_id ); ?>"
	<?php if ( $has_error ) : ?>aria-describedby="clefa-error-<?php echo esc_attr( $field_id ); ?>"<?php endif; ?>
>
	<?php if ( ! empty( $options ) ) : ?>
		<?php foreach ( $options as $i => $opt ) :
			$opt_val   = $opt['value'] ?? $opt['label'] ?? '';
			$opt_label = $opt['label'] ?? $opt_val;
			$is_chk    = in_array( (string) $opt_val, $selected, true );
			$input_id  = 'clefa-field-' . esc_attr( $field_id ) . '-' . $i;
		?>
		<label class="clefa-checkbox-label" for="<?php echo esc_attr( $input_id ); ?>">
			<input
				type="checkbox"
				id="<?php echo esc_attr( $input_id ); ?>"
				name="clefa_field[<?php echo esc_attr( $field_id ); ?>][]"
				value="<?php echo esc_attr( $opt_val ); ?>"
				class="clefa-checkbox"
				<?php checked( $is_chk ); ?>
				<?php if ( $i === 0 && ! empty( $field['required'] ) ) : ?>required aria-required="true"<?php endif; ?>
			/>
			<span class="clefa-checkbox-indicator"></span>
			<span class="clefa-checkbox-text"><?php echo esc_html( $opt_label ); ?></span>
		</label>
		<?php endforeach; ?>
	<?php else : ?>
		<?php // Single acceptance checkbox (e.g. terms)
			$is_chk = in_array( '1', $selected, true ) || in_array( 'true', $selected, true );
		?>
		<label class="clefa-checkbox-label clefa-checkbox-label-single" for="clefa-field-<?php echo esc_attr( $field_id ); ?>">
			<input
				type="checkbox"
				id="clefa-field-<?php echo esc_attr( $field_id ); ?>"
				name="clefa_field[<?php echo esc_attr( $field_id ); ?>]"
				value="1"
				class="clefa-checkbox"
				<?php checked( $is_chk ); ?>
				<?php if ( ! empty( $field['required'] ) ) : ?>required aria-required="true"<?php endif; ?>
			/>
			<span class="clefa-checkbox-indicator"></span>
			<?php if ( ! empty( $field['acceptance_text'] ) ) : ?>
			<span class="clefa-checkbox-text"><?php echo wp_kses_post( $field['acceptance_text'] ); ?></span>
			<?php endif; ?>
		</label>
	<?php endif; ?>
</div>
