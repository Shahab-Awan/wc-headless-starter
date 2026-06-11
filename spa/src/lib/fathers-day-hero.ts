import type { HomepageHeroConfig } from '$lib/config.svelte';

export const FATHERS_DAY_HERO_CONTENT = {
	tagline: 'Celebrate. Honor. Save.',
	headlinePrimary: "Father's Day",
	headlineSecondary: 'Sale',
	ribbon: 'Save Up To 40%',
	kicker: 'Premium Research Compounds',
	subheadline:
		'High-purity, lyophilized compounds backed by strict quality standards and full third-party testing.',
	trustItems: [
		{ icon: 'shield', text: 'Premium quality' },
		{ icon: 'lab', text: 'Third-party tested' },
		{ icon: 'lock', text: 'Secure shipping' },
		{ icon: 'award', text: 'Trusted by researchers' },
	],
	ctaText: 'Browse Catalog',
	limitedTime: 'Limited time event',
	announcement: "🎉 Father's Day Sale | Save Up to 40%  Ends Soon",
	cartSavingsLabel: "Father's Day saving",
	pdpShipsTimerBefore: "Father's Day offer — order within ",
	pdpShipsTimerAfter: ' to save up to 40%',
} as const;

export function applyFathersDayHero(hero: HomepageHeroConfig): HomepageHeroConfig {
	const c = FATHERS_DAY_HERO_CONTENT;
	return {
		...hero,
		show_eyebrow: false,
		show_rating: false,
		headline: `${c.headlinePrimary} ${c.headlineSecondary}`,
		headline_size: 'l',
		subheadline: c.subheadline,
		cta_text: c.ctaText,
		show_cta: true,
		trust_items: c.trustItems.map((item) => ({ ...item })),
	};
}
