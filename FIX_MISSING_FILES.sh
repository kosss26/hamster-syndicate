#!/bin/bash
# Исправление отсутствующих файлов на сервере

set -e

echo "🔧 Проверка и исправление отсутствующих файлов..."

cd /var/www/quiz-bot

# 1. Проверяем, что все файлы из репозитория на месте
echo "🔍 Проверка структуры проекта..."
if [ ! -d "bot/src/Infrastructure/Cache" ]; then
    echo "❌ Директория Cache не найдена!"
    echo "Обновляем проект..."
    git pull origin main
    cd bot
    composer install --no-dev --optimize-autoloader
else
    echo "✅ Директория Cache существует"
fi

# 2. Проверяем наличие всех необходимых файлов
echo "🔍 Проверка необходимых файлов..."
cd bot

MISSING_FILES=0

check_file() {
    if [ ! -f "$1" ]; then
        echo "❌ Файл не найден: $1"
        MISSING_FILES=$((MISSING_FILES + 1))
    else
        echo "✅ $1"
    fi
}

check_file "src/Infrastructure/Cache/CacheFactory.php"
check_file "src/Infrastructure/Config/Config.php"
check_file "src/Infrastructure/Logging/LoggerFactory.php"
check_file "src/Infrastructure/Telegram/TelegramClientFactory.php"
check_file "src/Infrastructure/Telegram/WebhookHandler.php"
check_file "src/Bootstrap/AppBootstrap.php"
check_file "public/index.php"

if [ $MISSING_FILES -gt 0 ]; then
    echo "❌ Найдено $MISSING_FILES отсутствующих файлов"
    echo "Обновляем проект..."
    
    # Обновляем из git
    cd /var/www/quiz-bot
    git fetch origin
    git reset --hard origin/main
    git pull origin main
    
    # Переустанавливаем зависимости
    cd bot
    composer install --no-dev --optimize-autoloader
    
    # Перегенерируем autoloader
    composer dump-autoload --optimize
    
    echo "✅ Проект обновлён"
else
    echo "✅ Все файлы на месте"
fi

# 3. Проверяем структуру директорий
echo "🔍 Проверка структуры директорий..."
ls -la src/Infrastructure/ || echo "Директория Infrastructure не найдена"
ls -la src/Infrastructure/Cache/ || echo "Директория Cache не найдена"
ls -la src/Infrastructure/Config/ || echo "Директория Config не найдена"

# 4. Перегенерируем autoloader
echo "🔄 Перегенерация autoloader..."
composer dump-autoload --optimize

# 5. Проверяем загрузку класса
echo "🔍 Проверка загрузки класса..."
php -r "
require 'vendor/autoload.php';
if (class_exists('QuizBot\Infrastructure\Cache\CacheFactory')) {
    echo '✅ Класс CacheFactory загружен успешно' . PHP_EOL;
} else {
    echo '❌ Класс CacheFactory не найден!' . PHP_EOL;
    echo 'Проверяем autoload файлы...' . PHP_EOL;
    if (file_exists('vendor/composer/autoload_classmap.php')) {
        \$map = require 'vendor/composer/autoload_classmap.php';
        if (isset(\$map['QuizBot\Infrastructure\Cache\CacheFactory'])) {
            echo 'Класс найден в classmap: ' . \$map['QuizBot\Infrastructure\Cache\CacheFactory'] . PHP_EOL;
        } else {
            echo 'Класс не найден в classmap' . PHP_EOL;
        }
    }
    exit(1);
}
"

# 6. Проверяем webhook
echo "🔍 Тест webhook..."
HTTP_CODE=$(curl -k -s -o /dev/null -w "%{http_code}" https://localhost/webhook || echo "000")
if [ "$HTTP_CODE" = "200" ] || [ "$HTTP_CODE" = "405" ]; then
    echo "✅ Webhook отвечает (код: $HTTP_CODE)"
else
    echo "⚠️ Webhook не отвечает правильно (код: $HTTP_CODE)"
    echo "Проверяем логи..."
    tail -5 /var/log/nginx/quiz-bot-error.log || true
fi

echo ""
echo "✅ Готово!"

