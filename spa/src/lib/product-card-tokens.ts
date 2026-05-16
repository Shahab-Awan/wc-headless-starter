/**
 * Apply product-card customization (from Design tab → Product card section)
 * as CSS custom properties on :root. Any change here streams into the live
 * preview via config.svelte.ts → initPreviewMode; pure tokens mean each
 * rule in ProductCard.svelte's <style> picks up the override automatically.
 */

import type { ProductCardConfig } from './config.svelte';

const ASPECT_RATIO: Record<ProductCardConfig['media_aspect_ratio'], string> = {
	'1:1':  '1 / 1',
	'4:5':  '4 / 5',
	'3:4':  '3 / 4',
	'16:9': '16 / 9',
};

const CORNER_RADIUS: Record<ProductCardConfig['corner_radius'], string> = {
	square: '14px',
	soft:   '14px',
	round:  '14px',
	pill:   '14px',
};

const BUTTON_RADIUS: Record<ProductCardConfig['corner_radius'], string> = {
	square: '14px',
	soft:   '14px',
	round:  '14px',
	pill:   '14px',
};

export function applyProductCardTokens(pc: ProductCardConfig, root = document.documentElement): void {
	const s = root.style;
	s.setProperty('--card-aspect-ratio', ASPECT_RATIO[pc.media_aspect_ratio] ?? '1 / 1');
	s.setProperty('--card-radius', CORNER_RADIUS[pc.corner_radius] ?? '0px');
	s.setProperty('--card-button-radius', BUTTON_RADIUS[pc.corner_radius] ?? '0px');

	// Border style is encoded as a data-attribute on <html> since it's a
	// topology change (bottom-only vs full) not just a width difference.
	root.setAttribute('data-card-border', pc.border);
	root.setAttribute('data-card-hover', pc.hover_effect);
	root.setAttribute('data-card-button', pc.button_style);
	root.setAttribute('data-card-badge-position', pc.badge_position);
	root.setAttribute('data-card-badge-style', pc.badge_style);
	root.setAttribute('data-card-oos-treatment', pc.oos_treatment);
	root.setAttribute('data-card-title-lines', pc.title_lines);
}
