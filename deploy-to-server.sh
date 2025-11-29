#!/bin/bash

# Скрипт для развёртывания проекта на сервере
# Использование: ./deploy-to-server.sh [user@host] [path]

set -e

# Цвета для вывода
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

if [ -z "$1" ] || [ -z "$2" ]; then
    echo -e "${RED}Использование: ./deploy-to-server.sh user@host /path/to/deploy${NC}"
    echo "Пример: ./deploy-to-server.sh root@tvixx.ru /var/www/quiz-bot"
    exit 1
fi

SERVER="$1"
DEPLOY_PATH="$2"
PROJECT_DIR="/Users/evgeny/Desktop/Social"

echo -e "${YELLOW}🚀 Развёртывание на сервер $SERVER...${NC}"

# Создаём директорию на сервере
echo -e "${YELLOW}Создаём директорию на сервере...${NC}"
ssh "$SERVER" "mkdir -p $DEPLOY_PATH"

# Копируем файлы (исключая vendor, .env и т.д.)
echo -e "${YELLOW}Копируем файлы проекта...${NC}"
rsync -avz --exclude='vendor/' \
           --exclude='.git/' \
           --exclude='*.env' \
           --exclude='*.log' \
           --exclude='*.db' \
           --exclude='.DS_Store' \
           --exclude='node_modules/' \
           "$PROJECT_DIR/" "$SERVER:$DEPLOY_PATH/"

# Устанавливаем зависимости на сервере
echo -e "${YELLOW}Устанавливаем зависимости на сервере...${NC}"
ssh "$SERVER" "cd $DEPLOY_PATH/bot && composer install --no-dev --optimize-autoloader"

# Настраиваем права
echo -e "${YELLOW}Настраиваем права доступа...${NC}"
ssh "$SERVER" "chmod -R 755 $DEPLOY_PATH && chown -R www-data:www-data $DEPLOY_PATH"

# Применяем миграции (если нужно)
echo -e "${YELLOW}Применить миграции? (y/n)${NC}"
read -p "" -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    ssh "$SERVER" "cd $DEPLOY_PATH/bot && composer migrate"
fi

echo -e "${GREEN}✅ Проект успешно развёрнут на сервере!${NC}"
echo -e "${GREEN}Путь: $SERVER:$DEPLOY_PATH${NC}"

