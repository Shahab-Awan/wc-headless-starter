<?php
/**
 * Plugin Name: Headless Thank-You Tracking
 * Description: CustomerLabs Purchased + GTM purchase on the native WC order-received page,
 *              and on a brief bridge page before SPA redirect so conversions are not lost.
 * Version:     0.1.0
 * Author:      WCHS Contributors
 */

defined( 'ABSPATH' ) || exit;

/**
 * Resolve order id from WC endpoint query var or /checkout/order-received/{id}/ path.
 */
function wchs_thankyou_request_order_id(): int {
	global $wp;
	$id = absint( $wp->query_vars['order-received'] ?? 0 );
	if ( $id > 0 ) {
		return $id;
	}
	$path = wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ) ?? '';
	if ( preg_match( '#/checkout/order-received/(\d+)/?#', $path, $m ) ) {
		return absint( $m[1] );
	}
	return 0;
}

/**
 * @return string Sanitized order key from query string, or empty.
 */
function wchs_thankyou_request_key(): string {
	return isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';
}

function wchs_thankyou_is_order_received_request(): bool {
	if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'order-received' ) ) {
		return true;
	}
	$path = wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ) ?? '';
	return (bool) preg_match( '#/checkout/order-received/\d+/?#', $path );
}

/**
 * Build CustomerLabs + dataLayer purchase scripts for a placed order.
 */
function wchs_thankyou_purchase_scripts_html( \WC_Order $order ): string {
	$order_id = (int) $order->get_id();
	$currency = $order->get_currency();
	$total    = round( (float) $order->get_total(), 4 );

	$ga_items = [];
	$cl_products = [];
	$content_ids = [];
	$num_items   = 0;

	foreach ( $order->get_items() as $item ) {
		if ( ! $item instanceof \WC_Order_Item_Product ) {
			continue;
		}
		$product = $item->get_product();
		$pid     = $product ? $product->get_id() : (int) $item->get_product_id();
		if ( $pid <= 0 ) {
			continue;
		}
		$qty        = max( 1, (int) $item->get_quantity() );
		$line_total = (float) $item->get_total();
		$unit       = $qty > 0 ? round( $line_total / $qty, 4 ) : $line_total;
		$image      = '';
		if ( $product ) {
			$img_id = $product->get_image_id();
			if ( $img_id ) {
				$image = (string) wp_get_attachment_image_url( $img_id, 'woocommerce_thumbnail' );
			}
		}

		$ga_items[] = [
			'item_id'   => (string) $pid,
			'item_name' => $item->get_name(),
			'price'     => $unit,
			'quantity'  => $qty,
		];
		$content_ids[] = (string) $pid;
		$num_items    += $qty;

		$cl_row = [
			'product_id'       => [ 't' => 'number', 'v' => (string) $pid ],
			'product_name'     => [ 't' => 'string', 'v' => $item->get_name() ],
			'product_price'    => [ 't' => 'number', 'v' => (string) $unit ],
			'product_quantity' => [ 't' => 'number', 'v' => (string) $qty ],
		];
		if ( $image !== '' ) {
			$cl_row['product_image'] = [ 't' => 'string', 'v' => $image ];
		}
		$cl_products[] = $cl_row;
	}

	$email = (string) $order->get_billing_email();
	$subtotal = round( (float) $order->get_subtotal(), 4 );
	$shipping = round( (float) $order->get_shipping_total(), 4 );
	$tax      = round( (float) $order->get_total_tax(), 4 );
	$host     = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
	$uri      = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
	$page_url = ( is_ssl() ? 'https://' : 'http://' ) . $host . $uri;

	$payload = [
		'orderId'      => (string) $order_id,
		'currency'     => $currency,
		'total'        => $total,
		'subtotal'     => $subtotal,
		'shipping'     => $shipping,
		'tax'          => $tax,
		'pageUrl'      => $page_url,
		'email'        => $email,
		'gaItems'      => $ga_items,
		'clProducts'   => $cl_products,
		'contentIds'   => $content_ids,
		'numItems'     => $num_items,
	];

	ob_start();
	?>
<script data-wchs-thankyou-purchase>
(function(){
  var p = <?php echo wp_json_encode( $payload ); ?>;
  var dedupeKey = 'wchs_cl_purchased_' + p.orderId;
  try {
    if (sessionStorage.getItem(dedupeKey)) return;
  } catch (e) {}

  window.dataLayer = window.dataLayer || [];
  window.dataLayer.push({ ecommerce: null });
  window.dataLayer.push({
    event: 'purchase',
    ecommerce: {
      transaction_id: p.orderId,
      value: p.total,
      currency: p.currency,
      items: p.gaItems
    }
  });

  function clStr(v) { return { t: 'string', v: String(v).slice(0, 2000) }; }
  function clNum(v) { return { t: 'number', v: String(v) }; }
  function fireCl() {
    if (typeof window._cl !== 'object' || typeof window._cl.trackClick !== 'function') return false;
    try {
      if (sessionStorage.getItem(dedupeKey)) return true;
    } catch (e) {}
    window._cl.trackClick('Purchased', {
      productProperties: p.clProducts,
      customProperties: {
        transaction_id: clStr(p.orderId),
        order_id: clStr(p.orderId),
        currency: clStr(p.currency),
        value: clNum(p.total),
        subtotal: clNum(p.subtotal),
        shipping: clNum(p.shipping),
        tax: clNum(p.tax),
        page_url: clStr(p.pageUrl)
      }
    });
    if (p.email && typeof window._cl.identify === 'function') {
      window._cl.identify({
        customProperties: {
          identify_by_email: { t: 'string', v: p.email, ib: true }
        }
      });
    }
    try { sessionStorage.setItem(dedupeKey, '1'); } catch (e) {}
    try { sessionStorage.setItem('wchs_purchase_fired_' + p.orderId, '1'); } catch (e) {}
    return true;
  }

  var attempts = 0;
  var timer = setInterval(function () {
    var ready = ((window.CLabsgbVar || {}).generalProps || {}).uid;
    if ((ready && fireCl()) || attempts >= 120) clearInterval(timer);
    attempts++;
  }, 500);
})();
</script>
	<?php
	return (string) ob_get_clean();
}

/**
 * Minimal HTML bridge: fire purchase pixels, then redirect to SPA thank-you.
 */
function wchs_thankyou_bridge_redirect( \WC_Order $order, string $target_url ): void {
	status_header( 200 );
	header( 'Content-Type: text/html; charset=utf-8' );
	nocache_headers();

	$scripts = wchs_thankyou_purchase_scripts_html( $order );
	$safe_url = esc_url( $target_url );
	?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8" />
	<meta name="referrer" content="no-referrer" />
	<meta http-equiv="refresh" content="0;url=<?php echo esc_attr( $safe_url ); ?>" />
	<title>Order received</title>
	<?php
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- script blob from trusted builder.
	echo $scripts;
	?>
</head>
<body>
	<p>Order received. Redirecting…</p>
	<script>location.replace(<?php echo wp_json_encode( $target_url ); ?>);</script>
</body>
</html>
	<?php
	exit;
}

/**
 * Extra copy + order number on the native WC thank-you template.
 */
add_action(
	'woocommerce_thankyou',
	function ( $order_id ) {
		if ( ! $order_id ) {
			return;
		}
		$order = wc_get_order( $order_id );
		if ( ! ( $order instanceof \WC_Order ) ) {
			return;
		}

		$number = $order->get_order_number();
		$email  = $order->get_billing_email();
		?>
		<div class="wchs-native-thankyou" style="max-width:36rem;margin:1.5rem auto 0;text-align:center;line-height:1.6;">
			<p style="margin:0 0 0.75rem;font-size:1.05rem;">
				<strong>Order #<?php echo esc_html( $number ); ?></strong>
			</p>
			<p style="margin:0 0 0.75rem;color:inherit;">
				Thank you for your order — we have received it and are getting it ready.
			</p>
			<?php if ( $email ) : ?>
				<p style="margin:0;color:inherit;opacity:0.85;">
					A confirmation email will be sent to <strong><?php echo esc_html( $email ); ?></strong>.
				</p>
			<?php endif; ?>
		</div>
		<?php
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo wchs_thankyou_purchase_scripts_html( $order );
	},
	8
);

add_filter(
	'woocommerce_thankyou_order_received_text',
	function ( $text, $order ) {
		if ( $order instanceof \WC_Order ) {
			return sprintf(
				/* translators: %s: order number */
				__( 'Thank you. Your order #%s has been received.', 'wchs' ),
				$order->get_order_number()
			);
		}
		return $text;
	},
	10,
	2
);
