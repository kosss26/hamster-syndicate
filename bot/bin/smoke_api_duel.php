#!/usr/bin/env php
<?php

declare(strict_types=1);

if (PHP_VERSION_ID < 80100) {
    fwrite(STDERR, "Smoke API duel requires PHP >= 8.1. Current: " . PHP_VERSION . PHP_EOL);
    exit(1);
}

use QuizBot\Application\Services\UserService;
use QuizBot\Application\Services\DuelService;
use QuizBot\Bootstrap\AppBootstrap;
use QuizBot\Domain\Model\Duel;
use QuizBot\Domain\Model\DuelGhostSnapshot;
use QuizBot\Domain\Model\DuelRound;
use QuizBot\Domain\Model\Question;
use QuizBot\Infrastructure\Config\Config;
use Illuminate\Support\Carbon;

require __DIR__ . '/../vendor/autoload.php';

$basePath = dirname(__DIR__);
$bootstrap = new AppBootstrap($basePath);
$container = $bootstrap->getContainer();

/** @var Config $config */
$config = $container->get(Config::class);
/** @var UserService $userService */
$userService = $container->get(UserService::class);
/** @var DuelService $duelService */
$duelService = $container->get(DuelService::class);

$argvAssoc = [];
foreach ($argv as $arg) {
    if (!str_starts_with($arg, '--')) {
        continue;
    }

    $eqPos = strpos($arg, '=');
    if ($eqPos === false) {
        $argvAssoc[substr($arg, 2)] = '1';
        continue;
    }

    $key = substr($arg, 2, $eqPos - 2);
    $value = substr($arg, $eqPos + 1);
    $argvAssoc[$key] = $value;
}

$insecure = ($argvAssoc['insecure'] ?? '0') === '1';

$baseUrl = trim((string) ($argvAssoc['base-url'] ?? ''));
if ($baseUrl === '') {
    $webappUrl = rtrim((string) $config->get('WEBAPP_URL', ''), '/');
    if ($webappUrl !== '' && str_ends_with($webappUrl, '/webapp')) {
        $baseUrl = substr($webappUrl, 0, -7);
    } else {
        $baseUrl = $webappUrl;
    }
}

if ($baseUrl === '') {
    fwrite(STDERR, "Provide --base-url or set WEBAPP_URL in env.\n");
    exit(1);
}

$baseUrl = rtrim($baseUrl, '/');
$apiBase = $baseUrl . '/api';

$seed = random_int(1000, 9999);
$userAId = (int) ($argvAssoc['user-a'] ?? (92000000 + $seed));
$userBId = (int) ($argvAssoc['user-b'] ?? (93000000 + $seed));

$errors = [];

function out(string $message): void
{
    fwrite(STDOUT, $message . PHP_EOL);
}

function fail(string $message, array &$errors): void
{
    $errors[] = $message;
    fwrite(STDERR, "[FAIL] {$message}" . PHP_EOL);
}

function ok(string $message): void
{
    fwrite(STDOUT, "[OK] {$message}" . PHP_EOL);
}

function assertTrue(bool $condition, string $message, array &$errors): void
{
    if ($condition) {
        ok($message);
        return;
    }

    fail($message, $errors);
}

/**
 * @return array{status:int,raw:string,json:?array}
 */
function httpJsonRequest(string $method, string $url, array $headers = [], ?array $body = null, bool $insecure = false): array
{
    $headerLines = [];
    foreach ($headers as $name => $value) {
        $headerLines[] = $name . ': ' . $value;
    }

    if ($body !== null) {
        $headerLines[] = 'Content-Type: application/json';
    }

    $httpOptions = [
        'method' => strtoupper($method),
        'header' => implode("\r\n", $headerLines),
        'ignore_errors' => true,
        'timeout' => 20,
    ];

    if ($body !== null) {
        $httpOptions['content'] = json_encode($body, JSON_UNESCAPED_UNICODE);
    }

    $options = ['http' => $httpOptions];
    if (str_starts_with($url, 'https://')) {
        $options['ssl'] = [
            'verify_peer' => !$insecure,
            'verify_peer_name' => !$insecure,
        ];
    }

    $context = stream_context_create($options);
    $raw = @file_get_contents($url, false, $context);
    $raw = is_string($raw) ? $raw : '';

    $status = 0;
    $responseHeaders = $http_response_header ?? [];
    if (isset($responseHeaders[0]) && preg_match('/\s(\d{3})\s/', $responseHeaders[0], $m)) {
        $status = (int) $m[1];
    }

    $json = json_decode($raw, true);

    return [
        'status' => $status,
        'raw' => $raw,
        'json' => is_array($json) ? $json : null,
    ];
}

/**
 * @return array{status:int,raw:string,json:?array}
 */
function apiCall(string $apiBase, int $telegramUserId, string $method, string $path, ?array $body = null, bool $insecure = false): array
{
    $headers = [
        'X-Dev-Telegram-User-Id' => (string) $telegramUserId,
    ];

    return httpJsonRequest($method, $apiBase . $path, $headers, $body, $insecure);
}

function isSuccessResponse(array $response): bool
{
    return ($response['status'] >= 200 && $response['status'] < 300)
        && is_array($response['json'])
        && (($response['json']['success'] ?? false) === true)
        && is_array($response['json']['data'] ?? null);
}

/**
 * @return array<string,mixed>
 */
function responseData(array $response): array
{
    $json = $response['json'];
    if (!is_array($json)) {
        return [];
    }

    $data = $json['data'] ?? null;
    return is_array($data) ? $data : [];
}

function cleanupActiveDuels(int $userAId, int $userBId): void
{
    Duel::query()
        ->where(function ($q) use ($userAId, $userBId): void {
            $q->whereIn('initiator_user_id', [$userAId, $userBId])
                ->orWhereIn('opponent_user_id', [$userAId, $userBId]);
        })
        ->whereIn('status', ['waiting', 'matched', 'in_progress'])
        ->update([
            'status' => 'cancelled',
            'finished_at' => Carbon::now(),
        ]);
}

function waitForSharedActiveDuel(string $apiBase, int $userAId, int $userBId, int $timeoutSeconds, bool $insecure, array &$errors): ?int
{
    $meta = waitForSharedActiveDuelMeta($apiBase, $userAId, $userBId, $timeoutSeconds, $insecure);
    if ($meta === null) {
        fail('Users did not converge to the same active duel in time', $errors);
        return null;
    }

    return (int) ($meta['duel_id'] ?? 0);
}

/**
 * @return array{duel_id:int,elapsed_ms:int}|null
 */
function waitForSharedActiveDuelMeta(string $apiBase, int $userAId, int $userBId, int $timeoutSeconds, bool $insecure): ?array
{
    $start = microtime(true);
    while ((microtime(true) - $start) < $timeoutSeconds) {
        $a = apiCall($apiBase, $userAId, 'GET', '/duel/current', null, $insecure);
        $b = apiCall($apiBase, $userBId, 'GET', '/duel/current', null, $insecure);

        if (isSuccessResponse($a) && isSuccessResponse($b)) {
            $da = responseData($a);
            $db = responseData($b);
            $idA = (int) ($da['duel_id'] ?? 0);
            $idB = (int) ($db['duel_id'] ?? 0);
            if ($idA > 0 && $idA === $idB) {
                return [
                    'duel_id' => $idA,
                    'elapsed_ms' => (int) round((microtime(true) - $start) * 1000),
                ];
            }
        }

        usleep(500_000);
    }

    return null;
}

function waitForRoundProgress(string $apiBase, int $userId, int $duelId, int $timeoutSeconds, bool $insecure): bool
{
    $start = time();
    while ((time() - $start) < $timeoutSeconds) {
        $state = apiCall($apiBase, $userId, 'GET', '/duel/' . $duelId, null, $insecure);
        if (isSuccessResponse($state)) {
            $data = responseData($state);
            $currentRound = (int) ($data['current_round'] ?? 0);
            $lastClosedRound = is_array($data['last_closed_round'] ?? null) ? $data['last_closed_round'] : [];
            $lastClosedNumber = (int) ($lastClosedRound['round_number'] ?? 0);
            $roundStatus = is_array($data['round_status'] ?? null) ? $data['round_status'] : [];
            $roundClosed = (($roundStatus['round_closed'] ?? false) === true);

            if ($roundClosed || $lastClosedNumber >= 1 || $currentRound >= 2 || ($data['status'] ?? '') === 'finished') {
                return true;
            }
        }

        usleep(500_000);
    }

    return false;
}

/**
 * @return array{ok:bool,round_id:int,my_answer_id:int}
 */
function waitForMyAnsweredOpponentPending(string $apiBase, int $userId, int $duelId, int $timeoutSeconds, bool $insecure): array
{
    $start = time();
    while ((time() - $start) < $timeoutSeconds) {
        $state = apiCall($apiBase, $userId, 'GET', '/duel/' . $duelId, null, $insecure);
        if (isSuccessResponse($state)) {
            $data = responseData($state);
            $roundStatus = is_array($data['round_status'] ?? null) ? $data['round_status'] : [];
            $myAnswered = (($roundStatus['my_answered'] ?? false) === true);
            $opponentAnswered = (($roundStatus['opponent_answered'] ?? false) === true);

            if ($myAnswered && !$opponentAnswered) {
                return [
                    'ok' => true,
                    'round_id' => (int) ($roundStatus['round_id'] ?? 0),
                    'my_answer_id' => (int) ($roundStatus['my_answer_id'] ?? 0),
                ];
            }
        }

        usleep(500_000);
    }

    return ['ok' => false, 'round_id' => 0, 'my_answer_id' => 0];
}

function assertReconnectSnapshotConsistency(
    string $apiBase,
    int $userId,
    int $duelId,
    int $expectedRoundId,
    int $expectedMyAnswerId,
    int $iterations,
    bool $insecure
): bool {
    for ($i = 0; $i < $iterations; $i++) {
        $current = apiCall($apiBase, $userId, 'GET', '/duel/current', null, $insecure);
        if (!isSuccessResponse($current)) {
            return false;
        }
        $currentData = responseData($current);
        if ((int) ($currentData['duel_id'] ?? 0) !== $duelId) {
            return false;
        }

        $state = apiCall($apiBase, $userId, 'GET', '/duel/' . $duelId, null, $insecure);
        if (!isSuccessResponse($state)) {
            return false;
        }
        $data = responseData($state);
        $roundStatus = is_array($data['round_status'] ?? null) ? $data['round_status'] : [];
        if ((int) ($roundStatus['round_id'] ?? 0) !== $expectedRoundId) {
            return false;
        }
        if (($roundStatus['my_answered'] ?? false) !== true) {
            return false;
        }
        if ((int) ($roundStatus['my_answer_id'] ?? 0) !== $expectedMyAnswerId) {
            return false;
        }
        if (($roundStatus['opponent_answered'] ?? false) === true) {
            return false;
        }

        usleep(150_000);
    }

    return true;
}

/**
 * @return array{ok:bool,round_number:int,question_id:int}
 */
function waitForQuestionVisible(string $apiBase, int $userId, int $duelId, int $timeoutSeconds, bool $insecure): array
{
    $start = time();
    while ((time() - $start) < $timeoutSeconds) {
        $state = apiCall($apiBase, $userId, 'GET', '/duel/' . $duelId, null, $insecure);
        if (isSuccessResponse($state)) {
            $data = responseData($state);
            $questionId = (int) (($data['question']['id'] ?? 0));
            $roundNumber = (int) ($data['current_round'] ?? 0);
            if ($questionId > 0 && $roundNumber > 0) {
                return [
                    'ok' => true,
                    'round_number' => $roundNumber,
                    'question_id' => $questionId,
                ];
            }
        }

        usleep(350_000);
    }

    return [
        'ok' => false,
        'round_number' => 0,
        'question_id' => 0,
    ];
}

function assertNoAnswerRegressionDuringRapidPoll(
    string $apiBase,
    int $userId,
    int $duelId,
    int $expectedRoundId,
    int $expectedAnswerId,
    int $iterations,
    bool $insecure
): bool {
    for ($i = 0; $i < $iterations; $i++) {
        $state = apiCall($apiBase, $userId, 'GET', '/duel/' . $duelId, null, $insecure);
        if (!isSuccessResponse($state)) {
            return false;
        }

        $data = responseData($state);
        $roundStatus = is_array($data['round_status'] ?? null) ? $data['round_status'] : [];
        $roundId = (int) ($roundStatus['round_id'] ?? 0);

        if ($roundId === $expectedRoundId) {
            $myAnswered = (($roundStatus['my_answered'] ?? false) === true);
            $myAnswerId = (int) ($roundStatus['my_answer_id'] ?? 0);
            if (!$myAnswered || $myAnswerId !== $expectedAnswerId) {
                return false;
            }
        }

        usleep(120_000);
    }

    return true;
}

function createGhostSnapshotFromUser(\QuizBot\Domain\Model\User $sourceUser): ?DuelGhostSnapshot
{
    $questions = Question::query()
        ->whereHas('answers')
        ->with('answers')
        ->limit(6)
        ->get();

    if ($questions->count() < 3) {
        return null;
    }

    $rounds = [];
    $roundNumber = 1;
    foreach ($questions as $question) {
        $questionId = (int) $question->getKey();
        if ($questionId <= 0) {
            continue;
        }

        $correctAnswer = $question->answers->firstWhere('is_correct', true);
        $fallbackAnswer = $question->answers->first();
        $answer = $correctAnswer ?: $fallbackAnswer;
        if (!$answer) {
            continue;
        }

        $isCorrect = (bool) ($correctAnswer !== null);
        $rounds[] = [
            'round_number' => $roundNumber,
            'question_id' => $questionId,
            'time_limit' => 30,
            'answer_id' => (int) $answer->getKey(),
            'is_correct' => $isCorrect,
            'reason' => null,
            'time_elapsed' => max(1, 8 + $roundNumber),
            'answered_at' => Carbon::now()->subSeconds(max(1, 45 - $roundNumber))->toAtomString(),
        ];
        $roundNumber++;
    }

    if (count($rounds) < 3) {
        return null;
    }

    return DuelGhostSnapshot::query()->create([
        'source_duel_id' => 0,
        'source_user_id' => (int) $sourceUser->getKey(),
        'source_rating' => $sourceUser->profile ? (int) $sourceUser->profile->rating : 1000,
        'question_count' => count($rounds),
        'quality_score' => max(30, count($rounds) * 10),
        'rounds_payload' => ['rounds' => $rounds],
        'created_at' => Carbon::now(),
    ]);
}

try {
    out('== Bootstrap test users ==');
    $userA = $userService->ensureProfile($userService->syncFromTelegram([
        'id' => $userAId,
        'first_name' => 'ApiSmokeA',
        'last_name' => 'User',
        'username' => 'api_smoke_a_' . $userAId,
        'language_code' => 'ru',
    ]));
    $userB = $userService->ensureProfile($userService->syncFromTelegram([
        'id' => $userBId,
        'first_name' => 'ApiSmokeB',
        'last_name' => 'User',
        'username' => 'api_smoke_b_' . $userBId,
        'language_code' => 'ru',
    ]));
    cleanupActiveDuels((int) $userA->getKey(), (int) $userB->getKey());
    ok('Test users prepared and stale duels cleaned');

    out('== Preflight auth ==');
    $authA = apiCall($apiBase, $userAId, 'GET', '/user', null, $insecure);
    $authB = apiCall($apiBase, $userBId, 'GET', '/user', null, $insecure);
    assertTrue(isSuccessResponse($authA), 'User A authorized via API', $errors);
    assertTrue(isSuccessResponse($authB), 'User B authorized via API', $errors);
    if (!isSuccessResponse($authA) || !isSuccessResponse($authB)) {
        $statusA = (int) ($authA['status'] ?? 0);
        $statusB = (int) ($authB['status'] ?? 0);
        if ($statusA === 401 || $statusB === 401) {
            fail('API auth failed with 401. For local/staging smoke enable DEV_AUTH_ENABLED=true (or use real Telegram initData auth).', $errors);
        }
    }

    out('== Scenario 1: Random duel via API ==');
    cleanupActiveDuels((int) $userA->getKey(), (int) $userB->getKey());
    $createA = apiCall($apiBase, $userAId, 'POST', '/duel/create', ['mode' => 'random'], $insecure);
    $createB = apiCall($apiBase, $userBId, 'POST', '/duel/create', ['mode' => 'random'], $insecure);
    assertTrue(isSuccessResponse($createA), 'User A random duel create succeeded', $errors);
    assertTrue(isSuccessResponse($createB), 'User B random duel create succeeded', $errors);

    $duelId = waitForSharedActiveDuel($apiBase, $userAId, $userBId, 20, $insecure, $errors);
    if ($duelId !== null) {
        ok('Both users see same active duel #' . $duelId);

        $duelA = apiCall($apiBase, $userAId, 'GET', '/duel/' . $duelId, null, $insecure);
        $duelB = apiCall($apiBase, $userBId, 'GET', '/duel/' . $duelId, null, $insecure);
        assertTrue(isSuccessResponse($duelA), 'User A duel state loaded', $errors);
        assertTrue(isSuccessResponse($duelB), 'User B duel state loaded', $errors);

        $ticketA = apiCall($apiBase, $userAId, 'GET', '/duel/ws-ticket?duel_id=' . $duelId, null, $insecure);
        $ticketB = apiCall($apiBase, $userBId, 'GET', '/duel/ws-ticket?duel_id=' . $duelId, null, $insecure);
        $ticketAData = responseData($ticketA);
        $ticketBData = responseData($ticketB);
        assertTrue(isSuccessResponse($ticketA) && !empty($ticketAData['ticket']), 'User A ws-ticket issued', $errors);
        assertTrue(isSuccessResponse($ticketB) && !empty($ticketBData['ticket']), 'User B ws-ticket issued', $errors);

        $duelAData = responseData($duelA);
        $duelBData = responseData($duelB);
        $answersA = $duelAData['question']['answers'] ?? [];
        $answersB = $duelBData['question']['answers'] ?? [];

        if (is_array($answersA) && is_array($answersB) && !empty($answersA) && !empty($answersB)) {
            $answerAId = (int) ($answersA[0]['id'] ?? 0);
            $answerBId = (int) ($answersB[0]['id'] ?? 0);

            if ($answerAId > 0 && $answerBId > 0) {
                $submitA = apiCall($apiBase, $userAId, 'POST', '/duel/answer', [
                    'duelId' => $duelId,
                    'answerId' => $answerAId,
                ], $insecure);
                assertTrue(isSuccessResponse($submitA), 'User A answer accepted', $errors);

                $pendingSnapshot = waitForMyAnsweredOpponentPending($apiBase, $userAId, $duelId, 8, $insecure);
                assertTrue($pendingSnapshot['ok'] === true, 'User A sees waiting-opponent state after answer', $errors);
                if ($pendingSnapshot['ok']) {
                    assertTrue($pendingSnapshot['my_answer_id'] === $answerAId, 'Server preserved submitted answer id while waiting', $errors);
                    $roundIdBefore = $pendingSnapshot['round_id'];
                    assertTrue(
                        assertReconnectSnapshotConsistency(
                            $apiBase,
                            $userAId,
                            $duelId,
                            $roundIdBefore,
                            $answerAId,
                            8,
                            $insecure
                        ),
                        'Reconnect/rejoin snapshots keep duel id, round id and fixed answer stable',
                        $errors
                    );
                    assertTrue(
                        assertNoAnswerRegressionDuringRapidPoll(
                            $apiBase,
                            $userAId,
                            $duelId,
                            $roundIdBefore,
                            $answerAId,
                            10,
                            $insecure
                        ),
                        'Rapid polling keeps my_answered/my_answer_id stable before opponent answers',
                        $errors
                    );
                    usleep(700_000);
                    $pendingSnapshotAgain = waitForMyAnsweredOpponentPending($apiBase, $userAId, $duelId, 3, $insecure);
                    if ($pendingSnapshotAgain['ok']) {
                        assertTrue($pendingSnapshotAgain['round_id'] === $roundIdBefore, 'Round id remains stable during waiting-opponent phase', $errors);
                    }

                    $pendingForB = apiCall($apiBase, $userBId, 'GET', '/duel/' . $duelId, null, $insecure);
                    $currentForB = apiCall($apiBase, $userBId, 'GET', '/duel/current', null, $insecure);
                    assertTrue(isSuccessResponse($pendingForB), 'User B duel state readable while opponent already answered', $errors);
                    assertTrue(isSuccessResponse($currentForB), 'User B current duel endpoint is readable while waiting', $errors);
                    if (isSuccessResponse($pendingForB)) {
                        $pendingForBData = responseData($pendingForB);
                        $pendingForBRound = is_array($pendingForBData['round_status'] ?? null) ? $pendingForBData['round_status'] : [];
                        assertTrue(
                            (($pendingForBRound['my_answered'] ?? false) === false),
                            'User B still sees own answer as pending before submit',
                            $errors
                        );
                        assertTrue(
                            (int) ($pendingForBData['current_round'] ?? 0) === (int) ($duelAData['current_round'] ?? 0),
                            'Both users stay on same round while only one answered',
                            $errors
                        );
                        if (isSuccessResponse($currentForB)) {
                            $currentForBData = responseData($currentForB);
                            assertTrue(
                                (int) ($currentForBData['duel_id'] ?? 0) === (int) $duelId,
                                'User B current duel id remains stable while waiting',
                                $errors
                            );
                        }
                    }
                }

                $submitB = apiCall($apiBase, $userBId, 'POST', '/duel/answer', [
                    'duelId' => $duelId,
                    'answerId' => $answerBId,
                ], $insecure);
                assertTrue(isSuccessResponse($submitB), 'User B answer accepted', $errors);
                assertTrue(
                    waitForRoundProgress($apiBase, $userAId, $duelId, 10, $insecure),
                    'Round progress observed after both answers',
                    $errors
                );
            } else {
                fail('Could not resolve answer IDs for first round', $errors);
            }
        } else {
            fail('Question answers are missing in duel state response', $errors);
        }
    }

    out('== Scenario 2: Watchdog timeout closes stale API round ==');
    cleanupActiveDuels((int) $userA->getKey(), (int) $userB->getKey());
    $timeoutCreateA = apiCall($apiBase, $userAId, 'POST', '/duel/create', ['mode' => 'random'], $insecure);
    $timeoutCreateB = apiCall($apiBase, $userBId, 'POST', '/duel/create', ['mode' => 'random'], $insecure);
    assertTrue(isSuccessResponse($timeoutCreateA), 'Timeout scenario: User A random duel create succeeded', $errors);
    assertTrue(isSuccessResponse($timeoutCreateB), 'Timeout scenario: User B random duel create succeeded', $errors);

    $timeoutDuelId = waitForSharedActiveDuel($apiBase, $userAId, $userBId, 20, $insecure, $errors);
    if ($timeoutDuelId !== null) {
        $timeoutState = apiCall($apiBase, $userAId, 'GET', '/duel/' . $timeoutDuelId, null, $insecure);
        $timeoutData = responseData($timeoutState);
        $timeoutAnswers = $timeoutData['question']['answers'] ?? [];

        if (is_array($timeoutAnswers) && !empty($timeoutAnswers)) {
            $timeoutAnswerId = (int) ($timeoutAnswers[0]['id'] ?? 0);
            if ($timeoutAnswerId > 0) {
                $timeoutSubmitA = apiCall($apiBase, $userAId, 'POST', '/duel/answer', [
                    'duelId' => $timeoutDuelId,
                    'answerId' => $timeoutAnswerId,
                ], $insecure);
                assertTrue(isSuccessResponse($timeoutSubmitA), 'Timeout scenario: User A answer accepted', $errors);

                $timeoutSubmitData = responseData($timeoutSubmitA);
                $timeoutRoundId = (int) ($timeoutSubmitData['round_id'] ?? 0);
                assertTrue($timeoutRoundId > 0, 'Timeout scenario: round id returned after answer', $errors);

                if ($timeoutRoundId > 0) {
                    $staleRound = DuelRound::query()->find($timeoutRoundId);
                    if ($staleRound instanceof DuelRound) {
                        $staleRound->question_sent_at = Carbon::now()->subSeconds(((int) $staleRound->time_limit) + 5);
                        $staleRound->save();

                        $watchdogResult = $duelService->processExpiredInProgressRounds(200);
                        assertTrue((int) ($watchdogResult['processed'] ?? 0) > 0, 'Timeout scenario: watchdog processed in-progress duel', $errors);

                        $staleRound->refresh();
                        $opponentPayload = is_array($staleRound->opponent_payload) ? $staleRound->opponent_payload : [];
                        assertTrue($staleRound->closed_at !== null, 'Timeout scenario: stale round closed by watchdog', $errors);
                        assertTrue(($opponentPayload['completed'] ?? false) === true, 'Timeout scenario: pending player completed by watchdog', $errors);
                        assertTrue((string) ($opponentPayload['reason'] ?? '') === 'timeout', 'Timeout scenario: pending player reason is timeout', $errors);
                    } else {
                        fail('Timeout scenario: stale round not found by id', $errors);
                    }
                }
            } else {
                fail('Timeout scenario: no answer id resolved', $errors);
            }
        } else {
            fail('Timeout scenario: question answers missing', $errors);
        }
    }

    out('== Scenario 2.5: Ghost fallback after 30s waiting ==');
    cleanupActiveDuels((int) $userA->getKey(), (int) $userB->getKey());

    $ghostSnapshot = createGhostSnapshotFromUser($userB);
    assertTrue($ghostSnapshot instanceof DuelGhostSnapshot, 'Ghost snapshot prepared for fallback scenario', $errors);

    $ghostCreate = apiCall($apiBase, $userAId, 'POST', '/duel/create', ['mode' => 'random'], $insecure);
    assertTrue(isSuccessResponse($ghostCreate), 'Ghost scenario: user A random duel create succeeded', $errors);
    $ghostCreateData = responseData($ghostCreate);
    $ghostDuelId = (int) ($ghostCreateData['duel_id'] ?? 0);
    assertTrue($ghostDuelId > 0, 'Ghost scenario: duel id returned', $errors);

    if ($ghostDuelId > 0) {
        $ghostDuel = Duel::query()->find($ghostDuelId);
        if ($ghostDuel instanceof Duel) {
            $ghostDuel->created_at = Carbon::now()->subSeconds(35);
            $ghostDuel->save();
        } else {
            fail('Ghost scenario: created duel not found in DB', $errors);
        }

        $ghostState = apiCall($apiBase, $userAId, 'GET', '/duel/' . $ghostDuelId, null, $insecure);
        assertTrue(isSuccessResponse($ghostState), 'Ghost scenario: duel state readable', $errors);
        if (isSuccessResponse($ghostState)) {
            $ghostStateData = responseData($ghostState);
            assertTrue(($ghostStateData['ghost_fallback_attempted'] ?? false) === true, 'Ghost scenario: fallback attempt flag is true', $errors);
            assertTrue(($ghostStateData['ghost_fallback_assigned'] ?? false) === true, 'Ghost scenario: fallback assigned flag is true', $errors);
            assertTrue(($ghostStateData['is_ghost_match'] ?? false) === true, 'Ghost scenario: duel marked as ghost match', $errors);
            assertTrue(($ghostStateData['match_type'] ?? '') === 'ghost', 'Ghost scenario: match_type is ghost', $errors);
            assertTrue(($ghostStateData['status'] ?? '') === 'in_progress', 'Ghost scenario: duel auto-started after fallback', $errors);
            assertTrue((int) (($ghostStateData['question']['id'] ?? 0)) > 0, 'Ghost scenario: question visible immediately', $errors);
        }
    }

    out('== Scenario 3: Friend duel via code ==');
    cleanupActiveDuels((int) $userA->getKey(), (int) $userB->getKey());

    $friendCreate = apiCall($apiBase, $userAId, 'POST', '/duel/create', ['mode' => 'friend'], $insecure);
    assertTrue(isSuccessResponse($friendCreate), 'Friend duel create succeeded', $errors);

    $friendData = responseData($friendCreate);
    $friendDuelId = (int) ($friendData['duel_id'] ?? 0);
    $friendCode = (string) ($friendData['code'] ?? '');
    assertTrue($friendDuelId > 0, 'Friend duel id returned', $errors);
    assertTrue((bool) preg_match('/^\d{5}$/', $friendCode), 'Friend duel code has 5 digits', $errors);

    if ($friendDuelId > 0 && preg_match('/^\d{5}$/', $friendCode)) {
        $join = apiCall($apiBase, $userBId, 'POST', '/duel/join', ['code' => $friendCode], $insecure);
        assertTrue(isSuccessResponse($join), 'User B joined friend duel by code', $errors);

        $sharedFriendMeta = waitForSharedActiveDuelMeta($apiBase, $userAId, $userBId, 20, $insecure);
        $sharedFriendDuelId = $sharedFriendMeta ? (int) ($sharedFriendMeta['duel_id'] ?? 0) : null;
        assertTrue($sharedFriendMeta !== null && $sharedFriendDuelId > 0, 'Both users converged to same friend duel', $errors);
        if ($sharedFriendMeta !== null && $sharedFriendDuelId !== null && $sharedFriendDuelId > 0) {
            assertTrue(
                (int) ($sharedFriendMeta['elapsed_ms'] ?? 999999) <= 8000,
                'Friend duel: both clients converge quickly (<= 8s)',
                $errors
            );
            assertTrue((int) $sharedFriendDuelId === (int) $friendDuelId, 'Friend duel id matches created invite', $errors);

            $friendAQuestion = waitForQuestionVisible($apiBase, $userAId, $sharedFriendDuelId, 8, $insecure);
            $friendBQuestion = waitForQuestionVisible($apiBase, $userBId, $sharedFriendDuelId, 8, $insecure);
            assertTrue($friendAQuestion['ok'] === true, 'Friend duel: initiator sees question without manual refresh', $errors);
            assertTrue($friendBQuestion['ok'] === true, 'Friend duel: opponent sees question without manual refresh', $errors);
            if ($friendAQuestion['ok'] && $friendBQuestion['ok']) {
                assertTrue(
                    (int) $friendAQuestion['round_number'] === (int) $friendBQuestion['round_number'],
                    'Friend duel: both users stay on same round after join',
                    $errors
                );
            }
        }
    }

    out('== Scenario 4: Friend invite cancel (waiting) ==');
    cleanupActiveDuels((int) $userA->getKey(), (int) $userB->getKey());
    $cancelCreate = apiCall($apiBase, $userAId, 'POST', '/duel/create', ['mode' => 'friend'], $insecure);
    assertTrue(isSuccessResponse($cancelCreate), 'Friend invite for cancel scenario created', $errors);

    $cancelData = responseData($cancelCreate);
    $cancelDuelId = (int) ($cancelData['duel_id'] ?? 0);

    if ($cancelDuelId > 0) {
        $cancel = apiCall($apiBase, $userAId, 'POST', '/duel/' . $cancelDuelId . '/cancel', [], $insecure);
        assertTrue(isSuccessResponse($cancel), 'Friend duel cancel succeeded', $errors);

        $cancelState = apiCall($apiBase, $userAId, 'GET', '/duel/' . $cancelDuelId, null, $insecure);
        $cancelStateData = responseData($cancelState);
        assertTrue(isSuccessResponse($cancelState), 'Cancelled friend duel is readable', $errors);
        assertTrue(($cancelStateData['status'] ?? '') === 'cancelled', 'Cancelled friend duel status is cancelled', $errors);
        if (array_key_exists('cancelled_without_match', $cancelStateData)) {
            assertTrue(($cancelStateData['cancelled_without_match'] ?? false) === true, 'Cancelled friend duel marks cancelled_without_match', $errors);
        }
    }

    out('== Scenario 5: Rematch API lifecycle (decline/cancel/accept) ==');
    cleanupActiveDuels((int) $userA->getKey(), (int) $userB->getKey());

    $targetUserId = (int) $userB->getKey();

    // 5.1 Decline flow
    $rematchDeclineCreate = apiCall($apiBase, $userAId, 'POST', '/duel/create', [
        'mode' => 'rematch',
        'target_user_id' => $targetUserId,
    ], $insecure);
    assertTrue(isSuccessResponse($rematchDeclineCreate), 'Rematch invite create (decline flow) succeeded', $errors);
    $rematchDeclineData = responseData($rematchDeclineCreate);
    $rematchDeclineId = (int) ($rematchDeclineData['duel_id'] ?? 0);
    assertTrue($rematchDeclineId > 0, 'Rematch invite id exists (decline flow)', $errors);
    if ($rematchDeclineId > 0) {
        $incomingDecline = apiCall($apiBase, $userBId, 'GET', '/duel/rematch/incoming', null, $insecure);
        assertTrue(isSuccessResponse($incomingDecline), 'Incoming rematch visible to target (decline flow)', $errors);
        if (isSuccessResponse($incomingDecline)) {
            $incomingDeclineData = responseData($incomingDecline);
            $incoming = is_array($incomingDeclineData['incoming'] ?? null) ? $incomingDeclineData['incoming'] : null;
            assertTrue($incoming !== null, 'Incoming rematch payload exists (decline flow)', $errors);
            if ($incoming !== null) {
                assertTrue((int) ($incoming['duel_id'] ?? 0) === $rematchDeclineId, 'Incoming rematch id matches created invite (decline flow)', $errors);
            }
        }

        $declineResp = apiCall($apiBase, $userBId, 'POST', '/duel/rematch/decline', [
            'duel_id' => $rematchDeclineId,
        ], $insecure);
        assertTrue(isSuccessResponse($declineResp), 'Rematch decline request succeeded', $errors);

        $declineState = apiCall($apiBase, $userAId, 'GET', '/duel/' . $rematchDeclineId, null, $insecure);
        assertTrue(isSuccessResponse($declineState), 'Declined rematch state readable', $errors);
        if (isSuccessResponse($declineState)) {
            $declineStateData = responseData($declineState);
            assertTrue(($declineStateData['status'] ?? '') === 'cancelled', 'Declined rematch status is cancelled', $errors);
        }
    }

    // 5.2 Initiator cancel flow
    $rematchCancelCreate = apiCall($apiBase, $userAId, 'POST', '/duel/create', [
        'mode' => 'rematch',
        'target_user_id' => $targetUserId,
    ], $insecure);
    assertTrue(isSuccessResponse($rematchCancelCreate), 'Rematch invite create (cancel flow) succeeded', $errors);
    $rematchCancelData = responseData($rematchCancelCreate);
    $rematchCancelId = (int) ($rematchCancelData['duel_id'] ?? 0);
    assertTrue($rematchCancelId > 0, 'Rematch invite id exists (cancel flow)', $errors);
    if ($rematchCancelId > 0) {
        $incomingCancel = apiCall($apiBase, $userBId, 'GET', '/duel/rematch/incoming', null, $insecure);
        assertTrue(isSuccessResponse($incomingCancel), 'Incoming rematch visible to target (cancel flow)', $errors);

        $cancelRematchResp = apiCall($apiBase, $userAId, 'POST', '/duel/rematch/cancel', [
            'duel_id' => $rematchCancelId,
        ], $insecure);
        assertTrue(isSuccessResponse($cancelRematchResp), 'Rematch cancel by initiator succeeded', $errors);

        $cancelRematchState = apiCall($apiBase, $userAId, 'GET', '/duel/' . $rematchCancelId, null, $insecure);
        assertTrue(isSuccessResponse($cancelRematchState), 'Cancelled rematch state readable', $errors);
        if (isSuccessResponse($cancelRematchState)) {
            $cancelRematchStateData = responseData($cancelRematchState);
            assertTrue(($cancelRematchStateData['status'] ?? '') === 'cancelled', 'Cancelled rematch status is cancelled', $errors);
        }
    }

    // 5.3 Accept flow
    $rematchAcceptCreate = apiCall($apiBase, $userAId, 'POST', '/duel/create', [
        'mode' => 'rematch',
        'target_user_id' => $targetUserId,
    ], $insecure);
    assertTrue(isSuccessResponse($rematchAcceptCreate), 'Rematch invite create (accept flow) succeeded', $errors);
    $rematchAcceptData = responseData($rematchAcceptCreate);
    $rematchAcceptId = (int) ($rematchAcceptData['duel_id'] ?? 0);
    assertTrue($rematchAcceptId > 0, 'Rematch invite id exists (accept flow)', $errors);
    if ($rematchAcceptId > 0) {
        $incomingAccept = apiCall($apiBase, $userBId, 'GET', '/duel/rematch/incoming', null, $insecure);
        assertTrue(isSuccessResponse($incomingAccept), 'Incoming rematch visible to target (accept flow)', $errors);

        $acceptRematchResp = apiCall($apiBase, $userBId, 'POST', '/duel/rematch/accept', [
            'duel_id' => $rematchAcceptId,
        ], $insecure);
        assertTrue(isSuccessResponse($acceptRematchResp), 'Rematch accept request succeeded', $errors);
        if (isSuccessResponse($acceptRematchResp)) {
            $acceptRematchData = responseData($acceptRematchResp);
            assertTrue(($acceptRematchData['status'] ?? '') === 'matched', 'Accepted rematch transitions to matched', $errors);
        }

        $sharedRematchDuelId = waitForSharedActiveDuel($apiBase, $userAId, $userBId, 20, $insecure, $errors);
        assertTrue($sharedRematchDuelId !== null, 'Both users converge to shared rematch duel', $errors);
        if ($sharedRematchDuelId !== null) {
            assertTrue((int) $sharedRematchDuelId === $rematchAcceptId, 'Shared rematch duel id matches accepted invite', $errors);
            $rematchAQuestion = waitForQuestionVisible($apiBase, $userAId, $sharedRematchDuelId, 8, $insecure);
            $rematchBQuestion = waitForQuestionVisible($apiBase, $userBId, $sharedRematchDuelId, 8, $insecure);
            assertTrue($rematchAQuestion['ok'] === true, 'Accepted rematch: initiator sees question without refresh', $errors);
            assertTrue($rematchBQuestion['ok'] === true, 'Accepted rematch: opponent sees question without refresh', $errors);
        }
    }

    out('== Scenario 6: Rematch expiry by TTL ==');
    cleanupActiveDuels((int) $userA->getKey(), (int) $userB->getKey());

    $rematchExpiryCreate = apiCall($apiBase, $userAId, 'POST', '/duel/create', [
        'mode' => 'rematch',
        'target_user_id' => $targetUserId,
    ], $insecure);
    assertTrue(isSuccessResponse($rematchExpiryCreate), 'Rematch invite create (expiry flow) succeeded', $errors);
    $rematchExpiryData = responseData($rematchExpiryCreate);
    $rematchExpiryId = (int) ($rematchExpiryData['duel_id'] ?? 0);
    assertTrue($rematchExpiryId > 0, 'Rematch invite id exists (expiry flow)', $errors);

    if ($rematchExpiryId > 0) {
        $expiryDuel = Duel::query()->find($rematchExpiryId);
        if ($expiryDuel instanceof Duel) {
            $settings = is_array($expiryDuel->settings) ? $expiryDuel->settings : [];
            $settings['rematch_expires_at'] = Carbon::now()->subSeconds(5)->toIso8601String();
            $expiryDuel->settings = $settings;
            $expiryDuel->save();
        } else {
            fail('Rematch expiry duel row not found by id', $errors);
        }

        $incomingAfterExpiry = apiCall($apiBase, $userBId, 'GET', '/duel/rematch/incoming', null, $insecure);
        assertTrue(isSuccessResponse($incomingAfterExpiry), 'Incoming rematch endpoint works after forced expiry', $errors);
        if (isSuccessResponse($incomingAfterExpiry)) {
            $incomingAfterExpiryData = responseData($incomingAfterExpiry);
            assertTrue(($incomingAfterExpiryData['incoming'] ?? null) === null, 'Expired rematch no longer appears as incoming', $errors);
        }

        $expiredState = apiCall($apiBase, $userAId, 'GET', '/duel/' . $rematchExpiryId, null, $insecure);
        assertTrue(isSuccessResponse($expiredState), 'Expired rematch state readable', $errors);
        if (isSuccessResponse($expiredState)) {
            $expiredStateData = responseData($expiredState);
            assertTrue(($expiredStateData['status'] ?? '') === 'cancelled', 'Expired rematch status is cancelled', $errors);
            assertTrue(($expiredStateData['cancel_reason'] ?? '') === 'rematch_expired', 'Expired rematch cancel_reason is rematch_expired', $errors);
            if (array_key_exists('cancelled_without_match', $expiredStateData)) {
                assertTrue(($expiredStateData['cancelled_without_match'] ?? false) === true, 'Expired rematch flagged as cancelled_without_match', $errors);
            }
        }

        $acceptAfterExpiry = apiCall($apiBase, $userBId, 'POST', '/duel/rematch/accept', [
            'duel_id' => $rematchExpiryId,
        ], $insecure);
        assertTrue(!isSuccessResponse($acceptAfterExpiry), 'Accepting expired rematch is rejected', $errors);
    }

    out('== Scenario 7: Rematch replacement keeps only latest invite ==');
    cleanupActiveDuels((int) $userA->getKey(), (int) $userB->getKey());

    $replaceCreateFirst = apiCall($apiBase, $userAId, 'POST', '/duel/create', [
        'mode' => 'rematch',
        'target_user_id' => $targetUserId,
    ], $insecure);
    assertTrue(isSuccessResponse($replaceCreateFirst), 'First rematch invite created (replacement flow)', $errors);
    $replaceFirstData = responseData($replaceCreateFirst);
    $replaceFirstId = (int) ($replaceFirstData['duel_id'] ?? 0);
    assertTrue($replaceFirstId > 0, 'First rematch invite id exists (replacement flow)', $errors);

    $replaceCreateSecond = apiCall($apiBase, $userAId, 'POST', '/duel/create', [
        'mode' => 'rematch',
        'target_user_id' => $targetUserId,
    ], $insecure);
    assertTrue(isSuccessResponse($replaceCreateSecond), 'Second rematch invite created (replacement flow)', $errors);
    $replaceSecondData = responseData($replaceCreateSecond);
    $replaceSecondId = (int) ($replaceSecondData['duel_id'] ?? 0);
    assertTrue($replaceSecondId > 0, 'Second rematch invite id exists (replacement flow)', $errors);

    if ($replaceFirstId > 0 && $replaceSecondId > 0) {
        assertTrue($replaceSecondId !== $replaceFirstId, 'Replacement flow uses a new rematch duel id', $errors);

        $incomingAfterReplace = apiCall($apiBase, $userBId, 'GET', '/duel/rematch/incoming', null, $insecure);
        assertTrue(isSuccessResponse($incomingAfterReplace), 'Incoming rematch endpoint works after replacement', $errors);
        if (isSuccessResponse($incomingAfterReplace)) {
            $incomingAfterReplaceData = responseData($incomingAfterReplace);
            $incoming = is_array($incomingAfterReplaceData['incoming'] ?? null) ? $incomingAfterReplaceData['incoming'] : null;
            assertTrue($incoming !== null, 'Incoming rematch payload exists after replacement', $errors);
            if ($incoming !== null) {
                assertTrue((int) ($incoming['duel_id'] ?? 0) === $replaceSecondId, 'Incoming rematch points to newest invite', $errors);
            }
        }

        $firstStateAfterReplace = apiCall($apiBase, $userAId, 'GET', '/duel/' . $replaceFirstId, null, $insecure);
        assertTrue(isSuccessResponse($firstStateAfterReplace), 'Replaced rematch state readable', $errors);
        if (isSuccessResponse($firstStateAfterReplace)) {
            $firstStateAfterReplaceData = responseData($firstStateAfterReplace);
            assertTrue(($firstStateAfterReplaceData['status'] ?? '') === 'cancelled', 'Replaced rematch status is cancelled', $errors);
            assertTrue(($firstStateAfterReplaceData['cancel_reason'] ?? '') === 'rematch_replaced', 'Replaced rematch cancel_reason is rematch_replaced', $errors);
        }

        $acceptOldReplaced = apiCall($apiBase, $userBId, 'POST', '/duel/rematch/accept', [
            'duel_id' => $replaceFirstId,
        ], $insecure);
        assertTrue(!isSuccessResponse($acceptOldReplaced), 'Accepting replaced rematch is rejected', $errors);
    }

    cleanupActiveDuels((int) $userA->getKey(), (int) $userB->getKey());
} catch (Throwable $e) {
    $errors[] = $e->getMessage();
    fwrite(STDERR, '[EXCEPTION] ' . $e->getMessage() . PHP_EOL);
}

if ($errors !== []) {
    fwrite(STDERR, "\nSmoke API duel failed with " . count($errors) . " issue(s).\n");
    fwrite(STDERR, "Base URL: {$baseUrl}\n");
    exit(1);
}

out('');
out('Smoke API duel passed.');
out('Base URL: ' . $baseUrl);
exit(0);
