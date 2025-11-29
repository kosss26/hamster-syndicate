# Telegram Quiz Bot - Путешествие знаний

Telegram-бот викторина с сюжетным режимом, свободной игрой и PvP дуэлями.

## Структура проекта

- `bot/` - PHP бэкенд (Slim Framework, Eloquent ORM)
- `docs/` - Документация проекта
- `admin/` - Админ-панель (в разработке)

## Технологии

- PHP 7.4+
- Slim Framework 4
- Eloquent ORM (Laravel Database)
- Telegram Bot API
- SQLite/MySQL

## Быстрый старт

### Локальная разработка

1. Установите зависимости:
```bash
cd bot
composer install
```

2. Настройте окружение:
```bash
cp config/app.example.env config/app.env
# Отредактируйте config/app.env и укажите TELEGRAM_BOT_TOKEN
```

3. Примените миграции и заполните базу:
```bash
composer migrate
composer seed
```

4. Запустите локальный сервер:
```bash
composer start
```

5. Настройте webhook (используйте ngrok или localtunnel):
```bash
# Пример с localtunnel
lt --port 8080

# Установите webhook
curl -X POST "https://api.telegram.org/bot<TOKEN>/setWebhook" \
  -H "Content-Type: application/json" \
  -d '{"url": "https://your-tunnel-url.loca.lt/webhook", "secret_token": "QuizBotSecret123"}'
```

## Развёртывание на сервере

### Требования

- PHP 7.4 или выше
- Composer
- SQLite или MySQL/MariaDB
- Nginx или Apache
- SSL сертификат (для webhook)

### Шаги развёртывания

1. Клонируйте репозиторий:
```bash
git clone https://github.com/kosss26/hamster-syndicate.git
cd hamster-syndicate/bot
```

2. Установите зависимости:
```bash
composer install --no-dev --optimize-autoloader
```

3. Настройте окружение:
```bash
cp config/app.example.env config/app.env
nano config/app.env  # Укажите все необходимые переменные
```

4. Примените миграции:
```bash
composer migrate
composer seed
```

5. Настройте Nginx (пример конфигурации в `docs/`):
```nginx
server {
    listen 80;
    server_name your-domain.com;
    
    root /path/to/hamster-syndicate/bot/public;
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
}
```

6. Установите webhook:
```bash
curl -X POST "https://api.telegram.org/bot<TOKEN>/setWebhook" \
  -H "Content-Type: application/json" \
  -d '{"url": "https://your-domain.com/webhook", "secret_token": "your-secret-token"}'
```

7. Настройте права доступа:
```bash
chmod -R 755 /path/to/hamster-syndicate/bot
chown -R www-data:www-data /path/to/hamster-syndicate/bot
```

## Команды

- `composer start` - запуск локального сервера
- `composer migrate` - применение миграций БД
- `composer seed` - заполнение базы тестовыми данными
- `composer test` - запуск тестов

## Структура базы данных

- `users` - пользователи Telegram
- `user_profiles` - профили и статистика
- `categories` - категории вопросов
- `questions` - вопросы викторины
- `answers` - варианты ответов
- `story_chapters` - главы сюжета
- `story_steps` - шаги глав
- `story_progress` - прогресс пользователей
- `duels` - дуэли между игроками
- `duel_rounds` - раунды дуэлей
- `duel_results` - результаты дуэлей

## Лицензия

MIT

