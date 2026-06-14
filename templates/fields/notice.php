<?php
/**
 * Field template: notice / alert box
 *
 * Available vars: $field, $form, $form_id, $step_id, $value, $errors
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

$content  = $field['content']    ?? '';
$type     = $field['notice_type'] ?? 'info';
$allowed  = array( 'info', 'success', 'warning', 'error' );
$type     = in_array( $type, $allowed, true ) ? $type : 'info';
$icon_map = array( 'info' => 'ℹ', 'success' => '✓', 'warning' => '⚠', 'error' => '✕' );
$icon     = $icon_map[ $type ];
?>
<?php if ( $content ) : ?>
<div
	class="clefa-notice clefa-notice-<?php echo esc_attr( $type ); ?>"
	data-clefa-notice="<?php echo esc_attr( $type ); ?>"
	role="<?php echo 'error' === $type || 'warning' === $type ? 'alert' : 'note'; ?>"
>
	<span class="clefa-notice-icon" aria-hidden="true"><?php echo esc_html( $icon ); ?></span>
	<div class="clefa-notice-content"><?php echo wp_kses_post( $content ); ?></div>
</div>
<?php endif; ?>
