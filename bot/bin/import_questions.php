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

Примечание:
  Дубликаты определяются по хешу: нормализованный вопрос + правильный ответ.
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

function normalizeImportText(string $value): string
{
    $value = str_replace('ё', 'е', mb_strtolower(trim($value)));
    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
    $value = preg_replace('/[!?.,;:…]+$/u', '', $value) ?? $value;

    return trim($value);
}

/**
 * @param array<int, array<string,mixed>> $answers
 */
function buildQuestionContentHash(string $questionText, array $answers): string
{
    $correctAnswerText = '';

    foreach ($answers as $answer) {
        if (!empty($answer['is_correct'])) {
            $correctAnswerText = (string) ($answer['answer_text'] ?? '');
            break;
        }
    }

    return sha1(
        normalizeImportText($questionText) . '|' . normalizeImportText($correctAnswerText)
    );
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
        'content_hash' => buildQuestionContentHash($questionText, $answers),
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
 * @param array<string, bool> $seenHashes
 * @return array{processed:int, inserted:int, updated:int, skipped:int, categories_created:int, skipped_duplicates:int}
 */
function processBatch(array $batch, string $mode, bool $dryRun, array &$seenHashes): array
{
    $hasContentHash = Capsule::schema()->hasColumn('questions', 'content_hash');
    $stats = [
        'processed' => count($batch),
        'inserted' => 0,
        'updated' => 0,
        'skipped' => 0,
        'categories_created' => 0,
        'skipped_duplicates' => 0,
    ];

    foreach ($batch as $item) {
        if ($item['created_category']) {
            $stats['categories_created']++;
        }
    }

    if (count($batch) === 0) {
        return $stats;
    }

    $hashes = [];
    $externalIds = [];

    foreach ($batch as $item) {
        $hash = $hasContentHash ? (string) ($item['question']['content_hash'] ?? '') : '';
        if ($hash !== '') {
            $hashes[] = $hash;
        }
        $externalId = (string) ($item['question']['external_id'] ?? '');
        if ($externalId !== '') {
            $externalIds[] = $externalId;
        }
    }

    $existingByHash = $hasContentHash && count($hashes) > 0
        ? Capsule::table('questions')->whereIn('content_hash', array_values(array_unique($hashes)))->pluck('id', 'content_hash')->toArray()
        : [];
    $existingByExternalId = count($externalIds) > 0
        ? Capsule::table('questions')->whereIn('external_id', array_values(array_unique($externalIds)))->pluck('id', 'external_id')->toArray()
        : [];

    $worker = function () use ($batch, $mode, $dryRun, &$stats, &$seenHashes, &$existingByHash, $existingByExternalId, $hasContentHash): void {
        $now = date('Y-m-d H:i:s');

        foreach ($batch as $item) {
            $question = $item['question'];
            $answers = $item['answers'];
            $hash = $hasContentHash ? (string) ($question['content_hash'] ?? '') : '';
            $externalId = (string) ($question['external_id'] ?? '');
            $existingQuestionId = $externalId !== '' ? (int) ($existingByExternalId[$externalId] ?? 0) : 0;

            if ($hash !== '' && isset($seenHashes[$hash])) {
                $stats['skipped']++;
                $stats['skipped_duplicates']++;
                continue;
            }

            if ($hash !== '' && isset($existingByHash[$hash])) {
                $hashOwnerId = (int) $existingByHash[$hash];
                if ($existingQuestionId <= 0 || $hashOwnerId !== $existingQuestionId) {
                    $stats['skipped']++;
                    $stats['skipped_duplicates']++;
                    continue;
                }
            }

            if ($mode === 'insert-only' && $externalId !== '' && $existingQuestionId > 0) {
                $stats['skipped']++;
                continue;
            }

            if ($dryRun) {
                if ($existingQuestionId > 0 && $mode === 'upsert') {
                    $stats['updated']++;
                } else {
                    $stats['inserted']++;
                }
                if ($hash !== '') {
                    $seenHashes[$hash] = true;
                }
                continue;
            }

            $questionRow = $question;
            if (!$hasContentHash) {
                unset($questionRow['content_hash']);
            }
            $questionRow['updated_at'] = $now;

            if ($existingQuestionId > 0 && $mode === 'upsert') {
                $questionUpdateRow = $questionRow;
                unset($questionUpdateRow['external_id']);

                Capsule::table('questions')
                    ->where('id', $existingQuestionId)
                    ->update($questionUpdateRow);

                Capsule::table('answers')
                    ->where('question_id', $existingQuestionId)
                    ->delete();

                $questionId = $existingQuestionId;
                $stats['updated']++;
            } else {
                $questionRow['created_at'] = $now;
                $questionId = (int) Capsule::table('questions')->insertGetId($questionRow);
                $stats['inserted']++;
            }

            $answerRows = [];
            foreach ($answers as $answer) {
                $answerRows[] = [
                    'question_id' => $questionId,
                    'answer_text' => $answer['answer_text'],
                    'is_correct' => $answer['is_correct'],
                    'score_delta' => $answer['score_delta'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if (count($answerRows) > 0) {
                Capsule::table('answers')->insert($answerRows);
            }

            if ($hash !== '') {
                $seenHashes[$hash] = true;
                $existingByHash[$hash] = $questionId;
            }
        }
    };

    if ($dryRun) {
        $worker();
    } else {
        Capsule::connection()->transaction($worker);
    }

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
        'skipped_duplicates' => 0,
        'errors' => 0,
        'categories_created' => 0,
    ];
    $seenHashes = [];

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
            $stats = processBatch($batch, $mode, $dryRun, $seenHashes);
            foreach ($stats as $k => $v) {
                $summary[$k] += $v;
            }

            fwrite(STDOUT, sprintf(
                "[batch] processed=%d inserted=%d updated=%d skipped=%d dup=%d errors=%d\n",
                $summary['processed'],
                $summary['inserted'],
                $summary['updated'],
                $summary['skipped'],
                $summary['skipped_duplicates'],
                $summary['errors']
            ));

            $batch = [];
        }
    }

    if (count($batch) > 0) {
        $stats = processBatch($batch, $mode, $dryRun, $seenHashes);
        foreach ($stats as $k => $v) {
            $summary[$k] += $v;
        }
    }

    $duration = microtime(true) - $startedAt;

    fwrite(STDOUT, PHP_EOL . sprintf(
        "Готово. processed=%d inserted=%d updated=%d skipped=%d skipped_duplicates=%d errors=%d categories_created=%d mode=%s dry_run=%s time=%.2fs\n",
        $summary['processed'],
        $summary['inserted'],
        $summary['updated'],
        $summary['skipped'],
        $summary['skipped_duplicates'],
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
