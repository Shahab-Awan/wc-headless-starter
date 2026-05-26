<script lang="ts">
	import { onMount } from 'svelte';
	import ProductSlider from './ProductSlider.svelte';
	import { listProducts, getProductsByIds, type StoreProduct } from '$lib/wc/products';
	import { config as siteConfig, isCartCrossSellBlockedProduct } from '$lib/config.svelte';
	import { auth } from '$lib/wc/auth.svelte';
	import type { ProductSliderModuleConfig, SpacingPreset } from '$lib/config.svelte';

	let { config, title_link = '/shop', spacing_v = 'normal', spacing_h = 'normal', center_header = false }: {
		config: ProductSliderModuleConfig;
		title_link?: string;
		spacing_v?: SpacingPreset;
		spacing_h?: SpacingPreset;
		center_header?: boolean;
	} = $props();

	let products = $state<StoreProduct[]>([]);
	let error = $state<string | null>(null);
	let loading = $state(true);

	onMount(async () => {
		try {
			switch (config.source) {
				case 'featured':
					products = await listProducts({ per_page: 12, featured: true });
					break;
				case 'category':
					products = config.category
						? await listProducts({ per_page: 12, category: config.category })
						: await listProducts({ per_page: 12 });
					break;
				case 'best_sellers':
					products = await listProducts({ per_page: 12, orderby: 'popularity' });
					break;
				case 'manual':
					products = config.product_ids.length
						? await getProductsByIds(config.product_ids)
						: [];
					break;
				default:
					products = await listProducts({ per_page: 12 });
			}
			products = products.filter(
				(p) => !isCartCrossSellBlockedProduct(p.id, p.slug)
			);
		} catch (e) {
			error = e instanceof Error ? e.message : String(e);
		} finally {
			loading = false;
		}
	});
</script>

{#if siteConfig.data.access_mode === 1 && !auth.isAuthenticated}
	<section class="homepage-module" id="members-cta">
		<div class="homepage-module__locked">
			<p class="homepage-module__locked-text">Sign in or create an account to browse our products</p>
			<a class="homepage-module__locked-cta" href="/account">Sign in or register</a>
		</div>
	</section>
{:else if !loading && products.length > 0}
	<section class="homepage-module" class:is-v-compact={spacing_v === 'compact'} class:is-v-spacious={spacing_v === 'spacious'} class:is-h-compact={spacing_h === 'compact'} class:is-h-spacious={spacing_h === 'spacious'} id={config.title?.toLowerCase().replace(/\s+/g, '-')}>
		<div class="homepage-module__head" class:is-centered={center_header}>
			<h2 class="homepage-module__label wchs-section-heading">{config.title || 'Products'}</h2>
			{#if !center_header}
				<a class="homepage-module__more" href={title_link}>All products →</a>
			{/if}
		</div>
		<ProductSlider {products} edge_to_edge={spacing_h === 'compact'} />
	</section>
{/if}

<style>
	.homepage-module {
		--mod-pt: var(--wchs-spacing-v-normal, 48px);
		--mod-pb: var(--wchs-spacing-v-normal, 48px);
		--mod-px: 24px;
		--mod-max-w: 1440px;
		max-width: var(--mod-max-w);
		margin: 0 auto;
		padding: var(--mod-pt) var(--mod-px) var(--mod-pb);
	}
	.homepage-module.is-v-compact {
		--mod-pt: var(--wchs-spacing-v-compact, 20px);
		--mod-pb: var(--wchs-spacing-v-compact, 24px);
	}
	.homepage-module.is-v-spacious {
		--mod-pt: var(--wchs-spacing-v-spacious, 72px);
		--mod-pb: var(--wchs-spacing-v-spacious, 80px);
	}
	.homepage-module.is-h-compact  { --mod-max-w: 100%; --mod-px: 12px; }
	.homepage-module.is-h-spacious { --mod-max-w: 760px; --mod-px: 40px; }

	.homepage-module__head {
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: 16px;
		padding: 0 0 22px;
	}
	.homepage-module__head.is-centered {
		justify-content: center;
		text-align: center;
	}
	.homepage-module__label {
		flex: 1;
		min-width: 0;
		line-height: 1.2;
		padding-right: 12px;
	}

	.homepage-module__more {
		font-size: 12px;
		font-weight: 450;
		letter-spacing: 0.04em;
		color: var(--fg-muted);
		text-decoration: none;
		transition: color var(--dur-fast) var(--ease);
	}
	.homepage-module__more:hover { color: var(--fg); }

	.homepage-module__locked {
		text-align: center;
		padding: 48px 24px;
		border: 1px solid var(--border);
	}
	.homepage-module__locked-text {
		font-size: 14px;
		color: var(--fg-muted);
		margin: 0 0 20px;
	}
	.homepage-module__locked-cta {
		display: inline-flex;
		padding: 12px 24px;
		background: var(--accent);
		color: var(--accent-fg);
		border: 1px solid var(--accent);
		text-decoration: none;
		font-size: 12px;
		font-weight: 600;
		text-transform: uppercase;
		letter-spacing: 0.08em;
	}
</style>
