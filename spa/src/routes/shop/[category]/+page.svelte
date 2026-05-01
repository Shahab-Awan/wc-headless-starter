<script lang="ts">
	import { onMount } from 'svelte';
	import { page } from '$app/state';
	import { config } from '$lib/config.svelte';
	import AccessGate from '$lib/components/AccessGate.svelte';
	import ShopGrid from '$lib/components/ShopGrid.svelte';
	import SEO from '$lib/components/SEO.svelte';
	import { listCategories, type StoreCategory } from '$lib/wc/products';

	const categorySlug = $derived(page.params.category ?? '');
	let categories = $state<StoreCategory[]>([]);
	let categoriesLoaded = $state(false);
	let categoryLoadFailed = $state(false);

	const category = $derived(categories.find((c) => c.slug === categorySlug) ?? null);
	const categoryTitle = $derived(category?.name ?? titleizeSlug(categorySlug));
	const categoryMissing = $derived(categoriesLoaded && !categoryLoadFailed && !category);

	const categoryUrl = $derived.by(() => {
		const origin = typeof window !== 'undefined'
			? window.location.origin
			: ((config.data as any).spa_origin || '');
		return `${origin}/shop/${categorySlug}`;
	});

	const categoryBreadcrumb = $derived.by(() => {
		const origin = typeof window !== 'undefined'
			? window.location.origin
			: ((config.data as any).spa_origin || '');
		return {
			'@context': 'https://schema.org',
			'@type': 'BreadcrumbList',
			itemListElement: [
				{ '@type': 'ListItem', position: 1, name: config.data.brand_name, item: `${origin}/` },
				{ '@type': 'ListItem', position: 2, name: 'Shop', item: `${origin}/shop` },
				{ '@type': 'ListItem', position: 3, name: categoryTitle, item: `${origin}/shop/${categorySlug}` },
			],
		};
	});

	function titleizeSlug(slug: string): string {
		return slug
			.split('-')
			.filter(Boolean)
			.map((part) => part.charAt(0).toUpperCase() + part.slice(1))
			.join(' ') || 'Category';
	}

	onMount(async () => {
		try {
			categories = await listCategories();
		} catch {
			categoryLoadFailed = true;
		} finally {
			categoriesLoaded = true;
		}
	});
</script>

{#if categoryMissing}
	<SEO title="Category Not Found" description="Category not found" type="website" noindex={true} />
	<AccessGate requires="products">
		<section class="shop-category-missing">
			<h1>Category not found</h1>
			<p>The category you're looking for doesn't exist.</p>
			<a href="/shop">Back to shop</a>
		</section>
	</AccessGate>
{:else}
	<SEO
		title={categoryTitle}
		description={`${config.data.brand_name} — browse ${categoryTitle}`}
		url={categoryUrl}
		type="website"
		schema={categoryBreadcrumb}
	/>
	<AccessGate requires="products">
		<ShopGrid title={categoryTitle} category={categorySlug} spacing_h={config.data.shop?.spacing_h ?? 'normal'} />
	</AccessGate>
{/if}

<style>
	.shop-category-missing {
		max-width: 720px;
		margin: 0 auto;
		padding: 96px 24px;
		text-align: center;
	}

	.shop-category-missing h1 {
		margin: 0 0 12px;
		font-size: clamp(2rem, 5vw, 4rem);
		line-height: 0.95;
	}

	.shop-category-missing p {
		margin: 0 0 28px;
		color: var(--color-muted, #667085);
	}

	.shop-category-missing a {
		color: inherit;
		font-weight: 700;
		text-transform: uppercase;
		letter-spacing: 0.12em;
	}
</style>
