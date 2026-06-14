<?php
/**
 * Runs a programmatic form scenario: validate, sanitize, execute actions, verify side effects.
 */

class CLEFA_Programmatic_Form_Test_Runner {

	public function run( array $config, array $case ): array {
		$user_id = (int) ( $case['user_id'] ?? 0 );
		if ( array_key_exists( 'user_id', $case ) ) {
			wp_set_current_user( $user_id );
		} elseif ( empty( $case['seed'] ) ) {
			wp_set_current_user( 1 );
		}

		$before = $this->snapshot_stores();

		$errors = CLEFA_Fixture_Form_Test_Helper::validate( $config, $case['data'] ?? array() );

		$result = array(
			'validation_passed' => empty( $errors ),
			'errors'            => $errors,
			'actions'           => array(),
			'action_results'    => array(),
		);

		if ( empty( $errors ) && empty( $case['skip_actions'] ) ) {
			$sanitized = CLEFA_Form_Sanitizer::sanitize( $case['data'], $config );
			$actions   = $config['actions'] ?? array( CLEFA_Programmatic_Form_Builder::action( 'save_submission' ) );

			$result['action_results'] = CLEFA_Form_Action_Runner::run_actions(
				$actions,
				$sanitized,
				$config,
				0
			);
			$result['actions'] = $result['action_results'];
		}

		$result['verify'] = $this->run_verifications(
			$case['verify'] ?? array(),
			$result,
			$before,
			$this->snapshot_stores()
		);

		return $result;
	}

	public function assert_case_expectations( array $case, array $result, PHPUnit\Framework\TestCase $test ): void {
		$expect_pass = ! empty( $case['expect_pass'] );

		if ( $expect_pass ) {
			$test->assertTrue(
				$result['validation_passed'],
				'Expected validation pass but got: ' . wp_json_encode( $result['errors'] )
			);
		} else {
			$test->assertFalse(
				$result['validation_passed'],
				'Expected validation failure but submission passed.'
			);
		}

		foreach ( (array) ( $case['error_fields'] ?? array() ) as $field_key ) {
			$test->assertArrayHasKey(
				$field_key,
				$result['errors'],
				'Expected error on "' . $field_key . '". Actual: ' . wp_json_encode( $result['errors'] )
			);
		}

		foreach ( (array) ( $case['no_error_fields'] ?? array() ) as $field_key ) {
			$test->assertArrayNotHasKey(
				$field_key,
				$result['errors'],
				'Did not expect error on "' . $field_key . '".'
			);
		}

		foreach ( $result['verify'] as $check ) {
			$test->assertTrue(
				! empty( $check['passed'] ),
				$check['message'] ?? 'Verification failed.'
			);
		}
	}

	protected function run_verifications( array $checks, array $result, array $before, array $after ): array {
		$out = array();
		foreach ( $checks as $check ) {
			$out[] = $this->run_single_verification( $check, $result, $before, $after );
		}
		return $out;
	}

	protected function run_single_verification( array $check, array $result, array $before, array $after ): array {
		$type = $check['type'] ?? '';

		switch ( $type ) {
			case 'action_success':
				$key     = $check['action'] ?? '';
				$actions = $result['action_results'] ?? array();
				$passed  = ! empty( $actions[ $key ]['success'] );
				return array(
					'type'    => $type,
					'passed'  => $passed,
					'message' => $passed ? "Action {$key} succeeded." : "Action {$key} did not succeed.",
				);

			case 'action_failure':
				$key     = $check['action'] ?? '';
				$actions = $result['action_results'] ?? array();
				$passed  = isset( $actions[ $key ] ) && empty( $actions[ $key ]['success'] );
				return array(
					'type'    => $type,
					'passed'  => $passed,
					'message' => $passed ? "Action {$key} failed as expected." : "Action {$key} should have failed.",
				);

			case 'post_created':
				$title  = (string) ( $check['title'] ?? '' );
				$passed = $this->find_post_by_title( $title ) !== null;
				return array(
					'type'    => $type,
					'passed'  => $passed,
					'message' => $passed ? "Post \"{$title}\" was created." : "Post \"{$title}\" was not created.",
				);

			case 'post_not_created':
				$title  = (string) ( $check['title'] ?? '' );
				$passed = $this->find_post_by_title( $title ) === null;
				return array(
					'type'    => $type,
					'passed'  => $passed,
					'message' => $passed ? "Post \"{$title}\" was not created (expected)." : "Post \"{$title}\" was created unexpectedly.",
				);

			case 'post_meta':
				$title = (string) ( $check['title'] ?? '' );
				$key   = (string) ( $check['key'] ?? '' );
				$value = (string) ( $check['value'] ?? '' );
				$post  = $this->find_post_by_title( $title );
				$actual = $post ? get_post_meta( $post->ID, $key, true ) : null;
				$passed = $post && (string) $actual === $value;
				return array(
					'type'    => $type,
					'passed'  => $passed,
					'message' => $passed
						? "Post meta {$key}={$value} verified."
						: "Post meta {$key} expected \"{$value}\", got \"{$actual}\".",
				);

			case 'post_count_delta':
				$delta  = (int) ( $check['delta'] ?? 0 );
				$before_count = count( $before['posts'] ?? array() );
				$after_count  = count( $after['posts'] ?? array() );
				$passed = ( $after_count - $before_count ) === $delta;
				return array(
					'type'    => $type,
					'passed'  => $passed,
					'message' => $passed
						? "Post count delta {$delta} verified."
						: "Expected post count delta {$delta}, got " . ( $after_count - $before_count ) . '.',
				);

			case 'user_exists':
				$login = (string) ( $check['login'] ?? '' );
				$email = (string) ( $check['email'] ?? '' );
				$user  = $login ? get_user_by( 'login', $login ) : ( $email ? get_user_by( 'email', $email ) : false );
				$passed = (bool) $user;
				return array(
					'type'    => $type,
					'passed'  => $passed,
					'message' => $passed ? 'User exists.' : 'Expected user was not created.',
				);

			case 'user_not_exists':
				$email  = (string) ( $check['email'] ?? '' );
				$passed = ! email_exists( $email );
				return array(
					'type'    => $type,
					'passed'  => $passed,
					'message' => $passed ? 'User correctly not created.' : 'User was created unexpectedly.',
				);

			case 'user_meta':
				$login  = (string) ( $check['login'] ?? '' );
				$email  = (string) ( $check['email'] ?? '' );
				$key    = (string) ( $check['key'] ?? '' );
				$value  = (string) ( $check['value'] ?? '' );
				$user   = $login ? get_user_by( 'login', $login ) : ( $email ? get_user_by( 'email', $email ) : false );
				$actual = $user ? get_user_meta( $user->ID, $key, true ) : '';
				$passed = $user && (string) $actual === $value;
				return array(
					'type'    => $type,
					'passed'  => $passed,
					'message' => $passed ? "User meta {$key} verified." : "User meta {$key} mismatch.",
				);

			case 'user_password':
				$login    = (string) ( $check['login'] ?? '' );
				$password = (string) ( $check['password'] ?? '' );
				$user     = get_user_by( 'login', $login );
				$passed   = $user && $user->user_pass === $password;
				return array(
					'type'    => $type,
					'passed'  => $passed,
					'message' => $passed ? 'User password updated.' : 'User password mismatch.',
				);

			case 'product_exists':
				$name   = (string) ( $check['name'] ?? '' );
				$passed = $this->find_product_by_name( $name ) !== null;
				return array(
					'type'    => $type,
					'passed'  => $passed,
					'message' => $passed ? "Product \"{$name}\" exists." : "Product \"{$name}\" missing.",
				);

			case 'product_not_exists':
				$name   = (string) ( $check['name'] ?? '' );
				$passed = $this->find_product_by_name( $name ) === null;
				return array(
					'type'    => $type,
					'passed'  => $passed,
					'message' => $passed ? "Product \"{$name}\" was not created (expected)." : "Product \"{$name}\" exists unexpectedly.",
				);

			case 'product_price':
				$name     = (string) ( $check['name'] ?? '' );
				$price    = (float) ( $check['price'] ?? 0 );
				$product  = $this->find_product_by_name( $name );
				$actual   = $product ? (float) $product->get_regular_price() : -1;
				$passed   = $product && abs( $actual - $price ) < 0.001;
				return array(
					'type'    => $type,
					'passed'  => $passed,
					'message' => $passed ? 'Product price verified.' : "Expected price {$price}, got {$actual}.",
				);

			case 'acf_value':
				$target = (string) ( $check['target'] ?? '' );
				$field  = (string) ( $check['field'] ?? '' );
				$value  = $check['value'] ?? null;
				global $clefa_test_acf_fields;
				$actual = $clefa_test_acf_fields[ $target ][ $field ] ?? null;
				$passed = $actual === $value;
				return array(
					'type'    => $type,
					'passed'  => $passed,
					'message' => $passed ? 'ACF value verified.' : 'ACF value mismatch.',
				);

			default:
				return array(
					'type'    => $type,
					'passed'  => false,
					'message' => 'Unknown verification type: ' . $type,
				);
		}
	}

	protected function snapshot_stores(): array {
		global $clefa_test_posts, $clefa_test_users, $clefa_test_products, $clefa_test_acf_fields;
		return array(
			'posts'    => $clefa_test_posts ?? array(),
			'users'    => $clefa_test_users ?? array(),
			'products' => $clefa_test_products ?? array(),
			'acf'      => $clefa_test_acf_fields ?? array(),
		);
	}

	protected function find_post_by_title( string $title ) {
		global $clefa_test_posts;
		foreach ( (array) $clefa_test_posts as $post ) {
			if ( $post->post_title === $title ) {
				return $post;
			}
		}
		return null;
	}

	protected function find_product_by_name( string $name ) {
		global $clefa_test_products;
		foreach ( (array) $clefa_test_products as $product ) {
			if ( method_exists( $product, 'get_name' ) && $product->get_name() === $name ) {
				return $product;
			}
		}
		return null;
	}
}
