<?php
/**
 * Field template: section heading
 *
 * Available vars: $field, $form, $form_id, $step_id, $value, $errors
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

$label       = $field['label']       ?? '';
$description = $field['description'] ?? '';
$allowed_tags = array( 'h2', 'h3', 'h4', 'h5', 'h6', 'p' );
$tag          = in_array( $field['heading_tag'] ?? '', $allowed_tags, true )
                ? $field['heading_tag']
                : 'h3';
?>
<div class="clefa-section-heading">
	<?php if ( $label ) : ?>
	<<?php echo $tag; ?> class="clefa-section-heading-title">
		<?php echo wp_kses_post( $label ); ?>
	</<?php echo $tag; ?>>
	<?php endif; ?>
	<?php if ( $description ) : ?>
	<p class="clefa-section-heading-desc"><?php echo wp_kses_post( $description ); ?></p>
	<?php endif; ?>
</div>
