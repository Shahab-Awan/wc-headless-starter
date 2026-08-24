<?php
/**
 * Plugin Name: Headless Order Redirect
 * Description: Clears SPA cart session state when thank-you / order-received pages render (native WC + FunnelKit).
 * Version:     0.3.0
 * Author:      WCHS Contributors
 *
 * Post-checkout confirmation and purchase tracking live on the native
 * /checkout/order-received/ page (see headless-thankyou-tracking.php).
 * FunnelKit store checkout often lands on a custom thank-you path instead —
 * we must clear SPA storage there too or the slide cart keeps purchased items.
 */

defined( 'ABSPATH' ) || exit;

/**
 * True on native order-received or FunnelKit thank-you surfaces.
 */
function wchs_order_redirect_is_thankyou_surface(): bool {
	if ( function_exists( 'wchs_thankyou_is_order_received_request' ) && wchs_thankyou_is_order_received_request() ) {
		return true;
	}
	if ( function_exists( 'wchs_thankyou_is_funnelkit_ty_path' ) && wchs_thankyou_is_funnelkit_ty_path() ) {
		return true;
	}
	if ( function_exists( 'wchs_is_funnelkit_thankyou_request' ) && wchs_is_funnelkit_thankyou_request() ) {
		return true;
	}
	if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'order-received' ) ) {
		return true;
	}
	return false;
}

/**
 * Browser keys the SPA cart client persists. Must stay in sync with
 * spa/src/lib/wc/store-api.ts + shadow-cart.ts.
 *
 * wchs_cart_token_ls is the localStorage backup — clearing only sessionStorage
 * left the old JWT able to resurrect the purchased cart on the next visit.
 */
function wchs_emit_spa_cart_clear_script(): void {
	static $emitted = false;
	if ( $emitted ) {
		return;
	}
	$emitted = true;
	?>
	<script data-wchs-clear-spa-cart>
	try {
		sessionStorage.removeItem('wchs_cart_token');
		sessionStorage.removeItem('wchs_store_nonce');
		localStorage.removeItem('wchs_cart_token_ls');
		localStorage.removeItem('wchs_shadow_cart_v1');
	} catch (e) {}
	</script>
	<?php
}

/**
 * Empty the Store API session that was bridged into classic checkout.
 * Browser token clear alone is not enough if the same JWT is restored.
 */
function wchs_clear_bridged_store_api_cart_after_order(): void {
	static $done = false;
	if ( $done ) {
		return;
	}
	$done = true;

	if ( ! function_exists( 'wchs_bridged_store_customer_id' ) || ! function_exists( 'wchs_write_store_api_session' ) ) {
		return;
	}

	$store_id = wchs_bridged_store_customer_id();
	if ( $store_id === '' || ! preg_match( '/^(t_[a-f0-9]{20,40}|[0-9]{1,20})$/', $store_id ) ) {
		return;
	}

	wchs_write_store_api_session(
		$store_id,
		[
			'cart'                       => [],
			'applied_coupons'            => [],
			'coupon_discount_totals'     => [],
			'coupon_discount_tax_totals' => [],
			'removed_cart_contents'      => [],
		]
	);

	if ( function_exists( 'WC' ) && WC()->session ) {
		WC()->session->set( 'wchs_bridged_store_customer_id', null );
		if ( method_exists( WC()->session, 'save_data' ) ) {
			WC()->session->save_data();
		}
	}

	if ( function_exists( 'wchs_clear_bridged_store_customer_cookie' ) ) {
		wchs_clear_bridged_store_customer_cookie();
	}
}

add_action(
	'woocommerce_thankyou',
	static function () {
		wchs_clear_bridged_store_api_cart_after_order();
		wchs_emit_spa_cart_clear_script();
	},
	50
);

// FunnelKit thank-you pages do not always fire woocommerce_thankyou in the
// page footer context customers actually see — mirror the clear there too.
add_action(
	'wp_footer',
	static function () {
		if ( ! wchs_order_redirect_is_thankyou_surface() ) {
			return;
		}
		wchs_clear_bridged_store_api_cart_after_order();
		wchs_emit_spa_cart_clear_script();
	},
	5
);
