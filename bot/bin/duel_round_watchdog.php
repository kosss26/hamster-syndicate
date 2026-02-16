#!/usr/bin/env php
<?php

declare(strict_types=1);

use Monolog\Logger;
use QuizBot\Application\Services\DuelService;
use QuizBot\Bootstrap\AppBootstrap;

require __DIR__ . '/../vendor/autoload.php';

$duelLimit = isset($argv[1]) && is_numeric($argv[1]) ? max(1, (int) $argv[1]) : 200;

$bootstrap = new AppBootstrap(dirname(__DIR__));
$container = $bootstrap->getContainer();

/** @var DuelService $duelService */
$duelService = $container->get(DuelService::class);
/** @var Logger $logger */
$logger = $container->get(Logger::class);

$result = $duelService->processExpiredInProgressRounds($duelLimit);

if ($result['timed_out'] > 0 || $result['round_closed'] > 0 || $result['duels_finished'] > 0) {
    $logger->info('Duel watchdog processed stale rounds', $result + ['duel_limit' => $duelLimit]);
}

echo sprintf(
    "processed=%d timed_out=%d round_closed=%d duels_finished=%d\n",
    (int) $result['processed'],
    (int) $result['timed_out'],
    (int) $result['round_closed'],
    (int) $result['duels_finished']
);
