<?php
/**
 * Plugin Name: Headless Thank-You Tracking
 * Description: Native /checkout/order-received/ confirmation copy + purchase pixels
 *              (CustomerLabs Purchased, GTM purchase). Works with upsell URLs that use order_key.
 * Version:     0.3.2
 * Author:      WCHS Contributors
 */

defined( 'ABSPATH' ) || exit;

/**
 * WC thank-you template only reads $_GET['key']; upsell links pass order_key.
 */
add_filter(
	'woocommerce_thankyou_order_key',
	function ( $key ) {
		if ( is_string( $key ) && $key !== '' ) {
			return $key;
		}
		if ( isset( $_GET['order_key'] ) ) {
			return sanitize_text_field( wp_unslash( $_GET['order_key'] ) );
		}
		return $key;
	},
	5
);

/**
 * Normalize upsell URLs: WC only reads ?key=, Alyve upsell links pass ?order_key=.
 */
add_action(
	'template_redirect',
	function () {
		if ( ! wchs_thankyou_is_order_received_request() ) {
			return;
		}
		if ( isset( $_GET['key'] ) && (string) $_GET['key'] !== '' ) {
			return;
		}
		if ( ! isset( $_GET['order_key'] ) || (string) $_GET['order_key'] === '' ) {
			return;
		}
		$target = add_query_arg(
			'key',
			sanitize_text_field( wp_unslash( $_GET['order_key'] ) ),
			remove_query_arg( 'key' )
		);
		wp_safe_redirect( $target, 302 );
		exit;
	},
	4
);

function wchs_thankyou_has_order_query(): bool {
	if ( empty( $_GET['order_id'] ) ) {
		return false;
	}
	if ( ! empty( $_GET['key'] ) || ! empty( $_GET['order_key'] ) ) {
		return true;
	}
	return ! empty( $_GET['wfty_source'] );
}

/**
 * FunnelKit thank-you permalink (e.g. /order-confirmed/thank-you-page/).
 */
function wchs_thankyou_is_funnelkit_ty_path(): bool {
	$path = trim( (string) ( wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ) ?? '' ), '/' );
	if ( $path === '' ) {
		return false;
	}
	return (bool) preg_match( '#^(order-confirmed|thankyou|thank-you)(/|$)#', $path );
}

function wchs_thankyou_is_order_received_request(): bool {
	if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'order-received' ) ) {
		return true;
	}
	$path = wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ) ?? '';
	if ( preg_match( '#/checkout/order-received/\d+/?#', $path ) ) {
		return true;
	}
	if ( wchs_thankyou_is_funnelkit_ty_path() && wchs_thankyou_has_order_query() ) {
		return true;
	}
	if ( function_exists( 'wchs_is_funnelkit_thankyou_request' ) && wchs_is_funnelkit_thankyou_request() ) {
		return true;
	}
	return false;
}

function wchs_thankyou_request_order_id(): int {
	global $wp;
	$id = absint( $wp->query_vars['order-received'] ?? 0 );
	if ( $id > 0 ) {
		return $id;
	}
	if ( isset( $_GET['order_id'] ) ) {
		$from_query = absint( $_GET['order_id'] );
		if ( $from_query > 0 ) {
			return $from_query;
		}
	}
	$path = wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ) ?? '';
	if ( preg_match( '#/checkout/order-received/(\d+)/?#', $path, $m ) ) {
		return absint( $m[1] );
	}
	return 0;
}

function wchs_thankyou_request_key(): string {
	if ( isset( $_GET['key'] ) ) {
		$key = sanitize_text_field( wp_unslash( $_GET['key'] ) );
		if ( $key !== '' ) {
			return $key;
		}
	}
	if ( isset( $_GET['order_key'] ) ) {
		return sanitize_text_field( wp_unslash( $_GET['order_key'] ) );
	}
	return '';
}

/**
 * Resolve + validate the order for the current thank-you / upsell request.
 */
function wchs_thankyou_resolve_order(): ?\WC_Order {
	$queued = wchs_thankyou_peek_queued_order();
	if ( $queued instanceof \WC_Order ) {
		return $queued;
	}

	$order_id = wchs_thankyou_request_order_id();
	if ( ! $order_id ) {
		return null;
	}

	$order = wc_get_order( $order_id );
	if ( ! ( $order instanceof \WC_Order ) ) {
		return null;
	}

	$key = wchs_thankyou_request_key();
	if ( $key !== '' && ! hash_equals( (string) $order->get_order_key(), $key ) ) {
		return null;
	}

	return $order;
}

/**
 * @param \WC_Order $order
 */
function wchs_thankyou_queue_order( \WC_Order $order ): void {
	$GLOBALS['wchs_thankyou_queued_order'] = $order;
}

function wchs_thankyou_peek_queued_order(): ?\WC_Order {
	$order = $GLOBALS['wchs_thankyou_queued_order'] ?? null;
	return $order instanceof \WC_Order ? $order : null;
}

add_action(
	'woocommerce_thankyou',
	static function ( $order_id ) {
		$order_id = absint( $order_id );
		if ( $order_id < 1 ) {
			return;
		}
		if ( ! wchs_thankyou_is_order_received_request() && ! wchs_thankyou_is_funnelkit_ty_path() ) {
			return;
		}
		$order = wc_get_order( $order_id );
		if ( $order instanceof \WC_Order ) {
			wchs_thankyou_queue_order( $order );
		}
	},
	5
);

function wchs_thankyou_confirmation_html( \WC_Order $order ): string {
	$number = $order->get_order_number();
	$email  = $order->get_billing_email();

	ob_start();
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
	return (string) ob_get_clean();
}

/**
 * Build CustomerLabs + dataLayer purchase scripts for a placed order.
 */
function wchs_thankyou_purchase_scripts_html( \WC_Order $order ): string {
	$order_id = (int) $order->get_id();
	$currency = $order->get_currency();
	$total    = round( (float) $order->get_total(), 4 );

	$ga_items    = [];
	$cl_products = [];

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

	$email    = (string) $order->get_billing_email();
	$subtotal = round( (float) $order->get_subtotal(), 4 );
	$shipping = round( (float) $order->get_shipping_total(), 4 );
	$tax      = round( (float) $order->get_total_tax(), 4 );
	$host     = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
	$uri      = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
	$page_url = ( is_ssl() ? 'https://' : 'http://' ) . $host . $uri;

	$payload = [
		'orderId'    => (string) $order_id,
		'currency'   => $currency,
		'total'      => $total,
		'subtotal'   => $subtotal,
		'shipping'   => $shipping,
		'tax'        => $tax,
		'pageUrl'    => $page_url,
		'email'      => $email,
		'gaItems'    => $ga_items,
		'clProducts' => $cl_products,
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
    var clPayload = {
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
    };
    window._cl.trackClick('Purchased', clPayload);
    window._cl.trackClick('pur_1', clPayload);
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
    var hasApi = typeof window._cl === 'object' && typeof window._cl.trackClick === 'function';
    var hasUid = ((window.CLabsgbVar || {}).generalProps || {}).uid;
    if ((hasApi && (hasUid || attempts >= 40)) && fireCl()) {
      clearInterval(timer);
      return;
    }
    if (attempts >= 120) clearInterval(timer);
    attempts++;
  }, 500);
})();
</script>
	<?php
	return (string) ob_get_clean();
}

/**
 * FunnelKit exposes wfocu_tracking_data on thank-you / upsell — same Purchased attrs when PHP order is unavailable.
 */
function wchs_thankyou_funnelkit_tracking_fallback_script(): string {
	ob_start();
	?>
<script data-wchs-thankyou-purchase-fk>
(function(){
  var dedupePrefix = 'wchs_cl_purchased_';
  function clStr(v) { return { t: 'string', v: String(v).slice(0, 2000) }; }
  function clNum(v) { return { t: 'number', v: String(v) }; }
  function fireCl(orderId, currency, total, email) {
    var dedupeKey = dedupePrefix + orderId;
    try { if (sessionStorage.getItem(dedupeKey)) return true; } catch (e) {}
    if (typeof window._cl !== 'object' || typeof window._cl.trackClick !== 'function') return false;
    var clPayload = {
      productProperties: [],
      customProperties: {
        transaction_id: clStr(orderId),
        order_id: clStr(orderId),
        currency: clStr(currency || 'USD'),
        value: clNum(total),
        page_url: clStr(window.location.href.split('#')[0])
      }
    };
    window._cl.trackClick('Purchased', clPayload);
    window._cl.trackClick('pur_1', clPayload);
    if (email && typeof window._cl.identify === 'function') {
      window._cl.identify({
        customProperties: {
          identify_by_email: { t: 'string', v: email, ib: true }
        }
      });
    }
    try { sessionStorage.setItem(dedupeKey, '1'); } catch (e) {}
    try { sessionStorage.setItem('wchs_purchase_fired_' + orderId, '1'); } catch (e) {}
    return true;
  }
  function fromFk() {
    var d = window.wfocu_tracking_data;
    if (!d || typeof d !== 'object') return false;
    var orderId = d.ga_transaction_id || d.transaction_id || '';
    if (!orderId) return false;
    var total = d.total != null ? d.total : (d.revenue != null ? d.revenue : 0);
    var currency = d.currency || 'USD';
    return fireCl(String(orderId), currency, total, d.email || '');
  }
  var attempts = 0;
  var timer = setInterval(function () {
    if (fromFk()) { clearInterval(timer); return; }
    if (attempts >= 120) clearInterval(timer);
    attempts++;
  }, 500);
})();
</script>
	<?php
	return (string) ob_get_clean();
}

function wchs_thankyou_emit_confirmation_once( \WC_Order $order ): void {
	static $emitted = [];
	$id = (int) $order->get_id();
	if ( isset( $emitted[ $id ] ) ) {
		return;
	}
	$emitted[ $id ] = true;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo wchs_thankyou_confirmation_html( $order );
}

function wchs_thankyou_emit_scripts_once( \WC_Order $order ): void {
	static $emitted = [];
	$id = (int) $order->get_id();
	if ( isset( $emitted[ $id ] ) ) {
		return;
	}
	$emitted[ $id ] = true;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo wchs_thankyou_purchase_scripts_html( $order );
}

add_action(
	'wp_body_open',
	function () {
		if ( ! wchs_thankyou_is_order_received_request() ) {
			return;
		}
		$order = wchs_thankyou_resolve_order();
		if ( ! ( $order instanceof \WC_Order ) ) {
			return;
		}
		echo "\n<!-- wchs-thankyou-tracking v0.3.2 -->\n";
		wchs_thankyou_emit_confirmation_once( $order );
	},
	5
);

add_action(
	'wp_footer',
	function () {
		if ( ! wchs_thankyou_is_order_received_request() && ! wchs_thankyou_is_funnelkit_ty_path() ) {
			return;
		}
		$order = wchs_thankyou_resolve_order();
		if ( $order instanceof \WC_Order ) {
			wchs_thankyou_emit_scripts_once( $order );
			return;
		}
		if ( wchs_thankyou_is_funnelkit_ty_path() && wchs_thankyou_has_order_query() ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo wchs_thankyou_funnelkit_tracking_fallback_script();
		}
	},
	99
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
