<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use QuizBot\Infrastructure\Database\Migration;

return new class implements Migration {
    public function name(): string
    {
        return '20260215_000027_add_runtime_indexes_for_duels_and_truefalse';
    }

    public function up(Builder $schema): void
    {
        if ($schema->hasTable('duels')) {
            $schema->table('duels', function (Blueprint $table): void {
                $table->index(
                    ['initiator_user_id', 'status', 'created_at'],
                    'duels_initiator_status_created_idx'
                );
                $table->index(
                    ['opponent_user_id', 'status', 'created_at'],
                    'duels_opponent_status_created_idx'
                );
                $table->index(
                    ['status', 'opponent_user_id', 'created_at'],
                    'duels_status_opponent_created_idx'
                );
                $table->index(
                    ['status', 'matched_at'],
                    'duels_status_matched_at_idx'
                );
            });
        }

        if ($schema->hasTable('duel_rounds')) {
            $schema->table('duel_rounds', function (Blueprint $table): void {
                $table->index(
                    ['duel_id', 'closed_at', 'round_number'],
                    'duel_rounds_duel_closed_round_idx'
                );
                $table->index(
                    ['duel_id', 'question_sent_at'],
                    'duel_rounds_duel_question_sent_idx'
                );
            });
        }

        if ($schema->hasTable('true_false_facts')) {
            $schema->table('true_false_facts', function (Blueprint $table): void {
                $table->index(
                    ['is_active', 'is_true'],
                    'true_false_facts_active_truth_idx'
                );
            });
        }
    }
};

