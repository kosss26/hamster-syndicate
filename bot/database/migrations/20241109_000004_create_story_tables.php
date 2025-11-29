<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use QuizBot\Infrastructure\Database\Migration;

return new class implements Migration {
    public function name(): string
    {
        return '20241109_000004_create_story_tables';
    }

    public function up(Builder $schema): void
    {
        $schema->create('story_chapters', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('title', 191);
            $table->text('description')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        $schema->create('story_steps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('chapter_id')->constrained('story_chapters')->cascadeOnDelete();
            $table->foreignId('question_id')->nullable()->constrained('questions')->nullOnDelete();
            $table->string('code', 64)->unique();
            $table->text('narrative_text')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->string('branch_key', 64)->nullable();
            $table->integer('reward_points')->default(0);
            $table->integer('penalty_points')->default(0);
            $table->json('transitions')->nullable();
            $table->timestamps();
        });

        $schema->create('story_progress', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('chapter_id')->constrained('story_chapters')->cascadeOnDelete();
            $table->foreignId('current_step_id')->nullable()->constrained('story_steps')->nullOnDelete();
            $table->string('status', 32)->default('in_progress');
            $table->unsignedInteger('score')->default(0);
            $table->unsignedInteger('lives_remaining')->default(3);
            $table->unsignedInteger('mistakes')->default(0);
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'chapter_id']);
        });
    }
};

