<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use QuizBot\Infrastructure\Database\Migration;

return new class implements Migration {
    public function name(): string
    {
        return '20260201_000003_create_teams_system';
    }

    public function up(Builder $schema): void
    {
        $schema->create('teams', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 50)->unique();
            $table->string('tag', 5)->unique(); // [TAG] Name
            $table->text('description')->nullable();
            $table->foreignId('owner_id')->constrained('users');
            $table->boolean('is_open')->default(true);
            $table->unsignedInteger('min_rating')->default(0);
            $table->unsignedBigInteger('score')->default(0); // Сумма очков участников или отдельный счет
            $table->unsignedInteger('members_count')->default(1);
            $table->timestamps();
            
            $table->index('score'); // Для топа кланов
        });

        $schema->create('team_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete(); // Юзер может быть только в одной команде
            $table->string('role', 20)->default('member'); // owner, admin, member
            $table->unsignedBigInteger('contribution')->default(0); // Вклад в очки клана
            $table->timestamp('joined_at')->useCurrent();
            
            $table->index(['team_id', 'role']);
        });
    }
};
