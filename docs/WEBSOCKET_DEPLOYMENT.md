# WebSocket deployment (duels)

Этот документ описывает продакшн-развёртывание realtime-синхронизации дуэлей.

## 1. Переменные окружения

В `bot/config/app.env`:

```env
WEBSOCKET_HOST=127.0.0.1
WEBSOCKET_PORT=8090
WEBSOCKET_SYNC_INTERVAL=1
WEBSOCKET_TICKET_SECRET=<long-random-secret>
WEBSOCKET_CLIENT_TIMEOUT_SECONDS=70
WEBSOCKET_MAX_MESSAGE_BYTES=2048
WEBAPP_URL=https://your-domain.com
INIT_DATA_TTL_SECONDS=300
WEBSOCKET_TICKET_TTL_SECONDS=300
```

В `webapp/.env.local` (или в CI env):

```env
VITE_API_URL=/api
VITE_WS_URL=wss://your-domain.com/ws
```

## 2. Nginx proxy для WebSocket

```nginx
location /ws/ {
    proxy_pass http://127.0.0.1:8090/;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_set_header Host $host;
    proxy_read_timeout 65s;
}
```

## 3. Systemd unit

Создайте `/etc/systemd/system/quizbot-websocket.service`:

```ini
[Unit]
Description=QuizBot WebSocket Server
After=network.target

[Service]
Type=simple
WorkingDirectory=/opt/hamster-syndicate/bot
ExecStart=/usr/bin/php /opt/hamster-syndicate/bot/bin/websocket_server.php
Restart=always
RestartSec=2
User=www-data
Group=www-data
Environment=APP_ENV=production

[Install]
WantedBy=multi-user.target
```

Затем:

```bash
sudo systemctl daemon-reload
sudo systemctl enable quizbot-websocket
sudo systemctl restart quizbot-websocket
sudo systemctl status quizbot-websocket
```

## 4. Проверки после релиза

1. Открывается `wss://your-domain.com/ws` из WebApp.
2. API `GET /api/duel/ws-ticket?duel_id=<id>` возвращает `ticket` для участников дуэли.
3. При ответе одного игрока второй получает `duel_update` без задержки polling-интервала.
4. Процесс `quizbot-websocket` автоматически рестартует после сбоя.

## 5. Рекомендации

- Используйте отдельный `WEBSOCKET_TICKET_SECRET`, не равный `TELEGRAM_BOT_TOKEN`.
- Храните Nginx и systemd логи в централизованном логировании.
- Держите `WEBSOCKET_SYNC_INTERVAL=1` как безопасный baseline; снижать ниже стоит только после профилирования.
- `WEBSOCKET_CLIENT_TIMEOUT_SECONDS` держите немного выше клиентского heartbeat-интервала (по умолчанию ping каждые 20с, timeout 70с).
- `WEBSOCKET_MAX_MESSAGE_BYTES` ограничивает размер входящего клиентского payload (по умолчанию 2048 байт).


## 6. Диагностика API

Все JSON-ответы API теперь содержат `request_id` для трассировки ошибок в логах.
