<script lang="ts">
	import { cart } from '$lib/wc/cart.svelte';
	import { pretext } from '$lib/pretext/engine';
	import { onMount } from 'svelte';
	import { formatPrice as fmt } from '$lib/utils/format';
	import { config } from '$lib/config.svelte';

	type AttributeTerm = { id: number; name: string; slug: string; default?: boolean };
	type ProductAttribute = { name: string; terms: AttributeTerm[] };
	type Variation = { id: number; attributes: { name: string; value: string }[] };

	type TierRow = {
		min_qty: number;
		unit_price: number;
		savings_per_unit: number;
		savings_pct: number;
		line_total_at_min_qty: number;
	};

	type Product = {
		id: number;
		name: string;
		slug: string;
		permalink: string;
		images: { src: string; thumbnail: string; alt: string }[];
		prices: {
			price: string;
			regular_price?: string;
			sale_price?: string;
			price_range?: { min_amount: string; max_amount: string } | null;
			currency_symbol: string;
			currency_minor_unit: number;
			currency_code?: string;
		};
		has_options?: boolean;
		attributes?: ProductAttribute[];
		variations?: Variation[];
		on_sale?: boolean;
		is_in_stock?: boolean;
		add_to_cart?: { minimum: number; maximum: number };
		extensions?: {
			wchs_cro?: {
				regular_price: number;
				tier_type: 'fixed' | 'percentage' | null;
				tiers: TierRow[];
			};
		};
	};

	type StepDef = { type: 'attribute'; key: string } | { type: 'quantity' };

	let { product, cardWidth = 252 }: { product: Product; cardWidth?: number } = $props();
	let fontsReady = $state(false);
	let adding = $state(false);
	let justAdded = $state(false);
	let quantity = $state(1);
	let selectorControl = $state<HTMLSelectElement | undefined>();

	// Pre-select WC default attributes if set (cleaned list — skips the
	// Shopify "Default Title" pseudo-attribute).
	const defaultAttrs = $derived.by(() => {
		const d: Record<string, string> = {};
		for (const attr of cleanAttributes) {
			const def = attr.terms.find(t => t.default);
			if (def) d[attr.name] = def.name;
		}
		return d;
	});
	let selectedAttrs = $state<Record<string, string>>({});
	let currentStepIdx = $state(0);

	// Initialize from defaults on first render
	let initialized = $state(false);
	$effect(() => {
		if (initialized) return;
		const keys = Object.keys(defaultAttrs);
		if (keys.length > 0) {
			selectedAttrs = { ...defaultAttrs };
			// Jump to quantity step if all attrs have defaults
			const allAttrs = cleanAttributes.map(a => a.name);
			if (allAttrs.every(k => defaultAttrs[k])) {
				currentStepIdx = allAttrs.length; // quantity step index
			}
		}
		initialized = true;
	});

	onMount(async () => {
		await pretext.ready();
		fontsReady = true;
	});

	const cro = $derived(product.extensions?.wchs_cro);

	// Filter out Shopify import artifacts — a legacy `title` attribute whose
	// sole option is "Default Title" has no semantic meaning in WC and would
	// render as a "Title" stepper row on the card. A DB cleanup script strips
	// these, but we keep this defensive filter for any future import that
	// drags the pollution in again.
	const cleanAttributes = $derived.by(() => {
		return (product.attributes ?? []).filter(a => {
			const opts = a.terms?.map(t => t.name) ?? [];
			const onlyDefaultTitle = opts.length === 1 &&
				opts[0].trim().toLowerCase() === 'default title';
			return !onlyDefaultTitle;
		});
	});

	const hasVariations = $derived(!!(product.has_options && cleanAttributes.length));
	const inStock = $derived(product.is_in_stock !== false);

	const attributeKeys = $derived.by(() => {
		return cleanAttributes.map(a => a.name);
	});

	const interactiveSteps = $derived.by<StepDef[]>(() => {
		const steps: StepDef[] = [];
		for (const key of attributeKeys) {
			steps.push({ type: 'attribute', key });
		}
		steps.push({ type: 'quantity' });
		return steps;
	});

	const totalSteps = $derived(interactiveSteps.length);
	const currentStep = $derived<StepDef>(interactiveSteps[currentStepIdx] ?? { type: 'quantity' });
	const isQuantityStep = $derived(currentStep.type === 'quantity');
	const canGoBack = $derived(totalSteps > 1 && currentStepIdx > 0);

	// Clamp step index
	$effect(() => {
		if (currentStepIdx >= totalSteps) currentStepIdx = Math.max(0, totalSteps - 1);
	});

	function valuesForAttribute(key: string): string[] {
		const attr = cleanAttributes.find(a => a.name === key);
		if (!attr) return [];

		// Filter by previously selected attributes
		const partial: Record<string, string> = {};
		for (const k of attributeKeys) {
			if (k === key) break;
			if (selectedAttrs[k]) partial[k] = selectedAttrs[k];
		}

		if (Object.keys(partial).length === 0) return attr.terms.map(t => t.name);

		// Filter variations that match partial selection
		const validValues = new Set<string>();
		for (const v of product.variations ?? []) {
			const matchesPartial = Object.entries(partial).every(
				([pk, pv]) => v.attributes.find(a => a.name === pk)?.value === pv
			);
			if (matchesPartial) {
				const val = v.attributes.find(a => a.name === key)?.value;
				if (val) validValues.add(val);
			}
		}
		return [...validValues];
	}

	const stepOptions = $derived.by(() => {
		if (currentStep.type !== 'attribute') return [];
		return valuesForAttribute(currentStep.key).map(v => ({ value: v, label: v }));
	});

	const stepValue = $derived.by(() => {
		if (currentStep.type === 'attribute') return selectedAttrs[currentStep.key] ?? '';
		return String(quantity);
	});

	function stepLabel(idx: number): string {
		const step = interactiveSteps[idx];
		if (!step) return 'Option';
		if (step.type === 'attribute') return step.key;
		return 'Qty';
	}

	function selectAtIdx(idx: number, value: string) {
		const step = interactiveSteps[idx];
		if (!step || step.type === 'quantity') return;

		const attrIdx = attributeKeys.indexOf(step.key);
		const next = { ...selectedAttrs, [step.key]: value };
		// Clear later selections
		for (let i = attrIdx + 1; i < attributeKeys.length; i++) {
			delete next[attributeKeys[i]];
		}
		selectedAttrs = next;
		if (idx < totalSteps - 1) currentStepIdx = idx + 1;
	}

	const allSelectionsMade = $derived.by(() => {
		for (const key of attributeKeys) {
			if (!selectedAttrs[key]) return false;
		}
		return true;
	});

	const selectedVariation = $derived.by(() => {
		if (!hasVariations || !allSelectionsMade) return null;
		return (product.variations ?? []).find(v =>
			attributeKeys.every(key =>
				v.attributes.find(a => a.name === key)?.value === selectedAttrs[key]
			)
		) ?? null;
	});

	const maxQty = $derived(Math.max(1, Math.min(product.add_to_cart?.maximum ?? 99, 99)));
	const readyToAdd = $derived((!hasVariations || allSelectionsMade) && isQuantityStep);

	// Price display
	const maxTierPct = $derived.by(() => {
		if (!cro?.tiers?.length) return 0;
		return cro.tiers[cro.tiers.length - 1].savings_pct ?? 0;
	});

	function formatPct(p: number): string {
		return Number.isInteger(p) ? `${p}%` : `${p.toFixed(1)}%`;
	}

	function formatPrice(cents?: string | number) {
		return fmt(cents ?? product.prices.price, product.prices);
	}

	const displayPrice = $derived.by(() => {
		if (hasVariations && !allSelectionsMade && product.prices.price_range) {
			return `${formatPrice(product.prices.price_range.min_amount)}–${formatPrice(product.prices.price_range.max_amount)}`;
		}
		return formatPrice();
	});

	const compareAtPrice = $derived.by(() => {
		if (product.on_sale && product.prices.regular_price && product.prices.regular_price !== product.prices.price) {
			return formatPrice(product.prices.regular_price);
		}
		return null;
	});

	// Savings % for the sale badge template. Prefer the CRO max-tier
	// percent; fall back to (regular - current) / regular for simple sales.
	const salePercent = $derived.by(() => {
		if (maxTierPct > 0) return Math.round(maxTierPct);
		const reg = parseFloat(product.prices.regular_price ?? '0');
		const cur = parseFloat(product.prices.price ?? '0');
		if (reg > 0 && cur > 0 && reg > cur) {
			return Math.round(((reg - cur) / reg) * 100);
		}
		return 0;
	});

	// Sale badge text with {percent} placeholder. Admins can set e.g.
	// "−{percent}%" or "Save {percent}%"; falls back to "Sale" when no
	// discount percent is computable.
	const saleBadgeRendered = $derived.by(() => {
		const tpl = config.data.product_card?.sale_badge_text ?? 'Sale';
		if (tpl.includes('{percent}')) {
			return tpl.replace('{percent}', String(salePercent));
		}
		return tpl;
	});

	const titleLayout = $derived.by(() => {
		if (!fontsReady) return null;
		return pretext.measure(product.name, 'title', cardWidth - 40, 20);
	});

	function firstIncompleteIdx(): number {
		for (let i = 0; i < interactiveSteps.length; i++) {
			const s = interactiveSteps[i];
			if (s.type === 'attribute' && !selectedAttrs[s.key]) return i;
		}
		return interactiveSteps.length - 1;
	}

	function goBack(e: Event) {
		e.preventDefault();
		e.stopPropagation();
		if (currentStepIdx > 0) currentStepIdx--;
		requestAnimationFrame(() => selectorControl?.focus());
	}

	function decrementQty(e: Event) {
		e.preventDefault();
		e.stopPropagation();
		if (quantity > 1) quantity--;
	}

	function incrementQty(e: Event) {
		e.preventDefault();
		e.stopPropagation();
		if (quantity < maxQty) quantity++;
	}

	function handleQtyInput(e: Event) {
		const v = parseInt((e.currentTarget as HTMLInputElement).value, 10);
		if (Number.isFinite(v)) quantity = Math.max(1, Math.min(maxQty, v));
	}

	async function handleAction(e: Event) {
		e.preventDefault();
		e.stopPropagation();
		if (adding) return;

		if (!readyToAdd) {
			currentStepIdx = firstIncompleteIdx();
			requestAnimationFrame(() => selectorControl?.focus());
			return;
		}

		adding = true;
		justAdded = false;
		try {
			const id = selectedVariation?.id ?? product.id;
			const variation = hasVariations
				? Object.entries(selectedAttrs).map(([k, v]) => ({ attribute: k, value: v }))
				: [];
			await cart.addItem(id, quantity, variation);
			justAdded = true;
			setTimeout(() => (justAdded = false), 900);
		} finally {
			adding = false;
		}
	}
</script>

<div class="store-card" class:is-oos={!inStock}>
	<a class="store-card__media-link" href="/product/{product.slug}" aria-label={product.name}>
		<div class="store-card__media">
			{#if product.images[0]}
				<img src={product.images[0].src} alt={product.images[0].alt || product.name} loading="lazy" />
				{#if config.data.product_card?.secondary_image_on_hover && product.images[1]}
					<img
						class="store-card__media-secondary"
						src={product.images[1].src}
						alt={product.images[1].alt || product.name}
						loading="lazy"
						aria-hidden="true"
					/>
				{/if}
			{:else}
				<div class="store-card__placeholder" aria-hidden="true">
					<svg viewBox="0 0 48 48" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.2">
						<rect x="8" y="12" width="32" height="24" rx="1" />
						<circle cx="17" cy="21" r="2.5" />
						<path d="M40 30 L30 22 L19 32" />
					</svg>
				</div>
			{/if}
			{#if !inStock}
				<span class="store-card__badge store-card__badge--oos">Out of stock</span>
			{:else if maxTierPct > 0 && config.data.product_card?.show_bulk_badge !== false}
				<span class="store-card__badge tabular-nums">Bulk save<br />up to {formatPct(maxTierPct)}</span>
			{:else if product.on_sale}
				<span class="store-card__badge">{saleBadgeRendered}</span>
			{/if}
		</div>
	</a>

	<div class="store-card__body">
		<a class="store-card__title-link" href="/product/{product.slug}">
			<h3
				class="store-card__title"
				style={titleLayout && config.data.product_card?.title_lines === 'auto' ? `height: ${titleLayout.height}px` : ''}
			>{product.name}</h3>
		</a>

		{#if isQuantityStep && cro?.tiers && cro.tiers.length > 0 && config.data.product_card?.show_tier_hint !== false}
			<p class="store-card__tier-hint tabular-nums">
				{cro.tiers[0].min_qty}+ save {formatPct(cro.tiers[0].savings_pct)}
				{#if cro.tiers.length > 1}
					· {cro.tiers[cro.tiers.length - 1].min_qty}+ save {formatPct(maxTierPct)}
				{/if}
			</p>
		{/if}

		<div class="store-card__foot">
			{#if !inStock && config.data.product_card?.oos_treatment === 'hidden-price'}
				<div class="store-card__price-stack">
					<span class="store-card__sold-out">Sold out</span>
				</div>
			{:else}
				<div class="store-card__price-stack">
					{#if compareAtPrice}
						<span class="store-card__price-was tabular-nums">{compareAtPrice}</span>
					{/if}
					<span class="store-card__price tabular-nums">{displayPrice}</span>
				</div>
			{/if}

			<!-- svelte-ignore a11y_no_static_element_interactions -->
			<div class="store-card__selector-row" onpointerdown={(e) => e.stopPropagation()}>
				{#if canGoBack}
					<button type="button" class="store-card__step-nav" onclick={goBack} aria-label="Back">
						<svg viewBox="0 0 10 10" width="10" height="10" fill="none"><path d="M6.5 1.5L3 5l3.5 3.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
					</button>
				{/if}

				{#if isQuantityStep}
					<div class="store-card__qty-slot">
						<button type="button" class="store-card__qty-btn" onclick={decrementQty} disabled={quantity <= 1} aria-label="Decrease">
							<svg viewBox="0 0 12 12" width="12" height="12" fill="none"><path d="M2 6h8" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
						</button>
						<input class="store-card__qty-input tabular-nums" type="number" min="1" max={maxQty} value={quantity} oninput={handleQtyInput} aria-label="Quantity" />
						<button type="button" class="store-card__qty-btn" onclick={incrementQty} disabled={quantity >= maxQty} aria-label="Increase">
							<svg viewBox="0 0 12 12" width="12" height="12" fill="none"><path d="M6 2v8M2 6h8" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
						</button>
					</div>
				{:else}
					<label class="store-card__selector">
						<select
							bind:this={selectorControl}
							class="store-card__selector-control"
							aria-label={stepLabel(currentStepIdx)}
							value={stepValue}
							onchange={(e) => {
								const v = (e.currentTarget as HTMLSelectElement).value;
								if (v) selectAtIdx(currentStepIdx, v);
							}}
						>
							<option value="" disabled>{stepLabel(currentStepIdx)}</option>
							{#each stepOptions as opt}
								<option value={opt.value}>{opt.label}</option>
							{/each}
						</select>
						<svg class="store-card__selector-chevron" viewBox="0 0 10 10" width="10" height="10" fill="none"><path d="M2.5 4l2.5 2.5L7.5 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
					</label>
					{#if stepValue}
						<button type="button" class="store-card__step-nav" onclick={(e) => { e.preventDefault(); e.stopPropagation(); if (currentStepIdx < totalSteps - 1) currentStepIdx++; }} aria-label="Next">
							<svg viewBox="0 0 10 10" width="10" height="10" fill="none"><path d="M3.5 1.5L7 5l-3.5 3.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
						</button>
					{/if}
				{/if}

				<button
					type="button"
					class="store-card__add"
					class:is-adding={adding}
					class:just-added={justAdded}
					onclick={handleAction}
					disabled={adding || !readyToAdd || !inStock}
					aria-label={inStock ? `Add ${product.name} to cart` : `${product.name} is out of stock`}
				>
					{#if justAdded}
						<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
					{:else}
						<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
					{/if}
				</button>
			</div>
		</div>
	</div>
</div>

<style>
	.store-card {
		position: relative;
		display: flex;
		flex-direction: column;
		background: var(--bg);
		border: 1px solid var(--border);
		border-radius: var(--card-radius, 0);
		color: var(--fg);
		text-decoration: none;
		min-height: 100%;
		overflow: hidden;
		transition: transform var(--dur-med) var(--ease-out),
			border-color var(--dur-med) var(--ease-out),
			box-shadow var(--dur-med) var(--ease-out);
		/* Let the price stack query its own card width so narrow cards
		   (cross-sell 148px, 2-col mobile shop ~180px) vertical-stack
		   regular + sale, while wider cards keep them inline. */
		container-type: inline-size;
	}

	/* Border topology — data-card-border on <html> sets the variant. Full
	   is the baseline (default .store-card rule); others override here. */
	:global(html[data-card-border='bottom-only']) .store-card {
		border-width: 0 0 1px 0;
		border-radius: 0;
	}
	:global(html[data-card-border='none']) .store-card {
		border-color: transparent;
	}
	:global(html[data-card-border='hover-only']) .store-card {
		border-color: transparent;
	}
	:global(html[data-card-border='hover-only']) .store-card:hover {
		border-color: var(--border);
	}

	/* Hover variants — data-card-hover switches the feedback style. */
	:global(html[data-card-hover='lift']) .store-card:hover {
		transform: translateY(-2px);
		border-color: var(--fg-muted);
	}
	:global(html[data-card-hover='shadow']) .store-card:hover {
		box-shadow: 0 10px 24px rgba(0, 0, 0, 0.08);
	}
	:global(html[data-card-hover='border']) .store-card:hover {
		border-color: var(--accent);
		box-shadow: 0 0 0 1px var(--accent);
	}
	/* 'none' → no rule needed (transition still runs on other properties) */

	.store-card__media-link, .store-card__title-link {
		display: block;
		color: inherit;
		text-decoration: none;
	}

	.store-card__media {
		position: relative;
		aspect-ratio: var(--card-aspect-ratio, 1 / 1);
		background: var(--bg);
		overflow: hidden;
	}
	.store-card__media img {
		width: 100%;
		height: 100%;
		object-fit: cover;
		transition: transform var(--dur-slow) var(--ease-out), opacity var(--dur-med) var(--ease-out);
	}
	/* Secondary image overlays + fades on hover when enabled */
	.store-card__media .store-card__media-secondary {
		position: absolute;
		inset: 0;
		opacity: 0;
		transition: opacity var(--dur-med) var(--ease-out);
	}
	.store-card:hover .store-card__media-secondary {
		opacity: 1;
	}
	:global(html[data-card-hover='lift']) .store-card:hover .store-card__media img:not(.store-card__media-secondary) {
		transform: scale(1.025);
	}
	.store-card__placeholder {
		width: 100%;
		height: 100%;
		display: flex;
		align-items: center;
		justify-content: center;
		color: var(--fg-muted);
	}
	.store-card__badge {
		position: absolute;
		top: 10px;
		left: 10px;
		padding: 5px 8px 6px;
		background: var(--fg);
		color: var(--bg);
		font-size: 9px;
		font-weight: 600;
		line-height: 1.2;
		text-transform: uppercase;
		letter-spacing: 0.08em;
		border-radius: var(--card-radius, 0);
		z-index: 1;
	}
	.store-card__badge--oos {
		background: color-mix(in srgb, var(--fg) 82%, transparent);
	}

	/* Badge position variants — top-right is the default per 2025 CRO
	   research; top-left flips to left via data-card-badge-position. */
	:global(html[data-card-badge-position='top-right']) .store-card__badge {
		left: auto;
		right: 10px;
	}

	/* Badge style variants — filled is the default; outline swaps to a
	   transparent bg with a foreground-colored border + text; minimal
	   drops the chrome entirely for editorial brands. */
	:global(html[data-card-badge-style='outline']) .store-card__badge {
		background: transparent;
		color: var(--fg);
		border: 1px solid var(--fg);
		padding: 4px 7px 5px;
	}
	:global(html[data-card-badge-style='outline']) .store-card__badge--oos {
		border-color: color-mix(in srgb, var(--fg) 60%, transparent);
		color: color-mix(in srgb, var(--fg) 60%, transparent);
	}
	:global(html[data-card-badge-style='minimal']) .store-card__badge {
		background: transparent;
		color: var(--fg);
		padding: 4px 0 5px;
		letter-spacing: 0.1em;
		text-shadow: 0 1px 2px rgba(0, 0, 0, 0.25);
	}
	/* OOS treatment — data-card-oos-treatment on <html> switches between:
	   'grayscale' (default, current) — desaturates + dims
	   'dim'                          — opacity only, keeps color
	   'hidden-price'                 — still dims, plus price swap
	     happens in the template (ProductCard.svelte) */
	:global(html[data-card-oos-treatment='grayscale']) .store-card.is-oos .store-card__media img,
	:global(html[data-card-oos-treatment='grayscale']) .store-card.is-oos .store-card__media .store-card__placeholder {
		filter: grayscale(0.7) brightness(0.85);
		opacity: 0.6;
	}
	:global(html[data-card-oos-treatment='dim']) .store-card.is-oos .store-card__media img,
	:global(html[data-card-oos-treatment='dim']) .store-card.is-oos .store-card__media .store-card__placeholder {
		opacity: 0.45;
	}
	:global(html[data-card-oos-treatment='hidden-price']) .store-card.is-oos .store-card__media img,
	:global(html[data-card-oos-treatment='hidden-price']) .store-card.is-oos .store-card__media .store-card__placeholder {
		filter: grayscale(0.8);
		opacity: 0.5;
	}
	.store-card.is-oos .store-card__add {
		cursor: not-allowed;
	}

	.store-card__body {
		padding: 14px 16px 16px;
		display: flex;
		flex-direction: column;
		gap: 12px;
		flex: 1 1 auto;
	}
	.store-card__title {
		margin: 0;
		font-family: var(--font-heading, var(--font-sans));
		font-size: 15px;
		font-weight: var(--heading-weight, 500);
		line-height: 20px;
		letter-spacing: -0.24px;
		color: var(--fg);
		min-height: 20px;
		overflow: hidden;
	}

	/* Title lines — 'auto' (default) lets pretext measure; fixed values
	   override with -webkit-line-clamp. Only applied when the admin picks
	   a specific count so pretext keeps its intelligent measurement path. */
	:global(html[data-card-title-lines='1']) .store-card__title,
	:global(html[data-card-title-lines='2']) .store-card__title,
	:global(html[data-card-title-lines='3']) .store-card__title {
		display: -webkit-box;
		-webkit-box-orient: vertical;
		height: auto !important;
	}
	:global(html[data-card-title-lines='1']) .store-card__title { -webkit-line-clamp: 1; }
	:global(html[data-card-title-lines='2']) .store-card__title { -webkit-line-clamp: 2; }
	:global(html[data-card-title-lines='3']) .store-card__title { -webkit-line-clamp: 3; }
	.store-card__tier-hint {
		margin: -6px 0 0;
		font-size: 10px;
		font-weight: 500;
		text-transform: uppercase;
		letter-spacing: 0.06em;
		color: var(--success, #059669);
	}

	.store-card__foot {
		display: flex;
		flex-direction: column;
		gap: 10px;
		margin-top: auto;
	}
	.store-card__price-stack {
		display: inline-flex;
		align-items: baseline;
		gap: 6px;
		flex-wrap: wrap;
	}
	.store-card__price-was {
		font-size: 11px;
		font-weight: 450;
		color: var(--fg-muted);
		text-decoration: line-through;
	}
	.store-card__price {
		font-size: 14px;
		font-weight: 500;
		color: var(--fg);
		letter-spacing: -0.2px;
	}
	.store-card__sold-out {
		font-size: 13px;
		font-weight: 500;
		color: var(--fg-muted);
		text-transform: uppercase;
		letter-spacing: 0.06em;
	}
	/* Narrow cards (cross-sell 148px, 2-col mobile ~180px): stack
	   regular-price above sale-price vertically so 4-digit prices and
	   price ranges don't overflow or clip. `flex-wrap: wrap` above
	   handles the 4-digit case on mid-width cards (~200-260px) by
	   line-wrapping without fully committing to column layout. */
	@container (max-width: 200px) {
		.store-card__price-stack {
			flex-direction: column;
			align-items: flex-start;
			gap: 2px;
		}
		.store-card__price-was {
			font-size: 10.5px;
			line-height: 1.25;
		}
		.store-card__price {
			font-size: 13px;
			line-height: 1.2;
		}
	}

	.store-card__selector-row {
		display: flex;
		align-items: center;
		gap: 6px;
		width: 100%;
	}

	.store-card__step-nav, .store-card__add {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		width: 40px;
		height: 40px;
		flex-shrink: 0;
		padding: 0;
		border: 1px solid var(--border);
		border-radius: var(--card-button-radius, 0);
		background: transparent;
		color: var(--fg);
		cursor: pointer;
		transition: background var(--dur-fast) var(--ease), color var(--dur-fast) var(--ease), transform var(--dur-fast) var(--ease), border-color var(--dur-fast) var(--ease);
	}
	.store-card__step-nav:hover:not(:disabled), .store-card__add:hover:not(:disabled) {
		background: var(--fg);
		color: var(--bg);
		border-color: var(--fg);
	}
	.store-card__step-nav:active:not(:disabled), .store-card__add:active:not(:disabled) {
		transform: scale(0.9);
	}
	.store-card__add {
		background: transparent;
		color: var(--accent);
		border-color: var(--accent);
	}
	.store-card__add:hover:not(:disabled) {
		background: var(--accent);
		color: var(--accent-fg);
		border-color: var(--accent);
	}
	.store-card__add:disabled {
		cursor: default;
		background: transparent;
		border-color: var(--border);
		color: var(--fg-muted);
	}
	.store-card__add.is-adding {
		background: var(--accent);
		color: var(--accent-fg);
	}
	.store-card__add.just-added {
		background: var(--success, #059669);
		color: #fff;
		border-color: var(--success, #059669);
		animation: wchs-pop var(--dur-med) var(--ease-out);
	}

	/* Button style variants — outline is the baseline (default .store-card__add
	   rule above); 'solid' inverts fill to call attention harder; 'icon-only'
	   drops the border for the most minimal form. */
	:global(html[data-card-button='solid']) .store-card__add:not(:disabled) {
		background: var(--accent);
		color: var(--accent-fg);
		border-color: var(--accent);
	}
	:global(html[data-card-button='solid']) .store-card__add:hover:not(:disabled) {
		background: color-mix(in srgb, var(--accent) 88%, var(--fg));
		border-color: color-mix(in srgb, var(--accent) 88%, var(--fg));
	}
	:global(html[data-card-button='icon-only']) .store-card__add:not(:disabled) {
		border-color: transparent;
		background: transparent;
	}
	:global(html[data-card-button='icon-only']) .store-card__add:hover:not(:disabled) {
		background: color-mix(in srgb, var(--accent) 12%, transparent);
		border-color: transparent;
		color: var(--accent);
	}

	.store-card__selector {
		position: relative;
		flex: 1 1 0;
		min-width: 0;
		border: 1px solid var(--border);
		background: transparent;
		color: var(--fg);
		overflow: hidden;
		transition: border-color var(--dur-fast) var(--ease);
	}
	.store-card__selector:hover, .store-card__selector:focus-within {
		border-color: var(--fg);
	}
	.store-card__selector-control {
		appearance: none;
		width: 100%;
		height: 40px;
		padding: 0 28px 0 10px;
		border: 0;
		background: transparent;
		color: var(--fg);
		font: inherit;
		font-size: 10px;
		font-weight: 600;
		letter-spacing: 0.08em;
		text-transform: uppercase;
		cursor: pointer;
		outline: none;
		color-scheme: light dark;
	}
	.store-card__selector-control option {
		background: var(--bg);
		color: var(--fg);
	}
	.store-card__selector-chevron {
		position: absolute;
		top: 50%;
		right: 10px;
		transform: translateY(-50%);
		pointer-events: none;
	}

	.store-card__qty-slot {
		display: grid;
		grid-template-columns: 40px 1fr 40px;
		flex: 1 1 0;
		min-width: 0;
		height: 40px;
		border: 1px solid var(--border);
		overflow: hidden;
		transition: border-color var(--dur-fast) var(--ease);
	}
	.store-card__qty-slot:hover, .store-card__qty-slot:focus-within {
		border-color: var(--fg);
	}
	.store-card__qty-btn {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		width: 40px;
		height: 40px;
		padding: 0;
		border: 0;
		background: transparent;
		color: var(--fg);
		cursor: pointer;
		transition: background var(--dur-fast) var(--ease);
	}
	.store-card__qty-btn:hover:not(:disabled) {
		background: var(--border);
	}
	.store-card__qty-btn:disabled {
		opacity: 0.35;
		cursor: default;
	}
	.store-card__qty-input {
		width: 100%;
		height: 40px;
		padding: 0;
		border: 0;
		background: transparent;
		color: var(--fg);
		font: inherit;
		font-size: 11px;
		font-weight: 600;
		letter-spacing: 0.06em;
		text-align: center;
		appearance: textfield;
		-moz-appearance: textfield;
		outline: none;
	}
	.store-card__qty-input::-webkit-outer-spin-button,
	.store-card__qty-input::-webkit-inner-spin-button {
		appearance: none;
		margin: 0;
	}

	@keyframes wchs-pop {
		0% { transform: scale(0.8); }
		50% { transform: scale(1.12); }
		100% { transform: scale(1); }
	}
</style>
