# Starter Checklist — fresh SiteGround clone

Single-page, ordered. Work top-down. If a step fails, fix it before moving
on — the next step will not work.

> **Time budget**: 25–40 minutes from blank SiteGround GoGeek to working
> homepage. If you cross 60 min, something is off — revisit the most
> recent step.

---

## 0. Pre-flight (5 min, in SiteGround Site Tools)

- [ ] Domain provisioned + DNS pointed at SG (A-record).
- [ ] SSL: Site Tools → Security → SSL Manager → "Let's Encrypt" → install.
- [ ] SSH key: Site Tools → Devs → SSH Keys → upload your `~/.ssh/id_*.pub`.
- [ ] MySQL DB: Site Tools → Site → MySQL → create DB + user + assign.
  Capture the **DB name, user, password, host (always `localhost`), and table prefix** that SG actually uses (it's not always `wp_`).
- [ ] WP installed via Site Tools → WordPress → Install. Use a **strong admin password**; you'll override it later via `wp user update`.
- [ ] Add SSH alias to `~/.ssh/config` on your dev box:

  ```
  Host <site>-sg-host
      HostName <ssh-host-from-site-tools>
      Port 18765
      User <ssh-user-from-site-tools>
      IdentityFile ~/.ssh/id_ed25519
  ```

- [ ] Test: `ssh <site>-sg-host 'wp core version'` returns a number.

---

## 1. DELETE these SiteGround default plugins immediately

SG installs four plugins on every fresh install that **will** break the headless setup:

```bash
ssh <site>-sg-host 'cd /home/customer/www/<domain>/public_html && \
  wp plugin deactivate sg-cachepress sg-security sg-ai-studio wordpress-starter --quiet && \
  wp plugin delete sg-cachepress sg-security sg-ai-studio wordpress-starter --quiet'
```

Why each must go:
- **sg-cachepress** — NGINX Dynamic Cache layer that ignores our `Cache-Control: no-store` header on `index.html`. After every SPA deploy the new bundle hash isn't picked up for 10 minutes. **Counter-intuitively**: re-activate it briefly only when you need to manually purge the cache, then deactivate again.
- **sg-security** — blocks several `admin-ajax.php` POSTs that the WCHS admin panel uses (module reordering, image picker). Silently breaks our admin UI.
- **sg-ai-studio** — onboarding wizard. Pure noise.
- **wordpress-starter** — bloat with no useful function in a headless setup.

---

## 2. The big architectural rule: NO Node.js on SiteGround

SiteGround GoGeek does **not** support running a Node.js process. There's no PM2, no systemd unit, no process supervisor available to you. **adapter-static is the only viable SvelteKit adapter for SG.** Don't try `adapter-node` or `adapter-vercel-edge` — they won't work.

The SPA build is **pure static files** (`index.html` + `_app/immutable/*.js,css,woff2`). Apache serves them. Routing is handled by `.htaccess` (next step).

---

## 3. The `.htaccess` is the entire routing layer

This is the file that decides whether a URL goes to the SPA, to WP REST, to wp-admin, or to WC ajax. Get it wrong and you get silent 404s, MIME mismatches, or REST endpoints returning SPA HTML.

Drop this verbatim into `public_html/.htaccess`. Source: `bin/templates/htaccess.template` in the starter folder.

```apache
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteBase /

# 1. Force HTTPS
RewriteCond %{HTTPS} !=on
RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# 2. Bare /wp-admin or /wp-admin/ → /wp-admin/index.php
RewriteRule ^wp-admin/?$ /wp-admin/index.php [L]

# 3. Real files on disk pass through
RewriteCond %{REQUEST_FILENAME} -f
RewriteRule ^ - [L]

# 4. WP-owned paths
RewriteRule ^wp-admin(/.*)?$       - [L]
RewriteRule ^wp-login\.php$        - [L]
RewriteRule ^wp-includes(/.*)?$    - [L]
RewriteRule ^wp-content(/.*)?$     - [L]
RewriteRule ^xmlrpc\.php$          - [L]

# 5. Cart/checkout/my-account → WP (under headless-shim theme)
RewriteRule ^(cart|checkout|my-account)(/.*)?$ /index.php [L]

# 6. WP REST: /wp-json/* → WP
RewriteRule ^wp-json(/.*)?$ /index.php [L]

# 7. WC AJAX (?wc-ajax=...) and ?rest_route= → WP
RewriteCond %{QUERY_STRING} (^|&)(wc-ajax|rest_route)=
RewriteRule ^$ /index.php [L]

# 8. Everything else → SPA fallback
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /index.html [L]
</IfModule>

DirectoryIndex index.html index.php
Options -Indexes

<IfModule mod_headers.c>
  <FilesMatch "\.html$">
    Header set Cache-Control "no-store, no-cache, must-revalidate, max-age=0"
    Header set Pragma "no-cache"
    Header set Expires "0"
  </FilesMatch>
  <FilesMatch "\.(js|css|woff2?|svg|png|jpg|webp|avif|ico)$">
    Header set Cache-Control "public, max-age=31536000, immutable"
  </FilesMatch>
</IfModule>
```

---

## 4. Mu-plugins must deploy BEFORE any DB import

The mu-plugins register classes (`WCHS\Admin\AdminPage`) that are referenced from autoloaded WP options. If WP boots without them present, you get **fatal errors on every request** — even wp-cli won't run.

Order:
1. `rsync wp/mu-plugins/ → public_html/wp-content/mu-plugins/` (FIRST)
2. `rsync wp/themes/headless-shim/ → public_html/wp-content/themes/headless-shim/`
3. THEN import any DB dump

The `wchs-admin/admin-page.php` file lives **inside** the `wchs-admin/` subdirectory, NOT at the mu-plugin root. WP's mu-plugin autoloader only auto-loads root-level `.php` files; the subdir file is included by `wchs-admin.php` itself. Don't move it.

---

## 5. Required plugin activation

```bash
wp plugin install woocommerce --activate
```

WooCommerce is the only universal plugin dependency. Payment gateways,
shipping label tools, email marketing, and analytics plugins are
per-site choices; install only what the store actually uses.

Optional imported pricing plugins should stay deactivated unless there is
a specific reason to keep them. We use the native
`headless-tier-pricing.php` mu-plugin for tier data.

---

## 6. Theme: headless-shim must be active

```bash
wp theme activate headless-shim
```

Without it, `/cart`, `/checkout`, `/my-account` render with the parent theme's CSS — wrong fonts, conflicting styles, broken layout. The shim dequeues parent CSS/JS on those routes and injects the SPA's design system instead.

---

## 7. wp-config.php constants — append these

Append (don't replace) to `wp-config.php`. The deploy script does this idempotently. Manual fallback:

```php
define( 'WCHS_BRAND_NAME',       '<Your Brand>' );

// Optional only for local dev or an intentional split-origin deployment.
// Same-origin production sites should let WCHS derive these from home/siteurl.
// define( 'WCHS_SPA_URL',          'https://<your-domain>' );
// define( 'WCHS_ALLOWED_ORIGINS',  'https://<your-domain>' );
// define( 'WCHS_RETURN_ORIGINS',   'https://<your-domain>' );
```

`WCHS_BRAND_NAME` is read by the config endpoint. The optional `WCHS_*`
origin overrides are only for local-dev or split-origin sites; normal
production installs should stay same-origin and let the runtime follow
`home/siteurl`.

---

## 8. Table prefix gotcha

SiteGround sometimes provisions a WP install where `$table_prefix` in `wp-config.php`
does NOT match the actual DB tables. Verify:

```bash
ssh <site>-sg-host '
  cd /home/customer/www/<domain>/public_html
  echo "wp-config says: $(grep "^\$table_prefix" wp-config.php | cut -d\"'"'"'\" -f2)"
  wp db tables --format=csv | head -3
'
```

If they mismatch, fix the `$table_prefix` line to match the real table names.

---

## 9. WC sessions — the cart-bridge JWT handoff

The first time anyone adds something to cart, WC creates a row in
`{prefix}_woocommerce_sessions`. The mu-plugin `headless-cart-bridge.php`
reads a JWT cart token from the `?cart=<token>` query param and injects it
into the WC session handler — that's how the SPA cart appears in the
classic checkout.

If `/checkout` shows an empty cart even though the SPA cart has items:
1. Confirm `headless-cart-bridge.php` is in mu-plugins.
2. Confirm the SPA is appending `?cart=<token>` when it links to checkout.
3. Run `wp db query "SELECT COUNT(*) FROM {prefix}_woocommerce_sessions"` — should be > 0 after a cart add.

---

## 10. SiteGround Dynamic Cache — the silent killer

Even with `sg-cachepress` deleted, SG's edge NGINX may cache static responses
for ~10 minutes. After every deploy, hit your domain with curl + a cachebuster
to confirm the new bundle is live:

```bash
curl -sI "https://<your-domain>/?_=$(date +%s)" | grep -i 'cache\|x-cached\|x-proxy'
```

If you see `X-Proxy-Cache: HIT`, wait 10 min OR temporarily reactivate
`sg-cachepress` to issue `wp sg purge`, then deactivate again.

---

## 11. Settings hydration — JSON snapshot import

After everything is wired:

```bash
# On the SG host:
envsubst < config/wchs-settings.baseline.json > /tmp/wchs.json
wp option update wchs_site_settings --format=json < /tmp/wchs.json
# (Repeat for wchs_homepage_config, wchs_pdp_config, wchs_pages_config, wchs_shop_config)
```

The baseline JSON has empty/placeholder values for everything. After import,
log into wp-admin → WCHS Settings to fill in real branding, accent color,
SMTP, pixels, and other integration values. Or pre-edit the JSON and skip
the admin step.

---

## 12. Smoke test — declare victory

In order:

1. `curl -fsS https://<your-domain>/wp-json/wchs/v1/config | jq .brand_name`
   should return your brand string.
2. Open `https://<your-domain>` in a browser — homepage renders without
   JS errors in the console.
3. Run your smoke test against the new domain, or run:
   `DOMAIN=<your-domain> SSH_HOST=<ssh-alias> WP_PATH='<remote-public-html>' ./scripts/verify-site-integrity.sh`
4. Log into wp-admin → WCHS Settings → confirm the 9 visible tabs load.

If all four pass: site is live. Total elapsed time should be 25–40 min on the
second-or-later deploy. The first deploy will run longer because you'll
discover SG-specific quirks in your particular GoGeek environment.

---

## When in doubt

- **REST returns HTML** → `.htaccess` rule order is wrong. Re-check section 3.
- **Fatal "class not found"** → mu-plugins didn't deploy first. See section 4.
- **CORS errors in console** → on a same-origin site, check `home/siteurl` and Cutover alignment first. For split-origin or local-dev, re-check the optional `WCHS_*` overrides in section 7.
- **Cart empty at checkout** → cart-bridge JWT not in the URL. See section 9.
- **Stale bundle after deploy** → SG edge cache. See section 10.
- **"You're locked out of admin" after deploy** → table prefix mismatch. See section 8.
