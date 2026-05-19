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
	import ContactForm from '$lib/components/ContactForm.svelte';
	import Hero from '$lib/components/Hero.svelte';
	import CTA from '$lib/components/CTA.svelte';
	import Spacer from '$lib/components/Spacer.svelte';
	import LogoStrip from '$lib/components/LogoStrip.svelte';
	import Video from '$lib/components/Video.svelte';
	import Listicle from '$lib/components/Listicle.svelte';
	import PromoOffer from '$lib/components/PromoOffer.svelte';
	import ReviewsListicle from '$lib/components/ReviewsListicle.svelte';
	import ListicleFaqs from '$lib/components/ListicleFaqs.svelte';
	import SEO from '$lib/components/SEO.svelte';
	import {
		config,
		homepageModulesWithSplitValueAfterHero,
		isHomepageModuleShown,
		type HomepageHeroConfig,
	} from '$lib/config.svelte';

	const hero = $derived(config.data.homepage.hero);

	const homepageTopHero = $derived.by((): HomepageHeroConfig => {
		const h = config.data.homepage.hero;
		return {
			...h,
			variant: 'research-motion',
			content_mode: 'text',
			layout: 'center',
			image_desktop: '',
			image_mobile: '',
			show_eyebrow: false,
			text_color_mode: 'white',
		};
	});

	const modules = $derived(
		homepageModulesWithSplitValueAfterHero(config.data.homepage.modules).filter(
			(m) => m.type !== 'trust_bar' && isHomepageModuleShown(m)
		)
	);

</script>

<SEO
	title={config.data.static_seo_title || config.data.brand_name}
	description={config.data.static_seo_description || hero.subheadline || hero.headline || `${config.data.brand_name} online store.`}
	image={hero.image_desktop || config.data.logo_full_url || config.data.logo_url || ''}
	type="website"
	schema={[
		{
			'@context': 'https://schema.org',
			'@type': 'Organization',
			name: config.data.brand_name,
			url: config.data.spa_origin || (typeof window !== 'undefined' ? window.location.origin : ''),
			logo: config.data.logo_url || undefined,
			sameAs: []
		},
		{
			'@context': 'https://schema.org',
			'@type': 'WebSite',
			name: config.data.brand_name,
			url: config.data.spa_origin || (typeof window !== 'undefined' ? window.location.origin : ''),
			potentialAction: {
				'@type': 'SearchAction',
				target: {
					'@type': 'EntryPoint',
					urlTemplate: `${config.data.spa_origin || ''}/shop?search={search_term_string}`
				},
				'query-input': 'required name=search_term_string'
			}
		}
	]}
/>

<AccessGate requires="products">
<Hero hero={homepageTopHero} />

{#each modules as mod}
	<div class="wchs-mod-wrap" data-module-type={mod.type} data-module-id={mod.id ?? ''} style="display: contents">
		{#if mod.type === 'product_slider' && mod.config.source === 'all'}
			<ShopCatalog
				spacing_v={mod.spacing_v || 'normal'}
				spacing_h={mod.spacing_h || 'normal'}
				showPageHead={false}
				showIntro={true}
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
		{:else if mod.type === 'promo_offer'}
			<PromoOffer config={mod.config} resolved={mod.resolved} spacing_v={mod.spacing_v || 'normal'} spacing_h={mod.spacing_h || 'normal'} />
		{:else if mod.type === 'reviews_listicle'}
			<ReviewsListicle config={mod.config} resolved={mod.resolved} spacing_v={mod.spacing_v || 'normal'} spacing_h={mod.spacing_h || 'normal'} />
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
		{:else if mod.type === 'split_value'}
			<SplitValue config={mod.config} resolved={mod.resolved} spacing_v={mod.spacing_v || 'normal'} spacing_h={mod.spacing_h || 'normal'} />
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
