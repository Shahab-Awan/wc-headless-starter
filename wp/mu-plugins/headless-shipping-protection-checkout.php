<?php
/**
 * Plugin Name: Headless Shipping Protection Checkout
 * Description: Checkout shows tiered "Protected Shipping" as a fee line (not the hidden WC product). Remove via ×; no qty controls.
 * Version:     0.2.0
 * Author:      WCHS Contributors
 */

defined( 'ABSPATH' ) || exit;

const WCHS_PROTECTED_SHIPPING_SESSION = 'wchs_protected_shipping_enabled';
const WCHS_PROTECTED_SHIPPING_FEE_NAME = 'Protected Shipping';

/**
 * @param array<string, mixed> $cart_item
 */
function wchs_checkout_line_is_shipping_protection( array $cart_item ): bool {
	if ( ! empty( $cart_item['wchs_shipping_protection'] ) ) {
		return true;
	}
	if ( function_exists( 'wchs_shipping_protection_product_id' ) ) {
		$protect_id = wchs_shipping_protection_product_id();
		if ( $protect_id > 0 && (int) ( $cart_item['product_id'] ?? 0 ) === $protect_id ) {
			return true;
		}
	}
	$product = $cart_item['data'] ?? null;
	if ( $product instanceof \WC_Product ) {
		if ( 'shipping-protection' === $product->get_slug() ) {
			return true;
		}
		if ( false !== stripos( $product->get_name(), 'shipping protection' ) ) {
			return true;
		}
	}
	return false;
}

function wchs_protected_shipping_is_enabled(): bool {
	if ( ! function_exists( 'WC' ) || ! WC()->session ) {
		return false;
	}
	$flag = WC()->session->get( WCHS_PROTECTED_SHIPPING_SESSION );
	return '0' !== $flag && 0 !== $flag && false !== $flag;
}

function wchs_protected_shipping_set_enabled( bool $enabled ): void {
	if ( ! function_exists( 'WC' ) || ! WC()->session ) {
		return;
	}
	WC()->session->set( WCHS_PROTECTED_SHIPPING_SESSION, $enabled ? '1' : '0' );
}

/**
 * Remove hidden shipping-protection product lines from the classic cart (checkout uses a fee instead).
 */
function wchs_strip_shipping_protection_products_from_cart(): void {
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return;
	}
	static $stripping = false;
	if ( $stripping ) {
		return;
	}
	$stripping = true;
	$removed   = false;
	foreach ( WC()->cart->get_cart() as $key => $item ) {
		if ( ! is_array( $item ) || ! wchs_checkout_line_is_shipping_protection( $item ) ) {
			continue;
		}
		WC()->cart->remove_cart_item( $key );
		$removed = true;
	}
	if ( $removed && WC()->session && null === WC()->session->get( WCHS_PROTECTED_SHIPPING_SESSION ) ) {
		wchs_protected_shipping_set_enabled( true );
	}
	$stripping = false;
}

/**
 * After SPA cart bridge import: enable protected shipping if Store API cart had protection lines.
 *
 * @param array<string, mixed> $store_cart_session Store API cart array from session row.
 */
function wchs_protected_shipping_flag_from_store_cart( array $store_cart_session ): void {
	if ( ! function_exists( 'WC' ) || ! WC()->session ) {
		return;
	}
	$cart = $store_cart_session['cart'] ?? null;
	if ( ! is_array( $cart ) ) {
		return;
	}
	$had_protection = false;
	foreach ( $cart as $item ) {
		if ( ! is_array( $item ) ) {
			continue;
		}
		$product_id = (int) ( $item['product_id'] ?? 0 );
		if ( function_exists( 'wchs_shipping_protection_product_id' ) && $product_id === wchs_shipping_protection_product_id() ) {
			$had_protection = true;
			break;
		}
	}
	if ( $had_protection && '0' !== WC()->session->get( WCHS_PROTECTED_SHIPPING_SESSION ) ) {
		wchs_protected_shipping_set_enabled( true );
	}
}

add_action( 'woocommerce_cart_loaded_from_session', 'wchs_strip_shipping_protection_products_from_cart', 5 );
add_action( 'woocommerce_before_calculate_totals', 'wchs_strip_shipping_protection_products_from_cart', 1 );

add_action(
	'woocommerce_cart_calculate_fees',
	static function ( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}
		if ( ! $cart instanceof \WC_Cart || ! wchs_protected_shipping_is_enabled() ) {
			return;
		}
		if ( ! function_exists( 'wchs_shipping_protection_fee_major' ) || ! function_exists( 'wchs_shipping_protection_cart_subtotal_major' ) ) {
			return;
		}
		$basis = wchs_shipping_protection_cart_subtotal_major( $cart );
		if ( $basis <= 0 && $cart->is_empty() ) {
			return;
		}
		$fee_major = wchs_shipping_protection_fee_major( $basis );
		if ( $fee_major <= 0 ) {
			return;
		}
		$cart->add_fee( WCHS_PROTECTED_SHIPPING_FEE_NAME, (float) wc_format_decimal( $fee_major ), false );
	},
	20,
	1
);

add_filter(
	'woocommerce_cart_item_visible',
	static function ( $visible, $cart_item, $cart_item_key ) {
		unset( $cart_item_key );
		if ( is_array( $cart_item ) && wchs_checkout_line_is_shipping_protection( $cart_item ) ) {
			return false;
		}
		return $visible;
	},
	20,
	3
);

add_filter(
	'woocommerce_cart_totals_fee_html',
	static function ( $fee_html, $fee ) {
		if ( is_object( $fee ) && isset( $fee->name ) && WCHS_PROTECTED_SHIPPING_FEE_NAME === $fee->name ) {
			return '';
		}
		return $fee_html;
	},
	20,
	2
);

function wchs_protected_shipping_fee_amount_html(): string {
	if ( ! function_exists( 'WC' ) || ! WC()->cart || ! wchs_protected_shipping_is_enabled() ) {
		return '';
	}
	foreach ( WC()->cart->get_fees() as $fee ) {
		if ( WCHS_PROTECTED_SHIPPING_FEE_NAME === $fee->name ) {
			return wc_price( (float) $fee->amount );
		}
	}
	return '';
}

function wchs_is_protected_shipping_checkout_surface(): bool {
	if ( function_exists( 'wchs_funnelkit_is_checkout_request' ) && wchs_funnelkit_is_checkout_request() ) {
		return true;
	}
	return function_exists( 'is_checkout' ) && is_checkout() && ! is_wc_endpoint_url( 'order-received' );
}

add_action(
	'woocommerce_review_order_after_cart_contents',
	static function () {
		if ( ! wchs_is_protected_shipping_checkout_surface() || ! wchs_protected_shipping_is_enabled() ) {
			return;
		}
		$price = wchs_protected_shipping_fee_amount_html();
		if ( '' === $price ) {
			return;
		}
		?>
		<tr class="wchs-protected-shipping-row">
			<td class="wchs-protected-shipping-row__label" colspan="2">
				<span class="wchs-protected-shipping-row__name"><?php echo esc_html( WCHS_PROTECTED_SHIPPING_FEE_NAME ); ?></span>
				<button type="button" class="wchs-protected-shipping-row__remove" data-wchs-protected-shipping-remove aria-label="<?php esc_attr_e( 'Remove protected shipping', 'wchs' ); ?>">×</button>
			</td>
			<td class="wchs-protected-shipping-row__total product-total"><?php echo wp_kses_post( $price ); ?></td>
		</tr>
		<?php
	},
	15
);

add_action(
	'wp_ajax_wchs_remove_protected_shipping',
	static function () {
		check_ajax_referer( 'wchs_protected_shipping' );
		wchs_protected_shipping_set_enabled( false );
		if ( function_exists( 'WC' ) && WC()->cart ) {
			WC()->cart->calculate_totals();
		}
		wp_send_json_success();
	}
);
add_action(
	'wp_ajax_nopriv_wchs_remove_protected_shipping',
	static function () {
		check_ajax_referer( 'wchs_protected_shipping' );
		wchs_protected_shipping_set_enabled( false );
		if ( function_exists( 'WC' ) && WC()->cart ) {
			WC()->cart->calculate_totals();
		}
		wp_send_json_success();
	}
);

add_action(
	'wp_enqueue_scripts',
	static function () {
		if ( ! wchs_is_protected_shipping_checkout_surface() || ! defined( 'WCHS_DS_URL' ) ) {
			return;
		}
		$ver = '0.2.0';
		wp_enqueue_style(
			'wchs-protected-shipping-checkout',
			WCHS_DS_URL . '/assets/checkout-protected-shipping.css',
			defined( 'WCHS_DS_VERSION' ) ? [ 'wchs-ds-tokens' ] : [],
			$ver
		);
		wp_enqueue_script(
			'wchs-protected-shipping-checkout',
			WCHS_DS_URL . '/assets/checkout-protected-shipping.js',
			[],
			$ver,
			true
		);
		wp_localize_script(
			'wchs-protected-shipping-checkout',
			'wchsProtectedShipping',
			[
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'wchs_protected_shipping' ),
				'label'    => WCHS_PROTECTED_SHIPPING_FEE_NAME,
				'enabled'  => wchs_protected_shipping_is_enabled(),
				'priceHtml' => wchs_protected_shipping_fee_amount_html(),
			]
		);
	},
	1001
);

add_filter(
	'woocommerce_add_cart_item_data',
	static function ( $cart_item_data, $product_id ) {
		if ( ! is_array( $cart_item_data ) || ! function_exists( 'wchs_shipping_protection_product_id' ) ) {
			return $cart_item_data;
		}
		if ( (int) $product_id === wchs_shipping_protection_product_id() ) {
			$cart_item_data['wchs_shipping_protection'] = true;
		}
		return $cart_item_data;
	},
	10,
	2
);
