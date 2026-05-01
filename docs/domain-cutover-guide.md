# WCHS Domain Cutover Guide

This is the operator-facing guide for moving a WCHS store from a
SiteGround preview hostname to a real public domain.

The short version:

- WCHS now follows the WordPress public domain automatically in normal deployments.
- You do not need to treat this as a permalink problem.
- The only time you should enter custom WCHS origins is when the SPA truly lives on a different host from WordPress.
- External services still need human verification after the cutover because they cache or own their own URLs.

---

## What WCHS Handles Automatically Now

If **WCHS → Cutover → Origin mode** is set to **Same-origin**, WCHS derives its runtime URLs from `home_url()`:

- checkout thank-you redirects
- login/account return redirects
- Store API CORS allowlist
- admin live-preview origin
- `/wp-json/wchs/v1/config`

That means a normal domain change now behaves the same way WordPress core does:

1. `siteurl` and `home` move to the new domain
2. WCHS follows automatically
3. the Cutover tab shows the effective runtime values so you can confirm the site is aligned

Legacy `WCHS_SPA_URL`, `WCHS_ALLOWED_ORIGINS`, and `WCHS_RETURN_ORIGINS` constants are still supported as fallback overrides, but only for local dev or intentional split-origin setups.

---

## When To Use Custom Mode

Leave WCHS on **Same-origin** unless one of these is true:

- the SPA lives on a different public host than WordPress
- you need a dedicated staging storefront origin
- you are intentionally proxying or isolating the frontend on another domain/subdomain

If you use **Custom** mode, set:

- `Custom SPA origin`
- `Custom allowed origins`
- `Custom return origins`

All three should usually point at the same frontend host unless you have a very specific reason to separate them.

If you are not sure, do not use Custom mode.

---

## Standard Cutover Runbook

### 1. Before the switch

- Point the new domain at the live SiteGround host.
- Issue or verify the SSL certificate for the new domain.
- Run the repo cutover script in dry-run mode:

```bash
./scripts/cutover-domain.sh <old-domain> <new-domain> --dry-run
```

- Confirm the live site responds on the new domain with SSL.

### 2. Run the cutover

```bash
./scripts/cutover-domain.sh <old-domain> <new-domain>
```

The script:

- backs up the database
- runs a serialization-safe search-replace
- updates `siteurl` and `home`
- repairs legacy/custom WCHS origin overrides if they still exist
- updates the `robots.txt` sitemap line
- flushes cache
- runs runtime verification

### 2b. Optional guided cutover in wp-admin

For newer same-origin WCHS sites, the **WCHS → Cutover** tab now includes a
guided cutover flow:

- enter the final production domain
- click **Preview guided cutover**
- if the checks pass, click **Finalize cutover**

The guided flow:

- updates `siteurl` and `home`
- refreshes a writable local `robots.txt` sitemap line if one exists
- flushes runtime caches
- redirects `wp-admin` to the new domain

It intentionally does **not** do a DB-wide search-replace. Use the CLI script
above when you need a full migration, backup, or legacy-content rewrite.

### 3. Open WCHS → Cutover

Confirm:

- `Origin mode` is what you expect
- `Public site origin` matches the final domain
- `Effective SPA origin` is correct
- `Allowed origins` and `Return origins` are correct
- the `robots.txt` sitemap host matches the new domain

If this is a standard same-domain storefront, `Origin mode` should be **Same-origin**.

### 4. Complete the checklist

The built-in checklist is tracked per current public domain and should be used after every cutover:

- Domain + SSL are live
- WCHS runtime verified
- Payment gateway webhooks updated, if the active gateway uses them
- Omnisend store URL confirmed
- GA4 checked
- GTM preview + publish completed
- Search Console sitemap submitted
- Checkout smoke test passed

---

## Vendor Follow-Up

WCHS can move its own runtime with the site domain. It cannot automatically update third-party dashboards unless you build and authorize direct API integrations for each one.

### Payment Gateways

Update gateway webhook endpoints to the new public HTTPS domain. For
example, if a plugin uses a `wc-api` webhook URL, the host portion must
match the final domain:

```text
https://<new-domain>/?wc-api=<gateway-endpoint>
```

Then confirm any signing secret in WooCommerce if the provider issued a new one.

Why this matters:

- Payment providers expect the final public HTTPS URL.
- Redirects are not a safe substitute for the final webhook target.

### Omnisend

Confirm the store URL in Omnisend matches the new public site URL.

After changing it:

- wait a few minutes for Omnisend to refresh
- log out and back in if needed
- verify forms and tracking still load on the live storefront

### Google Analytics 4

Usually you keep the same property and measurement ID.

What you still need to do:

- verify the correct web data stream
- check Realtime on the new domain
- review unwanted referrals if checkout or payments cross domains

### Google Tag Manager

The container ID usually does not change.

What breaks most often:

- hardcoded old hostnames in custom HTML tags
- hostname-based triggers
- variables or lookup tables that still reference the old domain

Run Tag Assistant preview on the new domain and publish if anything changed.

### Search Console

Verify the new property and submit the sitemap you want crawled.

The Cutover tab shows:

- the currently advertised `robots.txt` sitemap
- the core WordPress sitemap
- the WCHS headless sitemap

Use whichever sitemap matches your production SEO strategy.

---

## What To Verify Manually

After the cutover, test the real customer flow:

1. homepage loads on the new domain
2. product detail page loads on the new domain
3. add to cart works
4. checkout loads on the new domain
5. placing an order returns to the correct thank-you URL
6. login/account redirects return to the correct origin

If you have remote smoke tests, run them against the final domain.

---

## Common Failure Modes

### Checkout returns to the old domain

This means WCHS runtime ownership is wrong, not that permalinks are wrong.

Check:

- `WCHS → Cutover → Effective SPA origin`
- `Origin mode`
- legacy `WCHS_*` constants if the tab shows warnings

### WordPress moved but WCHS did not

This used to happen when `siteurl` and `home` were updated but `WCHS_*` constants were left behind.

Same-origin mode fixes that by making WCHS follow `home_url()` automatically.

### External services still point at the old domain

That is expected until the operator updates them. WCHS can show the values to copy, but Stripe, Omnisend, Google, and Search Console still need explicit confirmation.

---

## Recommended Default Policy

For production stores:

- keep WCHS in **Same-origin**
- only use **Custom** mode for intentional split-origin setups
- use the Cutover tab as the operator checklist after every domain move
- run the cutover script instead of ad hoc DB or config edits

That is the lowest-risk path and the one the starter now optimizes for.

---

## Official References

- WordPress migration guidance: https://developer.wordpress.org/advanced-administration/upgrade/migrating/
- Payment provider webhook documentation for the selected gateway
- Omnisend store connection settings: https://support.omnisend.com/en/articles/1279825-connect-your-store-to-omnisend
- GA4 web streams: https://support.google.com/analytics/answer/14183469
- GA4 Realtime: https://support.google.com/analytics/answer/9271392
- GA4 unwanted referrals: https://support.google.com/analytics/answer/10327750
- GTM preview mode: https://support.google.com/tagmanager/answer/6107056
- GTM publish flow: https://support.google.com/tagmanager/answer/6107163
