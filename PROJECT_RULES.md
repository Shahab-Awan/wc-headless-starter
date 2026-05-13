# Project Rules - wc-headless-starter

The hard rules for this project. Everything below is a non-negotiable
default. Violations need an explicit comment explaining why.

Last updated: 2026-04-11

---

## 1. Theming - always light + dark

**Every UI includes both themes.** Light mode and dark mode are equal
citizens. The theme choice is stored in `localStorage.wchs_theme` and
applied as `[data-theme='light' | 'dark']` on `<html>`. A toggle is
reachable from every page, including the native WP pages.

- **Never hardcode hex values in components.** All colors flow through
  `var(--*)` tokens defined in
  `wp/mu-plugins/wchs-design-system/assets/tokens.css`. Adding a new
  semantic color means adding a new token, not inlining.
- The token file is the single source of truth for both the SPA (via
  symlink at `spa/src/lib/styles/tokens.css`) and the native WP pages
  (via `wp_enqueue_style` in the design system mu-plugin).
- Components that need a new color should add a new semantic token with
  values for BOTH themes in the same commit.
- `prefers-color-scheme` is the fallback when no explicit choice exists.
  Never treat it as the only signal - always support explicit toggle.
- Cross-tab sync: storage event listener keeps tabs in sync
  automatically. Don't work around it.

---

## 2. Typography - one typeface, tight, deliberate

**Inter for everything.** No display pairing, no mono pairing except
for `.tabular-nums` on number-aligned contexts (prices, counts, quantity
displays). Variety comes from weight, size, case, and tracking, not
from different families.

### Scale

| Role | Size | Weight | Line height | Letter spacing |
|---|---|---|---|---|
| Display / hero | `clamp(48, 8vw, 104)px` | 500 | 0.96 | -0.035em |
| Section h1 | `clamp(38, 5vw, 56)px` | 500 | 1.0 | -0.025em |
| Section h2 | `clamp(22, 4vw, 28)px` | 500 | 1.1 | -0.02em |
| Product card title | 15px | 500 | 20px | -0.24px |
| Body | 14px | 400 | 1.5 | -0.16px |
| Small / caption | 13px | 400 | 1.4 | -0.16px |
| Micro label (uppercase) | 11-12px | 450 | 1.3 | +0.08 to 0.14em |
| Price / tabular number | 14-16px | 500 | 1 | -0.2px (+ `font-variant-numeric: tabular-nums`) |

### Rules

- **Tight tracking on display**. Negative letter-spacing at 24px+. Body
  text uses -0.16px as the default, not zero.
- **Weight 450 for micro uppercase labels** - Runway's precision move.
  Intermediate between 400 and 500.
- **Uppercase + positive tracking for navigation / labels.** 0.08em to
  0.14em positive spacing. Never tight uppercase.
- **Pretext-measured text never animates its dimensions post-layout.**
  If text width/height matters for layout, it's frozen after first
  measurement. See `PRETEXT_RULE.md`.
- **Font strings in Pretext engine variants must exactly match CSS.**
  If you change a font-family or size in CSS, update
  `spa/src/lib/pretext/engine.ts` in the same commit.

---

## 3. Motion - token-driven, respects user preferences

**Always use motion tokens from `tokens.css`:**

- `--dur-micro: 120ms` - hover, focus, press micro-interactions
- `--dur-fast: 180ms` - color transitions, fades, small scale
- `--dur-med: 280ms` - drawer slide-in for small panels, card lift
- `--dur-slow: 400ms` - cart drawer slide, full-panel entrances
- `--ease: cubic-bezier(0.4, 0, 0.2, 1)` - standard
- `--ease-out: cubic-bezier(0.2, 0.8, 0.2, 1)` - exit / reveal
- `--ease-snap: cubic-bezier(0.5, 0, 0.2, 1)` - snappy, for drawers

### Rules

- **Never hardcode a duration in ms.** Reach for the token.
- **Keep animations ≤400ms** for UI feedback. Longer animations belong
  only to intentional content reveals.
- **Always respect `prefers-reduced-motion: reduce`.** The global rule
  in `tokens.css` zeroes transition durations; for JS-driven animation
  loops, check the media query and render a static state.
- **Prefer `box-shadow` or `transform` over `background`** when
  animating an element that has borders. Background animations clip at
  the border's paint region; inset box-shadow covers it.
- **Flash / bump animations use `color-mix(in oklab, var(--fg) X%, ...)`**
  so they automatically re-color to the current theme without needing
  separate keyframes per theme.

---

## 4. Mobile-first - base is mobile, enhance up

**Mobile is the default.** Base CSS rules target ≤640px phones.
Enhancement happens via `@media (min-width: <px>)`. Never write
`max-width` media queries for layout - that's desktop-first and it
loses.

### Standard breakpoints

- **Base (0-639px)** - phones
- **`min-width: 640px`** - large phones, small tablets in portrait
- **`min-width: 768px`** - tablets
- **`min-width: 900px`** - larger tablets, small laptops
- **`min-width: 1024px`** - desktops
- **`min-width: 1280px`** - wide desktops

### Hard mobile rules

- **Touch targets ≥ 44×44px** on any interactive element. This is the
  iOS Human Interface Guideline minimum and the accessibility baseline.
- **Form input font-size ≥ 16px on mobile.** Below 16px, iOS Safari
  auto-zooms on focus. Break this rule and every user will curse you.
- **Buttons full-width at base**, `width: auto` at desktop via
  `min-width` enhancement.
- **Stack forms vertically at base.** Two-column at `min-width: 768px`
  or wider.
- **Disable hover-only interactions.** Hover doesn't exist on touch;
  ensure `:active` and `:focus` states carry any visual information the
  hover state does.

---

## 5. WooCommerce override strategy - mu-plugin only

**Native WP/WC page styling lives in `wp/mu-plugins/wchs-design-system/`.**
Not a theme, not a plugin you have to activate, not inline CSS in
functions.php. The mu-plugin auto-loads, dequeues WooCommerce's own
stylesheets at priority 999, and re-enqueues ours so the cascade lands
cleanly without needing `!important`.

### Rules

- **Never `!important`** except the documented Select2 exception. WC's
  Select2 integration includes inline styles and high-specificity
  selectors that genuinely require it. Every other rule in
  `wc-overrides.css` should use token-based specificity.
- **One mu-plugin subdirectory, multiple single-concern PHP files.**
  Don't let the loader balloon into a monster file. Each `src/*.php` is
  one class, one responsibility, ≤150 lines.
- **PHP classes are namespaced** under `WCHS\DesignSystem\*`.
- **Dequeue before re-enqueue.** Hook `wp_enqueue_scripts` and
  `login_enqueue_scripts` at priority 999 (after WC at ~10) in
  `Assets::register()`. Always dequeue `woocommerce-general`,
  `woocommerce-layout`, `woocommerce-smallscreen`.
- **Theme toggle JS runs inline in `<head>` for the no-FOUC path** +
  deferred footer script for interactive wiring. Storage key is always
  `wchs_theme` with values `light | dark`.

---

## 6. File placement - know where things live

- **SPA component styles**: scoped `<style>` blocks in each `.svelte`
  file. No global rule sheets except `tokens.css`.
- **SPA global tokens**: `wp/mu-plugins/wchs-design-system/assets/tokens.css`
  (consumed via symlink at `spa/src/lib/styles/tokens.css`).
- **SPA motion + theme + config stores**: `spa/src/lib/*.svelte.ts`
  files. Runes-based state management, one store per concern.
- **WP-side PHP classes**: `wp/mu-plugins/wchs-design-system/src/`, one
  class per file.
- **WP-side CSS + JS assets**: `wp/mu-plugins/wchs-design-system/assets/`
- **WP-side templates / fallback markup**: `wp/themes/headless-shim/`
  - minimal child theme owning only `index.php`, `header.php`,
  `footer.php`, `style.css`. No business logic, no enqueueing.
- **Headless glue (CORS, cart bridge, order redirect, login return,
  REST endpoints, cart lock)**: single-file mu-plugins at the top of
  `wp/mu-plugins/`. Not part of the design system.
- **Tests**: `tests/security/` for adversarial probes, `tests/e2e/` for
  full-journey and responsive visual tests.

---

## 7. Commit discipline

- Every commit should leave the test suite green. No "WIP, tests
  broken" commits on `main`.
- Design changes and behavior changes in separate commits when
  possible - makes review + rollback cleaner.
- Commit messages: short title, body explaining the why + what tests
  were run. Co-authored-by footer.
- Don't commit screenshots from `tests/screenshots/` - regenerable,
  gitignored.
- Don't commit `.env` - only `.env.example`.

---

## 8. What these rules are NOT

- Not permission to over-engineer. If a one-line change fits the rules,
  implement it; don't build a framework.
- Not gospel. If you hit a genuine edge case that needs an exception,
  write a comment explaining why and get on with it.
- Not a substitute for taste. Rules keep consistency; taste decides
  between equally valid alternatives.

---

## References

- `PRETEXT_RULE.md` - the text rendering rule (component-level)
- `docs/architecture.md` - headless WC architecture brief
- `docs/per-site-deploy.md` - Alyve isolation and deploy ownership guide
- `wp/mu-plugins/wchs-design-system/README.md` - design system plugin docs
- [Runway design reference](../dev/skills/reference/awesome-design-md/design-md/runwayml/DESIGN.md) - aesthetic inspiration
