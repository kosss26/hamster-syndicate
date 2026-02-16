<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use QuizBot\Infrastructure\Database\Migration;

return new class extends Migration {
    public function getName(): string
    {
        return '20260216_000030_create_admin_notifications_table';
    }

    public function up(Builder $schema): void
    {
        if ($schema->hasTable('admin_notifications')) {
            return;
        }

        $schema->create('admin_notifications', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 160);
            $table->text('message');
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['is_active', 'created_at']);
        });
    }

    public function down(Builder $schema): void
    {
        $schema->dropIfExists('admin_notifications');
    }
};

