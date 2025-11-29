<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use QuizBot\Infrastructure\Database\Migration;

return new class implements Migration {
    public function name(): string
    {
        return '20241109_000008_create_achievements_tables';
    }

    public function up(Builder $schema): void
    {
        $schema->create('achievements', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('title', 191);
            $table->text('description')->nullable();
            $table->unsignedInteger('points')->default(0);
            $table->json('conditions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $schema->create('user_achievements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('achievement_id')->constrained('achievements')->cascadeOnDelete();
            $table->timestamp('unlocked_at')->useCurrent();
            $table->json('context')->nullable();
            $table->unique(['user_id', 'achievement_id']);
        });
    }
};

