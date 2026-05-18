/**
 * Cart store — Svelte 5 runes.
 *
 * Single source of truth is whatever GET /wc/store/v1/cart returns. Every
 * mutation refetches. Cross-tab sync via storage event.
 *
 * Shadow-cart backing: every successful mutation mirrors the line items
 * to localStorage (see shadow-cart.ts). On fetch, if the server cart is
 * empty but the shadow has items, we treat that as silent token expiry
 * and replay the adds. Users don't lose their cart after 48h.
 */

import { request, currentCartToken, primeSession } from './store-api';
import {
	readShadow,
	writeShadow,
	clearShadow,
	itemsMissingFromActive,
	type ShadowItem
} from './shadow-cart';
import { config } from '../config.svelte';

/**
 * Shape of the wchs_cro extension injected by the headless-cro-extension
 * mu-plugin on each cart line. All monetary fields are integer minor
 * units (cents) — matches Store API conventions.
 */
export type WchsCroNextTier = {
	qty_needed: number;
	next_min_qty: number;
	next_unit_price: number;
	next_savings_pct: number;
	additional_savings_per_unit: number;
};

export type WchsCroCartItem = {
	regular_unit_price: number;
	effective_unit_price: number;
	savings_per_unit: number;
	savings_line_total: number;
	savings_pct: number;
	next_tier: WchsCroNextTier | null;
	cross_sell_ids: number[];
};

export type WchsCroCartTop = {
	total_savings: number;
	cross_sell_ids: number[];
};

export type StoreApiCartItem = {
	key: string;
	id: number;
	quantity: number;
	name: string;
	permalink: string;
	images: { src: string; thumbnail: string; alt: string }[];
	prices: {
		price: string;
		regular_price: string;
		sale_price: string;
		price_range: null | { min_amount: string; max_amount: string };
		currency_code: string;
		currency_symbol: string;
		currency_minor_unit: number;
	};
	totals: {
		line_subtotal: string;
		line_subtotal_tax: string;
		line_total: string;
		line_total_tax: string;
	};
	quantity_limits: { minimum: number; maximum: number; multiple_of: number; editable: boolean };
	variation: { attribute: string; value: string }[];
	sold_individually: boolean;
	extensions?: {
		wchs_cro?: WchsCroCartItem;
	};
};

export type StoreApiCart = {
	coupons: unknown[];
	shipping_rates: unknown[];
	items: StoreApiCartItem[];
	items_count: number;
	items_weight: number;
	needs_payment: boolean;
	needs_shipping: boolean;
	has_calculated_shipping: boolean;
	totals: {
		total_items: string;
		total_items_tax: string;
		total_fees: string;
		total_fees_tax: string;
		total_discount: string;
		total_discount_tax: string;
		total_shipping: string;
		total_shipping_tax: string;
		total_price: string;
		total_tax: string;
		tax_lines: unknown[];
		currency_code: string;
		currency_symbol: string;
		currency_minor_unit: number;
	};
	errors: unknown[];
	payment_methods: string[];
	extensions: {
		wchs_cro?: WchsCroCartTop;
		[key: string]: unknown;
	};
};

class CartStore {
	cart = $state<StoreApiCart | null>(null);
	loading = $state(false);
	error = $state<string | null>(null);
	open = $state(false);
	restored = $state(false); // true if shadow replay fired on last fetch

	/**
	 * Monotonic generation counter for cart mutations. Still used for
	 * convergence fetch ordering — so a stale GET /cart from an earlier
	 * mutation can't clobber the latest state.
	 */
	private generation = 0;

	/**
	 * Mutation mutex. Cart writes must serialize because the Store API's
	 * session cart isn't strictly transactional across concurrent writes
	 * to the same session — two POSTs /cart/add-item from the same
	 * session can race and clobber each other (each reads the empty cart,
	 * adds their item, writes back). This is a server-side property we
	 * cannot fix from the client; the only safe fix is to queue writes.
	 *
	 * Reads (GET /cart) do not need the mutex. Only writes.
	 */
	private mutationChain: Promise<unknown> = Promise.resolve();

	itemCount = $derived(this.cart?.items_count ?? 0);
	subtotal = $derived(this.cart?.totals.total_items ?? '0');
	currencyMinorUnit = $derived(this.cart?.totals.currency_minor_unit ?? 2);
	currencySymbol = $derived(this.cart?.totals.currency_symbol ?? '$');
	currencyCode = $derived(this.cart?.totals.currency_code ?? '');

	private cartEntryUrl(): string {
		return `${config.data.spa_origin.replace(/\/$/, '')}/shop?open_cart=1`;
	}

	/**
	 * Mirror the current cart state to the shadow. Called after every
	 * successful mutation and after fetch.
	 */
	private syncShadow() {
		if (!this.cart) return;
		const items: ShadowItem[] = this.cart.items.map((item) => ({
			id: item.id,
			quantity: item.quantity,
			variation: item.variation?.length ? item.variation : undefined
		}));
		writeShadow(items);
	}

	async fetch() {
		this.loading = true;
		this.error = null;
		this.restored = false;
		try {
			this.cart = await request<StoreApiCart>('/cart');
			await this.maybeReplayFromShadow();
			this.syncShadow();
		} catch (e) {
			this.error = e instanceof Error ? e.message : String(e);
		} finally {
			this.loading = false;
		}
	}

	/**
	 * If the active cart is empty but the shadow has items, the token
	 * likely expired silently and WC handed us a fresh cart. Replay
	 * the shadow items into the new cart. Items that are out of stock
	 * or deleted are skipped; other items still get restored.
	 */
	private async maybeReplayFromShadow(): Promise<void> {
		if (!this.cart) return;
		const shadow = readShadow();
		if (shadow.items.length === 0) return;

		const missing = itemsMissingFromActive(shadow.items, this.cart.items);
		if (missing.length === 0) return;

		// Only treat as silent expiry if the active cart is either empty
		// or significantly smaller than the shadow. This avoids replaying
		// when the user deliberately removed items in another tab.
		if (this.cart.items_count >= shadow.items.length) {
			return;
		}

		let replayed = 0;
		for (const item of missing) {
			try {
				this.cart = await request<StoreApiCart>('/cart/add-item', {
					method: 'POST',
					body: {
						id: item.id,
						quantity: item.quantity,
						variation: item.variation ?? []
					}
				});
				replayed++;
			} catch {
				// Sold out, deleted, or other error — skip this item but keep going.
			}
		}
		if (replayed > 0) {
			this.restored = true;
		}
	}

	/**
	 * Guarded mutation runner. Serializes mutations via a promise chain
	 * so concurrent add/update/remove calls execute one at a time
	 * (avoiding the Store API's per-session write race). After each
	 * mutation commits, we converge via a GET /cart so our view matches
	 * the server.
	 */
	private mutate(op: () => Promise<StoreApiCart>): Promise<void> {
		const gen = ++this.generation;

		const run = async () => {
			this.loading = true;
			this.error = null;
			try {
				const next = await op();
				// Still the latest?
				if (gen === this.generation) {
					this.cart = next;
					this.syncShadow();
				}
			} catch (e) {
				if (gen === this.generation) {
					this.error = e instanceof Error ? e.message : String(e);
				}
				throw e;
			} finally {
				if (gen === this.generation) {
					this.loading = false;
					try {
						const fresh = await request<StoreApiCart>('/cart');
						if (gen === this.generation) {
							this.cart = fresh;
							this.syncShadow();
						}
					} catch {
						// best-effort convergence; swallow
					}
				}
			}
		};

		// Chain off the previous mutation. Errors in earlier mutations do
		// not block later ones — we catch and swallow the chain state so
		// new mutations always run.
		const promise = this.mutationChain.catch(() => {}).then(run);
		this.mutationChain = promise;
		return promise;
	}

	async buyNow(id: number, quantity = 1, variation: { attribute: string; value: string }[] = []) {
		// Clear cart — DELETE /cart/items returns [] not a cart object,
		// so we fetch the cart after clearing instead of using mutate.
		await request('/cart/items', { method: 'DELETE' }).catch(() => {});
		// Add the single item
		await this.mutate(() =>
			request<StoreApiCart>('/cart/add-item', { method: 'POST', body: { id, quantity, variation } })
		);
		window.location.href = await this.beginCheckout();
	}

	async addItem(
		id: number,
		quantity = 1,
		variation: { attribute: string; value: string }[] = [],
		analytics?: { clicked_from?: string },
	) {
		const beforeQuantities = new Map((this.cart?.items ?? []).map((item) => [item.key, item.quantity]));
		await this.mutate(() =>
			request<StoreApiCart>('/cart/add-item', { method: 'POST', body: { id, quantity, variation } })
		);
		this.open = true;
		dispatch('added_to_cart', { id, quantity });
		// GA4 + Omnisend + Klaviyo + Meta + TikTok + Pinterest ecommerce
		// tracking — find the item in the cart to get name/price. Every
		// fire is safe when its pixel isn't loaded (no-ops).
		const sameVariation = (item: StoreApiCartItem) => {
			if (variation.length === 0) return false;
			return variation.every((wanted) =>
				item.variation?.some((actual) =>
					actual.attribute === wanted.attribute && actual.value === wanted.value
				)
			);
		};
		const added = this.cart?.items.find(i => i.id === id)
			?? this.cart?.items.find(sameVariation)
			?? this.cart?.items.find(i => i.quantity > (beforeQuantities.get(i.key) ?? 0));
		if (added && typeof window !== 'undefined') {
			import('$lib/analytics').then((a) => {
				const item = {
					id: added.id,
					variant_id: added.id === id ? undefined : id,
					name: added.name,
					price: added.prices.price,
					currency_minor_unit: added.prices.currency_minor_unit,
					currency_code: added.prices.currency_code,
					quantity,
					permalink: (added as { permalink?: string }).permalink,
					image: added.images?.[0]?.src,
					clicked_from: analytics?.clicked_from,
				};
				a.trackAddToCart(item);
				a.trackOmnisendAddedProductToCart(item);
				a.trackKlaviyoAddedToCart(item);
				a.trackMetaAddToCart(item);
				a.trackTikTokAddToCart(item);
				a.trackPinterestAddToCart(item);
			});
		}
	}

	async updateItem(key: string, quantity: number) {
		await this.mutate(() =>
			request<StoreApiCart>('/cart/update-item', { method: 'POST', body: { key, quantity } })
		);
		dispatch('fkcart_quantity_updated', { key, quantity });
	}

	async removeItem(key: string) {
		await this.mutate(() =>
			request<StoreApiCart>('/cart/remove-item', { method: 'POST', body: { key } })
		);
		if (this.cart && this.cart.items_count === 0) clearShadow();
		dispatch('removed_from_cart', { key });
	}

	async applyCoupon(code: string) {
		await this.mutate(() =>
			request<StoreApiCart>('/cart/apply-coupon', { method: 'POST', body: { code } })
		);
		dispatch('fkcart_coupon_applied', { code });
	}

	toggle(force?: boolean) {
		this.open = force ?? !this.open;
		dispatch(this.open ? 'fkcart_cart_open' : 'fkcart_cart_closed', {});
	}

	async beginCheckout(): Promise<string> {
		const hadVisibleItems = (this.cart?.items_count ?? 0) > 0;
		await this.fetch();
		if ((this.cart?.items_count ?? 0) < 1 && hadVisibleItems) {
			// If the user can already see items in the slide cart but the first
			// checkout-time fetch comes back empty, treat that as a transient
			// session/token hiccup and retry once before bouncing them back to shop.
			await primeSession().catch(() => {});
			await this.fetch().catch(() => {});
		}
		if ((this.cart?.items_count ?? 0) < 1) {
			this.open = true;
			return this.cartEntryUrl();
		}

		let href = this.checkoutUrl();
		if (!/\?cart=/.test(href)) {
			await primeSession().catch(() => {});
			href = this.checkoutUrl();
		}

		if (/\?cart=/.test(href) && this.cart?.items?.length && typeof window !== 'undefined') {
			const { trackCustomerLabsCheckoutMade } = await import('$lib/analytics');
			trackCustomerLabsCheckoutMade(this.cart!);
		}

		return /\?cart=/.test(href) ? href : this.cartEntryUrl();
	}

	/**
	 * Build the checkout URL with cart token for the handoff.
	 *
	 * Must be an ABSOLUTE URL to the WP origin, not a relative `/wp/...`
	 * path. Two reasons:
	 *   1. SvelteKit intercepts same-origin <a> clicks for client-side
	 *      routing; an absolute cross-origin URL bypasses the router.
	 *   2. WP's configured siteurl is e.g. shop.example.com, so its
	 *      rendered pages contain absolute URLs to that origin — better
	 *      the browser land directly there.
	 *
	 * Origin comes from the runtime config store (wp_origin field) so
	 * one SPA bundle can serve multiple per-site deployments.
	 */
	checkoutUrl(): string {
		const cartToken = currentCartToken();
		return cartToken ? config.checkoutUrl(cartToken) : this.cartEntryUrl();
	}
}

/**
 * Compatibility event bus — document.body custom events for analytics.
 *
 * Event names use the legacy cart prefix on purpose: analytics integrations
 * (Klaviyo, Omnisend, custom GTM) that already have listeners bound to those
 * names keep working without reconfiguration. Our SlideCart component is pure
 * WCHS code; only the event-name surface stays.
 */
function dispatch(name: string, detail: unknown) {
	if (typeof document === 'undefined') return;
	document.body.dispatchEvent(new CustomEvent(name, { detail, bubbles: true }));
}

export const cart = new CartStore();
