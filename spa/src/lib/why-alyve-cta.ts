import type { CustomPage, ListicleModuleConfig, PromoOfferModuleConfig } from '$lib/config.svelte';
import { isModuleVisibleNow } from '$lib/config.svelte';

export type WhyAlyveCta = {
	label: string;
	href: string;
};

const DEFAULT_CTA: WhyAlyveCta = {
	label: 'Shop Now — Check Availability',
	href: '/shop',
};

export function getWhyAlyveCta(pages: CustomPage[] | undefined): WhyAlyveCta | null {
	const pageData = pages?.find((p) => p.slug === 'why-alyve');
	if (!pageData) return null;

	for (const mod of pageData.modules ?? []) {
		if (mod.type !== 'listicle' || !isModuleVisibleNow(mod)) continue;
		const cfg = mod.config as ListicleModuleConfig;
		const label = cfg.hero_cta_label?.trim();
		const href = cfg.hero_cta_href?.trim();
		if (label && href) return { label, href };
	}

	for (const mod of pageData.modules ?? []) {
		if (mod.type !== 'promo_offer' || !isModuleVisibleNow(mod)) continue;
		const cfg = mod.config as PromoOfferModuleConfig;
		const label = cfg.cta_label?.trim();
		const href = cfg.cta_href?.trim();
		if (label && href) return { label, href };
	}

	return DEFAULT_CTA;
}
