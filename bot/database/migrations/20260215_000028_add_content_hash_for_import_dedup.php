<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use QuizBot\Infrastructure\Database\Migration;

return new class implements Migration {
    public function name(): string
    {
        return '20260215_000028_add_content_hash_for_import_dedup';
    }

    public function up(Builder $schema): void
    {
        if ($schema->hasTable('questions') && !$schema->hasColumn('questions', 'content_hash')) {
            $schema->table('questions', function (Blueprint $table): void {
                $table->string('content_hash', 40)->nullable()->after('external_id');
                $table->index('content_hash', 'questions_content_hash_idx');
            });
        }

        if ($schema->hasTable('true_false_facts') && !$schema->hasColumn('true_false_facts', 'content_hash')) {
            $schema->table('true_false_facts', function (Blueprint $table): void {
                $table->string('content_hash', 40)->nullable()->after('statement');
                $table->index('content_hash', 'true_false_facts_content_hash_idx');
            });
        }

        $this->backfillQuestionHashes($schema);
        $this->backfillFactHashes($schema);
    }

    private function backfillQuestionHashes(Builder $schema): void
    {
        if (!$schema->hasTable('questions') || !$schema->hasColumn('questions', 'content_hash')) {
            return;
        }

        Capsule::table('questions')
            ->select(['id', 'question_text'])
            ->orderBy('id')
            ->chunk(500, function ($questions): void {
                foreach ($questions as $question) {
                    $questionId = (int) ($question->id ?? 0);
                    if ($questionId <= 0) {
                        continue;
                    }

                    $currentHash = Capsule::table('questions')->where('id', $questionId)->value('content_hash');
                    if (is_string($currentHash) && $currentHash !== '') {
                        continue;
                    }

                    $correctAnswer = Capsule::table('answers')
                        ->where('question_id', $questionId)
                        ->where('is_correct', true)
                        ->orderBy('id')
                        ->value('answer_text');

                    if (!is_string($correctAnswer) || trim($correctAnswer) === '') {
                        continue;
                    }

                    $hash = sha1(
                        $this->normalizeImportText((string) ($question->question_text ?? ''))
                        . '|'
                        . $this->normalizeImportText($correctAnswer)
                    );

                    Capsule::table('questions')
                        ->where('id', $questionId)
                        ->update(['content_hash' => $hash]);
                }
            });
    }

    private function backfillFactHashes(Builder $schema): void
    {
        if (!$schema->hasTable('true_false_facts') || !$schema->hasColumn('true_false_facts', 'content_hash')) {
            return;
        }

        Capsule::table('true_false_facts')
            ->select(['id', 'statement'])
            ->orderBy('id')
            ->chunk(500, function ($facts): void {
                foreach ($facts as $fact) {
                    $factId = (int) ($fact->id ?? 0);
                    if ($factId <= 0) {
                        continue;
                    }

                    $currentHash = Capsule::table('true_false_facts')->where('id', $factId)->value('content_hash');
                    if (is_string($currentHash) && $currentHash !== '') {
                        continue;
                    }

                    $statement = (string) ($fact->statement ?? '');
                    if (trim($statement) === '') {
                        continue;
                    }

                    $hash = sha1($this->normalizeImportText($statement));

                    Capsule::table('true_false_facts')
                        ->where('id', $factId)
                        ->update(['content_hash' => $hash]);
                }
            });
    }

    private function normalizeImportText(string $value): string
    {
        $value = str_replace('ё', 'е', mb_strtolower(trim($value)));
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        $value = preg_replace('/[!?.,;:…]+$/u', '', $value) ?? $value;

        return trim($value);
    }
};
