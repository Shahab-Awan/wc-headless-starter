# Third-party integrations

Practical guide for integrating common WooCommerce/WordPress plugins with this
headless setup. Most of the WC plugin ecosystem works out of the box because our
backend is 100% native WooCommerce — checkout, my-account, order processing, and
all server-side hooks (`woocommerce_new_order`, `woocommerce_checkout_order_processed`,
`woocommerce_thankyou`) fire normally. The SPA handles product display, cart, and
post-purchase confirmation; everything else is stock WP.

Read [`docs/security.md`](security.md) alongside this file for rate limiting,
CORS, and API key handling.

---

## Compatibility matrix

Quick scan before installing something new. "Risk" = likelihood of surprise
breakage or admin-side tuning needed.

| Plugin / category | Risk | Where it works | Notes |
|---|---|---|---|
| WooCommerce core | — | Everywhere | Required. |
| Stripe, PayPal, Square, Klarna, Afterpay, Affirm | Low | WP checkout | Standard WC gateway plugins. No SPA changes. Pick the site owner's gateway plugin deliberately; WCHS does not require a cart/funnel plugin. |
| Omnisend (email) | Low | WP + SPA (via our `omnisend-compat` mu-plugin) | See detailed section below. |
| Klaviyo (email) | Low | WP pages + SPA via GTM | Double-tracking risk if `pixels-compat` + dedicated plugin both active — pick one. |
| Meta / TikTok / Pinterest / Google Ads pixels | Low | WP pages via mu-plugin, SPA via GTM | Pixel IDs set in WCHS → Integrations tab. |
| Yoast SEO / Rank Math / AIOSEO | Low | WP-native pages only | SPA routes use our own `<SEO />` component. See SEO section. |
| WP Rocket / W3 Total Cache / LiteSpeed Cache | Medium | HTML + static | MUST exclude `/wp-json/*` from cache. See Caching section. |
| Cloudflare (CDN + WAF) | Medium | Works well | Whitelist `/wp-json/wchs/v1/*` from WAF rate limiting. |
| SG Optimizer (SiteGround) | Low | HTML + static | Compatible by default. Don't enable aggressive HTML caching for `/wp-json/*`. |
| Wordfence / Sucuri / iThemes Security | Medium | WP login + admin | Whitelist `/wp-json/wchs/v1/session` — polled aggressively by SPA. |
| Cookie consent (Complianz, CookieYes, Cookiebot, Iubenda) | Medium | WP pages via `wp_head` | SPA needs GTM or direct `app.html` injection. No consent UI is included; add one for EU traffic. |
| WC Subscriptions | Medium | Checkout, admin | PDP + cart line rendering doesn't surface subscription terms (interval, trial). Customer sees amounts but not cadence. |
| Product Bundles / Composite Products | High | Admin | SPA shows parent product fine but the configurator UI isn't built. Bundles silently degrade to simple products. |
| WPML / Polylang | High | WP-native + partial API | Translation filters flow through Store API. URL-based language routing needs SPA support we haven't built. |
| Multi-currency switcher plugins | Medium | Checkout | Store API returns correct prices but no switcher UI in SPA. Single-currency stores are unaffected. |
| Gravity Forms / WPForms | Low | WP pages via shortcode | Drop on a `/{slug}` page via a HTML module, OR use our `contact-form` module for SPA-side capture. |
| Elementor / page builders | Low | WP pages only | Builder pages don't render inside the SPA. Use for WP-side microsites if needed. |
| Nextend / WP Social Login | Low | WP `/my-account/` | SPA's sign-in CTA redirects there — works end-to-end. |
| EasyPost, ShipStation, Shippo | Low | Server-side | Shipping rate calc runs server-side at checkout. Independent of our EasyPost address-validation feature. |
| Avalara, TaxJar | Low | Server-side | Tax calc via `woocommerce_calc_tax` filter. Store API returns calculated totals. |
| Judge.me, Yotpo, Stamped.io, Okendo | Medium | Pluggable | Use via our review-provider abstraction (WCHS → Integrations → Review Provider). Don't also load their widget JS. |
| Live chat (Intercom, Tidio, LiveChat, Crisp, Tawk.to) | Low | WP pages via snippet | Load via GTM or paste into `spa/src/app.html` for SPA coverage. |
| Backup plugins (UpdraftPlus, BackWPup) | Low | Server-side | No conflicts. `wp_wchs_abandoned_carts` table included in WP DB backups. |
| Uptime / monitoring (Uptime Robot, Pingdom) | Low | External | Point at `/wp-json/wchs/v1/config` for a fast JSON 200 health check. |

---

## Email marketing

Omnisend, Klaviyo, Mailchimp for WC, Mailerlite, ActiveCampaign.

### Installation
Install the plugin via `wp-admin` as normal. Connect it to your account via their
plugin settings. Product catalog sync and order/customer sync run automatically.

### Configuration on our side
1. **Disable built-in abandoned cart emails.** Go to WP Admin → WCHS → Checkout
   → **Abandoned Cart Recovery** and uncheck *"Send built-in abandoned cart
   recovery emails."* Their plugin will handle cart recovery instead — otherwise
   customers receive duplicate emails.
2. **Load their tracking JS via GTM.** Paste their tracking snippet into Google
   Tag Manager, set to fire on all page views.
3. **Map our events to theirs.** Our SPA fires these DataLayer events:
   - `view_item` — product page views (also re-fires on variation change)
   - `view_item_list` — shop grid / slider renders
   - `add_to_cart` — cart additions from anywhere
   - `remove_from_cart` — cart removals
   - `purchase` — SPA order-received page load (once per order id)

   In GTM, configure triggers on each event name, then fire the email-marketing
   plugin's corresponding event. Omnisend documents this pattern explicitly for
   headless storefronts.
4. **Checkout captures work natively.** Their email capture on the checkout form
   works because our checkout page is native WC, not part of the SPA.

### What doesn't work out of the box
- Newsletter signup forms placed via their shortcode: only render on WP pages
  (my-account, etc.), not on SPA routes. Use our `contact_form` module for
  SPA-side capture.
- Browse abandonment campaigns triggered by SPA views: rely on GTM event mapping.
- "Exit intent" popups configured in their plugin: only fire on WP pages.

---

## Ad pixels

Meta Pixel, Google Ads, TikTok Pixel, Pinterest Tag, Snap Pixel.

### Pattern
All work via GTM. Install GTM container ID in WP Admin → WCHS → Integrations.
In GTM, add the pixel's own tag and map our DataLayer events:

| Our event | Pixel event equivalent |
|---|---|
| `view_item` | `ViewContent` (Meta), `view_item` (Google), `ViewContent` (TikTok) |
| `add_to_cart` | `AddToCart` (Meta), `add_to_cart` (Google), `AddToCart` (TikTok) |
| `purchase` | `Purchase` (Meta), `purchase` (Google), `CompletePayment` (TikTok) |

Their native WC plugins also hook `woocommerce_thankyou` server-side — we fire
that hook from our order-redirect code (see `headless-order-redirect.php`), so
server-side conversion APIs (Meta CAPI, Google Enhanced Conversions) also work.

### Conversion pixels
The SPA `/order-received` route fires a `purchase` DataLayer event with full
order details. This is where your pixels should attribute conversion.

---

## Analytics

GA4 via GTM, Microsoft Clarity, Hotjar, Plausible, Fathom.

### GA4
Works out of the box. Our DataLayer events follow the GA4 ecommerce convention
exactly (`ecommerce.items[]`, `transaction_id`, `value`, `currency`). Install
GA4 Configuration tag in GTM, it auto-consumes everything.

### Clarity / Hotjar
Paste their snippet in GTM → fires on all SPA routes automatically. No event
mapping required; they just record sessions.

---

## SEO plugins

Yoast SEO, RankMath, All in One SEO (AIOSEO).

### What works
The plugin's output appears on WP-rendered pages:
- `/my-account/` and sub-pages
- `/checkout/`
- `/wp-login.php`
- Any `wp-admin` URL

### WCHS route SEO
SPA routes use WCHS-owned SEO rather than Yoast/RankMath/AIOSEO output.
For product, shop/category, account/order private routes, and one-level WCHS
content pages, `headless-seo-shell.php` serves the static SPA shell with
route-specific raw meta before JavaScript runs. After hydration, the Svelte
`<SEO />` component replaces those static tags with the live client-side tags:
- `<title>`, `<meta name="description">`, `<link rel="canonical">`
- Full Open Graph (`og:type`, `og:title`, `og:description`, `og:image`,
  `og:url`, `og:site_name`)
- Twitter Card (`summary_large_image` when an image is present)
- JSON-LD `Product` schema on PDPs with `aggregateRating` when reviews exist

SEO plugin data is not currently piped into SPA heads. Treat WCHS settings,
WooCommerce products/categories, and WCHS pages as the source of truth for SPA
route metadata.

### Remaining limitation
The raw homepage shell uses the per-site deploy-time fallback metadata. If a
future route needs highly custom raw metadata and it is not one of the routed
WCHS shell paths, add it to `headless-seo-shell.php` and `.htaccess`.

---

## Payment gateways

Stripe, PayPal, Square, Klarna, Afterpay/Clearpay, Affirm, WC Payments.

Install their plugin and configure as normal. Checkout is native WC; no SPA
changes are needed. Their JS fires on the checkout page via standard WC hooks.
Webhooks work server-side.

---

## Shipping

ShipStation, Shippo, EasyPost, ShipperHQ, Parcelforce, USPS/UPS/FedEx plugins.

Works out of the box. Shipping rate calc happens server-side at checkout via
`woocommerce_shipping_methods` filter. We integrate EasyPost natively for
address validation (see WCHS → Checkout → Address Validation); that's
independent of their shipping label functionality.

---

## Tax

Avalara, TaxJar, WooCommerce Tax.

Works out of the box. Tax calc is server-side at cart/checkout via
`woocommerce_calc_tax` filter. Store API returns calculated totals to the SPA.

---

## Security

Wordfence, Sucuri, iThemes Security, Cloudflare WAF.

### Rate limits
These plugins may throttle the SPA's repeated `/wchs/v1/session` calls (polled
on mount, tab focus, every navigation). Symptoms: occasional 429s, mysterious
logouts.

**Fix:** whitelist the session endpoint in the security plugin's rules:
- Wordfence: Firewall → Live Traffic → add `/wp-json/wchs/v1/session` to
  whitelist.
- Sucuri: exclude `/wp-json/wchs/v1/` from rate limiting.
- Cloudflare: exempt `/wp-json/wchs/v1/*` from WAF rate limiting rules.

### 2FA
Wordfence 2FA, WP 2FA, miniOrange 2FA work on the native WP login page. Our SPA
redirects to `/my-account/` for login, so 2FA gates the flow correctly. No SPA
changes needed.

### Login rate limiting
Their login throttling works natively because WP login is unchanged.

---

## Social login

Nextend Social Login, Super Socializer, WordPress Social Login.

Renders "Sign in with Google/Facebook/etc." buttons on the WP `/my-account/`
page. Our SPA's sign-in CTA redirects there, so users see the social buttons and
the flow works end-to-end.

---

## Caching / CDN

WP Rocket, W3 Total Cache, LiteSpeed Cache, Cloudflare, BunnyCDN.

### What to cache
- WP page HTML (excluding checkout/my-account — standard WC exclusions)
- Static assets (CSS, JS, images) with long TTL

### What NOT to cache
- `/wp-json/*` — all API endpoints. Our SPA relies on fresh data per request
  (cart state, auth session, product inventory).
- Cookies starting with `wp_` or `woocommerce_` — WP auth and WC session cookies
  must not be cached or served to other users.

### Cache setup
- **WP Rocket**: add `/wp-json/*` to "Exclude URLs from cache"
- **Cloudflare**: Page Rules → `*/wp-json/*` → Cache Level: Bypass
- **CDN rules**: don't send auth cookies through the CDN for static asset paths

The SPA bundle is pure static output from `@sveltejs/adapter-static` — served
by Apache from the site root. Any CDN in front of it caches normally (SiteGround
Dynamic Cache / Cloudflare / BunnyCDN). Just bypass cache for `/wp-json/*` and
`/wp-admin/*`.

---

## GDPR / cookie consent

Complianz, CookieYes, Cookiebot, Iubenda, OneTrust.

Their JS is injected via `wp_head` → renders on WP-native pages only. SPA routes
don't include it. If you need a consent banner on SPA pages, either:
1. Paste their universal tag code into `spa/src/app.html` (not recommended — bypasses their plugin UI)
2. Load their script via GTM

Our SiteGate modal is a separate feature (age verification, ToS acceptance, etc.)
and doesn't substitute for GDPR consent.

---

## Live chat / support

Intercom, Tidio, LiveChat, Crisp, Tawk.to, HubSpot Chat.

Their snippets load on WP pages via `wp_footer`. For the SPA, add via GTM or
paste directly into `spa/src/app.html`:

```html
<!-- example: Intercom -->
<script>
  window.intercomSettings = { app_id: "YOUR_APP_ID" };
  // ... their init snippet
</script>
```

---

## Reviews

Judge.me, Yotpo, Stamped.io, Trustpilot, Okendo.

Our starter includes a review-provider abstraction (`headless-reviews.php` /
`/wchs/v1/reviews/{product_id}` endpoint) with pluggable backends:
- WC native (default) — uses WP's built-in comment-based product reviews
- Yotpo — proxy to Yotpo's API (requires store ID + API key)
- Stamped — proxy to Stamped's API
- Judge.me — proxy to Judge.me's API
- Reviews.io, Mock — additional options

Pick one in WCHS → Integrations → Review Provider. The SPA reviews UI is
provider-agnostic (renders identically regardless of source).

**Do not** also load the provider's JS widget — it'll duplicate the reviews on
the page.

---

## Things we're NOT fully compatible with today

- **WC Subscriptions** — checkout works, but product-page price display and
  cart line-item rendering don't show subscription terms (billing interval,
  trial). Planned.
- **Product Bundles / Composite Products** — the parent product displays fine,
  but the bundle configurator UI isn't built. Planned.
- **Product attribute filters** (FacetWP, YITH AJAX Filter) — we have category
  + search + sort on shop, no faceted filtering yet.
- **Multilingual** (WPML, Polylang) — translation filters flow through Store
  API, but URL-based language routing needs SPA support.
- **Multi-currency switcher** — prices flow correctly from Store API if a
  multi-currency plugin is active, but there's no switcher UI component in
  the SPA. Stores with one currency work fine.

---

## What's NOT included — install separately

The starter covers the headless storefront + admin + common site-owner
settings. These are deliberate gaps where we defer to specialized
third-party plugins or external services. Not exhaustive — just the most
common asks.

| Need | Why we don't include it | Recommended |
|---|---|---|
| Full-page caching / CDN | Host-specific concern; plugins can fight each other | SG Optimizer (on SiteGround), WP Rocket, Cloudflare Page Rules, BunnyCDN |
| Backups / disaster recovery | Domain-specific (S3, Backblaze, host-managed) | UpdraftPlus, BackWPup, host snapshot |
| Uptime monitoring / alerting | Requires external service | Uptime Robot, Pingdom, BetterUptime |
| Image optimization / CDN | Orthogonal to commerce concerns | Imagify, ShortPixel, Smush, Cloudflare Polish |
| SEO plugin output on SPA routes | WCHS owns SPA metadata | Use WCHS settings + Woo products/categories + WCHS pages |
| Social-share preview (Open Graph for FB/Twitter/Slack unfurlers) | Covered for routed WCHS shell paths only | Product, shop/category, and WCHS content pages use `headless-seo-shell.php` |
| Cookie consent / GDPR banner on SPA | We have no consent UI — GDPR responsibility sits with operator | Complianz, CookieYes, Cookiebot, Iubenda, OneTrust (inject via GTM or `app.html`) |
| Contact form with conditional logic / payments | Out of scope for storefront | Gravity Forms, WPForms Pro. Or use our built-in `contact-form` module for simple email capture. |
| Wishlist / favorites | Out of scope for v1.0 | YITH Wishlist, TI WooCommerce Wishlist — both WP-page only. |
| Loyalty / rewards / referral program | Out of scope | YITH Points & Rewards, AutomateWoo, Referral Factory |
| SMS / push notifications | Defer to email marketing provider | Omnisend (SMS), Postscript, Twilio |
| A/B testing / experimentation | Out of scope | VWO, Optimizely, or SPA-side flag eval |
| Product recommendations (personalized) | Out of scope | Algolia Recommend, Nosto, WC's built-in cross-sell config (via `headless-cro-extension.php`) |
| Advanced product filters / faceted search | Only category + search + sort in v1.0 | FacetWP, YITH AJAX Filter (WP-only — won't render in SPA shop grid) |
| Multi-currency switcher UI | No switcher component in SPA; single-currency stores unaffected | WOOCS, Currency Switcher Woo — prices flow via Store API but UI is DIY |

---

## Known gotchas (cross-plugin)

Collected here so you don't have to re-learn them:

| Gotcha | Which plugin triggers it | Fix |
|---|---|---|
| SPA gets logged out mysteriously | Wordfence, Sucuri, any aggressive rate limiter | Whitelist `/wp-json/wchs/v1/session`. |
| Cart merge fails after login | Known WC issue #55653 | `headless-login-merge.php` is included as a workaround. Remove if upstream fixes. |
| Rate limits don't fire in dev | `WP_DEBUG=true` | Intentional — rate limiting is disabled when debug is on. Never deploy with `WP_DEBUG=true`. |
| CORS allowlist changes don't take effect | Same-origin/custom-origin runtime drift | For normal sites, fix `home/siteurl` or the Cutover settings. Only split-origin/local-dev setups need `WCHS_*` overrides in `wp-config.php`. |
| Stripe upsell flow skipped | No saved Stripe payment method on the order | Expected for Stripe. Offline/BACS/COD/Cheque orders can still show upsells. |
| Double-tracking in analytics | `pixels-compat` mu-plugin + dedicated Klaviyo/Meta plugin both active | Pick one. |
| Caching plugin breaks auth | Caching plugin caching `/wp-json/*` | Exclude `/wp-json/*` from cache (every caching plugin's settings). |
| Omnisend brand ID typo silently disables integration | Strict regex validation in `headless-omnisend-compat.php` | Check the WCHS → Integrations field — must match `^[a-f0-9]{20,32}$`. |

---

## Reporting integration issues

If a plugin works on native WP pages but not on the SPA, the usual culprits:

1. **JS not loading on SPA**: plugins that inject via `wp_head`/`wp_footer`
   only affect WP pages. Use GTM or `app.html` instead.
2. **CORS/origin**: check the plugin's AJAX URLs are relative or proxied via
   our `/wp/` path. See [`docs/security.md`](security.md).
3. **Access mode gating**: `headless-access-control.php` blocks certain API
   paths in locked/maintenance modes. If a third-party plugin registers its
   own REST routes and you're in mode 1/0, they may be blocked. Check the
   `always_open` allowlist in that file.
4. **Rate limiting**: our `/wchs/v1/*` endpoints rate-limit per IP unless
   `WP_DEBUG=true`. See [`docs/security.md`](security.md).

Use `docker compose logs -f wordpress` while reproducing to catch plugin
errors.
