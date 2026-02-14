#!/usr/bin/env php
<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use QuizBot\Bootstrap\AppBootstrap;

require __DIR__ . '/../vendor/autoload.php';

const DEFAULT_BATCH_SIZE = 1000;

/**
 * @return array<string, mixed>
 */
function parseOptions(array $argv): array
{
    $options = [
        'file' => null,
        'batch' => DEFAULT_BATCH_SIZE,
        'mode' => 'upsert',
        'format' => null,
        'dry-run' => false,
        'create-categories' => false,
    ];

    foreach ($argv as $arg) {
        if (strpos($arg, '--') !== 0) {
            continue;
        }

        if ($arg === '--dry-run') {
            $options['dry-run'] = true;
            continue;
        }

        if ($arg === '--create-categories') {
            $options['create-categories'] = true;
            continue;
        }

        $parts = explode('=', substr($arg, 2), 2);
        if (count($parts) !== 2) {
            continue;
        }

        [$key, $value] = $parts;

        switch ($key) {
            case 'file':
                $options['file'] = trim($value);
                break;
            case 'batch':
                $options['batch'] = max(1, (int) $value);
                break;
            case 'mode':
                $value = strtolower(trim($value));
                if (!in_array($value, ['upsert', 'insert-only'], true)) {
                    throw new RuntimeException("Неверный --mode={$value}. Допустимо: upsert, insert-only");
                }
                $options['mode'] = $value;
                break;
            case 'format':
                $value = strtolower(trim($value));
                if (!in_array($value, ['ndjson', 'json'], true)) {
                    throw new RuntimeException("Неверный --format={$value}. Допустимо: ndjson, json");
                }
                $options['format'] = $value;
                break;
            default:
                break;
        }
    }

    if (!$options['file']) {
        throw new RuntimeException('Укажите --file=/path/to/questions.ndjson');
    }

    return $options;
}

function printUsage(): void
{
    $usage = <<<'TXT'
Импорт вопросов в базу (bulk).

Пример:
  php bin/import_questions.php --file=storage/import/questions.ndjson --batch=1000 --mode=upsert

Опции:
  --file=...                Путь к файлу (ndjson/json)
  --batch=1000              Размер батча (по умолчанию 1000)
  --mode=upsert             upsert|insert-only (по умолчанию upsert)
  --format=ndjson           ndjson|json (если не указано, определяется по расширению)
  --dry-run                 Валидация без записи в БД
  --create-categories       Создавать категории, если не найдены
TXT;

    fwrite(STDOUT, $usage . PHP_EOL);
}

/**
 * @return iterable<int, array<string, mixed>>
 */
function iterateRecords(string $file, string $format): iterable
{
    if ($format === 'ndjson') {
        $handle = fopen($file, 'rb');
        if ($handle === false) {
            throw new RuntimeException("Не удалось открыть файл: {$file}");
        }

        $lineNo = 0;
        while (($line = fgets($handle)) !== false) {
            $lineNo++;
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0) {
                continue;
            }

            $decoded = json_decode($line, true);
            if (!is_array($decoded)) {
                throw new RuntimeException("Некорректный JSON в строке {$lineNo}");
            }

            $decoded['_line'] = $lineNo;
            yield $decoded;
        }

        fclose($handle);
        return;
    }

    $raw = file_get_contents($file);
    if ($raw === false) {
        throw new RuntimeException("Не удалось прочитать файл: {$file}");
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('JSON должен содержать массив объектов');
    }

    $records = $decoded;
    if (isset($decoded['questions']) && is_array($decoded['questions'])) {
        $records = $decoded['questions'];
    }

    $i = 0;
    foreach ($records as $record) {
        $i++;
        if (!is_array($record)) {
            throw new RuntimeException("Некорректный элемент в позиции {$i}");
        }
        $record['_line'] = $i;
        yield $record;
    }
}

/**
 * @return array{0:int,1:bool}
 */
function resolveCategoryId(array $record, array &$categoryCache, bool $createCategories): array
{
    if (isset($record['category_id']) && is_numeric($record['category_id'])) {
        return [(int) $record['category_id'], false];
    }

    $categoryCode = isset($record['category_code']) ? trim((string) $record['category_code']) : '';
    $categoryTitle = isset($record['category']) ? trim((string) $record['category']) : '';

    if ($categoryCode !== '') {
        $cacheKey = 'code:' . mb_strtolower($categoryCode);
        if (isset($categoryCache[$cacheKey])) {
            return [$categoryCache[$cacheKey], false];
        }

        $categoryId = Capsule::table('categories')->where('code', $categoryCode)->value('id');
        if ($categoryId) {
            $categoryCache[$cacheKey] = (int) $categoryId;
            return [(int) $categoryId, false];
        }
    }

    if ($categoryTitle === '') {
        throw new RuntimeException('Не указан category/category_code/category_id');
    }

    $cacheKey = 'title:' . mb_strtolower($categoryTitle);
    if (isset($categoryCache[$cacheKey])) {
        return [$categoryCache[$cacheKey], false];
    }

    $categoryId = Capsule::table('categories')->where('title', $categoryTitle)->value('id');
    if ($categoryId) {
        $categoryCache[$cacheKey] = (int) $categoryId;
        return [(int) $categoryId, false];
    }

    if (!$createCategories) {
        throw new RuntimeException("Категория не найдена: {$categoryTitle}");
    }

    $code = slugify($categoryTitle);
    $baseCode = $code;
    $suffix = 1;

    while (Capsule::table('categories')->where('code', $code)->exists()) {
        $suffix++;
        $code = $baseCode . '_' . $suffix;
    }

    $now = date('Y-m-d H:i:s');
    $categoryId = (int) Capsule::table('categories')->insertGetId([
        'code' => $code,
        'title' => $categoryTitle,
        'icon' => null,
        'description' => null,
        'difficulty' => 1,
        'is_active' => true,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $categoryCache[$cacheKey] = $categoryId;
    $categoryCache['code:' . mb_strtolower($code)] = $categoryId;

    return [$categoryId, true];
}

function slugify(string $value): string
{
    $value = mb_strtolower(trim($value));
    $value = preg_replace('/[^a-zа-я0-9]+/ui', '_', $value) ?? '';
    $value = trim($value, '_');

    if ($value === '') {
        return 'category_' . substr(md5((string) microtime(true)), 0, 8);
    }

    return $value;
}

function normalizeDifficulty($difficulty): int
{
    if (is_numeric($difficulty)) {
        $d = (int) $difficulty;
        return max(1, min(5, $d));
    }

    $value = mb_strtolower(trim((string) $difficulty));
    $map = [
        'easy' => 1,
        'medium' => 2,
        'hard' => 3,
        'expert' => 4,
        'nightmare' => 5,
    ];

    return $map[$value] ?? 1;
}

/**
 * @return array{question: array<string,mixed>, answers: array<int, array<string,mixed>>, created_category: bool}
 */
function normalizeRecord(array $record, array &$categoryCache, bool $createCategories): array
{
    [$categoryId, $createdCategory] = resolveCategoryId($record, $categoryCache, $createCategories);

    $questionText = trim((string) ($record['question'] ?? $record['question_text'] ?? ''));
    if ($questionText === '') {
        throw new RuntimeException('Пустой question/question_text');
    }

    $answersRaw = $record['answers'] ?? null;
    if (!is_array($answersRaw) || count($answersRaw) < 2) {
        throw new RuntimeException('У вопроса должно быть минимум 2 ответа');
    }

    $answers = [];
    $correctCount = 0;

    foreach ($answersRaw as $idx => $answerRaw) {
        if (!is_array($answerRaw)) {
            throw new RuntimeException("Ответ #{$idx} должен быть объектом");
        }

        $text = trim((string) ($answerRaw['text'] ?? $answerRaw['answer_text'] ?? ''));
        if ($text === '') {
            throw new RuntimeException("Пустой текст ответа #{$idx}");
        }

        $isCorrect = (bool) ($answerRaw['is_correct'] ?? false);
        if ($isCorrect) {
            $correctCount++;
        }

        $answers[] = [
            'answer_text' => $text,
            'is_correct' => $isCorrect,
            'score_delta' => isset($answerRaw['score_delta']) ? (int) $answerRaw['score_delta'] : 0,
        ];
    }

    if ($correctCount !== 1) {
        throw new RuntimeException("У вопроса должен быть ровно 1 правильный ответ (сейчас {$correctCount})");
    }

    $tags = $record['tags'] ?? null;
    if ($tags !== null && !is_array($tags)) {
        throw new RuntimeException('Поле tags должно быть массивом');
    }

    $question = [
        'external_id' => isset($record['external_id']) ? trim((string) $record['external_id']) : null,
        'category_id' => $categoryId,
        'type' => trim((string) ($record['type'] ?? 'multiple_choice')) ?: 'multiple_choice',
        'question_text' => $questionText,
        'image_url' => isset($record['image_url']) ? trim((string) $record['image_url']) : null,
        'explanation' => isset($record['explanation']) ? trim((string) $record['explanation']) : null,
        'difficulty' => normalizeDifficulty($record['difficulty'] ?? 1),
        'time_limit' => isset($record['time_limit']) ? max(5, (int) $record['time_limit']) : 30,
        'is_active' => !array_key_exists('is_active', $record) ? true : (bool) $record['is_active'],
        'tags' => $tags === null ? null : json_encode(array_values($tags), JSON_UNESCAPED_UNICODE),
    ];

    if ($question['external_id'] === '') {
        $question['external_id'] = null;
    }

    return [
        'question' => $question,
        'answers' => $answers,
        'created_category' => $createdCategory,
    ];
}

/**
 * @param array<int, array{question: array<string,mixed>, answers: array<int,array<string,mixed>>, created_category: bool}> $batch
 * @return array{processed:int, inserted:int, updated:int, skipped:int, categories_created:int}
 */
function processBatch(array $batch, string $mode, bool $dryRun): array
{
    $stats = [
        'processed' => count($batch),
        'inserted' => 0,
        'updated' => 0,
        'skipped' => 0,
        'categories_created' => 0,
    ];

    foreach ($batch as $item) {
        if ($item['created_category']) {
            $stats['categories_created']++;
        }
    }

    if ($dryRun || count($batch) === 0) {
        return $stats;
    }

    $now = date('Y-m-d H:i:s');

    Capsule::connection()->transaction(function () use ($batch, $mode, $now, &$stats): void {
        $upsertCandidates = [];
        $insertOnlyItems = [];

        foreach ($batch as $item) {
            $question = $item['question'];
            if ($mode === 'upsert' && !empty($question['external_id'])) {
                $upsertCandidates[] = $item;
                continue;
            }

            $insertOnlyItems[] = $item;
        }

        if (count($upsertCandidates) > 0) {
            $externalIds = [];
            $questionRows = [];

            foreach ($upsertCandidates as $item) {
                $row = $item['question'];
                $row['created_at'] = $now;
                $row['updated_at'] = $now;
                $externalIds[] = $row['external_id'];
                $questionRows[] = $row;
            }

            $existingIds = Capsule::table('questions')
                ->whereIn('external_id', $externalIds)
                ->pluck('id', 'external_id')
                ->toArray();

            $updateColumns = [
                'category_id',
                'type',
                'question_text',
                'image_url',
                'explanation',
                'difficulty',
                'time_limit',
                'is_active',
                'tags',
                'updated_at',
            ];

            Capsule::table('questions')->upsert($questionRows, ['external_id'], $updateColumns);

            $questionIdsByExternalId = Capsule::table('questions')
                ->whereIn('external_id', $externalIds)
                ->pluck('id', 'external_id')
                ->toArray();

            $allQuestionIds = array_values(array_map('intval', $questionIdsByExternalId));
            if (count($allQuestionIds) > 0) {
                Capsule::table('answers')->whereIn('question_id', $allQuestionIds)->delete();
            }

            $answerRows = [];
            foreach ($upsertCandidates as $item) {
                $externalId = $item['question']['external_id'];
                $questionId = (int) ($questionIdsByExternalId[$externalId] ?? 0);
                if ($questionId <= 0) {
                    continue;
                }

                foreach ($item['answers'] as $answer) {
                    $answerRows[] = [
                        'question_id' => $questionId,
                        'answer_text' => $answer['answer_text'],
                        'is_correct' => $answer['is_correct'],
                        'score_delta' => $answer['score_delta'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            if (count($answerRows) > 0) {
                Capsule::table('answers')->insert($answerRows);
            }

            foreach ($upsertCandidates as $item) {
                $eid = $item['question']['external_id'];
                if (isset($existingIds[$eid])) {
                    $stats['updated']++;
                } else {
                    $stats['inserted']++;
                }
            }
        }

        foreach ($insertOnlyItems as $item) {
            $questionRow = $item['question'];
            $questionRow['created_at'] = $now;
            $questionRow['updated_at'] = $now;

            if (!empty($questionRow['external_id'])) {
                $exists = Capsule::table('questions')->where('external_id', $questionRow['external_id'])->exists();
                if ($exists) {
                    $stats['skipped']++;
                    continue;
                }
            }

            $questionId = (int) Capsule::table('questions')->insertGetId($questionRow);

            $answersRows = [];
            foreach ($item['answers'] as $answer) {
                $answersRows[] = [
                    'question_id' => $questionId,
                    'answer_text' => $answer['answer_text'],
                    'is_correct' => $answer['is_correct'],
                    'score_delta' => $answer['score_delta'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            Capsule::table('answers')->insert($answersRows);
            $stats['inserted']++;
        }
    });

    return $stats;
}

function detectFormat(string $file, ?string $explicitFormat): string
{
    if ($explicitFormat !== null) {
        return $explicitFormat;
    }

    $ext = strtolower((string) pathinfo($file, PATHINFO_EXTENSION));
    if ($ext === 'ndjson' || $ext === 'jsonl') {
        return 'ndjson';
    }

    if ($ext === 'json') {
        return 'json';
    }

    throw new RuntimeException('Не удалось определить формат файла. Укажите --format=ndjson|json');
}

try {
    if (in_array('--help', $argv, true) || in_array('-h', $argv, true)) {
        printUsage();
        exit(0);
    }
    $options = parseOptions($argv);

    $file = (string) $options['file'];
    if (!is_file($file)) {
        throw new RuntimeException("Файл не найден: {$file}");
    }

    $format = detectFormat($file, $options['format']);

    $bootstrap = new AppBootstrap(dirname(__DIR__));
    $batchSize = (int) $options['batch'];
    $mode = (string) $options['mode'];
    $dryRun = (bool) $options['dry-run'];
    $createCategories = (bool) $options['create-categories'];

    $categoryCache = [];
    $batch = [];

    $summary = [
        'processed' => 0,
        'inserted' => 0,
        'updated' => 0,
        'skipped' => 0,
        'errors' => 0,
        'categories_created' => 0,
    ];

    $startedAt = microtime(true);

    foreach (iterateRecords($file, $format) as $record) {
        try {
            $normalized = normalizeRecord($record, $categoryCache, $createCategories);
            $batch[] = $normalized;
        } catch (Throwable $e) {
            $summary['errors']++;
            $line = isset($record['_line']) ? (int) $record['_line'] : -1;
            fwrite(STDERR, "[skip] line={$line}: {$e->getMessage()}" . PHP_EOL);
            continue;
        }

        if (count($batch) >= $batchSize) {
            $stats = processBatch($batch, $mode, $dryRun);
            foreach ($stats as $k => $v) {
                $summary[$k] += $v;
            }

            fwrite(STDOUT, sprintf(
                "[batch] processed=%d inserted=%d updated=%d skipped=%d errors=%d\n",
                $summary['processed'],
                $summary['inserted'],
                $summary['updated'],
                $summary['skipped'],
                $summary['errors']
            ));

            $batch = [];
        }
    }

    if (count($batch) > 0) {
        $stats = processBatch($batch, $mode, $dryRun);
        foreach ($stats as $k => $v) {
            $summary[$k] += $v;
        }
    }

    $duration = microtime(true) - $startedAt;

    fwrite(STDOUT, PHP_EOL . sprintf(
        "Готово. processed=%d inserted=%d updated=%d skipped=%d errors=%d categories_created=%d mode=%s dry_run=%s time=%.2fs\n",
        $summary['processed'],
        $summary['inserted'],
        $summary['updated'],
        $summary['skipped'],
        $summary['errors'],
        $summary['categories_created'],
        $mode,
        $dryRun ? 'yes' : 'no',
        $duration
    ));
} catch (Throwable $e) {
    fwrite(STDERR, "Ошибка импорта: {$e->getMessage()}" . PHP_EOL);
    printUsage();
    exit(1);
}
