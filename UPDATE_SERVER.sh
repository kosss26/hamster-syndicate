#!/usr/bin/env bash

set -euo pipefail

# One-command update pipeline for server.
# Usage:
#   ./UPDATE_SERVER.sh
# Optional env flags:
#   RUN_SEED=1                    # additionally run php bin/seed.php
#   RUN_API_SMOKE=1               # run API smoke in addition to service smoke
#   SMOKE_API_BASE_URL=https://app.tvixx.ru
#   SMOKE_INSECURE=1              # disable TLS verification for API smoke
#   RUN_FRONT_SMOKE=1             # run frontend state smoke before build
#   RESTART_DUEL_WATCHDOG_TIMER=1 # restart watchdog timer when unit is installed
#   PHP_BIN=php                   # php executable override
#   NPM_BIN=npm                   # npm executable override

ROOT_DIR="${ROOT_DIR:-/var/www/quiz-bot}"
PHP_BIN="${PHP_BIN:-php}"
NPM_BIN="${NPM_BIN:-npm}"
RUN_SEED="${RUN_SEED:-0}"
RUN_API_SMOKE="${RUN_API_SMOKE:-0}"
SMOKE_API_BASE_URL="${SMOKE_API_BASE_URL:-}"
SMOKE_INSECURE="${SMOKE_INSECURE:-0}"
RUN_FRONT_SMOKE="${RUN_FRONT_SMOKE:-1}"
RESTART_DUEL_WATCHDOG_TIMER="${RESTART_DUEL_WATCHDOG_TIMER:-1}"

echo "[update] root dir: ${ROOT_DIR}"
cd "${ROOT_DIR}"

echo "[update] cleaning local dist artifacts to avoid pull conflicts"
git restore webapp/dist/index.html 2>/dev/null || true
git clean -fd webapp/dist 2>/dev/null || true

echo "[update] pulling latest changes"
git pull origin main

echo "[update] running backend migrations"
cd "${ROOT_DIR}/bot"
"${PHP_BIN}" bin/migrate.php

if [[ "${RUN_SEED}" == "1" ]]; then
  echo "[update] running seed (RUN_SEED=1)"
  "${PHP_BIN}" bin/seed.php
fi

echo "[update] running service smoke"
"${PHP_BIN}" bin/smoke_duel.php

if [[ "${RUN_API_SMOKE}" == "1" ]]; then
  if [[ -z "${SMOKE_API_BASE_URL}" ]]; then
    if [[ -f config/app.env ]]; then
      WEBAPP_URL_LINE="$(grep -E '^WEBAPP_URL=' config/app.env | tail -n 1 || true)"
      WEBAPP_URL="${WEBAPP_URL_LINE#WEBAPP_URL=}"
      WEBAPP_URL="${WEBAPP_URL%/}"
      if [[ "${WEBAPP_URL}" == */webapp ]]; then
        SMOKE_API_BASE_URL="${WEBAPP_URL%/webapp}"
      fi
    fi
  fi

  if [[ -z "${SMOKE_API_BASE_URL}" ]]; then
    echo "[update] RUN_API_SMOKE=1 but SMOKE_API_BASE_URL is empty, skipping API smoke"
  else
    echo "[update] running API smoke against ${SMOKE_API_BASE_URL}"
    "${PHP_BIN}" bin/smoke_api_duel.php --base-url="${SMOKE_API_BASE_URL}" --insecure="${SMOKE_INSECURE}"
  fi
fi

echo "[update] building webapp"
cd "${ROOT_DIR}/webapp"
"${NPM_BIN}" ci
if [[ "${RUN_FRONT_SMOKE}" == "1" ]]; then
  echo "[update] running frontend state smoke"
  "${NPM_BIN}" run smoke:duel-state
fi
"${NPM_BIN}" run build

echo "[update] restarting services"
systemctl restart php8.2-fpm
systemctl restart nginx
systemctl restart quizbot-websocket

if [[ "${RESTART_DUEL_WATCHDOG_TIMER}" == "1" ]]; then
  if systemctl list-unit-files | grep -q '^quizbot-duel-watchdog.timer'; then
    echo "[update] restarting quizbot-duel-watchdog.timer"
    systemctl restart quizbot-duel-watchdog.timer
    systemctl status quizbot-duel-watchdog.timer --no-pager | sed -n '1,12p'
  else
    echo "[update] watchdog timer not installed (skip). Use ./INSTALL_DUEL_WATCHDOG_TIMER.sh"
  fi
fi

echo "[update] websocket status"
systemctl status quizbot-websocket --no-pager | sed -n '1,12p'

echo "[update] done"
