<script lang="ts">
	import EmblaCarousel, { type EmblaCarouselType, type EmblaOptionsType } from 'embla-carousel';
	import { onDestroy } from 'svelte';
	import { config, isCartCrossSellBlockedProduct } from '$lib/config.svelte';
	import ProductCard from '$lib/components/ProductCard.svelte';
	import type { StoreProduct } from '$lib/wc/products';

	let {
		products,
	}: {
		products: StoreProduct[];
	} = $props();

	const copy = $derived(config.data.pdp?.cross_sell ?? {});
	const eyebrow = $derived(copy.eyebrow ?? 'FREQUENTLY PAIRED');
	const title = $derived(copy.title ?? 'Often ordered with');
	const subtitle = $derived(copy.subtitle ?? 'Researchers commonly add these to their order');
	const viewAllUrl = $derived(copy.view_all_url ?? '/shop');

	const visible = $derived(
		products.filter(
			(p) =>
				!isCartCrossSellBlockedProduct(p.id, p.slug) &&
				(config.data.product_card?.show_oos_cards !== false || p.is_in_stock !== false)
		)
	);

	let viewport: HTMLElement;
	let track: HTMLUListElement;
	let progressEl: HTMLElement;
	let prevBtn: HTMLButtonElement;
	let nextBtn: HTMLButtonElement;
	let embla: EmblaCarouselType | undefined;

	const options: EmblaOptionsType = {
		align: 'start',
		containScroll: 'trimSnaps',
		dragFree: true,
		duration: 24,
		loop: false,
	};

	function update() {
		if (!embla) return;
		const p = Math.max(0.12, Math.min(1, embla.scrollProgress() || 0.12));
		if (progressEl) progressEl.style.transform = `scaleX(${p})`;
		if (prevBtn) prevBtn.disabled = !embla.canScrollPrev();
		if (nextBtn) nextBtn.disabled = !embla.canScrollNext();
	}

	function setDragging(active: boolean) {
		track?.classList.toggle('is-dragging', active);
	}

	$effect(() => {
		if (!viewport || !track || visible.length === 0) return;
		embla = EmblaCarousel(viewport, { ...options, container: track });
		embla.on('scroll', update);
		embla.on('select', update);
		embla.on('reInit', update);
		embla.on('settle', () => {
			setDragging(false);
			update();
		});
		embla.on('pointerDown', () => setDragging(true));
		embla.on('pointerUp', () => requestAnimationFrame(() => setDragging(false)));
		update();
		return () => embla?.destroy();
	});

	onDestroy(() => embla?.destroy());
</script>

{#if visible.length > 0}
	<section class="pdp-pair" aria-labelledby="pdp-pair-title">
		<header class="pdp-pair__head">
			<div class="pdp-pair__head-text">
				<p class="pdp-pair__eyebrow">{eyebrow}</p>
				<h2 id="pdp-pair-title" class="pdp-pair__title">{title}</h2>
				{#if subtitle}
					<p class="pdp-pair__subtitle">{subtitle}</p>
				{/if}
			</div>
			<a class="pdp-pair__view-all" href={viewAllUrl}>View all <span aria-hidden="true">→</span></a>
		</header>

		<div class="pdp-pair__rail">
			<div class="pdp-pair__viewport" bind:this={viewport}>
				<ul class="pdp-pair__track" bind:this={track}>
					{#each visible as product (product.id)}
						<li class="pdp-pair__cell">
							<ProductCard {product} cardWidth={260} listingSource="Cross-sells" />
						</li>
					{/each}
				</ul>
			</div>
			<div class="pdp-pair__controls">
				<button
					type="button"
					class="pdp-pair__btn"
					bind:this={prevBtn}
					onclick={() => embla?.scrollPrev()}
					aria-label="Previous products"
				>
					<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
						<path d="M15 18l-6-6 6-6" />
					</svg>
				</button>
				<div class="pdp-pair__progress" aria-hidden="true">
					<span class="pdp-pair__progress-fill" bind:this={progressEl}></span>
				</div>
				<button
					type="button"
					class="pdp-pair__btn"
					bind:this={nextBtn}
					onclick={() => embla?.scrollNext()}
					aria-label="Next products"
				>
					<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
						<path d="M9 6l6 6-6 6" />
					</svg>
				</button>
			</div>
		</div>
	</section>
{/if}

<style>
	.pdp-pair {
		padding: 48px 28px 56px;
		max-width: 1320px;
		margin: 0 auto;
	}
	@media (max-width: 860px) {
		.pdp-pair {
			padding: 40px 20px 48px;
		}
	}
	.pdp-pair__head {
		display: flex;
		align-items: flex-start;
		justify-content: space-between;
		gap: 20px;
		margin-bottom: 28px;
	}
	.pdp-pair__eyebrow {
		margin: 0 0 8px;
		font-size: 11px;
		font-weight: 600;
		letter-spacing: 0.14em;
		text-transform: uppercase;
		color: var(--fg-muted);
	}
	.pdp-pair__title {
		margin: 0 0 6px;
		font-family: var(--font-heading, var(--font-sans));
		font-size: clamp(28px, 3vw, 36px);
		font-weight: 700;
		letter-spacing: -0.03em;
		line-height: 1.1;
		color: #000;
	}
	.pdp-pair__subtitle {
		margin: 0;
		font-size: 15px;
		line-height: 1.45;
		color: var(--fg-muted);
	}
	.pdp-pair__view-all {
		flex-shrink: 0;
		margin-top: 8px;
		font-size: 14px;
		font-weight: 500;
		color: var(--fg-muted);
		text-decoration: none;
		white-space: nowrap;
	}
	.pdp-pair__view-all:hover {
		color: var(--accent);
	}
	.pdp-pair__viewport {
		overflow: hidden;
		touch-action: pan-y pinch-zoom;
	}
	.pdp-pair__track {
		display: flex;
		flex-wrap: nowrap;
		gap: 16px;
		margin: 0;
		padding: 0 0 8px;
		list-style: none;
		cursor: grab;
	}
	.pdp-pair__track:global(.is-dragging) {
		cursor: grabbing;
		user-select: none;
	}
	.pdp-pair__cell {
		flex: 0 0 min(268px, 82vw);
		width: min(268px, 82vw);
	}
	.pdp-pair__controls {
		display: flex;
		align-items: center;
		gap: 16px;
		margin-top: 24px;
	}
	.pdp-pair__btn {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		width: 38px;
		height: 38px;
		border: 1px solid var(--border);
		border-radius: 10px;
		background: transparent;
		color: var(--fg);
		cursor: pointer;
		padding: 0;
		transition:
			background var(--dur-fast) var(--ease),
			color var(--dur-fast) var(--ease),
			border-color var(--dur-fast) var(--ease),
			opacity var(--dur-fast) var(--ease);
	}
	.pdp-pair__btn:hover:not(:disabled) {
		background: var(--fg);
		color: var(--bg);
		border-color: var(--fg);
	}
	.pdp-pair__btn:disabled {
		opacity: 0.28;
		cursor: default;
	}
	.pdp-pair__progress {
		position: relative;
		flex: 1 1 auto;
		height: 1px;
		background: var(--border);
		overflow: hidden;
	}
	.pdp-pair__progress-fill {
		position: absolute;
		inset: 0 auto 0 0;
		width: 100%;
		background: var(--fg);
		transform: scaleX(0.12);
		transform-origin: left center;
		transition: transform var(--dur-fast) var(--ease);
	}
	:global([data-theme='dark']) .pdp-pair__title {
		color: var(--fg);
	}
</style>
