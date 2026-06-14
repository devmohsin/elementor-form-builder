<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once CLEFA_PLUGIN_PATH . 'includes/Actions/Abstract_Action.php';

class CLEFA_Send_Email_Action extends CLEFA_Abstract_Action {

	public function run( array $data, array $form_config, $submission_id, array $action_config = array() ) {
		$r = $this->resolve_all_tokens( $action_config, $data, $form_config, $submission_id );

		$to      = sanitize_email( $r['to'] ?? get_option( 'admin_email' ) );
		$subject = sanitize_text_field( $r['subject'] ?? __( 'New form submission', 'codelinden-elementor-form-addon' ) );
		$body    = wp_kses_post( $r['body'] ?? $this->default_body( $data, $form_config ) );

		$is_html   = ! empty( $action_config['is_html'] );
		$reply_to  = sanitize_email( $r['reply_to'] ?? '' );
		$from_name = sanitize_text_field( $r['from_name'] ?? get_bloginfo( 'name' ) );
		$from_email= sanitize_email( $r['from_email'] ?? get_option( 'admin_email' ) );
		$cc        = sanitize_text_field( $r['cc']  ?? '' );
		$bcc       = sanitize_text_field( $r['bcc'] ?? '' );

		$headers = array(
			'From: ' . $from_name . ' <' . $from_email . '>',
		);

		if ( $is_html ) {
			$headers[] = 'Content-Type: text/html; charset=UTF-8';
		}
		if ( $reply_to ) {
			$headers[] = 'Reply-To: ' . $reply_to;
		}
		if ( $cc ) {
			$headers[] = 'Cc: ' . $cc;
		}
		if ( $bcc ) {
			$headers[] = 'Bcc: ' . $bcc;
		}

		$headers = apply_filters( 'clefa_send_email_headers', $headers, $action_config, $data, $form_config );
		$to      = apply_filters( 'clefa_send_email_to',      $to,      $action_config, $data, $form_config );

		$result = wp_mail( $to, $subject, $body, $headers );

		return array( 'success' => $result );
	}

	private function default_body( array $data, array $form_config ) {
		$lines = array();
		foreach ( ( $form_config['steps'] ?? array() ) as $step ) {
			foreach ( ( $step['fields'] ?? array() ) as $field ) {
				$field_id = $field['field_id']  ?? '';
				$label    = $field['label']     ?? $field_id;
				$value    = $data[ $field_id ] ?? '';
				if ( is_array( $value ) ) { $value = implode( ', ', $value ); }
				$lines[] = esc_html( $label ) . ': ' . esc_html( (string) $value );
			}
		}
		return implode( "\n", $lines );
	}
}
