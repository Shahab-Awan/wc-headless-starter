#!/usr/bin/env bash
# setup-fresh-site.sh — runs ON the SiteGround server (not your dev box).
#
# What it does (idempotent):
#   1. Deletes SiteGround's bundled bloat plugins (sg-cachepress, etc.)
#   2. Installs + activates WooCommerce
#   3. Activates the headless-shim theme
#   4. Seeds the five wchs_* WP options from config/wchs-settings.baseline.json
#      (with .env values envsubst'd in)
#   5. Appends WCHS constants to wp-config.php (only if missing)
#   6. Installs .htaccess
#   7. Validates by hitting /wp-json/wchs/v1/config
#
# Prereqs assumed (handled by the dev-side deploy-siteground.sh BEFORE this runs):
#   - mu-plugins + theme already rsynced into wp-content/
#   - SPA build (_app/, index.html) already rsynced into webroot
#   - .env populated and copied to the site root as ./.env
#
# Run from the deploy script (over ssh) or manually as:
#   cd /home/customer/www/<domain>/public_html && bash setup-fresh-site.sh

set -euo pipefail

# ─── Sanity ─────────────────────────────────────────────────────────
if [ ! -f .env ]; then
  echo "✗ .env missing. Copy .env.example to .env and fill it in." >&2
  exit 1
fi
# shellcheck disable=SC1091
source .env

if ! command -v wp >/dev/null 2>&1; then
  echo "✗ wp-cli not on PATH. SG usually provides it at /usr/local/wp-cli/wp-cli.php" >&2
  exit 1
fi

echo "» starting setup for ${DOMAIN}"

# ─── Step 1: Delete SG bloat plugins ────────────────────────────────
echo "» step 1/7: deleting SiteGround bundled plugins"
for p in sg-cachepress sg-security sg-ai-studio wordpress-starter; do
  wp plugin deactivate "$p" --quiet 2>/dev/null || true
  wp plugin delete "$p" --quiet 2>/dev/null || true
done

# ─── Step 2: Required plugins ───────────────────────────────────────
echo "» step 2/7: installing + activating WooCommerce"
for p in woocommerce; do
  wp plugin is-installed "$p" 2>/dev/null || wp plugin install "$p" --quiet
  wp plugin is-active   "$p" 2>/dev/null || wp plugin activate "$p" --quiet
done

# ─── Step 3: Theme ──────────────────────────────────────────────────
echo "» step 3/7: activating headless-shim theme"
wp theme is-installed headless-shim || {
  echo "✗ headless-shim not in wp-content/themes/. Did the deploy script rsync it?" >&2
  exit 1
}
wp theme activate headless-shim

# ─── Step 4: Seed WCHS options from baseline JSON ───────────────────
echo "» step 4/7: seeding wchs_* WP options"
if [ ! -f config/wchs-settings.baseline.json ]; then
  echo "✗ config/wchs-settings.baseline.json missing." >&2
  exit 1
fi
# Substitute .env values into placeholders (${BRAND_NAME}, ${GTM_ID}, etc.)
# Portable substitution: prefer envsubst when available, fall back to a
# bash-only substitution that handles ${VAR} (envsubst is in gettext-base
# package which isn't always installed on shared hosting like SiteGround).
TMPJSON=$(mktemp)
if command -v envsubst >/dev/null 2>&1; then
  envsubst < config/wchs-settings.baseline.json > "$TMPJSON"
else
  # Bash-only fallback: read the file, then for each ${VAR} reference,
  # replace with the env value. Uses parameter expansion (no eval).
  CONTENT=$(cat config/wchs-settings.baseline.json)
  # Substitute every ${VAR} we know about — explicit list keeps this safe.
  for var in DOMAIN SPA_URL BRAND_NAME \
             EASYPOST_API_KEY GOOGLE_MAPS_API_KEY \
             TURNSTILE_SITE_KEY TURNSTILE_SECRET_KEY \
             GTM_ID META_PIXEL_ID TIKTOK_PIXEL_ID PINTEREST_TAG_ID \
             KLAVIYO_PUBLIC_KEY OMNISEND_BRAND_ID \
             CLARITY_PROJECT_ID HOTJAR_SITE_ID \
             GOOGLE_ADS_CONVERSION_ID GOOGLE_ADS_CONVERSION_LABEL; do
    val="${!var:-}"
    # JSON-escape: backslashes and double-quotes only (keys/values are simple)
    val_esc="${val//\\/\\\\}"
    val_esc="${val_esc//\"/\\\"}"
    CONTENT="${CONTENT//\$\{${var}\}/${val_esc}}"
  done
  printf '%s' "$CONTENT" > "$TMPJSON"
fi

# Each top-level key in baseline.json corresponds to a WP option name.
for opt in wchs_site_settings wchs_homepage_config wchs_pdp_config wchs_pages_config wchs_shop_config; do
  echo "  · $opt"
  jq -c ".${opt} // {}" "$TMPJSON" | wp option update "$opt" --format=json
done
rm -f "$TMPJSON"

# ─── Step 5: wp-config constants ────────────────────────────────────
echo "» step 5/7: appending brand/integration constants (idempotent)"
if ! grep -q "Headless WC Starter - per-site configuration" wp-config.php; then
  TMPCFG=$(mktemp)
  if command -v envsubst >/dev/null 2>&1; then
    envsubst < config/wp-config.constants.template > "$TMPCFG"
  else
    # Same bash-only fallback as the JSON case
    CONTENT=$(cat config/wp-config.constants.template)
    for var in DOMAIN SPA_URL BRAND_NAME; do
      val="${!var:-}"
      val_esc="${val//\\/\\\\}"
      val_esc="${val_esc//\"/\\\"}"
      val_esc="${val_esc//\'/\\\'}"
      CONTENT="${CONTENT//\$\{${var}\}/${val_esc}}"
    done
    printf '%s' "$CONTENT" > "$TMPCFG"
  fi
  # Insert above the "/* That's all, stop editing! */" line.
  # Using awk because sed-based multiline insertion mangles slashes and
  # leaves trailing backslashes in the output. Awk reads the constants
  # block from a file and prints it before the marker line — no escaping.
  if grep -q "stop editing" wp-config.php; then
    TMPNEW=$(mktemp)
    awk -v cf="$TMPCFG" '
      BEGIN {
        while ((getline line < cf) > 0) c = c line "\n"
        close(cf)
      }
      /\/\* That.s all, stop editing/ && !done { printf "%s", c; done=1 }
      { print }
    ' wp-config.php > "$TMPNEW"
    mv "$TMPNEW" wp-config.php
  else
    cat "$TMPCFG" >> wp-config.php
  fi
  rm -f "$TMPCFG"
  echo "  · constants appended"
else
  echo "  · already present, skipping"
fi

# ─── Step 6: .htaccess ──────────────────────────────────────────────
echo "» step 6/7: installing .htaccess"
if [ ! -f wp/htaccess.template ]; then
  echo "✗ wp/htaccess.template missing." >&2
  exit 1
fi
cp wp/htaccess.template .htaccess

# ─── Step 7: Validate ───────────────────────────────────────────────
echo "» step 7/7: validating"
sleep 2
RESP=$(curl -fsS "https://${DOMAIN}/wp-json/wchs/v1/config" 2>&1 || echo "FAILED")
if echo "$RESP" | jq -e '.brand_name' >/dev/null 2>&1; then
  BRAND=$(echo "$RESP" | jq -r .brand_name)
  echo "  ✓ /wchs/v1/config responding — brand_name='${BRAND}'"
else
  echo "  ⚠ /wchs/v1/config did not return JSON. Response (first 200 chars):"
  echo "$RESP" | head -c 200
  echo
  echo "  Common causes: SG NGINX cache (wait 10 min or purge), .htaccess rules"
  echo "  out of order, mu-plugins missing. See docs/STARTER-CHECKLIST.md sections 3, 4, 10."
  exit 1
fi

echo
echo "✓ Setup complete. Open https://${DOMAIN}/wp-admin → WCHS Settings to fine-tune branding."
echo "  Then run the integrity verifier from your dev box:"
echo "    DOMAIN=${DOMAIN} SSH_HOST=<ssh-alias> WP_PATH=${WP_PATH} ./scripts/verify-site-integrity.sh"
