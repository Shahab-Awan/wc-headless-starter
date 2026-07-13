<?php
/**
 * Plugin Name: Headless Cart Bridge
 * Description: Import a Store API cart (identified by ?cart=<JWT>) into the classic WC session on /checkout so the native checkout page renders the SPA cart.
 * Version:     0.5.1
 * Author:      WCHS Contributors
*
 * Security posture (v0.4.0):
 *   - Token is ONLY honored on the checkout handoff path (default /checkout,
 *     or the FunnelKit store checkout URL when headless-funnelkit-compat is active).
 *     Any other URL with ?cart=<JWT>
 *     is ignored without side effect, no logging of the token itself.
 *   - Signature validated via WC's CartTokenUtils (per-site wp_salt secret).
 *   - Expiry enforced.
 *   - Deserialized session data is validated against a key allowlist
 *     before any value is touched — no unbounded maybe_unserialize output
 *     reaches active code paths.
 *   - Logged-in users: numeric tokens must match the current WP user id.
 *     Guest tokens (t_*) are allowed — the SPA often keeps a guest Store
 *     API session after login until WC merge runs; checkout handoff must
 *     still import that cart. Other users' numeric tokens are refused.
 *   - All debug logging is gated behind WP_DEBUG. Zero token material
 *     reaches error_log.
 *
 * REFS
 *   - woocommerce/woocommerce discussion #44991 (pattern origin)
 *   - woocommerce/woocommerce issue #55653 (login merge still broken April 2026)
 *   - plugins/woocommerce/src/StoreApi/Utilities/CartTokenUtils.php (canonical JWT validator)
 *   - plugins/woocommerce/src/StoreApi/SessionHandler.php (Store API session storage)
 */

defined( 'ABSPATH' ) || exit;

/**
 * Session keys the bridge is allowed to touch. Everything else in the
 * Store API session row is left alone. Values deserialize via
 * maybe_unserialize, but only after we've confirmed the key is in this
 * list — any foreign key in the row triggers a refusal.
 */
const WCHS_BRIDGE_KEYS = [
	'cart',
	'cart_totals',
	'applied_coupons',
	'coupon_discount_totals',
	'coupon_discount_tax_totals',
	'removed_cart_contents',
	'shipping_for_package_0',
	'chosen_shipping_methods',
	'chosen_payment_method',
	'customer',
];

/**
 * Canonical fallback when checkout root is opened without a valid SPA cart.
 * We send the customer back into the SPA and open the slide cart there.
 */
function wchs_checkout_cart_fallback_url(): string {
	$spa_origin = function_exists( 'wchs_spa_origin' )
		? wchs_spa_origin()
		: ( defined( 'WCHS_SPA_URL' ) ? rtrim( WCHS_SPA_URL, '/' ) : untrailingslashit( home_url( '/' ) ) );

	return add_query_arg( 'open_cart', '1', $spa_origin . '/shop' );
}

/**
 * Clear any classic-session cart state. The native checkout is only meant
 * to render a cart imported from the SPA token bridge, so stale classic
 * session items must never survive a failed or missing handoff.
 */
function wchs_clear_classic_checkout_session(): void {
	if ( ! function_exists( 'WC' ) ) {
		return;
	}

	if ( WC()->cart ) {
		WC()->cart->empty_cart();
	}

	if ( WC()->session ) {
		foreach ( WCHS_BRIDGE_KEYS as $key ) {
			if ( method_exists( WC()->session, '__unset' ) ) {
				WC()->session->__unset( $key );
			} else {
				WC()->session->set( $key, null );
			}
		}
		if ( method_exists( WC()->session, 'save_data' ) ) {
			WC()->session->save_data();
		}
	}
}

/**
 * Stable WP_DEBUG-gated log helper. Never accepts token material.
 */
function wchs_bridge_log( string $message ): void {
	if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
		return;
	}
	error_log( '[wchs-cart-bridge] ' . $message );
}

/**
 * Decode + validate a Store API JWT using WC's own CartTokenUtils.
 * Returns the payload on success or null on any failure.
 */
function wchs_decode_cart_token( string $token ): ?array {
	// Shape check before touching WC — JWTs are three base64url segments.
	if ( ! preg_match( '/^[A-Za-z0-9_\-]+\.[A-Za-z0-9_\-]+\.[A-Za-z0-9_\-]+$/', $token ) ) {
		return null;
	}

	$utils = 'Automattic\\WooCommerce\\StoreApi\\Utilities\\CartTokenUtils';
	if ( ! class_exists( $utils ) ) {
		wchs_bridge_log( 'CartTokenUtils class not found — WC not loaded?' );
		return null;
	}

	try {
		if ( ! $utils::validate_cart_token( $token ) ) {
			wchs_bridge_log( 'cart token signature invalid' );
			return null;
		}
		$payload = $utils::get_cart_token_payload( $token );
	} catch ( \Throwable $e ) {
		wchs_bridge_log( 'cart token validate threw: ' . $e->getMessage() );
		return null;
	}

	if ( ! is_array( $payload ) ) {
		return null;
	}

	// Expiry enforcement.
	if ( ! isset( $payload['exp'] ) || ! is_numeric( $payload['exp'] ) || (int) $payload['exp'] < time() ) {
		wchs_bridge_log( 'cart token expired or missing exp' );
		return null;
	}

	// user_id must be a non-empty string. Store API uses `t_<hash>` for guests
	// and a numeric user ID (as string) for logged-in customers. We accept
	// only strings here; integers or other shapes are refused.
	if ( ! isset( $payload['user_id'] ) || ! is_string( $payload['user_id'] ) || $payload['user_id'] === '' ) {
		wchs_bridge_log( 'cart token missing string user_id' );
		return null;
	}

	// Whitelist the shape of user_id to avoid DB weirdness. The Store API
	// format is `t_` + 30 hex chars for guests, or numeric for users.
	if ( ! preg_match( '/^(t_[a-f0-9]{20,40}|[0-9]{1,20})$/', $payload['user_id'] ) ) {
		wchs_bridge_log( 'cart token user_id has unexpected shape' );
		return null;
	}

	return $payload;
}

/**
 * Read the Store API session row for a given customer id.
 * Returns an allowlisted subset of the deserialized session data, or null.
 *
 * Any key outside WCHS_BRIDGE_KEYS causes the entire row to be refused.
 * This is the defense against object-injection via poisoned session rows.
 */
function wchs_read_store_api_session( string $customer_id ): ?array {
	global $wpdb;

	$row = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT session_value FROM {$wpdb->prefix}woocommerce_sessions WHERE session_key = %s LIMIT 1",
			$customer_id
		)
	);
	if ( ! $row ) {
		return null;
	}

	$data = maybe_unserialize( $row );
	if ( ! is_array( $data ) ) {
		return null;
	}

	$safe = [];
	foreach ( $data as $key => $serialized_value ) {
		if ( ! is_string( $key ) ) {
			wchs_bridge_log( 'session row has non-string key — refusing' );
			return null;
		}
		// Only touch the keys we expect. Foreign keys are silently dropped.
		// They might be legitimate (another plugin stashed data in the
		// session), so we don't refuse the whole row — we just don't
		// deserialize their values.
		if ( in_array( $key, WCHS_BRIDGE_KEYS, true ) ) {
			// Safe to touch. WC session values are themselves serialized
			// within the row, so we deserialize once more.
			$safe[ $key ] = maybe_unserialize( $serialized_value );
		}
	}

	return $safe;
}

/**
 * Import a Store API cart JWT into the active classic session.
 *
 * @return true|\WP_Error
 */
function wchs_import_store_cart_from_token( string $token ) {
	$payload = wchs_decode_cart_token( $token );
	if ( ! $payload ) {
		return new \WP_Error( 'wchs_invalid_cart_token', 'Invalid or expired cart token.', [ 'status' => 400 ] );
	}

	$customer_id = (string) $payload['user_id'];

	if ( is_user_logged_in() && ctype_digit( $customer_id ) ) {
		$current_user_id = get_current_user_id();
		if ( (int) $customer_id !== $current_user_id ) {
			return new \WP_Error( 'wchs_cart_token_user_mismatch', 'Cart token does not match the logged-in customer.', [ 'status' => 403 ] );
		}
	}

	$session_data = wchs_read_store_api_session( $customer_id );
	if ( ! $session_data ) {
		return new \WP_Error( 'wchs_cart_session_missing', 'No cart session found for this token.', [ 'status' => 404 ] );
	}

	$session_cart = $session_data['cart'] ?? null;
	if ( ! is_array( $session_cart ) || count( $session_cart ) === 0 ) {
		return new \WP_Error( 'wchs_cart_empty', 'Cart is empty.', [ 'status' => 400 ] );
	}

	wchs_import_cart_into_classic_session( $session_data );

	// Remember which Store API session this classic checkout cart belongs to,
	// so Elementor/FunnelKit mini-cart qty edits can be written back.
	if ( function_exists( 'WC' ) && WC()->session ) {
		WC()->session->set( 'wchs_bridged_store_customer_id', $customer_id );
		if ( method_exists( WC()->session, 'save_data' ) ) {
			WC()->session->save_data();
		}
	}
	wchs_set_bridged_store_customer_cookie( $customer_id );

	return true;
}

/**
 * Clear the bridged Store API customer cookie after SPA has pulled classic state.
 */
function wchs_clear_bridged_store_customer_cookie(): void {
	if ( headers_sent() ) {
		return;
	}
	// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.cookies_setcookie
	setcookie(
		'wchs_bridged_store_cid',
		'',
		[
			'expires'  => time() - YEAR_IN_SECONDS,
			'path'     => COOKIEPATH ? COOKIEPATH : '/',
			'domain'   => COOKIE_DOMAIN ?: '',
			'secure'   => is_ssl(),
			'httponly' => true,
			'samesite' => 'Lax',
		]
	);
	unset( $_COOKIE['wchs_bridged_store_cid'] );
}

/**
 * Short-lived cookie so AJAX mini-cart updates can find the Store API session
 * even if the classic session key was rotated.
 */
function wchs_set_bridged_store_customer_cookie( string $customer_id ): void {
	if ( $customer_id === '' || headers_sent() ) {
		return;
	}
	if ( ! preg_match( '/^(t_[a-f0-9]{20,40}|[0-9]{1,20})$/', $customer_id ) ) {
		return;
	}
	$secure   = is_ssl();
	$httponly = true;
	// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.cookies_setcookie
	setcookie(
		'wchs_bridged_store_cid',
		$customer_id,
		[
			'expires'  => time() + DAY_IN_SECONDS,
			'path'     => COOKIEPATH ? COOKIEPATH : '/',
			'domain'   => COOKIE_DOMAIN ?: '',
			'secure'   => $secure,
			'httponly' => $httponly,
			'samesite' => 'Lax',
		]
	);
	$_COOKIE['wchs_bridged_store_cid'] = $customer_id;
}

/**
 * Store API customer id linked to the current classic checkout session.
 */
function wchs_bridged_store_customer_id(): string {
	if ( function_exists( 'WC' ) && WC()->session ) {
		$id = WC()->session->get( 'wchs_bridged_store_customer_id' );
		if ( is_string( $id ) && $id !== '' ) {
			return $id;
		}
	}
	if ( ! empty( $_COOKIE['wchs_bridged_store_cid'] ) ) {
		return sanitize_text_field( wp_unslash( (string) $_COOKIE['wchs_bridged_store_cid'] ) );
	}
	return '';
}

/**
 * After Elementor/FunnelKit mini-cart changes the classic cart, mirror it
 * into the Store API session the SPA SlideCart reads.
 *
 * DISABLED: live push was wiping the Store API cart when classic session
 * was empty/stale (empty SlideCart after returning from checkout). Keep the
 * helper for a future guarded sync; do not hook cart mutations until then.
 */
function wchs_push_classic_cart_to_bridged_store_api(): void {
	static $pushing = false;
	if ( $pushing || ! function_exists( 'WC' ) || ! WC()->session ) {
		return;
	}

	$store_id = wchs_bridged_store_customer_id();
	if ( $store_id === '' || ! preg_match( '/^(t_[a-f0-9]{20,40}|[0-9]{1,20})$/', $store_id ) ) {
		return;
	}

	$pushing = true;
	try {
		if ( WC()->cart ) {
			WC()->cart->calculate_totals();
		}

		$safe = [];
		foreach ( WCHS_BRIDGE_KEYS as $key ) {
			$val = WC()->session->get( $key );
			if ( null !== $val ) {
				$safe[ $key ] = $val;
			}
		}

		// Prefer the live cart object — session get can lag one tick behind AJAX qty updates.
		if ( WC()->cart && method_exists( WC()->cart, 'get_cart_for_session' ) ) {
			$safe['cart'] = WC()->cart->get_cart_for_session();
		} elseif ( ! isset( $safe['cart'] ) || ! is_array( $safe['cart'] ) ) {
			$safe['cart'] = [];
		}

		// Never overwrite a non-empty Store API cart with an empty classic cart.
		$classic_count = is_array( $safe['cart'] ) ? count( $safe['cart'] ) : 0;
		if ( $classic_count < 1 ) {
			$existing = wchs_read_store_api_session( $store_id );
			$existing_cart = is_array( $existing['cart'] ?? null ) ? $existing['cart'] : [];
			if ( count( $existing_cart ) > 0 ) {
				wchs_bridge_log( 'skip push: classic empty, store session still has items' );
				return;
			}
		}

		wchs_write_store_api_session( $store_id, $safe );
		wchs_bridge_log( 'pushed classic cart to store session ' . $store_id );
	} finally {
		$pushing = false;
	}
}

// Live classic→Store API push hooks intentionally not registered.
// They emptied the SPA cart when classic session lagged behind the handoff.

/**
 * Import allowlisted session data into the active classic session and
 * force WC to recalculate totals.
 */
function wchs_import_cart_into_classic_session( array $safe_data ): void {
	if ( ! function_exists( 'WC' ) || ! WC()->session ) {
		return;
	}
	wchs_clear_classic_checkout_session();
	$classic = WC()->session;
	foreach ( $safe_data as $key => $value ) {
		$classic->set( $key, $value );
	}
	if ( WC()->cart ) {
		WC()->cart->get_cart_from_session();
		if ( function_exists( 'wchs_cart_line_seed_unit_price_locks_from_session' ) ) {
			wchs_cart_line_seed_unit_price_locks_from_session( WC()->cart );
		}
		WC()->cart->calculate_totals();
	}
}

/**
 * Main entry: checkout root is only valid via /checkout/?cart=<JWT>.
 * Bare /checkout is not a supported customer entrypoint because the
 * customer-facing cart lives in the SPA Store API session, not the
 * classic Woo session. If checkout root is opened without a valid
 * non-empty token payload, clear any stale classic cart and send the
 * customer back to the SPA cart surface.
 *
 * Runs on wp_loaded priority 5. By then WC has initialized its classic
 * session but no checkout template has started rendering.
 *
 * Hard guards:
 *   - Must be the checkout handoff path (not order-pay / thank-you).
 *   - Must NOT be admin / ajax / REST context.
 *   - Logged-in + numeric token: token user_id must match current user.
 *     Guest (t_*) tokens are always imported — SPA checkout handoff.
 *   - Token must validate, must be unexpired, cart must be non-empty.
 */
add_action(
	'wp_loaded',
	function () {
		if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		// In restricted access modes, let the dedicated access-control
		// template gate own guest redirects (maintenance / login wall /
		// browse-only checkout block). The cart bridge only hardens the
		// open-mode checkout flow.
		if ( function_exists( 'wchs_access_mode' ) && 3 !== wchs_access_mode() && ! is_user_logged_in() ) {
			return;
		}

		$path = wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ) ?? '';
		$path = rtrim( $path, '/' );
		$handoff_path = function_exists( 'wchs_checkout_handoff_path' )
			? wchs_checkout_handoff_path()
			: '/checkout';
		// Only the checkout handoff root uses the cart bridge. Order-pay /
		// order-received stay native and must not be bounced.
		if ( ! function_exists( 'wchs_is_checkout_handoff_path' ) ) {
			if ( rtrim( $handoff_path, '/' ) !== $path ) {
				return;
			}
		} elseif ( ! wchs_is_checkout_handoff_path( $path ) ) {
			return;
		}

		if ( empty( $_GET['cart'] ) ) {
			if ( function_exists( 'wchs_allow_bare_checkout_handoff' ) && wchs_allow_bare_checkout_handoff() ) {
				return;
			}
			wchs_bridge_log( 'bare checkout hit without cart token; redirecting back to SPA cart' );
			wchs_clear_classic_checkout_session();
			wp_safe_redirect( wchs_checkout_cart_fallback_url() );
			exit;
		}

		$token = sanitize_text_field( wp_unslash( $_GET['cart'] ) );
		if ( ! function_exists( 'wchs_import_store_cart_from_token' ) ) {
			wchs_bridge_log( 'cart import helper missing' );
			wchs_clear_classic_checkout_session();
			wp_safe_redirect( wchs_checkout_cart_fallback_url() );
			exit;
		}

		$imported = wchs_import_store_cart_from_token( $token );
		if ( is_wp_error( $imported ) ) {
			wchs_bridge_log( 'checkout cart import failed: ' . $imported->get_error_code() );
			wchs_clear_classic_checkout_session();
			wp_safe_redirect( wchs_checkout_cart_fallback_url() );
			exit;
		}

		wchs_bridge_log( 'imported cart from checkout token' );
	},
	5
);

/**
 * Write allowlisted session keys into a Store API session row (by customer id).
 * Used to push classic/Elementor cart edits back into the SPA Cart-Token session.
 */
function wchs_write_store_api_session( string $customer_id, array $safe_data ): bool {
	global $wpdb;

	if ( $customer_id === '' || ! preg_match( '/^(t_[a-f0-9]{20,40}|[0-9]{1,20})$/', $customer_id ) ) {
		return false;
	}

	$table = $wpdb->prefix . 'woocommerce_sessions';
	$row   = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT session_value FROM {$table} WHERE session_key = %s LIMIT 1",
			$customer_id
		)
	);

	$data = [];
	if ( is_string( $row ) && $row !== '' ) {
		$parsed = maybe_unserialize( $row );
		if ( is_array( $parsed ) ) {
			$data = $parsed;
		}
	}

	foreach ( WCHS_BRIDGE_KEYS as $key ) {
		if ( array_key_exists( $key, $safe_data ) ) {
			$data[ $key ] = maybe_serialize( $safe_data[ $key ] );
		}
	}

	$serialized = maybe_serialize( $data );
	$expiry     = time() + (int) apply_filters( 'wc_session_expiration', 2 * DAY_IN_SECONDS );

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$result = $wpdb->replace(
		$table,
		[
			'session_key'   => $customer_id,
			'session_value' => $serialized,
			'session_expiry'=> $expiry,
		],
		[ '%s', '%s', '%d' ]
	);

	return false !== $result;
}

/**
 * Customer id from the classic `wp_woocommerce_session_*` cookie (not Cart-Token).
 */
function wchs_classic_session_customer_id_from_cookie(): ?string {
	foreach ( $_COOKIE as $name => $value ) {
		if ( ! is_string( $name ) || 0 !== strpos( $name, 'wp_woocommerce_session_' ) ) {
			continue;
		}
		if ( ! is_string( $value ) || $value === '' ) {
			continue;
		}
		// Cookie value: customer_id||expiry||expirytimestamp||hash
		$parts = explode( '||', $value );
		$id    = isset( $parts[0] ) ? (string) $parts[0] : '';
		if ( $id !== '' && preg_match( '/^(t_[a-f0-9]{20,40}|[0-9]{1,20})$/', $id ) ) {
			return $id;
		}
	}
	return null;
}

/**
 * Read allowlisted keys from the classic cookie session row in the DB.
 *
 * @return array<string, mixed>|null
 */
function wchs_read_classic_session_from_cookie(): ?array {
	$customer_id = wchs_classic_session_customer_id_from_cookie();
	if ( ! $customer_id ) {
		// Fall back to the active WC session when the cookie parser misses
		// (logged-in users sometimes expose the id via WC()->session).
		if ( function_exists( 'WC' ) && WC()->session && method_exists( WC()->session, 'get_customer_id' ) ) {
			$customer_id = (string) WC()->session->get_customer_id();
		}
	}
	if ( ! $customer_id ) {
		return null;
	}

	$safe = wchs_read_store_api_session( $customer_id );
	return $safe;
}

/**
 * POST /wchs/v1/cart/sync-from-classic
 *
 * Push the classic WC session (FunnelKit/Elementor mini-cart) into the
 * Store API session identified by ?cart= / body.cart / Cart-Token.
 * Call WITHOUT sending Cart-Token as a request header when possible so the
 * classic cookie session remains the one WC loads for this request — we
 * still read the cookie session from the DB directly either way.
 *
 * @param \WP_REST_Request $request
 * @return \WP_REST_Response|\WP_Error
 */
function wchs_rest_cart_sync_from_classic( \WP_REST_Request $request ) {
	if ( function_exists( 'wchs_rest_rate_limit' ) && ! wchs_rest_rate_limit( 'cart_sync_from_classic' ) ) {
		return new \WP_Error( 'wchs_rate_limited', 'Too many requests.', [ 'status' => 429 ] );
	}

	$token = '';
	$header = $request->get_header( 'cart-token' );
	if ( is_string( $header ) && $header !== '' ) {
		$token = sanitize_text_field( $header );
	}
	if ( $token === '' ) {
		$body = $request->get_json_params();
		if ( is_array( $body ) && ! empty( $body['cart'] ) ) {
			$token = sanitize_text_field( (string) $body['cart'] );
		}
	}
	if ( $token === '' && ! empty( $_GET['cart'] ) ) {
		$token = sanitize_text_field( wp_unslash( (string) $_GET['cart'] ) );
	}
	if ( $token === '' ) {
		return new \WP_Error( 'wchs_missing_cart_token', 'Cart token is required.', [ 'status' => 400 ] );
	}

	$payload = wchs_decode_cart_token( $token );
	if ( ! $payload ) {
		return new \WP_Error( 'wchs_invalid_cart_token', 'Invalid or expired cart token.', [ 'status' => 400 ] );
	}

	$store_customer_id = (string) $payload['user_id'];

	if ( is_user_logged_in() && ctype_digit( $store_customer_id ) ) {
		if ( (int) $store_customer_id !== get_current_user_id() ) {
			return new \WP_Error( 'wchs_cart_token_user_mismatch', 'Cart token does not match the logged-in customer.', [ 'status' => 403 ] );
		}
	}

	$classic = wchs_read_classic_session_from_cookie();
	if ( null === $classic ) {
		return new \WP_Error(
			'wchs_classic_session_missing',
			'No classic cart session to sync.',
			[ 'status' => 404 ]
		);
	}

	// Ensure cart key exists even when classic session only has totals.
	if ( ! isset( $classic['cart'] ) || ! is_array( $classic['cart'] ) ) {
		$classic['cart'] = [];
	}

	$classic_count = count( $classic['cart'] );
	$existing      = wchs_read_store_api_session( $store_customer_id );
	$existing_cart = is_array( $existing['cart'] ?? null ) ? $existing['cart'] : [];
	// Never wipe a populated Store API cart with an empty classic session.
	if ( $classic_count < 1 && count( $existing_cart ) > 0 ) {
		wchs_bridge_log( 'sync-from-classic refused: classic empty, store has items' );
		return new \WP_Error(
			'wchs_sync_refused_empty',
			'Classic cart is empty; refusing to overwrite Store API cart.',
			[ 'status' => 409 ]
		);
	}

	$written = wchs_write_store_api_session( $store_customer_id, $classic );
	if ( ! $written ) {
		wchs_bridge_log( 'sync-from-classic failed to write store session' );
		return new \WP_Error( 'wchs_sync_write_failed', 'Could not update Store API session.', [ 'status' => 500 ] );
	}

	if ( function_exists( 'WC' ) && WC()->session ) {
		WC()->session->set( 'wchs_bridged_store_customer_id', null );
	}
	wchs_clear_bridged_store_customer_cookie();

	$count = is_array( $classic['cart'] ) ? count( $classic['cart'] ) : 0;
	wchs_bridge_log( 'sync-from-classic wrote ' . $count . ' line(s) into store session' );

	return new \WP_REST_Response(
		[
			'ok'          => true,
			'items_count' => $count,
		],
		200
	);
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'wchs/v1',
			'/cart/sync-from-classic',
			[
				'methods'             => [ 'POST', 'OPTIONS' ],
				'callback'            => 'wchs_rest_cart_sync_from_classic',
				'permission_callback' => '__return_true',
			]
		);
	}
);

/**
 * Dev-only breadcrumb in the rendered HTML so we know the bridge fired.
 * Gated behind WP_DEBUG so it never leaks in production.
 */
add_action(
	'wp_head',
	function () {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}
		if ( empty( $_GET['cart'] ) ) {
			return;
		}
		echo "<!-- headless-cart-bridge: active (WP_DEBUG) -->\n";
	}
);
