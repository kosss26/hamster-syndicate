#!/bin/bash
# Исправление проблемы с autoloader

set -e

echo "🔧 Исправление autoloader..."

cd /var/www/quiz-bot/bot

# 1. Проверяем наличие файла CacheFactory
echo "🔍 Проверка файла CacheFactory..."
if [ -f "src/Infrastructure/Cache/CacheFactory.php" ]; then
    echo "✅ Файл CacheFactory.php существует"
    head -5 src/Infrastructure/Cache/CacheFactory.php
else
    echo "❌ Файл CacheFactory.php не найден!"
    exit 1
fi

# 2. Перегенерируем autoloader
echo "🔄 Перегенерация autoloader..."
composer dump-autoload --optimize

# 3. Проверяем, что класс загружается
echo "🔍 Проверка загрузки класса..."
php -r "
require 'vendor/autoload.php';
if (class_exists('QuizBot\Infrastructure\Cache\CacheFactory')) {
    echo '✅ Класс CacheFactory загружен успешно' . PHP_EOL;
} else {
    echo '❌ Класс CacheFactory не найден!' . PHP_EOL;
    exit(1);
}
"

# 4. Проверяем все необходимые классы
echo "🔍 Проверка всех необходимых классов..."
php -r "
require 'vendor/autoload.php';
\$classes = [
    'QuizBot\Infrastructure\Cache\CacheFactory',
    'QuizBot\Infrastructure\Config\Config',
    'QuizBot\Infrastructure\Logging\LoggerFactory',
    'QuizBot\Infrastructure\Telegram\TelegramClientFactory',
    'QuizBot\Application\Services\UserService',
    'QuizBot\Application\Services\DuelService',
    'QuizBot\Application\Services\GameSessionService',
    'QuizBot\Application\Services\StoryService',
    'QuizBot\Application\Services\ProfileFormatter',
];
foreach (\$classes as \$class) {
    if (class_exists(\$class)) {
        echo '✅ ' . \$class . PHP_EOL;
    } else {
        echo '❌ ' . \$class . ' не найден!' . PHP_EOL;
    }
}
"

# 5. Проверяем структуру директорий
echo "🔍 Проверка структуры директорий..."
ls -la src/Infrastructure/Cache/
ls -la src/Infrastructure/Config/
ls -la src/Infrastructure/Logging/
ls -la src/Infrastructure/Telegram/

# 6. Проверяем webhook после исправления
echo "🔍 Тест webhook после исправления..."
curl -k -s https://localhost/webhook | head -5 || echo "Ошибка"

echo ""
echo "✅ Готово!"

