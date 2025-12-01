<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use QuizBot\Infrastructure\Database\Migration;

return new class implements Migration {
    public function name(): string
    {
        return '20241201_000002_add_image_url_to_questions';
    }

    public function up(Builder $schema): void
    {
        $schema->table('questions', function (Blueprint $table): void {
            $table->string('image_url', 512)->nullable()->after('question_text');
        });
    }

    public function down(Builder $schema): void
    {
        $schema->table('questions', function (Blueprint $table): void {
            $table->dropColumn('image_url');
        });
    }
};

