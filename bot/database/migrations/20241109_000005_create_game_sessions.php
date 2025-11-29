<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use QuizBot\Infrastructure\Database\Migration;

return new class implements Migration {
    public function name(): string
    {
        return '20241109_000005_create_game_sessions';
    }

    public function up(Builder $schema): void
    {
        $schema->create('game_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('mode', 32);
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('story_chapter_id')->nullable()->constrained('story_chapters')->nullOnDelete();
            $table->foreignId('current_question_id')->nullable()->constrained('questions')->nullOnDelete();
            $table->string('state', 32)->default('idle');
            $table->unsignedInteger('score')->default(0);
            $table->unsignedInteger('correct_count')->default(0);
            $table->unsignedInteger('incorrect_count')->default(0);
            $table->unsignedInteger('streak')->default(0);
            $table->json('payload')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'mode', 'state']);
        });
    }
};

