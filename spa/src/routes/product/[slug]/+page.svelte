<script lang="ts">
	import { onMount } from 'svelte';
	import { page } from '$app/state';
	import { fade, fly } from 'svelte/transition';
	import EmblaCarousel, { type EmblaCarouselType } from 'embla-carousel';
	import AccessGate from '$lib/components/AccessGate.svelte';
	import { config } from '$lib/config.svelte';
	import {
		getProduct,
		getProductsByIds,
		getVariations,
		findVariationId,
		findPurchasableDefaultSelection,
		type StoreProduct,
		type StoreProductVariation
	} from '$lib/wc/products';
	import { canPurchase, isOutOfStock } from '$lib/wc/stock';
	import { cart } from '$lib/wc/cart.svelte';
	import PdpBuyBox from '$lib/components/pdp/PdpBuyBox.svelte';
	import PdpCrossSell from '$lib/components/pdp/PdpCrossSell.svelte';
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
	import ShopGrid from '$lib/components/ShopGrid.svelte';
	import ContactForm from '$lib/components/ContactForm.svelte';
	import SEO from '$lib/components/SEO.svelte';
	import { formatPrice, priceAsNumber } from '$lib/utils/format';

	const pdpModules = $derived(config.data.pdp?.modules ?? []);

	import ReviewCard, { type ReviewData } from '$lib/components/ReviewCard.svelte';

	let product = $state<StoreProduct | null>(null);
	let variations = $state<StoreProductVariation[]>([]);
	let crossSells = $state<StoreProduct[]>([]);
	let reviews = $state<ReviewData[]>([]);
	let reviewDistribution = $state<number[]>([0, 0, 0, 0, 0]);
	let reviewAverage = $state(0);
	let reviewCount = $state(0);
	let reviewsOpen = $state(false);
	let reviewModalDetail = $state<ReviewData | null>(null);
	let reviewViewport = $state<HTMLElement | undefined>();
	let reviewTrack = $state<HTMLElement | undefined>();
	let reviewEmbla: EmblaCarouselType | undefined;
	let error = $state<string | null>(null);
	let loading = $state(true);

	$effect(() => {
		if (!reviewsOpen || !reviewViewport || !reviewTrack || reviews.length === 0) return;
		reviewEmbla = EmblaCarousel(reviewViewport, {
			align: 'start',
			containScroll: 'trimSnaps',
			dragFree: true,
			container: reviewTrack,
		});
		return () => reviewEmbla?.destroy();
	});

	let selection = $state<Record<string, string>>({});
	let quantity = $state(1);

	// Seed selection from the first purchasable variation once variation
	// payloads are loaded (WC defaults only when that variation is in stock).
	$effect(() => {
		if (!product?.has_options || variations.length === 0) return;
		const defaults = findPurchasableDefaultSelection(product, variations);
		if (!defaults) return;
		const keys = product.attributes.map((a) => a.name);
		if (keys.every((k) => selection[k])) return;
		let touched = false;
		const next = { ...selection };
		for (const [k, v] of Object.entries(defaults)) {
			if (!next[k]) {
				next[k] = v;
				touched = true;
			}
		}
		if (touched) selection = next;
	});
	let adding = $state(false);
	let activeImage = $state(0);

	// Embla gallery
	let galleryViewport = $state<HTMLElement | undefined>();
	let galleryContainer = $state<HTMLElement | undefined>();
	let embla: EmblaCarouselType | undefined;

	$effect(() => {
		if (!galleryViewport || !galleryContainer || !product || product.images.length === 0) return;
		embla = EmblaCarousel(galleryViewport, {
			align: 'start',
			containScroll: 'keepSnaps',
			loop: false,
			container: galleryContainer,
		});
		embla.on('select', () => {
			activeImage = embla!.selectedScrollSnap();
		});
		return () => embla?.destroy();
	});

	// Jump carousel to variation image ONLY when the variant selection
	// actually changes — NOT every time the effect's reactive graph
	// updates (which includes `activeImage` via the embla select event).
	// Without the guard, the effect snaps the user back to the variant
	// image every time they scroll to a different gallery slide, making
	// the gallery feel "locked" to the variant.
	//
	// Track the last variant ID we jumped for; fire the scrollTo only
	// when the selected variant ID differs. After the jump, the user is
	// free to navigate the gallery normally until they pick a different
	// variant.
	let lastJumpedVariantId = $state<number | null>(null);
	$effect(() => {
		if (!product || !selectedVariation || !embla) return;
		if (selectedVariation.id === lastJumpedVariantId) return;
		const varImg = selectedVariation.images?.[0];
		if (!varImg) { lastJumpedVariantId = selectedVariation.id; return; }
		let idx = product.images.findIndex(img => img.id === varImg.id);
		if (idx < 0) {
			// variation's image isn't in parent gallery — inject it at position 0
			product.images = [varImg, ...product.images];
			idx = 0;
			embla.reInit();
		}
		embla.scrollTo(idx);
		lastJumpedVariantId = selectedVariation.id;
	});

	// Lightbox
	let lightboxOpen = $state(false);
	let lightboxIndex = $state(0);
	let lightboxEl = $state<HTMLElement | undefined>();

	function openLightbox(index: number) {
		lightboxIndex = index;
		lightboxOpen = true;
	}

	function closeLightbox() {
		lightboxOpen = false;
	}

	function lightboxPrev() {
		if (!product) return;
		lightboxIndex = (lightboxIndex - 1 + product.images.length) % product.images.length;
	}

	function lightboxNext() {
		if (!product) return;
		lightboxIndex = (lightboxIndex + 1) % product.images.length;
	}

	function lightboxKey(e: KeyboardEvent) {
		if (!lightboxOpen) return;
		if (e.key === 'Escape') closeLightbox();
		else if (e.key === 'ArrowLeft') lightboxPrev();
		else if (e.key === 'ArrowRight') lightboxNext();
	}

	// Focus lightbox on open for keyboard nav
	$effect(() => {
		if (lightboxOpen && lightboxEl) {
			lightboxEl.focus();
		}
	});

	onMount(async () => {
		try {
			product = await getProduct(page.params.slug ?? '');
			if (product && product.has_options && product.variations.length) {
				variations = await getVariations(product.variations.map((v) => v.id));
				const defaults = findPurchasableDefaultSelection(product, variations);
				if (defaults) selection = defaults;
			}
			const ids = product?.extensions?.wchs_cro?.cross_sell_ids ?? [];
			if (ids.length > 0) {
				crossSells = await getProductsByIds(ids);
			}

			// Push this product into the Recently-Viewed LRU list.
			// Deduplicates on id (any prior entry is removed first so the
			// re-push lands at the head). Capped at 10 entries. Wrapped in
			// try/catch because localStorage can throw (private mode, quota
			// exhaustion) and we never want that to break the PDP render.
			if (product && typeof localStorage !== 'undefined') {
				try {
					const KEY = 'wchs_recently_viewed';
					const raw = localStorage.getItem(KEY);
					let list: { id: number; ts: number }[] = [];
					if (raw) {
						try {
							const parsed = JSON.parse(raw);
							if (Array.isArray(parsed)) {
								list = parsed.filter((e: unknown) => e && typeof e === 'object' && Number.isFinite((e as { id: unknown }).id));
							}
						} catch { /* corrupt — treat as empty */ }
					}
					const pid = product.id;
					list = list.filter(e => e.id !== pid);
					list.unshift({ id: pid, ts: Date.now() });
					if (list.length > 10) list = list.slice(0, 10);
					localStorage.setItem(KEY, JSON.stringify(list));
				} catch { /* storage blocked or full — non-critical */ }
			}
			// Fetch reviews (if enabled in PDP config)
			if (product && product.review_count > 0 && config.data.pdp?.show_reviews !== false) {
				try {
					const res = await fetch(`/wp-json/wchs/v1/reviews/${product.id}?per_page=20`);
					if (res.ok) {
						const data = await res.json();
						reviews = data.reviews ?? [];
						reviewDistribution = data.distribution ?? [0, 0, 0, 0, 0];
						reviewAverage = Number(data.average ?? 0);
						reviewCount = Number(data.count ?? 0);
					}
				} catch { /* reviews are non-critical */ }
			}
		// GA4 view_item + Omnisend + Klaviyo + Meta + TikTok pixels
			if (product) {
				import('$lib/analytics').then((a) => {
					const p = {
						id: product!.id,
						name: product!.name,
						prices: product!.prices,
						permalink: product!.permalink,
						images: product!.images,
					};
					a.trackViewItem(product!);
					a.trackOmnisendViewedProduct(p);
					a.trackKlaviyoViewedProduct(p);
					a.trackMetaViewContent(p);
					a.trackTikTokViewContent(p);
				});
			}
		} catch (e) {
			error = e instanceof Error ? e.message : String(e);
		} finally {
			loading = false;
		}
	});

	// Derived: selected variation id (or null if selection incomplete)
	const selectedVariationId = $derived(
		product?.has_options ? findVariationId(product.variations, selection) : null
	);

	// Derived: the full variation object if we have one selected
	const selectedVariation = $derived(
		variations.find((v) => v.id === selectedVariationId) ?? null
	);

	// Re-fire GA4 view_item when the user picks a different variation so
	// Meta Pixel / Omnisend / GA4 see accurate per-variation views. The
	// initial product view fired in onMount covers the parent; this adds
	// re-fires on each variation selection. Guarded to skip the first
	// eval (to avoid double-firing with the onMount event) and skip
	// re-fires for the same variation id.
	// Product JSON-LD schema derived from product + selected variation +
	// review aggregate. Fed to <SEO />. Reflects the variation price when
	// one is selected, otherwise the parent price (or price_range min
	// if it's a variable product without a selection yet).
	const productSchema = $derived.by(() => {
		if (!product) return null;
		const priceSource = selectedVariation?.prices ?? product.prices;
		const priceNum = priceAsNumber(priceSource.price, priceSource);
		const desc = (product.short_description || product.description || '')
			.replace(/<[^>]*>/g, '')
			.replace(/\s+/g, ' ')
			.trim()
			.substring(0, 300);
		const inStock = selectedVariation
			? canPurchase(selectedVariation)
			: canPurchase(product);
		const schema: Record<string, unknown> = {
			'@context': 'https://schema.org',
			'@type': 'Product',
			name: product.name,
			description: desc,
			image: product.images?.[0]?.src || '',
			sku: product.sku || '',
			offers: {
				'@type': 'Offer',
				price: priceNum.toFixed(priceSource.currency_minor_unit),
				priceCurrency: priceSource.currency_code,
				availability: inStock ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
				itemCondition: 'https://schema.org/NewCondition',
				url: `${config.data.spa_origin}/product/${product.slug}`,
			},
		};
		if (reviewCount > 0 && reviewAverage > 0) {
			schema.aggregateRating = {
				'@type': 'AggregateRating',
				ratingValue: reviewAverage.toFixed(1),
				reviewCount,
				bestRating: 5,
				worstRating: 1,
			};
		}
		return schema;
	});

	// BreadcrumbList schema: Home → Shop → <product>. Emitted alongside
	// Product schema so Google can render the breadcrumb trail under the
	// PDP's SERP snippet.
	const breadcrumbSchema = $derived.by(() => {
		if (!product) return null;
		const origin = typeof window !== 'undefined'
			? window.location.origin
			: (config.data.spa_origin || '');
		return {
			'@context': 'https://schema.org',
			'@type': 'BreadcrumbList',
			itemListElement: [
				{ '@type': 'ListItem', position: 1, name: config.data.brand_name, item: `${origin}/` },
				{ '@type': 'ListItem', position: 2, name: 'Shop',                 item: `${origin}/shop` },
				{ '@type': 'ListItem', position: 3, name: product.name,            item: `${origin}/product/${product.slug}` },
			],
		};
	});

	const pdpSchemas = $derived(
		[productSchema, breadcrumbSchema].filter(Boolean)
	);

	// Description derived for meta description tag.
	const pdpDescription = $derived(
		product
			? (product.short_description || product.description || '')
				.replace(/<[^>]*>/g, '')
				.replace(/\s+/g, ' ')
				.trim()
				.substring(0, 160)
			: ''
	);

	let lastTrackedVariationId: number | null = null;
	$effect(() => {
		const v = selectedVariation;
		const p = product;
		if (!v || !p) return;
		if (v.id === lastTrackedVariationId) return;
		lastTrackedVariationId = v.id;
		import('$lib/analytics').then((a) => {
			const pl = { id: v.id, name: p.name, prices: v.prices, permalink: p.permalink, images: p.images };
			a.trackViewItem({ id: v.id, name: p.name, prices: v.prices, permalink: p.permalink, images: p.images });
			a.trackOmnisendViewedProduct(pl);
			a.trackKlaviyoViewedProduct(pl);
			a.trackMetaViewContent(pl);
			a.trackTikTokViewContent(pl);
		});
	});

	// CRO: use variation-level CRO when a variation is selected, else parent.
	// For variable products without a selection, dollar-valued tiers aren't
	// meaningful (parent regular_price is 0), but percentage tiers still
	// carry valid savings_pct values for the badge.
	const cro = $derived.by(() => {
		if (selectedVariation?.extensions?.wchs_cro) {
			return selectedVariation.extensions.wchs_cro;
		}
		return product?.extensions?.wchs_cro ?? null;
	});

	// Parent-level CRO — always available, used for the "save up to X%" badge
	// before a variation is selected on variable products.
	const parentCro = $derived(product?.extensions?.wchs_cro ?? null);

	const regularMinor = $derived.by((): number => {
		if (!product) return 0;
		if (cro?.regular_price && cro.regular_price > 0) return cro.regular_price;
		if (selectedVariation) return Number(selectedVariation.prices.regular_price);
		return Number(product.prices.regular_price);
	});

	const hasTiers = $derived.by(() => {
		if (cro?.tiers?.length) return true;
		return (
			config.data.pdp?.bundle_bogo?.enabled !== false &&
			regularMinor > 0
		);
	});

	// Max savings pct — check parent CRO too so the badge shows before variation selection
	const maxTierPct = $derived.by(() => {
		// Prefer the active (variation-level) CRO
		if (cro?.tiers?.length) {
			const pct = cro.tiers[cro.tiers.length - 1].savings_pct ?? 0;
			if (pct > 0) return pct;
		}
		// Fall back to parent CRO (percentage tiers always have valid savings_pct)
		if (parentCro?.tiers?.length) {
			return parentCro.tiers[parentCro.tiers.length - 1].savings_pct ?? 0;
		}
		return 0;
	});

	// Active tier for line-total pricing when quantity matches a bundle row.
	const activeTier = $derived.by(() => {
		if (!cro?.tiers?.length) return null;
		let hit = null;
		for (const t of cro.tiers) {
			if (quantity >= t.min_qty) hit = t;
		}
		return hit;
	});

	// Base unit price in minor units (before tiers). Uses variation price
	// when selected, else parent price.
	const baseUnitPrice = $derived.by((): number => {
		if (!product) return 0;
		if (selectedVariation) return Number(selectedVariation.prices.price);
		return Number(product.prices.price);
	});

	// Effective unit price accounting for tier pricing
	const unitPrice = $derived(activeTier ? activeTier.unit_price : baseUnitPrice);

	// Line total
	const lineTotal = $derived(unitPrice * quantity);

	function formatMoneyInt(minorInt: number): string {
		if (!product) return '';
		return formatPrice(minorInt, product.prices);
	}
	function formatPct(p: number): string {
		return Number.isInteger(p) ? `${p}%` : `${p.toFixed(1)}%`;
	}

	// Derived: can add to cart? Simple: in stock + purchasable. Variable: needs
	// complete selection + selected variation in stock + purchasable.
	const canAdd = $derived.by(() => {
		if (!product) return false;
		if (adding) return false;
		if (!product.has_options) return canPurchase(product);
		if (!selectedVariationId || !selectedVariation) return false;
		return canPurchase(selectedVariation);
	});

	// Derived: button label — gives the user feedback on why disabled
	const addLabel = $derived.by(() => {
		if (!product) return 'Loading';
		if (adding) return 'Adding…';
		if (!product.has_options) {
			if (!canPurchase(product)) return isOutOfStock(product) ? 'Out of Stock' : 'Unavailable';
			return product.add_to_cart?.text ?? 'Add to Cart';
		}
		// Variable
		const missing = product.attributes.filter((a) => !selection[a.name]).map((a) => a.name);
		if (missing.length) return `Select ${missing[0]}`;
		if (!selectedVariationId) return 'Unavailable';
		if (selectedVariation && !canPurchase(selectedVariation)) return 'Out of Stock';
		return 'Add to Cart';
	});

	// Per-variation stock indexed by "attr1=val1|attr2=val2" signature, so
	// buttons can show which combos are in stock without hitting the API.
	const stockByVariationId = $derived(
		new Map(variations.map((v) => [v.id, canPurchase(v)]))
	);

	/**
	 * For a given (attribute, value) pair, is there at least one variation
	 * in stock that has this value AND is consistent with the rest of the
	 * current selection (for attributes already chosen)?
	 * Used to dim "impossible" combinations.
	 */
	function termAvailable(attrName: string, value: string): boolean {
		if (!product) return false;
		for (const v of product.variations) {
			const thisAttr = v.attributes.find((a) => a.name === attrName);
			if (!thisAttr || thisAttr.value !== value) continue;
			const otherAttrsOk = v.attributes.every((a) => {
				if (a.name === attrName) return true;
				const chosen = selection[a.name];
				return chosen === undefined || chosen === a.value;
			});
			if (!otherAttrsOk) continue;
			if (stockByVariationId.get(v.id)) return true;
		}
		return false;
	}

	function selectTerm(attrName: string, value: string) {
		// Toggle: clicking the selected value clears it
		if (selection[attrName] === value) {
			const next = { ...selection };
			delete next[attrName];
			selection = next;
		} else {
			selection = { ...selection, [attrName]: value };
		}
	}

	let justAdded = $state(false);

	async function handleAdd() {
		if (!product || !canAdd) return;
		adding = true;
		justAdded = false;
		try {
			if (product.has_options && selectedVariationId) {
				const variationPayload = product.attributes.map((attr) => ({
					attribute: attr.name,
					value: selection[attr.name]
				}));
				await cart.addItem(selectedVariationId, quantity, variationPayload, { clicked_from: 'product_page' });
			} else {
				await cart.addItem(product.id, quantity, [], { clicked_from: 'product_page' });
			}
			justAdded = true;
			setTimeout(() => (justAdded = false), 1500);
		} finally {
			adding = false;
		}
	}

	function formatReviewDate(iso: string): string {
		return new Date(iso).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
	}

</script>

{#if product}
	<SEO
		title={product.name}
		description={pdpDescription}
		image={product.images?.[0]?.src || ''}
		type="product"
		schema={pdpSchemas}
		nosnippet={config.data.seo_nosnippet_products === true}
	/>
{:else}
	<SEO title="Product" type="product" />
{/if}

<AccessGate requires="products">
{#if loading}
	<p class="loading">Loading…</p>
{:else if error}
	<p class="err">{error}</p>
{:else if !product}
	<p class="err">Not found.</p>
{:else}
	<article class="pdp">
		<div class="pdp__media">
			{#if product.images.length > 0}
				<div class="pdp__gallery-wrap">
				<div class="pdp__gallery" bind:this={galleryViewport}>
					<div class="pdp__gallery-track" bind:this={galleryContainer}>
						{#each product.images as img, i}
							<button
								type="button"
								class="pdp__gallery-slide"
								onclick={() => openLightbox(i)}
								aria-label="View image {i + 1} full size"
							>
								<img
									src={img.src}
									srcset={img.srcset}
									sizes={img.sizes}
									alt={img.alt || product.name}
									loading={i === 0 ? 'eager' : 'lazy'}
									fetchpriority={i === 0 ? 'high' : undefined}
									draggable="false"
								/>
							</button>
						{/each}
					</div>
				</div>
				{#if config.data.pdp?.image_disclaimer}
					<p class="pdp__image-disclaimer">{config.data.pdp.image_disclaimer}</p>
				{/if}
				</div>
				{#if product.images.length > 1}
					<div class="pdp__dots">
						{#each product.images as _, i}
							<button
								type="button"
								class="pdp__dot"
								class:pdp__dot--active={i === activeImage}
								onclick={() => embla?.scrollTo(i)}
								aria-label="Go to image {i + 1}"
							></button>
						{/each}
					</div>
				{/if}
			{:else}
				<div class="pdp__gallery pdp__gallery--placeholder">
					<svg viewBox="0 0 64 64" width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.2">
						<rect x="6" y="14" width="52" height="36" rx="1" />
						<circle cx="22" cy="28" r="4" />
						<path d="M58 42 L42 28 L22 46" />
					</svg>
				</div>
			{/if}
		</div>

		<PdpBuyBox
			{product}
			brandName={config.data.brand_name}
			{selection}
			onSelectTerm={selectTerm}
			{termAvailable}
			{selectedVariation}
			{quantity}
			onQuantityChange={(q) => (quantity = q)}
			{cro}
			{hasTiers}
			{regularMinor}
			{unitPrice}
			{lineTotal}
			{maxTierPct}
			{formatMoneyInt}
			{formatPct}
			{canAdd}
			{addLabel}
			{adding}
			{justAdded}
			onAdd={handleAdd}
			showReviews={product.review_count > 0 && config.data.pdp?.show_reviews !== false}
			onReviewsOpen={() => (reviewsOpen = true)}
		/>
	</article>

	{#each pdpModules as mod}
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
		{:else if mod.type === 'shop_grid'}
			<ShopGrid title={mod.config.title || 'Shop'} category={mod.config.category} spacing_v={mod.spacing_v || 'normal'} spacing_h={mod.spacing_h || 'normal'} center_header={mod.center_header || false} />
		{:else if mod.type === 'contact_form'}
			<ContactForm config={mod.config} spacing_v={mod.spacing_v || 'normal'} spacing_h={mod.spacing_h || 'normal'} center_header={mod.center_header || false} />
		{/if}
	{/each}

	<PdpCrossSell products={crossSells} />
{/if}
</AccessGate>

<!-- Lightbox -->
{#if lightboxOpen && product}
	<!-- svelte-ignore a11y_no_static_element_interactions -->
	<div
		class="lightbox"
		bind:this={lightboxEl}
		transition:fade={{ duration: 150 }}
		onkeydown={lightboxKey}
		role="dialog"
		aria-label="Image viewer"
		tabindex="-1"
	>
		<!-- svelte-ignore a11y_click_events_have_key_events a11y_no_static_element_interactions -->
		<div class="lightbox__backdrop" onclick={closeLightbox}></div>
		<button class="lightbox__close" onclick={closeLightbox} aria-label="Close">
			<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
		</button>
		{#if product.images.length > 1}
			<button class="lightbox__nav lightbox__nav--prev" onclick={lightboxPrev} aria-label="Previous image">
				<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
			</button>
			<button class="lightbox__nav lightbox__nav--next" onclick={lightboxNext} aria-label="Next image">
				<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
			</button>
		{/if}
		{#key lightboxIndex}
			<div class="lightbox__image" in:fade={{ duration: 120 }}>
				<img
					src={product.images[lightboxIndex].src}
					alt={product.images[lightboxIndex].alt || product.name}
				/>
			</div>
		{/key}
		{#if product.images.length > 1}
			<div class="lightbox__counter">{lightboxIndex + 1} / {product.images.length}</div>
		{/if}
	</div>
{/if}

<!-- Reviews modal -->
{#if reviewsOpen && product && reviews.length > 0}
	<div class="review-overlay" role="dialog" aria-label="Customer reviews">
		<!-- svelte-ignore a11y_click_events_have_key_events -->
		<div class="review-overlay__backdrop" role="presentation" onclick={() => (reviewsOpen = false)} transition:fade={{ duration: 150 }}></div>
		<div class="review-overlay__panel" transition:fly={{ y: 40, duration: 250 }}>
			<div class="review-overlay__header">
				<div class="review-overlay__header-left">
					<p class="review-overlay__label">Customer Reviews</p>
					<div class="review-overlay__summary">
						<span class="review-overlay__avg">{parseFloat(product.average_rating).toFixed(1)}</span>
						<span class="review-overlay__stars">
							{#each Array(5) as _, i}
								<span class="review-overlay__star" class:filled={i < Math.round(parseFloat(product.average_rating))}>★</span>
							{/each}
						</span>
						<span class="review-overlay__count">{reviews.length} {reviews.length === 1 ? 'review' : 'reviews'}</span>
					</div>
					{#if reviewDistribution.some(v => v > 0)}
					{@const maxDist = Math.max(1, ...reviewDistribution)}
						<div class="review-overlay__dist">
							{#each [5, 4, 3, 2, 1] as star}
								{@const count = reviewDistribution[star - 1]}
								<div class="review-overlay__dist-row">
									<span class="review-overlay__dist-label">{star}★</span>
									<div class="review-overlay__dist-bar">
										<div class="review-overlay__dist-fill" style="width: {(count / maxDist) * 100}%"></div>
									</div>
									<span class="review-overlay__dist-count">{count}</span>
								</div>
							{/each}
						</div>
					{/if}
				</div>
				<button class="review-overlay__close" onclick={() => (reviewsOpen = false)} aria-label="Close">
					<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
				</button>
			</div>
			<div class="review-overlay__viewport" bind:this={reviewViewport}>
				<div class="review-overlay__track" bind:this={reviewTrack}>
					{#each reviews as review (review.id)}
						<div class="review-overlay__slide">
							<ReviewCard {review} onclick={() => (reviewModalDetail = review)} />
						</div>
					{/each}
				</div>
			</div>
		</div>
	</div>
{/if}

<!-- Review detail modal -->
{#if reviewModalDetail}
	<div class="review-modal" role="dialog" aria-label="Review detail">
		<!-- svelte-ignore a11y_click_events_have_key_events -->
		<div class="review-modal__backdrop" role="presentation" onclick={() => (reviewModalDetail = null)} transition:fade={{ duration: 200 }}></div>
		<div class="review-modal__dialog" transition:fly={{ y: 24, duration: 250 }}>
			<button class="review-modal__close" onclick={() => (reviewModalDetail = null)} aria-label="Close">✕</button>
			{#if reviewModalDetail.images.length > 0}
				<div class="review-modal__images">
					{#each reviewModalDetail.images as img}
						<img src={img.src} alt="" />
					{/each}
				</div>
			{/if}
			<div class="review-modal__body">
				<div class="review-modal__header">
					<span class="review-modal__author">{reviewModalDetail.author}</span>
					{#if reviewModalDetail.verified}
						<span class="review-modal__verified">Verified purchase</span>
					{/if}
				</div>
				<div class="review-modal__stars-row">
					{#each Array(5) as _, i}
						<span class="review-modal__star" class:filled={i < reviewModalDetail.rating}>★</span>
					{/each}
					<span class="review-modal__date">{formatReviewDate(reviewModalDetail.date)}</span>
				</div>
				<p class="review-modal__content">{reviewModalDetail.content}</p>
			</div>
		</div>
	</div>
{/if}

<style>
	.pdp {
		--pdp-radius: 16px;
		display: grid;
		grid-template-columns: 1.1fr 1fr;
		gap: 64px;
		padding: 56px 28px 48px;
		max-width: 1320px;
		margin: 0 auto;
	}
	@media (max-width: 860px) {
		.pdp {
			grid-template-columns: 1fr;
			gap: 32px;
			padding: 32px 20px 32px;
		}
	}

	.pdp__media {
		display: flex;
		flex-direction: column;
		gap: 12px;
		position: sticky;
		top: 100px;
		align-self: start;
	}
	.pdp__gallery-wrap {
		position: relative;
		border-radius: var(--pdp-radius);
		overflow: hidden;
	}
	.pdp__image-disclaimer {
		position: absolute;
		right: 12px;
		bottom: 12px;
		margin: 0;
		max-width: min(220px, 55%);
		font-size: 9px;
		font-weight: 600;
		letter-spacing: 0.06em;
		text-transform: uppercase;
		text-align: right;
		color: var(--fg-muted);
		line-height: 1.35;
		pointer-events: none;
	}
	@media (max-width: 860px) {
		.pdp__media {
			position: static;
		}
	}
	.pdp__gallery {
		aspect-ratio: 1 / 1;
		background: var(--bg-muted);
		border: 1px solid var(--border);
		border-radius: var(--pdp-radius);
		overflow: hidden;
	}
	.pdp__gallery--placeholder {
		display: flex;
		align-items: center;
		justify-content: center;
		color: var(--fg-faint);
	}
	.pdp__gallery-track {
		display: flex;
		height: 100%;
	}
	.pdp__gallery-slide {
		flex: 0 0 100%;
		min-width: 0;
		padding: 0;
		border: 0;
		background: transparent;
		cursor: zoom-in;
		overflow: hidden;
	}
	.pdp__gallery-slide img {
		width: 100%;
		height: 100%;
		object-fit: cover;
		border-radius: var(--pdp-radius);
		display: block;
		user-select: none;
		-webkit-user-drag: none;
	}
	.pdp__dots {
		display: flex;
		justify-content: center;
		gap: 8px;
	}
	.pdp__dot {
		width: 8px;
		height: 8px;
		padding: 0;
		border: 1px solid var(--fg-muted);
		border-radius: 50%;
		background: transparent;
		cursor: pointer;
		transition: background var(--dur-fast) var(--ease), border-color var(--dur-fast) var(--ease);
	}
	.pdp__dot:hover {
		border-color: var(--fg);
	}
	.pdp__dot--active {
		background: var(--fg);
		border-color: var(--fg);
	}

	/* ── Lightbox ── */
	.lightbox {
		position: fixed;
		inset: 0;
		z-index: 10000;
		display: flex;
		align-items: center;
		justify-content: center;
	}
	.lightbox__backdrop {
		position: absolute;
		inset: 0;
		background: rgba(0, 0, 0, 0.88);
	}
	.lightbox__close {
		position: absolute;
		top: 16px;
		right: 16px;
		z-index: 2;
		width: 44px;
		height: 44px;
		display: flex;
		align-items: center;
		justify-content: center;
		background: transparent;
		border: 1px solid rgba(255, 255, 255, 0.2);
		border-radius: 50%;
		color: #fff;
		cursor: pointer;
		transition: border-color 0.15s, background 0.15s;
	}
	.lightbox__close:hover {
		background: rgba(255, 255, 255, 0.1);
		border-color: rgba(255, 255, 255, 0.5);
	}
	.lightbox__nav {
		position: absolute;
		top: 50%;
		transform: translateY(-50%);
		z-index: 2;
		width: 44px;
		height: 44px;
		display: flex;
		align-items: center;
		justify-content: center;
		background: transparent;
		border: 1px solid rgba(255, 255, 255, 0.2);
		border-radius: 50%;
		color: #fff;
		cursor: pointer;
		transition: border-color 0.15s, background 0.15s;
	}
	.lightbox__nav:hover {
		background: rgba(255, 255, 255, 0.1);
		border-color: rgba(255, 255, 255, 0.5);
	}
	.lightbox__nav--prev { left: 16px; }
	.lightbox__nav--next { right: 16px; }
	.lightbox__image {
		position: relative;
		z-index: 1;
		display: flex;
		align-items: center;
		justify-content: center;
		max-width: calc(100vw - 120px);
		max-height: calc(100vh - 80px);
	}
	.lightbox__image img {
		max-width: 100%;
		max-height: calc(100vh - 80px);
		object-fit: contain;
		border-radius: var(--pdp-radius, 16px);
	}
	.lightbox__counter {
		position: absolute;
		bottom: 16px;
		left: 50%;
		transform: translateX(-50%);
		z-index: 2;
		font-size: 12px;
		font-weight: 500;
		letter-spacing: 0.06em;
		color: rgba(255, 255, 255, 0.6);
	}
	@media (max-width: 860px) {
		.lightbox__nav--prev { left: 8px; }
		.lightbox__nav--next { right: 8px; }
		.lightbox__image {
			max-width: calc(100vw - 32px);
		}
	}

	.loading,
	.err {
		padding: 60px 24px;
		color: var(--fg-muted);
		text-align: center;
		font-size: 14px;
	}

	/* ── Reviews overlay ── */
	.review-overlay {
		position: fixed;
		inset: 0;
		z-index: 9997;
		display: flex;
		align-items: flex-end;
	}
	.review-overlay__backdrop {
		position: absolute;
		inset: 0;
		background: rgba(0, 0, 0, 0.5);
	}
	.review-overlay__panel {
		position: relative;
		z-index: 1;
		width: 100%;
		max-height: 80vh;
		background: var(--bg);
		border-top: 1px solid var(--border);
		display: flex;
		flex-direction: column;
	}
	.review-overlay__header {
		display: flex;
		align-items: flex-start;
		justify-content: space-between;
		padding: 20px 24px 16px;
		border-bottom: 1px solid var(--border);
		flex-shrink: 0;
	}
	.review-overlay__label {
		font-size: 11px;
		font-weight: 500;
		text-transform: uppercase;
		letter-spacing: 0.1em;
		color: var(--fg-muted);
		margin: 0 0 6px;
	}
	.review-overlay__summary {
		display: flex;
		align-items: center;
		gap: 8px;
	}
	.review-overlay__avg {
		font-size: 15px;
		font-weight: 600;
		color: var(--fg);
	}
	.review-overlay__stars {
		font-size: 13px;
		letter-spacing: 1px;
	}
	.review-overlay__star {
		color: var(--border);
	}
	.review-overlay__star.filled {
		color: var(--accent, #ffdd24);
	}
	.review-overlay__count {
		font-size: 12px;
		color: var(--fg-muted);
	}
	.review-overlay__dist {
		display: flex;
		flex-direction: column;
		gap: 4px;
		margin-top: 12px;
		max-width: 240px;
	}
	.review-overlay__dist-row {
		display: flex;
		align-items: center;
		gap: 8px;
	}
	.review-overlay__dist-label {
		font-size: 11px;
		font-weight: 500;
		color: var(--fg-muted);
		width: 24px;
		text-align: right;
		flex-shrink: 0;
	}
	.review-overlay__dist-bar {
		flex: 1;
		height: 6px;
		background: var(--border);
		border-radius: 3px;
		overflow: hidden;
	}
	.review-overlay__dist-fill {
		height: 100%;
		background: var(--accent, #ffdd24);
		border-radius: 3px;
		transition: width 0.3s var(--ease);
	}
	.review-overlay__dist-count {
		font-size: 10px;
		color: var(--fg-muted);
		width: 20px;
		font-variant-numeric: tabular-nums;
	}
	.review-overlay__close {
		width: 36px;
		height: 36px;
		display: flex;
		align-items: center;
		justify-content: center;
		background: transparent;
		border: 1px solid var(--border);
		color: var(--fg-muted);
		cursor: pointer;
		flex-shrink: 0;
		transition: color var(--dur-fast) var(--ease), border-color var(--dur-fast) var(--ease);
	}
	.review-overlay__close:hover {
		color: var(--fg);
		border-color: var(--fg);
	}
	.review-overlay__viewport {
		overflow: hidden;
		padding: 20px 24px 24px;
	}
	.review-overlay__track {
		display: flex;
		gap: 16px;
		cursor: grab;
	}
	.review-overlay__track:active {
		cursor: grabbing;
	}
	.review-overlay__slide {
		flex: 0 0 260px;
		min-height: 280px;
	}
	@media (min-width: 640px) {
		.review-overlay {
			align-items: center;
			justify-content: center;
		}
		.review-overlay__panel {
			max-width: 900px;
			max-height: 70vh;
			margin: 0 24px;
			border: 1px solid var(--border);
		}
		.review-overlay__slide {
			flex: 0 0 280px;
		}
	}

	/* ── Review detail modal ── */
	.review-modal__backdrop {
		position: fixed;
		inset: 0;
		background: rgba(0, 0, 0, 0.6);
		z-index: 9998;
	}
	.review-modal__dialog {
		position: fixed;
		top: 50%;
		left: 50%;
		transform: translate(-50%, -50%);
		z-index: 9999;
		background: var(--bg);
		color: var(--fg);
		border: 1px solid var(--border);
		max-width: 560px;
		width: calc(100% - 32px);
		max-height: 90vh;
		overflow-y: auto;
		font-family: var(--font-sans);
	}
	.review-modal__close {
		position: absolute;
		top: 12px;
		right: 12px;
		z-index: 1;
		background: var(--bg);
		border: 1px solid var(--border);
		width: 32px;
		height: 32px;
		cursor: pointer;
		font-size: 14px;
		color: var(--fg-muted);
		display: flex;
		align-items: center;
		justify-content: center;
	}
	.review-modal__close:hover {
		color: var(--fg);
		border-color: var(--fg);
	}
	.review-modal__images {
		display: flex;
		gap: 2px;
		overflow-x: auto;
	}
	.review-modal__images img {
		width: 100%;
		max-height: 320px;
		object-fit: cover;
		display: block;
	}
	.review-modal__body {
		padding: 20px 24px 24px;
	}
	.review-modal__header {
		display: flex;
		align-items: center;
		gap: 8px;
		margin-bottom: 8px;
	}
	.review-modal__author {
		font-size: 15px;
		font-weight: 600;
	}
	.review-modal__verified {
		font-size: 10px;
		text-transform: uppercase;
		letter-spacing: 0.06em;
		color: var(--success, #059669);
		font-weight: 600;
	}
	.review-modal__stars-row {
		display: flex;
		align-items: center;
		gap: 8px;
		margin-bottom: 16px;
		font-size: 13px;
		letter-spacing: 1px;
	}
	.review-modal__star {
		color: var(--border);
	}
	.review-modal__star.filled {
		color: var(--accent, #ffdd24);
	}
	.review-modal__date {
		font-size: 12px;
		color: var(--fg-muted);
	}
	.review-modal__content {
		font-size: 14px;
		line-height: 1.65;
		color: var(--fg);
		margin: 0;
	}
</style>
