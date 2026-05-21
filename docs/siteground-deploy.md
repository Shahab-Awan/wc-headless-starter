# Siteground deploy — complete runbook

Target topology: **WordPress + static SvelteKit SPA on the same Siteground
GoGeek host, same domain**, no Cloudflare, no Node process. One Apache
virtual host serves both: `/` and SPA paths return `build/index.html`,
`/wp-admin`, `/wp-login.php`, `/wp-json`, `/wp-content` pass through to WP.

Important safety rule: this is a hybrid webroot. The SPA shell lives in
`public_html/`, but WordPress still owns large parts of that same
webroot. For existing sites, the safe local deploy path is `_app/` +
`index.html` only. A full `spa/build/` sync into `public_html/` is only
safe when you preserve the workflow's explicit excludes for every
WP-owned path. A raw `rsync spa/build/ .../public_html/` can flatten the
site.

CI/CD ownership: this fork's GitHub Actions workflow deploys Alyve only.
Pushes to `main` target `alyvepeptides.com` through repo-local `ALYVE_SG_*`
secrets.

After any restore, emergency repair, or suspicious live change, run the
repo's broader integrity audit:

```bash
DOMAIN=<live-domain> SSH_HOST=<ssh-alias> WP_PATH='<remote-public-html>' \
  ./bin/templates/verify-site-integrity.sh
```

That script composes the domain-alignment check with a wider live audit:

- `wp db check`
- active table prefix and discovered table families
- `siteurl`, `home`, `blogname`
- WooCommerce coming-soon / store-pages-only launch state
- minimum active plugin base
- required WCHS options
- Store API products/categories
- uploads presence
- sample config/product media URLs

The order matters. Doing these steps out of sequence is how you produce
the disasters in the "What will break you" section below.

---

## 0. Pre-flight — what Siteground gives you by default

A fresh Siteground GoGeek install lands with:

- Apache in front of NGINX reverse proxy (the infamous **Dynamic Cache**).
- **Random `wp_` table prefix** (e.g. `pzt_`). Every new install gets a
  different prefix. This is the #1 source of pain on Siteground migrations.
- Four Siteground plugins auto-activated:
  `sg-cachepress`, `sg-security`, `sg-ai-studio`, `wordpress-starter`.
  All of them interfere with headless REST behavior.
- The `twentytwentyfive` theme active.
- A host-generated admin user (usually id 1).
- `wp-cli` available on SSH (path: `~/bin/wp` or on `$PATH`).
- Node.js available (`node`, `npm`) but **no long-running process
  support** — no way to keep a SvelteKit node server running. This is
  why we use `adapter-static`.
- An Apache-level `.htaccess` is NOT created automatically until WP
  writes one. If WP hasn't written yet, there is no .htaccess at all.
- **Bare directory requests return 403 at the NGINX edge**, before Apache
  sees them. This bites `/wp-admin/` in particular (DirectoryIndex
  resolving `index.php` never fires), but it applies to every path that
  lacks a trailing file component. The dashboard, the admin bar
  "Dashboard" link, and WP's default post-login redirect target all hit
  this. See section 10.11 for the full fix (theme filter + SPA link
  change).
- Dynamic Cache is ON by default. Its TTL is ~10 minutes for dynamic
  responses. It keys on full URL including query string. Adding `?t=123`
  bypasses it temporarily, which is invaluable for debugging.

You do NOT get:

- Nginx config access
- System-level cron past basic cron jobs
- Docker
- Persistent Node processes
- Ability to run services on non-standard ports
- Direct MySQL access from outside the host (localhost only)

**Siteground provides**: SSH (custom port, usually 18765 or similar),
SFTP, phpMyAdmin (via Site Tools), Let's Encrypt auto-renew, daily
backups for 30 days, `wp-cli`, a staging clone feature we never used.

---

## 1. SSH key + host registration

Siteground uses a non-22 SSH port. Generate a dedicated key for the deploy
host (do not reuse your personal key):

```bash
ssh-keygen -t ed25519 -f ~/.ssh/<site>-sg-host -N '' \
  -C "wchs-deploy <site>.sg-host.com $(date +%Y-%m-%d)"
```

Paste the `.pub` into Site Tools → Devs → SSH Keys Manager → Import Key.

`~/.ssh/config`:
```
Host <site>-sg-host
    HostName <host>.siteground.biz
    Port 18765
    User u<nnn>-<account>
    IdentityFile ~/.ssh/<site>-sg-host
    IdentitiesOnly yes
    ServerAliveInterval 60
```

Verify: `ssh <site>-sg-host "wp --version && pwd"` should print wp-cli
version and the account home dir. If it asks for a password, the key
wasn't imported cleanly.

**Gotcha**: Siteground's SSH fingerprint changes when they migrate your
account between physical hosts. When it happens, `ssh-keygen -R
<host>.siteground.biz` and re-verify the fingerprint via Site Tools →
Dashboard → SSH Credentials.

---

## 2. Decisions made and why

### Adapter: `adapter-static`, not `adapter-node`

Do not build from the SiteGround webroot. Builds happen in GitHub
Actions or a local/generated deploy folder, then static artifacts are
copied to the host. SiteGround has no supervisor for `npm run start`;
static output (`build/index.html` + `build/_app/...`) is deployed to
Apache. SEO-sensitive SPA routes are routed through WordPress first so
`headless-seo-shell.php` can replace the static fallback head block with
route-specific raw meta before serving the same SPA shell. This gives
products, clean category pages, and WCHS content pages usable raw HTML
metadata without running a Node SSR server.

This is documented in `docs/integrations.md` under "SEO plugins".

Set in `spa/svelte.config.js`:
```js
import adapter from '@sveltejs/adapter-static';
adapter({
    pages: 'build',
    assets: 'build',
    fallback: 'index.html',   // client-side router handles unknown paths
    precompress: false,
    strict: false,             // dynamic routes don't need prerender list
})
```

And `spa/src/routes/+layout.ts` (create if absent):
```ts
export const ssr = false;
export const prerender = false;
```

### Co-hosted topology

WP and SPA on the **same domain** means the SPA can call
`/wp-json/wc/store/v1/*` as a same-origin request. No CORS, no cookie
domain issues. Cart-token cookies flow naturally. Login redirects work.

This also means **one `.htaccess` has to route both**:
- real files on disk → served as-is
- `/wp-admin`, `/wp-login.php`, `/wp-includes`, `/wp-content`, `xmlrpc.php` → WP
- `/wp-json/*` → WP (REST)
- `/cart`, `/checkout`, `/my-account` → WP (WC-owned pages)
- **everything else → SPA `index.html`** (client router takes over)

### Siteground plugins OUT

`sg-cachepress`, `sg-security`, `sg-ai-studio`, `wordpress-starter` must
be deactivated and deleted before import:

- **sg-cachepress**: Dynamic Cache ignores our `/wchs/v1/config` freshness
  signals; when access_mode changes in DB the SPA keeps seeing the old
  mode for 10+ minutes. We need its purge tool only — deactivate during
  normal ops, activate briefly for manual purges.
- **sg-security**: blocks some `admin-ajax.php` POSTs our admin panel
  uses for module reordering. Its XML-RPC blocking is fine but we already
  don't use XML-RPC.
- **sg-ai-studio**: irrelevant to headless.
- **wordpress-starter**: onboarding wizard — bloat.

```
wp plugin deactivate sg-cachepress sg-security sg-ai-studio wordpress-starter
wp plugin delete     sg-cachepress sg-security sg-ai-studio wordpress-starter
```

Re-install sg-cachepress later ONLY if you need manual cache purges
(`wp sg purge`). Deactivate immediately after. Do not leave it running.

---

## 3. DB migration sequence (the careful bit)

### 3.1 Backup the fresh destination first
```bash
ssh dest "cd public_html && wp db export /tmp/dest-pre-$(date +%Y%m%d-%H%M%S).sql"
ssh dest "cp public_html/wp-config.php /tmp/wp-config.php.pre-deploy-$(date +%Y%m%d-%H%M%S)"
```
Keep that `wp-config.php` backup copy somewhere you won't nuke. You will
need it — see "What will break you / catastrophic rsync".

### 3.2 Match the table prefix

**If the source uses `wp_` prefix** (most do) and the destination uses
`pzt_` (or whatever random prefix Siteground gave), **change the
destination's prefix before importing**. Two options:

1. **Edit `$table_prefix` in `wp-config.php`** (recommended). Change it
   to match the source. Then drop the existing `pzt_*` tables so the
   import doesn't collide. This is the path we took — faster and
   reversible.
   ```bash
   ssh dest "cd public_html && wp db query \"SHOW TABLES\" --skip-column-names | grep '^pzt_' | \
       xargs -I{} wp db query \"DROP TABLE IF EXISTS {}\""
   # then edit wp-config.php: $table_prefix = 'wp_';
   ```
2. **Rewrite the prefix inside the dump before import** (slow for >100MB
   dumps, error-prone with serialized data).

**Never import a dump whose prefix doesn't match `$table_prefix`** — it
creates `wp_*` alongside `pzt_*` and WP will silently read the original
empty Siteground tables. Nothing visible breaks; everything invisible
breaks.

### 3.3 Dump source, pull uploads, push to destination
```bash
ssh source "cd public_html && wp db export /tmp/source-$(date +%Y%m%d).sql"
scp source:/tmp/source-*.sql tmp/

# uploads (579MB+ on a real store)
rsync -avz --partial --progress source:public_html/wp-content/uploads/ tmp/uploads/
rsync -avz --partial --progress tmp/uploads/ dest:public_html/wp-content/uploads/

# push the dump
scp tmp/source-*.sql dest:~/tmp/
ssh dest "cd public_html && wp db import ~/tmp/source-*.sql"
```

### 3.4 URL rewrite (wp-cli handles serialized data; sed corrupts it)
```bash
ssh dest "cd public_html && \
  wp search-replace 'https://source.com' 'https://dest.com' --all-tables --precise && \
  wp search-replace 'http://source.com'  'https://dest.com' --all-tables --precise && \
  wp search-replace 'source.com' 'dest.com' --all-tables --precise --skip-columns=guid"
```

**Never** run `sed -i 's/source.com/dest.com/g' dump.sql`. PHP
serialized strings are length-prefixed; changing the string length
without updating the length byte destroys every serialized option in
the DB. wp-cli `search-replace` walks the serialization and rewrites
lengths.

### 3.5 Post-import cleanup

Source DB brings all its baggage: active plugins the destination
doesn't have, a different theme, stale transients, cached menu IDs.
Clear it:

```bash
ssh dest "cd public_html && \
  wp option update active_plugins '[\"woocommerce/woocommerce.php\"]' --format=json && \
  wp option update template   'headless-shim' && \
  wp option update stylesheet 'headless-shim' && \
  wp option update current_theme 'Headless Shim' && \
  wp transient delete --all && \
  wp rewrite flush --hard"
```

Then re-activate the universal runtime plugin and only the per-site
integrations the store actually uses:
```bash
wp plugin install woocommerce --version=10.6.2 --activate
wp theme activate headless-shim
```

**Gotcha — imported pricing plugins.** If a source DB had tier-pricing
plugins active, leave them inactive unless the site explicitly depends
on them. WCHS reads its native tier data through the Store API extension.
The safe order: **install + activate WC first**, verify WC loads
(`wp plugin list | grep woocommerce` shows "active"), THEN activate
tier-pricing plugins. If you hit the fatal, recover with direct SQL:
```bash
wp db query "UPDATE wp_options SET option_value='a:0:{}' WHERE option_name='active_plugins'"
```
then redo activation in the right order.

### 3.6 Sanitize users
```bash
# delete shop manager first, reassigning any owned orders to the admin we're keeping
wp user delete 1727 --reassign=1726 --yes
# delete demo admin (id 1)
wp user delete 1 --reassign=1726 --yes
# new admin password
NEW_PASS=$(openssl rand -base64 16 | tr -dc 'A-Za-z0-9' | head -c 12)
wp user update 1726 --user_pass="$NEW_PASS"
echo "ADMIN PASSWORD: $NEW_PASS"  # save this NOW, it is not stored anywhere else
```

### 3.7 API keys + site settings

The settings split across **5 options** — learn this or burn time
debugging why your patch didn't take:

| Option | Contains |
|---|---|
| `wchs_site_settings` | brand_name, accent_color, header_links, footer, API keys (turnstile, Google Maps, EasyPost, GTM), SMTP from, access_mode |
| `wchs_homepage_config` | `{hero, modules}` |
| `wchs_pdp_config` | `{show_reviews, cross_sell_mode, modules}` |
| `wchs_shop_config` | `{modules, cols_min, cols_max, edge_to_edge}` |
| `wchs_pages_config` | `{pages: [{slug, title, modules}]}` |

`logo_url` is returned by the REST config endpoint but is NOT stored
as a settings field — it's derived from the core `custom_logo` theme
mod (attachment ID). To set the logo, either upload via
`wp media import` + `wp theme mod set custom_logo <id>`, or if the file
is already in `wp-content/uploads/`, create an attachment for it:

```bash
wp eval '
  require_once ABSPATH . "wp-admin/includes/image.php";
  $path = ABSPATH . "wp-content/uploads/logo-source.svg";
  $att = [
    "guid"           => "/wp-content/uploads/logo-source.svg",
    "post_mime_type" => wp_check_filetype(basename($path))["type"] ?: "image/svg+xml",
    "post_title"     => "Site logo",
    "post_content"   => "",
    "post_status"    => "inherit",
  ];
  $id = wp_insert_attachment($att, $path);
  set_theme_mod("custom_logo", $id);
  echo "logo id: $id\n";
'
```

### 3.8 `wp-config.php` constants

Append these before `/* That's all, stop editing! */`:

```php
define( 'WCHS_BRAND_NAME',       '<Brand>' );

// Optional legacy/custom-origin overrides. Normal production sites should
// stay in same-origin mode and let WCHS follow home/siteurl automatically.
// define( 'WCHS_SPA_URL',          'https://<site>.sg-host.com' );
// define( 'WCHS_ALLOWED_ORIGINS',  'https://<site>.sg-host.com' );
// define( 'WCHS_RETURN_ORIGINS',   'https://<site>.sg-host.com' );
```

Verify `WP_DEBUG` is `false`. Preserve every Siteground-added constant
(DB creds, auth salts, etc.) — they are per-host and the site won't
boot without them.

### 3.9 Deploy code

```bash
rsync -avz wp/mu-plugins/ dest:public_html/wp-content/mu-plugins/
rsync -avz wp/themes/headless-shim/ dest:public_html/wp-content/themes/headless-shim/
```

Do this **before** the DB import so the mu-plugins are present when WP
bootstraps against the imported rows. If a mu-plugin is missing, its
rows in `wp_options` will produce "class not found" fatals.

---

## 4. The `.htaccess` — this is the whole game

Siteground does not write this for you. You write it exactly. The
wrong order of rules produces silent, weird failures (MIME mismatches,
404s from NGINX, REST routes returning SPA HTML).

```apache
# WCHS — WordPress + SvelteKit SPA on same Apache host (Siteground GoGeek)
# Rules evaluated top-down; first [L] wins.

<IfModule mod_rewrite.c>
RewriteEngine On
RewriteBase /

# 1. Force HTTPS
RewriteCond %{HTTPS} !=on
RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# 2. Bare /wp-admin or /wp-admin/ → /wp-admin/index.php so PHP handles it.
#    On Siteground this rewrite does NOT actually fire — NGINX 403s the bare
#    directory at the edge before Apache sees the request — but it's
#    harmless and helpful on non-Siteground Apache hosts. The real fix for
#    Siteground is the admin_url + login_redirect filters in the theme
#    (section 10.8) plus every SPA link pointing at /wp-admin/index.php
#    explicitly (section 12.5).
RewriteRule ^wp-admin/?$ /wp-admin/index.php [L]

# 3. Real files on disk served as-is (CSS/JS/images/fonts, the SPA index.html at root)
RewriteCond %{REQUEST_FILENAME} -f
RewriteRule ^ - [L]

# 4. WP paths pass through (index.php handles them via WP rewrite internals)
RewriteRule ^wp-admin(/.*)?$       - [L]
RewriteRule ^wp-login\.php$         - [L]
RewriteRule ^wp-includes(/.*)?$     - [L]
RewriteRule ^wp-content(/.*)?$      - [L]
RewriteRule ^xmlrpc\.php$           - [L]

# 5. Cart / checkout / my-account are WP-owned pages under our shim theme
RewriteRule ^(cart|checkout|my-account)(/.*)?$ /index.php [L]

# 6. WP REST API: route /wp-json/* to WP index.php.
#    WP parses REQUEST_URI internally for pretty-permalink REST routing; do NOT
#    use `?rest_route=/$1 [QSA]` — it clashes with caller query strings and
#    breaks REST matching when callers append `?t=...` cache busters.
RewriteRule ^wp-json(/.*)?$ /index.php [L]

# 6b. WooCommerce REST Auth (/wc-auth/v1/authorize) — the OAuth handshake
#     that external apps (Omnisend, Klaviyo, Zapier, any WC-keys integration)
#     hit when the user clicks "Authorize". Lives outside /wp-json so needs
#     its own rule; without it the SPA fallback swallows the request and
#     external app installs 404 mid-authorize.
RewriteRule ^wc-auth(/.*)?$ /index.php [L]

# 6c. WooCommerce gateway callbacks (path-form): /wc-api/<endpoint>.
#     Used by some gateways for IPN/webhook delivery.
RewriteRule ^wc-api(/.*)?$ /index.php [L]

# 6d. WP core sitemaps (since WP 5.5): /wp-sitemap.xml + dynamic subpages.
RewriteRule ^wp-sitemap(-[a-z0-9-]+)?\.xml$ /index.php [L]
RewriteRule ^wp-sitemap\.xsl$ /index.php [L]

# 6e. RSS feeds.
RewriteRule ^feed(/.*)?$ /index.php [L]
RewriteRule ^comments/feed(/.*)?$ /index.php [L]

# 6f. WCHS SEO endpoints.
RewriteRule ^sitemap\.xml$ /index.php [L]
RewriteRule ^robots\.txt$  /index.php [L]

# 6g. Old Shopify product URLs. Route through WordPress so
#     headless-legacy-redirects.php can validate real products and apply
#     per-site product alias maps before emitting canonical 301s.
RewriteRule ^products(/.*)?$ /index.php [L]

# 6h. Clean category URLs. Older WCHS builds linked category filters as
#     /shop?cat=<slug> or /shop?category=<slug>; canonical category pages are
#     /shop/<slug> so indexable category landings are not query-param URLs.
RewriteCond %{QUERY_STRING} (^|&)cat=([A-Za-z0-9_-]+)(&|$) [NC]
RewriteRule ^shop/?$ /shop/%2? [R=301,L,NE]
RewriteCond %{QUERY_STRING} (^|&)category=([A-Za-z0-9_-]+)(&|$) [NC]
RewriteRule ^shop/?$ /shop/%2? [R=301,L,NE]

# 6i. Legacy category paths need PHP because Shopify collection handles can
#     map to different Woo category slugs per site. headless-legacy-redirects.php
#     validates targets and emits the 301.
RewriteRule ^collections(/.*)?$ /index.php [L]
RewriteRule ^product-category(/.*)?$ /index.php [L]

# 6j. SEO-sensitive SPA routes go through WordPress first so
#     headless-seo-shell.php can emit route-specific raw SEO tags while
#     still serving the same Svelte SPA shell.
RewriteRule ^shop(/.*)?$ /index.php [L]
RewriteRule ^product(/.*)?$ /index.php [L]
RewriteRule ^account(/.*)?$ /index.php [L]
RewriteRule ^order-received(/.*)?$ /index.php [L]
RewriteRule ^[A-Za-z0-9_-]+/?$ /index.php [L]

# 7. WooCommerce AJAX (?wc-ajax=update_order_review etc.) and the legacy
#    /?rest_route= REST fallback both target the bare / path with a query
#    string. Without this rule, the SPA fallback below catches them and
#    returns index.html — checkout's update_order_review JS expects JSON,
#    fails silently, and the loading overlay never clears. See section 10.4b.
RewriteCond %{QUERY_STRING} (^|&)(wc-ajax|wc-api|rest_route)=
RewriteRule ^$ /index.php [L]

# 8. Everything else with no matching file → SPA fallback
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /index.html [L]
</IfModule>

DirectoryIndex index.html index.php
Options -Indexes

<IfModule mod_headers.c>
  # SPA shell (index.html) must NEVER be cached by Siteground's NGINX Dynamic
  # Cache — it references content-hashed bundles that change every deploy.
  <FilesMatch "\.html$">
    Header set Cache-Control "no-store, no-cache, must-revalidate, max-age=0"
    Header set Pragma "no-cache"
    Header set Expires "0"
  </FilesMatch>

  # Content-hashed SPA bundles are immutable — aggressive caching is correct.
  <FilesMatch "\.(js|css|woff2?|svg|png|jpg|webp|avif|ico)$">
    Header set Cache-Control "public, max-age=31536000, immutable"
  </FilesMatch>
</IfModule>
```

### Why each rule is there

| Rule | Problem it solves |
|---|---|
| HTTPS force | Siteground installs HTTP by default; LE cert adds HTTPS but doesn't redirect. |
| `wp-admin/?$ → wp-login.php` | Siteground NGINX returns 403 on bare directory request. |
| `REQUEST_FILENAME -f` passthrough | Serves `/_app/immutable/entry/start.<hash>.js` directly without falling through to SPA fallback (which would send `text/html`). |
| WP path passthroughs | Without them, `/wp-admin/admin.php` would fall through to SPA fallback. |
| Cart/checkout/my-account | These are real WP pages generated by WC; the SPA deep-links to them. |
| `/wp-json/*` → `index.php` (no QSA rewrite) | We originally used `?rest_route=/$1 [QSA,L]` — it works for simple requests but `/wp-json/wc/store/v1/products?per_page=2&t=<ts>` turns into `?rest_route=/wc/store/v1/products&per_page=2&t=<ts>` which confuses WP's REST matcher. Plain passthrough lets WP parse REQUEST_URI itself. |
| SPA fallback | `fallback: 'index.html'` in adapter-static means this is where the client router picks up. |
| HTML no-cache | **See below — this is the Dynamic Cache gotcha.** |
| Bundle long cache | Hash-based filenames are immutable; cache aggressively. |

---

## 5. Siteground's Dynamic Cache — the single biggest gotcha

### What it does

An NGINX reverse proxy in front of Apache caches dynamic responses.
TTL ~10 min. Cache key is the full URL including query string. Adding
`?t=123` bypasses a cached entry. Presence of the cache is visible in
the response header `x-proxy-cache-info: DT:1` (DT = dynamic).

### Why it bites

- `/wchs/v1/config` is cached for ~10 min. If you change `access_mode`
  via wp-cli, the SPA sees the old value until the cache expires or you
  purge.
- When we first deployed, the SPA bundled references to
  `start.B5iGjVTS.js`. After a rebuild those became `start.CXecAoGI.js`
  on disk but NGINX was still serving the old `index.html` pointing at
  the non-existent hash. Result: every JS bundle request 404s, the SPA
  fallback serves `index.html` (`text/html`), browser refuses to
  execute it as a module, SPA never hydrates. Symptom: white page, no
  errors, just `Failed to load module script: MIME mismatch`.

### The fix (two parts)

1. **Prevent future caching** of HTML via the `mod_headers`
   `no-store, no-cache, must-revalidate` block shown in section 4.
2. **Purge the existing cache**. SiteGround installs `sg-cachepress` with a
   `wp sg purge` command. We deactivate the plugin during normal ops;
   activate it briefly only to purge:
   ```bash
   wp plugin activate sg-cachepress
   wp sg purge
   wp plugin deactivate sg-cachepress
   wp cache flush
   ```

Add this four-liner to any deploy script that modifies HTML or REST
config. Run it after `rsync`, `wp option update`, or anything that
changes what a user-facing URL returns.

### Debugging tip

When REST or a page looks stale, always first check with a cache
buster:
```bash
curl -sI "https://<site>/wp-json/wchs/v1/config?t=$(date +%s%N)"
```
If the busted URL returns fresh data and the un-busted URL doesn't, it
is 100% Dynamic Cache. Purge.

---

## 6. SPA build + deploy

### Build outside the live webroot

```bash
cd spa
rm -rf build
npm run build
```

Produces `build/index.html` + `build/_app/immutable/` with content-hashed
filenames. Hashes change every build. `index.html` references specific
hashes — **`index.html` and `_app/` must stay in sync**.

### Deploy atomically

**Never** run `rsync --delete-excluded` against `public_html/`. Rsync
default excludes combined with `--delete-excluded` will wipe every
"excluded" file, and the default excludes include `wp-*`. This will
**destroy your WordPress install**. Ask me how I know.

Safe pattern: wipe `_app/` (it's immutable and replaceable), then rsync
both `_app/` and `index.html` in one shot:

```bash
# Nuke old bundles (safe — they're all hashed/replaceable)
ssh dest "rm -rf public_html/_app"

# Push fresh bundles
rsync -avz spa/build/_app/     dest:public_html/_app/
rsync -avz spa/build/index.html dest:public_html/index.html

# Purge the Dynamic Cache so NGINX picks up the new index.html
ssh dest "cd public_html && \
  wp plugin activate sg-cachepress && \
  wp sg purge && \
  wp plugin deactivate sg-cachepress && \
  wp cache flush"
```

### Verification

```bash
# 1. Hash in served index.html matches disk
curl -sL https://<site>/ | grep -oE 'start\.[A-Za-z0-9_-]+\.js' | head -1
ssh dest "ls public_html/_app/immutable/entry/"

# 2. Bundle returns JS, not HTML
curl -sI https://<site>/_app/immutable/entry/start.<hash>.js | grep -i content-type
# expected: content-type: application/javascript

# 3. REST is reachable and returns real data (not SPA HTML)
curl -sL "https://<site>/wp-json/wc/store/v1/products?per_page=1" | python3 -c 'import sys,json; d=json.loads(sys.stdin.read()); print(len(d) if isinstance(d,list) else d)'

# 4. SPA deep-link works
curl -s -o /dev/null -w '%{http_code}\n' https://<site>/shop
# expected: 200
```

---

## 7. Access mode + initial lockdown

For initial setup, set `access_mode=0` (maintenance) so only admins
can access the site:

```bash
wp option patch update wchs_site_settings access_mode 0
```

Verify in a logged-out browser — you should see the maintenance screen.
Logged-in admin — you should see the site with a red banner.

When ready for public:
```bash
wp option patch update wchs_site_settings access_mode 3
# purge so the new mode propagates immediately
wp plugin activate sg-cachepress && wp sg purge && wp plugin deactivate sg-cachepress
```

Access mode reference: `0`=maintenance, `1`=locked (members only),
`2`=browse-only (no cart), `3`=open.

---

## 8. SSL / Let's Encrypt

Site Tools → Security → SSL Manager → Let's Encrypt → install for the
root domain. Auto-renews every 60 days. After install, force HTTPS via
the `.htaccess` rule in section 4. **Do not** use Siteground's "Force
HTTPS" toggle in SG Optimizer — we removed sg-cachepress; the toggle
edits `.htaccess` via the plugin, which won't run.

---

## 9. SMTP

Siteground's default PHP `mail()` relay works for low-volume sends.
For the demo path we only need FROM control:

```bash
wp option patch update wchs_site_settings smtp_from_email "team@<brand>.com"
wp option patch update wchs_site_settings smtp_from_name  "<Brand>"
```

(These are read by our `wchs-smtp.php` mu-plugin which sets `Reply-To`
and `From` headers via the `phpmailer_init` filter. Nothing else is
required.)

Emails land in spam without SPF/DKIM records on the FROM domain —
that's the hosting-provider ceiling. For real mail, use a
transactional provider (SendGrid / Postmark / Resend) via the admin
panel's SMTP section when you stand up the production domain.

---

## 10. What will break you

### 10.1 `rsync --delete-excluded` against `public_html/` — CATASTROPHIC

Rsync has a default-exclude list that includes dotfiles and some
patterns. `--delete-excluded` tells rsync to delete anything matching
excludes on the destination. Combined with the wrong source path
(`spa/build/` instead of `spa/build/_app/`), it will delete **every
file that wasn't in build/** — which is every file in your WP install.

**Never use `--delete-excluded` against a path that contains files you
did not generate locally.** Use targeted subdirectory rsyncs
(`spa/build/_app/ → public_html/_app/`) instead.

Recovery path (the one we actually walked):
1. `wget https://wordpress.org/latest.tar.gz` and extract core files.
2. Restore `wp-config.php` from `~/backups/wp-config.php.pre-deploy-*`.
3. Re-set `$table_prefix = 'wp_';` if you changed it.
4. Re-append WCHS constants.
5. `rsync` mu-plugins, theme, each required plugin, uploads.
6. If a plugin has fatal-on-activate hooks (tier-pricing-table), clear
   active_plugins via direct SQL, install WC first, re-activate
   everything in the right order.

**The DB survived**, so users/products/orders are intact. The hours of
pain are restoring the filesystem.

### 10.2 Bundle hash skew after rebuild

Rebuilding the SPA produces new hashes. If you deploy only `index.html`
or only `_app/`, you get a broken state. Always deploy both, from the
same `build/` tree, in the same operation.

### 10.3 Stale Dynamic Cache

See section 5. Rule of thumb: any time you change something the user
sees (content, config, access mode, module list, homepage hero), run
the 4-liner purge. Cheap, always safe.

### 10.4 Table prefix mismatch

Symptom: WP boots, runs, looks empty. Admin user list shows only the
Siteground default user. No products. You think the import failed but
it didn't — WP is reading a different prefix than the one your import
created.

```bash
ssh dest "cd public_html && \
  grep table_prefix wp-config.php && \
  wp db query 'SHOW TABLES' --skip-column-names | awk '{print \$1}' | cut -d_ -f1 | sort -u"
```
If these disagree, fix `wp-config.php`. If there are two prefixes (e.g.
`wp_` and `pzt_`), drop the unused one's tables.

### 10.4b `?wc-ajax=*` swallowed by SPA fallback (checkout hangs)

WooCommerce's checkout JS calls `/?wc-ajax=update_order_review` (and a
few sibling endpoints) over AJAX whenever the user changes country,
postcode, or shipping. The default SPA fallback in `.htaccess` matches
the bare `/` path, sees no file, and serves `index.html` — WC's JS
expects JSON and silently fails, leaving the `<div class="blockUI
blockOverlay">` spinner over the checkout form forever.

Symptom: checkout looks "frozen" — fields are visible but you can't
click anything (overlay intercepts pointer events). Place Order, even
the Turnstile checkbox, all blocked.

Fix in `.htaccess` (already in section 4):

```apache
RewriteCond %{QUERY_STRING} (^|&)(wc-ajax|wc-api|rest_route)=
RewriteRule ^$ /index.php [L]
```

This must come BEFORE the SPA fallback. Same rule covers the legacy
`?rest_route=` REST fallback so older callers don't break either.

### 10.5 `?rest_route=/$1 [QSA]` REST rewrite

Section 4 explained. Don't do it. Use `/wp-json(/.*)?$ → /index.php`
without rewriting query. WP's REST dispatcher reads REQUEST_URI and
finds the route on its own.

### 10.6 tier-pricing-table fatal on activation before WC loads

Section 3.5 explained. Always activate WooCommerce FIRST.

### 10.7 REST 404s for the Store API when you forgot to flush rewrites

After WP import, Store API routes exist in PHP but WP's rewrite cache
doesn't know about the pretty-permalink form. Always run
`wp rewrite flush --hard` post-import.

### 10.8 Siteground NGINX 403 on every bare directory (not just wp-admin)

Siteground's NGINX layer refuses directory listings at the edge, before
Apache or WP's DirectoryIndex ever run. This applies to EVERY directory
path without a trailing file, not just `/wp-admin/`. The practical
ones that bite:

- `/wp-admin/` — the dashboard. Every WP admin link generated via
  `admin_url()` with no arg ends with `/wp-admin/` and 403s.
- Post-login redirects. `wp_safe_redirect( admin_url() )` lands on the
  same 403.
- Any custom header link that points at a bare directory.

**Fix** (applied in this repo — section 12.5 of this doc):

1. Every SPA link to admin uses `/wp-admin/index.php` explicitly — not
   `/wp-admin/`. See `spa/src/lib/components/AdminBar.svelte`.
2. WP's own generated admin links are rewritten by two filters in the
   theme (`wp/themes/headless-shim/functions.php`):
   - `admin_url` filter: `…/wp-admin/` → `…/wp-admin/index.php`
   - `login_redirect` filter: same rewrite on the post-login target

Don't try to fix this at `.htaccess` — the 403 happens in NGINX before
Apache sees the request, so rewrites don't fire. The
`^wp-admin/?$ → /wp-admin/index.php [L]` rule in section 4 is a
**belt-and-braces** for non-Siteground Apache hosts but has no effect
on Siteground.

### 10.8b Plugins we imported but don't need

`tier-pricing-table` + `tier-pricing-table-premium` came across in the
source DB's `active_plugins`. Our mu-plugin at
`wp/mu-plugins/headless-tier-pricing.php` implements the same feature
natively using meta keys `_tiered_price_rules_type`,
`_fixed_price_rules`, and `_percentage_price_rules`. Those are the keys
that may already exist on imported products, not the plugin's `_tpt_*`
keys.

Kill them:
```bash
wp plugin deactivate tier-pricing-table tier-pricing-table-premium
wp plugin delete     tier-pricing-table tier-pricing-table-premium
wp db query "DELETE FROM wp_postmeta WHERE meta_key LIKE '_tpt_%'"
```

Verify after: every PDP still renders "BULK SAVE" badges and the
Volume Savings table. Our mu-plugin emits the tier data via
`extensions.wchs_cro` in the Store API response and the SPA renders it.

Apply this to any future deploy — the canonical plugin set on a clean
site is just `woocommerce` plus the specific payment, shipping, email,
or analytics plugins the store actually uses. Do not copy the source
site's active plugin list blindly.

### 10.9 Logo not showing despite `logo_url` set in settings

`logo_url` in the REST response is **derived from `custom_logo`**
theme mod, not read from settings. Setting `logo_url` in
`wchs_site_settings` has no effect. Either upload the logo and set the
theme mod (section 3.7), or leave it null and let the brand_name text
render.

### 10.10 Config endpoint returns stale access_mode even after DB update

Dynamic Cache. Section 5. Purge.

### 10.11 sgcaptcha fingerprints non-browser HTTP clients (bot gate silently blocks tests)

Siteground's bot detection (`.well-known/sgcaptcha`) doesn't stop at User-Agent. It also fingerprints **TLS ClientHello + header order + header set**. Any request that doesn't look like a real Chromium/Firefox/Safari stack gets a 202 + meta-refresh to `sgcaptcha` — the request never hits PHP. Your tests look like they're firing, the URL returns a status, but `wp eval-file` checks show no state change on the server because the handler literally never ran.

Things that get gated:
- `curl` with any UA (real or custom)
- Playwright's `ctx.request.fetch()` / `APIRequestContext` (separate HTTP client, not the browser engine)
- Node's `http.get()` / `fetch()`
- Go/Python/Ruby HTTP libraries

The ONLY clean bypass is to fire requests from **inside a real browser page** via `page.evaluate(() => fetch(...))`. The page context owns real Chromium TLS + the sgcaptcha cookie + all `Sec-CH-UA` headers. Pattern:

```js
async function getPwCtx() {
	if (_ctx && _primed) return _ctx;
	_ctx = await _browser.newContext();
	const p = await _ctx.newPage();
	await p.goto(WP, { waitUntil: 'domcontentloaded' });
	await p.waitForTimeout(6000);   // let sgcaptcha solve
	await p.close();
	_primed = true;
	return _ctx;
}
async function browserFetch(url, opts = {}) {
	const ctx = await getPwCtx();
	const page = await ctx.newPage();
	try {
		await page.goto(`${WP}/robots.txt`, { waitUntil: 'domcontentloaded' });
		return await page.evaluate(async ({ u, o }) => {
			const r = await fetch(u, { ...o, credentials: 'include', redirect: 'manual' });
			const text = r.type === 'opaqueredirect' ? '' : await r.text();
			return { status: r.status, type: r.type, body: text };
		}, { u: url, o: opts });
	} finally { await page.close(); }
}
```

Secondary gotchas once you're through the gate:
- **Fresh page per call.** Two concurrent `page.evaluate()` on the same page will throw "Execution context was destroyed" mid-flight. For `Promise.all([...])` race tests, spawn a new page per request (same ctx, so cookies are shared).
- **`redirect: 'manual'` in browser fetch** surfaces a 3xx as `{ status: 0, type: 'opaqueredirect' }`, NOT `status: 302`. Assert on `type === 'opaqueredirect'` or follow the redirect.

### 10.12 HPOS + Shopify import leaves orphan line items on recycled order IDs

If the destination DB was seeded from a Shopify export, `wp_woocommerce_order_items` can contain rows for imported orders. When those orders later get deleted, their line items can stay in the table. Later, `wc_create_order()` assigns a NEW order that recycles the old ID → `$order->get_items()` returns both the new item AND the orphan line items from the dead Shopify order. Item counts are wrong.

Tell-tale sign: orphan items have `product_id = 0` (no valid product reference).

Runtime protection now lives in `wp/mu-plugins/headless-checkout-order-sanitizer.php`: on `woocommerce_checkout_order_created`, WCHS compares the saved order line items against the active cart. If they differ, it wipes the saved rows and rebuilds the order from the cart before payment continues. That protects real checkout orders even if the DB still has stale legacy rows waiting on future IDs.

Housekeeping fix for the DB itself: run `docs/examples/cleanup-orphan-order-items.php` once after any bulk migration / order purge. It deletes `woocommerce_order_items` + `woocommerce_order_itemmeta` rows whose `order_id` no longer exists in `wc_orders`, which removes the latent collision before a future checkout ever touches it.

If you are diagnosing a pre-sanitizer environment, the following filter avoids false positives in assertions:

```php
$items = [];
foreach ($order->get_items() as $i) {
	if ((int) $i->get_product_id() > 0) $items[] = $i->get_name();
}
```

Use the same filter in any order-item assertions for pre-sanitizer sites.

### 10.13 mu-plugins directory structure — don't let rsync mis-place admin-page.php

The admin UI plugin is `wp/mu-plugins/wchs-admin/admin-page.php` loaded by the top-level `wchs-admin.php` dispatcher in the mu-plugins root. If a wild rsync lists `admin-page.php` alongside the other mu-plugin files, the WP mu-plugin loader scans mu-plugins root and **auto-loads `admin-page.php` at the root level too**, which calls `\WCHS\Admin\AdminPage::boot()` twice — class-redeclaration fatal → site-wide white screen ("critical error on this website").

Symptom: deploy of mu-plugins + admin-page.php succeeds, `wp sg purge` runs, then every page errors. `/wp-json/wchs/v1/config` returns "critical error". Admin unreachable.

Recovery: `ssh host 'rm -f /path/to/public_html/wp-content/mu-plugins/admin-page.php'` (the stray root copy). Leave the one under `wchs-admin/` alone.

Prevention: always rsync the `wchs-admin/` SUBDIR as a whole, never cherry-pick `admin-page.php` with a flat target:

```bash
# WRONG — deposits admin-page.php at mu-plugins root
rsync admin-page.php host:public_html/wp-content/mu-plugins/

# RIGHT — mirrors the wchs-admin/ subdir structure
rsync -a wp/mu-plugins/wchs-admin/ host:public_html/wp-content/mu-plugins/wchs-admin/
```

### 10.14c Siteground bot-gate escalates under E2E test load

Every fresh Playwright `newContext()` that navigates to the site triggers a sgcaptcha challenge. The first one's cheap (6s solve). But 50+ of them in 20 minutes — which is typical for the access-modes suite that creates a fresh ctx per test — trains Siteground's bot-reputation engine that this IP is suspicious. Subsequent requests from that IP start getting 202-meta-refresh responses even through already-primed contexts, and tests that issue raw `page.evaluate(fetch(...))` calls against auth-sensitive paths get gated.

Symptoms during test runs:
- Suddenly ~4 tests fail with `cart add 403 (status=202)` or similar unexpected status.
- `p.content()` reads include `<meta http-equiv="refresh" ... /.well-known/sgcaptcha/ ...>`.
- `/shop` pages load "0 cards" because the products fetch was sgcaptcha-gated.

Mitigations:
- **Space runs**: wait ~15-20 minutes between full access-modes runs.
- **Batch contexts**: where possible, re-use a single primed ctx across multiple assertions instead of `freshCtx` per test.
- **Whitelist the runner IP** in Siteground Site Tools → Security → Blocked IPs (can add allowlist), if you have admin access.
- **Accept ~1-4 flakes per ~90 assertion run** as the realistic ceiling against a Siteground deploy without whitelisting.

These are test-infra limits, not product bugs. Real user traffic doesn't fingerprint like automated testing.

### 10.14b Turnstile widget wiped by WC's `update_order_review` AJAX

Every checkout AJAX (country change, state change, shipping method, payment
method select) re-renders the order-review fragment that the Turnstile
widget lives inside. WC replaces the DOM but Turnstile's JS never re-runs
`turnstile.render()` on the new widget — so the hidden `cf-turnstile-response`
input never exists at submit time → WC's checkout POST lacks the token →
`wchs_verify_turnstile('')` returns false → `"Bot verification failed. Please try again."`.

This affects EVERY real user whose checkout triggers `update_order_review`
after the first render (pretty much everyone — changing zip, country, or
even typing into state fires it).

Fixed in `wp/mu-plugins/headless-turnstile.php` `wchs_render_turnstile_widget()`:
listen for jQuery's `updated_checkout` event and re-render any `.cf-turnstile`
div that's missing its iframe. Loading Turnstile's api.js once (not on
every widget render — static guard) avoids the "Turnstile already loaded"
warning plus duplicate script tags.

Regression coverage should exercise the full flow: cart seed → checkout →
Turnstile → place order → upsell accept → thank-you with payment
instructions.

### 10.14 SMTP FROM filter didn't fire because of shape mismatch

`headless-smtp.php` used to read `$s['smtp']['from_email']` (nested), but the admin stored `smtp_from_email` flat. Result: `wp_mail_from` filter returned the default `wordpress@<host>` and every transactional email went out with a generic FROM.

Fixed in `wp/mu-plugins/headless-smtp.php` `wchs_smtp_config()`:

```php
$from_email = $smtp['from_email'] ?? ( $s['smtp_from_email'] ?? '' );
$from_name  = $smtp['from_name']  ?? ( $s['smtp_from_name']  ?? '' );
$from_only  = ! empty( $from_email ) || ! empty( $from_name );
// Enable the filter chain even when only FROM override is set (no SMTP auth).
return [ 'enabled' => ! empty( $smtp['enabled'] ) || $from_only, ... ];
```

The `phpmailer_init` hook still guards on `host && username` so FROM-only config doesn't try to redirect mail through phantom SMTP.

Regression coverage should exercise `apply_filters('wp_mail_from', ...)` to
verify the rewrite actually fires.

### 10.15 Review count + average-rating caches are zero after SQL import

A DB-level review import (mysqldump / `wp db import`) bypasses WooCommerce's `wp_insert_comment` / `wp_set_comment_status` hooks. Those hooks are what populate `_wc_review_count`, `_wc_average_rating`, and `_wc_rating_count` postmetas.

If they don't run, every product's `$product->get_review_count()` returns `0` — even though the review rows are sitting in `wp_comments`. Downstream effects:

- PDP star strip says "(0 reviews)" on every product
- WC Store API's `review_count` in `/wp-json/wc/store/v1/products` = 0 everywhere
- Our PDP per-product review detail block (gated on `product.review_count > 0`) never renders
- `wc_get_products( orderby=rating )` is meaningless

**Fix (post-import, one-shot):**

```bash
scp scripts/review-count-rebuild.php $DEST:/tmp/
ssh $DEST "cd public_html && wp eval-file /tmp/review-count-rebuild.php"
# then purge SG cache so Store API picks up fresh values
```

Script recomputes the three postmetas for every product directly from `wp_comments` rows. Idempotent — safe to re-run.

### 10.16 Review photos live under `ivole_review_image2` on CR4W-sourced sites

If the source site ran Customer Reviews for WooCommerce (`customer-reviews-woocommerce`, a.k.a. ivole/cr4w), review photos are stored in `wp_commentmeta` under `ivole_review_image2` — a single attachment ID per review — not our `_wchs_review_images` (array).

Without a fallback reader, every review that had a photo on source appears in our SPA without its photo — the DB has the attachment, we just don't look at it. `headless-review-providers.php` reads both keys; add another fallback entry if you migrate from a different review provider.

## 11. Deploy-from-zero script (condensed)

For future sites, this is the end-to-end sequence you run against a
fresh Siteground GoGeek install:

```bash
# Variables
DEST=<ssh-alias>
DOMAIN=<preview-domain>
BRAND="Example"
ADMIN_USER=admin

# 1. backups
ssh $DEST "cd public_html && \
  wp db export /tmp/dest-pre-$(date +%Y%m%d-%H%M%S).sql && \
  cp wp-config.php /tmp/wp-config.php.backup"

# 2. strip siteground plugins
ssh $DEST "cd public_html && \
  wp plugin deactivate sg-cachepress sg-security sg-ai-studio wordpress-starter || true; \
  wp plugin delete     sg-cachepress sg-security sg-ai-studio wordpress-starter || true"

# 3. deploy code
rsync -avz wp/mu-plugins/ $DEST:public_html/wp-content/mu-plugins/
rsync -avz wp/themes/headless-shim/ $DEST:public_html/wp-content/themes/headless-shim/

# 4. [manual] match table prefix, drop old prefix tables, import source DB, search-replace URLs
#    See sections 3.2 – 3.4

# 5. post-import cleanup
ssh $DEST "cd public_html && \
  wp option update active_plugins '[]' --format=json && \
  wp option update template 'headless-shim' && \
  wp option update stylesheet 'headless-shim' && \
  wp transient delete --all && \
  wp rewrite flush --hard && \
  wp plugin install woocommerce --activate && \
  wp theme activate headless-shim"
# Deliberately do NOT activate tier-pricing-table / tier-pricing-table-premium —
# our mu-plugin headless-tier-pricing.php implements them natively (see 10.8b).

# 6. rotate the intended admin password if desired
ssh $DEST "cd public_html && \
  NEW=\$(openssl rand -base64 16 | tr -dc 'A-Za-z0-9' | head -c 12); \
  wp user update $ADMIN_USER --user_pass=\"\$NEW\"; echo \"ADMIN PW: \$NEW\""

# 7. write .htaccess (see section 4)
scp bin/templates/htaccess.template $DEST:public_html/.htaccess

# 8. append wp-config constants (manual, because preserving Siteground constants)
#    See section 3.8

# 9. push site settings patch (brand, header links, homepage, pages, API keys)
scp your-config-patch.json $DEST:/tmp/
ssh $DEST "cd public_html && wp eval '<multi-option update script>'"   # see section 3.7

# 10. build outside the live webroot + deploy SPA artifacts
cd spa && rm -rf build && npm run build && cd ..
ssh $DEST "rm -rf public_html/_app"
rsync -avz spa/build/_app/     $DEST:public_html/_app/
rsync -avz spa/build/index.html $DEST:public_html/index.html

# 11. purge dynamic cache
ssh $DEST "cd public_html && \
  wp plugin activate sg-cachepress && \
  wp sg purge && \
  wp plugin deactivate sg-cachepress && \
  wp cache flush"

# 12. verify — curls give a quick smoke, then run the integrity verifier
curl -sL https://$DOMAIN/wp-json/wchs/v1/config | python3 -m json.tool | head -20
curl -sL https://$DOMAIN/wp-json/wc/store/v1/products?per_page=1 | head -c 400
curl -s -o /dev/null -w '%{http_code}\n' https://$DOMAIN/
curl -s -o /dev/null -w '%{http_code}\n' https://$DOMAIN/shop
curl -s -o /dev/null -w '%{http_code}\n' https://$DOMAIN/wp-login.php

# 13. domain + integrity validation
DOMAIN=$DOMAIN SSH_HOST=$DEST WP_PATH="/home/customer/www/$DOMAIN/public_html" \
  ./bin/templates/verify-site-integrity.sh
```

---

## 12. Verification gates

Don't proceed past each:

1. **After DB import**: `wp post list --post_type=product --format=count` returns expected count.
2. **After user sanitize**: `wp user list --role=administrator --fields=ID,user_login` shows only intended admin.
3. **After uploads rsync**: random product image URL returns 200.
4. **After .htaccess write**: `/wp-json/wchs/v1/config` returns JSON, `/` returns SPA HTML (with `<div id="svelte">` or similar root).
5. **After SPA deploy**: browser loads the site, homepage renders hero + modules, no console errors, `Failed to load module script` errors are absent.
6. **After access_mode=3**: guest browser sees the full site.
7. **Integrity validation** (final gate — run this every deploy):

   ```bash
   DOMAIN=<domain> SSH_HOST=<ssh-alias> WP_PATH='<remote-public-html>' \
     ./bin/templates/verify-site-integrity.sh
   ```

   Checks domain alignment, core WCHS options, Store API reachability,
   uploads, sample product media, WooCommerce launch state, and the deployed
   SEO shell.

   Catches deploy drift silently — exactly the failure modes we hit on this deploy (SMTP flat-vs-nested shape mismatch 10.14, would have caught 10.10 on any fresh cache).

Any gate failure → stop, diagnose, don't continue. Proceeding past a
failed gate produces symptom stacks that are painful to untangle.

---

## 12.5 Siteground-specific code changes in this repo

These are the permanent edits this repo carries so the headless starter
works on Siteground without surprises. They are also safe on
non-Siteground Apache hosts, so you don't need to branch deploy targets.

### SPA — `spa/src/lib/components/AdminBar.svelte`

- **Dashboard link** points to `/wp-admin/index.php` (not `/wp-admin/`).
  NGINX 403s bare dir requests; adding `index.php` makes the request a
  file request that passes through to PHP.

### WP theme — `wp/themes/headless-shim/functions.php`

Two filters appended after `after_setup_theme`:

- `admin_url` filter: rewrites any `.../wp-admin/` output to
  `.../wp-admin/index.php`. Catches every link WP core or a plugin
  generates via `admin_url()` without an arg. Same reason as above.
- `login_redirect` filter: applies the same rewrite to
  `wp_safe_redirect( admin_url() )` targets so post-login lands on a
  real file.

### `.htaccess` — section 4 of this doc

Three Siteground-shaped rules:

- `RewriteRule ^wp-admin/?$ /wp-admin/index.php [L]` (no-op on
  Siteground but helpful on non-Siteground Apache).
- `RewriteRule ^wp-json(/.*)?$ /index.php [L]` — NOT
  `?rest_route=/$1 [QSA]` which breaks caller query strings.
- `<FilesMatch "\.html$">` with `Cache-Control: no-store, no-cache,
  must-revalidate, max-age=0` on every `.html` response so
  Siteground's Dynamic Cache doesn't hold stale SPA shells.

### Plugin activation set

- NOT activated: `tier-pricing-table`, `tier-pricing-table-premium`
  (see 10.8b — our mu-plugin implements this natively). Do not
  re-activate even if they appear in the source `active_plugins` row;
  our `wp option update active_plugins` in the post-import cleanup
  strips them, and the deploy-from-zero script (section 11) lists
  only the plugins we actually run.

### Deploy scripts

Every deploy that changes HTML or REST config ends with the 4-liner:

```bash
wp plugin activate sg-cachepress
wp sg purge
wp plugin deactivate sg-cachepress
wp cache flush
```

Without this, you will debug a nonexistent bug for 10 minutes before
realizing Dynamic Cache is serving last iteration's response.

---

## 13. Reference — what Siteground hooks into `.htaccess` when you let it

If Siteground's SG Optimizer plugin ever touches `.htaccess` (it does
when you toggle anything in Site Tools → Speed), it writes blocks like:

```apache
# BEGIN Dynamic-Cache-Control
<IfModule mod_headers.c>
  Header unset ETag
</IfModule>
# END Dynamic-Cache-Control
```

These are harmless additions but will appear between your blocks.
Don't fight them — they preserve non-Siteground rules. But if your
`.htaccess` breaks after you change something in Site Tools, the first
thing to check is whether SG Optimizer rewrote a block.
