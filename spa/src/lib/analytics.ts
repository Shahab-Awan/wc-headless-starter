/**
 * Analytics helper — pushes GA4 ecommerce events to the dataLayer
 * for Google Tag Manager. GTM is loaded dynamically after config
 * provides the container ID.
 *
 * This file is the SPA's analytics adapter. It pushes standardized
 * GA4 ecommerce events (view_item, add_to_cart, etc.) that GTM
 * routes to whatever analytics destination the admin configured
 * (GA4, Meta Pixel, etc.) — all via the GTM dashboard, zero code.
 *
 * On WP-rendered pages (checkout, my-account, upsell), the GTM4WP
 * plugin handles ecommerce events natively. This file only covers
 * the SPA surface.
 */

import { config } from '$lib/config.svelte';
import type { StoreApiCart } from '$lib/wc/cart.svelte';
import type { StoreOrder } from '$lib/wc/orders';
import { priceAsNumber, type CurrencyMeta } from '$lib/utils/format';

declare global {
	interface Window {
		dataLayer: Record<string, unknown>[];
		omnisend: unknown[];
		klaviyo: unknown[];
		_learnq: unknown[];
		fbq: ((...args: unknown[]) => void) & { callMethod?: unknown; push?: unknown; loaded?: boolean; version?: string; queue?: unknown[] };
		_fbq: unknown;
		ttq: {
			load: (id: string) => void;
			page: () => void;
			track: (event: string, params?: Record<string, unknown>) => void;
			identify: (data: Record<string, unknown>) => void;
			methods?: string[];
			setAndDefer?: (target: unknown, method: string) => void;
			[key: string]: unknown;
		};
		TiktokAnalyticsObject: string;
		pintrk: ((...args: unknown[]) => void) & { queue?: unknown[]; version?: string };
		clarity: ((...args: unknown[]) => void) & { q?: unknown[] };
		hj: ((...args: unknown[]) => void) & { q?: unknown[] };
		_hjSettings: { hjid: number; hjsv: number };
		gtag: (...args: unknown[]) => void;
		_cl?: {
			pageview?: (eventName: string, properties: Record<string, unknown>) => void;
			trackClick?: (eventName: string, properties: Record<string, unknown>) => void;
			track?: (...args: unknown[]) => void;
		};
		CLabsgbVar?: { generalProps?: { uid?: string } };
	}
}

type EcomItem = {
	item_id: string;
	item_name: string;
	price: number;
	quantity?: number;
};

let initialized = false;
let omnisendInitialized = false;
let pendingOmnisendPushes: unknown[][] = [];

function drainOmnisendPendingInto(queue: { push(cmd: unknown[]): void }): void {
	if (!pendingOmnisendPushes.length) return;
	for (const command of pendingOmnisendPushes) {
		queue.push(command);
	}
	pendingOmnisendPushes = [];
}

function pushOmnisend(command: unknown[]): void {
	if (typeof window === 'undefined') return;
	const queue = window.omnisend;
	if (queue && typeof queue.push === 'function') {
		const canDrain =
			omnisendInitialized || Array.isArray(queue);
		if (pendingOmnisendPushes.length && canDrain) {
			drainOmnisendPendingInto(queue as { push(cmd: unknown[]): void });
		}
		queue.push(command);
		return;
	}
	pendingOmnisendPushes.push(command);
}

function flushPendingOmnisend(): void {
	if (typeof window === 'undefined' || !window.omnisend) return;
	const q = window.omnisend;
	if (typeof q.push !== 'function') return;
	drainOmnisendPendingInto(q as { push(cmd: unknown[]): void });
}

/**
 * Load Omnisend's on-site tracking + forms/popups/push launcher. Called
 * once after config provides the brand ID. When brandId is empty, no-ops
 * gracefully (the admin hasn't configured Omnisend yet).
 *
 * The launcher-v2 script handles:
 *   - tracker SDK (window.omnisend.push queue drain)
 *   - signup forms + popups (auto-injected from Omnisend dashboard config)
 *   - browser push (service worker registration if push enabled)
 *
 * We also push $pageViewed on the initial load — SPA navigations fire
 * additional $pageViewed events via trackOmnisendPageViewed below.
 */
export function initOmnisend(brandId: string): void {
	if (omnisendInitialized || !brandId || typeof window === 'undefined') return;
	omnisendInitialized = true;

	window.omnisend = window.omnisend || [];
	window.omnisend.push(['accountID', brandId]);
	window.omnisend.push(['track', '$pageViewed']);
	flushPendingOmnisend();

	const script = document.createElement('script');
	script.type = 'text/javascript';
	script.async = true;
	script.src = 'https://omnisnippet1.com/inshop/launcher-v2.js';
	const first = document.getElementsByTagName('script')[0];
	first?.parentNode?.insertBefore(script, first);
}

/**
 * Fire a $pageViewed event on SvelteKit navigations. Omnisend uses this
 * for session tracking and campaign attribution.
 */
export function trackOmnisendPageViewed(): void {
	if (typeof window === 'undefined' || !config.data.omnisend_brand_id) return;
	pushOmnisend(['track', '$pageViewed']);
}

/**
 * Product viewed (PDP). Omnisend uses this for browse-abandonment flows.
 */
export function trackOmnisendViewedProduct(product: {
	id: number;
	name: string;
	prices: { price: string; currency_minor_unit: number; currency_code: string };
	permalink?: string;
	images?: { src: string }[];
}): void {
	pushOmnisend([
		'track',
		'$productViewed',
		{
			$productID: String(product.id),
			$variantID: String(product.id),
			$title: product.name,
			$price: priceAsNumber(product.prices.price, product.prices),
			$currency: product.prices.currency_code || config.data.currency_code,
			$imageUrl: product.images?.[0]?.src || '',
			$productUrl: product.permalink || window.location.href,
		},
	]);
}

/**
 * Add-to-cart (fired from cart store alongside trackAddToCart).
 */
export function trackOmnisendAddedProductToCart(item: {
	id: number;
	variant_id?: number;
	name: string;
	price: string;
	currency_minor_unit: number;
	currency_code?: string;
	quantity: number;
	permalink?: string;
	image?: string;
}): void {
	const currency = item.currency_code || config.data.currency_code;
	pushOmnisend([
		'track',
		'$productAddedToCart',
		{
			$productID: String(item.id),
			$variantID: String(item.variant_id ?? item.id),
			$title: item.name,
			$price: priceAsNumber(item.price, item),
			$currency: currency,
			$quantity: item.quantity,
			$imageUrl: item.image || '',
			$productUrl: item.permalink || '',
		},
	]);
}

/**
 * Identify a contact by email (called from checkout on email field blur).
 * This ties an anonymous web session to an Omnisend contact so cart
 * abandonment emails can address them.
 */
export function identifyOmnisendContact(email: string, extras: { firstName?: string; lastName?: string; phone?: string } = {}): void {
	if (!email || !/.+@.+\..+/.test(email)) return;
	pushOmnisend([
		'identifyContact',
		{
			email,
			...(extras.firstName ? { firstName: extras.firstName } : {}),
			...(extras.lastName ? { lastName: extras.lastName } : {}),
			...(extras.phone ? { phone: extras.phone } : {}),
		},
	]);
}

/**
 * Placed-order event on the thank-you page. Omnisend uses this for
 * purchase attribution and to exit contacts from active cart-abandonment
 * automations.
 */
export function trackOmnisendPlacedOrder(order: {
	id: number;
	totals: { total_price: string; currency_code: string; currency_minor_unit: number };
	items: { id: number; name: string; quantity: number; totals: { line_total: string } }[];
	billing_address?: { email?: string; first_name?: string; last_name?: string };
}): void {
	const meta = order.totals;
	pushOmnisend([
		'track',
		'$placedOrder',
		{
			$orderID: String(order.id),
			$total: priceAsNumber(order.totals.total_price, meta),
			$currency: order.totals.currency_code,
			$email: order.billing_address?.email || '',
			$lineItems: order.items.map((li) => ({
				$productID: String(li.id),
				$title: li.name,
				$quantity: li.quantity,
				$price: priceAsNumber(li.totals.line_total, meta) / Math.max(1, li.quantity),
				$currency: order.totals.currency_code,
			})),
		},
	]);
}

/**
 * Load GTM dynamically. Called once after config provides the ID.
 */
export function initGTM(containerId: string): void {
	if (initialized || !containerId || typeof window === 'undefined') return;
	initialized = true;

	window.dataLayer = window.dataLayer || [];
	window.dataLayer.push({ 'gtm.start': new Date().getTime(), event: 'gtm.js' });

	const script = document.createElement('script');
	script.async = true;
	script.src = `https://www.googletagmanager.com/gtm.js?id=${containerId}`;
	document.head.appendChild(script);
}

/**
 * Push a page view. Call on every SvelteKit navigation.
 */
export function trackPageView(path: string, title?: string): void {
	window.dataLayer?.push({
		event: 'page_view',
		page_path: path,
		page_title: title || document.title,
	});
	trackCustomerLabsVirtualPageview(path, title || (typeof document !== 'undefined' ? document.title : ''));
}

/**
 * Product list viewed (shop page, homepage slider).
 */
export function trackViewItemList(
	listName: string,
	products: { id: number; name: string; prices: { price: string; currency_minor_unit: number } }[]
): void {
	window.dataLayer?.push({ ecommerce: null }); // clear previous
	window.dataLayer?.push({
		event: 'view_item_list',
		ecommerce: {
			item_list_name: listName,
			items: products.map((p, i) => ({
				item_id: String(p.id),
				item_name: p.name,
				price: priceAsNumber(p.prices.price, p.prices),
				index: i,
			})),
		},
	});
	trackCustomerLabsProductsListViewed(listName);
}

/**
 * Single product viewed (PDP).
 */
export function trackViewItem(product: {
	id: number;
	name: string;
	prices: { price: string; currency_minor_unit: number; currency_code?: string };
	permalink?: string;
	images?: { src: string }[];
}): void {
	window.dataLayer?.push({ ecommerce: null });
	window.dataLayer?.push({
		event: 'view_item',
		ecommerce: {
			items: [{
				item_id: String(product.id),
				item_name: product.name,
				price: priceAsNumber(product.prices.price, product.prices),
			}],
		},
	});
	trackCustomerLabsProductViewed(product);
}

/**
 * Item added to cart.
 */
export function trackAddToCart(item: {
	id: number;
	name: string;
	price: string;
	currency_minor_unit: number;
	currency_code?: string;
	quantity: number;
	permalink?: string;
	image?: string;
	clicked_from?: string;
}): void {
	window.dataLayer?.push({ ecommerce: null });
	window.dataLayer?.push({
		event: 'add_to_cart',
		ecommerce: {
			items: [{
				item_id: String(item.id),
				item_name: item.name,
				price: priceAsNumber(item.price, item),
				quantity: item.quantity,
			}],
		},
	});
	trackCustomerLabsAddedToCart(item);
}

/**
 * Item removed from cart.
 */
export function trackRemoveFromCart(item: {
	id: number;
	name: string;
	price: string;
	currency_minor_unit: number;
	quantity: number;
}): void {
	window.dataLayer?.push({ ecommerce: null });
	window.dataLayer?.push({
		event: 'remove_from_cart',
		ecommerce: {
			items: [{
				item_id: String(item.id),
				item_name: item.name,
				price: priceAsNumber(item.price, item),
				quantity: item.quantity,
			}],
		},
	});
}

/**
 * Purchase completed. Fire from the SPA /order-received page after
 * order data loads. GTM consumers (Meta Pixel, Google Ads, TikTok
 * Pixel, Omnisend client-side, Klaviyo) listen for this event to
 * attribute conversions.
 *
 * Shape follows GA4 ecommerce convention:
 *   event: 'purchase'
 *   ecommerce: { transaction_id, value, currency, items[] }
 */
export function trackPurchase(order: StoreOrder): void {
	const meta = order.totals;
	window.dataLayer?.push({ ecommerce: null });
	window.dataLayer?.push({
		event: 'purchase',
		ecommerce: {
			transaction_id: String(order.id),
			value: priceAsNumber(order.totals.total_price, meta),
			currency: order.totals.currency_code,
			items: order.items.map((li) => {
				const lineTotal = priceAsNumber(li.totals.line_total, meta);
				return {
					item_id: String(li.id),
					item_name: li.name,
					price: li.quantity > 0 ? lineTotal / li.quantity : lineTotal,
					quantity: li.quantity,
				};
			}),
		},
	});
	trackCustomerLabsPurchased(order);
}

// ════════════════════════════════════════════════════════════════════════
// Third-party ad pixels & session trackers
// Each init() is idempotent and no-ops when its ID is empty. Fire functions
// safe-guard against missing globals so calling them before (or without)
// init just silently skips.
// ════════════════════════════════════════════════════════════════════════

type PixelProduct = {
	id: number;
	name: string;
	prices: { price: string; currency_minor_unit: number; currency_code?: string };
	permalink?: string;
	images?: { src: string }[];
};
type PixelCartItem = {
	id: number;
	name: string;
	price: string;
	currency_minor_unit: number;
	quantity: number;
	permalink?: string;
	image?: string;
};
type PixelOrder = {
	id: number;
	totals: { total_price: string; currency_code: string; currency_minor_unit: number };
	items: { id: number; name: string; quantity: number; totals: { line_total: string } }[];
	billing_address?: { email?: string; first_name?: string; last_name?: string };
};

// ── Klaviyo ────────────────────────────────────────────────────────────
// Onsite queue API. New snippet uses `window.klaviyo`; legacy `_learnq` also
// works and is what many templates still push to, so we push to both.
let klaviyoInitialized = false;
export function initKlaviyo(publicKey: string): void {
	if (klaviyoInitialized || !publicKey || typeof window === 'undefined') return;
	klaviyoInitialized = true;
	window.klaviyo = window.klaviyo || [];
	window._learnq = window._learnq || [];
	const s = document.createElement('script');
	s.async = true;
	s.src = `https://static.klaviyo.com/onsite/js/klaviyo.js?company_id=${encodeURIComponent(publicKey)}`;
	document.head.appendChild(s);
}
function klaviyoPush(args: unknown[]): void {
	window.klaviyo?.push(args);
	window._learnq?.push(args);
}
export function trackKlaviyoViewedProduct(p: PixelProduct): void {
	klaviyoPush(['track', 'Viewed Product', {
		ProductName: p.name,
		ProductID: String(p.id),
		Categories: [],
		ImageURL: p.images?.[0]?.src || '',
		URL: p.permalink || (typeof window !== 'undefined' ? window.location.href : ''),
		Price: priceAsNumber(p.prices.price, p.prices),
	}]);
}
export function trackKlaviyoAddedToCart(i: PixelCartItem): void {
	klaviyoPush(['track', 'Added to Cart', {
		AddedItemProductName: i.name,
		AddedItemProductID: String(i.id),
		AddedItemPrice: priceAsNumber(i.price, i),
		AddedItemQuantity: i.quantity,
		AddedItemImageURL: i.image || '',
		AddedItemURL: i.permalink || '',
	}]);
}
export function identifyKlaviyoContact(email: string, extras: { first_name?: string; last_name?: string; phone?: string } = {}): void {
	if (!email || !/.+@.+\..+/.test(email)) return;
	klaviyoPush(['identify', {
		$email: email,
		...(extras.first_name ? { $first_name: extras.first_name } : {}),
		...(extras.last_name ? { $last_name: extras.last_name } : {}),
		...(extras.phone ? { $phone_number: extras.phone } : {}),
	}]);
}
export function trackKlaviyoPlacedOrder(o: PixelOrder): void {
	const meta = o.totals;
	const total = priceAsNumber(o.totals.total_price, meta);
	klaviyoPush(['track', 'Placed Order', {
		$event_id: String(o.id),
		$value: total,
		Categories: [],
		ItemNames: o.items.map((li) => li.name),
		Items: o.items.map((li) => ({
			ProductID: String(li.id),
			ProductName: li.name,
			Quantity: li.quantity,
			ItemPrice: priceAsNumber(li.totals.line_total, meta) / Math.max(1, li.quantity),
			RowTotal: priceAsNumber(li.totals.line_total, meta),
		})),
	}]);
}

// ── Meta Pixel (Facebook / Instagram) ──────────────────────────────────
let metaInitialized = false;
export function initMetaPixel(pixelId: string): void {
	if (metaInitialized || !pixelId || typeof window === 'undefined') return;
	metaInitialized = true;
	// Standard Meta snippet — upstream-maintained shim
	/* eslint-disable */
	// @ts-expect-error — Meta shim defines fbq/n properties dynamically
	!(function (f: any, b: any, e: any, v: any, n: any, t: any, s: any) { if (f.fbq) return; n = f.fbq = function () { n.callMethod ? n.callMethod.apply(n, arguments) : n.queue.push(arguments) }; if (!f._fbq) f._fbq = n; n.push = n; n.loaded = !0; n.version = '2.0'; n.queue = []; t = b.createElement(e); t.async = !0; t.src = v; s = b.getElementsByTagName(e)[0]; s.parentNode.insertBefore(t, s) })(window, document, 'script', 'https://connect.facebook.net/en_US/fbevents.js');
	/* eslint-enable */
	window.fbq('init', pixelId);
	window.fbq('track', 'PageView');
}
export function trackMetaViewContent(p: PixelProduct): void {
	window.fbq?.('track', 'ViewContent', {
		content_ids: [String(p.id)],
		content_name: p.name,
		content_type: 'product',
		value: priceAsNumber(p.prices.price, p.prices),
		currency: p.prices.currency_code || 'USD',
	});
}
export function trackMetaAddToCart(i: PixelCartItem): void {
	window.fbq?.('track', 'AddToCart', {
		content_ids: [String(i.id)],
		content_name: i.name,
		content_type: 'product',
		value: priceAsNumber(i.price, i) * i.quantity,
		currency: 'USD',
	});
}
export function trackMetaInitiateCheckout(cartTotalCents: number, itemCount: number): void {
	window.fbq?.('track', 'InitiateCheckout', {
		num_items: itemCount,
		value: cartTotalCents / 100,
		currency: 'USD',
	});
}
export function trackMetaPurchase(o: PixelOrder): void {
	const meta = o.totals;
	window.fbq?.('track', 'Purchase', {
		content_ids: o.items.map((li) => String(li.id)),
		content_type: 'product',
		value: priceAsNumber(o.totals.total_price, meta),
		currency: o.totals.currency_code,
		num_items: o.items.reduce((n, li) => n + li.quantity, 0),
	});
}

// ── TikTok Pixel ───────────────────────────────────────────────────────
let tiktokInitialized = false;
export function initTikTokPixel(pixelId: string): void {
	if (tiktokInitialized || !pixelId || typeof window === 'undefined') return;
	tiktokInitialized = true;
	/* eslint-disable */
	(function (w: any, d: any, t: any) { w.TiktokAnalyticsObject = t; const ttq = w[t] = w[t] || []; ttq.methods = ['page', 'track', 'identify', 'instances', 'debug', 'on', 'off', 'once', 'ready', 'alias', 'group', 'enableCookie', 'disableCookie']; ttq.setAndDefer = function (t2: any, e: string) { t2[e] = function () { t2.push([e].concat(Array.prototype.slice.call(arguments, 0))) } }; for (let i = 0; i < ttq.methods.length; i++) ttq.setAndDefer(ttq, ttq.methods[i]); ttq.instance = function (t2: any) { const e = ttq._i[t2] || []; for (let n = 0; n < ttq.methods.length; n++) ttq.setAndDefer(e, ttq.methods[n]); return e }; ttq.load = function (e: any, n: any) { const r = 'https://analytics.tiktok.com/i18n/pixel/events.js'; ttq._i = ttq._i || {}; ttq._i[e] = []; ttq._i[e]._u = r; ttq._t = ttq._t || {}; ttq._t[e] = +new Date(); ttq._o = ttq._o || {}; ttq._o[e] = n || {}; const o = document.createElement('script'); o.type = 'text/javascript'; o.async = true; o.src = r + '?sdkid=' + e + '&lib=' + t; const a = document.getElementsByTagName('script')[0]; if (a?.parentNode) a.parentNode.insertBefore(o, a); else d.head.appendChild(o); } })(window, document, 'ttq');
	/* eslint-enable */
	window.ttq.load(pixelId);
	window.ttq.page();
}
export function trackTikTokViewContent(p: PixelProduct): void {
	window.ttq?.track('ViewContent', {
		contents: [{ content_id: String(p.id), content_name: p.name, price: priceAsNumber(p.prices.price, p.prices), quantity: 1 }],
		value: priceAsNumber(p.prices.price, p.prices),
		currency: p.prices.currency_code || 'USD',
	});
}
export function trackTikTokAddToCart(i: PixelCartItem): void {
	window.ttq?.track('AddToCart', {
		contents: [{ content_id: String(i.id), content_name: i.name, price: priceAsNumber(i.price, i), quantity: i.quantity }],
		value: priceAsNumber(i.price, i) * i.quantity,
		currency: 'USD',
	});
}
export function trackTikTokInitiateCheckout(totalCents: number, itemCount: number): void {
	window.ttq?.track('InitiateCheckout', { value: totalCents / 100, currency: 'USD', contents: Array(itemCount).fill({}) });
}
export function trackTikTokCompletePayment(o: PixelOrder): void {
	const meta = o.totals;
	window.ttq?.track('CompletePayment', {
		contents: o.items.map((li) => ({
			content_id: String(li.id),
			content_name: li.name,
			quantity: li.quantity,
			price: priceAsNumber(li.totals.line_total, meta) / Math.max(1, li.quantity),
		})),
		value: priceAsNumber(o.totals.total_price, meta),
		currency: o.totals.currency_code,
	});
}
export function identifyTikTokContact(email: string, phone?: string): void {
	if (!email && !phone) return;
	window.ttq?.identify({
		...(email ? { email } : {}),
		...(phone ? { phone_number: phone } : {}),
	});
}

// ── Pinterest Tag ──────────────────────────────────────────────────────
let pinterestInitialized = false;
export function initPinterestTag(tagId: string): void {
	if (pinterestInitialized || !tagId || typeof window === 'undefined') return;
	pinterestInitialized = true;
	/* eslint-disable */
	((e: string) => {
		if (!window.pintrk) {
			// @ts-expect-error — upstream-maintained shim
			window.pintrk = function () { window.pintrk.queue.push(Array.prototype.slice.call(arguments)) };
			const n = window.pintrk as any; n.queue = []; n.version = '3.0';
			const t = document.createElement('script'); t.async = true; t.src = e;
			const r = document.getElementsByTagName('script')[0];
			if (r?.parentNode) r.parentNode.insertBefore(t, r); else document.head.appendChild(t);
		}
	})('https://s.pinimg.com/ct/core.js');
	/* eslint-enable */
	window.pintrk('load', tagId);
	window.pintrk('page');
}
export function trackPinterestAddToCart(i: PixelCartItem): void {
	window.pintrk?.('track', 'addtocart', {
		value: priceAsNumber(i.price, i) * i.quantity,
		order_quantity: i.quantity,
		currency: 'USD',
		line_items: [{ product_name: i.name, product_id: String(i.id), product_price: priceAsNumber(i.price, i), product_quantity: i.quantity }],
	});
}
export function trackPinterestCheckout(o: PixelOrder): void {
	const meta = o.totals;
	window.pintrk?.('track', 'checkout', {
		value: priceAsNumber(o.totals.total_price, meta),
		order_quantity: o.items.reduce((n, li) => n + li.quantity, 0),
		currency: o.totals.currency_code,
		order_id: String(o.id),
		line_items: o.items.map((li) => ({
			product_name: li.name,
			product_id: String(li.id),
			product_price: priceAsNumber(li.totals.line_total, meta) / Math.max(1, li.quantity),
			product_quantity: li.quantity,
		})),
	});
}

// ── Microsoft Clarity ──────────────────────────────────────────────────
let clarityInitialized = false;
export function initClarity(projectId: string): void {
	if (clarityInitialized || !projectId || typeof window === 'undefined') return;
	clarityInitialized = true;
	/* eslint-disable */
	(function (c: any, l: Document, a: string, r: string, i: string) { c[a] = c[a] || function () { (c[a].q = c[a].q || []).push(arguments) }; const t: any = l.createElement(r); t.async = 1; t.src = 'https://www.clarity.ms/tag/' + i; const y = l.getElementsByTagName(r)[0]; y.parentNode?.insertBefore(t, y) })(window, document, 'clarity', 'script', projectId);
	/* eslint-enable */
}
export function identifyClarityContact(email: string): void {
	if (!email) return;
	window.clarity?.('identify', email);
}

// ── Hotjar ─────────────────────────────────────────────────────────────
let hotjarInitialized = false;
export function initHotjar(siteId: string): void {
	if (hotjarInitialized || !siteId || typeof window === 'undefined') return;
	const n = Number(siteId);
	if (!Number.isFinite(n) || n <= 0) return;
	hotjarInitialized = true;
	/* eslint-disable */
	(function (h: any, o: Document, t: string, j: string) { h.hj = h.hj || function () { (h.hj.q = h.hj.q || []).push(arguments) }; h._hjSettings = { hjid: n, hjsv: 6 }; const a = o.getElementsByTagName('head')[0]; const r = o.createElement('script'); r.async = true; r.src = t + h._hjSettings.hjid + j + h._hjSettings.hjsv; a.appendChild(r) })(window, document, 'https://static.hotjar.com/c/hotjar-', '.js?sv=');
	/* eslint-enable */
}
export function identifyHotjarContact(userId: string, attrs: Record<string, string | number> = {}): void {
	if (!userId) return;
	window.hj?.('identify', userId, attrs);
}

// ── Google Ads Conversion ──────────────────────────────────────────────
// Uses gtag, which GTM may also load; both sharing window.dataLayer is safe.
let googleAdsInitialized = false;
export function initGoogleAds(conversionId: string): void {
	if (googleAdsInitialized || !conversionId || typeof window === 'undefined') return;
	googleAdsInitialized = true;
	window.dataLayer = window.dataLayer || [];
	// @ts-expect-error — upstream-maintained shim
	window.gtag = window.gtag || function () { window.dataLayer.push(arguments); };
	window.gtag('js', new Date());
	window.gtag('config', conversionId);
	// Load gtag.js if GTM hasn't already loaded it
	if (!document.querySelector(`script[src*="gtag/js?id="]`)) {
		const s = document.createElement('script');
		s.async = true;
		s.src = `https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(conversionId)}`;
		document.head.appendChild(s);
	}
}
export function trackGoogleAdsConversion(o: PixelOrder, conversionId: string, conversionLabel: string): void {
	if (!conversionId || !conversionLabel) return;
	const meta = o.totals;
	window.gtag?.('event', 'conversion', {
		send_to: `${conversionId}/${conversionLabel}`,
		value: priceAsNumber(o.totals.total_price, meta),
		currency: o.totals.currency_code,
		transaction_id: String(o.id),
	});
}

// ── CustomerLabs 1PD Ops (_cl) ─────────────────────────────────────────
// https://customerlabs.com/docs/website-event-tracking/developer-documentation/javascript-api-documentation/
// Event shapes use typed { t, v } fields per their API. No-ops when the
// CustomerLabs script is not installed (Site Scripts → CustomerLabs off).

type CLScalar = { t: 'string' | 'number'; v: string };

const CL_ZERO_DECIMAL = new Set([
	'BIF', 'CLP', 'DJF', 'GNF', 'ISK', 'JPY', 'KMF', 'KRW', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF',
]);
const CL_THREE_DECIMAL = new Set(['BHD', 'IQD', 'JOD', 'KWD', 'LYD', 'OMR', 'TND']);

function clResolveMinorUnit(explicit: number | undefined | null, currencyCode: string): number {
	if (typeof explicit === 'number' && Number.isFinite(explicit) && explicit >= 0 && explicit <= 4) {
		return Math.round(explicit);
	}
	const c = (currencyCode || 'USD').toUpperCase();
	if (CL_ZERO_DECIMAL.has(c)) return 0;
	if (CL_THREE_DECIMAL.has(c)) return 3;
	return 2;
}

function clStr(v: string): CLScalar {
	const s = v.replace(/[\u0000-\u001F<>"'`\\]/g, ' ').trim();
	return { t: 'string', v: s.slice(0, 2000) };
}

function clNumMajor(minorStr: string, meta: Pick<CurrencyMeta, 'currency_minor_unit'>): CLScalar {
	const n = priceAsNumber(minorStr, meta);
	const d = Math.min(4, Math.max(0, Math.round(meta.currency_minor_unit ?? 2)));
	const v = Number.isFinite(n) ? n.toFixed(d) : '0';
	return { t: 'number', v };
}

function clMajorFromMinor(
	minorStr: string,
	currencyCode: string,
	explicitMinor?: number | null,
): CLScalar {
	const mu = clResolveMinorUnit(explicitMinor, currencyCode);
	return clNumMajor(minorStr, { currency_minor_unit: mu });
}

function clPageUrl(): CLScalar {
	if (typeof window === 'undefined') return { t: 'string', v: '' };
	return clStr(window.location.href.split('#')[0]);
}

/** CustomerLabs drops customProperties if fired before the snippet uid is ready. */
function whenCustomerLabsReady(run: () => void, maxAttempts = 50): void {
	if (typeof window === 'undefined') return;
	let attempts = 0;
	const tick = () => {
		const hasApi = typeof window._cl?.trackClick === 'function';
		const hasUid = Boolean(window.CLabsgbVar?.generalProps?.uid);
		if ((hasApi && hasUid) || attempts >= maxAttempts) {
			if (hasApi) run();
			return;
		}
		attempts += 1;
		setTimeout(tick, 100);
	};
	tick();
}

function clCurrencyCode(code?: string): string {
	return (code || config.data.currency_code || 'USD').toUpperCase();
}

/** event_attributes for Product viewed / Added to cart (not product_traits). */
function clProductCustomAttributes(p: {
	pageUrl: string;
	currency: string;
	priceMinor: string;
	currency_minor_unit: number;
	name: string;
}): Record<string, CLScalar> {
	const currency = clCurrencyCode(p.currency);
	return {
		page_url: clStr(p.pageUrl),
		product_name: clStr(p.name),
		product_price: clMajorFromMinor(p.priceMinor, currency, p.currency_minor_unit),
		currency: clStr(currency),
	};
}

function clCheckoutCustomAttributes(cart: StoreApiCart): Record<string, CLScalar> {
	const currency = clCurrencyCode(cart.totals.currency_code);
	const mu = cart.totals.currency_minor_unit;
	return {
		page_url: clPageUrl(),
		currency: clStr(currency),
		value: clMajorFromMinor(cart.totals.total_price, currency, mu),
		subtotal: clMajorFromMinor(cart.totals.total_items, currency, mu),
		shipping: clMajorFromMinor(cart.totals.total_shipping, currency, mu),
		items_count: { t: 'number', v: String(cart.items_count) },
	};
}

function clPurchasedCustomAttributes(order: StoreOrder): Record<string, CLScalar> {
	const t = order.totals;
	const currency = clCurrencyCode(t.currency_code);
	return {
		order_id: clStr(String(order.id)),
		currency: clStr(currency),
		value: clMajorFromMinor(t.total_price, currency, t.currency_minor_unit),
	};
}

function clProductPropsRow(p: {
	id: number;
	name: string;
	priceMinor: string;
	currency_minor_unit?: number | null;
	currency_code: string;
	quantity?: number;
	image?: string;
	variantLabel?: string;
}): Record<string, CLScalar> {
	const row: Record<string, CLScalar> = {
		product_id: { t: 'number', v: String(p.id) },
		product_name: clStr(p.name),
		product_price: clMajorFromMinor(p.priceMinor, p.currency_code, p.currency_minor_unit),
	};
	if (p.quantity != null) row.product_quantity = { t: 'number', v: String(Math.max(0, Math.round(p.quantity))) };
	if (p.image) row.product_image = clStr(p.image);
	if (p.variantLabel) row.product_variant = clStr(p.variantLabel);
	return row;
}

let customerLabsNavigationCount = 0;

/** SPA client navigations — first shell load relies on CustomerLabs’ default pageview. */
export function trackCustomerLabsVirtualPageview(path: string, title: string): void {
	if (typeof window === 'undefined') return;
	customerLabsNavigationCount++;
	if (customerLabsNavigationCount <= 1) return;
	const href =
		`${window.location.origin}${path.startsWith('/') ? path : `/${path}`}`.split('#')[0];
	window._cl?.pageview?.('Page viewed', {
		customProperties: {
			page_url: clStr(href),
			page_title: clStr(title || document.title || path),
		},
	});
}

export function trackCustomerLabsProductsListViewed(listName: string): void {
	if (typeof window === 'undefined') return;
	window._cl?.pageview?.('Products list viewed', {
		customProperties: {
			page_url: clPageUrl(),
			category_name: clStr(listName),
		},
	});
}

export function trackCustomerLabsProductViewed(product: {
	id: number;
	name: string;
	prices: { price: string; currency_minor_unit: number; currency_code?: string };
	permalink?: string;
	images?: { src: string }[];
}): void {
	if (typeof window === 'undefined') return;
	const pageUrl = product.permalink || window.location.href.split('#')[0];
	const currency = clCurrencyCode(product.prices.currency_code);

	whenCustomerLabsReady(() => {
		window._cl?.trackClick?.('Product viewed', {
			productProperties: [
				clProductPropsRow({
					id: product.id,
					name: product.name,
					priceMinor: product.prices.price,
					currency_minor_unit: product.prices.currency_minor_unit,
					currency_code: currency,
					quantity: 1,
					image: product.images?.[0]?.src,
				}),
			],
			customProperties: clProductCustomAttributes({
				pageUrl,
				currency,
				priceMinor: product.prices.price,
				currency_minor_unit: product.prices.currency_minor_unit,
				name: product.name,
			}),
		});
	});
}

export function trackCustomerLabsProductClickedFromListing(p: {
	id: number;
	name: string;
	slug: string;
	prices: { price: string; currency_minor_unit: number; currency_code?: string };
	permalink: string;
	image?: string;
	listingSource: string;
}): void {
	if (typeof window === 'undefined') return;
	const pageUrl = p.permalink || `${window.location.origin}/product/${p.slug}`;
	const currency = clCurrencyCode(p.prices.currency_code);

	whenCustomerLabsReady(() => {
		window._cl?.trackClick?.('Product clicked', {
			productProperties: [
				clProductPropsRow({
					id: p.id,
					name: p.name,
					priceMinor: p.prices.price,
					currency_minor_unit: p.prices.currency_minor_unit,
					currency_code: currency,
					quantity: 1,
					image: p.image,
				}),
			],
			customProperties: {
				...clProductCustomAttributes({
					pageUrl,
					currency,
					priceMinor: p.prices.price,
					currency_minor_unit: p.prices.currency_minor_unit,
					name: p.name,
				}),
				clicked_from: clStr(p.listingSource),
			},
		});
	});
}

export function trackCustomerLabsAddedToCart(item: {
	id: number;
	name: string;
	price: string;
	currency_minor_unit: number;
	currency_code?: string;
	quantity: number;
	permalink?: string;
	image?: string;
	clicked_from?: string;
}): void {
	if (typeof window === 'undefined') return;
	const pageUrl = item.permalink || window.location.href.split('#')[0];
	const currency = clCurrencyCode(item.currency_code);
	const qty = Math.max(1, Math.round(item.quantity));

	whenCustomerLabsReady(() => {
		window._cl?.trackClick?.('Added to cart', {
			productProperties: [
				clProductPropsRow({
					id: item.id,
					name: item.name,
					priceMinor: item.price,
					currency_minor_unit: item.currency_minor_unit,
					currency_code: currency,
					quantity: qty,
					image: item.image,
				}),
			],
			customProperties: clProductCustomAttributes({
				pageUrl,
				currency,
				priceMinor: item.price,
				currency_minor_unit: item.currency_minor_unit,
				name: item.name,
			}),
		});
	});
}

export function trackCustomerLabsCheckoutMade(cart: StoreApiCart): void {
	if (typeof window === 'undefined' || !cart.items?.length) return;
	const currency = clCurrencyCode(cart.totals.currency_code);

	const productProperties = cart.items.map((li) => {
		const variantLabel =
			li.variation?.map((v) => `${v.attribute}: ${v.value}`).join(', ') || undefined;
		const lineCurrency = clCurrencyCode(li.prices.currency_code || currency);
		return clProductPropsRow({
			id: li.id,
			name: li.name,
			priceMinor: li.prices.price,
			currency_minor_unit: li.prices.currency_minor_unit,
			currency_code: lineCurrency,
			quantity: li.quantity,
			image: li.images?.[0]?.src,
			variantLabel,
		});
	});

	whenCustomerLabsReady(() => {
		window._cl?.trackClick?.('Checkout made', {
			productProperties,
			customProperties: clCheckoutCustomAttributes(cart),
		});
	});
}

export function trackCustomerLabsPurchased(order: StoreOrder): void {
	if (typeof window === 'undefined') return;
	const t = order.totals;
	const currency = clCurrencyCode(t.currency_code);

	const productProperties = order.items.map((li) => {
		const qty = Math.max(1, li.quantity);
		const unitMinor = String(Math.round((Number(li.totals.line_total) || 0) / qty));
		return clProductPropsRow({
			id: li.id,
			name: li.name,
			priceMinor: unitMinor,
			currency_minor_unit: t.currency_minor_unit,
			currency_code: currency,
			quantity: li.quantity,
			image: li.images?.[0]?.src,
		});
	});

	const customProperties = clPurchasedCustomAttributes(order);
	const dedupeKey = `wchs_cl_purchased_${order.id}`;

	const fire = () => {
		try {
			if (sessionStorage.getItem(dedupeKey)) return;
		} catch {
			/* private mode */
		}
		window._cl?.trackClick?.('Purchased', { productProperties, customProperties });
		try {
			sessionStorage.setItem(dedupeKey, '1');
		} catch {
			/* private mode */
		}
	};

	// Thank-you page: wait longer for the CustomerLabs snippet (per their docs).
	whenCustomerLabsReady(fire, 150);
}
