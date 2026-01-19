#!/usr/bin/env php
<?php

declare(strict_types=1);

use QuizBot\Bootstrap\AppBootstrap;
use QuizBot\Database\Seeders\SampleDataSeeder;

require __DIR__ . '/../vendor/autoload.php';

$basePath = dirname(__DIR__);

$bootstrap = new AppBootstrap($basePath);
$container = $bootstrap->getContainer();

$seeder = $container->get(SampleDataSeeder::class);

try {
    $seeder->run();
    
    // Запуск дополнительных сидеров
    (new \QuizBot\Database\Seeders\AchievementsSeeder())->seed();
    (new \QuizBot\Database\Seeders\CollectionsSeeder())->seed();
    
    fwrite(STDOUT, "Сидирование завершено успешно.\n");
} catch (Throwable $e) {
    fwrite(STDERR, sprintf("Ошибка сидирования: %s\n", $e->getMessage()));
    exit(1);
}

