<script lang="ts">
	import { browser } from '$app/environment';
	import {
		config as siteCfg,
		type ModuleResolved,
		type SplitValueModuleConfig,
		type SpacingPreset,
	} from '$lib/config.svelte';

	let {
		config,
		resolved,
		spacing_v = 'normal',
		spacing_h = 'normal',
	}: {
		config: SplitValueModuleConfig;
		resolved?: ModuleResolved;
		spacing_v?: SpacingPreset;
		spacing_h?: SpacingPreset;
	} = $props();

	const bulletIcons = [
		'<path d="M3.8 8.8h10.2v7.7H3.8z"/><path d="M14 11h3.1l3.1 3.2v2.3H14"/><circle cx="8" cy="17.6" r="1.7"/><circle cx="17.6" cy="17.6" r="1.7"/>',
		'<path d="M9 4.8h6M10.2 4.8v4.3L6.5 16a3.5 3.5 0 0 0 3.1 5.2h4.8a3.5 3.5 0 0 0 3.1-5.2l-3.7-6.9V4.8"/><path d="M9.1 14.6h5.8"/>',
		'<path d="M12 3.6 18.4 6v5.5c0 4-2.5 7.5-6.4 8.9-3.9-1.4-6.4-4.9-6.4-8.9V6Z"/><path d="m9.3 12.2 1.9 1.9 3.6-3.9"/>',
	];

	const accentStyle = $derived(resolved?.accent_color ? `--sv-accent: ${resolved.accent_color};` : '');
	const bullets = $derived((config.bullets ?? []).map((b) => b.text?.trim()).filter(Boolean));
	const stats = $derived(
		(config.stats ?? []).filter((s) => (s.value?.trim() || s.label?.trim()) !== '')
	);
	const ctaLabel = $derived((config.cta_label?.trim() || 'Buy 1 Get 1 Free').replace(/\s+/g, ' '));
	const ctaHref = $derived(config.cta_href?.trim() || '/shop');
	const href = $derived(
		ctaHref.startsWith('http://') || ctaHref.startsWith('https://') ? ctaHref : ctaHref.startsWith('/') ? ctaHref : `/${ctaHref}`
	);
	const hasCopy = $derived(
		Boolean(
			config.rating_line?.trim() ||
				config.headline_prefix?.trim() ||
				config.headline_accent?.trim() ||
				bullets.length ||
				ctaLabel
		)
	);

	let catalogImg = $state('');
	let catalogAlt = $state('');

	$effect(() => {
		if (!browser) return;
		if ((config.image ?? '').trim()) {
			catalogImg = '';
			catalogAlt = '';
			return;
		}
		let cancelled = false;
		(async () => {
			try {
				const r = await fetch('/wp-json/wc/store/v1/products?per_page=1', {
					credentials: 'include',
					headers: { Accept: 'application/json' },
				});
				if (!r.ok || cancelled) return;
				const data = (await r.json()) as Array<{ name?: string; images?: Array<{ src?: string }> }>;
				const p = data[0];
				const src = p?.images?.[0]?.src;
				if (cancelled || !src) return;
				catalogImg = src;
				catalogAlt = p?.name ?? '';
			} catch {
				/* noop */
			}
		})();
		return () => {
			cancelled = true;
		};
	});

	const imgSrc = $derived.by(() => {
		const raw = (config.image ?? '').trim();
		let primary = raw;
		if (primary.startsWith('/wp-content')) {
			const base = siteCfg.data.wp_origin.replace(/\/$/, '');
			if (base) primary = `${base}${primary}`;
		}
		return primary || catalogImg;
	});
	const imgAlt = $derived((config.image_alt ?? '').trim() || catalogAlt);
	const showMedia = $derived(Boolean(imgSrc));
</script>

{#if hasCopy || showMedia}
	<section
		class="sv"
		class:is-v-compact={spacing_v === 'compact'}
		class:is-v-spacious={spacing_v === 'spacious'}
		class:is-h-compact={spacing_h === 'compact'}
		class:is-h-spacious={spacing_h === 'spacious'}
		style={accentStyle}
		aria-label="Promotional offer"
	>
		<div class="sv__grid">
			<div class="sv__copy">
				{#if config.rating_line?.trim()}
					<div class="sv__rating">
						<span class="sv__stars" aria-hidden="true">
							{#each Array(5) as _, i (i)}
								<svg viewBox="0 0 20 20" width="16" height="16" class="sv__star">
									<path
										fill="currentColor"
										d="M10 1.5l2.35 5.4 5.9.5-4.5 3.9 1.35 5.75L10 14.9l-5.1 3.05L6.25 11.3 1.75 7.4l5.9-.5L10 1.5z"
									/>
								</svg>
							{/each}
						</span>
						<span class="sv__rating-txt">{config.rating_line.trim()}</span>
					</div>
				{/if}

				{#if config.headline_prefix?.trim() || config.headline_accent?.trim()}
					<h2 class="sv__headline">
						{#if config.headline_prefix?.trim()}
							<span class="sv__head-prefix">{config.headline_prefix.trim()}</span>
						{/if}
						{#if config.headline_accent?.trim()}
							<span
								class="sv__accent"
								class:sv__accent--line={config.accent_underline !== false}
							>
								{config.headline_accent.trim()}
							</span>
						{/if}
					</h2>
				{/if}

				{#if bullets.length}
					<ul class="sv__bullets">
						{#each bullets as line, bi (line)}
							<li class="sv__bullet">
								<span class="sv__bullet-icon" aria-hidden="true">
									<svg
										viewBox="0 0 24 24"
										fill="none"
										stroke="currentColor"
										stroke-width="1.9"
										stroke-linecap="round"
										stroke-linejoin="round"
									>
										{@html bulletIcons[bi % bulletIcons.length]}
									</svg>
								</span>
								<span class="sv__bullet-txt">{line}</span>
							</li>
						{/each}
					</ul>
				{/if}

				<div class="sv__cta-wrap">
					<a class="sv__cta" href={href}>
						<span>{ctaLabel}</span>
						<span class="sv__cta-ico" aria-hidden="true">
							<svg viewBox="0 0 24 24" width="20" height="20">
								<circle cx="12" cy="12" r="10" fill="color-mix(in srgb, var(--sv-accent, var(--accent)) 18%, transparent)" />
								<path
									d="M10.5 8.5 14 12l-3.5 3.5"
									fill="none"
									stroke="currentColor"
									stroke-width="1.6"
									stroke-linecap="round"
									stroke-linejoin="round"
								/>
							</svg>
						</span>
					</a>
					<p class="sv__hype">The more you buy, the more FREE!</p>
					<ul class="sv__mini-trust" aria-label="Trust signals">
						<li class="sv__mini-trust-item">
							<span class="sv__mini-ico" aria-hidden="true">
								<svg viewBox="0 0 16 16" width="14" height="14"><path fill="currentColor" d="M6.5 12 2 7.5l1.4-1.4L6.5 9.2 12.6 3 14 4.4z"/></svg>
							</span>
							Triple-Tested for Quality
						</li>
						<li class="sv__mini-trust-item">
							<span class="sv__mini-ico sv__mini-ico--lock" aria-hidden="true">
								<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
									<rect x="4.5" y="11" width="15" height="9.5" rx="1.5" />
									<path d="M7.5 11V8a4.5 4.5 0 0 1 9 0v3" />
								</svg>
							</span>
							60-day money-back guarantee
						</li>
					</ul>
					<div class="sv__secure-card">
						<div class="sv__secure-head">
							<svg class="sv__secure-ico" viewBox="0 0 20 20" width="18" height="18" aria-hidden="true">
								<path
									fill="currentColor"
									d="M10 1 3 4v5.5c0 4 4.2 7.7 7 8.5 2.8-.8 7-4.5 7-8.5V4l-7-3z"
									opacity="0.9"
								/>
							</svg>
							<span class="sv__secure-title">Safe & Secure Checkout</span>
						</div>
						{#if config.trust_note?.trim()}
							<p class="sv__secure-body">{config.trust_note.trim()}</p>
						{/if}
					</div>
				</div>
			</div>

			<div class="sv__media">
				{#if config.promo_badge_eyebrow?.trim() || config.promo_badge_title?.trim()}
					<div class="sv__badge">
						{#if config.promo_badge_eyebrow?.trim()}
							<span class="sv__badge-eyebrow">{config.promo_badge_eyebrow.trim()}</span>
						{/if}
						{#if config.promo_badge_title?.trim()}
							<span class="sv__badge-title">{config.promo_badge_title.trim()}</span>
						{/if}
					</div>
				{/if}

				<div class="sv__frame">
					<div class="sv__frame-deco" aria-hidden="true"></div>
					{#if showMedia}
						<img
							class="sv__img"
							src={imgSrc}
							alt={imgAlt}
							loading="lazy"
							draggable="false"
						/>
					{:else}
						<div class="sv__ph" aria-hidden="true"></div>
					{/if}
				</div>

				{#if stats.length}
					<div class="sv__stats">
						{#each stats as s, i (i)}
							<div class="sv__stat">
								<span class="sv__stat-val">{s.value?.trim()}</span>
								<span class="sv__stat-lab">{s.label?.trim()}</span>
							</div>
						{/each}
					</div>
				{/if}
			</div>
		</div>
	</section>
{/if}

<style>
	.sv {
		--sv-accent: var(--accent);
		--mod-pt: clamp(44px, 7vw, 88px);
		--mod-pb: clamp(48px, 8vw, 96px);
		--mod-px: clamp(20px, 4vw, 40px);
		--mod-max-w: 1200px;
		max-width: var(--mod-max-w);
		margin: clamp(12px, 2.5vw, 28px) auto clamp(16px, 3vw, 36px);
		padding: var(--mod-pt) var(--mod-px) var(--mod-pb);
		color: var(--fg);
	}
	.sv.is-v-compact {
		--mod-pt: 28px;
		--mod-pb: 32px;
		margin-top: 8px;
		margin-bottom: 8px;
	}
	.sv.is-v-spacious {
		--mod-pt: 72px;
		--mod-pb: 96px;
		margin-top: 36px;
		margin-bottom: 40px;
	}
	.sv.is-h-compact {
		--mod-max-w: 100%;
		--mod-px: 14px;
	}
	.sv.is-h-spacious {
		--mod-max-w: 1080px;
		--mod-px: 48px;
	}

	.sv__grid {
		display: grid;
		grid-template-columns: minmax(0, 1fr) minmax(0, 1.05fr);
		gap: clamp(32px, 5vw, 64px);
		align-items: stretch;
		min-height: clamp(480px, 56vh, 680px);
	}

	@media (max-width: 880px) {
		.sv__grid {
			grid-template-columns: 1fr;
			gap: 36px;
			min-height: 0;
		}
		.sv__copy {
			min-height: 0;
		}
		.sv__media {
			order: -1;
			min-height: 0;
		}
	}

	.sv__copy {
		display: flex;
		flex-direction: column;
		justify-content: center;
		min-height: min(520px, 54vh);
		gap: 0;
	}

	.sv__rating {
		display: flex;
		flex-wrap: wrap;
		align-items: center;
		gap: 10px;
		margin: 0 0 16px;
	}
	.sv__stars {
		display: inline-flex;
		gap: 2px;
		color: color-mix(in srgb, var(--sv-accent) 72%, hsl(33 90% 52%) 28%);
	}
	.sv__rating-txt {
		font-size: 13px;
		font-weight: 500;
		color: var(--fg-muted);
		letter-spacing: 0.02em;
	}

	.sv__headline {
		font-family: var(--font-heading, var(--font-sans));
		font-size: clamp(1.55rem, 3.2vw, 2.35rem);
		font-weight: var(--heading-weight, 700);
		line-height: 1.15;
		letter-spacing: -0.03em;
		margin: 0 0 22px;
		color: var(--fg);
	}
	.sv__head-prefix + .sv__accent {
		margin-left: 0.35em;
	}
	.sv__accent {
		color: var(--sv-accent);
		white-space: nowrap;
	}
	.sv__accent--line {
		text-decoration: underline;
		text-decoration-color: color-mix(in srgb, var(--sv-accent) 65%, transparent);
		text-decoration-thickness: 2px;
		text-underline-offset: 5px;
	}

	.sv__bullets {
		list-style: none;
		margin: 0 0 32px;
		padding: 0;
		display: flex;
		flex-direction: column;
		gap: 14px;
		width: 100%;
		max-width: 420px;
	}
	.sv__bullet {
		display: flex;
		align-items: center;
		gap: 14px;
		font-size: 15px;
		font-weight: 600;
		line-height: 1.35;
		color: color-mix(in srgb, var(--fg) 94%, var(--fg-muted) 6%);
	}
	.sv__bullet-icon {
		flex-shrink: 0;
		display: flex;
		align-items: center;
		justify-content: center;
		width: 46px;
		height: 46px;
		border-radius: 10px;
		border: 1px solid color-mix(in srgb, var(--border) 85%, var(--sv-accent) 15%);
		background: color-mix(in srgb, var(--bg) 88%, var(--sv-accent) 6%);
		color: color-mix(in srgb, var(--sv-accent) 78%, var(--fg-muted) 22%);
	}
	.sv__bullet-icon svg {
		width: 22px;
		height: 22px;
		display: block;
	}
	.sv__bullet-txt {
		flex: 1;
		min-width: 0;
	}

	.sv__cta-wrap {
		display: flex;
		flex-direction: column;
		align-items: flex-start;
		gap: 12px;
		margin-top: 4px;
		max-width: 100%;
	}
	.sv__hype {
		margin: 4px 0 0;
		font-size: 13px;
		font-weight: 600;
		color: color-mix(in srgb, var(--fg-muted) 88%, var(--sv-accent) 12%);
		letter-spacing: 0.02em;
	}
	.sv__mini-trust {
		list-style: none;
		margin: 0 0 4px;
		padding: 0;
		display: flex;
		flex-wrap: wrap;
		gap: 10px 20px;
	}
	.sv__mini-trust-item {
		display: inline-flex;
		align-items: center;
		gap: 6px;
		font-size: 12px;
		font-weight: 600;
		color: var(--fg-muted);
	}
	.sv__mini-ico {
		display: flex;
		color: color-mix(in srgb, var(--sv-accent) 65%, var(--fg-muted) 35%);
	}
	.sv__secure-card {
		width: 100%;
		max-width: 420px;
		margin-top: 8px;
		padding: 14px 16px 16px;
		border-radius: 14px;
		border: 1px solid var(--border);
		background: color-mix(in srgb, var(--bg) 92%, var(--fg-muted) 8%);
		box-shadow: 0 10px 28px color-mix(in srgb, black 8%, transparent);
	}
	.sv__secure-head {
		display: flex;
		align-items: center;
		gap: 10px;
		margin-bottom: 8px;
	}
	.sv__secure-ico {
		flex-shrink: 0;
		color: var(--sv-accent);
	}
	.sv__secure-title {
		font-size: 14px;
		font-weight: 800;
		letter-spacing: 0.04em;
		text-transform: uppercase;
		color: var(--fg);
	}
	.sv__secure-body {
		margin: 0;
		font-size: 12px;
		line-height: 1.55;
		color: var(--fg-muted);
	}
	.sv__cta {
		display: inline-flex;
		align-items: center;
		gap: 12px;
		min-height: 54px;
		padding: 0 24px 0 28px;
		border-radius: 14px;
		font-size: 14px;
		font-weight: 700;
		letter-spacing: 0.04em;
		text-transform: uppercase;
		text-decoration: none;
		color: color-mix(in srgb, var(--sv-accent) 8%, white 92%);
		background: color-mix(in srgb, var(--sv-accent) 92%, black 8%);
		border: 1px solid color-mix(in srgb, var(--sv-accent) 55%, transparent);
		box-shadow: 0 10px 28px color-mix(in srgb, var(--sv-accent) 28%, transparent);
		transition:
			transform var(--dur-fast) var(--ease),
			box-shadow var(--dur-fast) var(--ease);
	}
	.sv__cta:hover {
		transform: translateY(-1px);
		box-shadow: 0 14px 34px color-mix(in srgb, var(--sv-accent) 35%, transparent);
	}
	.sv__cta:active {
		transform: scale(0.99);
	}
	.sv__cta-ico {
		display: flex;
		color: color-mix(in srgb, var(--sv-accent) 8%, white 92%);
	}

	.sv__media {
		position: relative;
		align-self: stretch;
		min-height: min(560px, 58vh);
		display: flex;
		flex-direction: column;
		justify-content: center;
	}

	.sv__badge {
		position: absolute;
		z-index: 3;
		top: 10px;
		right: 10px;
		max-width: min(200px, 46vw);
		padding: 10px 14px 12px;
		border-radius: 14px;
		background: color-mix(in srgb, var(--sv-accent) 38%, hsl(28 95% 50%) 62%);
		color: color-mix(in srgb, white 94%, black 6%);
		box-shadow: 0 8px 22px color-mix(in srgb, black 22%, transparent);
		text-align: right;
		line-height: 1.15;
	}
	.sv__badge-eyebrow {
		display: block;
		font-size: 9px;
		font-weight: 800;
		letter-spacing: 0.14em;
		text-transform: uppercase;
		opacity: 0.92;
		margin-bottom: 4px;
	}
	.sv__badge-title {
		display: block;
		font-size: 13px;
		font-weight: 800;
		letter-spacing: 0.03em;
		text-transform: uppercase;
	}

	.sv__frame {
		position: relative;
		border-radius: 20px;
		overflow: hidden;
		border: 1px solid var(--border);
		background: var(--bg);
		min-height: clamp(320px, 42vh, 480px);
		flex: 1 1 auto;
		box-shadow: 0 22px 56px color-mix(in srgb, black 14%, transparent);
	}
	.sv__frame-deco {
		position: absolute;
		inset: 0;
		opacity: 0.55;
		background:
			radial-gradient(ellipse 80% 60% at 70% 20%, color-mix(in srgb, var(--sv-accent) 22%, transparent), transparent 62%),
			repeating-linear-gradient(
				125deg,
				transparent 0,
				transparent 14px,
				color-mix(in srgb, var(--sv-accent) 12%, transparent) 14px,
				color-mix(in srgb, var(--sv-accent) 12%, transparent) 15px
			);
		pointer-events: none;
	}
	.sv__img {
		position: relative;
		z-index: 1;
		display: block;
		width: 100%;
		height: 100%;
		min-height: clamp(320px, 42vh, 480px);
		object-fit: cover;
		object-position: center;
		user-select: none;
		-webkit-user-drag: none;
	}
	.sv__ph {
		position: relative;
		z-index: 1;
		min-height: clamp(320px, 42vh, 480px);
		background: color-mix(in srgb, var(--fg-muted) 8%, var(--bg));
	}

	.sv__stats {
		position: absolute;
		z-index: 4;
		left: 8%;
		right: 8%;
		bottom: -18px;
		display: grid;
		grid-template-columns: repeat(3, minmax(0, 1fr));
		gap: 0;
		padding: 14px 12px;
		border-radius: 14px;
		background: var(--bg);
		border: 1px solid var(--border);
		box-shadow: 0 12px 32px color-mix(in srgb, black 14%, transparent);
	}
	@media (max-width: 880px) {
		.sv__stats {
			position: relative;
			left: auto;
			right: auto;
			bottom: auto;
			margin-top: -32px;
		}
	}
	.sv__stat {
		text-align: center;
		padding: 4px 6px;
	}
	.sv__stat:not(:last-child) {
		border-right: 1px solid color-mix(in srgb, var(--border) 85%, transparent);
	}
	.sv__stat-val {
		display: block;
		font-size: 15px;
		font-weight: 800;
		letter-spacing: -0.02em;
		color: var(--fg);
		margin-bottom: 4px;
	}
	.sv__stat-lab {
		font-size: 10px;
		font-weight: 600;
		text-transform: uppercase;
		letter-spacing: 0.08em;
		color: var(--fg-muted);
		line-height: 1.25;
	}
</style>
