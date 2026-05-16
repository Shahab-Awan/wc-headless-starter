<script lang="ts">
	import BrandComparisonTable from '$lib/components/BrandComparisonTable.svelte';
	import {
		config as siteCfg,
		type ModuleResolved,
		type SplitFeaturesModuleConfig,
		type SpacingPreset,
	} from '$lib/config.svelte';

	let {
		config,
		resolved,
		spacing_v = 'normal',
		spacing_h = 'normal',
		center_header = false,
	}: {
		config: SplitFeaturesModuleConfig;
		resolved?: ModuleResolved;
		spacing_v?: SpacingPreset;
		spacing_h?: SpacingPreset;
		center_header?: boolean;
	} = $props();

	const layout = $derived.by((): 'alternating' | 'comparison' => {
		const raw = config.layout;
		if (raw === 'comparison' || raw === 'alternating') return raw;
		const t = (config.title || '').trim().toLowerCase();
		if (/why\s*choose|why\s*alyve/.test(t)) return 'comparison';
		return 'alternating';
	});

	const compareRows = $derived(
		(config.items ?? [])
			.map((row) => row.heading?.trim() || '')
			.filter((line) => line !== '')
	);

	const displayHeadline = $derived((config.headline?.trim() || config.title?.trim() || '').trim());
	const eyebrow = $derived(config.headline?.trim() ? config.title?.trim() || '' : '');
	const subtitlePlain = $derived((config.subtitle ?? '').trim());
	const brandName = $derived(
		(config.brand_name?.trim() || siteCfg.data.brand_name?.trim() || 'Our brand').trim()
	);
	const competitorName = $derived((config.competitor_name?.trim() || 'Unverified Sellers').trim());
	const brandLogo = $derived(config.brand_logo?.trim() || '');
	const competitorLogo = $derived(config.competitor_logo?.trim() || '');

	const showComparison = $derived(layout === 'comparison' && compareRows.length > 0);
	const showAlternating = $derived(!showComparison && (config.items?.length ?? 0) > 0);
</script>

{#if showComparison}
	<BrandComparisonTable
		spacing_v={spacing_v}
		spacing_h={spacing_h}
		center_header={center_header}
		resolved={resolved}
		eyebrow={eyebrow}
		title={displayHeadline}
		subtitleText={subtitlePlain}
		brandName={brandName}
		competitorName={competitorName}
		brandLogo={brandLogo}
		competitorLogo={competitorLogo}
		compareRows={compareRows}
	/>
{:else if showAlternating}
	<section class="split" class:is-v-compact={spacing_v === 'compact'} class:is-v-spacious={spacing_v === 'spacious'} class:is-h-compact={spacing_h === 'compact'} class:is-h-spacious={spacing_h === 'spacious'}>
		{#if config.title}
			<p class="split__label wchs-section-heading" class:is-centered={center_header}>{config.title}</p>
		{/if}
		<div class="split__list">
			{#each config.items as item, i}
				<div class="split__row" class:split__row--reverse={i % 2 !== 0}>
					<div class="split__media">
						<img src={item.image} alt={item.heading} loading="lazy" draggable="false" />
					</div>
					<div class="split__text">
						{#if item.eyebrow}
							<span class="split__eyebrow">{item.eyebrow}</span>
						{/if}
						<h3 class="split__heading">{item.heading}</h3>
						{#if item.description}
							<div class="split__desc">{@html item.description}</div>
						{/if}
					</div>
				</div>
			{/each}
		</div>
	</section>
{/if}

<style>
	.split {
		--mod-pt: 20px;
		--mod-pb: 48px;
		--mod-px: 24px;
		--mod-max-w: 1440px;
		max-width: var(--mod-max-w);
		margin: 0 auto;
		padding: var(--mod-pt) var(--mod-px) var(--mod-pb);
	}
	.split.is-v-compact {
		--mod-pt: 12px;
		--mod-pb: 12px;
	}
	.split.is-v-spacious {
		--mod-pt: 56px;
		--mod-pb: 64px;
	}
	.split.is-h-compact {
		--mod-max-w: 100%;
		--mod-px: 12px;
	}
	.split.is-h-spacious {
		--mod-max-w: 760px;
		--mod-px: 40px;
	}
	.split__label {
		margin: 0 0 28px;
	}

	.split__label.is-centered {
		text-align: center;
	}
	.split__list {
		display: flex;
		flex-direction: column;
		gap: 0;
	}
	.split__row {
		display: flex;
		align-items: stretch;
		gap: 0;
	}
	.split__row--reverse {
		flex-direction: row-reverse;
	}
	.split__media {
		flex: 1 1 50%;
		min-height: 320px;
		overflow: hidden;
	}
	.split__media img {
		width: 100%;
		height: 100%;
		object-fit: cover;
		display: block;
		user-select: none;
		-webkit-user-drag: none;
	}
	.split__text {
		flex: 1 1 50%;
		display: flex;
		flex-direction: column;
		justify-content: center;
		padding: 48px 56px;
	}
	.split__eyebrow {
		font-size: 11px;
		font-weight: 600;
		text-transform: uppercase;
		letter-spacing: 0.1em;
		color: var(--fg-muted);
		margin-bottom: 12px;
	}
	.split__heading {
		font-family: var(--font-heading, var(--font-sans));
		font-size: clamp(24px, 3vw, 34px);
		font-weight: var(--heading-weight, 600);
		letter-spacing: -0.025em;
		line-height: 1.12;
		color: var(--fg);
		margin: 0 0 16px;
	}
	.split__desc {
		font-size: 14px;
		line-height: 1.65;
		color: var(--fg-muted);
		margin: 0;
		max-width: 44ch;
	}
	.split__desc :global(p) {
		margin: 0 0 10px;
	}
	.split__desc :global(p:last-child) {
		margin-bottom: 0;
	}
	.split__desc :global(a) {
		color: var(--accent);
		text-decoration: underline;
		text-underline-offset: 2px;
	}
	.split__desc :global(strong) {
		font-weight: 700;
	}
	.split__desc :global(em) {
		font-style: italic;
	}
	.split__desc :global(ul),
	.split__desc :global(ol) {
		padding-left: 24px;
		margin: 0 0 10px;
	}

	@media (max-width: 860px) {
		.split__row,
		.split__row--reverse {
			flex-direction: column;
		}
		.split__media {
			min-height: 240px;
			max-height: 320px;
		}
		.split__text {
			padding: 28px 24px;
		}
	}
</style>
