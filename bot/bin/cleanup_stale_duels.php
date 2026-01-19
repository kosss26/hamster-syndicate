#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Скрипт для очистки зависших matchmaking-дуэлей.
 * Можно запускать через cron каждую минуту:
 * * * * * * php /var/www/quiz-bot/bot/bin/cleanup_stale_duels.php
 */

use Monolog\Logger;
use QuizBot\Application\Services\DuelService;
use QuizBot\Bootstrap\AppBootstrap;

require __DIR__ . '/../vendor/autoload.php';

$ttlSeconds = 60; // Отменять дуэли старше 60 секунд

if (isset($argv[1]) && is_numeric($argv[1])) {
    $ttlSeconds = max(30, (int) $argv[1]);
}

$bootstrap = new AppBootstrap(dirname(__DIR__));
$container = $bootstrap->getContainer();

/** @var DuelService $duelService */
$duelService = $container->get(DuelService::class);

/** @var Logger $logger */
$logger = $container->get(Logger::class);

$cancelled = $duelService->cleanupStaleMatchmakingDuels($ttlSeconds);

if ($cancelled > 0) {
    $logger->info(sprintf(
        'Cleanup: отменено %d зависших matchmaking-дуэлей (TTL: %d сек)',
        $cancelled,
        $ttlSeconds
    ));
    
    echo sprintf("Отменено зависших дуэлей: %d\n", $cancelled);
} else {
    echo "Зависших дуэлей не найдено.\n";
}
