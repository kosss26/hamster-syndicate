#!/usr/bin/env php
<?php

declare(strict_types=1);

use QuizBot\Bootstrap\AppBootstrap;
use QuizBot\Domain\Model\Duel;
use QuizBot\Infrastructure\Config\Config;

require __DIR__ . '/../vendor/autoload.php';

$basePath = dirname(__DIR__);
$bootstrap = new AppBootstrap($basePath);
$container = $bootstrap->getContainer();

/** @var Config $config */
$config = $container->get(Config::class);

$cacheDriver = (string) $config->get('CACHE_DRIVER', 'filesystem');
$redisUrl = (string) $config->get('REDIS_URL', '');
$wsHost = (string) $config->get('WEBSOCKET_HOST', '127.0.0.1');
$wsPort = (int) $config->get('WEBSOCKET_PORT', 8090);
$eventsPath = (string) $config->get('WEBSOCKET_EVENTS_PATH', $basePath . '/storage/runtime/duel_events');

$activeDuels = Duel::query()->whereIn('status', ['waiting', 'matched', 'in_progress'])->count();
$inProgressDuels = Duel::query()->where('status', 'in_progress')->count();
$waitingDuels = Duel::query()->where('status', 'waiting')->count();

$signalsCount = 0;
$oldestSignalSec = 0;
if (is_dir($eventsPath)) {
    $files = glob($eventsPath . '/duel_*.signal') ?: [];
    $signalsCount = count($files);
    if ($signalsCount > 0) {
        $oldestMtime = time();
        foreach ($files as $file) {
            $mtime = @filemtime($file);
            if ($mtime !== false && $mtime < $oldestMtime) {
                $oldestMtime = $mtime;
            }
        }
        $oldestSignalSec = max(0, time() - $oldestMtime);
    }
}

echo "=== QuizBot Realtime Diagnostics ===\n";
echo "time: " . date('Y-m-d H:i:s') . "\n";
echo "cache_driver: {$cacheDriver}\n";
echo "redis_url: " . ($redisUrl !== '' ? $redisUrl : '(empty)') . "\n";
echo "ws_endpoint: {$wsHost}:{$wsPort}\n";
echo "ws_events_path: {$eventsPath}\n";
echo "ws_events_path_exists: " . (is_dir($eventsPath) ? 'yes' : 'no') . "\n";
echo "signal_files: {$signalsCount}\n";
echo "oldest_signal_age_sec: {$oldestSignalSec}\n";
echo "active_duels: {$activeDuels}\n";
echo "in_progress_duels: {$inProgressDuels}\n";
echo "waiting_duels: {$waitingDuels}\n";

