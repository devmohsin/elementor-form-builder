<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CLEFA_Config_Normalizer {

	public function normalize( array $config ) {
		$normalized = array(
			'form_name'     => sanitize_text_field( $config['form_name'] ?? '' ),
			'form_type'     => sanitize_text_field( $config['form_type'] ?? 'standard' ),
			'steps'         => array(),
			'settings'      => $this->normalize_settings( $config['settings'] ?? array() ),
			'notifications' => $this->normalize_notifications( $config['notifications'] ?? array() ),
			'actions'       => $this->normalize_actions( $config['actions'] ?? array() ),
		);

		$seen_field_ids = array();

		foreach ( ( $config['steps'] ?? array() ) as $step ) {
			$step_id = sanitize_key( $step['step_id'] ?? '' );
			if ( ! $step_id ) {
				continue;
			}

			$normalized_step = array(
				'step_id'          => $step_id,
				'step_name'        => sanitize_text_field( $step['step_name'] ?? '' ),
				'step_heading'     => sanitize_text_field( $step['step_heading'] ?? '' ),
				'step_description' => sanitize_textarea_field( $step['step_description'] ?? '' ),
				'next_button_text' => sanitize_text_field( $step['next_button_text'] ?? '' ),
				'prev_button_text' => sanitize_text_field( $step['prev_button_text'] ?? '' ),
				'submit_button_text' => sanitize_text_field( $step['submit_button_text'] ?? '' ),
				'routing'          => $step['routing'] ?? array(),
				'conditions'       => $step['conditions'] ?? array(),
				'fields'           => array(),
			);

			foreach ( ( $step['fields'] ?? array() ) as $field ) {
				$field_id = sanitize_key( $field['field_id'] ?? '' );
				if ( ! $field_id ) {
					continue;
				}
				if ( in_array( $field_id, $seen_field_ids, true ) ) {
					continue;
				}
				$seen_field_ids[] = $field_id;
				$normalized_step['fields'][] = $this->normalize_field( $field );
			}

			$normalized['steps'][] = $normalized_step;
		}

		return $normalized;
	}

	private function normalize_field( array $field ) {
		return array(
			'field_id'         => sanitize_key( $field['field_id'] ?? '' ),
			'field_type'       => sanitize_key( $field['field_type'] ?? 'text' ),
			'label'            => sanitize_text_field( $field['label'] ?? '' ),
			'placeholder'      => sanitize_text_field( $field['placeholder'] ?? '' ),
			'description'      => sanitize_text_field( $field['description'] ?? '' ),
			'default_value'    => sanitize_text_field( $field['default_value'] ?? '' ),
			'required'         => (bool) ( $field['required'] ?? false ),
			'readonly'         => (bool) ( $field['readonly'] ?? false ),
			'disabled'         => (bool) ( $field['disabled'] ?? false ),
			'hidden'           => (bool) ( $field['hidden'] ?? false ),
			'css_class'        => sanitize_text_field( $field['css_class'] ?? '' ),
			'wrapper_class'    => sanitize_text_field( $field['wrapper_class'] ?? '' ),
			'validation'       => $field['validation'] ?? array(),
			'conditions'       => $field['conditions'] ?? array(),
			'mapping'          => $field['mapping'] ?? array(),
			'options'          => $field['options'] ?? array(),
			'live_check'       => $field['live_check'] ?? array(),
			'advanced'         => $field['advanced'] ?? array(),
		);
	}

	private function normalize_settings( array $settings ) {
		return array(
			'require_login'          => (bool) ( $settings['require_login'] ?? false ),
			'allow_guest'            => (bool) ( $settings['allow_guest'] ?? true ),
			'store_submissions'      => (bool) ( $settings['store_submissions'] ?? true ),
			'enable_ajax'            => (bool) ( $settings['enable_ajax'] ?? true ),
			'enable_nonce_refresh'   => (bool) ( $settings['enable_nonce_refresh'] ?? true ),
			'enable_antispam'        => (bool) ( $settings['enable_antispam'] ?? true ),
			'enable_uploads'         => (bool) ( $settings['enable_uploads'] ?? false ),
			'enable_draft'           => (bool) ( $settings['enable_draft'] ?? false ),
			'enable_events'          => (bool) ( $settings['enable_events'] ?? true ),
			'enable_debug_console'   => (bool) ( $settings['enable_debug_console'] ?? false ),
			'redirect_url'           => esc_url_raw( $settings['redirect_url'] ?? '' ),
			'success_message'        => sanitize_text_field( $settings['success_message'] ?? '' ),
			'error_message'          => sanitize_text_field( $settings['error_message'] ?? '' ),
			'allowed_roles'          => array_map( 'sanitize_text_field', (array) ( $settings['allowed_roles'] ?? array() ) ),
			'form_theme'             => sanitize_key( $settings['form_theme'] ?? '' ),
			'custom_styles'          => $this->normalize_custom_styles( $settings['custom_styles'] ?? array() ),
		);
	}

	/**
	 * Sanitize custom_styles object — each value is either a hex color, a numeric px value,
	 * or a short string (font-weight, size keyword, shadow preset, padding preset).
	 */
	private function normalize_custom_styles( $raw ) {
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$allowed_keys = array(
			'primary_color', 'bg_color', 'input_bg', 'border_color',
			'text_color', 'muted_color', 'label_color', 'error_color',
			'radius', 'label_weight', 'label_size', 'input_padding', 'shadow',
		);
		$clean = array();
		foreach ( $allowed_keys as $key ) {
			if ( ! isset( $raw[ $key ] ) || '' === $raw[ $key ] ) {
				continue;
			}
			$val = $raw[ $key ];
			if ( in_array( $key, array( 'primary_color', 'bg_color', 'input_bg', 'border_color', 'text_color', 'muted_color', 'label_color', 'error_color' ), true ) ) {
				// Must be a CSS color: hex, rgb(), rgba(), hsl(), or named
				$val = sanitize_text_field( $val );
				if ( preg_match( '/^(#[0-9a-fA-F]{3,8}|rgba?\([^)]+\)|hsla?\([^)]+\)|[a-zA-Z]+)$/', $val ) ) {
					$clean[ $key ] = $val;
				}
			} elseif ( 'radius' === $key ) {
				$clean[ $key ] = (string) absint( $val );
			} else {
				$clean[ $key ] = sanitize_text_field( $val );
			}
		}
		return $clean;
	}

	private function normalize_notifications( array $notifications ) {
		$normalized = array();
		foreach ( $notifications as $notification ) {
			$normalized[] = array(
				'notification_id' => sanitize_key( $notification['notification_id'] ?? wp_generate_uuid4() ),
				'label'           => sanitize_text_field( $notification['label'] ?? '' ),
				'enabled'         => (bool) ( $notification['enabled'] ?? true ),
				'to'              => sanitize_text_field( $notification['to'] ?? '' ),
				'cc'              => sanitize_text_field( $notification['cc'] ?? '' ),
				'bcc'             => sanitize_text_field( $notification['bcc'] ?? '' ),
				'subject'         => sanitize_text_field( $notification['subject'] ?? '' ),
				'message'         => wp_kses_post( $notification['message'] ?? '' ),
				'conditions'      => $notification['conditions'] ?? array(),
			);
		}
		return $normalized;
	}

	private function normalize_actions( array $actions ) {
		$normalized = array();
		$allowed_types = array(
			'save_submission', 'register_user', 'login_user', 'lost_password',
			'set_user_role', 'update_user_meta', 'update_post_meta', 'update_post_title',
			'update_post_content', 'create_post', 'update_taxonomy', 'update_acf_field',
			'update_acf_repeater', 'update_wc_product', 'create_wc_product',
			'send_email', 'webhook', 'redirect', 'custom_hook',
		);
		foreach ( $actions as $action ) {
			$type = sanitize_key( $action['action_type'] ?? '' );
			if ( ! in_array( $type, $allowed_types, true ) ) {
				continue;
			}
			$normalized[] = array(
				'action_id'   => sanitize_key( $action['action_id'] ?? wp_generate_uuid4() ),
				'action_type' => $type,
				'label'       => sanitize_text_field( $action['label'] ?? '' ),
				'enabled'     => (bool) ( $action['enabled'] ?? true ),
				'order'       => absint( $action['order'] ?? 0 ),
				'conditions'  => $action['conditions'] ?? array(),
				'config'      => $action['config'] ?? array(),
			);
		}
		usort( $normalized, fn( $a, $b ) => $a['order'] <=> $b['order'] );
		return $normalized;
	}

	public function generate_feature_map( array $normalized ) {
		$map = array(
			'has_steps'       => count( $normalized['steps'] ?? array() ) > 1,
			'has_uploads'     => false,
			'has_multi_file'  => false,
			'has_repeater'    => false,
			'has_select2'     => false,
			'has_range'       => false,
			'has_html'        => false,
			'has_notices'     => false,
			'has_conditions'  => false,
			'has_live_checks' => false,
			'has_routing'     => false,
			'has_password'    => false,
			'has_date'        => false,
			'field_count'     => 0,
			'step_count'      => count( $normalized['steps'] ?? array() ),
		);

		foreach ( ( $normalized['steps'] ?? array() ) as $step ) {
			if ( ! empty( $step['routing'] ) ) {
				$map['has_routing'] = true;
			}
			if ( ! empty( $step['conditions'] ) ) {
				$map['has_conditions'] = true;
			}

			foreach ( ( $step['fields'] ?? array() ) as $field ) {
				$map['field_count']++;
				$type = $field['field_type'] ?? '';

				if ( in_array( $type, array( 'file', 'multi_file' ), true ) ) {
					$map['has_uploads'] = true;
				}
				if ( 'multi_file' === $type ) {
					$map['has_multi_file'] = true;
				}
				if ( 'repeater' === $type ) {
					$map['has_repeater'] = true;
				}
				if ( 'select2' === $type ) {
					$map['has_select2'] = true;
				}
				if ( 'range' === $type ) {
					$map['has_range'] = true;
				}
				if ( 'html' === $type ) {
					$map['has_html'] = true;
				}
				if ( 'notice' === $type ) {
					$map['has_notices'] = true;
				}
				if ( 'password' === $type || 'confirm_password' === $type ) {
					$map['has_password'] = true;
				}
				if ( 'date' === $type ) {
					$map['has_date'] = true;
				}
				if ( ! empty( $field['conditions'] ) ) {
					$map['has_conditions'] = true;
				}
				if ( ! empty( $field['live_check']['enabled'] ) ) {
					$map['has_live_checks'] = true;
				}
			}
		}

		return apply_filters( 'clefa_feature_map', $map, $normalized );
	}
}
