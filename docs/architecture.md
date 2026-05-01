# Architecture - Headless WC + SvelteKit

Written around the WooCommerce Store API behavior current in April 2026. If
you are reading this later and something feels stale, re-check the WC Store API
docs and the linked issues before re-learning the hard way.

---

## API surface - pick these, not those

| API | Use for | Do not use for |
| --- | --- | --- |
| **Store API** (`/wp-json/wc/store/v1/`) | Catalog AND cart AND checkout sync, from the browser. Nonce + Cart-Token auth. CORS-safe since WC 9.8. | Admin-only data (customer list, order history, private fields). |
| **REST API v3** (`/wp-json/wc/v3/`) | Server-side only, from `+page.server.ts` / `hooks.server.ts`. Consumer key/secret. Admin-style product queries, post-order lookups. | Anything browser-side. Never expose the secret. |
| WPGraphQL / WooGraphQL | - | **Skip it.** Extra dep, lags Store API updates, cart story is weaker. |
| JWT plugins | - | **Skip them.** Attack surface, unnecessary when Store API + cookie auth already works. |
| CoCart free | - | **Skip free tier.** Doesn't solve the classic-checkout handoff; the thing you'd actually want is paid. |

**Rule of thumb:** Store API from the SPA, REST v3 only in SvelteKit server code.

---

## Cart bridging - the actual hard problem

This is why a headless WC project looks easy for a week and then stops for
two weeks. There is **no first-party documented handoff** from a Store API
cart to the classic `/checkout` page in April 2026. WC 9.8 improved the
primitives but the glue is still yours.

### The core fact
The Store API cart and the classic session cart are **the same row** in
`wp_woocommerce_sessions`. What differs is the *selector*:

- Classic session uses the `wp_woocommerce_session_*` cookie.
- Store API session uses a JWT `Cart-Token` returned in a response header.

WooCommerce chooses the handler at request time. If `Cart-Token` is present
on the request, it uses the Store API session handler. Otherwise cookie.

### The pattern that works
1. SPA mutates the cart via Store API. It captures the JWT from the
   `Cart-Token` response header (exposed via CORS).
2. On "Checkout" click, the SPA does a **top-level navigation** to
   `/wp/checkout/?cart=<JWT>`.
3. `wp/mu-plugins/headless-cart-bridge.php` hooks `plugins_loaded` early,
   reads `?cart=<JWT>`, validates its shape, and injects it into
   `$_SERVER['HTTP_CART_TOKEN']`.
4. A `woocommerce_session_handler` filter returns the Store API session
   handler class, so WC loads the token's cart instead of the cookie's cart.
5. The classic `/checkout` page renders with the SPA cart intact. WC
   handles payment, tax, shipping, order creation - all the stuff that
   already works.

### Known gotchas
- **Logged-in merge bug** (WC#55653): if the user was already logged in and
  had a classic-session cart, and the token is then promoted, the token cart
  *replaces* the logged-in cart instead of merging. The bridge plugin logs a
  warning when both are present. Until upstream fixes it, the SPA's cart is
  treated as source-of-truth on handoff.
- **`SameSite=Lax`** on login cookies means cross-origin fetch from the SPA
  can't rely on the logged-in cookie reaching WP. Top-level navigation is
  fine. Cross-origin XHR is not. See the CORS section.
- **Nonces rotate on login state change.** After returning from the native
  login page, always refetch `/wc/store/v1/cart` to get a fresh nonce before
  any POST. Caching the old nonce is a wasted afternoon.
- **`Access-Control-Expose-Headers`** must include `Cart-Token` or JS reads
  `null` from `response.headers.get('Cart-Token')`. See
  `wp/mu-plugins/headless-cors.php`.

---

## Auth bridging

Login stays on the native `/my-account/` page. The SPA needs to know
afterwards whether the user is signed in.

### The working pattern
1. SPA renders a "Sign in" link: `window.location = '/wp/my-account/?return=http://localhost:5175/'`
2. User logs in on the native page.
3. `headless-login-return.php` intercepts the post-login redirect and sends
   them back to `return=` if the origin is allowlisted.
4. Back on the SPA, a `GET /wc/store/v1/cart` with `credentials: 'include'`
   returns the logged-in customer object in the response body. The SPA
   treats that as the "am I logged in" probe.

No JWT. No cookie reading (impossible across origins anyway, HttpOnly). The
Store API response is the source of truth.

---

## CORS & local dev

### The rule
**Use a Vite dev proxy. Do not try to make cross-port cookies work in dev.**

Browsers treat `localhost:5175` and `localhost:8099` as different origins.
`SameSite=Lax` won't send login cookies on cross-origin fetches. Cross-port
cookies in dev do not match prod behavior, so "fixing" them just teaches you
a solution that breaks in prod.

`spa/vite.config.ts` proxies `/wp/*` to `localhost:8099`, so the SPA only
ever hits its own origin. Cookies flow, nonces work, the login return hop
Just Works.

The `headless-cors.php` plugin is there as a safety net (and as a template
for prod, where the SPA will live on `site.com` and WP on `wp.site.com`).

---

## Native page reskinning

**Classic child theme, not FSE, not block checkout.**

- Parent: `twentytwentyfive` (or Storefront if you prefer WC-native hooks).
- `wp/themes/headless-shim/` dequeues everything on `/cart`, `/checkout`,
  `/my-account`, loads the SPA's CSS bundle, overrides only the template
  files it needs.
- **Use classic `[woocommerce_checkout]` shortcode.** The block-based
  checkout is React-rendered with scoped styles and fights CSS overrides.
  Classic is PHP templates; CSS cleanly wins.
- `wp-login.php` is not a WC page - style it via the `login_enqueue_scripts`
  hook if needed, separately.

---

## Top gotchas (the 2026 list)

1. **Store API cart ≠ classic cart at checkout handoff.** The bridge plugin
   is not optional.
2. **`Access-Control-Expose-Headers` omission** will eat an afternoon.
3. **Nonce rotation on login state change** - refetch `/cart` after auth.
4. **Cross-port cookies in dev don't match prod.** Use the Vite proxy.
5. **Backend plugins still fire.** A WC plugin that emits notices into
   classic cart templates will silently do nothing for your SPA cart.
   Audit the active plugin list and expect to fork one or two.

---

## Reference implementations in 2026 (honest take)

- **Frontity**: dead (archived 2022).
- **Vercel Commerce**: Shopify only, explicitly not WC.
- **SvelteKit + WC**: no production-grade starter exists.
  - `itswadesh/svelte-commerce` - SvelteKit, backend-agnostic. UI patterns
    and store shape are worth cribbing; adapt its cart interface to hit
    Store API.
  - `kellenmace/intro-to-headless-wordpress-with-sveltekit` - WP content
    only, no WC, but shows hooks.server.ts patterns.
- **Next + WooGraphQL**: several. Cross-framework cribbing only.

**Bottom line: you are building this.** The SvelteKit half is straightforward
once the PHP glue is in place. The PHP glue is the project.
