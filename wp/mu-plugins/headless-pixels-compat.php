<?php
/**
 * Plugin Name: Headless Pixels Compatibility
 * Description: Injects ad-pixel / analytics snippets (Klaviyo, Meta, TikTok,
 *              Pinterest, Microsoft Clarity, Hotjar, Google Ads) on the
 *              WP-rendered surfaces (checkout, thank-you, my-account,
 *              upsell) that buyers still land on despite the headless split.
 *              The SPA handles these on its own routes via analytics.ts;
 *              this mu-plugin mirrors identical init + event firing on WP.
 *
 *
 * Author:      WCHS Contributors
 *
 * What this does:
 *   1. Reads pixel IDs from wchs_site_settings
 *   2. On customer-facing WP pages: inject each vendor's official snippet
 *   3. On checkout: wire email blur → identify for Klaviyo + TikTok
 *   4. On thank-you: fire Purchase / Placed Order events with full order data,
 *      including Google Ads conversion when configured
 *
 * When a given pixel's ID is empty, it's skipped individually. Leaving all
 * fields blank makes this plugin a total no-op.
 */

defined( 'ABSPATH' ) || exit;

function wchs_pixels_get_settings(): array {
	$s = get_option( 'wchs_site_settings', [] );
	return is_array( $s ) ? $s : [];
}

function wchs_pixels_should_load(): bool {
	if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) return false;
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) return false;
	return is_checkout() || is_account_page() || is_wc_endpoint_url() || wchs_pixels_is_upsell_page();
}

function wchs_pixels_is_upsell_page(): bool {
	$req = $_SERVER['REQUEST_URI'] ?? '';
	return (bool) preg_match( '#/(upsell|offer)/#i', $req );
}

/**
 * Head: inject each pixel's standard snippet. Order mirrors the SPA layout
 * init order. Each snippet is wrapped with a data-attribute marker for
 * the test suite to verify presence.
 */
add_action( 'wp_head', function () {
	if ( ! wchs_pixels_should_load() ) return;
	$s = wchs_pixels_get_settings();

	// ── Klaviyo ────────────────────────────────────────────────────────
	$klaviyo = (string) ( $s['klaviyo_public_key'] ?? '' );
	if ( $klaviyo ) {
		$k = esc_js( $klaviyo );
		?>
<script data-wchs-klaviyo>
window.klaviyo = window.klaviyo || [];
window._learnq = window._learnq || [];
</script>
<script data-wchs-klaviyo-src async src="https://static.klaviyo.com/onsite/js/klaviyo.js?company_id=<?php echo $k; ?>"></script>
		<?php
	}

	// ── TikTok Pixel ───────────────────────────────────────────────────
	$tiktok = (string) ( $s['tiktok_pixel_id'] ?? '' );
	if ( $tiktok ) {
		$tt = esc_js( $tiktok );
		?>
<script data-wchs-tiktok>
!function (w, d, t) {w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];ttq.methods=["page","track","identify","instances","debug","on","off","once","ready","alias","group","enableCookie","disableCookie"],ttq.setAndDefer=function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}};for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);ttq.instance=function(t){for(var e=ttq._i[t]||[],n=0;n<ttq.methods.length;n++)ttq.setAndDefer(e,ttq.methods[n]);return e},ttq.load=function(e,n){var r="https://analytics.tiktok.com/i18n/pixel/events.js";ttq._i=ttq._i||{},ttq._i[e]=[],ttq._i[e]._u=r,ttq._t=ttq._t||{},ttq._t[e]=+new Date,ttq._o=ttq._o||{},ttq._o[e]=n||{};var o=document.createElement("script");o.type="text/javascript",o.async=!0,o.src=r+"?sdkid="+e+"&lib="+t;var a=document.getElementsByTagName("script")[0];a.parentNode.insertBefore(o,a)};ttq.load('<?php echo $tt; ?>');ttq.page();}(window, document, 'ttq');
</script>
		<?php
	}

	// ── Pinterest Tag ──────────────────────────────────────────────────
	$pin = (string) ( $s['pinterest_tag_id'] ?? '' );
	if ( $pin ) {
		$p = esc_js( $pin );
		?>
<script data-wchs-pinterest>
!function(e){if(!window.pintrk){window.pintrk=function(){window.pintrk.queue.push(Array.prototype.slice.call(arguments))};var n=window.pintrk;n.queue=[],n.version="3.0";var t=document.createElement("script");t.async=!0,t.src=e;var r=document.getElementsByTagName("script")[0];r.parentNode.insertBefore(t,r)}}("https://s.pinimg.com/ct/core.js");
pintrk('load', '<?php echo $p; ?>');
pintrk('page');
</script>
		<?php
	}

	// ── Microsoft Clarity ──────────────────────────────────────────────
	$clarity = (string) ( $s['clarity_project_id'] ?? '' );
	if ( $clarity ) {
		$c = esc_js( $clarity );
		?>
<script data-wchs-clarity>
(function(c,l,a,r,i,t,y){c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y)})(window, document, "clarity", "script", "<?php echo $c; ?>");
</script>
		<?php
	}

	// ── Hotjar ─────────────────────────────────────────────────────────
	$hj = (string) ( $s['hotjar_site_id'] ?? '' );
	if ( $hj && ctype_digit( $hj ) ) {
		$h = (int) $hj;
		?>
<script data-wchs-hotjar>
(function(h,o,t,j,a,r){h.hj=h.hj||function(){(h.hj.q=h.hj.q||[]).push(arguments)};h._hjSettings={hjid:<?php echo $h; ?>,hjsv:6};a=o.getElementsByTagName('head')[0];r=o.createElement('script');r.async=1;r.src=t+h._hjSettings.hjid+j+h._hjSettings.hjsv;a.appendChild(r);})(window,document,'https://static.hotjar.com/c/hotjar-','.js?sv=');
</script>
		<?php
	}

	// ── GA4 + Google Ads (shared gtag.js) ──────────────────────────────
	$ga4_id  = (string) ( $s['ga4_measurement_id'] ?? '' );
	$gads_id = (string) ( $s['google_ads_conversion_id'] ?? '' );
	$ga4_ok  = $ga4_id && preg_match( '/^G-[A-Z0-9]+$/', $ga4_id );
	$gads_ok = $gads_id && preg_match( '/^AW-\d{9,12}$/', $gads_id );
	if ( $ga4_ok || $gads_ok ) {
		$loader_id = $ga4_ok ? esc_js( $ga4_id ) : esc_js( $gads_id );
		?>
<script data-wchs-gtag-src async src="https://www.googletagmanager.com/gtag/js?id=<?php echo $loader_id; ?>"></script>
<script data-wchs-gtag>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('js', new Date());
<?php if ( $ga4_ok ) : ?>
gtag('config', '<?php echo esc_js( $ga4_id ); ?>');
<?php endif; ?>
<?php if ( $gads_ok ) : ?>
gtag('config', '<?php echo esc_js( $gads_id ); ?>');
<?php endif; ?>
</script>
		<?php
	}
}, 2 );

/**
 * Footer on checkout: wire email-blur → identifyContact for Klaviyo and
 * TikTok (Omnisend has its own handler in headless-omnisend-compat.php).
 * Also fire InitiateCheckout / started_checkout events.
 */
add_action( 'wp_footer', function () {
	if ( ! is_checkout() || is_wc_endpoint_url( 'order-received' ) ) return;
	$s = wchs_pixels_get_settings();
	$has_klav = ! empty( $s['klaviyo_public_key'] );
	$has_tt   = ! empty( $s['tiktok_pixel_id'] );
	$has_pin  = ! empty( $s['pinterest_tag_id'] );
	if ( ! ( $has_klav || $has_tt || $has_pin ) ) return;

	// Gather cart data for InitiateCheckout values
	$cart = WC()->cart;
	$total_cents = $cart ? (int) round( (float) $cart->get_total( 'raw' ) * 100 ) : 0;
	$item_count  = $cart ? (int) $cart->get_cart_contents_count() : 0;
	?>
<script data-wchs-pixels-checkout>
(function(){
  var $ = window.jQuery;
  var klav = <?php echo $has_klav ? 'true' : 'false'; ?>;
  var tt   = <?php echo $has_tt ? 'true' : 'false'; ?>;
  var pin  = <?php echo $has_pin ? 'true' : 'false'; ?>;
  var totalCents = <?php echo (int) $total_cents; ?>;
  var itemCount = <?php echo (int) $item_count; ?>;

  function identify(){
    var email = document.querySelector('#billing_email')?.value || '';
    if (!email || !/.+@.+\..+/.test(email)) return;
    var first = document.querySelector('#billing_first_name')?.value || '';
    var last  = document.querySelector('#billing_last_name')?.value || '';
    var phone = document.querySelector('#billing_phone')?.value || '';
    if (klav && window.klaviyo) {
      var k = { $email: email };
      if (first) k.$first_name = first;
      if (last)  k.$last_name  = last;
      if (phone) k.$phone_number = phone;
      window.klaviyo.push(['identify', k]);
      if (window._learnq) window._learnq.push(['identify', k]);
    }
    if (tt && window.ttq) window.ttq.identify({ email: email, phone_number: phone || undefined });
    if (typeof window.TriplePixel === 'function') {
      var tp = { email: email };
      if (phone) tp.phone = phone;
      window.TriplePixel('Contact', tp);
    }
  }
  function wire(){
    var e = document.querySelector('#billing_email');
    if (!e) return;
    e.addEventListener('blur', identify);
    e.addEventListener('change', identify);
  }
  wire();
  identify();
  if ($) $(document.body).on('updated_checkout', wire);

  // Fire checkout-started events for each pixel that's enabled
  if (tt && window.ttq)   window.ttq.track('InitiateCheckout', { value: totalCents/100, currency: 'USD', contents: Array(itemCount).fill({}) });
  if (pin && window.pintrk) window.pintrk('track', 'checkout', { value: totalCents/100, order_quantity: itemCount, currency: 'USD' });
})();
</script>
	<?php
}, 99 );

/**
 * Thank-you page: fire every enabled pixel's purchase/conversion event
 * with full order data. Mirrors the SPA's order-received firePurchase().
 */
add_action( 'woocommerce_thankyou', function ( $order_id ) {
	if ( ! $order_id ) return;
	$order = wc_get_order( $order_id );
	if ( ! $order ) return;
	$s = wchs_pixels_get_settings();

	$has_klav = ! empty( $s['klaviyo_public_key'] );
	$has_tt   = ! empty( $s['tiktok_pixel_id'] );
	$has_pin  = ! empty( $s['pinterest_tag_id'] );
	$has_gads = ! empty( $s['google_ads_conversion_id'] ) && ! empty( $s['google_ads_conversion_label'] );
	if ( ! ( $has_klav || $has_tt || $has_pin || $has_gads ) ) return;

	$items = [];
	$content_ids = [];
	$num_items = 0;
	foreach ( $order->get_items() as $item ) {
		if ( ! method_exists( $item, 'get_product' ) ) continue;
		$product = $item->get_product();
		$pid = $product ? $product->get_id() : (int) $item->get_product_id();
		if ( $pid <= 0 ) continue;
		$qty = max( 1, (int) $item->get_quantity() );
		$line_total = (float) $item->get_total();
		$items[] = [
			'id'       => (string) $pid,
			'name'     => $item->get_name(),
			'quantity' => $qty,
			'price'    => (float) ( $line_total / $qty ),
		];
		$content_ids[] = (string) $pid;
		$num_items += $qty;
	}
	$total = (float) $order->get_total();
	$currency = $order->get_currency();
	$email = $order->get_billing_email();

	$gads_send_to = $has_gads
		? esc_js( $s['google_ads_conversion_id'] . '/' . $s['google_ads_conversion_label'] )
		: '';
	?>
<script data-wchs-pixels-thanks>
(function(){
  var items = <?php echo wp_json_encode( $items ); ?>;
  var contentIds = <?php echo wp_json_encode( $content_ids ); ?>;
  var total = <?php echo (float) $total; ?>;
  var currency = <?php echo wp_json_encode( $currency ); ?>;
  var email = <?php echo wp_json_encode( $email ); ?>;
  var orderId = <?php echo wp_json_encode( (string) $order_id ); ?>;

  <?php if ( $has_klav ) : ?>
  if (window.klaviyo) {
    if (email) {
      window.klaviyo.push(['identify', { $email: email }]);
      if (window._learnq) window._learnq.push(['identify', { $email: email }]);
    }
    var klavItems = items.map(function(li){
      return { ProductID: li.id, ProductName: li.name, Quantity: li.quantity, ItemPrice: li.price, RowTotal: li.price * li.quantity };
    });
    window.klaviyo.push(['track', 'Placed Order', { $event_id: orderId, $value: total, ItemNames: items.map(function(li){return li.name;}), Items: klavItems }]);
  }
  <?php endif; ?>
  <?php if ( $has_tt ) : ?>
  if (window.ttq) {
    if (email) window.ttq.identify({ email: email });
    window.ttq.track('CompletePayment', { contents: items.map(function(li){return { content_id: li.id, content_name: li.name, price: li.price, quantity: li.quantity };}), value: total, currency: currency });
  }
  <?php endif; ?>
  <?php if ( $has_pin ) : ?>
  if (window.pintrk) window.pintrk('track', 'checkout', { value: total, order_quantity: <?php echo (int) $num_items; ?>, currency: currency, order_id: orderId, line_items: items.map(function(li){ return { product_name: li.name, product_id: li.id, product_price: li.price, product_quantity: li.quantity }; }) });
  <?php endif; ?>
  <?php if ( $has_gads ) : ?>
  if (window.gtag) window.gtag('event', 'conversion', { send_to: '<?php echo $gads_send_to; ?>', value: total, currency: currency, transaction_id: orderId });
  <?php endif; ?>
})();
</script>
	<?php
}, 10 );
