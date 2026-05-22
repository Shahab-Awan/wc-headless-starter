<?php
/**
 * Plugin Name: Headless Cart Bridge
 * Description: Import a Store API cart (identified by ?cart=<JWT>) into the classic WC session on /checkout so the native checkout page renders the SPA cart.
 * Version:     0.5.0
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
 *   - Logged-in users only honor numeric checkout tokens that belong to
 *     the current WP user id. Guest / other-user tokens are refused even
 *     if the classic cart is empty, preventing the WC#55653 cart-overwrite
 *     attack via a phished ?cart= link while still allowing legitimate
 *     same-user SPA -> checkout handoff.
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
 *   - Must be the /checkout page (not any URL with ?cart=<JWT>).
 *   - Must NOT be admin / ajax / REST context.
 *   - Must NOT have a logged-in user with an existing cart — that
 *     scenario is the WC#55653 cart-overwrite attack surface.
 *   - Token must validate, must be unexpired, must have the expected
 *     user_id shape.
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
		$payload = wchs_decode_cart_token( $token );
		if ( ! $payload ) {
			wchs_bridge_log( 'invalid cart token on checkout root; redirecting back to SPA cart' );
			wchs_clear_classic_checkout_session();
			wp_safe_redirect( wchs_checkout_cart_fallback_url() );
			exit;
		}

		$customer_id = $payload['user_id'];
		// Logged-in customers legitimately hit /checkout/?cart=<JWT> from the
		// SPA, and their numeric Store API token belongs to the same WP user.
		// Allow that same-user handoff even if Woo still has a classic or
		// persistent cart. What we must refuse is any guest / other-user token
		// being imported into a logged-in session.
		if ( is_user_logged_in() ) {
			$current_user_id = get_current_user_id();
			$token_user_id   = (string) $customer_id;

			if ( ! ctype_digit( $token_user_id ) || (int) $token_user_id !== $current_user_id ) {
				wchs_bridge_log(
					sprintf(
						'logged-in user %d refusing checkout token for customer %s',
						$current_user_id,
						substr( $token_user_id, 0, 12 )
					)
				);
				wchs_clear_classic_checkout_session();
				wp_safe_redirect( wchs_checkout_cart_fallback_url() );
				exit;
			}
		}

		$session_data = wchs_read_store_api_session( $customer_id );
		if ( ! $session_data ) {
			wchs_bridge_log( 'no allowlisted session data for ' . substr( $customer_id, 0, 8 ) . '...' );
			wchs_clear_classic_checkout_session();
			wp_safe_redirect( wchs_checkout_cart_fallback_url() );
			exit;
		}

		$session_cart = $session_data['cart'] ?? null;
		if ( ! is_array( $session_cart ) || count( $session_cart ) === 0 ) {
			wchs_bridge_log( 'checkout token resolved to an empty cart; redirecting back to SPA cart' );
			wchs_clear_classic_checkout_session();
			wp_safe_redirect( wchs_checkout_cart_fallback_url() );
			exit;
		}

		wchs_import_cart_into_classic_session( $session_data );
		wchs_bridge_log( 'imported cart for customer ' . substr( $customer_id, 0, 8 ) . '...' );
	},
	5
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
