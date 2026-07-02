import type { HomepageConfig, HomepageHeroConfig, HomepageModule } from '$lib/config.svelte';
import { HERO_PRECISION_DEFAULTS } from '$lib/hero-precision';

export const HOME_1_PATH = '/home-1';

/** Fixed free-shipping threshold for home-1 copy (announcement, hero, trust bar). */
export const HOME_1_FREE_SHIPPING_THRESHOLD = 200;

export function freeDeliveryAnnouncement(): string {
	return `FREE DELIVERY ON ALL ORDERS ABOVE $${HOME_1_FREE_SHIPPING_THRESHOLD}`;
}

export function normalizeHome1AnnouncementItems(items: string[]): string[] {
	const fallback = [
		freeDeliveryAnnouncement(),
		'Third-Party Tested',
		'COA Published Every Batch',
	];
	if (!items.length) return fallback;
	return items.map((item) => {
		if (/free\s+(delivery|shipping)/i.test(item)) return freeDeliveryAnnouncement();
		return item.replace(/\$(?:150|250)\+?/g, `$${HOME_1_FREE_SHIPPING_THRESHOLD}`);
	});
}

/** Module types stripped from the Google Ads / B2B landing (retail promos, BOGO, etc.). */
export const HOME_1_EXCLUDED_MODULE_TYPES = new Set<string>(['split_value', 'promo_offer']);

export type Home1LandingConfig = {
	/** Subdomain hostnames (no www) that should show this landing at `/` and hand off shop links to spa_origin. */
	bridge_hosts: string[];
	announcement_bar_enabled: boolean;
	announcement_bar_items: string[];
	hero: HomepageHeroConfig;
	modules: HomepageModule[];
};

export function isHome1LandingPath(path: string): boolean {
	return path.replace(/\/$/, '') === HOME_1_PATH;
}

function cloneModules(mods: HomepageModule[]): HomepageModule[] {
	return JSON.parse(JSON.stringify(mods)) as HomepageModule[];
}

function patchHero(hero: HomepageHeroConfig): HomepageHeroConfig {
	return {
		...hero,
		headline: 'Laboratory Reference Materials for Research & Innovation',
		subheadline:
			'Top-tier compounds for critical research. Independently verified batches with published Certificates of Analysis.',
		research_badge: 'FOR LABORATORY RESEARCH USE ONLY — NOT FOR HUMAN CONSUMPTION',
		cta_text: 'Shop All Products',
		cta_link: '/shop',
		cta_secondary_text: 'View COA Library',
		cta_secondary_link: '/coa-library',
		show_rating: false,
		rating_text: '',
		variant: hero.variant ?? 'webgl-variant-6',
		layout: 'precision',
		precision: {
			...HERO_PRECISION_DEFAULTS,
			...(hero.precision ?? {}),
			badge: 'FOR LABORATORY RESEARCH USE ONLY',
			headline_primary: 'Laboratory Reference Materials',
			headline_accent: 'for Research & Innovation',
			rating_label: '',
			rating_subtext: '',
			stat_2_value: '99%+',
			stat_2_label: 'HPLC-verified purity',
			stat_3_value: `$${HOME_1_FREE_SHIPPING_THRESHOLD}+`,
			stat_3_label: 'Free delivery',
			body:
				'For in-vitro laboratory research only — not for human or animal consumption. Every batch is third-party tested with a published Certificate of Analysis before release.',
			cta_primary_text: 'Shop All Products',
			cta_primary_link: '/shop',
			cta_secondary_text: 'View COA Reports',
			cta_secondary_link: '/coa-library',
		},
		research_stats: [
			{ value: '≥99%', label: 'VERIFIED PURITY' },
			{ value: 'COA', label: 'EVERY BATCH' },
			{ value: 'US', label: 'LAB TESTED' },
		],
	};
}

function patchModules(mods: HomepageModule[]): HomepageModule[] {
	return mods
		.filter((m) => !HOME_1_EXCLUDED_MODULE_TYPES.has(m.type))
		.map((mod) => {
			if (mod.type === 'trust_bar') {
				return {
					...mod,
					config: {
						...mod.config,
						items: [
							{
								icon: 'lab',
								headline: '99%+ HPLC Purity',
								description:
									'Every batch independently tested for identity and purity before release.',
							},
							{
								icon: 'shield',
								headline: 'COA Before Purchase',
								description:
									'Full Certificate of Analysis published for every batch before you order.',
							},
							{
								icon: 'percent',
								headline: 'Transparent Bulk Pricing',
								description:
									'Per-unit pricing at volume — no retail-style percentage-off banners.',
							},
							{
								icon: 'shipping',
								headline: `Free Delivery Over $${HOME_1_FREE_SHIPPING_THRESHOLD}`,
								description:
									'Complimentary tracked domestic shipping on qualifying research orders.',
							},
						],
					},
				};
			}
			if (mod.type === 'featured_products') {
				return {
					...mod,
					config: {
						...mod.config,
						product_badge: '',
						eyebrow: 'Research catalog',
						headline_prefix: 'Featured',
						headline_accent: 'Compounds',
						subheadline:
							'Independently verified research-grade peptides with batch-linked documentation.',
					},
				};
			}
			if (mod.type === 'feature_highlights') {
				return {
					...mod,
					config: {
						...mod.config,
						badge_text: 'Quality assurance',
						headline_prefix: 'Built for',
						headline_accent: 'laboratory standards',
						subheadline:
							'Documentation and testing accuracy you can verify — not retail-style guarantees.',
						items: [
							{
								variant: 'lab',
								headline: 'Third-party tested',
								description: 'Independent U.S. laboratory verification on every batch.',
							},
							{
								variant: 'star',
								headline: '99%+ research grade',
								description: 'HPLC purity targets with published chromatogram data.',
							},
							{
								variant: 'award',
								headline: 'COA accuracy guarantee',
								description:
									'Batch documentation matches the vial you receive — verified before shipment.',
							},
							{
								variant: 'pin',
								headline: 'Lyophilized stability',
								description:
									'Supplied as lyophilized powder to preserve potency during storage and transit.',
							},
						],
					},
				};
			}
			if (mod.type === 'order_handling') {
				return {
					...mod,
					config: {
						...mod.config,
						headline: 'How research orders are fulfilled',
						subheadline:
							'From batch verification to documented delivery — built for laboratory procurement.',
						steps: [
							{
								variant: 'verified',
								headline: 'Batch verification',
								description:
									'Every lot is qualified against its published COA before release.',
							},
							{
								variant: 'lab',
								headline: 'Independent testing',
								description: 'HPLC identity and purity testing by third-party laboratories.',
							},
							{
								variant: 'shipping',
								headline: `Free delivery over $${HOME_1_FREE_SHIPPING_THRESHOLD}`,
								description: 'Tracked domestic shipping on qualifying orders.',
							},
							{
								variant: 'support',
								headline: 'Research support',
								description: 'Documentation and batch questions answered by our team.',
							},
						],
						metrics: [
							{ value: '99%+', label: 'Target purity' },
							{ value: '100%', label: 'COA matched' },
							{ value: '3rd party', label: 'Lab tested' },
						],
					},
				};
			}
			return mod;
		});
}

export const HOME_1_LANDING_DEFAULTS: Omit<Home1LandingConfig, 'hero' | 'modules'> = {
	bridge_hosts: [],
	announcement_bar_enabled: true,
	announcement_bar_items: [
		freeDeliveryAnnouncement(),
		'Third-Party Tested',
		'COA Published Every Batch',
	],
};

export function buildHome1FromHomepage(homepage: HomepageConfig): Home1LandingConfig {
	const hero = patchHero(homepage.hero);
	const modules = patchModules(
		homepage.modules.filter((m) => !HOME_1_EXCLUDED_MODULE_TYPES.has(m.type))
	);
	return {
		...HOME_1_LANDING_DEFAULTS,
		hero,
		modules,
	};
}

export function resolveHome1Landing(
	raw: Partial<Home1LandingConfig> | null | undefined,
	homepage: HomepageConfig
): Home1LandingConfig {
	const base = buildHome1FromHomepage(homepage);
	if (!raw) return base;

	const hero = raw.hero ? patchHero({ ...base.hero, ...raw.hero }) : base.hero;
	const modules =
		raw.modules && raw.modules.length > 0
			? patchModules(cloneModules(raw.modules))
			: base.modules;

	return {
		bridge_hosts: raw.bridge_hosts?.length ? [...raw.bridge_hosts] : base.bridge_hosts,
		announcement_bar_enabled:
			raw.announcement_bar_enabled ?? base.announcement_bar_enabled,
		announcement_bar_items: normalizeHome1AnnouncementItems(
			raw.announcement_bar_items?.length ? raw.announcement_bar_items : base.announcement_bar_items
		),
		hero,
		modules,
	};
}
