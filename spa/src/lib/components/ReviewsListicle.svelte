<script lang="ts">
	import { onMount } from 'svelte';
	import EmblaCarousel from 'embla-carousel';
	import type { ModuleResolved, ReviewsListicleModuleConfig, SpacingPreset } from '$lib/config.svelte';

	type Variant = 'default' | 'proof' | 'product' | 'marquee';

	let {
		config,
		resolved,
		spacing_v = 'normal',
		spacing_h = 'normal',
		variant = 'default',
		embedded = false,
		showHeadline,
		limit,
		offset = 0,
		columns = 3,
		visibleSlides = 3,
		marqueeItems,
		marqueeHeadline,
	}: {
		config: ReviewsListicleModuleConfig;
		resolved?: ModuleResolved;
		spacing_v?: SpacingPreset;
		spacing_h?: SpacingPreset;
		variant?: Variant;
		embedded?: boolean;
		showHeadline?: boolean;
		limit?: number;
		offset?: number;
		columns?: number;
		visibleSlides?: number;
		marqueeItems?: ReviewsListicleModuleConfig['marquee_items'];
		marqueeHeadline?: string;
	} = $props();

	const accentStyle = $derived(resolved?.accent_color ? `--accent: ${resolved.accent_color};` : '');

	const allReviews = $derived.by(() => {
		const source =
			variant === 'product'
				? config.items ?? []
				: variant === 'proof'
					? config.proof_items ?? config.items ?? []
					: variant === 'marquee'
						? marqueeItems ?? config.marquee_items ?? []
						: config.items ?? [];
		return source
			.filter((it) => {
				if (variant === 'product') {
					return Boolean(
						(it.product ?? '').trim() && (it.quote ?? '').trim() && (it.name ?? '').trim()
					);
				}
				if (variant === 'proof') {
					return Boolean((it.title ?? '').trim() && (it.quote ?? '').trim() && (it.name ?? '').trim());
				}
				return Boolean((it.quote ?? '').trim() && (it.name ?? '').trim());
			})
			.map((it) => ({
				title: it.title?.trim() || it.product?.trim() || '',
				quote: it.quote!.trim(),
				name: it.name!.trim(),
				location: it.location?.trim() || '',
				product: it.product?.trim() || '',
				rating: Math.min(5, Math.max(1, Number(it.rating) || 5)),
			}));
	});

	const reviews = $derived.by(() => {
		if (variant === 'marquee' || variant === 'proof' || variant === 'product') return allReviews;
		const start = Math.max(0, offset);
		const end = limit != null ? start + Math.max(0, limit) : undefined;
		return allReviews.slice(start, end);
	});

	const displayHeadline = $derived.by(() => {
		if (variant === 'product') {
			if (embedded) return config.headline?.trim() || '';
			return config.headline?.trim() || config.proof_headline?.trim() || '';
		}
		if (variant === 'proof') {
			return config.proof_headline?.trim() || config.headline?.trim() || '';
		}
		if (variant === 'marquee') {
			return marqueeHeadline?.trim() || config.marquee_headline?.trim() || config.headline?.trim() || '';
		}
		return config.headline?.trim() || '';
	});

	const displaySubheadline = $derived.by(() => {
		if (embedded && variant === 'product') return '';
		if (variant === 'product' || variant === 'proof') {
			return config.proof_subheadline?.trim() || config.subheadline?.trim() || '';
		}
		return config.subheadline?.trim() || '';
	});

	const headlineVisible = $derived(
		(showHeadline ?? (variant !== 'proof' && variant !== 'product' || !embedded)) &&
			Boolean(displayHeadline)
	);

	const subheadlineVisible = $derived(
		Boolean(displaySubheadline) &&
			(variant === 'proof' || variant === 'product' || variant === 'default')
	);

	const isCarouselVariant = $derived(
		variant === 'marquee' || variant === 'proof' || variant === 'product'
	);

	let marqueeViewport = $state<HTMLElement | null>(null);
	let marqueeTrack = $state<HTMLElement | null>(null);
	let embla: ReturnType<typeof EmblaCarousel> | null = null;

	const emblaOptions = $derived.by(() => {
		const opts: Parameters<typeof EmblaCarousel>[1] = {
			align: 'start',
			loop: reviews.length >= 2,
			slidesToScroll: 1,
		};
		if (variant !== 'marquee') {
			opts.containScroll = 'trimSnaps';
		}
		return opts;
	});

	onMount(() => () => {
		embla?.destroy();
		embla = null;
	});

	$effect(() => {
		if (!isCarouselVariant || !marqueeViewport || !marqueeTrack || reviews.length < 2) {
			embla?.destroy();
			embla = null;
			return;
		}

		embla?.destroy();
		embla = EmblaCarousel(marqueeViewport, emblaOptions);
		requestAnimationFrame(() => {
			embla?.reInit();
			embla?.scrollTo(0, true);
		});

		const tick = () => embla?.scrollNext();
		const id = window.setInterval(tick, 4000);

		return () => {
			window.clearInterval(id);
			embla?.destroy();
			embla = null;
		};
	});

	function reviewFooter(review: (typeof reviews)[number]): string {
		if (variant === 'product') {
			return `${review.name} · Verified order`;
		}
		if (variant === 'proof') {
			const loc = review.location ? ` — ${review.location}` : '';
			return `${review.name}${loc} · Verified order`;
		}
		if (review.product) return `${review.name} · ${review.product}`;
		return review.name;
	}

	function cardTitle(review: (typeof reviews)[number]): string {
		if (variant === 'product') return review.product;
		return review.title;
	}
</script>

{#if headlineVisible || subheadlineVisible || reviews.length}
	<section
		class="reviews-listicle"
		class:is-v-compact={spacing_v === 'compact'}
		class:is-v-spacious={spacing_v === 'spacious'}
		class:is-h-compact={spacing_h === 'compact'}
		class:is-h-spacious={spacing_h === 'spacious'}
		class:is-embedded={embedded}
		class:is-proof={variant === 'proof' || variant === 'product'}
		class:is-product={variant === 'product'}
		class:is-marquee={variant === 'marquee'}
		style="{accentStyle}; --rl-columns: {columns}; --rl-visible-slides: {visibleSlides};"
	>
		<div class="reviews-listicle__inner">
			{#if headlineVisible || subheadlineVisible}
				<header class="reviews-listicle__head">
					{#if headlineVisible}
						<h2 class="reviews-listicle__headline wchs-section-heading">{displayHeadline}</h2>
					{/if}
					{#if subheadlineVisible}
						<p class="reviews-listicle__subheadline">{displaySubheadline}</p>
					{/if}
				</header>
			{/if}

			{#if reviews.length}
				{#if isCarouselVariant}
					<div class="reviews-listicle__marquee" class:is-proof={variant === 'proof' || variant === 'product'}>
						<div class="reviews-listicle__viewport" bind:this={marqueeViewport}>
							<ul class="reviews-listicle__track" bind:this={marqueeTrack}>
								{#each reviews as review}
									<li class="reviews-listicle__slide">
										<article class="reviews-listicle__card">
											<div class="reviews-listicle__stars" aria-label="{review.rating} out of 5 stars">
												{#each Array(5) as _, i}
													<span
														class="reviews-listicle__star"
														class:is-filled={i < review.rating}
														aria-hidden="true">★</span
													>
												{/each}
											</div>
											{#if (variant === 'proof' || variant === 'product') && cardTitle(review)}
												<h3 class="reviews-listicle__card-title">{cardTitle(review)}</h3>
											{/if}
											<blockquote class="reviews-listicle__quote">
												<p>{variant === 'marquee' ? `“${review.quote}”` : review.quote}</p>
											</blockquote>
											<cite class="reviews-listicle__cite">{reviewFooter(review)}</cite>
										</article>
									</li>
								{/each}
							</ul>
						</div>
					</div>
				{:else}
					<ul class="reviews-listicle__grid">
						{#each reviews as review}
							<li class="reviews-listicle__card">
								<div class="reviews-listicle__stars" aria-label="{review.rating} out of 5 stars">
									{#each Array(5) as _, i}
										<span
											class="reviews-listicle__star"
											class:is-filled={i < review.rating}
											aria-hidden="true">★</span
										>
									{/each}
								</div>
								<blockquote class="reviews-listicle__quote">
									<p>&ldquo;{review.quote}&rdquo;</p>
								</blockquote>
								<cite class="reviews-listicle__cite">{reviewFooter(review)}</cite>
							</li>
						{/each}
					</ul>
				{/if}
			{/if}
		</div>
	</section>
{/if}

<style>
	.reviews-listicle {
		--mod-pt: var(--wchs-spacing-v-normal, 48px);
		--mod-pb: var(--wchs-spacing-v-normal, 56px);
		--mod-px: 28px;
		--rl-max: min(1040px, 100%);
		--rl-teal: var(--accent, #0d9488);
		background: var(--bg-muted);
		color: var(--fg);
		padding: var(--mod-pt) var(--mod-px) var(--mod-pb);
	}
	.reviews-listicle.is-v-compact {
		--mod-pt: var(--wchs-spacing-v-compact, 24px);
		--mod-pb: var(--wchs-spacing-v-compact, 28px);
	}
	.reviews-listicle.is-v-spacious {
		--mod-pt: var(--wchs-spacing-v-spacious, 64px);
		--mod-pb: var(--wchs-spacing-v-spacious, 72px);
	}
	.reviews-listicle.is-h-compact {
		--mod-px: 16px;
	}
	.reviews-listicle.is-h-spacious {
		--mod-px: 40px;
	}
	.reviews-listicle.is-embedded {
		background: transparent;
	}
	.reviews-listicle.is-proof:not(.is-embedded) {
		background: color-mix(in srgb, var(--rl-teal) 6%, var(--bg) 94%);
	}

	.reviews-listicle.is-embedded.is-proof,
	.reviews-listicle.is-embedded.is-product {
		--mod-pt: 0;
		--mod-pb: 0;
		--mod-px: 0;
	}

	.reviews-listicle__inner {
		max-width: var(--rl-max);
		margin: 0 auto;
	}

	.reviews-listicle__head {
		margin: 0 0 clamp(24px, 4vw, 36px);
		text-align: center;
	}

	.reviews-listicle__headline {
		margin: 0;
		text-align: center;
	}

	.reviews-listicle.is-embedded.is-product .reviews-listicle__headline,
	.reviews-listicle.is-embedded.is-proof .reviews-listicle__headline {
		margin-bottom: 0;
	}

	.reviews-listicle__subheadline {
		margin: 10px 0 0;
		font-size: clamp(14px, 2vw, 16px);
		line-height: 1.5;
		color: color-mix(in srgb, var(--fg) 58%, transparent);
	}

	.reviews-listicle__grid {
		list-style: none;
		margin: 0;
		padding: 0;
		display: grid;
		grid-template-columns: repeat(var(--rl-columns, 3), minmax(0, 1fr));
		gap: clamp(16px, 2.5vw, 24px);
		align-items: stretch;
	}

	.reviews-listicle__marquee {
		margin: 0;
	}

	.reviews-listicle__viewport {
		overflow: hidden;
		container-type: inline-size;
		padding-inline: 2px;
	}

	.reviews-listicle.is-proof,
	.reviews-listicle.is-product,
	.reviews-listicle.is-marquee {
		--rl-slide-gap: clamp(16px, 2.5vw, 24px);
		--rl-slide-size: calc(
			(100cqw - 4px - (var(--rl-visible-slides, 3) - 1) * var(--rl-slide-gap)) /
				var(--rl-visible-slides, 3)
		);
	}

	.reviews-listicle.is-proof .reviews-listicle__head,
	.reviews-listicle.is-product .reviews-listicle__head {
		margin-bottom: clamp(28px, 4.5vw, 40px);
	}

	.reviews-listicle.is-proof .reviews-listicle__subheadline,
	.reviews-listicle.is-product .reviews-listicle__subheadline {
		margin-top: 12px;
		max-width: 42ch;
		margin-inline: auto;
	}

	.reviews-listicle__track {
		display: flex;
		align-items: stretch;
		list-style: none;
		margin: 0;
		padding: 0;
	}

	.reviews-listicle.is-proof .reviews-listicle__track,
	.reviews-listicle.is-product .reviews-listicle__track,
	.reviews-listicle.is-marquee .reviews-listicle__track {
		margin-left: calc(-1 * var(--rl-slide-gap));
	}

	.reviews-listicle__slide {
		flex: 0 0 min(320px, 82vw);
		min-width: 0;
		display: flex;
		align-items: stretch;
	}

	.reviews-listicle__slide > .reviews-listicle__card {
		flex: 1 1 auto;
		min-height: 100%;
	}

	.reviews-listicle.is-proof .reviews-listicle__slide,
	.reviews-listicle.is-product .reviews-listicle__slide,
	.reviews-listicle.is-marquee .reviews-listicle__slide {
		flex: 0 0 var(--rl-slide-size);
		max-width: var(--rl-slide-size);
		padding-left: var(--rl-slide-gap);
		box-sizing: content-box;
	}

	@supports not (width: 100cqw) {
		.reviews-listicle.is-proof,
		.reviews-listicle.is-product,
		.reviews-listicle.is-marquee {
			--rl-slide-size: calc(
				(100% - (var(--rl-visible-slides, 3) - 1) * var(--rl-slide-gap)) /
					var(--rl-visible-slides, 3)
			);
		}
	}

	@media (max-width: 800px) {
		.reviews-listicle.is-proof,
		.reviews-listicle.is-product,
		.reviews-listicle.is-marquee {
			--rl-slide-size: calc(100cqw - 4px - var(--rl-slide-gap));
		}

		.reviews-listicle.is-proof .reviews-listicle__slide,
		.reviews-listicle.is-product .reviews-listicle__slide,
		.reviews-listicle.is-marquee .reviews-listicle__slide {
			flex: 0 0 var(--rl-slide-size);
			max-width: var(--rl-slide-size);
		}
	}

	.reviews-listicle__card {
		display: flex;
		flex-direction: column;
		align-items: flex-start;
		text-align: left;
		margin: 0;
		width: 100%;
		padding: clamp(20px, 3vw, 24px);
		border: 1px solid var(--border);
		border-radius: 12px;
		background: var(--bg);
		box-shadow:
			0 1px 0 color-mix(in srgb, white 40%, transparent) inset,
			0 4px 16px color-mix(in srgb, black 4%, transparent);
	}

	.reviews-listicle.is-proof .reviews-listicle__card,
	.reviews-listicle.is-product .reviews-listicle__card {
		padding: clamp(20px, 3.2vw, 26px);
		gap: 0;
	}

	.reviews-listicle.is-marquee .reviews-listicle__card {
		align-items: center;
		text-align: center;
		border-color: color-mix(in srgb, var(--rl-teal) 28%, var(--border) 72%);
	}

	.reviews-listicle__stars {
		display: flex;
		gap: 2px;
		margin: 0 0 14px;
		font-size: 14px;
		line-height: 1;
		letter-spacing: 1px;
	}
	.reviews-listicle__star {
		color: color-mix(in srgb, var(--fg) 18%, transparent);
	}
	.reviews-listicle__star.is-filled {
		color: #d4a017;
	}

	.reviews-listicle__card-title {
		margin: 0 0 12px;
		font-family: var(--font-heading, var(--font-sans));
		font-size: clamp(15px, 2vw, 17px);
		font-weight: 800;
		line-height: 1.3;
		letter-spacing: -0.01em;
		color: var(--fg);
	}

	.reviews-listicle.is-proof .reviews-listicle__card-title {
		min-height: calc(1.3em * 2);
	}

	.reviews-listicle.is-product .reviews-listicle__card-title {
		font-size: clamp(16px, 2.2vw, 18px);
		color: color-mix(in srgb, var(--rl-teal) 78%, var(--fg) 22%);
	}

	.reviews-listicle__quote {
		margin: 0;
		padding: 0;
		border: none;
		font-style: normal;
		width: 100%;
	}

	.reviews-listicle.is-proof .reviews-listicle__quote,
	.reviews-listicle.is-product .reviews-listicle__quote {
		flex: 1 1 auto;
		min-height: 0;
	}
	.reviews-listicle__quote p {
		margin: 0;
		font-size: clamp(13px, 1.8vw, 15px);
		line-height: 1.65;
		color: color-mix(in srgb, var(--fg) 68%, transparent);
	}

	.reviews-listicle__cite {
		margin: 0;
		padding-top: 14px;
		border-top: 1px solid color-mix(in srgb, var(--border) 85%, transparent);
		width: 100%;
		font-size: 12px;
		font-weight: 700;
		font-style: normal;
		letter-spacing: 0.02em;
		color: color-mix(in srgb, var(--rl-teal) 72%, var(--fg) 28%);
	}

	.reviews-listicle.is-proof .reviews-listicle__cite,
	.reviews-listicle.is-product .reviews-listicle__cite {
		margin-top: auto;
		padding-top: clamp(14px, 2vw, 18px);
	}

	.reviews-listicle__grid > .reviews-listicle__card {
		height: 100%;
	}

	.reviews-listicle.is-marquee .reviews-listicle__cite {
		color: var(--fg-muted);
		border-top: none;
		padding-top: 0;
		font-weight: 600;
	}

	@media (max-width: 800px) {
		.reviews-listicle__grid {
			grid-template-columns: 1fr;
			max-width: 420px;
			margin: 0 auto;
		}
	}
</style>
