<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Central validation rule registry.
 *
 * Stores metadata + PHP handlers for every available validation rule.
 * Rules are extensible via the `clefa_register_validation_rules` action.
 *
 * Rule schema (all keys):
 *   key               string    Unique rule ID
 *   label             string    Display name for the builder UI
 *   description       string    Short description / tooltip
 *   applies_to        array     Field types the rule is valid for; ['*'] = all types
 *   has_value         bool      Whether the rule needs a parameter value
 *   value_type        string    'text'|'number'|'select'|'date'|'none'
 *   value_label       string    Label for the value input in the builder
 *   value_placeholder string    Placeholder text for the value input
 *   value_options     array     Options for select type: [ ['value'=>'x','label'=>'X'], ... ]
 *   default_message   string    Default error message; {value} is replaced with the param
 *   server            bool      Evaluated server-side by Form_Validator
 *   client            bool      Evaluated client-side by ValidationEngine.js
 *   handler           callable  PHP callable: fn($value,$param,$field,$all_data):?string
 */
class CLEFA_Validation_Registry {

	private static array $rules       = [];
	private static bool  $initialized = false;

	// -----------------------------------------------------------------------
	// Public API
	// -----------------------------------------------------------------------

	/** Initialize once; fires the extension hook. */
	public static function init(): void {
		if ( self::$initialized ) {
			return;
		}
		self::$initialized = true;
		self::register_core_rules();

		/**
		 * Hook for third-party code to register additional validation rules.
		 *
		 * @param string $registry Class name (CLEFA_Validation_Registry); call
		 *                         CLEFA_Validation_Registry::register( $rule ) to add rules.
		 */
		do_action( 'clefa_register_validation_rules', static::class );
	}

	/**
	 * Register a single rule definition.
	 *
	 * @param array $rule Rule definition (see class docblock for schema).
	 */
	public static function register( array $rule ): void {
		if ( empty( $rule['key'] ) ) {
			return;
		}
		self::$rules[ $rule['key'] ] = $rule;
	}

	/** Return all registered rules (initializes if needed). */
	public static function get_all(): array {
		self::init();
		return self::$rules;
	}

	/** Return a single rule definition or null if not found. */
	public static function get( string $key ): ?array {
		self::init();
		return self::$rules[ $key ] ?? null;
	}

	/** Return rules applicable to a given field type. */
	public static function get_for_field_type( string $type ): array {
		self::init();
		return array_filter(
			self::$rules,
			static function ( array $rule ) use ( $type ): bool {
				$applies = $rule['applies_to'] ?? [];
				return in_array( '*', $applies, true ) || in_array( $type, $applies, true );
			}
		);
	}

	/**
	 * Execute a rule's PHP handler.
	 *
	 * @return string|null  Error message, or null when the value passes.
	 */
	public static function execute( string $key, $value, $param, array $field, array $all_data ): ?string {
		self::init();
		$rule = self::get( $key );
		if ( ! $rule || empty( $rule['handler'] ) || empty( $rule['server'] ) ) {
			return null;
		}
		return call_user_func( $rule['handler'], $value, $param, $field, $all_data );
	}

	/**
	 * Return a serialisable schema suitable for the builder's JavaScript.
	 * PHP handler callables are stripped; only metadata remains.
	 */
	public static function get_builder_schema(): array {
		self::init();
		return array_map(
			static function ( array $rule ): array {
				unset( $rule['handler'] );
				return $rule;
			},
			self::$rules
		);
	}

	// -----------------------------------------------------------------------
	// Core rule definitions
	// -----------------------------------------------------------------------

	private static function register_core_rules(): void {
		$text_types  = array( 'text', 'textarea', 'email', 'url', 'phone', 'password' );
		$typed_text  = array( 'text', 'textarea', 'email', 'url', 'phone' );
		$date_types  = array( 'date', 'text' );
		$num_types   = array( 'number', 'range', 'text' );

		// ------------------------------------------------------------------ //
		// Text-length rules
		// ------------------------------------------------------------------ //

		self::register( array(
			'key'               => 'min_length',
			'label'             => __( 'Minimum length', 'codelinden-elementor-form-addon' ),
			'description'       => __( 'Value must have at least this many characters.', 'codelinden-elementor-form-addon' ),
			'applies_to'        => $text_types,
			'has_value'         => true,
			'value_type'        => 'number',
			'value_label'       => __( 'Min characters', 'codelinden-elementor-form-addon' ),
			'value_placeholder' => 'e.g. 5',
			'value_options'     => array(),
			'default_message'   => __( 'Minimum {value} characters required.', 'codelinden-elementor-form-addon' ),
			'server'            => true,
			'client'            => true,
			'handler'           => static function ( $value, $param ) {
				$min = (int) $param;
				if ( $min <= 0 ) {
					return null;
				}
				return mb_strlen( (string) $value ) < $min
					/* translators: %d = minimum character count */
					? sprintf( __( 'Minimum %d characters required.', 'codelinden-elementor-form-addon' ), $min )
					: null;
			},
		) );

		self::register( array(
			'key'               => 'max_length',
			'label'             => __( 'Maximum length', 'codelinden-elementor-form-addon' ),
			'description'       => __( 'Value must not exceed this many characters.', 'codelinden-elementor-form-addon' ),
			'applies_to'        => $text_types,
			'has_value'         => true,
			'value_type'        => 'number',
			'value_label'       => __( 'Max characters', 'codelinden-elementor-form-addon' ),
			'value_placeholder' => 'e.g. 100',
			'value_options'     => array(),
			'default_message'   => __( 'Maximum {value} characters allowed.', 'codelinden-elementor-form-addon' ),
			'server'            => true,
			'client'            => true,
			'handler'           => static function ( $value, $param ) {
				$max = (int) $param;
				if ( $max <= 0 ) {
					return null;
				}
				return mb_strlen( (string) $value ) > $max
					/* translators: %d = maximum character count */
					? sprintf( __( 'Maximum %d characters allowed.', 'codelinden-elementor-form-addon' ), $max )
					: null;
			},
		) );

		self::register( array(
			'key'               => 'exact_length',
			'label'             => __( 'Exact length', 'codelinden-elementor-form-addon' ),
			'description'       => __( 'Value must be exactly this many characters.', 'codelinden-elementor-form-addon' ),
			'applies_to'        => $text_types,
			'has_value'         => true,
			'value_type'        => 'number',
			'value_label'       => __( 'Exact characters', 'codelinden-elementor-form-addon' ),
			'value_placeholder' => 'e.g. 10',
			'value_options'     => array(),
			'default_message'   => __( 'Must be exactly {value} characters.', 'codelinden-elementor-form-addon' ),
			'server'            => true,
			'client'            => true,
			'handler'           => static function ( $value, $param ) {
				$len = (int) $param;
				if ( $len <= 0 ) {
					return null;
				}
				return mb_strlen( (string) $value ) !== $len
					/* translators: %d = exact character count */
					? sprintf( __( 'Must be exactly %d characters.', 'codelinden-elementor-form-addon' ), $len )
					: null;
			},
		) );

		// ------------------------------------------------------------------ //
		// Pattern / block rules
		// ------------------------------------------------------------------ //

		self::register( array(
			'key'               => 'regex',
			'label'             => __( 'Regex pattern', 'codelinden-elementor-form-addon' ),
			'description'       => __( 'Value must match the given regular expression (without delimiters).', 'codelinden-elementor-form-addon' ),
			'applies_to'        => $text_types,
			'has_value'         => true,
			'value_type'        => 'text',
			'value_label'       => __( 'Pattern', 'codelinden-elementor-form-addon' ),
			'value_placeholder' => '^[a-z0-9]+$',
			'value_options'     => array(),
			'default_message'   => __( 'Invalid format.', 'codelinden-elementor-form-addon' ),
			'server'            => true,
			'client'            => true,
			'handler'           => static function ( $value, $param ) {
				if ( ! $param ) {
					return null;
				}
				$pattern = '/' . trim( (string) $param, '/' ) . '/u';
				// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				if ( ! @preg_match( $pattern, (string) $value ) ) {
					return __( 'Invalid format.', 'codelinden-elementor-form-addon' );
				}
				return null;
			},
		) );

		self::register( array(
			'key'               => 'blocked_values',
			'label'             => __( 'Blocked values', 'codelinden-elementor-form-addon' ),
			'description'       => __( 'Comma-separated list of disallowed values.', 'codelinden-elementor-form-addon' ),
			'applies_to'        => $text_types,
			'has_value'         => true,
			'value_type'        => 'text',
			'value_label'       => __( 'Blocked values (comma-separated)', 'codelinden-elementor-form-addon' ),
			'value_placeholder' => 'spam, test, admin',
			'value_options'     => array(),
			'default_message'   => __( 'This value is not allowed.', 'codelinden-elementor-form-addon' ),
			'server'            => true,
			'client'            => true,
			'handler'           => static function ( $value, $param ) {
				if ( ! $param ) {
					return null;
				}
				$blocked = array_map( 'trim', explode( ',', (string) $param ) );
				return in_array( (string) $value, $blocked, true )
					? __( 'This value is not allowed.', 'codelinden-elementor-form-addon' )
					: null;
			},
		) );

		self::register( array(
			'key'               => 'equals',
			'label'             => __( 'Must equal', 'codelinden-elementor-form-addon' ),
			'description'       => __( 'Value must exactly match the given string.', 'codelinden-elementor-form-addon' ),
			'applies_to'        => $text_types,
			'has_value'         => true,
			'value_type'        => 'text',
			'value_label'       => __( 'Required value', 'codelinden-elementor-form-addon' ),
			'value_placeholder' => '',
			'value_options'     => array(),
			'default_message'   => __( 'Value does not match.', 'codelinden-elementor-form-addon' ),
			'server'            => true,
			'client'            => true,
			'handler'           => static function ( $value, $param ) {
				return (string) $value !== (string) $param
					? __( 'Value does not match.', 'codelinden-elementor-form-addon' )
					: null;
			},
		) );

		self::register( array(
			'key'               => 'not_equals',
			'label'             => __( 'Must not equal', 'codelinden-elementor-form-addon' ),
			'description'       => __( 'Value must not match the given string.', 'codelinden-elementor-form-addon' ),
			'applies_to'        => $text_types,
			'has_value'         => true,
			'value_type'        => 'text',
			'value_label'       => __( 'Disallowed value', 'codelinden-elementor-form-addon' ),
			'value_placeholder' => '',
			'value_options'     => array(),
			'default_message'   => __( 'This value is not permitted.', 'codelinden-elementor-form-addon' ),
			'server'            => true,
			'client'            => true,
			'handler'           => static function ( $value, $param ) {
				return (string) $value === (string) $param
					? __( 'This value is not permitted.', 'codelinden-elementor-form-addon' )
					: null;
			},
		) );

		// ------------------------------------------------------------------ //
		// Format rules
		// ------------------------------------------------------------------ //

		self::register( array(
			'key'               => 'email',
			'label'             => __( 'Email address', 'codelinden-elementor-form-addon' ),
			'description'       => __( 'Value must be a valid email address.', 'codelinden-elementor-form-addon' ),
			'applies_to'        => array( 'text', 'email' ),
			'has_value'         => false,
			'value_type'        => 'none',
			'value_label'       => '',
			'value_placeholder' => '',
			'value_options'     => array(),
			'default_message'   => __( 'Please enter a valid email address.', 'codelinden-elementor-form-addon' ),
			'server'            => true,
			'client'            => true,
			'handler'           => static function ( $value ) {
				return is_email( (string) $value )
					? null
					: __( 'Please enter a valid email address.', 'codelinden-elementor-form-addon' );
			},
		) );

		self::register( array(
			'key'               => 'unique_email',
			'label'             => __( 'Unique email', 'codelinden-elementor-form-addon' ),
			'description'       => __( 'Email must not already be registered in WordPress (server-side only).', 'codelinden-elementor-form-addon' ),
			'applies_to'        => array( 'email' ),
			'has_value'         => false,
			'value_type'        => 'none',
			'value_label'       => '',
			'value_placeholder' => '',
			'value_options'     => array(),
			'default_message'   => __( 'This email is already registered.', 'codelinden-elementor-form-addon' ),
			'server'            => true,
			'client'            => false,
			'handler'           => static function ( $value ) {
				return email_exists( (string) $value )
					? __( 'This email is already registered.', 'codelinden-elementor-form-addon' )
					: null;
			},
		) );

		self::register( array(
			'key'               => 'url',
			'label'             => __( 'URL', 'codelinden-elementor-form-addon' ),
			'description'       => __( 'Value must be a valid URL including protocol.', 'codelinden-elementor-form-addon' ),
			'applies_to'        => array( 'text', 'url' ),
			'has_value'         => false,
			'value_type'        => 'none',
			'value_label'       => '',
			'value_placeholder' => '',
			'value_options'     => array(),
			'default_message'   => __( 'Please enter a valid URL.', 'codelinden-elementor-form-addon' ),
			'server'            => true,
			'client'            => true,
			'handler'           => static function ( $value ) {
				return filter_var( (string) $value, FILTER_VALIDATE_URL )
					? null
					: __( 'Please enter a valid URL.', 'codelinden-elementor-form-addon' );
			},
		) );

		self::register( array(
			'key'               => 'numeric',
			'label'             => __( 'Numeric', 'codelinden-elementor-form-addon' ),
			'description'       => __( 'Value must be a number (integer or decimal).', 'codelinden-elementor-form-addon' ),
			'applies_to'        => $num_types,
			'has_value'         => false,
			'value_type'        => 'none',
			'value_label'       => '',
			'value_placeholder' => '',
			'value_options'     => array(),
			'default_message'   => __( 'Please enter a valid number.', 'codelinden-elementor-form-addon' ),
			'server'            => true,
			'client'            => true,
			'handler'           => static function ( $value ) {
				return is_numeric( $value )
					? null
					: __( 'Please enter a valid number.', 'codelinden-elementor-form-addon' );
			},
		) );

		self::register( array(
			'key'               => 'integer',
			'label'             => __( 'Integer', 'codelinden-elementor-form-addon' ),
			'description'       => __( 'Value must be a whole number (no decimals).', 'codelinden-elementor-form-addon' ),
			'applies_to'        => $num_types,
			'has_value'         => false,
			'value_type'        => 'none',
			'value_label'       => '',
			'value_placeholder' => '',
			'value_options'     => array(),
			'default_message'   => __( 'Please enter a whole number.', 'codelinden-elementor-form-addon' ),
			'server'            => true,
			'client'            => true,
			'handler'           => static function ( $value ) {
				return (string) (int) $value === (string) trim( (string) $value )
					? null
					: __( 'Please enter a whole number.', 'codelinden-elementor-form-addon' );
			},
		) );

		// ------------------------------------------------------------------ //
		// Numeric range rules
		// ------------------------------------------------------------------ //

		self::register( array(
			'key'               => 'min_value',
			'label'             => __( 'Minimum value', 'codelinden-elementor-form-addon' ),
			'description'       => __( 'Number must be at least this value.', 'codelinden-elementor-form-addon' ),
			'applies_to'        => $num_types,
			'has_value'         => true,
			'value_type'        => 'number',
			'value_label'       => __( 'Min value', 'codelinden-elementor-form-addon' ),
			'value_placeholder' => 'e.g. 0',
			'value_options'     => array(),
			'default_message'   => __( 'Minimum value is {value}.', 'codelinden-elementor-form-addon' ),
			'server'            => true,
			'client'            => true,
			'handler'           => static function ( $value, $param ) {
				if ( $param === '' || $param === null ) {
					return null;
				}
				return (float) $value < (float) $param
					/* translators: %s = minimum value */
					? sprintf( __( 'Minimum value is %s.', 'codelinden-elementor-form-addon' ), $param )
					: null;
			},
		) );

		self::register( array(
			'key'               => 'max_value',
			'label'             => __( 'Maximum value', 'codelinden-elementor-form-addon' ),
			'description'       => __( 'Number must be no greater than this value.', 'codelinden-elementor-form-addon' ),
			'applies_to'        => $num_types,
			'has_value'         => true,
			'value_type'        => 'number',
			'value_label'       => __( 'Max value', 'codelinden-elementor-form-addon' ),
			'value_placeholder' => 'e.g. 100',
			'value_options'     => array(),
			'default_message'   => __( 'Maximum value is {value}.', 'codelinden-elementor-form-addon' ),
			'server'            => true,
			'client'            => true,
			'handler'           => static function ( $value, $param ) {
				if ( $param === '' || $param === null ) {
					return null;
				}
				return (float) $value > (float) $param
					/* translators: %s = maximum value */
					? sprintf( __( 'Maximum value is %s.', 'codelinden-elementor-form-addon' ), $param )
					: null;
			},
		) );

		// ------------------------------------------------------------------ //
		// Date rules
		// ------------------------------------------------------------------ //

		self::register( array(
			'key'               => 'date_valid',
			'label'             => __( 'Valid date', 'codelinden-elementor-form-addon' ),
			'description'       => __( 'Value must be a recognisable date.', 'codelinden-elementor-form-addon' ),
			'applies_to'        => $date_types,
			'has_value'         => false,
			'value_type'        => 'none',
			'value_label'       => '',
			'value_placeholder' => '',
			'value_options'     => array(),
			'default_message'   => __( 'Please enter a valid date.', 'codelinden-elementor-form-addon' ),
			'server'            => true,
			'client'            => true,
			'handler'           => static function ( $value ) {
				return ( ! empty( $value ) && strtotime( (string) $value ) !== false )
					? null
					: __( 'Please enter a valid date.', 'codelinden-elementor-form-addon' );
			},
		) );

		self::register( array(
			'key'               => 'date_after_today',
			'label'             => __( 'After today', 'codelinden-elementor-form-addon' ),
			'description'       => __( 'Date must be strictly in the future.', 'codelinden-elementor-form-addon' ),
			'applies_to'        => $date_types,
			'has_value'         => false,
			'value_type'        => 'none',
			'value_label'       => '',
			'value_placeholder' => '',
			'value_options'     => array(),
			'default_message'   => __( 'Date must be after today.', 'codelinden-elementor-form-addon' ),
			'server'            => true,
			'client'            => true,
			'handler'           => static function ( $value ) {
				$ts = strtotime( (string) $value );
				return ( $ts !== false && $ts > strtotime( 'today midnight' ) )
					? null
					: __( 'Date must be after today.', 'codelinden-elementor-form-addon' );
			},
		) );

		self::register( array(
			'key'               => 'date_before_today',
			'label'             => __( 'Before today', 'codelinden-elementor-form-addon' ),
			'description'       => __( 'Date must be strictly in the past.', 'codelinden-elementor-form-addon' ),
			'applies_to'        => $date_types,
			'has_value'         => false,
			'value_type'        => 'none',
			'value_label'       => '',
			'value_placeholder' => '',
			'value_options'     => array(),
			'default_message'   => __( 'Date must be before today.', 'codelinden-elementor-form-addon' ),
			'server'            => true,
			'client'            => true,
			'handler'           => static function ( $value ) {
				$ts = strtotime( (string) $value );
				return ( $ts !== false && $ts < strtotime( 'today midnight' ) )
					? null
					: __( 'Date must be before today.', 'codelinden-elementor-form-addon' );
			},
		) );

		self::register( array(
			'key'               => 'date_after',
			'label'             => __( 'After date', 'codelinden-elementor-form-addon' ),
			'description'       => __( 'Date must be after the specified date. Use YYYY-MM-DD or a relative string like "+7 days".', 'codelinden-elementor-form-addon' ),
			'applies_to'        => $date_types,
			'has_value'         => true,
			'value_type'        => 'text',
			'value_label'       => __( 'After date', 'codelinden-elementor-form-addon' ),
			'value_placeholder' => '2025-01-01',
			'value_options'     => array(),
			'default_message'   => __( 'Date must be after {value}.', 'codelinden-elementor-form-addon' ),
			'server'            => true,
			'client'            => true,
			'handler'           => static function ( $value, $param ) {
				if ( ! $param ) {
					return null;
				}
				$ts        = strtotime( (string) $value );
				$threshold = strtotime( (string) $param );
				if ( $ts === false || $threshold === false ) {
					return null;
				}
				return $ts <= $threshold
					/* translators: %s = minimum date */
					? sprintf( __( 'Date must be after %s.', 'codelinden-elementor-form-addon' ), $param )
					: null;
			},
		) );

		self::register( array(
			'key'               => 'date_before',
			'label'             => __( 'Before date', 'codelinden-elementor-form-addon' ),
			'description'       => __( 'Date must be before the specified date. Use YYYY-MM-DD or a relative string like "-1 year".', 'codelinden-elementor-form-addon' ),
			'applies_to'        => $date_types,
			'has_value'         => true,
			'value_type'        => 'text',
			'value_label'       => __( 'Before date', 'codelinden-elementor-form-addon' ),
			'value_placeholder' => '2030-12-31',
			'value_options'     => array(),
			'default_message'   => __( 'Date must be before {value}.', 'codelinden-elementor-form-addon' ),
			'server'            => true,
			'client'            => true,
			'handler'           => static function ( $value, $param ) {
				if ( ! $param ) {
					return null;
				}
				$ts        = strtotime( (string) $value );
				$threshold = strtotime( (string) $param );
				if ( $ts === false || $threshold === false ) {
					return null;
				}
				return $ts >= $threshold
					/* translators: %s = maximum date */
					? sprintf( __( 'Date must be before %s.', 'codelinden-elementor-form-addon' ), $param )
					: null;
			},
		) );

		// ------------------------------------------------------------------ //
		// Age rules (date of birth field)
		// ------------------------------------------------------------------ //

		self::register( array(
			'key'               => 'age_over',
			'label'             => __( 'Age over', 'codelinden-elementor-form-addon' ),
			'description'       => __( 'User must be older than the given number of years (treats field as date of birth).', 'codelinden-elementor-form-addon' ),
			'applies_to'        => array( 'date' ),
			'has_value'         => true,
			'value_type'        => 'number',
			'value_label'       => __( 'Minimum age (years)', 'codelinden-elementor-form-addon' ),
			'value_placeholder' => '18',
			'value_options'     => array(),
			'default_message'   => __( 'You must be at least {value} years old.', 'codelinden-elementor-form-addon' ),
			'server'            => true,
			'client'            => true,
			'handler'           => static function ( $value, $param ) {
				$min_age = (int) $param;
				if ( $min_age <= 0 ) {
					return null;
				}
				$dob_ts = strtotime( (string) $value );
				if ( $dob_ts === false ) {
					return null;
				}
				$age = (int) floor( ( time() - $dob_ts ) / 31557600 ); // ~365.25 days
				return $age < $min_age
					/* translators: %d = minimum age */
					? sprintf( __( 'You must be at least %d years old.', 'codelinden-elementor-form-addon' ), $min_age )
					: null;
			},
		) );

		self::register( array(
			'key'               => 'age_under',
			'label'             => __( 'Age under', 'codelinden-elementor-form-addon' ),
			'description'       => __( 'User must be younger than the given number of years (treats field as date of birth).', 'codelinden-elementor-form-addon' ),
			'applies_to'        => array( 'date' ),
			'has_value'         => true,
			'value_type'        => 'number',
			'value_label'       => __( 'Maximum age (years)', 'codelinden-elementor-form-addon' ),
			'value_placeholder' => '65',
			'value_options'     => array(),
			'default_message'   => __( 'You must be under {value} years old.', 'codelinden-elementor-form-addon' ),
			'server'            => true,
			'client'            => true,
			'handler'           => static function ( $value, $param ) {
				$max_age = (int) $param;
				if ( $max_age <= 0 ) {
					return null;
				}
				$dob_ts = strtotime( (string) $value );
				if ( $dob_ts === false ) {
					return null;
				}
				$age = (int) floor( ( time() - $dob_ts ) / 31557600 ); // ~365.25 days
				return $age >= $max_age
					/* translators: %d = maximum age */
					? sprintf( __( 'You must be under %d years old.', 'codelinden-elementor-form-addon' ), $max_age )
					: null;
			},
		) );

		// ------------------------------------------------------------------ //
		// Time-elapsed rules
		// ------------------------------------------------------------------ //

		self::register( array(
			'key'               => 'time_since',
			'label'             => __( 'Time since (days)', 'codelinden-elementor-form-addon' ),
			'description'       => __( 'The submitted date must be at least X days in the past.', 'codelinden-elementor-form-addon' ),
			'applies_to'        => array( 'date' ),
			'has_value'         => true,
			'value_type'        => 'number',
			'value_label'       => __( 'Minimum days elapsed', 'codelinden-elementor-form-addon' ),
			'value_placeholder' => 'e.g. 30',
			'value_options'     => array(),
			'default_message'   => __( 'Date must be at least {value} days in the past.', 'codelinden-elementor-form-addon' ),
			'server'            => true,
			'client'            => true,
			'handler'           => static function ( $value, $param ) {
				$min_days = (float) $param;
				if ( $min_days <= 0 ) {
					return null;
				}
				$ts = strtotime( (string) $value );
				if ( $ts === false ) {
					return null;
				}
				$days_elapsed = ( time() - $ts ) / 86400;
				return $days_elapsed < $min_days
					/* translators: %s = minimum days */
					? sprintf( __( 'Date must be at least %s days in the past.', 'codelinden-elementor-form-addon' ), $param )
					: null;
			},
		) );

		self::register( array(
			'key'               => 'time_passed',
			'label'             => __( 'Time passed (hours)', 'codelinden-elementor-form-addon' ),
			'description'       => __( 'At least X hours must have elapsed since the submitted date.', 'codelinden-elementor-form-addon' ),
			'applies_to'        => array( 'date' ),
			'has_value'         => true,
			'value_type'        => 'number',
			'value_label'       => __( 'Minimum hours elapsed', 'codelinden-elementor-form-addon' ),
			'value_placeholder' => 'e.g. 24',
			'value_options'     => array(),
			'default_message'   => __( 'At least {value} hours must have elapsed since that date.', 'codelinden-elementor-form-addon' ),
			'server'            => true,
			'client'            => true,
			'handler'           => static function ( $value, $param ) {
				$min_hours = (float) $param;
				if ( $min_hours <= 0 ) {
					return null;
				}
				$ts = strtotime( (string) $value );
				if ( $ts === false ) {
					return null;
				}
				$hours_elapsed = ( time() - $ts ) / 3600;
				return $hours_elapsed < $min_hours
					/* translators: %s = minimum hours */
					? sprintf( __( 'At least %s hours must have elapsed since that date.', 'codelinden-elementor-form-addon' ), $param )
					: null;
			},
		) );

		// ------------------------------------------------------------------ //
		// Password complexity rules
		// ------------------------------------------------------------------ //

		self::register( array(
			'key'               => 'require_uppercase',
			'label'             => __( 'Require uppercase', 'codelinden-elementor-form-addon' ),
			'description'       => __( 'Value must contain at least one uppercase letter.', 'codelinden-elementor-form-addon' ),
			'applies_to'        => array( 'text', 'password' ),
			'has_value'         => false,
			'value_type'        => 'none',
			'value_label'       => '',
			'value_placeholder' => '',
			'value_options'     => array(),
			'default_message'   => __( 'Must contain at least one uppercase letter.', 'codelinden-elementor-form-addon' ),
			'server'            => true,
			'client'            => true,
			'handler'           => static function ( $value ) {
				return preg_match( '/[A-Z]/', (string) $value )
					? null
					: __( 'Must contain at least one uppercase letter.', 'codelinden-elementor-form-addon' );
			},
		) );

		self::register( array(
			'key'               => 'require_number_char',
			'label'             => __( 'Require a number', 'codelinden-elementor-form-addon' ),
			'description'       => __( 'Value must contain at least one digit.', 'codelinden-elementor-form-addon' ),
			'applies_to'        => array( 'text', 'password' ),
			'has_value'         => false,
			'value_type'        => 'none',
			'value_label'       => '',
			'value_placeholder' => '',
			'value_options'     => array(),
			'default_message'   => __( 'Must contain at least one number.', 'codelinden-elementor-form-addon' ),
			'server'            => true,
			'client'            => true,
			'handler'           => static function ( $value ) {
				return preg_match( '/[0-9]/', (string) $value )
					? null
					: __( 'Must contain at least one number.', 'codelinden-elementor-form-addon' );
			},
		) );

		self::register( array(
			'key'               => 'require_special',
			'label'             => __( 'Require special character', 'codelinden-elementor-form-addon' ),
			'description'       => __( 'Value must contain at least one non-alphanumeric character.', 'codelinden-elementor-form-addon' ),
			'applies_to'        => array( 'text', 'password' ),
			'has_value'         => false,
			'value_type'        => 'none',
			'value_label'       => '',
			'value_placeholder' => '',
			'value_options'     => array(),
			'default_message'   => __( 'Must contain at least one special character.', 'codelinden-elementor-form-addon' ),
			'server'            => true,
			'client'            => true,
			'handler'           => static function ( $value ) {
				return preg_match( '/[^A-Za-z0-9]/', (string) $value )
					? null
					: __( 'Must contain at least one special character.', 'codelinden-elementor-form-addon' );
			},
		) );

		self::register( array(
			'key'               => 'password_strength',
			'label'             => __( 'Password strength', 'codelinden-elementor-form-addon' ),
			'description'       => __( 'Password must meet a minimum complexity level.', 'codelinden-elementor-form-addon' ),
			'applies_to'        => array( 'password' ),
			'has_value'         => true,
			'value_type'        => 'select',
			'value_label'       => __( 'Minimum strength', 'codelinden-elementor-form-addon' ),
			'value_placeholder' => '',
			'value_options'     => array(
				array( 'value' => 'weak',   'label' => __( 'Weak (6+ chars)', 'codelinden-elementor-form-addon' ) ),
				array( 'value' => 'medium', 'label' => __( 'Medium (8+ chars, upper + lower + number)', 'codelinden-elementor-form-addon' ) ),
				array( 'value' => 'strong', 'label' => __( 'Strong (10+ chars, upper + lower + number + special)', 'codelinden-elementor-form-addon' ) ),
			),
			'default_message'   => __( 'Password is not strong enough.', 'codelinden-elementor-form-addon' ),
			'server'            => true,
			'client'            => true,
			'handler'           => static function ( $value, $param ) {
				$pw     = (string) $value;
				$level  = $param ?: 'weak';
				$errors = array();

				if ( 'medium' === $level || 'strong' === $level ) {
					if ( strlen( $pw ) < 8 ) {
						$errors[] = 'length';
					}
					if ( ! preg_match( '/[A-Z]/', $pw ) || ! preg_match( '/[a-z]/', $pw ) ) {
						$errors[] = 'case';
					}
					if ( ! preg_match( '/[0-9]/', $pw ) ) {
						$errors[] = 'number';
					}
				}

				if ( 'strong' === $level ) {
					if ( strlen( $pw ) < 10 ) {
						$errors[] = 'length';
					}
					if ( ! preg_match( '/[^A-Za-z0-9]/', $pw ) ) {
						$errors[] = 'special';
					}
				}

				if ( 'weak' === $level && strlen( $pw ) < 6 ) {
					$errors[] = 'length';
				}

				return empty( $errors )
					? null
					: __( 'Password is not strong enough.', 'codelinden-elementor-form-addon' );
			},
		) );

		// ------------------------------------------------------------------ //
		// Cross-field match
		// ------------------------------------------------------------------ //

		self::register( array(
			'key'               => 'confirm_password',
			'label'             => __( 'Confirm password', 'codelinden-elementor-form-addon' ),
			'description'       => __( 'Value must match another field (specify the source field ID).', 'codelinden-elementor-form-addon' ),
			'applies_to'        => array( 'text', 'password', 'email' ),
			'has_value'         => true,
			'value_type'        => 'text',
			'value_label'       => __( 'Source field ID', 'codelinden-elementor-form-addon' ),
			'value_placeholder' => 'e.g. password_field_id',
			'value_options'     => array(),
			'default_message'   => __( 'Passwords do not match.', 'codelinden-elementor-form-addon' ),
			'server'            => true,
			'client'            => true,
			'handler'           => static function ( $value, $param, array $field, array $all_data ) {
				if ( ! $param ) {
					return null;
				}
				$main_value = $all_data[ $param ] ?? '';
				return (string) $value !== (string) $main_value
					? __( 'Passwords do not match.', 'codelinden-elementor-form-addon' )
					: null;
			},
		) );

		// ------------------------------------------------------------------ //
		// Text content rules (used by seeded fixture forms)
		// ------------------------------------------------------------------ //

		self::register( array(
			'key'               => 'alpha_only',
			'label'             => __( 'Letters only', 'codelinden-elementor-form-addon' ),
			'description'       => __( 'Value may contain letters and spaces only.', 'codelinden-elementor-form-addon' ),
			'applies_to'        => $text_types,
			'has_value'         => false,
			'value_type'        => 'none',
			'value_label'       => '',
			'value_placeholder' => '',
			'value_options'     => array(),
			'default_message'   => __( 'Letters only please.', 'codelinden-elementor-form-addon' ),
			'server'            => true,
			'client'            => true,
			'handler'           => static function ( $value ) {
				return preg_match( '/^[\\p{L}\\s]+$/u', (string) $value )
					? null
					: __( 'Letters only please.', 'codelinden-elementor-form-addon' );
			},
		) );

		self::register( array(
			'key'               => 'alphanumeric',
			'label'             => __( 'Alphanumeric', 'codelinden-elementor-form-addon' ),
			'description'       => __( 'Letters, numbers, and underscores only.', 'codelinden-elementor-form-addon' ),
			'applies_to'        => $text_types,
			'has_value'         => false,
			'value_type'        => 'none',
			'value_label'       => '',
			'value_placeholder' => '',
			'value_options'     => array(),
			'default_message'   => __( 'Letters, numbers and underscores only.', 'codelinden-elementor-form-addon' ),
			'server'            => true,
			'client'            => true,
			'handler'           => static function ( $value ) {
				return preg_match( '/^[a-zA-Z0-9_]+$/', (string) $value )
					? null
					: __( 'Letters, numbers and underscores only.', 'codelinden-elementor-form-addon' );
			},
		) );

		self::register( array(
			'key'               => 'no_spaces',
			'label'             => __( 'No spaces', 'codelinden-elementor-form-addon' ),
			'description'       => __( 'Value must not contain spaces.', 'codelinden-elementor-form-addon' ),
			'applies_to'        => $text_types,
			'has_value'         => false,
			'value_type'        => 'none',
			'value_label'       => '',
			'value_placeholder' => '',
			'value_options'     => array(),
			'default_message'   => __( 'Spaces are not allowed.', 'codelinden-elementor-form-addon' ),
			'server'            => true,
			'client'            => true,
			'handler'           => static function ( $value ) {
				return false !== strpos( (string) $value, ' ' )
					? __( 'Spaces are not allowed.', 'codelinden-elementor-form-addon' )
					: null;
			},
		) );

		self::register( array(
			'key'               => 'min_words',
			'label'             => __( 'Minimum words', 'codelinden-elementor-form-addon' ),
			'description'       => __( 'Text must contain at least this many words.', 'codelinden-elementor-form-addon' ),
			'applies_to'        => array( 'text', 'textarea' ),
			'has_value'         => true,
			'value_type'        => 'number',
			'value_label'       => __( 'Min words', 'codelinden-elementor-form-addon' ),
			'value_placeholder' => 'e.g. 5',
			'value_options'     => array(),
			'default_message'   => __( 'Please write at least {value} words.', 'codelinden-elementor-form-addon' ),
			'server'            => true,
			'client'            => true,
			'handler'           => static function ( $value, $param ) {
				$min   = (int) $param;
				$words = preg_split( '/\\s+/u', trim( (string) $value ), -1, PREG_SPLIT_NO_EMPTY );
				$count = is_array( $words ) ? count( $words ) : 0;
				return $count < $min
					? sprintf( __( 'Please write at least %d words.', 'codelinden-elementor-form-addon' ), $min )
					: null;
			},
		) );

		self::register( array(
			'key'               => 'max_words',
			'label'             => __( 'Maximum words', 'codelinden-elementor-form-addon' ),
			'description'       => __( 'Text must not exceed this many words.', 'codelinden-elementor-form-addon' ),
			'applies_to'        => array( 'text', 'textarea' ),
			'has_value'         => true,
			'value_type'        => 'number',
			'value_label'       => __( 'Max words', 'codelinden-elementor-form-addon' ),
			'value_placeholder' => 'e.g. 100',
			'value_options'     => array(),
			'default_message'   => __( 'Text must be {value} words or fewer.', 'codelinden-elementor-form-addon' ),
			'server'            => true,
			'client'            => true,
			'handler'           => static function ( $value, $param ) {
				$max   = (int) $param;
				$words = preg_split( '/\\s+/u', trim( (string) $value ), -1, PREG_SPLIT_NO_EMPTY );
				$count = is_array( $words ) ? count( $words ) : 0;
				return $count > $max
					? sprintf( __( 'Text must be %d words or fewer.', 'codelinden-elementor-form-addon' ), $max )
					: null;
			},
		) );

		self::register( array(
			'key'               => 'no_html',
			'label'             => __( 'No HTML', 'codelinden-elementor-form-addon' ),
			'description'       => __( 'HTML tags are not permitted.', 'codelinden-elementor-form-addon' ),
			'applies_to'        => array( 'text', 'textarea' ),
			'has_value'         => false,
			'value_type'        => 'none',
			'value_label'       => '',
			'value_placeholder' => '',
			'value_options'     => array(),
			'default_message'   => __( 'HTML tags are not permitted.', 'codelinden-elementor-form-addon' ),
			'server'            => true,
			'client'            => true,
			'handler'           => static function ( $value ) {
				return strip_tags( (string) $value ) !== (string) $value
					? __( 'HTML tags are not permitted.', 'codelinden-elementor-form-addon' )
					: null;
			},
		) );

		self::register( array(
			'key'               => 'no_urls',
			'label'             => __( 'No URLs', 'codelinden-elementor-form-addon' ),
			'description'       => __( 'URLs are not permitted in this field.', 'codelinden-elementor-form-addon' ),
			'applies_to'        => array( 'text', 'textarea' ),
			'has_value'         => false,
			'value_type'        => 'none',
			'value_label'       => '',
			'value_placeholder' => '',
			'value_options'     => array(),
			'default_message'   => __( 'Please do not include URLs in your bio.', 'codelinden-elementor-form-addon' ),
			'server'            => true,
			'client'            => true,
			'handler'           => static function ( $value ) {
				return preg_match( '#https?://|www\\.\\w#i', (string) $value )
					? __( 'Please do not include URLs in your bio.', 'codelinden-elementor-form-addon' )
					: null;
			},
		) );

		self::register( array(
			'key'               => 'phone',
			'label'             => __( 'Phone number', 'codelinden-elementor-form-addon' ),
			'description'       => __( 'Value must look like a valid phone number.', 'codelinden-elementor-form-addon' ),
			'applies_to'        => array( 'text', 'phone' ),
			'has_value'         => false,
			'value_type'        => 'none',
			'value_label'       => '',
			'value_placeholder' => '',
			'value_options'     => array(),
			'default_message'   => __( 'Please enter a valid phone number.', 'codelinden-elementor-form-addon' ),
			'server'            => true,
			'client'            => true,
			'handler'           => static function ( $value ) {
				$clean = preg_replace( '/[^0-9+]/', '', (string) $value );
				return ( strlen( $clean ) >= 8 && preg_match( '/^\\+?[0-9]{8,15}$/', $clean ) )
					? null
					: __( 'Please enter a valid phone number.', 'codelinden-elementor-form-addon' );
			},
		) );

		// ------------------------------------------------------------------ //
		// Checkbox rules
		// ------------------------------------------------------------------ //

		self::register( array(
			'key'               => 'checkbox_min',
			'label'             => __( 'Minimum selections', 'codelinden-elementor-form-addon' ),
			'description'       => __( 'At least this many checkboxes must be ticked.', 'codelinden-elementor-form-addon' ),
			'applies_to'        => array( 'checkbox' ),
			'has_value'         => true,
			'value_type'        => 'number',
			'value_label'       => __( 'Min selections', 'codelinden-elementor-form-addon' ),
			'value_placeholder' => 'e.g. 1',
			'value_options'     => array(),
			'default_message'   => __( 'Please select at least {value} option(s).', 'codelinden-elementor-form-addon' ),
			'server'            => true,
			'client'            => true,
			'handler'           => static function ( $value, $param ) {
				$min  = (int) $param;
				$arr  = is_array( $value ) ? $value : ( $value ? array( $value ) : array() );
				$count = count( array_filter( $arr ) );
				return $count < $min
					/* translators: %d = minimum selection count */
					? sprintf( __( 'Please select at least %d option(s).', 'codelinden-elementor-form-addon' ), $min )
					: null;
			},
		) );

		self::register( array(
			'key'               => 'checkbox_max',
			'label'             => __( 'Maximum selections', 'codelinden-elementor-form-addon' ),
			'description'       => __( 'No more than this many checkboxes may be ticked.', 'codelinden-elementor-form-addon' ),
			'applies_to'        => array( 'checkbox' ),
			'has_value'         => true,
			'value_type'        => 'number',
			'value_label'       => __( 'Max selections', 'codelinden-elementor-form-addon' ),
			'value_placeholder' => 'e.g. 3',
			'value_options'     => array(),
			'default_message'   => __( 'Please select no more than {value} option(s).', 'codelinden-elementor-form-addon' ),
			'server'            => true,
			'client'            => true,
			'handler'           => static function ( $value, $param ) {
				$max  = (int) $param;
				$arr  = is_array( $value ) ? $value : ( $value ? array( $value ) : array() );
				$count = count( array_filter( $arr ) );
				return $count > $max
					/* translators: %d = maximum selection count */
					? sprintf( __( 'Please select no more than %d option(s).', 'codelinden-elementor-form-addon' ), $max )
					: null;
			},
		) );

		self::register( array(
			'key'               => 'min_checked',
			'label'             => __( 'Minimum checked', 'codelinden-elementor-form-addon' ),
			'description'       => __( 'Alias for checkbox_min — at least N options must be selected.', 'codelinden-elementor-form-addon' ),
			'applies_to'        => array( 'checkbox' ),
			'has_value'         => true,
			'value_type'        => 'number',
			'value_label'       => __( 'Min selections', 'codelinden-elementor-form-addon' ),
			'value_placeholder' => 'e.g. 1',
			'value_options'     => array(),
			'default_message'   => __( 'Please select at least {value} option(s).', 'codelinden-elementor-form-addon' ),
			'server'            => true,
			'client'            => true,
			'handler'           => static function ( $value, $param ) {
				$min   = (int) $param;
				$arr   = is_array( $value ) ? $value : ( $value ? array( $value ) : array() );
				$count = count( array_filter( $arr ) );
				return $count < $min
					? sprintf( __( 'Please select at least %d option(s).', 'codelinden-elementor-form-addon' ), $min )
					: null;
			},
		) );

		self::register( array(
			'key'               => 'max_checked',
			'label'             => __( 'Maximum checked', 'codelinden-elementor-form-addon' ),
			'description'       => __( 'Alias for checkbox_max — no more than N options may be selected.', 'codelinden-elementor-form-addon' ),
			'applies_to'        => array( 'checkbox' ),
			'has_value'         => true,
			'value_type'        => 'number',
			'value_label'       => __( 'Max selections', 'codelinden-elementor-form-addon' ),
			'value_placeholder' => 'e.g. 3',
			'value_options'     => array(),
			'default_message'   => __( 'Please select no more than {value} option(s).', 'codelinden-elementor-form-addon' ),
			'server'            => true,
			'client'            => true,
			'handler'           => static function ( $value, $param ) {
				$max   = (int) $param;
				$arr   = is_array( $value ) ? $value : ( $value ? array( $value ) : array() );
				$count = count( array_filter( $arr ) );
				return $count > $max
					? sprintf( __( 'Please select no more than %d option(s).', 'codelinden-elementor-form-addon' ), $max )
					: null;
			},
		) );

		// ------------------------------------------------------------------ //
		// File rules (server-side)
		// ------------------------------------------------------------------ //

		self::register( array(
			'key'               => 'file_type',
			'label'             => __( 'Allowed file types', 'codelinden-elementor-form-addon' ),
			'description'       => __( 'Comma-separated list of allowed extensions, e.g. pdf,jpg,png', 'codelinden-elementor-form-addon' ),
			'applies_to'        => array( 'file', 'multi_file' ),
			'has_value'         => true,
			'value_type'        => 'text',
			'value_label'       => __( 'Allowed extensions', 'codelinden-elementor-form-addon' ),
			'value_placeholder' => 'pdf,jpg,png',
			'value_options'     => array(),
			'default_message'   => __( 'File type not allowed. Permitted: {value}.', 'codelinden-elementor-form-addon' ),
			'server'            => true,
			'client'            => false,
			'handler'           => static function ( $value, $param ) {
				if ( ! $param || ! is_array( $value ) ) {
					return null;
				}
				$allowed = array_map( 'trim', explode( ',', strtolower( (string) $param ) ) );
				$name    = strtolower( $value['name'] ?? '' );
				$ext     = pathinfo( $name, PATHINFO_EXTENSION );
				return in_array( $ext, $allowed, true )
					? null
					/* translators: %s = allowed file extensions */
					: sprintf( __( 'File type not allowed. Permitted: %s.', 'codelinden-elementor-form-addon' ), $param );
			},
		) );

		self::register( array(
			'key'               => 'file_size_max',
			'label'             => __( 'Max file size', 'codelinden-elementor-form-addon' ),
			'description'       => __( 'Maximum file size in megabytes.', 'codelinden-elementor-form-addon' ),
			'applies_to'        => array( 'file', 'multi_file' ),
			'has_value'         => true,
			'value_type'        => 'number',
			'value_label'       => __( 'Max size (MB)', 'codelinden-elementor-form-addon' ),
			'value_placeholder' => 'e.g. 5',
			'value_options'     => array(),
			'default_message'   => __( 'File exceeds the maximum size of {value} MB.', 'codelinden-elementor-form-addon' ),
			'server'            => true,
			'client'            => false,
			'handler'           => static function ( $value, $param ) {
				if ( ! $param || ! is_array( $value ) ) {
					return null;
				}
				$max_bytes = (float) $param * 1048576;
				return ( $value['size'] ?? 0 ) > $max_bytes
					/* translators: %s = maximum size in MB */
					? sprintf( __( 'File exceeds the maximum size of %s MB.', 'codelinden-elementor-form-addon' ), $param )
					: null;
			},
		) );

		self::register( array(
			'key'               => 'file_count_max',
			'label'             => __( 'Max file count', 'codelinden-elementor-form-addon' ),
			'description'       => __( 'Maximum number of files that may be uploaded.', 'codelinden-elementor-form-addon' ),
			'applies_to'        => array( 'multi_file' ),
			'has_value'         => true,
			'value_type'        => 'number',
			'value_label'       => __( 'Max files', 'codelinden-elementor-form-addon' ),
			'value_placeholder' => 'e.g. 5',
			'value_options'     => array(),
			'default_message'   => __( 'Maximum {value} file(s) allowed.', 'codelinden-elementor-form-addon' ),
			'server'            => true,
			'client'            => false,
			'handler'           => static function ( $value, $param ) {
				if ( ! $param || ! is_array( $value ) ) {
					return null;
				}
				$count = count( (array) $value );
				return $count > (int) $param
					/* translators: %d = maximum file count */
					? sprintf( __( 'Maximum %d file(s) allowed.', 'codelinden-elementor-form-addon' ), (int) $param )
					: null;
			},
		) );

		// ------------------------------------------------------------------ //
		// API / external result
		// ------------------------------------------------------------------ //

		self::register( array(
			'key'               => 'api_result',
			'label'             => __( 'API / live-check result', 'codelinden-elementor-form-addon' ),
			'description'       => __( 'Field must have passed its configured live-check endpoint (server-side verification).', 'codelinden-elementor-form-addon' ),
			'applies_to'        => array( '*' ),
			'has_value'         => false,
			'value_type'        => 'none',
			'value_label'       => '',
			'value_placeholder' => '',
			'value_options'     => array(),
			'default_message'   => __( 'This value could not be verified.', 'codelinden-elementor-form-addon' ),
			'server'            => true,
			'client'            => false,
			'handler'           => static function ( $value, $param, array $field, array $all_data ) {
				/**
				 * Filter: return an error string to block submission, or null to pass.
				 *
				 * @param string|null $error     null = passes.
				 * @param mixed       $value     The submitted field value.
				 * @param array       $field     Field config.
				 * @param array       $all_data  All submitted form data.
				 */
				return apply_filters( 'clefa_api_result_validation', null, $value, $field, $all_data );
			},
		) );

		// ------------------------------------------------------------------ //
		// Repeater row-count rules
		// ------------------------------------------------------------------ //

		self::register( array(
			'key'               => 'repeater_min_rows',
			'label'             => __( 'Min repeater rows', 'codelinden-elementor-form-addon' ),
			'description'       => __( 'Repeater must have at least this many rows.', 'codelinden-elementor-form-addon' ),
			'applies_to'        => array( 'repeater' ),
			'has_value'         => true,
			'value_type'        => 'number',
			'value_label'       => __( 'Min rows', 'codelinden-elementor-form-addon' ),
			'value_placeholder' => 'e.g. 1',
			'value_options'     => array(),
			'default_message'   => __( 'At least {value} row(s) required.', 'codelinden-elementor-form-addon' ),
			'server'            => true,
			'client'            => false,
			'handler'           => static function ( $value, $param ) {
				$min  = (int) $param;
				$rows = is_array( $value ) ? count( $value ) : 0;
				return $rows < $min
					/* translators: %d = minimum row count */
					? sprintf( __( 'At least %d row(s) required.', 'codelinden-elementor-form-addon' ), $min )
					: null;
			},
		) );

		self::register( array(
			'key'               => 'repeater_max_rows',
			'label'             => __( 'Max repeater rows', 'codelinden-elementor-form-addon' ),
			'description'       => __( 'Repeater must not exceed this many rows.', 'codelinden-elementor-form-addon' ),
			'applies_to'        => array( 'repeater' ),
			'has_value'         => true,
			'value_type'        => 'number',
			'value_label'       => __( 'Max rows', 'codelinden-elementor-form-addon' ),
			'value_placeholder' => 'e.g. 10',
			'value_options'     => array(),
			'default_message'   => __( 'Maximum {value} row(s) allowed.', 'codelinden-elementor-form-addon' ),
			'server'            => true,
			'client'            => false,
			'handler'           => static function ( $value, $param ) {
				$max  = (int) $param;
				$rows = is_array( $value ) ? count( $value ) : 0;
				return $rows > $max
					/* translators: %d = maximum row count */
					? sprintf( __( 'Maximum %d row(s) allowed.', 'codelinden-elementor-form-addon' ), $max )
					: null;
			},
		) );
	}
}
