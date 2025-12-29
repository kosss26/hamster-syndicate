# 🚀 Деплой реферальной системы на сервер

## 📦 Что было добавлено:
- ✅ Реферальная система с наградами
- ✅ Страница рефералов в Mini App
- ✅ API endpoint для статистики
- ✅ Команда /referral в боте
- ✅ Миграции и milestone награды

---

## 🔧 Команды для обновления сервера:

### Вариант 1: Полное обновление (рекомендуется)

```bash
# Подключаемся к серверу
ssh your_server

# Переходим в директорию проекта
cd /path/to/your/project

# Получаем изменения с GitHub
git pull origin main

# Обновляем зависимости PHP (если добавлялись новые)
cd bot
composer install --no-dev --optimize-autoloader

# Применяем миграции БД (создает таблицы реферальной системы)
# Миграция уже применена локально, но на сервере нужно через PHP
php -r "
require 'vendor/autoload.php';
use QuizBot\Bootstrap\AppBootstrap;
\$app = new AppBootstrap(dirname(__DIR__) . '/bot');
\$container = \$app->getContainer();

// Создаём таблицы вручную
\$db = \$container->get(Illuminate\Database\Capsule\Manager::class);

// Добавляем поля в user_profiles
try {
    \$db->schema()->table('user_profiles', function (\$table) {
        \$table->string('referral_code', 12)->unique()->nullable();
        \$table->foreignId('referred_by_user_id')->nullable()->constrained('users')->nullOnDelete();
        \$table->boolean('referral_rewards_claimed')->default(false);
        \$table->unsignedInteger('total_referrals')->default(0);
    });
} catch (\Exception \$e) {
    // Уже существуют
}

// Создаём таблицу referrals
\$db->schema()->create('referrals', function (\$table) {
    \$table->id();
    \$table->foreignId('referrer_user_id')->constrained('users')->cascadeOnDelete();
    \$table->foreignId('referred_user_id')->constrained('users')->cascadeOnDelete();
    \$table->string('referral_code', 12);
    \$table->string('status', 32)->default('pending');
    \$table->unsignedInteger('referrer_coins_earned')->default(0);
    \$table->unsignedInteger('referrer_experience_earned')->default(0);
    \$table->unsignedInteger('referred_coins_earned')->default(0);
    \$table->unsignedInteger('referred_experience_earned')->default(0);
    \$table->boolean('referred_completed_onboarding')->default(false);
    \$table->unsignedInteger('referred_games_played')->default(0);
    \$table->timestamp('activated_at')->nullable();
    \$table->timestamp('rewarded_at')->nullable();
    \$table->timestamps();
    \$table->unique(['referrer_user_id', 'referred_user_id']);
});

// Создаём таблицу referral_milestones
\$db->schema()->create('referral_milestones', function (\$table) {
    \$table->id();
    \$table->unsignedInteger('referrals_count');
    \$table->string('title', 255);
    \$table->text('description')->nullable();
    \$table->unsignedInteger('reward_coins')->default(0);
    \$table->unsignedInteger('reward_experience')->default(0);
    \$table->string('reward_badge', 64)->nullable();
    \$table->boolean('is_active')->default(true);
    \$table->timestamps();
    \$table->unique('referrals_count');
});

// Создаём таблицу user_referral_milestones
\$db->schema()->create('user_referral_milestones', function (\$table) {
    \$table->id();
    \$table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
    \$table->foreignId('milestone_id')->constrained('referral_milestones')->cascadeOnDelete();
    \$table->timestamp('claimed_at')->useCurrent();
    \$table->unique(['user_id', 'milestone_id']);
});

echo 'Миграции применены успешно!';
"

# Добавляем milestone награды
composer seed

# Собираем Mini App (если есть изменения в webapp)
cd ../webapp
npm install
npm run build

# Возвращаемся в корень
cd ..

# Перезапускаем PHP-FPM (если нужно)
sudo systemctl restart php-fpm

# Перезапускаем Nginx
sudo systemctl restart nginx

echo "✅ Деплой завершён!"
```

---

### Вариант 2: Упрощённые команды

```bash
# SSH на сервер
ssh your_server

# Обновление кода
cd /path/to/project && git pull origin main

# Применение миграций через SQL (если MySQL)
mysql -u root -p quiz_bot < /path/to/migration.sql

# Или через SQLite
sqlite3 /path/to/database.sqlite < /path/to/migration.sql

# Пересборка Mini App
cd webapp && npm run build

# Перезапуск сервисов
sudo systemctl restart php-fpm nginx
```

---

### Вариант 3: SQL миграция напрямую (MySQL)

```sql
-- Подключаемся к БД
mysql -u root -p quiz_bot

-- Выполняем миграцию
ALTER TABLE user_profiles 
ADD COLUMN referral_code VARCHAR(12) DEFAULT NULL,
ADD COLUMN referred_by_user_id BIGINT DEFAULT NULL,
ADD COLUMN referral_rewards_claimed BOOLEAN DEFAULT 0,
ADD COLUMN total_referrals INT DEFAULT 0,
ADD UNIQUE KEY (referral_code),
ADD KEY (referred_by_user_id),
ADD FOREIGN KEY (referred_by_user_id) REFERENCES users(id) ON DELETE SET NULL;

CREATE TABLE referrals (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    referrer_user_id BIGINT NOT NULL,
    referred_user_id BIGINT NOT NULL,
    referral_code VARCHAR(12) NOT NULL,
    status VARCHAR(32) DEFAULT 'pending',
    referrer_coins_earned INT DEFAULT 0,
    referrer_experience_earned INT DEFAULT 0,
    referred_coins_earned INT DEFAULT 0,
    referred_experience_earned INT DEFAULT 0,
    referred_completed_onboarding BOOLEAN DEFAULT 0,
    referred_games_played INT DEFAULT 0,
    activated_at TIMESTAMP NULL,
    rewarded_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY (referrer_user_id, referred_user_id),
    KEY (referrer_user_id, status),
    KEY (referred_user_id),
    FOREIGN KEY (referrer_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (referred_user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE referral_milestones (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    referrals_count INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    reward_coins INT DEFAULT 0,
    reward_experience INT DEFAULT 0,
    reward_badge VARCHAR(64),
    is_active BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY (referrals_count)
);

CREATE TABLE user_referral_milestones (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT NOT NULL,
    milestone_id BIGINT NOT NULL,
    claimed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (user_id, milestone_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (milestone_id) REFERENCES referral_milestones(id) ON DELETE CASCADE
);

-- Заполняем milestone награды
INSERT INTO referral_milestones (referrals_count, title, description, reward_coins, reward_experience, reward_badge, is_active)
VALUES
(1, '🌟 Первый друг', 'Пригласил первого друга', 50, 25, '🌟', 1),
(5, '🎯 Наставник', 'Пригласил 5 друзей', 200, 100, '🎯', 1),
(10, '🏅 Рекрутер', 'Пригласил 10 друзей', 500, 250, '🏅', 1),
(25, '👑 Король рефералов', 'Пригласил 25 друзей', 1500, 750, '👑', 1),
(50, '⭐ Легенда', 'Пригласил 50 друзей', 5000, 2500, '⭐', 1),
(100, '🌌 Мастер вселенной', 'Пригласил 100 друзей', 15000, 7500, '🌌', 1);
```

---

### Вариант 4: SQLite (если используется SQLite)

```bash
ssh your_server
cd /path/to/project/bot

sqlite3 storage/database/database.sqlite <<'EOF'
-- Добавляем поля в user_profiles
ALTER TABLE user_profiles ADD COLUMN referral_code VARCHAR(12) DEFAULT NULL;
ALTER TABLE user_profiles ADD COLUMN referred_by_user_id INTEGER DEFAULT NULL;
ALTER TABLE user_profiles ADD COLUMN referral_rewards_claimed BOOLEAN DEFAULT 0;
ALTER TABLE user_profiles ADD COLUMN total_referrals INTEGER DEFAULT 0;

CREATE UNIQUE INDEX user_profiles_referral_code_unique ON user_profiles(referral_code);
CREATE INDEX user_profiles_referred_by_user_id_index ON user_profiles(referred_by_user_id);

-- Создаем таблицу referrals
CREATE TABLE referrals (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    referrer_user_id INTEGER NOT NULL,
    referred_user_id INTEGER NOT NULL,
    referral_code VARCHAR(12) NOT NULL,
    status VARCHAR(32) DEFAULT 'pending',
    referrer_coins_earned INTEGER DEFAULT 0,
    referrer_experience_earned INTEGER DEFAULT 0,
    referred_coins_earned INTEGER DEFAULT 0,
    referred_experience_earned INTEGER DEFAULT 0,
    referred_completed_onboarding BOOLEAN DEFAULT 0,
    referred_games_played INTEGER DEFAULT 0,
    activated_at DATETIME DEFAULT NULL,
    rewarded_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT NULL,
    updated_at DATETIME DEFAULT NULL,
    FOREIGN KEY (referrer_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (referred_user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE UNIQUE INDEX referrals_referrer_referred_unique ON referrals(referrer_user_id, referred_user_id);
CREATE INDEX referrals_referrer_status_index ON referrals(referrer_user_id, status);
CREATE INDEX referrals_referred_user_id_index ON referrals(referred_user_id);

-- Создаем таблицу referral_milestones
CREATE TABLE referral_milestones (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    referrals_count INTEGER NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    reward_coins INTEGER DEFAULT 0,
    reward_experience INTEGER DEFAULT 0,
    reward_badge VARCHAR(64) DEFAULT NULL,
    is_active BOOLEAN DEFAULT 1,
    created_at DATETIME DEFAULT NULL,
    updated_at DATETIME DEFAULT NULL
);

CREATE UNIQUE INDEX referral_milestones_referrals_count_unique ON referral_milestones(referrals_count);

-- Создаем таблицу user_referral_milestones
CREATE TABLE user_referral_milestones (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    milestone_id INTEGER NOT NULL,
    claimed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (milestone_id) REFERENCES referral_milestones(id) ON DELETE CASCADE
);

CREATE UNIQUE INDEX user_referral_milestones_user_milestone_unique ON user_referral_milestones(user_id, milestone_id);

-- Заполняем milestone награды
INSERT INTO referral_milestones (referrals_count, title, description, reward_coins, reward_experience, reward_badge, is_active, created_at, updated_at)
VALUES
(1, '🌟 Первый друг', 'Пригласил первого друга', 50, 25, '🌟', 1, datetime('now'), datetime('now')),
(5, '🎯 Наставник', 'Пригласил 5 друзей', 200, 100, '🎯', 1, datetime('now'), datetime('now')),
(10, '🏅 Рекрутер', 'Пригласил 10 друзей', 500, 250, '🏅', 1, datetime('now'), datetime('now')),
(25, '👑 Король рефералов', 'Пригласил 25 друзей', 1500, 750, '👑', 1, datetime('now'), datetime('now')),
(50, '⭐ Легенда', 'Пригласил 50 друзей', 5000, 2500, '⭐', 1, datetime('now'), datetime('now')),
(100, '🌌 Мастер вселенной', 'Пригласил 100 друзей', 15000, 7500, '🌌', 1, datetime('now'), datetime('now'));
EOF

echo "✅ SQLite миграция завершена!"
```

---

## ⚙️ Настройка конфига

```bash
# Отредактировать config/app.env на сервере
nano bot/config/app.env

# Добавить/проверить:
TELEGRAM_BOT_USERNAME=duelquizbot
```

---

## 🧪 Проверка после деплоя

```bash
# 1. Проверить что таблицы созданы
sqlite3 bot/storage/database/database.sqlite "SELECT COUNT(*) FROM referral_milestones;"
# Должно вернуть: 6

# 2. Проверить API endpoint
curl -X GET "https://your-domain.com/api/referral/stats" \
  -H "X-Telegram-Init-Data: user={\"id\":123}"

# 3. Проверить Mini App
# Открыть в браузере: https://your-domain.com/webapp/referral

# 4. Проверить команду в боте
# Написать боту: /referral
```

---

## 🎮 Тест функционала

1. **В боте:**
   ```
   /referral
   ```
   → Должно показать код и кнопки

2. **В Mini App:**
   - Открыть главную страницу
   - Нажать "🎁 Пригласить"
   - Проверить все кнопки работают

3. **Тест реферала:**
   - Скопировать ссылку
   - Открыть в другом аккаунте
   - Проверить начисление 50 монет
   - Сыграть 3 игры
   - Проверить начисление 100 монет рефереру

---

## 🔍 Логи для отладки

```bash
# Смотреть логи PHP
tail -f bot/storage/logs/app.log | grep -i referral

# Смотреть логи Nginx
tail -f /var/log/nginx/error.log

# Смотреть ошибки PHP-FPM
tail -f /var/log/php-fpm/error.log
```

---

## 📝 Откат (если что-то пошло не так)

```bash
# Откат git
git reset --hard HEAD~1

# Откат миграций (удалить таблицы)
sqlite3 database.sqlite "DROP TABLE IF EXISTS user_referral_milestones;"
sqlite3 database.sqlite "DROP TABLE IF EXISTS referral_milestones;"
sqlite3 database.sqlite "DROP TABLE IF EXISTS referrals;"

# Откат изменений в user_profiles (сложнее, лучше восстановить из бэкапа)
```

---

## ✅ Готово!

После выполнения команд реферальная система будет работать на сервере! 🎉

