<?php
/**
 * One-shot cleanup for stale WooCommerce order-item rows left behind after
 * deleting imported orders on an HPOS store.
 *
 * Problem:
 *   - A migration imports historical orders, then later deletes them.
 *   - `wp_woocommerce_order_items` / `wp_woocommerce_order_itemmeta` rows can
 *     remain even after the order row is gone.
 *   - When `wc_orders.id` later reuses that deleted ID, the new checkout order
 *     can appear to "inherit" the dead order's line items.
 *
 * What this script deletes:
 *   - Any order-item row whose `order_id` no longer exists in `wp_wc_orders`
 *   - Its corresponding `wp_woocommerce_order_itemmeta` rows
 *
 * Run:
 *   wp eval-file docs/examples/cleanup-orphan-order-items.php
 */

if ( ! function_exists( 'wc_get_orders' ) ) {
	echo "WooCommerce not loaded\n";
	exit( 1 );
}

global $wpdb;

$items_tbl    = $wpdb->prefix . 'woocommerce_order_items';
$itemmeta_tbl = $wpdb->prefix . 'woocommerce_order_itemmeta';
$orders_tbl   = $wpdb->prefix . 'wc_orders';

$orphan_order_ids = $wpdb->get_col(
	"
	SELECT DISTINCT oi.order_id
	FROM $items_tbl oi
	LEFT JOIN $orders_tbl o ON o.id = oi.order_id
	WHERE o.id IS NULL
	ORDER BY oi.order_id DESC
	LIMIT 20
	"
);

$orphan_items = (int) $wpdb->get_var(
	"
	SELECT COUNT(*)
	FROM $items_tbl oi
	LEFT JOIN $orders_tbl o ON o.id = oi.order_id
	WHERE o.id IS NULL
	"
);

$orphan_meta = (int) $wpdb->get_var(
	"
	SELECT COUNT(*)
	FROM $itemmeta_tbl oim
	JOIN $items_tbl oi ON oi.order_item_id = oim.order_item_id
	LEFT JOIN $orders_tbl o ON o.id = oi.order_id
	WHERE o.id IS NULL
	"
);

echo "═══ Orphan order-item audit ═══\n";
echo "items: $orphan_items\n";
echo "meta rows: $orphan_meta\n";
echo 'sample missing order_ids: ' . wp_json_encode( array_map( 'intval', $orphan_order_ids ) ) . "\n";

if ( $orphan_items <= 0 ) {
	echo "Nothing to delete.\n";
	return;
}

$deleted_meta = $wpdb->query(
	"
	DELETE oim
	FROM $itemmeta_tbl oim
	JOIN $items_tbl oi ON oi.order_item_id = oim.order_item_id
	LEFT JOIN $orders_tbl o ON o.id = oi.order_id
	WHERE o.id IS NULL
	"
);

$deleted_items = $wpdb->query(
	"
	DELETE oi
	FROM $items_tbl oi
	LEFT JOIN $orders_tbl o ON o.id = oi.order_id
	WHERE o.id IS NULL
	"
);

echo "\n═══ Deleted ═══\n";
echo 'itemmeta rows: ' . (int) $deleted_meta . "\n";
echo 'order_items rows: ' . (int) $deleted_items . "\n";
