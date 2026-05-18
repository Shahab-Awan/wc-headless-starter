<script lang="ts">
	import ProductCard from './ProductCard.svelte';
	import {
		listCategories,
		listProducts,
		type StoreCategory,
		type StoreProduct,
	} from '$lib/wc/products';
	import type { SpacingPreset } from '$lib/config.svelte';

	type ShopSection = {
		category: StoreCategory;
		index: number;
		products: StoreProduct[];
		title: string;
		subtitle: string;
	};

	let {
		spacing_v = 'normal',
		spacing_h = 'normal',
		searchQuery = '',
		showPageHead = true,
		pageTitle = 'Shop',
		showIntro = false,
		introEyebrow = 'Research catalog',
		introHeadline = 'Research-grade peptides, organized by category',
		introSubheadline = 'Browse our most requested compounds by research area. Select a category below to jump to products.',
	}: {
		spacing_v?: SpacingPreset;
		spacing_h?: SpacingPreset;
		searchQuery?: string;
		/** Hide the page H1 (e.g. when embedded on the homepage). */
		showPageHead?: boolean;
		pageTitle?: string;
		/** Centered eyebrow + headline block above category nav (homepage). */
		showIntro?: boolean;
		introEyebrow?: string;
		introHeadline?: string;
		introSubheadline?: string;
	} = $props();

	let loading = $state(true);
	let error = $state<string | null>(null);
	let sections = $state<ShopSection[]>([]);
	let searchResults = $state<StoreProduct[]>([]);
	let activeSlug = $state('');

	const trimmedSearch = $derived(searchQuery.trim());
	const isSearchMode = $derived(trimmedSearch.length >= 2);

	const HEADER_OFFSET = 120;

	function stripHtml(html: string): string {
		return html.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
	}

	function categoryCopy(cat: StoreCategory): { title: string; subtitle: string } {
		const plain = stripHtml(cat.description ?? '');
		if (!plain) return { title: cat.name, subtitle: '' };
		const dot = plain.indexOf('. ');
		if (dot > 24 && dot < plain.length - 8) {
			return { title: plain.slice(0, dot + 1), subtitle: plain.slice(dot + 2) };
		}
		return { title: cat.name, subtitle: plain };
	}

	function sectionId(slug: string): string {
		return `shop-cat-${slug}`;
	}

	function scrollToCategory(slug: string) {
		const el = document.getElementById(sectionId(slug));
		if (!el) return;
		activeSlug = slug;
		const top = el.getBoundingClientRect().top + window.scrollY - HEADER_OFFSET;
		window.scrollTo({ top: Math.max(0, top), behavior: 'smooth' });
	}

	function isTopLevelCategory(cat: StoreCategory): boolean {
		return cat.parent === 0 && cat.slug !== 'uncategorized' && cat.count > 0;
	}

	$effect(() => {
		const q = trimmedSearch;
		const searchMode = q.length >= 2;
		let observers: IntersectionObserver[] = [];
		let cancelled = false;

		(async () => {
			loading = true;
			error = null;
			searchResults = [];
			sections = [];
			try {
				if (searchMode) {
					searchResults = await listProducts({
						search: q,
						per_page: 48,
						orderby: 'title',
						order: 'asc',
					});
					return;
				}

				const cats = (await listCategories({ parent: 0 }))
					.filter(isTopLevelCategory)
					.sort((a, b) => a.id - b.id);

				const built = await Promise.all(
					cats.map(async (category, i) => {
						const products = await listProducts({
							category: category.slug,
							per_page: 100,
							orderby: 'title',
							order: 'asc',
						});
						const copy = categoryCopy(category);
						return {
							category,
							index: i + 1,
							products,
							title: copy.title,
							subtitle: copy.subtitle,
						} satisfies ShopSection;
					})
				);

				if (cancelled) return;

				sections = built.filter((s) => s.products.length > 0);
				activeSlug = sections[0]?.category.slug ?? '';

				const hash = window.location.hash.replace(/^#/, '');
				if (hash.startsWith('shop-cat-')) {
					const slug = hash.slice('shop-cat-'.length);
					if (sections.some((s) => s.category.slug === slug)) {
						requestAnimationFrame(() => scrollToCategory(slug));
					}
				}

				requestAnimationFrame(() => {
					if (cancelled) return;
					const obs = new IntersectionObserver(
						(entries) => {
							const visible = entries
								.filter((e) => e.isIntersecting)
								.sort((a, b) => b.intersectionRatio - a.intersectionRatio);
							const hit = visible[0]?.target as HTMLElement | undefined;
							if (hit?.dataset.catSlug) activeSlug = hit.dataset.catSlug;
						},
						{ rootMargin: `-${HEADER_OFFSET}px 0px -55% 0px`, threshold: [0, 0.15, 0.4] }
					);
					for (const s of sections) {
						const el = document.getElementById(sectionId(s.category.slug));
						if (el) obs.observe(el);
					}
					observers.push(obs);
				});
			} catch (e) {
				if (!cancelled) error = e instanceof Error ? e.message : String(e);
			} finally {
				if (!cancelled) loading = false;
			}
		})();

		return () => {
			cancelled = true;
			for (const o of observers) o.disconnect();
		};
	});
</script>

<section
	class="shop-cat"
	class:is-v-compact={spacing_v === 'compact'}
	class:is-v-spacious={spacing_v === 'spacious'}
	class:is-h-compact={spacing_h === 'compact'}
	class:is-h-spacious={spacing_h === 'spacious'}
	aria-label="Shop catalog"
>
	{#if showPageHead}
		<header class="shop-cat__page-head">
			<h1 class="shop-cat__page-title">
				{#if isSearchMode}
					Search: {trimmedSearch}
				{:else}
					{pageTitle}
				{/if}
			</h1>
		</header>
	{/if}

	{#if showIntro && !isSearchMode}
		<header class="shop-cat__intro">
			<p class="shop-cat__intro-eyebrow">{introEyebrow}</p>
			<h2 class="shop-cat__intro-title">{introHeadline}</h2>
			<p class="shop-cat__intro-sub">{introSubheadline}</p>
		</header>
	{/if}

	{#if loading}
		<p class="shop-cat__status" role="status">Loading catalog…</p>
	{:else if error}
		<p class="shop-cat__status shop-cat__status--err" role="alert">{error}</p>
	{:else if isSearchMode}
		{#if !searchResults.length}
			<p class="shop-cat__status">No products matched your search.</p>
		{:else}
			<ul class="shop-cat__grid shop-cat__grid--search">
				{#each searchResults as product (product.id)}
					<li class="shop-cat__cell">
						<ProductCard {product} listingSource="Shop search" />
					</li>
				{/each}
			</ul>
		{/if}
	{:else if !sections.length}
		<p class="shop-cat__status">No products are available right now.</p>
	{:else}
		<nav class="shop-cat__nav-wrap" aria-label="Product categories">
			<ul class="shop-cat__nav">
				{#each sections as section (section.category.slug)}
					<li>
						<button
							type="button"
							class="shop-cat__nav-btn"
							class:is-active={activeSlug === section.category.slug}
							onclick={() => scrollToCategory(section.category.slug)}
						>
							<span class="shop-cat__nav-num">{String(section.index).padStart(2, '0')}</span>
							<span class="shop-cat__nav-label">{section.category.name}</span>
						</button>
					</li>
				{/each}
			</ul>
		</nav>

		<div class="shop-cat__sections">
			{#each sections as section (section.category.slug)}
				<section
					id={sectionId(section.category.slug)}
					class="shop-cat__block"
					data-cat-slug={section.category.slug}
					aria-labelledby="shop-cat-label-{section.category.slug}"
				>
					<div class="shop-cat__block-rule">
						<p id="shop-cat-label-{section.category.slug}" class="shop-cat__block-eyebrow">
							<span class="shop-cat__block-num">{String(section.index).padStart(2, '0')}</span>
							<span aria-hidden="true"> — </span>
							<span class="shop-cat__block-name">{section.category.name}</span>
						</p>
					</div>

					<div class="shop-cat__panel">
						<header class="shop-cat__panel-head">
							<h2 class="shop-cat__panel-title">{section.title}</h2>
							{#if section.subtitle}
								<p class="shop-cat__panel-sub">{section.subtitle}</p>
							{/if}
						</header>

						<ul class="shop-cat__grid">
							{#each section.products as product (product.id)}
								<li class="shop-cat__cell">
									<ProductCard
										{product}
										listingSource={`Shop — ${section.category.name}`}
									/>
								</li>
							{/each}
						</ul>
					</div>
				</section>
			{/each}
		</div>
	{/if}
</section>

<style>
	.shop-cat {
		--mod-pt: 24px;
		--mod-pb: 72px;
		--mod-px: 28px;
		--mod-max-w: 1280px;
		max-width: var(--mod-max-w);
		margin: 0 auto;
		padding: var(--mod-pt) var(--mod-px) var(--mod-pb);
	}
	.shop-cat.is-v-compact {
		--mod-pt: 12px;
		--mod-pb: 48px;
	}
	.shop-cat.is-v-spacious {
		--mod-pt: 40px;
		--mod-pb: 96px;
	}
	.shop-cat.is-h-compact {
		--mod-max-w: 100%;
		--mod-px: 16px;
	}
	.shop-cat.is-h-spacious {
		--mod-max-w: 920px;
		--mod-px: 40px;
	}

	.shop-cat__page-head {
		margin-bottom: 20px;
	}
	.shop-cat__page-title {
		margin: 0;
		font-size: clamp(28px, 4vw, 36px);
		font-weight: 700;
		letter-spacing: -0.03em;
		color: var(--fg);
	}

	.shop-cat__intro {
		text-align: center;
		max-width: 40rem;
		margin: 0 auto 36px;
		padding-top: 8px;
	}
	.shop-cat__intro-eyebrow {
		margin: 0 0 14px;
		font-size: 11px;
		font-weight: 600;
		letter-spacing: 0.16em;
		text-transform: uppercase;
		color: var(--fg-muted);
	}
	.shop-cat__intro-title {
		margin: 0 0 16px;
		font-family: var(--font-heading, var(--font-sans));
		font-size: clamp(1.75rem, 4.2vw, 2.5rem);
		font-weight: 700;
		letter-spacing: -0.03em;
		line-height: 1.12;
		color: var(--fg-strong, var(--fg));
		text-wrap: balance;
	}
	.shop-cat__intro-sub {
		margin: 0 auto;
		max-width: 34rem;
		font-size: 15px;
		line-height: 1.6;
		color: var(--fg-muted);
		text-wrap: pretty;
	}

	.shop-cat__status {
		text-align: center;
		color: var(--fg-muted);
		padding: 48px 0;
	}
	.shop-cat__status--err {
		color: var(--accent);
	}

	.shop-cat__nav-wrap {
		position: sticky;
		top: calc(var(--header-height, 72px) + 8px);
		z-index: 20;
		margin-bottom: 28px;
		padding: 6px 0;
		background: color-mix(in srgb, var(--bg) 92%, transparent);
		backdrop-filter: blur(10px);
	}
	.shop-cat__nav {
		list-style: none;
		margin: 0;
		padding: 8px 10px;
		display: flex;
		flex-wrap: nowrap;
		gap: 6px;
		overflow-x: auto;
		scrollbar-width: none;
		border: 1px solid var(--border);
		border-radius: 14px;
		background: color-mix(in srgb, var(--fg) 4%, var(--bg));
	}
	.shop-cat__nav::-webkit-scrollbar {
		display: none;
	}
	.shop-cat__nav-btn {
		display: inline-flex;
		align-items: center;
		gap: 8px;
		padding: 10px 16px;
		border: 0;
		border-radius: 10px;
		background: transparent;
		color: var(--fg);
		font: inherit;
		font-size: 14px;
		font-weight: 500;
		white-space: nowrap;
		cursor: pointer;
		transition:
			background var(--dur-fast) var(--ease),
			color var(--dur-fast) var(--ease);
	}
	.shop-cat__nav-btn:hover {
		background: color-mix(in srgb, var(--fg) 6%, transparent);
	}
	.shop-cat__nav-btn.is-active {
		background: var(--bg);
		box-shadow: 0 1px 4px color-mix(in srgb, var(--fg) 10%, transparent);
	}
	.shop-cat__nav-num {
		font-size: 12px;
		font-weight: 500;
		color: color-mix(in srgb, var(--fg) 45%, transparent);
		font-variant-numeric: tabular-nums;
	}
	.shop-cat__nav-btn.is-active .shop-cat__nav-num {
		color: color-mix(in srgb, var(--fg) 58%, transparent);
	}

	.shop-cat__sections {
		display: flex;
		flex-direction: column;
		gap: 40px;
	}
	.shop-cat__block-rule {
		margin-bottom: 14px;
		padding-top: 4px;
		border-top: 1px solid var(--border);
	}
	.shop-cat__block-eyebrow {
		margin: 12px 0 0;
		display: flex;
		align-items: baseline;
		gap: 0;
		font-size: 11px;
		font-weight: 600;
		letter-spacing: 0.14em;
		text-transform: uppercase;
		color: color-mix(in srgb, var(--fg) 48%, transparent);
	}
	.shop-cat__block-num {
		font-variant-numeric: tabular-nums;
	}

	.shop-cat__panel {
		border: 1px solid var(--border);
		border-radius: 18px;
		background: var(--bg);
		padding: clamp(20px, 3vw, 32px);
		box-shadow: 0 8px 32px color-mix(in srgb, var(--fg) 4%, transparent);
	}
	.shop-cat__panel-head {
		margin-bottom: 24px;
		max-width: 62ch;
	}
	.shop-cat__panel-title {
		margin: 0 0 10px;
		font-family: var(--font-heading, var(--font-sans));
		font-size: clamp(22px, 2.8vw, 30px);
		font-weight: 700;
		letter-spacing: -0.03em;
		line-height: 1.15;
		color: var(--fg);
	}
	.shop-cat__panel-sub {
		margin: 0;
		font-size: 15px;
		line-height: 1.55;
		color: var(--fg-muted);
	}

	.shop-cat__grid {
		list-style: none;
		margin: 0;
		padding: 0;
		display: grid;
		grid-template-columns: repeat(4, minmax(0, 1fr));
		gap: 18px;
	}
	.shop-cat__cell {
		min-width: 0;
	}

	@media (max-width: 1100px) {
		.shop-cat__grid {
			grid-template-columns: repeat(3, minmax(0, 1fr));
		}
	}
	@media (max-width: 820px) {
		.shop-cat__grid {
			grid-template-columns: repeat(2, minmax(0, 1fr));
			gap: 14px;
		}
	}
	@media (max-width: 520px) {
		.shop-cat__grid {
			grid-template-columns: repeat(2, minmax(0, 1fr));
			gap: 12px;
		}
		.shop-cat__panel {
			padding: 16px 14px;
		}
		.shop-cat__panel-title {
			font-size: 20px;
		}
		.shop-cat__panel-sub {
			font-size: 13px;
		}
	}
</style>
