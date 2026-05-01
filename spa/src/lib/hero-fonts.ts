/**
 * Hero font registry — maps the admin `headline_font` key to a Bunny CDN
 * stylesheet URL and a CSS font-family stack.
 *
 * The selected font is loaded dynamically in the layout's onMount so we
 * only pay the network cost when the admin actually picked something
 * other than Inter (Inter is already loaded in app.html for the base UI).
 *
 * Bunny serves the same catalogue as Google Fonts with a GDPR-clean origin.
 * Weight lists here match the admin-facing `HeroTextWeight` options so any
 * weight the user picks is available after the link loads.
 */

import type { HeroFontKey } from '$lib/config.svelte';

export type HeroFontSpec = {
	label: string;
	/** CSS font-family value to apply to hero elements. */
	family: string;
	/** Bunny stylesheet URL — empty when the font is already bundled. */
	href: string;
};

export const HERO_FONTS: Record<HeroFontKey, HeroFontSpec> = {
	inter: {
		label: 'Inter',
		family: "'Inter', system-ui, -apple-system, Segoe UI, Roboto, sans-serif",
		// Already loaded by app.html for body copy — extend weights inline there.
		href: '',
	},
	barlow: {
		label: 'Barlow Semi Condensed',
		family: "'Barlow Semi Condensed', system-ui, sans-serif",
		href: 'https://fonts.bunny.net/css?family=barlow-semi-condensed:500,600,700,800,900&display=swap',
	},
	bebas: {
		label: 'Bebas Neue',
		family: "'Bebas Neue', system-ui, sans-serif",
		href: 'https://fonts.bunny.net/css?family=bebas-neue:400&display=swap',
	},
	playfair: {
		label: 'Playfair Display',
		family: "'Playfair Display', Georgia, serif",
		href: 'https://fonts.bunny.net/css?family=playfair-display:400,500,600,700,800,900&display=swap',
	},
	space_grotesk: {
		label: 'Space Grotesk',
		family: "'Space Grotesk', system-ui, sans-serif",
		href: 'https://fonts.bunny.net/css?family=space-grotesk:300,400,500,600,700&display=swap',
	},
	archivo: {
		label: 'Archivo',
		family: "'Archivo', system-ui, sans-serif",
		href: 'https://fonts.bunny.net/css?family=archivo:300,400,500,600,700,800,900&display=swap',
	},
	oswald: {
		label: 'Oswald',
		family: "'Oswald', system-ui, sans-serif",
		href: 'https://fonts.bunny.net/css?family=oswald:300,400,500,600,700&display=swap',
	},
};

const loadedFonts = new Set<string>();

/**
 * Lazy-inject the Bunny <link> for a font by key. Idempotent — deduplicates
 * across multiple callers (hero font, global heading font, global body font).
 * No-op when Inter (bundled) or when already on the page.
 */
export function loadFont(key: string | undefined | null): void {
	if (typeof document === 'undefined' || !key) return;
	const spec = HERO_FONTS[key as HeroFontKey];
	if (!spec || !spec.href || loadedFonts.has(key)) return;
	if (document.querySelector(`link[data-wchs-font="${key}"]`)) {
		loadedFonts.add(key);
		return;
	}
	const link = document.createElement('link');
	link.rel = 'stylesheet';
	link.href = spec.href;
	link.setAttribute('data-wchs-font', key);
	document.head.appendChild(link);
	loadedFonts.add(key);
}

/** Backward-compat alias. */
export const loadHeroFont = loadFont;
