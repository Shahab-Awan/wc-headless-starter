<script lang="ts">
	import { onMount } from 'svelte';
	import ProductCard from './ProductCard.svelte';
	import { bridgeAwareHref } from '$lib/bridge-domain';
	import { listProducts, type StoreProduct } from '$lib/wc/products';
	import { config as siteConfig, isCartCrossSellBlockedProduct } from '$lib/config.svelte';
	import { auth } from '$lib/wc/auth.svelte';
	import { pickPopularProducts } from '$lib/popular-products';
	import type { FeaturedProductsModuleConfig, SpacingPreset, ModuleResolved } from '$lib/config.svelte';

	let {
		config,
		spacing_v = 'normal',
		spacing_h = 'normal',
		resolved,
	}: {
		config: FeaturedProductsModuleConfig;
		spacing_v?: SpacingPreset;
		spacing_h?: SpacingPreset;
		resolved?: ModuleResolved;
	} = $props();

	const accentStyle = $derived(
		resolved?.accent_color ? `--fp-teal: ${resolved.accent_color};` : ''
	);

	const eyebrow = $derived(config.eyebrow?.trim() || 'Bestsellers');
	const headlinePrefix = $derived(config.headline_prefix?.trim() || 'Featured');
	const headlineAccent = $derived(config.headline_accent?.trim() || 'Products');
	const subheadline = $derived(
		config.subheadline?.trim() ||
			'Explore our most popular research compounds, chosen for their quality, purity, and consistency.'
	);
	const productBadge = $derived(config.product_badge?.trim() || 'Most Popular');
	const ctaText = $derived(config.cta_text?.trim() || 'Explore All Products');
	const ctaHref = $derived(bridgeAwareHref(config.cta_href?.trim() || '/shop'));
	const productLimit = $derived(Math.min(6, Math.max(1, Number(config.product_limit) || 3)));
	const source = $derived(config.source === 'best_sellers' ? 'best_sellers' : 'popular');

	let products = $state<StoreProduct[]>([]);
	let loading = $state(true);

	onMount(async () => {
		try {
			const pool = await listProducts({
				per_page: source === 'best_sellers' ? productLimit : 100,
				orderby: source === 'best_sellers' ? 'popularity' : 'date',
			});
			const filtered = pool.filter((p) => !isCartCrossSellBlockedProduct(p.id, p.slug));
			products =
				source === 'best_sellers'
					? filtered.slice(0, productLimit)
					: pickPopularProducts(filtered, productLimit);
		} catch {
			products = [];
		} finally {
			loading = false;
		}
	});
</script>

{#if siteConfig.data.access_mode !== 1 || auth.isAuthenticated}
	<section
		class="fp"
		class:is-v-compact={spacing_v === 'compact'}
		class:is-v-spacious={spacing_v === 'spacious'}
		class:is-h-compact={spacing_h === 'compact'}
		class:is-h-spacious={spacing_h === 'spacious'}
		style={accentStyle}
		aria-labelledby="fp-title"
	>
		<div class="fp__shell">
			<header class="fp__head">
				{#if eyebrow}
					<p class="fp__eyebrow">{eyebrow}</p>
				{/if}
				<h2 id="fp-title" class="fp__title">
					{#if headlinePrefix}<span class="fp__title-pre">{headlinePrefix}</span>{/if}
					{#if headlineAccent}<span class="fp__title-accent">{headlineAccent}</span>{/if}
				</h2>
				{#if subheadline}
					<p class="fp__sub">{subheadline}</p>
				{/if}
			</header>

			{#if loading}
				<p class="fp__status" role="status">Loading products…</p>
			{:else if products.length}
				<ul class="fp__grid">
					{#each products as product (product.id)}
						<li class="fp__cell">
							<ProductCard
								{product}
								highlightBadge={productBadge}
								listingSource="Homepage — Featured products"
							/>
						</li>
					{/each}
				</ul>
				<div class="fp__actions">
					<a class="fp__cta" href={ctaHref}>{ctaText}</a>
				</div>
			{/if}
		</div>
	</section>
{/if}

<style>
	.fp {
		--fp-teal: var(--accent, #0d9488);
		--mod-pt: var(--wchs-spacing-v-normal, 48px);
		--mod-pb: var(--wchs-spacing-v-normal, 56px);
		--mod-px: clamp(20px, 4vw, 32px);
		width: 100%;
		padding: var(--mod-pt) var(--mod-px) var(--mod-pb);
	}

	.fp.is-v-compact {
		--mod-pt: var(--wchs-spacing-v-compact, 24px);
		--mod-pb: var(--wchs-spacing-v-compact, 28px);
	}

	.fp.is-v-spacious {
		--mod-pt: var(--wchs-spacing-v-spacious, 64px);
		--mod-pb: var(--wchs-spacing-v-spacious, 72px);
	}

	.fp__shell {
		max-width: min(1120px, 100%);
		margin: 0 auto;
	}

	.fp.is-h-compact .fp__shell {
		max-width: 100%;
	}

	.fp.is-h-spacious .fp__shell {
		max-width: 920px;
	}

	.fp__head {
		display: flex;
		flex-direction: column;
		align-items: center;
		text-align: center;
		gap: 14px;
		margin-bottom: clamp(28px, 4vw, 36px);
	}

	.fp__eyebrow {
		margin: 0;
		font-size: 11px;
		font-weight: 700;
		letter-spacing: 0.16em;
		text-transform: uppercase;
		color: var(--fp-teal);
	}

	.fp__title {
		margin: 0;
		max-width: 16ch;
		font-family: var(--font-heading, var(--font-sans));
		font-size: clamp(30px, 4.8vw, 46px);
		font-weight: 800;
		line-height: 1.06;
		letter-spacing: -0.03em;
		color: var(--fg);
	}

	.fp__title-pre {
		display: inline;
	}

	.fp__title-accent {
		display: inline;
		color: var(--fp-teal);
	}

	.fp__sub {
		margin: 0;
		max-width: 52ch;
		font-size: clamp(14px, 2vw, 16px);
		line-height: 1.6;
		color: color-mix(in srgb, var(--fg) 62%, transparent);
	}

	.fp__status {
		margin: 0;
		text-align: center;
		font-size: 14px;
		color: color-mix(in srgb, var(--fg) 58%, transparent);
	}

	.fp__grid {
		list-style: none;
		margin: 0;
		padding: 0;
		display: grid;
		grid-template-columns: repeat(3, minmax(0, 1fr));
		gap: clamp(14px, 2.5vw, 22px);
	}

	.fp__cell {
		min-width: 0;
	}

	.fp__actions {
		display: flex;
		justify-content: center;
		margin-top: clamp(28px, 4vw, 36px);
	}

	.fp__cta {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		min-height: 48px;
		padding: 12px 28px;
		border-radius: 999px;
		background: var(--fg);
		color: var(--bg);
		border: 1px solid var(--fg);
		font-size: 14px;
		font-weight: 600;
		text-decoration: none;
		transition:
			opacity var(--dur-fast) var(--ease),
			transform var(--dur-fast) var(--ease);
	}

	.fp__cta:hover {
		opacity: 0.92;
		transform: translateY(-1px);
	}

	@media (max-width: 900px) {
		.fp__grid {
			grid-template-columns: repeat(2, minmax(0, 1fr));
		}
	}

	@media (max-width: 560px) {
		.fp__grid {
			grid-template-columns: minmax(0, 1fr);
			max-width: 360px;
			margin-inline: auto;
		}

		.fp__title {
			max-width: none;
		}

		.fp__cta {
			width: 100%;
			max-width: 320px;
		}
	}
</style>
