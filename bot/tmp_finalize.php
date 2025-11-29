<?php
require 'vendor/autoload.php';

use Carbon\Carbon;
use QuizBot\Bootstrap\AppBootstrap;
use QuizBot\Domain\Model\Duel;

$container = (new AppBootstrap(__DIR__))->getContainer();
$duelService = $container->get(QuizBot\Application\Services\DuelService::class);

$code = $argv[1] ?? null;
if (!$code) {
    fwrite(STDERR, "Usage: php tmp_finalize.php <duel_code>\n");
    exit(1);
}

$duel = Duel::query()->where('code', $code)->first();

if ($duel === null) {
    fwrite(STDERR, "Duel not found\n");
    exit(1);
}

$duel->rounds()->whereNull('closed_at')->update(['closed_at' => Carbon::now()]);
$duel->refresh();
$result = $duelService->finalizeDuel($duel);
var_dump($result->toArray());
