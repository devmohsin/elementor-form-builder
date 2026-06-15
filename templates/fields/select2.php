<?php
/**
 * Field template: select2
 *
 * Renders a select element with Select2 enhancement forced on.
 * Available vars: $field, $form, $form_id, $step_id, $value, $errors
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

$field['use_select2'] = true;

include CLEFA_TEMPLATE_PATH . 'fields/select.php';
