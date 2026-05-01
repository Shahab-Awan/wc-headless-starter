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
	variant: 'text-only' | 'webgl-noise' | 'webgl-variant-2' | 'webgl-variant-3' | 'webgl-variant-4' | 'webgl-variant-5' | 'webgl-variant-6';
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
	variant?: 'text-only' | 'webgl-noise' | 'webgl-variant-2' | 'webgl-variant-3' | 'webgl-variant-4' | 'webgl-variant-5' | 'webgl-variant-6';
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

export type TextBlockModuleConfig = {
	title: string;
	content: string;
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
	title: string;
	items: SplitFeatureItem[];
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
	| (ModuleBase & { type: 'gallery'; config: GalleryModuleConfig })
	| (ModuleBase & { type: 'category_grid'; config: CategoryGridModuleConfig })
	| (ModuleBase & { type: 'split_features'; config: SplitFeaturesModuleConfig })
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

export type PdpConfig = {
	show_reviews: boolean;
	cross_sell_mode: 'simple' | 'complex';
	modules: HomepageModule[];
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
	brand_position: 'left' | 'center';
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
	features: { guest_checkout: true, dark_mode: true, pretext: true },
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
			headline: 'Welcome',
			content_mode: 'text',
			logo_source: 'site_logo',
			logo_url: '',
			logo_dark_url: '',
			logo_size: 'large',
			subheadline: 'Browse products, review your cart, and checkout securely.',
			cta_text: 'Enter the shop',
			cta_link: '/shop',
			variant: 'webgl-noise',
			layout: 'left',
			image_desktop: '',
			image_mobile: '',
			image_position_x: 50,
			image_position_y: 50,
			image_position_mobile_x: 50,
			image_position_mobile_y: 80,
			show_eyebrow: true,
			cta_accent: true,
			show_cta: true,
			show_rating: false,
			rating_text: '',
			trust_items: [],
		},
		modules: [{
			type: 'product_slider',
			visibility: 'all',
			config: {
				title: 'Featured',
				source: 'all',
				category: null,
				product_ids: [],
			},
		}],
	},
	review_write_enabled: true,
	turnstile_site_key: '',
	header_links: [
		{ label: 'Shop', url: '/shop', display: 'text', icon: '', accent: false, mobile_pin: false },
		{ label: 'Account', url: '/account', display: 'icon', icon: 'user', accent: true, mobile_pin: false },
	],
	header_toggle_accent: true,
	header_cart_accent: true,
	header_inverted: false,
	header_borderless: false,
	mobile_hamburger_side: 'right',
	header_show_toggle: true,
	header_toggle_mobile_pin: false,
	header_cart_mobile_pin: true,
	theme_default: 'system',
	logo_invert_on_dark: true,
	logo_size: 'standard',
	brand_position: 'left',
	typography: { heading_font: 'inter', body_font: 'inter', heading_weight: 'semibold', body_size: 'm' },
	seo_nosnippet_products: true,
	pdp: {
		show_reviews: true,
		cross_sell_mode: 'simple',
		modules: [],
	},
	shop: { modules: [], cols_min: 2, cols_max: 4, spacing_h: 'normal' },
	pages: [],
	footer: { columns: [] },
	social_links: [],
	product_card: {
		media_aspect_ratio: '1:1',
		corner_radius: 'square',
		border: 'full',
		hover_effect: 'lift',
		button_style: 'outline',
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
				this.data = { ...DEFAULTS, ...json, features: { ...DEFAULTS.features, ...json.features } };
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
				const nextModules = hp.modules ? this.reResolveModules(hp.modules) : currentHp.modules;
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
