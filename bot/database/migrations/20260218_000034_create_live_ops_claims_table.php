<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use QuizBot\Infrastructure\Database\Migration;

return new class implements Migration {
    public function name(): string
    {
        return '20260218_000034_create_live_ops_claims_table';
    }

    public function up(Builder $schema): void
    {
        if ($schema->hasTable('user_live_ops_claims')) {
            return;
        }

        $schema->create('user_live_ops_claims', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('claim_key', 191);
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();

            $table->unique(['user_id', 'claim_key'], 'uniq_live_ops_claim_user_key');
            $table->index(['claim_key'], 'idx_live_ops_claim_key');
            $table->index(['created_at'], 'idx_live_ops_claim_created');
        });
    }
};
