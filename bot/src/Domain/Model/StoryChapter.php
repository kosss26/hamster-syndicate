<?php

declare(strict_types=1);

namespace QuizBot\Domain\Model;

use Illuminate\Database\Eloquent\Relations\HasMany;

class StoryChapter extends BaseModel
{
    protected $table = 'story_chapters';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'code',
        'title',
        'description',
        'position',
        'is_active',
        'metadata',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'position' => 'int',
        'is_active' => 'bool',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function steps(): HasMany
    {
        return $this->hasMany(StoryStep::class, 'chapter_id')->orderBy('position');
    }

    public function progress(): HasMany
    {
        return $this->hasMany(StoryProgress::class, 'chapter_id');
    }
}

