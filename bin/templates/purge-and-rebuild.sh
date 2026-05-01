#!/usr/bin/env bash
# purge-and-rebuild.sh — quick redeploy after iterating on SPA or mu-plugins.
# Skips the heavy first-time setup; just rsyncs deltas + bumps SG cache.
#
# Usage: ./scripts/purge-and-rebuild.sh

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

if [ ! -f .env ]; then echo "✗ .env missing" >&2; exit 1; fi
# shellcheck disable=SC1091
source .env

: "${SSH_HOST:?SSH_HOST not set}"
: "${WP_PATH:?WP_PATH not set}"
: "${SPA_URL:?SPA_URL not set}"

# Optional: rebuild SPA if --build flag passed
if [ "${1:-}" = "--build" ]; then
  echo "» rebuilding SPA"
  (cd spa && npm run build)
fi

if [ ! -d spa/build/_app ]; then
  echo "✗ spa/build/_app missing. Run with --build to rebuild." >&2
  exit 1
fi

echo "» rsync mu-plugins"
rsync -az --delete wp/mu-plugins/ "$SSH_HOST:$WP_PATH/wp-content/mu-plugins/"

echo "» rsync SPA"
ssh "$SSH_HOST" "rm -rf $WP_PATH/_app"
rsync -az spa/build/_app/ "$SSH_HOST:$WP_PATH/_app/"
rsync -az spa/build/index.html "$SSH_HOST:$WP_PATH/index.html"

echo "» SG cache purge cycle (activate → purge → deactivate)"
ssh "$SSH_HOST" "cd $WP_PATH && wp plugin activate sg-cachepress --quiet 2>/dev/null && wp sg purge 2>&1 | head -3 ; wp plugin deactivate sg-cachepress --quiet 2>/dev/null" || true

echo "» smoke"
HTTP_CODE=$(curl -sko /dev/null -w "%{http_code}" "${SPA_URL}/?_=$(date +%s)")
echo "  ${SPA_URL} → ${HTTP_CODE}"

echo "✓ Done."
