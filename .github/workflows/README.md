# CI/CD — GitHub Actions

## `deploy.yml`

On every push to `main` that changes deployable code (or manual trigger via
the Actions tab), this fork builds the SPA once and rsyncs
`wp/mu-plugins/`, `bin/templates/htaccess.template` to the live
`.htaccess`, `spa/build/`, and `wp/themes/headless-shim/` to Alyve only:
`alyvepeptides.com`.

This workflow intentionally does not deploy AusBio or any other WCHS site.

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

- **Auto:** any push to `main` that touches non-doc, non-workflow files.
  Doc-only/workflow-only pushes skip.
- **Manual:** Actions tab → *Deploy to SiteGround* → *Run workflow*.
  Runs the Alyve deployment.
- **Skip auto-deploy on a specific push:** include `[skip deploy]`
  anywhere in the commit message.

### Secrets (configured via `gh secret set`)

Secrets are repository-local. This fork owns only the Alyve deployment
secrets.

| Secret | Value |
|---|---|
| `ALYVE_SG_SSH_KEY` | Full private key contents for Alyve's SiteGround SSH user |
| `ALYVE_SG_HOST` | Alyve SiteGround SSH hostname |
| `ALYVE_SG_USER` | Alyve SiteGround SSH username |
| `ALYVE_SG_DOMAIN` | `alyvepeptides.com`, matching `~/www/alyvepeptides.com/public_html` |

Rotate any of these via `gh secret set <NAME>` (reads from stdin for
keys, takes `--body '<value>'` for strings).

If Alyve's domain changes, update `ALYVE_SG_DOMAIN` before the next deploy.
The workflow uses it both as the live Host
header for smoke checks and as the remote SiteGround webroot folder
name.

### Moving this fork to a different site

Do not add a second matrix row here. For another client/site, create a separate
fork or workflow with its own single-target secrets.

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
- There is no deploy matrix in this fork. A failed workflow means the Alyve
  deploy did not complete.
- The lint step doesn't fail the deploy on PHP syntax errors (it's
  grep-guarded). If you care, tighten it after first green run.
- Never treat "SPA goes to the webroot" as permission to overwrite the
  whole webroot. Only the workflow's guarded `spa/build/` rsync, or the
  local helper scripts that copy just `_app/` + `index.html`, are
  approved deploy paths for existing sites.
