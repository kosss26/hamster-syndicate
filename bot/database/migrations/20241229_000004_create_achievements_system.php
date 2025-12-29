<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use QuizBot\Infrastructure\Database\Migration;

return new class implements Migration {
    public function name(): string
    {
        return '20241229_000004_create_achievements_system';
    }

    public function up(Builder $schema): void
    {
        // Таблица достижений (справочник)
        $schema->create('achievements', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 100)->unique();
            $table->string('title');
            $table->text('description');
            $table->string('icon', 50);
            $table->string('rarity', 20)->default('common');
            $table->string('category', 50);
            $table->string('condition_type', 50);
            $table->integer('condition_value');
            $table->integer('reward_coins')->default(0);
            $table->integer('reward_gems')->default(0);
            $table->boolean('is_secret')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamp('created_at')->useCurrent();
            
            $table->index('category');
            $table->index('rarity');
        });

        // Таблица прогресса достижений игроков
        $schema->create('user_achievements', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('achievement_id');
            $table->integer('current_value')->default(0);
            $table->boolean('is_completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->boolean('is_showcased')->default(false);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('achievement_id')->references('id')->on('achievements')->onDelete('cascade');
            $table->unique(['user_id', 'achievement_id']);
            
            $table->index('user_id');
            $table->index('is_completed');
            $table->index('is_showcased');
        });

        // Таблица коллекций
        $schema->create('collections', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 100)->unique();
            $table->string('title');
            $table->text('description');
            $table->string('icon', 50);
            $table->integer('total_items')->default(0);
            $table->string('rarity', 20)->default('common');
            $table->integer('reward_coins')->default(0);
            $table->integer('reward_gems')->default(0);
            $table->timestamp('created_at')->useCurrent();
        });

        // Таблица элементов коллекций
        $schema->create('collection_items', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('collection_id');
            $table->string('key', 100)->unique();
            $table->string('name');
            $table->text('description');
            $table->string('image_url')->nullable();
            $table->string('rarity', 20)->default('common');
            $table->decimal('drop_chance', 5, 4)->default(0.1);
            $table->integer('sort_order')->default(0);
            $table->timestamp('created_at')->useCurrent();
            
            $table->foreign('collection_id')->references('id')->on('collections')->onDelete('cascade');
            $table->index('collection_id');
        });

        // Таблица собранных карточек
        $schema->create('user_collection_items', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('collection_item_id');
            $table->timestamp('obtained_at')->useCurrent();
            $table->string('obtained_from', 50)->nullable();
            
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('collection_item_id')->references('id')->on('collection_items')->onDelete('cascade');
            $table->unique(['user_id', 'collection_item_id']);
            
            $table->index('user_id');
        });

        // Таблица статистики для трекинга
        $schema->create('achievement_stats', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('stat_key', 100);
            $table->integer('stat_value')->default(0);
            $table->timestamp('last_updated')->useCurrent();
            
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['user_id', 'stat_key']);
            
            $table->index('user_id');
            $table->index('stat_key');
        });
    }

    public function down(Builder $schema): void
    {
        $schema->dropIfExists('achievement_stats');
        $schema->dropIfExists('user_collection_items');
        $schema->dropIfExists('collection_items');
        $schema->dropIfExists('collections');
        $schema->dropIfExists('user_achievements');
        $schema->dropIfExists('achievements');
    }

    public function getDescription(): string
    {
        return 'Создание системы достижений и коллекций';
    }
};
