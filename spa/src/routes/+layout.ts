// Disable SSR + prerender for the entire app. The SPA is a pure
// client-side app — all data loads from WP via fetch() in onMount,
// there's nothing server-side to render, and adapter-static's
// fallback: 'index.html' handles every route via the client router.
//
// SEO-sensitive routes are still client-rendered after hydration, but
// WordPress serves the initial shell through headless-seo-shell.php so
// raw product/shop/content route metadata exists before JS executes.

export const ssr = false;
export const prerender = false;
