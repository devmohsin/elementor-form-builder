<?php
/**
 * Field template: file upload
 *
 * Available vars: $field, $form, $form_id, $step_id, $value, $errors
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

$field_id     = $field['field_id']    ?? '';
$has_error    = ! empty( $errors[ $field_id ] );
$allowed_ext  = $field['allowed_types'] ?? CLEFA_Settings_Page::get( 'upload_allowed_types', 'jpg,jpeg,png,pdf' );
$max_size     = absint( $field['max_file_size'] ?? CLEFA_Settings_Page::get( 'upload_max_size', 5 ) );
$multiple     = ! empty( $field['multiple_files'] );
$accept_attr  = '';
if ( $allowed_ext ) {
	$exts = array_map( 'trim', explode( ',', $allowed_ext ) );
	$accept_attr = implode( ',', array_map( function( $ext ) { return '.' . $ext; }, $exts ) );
}
?>
<div
	class="clefa-file-upload-wrap<?php echo $has_error ? ' clefa-field-error' : ''; ?>"
	data-clefa-file-wrap
	data-clefa-field-id="<?php echo esc_attr( $field_id ); ?>"
	data-clefa-max-size="<?php echo esc_attr( $max_size ); ?>"
>
	<input
		type="file"
		id="clefa-field-<?php echo esc_attr( $field_id ); ?>"
		name="clefa_field[<?php echo esc_attr( $field_id ); ?>]<?php echo $multiple ? '[]' : ''; ?>"
		data-clefa-input
		data-clefa-field-id="<?php echo esc_attr( $field_id ); ?>"
		class="clefa-input-file"
		<?php if ( $accept_attr ) : ?>accept="<?php echo esc_attr( $accept_attr ); ?>"<?php endif; ?>
		<?php if ( $multiple ) : ?>multiple<?php endif; ?>
		<?php if ( ! empty( $field['required'] ) ) : ?>required aria-required="true"<?php endif; ?>
		<?php if ( $has_error ) : ?>aria-describedby="clefa-error-<?php echo esc_attr( $field_id ); ?>" aria-invalid="true"<?php endif; ?>
	/>
	<div class="clefa-file-drop-zone" data-clefa-drop-zone aria-hidden="true">
		<span class="clefa-file-drop-icon">&#8593;</span>
		<span class="clefa-file-drop-text">
			<?php
			printf(
				/* translators: 1: Click link, 2: allowed file types */
				esc_html__( '%1$s or drag & drop. Allowed: %2$s. Max: %3$sMB', 'codelinden-elementor-form-addon' ),
				'<span class="clefa-file-click">' . esc_html__( 'Click to upload', 'codelinden-elementor-form-addon' ) . '</span>',
				esc_html( $allowed_ext ),
				esc_html( $max_size )
			);
			?>
		</span>
	</div>
	<ul class="clefa-file-list" data-clefa-file-list aria-live="polite"></ul>
</div>
