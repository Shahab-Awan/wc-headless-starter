<?php
/**
 * Plugin Name: Headless Omnisend Compatibility
 * Description: Bridges Omnisend's on-site tracking / forms / popups / browser-push
 *              features to our headless split. The SPA boots Omnisend in
 *              spa/src/routes/+layout.svelte via initOmnisend(brand_id). This
 *              mu-plugin mirrors the same setup on the WP-rendered surfaces
 *              (checkout, thank-you, my-account, upsell) that the buyer
 *              actually lands on — they're WooCommerce pages, not SPA routes.
 *
 *
 * Author:      WCHS Contributors
 *
 * What this does:
 *   1. Reads omnisend_brand_id from wchs_site_settings
 *   2. Enqueues the Omnisend launcher + init snippet on customer-facing WP pages
 *   3. Wires checkout email field → omnisend.identifyContact on blur
 *   4. Fires $startedCheckout on checkout page load
 *   5. Fires $placedOrder server-side (via client-rendered snippet) on thank-you
 *
 * What this does NOT do:
 *   - Popup/signup form injection: handled automatically by launcher-v2.js
 *     once loaded. Admin configures popups in Omnisend dashboard.
 *   - Browser push opt-in: launcher-v2.js registers the SW and prompts.
 *   - Product catalog sync / order sync / abandoned cart: handled by the
 *     separate `omnisend-connect` plugin server-side.
 *
 * When omnisend_brand_id is empty, this plugin no-ops. Safe to deploy inactive.
 */

defined( 'ABSPATH' ) || exit;

function wchs_omnisend_get_brand_id(): string {
	$settings = get_option( 'wchs_site_settings', [] );
	$id = is_array( $settings ) ? (string) ( $settings['omnisend_brand_id'] ?? '' ) : '';
	return preg_match( '/^[a-f0-9]{20,32}$/i', $id ) ? $id : '';
}

/**
 * Is this a frontend page where we should inject the tracker?
 * Checkout, thank-you, my-account, and any upsell page qualify.
 * We skip admin, AJAX, REST, and cron.
 */
function wchs_omnisend_should_load(): bool {
	if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) return false;
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) return false;
	return is_checkout() || is_account_page() || is_wc_endpoint_url() || wchs_omnisend_is_upsell_page();
}

function wchs_omnisend_is_upsell_page(): bool {
	$req = $_SERVER['REQUEST_URI'] ?? '';
	return (bool) preg_match( '#/(upsell|offer)/#i', $req );
}

/**
 * Inject the Omnisend launcher + identity/event setup into wp_head. Fires
 * early so the queue (`window.omnisend = []`) is created before any page
 * script tries to push to it.
 */
add_action( 'wp_head', function () {
	$brand_id = wchs_omnisend_get_brand_id();
	if ( ! $brand_id || ! wchs_omnisend_should_load() ) return;

	$brand = esc_js( $brand_id );
	?>
<script data-wchs-omnisend>
(function(){
  window.omnisend = window.omnisend || [];
  window.omnisend.push(["accountID", "<?php echo $brand; ?>"]);
  window.omnisend.push(["track", "$pageViewed"]);
  var s = document.createElement("script");
  s.type = "text/javascript";
  s.async = true;
  s.src = "https://omnisnippet1.com/inshop/launcher-v2.js";
  var t = document.getElementsByTagName("script")[0];
  t.parentNode.insertBefore(s, t);
})();
</script>
	<?php
}, 1 );

/**
 * On checkout: wire the billing-email input to identifyContact on blur so
 * an abandoned cart can be attributed to the right contact. Runs in the
 * footer after wc-checkout JS has initialized.
 */
add_action( 'wp_footer', function () {
	$brand_id = wchs_omnisend_get_brand_id();
	if ( ! $brand_id || ! is_checkout() ) return;
	if ( is_wc_endpoint_url( 'order-received' ) ) return; // handled separately below
	?>
<script data-wchs-omnisend-checkout>
(function(){
  if (!window.omnisend) return;
  var $ = window.jQuery;
  function identifyFromForm(){
    var email = document.querySelector('#billing_email')?.value || '';
    if (!email || !/.+@.+\..+/.test(email)) return;
    var payload = { email: email };
    var fn = document.querySelector('#billing_first_name')?.value;
    var ln = document.querySelector('#billing_last_name')?.value;
    var ph = document.querySelector('#billing_phone')?.value;
    if (fn) payload.firstName = fn;
    if (ln) payload.lastName = ln;
    if (ph) payload.phone = ph;
    window.omnisend.push(["identifyContact", payload]);
  }
  function wire(){
    var emailEl = document.querySelector('#billing_email');
    if (!emailEl) return;
    emailEl.addEventListener('blur', identifyFromForm);
    emailEl.addEventListener('change', identifyFromForm);
  }
  // Fire immediately if the field already has a value (logged-in user)
  wire();
  identifyFromForm();
  // WC re-renders the checkout form on update_order_review — rebind.
  if ($) $(document.body).on('updated_checkout', wire);
  // $startedCheckout for abandoned-checkout attribution
  window.omnisend.push(["track", "$startedCheckout"]);
})();
</script>
	<?php
}, 99 );

/**
 * On the thank-you page (order-received): fire $placedOrder server-side
 * by emitting the order data to the tracker. Uses the same line-item
 * shape as the SPA order-received page.
 *
 * WP's order-received is rarely the landing page for our flow (SPA's
 * /order-received/ is), but it's kept as a defensive fallback for any
 * gateway that redirects buyers back to WP's endpoint directly.
 */
add_action( 'woocommerce_thankyou', function ( $order_id ) {
	$brand_id = wchs_omnisend_get_brand_id();
	if ( ! $brand_id || ! $order_id ) return;

	$order = wc_get_order( $order_id );
	if ( ! $order ) return;

	$items = [];
	foreach ( $order->get_items() as $item ) {
		if ( ! method_exists( $item, 'get_product' ) ) continue;
		$product = $item->get_product();
		$pid = $product ? $product->get_id() : (int) $item->get_product_id();
		if ( $pid <= 0 ) continue;
		$qty = max( 1, (int) $item->get_quantity() );
		$line_total = (float) $item->get_total();
		$items[] = [
			'$productID' => (string) $pid,
			'$title'     => $item->get_name(),
			'$quantity'  => $qty,
			'$price'     => (int) round( ( $line_total / $qty ) * 100 ),
		];
	}

	$total   = (int) round( (float) $order->get_total() * 100 );
	$email   = $order->get_billing_email();
	$order_j = wp_json_encode( [
		'$orderID'    => (string) $order_id,
		'$total'      => $total,
		'$currency'   => $order->get_currency(),
		'$email'      => $email,
		'$lineItems'  => $items,
	] );
	?>
<script data-wchs-omnisend-thanks>
(function(){
  if (!window.omnisend) return;
  var o = <?php echo $order_j; ?>;
  if (o.$email) window.omnisend.push(["identifyContact", { email: o.$email }]);
  window.omnisend.push(["track", "$placedOrder", o]);
})();
</script>
	<?php
}, 5 );
