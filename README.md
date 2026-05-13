# wc-headless-starter

A headless WooCommerce starter for shared hosting: SvelteKit 5 static SPA
on the frontend, WordPress + WooCommerce on the backend. It is built for
single-origin deployments where Apache serves the SPA and WordPress still
owns checkout, accounts, admin, and REST.

This fork is the Alyve production deployment fork. Pushes to `main` deploy
`alyvepeptides.com` only through the fork-local Alyve SiteGround secrets.
Shared starter changes should still flow from `lyc-aon/wc-headless-starter`
when they are not Alyve-specific.

---

## What you get

- **Static SPA** built with `@sveltejs/adapter-static` — Apache serves it.
  No Node runtime required.
- **Single-origin deploy** — SPA at `/`, WP at `/wp-*` and `/wp-json/*`
  via `.htaccess` passthrough. Cookies + sessions flow naturally.
- **Multi-tab admin panel** (WCHS menu in wp-admin) for all storefront
  configuration — no code edits for color/hero/modules/pricing/access.
- **WCHS mu-plugin set** covering the headless glue: cart bridging, CORS,
  rate limiting, access modes, abandoned cart recovery, order bumps +
  upsells, offline gateways, review abstraction, more.
- **Native WooCommerce checkout** — the backend stays stock WC, so
  per-site gateway plugins can be configured in wp-admin when needed.
  Offline gateways (CashApp, Venmo, Zelle, Bitcoin, custom) are covered
  by WCHS mu-plugins.
- **SEO baseline** — Product + Organization + BreadcrumbList + FAQPage
  JSON-LD, Open Graph, Twitter Cards, configurable robots.txt rules.
- **PWA manifest** generated per-site from brand tokens.
- **Accessibility** — skip-to-content, keyboard-friendly cart + toggles,
  ARIA labels on icon-only UI.

Full feature list + every admin-configurable option:
**[`docs/admin-settings-reference.md`](docs/admin-settings-reference.md)**.

---

## Screenshots

The repo includes a local showcase seed called **Northstar Supply**. It is
generic demo content, not a real store.

<p>
  <img src="docs/assets/showcase/screenshots/storefront-home-desktop.jpg" alt="Northstar Supply homepage with WebGL hero" width="49%">
  <img src="docs/assets/showcase/screenshots/storefront-shop-desktop.jpg" alt="Northstar Supply shop grid" width="49%">
</p>
<p>
  <img src="docs/assets/showcase/screenshots/admin-homepage-studio.jpg" alt="WCHS homepage studio in wp-admin" width="49%">
  <img src="docs/assets/showcase/screenshots/admin-design-tab.jpg" alt="WCHS design tab with product card controls" width="49%">
</p>

More screenshots, generated demo assets, and the exact reseed commands:
**[`docs/showcase.md`](docs/showcase.md)**.

---

## Quickstart

### Deploy to SiteGround-Style Shared Hosting

1. **Provision** a shared-hosting WordPress install + SSH key. Add an SSH alias
   to your `~/.ssh/config`.
2. **Clone** this repo locally:
   ```sh
   git clone https://github.com/Shahab-Awan/wc-headless-starter.git
   cd wc-headless-starter
   ```
3. **Generate a per-site snapshot** of the deploy toolkit:
   ```sh
   bin/snapshot-template.sh ~/dev/sites/<newsite>
   cd ~/dev/sites/<newsite>
   cp .env.example .env
   # Edit .env — fill in site URL, SSH alias, brand, SMTP creds, and
   # any per-site integration keys
   ```
   The emitted `./scripts/*.sh` helpers are the scripts you run for
   first-time deploys, cutovers, and manual live repairs. The
   `bin/templates/*.sh` files in the source repo are their source
   templates.
4. **Deploy from the generated site folder:**
   ```sh
   ./scripts/deploy-siteground.sh
   ```
   This rsyncs the mu-plugins, theme shim, and guarded SPA build; runs
   wp-cli to set options and activate WooCommerce; flushes host cache when
   the host provides a supported cache command.
5. **Site is live.** Admin at `https://<domain>/wp-admin` with the
   WordPress credentials you set during host provisioning.

Detailed walkthrough with troubleshooting:
**[`docs/starter-checklist.md`](docs/starter-checklist.md)** (onboarding)
and **[`docs/siteground-deploy.md`](docs/siteground-deploy.md)** (full
runbook).

### Update a deployed site

Primary path: configure the GitHub Actions secrets documented in
[`.github/workflows/README.md`](.github/workflows/README.md), then push to
`main`. This fork's workflow builds from the repository checkout and deploys
to Alyve only, with guarded rsync excludes. Do not build or sync from the live
webroot.

Manual path from a generated site folder:

```sh
./scripts/purge-and-rebuild.sh
```

Rsyncs current mu-plugins + SPA build, flushes SG cache.

### Cutover to a production domain

From a generated site folder:

```sh
./scripts/cutover-domain.sh <old-domain> <new-domain>
```

Handles DB search-replace, WCHS domain alignment, and email/DNS checks.
See **[`docs/cutover-checklist.md`](docs/cutover-checklist.md)**.

### Audit a live site after restore or repair

From a generated site folder:

```sh
DOMAIN=<domain> SSH_HOST=<ssh-alias> WP_PATH='<remote-public-html>' \
  ./scripts/verify-site-integrity.sh
```

Runs the domain-alignment verifier plus a broader audit of the live DB,
plugin base, Store API, uploads, and sample media URLs.

---

## Local development

```sh
cp .env.example .env
# Edit .env — local dev credentials (MYSQL_*, WP_ADMIN_*, integration keys)
./scripts/up.sh          # docker-compose: WordPress, MySQL, Redis
./scripts/seed.sh        # WP install, plugin activate, product seed
cd spa && npm install && npm run dev
```

Site: `http://localhost:5175` (SPA) + `http://localhost:8099/wp-admin`
(WP). Vite proxies `/wp-*` and `/wp-json/*` to the WP container so the
SPA is same-origin.

Optional polished demo store:

```sh
./scripts/seed-showcase.sh
```

This replaces the local product catalog with the Northstar Supply demo,
loads the generated showcase media, and writes WCHS settings for the admin
studio. It refuses non-local `WP_SITE_URL` values by default and deactivates
active non-WooCommerce plugins for clean screenshots.

Ports used: **[`docs/ports.md`](docs/ports.md)**.

---

## What you need separately

This starter doesn't include:

- Backups (UpdraftPlus, BackWPup, SG snapshots)
- Full-page caching / CDN rules (WP Rocket, Cloudflare, SG Optimizer)
- Cookie consent UI for EU traffic (Complianz, CookieYes, Cookiebot)
- SEO plugin output on SPA routes (we handle SPA SEO ourselves; use
  Yoast / Rank Math on native WP pages if you want)
- Wishlist, loyalty/rewards, product bundles, faceted filters, multi-
  language routing

Full list + recommended companion plugins:
**[`docs/integrations.md`](docs/integrations.md)**.

---

## Modifying the codebase

- **Docs map:** start at **[`docs/README.md`](docs/README.md)**.
- **Agentic tools (Claude Code, Cursor, Copilot):** read
  **[`CLAUDE.md`](CLAUDE.md)** first. Covers data flow, file ownership,
  extension points, danger zones, worked examples for the most common
  modifications.
- **Humans:** read **[`PROJECT_RULES.md`](PROJECT_RULES.md)** (design
  system + code standards) and
  **[`PRETEXT_RULE.md`](PRETEXT_RULE.md)** (typography rule).
- **Adding a setting / REST endpoint / module:** walkthroughs in
  `CLAUDE.md` § "Common modifications".
- **Documenting a new mu-plugin:** append to
  **[`docs/mu-plugins-reference.md`](docs/mu-plugins-reference.md)**.

---

## Structure

```
wc-headless-starter/
├── README.md                 this file
├── CLAUDE.md                 agentic tool orientation
├── CHANGELOG.md              forward-only release notes
├── PROJECT_RULES.md          code + design rules
├── PRETEXT_RULE.md           typography rule
├── bin/
│   ├── snapshot-template.sh  freeze starter into a clean template bundle
│   └── templates/            per-site deploy toolkit
├── config/                   starter config snapshots (icons, baseline settings)
├── docker-compose.yml        local dev (WP + MySQL + Redis)
├── docs/                     runbooks, references, specs
│   ├── siteground-deploy.md       canonical deploy runbook
│   ├── README.md                  docs map for humans and agents
│   ├── showcase.md                demo seed, assets, screenshots
│   ├── starter-checklist.md       per-site onboarding
│   ├── cutover-checklist.md       domain-swap runbook
│   ├── stripe-integration.md      payment gateway notes
│   ├── admin-settings-reference.md
│   ├── mu-plugins-reference.md
│   ├── integrations.md            third-party plugin compat + what's not included
│   ├── architecture.md            headless WC architectural notes
│   ├── security.md                rate limits, CORS, credentials
│   ├── ports.md                   port allocation for local dev
│   ├── per-site-deploy.md         wp-config constants contract
│   ├── cart-spec.md               SlideCart component behavior contract
│   └── examples/                  optional migration helper scripts
├── scripts/                  dev tooling (up, seed, reset)
├── spa/                      SvelteKit 5 static SPA
│   ├── src/
│   │   ├── app.html
│   │   ├── routes/                SPA pages
│   │   └── lib/
│   │       ├── components/
│   │       ├── config.svelte.ts   config type + defaults (mirrors REST)
│   │       ├── theme.svelte.ts    theme engine
│   │       ├── wc/                cart, auth, Store API client
│   │       └── styles/
│   ├── package.json
│   ├── svelte.config.js
│   └── vite.config.ts
├── tests/                    local Playwright suites
└── wp/
    ├── mu-plugins/           WCHS mu-plugin set — the headless glue
    │   ├── headless-*.php    (28 standalone plugins)
    │   ├── wchs-admin/       admin panel
    │   └── wchs-design-system/  shared tokens + WC overrides
    ├── themes/
    │   └── headless-shim/    minimal WP theme (hooks only, no UI)
    └── php-overrides.ini     1GB memory limit for heavy admin ops
```

---

## Version + release

Tags follow [semver](https://semver.org/). Changes are tracked in
**[`CHANGELOG.md`](CHANGELOG.md)**.

---

## License

MIT. See [`LICENSE`](LICENSE).
