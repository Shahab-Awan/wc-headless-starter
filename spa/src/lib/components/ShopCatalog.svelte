<script lang="ts">
	import ProductCard from './ProductCard.svelte';
	import {
		listCategories,
		listProducts,
		type StoreCategory,
		type StoreProduct,
		type ProductListParams,
	} from '$lib/wc/products';
	import { canPurchase } from '$lib/wc/stock';
	import type { SpacingPreset } from '$lib/config.svelte';

	type ShopSection = {
		category: StoreCategory;
		index: number;
		products: StoreProduct[];
		title: string;
		subtitle: string;
	};

	type CatalogLayout = 'sections' | 'filter-grid';
	type SortValue = 'popularity-desc' | 'date-desc' | 'price-asc' | 'price-desc';

	const MOST_POPULAR_MATCHERS = [
		{ slug: 'bpc-157', name: 'bpc-157' },
		{ slug: 'retatrutide', name: 'retatrutide' },
		{ slug: 'ghk-cu', name: 'ghk-cu' },
	] as const;

	let {
		spacing_v = 'normal',
		spacing_h = 'normal',
		searchQuery = '',
		layout = 'sections',
		showPageHead = true,
		pageTitle = 'Shop',
		pageSubtitle = '',
		showIntro = false,
		introEyebrow = 'Research catalog',
		introHeadline = 'Research-grade peptides, organized by category',
		introSubheadline = 'Browse our most requested compounds by research area. Select a category below to jump to products.',
	}: {
		spacing_v?: SpacingPreset;
		spacing_h?: SpacingPreset;
		searchQuery?: string;
		layout?: CatalogLayout;
		showPageHead?: boolean;
		pageTitle?: string;
		pageSubtitle?: string;
		showIntro?: boolean;
		introEyebrow?: string;
		introHeadline?: string;
		introSubheadline?: string;
	} = $props();

	let loading = $state(true);
	let error = $state<string | null>(null);
	let sections = $state<ShopSection[]>([]);
	let allProducts = $state<StoreProduct[]>([]);
	let filterCategories = $state<StoreCategory[]>([]);
	let searchResults = $state<StoreProduct[]>([]);
	let activeSlug = $state('');
	let categoryFilter = $state('');
	let sortBy = $state<SortValue>('popularity-desc');
	let inStockOnly = $state(true);
	let filterSearch = $state('');
	let filterSearchInput = $state('');

	const isFilterGrid = $derived(layout === 'filter-grid');
	const trimmedSearch = $derived(searchQuery.trim());
	const isSearchMode = $derived(!isFilterGrid && trimmedSearch.length >= 2);

	const isDefaultDiscoveryView = $derived(
		isFilterGrid && !categoryFilter && !filterSearch.trim()
	);

	const popularProducts = $derived.by(() => pickPopularProducts(allProducts));

	const baseFiltered = $derived.by(() => {
		if (!isFilterGrid) return allProducts;
		let list = allProducts;
		if (categoryFilter) {
			list = list.filter((p) => p.categories.some((c) => c.slug === categoryFilter));
		}
		if (inStockOnly) {
			list = list.filter((p) => canPurchase(p));
		}
		const q = filterSearch.trim().toLowerCase();
		if (q) {
			list = list.filter((p) => {
				const hay = `${p.name} ${p.sku} ${p.short_description}`.toLowerCase();
				return hay.includes(q);
			});
		}
		return list;
	});

	const gridProducts = $derived.by(() => {
		if (!isDefaultDiscoveryView) return baseFiltered;
		const popularIds = new Set(popularProducts.map((p) => p.id));
		return baseFiltered.filter((p) => !popularIds.has(p.id));
	});

	const displayCount = $derived(baseFiltered.length);
	const popularRowProducts = $derived.by(() => {
		if (!inStockOnly) return popularProducts;
		return popularProducts.filter((p) => canPurchase(p));
	});
	const showPopularRow = $derived(
		isFilterGrid && isDefaultDiscoveryView && popularRowProducts.length > 0
	);

	const HEADER_OFFSET = 120;

	function normalizeKey(value: string): string {
		return value.toLowerCase().replace(/[^a-z0-9]+/g, '');
	}

	function pickPopularProducts(products: StoreProduct[]): StoreProduct[] {
		const out: StoreProduct[] = [];
		const used = new Set<number>();
		for (const matcher of MOST_POPULAR_MATCHERS) {
			const nameKey = normalizeKey(matcher.name);
			const hit = products.find((p) => {
				if (used.has(p.id)) return false;
				const slug = p.slug.toLowerCase();
				if (slug === matcher.slug || slug.includes(matcher.slug)) return true;
				return normalizeKey(p.name).includes(nameKey);
			});
			if (hit) {
				out.push(hit);
				used.add(hit.id);
			}
		}
		return out;
	}

	function parseSort(value: SortValue): Pick<ProductListParams, 'orderby' | 'order'> {
		switch (value) {
			case 'date-desc':
				return { orderby: 'date', order: 'desc' };
			case 'price-asc':
				return { orderby: 'price', order: 'asc' };
			case 'price-desc':
				return { orderby: 'price', order: 'desc' };
			default:
				return { orderby: 'popularity', order: 'desc' };
		}
	}

	let searchDebounceTimer: ReturnType<typeof setTimeout> | null = null;

	function onFilterSearchInput() {
		if (searchDebounceTimer) clearTimeout(searchDebounceTimer);
		const captured = filterSearchInput;
		searchDebounceTimer = setTimeout(() => {
			searchDebounceTimer = null;
			filterSearch = captured;
		}, 250);
	}

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
		if (!isFilterGrid) return;
		const q = searchQuery.trim();
		if (q) {
			filterSearch = q;
			filterSearchInput = q;
		}
	});

	$effect(() => {
		const q = trimmedSearch;
		const searchMode = q.length >= 2;
		const filterGrid = layout === 'filter-grid';
		const sort = sortBy;
		let observers: IntersectionObserver[] = [];
		let cancelled = false;

		(async () => {
			loading = true;
			error = null;
			searchResults = [];
			sections = [];
			allProducts = [];
			filterCategories = [];
			try {
				if (searchMode && !filterGrid) {
					searchResults = await listProducts({
						search: q,
						per_page: 48,
						orderby: 'title',
						order: 'asc',
					});
					return;
				}

				if (filterGrid) {
					const { orderby, order } = parseSort(sort);
					const [products, cats] = await Promise.all([
						listProducts({ per_page: 100, orderby, order }),
						listCategories({ parent: 0 }),
					]);
					if (cancelled) return;
					allProducts = products;
					filterCategories = cats.filter(isTopLevelCategory).sort((a, b) => a.id - b.id);
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
	class:is-filter-grid={isFilterGrid}
	class:is-v-compact={spacing_v === 'compact'}
	class:is-v-spacious={spacing_v === 'spacious'}
	class:is-h-compact={spacing_h === 'compact'}
	class:is-h-spacious={spacing_h === 'spacious'}
	aria-label="Shop catalog"
>
	{#if showPageHead}
		<header class="shop-cat__page-head" class:is-centered={isFilterGrid}>
			<h1 class="shop-cat__page-title" class:is-upper={isFilterGrid}>
				{#if isSearchMode}
					Search: {trimmedSearch}
				{:else}
					{pageTitle}
				{/if}
			</h1>
			{#if isFilterGrid && pageSubtitle && !isSearchMode}
				<p class="shop-cat__page-sub">{pageSubtitle}</p>
			{/if}
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
	{:else if isFilterGrid}
		{#if !allProducts.length}
			<p class="shop-cat__status">No products are available right now.</p>
		{:else}
			<div class="shop-cat__toolbar" role="toolbar" aria-label="Product filters">
				<span class="shop-cat__count" aria-live="polite">
					<svg class="shop-cat__count-icon" viewBox="0 0 20 20" width="16" height="16" aria-hidden="true">
						<path
							fill="none"
							stroke="currentColor"
							stroke-width="1.5"
							d="M4 6.5 10 3.5l6 3v7l-6 3-6-3v-7Z"
						/>
						<path fill="none" stroke="currentColor" stroke-width="1.5" d="M10 10.5v7M4 6.5l6 3 6-3" />
					</svg>
					{displayCount} {displayCount === 1 ? 'Product' : 'Products'}
				</span>

				<label class="shop-cat__search-wrap">
					<svg class="shop-cat__search-icon" viewBox="0 0 20 20" width="16" height="16" aria-hidden="true">
						<path
							fill="none"
							stroke="currentColor"
							stroke-width="1.6"
							d="M8.75 14.5a5.75 5.75 0 1 1 0-11.5 5.75 5.75 0 0 1 0 11.5Zm4.1-1.05 3.65 3.65"
						/>
					</svg>
					<input
						type="search"
						class="shop-cat__search-input"
						placeholder="Search products…"
						bind:value={filterSearchInput}
						oninput={onFilterSearchInput}
						aria-label="Search products"
					/>
				</label>

				<select
					class="shop-cat__select"
					bind:value={categoryFilter}
					aria-label="Filter by category"
				>
					<option value="">All categories</option>
					{#each filterCategories as category (category.slug)}
						<option value={category.slug}>{category.name}</option>
					{/each}
				</select>

				<select class="shop-cat__select" bind:value={sortBy} aria-label="Sort products">
					<option value="popularity-desc">Bestselling</option>
					<option value="date-desc">Newest</option>
					<option value="price-asc">Price: Low to High</option>
					<option value="price-desc">Price: High to Low</option>
				</select>

				<label class="shop-cat__stock-toggle">
					<span class="shop-cat__stock-label">In Stock Only</span>
					<input
						type="checkbox"
						class="shop-cat__stock-input"
						bind:checked={inStockOnly}
						aria-label="In stock only"
					/>
					<span class="shop-cat__stock-switch" aria-hidden="true"></span>
				</label>
			</div>

			{#if showPopularRow && popularRowProducts.length}
				<section class="shop-cat__bestsellers" aria-labelledby="shop-bestsellers-label">
					<h2 id="shop-bestsellers-label" class="shop-cat__section-head">Bestsellers</h2>
					<ul class="shop-cat__grid shop-cat__grid--bestsellers">
						{#each popularRowProducts as product (product.id)}
							<li class="shop-cat__cell">
								<ProductCard
									{product}
									highlightBadge="Most Popular"
									listingSource="Shop — Bestsellers"
								/>
							</li>
						{/each}
					</ul>
				</section>
			{/if}

			{#if !gridProducts.length && !(showPopularRow && popularRowProducts.length)}
				<p class="shop-cat__status">No products match your filters.</p>
			{:else if gridProducts.length}
				<section
					class="shop-cat__collection"
					aria-labelledby={showPopularRow ? 'shop-collection-label' : undefined}
				>
					{#if showPopularRow}
						<h2 id="shop-collection-label" class="shop-cat__section-head">Collection</h2>
					{/if}
					<ul class="shop-cat__grid">
						{#each gridProducts as product (product.id)}
							<li class="shop-cat__cell">
								<ProductCard
									{product}
									listingSource={categoryFilter
										? `Shop — ${filterCategories.find((c) => c.slug === categoryFilter)?.name ?? 'Category'}`
										: 'Shop — Collection'}
								/>
							</li>
						{/each}
					</ul>
				</section>
			{/if}
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
	.shop-cat__page-head.is-centered {
		text-align: center;
		margin-bottom: 28px;
	}
	.shop-cat__page-title {
		margin: 0;
		font-size: clamp(28px, 4vw, 36px);
		font-weight: 700;
		letter-spacing: -0.03em;
		color: var(--fg);
	}
	.shop-cat__page-title.is-upper {
		font-size: clamp(26px, 3.6vw, 34px);
		letter-spacing: 0.08em;
		text-transform: uppercase;
	}
	.shop-cat__page-sub {
		margin: 12px 0 0;
		font-size: 15px;
		line-height: 1.55;
		color: var(--fg-muted);
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

	.shop-cat__toolbar {
		display: flex;
		flex-wrap: wrap;
		align-items: center;
		gap: 10px;
		margin-bottom: 28px;
		padding: 10px 12px;
		border: 1px solid var(--border);
		border-radius: 14px;
		background: color-mix(in srgb, var(--fg) 3%, var(--bg));
	}
	.shop-cat__count {
		display: inline-flex;
		align-items: center;
		gap: 8px;
		flex: 0 0 auto;
		height: 40px;
		padding: 0 12px;
		border: 1px solid var(--border);
		border-radius: 10px;
		background: var(--bg);
		font-size: 13px;
		font-weight: 500;
		color: var(--fg-muted);
		white-space: nowrap;
	}
	.shop-cat__count-icon {
		color: color-mix(in srgb, var(--fg) 55%, transparent);
	}
	.shop-cat__search-wrap {
		position: relative;
		flex: 1 1 200px;
		min-width: 0;
	}
	.shop-cat__search-icon {
		position: absolute;
		left: 12px;
		top: 50%;
		transform: translateY(-50%);
		color: var(--fg-muted);
		pointer-events: none;
	}
	.shop-cat__search-input,
	.shop-cat__select {
		width: 100%;
		height: 40px;
		padding: 0 14px;
		border: 1px solid var(--border);
		border-radius: 10px;
		background: var(--bg);
		color: var(--fg);
		font: inherit;
		font-size: 13px;
		transition: border-color var(--dur-fast) var(--ease);
	}
	.shop-cat__search-input {
		padding-left: 36px;
	}
	.shop-cat__search-input:focus,
	.shop-cat__select:focus {
		outline: none;
		border-color: color-mix(in srgb, var(--fg) 40%, var(--border));
	}
	.shop-cat__search-input::placeholder {
		color: var(--fg-muted);
	}
	.shop-cat__select {
		flex: 0 1 auto;
		min-width: 140px;
		width: auto;
		cursor: pointer;
		padding-right: 32px;
		appearance: none;
		background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12' fill='none'%3E%3Cpath d='M2.5 4.5 6 8l3.5-3.5' stroke='%23666' stroke-width='1.4' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
		background-repeat: no-repeat;
		background-position: right 12px center;
	}
	.shop-cat__stock-toggle {
		display: inline-flex;
		align-items: center;
		gap: 10px;
		flex: 0 0 auto;
		height: 40px;
		padding: 0 4px 0 12px;
		cursor: pointer;
		user-select: none;
		white-space: nowrap;
	}
	.shop-cat__stock-label {
		font-size: 13px;
		font-weight: 500;
		color: var(--fg);
	}
	.shop-cat__stock-input {
		position: absolute;
		opacity: 0;
		width: 0;
		height: 0;
		pointer-events: none;
	}
	.shop-cat__stock-switch {
		position: relative;
		width: 42px;
		height: 24px;
		border-radius: 999px;
		background: color-mix(in srgb, var(--fg) 18%, var(--bg));
		transition: background var(--dur-fast) var(--ease);
	}
	.shop-cat__stock-switch::after {
		content: '';
		position: absolute;
		top: 3px;
		left: 3px;
		width: 18px;
		height: 18px;
		border-radius: 50%;
		background: var(--bg);
		box-shadow: 0 1px 3px color-mix(in srgb, var(--fg) 20%, transparent);
		transition: transform var(--dur-fast) var(--ease);
	}
	.shop-cat__stock-input:checked + .shop-cat__stock-switch {
		background: var(--accent);
	}
	.shop-cat__stock-input:checked + .shop-cat__stock-switch::after {
		transform: translateX(18px);
	}
	.shop-cat__stock-input:focus-visible + .shop-cat__stock-switch {
		outline: 2px solid var(--accent);
		outline-offset: 2px;
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

	.shop-cat__bestsellers {
		margin-bottom: 40px;
	}
	.shop-cat__collection {
		margin-top: 4px;
	}
	.shop-cat__section-head {
		margin: 0 0 20px;
		font-family: var(--font-heading, var(--font-sans));
		font-size: clamp(1.35rem, 2.4vw, 1.75rem);
		font-weight: 700;
		letter-spacing: -0.02em;
		line-height: 1.15;
		color: var(--fg-strong, var(--fg));
	}
	.shop-cat__grid--bestsellers {
		grid-template-columns: repeat(3, minmax(0, 1fr));
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
		align-items: start;
		column-gap: 18px;
		row-gap: 24px;
	}
	.shop-cat__cell {
		min-width: 0;
		height: auto;
	}

	@media (max-width: 1100px) {
		.shop-cat__grid {
			grid-template-columns: repeat(3, minmax(0, 1fr));
		}
	}
	@media (max-width: 820px) {
		.shop-cat__toolbar {
			gap: 8px;
		}
		.shop-cat__count,
		.shop-cat__search-wrap,
		.shop-cat__select,
		.shop-cat__stock-toggle {
			flex: 1 1 calc(50% - 8px);
			min-width: calc(50% - 8px);
		}
		.shop-cat__search-wrap {
			flex: 1 1 100%;
			min-width: 100%;
		}
		.shop-cat__stock-toggle {
			justify-content: space-between;
			padding-right: 8px;
		}
		.shop-cat__grid,
		.shop-cat__grid--bestsellers {
			grid-template-columns: repeat(2, minmax(0, 1fr));
			column-gap: 14px;
			row-gap: 20px;
		}

		.shop-cat__grid--bestsellers .shop-cat__cell {
			overflow: visible;
			padding-top: 4px;
		}
	}
	@media (max-width: 520px) {
		.shop-cat__grid {
			grid-template-columns: repeat(2, minmax(0, 1fr));
			column-gap: 12px;
			row-gap: 18px;
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
