<?php
/**
 * Step wrapper template.
 *
 * Variables from form.php:
 * @var array  $step        Step config array.
 * @var int    $step_index  Zero-based step index.
 * @var string $step_id     Step ID attribute (already sanitised).
 * @var bool   $is_last     Whether this is the final step.
 * @var int    $step_count  Total number of steps.
 * @var array  $form        Full form row from DB.
 * @var array  $config      Decoded form config.
 * @var int    $form_id     Form ID.
 * @var string $instance_id Render instance UUID.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<div
	class="clefa-step<?php echo ! empty( $step['custom_class'] ) ? ' ' . esc_attr( $step['custom_class'] ) : ''; ?>"
	data-clefa-step="<?php echo esc_attr( $step_id ); ?>"
	data-clefa-step-index="<?php echo esc_attr( $step_index ); ?>"
	data-clefa-step-active="<?php echo $step_index === 0 ? '1' : '0'; ?>"
	<?php if ( $step_index > 0 ) : ?>style="display:none" aria-hidden="true"<?php endif; ?>
	<?php if ( ! empty( $step['button_mode'] ) ) : ?>data-clefa-btn-mode="<?php echo esc_attr( $step['button_mode'] ); ?>"<?php endif; ?>
	<?php
		if ( ! empty( $step['custom_attributes'] ) ) {
			foreach ( (array) $step['custom_attributes'] as $attr ) {
				$key = sanitize_key( $attr['key'] ?? '' );
				$val = esc_attr( $attr['value'] ?? '' );
				if ( $key ) { echo ' ' . $key . '="' . $val . '"'; }
			}
		}
	?>
>
	<?php do_action( 'clefa_before_step_fields', $step_id, $step, $form_id, $config ); ?>

	<?php if ( ! empty( $step['step_notice_before'] ) ) : ?>
	<div class="clefa-step-notice clefa-step-notice-before">
		<?php echo wp_kses_post( $step['step_notice_before'] ); ?>
	</div>
	<?php endif; ?>

	<?php if ( ! empty( $step['step_heading'] ) ) : ?>
	<h2 class="clefa-step-heading"><?php echo esc_html( $step['step_heading'] ); ?></h2>
	<?php endif; ?>

	<?php if ( ! empty( $step['step_description'] ) ) : ?>
	<p class="clefa-step-description"><?php echo esc_html( $step['step_description'] ); ?></p>
	<?php endif; ?>

	<div class="clefa-fields-wrap">
		<?php
		foreach ( ( $step['fields'] ?? array() ) as $field ) :
			$field = apply_filters( 'clefa_field_config', $field, $form_id );
			$fid   = esc_attr( $field['field_id']  ?? '' );
			$ftype = sanitize_key( $field['field_type'] ?? 'text' );
			$value = $field['default_value'] ?? '';
			$errors= array();

			$wrapper_tpl = CLEFA_Form_Renderer::locate_template( 'field-wrapper.php' );
			if ( $wrapper_tpl ) {
				// phpcs:ignore WordPress.PHP.DontExtract.extract_extract
				extract( array(
					'field'   => $field,
					'fid'     => $fid,
					'ftype'   => $ftype,
					'value'   => $value,
					'errors'  => $errors,
					'form'    => $form,
					'form_id' => $form_id,
					'step_id' => $step_id,
					'config'  => $config,
				), EXTR_OVERWRITE );
				include $wrapper_tpl;
			} else {
				// Fallback: render the field without wrapper (template not found)
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo CLEFA_Form_Renderer::render_field( $field, $form, $step_id, $value, $errors );
			}
		endforeach;
		?>
	</div>

	<?php if ( ! empty( $step['step_notice_after'] ) ) : ?>
	<div class="clefa-step-notice clefa-step-notice-after">
		<?php echo wp_kses_post( $step['step_notice_after'] ); ?>
	</div>
	<?php endif; ?>

	<?php if ( $is_last && ! empty( $show_remember_me ) ) : ?>
	<div class="clefa-field-wrap clefa-remember-me-wrap">
		<div class="clefa-checkbox-group">
			<label class="clefa-checkbox-label clefa-checkbox-label-single" for="clefa-field-remember-me">
				<input
					type="checkbox"
					id="clefa-field-remember-me"
					name="clefa_field[_clefa_remember_me]"
					value="1"
					data-clefa-input
					data-clefa-field-id="_clefa_remember_me"
					class="clefa-checkbox"
				/>
				<span class="clefa-checkbox-indicator"></span>
				<span class="clefa-checkbox-text"><?php echo esc_html( $remember_me_label ?? 'Remember Me' ); ?></span>
			</label>
		</div>
	</div>
	<?php endif; ?>

	<?php
	$buttons_tpl = CLEFA_Form_Renderer::locate_template( 'buttons.php' );
	if ( $buttons_tpl ) {
		include $buttons_tpl;
	} else {
		?>
	<div class="clefa-step-buttons">
		<?php if ( $step_index > 0 ) : ?>
		<button type="button" class="clefa-btn clefa-btn-secondary clefa-btn-prev" data-clefa-prev>
			<?php echo esc_html( $step['prev_button_text'] ?? __( '← Previous', 'codelinden-elementor-form-addon' ) ); ?>
		</button>
		<?php endif; ?>
		<?php if ( ! $is_last ) : ?>
		<button type="button" class="clefa-btn clefa-btn-primary clefa-btn-next" data-clefa-next>
			<?php echo esc_html( $step['next_button_text'] ?? __( 'Next →', 'codelinden-elementor-form-addon' ) ); ?>
		</button>
		<?php else : ?>
		<button type="submit" class="clefa-btn clefa-btn-primary clefa-btn-submit" data-clefa-submit>
			<?php echo esc_html( $step['submit_button_text'] ?? __( 'Submit', 'codelinden-elementor-form-addon' ) ); ?>
		</button>
		<?php endif; ?>
	</div>
	<?php } ?>

	<?php do_action( 'clefa_after_step_fields', $step_id, $step, $form_id, $config ); ?>
</div>
