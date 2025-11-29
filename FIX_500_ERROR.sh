#!/bin/bash
# Исправление ошибки 500 Internal Server Error

set -e

echo "🔧 Исправление ошибки 500..."

cd /var/www/quiz-bot/bot

# 1. Проверяем логи Nginx
echo "📋 Последние ошибки Nginx:"
tail -20 /var/log/nginx/quiz-bot-error.log || echo "Лог пуст"

# 2. Проверяем логи PHP-FPM
echo "📋 Последние ошибки PHP-FPM:"
tail -20 /var/log/php8.2-fpm.log || echo "Лог пуст"

# 3. Проверяем логи приложения
echo "📋 Логи приложения:"
if [ -f "storage/logs/app.log" ]; then
    tail -20 storage/logs/app.log || echo "Лог пуст"
else
    echo "Файл логов не найден"
fi

# 4. Проверяем права доступа
echo "🔐 Проверка прав доступа..."
ls -la storage/
ls -la storage/database/
ls -la storage/logs/

# 5. Проверяем конфигурацию
echo "⚙️ Проверка конфигурации..."
if [ -f "config/app.env" ]; then
    echo "Конфигурация существует"
    cat config/app.env | grep -v "PASSWORD\|TOKEN" || echo "Ошибка чтения"
else
    echo "❌ Конфигурация не найдена!"
fi

# 6. Проверяем базу данных
echo "🗄️ Проверка базы данных..."
if [ -f "storage/database/database.sqlite" ]; then
    DB_SIZE=$(stat -c%s storage/database/database.sqlite 2>/dev/null || echo "0")
    echo "База данных существует, размер: $DB_SIZE байт"
    ls -la storage/database/database.sqlite
else
    echo "❌ База данных не найдена!"
fi

# 7. Проверяем права на файлы
echo "🔐 Настройка прав..."
chown -R www-data:www-data /var/www/quiz-bot
chmod -R 755 /var/www/quiz-bot
chmod -R 777 /var/www/quiz-bot/bot/storage

# 8. Проверяем PHP ошибки напрямую
echo "🔍 Тест PHP..."
php -r "echo 'PHP работает\n';"
php -r "require 'vendor/autoload.php'; echo 'Autoload работает\n';"

# 9. Проверяем webhook endpoint напрямую через PHP
echo "🔍 Тест webhook endpoint..."
php -r "
require 'vendor/autoload.php';
\$_SERVER['REQUEST_METHOD'] = 'POST';
\$_SERVER['REQUEST_URI'] = '/webhook';
try {
    require 'public/index.php';
} catch (Exception \$e) {
    echo 'Ошибка: ' . \$e->getMessage() . PHP_EOL;
    echo 'Trace: ' . \$e->getTraceAsString() . PHP_EOL;
}
" 2>&1 | head -30

# 10. Проверяем доступность через curl с подробным выводом
echo "🔍 Детальная проверка webhook..."
curl -v -k https://localhost/webhook 2>&1 | head -40

echo ""
echo "✅ Диагностика завершена!"

