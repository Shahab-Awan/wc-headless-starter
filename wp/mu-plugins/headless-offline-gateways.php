<?php
/**
 * Plugin Name: Headless Offline Gateways
 * Description: Dynamically registers WooCommerce payment gateways for offline/manual
 *              payment methods (CashApp, Venmo, PayPal.me, Bitcoin, Zelle, custom).
 *              Each gateway sets the order to on-hold and shows payment instructions
 *              (handle, payment link, optional QR code) on the thank-you page and
 *              in the order confirmation email.
 *
 *
 * Author:      WCHS Contributors
 *
 * Config:      WCHS admin → Checkout tab → Offline Payment Methods.
 *              Stored in `wchs_offline_gateways` option.
 */

defined( 'ABSPATH' ) || exit;

// ─── Presets ────────────────────────────────────────────────────

function wchs_offline_gateway_presets(): array {
	return [
		'cashapp' => [
			'title'         => 'CashApp',
			'description'   => 'Pay via CashApp. You\'ll see the payment details after placing your order.',
			'instructions'  => 'Please send payment to the CashApp handle below. Use your Order ID as the note. Your order will be processed once payment is confirmed.',
			'handle'        => '',
			'link_template' => 'https://cash.app/{handle}/{amount}',
			'show_qr'       => true,
			'enabled'       => true,
		],
		'venmo' => [
			'title'         => 'Venmo',
			'description'   => 'Pay via Venmo. You\'ll see the payment details after placing your order.',
			'instructions'  => 'Please send payment to the Venmo account below. Include your Order ID in the note. Your order will be processed once payment is confirmed.',
			'handle'        => '',
			'link_template' => 'https://venmo.com/{handle}?txn=pay&amount={amount}&note=Order+{order_id}',
			'show_qr'       => true,
			'enabled'       => true,
		],
		'paypalme' => [
			'title'         => 'PayPal.me',
			'description'   => 'Pay via PayPal. You\'ll see the payment details after placing your order.',
			'instructions'  => 'Please send payment using the PayPal.me link below. Your order will be processed once payment is confirmed.',
			'handle'        => '',
			'link_template' => 'https://paypal.me/{handle}/{amount}',
			'show_qr'       => true,
			'enabled'       => true,
		],
		'zelle' => [
			'title'         => 'Zelle',
			'description'   => 'Pay via Zelle through your banking app. You\'ll see the payment details after placing your order.',
			'instructions'  => 'Please send payment via Zelle to the account below using your banking app. Include your Order ID as the memo. Your order will be processed once payment is confirmed.',
			'handle'        => '',
			'link_template' => '',
			'show_qr'       => false,
			'enabled'       => true,
		],
		'bitcoin' => [
			'title'         => 'Bitcoin',
			'description'   => 'Pay with Bitcoin. You\'ll see the wallet address after placing your order.',
			'instructions'  => 'Please send the exact amount in BTC to the address below. Your order will be processed once the transaction is confirmed on the blockchain.',
			'handle'        => '',
			'link_template' => 'bitcoin:{handle}?amount={amount}',
			'show_qr'       => true,
			'enabled'       => true,
		],
	];
}

// ─── Load configured gateways ───────────────────────────────────

function wchs_get_offline_gateways(): array {
	$gateways = get_option( 'wchs_offline_gateways', [] );
	return is_array( $gateways ) ? $gateways : [];
}

function wchs_get_offline_gateway_config( string $payment_method ): ?array {
	if ( ! str_starts_with( $payment_method, 'wchs_offline_' ) ) {
		return null;
	}

	$gateway_id = str_replace( 'wchs_offline_', '', $payment_method );
	foreach ( wchs_get_offline_gateways() as $gateway ) {
		if ( sanitize_key( $gateway['id'] ?? '' ) === $gateway_id ) {
			return is_array( $gateway ) ? $gateway : null;
		}
	}

	return null;
}

function wchs_offline_gateway_build_payment_link( string $handle, string $link_template, \WC_Order $order ): string {
	if ( '' === $link_template || '' === $handle ) {
		return '';
	}

	$normalized_handle = $handle;
	if ( str_starts_with( $normalized_handle, '$' ) && str_contains( $link_template, 'cash.app' ) ) {
		$normalized_handle = ltrim( $normalized_handle, '$' );
	}
	if ( str_starts_with( $normalized_handle, '@' ) && str_contains( $link_template, 'venmo.com' ) ) {
		$normalized_handle = ltrim( $normalized_handle, '@' );
	}

	return str_replace(
		[ '{handle}', '{amount}', '{order_id}' ],
		[ rawurlencode( $normalized_handle ), $order->get_total(), $order->get_id() ],
		$link_template
	);
}

function wchs_get_offline_gateway_order_details( \WC_Order $order ): ?array {
	$config = wchs_get_offline_gateway_config( $order->get_payment_method() );
	if ( ! $config ) {
		return null;
	}

	$instructions = (string) $order->get_meta( '_wchs_offline_instructions' );
	if ( '' === $instructions ) {
		$instructions = (string) ( $config['instructions'] ?? '' );
	}

	$handle = (string) $order->get_meta( '_wchs_offline_handle' );
	if ( '' === $handle ) {
		$handle = (string) ( $config['handle'] ?? '' );
	}

	$link_template = (string) $order->get_meta( '_wchs_offline_link_template' );
	if ( '' === $link_template ) {
		$link_template = (string) ( $config['link_template'] ?? '' );
	}

	$show_qr_meta = (string) $order->get_meta( '_wchs_offline_show_qr', true, 'edit' );
	$show_qr      = '' === $show_qr_meta ? (bool) ( $config['show_qr'] ?? false ) : '1' === $show_qr_meta;

	return [
		'title'        => (string) ( $order->get_payment_method_title() ?: ( $config['title'] ?? 'Offline Payment' ) ),
		'instructions' => $instructions,
		'handle'       => $handle,
		'link'         => wchs_offline_gateway_build_payment_link( $handle, $link_template, $order ),
		'show_qr'      => $show_qr,
	];
}

function wchs_render_offline_gateway_email_instructions( array $details, \WC_Order $order, bool $plain_text = false ): void {
	$instructions = trim( (string) ( $details['instructions'] ?? '' ) );
	$handle       = trim( (string) ( $details['handle'] ?? '' ) );
	$link         = trim( (string) ( $details['link'] ?? '' ) );
	$title        = trim( (string) ( $details['title'] ?? $order->get_payment_method_title() ) );
	$show_qr      = ! empty( $details['show_qr'] );
	$total        = $order->get_total();

	if ( $plain_text ) {
		if ( '' !== $instructions ) {
			echo wp_strip_all_tags( wptexturize( $instructions ) ) . PHP_EOL . PHP_EOL;
		}
		echo strtoupper( $title ) . ' PAYMENT INSTRUCTIONS' . PHP_EOL;
		echo str_repeat( '-', 40 ) . PHP_EOL;
		if ( '' !== $handle ) {
			echo 'Send ' . wp_strip_all_tags( wc_price( $total ) ) . ' to: ' . $handle . PHP_EOL;
		}
		if ( '' !== $link ) {
			echo 'Payment link: ' . $link . PHP_EOL;
		}
		return;
	}

	if ( '' !== $instructions ) {
		echo wp_kses_post( wpautop( wptexturize( $instructions ) ) ) . PHP_EOL;
	}

	echo '<h2 style="font-size:18px;font-weight:600;margin:16px 0 8px;">Payment Instructions</h2>' . PHP_EOL;
	if ( '' !== $handle ) {
		echo '<p style="font-size:15px;">Please send <strong>' . wp_kses_post( wc_price( $total ) ) . '</strong> to <strong>' . esc_html( $handle ) . '</strong></p>' . PHP_EOL;
	}
	if ( '' !== $link ) {
		echo '<p style="margin:12px 0;"><a href="' . esc_url( $link, [ 'http', 'https', 'bitcoin' ] )
			. '" style="display:inline-block;padding:12px 24px;background:#0c0c0c;color:#ffffff;text-decoration:none;font-size:14px;font-weight:600;">'
			. 'Pay via ' . esc_html( $title ) . '</a></p>' . PHP_EOL;
	}
	if ( $show_qr && '' !== $link ) {
		$qr_url = 'https://quickchart.io/qr?text=' . rawurlencode( $link ) . '&size=160&margin=1';
		echo '<p style="margin:12px 0;"><img src="' . esc_url( $qr_url ) . '" alt="Payment QR code" width="160" height="160" style="display:block;" /></p>' . PHP_EOL;
	}
}

function wchs_offline_gateway_email_instructions( $order, $sent_to_admin, $plain_text = false ): void {
	if ( $sent_to_admin || ! $order instanceof \WC_Order ) {
		return;
	}
	if ( ! $order->has_status( 'on-hold' ) ) {
		return;
	}

	$details = wchs_get_offline_gateway_order_details( $order );
	if ( ! $details ) {
		return;
	}

	wchs_render_offline_gateway_email_instructions( $details, $order, (bool) $plain_text );
}

add_action( 'woocommerce_email_before_order_table', 'wchs_offline_gateway_email_instructions', 10, 3 );

function wchs_offline_gateway_fee_label( array $gateway ): string {
	$title     = (string) ( $gateway['title'] ?? 'Payment' );
	$fee_type  = (string) ( $gateway['fee_type'] ?? 'none' );
	$fee_value = (float) ( $gateway['fee_value'] ?? 0 );

	return match ( $fee_type ) {
		'flat_add' => $title . ' fee',
		'flat_sub' => $title . ' discount',
		'pct_add'  => $title . ' fee (' . $fee_value . '%)',
		'pct_sub'  => $title . ' discount (' . $fee_value . '%)',
		default    => '',
	};
}

function wchs_offline_gateway_fee_amount_for_totals( float $subtotal, float $discount, float $shipping, array $gateway ): float {
	$fee_type  = (string) ( $gateway['fee_type'] ?? 'none' );
	$fee_value = (float) ( $gateway['fee_value'] ?? 0 );
	if ( 'none' === $fee_type || $fee_value <= 0 ) {
		return 0.0;
	}

	if ( 'flat_add' === $fee_type ) {
		return $fee_value;
	}
	if ( 'flat_sub' === $fee_type ) {
		return -$fee_value;
	}

	$base = max( 0.0, $subtotal - $discount + $shipping );
	$amount = round( $base * ( $fee_value / 100 ), 2 );
	if ( 'pct_sub' === $fee_type ) {
		$amount = min( $amount, $base );
		return -$amount;
	}
	if ( 'pct_add' === $fee_type ) {
		return $amount;
	}

	return 0.0;
}

function wchs_sync_offline_gateway_order_fee( \WC_Order $order ): void {
	$gateway = wchs_get_offline_gateway_config( $order->get_payment_method() );
	if ( ! $gateway ) {
		return;
	}

	$label  = wchs_offline_gateway_fee_label( $gateway );
	$amount = wchs_offline_gateway_fee_amount_for_totals(
		(float) $order->get_subtotal(),
		(float) $order->get_discount_total(),
		(float) $order->get_shipping_total(),
		$gateway
	);

	$matching_fee = null;
	foreach ( $order->get_fees() as $fee_item ) {
		if ( $fee_item->get_name() === $label ) {
			$matching_fee = $fee_item;
			break;
		}
	}

	if ( '' === $label || abs( $amount ) < 0.00001 ) {
		if ( $matching_fee ) {
			$order->remove_item( $matching_fee->get_id() );
		}
		return;
	}

	if ( ! $matching_fee ) {
		$matching_fee = new \WC_Order_Item_Fee();
		$matching_fee->set_name( $label );
		$matching_fee->set_tax_status( 'none' );
		$order->add_item( $matching_fee );
	}

	$matching_fee->set_total( $amount );
	$matching_fee->save();
}

// ─── Payment method fee/discount ──────────────────────────────

// Ensure chosen_payment_method is set in session during AJAX order review
// (fires BEFORE cart_calculate_fees so the fee hook can read it)
// WC's checkout.js sends payment_method as a top-level AJAX param, not
// inside the serialized post_data string. Read from $_POST directly.
add_action( 'woocommerce_checkout_update_order_review', function ( $posted_data ) {
	$pm = sanitize_text_field( wp_unslash( $_POST['payment_method'] ?? '' ) );
	if ( $pm ) {
		WC()->session->set( 'chosen_payment_method', $pm );
	}
} );

// WC's checkout.js does NOT trigger update_order_review when the payment
// method radio changes — only on address changes. We need to force it so
// payment-method-specific fees recalculate.
add_action( 'woocommerce_after_checkout_form', function () {
	// Only inject if we have gateways with fees
	$has_fees = false;
	foreach ( wchs_get_offline_gateways() as $gw ) {
		if ( ( $gw['fee_type'] ?? 'none' ) !== 'none' && (float) ( $gw['fee_value'] ?? 0 ) > 0 ) {
			$has_fees = true;
			break;
		}
	}
	if ( ! $has_fees ) return;
	?>
	<script>
	jQuery(function($) {
		$('form.checkout').on('change', 'input[name="payment_method"]', function() {
			$(document.body).trigger('update_checkout');
		});
	});
	</script>
	<?php
} );

add_action( 'woocommerce_cart_calculate_fees', function ( $cart ) {
	if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;

	$chosen = WC()->session->get( 'chosen_payment_method', '' );
	if ( ! $chosen || ! str_starts_with( $chosen, 'wchs_offline_' ) ) return;

	$gateway_id = str_replace( 'wchs_offline_', '', $chosen );
	$gateway    = wchs_get_offline_gateway_config( 'wchs_offline_' . $gateway_id );
	if ( ! $gateway ) return;

	$label  = wchs_offline_gateway_fee_label( $gateway );
	$amount = wchs_offline_gateway_fee_amount_for_totals(
		(float) $cart->get_subtotal(),
		(float) $cart->get_discount_total(),
		(float) $cart->get_shipping_total(),
		$gateway
	);
	if ( '' === $label || abs( $amount ) < 0.00001 ) return;

	$cart->add_fee( $label, $amount, false );
}, 20 );

// ─── Gateway class (deferred until WC loads) ───────────────────

add_action( 'plugins_loaded', function () {
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}

	class WCHS_Offline_Gateway extends WC_Payment_Gateway {

	private string $handle;
	private string $link_template;
	private bool $show_qr;
	private string $payment_instructions;

	public function __construct( array $config ) {
		$this->id                 = 'wchs_offline_' . sanitize_key( $config['id'] );
		$this->method_title       = $config['title'] ?? 'Offline Payment';
		$this->method_description = 'Custom offline payment method managed by WCHS.';
		$this->has_fields         = false;
		$this->enabled            = ( $config['enabled'] ?? false ) ? 'yes' : 'no';

		$this->title                = $config['title'] ?? 'Offline Payment';
		$this->description          = $config['description'] ?? '';
		$this->payment_instructions = $config['instructions'] ?? '';
		$this->handle               = $config['handle'] ?? '';
		$this->link_template        = $config['link_template'] ?? '';
		$this->show_qr              = (bool) ( $config['show_qr'] ?? false );

		// Append fee/discount notice to title (always visible next to radio)
		// and description (visible after selection)
		$fee_type  = $config['fee_type'] ?? 'none';
		$fee_value = (float) ( $config['fee_value'] ?? 0 );
		if ( 'none' !== $fee_type && $fee_value > 0 ) {
			$notice = '';
			switch ( $fee_type ) {
				case 'flat_add':
					$notice = '+ ' . strip_tags( wc_price( $fee_value ) ) . ' fee';
					break;
				case 'flat_sub':
					$notice = strip_tags( wc_price( $fee_value ) ) . ' off';
					break;
				case 'pct_add':
					$notice = '+ ' . $fee_value . '% fee';
					break;
				case 'pct_sub':
					$notice = $fee_value . '% off';
					break;
			}
			if ( $notice ) {
				$this->title = $this->title . ' (' . $notice . ')';
			}
		}

		add_action( 'woocommerce_thankyou_' . $this->id, [ $this, 'thankyou_page' ] );
	}

	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( $order->get_total() > 0 ) {
			$order->update_status( 'on-hold', sprintf(
				'Awaiting %s payment.',
				$this->title
			) );
		} else {
			$order->payment_complete();
		}

		$order->update_meta_data( '_wchs_offline_handle', $this->handle );
		$order->update_meta_data( '_wchs_offline_link_template', $this->link_template );
		$order->update_meta_data( '_wchs_offline_show_qr', $this->show_qr ? '1' : '0' );
		$order->update_meta_data( '_wchs_offline_instructions', $this->payment_instructions );
		$order->save();

		// Save as preferred offline gateway for instant checkout
		$uid = $order->get_user_id();
		if ( $uid ) {
			update_user_meta( $uid, '_wchs_preferred_offline_gateway', $this->id );
		}

		WC()->cart->empty_cart();

		return [
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		];
	}

	public function thankyou_page( $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$details = wchs_get_offline_gateway_order_details( $order );
		if ( ! $details ) {
			return;
		}

		$instructions = (string) ( $details['instructions'] ?? '' );
		$handle       = (string) ( $details['handle'] ?? '' );
		$link         = (string) ( $details['link'] ?? '' );
		$show_qr      = ! empty( $details['show_qr'] );
		$title        = (string) ( $details['title'] ?? $this->title );

		echo '<div class="wchs-offline-payment">';
		echo '<div class="wchs-offline-payment__body">';

		echo '<div class="wchs-offline-payment__info">';

		if ( '' !== $instructions ) {
			echo '<div class="wchs-offline-payment__instructions">' . wp_kses_post( wpautop( wptexturize( $instructions ) ) ) . '</div>';
		}

		if ( $handle ) {
			echo '<div class="wchs-offline-payment__handle">';
			echo '<span class="wchs-offline-payment__label">Send to</span>';
			echo '<span class="wchs-offline-payment__value" id="wchs-handle-value">' . esc_html( $handle ) . '</span>';
			echo '<button type="button" class="wchs-offline-payment__copy" onclick="navigator.clipboard.writeText(\'' . esc_js( $handle ) . '\').then(function(){var b=event.target;b.textContent=\'Copied!\';setTimeout(function(){b.textContent=\'Copy\'},2000)})" aria-label="Copy handle">Copy</button>';
			echo '</div>';
		}

		if ( $link ) {
			$safe_link = esc_url( $link, [ 'http', 'https', 'bitcoin' ] );
			echo '<div class="wchs-offline-payment__link">';
			echo '<a href="' . $safe_link . '" target="_blank" rel="noopener noreferrer" class="wchs-offline-payment__pay-link">Open ' . esc_html( $title ) . ' →</a>';
			echo '</div>';
		}

		echo '</div>'; // __info

		if ( $show_qr && $link ) {
			echo '<div class="wchs-offline-payment__qr" id="wchs-qr-target">';
			echo '<span class="wchs-offline-payment__qr-label">Scan to pay</span>';
			echo '</div>';
			$this->render_qr_script( $link );
		}

		echo '</div>'; // __body
		echo '</div>';
	}

	public function email_instructions( $order, $sent_to_admin, $plain_text = false ): void {
		wchs_offline_gateway_email_instructions( $order, $sent_to_admin, $plain_text );
	}

	private function build_payment_link( WC_Order $order ): string {
		return wchs_offline_gateway_build_payment_link( $this->handle, $this->link_template, $order );
	}

	private function render_qr_script( string $data ): void {
		?>
		<script>
		(function(){
			var d=<?php echo wp_json_encode( $data ); ?>;
			var t=document.getElementById('wchs-qr-target');
			if(!t)return;
			// QR via quickchart.io (free, no auth, no deprecated API)
			var img=new Image();
			img.style.cssText='display:block';
			img.width=140;img.height=140;img.alt='QR code';
			img.onerror=function(){
				var label=t.querySelector('.wchs-offline-payment__qr-label');
				if(label)label.remove();
			};
			img.src='https://quickchart.io/qr?text='+encodeURIComponent(d)+'&size=280&margin=0';
			var label=t.querySelector('.wchs-offline-payment__qr-label');
			if(label)t.insertBefore(img,label);
			else t.appendChild(img);
		})();
		</script>
		<?php
	}
}

// ─── Register gateways with WooCommerce ─────────────────────────

add_filter( 'woocommerce_payment_gateways', function ( array $gateways ): array {
	$offline = wchs_get_offline_gateways();
	foreach ( $offline as $config ) {
		if ( empty( $config['id'] ) ) {
			continue;
		}
		$gateways[] = new WCHS_Offline_Gateway( $config );
	}
	return $gateways;
} );

} ); // end plugins_loaded

// ─── CSS for the thank-you page payment block ───────────────────

add_action( 'wp_head', function () {
	if ( ! is_wc_endpoint_url( 'order-received' ) ) {
		return;
	}
	?>
	<style>
	.wchs-offline-payment {
		background: var(--bg-elevated, #f9fafb);
		border: 1px solid var(--border, #e5e7eb);
		padding: 24px;
		margin: 20px 0;
	}
	.wchs-offline-payment__body {
		display: flex;
		align-items: flex-start;
		gap: 24px;
	}
	.wchs-offline-payment__info {
		flex: 1;
		min-width: 0;
	}
	.wchs-offline-payment__instructions {
		margin: 0 0 16px;
		font-size: 13px;
		line-height: 1.5;
		color: var(--fg-muted, #6b7280);
	}
	.wchs-offline-payment__instructions p { margin: 0; }
	.wchs-offline-payment__handle {
		display: flex;
		align-items: baseline;
		gap: 10px;
		flex-wrap: wrap;
		margin-bottom: 16px;
	}
	.wchs-offline-payment__label {
		font-size: 11px;
		text-transform: uppercase;
		letter-spacing: 0.08em;
		color: var(--fg-muted, #6b7280);
		font-weight: 500;
	}
	.wchs-offline-payment__value {
		font-family: var(--font-mono, monospace);
		font-size: 18px;
		font-weight: 600;
		color: var(--fg, #1a1a1a);
		user-select: all;
		letter-spacing: -0.01em;
	}
	.wchs-offline-payment__copy {
		padding: 3px 10px;
		border: 1px solid var(--border, #e5e7eb);
		background: var(--bg, #fff);
		color: var(--fg-muted, #6b7280);
		font-size: 10px;
		font-weight: 600;
		text-transform: uppercase;
		letter-spacing: 0.06em;
		cursor: pointer;
		font-family: var(--font-sans, sans-serif);
		vertical-align: middle;
	}
	.wchs-offline-payment__copy:hover {
		border-color: var(--fg, #1a1a1a);
		color: var(--fg, #1a1a1a);
	}
	.wchs-offline-payment__link { margin: 0; }
	.wchs-offline-payment a.wchs-offline-payment__pay-link,
	.wchs-offline-payment a.wchs-offline-payment__pay-link:hover {
		display: inline-block;
		padding: 10px 20px;
		background: var(--fg, #1a1a1a);
		color: var(--bg, #fff);
		text-decoration: none;
		font-size: 11px;
		font-weight: 600;
		text-transform: uppercase;
		letter-spacing: 0.08em;
		font-family: var(--font-sans, sans-serif);
	}
	.wchs-offline-payment a.wchs-offline-payment__pay-link:hover {
		opacity: 0.85;
	}
	.wchs-offline-payment__qr {
		flex-shrink: 0;
		display: flex;
		flex-direction: column;
		align-items: center;
		gap: 6px;
	}
	.wchs-offline-payment__qr img {
		border: 6px solid #fff;
		box-shadow: 0 0 0 1px var(--border, #e5e7eb);
		border-radius: 2px;
	}
	.wchs-offline-payment__qr-label {
		font-size: 10px;
		text-transform: uppercase;
		letter-spacing: 0.08em;
		color: var(--fg-muted, #6b7280);
		font-weight: 500;
	}
	[data-theme='dark'] .wchs-offline-payment__qr img {
		border-color: #1a1a1a;
		box-shadow: 0 0 0 1px var(--border, #27272a);
	}
	@media (max-width: 480px) {
		.wchs-offline-payment__body {
			flex-direction: column;
		}
		.wchs-offline-payment__qr {
			align-items: flex-start;
		}
	}
	</style>
	<?php
} );
