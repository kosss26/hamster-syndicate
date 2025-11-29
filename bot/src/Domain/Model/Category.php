<?php

declare(strict_types=1);

namespace QuizBot\Domain\Model;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends BaseModel
{
    protected $table = 'categories';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'code',
        'title',
        'icon',
        'description',
        'difficulty',
        'is_active',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'difficulty' => 'int',
        'is_active' => 'bool',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }
}

