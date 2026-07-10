/**
 * Cart store — Svelte 5 runes.
 *
 * Single source of truth is whatever GET /wc/store/v1/cart returns. Qty
 * edits patch the local cart immediately, then debounce a Store API POST.
 * Cross-tab sync via shadow-cart storage events. After FunnelKit/classic
 * checkout, reverse-sync classic → Store API so Elementor mini-cart edits
 * land back in the SPA.
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
	SHADOW_CART_KEY,
	type ShadowItem
} from './shadow-cart';
import { config } from '../config.svelte';
import { estimateCartLineCro } from '../cart/bundle-pricing';
import { browser } from '$app/environment';

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
	line_total_minor?: number;
	compare_line_minor?: number;
	savings_per_unit: number;
	savings_line_total: number;
	savings_pct: number;
	next_tier: WchsCroNextTier | null;
	bundle_label?: string;
	tier_qty_thresholds?: number[];
	active_bundle_min_qty?: number;
	cross_sell_ids: number[];
	is_shipping_protection?: boolean;
	is_free_bac_gift?: boolean;
	fee_minor?: number;
};

export type WchsCroShippingProtection = {
	subtotal_basis_minor: number;
	fee_minor: number;
	tiers?: { up_to: number | null; fee: number }[];
};

export type WchsCroCartRewards = {
	subtotal_minor: number;
	shipping_threshold_minor: number;
	bac_water_threshold_minor: number;
	track_max_minor: number;
	shipping_unlocked: boolean;
	bac_water_unlocked: boolean;
	bac_water_product_id: number;
};

export type WchsCroCartTop = {
	total_savings: number;
	cross_sell_ids: number[];
	shipping_protection?: WchsCroShippingProtection;
	rewards?: WchsCroCartRewards;
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
	 * Cancels in-flight convergence GETs when a newer mutation starts.
	 * POST responses always apply — mutations are serialized on mutationChain.
	 */
	private convergenceGen = 0;

	/** Latest qty per line — coalesces rapid +/- clicks into one POST. */
	private pendingQtyByKey = new Map<string, number>();
	private qtyFlushChain: Promise<void> = Promise.resolve();
	private qtyDebounceTimer: ReturnType<typeof setTimeout> | null = null;
	/** Short debounce — UI is optimistic; this only batches rapid +/- clicks. */
	private static readonly QTY_SYNC_DEBOUNCE_MS = 280;
	private static readonly CHECKOUT_RETURN_KEY = 'wchs_awaiting_checkout_return';
	/** After classic→Store API sync, trust server and skip shadow replay once. */
	private skipShadowReplayOnce = false;

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
	private pruningProtection = false;

	itemCount = $derived.by(() => this.countVisibleItems());
	subtotal = $derived(this.cart?.totals.total_items ?? '0');
	currencyMinorUnit = $derived(this.cart?.totals.currency_minor_unit ?? 2);
	currencySymbol = $derived(this.cart?.totals.currency_symbol ?? '$');
	currencyCode = $derived(this.cart?.totals.currency_code ?? '');

	private cartEntryUrl(): string {
		return `${config.data.spa_origin.replace(/\/$/, '')}/shop?open_cart=1`;
	}

	private wpCheckoutBaseUrl(): string {
		const path = (config.data.checkout_handoff_path || '/checkout').replace(/\/+$/, '') || '/checkout';
		return config.wpUrl(`${path}/`);
	}

	private activeItemCount(): number {
		if (!this.cart) return 0;
		return Math.max(this.cart.items_count ?? 0, this.cart.items?.length ?? 0);
	}

	private isShippingProtectionItem(item: StoreApiCartItem): boolean {
		if (item.extensions?.wchs_cro?.is_shipping_protection) return true;
		const protectId = this.shippingProtectionProductId();
		return protectId !== null && item.id === protectId;
	}

	private countVisibleItems(): number {
		if (!this.cart) return 0;
		return this.cart.items.reduce((n, item) => {
			if (this.isShippingProtectionItem(item)) return n;
			return n + item.quantity;
		}, 0);
	}

	private visibleItemCount(): number {
		return this.countVisibleItems();
	}

	private async ensureCartHandoffToken(): Promise<string | null> {
		let token = currentCartToken();
		if (token) return token;

		await primeSession().catch(() => {});
		token = currentCartToken();
		if (token) return token;

		try {
			await request<StoreApiCart>('/cart');
		} catch {
			// best-effort — checkout flow will fall back if still missing
		}
		return currentCartToken();
	}

	/**
	 * Mirror the current cart state to the shadow. Called after every
	 * successful mutation and after fetch.
	 */
	private shippingProtectionProductId(): number | null {
		const id = config.data.pdp?.slide_cart?.shipping_protection_product_id;
		return typeof id === 'number' && id > 0 ? id : null;
	}

	private hasRegularCartItems(cart: StoreApiCart): boolean {
		return cart.items.some((item) => !this.isShippingProtectionItem(item));
	}

	/**
	 * Remove shipping protection when it is the only line left (e.g. user
	 * deleted all products). Protection must not outlive purchasable items.
	 */
	private async pruneOrphanShippingProtection(): Promise<void> {
		if (this.pruningProtection || !this.cart) return;
		const protectId = this.shippingProtectionProductId();
		if (!protectId || this.hasRegularCartItems(this.cart)) return;

		const line = this.cart.items.find((item) => item.id === protectId);
		if (!line) return;

		this.pruningProtection = true;
		try {
			await this.mutate(() =>
				request<StoreApiCart>('/cart/remove-item', { method: 'POST', body: { key: line.key } })
			);
		} finally {
			this.pruningProtection = false;
		}
	}

	private syncShadow() {
		if (!this.cart) return;
		const protectId = this.shippingProtectionProductId();
		const items: ShadowItem[] = this.cart.items
			.filter((item) => !protectId || item.id !== protectId)
			.map((item) => ({
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
			if (this.skipShadowReplayOnce) {
				this.skipShadowReplayOnce = false;
			} else {
				await this.maybeReplayFromShadow();
			}
			await this.pruneOrphanShippingProtection();
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
	private mutate(
		op: () => Promise<StoreApiCart>,
		options?: { converge?: boolean }
	): Promise<void> {
		const shouldConverge = options?.converge !== false;
		const convergeAtStart = ++this.convergenceGen;

		const run = async () => {
			const showLoading = !this.cart;
			if (showLoading) this.loading = true;
			this.error = null;
			try {
				const next = await op();
				this.cart = next;
				this.syncShadow();
			} catch (e) {
				this.error = e instanceof Error ? e.message : String(e);
				await this.fetch().catch(() => {});
				throw e;
			} finally {
				if (showLoading) this.loading = false;
				if (shouldConverge && convergeAtStart === this.convergenceGen) {
					try {
						const fresh = await request<StoreApiCart>('/cart');
						if (convergeAtStart === this.convergenceGen) {
							this.cart = fresh;
							await this.pruneOrphanShippingProtection();
							this.syncShadow();
						}
					} catch {
						// best-effort convergence; swallow
					}
				}
			}
		};

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
		await this.mutate(
			() => request<StoreApiCart>('/cart/add-item', { method: 'POST', body: { id, quantity, variation } }),
			{ converge: false }
		);
		await this.openCartDrawer();
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

	private clearQtyDebounce(): void {
		if (this.qtyDebounceTimer !== null) {
			clearTimeout(this.qtyDebounceTimer);
			this.qtyDebounceTimer = null;
		}
	}

	private scheduleQtySync(): void {
		this.clearQtyDebounce();
		this.qtyDebounceTimer = setTimeout(() => {
			this.qtyDebounceTimer = null;
			void this.flushPendingQtyUpdates();
		}, CartStore.QTY_SYNC_DEBOUNCE_MS);
	}

	/** Push debounced qty edits to the Store API (checkout / drawer close call this immediately). */
	flushPendingQtyUpdates(): Promise<void> {
		this.clearQtyDebounce();
		this.qtyFlushChain = this.qtyFlushChain.catch(() => {}).then(async () => {
			while (this.pendingQtyByKey.size > 0) {
				const batch = [...this.pendingQtyByKey.entries()];
				for (const [key] of batch) {
					const quantity = this.pendingQtyByKey.get(key);
					if (quantity === undefined) continue;
					this.pendingQtyByKey.delete(key);
					try {
						await this.mutate(
							() =>
								request<StoreApiCart>('/cart/update-item', {
									method: 'POST',
									body: { key, quantity }
								}),
							{ converge: false }
						);
					} catch {
						await this.fetch().catch(() => {});
					}
				}
			}
		});
		return this.qtyFlushChain;
	}

	/** Qualifying rewards subtotal — excludes shipping protection + free BAC gift. */
	private rewardsSubtotalMinor(items: StoreApiCartItem[]): number {
		let minor = 0;
		for (const item of items) {
			if (this.isShippingProtectionItem(item)) continue;
			if (item.extensions?.wchs_cro?.is_free_bac_gift) continue;
			const line =
				item.extensions?.wchs_cro?.line_total_minor ?? Number(item.totals.line_total);
			minor += Number.isFinite(line) ? line : 0;
		}
		return Math.max(0, Math.round(minor));
	}

	private estimateRewards(subtotalMinor: number): WchsCroCartRewards {
		const prev = this.cart?.extensions?.wchs_cro?.rewards;
		const mu = this.cart?.totals.currency_minor_unit ?? 2;
		const shipMinor =
			prev?.shipping_threshold_minor ??
			Math.round((config.data.shipping_free_threshold || 0) * 10 ** mu);
		const bacMajor = config.data.pdp?.slide_cart?.rewards?.bac_water_threshold ?? 300;
		const bacMinor = prev?.bac_water_threshold_minor ?? Math.round(bacMajor * 10 ** mu);
		const trackMax = prev?.track_max_minor ?? Math.max(bacMinor, shipMinor, 1);
		return {
			subtotal_minor: subtotalMinor,
			shipping_threshold_minor: shipMinor,
			bac_water_threshold_minor: bacMinor,
			track_max_minor: trackMax,
			shipping_unlocked: shipMinor > 0 && subtotalMinor >= shipMinor,
			bac_water_unlocked: subtotalMinor >= bacMinor,
			bac_water_product_id:
				prev?.bac_water_product_id ?? config.data.pdp?.slide_cart?.bac_water_product_id ?? 0
		};
	}

	private applyOptimisticCart(items: StoreApiCartItem[]): void {
		if (!this.cart) return;
		const rewardsSubtotal = this.rewardsSubtotalMinor(items);
		const itemsCount = items.reduce((n, i) => n + i.quantity, 0);
		const prevCro = this.cart.extensions?.wchs_cro;
		this.cart = {
			...this.cart,
			items,
			items_count: itemsCount,
			totals: {
				...this.cart.totals,
				total_items: String(rewardsSubtotal),
				total_price: String(rewardsSubtotal)
			},
			extensions: {
				...this.cart.extensions,
				wchs_cro: {
					...prevCro,
					total_savings: prevCro?.total_savings ?? 0,
					cross_sell_ids: prevCro?.cross_sell_ids ?? [],
					rewards: this.estimateRewards(rewardsSubtotal)
				}
			}
		};
	}

	private patchItemQuantity(key: string, quantity: number): void {
		if (!this.cart) return;
		const bogo = config.data.pdp?.bundle_bogo;

		const items = this.cart.items.map((item) => {
			if (item.key !== key) return item;
			const cro = item.extensions?.wchs_cro;
			if (cro) {
				const nextCro = estimateCartLineCro(quantity, cro, bogo);
				const lineStr = String(nextCro.line_total_minor ?? Number(item.totals.line_total));
				return {
					...item,
					quantity,
					totals: {
						...item.totals,
						line_total: lineStr,
						line_subtotal: lineStr
					},
					extensions: {
						...item.extensions,
						wchs_cro: nextCro
					}
				};
			}
			return { ...item, quantity };
		});

		this.applyOptimisticCart(items);
	}

	async updateItem(key: string, quantity: number) {
		this.patchItemQuantity(key, quantity);
		this.pendingQtyByKey.set(key, quantity);
		this.syncShadow();
		this.scheduleQtySync();
		dispatch('fkcart_quantity_updated', { key, quantity });
	}

	async removeItem(key: string) {
		this.pendingQtyByKey.delete(key);
		if (this.pendingQtyByKey.size === 0) {
			this.clearQtyDebounce();
		}

		if (this.cart) {
			const items = this.cart.items.filter((item) => item.key !== key);
			this.applyOptimisticCart(items);
			if (items.length === 0) clearShadow();
			else this.syncShadow();
			dispatch('removed_from_cart', { key });
		}

		try {
			await this.mutate(
				() => request<StoreApiCart>('/cart/remove-item', { method: 'POST', body: { key } }),
				{ converge: false }
			);
			if (this.cart && this.cart.items_count === 0) clearShadow();
		} catch {
			// mutate already refetches on error
		}
	}

	async applyCoupon(code: string) {
		await this.mutate(() =>
			request<StoreApiCart>('/cart/apply-coupon', { method: 'POST', body: { code } })
		);
		dispatch('fkcart_coupon_applied', { code });
	}

	async toggle(force?: boolean) {
		if (config.data.funnelkit_cart?.enabled) {
			if (force === false) return;
			await this.openCartDrawer();
			return;
		}
		const wasOpen = this.open;
		this.open = force ?? !this.open;
		if (wasOpen && !this.open) {
			void this.flushPendingQtyUpdates();
		}
		if (!wasOpen && this.open) {
			void this.refreshOnOpen();
		}
		dispatch(this.open ? 'fkcart_cart_open' : 'fkcart_cart_closed', {});
	}

	private async openCartDrawer(): Promise<void> {
		if (config.data.funnelkit_cart?.enabled) {
			const { openFunnelKitCart } = await import('$lib/funnelkit-cart');
			await openFunnelKitCart(this.itemCount);
			dispatch('fkcart_cart_open', {});
			return;
		}
		const wasOpen = this.open;
		this.open = true;
		if (!wasOpen) void this.refreshOnOpen();
		dispatch('fkcart_cart_open', {});
	}

	/**
	 * Copy classic WC session (Elementor/FunnelKit mini-cart) back into the
	 * Store API session for this Cart-Token. Intentionally omits Cart-Token
	 * on the request so WP loads the cookie session the checkout page mutated.
	 */
	async syncFromClassicSession(): Promise<boolean> {
		const token = currentCartToken();
		if (!token || typeof fetch === 'undefined') return false;
		try {
			const res = await fetch('/wp-json/wchs/v1/cart/sync-from-classic', {
				method: 'POST',
				credentials: 'include',
				headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
				body: JSON.stringify({ cart: token })
			});
			return res.ok;
		} catch {
			return false;
		}
	}

	private checkoutReturnFlagSet(): boolean {
		try {
			if (typeof sessionStorage !== 'undefined' && sessionStorage.getItem(CartStore.CHECKOUT_RETURN_KEY) === '1') {
				return true;
			}
			if (typeof localStorage !== 'undefined' && localStorage.getItem(CartStore.CHECKOUT_RETURN_KEY) === '1') {
				return true;
			}
		} catch {
			// storage unavailable
		}
		return false;
	}

	private clearCheckoutReturnFlag(): void {
		try {
			sessionStorage?.removeItem(CartStore.CHECKOUT_RETURN_KEY);
			localStorage?.removeItem(CartStore.CHECKOUT_RETURN_KEY);
		} catch {
			// storage unavailable
		}
	}

	private markCheckoutReturnFlag(): void {
		try {
			sessionStorage?.setItem(CartStore.CHECKOUT_RETURN_KEY, '1');
			localStorage?.setItem(CartStore.CHECKOUT_RETURN_KEY, '1');
		} catch {
			// storage unavailable
		}
	}

	/** True when document.referrer looks like FunnelKit / WC checkout. */
	private referrerLooksLikeCheckout(): boolean {
		if (typeof document === 'undefined' || !document.referrer) return false;
		try {
			const ref = new URL(document.referrer);
			if (typeof window !== 'undefined' && ref.origin !== window.location.origin) return false;
			const path = ref.pathname.toLowerCase();
			const handoff = (config.data.checkout_handoff_path || '/checkout').toLowerCase();
			if (handoff && path.includes(handoff.replace(/\/+$/, ''))) return true;
			if (path.includes('/checkout')) return true;
			if (path.includes('/checkouts/')) return true;
			return false;
		} catch {
			return false;
		}
	}

	/**
	 * Pull Elementor/FunnelKit mini-cart changes into the SPA Store API cart.
	 * Returns true when a reverse-sync + fetch ran.
	 *
	 * `allowReferrer`: only on full SPA boot — document.referrer stays stuck on
	 * checkout during client-side navigations and must not re-sync later.
	 */
	async refreshAfterExternalCartChange(opts?: { allowReferrer?: boolean }): Promise<boolean> {
		const fromFlag = this.checkoutReturnFlagSet();
		const fromReferrer = Boolean(opts?.allowReferrer) && this.referrerLooksLikeCheckout();
		if (!fromFlag && !fromReferrer) return false;

		this.clearCheckoutReturnFlag();

		const synced = await this.syncFromClassicSession();
		if (synced) {
			clearShadow();
			this.skipShadowReplayOnce = true;
		}

		await primeSession().catch(() => {});
		await this.fetch().catch(() => {});
		return true;
	}

	/** Soft refresh when the drawer opens — reverse-sync only if checkout-return flag is set. */
	async refreshOnOpen(): Promise<void> {
		if (this.pendingQtyByKey.size > 0) return;
		const synced = await this.refreshAfterExternalCartChange();
		if (!synced) await this.fetch().catch(() => {});
	}

	async beginCheckout(): Promise<string> {
		const hadVisibleItems = this.visibleItemCount() > 0;

		await this.flushPendingQtyUpdates();

		// Prime before fetch — Safari can drop sessionStorage between cart edits
		// and checkout; a fresh GET /cart re-establishes Cart-Token + nonce.
		await primeSession().catch(() => {});
		await this.fetch().catch(() => {});

		if (this.visibleItemCount() < 1 && hadVisibleItems) {
			for (let attempt = 0; attempt < 2 && this.visibleItemCount() < 1; attempt++) {
				await primeSession().catch(() => {});
				await this.fetch().catch(() => {});
			}
		}

		if (this.visibleItemCount() < 1) {
			await this.openCartDrawer();
			return this.cartEntryUrl();
		}

		const token = await this.ensureCartHandoffToken();
		const href = token ? config.checkoutUrl(token) : this.wpCheckoutBaseUrl();

		this.markCheckoutReturnFlag();

		if (token && this.cart?.items?.length && typeof window !== 'undefined') {
			const { trackCustomerLabsCheckoutMade } = await import('$lib/analytics');
			trackCustomerLabsCheckoutMade(this.cart!);
		}

		return href;
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
	/** Link target for the checkout CTA — never the shop fallback (Safari native click). */
	checkoutUrl(): string {
		const cartToken = currentCartToken();
		return cartToken ? config.checkoutUrl(cartToken) : this.wpCheckoutBaseUrl();
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

if (browser) {
	window.addEventListener('storage', (e) => {
		if (e.key === SHADOW_CART_KEY && e.newValue !== e.oldValue) {
			void cart.fetch();
		}
	});
}
