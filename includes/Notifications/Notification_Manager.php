<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CLEFA_Notification_Manager {

	public static function init() {
		add_action( 'clefa_after_submission_save', array( __CLASS__, 'send_notifications' ), 20, 4 );
	}

	public static function send_notifications( $form_id, array $data, $submission_id, array $action_results ) {
		$form = CLEFA_Tables::get_form( absint( $form_id ) );
		if ( ! $form ) { return; }

		$config = is_array( $form['config'] ?? null ) ? $form['config'] : array();
		$notifications = $config['notifications'] ?? array();

		if ( empty( $notifications ) ) { return; }

		foreach ( $notifications as $notification ) {
			if ( empty( $notification['enabled'] ) ) { continue; }
			self::send_notification( $notification, $data, $config, $submission_id );
		}
	}

	public static function send_notification( array $n, array $data, array $form_config, $submission_id ) {
		// Evaluate send conditions
		$conditions = $n['conditions'] ?? array();
		if ( ! empty( $conditions ) ) {
			$result = CLEFA_Form_Condition_Engine::evaluate_field_conditions( $conditions, $data );
			if ( 'show' !== $result['action'] ) { return; }
		}

		$resolver = new self();

		// Resolve recipient(s)
		$to_list = self::resolve_recipients( $n, $data, $form_config );
		if ( empty( $to_list ) ) { return; }

		$subject  = $resolver->resolve( $n['subject'] ?? __( 'New form submission', 'codelinden-elementor-form-addon' ), $data, $form_config, $submission_id );
		$body     = $resolver->resolve( $n['body']    ?? '', $data, $form_config, $submission_id );

		if ( empty( $body ) ) {
			$body = self::build_default_body( $data, $form_config );
		}

		$is_html    = ! empty( $n['is_html'] );
		$from_name  = $resolver->resolve( $n['from_name']  ?? get_bloginfo( 'name' ), $data, $form_config, $submission_id );
		$from_email = sanitize_email( $resolver->resolve( $n['from_email'] ?? get_option( 'admin_email' ), $data, $form_config, $submission_id ) );
		$reply_to   = sanitize_email( $resolver->resolve( $n['reply_to']   ?? '', $data, $form_config, $submission_id ) );
		$cc         = $resolver->resolve( $n['cc']  ?? '', $data, $form_config, $submission_id );
		$bcc        = $resolver->resolve( $n['bcc'] ?? '', $data, $form_config, $submission_id );

		$headers = array( 'From: ' . $from_name . ' <' . $from_email . '>' );
		if ( $is_html )  $headers[] = 'Content-Type: text/html; charset=UTF-8';
		if ( $reply_to ) $headers[] = 'Reply-To: ' . $reply_to;
		if ( $cc )       $headers[] = 'Cc: '  . $cc;
		if ( $bcc )      $headers[] = 'Bcc: ' . $bcc;

		$headers = apply_filters( 'clefa_notification_headers', $headers, $n, $data, $form_config );

		foreach ( $to_list as $to ) {
			$to = sanitize_email( $to );
			if ( ! is_email( $to ) ) { continue; }

			$result = wp_mail( $to, $subject, $body, $headers );

			CLEFA_Audit_Log::write( 'notification_sent', array(
				'form_id'       => $form_config['id'] ?? 0,
				'submission_id' => $submission_id,
				'to'            => $to,
				'subject'       => $subject,
				'success'       => $result,
			) );
		}
	}

	private static function resolve_recipients( array $n, array $data, array $form_config ) {
		$type = $n['recipient_type'] ?? 'admin';
		$list = array();

		switch ( $type ) {
			case 'admin':
				$list[] = get_option( 'admin_email' );
				break;
			case 'submitter': {
				// Find first email field in form
				foreach ( ( $form_config['steps'] ?? array() ) as $step ) {
					foreach ( ( $step['fields'] ?? array() ) as $field ) {
						if ( ( $field['field_type'] ?? '' ) === 'email' && ! empty( $data[ $field['field_id'] ?? '' ] ) ) {
							$list[] = $data[ $field['field_id'] ];
							break 2;
						}
					}
				}
				// Fallback: field_id specified
				if ( empty( $list ) && ! empty( $n['submitter_email_field'] ) ) {
					$val = $data[ $n['submitter_email_field'] ] ?? '';
					if ( $val ) $list[] = $val;
				}
				break;
			}
			case 'field': {
				$fid = $n['email_field_id'] ?? '';
				$val = $data[ $fid ] ?? '';
				if ( $val ) {
					$list = is_array( $val ) ? $val : array( $val );
				}
				break;
			}
			case 'custom': {
				$custom = $n['custom_emails'] ?? '';
				if ( $custom ) {
					$list = array_map( 'trim', explode( ',', $custom ) );
				}
				break;
			}
			case 'user_role': {
				$role  = sanitize_key( $n['user_role'] ?? '' );
				$users = get_users( array( 'role' => $role, 'fields' => array( 'user_email' ) ) );
				foreach ( $users as $u ) {
					$list[] = $u->user_email;
				}
				break;
			}
		}

		// Always add extra_to if set
		if ( ! empty( $n['extra_to'] ) ) {
			$extras = array_map( 'trim', explode( ',', $n['extra_to'] ) );
			$list   = array_merge( $list, $extras );
		}

		return array_filter( $list, 'is_email' );
	}

	private function resolve( $template, array $data, array $form_config, $submission_id ) {
		if ( ! is_string( $template ) ) return (string) $template;

		// {field:id} tokens
		$template = preg_replace_callback( '/\{field:([a-zA-Z0-9_\-]+)\}/', function( $m ) use ( $data ) {
			$val = $data[ $m[1] ] ?? '';
			return is_array( $val ) ? implode( ', ', $val ) : (string) $val;
		}, $template );

		// {all_fields} — auto-generate a full field list
		if ( false !== strpos( $template, '{all_fields}' ) ) {
			$template = str_replace( '{all_fields}', self::build_default_body( $data, $form_config ), $template );
		}

		// {user:key} tokens
		$template = preg_replace_callback( '/\{user:([a-zA-Z0-9_\-]+)\}/', function( $m ) {
			if ( ! is_user_logged_in() ) return '';
			$user = wp_get_current_user();
			$key  = $m[1];
			$props = array( 'user_login', 'user_email', 'display_name', 'ID', 'first_name', 'last_name' );
			if ( in_array( $key, $props, true ) ) return (string) $user->$key;
			return (string) get_user_meta( $user->ID, $key, true );
		}, $template );

		// Static tokens
		$replacements = array(
			'{form:name}'       => $form_config['form_name'] ?? '',
			'{form:id}'         => (string) ( $form_config['id'] ?? '' ),
			'{submission:id}'   => (string) $submission_id,
			'{site:name}'       => get_bloginfo( 'name' ),
			'{site:url}'        => get_site_url(),
			'{admin:email}'     => get_option( 'admin_email' ),
			'{date}'            => current_time( 'Y-m-d' ),
			'{time}'            => current_time( 'H:i:s' ),
			'{datetime}'        => current_time( 'Y-m-d H:i:s' ),
		);

		foreach ( $replacements as $token => $val ) {
			$template = str_replace( $token, $val, $template );
		}

		return apply_filters( 'clefa_notification_resolve_token', $template, $data, $form_config );
	}

	private static function build_default_body( array $data, array $form_config ) {
		$lines = array( 'Form: ' . ( $form_config['form_name'] ?? '' ), '' );
		foreach ( ( $form_config['steps'] ?? array() ) as $step ) {
			foreach ( ( $step['fields'] ?? array() ) as $field ) {
				$fid   = $field['field_id'] ?? '';
				$label = $field['label']    ?? $fid;
				$ftype = $field['field_type'] ?? '';
				if ( in_array( $ftype, array( 'html', 'notice', 'hidden', 'grid_break', 'heading' ), true ) ) continue;
				$val   = $data[ $fid ] ?? '';
				if ( is_array( $val ) ) $val = implode( ', ', $val );
				$lines[] = esc_html( $label ) . ': ' . esc_html( (string) $val );
			}
		}
		return implode( "\n", $lines );
	}
}
