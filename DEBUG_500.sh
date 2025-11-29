#!/bin/bash
# Детальная диагностика ошибки 500

set -e

echo "🔍 Детальная диагностика ошибки 500..."

cd /var/www/quiz-bot/bot

# 1. Проверяем последние ошибки Nginx
echo "📋 Последние ошибки Nginx (последние 50 строк):"
tail -50 /var/log/nginx/quiz-bot-error.log | grep -A 10 "error" || echo "Нет ошибок в логах Nginx"

# 2. Проверяем логи приложения
echo "📋 Логи приложения:"
if [ -f "storage/logs/app.log" ]; then
    tail -50 storage/logs/app.log || echo "Лог пуст"
else
    echo "Файл логов не найден"
fi

# 3. Проверяем PHP ошибки напрямую
echo "🔍 Тест PHP напрямую..."
php -r "
error_reporting(E_ALL);
ini_set('display_errors', '1');
require 'vendor/autoload.php';
try {
    \$bootstrap = new QuizBot\Bootstrap\AppBootstrap(__DIR__);
    echo '✅ Bootstrap создан успешно' . PHP_EOL;
} catch (Exception \$e) {
    echo '❌ Ошибка Bootstrap: ' . \$e->getMessage() . PHP_EOL;
    echo 'Trace: ' . \$e->getTraceAsString() . PHP_EOL;
    exit(1);
}
" 2>&1

# 4. Проверяем конфигурацию
echo "🔍 Проверка конфигурации..."
if [ -f "config/app.env" ]; then
    echo "Конфигурация существует"
    cat config/app.env | grep -E "TELEGRAM_BOT_TOKEN|DB_" | head -5
else
    echo "❌ Конфигурация не найдена!"
fi

# 5. Проверяем базу данных
echo "🔍 Проверка базы данных..."
if [ -f "storage/database/database.sqlite" ]; then
    DB_SIZE=$(stat -c%s storage/database/database.sqlite 2>/dev/null || echo "0")
    echo "База данных существует, размер: $DB_SIZE байт"
    
    # Проверяем доступность БД
    php -r "
    require 'vendor/autoload.php';
    try {
        \$capsule = new Illuminate\Database\Capsule\Manager();
        \$capsule->addConnection([
            'driver' => 'sqlite',
            'database' => __DIR__ . '/storage/database/database.sqlite',
        ]);
        \$capsule->setAsGlobal();
        \$capsule->bootEloquent();
        \$result = \$capsule->connection()->select('SELECT 1 as test');
        echo '✅ База данных доступна' . PHP_EOL;
    } catch (Exception \$e) {
        echo '❌ Ошибка БД: ' . \$e->getMessage() . PHP_EOL;
    }
    " 2>&1
else
    echo "❌ База данных не найдена!"
fi

# 6. Проверяем права доступа
echo "🔍 Проверка прав доступа..."
ls -la storage/
ls -la storage/database/
ls -la storage/logs/

# 7. Проверяем webhook с подробным выводом
echo "🔍 Детальный тест webhook..."
curl -v -k -X POST https://localhost/webhook \
  -H "Content-Type: application/json" \
  -H "X-Telegram-Bot-Api-Secret-Token: QuizBotSecret123" \
  -d '{"update_id": 1, "message": {"message_id": 1, "from": {"id": 123, "is_bot": false, "first_name": "Test"}, "chat": {"id": 123, "type": "private"}, "date": 1234567890, "text": "/start"}}' 2>&1 | head -60

echo ""
echo "✅ Диагностика завершена!"

