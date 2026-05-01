# WCHS Design System (mu-plugin)

Shared design tokens, WooCommerce widget overrides, theme sync, and
mobile responsive styles for the native WordPress pages that the
headless starter keeps on the WP side (/checkout, /my-account, /cart,
wp-login.php).

## Why this lives as a mu-plugin

- Auto-loaded (no activation) - matches the rest of our mu-plugin stack
  (headless-cors, headless-cart-bridge, headless-cart-lock, etc.)
- Dequeues WooCommerce's own stylesheets at priority 999 and wins the
  cascade fight - a child theme couldn't do this cleanly
- Survives theme swaps - if you change the parent theme or ditch the
  shim, the design system stays
- Keeps PHP business logic (behavior hooks) and CSS concerns bundled
  in one place with a clear contract

## Architecture

```
wp/mu-plugins/
├── wchs-design-system.php      ← loader (WP auto-loads this)
└── wchs-design-system/          ← subdirectory (WP ignores, loader requires)
    ├── src/
    │   ├── Assets.php           ← enqueue tokens + overrides, DEQUEUE WC
    │   ├── ThemeSync.php        ← inline no-FOUC head script + footer script
    │   ├── HeaderRenderer.php   ← renders the native WP header shell + in-header toggle
    │   ├── ToggleRenderer.php   ← legacy floating toggle renderer (currently unused)
    │   └── WcOverrides.php      ← PHP-side WC hooks (classic shortcodes, etc.)
    ├── assets/
    │   ├── tokens.css           ← shared tokens (also symlinked into SPA)
    │   ├── wc-overrides.css     ← hand-authored WC widget + mobile styles
    │   └── theme-sync.js        ← toggle wiring + cross-tab storage sync
    └── README.md                ← this file
```

## Conventions

- **Tokens only** - no hardcoded hex values. All colors flow through
  `var(--*)` in `tokens.css`. Adding a new semantic color means adding a
  new token, not inlining.
- **Mobile-first** - base styles target ≤640px phones. Enhance up via
  `@media (min-width: <px>)`. Touch targets ≥44px; input fonts ≥16px
  on mobile (prevents iOS zoom).
- **Never !important** - except the documented Select2 exceptions (WC
  includes Select2 with high-specificity rules we have to force through).
  If you find yourself reaching for !important elsewhere, fix the
  cascade instead.
- **Theme toggle state** lives in `localStorage.wchs_theme`. Values:
  `"light"` | `"dark"`. Read via `window.wchsTheme.get()`, toggled via
  `window.wchsTheme.toggle()`. Cross-tab sync built in.

## Extending

Adding a new WC widget style:
1. Add CSS rules to `assets/wc-overrides.css` using existing tokens.
2. If you need a new token, add it to `assets/tokens.css` with values
   for both light and dark themes.
3. Verify desktop + mobile in both themes via
   `node tests/visual-responsive.js`.

Adding a new PHP-side behavior:
1. Add a method to `src/WcOverrides.php` or create a new class in
   `src/` if it warrants isolation.
2. Require it from the loader (`wchs-design-system.php`).
3. Don't put CSS-reachable concerns in PHP.

## What this does NOT own

- SPA styling - that lives in `spa/src/lib/components/*.svelte` and
  `spa/src/routes/*.svelte`. The SPA imports `tokens.css` via a
  symlink at `spa/src/lib/styles/tokens.css` → this plugin's assets.
- Headless-shim child theme templates - kept as a minimal fallback for
  rendering unexpected non-WC front-end requests. No enqueue logic.
- Cart bridge / login redirect / order redirect - those live in
  sibling mu-plugins with single-file scope.
