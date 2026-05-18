<script lang="ts">
	import type {
		FeatureHighlightsModuleConfig,
		ModuleResolved,
		SpacingPreset,
	} from '$lib/config.svelte';

	let {
		config,
		resolved,
		spacing_v = 'normal',
		spacing_h = 'normal',
	}: {
		config: FeatureHighlightsModuleConfig;
		resolved?: ModuleResolved;
		spacing_v?: SpacingPreset;
		spacing_h?: SpacingPreset;
	} = $props();

	const VARIANTS = ['pin', 'star', 'lab', 'award'] as const;

	function normVariant(raw: string): (typeof VARIANTS)[number] {
		const v = (raw || '').trim();
		return ((VARIANTS as readonly string[]).includes(v) ? v : 'pin') as (typeof VARIANTS)[number];
	}

	const accentStyle = $derived(resolved?.accent_color ? `--fh-accent: ${resolved.accent_color};` : '');
	const items = $derived(
		(config.items ?? [])
			.map((row) => ({
				variant: normVariant(row.variant),
				headline: row.headline?.trim() || '',
				description: row.description?.trim() || '',
			}))
			.filter((row) => row.headline !== '')
	);
	const badgeText = $derived(config.badge_text?.trim() || '');
	const prefix = $derived(config.headline_prefix?.trim() || '');
	const accentWord = $derived(config.headline_accent?.trim() || '');
	const sub = $derived(config.subheadline?.trim() || '');
</script>

{#if items.length}
	<section
		class="fh"
		class:is-v-compact={spacing_v === 'compact'}
		class:is-v-spacious={spacing_v === 'spacious'}
		class:is-h-compact={spacing_h === 'compact'}
		class:is-h-spacious={spacing_h === 'spacious'}
		style={accentStyle}
		aria-label={prefix || accentWord ? `${prefix}${accentWord}` : 'Highlights'}
	>
		<div class="fh__inner">
			{#if badgeText}
				<p class="fh__badge">
					<span class="fh__badge-ico" aria-hidden="true">
						<svg viewBox="0 0 16 16" width="14" height="14">
							<path
								fill="currentColor"
								d="M6.5 12 2 7.5l1.4-1.4L6.5 9.2 12.6 3 14 4.4z"
							/>
						</svg>
					</span>
					{badgeText}
				</p>
			{/if}

			{#if prefix || accentWord}
				<h2 class="fh__title">
					{#if prefix}<span class="fh__title-pre">{prefix}</span>{/if}
					{#if accentWord}<span class="fh__title-accent">{accentWord}</span>{/if}
				</h2>
			{/if}

			{#if sub}
				<p class="fh__sub">{sub}</p>
			{/if}

			<ul class="fh__grid">
				{#each items as item}
					<li class="fh-card fh-card--{item.variant}">
						<div class="fh-card__icon" aria-hidden="true">
							{#if item.variant === 'pin'}
								<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.85" stroke-linecap="round" stroke-linejoin="round">
									<path d="M12 21s7-4.8 7-11a7 7 0 1 0-14 0c0 6.2 7 11 7 11z" />
									<circle cx="12" cy="10" r="2.5" />
								</svg>
							{:else if item.variant === 'star'}
								<svg viewBox="0 0 24 24" width="22" height="22">
									<path
										d="M12 3.6l2.3 4.9 5.4.8-3.9 3.8.9 5.3-4.7-2.5-4.8 2.5.9-5.3L4.2 9.3l5.4-.8L12 3.6z"
										fill="currentColor"
									/>
								</svg>
							{:else if item.variant === 'lab'}
								<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.85" stroke-linecap="round" stroke-linejoin="round">
									<path d="M9 4.8h6M10.2 4.8v4.3L6.5 16a3.5 3.5 0 0 0 3.1 5.2h4.8a3.5 3.5 0 0 0 3.1-5.2l-3.7-6.9V4.8" />
									<path d="M9.1 14.6h5.8" />
								</svg>
							{:else}
								<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.85" stroke-linecap="round" stroke-linejoin="round">
									<circle cx="12" cy="9" r="5.5" />
									<path d="M8.5 13.5 7 22l5-3 5 3-1.5-8.5" />
								</svg>
							{/if}
						</div>
						<h3 class="fh-card__title">{item.headline}</h3>
						{#if item.description}
							<p class="fh-card__desc">{item.description}</p>
						{/if}
					</li>
				{/each}
			</ul>
		</div>
	</section>
{/if}

<style>
	.fh {
		--fh-accent: var(--accent);
		--mod-pt: 72px;
		--mod-pb: 80px;
		--mod-px: 28px;
		--mod-max-w: 1180px;
		max-width: var(--mod-max-w);
		margin: 0 auto;
		padding: var(--mod-pt) var(--mod-px) var(--mod-pb);
		background: transparent;
	}
	.fh.is-v-compact {
		--mod-pt: 44px;
		--mod-pb: 48px;
	}
	.fh.is-v-spacious {
		--mod-pt: 96px;
		--mod-pb: 104px;
	}
	.fh.is-h-compact {
		--mod-max-w: 100%;
		--mod-px: 16px;
	}
	.fh.is-h-spacious {
		--mod-max-w: 920px;
		--mod-px: 36px;
	}

	.fh__inner {
		display: flex;
		flex-direction: column;
		align-items: center;
		text-align: center;
		gap: 18px;
	}

	.fh__badge {
		display: inline-flex;
		align-items: center;
		gap: 8px;
		margin: 0;
		padding: 6px 14px 7px;
		border-radius: 999px;
		font-size: 12px;
		font-weight: 700;
		letter-spacing: 0.06em;
		text-transform: uppercase;
		color: color-mix(in srgb, var(--fh-accent) 42%, var(--fg) 58%);
		background: color-mix(in srgb, var(--fh-accent) 14%, var(--bg) 86%);
		border: 1px solid color-mix(in srgb, var(--fh-accent) 22%, transparent);
	}
	.fh__badge-ico {
		display: flex;
		color: color-mix(in srgb, var(--fh-accent) 72%, var(--fg-muted) 28%);
	}

	.fh__title {
		margin: 0;
		max-width: 36ch;
		font-family: var(--font-heading, var(--font-sans));
		font-size: clamp(26px, 3.6vw, 40px);
		font-weight: var(--heading-weight, 700);
		line-height: 1.08;
		letter-spacing: -0.03em;
		color: var(--fg);
	}
	.fh__title-pre {
		font-weight: 800;
	}
	.fh__title-accent {
		font-weight: 800;
		color: var(--fh-accent);
	}

	.fh__sub {
		margin: 0;
		max-width: 52ch;
		font-size: 15px;
		line-height: 1.55;
		color: var(--fg-muted);
	}

	.fh__grid {
		list-style: none;
		margin: 8px 0 0;
		padding: 0;
		width: 100%;
		display: grid;
		grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
		gap: 18px;
		text-align: left;
	}

	.fh-card {
		padding: 20px 18px 22px;
		border-radius: 16px;
		background: color-mix(in srgb, var(--bg) 94%, var(--fg) 6%);
		border: 1px solid var(--border);
		box-shadow: 0 10px 28px color-mix(in srgb, black 7%, transparent);
	}

	.fh-card__icon {
		display: flex;
		align-items: center;
		justify-content: center;
		width: 44px;
		height: 44px;
		border-radius: 12px;
		margin-bottom: 14px;
		background: color-mix(in srgb, var(--fh-accent) 12%, var(--bg) 88%);
		color: var(--fh-accent);
	}

	.fh-card--pin {
		--tone: 210 72% 44%;
	}
	.fh-card--star {
		--tone: 28 92% 46%;
	}
	.fh-card--lab {
		--tone: 152 62% 34%;
	}
	.fh-card--award {
		--tone: 268 58% 48%;
	}

	.fh-card--pin .fh-card__icon,
	.fh-card--star .fh-card__icon,
	.fh-card--lab .fh-card__icon,
	.fh-card--award .fh-card__icon {
		background: hsl(var(--tone) / 0.14);
		color: hsl(var(--tone));
	}

	.fh-card__title {
		margin: 0 0 8px;
		font-size: 16px;
		font-weight: 800;
		letter-spacing: -0.02em;
		color: var(--fg);
	}
	.fh-card__desc {
		margin: 0;
		font-size: 13px;
		line-height: 1.55;
		color: var(--fg-muted);
	}

	@media (max-width: 639px) {
		.fh__grid {
			grid-template-columns: repeat(2, minmax(0, 1fr));
			gap: 12px;
		}
		.fh-card {
			padding: 14px 12px 16px;
			border-radius: 14px;
		}
		.fh-card__icon {
			width: 36px;
			height: 36px;
			border-radius: 10px;
			margin-bottom: 10px;
		}
		.fh-card__icon svg {
			width: 18px;
			height: 18px;
		}
		.fh-card__title {
			font-size: 13px;
			margin-bottom: 6px;
		}
		.fh-card__desc {
			font-size: 11px;
			line-height: 1.45;
		}
	}
</style>
