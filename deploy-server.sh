#!/bin/bash
# Скрипт развёртывания на сервере tvixx.ru

set -e

SERVER="root@tvixx.ru"
DEPLOY_PATH="/var/www/quiz-bot"
PROJECT_DIR="/Users/evgeny/Desktop/Social"

echo "🚀 Развёртывание на сервер $SERVER..."

# Создаём директорию
ssh $SERVER "mkdir -p $DEPLOY_PATH"

# Копируем файлы
echo "📦 Копирование файлов..."
rsync -avz --exclude='vendor/' \
           --exclude='.git/' \
           --exclude='*.env' \
           --exclude='*.log' \
           --exclude='*.db' \
           --exclude='.DS_Store' \
           --exclude='node_modules/' \
           "$PROJECT_DIR/" "$SERVER:$DEPLOY_PATH/"

# Устанавливаем зависимости
echo "📥 Установка зависимостей..."
ssh $SERVER "cd $DEPLOY_PATH/bot && composer install --no-dev --optimize-autoloader"

# Настраиваем права
echo "🔐 Настройка прав..."
ssh $SERVER "chmod -R 755 $DEPLOY_PATH && chown -R www-data:www-data $DEPLOY_PATH"

# Применяем миграции
echo "🗄️ Применение миграций..."
ssh $SERVER "cd $DEPLOY_PATH/bot && composer migrate"

# Заполняем базу
echo "🌱 Заполнение базы данных..."
ssh $SERVER "cd $DEPLOY_PATH/bot && composer seed"

echo "✅ Развёртывание завершено!"
echo "📝 Не забудьте:"
echo "   1. Настроить config/app.env на сервере"
echo "   2. Установить webhook: curl -X POST \"https://api.telegram.org/bot<TOKEN>/setWebhook\" -d '{\"url\":\"https://tvixx.ru/webhook\",\"secret_token\":\"QuizBotSecret123\"}'"
echo "   3. Настроить Nginx (см. DEPLOY.md)"

