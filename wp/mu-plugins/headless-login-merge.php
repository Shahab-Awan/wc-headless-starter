<?php
/**
 * Plugin Name: Headless Login Merge
 * Description: Workaround for WC#55653 — set the user meta flag that WooCommerce checks after login to merge a guest cart into the newly logged-in customer cart.
 * Version:     0.1.0
 * Author:      WCHS Contributors
*
 * @see https://github.com/woocommerce/woocommerce/issues/55653
 * TODO: Remove this plugin when WC#55653 is resolved upstream.
 *       Check status periodically — last checked April 2026, still open.
 *
 * THE PROBLEM
 *   WooCommerce issue #55653 (open as of April 2026): when a guest with
 *   a Store API cart logs in, WC's cart-merge logic hinges on a user
 *   meta key `_woocommerce_load_saved_cart_after_login` that only gets
 *   set by the classic `wp_login` action path — not the headless JWT
 *   flow. Without the flag, the guest cart *replaces* the logged-in
 *   cart instead of merging.
 *
 * THE FIX
 *   On every `wp_login` action (and the WC customer authenticated
 *   action), set the flag. The next Store API call from the SPA — with
 *   Cart-Token header — then triggers WC's merge logic correctly.
 *
 *   This is a nudge, not a replacement for a proper fix upstream.
 *   Remove when #55653 lands.
 *
 * REFS
 *   - https://github.com/woocommerce/woocommerce/issues/55653
 *   - https://www.businessbloomer.com/woocommerce-cart-merge-sessions-changes/
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'wp_login',
	function ( $user_login, $user ) {
		if ( ! ( $user instanceof \WP_User ) || ! $user->ID ) {
			return;
		}
		update_user_meta( $user->ID, '_woocommerce_load_saved_cart_after_login', 1 );
	},
	10,
	2
);

// WC-specific hook — fires when the classic login form authenticates a customer.
add_action(
	'woocommerce_customer_authenticated',
	function ( $user_id ) {
		if ( ! $user_id ) {
			return;
		}
		update_user_meta( $user_id, '_woocommerce_load_saved_cart_after_login', 1 );
	}
);

// Also fire on programmatic auth set (wp_set_current_user + wp_set_auth_cookie
// can happen in custom flows that don't trigger wp_login).
add_action(
	'set_current_user',
	function () {
		if ( ! is_user_logged_in() ) {
			return;
		}
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return;
		}
		// Only set if not already set, so we don't thrash the meta on every request.
		if ( ! get_user_meta( $user_id, '_woocommerce_load_saved_cart_after_login', true ) ) {
			// Don't set on every page load — only on the first one after auth.
			// Use a transient to track first-request-after-login.
			$transient_key = 'wchs_first_post_login_' . $user_id;
			if ( ! get_transient( $transient_key ) ) {
				update_user_meta( $user_id, '_woocommerce_load_saved_cart_after_login', 1 );
				set_transient( $transient_key, 1, 5 * MINUTE_IN_SECONDS );
			}
		}
	}
);
