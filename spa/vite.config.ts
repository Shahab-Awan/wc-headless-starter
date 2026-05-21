import { sveltekit } from '@sveltejs/kit/vite';
import { defineConfig } from 'vite';

/**
 * Dev-only proxies to WordPress. In production (same-domain deploy) both
 * the SPA and WP live at the same origin, so these rewrites match what
 * Apache serves directly. In dev they make everything same-origin via
 * Vite so cookies flow naturally.
 *
 * - /wp-json/*                 -> WP REST API (mu-plugin endpoints + Store API)
 * - /wp-admin/*                -> WP admin
 * - /wp-login.php              -> WP login (login flow)
 * - /wp-content/*              -> uploads, themes, plugins (media)
 */
const wpProxyOpts = {
	target: 'http://localhost:8099',
	changeOrigin: false,
	cookieDomainRewrite: 'localhost',
	configure: (proxy: any) => {
		proxy.on('proxyRes', (proxyRes: any) => {
			// Make Cart-Token visible to JS even though we're same-origin
			// (browsers still require Expose-Headers per fetch spec).
			const expose = proxyRes.headers['access-control-expose-headers'];
			if (!expose) {
				proxyRes.headers['access-control-expose-headers'] =
					'Cart-Token, Nonce, X-WC-Store-API-Nonce';
			}
		});
	},
};

export default defineConfig({
	plugins: [sveltekit()],
	server: {
		port: 5175,
		strictPort: true,
		proxy: {
			'/wp-json': wpProxyOpts,
			'/wp-admin': wpProxyOpts,
			'/wp-login.php': wpProxyOpts,
			'/wp-content': wpProxyOpts,
			'/my-account': wpProxyOpts,
			'/checkout': wpProxyOpts,
			'/thank-you': wpProxyOpts,
			'/order-received': wpProxyOpts,
		},
	},
});
