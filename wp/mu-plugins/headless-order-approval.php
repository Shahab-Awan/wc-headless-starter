<?php
/**
 * Plugin Name: Headless Order Approval
 * Description: One-click "Approve Payment" for on-hold orders. Adds a button
 *              to the order detail page and a bulk action to the orders list.
 *              Moves order from on-hold → processing, which triggers WC's
 *              processing email automatically.

 * Author:      WCHS Contributors
 */

defined( 'ABSPATH' ) || exit;

// ─── Single order: meta box button ──────────────────────────────

add_action( 'add_meta_boxes', function () {
	add_meta_box(
		'wchs_order_approval',
		'Payment Approval',
		'wchs_render_approval_box',
		'shop_order',
		'side',
		'high'
	);
	// HPOS (High-Performance Order Storage) compatibility
	add_meta_box(
		'wchs_order_approval',
		'Payment Approval',
		'wchs_render_approval_box',
		'woocommerce_page_wc-orders',
		'side',
		'high'
	);
} );

function wchs_render_approval_box( $post_or_order ): void {
	$order = $post_or_order instanceof \WP_Post
		? wc_get_order( $post_or_order->ID )
		: $post_or_order;

	if ( ! $order || $order->get_status() !== 'on-hold' ) {
		echo '<p style="color:#666;font-size:13px;margin:0">Only available for on-hold orders.</p>';
		return;
	}

	$nonce = wp_create_nonce( 'wchs_approve_order_' . $order->get_id() );
	$url   = admin_url( 'admin-post.php?action=wchs_approve_order&order_id=' . $order->get_id() . '&_wpnonce=' . $nonce );
	?>
	<p style="margin:0 0 8px;font-size:13px;color:#666">
		Move this order to "Processing" and notify the customer.
	</p>
	<a href="<?php echo esc_url( $url ); ?>" class="button button-primary" style="width:100%;text-align:center">
		Approve Payment
	</a>
	<?php
}

// ─── Single order: handle approval ──────────────────────────────

add_action( 'admin_post_wchs_approve_order', function () {
	if ( ! current_user_can( 'edit_shop_orders' ) ) {
		wp_die( 'Unauthorized' );
	}

	$order_id = absint( $_GET['order_id'] ?? 0 );
	check_admin_referer( 'wchs_approve_order_' . $order_id );

	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		wp_die( 'Order not found' );
	}

	if ( $order->get_status() === 'on-hold' ) {
		$order->update_status( 'processing', 'Payment approved by admin (' . wp_get_current_user()->display_name . ').' );
	}

	$redirect = ( $_GET['redirect'] ?? '' ) === 'list'
		? admin_url( 'edit.php?post_type=shop_order&wchs_approved=1' )
		: $order->get_edit_order_url();
	wp_safe_redirect( $redirect );
	exit;
} );

// ─── Row action: one-click approve from orders list ─────────────

add_filter( 'woocommerce_admin_order_actions', function ( $actions, $order ) {
	if ( $order->get_status() === 'on-hold' ) {
		$nonce = wp_create_nonce( 'wchs_approve_order_' . $order->get_id() );
		$actions['wchs_approve'] = [
			'url'    => admin_url( 'admin-post.php?action=wchs_approve_order&order_id=' . $order->get_id() . '&_wpnonce=' . $nonce . '&redirect=list' ),
			'name'   => 'Approve Payment',
			'action' => 'wchs_approve',
		];
	}
	return $actions;
}, 10, 2 );

// Style the row action button green
add_action( 'admin_head', function () {
	$screen = get_current_screen();
	if ( ! $screen || ! in_array( $screen->id, [ 'edit-shop_order', 'woocommerce_page_wc-orders' ], true ) ) return;
	echo '<style>.wc-action-button-wchs_approve::after { font-family: woocommerce !important; content: "\e015" !important; color: #059669 !important; }</style>';
} );

// ─── Bulk action: approve multiple orders ───────────────────────

add_filter( 'bulk_actions-edit-shop_order', function ( $actions ) {
	$actions['wchs_approve_payment'] = 'Approve Payment (on-hold → processing)';
	return $actions;
} );

// HPOS compatibility
add_filter( 'bulk_actions-woocommerce_page_wc-orders', function ( $actions ) {
	$actions['wchs_approve_payment'] = 'Approve Payment (on-hold → processing)';
	return $actions;
} );

add_filter( 'handle_bulk_actions-edit-shop_order', 'wchs_handle_bulk_approve', 10, 3 );
add_filter( 'handle_bulk_actions-woocommerce_page_wc-orders', 'wchs_handle_bulk_approve', 10, 3 );

function wchs_handle_bulk_approve( string $redirect_to, string $action, array $order_ids ): string {
	if ( $action !== 'wchs_approve_payment' ) {
		return $redirect_to;
	}

	$approved = 0;
	foreach ( $order_ids as $order_id ) {
		$order = wc_get_order( $order_id );
		if ( $order && $order->get_status() === 'on-hold' ) {
			$order->update_status( 'processing', 'Bulk payment approval by admin (' . wp_get_current_user()->display_name . ').' );
			$approved++;
		}
	}

	return add_query_arg( 'wchs_approved', $approved, $redirect_to );
}

// Show admin notice after bulk approval
add_action( 'admin_notices', function () {
	if ( ! empty( $_GET['wchs_approved'] ) ) {
		$count = (int) $_GET['wchs_approved'];
		printf(
			'<div class="notice notice-success is-dismissible"><p>%d order(s) approved and moved to processing.</p></div>',
			$count
		);
	}
} );
