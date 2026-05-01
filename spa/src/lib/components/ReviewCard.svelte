<script lang="ts">
	import { pretext } from '$lib/pretext/engine';

	export type ReviewData = {
		id: number;
		author: string;
		date: string;
		rating: number;
		content: string;
		verified: boolean;
		images: { id: number; src: string }[];
	};

	let { review, onclick }: {
		review: ReviewData;
		onclick?: () => void;
	} = $props();

	const hasImage = $derived(review.images.length > 0);

	// For no-image cards, use Pretext to fit text into the quote area.
	// Card width is 260-280px, quote area has ~24px padding on each side.
	// The area is ~195px tall (matching 4:3 aspect of image cards).
	const QUOTE_WIDTH = 220; // 268 - 48px padding
	const QUOTE_LINE_HEIGHT = 22;
	const QUOTE_MAX_HEIGHT = 170; // leave room for opening quote mark
	const maxQuoteLines = Math.floor(QUOTE_MAX_HEIGHT / QUOTE_LINE_HEIGHT);

	const quoteText = $derived.by(() => {
		if (hasImage) return review.content;
		// Measure how much text fits in the quote area
		const measured = pretext.measure(review.content, 'review-quote', QUOTE_WIDTH, QUOTE_LINE_HEIGHT);
		if (measured.lineCount <= maxQuoteLines) return review.content;
		// Binary search for the right truncation point
		let lo = 0, hi = review.content.length;
		while (lo < hi) {
			const mid = Math.ceil((lo + hi) / 2);
			const test = pretext.measure(review.content.slice(0, mid) + '…', 'review-quote', QUOTE_WIDTH, QUOTE_LINE_HEIGHT);
			if (test.lineCount <= maxQuoteLines) lo = mid;
			else hi = mid - 1;
		}
		return review.content.slice(0, lo) + '…';
	});

	const bodyTruncated = $derived(
		review.content.length > 80
			? review.content.slice(0, 80) + '…'
			: review.content
	);

	function formatDate(iso: string): string {
		return new Date(iso).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
	}
</script>

<button class="review-card" onclick={onclick} type="button">
	{#if hasImage}
		<div class="review-card__image">
			<img src={review.images[0].src} alt="" loading="lazy" />
		</div>
	{:else}
		<div class="review-card__quote-area">
			<span class="review-card__quote-mark">"</span>
			<p class="review-card__quote-text">{quoteText}</p>
		</div>
	{/if}
	<div class="review-card__body">
		<div class="review-card__header">
			<span class="review-card__author">{review.author}</span>
			{#if review.verified}
				<span class="review-card__verified">✓</span>
			{/if}
		</div>
		<div class="review-card__stars">
			{#each Array(5) as _, i}
				<span class="review-card__star" class:filled={i < review.rating}>★</span>
			{/each}
		</div>
		{#if hasImage}
			<p class="review-card__content">{bodyTruncated}</p>
		{/if}
		<span class="review-card__date">{formatDate(review.date)}</span>
	</div>
</button>

<style>
	.review-card {
		display: flex;
		flex-direction: column;
		width: 100%;
		height: 100%;
		border: 1px solid var(--border);
		background: var(--bg);
		cursor: pointer;
		text-align: left;
		font-family: var(--font-sans);
		padding: 0;
		transition: border-color 0.15s ease;
	}
	.review-card:hover {
		border-color: var(--fg-muted);
	}

	/* Photo cards */
	.review-card__image {
		width: 100%;
		aspect-ratio: 4 / 3;
		overflow: hidden;
		border-bottom: 1px solid var(--border);
	}
	.review-card__image img {
		width: 100%;
		height: 100%;
		object-fit: cover;
		display: block;
	}

	/* Text-only cards — pull quote fills the image area */
	.review-card__quote-area {
		width: 100%;
		aspect-ratio: 4 / 3;
		padding: 20px 24px;
		display: flex;
		flex-direction: column;
		border-bottom: 1px solid var(--border);
		background: var(--bg-elevated, #f9fafb);
		overflow: hidden;
		box-sizing: border-box;
	}

	.review-card__quote-mark {
		font-size: 48px;
		line-height: 0.6;
		color: var(--fg);
		opacity: 0.15;
		font-family: Georgia, serif;
		user-select: none;
		margin-bottom: 4px;
	}

	.review-card__quote-text {
		font-size: 15px;
		line-height: 22px;
		color: var(--fg);
		margin: 0;
		flex: 1;
		overflow: hidden;
	}

	.review-card__body {
		padding: 16px;
		display: flex;
		flex-direction: column;
		flex: 1;
		min-height: 0;
	}

	.review-card__header {
		display: flex;
		align-items: center;
		gap: 6px;
		margin-bottom: 6px;
	}

	.review-card__author {
		font-size: 13px;
		font-weight: 600;
		color: var(--fg);
	}

	.review-card__verified {
		font-size: 11px;
		color: var(--success, #059669);
		font-weight: 600;
	}

	.review-card__stars {
		margin-bottom: 8px;
		font-size: 12px;
		letter-spacing: 1px;
	}
	.review-card__star {
		color: var(--border);
	}
	.review-card__star.filled {
		color: var(--accent, #ffdd24);
	}

	.review-card__content {
		font-size: 13px;
		line-height: 1.5;
		color: var(--fg-muted);
		margin: 0;
		flex: 1;
	}

	.review-card__date {
		font-size: 11px;
		color: var(--fg-muted);
		margin-top: 12px;
		opacity: 0.6;
	}
</style>
