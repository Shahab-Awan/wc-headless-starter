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

	// Elementor renders shortcodes after wp_head — enqueue alone often never prints.
	// Inline CSS + JS in the shortcode output so styles always apply.
	wchs_checkout_mini_cart_schedule_assets();

	ob_start();
	echo wchs_checkout_mini_cart_inline_css(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	wchs_checkout_mini_cart_render( (string) $atts['title'] );
	echo wchs_checkout_mini_cart_inline_js(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	return (string) ob_get_clean();
}
add_shortcode( 'wchs_checkout_mini_cart', 'wchs_checkout_mini_cart_shortcode' );

/**
 * Load asset file, or built-in fallback (so deploy of PHP alone still styles).
 */
function wchs_checkout_mini_cart_read_asset( string $relative ): string {
	$path = WCHS_MINI_CART_DIR . '/assets/' . ltrim( $relative, '/' );
	if ( is_readable( $path ) ) {
		$raw = file_get_contents( $path );
		if ( is_string( $raw ) && $raw !== '' ) {
			return $raw;
		}
	}
	if ( $relative === 'mini-cart.css' ) {
		return wchs_checkout_mini_cart_fallback_css();
	}
	if ( $relative === 'mini-cart.js' ) {
		return wchs_checkout_mini_cart_fallback_js();
	}
	return '';
}

/** @return string */
function wchs_checkout_mini_cart_fallback_css(): string {
	return <<<'CSS'
.wchs-mini-cart{--wchs-mc-bg:#e8f4f7;--wchs-mc-border:#7eb8c4;--wchs-mc-fg:#3a3f45;--wchs-mc-muted:#6b7280;--wchs-mc-was:#e57373;--wchs-mc-teal:#2a9d8f;--wchs-mc-btn:#b0b6be;--wchs-mc-radius:10px;position:relative;width:100%;max-width:420px;font-family:Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif;color:var(--wchs-mc-fg);box-sizing:border-box}
.wchs-mini-cart *,.wchs-mini-cart *::before,.wchs-mini-cart *::after{box-sizing:border-box}
.wchs-mini-cart__inner{background:var(--wchs-mc-bg);border:1px solid var(--wchs-mc-border);border-radius:var(--wchs-mc-radius);padding:20px 18px 16px}
.wchs-mini-cart__title{margin:0 0 16px;font-size:20px;font-weight:700;line-height:1.25;color:var(--wchs-mc-fg);letter-spacing:-.02em}
.wchs-mini-cart__empty{margin:8px 0 4px;font-size:14px;color:var(--wchs-mc-muted)}
.wchs-mini-cart__items{list-style:none!important;margin:0!important;padding:0!important;display:flex;flex-direction:column;gap:14px}
.wchs-mini-cart__items>li{list-style:none!important;margin:0!important;padding:0!important}
.wchs-mini-cart__item{display:grid;grid-template-columns:56px 1fr auto;gap:12px;align-items:flex-start}
.wchs-mini-cart__media{position:relative;width:56px;height:56px;flex-shrink:0}
.wchs-mini-cart__media img{display:block;width:56px;height:56px;object-fit:cover;border-radius:8px;border:1px solid rgba(0,0,0,.06);background:#fff}
.wchs-mini-cart__qty{position:absolute;top:-6px;right:-6px;min-width:20px;height:20px;padding:0 5px;border-radius:999px;background:#6b7280;color:#fff;font-size:11px;font-weight:700;line-height:20px;text-align:center}
.wchs-mini-cart__info{min-width:0;padding-top:2px}
.wchs-mini-cart__name{margin:0;font-size:14px;font-weight:500;line-height:1.35;color:var(--wchs-mc-fg);word-break:break-word}
.wchs-mini-cart__stepper{display:inline-flex;align-items:center;margin-top:8px;height:28px;border:1px solid #c5cad1;border-radius:6px;background:#fff;overflow:hidden}
.wchs-mini-cart__step{width:28px;height:28px;padding:0;border:0;background:transparent;color:var(--wchs-mc-fg);font-size:16px;font-weight:600;line-height:1;cursor:pointer}
.wchs-mini-cart__step:hover{background:rgba(0,0,0,.04)}
.wchs-mini-cart__step-val{min-width:28px;padding:0 4px;text-align:center;font-size:13px;font-weight:600;line-height:1;color:var(--wchs-mc-fg);border-left:1px solid #e5e7eb;border-right:1px solid #e5e7eb;user-select:none}
.wchs-mini-cart__aside{display:flex;flex-direction:column;align-items:flex-end;gap:6px;min-width:72px}
.wchs-mini-cart__prices{display:flex;flex-direction:column;align-items:flex-end;gap:2px;text-align:right}
.wchs-mini-cart__was{font-size:12px;font-weight:500;color:var(--wchs-mc-was);text-decoration:line-through}
.wchs-mini-cart__was .amount,.wchs-mini-cart__now .amount{color:inherit}
.wchs-mini-cart__now{font-size:14px;font-weight:600;color:var(--wchs-mc-fg)}
.wchs-mini-cart__now--free{color:var(--wchs-mc-teal);text-transform:uppercase;letter-spacing:.02em}
.wchs-mini-cart__item.is-shipping-protection .wchs-mini-cart__stepper{display:none}
.wchs-mini-cart__remove{display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;padding:0;border:1px solid #c5cad1;border-radius:50%;background:transparent;color:#9aa1aa;font-size:16px;line-height:1;cursor:pointer}
.wchs-mini-cart__remove:hover{border-color:#8b939e;color:#5b6470}
.wchs-mini-cart__coupon{display:flex;gap:8px;margin:16px 0 0}
.wchs-mini-cart__coupon-input{flex:1 1 auto;min-width:0;height:40px;padding:0 12px;border:1px solid #c9ced3;border-radius:4px;background:#fff;color:var(--wchs-mc-fg);font:inherit;font-size:14px}
.wchs-mini-cart__coupon-input::placeholder{color:#9aa1aa}
.wchs-mini-cart__coupon-input:focus{outline:none;border-color:var(--wchs-mc-border)}
.wchs-mini-cart__coupon-btn{flex:0 0 auto;height:40px;padding:0 16px;border:0;border-radius:4px;background:var(--wchs-mc-btn);color:#fff;font:inherit;font-size:14px;font-weight:700;cursor:pointer}
.wchs-mini-cart__coupon-btn:hover{filter:brightness(.96)}
.wchs-mini-cart__coupon-msg{margin:8px 0 0;font-size:12px;color:#b45309}
.wchs-mini-cart__coupon-msg.is-error{color:#b91c1c}
.wchs-mini-cart__row--coupon{align-items:center;color:var(--wchs-mc-teal);font-size:13px}
.wchs-mini-cart__coupon-label{display:inline-flex;align-items:center;flex-wrap:wrap;gap:6px;min-width:0}
.wchs-mini-cart__coupon-label-text{color:var(--wchs-mc-fg);font-size:14px}
.wchs-mini-cart__coupon-code{display:inline-flex;align-items:center;gap:4px;min-width:0;font-weight:600;color:var(--wchs-mc-teal);word-break:break-all}
.wchs-mini-cart__coupon-icon{flex-shrink:0;color:#9aa1aa}
.wchs-mini-cart__coupon-value{display:inline-flex;align-items:center;gap:6px;flex-shrink:0;white-space:nowrap}
.wchs-mini-cart__coupon-amount{font-weight:600;color:var(--wchs-mc-teal)}
.wchs-mini-cart__coupon-remove-link{padding:0;border:0;background:transparent;color:var(--wchs-mc-teal);font:inherit;font-size:12px;font-weight:600;line-height:1;cursor:pointer;text-decoration:none}
.wchs-mini-cart__coupon-remove-link:hover{text-decoration:underline}
.wchs-mini-cart__totals{margin-top:16px;padding-top:14px;border-top:1px solid rgba(0,0,0,.1);display:flex;flex-direction:column;gap:8px}
.wchs-mini-cart__row{display:flex;justify-content:space-between;align-items:baseline;gap:12px;font-size:14px;color:var(--wchs-mc-fg)}
.wchs-mini-cart__row--total{margin-top:4px;padding-top:10px;border-top:1px solid rgba(0,0,0,.1);font-size:18px;font-weight:700}
.wchs-mini-cart__savings{display:flex;align-items:center;gap:8px;margin:14px 0 0;font-size:13px;font-weight:600;line-height:1.35;color:var(--wchs-mc-teal)}
.wchs-mini-cart__savings-icon{flex-shrink:0;display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;border-radius:50%;background:var(--wchs-mc-teal);color:#fff;font-size:11px;font-weight:800}
.wchs-mini-cart.is-busy .wchs-mini-cart__busy{display:block}
.wchs-mini-cart__busy{display:none;position:absolute;inset:0;border-radius:var(--wchs-mc-radius);background:rgba(255,255,255,.45);cursor:wait}
CSS;
}

/** @return string */
function wchs_checkout_mini_cart_fallback_js(): string {
	return <<<'JS'
(function(){"use strict";if(window.__wchsMiniCartBound)return;window.__wchsMiniCartBound=true;var cfg=window.wchsMiniCart||{};if(!cfg.ajaxUrl||!cfg.nonce)return;var refreshTimer=null,refreshing=false;function rootFrom(el){return el&&el.closest?el.closest("[data-wchs-mini-cart]"):null}function activeRoot(){return document.querySelector("[data-wchs-mini-cart]")}function setBusy(root,on){if(!root)return;root.classList.toggle("is-busy",!!on);var overlay=root.querySelector(".wchs-mini-cart__busy");if(overlay)overlay.hidden=!on}function triggerCheckoutRefresh(){try{if(window.jQuery){window.jQuery(document.body).trigger("update_checkout");window.jQuery(document.body).trigger("wc_fragment_refresh")}document.body.dispatchEvent(new CustomEvent("wchs_mini_cart_updated",{bubbles:true}))}catch(e){}}function replaceRoot(root,html){var wrap=document.createElement("div");wrap.innerHTML=html.trim();var next=wrap.querySelector("[data-wchs-mini-cart]")||wrap.firstElementChild;if(!next||!root.parentNode)return root;root.parentNode.replaceChild(next,root);return next}function post(action,fields){var body=new URLSearchParams();body.set("action",action);body.set("nonce",cfg.nonce);Object.keys(fields||{}).forEach(function(k){body.set(k,fields[k])});return fetch(cfg.ajaxUrl,{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/x-www-form-urlencoded; charset=UTF-8"},body:body.toString()}).then(function(res){return res.json().then(function(json){if(!res.ok||!json||!json.success){var msg=(json&&json.data&&json.data.message)||"Something went wrong. Please try again.";throw new Error(msg)}return json.data})})}function refreshFromServer(){var root=activeRoot();if(!root||refreshing)return;var title=root.getAttribute("data-title")||"Your Cart";refreshing=true;post("wchs_mini_cart_refresh",{title:title}).then(function(data){root=activeRoot()||root;if(data&&data.html)replaceRoot(root,data.html)}).catch(function(){}).then(function(){refreshing=false})}function scheduleRefreshFromCheckout(){if(refreshTimer)clearTimeout(refreshTimer);refreshTimer=setTimeout(function(){refreshTimer=null;refreshFromServer()},50)}function run(root,action,fields){if(!root||root.classList.contains("is-busy"))return;var title=root.getAttribute("data-title")||"Your Cart";fields=fields||{};fields.title=title;setBusy(root,true);post(action,fields).then(function(data){if(data&&data.html)replaceRoot(root,data.html);triggerCheckoutRefresh()}).catch(function(err){setBusy(root,false);var msgEl=root.querySelector("[data-coupon-msg]");if(msgEl){msgEl.hidden=false;msgEl.classList.add("is-error");msgEl.textContent=err.message||"Request failed."}else{window.alert(err.message||"Request failed.")}})}document.addEventListener("click",function(e){var btn=e.target&&e.target.closest?e.target.closest("[data-action]"):null;if(!btn)return;var root=rootFrom(btn);if(!root)return;var action=btn.getAttribute("data-action");if(action==="remove"){e.preventDefault();run(root,"wchs_mini_cart_remove",{key:btn.getAttribute("data-key")||""});return}if(action==="remove-coupon"){e.preventDefault();run(root,"wchs_mini_cart_remove_coupon",{code:btn.getAttribute("data-code")||""});return}if(action==="qty-inc"||action==="qty-dec"){e.preventDefault();var key=btn.getAttribute("data-key")||"";var current=parseInt(btn.getAttribute("data-qty")||"1",10);if(!key||!Number.isFinite(current))return;var nextQty=action==="qty-inc"?current+1:current-1;if(nextQty<1){run(root,"wchs_mini_cart_remove",{key:key})}else{run(root,"wchs_mini_cart_update_qty",{key:key,qty:String(nextQty)})}}});document.addEventListener("submit",function(e){var form=e.target&&e.target.closest?e.target.closest("[data-coupon-form]"):null;if(!form)return;var root=rootFrom(form);if(!root)return;e.preventDefault();var input=form.querySelector('input[name="coupon_code"]');var code=input?String(input.value||"").trim():"";var msgEl=root.querySelector("[data-coupon-msg]");if(msgEl){msgEl.hidden=true;msgEl.textContent=""}if(!code){if(msgEl){msgEl.hidden=false;msgEl.classList.add("is-error");msgEl.textContent="Enter a coupon code."}return}run(root,"wchs_mini_cart_apply_coupon",{code:code})});if(window.jQuery){window.jQuery(document.body).on("updated_checkout",scheduleRefreshFromCheckout)}})();
JS;
}

/**
 * Inline <style> once per request (safe inside Elementor HTML).
 */
function wchs_checkout_mini_cart_inline_css(): string {
	static $printed = false;
	if ( $printed ) {
		return '';
	}
	$printed = true;

	$css = wchs_checkout_mini_cart_read_asset( 'mini-cart.css' );
	if ( $css === '' ) {
		return '';
	}

	return '<style id="wchs-mini-cart-css">' . $css . '</style>';
}

/**
 * Inline config + JS once (Elementor-safe; also scheduled in footer as backup).
 */
function wchs_checkout_mini_cart_inline_js(): string {
	static $printed = false;
	if ( $printed ) {
		return '';
	}
	$printed = true;

	$js = wchs_checkout_mini_cart_read_asset( 'mini-cart.js' );
	if ( $js === '' ) {
		return '';
	}

	$config = wp_json_encode(
		[
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'wchs_mini_cart' ),
		]
	);

	return '<script id="wchs-mini-cart-config">window.wchsMiniCart=' . $config . ';</script>'
		. '<script id="wchs-mini-cart-js">' . $js . '</script>';
}

function wchs_checkout_mini_cart_schedule_assets(): void {
	static $done = false;
	if ( $done ) {
		return;
	}
	$done = true;

	add_action( 'wp_footer', 'wchs_checkout_mini_cart_print_footer_js', 50 );
}

function wchs_checkout_mini_cart_print_footer_js(): void {
	// Prefer shortcode-inline print; footer is a backup if Elementor strips body scripts.
	echo wchs_checkout_mini_cart_inline_css(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo wchs_checkout_mini_cart_inline_js(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

/**
 * @return bool
 */
function wchs_checkout_mini_cart_is_shipping_protection( array $line ): bool {
	if ( ! function_exists( 'wchs_shipping_protection_product_id' ) ) {
		return false;
	}
	$protect_id = wchs_shipping_protection_product_id();
	return $protect_id > 0 && (int) ( $line['product_id'] ?? 0 ) === $protect_id;
}

/**
 * Resolve shipping display from the same WC packages / chosen rates
 * FunnelKit uses (ShipStation live rates land here after update_checkout).
 *
 * @return array{html:string,is_free:bool,pending:bool,is_plain:bool}
 */
function wchs_checkout_mini_cart_shipping_display( WC_Cart $cart ): array {
	if ( ! $cart->needs_shipping() ) {
		return [
			'html'     => 'Free',
			'is_free'  => true,
			'pending'  => false,
			'is_plain' => true,
		];
	}

	$customer = function_exists( 'WC' ) ? WC()->customer : null;
	$has_dest = $customer instanceof WC_Customer
		&& (
			$customer->get_shipping_country() !== ''
			|| $customer->get_billing_country() !== ''
			|| $customer->get_shipping_postcode() !== ''
			|| $customer->get_billing_postcode() !== ''
		);

	if ( $has_dest && function_exists( 'WC' ) && WC()->shipping() ) {
		$packages = WC()->shipping()->get_packages();
		if ( empty( $packages ) ) {
			$cart->calculate_shipping();
			$packages = WC()->shipping()->get_packages();
		}
	} else {
		$packages = function_exists( 'WC' ) && WC()->shipping()
			? WC()->shipping()->get_packages()
			: [];
	}

	$chosen     = ( function_exists( 'WC' ) && WC()->session )
		? (array) WC()->session->get( 'chosen_shipping_methods', [] )
		: [];
	$cost       = 0.0;
	$has_rate   = false;
	$incl_tax   = $cart->display_prices_including_tax();

	foreach ( $packages as $i => $package ) {
		$method_id = isset( $chosen[ $i ] ) ? (string) $chosen[ $i ] : '';
		if ( $method_id === '' || empty( $package['rates'][ $method_id ] ) ) {
			continue;
		}
		/** @var WC_Shipping_Rate $rate */
		$rate = $package['rates'][ $method_id ];
		$has_rate = true;
		$cost    += (float) $rate->get_cost();
		if ( $incl_tax ) {
			$cost += (float) $rate->get_shipping_tax();
		}
	}

	if ( ! $has_rate ) {
		$shipping_total = (float) $cart->get_shipping_total();
		if ( $incl_tax ) {
			$shipping_total += (float) $cart->get_shipping_tax();
		}
		$chosen_any = array_filter( array_map( 'strval', $chosen ) );
		if ( empty( $chosen_any ) && $shipping_total <= 0.009 ) {
			return [
				'html'     => '—',
				'is_free'  => false,
				'pending'  => true,
				'is_plain' => true,
			];
		}
		if ( $shipping_total <= 0.009 ) {
			return [
				'html'     => 'Free',
				'is_free'  => true,
				'pending'  => false,
				'is_plain' => true,
			];
		}
		return [
			'html'     => wc_price( $shipping_total ),
			'is_free'  => false,
			'pending'  => false,
			'is_plain' => false,
		];
	}

	if ( $cost <= 0.009 ) {
		return [
			'html'     => 'Free',
			'is_free'  => true,
			'pending'  => false,
			'is_plain' => true,
		];
	}

	return [
		'html'     => wc_price( $cost ),
		'is_free'  => false,
		'pending'  => false,
		'is_plain' => false,
	];
}

/**
 * @return array{items:array<int,array<string,mixed>>,coupons:array<int,string>,subtotal:string,shipping:string,shipping_is_free:bool,shipping_is_plain:bool,shipping_pending:bool,total:string,savings_amount:string,savings_pct:int,empty:bool}
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

		$is_ship_protect = wchs_checkout_mini_cart_is_shipping_protection( $line );
		$is_free_bac     = ! empty( $line['wchs_free_bac_gift'] );

		$line_total = (float) ( $line['line_total'] ?? 0 );
		$regular    = (float) $product->get_regular_price();
		if ( $regular <= 0 && $product->is_type( 'variation' ) ) {
			$parent = wc_get_product( $product->get_parent_id() );
			if ( $parent ) {
				$regular = (float) $parent->get_regular_price();
			}
		}
		// Free gift uses set_price(0); pull catalog regular for the strikethrough.
		if ( $is_free_bac && $regular <= 0 && function_exists( 'wchs_bac_water_product_id' ) ) {
			$bac = wc_get_product( wchs_bac_water_product_id() );
			if ( $bac instanceof WC_Product ) {
				$regular = (float) $bac->get_regular_price();
			}
		}
		$compare_line = $regular > 0 ? $regular * $qty : $line_total;
		$has_compare  = $compare_line > $line_total + 0.009;

		$savings_minor += max( 0, $compare_line - $line_total );
		$compare_minor += max( $compare_line, $line_total );

		$items[] = [
			'key'                   => (string) $key,
			'name'                  => $name,
			'qty'                   => $qty,
			'thumb'                 => $thumb,
			'price'                 => $is_free_bac ? '' : wc_price( $line_total ),
			'price_label'           => $is_free_bac ? 'FREE' : '',
			'compare_price'         => $has_compare ? wc_price( $compare_line ) : '',
			'has_compare'           => $has_compare,
			'is_shipping_protection'=> $is_ship_protect,
			'is_free_bac_gift'      => $is_free_bac,
			'qty_editable'          => ! $is_ship_protect,
		];
	}

	$ship = wchs_checkout_mini_cart_shipping_display( $cart );

	$coupons = [];
	foreach ( $cart->get_applied_coupons() as $code ) {
		$code = (string) $code;
		// 2nd arg is $ex_tax: true = amount without tax (default Woo review table).
		$ex_tax = ! $cart->display_prices_including_tax();
		$amount = (float) $cart->get_coupon_discount_amount( $code, $ex_tax );
		if ( $amount < 0.009 && method_exists( $cart, 'get_coupon_discount_totals' ) ) {
			$totals_map = $cart->get_coupon_discount_totals();
			if ( isset( $totals_map[ $code ] ) ) {
				$amount = (float) $totals_map[ $code ];
			}
		}
		$coupons[] = [
			'code'          => $code,
			'discount_html' => '-' . wp_strip_all_tags( wc_price( $amount ) ),
			'amount'        => $amount,
		];
	}

	$savings_pct = $compare_minor > 0
		? (int) round( ( $savings_minor / $compare_minor ) * 100 )
		: 0;

	return [
		'items'              => $items,
		'coupons'            => $coupons,
		'subtotal'           => wc_price( (float) $cart->get_subtotal() ),
		'shipping'           => $ship['html'],
		'shipping_is_free'   => $ship['is_free'],
		'shipping_is_plain'  => $ship['is_plain'],
		'shipping_pending'   => $ship['pending'],
		'total'              => wc_price( (float) $cart->get_total( 'edit' ) ),
		'savings_amount'     => $savings_minor > 0.009 ? wc_price( $savings_minor ) : '',
		'savings_pct'        => $savings_pct,
		'empty'              => count( $items ) < 1,
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
						<?php
						$item_classes = 'wchs-mini-cart__item';
						if ( ! empty( $item['is_shipping_protection'] ) ) {
							$item_classes .= ' is-shipping-protection';
						}
						if ( ! empty( $item['is_free_bac_gift'] ) ) {
							$item_classes .= ' is-free-bac-gift';
						}
						?>
						<li class="<?php echo esc_attr( $item_classes ); ?>" data-key="<?php echo esc_attr( $item['key'] ); ?>">
							<div class="wchs-mini-cart__media">
								<img src="<?php echo esc_url( $item['thumb'] ); ?>" alt="" width="56" height="56" loading="lazy" />
								<span class="wchs-mini-cart__qty" aria-label="Quantity"><?php echo esc_html( (string) $item['qty'] ); ?></span>
							</div>
							<div class="wchs-mini-cart__info">
								<p class="wchs-mini-cart__name"><?php echo esc_html( $item['name'] ); ?></p>
								<?php if ( ! empty( $item['qty_editable'] ) ) : ?>
									<div class="wchs-mini-cart__stepper" role="group" aria-label="<?php echo esc_attr( sprintf( 'Quantity for %s', $item['name'] ) ); ?>">
										<button
											type="button"
											class="wchs-mini-cart__step"
											data-action="qty-dec"
											data-key="<?php echo esc_attr( $item['key'] ); ?>"
											data-qty="<?php echo esc_attr( (string) $item['qty'] ); ?>"
											aria-label="Decrease quantity"
										>−</button>
										<span class="wchs-mini-cart__step-val" aria-live="polite"><?php echo esc_html( (string) $item['qty'] ); ?></span>
										<button
											type="button"
											class="wchs-mini-cart__step"
											data-action="qty-inc"
											data-key="<?php echo esc_attr( $item['key'] ); ?>"
											data-qty="<?php echo esc_attr( (string) $item['qty'] ); ?>"
											aria-label="Increase quantity"
										>+</button>
									</div>
								<?php endif; ?>
							</div>
							<div class="wchs-mini-cart__aside">
								<div class="wchs-mini-cart__prices">
									<?php if ( $item['has_compare'] ) : ?>
										<span class="wchs-mini-cart__was"><?php echo wp_kses_post( $item['compare_price'] ); ?></span>
									<?php endif; ?>
									<?php if ( $item['price_label'] !== '' ) : ?>
										<span class="wchs-mini-cart__now wchs-mini-cart__now--free"><?php echo esc_html( $item['price_label'] ); ?></span>
									<?php else : ?>
										<span class="wchs-mini-cart__now"><?php echo wp_kses_post( $item['price'] ); ?></span>
									<?php endif; ?>
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

				<div class="wchs-mini-cart__totals">
					<div class="wchs-mini-cart__row">
						<span>Subtotal</span>
						<span><?php echo wp_kses_post( $data['subtotal'] ); ?></span>
					</div>
					<?php foreach ( $data['coupons'] as $coupon ) : ?>
						<div class="wchs-mini-cart__row wchs-mini-cart__row--coupon">
							<span class="wchs-mini-cart__coupon-label">
								<span class="wchs-mini-cart__coupon-label-text">Coupon</span>
								<span class="wchs-mini-cart__coupon-code" title="<?php echo esc_attr( $coupon['code'] ); ?>">
									<svg class="wchs-mini-cart__coupon-icon" viewBox="0 0 24 24" width="14" height="14" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
										<path d="M20.59 13.41 13.42 20.58a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z" />
										<circle cx="7.5" cy="7.5" r="1.5" fill="currentColor" stroke="none" />
									</svg>
									<?php echo esc_html( $coupon['code'] ); ?>
								</span>
							</span>
							<span class="wchs-mini-cart__coupon-value">
								<span class="wchs-mini-cart__coupon-amount"><?php echo esc_html( $coupon['discount_html'] ); ?></span>
								<button
									type="button"
									class="wchs-mini-cart__coupon-remove-link"
									data-action="remove-coupon"
									data-code="<?php echo esc_attr( $coupon['code'] ); ?>"
								>[Remove]</button>
							</span>
						</div>
					<?php endforeach; ?>
					<div class="wchs-mini-cart__row" data-shipping-row<?php echo ! empty( $data['shipping_pending'] ) ? ' data-shipping-pending' : ''; ?>>
						<span>Shipping</span>
						<span>
							<?php
							if ( ! empty( $data['shipping_is_plain'] ) ) {
								echo esc_html( $data['shipping'] );
							} else {
								echo wp_kses_post( $data['shipping'] );
							}
							?>
						</span>
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
		// Recalc packages so ShipStation / chosen rates feed get_shipping_total().
		WC()->cart->calculate_shipping();
		WC()->cart->calculate_totals();
	}
	if ( function_exists( 'wchs_push_classic_cart_to_bridged_store_api' ) ) {
		wchs_push_classic_cart_to_bridged_store_api();
	}
	if ( function_exists( 'WC' ) && WC()->session && method_exists( WC()->session, 'save_data' ) ) {
		WC()->session->save_data();
	}
	// Qty/BAC extras can leave sold-individually notices in the session that
	// FunnelKit surfaces on update_checkout — clear them for this AJAX path.
	if ( function_exists( 'wc_clear_notices' ) ) {
		wc_clear_notices();
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

function wchs_checkout_mini_cart_ajax_update_qty(): void {
	wchs_checkout_mini_cart_verify();
	$key = isset( $_POST['key'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['key'] ) ) : '';
	$qty = isset( $_POST['qty'] ) ? (int) $_POST['qty'] : 0;
	if ( $key === '' ) {
		wp_send_json_error( [ 'message' => 'Missing item.' ], 400 );
	}

	$cart_item = WC()->cart->get_cart_item( $key );
	if ( ! is_array( $cart_item ) || $cart_item === [] ) {
		wp_send_json_error( [ 'message' => 'Item not found.' ], 404 );
	}

	// Shipping protection is toggle-only — never change qty.
	if ( wchs_checkout_mini_cart_is_shipping_protection( $cart_item ) ) {
		wchs_checkout_mini_cart_ajax_html();
		return;
	}

	// Free BAC gift stays qty 1 / $0. Extra units become a separate paid line.
	if ( ! empty( $cart_item['wchs_free_bac_gift'] ) ) {
		if ( $qty < 1 ) {
			WC()->cart->remove_cart_item( $key );
		} elseif ( $qty > 1 ) {
			$extra = $qty - 1;
			if ( function_exists( 'wchs_bac_water_add_paid' ) ) {
				wchs_bac_water_add_paid( $extra );
			}
		}
		wchs_checkout_mini_cart_after_mutate();
		wchs_checkout_mini_cart_ajax_html();
		return;
	}

	if ( $qty < 1 ) {
		WC()->cart->remove_cart_item( $key );
	} else {
		WC()->cart->set_quantity( $key, $qty, true );
	}
	wchs_checkout_mini_cart_after_mutate();
	wchs_checkout_mini_cart_ajax_html();
}

function wchs_checkout_mini_cart_ajax_refresh(): void {
	wchs_checkout_mini_cart_verify();
	if ( function_exists( 'WC' ) && WC()->cart ) {
		WC()->cart->calculate_shipping();
		WC()->cart->calculate_totals();
	}
	wchs_checkout_mini_cart_ajax_html();
}

add_action( 'wp_ajax_wchs_mini_cart_remove', 'wchs_checkout_mini_cart_ajax_remove' );
add_action( 'wp_ajax_nopriv_wchs_mini_cart_remove', 'wchs_checkout_mini_cart_ajax_remove' );
add_action( 'wp_ajax_wchs_mini_cart_apply_coupon', 'wchs_checkout_mini_cart_ajax_apply_coupon' );
add_action( 'wp_ajax_nopriv_wchs_mini_cart_apply_coupon', 'wchs_checkout_mini_cart_ajax_apply_coupon' );
add_action( 'wp_ajax_wchs_mini_cart_remove_coupon', 'wchs_checkout_mini_cart_ajax_remove_coupon' );
add_action( 'wp_ajax_nopriv_wchs_mini_cart_remove_coupon', 'wchs_checkout_mini_cart_ajax_remove_coupon' );
add_action( 'wp_ajax_wchs_mini_cart_update_qty', 'wchs_checkout_mini_cart_ajax_update_qty' );
add_action( 'wp_ajax_nopriv_wchs_mini_cart_update_qty', 'wchs_checkout_mini_cart_ajax_update_qty' );
add_action( 'wp_ajax_wchs_mini_cart_refresh', 'wchs_checkout_mini_cart_ajax_refresh' );
add_action( 'wp_ajax_nopriv_wchs_mini_cart_refresh', 'wchs_checkout_mini_cart_ajax_refresh' );
