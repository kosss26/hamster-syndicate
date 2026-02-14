<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use QuizBot\Infrastructure\Database\Migration;

return new class implements Migration {
    public function name(): string
    {
        return '20260214_000026_add_external_id_to_questions';
    }

    public function up(Builder $schema): void
    {
        if (!$schema->hasTable('questions')) {
            return;
        }

        if (!$schema->hasColumn('questions', 'external_id')) {
            $schema->table('questions', function (Blueprint $table): void {
                $table->string('external_id', 128)->nullable();
            });
        }

        // Пробуем создать уникальный индекс; если уже есть — игнорируем.
        try {
            $schema->table('questions', function (Blueprint $table): void {
                $table->unique('external_id', 'questions_external_id_unique');
            });
        } catch (Throwable $e) {
            // no-op
        }
    }
};
