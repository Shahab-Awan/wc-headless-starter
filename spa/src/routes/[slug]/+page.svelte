<script lang="ts">
	import { page } from '$app/state';
	import { config, isModuleVisibleNow, type HomepageModule } from '$lib/config.svelte';
	import { auth } from '$lib/wc/auth.svelte';
	import AccessGate from '$lib/components/AccessGate.svelte';
	import Hero from '$lib/components/Hero.svelte';
	import HomepageProductSlider from '$lib/components/HomepageProductSlider.svelte';
	import ReviewSlider from '$lib/components/ReviewSlider.svelte';
	import Accordion from '$lib/components/Accordion.svelte';
	import TrustBar from '$lib/components/TrustBar.svelte';
	import TextBlock from '$lib/components/TextBlock.svelte';
	import Gallery from '$lib/components/Gallery.svelte';
	import CategoryGrid from '$lib/components/CategoryGrid.svelte';
	import SplitFeatures from '$lib/components/SplitFeatures.svelte';
	import SplitValue from '$lib/components/SplitValue.svelte';
	import FeatureHighlights from '$lib/components/FeatureHighlights.svelte';
	import OrderHandling from '$lib/components/OrderHandling.svelte';
	import ShopGrid from '$lib/components/ShopGrid.svelte';
	import ContactForm from '$lib/components/ContactForm.svelte';
	import CTA from '$lib/components/CTA.svelte';
	import Spacer from '$lib/components/Spacer.svelte';
	import LogoStrip from '$lib/components/LogoStrip.svelte';
	import Video from '$lib/components/Video.svelte';
	import Listicle from '$lib/components/Listicle.svelte';
	import PromoOffer from '$lib/components/PromoOffer.svelte';
	import ReviewsListicle from '$lib/components/ReviewsListicle.svelte';
	import ListicleFaqs from '$lib/components/ListicleFaqs.svelte';
	import VaultHero from '$lib/components/VaultHero.svelte';
	import VaultQualityTabs from '$lib/components/VaultQualityTabs.svelte';
	import VaultQualityVerify from '$lib/components/VaultQualityVerify.svelte';
	import VaultWhyChoose from '$lib/components/VaultWhyChoose.svelte';
	import VaultCta from '$lib/components/VaultCta.svelte';
	import WhyAlyveStickyCta from '$lib/components/WhyAlyveStickyCta.svelte';
	import SEO from '$lib/components/SEO.svelte';
	import { getWhyAlyveCta } from '$lib/why-alyve-cta';
	import { mergeWhyAlyveReviewsConfig } from '$lib/why-alyve-reviews';
	import { mergeWhyAlyveProcessConfig } from '$lib/why-alyve-process';
	import { resolveVaultTestimonialsModule } from '$lib/vault-page';

	const pageData = $derived(
		config.data.pages?.find(p => p.slug === (page.params.slug ?? '')) ?? null
	);

	const hidePageTitle = $derived.by(() => {
		const first = (pageData?.modules?.filter(isModuleVisibleNow) ?? [])[0]?.type;
		return first === 'listicle' || first === 'vault_hero';
	});

	const isVaultPage = $derived((page.params.slug ?? '').replace(/\/$/, '') === 'vault');

	const vaultTestimonialsModule = $derived.by(() => {
		if (!isVaultPage) return null;
		return resolveVaultTestimonialsModule(pageData?.modules, config.data.homepage.modules);
	});

	const isWhyAlyvePage = $derived((page.params.slug ?? '').replace(/\/$/, '') === 'why-alyve');

	const whyAlyveStickyCta = $derived(isWhyAlyvePage ? getWhyAlyveCta(config.data.pages) : null);

	const whyAlyveMidPromoModule = $derived.by((): (HomepageModule & { type: 'promo_offer' }) | null => {
		if (!isWhyAlyvePage || !pageData?.modules) return null;
		const mod = pageData.modules.find((m) => m.type === 'promo_offer' && isModuleVisibleNow(m));
		return mod?.type === 'promo_offer' ? mod : null;
	});

	const whyAlyveReviewsModule = $derived.by((): (HomepageModule & { type: 'reviews_listicle' }) | null => {
		if (!isWhyAlyvePage || !pageData?.modules) return null;
		const mod = pageData.modules.find((m) => m.type === 'reviews_listicle' && isModuleVisibleNow(m));
		const hiddenReviews = pageData.modules.some(
			(m) => m.type === 'reviews_listicle' && !isModuleVisibleNow(m),
		);
		if (hiddenReviews) return null;
		const mergedConfig = mergeWhyAlyveReviewsConfig(
			mod?.type === 'reviews_listicle' ? mod.config : null,
		);
		if (mod?.type === 'reviews_listicle') {
			return { ...mod, config: mergedConfig };
		}
		const hasListicle = pageData.modules.some(
			(m) => m.type === 'listicle' && isModuleVisibleNow(m),
		);
		if (!hasListicle) return null;
		return {
			type: 'reviews_listicle',
			visibility: 'all',
			spacing_v: 'normal',
			spacing_h: 'normal',
			config: mergedConfig,
		};
	});

	const whyAlyveProcessModule = $derived.by((): (HomepageModule & { type: 'order_handling' }) | null => {
		if (!isWhyAlyvePage || !pageData?.modules) return null;
		const mod = pageData.modules.find((m) => m.type === 'order_handling' && isModuleVisibleNow(m));
		const hiddenProcess = pageData.modules.some(
			(m) => m.type === 'order_handling' && !isModuleVisibleNow(m),
		);
		if (hiddenProcess) return null;
		const mergedConfig = mergeWhyAlyveProcessConfig(
			mod?.type === 'order_handling' ? mod.config : null,
		);
		if (mod?.type === 'order_handling') {
			return { ...mod, config: mergedConfig };
		}
		const hasListicle = pageData.modules.some(
			(m) => m.type === 'listicle' && isModuleVisibleNow(m),
		);
		if (!hasListicle) return null;
		return {
			type: 'order_handling',
			visibility: 'all',
			spacing_v: 'normal',
			spacing_h: 'normal',
			center_header: true,
			config: mergedConfig,
		};
	});

	const visiblePageModules = $derived(
		(pageData?.modules ?? []).filter(
			(m) =>
				isModuleVisibleNow(m) &&
				!(isVaultPage && m.type === 'reviews_listicle') &&
				!(
					isWhyAlyvePage &&
					(m.type === 'promo_offer' || m.type === 'reviews_listicle' || m.type === 'order_handling')
				)
		)
	);

	// Derive a description from the first text/gallery/trust module if
	// available; fall back to generic.
	const description = $derived.by(() => {
		if (!pageData) return '';
		for (const mod of pageData.modules ?? []) {
			if (mod.type === 'text_block' && (mod.config as any).content) {
				return String((mod.config as any).content)
					.replace(/<[^>]+>/g, ' ')
					.replace(/\s+/g, ' ')
					.trim()
					.substring(0, 160);
			}
		}
		return `${pageData.title} — ${config.data.brand_name}`;
	});

	// Derive a hero image from the first module that has one.
	const image = $derived.by(() => {
		if (!pageData) return '';
		for (const mod of pageData.modules ?? []) {
			const c = mod.config as any;
			if (c?.image_desktop) return c.image_desktop as string;
			if (c?.items?.[0]?.src) return c.items[0].src as string;
			if (c?.items?.[0]?.image) return c.items[0].image as string;
		}
		return '';
	});

	// JSON-LD schemas: always emit a BreadcrumbList (Home → this page), and
	// emit a FAQPage if the page has one or more accordion modules. These
	// light up Google's rich results (expandable FAQs + breadcrumb trail in
	// search listings) with zero admin effort — the schema just reflects
	// whatever's already in wchs_pages_config.
	const origin = $derived.by(() => {
		if (typeof window !== 'undefined') return window.location.origin;
		return (config.data as any).spa_origin || '';
	});
	const pageSchemas = $derived.by(() => {
		if (!pageData) return null;
		const out: unknown[] = [];
		// BreadcrumbList — always
		out.push({
			'@context': 'https://schema.org',
			'@type': 'BreadcrumbList',
			itemListElement: [
				{ '@type': 'ListItem', position: 1, name: config.data.brand_name, item: `${origin}/` },
				{ '@type': 'ListItem', position: 2, name: pageData.title, item: `${origin}/${pageData.slug}` },
			],
		});
		// FAQPage — only if the page has accordion modules
		const faqItems = (pageData.modules ?? [])
			.filter((m) => m.type === 'accordion' || m.type === 'listicle_faqs')
			.flatMap((m) => ((m.config as any).items ?? []));
		if (faqItems.length > 0) {
			out.push({
				'@context': 'https://schema.org',
				'@type': 'FAQPage',
				mainEntity: faqItems.map((it: { q: string; a: string }) => ({
					'@type': 'Question',
					name: it.q,
					acceptedAnswer: {
						'@type': 'Answer',
						text: String(it.a || '').replace(/<[^>]+>/g, '').trim(),
					},
				})),
			});
		}
		return out;
	});
</script>

{#if pageData}
	<SEO title={pageData.title} description={description} image={image} type="website" schema={pageSchemas} />
{:else}
	<SEO title="Not Found" description="Page not found" type="website" noindex={true} />
{/if}

<AccessGate requires="products">
{#if pageData}
	<article class="content-page" class:content-page--listicle={hidePageTitle} class:content-page--why-alyve={isWhyAlyvePage} class:content-page--vault={isVaultPage}>
		{#if !hidePageTitle}
			<h1 class="content-page__title">{pageData.title}</h1>
		{/if}

		{#each visiblePageModules as mod}
			<div class="wchs-mod-wrap" data-module-type={mod.type} data-module-id={mod.id ?? ''} style="display: contents">
				{#if mod.type === 'product_slider'}
					<HomepageProductSlider config={mod.config} spacing_v={mod.spacing_v || 'normal'} spacing_h={mod.spacing_h || 'normal'} center_header={mod.center_header || false} />
				{:else if mod.type === 'review_slider'}
					<ReviewSlider title={mod.config.title || 'What customers say'} photos_only={mod.config.photos_only || false} product_ids={mod.config.product_ids || []} spacing_v={mod.spacing_v || 'normal'} spacing_h={mod.spacing_h || 'normal'} center_header={mod.center_header || false} />
				{:else if mod.type === 'order_handling'}
					<OrderHandling config={mod.config} resolved={mod.resolved} spacing_v={mod.spacing_v || 'normal'} spacing_h={mod.spacing_h || 'normal'} center_header={mod.center_header ?? true} />
				{:else if mod.type === 'accordion'}
					<Accordion config={mod.config} spacing_v={mod.spacing_v || 'normal'} spacing_h={mod.spacing_h || 'normal'} center_header={mod.center_header || false} />
				{:else if mod.type === 'trust_bar'}
					<TrustBar config={mod.config} spacing_v={mod.spacing_v || 'normal'} spacing_h={mod.spacing_h || 'normal'} resolved={mod.resolved} />
				{:else if mod.type === 'listicle'}
					<Listicle
						config={mod.config}
						resolved={mod.resolved}
						spacing_v={mod.spacing_v || 'normal'}
						spacing_h={mod.spacing_h || 'normal'}
						midPromo={whyAlyveMidPromoModule
							? {
									config: whyAlyveMidPromoModule.config,
									resolved: whyAlyveMidPromoModule.resolved,
									spacing_v: whyAlyveMidPromoModule.spacing_v || 'normal',
									spacing_h: whyAlyveMidPromoModule.spacing_h || 'normal',
									afterIndex: 3,
								}
							: undefined}
						midReviews={whyAlyveReviewsModule
							? {
									config: whyAlyveReviewsModule.config,
									resolved: whyAlyveReviewsModule.resolved,
									spacing_v: whyAlyveReviewsModule.spacing_v || 'normal',
									spacing_h: whyAlyveReviewsModule.spacing_h || 'normal',
									afterIndex: 3,
								}
							: undefined}
						tailProcess={whyAlyveProcessModule
							? {
									config: whyAlyveProcessModule.config,
									resolved: whyAlyveProcessModule.resolved,
									spacing_v: whyAlyveProcessModule.spacing_v || 'normal',
									spacing_h: whyAlyveProcessModule.spacing_h || 'normal',
									center_header: whyAlyveProcessModule.center_header ?? true,
								}
							: undefined}
					/>
				{:else if mod.type === 'promo_offer'}
					<PromoOffer config={mod.config} resolved={mod.resolved} spacing_v={mod.spacing_v || 'normal'} spacing_h={mod.spacing_h || 'normal'} />
				{:else if mod.type === 'reviews_listicle'}
					<ReviewsListicle config={mod.config} resolved={mod.resolved} spacing_v={mod.spacing_v || 'normal'} spacing_h={mod.spacing_h || 'normal'} />
				{:else if mod.type === 'listicle_faqs'}
					{#if isWhyAlyvePage && whyAlyveReviewsModule}
						<ReviewsListicle
							config={whyAlyveReviewsModule.config}
							resolved={whyAlyveReviewsModule.resolved}
							spacing_v={whyAlyveReviewsModule.spacing_v || 'normal'}
							spacing_h={whyAlyveReviewsModule.spacing_h || 'normal'}
							variant="proof"
							visibleSlides={3}
							showHeadline={true}
						/>
					{/if}
					<ListicleFaqs config={mod.config} resolved={mod.resolved} spacing_v={mod.spacing_v || 'normal'} spacing_h={mod.spacing_h || 'normal'} />
				{:else if mod.type === 'vault_hero'}
					<VaultHero
						config={mod.config}
						resolved={mod.resolved}
						spacing_v={mod.spacing_v || 'normal'}
						spacing_h={mod.spacing_h || 'normal'}
					/>
				{:else if mod.type === 'vault_quality_tabs'}
					<VaultQualityTabs
						config={mod.config}
						resolved={mod.resolved}
						spacing_v={mod.spacing_v || 'normal'}
						spacing_h={mod.spacing_h || 'normal'}
					/>
				{:else if mod.type === 'vault_quality_verify'}
					<VaultQualityVerify
						config={mod.config}
						resolved={mod.resolved}
						spacing_v={mod.spacing_v || 'normal'}
						spacing_h={mod.spacing_h || 'normal'}
					/>
				{:else if mod.type === 'vault_why_choose'}
					<VaultWhyChoose
						config={mod.config}
						resolved={mod.resolved}
						spacing_v={mod.spacing_v || 'normal'}
						spacing_h={mod.spacing_h || 'normal'}
					/>
					{#if vaultTestimonialsModule}
						<ReviewsListicle
							config={vaultTestimonialsModule.config}
							resolved={vaultTestimonialsModule.resolved}
							spacing_v={vaultTestimonialsModule.spacing_v || 'normal'}
							spacing_h={vaultTestimonialsModule.spacing_h || 'normal'}
							variant="product"
							visibleSlides={3}
							showHeadline={true}
							transparentBg={true}
						/>
					{/if}
				{:else if mod.type === 'vault_cta'}
					<VaultCta
						config={mod.config}
						resolved={mod.resolved}
						spacing_v={mod.spacing_v || 'normal'}
						spacing_h={mod.spacing_h || 'normal'}
					/>
				{:else if mod.type === 'text_block'}
					<TextBlock config={mod.config} resolved={mod.resolved} spacing_v={mod.spacing_v || 'normal'} spacing_h={mod.spacing_h || 'normal'} center_header={mod.center_header || false} />
				{:else if mod.type === 'gallery'}
					<Gallery config={mod.config} spacing_v={mod.spacing_v || 'normal'} spacing_h={mod.spacing_h || 'normal'} center_header={mod.center_header || false} />
				{:else if mod.type === 'category_grid'}
					<CategoryGrid config={mod.config} spacing_v={mod.spacing_v || 'normal'} spacing_h={mod.spacing_h || 'normal'} center_header={mod.center_header || false} />
				{:else if mod.type === 'split_features'}
					<SplitFeatures config={mod.config} resolved={mod.resolved} spacing_v={mod.spacing_v || 'normal'} spacing_h={mod.spacing_h || 'normal'} center_header={mod.center_header || false} />
				{:else if mod.type === 'split_value'}
					<SplitValue config={mod.config} resolved={mod.resolved} spacing_v={mod.spacing_v || 'normal'} spacing_h={mod.spacing_h || 'normal'} />
				{:else if mod.type === 'feature_highlights'}
					<FeatureHighlights config={mod.config} resolved={mod.resolved} spacing_v={mod.spacing_v || 'normal'} spacing_h={mod.spacing_h || 'normal'} />
				{:else if mod.type === 'shop_grid'}
					<ShopGrid title={mod.config.title || 'Shop'} category={mod.config.category} spacing_v={mod.spacing_v || 'normal'} spacing_h={mod.spacing_h || 'normal'} center_header={mod.center_header || false} />
				{:else if mod.type === 'contact_form'}
					<ContactForm config={mod.config} spacing_v={mod.spacing_v || 'normal'} spacing_h={mod.spacing_h || 'normal'} center_header={mod.center_header || false} resolved={mod.resolved} />
				{:else if mod.type === 'hero'}
					<Hero hero={mod.config} resolved={mod.resolved} />
				{:else if mod.type === 'cta'}
					<CTA config={mod.config} resolved={mod.resolved} spacing_v={mod.spacing_v || 'normal'} spacing_h={mod.spacing_h || 'normal'} />
				{:else if mod.type === 'spacer'}
					<Spacer config={mod.config} />
				{:else if mod.type === 'logo_strip'}
					<LogoStrip config={mod.config} spacing_v={mod.spacing_v || 'normal'} spacing_h={mod.spacing_h || 'normal'} center_header={mod.center_header || false} />
				{:else if mod.type === 'video'}
					<Video config={mod.config} spacing_v={mod.spacing_v || 'normal'} spacing_h={mod.spacing_h || 'normal'} center_header={mod.center_header || false} />
				{/if}
			</div>
		{/each}
	</article>
	{#if whyAlyveStickyCta}
		<WhyAlyveStickyCta label={whyAlyveStickyCta.label} href={whyAlyveStickyCta.href} />
	{/if}
{:else if config.ready}
	<div class="content-page content-page--404">
		<h1 class="content-page__title">Page not found</h1>
		<p class="content-page__message">The page you're looking for doesn't exist.</p>
		<a class="content-page__back" href="/">Back to home</a>
	</div>
{/if}
</AccessGate>

<style>
	.content-page {
		max-width: 1440px;
		margin: 0 auto;
		padding: 56px 28px 64px;
	}
	.content-page--listicle {
		/* Half-padding per block → 64px between sections (32px + 32px). */
		--wchs-page-section-half: 32px;
		--wchs-page-section-bottom: 72px;
		max-width: none;
		padding: 0 0 var(--wchs-page-section-bottom);
		display: flex;
		flex-direction: column;
	}

	.content-page--listicle > :global(section.listicle),
	.content-page--listicle > :global(section.promo-offer),
	.content-page--listicle > :global(section.reviews-listicle),
	.content-page--listicle > :global(section.listicle-faqs),
	.content-page--listicle > :global(section.compare) {
		--mod-pt: var(--wchs-page-section-half);
		--mod-pb: var(--wchs-page-section-half);
	}

	.content-page--listicle > :global(section:first-child) {
		--mod-pt: clamp(44px, 6vw, 64px);
	}

	@media (min-width: 901px) {
		.content-page--listicle > :global(section.listicle.has-editorial-hero) {
			--listicle-max: min(980px, 100%);
			--mod-px: clamp(20px, 2.5vw, 36px);
		}
		.content-page--listicle > :global(section.compare.is-text-mode) {
			--mod-max-w: min(1320px, calc(100% - 48px));
			--mod-px: clamp(20px, 2.5vw, 36px);
		}
		.content-page--listicle > :global(section.promo-offer) {
			--po-max: min(1080px, 100%);
		}
		.content-page--listicle > :global(section.reviews-listicle) {
			--rl-max: min(1180px, 100%);
		}

		.content-page--listicle > :global(section.reviews-listicle.is-proof),
		.content-page--listicle > :global(section.reviews-listicle.is-product),
		.content-page--listicle > :global(section.reviews-listicle.is-marquee) {
			--rl-max: min(1120px, calc(100% - 48px));
		}
		.content-page--listicle > :global(section.listicle-faqs) {
			--lf-max: min(980px, 100%);
		}
	}

	.content-page--listicle :global(.listicle__prose p),
	.content-page--listicle :global(.listicle__intro p) {
		font-size: 16px;
		line-height: 1.75;
		color: var(--fg);
	}

	.content-page--listicle :global(.listicle__cta),
	.content-page--listicle :global(.listicle__hero-cta),
	.content-page--listicle :global(.promo-offer__cta),
	.content-page--listicle :global(a.cta),
	.content-page--listicle :global(button.cta) {
		border-radius: 14px;
	}

	@media (max-width: 640px) {
		.content-page--listicle {
			--wchs-page-section-half: 24px;
			--wchs-page-section-bottom: 48px;
		}

		.content-page--listicle > :global(section:first-child) {
			--mod-pt: 8px;
		}
	}

	.content-page--vault {
		padding-top: 0;
		padding-inline: 0;
		max-width: none;
	}

	.content-page--vault > :global(.vault-hero:first-child) {
		--mod-pt: 0;
		--mod-pb: 0;
	}

	.content-page--vault > :global(.vault-hero + .vault-quality-tabs) {
		margin-top: 0;
	}

	.content-page--vault > :global(.vault-quality-tabs) {
		--mod-pt: 0;
	}

	.content-page--vault > :global(section.compare) {
		--mod-pt: clamp(32px, 5vw, 56px);
		--mod-pb: clamp(40px, 6vw, 72px);
		max-width: none;
		padding-inline: clamp(16px, 4vw, 28px);
	}

	.content-page--vault > :global(section.compare .compare__headline),
	.content-page--vault > :global(section.compare .compare__title),
	.content-page--vault > :global(section.compare .compare__lead),
	.content-page--vault > :global(section.compare .compare__lead--html) {
		text-align: center;
		margin-inline: auto;
	}

	.content-page--why-alyve {
		padding-bottom: var(--wchs-page-section-bottom, 72px);
	}

	.content-page--why-alyve > :global(section.listicle.has-editorial-hero:first-child) {
		--mod-pt: 0;
	}

	.content-page--why-alyve > :global(section.listicle.has-editorial-hero .listicle__hero-band) {
		padding-top: clamp(48px, 7vw, 80px);
	}

	/* Section headings — match Why Alyve comparison title; skip listicle H1 + reason titles */
	.content-page--why-alyve :global(.reviews-listicle__headline),
	.content-page--why-alyve :global(.listicle-faqs__headline),
	.content-page--why-alyve :global(.compare__headline),
	.content-page--why-alyve :global(.compare__title) {
		text-align: center;
	}

	.content-page--why-alyve :global(.reviews-listicle__subheadline) {
		text-align: center;
		max-width: 42ch;
		margin-inline: auto;
	}
	@media (max-width: 639px) {
		.content-page--why-alyve {
			padding-bottom: calc(var(--wchs-page-section-bottom, 48px) + 84px);
		}
	}
	.content-page__title {
		font-family: var(--font-heading, var(--font-sans));
		font-size: clamp(32px, 4vw, 48px);
		font-weight: var(--heading-weight, 500);
		line-height: 1.05;
		letter-spacing: -0.03em;
		color: var(--fg);
		margin: 0 0 40px;
		padding: 0 0 24px;
		border-bottom: 1px solid var(--border);
	}
	.content-page--404 {
		text-align: center;
		padding: 120px 28px;
	}
	.content-page--404 .content-page__title {
		border: 0;
		margin-bottom: 16px;
	}
	.content-page__message {
		font-size: 14px;
		color: var(--fg-muted);
		margin: 0 0 32px;
	}
	.content-page__back {
		display: inline-flex;
		padding: 12px 24px;
		background: var(--accent);
		color: var(--accent-fg);
		border: 1px solid var(--accent);
		border-radius: var(--radius-sm);
		text-decoration: none;
		font-size: 12px;
		font-weight: 600;
		text-transform: uppercase;
		letter-spacing: 0.1em;
	}
	.content-page__back:hover {
		background: transparent;
		color: var(--accent);
	}
	@media (max-width: 640px) {
		.content-page {
			padding: 32px 20px 48px;
		}
	}
</style>
