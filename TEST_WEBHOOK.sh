#!/bin/bash
# Тест webhook с правильным методом POST

set -e

echo "🔍 Тестирование webhook..."

cd /var/www/quiz-bot/bot

# 1. Тест webhook с POST запросом
echo "📡 Тест webhook с POST..."
HTTP_CODE=$(curl -k -s -o /dev/null -w "%{http_code}" -X POST https://localhost/webhook -H "Content-Type: application/json" -d '{"test": true}' || echo "000")
echo "Код ответа POST: $HTTP_CODE"

# 2. Проверка статуса webhook в Telegram
echo "📊 Статус webhook в Telegram:"
BOT_TOKEN="8416923485:AAHcLsagrmSfopY453D9YfbDZ3ihRJ2zE5w"
curl -s "https://api.telegram.org/bot${BOT_TOKEN}/getWebhookInfo" | python3 -m json.tool || echo "Ошибка проверки"

# 3. Проверка последних ошибок
echo "📋 Последние ошибки (если есть):"
WEBHOOK_INFO=$(curl -s "https://api.telegram.org/bot${BOT_TOKEN}/getWebhookInfo")
if echo "$WEBHOOK_INFO" | grep -q '"last_error_message"'; then
    echo "$WEBHOOK_INFO" | python3 -c "import sys, json; data = json.load(sys.stdin); print('Последняя ошибка:', data.get('result', {}).get('last_error_message', 'Нет ошибок'))" 2>/dev/null || echo "Ошибка парсинга"
else
    echo "✅ Нет ошибок в webhook!"
fi

# 4. Проверка pending updates
PENDING=$(echo "$WEBHOOK_INFO" | python3 -c "import sys, json; data = json.load(sys.stdin); print(data.get('result', {}).get('pending_update_count', 0))" 2>/dev/null || echo "0")
echo "Ожидающих обновлений: $PENDING"

if [ "$PENDING" -gt 0 ]; then
    echo "⚠️ Есть ожидающие обновления. Webhook работает, но есть накопленные сообщения."
else
    echo "✅ Нет ожидающих обновлений"
fi

echo ""
echo "✅ Тест завершён!"

