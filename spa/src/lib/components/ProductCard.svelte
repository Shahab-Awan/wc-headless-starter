<script lang="ts">
	import { pretext } from '$lib/pretext/engine';
	import { onMount } from 'svelte';
	import { formatPrice as fmt } from '$lib/utils/format';
	import { config } from '$lib/config.svelte';

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
		on_sale?: boolean;
		is_in_stock?: boolean;
		extensions?: {
			wchs_cro?: {
				regular_price: number;
				tier_type: 'fixed' | 'percentage' | null;
				tiers: { min_qty: number; savings_pct: number }[];
			};
		};
	};

	let { product, cardWidth = 252, listingSource }: { product: Product; cardWidth?: number; listingSource?: string } = $props();
	let fontsReady = $state(false);

	onMount(async () => {
		await pretext.ready();
		fontsReady = true;
	});

	const cro = $derived(product.extensions?.wchs_cro);
	const hasVariations = $derived(!!(product.has_options && product.prices.price_range));
	const inStock = $derived(product.is_in_stock !== false);
	const productHref = $derived(`/product/${product.slug}`);

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
		if (hasVariations && product.prices.price_range) {
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

	const salePercent = $derived.by(() => {
		if (maxTierPct > 0) return Math.round(maxTierPct);
		const reg = parseFloat(product.prices.regular_price ?? '0');
		const cur = parseFloat(product.prices.price ?? '0');
		if (reg > 0 && cur > 0 && reg > cur) {
			return Math.round(((reg - cur) / reg) * 100);
		}
		return 0;
	});

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

	function reportProductLinkIntent(e: MouseEvent) {
		const el = e.currentTarget;
		if (!(el instanceof HTMLAnchorElement)) return;
		if (e.defaultPrevented || e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;
		const src = listingSource?.trim() || 'Product listing';
		void import('$lib/analytics').then((m) =>
			m.trackCustomerLabsProductClickedFromListing({
				id: product.id,
				name: product.name,
				slug: product.slug,
				prices: product.prices,
				permalink: product.permalink,
				image: product.images[0]?.src,
				listingSource: src,
			})
		);
	}
</script>

<div class="store-card" class:is-oos={!inStock}>
	<a class="store-card__media-link" href={productHref} aria-label={product.name} onclick={reportProductLinkIntent}>
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
		<a class="store-card__title-link" href={productHref} onclick={reportProductLinkIntent}>
			<h3
				class="store-card__title"
				style={titleLayout && config.data.product_card?.title_lines === 'auto' ? `height: ${titleLayout.height}px` : ''}
			>{product.name}</h3>
		</a>

		{#if cro?.tiers && cro.tiers.length > 0 && config.data.product_card?.show_tier_hint !== false}
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

			<a
				class="store-card__select"
				href={productHref}
				onclick={reportProductLinkIntent}
				aria-label={inStock ? `Select ${product.name}` : `View ${product.name}`}
			>
				Select
			</a>
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
		container-type: inline-size;
	}

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

	:global(html[data-card-badge-position='top-right']) .store-card__badge {
		left: auto;
		right: 10px;
	}

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

	.store-card__select {
		display: flex;
		align-items: center;
		justify-content: center;
		width: 100%;
		height: 40px;
		padding: 0 12px;
		border: 1px solid var(--accent);
		border-radius: var(--card-button-radius, 0);
		background: transparent;
		color: var(--accent);
		font: inherit;
		font-size: 11px;
		font-weight: 600;
		letter-spacing: 0.08em;
		text-transform: uppercase;
		text-decoration: none;
		cursor: pointer;
		transition: background var(--dur-fast) var(--ease), color var(--dur-fast) var(--ease), border-color var(--dur-fast) var(--ease), transform var(--dur-fast) var(--ease);
	}
	.store-card__select:hover {
		background: var(--accent);
		color: var(--accent-fg);
		border-color: var(--accent);
	}
	.store-card__select:active {
		transform: scale(0.98);
	}
	.store-card.is-oos .store-card__select {
		border-color: var(--border);
		color: var(--fg-muted);
	}
	.store-card.is-oos .store-card__select:hover {
		background: var(--fg);
		color: var(--bg);
		border-color: var(--fg);
	}

	:global(html[data-card-button='solid']) .store-card__select {
		background: var(--accent);
		color: var(--accent-fg);
		border-color: var(--accent);
	}
	:global(html[data-card-button='solid']) .store-card__select:hover {
		background: color-mix(in srgb, var(--accent) 88%, var(--fg));
		border-color: color-mix(in srgb, var(--accent) 88%, var(--fg));
	}
	:global(html[data-card-button='icon-only']) .store-card__select {
		border-color: transparent;
		background: transparent;
	}
	:global(html[data-card-button='icon-only']) .store-card__select:hover {
		background: color-mix(in srgb, var(--accent) 12%, transparent);
		border-color: transparent;
		color: var(--accent);
	}
</style>
