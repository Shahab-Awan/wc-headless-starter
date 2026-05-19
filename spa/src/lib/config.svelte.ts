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
	layout: 'left' | 'center' | 'bottom';
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
	layout?: 'left' | 'center' | 'bottom';
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

export type TextBlockComparisonRow = { heading: string };

export type ListicleItem = {
	number?: string;
	label?: string;
	headline: string;
	body?: string;
	callout?: string;
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
	quote?: string;
	name?: string;
	rating?: number;
};

export type ReviewsListicleModuleConfig = {
	headline?: string;
	items?: ReviewsListicleItem[];
};

export type ListicleModuleConfig = {
	section_eyebrow?: string;
	headline?: string;
	hero_image?: string;
	hero_image_alt?: string;
	intro?: string;
	items_headline?: string;
	closing?: string;
	items?: ListicleItem[];
	cta_label?: string;
	cta_href?: string;
};

export type TextBlockModuleConfig = {
	layout?: 'auto' | 'standard' | 'comparison';
	title: string;
	headline?: string;
	content: string;
	brand_name?: string;
	competitor_name?: string;
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

export type OrderHandlingStep = {
	variant: string;
	headline: string;
	description: string;
};

export type OrderHandlingMetric = { value: string; label: string };

export type OrderHandlingModuleConfig = {
	badge_text: string;
	headline: string;
	subheadline: string;
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

export type HomepageModule =
	| (ModuleBase & { type: 'product_slider'; config: ProductSliderModuleConfig })
	| (ModuleBase & { type: 'review_slider'; config: ReviewSliderModuleConfig })
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
	| (ModuleBase & { type: 'feature_highlights'; config: FeatureHighlightsModuleConfig })
	| (ModuleBase & { type: 'order_handling'; config: OrderHandlingModuleConfig })
	| (ModuleBase & { type: 'shop_grid'; config: ShopGridModuleConfig })
	| (ModuleBase & { type: 'contact_form'; config: ContactFormModuleConfig })
	| (ModuleBase & { type: 'hero'; config: HeroModuleConfig })
	| (ModuleBase & { type: 'cta'; config: CTAModuleConfig })
	| (ModuleBase & { type: 'spacer'; config: SpacerModuleConfig })
	| (ModuleBase & { type: 'logo_strip'; config: LogoStripModuleConfig })
	| (ModuleBase & { type: 'video'; config: VideoModuleConfig });

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
	presets?: Array<{ paid_qty: number; free_qty?: number; flag?: string }>;
};

export type PdpCoaSectionConfig = {
	enabled?: boolean;
	eyebrow?: string;
	title?: string;
	subtitle?: string;
	disclaimer?: string;
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

export type SlideCartConfig = {
	cross_sell_exclude_product_ids?: number[];
	cross_sell_exclude_slugs?: string[];
};

export const CART_CROSS_SELL_DEFAULT_EXCLUDE_SLUGS = ['bac-water-10ml', 'shipping-protection'] as const;
export const CART_CROSS_SELL_TARGET_COUNT = 4;

export function cartCrossSellExcludeSlugs(): string[] {
	const fromConfig = config.data.pdp?.slide_cart?.cross_sell_exclude_slugs ?? [];
	return [...new Set([...CART_CROSS_SELL_DEFAULT_EXCLUDE_SLUGS, ...fromConfig])];
}

export function cartCrossSellExcludeProductIds(): number[] {
	const fromConfig = config.data.pdp?.slide_cart?.cross_sell_exclude_product_ids ?? [];
	return [...new Set([...fromConfig])];
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
	brand_name: string;
	static_seo_title: string;
	static_seo_description: string;
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
	spa_origin: 'http://localhost:5175',
	brand_name: 'Online Store',
	static_seo_title: '',
	static_seo_description: '',
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
	gtm_id: '',
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
			variant: 'research-motion',
			layout: 'center',
			image_desktop: '',
			image_mobile: '',
			image_position_x: 50,
			image_position_y: 50,
			image_position_mobile_x: 50,
			image_position_mobile_y: 80,
			image_zoom: 100,
			image_zoom_mobile: 100,
			show_eyebrow: false,
			cta_accent: true,
			show_cta: true,
			show_rating: false,
			rating_text: '',
			trust_items: [],
		},
		modules: [
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
				type: 'feature_highlights',
				visibility: 'all',
				spacing_v: 'normal',
				spacing_h: 'normal',
				config: {
					badge_text: 'Verified & Trusted',
					headline_prefix: 'The Standard for ',
					headline_accent: 'Verified Peptides',
					subheadline:
						'Independent testing. Full batch documentation. Reliable, tracked delivery.',
					items: [
						{
							variant: 'pin',
							headline: 'USA Manufactured',
							description: 'Synthesized and packaged domestically. No overseas sourcing.',
						},
						{
							variant: 'star',
							headline: '5-Star Reviewed',
							description: 'Rated 5 stars by verified customers.',
						},
						{
							variant: 'lab',
							headline: 'Third-Party Lab Tested',
							description: 'Every batch independently verified before shipping.',
						},
						{
							variant: 'award',
							headline: 'Triple-Tested for Quality',
							description: 'Purity, Content, and Endotoxin testing on every product.',
						},
					],
					cta_label: 'Buy 1 Get 1 Free',
					cta_href: '/shop',
				},
			},
			{
				type: 'product_slider',
				visibility: 'all',
				config: {
					title: 'Featured',
					source: 'all',
					category: null,
					product_ids: [],
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
		'UP TO 40% OFF TODAY',
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
				{ paid_qty: 1, free_qty: 0, flag: '' },
				{ paid_qty: 2, free_qty: 1, flag: 'MOST POPULAR' },
				{ paid_qty: 3, free_qty: 2, flag: 'BEST VALUE' },
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
		},
		coa_section: {
			enabled: true,
			eyebrow: 'TRANSPARENCY',
			title: 'Certificate of Analysis',
			subtitle: 'Every batch independently verified by third-party laboratories.',
			disclaimer:
				'Certificates of Analysis are provided for informational purposes. Results apply to the specific batch tested. Products are sold for research use only.',
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
			{ icon: 'shipping', label: 'Faster shipping' },
			{ icon: 'shield', label: '60-day guarantee' },
			{ icon: 'lock', label: 'Secure checkout' },
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

/** Where to seed feature_highlights — after BOGO when present, else before the catalog slider. */
function legacyFeatureHighlightsInsertIndex(modules: HomepageModule[]): number {
	const list = modules;
	const svIdx = list.findIndex((m) => m?.type === 'split_value');
	if (svIdx !== -1) {
		let j = svIdx + 1;
		while (j < list.length) {
			const t = list[j]?.type;
			if (t === 'trust_bar' || t === 'spacer') j++;
			else break;
		}
		if (j < list.length && list[j]?.type === 'product_slider') return j;
	}
	const sliderIdx = list.findIndex((m) => m?.type === 'product_slider');
	return sliderIdx >= 0 ? sliderIdx : -1;
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
	const fhInsert = legacyFeatureHighlightsInsertIndex(list);
	const needsLegacyFh =
		!list.some((m) => m && m.type === 'feature_highlights') && fhInsert >= 0;
	if (needsLegacyFh) {
		const fhSeed = DEFAULTS.homepage.modules.find((m) => m.type === 'feature_highlights');
		if (fhSeed) {
			const copy = JSON.parse(JSON.stringify(fhSeed)) as HomepageModule;
			list.splice(fhInsert, 0, copy);
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

export function homepageModulesWithSplitValueAfterHero(modules: HomepageModule[]): HomepageModule[] {
	const visible = modules.filter(isModuleVisibleNow);
	const svIdx = visible.findIndex((m) => m.type === 'split_value');
	let ordered = [...visible];
	if (HOMEPAGE_SPLIT_VALUE_ENABLED && svIdx > 0) {
		const [sv] = ordered.splice(svIdx, 1);
		ordered.unshift(sv);
	}
	const fhIdx = ordered.findIndex((m) => m.type === 'feature_highlights');
	const svPos = ordered.findIndex((m) => m.type === 'split_value');
	if (
		HOMEPAGE_SPLIT_VALUE_ENABLED &&
		fhIdx !== -1 &&
		svPos !== -1 &&
		fhIdx !== svPos + 1
	) {
		const [fh] = ordered.splice(fhIdx, 1);
		const insertAfter = ordered.findIndex((m) => m.type === 'split_value');
		ordered.splice(insertAfter + 1, 0, fh);
	} else if (fhIdx > 0 && (!HOMEPAGE_SPLIT_VALUE_ENABLED || svPos === -1)) {
		const [fh] = ordered.splice(fhIdx, 1);
		ordered.unshift(fh);
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
		},
		modules: mergeHomepageModulesWithDefaultOrderHandling(
			mergeHomepageModulesWithDefaultSplitValue(rawModules)
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
					pdp: mergeFetchedPdp(json.pdp),
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
		const base = this.wpUrl('/checkout/');
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
