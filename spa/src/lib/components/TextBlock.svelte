<script lang="ts">
	import BrandComparisonTable from '$lib/components/BrandComparisonTable.svelte';
	import {
		DEFAULT_WHY_ALYVE_COMPARE_ROWS,
		type BrandComparisonRow,
	} from '$lib/brand-comparison';
	import {
		config as siteCfg,
		type ModuleResolved,
		type TextBlockModuleConfig,
		type SpacingPreset,
	} from '$lib/config.svelte';

	let {
		config,
		resolved,
		spacing_v = 'normal',
		spacing_h = 'normal',
		center_header = false,
	}: {
		config: TextBlockModuleConfig;
		resolved?: ModuleResolved;
		spacing_v?: SpacingPreset;
		spacing_h?: SpacingPreset;
		center_header?: boolean;
	} = $props();

	const TITLE_COMPARE_HINT = /why\s*alyve|why choose/i;

	const layoutMode = $derived((config.layout ?? 'auto') as 'auto' | 'standard' | 'comparison');
	const titleTrim = $derived((config.title ?? '').trim());
	const hint = $derived(TITLE_COMPARE_HINT.test(titleTrim.toLowerCase()));

	const rowsFromConfig = $derived.by((): BrandComparisonRow[] => {
		return (config.comparison_rows ?? [])
			.map((r) => ({
				feature: r.heading?.trim() ?? '',
				brand: r.brand?.trim() ?? '',
				competitor: r.competitor?.trim() ?? '',
				competitor_2: r.competitor_2?.trim() ?? '',
			}))
			.filter((row) => row.feature !== '');
	});

	const forcedStandard = $derived(layoutMode === 'standard');
	const forcedComparison = $derived(layoutMode === 'comparison');

	const compareRows = $derived.by((): BrandComparisonRow[] => {
		if (rowsFromConfig.length > 0) return rowsFromConfig;
		if (forcedStandard) return [];
		if (forcedComparison || hint) return DEFAULT_WHY_ALYVE_COMPARE_ROWS;
		return [];
	});

	const showComparison = $derived(
		!forcedStandard &&
			compareRows.length > 0 &&
			(forcedComparison || hint || rowsFromConfig.length > 0)
	);

	const brandName = $derived(
		(config.brand_name?.trim() || siteCfg.data.brand_name?.trim() || 'Alyve').trim()
	);
	const competitorName = $derived(
		(config.competitor_name?.trim() || 'Generic Peptide Sites').trim()
	);
	const competitorName2 = $derived(
		(config.competitor_name_2?.trim() || 'Overseas / Grey-Market').trim()
	);
	const brandLogo = $derived(config.brand_logo?.trim() || '');
	const competitorLogo = $derived(config.competitor_logo?.trim() || '');

	const compareEyebrow = $derived(titleTrim || '');
	const compareTitle = $derived((config.headline?.trim() || '').trim());
	const compareLeadHtml = $derived((config.content ?? '').trim());

	const comparisonHeaderCentered = $derived(
		center_header ||
			(!compareTitle && (!!compareLeadHtml || !!compareEyebrow))
	);
</script>

{#if showComparison}
	<BrandComparisonTable
		spacing_v={spacing_v}
		spacing_h={spacing_h}
		center_header={comparisonHeaderCentered}
		resolved={resolved}
		eyebrow={compareEyebrow || undefined}
		title={compareTitle || undefined}
		subtitleHtml={compareLeadHtml}
		brandName={brandName}
		competitorName={competitorName}
		competitorName2={competitorName2}
		brandLogo={brandLogo}
		competitorLogo={competitorLogo}
		compareRows={compareRows}
	/>
{:else if config.content}
	<section class="text-block" class:is-v-compact={spacing_v === 'compact'} class:is-v-spacious={spacing_v === 'spacious'} class:is-h-compact={spacing_h === 'compact'} class:is-h-spacious={spacing_h === 'spacious'} class:is-centered={center_header}>
		{#if config.title}
			<h2 class="text-block__label wchs-section-heading" class:is-centered={center_header}>{config.title}</h2>
		{/if}
		<div class="text-block__content" class:is-centered={center_header}>
			{@html config.content}
		</div>
	</section>
{/if}

<style>
	.text-block {
		--mod-pt: 24px;
		--mod-pb: 24px;
		--mod-px: 28px;
		--mod-max-w: 960px;
		max-width: var(--mod-max-w);
		margin: 0 auto;
		padding: var(--mod-pt) var(--mod-px) var(--mod-pb);
	}
	.text-block.is-v-compact  { --mod-pt: 12px; --mod-pb: 12px; }
	.text-block.is-v-spacious { --mod-pt: 56px; --mod-pb: 64px; }
	.text-block.is-h-compact  { --mod-max-w: 100%; --mod-px: 12px; }
	.text-block.is-h-spacious { --mod-max-w: 760px; --mod-px: 40px; }
	.text-block__label {
		margin: 0 0 20px;
	}
	.text-block__label.is-centered {
		text-align: center;
	}

	.text-block__content {
		word-wrap: break-word;
	}

	/* When `center_header` is on, center the whole block — label, content,
	   and narrow the measure so long paragraphs don't stretch full width. */
	.text-block.is-centered {
		text-align: center;
	}
	.text-block__content.is-centered {
		text-align: center;
		max-width: 60ch;
		margin-left: auto;
		margin-right: auto;
	}

	.text-block__content :global(h1) {
		font-family: var(--font-heading, var(--font-sans));
		font-size: clamp(32px, 5vw, 48px);
		font-weight: var(--heading-weight, 600);
		letter-spacing: -0.03em;
		line-height: 1.08;
		color: var(--fg);
		margin: 0 0 20px;
	}
	.text-block__content :global(h2) {
		font-family: var(--font-heading, var(--font-sans));
		font-size: clamp(24px, 4vw, 36px);
		font-weight: var(--heading-weight, 600);
		letter-spacing: -0.025em;
		line-height: 1.15;
		color: var(--fg);
		margin: 0 0 16px;
	}
	.text-block__content :global(h3) {
		font-family: var(--font-heading, var(--font-sans));
		font-size: clamp(18px, 3vw, 24px);
		font-weight: var(--heading-weight, 600);
		letter-spacing: -0.015em;
		line-height: 1.25;
		color: var(--fg);
		margin: 0 0 12px;
	}
	.text-block__content :global(h4) {
		font-size: 13px;
		font-weight: 600;
		text-transform: uppercase;
		letter-spacing: 0.08em;
		color: var(--fg);
		margin: 0 0 10px;
	}
	.text-block__content :global(p) {
		font-size: 15px;
		line-height: 1.65;
		color: var(--fg);
		margin: 0 0 14px;
	}
	.text-block__content :global(p:last-child) {
		margin-bottom: 0;
	}
	.text-block__content :global(a) {
		color: var(--accent);
		text-decoration: underline;
		text-underline-offset: 2px;
	}
	.text-block__content :global(a:hover) {
		opacity: 0.8;
	}
	.text-block__content :global(strong) {
		font-weight: 700;
	}
	.text-block__content :global(em) {
		font-style: italic;
	}
	.text-block__content :global(ul),
	.text-block__content :global(ol) {
		padding-left: 24px;
		margin: 0 0 14px;
		font-size: 15px;
		line-height: 1.65;
		color: var(--fg);
	}
	.text-block__content :global(li) {
		margin-bottom: 4px;
	}
	.text-block__content :global(blockquote) {
		border-left: 3px solid var(--fg-muted);
		padding: 0 0 0 20px;
		margin: 14px 0;
		font-style: italic;
		color: var(--fg-muted);
	}
	.text-block__content :global(hr) {
		border: 0;
		border-top: 1px solid var(--border);
		margin: 24px 0;
	}

	/* Alignment classes from TinyMCE */
	.text-block__content :global(.alignleft),
	.text-block__content :global([style*="text-align: left"]) {
		text-align: left;
	}
	.text-block__content :global(.aligncenter),
	.text-block__content :global([style*="text-align: center"]) {
		text-align: center;
	}
	.text-block__content :global(.alignright),
	.text-block__content :global([style*="text-align: right"]) {
		text-align: right;
	}
</style>
