<?php
/**
 * Plugin Name: Headless Cart Lock
 * Description: Server-side serialization of WooCommerce Store API cart write operations using MySQL application locks. Prevents concurrent read-modify-write races on the same session's cart row.
 * Version:     0.1.0
 * Author:      WCHS Contributors
*
 * THE PROBLEM
 *   WC's Store API cart session handler does read-modify-write without
 *   row-level locking. Two simultaneous POSTs to /cart/add-item from the
 *   same session can race:
 *     - Request A reads cart=[], adds A, writes cart=[A]
 *     - Request B reads cart=[] (concurrently), adds B, writes cart=[B]
 *     - Final state: cart=[B]. Item A is lost.
 *
 *   The SPA cart store has a client-side mutex that serializes UI-driven
 *   mutations. But a misbehaving client, a second browser tab, or a
 *   direct API call still bypasses it. This plugin is the server-side
 *   correctness boundary.
 *
 * THE FIX
 *   Hook `rest_pre_dispatch` for Store API cart mutation routes. Before
 *   WC processes the request, acquire a per-session MySQL application
 *   lock (`GET_LOCK`). The next mutation for the same session waits
 *   until the first releases or times out.
 *
 *   Lock key = hash of the Cart-Token (or a session fallback for classic
 *   requests). Hashed to fit inside MySQL's 64-char name limit.
 *
 *   Released via register_shutdown_function — runs after the response
 *   is sent, so subsequent requests are unblocked as soon as possible.
 *   MySQL also auto-releases on connection close, so a PHP crash mid-
 *   request can't orphan a lock.
 *
 * MULTISITE NOTES
 *   In this project each site has its own MySQL DB (not WP Multisite
 *   in the sense of a network-of-blogs). Application locks via GET_LOCK
 *   are scoped per MySQL server connection, but names are global across
 *   the server. We include `DB_NAME` in the lock key as a belt-and-
 *   suspenders prefix so if you ever move to a shared MySQL instance,
 *   cross-DB collisions don't happen.
 *
 * CONFIGURATION
 *   WCHS_CART_LOCK_TIMEOUT  — max seconds to wait to acquire (default 5)
 *   WCHS_CART_LOCK_ENABLED  — set to false to disable (default true)
 */

defined( 'ABSPATH' ) || exit;

const WCHS_CART_LOCK_DEFAULT_TIMEOUT = 5;

/**
 * Store API cart mutation routes we lock. GET is never locked.
 */
const WCHS_CART_LOCK_ROUTES_RE = '#^/wc/store/v1/cart/(add-item|update-item|remove-item|apply-coupon|remove-coupon|update-customer|select-shipping-rate|extensions)#';

/**
 * Track the current request's lock name so shutdown can release it.
 */
$GLOBALS['wchs_cart_lock_held'] = null;

/**
 * Derive a stable lock name for the current request. Uses the Cart-Token
 * header if present (Store API), or the classic WC session cookie as
 * fallback.
 */
function wchs_cart_lock_name_for_request( \WP_REST_Request $request ): string {
	$identifier = '';

	// Primary: Store API cart token from the header. Store-api-session.
	$token = $request->get_header( 'Cart-Token' );
	if ( $token ) {
		$identifier = $token;
	}

	// Secondary: classic WC session cookie (rare for Store API, but handle)
	if ( $identifier === '' ) {
		foreach ( $_COOKIE as $name => $value ) {
			if ( strpos( $name, 'wp_woocommerce_session_' ) === 0 ) {
				$identifier = $value;
				break;
			}
		}
	}

	// Tertiary: logged-in user id
	if ( $identifier === '' && is_user_logged_in() ) {
		$identifier = 'user_' . get_current_user_id();
	}

	// Fallback: IP + UA (treats anonymous non-session clients as a single bucket per IP)
	if ( $identifier === '' ) {
		$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
		$ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
		$identifier = 'anon_' . $ip . '|' . $ua;
	}

	// Prefix with DB_NAME so lock names don't collide across sites on a
	// shared MySQL server.
	$db    = defined( 'DB_NAME' ) ? DB_NAME : 'wp';
	$hash  = hash( 'sha256', $db . '|' . $identifier );
	// MySQL lock names are limited to 64 chars. `wchs_cart_` (10) + 50 hex = 60.
	return 'wchs_cart_' . substr( $hash, 0, 50 );
}

/**
 * Hook Store API cart write routes and serialize them.
 */
add_filter(
	'rest_pre_dispatch',
	function ( $result, $server, $request ) {
		if ( defined( 'WCHS_CART_LOCK_ENABLED' ) && ! WCHS_CART_LOCK_ENABLED ) {
			return $result;
		}

		$route = $request->get_route();
		if ( ! preg_match( WCHS_CART_LOCK_ROUTES_RE, $route ) ) {
			return $result;
		}
		// GET is read-only; don't lock.
		if ( $request->get_method() === 'GET' ) {
			return $result;
		}

		global $wpdb;
		$lock_name = wchs_cart_lock_name_for_request( $request );
		$timeout   = defined( 'WCHS_CART_LOCK_TIMEOUT' ) ? (int) WCHS_CART_LOCK_TIMEOUT : WCHS_CART_LOCK_DEFAULT_TIMEOUT;

		$t0 = microtime( true );
		$got = (int) $wpdb->get_var(
			$wpdb->prepare( 'SELECT GET_LOCK(%s, %d)', $lock_name, $timeout )
		);
		$wait_ms = (int) ( ( microtime( true ) - $t0 ) * 1000 );
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( '[wchs-cart-lock] %s %s lock=%s got=%d waited=%dms pid=%d', $request->get_method(), $route, substr( $lock_name, 0, 20 ) . '…', $got, $wait_ms, getmypid() ) );
		}

		if ( $got !== 1 ) {
			// Either timeout (0) or error (null → cast to 0). Return 409
			// so the client can retry.
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[wchs-cart-lock] failed to acquire lock ' . substr( $lock_name, 0, 20 ) . '…' );
			}
			return new \WP_Error(
				'wchs_cart_locked',
				__( 'Your cart is being updated. Please try again in a moment.', 'wchs' ),
				[ 'status' => 409 ]
			);
		}

		// Track for shutdown release. Do NOT release in rest_post_dispatch
		// — WC writes the session row in its own shutdown action at
		// priority 20. If we release before that, the next request can
		// acquire the lock, read the PRE-WRITE session, and race us.
		//
		// Release priority 100 ensures it runs after WC_Session_Handler's
		// save_data at priority 20.
		$GLOBALS['wchs_cart_lock_held'] = $lock_name;

		return $result; // null = let WC continue processing the route
	},
	5,
	3
);

/**
 * Release the cart lock AFTER WC's session save_data has written to the
 * database. WC hooks save_data at shutdown priority 20, so we release
 * at priority 100.
 *
 * MySQL also auto-releases on connection close as a fail-safe if the
 * process crashes between WC's save and our release.
 */
add_action(
	'shutdown',
	function () {
		if ( empty( $GLOBALS['wchs_cart_lock_held'] ) ) {
			return;
		}
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $GLOBALS['wchs_cart_lock_held'] ) );
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( '[wchs-cart-lock] released %s (pid=%d)', substr( $GLOBALS['wchs_cart_lock_held'], 0, 20 ) . '…', getmypid() ) );
		}
		$GLOBALS['wchs_cart_lock_held'] = null;
	},
	100
);
