<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use QuizBot\Infrastructure\Database\Migration;

return new class implements Migration {
    public function name(): string
    {
        return '20241109_000003_create_questions_and_answers';
    }

    public function up(Builder $schema): void
    {
        $schema->create('questions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->string('type', 32)->default('multiple_choice');
            $table->text('question_text');
            $table->text('explanation')->nullable();
            $table->unsignedTinyInteger('difficulty')->default(1);
            $table->unsignedSmallInteger('time_limit')->default(30);
            $table->boolean('is_active')->default(true);
            $table->json('tags')->nullable();
            $table->timestamps();
            $table->index(['category_id', 'difficulty']);
        });

        $schema->create('answers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('question_id')->constrained('questions')->cascadeOnDelete();
            $table->text('answer_text');
            $table->boolean('is_correct')->default(false);
            $table->integer('score_delta')->default(0);
            $table->timestamps();
        });
    }
};

