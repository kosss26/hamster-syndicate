# Быстрое исправление проблем на сервере

Выполните эти команды на сервере:

```bash
# 1. Установка PHP 8.2
apt install -y software-properties-common
add-apt-repository ppa:ondrej/php -y
apt update
apt install -y php8.2-fpm php8.2-cli php8.2-mbstring php8.2-xml php8.2-sqlite3 php8.2-mysql php8.2-curl

# 2. Установка Composer
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer
chmod +x /usr/local/bin/composer

# 3. Переход в директорию проекта
cd /var/www/quiz-bot/bot

# 4. Установка зависимостей
composer install --no-dev --optimize-autoloader

# 5. Настройка окружения (если ещё не настроено)
if [ ! -f "config/app.env" ]; then
    cp config/app.example.env config/app.env
    echo "⚠️ Отредактируйте config/app.env и укажите TELEGRAM_BOT_TOKEN!"
fi

# 6. Применение миграций
composer migrate

# 7. Заполнение базы
composer seed

# 8. Обновление конфигурации Nginx
sed -i 's/php8.1-fpm/php8.2-fpm/g' /etc/nginx/sites-available/quiz-bot
nginx -t
systemctl reload nginx

# 9. Перезапуск PHP-FPM
systemctl restart php8.2-fpm

# 10. Проверка работы
curl http://localhost/webhook
# Должен вернуть что-то, не 404

# 11. Установка webhook (после настройки config/app.env)
# Замените <YOUR_BOT_TOKEN> на реальный токен из config/app.env
BOT_TOKEN=$(grep TELEGRAM_BOT_TOKEN /var/www/quiz-bot/bot/config/app.env | cut -d '=' -f2 | tr -d ' ')
curl -X POST "https://api.telegram.org/bot${BOT_TOKEN}/setWebhook" \
  -H "Content-Type: application/json" \
  -d '{"url": "https://app.tvix.ru/webhook", "secret_token": "QuizBotSecret123"}'

# 12. Проверка webhook
curl "https://api.telegram.org/bot${BOT_TOKEN}/getWebhookInfo"
```

