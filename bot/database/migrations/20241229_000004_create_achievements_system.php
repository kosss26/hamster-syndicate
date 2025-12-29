<?php

use QuizBot\Infrastructure\Database\Migration;

return new class implements Migration
{
    public function up(\PDO $pdo): void
    {
        // Таблица достижений (справочник)
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS achievements (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                key TEXT NOT NULL UNIQUE,
                title TEXT NOT NULL,
                description TEXT NOT NULL,
                icon TEXT NOT NULL,
                rarity TEXT NOT NULL DEFAULT 'common',
                category TEXT NOT NULL,
                condition_type TEXT NOT NULL,
                condition_value INTEGER NOT NULL,
                reward_coins INTEGER DEFAULT 0,
                reward_gems INTEGER DEFAULT 0,
                is_secret INTEGER DEFAULT 0,
                sort_order INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Таблица прогресса достижений игроков
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS user_achievements (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                achievement_id INTEGER NOT NULL,
                current_value INTEGER DEFAULT 0,
                is_completed INTEGER DEFAULT 0,
                completed_at DATETIME,
                is_showcased INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (achievement_id) REFERENCES achievements(id) ON DELETE CASCADE,
                UNIQUE(user_id, achievement_id)
            )
        ");

        // Таблица коллекций
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS collections (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                key TEXT NOT NULL UNIQUE,
                title TEXT NOT NULL,
                description TEXT NOT NULL,
                icon TEXT NOT NULL,
                total_items INTEGER DEFAULT 0,
                rarity TEXT NOT NULL DEFAULT 'common',
                reward_coins INTEGER DEFAULT 0,
                reward_gems INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Таблица элементов коллекций
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS collection_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                collection_id INTEGER NOT NULL,
                key TEXT NOT NULL UNIQUE,
                name TEXT NOT NULL,
                description TEXT NOT NULL,
                image_url TEXT,
                rarity TEXT NOT NULL DEFAULT 'common',
                drop_chance REAL DEFAULT 0.1,
                sort_order INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (collection_id) REFERENCES collections(id) ON DELETE CASCADE
            )
        ");

        // Таблица собранных карточек
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS user_collection_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                collection_item_id INTEGER NOT NULL,
                obtained_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                obtained_from TEXT,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (collection_item_id) REFERENCES collection_items(id) ON DELETE CASCADE,
                UNIQUE(user_id, collection_item_id)
            )
        ");

        // Таблица статистики для трекинга
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS achievement_stats (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                stat_key TEXT NOT NULL,
                stat_value INTEGER DEFAULT 0,
                last_updated DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                UNIQUE(user_id, stat_key)
            )
        ");

        // Индексы для производительности
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_achievements_category ON achievements(category)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_achievements_rarity ON achievements(rarity)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_user_achievements_user ON user_achievements(user_id)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_user_achievements_completed ON user_achievements(is_completed)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_user_achievements_showcased ON user_achievements(is_showcased)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_collection_items_collection ON collection_items(collection_id)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_user_collection_items_user ON user_collection_items(user_id)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_achievement_stats_user ON achievement_stats(user_id)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_achievement_stats_key ON achievement_stats(stat_key)");
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec("DROP TABLE IF EXISTS achievement_stats");
        $pdo->exec("DROP TABLE IF EXISTS user_collection_items");
        $pdo->exec("DROP TABLE IF EXISTS collection_items");
        $pdo->exec("DROP TABLE IF EXISTS collections");
        $pdo->exec("DROP TABLE IF EXISTS user_achievements");
        $pdo->exec("DROP TABLE IF EXISTS achievements");
    }

    public function getDescription(): string
    {
        return 'Создание системы достижений и коллекций';
    }
};

