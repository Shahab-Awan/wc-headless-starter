/**
 * Runtime site config — fetched once on boot from /wchs/v1/config.
 *
 * WHY
 *   The SPA bundle is shared across N deployments (werewolfbiologics,
 *   vitanovalabs, etc.). Each site has its own WP origin, its own brand,
 *   its own currency, its own feature flags. Hardcoding any of those
 *   into the bundle breaks the "one build, many sites" model.
 *
 * HOW
 *   On layout mount, we hit /wp/wp-json/wchs/v1/config (same-origin via
 *   Vite proxy in dev, nginx proxy in prod). The endpoint reads from
 *   wp-config.php constants (WCHS_SPA_URL, WCHS_ALLOWED_ORIGINS,
 *   WCHS_BRAND_NAME, etc.) and returns a flat JSON object.
 *
 *   Components read from `config.*` fields via the store. Until the
 *   config has loaded, `ready` is false and consumers should gate.
 */

import { browser } from '$app/environment';
import { resolveModules, siteDefaults } from './resolver';
import { theme } from './theme.svelte';
import { loadFont } from './hero-fonts';
import { isCaptchaChallenge, handleCaptchaChallenge } from './siteground-captcha';
import type { Home1LandingConfig } from './home-1-landing';
import { resolveHome1Landing } from './home-1-landing';
import type { HeroPrecisionConfig } from './hero-precision';
import { HERO_PRECISION_DEFAULTS } from './hero-precision';
import { mergeVaultPages } from './vault-page';

export type HeroTrustItem = {
	icon: string;
	text: string;
};

export type HeroTextSize = 's' | 'm' | 'l' | 'xl';
export type HeroTextWeight = 'light' | 'regular' | 'medium' | 'semibold' | 'bold' | 'extrabold' | 'black';
export type HeroFontKey = 'inter' | 'barlow' | 'bebas' | 'playfair' | 'space_grotesk' | 'archivo' | 'oswald';
export type HeroTextColorMode = 'theme' | 'white' | 'black' | 'accent';

export type HeroResearchStat = { value: string; label: string };

export type HomepageHeroConfig = {
	headline: string;
	content_mode?: 'text' | 'logo';
	logo_source?: 'site_logo' | 'custom';
	logo_url?: string;
	logo_dark_url?: string;
	logo_size?: 'standard' | 'large' | 'statement';
	headline_size?: HeroTextSize;
	headline_weight?: HeroTextWeight;
	headline_font?: HeroFontKey;
	text_color_mode?: HeroTextColorMode;
	subheadline: string;
	subheadline_size?: Extract<HeroTextSize, 's' | 'm' | 'l'>;
	cta_text: string;
	cta_link: string;
	variant:
		| 'text-only'
		| 'research-motion'
		| 'webgl-noise'
		| 'webgl-variant-2'
		| 'webgl-variant-3'
		| 'webgl-variant-4'
		| 'webgl-variant-5'
		| 'webgl-variant-6';
	layout: 'left' | 'center' | 'bottom' | 'split' | 'precision';
	image_desktop: string;
	image_mobile: string;
	image_position_x: number;
	image_position_y: number;
	image_position_mobile_x?: number;
	image_position_mobile_y?: number;
	image_zoom?: number;
	image_zoom_mobile?: number;
	show_eyebrow: boolean;
	show_rating: boolean;
	rating_text: string;
	cta_accent: boolean;
	show_cta: boolean;
	trust_items: HeroTrustItem[];
	research_badge?: string;
	cta_secondary_text?: string;
	cta_secondary_link?: string;
	research_stats?: HeroResearchStat[];
	precision?: HeroPrecisionConfig;
};

/**
 * Hero module config — pared-down subset of HomepageHeroConfig for the
 * reusable Hero module. Dropped fields: show_rating/rating_text, show_eyebrow,
 * mobile image position + zoom (fall back to desktop values), cta_accent
 * (accent override handles this), trust_items (use trust_bar module below).
 */
export type HeroModuleConfig = {
	content_mode?: 'text' | 'logo';
	logo_source?: 'site_logo' | 'custom';
	logo_url?: string;
	logo_dark_url?: string;
	logo_size?: 'standard' | 'large' | 'statement';
	image_desktop?: string;
	image_mobile?: string;
	image_position_x?: number;
	image_position_y?: number;
	image_zoom?: number;
	headline?: string;
	headline_size?: HeroTextSize;
	headline_weight?: HeroTextWeight;
	headline_font?: HeroFontKey;
	subheadline?: string;
	subheadline_size?: Extract<HeroTextSize, 's' | 'm' | 'l'>;
	text_color_mode?: HeroTextColorMode;
	show_cta?: boolean;
	cta_text?: string;
	cta_link?: string;
	layout?: 'left' | 'center' | 'bottom' | 'split' | 'precision';
	variant?:
		| 'text-only'
		| 'research-motion'
		| 'webgl-noise'
		| 'webgl-variant-2'
		| 'webgl-variant-3'
		| 'webgl-variant-4'
		| 'webgl-variant-5'
		| 'webgl-variant-6';
	research_badge?: string;
	cta_secondary_text?: string;
	cta_secondary_link?: string;
	research_stats?: HeroResearchStat[];
};

export type ProductSliderModuleConfig = {
	title: string;
	source: 'all' | 'featured' | 'category' | 'best_sellers' | 'manual';
	category: string | null;
	product_ids: number[];
};

export type ReviewSliderModuleConfig = {
	title: string;
	photos_only?: boolean;
	/**
	 * Products whose reviews populate the slider. If empty, the slider falls
	 * back to a built-in list (or renders nothing if those IDs don't exist
	 * on this site). Set per-deployment from the WCHS admin to your
	 * best-reviewed SKUs.
	 */
	product_ids?: number[];
};

export type AccordionItem = {
	q: string;
	a: string;
};

export type AccordionModuleConfig = {
	title: string;
	items: AccordionItem[];
};

export type SpacingPreset = 'compact' | 'normal' | 'spacious';

export type ModuleOverrides = {
	accent_color?: string;
	typography?: Partial<{
		heading_font: string;
		body_font: string;
		heading_weight: string;
		body_size: string;
	}>;
};

export type ModuleResolved = {
	accent_color: string | null;
	typography: {
		heading_font: string;
		body_font: string;
		heading_weight: string;
		body_size: string;
	};
};

type ModuleBase = {
	/** 8-char stable id assigned by SchemaSanitizer. Persists across reorder
	 * and config edits. Powers data-module-id hooks for analytics. */
	id?: string;
	visibility: 'all' | 'members' | 'guests';
	spacing_v?: SpacingPreset;
	spacing_h?: SpacingPreset;
	center_header?: boolean;
	overrides?: ModuleOverrides;
	resolved?: ModuleResolved;
	inherited?: Record<string, 'default' | 'page' | 'module'>;
	/** ISO-8601 datetime. Module hidden until this moment. */
	start_at?: string;
	/** ISO-8601 datetime. Module hidden after this moment. */
	end_at?: string;
};

/**
 * Client-side schedule filter. Runs per render so changing the device clock
 * instantly re-shows/hides scheduled modules.
 *
 * Preview mode (URL `?preview=1`) bypasses the filter so admins see
 * scheduled+expired modules while editing.
 */
export function isModuleVisibleNow(mod: { start_at?: string; end_at?: string }): boolean {
	if (typeof window !== 'undefined') {
		try {
			const params = new URLSearchParams(window.location.search);
			if (params.get('preview') === '1') return true;
		} catch { /* noop */ }
	}
	const now = Date.now();
	if (mod.start_at) {
		const s = Date.parse(mod.start_at);
		if (Number.isFinite(s) && now < s) return false;
	}
	if (mod.end_at) {
		const e = Date.parse(mod.end_at);
		if (Number.isFinite(e) && now > e) return false;
	}
	return true;
}

/** Set `true` to show homepage BOGO / split_value promo again (WP config is unchanged). */
export const HOMEPAGE_SPLIT_VALUE_ENABLED = false;

export function isHomepageModuleShown(mod: HomepageModule): boolean {
	if (!HOMEPAGE_SPLIT_VALUE_ENABLED && mod.type === 'split_value') return false;
	return true;
}

export type TrustBarItem = {
	icon: string;
	headline: string;
	description: string;
};

export type TrustBarModuleConfig = {
	title: string;
	items: TrustBarItem[];
	icon_accent?: boolean;
};

export type TextBlockComparisonRow = {
	heading: string;
	brand?: string;
	competitor?: string;
	competitor_2?: string;
};

export type ListicleItem = {
	number?: string;
	/** Trust-bar icon key (shipping, lab, shield, …). */
	icon?: string;
	/** Short text fallback when no icon is set (e.g. US). */
	icon_text?: string;
	label?: string;
	headline: string;
	body?: string;
	callout?: string;
	/** Pill tags under the body copy. Empty strings render dot-only placeholders. */
	badges?: string[];
	image?: string;
	image_alt?: string;
};

export type PromoOfferModuleConfig = {
	intro_headline?: string;
	intro_subheadline?: string;
	badge_text?: string;
	image?: string;
	image_alt?: string;
	ribbon_text?: string;
	offer_primary?: string;
	offer_secondary?: string;
	scarcity_text?: string;
	cta_label?: string;
	cta_href?: string;
	show_countdown?: boolean;
	countdown_end_at?: string;
	status_label?: string;
	status_value?: string;
	status_note?: string;
	footer_text?: string;
};

export type ListicleFaqsItem = {
	q?: string;
	a?: string;
};

export type ListicleFaqsModuleConfig = {
	eyebrow?: string;
	headline?: string;
	/** @deprecated Use headline — kept for saved configs before merge. */
	headline_prefix?: string;
	/** @deprecated Use headline — kept for saved configs before merge. */
	headline_accent?: string;
	items?: ListicleFaqsItem[];
};

export type ReviewsListicleItem = {
	title?: string;
	quote?: string;
	name?: string;
	location?: string;
	product?: string;
	rating?: number;
};

export type ReviewsListicleModuleConfig = {
	headline?: string;
	subheadline?: string;
	proof_headline?: string;
	proof_subheadline?: string;
	items?: ReviewsListicleItem[];
	proof_items?: ReviewsListicleItem[];
	marquee_headline?: string;
	marquee_items?: ReviewsListicleItem[];
};

export type ListicleModuleConfig = {
	section_eyebrow?: string;
	/** split = image + copy columns; editorial = headline, trust bar, callout stack. */
	hero_layout?: 'split' | 'editorial';
	/** Optional full-section background image behind the listicle hero. */
	bg_image?: string;
	/** modern = gradient + floating vials; photo = bg_image only (no gradient/vials). */
	hero_backdrop?: 'modern' | 'photo';
	vial_primary?: string;
	vial_primary_alt?: string;
	vial_secondary?: string;
	vial_secondary_alt?: string;
	vial_tertiary?: string;
	vial_tertiary_alt?: string;
	headline?: string;
	hero_image?: string;
	hero_image_alt?: string;
	trust_brand?: string;
	trust_items?: string[];
	hero_callout?: string;
	hero_trust_lead?: string;
	hero_cta_image?: string;
	hero_cta_image_alt?: string;
	hero_cta_headline?: string;
	hero_cta_badge?: string;
	hero_cta_ribbon?: string;
	hero_cta_scarcity?: string;
	hero_cta_label?: string;
	hero_cta_href?: string;
	intro?: string;
	items_headline?: string;
	closing?: string;
	items?: ListicleItem[];
	cta_label?: string;
	cta_href?: string;
	/** Reason #4 — COA thumbnail + library link. */
	coa_embed_image?: string;
	coa_embed_image_alt?: string;
	coa_embed_href?: string;
	coa_embed_link_label?: string;
};

export type TextBlockModuleConfig = {
	layout?: 'auto' | 'standard' | 'comparison';
	title: string;
	headline?: string;
	content: string;
	brand_name?: string;
	competitor_name?: string;
	competitor_name_2?: string;
	brand_logo?: string;
	competitor_logo?: string;
	comparison_rows?: TextBlockComparisonRow[];
};

export type GalleryItem = {
	src: string;
	title?: string;
	description?: string;
};

export type GalleryModuleConfig = {
	title: string;
	columns: number;
	gap: number;
	aspect_ratio: string;
	items: GalleryItem[];
};

export type CategoryGridItem = {
	category_id: number;
	image?: string;
};

export type CategoryGridModuleConfig = {
	title: string;
	columns: number;
	gap: number;
	items: CategoryGridItem[];
	/** Suffix for category counts — e.g. “compounds” instead of “products”. */
	count_label?: string;
};

export type SplitFeatureItem = {
	eyebrow: string;
	heading: string;
	description: string;
	image: string;
};

export type SplitFeaturesModuleConfig = {
	layout?: 'alternating' | 'comparison';
	headline?: string;
	subtitle?: string;
	brand_name?: string;
	competitor_name?: string;
	brand_logo?: string;
	competitor_logo?: string;
	title: string;
	items: SplitFeatureItem[];
};

export type SplitValueBullet = { text: string };
export type SplitValueStat = { value: string; label: string };

export type SplitValueModuleConfig = {
	rating_line: string;
	headline_prefix: string;
	headline_accent: string;
	accent_underline: boolean;
	bullets: SplitValueBullet[];
	cta_label: string;
	cta_href: string;
	trust_note: string;
	promo_badge_eyebrow: string;
	promo_badge_title: string;
	image: string;
	image_alt: string;
	stats: SplitValueStat[];
};

export type FeatureHighlightItem = {
	variant: string;
	headline: string;
	description: string;
};

export type FeatureHighlightsModuleConfig = {
	badge_text: string;
	headline_prefix: string;
	headline_accent: string;
	subheadline: string;
	items: FeatureHighlightItem[];
	cta_label: string;
	cta_href: string;
};

export type PriceComparisonBullet = {
	variant: string;
	headline: string;
	description: string;
};

export type PriceComparisonCompetitor = {
	letter: string;
	name: string;
	price: string;
};

export type PriceComparisonSheet = {
	tab_label: string;
	product_label: string;
	brand_price: string;
	brand_tags: string;
	competitors: PriceComparisonCompetitor[];
};

export type PriceComparisonModuleConfig = {
	headline: string;
	body: string;
	bullets: PriceComparisonBullet[];
	cta_label: string;
	cta_href: string;
	status_label: string;
	lowest_badge: string;
	brand_name: string;
	footnote: string;
	sheets?: PriceComparisonSheet[];
	/** @deprecated Use sheets[].product_label */
	product_label?: string;
	/** @deprecated Use sheets[].brand_price */
	brand_price?: string;
	/** @deprecated Use sheets[].brand_tags */
	brand_tags?: string;
	/** @deprecated Use sheets[].competitors */
	competitors?: PriceComparisonCompetitor[];
};

/** Fallback when homepage has no price_comparison module yet. */
export const PRICE_COMPARISON_CARD_DEFAULTS: PriceComparisonModuleConfig = {
	headline: 'Priced Below The Market, Guaranteed.',
	body: '',
	bullets: [],
	cta_label: '',
	cta_href: '/shop',
	status_label: 'LIVE PRICE COMPARISON',
	lowest_badge: 'LOWEST',
	brand_name: '',
	footnote:
		'Prices tracked from publicly listed research peptide vendors for comparable SKU, dose, and purity tier. Updated regularly; for research use only.',
	sheets: [
		{
			tab_label: 'GLP Reta',
			product_label: 'GLP Reta 10 MG',
			brand_price: '89.00',
			brand_tags: 'IN STOCK · SHIPS FAST · COA ON FILE',
			competitors: [
				{ letter: 'A', name: 'Modern Aminos', price: '109.00' },
				{ letter: 'B', name: 'Soma Chems', price: '119.00' },
				{ letter: 'C', name: 'Onyx Research', price: '125.00' },
				{ letter: 'D', name: 'Ascension Peptides', price: '135.00' },
			],
		},
		{
			tab_label: 'BPC-157',
			product_label: 'BPC-157 5MG',
			brand_price: '28.00',
			brand_tags: 'IN STOCK · SHIPS FAST · COA ON FILE',
			competitors: [
				{ letter: 'A', name: 'Modern Aminos', price: '34.00' },
				{ letter: 'B', name: 'Soma Chems', price: '39.99' },
				{ letter: 'C', name: 'Onyx Research', price: '45.00' },
				{ letter: 'D', name: 'Ascension Peptides', price: '55.00' },
			],
		},
	],
};

export type OrderHandlingStep = {
	variant: string;
	icon_url?: string;
	headline: string;
	description: string;
};

export type OrderHandlingMetric = { value: string; label: string };

export type OrderHandlingModuleConfig = {
	badge_text: string;
	headline: string;
	subheadline: string;
	bg_color?: string;
	steps: OrderHandlingStep[];
	metrics_title: string;
	metrics: OrderHandlingMetric[];
};

export type ShopGridModuleConfig = {
	title: string;
	category?: string;
};

export type ContactFormField = {
	name: string;
	label: string;
	type: 'text' | 'email' | 'textarea';
	required: boolean;
};

export type ContactFormModuleConfig = {
	title: string;
	recipient_email: string;
	subject_prefix: string;
	success_message: string;
	fields: ContactFormField[];
};

export type CTAModuleConfig = {
	label: string;
	href: string;
	style: 'primary' | 'ghost' | 'text';
	size: 'sm' | 'md' | 'lg';
	align: 'left' | 'center' | 'right';
	open_new_tab: boolean;
};

export type SpacerModuleConfig = {
	height: number;
};

export type LogoStripItem = {
	src: string;
	alt?: string;
	link_url?: string;
};

export type LogoStripModuleConfig = {
	title?: string;
	grayscale: boolean;
	items: LogoStripItem[];
};

export type VideoModuleConfig = {
	title?: string;
	source_url: string;
	poster_url?: string;
	aspect_ratio: '16/9' | '4/3' | '1/1' | '9/16';
	autoplay: boolean;
	muted: boolean;
	loop: boolean;
	controls: boolean;
};

export type VaultHeroModuleConfig = {
	headline?: string;
	stats?: { label: string }[];
	cta_text?: string;
	cta_href?: string;
	bg_image?: string;
	vial_primary?: string;
	vial_primary_alt?: string;
	vial_secondary?: string;
	vial_secondary_alt?: string;
	vial_tertiary?: string;
	vial_tertiary_alt?: string;
};

export type VaultQualityTab = {
	title?: string;
	summary?: string;
	body?: string;
	why_matters?: string;
	chart_image?: string;
};

export type VaultQualityStat = {
	value?: string;
	label?: string;
};

export type VaultGuaranteeCard = {
	title?: string;
	description?: string;
	tooltip?: string;
	accent?: 'green' | 'blue' | 'yellow' | string;
	icon?: 'purity' | 'shipping' | 'coa' | string;
};

export type VaultQualityTabsModuleConfig = {
	section_title?: string;
	section_subtitle?: string;
	product_image?: string;
	product_image_alt?: string;
	image_badge?: string;
	panel_bg?: string;
	guarantee_cards?: VaultGuaranteeCard[];
};

export type VaultQualityVerifyModuleConfig = {
	section_title?: string;
	section_subtitle?: string;
	product_image?: string;
	product_image_alt?: string;
	purity_badge_title?: string;
	purity_badge_subtitle?: string;
	panel_bg?: string;
	proof_link_title?: string;
	proof_link_subtitle?: string;
	proof_link_href?: string;
	shop_cta_text?: string;
	shop_cta_href?: string;
	trust_note?: string;
	stats?: VaultQualityStat[];
	tabs?: VaultQualityTab[];
};

export type VaultWhyChooseItem = {
	title?: string;
	description?: string;
	icon?: 'stock' | 'volume' | 'shipping' | 'verified' | 'coa' | 'fulfillment' | string;
	accent?: 'violet' | 'green' | 'amber' | 'rose' | 'blue' | 'teal' | string;
};

export type VaultWhyChooseModuleConfig = {
	section_title?: string;
	items?: VaultWhyChooseItem[];
};

export type VaultCtaModuleConfig = {
	headline_prefix?: string;
	headline_accent?: string;
	primary_cta_text?: string;
	primary_cta_href?: string;
	secondary_cta_text?: string;
	secondary_cta_href?: string;
};

export type CuratedFeaturedProductRef = {
	slug: string;
	/** Card title override — WooCommerce product name unchanged. */
	display_name?: string;
};

export type FeaturedProductsModuleConfig = {
	eyebrow?: string;
	headline_prefix?: string;
	headline_accent?: string;
	subheadline?: string;
	product_badge?: string;
	/** When false, no highlight badge (e.g. “Most Popular”) on cards. */
	show_product_badge?: boolean;
	/** When true, hide dose pills on product cards in this module. */
	hide_dose_pill?: boolean;
	select_cta_label?: string;
	source?: 'popular' | 'best_sellers' | 'curated';
	/** Fixed slug order with optional display names (e.g. Google Merchant landing). */
	curated_products?: CuratedFeaturedProductRef[];
	product_limit?: number;
	cta_text?: string;
	cta_href?: string;
};

export type HomepageModule =
	| (ModuleBase & { type: 'product_slider'; config: ProductSliderModuleConfig })
	| (ModuleBase & { type: 'review_slider'; config: ReviewSliderModuleConfig })
	| (ModuleBase & { type: 'featured_products'; config: FeaturedProductsModuleConfig })
	| (ModuleBase & { type: 'accordion'; config: AccordionModuleConfig })
	| (ModuleBase & { type: 'trust_bar'; config: TrustBarModuleConfig })
	| (ModuleBase & { type: 'text_block'; config: TextBlockModuleConfig })
	| (ModuleBase & { type: 'listicle'; config: ListicleModuleConfig })
	| (ModuleBase & { type: 'promo_offer'; config: PromoOfferModuleConfig })
	| (ModuleBase & { type: 'reviews_listicle'; config: ReviewsListicleModuleConfig })
	| (ModuleBase & { type: 'listicle_faqs'; config: ListicleFaqsModuleConfig })
	| (ModuleBase & { type: 'gallery'; config: GalleryModuleConfig })
	| (ModuleBase & { type: 'category_grid'; config: CategoryGridModuleConfig })
	| (ModuleBase & { type: 'split_features'; config: SplitFeaturesModuleConfig })
	| (ModuleBase & { type: 'split_value'; config: SplitValueModuleConfig })
	| (ModuleBase & { type: 'price_comparison'; config: PriceComparisonModuleConfig })
	| (ModuleBase & { type: 'feature_highlights'; config: FeatureHighlightsModuleConfig })
	| (ModuleBase & { type: 'order_handling'; config: OrderHandlingModuleConfig })
	| (ModuleBase & { type: 'shop_grid'; config: ShopGridModuleConfig })
	| (ModuleBase & { type: 'contact_form'; config: ContactFormModuleConfig })
	| (ModuleBase & { type: 'hero'; config: HeroModuleConfig })
	| (ModuleBase & { type: 'cta'; config: CTAModuleConfig })
	| (ModuleBase & { type: 'spacer'; config: SpacerModuleConfig })
	| (ModuleBase & { type: 'logo_strip'; config: LogoStripModuleConfig })
	| (ModuleBase & { type: 'video'; config: VideoModuleConfig })
	| (ModuleBase & { type: 'vault_hero'; config: VaultHeroModuleConfig })
	| (ModuleBase & { type: 'vault_quality_tabs'; config: VaultQualityTabsModuleConfig })
	| (ModuleBase & { type: 'vault_quality_verify'; config: VaultQualityVerifyModuleConfig })
	| (ModuleBase & { type: 'vault_why_choose'; config: VaultWhyChooseModuleConfig })
	| (ModuleBase & { type: 'vault_cta'; config: VaultCtaModuleConfig });

export type HomepageConfig = {
	hero: HomepageHeroConfig;
	modules: HomepageModule[];
};

export type PdpFeatureItem = { icon: string; label: string };
export type PdpTrustBadge = { icon: string; label: string };
export type PdpCoaMetric = { label: string; value: string };

export type PdpBogoBundleConfig = {
	enabled?: boolean;
	savings_pct?: number;
	presets?: Array<{
		paid_qty: number;
		free_qty?: number;
		discount_pct?: number;
		flag?: string;
		pdp_hidden?: boolean;
	}>;
};

export type PdpCoaSectionConfig = {
	enabled?: boolean;
	eyebrow?: string;
	title?: string;
	subtitle?: string;
	disclaimer?: string;
	/** Site-wide COA preview image for the PDP COA tab card. */
	thumbnail?: string;
	thumbnail_alt?: string;
	default_batch?: string;
	default_lab?: string;
	default_metrics?: PdpCoaMetric[];
};

export type PdpCrossSellConfig = {
	eyebrow?: string;
	title?: string;
	subtitle?: string;
	view_all_url?: string;
};

export type ShippingProtectionTierConfig = {
	up_to: number | null;
	/** Fee in minor units (cents). */
	fee: number;
};

export type SlideCartSocialProofConfig = {
	enabled?: boolean;
	count_min?: number;
	count_max?: number;
	suffix?: string;
	live_label?: string;
	avatars?: string[];
};

export type SlideCartRewardsConfig = {
	enabled?: boolean;
	bac_water_threshold?: number;
	urgency_label?: string;
};

export type SlideCartConfig = {
	cross_sell_exclude_product_ids?: number[];
	cross_sell_exclude_slugs?: string[];
	/** Hidden product for slide-cart shipping protection addon (from WP slug). */
	shipping_protection_product_id?: number;
	shipping_protection_tiers?: ShippingProtectionTierConfig[];
	/** BAC water ancillary product (slide-cart reconstitution prompt). */
	bac_water_product_id?: number;
	social_proof?: SlideCartSocialProofConfig;
	rewards?: SlideCartRewardsConfig;
};

export const CART_CROSS_SELL_DEFAULT_EXCLUDE_SLUGS = ['bac-water-10ml', 'shipping-protection'] as const;
export const SHIPPING_PROTECTION_SLUG = 'shipping-protection';
export const BAC_WATER_SLUG = 'bac-water-10ml';
export const CART_CROSS_SELL_TARGET_COUNT = 4;

export function cartCrossSellExcludeSlugs(): string[] {
	const fromConfig = config.data.pdp?.slide_cart?.cross_sell_exclude_slugs ?? [];
	return [...new Set([...CART_CROSS_SELL_DEFAULT_EXCLUDE_SLUGS, ...fromConfig])];
}

export function cartCrossSellExcludeProductIds(): number[] {
	const fromConfig = config.data.pdp?.slide_cart?.cross_sell_exclude_product_ids ?? [];
	const bacId = config.data.pdp?.slide_cart?.bac_water_product_id ?? 0;
	const extras = bacId > 0 ? [bacId] : [];
	return [...new Set([...fromConfig, ...extras])];
}

/** Shipping protection only — hidden from shop/catalog API lists, not BAC water. */
export function isCatalogHiddenSlug(slug: string): boolean {
	const s = slug.trim().toLowerCase();
	if (!s) return false;
	if (s === SHIPPING_PROTECTION_SLUG || s.startsWith(`${SHIPPING_PROTECTION_SLUG}-`)) return true;
	if (/shipping[-_]?protection|protected[-_]?shipping/.test(s)) return true;
	return false;
}

export function isCatalogHiddenProduct(id: number, slug = ''): boolean {
	const protectId = config.data.pdp?.slide_cart?.shipping_protection_product_id;
	if (protectId && id === protectId) return true;
	if (slug) return isCatalogHiddenSlug(slug);
	return false;
}

export function isCartCrossSellBlockedSlug(slug: string): boolean {
	const s = slug.trim().toLowerCase();
	if (!s) return false;
	for (const raw of cartCrossSellExcludeSlugs()) {
		const x = raw.trim().toLowerCase();
		if (!x) continue;
		if (s === x || s.startsWith(`${x}-`)) return true;
	}
	if (/bac[-_]?water|bacteriostatic[-_]?water/.test(s)) return true;
	if (/shipping[-_]?protection|protected[-_]?shipping/.test(s)) return true;
	return false;
}

export function isCartCrossSellBlockedProduct(id: number, slug = ''): boolean {
	if (cartCrossSellExcludeProductIds().includes(id)) return true;
	if (slug) return isCartCrossSellBlockedSlug(slug);
	return false;
}

export function isBacWaterProduct(id: number, slug = ''): boolean {
	const bacId = config.data.pdp?.slide_cart?.bac_water_product_id;
	if (bacId && id === bacId) return true;
	const s = slug.trim().toLowerCase();
	if (!s) return false;
	if (s === BAC_WATER_SLUG || s.startsWith(`${BAC_WATER_SLUG}-`)) return true;
	return /bac[-_]?water|bacteriostatic[-_]?water/.test(s);
}

export type PdpConfig = {
	show_reviews: boolean;
	cross_sell_mode: 'simple' | 'complex';
	modules: HomepageModule[];
	coa_library_url?: string;
	slide_cart?: SlideCartConfig;
	cross_sell?: PdpCrossSellConfig;
	bundle_bogo?: PdpBogoBundleConfig;
	coa_section?: PdpCoaSectionConfig;
	verified_label?: string;
	show_ships_banner?: boolean;
	show_payment_icons?: boolean;
	image_disclaimer?: string;
	features?: PdpFeatureItem[];
	trust_badges?: PdpTrustBadge[];
};

export type CustomPage = {
	slug: string;
	title: string;
	modules: HomepageModule[];
};

export type GateModalConfig = {
	enabled: boolean;
	strict: boolean;
	title: string;
	content: string;
	confirm_text: string;
	decline_text: string;
	decline_url: string;
	version: number;
};

export type FooterLink = { label: string; url: string };
export type FooterColumn = { title: string; links: FooterLink[] };

export type HeaderLink = {
	label: string;
	url: string;
	display: 'text' | 'icon' | 'both';
	icon?: string;
	accent: boolean;
	/**
	 * When true, this link renders inline on mobile next to the logo
	 * (up to 3 pinned items total). When false, it falls into the
	 * hamburger drawer. Ignored if mobile_hamburger_side is 'off'.
	 */
	mobile_pin?: boolean;
};

export type SiteConfig = {
	wp_origin: string;
	spa_origin: string;
	/** Path on wp_origin for SPA cart JWT handoff (e.g. /checkout or /checkouts/alyve). */
	checkout_handoff_path?: string;
	/** When false, checkout uses FunnelKit path from checkout_handoff_path. */
	use_wchs_checkout?: boolean;
	/** FunnelKit Cart drawer on SPA (replaces SlideCart when enabled). */
	funnelkit_cart?: {
		/** Admin toggle — user asked for FunnelKit cart. */
		requested?: boolean;
		/** Runtime: requested + FunnelKit Cart plugin active. */
		enabled: boolean;
		shell_url: string;
		sync_url: string;
		open_class: string;
		cart_selector: string;
		plugin_active?: boolean;
	};
	brand_name: string;
	static_seo_title: string;
	static_seo_description: string;
	static_seo_image_url: string | null;
	favicon_url: string | null;
	logo_url: string | null;
	logo_dark_url: string | null;
	logo_full_url: string | null;
	logo_dark_full_url: string | null;
	currency_code: string;
	currency_symbol: string;
	shipping_free_threshold: number;
	features: {
		guest_checkout: boolean;
		dark_mode: boolean;
		pretext: boolean;
	};
	version: string;
	access_mode: number;
	accent_color: string | null;
	accent_fg: string | null;
	gtm_id: string;
	ga4_measurement_id: string;
	omnisend_brand_id: string;
	klaviyo_public_key: string;
	meta_pixel_id: string;
	tiktok_pixel_id: string;
	pinterest_tag_id: string;
	clarity_project_id: string;
	hotjar_site_id: string;
	google_ads_conversion_id: string;
	google_ads_conversion_label: string;
	review_write_enabled: boolean;
	turnstile_site_key: string;
	announcement_bar_enabled: boolean;
	announcement_bar_items: string[];
	header_links: HeaderLink[];
	header_toggle_accent: boolean;
	header_cart_accent: boolean;
	header_inverted: boolean;
	header_borderless: boolean;
	/** 'left' | 'right' | 'off'. 'off' keeps the current no-hamburger behavior. */
	mobile_hamburger_side: 'left' | 'right' | 'off';
	/** When false, theme toggle doesn't render anywhere (desktop, mobile inline, or drawer). */
	header_show_toggle: boolean;
	/** When true, pin theme toggle inline on mobile. Otherwise it goes into the drawer. */
	header_toggle_mobile_pin: boolean;
	/** When true, pin cart inline on mobile (default). Otherwise cart goes into the drawer. */
	header_cart_mobile_pin: boolean;
	/** First-load theme default — overridden by any explicit user toggle (persisted). */
	theme_default: 'system' | 'light' | 'dark';
	/** Auto-invert the header logo via CSS filter when data-theme='dark'. */
	logo_invert_on_dark: boolean;
	/** Desktop logo height preset. Mobile stays constrained regardless. */
	logo_size: 'compact' | 'standard' | 'prominent' | 'xl';
	/** Desktop brand / logo position. Mobile is always centered. */
	brand_position: 'left' | 'center' | 'nav-center';
	/** Global typography settings from Appearance tab. */
	typography: {
		heading_font: string;
		body_font: string;
		heading_weight: string;
		body_size: 's' | 'm' | 'l';
	};
	seo_nosnippet_products: boolean;
	homepage: HomepageConfig;
	/** Google Ads / B2B subdomain landing (`/home-1`). */
	home_1?: Home1LandingConfig | null;
	pdp: PdpConfig;
	shop: {
		modules: HomepageModule[];
		cols_min: number;
		cols_max: number;
		spacing_h?: SpacingPreset;
	};
	pages: CustomPage[];
	footer: { columns: FooterColumn[]; tagline?: string };
	social_links: Array<{ platform: string; url: string }>;
	product_card: ProductCardConfig;
	tokens: DesignTokens;
	gate_modal: GateModalConfig;
	active_scripts: ActiveScript[];
};

export type DesignTokens = {
	radius: number | null;
	spacing_v_compact: number | null;
	spacing_v_normal: number | null;
	spacing_v_spacious: number | null;
};

export type ProductCardConfig = {
	media_aspect_ratio: '1:1' | '4:5' | '3:4' | '16:9';
	corner_radius: 'square' | 'soft' | 'round' | 'pill';
	border: 'full' | 'bottom-only' | 'none' | 'hover-only';
	hover_effect: 'lift' | 'shadow' | 'border' | 'none';
	button_style: 'outline' | 'solid' | 'icon-only';
	badge_position: 'top-left' | 'top-right';
	badge_style: 'filled' | 'outline' | 'minimal';
	show_bulk_badge: boolean;
	show_tier_hint: boolean;
	show_oos_cards: boolean;
	oos_treatment: 'grayscale' | 'dim' | 'hidden-price';
	title_lines: 'auto' | '1' | '2' | '3';
	secondary_image_on_hover: boolean;
	sale_badge_text: string;
};

/**
 * A resolved, server-assembled script entry from the admin-curated
 * registry filtered by per-site toggles. The SPA renders these as
 * `<script>` elements in the head (or body) based on `placement`,
 * keyed by `data-wchs-id="{id}"` for idempotency. See the
 * `active_scripts` $effect in +layout.svelte.
 */
export type ActiveScript = {
	id: string;
	name: string;
	src: string;
	async: boolean;
	defer: boolean;
	placement: 'head' | 'body_end';
	surfaces: Array<'spa' | 'wp'>;
	/** Admin-curated registry JS; runs before `src` when both are set. */
	inline?: string;
};

const DEFAULTS: SiteConfig = {
	wp_origin: 'http://localhost:8099',
	checkout_handoff_path: '/checkouts/checkout-page',
	use_wchs_checkout: false,
	funnelkit_cart: {
		requested: false,
		enabled: false,
		shell_url: '',
		sync_url: '',
		open_class: 'fkcart-mini-open',
		cart_selector: '.site-header__cart'
	},
	spa_origin: 'http://localhost:5175',
	brand_name: 'Online Store',
	static_seo_title: 'Alyve Peptides',
	static_seo_description:
		'Shop research-grade peptides with third-party COAs, verified purity, and fast US fulfillment. For in-vitro laboratory research only.',
	static_seo_image_url: null,
	favicon_url: null,
	logo_url: null,
	logo_dark_url: null,
	logo_full_url: null,
	logo_dark_full_url: null,
	currency_code: 'USD',
	currency_symbol: '$',
	shipping_free_threshold: 0,
	features: { guest_checkout: true, dark_mode: false, pretext: true },
	version: '0.1.0',
	access_mode: 3,
	accent_color: null,
	accent_fg: null,
	gtm_id: 'GTM-W8WNMKJ5',
	ga4_measurement_id: '',
	omnisend_brand_id: '',
	klaviyo_public_key: '',
	meta_pixel_id: '',
	tiktok_pixel_id: '',
	pinterest_tag_id: '',
	clarity_project_id: '',
	hotjar_site_id: '',
	google_ads_conversion_id: '',
	google_ads_conversion_label: '',
	homepage: {
		hero: {
			headline: 'A leading grade provider of research peptides.',
			content_mode: 'text',
			logo_source: 'site_logo',
			logo_url: '',
			logo_dark_url: '',
			logo_size: 'large',
			headline_size: 'l',
			headline_weight: 'medium',
			headline_font: 'inter',
			text_color_mode: 'white',
			subheadline:
				'Independently verified. Third-party tested. Every batch held to the highest standard.',
			subheadline_size: 'm',
			cta_text: 'Shop All Peptides',
			cta_link: '/shop',
			cta_secondary_text: 'View COA Library',
			cta_secondary_link: '/coa-library',
			research_badge: '• RESEARCH USE ONLY',
			research_stats: [
				{ value: '≥99%', label: 'VERIFIED PURITY' },
				{ value: '6-panel', label: 'COA EVERY BATCH' },
				{ value: '60+', label: 'RESEARCH COMPOUNDS' },
			],
			variant: 'webgl-variant-6',
			layout: 'precision',
			precision: { ...HERO_PRECISION_DEFAULTS },
			show_eyebrow: true,
			image_desktop: '',
			image_mobile: '',
			image_position_x: 50,
			image_position_y: 50,
			image_position_mobile_x: 50,
			image_position_mobile_y: 80,
			image_zoom: 100,
			image_zoom_mobile: 100,
			cta_accent: true,
			show_cta: true,
			show_rating: false,
			rating_text: '',
			trust_items: [],
		},
		modules: [
			{
				type: 'trust_bar',
				visibility: 'all',
				spacing_v: 'compact',
				spacing_h: 'normal',
				config: {
					title: '',
					icon_accent: true,
					items: [
						{
							icon: 'percent',
							headline: 'Price Below Market',
							description: 'Research-grade peptides at verified pricing — no grey-market markups.',
						},
						{
							icon: 'lab',
							headline: '1 Vial · 3 Tests',
							description: 'Purity, identity, and endotoxin testing on every batch before release.',
						},
						{
							icon: 'shield',
							headline: 'COA Before Purchase',
							description: 'Full Certificate of Analysis published for every batch before you order.',
						},
						{
							icon: 'shipping',
							headline: 'Same-Day US Fulfillment',
							description: 'Orders placed before 2PM EST ship same day via tracked domestic carrier.',
						},
					],
				},
			},
			{
				type: 'split_value',
				visibility: 'all',
				spacing_v: 'normal',
				spacing_h: 'normal',
				config: {
					rating_line: 'Rated 4.98/5 · 24,987+ reviews',
					headline_prefix: 'A Leading Provider of Research Grade',
					headline_accent: 'Peptides.',
					accent_underline: true,
					bullets: [
						{ text: 'Fast U.S. Shipping' },
						{ text: '99% Tested Purity' },
						{ text: 'Made in USA' },
					],
					cta_label: 'Buy 1 Get 1 Free',
					cta_href: '/shop',
					trust_note: 'Research use only. All major credit/debit cards, PayPal, ACH, BTC, Zelle.',
					promo_badge_eyebrow: 'LIMITED TIME',
					promo_badge_title: 'Buy 1 Get 1 Free',
					image: '/wp-content/uploads/2026/05/e33abf7d-1bcf-42ea-b324-c777cec4006d.webp',
					image_alt: 'Research-grade peptides — product lineup',
					stats: [
						{ value: '99%', label: 'Purity' },
						{ value: '24.9K+', label: 'Reviews' },
						{ value: 'Triple-Tested', label: 'for Quality' },
					],
				},
			},
			{
				type: 'featured_products',
				visibility: 'all',
				spacing_v: 'normal',
				spacing_h: 'normal',
				config: {
					eyebrow: 'Bestsellers',
					headline_prefix: 'Featured',
					headline_accent: 'Products',
					subheadline:
						'Explore our most popular research compounds, chosen for their quality, purity, and consistency.',
					product_badge: 'Most Popular',
					source: 'popular',
					product_limit: 3,
					cta_text: 'Explore All Products',
					cta_href: '/shop',
				},
			},
			{
				type: 'reviews_listicle',
				visibility: 'all',
				spacing_v: 'normal',
				spacing_h: 'normal',
				center_header: true,
				config: {
					headline: 'What researchers say after ordering',
					proof_subheadline: '4.9 stars · 200+ verified orders.',
					items: [
						{
							quote:
								'COAs matched the batch numbers on our BPC-157 vials. Documentation was clear and easy to file for our lab records.',
							name: 'Vincent R.',
							product: 'BPC-157 5mg',
							rating: 5,
						},
						{
							quote:
								'TB-500 batch purity matched the published COA exactly. Reconstitution notes were clear and shipment arrived tracked within two days.',
							name: 'James T.',
							product: 'TB-500 5mg',
							rating: 5,
						},
						{
							quote:
								'Tirzepatide purity report was posted before checkout — exactly what our QC process requires.',
							name: 'Justin F.',
							product: 'Tirzepatide 10mg',
							rating: 5,
						},
						{
							quote:
								'Ipamorelin vials arrived cold-packed with batch COA attached. Purity matched the published report on the first HPLC rerun.',
							name: 'Sarah M.',
							product: 'Ipamorelin 2mg',
							rating: 5,
						},
						{
							quote:
								'Consistent Retatrutide quality across reorders — no surprises between batches. Support answered technical questions the same day.',
							name: 'Carlos B.',
							product: 'Retatrutide 5mg',
							rating: 5,
						},
					],
				},
			},
			{
				type: 'listicle_faqs',
				visibility: 'all',
				spacing_v: 'normal',
				spacing_h: 'normal',
				config: {
					eyebrow: 'PRODUCT QUESTIONS',
					headline: 'FAQs by compound',
					items: [
						{
							q: 'What purity should I expect from BPC-157 batches?',
							a: '<p>Every BPC-157 batch is third-party tested for identity and purity via HPLC. Published COAs list the exact percentage for the batch tied to your vial — typically ≥99% on recent lots.</p>',
						},
						{
							q: 'Is the BPC-157 COA available before I order?',
							a: '<p>Yes. Batch-specific Certificates of Analysis are posted on the product page and in our COA library before checkout, so your team can qualify material against protocol requirements in advance.</p>',
						},
						{
							q: 'How is TB-500 tested before release?',
							a: '<p>TB-500 undergoes independent laboratory testing for purity, identity, and endotoxin. Results are tied to a batch number printed on each vial and documented on the COA shipped with your order.</p>',
						},
						{
							q: 'Can I match TB-500 batch numbers to the published COA?',
							a: '<p>Every TB-500 vial label matches the batch identifier on its COA. Search by product name or batch number in the COA library to pull the exact report for the lot you received.</p>',
						},
						{
							q: 'What documentation comes with Ipamorelin orders?',
							a: '<p>Ipamorelin shipments include batch-linked COA PDFs with HPLC purity, identity confirmation, and storage guidance. Research-use labeling and batch traceability are included for lab filing.</p>',
						},
					],
				},
			},
			{
				type: 'order_handling',
				visibility: 'all',
				spacing_v: 'normal',
				spacing_h: 'normal',
				center_header: true,
				config: {
					badge_text: 'Our Process',
					headline: 'How Every Order Is Handled',
					subheadline:
						'From verification to delivery, we ensure each step meets our highest standards.',
					bg_color: '',
					steps: [
						{
							variant: 'verified',
							headline: 'Verified Batches',
							description:
								'Every batch undergoes rigorous quality control and verification before release.',
						},
						{
							variant: 'lab',
							headline: '3rd Party Testing',
							description:
								'Independent laboratory testing ensures purity and consistency you can trust.',
						},
						{
							variant: 'shipping',
							headline: 'Ships Same Day',
							description:
								'Discreetly packaged and dispatched within 24 hours from our U.S. facility.',
						},
						{
							variant: 'support',
							headline: '24/7 Support',
							description:
								'Round-the-clock customer service for any questions before or after your order.',
						},
					],
					metrics_title: 'Quality Metrics',
					metrics: [
						{ value: '99.8%', label: 'Batch Accuracy' },
						{ value: '100%', label: 'Verified Testing' },
						{ value: '24/7', label: 'Support Response' },
					],
				},
			},
		],
	},
	review_write_enabled: true,
	turnstile_site_key: '',
	announcement_bar_enabled: true,
	announcement_bar_items: [
		'Fast & Discreet Shipping',
		'Third-Party Tested',
		'Fulfilled in the USA',
	],
	header_links: [
		{ label: 'Home', url: '/', display: 'text', icon: '', accent: false, mobile_pin: false },
		{ label: 'Shop', url: '/shop', display: 'text', icon: '', accent: false, mobile_pin: false },
		{ label: 'About', url: '/about', display: 'text', icon: '', accent: false, mobile_pin: false },
		{ label: 'COA Library', url: '/coa-library', display: 'text', icon: '', accent: false, mobile_pin: false },
		{ label: 'Contact', url: '/contact', display: 'text', icon: '', accent: false, mobile_pin: false },
		{ label: 'Account', url: '/account', display: 'icon', icon: 'user', accent: true, mobile_pin: false },
	],
	header_toggle_accent: true,
	header_cart_accent: true,
	header_inverted: false,
	header_borderless: false,
	mobile_hamburger_side: 'right',
	header_show_toggle: false,
	header_toggle_mobile_pin: false,
	header_cart_mobile_pin: true,
	theme_default: 'light',
	logo_invert_on_dark: true,
	logo_size: 'standard',
	brand_position: 'nav-center',
	typography: { heading_font: 'inter', body_font: 'inter', heading_weight: 'semibold', body_size: 'm' },
	seo_nosnippet_products: true,
	pdp: {
		show_reviews: true,
		cross_sell_mode: 'simple',
		modules: [],
		coa_library_url: '',
		bundle_bogo: {
			enabled: true,
			savings_pct: 50,
			presets: [
				{ paid_qty: 1, discount_pct: 0, flag: '' },
				{ paid_qty: 3, discount_pct: 15, flag: 'POPULAR' },
				{ paid_qty: 5, discount_pct: 23, flag: 'BEST VALUE' },
				{ paid_qty: 10, discount_pct: 31, flag: 'BULK' },
				{ paid_qty: 15, discount_pct: 40, flag: '', pdp_hidden: true },
			],
		},
		cross_sell: {
			eyebrow: 'FREQUENTLY PAIRED',
			title: 'Often ordered with',
			subtitle: 'Researchers commonly add these to their order',
			view_all_url: '/shop',
		},
		slide_cart: {
			cross_sell_exclude_product_ids: [],
			cross_sell_exclude_slugs: [...CART_CROSS_SELL_DEFAULT_EXCLUDE_SLUGS],
			social_proof: {
				enabled: true,
				count_min: 18,
				count_max: 32,
				suffix: 'researchers checking out now',
				live_label: 'LIVE',
				avatars: ['JM', 'AH', 'RT'],
			},
			rewards: {
				enabled: true,
				bac_water_threshold: 300,
				urgency_label: 'ENDS SUNDAY',
			},
		},
		coa_section: {
			enabled: true,
			eyebrow: 'TRANSPARENCY',
			title: 'Certificate of Analysis',
			subtitle: 'Every batch independently verified by third-party laboratories.',
			disclaimer:
				'Certificates of Analysis are provided for informational purposes. Results apply to the specific batch tested. Products are sold for research use only.',
			thumbnail: '',
			thumbnail_alt: 'Certificate of Analysis preview',
			default_lab: 'Analytical Laboratories Inc.',
			default_metrics: [
				{ label: 'HPLC Purity', value: '≥99.4%' },
				{ label: 'LC-MS Identity', value: 'Confirmed' },
				{ label: 'Sterility', value: 'PASS' },
				{ label: 'Contaminants', value: 'ND' },
				{ label: 'Heavy Metals', value: '<20 ppb' },
				{ label: 'TAMC / TYMC', value: 'PASS' },
			],
		},
		verified_label: 'VERIFIED',
		show_ships_banner: true,
		show_payment_icons: true,
		image_disclaimer: 'FOR RESEARCH PURPOSES ONLY',
		features: [
			{ icon: 'lab', label: 'Manufactured in US' },
			{ icon: 'zap', label: 'Fastest in Trend' },
			{ icon: 'shield', label: 'Independently Tested' },
			{ icon: 'shipping', label: 'Same Day Shipping' },
		],
		trust_badges: [
			{ icon: 'shield', label: '60-Day Money-Back Guarantee' },
			{ icon: 'shipping', label: 'Ships Today if Ordered Before 2PM' },
			{ icon: 'lock', label: 'Secure Checkout' },
		],
	},
	shop: { modules: [], cols_min: 2, cols_max: 4, spacing_h: 'normal' },
	pages: [],
	footer: { columns: [] },
	social_links: [],
	product_card: {
		media_aspect_ratio: '1:1',
		corner_radius: 'round',
		border: 'none',
		hover_effect: 'shadow',
		button_style: 'solid',
		badge_position: 'top-right',
		badge_style: 'filled',
		show_bulk_badge: true,
		show_tier_hint: true,
		show_oos_cards: true,
		oos_treatment: 'grayscale',
		title_lines: 'auto',
		secondary_image_on_hover: false,
		sale_badge_text: 'Sale',
	},
	tokens: {
		radius: null,
		spacing_v_compact: null,
		spacing_v_normal: null,
		spacing_v_spacious: null,
	},
	gate_modal: {
		enabled: false,
		strict: false,
		title: '',
		content: '',
		confirm_text: 'Enter Site',
		decline_text: '',
		decline_url: '',
		version: 1,
	},
	active_scripts: [],
};

const VALID_HERO_VARIANTS: HomepageHeroConfig['variant'][] = [
	'text-only',
	'research-motion',
	'webgl-noise',
	'webgl-variant-2',
	'webgl-variant-3',
	'webgl-variant-4',
	'webgl-variant-5',
	'webgl-variant-6',
];

function normalizeHeroVariant(raw: unknown): HomepageHeroConfig['variant'] {
	const s = typeof raw === 'string' ? raw.trim() : '';
	if (s && (VALID_HERO_VARIANTS as readonly string[]).includes(s)) {
		return s as HomepageHeroConfig['variant'];
	}
	return DEFAULTS.homepage.hero.variant;
}

/** Where to seed trust_bar — before split_value / catalog when missing. */
function legacyTrustBarInsertIndex(modules: HomepageModule[]): number {
	const list = modules;
	const svIdx = list.findIndex((m) => m?.type === 'split_value');
	if (svIdx !== -1) {
		return svIdx;
	}
	const sliderIdx = list.findIndex(
		(m) => m?.type === 'featured_products' || m?.type === 'product_slider'
	);
	return sliderIdx >= 0 ? sliderIdx : 0;
}

function mergeHomepageTrustBar(modules: HomepageModule[]): HomepageModule[] {
	const seed = DEFAULTS.homepage.modules.find((m) => m.type === 'trust_bar');
	if (!seed || seed.type !== 'trust_bar') return modules;
	return modules.map((m) => {
		if (m.type !== 'trust_bar') return m;
		return {
			...m,
			spacing_v: 'compact',
			config: {
				...seed.config,
				title: '',
				icon_accent: true,
				items: seed.config.items.map((item) => ({ ...item })),
			},
		};
	});
}

function mergeHomepageModulesWithDefaultSplitValue(modules: HomepageModule[]): HomepageModule[] {
	const list = Array.isArray(modules) ? [...modules] : [];
	if (
		HOMEPAGE_SPLIT_VALUE_ENABLED &&
		!list.some((m) => m && m.type === 'split_value')
	) {
		const seed = DEFAULTS.homepage.modules.find((m) => m.type === 'split_value');
		if (seed) {
			list.unshift(JSON.parse(JSON.stringify(seed)) as HomepageModule);
		}
	}
	const tbInsert = legacyTrustBarInsertIndex(list);
	if (!list.some((m) => m && m.type === 'trust_bar') && tbInsert >= 0) {
		const tbSeed = DEFAULTS.homepage.modules.find((m) => m.type === 'trust_bar');
		if (tbSeed) {
			list.splice(tbInsert, 0, JSON.parse(JSON.stringify(tbSeed)) as HomepageModule);
		}
	}
	return list;
}

function mergeHomepageModulesWithDefaultOrderHandling(modules: HomepageModule[]): HomepageModule[] {
	const list = Array.isArray(modules) ? [...modules] : [];
	if (list.some((m) => m && m.type === 'order_handling')) {
		return list;
	}
	const seed = DEFAULTS.homepage.modules.find((m) => m.type === 'order_handling');
	if (!seed) {
		return list;
	}
	const copy = JSON.parse(JSON.stringify(seed)) as HomepageModule;
	const accIdx = list.findIndex((m) => m?.type === 'accordion');
	if (accIdx >= 0) {
		list.splice(accIdx, 0, copy);
	} else {
		list.push(copy);
	}
	return list;
}

const FEATURED_PRODUCTS_MODULE_SEED = (): HomepageModule & { type: 'featured_products' } => {
	const seed = DEFAULTS.homepage.modules.find((m) => m.type === 'featured_products');
	if (seed?.type === 'featured_products') {
		return JSON.parse(JSON.stringify(seed)) as HomepageModule & { type: 'featured_products' };
	}
	return {
		type: 'featured_products',
		visibility: 'all',
		spacing_v: 'normal',
		spacing_h: 'normal',
		config: {
			eyebrow: 'Bestsellers',
			headline_prefix: 'Featured',
			headline_accent: 'Products',
			subheadline:
				'Explore our most popular research compounds, chosen for their quality, purity, and consistency.',
			product_badge: 'Most Popular',
			source: 'popular',
			product_limit: 3,
			cta_text: 'Explore All Products',
			cta_href: '/shop',
		},
	};
};

function moduleToFeaturedProducts(mod: HomepageModule): HomepageModule & { type: 'featured_products' } {
	const seed = FEATURED_PRODUCTS_MODULE_SEED();
	return {
		...seed,
		id: mod.id,
		visibility: mod.visibility ?? seed.visibility,
		spacing_v: mod.spacing_v ?? seed.spacing_v,
		spacing_h: mod.spacing_h ?? seed.spacing_h,
		config: {
			...seed.config,
			...(mod.type === 'featured_products' ? mod.config : {}),
		},
	};
}

function mergeHomepageFeaturedProducts(modules: HomepageModule[]): HomepageModule[] {
	const list = Array.isArray(modules) ? [...modules] : [];
	if (list.some((m) => m?.type === 'featured_products')) {
		return list.map((m) =>
			m?.type === 'featured_products' ? moduleToFeaturedProducts(m) : m
		);
	}
	for (let i = 0; i < list.length; i++) {
		const mod = list[i];
		if (!mod) continue;
		if (
			mod.type === 'product_slider' &&
			(mod.config as ProductSliderModuleConfig)?.source === 'all'
		) {
			list[i] = moduleToFeaturedProducts(mod);
			return list;
		}
		if (mod.type === 'category_grid' || mod.type === 'shop_grid') {
			list[i] = moduleToFeaturedProducts(mod);
			return list;
		}
	}
	const sliderIdx = list.findIndex((m) => m?.type === 'product_slider');
	const insertAt = sliderIdx >= 0 ? sliderIdx : legacyTrustBarInsertIndex(list);
	list.splice(insertAt, 0, FEATURED_PRODUCTS_MODULE_SEED());
	return list;
}

export function homepageModulesWithSplitValueAfterHero(modules: HomepageModule[]): HomepageModule[] {
	const visible = modules.filter(isModuleVisibleNow);
	let ordered = [...visible];

	const tbIdx = ordered.findIndex((m) => m.type === 'trust_bar');
	if (tbIdx > 0) {
		const [tb] = ordered.splice(tbIdx, 1);
		ordered.unshift(tb);
	}

	const svIdx = ordered.findIndex((m) => m.type === 'split_value');
	if (HOMEPAGE_SPLIT_VALUE_ENABLED && svIdx !== -1) {
		const [sv] = ordered.splice(svIdx, 1);
		const afterTrust = ordered.findIndex((m) => m.type === 'trust_bar');
		ordered.splice(afterTrust >= 0 ? afterTrust + 1 : 0, 0, sv);
	}

	const catalogIdx = ordered.findIndex(
		(m) =>
			m.type === 'featured_products' ||
			m.type === 'product_slider' ||
			m.type === 'shop_grid'
	);
	const reviewsIdx = ordered.findIndex((m) => m.type === 'reviews_listicle');
	if (catalogIdx !== -1 && reviewsIdx !== -1 && reviewsIdx !== catalogIdx + 1) {
		const [reviews] = ordered.splice(reviewsIdx, 1);
		ordered.splice(catalogIdx + 1, 0, reviews);
	}

	const faqsIdx = ordered.findIndex((m) => m.type === 'listicle_faqs');
	const reviewsPos = ordered.findIndex((m) => m.type === 'reviews_listicle');
	if (faqsIdx !== -1 && reviewsPos !== -1 && faqsIdx !== reviewsPos + 1) {
		const [faqs] = ordered.splice(faqsIdx, 1);
		const insertAfter = ordered.findIndex((m) => m.type === 'reviews_listicle');
		ordered.splice(insertAfter + 1, 0, faqs);
	}

	return ordered;
}

function mergeFetchedPdp(incoming: PdpConfig | undefined): PdpConfig {
	const base = DEFAULTS.pdp;
	const pdp = incoming ?? base;
	const slide = { ...base.slide_cart, ...pdp.slide_cart };
	return {
		...base,
		...pdp,
		slide_cart: {
			...slide,
			cross_sell_exclude_slugs: [
				...new Set([
					...CART_CROSS_SELL_DEFAULT_EXCLUDE_SLUGS,
					...(slide.cross_sell_exclude_slugs ?? []),
				]),
			],
			cross_sell_exclude_product_ids: [
				...new Set([
					...(base.slide_cart?.cross_sell_exclude_product_ids ?? []),
					...(slide.cross_sell_exclude_product_ids ?? []),
				]),
			],
			social_proof: {
				...base.slide_cart?.social_proof,
				...slide.social_proof,
			},
			rewards: {
				...base.slide_cart?.rewards,
				...slide.rewards,
			},
		},
		coa_section: {
			...base.coa_section,
			...pdp.coa_section,
		},
	};
}

/** REST replaces whole `homepage`; merge defaults into `hero` so new keys resolve without wiping merchant overrides. */
function mergeFetchedHomepage(incoming: HomepageConfig | undefined): HomepageConfig {
	const base = DEFAULTS.homepage;
	const hp = incoming ?? base;
	const rawHero = hp.hero ?? {};
	const rawModules = Array.isArray(hp.modules) ? hp.modules : base.modules;
	return {
		...base,
		...hp,
		hero: {
			...base.hero,
			...rawHero,
			variant: normalizeHeroVariant(rawHero.variant),
			precision: {
				...HERO_PRECISION_DEFAULTS,
				...(rawHero.precision ?? {}),
			},
		},
		modules: mergeHomepageFeaturedProducts(
			mergeHomepageTrustBar(
				mergeHomepageModulesWithDefaultOrderHandling(
					mergeHomepageModulesWithDefaultSplitValue(rawModules)
				)
			)
		),
	};
}

class ConfigStore {
	data = $state<SiteConfig>(DEFAULTS);
	ready = $state(false);
	error = $state<string | null>(null);
	private loadPromise: Promise<SiteConfig> | null = null;

	/**
	 * Fetch config once. Safe to call from multiple mount points — the
	 * second call returns the same promise.
	 */
	load(): Promise<SiteConfig> {
		if (!browser) return Promise.resolve(this.data);
		if (this.loadPromise) return this.loadPromise;
		return this.doFetch();
	}

	/**
	 * Force a config re-fetch. Used on tab focus and after 503 responses
	 * so access mode changes (admin switching the site to maintenance,
	 * locked, etc.) take effect without a hard refresh. Quietly updates
	 * state — no loading gate retrigger.
	 */
	refresh(): Promise<SiteConfig> {
		if (!browser) return Promise.resolve(this.data);
		this.loadPromise = null;
		return this.doFetch();
	}

	private doFetch(): Promise<SiteConfig> {
		this.loadPromise = (async () => {
			try {
				const ac = new AbortController();
				const timer = setTimeout(() => ac.abort(), 10000);
				const bust = Date.now().toString(36);
				const res = await fetch(`/wp-json/wchs/v1/config?__wchs_bust=${encodeURIComponent(bust)}`, {
					credentials: 'include',
					headers: { Accept: 'application/json' },
					signal: ac.signal,
				});
				clearTimeout(timer);
				if (isCaptchaChallenge(res)) {
					if (handleCaptchaChallenge()) {
						await new Promise(() => {});
					}
					throw new Error('Security challenge — please refresh the page.');
				}
				if (!res.ok) {
					throw new Error(`config fetch failed: HTTP ${res.status}`);
				}
				const json = (await res.json()) as SiteConfig;
				// Validate the shape minimally — must have wp_origin at least.
				if (!json.wp_origin || typeof json.wp_origin !== 'string') {
					throw new Error('config response missing wp_origin');
				}
				const mergedHomepage = mergeFetchedHomepage(json.homepage);
				this.data = {
					...DEFAULTS,
					...json,
					features: { ...DEFAULTS.features, ...json.features },
					homepage: mergedHomepage,
					home_1: resolveHome1Landing(json.home_1, mergedHomepage),
					pdp: mergeFetchedPdp(json.pdp),
					pages: mergeVaultPages(json.pages ?? [], mergedHomepage.modules),
				};
				this.ready = true;
				this.error = null;
				return this.data;
			} catch (e) {
				this.error = e instanceof Error ? e.message : String(e);
				// Keep defaults so the SPA still runs in a degraded mode.
				this.ready = true;
				return this.data;
			}
		})();

		return this.loadPromise;
	}

	/** Convenience — canonical URL builders that use the loaded config. */
	wpUrl(path: string): string {
		const base = this.data.wp_origin.replace(/\/$/, '');
		const p = path.startsWith('/') ? path : `/${path}`;
		return base + p;
	}

	checkoutUrl(cartToken: string | null): string {
		const path = (this.data.checkout_handoff_path || '/checkout').replace(/\/+$/, '') || '/checkout';
		const base = this.wpUrl(`${path}/`);
		return cartToken ? `${base}?cart=${encodeURIComponent(cartToken)}` : base;
	}

	myAccountUrl(returnTo?: string): string {
		const base = this.wpUrl('/my-account/');
		const ret = returnTo ?? this.data.spa_origin + '/account';
		return `${base}?return=${encodeURIComponent(ret)}`;
	}

	myAccountPage(page: string, returnTo?: string): string {
		const base = this.wpUrl(`/my-account/${page.replace(/^\/+/, '')}`);
		const ret = returnTo ?? this.data.spa_origin + '/account';
		return `${base}?return=${encodeURIComponent(ret)}`;
	}

	logoutUrl(returnTo?: string): string {
		return this.myAccountPage('customer-logout/', returnTo);
	}

	/** Preview mode — listen for postMessage config overrides from admin iframe parent. */
	initPreviewMode(): void {
		if (!browser) return;
		if (!new URLSearchParams(window.location.search).has('preview')) return;

		window.addEventListener('message', (e) => {
			if (!e.data?.__wchs_preview) return;
			const msg = e.data as Record<string, unknown>;

			// Merge homepage override — deep merge hero fields to preserve existing values
			if (msg.homepage && typeof msg.homepage === 'object') {
				const hp = msg.homepage as Partial<SiteConfig['homepage']>;
				const currentHp = this.data.homepage;
				const rawList = (hp.modules !== undefined ? hp.modules : currentHp.modules) as HomepageModule[];
				const mergedList = mergeHomepageModulesWithDefaultSplitValue(rawList);
				const nextModules = this.reResolveModules(mergedList) as SiteConfig['homepage']['modules'];
				this.data = {
					...this.data,
					homepage: {
						...currentHp,
						hero: { ...currentHp.hero, ...(hp.hero ?? {}) },
						modules: nextModules,
					},
				};
				// Lazy-load the hero's @font-face stylesheet when the admin
				// switches to a non-Inter font. Without this the font-family
				// CSS applies but no file ever loads, so the preview falls
				// back to system sans and every non-Inter option looks the
				// same. loadFont is idempotent.
				if (hp.hero && (hp.hero as { headline_font?: string }).headline_font) {
					loadFont((hp.hero as { headline_font?: string }).headline_font);
				}
			}

			// Merge shop override
			if (msg.shop && typeof msg.shop === 'object') {
				const sh = msg.shop as Partial<SiteConfig['shop']>;
				const currentShop = this.data.shop;
				const nextModules = sh.modules ? this.reResolveModules(sh.modules) : currentShop.modules;
				this.data = {
					...this.data,
					shop: { ...currentShop, ...sh, modules: nextModules },
				};
			}

			// Merge PDP override
			if (msg.pdp && typeof msg.pdp === 'object') {
				const pd = msg.pdp as Partial<SiteConfig['pdp']>;
				const currentPdp = this.data.pdp;
				const nextModules = pd.modules ? this.reResolveModules(pd.modules) : currentPdp.modules;
				this.data = {
					...this.data,
					pdp: { ...currentPdp, ...pd, modules: nextModules },
				};
			}

			const mergePage = (
				currentPages: SiteConfig['pages'],
				raw: { slug: string; title: string; modules: SiteConfig['pages'][0]['modules'] },
			) => {
				const pg = raw;
				const pages = [...currentPages];
				const mods = pg.modules ? this.reResolveModules(pg.modules) : [];
				const idx = pages.findIndex(p => p.slug === pg.slug);
				const next = { ...pg, modules: mods };
				if (idx >= 0) pages[idx] = next;
				else pages.push(next);
				return pages;
			};

			// Merge page overrides. The admin sends the whole pages array so
			// multi-artboard previews update the edited page, not just whichever
			// page card happened to be last in the form.
			if (Array.isArray(msg.pages)) {
				let pages = [...(this.data.pages ?? [])];
				for (const raw of msg.pages) {
					if (!raw || typeof raw !== 'object') continue;
					const pg = raw as { slug: string; title: string; modules: SiteConfig['pages'][0]['modules'] };
					if (!pg.slug) continue;
					pages = mergePage(pages, pg);
				}
				this.data = { ...this.data, pages };
			} else if (msg.page && typeof msg.page === 'object') {
				const pg = msg.page as { slug: string; title: string; modules: SiteConfig['pages'][0]['modules'] };
				const pages = mergePage(this.data.pages ?? [], pg);
				this.data = { ...this.data, pages };
			}

			// Merge appearance overrides (typography, accent, header, footer, logo, theme, social)
			if (msg.appearance && typeof msg.appearance === 'object') {
				this.applyAppearance(msg.appearance as Record<string, unknown>);
			}
		});
	}

	private reResolveModules<T extends { overrides?: unknown; type?: string; config?: Record<string, unknown> }>(modules: T[]): T[] {
		const defaults = siteDefaults({
			accent_color: this.data.accent_color,
			typography: this.data.typography,
		});
		// Lazy-load Bunny stylesheet for any hero module's headline_font so
		// non-Inter fonts actually render in the preview iframe (see typography
		// branch in applyAppearance for the full rationale).
		modules.forEach(m => {
			if (m.type === 'hero' && m.config && typeof m.config === 'object') {
				const f = (m.config as { headline_font?: string }).headline_font;
				if (f) loadFont(f);
			}
		});
		return resolveModules(
			modules as Array<T & { overrides?: Record<string, unknown> }>,
			defaults,
		) as unknown as T[];
	}

	private applyAppearance(app: Record<string, unknown>): void {
		const root = document.documentElement;
		const patch: Partial<SiteConfig> = {};

		// Typography — apply CSS custom properties + update config.data
		if (app.typography && typeof app.typography === 'object') {
			const nextTypo = { ...this.data.typography, ...(app.typography as Partial<SiteConfig['typography']>) };
			patch.typography = nextTypo;
			const fontMap: Record<string, string> = {
				inter: "'Inter', system-ui, -apple-system, 'Segoe UI', sans-serif",
				barlow: "'Barlow Semi Condensed', sans-serif",
				bebas: "'Bebas Neue', sans-serif",
				playfair: "'Playfair Display', serif",
				space_grotesk: "'Space Grotesk', sans-serif",
				archivo: "'Archivo', sans-serif",
				oswald: "'Oswald', sans-serif",
			};
			const weightMap: Record<string, string> = {
				light: '300', regular: '400', medium: '500', semibold: '600',
				bold: '700', extrabold: '800', black: '900',
			};
			const sizeMap: Record<string, string> = { s: '14px', m: '15px', l: '16px' };
			if (fontMap[nextTypo.heading_font]) {
				root.style.setProperty('--font-heading', fontMap[nextTypo.heading_font]);
				// Lazy-load the Bunny @font-face stylesheet. Without this, only
				// Inter (preloaded in app.html) actually renders — every other
				// font falls back to the next stack entry (sans-serif / serif),
				// making Barlow / Bebas / Space Grotesk / Archivo / Oswald all
				// look identical in the live-preview iframe. loadFont is
				// idempotent so repeated calls during scheduleSync are free.
				loadFont(nextTypo.heading_font);
			}
			if (fontMap[nextTypo.body_font]) {
				root.style.setProperty('--font-sans', fontMap[nextTypo.body_font]);
				root.style.setProperty('--font-body', fontMap[nextTypo.body_font]);
				loadFont(nextTypo.body_font);
			}
			root.style.setProperty('--heading-weight', weightMap[nextTypo.heading_weight] || '600');
			const bs = sizeMap[nextTypo.body_size] || '15px';
			root.style.setProperty('--body-size', bs);
			root.style.fontSize = bs;
		}

		// Accent color + CSS var
		if (app.accent_color !== undefined) {
			patch.accent_color = (app.accent_color as string) || null;
			if (patch.accent_color) {
				root.style.setProperty('--accent', patch.accent_color);
			} else {
				root.style.removeProperty('--accent');
			}
		}

		// Design tokens — each component reads via var(--token, <fallback>),
		// so null removes the var and hardcoded defaults kick back in.
		if (app.tokens !== undefined) {
			patch.tokens = app.tokens as DesignTokens;
			const tk = patch.tokens;
			const setOrRemove = (name: string, value: number | null) => {
				if (typeof value === 'number' && Number.isFinite(value)) {
					root.style.setProperty(name, `${value}px`);
				} else {
					root.style.removeProperty(name);
				}
			};
			if (tk) {
				setOrRemove('--wchs-radius', tk.radius);
				setOrRemove('--wchs-spacing-v-compact', tk.spacing_v_compact);
				setOrRemove('--wchs-spacing-v-normal', tk.spacing_v_normal);
				setOrRemove('--wchs-spacing-v-spacious', tk.spacing_v_spacious);
			}
		}

		// Scalar assignments — passthrough fields that Header/Footer already consume reactively
		const passthrough: Array<keyof SiteConfig> = [
			'logo_size', 'logo_invert_on_dark', 'logo_dark_url', 'brand_position',
			'theme_default', 'header_links', 'mobile_hamburger_side',
			'header_show_toggle', 'header_toggle_accent', 'header_cart_accent',
			'header_inverted', 'header_borderless',
			'header_toggle_mobile_pin', 'header_cart_mobile_pin',
			'footer', 'social_links',
		];
		for (const key of passthrough) {
			if (app[key as string] !== undefined) {
				(patch as Record<string, unknown>)[key as string] = app[key as string];
			}
		}

		// Header sub-object: admin sends header: { show_toggle, ... }
		const header = app.header as Record<string, unknown> | undefined;
		if (header && typeof header === 'object') {
			if (header.show_toggle !== undefined) patch.header_show_toggle = !!header.show_toggle;
			if (header.toggle_accent !== undefined) patch.header_toggle_accent = !!header.toggle_accent;
			if (header.cart_accent !== undefined) patch.header_cart_accent = !!header.cart_accent;
			if (header.inverted !== undefined) patch.header_inverted = !!header.inverted;
			if (header.borderless !== undefined) patch.header_borderless = !!header.borderless;
			if (header.toggle_mobile_pin !== undefined) patch.header_toggle_mobile_pin = !!header.toggle_mobile_pin;
			if (header.cart_mobile_pin !== undefined) patch.header_cart_mobile_pin = !!header.cart_mobile_pin;
			if (header.mobile_hamburger_side) patch.mobile_hamburger_side = header.mobile_hamburger_side as SiteConfig['mobile_hamburger_side'];
		}

		// Product card: merge incoming keys onto the existing config
		// (partial payloads shouldn't wipe untouched keys).
		if (app.product_card && typeof app.product_card === 'object') {
			const next = {
				...this.data.product_card,
				...(app.product_card as Partial<SiteConfig['product_card']>),
			};
			patch.product_card = next;
			// Re-apply CSS vars + data attributes immediately. Dynamic
			// import keeps the tokens module out of non-preview bundles.
			import('./product-card-tokens').then((m) => m.applyProductCardTokens(next));
		}

		this.data = { ...this.data, ...patch };

		// Theme default: flip the data-theme attribute in preview without
		// touching stored prefs. Skipped when the admin canvas toolbar has
		// forced a specific preview theme (theme.previewOverride) — otherwise
		// every scheduleSync would revert the admin's chosen theme on the
		// next setting change.
		if (patch.theme_default && !theme.previewOverride) {
			if (patch.theme_default === 'system') {
				const prefersDark = window.matchMedia?.('(prefers-color-scheme: dark)').matches;
				root.setAttribute('data-theme', prefersDark ? 'dark' : 'light');
			} else {
				root.setAttribute('data-theme', patch.theme_default);
			}
		}
	}
}

export const config = new ConfigStore();
