<?php
require 'vendor/autoload.php';
$container=(new QuizBot\Bootstrap\AppBootstrap(__DIR__))->getContainer();
$commandHandler=$container->get(QuizBot\Presentation\Updates\Handlers\CommandHandler::class);
$duel=QuizBot\Domain\Model\Duel::find(6);
$reflection=new ReflectionClass($commandHandler);
$method=$reflection->getMethod('ensureNextRoundDispatched');
$method->setAccessible(true);
$method->invoke($commandHandler,$duel);
