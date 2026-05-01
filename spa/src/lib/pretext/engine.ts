/**
 * PretextEngine — singleton wrapping @chenglou/pretext for deterministic
 * text measurement across the SPA.
 *
 * This is the single entry point for text measurement. All callers go
 * through this — never call prepare() or layout() directly in components.
 * Reasons:
 *
 *   1. Caching — prepare() is ~0.038ms per call but we call it for every
 *      card on every render, and a 100-product shop page * 3 text nodes
 *      per card adds up. We cache by (text, variant) key.
 *
 *   2. Theme changes must invalidate — when the font stack changes
 *      (theme, locale, font loading) the cached PreparedText is stale.
 *      invalidate() clears it all at once.
 *
 *   3. Font loading gate — prepare() before document.fonts.ready
 *      measures against fallback fonts. ready() is a promise every
 *      caller awaits before their first prepare.
 *
 * USAGE in a component ($derived for reactivity, cached via the engine):
 *
 *   import { pretext } from '$lib/pretext/engine';
 *
 *   const titleLayout = $derived.by(() => {
 *     const prepared = pretext.prepare(product.name, 'title');
 *     return pretext.layout(prepared, cardWidth - 32, 20); // lineHeight in px
 *   });
 *
 *   // titleLayout.height is the computed pixel height for the title
 *   // wrapped at cardWidth - 32. Apply as inline style to prevent CLS.
 */

import { prepare, layout, type PreparedText } from '@chenglou/pretext';

/**
 * Text variants we measure. Each variant maps to a canonical font string.
 * Font strings MUST match exactly what CSS resolves — if CSS says
 * `font: 500 14px "Outfit", sans-serif`, the Pretext font string here
 * must be `'500 14px "Outfit", sans-serif'`. Any mismatch measures the
 * wrong font.
 */
export type Variant = 'title' | 'price' | 'description' | 'cart-item' | 'toast' | 'review-quote';

export type LayoutResult = {
	lineCount: number;
	height: number;
};

class PretextEngine {
	private cache = new Map<string, PreparedText>();
	/*
	 * Runway single-typeface commitment: Inter for everything.
	 * The font strings below MUST match exactly what CSS resolves for
	 * these elements — if you change the CSS, update these strings.
	 */
	private variants: Record<Variant, string> = {
		title: '500 15px "Inter", ui-sans-serif, system-ui, sans-serif',
		price: '500 14px "Inter", ui-sans-serif, system-ui, sans-serif',
		description: '400 14px "Inter", ui-sans-serif, system-ui, sans-serif',
		'cart-item': '500 14px "Inter", ui-sans-serif, system-ui, sans-serif',
		toast: '500 13px "Inter", ui-sans-serif, system-ui, sans-serif',
		'review-quote': '400 15px "Inter", ui-sans-serif, system-ui, sans-serif'
	};

	private fontsReadyPromise: Promise<void> | null = null;

	/**
	 * Returns a promise that resolves when web fonts have loaded. First
	 * call starts the wait; subsequent calls return the same promise.
	 *
	 * CRITICAL: every caller MUST await this before their first prepare()
	 * call. Measuring before fonts load gives wrong widths.
	 */
	ready(): Promise<void> {
		if (this.fontsReadyPromise) return this.fontsReadyPromise;
		if (typeof document === 'undefined' || !document.fonts) {
			this.fontsReadyPromise = Promise.resolve();
			return this.fontsReadyPromise;
		}
		this.fontsReadyPromise = document.fonts.ready.then(() => {
			// Any cached measurements from before the fonts loaded are stale.
			this.cache.clear();
		});
		return this.fontsReadyPromise;
	}

	prepare(text: string, variant: Variant): PreparedText {
		const font = this.variants[variant];
		const key = `${variant}::${text}`;
		const cached = this.cache.get(key);
		if (cached) return cached;
		const prepared = prepare(text, font);
		this.cache.set(key, prepared);
		return prepared;
	}

	/**
	 * Compute the height of a prepared text block at a given width +
	 * line height. Pure arithmetic — no canvas cost. Cheap to call.
	 */
	layout(prepared: PreparedText, maxWidth: number, lineHeightPx: number): LayoutResult {
		return layout(prepared, maxWidth, lineHeightPx);
	}

	/**
	 * One-shot helper: prepare + layout in one call. The most common
	 * component pattern. Caches the prepare() behind the scenes.
	 */
	measure(text: string, variant: Variant, maxWidth: number, lineHeightPx: number): LayoutResult {
		const prepared = this.prepare(text, variant);
		return this.layout(prepared, maxWidth, lineHeightPx);
	}

	/**
	 * Clear all cached measurements. Call when the variant font strings
	 * change (theme switch with different fonts, locale change, etc.).
	 * Cheap operation — just drops the Map.
	 */
	invalidate(): void {
		this.cache.clear();
	}

	/**
	 * Update a variant's font string. Invalidates the cache. Rare — only
	 * needed if the theme exposes runtime font switching.
	 */
	setVariantFont(variant: Variant, fontString: string): void {
		this.variants[variant] = fontString;
		this.invalidate();
	}
}

export const pretext = new PretextEngine();
