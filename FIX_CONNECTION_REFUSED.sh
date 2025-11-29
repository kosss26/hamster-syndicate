#!/bin/bash
# Исправление ошибки Connection refused

set -e

echo "🔧 Исправление ошибки Connection refused..."

# 1. Проверяем статус PHP-FPM
echo "🔍 Проверка статуса PHP-FPM..."
systemctl status php8.2-fpm --no-pager | head -10

# 2. Запускаем PHP-FPM, если не запущен
echo "🔄 Запуск PHP-FPM..."
systemctl start php8.2-fpm
systemctl enable php8.2-fpm

# 3. Проверяем сокет PHP-FPM
echo "🔍 Проверка сокета PHP-FPM..."
if [ -S "/var/run/php/php8.2-fpm.sock" ]; then
    echo "✅ Сокет PHP-FPM существует"
    ls -la /var/run/php/php8.2-fpm.sock
else
    echo "❌ Сокет PHP-FPM не найден!"
    echo "Проверяем альтернативные пути..."
    find /var/run -name "*fpm*.sock" 2>/dev/null || true
fi

# 4. Проверяем конфигурацию Nginx после certbot
echo "🔍 Проверка конфигурации Nginx..."
if [ -f "/etc/nginx/sites-enabled/quiz-bot" ]; then
    echo "Конфигурация quiz-bot:"
    cat /etc/nginx/sites-enabled/quiz-bot | grep -A 5 "fastcgi_pass" || echo "Не найдено fastcgi_pass"
fi

# 5. Проверяем, что certbot не сломал конфигурацию
echo "🔍 Проверка конфигурации после certbot..."
if [ -f "/etc/nginx/sites-available/app.tvixx.ru" ]; then
    echo "Найдена конфигурация app.tvixx.ru, проверяем..."
    cat /etc/nginx/sites-available/app.tvixx.ru | head -30
fi

# 6. Обновляем конфигурацию Nginx с правильным fastcgi_pass
echo "⚙️ Обновление конфигурации Nginx..."
cat > /etc/nginx/sites-available/quiz-bot << 'NGINX_EOF'
server {
    listen 80;
    listen [::]:80;
    server_name app.tvixx.ru;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name app.tvixx.ru;
    
    root /var/www/quiz-bot/bot/public;
    index index.php;
    
    access_log /var/log/nginx/quiz-bot-access.log;
    error_log /var/log/nginx/quiz-bot-error.log;
    
    ssl_certificate /etc/letsencrypt/live/app.tvixx.ru-0001/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/app.tvixx.ru-0001/privkey.pem;
    include /etc/letsencrypt/options-ssl-nginx.conf;
    ssl_dhparam /etc/letsencrypt/ssl-dhparams.pem;
    
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

# 7. Активируем конфигурацию
ln -sf /etc/nginx/sites-available/quiz-bot /etc/nginx/sites-enabled/quiz-bot

# 8. Проверяем конфигурацию
echo "✅ Проверка конфигурации Nginx..."
nginx -t

# 9. Перезагружаем Nginx
echo "🔄 Перезагрузка Nginx..."
systemctl reload nginx

# 10. Перезапускаем PHP-FPM
echo "🔄 Перезапуск PHP-FPM..."
systemctl restart php8.2-fpm

# 11. Проверяем доступность webhook локально
echo "🔍 Проверка webhook локально..."
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://localhost/webhook || echo "000")
echo "Локальный webhook код: $HTTP_CODE"

# 12. Проверяем доступность через HTTPS
echo "🔍 Проверка webhook через HTTPS..."
HTTPS_CODE=$(curl -s -k -o /dev/null -w "%{http_code}" https://localhost/webhook || echo "000")
echo "HTTPS webhook код: $HTTPS_CODE"

# 13. Проверяем статус webhook
echo "📊 Статус webhook:"
BOT_TOKEN="8416923485:AAHcLsagrmSfopY453D9YfbDZ3ihRJ2zE5w"
curl -s "https://api.telegram.org/bot${BOT_TOKEN}/getWebhookInfo" | python3 -m json.tool || echo "Ошибка проверки"

echo ""
echo "✅ Готово!"

