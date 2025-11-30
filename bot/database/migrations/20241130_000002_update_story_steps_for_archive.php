<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use QuizBot\Infrastructure\Database\Migration;

return new class implements Migration {
    public function name(): string
    {
        return '20241130_000002_update_story_steps_for_archive';
    }

    public function up(Builder $schema): void
    {
        $driver = $schema->getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            // SQLite требует пересоздания таблицы
            $schema->getConnection()->statement('
                CREATE TABLE story_steps_new (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    chapter_id INTEGER NOT NULL,
                    code VARCHAR(64) NOT NULL UNIQUE,
                    step_type VARCHAR(32) NOT NULL DEFAULT "narrative",
                    narrative_text TEXT,
                    position INTEGER NOT NULL DEFAULT 0,
                    branch_key VARCHAR(64),
                    reward_points INTEGER NOT NULL DEFAULT 0,
                    penalty_points INTEGER NOT NULL DEFAULT 0,
                    transitions TEXT,
                    choice_options TEXT,
                    created_at TIMESTAMP,
                    updated_at TIMESTAMP,
                    FOREIGN KEY (chapter_id) REFERENCES story_chapters(id) ON DELETE CASCADE
                )
            ');

            // Копируем данные из старой таблицы
            $schema->getConnection()->statement('
                INSERT INTO story_steps_new 
                (id, chapter_id, code, step_type, narrative_text, position, branch_key, reward_points, penalty_points, transitions, choice_options, created_at, updated_at)
                SELECT 
                    id, 
                    chapter_id, 
                    code, 
                    "narrative" as step_type,
                    narrative_text, 
                    position, 
                    branch_key, 
                    reward_points, 
                    penalty_points, 
                    transitions, 
                    NULL as choice_options,
                    created_at, 
                    updated_at
                FROM story_steps
            ');

            // Удаляем старую таблицу и переименовываем новую
            $schema->drop('story_steps');
            $schema->getConnection()->statement('ALTER TABLE story_steps_new RENAME TO story_steps');

            // Создаём индексы
            $schema->getConnection()->statement('CREATE INDEX story_steps_chapter_id_index ON story_steps(chapter_id)');
        } else {
            // Для MySQL/MariaDB используем стандартный подход
            $schema->table('story_steps', function (Blueprint $table): void {
                // Удаляем внешний ключ и колонку question_id
                $table->dropForeign(['question_id']);
                $table->dropColumn('question_id');
                
                // Добавляем новые поля для интерактивных веток
                $table->string('step_type', 32)->default('narrative')->after('code');
                $table->json('choice_options')->nullable()->after('transitions');
            });
        }
    }
};
