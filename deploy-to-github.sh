#!/bin/bash

# Скрипт для развёртывания проекта на GitHub
# Использование: ./deploy-to-github.sh

set -e

echo "🚀 Начинаем развёртывание на GitHub..."

# Цвета для вывода
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

REPO_URL="https://github.com/kosss26/hamster-syndicate.git"
PROJECT_DIR="/Users/evgeny/Desktop/Social"

cd "$PROJECT_DIR"

# Проверяем, инициализирован ли git
if [ ! -d ".git" ]; then
    echo -e "${YELLOW}Инициализируем git репозиторий...${NC}"
    git init
fi

# Проверяем наличие remote
if ! git remote get-url origin &>/dev/null; then
    echo -e "${YELLOW}Добавляем remote origin...${NC}"
    git remote add origin "$REPO_URL"
else
    echo -e "${YELLOW}Обновляем remote origin...${NC}"
    git remote set-url origin "$REPO_URL"
fi

# Проверяем статус
echo -e "${YELLOW}Проверяем статус репозитория...${NC}"
git status

# Добавляем все файлы
echo -e "${YELLOW}Добавляем файлы...${NC}"
git add .

# Создаём коммит
echo -e "${YELLOW}Создаём коммит...${NC}"
git commit -m "Initial commit: Telegram Quiz Bot - Путешествие знаний" || echo "Нет изменений для коммита"

# Спрашиваем о force push
echo -e "${YELLOW}Внимание! Это заменит всё содержимое репозитория на GitHub.${NC}"
read -p "Продолжить? (y/n): " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    echo -e "${GREEN}Отправляем на GitHub...${NC}"
    git push -f origin main || git push -f origin master
    echo -e "${GREEN}✅ Проект успешно развёрнут на GitHub!${NC}"
    echo -e "${GREEN}Репозиторий: $REPO_URL${NC}"
else
    echo -e "${RED}Отменено пользователем.${NC}"
    exit 1
fi

