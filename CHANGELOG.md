# Changelog

All notable public changes are tracked here.

Format loosely follows [Keep a Changelog](https://keepachangelog.com/).

## [Unreleased]

### Changed

- This fork is documented as the Alyve-only deployment repo. Pushes to `main`
  deploy `alyvepeptides.com` through fork-local `ALYVE_SG_*` secrets.
- Deployment docs now warn against adding unrelated client sites to this fork's
  workflow.

## [1.0.0] - 2026-05-01

### Added

- First public release of the headless WooCommerce starter.
- SvelteKit 5 static SPA for catalog, shop, PDP, cart, homepage, content
  pages, and post-purchase confirmation.
- WordPress/WooCommerce mu-plugin set for same-origin SPA routing, Store API
  cart bridging, SEO shell rendering, admin configuration, access modes,
  address validation, order bumps, upsells, review providers, offline
  gateways, SMTP, pixels, Omnisend compatibility, and domain cutover tooling.
- WCHS wp-admin settings surface for storefront configuration without code
  edits.
- SiteGround-style shared-hosting deployment toolkit with snapshot generation,
  guarded rsync deploys, cutover helpers, and integrity verification scripts.
- GitHub Actions deployment workflow for one or more configured shared-hosting
  sites using repository secrets.
- Documentation for local development, deployment, security, integrations,
  mu-plugin ownership, admin settings, architecture, and cutovers.
- Docs index, showcase guide, generated Northstar Supply demo assets, and
  local showcase seeding script.

### Changed

- Seed data, sample reviews, fallback SEO text, and starter copy are neutral
  and safe for public reuse.
- Public tree excludes site-specific migration notes, remote test artifacts,
  private deployment breadcrumbs, and site-specific helper scripts.

### Security

- No live credentials are committed. Runtime secrets belong in `.env`,
  `wp-config.php`, WordPress admin settings, or GitHub Actions secrets.
- The public release history starts from this sanitized baseline.
