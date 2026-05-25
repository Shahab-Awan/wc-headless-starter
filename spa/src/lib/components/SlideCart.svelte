<script lang="ts">
	/**
	 * SlideCart — Slide-out cart drawer in native Svelte 5.
	 *
	 * Behavior spec: docs/cart-spec.md
	 * Design: Runway-inspired dark-dominant, tight type, zero shadows.
	 *
	 * Free-tier FK parity: slide, items, qty stepper, remove, coupon,
	 * subtotal, checkout CTA. Upsells/rewards are Pro features — separate
	 * sibling components if/when we add them.
	 */
	import { cart } from '$lib/wc/cart.svelte';
	import { config } from '$lib/config.svelte';
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

	let couponCode = $state('');
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
		if (checkouting) return;

		checkouting = true;
		try {
			window.location.href = await cart.beginCheckout();
		} finally {
			checkouting = false;
		}
	}

	async function decrement(key: string, current: number) {
		if (current <= 1) {
			await cart.removeItem(key);
		} else {
			flashKey(key);
			await cart.updateItem(key, current - 1);
		}
	}

	async function increment(key: string, current: number) {
		flashKey(key);
		await cart.updateItem(key, current + 1);
	}

	function flashKey(key: string) {
		flashedKeys = { ...flashedKeys, [key]: Date.now() };
		setTimeout(() => {
			flashedKeys = Object.fromEntries(Object.entries(flashedKeys).filter(([k]) => k !== key));
		}, 500);
	}

	async function applyCoupon(e: Event) {
		e.preventDefault();
		const code = couponCode.trim();
		if (!code) return;
		await cart.applyCoupon(code);
		couponCode = '';
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

	function formatPct(p: number): string {
		// Trim trailing .0 for integer percents
		return Number.isInteger(p) ? `${p}%` : `${p.toFixed(1)}%`;
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
		if (shipProtectLine) {
			return Number(shipProtectLine.totals.line_total ?? 0);
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
		} finally {
			shipProtectBusy = false;
		}
	}

	async function removeShippingProtection() {
		if (!shipProtectLine || shipProtectBusy) return;
		shipProtectBusy = true;
		try {
			await cart.removeItem(shipProtectLine.key);
			shipProtectDeclined = true;
			if (browser) {
				sessionStorage.setItem(SHIP_PROTECT_DECLINED_KEY, '1');
			}
		} finally {
			shipProtectBusy = false;
		}
	}

	$effect(() => {
		if (!cart.open || cart.itemCount === 0) return;
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
	aria-label="Shopping cart"
	aria-hidden={!cart.open}
>
	<header class="fkcart-header">
		<h2 class="fkcart-header__title">
			Cart
			<span class="fkcart-header__count tabular-nums">({visibleItemCount || cart.itemCount})</span>
		</h2>
		<button type="button" class="fkcart-close" onclick={close} aria-label="Close cart">
			<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
				<path d="M6 6l12 12M18 6L6 18" />
			</svg>
		</button>
	</header>

	<div class="fkcart-body" class:has-zero-state={cart.itemCount === 0}>
		{#if cart.loading && !cart.cart}
			<p class="fkcart-state">Loading…</p>
		{:else if cart.itemCount === 0}
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
					<li class="fkcart-item" class:is-flashing={isFlashing}>
						<div class="fkcart-item__media">
							{#if item.images[0]}
								<img
									src={item.images[0].thumbnail}
									alt={item.images[0].alt || item.name}
									loading="lazy"
								/>
							{/if}
							<button
								type="button"
								class="fkcart-item__remove"
								onclick={() => cart.removeItem(item.key)}
								aria-label="Remove {item.name}"
							>
								<svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round">
									<path d="M6 6l12 12M18 6L6 18" />
								</svg>
							</button>
						</div>

						<div class="fkcart-item__body">
							<a
								class="fkcart-item__title"
								href={item.permalink}
								style={h !== null ? `min-height: ${h}px` : ''}
							>{item.name}</a>

							{#if item.variation.length}
								<ul class="fkcart-item__variation">
									{#each item.variation as v}
										<li>{v.attribute}: <span>{v.value}</span></li>
									{/each}
								</ul>
							{/if}

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
								<div class="fkcart-item__price-stack">
									{#if cro && cro.savings_per_unit > 0}
										<span class="fkcart-item__price-was tabular-nums"
											>{formatMoneyInt(cro.regular_unit_price * item.quantity)}</span
										>
									{/if}
									<span class="fkcart-item__price tabular-nums">
										{formatMoney(item.totals.line_total, cart.currencyMinorUnit, cart.currencySymbol)}
									</span>
								</div>
							</div>

							{#if cro && cro.savings_per_unit > 0}
								<p class="fkcart-item__saved">
									You saved {formatMoneyInt(cro.savings_line_total)}
									<span class="fkcart-item__saved-pct"
										>({formatPct(cro.savings_pct)} off)</span
									>
								</p>
							{/if}

							{#if cro?.next_tier}
								{@const nt = cro.next_tier}
								<button
									type="button"
									class="fkcart-item__next-tier"
									onclick={() => cart.updateItem(item.key, nt.next_min_qty)}
								>
									<span class="fkcart-item__next-tier-arrow">+</span>
									<span class="fkcart-item__next-tier-text">
										Add {nt.qty_needed}&nbsp;more to save {formatPct(nt.next_savings_pct)}
										<small>{formatMoneyInt(nt.next_unit_price)} each at qty {nt.next_min_qty}</small>
									</span>
								</button>
							{/if}
						</div>
					</li>
				{/each}
			</ul>

			{@const cartCroInline = cart.cart.extensions?.wchs_cro}
			{#if cartCroInline?.cross_sell_ids && cartCroInline.cross_sell_ids.length > 0}
				<CartCrossSellStrip ids={cartCroInline.cross_sell_ids} />
			{/if}
		{/if}

	{#if cart.cart && cart.itemCount > 0}
		{@const cartCro = cart.cart.extensions?.wchs_cro}
		{@const hasSavings = !!cartCro && cartCro.total_savings > 0}
		{@const couponDiscount = Number(cart.cart.totals.total_discount ?? '0')}
		{@const hasCoupon = couponDiscount > 0}
		{@const freeShipThreshold = Number(config.data.shipping_free_threshold ?? 0)}
		{@const subtotalMajor = Number(cart.subtotal ?? 0) / Math.pow(10, cart.currencyMinorUnit || 2)}
		{@const freeShipEligible = freeShipThreshold > 0 && subtotalMajor > 0}
		{@const freeShipRemaining = Math.max(0, freeShipThreshold - subtotalMajor)}
		{@const freeShipUnlocked = freeShipEligible && subtotalMajor >= freeShipThreshold}
		{@const freeShipPct = freeShipEligible ? Math.min(100, (subtotalMajor / freeShipThreshold) * 100) : 0}
		<footer class="fkcart-footer">
			{#if freeShipEligible}
				<div class="fkcart-freeship" class:is-unlocked={freeShipUnlocked} data-testid="freeship-bar">
					<p class="fkcart-freeship__copy" data-testid="freeship-copy">
						{#if freeShipUnlocked}
							🎉 You've unlocked FREE shipping
						{:else}
							Add {formatPrice(freeShipRemaining * Math.pow(10, cart.currencyMinorUnit || 2), { currency_minor_unit: cart.currencyMinorUnit, currency_symbol: cart.currencySymbol, currency_code: cart.currencyCode })} more for FREE shipping
						{/if}
					</p>
					<div class="fkcart-freeship__track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow={Math.round(freeShipPct)}>
						<div class="fkcart-freeship__fill" style="width: {freeShipPct}%" data-testid="freeship-fill-pct={Math.round(freeShipPct)}"></div>
					</div>
				</div>
			{/if}
			<form class="fkcart-coupon" onsubmit={applyCoupon}>
				<input
					type="text"
					bind:value={couponCode}
					placeholder="Coupon code"
					aria-label="Coupon code"
				/>
				<button type="submit">Apply</button>
			</form>

			<dl class="fkcart-summary tabular-nums">
				<div class="fkcart-summary__row">
					<dt>Subtotal</dt>
					{#key cart.subtotal}
						<dd class="fkcart-summary__value">
							{formatMoney(cart.subtotal, cart.currencyMinorUnit, cart.currencySymbol)}
						</dd>
					{/key}
				</div>
				{#if hasCoupon}
					<div class="fkcart-summary__row fkcart-summary__row--savings">
						<dt>Coupon discount</dt>
						<dd class="fkcart-summary__value fkcart-summary__value--savings">
							−{formatMoneyInt(couponDiscount)}
						</dd>
					</div>
				{/if}
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
				Checkout
			</a>

			{#if hasShipProtect}
				<button
					type="button"
					class="fkcart-ship-protect__skip"
					disabled={shipProtectBusy}
					onclick={removeShippingProtection}
				>
					Continue without shipping protection
				</button>
			{/if}
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
	@media (max-width: 520px) {
		.fkcart-modal { width: 100vw; border-left: 0; }
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
		overflow-y: auto;
		/* Horizontal padding moved onto .fkcart-item so the flash
		   animation can span the full drawer width without clipping */
		padding: 8px 0 16px;
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
		grid-template-columns: 72px 1fr;
		gap: 16px;
		/* Internal padding so the item spans the full drawer width.
		   This lets the flash animation cover edge-to-edge. */
		padding: 16px 24px;
		border-bottom: 1px solid var(--border);
	}
	.fkcart-item:last-child { border-bottom: 0; }
	/*
	 * Flash animation — inset box-shadow + border-color in the same
	 * animation so the 1px border participates in the flash too.
	 */
	.fkcart-item.is-flashing {
		animation: wchs-flash var(--dur-slow) var(--ease) both;
	}
	.fkcart-item__media {
		position: relative;
		width: 72px;
		height: 72px;
		background: var(--bg-muted);
		border-radius: var(--radius-sm);
		overflow: visible;
	}
	.fkcart-item__media img {
		width: 100%;
		height: 100%;
		object-fit: cover;
		border-radius: var(--radius-sm);
	}
	.fkcart-item__remove {
		position: absolute;
		top: -6px;
		right: -6px;
		width: 20px;
		height: 20px;
		padding: 0;
		border: 0;
		background: var(--fg);
		color: var(--bg);
		border-radius: 999px;
		display: inline-flex;
		align-items: center;
		justify-content: center;
		cursor: pointer;
		opacity: 0;
		transform: scale(0.85);
		transition:
			opacity var(--dur-fast) var(--ease),
			transform var(--dur-fast) var(--ease);
	}
	.fkcart-item:hover .fkcart-item__remove {
		opacity: 1;
		transform: scale(1);
	}
	.fkcart-item__remove:hover {
		transform: scale(1.08);
	}

	.fkcart-item__body {
		display: flex;
		flex-direction: column;
		gap: 8px;
		min-width: 0;  /* allow title truncation */
	}
	.fkcart-item__title {
		color: var(--fg);
		text-decoration: none;
		font-size: 14px;
		font-weight: 500;
		line-height: 18px;
		letter-spacing: -0.2px;
		display: block;
		overflow: hidden;
	}
	.fkcart-item__title:hover {
		color: var(--fg-muted);
	}
	.fkcart-item__variation {
		list-style: none;
		padding: 0;
		margin: 0;
		font-size: 11px;
		color: var(--fg-muted);
		text-transform: uppercase;
		letter-spacing: 0.06em;
		font-weight: 450;
		display: flex;
		gap: 10px;
	}
	.fkcart-item__variation span {
		color: var(--fg);
		font-weight: 500;
		text-transform: none;
		letter-spacing: -0.16px;
	}
	.fkcart-item__foot {
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: 12px;
		margin-top: auto;
		padding-top: 4px;
	}

	/* =================================================================
	   Quantity stepper — the bug fix.
	   Use a <span> for the value so we don't inherit number input chrome.
	   ================================================================= */

	.fkcart-qty {
		display: inline-flex;
		align-items: center;
		border: 1px solid var(--border);
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

	.fkcart-item__price-stack {
		display: inline-flex;
		align-items: baseline;
		gap: 8px;
	}
	.fkcart-item__price {
		font-size: 14px;
		font-weight: 500;
		color: var(--fg);
		letter-spacing: -0.2px;
	}
	.fkcart-item__price-was {
		font-size: 12px;
		font-weight: 450;
		color: var(--fg-muted);
		text-decoration: line-through;
		text-decoration-thickness: 1px;
		text-decoration-color: currentColor;
	}

	.fkcart-item__saved {
		margin: 6px 0 0;
		padding-top: 6px;
		border-top: 1px dashed var(--border);
		font-size: 11px;
		font-weight: 500;
		text-transform: uppercase;
		letter-spacing: 0.06em;
		color: var(--success, #5ba238);
	}
	.fkcart-item__saved-pct {
		color: var(--fg-muted);
		font-weight: 450;
		margin-left: 2px;
	}

	.fkcart-item__next-tier {
		display: flex;
		align-items: center;
		gap: 10px;
		margin-top: 8px;
		padding: 10px 12px;
		width: 100%;
		background: transparent;
		border: 1px dashed var(--border);
		border-radius: var(--radius-sm);
		color: var(--fg);
		font: inherit;
		text-align: left;
		cursor: pointer;
		transition:
			border-color var(--dur-fast) var(--ease),
			background var(--dur-fast) var(--ease);
	}
	.fkcart-item__next-tier:hover {
		border-color: var(--fg);
		border-style: solid;
		background: var(--bg-elevated);
	}
	.fkcart-item__next-tier-arrow {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		width: 22px;
		height: 22px;
		border: 1px solid var(--fg-muted);
		border-radius: 999px;
		color: var(--fg);
		font-size: 14px;
		line-height: 1;
		flex-shrink: 0;
	}
	.fkcart-item__next-tier-text {
		display: flex;
		flex-direction: column;
		gap: 2px;
		font-size: 11px;
		font-weight: 500;
		text-transform: uppercase;
		letter-spacing: 0.06em;
		color: var(--fg);
	}
	.fkcart-item__next-tier-text small {
		font-size: 10px;
		font-weight: 450;
		text-transform: none;
		letter-spacing: 0;
		color: var(--fg-muted);
	}

	/* =================================================================
	   Footer — coupon, summary, checkout CTA
	   ================================================================= */

	.fkcart-footer {
		border-top: 1px solid var(--border);
		padding: 20px 24px 24px;
		display: flex;
		flex-direction: column;
		gap: 16px;
		background: var(--bg);
		flex-shrink: 0;
	}
	.fkcart-freeship {
		display: flex;
		flex-direction: column;
		gap: 8px;
	}
	.fkcart-freeship__copy {
		font-size: 12px;
		font-weight: 500;
		color: var(--fg);
		margin: 0;
		letter-spacing: 0;
		line-height: 1.3;
	}
	.fkcart-freeship__track {
		height: 6px;
		background: color-mix(in srgb, var(--fg) 10%, transparent);
		border-radius: 3px;
		overflow: hidden;
	}
	.fkcart-freeship__fill {
		height: 100%;
		background: var(--accent, #ffdd24);
		border-radius: 3px;
		transition: width 280ms var(--ease, ease-out);
	}
	.fkcart-freeship.is-unlocked .fkcart-freeship__copy {
		color: color-mix(in srgb, var(--accent, #22c55e) 90%, var(--fg) 10%);
	}
	.fkcart-coupon {
		display: flex;
		gap: 8px;
	}
	.fkcart-coupon input {
		flex: 1 1 auto;
		padding: 10px 14px;
		background: var(--bg);
		color: var(--fg);
		border: 1px solid var(--border);
		border-radius: var(--radius-sm);
		font: inherit;
		font-size: 13px;
		transition: border-color var(--dur-fast) var(--ease);
	}
	.fkcart-coupon input::placeholder { color: var(--fg-muted); }
	.fkcart-coupon input:focus {
		outline: none;
		border-color: var(--fg);
	}
	.fkcart-coupon button {
		padding: 10px 16px;
		background: transparent;
		border: 1px solid var(--border);
		border-radius: var(--radius-sm);
		color: var(--fg);
		font: inherit;
		font-size: 11px;
		font-weight: 500;
		text-transform: uppercase;
		letter-spacing: 0.08em;
		cursor: pointer;
		transition:
			background var(--dur-fast) var(--ease),
			border-color var(--dur-fast) var(--ease);
	}
	.fkcart-coupon button:hover {
		background: var(--fg);
		color: var(--bg);
		border-color: var(--fg);
	}

	.fkcart-summary {
		margin: 0;
		display: flex;
		flex-direction: column;
		gap: 4px;
		font-size: 13px;
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
		padding: 15px 16px;
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
		gap: 12px;
		padding: 12px 14px;
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
