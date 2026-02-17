#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="${ROOT_DIR:-/var/www/quiz-bot}"
PHP_BIN="${PHP_BIN:-/usr/bin/php}"
DUEL_LIMIT="${DUEL_LIMIT:-500}"
INTERVAL_SECONDS="${INTERVAL_SECONDS:-20}"
SERVICE_USER="${SERVICE_USER:-www-data}"
SERVICE_GROUP="${SERVICE_GROUP:-www-data}"

SERVICE_FILE="/etc/systemd/system/quizbot-duel-watchdog.service"
TIMER_FILE="/etc/systemd/system/quizbot-duel-watchdog.timer"

echo "[watchdog] installing systemd units"

cat <<EOF | sudo tee "${SERVICE_FILE}" >/dev/null
[Unit]
Description=QuizBot Duel Round Watchdog
After=network.target

[Service]
Type=oneshot
User=${SERVICE_USER}
Group=${SERVICE_GROUP}
WorkingDirectory=${ROOT_DIR}/bot
ExecStart=${PHP_BIN} ${ROOT_DIR}/bot/bin/duel_round_watchdog.php ${DUEL_LIMIT}
Nice=10

[Install]
WantedBy=multi-user.target
EOF

cat <<EOF | sudo tee "${TIMER_FILE}" >/dev/null
[Unit]
Description=Run QuizBot Duel Watchdog every ${INTERVAL_SECONDS} seconds

[Timer]
OnBootSec=30s
OnUnitActiveSec=${INTERVAL_SECONDS}s
AccuracySec=2s
Persistent=true
Unit=quizbot-duel-watchdog.service

[Install]
WantedBy=timers.target
EOF

sudo systemctl daemon-reload
sudo systemctl enable --now quizbot-duel-watchdog.timer

echo "[watchdog] timer status"
sudo systemctl status quizbot-duel-watchdog.timer --no-pager | sed -n '1,14p'
echo "[watchdog] recent service logs"
sudo journalctl -u quizbot-duel-watchdog.service -n 20 --no-pager

echo "[watchdog] installed"
