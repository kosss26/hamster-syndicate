# Quiz Bot Mini App

Telegram Mini App для игры "Битва знаний" — красивый и быстрый интерфейс для дуэлей и режима "Правда или ложь".

## 🚀 Быстрый старт

### Локальная разработка

```bash
cd webapp

# Установка зависимостей
npm install

# Запуск dev-сервера
npm run dev
```

Приложение будет доступно на http://localhost:3000

### Сборка для продакшена

```bash
npm run build
```

Собранные файлы будут в папке `dist/`.

## 🔧 Развёртывание на сервере

### 1. Сборка приложения

```bash
cd webapp
npm install
npm run build
```

### 2. Копирование на сервер

Скопируйте содержимое папки `dist/` на сервер, например в `/var/www/quiz-webapp/`.

### 3. Настройка Nginx

```nginx
server {
    listen 443 ssl http2;
    server_name webapp.yourdomain.com;

    ssl_certificate /path/to/ssl/cert.pem;
    ssl_certificate_key /path/to/ssl/key.pem;

    root /var/www/quiz-webapp;
    index index.html;

    # SPA routing
    location / {
        try_files $uri $uri/ /index.html;
    }

    # API proxy (если Mini App и бот на одном сервере)
    location /api {
        proxy_pass http://127.0.0.1:80/api;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
    }

    # Кэширование статики
    location ~* \.(js|css|png|jpg|jpeg|gif|svg|ico|woff|woff2)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
```

### 4. Настройка бота

В файле `bot/config/app.env` добавьте URL Mini App:

```env
WEBAPP_URL=https://webapp.yourdomain.com
```

После этого в боте появится кнопка "🎮 Играть", которая открывает Mini App.

## 🚀 Развёртывание на Vercel

Если нужен самый простой вариант, деплойте только `webapp/`:

1. В Vercel создайте новый Project из этого репозитория.
2. Укажите `Root Directory = webapp`.
3. Оставьте `Build Command = npm run build`, `Output Directory = dist`.
4. Добавьте переменные окружения:
   - `VITE_API_URL=https://your-api-domain.com/api`
   - `VITE_WS_URL=wss://your-api-domain.com/ws` если нужен WebSocket
5. После деплоя укажите URL Vercel-страницы в боте как `WEBAPP_URL`.

Для SPA-роутинга уже добавлен `webapp/vercel.json`, поэтому прямые переходы на `/profile`, `/duel/123` и т.п. будут работать.

## 📁 Структура проекта

```
webapp/
├── src/
│   ├── api/           # API клиент
│   ├── assets/        # Статические ресурсы
│   ├── components/    # React компоненты
│   ├── hooks/         # Хуки (useTelegram)
│   ├── pages/         # Страницы
│   │   ├── HomePage.jsx
│   │   ├── DuelPage.jsx
│   │   ├── ProfilePage.jsx
│   │   └── LeaderboardPage.jsx
│   ├── App.jsx        # Главный компонент
│   ├── index.css      # Стили (Tailwind)
│   └── main.jsx       # Точка входа
├── index.html
├── package.json
├── vite.config.js
├── tailwind.config.js
└── postcss.config.js
```

## 🎨 Стилизация

Проект использует:
- **Tailwind CSS** — утилитарный CSS-фреймворк
- **Framer Motion** — анимации
- **Telegram Theme Variables** — автоматическая подстройка под тему Telegram

Цвета из Telegram автоматически подхватываются через CSS-переменные:
- `var(--tg-theme-bg-color)` — цвет фона
- `var(--tg-theme-text-color)` — цвет текста
- `var(--tg-theme-button-color)` — цвет кнопок
- и другие...

## 🔗 API

Mini App общается с PHP бэкендом через `/api`:

| Метод | Эндпоинт | Описание |
|-------|----------|----------|
| GET | `/api/user` | Текущий пользователь |
| GET | `/api/profile` | Профиль с рейтингом |
| POST | `/api/duel/create` | Создать дуэль |
| GET | `/api/duel/:id` | Информация о дуэли |
| POST | `/api/duel/answer` | Ответить на вопрос |
| GET | `/api/truefalse/question` | Получить факт |
| POST | `/api/truefalse/answer` | Ответить на факт |
| GET | `/api/leaderboard` | Рейтинг игроков |

Авторизация происходит через заголовок `X-Telegram-Init-Data`, который автоматически передаётся из Telegram.

## 🔒 Безопасность

- Все запросы верифицируются через `initData` от Telegram
- API не доступен без валидной подписи
- Для разработки используется моковый пользователь (только при `APP_ENV=development`)

## 📱 Особенности Telegram Mini App

- **Haptic Feedback** — вибрация при нажатиях
- **BackButton** — системная кнопка "Назад"
- **Theme** — автоподстройка под тему пользователя
- **Expand** — приложение раскрывается на весь экран
