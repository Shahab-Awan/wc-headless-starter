# Mu-Plugins Reference

The mu-plugins listed below are included with this starter. All auto-load from
`wp-content/mu-plugins/` — no activation step needed. Two of them
(`wchs-admin.php`, `wchs-design-system.php`) are loaders that bootstrap
companion subdirectory plugins.

For each plugin: what it owns, what it depends on, what it explicitly
does NOT own, and gotchas worth knowing before you modify it.

---

## Quick index

| File | Purpose |
|---|---|
| [`headless-abandoned-cart.php`](#headless-abandoned-cart) | Capture + email-recover abandoned checkouts |
| [`headless-access-control.php`](#headless-access-control) | 4-mode site gating (maintenance / locked / browse-only / open) |
| [`headless-address-validation.php`](#headless-address-validation) | EasyPost address verification at checkout |
| [`headless-cart-bridge.php`](#headless-cart-bridge) | JWT → classic WC session handoff for native checkout |
| [`headless-cart-lock.php`](#headless-cart-lock) | MySQL GET_LOCK mutex on Store API cart mutations |
| [`headless-checkout-order-sanitizer.php`](#headless-checkout-order-sanitizer) | Rebuild checkout orders from the live cart if stale legacy line items hitch a ride |
| [`headless-cors.php`](#headless-cors) | Strict CORS for Store API + defensive security headers |
| [`headless-cro-extension.php`](#headless-cro-extension) | Expose tier pricing + cross-sells on Store API |
| [`headless-login-merge.php`](#headless-login-merge) | WC#55653 workaround — merge saved cart on login |
| [`headless-login-return.php`](#headless-login-return) | `?return=` origin allowlist on wp-login |
| [`headless-media.php`](#headless-media) | Prefer WebP as the output format for new JPEG / PNG uploads |
| [`headless-offline-gateways.php`](#headless-offline-gateways) | CashApp/Venmo/PayPal.me/Zelle/Bitcoin gateways |
| [`headless-omnisend-compat.php`](#headless-omnisend-compat) | Omnisend launcher + checkout tracking on WP surfaces |
| [`headless-one-click-upsell.php`](#headless-one-click-upsell) | Post-purchase Stripe off-session upsell |
| [`headless-order-approval.php`](#headless-order-approval) | Admin 1-click on-hold → processing |
| [`headless-order-bump.php`](#headless-order-bump) | Opt-in bump product at checkout review |
| [`headless-order-redirect.php`](#headless-order-redirect) | Rewrite WC thank-you URL → SPA /order-received |
| [`headless-pixels-compat.php`](#headless-pixels-compat) | Inject ad/analytics pixels on WP-rendered surfaces |
| [`headless-preview-role.php`](#headless-preview-role) | Restrict shop_manager from sensitive admin surfaces |
| [`headless-registration-reqs.php`](#headless-registration-reqs) | Email verification + required field enforcement |
| [`headless-rest-endpoints.php`](#headless-rest-endpoints) | Custom `/wchs/v1/*` routes: config, reviews, orders, newsletter |
| [`headless-review-providers.php`](#headless-review-providers) | Swappable review backend (WC / Yotpo / Stamped / Reviews.io) |
| [`headless-seo.php`](#headless-seo) | `/sitemap.xml` + `/robots.txt` generator for the SPA origin |
| [`headless-seo-shell.php`](#headless-seo-shell) | Route-specific raw SEO tags for SPA routes before Svelte hydrates |
| [`headless-smtp.php`](#headless-smtp) | Configure wp_mail to use SMTP |
| [`headless-tier-pricing.php`](#headless-tier-pricing) | Qty-based volume discounts on product admin |
| [`headless-turnstile.php`](#headless-turnstile) | Cloudflare Turnstile server-side verification |
| [`wchs-head-scripts.php`](#wchs-head-scripts) | Inject curated third-party scripts on WP-rendered pages (Alia, Cookiebot, etc. — the WP half of the Site Scripts feature) |
| [`wchs-origin-config.php`](#wchs-origin-config) | Centralized same-origin/custom-origin resolution shared by redirects, CORS, login returns, and cutover tooling |
| [`wchs-admin.php`](#wchs-admin) (loader → `wchs-admin/`) | Centralized admin settings UI |
| [`wchs-design-system.php`](#wchs-design-system) (loader → `wchs-design-system/`) | Shared tokens + WC overrides for native WP pages |

---

## Common patterns

**Dependencies:** every plugin assumes WordPress core + WooCommerce are active. Additional dependencies are called out per plugin.

**Secrets vs runtime settings:** SMTP credentials and optional brand overrides still fit naturally in `wp-config.php`. Origin handling no longer depends on `WCHS_*` constants for normal sites: same-origin mode follows `home_url()` automatically, while custom split-origin setups can opt into WCHS settings or legacy constants as fallback overrides.

**WC_DEBUG=true gotcha:** rate limiting is disabled, CORS is slightly more permissive. Never deploy with `WP_DEBUG=true`.

**HPOS (High-Performance Order Storage):** all plugins that touch orders are HPOS-compatible.

**Site option:** most cross-plugin config lives in `wp_options.wchs_site_settings` (serialized PHP). See `docs/admin-settings-reference.md` for the full key list.

---

## headless-abandoned-cart

**Owns:**
- Custom table `wp_wchs_abandoned_carts` (email, cart_contents, captured_at, emails_sent, recovered_at)
- AJAX endpoint to capture checkout email on blur
- WP-Cron task (5-min interval) to send recovery emails at 1h + 24h
- Recovery token → one-time cart session rehydrate

**Depends on:** `wp_mail()` (via `headless-smtp.php`), WC cart session

**Doesn't own:** email templates (uses WC default), GDPR consent (caller's responsibility)

**Gotchas:**
- Recovery tokens stored plaintext. If DB leaks, attacker can hydrate any cart.
- WP-Cron relies on site traffic. Add a system cron fallback for low-traffic sites.
- `captured_at` rows don't auto-prune. Purge manually or add a cleanup task.

---

## headless-access-control

**Owns:**
- `access_mode` setting: 0 (Maintenance/503), 1 (Locked/403 for guests), 2 (Browse-only — catalog public, checkout gated), 3 (Open)
- `rest_pre_dispatch` priority 5 — blocks Store API endpoints per mode
- `template_redirect` priority 5 — blocks /cart, /checkout, /my-account per mode
- Always-open routes: `/wchs/v1/config`, `/wchs/v1/session`
- Appends to robots.txt when `seo_block_cart_checkout` is on

**Depends on:** WC (endpoint URL helpers), optionally `headless-registration-reqs.php` (email verification gate)

**Doesn't own:** email verification logic (reg-reqs), rate limiting (rest-endpoints)

**Gotchas:**
- Mode 0 returns 503, not 401 — browsers will retry.
- Unauthed blocked users get 403, not 401 — some API clients expecting WWW-Authenticate may misinterpret.
- `seo_nosnippet_products` is aggressive — disable if you want product rich snippets in SERPs.

---

## headless-address-validation

**Owns:**
- `/wchs/v1/validate-address` REST endpoint
- EasyPost API integration
- Modes: `strict` (reject corrections) / `moderate` (offer corrections as modal)

**Depends on:** EasyPost API (HTTP), `easypost_api_key` setting

**Doesn't own:** address autocomplete (Google Places — separate plugin if used)

**Gotchas:**
- API key plaintext in admin option. Rotate if admin panel breaches.
- Network failure: configure fail-open vs fail-closed per site. Default fails open.
- Requires address shape matching EasyPost's schema (street1/street2/city/state/zip/country).

---

## headless-cart-bridge

**Owns:**
- `?cart=<JWT>` param handling on `/checkout`
- JWT signature verification via WC's `CartTokenUtils::decode`
- Imports whitelisted session keys: `cart`, `cart_totals`, `applied_coupons`, etc.
- WC#55653 mitigation: refuses the token if user is logged in with a non-empty cart (prevents cart-phishing)

**Depends on:** WC (`CartTokenUtils`), `wp_salt()`, auth cookie validation

**Doesn't own:** JWT issuance (Store API does), cart encryption (`wp_salt` based HMAC)

**Gotchas:**
- Tokens are only honored on `/checkout` — any other URL silently ignores `?cart=`.
- `maybe_unserialize()` on session values — key allowlist validates BEFORE deserializing (defense-in-depth).
- No token in logs (log-injection prevention). WP_DEBUG=true is the only path to see tokens in error logs.

---

## headless-cart-lock

**Owns:**
- `rest_pre_dispatch` priority 1 on `/wc/store/v1/cart/*`
- MySQL `GET_LOCK()` / `RELEASE_LOCK()` per session hash
- Lock timeout: `WCHS_CART_LOCK_TIMEOUT` (default 5s)
- Toggle: `WCHS_CART_LOCK_ENABLED` (default true)
- Multi-DB safe (lock key includes `DB_NAME`)

**Depends on:** MySQL (GET_LOCK — not available on SQLite/Postgres)

**Doesn't own:** cart validation, cart persistence

**Gotchas:**
- Lock auto-releases on PHP connection close (even on crash). Safe.
- Hung request > 5s → next caller gets lock, old request finishes with stale cart. Tune `WCHS_CART_LOCK_TIMEOUT` if you see this.
- Shared hosts with many sites on one MySQL: lock names already namespaced by DB_NAME. Still, name collisions theoretically possible.

---

## headless-checkout-order-sanitizer

**Owns:**
- `woocommerce_checkout_order_created` guard that compares the just-saved order rows against the active cart
- Direct DB read of `woocommerce_order_items` + `woocommerce_order_itemmeta` so recycled order IDs cannot hide stale line items
- Signature compare keyed on product id, variation id, qty, subtotal, and total
- Emergency rebuild path: `remove_order_items()` + `WC()->checkout()->set_data_from_cart( $order )`
- Audit meta: `_wchs_order_sanitized`, `_wchs_order_sanitizer_expected_count`, `_wchs_order_sanitizer_actual_count`

**Depends on:** WC cart + checkout objects, Woo order item tables

**Doesn't own:** root-cause cleanup of bad legacy rows, checkout pricing logic, or post-order remediation outside the current request

**Gotchas:**
- It only runs when checkout has an active cart and the expected cart signature is non-empty.
- The compare is intentionally narrow: same products with different totals will still trigger a rebuild.
- A rebuilt order gets an order note. If you see repeated sanitizer notes in production, fix the underlying DB hygiene instead of relying on this forever.

---

## headless-cors

**Owns:**
- `wchs_allowed_origins()` / `wchs_is_allowed_origin()` helpers
- `rest_pre_serve_request` (priority 10) — adds `Access-Control-Allow-*` headers
- `init` priority 0 — early OPTIONS preflight response for unknown origins (403)
- Security headers on every REST response: `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`
- 60s preflight cache

**Depends on:** `wchs-origin-config.php` for the effective allowlist. Legacy `WCHS_ALLOWED_ORIGINS` constants are still honored as fallback input for custom/local-dev setups.

**Doesn't own:** authentication (Store API / plugins own that), rate limiting.

**Gotchas:**
- Same-origin stores follow `home_url()` automatically. Only split-origin/local-dev setups need explicit custom origins.
- Unknown origins silently rejected (no log — prevents log-injection). Use WP_DEBUG if triaging.
- Fires on every `/wp-json/` response, not just ours — intentional defense-in-depth.

---

## headless-cro-extension

**Owns:**
- Store API `extensions.wchs_cro` object on products + cart items + cart totals
- Tier pricing math (reads meta from `headless-tier-pricing.php`)
- Per-line savings calculation
- Cross-sell ID exposure (from `_crosssell_ids`)
- All monetary values in minor units (cents)

**Depends on:** WC Store API `ExtendSchema`, `headless-tier-pricing.php` (for meta keys)

**Doesn't own:** pricing hooks (WC core), coupon logic (WC core), cross-sell ordering

**Gotchas:**
- Savings calculated BEFORE coupons applied. Display may differ from final line.
- `next_tier` populated only when qty+1 would unlock a tier. Null otherwise.
- SPA must handle integer cents arithmetic (no float surprises).

---

## headless-login-merge

**Owns:**
- `wp_login` + `woocommerce_authentication` action hooks
- Sets `_woocommerce_load_saved_cart_after_login = 1` user meta

**Depends on:** WC session + auth flow

**Doesn't own:** cart merge itself (WC core consumes the meta), password reset, 2FA

**Gotchas:**
- Workaround for open WC#55653 (as of April 2026). Remove this plugin if/when upstream fixes.
- Only fires on `wp_login` + `woocommerce_authentication` — custom auth hooks (social login) may not trigger it.
- Flag isn't cleared after merge — successive logins merge redundantly (harmless but wasteful).

---

## headless-login-return

**Owns:**
- `?return=<spa-origin>` handling on wp-login.php / wp-register.php
- Strict origin allowlist via `wchs_return_origin_list()`
- Fixed path allowlist: `/`, `/account`, `/account/orders`, `/shop`
- Drops query + fragment on every redirect; unsupported paths collapse back to `/`
- Blocks CR/LF in return value (header-injection defense)

**Depends on:** wp-login standard flow, `wchs-origin-config.php`

**Doesn't own:** authentication itself (WP core), email verification (reg-reqs)

**Gotchas:**
- Intentionally drops query string. `?return=https://spa/account?utm=x` becomes `https://spa/account`. Features that need query preservation must pass state via localStorage or cookies.
- Deep links are intentionally narrow. Anything outside the small route allowlist collapses to `/`.
- Rejected inputs fall back to WP default redirect (wp-admin) — no user-facing error.

---

## headless-media

**Owns:**
- `wp_image_editor_output_format` filter mapping `image/jpeg` + `image/png` → `image/webp`

**Depends on:** WP core image editor (GD or Imagick). WP auto-falls-back to the source mime type when the image editor lacks WebP support, so safe to enable unconditionally.

**Doesn't own:** existing uploads (filter applies only to future uploads + any explicit regeneration); alt-text enforcement (that lives in `wchs-admin/assets/admin.js` as a soft submit-time audit).

**Gotchas:**
- Some older iOS Safari versions don't render WebP. WP serves original jpeg/png via `wp_get_original_image_url()` as a fallback on image tags — no action needed, but flag it if you add a new media surface that bypasses WP's image renderer.
- The filter affects sub-size generation, not the originally uploaded file. The original always keeps its source format.
- File: `wp/mu-plugins/headless-media.php`

---

## headless-offline-gateways

**Owns:**
- Dynamic WC gateway registration via `woocommerce_payment_gateways` filter
- Per-gateway config: title, description, instructions, handle/account, payment-link template, QR code rendering
- Stored in `wchs_offline_gateways` option
- Presets: CashApp, Venmo, PayPal.me, Zelle, Bitcoin, custom

**Depends on:** WC payment gateway base class

**Doesn't own:** payment processing (all offline — manual settlement), account reconciliation

**Gotchas:**
- Orders default to on-hold — customer sends payment, admin approves via `headless-order-approval.php`.
- Handle/account plaintext in option. If DB leaks, attacker can swap payment URLs.
- QR encodes the full payment link including order amount — don't share screenshots publicly.

---

## headless-omnisend-compat

**Owns:**
- Launcher v2 JS injection on WP-rendered pages
- Brand ID regex validation (`^[a-f0-9]{20,32}$`)
- Events: `identifyContact` (on email blur), `startedCheckout` (on /checkout), `placedOrder` (on thank-you)

**Depends on:** Omnisend launcher CDN (`launcher-v2.js`), `omnisend_brand_id` setting

**Doesn't own:** signup forms (Omnisend renders them), product sync (separate `omnisend-connect` plugin), order sync (same)

**Gotchas:**
- Brand ID typo = silent disable (regex fails).
- Email sent to Omnisend on blur, BEFORE checkout validation → unverified addresses may land in their CRM.
- CDN-loaded. If launcher-v2.js is down, no tracking + no forms render.

---

## headless-one-click-upsell

**Owns:**
- `woocommerce_get_checkout_order_received_url` filter (intercepts thank-you URL to upsell page)
- Native upsell offer render on Woo's `/checkout/order-received/...` page before the final SPA thank-you handoff
- Variable-product resolution: default variation fallback plus customer-selectable variation controls on the offer page
- Stripe off-session PaymentIntent creation (saved card required) plus offline/deferred add-to-order paths
- Deferred customer-order email flow so the buyer gets one final order email after accept, decline, or expiry
- Expiry fallback via `wchs_upsell_finalize_pending_order`

**Depends on:** WC order + payment method meta, Woo Stripe order meta for off-session charges, `headless-order-redirect.php` for the final SPA thank-you redirect

**Doesn't own:** the final SPA `/order-received` rendering, gateway settlement outside its own accept flow, or refund/reversal logic

**Gotchas:**
- Stripe upsells still require a saved payment method. Offline/BACS/COD/Cheque orders can still show the offer.
- Variable-product offers resolve to a specific variation before pricing or order writes happen.
- Customer emails are deferred while the upsell is pending. Abandoned offer pages fall back to a cron-released final email after TTL expiry.
- Upsell charge failure doesn't roll back the order — customer still got the original product.
- Order bump (`headless-order-bump.php`) runs before this — no interaction conflict.

---

## headless-order-approval

**Owns:**
- Meta box on order detail (HPOS + classic)
- Bulk action on orders list
- Admin POST endpoint `wchs_approve_order` (nonce-protected)
- On click: moves on-hold → processing (triggers WC's processing email)

**Depends on:** WC order status transitions

**Doesn't own:** email templates (WC core), status logic

**Gotchas:**
- Only visible on on-hold orders.
- Bulk approval with no progress UI — very large bulks may timeout.
- Triggers the processing email — if you've customized that, audit before approving real orders.

---

## headless-order-bump

**Owns:**
- `woocommerce_review_order_before_submit` action (renders checkbox)
- AJAX handler on `woocommerce_checkout_update_order_review`
- `wchs_bump` cart item meta marker

**Depends on:** WC cart, `bump_product_id` + `bump_variation_id` settings

**Doesn't own:** product selection (one hard-coded ID), bump pricing (uses product price as-is)

**Gotchas:**
- Out-of-stock bump = WC rejects cart validation. Handle gracefully upstream.
- Meta marker is custom — third-party cart plugins may ignore it.
- Adds bump as a real cart item (affects shipping, coupon eligibility).

---

## headless-order-redirect

**Owns:**
- `woocommerce_get_return_url` filter (rewrites post-payment destination to SPA)
- SPA origin from `wchs_spa_origin()` (same-origin default, custom-mode override when needed)
- Guest orders: appends `billing_email` (needed for Store API access without cookie)
- Logged-in users: omits email (cookie suffices)
- Native thank-you interception for gateways that still land on Woo's `/order-received/` page
- Server-side thank-you action dispatch before redirect so Woo/server-side integrations still fire

**Depends on:** WC order access, `wchs-origin-config.php`

**Doesn't own:** payment processing, SPA rendering, confirmation emails

**Gotchas:**
- Upsell URLs pass through unchanged so the native WP upsell page can render before the final SPA thank-you route.
- `?key=` query param is sensitive (Store API grants order access via it). SPA should strip from browser history.
- Guest email in URL is a phishing vector — SPA should validate email matches order before rendering.
- If the native thank-you template renders, this plugin still injects cart/session cleanup so stale SPA cart state does not linger.

---

## headless-pixels-compat

**Owns:**
- Injection (on WP surfaces only) of: Klaviyo, Meta, TikTok, Pinterest, Clarity, Hotjar, Google Ads
- Email blur → identify event (where supported)
- Thank-you page → Purchase/PlacedOrder events with full order data
- Pixel IDs from `wchs_site_settings`

**Depends on:** each vendor's pixel/snippet APIs

**Doesn't own:** SPA-side tracking (SPA has `analytics.ts`), consent management

**Gotchas:**
- **No consent banner.** GDPR risk without an external consent plugin (see `docs/integrations.md`).
- Email blur fires before validation.
- Pixel IDs plaintext in admin option (readable by shop_manager).
- Running this + SPA's analytics.ts = double-tracking. Reconcile one source per event.

---

## headless-preview-role

**Owns:**
- Restricts `shop_manager` role from:
  - WCHS admin Integrations + Access tabs
  - WC Settings > Payments
  - Tools > Import/Export
  - `/wp-admin/theme-editor.php`, `/plugin-editor.php`
  - Plugin install/update/delete (via `user_has_cap`)
- Masks `easypost_api_key` display on Checkout tab
- Synthetic `manage_options` grant (scoped to WCHS only)
- Omnisend onboarding exception

**Depends on:** WP caps + roles

**Doesn't own:** role definitions (WP core), server-side cap enforcement by third-party plugins

**Gotchas:**
- Hides UI only. A determined shop_manager could still POST to `admin-post.php` directly. Don't rely on this for hard security — rotate credentials if you actually don't trust the user.
- If Omnisend adds a new admin page, exception won't cover it without updating this plugin.

---

## headless-registration-reqs

**Owns:**
- Email verification: 6-digit code emailed, unverified users treated as guests
- `wchs_is_email_verified()` helper
- `wchs_email_verified` user meta
- Address requirement at checkout
- Required fields: name, phone (added to registration form)
- Grandfathering: existing users without the meta are considered verified

**Depends on:** `wp_mail()`, WC my-account, `headless-access-control.php` (blocks unverified users in modes < 3)

**Doesn't own:** email templates, password reset

**Gotchas:**
- No "resend verification" UI — if the user loses the email, they must re-register.
- Address requirement enforced at checkout, not registration. Customer adds address later.
- Grandfathering is permanent — existing users never see the verify flow.

---

## headless-rest-endpoints

**Owns:**
- `GET /wchs/v1/config` — the full config payload consumed by the SPA
- `GET /wchs/v1/reviews/{product_id}` — paginated approved reviews (20/page cap)
- `GET /wchs/v1/reviews/aggregate` — review-slider aggregate summary
- `POST /wchs/v1/reviews/{product_id}` — cookie-authenticated review creation
- `GET /wchs/v1/session` / `DELETE /wchs/v1/session` — cross-origin session introspection + logout
- `GET /wchs/v1/my-orders` — logged-in user's orders (cookie auth)
- `POST /wchs/v1/newsletter` — newsletter signup (Omnisend forward + fallback option buffer)
- `POST /wchs/v1/contact` — contact form submission
- `GET /wchs/v1/order-payment/{id}?key=...` — thank-you/offline-payment detail payload for SPA order pages
- `wchs_rest_rate_limit()` helper + per-bucket limits
- Toggleable rate limiting via `internal_rate_limit_enabled`

**Depends on:** WP REST API, WC

**Doesn't own:** Store API (WC core), review CRUD (delegated to `headless-review-providers.php`)

**Gotchas:**
- Rate limit keyed by `md5(bucket|REMOTE_ADDR)`. Behind a proxy, REMOTE_ADDR must be the real client IP or all visitors share a bucket.
- `WP_DEBUG=true` disables all rate limiting.
- Reviews GET is hard-capped at 20/page; my-orders caps at 50/page.
- Session DELETE is origin-checked because it is a state-changing cookie-backed route.
- Read routes use direct auth-cookie validation because cross-origin SPAs cannot rely on WP REST nonces.
- Newsletter fallback option `wchs_newsletter_signups` capped at 500 entries (oldest purged).

---

## headless-review-providers

**Owns:**
- `WchsReviewProvider` interface
- WooCommerce provider (default — reads `wp_comments`)
- Adapter stubs for Yotpo, Stamped, Reviews.io (incomplete — require code to finish)
- `delete_comment` hook for review image cleanup

**Depends on:** WP comments, provider-specific APIs when swapped

**Doesn't own:** review creation UI (SPA's job), moderation (admin panel)

**Gotchas:**
- Default only returns `approved` comments — no pending reviews shown.
- Yotpo/Stamped/Reviews.io stubs are not finished. Implement before switching `review_provider`.
- Image cleanup on comment delete — soft-deleted comments may orphan images.
- No filter/sort options exposed — endpoint always returns raw list.

---

## headless-legacy-redirects

**Owns:**
- Server-side 301s for legacy Shopify collection/product URLs before the SPA shell handles them.
- `/products/<handle>` redirects to `/product/<slug>` only after confirming the product exists or resolving a configured alias.
- `/collections/<collection>/products/<handle>` product redirects, with fallback to the collection/category landing.
- Per-site product aliases from the `wchs_legacy_product_redirects` option plus the `wchs_legacy_product_redirects` filter.

**Depends on:** `.htaccess` routing `/products`, `/collections`, and `/product-category` to `/index.php`; WooCommerce product/category data; `home_url()` for canonical origins.

**Per-site product aliases:**
```bash
wp option update wchs_legacy_product_redirects '{"old-product-handle":"canonical-product-slug"}' --format=json
```

The option must stay site-specific. Do not bake one merchant's aliases into `bin/templates/htaccess.template` or the default plugin map.

**Gotchas:**
- Unknown `/products/<handle>` links redirect to `/shop` instead of producing a dead `/product/<handle>` shell.
- `/product/<handle>` and bare `/<handle>` are redirected only when the handle is configured as an alias; normal product/page routing is left alone.
- Tracking query parameters (`utm_*`, `gclid`, `fbclid`, `msclkid`) are preserved.
- File: `wp/mu-plugins/headless-legacy-redirects.php`

---

## headless-seo

**Owns:**
- `/sitemap.xml` — XML sitemap listing home, `/shop`, non-empty clean category pages, every published product (`wc_get_products`), and every WCHS page (`AdminPage::get_pages_config`)
- `/robots.txt` — User-agent / Allow / Disallow directives + `Sitemap:` pointer
- `lastmod` per URL: `post_modified_gmt` for products, current time for landing pages

**Depends on:** `wp_loaded` action (fires after WP + WC are booted so `wc_get_products` is available); `home_url()` for canonical origin.

**Doesn't own:** route-specific raw HTML SEO (`headless-seo-shell.php` owns the pre-hydration SPA shell); hydrated client-side SEO (`SEO.svelte` owns that); the native `/wp-sitemap.xml` generator (intentionally left enabled for any surfaces that aren't SPA-routed).

**Gotchas:**
- htaccess rewrites `/sitemap.xml` + `/robots.txt` to `/index.php` so the SPA fallback doesn't swallow them. See `bin/templates/htaccess.template` rule 6f. If deploying to a fresh site, regenerate `.htaccess` from the template.
- Output is `text/plain` for robots, `application/xml` for sitemap, both with `Cache-Control: public, max-age=3600`. SG dynamic cache respects this.
- Excluded from sitemap: draft / private products, custom post types other than `product` + WCHS pages. If you add a new content surface you must add it here.
- File: `wp/mu-plugins/headless-seo.php`

---

## headless-seo-shell

**Owns:**
- Raw HTML SEO for SPA routes that are routed through WordPress before the SPA fallback: `/shop`, `/shop/<category>`, `/product/<slug>`, `/account`, `/order-received`, and one-level WCHS content pages.
- Replaces only the `<!-- STATIC_SEO_START -->...<!-- STATIC_SEO_END -->` block inside the deployed `index.html`; it does not alter the SPA body or bundle URLs.
- Emits route-specific title, description, canonical, Open Graph, Twitter Card, robots/noindex where appropriate, and JSON-LD breadcrumbs/products.

**Depends on:** `.htaccess` rule 6j routing SEO-sensitive SPA paths to `/index.php`; WooCommerce product/category data; `wchs_site_settings`; `wchs_pages_config`; the deployed SPA `index.html`.

**Doesn't own:** checkout/cart/payment endpoints, WP-native pages, hydrated Svelte head tags, or deploy-time homepage fallback metadata.

**Gotchas:**
- The plugin intentionally exits with the same SPA shell body after replacing the head block. This keeps cart/checkout runtime behavior client-side and avoids a Node SSR server.
- Account and order confirmation SPA routes are emitted with `noindex`.
- File: `wp/mu-plugins/headless-seo-shell.php`

---

## headless-smtp

**Owns:**
- `phpmailer_init` hook configuring SMTP auth
- `wchs_smtp_config()` helper (constants > admin settings)
- Constants (preferred, wp-config): `WCHS_SMTP_HOST`, `WCHS_SMTP_PORT`, `WCHS_SMTP_SECURE`, `WCHS_SMTP_USER`, `WCHS_SMTP_PASS`, `WCHS_SMTP_FROM`, `WCHS_SMTP_FROM_NAME`
- FROM field override from admin settings (`smtp_from_email`, `smtp_from_name`)

**Depends on:** WP PHPMailer, PHP SMTP support

**Doesn't own:** email content (WC core, plugins), templates, bounce handling

**Gotchas:**
- Port 25 commonly blocked on shared hosts. Use 465 (SSL) or 587 (STARTTLS).
- Some SMTP providers rewrite FROM at their edge — WC emails may show provider address, not yours.
- No TLS/SSL fallback — if secure connection fails, the whole send fails.
- Credentials plaintext in wp-config (but wp-config is not web-accessible and should be in .gitignore).

---

## headless-tier-pricing

**Owns:**
- Product data tab "Tier Pricing" in WC admin
- Meta keys: `_tiered_price_rules_type` (`fixed` | `percentage`), `_fixed_price_rules`, `_percentage_price_rules`
- Stand-alone admin UI for defining tiers

**Depends on:** WC product data tab API

**Doesn't own:** price math (that's `headless-cro-extension.php`), cart totals, discount application

**Gotchas:**
- Stand-alone — does NOT depend on tier-pricing-table or tier-pricing-table-premium wp-plugins.
- Tier math happens in CRO extension at cart time, not here.
- No UI for converting fixed ↔ percentage once saved — manual meta update needed.

---

## headless-turnstile

**Owns:**
- `wchs_verify_turnstile()` server-side verification helper
- Site key (public → SPA) + secret key (server only)
- Fail-open on network error (avoids locking legitimate users out)
- `anti_bot_enabled` toggle

**Depends on:** Cloudflare Turnstile API (HTTP)

**Doesn't own:** client widget (SPA embeds the Turnstile JS)

**Gotchas:**
- Fails open on Cloudflare outage — spammers get through during an incident.
- `WP_DEBUG=true` disables the feature entirely.
- Tokens expire fast (~5 min). If user stalls at checkout past expiry, regenerate client-side before submit.

---

## wchs-head-scripts

**Owns:**
- `wp_head` (priority 99) + `wp_footer` (priority 99) hooks
- Emits `<script src="..." data-wchs-id="..." [async|defer]>` tags for every entry in `wchs_site_settings['active_scripts']` that is enabled, resolves against the registry, has all required params, isn't shadowed by a `dedicated_setting_key`, and has `'wp'` in `surfaces`.

**Depends on:** `headless-rest-endpoints.php` (reuses `wchs_build_active_scripts()` resolver), `wchs-admin/admin-page.php` (registry + per-site settings).

**Doesn't own:** SPA-side rendering (the SPA consumes `config.active_scripts[]` in `+layout.svelte`), the registry itself (admin curates via Script Registry tab), per-site toggles (shop_manager via Site Scripts tab).

**Gotchas:**
- Priority 99 so scripts land AFTER any theme/plugin additions to head. If you need to go earlier, lower the priority — but GTM/Omnisend pixel-compat mu-plugins run ahead of this.
- Output is byte-identical to the SPA's `<script>` injection because both paths resolve via the same `wchs_build_active_scripts()` helper. Changes to resolution logic affect both surfaces consistently.
- Does NOT emit `<script>` tags on wp-admin — only public-facing WP pages (checkout, my-account, wp-login via theme's wp_footer call).

---

## wchs-admin

**Loader:** `wchs-admin.php` (top-level) bootstraps `wchs-admin/admin-page.php`.

**Owns:**
- `/wp-admin/admin.php?page=wchs-admin` settings menu
- 9 visible tabs: Homepage, Shop, Product page, Pages, Design, Checkout, Integrations, Cutover, Access & Privacy
- Site Scripts + Script Registry management inside the Design tab
- AJAX endpoints: product search, variation lookup
- Storage: `wchs_site_settings`, `wchs_homepage_config`, `wchs_pdp_config`, `wchs_pages_config`, `wchs_shop_config`, `wchs_offline_gateways`
- Helpers consumed by REST: `get_site_settings()`, `get_homepage_config()`, `get_accent_fg()`, etc.

**Depends on:** WP admin menu, WC products/gateways

**Doesn't own:** product CRUD (WC), theme activation (WP), plugin install (WP)

**Gotchas:**
- Settings stored as single serialized PHP option — no per-key schema validation.
- Accent palette hardcoded to 8 colors.
- No settings import/export UI.

### Module subsystem (homepage, shop, PDP, pages)

Module schemas live in `wp/mu-plugins/wchs-admin/modules/*.php` — one file per type. Each exports an associative array with `slug`, `label`, `fields`, `defaults`, and `supports` keys. `supports.color.accent = true` opts the module into the accent override UI.

- `ModuleRegistry` (`wchs-admin/class-module-registry.php`) — loads every schema file at boot and exposes them via `ModuleRegistry::all()`. The `wchs_module_registry` filter lets plugins append/mutate schemas before sanitize runs.
- `SchemaSanitizer` (`wchs-admin/class-schema-sanitizer.php`) — the single path for turning admin-form input into persisted module arrays. Whitelists keys, coerces enum values, strips unknown fields. Also whitelists per-module `overrides` (currently: `accent_color`). This is what `render_homepage_tab() → save_*_settings()` calls — the old hand-rolled `parse_modules_from_post()` switch has been deleted.
- `ResolverService` (`wchs-admin/class-resolver-service.php`) — runs at REST emission time. For every module with an opted-in `supports.color.*`, it walks the cascade (module `overrides` → page-scope defaults → site-scope defaults) and attaches two sibling keys on the REST payload: `resolved` (the merged final value) and `inherited` (which tier each key came from: `"module" | "page" | "site"`). The SPA consumes `resolved` to scope CSS custom properties on the module's root element.
- Admin override UI: `accent_override_swatches()` helper (defined next to `hint_icon()`) emits a hidden input + Default swatch + 8 palette swatches. `admin.js` reads/writes via delegated click handler and stores the value inside `module.overrides` in the modules_json payload. Clearing to Default removes the key entirely.

**When adding a new module**: start with `modules/product_slider.php` as the template. Opt into `supports.color.accent` if you want the override picker. Drop a Svelte component into `spa/src/lib/components/` and accept `config`, optional `resolved?: ModuleResolved`, and apply `style={accentStyle}` on the root element.

---

## wchs-design-system

**Loader:** `wchs-design-system.php` bootstraps `wchs-design-system/src/*.php` classes.

**Owns:**
- `Assets.php` — enqueues shared `tokens.css` + `wc-overrides.css`, dequeues WC's default styles at priority 999
- `ThemeSync.php` — `theme-sync.js` reads `localStorage.wchs_theme` cookie and applies `data-theme` on native WP pages
- `HeaderRenderer.php` — renders the native WP header shell + in-header theme toggle
- `WcOverrides.php` — forces classic cart/checkout (no blocks), custom breadcrumb
- `assets/tokens.css` — color + type + motion tokens (mirrored into SPA via build symlink)
- `assets/wc-overrides.css` — hand-authored WC widget overrides, mobile responsive

**Depends on:** WP enqueue, WC hooks

**Doesn't own:** SPA styling (SPA has its own tokens import), JS on native pages beyond theme-sync

**Gotchas:**
- Priority 999 dequeue — another plugin enqueuing at 999+ will win.
- Theme sync uses `localStorage` — cleared by users = theme resets to default.
- `ToggleRenderer.php` still exists, but the current runtime path uses `HeaderRenderer` instead of the old floating footer toggle.
- `tokens.css` is the shared source of truth. The SPA build imports it at compile time. Don't duplicate tokens in SPA CSS.

---

## wchs-origin-config

**Owns:**
- `wchs_public_origin()`, `wchs_spa_origin()`, `wchs_allowed_origin_list()`, `wchs_return_origin_list()`
- `same-origin` vs `custom` mode resolution
- Legacy `WCHS_*` constant fallback parsing for local dev and intentional split-origin sites
- `wchs_origin_report()` diagnostics consumed by the Cutover tab and verification scripts

**Depends on:** `home_url()`, `siteurl` / `home`, optional legacy `WCHS_SPA_URL`, `WCHS_ALLOWED_ORIGINS`, `WCHS_RETURN_ORIGINS`

**Doesn't own:** CORS headers, login redirects, thank-you redirects, or REST emission by itself — other plugins consume these helpers

**Gotchas:**
- Same-origin mode is the default path. Most production sites should not need any explicit origin constants.
- Custom mode must have a real custom SPA origin or explicit allowlists; otherwise dependent plugins surface alignment errors.
- Legacy `WCHS_*` constants are intentionally ignored in same-origin mode when they disagree with the live public origin, and `wchs_origin_report()` exposes that as a warning instead of silently honoring stale hosts.

---

## Load order

All 30 top-level `.php` files are auto-loaded alphabetically by WordPress. No explicit `require_once` dependencies between plugins — each registers its own hooks at the right priority. The two loader files (`wchs-admin.php`, `wchs-design-system.php`) `require_once` their subdir files explicitly.

Priority conventions in this codebase:
- `0` — earliest (CORS preflight OPTIONS, critical init)
- `1–5` — pre-WP-default (cart lock before Store API processes, access gate before REST routing)
- `10` — default (most hooks)
- `999` — last (asset dequeues so we win over other plugins)

---

## Adding a new mu-plugin

1. Create `wp/mu-plugins/headless-<name>.php`.
2. Add the standard plugin header (Plugin Name, Description, Version, Author lines).
3. Start with `defined( 'ABSPATH' ) || exit;`.
4. Register your hooks — no activation step, no register_activation_hook (mu-plugins don't activate).
5. If you need admin UI: extend the existing WCHS admin tabs (`wchs-admin/admin-page.php`) rather than adding a new WP admin menu.
6. If you need REST routes: add them to `headless-rest-endpoints.php` under the `wchs/v1` namespace (or create a new namespaced route in your file).
7. Deploy to Alyve by pushing deployable code to `main`, or from a generated site folder with `./scripts/purge-and-rebuild.sh`.

Document your new plugin in this file under the alphabetical position.
