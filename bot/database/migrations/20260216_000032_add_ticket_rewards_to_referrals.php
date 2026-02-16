<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use QuizBot\Infrastructure\Database\Migration;

return new class implements Migration {
    public function name(): string
    {
        return '20260216_000032_add_ticket_rewards_to_referrals';
    }

    public function up(Builder $schema): void
    {
        if ($schema->hasTable('referrals')) {
            $schema->table('referrals', function (Blueprint $table) use ($schema): void {
                if (!$schema->hasColumn('referrals', 'referrer_tickets_earned')) {
                    $table->unsignedInteger('referrer_tickets_earned')->default(0)->after('referrer_experience_earned');
                }
                if (!$schema->hasColumn('referrals', 'referred_tickets_earned')) {
                    $table->unsignedInteger('referred_tickets_earned')->default(0)->after('referred_experience_earned');
                }
            });
        }

        if ($schema->hasTable('referral_milestones')) {
            $schema->table('referral_milestones', function (Blueprint $table) use ($schema): void {
                if (!$schema->hasColumn('referral_milestones', 'reward_tickets')) {
                    $table->unsignedInteger('reward_tickets')->default(0)->after('reward_experience');
                }
            });
        }
    }
};

