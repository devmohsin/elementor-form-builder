<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST arg validator — WP passes ( $value, $request, $param ); do not use bare is_numeric().
 *
 * @param mixed $param Route parameter value.
 * @return bool
 */
function clefa_rest_validate_numeric_param( $param ) {
	return is_numeric( $param );
}
