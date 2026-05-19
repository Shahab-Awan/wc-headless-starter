<script lang="ts">
	import type { ModuleResolved, ReviewsListicleModuleConfig, SpacingPreset } from '$lib/config.svelte';

	let {
		config,
		resolved,
		spacing_v = 'normal',
		spacing_h = 'normal',
	}: {
		config: ReviewsListicleModuleConfig;
		resolved?: ModuleResolved;
		spacing_v?: SpacingPreset;
		spacing_h?: SpacingPreset;
	} = $props();

	const accentStyle = $derived(resolved?.accent_color ? `--accent: ${resolved.accent_color};` : '');

	const reviews = $derived(
		(config.items ?? [])
			.filter((it) => (it.quote ?? '').trim() && (it.name ?? '').trim())
			.slice(0, 3)
			.map((it) => ({
				quote: it.quote!.trim(),
				name: it.name!.trim(),
				rating: Math.min(5, Math.max(1, Number(it.rating) || 5)),
			}))
	);
</script>

{#if config.headline?.trim() || reviews.length}
	<section
		class="reviews-listicle"
		class:is-v-compact={spacing_v === 'compact'}
		class:is-v-spacious={spacing_v === 'spacious'}
		class:is-h-compact={spacing_h === 'compact'}
		class:is-h-spacious={spacing_h === 'spacious'}
		style={accentStyle}
	>
		<div class="reviews-listicle__inner">
			{#if config.headline?.trim()}
				<h2 class="reviews-listicle__headline">{config.headline.trim()}</h2>
			{/if}

			{#if reviews.length}
				<ul class="reviews-listicle__grid">
					{#each reviews as review}
						<li class="reviews-listicle__card">
							<div class="reviews-listicle__stars" aria-label="{review.rating} out of 5 stars">
								{#each Array(5) as _, i}
									<span class="reviews-listicle__star" class:is-filled={i < review.rating} aria-hidden="true"
										>★</span
									>
								{/each}
							</div>
							<blockquote class="reviews-listicle__quote">
								<p>&ldquo;{review.quote}&rdquo;</p>
							</blockquote>
							<cite class="reviews-listicle__name">{review.name}</cite>
						</li>
					{/each}
				</ul>
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

	.reviews-listicle__inner {
		max-width: var(--rl-max);
		margin: 0 auto;
	}

	.reviews-listicle__headline {
		margin: 0 0 36px;
		text-align: center;
		font-family: var(--font-heading, var(--font-sans));
		font-size: clamp(24px, 3.5vw, 34px);
		font-weight: var(--heading-weight, 700);
		line-height: 1.15;
		letter-spacing: -0.02em;
		color: var(--fg);
	}

	.reviews-listicle__grid {
		list-style: none;
		margin: 0;
		padding: 0;
		display: grid;
		grid-template-columns: repeat(3, minmax(0, 1fr));
		gap: clamp(20px, 4vw, 40px);
	}

	.reviews-listicle__card {
		display: flex;
		flex-direction: column;
		align-items: center;
		text-align: center;
		margin: 0;
	}

	.reviews-listicle__stars {
		display: flex;
		justify-content: center;
		gap: 2px;
		margin: 0 0 16px;
		font-size: 15px;
		line-height: 1;
		letter-spacing: 2px;
	}
	.reviews-listicle__star {
		color: color-mix(in srgb, var(--fg) 18%, transparent);
	}
	.reviews-listicle__star.is-filled {
		color: var(--accent);
	}

	.reviews-listicle__quote {
		margin: 0;
		padding: 0;
		border: none;
		font-style: normal;
		max-width: 34ch;
	}
	.reviews-listicle__quote p {
		margin: 0;
		font-size: 15px;
		line-height: 1.65;
		color: var(--fg);
	}

	.reviews-listicle__name {
		margin: 14px 0 0;
		font-size: 14px;
		font-weight: 600;
		font-style: normal;
		color: var(--fg-muted);
	}

	@media (max-width: 800px) {
		.reviews-listicle__grid {
			grid-template-columns: 1fr;
			gap: 32px;
			max-width: 420px;
			margin: 0 auto;
		}
	}
</style>
