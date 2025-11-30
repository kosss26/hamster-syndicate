#!/bin/bash
# Быстрое исправление проблемы с админ-панелью

set -e

echo "🔧 Быстрое исправление..."

cd /var/www/quiz-bot/bot

# 1. Обновляем код
echo "📥 Обновление кода..."
cd /var/www/quiz-bot
git pull origin main

# 2. Обновляем autoload
echo "🔄 Обновление autoload..."
cd bot
composer dump-autoload --optimize

# 3. Проверяем последние ошибки
echo "📋 Последние ошибки (последние 10 строк):"
tail -10 /var/log/nginx/quiz-bot-error.log 2>/dev/null | grep -i "error\|fatal" || echo "Нет критических ошибок"

# 4. Проверяем логи приложения
echo "📋 Логи приложения (последние 5 строк):"
tail -5 storage/logs/app.log 2>/dev/null || echo "Лог пуст"

# 5. Тест PHP
echo "🔍 Тест загрузки классов..."
php -r "
require 'vendor/autoload.php';
try {
    \$bootstrap = new QuizBot\Bootstrap\AppBootstrap(__DIR__);
    echo '✅ Bootstrap OK' . PHP_EOL;
    
    \$adminService = \$bootstrap->getContainer()->get('QuizBot\Application\Services\AdminService');
    echo '✅ AdminService OK' . PHP_EOL;
} catch (Exception \$e) {
    echo '❌ Ошибка: ' . \$e->getMessage() . PHP_EOL;
    exit(1);
}
" 2>&1

# 6. Проверяем webhook
echo ""
echo "📡 Статус webhook:"
BOT_TOKEN="8416923485:AAHcLsagrmSfopY453D9YfbDZ3ihRJ2zE5w"
curl -s "https://api.telegram.org/bot${BOT_TOKEN}/getWebhookInfo" | python3 -c "import sys, json; data = json.load(sys.stdin); print('URL:', data.get('result', {}).get('url')); print('Ошибка:', data.get('result', {}).get('last_error_message', 'Нет'))" 2>/dev/null || echo "Ошибка проверки"

echo ""
echo "✅ Готово! Попробуйте отправить /admin боту."

