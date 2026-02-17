#!/usr/bin/env bash
set -euo pipefail

BASE_DIR="$(cd "$(dirname "$0")/.." && pwd)"
cd "$BASE_DIR"

PHP_BIN="${PHP_BIN:-php}"
API_BASE_URL="${SMOKE_API_BASE_URL:-}"
INSECURE="${SMOKE_INSECURE:-0}"

echo "[smoke] running service-level duel smoke"
"$PHP_BIN" bin/smoke_duel.php

echo "[smoke] running API-level duel smoke"
if [[ -n "$API_BASE_URL" ]]; then
  "$PHP_BIN" bin/smoke_api_duel.php --base-url="$API_BASE_URL" --insecure="$INSECURE"
else
  "$PHP_BIN" bin/smoke_api_duel.php --insecure="$INSECURE"
fi

echo "[smoke] all checks passed"
