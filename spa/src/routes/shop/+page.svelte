<script lang="ts">
	import { page } from '$app/state';
	import { config } from '$lib/config.svelte';
	import AccessGate from '$lib/components/AccessGate.svelte';
	import ShopCatalog from '$lib/components/ShopCatalog.svelte';
	import SEO from '$lib/components/SEO.svelte';
	import HomepageProductSlider from '$lib/components/HomepageProductSlider.svelte';
	import ReviewSlider from '$lib/components/ReviewSlider.svelte';
	import Accordion from '$lib/components/Accordion.svelte';
	import TrustBar from '$lib/components/TrustBar.svelte';
	import TextBlock from '$lib/components/TextBlock.svelte';
	import Listicle from '$lib/components/Listicle.svelte';
	import PromoOffer from '$lib/components/PromoOffer.svelte';
	import ReviewsListicle from '$lib/components/ReviewsListicle.svelte';
	import ListicleFaqs from '$lib/components/ListicleFaqs.svelte';
	import Gallery from '$lib/components/Gallery.svelte';
	import CategoryGrid from '$lib/components/CategoryGrid.svelte';
	import SplitFeatures from '$lib/components/SplitFeatures.svelte';
	import SplitValue from '$lib/components/SplitValue.svelte';
	import FeatureHighlights from '$lib/components/FeatureHighlights.svelte';
	import OrderHandling from '$lib/components/OrderHandling.svelte';
	import ContactForm from '$lib/components/ContactForm.svelte';

	const shopModules = $derived(config.data.shop?.modules ?? []);
	const shopSearch = $derived(page.url.searchParams.get('search') ?? '');

	const shopBreadcrumb = $derived.by(() => {
		const origin = typeof window !== 'undefined'
			? window.location.origin
			: ((config.data as any).spa_origin || '');
		return {
			'@context': 'https://schema.org',
			'@type': 'BreadcrumbList',
			itemListElement: [
				{ '@type': 'ListItem', position: 1, name: config.data.brand_name, item: `${origin}/` },
				{ '@type': 'ListItem', position: 2, name: 'Shop',                 item: `${origin}/shop` },
			],
		};
	});
	const shopUrl = $derived.by(() => {
		const origin = typeof window !== 'undefined'
			? window.location.origin
			: ((config.data as any).spa_origin || '');
		return `${origin}/shop`;
	});
</script>

<SEO title="Shop" description={`${config.data.brand_name} — browse all products`} url={shopUrl} type="website" schema={shopBreadcrumb} />

<AccessGate requires="products">
	<ShopCatalog
		spacing_v="normal"
		spacing_h={config.data.shop?.spacing_h ?? 'normal'}
		searchQuery={shopSearch}
	/>

	{#each shopModules as mod}
		{#if mod.type === 'product_slider'}
			<HomepageProductSlider config={mod.config} spacing_v={mod.spacing_v || 'normal'} spacing_h={mod.spacing_h || 'normal'} center_header={mod.center_header || false} />
		{:else if mod.type === 'review_slider'}
			<ReviewSlider title={mod.config.title || 'What customers say'} photos_only={mod.config.photos_only || false} product_ids={mod.config.product_ids || []} spacing_v={mod.spacing_v || 'normal'} spacing_h={mod.spacing_h || 'normal'} center_header={mod.center_header || false} />
		{:else if mod.type === 'order_handling'}
			<OrderHandling config={mod.config} resolved={mod.resolved} spacing_v={mod.spacing_v || 'normal'} spacing_h={mod.spacing_h || 'normal'} center_header={mod.center_header ?? true} />
		{:else if mod.type === 'accordion'}
			<Accordion config={mod.config} spacing_v={mod.spacing_v || 'normal'} spacing_h={mod.spacing_h || 'normal'} center_header={mod.center_header || false} />
		{:else if mod.type === 'trust_bar'}
			<TrustBar config={mod.config} spacing_v={mod.spacing_v || 'normal'} spacing_h={mod.spacing_h || 'normal'} />
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
		{:else if mod.type === 'contact_form'}
			<ContactForm config={mod.config} spacing_v={mod.spacing_v || 'normal'} spacing_h={mod.spacing_h || 'normal'} center_header={mod.center_header || false} />
		{/if}
	{/each}
</AccessGate>
