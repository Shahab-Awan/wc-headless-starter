<script lang="ts">
	/**
	 * Hero — reusable hero section. Used for the homepage's top-of-page
	 * hero (hardcoded in +page.svelte) AND as a module that can be placed
	 * on any page via the module system. Accepts a full hero config plus
	 * an optional module-resolved bag so per-module accent + font overrides
	 * scope cleanly.
	 *
	 * Multi-instance safe: each mount owns its own ResizeObserver, WebGL
	 * context, and bound heroBoxEl/heroImgEl. Chrome's WebGL context limit
	 * (~16 per tab) means pages with many hero modules all using WebGL
	 * backgrounds will degrade gracefully via HeroWebGL*'s failed-state
	 * fallback added earlier.
	 *
	 * Typography override: when the hero module sets its own headline_font,
	 * the +layout.svelte onMount + config.svelte.ts preview patch calls
	 * loadFont() to ensure the Bunny stylesheet is present.
	 */
	import HeroResearchMotion from '$lib/components/HeroResearchMotion.svelte';
	import { browser } from '$app/environment';
	import { config, type ModuleResolved } from '$lib/config.svelte';
	import { HERO_FONTS } from '$lib/hero-fonts';

	let { hero, resolved, showEyebrow = true, showRating = true, showTrust = true, brandName } = $props<{
		hero: {
			layout?: string;
			image_desktop?: string;
			image_mobile?: string;
			image_position_x?: number;
			image_position_y?: number;
			image_position_mobile_x?: number;
			image_position_mobile_y?: number;
			image_zoom?: number;
			image_zoom_mobile?: number;
			variant?: string;
			content_mode?: 'text' | 'logo';
			logo_source?: 'site_logo' | 'custom';
			logo_url?: string;
			logo_dark_url?: string;
			logo_size?: 'standard' | 'large' | 'statement';
			headline?: string;
			headline_size?: 's' | 'm' | 'l' | 'xl';
			headline_weight?: string;
			headline_font?: string;
			subheadline?: string;
			subheadline_size?: 's' | 'm' | 'l';
			text_color_mode?: 'theme' | 'white' | 'black' | 'accent';
			show_cta?: boolean;
			cta_text?: string;
			cta_link?: string;
			cta_accent?: boolean;
			show_eyebrow?: boolean;
			show_rating?: boolean;
			rating_text?: string;
			trust_items?: Array<{ icon?: string; text?: string }>;
		};
		resolved?: ModuleResolved;
		showEyebrow?: boolean;
		showRating?: boolean;
		showTrust?: boolean;
		brandName?: string;
	}>();

	const layout = $derived(hero.layout || 'left');

	const HEADLINE_SIZES = {
		s:  'clamp(32px, 5vw, 56px)',
		m:  'clamp(40px, 7vw, 80px)',
		l:  'clamp(64px, 11vw, 136px)',
		xl: 'clamp(80px, 13vw, 176px)',
	};
	const SUBHEADLINE_SIZES = {
		s: 'clamp(14px, 1.3vw, 16px)',
		m: 'clamp(16px, 1.5vw, 19px)',
		l: 'clamp(18px, 1.8vw, 22px)',
	};
	const WEIGHT_MAP: Record<string, string> = {
		light: '300', regular: '400', medium: '500', semibold: '600',
		bold: '700', extrabold: '800', black: '900',
	};
	const headlineFontSize   = $derived(HEADLINE_SIZES[(hero.headline_size ?? 'l') as keyof typeof HEADLINE_SIZES] || HEADLINE_SIZES.l);
	const headlineFontWeight = $derived(WEIGHT_MAP[hero.headline_weight ?? 'medium'] || '500');
	const subFontSize        = $derived(SUBHEADLINE_SIZES[(hero.subheadline_size ?? 'm') as keyof typeof SUBHEADLINE_SIZES] || SUBHEADLINE_SIZES.m);
	const headlineFontFamily = $derived(HERO_FONTS[(hero.headline_font ?? 'inter') as keyof typeof HERO_FONTS]?.family ?? HERO_FONTS.inter.family);

	const HERO_TEXT_COLOR: Record<string, string | null> = {
		theme: null,
		white: '#ffffff',
		black: '#000000',
		accent: 'var(--accent)',
	};
	const heroTextColor = $derived(HERO_TEXT_COLOR[hero.text_color_mode ?? 'theme']);
	const heroContentMode = $derived(hero.content_mode ?? 'text');
	const heroLogoSource = $derived(hero.logo_source ?? 'site_logo');
	const resolvedHeroLogoUrl = $derived.by(() => {
		if (heroContentMode !== 'logo') return null;
		if (heroLogoSource === 'site_logo') {
			return config.data.logo_full_url || config.data.logo_url || null;
		}
		return hero.logo_url || null;
	});
	const resolvedHeroLogoDarkUrl = $derived.by(() => {
		if (heroContentMode !== 'logo') return null;
		if (heroLogoSource === 'site_logo') {
			return config.data.logo_dark_full_url || config.data.logo_dark_url || null;
		}
		return hero.logo_dark_url || null;
	});
	const heroLogoSize = $derived(hero.logo_size ?? 'large');
	const heroLogoAutoInvert = $derived(
		heroContentMode === 'logo' &&
		heroLogoSource === 'site_logo' &&
		!!resolvedHeroLogoUrl &&
		!resolvedHeroLogoDarkUrl &&
		!!config.data.logo_invert_on_dark
	);
	const showVisibleHeadline = $derived(heroContentMode !== 'logo' || !resolvedHeroLogoUrl);

	// Module-scoped accent override — applies var(--accent) only inside this
	// hero's subtree, leaving the global accent untouched for sibling content.
	const accentStyle = $derived(resolved?.accent_color ? `--accent: ${resolved.accent_color};` : '');

	// Image layout — JS-driven so zoom slider has true reveal-more semantics
	// (see notes in +page.svelte extraction source). Multi-instance safe
	// because heroBoxEl/heroImgEl are let-bindings per component instance.
	let heroBoxEl = $state<HTMLElement | undefined>();
	let heroImgEl = $state<HTMLImageElement | undefined>();
	let heroImgNat = $state<{ w: number; h: number }>({ w: 0, h: 0 });
	let heroIsMobile = $state(false);

	$effect(() => {
		if (typeof window === 'undefined') return;
		const mq = window.matchMedia('(max-width: 639px)');
		heroIsMobile = mq.matches;
		const onChange = (e: MediaQueryListEvent) => { heroIsMobile = e.matches; };
		mq.addEventListener('change', onChange);
		return () => mq.removeEventListener('change', onChange);
	});

	$effect(() => {
		if (!heroImgEl || !heroBoxEl) return;
		const img = heroImgEl;
		const box = heroBoxEl;
		const onLoad = () => { heroImgNat = { w: img.naturalWidth, h: img.naturalHeight }; };
		if (img.complete && img.naturalWidth > 0) onLoad();
		img.addEventListener('load', onLoad);
		const ro = new ResizeObserver(() => applyHeroImgLayout());
		ro.observe(box);
		return () => {
			img.removeEventListener('load', onLoad);
			ro.disconnect();
		};
	});

	$effect(() => {
		void heroImgNat.w; void heroImgNat.h; void heroIsMobile;
		void hero.image_zoom; void hero.image_zoom_mobile;
		void hero.image_position_x; void hero.image_position_y;
		void hero.image_position_mobile_x; void hero.image_position_mobile_y;
		applyHeroImgLayout();
	});

	function applyHeroImgLayout() {
		if (!heroImgEl || !heroBoxEl) return;
		const { w: natW, h: natH } = heroImgNat;
		if (!natW || !natH) return;
		const boxW = heroBoxEl.clientWidth;
		const boxH = heroBoxEl.clientHeight;
		if (!boxW || !boxH) return;
		const zoomPct = heroIsMobile ? (hero.image_zoom_mobile ?? hero.image_zoom ?? 100) : (hero.image_zoom ?? 100);
		const posX = heroIsMobile ? (hero.image_position_mobile_x ?? hero.image_position_x ?? 50) : (hero.image_position_x ?? 50);
		const posY = heroIsMobile ? (hero.image_position_mobile_y ?? hero.image_position_y ?? 80) : (hero.image_position_y ?? 50);
		const coverScale = Math.max(boxW / natW, boxH / natH);
		const z = zoomPct / 100;
		const imgW = natW * coverScale * z;
		const imgH = natH * coverScale * z;
		heroImgEl.style.width  = `${imgW}px`;
		heroImgEl.style.height = `${imgH}px`;
		heroImgEl.style.left   = `${(boxW - imgW) * posX / 100}px`;
		heroImgEl.style.top    = `${(boxH - imgH) * posY / 100}px`;
	}

	// Trust pill icons — hand-tuned stroke geometry.
	const trustIcons: Record<string, string> = {
		check: 'M5 12.5l4.2 4.2L19 7',
		shield: 'M12 3.6 18.4 6v5.5c0 4-2.5 7.5-6.4 8.9-3.9-1.4-6.4-4.9-6.4-8.9V6ZM9.3 12.2l1.9 1.9 3.6-3.9',
		star: 'M12 3.6l2.3 4.9 5.4.8-3.9 3.8.9 5.3-4.7-2.5-4.8 2.5.9-5.3L4.2 9.3l5.4-.8L12 3.6z',
		shipping: 'M3.8 8.8h10.2v7.7H3.8zM14 11h3.1l3.1 3.2v2.3H14',
		lock: 'M4.5 11h15v9.5a1.5 1.5 0 0 1-1.5 1.5H6a1.5 1.5 0 0 1-1.5-1.5V11zM7.5 11V8a4.5 4.5 0 0 1 9 0v3',
		lab: 'M9 4.8h6M10.2 4.8v4.3L6.5 16a3.5 3.5 0 0 0 3.1 5.2h4.8a3.5 3.5 0 0 0 3.1-5.2l-3.7-6.9V4.8M9.1 14.6h5.8',
		heart: 'M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z',
		leaf: 'M17 8C8 10 5.9 16.17 3.82 21.34l1.89.66.95-2.3c.48.17.98.3 1.34.3C19 20 22 3 22 3c-1 2-8 2.25-13 3.25S2 11.5 2 13.5s1.75 3.75 1.75 3.75',
		zap: 'M13 2L3 14h9l-1 8 10-12h-9z',
		award: 'M12 9a5.5 5.5 0 1 0 0-11 5.5 5.5 0 0 0 0 11zM8.5 13.5L7 22l5-3 5 3-1.5-8.5',
	};

	const displayBrandName = $derived(brandName ?? config.data.brand_name);
</script>

{#if hero.variant === 'research-motion'}
	<HeroResearchMotion hero={hero} {resolved} brandName={displayBrandName} />
{:else}
<section
	class="hero hero--{layout}"
	style="--hero-mobile-pad: {430 + ((hero.image_position_mobile_y ?? 50) - 50) * 2}px;{heroTextColor ? ` --hero-text: ${heroTextColor};` : ''}{accentStyle}"
>
	{#if hero.image_desktop}
		<picture class="hero__image-ambient" aria-hidden="true">
			{#if hero.image_mobile}
				<source media="(max-width: 639px)" srcset={hero.image_mobile} />
			{/if}
			<img src={hero.image_desktop} alt="" loading="eager" />
		</picture>
		<picture class="hero__image" bind:this={heroBoxEl}>
			{#if hero.image_mobile}
				<source media="(max-width: 639px)" srcset={hero.image_mobile} />
			{/if}
			<img bind:this={heroImgEl} src={hero.image_desktop} alt="" loading="eager" />
		</picture>
	{/if}
	{#if browser && hero.variant === 'webgl-noise'}
		{#await import('$lib/components/HeroWebGL.svelte') then mod}
			{@const C = mod.default}
			<C />
		{/await}
	{:else if browser && hero.variant === 'webgl-variant-2'}
		{#await import('$lib/components/HeroWebGLVariant2.svelte') then mod}
			{@const C = mod.default}
			<C />
		{/await}
	{:else if browser && hero.variant === 'webgl-variant-3'}
		{#await import('$lib/components/HeroWebGLVariant3.svelte') then mod}
			{@const C = mod.default}
			<C />
		{/await}
	{:else if browser && hero.variant === 'webgl-variant-4'}
		{#await import('$lib/components/HeroWebGLVariant4.svelte') then mod}
			{@const C = mod.default}
			<C />
		{/await}
	{:else if browser && hero.variant === 'webgl-variant-5'}
		{#await import('$lib/components/HeroWebGLVariant5.svelte') then mod}
			{@const C = mod.default}
			<C />
		{/await}
	{:else if browser && hero.variant === 'webgl-variant-6'}
		{#await import('$lib/components/HeroWebGLVariant6.svelte') then mod}
			{@const C = mod.default}
			<C />
		{/await}
	{/if}
	<div class="hero__inner">
		{#if showRating && hero.show_rating && hero.rating_text}
			<div class="hero__rating">
				<svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor" stroke="none"><polygon points="12,2 15.09,8.26 22,9.27 17,14.14 18.18,21.02 12,17.77 5.82,21.02 7,14.14 2,9.27 8.91,8.26"/></svg>
				<span>{hero.rating_text}</span>
			</div>
		{/if}
		{#if showEyebrow && hero.show_eyebrow !== false && displayBrandName}
			<p class="hero__eyebrow">{displayBrandName}</p>
		{/if}
		{#if heroContentMode === 'logo' && resolvedHeroLogoUrl}
			<h1 class="hero__title hero__title--sr">{hero.headline || displayBrandName}</h1>
		{/if}
		{#if !showVisibleHeadline && resolvedHeroLogoUrl}
			<div class="hero__logo-lockup hero__logo-lockup--{heroLogoSize}">
				<img
					class="hero__logo-image hero__logo-image--size-{heroLogoSize}"
					class:hero__logo-image--light-variant={resolvedHeroLogoDarkUrl}
					class:hero__logo-image--auto-invert={heroLogoAutoInvert}
					src={resolvedHeroLogoUrl}
					alt={displayBrandName}
				/>
				{#if resolvedHeroLogoDarkUrl}
					<img
						class="hero__logo-image hero__logo-image--dark-variant hero__logo-image--size-{heroLogoSize}"
						src={resolvedHeroLogoDarkUrl}
						alt=""
						aria-hidden="true"
					/>
				{/if}
			</div>
		{:else if hero.headline}
			<h1 class="hero__title" style="font-size: {headlineFontSize}; font-weight: {headlineFontWeight}; font-family: {headlineFontFamily};">{hero.headline}</h1>
		{/if}
		{#if hero.subheadline}
			<p class="hero__lede" style="font-size: {subFontSize};">{hero.subheadline}</p>
		{/if}
		{#if hero.show_cta !== false && hero.cta_text}
			<div class="hero__cta">
				<a href={hero.cta_link || '#'} class="hero__cta-primary" class:hero__cta-primary--neutral={!hero.cta_accent}>{hero.cta_text}</a>
			</div>
		{/if}
		{#if showTrust && hero.trust_items?.length}
			<div class="hero__trust">
				<div class="hero__trust-track">
					{#each hero.trust_items as ti, i}
						{#if i > 0}<span class="hero__trust-dot">·</span>{/if}
						<span class="hero__trust-item">
							{#if ti.icon && trustIcons[ti.icon]}
								<svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><path d={trustIcons[ti.icon]} /></svg>
							{/if}
							{ti.text}
						</span>
					{/each}
				</div>
			</div>
		{/if}
	</div>
</section>
{/if}

<style>
	.hero {
		position: relative;
		min-height: 82vh;
		display: flex;
		align-items: center;
		padding: 140px 28px 80px;
		overflow: hidden;
		background: var(--bg);
		isolation: isolate;
	}
	.hero::after {
		content: '';
		position: absolute;
		inset: auto 0 0 0;
		height: 240px;
		background: linear-gradient(
			to bottom,
			transparent 0%,
			color-mix(in srgb, var(--bg) 15%, transparent) 35%,
			color-mix(in srgb, var(--bg) 45%, transparent) 65%,
			color-mix(in srgb, var(--bg) 80%, transparent) 85%,
			var(--bg) 100%
		);
		pointer-events: none;
		z-index: 1;
	}
	.hero__image-ambient {
		position: absolute;
		inset: 0;
		z-index: 0;
		overflow: hidden;
		pointer-events: none;
	}
	.hero__image-ambient img {
		width: 100%;
		height: 100%;
		object-fit: cover;
		transform: scale(1.15);
		filter: blur(48px) saturate(1.1);
	}
	.hero__image {
		position: absolute;
		inset: 0;
		z-index: 1;
		overflow: hidden;
		pointer-events: none;
	}
	.hero__image img {
		position: absolute;
		max-width: none;
		max-height: none;
		mix-blend-mode: multiply;
		opacity: 0.9;
	}
	:global([data-theme='dark']) .hero__image img {
		mix-blend-mode: screen;
		opacity: 0.85;
	}
	.hero__image::after {
		content: '';
		position: absolute;
		inset: 0;
		background: linear-gradient(
			to right,
			color-mix(in srgb, var(--bg) 48%, transparent) 0%,
			color-mix(in srgb, var(--bg) 38%, transparent) 20%,
			color-mix(in srgb, var(--bg) 22%, transparent) 45%,
			color-mix(in srgb, var(--bg) 8%, transparent) 65%,
			transparent 85%
		);
		z-index: 2;
		pointer-events: none;
	}
	.hero__inner {
		position: relative;
		z-index: 2;
		max-width: 820px;
		margin: 0;
		padding-left: clamp(28px, 4vw, 100px);
	}
	.hero__eyebrow {
		font-size: 12px;
		font-weight: 500;
		text-transform: uppercase;
		letter-spacing: 0.18em;
		color: var(--hero-text, var(--fg));
		margin: 0 0 32px;
		opacity: 0.75;
	}
	.hero__title {
		font-family: var(--font-sans);
		font-size: clamp(64px, 11vw, 136px);
		font-weight: 700;
		line-height: 0.86;
		letter-spacing: -0.045em;
		margin: 0 0 32px;
		color: var(--hero-text, var(--fg));
	}
	.hero__title--sr {
		position: absolute;
		width: 1px;
		height: 1px;
		padding: 0;
		margin: -1px;
		overflow: hidden;
		clip: rect(0, 0, 0, 0);
		white-space: nowrap;
		border: 0;
	}
	.hero__logo-lockup {
		position: relative;
		display: inline-flex;
		width: min(100%, var(--hero-logo-max-width, 520px));
		margin: 0 0 32px;
	}
	.hero__logo-lockup--standard { --hero-logo-max-width: clamp(240px, 34vw, 420px); }
	.hero__logo-lockup--large { --hero-logo-max-width: clamp(320px, 46vw, 620px); }
	.hero__logo-lockup--statement { --hero-logo-max-width: clamp(380px, 58vw, 760px); }
	.hero__logo-image {
		display: block;
		width: 100%;
		height: auto;
	}
	.hero__logo-image--dark-variant {
		display: none;
	}
	:global([data-theme='dark']) .hero__logo-image--light-variant {
		display: none;
	}
	:global([data-theme='dark']) .hero__logo-image--dark-variant {
		display: block;
	}
	:global([data-theme='dark']) .hero__logo-image--auto-invert {
		filter: invert(1) brightness(1.04);
	}
	.hero__lede {
		font-size: 18px;
		line-height: 1.4;
		letter-spacing: -0.28px;
		color: var(--hero-text, var(--fg));
		opacity: 0.72;
		max-width: 560px;
		margin: 0 0 44px;
	}
	.hero__cta {
		display: flex;
		align-items: center;
		gap: 20px;
		flex-wrap: wrap;
	}
	.hero__cta-primary {
		display: inline-flex;
		align-items: center;
		padding: 16px 32px;
		background: linear-gradient(180deg, color-mix(in srgb, var(--accent), white 15%) 0%, var(--accent) 50%, color-mix(in srgb, var(--accent), black 10%) 100%);
		color: var(--accent-fg);
		border: 1px solid color-mix(in srgb, var(--accent), black 15%);
		border-radius: var(--radius-sm);
		text-decoration: none;
		font-size: 12px;
		font-weight: 600;
		text-transform: uppercase;
		letter-spacing: 0.1em;
		transition:
			background var(--dur-fast) var(--ease),
			color var(--dur-fast) var(--ease),
			transform var(--dur-fast) var(--ease);
	}
	.hero__cta-primary:hover {
		background: transparent;
		color: var(--accent);
	}
	.hero__cta-primary--neutral {
		background: var(--fg);
		color: var(--bg);
		border-color: var(--fg);
	}
	.hero__cta-primary--neutral:hover {
		background: transparent;
		color: var(--fg);
	}
	.hero__cta-primary:active {
		transform: scale(0.98);
	}
	.hero__rating {
		display: inline-flex;
		align-items: center;
		gap: 6px;
		margin-bottom: 20px;
		font-size: 13px;
		font-weight: 600;
		color: var(--hero-text, var(--fg));
	}
	.hero__rating svg {
		color: var(--accent, #ffdd24);
	}
	.hero__trust {
		margin-top: 24px;
		font-weight: 500;
		color: var(--hero-text, var(--fg));
		opacity: 0.7;
		max-width: 100%;
	}
	.hero__trust-track {
		display: inline-flex;
		align-items: center;
		flex-wrap: nowrap;
		gap: 0;
		white-space: nowrap;
	}
	.hero__trust-item {
		display: inline-flex;
		align-items: center;
		gap: 4px;
		white-space: nowrap;
	}
	.hero__trust-item svg {
		opacity: 0.8;
	}
	.hero__trust-dot {
		margin: 0 6px;
		opacity: 0.4;
	}

	@media (max-width: 639px) {
		.hero, :where(.hero--left, .hero--center, .hero--split, .hero--bottom) {
			min-height: calc(100svh - 45px);
			padding: 80px 24px 100px;
			align-items: center;
			justify-content: center;
		}
		.hero__inner,
		:where(.hero--left, .hero--center, .hero--split, .hero--bottom) .hero__inner {
			width: 100%;
			max-width: 100%;
			padding-left: 0;
			padding-right: 0;
			margin: 0 auto;
			text-align: center;
			display: flex;
			flex-direction: column;
			align-items: center;
		}
		.hero__rating,
		.hero__eyebrow,
		.hero__title,
		.hero__lede,
		.hero__cta,
		.hero__trust,
		.hero__trust-track {
			text-align: center;
			justify-content: center;
			align-self: center;
		}
		.hero__lede {
			margin-left: auto;
			margin-right: auto;
		}
		.hero__title {
			font-size: clamp(40px, 10vw, 64px);
		}
		.hero__logo-lockup--standard { --hero-logo-max-width: min(78vw, 320px); }
		.hero__logo-lockup--large { --hero-logo-max-width: min(84vw, 420px); }
		.hero__logo-lockup--statement { --hero-logo-max-width: min(90vw, 520px); }
		.hero__lede {
			max-width: 36ch;
		}
		.hero__cta {
			flex-direction: column;
			width: 100%;
			gap: 12px;
		}
		.hero__cta-primary {
			width: 100%;
			justify-content: center;
			padding: 18px 32px;
			font-size: 14px;
		}
		.hero__trust {
			margin-top: 14px;
			width: 100%;
			overflow-x: auto;
			overflow-y: hidden;
			-webkit-overflow-scrolling: touch;
			scrollbar-width: none;
			-ms-overflow-style: none;
			padding-bottom: 4px;
			overscroll-behavior-x: contain;
		}
		.hero__trust::-webkit-scrollbar {
			display: none;
		}
		.hero__trust-track {
			width: max-content;
			min-width: 100%;
			font-size: clamp(10px, 2.8vw, 12px);
			justify-content: center;
		}
		.hero__trust-item {
			gap: 3px;
		}
		.hero__trust-item svg {
			width: 10px;
			height: 10px;
			flex: 0 0 auto;
		}
		.hero__trust-dot {
			margin: 0 4px;
		}
		.hero__image {
			display: block;
		}
		.hero__image::after {
			background: linear-gradient(
				180deg,
				color-mix(in srgb, var(--bg) 60%, transparent) 0%,
				color-mix(in srgb, var(--bg) 48%, transparent) 20%,
				color-mix(in srgb, var(--bg) 30%, transparent) 45%,
				color-mix(in srgb, var(--bg) 14%, transparent) 65%,
				transparent 85%
			);
		}
		.hero {
			background: var(--bg);
			min-height: max(1010px, calc(100svh - 45px));
			padding-bottom: var(--hero-mobile-pad, 530px);
			padding-top: 10px;
			align-items: flex-start;
		}
	}

	.hero--center {
		justify-content: center;
	}
	.hero--center .hero__inner {
		display: flex;
		flex-direction: column;
		align-items: center;
		text-align: center;
		max-width: 820px;
		margin: 0 auto;
		padding-left: 28px;
		padding-right: 28px;
	}
	.hero--center .hero__rating,
	.hero--center .hero__eyebrow,
	.hero--center .hero__title,
	.hero--center .hero__lede,
	.hero--center .hero__cta,
	.hero--center .hero__trust,
	.hero--center .hero__trust-track {
		width: 100%;
		text-align: center;
		justify-content: center;
	}
	.hero--center .hero__cta {
		flex-direction: column;
		align-items: center;
		gap: 8px;
	}
	.hero--bottom {
		align-items: flex-end;
	}
	.hero--bottom .hero__inner {
		padding-bottom: 20px;
	}
	@media (max-width: 639px) {
		.hero--bottom {
			align-items: flex-end;
		}
		.hero--bottom .hero__inner {
			text-align: center;
			align-items: center;
		}
	}
</style>
