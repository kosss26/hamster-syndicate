<?php
require 'vendor/autoload.php';
$container = (new QuizBot\Bootstrap\AppBootstrap(__DIR__))->getContainer();
$duelService = $container->get(QuizBot\Application\Services\DuelService::class);
$duel = QuizBot\Domain\Model\Duel::find(6);
$round = $duelService->getCurrentRound($duel);
if ($round) {
    $duelService->markRoundDispatched($round);
    $duelService->getCurrentRound($duel);
}
