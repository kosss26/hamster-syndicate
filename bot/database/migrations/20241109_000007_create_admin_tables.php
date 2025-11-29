<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use QuizBot\Infrastructure\Database\Migration;

return new class implements Migration {
    public function name(): string
    {
        return '20241109_000007_create_admin_tables';
    }

    public function up(Builder $schema): void
    {
        $schema->create('admin_roles', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name', 191);
            $table->json('permissions')->nullable();
            $table->timestamps();
        });

        $schema->create('admin_users', function (Blueprint $table): void {
            $table->id();
            $table->string('email', 191)->unique();
            $table->string('password_hash', 255);
            $table->string('name', 191)->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        $schema->create('admin_role_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admin_user_id')->constrained('admin_users')->cascadeOnDelete();
            $table->foreignId('admin_role_id')->constrained('admin_roles')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['admin_user_id', 'admin_role_id']);
        });

        $schema->create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admin_user_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->string('action', 191);
            $table->string('entity_type', 128)->nullable();
            $table->string('entity_id', 128)->nullable();
            $table->json('payload')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }
};

