#!/bin/bash
# Полный скрипт настройки сервера - выполнить на сервере

set -e

echo "🚀 Полная настройка сервера для Quiz Bot..."

cd /var/www/quiz-bot

# 1. Обновляем код
echo "📥 Обновление кода с GitHub..."
git pull origin main

cd bot

# 2. Устанавливаем php-zip (если ещё не установлен)
echo "📦 Проверка зависимостей..."
if ! dpkg -l | grep -q php8.2-zip; then
    apt install -y php8.2-zip unzip
fi

# 3. Исправляем git safe directory
echo "🔐 Настройка git..."
git config --global --add safe.directory /var/www/quiz-bot

# 4. Удаляем старую БД
echo "🧹 Очистка старых данных..."
rm -f storage/database/database.sqlite
rm -rf storage/database/*

# 5. Создаём директорию для БД
echo "📁 Создание директорий..."
mkdir -p storage/database storage/logs storage/cache
chmod 777 storage/database
chmod 755 storage/logs storage/cache

# 6. Создаём пустой файл БД
echo "📄 Создание файла базы данных..."
touch storage/database/database.sqlite
chmod 666 storage/database/database.sqlite

# 7. Настраиваем config/app.env
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

# 8. Применяем миграции
echo "🗄️ Применение миграций..."
php bin/migrate.php

# 9. Заполняем базу данными
echo "🌱 Заполнение базы данных..."
php bin/seed.php

# 10. Обновляем конфигурацию Nginx
echo "🌐 Обновление конфигурации Nginx..."
cat > /etc/nginx/sites-available/quiz-bot << 'NGINX_EOF'
server {
    listen 80;
    server_name app.tvix.ru;
    
    root /var/www/quiz-bot/bot/public;
    index index.php;
    
    access_log /var/log/nginx/quiz-bot-access.log;
    error_log /var/log/nginx/quiz-bot-error.log;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param PATH_INFO $fastcgi_path_info;
    }
    
    location ~ /\. {
        deny all;
        access_log off;
        log_not_found off;
    }
    
    location ~ \.(env|log|db)$ {
        deny all;
        access_log off;
        log_not_found off;
    }
}
NGINX_EOF

# 11. Перезагружаем Nginx
echo "🔄 Перезагрузка Nginx..."
nginx -t && systemctl reload nginx

# 12. Перезапускаем PHP-FPM
echo "🔄 Перезапуск PHP-FPM..."
systemctl restart php8.2-fpm

# 13. Настраиваем права
echo "🔐 Настройка прав доступа..."
chown -R www-data:www-data /var/www/quiz-bot
chmod -R 755 /var/www/quiz-bot
chmod -R 777 /var/www/quiz-bot/bot/storage

# 14. Проверяем работу
echo "✅ Проверка работы..."
if [ -f "storage/database/database.sqlite" ]; then
    DB_SIZE=$(stat -c%s storage/database/database.sqlite 2>/dev/null || echo "0")
    echo "✅ База данных создана! Размер: $DB_SIZE байт"
else
    echo "❌ Ошибка: файл базы данных не создан!"
    exit 1
fi

# 15. Проверяем webhook endpoint
echo "🔍 Проверка webhook endpoint..."
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://localhost/webhook || echo "000")
if [ "$HTTP_CODE" != "404" ] && [ "$HTTP_CODE" != "000" ]; then
    echo "✅ Webhook endpoint отвечает (код: $HTTP_CODE)"
else
    echo "⚠️ Webhook endpoint не отвечает (код: $HTTP_CODE)"
fi

# 16. Устанавливаем webhook
echo "📡 Установка webhook..."
BOT_TOKEN="8416923485:AAHcLsagrmSfopY453D9YfbDZ3ihRJ2zE5w"
WEBHOOK_RESULT=$(curl -s -X POST "https://api.telegram.org/bot${BOT_TOKEN}/setWebhook" \
  -H "Content-Type: application/json" \
  -d '{"url": "https://app.tvix.ru/webhook", "secret_token": "QuizBotSecret123"}')

if echo "$WEBHOOK_RESULT" | grep -q '"ok":true'; then
    echo "✅ Webhook установлен успешно!"
else
    echo "⚠️ Проблема с установкой webhook:"
    echo "$WEBHOOK_RESULT"
fi

# 17. Проверяем статус webhook
echo "📊 Статус webhook:"
curl -s "https://api.telegram.org/bot${BOT_TOKEN}/getWebhookInfo" | head -10

echo ""
echo "✅ Настройка завершена!"
echo "📍 Путь к БД: /var/www/quiz-bot/bot/storage/database/database.sqlite"
echo "🌐 URL: https://app.tvix.ru/webhook"

