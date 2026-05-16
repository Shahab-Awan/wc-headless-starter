<script lang="ts">
	import { onMount, untrack } from 'svelte';
	import { page } from '$app/state';
	import { goto } from '$app/navigation';
	import ProductCard from './ProductCard.svelte';
	import { listProducts, listCategories, type StoreProduct, type StoreCategory, type ProductListParams } from '$lib/wc/products';
	import { config } from '$lib/config.svelte';
	import type { SpacingPreset } from '$lib/config.svelte';

	let { title = 'Shop', spacing_v = 'normal', spacing_h = 'normal', center_header = false, category }: {
		title?: string;
		spacing_v?: SpacingPreset;
		spacing_h?: SpacingPreset;
		center_header?: boolean;
		category?: string;
	} = $props();

	// Shop-wide column bounds. Admin sets min/max; the grid auto-fits
	// columns between them via container queries. Clamped defensively
	// in case config values slipped past the PHP clamp.
	const colsMin = $derived(Math.max(1, Math.min(8, config.data.shop?.cols_min ?? 2)));
	const colsMax = $derived(Math.max(colsMin, Math.min(8, config.data.shop?.cols_max ?? 4)));

	const PER_PAGE = 12;

	let products = $state<StoreProduct[]>([]);
	let loading = $state(true);
	let error = $state<string | null>(null);

	let searchTerm = $state('');
	let searchInput = $state('');
	let orderby = $state<'date' | 'price' | 'rating' | 'popularity' | 'title'>('date');
	let order = $state<'asc' | 'desc'>('desc');
	let pageNum = $state(1);
	let requestId = 0;

	// User-picked category (only active on the /shop page; when the
	// `category` prop is passed in module mode, the filter is locked
	// to that and the dropdown is hidden).
	let categorySlug = $state('');
	let categories = $state<StoreCategory[]>([]);

	// On-sale filter — independent of category so the two can combine
	// (e.g. "on-sale items in Metabolic Health"). URL truthy check is
	// strict: only the literal "1" enables. Anything else (including
	// "true", "yes", numeric 99, XSS payloads) is treated as off so we
	// never silently filter on a malformed value.
	let onSale = $state(false);

	function syncFromUrl() {
		const u = page.url.searchParams;
		searchTerm = u.get('search') ?? '';
		// Preserve in-flight typed input if a debounce is pending — a URL
		// change driven by a different filter (e.g. the sale toggle) MUST
		// NOT clobber what the user just typed, otherwise the search gets
		// silently dropped when the timer fires 350ms later.
		if (!searchDebounceTimer) searchInput = searchTerm;
		const sortRaw = u.get('sort') ?? 'date-desc';
		const [ob, od] = sortRaw.split('-');
		orderby = (['date', 'price', 'rating', 'popularity', 'title'].includes(ob) ? ob : 'date') as any;
		order = od === 'asc' ? 'asc' : 'desc';
		pageNum = Math.max(1, Number(u.get('page')) || 1);
		categorySlug = u.get('cat') ?? '';
		onSale = u.get('sale') === '1';
	}

	function pushUrl(next: Partial<{ search: string; sort: string; page: number; cat: string; sale: boolean }>) {
		const u = new URL(page.url);
		if (next.search !== undefined) {
			if (next.search) u.searchParams.set('search', next.search);
			else u.searchParams.delete('search');
		}
		if (next.sort !== undefined) {
			if (next.sort) u.searchParams.set('sort', next.sort);
			else u.searchParams.delete('sort');
		}
		if (next.cat !== undefined) {
			if (next.cat) u.searchParams.set('cat', next.cat);
			else u.searchParams.delete('cat');
		}
		if (next.sale !== undefined) {
			if (next.sale) u.searchParams.set('sale', '1');
			else u.searchParams.delete('sale');
		}
		if (next.page !== undefined) {
			if (next.page > 1) u.searchParams.set('page', String(next.page));
			else u.searchParams.delete('page');
		}
		goto(u.pathname + (u.search ? u.search : ''), { replaceState: false, keepFocus: true, noScroll: true });
	}

	async function fetchPage() {
		const thisReq = ++requestId;
		loading = true;
		error = null;
		try {
			const params: ProductListParams = {
				per_page: PER_PAGE,
				page: pageNum,
				orderby,
				order,
			};
			if (searchTerm) params.search = searchTerm;
			// `category` prop (module mode) wins. Otherwise use the
			// user-selected category from the dropdown/URL.
			const activeCat = category ?? categorySlug;
			if (activeCat) params.category = activeCat;
			if (onSale) params.on_sale = true;
			const result = await listProducts(params);
			if (thisReq !== requestId) return;
			products = result;
			if (result.length > 0) {
				import('$lib/analytics').then(({ trackViewItemList }) => trackViewItemList('Shop', result));
			}
			if (searchTerm && typeof window !== 'undefined' && window.dataLayer) {
				window.dataLayer.push({ event: 'search', search_term: searchTerm });
			}
		} catch (e) {
			if (thisReq !== requestId) return;
			error = e instanceof Error ? e.message : String(e);
		} finally {
			if (thisReq === requestId) loading = false;
		}
	}

	onMount(() => {
		syncFromUrl();
		fetchPage();
		// Load categories for the filter dropdown. Only needed when
		// the filter is actually rendered (no `category` prop bound).
		if (!category) {
			listCategories()
				.then((cats) => {
					// Sort by name, skip hidden/empty categories. Also drop
					// the "Uncategorized" default bucket (WooCommerce adds
					// it automatically, not useful in the filter UI).
					categories = cats
						.filter((c) => c.count > 0 && c.slug !== 'uncategorized')
						.sort((a, b) => a.name.localeCompare(b.name));
				})
				.catch(() => {
					// Non-critical; dropdown just shows "All categories".
				});
		}
	});

	$effect(() => {
		const href = page.url.href;
		untrack(() => {
			syncFromUrl();
			fetchPage();
		});
		void href;
	});

	let searchDebounceTimer: ReturnType<typeof setTimeout> | null = null;
	function onSearchInput() {
		if (searchDebounceTimer) clearTimeout(searchDebounceTimer);
		// Capture the value at schedule time. If syncFromUrl runs between
		// schedule and fire, searchInput could be reset by the bind:value
		// being out-of-sync with what the user actually typed.
		const captured = searchInput;
		searchDebounceTimer = setTimeout(() => {
			searchDebounceTimer = null;
			pushUrl({ search: captured, page: 1 });
		}, 350);
	}

	function onSortChange(e: Event) {
		pushUrl({ sort: (e.target as HTMLSelectElement).value, page: 1 });
	}

	function onCategoryChange(e: Event) {
		pushUrl({ cat: (e.target as HTMLSelectElement).value, page: 1 });
	}

	function onSaleToggle(e: Event) {
		pushUrl({ sale: (e.target as HTMLInputElement).checked, page: 1 });
	}

	function goToPage(n: number) {
		if (n < 1) return;
		pushUrl({ page: n });
	}

	const sortValue = $derived(`${orderby}-${order}`);
	const hasPrevPage = $derived(pageNum > 1);
	const hasNextPage = $derived(!loading && products.length === PER_PAGE);
</script>

<section class="shop-grid" class:is-v-compact={spacing_v === 'compact'} class:is-v-spacious={spacing_v === 'spacious'} class:is-h-compact={spacing_h === 'compact'} class:is-h-spacious={spacing_h === 'spacious'}>
	<header class="shop-grid__head" class:is-centered={center_header}>
		{#if title}
			<p class="shop-grid__label wchs-section-heading">{title}</p>
		{/if}
		<div class="shop-grid__controls">
			{#if !category}
				<select class="shop-grid__filter" value={categorySlug} onchange={onCategoryChange} aria-label="Filter by category">
					<option value="">All categories</option>
					{#each categories as c (c.slug)}
						<option value={c.slug}>{c.name}</option>
					{/each}
				</select>
			{/if}
			<label class="shop-grid__sale" data-testid="shop-sale-toggle">
				<input type="checkbox" checked={onSale} onchange={onSaleToggle} aria-label="Show only on-sale products" />
				<span>On sale</span>
			</label>
			<div class="shop-grid__search">
				<input
					type="search"
					placeholder="Search products…"
					bind:value={searchInput}
					oninput={onSearchInput}
					aria-label="Search products"
				/>
			</div>
			<select class="shop-grid__sort" value={sortValue} onchange={onSortChange} aria-label="Sort products">
				<option value="date-desc">Newest first</option>
				<option value="date-asc">Oldest first</option>
				<option value="price-asc">Price ascending</option>
				<option value="price-desc">Price descending</option>
				<option value="rating-desc">Top rated</option>
				<option value="popularity-desc">Most popular</option>
				<option value="title-asc">A → Z</option>
			</select>
		</div>
	</header>

	{#if error}
		<p class="shop-grid__msg shop-grid__msg--err">Failed to load products: {error}</p>
	{:else if loading && products.length === 0}
		<ul class="shop-grid__list" style="--cols-min: {colsMin}; --cols-max: {colsMax};">
			{#each Array(PER_PAGE) as _, i (i)}
				<li><div class="shop-grid__skeleton"></div></li>
			{/each}
		</ul>
	{:else if products.length === 0}
		<p class="shop-grid__msg">
			{#if searchTerm}
				No products match <strong>"{searchTerm}"</strong>.
			{:else}
				No products found.
			{/if}
		</p>
	{:else}
		<ul class="shop-grid__list" class:is-loading={loading} style="--cols-min: {colsMin}; --cols-max: {colsMax};">
			{#each products.filter(p => config.data.product_card?.show_oos_cards !== false || p.is_in_stock !== false) as p (p.id)}
				<li><ProductCard product={p} listingSource="Shop" /></li>
			{/each}
		</ul>

		<nav class="shop-grid__pager" aria-label="Pagination">
			<button type="button" class="shop-grid__pager-btn" disabled={!hasPrevPage} onclick={() => goToPage(pageNum - 1)}>← Prev</button>
			<span class="shop-grid__pager-num">Page {pageNum}</span>
			<button type="button" class="shop-grid__pager-btn" disabled={!hasNextPage} onclick={() => goToPage(pageNum + 1)}>Next →</button>
		</nav>
	{/if}
</section>

<style>
	.shop-grid {
		--mod-pt: 32px;
		--mod-pb: 40px;
		--mod-px: 28px;
		--mod-max-w: 960px;
		max-width: var(--mod-max-w);
		margin: 0 auto;
		padding: var(--mod-pt) var(--mod-px) var(--mod-pb);
	}
	.shop-grid.is-v-compact  { --mod-pt: 12px; --mod-pb: 12px; }
	.shop-grid.is-v-spacious { --mod-pt: 56px; --mod-pb: 64px; }
	.shop-grid.is-h-compact  { --mod-max-w: 100%; --mod-px: 12px; }
	.shop-grid.is-h-spacious { --mod-max-w: 760px; --mod-px: 40px; }
	.shop-grid__head {
		display: flex;
		flex-direction: column;
		gap: 16px;
		margin-bottom: 28px;
	}
	.shop-grid__head.is-centered {
		align-items: center;
		text-align: center;
	}
	.shop-grid__label {
		line-height: 1.2;
		margin-bottom: 4px;
	}

	/* Controls row: [Filter | Search | Sort]. Search grows; the two
	   dropdowns stay at fixed 180px min-width. Stacks under 640px since
	   three elements don't shrink gracefully at tablet widths. */
	.shop-grid__controls {
		display: flex;
		flex-direction: column;
		gap: 10px;
		width: 100%;
	}
	@media (min-width: 640px) {
		.shop-grid__controls {
			flex-direction: row;
			align-items: stretch;
			gap: 12px;
		}
	}
	.shop-grid__search {
		flex: 1 1 auto;
		min-width: 0;
	}
	/* All three controls share identical metrics so the row reads as
	   a unit — same height, padding, border, font. No external labels. */
	.shop-grid__search input,
	.shop-grid__filter,
	.shop-grid__sort {
		width: 100%;
		height: 40px;
		padding: 0 14px;
		background: var(--bg);
		color: var(--fg);
		border: 1px solid var(--border);
		border-radius: 14px;
		font-family: var(--font-sans);
		font-size: 13px;
		letter-spacing: -0.16px;
		transition: border-color var(--dur-fast) var(--ease);
	}
	.shop-grid__search input:focus,
	.shop-grid__filter:focus,
	.shop-grid__sort:focus {
		outline: none;
		border-color: var(--fg);
	}
	.shop-grid__search input::placeholder {
		color: var(--fg-muted);
	}
	.shop-grid__filter,
	.shop-grid__sort {
		flex: 0 0 auto;
		width: auto;
		min-width: 180px;
		cursor: pointer;
	}
	/* On-sale checkbox — matches the height of the dropdowns so the
	   controls row reads as one unit. Subtle pill border so it doesn't
	   visually compete with the dropdowns. */
	.shop-grid__sale {
		display: inline-flex;
		align-items: center;
		gap: 8px;
		flex: 0 0 auto;
		height: 40px;
		padding: 0 14px;
		background: var(--bg);
		color: var(--fg);
		border: 1px solid var(--border);
		border-radius: 14px;
		font-family: var(--font-sans);
		font-size: 13px;
		letter-spacing: -0.16px;
		cursor: pointer;
		user-select: none;
	}
	.shop-grid__sale input[type='checkbox'] {
		width: 14px;
		height: 14px;
		margin: 0;
		accent-color: var(--accent, var(--fg));
		cursor: pointer;
	}
	.shop-grid__sale:hover {
		border-color: var(--fg);
	}
	/* Flex grid (not CSS grid) so the last underfilled row can center
	   its orphans via justify-content. Each card gets a calculated
	   flex-basis so columns are exact based on --cols. Container
	   queries step --cols up as the container widens. Each step uses
	   clamp(cols-min, N, cols-max) so the value stays within bounds
	   regardless of the admin's chosen min/max. */
	/* container-type must live on an ANCESTOR — an element cannot query
	   itself. `.shop-grid` is the grid's parent, so that's where
	   container-type goes. @container queries inside this wrapper
	   then size against `.shop-grid`'s own inline width. */
	.shop-grid {
		container-type: inline-size;
	}
	.shop-grid__list {
		--gap: 20px;
		--cols: var(--cols-min, 2);
		display: flex;
		flex-wrap: wrap;
		justify-content: center;
		gap: var(--gap);
		list-style: none;
		padding: 0;
		margin: 0 0 32px;
		transition: opacity var(--dur-fast) var(--ease);
	}
	@container (min-width: 400px)  { .shop-grid__list { --cols: clamp(var(--cols-min, 2), 2, var(--cols-max, 4)); } }
	@container (min-width: 600px)  { .shop-grid__list { --cols: clamp(var(--cols-min, 2), 3, var(--cols-max, 4)); } }
	@container (min-width: 800px)  { .shop-grid__list { --cols: clamp(var(--cols-min, 2), 3, var(--cols-max, 4)); } }
	@container (min-width: 1000px) { .shop-grid__list { --cols: clamp(var(--cols-min, 2), 4, var(--cols-max, 4)); } }
	@container (min-width: 1200px) { .shop-grid__list { --cols: clamp(var(--cols-min, 2), 5, var(--cols-max, 4)); } }
	@container (min-width: 1400px) { .shop-grid__list { --cols: clamp(var(--cols-min, 2), 6, var(--cols-max, 4)); } }
	@container (min-width: 1600px) { .shop-grid__list { --cols: clamp(var(--cols-min, 2), 7, var(--cols-max, 4)); } }
	.shop-grid__list > li {
		flex: 0 0 calc((100% - (var(--cols) - 1) * var(--gap)) / var(--cols));
		max-width: calc((100% - (var(--cols) - 1) * var(--gap)) / var(--cols));
		min-width: 0;
	}
	.shop-grid__list.is-loading {
		opacity: 0.55;
	}
	.shop-grid__skeleton {
		aspect-ratio: 1 / 1;
		background: var(--bg-muted);
		border: 1px solid var(--border);
		border-radius: 14px;
		position: relative;
		overflow: hidden;
	}
	.shop-grid__skeleton::after {
		content: '';
		position: absolute;
		inset: 0;
		background: linear-gradient(90deg, transparent, color-mix(in oklab, var(--fg) 6%, transparent), transparent);
		animation: shop-shimmer 1.4s infinite;
	}
	@keyframes shop-shimmer {
		from { transform: translateX(-100%); }
		to { transform: translateX(100%); }
	}
	.shop-grid__msg {
		padding: 48px 24px;
		text-align: center;
		color: var(--fg-muted);
		font-size: 14px;
	}
	.shop-grid__msg--err {
		color: var(--danger, #dc2626);
	}
	.shop-grid__pager {
		display: flex;
		align-items: center;
		justify-content: center;
		gap: 20px;
	}
	.shop-grid__pager-btn {
		padding: 10px 20px;
		background: transparent;
		color: var(--fg);
		border: 1px solid var(--border);
		border-radius: 14px;
		font: inherit;
		font-size: 11px;
		font-weight: 500;
		text-transform: uppercase;
		letter-spacing: 0.1em;
		cursor: pointer;
		transition: background var(--dur-fast) var(--ease), border-color var(--dur-fast) var(--ease), color var(--dur-fast) var(--ease), opacity var(--dur-fast) var(--ease);
	}
	.shop-grid__pager-btn:hover:not(:disabled) {
		background: var(--fg);
		color: var(--bg);
		border-color: var(--fg);
	}
	.shop-grid__pager-btn:disabled {
		opacity: 0.28;
		cursor: default;
	}
	.shop-grid__pager-num {
		font-size: 11px;
		font-weight: 450;
		text-transform: uppercase;
		letter-spacing: 0.1em;
		color: var(--fg-muted);
		font-variant-numeric: tabular-nums;
	}
</style>
