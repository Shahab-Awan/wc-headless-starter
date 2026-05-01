<?php
/**
 * Plugin Name: Headless Checkout Order Sanitizer
 * Description: Rebuilds freshly-created checkout orders from the active cart when the saved order rows do not match the cart. Protects migrated HPOS stores from stale legacy order-item rows on recycled order IDs.
 *
 * Author:      WCHS Contributors
 */

defined( 'ABSPATH' ) || exit;

/**
 * Normalize a line-item shape so legacy rows and cart rows can be compared.
 */
function wchs_checkout_sanitizer_signature( array $parts ): string {
	$decimals = wc_get_price_decimals();

	return wp_json_encode(
		[
			'product_id'   => (int) ( $parts['product_id'] ?? 0 ),
			'variation_id' => (int) ( $parts['variation_id'] ?? 0 ),
			'qty'          => (int) ( $parts['qty'] ?? 0 ),
			'subtotal'     => wc_format_decimal( (float) ( $parts['subtotal'] ?? 0 ), $decimals ),
			'total'        => wc_format_decimal( (float) ( $parts['total'] ?? 0 ), $decimals ),
		],
		JSON_UNESCAPED_SLASHES
	);
}

/**
 * Build the expected line-item multiset from the active checkout cart.
 */
function wchs_checkout_sanitizer_expected_signatures(): array {
	$signatures = [];

	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return $signatures;
	}

	foreach ( WC()->cart->get_cart() as $values ) {
		$product = $values['data'] ?? null;
		if ( ! $product instanceof \WC_Product ) {
			continue;
		}

		$signatures[] = wchs_checkout_sanitizer_signature(
			[
				'product_id'   => $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id(),
				'variation_id' => $product->is_type( 'variation' ) ? $product->get_id() : 0,
				'qty'          => $values['quantity'] ?? 0,
				'subtotal'     => $values['line_subtotal'] ?? 0,
				'total'        => $values['line_total'] ?? 0,
			]
		);
	}

	sort( $signatures, SORT_STRING );

	return $signatures;
}

/**
 * Read the saved line items directly from the DB so recycled IDs cannot hide.
 */
function wchs_checkout_sanitizer_saved_signatures( int $order_id ): array {
	global $wpdb;

	$items_tbl    = $wpdb->prefix . 'woocommerce_order_items';
	$itemmeta_tbl = $wpdb->prefix . 'woocommerce_order_itemmeta';

	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"
			SELECT
				MAX(CASE WHEN oim.meta_key = '_product_id' THEN oim.meta_value END) AS product_id,
				MAX(CASE WHEN oim.meta_key = '_variation_id' THEN oim.meta_value END) AS variation_id,
				MAX(CASE WHEN oim.meta_key = '_qty' THEN oim.meta_value END) AS qty,
				MAX(CASE WHEN oim.meta_key = '_line_subtotal' THEN oim.meta_value END) AS subtotal,
				MAX(CASE WHEN oim.meta_key = '_line_total' THEN oim.meta_value END) AS total
			FROM $items_tbl oi
			LEFT JOIN $itemmeta_tbl oim ON oim.order_item_id = oi.order_item_id
			WHERE oi.order_id = %d
			  AND oi.order_item_type = 'line_item'
			GROUP BY oi.order_item_id
			ORDER BY oi.order_item_id ASC
			",
			$order_id
		),
		ARRAY_A
	);

	$signatures = [];
	foreach ( $rows as $row ) {
		$signatures[] = wchs_checkout_sanitizer_signature( $row );
	}

	sort( $signatures, SORT_STRING );

	return $signatures;
}

/**
 * When stale legacy rows hitch a ride on a new order ID, wipe the saved
 * order items and rebuild them from the active cart before payment continues.
 */
add_action(
	'woocommerce_checkout_order_created',
	function ( \WC_Order $order ) {
		if ( ! function_exists( 'WC' ) || ! WC()->cart || ! WC()->checkout() ) {
			return;
		}

		$expected = wchs_checkout_sanitizer_expected_signatures();
		if ( empty( $expected ) ) {
			return;
		}

		$actual = wchs_checkout_sanitizer_saved_signatures( $order->get_id() );
		if ( $expected === $actual ) {
			return;
		}

		$order->remove_order_items();
		WC()->checkout()->set_data_from_cart( $order );
		$order->update_meta_data( '_wchs_order_sanitized', gmdate( 'c' ) );
		$order->update_meta_data( '_wchs_order_sanitizer_expected_count', count( $expected ) );
		$order->update_meta_data( '_wchs_order_sanitizer_actual_count', count( $actual ) );
		$order->save();
		$order->add_order_note( 'WCHS rebuilt this order from the active cart after detecting stale legacy order-item rows.' );
	},
	20
);
