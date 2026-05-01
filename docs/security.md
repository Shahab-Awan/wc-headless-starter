# Security & Rate Limiting

Production deployment guide for securing a wc-headless-starter site.

---

## Credentials & secrets

**Nothing secret is committed to this repo.** All credentials live in `.env`
(gitignored). The committed `.env.example` has `CHANGE_ME` placeholders only.

Before going live:

- Generate strong passwords: `openssl rand -hex 24`
- Rotate the default admin password immediately after install
- Never put live payment provider keys in `.env` during development
- SMTP credentials go in either `wp-config.php` constants or the admin panel
  (constants take priority; see `headless-smtp.php`)

### Where API keys are stored

| Key | Where | Notes |
|-----|-------|-------|
| Stripe | `.env` (dev) / `wp-config.php` (prod) | Test keys only in .env |
| EasyPost | WP Admin > WCHS > Checkout tab | Stored in `wp_options` |
| Turnstile (Cloudflare) | WP Admin > WCHS > Integrations tab | Site key + secret key |
| Google Maps | WP Admin > WCHS > Checkout tab | For address autocomplete |
| SMTP | `wp-config.php` constants OR admin panel | Constants override DB |
| GTM container ID | WP Admin > WCHS > Integrations tab | Not a secret, but noted |

None of these are in the codebase. If you see a real key in source, it's a bug.

---

## Rate limiting

### How it works

All custom REST endpoints use a per-IP token-bucket rate limiter backed by
WordPress transients. Each endpoint has its own bucket so a noisy endpoint
doesn't starve others.

**Rate limiting is disabled when `WP_DEBUG` is `true`.** In local dev, the
SPA dev server proxies all requests through the same IP, so rate limits are
meaningless and will lock you out during testing. In production, set
`WP_DEBUG` to `false`.

### Custom endpoint limits

| Endpoint | Method | Limit | Window | Notes |
|----------|--------|-------|--------|-------|
| `/wchs/v1/config` | GET | 60 | 60s | SPA boot config |
| `/wchs/v1/session` | GET | 120 | 60s | Auth check (polled on mount, focus, nav) |
| `/wchs/v1/session` | DELETE | 10 | 60s | Logout |
| `/wchs/v1/reviews/{id}` | GET | 120 | 60s | Read reviews |
| `/wchs/v1/reviews/{id}` | POST | 5 | 60s | Write reviews |
| `/wchs/v1/my-orders` | GET | 30 | 60s | Order history |
| `/wchs/v1/contact` | POST | 5 | 15min | Contact form (also Turnstile-gated) |
| `/wchs/v1/order-payment/{id}` | GET | none | -- | Gated by secret order key |
| `wp_ajax_wchs_validate_address` | POST | 10 | 60s | EasyPost ($0.02/call) |
| `wp_ajax_wchs_capture_cart_email` | POST | 5 | 60s | Abandoned cart capture |
| `wp_ajax_wchs_resend_verification` | POST | 1 | 60s | Email verification resend |
| `wp_ajax_wchs_verify_email_code` | POST | none | -- | 5 wrong attempts burns the code |
| `wp_ajax_wchs_product_search` | POST | none | -- | Admin-only (manage_options) |
| `wp_ajax_wchs_product_variations` | POST | none | -- | Admin-only (manage_options) |

### WooCommerce & WordPress native endpoints have NO rate limiting

The WC Store API (`/wc/store/v1/products`, `/wc/store/v1/cart`, etc.) and
WordPress REST API (`/wp/v2/`) come with **zero rate limiting**. A bot can
hit `/wc/store/v1/products` thousands of times with no throttle.

Our access control plugin (`headless-access-control.php`) blocks unauthorized
access by mode, but returns 403/503 responses — it doesn't prevent volume abuse.

**You MUST add rate limiting at the infrastructure level for production.**

---

## Production rate limiting (REQUIRED)

### Option 1: Cloudflare (recommended)

If your site is behind Cloudflare:

1. **WAF rate limiting rules** - Create rules under Security > WAF:
   - `/wp-json/*` — 300 req/min per IP, block for 60s
   - `/wp-json/wc/store/v1/cart/add-item` — 30 req/min per IP
   - `/wp-json/wc/store/v1/checkout` — 10 req/min per IP
   - `/wp-admin/admin-ajax.php` — 60 req/min per IP

2. **Bot Management** - Enable under Security > Bots. The free tier includes
   basic bot detection. Paid plans add JS challenge for suspicious traffic.

3. **Real IP forwarding** - Add to your nginx config:

   ```nginx
   # /etc/nginx/conf.d/cloudflare-realip.conf
   # Cloudflare IPv4 ranges (update periodically from https://cloudflare.com/ips)
   set_real_ip_from 173.245.48.0/20;
   set_real_ip_from 103.21.244.0/22;
   set_real_ip_from 103.22.200.0/22;
   set_real_ip_from 103.31.4.0/22;
   set_real_ip_from 141.101.64.0/18;
   set_real_ip_from 108.162.192.0/18;
   set_real_ip_from 190.93.240.0/20;
   set_real_ip_from 188.114.96.0/20;
   set_real_ip_from 197.234.240.0/22;
   set_real_ip_from 198.41.128.0/17;
   set_real_ip_from 162.158.0.0/15;
   set_real_ip_from 104.16.0.0/13;
   set_real_ip_from 104.24.0.0/14;
   set_real_ip_from 172.64.0.0/13;
   set_real_ip_from 131.0.72.0/22;
   # Cloudflare IPv6
   set_real_ip_from 2400:cb00::/32;
   set_real_ip_from 2606:4700::/32;
   set_real_ip_from 2803:f800::/32;
   set_real_ip_from 2405:b500::/32;
   set_real_ip_from 2405:8100::/32;
   set_real_ip_from 2a06:98c0::/29;
   set_real_ip_from 2c0f:f248::/32;
   real_ip_header CF-Connecting-IP;
   ```

### Option 2: Nginx rate limiting (no CDN)

If you're not using Cloudflare, add rate limiting directly in nginx:

```nginx
# In http {} block:
limit_req_zone $binary_remote_addr zone=wpapi:10m rate=5r/s;
limit_req_zone $binary_remote_addr zone=wccart:10m rate=1r/s;

# In server {} block:
location /wp-json/ {
    limit_req zone=wpapi burst=20 nodelay;
    # ... proxy_pass etc.
}

location ~ /wp-json/wc/store/v1/cart/(add-item|update-item|remove-item) {
    limit_req zone=wccart burst=5 nodelay;
    # ... proxy_pass etc.
}
```

### Why this matters

Without infrastructure-level rate limiting:

- A bot can scrape your entire product catalog via the Store API
- A bot can flood `/cart/add-item` to exhaust server resources
- Our application-level limits only cover custom `/wchs/v1/` endpoints
- WooCommerce and WordPress provide no built-in protection

**The application-level rate limits in this project are a second line of
defense. They are not sufficient as the only rate limiting layer.**

---

## Real IP forwarding (CRITICAL)

The application-level rate limiter uses `$_SERVER['REMOTE_ADDR']` to
identify clients. Behind a reverse proxy (nginx, Cloudflare, load balancer),
`REMOTE_ADDR` is the proxy's IP, not the client's. This means:

- **All visitors share one rate limit bucket** (the proxy's IP)
- One aggressive client rate-limits everyone
- Or worse: the limit is so high per-"IP" that it's effectively useless

**You MUST configure real IP forwarding so `REMOTE_ADDR` reflects the
actual client IP.** See the Cloudflare and nginx examples above.

Verify it's working:

```php
// Add temporarily to a mu-plugin to check:
add_action('init', function() {
    if (current_user_can('manage_options') && isset($_GET['debug_ip'])) {
        wp_die('REMOTE_ADDR: ' . $_SERVER['REMOTE_ADDR']);
    }
});
```

Visit `?debug_ip=1` while logged in as admin. If you see `127.0.0.1` or a
private IP instead of your real public IP, real IP forwarding is not configured.

---

## Access control modes

| Mode | Guest | Verified member | Admin |
|------|-------|-----------------|-------|
| 0 - Maintenance | Blocked (503) | Blocked (503) | Full access + red banner |
| 1 - Locked | Blocked (403 on all product/cart APIs) | Full access | Full access + amber banner |
| 2 - Browse-only | Can browse, cart writes 403 | Full access | Full access + blue banner |
| 3 - Open | Full access | Full access | Full access |

When email verification is enabled, unverified users are treated as guests
for all access mode checks. They can log in, but they can't do anything a
guest can't do until they verify their email.

Admins (users with `manage_options` capability) always bypass all access
restrictions.

---

## Email verification

When enabled in WP Admin > WCHS > Access & Privacy:

- New registrations get `wchs_email_verified = false` set explicitly
- A 6-digit code is emailed via the WC email system
- Codes expire in 15 minutes, max 5 attempts per code
- Resend is rate-limited to 1 per 60 seconds
- **Existing users are grandfathered** — users without `wchs_email_verified`
  meta are treated as verified (the meta's absence = verified)

---

## Turnstile bot protection

Cloudflare Turnstile is integrated on:

- Checkout
- Login
- Registration
- Contact form

Configure the site key and secret key in WP Admin > WCHS > Integrations.
Turnstile is optional — if no keys are configured, forms work without it.

---

## Security headers

The CORS plugin (`headless-cors.php`) adds to all responses:

- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: SAMEORIGIN`
- `X-XSS-Protection: 0` (modern browsers use CSP instead)
- Strict `Access-Control-Allow-Origin` from the allowlist
- `Access-Control-Allow-Credentials: true` only for allowed origins

---

## Test credentials (development only)

The E2E test suite uses these hardcoded credentials:

| User | Login | Password | Purpose |
|------|-------|----------|---------|
| Admin | `admin` | `wchs-admin-dev` | Full admin access |
| Verified customer | `verified@example.test` | `testpass123` | Email-verified customer |
| Unverified customer | `unverified_test` | `testpass123` | Unverified email customer |

These are created by the seed script for local development only. They do not
exist in production. The passwords are intentionally weak because this is a
local Docker environment.

**Do not reuse these credentials in production.**
