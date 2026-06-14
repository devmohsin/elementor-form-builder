<?php
/**
 * Field template: repeater
 *
 * A repeater renders N copies of its sub-fields. Each row contains all
 * sub-fields; rows can be added/removed dynamically via JS.
 *
 * Available vars: $field, $form, $form_id, $step_id, $value, $errors
 *
 * $field['sub_fields']  array  Array of field config arrays (same shape as top-level fields).
 * $field['min_rows']    int    Minimum rows (default 1).
 * $field['max_rows']    int    Maximum rows (default 10, 0 = unlimited).
 * $field['add_text']    string Button label for "Add Row".
 * $field['remove_text'] string Button label for "Remove Row".
 * $field['layout']      string 'stack' | 'inline' (default 'stack').
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$field_id   = $field['field_id'] ?? '';
$sub_fields = $field['sub_fields'] ?? array();
$min_rows   = max( 0, absint( $field['min_rows'] ?? 1 ) );
$max_rows   = absint( $field['max_rows'] ?? 10 );   // 0 = unlimited
$add_text   = esc_html( $field['add_text']    ?? __( '+ Add Row', 'codelinden-elementor-form-addon' ) );
$remove_text= esc_html( $field['remove_text'] ?? __( '✕', 'codelinden-elementor-form-addon' ) );
$layout     = in_array( $field['layout'] ?? 'stack', array( 'stack', 'inline' ), true ) ? ( $field['layout'] ?? 'stack' ) : 'stack';
$has_error  = ! empty( $errors[ $field_id ] );

// Seed initial rows from submitted $value (array of rows), defaulting to $min_rows rows
$existing_rows = array();
if ( is_array( $value ) && ! empty( $value ) ) {
	$existing_rows = array_values( $value );
} else {
	for ( $i = 0; $i < max( 1, $min_rows ); $i++ ) {
		$existing_rows[] = array();
	}
}
?>
<div
	class="clefa-repeater"
	data-clefa-repeater="<?php echo esc_attr( $field_id ); ?>"
	data-clefa-repeater-min="<?php echo esc_attr( $min_rows ); ?>"
	data-clefa-repeater-max="<?php echo esc_attr( $max_rows ); ?>"
	data-clefa-repeater-layout="<?php echo esc_attr( $layout ); ?>"
>
	<?php /* Hidden row-count sync input (also triggers validation) */ ?>
	<input
		type="hidden"
		data-clefa-input
		data-clefa-field-id="<?php echo esc_attr( $field_id ); ?>"
		name="clefa_field[<?php echo esc_attr( $field_id ); ?>][_row_count]"
		data-clefa-repeater-count
		value="<?php echo count( $existing_rows ); ?>"
	/>

	<?php /* Rows */ ?>
	<div class="clefa-repeater-rows" data-clefa-repeater-rows>
		<?php foreach ( $existing_rows as $row_index => $row_data ) : ?>
		<div
			class="clefa-repeater-row"
			data-clefa-repeater-row
			data-clefa-row-index="<?php echo esc_attr( $row_index ); ?>"
		>
			<div class="clefa-repeater-row-fields clefa-repeater-layout-<?php echo esc_attr( $layout ); ?>">
				<?php foreach ( $sub_fields as $sub_field ) :
					$sfid     = $sub_field['field_id'] ?? '';
					$sftype   = sanitize_key( $sub_field['field_type'] ?? 'text' );
					$sf_val   = $row_data[ $sfid ] ?? ( $sub_field['default_value'] ?? '' );
					$sf_err   = array();
					$has_label= ! empty( $sub_field['label'] ) && ! in_array( $sftype, array( 'hidden', 'html' ), true );

					// Override field_id with row-indexed name for sub-fields
					$indexed_field = array_merge( $sub_field, array(
						'field_id' => $field_id . '[' . $row_index . '][' . $sfid . ']',
					) );
					$tpl = CLEFA_Form_Renderer::locate_field_template( $sftype );
				?>
				<div
					class="clefa-repeater-sub-field clefa-field-wrap clefa-field-wrap-<?php echo esc_attr( $sftype ); ?>"
					data-clefa-repeater-sub-field="<?php echo esc_attr( $sfid ); ?>"
				>
					<?php if ( $has_label ) : ?>
					<label
						for="clefa-field-<?php echo esc_attr( $field_id . '-' . $row_index . '-' . $sfid ); ?>"
						class="clefa-field-label"
					><?php echo esc_html( $sub_field['label'] ); ?></label>
					<?php endif; ?>

					<div class="clefa-input-wrap">
						<?php if ( $tpl ) :
							ob_start();
							// phpcs:ignore WordPress.PHP.DontExtract.extract_extract
							extract( array(
								'field'   => $indexed_field,
								'form'    => $form,
								'form_id' => $form_id,
								'step_id' => $step_id,
								'value'   => $sf_val,
								'errors'  => $sf_err,
							), EXTR_OVERWRITE );
							include $tpl;
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo ob_get_clean();
						endif; ?>
					</div>
				</div>
				<?php endforeach; ?>
			</div>

			<?php if ( count( $existing_rows ) > $min_rows ) : ?>
			<button
				type="button"
				class="clefa-btn clefa-btn-ghost clefa-repeater-remove-row"
				data-clefa-repeater-remove
				aria-label="<?php esc_attr_e( 'Remove row', 'codelinden-elementor-form-addon' ); ?>"
			><?php echo $remove_text; ?></button>
			<?php endif; ?>
		</div>
		<?php endforeach; ?>
	</div>

	<?php /* Template row (hidden, cloned by JS) */ ?>
	<script type="text/template" data-clefa-repeater-template>
	<?php
		// Render a template row (index __ROW_INDEX__ replaced by JS)
		$tpl_row_index = '__ROW_INDEX__';
	?>
	<div class="clefa-repeater-row" data-clefa-repeater-row data-clefa-row-index="<?php echo esc_attr( $tpl_row_index ); ?>">
		<div class="clefa-repeater-row-fields clefa-repeater-layout-<?php echo esc_attr( $layout ); ?>">
			<?php foreach ( $sub_fields as $sub_field ) :
				$sfid   = $sub_field['field_id'] ?? '';
				$sftype = sanitize_key( $sub_field['field_type'] ?? 'text' );
				$indexed_field_tpl = array_merge( $sub_field, array(
					'field_id'      => $field_id . '[' . $tpl_row_index . '][' . $sfid . ']',
					'default_value' => '',
				) );
				$tpl2 = CLEFA_Form_Renderer::locate_field_template( $sftype );
				$has_lbl = ! empty( $sub_field['label'] ) && ! in_array( $sftype, array( 'hidden', 'html' ), true );
			?>
			<div class="clefa-repeater-sub-field clefa-field-wrap clefa-field-wrap-<?php echo esc_attr( $sftype ); ?>"
				data-clefa-repeater-sub-field="<?php echo esc_attr( $sfid ); ?>">
				<?php if ( $has_lbl ) : ?>
				<label class="clefa-field-label"><?php echo esc_html( $sub_field['label'] ); ?></label>
				<?php endif; ?>
				<div class="clefa-input-wrap">
					<?php if ( $tpl2 ) :
						ob_start();
						// phpcs:ignore WordPress.PHP.DontExtract.extract_extract
						extract( array( 'field' => $indexed_field_tpl, 'form' => $form, 'form_id' => $form_id, 'step_id' => $step_id, 'value' => '', 'errors' => array() ), EXTR_OVERWRITE );
						include $tpl2;
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						echo ob_get_clean();
					endif; ?>
				</div>
			</div>
			<?php endforeach; ?>
		</div>
		<button type="button" class="clefa-btn clefa-btn-ghost clefa-repeater-remove-row" data-clefa-repeater-remove
			aria-label="<?php esc_attr_e( 'Remove row', 'codelinden-elementor-form-addon' ); ?>"><?php echo $remove_text; ?></button>
	</div>
	</script>

	<div class="clefa-repeater-footer">
		<button
			type="button"
			class="clefa-btn clefa-btn-secondary clefa-repeater-add-row"
			data-clefa-repeater-add
			<?php if ( $max_rows > 0 && count( $existing_rows ) >= $max_rows ) : ?>disabled<?php endif; ?>
		><?php echo $add_text; ?></button>
	</div>

	<?php if ( $has_error ) : ?>
	<div class="clefa-field-error" style="display:block"><?php echo esc_html( $errors[ $field_id ] ?? '' ); ?></div>
	<?php endif; ?>
</div>
