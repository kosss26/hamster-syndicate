<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use QuizBot\Infrastructure\Database\Migration;

return new class implements Migration {
    public function name(): string
    {
        return '20241130_000001_create_story_questions_table';
    }

    public function up(Builder $schema): void
    {
        // Создаём таблицу для вопросов истории (отдельно от обычных вопросов)
        $schema->create('story_questions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('story_step_id')->constrained('story_steps')->cascadeOnDelete();
            $table->text('question_text'); // Текст вопроса в контексте истории
            $table->text('context_text')->nullable(); // Контекст (что игрок исследует)
            $table->text('explanation')->nullable(); // Объяснение правильного ответа
            $table->unsignedInteger('position')->default(0); // Порядок вопроса в шаге
            $table->timestamps();
            
            $table->index('story_step_id');
        });

        // Создаём таблицу для ответов на вопросы истории
        $schema->create('story_question_answers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('story_question_id')->constrained('story_questions')->cascadeOnDelete();
            $table->text('answer_text');
            $table->boolean('is_correct')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            
            $table->index('story_question_id');
        });
    }
};

