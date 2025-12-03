<?php

declare(strict_types=1);

namespace QuizBot\Domain\Model;

class TrueFalseFact extends BaseModel
{
    protected $table = 'true_false_facts';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'statement',
        'explanation',
        'is_true',
        'is_active',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'is_true' => 'bool',
        'is_active' => 'bool',
    ];
}


