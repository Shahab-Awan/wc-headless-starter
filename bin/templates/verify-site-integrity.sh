#!/usr/bin/env bash
# verify-site-integrity.sh — broader live-site audit for a WCHS host.
# Use this after restores, cutovers, or any manual repair to confirm the
# live DB, plugin base, Store API, WCHS config, uploads, and sample media
# are all sane together.

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
if [ -f "$ROOT/.env" ]; then
  # shellcheck disable=SC1091
  source "$ROOT/.env"
fi

DOMAIN="${DOMAIN:-${1:-}}"
SSH_HOST="${SSH_HOST:-sg-target}"
WP_PATH="${WP_PATH:-~/www/${DOMAIN}/public_html}"
REQUIRED_PLUGINS="${REQUIRED_PLUGINS:-woocommerce}"
MIN_PUBLISHED_PRODUCTS="${MIN_PUBLISHED_PRODUCTS:-1}"
MIN_UPLOAD_FILES="${MIN_UPLOAD_FILES:-1}"
MIN_CATEGORY_COUNT="${MIN_CATEGORY_COUNT:-1}"
PRODUCT_SAMPLE_SIZE="${PRODUCT_SAMPLE_SIZE:-3}"
MEDIA_SAMPLE_LIMIT="${MEDIA_SAMPLE_LIMIT:-12}"
SPA_ROUTE_PATHS="${SPA_ROUTE_PATHS:-/account,/shop}"
EXPECTED_BRAND="${EXPECTED_BRAND:-}"
SKIP_DOMAIN_ALIGNMENT="${SKIP_DOMAIN_ALIGNMENT:-0}"

: "${DOMAIN:?DOMAIN not set (env or first arg)}"

TMP_DIR="$(mktemp -d)"
trap 'rm -rf "$TMP_DIR"' EXIT

trim() {
  printf '%s' "$1" | sed 's/^[[:space:]]*//;s/[[:space:]]*$//'
}

contains_csv_value() {
  local needle="$1"
  local csv="$2"
  local item trimmed

  IFS=',' read -r -a items <<< "$csv"
  for item in "${items[@]}"; do
    trimmed="$(trim "$item")"
    if [ "$trimmed" = "$needle" ]; then
      return 0
    fi
  done
  return 1
}

fail() {
  echo "  ✗ $1" >&2
  exit 1
}

remote_origin_body() {
  local path="$1"
  local quoted_url
  quoted_url="$(printf '%q' "https://127.0.0.1${path}")"
  ssh "$SSH_HOST" "curl -sk -H 'Host: ${DOMAIN}' ${quoted_url}"
}

remote_origin_code() {
  local path="$1"
  local quoted_url
  quoted_url="$(printf '%q' "https://127.0.0.1${path}")"
  ssh "$SSH_HOST" "curl -sk -o /dev/null -w '%{http_code}' -H 'Host: ${DOMAIN}' ${quoted_url}"
}

remote_origin_head() {
  local path="$1"
  local quoted_url
  quoted_url="$(printf '%q' "https://127.0.0.1${path}")"
  ssh "$SSH_HOST" "curl -sk -I -H 'Host: ${DOMAIN}' ${quoted_url}"
}

remote_url_code() {
  local url="$1"
  local quoted_url
  quoted_url="$(printf '%q' "$url")"
  ssh "$SSH_HOST" "curl -sk --max-time 20 -o /dev/null -w '%{http_code}' ${quoted_url}"
}

echo "  · verifying site integrity on ${SSH_HOST}:${WP_PATH}"

if [ "$SKIP_DOMAIN_ALIGNMENT" != "1" ]; then
  ALIGN_SCRIPT="$(cd "$(dirname "$0")" && pwd)/verify-domain-alignment.sh"
  if [ -x "$ALIGN_SCRIPT" ]; then
    DOMAIN="$DOMAIN" SSH_HOST="$SSH_HOST" WP_PATH="$WP_PATH" "$ALIGN_SCRIPT"
  else
    echo "  ⚠ verify-domain-alignment.sh not found; skipping nested domain alignment check"
  fi
fi

DB_CHECK_OUTPUT="$(ssh "$SSH_HOST" "cd $WP_PATH && wp db check" 2>&1)" || fail "wp db check failed"
TABLE_PREFIX="$(ssh "$SSH_HOST" "cd $WP_PATH && wp eval 'echo \$GLOBALS[\"table_prefix\"];'" 2>/dev/null)"
TABLE_FAMILIES="$(ssh "$SSH_HOST" "cd $WP_PATH && wp db query \"SHOW TABLES\" --skip-column-names | awk '/_options$/ { sub(/options$/, \"\", \$0); print \$0 }' | paste -sd, -" 2>/dev/null)"
SITEURL="$(ssh "$SSH_HOST" "cd $WP_PATH && wp option get siteurl" 2>/dev/null)"
HOME_URL="$(ssh "$SSH_HOST" "cd $WP_PATH && wp option get home" 2>/dev/null)"
BLOGNAME="$(ssh "$SSH_HOST" "cd $WP_PATH && wp option get blogname" 2>/dev/null)"
ACTIVE_PLUGINS="$(ssh "$SSH_HOST" "cd $WP_PATH && wp plugin list --status=active --field=name | paste -sd, -" 2>/dev/null)"
WOO_COMING_SOON="$(ssh "$SSH_HOST" "cd $WP_PATH && wp option get woocommerce_coming_soon 2>/dev/null || true")"
WOO_STORE_PAGES_ONLY="$(ssh "$SSH_HOST" "cd $WP_PATH && wp option get woocommerce_store_pages_only 2>/dev/null || true")"
UPLOAD_FILE_COUNT="$(ssh "$SSH_HOST" "cd $WP_PATH && if [ -d wp-content/uploads ]; then find wp-content/uploads -type f ! -name '.htaccess' | wc -l | tr -d ' '; else echo 0; fi" 2>/dev/null)"
UPLOADS_SIZE="$(ssh "$SSH_HOST" "cd $WP_PATH && if [ -d wp-content/uploads ]; then du -sh wp-content/uploads | awk '{print \$1}'; else echo 0; fi" 2>/dev/null)"
PRODUCT_TOTAL="$(ssh "$SSH_HOST" "cd $WP_PATH && wp post list --post_type=product --post_status=any --format=count" 2>/dev/null)"
PRODUCT_PUBLISHED="$(ssh "$SSH_HOST" "cd $WP_PATH && wp post list --post_type=product --post_status=publish --format=count" 2>/dev/null)"
PAGE_TOTAL="$(ssh "$SSH_HOST" "cd $WP_PATH && wp post list --post_type=page --post_status=any --format=count" 2>/dev/null)"
WCHS_OPTIONS_PRESENT="$(ssh "$SSH_HOST" "cd $WP_PATH && wp eval 'echo ((get_option(\"wchs_site_settings\", null) !== null) && (get_option(\"wchs_homepage_config\", null) !== null) && (get_option(\"wchs_pages_config\", null) !== null)) ? \"yes\" : \"no\";'" 2>/dev/null)"

printf '%s' "$(remote_origin_body "/wp-json/wchs/v1/config?bust=$(date +%s)")" > "$TMP_DIR/config.json"
printf '%s' "$(remote_origin_body "/wp-json/wc/store/v1/products?per_page=${PRODUCT_SAMPLE_SIZE}&orderby=date&order=desc&bust=$(date +%s)")" > "$TMP_DIR/products.json"
printf '%s' "$(remote_origin_body "/wp-json/wc/store/v1/products/categories?per_page=20&bust=$(date +%s)")" > "$TMP_DIR/categories.json"

readarray -t CONFIG_META < <(python3 - "$TMP_DIR/config.json" <<'PY'
import json
import sys

with open(sys.argv[1], "r", encoding="utf-8") as fh:
    data = json.load(fh)

if not isinstance(data, dict):
    raise SystemExit("config endpoint did not return an object")

print(data.get("brand_name", ""))
print(data.get("wp_origin", ""))
print(data.get("spa_origin", ""))
print(data.get("origin_mode", ""))
print(data.get("shipping_free_threshold", ""))
PY
) || fail "WCHS config endpoint did not return valid JSON"

readarray -t PRODUCT_META < <(python3 - "$TMP_DIR/products.json" <<'PY'
import json
import sys

with open(sys.argv[1], "r", encoding="utf-8") as fh:
    data = json.load(fh)

if not isinstance(data, list):
    raise SystemExit("products endpoint did not return a list")

print(len(data))
PY
) || fail "Store API products endpoint did not return valid JSON"

readarray -t CATEGORY_META < <(python3 - "$TMP_DIR/categories.json" <<'PY'
import json
import sys

with open(sys.argv[1], "r", encoding="utf-8") as fh:
    data = json.load(fh)

if not isinstance(data, list):
    raise SystemExit("categories endpoint did not return a list")

print(len(data))
PY
) || fail "Store API categories endpoint did not return valid JSON"

python3 - "$TMP_DIR/config.json" "$TMP_DIR/products.json" "$DOMAIN" "$MEDIA_SAMPLE_LIMIT" > "$TMP_DIR/media-targets.tsv" <<'PY'
import json
import sys
from urllib.parse import urlparse

config_path, products_path, domain, limit_raw = sys.argv[1:5]
limit = int(limit_raw)

with open(config_path, "r", encoding="utf-8") as fh:
    cfg = json.load(fh)
with open(products_path, "r", encoding="utf-8") as fh:
    products = json.load(fh)

rows = []
seen = set()

def add(label, url):
    if not url or not isinstance(url, str):
        return
    parsed = urlparse(url)
    if not parsed.scheme or not parsed.netloc:
        return
    if parsed.hostname == domain:
        target = (parsed.path or "/") + (("?" + parsed.query) if parsed.query else "")
        key = ("same-host", target)
        mode = "same-host"
    else:
        target = url
        key = ("absolute", target)
        mode = "absolute"
    if key in seen:
        return
    seen.add(key)
    rows.append((label.replace("\t", " ").replace("\n", " "), mode, target))

for key in ("logo_url", "image_desktop", "image_mobile"):
    add(f"config:{key}", cfg.get(key))

for module in cfg.get("modules") or []:
    if isinstance(module, dict) and module.get("type") == "category-grid":
        for index, item in enumerate(module.get("items") or []):
            if isinstance(item, dict):
                add(f"config:category-grid:{index}", item.get("image"))

for product_index, product in enumerate(products):
    if not isinstance(product, dict):
        continue
    name = product.get("name") or f"product-{product_index + 1}"
    for image_index, image in enumerate(product.get("images") or []):
        if not isinstance(image, dict):
            continue
        add(f"product:{name}:{image_index}:src", image.get("src"))
        add(f"product:{name}:{image_index}:thumbnail", image.get("thumbnail"))
        if len(rows) >= limit:
            break
    if len(rows) >= limit:
        break

for label, mode, target in rows[:limit]:
    print(f"{label}\t{mode}\t{target}")
PY

CONFIG_BRAND="${CONFIG_META[0]:-}"
CONFIG_WP_ORIGIN="${CONFIG_META[1]:-}"
CONFIG_SPA_ORIGIN="${CONFIG_META[2]:-}"
CONFIG_ORIGIN_MODE="${CONFIG_META[3]:-}"
CONFIG_SHIPPING_THRESHOLD="${CONFIG_META[4]:-}"
PRODUCT_SAMPLE_COUNT="${PRODUCT_META[0]:-0}"
CATEGORY_COUNT="${CATEGORY_META[0]:-0}"
MEDIA_TARGET_COUNT="$(wc -l < "$TMP_DIR/media-targets.tsv" | tr -d ' ')"

echo "    table prefix:        ${TABLE_PREFIX}"
echo "    table families:      ${TABLE_FAMILIES:-<unknown>}"
echo "    siteurl:             ${SITEURL}"
echo "    home:                ${HOME_URL}"
echo "    blogname:            ${BLOGNAME}"
echo "    config brand:        ${CONFIG_BRAND}"
echo "    config spa origin:   ${CONFIG_SPA_ORIGIN}"
echo "    config mode:         ${CONFIG_ORIGIN_MODE}"
echo "    free shipping:       ${CONFIG_SHIPPING_THRESHOLD}"
echo "    active plugins:      ${ACTIVE_PLUGINS}"
echo "    woo coming soon:     ${WOO_COMING_SOON:-<unset>} (store pages only: ${WOO_STORE_PAGES_ONLY:-<unset>})"
echo "    wchs options:        ${WCHS_OPTIONS_PRESENT}"
echo "    products:            ${PRODUCT_PUBLISHED} published / ${PRODUCT_TOTAL} total"
echo "    pages:               ${PAGE_TOTAL}"
echo "    uploads:             ${UPLOAD_FILE_COUNT} files (${UPLOADS_SIZE})"
echo "    sampled categories:  ${CATEGORY_COUNT}"
echo "    sampled products:    ${PRODUCT_SAMPLE_COUNT}"
echo "    sampled media urls:  ${MEDIA_TARGET_COUNT}"
echo "    spa route probes:    ${SPA_ROUTE_PATHS}"

[ -n "$TABLE_PREFIX" ] || fail "failed to resolve the active table prefix"
[ -n "$SITEURL" ] || fail "siteurl is empty"
[ -n "$HOME_URL" ] || fail "home is empty"
[ -n "$BLOGNAME" ] || fail "blogname is empty"
[ "$WCHS_OPTIONS_PRESENT" = "yes" ] || fail "required WCHS options are missing"
[ -n "$CONFIG_BRAND" ] || fail "config endpoint brand_name is empty"
[ -n "$CONFIG_WP_ORIGIN" ] || fail "config endpoint wp_origin is empty"
[ -n "$CONFIG_SPA_ORIGIN" ] || fail "config endpoint spa_origin is empty"
[ "$PRODUCT_PUBLISHED" -ge "$MIN_PUBLISHED_PRODUCTS" ] || fail "published product count is ${PRODUCT_PUBLISHED}, expected at least ${MIN_PUBLISHED_PRODUCTS}"
[ "$PRODUCT_SAMPLE_COUNT" -ge 1 ] || fail "Store API products endpoint returned no products"
[ "$CATEGORY_COUNT" -ge "$MIN_CATEGORY_COUNT" ] || fail "Store API categories endpoint returned ${CATEGORY_COUNT}, expected at least ${MIN_CATEGORY_COUNT}"
[ "$UPLOAD_FILE_COUNT" -ge "$MIN_UPLOAD_FILES" ] || fail "uploads tree has ${UPLOAD_FILE_COUNT} files, expected at least ${MIN_UPLOAD_FILES}"

if [ "$WOO_COMING_SOON" = "yes" ]; then
  if [ "$WOO_STORE_PAGES_ONLY" = "yes" ]; then
    fail "WooCommerce store pages are still in coming-soon mode"
  fi
  fail "WooCommerce site-wide coming-soon mode is still enabled"
fi

if [ -n "$EXPECTED_BRAND" ] && [ "$CONFIG_BRAND" != "$EXPECTED_BRAND" ]; then
  fail "config endpoint brand_name is '${CONFIG_BRAND}', expected '${EXPECTED_BRAND}'"
fi

for required_plugin in ${REQUIRED_PLUGINS//,/ }; do
  contains_csv_value "$required_plugin" "$ACTIVE_PLUGINS" || fail "required active plugin '${required_plugin}' is missing"
done

if [ "$MEDIA_TARGET_COUNT" -eq 0 ]; then
  echo "  ⚠ no config/product media URLs were discovered to sample"
else
  while IFS=$'\t' read -r label mode target; do
    [ -n "$label" ] || continue
    if [ "$mode" = "same-host" ]; then
      HTTP_CODE="$(remote_origin_code "$target")"
    else
      HTTP_CODE="$(remote_url_code "$target")"
    fi
    [ "$HTTP_CODE" = "200" ] || fail "media check failed for ${label} (${target}) → HTTP ${HTTP_CODE}"
  done < "$TMP_DIR/media-targets.tsv"
fi

IFS=',' read -r -a SPA_PATHS <<< "$SPA_ROUTE_PATHS"
for spa_path in "${SPA_PATHS[@]}"; do
  spa_path="$(trim "$spa_path")"
  [ -n "$spa_path" ] || continue
  HTTP_CODE="$(remote_origin_code "$spa_path")"
  [ "$HTTP_CODE" = "200" ] || fail "SPA route probe failed for ${spa_path} → HTTP ${HTTP_CODE}"
  HEADERS="$(remote_origin_head "$spa_path")"
  if printf '%s' "$HEADERS" | grep -qi '^location:'; then
    fail "SPA route probe for ${spa_path} unexpectedly redirected"
  fi
done

echo "    db check:            $(printf '%s' "$DB_CHECK_OUTPUT" | tail -1)"
echo "  ✓ site integrity verified"
