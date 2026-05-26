<script lang="ts">
	/**
	 * Recently-viewed products strip. Client-side, localStorage-backed.
	 *
	 * Data shape in localStorage key `wchs_recently_viewed`:
	 *   [{ id: number, ts: number (ms since epoch) }, ...]   — max 10, LRU
	 *
	 * The PDP adds to this list on mount (see +page.svelte). This component
	 * reads the list, filters stale entries (>30 days), fetches the products
	 * via getProductsByIds, and renders a ProductSlider.
	 *
	 * Robustness:
	 *   - Corrupt localStorage → reset gracefully
	 *   - Nonexistent product IDs → silently dropped by getProductsByIds
	 *   - XSS-like IDs (non-int) → coerced to int, invalid dropped
	 *   - Quota-exceeded writes (elsewhere) → not our concern here
	 *
	 * Empty state: renders nothing. No awkward "nothing yet" message.
	 */
	import { onMount } from 'svelte';
	import ProductSlider from './ProductSlider.svelte';
	import { getProductsByIds } from '$lib/wc/products';
	import { isCartCrossSellBlockedProduct } from '$lib/config.svelte';
	import type { StoreProduct } from '$lib/wc/products';

	let { title = 'Recently viewed', edge_to_edge = false }: {
		title?: string;
		edge_to_edge?: boolean;
	} = $props();

	const STORAGE_KEY = 'wchs_recently_viewed';
	const MAX_AGE_MS = 30 * 24 * 60 * 60 * 1000; // 30 days
	const MAX_ITEMS  = 10;

	let products = $state<StoreProduct[]>([]);
	let loading = $state(true);

	onMount(async () => {
		if (typeof localStorage === 'undefined') { loading = false; return; }

		let raw: string | null = null;
		try { raw = localStorage.getItem(STORAGE_KEY); } catch { /* blocked in some privacy modes */ }
		if (!raw) { loading = false; return; }

		let list: { id: number; ts: number }[] = [];
		try {
			const parsed = JSON.parse(raw);
			if (!Array.isArray(parsed)) throw new Error('not-array');
			const now = Date.now();
			list = parsed
				.filter(e => e && typeof e === 'object')
				.map(e => ({ id: Number((e as { id: unknown }).id), ts: Number((e as { ts: unknown }).ts) }))
				.filter(e => Number.isFinite(e.id) && e.id > 0 && Number.isFinite(e.ts) && (now - e.ts) < MAX_AGE_MS);
		} catch {
			// Corrupt JSON or non-array — wipe and move on.
			try { localStorage.removeItem(STORAGE_KEY); } catch { /* */ }
			loading = false;
			return;
		}

		if (list.length === 0) { loading = false; return; }

		// Preserve LRU order (list is already stored head=newest)
		const ids = list.slice(0, MAX_ITEMS).map(e => e.id);
		try {
			const fetched = await getProductsByIds(ids);
			// Restore the LRU order that ids was in (the API may return any order)
			const byId = new Map(fetched.map(p => [p.id, p]));
			products = ids
				.map((id) => byId.get(id))
				.filter((p): p is StoreProduct => !!p && !isCartCrossSellBlockedProduct(p.id, p.slug));
		} catch {
			products = [];
		} finally {
			loading = false;
		}
	});
</script>

{#if !loading && products.length > 0}
	<section class="homepage-module recently-viewed" class:is-edge={edge_to_edge} data-testid="recently-viewed">
		<div class="homepage-module__head">
			<h2 class="homepage-module__label wchs-section-heading">{title}</h2>
		</div>
		<ProductSlider {products} {edge_to_edge} listingSource="Recently viewed" />
	</section>
{/if}

<style>
	.homepage-module {
		padding: 27px 24px 8px;
		max-width: 1440px;
		margin: 0 auto;
	}
	@media (min-width: 640px) {
		.homepage-module { padding: 37px 32px 12px; }
	}
	.homepage-module.is-edge {
		max-width: 100%;
		padding-left: 16px;
		padding-right: 16px;
	}
	.homepage-module__head {
		display: flex;
		align-items: baseline;
		justify-content: space-between;
		padding: 0 0 20px;
	}
	.homepage-module__label {
		line-height: 1.2;
		margin: 0;
	}
</style>
