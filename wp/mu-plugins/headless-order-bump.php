<?php
/**
 * Plugin Name: Headless Order Bump
 * Description: Adds a styled product offer at the bottom of the WC checkout.
 *              Customer checks a box -> product added to cart (not just the
 *              order). This means fees, coupons, and tax all include the bump.
 *
 *
 * Author:      WCHS Contributors
 *
 * How it works:
 *   1. Render checkbox via woocommerce_review_order_before_submit
 *   2. JS triggers update_checkout on checkbox change
 *   3. woocommerce_checkout_update_order_review hook reads the serialized
 *      post_data, adds/removes the bump from the cart
 *   4. WC recalculates totals (fees, tax, shipping) with bump included
 *   5. On place_order, bump is already a cart item — no special handling
 *
 * Config: WCHS admin -> Checkout -> Order Bump Product ID
 */

defined( 'ABSPATH' ) || exit;

function wchs_bump_product_id(): int {
	if ( ! class_exists( '\WCHS\Admin\AdminPage' ) ) {
		return 0;
	}
	$s = \WCHS\Admin\AdminPage::get_site_settings();
	return (int) ( $s['bump_product_id'] ?? 0 );
}

/**
 * Find the cart item key for the bump product, if it's in the cart.
 */
function wchs_bump_cart_key(): ?string {
	$pid = wchs_bump_product_id();
	if ( ! $pid ) return null;
	foreach ( WC()->cart->get_cart() as $key => $item ) {
		if ( (int) $item['product_id'] === $pid && ! empty( $item['wchs_bump'] ) ) {
			return $key;
		}
	}
	return null;
}

// ─── Render the bump checkbox ─────────────────────────────────

add_action( 'woocommerce_review_order_before_submit', function () {
	$pid = wchs_bump_product_id();
	if ( ! $pid ) return;

	$s = \WCHS\Admin\AdminPage::get_site_settings();
	$vid = (int) ( $s['bump_variation_id'] ?? 0 );
	$product = $vid ? wc_get_product( $vid ) : wc_get_product( $pid );
	if ( ! $product || ! $product->is_purchasable() || ! $product->is_in_stock() ) return;

	// Check if bump is already in cart (e.g. after update_checkout re-render)
	$checked = wchs_bump_cart_key() ? 'checked' : '';

	$img_id = $product->get_image_id();
	if ( ! $img_id && $product->is_type( 'variation' ) ) {
		$parent = wc_get_product( $product->get_parent_id() );
		if ( $parent ) $img_id = $parent->get_image_id();
	}
	$image = $img_id ? wp_get_attachment_image( $img_id, 'thumbnail' ) : '';
	$price = html_entity_decode( strip_tags( wc_price( $product->get_price() ) ), ENT_QUOTES, 'UTF-8' );
	$display_name = $product->get_name();
	if ( $product->is_type( 'variation' ) ) {
		$parent = wc_get_product( $product->get_parent_id() );
		$attrs  = $product->get_variation_attributes();
		$display_name = $parent ? $parent->get_name() : $display_name;
		if ( ! empty( $attrs ) ) {
			$display_name .= ' - ' . implode( ', ', array_values( $attrs ) );
		}
	}
	?>
	<div class="wchs-bump" style="border:1px solid var(--border, #c9ccd1);padding:16px;margin:16px 0;font-family:var(--font-sans, Inter, sans-serif);">
		<label style="display:flex;align-items:flex-start;gap:12px;cursor:pointer;">
			<input type="checkbox" name="wchs_bump_add" value="1" <?php echo $checked; ?> style="margin-top:4px;accent-color:var(--accent, #0c0c0c);width:18px;height:18px;flex-shrink:0;" />
			<div style="display:flex;gap:12px;align-items:center;flex:1;">
				<?php if ( $image ) : ?>
					<div style="width:48px;height:48px;flex-shrink:0;overflow:hidden;"><?php echo $image; ?></div>
				<?php endif; ?>
				<div>
					<strong style="font-size:13px;display:block;margin-bottom:2px;"><?php echo esc_html( $display_name ); ?></strong>
					<span style="font-size:12px;color:var(--fg-muted, #767d88);">Add to your order for just <?php echo $price; ?></span>
				</div>
			</div>
		</label>
	</div>
	<?php
} );

// ─── Trigger update_checkout when checkbox changes ────────────

add_action( 'woocommerce_after_checkout_form', function () {
	if ( ! wchs_bump_product_id() ) return;
	?>
	<script>
	jQuery(function($) {
		$('form.checkout').on('change', 'input[name="wchs_bump_add"]', function() {
			$(document.body).trigger('update_checkout');
		});
	});
	</script>
	<?php
} );

// ─── Add/remove bump from cart on update_order_review ─────────

add_action( 'woocommerce_checkout_update_order_review', function ( $post_data_string ) {
	$pid = wchs_bump_product_id();
	if ( ! $pid ) return;

	// Parse the serialized form data that WC sends
	parse_str( $post_data_string, $post_data );
	$wants_bump = ! empty( $post_data['wchs_bump_add'] );

	$existing_key = wchs_bump_cart_key();

	if ( $wants_bump && ! $existing_key ) {
		// Add bump to cart with a flag so we can identify it later
		$s = \WCHS\Admin\AdminPage::get_site_settings();
		$vid = (int) ( $s['bump_variation_id'] ?? 0 );
		$product = $vid ? wc_get_product( $vid ) : wc_get_product( $pid );
		if ( $product && $product->is_purchasable() && $product->is_in_stock() ) {
			$variation_attrs = [];
			if ( $vid && $product->is_type( 'variation' ) ) {
				$variation_attrs = $product->get_variation_attributes();
			}
			WC()->cart->add_to_cart( $pid, 1, $vid, $variation_attrs, [ 'wchs_bump' => true ] );
		}
	} elseif ( ! $wants_bump && $existing_key ) {
		// Remove bump from cart
		WC()->cart->remove_cart_item( $existing_key );
	}
} );

// ─── Add order note when bump is purchased ────────────────────

add_action( 'woocommerce_checkout_create_order', function ( \WC_Order $order ) {
	$pid = wchs_bump_product_id();
	if ( ! $pid ) return;

	foreach ( WC()->cart->get_cart() as $item ) {
		if ( (int) $item['product_id'] === $pid && ! empty( $item['wchs_bump'] ) ) {
			$product = $item['data'];
			$order->add_order_note( sprintf(
				'Order bump: %s ($%s)',
				$product->get_name(),
				number_format( (float) $product->get_price(), 2 )
			) );
			return;
		}
	}
} );
