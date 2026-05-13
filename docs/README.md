# Documentation

This is the map for the repo docs. The short version: use `README.md` to
get a store running, use `CLAUDE.md` when an agent is editing code, and use
the runbooks here when you are deploying or repairing a site.

## Start Here

| Need | Read |
|---|---|
| See what the starter looks like | [`showcase.md`](showcase.md) |
| Run it locally | [`../README.md#local-development`](../README.md#local-development) |
| Deploy a new shared-hosting site | [`starter-checklist.md`](starter-checklist.md) |
| Understand the architecture | [`architecture.md`](architecture.md) |
| Find an admin setting contract | [`admin-settings-reference.md`](admin-settings-reference.md) |
| Find a mu-plugin owner | [`mu-plugins-reference.md`](mu-plugins-reference.md) |

## Agent-Readable Set

Agents should read these before making code changes:

| File | Purpose |
|---|---|
| [`../CLAUDE.md`](../CLAUDE.md) | Repo orientation, file ownership, danger zones, worked examples. |
| [`admin-settings-reference.md`](admin-settings-reference.md) | Admin option keys, REST shape, and setting add workflow. |
| [`mu-plugins-reference.md`](mu-plugins-reference.md) | Root mu-plugin index and ownership notes. |
| [`architecture.md`](architecture.md) | Store API, cart bridge, auth bridge, and same-origin rules. |
| [`security.md`](security.md) | Credentials, rate limits, access modes, Turnstile, and headers. |
| [`integrations.md`](integrations.md) | Third-party plugin compatibility boundaries. |
| [`cart-spec.md`](cart-spec.md) | Slide cart behavior contract. |

## Human Runbooks

| File | Purpose |
|---|---|
| [`starter-checklist.md`](starter-checklist.md) | Fresh SiteGround-style install from zero. |
| [`siteground-deploy.md`](siteground-deploy.md) | Full deployment and troubleshooting runbook. |
| [`cutover-checklist.md`](cutover-checklist.md) | Short domain cutover checklist. |
| [`domain-cutover-guide.md`](domain-cutover-guide.md) | Detailed domain cutover behavior and vendor follow-up. |
| [`per-site-deploy.md`](per-site-deploy.md) | Per-site constants, DB isolation, and reverse proxy contracts. |
| [`stripe-integration.md`](stripe-integration.md) | Stripe notes for native checkout and one-click upsells. |
| [`ports.md`](ports.md) | Local port allocation. |

## Reference And Examples

| Path | Purpose |
|---|---|
| [`showcase.md`](showcase.md) | Northstar Supply seed, generated assets, and screenshots. |
| [`examples/`](examples/) | Optional migration cleanup scripts. Read before running. |

## Alignment Notes

- Upstream public docs should not name real sites using this starter. This
  fork may name Alyve in deployment ownership docs because it is the Alyve
  production deployment fork.
- Runtime credentials belong in `.env`, `wp-config.php`, WordPress admin
  settings, or GitHub Actions secrets, never in committed docs.
- Do not add unrelated client sites to this fork's deploy workflow. Use a
  separate fork or workflow with its own single-site secrets.
- If you add an admin setting, update `admin-settings-reference.md`,
  `CLAUDE.md` if the ownership path changes, and the SPA config type when
  the setting is emitted through `/wp-json/wchs/v1/config`.
- If you add or remove a root mu-plugin, update `mu-plugins-reference.md`.
