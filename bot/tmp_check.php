<?php
require 'vendor/autoload.php';

$container = (new QuizBot\Bootstrap\AppBootstrap(__DIR__))->getContainer();
$duelService = $container->get(QuizBot\Application\Services\DuelService::class);
$duel = QuizBot\Domain\Model\Duel::find(6);
if (!$duel) {
    var_dump('not found');
    exit;
}
$round = $duelService->getCurrentRound($duel);
var_dump($round ? $round->round_number : null, $round ? $round->question_sent_at : null);
