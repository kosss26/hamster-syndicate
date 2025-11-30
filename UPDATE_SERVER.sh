#!/bin/bash

# Скрипт для обновления бота на сервере

set -e

echo "🔄 Начинаю обновление бота на сервере..."

cd /var/www/quiz-bot/bot || exit 1

echo "📥 Получаю последние изменения из репозитория..."
git stash || true
git reset --hard origin/main
git pull origin main

echo "📦 Обновляю зависимости..."
composer install --no-dev --optimize-autoloader

echo "🗄️ Запускаю миграции..."
php bin/migrate.php

echo "🌱 Заполняю базу данных..."
php bin/seed.php

echo "🔄 Обновляю autoloader..."
composer dump-autoload --optimize

echo "✅ Обновление завершено!"
echo ""
echo "Проверьте логи:"
echo "  tail -50 storage/logs/app.log"
echo ""
echo "Проверьте статус webhook:"
echo "  curl -s \"https://api.telegram.org/bot8416923485:AAHcLsagrmSfopY453D9YfbDZ3ihRJ2zE5w/getWebhookInfo\" | python3 -m json.tool"

