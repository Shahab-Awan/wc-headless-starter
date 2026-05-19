<script lang="ts">
	import type { ModuleResolved, SpacingPreset } from '$lib/config.svelte';

	let {
		spacing_v = 'normal',
		spacing_h = 'normal',
		center_header = false,
		resolved,
		eyebrow = '',
		title = '',
		subtitleText = '',
		subtitleHtml = '',
		brandName,
		competitorName,
		brandLogo = '',
		competitorLogo = '',
		compareRows,
	}: {
		spacing_v?: SpacingPreset;
		spacing_h?: SpacingPreset;
		center_header?: boolean;
		resolved?: ModuleResolved;
		eyebrow?: string;
		title?: string;
		subtitleText?: string;
		subtitleHtml?: string;
		brandName: string;
		competitorName: string;
		brandLogo?: string;
		competitorLogo?: string;
		compareRows: string[];
	} = $props();

	const accentStyle = $derived(
		resolved?.accent_color ? `--compare-accent: ${resolved.accent_color};` : ''
	);

	const vialUid = Math.random().toString(36).slice(2, 11);
	const gradGlassId = `cmp-gls-${vialUid}`;
	const gradCapId = `cmp-cap-${vialUid}`;
</script>

<section
	class="compare"
	class:is-v-compact={spacing_v === 'compact'}
	class:is-v-spacious={spacing_v === 'spacious'}
	class:is-h-compact={spacing_h === 'compact'}
	class:is-h-spacious={spacing_h === 'spacious'}
	style={accentStyle}
>
	<header class="compare__head" class:is-centered={center_header}>
		{#if eyebrow}
			<h2 class="compare__headline">{eyebrow}</h2>
		{/if}
		{#if title}
			<h2 class="compare__title">{title}</h2>
		{/if}
		{#if subtitleHtml}
			<div class="compare__lead compare__lead--html">{@html subtitleHtml}</div>
		{:else if subtitleText}
			<p class="compare__lead">{subtitleText}</p>
		{/if}
	</header>

	<div class="compare__scroll">
		<div class="compare__shell">
			<table class="compare__table">
				<thead>
					<tr>
						<th class="compare__th compare__th--corner" scope="col"></th>
						<th class="compare__th compare__th--brand" scope="col">
							<div class="compare__brand-cap">
								{#if brandLogo}
									<img class="compare__logo compare__logo--brand" src={brandLogo} alt="" loading="lazy" draggable="false" />
								{:else}
									<svg class="compare__vial compare__vial--brand" viewBox="0 0 64 100" aria-hidden="true">
										<defs>
											<linearGradient id={gradGlassId} x1="0%" y1="0%" x2="100%" y2="100%">
												<stop offset="0%" stop-color="rgba(255,255,255,0.55)" />
												<stop offset="45%" stop-color="rgba(255,255,255,0.22)" />
												<stop offset="100%" stop-color="rgba(255,255,255,0.08)" />
											</linearGradient>
											<linearGradient id={gradCapId} x1="0%" y1="0%" x2="0%" y2="100%">
												<stop offset="0%" stop-color="rgba(255,255,255,0.95)" />
												<stop offset="100%" stop-color="rgba(220,240,255,0.75)" />
											</linearGradient>
										</defs>
										<rect x="22" y="6" width="20" height="14" rx="4" fill={`url(#${gradCapId})`} />
										<rect x="26" y="20" width="12" height="16" rx="2" fill="rgba(255,255,255,0.35)" />
										<path
											fill={`url(#${gradGlassId})`}
											stroke="rgba(255,255,255,0.65)"
											stroke-width="1.25"
											d="M22 38h20l8 52a7 7 0 0 1-7 7H21a7 7 0 0 1-7-7l8-52Z"
										/>
										<rect x="19" y="52" width="26" height="18" rx="3" fill="rgba(255,255,255,0.96)" />
										<text
											class="compare__vial-label-text"
											x="32"
											y="65"
											text-anchor="middle"
											font-size="11"
											font-weight="700"
										>Alyve</text>
									</svg>
								{/if}
								<span class="compare__brand-title">{brandName}</span>
							</div>
						</th>
						<th class="compare__th compare__th--rival" scope="col">
							<div class="compare__rival-cap">
								{#if competitorLogo}
									<img class="compare__logo compare__logo--rival" src={competitorLogo} alt="" loading="lazy" draggable="false" />
								{:else}
									<svg class="compare__vial compare__vial--rival" viewBox="0 0 64 100" aria-hidden="true">
										<g class="compare__vial-silhouette">
											<rect x="22" y="6" width="20" height="14" rx="4" />
											<rect x="26" y="20" width="12" height="16" rx="2" />
											<path d="M22 38h20l8 52a7 7 0 0 1-7 7H21a7 7 0 0 1-7-7l8-52Z" />
										</g>
									</svg>
								{/if}
								<span class="compare__rival-title">{competitorName}</span>
							</div>
						</th>
					</tr>
				</thead>
				<tbody>
					{#each compareRows as label}
						<tr class="compare__row">
							<th class="compare__feature" scope="row">{label}</th>
							<td class="compare__cell compare__cell--brand">
								<span class="compare__mark compare__mark--yes" aria-label="Yes">
									<svg viewBox="0 0 24 24" width="22" height="22">
										<circle cx="12" cy="12" r="10.5" fill="white" />
										<path
											d="M8 12.5 11 15.5 17 9"
											fill="none"
											stroke="currentColor"
											stroke-width="2"
											stroke-linecap="round"
											stroke-linejoin="round"
										/>
									</svg>
								</span>
							</td>
							<td class="compare__cell compare__cell--rival">
								<span class="compare__mark compare__mark--no" aria-label="No">
									<svg viewBox="0 0 24 24" width="22" height="22">
										<circle cx="12" cy="12" r="10.5" fill="color-mix(in srgb, var(--fg-muted) 14%, var(--bg) 86%)" />
										<path
											d="M9 9 15 15M15 9l-6 6"
											fill="none"
											stroke="color-mix(in srgb, var(--fg-muted) 72%, var(--fg) 28%)"
											stroke-width="2"
											stroke-linecap="round"
										/>
									</svg>
								</span>
							</td>
						</tr>
					{/each}
				</tbody>
			</table>
		</div>
	</div>
</section>

<style>
	.compare {
		--compare-accent: var(--accent);
		--compare-row-py: 24px;
		--compare-head-py: 30px;
		--mod-pt: var(--wchs-spacing-v-normal, 48px);
		--mod-pb: var(--wchs-spacing-v-normal, 56px);
		--mod-px: 24px;
		--mod-max-w: 1040px;
		max-width: var(--mod-max-w);
		margin: 0 auto;
		padding: var(--mod-pt) var(--mod-px) var(--mod-pb);
	}
	.compare.is-v-compact {
		--compare-row-py: 18px;
		--compare-head-py: 22px;
		--mod-pt: 28px;
		--mod-pb: 32px;
	}
	.compare.is-v-spacious {
		--compare-row-py: 30px;
		--compare-head-py: 36px;
		--mod-pt: 88px;
		--mod-pb: 96px;
	}
	.compare.is-h-compact {
		--mod-max-w: 100%;
		--mod-px: 16px;
	}
	.compare.is-h-spacious {
		--mod-max-w: 880px;
		--mod-px: 36px;
	}

	.compare__head {
		text-align: left;
		margin-bottom: 36px;
		max-width: min(52rem, 100%);
	}
	.compare__head.is-centered {
		text-align: center;
		margin-left: auto;
		margin-right: auto;
	}
	.compare__head.is-centered .compare__lead,
	.compare__head.is-centered .compare__lead--html {
		max-width: 54ch;
		margin-left: auto;
		margin-right: auto;
	}
	.compare__headline {
		margin: 0 0 20px;
		font-family: var(--font-heading, var(--font-sans));
		font-size: clamp(24px, 3.5vw, 34px);
		font-weight: var(--heading-weight, 700);
		line-height: 1.15;
		letter-spacing: -0.02em;
		color: var(--fg);
	}
	.compare__head.is-centered .compare__headline {
		text-align: center;
		margin-bottom: 22px;
	}
	.compare__title {
		margin: 0 0 20px;
		font-family: var(--font-heading, var(--font-sans));
		font-size: clamp(24px, 3.5vw, 34px);
		font-weight: var(--heading-weight, 700);
		line-height: 1.15;
		letter-spacing: -0.02em;
		color: var(--fg);
	}
	.compare__head.is-centered .compare__title {
		text-align: center;
		margin-bottom: 22px;
	}
	.compare__lead {
		margin: 0;
		font-size: clamp(15px, 1.35vw, 17px);
		font-weight: 400;
		line-height: 1.68;
		color: color-mix(in srgb, var(--fg-muted) 92%, var(--fg) 8%);
	}
	.compare__lead--html :global(p) {
		margin: 0 0 14px;
		font-size: inherit;
		line-height: inherit;
		font-weight: inherit;
		color: inherit;
	}
	.compare__lead--html :global(p:last-child) {
		margin-bottom: 0;
	}
	.compare__lead--html :global(a) {
		color: var(--accent);
		text-decoration: underline;
		text-underline-offset: 2px;
	}

	.compare__scroll {
		overflow-x: auto;
		-webkit-overflow-scrolling: touch;
		margin: 0 calc(var(--mod-px) * -0.25);
		padding: 4px 2px 8px;
	}

	.compare__shell {
		min-width: min(100%, 560px);
		border-radius: 20px;
		border: 1px solid var(--border);
		background: color-mix(in srgb, var(--bg) 94%, var(--fg-muted) 6%);
		box-shadow: 0 16px 40px color-mix(in srgb, black 6%, transparent);
		overflow: hidden;
	}

	.compare__table {
		width: 100%;
		border-collapse: collapse;
		table-layout: fixed;
		font-size: 15px;
	}

	.compare__th {
		padding: var(--compare-head-py) 18px calc(var(--compare-head-py) + 4px);
		font-weight: 700;
		vertical-align: middle;
		border-bottom: 1px solid var(--border);
		color: var(--fg);
	}

	.compare__th--corner {
		width: 32%;
		background: color-mix(in srgb, var(--bg) 96%, var(--fg-muted) 4%);
	}

	.compare__th--brand {
		width: 34%;
		background: var(--compare-accent);
		color: white;
		border-bottom-color: color-mix(in srgb, var(--compare-accent) 65%, black 35%);
		box-shadow: inset 0 1px 0 color-mix(in srgb, white 14%, transparent);
	}

	.compare__th--rival {
		width: 34%;
		background: color-mix(in srgb, var(--bg) 96%, var(--fg-muted) 4%);
	}

	.compare__brand-cap,
	.compare__rival-cap {
		display: flex;
		flex-direction: column;
		align-items: center;
		justify-content: center;
		gap: 14px;
		text-align: center;
		min-height: 96px;
	}

	.compare__vial {
		display: block;
		width: 56px;
		height: 88px;
		flex-shrink: 0;
		filter: drop-shadow(0 6px 14px color-mix(in srgb, black 22%, transparent));
	}
	.compare__vial--brand {
		overflow: visible;
	}
	.compare__vial-label-text {
		font-family: var(--font-sans, system-ui, sans-serif);
		fill: var(--compare-accent);
	}

	.compare__vial-silhouette {
		fill: #141414;
	}

	.compare__brand-title {
		font-size: 13px;
		font-weight: 800;
		letter-spacing: 0.06em;
		text-transform: uppercase;
		line-height: 1.25;
	}

	.compare__rival-title {
		font-size: 13px;
		font-weight: 700;
		color: var(--fg-muted);
		line-height: 1.3;
	}

	.compare__logo {
		width: 52px;
		height: 52px;
		object-fit: contain;
		border-radius: 12px;
		background: white;
		padding: 5px;
	}

	.compare__logo--brand {
		box-shadow: 0 6px 18px color-mix(in srgb, black 22%, transparent);
	}

	.compare__row:last-child .compare__feature,
	.compare__row:last-child .compare__cell {
		border-bottom: none;
	}

	.compare__feature {
		padding: var(--compare-row-py) 18px;
		text-align: left;
		font-weight: 600;
		color: var(--fg);
		vertical-align: middle;
		border-bottom: 1px solid color-mix(in srgb, var(--border) 85%, transparent);
		background: color-mix(in srgb, var(--bg) 97%, var(--fg-muted) 3%);
		line-height: 1.45;
	}

	.compare__cell {
		padding: var(--compare-row-py) 16px;
		text-align: center;
		vertical-align: middle;
		border-bottom: 1px solid color-mix(in srgb, var(--border) 85%, transparent);
	}

	.compare__cell--brand {
		background: var(--compare-accent);
		border-bottom-color: color-mix(in srgb, var(--compare-accent) 55%, black 45%);
	}

	.compare__cell--rival {
		background: color-mix(in srgb, var(--bg) 97%, var(--fg-muted) 3%);
	}

	.compare__mark {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		vertical-align: middle;
	}
	.compare__mark--yes {
		color: var(--compare-accent);
		filter: drop-shadow(0 2px 4px color-mix(in srgb, black 16%, transparent));
	}

	@media (max-width: 860px) {
		.compare__feature {
			font-size: 14px;
			padding: calc(var(--compare-row-py) * 0.85) 12px;
		}
		.compare__cell {
			padding: calc(var(--compare-row-py) * 0.85) 10px;
		}
		.compare__th {
			padding: calc(var(--compare-head-py) * 0.88) 12px;
		}
		.compare__vial {
			width: 48px;
			height: 76px;
		}
	}
</style>
