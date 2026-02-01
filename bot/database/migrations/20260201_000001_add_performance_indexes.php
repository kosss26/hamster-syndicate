<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use QuizBot\Infrastructure\Database\Migration;

return new class implements Migration {
    public function name(): string
    {
        return '20260201_000001_add_performance_indexes';
    }

    public function up(Builder $schema): void
    {
        // Оптимизация поиска дуэлей
        $schema->table('duels', function (Blueprint $table): void {
            // Быстрый поиск активных дуэлей конкретного игрока
            $table->index(['initiator_user_id', 'status'], 'idx_duels_initiator_status');
            $table->index(['opponent_user_id', 'status'], 'idx_duels_opponent_status');
            
            // Для матчмейкинга (ожидающие дуэли по категории)
            $table->index(['status', 'category_id', 'created_at'], 'idx_duels_matchmaking');
        });

        // Оптимизация лидербордов
        $schema->table('user_profiles', function (Blueprint $table): void {
            // Топ игроков по опыту, победам, монетам
            $table->index(['experience', 'id'], 'idx_profiles_experience_id'); // Compound with ID for stable sort
            $table->index(['duel_wins', 'id'], 'idx_profiles_duel_wins_id');
            $table->index(['coins', 'id'], 'idx_profiles_coins_id');
        });

        // Оптимизация статистики
        $schema->table('user_stats', function (Blueprint $table): void {
            $table->index(['correct_answers', 'id'], 'idx_stats_correct_answers_id');
            $table->index(['total_questions', 'id'], 'idx_stats_total_questions_id');
            // Для ежедневной активности (если last_activity_at используется для выборки активных)
            $table->index('last_activity_at'); 
        });

        // Оптимизация истории (если еще нет)
        if ($schema->hasTable('user_answer_history')) {
            $schema->table('user_answer_history', function (Blueprint $table): void {
                // Если нужно выбирать историю по режиму
                $table->index(['user_id', 'mode', 'created_at'], 'idx_history_user_mode_time');
            });
        }
    }
};
