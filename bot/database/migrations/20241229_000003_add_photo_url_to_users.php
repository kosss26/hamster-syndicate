<?php

use QuizBot\Database\Migration;
use Illuminate\Database\Capsule\Manager as Capsule;

return new class implements Migration
{
    public function up(): void
    {
        $schema = Capsule::schema();

        // Добавляем поле для хранения URL фото профиля из Telegram
        $schema->table('users', function ($table) {
            $table->string('photo_url', 512)->nullable()->after('username');
        });
    }

    public function down(): void
    {
        $schema = Capsule::schema();

        $schema->table('users', function ($table) {
            $table->dropColumn('photo_url');
        });
    }
};

