/**
 * SvelteKit server hook — injects the theme into the HTML before it
 * reaches the browser. This is the ONLY way to guarantee zero flash
 * on page load. The inline <script> in app.html is a client-side
 * fallback; this hook is the primary mechanism.
 *
 * How it works:
 *   1. Browser sends request with cookies (including wchs_theme)
 *   2. SvelteKit's handle() reads the cookie server-side
 *   3. transformPageChunk replaces the <html> tag to include
 *      data-theme and color-scheme BEFORE the HTML is sent
 *   4. Browser receives HTML with correct theme already set
 *   5. No JS execution needed before first paint = zero flash
 *
 * The wchs_theme cookie is shared across localhost ports (SPA :5175
 * and WP :8099) because cookies scope by hostname, not port.
 */

import type { Handle } from '@sveltejs/kit';

const VALID_THEMES = ['light', 'dark'] as const;

export const handle: Handle = async ({ event, resolve }) => {
	const cookieTheme = event.cookies.get('wchs_theme');
	const theme = VALID_THEMES.includes(cookieTheme as any)
		? (cookieTheme as string)
		: 'light';

	return resolve(event, {
		transformPageChunk: ({ html }) =>
			html.replace(
				'<html lang="en">',
				`<html lang="en" data-theme="${theme}" style="color-scheme:${theme}">`
			),
	});
};
