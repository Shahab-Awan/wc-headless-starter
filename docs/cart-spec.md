# WCHS Slide Cart Spec

The slide cart is WCHS-owned SPA code. It does not depend on a third-party
cart, funnel, or checkout plugin.

## Ownership

- Component: `spa/src/lib/components/SlideCart.svelte`
- State store: `spa/src/lib/wc/cart.svelte.ts`
- API client: `spa/src/lib/wc/store-api.ts`
- Checkout handoff: `wp/mu-plugins/headless-cart-bridge.php`

## Runtime Model

The SPA cart reads and mutates WooCommerce Store API state through
`/wp-json/wc/store/v1/cart`. The Store API response is the source of truth.
The browser keeps only the cart token and a lightweight local mirror for
cross-tab refresh.

## Required Behavior

- Add, remove, and quantity updates must serialize through the cart store.
- Cart state must survive SPA route changes.
- Cross-tab changes should trigger a refetch.
- The checkout button must include the current cart token when navigating
  to `/checkout`.
- Native WooCommerce checkout must receive the same cart via
  `headless-cart-bridge.php` before checkout totals render.
- The cart UI must not rely on any WordPress-side cart drawer.

## Checkout Handoff

The SPA links to checkout with a signed cart token. The mu-plugin validates
that token, promotes it into the WooCommerce session, and lets the stock
checkout page render normally.

If checkout shows stale or unexpected items:

1. Verify the SPA checkout link includes `?cart=<token>`.
2. Verify `headless-cart-bridge.php` is loaded as an mu-plugin.
3. Verify WooCommerce sessions exist in `{prefix}_woocommerce_sessions`.
4. Clear the browser session and retest before touching the database.

## Extension Events

The cart dispatches compatibility DOM events for analytics snippets that
already listen to cart opens, quantity changes, coupon changes, and cart
refreshes. These are WCHS compatibility events, not evidence of a plugin
dependency.
