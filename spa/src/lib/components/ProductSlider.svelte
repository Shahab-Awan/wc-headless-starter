<script lang="ts">
	/**
	 * ProductSlider — embla-powered carousel for featured collections.
	 */
	import EmblaCarousel, { type EmblaCarouselType, type EmblaOptionsType } from 'embla-carousel';
	import { onDestroy } from 'svelte';
	import ProductCard from './ProductCard.svelte';
	import { config } from '$lib/config.svelte';

	type Product = {
		id: number;
		name: string;
		slug: string;
		permalink: string;
		images: { src: string; thumbnail: string; alt: string }[];
		prices: { price: string; currency_symbol: string; currency_minor_unit: number; currency_code?: string };
		is_in_stock?: boolean;
	};

	let { products, edge_to_edge = false }: { products: Product[]; edge_to_edge?: boolean } = $props();

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
		loop: false
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
		if (!viewport || !track) return;
		embla = EmblaCarousel(viewport, { ...options, container: track });
		embla.on('scroll', update);
		embla.on('select', update);
		embla.on('reInit', update);
		embla.on('settle', () => { setDragging(false); update(); });
		embla.on('pointerDown', () => setDragging(true));
		embla.on('pointerUp', () => requestAnimationFrame(() => setDragging(false)));
		update();
		return () => embla?.destroy();
	});

	onDestroy(() => embla?.destroy());
</script>

<div class="rail" class:is-edge={edge_to_edge}>
	<div class="rail__viewport" bind:this={viewport}>
		<ul class="rail__track" bind:this={track}>
			{#each products.filter(p => config.data.product_card?.show_oos_cards !== false || p.is_in_stock !== false) as product (product.id)}
				<li class="rail__cell">
					<ProductCard {product} />
				</li>
			{/each}
		</ul>
	</div>
	<div class="rail__controls">
		<button
			type="button"
			class="rail__btn"
			bind:this={prevBtn}
			onclick={() => embla?.scrollPrev()}
			aria-label="Previous products"
		>
			<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
				<path d="M15 18l-6-6 6-6" />
			</svg>
		</button>
		<div class="rail__progress" aria-hidden="true">
			<span class="rail__progress-fill" bind:this={progressEl}></span>
		</div>
		<button
			type="button"
			class="rail__btn"
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

<style>
	.rail {
		max-width: 1400px;
		margin: 0 auto;
		padding: 0 4px;
	}
	.rail.is-edge {
		max-width: 100%;
		padding: 0;
	}
	.rail.is-edge .rail__viewport {
		padding-left: 0;
		padding-right: 0;
		margin: 0;
	}
	.rail.is-edge .rail__controls {
		padding-left: 0;
		padding-right: 0;
	}
	.rail__viewport {
		overflow-x: hidden;
		overflow-y: visible;
		touch-action: pan-y pinch-zoom;
		padding: 4px 24px;
		margin: 0 -24px;
	}
	.rail__track {
		display: flex;
		flex-wrap: nowrap;
		gap: 20px;
		margin: 0;
		padding: 0 0 8px;
		list-style: none;
		cursor: grab;
	}
	/* Applied via JS classList.toggle */
	.rail__track:global(.is-dragging) {
		cursor: grabbing;
		user-select: none;
	}
	.rail__cell {
		flex: 0 0 min(260px, 82vw);
		width: min(260px, 82vw);
	}

	.rail__controls {
		display: flex;
		align-items: center;
		gap: 16px;
		padding: 28px 24px 0;
	}
	.rail__btn {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		width: 38px;
		height: 38px;
		border: 1px solid var(--border);
		background: transparent;
		color: var(--fg);
		cursor: pointer;
		padding: 0;
		border-radius: var(--radius-sm);
		transition:
			background var(--dur-fast) var(--ease),
			color var(--dur-fast) var(--ease),
			border-color var(--dur-fast) var(--ease),
			opacity var(--dur-fast) var(--ease);
	}
	.rail__btn:hover:not(:disabled) {
		background: var(--fg);
		color: var(--bg);
		border-color: var(--fg);
	}
	.rail__btn:active:not(:disabled) svg {
		transform: scale(0.85);
	}
	.rail__btn svg {
		transition: transform var(--dur-micro) var(--ease);
	}
	.rail__btn:disabled {
		opacity: 0.28;
		cursor: default;
	}

	.rail__progress {
		position: relative;
		flex: 1 1 auto;
		height: 1px;
		background: var(--border);
		overflow: hidden;
	}
	.rail__progress-fill {
		position: absolute;
		inset: 0 auto 0 0;
		width: 100%;
		background: var(--fg);
		transform: scaleX(0.12);
		transform-origin: left center;
		transition: transform var(--dur-fast) var(--ease);
	}
</style>
