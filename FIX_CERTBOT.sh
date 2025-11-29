#!/bin/bash
# Исправление проблемы с установкой SSL сертификата

set -e

echo "🔧 Исправление конфигурации для Certbot..."

# 1. Создаём директорию для ACME challenge
echo "📁 Создание директории для ACME challenge..."
mkdir -p /var/www/certbot
chmod 755 /var/www/certbot

# 2. Обновляем конфигурацию Nginx с поддержкой ACME challenge
echo "🌐 Обновление конфигурации Nginx..."
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

# 3. Проверяем конфигурацию
echo "✅ Проверка конфигурации Nginx..."
nginx -t

# 4. Перезагружаем Nginx
echo "🔄 Перезагрузка Nginx..."
systemctl reload nginx

# 5. Проверяем доступность ACME challenge
echo "🔍 Проверка доступности ACME challenge..."
mkdir -p /var/www/certbot/.well-known/acme-challenge
echo "test" > /var/www/certbot/.well-known/acme-challenge/test
chmod 644 /var/www/certbot/.well-known/acme-challenge/test

# Проверяем локально
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://localhost/.well-known/acme-challenge/test || echo "000")
if [ "$HTTP_CODE" = "200" ]; then
    echo "✅ ACME challenge доступен локально"
else
    echo "⚠️ ACME challenge не доступен (код: $HTTP_CODE)"
fi

# 6. Пытаемся установить сертификат снова
echo "🔒 Попытка установки SSL сертификата..."
certbot --nginx -d app.tvixx.ru --non-interactive --agree-tos --email admin@tvix.ru --redirect || {
    echo "⚠️ Не удалось установить сертификат автоматически"
    echo "Проверьте:"
    echo "1. Домен app.tvixx.ru указывает на IP 91.218.115.167"
    echo "2. Порт 80 открыт в firewall"
    echo "3. Nginx доступен из интернета"
}

# 7. Проверяем наличие сертификата
if [ -f "/etc/letsencrypt/live/app.tvixx.ru/fullchain.pem" ]; then
    echo "✅ SSL сертификат установлен!"
    echo "📍 Путь: /etc/letsencrypt/live/app.tvixx.ru/"
    
    # 8. Обновляем webhook
    echo "📡 Обновление webhook..."
    BOT_TOKEN="8416923485:AAHcLsagrmSfopY453D9YfbDZ3ihRJ2zE5w"
    curl -X POST "https://api.telegram.org/bot${BOT_TOKEN}/setWebhook" \
      -H "Content-Type: application/json" \
      -d '{"url": "https://app.tvixx.ru/webhook", "secret_token": "QuizBotSecret123"}'
    
    # 9. Проверяем статус
    echo "📊 Статус webhook:"
    curl -s "https://api.telegram.org/bot${BOT_TOKEN}/getWebhookInfo" | python3 -m json.tool || echo "Ошибка проверки"
else
    echo "❌ SSL сертификат не установлен"
    echo "Попробуйте установить вручную:"
    echo "certbot --nginx -d app.tvixx.ru"
fi

echo ""
echo "✅ Готово!"

