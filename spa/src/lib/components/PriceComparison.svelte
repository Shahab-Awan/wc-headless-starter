<script lang="ts">
	import {
		config as siteCfg,
		type ModuleResolved,
		type PriceComparisonModuleConfig,
		type SpacingPreset,
	} from '$lib/config.svelte';

	let {
		config,
		resolved,
		spacing_v = 'normal',
		spacing_h = 'normal',
		cardOnly = false,
	}: {
		config: PriceComparisonModuleConfig;
		resolved?: ModuleResolved;
		spacing_v?: SpacingPreset;
		spacing_h?: SpacingPreset;
		/** When true, render only the Live Price Comparison card (hero visual). */
		cardOnly?: boolean;
	} = $props();

	const BULLET_VARIANTS = ['globe', 'price_search', 'award'] as const;

	function normBulletVariant(raw: string): (typeof BULLET_VARIANTS)[number] {
		const v = (raw || '').trim();
		return ((BULLET_VARIANTS as readonly string[]).includes(v) ? v : 'globe') as (typeof BULLET_VARIANTS)[number];
	}

	function formatUsd(raw: string): string {
		const t = (raw ?? '').trim().replace(/^\$/, '');
		if (!t) return '';
		const n = parseFloat(t.replace(/,/g, ''));
		if (!Number.isFinite(n)) return raw.trim().startsWith('$') ? raw.trim() : `$${raw.trim()}`;
		return `$${n.toFixed(2)}`;
	}

	const accentStyle = $derived(resolved?.accent_color ? `--pc-accent: ${resolved.accent_color};` : '');
	const bullets = $derived(
		(config.bullets ?? [])
			.map((row) => ({
				variant: normBulletVariant(row.variant),
				headline: row.headline?.trim() || '',
				description: row.description?.trim() || '',
			}))
			.filter((row) => row.headline !== '')
	);
	const competitors = $derived(
		(config.competitors ?? [])
			.map((row, i) => ({
				letter: (row.letter?.trim() || String.fromCharCode(65 + i)).toUpperCase(),
				name: row.name?.trim() || '',
				price: formatUsd(row.price ?? ''),
			}))
			.filter((row) => row.name !== '' && row.price !== '')
	);
	const brandName = $derived(
		(config.brand_name ?? '').trim() || siteCfg.data.brand_name?.trim() || 'Our Store'
	);
	const brandPrice = $derived(formatUsd(config.brand_price ?? ''));
	const ctaLabel = $derived((config.cta_label?.trim() || 'Browse Catalog').replace(/\s+/g, ' '));
	const ctaHref = $derived(config.cta_href?.trim() || '/shop');
	const href = $derived(
		ctaHref.startsWith('http://') || ctaHref.startsWith('https://')
			? ctaHref
			: ctaHref.startsWith('/')
				? ctaHref
				: `/${ctaHref}`
	);
	const hasCopy = $derived(
		Boolean(
			config.headline?.trim() ||
				config.body?.trim() ||
				bullets.length ||
				ctaLabel ||
				brandPrice ||
				competitors.length
		)
	);
</script>

{#if hasCopy || (cardOnly && (brandPrice || competitors.length))}
	{#if cardOnly}
		<div class="pc pc--card-only" style={accentStyle}>
			{#if brandPrice || competitors.length}
				<div class="pc-card" aria-label="Live price comparison">
					<div class="pc-card__header">
						<div class="pc-card__status-row">
							{#if config.status_label?.trim() || config.product_label?.trim()}
								<p class="pc-card__status">
									<span class="pc-card__live" aria-hidden="true"></span>
									{#if config.status_label?.trim()}{config.status_label.trim()}{/if}
									{#if config.status_label?.trim() && config.product_label?.trim()}
										<span class="pc-card__status-sep" aria-hidden="true">·</span>
									{/if}
									{#if config.product_label?.trim()}{config.product_label.trim()}{/if}
								</p>
							{/if}
							{#if config.lowest_badge?.trim()}
								<span class="pc-card__lowest">{config.lowest_badge.trim()}</span>
							{/if}
						</div>

						{#if brandPrice}
							<div class="pc-card__brand">
								<span class="pc-card__brand-mark" aria-hidden="true">
									<svg viewBox="0 0 20 20" width="18" height="18">
										<circle cx="10" cy="10" r="10" fill="currentColor" />
										<path fill="var(--bg)" d="M6.2 10.2 8.8 12.8 14 7.6" stroke="var(--bg)" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" />
									</svg>
								</span>
								<div class="pc-card__brand-copy">
									<strong class="pc-card__brand-name">{brandName}</strong>
									{#if config.brand_tags?.trim()}
										<p class="pc-card__brand-tags">{config.brand_tags.trim()}</p>
									{/if}
								</div>
								<strong class="pc-card__brand-price">{brandPrice}</strong>
							</div>
						{/if}
					</div>

					{#if competitors.length}
						<ul class="pc-card__rows">
							{#each competitors as row}
								<li class="pc-card__row">
									<span class="pc-card__letter" aria-hidden="true">{row.letter}</span>
									<span class="pc-card__name">{row.name}</span>
									<span class="pc-card__price">{row.price}</span>
								</li>
							{/each}
						</ul>
					{/if}

					{#if config.footnote?.trim()}
						<p class="pc-card__footnote">{config.footnote.trim()}</p>
					{/if}
				</div>
			{/if}
		</div>
	{:else if hasCopy}
	<section
		class="pc"
		class:is-v-compact={spacing_v === 'compact'}
		class:is-v-spacious={spacing_v === 'spacious'}
		class:is-h-compact={spacing_h === 'compact'}
		class:is-h-spacious={spacing_h === 'spacious'}
		style={accentStyle}
		aria-label={config.headline?.trim() || 'Price comparison'}
	>
		<div class="pc__grid">
			<div class="pc__copy">
				{#if config.headline?.trim()}
					<h2 class="pc__title">{config.headline.trim()}</h2>
				{/if}
				{#if config.body?.trim()}
					<p class="pc__body">{config.body.trim()}</p>
				{/if}

				{#if bullets.length}
					<ul class="pc__bullets">
						{#each bullets as item}
							<li class="pc-bullet pc-bullet--{item.variant}">
								<span class="pc-bullet__icon" aria-hidden="true">
									{#if item.variant === 'globe'}
										<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
											<circle cx="12" cy="12" r="9" />
											<path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18" />
										</svg>
									{:else if item.variant === 'price_search'}
										<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
											<circle cx="10.5" cy="10.5" r="6.5" />
											<path d="M16 16 21 21" />
											<path d="M8.2 10.5h4.6M10.5 8.2v4.6" />
										</svg>
									{:else}
										<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
											<circle cx="12" cy="9" r="5.5" />
											<path d="M8.5 13.5 7 22l5-3 5 3-1.5-8.5" />
										</svg>
									{/if}
								</span>
								<div class="pc-bullet__text">
									<strong class="pc-bullet__title">{item.headline}</strong>
									{#if item.description}
										<p class="pc-bullet__desc">{item.description}</p>
									{/if}
								</div>
							</li>
						{/each}
					</ul>
				{/if}

				{#if ctaLabel}
					<a class="pc__cta" href={href}>{ctaLabel}</a>
				{/if}
			</div>

			{#if brandPrice || competitors.length}
				<div class="pc-card" aria-label="Live price comparison">
					<div class="pc-card__header">
						<div class="pc-card__status-row">
							{#if config.status_label?.trim() || config.product_label?.trim()}
								<p class="pc-card__status">
									<span class="pc-card__live" aria-hidden="true"></span>
									{#if config.status_label?.trim()}{config.status_label.trim()}{/if}
									{#if config.status_label?.trim() && config.product_label?.trim()}
										<span class="pc-card__status-sep" aria-hidden="true">·</span>
									{/if}
									{#if config.product_label?.trim()}{config.product_label.trim()}{/if}
								</p>
							{/if}
							{#if config.lowest_badge?.trim()}
								<span class="pc-card__lowest">{config.lowest_badge.trim()}</span>
							{/if}
						</div>

						{#if brandPrice}
							<div class="pc-card__brand">
								<span class="pc-card__brand-mark" aria-hidden="true">
									<svg viewBox="0 0 20 20" width="18" height="18">
										<circle cx="10" cy="10" r="10" fill="currentColor" />
										<path fill="var(--bg)" d="M6.2 10.2 8.8 12.8 14 7.6" stroke="var(--bg)" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" />
									</svg>
								</span>
								<div class="pc-card__brand-copy">
									<strong class="pc-card__brand-name">{brandName}</strong>
									{#if config.brand_tags?.trim()}
										<p class="pc-card__brand-tags">{config.brand_tags.trim()}</p>
									{/if}
								</div>
								<strong class="pc-card__brand-price">{brandPrice}</strong>
							</div>
						{/if}
					</div>

					{#if competitors.length}
						<ul class="pc-card__rows">
							{#each competitors as row}
								<li class="pc-card__row">
									<span class="pc-card__letter" aria-hidden="true">{row.letter}</span>
									<span class="pc-card__name">{row.name}</span>
									<span class="pc-card__price">{row.price}</span>
								</li>
							{/each}
						</ul>
					{/if}

					{#if config.footnote?.trim()}
						<p class="pc-card__footnote">{config.footnote.trim()}</p>
					{/if}
				</div>
			{/if}
		</div>
	</section>
	{/if}
{/if}

<style>
	.pc {
		--pc-accent: var(--accent);
		--pc-accent-soft: color-mix(in srgb, var(--pc-accent) 12%, var(--bg));
		--pc-accent-mid: color-mix(in srgb, var(--pc-accent) 22%, var(--bg));
		--mod-pt: 72px;
		--mod-pb: 80px;
		--mod-px: 28px;
		--mod-max-w: 1180px;
		max-width: var(--mod-max-w);
		margin: 0 auto;
		padding: var(--mod-pt) var(--mod-px) var(--mod-pb);
		color: var(--fg);
	}
	.pc--card-only {
		--mod-pt: 0;
		--mod-pb: 0;
		--mod-px: 0;
		--mod-max-w: 100%;
		width: 100%;
		max-width: 420px;
		margin: 0;
		padding: 0;
	}
	.pc--card-only .pc-card {
		width: 100%;
	}
	.pc.is-v-compact {
		--mod-pt: 44px;
		--mod-pb: 48px;
	}
	.pc.is-v-spacious {
		--mod-pt: 96px;
		--mod-pb: 104px;
	}
	.pc.is-h-compact {
		--mod-max-w: 100%;
		--mod-px: 16px;
	}
	.pc.is-h-spacious {
		--mod-max-w: 920px;
		--mod-px: 36px;
	}

	.pc__grid {
		display: grid;
		grid-template-columns: minmax(0, 1fr) minmax(280px, 420px);
		gap: clamp(28px, 4vw, 56px);
		align-items: center;
	}

	.pc__title {
		margin: 0 0 16px;
		font-size: clamp(1.65rem, 2.8vw, 2.15rem);
		line-height: 1.15;
		font-weight: 700;
		letter-spacing: -0.02em;
		color: var(--fg);
	}

	.pc__body {
		margin: 0 0 28px;
		font-size: 1rem;
		line-height: 1.65;
		color: color-mix(in srgb, var(--fg) 72%, transparent);
		max-width: 52ch;
	}

	.pc__bullets {
		list-style: none;
		margin: 0 0 32px;
		padding: 0;
		display: flex;
		flex-direction: column;
		gap: 22px;
	}

	.pc-bullet {
		display: flex;
		gap: 14px;
		align-items: flex-start;
	}

	.pc-bullet__icon {
		flex: 0 0 auto;
		display: grid;
		place-items: center;
		width: 44px;
		height: 44px;
		border-radius: 12px;
		background: var(--pc-accent-soft);
		color: var(--pc-accent);
	}

	.pc-bullet__text {
		min-width: 0;
		padding-top: 2px;
	}

	.pc-bullet__title {
		display: block;
		font-size: 1rem;
		font-weight: 700;
		line-height: 1.3;
		color: var(--fg);
	}

	.pc-bullet__desc {
		margin: 6px 0 0;
		font-size: 0.92rem;
		line-height: 1.55;
		color: color-mix(in srgb, var(--fg) 68%, transparent);
	}

	.pc__cta {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		min-height: 48px;
		padding: 0 28px;
		border-radius: 10px;
		background: var(--pc-accent);
		color: var(--bg);
		font-size: 0.95rem;
		font-weight: 600;
		text-decoration: none;
		transition: opacity 0.15s ease;
	}
	.pc__cta:hover {
		opacity: 0.9;
	}

	.pc-card {
		border: 1px solid var(--border);
		border-radius: 16px;
		overflow: hidden;
		background: var(--bg);
		box-shadow: 0 12px 40px color-mix(in srgb, var(--fg) 6%, transparent);
	}

	.pc-card__header {
		padding: 18px 20px 16px;
		background: var(--pc-accent-soft);
		border-bottom: 1px solid color-mix(in srgb, var(--pc-accent) 18%, var(--border));
	}

	.pc-card__status-row {
		display: flex;
		align-items: flex-start;
		justify-content: space-between;
		gap: 12px;
		margin-bottom: 16px;
	}

	.pc-card__status {
		margin: 0;
		display: flex;
		flex-wrap: wrap;
		align-items: center;
		gap: 8px;
		font-size: 0.68rem;
		font-weight: 700;
		letter-spacing: 0.08em;
		text-transform: uppercase;
		color: color-mix(in srgb, var(--pc-accent) 78%, var(--fg));
	}

	.pc-card__live {
		width: 7px;
		height: 7px;
		border-radius: 50%;
		background: #22c55e;
		box-shadow: 0 0 0 3px color-mix(in srgb, #22c55e 24%, transparent);
		flex: 0 0 auto;
	}

	.pc-card__status-sep {
		opacity: 0.55;
	}

	.pc-card__lowest {
		flex: 0 0 auto;
		padding: 4px 10px;
		border-radius: 999px;
		background: var(--pc-accent);
		color: var(--bg);
		font-size: 0.62rem;
		font-weight: 800;
		letter-spacing: 0.08em;
	}

	.pc-card__brand {
		display: grid;
		grid-template-columns: auto 1fr auto;
		gap: 12px;
		align-items: center;
	}

	.pc-card__brand-mark {
		display: grid;
		place-items: center;
		color: var(--pc-accent);
	}

	.pc-card__brand-name {
		display: block;
		font-size: 1.05rem;
		font-weight: 700;
		color: var(--fg);
	}

	.pc-card__brand-tags {
		margin: 4px 0 0;
		font-size: 0.62rem;
		font-weight: 600;
		letter-spacing: 0.06em;
		text-transform: uppercase;
		color: color-mix(in srgb, var(--fg) 52%, transparent);
	}

	.pc-card__brand-price {
		font-size: 1.2rem;
		font-weight: 800;
		color: var(--fg);
		white-space: nowrap;
	}

	.pc-card__rows {
		list-style: none;
		margin: 0;
		padding: 0;
	}

	.pc-card__row {
		display: grid;
		grid-template-columns: auto 1fr auto;
		gap: 12px;
		align-items: center;
		padding: 14px 20px;
		border-top: 1px solid var(--border);
	}

	.pc-card__letter {
		display: grid;
		place-items: center;
		width: 28px;
		height: 28px;
		border-radius: 8px;
		background: color-mix(in srgb, var(--fg) 8%, var(--bg));
		font-size: 0.72rem;
		font-weight: 700;
		color: color-mix(in srgb, var(--fg) 55%, transparent);
	}

	.pc-card__name {
		font-size: 0.95rem;
		font-weight: 700;
		color: var(--fg);
	}

	.pc-card__price {
		font-size: 0.95rem;
		font-weight: 600;
		color: color-mix(in srgb, var(--fg) 58%, transparent);
		white-space: nowrap;
	}

	.pc-card__footnote {
		margin: 0;
		padding: 14px 20px 18px;
		border-top: 1px solid var(--border);
		font-size: 0.72rem;
		line-height: 1.5;
		color: color-mix(in srgb, var(--fg) 52%, transparent);
	}

	@media (max-width: 900px) {
		.pc__grid {
			grid-template-columns: 1fr;
		}
		.pc-card {
			max-width: 520px;
		}
	}

	@media (max-width: 520px) {
		.pc-card__status-row {
			flex-direction: column;
			align-items: flex-start;
		}
		.pc-card__brand {
			grid-template-columns: auto 1fr;
			grid-template-rows: auto auto;
		}
		.pc-card__brand-price {
			grid-column: 2;
			justify-self: start;
			margin-top: -2px;
		}
	}
</style>
