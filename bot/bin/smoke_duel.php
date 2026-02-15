#!/usr/bin/env php
<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use QuizBot\Application\Services\DuelService;
use QuizBot\Application\Services\UserService;
use QuizBot\Bootstrap\AppBootstrap;
use QuizBot\Domain\Model\Duel;
use QuizBot\Domain\Model\User;

require __DIR__ . '/../vendor/autoload.php';

$basePath = dirname(__DIR__);
$bootstrap = new AppBootstrap($basePath);
$container = $bootstrap->getContainer();

/** @var UserService $userService */
$userService = $container->get(UserService::class);
/** @var DuelService $duelService */
$duelService = $container->get(DuelService::class);

$errors = [];

function assertTrue(bool $condition, string $message, array &$errors): void
{
    if (!$condition) {
        $errors[] = $message;
        fwrite(STDERR, "[FAIL] {$message}\n");
    } else {
        fwrite(STDOUT, "[OK] {$message}\n");
    }
}

function makeUser(UserService $userService, int $telegramId, string $name): User
{
    $user = $userService->syncFromTelegram([
        'id' => $telegramId,
        'first_name' => $name,
        'last_name' => 'Smoke',
        'username' => strtolower($name) . '_smoke_' . $telegramId,
        'language_code' => 'ru',
    ]);

    return $userService->ensureProfile($user);
}

function cleanupUserDuels(User $user): void
{
    Duel::query()
        ->where(function ($q) use ($user): void {
            $q->where('initiator_user_id', $user->getKey())
                ->orWhere('opponent_user_id', $user->getKey());
        })
        ->whereIn('status', ['waiting', 'matched', 'in_progress'])
        ->update([
            'status' => 'cancelled',
            'finished_at' => Carbon::now(),
        ]);
}

try {
    $seed = random_int(1000, 9999);
    $userA = makeUser($userService, 90000000 + $seed, 'SmokeA');
    $userB = makeUser($userService, 91000000 + $seed, 'SmokeB');

    cleanupUserDuels($userA);
    cleanupUserDuels($userB);

    fwrite(STDOUT, "== Scenario 1: Random duel matchmaking ==\n");
    $ticket = $duelService->createMatchmakingTicket($userA);
    $found = $duelService->findAvailableMatchmakingTicket($userB, 300);
    assertTrue($found !== null, 'User B sees available matchmaking ticket', $errors);
    if ($found) {
        assertTrue($found->getKey() === $ticket->getKey(), 'Matchmaking ticket IDs match', $errors);
        $matched = $duelService->acceptDuel($found, $userB);
        $started = $duelService->startDuel($matched);
        assertTrue($started->status === 'in_progress', 'Random duel started', $errors);
        $round = $duelService->getCurrentRound($started);
        assertTrue($round !== null, 'Random duel has current round', $errors);
    }

    fwrite(STDOUT, "== Scenario 2: Friend duel by invite ==\n");
    $invite = $duelService->createDuel($userA, null, null, ['awaiting_target' => true]);
    assertTrue($invite->status === 'waiting', 'Invite duel created in waiting state', $errors);
    $joined = $duelService->acceptDuel($invite, $userB);
    assertTrue($joined->status === 'matched', 'Friend duel accepted', $errors);

    fwrite(STDOUT, "== Scenario 3: Timeout completion ==\n");
    $timeoutDuel = $duelService->startDuel($joined);
    $timeoutRound = $duelService->getCurrentRound($timeoutDuel);
    assertTrue($timeoutRound !== null, 'Timeout scenario has current round', $errors);
    if ($timeoutRound) {
        $timeoutRound->question_sent_at = Carbon::now()->subSeconds(((int) $timeoutRound->time_limit) + 3);
        $timeoutRound->save();
        $duelService->maybeCompleteRound($timeoutRound);
        $timeoutRound->refresh();
        assertTrue($timeoutRound->closed_at !== null, 'Round auto-closed by timeout', $errors);
    }

    fwrite(STDOUT, "== Scenario 4: Reconnect recovery semantics ==\n");
    $recoveryDuel = $duelService->createDuel($userA, $userB);
    $recoveryDuel = $duelService->startDuel($recoveryDuel);
    $recoveryRound = $duelService->getCurrentRound($recoveryDuel);
    assertTrue($recoveryRound !== null, 'Recovery scenario has current round', $errors);

    if ($recoveryRound) {
        $recoveryRound = $duelService->markRoundDispatched($recoveryRound);
        $recoveryRound->loadMissing('question.answers', 'duel');
        $correct = $recoveryRound->question->answers->firstWhere('is_correct', true);
        assertTrue($correct !== null, 'Recovery scenario has correct answer', $errors);

        if ($correct) {
            $updated = $duelService->submitAnswer($recoveryRound, $userA, (int) $correct->getKey());
            $updated->refresh();
            $initiatorPayload = $updated->initiator_payload ?? [];
            $opponentPayload = $updated->opponent_payload ?? [];

            assertTrue(($initiatorPayload['completed'] ?? false) === true, 'User A answer is persisted', $errors);
            assertTrue(($opponentPayload['completed'] ?? false) !== true, 'User B still pending (waiting opponent answer)', $errors);

            $sameRound = $duelService->getCurrentRound($recoveryDuel->fresh());
            assertTrue($sameRound !== null && $sameRound->getKey() === $updated->getKey(), 'Current round remains same for reconnect recovery', $errors);
        }
    }
} catch (Throwable $e) {
    $errors[] = $e->getMessage();
    fwrite(STDERR, "[EXCEPTION] {$e->getMessage()}\n");
}

if ($errors !== []) {
    fwrite(STDERR, "\nSmoke duel failed with " . count($errors) . " issue(s).\n");
    exit(1);
}

fwrite(STDOUT, "\nSmoke duel passed.\n");
exit(0);

