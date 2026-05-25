<script lang="ts">
	/**
	 * SlideCart — Slide-out cart drawer in native Svelte 5.
	 *
	 * Behavior spec: docs/cart-spec.md
	 * Design: Runway-inspired dark-dominant, tight type, zero shadows.
	 *
	 * Free-tier FK parity: slide, items, qty stepper, remove,
	 * subtotal, checkout CTA. Upsells/rewards are Pro features — separate
	 * sibling components if/when we add them.
	 */
	import { cart } from '$lib/wc/cart.svelte';
	import { pretext } from '$lib/pretext/engine';
	import { browser } from '$app/environment';
	import { onMount } from 'svelte';
	import CartCrossSellStrip from './CartCrossSellStrip.svelte';
	import { formatPrice } from '$lib/utils/format';
	import { getShippingProtectionProduct } from '$lib/wc/products';
	import type { StoreProduct } from '$lib/wc/products';
	import {
		shippingProtectionFeeMajor,
		shippingProtectionTierIndex
	} from '$lib/shipping-protection';
	import { resolveCartLineQty } from '$lib/cart/bundle-qty';

	let fontsReady = $state(false);
	let checkouting = $state(false);
	let shipProtectProduct = $state<StoreProduct | null>(null);
	let shipProtectBusy = $state(false);
	let shipProtectDeclined = $state(false);

	const SHIP_PROTECT_DECLINED_KEY = 'wchs_ship_protect_declined';
	let shipProtectTierTracked = -1;

	// Flash-on-mutation: when an item's quantity or total changes we
	// briefly highlight it. Keyed by item.key → timestamp; CSS transition
	// does the work.
	let flashedKeys = $state<Record<string, number>>({});
	let upsellMobileOpen = $state(true);

	onMount(async () => {
		await pretext.ready();
		fontsReady = true;
	});

	// Pretext-measured item title height. Cart drawer inner width is 420,
	// minus thumbnail col (72) minus padding (48) minus gap (14) ≈ 286.
	function titleHeight(name: string): number | null {
		if (!fontsReady) return null;
		const r = pretext.measure(name, 'cart-item', 286, 18);
		return r.height;
	}

	function close() {
		cart.toggle(false);
	}

	async function goToCheckout() {
		if (checkouting) return;
		checkouting = true;
		try {
			window.location.href = await cart.beginCheckout();
		} finally {
			checkouting = false;
		}
	}

	async function beginCheckout(event: MouseEvent) {
		if (
			event.defaultPrevented ||
			event.button !== 0 ||
			event.metaKey ||
			event.ctrlKey ||
			event.shiftKey ||
			event.altKey
		) {
			return;
		}

		event.preventDefault();
		event.stopPropagation();
		await goToCheckout();
	}

	async function continueWithoutShippingProtection() {
		if (shipProtectBusy || checkouting) return;
		shipProtectBusy = true;
		try {
			if (shipProtectLine) {
				await cart.removeItem(shipProtectLine.key);
				shipProtectDeclined = true;
				if (browser) {
					try {
						sessionStorage.setItem(SHIP_PROTECT_DECLINED_KEY, '1');
					} catch {
						// Safari private mode / storage quota
					}
				}
			}
			await cart.fetch().catch(() => {});
		} finally {
			shipProtectBusy = false;
		}
		await goToCheckout();
	}

	function resolvedQty(item: (typeof displayCartItems)[number], proposed: number): number {
		const thresholds = item.extensions?.wchs_cro?.tier_qty_thresholds ?? [];
		if (!thresholds.length) return Math.max(1, proposed);
		return resolveCartLineQty(thresholds, item.quantity, proposed);
	}

	async function decrement(key: string, current: number) {
		const item = displayCartItems.find((i) => i.key === key);
		if (!item) return;
		const next = resolvedQty(item, current - 1);
		if (next <= 0 || (current <= 1 && next <= 1)) {
			await cart.removeItem(key);
		} else {
			flashKey(key);
			await cart.updateItem(key, next);
		}
	}

	async function increment(key: string, current: number) {
		const item = displayCartItems.find((i) => i.key === key);
		if (!item) return;
		const next = resolvedQty(item, current + 1);
		flashKey(key);
		await cart.updateItem(key, next);
	}

	function flashKey(key: string) {
		flashedKeys = { ...flashedKeys, [key]: Date.now() };
		setTimeout(() => {
			flashedKeys = Object.fromEntries(Object.entries(flashedKeys).filter(([k]) => k !== key));
		}, 500);
	}

	function onKeydown(e: KeyboardEvent) {
		if (e.key === 'Escape' && cart.open) close();
	}

	function formatMoney(minor: string, minorUnit: number, symbol: string, code = cart.currencyCode): string {
		return formatPrice(minor, { currency_minor_unit: minorUnit, currency_symbol: symbol, currency_code: code });
	}

	// Overload for integer-minor-unit values emitted by the wchs_cro extension.
	function formatMoneyInt(minorInt: number): string {
		return formatPrice(minorInt, {
			currency_minor_unit: cart.currencyMinorUnit,
			currency_symbol: cart.currencySymbol,
			currency_code: cart.currencyCode,
		});
	}

	const shipProtectLine = $derived.by(() => {
		const pid = shipProtectProduct?.id;
		if (!pid || !cart.cart) return null;
		return cart.cart.items.find((i) => i.id === pid) ?? null;
	});

	const hasShipProtect = $derived(shipProtectLine !== null);

	const displayCartItems = $derived.by(() => {
		const pid = shipProtectProduct?.id;
		if (!cart.cart) return [];
		if (!pid) return cart.cart.items;
		return cart.cart.items.filter((i) => i.id !== pid);
	});

	const visibleItemCount = $derived(
		displayCartItems.reduce((n, i) => n + i.quantity, 0)
	);

	const crossSellIds = $derived(cart.cart?.extensions?.wchs_cro?.cross_sell_ids ?? []);
	const showUpsell = $derived(crossSellIds.length > 0 && visibleItemCount > 0);

	const shipProtectSubtotalMajor = $derived.by(() => {
		const mu = cart.currencyMinorUnit || 2;
		const fromApi = cart.cart?.extensions?.wchs_cro?.shipping_protection?.subtotal_basis_minor;
		if (typeof fromApi === 'number' && fromApi >= 0) {
			return fromApi / Math.pow(10, mu);
		}
		let minor = 0;
		for (const item of displayCartItems) {
			minor += Number(item.totals.line_total ?? 0);
		}
		return minor / Math.pow(10, mu);
	});

	const shipProtectFeeMinor = $derived.by(() => {
		const fromApi = cart.cart?.extensions?.wchs_cro?.shipping_protection?.fee_minor;
		if (typeof fromApi === 'number' && fromApi > 0) {
			return fromApi;
		}
		const croFee = shipProtectLine?.extensions?.wchs_cro?.fee_minor;
		if (typeof croFee === 'number' && croFee > 0) {
			return croFee;
		}
		const major = shippingProtectionFeeMajor(shipProtectSubtotalMajor);
		return Math.round(major * Math.pow(10, cart.currencyMinorUnit || 2));
	});

	const shipProtectPriceLabel = $derived.by(() => {
		return formatPrice(shipProtectFeeMinor, {
			currency_minor_unit: cart.currencyMinorUnit,
			currency_symbol: cart.currencySymbol,
			currency_code: cart.currencyCode
		});
	});

	async function ensureShipProtectProduct() {
		if (shipProtectProduct) return;
		shipProtectProduct = await getShippingProtectionProduct();
	}

	async function addShippingProtection() {
		if (!shipProtectProduct || shipProtectBusy || hasShipProtect) return;
		shipProtectBusy = true;
		try {
			await cart.addItem(shipProtectProduct.id, 1, [], {
				clicked_from: 'slide_cart_ship_protect_auto'
			});
			await cart.fetch().catch(() => {});
		} finally {
			shipProtectBusy = false;
		}
	}

	$effect(() => {
		if (!cart.open || visibleItemCount === 0) return;
		void ensureShipProtectProduct();
	});

	$effect(() => {
		if (!browser || cart.itemCount === 0) {
			shipProtectDeclined = false;
			if (browser) sessionStorage.removeItem(SHIP_PROTECT_DECLINED_KEY);
			return;
		}
		if (sessionStorage.getItem(SHIP_PROTECT_DECLINED_KEY) === '1') {
			shipProtectDeclined = true;
		}
	});

	$effect(() => {
		if (!cart.open || !shipProtectProduct || hasShipProtect || shipProtectBusy) return;
		if (displayCartItems.length === 0 || shipProtectDeclined) return;
		void addShippingProtection();
	});

	$effect(() => {
		if (!hasShipProtect || !cart.cart) {
			shipProtectTierTracked = -1;
			return;
		}
		const tier = shippingProtectionTierIndex(shipProtectSubtotalMajor);
		if (shipProtectTierTracked >= 0 && tier !== shipProtectTierTracked) {
			void cart.fetch();
		}
		shipProtectTierTracked = tier;
	});

	$effect(() => {
		if (typeof document === 'undefined') return;
		document.documentElement.classList.toggle('fkcart-trigger-open', cart.open);
	});
</script>

<svelte:window onkeydown={onKeydown} />

<div
	class="fkcart-backdrop"
	class:fkcart-backdrop-show={cart.open}
	aria-hidden="true"
	onclick={close}
	role="presentation"
></div>

<aside
	class="fkcart-modal"
	class:fkcart-show={cart.open}
	class:has-upsell={showUpsell}
	aria-label="Shopping cart"
	aria-hidden={!cart.open}
>
	{#if showUpsell}
		<div class="fkcart-upsell fkcart-upsell--desktop">
			<CartCrossSellStrip ids={crossSellIds} layout="sidebar" />
		</div>
	{/if}

	<div class="fkcart-main">
	<header class="fkcart-header">
		<h2 class="fkcart-header__title">
			Review Your Cart
			<span class="fkcart-header__count tabular-nums">({visibleItemCount})</span>
		</h2>
		<button type="button" class="fkcart-close" onclick={close} aria-label="Close cart">
			<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
				<path d="M6 6l12 12M18 6L6 18" />
			</svg>
		</button>
	</header>

	<div class="fkcart-body" class:has-zero-state={visibleItemCount === 0}>
		{#if cart.loading && !cart.cart}
			<p class="fkcart-state">Loading…</p>
		{:else if visibleItemCount === 0}
			<div class="fkcart-empty">
				<p class="fkcart-empty__msg">Your cart is empty</p>
				<button type="button" class="fkcart-empty__cta" onclick={close}>
					Continue shopping
				</button>
			</div>
		{:else if cart.cart}
			<ul class="fkcart-items">
				{#each displayCartItems as item (item.key)}
					{@const h = titleHeight(item.name)}
					{@const isFlashing = !!flashedKeys[item.key]}
					{@const cro = item.extensions?.wchs_cro}
					{@const compareMinor =
						cro?.compare_line_minor ??
						(cro?.regular_unit_price ?? Number(item.prices.regular_price)) * item.quantity}
					{@const lineMinor = cro?.line_total_minor ?? Number(item.totals.line_total)}
					{@const showCompare = compareMinor > lineMinor}
					{@const bundleLabel = (cro?.bundle_label?.split('·')[0] ?? '').trim()}
					<li class="fkcart-item" class:is-flashing={isFlashing}>
						<div class="fkcart-item__media">
							{#if item.images[0]}
								<img
									src={item.images[0].thumbnail}
									alt={item.images[0].alt || item.name}
									loading="lazy"
								/>
							{/if}
						</div>

						<div class="fkcart-item__body">
							<div class="fkcart-item__top">
								<div class="fkcart-item__info">
									<a
										class="fkcart-item__title"
										href={item.permalink}
										style={h !== null ? `min-height: ${h}px` : ''}
									>{item.name}</a>

									{#if bundleLabel}
										<p class="fkcart-item__bundle">{bundleLabel}</p>
									{/if}

									{#if item.variation.length}
										<ul class="fkcart-item__variation">
											{#each item.variation as v}
												<li>{v.attribute}: <span>{v.value}</span></li>
											{/each}
										</ul>
									{/if}

									{#if cro && cro.effective_unit_price > 0}
										<p class="fkcart-item__unit tabular-nums">
											{formatMoneyInt(cro.effective_unit_price)} each
										</p>
									{/if}
								</div>

								<div class="fkcart-item__price-col tabular-nums">
									{#if showCompare}
										<span class="fkcart-item__price-was">{formatMoneyInt(compareMinor)}</span>
									{/if}
									<span class="fkcart-item__price">{formatMoneyInt(lineMinor)}</span>
								</div>
							</div>

							<div class="fkcart-item__foot">
								{#if !item.sold_individually && item.quantity_limits.editable}
									<div class="fkcart-qty" role="group" aria-label="Quantity for {item.name}">
										<button
											type="button"
											class="fkcart-qty__btn"
											onclick={() => decrement(item.key, item.quantity)}
											aria-label="Decrease quantity"
										>
											<svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
												<path d="M5 12h14" />
											</svg>
										</button>
										<span class="fkcart-qty__value tabular-nums" aria-live="polite">{item.quantity}</span>
										<button
											type="button"
											class="fkcart-qty__btn"
											onclick={() => increment(item.key, item.quantity)}
											aria-label="Increase quantity"
										>
											<svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
												<path d="M12 5v14M5 12h14" />
											</svg>
										</button>
									</div>
								{/if}
								<button
									type="button"
									class="fkcart-item__remove"
									onclick={() => cart.removeItem(item.key)}
									aria-label="Remove {item.name}"
								>
									<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
										<path d="M4 7h16M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2m2 0v11a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V7h12z" />
									</svg>
								</button>
							</div>
						</div>
					</li>
				{/each}
			</ul>
		{/if}
	</div>

	{#if showUpsell && visibleItemCount > 0}
		<div class="fkcart-upsell-mobile" class:is-collapsed={!upsellMobileOpen}>
			<button
				type="button"
				class="fkcart-upsell-mobile__toggle"
				aria-expanded={upsellMobileOpen}
				aria-controls="fkcart-upsell-mobile-panel"
				onclick={() => (upsellMobileOpen = !upsellMobileOpen)}
			>
				<span>Frequently Bought Together</span>
				<svg
					class="fkcart-upsell-mobile__chevron"
					class:is-open={upsellMobileOpen}
					viewBox="0 0 24 24"
					width="16"
					height="16"
					fill="none"
					stroke="currentColor"
					stroke-width="2"
					stroke-linecap="round"
					aria-hidden="true"
				>
					<path d="M6 9l6 6 6-6" />
				</svg>
			</button>
			{#if upsellMobileOpen}
				<div id="fkcart-upsell-mobile-panel" class="fkcart-upsell-mobile__panel">
					<CartCrossSellStrip ids={crossSellIds} layout="strip" hideHeading />
				</div>
			{/if}
		</div>
	{/if}

	{#if cart.cart && visibleItemCount > 0}
		{@const cartCro = cart.cart.extensions?.wchs_cro}
		{@const hasSavings = !!cartCro && cartCro.total_savings > 0}
		<footer class="fkcart-footer">
			<dl class="fkcart-summary tabular-nums">
				<div class="fkcart-summary__row">
					<dt>Subtotal</dt>
					{#key cart.subtotal}
						<dd class="fkcart-summary__value">
							{formatMoney(cart.subtotal, cart.currencyMinorUnit, cart.currencySymbol)}
						</dd>
					{/key}
				</div>
				{#if hasSavings && cartCro}
					<div class="fkcart-summary__row fkcart-summary__row--savings">
						<dt>You saved</dt>
						{#key cartCro.total_savings}
							<dd class="fkcart-summary__value fkcart-summary__value--savings">
								{formatMoneyInt(cartCro.total_savings)}
							</dd>
						{/key}
					</div>
				{/if}
			</dl>

			{#if shipProtectProduct && hasShipProtect && displayCartItems.length > 0}
				<div class="fkcart-ship-protect" aria-live="polite">
					<div class="fkcart-ship-protect__icon" aria-hidden="true">
						<svg viewBox="0 0 24 24" width="28" height="28" fill="currentColor">
							<path
								d="M12 2 4 5v6c0 5.25 3.44 10.15 8 11.35.55.14 1.12.14 1.67 0 4.56-1.2 8-6.1 8-11.35V5l-8-3zm-1.2 14.2-2.5-2.5 1.4-1.4.9.9 3.3-3.3 1.4 1.4-4.5 4.5z"
							/>
						</svg>
					</div>
					<div class="fkcart-ship-protect__copy">
						<span class="fkcart-ship-protect__title">Shipping Protection</span>
						<p class="fkcart-ship-protect__desc">Free returns + package protection</p>
					</div>
					<span class="fkcart-ship-protect__price tabular-nums">{shipProtectPriceLabel}</span>
				</div>
			{/if}

			<a
				href={cart.checkoutUrl()}
				class="fkcart-checkout"
				data-sveltekit-reload
				aria-busy={checkouting}
				onclick={beginCheckout}
			>
				{checkouting ? 'Loading…' : 'Checkout'}
			</a>

			{#if hasShipProtect && displayCartItems.length > 0}
				<button
					type="button"
					class="fkcart-ship-protect__skip"
					disabled={shipProtectBusy || checkouting}
					onclick={continueWithoutShippingProtection}
				>
					Continue without shipping protection
				</button>
			{/if}

			<div class="fkcart-trust" aria-label="Secure checkout">
				<span class="fkcart-trust__secure">
					<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
						<rect x="4.5" y="11" width="15" height="9.5" rx="1.5"/>
						<path d="M7.5 11V8a4.5 4.5 0 0 1 9 0v3"/>
					</svg>
					Secure Checkout
				</span>
				<span class="fkcart-trust__payments" aria-hidden="true">
					<span class="fkcart-pay fkcart-pay--visa">VISA</span>
					<span class="fkcart-pay fkcart-pay--mc" aria-label="Mastercard"></span>
					<span class="fkcart-pay fkcart-pay--amex">AMEX</span>
					<span class="fkcart-pay fkcart-pay--disc">DISC</span>
					<span class="fkcart-pay fkcart-pay--btc" aria-label="Bitcoin">₿</span>
				</span>
			</div>
		</footer>
	{/if}
	</div>
</aside>

<style>
	/* =================================================================
	   Backdrop + modal chrome
	   ================================================================= */

	/* Backdrop is ALWAYS in the DOM — never created/destroyed via {#if}.
	   Svelte 5 has a known bug (#14732) where elements inside {#if}
	   blocks flash at full opacity for one frame before animations
	   start. Keeping the element permanently mounted and toggling via
	   class avoids the mount cycle entirely. */
	.fkcart-backdrop {
		position: fixed;
		inset: 0;
		background: var(--overlay);
		z-index: 9998;
		opacity: 0;
		pointer-events: none;
		transition: opacity 200ms var(--ease-out);
	}
	.fkcart-backdrop-show {
		opacity: 1;
		pointer-events: auto;
	}

	.fkcart-modal {
		position: fixed;
		top: 0;
		right: 0;
		bottom: 0;
		width: 420px;
		max-width: 100vw;
		background: var(--bg);
		color: var(--fg);
		z-index: 9999;
		display: flex;
		flex-direction: column;
		transform: translateX(100%);
		transition: transform var(--dur-slow) var(--ease-snap);
		border-left: 1px solid var(--border);
		font-family: var(--font-sans);
		font-size: 14px;
		letter-spacing: -0.16px;
	}
	.fkcart-modal.has-upsell {
		flex-direction: row;
		width: min(720px, 100vw);
	}
	.fkcart-upsell--desktop {
		flex: 0 0 300px;
		width: 300px;
		min-width: 0;
		display: flex;
		flex-direction: column;
		border-right: 1px solid var(--border);
		background: var(--bg-elevated, var(--bg));
		overflow: hidden;
	}
	.fkcart-upsell-mobile {
		display: none;
		flex: 0 0 auto;
		flex-shrink: 0;
		border-top: 1px solid var(--border);
		background: var(--bg);
	}
	.fkcart-upsell-mobile__toggle {
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: 10px;
		width: 100%;
		padding: 10px 16px;
		border: 0;
		background: transparent;
		color: var(--fg);
		font: inherit;
		font-size: 13px;
		font-weight: 600;
		letter-spacing: -0.15px;
		text-align: left;
		cursor: pointer;
	}
	.fkcart-upsell-mobile__toggle:hover {
		background: color-mix(in srgb, var(--accent) 6%, transparent);
	}
	.fkcart-upsell-mobile__chevron {
		flex-shrink: 0;
		color: var(--fg-muted);
		transition: transform var(--dur-fast) var(--ease);
	}
	.fkcart-upsell-mobile__chevron.is-open {
		transform: rotate(180deg);
	}
	.fkcart-upsell-mobile__panel {
		border-top: 1px solid var(--border);
	}
	.fkcart-upsell-mobile.is-collapsed .fkcart-upsell-mobile__toggle {
		border-bottom: 0;
	}
	.fkcart-upsell-mobile :global(.cart-xsell) {
		border-top: 0;
		padding: 6px 0 4px;
	}
	.fkcart-upsell-mobile :global(.cart-xsell__viewport) {
		padding: 0 12px;
	}
	.fkcart-upsell-mobile :global(.cart-xsell__progress) {
		display: none;
	}
	.fkcart-upsell-mobile :global(.cart-xsell__card) {
		flex: 0 0 116px;
		gap: 6px;
	}
	.fkcart-upsell-mobile :global(.cart-xsell__media) {
		aspect-ratio: 1 / 0.92;
	}
	.fkcart-upsell-mobile :global(.cart-xsell__body) {
		padding: 0 6px 6px;
		gap: 2px;
	}
	.fkcart-upsell-mobile :global(.cart-xsell__title) {
		min-height: 22px;
		font-size: 10px;
		line-height: 11px;
	}
	.fkcart-upsell-mobile :global(.cart-xsell__price-now) {
		font-size: 11px;
	}
	.fkcart-upsell-mobile :global(.cart-xsell__price-was) {
		font-size: 10px;
	}
	.fkcart-main {
		flex: 1 1 420px;
		min-width: 0;
		max-width: 420px;
		height: 100%;
		display: flex;
		flex-direction: column;
		min-height: 0;
	}
	@media (max-width: 720px) {
		.fkcart-modal.has-upsell {
			flex-direction: column;
			width: 100vw;
		}
		.fkcart-upsell--desktop {
			display: none;
		}
		.fkcart-upsell-mobile {
			display: block;
		}
		.fkcart-main {
			flex: 1 1 auto;
			max-width: none;
			width: 100%;
		}
	}
	@media (max-width: 520px) {
		.fkcart-modal { width: 100vw; border-left: 0; }
		.fkcart-modal.has-upsell { width: 100vw; }
	}
	.fkcart-modal.fkcart-show {
		transform: translateX(0);
	}

	/* =================================================================
	   Header
	   ================================================================= */

	.fkcart-header {
		display: flex;
		align-items: center;
		justify-content: space-between;
		padding: 22px 24px;
		border-bottom: 1px solid var(--border);
		flex-shrink: 0;
	}
	.fkcart-header__title {
		margin: 0;
		font-size: 13px;
		font-weight: 500;
		text-transform: uppercase;
		letter-spacing: 0.08em;
		color: var(--fg);
	}
	.fkcart-header__count {
		color: var(--fg-muted);
		font-weight: 450;
		margin-left: 4px;
	}
	.fkcart-close {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		width: 32px;
		height: 32px;
		padding: 0;
		background: transparent;
		border: 1px solid transparent;
		border-radius: var(--radius-sm);
		color: var(--fg);
		cursor: pointer;
		transition:
			background var(--dur-fast) var(--ease),
			border-color var(--dur-fast) var(--ease);
	}
	.fkcart-close:hover {
		background: var(--bg-muted);
		border-color: var(--border);
	}

	/* =================================================================
	   Body — items + empty state
	   ================================================================= */

	.fkcart-body {
		flex: 1 1 auto;
		min-height: 0;
		overflow-y: auto;
		overflow-x: hidden;
		-webkit-overflow-scrolling: touch;
		padding: 8px 0 0;
	}
	.fkcart-body.has-zero-state {
		display: flex;
		flex-direction: column;
		justify-content: center;
	}
	.fkcart-state {
		color: var(--fg-muted);
		font-size: 13px;
		padding: 24px;
		text-align: center;
	}
	.fkcart-empty {
		display: flex;
		flex-direction: column;
		align-items: center;
		gap: 20px;
		padding: 64px 24px;
		text-align: center;
	}
	.fkcart-empty__msg {
		font-size: 14px;
		color: var(--fg-muted);
		margin: 0;
	}
	.fkcart-empty__cta {
		padding: 12px 22px;
		background: var(--accent);
		color: var(--accent-fg);
		border: 1px solid var(--accent);
		border-radius: var(--radius-sm);
		font-family: inherit;
		font-size: 12px;
		font-weight: 500;
		text-transform: uppercase;
		letter-spacing: 0.08em;
		cursor: pointer;
		transition: background var(--dur-fast) var(--ease), color var(--dur-fast) var(--ease);
	}
	.fkcart-empty__cta:hover {
		background: transparent;
		color: var(--accent);
	}

	/* =================================================================
	   Items
	   ================================================================= */

	.fkcart-items {
		list-style: none;
		margin: 0;
		padding: 0;
		display: flex;
		flex-direction: column;
	}
	.fkcart-item {
		position: relative;
		display: grid;
		grid-template-columns: 88px 1fr;
		gap: 14px;
		padding: 18px 24px;
		border-bottom: 1px solid var(--border);
	}
	.fkcart-item:last-child { border-bottom: 0; }
	.fkcart-item.is-flashing {
		animation: wchs-flash var(--dur-slow) var(--ease) both;
	}
	.fkcart-item__media {
		width: 88px;
		height: 88px;
		background: color-mix(in srgb, var(--accent) 6%, var(--bg-muted));
		border: 1px solid color-mix(in srgb, var(--accent) 35%, var(--border));
		border-radius: var(--radius-md, 10px);
		overflow: hidden;
	}
	.fkcart-item__media img {
		width: 100%;
		height: 100%;
		object-fit: cover;
	}
	.fkcart-item__body {
		display: flex;
		flex-direction: column;
		gap: 12px;
		min-width: 0;
	}
	.fkcart-item__top {
		display: flex;
		align-items: flex-start;
		justify-content: space-between;
		gap: 12px;
	}
	.fkcart-item__info {
		flex: 1 1 auto;
		min-width: 0;
		display: flex;
		flex-direction: column;
		gap: 4px;
	}
	.fkcart-item__title {
		color: var(--accent);
		text-decoration: none;
		font-size: 15px;
		font-weight: 600;
		line-height: 1.25;
		letter-spacing: -0.25px;
		display: block;
		transition: color var(--dur-fast) var(--ease);
	}
	.fkcart-item__title:hover {
		color: color-mix(in srgb, var(--accent) 72%, var(--fg));
	}
	.fkcart-item__bundle {
		margin: 0;
		font-size: 13px;
		font-weight: 600;
		line-height: 1.2;
		color: var(--accent);
		letter-spacing: -0.1px;
	}
	.fkcart-item__unit {
		margin: 0;
		font-size: 12px;
		color: var(--fg-muted);
		font-weight: 450;
	}
	.fkcart-item__variation {
		list-style: none;
		padding: 0;
		margin: 0;
		font-size: 11px;
		color: var(--fg-muted);
		display: flex;
		flex-wrap: wrap;
		gap: 8px;
	}
	.fkcart-item__variation span {
		color: var(--fg);
		font-weight: 500;
	}
	.fkcart-item__price-col {
		flex: 0 0 auto;
		display: flex;
		flex-direction: column;
		align-items: flex-end;
		gap: 2px;
		text-align: right;
	}
	.fkcart-item__price {
		font-size: 15px;
		font-weight: 700;
		color: var(--fg);
		letter-spacing: -0.2px;
	}
	.fkcart-item__price-was {
		font-size: 12px;
		font-weight: 450;
		color: var(--fg-muted);
		text-decoration: line-through;
	}
	.fkcart-item__foot {
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: 12px;
	}
	.fkcart-item__remove {
		padding: 6px;
		border: 0;
		background: transparent;
		color: var(--fg-muted);
		cursor: pointer;
		display: inline-flex;
		align-items: center;
		justify-content: center;
		border-radius: var(--radius-sm);
		transition: color var(--dur-fast) var(--ease), background var(--dur-fast) var(--ease);
	}
	.fkcart-item__remove:hover {
		color: var(--fg);
		background: var(--bg-muted);
	}

	/* =================================================================
	   Quantity stepper — the bug fix.
	   Use a <span> for the value so we don't inherit number input chrome.
	   ================================================================= */

	.fkcart-qty {
		display: inline-flex;
		align-items: center;
		border: 1px solid color-mix(in srgb, var(--accent) 30%, var(--border));
		border-radius: var(--radius-sm);
		background: var(--bg);
		height: 30px;
	}
	.fkcart-qty__btn {
		width: 30px;
		height: 100%;
		padding: 0;
		border: 0;
		background: transparent;
		color: var(--fg);
		cursor: pointer;
		display: inline-flex;
		align-items: center;
		justify-content: center;
		transition: background var(--dur-fast) var(--ease);
	}
	.fkcart-qty__btn:hover {
		background: var(--bg-muted);
	}
	.fkcart-qty__btn:active svg {
		transform: scale(0.85);
	}
	.fkcart-qty__btn svg {
		transition: transform var(--dur-micro) var(--ease);
	}
	.fkcart-qty__value {
		min-width: 36px;
		padding: 0 4px;
		text-align: center;
		font-size: 13px;
		font-weight: 500;
		line-height: 1;
		color: var(--fg);
		border-left: 1px solid var(--border);
		border-right: 1px solid var(--border);
		user-select: none;
	}

	/* =================================================================
	   Footer — summary, checkout CTA
	   ================================================================= */

	.fkcart-footer {
		border-top: 1px solid var(--border);
		padding: 14px 20px 16px;
		display: flex;
		flex-direction: column;
		gap: 12px;
		background: var(--bg);
		flex-shrink: 0;
		margin-top: auto;
		box-shadow: 0 -6px 20px color-mix(in srgb, var(--fg) 6%, transparent);
	}
	.fkcart-summary {
		margin: 0;
		display: flex;
		flex-direction: column;
		gap: 6px;
		font-size: 14px;
	}
	.fkcart-summary__row {
		display: flex;
		justify-content: space-between;
	}
	.fkcart-summary__row dt {
		color: var(--fg-muted);
	}
	.fkcart-summary__row dd {
		margin: 0;
		color: var(--fg);
		font-weight: 500;
	}
	.fkcart-summary__row--savings dt {
		color: var(--success, #5ba238);
		font-weight: 500;
	}
	.fkcart-summary__row--savings .fkcart-summary__value--savings {
		color: var(--success, #5ba238);
		font-weight: 500;
	}
	.fkcart-checkout {
		display: block;
		padding: 14px 14px;
		border-radius: var(--radius-md, 10px);
		background: var(--accent);
		color: var(--accent-fg) !important;
		border: 1px solid var(--accent);
		border-radius: var(--radius-sm);
		text-decoration: none;
		text-align: center;
		font-family: inherit;
		font-size: 12px;
		font-weight: 600;
		text-transform: uppercase;
		letter-spacing: 0.1em;
		transition:
			background var(--dur-fast) var(--ease),
			color var(--dur-fast) var(--ease),
			transform var(--dur-fast) var(--ease);
	}
	.fkcart-checkout:hover {
		background: transparent;
		color: var(--accent) !important;
	}
	.fkcart-checkout:active {
		transform: scale(0.985);
	}

	.fkcart-ship-protect {
		display: flex;
		align-items: center;
		gap: 10px;
		padding: 10px 12px;
		border: 1px solid var(--border);
		border-radius: var(--radius-md, 8px);
		background: var(--bg);
	}
	.fkcart-ship-protect__icon {
		flex-shrink: 0;
		display: flex;
		align-items: center;
		justify-content: center;
		color: var(--accent);
	}
	.fkcart-ship-protect__copy {
		flex: 1 1 auto;
		min-width: 0;
	}
	.fkcart-ship-protect__title {
		display: block;
		font-size: 14px;
		font-weight: 700;
		line-height: 1.2;
		color: var(--fg);
		letter-spacing: -0.01em;
	}
	.fkcart-ship-protect__desc {
		margin: 2px 0 0;
		font-size: 12px;
		line-height: 1.35;
		color: var(--fg-muted);
	}
	.fkcart-ship-protect__price {
		flex-shrink: 0;
		font-size: 14px;
		font-weight: 600;
		color: var(--fg);
		white-space: nowrap;
	}
	.fkcart-ship-protect__skip {
		margin: 0;
		padding: 0;
		border: 0;
		background: none;
		font: inherit;
		font-size: 12px;
		color: var(--fg-muted);
		text-decoration: underline;
		text-underline-offset: 2px;
		cursor: pointer;
		align-self: center;
	}
	.fkcart-ship-protect__skip:hover:not(:disabled) {
		color: var(--fg);
	}
	.fkcart-ship-protect__skip:disabled {
		opacity: 0.6;
		cursor: wait;
	}

	.fkcart-trust {
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: 10px;
		padding-top: 10px;
		border-top: 1px solid var(--border);
	}
	.fkcart-trust__secure {
		display: inline-flex;
		align-items: center;
		gap: 6px;
		font-size: 12px;
		font-weight: 500;
		color: var(--success, #16a34a);
		white-space: nowrap;
	}
	.fkcart-trust__secure svg {
		stroke: currentColor;
	}
	.fkcart-trust__payments {
		display: inline-flex;
		align-items: center;
		gap: 5px;
		flex-wrap: wrap;
		justify-content: flex-end;
	}
	.fkcart-pay {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		min-width: 34px;
		height: 22px;
		padding: 0 6px;
		border-radius: 4px;
		font-size: 9px;
		font-weight: 700;
		letter-spacing: 0.02em;
		line-height: 1;
	}
	.fkcart-pay--visa {
		background: #1a1f71;
		color: #fff;
	}
	.fkcart-pay--mc {
		width: 34px;
		padding: 0;
		background: #000;
		position: relative;
	}
	.fkcart-pay--mc::before,
	.fkcart-pay--mc::after {
		content: '';
		position: absolute;
		top: 50%;
		width: 14px;
		height: 14px;
		border-radius: 50%;
		transform: translateY(-50%);
	}
	.fkcart-pay--mc::before {
		left: 8px;
		background: #eb001b;
	}
	.fkcart-pay--mc::after {
		right: 8px;
		background: #f79e1b;
	}
	.fkcart-pay--amex {
		background: #2e77bc;
		color: #fff;
	}
	.fkcart-pay--disc {
		background: #111;
		color: #fff;
	}
	.fkcart-pay--btc {
		background: #f7931a;
		color: #fff;
		font-size: 12px;
	}

	/* =================================================================
	   Motion keyframes
	   ================================================================= */

	@keyframes wchs-fade-in {
		from { opacity: 0; }
		to   { opacity: 1; }
	}

	/*
	 * Flash animates background-color + border-color together.
	 * background-color paints the full padding box; the explicit
	 * border-bottom-color transition avoids the 1px border seam.
	 * Uses a noticeably stronger color mix so the flash is actually
	 * visible on both dark and light themes — 10% was imperceptible.
	 */
	@keyframes wchs-flash {
		0% {
			background-color: transparent;
			border-bottom-color: var(--border);
		}
		20% {
			background-color: color-mix(in oklab, var(--fg) 22%, transparent);
			border-bottom-color: color-mix(in oklab, var(--fg) 30%, transparent);
		}
		100% {
			background-color: transparent;
			border-bottom-color: var(--border);
		}
	}

	/* Animated subtotal/total value — {#key} in template re-mounts the
	   <dd> wrapper on value change, triggering this animation fresh. */
	.fkcart-summary__value {
		display: inline-block;
		margin: 0;
		color: var(--fg);
		animation: wchs-value-bump var(--dur-fast) var(--ease-out);
	}
	@keyframes wchs-value-bump {
		0%   { opacity: 0; transform: translateY(-3px); }
		100% { opacity: 1; transform: translateY(0); }
	}

	/* Scroll lock: prevent body scroll while cart is open.
	   IMPORTANT: overflow:hidden on <html> causes a full-page repaint
	   (scrollbar disappears, viewport width snaps, GPU recomposites
	   every layer) which creates a visible black flash — especially
	   on pages with WebGL canvases and backdrop-filter elements.
	   Fix: lock on <body> instead and use scrollbar-gutter:stable
	   so the scrollbar space is preserved (no width snap). */
	:global(html.fkcart-trigger-open body) {
		overflow: hidden;
	}
	@supports (scrollbar-gutter: stable) {
		:global(html.fkcart-trigger-open) {
			scrollbar-gutter: stable;
		}
	}
</style>
