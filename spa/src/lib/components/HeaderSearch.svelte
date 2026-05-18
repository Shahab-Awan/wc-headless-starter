<script lang="ts">
	import { onMount } from 'svelte';
	import { browser } from '$app/environment';
	import { goto } from '$app/navigation';
	import { icons } from '$lib/icons';
	import { listProducts, listCategories, type StoreProduct, type StoreCategory } from '$lib/wc/products';

	let open = $state(false);
	let query = $state('');
	let loading = $state(false);
	let products = $state<StoreProduct[]>([]);
	let categories = $state<StoreCategory[]>([]);

	let rootEl: HTMLDivElement | undefined = $state();
	let inputEl: HTMLInputElement | undefined = $state();
	let debounceTimer: ReturnType<typeof setTimeout> | undefined;

	async function runSearch(term: string) {
		const q = term.trim();
		if (q.length < 2) {
			products = [];
			categories = [];
			loading = false;
			return;
		}
		loading = true;
		const lower = q.toLowerCase();
		try {
			const [prods, cats] = await Promise.all([
				listProducts({ search: q, per_page: 6 }),
				listCategories()
			]);
			products = prods;
			categories = cats
				.filter((c) => c.count > 0 && (c.name.toLowerCase().includes(lower) || c.slug.includes(lower)))
				.slice(0, 4);
		} catch {
			products = [];
			categories = [];
		} finally {
			loading = false;
		}
	}

	function scheduleSearch(term: string) {
		clearTimeout(debounceTimer);
		debounceTimer = setTimeout(() => runSearch(term), 280);
	}

	function openPanel() {
		open = true;
		requestAnimationFrame(() => inputEl?.focus());
	}

	function closePanel() {
		open = false;
	}

	function onQueryInput() {
		if (query.trim().length < 2) {
			products = [];
			categories = [];
			loading = false;
			return;
		}
		loading = true;
		scheduleSearch(query);
	}

	function productHref(slug: string) {
		return `/product/${slug}`;
	}

	function categoryHref(slug: string) {
		return `/shop#shop-cat-${slug}`;
	}

	function viewAllHref() {
		return `/shop?search=${encodeURIComponent(query.trim())}`;
	}

	function navigate(href: string) {
		closePanel();
		query = '';
		products = [];
		categories = [];
		goto(href);
	}

	function onDocPointerDown(e: PointerEvent) {
		if (!open || !rootEl) return;
		if (!rootEl.contains(e.target as Node)) closePanel();
	}

	function onDocKeydown(e: KeyboardEvent) {
		if (e.key === 'Escape' && open) closePanel();
	}

	$effect(() => {
		if (!browser) return;
		document.addEventListener('pointerdown', onDocPointerDown);
		document.addEventListener('keydown', onDocKeydown);
		return () => {
			document.removeEventListener('pointerdown', onDocPointerDown);
			document.removeEventListener('keydown', onDocKeydown);
		};
	});

	onMount(() => {
		const params = new URLSearchParams(window.location.search);
		if (params.has('open_search')) {
			openPanel();
		}
	});
</script>

<div class="site-header__search-wrap" bind:this={rootEl}>
	<button
		type="button"
		class="site-header__icon-link site-header__search-trigger"
		aria-label="Search products"
		aria-expanded={open}
		aria-controls="site-header-search-panel"
		onclick={() => (open ? closePanel() : openPanel())}
	>
		<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
			{@html icons.search}
		</svg>
	</button>

	{#if open}
		<div class="site-header__search-panel" id="site-header-search-panel" role="dialog" aria-label="Search">
			<input
				bind:this={inputEl}
				type="search"
				class="site-header__search-input"
				placeholder="Search products or categories…"
				autocomplete="off"
				bind:value={query}
				oninput={onQueryInput}
			/>

			{#if query.trim().length >= 2}
				<div class="site-header__search-results" aria-live="polite">
					{#if loading}
						<p class="site-header__search-hint">Searching…</p>
					{:else if products.length === 0 && categories.length === 0}
						<p class="site-header__search-hint">No matches found.</p>
					{:else}
						{#if categories.length > 0}
							<p class="site-header__search-label">Categories</p>
							<ul class="site-header__search-list">
								{#each categories as cat}
									<li>
										<button type="button" class="site-header__search-item" onclick={() => navigate(categoryHref(cat.slug))}>
											<span>{cat.name}</span>
										</button>
									</li>
								{/each}
							</ul>
						{/if}
						{#if products.length > 0}
							<p class="site-header__search-label">Products</p>
							<ul class="site-header__search-list">
								{#each products as product}
									<li>
										<button type="button" class="site-header__search-item" onclick={() => navigate(productHref(product.slug))}>
											{#if product.images[0]?.src}
												<img src={product.images[0].thumbnail ?? product.images[0].src} alt="" width="32" height="32" loading="lazy" />
											{/if}
											<span>{product.name}</span>
										</button>
									</li>
								{/each}
							</ul>
						{/if}
						<button type="button" class="site-header__search-all" onclick={() => navigate(viewAllHref())}>
							View all results
						</button>
					{/if}
				</div>
			{:else}
				<p class="site-header__search-hint">Type at least 2 characters.</p>
			{/if}
		</div>
	{/if}
</div>
