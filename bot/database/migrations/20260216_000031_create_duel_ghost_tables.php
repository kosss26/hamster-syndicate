<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use QuizBot\Infrastructure\Database\Migration;

return new class implements Migration {
    public function name(): string
    {
        return '20260216_000031_create_duel_ghost_tables';
    }

    public function up(Builder $schema): void
    {
        if (!$schema->hasTable('duel_ghost_snapshots')) {
            $schema->create('duel_ghost_snapshots', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('source_duel_id');
                $table->unsignedBigInteger('source_user_id');
                $table->integer('source_rating')->default(1000);
                $table->unsignedInteger('question_count')->default(0);
                $table->unsignedInteger('quality_score')->default(0);
                $table->json('rounds_payload');
                $table->timestamp('created_at')->useCurrent();

                $table->index(['source_user_id', 'created_at'], 'duel_ghost_snapshots_source_user_created_idx');
                $table->index(['source_rating', 'created_at'], 'duel_ghost_snapshots_rating_created_idx');
                $table->index(['quality_score', 'created_at'], 'duel_ghost_snapshots_quality_created_idx');
            });
        }

        if (!$schema->hasTable('duel_ghost_plays')) {
            $schema->create('duel_ghost_plays', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('snapshot_id');
                $table->unsignedBigInteger('duel_id')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->unique(['user_id', 'snapshot_id'], 'duel_ghost_plays_user_snapshot_uniq');
                $table->index(['user_id', 'created_at'], 'duel_ghost_plays_user_created_idx');
                $table->index(['snapshot_id', 'created_at'], 'duel_ghost_plays_snapshot_created_idx');
            });
        }
    }
};

