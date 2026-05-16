<script lang="ts">
	import { onMount } from 'svelte';
	import { fade, fly } from 'svelte/transition';
	import EmblaCarousel, { type EmblaCarouselType, type EmblaOptionsType } from 'embla-carousel';
	import ReviewCard, { type ReviewData } from './ReviewCard.svelte';
	import type { SpacingPreset } from '$lib/config.svelte';

	let { product_ids = [], title = 'What customers say', photos_only = false, spacing_v = 'normal', spacing_h = 'normal', center_header = false }: {
		product_ids?: number[];
		title?: string;
		photos_only?: boolean;
		spacing_v?: SpacingPreset;
		spacing_h?: SpacingPreset;
		center_header?: boolean;
	} = $props();

	let reviews = $state<ReviewData[]>([]);
	// "Based on N reviews" + "Rated 4.X" labels are ALWAYS sitewide totals,
	// regardless of which product_ids this slider is scoped to for card
	// rendering. Rule: any scoped/subset count shown as a module label would
	// under-report social proof (e.g. a curated slider saying "Based on 30
	// reviews" when the store actually has 103). The only place a scoped
	// review count is allowed is on an individual PDP where it's obviously
	// about a single product. Sourced from GET /wchs/v1/reviews/aggregate.
	let totalReviews = $state(0);
	let totalAvg = $state('0');
	let viewport = $state<HTMLElement | undefined>();
	let track = $state<HTMLUListElement | undefined>();
	let embla: EmblaCarouselType | undefined;
	let modalReview = $state<ReviewData | null>(null);

	const options: EmblaOptionsType = {
		align: 'start',
		containScroll: 'trimSnaps',
		dragFree: true,
		slidesToScroll: 1,
	};

	onMount(async () => {
		const aggregateRes = await fetch('/wp-json/wchs/v1/reviews/aggregate')
			.then((r) => r.ok ? r.json() : null)
			.catch(() => null);

		const ids = product_ids.length > 0
			? product_ids
			: (Array.isArray(aggregateRes?.product_ids) ? aggregateRes.product_ids : []);

		const cardResults = ids.length > 0
			? await Promise.allSettled(
				ids.map((id: number) =>
					fetch(`/wp-json/wchs/v1/reviews/${id}?per_page=50`).then((r) =>
						r.ok ? r.json() : null
					)
				)
			)
			: [];

		// Label from sitewide aggregate. Prefer `with_content` (matches what
		// cards can actually render); fall back to `total` if the endpoint is
		// unavailable. Avg is also sitewide.
		if (aggregateRes) {
			// Use raw total (all approved reviews, including star-only rows
			// without written content) rather than `with_content`. Rationale:
			// a rating is a review — the social-proof count should reflect
			// every approved review the store received, not just the subset
			// that happens to render as a card in this carousel.
			totalReviews = Number(aggregateRes.total ?? 0);
			totalAvg = Number(aggregateRes.average ?? 0) > 0
				? Number(aggregateRes.average).toFixed(1)
				: '0';
		}

		// Cards from the scoped per-product fetches.
		const all: ReviewData[] = [];
		for (const r of cardResults) {
			if (r.status === 'fulfilled' && r.value && Array.isArray(r.value.reviews)) {
				all.push(...r.value.reviews);
			}
		}
		const dedupd = all
			.filter((r, i, arr) => arr.findIndex(x => x.id === r.id) === i)
			.filter(r => (r.content || '').trim().length > 0);

		// Fallback: if the aggregate endpoint failed (old WP without this
		// route, for instance), derive the label from the scoped pool so
		// something still renders — just flag it in dev.
		if (!aggregateRes) {
			totalReviews = dedupd.length;
			totalAvg = dedupd.length > 0
				? (dedupd.reduce((s, r) => s + r.rating, 0) / dedupd.length).toFixed(1)
				: '0';
		}

		let filtered = dedupd.slice().sort(() => Math.random() - 0.5);
		if (photos_only) {
			filtered = filtered.filter(r => r.images.length > 0);
		}
		reviews = filtered;
	});

	$effect(() => {
		if (!viewport || !track || reviews.length === 0) return;
		embla = EmblaCarousel(viewport, { ...options, container: track });
		return () => embla?.destroy();
	});

	function openModal(review: ReviewData) {
		modalReview = review;
	}

	function closeModal() {
		modalReview = null;
	}

	function formatDate(iso: string): string {
		return new Date(iso).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
	}
</script>

{#if reviews.length > 0}
	<section class="review-slider" class:is-v-compact={spacing_v === 'compact'} class:is-v-spacious={spacing_v === 'spacious'} class:is-h-compact={spacing_h === 'compact'} class:is-h-spacious={spacing_h === 'spacious'} id="reviews">
		<div class="review-slider__head" class:is-centered={center_header}>
			<p class="review-slider__title wchs-section-heading">{title}</p>
			<p class="review-slider__meta">
				<span class="review-slider__rating">{totalAvg}</span>
				<span class="review-slider__stars">
					{#each Array(5) as _, i}
						<span class="review-slider__star" class:filled={i < Math.round(parseFloat(totalAvg))}>★</span>
					{/each}
				</span>
				<span class="review-slider__count">Based on {totalReviews} reviews</span>
			</p>
		</div>
		<div class="review-slider__viewport" bind:this={viewport}>
			<ul class="review-slider__track" bind:this={track}>
				{#each reviews as review (review.id)}
					<li class="review-slider__slide">
						<ReviewCard {review} onclick={() => openModal(review)} />
					</li>
				{/each}
			</ul>
		</div>
	</section>
{/if}

<!-- Review detail modal -->
{#if modalReview}
	<div class="review-modal" role="dialog" aria-label="Review detail">
		<!-- svelte-ignore a11y_click_events_have_key_events -->
		<div class="review-modal__backdrop" role="presentation" onclick={closeModal} transition:fade={{ duration: 200 }}></div>
		<div class="review-modal__dialog" transition:fly={{ y: 24, duration: 250 }}>
			<button class="review-modal__close" onclick={closeModal} aria-label="Close">✕</button>

			{#if modalReview.images.length > 0}
				<div class="review-modal__images">
					{#each modalReview.images as img}
						<img src={img.src} alt="" />
					{/each}
				</div>
			{/if}

			<div class="review-modal__body">
				<div class="review-modal__header">
					<span class="review-modal__author">{modalReview.author}</span>
					{#if modalReview.verified}
						<span class="review-modal__verified">Verified purchase</span>
					{/if}
				</div>
				<div class="review-modal__stars">
					{#each Array(5) as _, i}
						<span class="review-modal__star" class:filled={i < modalReview.rating}>★</span>
					{/each}
					<span class="review-modal__date">{formatDate(modalReview.date)}</span>
				</div>
				<p class="review-modal__content">{modalReview.content}</p>
			</div>
		</div>
	</div>
{/if}

<style>
	.review-slider {
		--mod-pt: 32px;
		--mod-pb: 40px;
		--mod-px: 28px;
		--mod-max-w: 960px;
		max-width: var(--mod-max-w);
		margin: 0 auto;
		padding: var(--mod-pt) var(--mod-px) var(--mod-pb);
	}
	.review-slider.is-v-compact  { --mod-pt: 12px; --mod-pb: 12px; }
	.review-slider.is-v-spacious { --mod-pt: 56px; --mod-pb: 64px; }
	.review-slider.is-h-compact  { --mod-max-w: 100%; --mod-px: 12px; }
	.review-slider.is-h-spacious { --mod-max-w: 760px; --mod-px: 40px; }

	.review-slider__head {
		padding: 0 0 20px;
	}
	.review-slider__head.is-centered {
		text-align: center;
	}
	.review-slider__head.is-centered .review-slider__meta {
		justify-content: center;
	}
	.review-slider__title {
		margin: 0 0 10px;
		line-height: 1.2;
	}
	.review-slider__meta {
		display: flex;
		align-items: center;
		gap: 8px;
		margin: 0;
		font-size: 13px;
		color: var(--fg-muted);
	}
	.review-slider__rating {
		font-weight: 600;
		color: var(--fg);
		font-size: 15px;
	}
	.review-slider__stars {
		font-size: 13px;
		letter-spacing: 1px;
	}
	.review-slider__star {
		color: var(--border);
	}
	.review-slider__star.filled {
		color: var(--accent, #ffdd24);
	}
	.review-slider__count {
		font-size: 12px;
	}

	.review-slider__viewport {
		overflow: hidden;
	}
	.review-slider__track {
		display: flex;
		gap: 16px;
		list-style: none;
		padding: 0;
		margin: 0;
	}
	.review-slider__slide {
		flex: 0 0 260px;
		min-height: 280px;
	}
	@media (min-width: 640px) {
		.review-slider__slide { flex: 0 0 280px; }
	}

	/* ── Modal ── */
	.review-modal__backdrop {
		position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 9998;
	}
	.review-modal__dialog {
		position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%);
		z-index: 9999; background: var(--bg); color: var(--fg);
		border: 1px solid var(--border); max-width: 560px; width: calc(100% - 32px);
		max-height: 90vh; overflow-y: auto;
		font-family: var(--font-sans);
	}
	.review-modal__close {
		position: absolute; top: 12px; right: 12px; z-index: 1;
		background: var(--bg); border: 1px solid var(--border);
		width: 32px; height: 32px; cursor: pointer;
		font-size: 14px; color: var(--fg-muted);
		display: flex; align-items: center; justify-content: center;
	}
	.review-modal__close:hover { color: var(--fg); border-color: var(--fg); }

	.review-modal__images {
		display: flex; gap: 2px; overflow-x: auto;
	}
	.review-modal__images img {
		width: 100%; max-height: 320px; object-fit: cover; display: block;
	}
	.review-modal__images img:only-child {
		width: 100%;
	}

	.review-modal__body {
		padding: 20px 24px 24px;
	}
	.review-modal__header {
		display: flex; align-items: center; gap: 8px; margin-bottom: 8px;
	}
	.review-modal__author {
		font-size: 15px; font-weight: 600;
	}
	.review-modal__verified {
		font-size: 10px; text-transform: uppercase; letter-spacing: 0.06em;
		color: var(--success, #059669); font-weight: 600;
	}
	.review-modal__stars {
		display: flex; align-items: center; gap: 8px; margin-bottom: 16px;
		font-size: 13px; letter-spacing: 1px;
	}
	.review-modal__star { color: var(--border); }
	.review-modal__star.filled { color: var(--accent, #ffdd24); }
	.review-modal__date { font-size: 12px; color: var(--fg-muted); }
	.review-modal__content {
		font-size: 14px; line-height: 1.65; color: var(--fg); margin: 0;
	}
</style>
