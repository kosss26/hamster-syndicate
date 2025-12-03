<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use QuizBot\Infrastructure\Database\Migration;

return new class implements Migration {
    public function name(): string
    {
        return '20241203_000002_create_user_category_stats';
    }

    public function up(Builder $schema): void
    {
        // Статистика по категориям для каждого пользователя
        $schema->create('user_category_stats', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->unsignedInteger('total_questions')->default(0);
            $table->unsignedInteger('correct_answers')->default(0);
            $table->unsignedInteger('total_time_ms')->default(0); // Суммарное время ответов в мс
            $table->unsignedInteger('best_streak')->default(0);
            $table->timestamps();
            
            $table->unique(['user_id', 'category_id']);
            $table->index(['user_id']);
        });

        // Общая статистика пользователя (расширенная)
        $schema->create('user_stats', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('total_questions')->default(0);
            $table->unsignedInteger('correct_answers')->default(0);
            $table->unsignedInteger('total_time_ms')->default(0);
            $table->unsignedInteger('best_overall_streak')->default(0);
            $table->unsignedInteger('current_streak')->default(0);
            $table->unsignedInteger('games_played')->default(0);
            $table->unsignedInteger('best_duel_win_streak')->default(0);
            $table->json('answers_by_day')->nullable(); // {"Mon": {total: 10, correct: 8}, ...}
            $table->json('answers_by_hour')->nullable(); // {"10": {total: 5, correct: 4}, ...}
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();
        });

        // История ответов (для детальной аналитики, последние N ответов)
        $schema->create('user_answer_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('question_id')->nullable()->constrained('questions')->nullOnDelete();
            $table->boolean('is_correct');
            $table->unsignedInteger('time_ms'); // Время ответа в миллисекундах
            $table->string('mode', 32); // duel, story, free, true_false
            $table->timestamp('created_at')->useCurrent();
            
            $table->index(['user_id', 'created_at']);
            $table->index(['user_id', 'category_id']);
        });
    }
};

