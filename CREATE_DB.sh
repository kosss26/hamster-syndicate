#!/bin/bash
# Скрипт для создания новой БД на сервере

set -e

echo "🗄️ Создание новой базы данных..."

cd /var/www/quiz-bot/bot

# 1. Удаляем старую БД, если есть
echo "🧹 Очистка старых данных..."
rm -f storage/database/database.sqlite
rm -rf storage/database/*

# 2. Создаём директорию для БД
echo "📁 Создание директорий..."
mkdir -p storage/database
chmod 777 storage/database

# 3. Создаём пустой файл БД
echo "📄 Создание файла базы данных..."
touch storage/database/database.sqlite
chmod 666 storage/database/database.sqlite

# 4. Настраиваем config/app.env с правильными путями
echo "⚙️ Настройка конфигурации..."
cat > config/app.env << 'EOF'
APP_ENV=production
APP_DEBUG=false
APP_URL=https://app.tvix.ru
LOG_CHANNEL=stack

TELEGRAM_BOT_TOKEN=8416923485:AAHcLsagrmSfopY453D9YfbDZ3ihRJ2zE5w
TELEGRAM_WEBHOOK_SECRET=QuizBotSecret123

DB_CONNECTION=sqlite
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=/var/www/quiz-bot/bot/storage/database/database.sqlite
DB_USERNAME=null
DB_PASSWORD=null

CACHE_DRIVER=array
QUEUE_CONNECTION=sync
EOF

# 5. Применяем миграции (создаём структуру БД)
echo "🗄️ Применение миграций..."
php bin/migrate.php

# 6. Заполняем базу данными
echo "🌱 Заполнение базы данных..."
php bin/seed.php

# 7. Проверяем, что БД создана
if [ -f "storage/database/database.sqlite" ]; then
    DB_SIZE=$(stat -f%z storage/database/database.sqlite 2>/dev/null || stat -c%s storage/database/database.sqlite 2>/dev/null || echo "0")
    echo "✅ База данных создана успешно! Размер: $DB_SIZE байт"
else
    echo "❌ Ошибка: файл базы данных не создан!"
    exit 1
fi

# 8. Настраиваем права
echo "🔐 Настройка прав доступа..."
chown -R www-data:www-data /var/www/quiz-bot
chmod -R 755 /var/www/quiz-bot
chmod -R 777 storage/database

echo "✅ База данных готова к использованию!"
echo "📍 Путь к БД: /var/www/quiz-bot/bot/storage/database/database.sqlite"

