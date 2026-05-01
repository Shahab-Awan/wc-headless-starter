# wc-starter — fresh-clone kit for headless WooCommerce on SiteGround

This folder is a **regenerated artifact**. To refresh it with the latest
mu-plugins, SPA build, and WCHS settings schema, run
`bin/snapshot-template.sh` from the source repository.

---

## What's here

```
.
├── STARTER-CHECKLIST.md          ← read this first; ordered list of every gotcha
├── README.md                     ← you are here
├── .env.example                  ← copy to .env, fill in values
├── spa/build/                    ← pre-built static SPA (drop-in)
├── wp/
│   ├── mu-plugins/               ← required WP mu-plugins (sterilized)
│   ├── themes/headless-shim/     ← required WP theme
│   └── htaccess.template         ← exact .htaccess for SiteGround Apache
├── config/
│   ├── wchs-settings.baseline.json   ← seed for the 5 wchs_* WP options
│   ├── wp-config.constants.template  ← lines appended to wp-config.php
│   └── trustbar-icons.json           ← reference icon set (already inlined in TrustBar.svelte)
├── scripts/
│   ├── deploy-siteground.sh      ← run from your dev box (END-TO-END)
│   ├── setup-fresh-site.sh       ← runs ON the SG server (called by deploy)
│   ├── purge-and-rebuild.sh      ← quick redeploy after iterating
│   ├── verify-domain-alignment.sh← runtime/cutover host check
│   └── verify-site-integrity.sh  ← broader post-restore/post-repair audit
```

---

## Quick start (25–40 min)

1. `cp -r ~/dev/starters/wc-headless-starter-snapshot ~/dev/sites/<newsite>`
2. `cd ~/dev/sites/<newsite>`
3. Read `STARTER-CHECKLIST.md` — it walks through every SiteGround gotcha in order.
4. `cp .env.example .env` and fill in: domain, SSH alias, brand name,
   SMTP, and any per-site integration keys.
5. Provision SiteGround (Site Tools): WordPress install + SSH key + MySQL DB + SSL.
6. Add the SSH alias to `~/.ssh/config` (template is in the checklist).
7. `./scripts/deploy-siteground.sh` — does the rest end-to-end.
8. Open `https://<your-domain>` — homepage renders.
9. `https://<your-domain>/wp-admin` → WCHS Settings → fine-tune branding.
10. Run `DOMAIN=<your-domain> SSH_HOST=<ssh-alias> WP_PATH=<remote-wp-path> ./scripts/verify-site-integrity.sh` after any restore, cutover, or manual live repair.

If anything fails: every "if X then Y" is in `STARTER-CHECKLIST.md`.

---

## What's NOT here (do this manually)

- **DNS** — point your A-record at SiteGround. They have a wizard.
- **SSL** — Site Tools → Security → SSL Manager → Let's Encrypt.
- **Real product data** — this starter includes zero live products.
  Import a catalog or add products via wp-admin → Products → Add New.
- **Payment gateway webhooks** — the deploy script does NOT register
  dashboard webhooks for gateway plugins. Configure those in the payment
  provider dashboard after the selected gateway plugin is installed.

---

## Regenerating this folder

When the source repo adds new mu-plugins, SPA features, or WCHS schema
changes, regenerate from source:

```bash
cd /path/to/wc-headless-starter
bin/snapshot-template.sh
# → the target snapshot folder is rebuilt from the current checkout
```

Existing site folders are NOT touched — they're
yours to maintain. To pull in upstream improvements, manually rsync the
delta from the regenerated snapshot into your site folder.
