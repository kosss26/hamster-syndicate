<?php

require 'vendor/autoload.php';

$container = (new QuizBot\Bootstrap\AppBootstrap(__DIR__))->getContainer();
$userService = $container->get(QuizBot\Application\Services\UserService::class);
$duelService = $container->get(QuizBot\Application\Services\DuelService::class);

$u1 = $userService->syncFromTelegram(['id' => 1111, 'first_name' => 'Tester1', 'username' => 'testerone']);
$u2 = $userService->syncFromTelegram(['id' => 2222, 'first_name' => 'Tester2', 'username' => 'testertwo']);

$duel = $duelService->createDuel($u1, $u2);
$duel = $duelService->startDuel($duel);

$round = $duelService->getCurrentRound($duel);

$question = $round->question()->with('answers')->first();

echo 'Round1 question id: ' . $question->getKey() . PHP_EOL;

$answer = $question->answers->firstWhere('is_correct', true);

$duelService->submitAnswer($round, $u1, $answer->getKey());
$duelService->submitAnswer($round, $u2, $answer->getKey());

$duel = $duel->refresh(['rounds']);
$next = $duelService->getCurrentRound($duel);

echo 'Next round number: ' . ($next ? $next->round_number : 'none') . PHP_EOL;

