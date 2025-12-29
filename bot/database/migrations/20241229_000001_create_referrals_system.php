<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use QuizBot\Infrastructure\Database\Migration;

return new class implements Migration {
    public function name(): string
    {
        return '20241229_000001_create_referrals_system';
    }

    public function up(Builder $schema): void
    {
        // Добавляем поля реферальной системы в user_profiles
        $schema->table('user_profiles', function (Blueprint $table): void {
            $table->string('referral_code', 12)->unique()->nullable()->after('coins');
            $table->foreignId('referred_by_user_id')->nullable()
                ->constrained('users')->nullOnDelete()->after('referral_code');
            $table->boolean('referral_rewards_claimed')->default(false)->after('referred_by_user_id');
            $table->unsignedInteger('total_referrals')->default(0)->after('referral_rewards_claimed');
            
            $table->index('referral_code');
            $table->index('referred_by_user_id');
        });

        // Таблица рефералов с детальной информацией
        $schema->create('referrals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('referrer_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('referred_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('referral_code', 12);
            $table->string('status', 32)->default('pending'); // pending, active, rewarded
            
            // Награды
            $table->unsignedInteger('referrer_coins_earned')->default(0);
            $table->unsignedInteger('referrer_experience_earned')->default(0);
            $table->unsignedInteger('referred_coins_earned')->default(0);
            $table->unsignedInteger('referred_experience_earned')->default(0);
            
            // Условия активации
            $table->boolean('referred_completed_onboarding')->default(false);
            $table->unsignedInteger('referred_games_played')->default(0);
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('rewarded_at')->nullable();
            
            $table->timestamps();
            
            $table->unique(['referrer_user_id', 'referred_user_id']);
            $table->index(['referrer_user_id', 'status']);
            $table->index(['referred_user_id']);
        });

        // Таблица наград за количество рефералов (milestone rewards)
        $schema->create('referral_milestones', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('referrals_count'); // 5, 10, 25, 50, 100
            $table->string('title', 255); // "Наставник", "Рекрутер", "Легенда"
            $table->text('description')->nullable();
            $table->unsignedInteger('reward_coins')->default(0);
            $table->unsignedInteger('reward_experience')->default(0);
            $table->string('reward_badge', 64)->nullable(); // emoji или код достижения
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->unique('referrals_count');
        });

        // Прогресс пользователя по milestone'ам
        $schema->create('user_referral_milestones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('milestone_id')->constrained('referral_milestones')->cascadeOnDelete();
            $table->timestamp('claimed_at')->useCurrent();
            
            $table->unique(['user_id', 'milestone_id']);
        });
    }
};

