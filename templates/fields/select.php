<?php
/**
 * Field template: select
 *
 * Available vars: $field, $form, $form_id, $step_id, $value, $errors
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

$field_id    = $field['field_id'] ?? '';
$options     = $field['options']  ?? array();
$multiple    = ! empty( $field['multiple'] );
$use_select2 = ! empty( $field['use_select2'] );
$placeholder = $field['placeholder'] ?? __( '— Select —', 'codelinden-elementor-form-addon' );
$has_error   = ! empty( $errors[ $field_id ] );
$selected    = is_array( $value ) ? $value : array( (string) $value );
$name        = $multiple ? 'clefa_field[' . esc_attr( $field_id ) . '][]' : 'clefa_field[' . esc_attr( $field_id ) . ']';
$select_cls  = 'clefa-input clefa-select' . ( $use_select2 ? ' clefa-select2-input' : '' ) . ( $has_error ? ' clefa-input-error' : '' );
?>
<select
	id="clefa-field-<?php echo esc_attr( $field_id ); ?>"
	name="<?php echo esc_attr( $name ); ?>"
	data-clefa-input
	data-clefa-field-id="<?php echo esc_attr( $field_id ); ?>"
	class="<?php echo esc_attr( $select_cls ); ?>"
	<?php if ( $use_select2 ) : ?>data-clefa-select2 data-clefa-select2-placeholder="<?php echo esc_attr( $placeholder ); ?>"<?php endif; ?>
	<?php if ( $multiple ) : ?>multiple<?php endif; ?>
	<?php if ( ! empty( $field['required'] ) ) : ?>required aria-required="true"<?php endif; ?>
	<?php if ( $has_error ) : ?>aria-describedby="clefa-error-<?php echo esc_attr( $field_id ); ?>" aria-invalid="true"<?php endif; ?>
	<?php if ( ! empty( $field['max_items'] ) && $multiple ) : ?>data-clefa-select2-max="<?php echo absint( $field['max_items'] ); ?>"<?php endif; ?>
>
	<?php if ( ! $multiple ) : ?>
	<option value=""><?php echo esc_html( $placeholder ); ?></option>
	<?php endif; ?>
	<?php foreach ( $options as $opt ) :
		if ( is_string( $opt ) ) {
			$opt_val   = $opt;
			$opt_label = $opt;
		} else {
			$raw_val   = isset( $opt['value'] ) ? (string) $opt['value'] : '';
			$raw_label = isset( $opt['label'] ) ? (string) $opt['label'] : '';
			// If label is an auto-generated default like "Option 1", treat it as absent and use value.
			$is_auto_label = (bool) preg_match( '/^Option\s*\d+$/i', $raw_label );
			$opt_label = ( $raw_label !== '' && ! $is_auto_label ) ? $raw_label : ( $raw_val !== '' ? $raw_val : $raw_label );
			$opt_val   = $raw_val !== '' ? $raw_val : $opt_label;
		}
		$is_sel    = in_array( (string) $opt_val, $selected, true );
	?>
	<option value="<?php echo esc_attr( $opt_val ); ?>"<?php selected( $is_sel ); ?>><?php echo esc_html( $opt_label ); ?></option>
	<?php endforeach; ?>
</select>
