<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use QuizBot\Infrastructure\Database\Migration;

return new class implements Migration {
    public function name(): string
    {
        return '20241109_000001_create_users_and_profiles';
    }

    public function up(Builder $schema): void
    {
        $schema->create('users', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('telegram_id')->unique();
            $table->string('username', 64)->nullable();
            $table->string('first_name', 128)->nullable();
            $table->string('last_name', 128)->nullable();
            $table->string('language_code', 8)->nullable();
            $table->timestamp('onboarded_at')->nullable();
            $table->timestamps();
            $table->index('telegram_id');
        });

        $schema->create('user_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('level')->default(1);
            $table->unsignedBigInteger('experience')->default(0);
            $table->unsignedInteger('coins')->default(0);
            $table->unsignedInteger('lives')->default(3);
            $table->unsignedInteger('streak_days')->default(0);
            $table->unsignedInteger('duel_wins')->default(0);
            $table->unsignedInteger('duel_losses')->default(0);
            $table->unsignedInteger('duel_draws')->default(0);
            $table->unsignedInteger('story_progress_score')->default(0);
            $table->json('settings')->nullable();
            $table->timestamps();
        });
    }
};

