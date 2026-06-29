<script lang="ts">
	import type {
		ModuleResolved,
		OrderHandlingModuleConfig,
		SpacingPreset,
	} from '$lib/config.svelte';

	const STEP_VARIANTS = ['verified', 'lab', 'shipping', 'support'] as const;
	type StepVariant = (typeof STEP_VARIANTS)[number];

	let {
		config,
		resolved,
		spacing_v = 'normal',
		spacing_h = 'normal',
		center_header = true,
		embedded = false,
	}: {
		config: OrderHandlingModuleConfig;
		resolved?: ModuleResolved;
		spacing_v?: SpacingPreset;
		spacing_h?: SpacingPreset;
		center_header?: boolean;
		embedded?: boolean;
	} = $props();

	function normVariant(raw: string): StepVariant {
		const v = (raw || '').trim();
		return ((STEP_VARIANTS as readonly string[]).includes(v) ? v : 'verified') as StepVariant;
	}

	const sectionStyle = $derived.by(() => {
		const parts: string[] = [];
		if (resolved?.accent_color) parts.push(`--oh-accent: ${resolved.accent_color}`);
		const bg = config.bg_color?.trim() ?? '';
		if (/^#[0-9a-fA-F]{6}$/.test(bg)) parts.push(`background: ${bg}`);
		return parts.join('; ');
	});

	const steps = $derived(
		(config.steps ?? [])
			.map((row, i) => ({
				step: i + 1,
				variant: normVariant(row.variant),
				iconUrl: row.icon_url?.trim() || '',
				headline: row.headline?.trim() || '',
				description: row.description?.trim() || '',
			}))
			.filter((row) => row.headline !== '')
	);

	const metrics = $derived(
		(config.metrics ?? [])
			.map((m) => ({
				value: m.value?.trim() || '',
				label: m.label?.trim() || '',
			}))
			.filter((m) => m.value !== '' || m.label !== '')
	);

	const badge = $derived(config.badge_text?.trim() || '');
	const headline = $derived(config.headline?.trim() || '');
	const sub = $derived(config.subheadline?.trim() || '');
	const metricsTitle = $derived(config.metrics_title?.trim() || 'Quality Metrics');
	const showHead = $derived(Boolean(badge || headline || sub));
</script>

{#if steps.length}
	<section
		class="oh"
		class:is-embedded={embedded}
		class:is-v-compact={spacing_v === 'compact'}
		class:is-v-spacious={spacing_v === 'spacious'}
		class:is-h-compact={spacing_h === 'compact'}
		class:is-h-spacious={spacing_h === 'spacious'}
		class:is-centered={center_header}
		style={sectionStyle}
		aria-label={headline || 'Order handling'}
	>
		{#if showHead}
			<header class="oh__head">
				{#if badge}
					<p class="oh__badge">{badge}</p>
				{/if}
				{#if headline}
					<h2 class="oh__title wchs-section-heading">{headline}</h2>
				{/if}
				{#if sub}
					<p class="oh__sub">{sub}</p>
				{/if}
			</header>
		{/if}

		<div class="oh__flow">
			{#each steps as step, i (step.headline)}
				{#if i > 0}
					<div class="oh__arrow" aria-hidden="true">
						<span class="oh__arrow-line"></span>
						<span class="oh__arrow-cap">
							<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
								<path d="M9 6l6 6-6 6" />
							</svg>
						</span>
					</div>
				{/if}
				<article class="oh-card oh-card--{step.variant}">
					<span class="oh-card__num" aria-hidden="true">{step.step}</span>
					<div class="oh-card__icon" aria-hidden="true">
						{#if step.iconUrl}
							<img class="oh-card__icon-img" src={step.iconUrl} alt="" />
						{:else if step.variant === 'verified'}
							<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
								<path d="M20 6 9 17l-5-5" />
							</svg>
						{:else if step.variant === 'lab'}
							<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
								<path d="M10 2v6.5L6.5 16a4 4 0 0 0 3.5 6h4a4 4 0 0 0 3.5-6L14 8.5V2" />
								<path d="M8.5 14h7" />
							</svg>
						{:else if step.variant === 'shipping'}
							<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
								<path d="M3 8h11v8H3z" />
								<path d="M14 10h4l3 3v3h-7v-6Z" />
								<circle cx="7.5" cy="18" r="1.5" />
								<circle cx="17.5" cy="18" r="1.5" />
							</svg>
						{:else}
							<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
								<path d="M4 14a4 4 0 0 1 4-4h8a4 4 0 0 1 4 4v2H4v-2Z" />
								<path d="M12 6a2 2 0 0 1 2 2v2h-4V8a2 2 0 0 1 2-2Z" />
							</svg>
						{/if}
					</div>
					<h3 class="oh-card__title">{step.headline}</h3>
					{#if step.description}
						<p class="oh-card__desc">{step.description}</p>
					{/if}
				</article>
			{/each}
		</div>

		{#if metrics.length}
			<div class="oh__metrics">
				<p class="oh__metrics-label">{metricsTitle}</p>
				<div class="oh__metrics-grid">
					{#each metrics as m, i (i)}
						<div class="oh__metric">
							<span class="oh__metric-val">{m.value}</span>
							<span class="oh__metric-lab">{m.label}</span>
						</div>
					{/each}
				</div>
			</div>
		{/if}
	</section>
{/if}

<style>
	.oh {
		--oh-accent: var(--accent);
		--mod-pt: var(--wchs-spacing-v-normal, 48px);
		--mod-pb: var(--wchs-spacing-v-normal, 56px);
		--mod-px: 28px;
		--mod-max-w: 1180px;
		max-width: var(--mod-max-w);
		margin: 0 auto;
		padding: var(--mod-pt) var(--mod-px) var(--mod-pb);
	}
	.oh.is-v-compact {
		--mod-pt: var(--wchs-spacing-v-compact, 20px);
		--mod-pb: var(--wchs-spacing-v-compact, 24px);
	}
	.oh.is-v-spacious {
		--mod-pt: var(--wchs-spacing-v-spacious, 72px);
		--mod-pb: var(--wchs-spacing-v-spacious, 80px);
	}
	.oh.is-h-compact {
		--mod-max-w: 100%;
		--mod-px: 16px;
	}
	.oh.is-h-spacious {
		--mod-max-w: 920px;
		--mod-px: 40px;
	}

	.oh.is-embedded {
		--mod-pt: clamp(32px, 5vw, 48px);
		--mod-pb: 0;
		max-width: none;
		margin: 0;
		padding-left: 0;
		padding-right: 0;
	}

	.oh__head {
		text-align: left;
		margin-bottom: 36px;
		max-width: 52ch;
	}
	.oh.is-centered .oh__head {
		text-align: center;
		margin-left: auto;
		margin-right: auto;
	}

	.oh__badge {
		display: inline-flex;
		margin: 0 0 14px;
		padding: 6px 14px 7px;
		border-radius: 999px;
		font-size: 11px;
		font-weight: 700;
		letter-spacing: 0.1em;
		text-transform: uppercase;
		color: color-mix(in srgb, var(--oh-accent) 55%, var(--fg) 45%);
		background: color-mix(in srgb, var(--oh-accent) 12%, var(--bg) 88%);
		border: 1px solid color-mix(in srgb, var(--oh-accent) 22%, var(--border) 78%);
	}

	.oh__title {
		margin: 0 0 12px;
		max-width: none;
	}
	.oh.is-centered .oh__title {
		margin-left: auto;
		margin-right: auto;
	}

	.oh__sub {
		margin: 0;
		font-size: 15px;
		line-height: 1.6;
		color: var(--fg-muted);
	}

	.oh__flow {
		display: flex;
		align-items: stretch;
		gap: 0;
		margin-bottom: 32px;
	}

	.oh__arrow {
		flex: 0 0 28px;
		display: flex;
		align-items: center;
		justify-content: center;
		position: relative;
		align-self: center;
		margin-top: -28px;
	}
	.oh__arrow-line {
		position: absolute;
		left: 0;
		right: 0;
		top: 50%;
		height: 1px;
		background: color-mix(in srgb, var(--border) 90%, transparent);
	}
	.oh__arrow-cap {
		position: relative;
		z-index: 1;
		display: flex;
		align-items: center;
		justify-content: center;
		width: 26px;
		height: 26px;
		border-radius: 50%;
		background: var(--bg);
		border: 1px solid var(--border);
		color: var(--fg-muted);
	}

	.oh-card {
		position: relative;
		flex: 1 1 0;
		min-width: 0;
		padding: 22px 18px 20px;
		border-radius: 14px;
		background: var(--bg);
		border: 1px solid var(--border);
		box-shadow: 0 8px 24px color-mix(in srgb, black 6%, transparent);
	}

	.oh-card__num {
		position: absolute;
		top: 14px;
		right: 14px;
		width: 26px;
		height: 26px;
		display: flex;
		align-items: center;
		justify-content: center;
		border-radius: 50%;
		font-size: 12px;
		font-weight: 700;
		color: var(--fg-muted);
		background: var(--bg-muted);
	}

	.oh-card__icon {
		display: flex;
		align-items: center;
		justify-content: center;
		width: 48px;
		height: 48px;
		border-radius: 12px;
		margin-bottom: 16px;
	}
	.oh-card__icon-img {
		width: 28px;
		height: 28px;
		object-fit: contain;
	}

	.oh-card--verified .oh-card__icon {
		color: hsl(152 55% 36%);
		background: hsl(152 48% 94%);
	}
	.oh-card--lab .oh-card__icon {
		color: hsl(210 72% 42%);
		background: hsl(210 80% 95%);
	}
	.oh-card--shipping .oh-card__icon {
		color: hsl(28 85% 45%);
		background: hsl(32 90% 94%);
	}
	.oh-card--support .oh-card__icon {
		color: hsl(270 55% 48%);
		background: hsl(270 60% 96%);
	}

	.oh-card__title {
		margin: 0 0 10px;
		padding-right: 28px;
		font-family: var(--font-heading, var(--font-sans));
		font-size: 15px;
		font-weight: 700;
		line-height: 1.3;
		letter-spacing: -0.02em;
		color: var(--fg);
	}

	.oh-card__desc {
		margin: 0;
		font-size: 13px;
		line-height: 1.55;
		color: var(--fg-muted);
	}

	.oh__metrics {
		padding: 28px 24px 26px;
		border-radius: 14px;
		border: 1px solid var(--border);
		background: color-mix(in srgb, var(--bg-muted) 55%, var(--bg) 45%);
	}

	.oh__metrics-label {
		margin: 0 0 20px;
		text-align: center;
		font-size: 11px;
		font-weight: 700;
		letter-spacing: 0.12em;
		text-transform: uppercase;
		color: var(--fg-muted);
	}

	.oh__metrics-grid {
		display: grid;
		grid-template-columns: repeat(3, minmax(0, 1fr));
		gap: 16px;
	}

	.oh__metric {
		text-align: center;
		padding: 8px 12px;
	}

	.oh__metric-val {
		display: block;
		margin-bottom: 6px;
		font-size: clamp(1.75rem, 3vw, 2.25rem);
		font-weight: 800;
		letter-spacing: -0.03em;
		line-height: 1;
		color: var(--oh-accent);
	}

	.oh__metric-lab {
		font-size: 13px;
		font-weight: 500;
		color: var(--fg-muted);
	}

	@media (max-width: 960px) {
		.oh__flow {
			flex-direction: column;
			gap: 14px;
		}
		.oh__arrow {
			display: none;
		}
		.oh-card {
			flex: none;
			width: 100%;
		}
		.oh__metrics-grid {
			grid-template-columns: 1fr;
			gap: 20px;
		}
	}

	@media (max-width: 640px) {
		.oh__metrics {
			padding: 22px 16px 20px;
		}
	}
</style>
