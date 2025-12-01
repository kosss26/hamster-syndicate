<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use QuizBot\Infrastructure\Database\Migration;

return new class implements Migration {
    public function name(): string
    {
        return '20241201_000001_add_rating_to_profiles';
    }

    public function up(Builder $schema): void
    {
        $schema->table('user_profiles', function (Blueprint $table): void {
            $table->integer('rating')->default(0)->after('experience');
        });
    }

    public function down(Builder $schema): void
    {
        $schema->table('user_profiles', function (Blueprint $table): void {
            $table->dropColumn('rating');
        });
    }
};

