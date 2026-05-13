# CLAUDE.md

Orientation guide for agentic coding tools (Claude Code, Cursor, Copilot)
working in this repo. Optimized for pattern-match scanning. Read this
top-to-bottom on first session; skim section headings thereafter.

---

## What this repo is

`wc-headless-starter` — a SvelteKit SPA + WordPress/WooCommerce backend,
deployable to SiteGround-style shared hosts. It is for same-origin
headless WooCommerce stores where the frontend is static and WordPress
continues to own checkout, accounts, admin, and REST.

The SPA handles product catalog, shop grid, PDP, cart, homepage, content
pages. WordPress handles checkout, my-account, payments, order emails,
and returns the config payload that drives the SPA.

## Constraints

- **SiteGround-canonical.** Static SPA (`@sveltejs/adapter-static`) + Apache.
  No Node.js runtime. No Docker in prod. See `docs/siteground-deploy.md`.
- **Single-origin.** SPA and WP live at the same domain via `.htaccess`
  fallback (SPA) + `/wp-*` passthroughs (WP). Dev uses Vite proxy to fake
  same-origin locally.
- **Deploy auto-runs Alyve only on push to main via GitHub Actions** (`.github/workflows/deploy.yml`).
  The workflow deploys to `alyvepeptides.com` via guarded rsync + SSH.
  Doc-only and workflow-only pushes skip. Manual override:
  `bin/templates/deploy-siteground.sh` for ad-hoc cases,
  `purge-and-rebuild.sh` for incremental updates. Never build or rsync
  from the live webroot.
- **All changes must survive SiteGround Dynamic Cache.** REST responses
  get cached at the edge; bust via `site-tools-client -j domain update
  id=<id> flush_cache=1`.

---

## Entry points by task

| If you need to… | Start reading at |
|---|---|
| Understand overall architecture | `docs/architecture.md` |
| Navigate the doc set | `docs/README.md` |
| Recreate demo screenshots | `docs/showcase.md` + `./scripts/seed-showcase.sh` |
| Add / modify an admin setting | `docs/admin-settings-reference.md` + `wp/mu-plugins/wchs-admin/admin-page.php` |
| Modify a mu-plugin | `docs/mu-plugins-reference.md` (find your plugin there first) |
| Add a REST endpoint | `wp/mu-plugins/headless-rest-endpoints.php` |
| Add / modify an SPA component | `spa/src/lib/components/` + `spa/src/routes/` |
| Deploy a new site | `docs/starter-checklist.md` → generated site folder → `./scripts/deploy-siteground.sh` |
| Update an existing site | generated site folder → `./scripts/purge-and-rebuild.sh` |
| Domain cutover | `docs/cutover-checklist.md` |
| Integrate a third-party plugin | `docs/integrations.md` |
| Security / rate limit / CORS | `docs/security.md` |
| Stripe-specific | `docs/stripe-integration.md` |

---

## Data flow (primary path)

```
admin UI (wp-admin → WCHS menu)
  └─> wp/mu-plugins/wchs-admin/admin-page.php save_*_settings()
      └─> wp_options.wchs_site_settings (serialized PHP)
          └─> wp/mu-plugins/headless-rest-endpoints.php /wchs/v1/config
              └─> spa/src/lib/config.svelte.ts (config.load())
                  └─> individual components consume config.data.*
```

- Cache-bust happens at the REST edge (SiteGround). `wp_cache_flush()`
  only helps object cache, not nginx dynamic cache.
- The SPA's `config.ready` gate holds the header/footer until `/wchs/v1/config`
  resolves — don't assume settings are available before hydration.

---

## File ownership — "where do I edit X?"

| Surface | File |
|---|---|
| Admin tab structure + save handlers + UI | `wp/mu-plugins/wchs-admin/admin-page.php` |
| Admin JS (product picker, drag handlers) | `wp/mu-plugins/wchs-admin/assets/admin.js` |
| REST: SPA config payload | `wp/mu-plugins/headless-rest-endpoints.php` (`get_wchs_config()`) |
| REST: reviews, orders, newsletter, contact | Same file — `register_rest_route` calls |
| Payment gateways | Native WooCommerce gateway plugins + `docs/stripe-integration.md` for Stripe notes |
| Payment gateways (offline) | `wp/mu-plugins/headless-offline-gateways.php` |
| Cart mutation serialization | `wp/mu-plugins/headless-cart-lock.php` |
| CORS allowlist + security headers | `wp/mu-plugins/headless-cors.php` (constant `WCHS_ALLOWED_ORIGINS`) |
| Access modes | `wp/mu-plugins/headless-access-control.php` |
| Abandoned cart | `wp/mu-plugins/headless-abandoned-cart.php` |
| SPA entry HTML | `spa/src/app.html` |
| SPA layout + header + footer + nav | `spa/src/routes/+layout.svelte` |
| SPA config types + defaults | `spa/src/lib/config.svelte.ts` |
| SPA theme engine | `spa/src/lib/theme.svelte.ts` |
| SPA cart store | `spa/src/lib/wc/cart.svelte.ts` |
| SPA auth store | `spa/src/lib/wc/auth.svelte.ts` |
| SPA Store API client | `spa/src/lib/wc/store-api.ts` |
| Shared design tokens | `wp/mu-plugins/wchs-design-system/assets/tokens.css` (imported into SPA) |
| WC native page styling (checkout, my-account) | `wp/mu-plugins/wchs-design-system/assets/wc-overrides.css` |

---

## Extension points — where it's safe to add

1. **New admin setting** — touch 5 files in this order (see "Common modifications" below).
2. **New REST endpoint** — `register_rest_route()` in `headless-rest-endpoints.php` under `wchs/v1` namespace. Honor `wchs_rest_rate_limit()` for any public-read endpoint.
3. **New homepage/shop/PDP module** — add to `render_module_template_bank()` in `wchs-admin/admin-page.php` (backend schema) + new Svelte component in `spa/src/lib/components/` + dispatch case in `+layout.svelte` or wherever modules render.
4. **New mu-plugin** — `wp/mu-plugins/headless-<name>.php`. Auto-loads. Add the standard plugin header (see existing mu-plugins for the template). Document in `docs/mu-plugins-reference.md`.
5. **New page** — add to Pages tab in admin → SPA auto-renders at `/{slug}` via `spa/src/routes/[slug]/+page.svelte`. FAQPage JSON-LD auto-emits if slug is `faq` + page has accordion modules.
6. **New third-party script integration** (Alia, chat widget, consent banner, etc.) — add to Script Registry tab (admin) OR edit `REGISTRY_SEEDS` in `wp/mu-plugins/wchs-admin/admin-page.php`. Shop_manager activates per-site via Site Scripts tab. Never paste raw `<script>` — registry is the only supported surface. See `docs/admin-settings-reference.md` § Script Registry.
7. **New language translation** — not yet supported in SPA routing (see `docs/integrations.md` WPML row).

---

## Danger zones — don't touch without reading first

| Zone | Why it's dangerous |
|---|---|
| `headless-login-merge.php` | Workaround for open WC issue #55653. Removing breaks cart merge on login. When WC fixes upstream, delete this file. |
| `wchs_rest_rate_limit()` in `headless-rest-endpoints.php` | Disabled automatically when `WP_DEBUG=true`. Never deploy with WP_DEBUG on. |
| `WCHS_ALLOWED_ORIGINS` / `WCHS_RETURN_ORIGINS` | wp-config constants, NOT admin settings. Adding origins requires editing wp-config.php — there's no UI. |
| `headless-cart-lock.php` | MySQL `GET_LOCK` — won't work on SQLite/Postgres hosts. SiteGround is MySQL so fine. |
| `headless-cart-bridge.php` | Deserializes JWT payload into WC session. Key allowlist validates BEFORE deserializing (defense-in-depth). Don't broaden the allowlist carelessly. |
| `custom_logo` theme_mod vs `logo_dark_id` setting | Primary logo is WP's native `custom_logo` (Customizer). Dark variant is our `logo_dark_id` (WCHS admin). Different storage, different edit paths. |
| Inline theme script in `spa/src/app.html` | Runs before first paint to prevent FOUC. Reads cookie/localStorage/prefers-color-scheme. Changes here affect every page. |
| `wp/mu-plugins/wchs-design-system/assets/tokens.css` | Mirrored into SPA at build time. Don't edit the mirror. Source of truth is the PHP plugin's asset path. |
| Rate limit disabled under WP_DEBUG | Every `/wchs/v1/*` endpoint will accept infinite requests in dev. In prod with `WP_DEBUG=false` it's 10 req/min/IP default. |
| Release tags | Treat published tags as immutable once the repo is public. |

---

## Common modifications (worked examples)

### Add a new admin setting — e.g. `header_sticky` bool

Touches 5 files:

1. `wp/mu-plugins/wchs-admin/admin-page.php`:
   - Add `'header_sticky' => true` to `$defaults` array (in `get_site_settings()`)
   - In `save_appearance_settings()`: `$s['header_sticky'] = ! empty( $_POST['header_sticky'] );`
   - In `render_appearance_tab()`: add a checkbox input near other header toggles
2. `wp/mu-plugins/headless-rest-endpoints.php`:
   - Add to the config emission array around line ~570: `'header_sticky' => (bool) ( $site_settings['header_sticky'] ?? true ),`
3. `spa/src/lib/config.svelte.ts`:
   - Add `header_sticky: boolean;` to `ConfigData` type
   - Add `header_sticky: true,` to `defaultConfig`
4. `spa/src/routes/+layout.svelte`:
   - Consume `config.data.header_sticky` on the `<header>` element (toggle `position: sticky` vs `position: static`)
5. (Optional) `docs/admin-settings-reference.md`:
   - Add a row to the relevant tab's table

Deploy: push deployable code to `main` for the normal Alyve-only GitHub Actions path, or run `./scripts/purge-and-rebuild.sh` from a generated site folder for a manual hotfix.

### Add a new REST endpoint — e.g. `GET /wchs/v1/shipping-zones`

```php
// In wp/mu-plugins/headless-rest-endpoints.php
add_action( 'rest_api_init', function () {
  register_rest_route(
    'wchs/v1',
    '/shipping-zones',
    [
      'methods'             => 'GET',
      'callback'            => 'wchs_rest_shipping_zones',
      'permission_callback' => '__return_true', // public read
    ]
  );
} );

function wchs_rest_shipping_zones( \WP_REST_Request $request ) {
  wchs_rest_rate_limit( 'shipping-zones', 20, 60 ); // 20 req/min
  // ... build + return zones
}
```

Always call `wchs_rest_rate_limit()` on public-read endpoints. Rate limit disabled in dev (WP_DEBUG=true), strict in prod.

### Add a new homepage module — e.g. `spotlight`

Backend (schema-driven, single source of truth):
1. `wp/mu-plugins/wchs-admin/modules/spotlight.php`: export a schema array with `slug`, `label`, `fields`, `defaults`, and `supports` keys. `ModuleRegistry` auto-loads every `modules/*.php` file at boot. Use `product_slider.php` as a template.
2. `wp/mu-plugins/wchs-admin/admin-page.php`: add a `<template>` block in `render_module_template_bank()` matching the schema's fields.
3. `SchemaSanitizer` picks up the registered module automatically — no save-handler changes. Opt in to `supports.color.accent = true` if you want the per-module accent override picker.
4. `ResolverService` attaches `resolved` + `inherited` keys to every REST module with opted-in supports — no REST-layer changes.

Frontend:
1. `spa/src/lib/components/Spotlight.svelte` — new component consuming `config` + optional `resolved?: ModuleResolved`. If the module opts into color overrides, apply `style="--accent: {resolved.accent_color}"` on the root element to scope the cascade.
2. In whichever route renders modules (`+page.svelte` for homepage, `[slug]/+page.svelte` for pages), add a case to the module switch and pass `resolved={mod.resolved}` alongside `config={mod.config}`.

See `docs/mu-plugins-reference.md#module-subsystem-homepage-shop-pdp-pages` for the full subsystem overview.

---

## Code conventions

- **No comments unless WHY is non-obvious.** Comments describe constraints, workarounds, or surprises — not what the code literally does.
- **Always light+dark theming.** Colors via `var(--fg)`, `var(--bg)`, `var(--border)`, `var(--accent)`. No hardcoded hex in components.
- **Pretext for measured text.** If layout depends on text dimensions (price, truncated title, nav label), use `pretext.measure()`. See `PRETEXT_RULE.md`.
- **Monetary values in minor units (cents).** Store API + our `wchs_cro` extension both use integers. SPA must not float-math prices.
- **Svelte 5 runes.** `$state`, `$derived`, `$effect`, `$props`. No `writable()`/`readable()`. See existing components.
- **Svelte 5 snippets.** `{@render ...}` + `{#snippet ...}`. No `<slot>`.
- **No build-time SSR.** `adapter-static` only. Keep runtime data loading in the browser or in WordPress.
- **Tutorial copy in admin: prefer `hint_icon()` over `.wchs-info` blocks.** Transient, hover-revealed tips beat printed walls. See `docs/admin-settings-reference.md#admin-helpers` for the helper signatures (`hint_icon`, `accent_override_swatches`). Keep structured multi-paragraph documentation or colored banners (success/warning) as-is.

More: `PROJECT_RULES.md`, `PRETEXT_RULE.md`.

---

## Testing

- **Local dev:** `./scripts/up.sh` (docker-compose) + `./scripts/seed.sh` (installs WP + WooCommerce + seeds products) + `cd spa && npm run dev`.
- **Showcase seed:** `./scripts/seed-showcase.sh` replaces the local catalog with Northstar Supply demo content and WCHS settings. It refuses non-local `WP_SITE_URL` values by default and deactivates active non-WooCommerce plugins unless `WCHS_SHOWCASE_KEEP_PLUGINS=1`.
- **Local tests:** `tests/` — Playwright against the dev stack. `node tests/e2e-smoke.js`.
- **Type check:** `cd spa && npm run check`.
- **Build:** `cd spa && npm run build` — produces `build/` static files.

SiteGround captcha may intermittently block Playwright from outside the LAN. If tests fail with `title: "Robot Challenge Screen"`, retry or run from inside the site via SSH.

---

## Deploy

**Primary path**: push deployable code to `main` → GitHub Actions (`.github/workflows/deploy.yml`) deploys Alyve only. Doc-only and workflow-only pushes skip. Include `[skip deploy]` in a commit message to skip a specific push. The workflow builds from GitHub checkout and deploys guarded artifacts to SiteGround; the live webroot is never a build source.

**Manual override**: generate a site snapshot with `bin/snapshot-template.sh ~/dev/sites/<site>`, then run `./scripts/deploy-siteground.sh` from that generated site folder. Reads the per-site `.env`, rsyncs over SSH, triggers wp-cli setup, flushes cache.

**Incremental**: `./scripts/purge-and-rebuild.sh` from a generated site folder — for code-only ad-hoc updates (before/outside the CI/CD flow).

**Cutover** to production domain: `./scripts/cutover-domain.sh` from a generated site folder — handles WCHS domain alignment, email/DNS checks, and DB search-replace. Gateway dashboards still need human verification.

---

## Non-obvious facts

- `tests/critique/` is a regenerable artifact directory and should stay gitignored.
- The WCHS cart, order bumps, and one-click upsells are native SPA +
  mu-plugin code. No third-party cart/funnel plugin is required.
- `SlideCart.svelte` keeps a legacy cart CSS prefix for selector stability.
  Treat it as WCHS-owned markup, not a plugin dependency.
- `cart.svelte.ts` dispatches legacy cart DOM events for analytics snippets
  that already bound to those names.

---

## When in doubt

1. Is this a storefront / cart / checkout change? → Check `docs/architecture.md` first.
2. Is this a setting change? → `docs/admin-settings-reference.md`.
3. Is this a mu-plugin change? → `docs/mu-plugins-reference.md`.
4. Is this a deploy question? → `docs/siteground-deploy.md`.
5. Is this a third-party plugin question? → `docs/integrations.md`.
6. Still stuck? → grep the codebase, don't guess. File ownership table above.

Don't add features or refactor beyond the task. Don't add error handling for scenarios that can't happen. Don't narrate implementation — show the diff.
