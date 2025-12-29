<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use QuizBot\Infrastructure\Database\Migration;

return new class implements Migration {
    public function name(): string
    {
        return '20241229_000003_add_photo_url_to_users';
    }

    public function up(Builder $schema): void
    {
        // Добавляем поле для хранения URL фото профиля из Telegram
        $schema->table('users', function (Blueprint $table): void {
            $table->string('photo_url', 512)->nullable()->after('language_code');
        });
    }
};

