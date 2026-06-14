<?php
/**
 * Simulates real admin builder REST flows: create → save → reload → publish.
 */

class CLEFA_Builder_Admin_Flow_Test_Runner {

	private CLEFA_Forms_Route $route;

	public function __construct() {
		require_once CLEFA_PLUGIN_PATH . 'includes/REST/REST_Helpers.php';
		require_once CLEFA_PLUGIN_PATH . 'includes/REST/Forms_Route.php';
		$this->route = new CLEFA_Forms_Route();
	}

	public function create_form( array $overrides = array() ) {
		$payload = array_merge(
			array(
				'form_name' => 'Programmatic Form',
				'form_type' => 'standard',
			),
			$overrides
		);

		$request = new WP_REST_Request();
		$request->set_body( wp_json_encode( $payload ) );

		return $this->unwrap( $this->route->create_form( $request ) );
	}

	public function save_form( int $form_id, array $config, array $overrides = array() ) {
		$payload = array_merge(
			array(
				'form_name' => $config['form_name'] ?? 'Programmatic Form',
				'config'    => $config,
			),
			$overrides
		);

		$request = new WP_REST_Request();
		$request->set_param( 'id', $form_id );
		$request->set_body( wp_json_encode( $payload ) );

		return $this->unwrap( $this->route->save_form( $request ) );
	}

	public function get_form( int $form_id ) {
		$request = new WP_REST_Request();
		$request->set_param( 'id', $form_id );

		return $this->unwrap( $this->route->get_form( $request ) );
	}

	public function publish_form( int $form_id ) {
		$request = new WP_REST_Request();
		$request->set_param( 'id', $form_id );

		return $this->unwrap( $this->route->publish_form( $request ) );
	}

	public function duplicate_form( int $form_id ) {
		$request = new WP_REST_Request();
		$request->set_param( 'id', $form_id );

		return $this->unwrap( $this->route->duplicate_form( $request ) );
	}

	public function builder_payload_from_config( array $config ): array {
		return array(
			'form_name' => $config['form_name'] ?? 'Programmatic Form',
			'config'    => array(
				'form_name'     => $config['form_name'] ?? 'Programmatic Form',
				'form_type'     => $config['form_type'] ?? 'standard',
				'description'   => $config['description'] ?? '',
				'steps'         => $config['steps'] ?? array(),
				'settings'      => $config['settings'] ?? array(),
				'notifications' => $config['notifications'] ?? array(),
				'actions'       => $config['actions'] ?? array(),
			),
		);
	}

	public function simulate_new_form_save( array $config ): array {
		$create = $this->create_form(
			array(
				'form_name' => $config['form_name'] ?? 'Programmatic Form',
				'form_type' => $config['form_type'] ?? 'standard',
			)
		);

		$form_id = (int) ( $create['form_id'] ?? 0 );
		$saved   = $this->save_form( $form_id, $config );

		return array(
			'create' => $create,
			'save'   => $saved,
			'form'   => $this->get_form( $form_id ),
		);
	}

	public function simulate_existing_form_save( int $form_id, array $config ): array {
		return array(
			'save' => $this->save_form( $form_id, $config ),
			'form' => $this->get_form( $form_id ),
		);
	}

	private function unwrap( $response ): array {
		if ( is_wp_error( $response ) ) {
			return array(
				'_error'  => true,
				'code'    => $response->get_error_code(),
				'message' => $response->get_error_message(),
				'data'    => $response->get_error_data(),
			);
		}

		$data = rest_ensure_response( $response );
		if ( is_object( $data ) && method_exists( $data, 'get_data' ) ) {
			return $data->get_data();
		}

		return is_array( $data ) ? $data : array();
	}
}
