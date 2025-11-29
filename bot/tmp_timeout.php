<?php
require 'vendor/autoload.php';
$container=(new QuizBot\Bootstrap\AppBootstrap(__DIR__))->getContainer();
$runner=$container->get(QuizBot\Application\Services\DuelTimeoutRunner::class);
$runner->handle(54);
