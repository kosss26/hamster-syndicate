#!/usr/bin/env php
<?php

declare(strict_types=1);

use QuizBot\Bootstrap\AppBootstrap;
use QuizBot\Database\Seeders\AchievementsSeeder;
use QuizBot\Database\Seeders\CollectionsSeeder;

require __DIR__ . '/../vendor/autoload.php';

$basePath = dirname(__DIR__);
$bootstrap = new AppBootstrap($basePath);
$bootstrap->getContainer();

try {
    (new AchievementsSeeder())->seed();
    (new CollectionsSeeder())->seed();
    fwrite(STDOUT, "Achievements/Collections seeding completed.\n");
} catch (Throwable $e) {
    fwrite(STDERR, "Seeding failed: " . $e->getMessage() . "\n");
    exit(1);
}

exit(0);
