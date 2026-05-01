#!/usr/bin/env bash
# deploy-siteground.sh — runs from your DEV BOX. End-to-end clone deploy.
#
# What it does:
#   1. Validates .env is filled in
#   2. Validates SSH alias works + remote wp-cli is responsive
#   3. Rsyncs mu-plugins, theme, .htaccess, config files, setup script to the server
#   4. Rsyncs the pre-built SPA artifact (spa/build/_app/ + index.html)
#      from this generated site folder to the webroot
#   5. SSH-runs setup-fresh-site.sh on the server
#   6. Runs a final curl check
#
# Idempotent: safe to re-run. rsync is idempotent. wp plugin activate is
# idempotent. wp-config constant append is guarded by grep.
#
# Usage:
#   cd ~/dev/sites/<newsite>     # the cp -r'd starter folder
#   ./scripts/deploy-siteground.sh

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
if [ "$(basename "$SCRIPT_DIR")" = "templates" ] && [ -f "$ROOT/snapshot-template.sh" ]; then
  echo "✗ This is the template source script inside wc-headless-starter." >&2
  echo "  Generate a site folder first: bin/snapshot-template.sh ~/dev/sites/<site>" >&2
  echo "  Then run ./scripts/$(basename "$0") from that generated folder." >&2
  exit 1
fi
cd "$ROOT"

# ─── Sanity ─────────────────────────────────────────────────────────
if [ ! -f .env ]; then
  echo "✗ .env missing. Copy .env.example to .env and fill it in." >&2
  exit 1
fi
# shellcheck disable=SC1091
source .env

: "${DOMAIN:?DOMAIN not set in .env}"
: "${SPA_URL:?SPA_URL not set in .env}"
: "${SSH_HOST:?SSH_HOST not set in .env}"
: "${WP_PATH:?WP_PATH not set in .env}"
: "${BRAND_NAME:?BRAND_NAME not set in .env}"

echo "» deploying ${BRAND_NAME} → ${DOMAIN}"
echo "  via ${SSH_HOST}:${WP_PATH}"

# ─── Step 1: Verify SSH + wp-cli ────────────────────────────────────
echo "» step 1/6: SSH + wp-cli"
WPVER=$(ssh "$SSH_HOST" "cd $WP_PATH && wp core version" 2>&1) || {
  echo "✗ wp-cli failed on remote. SSH alias '${SSH_HOST}' working? wp-config readable?" >&2
  echo "  Got: $WPVER" >&2
  exit 1
}
echo "  · WP $WPVER"

# ─── Step 2: Rsync server-side files ────────────────────────────────
echo "» step 2/6: rsync mu-plugins + theme"
rsync -az --delete wp/mu-plugins/ "$SSH_HOST:$WP_PATH/wp-content/mu-plugins/"
rsync -az --delete wp/themes/headless-shim/ "$SSH_HOST:$WP_PATH/wp-content/themes/headless-shim/"

echo "» step 3/6: rsync config + setup script + .env"
ssh "$SSH_HOST" "mkdir -p $WP_PATH/config $WP_PATH/wp"
rsync -az config/ "$SSH_HOST:$WP_PATH/config/"
rsync -az wp/htaccess.template "$SSH_HOST:$WP_PATH/wp/htaccess.template"
rsync -az scripts/setup-fresh-site.sh "$SSH_HOST:$WP_PATH/setup-fresh-site.sh"
rsync -az .env "$SSH_HOST:$WP_PATH/.env"

# ─── Step 4: Rsync SPA build (must be pre-built; the snapshot includes build/) ──
echo "» step 4/6: rsync SPA build"
if [ ! -d spa/build/_app ]; then
  echo "✗ spa/build/_app missing. The snapshot includes a pre-built SPA." >&2
  echo "  If you customized the SPA, run: cd spa && npm ci && npm run build" >&2
  exit 1
fi
ssh "$SSH_HOST" "rm -rf $WP_PATH/_app"
rsync -az spa/build/_app/ "$SSH_HOST:$WP_PATH/_app/"
rsync -az spa/build/index.html "$SSH_HOST:$WP_PATH/index.html"

# ─── Step 5: Run server-side setup ──────────────────────────────────
echo "» step 5/6: running setup-fresh-site.sh on server"
ssh "$SSH_HOST" "cd $WP_PATH && bash setup-fresh-site.sh"

# ─── Step 6: Final smoke check ──────────────────────────────────────
echo "» step 6/6: final smoke check"
sleep 3
HTTP_CODE=$(curl -sko /dev/null -w "%{http_code}" "${SPA_URL}/?_=$(date +%s)")
if [ "$HTTP_CODE" = "200" ]; then
  echo "  ✓ ${SPA_URL} → 200"
else
  echo "  ⚠ ${SPA_URL} → HTTP ${HTTP_CODE}. Check SG dashboard, .htaccess, NGINX cache."
  exit 1
fi

echo
echo "✓ Deploy complete."
echo "  Open https://${DOMAIN} in a browser to verify the SPA renders."
echo "  Open https://${DOMAIN}/wp-admin → WCHS Settings to fine-tune branding."
