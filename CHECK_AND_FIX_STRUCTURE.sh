#!/bin/bash
# Проверка и исправление структуры проекта

set -e

echo "🔧 Проверка структуры проекта..."

cd /var/www/quiz-bot

# 1. Проверяем текущую структуру
echo "🔍 Текущая структура:"
ls -la

# 2. Проверяем, где находятся файлы
echo "🔍 Поиск файлов проекта..."
find . -name "CacheFactory.php" -type f 2>/dev/null || echo "CacheFactory.php не найден"
find . -name "AppBootstrap.php" -type f 2>/dev/null || echo "AppBootstrap.php не найден"
find . -name "index.php" -path "*/public/*" -type f 2>/dev/null || echo "index.php не найден"

# 3. Проверяем структуру директорий
echo "🔍 Структура директорий:"
if [ -d "bot" ]; then
    echo "✅ Директория bot существует"
    ls -la bot/ | head -20
    if [ -d "bot/src" ]; then
        echo "✅ Директория bot/src существует"
        ls -la bot/src/ | head -20
    fi
else
    echo "❌ Директория bot не найдена"
    echo "Проверяем, может файлы в корне..."
    if [ -d "src" ]; then
        echo "✅ Директория src в корне существует"
        ls -la src/
    fi
fi

# 4. Если структура неправильная, исправляем
echo "🔧 Исправление структуры..."

# Проверяем, есть ли файлы в корне
if [ -d "src" ] && [ ! -d "bot" ]; then
    echo "Файлы в корне, создаём структуру bot/"
    mkdir -p bot
    mv src bot/ 2>/dev/null || true
    mv public bot/ 2>/dev/null || true
    mv vendor bot/ 2>/dev/null || true
    mv composer.json bot/ 2>/dev/null || true
    mv composer.lock bot/ 2>/dev/null || true
    mv config bot/ 2>/dev/null || true
    mv database bot/ 2>/dev/null || true
    mv bin bot/ 2>/dev/null || true
fi

# 5. Если директория bot существует, проверяем её содержимое
if [ -d "bot" ]; then
    cd bot
    echo "🔍 Содержимое bot/:"
    ls -la
    
    # Проверяем наличие необходимых файлов
    if [ ! -d "src/Infrastructure/Cache" ]; then
        echo "❌ Директория src/Infrastructure/Cache не найдена"
        echo "Обновляем из git..."
        cd /var/www/quiz-bot
        git fetch origin
        git reset --hard origin/main
        git clean -fd
        git pull origin main
    fi
fi

# 6. Переустанавливаем зависимости
echo "📦 Переустановка зависимостей..."
if [ -d "bot" ]; then
    cd bot
else
    echo "⚠️ Директория bot не найдена, работаем в корне"
fi

if [ -f "composer.json" ]; then
    composer install --no-dev --optimize-autoloader
    composer dump-autoload --optimize
else
    echo "❌ composer.json не найден!"
    exit 1
fi

# 7. Проверяем наличие файлов после исправления
echo "🔍 Проверка файлов после исправления..."
if [ -f "src/Infrastructure/Cache/CacheFactory.php" ]; then
    echo "✅ CacheFactory.php найден"
    head -5 src/Infrastructure/Cache/CacheFactory.php
else
    echo "❌ CacheFactory.php всё ещё не найден"
    echo "Список файлов в src/Infrastructure/:"
    ls -la src/Infrastructure/ 2>/dev/null || echo "Директория не существует"
fi

# 8. Проверяем webhook
echo "🔍 Тест webhook..."
cd /var/www/quiz-bot/bot 2>/dev/null || cd /var/www/quiz-bot
HTTP_CODE=$(curl -k -s -o /dev/null -w "%{http_code}" https://localhost/webhook || echo "000")
echo "Код ответа: $HTTP_CODE"

echo ""
echo "✅ Готово!"

