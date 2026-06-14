<?php
/**
 * In-memory WordPress stubs for form action PHPUnit tests.
 */

if ( ! function_exists( 'clefa_test_reset_action_stores' ) ) {
	function clefa_test_reset_action_stores() {
		global $clefa_test_posts, $clefa_test_post_meta, $clefa_test_users, $clefa_test_user_meta,
			$clefa_test_terms, $clefa_test_post_terms, $clefa_test_taxonomies, $clefa_test_roles,
			$clefa_test_acf_fields, $clefa_test_products, $clefa_test_current_user_id, $clefa_test_user_caps,
			$clefa_test_auth_cookie, $clefa_test_last_http_request, $clefa_test_password_resets,
			$clefa_test_submissions, $clefa_test_mails, $clefa_test_transients, $clefa_test_audit_events;

		$clefa_test_posts              = array();
		$clefa_test_post_meta          = array();
		$clefa_test_users              = array();
		$clefa_test_user_meta          = array();
		$clefa_test_terms              = array();
		$clefa_test_post_terms         = array();
		$clefa_test_taxonomies         = array( 'category', 'post_tag', 'product_cat', 'product_tag' );
		$clefa_test_roles              = array( 'subscriber' => true, 'editor' => true, 'customer' => true );
		$clefa_test_acf_fields         = array();
		$clefa_test_products           = array();
		$clefa_test_current_user_id    = 0;
		$clefa_test_user_caps          = array();
		$clefa_test_auth_cookie        = null;
		$clefa_test_last_http_request  = null;
		$clefa_test_password_resets    = array();
		$clefa_test_submissions        = array();
		$clefa_test_mails              = array();
		$clefa_test_transients         = array();
		$clefa_test_audit_events       = array();
	}
}

clefa_test_reset_action_stores();

if ( ! class_exists( 'WP_Error' ) || ! method_exists( 'WP_Error', 'get_error_message' ) ) {
	class WP_Error {
		private $code;
		private $message;
		private $data = array();

		public function __construct( $code = '', $message = '', $data = array() ) {
			$this->code    = $code;
			$this->message = $message;
			$this->data    = is_array( $data ) ? $data : array();
		}

		public function get_error_message() {
			return $this->message;
		}

		public function get_error_code() {
			return $this->code;
		}

		public function get_error_data( $code = '' ) {
			return $this->data;
		}
	}
}

if ( ! function_exists( 'sanitize_user' ) ) {
	function sanitize_user( $username ) {
		return preg_replace( '/[^a-z0-9_\-\.@]/i', '', (string) $username );
	}
}

if ( ! function_exists( 'username_exists' ) ) {
	function username_exists( $username ) {
		global $clefa_test_users;
		foreach ( $clefa_test_users as $user ) {
			if ( $user->user_login === $username ) {
				return $user->ID;
			}
		}
		return false;
	}
}

if ( ! function_exists( 'email_exists' ) ) {
	function email_exists( $email ) {
		global $clefa_test_users;
		foreach ( $clefa_test_users as $user ) {
			if ( $user->user_email === $email ) {
				return $user->ID;
			}
		}
		return false;
	}
}

if ( ! function_exists( 'wp_insert_user' ) ) {
	function wp_insert_user( array $userdata ) {
		global $clefa_test_users;
		$id = count( $clefa_test_users ) + 1;
		$user = (object) array_merge(
			array(
				'ID'           => $id,
				'user_login'   => '',
				'user_email'   => '',
				'user_pass'    => '',
				'display_name' => '',
				'first_name'   => '',
				'last_name'    => '',
				'roles'        => array( 'subscriber' ),
			),
			$userdata
		);
		$user->ID = $id;
		if ( ! empty( $userdata['role'] ) ) {
			$user->roles = array( $userdata['role'] );
		}
		$clefa_test_users[ $id ] = $user;
		return $id;
	}
}

if ( ! function_exists( 'wp_update_user' ) ) {
	function wp_update_user( array $userdata ) {
		global $clefa_test_users;
		$id = (int) ( $userdata['ID'] ?? 0 );
		if ( ! isset( $clefa_test_users[ $id ] ) ) {
			return new WP_Error( 'invalid_user', 'Invalid user.' );
		}
		foreach ( $userdata as $key => $val ) {
			if ( 'ID' !== $key ) {
				$clefa_test_users[ $id ]->$key = $val;
			}
		}
		return $id;
	}
}

if ( ! function_exists( 'get_user_by' ) ) {
	function get_user_by( $field, $value ) {
		global $clefa_test_users;
		foreach ( $clefa_test_users as $user ) {
			if ( 'id' === $field && (int) $user->ID === (int) $value ) {
				return $user;
			}
			if ( 'login' === $field && $user->user_login === $value ) {
				return $user;
			}
			if ( 'email' === $field && $user->user_email === $value ) {
				return $user;
			}
		}
		return false;
	}
}

if ( ! function_exists( 'wp_get_current_user' ) ) {
	function wp_get_current_user() {
		global $clefa_test_current_user_id, $clefa_test_users;
		$id = get_current_user_id();
		if ( $id && isset( $clefa_test_users[ $id ] ) ) {
			return $clefa_test_users[ $id ];
		}
		return (object) array( 'ID' => 0, 'roles' => array(), 'user_email' => '', 'user_login' => '' );
	}
}

if ( ! function_exists( 'wp_set_current_user' ) ) {
	function wp_set_current_user( $user_id ) {
		global $clefa_test_current_user_id;
		$clefa_test_current_user_id = (int) $user_id;
	}
}

if ( ! function_exists( 'wp_set_auth_cookie' ) ) {
	function wp_set_auth_cookie( $user_id ) {
		global $clefa_test_auth_cookie;
		$clefa_test_auth_cookie = (int) $user_id;
	}
}

if ( ! function_exists( 'wp_signon' ) ) {
	function wp_signon( array $credentials, $secure = false ) {
		global $clefa_test_users;
		$login    = $credentials['user_login'] ?? '';
		$password = $credentials['user_password'] ?? '';
		foreach ( $clefa_test_users as $user ) {
			if ( $user->user_login === $login && $user->user_pass === $password ) {
				wp_set_current_user( $user->ID );
				wp_set_auth_cookie( $user->ID );
				return $user;
			}
		}
		return new WP_Error( 'invalid_login', 'Invalid username or password.' );
	}
}

if ( ! function_exists( 'is_ssl' ) ) {
	function is_ssl() {
		return false;
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $cap, ...$args ) {
		global $clefa_test_user_caps, $clefa_test_current_user_id;
		$uid = get_current_user_id();
		if ( isset( $clefa_test_user_caps[ $uid ][ $cap ] ) ) {
			return (bool) $clefa_test_user_caps[ $uid ][ $cap ];
		}
		if ( 'edit_post' === $cap || 'publish_posts' === $cap || 'edit_user' === $cap ) {
			return $uid > 0;
		}
		return false;
	}
}

if ( ! function_exists( 'wp_insert_post' ) ) {
	function wp_insert_post( array $postarr, $wp_error = false ) {
		global $clefa_test_posts;
		if ( ! empty( $postarr['post_title'] ) && 'FAIL_INSERT' === $postarr['post_title'] ) {
			return $wp_error ? new WP_Error( 'insert_failed', 'Could not insert post.' ) : 0;
		}
		$id = count( $clefa_test_posts ) + 1;
		$post = (object) array_merge(
			array(
				'ID'           => $id,
				'post_type'    => 'post',
				'post_status'  => 'draft',
				'post_title'   => '',
				'post_content' => '',
				'post_excerpt' => '',
				'post_author'  => 1,
			),
			$postarr
		);
		$post->ID = $id;
		$clefa_test_posts[ $id ] = $post;
		return $id;
	}
}

if ( ! function_exists( 'get_post' ) ) {
	function get_post( $post_id ) {
		global $clefa_test_posts;
		return $clefa_test_posts[ (int) $post_id ] ?? null;
	}
}

if ( ! function_exists( 'update_post_meta' ) ) {
	function update_post_meta( $post_id, $key, $value ) {
		global $clefa_test_post_meta;
		$clefa_test_post_meta[ (int) $post_id ][ $key ] = $value;
		return true;
	}
}

if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( $post_id, $key, $single = false ) {
		global $clefa_test_post_meta;
		$val = $clefa_test_post_meta[ (int) $post_id ][ $key ] ?? null;
		if ( $single ) {
			return is_array( $val ) ? $val : ( $val ?? '' );
		}
		return isset( $val ) ? array( $val ) : array();
	}
}

if ( ! function_exists( 'wp_set_object_terms' ) ) {
	function wp_set_object_terms( $post_id, $terms, $taxonomy, $append = false ) {
		global $clefa_test_post_terms;
		$key = (int) $post_id . ':' . $taxonomy;
		if ( ! $append ) {
			$clefa_test_post_terms[ $key ] = array();
		}
		foreach ( (array) $terms as $term ) {
			$clefa_test_post_terms[ $key ][] = (int) $term;
		}
		return array_map( 'intval', (array) $terms );
	}
}

if ( ! function_exists( 'wp_set_post_terms' ) ) {
	function wp_set_post_terms( $post_id, $terms, $taxonomy, $append = false ) {
		return wp_set_object_terms( $post_id, $terms, $taxonomy, $append );
	}
}

if ( ! function_exists( 'taxonomy_exists' ) ) {
	function taxonomy_exists( $taxonomy ) {
		global $clefa_test_taxonomies;
		return in_array( $taxonomy, $clefa_test_taxonomies, true );
	}
}

if ( ! function_exists( 'term_exists' ) ) {
	function term_exists( $term, $taxonomy = '' ) {
		global $clefa_test_terms;
		foreach ( $clefa_test_terms as $id => $row ) {
			if ( (int) $term === $id || (string) $term === $row['name'] || (string) $term === $row['slug'] ) {
				if ( ! $taxonomy || $row['taxonomy'] === $taxonomy ) {
					return array( 'term_id' => $id );
				}
			}
		}
		return null;
	}
}

if ( ! function_exists( 'get_term_by' ) ) {
	function get_term_by( $field, $value, $taxonomy ) {
		global $clefa_test_terms;
		foreach ( $clefa_test_terms as $id => $row ) {
			if ( $row['taxonomy'] !== $taxonomy ) {
				continue;
			}
			if ( ( 'slug' === $field && $row['slug'] === $value ) || ( 'name' === $field && $row['name'] === $value ) || ( 'id' === $field && (int) $id === (int) $value ) ) {
				return (object) array(
					'term_id'  => $id,
					'name'     => $row['name'],
					'slug'     => $row['slug'],
					'taxonomy' => $taxonomy,
				);
			}
		}
		return false;
	}
}

if ( ! function_exists( 'wp_insert_term' ) ) {
	function wp_insert_term( $term, $taxonomy ) {
		global $clefa_test_terms;
		$id = count( $clefa_test_terms ) + 1;
		$clefa_test_terms[ $id ] = array(
			'name'     => $term,
			'slug'     => sanitize_key( $term ),
			'taxonomy' => $taxonomy,
		);
		return array( 'term_id' => $id );
	}
}

if ( ! function_exists( 'wp_remove_object_terms' ) ) {
	function wp_remove_object_terms( $post_id, $terms, $taxonomy ) {
		global $clefa_test_post_terms;
		$key = (int) $post_id . ':' . $taxonomy;
		if ( empty( $clefa_test_post_terms[ $key ] ) ) {
			return array();
		}
		$remove = array_map( 'intval', (array) $terms );
		$clefa_test_post_terms[ $key ] = array_values( array_diff( $clefa_test_post_terms[ $key ], $remove ) );
		return $clefa_test_post_terms[ $key ];
	}
}

if ( ! function_exists( 'get_role' ) ) {
	function get_role( $role ) {
		global $clefa_test_roles;
		return isset( $clefa_test_roles[ $role ] ) ? (object) array( 'name' => $role ) : null;
	}
}

if ( ! class_exists( 'WP_User' ) ) {
	class WP_User {
		public $ID;
		public $roles = array();

		public function __construct( $user_id ) {
			global $clefa_test_users;
			$this->ID = (int) $user_id;
			if ( isset( $clefa_test_users[ $this->ID ] ) ) {
				$this->roles = $clefa_test_users[ $this->ID ]->roles;
			}
		}

		public function set_role( $role ) {
			global $clefa_test_users;
			$this->roles = array( $role );
			if ( isset( $clefa_test_users[ $this->ID ] ) ) {
				$clefa_test_users[ $this->ID ]->roles = array( $role );
			}
		}

		public function add_role( $role ) {
			global $clefa_test_users;
			if ( ! in_array( $role, $this->roles, true ) ) {
				$this->roles[] = $role;
			}
			if ( isset( $clefa_test_users[ $this->ID ] ) ) {
				$clefa_test_users[ $this->ID ]->roles = $this->roles;
			}
		}
	}
}

if ( ! function_exists( 'update_field' ) ) {
	function update_field( $field, $value, $target ) {
		global $clefa_test_acf_fields;
		$key = is_scalar( $target ) ? (string) $target : '0';
		$clefa_test_acf_fields[ $key ][ $field ] = $value;
		return true;
	}
}

if ( ! function_exists( 'retrieve_password' ) ) {
	function retrieve_password( $user_login ) {
		global $clefa_test_password_resets;
		if ( ! username_exists( $user_login ) ) {
			return new WP_Error( 'invalid_user', 'Invalid user.' );
		}
		$clefa_test_password_resets[] = $user_login;
		return true;
	}
}

if ( ! function_exists( 'wp_remote_post' ) ) {
	function wp_remote_post( $url, $args = array() ) {
		global $clefa_test_last_http_request;
		$clefa_test_last_http_request = array(
			'url'  => $url,
			'args' => $args,
		);
		return array(
			'response' => array( 'code' => 200 ),
			'body'     => '{"ok":true}',
		);
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) {
		return ( $thing instanceof WP_Error );
	}
}

if ( ! function_exists( 'wc_get_product' ) ) {
	function wc_get_product( $product_id ) {
		global $clefa_test_products;
		return $clefa_test_products[ (int) $product_id ] ?? null;
	}
}

if ( ! class_exists( 'WC_Product_Simple' ) ) {
	class WC_Product_Simple {
		private $id = 0;
		private $data = array(
			'name'                => '',
			'description'         => '',
			'regular_price'       => '',
			'sku'                 => '',
			'status'              => 'draft',
			'catalog_visibility'  => 'visible',
		);

		public function set_name( $v ) { $this->data['name'] = $v; }
		public function set_description( $v ) { $this->data['description'] = $v; }
		public function set_regular_price( $v ) { $this->data['regular_price'] = $v; }
		public function set_sku( $v ) { $this->data['sku'] = $v; }
		public function set_status( $v ) { $this->data['status'] = $v; }
		public function set_catalog_visibility( $v ) { $this->data['catalog_visibility'] = $v; }

		public function get_name() { return $this->data['name']; }
		public function get_regular_price() { return $this->data['regular_price']; }
		public function get_sku() { return $this->data['sku']; }
		public function get_status() { return $this->data['status']; }

		public function save() {
			global $clefa_test_products;
			if ( ! $this->id ) {
				$this->id = count( $clefa_test_products ) + 1;
			}
			$clefa_test_products[ $this->id ] = $this;
			return $this->id;
		}

		public function get_id() {
			return $this->id;
		}
	}
}

if ( ! function_exists( 'wp_set_password' ) ) {
	function wp_set_password( $password, $user_id ) {
		global $clefa_test_users;
		$user_id = (int) $user_id;
		if ( ! isset( $clefa_test_users[ $user_id ] ) ) {
			return false;
		}
		$clefa_test_users[ $user_id ]->user_pass = (string) $password;
		return true;
	}
}

if ( ! function_exists( 'set_post_thumbnail' ) ) {
	function set_post_thumbnail( $post_id, $attachment_id ) {
		update_post_meta( $post_id, '_thumbnail_id', $attachment_id );
		return true;
	}
}

if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
	function wp_remote_retrieve_response_code( $response ) {
		return $response['response']['code'] ?? 0;
	}
}

if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
	function wp_remote_retrieve_body( $response ) {
		return $response['body'] ?? '';
	}
}
