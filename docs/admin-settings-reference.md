# Admin Settings Reference

Every configurable option, indexed by admin tab. The current visible tab
order in the icon-rail editor is: `Homepage`, `Shop`, `Product page`,
`Pages`, `Design`, `Checkout`, `Integrations`, `Cutover`, `Access & Privacy`.
All values live in the
`wchs_site_settings` WP option (plus `wchs_homepage_config`,
`wchs_pdp_config`, `wchs_pages_config`, `wchs_shop_config`,
`wchs_offline_gateways` for tab-specific blobs). The SPA reads them via
`/wp-json/wchs/v1/config`.

**Source of truth:** `wp/mu-plugins/wchs-admin/admin-page.php` —
`$defaults` array in `get_site_settings()` and the per-tab
`save_*_settings()` methods. SPA contract:
`spa/src/lib/config.svelte.ts`.

---

## Design tab

Controls the look of the header, footer, and theme behavior.

### Header

| Admin field | Option key | Type | Default | Notes |
|---|---|---|---|---|
| Accent color | `accent_color` | string (hex) or null | null | One of 8 preset swatches. Emitted as `--accent` CSS var. `accent_fg` is derived automatically for contrast. |
| Header links | `header_links[]` | array of `{label,url,display,icon,accent,mobile_pin}` | Shop + Account | `display`: `text` / `icon` / `both`. `icon` key must exist in `spa/src/lib/icons.ts`. `mobile_pin` up to 3 inline-on-mobile. |
| Theme toggle accent | `header_toggle_accent` | bool | true | Render theme toggle with accent background. |
| Cart accent | `header_cart_accent` | bool | true | Render cart button with accent background. |
| Invert header colors | `header_inverted` | bool | false | Dark-on-light / light-on-dark header variant. |
| Borderless icons | `header_borderless` | bool | false | Strip 1px border from icon-only header buttons. |
| Show theme toggle | `header_show_toggle` | bool | true | Hide toggle entirely on all surfaces. |
| Hamburger side (mobile) | `mobile_hamburger_side` | `'left'` / `'right'` / `'off'` | `'right'` | `off` = no drawer, all items inline. |
| Pin cart on mobile | `header_cart_mobile_pin` | bool | true | Inline cart on mobile vs in drawer. |
| Pin theme toggle on mobile | `header_toggle_mobile_pin` | bool | false | Inline toggle on mobile vs in drawer. |

### Theme + logo

| Admin field | Option key | Type | Default | Notes |
|---|---|---|---|---|
| Default theme on first load | `theme_default` | `'system'` / `'light'` / `'dark'` | `'system'` | First-visit theme. Toggle + stored user preference always override. |
| Auto-invert logo on dark | `logo_invert_on_dark` | bool | true | CSS `filter: invert(1)` on `<img>` when `[data-theme='dark']`. Suppressed if `logo_dark_id` is set. |
| Dark-mode logo (optional) | `logo_dark_id` | int (WP attachment ID) | 0 | When set, emits `logo_dark_url` to SPA; renders via CSS `display` swap; auto-invert is skipped. |
| Logo size (desktop) | `logo_size` | `'compact'` / `'standard'` / `'prominent'` / `'xl'` | `'standard'` | 24/32/40/56px. Mobile stays 24–28px. Header padding scales inversely. |
| Logo / brand position (desktop) | `brand_position` | `'left'` / `'center'` | `'left'` | Absolute-positions brand on desktop. Mobile always centered. |

### Footer

| Admin field | Option key | Type | Default | Notes |
|---|---|---|---|---|
| Footer columns | `footer.columns[]` | array of `{title, links[]}` | empty | Rendered as 4-col grid (auto-fit). |
| Footer tagline | `footer.tagline` | string | `''` | Shown under brand name in footer. |
| Social links | `social_links[]` | array of `{platform, url}` | empty | Whitelist: `instagram`, `facebook`, `x`, `twitter`, `youtube`, `linkedin`, `tiktok`, `pinterest`. |

### Design tokens

Site-wide CSS variables surfaced to the SPA as `--wchs-*`. Null values are no-ops — component defaults stay in effect. Setting any value cascades to every place the token is wired.

| Admin field | Option key | Type | Range | Notes |
|---|---|---|---|---|
| Corner radius (px) | `tokens.radius` | int \| null | 0–32 | → `--wchs-radius`. Applied to CTA buttons (opt-in via `var(--wchs-radius, 0)` in component CSS). |
| Vertical spacing — Compact | `tokens.spacing_v_compact` | int \| null | 0–48 | → `--wchs-spacing-v-compact`. Used by `.is-v-compact` classes on module sections. |
| Vertical spacing — Normal | `tokens.spacing_v_normal` | int \| null | 16–96 | → `--wchs-spacing-v-normal`. Default `.is-v-normal` uses per-component px values as fallback. |
| Vertical spacing — Spacious | `tokens.spacing_v_spacious` | int \| null | 48–160 | → `--wchs-spacing-v-spacious`. Applied to modules with `spacing_v='spacious'`. |

**Adoption:** components opt-in by using `padding: var(--wchs-spacing-v-normal, 40px)` or equivalent. CTA module uses `--wchs-radius` + the spacing triple. Other modules (Hero, Trust Bar, etc.) still use hardcoded px until progressively migrated.

---

## Checkout tab

| Admin field | Option key | Type | Default | Notes |
|---|---|---|---|---|
| Upsell enabled | `upsell_enabled` | bool | false | Post-purchase one-click upsell flow (requires Stripe saved PM). |
| Bump product ID | `bump_product_id` | int | 0 | Order bump product shown at checkout. |
| Bump variation ID | `bump_variation_id` | int | 0 | If bump is a variable product. |
| Address validation enabled | `address_validation_enabled` | bool | false | EasyPost address verification before Place Order. |
| Address validation mode | `address_validation_mode` | `'strict'` / `'moderate'` | `'moderate'` | strict = reject corrections; moderate = offer to apply them. |
| EasyPost API key | `easypost_api_key` | string | `''` | Plaintext in DB (admin-only visible). |
| Google Maps API key | `google_maps_api_key` | string | `''` | Autocomplete provider at checkout. |
| Abandoned cart enabled | `abandoned_cart_enabled` | bool | true | Recovery email via WP-Cron + SMTP. |

---

## Integrations tab

All IDs are plaintext in the option; empty string = integration disabled.

| Admin field | Option key | Type | Default | Provider script |
|---|---|---|---|---|
| GTM ID | `gtm_id` | string | `''` | Google Tag Manager |
| Omnisend brand ID | `omnisend_brand_id` | string (hex 20–32) | `''` | Omnisend launcher v2 |
| Klaviyo public key | `klaviyo_public_key` | string | `''` | Klaviyo on-site JS |
| Meta pixel ID | `meta_pixel_id` | string | `''` | Facebook/Instagram |
| TikTok pixel ID | `tiktok_pixel_id` | string | `''` | TikTok Pixel |
| Pinterest tag ID | `pinterest_tag_id` | string | `''` | Pinterest Tag |
| Clarity project ID | `clarity_project_id` | string | `''` | Microsoft Clarity |
| Hotjar site ID | `hotjar_site_id` | string | `''` | Hotjar |
| Google Ads conversion ID | `google_ads_conversion_id` | string | `''` | Google Ads |
| Google Ads conversion label | `google_ads_conversion_label` | string | `''` | Paired with conversion ID |
| Review provider | `review_provider` | `'woocommerce'` / `'yotpo'` / `'stamped'` / `'reviewsio'` | `'woocommerce'` | Default reads `wp_comments`. Others need adapter in `headless-review-providers.php`. |
| Review provider keys | `review_provider_keys` | assoc array | `{}` | Per-provider API keys. |

SMTP is configured via wp-config constants (preferred) OR this tab. See `docs/security.md`.

---

## Cutover tab

Domain ownership and post-cutover operational checklist.

| Admin field | Option key | Type | Default | Notes |
|---|---|---|---|---|
| Origin mode | `domain_origin_mode` | `'same-origin'` / `'custom'` / `''` | `''` | Empty means "derive automatically." Runtime resolves to `same-origin` unless the site looks like local dev with legacy overrides present. |
| Custom SPA origin | `custom_spa_origin` | string | `''` | Only used when origin mode is `custom`. |
| Custom allowed origins | `custom_allowed_origins[]` | string[] | `[]` | One origin per line in the UI. Used for Store API CORS allowlist in custom mode. |
| Custom return origins | `custom_return_origins[]` | string[] | `[]` | One origin per line in the UI. Used for login/account return redirects in custom mode. |
| Cutover checklist | `cutover_checklist` | `{domain, items, updated_at}` | empty | Tracked per current public domain. Resets automatically when `home_url()` host changes. |
| Guided cutover candidate | `cutover_candidate_domain` | string | `''` | Final production domain used by the guided Cutover action. Prefills from the current admin request host when it differs from the live public domain. |
| Last guided cutover from | `last_cutover_from_domain` | string | `''` | Previous public domain recorded by the guided finalize action. |
| Last guided cutover to | `last_cutover_to_domain` | string | `''` | Final public domain recorded by the guided finalize action. |
| Last guided cutover at | `last_cutover_at` | MySQL datetime | `''` | Timestamp of the most recent guided finalize action. |

### Runtime behavior

- `same-origin` mode is the new normal path.
  WCHS resolves checkout redirects, login returns, CORS allowlists, admin previews, and `/wp-json/wchs/v1/config` from `home_url()` automatically.
- `custom` mode is for split-host or staging cases where the SPA really does live on a different origin.
- Legacy `WCHS_*` constants are still read as a fallback source for custom mode and local dev, but in same-origin mode they are treated as legacy overrides and surfaced as warnings if they no longer match the live site domain.

### Guided cutover

- `Preview guided cutover` validates the candidate domain, same-origin mode, current runtime health, and HTTPS reachability.
- `Finalize cutover` updates `siteurl` + `home`, refreshes a writable `robots.txt` sitemap line if present, flushes caches, and redirects `wp-admin` to the new domain.
- Guided cutover does **not** run a DB-wide search-replace. Use the CLI cutover script for older or full-content migrations.

The public config endpoint now emits:

- `wp_origin`
- `spa_origin`
- `origin_mode`
- `allowed_origins[]`
- `return_origins[]`

---

## Access & Privacy tab

| Admin field | Option key | Type | Default | Notes |
|---|---|---|---|---|
| Access mode | `access_mode` | int `0`–`3` | `3` | 0=Maintenance (503), 1=Locked (guests get 403), 2=Browse-only (catalog open, checkout locked), 3=Open. |
| Anti-bot enabled | `anti_bot_enabled` | bool | false | Cloudflare Turnstile at checkout/login/register. |
| Turnstile site key | `turnstile_site_key` | string | `''` | Public (sent to SPA). |
| Turnstile secret key | `turnstile_secret_key` | string | `''` | Server-side verification only. |
| Internal rate limit enabled | `internal_rate_limit_enabled` | bool | true | Disabled automatically when `WP_DEBUG=true`. |
| Block cart/checkout from robots | `seo_block_cart_checkout` | bool | false | Appends `Disallow: /cart /checkout` to robots.txt. |
| Nosnippet on products | `seo_nosnippet_products` | bool | false | Adds `<meta name="robots" content="nosnippet">` to PDPs. Useful for regulated SKUs. |
| Require email verification | `reg_require_email_verify` | bool | false | 6-digit code emailed at registration. Unverified users = guests. |
| Require shipping address | `reg_require_address` | bool | false | Enforced at checkout. |
| Require name | `reg_require_name` | bool | false | Adds first/last name fields to registration. |
| Require phone | `reg_require_phone` | bool | false | Adds phone field to registration. |

---

### Site gate modal

Optional site-gate modal shown on first visit.

| Admin field | Option key | Type | Default | Notes |
|---|---|---|---|---|
| Gate enabled | `gate_modal.enabled` | bool | false | |
| Strict | `gate_modal.strict` | bool | false | Block page interaction until accepted. |
| Title | `gate_modal.title` | string | `''` | |
| Content (HTML) | `gate_modal.content` | string | `''` | Allowed: basic safe HTML. |
| Confirm text | `gate_modal.confirm_text` | string | `'Enter Site'` | |
| Decline text | `gate_modal.decline_text` | string | `''` | If empty, no decline button. |
| Decline URL | `gate_modal.decline_url` | string | `''` | Where to send decliners. |
| Version | `gate_modal.version` | int | 1 | Bump to force re-acceptance (clears stored consent cookie). |

---

## Homepage tab

Stored under `wchs_homepage_config` option.

### Hero

| Admin field | Key | Type | Default | Notes |
|---|---|---|---|---|
| Headline | `hero.headline` | string | "Commerce, unbundled." | |
| Hero body | `hero.content_mode` | `'text' \| 'logo'` | `'text'` | `logo` keeps `hero.headline` as the semantic H1 while the visible hero body becomes an image. |
| Logo source | `hero.logo_source` | `'site_logo' \| 'custom'` | `'site_logo'` | Site logo uses the full-size logo assets from the Design tab/header logo pipeline. |
| Hero logo | `hero.logo_url` | URL string | `''` | Used only when `hero.logo_source='custom'`. |
| Hero logo dark | `hero.logo_dark_url` | URL string | `''` | Optional alternate asset for dark-mode hero rendering. |
| Hero logo size | `hero.logo_size` | `'standard' \| 'large' \| 'statement'` | `'large'` | Only affects visible logo mode. |
| Headline size | `hero.headline_size` | `'s' \| 'm' \| 'l' \| 'xl'` | `'l'` | |
| Headline weight | `hero.headline_weight` | `'light' \| 'regular' \| 'medium' \| 'semibold' \| 'bold' \| 'extrabold' \| 'black'` | `'medium'` | |
| Headline font | `hero.headline_font` | `'inter'` or any Bunny font | `'inter'` | Non-Inter fonts loaded lazily via `hero-fonts.ts`. |
| Text color mode | `hero.text_color_mode` | `'theme' \| 'white' \| 'black' \| 'accent'` | `'theme'` | Force hero copy color independent of page theme. |
| Subheadline | `hero.subheadline` | string | — | |
| Subheadline size | `hero.subheadline_size` | size preset | `'m'` | |
| CTA text | `hero.cta_text` | string | `"Enter the shop"` | |
| CTA link | `hero.cta_link` | string | `/shop` | |
| Variant | `hero.variant` | `'webgl-noise'` / `'2'` / `'3'` / `'4'` / `'5'` / `'6'` | `'webgl-noise'` | 6 WebGL variants in `spa/src/lib/components/`. |

### Modules

`wchs_homepage_config.modules[]` — array of typed blocks. Current types: `accordion`, `hero`, `trust_bar`, `text_block`, `gallery`, `category_grid`, `split_features`, `product_slider`, `review_slider`, `shop_grid`, `contact_form`, `cta`, `spacer`, `logo_strip`, `video`. See `wp/mu-plugins/wchs-admin/modules/*.php` for the schema per type (slug, label, supports, fields, defaults) and `admin-page.php` `render_module_template_bank()` for the admin-UI field templates.

**Module shape (common fields — all types):**

```
{
  "id":         "8-char stable",  // assigned by SchemaSanitizer on first save
  "type":       "cta" | ...,
  "visibility": "all" | "members" | "guests",
  "spacing_v":  "compact" | "normal" | "spacious",
  "spacing_h":  "compact" | "normal" | "spacious",
  "center_header": bool,
  "overrides":  { "accent_color"?: "#hex", "typography"?: {...} },
  "start_at":   "ISO-8601" | undefined,   // scheduled-publishing: show from
  "end_at":     "ISO-8601" | undefined,   // scheduled-publishing: show until
  "config":     { ... type-specific ... }
}
```

**Scheduled publishing:** both `start_at` and `end_at` are optional. Validation: ISO-8601 via `strtotime()`, normalized to UTC `gmdate('c')`. SPA filters client-side via `isModuleVisibleNow()` in `spa/src/lib/config.svelte.ts`. Admins see all modules when they append `?preview=1` to the SPA URL.

**Analytics hooks:** every module's `id` is emitted on a `display: contents` wrapper around the dispatch as `data-module-type="X" data-module-id="Y"`. GTM / pixel integrations can bind to either. IDs are stable across reorder + config edits.

**Context scoping (`supports.contexts`):** each module schema declares which pages it can be inserted on (`homepage`, `shop`, `pdp`, `pages`). `SchemaSanitizer` enforces on save — crafted POSTs with a mismatched type are rejected.

---

## Shop tab

Stored under `wchs_shop_config`.

| Admin field | Key | Type | Default | Notes |
|---|---|---|---|---|
| Min columns | `cols_min` | int | 2 | Grid auto-fit lower bound. |
| Max columns | `cols_max` | int | 4 | Grid auto-fit upper bound. |
| Edge-to-edge | `edge_to_edge` | bool | false | Full-width grid (no max-width container). |
| Modules | `modules[]` | array | `[]` | Same module schema as homepage; injected above/below grid. |

---

## Product Page (PDP) tab

Stored under `wchs_pdp_config`.

| Admin field | Key | Type | Default | Notes |
|---|---|---|---|---|
| Show reviews | `show_reviews` | bool | true | Toggle entire reviews section. |
| Modules | `modules[]` | array | `[]` | Injected below the gallery/specs; same module schema as homepage. |

---

## Pages tab

Stored under `wchs_pages_config`.

Each page is `{slug, title, modules[]}`. Rendered at `/{slug}`. Common uses: `/about`, `/faq`, `/shipping-returns`, `/privacy-policy`, `/terms-of-service`.

FAQPage JSON-LD is auto-emitted when a page's slug is `faq` AND at least one module is an accordion.

---

### Site Scripts (within Design tab; shop_manager visible)

Per-site activation + params for curated third-party scripts (Alia, Omnisend, GTM, Klaviyo, Cookiebot). Admin curates the list in Script Registry; shop_manager picks which to turn on and fills in per-site values.

Stored as `wchs_site_settings['active_scripts']` — array of `{id, enabled, params}` rows. `id` must match a registry entry.

Each registered script renders with name + description (read-only from registry), enabled checkbox, per-param inputs, and read-only metadata showing source / placement / surfaces so shop_manager understands what's being loaded without controlling it. Shows a warning when `dedicated_setting_key` (e.g. `gtm_id`) is already populated — in that case the generic injection is server-side skipped to avoid double-firing.

Save handler `save_active_scripts_settings()` uses cap `manage_woocommerce`. Only reads `$_POST['active_scripts']`, never touches any other site-settings key. Param values are schema-sanitized against the registry — unknown keys silently dropped.

### Script Registry (within Design tab; real-admin only)

Admin-curated whitelist of approved scripts. IP-protection surface — only real administrators can edit `src_template` URLs, param schemas, or placement. Stored as `wchs_script_registry` (separate option). Saved option merges **over** `REGISTRY_SEEDS` in `admin-page.php` — removing an entry reverts to the seed.

Each registry entry:

| Field | Type | Description |
|---|---|---|
| `id` | slug | Unique identifier. Referenced by `active_scripts[].id` |
| `name`, `description` | string | UI labels |
| `src_template` | URL | External script URL without query string; params appended server-side |
| `params[]` | array of `{key, label, required, type, example}` | Param schema |
| `attributes.async`, `.defer` | bool | Render as `<script async/defer>` |
| `placement` | `'head' \| 'body_end'` | DOM insertion point |
| `surfaces[]` | `Array<'spa' \| 'wp'>` | Which page types inject it |
| `dedicated_setting_key` | string | Optional — suppresses generic injection when the matching `wchs_site_settings` key is already populated |

Save handler `save_script_registry_settings()` uses `wchs_is_real_admin()`. URLs via `esc_url_raw`, placements/surfaces/param types whitelist-checked, unknown keys dropped.

### Seeded entries

`alia`, `gtm`, `omnisend`, `klaviyo`, `cookiebot`. GTM/Omnisend/Klaviyo set `dedicated_setting_key` to their existing pixel-integration keys so activating here is noop'd if the dedicated path is wired.

### Adding new entries

Edit `REGISTRY_SEEDS` in `wp/mu-plugins/wchs-admin/admin-page.php` OR patch `wp_options.wchs_script_registry` via wp-cli. Inline "add entry" UI is deferred.

### Data flow

```
admin → Script Registry tab          →  wp_options.wchs_script_registry
                                              ↓ merged over REGISTRY_SEEDS
shop_manager → Site Scripts tab      →  wp_options.wchs_site_settings.active_scripts
                                              ↓ joined + filtered by:
wchs_build_active_scripts()          →  (helper in headless-rest-endpoints.php)
                                              ↓
                     ┌────────────────────────┴────────────────────────┐
                     │                                                 │
        /wchs/v1/config → config.active_scripts[]      wp_head/wp_footer <script>
                     ↓                                                 │
        +layout.svelte $effect:                           (via wchs-head-scripts.php)
        for each entry with 'spa' in surfaces,
        create <script data-wchs-id="{id}">
```

---

### Offline payment methods (within Checkout tab)

Stored under `wchs_offline_gateways`. Configures the offline gateways mu-plugin.

Each gateway: `{preset, title, description, instructions, handle, payment_link_template}`. Presets: CashApp, Venmo, PayPal.me, Zelle, Bitcoin, custom.

Card gateways are configured separately through the selected WooCommerce gateway plugin (`wp-admin → WooCommerce → Settings → Payments`). See `docs/stripe-integration.md` for Stripe notes.

---

## Product card (under Design tab)

Stored under `wchs_site_settings['product_card']` — 14 keys governing shop-grid, slider, cross-sell card visuals. Saved via SchemaSanitizer; unknown enum values fall back to the default on next save.

| Admin field | Key | Type | Default | Notes |
|---|---|---|---|---|
| Media aspect ratio | `media_aspect_ratio` | `'1:1'` / `'4:5'` / `'3:4'` / `'2:3'` | `'1:1'` | CSS `aspect-ratio` on `.store-card__media`. |
| Corner radius | `corner_radius` | `'square'` / `'soft'` / `'round'` / `'pill'` | `'square'` | 0 / 4 / 8 / 16 px. Forced to 0 when `border = bottom-only`. |
| Border | `border` | `'full'` / `'bottom-only'` / `'none'` | `'full'` | Topology of the card outline. |
| Hover effect | `hover_effect` | `'lift'` / `'shadow'` / `'none'` | `'lift'` | translateY vs box-shadow vs no-op. Shadow composes with OOS grayscale. |
| Button style | `button_style` | `'solid'` / `'outline'` / `'icon-only'` | `'outline'` | Add-to-cart visual. `icon-only` uses muted fg when OOS. |
| Badge position | `badge_position` | `'top-left'` / `'top-right'` / `'bottom-left'` / `'bottom-right'` | `'top-right'` | Absolute-positioned badge anchor. |
| Badge style | `badge_style` | `'filled'` / `'outline'` / `'minimal'` | `'filled'` | `minimal` is text-only with a subtle rgba text-shadow for legibility on any background. |
| Show bulk-pricing badge | `show_bulk_badge` | bool | true | When false, on-sale takes priority even if a bulk tier is active. |
| Show tier hint | `show_tier_hint` | bool | true | Subtle "Save X%" hint under price for tier-pricing products. |
| Show OOS cards | `show_oos_cards` | bool | true | When false, ShopGrid + ProductSlider filter OOS products from the results; CartCrossSellStrip intentionally ignores this flag (curated list). |
| OOS treatment | `oos_treatment` | `'grayscale'` / `'dim'` / `'none'` | `'grayscale'` | CSS filter on `.is-oos` cards. OOS badge always shows on-card regardless of this. |
| Title lines | `title_lines` | `'1'` / `'2'` / `'auto'` | `'auto'` | `auto` uses pretext to measure + set `height` inline; `1`/`2` use `-webkit-line-clamp`. |
| Secondary image on hover | `secondary_image_on_hover` | bool | false | Renders a second `<img>` (fade-in on hover) only when the product has ≥2 gallery images. |
| Sale badge text | `sale_badge_text` | string | `'Sale'` | Supports `{percent}` placeholder. Renders literal if placeholder absent; falls back to `'Sale'` when `{percent}` would interpolate 0. |

**Badge priority** when multiple flags are true on the same card: OOS > sale > bulk tier.

---

## Admin helpers

Two helpers on `WCHS_Admin` are worth knowing about when editing `admin-page.php`:

### `hint_icon( string $text, array $opts = [] ): string`

Renders a question-mark icon with a hover-revealed tooltip. Prefer this over `.wchs-info` blocks for single-sentence tutorial copy — the goal is transient, user-initiated guidance rather than walls of printed instructions. `$text` is escaped and placed into `data-tip`. `$opts['placement']` accepts `'top' | 'bottom' | 'left' | 'right'` (default `'top'`). Keep the full Checkout / Integrations / Access & Privacy tabs as reference examples.

### `accent_override_swatches( string $data_field = 'overrides_accent_color' ): string`

Emits the per-module accent override picker used inside module modals that opt into `supports.color.accent`. Outputs a hidden input keyed by `data-field` + a "Default" swatch + 8 palette swatches. `admin.js` has the click handler + read/write integration in `readModuleFields` / `populateModuleFields`. On the SPA side, the resolved value lands in `module.resolved.accent_color` and should be applied as `style="--accent: {value}"` on the component's root element.

Both helpers live next to each other in `admin-page.php` — search for `public static function hint_icon`.

### Text editors

Three intentional editor classes — don't invent a fourth:

- **Plain input / textarea** — titles, labels, IDs, URLs, hero headline + subheadline. Any single-line or short multi-line field that stores plain text.
- **Modal WYSIWYG** (`data-wysiwyg="1"`, driven by `initModalWysiwyg()` in `admin.js`) — lightweight TinyMCE with `bold italic underline | bullist numlist | link | removeformat`. Use for repeatable rich-text item fields: accordion answers, text_block content, split_features descriptions.
- **`wp_editor()` full** — gate modal content only. Full WP TinyMCE with complete toolbar + media insert. Reserved for standalone site settings where the merchant expects WordPress-parity formatting.

Hero headline/subheadline are intentionally plain text (no rich formatting) — the hero is typographic, not content-rich. When adding a new module with an item-description field, use `data-wysiwyg="1"` not `wp_editor()`.

---

## Runtime-derived options

These values are emitted to the SPA config or consumed by runtime plugins, but they are not stored as plain editable fields in `wchs_site_settings`:

| Key | Set via | Type | Notes |
|---|---|---|---|
| `wp_origin` | `home_url()` / `siteurl` | string | Canonical public WP origin. |
| `spa_origin` | `wchs_spa_origin()` | string | Same-origin by default. Custom mode or legacy constants can override it. |
| `allowed_origins` | `wchs_allowed_origin_list()` | string[] | Effective CORS allowlist derived from same-origin/custom mode plus any legacy fallback overrides. |
| `return_origins` | `wchs_return_origin_list()` | string[] | Effective login/account return allowlist derived from the same source as above. |
| `brand_name` | `WCHS_BRAND_NAME` constant OR `get_bloginfo('name')` | string | Site-wide brand name emitted to SPA. |

SMTP credentials: prefer the `WCHS_SMTP_*` constants in `wp-config.php` over admin UI — they win over stored values.

---

## Field interactions

- `logo_dark_id` (set) **wins over** `logo_invert_on_dark` (even if checked). Dark-mode image replaces the inverted one.
- `mobile_hamburger_side = 'off'` disables all `*_mobile_pin` fields (everything renders inline).
- `reg_require_email_verify = true` + `access_mode < 3` **together** block unverified users from Store API entirely until they verify.
- `brand_position = 'center'` (desktop) forces the header flex layout to `justify-content: flex-end` so nav stays right-pinned. Does not change mobile.
- `header_borderless = true` + `header_inverted = true` = fully background-blended header (advanced; test both themes).
- `gate_modal.version` bump invalidates previously-stored consent cookies site-wide. Use when terms change.

---

## REST emission (SPA contract)

`/wp-json/wchs/v1/config` emits most of these as top-level fields. Some renaming:

| Option key | REST field |
|---|---|
| `gate_modal.*` | `gate_modal` (full nested object) |
| `footer.columns` | `footer.columns` |
| `footer.tagline` | `footer.tagline` |
| `social_links` | `social_links` |
| `logo_dark_id` | `logo_dark_url` (resolved via `wp_get_attachment_image_url($id, 'medium')`) |
| `accent_color` | `accent_color` + derived `accent_fg` for contrast |

Full shape in `spa/src/lib/config.svelte.ts` `type ConfigData`.

---

## Adding a new setting (agentic workflow)

1. `wp/mu-plugins/wchs-admin/admin-page.php` — add the key to `$defaults` in `get_site_settings()`.
2. Same file — handle it in the relevant `save_*_settings()` method (sanitize + store).
3. Same file — add the admin UI field in the relevant `render_*_tab()` method.
4. `wp/mu-plugins/headless-rest-endpoints.php` — emit it in the `/wchs/v1/config` response (around line 531).
5. `spa/src/lib/config.svelte.ts` — add to `type ConfigData` + `defaultConfig` constant.
6. Consume `config.data.<your_key>` in the SPA component that needs it.

Deploy to Alyve by pushing deployable code to `main`, or from a generated site
folder with `./scripts/purge-and-rebuild.sh`. The new option's default seeds
automatically via `wp_parse_args` - no migration needed.
