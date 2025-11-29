#!/bin/bash
# Исправление конфликта конфигураций Nginx

set -e

echo "🔧 Исправление конфликта конфигураций Nginx..."

# 1. Проверяем все конфигурации с app.tvixx.ru
echo "🔍 Поиск конфликтующих конфигураций..."
grep -r "app.tvixx.ru" /etc/nginx/sites-available/ /etc/nginx/sites-enabled/ 2>/dev/null || true

# 2. Удаляем дублирующие конфигурации
echo "🗑️ Удаление дублирующих конфигураций..."
rm -f /etc/nginx/sites-enabled/hamster 2>/dev/null || true
rm -f /etc/nginx/sites-enabled/default 2>/dev/null || true

# 3. Оставляем только quiz-bot конфигурацию
echo "✅ Активируем только quiz-bot конфигурацию..."
ln -sf /etc/nginx/sites-available/quiz-bot /etc/nginx/sites-enabled/quiz-bot

# 4. Проверяем конфигурацию
echo "✅ Проверка конфигурации..."
nginx -t

# 5. Перезагружаем Nginx
echo "🔄 Перезагрузка Nginx..."
systemctl reload nginx

# 6. Проверяем статус webhook
echo "📊 Проверка статуса webhook..."
BOT_TOKEN="8416923485:AAHcLsagrmSfopY453D9YfbDZ3ihRJ2zE5w"
curl -s "https://api.telegram.org/bot${BOT_TOKEN}/getWebhookInfo" | python3 -m json.tool || echo "Ошибка проверки"

# 7. Проверяем доступность webhook endpoint
echo "🔍 Проверка webhook endpoint..."
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" https://app.tvixx.ru/webhook || echo "000")
echo "Код ответа webhook: $HTTP_CODE"

echo ""
echo "✅ Готово!"

