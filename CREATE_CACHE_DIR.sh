#!/bin/bash
# Создание отсутствующей директории Cache и файла CacheFactory

set -e

echo "🔧 Создание директории Cache и файла CacheFactory..."

cd /var/www/quiz-bot/bot

# 1. Создаём директорию Cache
echo "📁 Создание директории Cache..."
mkdir -p src/Infrastructure/Cache
chmod 755 src/Infrastructure/Cache

# 2. Создаём файл CacheFactory.php
echo "📄 Создание файла CacheFactory.php..."
cat > src/Infrastructure/Cache/CacheFactory.php << 'PHP_EOF'
<?php

declare(strict_types=1);

namespace QuizBot\Infrastructure\Cache;

use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Contracts\Cache\CacheInterface;

final class CacheFactory
{
    private string $driver;

    private string $storagePath;

    public function __construct(string $driver, string $storagePath)
    {
        $this->driver = $driver;
        $this->storagePath = $storagePath;

        if (!is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0775, true);
        }
    }

    public function create(): CacheInterface
    {
        if ($this->driver === 'filesystem') {
            return new FilesystemAdapter('quiz_bot', 0, $this->storagePath);
        }

        return new ArrayAdapter();
    }
}
PHP_EOF

chmod 644 src/Infrastructure/Cache/CacheFactory.php

# 3. Проверяем, что файл создан
echo "🔍 Проверка файла..."
if [ -f "src/Infrastructure/Cache/CacheFactory.php" ]; then
    echo "✅ Файл CacheFactory.php создан"
    head -10 src/Infrastructure/Cache/CacheFactory.php
else
    echo "❌ Ошибка создания файла!"
    exit 1
fi

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
    exit(1);
}
"

# 6. Проверяем webhook
echo "🔍 Тест webhook..."
HTTP_CODE=$(curl -k -s -o /dev/null -w "%{http_code}" https://localhost/webhook || echo "000")
if [ "$HTTP_CODE" = "200" ] || [ "$HTTP_CODE" = "405" ]; then
    echo "✅ Webhook отвечает (код: $HTTP_CODE)"
else
    echo "⚠️ Webhook код: $HTTP_CODE"
    if [ "$HTTP_CODE" = "500" ]; then
        echo "Проверяем логи..."
        tail -10 /var/log/nginx/quiz-bot-error.log | grep -A 5 "error" || true
    fi
fi

echo ""
echo "✅ Готово!"

