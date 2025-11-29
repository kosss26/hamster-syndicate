<?php
require 'vendor/autoload.php';
$container=(new QuizBot\Bootstrap\AppBootstrap(__DIR__))->getContainer();
$runner=$container->get(QuizBot\Application\Services\DuelTimeoutRunner::class);
$duel=QuizBot\Domain\Model\Duel::find(6);
$ref=new ReflectionClass($runner);
$method=$ref->getMethod('ensureNextRoundDispatched');
$method->setAccessible(true);
$method->invoke($runner,$duel);
