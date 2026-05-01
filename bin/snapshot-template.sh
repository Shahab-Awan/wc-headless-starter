#!/usr/bin/env bash
# snapshot-template.sh — emit a deploy-ready per-site bundle from this repo.
#
# Usage:
#   bin/snapshot-template.sh                    # default target
#   bin/snapshot-template.sh /custom/path       # override target
#   bin/snapshot-template.sh --no-build         # skip npm run build

set -euo pipefail

TARGET=""
DO_BUILD=1
for arg in "$@"; do
  case "$arg" in
    --no-build) DO_BUILD=0 ;;
    --*)        ;;
    *)          [ -z "$TARGET" ] && TARGET="$arg" ;;
  esac
done

TARGET="${TARGET:-$HOME/dev/sites/wc-headless-starter-site}"
SOURCE="$(cd "$(dirname "$0")/.." && pwd)"

echo "==> source: $SOURCE"
echo "==> target: $TARGET"

need() {
  command -v "$1" >/dev/null 2>&1 || {
    echo "ERROR: missing: $1" >&2
    exit 1
  }
}

echo "==> step 1/7: validating prerequisites"
need node
need npm
need rsync
need sed

NODE_MAJOR=$(node -v | sed 's/^v//; s/\..*//')
if [ "$NODE_MAJOR" -lt 20 ]; then
  echo "ERROR: node $NODE_MAJOR detected; need Node 20 or newer" >&2
  exit 1
fi

if [ "$DO_BUILD" = "1" ]; then
  echo "==> step 2/7: building SPA"
  (cd "$SOURCE/spa" && npm ci && npm run build) >/tmp/wchs-snapshot-build.log 2>&1 || {
    echo "ERROR: SPA build failed. Tail:" >&2
    tail -40 /tmp/wchs-snapshot-build.log >&2
    exit 1
  }
else
  echo "==> step 2/7: skipping build (--no-build)"
  test -d "$SOURCE/spa/build/_app" || {
    echo "ERROR: spa/build/_app missing and --no-build was passed" >&2
    exit 1
  }
fi

echo "==> step 3/7: preparing target"
if [ -e "$TARGET" ] && [ ! -d "$TARGET" ]; then
  echo "ERROR: target exists and is not a directory: $TARGET" >&2
  exit 1
fi
mkdir -p "$TARGET"
for path in spa wp config scripts; do
  rm -rf "${TARGET:?}/$path"
done
rm -f "$TARGET"/{README.md,STARTER-CHECKLIST.md,CUTOVER-CHECKLIST.md,.env.example}
mkdir -p "$TARGET"/{spa,wp,config,scripts}

echo "==> step 4/7: copying build and WordPress assets"
rsync -az --delete "$SOURCE/spa/build/" "$TARGET/spa/build/"
mkdir -p "$TARGET/wp/mu-plugins" "$TARGET/wp/themes/headless-shim"
rsync -az --delete "$SOURCE/wp/mu-plugins/" "$TARGET/wp/mu-plugins/"
rsync -az --delete "$SOURCE/wp/themes/headless-shim/" "$TARGET/wp/themes/headless-shim/"

echo "==> step 5/7: copying deploy toolkit"
cp "$SOURCE/bin/templates/htaccess.template" "$TARGET/wp/htaccess.template"
cp "$SOURCE/config/wchs-settings.baseline.json" "$TARGET/config/wchs-settings.baseline.json"
cp "$SOURCE/config/trustbar-icons.json" "$TARGET/config/trustbar-icons.json"
cp "$SOURCE/bin/templates/wp-config.constants.template" "$TARGET/config/wp-config.constants.template"
cp "$SOURCE/bin/templates/setup-fresh-site.sh" "$TARGET/scripts/setup-fresh-site.sh"
cp "$SOURCE/bin/templates/deploy-siteground.sh" "$TARGET/scripts/deploy-siteground.sh"
cp "$SOURCE/bin/templates/purge-and-rebuild.sh" "$TARGET/scripts/purge-and-rebuild.sh"
cp "$SOURCE/bin/templates/cutover-domain.sh" "$TARGET/scripts/cutover-domain.sh"
cp "$SOURCE/bin/templates/verify-domain-alignment.sh" "$TARGET/scripts/verify-domain-alignment.sh"
cp "$SOURCE/bin/templates/verify-site-integrity.sh" "$TARGET/scripts/verify-site-integrity.sh"
chmod +x "$TARGET"/scripts/*.sh

cp "$SOURCE/docs/starter-checklist.md" "$TARGET/STARTER-CHECKLIST.md"
cp "$SOURCE/docs/cutover-checklist.md" "$TARGET/CUTOVER-CHECKLIST.md"
cp "$SOURCE/bin/templates/README.md" "$TARGET/README.md"
cp "$SOURCE/bin/templates/.env.example" "$TARGET/.env.example"

echo "==> step 6/7: checking snapshot for obvious placeholders and private strings"
if grep -RIlE 'example\\.invalid|CHANGE_ME|TODO_PRIVATE' "$TARGET" >/tmp/wchs-snapshot-placeholders 2>/dev/null; then
  echo "  - placeholder values remain by design:"
  sed 's/^/    - /' /tmp/wchs-snapshot-placeholders | head -20
fi

if grep -RIlE '/home/[^ ]+/dev/' "$TARGET" >/tmp/wchs-snapshot-private 2>/dev/null; then
  echo "ERROR: snapshot contains private or site-specific strings:" >&2
  sed 's/^/    - /' /tmp/wchs-snapshot-private >&2
  exit 1
fi

echo "==> step 7/7: final report"
echo
echo "Generated at: $TARGET"
du -sh "$TARGET"/* 2>/dev/null | sed 's/^/  /'
echo
echo "Next:"
echo "  cd $TARGET"
echo "  cp .env.example .env"
echo "  editor .env"
echo "  ./scripts/deploy-siteground.sh"
echo
echo "OK: snapshot complete"
