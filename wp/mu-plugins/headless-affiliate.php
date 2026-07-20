<?php
/**
 * Plugin Name: Headless Affiliate Portal
 * Description: Affiliate register/login, coupon recovery via description email, and dashboard payload.
 * Version:     0.1.0
 * Author:      WCHS Contributors
 *
 * Coupon matching contract:
 *   - Auto-created coupons store the affiliate email in WC coupon description
 *     (post_excerpt) AND meta `_wchs_affiliate_email`.
 *   - Manually created coupons work the same if the admin puts the affiliate
 *     email alone in the coupon Description field.
 *   - Lookup order: user meta code → coupon meta email → description exact email.
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'rest_api_init',
	static function () {
		register_rest_route(
			'wchs/v1',
			'/affiliate/register',
			[
				'methods'             => 'POST',
				'callback'            => 'wchs_rest_affiliate_register',
				'permission_callback' => '__return_true',
				'args'                => [
					'name'     => [ 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
					'email'    => [ 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_email' ],
					'password' => [ 'required' => true, 'type' => 'string' ],
				],
			]
		);

		register_rest_route(
			'wchs/v1',
			'/affiliate/login',
			[
				'methods'             => 'POST',
				'callback'            => 'wchs_rest_affiliate_login',
				'permission_callback' => '__return_true',
				'args'                => [
					'login'    => [ 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
					'password' => [ 'required' => true, 'type' => 'string' ],
				],
			]
		);

		register_rest_route(
			'wchs/v1',
			'/affiliate/me',
			[
				'methods'             => 'GET',
				'callback'            => 'wchs_rest_affiliate_me',
				'permission_callback' => static function () {
					if ( ! function_exists( 'wchs_current_user_from_cookie' ) ) {
						return false;
					}
					return wchs_current_user_from_cookie() instanceof \WP_User;
				},
			]
		);

		register_rest_route(
			'wchs/v1',
			'/affiliate/forgot-coupon',
			[
				'methods'             => 'POST',
				'callback'            => 'wchs_rest_affiliate_forgot_coupon',
				'permission_callback' => '__return_true',
				'args'                => [
					'email' => [ 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_email' ],
				],
			]
		);

		register_rest_route(
			'wchs/v1',
			'/affiliate/forgot-password',
			[
				'methods'             => 'POST',
				'callback'            => 'wchs_rest_affiliate_forgot_password',
				'permission_callback' => '__return_true',
				'args'                => [
					'login' => [ 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
				],
			]
		);
	}
);

/**
 * Normalize affiliate email for matching.
 */
function wchs_affiliate_normalize_email( string $email ): string {
	return strtolower( sanitize_email( $email ) );
}

/**
 * Build a coupon code like name-15, uniquified on collision.
 */
function wchs_affiliate_make_coupon_code( string $name ): string {
	$slug = strtolower( sanitize_title( $name ) );
	$slug = preg_replace( '/[^a-z0-9]+/', '-', (string) $slug );
	$slug = trim( (string) $slug, '-' );
	if ( '' === $slug ) {
		$slug = 'affiliate';
	}
	if ( strlen( $slug ) > 20 ) {
		$slug = substr( $slug, 0, 20 );
		$slug = rtrim( $slug, '-' );
	}

	$base = $slug . '-15';
	$code = $base;
	$i    = 2;
	while ( wchs_affiliate_coupon_code_exists( $code ) && $i < 50 ) {
		$code = $slug . '-15-' . $i;
		++$i;
	}
	return function_exists( 'wc_format_coupon_code' ) ? wc_format_coupon_code( $code ) : $code;
}

function wchs_affiliate_coupon_code_exists( string $code ): bool {
	if ( ! class_exists( '\WC_Coupon' ) ) {
		return false;
	}
	$coupon = new \WC_Coupon( $code );
	return (bool) $coupon->get_id();
}

/**
 * Find published coupons whose description or meta matches this email.
 *
 * @return \WC_Coupon[]
 */
function wchs_affiliate_find_coupons_by_email( string $email ): array {
	if ( ! class_exists( '\WC_Coupon' ) ) {
		return [];
	}

	$email = wchs_affiliate_normalize_email( $email );
	if ( '' === $email || ! is_email( $email ) ) {
		return [];
	}

	$found = [];

	$meta_q = new \WP_Query(
		[
			'post_type'              => 'shop_coupon',
			'post_status'            => 'publish',
			'posts_per_page'         => 20,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'meta_query'             => [
				[
					'key'   => '_wchs_affiliate_email',
					'value' => $email,
				],
			],
		]
	);
	foreach ( (array) $meta_q->posts as $id ) {
		$coupon = new \WC_Coupon( (int) $id );
		if ( $coupon->get_id() ) {
			$found[ $coupon->get_id() ] = $coupon;
		}
	}

	global $wpdb;
	$ids = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts}
			WHERE post_type = 'shop_coupon'
			AND post_status = 'publish'
			AND LOWER(TRIM(post_excerpt)) = %s
			LIMIT 20",
			$email
		)
	);
	foreach ( (array) $ids as $id ) {
		$id = (int) $id;
		if ( isset( $found[ $id ] ) ) {
			continue;
		}
		$coupon = new \WC_Coupon( $id );
		if ( $coupon->get_id() ) {
			$found[ $coupon->get_id() ] = $coupon;
		}
	}

	return array_values( $found );
}

/**
 * Resolve the coupon linked to a WP user (meta first, then email description).
 */
function wchs_affiliate_resolve_user_coupon( \WP_User $user ): ?\WC_Coupon {
	if ( ! class_exists( '\WC_Coupon' ) ) {
		return null;
	}

	$code = (string) get_user_meta( $user->ID, 'wchs_affiliate_coupon_code', true );
	if ( '' !== $code ) {
		$coupon = new \WC_Coupon( $code );
		if ( $coupon->get_id() ) {
			return $coupon;
		}
	}

	$by_email = wchs_affiliate_find_coupons_by_email( $user->user_email );
	if ( ! empty( $by_email ) ) {
		$coupon = $by_email[0];
		update_user_meta( $user->ID, 'wchs_affiliate_coupon_code', $coupon->get_code() );
		return $coupon;
	}

	return null;
}

/**
 * Create or attach a 15% affiliate coupon; description = email.
 */
function wchs_affiliate_ensure_coupon( int $user_id, string $name, string $email ): \WC_Coupon|\WP_Error {
	if ( ! class_exists( '\WC_Coupon' ) ) {
		return new \WP_Error( 'unavailable', 'Coupons are unavailable.', [ 'status' => 503 ] );
	}

	$email = wchs_affiliate_normalize_email( $email );
	$existing = wchs_affiliate_find_coupons_by_email( $email );
	if ( ! empty( $existing ) ) {
		$coupon = $existing[0];
		$coupon->set_description( $email );
		$coupon->update_meta_data( '_wchs_affiliate_email', $email );
		$coupon->update_meta_data( '_wchs_affiliate_user_id', $user_id );
		$coupon->save();
		update_user_meta( $user_id, 'wchs_affiliate_coupon_code', $coupon->get_code() );
		return $coupon;
	}

	$code   = wchs_affiliate_make_coupon_code( $name );
	$coupon = new \WC_Coupon();
	$coupon->set_code( $code );
	$coupon->set_discount_type( 'percent' );
	$coupon->set_amount( 15 );
	$coupon->set_individual_use( false );
	$coupon->set_usage_limit( 0 );
	$coupon->set_usage_limit_per_user( 0 );
	$coupon->set_limit_usage_to_x_items( null );
	$coupon->set_free_shipping( false );
	$coupon->set_description( $email );
	$coupon->update_meta_data( '_wchs_affiliate_email', $email );
	$coupon->update_meta_data( '_wchs_affiliate_user_id', $user_id );
	$id = $coupon->save();
	if ( ! $id ) {
		return new \WP_Error( 'coupon_create_failed', 'Could not create affiliate coupon.', [ 'status' => 500 ] );
	}

	update_user_meta( $user_id, 'wchs_affiliate_coupon_code', $coupon->get_code() );
	return $coupon;
}

/**
 * Dashboard payload for a logged-in affiliate.
 *
 * @return array<string, mixed>|\WP_Error
 */
function wchs_affiliate_dashboard_payload( \WP_User $user ) {
	$coupon = wchs_affiliate_resolve_user_coupon( $user );
	$stats  = null;
	if ( $coupon && function_exists( 'wchs_affiliate_coupon_payload' ) ) {
		$stats = wchs_affiliate_coupon_payload( $coupon );
	}

	return [
		'user' => [
			'id'           => (int) $user->ID,
			'name'         => $user->display_name,
			'email'        => $user->user_email,
			'username'     => $user->user_login,
			'coupon_code'  => $coupon ? $coupon->get_code() : null,
		],
		'coupon' => $stats,
	];
}

function wchs_rest_affiliate_register( \WP_REST_Request $request ) {
	if ( ! function_exists( 'wchs_rest_rate_limit' ) || ! wchs_rest_rate_limit( 'affiliate_register' ) ) {
		return new \WP_Error( 'rate_limited', 'Too many requests. Please wait a minute and try again.', [ 'status' => 429 ] );
	}

	$name     = trim( (string) $request->get_param( 'name' ) );
	$email    = wchs_affiliate_normalize_email( (string) $request->get_param( 'email' ) );
	$password = (string) $request->get_param( 'password' );

	if ( strlen( $name ) < 2 || strlen( $name ) > 80 ) {
		return new \WP_Error( 'invalid_name', 'Enter your full name.', [ 'status' => 400 ] );
	}
	if ( ! is_email( $email ) ) {
		return new \WP_Error( 'invalid_email', 'Enter a valid email address.', [ 'status' => 400 ] );
	}
	if ( strlen( $password ) < 8 ) {
		return new \WP_Error( 'weak_password', 'Password must be at least 8 characters.', [ 'status' => 400 ] );
	}
	if ( email_exists( $email ) ) {
		return new \WP_Error( 'email_exists', 'An account with that email already exists. Please log in.', [ 'status' => 409 ] );
	}

	$login_base = sanitize_user( current( explode( '@', $email ) ), true );
	if ( '' === $login_base ) {
		$login_base = 'affiliate';
	}
	$login = $login_base;
	$n     = 1;
	while ( username_exists( $login ) && $n < 100 ) {
		$login = $login_base . $n;
		++$n;
	}

	$user_id = wp_insert_user(
		[
			'user_login'   => $login,
			'user_email'   => $email,
			'user_pass'    => $password,
			'display_name' => $name,
			'first_name'   => $name,
			'role'         => get_role( 'customer' ) ? 'customer' : 'subscriber',
		]
	);
	if ( is_wp_error( $user_id ) ) {
		return new \WP_Error( 'register_failed', $user_id->get_error_message(), [ 'status' => 400 ] );
	}

	update_user_meta( (int) $user_id, 'wchs_is_affiliate', '1' );

	$coupon = wchs_affiliate_ensure_coupon( (int) $user_id, $name, $email );
	if ( is_wp_error( $coupon ) ) {
		return $coupon;
	}

	wp_set_current_user( (int) $user_id );
	wp_set_auth_cookie( (int) $user_id, true );
	do_action( 'wp_login', $login, get_userdata( (int) $user_id ) );

	$user = get_userdata( (int) $user_id );
	return rest_ensure_response(
		[
			'ok'      => true,
			'created' => true,
			'data'    => wchs_affiliate_dashboard_payload( $user ),
		]
	);
}

function wchs_rest_affiliate_login( \WP_REST_Request $request ) {
	if ( ! function_exists( 'wchs_rest_rate_limit' ) || ! wchs_rest_rate_limit( 'affiliate_login' ) ) {
		return new \WP_Error( 'rate_limited', 'Too many requests. Please wait a minute and try again.', [ 'status' => 429 ] );
	}

	$login    = trim( (string) $request->get_param( 'login' ) );
	$password = (string) $request->get_param( 'password' );

	if ( '' === $login || '' === $password ) {
		return new \WP_Error( 'invalid_credentials', 'Enter your email/username and password.', [ 'status' => 400 ] );
	}

	if ( is_email( $login ) ) {
		$user_by_email = get_user_by( 'email', $login );
		if ( $user_by_email ) {
			$login = $user_by_email->user_login;
		}
	}

	$user = wp_signon(
		[
			'user_login'    => $login,
			'user_password' => $password,
			'remember'      => true,
		],
		is_ssl()
	);

	if ( is_wp_error( $user ) ) {
		return new \WP_Error( 'invalid_credentials', 'Incorrect username/email or password.', [ 'status' => 401 ] );
	}

	update_user_meta( $user->ID, 'wchs_is_affiliate', '1' );

	// Link a manually created coupon (email in description) if present.
	wchs_affiliate_resolve_user_coupon( $user );

	return rest_ensure_response(
		[
			'ok'   => true,
			'data' => wchs_affiliate_dashboard_payload( $user ),
		]
	);
}

function wchs_rest_affiliate_me( \WP_REST_Request $request ) {
	if ( ! function_exists( 'wchs_rest_rate_limit' ) || ! wchs_rest_rate_limit( 'affiliate_me' ) ) {
		return new \WP_Error( 'rate_limited', 'Too many requests', [ 'status' => 429 ] );
	}

	$user = wchs_current_user_from_cookie();
	if ( ! $user ) {
		return new \WP_Error( 'unauthorized', 'Please log in.', [ 'status' => 401 ] );
	}

	return rest_ensure_response(
		[
			'ok'   => true,
			'data' => wchs_affiliate_dashboard_payload( $user ),
		]
	);
}

function wchs_rest_affiliate_forgot_coupon( \WP_REST_Request $request ) {
	if ( ! function_exists( 'wchs_rest_rate_limit' ) || ! wchs_rest_rate_limit( 'affiliate_forgot' ) ) {
		return new \WP_Error( 'rate_limited', 'Too many requests. Please wait a minute and try again.', [ 'status' => 429 ] );
	}

	$email = wchs_affiliate_normalize_email( (string) $request->get_param( 'email' ) );
	if ( ! is_email( $email ) ) {
		return new \WP_Error( 'invalid_email', 'Enter a valid email address.', [ 'status' => 400 ] );
	}

	$coupons = wchs_affiliate_find_coupons_by_email( $email );
	if ( empty( $coupons ) ) {
		return new \WP_Error( 'not_found', 'No coupon found for that email. Ask your contact to confirm the email is saved in the coupon Description.', [ 'status' => 404 ] );
	}

	$codes = [];
	foreach ( $coupons as $coupon ) {
		$codes[] = $coupon->get_code();
	}

	return rest_ensure_response(
		[
			'ok'     => true,
			'email'  => $email,
			'codes'  => array_values( array_unique( $codes ) ),
			'code'   => $codes[0],
			'message'=> count( $codes ) > 1
				? 'Multiple coupons matched this email.'
				: 'Coupon code found.',
		]
	);
}

function wchs_rest_affiliate_forgot_password( \WP_REST_Request $request ) {
	if ( ! function_exists( 'wchs_rest_rate_limit' ) || ! wchs_rest_rate_limit( 'affiliate_forgot' ) ) {
		return new \WP_Error( 'rate_limited', 'Too many requests. Please wait a minute and try again.', [ 'status' => 429 ] );
	}

	$login = trim( (string) $request->get_param( 'login' ) );
	if ( '' === $login ) {
		return new \WP_Error( 'invalid_login', 'Enter your email or username.', [ 'status' => 400 ] );
	}

	if ( is_email( $login ) ) {
		$user = get_user_by( 'email', $login );
		if ( $user ) {
			$login = $user->user_login;
		}
	}

	// Always return a generic success message to avoid account enumeration.
	$result = retrieve_password( $login );
	if ( is_wp_error( $result ) ) {
		// Still return ok for unknown users; only surface hard failures for known users.
		$codes = $result->get_error_codes();
		if ( in_array( 'invalidcombo', $codes, true ) || in_array( 'invalid_email', $codes, true ) ) {
			return rest_ensure_response(
				[
					'ok'      => true,
					'message' => 'If an account exists for that login, a password reset email has been sent.',
				]
			);
		}
		return new \WP_Error( 'reset_failed', $result->get_error_message(), [ 'status' => 400 ] );
	}

	return rest_ensure_response(
		[
			'ok'      => true,
			'message' => 'If an account exists for that login, a password reset email has been sent.',
		]
	);
}
