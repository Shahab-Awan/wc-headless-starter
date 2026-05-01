<script lang="ts">
	import AccessGate from '$lib/components/AccessGate.svelte';
	import HomepageProductSlider from '$lib/components/HomepageProductSlider.svelte';
	import ReviewSlider from '$lib/components/ReviewSlider.svelte';
	import Accordion from '$lib/components/Accordion.svelte';
	import TrustBar from '$lib/components/TrustBar.svelte';
	import TextBlock from '$lib/components/TextBlock.svelte';
	import Gallery from '$lib/components/Gallery.svelte';
	import CategoryGrid from '$lib/components/CategoryGrid.svelte';
	import SplitFeatures from '$lib/components/SplitFeatures.svelte';
	import ShopGrid from '$lib/components/ShopGrid.svelte';
	import ContactForm from '$lib/components/ContactForm.svelte';
	import RecentlyViewed from '$lib/components/RecentlyViewed.svelte';
	import Hero from '$lib/components/Hero.svelte';
	import CTA from '$lib/components/CTA.svelte';
	import Spacer from '$lib/components/Spacer.svelte';
	import LogoStrip from '$lib/components/LogoStrip.svelte';
	import Video from '$lib/components/Video.svelte';
	import SEO from '$lib/components/SEO.svelte';
	import { config, isModuleVisibleNow } from '$lib/config.svelte';

	const hero = $derived(config.data.homepage.hero);
	const modules = $derived(config.data.homepage.modules.filter(isModuleVisibleNow));

</script>

<SEO
	title={config.data.static_seo_title || config.data.brand_name}
	description={config.data.static_seo_description || hero.subheadline || hero.headline || `${config.data.brand_name} online store.`}
	image={hero.image_desktop || ''}
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
<Hero hero={hero} />

{#each modules as mod}
	<div class="wchs-mod-wrap" data-module-type={mod.type} data-module-id={mod.id ?? ''} style="display: contents">
		{#if mod.type === 'product_slider'}
			<HomepageProductSlider config={mod.config} spacing_v={mod.spacing_v || 'normal'} spacing_h={mod.spacing_h || 'normal'} center_header={mod.center_header || false} />
		{:else if mod.type === 'review_slider'}
			<ReviewSlider title={mod.config.title || 'What customers say'} photos_only={mod.config.photos_only || false} product_ids={mod.config.product_ids || []} spacing_v={mod.spacing_v || 'normal'} spacing_h={mod.spacing_h || 'normal'} center_header={mod.center_header || false} />
		{:else if mod.type === 'accordion'}
			<Accordion config={mod.config} spacing_v={mod.spacing_v || 'normal'} spacing_h={mod.spacing_h || 'normal'} center_header={mod.center_header || false} />
		{:else if mod.type === 'trust_bar'}
			<TrustBar config={mod.config} spacing_v={mod.spacing_v || 'normal'} spacing_h={mod.spacing_h || 'normal'} resolved={mod.resolved} />
		{:else if mod.type === 'text_block'}
			<TextBlock config={mod.config} spacing_v={mod.spacing_v || 'normal'} spacing_h={mod.spacing_h || 'normal'} center_header={mod.center_header || false} />
		{:else if mod.type === 'gallery'}
			<Gallery config={mod.config} spacing_v={mod.spacing_v || 'normal'} spacing_h={mod.spacing_h || 'normal'} center_header={mod.center_header || false} />
		{:else if mod.type === 'category_grid'}
			<CategoryGrid config={mod.config} spacing_v={mod.spacing_v || 'normal'} spacing_h={mod.spacing_h || 'normal'} center_header={mod.center_header || false} />
		{:else if mod.type === 'split_features'}
			<SplitFeatures config={mod.config} spacing_v={mod.spacing_v || 'normal'} spacing_h={mod.spacing_h || 'normal'} center_header={mod.center_header || false} />
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

<!-- Recently viewed strip — renders nothing when the visitor has no PDP
     history yet. Sits after the admin-configured modules so it's the
     last thing above any footer content the layout adds. -->
<RecentlyViewed />
</AccessGate>
