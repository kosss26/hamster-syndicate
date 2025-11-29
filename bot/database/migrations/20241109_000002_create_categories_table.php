<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use QuizBot\Infrastructure\Database\Migration;

return new class implements Migration {
    public function name(): string
    {
        return '20241109_000002_create_categories_table';
    }

    public function up(Builder $schema): void
    {
        $schema->create('categories', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('title', 191);
            $table->string('icon', 16)->nullable();
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('difficulty')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }
};

