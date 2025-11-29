# Инструкция по развёртыванию на сервере

## Сервер: 91.218.115.167
## Домены: app.tvix.ru, api.tvix.ru

### Шаг 1: Подключение к серверу

```bash
ssh root@91.218.115.167
```

### Шаг 2: Установка необходимых пакетов

```bash
apt update
apt install -y git composer nginx php8.1-fpm php8.1-cli php8.1-mbstring php8.1-xml php8.1-sqlite3 php8.1-mysql
```

### Шаг 3: Клонирование и настройка проекта

```bash
cd /var/www
git clone https://github.com/kosss26/hamster-syndicate.git quiz-bot
cd quiz-bot/bot
composer install --no-dev --optimize-autoloader
cp config/app.example.env config/app.env
nano config/app.env  # Настройте токен бота и другие параметры
```

### Шаг 4: Настройка базы данных

```bash
composer migrate
composer seed
```

### Шаг 5: Настройка Nginx

```bash
# Скопируйте конфигурацию
cp /var/www/quiz-bot/nginx-config.conf /etc/nginx/sites-available/quiz-bot

# Создайте симлинк
ln -s /etc/nginx/sites-available/quiz-bot /etc/nginx/sites-enabled/

# Проверьте конфигурацию
nginx -t

# Перезагрузите Nginx
systemctl reload nginx
```

### Шаг 6: Настройка прав доступа

```bash
chown -R www-data:www-data /var/www/quiz-bot
chmod -R 755 /var/www/quiz-bot
```

### Шаг 7: Установка SSL (Let's Encrypt)

```bash
apt install certbot python3-certbot-nginx
certbot --nginx -d app.tvix.ru
```

### Шаг 8: Установка webhook

```bash
curl -X POST "https://api.telegram.org/bot<YOUR_BOT_TOKEN>/setWebhook" \
  -H "Content-Type: application/json" \
  -d '{
    "url": "https://app.tvix.ru/webhook",
    "secret_token": "QuizBotSecret123"
  }'
```

### Шаг 9: Проверка webhook

```bash
curl "https://api.telegram.org/bot<YOUR_BOT_TOKEN>/getWebhookInfo"
```

### Обновление проекта

```bash
cd /var/www/quiz-bot
git pull origin main
cd bot
composer install --no-dev --optimize-autoloader
composer migrate  # Если есть новые миграции
systemctl reload nginx
```

