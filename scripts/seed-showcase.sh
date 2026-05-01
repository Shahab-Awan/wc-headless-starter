#!/usr/bin/env bash
# Seed a polished local demo catalog + WCHS settings from docs/assets/showcase.
# Refuses non-local WP_SITE_URL values unless WCHS_ALLOW_SHOWCASE_RESET=1.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
ASSET_DIR="$ROOT/docs/assets/showcase/generated"

cd "$ROOT"

if [[ ! -f "$ROOT/.env" ]]; then
  echo "error: $ROOT/.env not found. Copy .env.example to .env and fill in values." >&2
  exit 1
fi

set -a
# shellcheck disable=SC1091
. "$ROOT/.env"
set +a

: "${WP_SITE_URL:?WP_SITE_URL missing in .env}"
: "${WCHS_SHOWCASE_SPA_ORIGIN:=http://localhost:5175}"

case "$WP_SITE_URL" in
  http://localhost:*|http://127.0.0.1:*|http://[::1]:*)
    ;;
  *)
    if [[ "${WCHS_ALLOW_SHOWCASE_RESET:-}" != "1" ]]; then
      echo "error: seed-showcase replaces the product catalog and only runs against localhost by default." >&2
      echo "Set WCHS_ALLOW_SHOWCASE_RESET=1 only if you are deliberately targeting a disposable environment." >&2
      exit 1
    fi
    ;;
esac

if [[ ! -d "$ASSET_DIR" ]]; then
  echo "error: showcase assets not found at $ASSET_DIR" >&2
  exit 1
fi

required_assets=(
  hero-desktop.webp
  hero-mobile.webp
  product-daypack.webp
  product-tote.webp
  product-tumbler.webp
  product-lamp.webp
  product-notebook.webp
  product-pouch.webp
)

for asset in "${required_assets[@]}"; do
  if [[ ! -s "$ASSET_DIR/$asset" ]]; then
    echo "error: missing showcase asset $asset" >&2
    exit 1
  fi
done

wp() {
  docker compose exec -T -u 33:33 -e WCHS_SHOWCASE_SPA_ORIGIN="$WCHS_SHOWCASE_SPA_ORIGIN" wpcli php -d memory_limit=1024M /usr/local/bin/wp "$@"
}

if ! docker compose ps --services --filter status=running | grep -qx wpcli; then
  echo "error: wpcli container is not running. Run ./scripts/up.sh first." >&2
  exit 1
fi

if ! wp core is-installed >/dev/null 2>&1; then
  echo "error: WordPress is not installed. Run ./scripts/seed.sh first." >&2
  exit 1
fi

if ! wp plugin is-active woocommerce >/dev/null 2>&1; then
  echo "error: WooCommerce is not active. Run ./scripts/seed.sh first." >&2
  exit 1
fi

if [[ "${WCHS_SHOWCASE_KEEP_PLUGINS:-}" != "1" ]]; then
  mapfile -t active_plugins < <(wp plugin list --status=active --field=name)
  extra_plugins=()
  for plugin in "${active_plugins[@]}"; do
    [[ "$plugin" == "woocommerce" ]] && continue
    extra_plugins+=("$plugin")
  done
  if (( ${#extra_plugins[@]} > 0 )); then
    echo "Deactivating optional active plugins for a clean local showcase: ${extra_plugins[*]}"
    wp plugin deactivate "${extra_plugins[@]}"
  fi
fi

docker compose exec -T -u 0:0 wpcli sh -c 'rm -rf /tmp/wchs-showcase-assets && mkdir -p /tmp/wchs-showcase-assets'
docker compose cp "$ASSET_DIR/." wpcli:/tmp/wchs-showcase-assets/
docker compose cp "$ROOT/scripts/seed-showcase.php" wpcli:/tmp/wchs-showcase-seed.php

wp eval-file /tmp/wchs-showcase-seed.php

echo
echo "==========================================="
echo " showcase seeded"
echo " spa:      ${WCHS_SHOWCASE_SPA_ORIGIN}"
echo " wp-admin: ${WP_SITE_URL}/wp-admin"
echo "==========================================="
