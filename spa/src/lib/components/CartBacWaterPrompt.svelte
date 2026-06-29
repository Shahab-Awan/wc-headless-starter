<script lang="ts">
	import { browser } from '$app/environment';
	import { config, BAC_WATER_SLUG, isBacWaterProduct } from '$lib/config.svelte';
	import { formatPrice } from '$lib/utils/format';
	import { cart } from '$lib/wc/cart.svelte';
	import type { StoreApiCartItem } from '$lib/wc/cart.svelte';
	import {
		findPurchasableDefaultSelection,
		findVariationId,
		getBacWaterProduct,
		getVariations,
		type StoreProduct
	} from '$lib/wc/products';
	import { canPurchase } from '$lib/wc/stock';

	const HEADER =
		'Reconstitution Solution Required For Peptide Reconstitution And Testing';
	const DISMISS_KEY = 'wchs_bac_water_dismissed';

	let product = $state<StoreProduct | null>(null);
	let loading = $state(false);
	let adding = $state(false);
	let dismissed = $state(false);

	function isShipProtectLine(item: StoreApiCartItem): boolean {
		if (item.extensions?.wchs_cro?.is_shipping_protection) return true;
		const pid = config.data.pdp?.slide_cart?.shipping_protection_product_id ?? 0;
		return pid > 0 && item.id === pid;
	}

	const bacProductId = $derived(config.data.pdp?.slide_cart?.bac_water_product_id ?? 0);

	function isBacCartLine(item: StoreApiCartItem): boolean {
		if (bacProductId > 0 && item.id === bacProductId) return true;
		const link = item.permalink.toLowerCase();
		if (link.includes(BAC_WATER_SLUG) || /bac[-_]?water|bacteriostatic/.test(link)) return true;
		if (/bac[-_]?water|bacteriostatic/i.test(item.name)) return true;
		return isBacWaterProduct(item.id);
	}

	const hasBacInCart = $derived.by(() => {
		if (!cart.cart) return false;
		return cart.cart.items.some(isBacCartLine);
	});

	const hasQualifyingItems = $derived.by(() => {
		if (!cart.cart) return false;
		return cart.cart.items.some(
			(item) => !isShipProtectLine(item) && !isBacCartLine(item)
		);
	});

	const showPrompt = $derived.by(() => {
		if (!cart.cart || dismissed || hasBacInCart || !hasQualifyingItems) return false;
		if (!product || !canPurchase(product)) return false;
		return true;
	});

	const priceLabel = $derived.by(() => {
		if (!product) return '';
		const cro = product.extensions?.wchs_cro;
		const minor = cro?.regular_price ?? Number(product.prices.price);
		return formatPrice(minor, {
			currency_minor_unit: product.prices.currency_minor_unit,
			currency_symbol: product.prices.currency_symbol,
			currency_code: product.prices.currency_code
		});
	});

	$effect(() => {
		if (!browser) return;
		if (cart.itemCount === 0) {
			dismissed = false;
			sessionStorage.removeItem(DISMISS_KEY);
			return;
		}
		if (sessionStorage.getItem(DISMISS_KEY) === '1') {
			dismissed = true;
		}
	});

	$effect(() => {
		const id = bacProductId;
		if (id < 1) {
			product = null;
			return;
		}
		let cancelled = false;
		loading = true;
		void getBacWaterProduct()
			.then((p) => {
				if (!cancelled) product = p;
			})
			.finally(() => {
				if (!cancelled) loading = false;
			});
		return () => {
			cancelled = true;
		};
	});

	function dismissPrompt() {
		dismissed = true;
		if (browser) {
			try {
				sessionStorage.setItem(DISMISS_KEY, '1');
			} catch {
				// Safari private mode / storage quota
			}
		}
	}

	async function addBacWater() {
		if (!product || adding || !canPurchase(product)) return;
		adding = true;
		try {
			if (product.has_options && product.variations.length > 0) {
				const variations = await getVariations(product.variations.map((v) => v.id));
				const defaults = findPurchasableDefaultSelection(product, variations);
				if (!defaults) return;
				const vid = findVariationId(product.variations, defaults);
				if (!vid) return;
				const variation = Object.entries(defaults).map(([attribute, value]) => ({
					attribute,
					value
				}));
				await cart.addItem(vid, 1, variation, { clicked_from: 'cart_bac_water_prompt' });
			} else {
				await cart.addItem(product.id, 1, [], { clicked_from: 'cart_bac_water_prompt' });
			}
		} finally {
			adding = false;
		}
	}
</script>

{#if showPrompt && product}
	<section class="bac-prompt" aria-label="Reconstitution solution">
		<h3 class="bac-prompt__headline">{HEADER}</h3>

		<article class="bac-prompt__card">
			<div class="bac-prompt__media">
				{#if product.images[0]}
					<img
						src={product.images[0].thumbnail || product.images[0].src}
						alt={product.images[0].alt || product.name}
						loading="lazy"
					/>
				{/if}
			</div>
			<p class="bac-prompt__title">{product.name}</p>
			<button
				type="button"
				class="bac-prompt__add"
				disabled={adding}
				aria-busy={adding}
				onclick={() => void addBacWater()}
			>
				{adding ? 'Adding…' : `Add ${priceLabel}`}
			</button>
		</article>

		<button type="button" class="bac-prompt__dismiss" onclick={dismissPrompt}>
			I don't need it
		</button>
	</section>
{:else if loading && hasQualifyingItems && !hasBacInCart && !dismissed}
	<p class="bac-prompt__loading" aria-hidden="true">Loading…</p>
{/if}

<style>
	.bac-prompt {
		margin: 0;
		padding: 20px 24px 12px;
		border-top: 1px solid var(--border);
		background: color-mix(in srgb, var(--accent) 4%, var(--bg));
	}

	.bac-prompt__headline {
		margin: 0 0 16px;
		font-size: 13px;
		font-weight: 700;
		line-height: 1.4;
		letter-spacing: -0.2px;
		text-align: center;
		color: var(--fg);
		text-wrap: balance;
	}

	.bac-prompt__card {
		display: flex;
		flex-direction: column;
		align-items: center;
		gap: 10px;
		max-width: 220px;
		margin: 0 auto;
		padding: 14px 12px 12px;
		border: 1px solid var(--accent);
		border-radius: var(--radius-md, 8px);
		background: transparent;
	}

	.bac-prompt__media {
		width: 72px;
		height: 72px;
		border-radius: var(--radius-sm, 6px);
		overflow: hidden;
		background: color-mix(in srgb, var(--fg) 4%, var(--bg));
		border: 1px solid var(--border);
	}

	.bac-prompt__media img {
		display: block;
		width: 100%;
		height: 100%;
		object-fit: cover;
	}

	.bac-prompt__title {
		margin: 0;
		font-size: 13px;
		font-weight: 600;
		line-height: 1.35;
		text-align: center;
		color: var(--fg);
	}

	.bac-prompt__add {
		width: 100%;
		margin-top: 2px;
		padding: 9px 14px;
		border: 1px solid var(--accent);
		border-radius: var(--radius-sm, 6px);
		background: transparent;
		color: var(--accent);
		font: inherit;
		font-size: 13px;
		font-weight: 600;
		letter-spacing: -0.15px;
		cursor: pointer;
		transition:
			background 150ms var(--ease-out),
			color 150ms var(--ease-out);
	}

	.bac-prompt__add:hover:not(:disabled) {
		background: color-mix(in srgb, var(--accent) 10%, transparent);
	}

	.bac-prompt__add:disabled {
		opacity: 0.65;
		cursor: wait;
	}

	.bac-prompt__dismiss {
		display: block;
		width: fit-content;
		margin: 12px auto 0;
		padding: 0;
		border: 0;
		background: transparent;
		color: var(--fg-muted, color-mix(in srgb, var(--fg) 55%, transparent));
		font: inherit;
		font-size: 12px;
		text-decoration: underline;
		text-underline-offset: 2px;
		cursor: pointer;
	}

	.bac-prompt__dismiss:hover {
		color: var(--fg);
	}

	.bac-prompt__loading {
		margin: 0;
		padding: 16px 24px;
		border-top: 1px solid var(--border);
		font-size: 12px;
		color: var(--fg-muted, color-mix(in srgb, var(--fg) 55%, transparent));
		text-align: center;
	}
</style>
