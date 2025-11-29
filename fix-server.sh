#!/bin/bash
# Скрипт для исправления проблем на сервере

echo "🔧 Исправление проблем на сервере..."

# 1. Установка PHP (проверяем доступную версию)
echo "📦 Установка PHP..."
apt install -y software-properties-common
add-apt-repository ppa:ondrej/php -y
apt update
apt install -y php8.2-fpm php8.2-cli php8.2-mbstring php8.2-xml php8.2-sqlite3 php8.2-mysql php8.2-curl

# 2. Установка Composer
echo "📦 Установка Composer..."
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer
chmod +x /usr/local/bin/composer

# 3. Обновление конфигурации Nginx для правильной версии PHP
echo "⚙️ Обновление конфигурации Nginx..."
sed -i 's/php8.1-fpm/php8.2-fpm/g' /etc/nginx/sites-available/quiz-bot

# 4. Перезапуск сервисов
echo "🔄 Перезапуск сервисов..."
systemctl restart php8.2-fpm
systemctl reload nginx

echo "✅ Исправления применены!"

