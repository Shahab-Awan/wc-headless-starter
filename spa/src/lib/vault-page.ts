import type {
	CustomPage,
	HomepageModule,
	VaultHeroModuleConfig,
	VaultQualityTabsModuleConfig,
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
		'Every batch is independently verified — purity, identity, endotoxin, stability, and consistency.',
	product_image: '',
	product_image_alt: '',
	image_badge: '99.4% Purity — Verified by HPLC',
	panel_bg: '#ebe6f5',
	detail_cta_text: 'See the Proof → View COA Library',
	detail_cta_href: '/coa-library',
	tabs: [
		{
			title: 'Purity',
			summary: 'HPLC ≥99%',
			body: '<p>Every batch is verified by High-Performance Liquid Chromatography (HPLC) to confirm peptide purity meets or exceeds 99%.</p>',
			why_matters: 'Impurities can skew receptor binding and invalidate your study data.',
			chart_image: '',
		},
		{
			title: 'Identity',
			summary: 'Mass Spec confirmed',
			body: '<p>Mass spectrometry confirms the molecular weight and sequence identity of each peptide lot before release.</p>',
			why_matters: 'Ensures you receive the exact compound specified — not a mislabeled analog.',
			chart_image: '',
		},
		{
			title: 'Endotoxin',
			summary: 'LAL tested, pharma-grade low',
			body: '<p>Limulus Amebocyte Lysate (LAL) testing verifies endotoxin levels meet pharmaceutical-grade thresholds.</p>',
			why_matters: 'Elevated endotoxins can trigger immune responses that confound in vitro and in vivo results.',
			chart_image: '',
		},
		{
			title: 'Stability',
			summary: 'Lyophilized for shelf life',
			body: '<p>Peptides are lyophilized under controlled conditions to maximize stability during storage and transit.</p>',
			why_matters: 'Proper lyophilization preserves bioactivity from synthesis to your bench.',
			chart_image: '',
		},
		{
			title: 'Consistency',
			summary: 'Batch-to-batch variance data',
			body: '<p>We publish lot-to-lot analytical data so you can compare batches across your study timeline.</p>',
			why_matters: 'Reproducible research requires predictable material from order to order.',
			chart_image: '',
		},
	],
};

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
			tabs: VAULT_QUALITY_TABS_DEFAULTS.tabs?.map((t) => ({ ...t })),
		},
	};
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
	const tabs = (merged.tabs ?? [])
		.map((tab) => ({
			title: tab.title?.trim() ?? '',
			summary: tab.summary?.trim() ?? '',
			body: tab.body?.trim() ?? '',
			why_matters: tab.why_matters?.trim() ?? '',
			chart_image: tab.chart_image?.trim() ?? '',
		}))
		.filter((tab) => tab.title);
	return {
		...merged,
		tabs: tabs.length ? tabs : VAULT_QUALITY_TABS_DEFAULTS.tabs?.map((t) => ({ ...t })) ?? [],
	};
}

export function mergeVaultPages(pages: CustomPage[]): CustomPage[] {
	return pages.map((page) => {
		if (page.slug?.replace(/\/$/, '') !== 'vault') return page;
		const modules = [...(page.modules ?? [])];
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
		return {
			...page,
			title: page.title?.trim() || 'Vault',
			modules,
		};
	});
}
