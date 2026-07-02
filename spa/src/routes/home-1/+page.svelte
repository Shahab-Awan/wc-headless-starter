<script lang="ts">
	import AccessGate from '$lib/components/AccessGate.svelte';
	import HomepageProductSlider from '$lib/components/HomepageProductSlider.svelte';
	import ReviewSlider from '$lib/components/ReviewSlider.svelte';
	import Accordion from '$lib/components/Accordion.svelte';
	import TextBlock from '$lib/components/TextBlock.svelte';
	import Gallery from '$lib/components/Gallery.svelte';
	import CategoryGrid from '$lib/components/CategoryGrid.svelte';
	import SplitFeatures from '$lib/components/SplitFeatures.svelte';
	import SplitValue from '$lib/components/SplitValue.svelte';
	import FeatureHighlights from '$lib/components/FeatureHighlights.svelte';
	import OrderHandling from '$lib/components/OrderHandling.svelte';
	import ShopCatalog from '$lib/components/ShopCatalog.svelte';
	import FeaturedProducts from '$lib/components/FeaturedProducts.svelte';
	import ContactForm from '$lib/components/ContactForm.svelte';
	import Hero from '$lib/components/Hero.svelte';
	import CTA from '$lib/components/CTA.svelte';
	import Spacer from '$lib/components/Spacer.svelte';
	import LogoStrip from '$lib/components/LogoStrip.svelte';
	import Video from '$lib/components/Video.svelte';
	import TrustBar from '$lib/components/TrustBar.svelte';
	import Listicle from '$lib/components/Listicle.svelte';
	import PromoOffer from '$lib/components/PromoOffer.svelte';
	import ReviewsListicle from '$lib/components/ReviewsListicle.svelte';
	import ListicleFaqs from '$lib/components/ListicleFaqs.svelte';
	import SEO from '$lib/components/SEO.svelte';
	import { config } from '$lib/config.svelte';
	import { resolveHome1Landing } from '$lib/home-1-landing';

	const landing = $derived(resolveHome1Landing(config.data.home_1, config.data.homepage));

	const hero = $derived.by(() => {
		let h = landing.hero;
		if (h.variant === 'research-motion') {
			h = { ...h, variant: 'webgl-variant-6' as const, layout: 'precision' as const };
		}
		return h;
	});

	const modules = $derived(landing.modules);
</script>

<SEO
	title="{config.data.brand_name} — Research Peptides"
	description={hero.subheadline || hero.headline || `${config.data.brand_name} research-grade peptides.`}
	image={config.data.static_seo_image_url || hero.image_desktop || config.data.logo_full_url || config.data.logo_url || ''}
	type="website"
	schema={[
		{
			'@context': 'https://schema.org',
			'@type': 'Organization',
			name: config.data.brand_name,
			url: config.data.spa_origin || (typeof window !== 'undefined' ? window.location.origin : ''),
			logo: config.data.logo_url || undefined,
			sameAs: [],
		},
	]}
/>

<AccessGate requires="products">
<Hero hero={hero} />

{#each modules as mod}
	<div class="wchs-mod-wrap" data-module-type={mod.type} data-module-id={mod.id ?? ''} style="display: contents">
		{#if mod.type === 'featured_products'}
			<FeaturedProducts
				config={mod.config}
				resolved={mod.resolved}
				spacing_v={mod.spacing_v || 'normal'}
				spacing_h={mod.spacing_h || 'normal'}
			/>
		{:else if mod.type === 'product_slider'}
			<HomepageProductSlider config={mod.config} spacing_v={mod.spacing_v || 'normal'} spacing_h={mod.spacing_h || 'normal'} center_header={mod.center_header || false} />
		{:else if mod.type === 'review_slider'}
			<ReviewSlider title={mod.config.title || 'What customers say'} photos_only={mod.config.photos_only || false} product_ids={mod.config.product_ids || []} spacing_v={mod.spacing_v || 'normal'} spacing_h={mod.spacing_h || 'normal'} center_header={mod.center_header || false} />
		{:else if mod.type === 'order_handling'}
			<OrderHandling config={mod.config} resolved={mod.resolved} spacing_v={mod.spacing_v || 'normal'} spacing_h={mod.spacing_h || 'normal'} center_header={mod.center_header ?? true} />
		{:else if mod.type === 'accordion'}
			<Accordion config={mod.config} spacing_v={mod.spacing_v || 'normal'} spacing_h={mod.spacing_h || 'normal'} center_header={mod.center_header || false} />
		{:else if mod.type === 'listicle'}
			<Listicle config={mod.config} resolved={mod.resolved} spacing_v={mod.spacing_v || 'normal'} spacing_h={mod.spacing_h || 'normal'} />
		{:else if mod.type === 'trust_bar'}
			<TrustBar
				config={mod.config}
				resolved={mod.resolved}
				spacing_v={mod.spacing_v || 'compact'}
				spacing_h={mod.spacing_h || 'normal'}
			/>
		{:else if mod.type === 'reviews_listicle' && (mod.config.items?.length ?? 0) > 0}
			<ReviewsListicle
				config={mod.config}
				resolved={mod.resolved}
				spacing_v={mod.spacing_v || 'normal'}
				spacing_h={mod.spacing_h || 'normal'}
				variant="product"
				visibleSlides={3}
				showHeadline={true}
			/>
		{:else if mod.type === 'listicle_faqs'}
			<ListicleFaqs config={mod.config} resolved={mod.resolved} spacing_v={mod.spacing_v || 'normal'} spacing_h={mod.spacing_h || 'normal'} />
		{:else if mod.type === 'text_block'}
			<TextBlock config={mod.config} resolved={mod.resolved} spacing_v={mod.spacing_v || 'normal'} spacing_h={mod.spacing_h || 'normal'} center_header={mod.center_header || false} />
		{:else if mod.type === 'gallery'}
			<Gallery config={mod.config} spacing_v={mod.spacing_v || 'normal'} spacing_h={mod.spacing_h || 'normal'} center_header={mod.center_header || false} />
		{:else if mod.type === 'category_grid'}
			<CategoryGrid config={mod.config} spacing_v={mod.spacing_v || 'normal'} spacing_h={mod.spacing_h || 'normal'} center_header={mod.center_header || false} />
		{:else if mod.type === 'split_features'}
			<SplitFeatures config={mod.config} resolved={mod.resolved} spacing_v={mod.spacing_v || 'normal'} spacing_h={mod.spacing_h || 'normal'} center_header={mod.center_header || false} />
		{:else if mod.type === 'feature_highlights'}
			<FeatureHighlights config={mod.config} resolved={mod.resolved} spacing_v={mod.spacing_v || 'normal'} spacing_h={mod.spacing_h || 'normal'} />
		{:else if mod.type === 'shop_grid'}
			<ShopCatalog
				spacing_v={mod.spacing_v || 'normal'}
				spacing_h={mod.spacing_h || 'normal'}
				showPageHead={false}
				showIntro={true}
			/>
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

</AccessGate>
