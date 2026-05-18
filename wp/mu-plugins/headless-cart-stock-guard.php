<?php
/**
 * Plugin Name: WCHS Cart Stock Guard
 * Description: Rejects Store API cart mutations for out-of-stock or non-purchasable products.
 */

defined( 'ABSPATH' ) || exit;

/**
 * @param mixed           $result
 * @param WP_REST_Server  $server
 * @param WP_REST_Request $request
 */
function wchs_cart_stock_guard_pre_dispatch( $result, $server, $request ) {
	if ( ! ( $request instanceof WP_REST_Request ) ) {
		return $result;
	}

	$route  = $request->get_route();
	$method = $request->get_method();

	if ( $method !== 'POST' || ! preg_match( '#^/wc/store/v1/cart/(add-item|update-item)$#', $route ) ) {
		return $result;
	}

	$body = $request->get_json_params();
	if ( ! is_array( $body ) ) {
		return $result;
	}

	$product_id = 0;
	if ( $route === '/wc/store/v1/cart/add-item' ) {
		$product_id = absint( $body['id'] ?? 0 );
	} elseif ( $route === '/wc/store/v1/cart/update-item' ) {
		$key = sanitize_text_field( (string) ( $body['key'] ?? '' ) );
		if ( $key && function_exists( 'WC' ) && WC()->cart ) {
			$item = WC()->cart->get_cart_item( $key );
			if ( $item ) {
				$product_id = absint( $item['variation_id'] ?: $item['product_id'] );
			}
		}
	}

	if ( ! $product_id ) {
		return $result;
	}

	$product = wc_get_product( $product_id );
	if ( ! $product ) {
		return $result;
	}

	if ( ! $product->is_purchasable() || ! $product->is_in_stock() ) {
		return new WP_Error(
			'wchs_product_out_of_stock',
			__( 'This product is out of stock and cannot be purchased.', 'wchs' ),
			[ 'status' => 400 ]
		);
	}

	return $result;
}

add_filter( 'rest_pre_dispatch', 'wchs_cart_stock_guard_pre_dispatch', 20, 3 );
