#!/usr/bin/env php
<?php

declare(strict_types=1);

if (PHP_VERSION_ID < 80100) {
    fwrite(STDERR, "Smoke duel requires PHP >= 8.1. Current: " . PHP_VERSION . PHP_EOL);
    exit(1);
}

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

function cleanupUsers(User $userA, User $userB): void
{
    cleanupUserDuels($userA);
    cleanupUserDuels($userB);
}

function findCorrectAnswerId(DuelService $duelService, Duel $duel): ?int
{
    $round = $duelService->getCurrentRound($duel);
    if (!$round) {
        return null;
    }

    $round->loadMissing('question.answers');
    $correct = $round->question && $round->question->answers
        ? $round->question->answers->firstWhere('is_correct', true)
        : null;

    return $correct ? (int) $correct->getKey() : null;
}

try {
    $seed = random_int(1000, 9999);
    $userA = makeUser($userService, 90000000 + $seed, 'SmokeA');
    $userB = makeUser($userService, 91000000 + $seed, 'SmokeB');

    cleanupUsers($userA, $userB);

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
    cleanupUsers($userA, $userB);
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

    fwrite(STDOUT, "== Scenario 5: Rematch lifecycle (decline/cancel/accept) ==\n");
    cleanupUsers($userA, $userB);

    $rematchDeclined = $duelService->createRematchInvite($userA, $userB, null);
    $incomingDeclined = $duelService->getIncomingRematchInvite($userB);
    assertTrue($incomingDeclined !== null, 'Target user sees incoming rematch invite', $errors);
    $declined = $duelService->declineRematchInvite($rematchDeclined, $userB);
    $declinedSettings = is_array($declined->settings) ? $declined->settings : [];
    assertTrue($declined->status === 'cancelled', 'Declined rematch is cancelled', $errors);
    assertTrue(($declinedSettings['cancel_reason'] ?? null) === 'rematch_declined', 'Declined rematch has proper cancel reason', $errors);

    $rematchCancelled = $duelService->createRematchInvite($userA, $userB, null);
    $incomingCancelled = $duelService->getIncomingRematchInvite($userB);
    assertTrue($incomingCancelled !== null, 'Target user sees second rematch invite', $errors);
    $cancelled = $duelService->cancelRematchInvite($rematchCancelled, $userA);
    $cancelledSettings = is_array($cancelled->settings) ? $cancelled->settings : [];
    assertTrue($cancelled->status === 'cancelled', 'Cancelled rematch is cancelled by initiator', $errors);
    assertTrue(($cancelledSettings['cancel_reason'] ?? null) === 'rematch_cancelled_by_initiator', 'Cancelled rematch has proper cancel reason', $errors);

    $rematchAccepted = $duelService->createRematchInvite($userA, $userB, null);
    $incomingAccepted = $duelService->getIncomingRematchInvite($userB);
    assertTrue($incomingAccepted !== null, 'Target user sees third rematch invite', $errors);
    $accepted = $duelService->acceptRematchInvite($rematchAccepted, $userB);
    assertTrue($accepted->status === 'matched', 'Accepted rematch transitions to matched', $errors);
    assertTrue((int) ($accepted->opponent_user_id ?? 0) === (int) $userB->getKey(), 'Accepted rematch has correct opponent', $errors);

    fwrite(STDOUT, "== Scenario 6: Technical defeat after 3 timeout rounds ==\n");
    cleanupUsers($userA, $userB);
    $techDuel = $duelService->createDuel($userA, $userB, null, ['rounds_to_win' => 5]);
    $techDuel = $duelService->startDuel($techDuel);

    for ($i = 0; $i < 3; $i++) {
        $techDuel = $techDuel->fresh();
        $round = $duelService->getCurrentRound($techDuel);
        assertTrue($round !== null, 'Technical defeat round exists at step ' . ($i + 1), $errors);
        if (!$round) {
            break;
        }

        $correctAnswerId = findCorrectAnswerId($duelService, $techDuel);
        assertTrue($correctAnswerId !== null, 'Technical defeat scenario has correct answer at step ' . ($i + 1), $errors);
        if ($correctAnswerId === null) {
            break;
        }

        // A times out, B answers correctly -> timeout streak for A.
        $duelService->submitAnswer($round, $userA, null);
        $round = $round->fresh();
        $duelService->submitAnswer($round, $userB, $correctAnswerId);
    }

    $techDuel = $techDuel->fresh(['result']);
    assertTrue($techDuel->status === 'finished', 'Duel is finished after timeout streak', $errors);
    if ($techDuel->result) {
        $metadata = is_array($techDuel->result->metadata) ? $techDuel->result->metadata : [];
        $technical = is_array($metadata['technical_defeat'] ?? null) ? $metadata['technical_defeat'] : [];
        assertTrue(($techDuel->result->result ?? '') === 'opponent_win', 'Technical defeat result winner is opponent', $errors);
        assertTrue((int) ($techDuel->result->winner_user_id ?? 0) === (int) $userB->getKey(), 'Technical defeat winner_user_id is user B', $errors);
        assertTrue((int) ($technical['loser_user_id'] ?? 0) === (int) $userA->getKey(), 'Technical defeat loser_user_id is user A', $errors);
        assertTrue((string) ($technical['reason'] ?? '') === 'timeout_streak', 'Technical defeat reason is timeout_streak', $errors);
    } else {
        assertTrue(false, 'Technical defeat duel has result row', $errors);
    }

    fwrite(STDOUT, "== Scenario 7: Global watchdog closes stale in-progress round ==\n");
    cleanupUsers($userA, $userB);
    $watchdogDuel = $duelService->createDuel($userA, $userB);
    $watchdogDuel = $duelService->startDuel($watchdogDuel);
    $watchdogRound = $duelService->getCurrentRound($watchdogDuel);
    assertTrue($watchdogRound !== null, 'Watchdog scenario has current round', $errors);
    if ($watchdogRound) {
        $correctAnswerId = findCorrectAnswerId($duelService, $watchdogDuel);
        assertTrue($correctAnswerId !== null, 'Watchdog scenario has correct answer', $errors);
        if ($correctAnswerId !== null) {
            // A already answered, B still pending. Round should be closed by watchdog timeout.
            $duelService->submitAnswer($watchdogRound, $userA, $correctAnswerId);
            $watchdogRound = $watchdogRound->fresh();
            $watchdogRound->question_sent_at = Carbon::now()->subSeconds(((int) $watchdogRound->time_limit) + 5);
            $watchdogRound->save();

            $watchdogStats = $duelService->processExpiredInProgressRounds(50);
            $watchdogRound = $watchdogRound->fresh();
            $opponentPayload = is_array($watchdogRound->opponent_payload) ? $watchdogRound->opponent_payload : [];

            assertTrue((int) ($watchdogStats['processed'] ?? 0) > 0, 'Watchdog processed in-progress duels', $errors);
            assertTrue((int) ($watchdogStats['round_closed'] ?? 0) > 0, 'Watchdog closed stale round', $errors);
            assertTrue($watchdogRound->closed_at !== null, 'Watchdog scenario round is closed', $errors);
            assertTrue(($opponentPayload['completed'] ?? false) === true, 'Watchdog marked pending participant as completed', $errors);
            assertTrue(($opponentPayload['reason'] ?? '') === 'timeout', 'Watchdog completed pending participant by timeout', $errors);
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
