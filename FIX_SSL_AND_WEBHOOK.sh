#!/bin/bash
# Исправление SSL и webhook endpoint

set -e

echo "🔧 Исправление SSL и webhook..."

cd /var/www/quiz-bot/bot

# 1. Проверяем, что webhook endpoint доступен локально
echo "🔍 Проверка webhook endpoint локально..."
curl -v http://localhost/webhook 2>&1 | head -20

# 2. Проверяем конфигурацию Nginx
echo "🌐 Проверка конфигурации Nginx..."
cat /etc/nginx/sites-available/quiz-bot

# 3. Устанавливаем SSL сертификат
echo "🔒 Установка SSL сертификата..."
if ! command -v certbot &> /dev/null; then
    apt install -y certbot python3-certbot-nginx
fi

# Устанавливаем сертификат
certbot --nginx -d app.tvix.ru --non-interactive --agree-tos --email admin@tvix.ru || echo "⚠️ Не удалось установить SSL автоматически"

# 4. Обновляем webhook после установки SSL
echo "📡 Обновление webhook..."
BOT_TOKEN="8416923485:AAHcLsagrmSfopY453D9YfbDZ3ihRJ2zE5w"
curl -X POST "https://api.telegram.org/bot${BOT_TOKEN}/setWebhook" \
  -H "Content-Type: application/json" \
  -d '{"url": "https://app.tvix.ru/webhook", "secret_token": "QuizBotSecret123"}'

# 5. Проверяем статус webhook
echo "📊 Финальный статус webhook:"
curl -s "https://api.telegram.org/bot${BOT_TOKEN}/getWebhookInfo" | python3 -m json.tool

echo ""
echo "✅ Готово!"

