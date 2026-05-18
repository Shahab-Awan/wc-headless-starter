<script lang="ts">
	import { type ModuleResolved } from '$lib/config.svelte';
	import { HERO_FONTS } from '$lib/hero-fonts';

	const RX_STATS_FALLBACK = [
		{ value: '≥99%', label: 'VERIFIED PURITY' },
		{ value: '6-panel', label: 'COA EVERY BATCH' },
		{ value: '60+', label: 'RESEARCH COMPOUNDS' },
	];

	let { hero, resolved, brandName } = $props<{
		hero: {
			headline?: string;
			headline_size?: string;
			headline_weight?: string;
			headline_font?: string;
			subheadline?: string;
			subheadline_size?: string;
			show_cta?: boolean;
			cta_text?: string;
			cta_link?: string;
			research_badge?: string;
			cta_secondary_text?: string;
			cta_secondary_link?: string;
			research_stats?: Array<{ value: string; label: string }>;
		};
		resolved?: ModuleResolved;
		brandName?: string;
	}>();

	const accentStyle = $derived(resolved?.accent_color ? `--accent: ${resolved.accent_color};` : '');

	const headlineFontFamily = $derived(
		HERO_FONTS[(hero.headline_font ?? 'inter') as keyof typeof HERO_FONTS]?.family ?? HERO_FONTS.inter.family
	);
	const headlineWeightMap: Record<string, string> = {
		light: '300',
		regular: '400',
		medium: '500',
		semibold: '600',
		bold: '700',
		extrabold: '800',
		black: '900',
	};
	const headlineWt = $derived(headlineWeightMap[hero.headline_weight ?? 'medium'] ?? '500');
	const headlineSz = $derived(
		hero.headline_size === 'xl'
			? 'clamp(2.25rem, 6.8vw, 3.75rem)'
			: hero.headline_size === 'm'
				? 'clamp(1.85rem, 5.2vw, 2.85rem)'
				: hero.headline_size === 's'
					? 'clamp(1.55rem, 4.5vw, 2.25rem)'
					: 'clamp(2.05rem, 6.2vw, 3.45rem)'
	);
	const subSz = $derived(
		hero.subheadline_size === 'l'
			? 'clamp(1rem, 2vw, 1.2rem)'
			: hero.subheadline_size === 's'
				? 'clamp(0.85rem, 1.6vw, 0.98rem)'
				: 'clamp(0.92rem, 1.85vw, 1.1rem)'
	);

	const badgeText = $derived(hero.research_badge?.trim() || '• RESEARCH USE ONLY');
	function ctaLabel(text: string | undefined, fallback: string): string {
		const t = text?.trim() || fallback;
		return /→|->|›/.test(t) ? t : `${t} →`;
	}

	function splitHeadlineLines(text: string): { primary: string; soft?: string } {
		const h = text.trim();
		if (!h) return { primary: '' };
		if (h.includes('\n')) {
			const parts = h.split(/\n+/).map((s) => s.trim()).filter(Boolean);
			return { primary: parts[0] ?? '', soft: parts[1] };
		}
		const peptidesIdx = h.toLowerCase().lastIndexOf(' research peptides');
		if (peptidesIdx > 0) {
			return {
				primary: h.slice(0, peptidesIdx).trim(),
				soft: h.slice(peptidesIdx).trim(),
			};
		}
		const dot = h.indexOf('. ');
		if (dot > 0) {
			return { primary: h.slice(0, dot + 1).trim(), soft: h.slice(dot + 2).trim() };
		}
		return { primary: h };
	}

	const primaryLabel = $derived.by(() => ctaLabel(hero.cta_text, 'Shop All Peptides'));
	const secondaryLabel = $derived.by(() =>
		ctaLabel(hero.cta_secondary_text, 'View COA Library')
	);
	const stats = $derived(
		Array.isArray(hero.research_stats) && hero.research_stats.length > 0
			? hero.research_stats
			: RX_STATS_FALLBACK
	);

	const molPositions = [
		{ c: 'hero-rx__mol--p1', delay: '0s', dur: '22s' },
		{ c: 'hero-rx__mol--p2', delay: '-4s', dur: '28s' },
		{ c: 'hero-rx__mol--p3', delay: '-9s', dur: '19s' },
		{ c: 'hero-rx__mol--p4', delay: '-2s', dur: '31s' },
		{ c: 'hero-rx__mol--p5', delay: '-14s', dur: '25s' },
		{ c: 'hero-rx__mol--p6', delay: '-7s', dur: '34s' },
		{ c: 'hero-rx__mol--p7', delay: '-11s', dur: '21s' },
		{ c: 'hero-rx__mol--p8', delay: '-18s', dur: '27s' },
		{ c: 'hero-rx__mol--p9', delay: '-5s', dur: '29s' },
		{ c: 'hero-rx__mol--p10', delay: '-21s', dur: '23s' },
		{ c: 'hero-rx__mol--p11', delay: '-12s', dur: '26s' },
		{ c: 'hero-rx__mol--p12', delay: '-16s', dur: '32s' },
	];
</script>

<section class="hero-rx" style={accentStyle} aria-label={brandName ? `${brandName} hero` : 'Hero'}>
	<div class="hero-rx__bg" aria-hidden="true">
		<div class="hero-rx__bg-base"></div>
		<div class="hero-rx__bg-glow"></div>
		<div class="hero-rx__mols">
			{#each molPositions as m}
				<div
					class="hero-rx__mol {m.c}"
					style="animation-duration: {m.dur}; animation-delay: {m.delay};"
				>
					<svg viewBox="0 0 56 56" fill="none" aria-hidden="true">
						<polygon
							points="28,7 43.5,16 43.5,34 28,43 12.5,34 12.5,16"
							stroke="currentColor"
							stroke-width="1.1"
							opacity="0.45"
						/>
						<circle cx="28" cy="7" r="3.2" fill="currentColor" opacity="0.35" />
						<circle cx="43.5" cy="16" r="3.2" fill="currentColor" opacity="0.28" />
						<circle cx="43.5" cy="34" r="3.2" fill="currentColor" opacity="0.28" />
						<circle cx="28" cy="43" r="3.2" fill="currentColor" opacity="0.35" />
						<circle cx="12.5" cy="34" r="3.2" fill="currentColor" opacity="0.28" />
						<circle cx="12.5" cy="16" r="3.2" fill="currentColor" opacity="0.28" />
						<line x1="28" y1="16" x2="36" y2="38" stroke="currentColor" stroke-width="0.9" opacity="0.25" />
						<line x1="20" y1="38" x2="36" y2="22" stroke="currentColor" stroke-width="0.9" opacity="0.22" />
					</svg>
				</div>
			{/each}
		</div>
		<div class="hero-rx__bg-orb-outer"></div>
		<div class="hero-rx__bg-orb-core"></div>
	</div>

	<div class="hero-rx__inner">
		<p class="hero-rx__badge">{badgeText}</p>

		{#if hero.headline?.trim()}
			{@const headlineLines = splitHeadlineLines(hero.headline)}
			<h1
				class="hero-rx__title"
				style="font-family: {headlineFontFamily}; font-size: {headlineSz}; --hero-rx-title-weight: {headlineWt};"
			>
				<span class="hero-rx__title-line hero-rx__title-line--primary">{headlineLines.primary}</span>
				{#if headlineLines.soft}
					<span class="hero-rx__title-line hero-rx__title-line--soft">{headlineLines.soft}</span>
				{/if}
			</h1>
		{:else if brandName?.trim()}
			<h1
				class="hero-rx__title"
				style="font-family: {headlineFontFamily}; font-size: {headlineSz}; --hero-rx-title-weight: {headlineWt};"
			>
				<span class="hero-rx__title-line hero-rx__title-line--primary">{brandName}</span>
			</h1>
		{/if}

		{#if hero.subheadline}
			<p class="hero-rx__lede" style="font-size: {subSz};">{hero.subheadline}</p>
		{/if}

		{#if hero.show_cta !== false}
			<div class="hero-rx__ctas">
				<a href={hero.cta_link?.trim() || '/shop'} class="hero-rx__cta hero-rx__cta--primary">
					{primaryLabel}
				</a>
				<a
					href={hero.cta_secondary_link?.trim() || '/coa-library'}
					class="hero-rx__cta hero-rx__cta--secondary"
				>
					{secondaryLabel}
				</a>
			</div>
		{/if}

		{#if stats.length}
			<div class="hero-rx__stats">
				{#each stats as s, i}
					{#if i > 0}<span class="hero-rx__stat-rule" aria-hidden="true"></span>{/if}
					<div class="hero-rx__stat">
						<span class="hero-rx__stat-value">{s.value}</span>
						<span class="hero-rx__stat-label">{s.label}</span>
					</div>
				{/each}
			</div>
		{/if}
	</div>
</section>

<style>
	.hero-rx {
		--rx-fg: color-mix(in srgb, var(--fg) 6%, white 94%);
		--rx-fg-soft: color-mix(in srgb, var(--rx-fg) 72%, transparent);
		--rx-orb-hot: color-mix(in srgb, var(--accent) 30%, white 70%);
		--rx-orb-bloom: color-mix(in srgb, var(--accent) 58%, transparent);
		--rx-orb-edge: color-mix(in srgb, var(--accent) 22%, transparent);
		position: relative;
		min-height: max(min(100svh, 1080px), 92vh);
		display: flex;
		align-items: center;
		justify-content: center;
		padding: clamp(52px, 9vh, 108px) clamp(22px, 4vw, 40px) clamp(68px, 12vh, 132px);
		overflow: hidden;
		isolation: isolate;
		color: var(--rx-fg);
	}

	.hero-rx__bg {
		position: absolute;
		inset: 0;
		z-index: 0;
		pointer-events: none;
	}

	.hero-rx__bg-base {
		position: absolute;
		inset: 0;
		background-color: color-mix(in srgb, black 58%, var(--accent) 10%);
		background-image:
			radial-gradient(
				ellipse 92% 78% at 50% 30%,
				color-mix(in srgb, var(--accent) 52%, transparent) 0%,
				color-mix(in srgb, var(--accent) 22%, transparent) 42%,
				transparent 64%
			),
			radial-gradient(
				ellipse 130% 90% at 50% 100%,
				color-mix(in srgb, black 45%, var(--accent) 18%) 0%,
				transparent 55%
			),
			linear-gradient(
				162deg,
				color-mix(in srgb, var(--accent) 28%, black) 0%,
				color-mix(in srgb, var(--accent) 14%, black) 44%,
				color-mix(in srgb, black 62%, var(--accent) 8%) 100%
			);
		animation: hero-rx-bg-breathe 42s ease-in-out infinite alternate;
	}

	.hero-rx__bg-glow {
		position: absolute;
		inset: -22%;
		background:
			radial-gradient(
				circle at 48% 38%,
				color-mix(in srgb, var(--accent) 32%, transparent) 0%,
				transparent 48%
			),
			radial-gradient(
				ellipse min(44vw, 360px) min(34vw, 260px) at 52% 42%,
				color-mix(in srgb, var(--rx-orb-hot) 22%, transparent) 0%,
				transparent 58%
			);
		animation: hero-rx-glow-shift 36s ease-in-out infinite alternate;
		opacity: 0.88;
	}

	.hero-rx__bg-orb-outer {
		position: absolute;
		left: 50%;
		top: clamp(40%, 46vh, 54%);
		width: min(82vw, 640px);
		height: min(82vw, 640px);
		border-radius: 50%;
		background:
			radial-gradient(
				circle at 48% 42%,
				var(--rx-orb-hot) 0%,
				color-mix(in srgb, var(--accent) 55%, transparent) 14%,
				var(--rx-orb-bloom) 32%,
				var(--rx-orb-edge) 52%,
				transparent 70%
			);
		filter: blur(44px);
		opacity: 0.62;
		transform: translate(-50%, -50%);
		animation: hero-rx-orb-outer 52s ease-in-out infinite alternate;
	}

	.hero-rx__bg-orb-core {
		position: absolute;
		left: 50%;
		top: clamp(40%, 46vh, 54%);
		width: min(46vw, 380px);
		height: min(46vw, 380px);
		border-radius: 50%;
		background: radial-gradient(
			circle at 50% 44%,
			color-mix(in srgb, var(--rx-orb-hot) 88%, transparent) 0%,
			color-mix(in srgb, var(--accent) 62%, transparent) 20%,
			color-mix(in srgb, var(--accent) 28%, transparent) 42%,
			transparent 62%
		);
		filter: blur(22px);
		opacity: 0.72;
		transform: translate(-50%, -50%);
		animation: hero-rx-orb-core 44s ease-in-out infinite alternate;
	}

	@keyframes hero-rx-orb-outer {
		from {
			transform: translate(-50%, -50%) scale(0.96);
			opacity: 0.52;
		}
		to {
			transform: translate(-50%, -50%) scale(1.04);
			opacity: 0.68;
		}
	}

	@keyframes hero-rx-orb-core {
		from {
			transform: translate(-50%, -50%) scale(0.92);
			opacity: 0.62;
		}
		to {
			transform: translate(-50%, -50%) scale(1.08);
			opacity: 0.82;
		}
	}

	@keyframes hero-rx-bg-breathe {
		from {
			filter: saturate(1) hue-rotate(0deg);
		}
		to {
			filter: saturate(1.12) hue-rotate(-8deg);
		}
	}

	@keyframes hero-rx-glow-shift {
		from {
			transform: translate(-2%, -1%) scale(1);
		}
		to {
			transform: translate(3%, 2%) scale(1.05);
		}
	}

	.hero-rx__mols {
		position: absolute;
		inset: 0;
		color: color-mix(in srgb, var(--accent) 55%, var(--rx-fg));
	}

	.hero-rx__mol {
		position: absolute;
		width: min(18vw, 104px);
		height: min(18vw, 104px);
		opacity: 0.18;
		animation-name: hero-rx-float;
		animation-timing-function: ease-in-out;
		animation-iteration-count: infinite;
		animation-direction: alternate;
		will-change: transform;
	}

	.hero-rx__mol svg {
		width: 100%;
		height: 100%;
		display: block;
	}

	.hero-rx__mol--p1 {
		left: 6%;
		top: 14%;
	}
	.hero-rx__mol--p2 {
		right: 8%;
		top: 10%;
	}
	.hero-rx__mol--p3 {
		left: 12%;
		bottom: 18%;
	}
	.hero-rx__mol--p4 {
		right: 14%;
		bottom: 12%;
	}
	.hero-rx__mol--p5 {
		left: 28%;
		top: 8%;
		width: min(14vw, 78px);
		height: min(14vw, 78px);
	}
	.hero-rx__mol--p6 {
		right: 22%;
		top: 36%;
		width: min(12vw, 68px);
		height: min(12vw, 68px);
	}
	.hero-rx__mol--p7 {
		left: 4%;
		top: 48%;
		width: min(11vw, 62px);
		height: min(11vw, 62px);
	}
	.hero-rx__mol--p8 {
		right: 4%;
		top: 52%;
		width: min(15vw, 86px);
		height: min(15vw, 86px);
	}
	.hero-rx__mol--p9 {
		left: 38%;
		bottom: 8%;
		width: min(13vw, 74px);
		height: min(13vw, 74px);
	}
	.hero-rx__mol--p10 {
		right: 38%;
		bottom: 22%;
		width: min(10vw, 56px);
		height: min(10vw, 56px);
	}
	.hero-rx__mol--p11 {
		left: 52%;
		top: 18%;
		width: min(9vw, 50px);
		height: min(9vw, 50px);
	}
	.hero-rx__mol--p12 {
		left: 46%;
		bottom: 38%;
		width: min(16vw, 92px);
		height: min(16vw, 92px);
		opacity: 0.1;
	}

	@keyframes hero-rx-float {
		from {
			transform: translate(0, 0) rotate(0deg) scale(1);
			opacity: 0.1;
		}
		to {
			transform: translate(14px, -18px) rotate(8deg) scale(1.06);
			opacity: 0.26;
		}
	}

	.hero-rx__inner {
		position: relative;
		z-index: 1;
		width: 100%;
		max-width: 860px;
		margin: 0 auto;
		text-align: center;
		display: flex;
		flex-direction: column;
		align-items: center;
	}

	.hero-rx__badge {
		display: inline-flex;
		align-items: center;
		gap: 8px;
		margin: 0 0 clamp(18px, 3vw, 28px);
		padding: 8px 18px;
		border-radius: 999px;
		border: 1px solid color-mix(in srgb, var(--rx-fg) 35%, transparent);
		font-size: 11px;
		font-weight: 600;
		letter-spacing: 0.16em;
		text-transform: uppercase;
		color: var(--rx-fg-soft);
		background: color-mix(in srgb, var(--bg) 12%, transparent);
	}

	.hero-rx__title {
		margin: 0 0 clamp(16px, 2.5vw, 24px);
		display: flex;
		flex-direction: column;
		align-items: center;
		gap: 0.14em;
		line-height: 1.06;
		letter-spacing: -0.03em;
		font-weight: var(--hero-rx-title-weight, 500);
		text-wrap: balance;
	}
	.hero-rx__title-line {
		display: block;
	}
	.hero-rx__title-line--primary {
		color: var(--rx-fg);
	}
	.hero-rx__title-line--soft {
		color: color-mix(in srgb, var(--rx-fg) 58%, var(--accent) 42%);
	}

	.hero-rx__lede {
		margin: 0 0 clamp(28px, 4vw, 40px);
		line-height: 1.55;
		max-width: 52ch;
		color: var(--rx-fg-soft);
		font-weight: 450;
		text-wrap: balance;
	}

	.hero-rx__ctas {
		display: flex;
		flex-wrap: wrap;
		align-items: center;
		justify-content: center;
		gap: 12px;
		margin-bottom: clamp(36px, 6vw, 56px);
	}

	.hero-rx__cta {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		min-height: 48px;
		padding: 14px 28px;
		border-radius: 999px;
		font-size: 13px;
		font-weight: 600;
		letter-spacing: 0.04em;
		text-decoration: none;
		border: 1px solid transparent;
		transition:
			transform var(--dur-fast) var(--ease),
			background var(--dur-fast) var(--ease),
			color var(--dur-fast) var(--ease),
			border-color var(--dur-fast) var(--ease),
			filter var(--dur-fast) var(--ease);
	}

	.hero-rx__cta--primary {
		background: var(--rx-fg);
		color: color-mix(in srgb, var(--accent) 18%, #0a0a0a);
		border-color: var(--rx-fg);
	}

	.hero-rx__cta--primary:hover {
		filter: brightness(1.04);
	}

	.hero-rx__cta--secondary {
		background: transparent;
		color: var(--rx-fg);
		border-color: color-mix(in srgb, var(--rx-fg) 65%, transparent);
	}

	.hero-rx__cta--secondary:hover {
		border-color: var(--rx-fg);
		background: color-mix(in srgb, var(--rx-fg) 10%, transparent);
	}

	.hero-rx__cta:active {
		transform: scale(0.98);
	}

	.hero-rx__stats {
		display: flex;
		flex-wrap: wrap;
		align-items: stretch;
		justify-content: center;
		gap: 0;
		width: 100%;
		max-width: 720px;
		padding-top: clamp(8px, 2vw, 16px);
		border-top: 1px solid color-mix(in srgb, var(--rx-fg) 14%, transparent);
	}

	.hero-rx__stat {
		flex: 1 1 140px;
		min-width: 120px;
		padding: clamp(12px, 2vw, 20px) clamp(10px, 2vw, 22px);
		text-align: center;
		display: flex;
		flex-direction: column;
		align-items: center;
		gap: 6px;
	}

	.hero-rx__stat-rule {
		display: none;
		width: 1px;
		align-self: stretch;
		margin: 12px 0;
		background: color-mix(in srgb, var(--rx-fg) 18%, transparent);
	}

	.hero-rx__stat-value {
		font-size: clamp(1.35rem, 3.2vw, 1.85rem);
		font-weight: 700;
		letter-spacing: -0.03em;
		line-height: 1.1;
		color: var(--rx-fg);
		font-variant-numeric: tabular-nums;
	}

	.hero-rx__stat-label {
		font-size: 10px;
		font-weight: 600;
		letter-spacing: 0.14em;
		text-transform: uppercase;
		color: var(--rx-fg-soft);
		line-height: 1.35;
		max-width: 28ch;
	}

	@media (min-width: 640px) {
		.hero-rx__stats {
			flex-wrap: nowrap;
		}
		.hero-rx__stat-rule {
			display: block;
		}
	}

	/* Mobile: shorter hero, tighter top spacing, row layouts for stats + CTAs. */
	@media (max-width: 639px) {
		.hero-rx {
			min-height: min(78svh, 640px);
			padding: 16px 16px 36px;
		}
		.hero-rx__inner {
			padding-inline: 4px;
		}
		.hero-rx__badge {
			margin-bottom: 10px;
			padding: 6px 14px;
			font-size: 10px;
		}
		.hero-rx__title {
			font-size: clamp(1.85rem, 7.5vw, 2.45rem) !important;
			--hero-rx-title-weight: 500;
			line-height: 1.06;
			margin-bottom: 12px;
			letter-spacing: -0.03em;
		}
		.hero-rx__lede {
			font-size: clamp(0.86rem, 3.4vw, 0.98rem) !important;
			margin-bottom: 18px;
			line-height: 1.45;
		}
		.hero-rx__ctas {
			flex-direction: row;
			flex-wrap: nowrap;
			justify-content: center;
			gap: 10px;
			width: 100%;
			max-width: 100%;
			margin-bottom: 20px;
		}
		.hero-rx__cta {
			flex: 1 1 0;
			min-width: 0;
			width: auto;
			max-width: none;
			padding: 12px 14px;
			font-size: 10px;
			letter-spacing: 0.03em;
			min-height: 44px;
		}
		.hero-rx__stats {
			flex-direction: row;
			flex-wrap: nowrap;
			align-items: stretch;
			border-top: 1px solid color-mix(in srgb, var(--rx-fg) 14%, transparent);
			padding-top: 10px;
		}
		.hero-rx__stat {
			flex: 1 1 0;
			min-width: 0;
			padding: 8px 4px;
			border-bottom: none;
		}
		.hero-rx__stat-rule {
			display: block;
			margin: 8px 0;
		}
		.hero-rx__stat-value {
			font-size: clamp(1rem, 4.2vw, 1.25rem);
		}
		.hero-rx__stat-label {
			font-size: 8px;
			letter-spacing: 0.1em;
			max-width: none;
		}
	}

	@media (prefers-reduced-motion: reduce) {
		.hero-rx__bg-base,
		.hero-rx__bg-glow,
		.hero-rx__bg-orb-outer,
		.hero-rx__bg-orb-core,
		.hero-rx__mol {
			animation: none !important;
		}
		.hero-rx__bg-orb-outer {
			opacity: 0.52;
			transform: translate(-50%, -50%) scale(1);
		}
		.hero-rx__bg-orb-core {
			opacity: 0.58;
			transform: translate(-50%, -50%) scale(1);
		}
		.hero-rx__mol {
			opacity: 0.12;
		}
	}
</style>
