# Domain cutover checklist — SG preview → production

Use this when swapping a WCHS site from a SiteGround preview hostname to
its real production domain.

Budget: 20–30 minutes on the good path. Up to 2 hours if DNS is slow or
SSL issuance is flaky.

> **Before you start**: the auto-applied steps (search-replace, runtime
> alignment repair, cache purge) are handled by `scripts/cutover-domain.sh`
> in every wc-starter deploy. The script also repairs the common
> partial-cutover case where `siteurl`/`home` already point at the new domain
> but legacy WCHS overrides or `robots.txt` still reference the old SiteGround
> hostname. The HUMAN steps below are what's left after the script finishes.

---

## T-minus (before the script)

- [ ] **DNS**: A-record for `<new-domain>` → SiteGround host IP is in place. Propagated? Verify: `dig +short <new-domain>`.
- [ ] **SSL**: SiteGround Site Tools → Security → SSL Manager → install Let's Encrypt for `<new-domain>`. Wait for green checkmark.
- [ ] **Pre-flight curl**: `curl -sk -o /dev/null -w "%{http_code}" https://<new-domain>/` returns `202` (captcha) or `200`, not `000` or connection-refused. Confirms DNS + SSL are live.
- [ ] **Maintenance window** (optional): SG Site Tools → Dev → put site in maintenance mode while cutover runs (~30s of wp-cli operations shouldn't be noticed by users).

## T-zero (run the script)

If this is a newer **same-origin** WCHS site and you only need the WordPress-like
core URL swap, you can also use **WCHS → Cutover → Preview/Finalize guided
cutover** from wp-admin. That guided flow updates `siteurl` / `home`, refreshes
local `robots.txt` if needed, flushes caches, and redirects wp-admin to the new
domain.

Use the CLI script below when you want the full migration path, especially:

- DB backup
- serialization-safe search-replace
- older sites with legacy absolute URLs in stored content

```bash
# Dry-run first — see exactly what WILL change
./scripts/cutover-domain.sh <old-domain> <new-domain> --dry-run

# If numbers look right (expect 100–300 DB replacements), run live
./scripts/cutover-domain.sh <old-domain> <new-domain>
```

The script handles:
1. DB backup to `~/wchs-cutover-backups/` (timestamped gzipped SQL)
2. `wp search-replace` across all tables, serialization-safe, skipping GUIDs
3. Explicit `siteurl` + `home` update
4. Repair of legacy/custom WCHS origin overrides if they still exist
5. `robots.txt` Sitemap URL update
6. WP cache flush + SG Dynamic Cache purge
7. Deep runtime verification (`siteurl`, `home`, effective WCHS runtime, `robots.txt`, config endpoint)
8. Post-cutover validation curl

## T-plus (human-driven — the script tells you what's left)

- [ ] **Payment webhooks**: For each active payment gateway, update the provider dashboard from `<old-domain>` to `<new-domain>`. If the provider issues a new signing secret, paste it into that gateway's WooCommerce settings.
- [ ] **Google Search Console**: Add `<new-domain>` as a new property. Verify via DNS TXT record OR HTML meta tag. Submit `https://<new-domain>/wp-sitemap.xml`. (Don't delete the old property — it'll report the 301 redirect transition cleanly.)
- [ ] **GA4 / GTM**: Update the property's associated URL in Google Analytics Admin → Data Streams. Keep the same Measurement ID — just swap the URL.
- [ ] **Omnisend / Klaviyo / Meta pixel**: Log into each dashboard, confirm the Store URL field reads `<new-domain>`. These plugins often cache the site URL at install time.
- [ ] **WCHS → Cutover**: Confirm `Origin mode` is correct, `Public site origin` and `Effective SPA origin` match the final domain, and the copy targets look right for gateway / sitemap / account testing.
- [ ] **wp-admin smoke**: Log into `https://<new-domain>/wp-admin/`. Confirm: WCHS Settings loads without 404s, product permalinks work, hero image loads.
- [ ] **Woo launch state**: WooCommerce → Home / Launch your store. Confirm Coming Soon is OFF before you call the site live. The exact failure mode here is subtle: the homepage can look public while Woo still blocks `/shop`, `/cart`, `/checkout`, and other store pages with the core "Great things are on the horizon" screen.
- [ ] **Runtime alignment check**: `DOMAIN=<new-domain> SSH_HOST=<ssh-alias> ./scripts/verify-domain-alignment.sh`
- [ ] **Full integrity audit**: `DOMAIN=<new-domain> SSH_HOST=<ssh-alias> WP_PATH='<remote-public-html>' ./scripts/verify-site-integrity.sh`
- [ ] **Golden-path test**: homepage → click product → add to cart → open slide cart → click checkout → verify checkout loads with the new domain.
- [ ] **E2E**: run your local Playwright smoke test against `https://<new-domain>` or use the generated integrity verifier.
- [ ] **301 redirect from old to new** (optional but standard): in SG Site Tools → Domain → Parked Domains, add `<old-domain>` as a parked domain redirecting to `<new-domain>` with 301. Preserves any backlinks pointing at the preview URL.

---

## If something breaks

- **"Too many redirects" on homepage after cutover**: `siteurl` + `home` got set to the wrong URL. Fix: `wp option update siteurl 'https://<new-domain>'` on SSH.
- **Mixed-content warnings in console**: Some DB content still uses `http://` form. Re-run the script with the same args — search-replace is idempotent.
- **Serialized data corrupt**: The `--recurse-objects --precise` flags prevent this, but if it happens, restore from the backup: `gunzip -c <backup>.sql.gz | wp db import -` on SSH, then retry.
- **External apps failing**: Their dashboards still have the old domain cached. Use WCHS → Cutover for the exact values to paste, then re-check the vendor dashboards.
- **SG captcha blocking everything**: Fresh domain means the captcha IP-allowlist has to rebuild. Wait 10 min, purge SG cache.
- **Homepage is live but `/checkout` shows "Great things are on the horizon"**: WooCommerce Coming Soon is still enabled for store pages. Check `wp option get woocommerce_coming_soon` and `wp option get woocommerce_store_pages_only` over SSH, or turn it off in WooCommerce → Home / Launch your store.

## Backup / rollback

Every live run creates: `~/wchs-cutover-backups/cutover-<old>-to-<new>-<timestamp>.sql.gz`

To roll back (rare, but possible):
```bash
gunzip -c ~/wchs-cutover-backups/<backup>.sql.gz | ssh <ssh-host> "cd <wp-path> && wp db import -"
ssh <ssh-host> "cd <wp-path> && wp option update siteurl 'https://<old-domain>' && wp option update home 'https://<old-domain>'"
# Edit wp-config.php to swap the WCHS_* constants back
# Purge SG cache
```
