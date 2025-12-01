<?php

declare(strict_types=1);

namespace QuizBot\Domain\Model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Question extends BaseModel
{
    protected $table = 'questions';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'category_id',
        'type',
        'question_text',
        'image_url',
        'explanation',
        'difficulty',
        'time_limit',
        'is_active',
        'tags',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'difficulty' => 'int',
        'time_limit' => 'int',
        'is_active' => 'bool',
        'tags' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class);
    }
}

