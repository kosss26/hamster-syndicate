#!/usr/bin/env php
<?php

declare(strict_types=1);

if (PHP_VERSION_ID < 80100) {
    fwrite(STDERR, "Smoke API duel requires PHP >= 8.1. Current: " . PHP_VERSION . PHP_EOL);
    exit(1);
}

use QuizBot\Application\Services\UserService;
use QuizBot\Bootstrap\AppBootstrap;
use QuizBot\Domain\Model\Duel;
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
    $start = time();
    while ((time() - $start) < $timeoutSeconds) {
        $a = apiCall($apiBase, $userAId, 'GET', '/duel/current', null, $insecure);
        $b = apiCall($apiBase, $userBId, 'GET', '/duel/current', null, $insecure);

        if (isSuccessResponse($a) && isSuccessResponse($b)) {
            $da = responseData($a);
            $db = responseData($b);
            $idA = (int) ($da['duel_id'] ?? 0);
            $idB = (int) ($db['duel_id'] ?? 0);
            if ($idA > 0 && $idA === $idB) {
                return $idA;
            }
        }

        usleep(500_000);
    }

    fail('Users did not converge to the same active duel in time', $errors);
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
                    usleep(700_000);
                    $pendingSnapshotAgain = waitForMyAnsweredOpponentPending($apiBase, $userAId, $duelId, 3, $insecure);
                    if ($pendingSnapshotAgain['ok']) {
                        assertTrue($pendingSnapshotAgain['round_id'] === $roundIdBefore, 'Round id remains stable during waiting-opponent phase', $errors);
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

    out('== Scenario 2: Friend duel via code ==');
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

        $sharedFriendDuelId = waitForSharedActiveDuel($apiBase, $userAId, $userBId, 20, $insecure, $errors);
        assertTrue($sharedFriendDuelId !== null, 'Both users converged to same friend duel', $errors);
        if ($sharedFriendDuelId !== null) {
            assertTrue((int) $sharedFriendDuelId === (int) $friendDuelId, 'Friend duel id matches created invite', $errors);
        }
    }

    out('== Scenario 3: Friend invite cancel (waiting) ==');
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
