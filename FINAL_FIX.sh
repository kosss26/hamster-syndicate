#!/bin/bash
# Финальное исправление всех проблем на сервере

echo "🔧 Финальное исправление проблем..."

cd /var/www/quiz-bot/bot

# 1. Установка php-zip для Composer
echo "📦 Установка php-zip..."
apt install -y php8.2-zip unzip

# 2. Исправление git safe directory
echo "🔐 Исправление git..."
git config --global --add safe.directory /var/www/quiz-bot

# 3. Настройка config/app.env для SQLite
echo "⚙️ Настройка config/app.env..."
cat > config/app.env << 'EOF'
APP_ENV=production
APP_DEBUG=false
APP_URL=https://app.tvixx.ru
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

# 4. Создание директории для базы данных
echo "📁 Создание директорий..."
mkdir -p storage/database
chmod 777 storage/database
touch storage/database/database.sqlite
chmod 666 storage/database/database.sqlite

# 5. Применение миграций
echo "🗄️ Применение миграций..."
composer migrate

# 6. Заполнение базы
echo "🌱 Заполнение базы данных..."
composer seed

# 7. Обновление конфигурации Nginx
echo "🌐 Обновление Nginx..."
cat > /etc/nginx/sites-available/quiz-bot << 'NGINX_EOF'
server {
    listen 80;
    server_name app.tvixx.ru;
    
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

# 8. Перезагрузка Nginx
echo "🔄 Перезагрузка Nginx..."
nginx -t && systemctl reload nginx

# 9. Перезапуск PHP-FPM
echo "🔄 Перезапуск PHP-FPM..."
systemctl restart php8.2-fpm

# 10. Настройка прав
echo "🔐 Настройка прав..."
chown -R www-data:www-data /var/www/quiz-bot
chmod -R 755 /var/www/quiz-bot
chmod -R 777 /var/www/quiz-bot/bot/storage

# 11. Проверка работы
echo "✅ Проверка работы..."
curl -s http://localhost/webhook | head -5

# 12. Установка webhook
echo "📡 Установка webhook..."
BOT_TOKEN="8416923485:AAHcLsagrmSfopY453D9YfbDZ3ihRJ2zE5w"
curl -X POST "https://api.telegram.org/bot${BOT_TOKEN}/setWebhook" \
  -H "Content-Type: application/json" \
  -d '{"url": "https://app.tvixx.ru/webhook", "secret_token": "QuizBotSecret123"}'

echo ""
echo "✅ Все исправления применены!"
echo "📝 Проверьте webhook: curl 'https://api.telegram.org/bot${BOT_TOKEN}/getWebhookInfo'"

