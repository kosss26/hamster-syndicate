#!/usr/bin/env php
<?php

declare(strict_types=1);

use QuizBot\Bootstrap\AppBootstrap;
use QuizBot\Infrastructure\Database\MigrationRunner;

require __DIR__ . '/../vendor/autoload.php';

$basePath = dirname(__DIR__);

$bootstrap = new AppBootstrap($basePath);
$container = $bootstrap->getContainer();

/** @var MigrationRunner $runner */
$runner = $container->get(MigrationRunner::class);

$migrationsPath = $basePath . '/database/migrations';

if (!is_dir($migrationsPath)) {
    fwrite(STDERR, "Каталог миграций не найден: {$migrationsPath}\n");
    exit(1);
}

try {
    $runner->run($migrationsPath);
    fwrite(STDOUT, "Миграции успешно выполнены.\n");
} catch (Throwable $e) {
    fwrite(STDERR, sprintf("Ошибка миграции: %s\n", $e->getMessage()));
    exit(1);
}

