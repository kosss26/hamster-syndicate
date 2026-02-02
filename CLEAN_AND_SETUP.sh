#!/bin/bash
# Полная очистка и установка проекта с нуля

set -e

echo "🧹 Полная очистка и установка проекта..."

# 1. Останавливаем сервисы (если нужно)
echo "⏸️ Остановка сервисов..."
systemctl stop nginx 2>/dev/null || true
systemctl stop php8.2-fpm 2>/dev/null || true

# 2. Удаляем старый проект полностью
echo "🗑️ Удаление старого проекта..."
if [ -d "/var/www/quiz-bot" ]; then
    echo "Удаление /var/www/quiz-bot..."
    rm -rf /var/www/quiz-bot
fi

# 3. Удаляем старую конфигурацию Nginx
echo "🗑️ Удаление старой конфигурации Nginx..."
rm -f /etc/nginx/sites-enabled/quiz-bot
rm -f /etc/nginx/sites-available/quiz-bot

# 4. Удаляем старые логи
echo "🗑️ Очистка старых логов..."
rm -f /var/log/nginx/quiz-bot-*.log 2>/dev/null || true

# 5. Удаляем старую базу данных (если была в другом месте)
echo "🗑️ Поиск и удаление старых баз данных..."
find /var/www -name "*.sqlite" -type f -delete 2>/dev/null || true
find /var/www -name "*.db" -type f -delete 2>/dev/null || true

# 6. Создаём чистую директорию
echo "📁 Создание новой директории..."
mkdir -p /var/www/quiz-bot
cd /var/www/quiz-bot

# 7. Клонируем проект заново
echo "📥 Клонирование проекта с GitHub..."
git clone https://github.com/kosss26/hamster-syndicate.git .

# 8. Переходим в директорию бота
cd bot

# 9. Устанавливаем зависимости
echo "📦 Установка зависимостей..."
if ! dpkg -l | grep -q php8.2-zip; then
    apt install -y php8.2-zip unzip
fi

# 10. Устанавливаем Composer (если нужно)
if ! command -v composer &> /dev/null; then
    echo "📦 Установка Composer..."
    curl -sS https://getcomposer.org/installer | php
    mv composer.phar /usr/local/bin/composer
    chmod +x /usr/local/bin/composer
fi

# 11. Устанавливаем зависимости проекта
composer install --no-dev --optimize-autoloader

# 12. Создаём директории
echo "📁 Создание директорий..."
mkdir -p storage/database storage/logs storage/cache
chmod 777 storage/database
chmod 755 storage/logs storage/cache

# 13. Создаём пустую БД
echo "📄 Создание файла базы данных..."
touch storage/database/database.sqlite
chmod 666 storage/database/database.sqlite

# 14. Настраиваем config/app.env
echo "⚙️ Настройка конфигурации..."
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

ADMIN_TELEGRAM_IDS=1763619724

WEBAPP_URL=https://app.tvixx.ru/webapp/
EOF

# 15. Применяем миграции
echo "🗄️ Применение миграций..."
php bin/migrate.php

# 16. Заполняем базу
echo "🌱 Заполнение базы данных..."
php bin/seed.php

# 17. Настраиваем Nginx
echo "🌐 Настройка Nginx..."
cat > /etc/nginx/sites-available/quiz-bot << 'NGINX_EOF'
server {
    listen 80;
    server_name app.tvixx.ru;
    
    root /var/www/quiz-bot/bot/public;
    index index.php;
    
    access_log /var/log/nginx/quiz-bot-access.log;
    error_log /var/log/nginx/quiz-bot-error.log;
    
    # ACME challenge для Let's Encrypt
    location /.well-known/acme-challenge/ {
        root /var/www/certbot;
        try_files $uri =404;
    }
    
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

# 18. Активируем конфигурацию
ln -sf /etc/nginx/sites-available/quiz-bot /etc/nginx/sites-enabled/

# 19. Создаём директорию для ACME challenge
mkdir -p /var/www/certbot
chmod 755 /var/www/certbot

# 20. Проверяем и перезагружаем Nginx
echo "🔄 Перезагрузка Nginx..."
nginx -t
systemctl start nginx
systemctl reload nginx

# 21. Перезапускаем PHP-FPM
echo "🔄 Перезапуск PHP-FPM..."
systemctl start php8.2-fpm
systemctl restart php8.2-fpm

# 22. Настраиваем права
echo "🔐 Настройка прав доступа..."
chown -R www-data:www-data /var/www/quiz-bot
chmod -R 755 /var/www/quiz-bot
chmod -R 777 /var/www/quiz-bot/bot/storage

# 23. Проверяем работу
echo "✅ Проверка работы..."
if [ -f "storage/database/database.sqlite" ]; then
    DB_SIZE=$(stat -c%s storage/database/database.sqlite 2>/dev/null || echo "0")
    echo "✅ База данных создана! Размер: $DB_SIZE байт"
else
    echo "❌ Ошибка: файл базы данных не создан!"
    exit 1
fi

# 24. Проверяем webhook endpoint
echo "🔍 Проверка webhook endpoint..."
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://localhost/webhook || echo "000")
if [ "$HTTP_CODE" != "404" ] && [ "$HTTP_CODE" != "000" ]; then
    echo "✅ Webhook endpoint отвечает (код: $HTTP_CODE)"
else
    echo "⚠️ Webhook endpoint не отвечает (код: $HTTP_CODE)"
fi

echo ""
echo "✅ Установка завершена!"
echo "📍 Путь к БД: /var/www/quiz-bot/bot/storage/database/database.sqlite"
echo "🌐 URL: http://app.tvixx.ru/webhook"
echo ""
echo "📝 Следующий шаг: установите SSL сертификат:"
echo "   certbot --nginx -d app.tvixx.ru"

