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
        // Удаляем старую связь с questions (будет использоваться story_questions)
        $schema->table('story_steps', function (Blueprint $table): void {
            // Удаляем внешний ключ и колонку question_id
            $table->dropForeign(['question_id']);
            $table->dropColumn('question_id');
            
            // Добавляем новые поля для интерактивных веток
            $table->string('step_type', 32)->default('narrative')->after('code'); // narrative, question, choice
            $table->json('choice_options')->nullable()->after('transitions'); // Опции выбора для интерактивных веток
        });
    }
};

