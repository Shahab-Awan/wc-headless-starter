#!/usr/bin/env bash
# verify-domain-alignment.sh — assert that a live WCHS host's runtime
# settings agree with the public domain. Same-origin sites must resolve
# WCHS URLs from `home_url()` automatically; custom-mode sites must at
# least be internally consistent.

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
if [ -f "$ROOT/.env" ]; then
  # shellcheck disable=SC1091
  source "$ROOT/.env"
fi

DOMAIN="${DOMAIN:-${1:-}}"
SSH_HOST="${SSH_HOST:-sg-target}"
WP_PATH="${WP_PATH:-~/www/${DOMAIN}/public_html}"

: "${DOMAIN:?DOMAIN not set (env or first arg)}"

EXPECTED="https://${DOMAIN}"

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

echo "  · verifying domain alignment on ${SSH_HOST}:${WP_PATH}"

SITEURL=$(ssh "$SSH_HOST" "cd $WP_PATH && wp option get siteurl" 2>/dev/null)
HOME_URL=$(ssh "$SSH_HOST" "cd $WP_PATH && wp option get home" 2>/dev/null)
ORIGIN_MODE=$(ssh "$SSH_HOST" "cd $WP_PATH && wp eval 'if ( function_exists(\"wchs_origin_mode\") ) { echo wchs_origin_mode(); } elseif ( defined(\"WCHS_SPA_URL\") || defined(\"WCHS_ALLOWED_ORIGINS\") || defined(\"WCHS_RETURN_ORIGINS\") ) { echo \"custom\"; } else { echo \"same-origin\"; }'" 2>/dev/null)
EFFECTIVE_SPA_ORIGIN=$(ssh "$SSH_HOST" "cd $WP_PATH && wp eval 'if ( function_exists(\"wchs_spa_origin\") ) { echo wchs_spa_origin(); } elseif ( defined(\"WCHS_SPA_URL\") ) { echo rtrim(WCHS_SPA_URL, \"/\"); } else { echo rtrim(get_option(\"home\"), \"/\"); }'" 2>/dev/null)
EFFECTIVE_ALLOWED=$(ssh "$SSH_HOST" "cd $WP_PATH && wp eval 'if ( function_exists(\"wchs_allowed_origin_list\") ) { echo implode(\",\", wchs_allowed_origin_list()); } elseif ( defined(\"WCHS_ALLOWED_ORIGINS\") ) { echo WCHS_ALLOWED_ORIGINS; } else { echo get_option(\"home\"); }'" 2>/dev/null)
EFFECTIVE_RETURN=$(ssh "$SSH_HOST" "cd $WP_PATH && wp eval 'if ( function_exists(\"wchs_return_origin_list\") ) { echo implode(\",\", wchs_return_origin_list()); } elseif ( defined(\"WCHS_RETURN_ORIGINS\") ) { echo WCHS_RETURN_ORIGINS; } else { echo get_option(\"home\"); }'" 2>/dev/null)
LEGACY_WCHS_SPA_URL=$(ssh "$SSH_HOST" "cd $WP_PATH && wp eval 'echo defined(\"WCHS_SPA_URL\") ? WCHS_SPA_URL : \"\";'" 2>/dev/null)
LEGACY_WCHS_ALLOWED=$(ssh "$SSH_HOST" "cd $WP_PATH && wp eval 'echo defined(\"WCHS_ALLOWED_ORIGINS\") ? WCHS_ALLOWED_ORIGINS : \"\";'" 2>/dev/null)
LEGACY_WCHS_RETURN=$(ssh "$SSH_HOST" "cd $WP_PATH && wp eval 'echo defined(\"WCHS_RETURN_ORIGINS\") ? WCHS_RETURN_ORIGINS : \"\";'" 2>/dev/null)
ROBOTS_TXT=$(ssh "$SSH_HOST" "curl -sk -H 'Host: ${DOMAIN}' 'https://127.0.0.1/robots.txt?bust=$(date +%s)'" 2>/dev/null)
SITEMAP_URL=$(printf '%s' "$ROBOTS_TXT" | sed -n 's/^Sitemap:[[:space:]]*//p' | head -1)
CONFIG_JSON=$(ssh "$SSH_HOST" "curl -sk -H 'Host: ${DOMAIN}' 'https://127.0.0.1/wp-json/wchs/v1/config?bust=$(date +%s)'" 2>/dev/null)
CONFIG_WP_ORIGIN=$(printf '%s' "$CONFIG_JSON" | python3 -c 'import json,sys; data=json.load(sys.stdin); print(data.get("wp_origin",""))')
CONFIG_SPA_ORIGIN=$(printf '%s' "$CONFIG_JSON" | python3 -c 'import json,sys; data=json.load(sys.stdin); print(data.get("spa_origin",""))')
CONFIG_ORIGIN_MODE=$(printf '%s' "$CONFIG_JSON" | python3 -c 'import json,sys; data=json.load(sys.stdin); print(data.get("origin_mode",""))')
SITEMAP_HOST=$(printf '%s' "$SITEMAP_URL" | python3 -c 'import sys,urllib.parse; print((urllib.parse.urlparse(sys.stdin.read().strip()).hostname or ""))')

echo "    siteurl:             ${SITEURL}"
echo "    home:                ${HOME_URL}"
echo "    origin mode:         ${ORIGIN_MODE}"
echo "    effective spa:       ${EFFECTIVE_SPA_ORIGIN}"
echo "    effective allowed:   ${EFFECTIVE_ALLOWED}"
echo "    effective return:    ${EFFECTIVE_RETURN}"
echo "    legacy WCHS_SPA:     ${LEGACY_WCHS_SPA_URL}"
echo "    legacy WCHS_ALLOW:   ${LEGACY_WCHS_ALLOWED}"
echo "    legacy WCHS_RETURN:  ${LEGACY_WCHS_RETURN}"
echo "    config wp_origin:    ${CONFIG_WP_ORIGIN}"
echo "    config spa_origin:   ${CONFIG_SPA_ORIGIN}"
echo "    config mode:         ${CONFIG_ORIGIN_MODE}"
echo "    robots sitemap:      ${SITEMAP_URL}"

[ "$SITEURL" = "$EXPECTED" ] || fail "siteurl is '${SITEURL}', expected '${EXPECTED}'"
[ "$HOME_URL" = "$EXPECTED" ] || fail "home is '${HOME_URL}', expected '${EXPECTED}'"
[ "$CONFIG_WP_ORIGIN" = "$EXPECTED" ] || fail "config endpoint wp_origin is '${CONFIG_WP_ORIGIN}', expected '${EXPECTED}'"
[ "$CONFIG_SPA_ORIGIN" = "$EFFECTIVE_SPA_ORIGIN" ] || fail "config endpoint spa_origin is '${CONFIG_SPA_ORIGIN}', expected '${EFFECTIVE_SPA_ORIGIN}'"
[ -n "$CONFIG_ORIGIN_MODE" ] && [ "$CONFIG_ORIGIN_MODE" = "$ORIGIN_MODE" ] || fail "config endpoint origin_mode is '${CONFIG_ORIGIN_MODE}', expected '${ORIGIN_MODE}'"

if [ "$ORIGIN_MODE" = "same-origin" ]; then
  [ "$EFFECTIVE_SPA_ORIGIN" = "$EXPECTED" ] || fail "effective WCHS SPA origin is '${EFFECTIVE_SPA_ORIGIN}', expected '${EXPECTED}'"
  contains_csv_value "$EXPECTED" "$EFFECTIVE_ALLOWED" || fail "effective allowed origins do not include '${EXPECTED}'"
  contains_csv_value "$EXPECTED" "$EFFECTIVE_RETURN" || fail "effective return origins do not include '${EXPECTED}'"
else
  [ -n "$EFFECTIVE_SPA_ORIGIN" ] || fail "custom mode is enabled but no effective SPA origin was resolved"
  contains_csv_value "$EFFECTIVE_SPA_ORIGIN" "$EFFECTIVE_ALLOWED" || fail "effective allowed origins do not include '${EFFECTIVE_SPA_ORIGIN}'"
  contains_csv_value "$EFFECTIVE_SPA_ORIGIN" "$EFFECTIVE_RETURN" || fail "effective return origins do not include '${EFFECTIVE_SPA_ORIGIN}'"
fi

[ "$SITEMAP_HOST" = "$DOMAIN" ] || fail "robots sitemap host is '${SITEMAP_HOST}', expected '${DOMAIN}'"

echo "  ✓ domain alignment verified"
