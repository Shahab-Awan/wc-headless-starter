<script lang="ts">
	import { onMount } from 'svelte';
	import type { ModuleResolved, PromoOfferModuleConfig, SpacingPreset } from '$lib/config.svelte';

	let {
		config,
		resolved,
		spacing_v = 'normal',
		spacing_h = 'normal',
	}: {
		config: PromoOfferModuleConfig;
		resolved?: ModuleResolved;
		spacing_v?: SpacingPreset;
		spacing_h?: SpacingPreset;
	} = $props();

	const accentStyle = $derived(
		resolved?.accent_color ? `--accent: ${resolved.accent_color};` : ''
	);

	const endMs = $derived.by(() => {
		if (!config.show_countdown || !config.countdown_end_at) return null;
		const t = Date.parse(config.countdown_end_at);
		return Number.isFinite(t) ? t : null;
	});

	let remaining = $state({ h: 0, m: 0, s: 0 });
	let expired = $state(false);

	function tick() {
		if (!endMs) return;
		const diff = endMs - Date.now();
		if (diff <= 0) {
			expired = true;
			remaining = { h: 0, m: 0, s: 0 };
			return;
		}
		expired = false;
		const total = Math.floor(diff / 1000);
		remaining = {
			h: Math.floor(total / 3600),
			m: Math.floor((total % 3600) / 60),
			s: total % 60,
		};
	}

	function pad(n: number): string {
		return String(n).padStart(2, '0');
	}

	const countdownDisplay = $derived(
		expired ? '00:00:00' : `${pad(remaining.h)}:${pad(remaining.m)}:${pad(remaining.s)}`
	);

	onMount(() => {
		if (!endMs) return;
		tick();
		const id = setInterval(tick, 1000);
		return () => clearInterval(id);
	});

	$effect(() => {
		endMs;
		tick();
	});
</script>

<section
	class="promo-offer"
	class:is-v-compact={spacing_v === 'compact'}
	class:is-v-spacious={spacing_v === 'spacious'}
	class:is-h-compact={spacing_h === 'compact'}
	class:is-h-spacious={spacing_h === 'spacious'}
	style={accentStyle}
>
	<div class="promo-offer__wrap">
		{#if config.intro_subheadline?.trim()}
			<header class="promo-offer__intro">
				<h2 class="promo-offer__intro-title">{config.intro_subheadline.trim()}</h2>
			</header>
		{/if}

		<div class="promo-offer__box">
			{#if config.badge_text?.trim()}
				<div class="promo-offer__badge">{config.badge_text.trim()}</div>
			{/if}

			<div class="promo-offer__split">
				<div class="promo-offer__media">
					{#if config.image}
						<img src={config.image} alt={config.image_alt || ''} loading="lazy" />
					{:else}
						<div class="promo-offer__media-placeholder" aria-hidden="true"></div>
					{/if}
				</div>

				<div class="promo-offer__copy">
					{#if config.ribbon_text?.trim()}
						<p class="promo-offer__ribbon">{config.ribbon_text.trim()}</p>
					{/if}

					<h3 class="promo-offer__headline">
						{#if config.offer_primary?.trim()}
							<span class="promo-offer__headline-accent">{config.offer_primary.trim()}</span>
						{/if}
						{#if config.offer_secondary?.trim()}
							<span class="promo-offer__headline-rest">{config.offer_secondary.trim()}</span>
						{/if}
					</h3>

					{#if config.scarcity_text?.trim()}
						<p class="promo-offer__scarcity">{config.scarcity_text.trim()}</p>
					{/if}

					{#if config.cta_label?.trim() && config.cta_href?.trim()}
						<a class="promo-offer__cta" href={config.cta_href.trim()}>
							{config.cta_label.trim()}
							<span aria-hidden="true">→</span>
						</a>
					{/if}

					{#if config.show_countdown && endMs}
						<p class="promo-offer__countdown">
							DEAL ENDING IN:
							<strong class="promo-offer__countdown-digits">{countdownDisplay}</strong>
						</p>
					{/if}

					{#if config.status_label?.trim() || config.status_note?.trim()}
						<div class="promo-offer__status">
							{#if config.status_label?.trim()}
								<span>
									{config.status_label.trim()}
									{#if config.status_value?.trim()}
										<strong class="promo-offer__status-high">{config.status_value.trim()}</strong>
									{/if}
								</span>
							{/if}
							{#if config.status_label?.trim() && config.status_note?.trim()}
								<span class="promo-offer__status-divider" aria-hidden="true"></span>
							{/if}
							{#if config.status_note?.trim()}
								<strong>{config.status_note.trim()}</strong>
							{/if}
						</div>
					{/if}

					{#if config.footer_text?.trim()}
						<p class="promo-offer__footer">{config.footer_text.trim()}</p>
					{/if}
				</div>
			</div>
		</div>
	</div>
</section>

<style>
	.promo-offer {
		--mod-pt: var(--wchs-spacing-v-normal, 40px);
		--mod-pb: var(--wchs-spacing-v-normal, 56px);
		--mod-px: 28px;
		--po-max: min(920px, 100%);
		--po-badge-bg: color-mix(in srgb, var(--accent) 88%, #1a1630 12%);
		--po-panel-left: color-mix(in srgb, var(--accent) 12%, var(--bg-muted) 88%);
		padding: var(--mod-pt) var(--mod-px) var(--mod-pb);
		background: color-mix(in srgb, var(--accent) 6%, var(--bg) 94%);
	}
	.promo-offer.is-v-compact {
		--mod-pt: var(--wchs-spacing-v-compact, 20px);
		--mod-pb: var(--wchs-spacing-v-compact, 28px);
	}
	.promo-offer.is-v-spacious {
		--mod-pt: var(--wchs-spacing-v-spacious, 64px);
		--mod-pb: var(--wchs-spacing-v-spacious, 72px);
	}
	.promo-offer.is-h-compact {
		--mod-px: 16px;
	}
	.promo-offer.is-h-spacious {
		--mod-px: 40px;
	}

	.promo-offer__wrap {
		max-width: var(--po-max);
		margin: 0 auto;
	}

	.promo-offer__intro {
		text-align: center;
		margin: 0 0 28px;
	}
	.promo-offer__intro-title {
		font-family: var(--font-heading, var(--font-sans));
		font-size: clamp(20px, 3.2vw, 28px);
		font-weight: var(--heading-weight, 600);
		line-height: 1.35;
		letter-spacing: -0.02em;
		margin: 0 auto;
		max-width: 42ch;
		color: var(--fg);
	}

	.promo-offer__box {
		position: relative;
		border: 2px dashed var(--fg);
		border-radius: 15px;
		overflow: visible;
	}

	.promo-offer__badge {
		position: absolute;
		top: 0;
		left: 50%;
		z-index: 2;
		transform: translate(-50%, -50%);
		padding: 8px 18px;
		background: var(--po-badge-bg);
		color: var(--accent-fg);
		font-size: 11px;
		font-weight: 700;
		letter-spacing: 0.12em;
		text-transform: uppercase;
		white-space: nowrap;
		border-radius: 6px;
	}

	.promo-offer__split {
		display: grid;
		grid-template-columns: 1fr 1fr;
		min-height: 360px;
		border-radius: 15px;
		overflow: hidden;
	}

	.promo-offer__media {
		background: var(--po-panel-left);
		display: flex;
		align-items: center;
		justify-content: center;
		padding: 12px;
		min-height: 100%;
	}
	.promo-offer__media img {
		width: 100%;
		max-width: 100%;
		max-height: 100%;
		height: auto;
		object-fit: contain;
		display: block;
	}
	.promo-offer__media-placeholder {
		width: 100%;
		max-width: 280px;
		aspect-ratio: 1;
		border-radius: 50%;
		background: color-mix(in srgb, var(--accent) 18%, transparent);
		opacity: 0.35;
	}

	.promo-offer__copy {
		display: flex;
		flex-direction: column;
		align-items: center;
		justify-content: center;
		text-align: center;
		padding: 28px 24px 32px;
		gap: 14px;
		background: var(--bg);
	}

	.promo-offer__ribbon {
		margin: 0;
		font-size: 11px;
		font-weight: 700;
		letter-spacing: 0.1em;
		text-transform: uppercase;
		color: var(--fg);
	}

	.promo-offer__headline {
		margin: 0;
		font-family: var(--font-heading, var(--font-sans));
		font-size: clamp(22px, 3.2vw, 30px);
		font-weight: 800;
		line-height: 1.15;
		letter-spacing: -0.02em;
		max-width: 20ch;
	}
	.promo-offer__headline-accent {
		display: block;
		color: var(--accent);
	}
	.promo-offer__headline-rest {
		display: block;
		color: var(--fg);
		font-size: 0.72em;
		font-weight: 700;
		letter-spacing: 0.02em;
		margin-top: 4px;
	}

	.promo-offer__scarcity {
		margin: 0;
		max-width: 34ch;
		font-size: 13px;
		line-height: 1.55;
		color: var(--fg-muted);
	}

	.promo-offer__cta {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		gap: 8px;
		width: min(100%, 300px);
		padding: 14px 20px;
		background: var(--po-badge-bg);
		color: var(--accent-fg);
		border: 1px solid var(--po-badge-bg);
		border-radius: 14px;
		text-decoration: none;
		font-size: 13px;
		font-weight: 700;
		letter-spacing: 0.08em;
		text-transform: uppercase;
		transition: opacity var(--dur-fast) var(--ease);
	}
	.promo-offer__cta:hover {
		opacity: 0.9;
	}

	.promo-offer__countdown {
		margin: 4px 0 0;
		font-size: 12px;
		font-weight: 600;
		letter-spacing: 0.06em;
		color: var(--fg);
	}
	.promo-offer__countdown-digits {
		color: var(--accent);
		font-variant-numeric: tabular-nums;
		margin-left: 6px;
	}

	.promo-offer__status {
		display: flex;
		align-items: center;
		justify-content: center;
		flex-wrap: wrap;
		gap: 10px 14px;
		width: min(100%, 340px);
		padding: 10px 16px;
		background: color-mix(in srgb, var(--fg) 6%, var(--bg-muted) 94%);
		font-size: 12px;
		color: var(--fg);
	}
	.promo-offer__status-high {
		color: var(--accent);
	}
	.promo-offer__status-divider {
		width: 1px;
		height: 14px;
		background: var(--border);
	}

	.promo-offer__footer {
		margin: 0;
		font-size: 12px;
		color: var(--fg-muted);
		max-width: 36ch;
	}

	@media (max-width: 720px) {
		.promo-offer__split {
			grid-template-columns: 1fr;
			min-height: 0;
		}
		.promo-offer__media {
			min-height: 260px;
			padding: 10px;
		}
		.promo-offer__badge {
			white-space: normal;
			text-align: center;
			max-width: calc(100% - 32px);
		}
	}
</style>
