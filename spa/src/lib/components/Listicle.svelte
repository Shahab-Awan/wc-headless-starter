<script lang="ts">
	import type {
		ListicleItem,
		ListicleModuleConfig,
		ModuleResolved,
		OrderHandlingModuleConfig,
		PromoOfferModuleConfig,
		ReviewsListicleModuleConfig,
		SpacingPreset,
	} from '$lib/config.svelte';
	import { bridgeAwareHref } from '$lib/bridge-domain';
	import { icons as listicleIcons } from '$lib/icons';
	import PromoOffer from '$lib/components/PromoOffer.svelte';
	import OrderHandling from '$lib/components/OrderHandling.svelte';
	import ReviewsListicle from '$lib/components/ReviewsListicle.svelte';

	const MID_PROMO_AFTER_INDEX = 3;
	const REASON_FOUR_INDEX = 3;

	let {
		config,
		resolved,
		spacing_v = 'normal',
		spacing_h = 'normal',
		midPromo,
		midReviews,
		tailProcess,
	}: {
		config: ListicleModuleConfig;
		resolved?: ModuleResolved;
		spacing_v?: SpacingPreset;
		spacing_h?: SpacingPreset;
		midPromo?: {
			config: PromoOfferModuleConfig;
			resolved?: ModuleResolved;
			spacing_v?: SpacingPreset;
			spacing_h?: SpacingPreset;
			afterIndex?: number;
		};
		midReviews?: {
			config: ReviewsListicleModuleConfig;
			resolved?: ModuleResolved;
			spacing_v?: SpacingPreset;
			spacing_h?: SpacingPreset;
			afterIndex?: number;
		};
		tailProcess?: {
			config: OrderHandlingModuleConfig;
			resolved?: ModuleResolved;
			spacing_v?: SpacingPreset;
			spacing_h?: SpacingPreset;
			center_header?: boolean;
		};
	} = $props();

	const midBreakAfterIndex = $derived(
		midPromo?.afterIndex ?? midReviews?.afterIndex ?? MID_PROMO_AFTER_INDEX
	);

	const accentStyle = $derived(
		resolved?.accent_color ? `--accent: ${resolved.accent_color};` : ''
	);

	const EDITORIAL_DEFAULTS = {
		headline: '8 Reasons Researchers Choose Alyve For their Research Compounds',
		trust_brand: 'Alyve Peptides',
		trust_items: [
			'99%+ HPLC Verified',
			'3rd-Party Tested Every Batch',
			'COA Pre-Purchase',
		],
		hero_callout:
			'READ THIS BEFORE YOU BUY RESEARCH COMPOUNDS FROM ANY OTHER COMPANY',
		hero_cta_image: '/wp-content/uploads/2026/05/e33abf7d-1bcf-42ea-b324-c777cec4006d.webp',
		hero_cta_image_alt: 'Alyve research-grade peptide vials',
		hero_cta_headline: 'Up to 40% Off — Verified Batches In Stock',
		hero_cta_label: 'Shop Now — Check Availability',
		hero_cta_href: '/shop',
	} as const;

	const isEditorialHero = $derived((config.hero_layout ?? 'editorial') === 'editorial');

	const DEFAULT_LISTICLE_ITEMS: ListicleItem[] = [
		{
			icon: 'shipping',
			headline: 'Domestic Fulfillment, Direct to Your Lab',
			body: '<p>Every Alyve order is fulfilled through our U.S. operations with an emphasis on transparency and dependable service. From sourcing to shipment, products are carefully handled and prepared under established quality practices to help maintain consistency. No unknown middlemen and no complicated fulfillment chains.</p><div class="listicle__highlight-callout"><p>Orders placed before 2PM EST ship same day. Delivered in 2–3 business days via tracked carrier.</p></div>',
			badges: ['Quality Standards', 'Supply Chain Transparency', 'Direct Fulfillment'],
		},
		{
			icon: 'lab',
			headline: 'Endotoxin Testing Standard',
			body: '<p>Placeholder copy — content coming soon.</p>',
		},
		{
			icon: 'shield',
			headline: 'Unverified purity claims can invalidate your data.',
			body: '<p>Your outcomes depend on what is actually in the vial. Without independent testing on every batch, you are trusting a label—not a lab result.</p>',
		},
		{
			icon: 'check',
			headline: 'No COA before purchase means no audit trail.',
			body: '<p>Reputable suppliers publish Certificates of Analysis tied to batch numbers before you buy.</p>',
		},
		{
			icon: 'refresh',
			headline: 'Inconsistent sourcing slows every experiment cycle.',
			body: '<p>Switching vendors mid-study introduces variables you cannot control.</p>',
		},
		{
			icon: 'award',
			headline: 'Research-use standards matter for your reputation.',
			body: '<p>Materials labeled and handled for research use reduce ambiguity for PI review and institutional policy.</p>',
		},
		{
			icon: 'clock',
			headline: 'Verified supply is faster to trust than faster to ship.',
			body: '<p>Tracked domestic shipping matters—but only after purity and documentation are settled.</p>',
		},
		{
			icon: 'lock',
			headline: 'Batch documentation you can defend in review.',
			body: '<p>Placeholder copy — content coming soon.</p>',
		},
	];

	const items = $derived.by(() => {
		const saved = (config.items ?? []).filter((it) => (it.headline ?? '').trim());
		if (!saved.length) return DEFAULT_LISTICLE_ITEMS;
		const merged: ListicleItem[] = [];
		for (let i = 0; i < DEFAULT_LISTICLE_ITEMS.length; i++) {
			const savedItem = saved[i];
			const base = DEFAULT_LISTICLE_ITEMS[i];
			if (!savedItem) {
				merged.push(base);
				continue;
			}
			merged.push({
				...base,
				...savedItem,
				icon: savedItem.icon?.trim() || base.icon,
				badges: savedItem.badges?.length ? savedItem.badges : base.badges,
			});
		}
		for (let j = DEFAULT_LISTICLE_ITEMS.length; j < saved.length; j++) {
			merged.push(saved[j]);
		}
		return merged.filter((it) => (it.headline ?? '').trim());
	});
	const showCta = $derived(Boolean(config.cta_label?.trim() && config.cta_href?.trim()));

	const introHtml = $derived(config.intro?.trim() ?? '');

	const DEFAULT_ITEMS_HEADLINE =
		'Here is why more research teams standardize on documented, batch-tested supply:';

	const itemsHeadline = $derived(
		config.items_headline?.trim() || DEFAULT_ITEMS_HEADLINE
	);

	const introBody = $derived.by(() => {
		if (!introHtml) return '';
		const stripped = introHtml.replace(
			/(<p[^>]*>)([\s\S]*?)(<\/p>)/gi,
			(match, open, content, close) => {
				const plain = content.replace(/<[^>]+>/g, '').trim();
				const explicitLead = config.items_headline?.trim();
				if (
					(explicitLead && plain === explicitLead) ||
					(!explicitLead && /^here is why more research teams standardize/i.test(plain))
				) {
					return '';
				}
				return match;
			}
		);
		const out = stripped.trim();
		return out || introHtml;
	});

	const heroHeadline = $derived(
		config.headline?.trim() || EDITORIAL_DEFAULTS.headline
	);
	const trustBrandId = $derived(
		`listicle-trust-${heroHeadline.replace(/\s+/g, '-').slice(0, 40).toLowerCase()}`
	);

	const trustBrand = $derived(
		config.trust_brand?.trim() || EDITORIAL_DEFAULTS.trust_brand
	);

	const trustItems = $derived.by(() => {
		const saved = (config.trust_items ?? []).map((item) => item.trim()).filter(Boolean);
		return saved.length ? saved : [...EDITORIAL_DEFAULTS.trust_items];
	});

	const heroCallout = $derived(
		config.hero_callout?.trim() || EDITORIAL_DEFAULTS.hero_callout
	);

	const heroCtaImage = $derived(
		config.hero_cta_image?.trim() || EDITORIAL_DEFAULTS.hero_cta_image
	);
	const heroCtaImageAlt = $derived(
		config.hero_cta_image_alt?.trim() || EDITORIAL_DEFAULTS.hero_cta_image_alt
	);
	const heroCtaHeadline = $derived(
		config.hero_cta_headline?.trim() || EDITORIAL_DEFAULTS.hero_cta_headline
	);
	const heroCtaLabel = $derived(
		config.hero_cta_label?.trim() || EDITORIAL_DEFAULTS.hero_cta_label
	);
	const heroCtaHref = $derived(
		config.hero_cta_href?.trim() || EDITORIAL_DEFAULTS.hero_cta_href
	);

	const showHeroMidCta = $derived(
		isEditorialHero &&
			Boolean(
				heroCtaImage ||
					heroCtaHeadline ||
					(heroCtaLabel && heroCtaHref)
			)
	);

	const heroPromoConfig = $derived.by((): PromoOfferModuleConfig => {
		const parts = heroCtaHeadline.split('—').map((part) => part.trim()).filter(Boolean);
		return {
			badge_text: '',
			image: heroCtaImage,
			image_alt: heroCtaImageAlt,
			ribbon_text: config.hero_cta_ribbon?.trim() || '',
			offer_primary: parts[0] || heroCtaHeadline,
			offer_secondary: parts.slice(1).join(' — ') || '',
			scarcity_text: config.hero_cta_scarcity?.trim() || '',
			cta_label: heroCtaLabel,
			cta_href: heroCtaHref,
			show_countdown: false,
		};
	});

	const showEditorialHero = $derived(
		isEditorialHero &&
			Boolean(
				heroHeadline ||
					trustBrand ||
					trustItems.length ||
					showHeroMidCta ||
					heroCallout
			)
	);

	const showSplitHero = $derived(
		!isEditorialHero &&
			Boolean(
				config.section_eyebrow?.trim() ||
					config.headline?.trim() ||
					introBody ||
					showCta ||
					config.hero_image?.trim()
			)
	);

	function reasonLabel(index: number, total: number): string {
		return `Reason ${String(index + 1).padStart(2, '0')} of ${String(total).padStart(2, '0')}`;
	}

	/** Always show three pill slots — empty text renders dot-only placeholders. */
	function badgeSlots(item: ListicleItem): string[] {
		const saved = item.badges ?? [];
		if (saved.length >= 3) return saved.slice(0, 3);
		return [...saved, ...Array(3 - saved.length).fill('')];
	}

	function coaEmbedVisible(index: number): boolean {
		return index === REASON_FOUR_INDEX;
	}

	function coaEmbedImage(): string {
		return config.coa_embed_image?.trim() || '';
	}

	function coaEmbedHref(): string {
		const raw = config.coa_embed_href?.trim();
		if (!raw) return '/coa-library';
		return raw;
	}

	const LEGACY_COA_LINK_LABELS = new Set([
		'View Sample COA →',
		'View Sample COA',
		'View sample COA →',
	]);

	function coaEmbedLinkLabel(): string {
		const raw = config.coa_embed_link_label?.trim();
		if (!raw || LEGACY_COA_LINK_LABELS.has(raw)) return 'View COA Library →';
		return raw;
	}

	function coaEmbedImageAlt(): string {
		return config.coa_embed_image_alt?.trim() || 'Sample Certificate of Analysis preview';
	}
</script>

{#if showEditorialHero || showSplitHero || items.length}
	<section
		class="listicle"
		class:has-editorial-hero={showEditorialHero}
		class:is-v-compact={spacing_v === 'compact'}
		class:is-v-spacious={spacing_v === 'spacious'}
		class:is-h-compact={spacing_h === 'compact'}
		class:is-h-spacious={spacing_h === 'spacious'}
		style={accentStyle}
	>
		<div class="listicle__inner">
			{#if showEditorialHero}
				<header class="listicle__hero listicle__hero--editorial" class:has-items-headline={Boolean(itemsHeadline && items.length)}>
					{#if config.section_eyebrow?.trim()}
						<p class="listicle__eyebrow listicle__hero-eyebrow">{config.section_eyebrow.trim()}</p>
					{/if}

					<h1 class="listicle__editorial-headline">{heroHeadline}</h1>

					{#if trustBrand || trustItems.length}
						<div class="listicle__trust-bar" aria-labelledby={trustBrand ? `${trustBrandId}-brand` : undefined}>
							{#if trustBrand}
								<span class="listicle__trust-brand" id="{trustBrandId}-brand">{trustBrand}</span>
							{/if}
							{#each trustItems as item, i}
								<span class="listicle__trust-sep" aria-hidden="true">·</span>
								<span class="listicle__trust-item">
									<svg class="listicle__trust-check" viewBox="0 0 16 16" width="14" height="14" aria-hidden="true">
										<path
											fill="currentColor"
											d="M6.5 12 2 7.5l1.4-1.4L6.5 9.2 12.6 3 14 4.4z"
										/>
									</svg>
									<span>{item}</span>
								</span>
							{/each}
						</div>
					{/if}

					{#if showHeroMidCta}
						<div class="listicle__hero-promo">
							<PromoOffer
								config={heroPromoConfig}
								{resolved}
								embedded
								spacing_v="compact"
								spacing_h="compact"
							/>
						</div>
					{/if}

					{#if heroCallout}
						<div class="listicle__hero-callout">
							<p>{heroCallout}</p>
						</div>
					{/if}
				</header>
			{:else if showSplitHero}
				<header class="listicle__hero" class:has-items-headline={Boolean(itemsHeadline && items.length)}>
					{#if config.section_eyebrow?.trim()}
						<p class="listicle__eyebrow listicle__hero-eyebrow">{config.section_eyebrow.trim()}</p>
					{/if}
					<div class="listicle__hero-grid">
						<div class="listicle__hero-media">
							{#if config.hero_image?.trim()}
								<img
									src={config.hero_image.trim()}
									alt={config.hero_image_alt?.trim() || ''}
									loading="eager"
								/>
							{:else}
								<div class="listicle__hero-placeholder" aria-hidden="true"></div>
							{/if}
						</div>
						<div class="listicle__hero-aside">
							{#if config.headline?.trim()}
								<h2 class="listicle__headline listicle__hero-headline">{config.headline.trim()}</h2>
							{/if}
							{#if introBody || showCta}
								<div class="listicle__hero-body">
									{#if introBody}
										<div class="listicle__intro listicle__prose">{@html introBody}</div>
									{/if}
									{#if showCta}
										<p class="listicle__cta-wrap">
											<a href={bridgeAwareHref(config.cta_href!.trim())} class="listicle__cta">{config.cta_label!.trim()}</a>
										</p>
									{/if}
								</div>
							{/if}
						</div>
					</div>
				</header>
			{/if}

			{#if itemsHeadline && items.length}
				<h3 class="listicle__items-headline">{itemsHeadline}</h3>
			{/if}

			{#if items.length}
				<div class="listicle__rows">
					{#each items as item, i}
						<article
							class="listicle__row"
							class:listicle__row--media-first={i % 2 === 1}
						>
							<div class="listicle__copy">
								<div class="listicle__reason-row">
									{#if item.icon && listicleIcons[item.icon]}
										<span class="listicle__icon-badge listicle__icon-badge--svg" aria-hidden="true">
											<svg
												viewBox="0 0 24 24"
												width="22"
												height="22"
												fill="none"
												stroke="currentColor"
												stroke-width="1.9"
												stroke-linecap="round"
												stroke-linejoin="round"
											>
												{@html listicleIcons[item.icon]}
											</svg>
										</span>
									{:else if item.icon_text?.trim()}
										<span class="listicle__icon-badge">{item.icon_text.trim()}</span>
									{:else}
										<span class="listicle__icon-badge listicle__icon-badge--empty" aria-hidden="true"></span>
									{/if}
									<span class="listicle__reason-label">{reasonLabel(i, items.length)}</span>
								</div>

								<h3 class="listicle__point-title">{item.headline}</h3>
								<span class="listicle__title-accent" aria-hidden="true"></span>

								{#if item.body?.trim()}
									<div class="listicle__point-body listicle__prose">{@html item.body}</div>
								{/if}

								{#if coaEmbedVisible(i)}
									<figure class="listicle__coa">
										<a
											class="listicle__coa-card"
											href={bridgeAwareHref(coaEmbedHref())}
										>
											<span class="listicle__coa-thumb">
												{#if coaEmbedImage()}
													<img src={coaEmbedImage()} alt={coaEmbedImageAlt()} loading="lazy" />
												{:else}
													<span class="listicle__coa-doc" aria-hidden="true">
														<span class="listicle__coa-doc-title">Certificate of Analysis</span>
														<span class="listicle__coa-doc-batch">Batch #AV-2026-0412</span>
														<ul class="listicle__coa-doc-lines">
															<li><span>HPLC Purity</span><strong>99.4%</strong></li>
															<li><span>Identity</span><strong>Pass</strong></li>
															<li><span>Endotoxin</span><strong>&lt;0.25 EU/mg</strong></li>
														</ul>
														<span class="listicle__coa-doc-pass">PASS</span>
													</span>
												{/if}
											</span>
											<span class="listicle__coa-copy">
												<span class="listicle__coa-kicker">Sample batch documentation</span>
												<span class="listicle__coa-link">{coaEmbedLinkLabel()}</span>
											</span>
										</a>
									</figure>
								{/if}

								<ul class="listicle__badges" aria-label="Highlights">
									{#each badgeSlots(item) as badge}
										<li class="listicle__badge" class:is-empty={!badge.trim()}>
											<span class="listicle__badge-dot" aria-hidden="true"></span>
											{#if badge.trim()}
												<span class="listicle__badge-text">{badge.trim()}</span>
											{/if}
										</li>
									{/each}
								</ul>

								{#if item.callout?.trim()}
									<aside class="listicle__callout listicle__prose">{@html item.callout}</aside>
								{/if}
							</div>
							<div class="listicle__media">
								{#if item.image?.trim()}
									<img
										src={item.image.trim()}
										alt={item.image_alt?.trim() || ''}
										loading="lazy"
									/>
								{:else}
									<div class="listicle__media-placeholder" aria-hidden="true"></div>
								{/if}
							</div>
						</article>
						{#if (midPromo || midReviews) && i === midBreakAfterIndex}
							<div class="listicle__mid-promo">
								{#if midPromo}
									<PromoOffer
										config={midPromo.config}
										resolved={midPromo.resolved}
										spacing_v={midPromo.spacing_v ?? 'normal'}
										spacing_h={midPromo.spacing_h ?? 'normal'}
									/>
								{/if}
								{#if midReviews}
									<div class="listicle__mid-promo-reviews">
										<ReviewsListicle
											config={midReviews.config}
											resolved={midReviews.resolved}
											spacing_v={midReviews.spacing_v ?? 'compact'}
											spacing_h={midReviews.spacing_h ?? 'normal'}
											variant="product"
											embedded
											visibleSlides={3}
											showHeadline={true}
										/>
									</div>
								{/if}
							</div>
						{/if}
					{/each}
				</div>
			{/if}

			{#if tailProcess}
				<div class="listicle__process">
					<OrderHandling
						config={tailProcess.config}
						resolved={tailProcess.resolved}
						spacing_v={tailProcess.spacing_v ?? 'normal'}
						spacing_h={tailProcess.spacing_h ?? 'normal'}
						center_header={tailProcess.center_header ?? true}
						embedded
					/>
				</div>
			{/if}

			{#if config.closing?.trim()}
				<div class="listicle__closing listicle__prose">{@html config.closing}</div>
			{/if}
		</div>
	</section>
{/if}

<style>
	.listicle {
		--mod-pt: var(--wchs-spacing-v-normal, 56px);
		--mod-pb: var(--wchs-spacing-v-normal, 64px);
		--mod-px: 28px;
		--listicle-max: min(1120px, 100%);
		--listicle-hero-max: min(1280px, 100%);
		--listicle-teal: var(--accent, #0d9488);
		--listicle-teal-soft: color-mix(in srgb, var(--listicle-teal) 12%, var(--bg) 88%);
		--listicle-teal-border: color-mix(in srgb, var(--listicle-teal) 28%, var(--border) 72%);
		background: var(--bg);
		color: var(--fg);
		padding: var(--mod-pt) var(--mod-px) var(--mod-pb);
	}
	.listicle.is-v-compact {
		--mod-pt: var(--wchs-spacing-v-compact, 28px);
		--mod-pb: var(--wchs-spacing-v-compact, 32px);
	}
	.listicle.is-v-spacious {
		--mod-pt: var(--wchs-spacing-v-spacious, 80px);
		--mod-pb: var(--wchs-spacing-v-spacious, 88px);
	}
	.listicle.is-h-compact {
		--mod-px: 16px;
	}
	.listicle.is-h-spacious {
		--mod-px: 40px;
	}

	.listicle.has-editorial-hero {
		--mod-px: clamp(16px, 3vw, 28px);
		--listicle-max: min(960px, 100%);
	}

	.listicle__inner {
		max-width: var(--listicle-max);
		margin: 0 auto;
	}

	.listicle__hero {
		display: flex;
		flex-direction: column;
		gap: 16px;
		margin: 0 auto 56px;
		max-width: var(--listicle-hero-max);
		width: 100%;
	}
	.listicle__hero.has-items-headline {
		margin-bottom: 40px;
	}

	.listicle__hero--editorial {
		gap: 20px;
		max-width: 100%;
		width: 100%;
		margin-left: 0;
		margin-right: 0;
		align-items: center;
		text-align: center;
	}

	.listicle__hero--editorial .listicle__hero-eyebrow {
		width: 100%;
	}

	.listicle__hero--editorial .listicle__editorial-headline {
		width: 100%;
	}

	.listicle__hero--editorial .listicle__trust-bar {
		width: 100%;
		justify-content: center;
	}

	.listicle__hero--editorial .listicle__hero-callout {
		width: 100%;
		margin-top: 14px;
		padding: 18px 22px 18px 24px;
	}

	.listicle__editorial-headline {
		margin: 0;
		font-family: var(--font-heading, var(--font-sans));
		font-size: clamp(28px, 7.2vw, 42px);
		font-weight: 800;
		line-height: 1.12;
		letter-spacing: -0.03em;
		color: var(--fg);
		text-wrap: balance;
	}

	.listicle__trust-bar {
		display: flex;
		flex-wrap: wrap;
		align-items: center;
		gap: 8px 10px;
		margin-top: 4px;
		padding: 12px 16px;
		border: 1px solid var(--listicle-teal-border);
		border-radius: 10px;
		background: var(--listicle-teal-soft);
	}

	.listicle__trust-brand {
		font-size: 14px;
		font-weight: 800;
		line-height: 1.3;
		color: var(--fg);
		white-space: nowrap;
	}

	.listicle__trust-sep {
		font-size: 16px;
		font-weight: 700;
		line-height: 1;
		color: color-mix(in srgb, var(--fg) 36%, transparent);
		user-select: none;
	}

	.listicle__trust-item {
		display: inline-flex;
		align-items: center;
		gap: 6px;
		font-size: 13px;
		font-weight: 600;
		line-height: 1.35;
		color: color-mix(in srgb, var(--fg) 78%, transparent);
	}

	.listicle__trust-check {
		flex-shrink: 0;
		color: var(--listicle-teal);
	}

	.listicle__hero-promo {
		margin: 16px auto 18px;
		width: 100%;
		max-width: min(700px, 100%);
		--po-hero-panel: color-mix(in srgb, var(--listicle-teal) 9%, var(--bg) 91%);
	}

	.listicle__hero-promo :global(.promo-offer__wrap) {
		max-width: 100%;
	}

	.listicle__hero-promo :global(.promo-offer__split) {
		min-height: 280px;
		gap: 0;
		grid-template-columns: 1.08fr 0.92fr;
		background: var(--po-hero-panel);
	}

	.listicle__hero-promo :global(.promo-offer__media) {
		padding: 14px 0 14px 12px;
		background: transparent;
	}

	.listicle__hero-promo :global(.promo-offer__media img) {
		max-height: 240px;
		width: auto;
		max-width: 100%;
	}

	.listicle__hero-promo :global(.promo-offer__copy) {
		padding: 20px 14px 28px 0;
		gap: 14px;
		background: transparent;
	}

	.listicle__hero-promo :global(.promo-offer__headline) {
		font-size: clamp(18px, 2.6vw, 24px);
	}

	.listicle__hero-promo :global(.promo-offer__cta) {
		padding: 12px 18px;
	}

	.listicle__mid-promo {
		margin: clamp(36px, 6vw, 64px) calc(-1 * var(--mod-px, 28px));
		width: calc(100% + 2 * var(--mod-px, 28px));
		padding: clamp(28px, 5vw, 48px) var(--mod-px, 28px);
		background: color-mix(in srgb, var(--listicle-teal) 6%, var(--bg) 94%);
		box-sizing: border-box;
	}

	.listicle__mid-promo :global(.promo-offer) {
		border-radius: 0;
		padding: 0;
		background: transparent;
	}

	.listicle__mid-promo-reviews {
		margin-top: clamp(24px, 4vw, 36px);
		padding-top: clamp(20px, 3.5vw, 28px);
		border-top: 1px dashed color-mix(in srgb, var(--fg) 22%, transparent);
	}

	.listicle__mid-promo-reviews :global(.reviews-listicle.is-proof),
	.listicle__mid-promo-reviews :global(.reviews-listicle.is-product) {
		--rl-max: min(920px, 100%);
	}

	.listicle__process {
		margin-top: clamp(36px, 6vw, 64px);
		width: 100%;
	}

	.listicle__hero-callout {
		--callout-accent: #9b1c2e;
		--callout-bg: #fde8e8;
		margin-top: 4px;
		padding: 16px 18px 16px 20px;
		border-left: 6px solid var(--callout-accent);
		border-radius: 0 10px 10px 0;
		background: var(--callout-bg);
		box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--callout-accent) 8%, transparent);
	}

	:global([data-theme='dark']) .listicle__hero-callout {
		--callout-accent: #f87171;
		--callout-bg: color-mix(in srgb, #ef4444 14%, var(--bg) 86%);
	}

	.listicle__hero-callout p {
		margin: 0;
		font-size: clamp(14px, 3.8vw, 16px);
		font-weight: 800;
		line-height: 1.45;
		letter-spacing: 0.01em;
		color: var(--fg);
		text-wrap: pretty;
	}

	.listicle__hero-eyebrow {
		margin: 0;
	}

	.listicle__hero-grid {
		display: grid;
		grid-template-columns: minmax(0, 1.2fr) minmax(0, 0.8fr);
		grid-template-areas: 'media aside';
		gap: clamp(28px, 4vw, 56px);
		align-items: center;
	}

	.listicle__hero-aside {
		grid-area: aside;
		display: flex;
		flex-direction: column;
		justify-content: center;
		align-items: flex-start;
		gap: 16px;
		min-width: 0;
	}

	.listicle__hero-headline {
		margin: 0;
	}

	.listicle__hero-body {
		display: flex;
		flex-direction: column;
		align-items: flex-start;
		gap: 16px;
		min-width: 0;
		width: 100%;
	}

	.listicle__hero-media {
		grid-area: media;
		min-width: 0;
		border-radius: 14px;
		overflow: hidden;
		background: var(--bg-muted);
		min-height: clamp(320px, 44vw, 480px);
	}
	.listicle__hero-media img {
		display: block;
		width: 100%;
		height: 100%;
		min-height: clamp(320px, 44vw, 480px);
		object-fit: cover;
	}
	.listicle__hero-placeholder {
		width: 100%;
		min-height: clamp(320px, 44vw, 480px);
		aspect-ratio: 5 / 4;
		background: color-mix(in srgb, var(--accent) 8%, var(--bg-muted) 92%);
	}

	.listicle__items-headline {
		margin: 0 auto clamp(40px, 6vw, 56px);
		max-width: min(42rem, 100%);
		padding: 0 4px;
		text-align: center;
		font-family: var(--font-heading, var(--font-sans));
		font-size: clamp(20px, 2.6vw, 26px);
		font-weight: var(--heading-weight, 700);
		line-height: 1.3;
		letter-spacing: -0.02em;
		color: var(--fg);
	}

	.listicle.has-editorial-hero .listicle__items-headline {
		width: 100%;
		padding: 0;
		text-align: center;
		font-size: clamp(18px, 4.6vw, 22px);
		font-weight: 600;
		line-height: 1.4;
	}

	.listicle__eyebrow {
		margin: 0 0 12px;
		font-size: 11px;
		font-weight: 700;
		letter-spacing: 0.14em;
		text-transform: uppercase;
		color: var(--accent);
	}

	.listicle__headline {
		margin: 0;
		max-width: 22ch;
		font-family: var(--font-heading, var(--font-sans));
		font-size: clamp(24px, 3.5vw, 34px);
		font-weight: var(--heading-weight, 700);
		line-height: 1.15;
		letter-spacing: -0.02em;
		color: var(--fg);
	}

	.listicle__intro {
		margin: 0;
		max-width: 40ch;
		width: 100%;
	}
	.listicle__intro :global(p) {
		font-family: var(--font-sans);
		font-size: 16px;
		font-weight: 400;
		line-height: 1.75;
		color: var(--fg);
	}

	.listicle__rows {
		display: flex;
		flex-direction: column;
		gap: clamp(56px, 9vw, 96px);
	}

	.listicle__row {
		display: grid;
		grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
		gap: clamp(32px, 5vw, 64px);
		align-items: center;
	}

	.listicle__row--media-first .listicle__copy {
		order: 2;
	}
	.listicle__row--media-first .listicle__media {
		order: 1;
	}

	.listicle__copy {
		display: flex;
		flex-direction: column;
		align-items: flex-start;
		text-align: left;
		gap: 0;
		min-width: 0;
		padding-top: 8px;
	}

	.listicle__reason-row {
		display: flex;
		align-items: center;
		gap: 12px;
		margin: 0 0 20px;
		flex-wrap: wrap;
	}

	.listicle__icon-badge {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		min-width: 44px;
		height: 44px;
		padding: 0 10px;
		border-radius: 10px;
		border: 1px solid var(--listicle-teal-border);
		background: var(--listicle-teal-soft);
		font-size: 13px;
		font-weight: 800;
		letter-spacing: 0.04em;
		color: var(--fg);
		flex-shrink: 0;
	}
	.listicle__icon-badge--empty {
		min-width: 44px;
		width: 44px;
		padding: 0;
	}
	.listicle__icon-badge--svg {
		color: var(--listicle-teal);
	}
	.listicle__icon-badge--svg :global(svg) {
		display: block;
	}

	.listicle__reason-label {
		font-size: 11px;
		font-weight: 700;
		letter-spacing: 0.14em;
		text-transform: uppercase;
		color: color-mix(in srgb, var(--fg) 42%, transparent);
	}

	.listicle__point-title {
		margin: 0;
		max-width: 16ch;
		font-family: var(--font-heading, var(--font-sans));
		font-size: clamp(26px, 3.2vw, 36px);
		font-weight: 800;
		line-height: 1.15;
		letter-spacing: -0.03em;
		color: var(--fg);
	}

	.listicle__title-accent {
		display: block;
		width: 52px;
		height: 4px;
		margin: 14px 0 20px;
		border-radius: 2px;
		background: var(--listicle-teal);
		flex-shrink: 0;
	}

	.listicle__point-body {
		max-width: 38rem;
		margin: 0 0 24px;
	}
	.listicle__point-body :global(p) {
		font-size: clamp(15px, 1.6vw, 17px);
		line-height: 1.72;
		color: color-mix(in srgb, var(--fg) 62%, transparent);
		margin: 0 0 14px;
	}
	.listicle__point-body :global(p:last-child) {
		margin-bottom: 0;
	}

	.listicle__point-body :global(.listicle__highlight-callout) {
		margin: 18px 0 0;
		padding: 16px 18px 16px 20px;
		border-left: 6px solid var(--listicle-teal);
		border-radius: 0 10px 10px 0;
		background: var(--listicle-teal-soft);
		box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--listicle-teal) 12%, transparent);
	}

	.listicle__point-body :global(.listicle__highlight-callout p) {
		margin: 0;
		font-size: clamp(14px, 3.8vw, 16px);
		font-weight: 800;
		line-height: 1.45;
		letter-spacing: 0.01em;
		color: var(--fg);
		text-wrap: pretty;
	}

	.listicle__coa {
		margin: 0 0 24px;
		max-width: min(100%, 34rem);
	}

	.listicle__coa-card {
		display: flex;
		align-items: center;
		gap: 16px;
		padding: 12px 14px 12px 12px;
		border-radius: 12px;
		border: 1px solid color-mix(in srgb, var(--listicle-teal) 22%, var(--border) 78%);
		background: color-mix(in srgb, var(--listicle-teal-soft) 65%, var(--bg) 35%);
		box-shadow: 0 6px 20px color-mix(in srgb, black 5%, transparent);
		text-decoration: none;
		transition:
			border-color 0.15s ease,
			box-shadow 0.15s ease,
			transform 0.15s ease;
	}

	.listicle__coa-card:hover {
		border-color: color-mix(in srgb, var(--listicle-teal) 42%, var(--border) 58%);
		box-shadow: 0 10px 28px color-mix(in srgb, var(--listicle-teal) 10%, transparent);
		transform: translateY(-1px);
	}

	.listicle__coa-thumb {
		flex: 0 0 120px;
		width: 120px;
		border-radius: 8px;
		overflow: hidden;
		border: 1px solid var(--listicle-teal-border);
		background: var(--bg);
		box-shadow: 0 4px 14px color-mix(in srgb, black 6%, transparent);
	}

	.listicle__coa-thumb img {
		display: block;
		width: 100%;
		height: auto;
	}

	.listicle__coa-doc {
		position: relative;
		display: block;
		padding: 11px 11px 13px;
		min-height: 118px;
		background: linear-gradient(
			165deg,
			color-mix(in srgb, var(--bg) 92%, white 8%) 0%,
			color-mix(in srgb, var(--bg-muted) 70%, var(--bg) 30%) 100%
		);
	}

	.listicle__coa-doc-title {
		display: block;
		margin: 0 0 4px;
		padding-right: 2.75rem;
		font-size: 9px;
		font-weight: 800;
		line-height: 1.35;
		letter-spacing: 0.06em;
		text-transform: uppercase;
		color: color-mix(in srgb, var(--fg) 72%, transparent);
	}

	.listicle__coa-doc-batch {
		display: block;
		margin: 0 0 9px;
		font-size: 10px;
		font-weight: 600;
		color: var(--fg-muted);
		font-variant-numeric: tabular-nums;
	}

	.listicle__coa-doc-lines {
		list-style: none;
		margin: 0;
		padding: 0;
		display: grid;
		gap: 5px;
	}

	.listicle__coa-doc-lines li {
		display: flex;
		justify-content: space-between;
		gap: 6px;
		font-size: 9px;
		line-height: 1.35;
		color: var(--fg-muted);
		border-bottom: 1px dashed color-mix(in srgb, var(--border) 80%, transparent);
		padding-bottom: 4px;
	}

	.listicle__coa-doc-lines strong {
		font-weight: 700;
		color: var(--fg);
	}

	.listicle__coa-doc-pass {
		position: absolute;
		top: 9px;
		right: 9px;
		padding: 2px 7px;
		border-radius: 999px;
		font-size: 8px;
		font-weight: 800;
		letter-spacing: 0.06em;
		color: hsl(152 55% 28%);
		background: hsl(152 48% 92%);
	}

	.listicle__coa-copy {
		display: flex;
		flex-direction: column;
		gap: 5px;
		min-width: 0;
		flex: 1;
	}

	.listicle__coa-kicker {
		font-size: 11px;
		font-weight: 600;
		letter-spacing: 0.04em;
		text-transform: uppercase;
		color: color-mix(in srgb, var(--fg) 48%, transparent);
	}

	.listicle__coa-link {
		font-size: 14px;
		font-weight: 700;
		line-height: 1.35;
		color: var(--listicle-teal);
	}

	@media (max-width: 520px) {
		.listicle__coa-card {
			align-items: flex-start;
			padding: 12px;
		}

		.listicle__coa-thumb {
			flex: 0 0 112px;
			width: 112px;
		}
	}

	@media (max-width: 380px) {
		.listicle__coa-card {
			flex-direction: column;
			align-items: stretch;
			gap: 12px;
		}

		.listicle__coa-thumb {
			flex: none;
			width: min(100%, 200px);
			align-self: center;
		}

		.listicle__coa-copy {
			text-align: center;
			align-items: center;
		}
	}

	.listicle__badges {
		display: flex;
		flex-wrap: wrap;
		gap: 10px;
		list-style: none;
		margin: 0;
		padding: 0;
	}

	.listicle__badge {
		display: inline-flex;
		align-items: center;
		gap: 8px;
		min-height: 36px;
		padding: 8px 16px 8px 12px;
		border-radius: 999px;
		border: 1px solid var(--listicle-teal-border);
		background: var(--listicle-teal-soft);
	}
	.listicle__badge.is-empty {
		min-width: 52px;
		padding-right: 16px;
	}

	.listicle__badge-dot {
		width: 8px;
		height: 8px;
		border-radius: 50%;
		background: var(--listicle-teal);
		flex-shrink: 0;
	}

	.listicle__badge-text {
		font-size: 13px;
		font-weight: 600;
		line-height: 1.2;
		color: color-mix(in srgb, var(--listicle-teal) 78%, var(--fg) 22%);
		white-space: nowrap;
	}

	.listicle__callout {
		width: 100%;
		max-width: 36rem;
		margin: 20px 0 0;
		padding: 14px 16px 14px 18px;
		border-left: 3px solid var(--accent);
		background: color-mix(in srgb, var(--fg) 4%, var(--bg-muted) 96%);
		border-radius: 0 8px 8px 0;
	}

	.listicle__media {
		min-width: 0;
		border-radius: 12px;
		overflow: hidden;
		background: var(--bg-muted);
	}
	.listicle__media img {
		display: block;
		width: 100%;
		height: auto;
		object-fit: cover;
	}
	.listicle__media-placeholder {
		width: 100%;
		aspect-ratio: 4 / 3;
		background: color-mix(in srgb, var(--accent) 8%, var(--bg-muted) 92%);
	}

	.listicle__cta-wrap {
		margin: 8px 0 0;
		text-align: left;
		width: 100%;
	}

	.listicle__cta {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		padding: 14px 28px;
		background: var(--accent);
		color: var(--accent-fg);
		border: 1px solid var(--accent);
		border-radius: 14px;
		text-decoration: none;
		font-size: 12px;
		font-weight: 600;
		text-transform: uppercase;
		letter-spacing: 0.1em;
		transition: opacity var(--dur-fast) var(--ease);
	}
	.listicle__cta:hover {
		opacity: 0.88;
	}

	.listicle__prose :global(p) {
		font-size: 16px;
		line-height: 1.75;
		color: var(--fg);
		margin: 0 0 14px;
	}
	.listicle__prose :global(p:last-child) {
		margin-bottom: 0;
	}
	.listicle__closing {
		margin-top: 48px;
		text-align: center;
		max-width: 52ch;
		margin-left: auto;
		margin-right: auto;
	}

	@media (max-width: 640px) {
		.listicle.has-editorial-hero {
			--mod-px: 14px;
		}

		.listicle__hero--editorial {
			gap: 10px;
		}

		.listicle__editorial-headline {
			font-size: clamp(24px, 6.4vw, 34px);
		}

		.listicle__hero-promo {
			margin: 8px auto 14px;
		}

		.listicle__hero-promo :global(.promo-offer__split) {
			min-height: 0;
			grid-template-columns: 1fr;
			background: var(--po-hero-panel);
		}

		.listicle__hero-promo :global(.promo-offer__media) {
			min-height: 0;
			max-height: 200px;
			padding: 14px 14px 10px;
			background: transparent;
		}

		.listicle__hero-promo :global(.promo-offer__media img) {
			max-height: 168px;
		}

		.listicle__hero-promo :global(.promo-offer__copy) {
			padding: 10px 16px 22px;
			gap: 12px;
			background: transparent;
		}

		.listicle__hero--editorial .listicle__hero-callout {
			margin-top: 18px;
			padding: 16px 18px 18px 20px;
		}

		.listicle__hero-promo :global(.promo-offer__headline) {
			font-size: clamp(17px, 4.8vw, 21px);
		}

		.listicle__hero-promo :global(.promo-offer__scarcity) {
			font-size: 12px;
			line-height: 1.45;
		}

		.listicle__trust-bar {
			flex-direction: row;
			flex-wrap: wrap;
			align-items: center;
			gap: 4px 6px;
			margin-top: 0;
			padding: 10px 12px;
		}

		.listicle__trust-brand {
			flex: 1 1 100%;
		}

		.listicle__trust-sep {
			display: inline;
			font-size: 13px;
		}

		.listicle__trust-item {
			font-size: 11px;
			gap: 4px;
		}

		.listicle__trust-check {
			width: 12px;
			height: 12px;
		}
	}

	@media (max-width: 800px) {
		.listicle__hero-eyebrow {
			text-align: center;
		}

		.listicle__hero-grid {
			display: flex;
			flex-direction: column;
			gap: 20px;
			align-items: center;
			text-align: center;
		}

		.listicle__hero-aside {
			display: contents;
		}

		.listicle__hero-headline {
			order: 1;
			max-width: none;
			width: 100%;
		}

		.listicle__hero-media {
			order: 2;
			width: 100%;
			min-height: clamp(280px, 68vw, 420px);
		}
		.listicle__hero-media img,
		.listicle__hero-placeholder {
			min-height: clamp(280px, 68vw, 420px);
		}

		.listicle__hero-body {
			order: 3;
			display: flex;
			flex-direction: column;
			align-items: center;
			gap: 20px;
			width: 100%;
			text-align: center;
		}

		.listicle__hero-body .listicle__cta-wrap {
			order: 1;
			margin: 0;
		}

		.listicle__hero-body .listicle__intro {
			order: 2;
		}

		.listicle__headline {
			max-width: none;
			width: 100%;
		}

		.listicle__intro {
			max-width: 36rem;
			margin-left: auto;
			margin-right: auto;
		}

		.listicle__cta-wrap {
			text-align: center;
			width: 100%;
		}

		.listicle__row,
		.listicle__row--media-first {
			display: flex;
			flex-direction: column;
			gap: 24px;
		}

		.listicle__row .listicle__media,
		.listicle__row--media-first .listicle__media {
			order: 0;
			width: 100%;
		}

		.listicle__row .listicle__copy,
		.listicle__row--media-first .listicle__copy {
			order: 1;
			width: 100%;
		}

		.listicle__point-title {
			max-width: none;
		}
	}
</style>
