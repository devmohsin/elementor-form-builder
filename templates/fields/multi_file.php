<?php
/**
 * Field template: multi-file upload (re-uses file.php structure with multiple enabled)
 *
 * Available vars: $field, $form, $form_id, $step_id, $value, $errors
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

$field['multiple_files'] = true;
include CLEFA_Form_Renderer::locate_template( 'fields/file.php' );
