#!/usr/bin/env bash
# Nuke the env back to zero. Asks for confirmation because this deletes volumes.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$ROOT"

read -rp "this will delete the MySQL + WP volumes for wc-headless-starter. continue? [y/N] " ans
if [[ "$ans" != "y" && "$ans" != "Y" ]]; then
  echo "aborted"
  exit 0
fi

docker compose down -v
echo "clean. run ./scripts/up.sh then ./scripts/seed.sh to rebuild."
