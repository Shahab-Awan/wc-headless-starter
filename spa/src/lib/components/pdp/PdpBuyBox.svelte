<script lang="ts">
	import { onMount } from 'svelte';
	import { config } from '$lib/config.svelte';
	import PdpInfoTabs from '$lib/components/pdp/PdpInfoTabs.svelte';
	import PaymentMethodIcons from '$lib/components/PaymentMethodIcons.svelte';
	import { resolveBundleRows, defaultBundleMinQty, type BundleDisplayRow } from '$lib/pdp/bogo-bundles';
	import type { StoreProduct, StoreProductVariation, WchsCroProduct } from '$lib/wc/products';

	const TRUST_LABELS: Record<string, string> = {
		shield: '60-Day Money-Back Guarantee',
		shipping: 'Ships Today if Ordered Before 2PM',
		lock: 'Secure Checkout',
	};

	const PDP_TRUST_ICONS = ['shield', 'shipping', 'lock'] as const;

	const FEATURE_ICONS: Record<string, string> = {
		lab: '<path d="M9 4.8h6M10.2 4.8v4.3L6.5 16a3.5 3.5 0 0 0 3.1 5.2h4.8a3.5 3.5 0 0 0 3.1-5.2l-3.7-6.9V4.8M9.1 14.6h5.8"/>',
		zap: '<path d="M13 2L3 14h9l-1 8 10-12h-9z"/>',
		shield: '<path d="M12 3.6 18.4 6v5.5c0 4-2.5 7.5-6.4 8.9-3.9-1.4-6.4-4.9-6.4-8.9V6Z"/><path d="m9.3 12.2 1.9 1.9 3.6-3.9"/>',
		shipping: '<path d="M3.8 8.8h10.2v7.7H3.8z"/><path d="M14 11h3.1l3.1 3.2v2.3H14"/><circle cx="8" cy="17.6" r="1.7"/><circle cx="17.6" cy="17.6" r="1.7"/>',
		lock: '<rect x="4.5" y="11" width="15" height="9.5" rx="1.5"/><path d="M7.5 11V8a4.5 4.5 0 0 1 9 0v3"/>',
		check: '<path d="M5 12.5l4.2 4.2L19 7"/>',
	};

	let {
		product,
		brandName,
		selection,
		onSelectTerm,
		termAvailable,
		variations,
		selectedVariation,
		quantity,
		onQuantityChange,
		cro,
		hasTiers,
		regularMinor,
		unitPrice,
		lineTotal,
		maxTierPct,
		formatMoneyInt,
		formatPct,
		canAdd,
		addLabel,
		adding,
		justAdded,
		onAdd,
		showReviews,
		onReviewsOpen,
	}: {
		product: StoreProduct;
		brandName: string;
		selection: Record<string, string>;
		onSelectTerm: (attr: string, value: string) => void;
		termAvailable: (attr: string, value: string) => boolean;
		variations: StoreProductVariation[];
		selectedVariation: StoreProductVariation | null;
		quantity: number;
		onQuantityChange: (q: number) => void;
		cro: WchsCroProduct | null;
		hasTiers: boolean;
		regularMinor: number;
		unitPrice: number;
		lineTotal: number;
		maxTierPct: number;
		formatMoneyInt: (n: number) => string;
		formatPct: (n: number) => string;
		canAdd: boolean;
		addLabel: string;
		adding: boolean;
		justAdded: boolean;
		onAdd: () => void;
		showReviews: boolean;
		onReviewsOpen?: () => void;
	} = $props();

	const pdpUi = $derived(config.data.pdp);
	const features = $derived(pdpUi?.features?.length ? pdpUi.features : []);
	const trustBadges = $derived(
		PDP_TRUST_ICONS.map((icon) => ({
			icon,
			label: TRUST_LABELS[icon],
		}))
	);

	const purityLine = $derived.by(() => {
		const metrics = cro?.coa_metrics ?? config.data.pdp?.coa_section?.default_metrics ?? [];
		const row = metrics.find((m) => /hplc|purity/i.test(m.label));
		if (row?.value?.trim()) {
			const v = row.value.trim();
			if (/purity/i.test(v)) return v;
		}
		return '≥99% Purity';
	});

	const puritySubtitle = $derived.by(() => {
		const parts = Object.values(selection).filter(Boolean);
		if (parts.length) return `${parts.join(' · ')} — ${purityLine}`;
		return purityLine;
	});

	const bundleRows = $derived(
		resolveBundleRows(cro?.tiers ?? [], regularMinor, pdpUi?.bundle_bogo)
	);
	const showBundles = $derived(hasTiers && bundleRows.length > 0);

	let selectedBundleQty = $state<number | null>(null);

	$effect(() => {
		if (!showBundles) {
			selectedBundleQty = null;
			return;
		}
		const valid = bundleRows.some((b) => b.min_qty === selectedBundleQty);
		if (selectedBundleQty === null || !valid) {
			selectedBundleQty = defaultBundleMinQty(bundleRows);
		}
	});

	$effect(() => {
		if (selectedBundleQty !== null && quantity !== selectedBundleQty) {
			onQuantityChange(selectedBundleQty);
		}
	});

	function selectBundle(row: BundleDisplayRow) {
		selectedBundleQty = row.min_qty;
		onQuantityChange(row.min_qty);
	}

	const variationById = $derived(new Map(variations.map((v) => [v.id, v])));

	const addButtonLabel = $derived.by(() => {
		if (justAdded) return 'Added';
		if (adding) return 'Adding…';
		if (!canAdd) return addLabel;
		return `Add to Cart — ${formatMoneyInt(lineTotal)}`;
	});

	function variationIdForTerm(attrName: string, termName: string): number | null {
		for (const ref of product.variations) {
			const thisAttr = ref.attributes.find((a) => a.name === attrName);
			if (!thisAttr || thisAttr.value !== termName) continue;
			const matchesSelection = ref.attributes.every((a) => {
				if (a.name === attrName) return true;
				const chosen = selection[a.name];
				return chosen === undefined || chosen === a.value;
			});
			if (matchesSelection) return ref.id;
		}
		return null;
	}

	function sizePriceForTerm(attrName: string, termName: string): string {
		if (!product.has_options) return '';
		const vid = variationIdForTerm(attrName, termName);
		if (!vid) return '';
		const full = variationById.get(vid);
		if (!full) return '';
		return formatMoneyInt(Number(full.prices.price));
	}

	function bundleSubLabel(row: (typeof bundleRows)[number]): string {
		if (/^\d+ Vials?$/.test(row.title)) return 'of the same product';
		return row.title;
	}
</script>

<div class="pdp-buy">
	<nav class="pdp-buy__crumbs" aria-label="Breadcrumb">
		<a href="/">Home</a>
		<span aria-hidden="true">›</span>
		<a href="/shop">Products</a>
		<span aria-hidden="true">›</span>
		<span aria-current="page">{product.name}</span>
	</nav>

	<p class="pdp-buy__brand">{brandName}</p>
	<h1 class="pdp-buy__title">{product.name}</h1>

	{#if puritySubtitle}
		<div class="pdp-buy__subtitle-row">

				<span class="pdp-buy__free-shipping">Free Shipping Over $200</span>
				
			<p class="pdp-buy__subtitle">{puritySubtitle}</p>
			<span class="pdp-buy__verified">{pdpUi?.verified_label ?? 'VERIFIED'}</span>
		</div>
	{/if}




	{#if showReviews && product.review_count > 0 && onReviewsOpen}
		<button type="button" class="pdp-buy__rating" onclick={onReviewsOpen}>
			<span class="pdp-buy__stars" aria-hidden="true">
				{#each Array(5) as _, i}
					<span class:filled={i < Math.round(parseFloat(product.average_rating))}>★</span>
				{/each}
			</span>
			<span>{parseFloat(product.average_rating).toFixed(1)} ({product.review_count} reviews)</span>
		</button>
	{/if}

	<div class="pdp-buy__panel">
		{#if product.has_options}
			{#each product.attributes as attr, attrIdx}
				<div class="pdp-buy__section">
					<p class="pdp-buy__section-label">{attrIdx === 0 ? 'SELECT SIZE' : attr.name.toUpperCase()}</p>
					<div class="pdp-buy__size-grid">
						{#each attr.terms as term}
							{@const available = termAvailable(attr.name, term.name)}
							{@const priceHint = sizePriceForTerm(attr.name, term.name)}
							<button
								type="button"
								class="pdp-buy__size"
								class:pdp-buy__size--active={selection[attr.name] === term.name}
								class:pdp-buy__size--disabled={!available && selection[attr.name] !== term.name}
								disabled={!available && selection[attr.name] !== term.name}
								onclick={() => onSelectTerm(attr.name, term.name)}
							>
								<span class="pdp-buy__size-name">{term.name}</span>
								{#if priceHint}
									<span class="pdp-buy__size-price tabular-nums">{priceHint}</span>
								{/if}
							</button>
						{/each}
					</div>
				</div>
			{/each}
		{/if}

		{#if showBundles}
			<div class="pdp-buy__section">
				<p class="pdp-buy__section-label">SELECT YOUR BUNDLE</p>
				<div
					class="pdp-buy__bundles"
					style="--bundle-cols: {Math.min(bundleRows.length, 4)}"
				>
					{#each bundleRows as row (row.min_qty)}
						<button
							type="button"
							class="pdp-buy__bundle"
							class:pdp-buy__bundle--active={selectedBundleQty === row.min_qty}
							class:pdp-buy__bundle--tagged={!!row.flag}
							onclick={() => selectBundle(row)}
						>
							{#if row.flag}
								<span class="pdp-buy__bundle-flag">{row.flag}</span>
							{/if}
							<span class="pdp-buy__bundle-qty tabular-nums">{row.min_qty}</span>
							<span class="pdp-buy__bundle-sub">{bundleSubLabel(row)}</span>
							{#if row.savings_pct > 0}
								<span class="pdp-buy__bundle-off">{Math.round(row.savings_pct)}% off</span>
							{:else}
								<span class="pdp-buy__bundle-off pdp-buy__bundle-off--muted">Standard price</span>
							{/if}
							<span class="pdp-buy__bundle-total tabular-nums">{formatMoneyInt(row.line_total_at_min_qty)}</span>
							{#if row.compare_line_total > row.line_total_at_min_qty}
								<span class="pdp-buy__bundle-was tabular-nums">{formatMoneyInt(row.compare_line_total)}</span>
							{/if}
						</button>
					{/each}
				</div>
			</div>
		{/if}

		<div class="pdp-buy__actions">
			<button
				type="button"
				class="pdp-buy__add"
				class:pdp-buy__add--success={justAdded}
				disabled={!canAdd || adding}
				aria-busy={adding}
				onclick={onAdd}
			>
				<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
					<circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
				</svg>
				<span>{addButtonLabel}</span>
			</button>
		</div>

		<ul class="pdp-buy__trust" aria-label="Purchase guarantees">
			{#each trustBadges as badge}
				<li>
					{#if badge.icon && FEATURE_ICONS[badge.icon]}
						<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
							{@html FEATURE_ICONS[badge.icon]}
						</svg>
					{/if}
					<span>{badge.label}</span>
				</li>
			{/each}
		</ul>

		{#if pdpUi?.show_payment_icons !== false}
			<div class="pdp-buy__payments">
				<span class="pdp-buy__payments-label">We Accept:</span>
				<PaymentMethodIcons />
			</div>
		{/if}

		<PdpInfoTabs {product} {cro} {features} />
	</div>
</div>

<style>
	.pdp-buy {
		--pdp-radius: 16px;
		display: flex;
		flex-direction: column;
		gap: 0;
		color: var(--fg);
	}
	.pdp-buy__crumbs {
		display: flex;
		flex-wrap: wrap;
		align-items: center;
		gap: 8px;
		margin: 0 0 20px;
		font-size: 12px;
		color: var(--fg-muted);
	}
	.pdp-buy__crumbs a {
		color: var(--fg-muted);
		text-decoration: none;
	}
	.pdp-buy__crumbs a:hover {
		color: var(--accent);
	}
	.pdp-buy__free-shipping {
		display: flex;
		align-items: center;
		margin: 0;
		font-size: 12px;
		font-weight: 700;
		letter-spacing: 0.14em;
		text-transform: uppercase;
	}
	.pdp-buy__free-shipping::after {
		content: '';
		display: fixed;
		width: 2px;
		height: 20px;
		background: var(--border);
		margin: 6px;
	}
	.pdp-buy__brand {
		margin: 0 0 8px;
		font-size: 11px;
		font-weight: 700;
		letter-spacing: 0.14em;
		text-transform: uppercase;
		color: var(--accent);
	}
	.pdp-buy__title {
		margin: 0 0 12px;
		font-family: var(--font-heading, var(--font-sans));
		font-size: clamp(32px, 4vw, 44px);
		font-weight: var(--heading-weight, 600);
		line-height: 1.05;
		letter-spacing: -0.03em;
	}
	.pdp-buy__subtitle-row {
		display: flex;
		flex-wrap: wrap;
		align-items: center;
		gap: 10px;
		margin: 0 0 16px;
	}
	.pdp-buy__subtitle {
		margin: 0;
		font-size: 15px;
		font-weight: 500;
		color: var(--fg-muted);
	}
	.pdp-buy__verified {
		display: inline-flex;
		padding: 4px 10px;
		border-radius: 999px;
		background: color-mix(in srgb, var(--success, #059669) 14%, transparent);
		color: var(--success, #059669);
		font-size: 10px;
		font-weight: 700;
		letter-spacing: 0.08em;
	}
	.pdp-buy__rating {
		display: inline-flex;
		align-items: center;
		gap: 8px;
		margin: 0 0 16px;
		padding: 0;
		border: 0;
		background: transparent;
		font: inherit;
		font-size: 13px;
		color: var(--fg-muted);
		cursor: pointer;
	}
	.pdp-buy__stars .filled {
		color: var(--accent);
	}
	.pdp-buy__desc {
		margin: 0 0 20px;
		font-size: 14px;
		line-height: 1.6;
		color: var(--fg-muted);
	}
	.pdp-buy__desc :global(p) {
		margin: 0;
	}
	.pdp-buy__features {
		list-style: none;
		margin: 0 0 24px;
		padding: 0;
		display: grid;
		grid-template-columns: repeat(2, minmax(0, 1fr));
		gap: 12px 20px;
	}
	.pdp-buy__features li {
		display: flex;
		align-items: flex-start;
		gap: 10px;
		font-size: 13px;
		font-weight: 500;
		color: var(--fg);
	}
	.pdp-buy__features svg {
		flex-shrink: 0;
		color: var(--accent);
		margin-top: 1px;
	}
	.pdp-buy__panel {
		--pdp-shine-mid: color-mix(in srgb, var(--accent) 55%, #c9a227);
		border: 1px solid var(--border);
		border-radius: var(--pdp-radius);
		background: var(--bg);
		padding: 20px;
		display: flex;
		flex-direction: column;
		gap: 20px;
		box-shadow: 0 4px 24px color-mix(in srgb, var(--fg) 6%, transparent);
	}
	.pdp-buy__section-label {
		margin: 0 0 10px;
		font-size: 11px;
		font-weight: 700;
		letter-spacing: 0.12em;
		color: var(--fg-muted);
	}
	.pdp-buy__size-grid {
		display: grid;
		grid-template-columns: repeat(3, minmax(0, 1fr));
		gap: 10px;
	}
	@media (max-width: 520px) {
		.pdp-buy__size-grid {
			grid-template-columns: repeat(2, minmax(0, 1fr));
		}
	}
	.pdp-buy__size {
		display: flex;
		flex-direction: column;
		align-items: center;
		justify-content: center;
		gap: 4px;
		min-height: 72px;
		padding: 12px 10px;
		border: 1px solid var(--border);
		border-radius: var(--pdp-radius);
		background: var(--bg);
		color: var(--fg);
		font: inherit;
		cursor: pointer;
		transition: border-color var(--dur-fast) var(--ease), box-shadow var(--dur-fast) var(--ease);
	}
	.pdp-buy__size:hover:not(:disabled) {
		border-color: color-mix(in srgb, var(--accent) 50%, var(--border));
	}
	.pdp-buy__size--active {
		border-color: var(--accent);
		box-shadow: 0 0 0 1px var(--accent);
	}
	.pdp-buy__size--active .pdp-buy__size-name {
		color: var(--accent);
	}
	.pdp-buy__size--disabled {
		opacity: 0.4;
		cursor: not-allowed;
	}
	.pdp-buy__size-name {
		font-size: 14px;
		font-weight: 600;
	}
	.pdp-buy__size-price {
		font-size: 12px;
		color: var(--fg-muted);
	}
	.pdp-buy__bundles {
		display: grid;
		grid-template-columns: repeat(2, minmax(0, 1fr));
		gap: 10px;
	}
	@media (min-width: 640px) {
		.pdp-buy__bundles {
			grid-template-columns: repeat(var(--bundle-cols, 4), minmax(0, 1fr));
		}
	}
	.pdp-buy__bundle {
		position: relative;
		display: flex;
		flex-direction: column;
		align-items: center;
		justify-content: center;
		gap: 3px;
		min-height: 118px;
		width: 100%;
		padding: 16px 10px 12px;
		border: 1.5px solid var(--border);
		border-radius: var(--pdp-radius);
		background: var(--bg);
		color: var(--fg);
		font: inherit;
		text-align: center;
		cursor: pointer;
		transition:
			border-color var(--dur-fast) var(--ease),
			background var(--dur-fast) var(--ease),
			box-shadow var(--dur-fast) var(--ease);
	}
	.pdp-buy__bundle--tagged {
		padding-top: 22px;
	}
	.pdp-buy__bundle--active {
		border-color: var(--accent);
		box-shadow: 0 0 0 1px var(--accent), 0 4px 16px color-mix(in srgb, var(--accent) 18%, transparent);
	}
	.pdp-buy__bundle-flag {
		position: absolute;
		top: -10px;
		left: 50%;
		transform: translateX(-50%);
		padding: 4px 10px;
		border-radius: 999px;
		border: 1px solid color-mix(in srgb, #f59e0b 70%, var(--accent));
		background: #fbbf24;
		color: #111;
		font-size: 8px;
		font-weight: 800;
		letter-spacing: 0.08em;
		white-space: nowrap;
		max-width: calc(100% - 12px);
		overflow: hidden;
		text-overflow: ellipsis;
	}
	.pdp-buy__bundle-qty {
		font-size: clamp(28px, 5vw, 34px);
		font-weight: 800;
		line-height: 1;
		letter-spacing: -0.03em;
		color: var(--fg);
	}
	.pdp-buy__bundle-sub {
		font-size: 10px;
		line-height: 1.3;
		color: var(--fg-muted);
		max-width: 11ch;
	}
	.pdp-buy__bundle-off {
		margin-top: 2px;
		font-size: 13px;
		font-weight: 700;
		line-height: 1.2;
		color: var(--accent);
	}
	.pdp-buy__bundle-off--muted {
		font-size: 11px;
		font-weight: 600;
		color: var(--fg-muted);
	}
	.pdp-buy__bundle-total {
		margin-top: 4px;
		font-size: 12px;
		font-weight: 700;
		color: var(--fg);
		line-height: 1.1;
	}
	.pdp-buy__bundle-was {
		font-size: 10px;
		font-weight: 500;
		color: var(--fg-muted);
		text-decoration: line-through;
	}
	.pdp-buy__actions {
		display: block;
		width: 100%;
	}
	.pdp-buy__add {
		width: 100%;
		display: inline-flex;
		align-items: center;
		justify-content: center;
		gap: 10px;
		min-height: 52px;
		padding: 14px 20px;
		border: 0;
		border-radius: var(--pdp-radius);
		background: linear-gradient(135deg, var(--accent), var(--pdp-shine-mid));
		color: var(--accent-fg);
		font: inherit;
		font-size: 14px;
		font-weight: 700;
		cursor: pointer;
		box-shadow: 0 8px 22px color-mix(in srgb, var(--accent) 32%, transparent);
		transition:
			opacity var(--dur-fast) var(--ease),
			transform var(--dur-fast) var(--ease),
			box-shadow var(--dur-fast) var(--ease);
	}
	.pdp-buy__add:hover:not(:disabled) {
		opacity: 0.95;
		box-shadow: 0 10px 28px color-mix(in srgb, var(--accent) 40%, transparent);
	}
	.pdp-buy__add:disabled {
		opacity: 0.45;
		cursor: not-allowed;
	}
	.pdp-buy__add[aria-busy='true'] {
		cursor: wait;
	}
	.pdp-buy__add--success {
		background: var(--success, #059669);
		color: #fff;
	}
	.pdp-buy__payments {
		display: flex;
		flex-wrap: wrap;
		align-items: center;
		justify-content: center;
		gap: 8px;
		margin-top: 10px;
		font-size: 12px;
		color: var(--fg-muted);
	}
	.pdp-buy__payments-label {
		font-size: 12px;
		font-weight: 500;
		color: var(--fg-muted);
	}
	.pdp-buy__trust {
		list-style: none;
		margin: 10px 0 0;
		padding: 0;
		border-top: 0;
		display: grid;
		grid-template-columns: repeat(3, minmax(0, 1fr));
		gap: 8px 10px;
	}
	@media (max-width: 720px) {
		.pdp-buy__trust {
			grid-template-columns: repeat(3, minmax(0, 1fr));
			gap: 6px 8px;
		}

		.pdp-buy__trust li {
			font-size: 10px;
		}
	}
	.pdp-buy__trust li {
		display: flex;
		flex-direction: column;
		align-items: center;
		justify-content: flex-start;
		text-align: center;
		gap: 5px;
		font-size: 11px;
		line-height: 1.3;
		color: var(--fg-muted);
	}
	.pdp-buy__trust span {
		font-weight: 700;
		color: var(--fg);
	}
	.pdp-buy__trust svg {
		flex-shrink: 0;
		color: var(--success, #059669);
	}
	.pdp-buy__long {
		margin-top: 28px;
		padding: 20px;
		border: 1px solid var(--border);
		border-radius: var(--pdp-radius);
		background: var(--bg);
		box-shadow: 0 4px 24px color-mix(in srgb, var(--fg) 6%, transparent);
		font-size: 14px;
		line-height: 1.65;
		color: #111;
	}
	.pdp-buy__long :global(h1),
	.pdp-buy__long :global(h2),
	.pdp-buy__long :global(h3),
	.pdp-buy__long :global(h4),
	.pdp-buy__long :global(h5),
	.pdp-buy__long :global(h6) {
		margin: 0 0 12px;
		font-family: var(--font-heading, var(--font-sans));
		font-weight: 700;
		line-height: 1.25;
		letter-spacing: -0.02em;
		color: #000;
	}
	.pdp-buy__long :global(h2:not(:first-child)),
	.pdp-buy__long :global(h3:not(:first-child)) {
		margin-top: 20px;
	}
	.pdp-buy__long :global(p),
	.pdp-buy__long :global(li),
	.pdp-buy__long :global(td),
	.pdp-buy__long :global(th) {
		color: #111;
	}
	.pdp-buy__long :global(strong),
	.pdp-buy__long :global(b) {
		font-weight: 600;
		color: #000;
	}
	.pdp-buy__long :global(a) {
		color: var(--accent);
		font-weight: 500;
	}
	.pdp-buy__long :global(ul),
	.pdp-buy__long :global(ol) {
		margin: 0 0 12px;
		padding-left: 1.25em;
	}
	.pdp-buy__long :global(p:first-child),
	.pdp-buy__long :global(h1:first-child),
	.pdp-buy__long :global(h2:first-child) {
		margin-top: 0;
	}
	.pdp-buy__long :global(p:last-child),
	.pdp-buy__long :global(ul:last-child),
	.pdp-buy__long :global(ol:last-child) {
		margin-bottom: 0;
	}
	:global([data-theme='dark']) .pdp-buy__long,
	:global([data-theme='dark']) .pdp-buy__long :global(p),
	:global([data-theme='dark']) .pdp-buy__long :global(li),
	:global([data-theme='dark']) .pdp-buy__long :global(td),
	:global([data-theme='dark']) .pdp-buy__long :global(th) {
		color: var(--fg);
	}
	:global([data-theme='dark']) .pdp-buy__long :global(h1),
	:global([data-theme='dark']) .pdp-buy__long :global(h2),
	:global([data-theme='dark']) .pdp-buy__long :global(h3),
	:global([data-theme='dark']) .pdp-buy__long :global(h4),
	:global([data-theme='dark']) .pdp-buy__long :global(h5),
	:global([data-theme='dark']) .pdp-buy__long :global(h6),
	:global([data-theme='dark']) .pdp-buy__long :global(strong),
	:global([data-theme='dark']) .pdp-buy__long :global(b) {
		color: var(--fg);
	}
</style>
