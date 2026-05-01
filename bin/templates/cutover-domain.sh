#!/usr/bin/env bash
# cutover-domain.sh — swap a WCHS-deployed site from one domain to another.
# Typically: <preview-domain> → your-real-brand.com
#
# What it does (in order, idempotent where possible):
#   1. Pre-flight: SSH + wp-cli + detect whether any primary runtime setting
#      still references <old> (supports repairing partial cutovers)
#   2. Backup: wp db export → <canonical>/cutover-<oldDomain>-to-<newDomain>-<ts>.sql.gz
#   3. wp search-replace old → new, skipping GUIDs
#   4. Explicit wp option update siteurl + home
#   5. Repair legacy/custom WCHS origin overrides in wp-config.php if present
#   6. robots.txt Sitemap URL update
#   7. Flush WP cache + transients + SG Dynamic Cache purge
#   8. Verification: curl new domain endpoints
#   9. Print the HUMAN checklist (DNS, SSL, payment webhooks, Google Search Console)
#
# Usage:
#   ./cutover-domain.sh <old-domain> <new-domain> [--dry-run] [--canonical=<local-path>]
#
# Example:
#   ./cutover-domain.sh <preview-domain> new-domain.example --dry-run
#   ./cutover-domain.sh <preview-domain> new-domain.example
#
# Prereqs:
#   - SSH alias to the target site exists in ~/.ssh/config (set SSH_HOST env var OR read from .env)
#   - .env present OR all vars exported: SSH_HOST, WP_PATH
#   - DNS for <new-domain> already points to the SG host (via A-record)
#   - SSL cert already issued on <new-domain> via SG Let's Encrypt

set -euo pipefail

OLD_DOMAIN="${1:-}"
NEW_DOMAIN="${2:-}"
DRY_RUN=0
CANONICAL_DIR="${HOME}/wchs-cutover-backups"

for arg in "$@"; do
  case "$arg" in
    --dry-run)       DRY_RUN=1 ;;
    --canonical=*)   CANONICAL_DIR="${arg#--canonical=}" ;;
  esac
done

if [ -z "$OLD_DOMAIN" ] || [ -z "$NEW_DOMAIN" ]; then
  echo "usage: $0 <old-domain> <new-domain> [--dry-run] [--canonical=<path>]" >&2
  exit 1
fi

# ─── Resolve SSH_HOST + WP_PATH ──────────────────────────────────
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
if [ "$(basename "$SCRIPT_DIR")" = "templates" ] && [ -f "$ROOT/snapshot-template.sh" ]; then
  echo "✗ This is the template source script inside wc-headless-starter." >&2
  echo "  Generate a site folder first: bin/snapshot-template.sh ~/dev/sites/<site>" >&2
  echo "  Then run ./scripts/$(basename "$0") from that generated folder." >&2
  exit 1
fi
if [ -f "$ROOT/.env" ]; then
  # shellcheck disable=SC1091
  source "$ROOT/.env"
fi
: "${SSH_HOST:?SSH_HOST not set (in .env or env)}"
: "${WP_PATH:?WP_PATH not set (in .env or env)}"

TS=$(date +%Y%m%d-%H%M%S)
BACKUP="${CANONICAL_DIR}/cutover-${OLD_DOMAIN}-to-${NEW_DOMAIN}-${TS}.sql.gz"
mkdir -p "$CANONICAL_DIR"

echo "╭─────────────────────────────────────────────────────────────"
echo "│ Cutover plan"
echo "├─────────────────────────────────────────────────────────────"
echo "│ OLD DOMAIN : ${OLD_DOMAIN}"
echo "│ NEW DOMAIN : ${NEW_DOMAIN}"
echo "│ SSH host   : ${SSH_HOST}"
echo "│ WP path    : ${WP_PATH}"
echo "│ Backup to  : ${BACKUP}"
echo "│ Mode       : $([ $DRY_RUN = 1 ] && echo "DRY RUN (no mutations)" || echo "LIVE")"
echo "╰─────────────────────────────────────────────────────────────"
echo

# ─── 1. Pre-flight ────────────────────────────────────────────────
echo "» 1/9: pre-flight"
CURRENT_SITEURL=$(ssh "$SSH_HOST" "cd $WP_PATH && wp option get siteurl" 2>/dev/null)
CURRENT_HOME=$(ssh "$SSH_HOST" "cd $WP_PATH && wp option get home" 2>/dev/null)
CURRENT_ORIGIN_MODE=$(ssh "$SSH_HOST" "cd $WP_PATH && wp eval 'if ( function_exists(\"wchs_origin_mode\") ) { echo wchs_origin_mode(); } elseif ( defined(\"WCHS_SPA_URL\") || defined(\"WCHS_ALLOWED_ORIGINS\") || defined(\"WCHS_RETURN_ORIGINS\") ) { echo \"custom\"; } else { echo \"same-origin\"; }'" 2>/dev/null)
CURRENT_WCHS_SPA=$(ssh "$SSH_HOST" "cd $WP_PATH && wp eval 'if ( function_exists(\"wchs_spa_origin\") ) { echo wchs_spa_origin(); } elseif ( defined(\"WCHS_SPA_URL\") ) { echo rtrim(WCHS_SPA_URL, \"/\"); } else { echo rtrim(get_option(\"home\"), \"/\"); }'" 2>/dev/null)
CURRENT_WCHS_ALLOWED=$(ssh "$SSH_HOST" "cd $WP_PATH && wp eval 'if ( function_exists(\"wchs_allowed_origin_list\") ) { echo implode(\",\", wchs_allowed_origin_list()); } elseif ( defined(\"WCHS_ALLOWED_ORIGINS\") ) { echo WCHS_ALLOWED_ORIGINS; } else { echo get_option(\"home\"); }'" 2>/dev/null)
CURRENT_WCHS_RETURN=$(ssh "$SSH_HOST" "cd $WP_PATH && wp eval 'if ( function_exists(\"wchs_return_origin_list\") ) { echo implode(\",\", wchs_return_origin_list()); } elseif ( defined(\"WCHS_RETURN_ORIGINS\") ) { echo WCHS_RETURN_ORIGINS; } else { echo get_option(\"home\"); }'" 2>/dev/null)
LEGACY_WCHS_SPA=$(ssh "$SSH_HOST" "cd $WP_PATH && wp eval 'echo defined(\"WCHS_SPA_URL\") ? WCHS_SPA_URL : \"\";'" 2>/dev/null)
LEGACY_WCHS_ALLOWED=$(ssh "$SSH_HOST" "cd $WP_PATH && wp eval 'echo defined(\"WCHS_ALLOWED_ORIGINS\") ? WCHS_ALLOWED_ORIGINS : \"\";'" 2>/dev/null)
LEGACY_WCHS_RETURN=$(ssh "$SSH_HOST" "cd $WP_PATH && wp eval 'echo defined(\"WCHS_RETURN_ORIGINS\") ? WCHS_RETURN_ORIGINS : \"\";'" 2>/dev/null)
CURRENT_SITEMAP=$(ssh "$SSH_HOST" "cd $WP_PATH && if [ -f robots.txt ]; then grep -E '^Sitemap:' robots.txt | head -1 | sed 's/^Sitemap:[[:space:]]*//'; fi" 2>/dev/null || true)

echo "  current siteurl:            ${CURRENT_SITEURL}"
echo "  current home:               ${CURRENT_HOME}"
echo "  current origin mode:        ${CURRENT_ORIGIN_MODE}"
echo "  effective WCHS_SPA_URL:     ${CURRENT_WCHS_SPA}"
echo "  effective WCHS_ALLOWED:     ${CURRENT_WCHS_ALLOWED}"
echo "  effective WCHS_RETURN:      ${CURRENT_WCHS_RETURN}"
echo "  legacy WCHS_SPA_URL:        ${LEGACY_WCHS_SPA}"
echo "  legacy WCHS_ALLOWED:        ${LEGACY_WCHS_ALLOWED}"
echo "  legacy WCHS_RETURN:         ${LEGACY_WCHS_RETURN}"
echo "  current robots sitemap:     ${CURRENT_SITEMAP:-<none>}"

PRIMARY_RUNTIME_STATE=$(
  printf '%s\n%s\n%s\n%s\n%s\n%s\n' \
    "$CURRENT_SITEURL" \
    "$CURRENT_HOME" \
    "$CURRENT_WCHS_SPA" \
    "$CURRENT_WCHS_ALLOWED" \
    "$CURRENT_WCHS_RETURN" \
    "$CURRENT_SITEMAP"
)
LEGACY_RUNTIME_STATE=$(
  printf '%s\n%s\n%s\n' \
    "$LEGACY_WCHS_SPA" \
    "$LEGACY_WCHS_ALLOWED" \
    "$LEGACY_WCHS_RETURN"
)

if ! echo "$PRIMARY_RUNTIME_STATE" | grep -q "$OLD_DOMAIN"; then
  if echo "$PRIMARY_RUNTIME_STATE" | grep -q "$NEW_DOMAIN"; then
    if echo "$LEGACY_RUNTIME_STATE" | grep -q "$OLD_DOMAIN"; then
      echo "  ⚠ live runtime is already on ${NEW_DOMAIN}; only legacy WCHS overrides still reference ${OLD_DOMAIN}. Proceeding so the cleanup can repair them."
    else
      echo "  ✓ no primary runtime setting still references ${OLD_DOMAIN}; cutover is already complete"
      exit 0
    fi
  else
    echo "  ✗ neither ${OLD_DOMAIN} nor ${NEW_DOMAIN} appears in the live runtime settings. Refusing to guess." >&2
    exit 1
  fi
fi

if ! printf '%s\n%s\n' "$CURRENT_SITEURL" "$CURRENT_HOME" | grep -q "$OLD_DOMAIN"; then
  echo "  ⚠ partial cutover detected: siteurl/home already moved, but WCHS runtime or sitemap still reference ${OLD_DOMAIN}"
else
  echo "  ✓ old domain still present in runtime settings — proceeding with cutover"
fi

if echo "$LEGACY_RUNTIME_STATE" | grep -q "$OLD_DOMAIN"; then
  echo "  · legacy wp-config overrides still reference ${OLD_DOMAIN}; step 5 will repair them if they exist"
fi

# ─── 2. Backup ────────────────────────────────────────────────────
if [ $DRY_RUN = 0 ]; then
  echo "» 2/9: backup DB → ${BACKUP}"
  ssh "$SSH_HOST" "cd $WP_PATH && wp db export - | gzip" > "$BACKUP"
  echo "  ✓ backup saved ($(du -h "$BACKUP" | cut -f1))"
else
  echo "» 2/9: [dry-run] skip backup"
fi

# ─── 3. search-replace ───────────────────────────────────────────
echo "» 3/9: wp search-replace"
for from_to in \
    "https://${OLD_DOMAIN}=https://${NEW_DOMAIN}" \
    "http://${OLD_DOMAIN}=http://${NEW_DOMAIN}" \
    "${OLD_DOMAIN}=${NEW_DOMAIN}"; do
  OLD="${from_to%%=*}"
  NEW="${from_to##*=}"
  echo "  · '$OLD' → '$NEW'"
  FLAGS="--skip-columns=guid --precise --recurse-objects --all-tables"
  [ $DRY_RUN = 1 ] && FLAGS="$FLAGS --dry-run"
  ssh "$SSH_HOST" "cd $WP_PATH && wp search-replace '$OLD' '$NEW' $FLAGS 2>&1 | tail -3"
done

# ─── 4. Explicit siteurl + home ──────────────────────────────────
echo "» 4/9: explicit wp option update siteurl + home"
if [ $DRY_RUN = 0 ]; then
  ssh "$SSH_HOST" "cd $WP_PATH && wp option update siteurl 'https://${NEW_DOMAIN}' && wp option update home 'https://${NEW_DOMAIN}'"
else
  echo "  [dry-run] would update siteurl + home to https://${NEW_DOMAIN}"
fi

# ─── 5. legacy wp-config origin override swap ────────────────────
echo "» 5/9: legacy wp-config origin overrides (only if WCHS_* constants exist)"
if [ $DRY_RUN = 0 ]; then
  ssh "$SSH_HOST" bash -s "$OLD_DOMAIN" "$NEW_DOMAIN" "$WP_PATH" <<'REMOTE'
set -e
OLD="$1"; NEW="$2"; WP="$3"
cd "$WP"
cp wp-config.php wp-config.php.bak-$(date +%s)
if grep -q "WCHS_SPA_URL\|WCHS_ALLOWED_ORIGINS\|WCHS_RETURN_ORIGINS" wp-config.php; then
  # Replace the exact domain strings in the WCHS constant lines only.
  sed -i -E "s|'https://${OLD}'|'https://${NEW}'|g" wp-config.php
  php -l wp-config.php | head -1
  grep "WCHS_" wp-config.php
else
  echo "  no legacy WCHS origin constants present; skipping"
fi
REMOTE
else
  echo "  [dry-run] would sed-swap legacy WCHS_* origin constants if present"
fi

# ─── 6. robots.txt ────────────────────────────────────────────────
echo "» 6/9: robots.txt Sitemap URL"
if [ $DRY_RUN = 0 ]; then
  ssh "$SSH_HOST" "cd $WP_PATH && sed -i 's|Sitemap: https://${OLD_DOMAIN}|Sitemap: https://${NEW_DOMAIN}|g' robots.txt && grep Sitemap robots.txt"
else
  echo "  [dry-run] would update Sitemap URL in robots.txt"
fi

# ─── 7. Cache flush + SG purge ───────────────────────────────────
echo "» 7/9: cache flush + SG purge"
if [ $DRY_RUN = 0 ]; then
  ssh "$SSH_HOST" "cd $WP_PATH && wp cache flush 2>&1 | tail -1 && wp transient delete --all 2>&1 | tail -1 && wp plugin install sg-cachepress --activate --quiet 2>/dev/null && wp sg purge 2>&1 | tail -1 && wp plugin deactivate sg-cachepress --quiet 2>/dev/null"
else
  echo "  [dry-run] would flush caches"
fi

# ─── 8. Verification ─────────────────────────────────────────────
echo "» 8/9: post-cutover verification"
if [ $DRY_RUN = 0 ]; then
  echo "  (SG captcha may intercept direct curls; use a primed browser for visual check)"
  HTTP=$(curl -sk -o /dev/null -w "%{http_code}" "https://${NEW_DOMAIN}/?_=$(date +%s)")
  echo "  GET https://${NEW_DOMAIN}/ → ${HTTP}"
  if echo "$HTTP" | grep -qE "^20"; then
    echo "  ✓ new domain responding"
  else
    echo "  ⚠ new domain returned ${HTTP} — DNS not ready? SSL not issued?"
  fi

  VERIFY_SCRIPT="$(cd "$(dirname "$0")" && pwd)/verify-domain-alignment.sh"
  if [ -f "$VERIFY_SCRIPT" ]; then
    DOMAIN="$NEW_DOMAIN" SSH_HOST="$SSH_HOST" WP_PATH="$WP_PATH" bash "$VERIFY_SCRIPT"
  else
    echo "  ⚠ verify-domain-alignment.sh not found; skipping deep runtime verification"
  fi
else
  echo "  [dry-run] would curl https://${NEW_DOMAIN}/"
fi

# ─── 9. Human checklist ──────────────────────────────────────────
echo
echo "╭─────────────────────────────────────────────────────────────"
echo "│ Human actions still required"
echo "├─────────────────────────────────────────────────────────────"
echo "│ 1. DNS A-record for ${NEW_DOMAIN} → SG host IP"
echo "│ 2. SSL cert: SG Site Tools → Security → SSL → install for ${NEW_DOMAIN}"
echo "│ 3. Payment webhooks: update any gateway dashboard URLs from ${OLD_DOMAIN} to ${NEW_DOMAIN}"
echo "│ 4. Google Search Console: re-verify ownership on ${NEW_DOMAIN}, submit new sitemap"
echo "│    https://${NEW_DOMAIN}/wp-sitemap.xml"
echo "│ 5. Omnisend / Klaviyo / Meta pixel: update store URL if those plugins cached it"
echo "│ 6. wp-admin → WCHS → Cutover → confirm runtime alignment + copy targets"
echo "│ 7. Test the golden path: homepage → PDP → add to cart → checkout"
echo "│ 8. Run the golden path in a browser: homepage → PDP → cart → checkout"
echo "╰─────────────────────────────────────────────────────────────"

if [ $DRY_RUN = 1 ]; then
  echo
  echo "✓ dry-run complete. Re-run without --dry-run to apply."
else
  echo
  echo "✓ cutover complete. Backup at: $BACKUP"
fi
