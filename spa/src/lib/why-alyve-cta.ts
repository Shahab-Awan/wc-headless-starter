import type { CustomPage, PromoOfferModuleConfig } from '$lib/config.svelte';
import { isModuleVisibleNow } from '$lib/config.svelte';

export type WhyAlyveCta = {
	label: string;
	href: string;
};

export function getWhyAlyveCta(pages: CustomPage[] | undefined): WhyAlyveCta | null {
	const pageData = pages?.find((p) => p.slug === 'why-alyve');
	if (!pageData) return null;

	for (const mod of pageData.modules ?? []) {
		if (mod.type !== 'promo_offer' || !isModuleVisibleNow(mod)) continue;
		const cfg = mod.config as PromoOfferModuleConfig;
		const label = cfg.cta_label?.trim();
		const href = cfg.cta_href?.trim();
		if (label && href) return { label, href };
	}

	return { label: 'Check Availability', href: '/shop' };
}
