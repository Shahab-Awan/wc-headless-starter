<?php
/**
 * Plugin Name: Headless One-Click Upsell
 * Description: Post-purchase one-click upsell engine. After checkout, intercepts
 *              the thank-you redirect and shows an offer page. On accept, charges
 *              the saved Stripe payment method (for card payments) or simply adds
 *              the product to the order total (for offline gateways).
 *
 *
 * Author:      WCHS Contributors
 *
 * Mechanics:
 *   1. woocommerce_get_checkout_order_received_url — rewrite to offer page URL
 *   2. Offer page renders product + "Yes, add to order" / "No thanks" buttons
 *   3. Accept (Stripe): creates a new PaymentIntent with saved PM + off_session
 *      Accept (offline): adds product to order, recalculates total (no charge)
 *   4. Decline: redirects to normal order-received (SPA redirects from there)
 *
 * Config: WCHS admin → Checkout tab. Enable the flow there, then pick
 * per-product upsells in WooCommerce's Linked Products UI.
 */

defined( 'ABSPATH' ) || exit;

// ─── Config ─────────────────────────────────────────────────────

function wchs_upsell_config(): array {
	if ( ! class_exists( '\WCHS\Admin\AdminPage' ) ) {
		return [ 'enabled' => false ];
	}
	$settings = \WCHS\Admin\AdminPage::get_site_settings();
	return [
		'enabled' => ! empty( $settings['upsell_enabled'] ),
	];
}

function wchs_is_offline_gateway( string $method ): bool {
	return str_starts_with( $method, 'wchs_offline_' );
}

function wchs_upsell_supports_payment_method( string $method ): bool {
	return 'stripe' === $method
		|| wchs_is_offline_gateway( $method )
		|| in_array( $method, [ 'cod', 'bacs', 'cheque' ], true );
}

function wchs_upsell_validate_variation( \WC_Product $parent, int $variation_id ): ?\WC_Product_Variation {
	if ( $variation_id <= 0 || ! $parent->is_type( 'variable' ) ) {
		return null;
	}

	$variation = wc_get_product( $variation_id );
	if ( ! $variation instanceof \WC_Product_Variation ) {
		return null;
	}
	if ( $variation->get_parent_id() !== $parent->get_id() ) {
		return null;
	}
	if ( ! $variation->is_purchasable() || ! $variation->is_in_stock() ) {
		return null;
	}

	return $variation;
}

function wchs_upsell_default_variation( \WC_Product $product ): ?\WC_Product_Variation {
	if ( ! $product->is_type( 'variable' ) ) {
		return null;
	}

	$defaults = array_filter(
		(array) $product->get_default_attributes(),
		static fn( $value ) => '' !== (string) $value
	);

	if ( ! empty( $defaults ) ) {
		$data_store = \WC_Data_Store::load( 'product' );
		if ( is_object( $data_store ) && is_callable( [ $data_store, 'find_matching_product_variation' ] ) ) {
			$variation_id = (int) $data_store->find_matching_product_variation( $product, $defaults );
			$variation    = wchs_upsell_validate_variation( $product, $variation_id );
			if ( $variation ) {
				return $variation;
			}
		}
	}

	foreach ( $product->get_children() as $variation_id ) {
		$variation = wchs_upsell_validate_variation( $product, (int) $variation_id );
		if ( $variation ) {
			return $variation;
		}
	}

	return null;
}

function wchs_upsell_purchase_product( \WC_Product $product, int $variation_id = 0 ): ?\WC_Product {
	if ( ! $product->is_type( 'variable' ) ) {
		return ( $product->is_purchasable() && $product->is_in_stock() ) ? $product : null;
	}

	$variation = wchs_upsell_validate_variation( $product, $variation_id );
	if ( $variation ) {
		return $variation;
	}

	return wchs_upsell_default_variation( $product );
}

function wchs_upsell_variation_summary( \WC_Product_Variation $variation ): string {
	$parts = array_values(
		array_filter(
			array_map(
				static fn( $value ) => is_scalar( $value ) ? (string) $value : '',
				$variation->get_attributes()
			),
			static fn( string $value ) => '' !== $value
		)
	);

	return ! empty( $parts ) ? implode( ' / ', $parts ) : $variation->get_name();
}

function wchs_upsell_normalize_attributes( array $attributes ): array {
	$normalized = [];
	foreach ( $attributes as $name => $value ) {
		$key = strtolower( sanitize_title( (string) $name ) );
		if ( '' === $key ) {
			continue;
		}
		$normalized[ $key ] = is_scalar( $value ) ? (string) $value : '';
	}
	return $normalized;
}

function wchs_upsell_offer_context( \WC_Product $product ): array {
	$selected_product = wchs_upsell_purchase_product( $product );
	$selected_summary = '';
	$variation_fields = [];
	$variation_rows   = [];

	if ( $selected_product instanceof \WC_Product_Variation ) {
		$selected_summary = wchs_upsell_variation_summary( $selected_product );
	}

	if ( $product->is_type( 'variable' ) ) {
		$selected_attrs = wchs_upsell_normalize_attributes(
			$selected_product instanceof \WC_Product_Variation
				? $selected_product->get_attributes()
				: $product->get_default_attributes()
		);

		foreach ( $product->get_variation_attributes() as $name => $options ) {
			$variation_fields[] = [
				'name'     => strtolower( sanitize_title( (string) $name ) ),
				'label'    => wc_attribute_label( $name, $product ),
				'options'  => array_values( array_unique( array_map( 'strval', $options ) ) ),
				'selected' => (string) ( $selected_attrs[ strtolower( sanitize_title( (string) $name ) ) ] ?? '' ),
			];
		}

		foreach ( $product->get_available_variations() as $row ) {
			if ( empty( $row['variation_id'] ) ) {
				continue;
			}
			$variation = wchs_upsell_validate_variation( $product, (int) $row['variation_id'] );
			if ( ! $variation ) {
				continue;
			}
			$variation_rows[] = [
				'variation_id' => $variation->get_id(),
				'attributes'   => wchs_upsell_normalize_attributes( $variation->get_attributes() ),
				'price_html'   => $variation->get_price_html(),
				'summary'      => wchs_upsell_variation_summary( $variation ),
			];
		}
	}

	return [
		'selected_product' => $selected_product,
		'selected_summary' => $selected_summary,
		'variation_fields' => $variation_fields,
		'variation_rows'   => $variation_rows,
	];
}

function wchs_upsell_customer_email_id( \WC_Order $order ): string {
	return match ( $order->get_status() ) {
		'on-hold'    => 'customer_on_hold_order',
		'processing' => 'customer_processing_order',
		'completed'  => 'customer_completed_order',
		default      => '',
	};
}

function wchs_upsell_offer_ttl(): int {
	return max( 60, (int) apply_filters( 'wchs_upsell_offer_ttl', 15 * MINUTE_IN_SECONDS ) );
}

function wchs_upsell_should_defer_customer_email( \WC_Order $order ): bool {
	return 'yes' === (string) $order->get_meta( '_wchs_upsell_email_deferred' )
		&& '' === (string) $order->get_meta( '_wchs_upsell_customer_email_sent_at' )
		&& 'yes' !== (string) $order->get_meta( '_wchs_upsell_email_force_send' );
}

function wchs_upsell_filter_customer_email_enabled( bool $enabled, $order ): bool {
	if ( ! $enabled || ! $order instanceof \WC_Order ) {
		return $enabled;
	}

	return wchs_upsell_should_defer_customer_email( $order ) ? false : $enabled;
}

add_filter( 'woocommerce_email_enabled_customer_on_hold_order', 'wchs_upsell_filter_customer_email_enabled', 10, 2 );
add_filter( 'woocommerce_email_enabled_customer_processing_order', 'wchs_upsell_filter_customer_email_enabled', 10, 2 );
add_filter( 'woocommerce_email_enabled_customer_completed_order', 'wchs_upsell_filter_customer_email_enabled', 10, 2 );

function wchs_upsell_unschedule_email_fallback( int $order_id ): void {
	$args = [ $order_id ];
	$hook = 'wchs_upsell_finalize_pending_order';
	while ( $timestamp = wp_next_scheduled( $hook, $args ) ) {
		wp_unschedule_event( $timestamp, $hook, $args );
	}
}

function wchs_upsell_schedule_email_fallback( \WC_Order $order ): void {
	$timestamp = time() + wchs_upsell_offer_ttl();
	$order->update_meta_data( '_wchs_upsell_offer_expires_at', gmdate( DATE_ATOM, $timestamp ) );
	if ( ! wp_next_scheduled( 'wchs_upsell_finalize_pending_order', [ $order->get_id() ] ) ) {
		wp_schedule_single_event( $timestamp, 'wchs_upsell_finalize_pending_order', [ $order->get_id() ] );
	}
}

function wchs_upsell_offer_has_expired( \WC_Order $order ): bool {
	$expires_at = (string) $order->get_meta( '_wchs_upsell_offer_expires_at' );
	if ( '' === $expires_at ) {
		return false;
	}

	$timestamp = strtotime( $expires_at );
	return false !== $timestamp && $timestamp <= time();
}

function wchs_upsell_send_customer_email( \WC_Order $order, string $context ): void {
	if ( '' !== (string) $order->get_meta( '_wchs_upsell_customer_email_sent_at' ) ) {
		return;
	}

	$email_id = wchs_upsell_customer_email_id( $order );
	if ( '' === $email_id || ! function_exists( 'WC' ) || ! WC()->mailer() ) {
		$order->delete_meta_data( '_wchs_upsell_email_deferred' );
		$order->delete_meta_data( '_wchs_upsell_email_force_send' );
		$order->save();
		return;
	}

	$order->update_meta_data( '_wchs_upsell_email_force_send', 'yes' );
	$order->delete_meta_data( '_wchs_upsell_email_deferred' );
	$order->save();

	$sent = false;
	foreach ( WC()->mailer()->get_emails() as $email ) {
		if ( ( $email->id ?? '' ) === $email_id ) {
			$email->trigger( $order->get_id(), $order );
			$sent = true;
			break;
		}
	}

	$order->delete_meta_data( '_wchs_upsell_email_force_send' );
	if ( $sent ) {
		$order->update_meta_data( '_wchs_upsell_customer_email_sent_at', gmdate( DATE_ATOM ) );
		$order->add_order_note( 'Customer order email sent after ' . $context . '.' );
	}
	$order->save();
}

function wchs_upsell_finalize_customer_email( \WC_Order $order, string $context ): void {
	wchs_upsell_unschedule_email_fallback( $order->get_id() );
	$order->delete_meta_data( '_wchs_upsell_token' );
	$order->delete_meta_data( '_wchs_upsell_offer_expires_at' );
	$order->save();
	wchs_upsell_send_customer_email( $order, $context );
}

function wchs_upsell_release_deferred_email( \WC_Order $order, string $context ): void {
	if ( ! wchs_upsell_should_defer_customer_email( $order ) ) {
		return;
	}

	$order->delete_meta_data( '_wchs_upsell_offer_expires_at' );
	$order->save();
	wchs_upsell_send_customer_email( $order, $context );
}

add_action(
	'wchs_upsell_finalize_pending_order',
	function ( int $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		if ( 'pending' === (string) $order->get_meta( '_wchs_upsell_status' ) ) {
			$order->update_meta_data( '_wchs_upsell_status', 'expired' );
			$order->add_order_note( 'Upsell offer expired before the customer responded.' );
			$order->save();
		}

		wchs_upsell_finalize_customer_email( $order, 'upsell expiry' );
	},
	10,
	1
);

add_action(
	'woocommerce_checkout_create_order',
	function ( \WC_Order $order ) {
		$config = wchs_upsell_config();
		if ( ! $config['enabled'] ) {
			return;
		}
		if ( ! wchs_upsell_supports_payment_method( $order->get_payment_method() ) ) {
			return;
		}
		if ( ! wchs_resolve_upsell_product( $order ) ) {
			return;
		}

		$order->update_meta_data( '_wchs_upsell_email_deferred', 'yes' );
	},
	20
);

// ─── Smart product resolution ──────────────────────────────────
// Waterfall: per-product WC upsells > category best-seller > global fallback.
// Returns null if no suitable upsell can be found.

function wchs_resolve_upsell_product( \WC_Order $order ): ?\WC_Product {
	// Collect product IDs already in the order, sorted by line total
	$order_product_ids   = [];
	$order_items_by_total = [];
	foreach ( $order->get_items() as $item ) {
		$pid = (int) $item->get_product_id();
		$order_product_ids[]   = $pid;
		$order_items_by_total[] = [
			'product_id' => $pid,
			'total'      => (float) $item->get_total(),
		];
	}
	usort( $order_items_by_total, fn( $a, $b ) => $b['total'] <=> $a['total'] );

	// 1. Per-product WC native upsells (highest-spend item first)
	foreach ( $order_items_by_total as $entry ) {
		$p = wc_get_product( $entry['product_id'] );
		if ( ! $p ) continue;
		foreach ( $p->get_upsell_ids() as $uid ) {
			if ( in_array( (int) $uid, $order_product_ids, true ) ) continue;
			$candidate = wc_get_product( $uid );
			if ( ! $candidate ) continue;
			if ( ! $candidate->is_purchasable() || ! $candidate->is_in_stock() ) continue;
			return $candidate;
		}
	}

	// 2. Category-based fallback: best-seller in same categories
	$cat_ids = [];
	foreach ( $order_items_by_total as $entry ) {
		$terms = wp_get_post_terms( $entry['product_id'], 'product_cat', [ 'fields' => 'ids' ] );
		if ( is_array( $terms ) ) {
			$cat_ids = array_merge( $cat_ids, $terms );
		}
	}
	$cat_ids = array_unique( $cat_ids );
	if ( ! empty( $cat_ids ) ) {
		$results = wc_get_products( [
			'status'       => 'publish',
			'limit'        => 1,
			'orderby'      => 'meta_value_num',
			'meta_key'     => 'total_sales',
			'order'        => 'DESC',
			'exclude'      => $order_product_ids,
			'category'     => $cat_ids,
			'stock_status' => 'instock',
		] );
		if ( ! empty( $results ) && $results[0]->is_purchasable() ) {
			return $results[0];
		}
	}

	return null;
}

// ─── Force Stripe to save the payment method for future charges ─
// WC Stripe only sets setup_future_usage when the customer checks
// "save card". We force it for ALL Stripe checkouts when upsell is
// enabled, so the PM is attached to the customer and reusable.

add_filter( 'wc_stripe_force_save_source', function ( $force ) {
	$config = wchs_upsell_config();
	return $config['enabled'] ? true : $force;
} );

// Also ensure saved_cards is treated as enabled for PaymentIntent creation
add_filter( 'wc_stripe_save_payment_method_to_store', function ( $save ) {
	$config = wchs_upsell_config();
	return $config['enabled'] ? true : $save;
} );

// ─── Intercept post-checkout redirect ───────────────────────────

add_filter( 'woocommerce_get_checkout_order_received_url', 'wchs_upsell_maybe_redirect', 999, 2 );

function wchs_upsell_maybe_redirect( string $url, \WC_Order $order ): string {
	$config = wchs_upsell_config();
	if ( ! $config['enabled'] ) {
		return $url;
	}

	$payment_method = $order->get_payment_method();
	$is_stripe   = 'stripe' === $payment_method;
	$is_offline  = wchs_is_offline_gateway( $payment_method );
	$is_deferred = in_array( $payment_method, [ 'cod', 'bacs', 'cheque' ], true );

	// Intercept Stripe (re-charge), offline gateways (add to balance),
	// and deferred-payment methods like COD/BACS/cheque (add to balance).
	if ( ! $is_stripe && ! $is_offline && ! $is_deferred ) {
		return $url;
	}

	// Don't intercept if upsell already processed (accepted/declined/failed)
	$upsell_status = $order->get_meta( '_wchs_upsell_status' );
	if ( $upsell_status && 'pending' !== $upsell_status ) {
		return $url;
	}

	// For Stripe: verify we have the saved payment method
	if ( $is_stripe ) {
		$customer_id = $order->get_meta( '_stripe_customer_id' );
		$source_id   = $order->get_meta( '_stripe_source_id' );
		if ( ! $customer_id || ! $source_id ) {
			wchs_upsell_release_deferred_email( $order, 'upsell skip (missing saved payment method)' );
			return $url;
		}
	}

	// Resolve the best upsell product for this order.
	// Waterfall: per-product WC upsells > category best-seller > global fallback.
	// Returns null if all candidates are already in the order, out of stock, etc.
	$product = wchs_resolve_upsell_product( $order );
	if ( ! $product ) {
		wchs_upsell_release_deferred_email( $order, 'upsell skip (no eligible offer)' );
		return $url;
	}

	$token = wp_generate_password( 32, false );
	$order->update_meta_data( '_wchs_upsell_token', $token );
	$order->update_meta_data( '_wchs_upsell_status', 'pending' );
	$order->update_meta_data( '_wchs_upsell_product_id', $product->get_id() );
	$order->update_meta_data( '_wchs_upsell_offered_price', $product->get_price() );
	wchs_upsell_schedule_email_fallback( $order );
	$order->save();

	// Build the offer page URL
	$offer_url = add_query_arg( [
		'wchs_upsell' => '1',
		'order_id'    => $order->get_id(),
		'order_key'   => $order->get_order_key(),
		'token'       => $token,
	], home_url( '/checkout/order-received/' . $order->get_id() . '/' ) );

	return $offer_url;
}

// ─── Render offer page ──────────────────────────────────────────

add_action( 'template_redirect', 'wchs_upsell_maybe_render_offer', 5 );

function wchs_upsell_maybe_render_offer(): void {
	if ( ! isset( $_GET['wchs_upsell'] ) ) {
		return;
	}

	$order_id  = (int) ( $_GET['order_id'] ?? 0 );
	$order_key = sanitize_text_field( $_GET['order_key'] ?? '' );
	$token     = sanitize_text_field( $_GET['token'] ?? '' );

	$order = wc_get_order( $order_id );
	if ( ! $order || ! hash_equals( (string) $order->get_order_key(), $order_key ) ) {
		return;
	}

	$saved_token = $order->get_meta( '_wchs_upsell_token' );
	if ( ! $saved_token || ! hash_equals( $saved_token, $token ) ) {
		return;
	}

	// Already processed
	if ( 'pending' !== $order->get_meta( '_wchs_upsell_status' ) ) {
		wp_safe_redirect( $order->get_checkout_order_received_url() );
		exit;
	}
	if ( wchs_upsell_offer_has_expired( $order ) ) {
		$order->update_meta_data( '_wchs_upsell_status', 'expired' );
		$order->add_order_note( 'Upsell offer expired before the customer responded.' );
		$order->save();
		wchs_upsell_finalize_customer_email( $order, 'upsell expiry' );
		wp_safe_redirect( $order->get_checkout_order_received_url() );
		exit;
	}

	// Read the resolved product ID from order meta (set during redirect)
	$upsell_pid = (int) $order->get_meta( '_wchs_upsell_product_id' );
	$product    = $upsell_pid ? wc_get_product( $upsell_pid ) : null;
	if ( ! $product ) {
		return;
	}
	$offer = wchs_upsell_offer_context( $product );
	if ( ! $offer['selected_product'] ) {
		return;
	}

	$selected_product = $offer['selected_product'];

	// Build accept/decline URLs — must point to /checkout/order-received/
	// which is in the native paths allowlist. home_url('/') gets
	// redirected to the SPA by the headless-shim template_redirect.
	$base_args = [
		'order_id'  => $order_id,
		'order_key' => $order_key,
		'token'     => $token,
	];
	$base = add_query_arg( $base_args, home_url( '/checkout/order-received/' . $order_id . '/' ) );

	$decline_url = add_query_arg( 'wchs_upsell_decline', '1', $base );

	// Render the offer page using the theme
	get_header();
	?>
	<main class="wchs-shell wchs-offer">
		<article class="wchs-offer__content">
			<section class="wchs-offer__hero">
				<?php
				$image_id = $selected_product->get_image_id();
				if ( ! $image_id ) {
					$image_id = $product->get_image_id();
				}
				if ( $image_id ) :
					?>
					<div class="wchs-offer__image"><?php echo wp_get_attachment_image( $image_id, 'medium_large' ); ?></div>
				<?php endif; ?>
				<div class="wchs-offer__details">
					<p class="wchs-offer__eyebrow">Exclusive offer</p>
					<h1 class="wchs-offer__title"><?php echo esc_html( $product->get_name() ); ?></h1>
					<div class="wchs-offer__price" data-wchs-upsell-price><?php echo wp_kses_post( $selected_product->get_price_html() ); ?></div>
					<?php if ( ! empty( $offer['selected_summary'] ) ) : ?>
						<p class="wchs-offer__selection" data-wchs-upsell-selection><?php echo esc_html( 'Selected: ' . $offer['selected_summary'] ); ?></p>
					<?php endif; ?>
					<?php if ( $product->get_short_description() ) : ?>
						<div class="wchs-offer__desc"><?php echo wp_kses_post( $product->get_short_description() ); ?></div>
					<?php endif; ?>
					<?php if ( ! empty( $offer['variation_fields'] ) ) : ?>
						<div class="wchs-offer__variations">
							<?php foreach ( $offer['variation_fields'] as $field ) : ?>
								<label class="wchs-offer__variation-field">
									<span class="wchs-offer__variation-label"><?php echo esc_html( $field['label'] ); ?></span>
									<select class="wchs-offer__variation-select" data-wchs-upsell-attr="<?php echo esc_attr( $field['name'] ); ?>">
										<?php foreach ( $field['options'] as $option ) : ?>
											<option value="<?php echo esc_attr( $option ); ?>" <?php selected( $field['selected'], $option ); ?>>
												<?php echo esc_html( $option ); ?>
											</option>
										<?php endforeach; ?>
									</select>
								</label>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>
			</section>

			<section class="wchs-offer__cta">
				<form method="get" class="wchs-offer__accept-form">
					<input type="hidden" name="wchs_upsell_accept" value="1" />
					<input type="hidden" name="order_id" value="<?php echo esc_attr( (string) $order_id ); ?>" />
					<input type="hidden" name="order_key" value="<?php echo esc_attr( $order_key ); ?>" />
					<input type="hidden" name="token" value="<?php echo esc_attr( $token ); ?>" />
					<input type="hidden" name="wchs_upsell_variation_id" value="<?php echo esc_attr( (string) ( $selected_product instanceof \WC_Product_Variation ? $selected_product->get_id() : 0 ) ); ?>" data-wchs-upsell-variation-id />
					<button type="submit" class="wchs-offer__accept">Yes, add this to my order</button>
				</form>
				<div class="wchs-offer__decline">
					<a href="<?php echo esc_url( $decline_url ); ?>" class="wchs-offer__skip">No thanks, continue to my order</a>
				</div>
			</section>
		</article>
	</main>
	<?php if ( ! empty( $offer['variation_rows'] ) ) : ?>
		<script>
		(() => {
			const variations = <?php echo wp_json_encode( $offer['variation_rows'], JSON_UNESCAPED_SLASHES ); ?>;
			const selects = Array.from(document.querySelectorAll('[data-wchs-upsell-attr]'));
			const hidden = document.querySelector('[data-wchs-upsell-variation-id]');
			const priceEl = document.querySelector('[data-wchs-upsell-price]');
			const selectionEl = document.querySelector('[data-wchs-upsell-selection]');
			const button = document.querySelector('.wchs-offer__accept');
			if (!selects.length || !hidden || !priceEl || !button) return;

			const formatSummary = (summary) => summary ? `Selected: ${summary}` : '';

			function currentSelection() {
				const chosen = {};
				for (const select of selects) chosen[select.dataset.wchsUpsellAttr] = select.value;
				return chosen;
			}

			function findMatch(chosen) {
				return variations.find((variation) => Object.entries(chosen).every(([name, value]) => variation.attributes?.[name] === value));
			}

			function update() {
				const chosen = currentSelection();
				const match = findMatch(chosen);
				if (!match) {
					hidden.value = '';
					button.disabled = true;
					if (selectionEl) selectionEl.textContent = 'Choose a valid variation to continue.';
					return;
				}
				hidden.value = String(match.variation_id);
				priceEl.innerHTML = match.price_html;
				button.disabled = false;
				if (selectionEl) selectionEl.textContent = formatSummary(match.summary);
			}

			selects.forEach((select) => select.addEventListener('change', update));
			update();
		})();
		</script>
	<?php endif; ?>
	<?php
	get_footer();
	exit;
}

// ─── Process accept ─────────────────────────────────────────────

// Priority 3 — must run BEFORE the order-redirect mu-plugin (priority 20)
// and before the offer renderer (priority 5)
add_action( 'template_redirect', 'wchs_upsell_process_action', 3 );

function wchs_upsell_process_action(): void {
	$is_accept  = isset( $_GET['wchs_upsell_accept'] );
	$is_decline = isset( $_GET['wchs_upsell_decline'] );

	if ( ! $is_accept && ! $is_decline ) {
		return;
	}

	$order_id  = (int) ( $_GET['order_id'] ?? 0 );
	$order_key = sanitize_text_field( $_GET['order_key'] ?? '' );
	$token     = sanitize_text_field( $_GET['token'] ?? '' );

	$order = wc_get_order( $order_id );
	if ( ! $order || ! hash_equals( (string) $order->get_order_key(), $order_key ) ) {
		wp_safe_redirect( home_url() );
		exit;
	}

	$saved_token = $order->get_meta( '_wchs_upsell_token' );
	if ( ! $saved_token || ! hash_equals( $saved_token, $token ) ) {
		wp_safe_redirect( home_url() );
		exit;
	}

	// Idempotency: only process once
	if ( 'pending' !== $order->get_meta( '_wchs_upsell_status' ) ) {
		wp_safe_redirect( $order->get_checkout_order_received_url() );
		exit;
	}
	if ( wchs_upsell_offer_has_expired( $order ) ) {
		$order->update_meta_data( '_wchs_upsell_status', 'expired' );
		$order->add_order_note( 'Upsell offer expired before the customer responded.' );
		$order->save();
		wchs_upsell_finalize_customer_email( $order, 'upsell expiry' );
		wp_safe_redirect( $order->get_checkout_order_received_url() );
		exit;
	}

	if ( $is_decline ) {
		$order->update_meta_data( '_wchs_upsell_status', 'declined' );
		$order->save();
		wchs_upsell_finalize_customer_email( $order, 'upsell decline' );
		wp_safe_redirect( $order->get_checkout_order_received_url() );
		exit;
	}

	// ── Accept: add upsell product to order ──

	// Acquire a database-level mutex to prevent race conditions.
	// Only one request can hold this lock at a time. Others will
	// fail the INSERT and bail.
	global $wpdb;
	$lock_key = '_transient_wchs_upsell_lock_' . $order_id;

	// Auto-release lock on exit (covers all failure paths)
	register_shutdown_function( function () use ( $wpdb, $lock_key ) {
		$wpdb->delete( $wpdb->options, [ 'option_name' => $lock_key ] );
	} );
	$locked = $wpdb->query( $wpdb->prepare(
		"INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
		$lock_key,
		time()
	) );

	if ( ! $locked ) {
		// Another request already holds the lock
		wp_safe_redirect( $order->get_checkout_order_received_url() );
		exit;
	}

	// Re-read to confirm status is still pending (belt + suspenders)
	$fresh = wc_get_order( $order_id );
	if ( 'pending' !== $fresh->get_meta( '_wchs_upsell_status' ) ) {
		$wpdb->delete( $wpdb->options, [ 'option_name' => $lock_key ] );
		wp_safe_redirect( $order->get_checkout_order_received_url() );
		exit;
	}

	// Validate the order hasn't been cancelled/refunded
	if ( ! $order->has_status( [ 'processing', 'completed', 'on-hold' ] ) ) {
		$order->update_meta_data( '_wchs_upsell_status', 'failed' );
		$order->add_order_note( 'Upsell rejected: order status is ' . $order->get_status() );
		$order->save();
		wchs_upsell_finalize_customer_email( $order, 'upsell failure' );
		wp_safe_redirect( $order->get_checkout_order_received_url() );
		exit;
	}

	// Validate payment method is one we support
	$pm          = $order->get_payment_method();
	$is_offline  = wchs_is_offline_gateway( $pm );
	$is_deferred = in_array( $pm, [ 'cod', 'bacs', 'cheque' ], true );
	if ( 'stripe' !== $pm && ! $is_offline && ! $is_deferred ) {
		$order->update_meta_data( '_wchs_upsell_status', 'failed' );
		$order->add_order_note( 'Upsell failed: unsupported payment method ' . $pm );
		$order->save();
		wchs_upsell_finalize_customer_email( $order, 'upsell failure' );
		wp_safe_redirect( $order->get_checkout_order_received_url() );
		exit;
	}

	// Read the resolved product ID from order meta (set during redirect)
	$upsell_pid = (int) $order->get_meta( '_wchs_upsell_product_id' );
	$product    = $upsell_pid ? wc_get_product( $upsell_pid ) : null;

	if ( ! $product ) {
		$order->update_meta_data( '_wchs_upsell_status', 'failed' );
		$order->add_order_note( 'Upsell failed: product not found.' );
		$order->save();
		wchs_upsell_finalize_customer_email( $order, 'upsell failure' );
		wp_safe_redirect( $order->get_checkout_order_received_url() );
		exit;
	}

	$variation_id     = absint( $_REQUEST['wchs_upsell_variation_id'] ?? 0 );
	$purchase_product = wchs_upsell_purchase_product( $product, $variation_id );
	if ( ! $purchase_product ) {
		$order->update_meta_data( '_wchs_upsell_status', 'failed' );
		$order->add_order_note( 'Upsell failed: selected product variation is unavailable.' );
		$order->save();
		wchs_upsell_finalize_customer_email( $order, 'upsell failure' );
		wp_safe_redirect( $order->get_checkout_order_received_url() );
		exit;
	}

	// Simple products keep the locked offer price from the redirect step.
	// Variable products derive the amount from the selected variation.
	$offered_price = (float) $order->get_meta( '_wchs_upsell_offered_price' );
	$charge_price  = $purchase_product->is_type( 'variation' )
		? (float) $purchase_product->get_price()
		: ( $offered_price > 0 ? $offered_price : (float) $purchase_product->get_price() );

	// ── Stripe path: charge the saved payment method ──
	if ( ! $is_offline && ! $is_deferred ) {
		$customer       = $order->get_meta( '_stripe_customer_id' );
		$payment_method = $order->get_meta( '_stripe_source_id' );

		if ( ! $customer || ! $payment_method ) {
			$order->update_meta_data( '_wchs_upsell_status', 'failed' );
			$order->add_order_note( 'Upsell failed: missing Stripe payment data.' );
			$order->save();
			wchs_upsell_finalize_customer_email( $order, 'upsell failure' );
			wp_safe_redirect( $order->get_checkout_order_received_url() );
			exit;
		}

		$amount   = (int) round( $charge_price * 100 );
		$currency = strtolower( get_woocommerce_currency() );

		// Attach PM to customer (ignore "already attached")
		$attach = WC_Stripe_API::request(
			[ 'customer' => $customer ],
			"payment_methods/{$payment_method}/attach"
		);
		if ( is_wp_error( $attach ) ) {
			$order->update_meta_data( '_wchs_upsell_status', 'failed' );
			$order->add_order_note( 'Upsell failed: could not attach PM — ' . $attach->get_error_message() );
			$order->save();
			wchs_upsell_finalize_customer_email( $order, 'upsell failure' );
			wp_safe_redirect( $order->get_checkout_order_received_url() );
			exit;
		}
		if ( isset( $attach->error ) && 'resource_already_attached' !== ( $attach->error->code ?? '' ) ) {
			$order->update_meta_data( '_wchs_upsell_status', 'failed' );
			$order->add_order_note( 'Upsell failed: Stripe attach error — ' . ( $attach->error->message ?? 'unknown' ) );
			$order->save();
			wchs_upsell_finalize_customer_email( $order, 'upsell failure' );
			wp_safe_redirect( $order->get_checkout_order_received_url() );
			exit;
		}

		$response = WC_Stripe_API::request( [
			'amount'               => $amount,
			'currency'             => $currency,
			'customer'             => $customer,
			'payment_method'       => $payment_method,
			'off_session'          => 'true',
			'confirm'              => 'true',
			'description'          => sprintf(
				'Upsell for Order #%d: %s',
				$order->get_id(),
				$purchase_product->get_name()
			),
			'metadata'             => [
				'order_id'         => $order->get_id(),
				'upsell_product'   => $purchase_product->get_id(),
				'site_url'         => get_site_url(),
				'idempotency_ref'  => 'upsell_' . $order->get_id(),
			],
		], 'payment_intents' );

		if ( is_wp_error( $response ) ) {
			$order->update_meta_data( '_wchs_upsell_status', 'failed' );
			$order->add_order_note( 'Upsell charge failed: ' . $response->get_error_message() );
			$order->save();
			wchs_upsell_finalize_customer_email( $order, 'upsell failure' );
			wp_safe_redirect( $order->get_checkout_order_received_url() );
			exit;
		}

		if ( isset( $response->error ) ) {
			$order->update_meta_data( '_wchs_upsell_status', 'failed' );
			$order->add_order_note( 'Upsell charge failed: ' . ( $response->error->message ?? 'Stripe error' ) );
			$order->save();
			wchs_upsell_finalize_customer_email( $order, 'upsell failure' );
			wp_safe_redirect( $order->get_checkout_order_received_url() );
			exit;
		}

		if ( ! isset( $response->status ) || 'succeeded' !== $response->status ) {
			$order->update_meta_data( '_wchs_upsell_status', 'failed' );
			$order->add_order_note( 'Upsell charge failed: intent status = ' . ( $response->status ?? 'unknown' ) );
			$order->save();
			wchs_upsell_finalize_customer_email( $order, 'upsell failure' );
			wp_safe_redirect( $order->get_checkout_order_received_url() );
			exit;
		}
	}

	// ── Add product to order (both Stripe and offline) ──

	$order->add_product(
		$purchase_product,
		1,
		[
			'subtotal' => $charge_price,
			'total'    => $charge_price,
		]
	);
	if ( function_exists( 'wchs_sync_offline_gateway_order_fee' ) ) {
		wchs_sync_offline_gateway_order_fee( $order );
	}
	$order->calculate_totals();

	$order->update_meta_data( '_wchs_upsell_status', 'accepted' );
	$order->update_meta_data( '_wchs_upsell_offered_price', $charge_price );
	$order->update_meta_data( '_wchs_upsell_amount', $charge_price );
	$order->update_meta_data( '_wchs_upsell_variation_id', $purchase_product->is_type( 'variation' ) ? $purchase_product->get_id() : 0 );

	if ( $is_offline || $is_deferred ) {
		$label = $is_deferred ? $pm : 'offline';
		$order->add_order_note( sprintf(
			'Upsell accepted (%s): %s ($%s) added to order total.',
			$label,
			$purchase_product->get_name(),
			number_format( $charge_price, 2 )
		) );
	} else {
		$order->update_meta_data( '_wchs_upsell_intent_id', $response->id );
		$order->add_order_note( sprintf(
			'Upsell accepted: %s ($%s) — Stripe PaymentIntent %s',
			$purchase_product->get_name(),
			number_format( $charge_price, 2 ),
			$response->id
		) );
	}
	$order->save();
	wchs_upsell_finalize_customer_email( $order, 'upsell acceptance' );

	wp_safe_redirect( $order->get_checkout_order_received_url() );
	exit;
}
