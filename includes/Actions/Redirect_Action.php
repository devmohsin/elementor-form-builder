<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once CLEFA_PLUGIN_PATH . 'includes/Actions/Abstract_Action.php';

class CLEFA_Redirect_Action extends CLEFA_Abstract_Action {

	public function run( array $data, array $form_config, $submission_id, array $action_config = array() ) {
		$url_template = $action_config['redirect_url'] ?? '';
		if ( empty( $url_template ) ) {
			return array( 'success' => false );
		}

		$url = $this->resolve_token( $url_template, $data, $form_config, $submission_id );
		$url = esc_url_raw( $url );
		$url = apply_filters( 'clefa_redirect_url', $url, $data, $form_config, $submission_id );

		return array(
			'success'      => true,
			'redirect_url' => $url,
		);
	}
}
