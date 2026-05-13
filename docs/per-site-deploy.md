# Per-site deployment guide

How to use this scaffold as a template for multiple independent WP +
WooCommerce sites. Target pattern: **one shared codebase, one SPA build,
N isolated WP installs** - each with its own database, its own brand,
its own origin.

This fork uses that isolation model for one production target:
`alyvepeptides.com`. Pushes to `main` deploy Alyve only through the fork-local
`ALYVE_SG_*` secrets. Do not add unrelated client sites to this workflow; use a
separate fork or workflow with its own single-site secrets.

---

## What lives where

| Layer | Per-site or shared? |
|---|---|
| mu-plugins (`wp/mu-plugins/*.php`) | **Shared code.** Deployed identically to every site. |
| WP shim theme (`wp/themes/headless-shim/`) | **Shared code.** |
| SPA bundle (`spa/build/`) | **Shared code.** One build, served to every site. |
| `wp-config.php` constants | **Per-site.** Each install has its own. |
| MySQL database | **Per-site.** Each install has its own DB (or its own DB on a shared server). |
| `wp_woocommerce_sessions` table | **Per-site** (lives in the per-site DB). |
| Cart-Token JWTs | **Per-site** (signed with `@.wp_salt()` which is per-site). |
| MySQL `GET_LOCK` lock names | **Per-site** (prefixed with `DB_NAME` hash). |

Nothing leaks between sites. JWTs from site A won't validate on site B.
Cart locks are scoped per-DB. Runtime config is resolved per site from
that site's own DB options plus any per-site `wp-config.php` secrets or
intentional overrides.

---

## Per-site `wp-config.php` constants

Add these to each site's `wp-config.php`, above the `/* That's all, stop
editing! */` line:

```php
// ---- Headless WC Starter - per-site configuration ----

// Brand name shown in the SPA header. Defaults to the site title if
// omitted.
define( 'WCHS_BRAND_NAME', 'Example Shop' );

// Optional: lock timeout in seconds for concurrent cart writes
// (default 5). Raise if you expect long-running plugins in the
// add-to-cart hook chain.
// define( 'WCHS_CART_LOCK_TIMEOUT', 10 );

// Optional: disable the cart lock if a site's cart hooks are known
// to be safe without serialization (NOT recommended).
// define( 'WCHS_CART_LOCK_ENABLED', false );

// Optional: only for local dev or an intentional split-origin deployment.
// Normal production sites should stay in same-origin mode and let WCHS
// derive these automatically from home_url().
// define( 'WCHS_SPA_URL', 'https://spa.example.com' );
// define( 'WCHS_ALLOWED_ORIGINS', 'https://spa.example.com' );
// define( 'WCHS_RETURN_ORIGINS', 'https://spa.example.com' );
```

For multiple environments (dev/staging/prod) on the same site, gate
with `WP_ENVIRONMENT_TYPE`:

```php
if ( wp_get_environment_type() === 'local' ) {
    define( 'WCHS_SPA_URL',         'http://localhost:5175' );
    define( 'WCHS_ALLOWED_ORIGINS', 'http://localhost:5175,http://127.0.0.1:5175' );
    define( 'WCHS_RETURN_ORIGINS',  'http://localhost:5175,http://127.0.0.1:5175' );
} else {
    define( 'WCHS_BRAND_NAME',      'Example Shop' );
}
```

---

## SPA runtime configuration

The SPA fetches its config from `GET /wp/wp-json/wchs/v1/config` on
mount. The response shape:

```json
{
  "wp_origin": "https://shop.example.com",
  "spa_origin": "https://shop.example.com",
  "brand_name": "Example Shop",
  "currency_code": "USD",
  "currency_symbol": "$",
  "features": {
    "guest_checkout": true,
    "dark_mode": true,
    "pretext": true
  },
  "version": "0.1.0"
}
```

**You don't rebuild the SPA per site.** One build, one bundle, N sites.
The SPA reads `window.location.origin` for its own origin and fetches
everything else from `/wp/wp-json/wchs/v1/config`.

---

## Nginx proxy - the `/wp/*` convention

Both the dev (Vite) and prod (nginx) setups proxy `/wp/*` on the SPA
origin to the WP origin. Same-origin requests from the SPA mean no
CORS dance, cookies flow naturally, and the runtime config URL
(`/wp/wp-json/wchs/v1/config`) is portable across environments.

Example nginx block for `shop.example.com`:

```nginx
server {
    listen 443 ssl http2;
    server_name shop.example.com;

    # Static SPA bundle
    root /var/www/wchs-spa;
    index index.html;

    # SvelteKit Node adapter (if using SSR)
    location / {
        proxy_pass http://127.0.0.1:3000;
        proxy_set_header Host $host;
        proxy_set_header X-Forwarded-For $remote_addr;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    # WP backend at /wp/*
    location /wp/ {
        # Strip /wp prefix before forwarding
        rewrite ^/wp/(.*)$ /$1 break;
        proxy_pass http://wp-backend-shop.example.com;
        proxy_set_header Host shop.example.com;  # keep original host so
                                                  # WP knows its own origin
        proxy_set_header X-Forwarded-For $remote_addr;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

Or if you prefer a subdomain split (`shop.example.com` for SPA,
`wp.shop.example.com` for WP), you still proxy `/wp/*` on the SPA side
to `wp.shop.example.com/*` so the SPA's Store API calls are same-origin.
This also simplifies CORS (none needed in the happy path).

---

## Database isolation

Each site has its own database. The cart-lock plugin includes `DB_NAME`
in the hash of its lock names, so even if two sites share a MySQL
server, their cart locks cannot collide:

```
wchs_cart_<hash(DB_NAME + cart_token)>
```

The `@.wp_salt()` used by WC's Cart-Token JWT is per-site (each site
has its own salts in wp-config), so tokens from site A can't be
replayed on site B.

`wp_woocommerce_sessions` is a per-site table - no cross-contamination.

---

## Deploy checklist per new site

1. Provision a WordPress install pointing at a new/clean database
2. Copy this repo's `wp/mu-plugins/*.php` into `wp-content/mu-plugins/`
3. Copy `wp/themes/headless-shim/` into `wp-content/themes/`
4. Activate: WooCommerce and the `headless-shim` theme. Add payment,
   shipping, analytics, or email plugins only when the site needs them.
5. Add the per-site constants to `wp-config.php` (see above)
6. **Set `WP_DEBUG` to `false`** (enables application-level rate limiting)
7. **Configure real IP forwarding** in nginx (see below)
8. **Add infrastructure-level rate limiting** in nginx or Cloudflare
9. Verify `GET https://shop.example.com/wp-json/wchs/v1/config` returns
   the expected JSON
10. Point the shared SPA bundle at this origin (add nginx vhost)
11. Smoke test: `curl https://shop.example.com/wp-json/wc/store/v1/products`

No per-site code changes. Just config + infra.

---

## Rate limiting & real IP (REQUIRED for production)

The custom `/wchs/v1/` endpoints have application-level rate limiting
(per-IP, transient-backed). **WooCommerce and WordPress native endpoints
have zero rate limiting.** You need infrastructure-level protection.

### Real IP forwarding

Behind any reverse proxy, `$_SERVER['REMOTE_ADDR']` is the proxy's IP, not
the client's. Without real IP forwarding, **all visitors share one rate limit
bucket** and one aggressive client locks out everyone.

If behind Cloudflare, add `real_ip_header CF-Connecting-IP;` plus all CF IP
ranges to your nginx config. If behind a plain nginx proxy, use
`X-Forwarded-For`. See `SECURITY.md` for the full config block.

### Infrastructure rate limits

Add these to your nginx config (or equivalent Cloudflare WAF rules):

```nginx
limit_req_zone $binary_remote_addr zone=wpapi:10m rate=5r/s;
limit_req_zone $binary_remote_addr zone=wccart:10m rate=1r/s;

location /wp-json/ {
    limit_req zone=wpapi burst=20 nodelay;
}

location ~ /wp-json/wc/store/v1/cart/(add-item|update-item|remove-item) {
    limit_req zone=wccart burst=5 nodelay;
}
```

Full details in `SECURITY.md`.

---

## What IS shared across sites (and why that's fine)

- **mu-plugin code** - reads per-site constants; behavior differs per
  site based on config
- **SPA bundle** - reads per-site config from `/wchs/v1/config`; behavior
  differs per site based on response
  has its own funnel/upsell configuration in its own DB
- **WordPress core** - standard

Nothing in the shared layer has per-site knowledge baked in. All
per-site behavior comes from either (a) per-site DB content or (b)
per-site `wp-config.php` constants.

---

## What is NOT shared

- Databases
- Media uploads (each site's `/wp-content/uploads/`)
- Session rows
- Orders
- Customers
- Tier pricing tables
- Any cart state whatsoever

This is the hard isolation boundary.
