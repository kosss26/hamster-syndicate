#!/bin/bash
# Проверка статуса бота после обновления

set -e

echo "🔍 Проверка статуса бота..."

cd /var/www/quiz-bot/bot

# 1. Проверяем последние ошибки Nginx
echo "📋 Последние ошибки Nginx (последние 20 строк):"
tail -20 /var/log/nginx/quiz-bot-error.log 2>/dev/null | grep -i "error\|fatal\|warning" || echo "Нет критических ошибок в логах Nginx"

# 2. Проверяем логи приложения
echo ""
echo "📋 Логи приложения (последние 30 строк):"
if [ -f "storage/logs/app.log" ]; then
    tail -30 storage/logs/app.log || echo "Лог пуст"
else
    echo "Файл логов не найден"
fi

# 3. Проверяем PHP ошибки напрямую
echo ""
echo "🔍 Тест загрузки AdminService..."
php -r "
error_reporting(E_ALL);
ini_set('display_errors', '1');
require 'vendor/autoload.php';
try {
    \$bootstrap = new QuizBot\Bootstrap\AppBootstrap(__DIR__);
    echo '✅ Bootstrap создан успешно' . PHP_EOL;
    
    // Проверяем AdminService
    if (class_exists('QuizBot\Application\Services\AdminService')) {
        echo '✅ Класс AdminService найден' . PHP_EOL;
    } else {
        echo '❌ Класс AdminService не найден!' . PHP_EOL;
    }
    
    // Пытаемся получить AdminService из контейнера
    try {
        \$adminService = \$bootstrap->getContainer()->get('QuizBot\Application\Services\AdminService');
        echo '✅ AdminService загружен из контейнера' . PHP_EOL;
    } catch (Exception \$e) {
        echo '❌ Ошибка загрузки AdminService: ' . \$e->getMessage() . PHP_EOL;
    }
} catch (Exception \$e) {
    echo '❌ Ошибка Bootstrap: ' . \$e->getMessage() . PHP_EOL;
    echo 'Trace: ' . \$e->getTraceAsString() . PHP_EOL;
    exit(1);
}
" 2>&1

# 4. Обновляем autoload
echo ""
echo "🔄 Обновление autoload..."
composer dump-autoload --optimize 2>&1 | tail -5

# 5. Проверяем статус webhook
echo ""
echo "📡 Статус webhook в Telegram:"
BOT_TOKEN="8416923485:AAHcLsagrmSfopY453D9YfbDZ3ihRJ2zE5w"
WEBHOOK_INFO=$(curl -s "https://api.telegram.org/bot${BOT_TOKEN}/getWebhookInfo")
echo "$WEBHOOK_INFO" | python3 -m json.tool 2>/dev/null || echo "$WEBHOOK_INFO"

# 6. Проверяем последнюю ошибку webhook
echo ""
echo "📋 Последняя ошибка webhook:"
if echo "$WEBHOOK_INFO" | grep -q '"last_error_message"'; then
    echo "$WEBHOOK_INFO" | python3 -c "import sys, json; data = json.load(sys.stdin); err = data.get('result', {}).get('last_error_message', 'Нет ошибок'); print('Ошибка:', err)" 2>/dev/null || echo "Ошибка парсинга"
else
    echo "✅ Нет ошибок в webhook!"
fi

# 7. Проверяем конфигурацию
echo ""
echo "⚙️ Проверка конфигурации:"
if [ -f "config/app.env" ]; then
    if grep -q "ADMIN_TELEGRAM_IDS" config/app.env; then
        echo "✅ ADMIN_TELEGRAM_IDS найден:"
        grep "ADMIN_TELEGRAM_IDS" config/app.env
    else
        echo "⚠️ ADMIN_TELEGRAM_IDS не найден в конфигурации"
    fi
else
    echo "❌ Конфигурация не найдена!"
fi

# 8. Тест webhook локально
echo ""
echo "🔍 Тест webhook локально..."
HTTP_CODE=$(curl -k -s -o /dev/null -w "%{http_code}" -X POST https://localhost/webhook -H "Content-Type: application/json" -H "X-Telegram-Bot-Api-Secret-Token: QuizBotSecret123" -d '{"message":{"chat":{"id":123},"from":{"id":123},"text":"test"}}' 2>&1 || echo "000")
echo "Код ответа: $HTTP_CODE"

if [ "$HTTP_CODE" = "200" ]; then
    echo "✅ Webhook отвечает нормально"
elif [ "$HTTP_CODE" = "500" ]; then
    echo "❌ Ошибка 500 - проверьте логи выше"
else
    echo "⚠️ Webhook вернул код: $HTTP_CODE"
fi

echo ""
echo "✅ Диагностика завершена!"

