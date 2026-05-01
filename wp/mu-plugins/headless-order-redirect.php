<?php
/**
 * Plugin Name: Headless Order Redirect
 * Description: After payment, redirect from the native WC /checkout/order-received/ page to the SPA /order-received route, passing order id + key (+ billing email for guests) as query params.
 * Version:     0.1.0
 * Author:      WCHS Contributors
*
 * THE PROBLEM
 *   WC hands off to gateways, gateways redirect back to
 *   /checkout/order-received/{id}/?key=wc_order_<hash>. That page is
 *   rendered by the native theme. For a headless flow we want users
 *   to finish their journey on the SPA, not on the native thank-you
 *   page.
 *
 * THE FIX
 *   Filter `woocommerce_get_return_url` — called by WC to decide where
 *   to send the user after order creation — to return a SPA URL
 *   carrying {id, key, email}. The SPA's /order-received route then
 *   calls the Store API `/order/{id}?key=&billing_email=` endpoint to
 *   render a native confirmation page.
 *
 * SECURITY POSTURE
 *   - Origin is not attacker-controlled; it comes from WCHS's resolved
 *     public/custom SPA origin helpers.
 *   - The `key` param IS sensitive (grants access to order details via
 *     Store API). Risk vectors:
 *       * Referrer leakage: the SPA route sets Referrer-Policy: no-referrer.
 *       * Browser history: the SPA stashes params in sessionStorage and
 *         history.replaceState-strips them before first render.
 *   - Guest orders require billing_email alongside key for Store API
 *     access. We pass it in the URL only when the order is a guest
 *     order — for logged-in users the cookie session is sufficient.
 */

defined( 'ABSPATH' ) || exit;

/**
 * SPA origin used for return URLs. Same-origin is the default path; custom
 * split-origin deployments can still override via the WCHS origin helpers.
 */
function wchs_order_redirect_origin(): string {
	if ( function_exists( 'wchs_spa_origin' ) ) {
		return wchs_spa_origin();
	}
	if ( defined( 'WCHS_SPA_URL' ) && is_string( WCHS_SPA_URL ) ) {
		return rtrim( WCHS_SPA_URL, '/' );
	}
	return untrailingslashit( home_url( '/' ) );
}

/**
 * Check-and-set: returns false the first time it's called for an
 * order id (caller should then fire actions + mark done via the
 * next call), true on subsequent calls. Prevents double-firing the
 * thank-you actions when the template ever runs (edge cases like
 * ?wchs_bridge=stay during testing) after we already dispatched
 * them from the redirect.
 *
 * Static map — scoped to this request. Order IDs can't repeat
 * within one request anyway; the map is defensive.
 */
function wchs_thankyou_fired( int $order_id ): bool {
	static $fired = [];
	if ( isset( $fired[ $order_id ] ) ) {
		return true;
	}
	$fired[ $order_id ] = true;
	return false;
}

/**
 * Replace the default WC return URL with a SPA URL carrying order params.
 */
add_filter(
	'woocommerce_get_return_url',
	function ( $url, $order ) {
		if ( ! ( $order instanceof \WC_Order ) ) {
			return $url;
		}

		// If the URL points to an upsell offer page, pass through
		// unchanged so the offer page can render on WP.
		$parsed_query = parse_url( $url, PHP_URL_QUERY ) ?: '';
		if ( strpos( $parsed_query, 'wchs_upsell=' ) !== false ) {
			return $url;
		}

		$origin = wchs_order_redirect_origin();
		$params = [
			'id'  => (int) $order->get_id(),
			'key' => (string) $order->get_order_key(),
		];
		if ( ! $order->get_user_id() ) {
			$email = (string) $order->get_billing_email();
			if ( $email !== '' ) {
				$params['email'] = $email;
			}
		}
		return $origin . '/order-received?' . http_build_query( $params );
	},
	10,
	2
);

/**
 * Also intercept the `woocommerce_thankyou` action — for gateways that
 * redirect on their own but let WC render the thank-you page, we still
 * want the user bounced to the SPA if they land on the native page.
 *
 * We do NOT redirect if ?wchs_bridge=stay is present, so we can manually
 * view the native page during testing.
 */
add_action(
	'template_redirect',
	function () {
		if ( ! function_exists( 'is_wc_endpoint_url' ) ) {
			return;
		}
		if ( ! is_wc_endpoint_url( 'order-received' ) ) {
			return;
		}
		if ( isset( $_GET['wchs_bridge'] ) && $_GET['wchs_bridge'] === 'stay' ) {
			return;
		}
		// Don't redirect if we're on the upsell offer page
		if ( isset( $_GET['wchs_upsell'] ) || isset( $_GET['wchs_upsell_accept'] ) || isset( $_GET['wchs_upsell_decline'] ) ) {
			return;
		}
		// All orders redirect to the SPA order-received page.

		global $wp;
		$order_id = absint( $wp->query_vars['order-received'] ?? 0 );
		if ( ! $order_id ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! ( $order instanceof \WC_Order ) ) {
			return;
		}

		// All orders redirect to the SPA — including offline gateways.
		// The SPA order-received page handles cleanup (shadow cart,
		// cart token) and renders order details for all payment methods.

		$key = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';
		if ( $key === '' || ! hash_equals( (string) $order->get_order_key(), $key ) ) {
			return;
		}

		// Fire the standard WC thank-you server-side hooks so plugins
		// that depend on them (email marketing, loyalty, referrals,
		// ERP sync, review requests, anything server-side) still get
		// their callbacks. Client-side pixels still need to fire on
		// the SPA /order-received route — those can't run here.
		//
		// We guard against double-firing via a static flag and wrap
		// the action dispatch in an output buffer so any stray HTML
		// from plugins doesn't break the redirect with "headers
		// already sent."
		if ( ! wchs_thankyou_fired( $order_id ) ) {
			ob_start();
			do_action( 'woocommerce_before_thankyou', $order_id );
			do_action( 'woocommerce_thankyou_' . $order->get_payment_method(), $order_id );
			do_action( 'woocommerce_thankyou', $order_id );
			ob_end_clean();
		}

		$origin = wchs_order_redirect_origin();
		$params = [
			'id'  => $order_id,
			'key' => $key,
		];
		if ( ! $order->get_user_id() ) {
			$email = (string) $order->get_billing_email();
			if ( $email !== '' ) {
				$params['email'] = $email;
			}
		}

		wp_redirect( $origin . '/order-received?' . http_build_query( $params ), 302 );
		exit;
	},
	20
);

// ─── Clear SPA cart state on any native thank-you render ───────
// Most orders are redirected to the SPA immediately, but if a native
// thank-you page ever renders (gateway edge case, manual testing, or a
// temporary bypass), clear the shadow cart/session tokens so the SPA
// does not replay purchased items on the next visit.

add_action( 'woocommerce_thankyou', function () {
	?>
	<script>
	try {
		sessionStorage.removeItem('wchs_cart_token');
		sessionStorage.removeItem('wchs_store_nonce');
		localStorage.removeItem('wchs_shadow_cart_v1');
	} catch(e) {}
	</script>
	<?php
}, 50 );
