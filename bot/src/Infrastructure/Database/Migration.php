<?php

declare(strict_types=1);

namespace QuizBot\Infrastructure\Database;

use Illuminate\Database\Schema\Builder;

interface Migration
{
    public function name(): string;

    public function up(Builder $schema): void;
}

