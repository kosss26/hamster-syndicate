<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use QuizBot\Infrastructure\Database\Migration;

return new class implements Migration {
    public function name(): string
    {
        return '20241203_000001_create_true_false_facts_table';
    }

    public function up(Builder $schema): void
    {
        if (!$schema->hasTable('true_false_facts')) {
            $schema->create('true_false_facts', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->string('statement', 500);
                $table->text('explanation')->nullable();
                $table->boolean('is_true')->default(true);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if ($schema->hasTable('user_profiles') && !$schema->hasColumn('user_profiles', 'true_false_record')) {
            $schema->table('user_profiles', function (Blueprint $table): void {
                $table->integer('true_false_record')->default(0)->after('story_progress_score');
            });
        }
    }

    public function down(Builder $schema): void
    {
        if ($schema->hasTable('true_false_facts')) {
            $schema->drop('true_false_facts');
        }

        if ($schema->hasTable('user_profiles') && $schema->hasColumn('user_profiles', 'true_false_record')) {
            $schema->table('user_profiles', function (Blueprint $table): void {
                $table->dropColumn('true_false_record');
            });
        }
    }
};


