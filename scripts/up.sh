#!/usr/bin/env bash
# Bring up the WP + MySQL + Redis containers and wait for readiness.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$ROOT"

if ! docker compose version >/dev/null 2>&1; then
  echo "error: 'docker compose' (v2) not available" >&2
  exit 1
fi

docker compose up -d

echo -n "waiting for WP on http://localhost:8099 "
for i in {1..60}; do
  if curl -sf -o /dev/null "http://localhost:8099/wp-login.php"; then
    echo " ready"
    exit 0
  fi
  echo -n "."
  sleep 1
done
echo " timeout"
exit 1
