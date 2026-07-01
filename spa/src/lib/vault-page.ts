import type {
	CustomPage,
	HomepageModule,
	ReviewsListicleModuleConfig,
	TextBlockModuleConfig,
	VaultHeroModuleConfig,
	VaultQualityTabsModuleConfig,
	VaultQualityVerifyModuleConfig,
	VaultWhyChooseModuleConfig,
	VaultCtaModuleConfig,
} from '$lib/config.svelte';

export const VAULT_HERO_DEFAULTS: VaultHeroModuleConfig = {
	headline: 'Quality You Can Verify, Not Just Trust',
	stats: [
		{ label: '99%+ Purity Guaranteed' },
		{ label: '5 Quality Checks' },
		{ label: '100% US Verified' },
	],
	cta_text: 'Browse the Vault →',
	cta_href: '/shop',
	bg_image: '',
	vial_primary: '',
	vial_primary_alt: '',
	vial_secondary: '',
	vial_secondary_alt: '',
	vial_tertiary: '',
	vial_tertiary_alt: '',
};

export const VAULT_QUALITY_TABS_DEFAULTS: VaultQualityTabsModuleConfig = {
	section_title: 'The Alyve Vault Guarantee',
	section_subtitle:
		'Documented quality for research and laboratory use. Every batch meets our internal purity standards.',
	product_image: '',
	product_image_alt: '',
	image_badge: '99.4% Purity — Verified by HPLC',
	panel_bg: '#ebe6f5',
	guarantee_cards: [
		{
			title: '99% Purity Guaranteed',
			description: 'Every batch verified',
			tooltip: '',
			accent: 'green',
			icon: 'purity',
		},
		{
			title: 'Shipment Protection',
			description: 'Every order fully covered',
			tooltip: 'Full replacement or refund if your shipment is lost or damaged in transit.',
			accent: 'blue',
			icon: 'shipping',
		},
		{
			title: 'COA with Every Batch',
			description: 'Third Party tested in America',
			tooltip:
				'Independent U.S. lab Certificates of Analysis ship with every order and are published before purchase.',
			accent: 'yellow',
			icon: 'coa',
		},
	],
};

export const VAULT_QUALITY_VERIFY_DEFAULTS: VaultQualityVerifyModuleConfig = {
	section_title: 'Quality You Can Verify, Not Just Trust',
	section_subtitle:
		'Every batch is independently tested and documented. Review the data before you buy — not after.',
	product_image: '',
	product_image_alt: '',
	purity_badge_title: '99.4% Purity',
	purity_badge_subtitle: 'Verified by HPLC',
	panel_bg: '#e8eef5',
	proof_link_title: 'See the Proof',
	proof_link_subtitle: 'View our quality procedures',
	proof_link_href: '/coa-library',
	shop_cta_text: 'Shop Now →',
	shop_cta_href: '/shop',
	trust_note: 'Free COA included with every order',
	stats: [
		{ value: '99%+', label: 'Purity Guaranteed' },
		{ value: '5', label: 'Quality Checks' },
		{ value: '100%', label: 'U.S. Verified' },
	],
	tabs: [
		{
			title: 'Purity',
			summary: 'HPLC ≥99%',
			body: '<p>Every batch is verified by High-Performance Liquid Chromatography (HPLC) to confirm peptide purity meets or exceeds 99%. Chromatogram peaks and purity percentages are published on every Certificate of Analysis before release.</p>',
			why_matters: 'Impurities can skew receptor binding and invalidate your study data.',
			chart_image: '',
		},
		{
			title: 'Identity',
			summary: 'Mass Spec confirmed',
			body: '<p>Mass spectrometry confirms the molecular weight and sequence identity of each peptide lot before release — verifying you receive the exact compound specified, not a mislabeled analog.</p>',
			why_matters: 'Ensures you receive the exact compound specified — not a mislabeled analog.',
			chart_image: '',
		},
		{
			title: 'Endotoxin',
			summary: 'LAL tested, pharma-grade low',
			body: '<p>Limulus Amebocyte Lysate (LAL) testing verifies endotoxin levels meet pharmaceutical-grade thresholds.</p>',
			why_matters:
				'Elevated endotoxins can trigger immune responses that confound in vitro and in vivo results.',
			chart_image: '',
		},
		{
			title: 'Stability',
			summary: 'Lyophilized for shelf life',
			body: '<p>Peptides are lyophilized under controlled conditions to maximize stability during storage and transit, preserving bioactivity from synthesis to your bench.</p>',
			why_matters: 'Proper lyophilization preserves bioactivity from synthesis to your bench.',
			chart_image: '',
		},
		{
			title: 'Consistency',
			summary: 'Batch-to-batch variance data',
			body: '<p>We publish lot-to-lot analytical data so you can compare batches across your study timeline and maintain reproducible research outcomes.</p>',
			why_matters: 'Reproducible research requires predictable material from order to order.',
			chart_image: '',
		},
	],
};

const VAULT_QUALITY_VERIFY_CANONICAL_TITLES = [
	'Purity',
	'Identity',
	'Endotoxin',
	'Stability',
	'Consistency',
] as const;

function normalizeTabTitle(title: string): string {
	return title.trim().toLowerCase();
}

function vaultQualityVerifyTabsNeedMigration(
	tabs: Array<{ title?: string | null }>
): boolean {
	const titles = tabs.map((tab) => normalizeTabTitle(tab.title ?? '')).filter(Boolean);
	if (titles.some((title) => title === 'potency' || title === 'safety')) return true;
	return !VAULT_QUALITY_VERIFY_CANONICAL_TITLES.every((title) =>
		titles.includes(title.toLowerCase())
	);
}

function migrateVaultQualityVerifyTabs(
	savedTabs: Array<{
		title?: string | null;
		chart_image?: string | null;
	}>
): NonNullable<VaultQualityVerifyModuleConfig['tabs']> {
	const canonical =
		VAULT_QUALITY_VERIFY_DEFAULTS.tabs?.map((tab) => ({ ...tab })) ?? [];
	let chartImage = '';
	for (const tab of savedTabs) {
		const img = tab.chart_image?.trim() ?? '';
		if (!img) continue;
		const title = normalizeTabTitle(tab.title ?? '');
		if (title === 'potency' || title === 'purity' || !chartImage) {
			chartImage = img;
		}
	}
	if (chartImage && canonical[0]) {
		canonical[0].chart_image = chartImage;
	}
	return canonical;
}

export const VAULT_CTA_DEFAULTS: VaultCtaModuleConfig = {
	headline_prefix: 'Ready to Verify? Browse the',
	headline_accent: 'Research Vault.',
	primary_cta_text: 'Browse Catalog →',
	primary_cta_href: '/shop',
	secondary_cta_text: 'View COA Library',
	secondary_cta_href: '/coa-library',
};

export const VAULT_WHY_CHOOSE_DEFAULTS: VaultWhyChooseModuleConfig = {
	section_title: 'Why Choose Alyve',
	items: [
		{
			title: 'Always In Stock',
			description:
				'Core research compounds restocked on a reliable cadence — your protocol stays on schedule.',
			icon: 'stock',
			accent: 'violet',
		},
		{
			title: 'Volume Pricing',
			description:
				'Transparent tiered discounts from 3 vials up — scale your order and save on every batch.',
			icon: 'volume',
			accent: 'green',
		},
		{
			title: 'Safe & Protected Shipping',
			description: 'Tracked domestic fulfillment with shipment protection on every order.',
			icon: 'shipping',
			accent: 'amber',
		},
		{
			title: 'Third-Party Verified',
			description:
				'Independent U.S. laboratory testing confirms identity, purity, and safety before release.',
			icon: 'verified',
			accent: 'rose',
		},
		{
			title: 'COA Every Batch',
			description:
				'Full Certificates of Analysis published for every lot — review documentation before you buy.',
			icon: 'coa',
			accent: 'blue',
		},
		{
			title: 'Same-Day Fulfillment',
			description: 'Orders placed before 2PM EST ship same day via tracked carrier.',
			icon: 'fulfillment',
			accent: 'teal',
		},
	],
};

/** Same brand comparison block used on the homepage / Why Alyve page. */
export const VAULT_COMPARISON_DEFAULTS: TextBlockModuleConfig = {
	layout: 'comparison',
	title: '',
	headline: 'Alyve vs Grey-Market Sites',
	content:
		'<p>How verified U.S. batches stack up against generic peptide sellers and overseas grey-market sources.</p>',
	brand_name: 'Alyve',
	competitor_name: 'Generic Peptide Sites',
	competitor_name_2: 'Overseas / Grey-Market',
	brand_logo: '',
	competitor_logo: '',
	comparison_rows: [
		{
			heading: '🧬 Endotoxin Testing',
			brand: 'LAB tested every batch, pharma-grade low',
			competitor: 'Skipped entirely',
			competitor_2: 'Unknown, never tested',
		},
		{
			heading: '🧪 Purity',
			brand: '99%+ HPLC-verified at manufacture',
			competitor: 'Estimated, not proven',
			competitor_2: 'Label claim only',
		},
		{
			heading: '📄 Third-Party Verification',
			brand: "Accredited labs, COA per batch, test it yourself and we'll reimburse",
			competitor: 'In-house claims only',
			competitor_2: 'Redacted or none',
		},
		{
			heading: '🚚 Shipping',
			brand: 'Same-day, tracked, discreet, 2–3 days',
			competitor: 'Slow, sometimes tracked',
			competitor_2: '2–6 weeks, customs risk',
		},
	],
};

function vaultComparisonModuleSeed(): HomepageModule & { type: 'text_block' } {
	return {
		type: 'text_block',
		visibility: 'all',
		spacing_v: 'normal',
		spacing_h: 'normal',
		center_header: true,
		config: {
			...VAULT_COMPARISON_DEFAULTS,
			comparison_rows: VAULT_COMPARISON_DEFAULTS.comparison_rows?.map((row) => ({ ...row })),
		},
	};
}

function moduleIsVaultComparison(mod: HomepageModule | undefined): boolean {
	if (mod?.type !== 'text_block') return false;
	return (mod.config as TextBlockModuleConfig).layout === 'comparison';
}

function vaultHeroModuleSeed(): HomepageModule & { type: 'vault_hero' } {
	return {
		type: 'vault_hero',
		visibility: 'all',
		spacing_v: 'normal',
		spacing_h: 'normal',
		config: {
			...VAULT_HERO_DEFAULTS,
			stats: VAULT_HERO_DEFAULTS.stats?.map((s) => ({ ...s })),
		},
	};
}

function vaultQualityTabsModuleSeed(): HomepageModule & { type: 'vault_quality_tabs' } {
	return {
		type: 'vault_quality_tabs',
		visibility: 'all',
		spacing_v: 'normal',
		spacing_h: 'normal',
		config: {
			...VAULT_QUALITY_TABS_DEFAULTS,
			guarantee_cards: VAULT_QUALITY_TABS_DEFAULTS.guarantee_cards?.map((card) => ({ ...card })),
		},
	};
}

function vaultQualityVerifyModuleSeed(): HomepageModule & { type: 'vault_quality_verify' } {
	return {
		type: 'vault_quality_verify',
		visibility: 'all',
		spacing_v: 'normal',
		spacing_h: 'normal',
		config: {
			...VAULT_QUALITY_VERIFY_DEFAULTS,
			stats: VAULT_QUALITY_VERIFY_DEFAULTS.stats?.map((row) => ({ ...row })),
			tabs: VAULT_QUALITY_VERIFY_DEFAULTS.tabs?.map((t) => ({ ...t })),
		},
	};
}

function vaultWhyChooseModuleSeed(): HomepageModule & { type: 'vault_why_choose' } {
	return {
		type: 'vault_why_choose',
		visibility: 'all',
		spacing_v: 'normal',
		spacing_h: 'normal',
		config: {
			...VAULT_WHY_CHOOSE_DEFAULTS,
			items: VAULT_WHY_CHOOSE_DEFAULTS.items?.map((item) => ({ ...item })),
		},
	};
}

const VAULT_REVIEWS_LISTICLE_FALLBACK: ReviewsListicleModuleConfig = {
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
};

function cloneReviewsListicleConfig(
	cfg: ReviewsListicleModuleConfig | null | undefined
): ReviewsListicleModuleConfig {
	const items = (cfg?.items ?? [])
		.map((item) => ({
			quote: item.quote?.trim() ?? '',
			name: item.name?.trim() ?? '',
			product: item.product?.trim() ?? '',
			location: item.location?.trim() ?? '',
			title: item.title?.trim() ?? '',
			rating: item.rating ?? 5,
		}))
		.filter((item) => item.quote && item.name);
	return {
		headline: cfg?.headline?.trim() || VAULT_REVIEWS_LISTICLE_FALLBACK.headline,
		subheadline: cfg?.subheadline?.trim() ?? '',
		proof_headline: cfg?.proof_headline?.trim() ?? '',
		proof_subheadline:
			cfg?.proof_subheadline?.trim() || VAULT_REVIEWS_LISTICLE_FALLBACK.proof_subheadline,
		items: items.length ? items : VAULT_REVIEWS_LISTICLE_FALLBACK.items?.map((item) => ({ ...item })),
		proof_items: cfg?.proof_items?.map((item) => ({ ...item })),
		marquee_headline: cfg?.marquee_headline?.trim() ?? '',
		marquee_items: cfg?.marquee_items?.map((item) => ({ ...item })),
	};
}

function vaultCtaModuleSeed(): HomepageModule & { type: 'vault_cta' } {
	return {
		type: 'vault_cta',
		visibility: 'all',
		spacing_v: 'normal',
		spacing_h: 'normal',
		config: { ...VAULT_CTA_DEFAULTS },
	};
}

function findHomepageReviewsListicle(
	homepageModules: HomepageModule[] | undefined
): (HomepageModule & { type: 'reviews_listicle' }) | null {
	for (const mod of homepageModules ?? []) {
		if (mod?.type === 'reviews_listicle') {
			return mod;
		}
	}
	return null;
}

function vaultReviewsListicleModuleSeed(
	homepageModules?: HomepageModule[]
): HomepageModule & { type: 'reviews_listicle' } {
	const fromHome = findHomepageReviewsListicle(homepageModules);
	const cfg = fromHome?.config as ReviewsListicleModuleConfig | undefined;
	return {
		id: 'vault-reviews',
		type: 'reviews_listicle',
		visibility: fromHome?.visibility ?? 'all',
		spacing_v: fromHome?.spacing_v ?? 'normal',
		spacing_h: fromHome?.spacing_h ?? 'normal',
		center_header: fromHome?.center_header ?? true,
		config: cloneReviewsListicleConfig(cfg),
	};
}

function moduleIsVaultReviewsListicle(mod: HomepageModule | undefined): boolean {
	return mod?.type === 'reviews_listicle';
}

function stripVaultReviewSlider(modules: HomepageModule[]): HomepageModule[] {
	return modules.filter((mod) => {
		const type = (mod as { type?: string })?.type;
		return type !== 'review_slider' && type !== 'vault_featured_products';
	});
}

function ensureVaultReviewsListiclePosition(modules: HomepageModule[]): HomepageModule[] {
	const reviewIdx = modules.findIndex((m) => moduleIsVaultReviewsListicle(m));
	const whyIdx = modules.findIndex((m) => m?.type === 'vault_why_choose');
	if (reviewIdx === -1 || whyIdx === -1) return modules;
	const targetIdx = whyIdx + 1;
	if (reviewIdx === targetIdx) return modules;
	const next = [...modules];
	const [review] = next.splice(reviewIdx, 1);
	const whyAfter = next.findIndex((m) => m?.type === 'vault_why_choose');
	next.splice(whyAfter + 1, 0, review);
	return next;
}

export function resolveVaultTestimonialsModule(
	pageModules: HomepageModule[] | undefined,
	homepageModules: HomepageModule[] | undefined
): (HomepageModule & { type: 'reviews_listicle' }) | null {
	const fromPage = pageModules?.find((mod) => mod?.type === 'reviews_listicle');
	if (fromPage?.type === 'reviews_listicle') {
		const items = fromPage.config.items ?? [];
		if (items.length > 0) {
			return {
				...fromPage,
				config: cloneReviewsListicleConfig(fromPage.config),
			};
		}
	}
	const fromHome = findHomepageReviewsListicle(homepageModules);
	if (fromHome) {
		return vaultReviewsListicleModuleSeed(homepageModules);
	}
	return vaultReviewsListicleModuleSeed();
}

export function mergeVaultHeroConfig(
	cfg: Partial<VaultHeroModuleConfig> | null | undefined
): VaultHeroModuleConfig {
	const merged = { ...VAULT_HERO_DEFAULTS, ...(cfg ?? {}) };
	const stats = (merged.stats ?? [])
		.map((row) => ({ label: row.label?.trim() ?? '' }))
		.filter((row) => row.label);
	return {
		...merged,
		stats: stats.length ? stats : VAULT_HERO_DEFAULTS.stats?.map((s) => ({ ...s })) ?? [],
	};
}

export function mergeVaultQualityTabsConfig(
	cfg: Partial<VaultQualityTabsModuleConfig> | null | undefined
): VaultQualityTabsModuleConfig {
	const merged = { ...VAULT_QUALITY_TABS_DEFAULTS, ...(cfg ?? {}) };
	const guaranteeCards = (merged.guarantee_cards ?? [])
		.map((card) => {
			let title = card.title?.trim() ?? '';
			if (title === 'CoA with Every Batch') title = 'COA with Every Batch';
			return {
				title,
				description: card.description?.trim() ?? '',
				tooltip: card.tooltip?.trim() ?? '',
				accent: card.accent?.trim() || 'green',
				icon: card.icon?.trim() || 'purity',
			};
		})
		.filter((card) => card.title);
	return {
		...merged,
		guarantee_cards: guaranteeCards.length
			? guaranteeCards
			: VAULT_QUALITY_TABS_DEFAULTS.guarantee_cards?.map((card) => ({ ...card })) ?? [],
	};
}

export function mergeVaultQualityVerifyConfig(
	cfg: Partial<VaultQualityVerifyModuleConfig> | null | undefined
): VaultQualityVerifyModuleConfig {
	const merged = { ...VAULT_QUALITY_VERIFY_DEFAULTS, ...(cfg ?? {}) };
	const rawTabs = (merged.tabs ?? [])
		.map((tab) => ({
			title: tab.title?.trim() ?? '',
			summary: tab.summary?.trim() ?? '',
			body: tab.body?.trim() ?? '',
			why_matters: tab.why_matters?.trim() ?? '',
			chart_image: tab.chart_image?.trim() ?? '',
		}))
		.filter((tab) => tab.title);
	const tabs = rawTabs.length
		? vaultQualityVerifyTabsNeedMigration(rawTabs)
			? migrateVaultQualityVerifyTabs(rawTabs)
			: rawTabs
		: VAULT_QUALITY_VERIFY_DEFAULTS.tabs?.map((t) => ({ ...t })) ?? [];
	const stats = (merged.stats ?? [])
		.map((row) => ({
			value: row.value?.trim() ?? '',
			label: row.label?.trim() ?? '',
		}))
		.filter((row) => row.value && row.label);
	const purityBadgeTitle =
		merged.purity_badge_title?.trim() === '99%+ Purity'
			? VAULT_QUALITY_VERIFY_DEFAULTS.purity_badge_title
			: merged.purity_badge_title?.trim() ||
				VAULT_QUALITY_VERIFY_DEFAULTS.purity_badge_title;
	return {
		...merged,
		purity_badge_title: purityBadgeTitle,
		stats: stats.length
			? stats
			: VAULT_QUALITY_VERIFY_DEFAULTS.stats?.map((row) => ({ ...row })) ?? [],
		tabs,
	};
}

export function mergeVaultCtaConfig(
	cfg: Partial<VaultCtaModuleConfig> | null | undefined
): VaultCtaModuleConfig {
	const merged = { ...VAULT_CTA_DEFAULTS, ...(cfg ?? {}) };
	return {
		headline_prefix: merged.headline_prefix?.trim() || VAULT_CTA_DEFAULTS.headline_prefix,
		headline_accent: merged.headline_accent?.trim() || VAULT_CTA_DEFAULTS.headline_accent,
		primary_cta_text: merged.primary_cta_text?.trim() || VAULT_CTA_DEFAULTS.primary_cta_text,
		primary_cta_href: merged.primary_cta_href?.trim() || VAULT_CTA_DEFAULTS.primary_cta_href,
		secondary_cta_text:
			merged.secondary_cta_text?.trim() || VAULT_CTA_DEFAULTS.secondary_cta_text,
		secondary_cta_href:
			merged.secondary_cta_href?.trim() || VAULT_CTA_DEFAULTS.secondary_cta_href,
	};
}

export function mergeVaultWhyChooseConfig(
	cfg: Partial<VaultWhyChooseModuleConfig> | null | undefined
): VaultWhyChooseModuleConfig {
	const merged = { ...VAULT_WHY_CHOOSE_DEFAULTS, ...(cfg ?? {}) };
	const items = (merged.items ?? [])
		.map((row) => ({
			title: row.title?.trim() ?? '',
			description: row.description?.trim() ?? '',
			icon: row.icon?.trim() || 'stock',
			accent: row.accent?.trim() || 'violet',
		}))
		.filter((row) => row.title);
	return {
		...merged,
		section_title: merged.section_title?.trim() || VAULT_WHY_CHOOSE_DEFAULTS.section_title,
		items: items.length
			? items
			: VAULT_WHY_CHOOSE_DEFAULTS.items?.map((item) => ({ ...item })) ?? [],
	};
}

export function mergeVaultPages(
	pages: CustomPage[],
	homepageModules?: HomepageModule[]
): CustomPage[] {
	return pages.map((page) => {
		if (page.slug?.replace(/\/$/, '') !== 'vault') return page;
		let modules = stripVaultReviewSlider([...(page.modules ?? [])]);
		const heroIdx = modules.findIndex((m) => m?.type === 'vault_hero');
		if (heroIdx === -1) {
			modules.unshift(vaultHeroModuleSeed());
		} else {
			const mod = modules[heroIdx];
			if (mod?.type === 'vault_hero') {
				modules[heroIdx] = {
					...mod,
					visibility: mod.visibility ?? 'all',
					config: mergeVaultHeroConfig(mod.config),
				};
			}
		}
		const tabsIdx = modules.findIndex((m) => m?.type === 'vault_quality_tabs');
		if (tabsIdx === -1) {
			const insertAt = modules.findIndex((m) => m?.type === 'vault_hero') + 1 || 1;
			modules.splice(insertAt, 0, vaultQualityTabsModuleSeed());
		} else {
			const mod = modules[tabsIdx];
			if (mod?.type === 'vault_quality_tabs') {
				modules[tabsIdx] = {
					...mod,
					visibility: mod.visibility ?? 'all',
					config: mergeVaultQualityTabsConfig(mod.config),
				};
			}
		}
		const verifyIdx = modules.findIndex((m) => m?.type === 'vault_quality_verify');
		if (verifyIdx === -1) {
			const insertAt =
				modules.findIndex((m) => m?.type === 'vault_quality_tabs') + 1 ||
				modules.findIndex((m) => m?.type === 'vault_hero') + 2 ||
				2;
			modules.splice(insertAt, 0, vaultQualityVerifyModuleSeed());
		} else {
			const mod = modules[verifyIdx];
			if (mod?.type === 'vault_quality_verify') {
				modules[verifyIdx] = {
					...mod,
					visibility: mod.visibility ?? 'all',
					config: mergeVaultQualityVerifyConfig(mod.config),
				};
			}
		}
		const compareIdx = modules.findIndex((m) => moduleIsVaultComparison(m));
		if (compareIdx === -1) {
			const insertAt =
				modules.findIndex((m) => m?.type === 'vault_quality_verify') + 1 ||
				modules.findIndex((m) => m?.type === 'vault_quality_tabs') + 1 ||
				modules.findIndex((m) => m?.type === 'vault_hero') + 2 ||
				2;
			modules.splice(insertAt, 0, vaultComparisonModuleSeed());
		} else {
			const mod = modules[compareIdx];
			if (mod?.type === 'text_block') {
				const cfg = mod.config as TextBlockModuleConfig;
				modules[compareIdx] = {
					...mod,
					center_header: mod.center_header ?? true,
					config: {
						...VAULT_COMPARISON_DEFAULTS,
						...cfg,
						title: VAULT_COMPARISON_DEFAULTS.title,
						headline: cfg.headline?.trim() || VAULT_COMPARISON_DEFAULTS.headline,
						comparison_rows: cfg.comparison_rows?.length
							? cfg.comparison_rows
							: VAULT_COMPARISON_DEFAULTS.comparison_rows,
					},
				};
			}
		}
		const whyIdx = modules.findIndex((m) => m?.type === 'vault_why_choose');
		if (whyIdx === -1) {
			const insertAt =
				modules.findIndex((m) => moduleIsVaultComparison(m)) + 1 ||
				modules.findIndex((m) => m?.type === 'vault_quality_verify') + 2 ||
				modules.findIndex((m) => m?.type === 'vault_quality_tabs') + 2 ||
				3;
			modules.splice(insertAt, 0, vaultWhyChooseModuleSeed());
		} else {
			const mod = modules[whyIdx];
			if (mod?.type === 'vault_why_choose') {
				modules[whyIdx] = {
					...mod,
					visibility: mod.visibility ?? 'all',
					config: mergeVaultWhyChooseConfig(mod.config),
				};
			}
		}
		const reviewIdx = modules.findIndex((m) => moduleIsVaultReviewsListicle(m));
		if (reviewIdx === -1) {
			const whyIdx = modules.findIndex((m) => m?.type === 'vault_why_choose');
			const insertAt =
				whyIdx !== -1
					? whyIdx + 1
					: modules.findIndex((m) => moduleIsVaultComparison(m)) + 1 ||
						modules.findIndex((m) => m?.type === 'vault_quality_verify') + 1 ||
						modules.length;
			modules.splice(insertAt, 0, vaultReviewsListicleModuleSeed(homepageModules));
		} else {
			const mod = modules[reviewIdx];
			if (mod?.type === 'reviews_listicle') {
				const seed = vaultReviewsListicleModuleSeed(homepageModules);
				modules[reviewIdx] = {
					...mod,
					visibility: mod.visibility ?? seed.visibility,
					spacing_v: mod.spacing_v ?? seed.spacing_v,
					spacing_h: mod.spacing_h ?? seed.spacing_h,
					center_header: mod.center_header ?? seed.center_header,
					config: cloneReviewsListicleConfig({
						...seed.config,
						...mod.config,
						items:
							mod.config.items?.length && mod.config.items.length > 0
								? mod.config.items
								: seed.config.items,
					}),
				};
			}
		}
		modules = ensureVaultReviewsListiclePosition(modules);
		const ctaIdx = modules.findIndex((m) => m?.type === 'vault_cta');
		if (ctaIdx === -1) {
			modules.push(vaultCtaModuleSeed());
		} else {
			const mod = modules[ctaIdx];
			if (mod?.type === 'vault_cta') {
				modules[ctaIdx] = {
					...mod,
					visibility: mod.visibility ?? 'all',
					config: mergeVaultCtaConfig(mod.config),
				};
			}
		}
		return {
			...page,
			title: page.title?.trim() || 'Vault',
			modules,
		};
	});
}
