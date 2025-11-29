# Инструкция по развёртыванию

## Подготовка GitHub репозитория

### 1. Очистка существующего репозитория

```bash
# Клонируйте репозиторий
git clone https://github.com/kosss26/hamster-syndicate.git
cd hamster-syndicate

# Удалите все файлы (кроме .git)
git rm -rf .
git commit -m "Очистка репозитория для нового проекта"
git push origin main
```

### 2. Добавление нового проекта

```bash
# Из корня проекта Social
cd /Users/evgeny/Desktop/Social

# Инициализируйте git (если ещё не инициализирован)
git init

# Добавьте remote
git remote add origin https://github.com/kosss26/hamster-syndicate.git

# Добавьте все файлы
git add .

# Создайте коммит
git commit -m "Initial commit: Telegram Quiz Bot"

# Отправьте на GitHub (force push для замены содержимого)
git push -f origin main
```

## Развёртывание на сервере

### Вариант 1: Через SSH

```bash
# На сервере
cd /var/www
git clone https://github.com/kosss26/hamster-syndicate.git
cd hamster-syndicate/bot

# Установите зависимости
composer install --no-dev --optimize-autoloader

# Настройте окружение
cp config/app.example.env config/app.env
nano config/app.env

# Примените миграции
composer migrate
composer seed

# Настройте права
chmod -R 755 /var/www/hamster-syndicate
chown -R www-data:www-data /var/www/hamster-syndicate
```

### Вариант 2: Через панель управления (Reg.ru)

1. Зайдите в панель управления хостингом
2. Откройте файловый менеджер
3. Загрузите проект через Git или архивом
4. Настройте окружение через файловый менеджер
5. Примените миграции через SSH или cron

### Настройка Nginx

Создайте файл `/etc/nginx/sites-available/quiz-bot`:

```nginx
server {
    listen 80;
    server_name tvixx.ru www.tvixx.ru;
    
    root /var/www/hamster-syndicate/bot/public;
    index index.php;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
    
    location ~ /\. {
        deny all;
    }
}
```

Активируйте конфигурацию:
```bash
ln -s /etc/nginx/sites-available/quiz-bot /etc/nginx/sites-enabled/
nginx -t
systemctl reload nginx
```

### Настройка SSL (Let's Encrypt)

```bash
certbot --nginx -d tvixx.ru -d www.tvixx.ru
```

### Установка webhook

```bash
curl -X POST "https://api.telegram.org/bot<YOUR_BOT_TOKEN>/setWebhook" \
  -H "Content-Type: application/json" \
  -d '{
    "url": "https://tvixx.ru/webhook",
    "secret_token": "QuizBotSecret123"
  }'
```

### Проверка webhook

```bash
curl "https://api.telegram.org/bot<YOUR_BOT_TOKEN>/getWebhookInfo"
```

## Обновление проекта

```bash
# На сервере
cd /var/www/hamster-syndicate
git pull origin main
cd bot
composer install --no-dev --optimize-autoloader
composer migrate  # Если есть новые миграции
```

## Мониторинг

Для мониторинга логов:
```bash
tail -f /var/www/hamster-syndicate/bot/storage/logs/app.log
```

Для мониторинга ошибок PHP:
```bash
tail -f /var/log/php7.4-fpm.log
```

