<script lang="ts">
	import { HERO_FONTS } from '$lib/hero-fonts';
	import {
		resolveHeroPrecision,
		type HeroPrecisionConfig,
	} from '$lib/hero-precision';
	import type { ModuleResolved, PriceComparisonModuleConfig } from '$lib/config.svelte';
	import PriceComparison from '$lib/components/PriceComparison.svelte';

	let {
		precision,
		headline_font,
		headline_weight,
		resolved,
		priceComparison,
	}: {
		precision?: Partial<HeroPrecisionConfig> | null;
		headline_font?: string;
		headline_weight?: string;
		resolved?: ModuleResolved;
		priceComparison?: PriceComparisonModuleConfig | null;
	} = $props();

	const p = $derived(resolveHeroPrecision(precision));
	const usePriceCard = $derived(p.visual === 'price_comparison' && !!priceComparison);
	const headlinePrimary = $derived(usePriceCard ? 'Half the price.' : p.headline_primary);
	const headlineAccent = $derived(usePriceCard ? 'Triple the testing.' : p.headline_accent);

	const accentStyle = $derived(resolved?.accent_color ? `--accent: ${resolved.accent_color};` : '');

	const headlineFontFamily = $derived(
		HERO_FONTS[(headline_font ?? 'inter') as keyof typeof HERO_FONTS]?.family ??
			HERO_FONTS.inter.family
	);

	const WEIGHT_MAP: Record<string, string> = {
		light: '300',
		regular: '400',
		medium: '500',
		semibold: '600',
		bold: '700',
		extrabold: '800',
		black: '900',
	};
	const headlineWt = $derived(WEIGHT_MAP[headline_weight ?? 'semibold'] ?? '600');
</script>

<section class="hero-precision" style={accentStyle}>
	<div class="hero-precision__inner">
		<div class="hero-precision__copy">
			<div class="hero-precision__lead">
				{#if p.badge.trim()}
					<p class="hero-precision__badge">
						<span class="hero-precision__badge-dot" aria-hidden="true"></span>
						{p.badge.trim()}
					</p>
				{/if}

				<h1
					class="hero-precision__title"
					style="font-family: {headlineFontFamily}; font-weight: {headlineWt};"
				>
					<span>{headlinePrimary}</span>
					{#if headlineAccent.trim()}
						<span class="hero-precision__title-accent">{headlineAccent}</span>
					{/if}
				</h1>

				<div class="hero-precision__trust">
					{#if p.rating_label.trim()}
						<div class="hero-precision__trust-rating">
							<div class="hero-precision__rating-row">
								<span class="hero-precision__stars" aria-hidden="true">
									{#each Array(5) as _}
										<span class="hero-precision__star">
											<svg viewBox="0 0 24 24" width="11" height="11" fill="#fff" stroke="none">
												<polygon
													points="12,2 15.09,8.26 22,9.27 17,14.14 18.18,21.02 12,17.77 5.82,21.02 7,14.14 2,9.27 8.91,8.26"
												/>
											</svg>
										</span>
									{/each}
								</span>
								<div class="hero-precision__rating-copy">
									<strong class="hero-precision__rating-label">{p.rating_label}</strong>
									<span class="hero-precision__trust-sub">{p.rating_subtext}</span>
								</div>
							</div>
						</div>
					{/if}
					<div class="hero-precision__trust-stat">
						<strong>{p.stat_2_value}</strong>
						<span class="hero-precision__trust-sub">{p.stat_2_label}</span>
					</div>
					<div class="hero-precision__trust-stat">
						<strong>{p.stat_3_value}</strong>
						<span class="hero-precision__trust-sub">{p.stat_3_label}</span>
					</div>
				</div>
			</div>

			<div class="hero-precision__rest">
				{#if p.body.trim()}
					<p class="hero-precision__body">{p.body}</p>
				{/if}

				<div class="hero-precision__ctas">
					{#if p.cta_primary_text.trim()}
						<a href={p.cta_primary_link || '/shop'} class="hero-precision__cta hero-precision__cta--primary">
							{p.cta_primary_text}
						</a>
					{/if}
					{#if p.cta_secondary_text.trim()}
						<a
							href={p.cta_secondary_link || '/coa-library'}
							class="hero-precision__cta hero-precision__cta--secondary"
						>
							{p.cta_secondary_text}
						</a>
					{/if}
				</div>
			</div>
		</div>

		<div
			class="hero-precision__visual"
			class:hero-precision__visual--card={usePriceCard}
			aria-hidden={!usePriceCard && !p.image_desktop}
		>
			{#if usePriceCard && priceComparison}
				<PriceComparison config={priceComparison} resolved={resolved} cardOnly />
			{:else if p.image_desktop}
				<picture>
					{#if p.image_mobile}
						<source media="(max-width: 639px)" srcset={p.image_mobile} />
					{/if}
					<img src={p.image_desktop} alt="" loading="eager" />
				</picture>
			{/if}
		</div>
	</div>
</section>

<style>
	.hero-precision {
		--hp-surface: color-mix(in srgb, var(--accent) 7%, var(--bg));
		--hp-surface-border: color-mix(in srgb, var(--accent) 14%, var(--border));
		padding: 12px clamp(20px, 4vw, 40px) clamp(28px, 4vh, 48px);
		background: var(--bg);
		color: var(--fg);
	}

	.hero-precision__inner {
		max-width: 1280px;
		margin: 0 auto;
		display: grid;
		grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
		grid-template-areas: 'copy visual';
		column-gap: clamp(24px, 4vw, 48px);
		align-items: stretch;
	}

	.hero-precision__copy {
		grid-area: copy;
		display: flex;
		flex-direction: column;
		justify-content: center;
		gap: 22px;
		min-width: 0;
		min-height: 0;
	}

	.hero-precision__lead {
		min-width: 0;
	}

	.hero-precision__rest {
		min-width: 0;
	}

	.hero-precision__badge {
		display: inline-flex;
		align-items: center;
		gap: 8px;
		margin: 0 0 14px;
		padding: 8px 14px;
		border-radius: 999px;
		border: 1px solid var(--hp-surface-border);
		background: var(--hp-surface);
		font-size: 10px;
		font-weight: 700;
		letter-spacing: 0.1em;
		text-transform: uppercase;
		color: color-mix(in srgb, var(--accent) 70%, var(--fg));
	}

	.hero-precision__badge-dot {
		width: 7px;
		height: 7px;
		border-radius: 50%;
		background: var(--accent);
		flex-shrink: 0;
	}

	.hero-precision__title {
		margin: 0 0 22px;
		display: flex;
		flex-direction: column;
		gap: 0.1em;
		font-size: clamp(2.15rem, 4.8vw, 3.5rem);
		line-height: 1.05;
		letter-spacing: -0.03em;
		color: var(--fg);
	}

	.hero-precision__title-accent {
		color: var(--accent);
	}

	.hero-precision__trust {
		--hp-trust-border: color-mix(in srgb, var(--border) 90%, transparent);
		--hp-trust-box-bg: color-mix(in srgb, var(--fg) 4%, var(--bg));
		display: flex;
		align-items: stretch;
		flex-wrap: nowrap;
		gap: clamp(14px, 2vw, 22px);
		margin: 0;
	}

	.hero-precision__trust-rating {
		flex: 0 1 auto;
		min-width: 0;
		display: flex;
		align-items: center;
		padding: 6px 10px;
		border-radius: 10px;
		border: 1px solid var(--hp-trust-border);
		background: var(--hp-trust-box-bg);
		box-sizing: border-box;
	}

	.hero-precision__trust-stat {
		display: flex;
		flex-direction: column;
		justify-content: center;
		gap: 3px;
		flex: 0 1 auto;
		min-width: 0;
		padding: 8px clamp(14px, 2vw, 20px);
		border-left: 1px solid var(--hp-trust-border);
		box-sizing: border-box;
	}

	.hero-precision__trust-stat strong {
		font-size: clamp(0.95rem, 1.75vw, 1.2rem);
		font-weight: 800;
		line-height: 1.1;
		letter-spacing: -0.02em;
		color: var(--fg);
	}

	.hero-precision__trust-sub {
		font-size: 11px;
		line-height: 1.3;
		color: var(--fg-muted);
	}

	.hero-precision__rating-row {
		display: flex;
		align-items: center;
		gap: 8px;
		min-width: 0;
		width: 100%;
	}

	.hero-precision__rating-copy {
		display: flex;
		flex-direction: column;
		gap: 2px;
		min-width: 0;
		flex: 1 1 auto;
	}

	.hero-precision__rating-label {
		font-size: clamp(0.85rem, 1.5vw, 1.05rem);
		font-weight: 800;
		line-height: 1.1;
		letter-spacing: -0.02em;
		color: var(--fg);
	}

	.hero-precision__trust-rating .hero-precision__trust-sub {
		font-size: 10px;
		line-height: 1.25;
	}

	.hero-precision__stars {
		display: inline-flex;
		gap: 2px;
		flex-shrink: 0;
	}

	.hero-precision__star {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		width: 16px;
		height: 16px;
		border-radius: 3px;
		background: #00b67a;
		color: #fff;
		flex-shrink: 0;
	}

	.hero-precision__star svg {
		width: 9px;
		height: 9px;
	}

	.hero-precision__body {
		margin: 0 0 26px;
		max-width: 54ch;
		font-size: clamp(0.95rem, 1.6vw, 1.05rem);
		line-height: 1.6;
		color: var(--fg-muted);
	}

	.hero-precision__ctas {
		display: flex;
		flex-wrap: wrap;
		gap: 12px;
	}

	.hero-precision__cta {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		min-height: 48px;
		padding: 12px 24px;
		border-radius: 10px;
		font-size: 14px;
		font-weight: 600;
		text-decoration: none;
		transition:
			background var(--dur-fast) var(--ease),
			border-color var(--dur-fast) var(--ease),
			color var(--dur-fast) var(--ease),
			opacity var(--dur-fast) var(--ease);
	}

	.hero-precision__cta--primary {
		background: var(--accent);
		color: var(--accent-fg);
		border: 1px solid color-mix(in srgb, var(--accent) 85%, black 15%);
	}

	.hero-precision__cta--primary:hover {
		opacity: 0.92;
	}

	.hero-precision__cta--secondary {
		background: var(--bg);
		color: var(--fg);
		border: 1px solid color-mix(in srgb, var(--fg) 22%, var(--border));
	}

	.hero-precision__cta--secondary:hover {
		border-color: var(--accent);
		color: var(--accent);
	}

	.hero-precision__visual {
		grid-area: visual;
		align-self: stretch;
		position: relative;
		width: 100%;
		min-height: clamp(320px, 42vw, 520px);
		aspect-ratio: 1;
		max-width: min(100%, 560px);
		margin-inline: auto;
		border-radius: 20px;
		border: 1px solid var(--hp-surface-border);
		background-color: var(--hp-surface);
		background-image:
			linear-gradient(color-mix(in srgb, var(--accent) 6%, transparent) 1px, transparent 1px),
			linear-gradient(90deg, color-mix(in srgb, var(--accent) 6%, transparent) 1px, transparent 1px);
		background-size: 28px 28px;
		overflow: hidden;
	}

	.hero-precision__visual--card {
		aspect-ratio: auto;
		min-height: 0;
		max-width: min(100%, 500px);
		border: 0;
		background: transparent;
		background-image: none;
		overflow: visible;
		display: flex;
		align-items: center;
		justify-content: center;
	}

	.hero-precision__visual picture,
	.hero-precision__visual img {
		display: block;
		width: 100%;
		height: 100%;
		object-fit: contain;
		object-position: center;
	}

	@media (min-width: 961px) {
		.hero-precision {
			padding-top: 24px;
		}

		.hero-precision__inner {
			align-items: stretch;
		}

		.hero-precision__copy {
			justify-content: center;
		}
	}

	@media (max-width: 960px) {
		.hero-precision {
			padding-top: 22px;
			padding-bottom: clamp(36px, 6vh, 60px);
		}

		.hero-precision__badge {
			margin-bottom: 16px;
		}

		.hero-precision__title {
			margin-bottom: 20px;
		}

		.hero-precision__inner {
			grid-template-columns: 1fr;
			grid-template-areas:
				'lead'
				'rest'
				'visual';
			row-gap: clamp(24px, 6vw, 36px);
			align-items: stretch;
		}

		.hero-precision__copy {
			display: contents;
		}

		.hero-precision__lead {
			grid-area: lead;
			display: flex;
			flex-direction: column;
			align-items: center;
			text-align: center;
		}

		.hero-precision__title {
			align-items: center;
			text-align: center;
		}

		.hero-precision__trust {
			width: 100%;
			align-self: stretch;
		}

		.hero-precision__rest {
			grid-area: rest;
		}

		.hero-precision__visual {
			grid-area: visual;
		}

		.hero-precision__visual:not(.hero-precision__visual--card) {
			align-self: stretch;
			min-height: clamp(280px, 72vw, 420px);
		}

		.hero-precision__visual--card {
			min-height: 0;
		}

		.hero-precision__body {
			max-width: none;
			text-align: center;
			margin-inline: auto;
		}

		.hero-precision__ctas {
			flex-direction: column;
		}

		.hero-precision__cta {
			width: 100%;
		}
	}

	@media (max-width: 640px) {
		.hero-precision {
			padding-top: 24px;
		}

		.hero-precision__badge {
			margin-bottom: 18px;
		}

		.hero-precision__title {
			margin-bottom: 22px;
		}

		.hero-precision__rest {
			display: flex;
			flex-direction: column;
			gap: 20px;
		}

		.hero-precision__trust {
			display: grid;
			grid-template-columns: repeat(3, minmax(0, 1fr));
			align-items: stretch;
			gap: 0;
			border: 1px solid var(--hp-trust-border);
			border-radius: 10px;
			overflow: hidden;
			background: var(--bg);
		}

		.hero-precision__trust-rating {
			flex: unset;
			min-width: 0;
			padding: 10px 6px;
			border: 0;
			border-radius: 0;
			background: transparent;
			overflow: hidden;
			justify-content: center;
			align-items: center;
		}

		.hero-precision__trust-stat {
			flex: unset;
			min-width: 0;
			align-items: center;
			text-align: center;
			padding: 10px 6px;
			border-left: 1px solid var(--hp-trust-border);
			overflow: hidden;
		}

		.hero-precision__rating-row {
			flex-direction: column;
			align-items: center;
			text-align: center;
			gap: 4px;
			width: 100%;
		}

		.hero-precision__rating-copy {
			align-items: center;
			width: 100%;
		}

		.hero-precision__stars {
			justify-content: center;
			gap: 2px;
		}

		.hero-precision__rating-label {
			font-size: clamp(0.68rem, 2.6vw, 0.78rem);
			line-height: 1.1;
		}

		.hero-precision__trust-rating .hero-precision__trust-sub {
			font-size: clamp(8px, 2.1vw, 9px);
		}

		.hero-precision__trust-stat strong {
			font-size: clamp(0.68rem, 2.6vw, 0.78rem);
			white-space: nowrap;
		}

		.hero-precision__trust-sub {
			font-size: clamp(8px, 2.1vw, 9px);
			line-height: 1.25;
			display: -webkit-box;
			-webkit-box-orient: vertical;
			-webkit-line-clamp: 2;
			overflow: hidden;
			max-width: 100%;
		}

		.hero-precision__star {
			width: 13px;
			height: 13px;
			border-radius: 2px;
		}

		.hero-precision__star svg {
			width: 8px;
			height: 8px;
		}
	}
</style>
