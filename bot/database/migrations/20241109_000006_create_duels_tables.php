<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use QuizBot\Infrastructure\Database\Migration;

return new class implements Migration {
    public function name(): string
    {
        return '20241109_000006_create_duels_tables';
    }

    public function up(Builder $schema): void
    {
        $schema->create('duels', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 64)->unique();
            $table->foreignId('initiator_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('opponent_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->unsignedTinyInteger('rounds_to_win')->default(3);
            $table->string('status', 32)->default('waiting');
            $table->json('settings')->nullable();
            $table->timestamp('matched_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        $schema->create('duel_rounds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('duel_id')->constrained('duels')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('questions')->cascadeOnDelete();
            $table->unsignedInteger('round_number');
            $table->unsignedInteger('time_limit')->default(30);
            $table->json('initiator_payload')->nullable();
            $table->json('opponent_payload')->nullable();
            $table->integer('initiator_score')->default(0);
            $table->integer('opponent_score')->default(0);
            $table->timestamp('question_sent_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->unique(['duel_id', 'round_number']);
        });

        $schema->create('duel_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('duel_id')->constrained('duels')->cascadeOnDelete();
            $table->foreignId('winner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->integer('initiator_total_score')->default(0);
            $table->integer('opponent_total_score')->default(0);
            $table->unsignedInteger('initiator_correct')->default(0);
            $table->unsignedInteger('opponent_correct')->default(0);
            $table->string('result', 32)->default('pending');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique('duel_id');
        });
    }
};

