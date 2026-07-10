<?php
/**
 * Plugin Name: Headless Checkout Mini Cart
 * Description: Shortcode [wchs_checkout_mini_cart] — Elementor-friendly order summary for FunnelKit checkout. Uses the classic WC cart (post handoff) and mirrors changes back to the Store API session when bridged.
 * Version:     1.0.0
 * Author:      WCHS Contributors
 */

defined( 'ABSPATH' ) || exit;

define( 'WCHS_MINI_CART_DIR', __DIR__ . '/wchs-checkout-mini-cart' );
define(
	'WCHS_MINI_CART_URL',
	( defined( 'WPMU_PLUGIN_URL' ) ? trailingslashit( WPMU_PLUGIN_URL ) : content_url( '/mu-plugins/' ) ) . 'wchs-checkout-mini-cart'
);

/**
 * @param array<string, string>|string $atts
 */
function wchs_checkout_mini_cart_shortcode( $atts = [] ): string {
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return '';
	}

	$atts = shortcode_atts(
		[
			'title' => 'Your Cart',
		],
		is_array( $atts ) ? $atts : [],
		'wchs_checkout_mini_cart'
	);

	wchs_checkout_mini_cart_enqueue();

	ob_start();
	wchs_checkout_mini_cart_render( (string) $atts['title'] );
	return (string) ob_get_clean();
}
add_shortcode( 'wchs_checkout_mini_cart', 'wchs_checkout_mini_cart_shortcode' );

function wchs_checkout_mini_cart_enqueue(): void {
	static $done = false;
	if ( $done ) {
		return;
	}
	$done = true;

	$css = WCHS_MINI_CART_DIR . '/assets/mini-cart.css';
	$js  = WCHS_MINI_CART_DIR . '/assets/mini-cart.js';
	$css_ver = file_exists( $css ) ? (string) filemtime( $css ) : '1';
	$js_ver  = file_exists( $js ) ? (string) filemtime( $js ) : '1';

	wp_enqueue_style(
		'wchs-checkout-mini-cart',
		WCHS_MINI_CART_URL . '/assets/mini-cart.css',
		[],
		$css_ver
	);
	wp_enqueue_script(
		'wchs-checkout-mini-cart',
		WCHS_MINI_CART_URL . '/assets/mini-cart.js',
		[],
		$js_ver,
		true
	);
	wp_localize_script(
		'wchs-checkout-mini-cart',
		'wchsMiniCart',
		[
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'wchs_mini_cart' ),
		]
	);
}

/**
 * @return array{items:array<int,array<string,mixed>>,coupons:array<int,string>,subtotal:string,shipping:string,shipping_is_free:bool,total:string,savings_amount:string,savings_pct:int,empty:bool}
 */
function wchs_checkout_mini_cart_payload(): array {
	$cart = WC()->cart;
	$items = [];
	$savings_minor = 0.0;
	$compare_minor = 0.0;

	foreach ( $cart->get_cart() as $key => $line ) {
		$product = $line['data'] ?? null;
		if ( ! $product instanceof WC_Product ) {
			continue;
		}

		$qty  = max( 1, (int) ( $line['quantity'] ?? 1 ) );
		$name = $product->get_name();
		$thumb = wp_get_attachment_image_url( $product->get_image_id(), 'woocommerce_gallery_thumbnail' );
		if ( ! $thumb ) {
			$thumb = wc_placeholder_img_src( 'woocommerce_gallery_thumbnail' );
		}

		$line_total = (float) ( $line['line_total'] ?? 0 );
		$regular    = (float) $product->get_regular_price();
		if ( $regular <= 0 && $product->is_type( 'variation' ) ) {
			$parent = wc_get_product( $product->get_parent_id() );
			if ( $parent ) {
				$regular = (float) $parent->get_regular_price();
			}
		}
		$compare_line = $regular > 0 ? $regular * $qty : $line_total;
		$has_compare  = $compare_line > $line_total + 0.009;

		$savings_minor += max( 0, $compare_line - $line_total );
		$compare_minor += max( $compare_line, $line_total );

		$items[] = [
			'key'           => (string) $key,
			'name'          => $name,
			'qty'           => $qty,
			'thumb'         => $thumb,
			'price'         => wc_price( $line_total ),
			'compare_price' => $has_compare ? wc_price( $compare_line ) : '',
			'has_compare'   => $has_compare,
		];
	}

	$shipping_total = (float) $cart->get_shipping_total();
	$needs_shipping = $cart->needs_shipping();
	$shipping_free  = ! $needs_shipping || $shipping_total <= 0.009;

	$coupons = [];
	foreach ( $cart->get_applied_coupons() as $code ) {
		$coupons[] = (string) $code;
	}

	$savings_pct = $compare_minor > 0
		? (int) round( ( $savings_minor / $compare_minor ) * 100 )
		: 0;

	return [
		'items'            => $items,
		'coupons'          => $coupons,
		'subtotal'         => wc_price( (float) $cart->get_subtotal() ),
		'shipping'         => $shipping_free ? 'Free' : wc_price( $shipping_total ),
		'shipping_is_free' => $shipping_free,
		'total'            => wc_price( (float) $cart->get_total( 'edit' ) ),
		'savings_amount'   => $savings_minor > 0.009 ? wc_price( $savings_minor ) : '',
		'savings_pct'      => $savings_pct,
		'empty'            => count( $items ) < 1,
	];
}

function wchs_checkout_mini_cart_render( string $title = 'Your Cart' ): void {
	$data  = wchs_checkout_mini_cart_payload();
	$title = $title !== '' ? $title : 'Your Cart';
	?>
	<div class="wchs-mini-cart" data-wchs-mini-cart data-title="<?php echo esc_attr( $title ); ?>">
		<div class="wchs-mini-cart__inner">
			<h3 class="wchs-mini-cart__title"><?php echo esc_html( $title ); ?></h3>

			<?php if ( $data['empty'] ) : ?>
				<p class="wchs-mini-cart__empty">Your cart is empty</p>
			<?php else : ?>
				<ul class="wchs-mini-cart__items">
					<?php foreach ( $data['items'] as $item ) : ?>
						<li class="wchs-mini-cart__item" data-key="<?php echo esc_attr( $item['key'] ); ?>">
							<div class="wchs-mini-cart__media">
								<img src="<?php echo esc_url( $item['thumb'] ); ?>" alt="" width="56" height="56" loading="lazy" />
								<span class="wchs-mini-cart__qty" aria-label="Quantity"><?php echo esc_html( (string) $item['qty'] ); ?></span>
							</div>
							<div class="wchs-mini-cart__info">
								<p class="wchs-mini-cart__name"><?php echo esc_html( $item['name'] ); ?></p>
							</div>
							<div class="wchs-mini-cart__aside">
								<div class="wchs-mini-cart__prices">
									<?php if ( $item['has_compare'] ) : ?>
										<span class="wchs-mini-cart__was"><?php echo wp_kses_post( $item['compare_price'] ); ?></span>
									<?php endif; ?>
									<span class="wchs-mini-cart__now"><?php echo wp_kses_post( $item['price'] ); ?></span>
								</div>
								<button
									type="button"
									class="wchs-mini-cart__remove"
									data-action="remove"
									data-key="<?php echo esc_attr( $item['key'] ); ?>"
									aria-label="<?php echo esc_attr( sprintf( 'Remove %s', $item['name'] ) ); ?>"
								>
									<span aria-hidden="true">×</span>
								</button>
							</div>
						</li>
					<?php endforeach; ?>
				</ul>

				<form class="wchs-mini-cart__coupon" data-coupon-form>
					<input
						type="text"
						name="coupon_code"
						class="wchs-mini-cart__coupon-input"
						placeholder="Coupon code"
						autocomplete="off"
						aria-label="Coupon code"
					/>
					<button type="submit" class="wchs-mini-cart__coupon-btn">Apply</button>
				</form>
				<p class="wchs-mini-cart__coupon-msg" data-coupon-msg hidden></p>

				<?php if ( ! empty( $data['coupons'] ) ) : ?>
					<ul class="wchs-mini-cart__coupon-list">
						<?php foreach ( $data['coupons'] as $code ) : ?>
							<li>
								<span><?php echo esc_html( $code ); ?></span>
								<button
									type="button"
									class="wchs-mini-cart__coupon-remove"
									data-action="remove-coupon"
									data-code="<?php echo esc_attr( $code ); ?>"
									aria-label="<?php echo esc_attr( sprintf( 'Remove coupon %s', $code ) ); ?>"
								>×</button>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>

				<div class="wchs-mini-cart__totals">
					<div class="wchs-mini-cart__row">
						<span>Subtotal</span>
						<span><?php echo wp_kses_post( $data['subtotal'] ); ?></span>
					</div>
					<div class="wchs-mini-cart__row">
						<span>Shipping</span>
						<span><?php echo $data['shipping_is_free'] ? esc_html( $data['shipping'] ) : wp_kses_post( $data['shipping'] ); ?></span>
					</div>
					<div class="wchs-mini-cart__row wchs-mini-cart__row--total">
						<span>Total</span>
						<span><?php echo wp_kses_post( $data['total'] ); ?></span>
					</div>
				</div>

				<?php if ( $data['savings_amount'] !== '' && $data['savings_pct'] > 0 ) : ?>
					<p class="wchs-mini-cart__savings">
						<span class="wchs-mini-cart__savings-icon" aria-hidden="true">%</span>
						<span>
							Your saving:
							<?php echo wp_kses_post( $data['savings_amount'] ); ?>
							(<?php echo esc_html( (string) $data['savings_pct'] ); ?>%) on this order
						</span>
					</p>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<div class="wchs-mini-cart__busy" hidden></div>
	</div>
	<?php
}

function wchs_checkout_mini_cart_after_mutate(): void {
	if ( function_exists( 'WC' ) && WC()->cart ) {
		WC()->cart->calculate_totals();
	}
	if ( function_exists( 'wchs_push_classic_cart_to_bridged_store_api' ) ) {
		wchs_push_classic_cart_to_bridged_store_api();
	}
	if ( function_exists( 'WC' ) && WC()->session && method_exists( WC()->session, 'save_data' ) ) {
		WC()->session->save_data();
	}
}

function wchs_checkout_mini_cart_ajax_html(): void {
	$title = isset( $_REQUEST['title'] ) ? sanitize_text_field( wp_unslash( (string) $_REQUEST['title'] ) ) : 'Your Cart';
	ob_start();
	wchs_checkout_mini_cart_render( $title );
	$html = (string) ob_get_clean();
	wp_send_json_success(
		[
			'html'  => $html,
			'count' => WC()->cart ? WC()->cart->get_cart_contents_count() : 0,
		]
	);
}

function wchs_checkout_mini_cart_verify(): void {
	if ( ! check_ajax_referer( 'wchs_mini_cart', 'nonce', false ) ) {
		wp_send_json_error( [ 'message' => 'Invalid session.' ], 403 );
	}
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		wp_send_json_error( [ 'message' => 'Cart unavailable.' ], 503 );
	}
}

function wchs_checkout_mini_cart_ajax_remove(): void {
	wchs_checkout_mini_cart_verify();
	$key = isset( $_POST['key'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['key'] ) ) : '';
	if ( $key === '' ) {
		wp_send_json_error( [ 'message' => 'Missing item.' ], 400 );
	}
	WC()->cart->remove_cart_item( $key );
	wchs_checkout_mini_cart_after_mutate();
	wchs_checkout_mini_cart_ajax_html();
}

function wchs_checkout_mini_cart_ajax_apply_coupon(): void {
	wchs_checkout_mini_cart_verify();
	$code = isset( $_POST['code'] ) ? wc_format_coupon_code( wp_unslash( (string) $_POST['code'] ) ) : '';
	if ( $code === '' ) {
		wp_send_json_error( [ 'message' => 'Enter a coupon code.' ], 400 );
	}
	$result = WC()->cart->apply_coupon( $code );
	if ( ! $result ) {
		$notices = wc_get_notices( 'error' );
		wc_clear_notices();
		$msg = 'Coupon could not be applied.';
		if ( ! empty( $notices[0]['notice'] ) ) {
			$msg = wp_strip_all_tags( (string) $notices[0]['notice'] );
		}
		wp_send_json_error( [ 'message' => $msg ], 400 );
	}
	wc_clear_notices();
	wchs_checkout_mini_cart_after_mutate();
	wchs_checkout_mini_cart_ajax_html();
}

function wchs_checkout_mini_cart_ajax_remove_coupon(): void {
	wchs_checkout_mini_cart_verify();
	$code = isset( $_POST['code'] ) ? wc_format_coupon_code( wp_unslash( (string) $_POST['code'] ) ) : '';
	if ( $code === '' ) {
		wp_send_json_error( [ 'message' => 'Missing coupon.' ], 400 );
	}
	WC()->cart->remove_coupon( $code );
	wchs_checkout_mini_cart_after_mutate();
	wchs_checkout_mini_cart_ajax_html();
}

add_action( 'wp_ajax_wchs_mini_cart_remove', 'wchs_checkout_mini_cart_ajax_remove' );
add_action( 'wp_ajax_nopriv_wchs_mini_cart_remove', 'wchs_checkout_mini_cart_ajax_remove' );
add_action( 'wp_ajax_wchs_mini_cart_apply_coupon', 'wchs_checkout_mini_cart_ajax_apply_coupon' );
add_action( 'wp_ajax_nopriv_wchs_mini_cart_apply_coupon', 'wchs_checkout_mini_cart_ajax_apply_coupon' );
add_action( 'wp_ajax_wchs_mini_cart_remove_coupon', 'wchs_checkout_mini_cart_ajax_remove_coupon' );
add_action( 'wp_ajax_nopriv_wchs_mini_cart_remove_coupon', 'wchs_checkout_mini_cart_ajax_remove_coupon' );
