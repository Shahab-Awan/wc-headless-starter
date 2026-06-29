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
	import { onMount } from 'svelte';
	import CartCrossSellStrip from './CartCrossSellStrip.svelte';
	import CartBacWaterPrompt from './CartBacWaterPrompt.svelte';
	import CartCheckoutSocialProof from './CartCheckoutSocialProof.svelte';
	import CartRewardsMilestones from './CartRewardsMilestones.svelte';
	import { formatPrice } from '$lib/utils/format';
	import { config } from '$lib/config.svelte';
	import type { StoreApiCartItem } from '$lib/wc/cart.svelte';
	import {
		shippingProtectionFeeMajor,
		shippingProtectionTierIndex
	} from '$lib/shipping-protection';
	import { resolveCartLineQty } from '$lib/cart/bundle-qty';

	let fontsReady = $state(false);
	let checkouting = $state(false);
	let shipProtectBusy = $state(false);
	let checkoutPlusOn = $state(true);

	let shipProtectTierTracked = -1;
	let checkoutPlusTipOpen = $state(false);

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

	function resolvedQty(item: (typeof displayCartItems)[number], proposed: number): number {
		const thresholds = item.extensions?.wchs_cro?.tier_qty_thresholds ?? [];
		if (!thresholds.length) return Math.max(1, proposed);
		return resolveCartLineQty(thresholds, item.quantity, proposed);
	}

	function decrement(key: string, current: number) {
		const item = displayCartItems.find((i) => i.key === key);
		if (!item) return;
		const next = resolvedQty(item, current - 1);
		if (next <= 0 || (current <= 1 && next <= 1)) {
			void cart.removeItem(key);
			return;
		}
		flashKey(key);
		void cart.updateItem(key, next);
	}

	function increment(key: string, current: number) {
		const item = displayCartItems.find((i) => i.key === key);
		if (!item) return;
		const next = resolvedQty(item, current + 1);
		flashKey(key);
		void cart.updateItem(key, next);
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

	function formatMoney(minor: string | number, minorUnit: number, symbol: string, code = cart.currencyCode): string {
		return formatPrice(minor, { currency_minor_unit: minorUnit, currency_symbol: symbol, currency_code: code });
	}

	// Overload for integer-minor-unit values emitted by the wchs_cro extension.
	function formatMoneyInt(minorInt: number, appendCode = true): string {
		return formatPrice(
			minorInt,
			{
				currency_minor_unit: cart.currencyMinorUnit,
				currency_symbol: cart.currencySymbol,
				currency_code: cart.currencyCode,
			},
			appendCode
		);
	}

	function toMinorInt(value: unknown): number {
		const parsed =
			typeof value === 'number'
				? Math.round(value)
				: typeof value === 'string'
					? Number.parseInt(value, 10)
					: NaN;
		if (!Number.isFinite(parsed)) return 0;
		return Math.max(0, parsed);
	}

	function isShipProtectLine(item: StoreApiCartItem): boolean {
		if (item.extensions?.wchs_cro?.is_shipping_protection) return true;
		const pid = config.data.pdp?.slide_cart?.shipping_protection_product_id ?? 0;
		return pid > 0 && item.id === pid;
	}

	const shipProtectLine = $derived.by(() => {
		if (!cart.cart) return null;
		return cart.cart.items.find(isShipProtectLine) ?? null;
	});

	const hasShipProtect = $derived(shipProtectLine !== null);

	const displayCartItems = $derived.by(() => {
		if (!cart.cart) return [];
		return cart.cart.items.filter((i) => !isShipProtectLine(i));
	});

	const shipProtectAvailable = $derived(
		(config.data.pdp?.slide_cart?.shipping_protection_product_id ?? 0) > 0
	);

	function lineSavingsMinor(item: StoreApiCartItem): number {
		const cro = item.extensions?.wchs_cro;
		if (typeof cro?.savings_line_total === 'number' && cro.savings_line_total > 0) {
			return cro.savings_line_total;
		}
		const compare =
			cro?.compare_line_minor ??
			(cro?.regular_unit_price ?? Number(item.prices.regular_price)) * item.quantity;
		const line = cro?.line_total_minor ?? Number(item.totals.line_total);
		return Math.max(0, compare - line);
	}

	/** One cart-wide savings total — sum of every visible line, not per-product footer rows. */
	const cartTotalSavings = $derived.by(() => {
		return displayCartItems.reduce((sum, item) => sum + lineSavingsMinor(item), 0);
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
			minor += toMinorInt(item.totals.line_total ?? 0);
		}
		return Math.max(0, minor) / Math.pow(10, mu);
	});

	const shipProtectFeeMinor = $derived.by(() => {
		const fromApi = cart.cart?.extensions?.wchs_cro?.shipping_protection?.fee_minor;
		const fromApiMinor = toMinorInt(fromApi);
		if (fromApiMinor > 0) return fromApiMinor;

		const croFee = shipProtectLine?.extensions?.wchs_cro?.fee_minor;
		const croFeeMinor = toMinorInt(croFee);
		if (croFeeMinor > 0) return croFeeMinor;

		const major = shippingProtectionFeeMajor(shipProtectSubtotalMajor);
		return Math.max(0, Math.round(major * Math.pow(10, cart.currencyMinorUnit || 2)));
	});

	const shipProtectPriceLabel = $derived.by(() => {
		return formatPrice(shipProtectFeeMinor, {
			currency_minor_unit: cart.currencyMinorUnit,
			currency_symbol: cart.currencySymbol,
			currency_code: cart.currencyCode
		}, false);
	});

	const checkoutDueLabel = $derived.by(() => {
		if (checkouting) return 'Loading…';
		const itemsMinor = toMinorInt(cart.cart?.totals?.total_items ?? cart.subtotal);
		const itemsTaxMinor = toMinorInt(cart.cart?.totals?.total_items_tax);
		const checkoutDueMinor = Math.max(0, itemsMinor + itemsTaxMinor);
		const formatted = formatMoney(checkoutDueMinor, cart.currencyMinorUnit, cart.currencySymbol);
		const prefix = checkoutPlusOn ? 'Checkout+' : 'Checkout';
		return `${prefix} | ${formatted}`;
	});

	async function addShippingProtection() {
		const pid = config.data.pdp?.slide_cart?.shipping_protection_product_id;
		if (!pid || shipProtectBusy || hasShipProtect) return;
		shipProtectBusy = true;
		try {
			await cart.addItem(pid, 1, [], {
				clicked_from: 'slide_cart_ship_protect_toggle'
			});
			await cart.fetch().catch(() => {});
		} finally {
			shipProtectBusy = false;
		}
	}

	async function removeShippingProtection() {
		if (shipProtectBusy || !shipProtectLine) return;
		shipProtectBusy = true;
		try {
			await cart.removeItem(shipProtectLine.key);
			await cart.fetch().catch(() => {});
		} finally {
			shipProtectBusy = false;
		}
	}

	async function setCheckoutPlusOn(on: boolean) {
		if (shipProtectBusy || checkouting || checkoutPlusOn === on) return;
		checkoutPlusOn = on;
		if (on) {
			await addShippingProtection();
		} else {
			await removeShippingProtection();
		}
	}

	function toggleCheckoutPlusTip() {
		checkoutPlusTipOpen = !checkoutPlusTipOpen;
	}

	$effect(() => {
		if (cart.itemCount === 0) {
			checkoutPlusOn = true;
		}
	});

	$effect(() => {
		if (!cart.open || !checkoutPlusOn || hasShipProtect || shipProtectBusy) return;
		if (!shipProtectAvailable) return;
		if (displayCartItems.length === 0) return;
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

	{#if cart.cart && visibleItemCount > 0}
		<div class="fkcart-social-wrap">
			<CartCheckoutSocialProof />
			<CartRewardsMilestones />
		</div>
	{/if}

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
					{@const isFreeGift = cro?.is_free_bac_gift === true}
					<li class="fkcart-item" class:is-flashing={isFlashing} class:is-free-gift={isFreeGift}>
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

									{#if cro?.bundle_label}
										<p class="fkcart-item__bundle">{cro.bundle_label}</p>
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
									{#if isFreeGift}
										{#if showCompare}
											<span class="fkcart-item__price-was">{formatMoneyInt(compareMinor)}</span>
										{/if}
										<span class="fkcart-item__price fkcart-item__price--free">FREE</span>
									{:else}
										{#if showCompare}
											<span class="fkcart-item__price-was">{formatMoneyInt(compareMinor)}</span>
										{/if}
										<span class="fkcart-item__price">{formatMoneyInt(lineMinor)}</span>
									{/if}
								</div>
							</div>

							<div class="fkcart-item__foot">
								{#if !isFreeGift && !item.sold_individually && item.quantity_limits.editable}
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

			<CartBacWaterPrompt />
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
		<footer class="fkcart-footer">
			{#if cartTotalSavings > 0}
				<p class="fkcart-savings tabular-nums">
					{#key cartTotalSavings}
						You're saving {formatMoneyInt(cartTotalSavings, false)} vs market retail.
					{/key}
				</p>
			{/if}

			{#if shipProtectAvailable && displayCartItems.length > 0}
				<div class="fkcart-ship-protect" aria-live="polite">
					<div class="fkcart-ship-protect__icon" aria-hidden="true">
						<svg viewBox="0 0 24 24" width="28" height="28" fill="currentColor">
							<path
								d="M12 2 4 5v6c0 5.25 3.44 10.15 8 11.35.55.14 1.12.14 1.67 0 4.56-1.2 8-6.1 8-11.35V5l-8-3zm-1.2 14.2-2.5-2.5 1.4-1.4.9.9 3.3-3.3 1.4 1.4-4.5 4.5z"
							/>
						</svg>
					</div>
					<div class="fkcart-ship-protect__copy">
						<div class="fkcart-ship-protect__title-row">
							<span class="fkcart-ship-protect__title">Checkout+</span>
							<span class="fkcart-ship-protect__help-wrap" class:is-open={checkoutPlusTipOpen}>
								<button
									type="button"
									class="fkcart-ship-protect__help"
									aria-label="What is Checkout+?"
									aria-expanded={checkoutPlusTipOpen}
									onclick={toggleCheckoutPlusTip}
								>
									?
								</button>
								<span class="fkcart-ship-protect__tooltip" role="tooltip">
									<span class="fkcart-ship-protect__tooltip-q">What is Checkout+?</span>
									<span class="fkcart-ship-protect__tooltip-a"
										>It covers lost/damaged packages. Free returns within 30 days.</span
									>
								</span>
							</span>
						</div>
						<p class="fkcart-ship-protect__desc">Free returns + package protection</p>
					</div>
					<div class="fkcart-ship-protect__aside">
						<button
							type="button"
							class="fkcart-ship-protect__toggle"
							class:is-on={checkoutPlusOn}
							role="switch"
							aria-checked={checkoutPlusOn}
							aria-label="Toggle Checkout+ package protection"
							disabled={shipProtectBusy || checkouting}
							onclick={() => void setCheckoutPlusOn(!checkoutPlusOn)}
						>
							<span class="fkcart-ship-protect__toggle-track" aria-hidden="true">
								<span class="fkcart-ship-protect__toggle-thumb"></span>
							</span>
						</button>
						<span class="fkcart-ship-protect__price tabular-nums">{shipProtectPriceLabel}</span>
					</div>
				</div>
			{/if}

			<a
				href={cart.checkoutUrl()}
				class="fkcart-checkout"
				data-sveltekit-reload
				aria-busy={checkouting}
				onclick={beginCheckout}
			>
				{checkoutDueLabel}
			</a>

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
		max-width: 100vw;
	}
	.fkcart-upsell--desktop {
		flex: 0 0 clamp(240px, 32vw, 300px);
		width: clamp(240px, 32vw, 300px);
		min-width: 0;
		max-width: 42%;
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
		max-width: min(420px, 58vw);
		width: min(420px, 58vw);
		height: 100%;
		display: flex;
		flex-direction: column;
		min-height: 0;
	}
	@media (max-width: 900px) and (min-width: 721px) {
		.fkcart-modal.has-upsell {
			width: 100vw;
		}
		.fkcart-upsell--desktop {
			flex: 0 0 min(36vw, 260px);
			width: min(36vw, 260px);
		}
		.fkcart-main {
			flex: 1 1 auto;
			width: auto;
			max-width: none;
		}
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
	.fkcart-social-wrap {
		flex-shrink: 0;
		min-width: 0;
		max-width: 100%;
		padding: 12px 20px 4px;
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
	.fkcart-item__price--free {
		color: var(--accent);
		text-transform: uppercase;
		letter-spacing: 0.06em;
		font-size: 13px;
	}
	.fkcart-item.is-free-gift {
		background: color-mix(in srgb, var(--accent) 5%, var(--bg));
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
	.fkcart-savings {
		margin: 0 0 12px;
		font-size: 14px;
		font-weight: 500;
		line-height: 1.35;
		text-align: center;
		color: var(--success, #5ba238);
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
	.fkcart-checkout {
		display: block;
		padding: 14px 16px;
		border-radius: 999px;
		background: var(--accent);
		color: var(--accent-fg) !important;
		border: 1px solid var(--accent);
		text-decoration: none;
		text-align: center;
		font-family: inherit;
		font-size: 14px;
		font-weight: 600;
		letter-spacing: -0.01em;
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
	.fkcart-ship-protect__title-row {
		display: flex;
		align-items: center;
		gap: 6px;
		flex-wrap: wrap;
	}
	.fkcart-ship-protect__help-wrap {
		position: relative;
		display: inline-flex;
		align-items: center;
	}
	.fkcart-ship-protect__help {
		width: 18px;
		height: 18px;
		padding: 0;
		border: 1px solid var(--border);
		border-radius: 50%;
		background: transparent;
		color: var(--fg-muted);
		font: inherit;
		font-size: 11px;
		font-weight: 700;
		line-height: 1;
		cursor: pointer;
	}
	.fkcart-ship-protect__help:hover,
	.fkcart-ship-protect__help:focus-visible {
		border-color: var(--accent);
		color: var(--accent);
		outline: none;
	}
	.fkcart-ship-protect__tooltip {
		display: none;
		position: absolute;
		left: 50%;
		bottom: calc(100% + 8px);
		transform: translateX(-50%);
		width: max-content;
		max-width: min(240px, 70vw);
		padding: 8px 10px;
		border: 1px solid var(--border);
		border-radius: var(--radius-sm, 6px);
		background: var(--bg);
		color: var(--fg);
		font-size: 11px;
		line-height: 1.4;
		text-align: left;
		box-shadow: 0 4px 16px color-mix(in srgb, var(--fg) 12%, transparent);
		z-index: 2;
		pointer-events: none;
	}
	.fkcart-ship-protect__tooltip-q {
		display: block;
		font-weight: 600;
		margin-bottom: 4px;
	}
	.fkcart-ship-protect__tooltip-a {
		display: block;
		color: var(--fg-muted);
	}
	.fkcart-ship-protect__help-wrap:hover .fkcart-ship-protect__tooltip,
	.fkcart-ship-protect__help-wrap:focus-within .fkcart-ship-protect__tooltip,
	.fkcart-ship-protect__help-wrap.is-open .fkcart-ship-protect__tooltip {
		display: block;
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
	.fkcart-ship-protect__aside {
		flex-shrink: 0;
		display: flex;
		flex-direction: column;
		align-items: flex-end;
		gap: 6px;
	}
	.fkcart-ship-protect__toggle {
		padding: 0;
		border: 0;
		background: transparent;
		cursor: pointer;
		line-height: 0;
	}
	.fkcart-ship-protect__toggle:disabled {
		opacity: 0.55;
		cursor: wait;
	}
	.fkcart-ship-protect__toggle-track {
		display: block;
		width: 40px;
		height: 22px;
		border-radius: 999px;
		background: color-mix(in srgb, var(--fg) 18%, var(--border));
		position: relative;
		transition: background 150ms var(--ease-out);
	}
	.fkcart-ship-protect__toggle.is-on .fkcart-ship-protect__toggle-track {
		background: var(--accent);
	}
	.fkcart-ship-protect__toggle-thumb {
		position: absolute;
		top: 2px;
		left: 2px;
		width: 18px;
		height: 18px;
		border-radius: 50%;
		background: var(--bg);
		box-shadow: 0 1px 2px color-mix(in srgb, var(--fg) 18%, transparent);
		transition: transform 150ms var(--ease-out);
	}
	.fkcart-ship-protect__toggle.is-on .fkcart-ship-protect__toggle-thumb {
		transform: translateX(18px);
	}
	.fkcart-ship-protect__price {
		flex-shrink: 0;
		font-size: 14px;
		font-weight: 600;
		color: var(--fg);
		white-space: nowrap;
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
