<script lang="ts">
	/**
	 * CartCrossSellStrip — horizontal "you might also like" rail inside the
	 * slide cart. Consumes `extensions.wchs_cro.cross_sell_ids` from the
	 * cart response and renders up to 4 product cards with a one-click add
	 * button. The server-side union already drops products already in the
	 * cart. BAC water, protected shipping, and admin-configured exclusions
	 * are stripped server-side and again here as a safety net.
	 */
	import { goto } from '$app/navigation';
	import { onMount } from 'svelte';
	import EmblaCarousel, { type EmblaCarouselType } from 'embla-carousel';
	import { cart } from '$lib/wc/cart.svelte';
	import {
		getProductsByIds,
		getVariations,
		findPurchasableDefaultSelection,
		type StoreProduct,
		type StoreProductVariation,
	} from '$lib/wc/products';
	import { canPurchase } from '$lib/wc/stock';

	import { fade, fly } from 'svelte/transition';
	import {
		CART_CROSS_SELL_TARGET_COUNT,
		config,
		isCatalogHiddenProduct,
	} from '$lib/config.svelte';
	import { formatPrice } from '$lib/utils/format';

	let {
		ids,
		layout = 'strip',
		title = 'Frequently Bought Together',
		hideHeading = false,
	}: {
		ids: number[];
		layout?: 'strip' | 'sidebar';
		title?: string;
		hideHeading?: boolean;
	} = $props();

	const isSidebar = $derived(layout === 'sidebar');

	const mode = $derived(config.data.pdp?.cross_sell_mode ?? 'simple');
	const recommendIds = $derived(
		ids.filter((id) => !isCatalogHiddenProduct(id)).slice(0, CART_CROSS_SELL_TARGET_COUNT)
	);

	// Modal state for simple mode variable products
	let modalProduct = $state<StoreProduct | null>(null);
	let modalState = $state<MiniState | null>(null);
	let modalVariations = $state<StoreProductVariation[]>([]);

	async function openModal(product: StoreProduct) {
		modalProduct = product;
		modalState = { ...getState(product), stepIdx: 0 };
		modalVariations = [];
		if (product.has_options && product.variations.length) {
			modalVariations = await getVariations(product.variations.map((v) => v.id));
			const defaults = findPurchasableDefaultSelection(product, modalVariations);
			if (defaults && modalState) {
				modalState = { ...modalState, attrs: defaults };
			}
		}
	}

	function closeModal() {
		modalProduct = null;
		modalState = null;
		modalVariations = [];
	}

	// Check if a specific attribute value has any in-stock variation
	function modalOptionAvailable(product: StoreProduct, attrName: string, value: string, currentAttrs: Record<string, string>): boolean {
		if (!modalVariations.length) return true; // data not loaded yet, allow all
		const keys = getAttrKeys(product);
		for (const vRef of product.variations) {
			const thisAttr = vRef.attributes.find(a => a.name === attrName);
			if (!thisAttr || thisAttr.value !== value) continue;
			// Check other selected attrs match
			const otherOk = vRef.attributes.every(a => {
				if (a.name === attrName) return true;
				const chosen = currentAttrs[a.name];
				return !chosen || chosen === a.value;
			});
			if (!otherOk) continue;
			// Check stock from full variation data
			const fullVar = modalVariations.find(v => v.id === vRef.id);
			if (fullVar && canPurchase(fullVar)) return true;
		}
		return false;
	}

	type MiniState = {
		attrs: Record<string, string>;
		qty: number;
		stepIdx: number;
		justAdded: boolean;
	};

	let products = $state<StoreProduct[]>([]);
	let loading = $state(false);
	let loadedForIds = $state('');
	let fetchGeneration = 0;
	let addingId = $state<number | null>(null);
	let miniStates = $state<Map<number, MiniState>>(new Map());

	function getState(product: StoreProduct): MiniState {
		if (!miniStates.has(product.id)) {
			// Use WC default attributes if set, else fall back to first option
			const attrs: Record<string, string> = {};
			for (const attr of product.attributes ?? []) {
				const def = attr.terms.find(t => t.default);
				if (def) {
					attrs[attr.name] = def.name;
				} else if (attr.terms.length > 0) {
					attrs[attr.name] = attr.terms[0].name;
				}
			}
			const steps = getSteps(product);
			miniStates.set(product.id, {
				attrs,
				qty: 1,
				stepIdx: steps.length - 1, // start at quantity since all attrs are defaulted
				justAdded: false,
			});
		}
		return miniStates.get(product.id)!;
	}

	function getAttrKeys(product: StoreProduct): string[] {
		return (product.attributes ?? []).map(a => a.name);
	}

	function getSteps(product: StoreProduct): Array<{ type: 'attribute'; key: string } | { type: 'quantity' }> {
		const steps: Array<{ type: 'attribute'; key: string } | { type: 'quantity' }> = [];
		for (const key of getAttrKeys(product)) {
			steps.push({ type: 'attribute', key });
		}
		steps.push({ type: 'quantity' });
		return steps;
	}

	function getStepOptions(product: StoreProduct, stepIdx: number, attrs: Record<string, string>): string[] {
		const steps = getSteps(product);
		const step = steps[stepIdx];
		if (!step || step.type !== 'attribute') return [];
		const attr = product.attributes?.find(a => a.name === step.key);
		if (!attr) return [];
		// Filter by prior selections
		const keys = getAttrKeys(product);
		const partial: Record<string, string> = {};
		for (const k of keys) {
			if (k === step.key) break;
			if (attrs[k]) partial[k] = attrs[k];
		}
		if (Object.keys(partial).length === 0) return attr.terms.map(t => t.name);
		const valid = new Set<string>();
		for (const v of product.variations ?? []) {
			const matches = Object.entries(partial).every(([pk, pv]) => v.attributes.find(a => a.name === pk)?.value === pv);
			if (matches) {
				const val = v.attributes.find(a => a.name === step.key)?.value;
				if (val) valid.add(val);
			}
		}
		return [...valid];
	}

	function allSelected(product: StoreProduct, attrs: Record<string, string>): boolean {
		return getAttrKeys(product).every(k => !!attrs[k]);
	}

	function findVariationId(product: StoreProduct, attrs: Record<string, string>): number | null {
		const keys = getAttrKeys(product);
		const v = (product.variations ?? []).find(v =>
			keys.every(k => v.attributes.find(a => a.name === k)?.value === attrs[k])
		);
		return v?.id ?? null;
	}

	async function miniAdd(e: Event, product: StoreProduct) {
		e.preventDefault();
		e.stopPropagation();
		if (addingId !== null) return;
		const s = getState(product);
		const steps = getSteps(product);
		const isQtyStep = steps[s.stepIdx]?.type === 'quantity';
		const ready = (!product.has_options || allSelected(product, s.attrs)) && isQtyStep;

		if (!ready) {
			for (let i = 0; i < steps.length; i++) {
				const step = steps[i];
				if (step.type === 'attribute' && !s.attrs[step.key]) {
					miniStates.set(product.id, { ...s, stepIdx: i });
					miniStates = new Map(miniStates);
					break;
				}
			}
			return;
		}

		const vid = product.has_options ? findVariationId(product, s.attrs) : null;
		const variation = product.has_options
			? Object.entries(s.attrs).map(([k, v]) => ({ attribute: k, value: v }))
			: [];
		const targetId = vid ?? product.id;
		if (!canPurchase(product) && !product.has_options) return;
		if (product.has_options && vid === null) return;

		addingId = product.id;
		try {
			await cart.addItem(targetId, s.qty, variation, { clicked_from: 'cart_cross_sell' });
			miniStates.set(product.id, { ...s, justAdded: true });
			miniStates = new Map(miniStates);
			setTimeout(() => {
				const cur = miniStates.get(product.id);
				if (cur) {
					miniStates.set(product.id, { ...cur, justAdded: false });
					miniStates = new Map(miniStates);
				}
			}, 900);
		} finally {
			addingId = null;
		}
	}

	function miniSelect(product: StoreProduct, value: string) {
		const s = getState(product);
		const steps = getSteps(product);
		const step = steps[s.stepIdx];
		if (!step || step.type !== 'attribute') return;
		const keys = getAttrKeys(product);
		const idx = keys.indexOf(step.key);
		const newAttrs = { ...s.attrs, [step.key]: value };
		for (let i = idx + 1; i < keys.length; i++) delete newAttrs[keys[i]];
		miniStates.set(product.id, {
			...s,
			attrs: newAttrs,
			stepIdx: s.stepIdx < steps.length - 1 ? s.stepIdx + 1 : s.stepIdx,
		});
		miniStates = new Map(miniStates);
	}

	function miniBack(e: Event, product: StoreProduct) {
		e.preventDefault();
		e.stopPropagation();
		const s = getState(product);
		if (s.stepIdx > 0) {
			miniStates.set(product.id, { ...s, stepIdx: s.stepIdx - 1 });
			miniStates = new Map(miniStates);
		}
	}

	function miniQtyDec(e: Event, product: StoreProduct) {
		e.preventDefault();
		e.stopPropagation();
		const s = getState(product);
		if (s.qty > 1) {
			miniStates.set(product.id, { ...s, qty: s.qty - 1 });
			miniStates = new Map(miniStates);
		}
	}

	function miniQtyInc(e: Event, product: StoreProduct) {
		e.preventDefault();
		e.stopPropagation();
		const s = getState(product);
		miniStates.set(product.id, { ...s, qty: s.qty + 1 });
		miniStates = new Map(miniStates);
	}
	// Modal step functions for simple mode
	function modalSelectAttr(value: string) {
		if (!modalProduct || !modalState) return;
		const steps = getSteps(modalProduct);
		const step = steps[modalState.stepIdx];
		if (!step || step.type !== 'attribute') return;
		const keys = getAttrKeys(modalProduct);
		const idx = keys.indexOf(step.key);
		const newAttrs = { ...modalState.attrs, [step.key]: value };
		for (let i = idx + 1; i < keys.length; i++) delete newAttrs[keys[i]];
		modalState = { ...modalState, attrs: newAttrs, stepIdx: modalState.stepIdx < steps.length - 1 ? modalState.stepIdx + 1 : modalState.stepIdx };
	}

	function modalBack() {
		if (!modalState || modalState.stepIdx <= 0) return;
		modalState = { ...modalState, stepIdx: modalState.stepIdx - 1 };
	}

	async function modalAdd() {
		if (!modalProduct || !modalState || addingId !== null) return;
		const steps = getSteps(modalProduct);
		const isQty = steps[modalState.stepIdx]?.type === 'quantity';
		const ready = (!modalProduct.has_options || allSelected(modalProduct, modalState.attrs)) && isQty;
		if (!ready) return;

		const vid = modalProduct.has_options ? findVariationId(modalProduct, modalState.attrs) : null;
		if (modalProduct.has_options && !vid) return;
		const variation = modalProduct.has_options
			? Object.entries(modalState.attrs).map(([k, v]) => ({ attribute: k, value: v }))
			: [];
		const targetId = vid ?? modalProduct.id;
		const varRow = vid ? modalVariations.find((v) => v.id === vid) : null;
		if (modalProduct.has_options) {
			if (!varRow || !canPurchase(varRow)) return;
		} else if (!canPurchase(modalProduct)) {
			return;
		}

		addingId = modalProduct.id;
		try {
			await cart.addItem(targetId, modalState.qty, variation, { clicked_from: 'cart_cross_sell' });
			miniStates.set(modalProduct.id, { ...modalState, justAdded: true });
			miniStates = new Map(miniStates);
			closeModal();
			setTimeout(() => {
				const cur = miniStates.get(modalProduct!.id);
				if (cur) {
					miniStates.set(modalProduct!.id, { ...cur, justAdded: false });
					miniStates = new Map(miniStates);
				}
			}, 900);
		} finally {
			addingId = null;
		}
	}

	let viewportEl = $state<HTMLElement | undefined>();
	let trackEl = $state<HTMLElement | undefined>();
	let progressEl = $state<HTMLElement | undefined>();
	let embla: EmblaCarouselType | undefined;

	function updateProgress() {
		if (!embla || !progressEl) return;
		const p = Math.max(0.15, Math.min(1, embla.scrollProgress() || 0.15));
		progressEl.style.transform = `scaleX(${p})`;
	}

	$effect(() => {
		if (isSidebar || !viewportEl || !trackEl || products.length === 0) return;
		embla = EmblaCarousel(viewportEl, {
			align: 'start',
			containScroll: 'trimSnaps',
			dragFree: true,
			container: trackEl,
		});
		embla.on('scroll', updateProgress);
		embla.on('reInit', updateProgress);
		updateProgress();
		return () => embla?.destroy();
	});

	// Re-fetch only when the server id list changes (order preserved — BAC first).
	$effect(() => {
		const key = recommendIds.join(',');
		if (key === loadedForIds) return;
		if (recommendIds.length === 0) {
			products = [];
			loadedForIds = key;
			return;
		}
		const gen = ++fetchGeneration;
		loading = true;
		getProductsByIds(recommendIds.slice(0, CART_CROSS_SELL_TARGET_COUNT))
			.then((list) => {
				if (gen !== fetchGeneration) return;
				const order = new Map(recommendIds.map((id, i) => [id, i]));
				products = list
					.filter((p) => !isCatalogHiddenProduct(p.id, p.slug))
					.sort((a, b) => (order.get(a.id) ?? 0) - (order.get(b.id) ?? 0));
				loadedForIds = key;
			})
			.finally(() => {
				if (gen === fetchGeneration) loading = false;
			});
	});

	async function addToCart(e: Event, product: StoreProduct) {
		e.preventDefault();
		e.stopPropagation();
		if (addingId !== null || !canPurchase(product)) return;
		addingId = product.id;
		try {
			await cart.addItem(product.id, 1, [], { clicked_from: 'cart_cross_sell' });
		} finally {
			addingId = null;
		}
	}

	function formatMoneyInt(minorInt: number): string {
		return formatPrice(minorInt, {
			currency_minor_unit: cart.currencyMinorUnit,
			currency_symbol: cart.currencySymbol,
			currency_code: cart.currencyCode,
		});
	}

	function productHref(slug: string): string {
		return `/product/${slug}`;
	}

	function openProduct(e: MouseEvent, slug: string) {
		if (
			e.defaultPrevented ||
			e.button !== 0 ||
			e.metaKey ||
			e.ctrlKey ||
			e.shiftKey ||
			e.altKey
		) {
			return;
		}
		e.preventDefault();
		e.stopPropagation();
		cart.toggle(false);
		void goto(productHref(slug), { invalidateAll: true });
	}
</script>

{#if products.length > 0}
	<section
		class="cart-xsell"
		class:cart-xsell--sidebar={isSidebar}
		class:is-loading={loading}
		aria-label={title}
	>
		{#if !hideHeading}
			<header class="cart-xsell__head">
				<h3>{title}</h3>
			</header>
		{/if}
		{#if isSidebar}
			<div class="cart-xsell__list" role="list">
				{#each products.slice(0, CART_CROSS_SELL_TARGET_COUNT) as product (product.id)}
					{@const cro = product.extensions?.wchs_cro}
					{@const regular = cro?.regular_price ?? Number(product.prices.regular_price)}
					{@const current = Number(product.prices.price)}
					{@const onSale = regular > current}
					<article class="cart-xsell__card cart-xsell__card--stack" role="listitem">
						<a
							class="cart-xsell__card-link cart-xsell__card-link--stack"
							href={productHref(product.slug)}
							onclick={(e) => openProduct(e, product.slug)}
						>
							<span class="cart-xsell__media-stack">
								{#if product.images[0]}
									<img
										src={product.images[0].thumbnail || product.images[0].src}
										alt={product.images[0].alt || product.name}
										loading="lazy"
									/>
								{/if}
							</span>
							<span class="cart-xsell__body-stack">
								<span class="cart-xsell__title">{product.name}</span>
								<span class="cart-xsell__price tabular-nums">
									<span class="cart-xsell__price-now">{formatMoneyInt(current)}</span>
									{#if onSale}
										<span class="cart-xsell__price-was">{formatMoneyInt(regular)}</span>
									{/if}
								</span>
							</span>
						</a>
					</article>
				{/each}
			</div>
		{:else}
		<div class="cart-xsell__viewport" bind:this={viewportEl}>
			<div class="cart-xsell__track" bind:this={trackEl} role="list">
			{#each products.slice(0, CART_CROSS_SELL_TARGET_COUNT) as product (product.id)}
				{@const cro = product.extensions?.wchs_cro}
				{@const regular = cro?.regular_price ?? Number(product.prices.regular_price)}
				{@const current = Number(product.prices.price)}
				{@const onSale = regular > current}
				<article class="cart-xsell__card" role="listitem">
					<a
						class="cart-xsell__link"
						href={productHref(product.slug)}
						onclick={(e) => openProduct(e, product.slug)}
					>
						<div class="cart-xsell__media">
							{#if product.images[0]}
								<img
									src={product.images[0].thumbnail || product.images[0].src}
									alt={product.images[0].alt || product.name}
									loading="lazy"
								/>
							{/if}
						</div>
						<div class="cart-xsell__body">
							<p class="cart-xsell__title">{product.name}</p>
							<p class="cart-xsell__price tabular-nums">
								<span class="cart-xsell__price-now">{formatMoneyInt(current)}</span>
								{#if onSale}
									<span class="cart-xsell__price-was">{formatMoneyInt(regular)}</span>
								{/if}
							</p>
						</div>
					</a>
				</article>
			{/each}
			</div>
		</div>
		<div class="cart-xsell__progress" aria-hidden="true">
			<span class="cart-xsell__progress-fill" bind:this={progressEl}></span>
		</div>
		{/if}
	</section>
{/if}

<!-- Simple mode: variable product attribute modal -->
{#if modalProduct && modalState}
	{@const mSteps = getSteps(modalProduct)}
	{@const mStep = mSteps[modalState.stepIdx]}
	{@const mIsQty = mStep?.type === 'quantity'}
	{@const mVariationFound = modalProduct.has_options ? findVariationId(modalProduct, modalState.attrs) !== null : true}
	{@const mReady = (!modalProduct.has_options || (allSelected(modalProduct, modalState.attrs) && mVariationFound)) && mIsQty}
	<div class="xsell-modal" role="dialog" aria-label="Select options">
		<!-- svelte-ignore a11y_click_events_have_key_events a11y_no_static_element_interactions -->
		<div class="xsell-modal__backdrop" role="presentation" onclick={closeModal} transition:fade={{ duration: 150 }}></div>
		<div class="xsell-modal__panel" transition:fly={{ y: 30, duration: 200 }}>
			<button class="xsell-modal__close" onclick={closeModal} aria-label="Close">
				<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
			</button>
			<p class="xsell-modal__name">{modalProduct.name}</p>

			{#if mStep?.type === 'attribute'}
				<p class="xsell-modal__step-label">{mStep.key}</p>
				<div class="xsell-modal__options">
					{#each getStepOptions(modalProduct, modalState.stepIdx, modalState.attrs) as opt}
						{@const available = modalOptionAvailable(modalProduct, mStep.key, opt, modalState.attrs)}
						<button
							type="button"
							class="xsell-modal__option"
							class:is-selected={modalState.attrs[mStep.key] === opt}
							class:is-unavailable={!available}
							disabled={!available}
							onclick={() => modalSelectAttr(opt)}
						>{opt}</button>
					{/each}
				</div>
			{:else if mIsQty}
				<p class="xsell-modal__step-label">Quantity</p>
				<div class="xsell-modal__qty">
					<button type="button" onclick={() => { if (modalState && modalState.qty > 1) modalState = { ...modalState, qty: modalState.qty - 1 }; }} disabled={modalState.qty <= 1}>−</button>
					<span>{modalState.qty}</span>
					<button type="button" onclick={() => { if (modalState) modalState = { ...modalState, qty: modalState.qty + 1 }; }}>+</button>
				</div>
			{/if}

			<div class="xsell-modal__actions">
				{#if modalState.stepIdx > 0}
					<button type="button" class="xsell-modal__back" onclick={modalBack}>Back</button>
				{/if}
				{#if mReady}
					<button type="button" class="xsell-modal__add" onclick={modalAdd} disabled={addingId !== null}>
						{addingId ? 'Adding…' : 'Add to Cart'}
					</button>
				{/if}
			</div>
		</div>
	</div>
{/if}

<style>
	.cart-xsell {
		position: relative;
		border-top: 1px solid var(--border);
		padding: 16px 0 20px;
		background: var(--bg);
	}
	.cart-xsell--sidebar {
		display: flex;
		flex-direction: column;
		height: 100%;
		min-height: 0;
		border-top: 0;
		padding: 0;
		background: transparent;
	}
	.cart-xsell--sidebar .cart-xsell__head {
		padding: 18px 14px 12px;
		text-align: center;
		flex-shrink: 0;
	}
	.cart-xsell--sidebar .cart-xsell__head h3 {
		margin: 0;
		font-size: 13px;
		font-weight: 600;
		text-transform: none;
		letter-spacing: -0.2px;
		color: var(--fg);
		line-height: 1.25;
	}
	.cart-xsell--sidebar .cart-xsell__list {
		flex: 1 1 auto;
		min-height: 0;
		overflow-x: hidden;
		overflow-y: auto;
		padding: 0 10px 14px;
		display: flex;
		flex-direction: column;
		gap: 12px;
		-webkit-overflow-scrolling: touch;
	}
	.cart-xsell--sidebar .cart-xsell__card {
		flex: 0 0 auto;
		width: 100%;
		max-width: 100%;
	}
	.cart-xsell.is-loading {
		opacity: 0.92;
	}
	.cart-xsell--sidebar .cart-xsell__card--stack {
		padding: 0;
		display: flex;
	}
	.cart-xsell__card-link--stack {
		display: flex;
		flex-direction: column;
		align-items: center;
		justify-content: flex-start;
		width: 100%;
		min-height: 132px;
		padding: 14px 12px;
		box-sizing: border-box;
		text-align: center;
		text-decoration: none;
		color: inherit;
		border-radius: inherit;
		cursor: pointer;
		gap: 10px;
	}
	.cart-xsell__card-link--stack:hover .cart-xsell__title {
		color: color-mix(in srgb, var(--accent) 72%, var(--fg));
	}
	.cart-xsell__media-stack {
		flex: 0 0 auto;
		width: 112px;
		height: 112px;
		display: flex;
		align-items: center;
		justify-content: center;
		background: color-mix(in srgb, var(--accent) 6%, var(--bg-muted));
		border: 1px solid color-mix(in srgb, var(--accent) 35%, var(--border));
		border-radius: var(--radius-sm);
		overflow: hidden;
	}
	.cart-xsell__media-stack img {
		width: 100%;
		height: 100%;
		object-fit: cover;
	}
	.cart-xsell__body-stack {
		width: 100%;
		flex: 1 1 auto;
		display: flex;
		flex-direction: column;
		align-items: center;
		justify-content: flex-start;
		gap: 6px;
		min-width: 0;
	}
	.cart-xsell--sidebar .cart-xsell__title {
		display: block;
		width: 100%;
		min-height: 0;
		font-size: 12px;
		line-height: 1.35;
		line-clamp: 3;
		-webkit-line-clamp: 3;
		color: var(--accent);
		text-align: center;
	}
	.cart-xsell--sidebar .cart-xsell__price {
		display: flex;
		flex-direction: column;
		align-items: center;
		gap: 2px;
		width: 100%;
	}
	.cart-xsell--sidebar .cart-xsell__price-now {
		font-size: 14px;
	}
	.cart-xsell--sidebar .cart-xsell__price-was {
		font-size: 11px;
	}
	.cart-xsell__head {
		padding: 0 24px;
	}
	.cart-xsell:not(.cart-xsell--sidebar) .cart-xsell__head h3 {
		margin: 0 0 12px;
		font-size: 13px;
		font-weight: 600;
		text-transform: none;
		letter-spacing: -0.2px;
		color: var(--fg);
	}
	.cart-xsell__viewport {
		overflow: hidden;
		padding: 0 24px;
	}
	.cart-xsell__track {
		display: flex;
		gap: 10px;
		cursor: grab;
	}
	.cart-xsell__track:active {
		cursor: grabbing;
	}
	.cart-xsell__progress {
		position: relative;
		height: 1px;
		background: var(--border);
		margin: 12px 24px 0;
		overflow: hidden;
	}
	.cart-xsell__progress-fill {
		position: absolute;
		inset: 0 auto 0 0;
		width: 100%;
		background: var(--fg);
		transform: scaleX(0.15);
		transform-origin: left center;
		transition: transform 0.15s ease;
	}
	.cart-xsell__card {
		position: relative;
		flex: 0 0 148px;
		display: flex;
		flex-direction: column;
		gap: 8px;
		background: color-mix(in srgb, var(--accent) 5%, var(--bg-elevated));
		border: 1px solid color-mix(in srgb, var(--accent) 35%, var(--border));
		border-radius: var(--radius-sm);
		overflow: hidden;
		scroll-snap-align: start;
		transition:
			border-color var(--dur-fast) var(--ease),
			background var(--dur-fast) var(--ease);
	}
	.cart-xsell__card:hover {
		border-color: var(--accent);
		background: color-mix(in srgb, var(--accent) 9%, var(--bg-elevated));
	}
	.cart-xsell__card.just-added {
		border-color: var(--success, #059669);
		animation: xsell-card-flash 0.6s ease-out;
	}
	@keyframes xsell-card-flash {
		0% { transform: scale(0.97); box-shadow: 0 0 0 0 color-mix(in srgb, var(--success, #059669) 40%, transparent); }
		40% { transform: scale(1.02); box-shadow: 0 0 0 4px color-mix(in srgb, var(--success, #059669) 30%, transparent); }
		100% { transform: scale(1); box-shadow: 0 0 0 0 transparent; }
	}
	.cart-xsell__link {
		display: flex;
		flex-direction: column;
		gap: 8px;
		color: var(--fg);
		text-decoration: none;
		flex: 1 1 auto;
	}
	.cart-xsell__media {
		aspect-ratio: 1 / 1;
		background: color-mix(in srgb, var(--accent) 6%, var(--bg-muted));
		overflow: hidden;
	}
	.cart-xsell__media img {
		width: 100%;
		height: 100%;
		object-fit: cover;
	}
	.cart-xsell__body {
		padding: 0 10px 12px;
		display: flex;
		flex-direction: column;
		gap: 4px;
	}
	.cart-xsell__title {
		margin: 0;
		font-size: 11px;
		font-weight: 500;
		line-height: 14px;
		letter-spacing: -0.16px;
		color: var(--accent);
		text-decoration: none;
		display: -webkit-box;
		-webkit-line-clamp: 2;
		line-clamp: 2;
		-webkit-box-orient: vertical;
		overflow: hidden;
		min-height: 28px;
	}
	.cart-xsell__price {
		margin: 0;
		display: flex;
		flex-direction: column;
		align-items: flex-start;
		gap: 2px;
	}
	.cart-xsell__price-now {
		font-size: 12px;
		font-weight: 600;
		color: var(--fg);
		line-height: 1.2;
	}
	.cart-xsell__price-was {
		color: var(--fg-muted);
		font-weight: 450;
		font-size: 11px;
		line-height: 1.2;
		text-decoration: line-through;
	}
	/* Mini step controls — overlaid at top of image */
	.cart-xsell__controls {
		position: absolute;
		top: 6px;
		left: 6px;
		right: 6px;
		display: flex;
		align-items: center;
		gap: 4px;
		z-index: 2;
		touch-action: manipulation;
	}
	/* Prevent Embla drag from swallowing clicks on controls */
	.cart-xsell__controls * {
		pointer-events: auto;
	}
	.cart-xsell__ctrl-btn {
		width: 30px;
		height: 30px;
		display: inline-flex;
		align-items: center;
		justify-content: center;
		padding: 0;
		background: var(--bg);
		border: 1px solid var(--border);
		color: var(--fg);
		cursor: pointer;
		font-size: 10px;
		flex-shrink: 0;
		transition: background 0.15s, color 0.15s, border-color 0.15s;
	}
	.cart-xsell__ctrl-btn:hover:not(:disabled) {
		background: var(--fg);
		color: var(--bg);
		border-color: var(--fg);
	}
	.cart-xsell__ctrl-btn:disabled {
		opacity: 0.3;
		cursor: default;
	}
	.cart-xsell__ctrl-qty {
		font-size: 11px;
		font-weight: 600;
		color: var(--bg);
		background: var(--fg);
		width: 24px;
		height: 30px;
		display: inline-flex;
		align-items: center;
		justify-content: center;
		letter-spacing: 0;
		flex-shrink: 0;
	}
	.cart-xsell__ctrl-select {
		appearance: none;
		height: 30px;
		padding: 0 6px;
		border: 1px solid var(--border);
		background: var(--bg);
		color: var(--fg);
		font-size: 9px;
		font-weight: 600;
		text-transform: uppercase;
		letter-spacing: 0.04em;
		cursor: pointer;
		flex: 1 1 auto;
		min-width: 0;
		outline: none;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}
	.cart-xsell__ctrl-select option {
		background: var(--bg);
		color: var(--fg);
	}
	/* Add button — bottom-right corner of image */
	.cart-xsell__add-btn {
		position: absolute;
		bottom: 6px;
		right: 6px;
		z-index: 2;
		width: 36px;
		height: 36px;
		display: inline-flex;
		align-items: center;
		justify-content: center;
		padding: 0;
		background: var(--accent);
		border: 1px solid var(--accent);
		color: var(--accent-fg);
		cursor: pointer;
		touch-action: manipulation;
		transition: background 0.15s, color 0.15s, border-color 0.15s, opacity 0.15s;
	}
	.cart-xsell__add-btn:hover:not(:disabled) {
		background: transparent;
		color: var(--accent);
		border-color: var(--accent);
	}
	.cart-xsell__add-btn:disabled {
		background: var(--bg);
		border-color: var(--border);
		color: var(--fg-muted);
		cursor: default;
		opacity: 0.6;
	}
	.cart-xsell__add-btn.just-added {
		background: var(--success, #059669);
		border-color: var(--success, #059669);
		color: #fff;
		opacity: 1;
		animation: xsell-pop 0.3s ease-out;
	}
	@keyframes xsell-pop {
		0% { transform: scale(0.8); }
		50% { transform: scale(1.15); }
		100% { transform: scale(1); }
	}

	/* ── Simple mode modal ── */
	.xsell-modal {
		position: fixed;
		inset: 0;
		z-index: 10001;
		display: flex;
		align-items: center;
		justify-content: center;
	}
	.xsell-modal__backdrop {
		position: absolute;
		inset: 0;
		background: rgba(0, 0, 0, 0.5);
	}
	.xsell-modal__panel {
		position: relative;
		z-index: 1;
		background: var(--bg);
		border: 1px solid var(--border);
		padding: 24px;
		width: calc(100% - 48px);
		max-width: 320px;
	}
	.xsell-modal__close {
		position: absolute;
		top: 8px;
		right: 8px;
		background: transparent;
		border: 0;
		color: var(--fg-muted);
		cursor: pointer;
		padding: 4px;
	}
	.xsell-modal__close:hover { color: var(--fg); }
	.xsell-modal__name {
		font-size: 14px;
		font-weight: 600;
		color: var(--fg);
		margin: 0 0 16px;
		padding-right: 24px;
	}
	.xsell-modal__step-label {
		font-size: 11px;
		font-weight: 500;
		text-transform: uppercase;
		letter-spacing: 0.08em;
		color: var(--fg-muted);
		margin: 0 0 10px;
	}
	.xsell-modal__options {
		display: flex;
		flex-wrap: wrap;
		gap: 8px;
		margin-bottom: 20px;
	}
	.xsell-modal__option {
		padding: 9px 16px;
		background: transparent;
		color: var(--fg);
		border: 1px solid var(--border);
		font: inherit;
		font-size: 13px;
		font-weight: 500;
		cursor: pointer;
		transition: background var(--dur-fast) var(--ease), border-color var(--dur-fast) var(--ease), color var(--dur-fast) var(--ease);
	}
	.xsell-modal__option:hover {
		border-color: var(--fg);
	}
	.xsell-modal__option.is-selected {
		background: var(--accent);
		color: var(--accent-fg);
		border-color: var(--accent);
	}
	.xsell-modal__option.is-unavailable {
		opacity: 0.3;
		text-decoration: line-through;
		cursor: not-allowed;
	}
	.xsell-modal__qty {
		display: flex;
		align-items: center;
		gap: 0;
		margin-bottom: 20px;
		border: 1px solid var(--border);
		width: fit-content;
	}
	.xsell-modal__qty button {
		width: 40px;
		height: 40px;
		background: transparent;
		border: 0;
		color: var(--fg);
		font-size: 16px;
		cursor: pointer;
	}
	.xsell-modal__qty button:hover { background: var(--bg-muted); }
	.xsell-modal__qty button:disabled { opacity: 0.3; cursor: default; }
	.xsell-modal__qty span {
		width: 40px;
		text-align: center;
		font-size: 13px;
		font-weight: 600;
		border-left: 1px solid var(--border);
		border-right: 1px solid var(--border);
		line-height: 40px;
	}
	.xsell-modal__actions {
		display: flex;
		gap: 8px;
	}
	.xsell-modal__back {
		padding: 10px 16px;
		background: transparent;
		border: 1px solid var(--border);
		color: var(--fg);
		font: inherit;
		font-size: 12px;
		font-weight: 500;
		text-transform: uppercase;
		letter-spacing: 0.08em;
		cursor: pointer;
	}
	.xsell-modal__back:hover { border-color: var(--fg); }
	.xsell-modal__add {
		flex: 1;
		padding: 10px 16px;
		background: var(--accent);
		color: var(--accent-fg);
		border: 1px solid var(--accent);
		font: inherit;
		font-size: 12px;
		font-weight: 600;
		text-transform: uppercase;
		letter-spacing: 0.08em;
		cursor: pointer;
		transition: background var(--dur-fast) var(--ease), color var(--dur-fast) var(--ease);
	}
	.xsell-modal__add:hover:not(:disabled) {
		background: transparent;
		color: var(--accent);
	}
	.xsell-modal__add:disabled {
		opacity: 0.5;
		cursor: not-allowed;
	}
</style>
