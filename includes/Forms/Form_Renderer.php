<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CLEFA_Form_Renderer {

	public static function render( $form_id, $args = array() ) {
		$form = CLEFA_Tables::get_form( absint( $form_id ) );
		if ( ! $form ) {
			return '';
		}

		$config = is_array( $form['config'] ?? null ) ? $form['config'] : array();
		$config = apply_filters( 'clefa_form_config', $config, $form_id );

		$instance_id = wp_generate_uuid4();

		// Conditionally enqueue Select2 if any field in this form uses it
		if ( self::config_has_select2( $config ) ) {
			wp_enqueue_style( 'clefa-select2' );
			wp_enqueue_script( 'clefa-select2' );
		}

		// Conditionally enqueue feature-specific modules based on feature_map
		$feature_map = is_array( $form['feature_map'] ?? null ) ? $form['feature_map'] : array();
		// Enqueue step router whenever the form has more than one step (do not rely
		// solely on feature_map which may be stale if the form wasn't re-saved after
		// adding a step in the builder).
		$config_step_count = count( $config['steps'] ?? array() );
		if ( $config_step_count > 1 || ! empty( $feature_map['has_steps'] ) ) {
			wp_enqueue_script( 'clefa-step-router' );
		}
		if ( ! empty( $feature_map['has_uploads'] ) || self::config_has_field_type( $config, array( 'file', 'multi_file' ) ) ) {
			wp_enqueue_script( 'clefa-upload-manager' );
		}
		if ( ! empty( $feature_map['has_live_checks'] ) ) {
			wp_enqueue_script( 'clefa-live-check' );
		}

		// Access gate: require login or specific role
		$settings = $config['settings'] ?? array();
		if ( ! empty( $settings['require_login'] ) ) {
			$is_denied = ! is_user_logged_in();

			// Role restriction check (even for logged-in users)
			if ( ! $is_denied && ! empty( $settings['allowed_roles'] ) ) {
				$user     = wp_get_current_user();
				$roles    = array_filter( (array) $settings['allowed_roles'] );
				$is_denied= ! empty( $roles ) && ! array_intersect( $user->roles, $roles );
			}

			if ( $is_denied ) {
				$gate_tpl = self::locate_template( 'access-denied.php' );
				if ( $gate_tpl ) {
					ob_start();
					// phpcs:ignore WordPress.PHP.DontExtract.extract_extract
					extract( array(
						'form'     => $form,
						'config'   => $config,
						'form_id'  => absint( $form_id ),
						'settings' => $settings,
					), EXTR_SKIP );
					include $gate_tpl;
					return ob_get_clean();
				}
				return '';
			}
		}

		do_action( 'clefa_before_form_render', absint( $form_id ), $config );

		$template = self::locate_template( 'form.php' );
		if ( ! $template ) {
			return '';
		}

		ob_start();
		extract( array(
			'form'        => $form,
			'config'      => $config,
			'form_id'     => absint( $form_id ),
			'instance_id' => $instance_id,
		), EXTR_SKIP );
		include $template;
		$html = ob_get_clean();

		do_action( 'clefa_after_form_render', absint( $form_id ), $config );

		return $html;
	}

	public static function shortcode( $atts ) {
		$atts = shortcode_atts( array( 'id' => 0 ), $atts, 'clefa_form' );
		$form_id = absint( $atts['id'] );
		if ( ! $form_id ) {
			return '';
		}
		return self::render( $form_id );
	}

	public static function locate_template( $template_name ) {
		$locations = array(
			get_stylesheet_directory() . '/clefa-forms/' . $template_name,
			get_template_directory()   . '/clefa-forms/' . $template_name,
			CLEFA_TEMPLATE_PATH . $template_name,
		);
		foreach ( $locations as $path ) {
			if ( file_exists( $path ) ) {
				return $path;
			}
		}
		return false;
	}

	public static function locate_field_template( $field_type ) {
		$slug = sanitize_key( $field_type );
		return self::locate_template( 'fields/' . $slug . '.php' ) ?: self::locate_template( 'fields/text.php' );
	}

	/**
	 * Render a form notice using the notices.php template.
	 *
	 * @param  string $message  The notice text (HTML-safe).
	 * @param  string $type     'success' | 'error' | 'info' | 'warning'
	 * @param  int    $form_id  Optional form ID for context.
	 * @return string  Rendered HTML.
	 */
	public static function render_notice( $message, $type = 'info', $form_id = 0 ) {
		$tpl = self::locate_template( 'notices.php' );
		if ( ! $tpl ) {
			return '<div class="clefa-notice clefa-notice-' . sanitize_html_class( $type ) . '" role="status" aria-live="polite">' . wp_kses_post( $message ) . '</div>';
		}
		ob_start();
		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		extract(
			array(
				'message' => $message,
				'type'    => $type,
				'form_id' => absint( $form_id ),
			),
			EXTR_SKIP
		);
		include $tpl;
		return ob_get_clean();
	}

	public static function render_field( array $field, array $form, $step_id, $value = '', $errors = array() ) {
		$template = self::locate_field_template( $field['field_type'] ?? 'text' );
		if ( ! $template ) {
			return '';
		}
		ob_start();
		extract( array(
			'field'   => $field,
			'form'    => $form,
			'form_id' => intval( $form['id'] ?? 0 ),
			'step_id' => $step_id,
			'value'   => $value,
			'errors'  => $errors,
		), EXTR_SKIP );
		include $template;
		return ob_get_clean();
	}

	public static function get_condition_config( array $config ) {
		$conditions = array();
		foreach ( ( $config['steps'] ?? array() ) as $step ) {
			foreach ( ( $step['fields'] ?? array() ) as $field ) {
				if ( ! empty( $field['conditions'] ) ) {
					$conditions[ $field['field_id'] ] = $field['conditions'];
				}
			}
		}
		return $conditions;
	}

	/**
	 * Check whether any field in the config matches one of the given field types.
	 */
	public static function config_has_field_type( array $config, array $types ) {
		foreach ( ( $config['steps'] ?? array() ) as $step ) {
			foreach ( ( $step['fields'] ?? array() ) as $field ) {
				if ( in_array( $field['field_type'] ?? '', $types, true ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Check whether any field in the config uses Select2.
	 */
	public static function config_has_select2( array $config ) {
		foreach ( ( $config['steps'] ?? array() ) as $step ) {
			foreach ( ( $step['fields'] ?? array() ) as $field ) {
				if ( ( $field['field_type'] ?? '' ) === 'select' && ! empty( $field['use_select2'] ) ) {
					return true;
				}
			}
		}
		return false;
	}
}
