<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

// $form, $config, $form_id, $instance_id are extracted by Form_Renderer::render()
if ( empty( $form ) ) { return; }

$form_id_attr  = absint( $form_id );
$form_uuid     = esc_attr( $form['form_uuid'] ?? '' );
$instance_attr = esc_attr( $instance_id );
$settings      = $config['settings'] ?? array();
$step_count    = count( $config['steps'] ?? array() );
$has_steps     = $step_count > 1;
$hide_on_success = ! empty( $settings['hide_form_on_success'] );
$reset_on_success= isset( $settings['reset_on_success'] ) ? (int) (bool) $settings['reset_on_success'] : 1;
$enable_transitions = ! isset( $settings['enable_transitions'] ) || ! empty( $settings['enable_transitions'] );
$form_theme    = sanitize_key( $config['settings']['form_theme'] ?? $config['form_theme'] ?? '' );

// Build inline CSS overrides from custom_styles
$css_var_map = array(
	'primary_color' => '--clefa-primary',
	'bg_color'      => '--clefa-bg',
	'input_bg'      => '--clefa-input-bg',
	'border_color'  => '--clefa-border',
	'text_color'    => '--clefa-text',
	'muted_color'   => '--clefa-text-muted',
	'label_color'   => '--clefa-label-color',
	'error_color'   => '--clefa-error',
	'radius'        => '--clefa-radius',
	'shadow'        => '--clefa-shadow-sm',
	'label_weight'  => '--clefa-label-weight',
	'label_size'    => '--clefa-label-size',
	'input_padding' => '--clefa-input-padding',
);
$custom_styles = is_array( $settings['custom_styles'] ?? null ) ? $settings['custom_styles'] : array();
$inline_vars   = array();
foreach ( $css_var_map as $key => $var ) {
	$val = $custom_styles[ $key ] ?? '';
	if ( '' === $val ) {
		continue;
	}
	// Radius: append 'px' if numeric
	if ( 'radius' === $key && is_numeric( $val ) ) {
		$val = intval( $val ) . 'px';
	}
	$inline_vars[] = esc_attr( $var ) . ':' . esc_attr( $val );
}
// Also apply bg_color as a proper background property
$inline_style = $inline_vars ? implode( ';', $inline_vars ) . ';' : '';

$form_type       = $config['form_type'] ?? 'standard';
$error_placement = $settings['error_placement'] ?? 'below';
$success_delay   = ( 'login' === $form_type ) ? absint( $settings['login_success_delay'] ?? 2 ) : 0;

// Encode config for JS (only the parts needed: steps, conditions, validation)
$js_config = wp_json_encode( array(
	'steps' => array_map( function( $step ) {
		return array(
			'step_id'   => $step['step_id']   ?? '',
			'step_name' => $step['step_name'] ?? '',
			'fields'    => array_map( function( $f ) {
				$fd = array(
					'field_id'         => $f['field_id']        ?? '',
					'field_type'       => $f['field_type']       ?? 'text',
					'label'            => $f['label']            ?? '',
					'required'         => ! empty( $f['required'] ),
					'validation_rules' => $f['validation_rules'] ?? array(),
					'conditions'       => $f['conditions']       ?? array(),
				);
				// Pass sub-field condition metadata so ConditionEngine can
				// evaluate per-row visibility for repeater sub-fields.
				if ( 'repeater' === ( $f['field_type'] ?? '' ) && ! empty( $f['sub_fields'] ) ) {
					$fd['sub_fields'] = array_map( function( $sf ) {
						return array(
							'field_id'   => $sf['field_id']  ?? '',
							'conditions' => $sf['conditions'] ?? array(),
						);
					}, $f['sub_fields'] );
				}
				return $fd;
			}, $step['fields'] ?? array() ),
			'routing' => $step['routing'] ?? array(),
		);
	}, $config['steps'] ?? array() ),
) );
?>
<div
	class="clefa-form-wrap"
	data-clefa-form="<?php echo esc_attr( $form_id_attr ); ?>"
	data-clefa-form-id="<?php echo esc_attr( $form_id_attr ); ?>"
	data-clefa-form-uuid="<?php echo esc_attr( $form_uuid ); ?>"
	data-clefa-instance="<?php echo esc_attr( $instance_attr ); ?>"
	data-clefa-hide-on-success="<?php echo $hide_on_success ? '1' : '0'; ?>"
	data-clefa-reset-on-success="<?php echo esc_attr( $reset_on_success ); ?>"
	data-clefa-transitions="<?php echo $enable_transitions ? '1' : '0'; ?>"
	data-clefa-persist-draft="<?php echo ! empty( $settings['persist_draft'] ) ? '1' : '0'; ?>"
	data-clefa-form-type="<?php echo esc_attr( $form_type ); ?>"
	data-clefa-success-delay="<?php echo esc_attr( $success_delay ); ?>"
	data-clefa-config="<?php echo esc_attr( $js_config ); ?>"
	<?php if ( $form_theme ) : ?>data-clefa-theme="<?php echo esc_attr( $form_theme ); ?>"<?php endif; ?>
	<?php if ( $inline_style ) : ?>style="<?php echo $inline_style; ?>"<?php endif; ?>
>

	<?php do_action( 'clefa_before_form_fields', $form_id_attr, $config ); ?>

	<?php if ( 'above' === $error_placement ) : ?>
	<div
		class="clefa-form-message"
		data-clefa-message
		role="status"
		aria-live="polite"
		style="display:none"
	></div>
	<?php endif; ?>

	<?php if ( $has_steps ) : ?>
	<div class="clefa-progress" data-clefa-progress aria-label="<?php esc_attr_e( 'Form progress', 'codelinden-elementor-form-addon' ); ?>">
		<div class="clefa-progress-bar-wrap">
			<div class="clefa-progress-bar" data-clefa-progress-bar role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" style="width:0%"></div>
		</div>
		<span class="clefa-step-count" data-clefa-step-count aria-live="polite">1 / <?php echo esc_html( $step_count ); ?></span>
	</div>
	<?php endif; ?>

	<form
		class="clefa-form"
		data-clefa-form-inner
		novalidate
		action=""
		method="post"
	>
	<?php
	foreach ( ( $config['steps'] ?? array() ) as $step_index => $step ) :
		$is_last  = ( $step_index === $step_count - 1 );
		$step_id  = esc_attr( $step['step_id'] ?? 'step_' . $step_index );
		$step_tpl = CLEFA_Form_Renderer::locate_template( 'step.php' );
		if ( $step_tpl ) { include $step_tpl; }
	endforeach;
	?>

		<?php if ( ! empty( $settings['enable_antispam'] ) ) : ?>
		<div class="clefa-hp" aria-hidden="true" style="position:absolute;left:-9999px;opacity:0;height:0;overflow:hidden;pointer-events:none" tabindex="-1">
			<label for="clefa-hp-<?php echo esc_attr( $form_uuid ); ?>">
				<?php esc_html_e( 'Leave this field empty', 'codelinden-elementor-form-addon' ); ?>
			</label>
			<input
				type="text"
				id="clefa-hp-<?php echo esc_attr( $form_uuid ); ?>"
				name="clefa_hp_<?php echo esc_attr( $form_uuid ); ?>"
				value=""
				autocomplete="off"
				tabindex="-1"
			/>
		</div>
		<?php
			// Time-to-submit token: base64( timestamp:hmac )
			$ts_val = base64_encode( time() . ':' . hash_hmac( 'sha256', (string) time(), wp_salt( 'nonce' ) ) );
		?>
		<input type="hidden" name="_clefa_ts" value="<?php echo esc_attr( $ts_val ); ?>">
		<?php endif; ?>

	</form>

	<?php if ( 'below' === $error_placement ) : ?>
	<div
		class="clefa-form-message"
		data-clefa-message
		role="status"
		aria-live="polite"
		style="display:none"
	></div>
	<?php endif; ?>

	<?php do_action( 'clefa_after_form_fields', $form_id_attr, $config ); ?>
</div>
