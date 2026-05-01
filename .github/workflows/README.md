# CI/CD — GitHub Actions

## `deploy.yml`

On every push to `main` (or manual trigger via the Actions tab), builds
the SPA once and rsyncs `wp/mu-plugins/`, `bin/templates/htaccess.template`
to the live `.htaccess`, `spa/build/`, and `wp/themes/headless-shim/`
to the configured SiteGround sites in parallel. Also flushes SG Dynamic Cache
and smoke-tests `/wp-json/wchs/v1/config` plus SPA shell routes like
`/account` and `/shop` on each site.

Important: these sites use a hybrid webroot. The SPA shell lives at the
webroot (`index.html`, `_app/`, a few static icons/files), but
WordPress still owns `wp-admin/`, `wp-includes/`, `wp-content/`,
`wp-*.php`, `.htaccess`, `robots.txt`, and any per-site artifacts at the
same `public_html/` root.

SEO-sensitive SPA paths such as `/shop`, `/shop/<category>`,
`/product/<slug>`, `/account`, `/order-received`, and one-level WCHS
content pages route through WordPress first so `headless-seo-shell.php`
can emit route-specific raw SEO tags while serving the same static SPA
body.

The workflow is safe because the SPA rsync step syncs `spa/build/` to
the webroot with an explicit exclude list for every WP-owned path. Do
not manually run a raw `rsync spa/build/ .../public_html/` against a
live site unless you are carrying forward the exact same excludes. That
will flatten the WordPress install.

### Trigger

- **Auto:** any push to `main` that touches non-doc files. Doc-only
  pushes (changes limited to `**.md`, `docs/**`, `.github/**`) skip.
- **Manual:** Actions tab → *Deploy to SiteGround* → *Run workflow*.
  Select `both` / `site1` / `site2`.
- **Skip auto-deploy on a specific push:** include `[skip deploy]`
  anywhere in the commit message.

### Secrets (configured via `gh secret set`)

Secrets are repository-local. Public forks do not receive the upstream
deployment secrets. If you use this repo for a new public project, create
your own secrets or remove the deploy matrix rows until you are ready to
deploy.

| Secret | Value |
|---|---|
| `SG_SSH_KEY_SITE1` | Full private key contents for site 1 |
| `SG_SSH_KEY_SITE2` | Full private key contents for site 2 |
| `SG_HOST_SITE1` | SiteGround SSH hostname, e.g. `<host>.siteground.biz` |
| `SG_HOST_SITE2` | (same shape, site 2's host) |
| `SG_USER_SITE1` | SG-generated username for site 1 |
| `SG_USER_SITE2` | (same shape, site 2) |
| `SG_DOMAIN_SITE1` | Primary public domain for site 1, and the matching `~/www/<domain>/public_html` SiteGround webroot folder |
| `SG_DOMAIN_SITE2` | (same shape, site 2) |

Rotate any of these via `gh secret set <NAME>` (reads from stdin for
keys, takes `--body '<value>'` for strings).

If a site domain changes, update the matching `SG_DOMAIN_SITE*` secret
before the next deploy. The workflow uses it both as the live Host
header for smoke checks and as the remote SiteGround webroot folder
name.

### Swapping in a new site

1. Provision the SG account, add SSH alias locally, confirm
   `ssh <alias>` works.
2. Add four new secrets: `SG_SSH_KEY_SITE3`, `SG_HOST_SITE3`,
   `SG_USER_SITE3`, `SG_DOMAIN_SITE3`.
3. Add a new row to the `matrix.include` list in `deploy.yml` following
   the pattern of the existing rows.
4. If this site needs to be opt-in-only (not deployed on every push),
   wrap the deploy steps in a condition based on a new workflow_dispatch
   input option.

### Rollback

There's no one-click rollback. If a deploy lands broken:

1. `git revert <bad-sha>` locally.
2. `git push` — triggers another deploy with the reverted code.

Or for urgent reversions, deploy directly from local:
check out a known-good SHA, populate `.env`, then run
`./bin/templates/purge-and-rebuild.sh`.

### Known limitations

- SG Dynamic Cache sometimes takes a few seconds to reflect a flush.
  The smoke-test step hits the origin directly via `curl -H Host:` on
  the site's own 127.0.0.1, bypassing the edge cache. If the smoke
  passes but the public site shows stale content, wait ~30s or hit the
  flush API again.
- Fail-fast is off in the matrix — if site 1 fails, site 2 still
  deploys. This is intentional (one broken SSH key shouldn't block the
  other site).
- The lint step doesn't fail the deploy on PHP syntax errors (it's
  grep-guarded). If you care, tighten it after first green run.
- Never treat "SPA goes to the webroot" as permission to overwrite the
  whole webroot. Only the workflow's guarded `spa/build/` rsync, or the
  local helper scripts that copy just `_app/` + `index.html`, are
  approved deploy paths for existing sites.
