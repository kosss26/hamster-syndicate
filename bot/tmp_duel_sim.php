<?php
require 'vendor/autoload.php';

$container = (new QuizBot\Bootstrap\AppBootstrap(__DIR__))->getContainer();
$userService = $container->get(QuizBot\Application\Services\UserService::class);
$duelService = $container->get(QuizBot\Application\Services\DuelService::class);

$u1 = $userService->syncFromTelegram(['id' => 5551, 'first_name' => 'A', 'username' => 'usera']);
$u2 = $userService->syncFromTelegram(['id' => 5552, 'first_name' => 'B', 'username' => 'userb']);

$duel = $duelService->createDuel($u1, $u2);
$duel = $duelService->startDuel($duel);

$round = $duelService->getCurrentRound($duel);
$question = $round->question()->with('answers')->first();
$correct = $question->answers->firstWhere('is_correct', true);

$duelService->submitAnswer($round, $u1, $correct->getKey());
$round = $round->fresh();
$duelService->submitAnswer($round, $u2, $correct->getKey());

$duel = $duel->fresh();
$next = $duelService->getCurrentRound($duel);

echo 'Next round: ' . ($next ? $next->round_number : 'none') . PHP_EOL;
