<?php

declare(strict_types=1);

namespace QuizBot\Domain\Model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Team extends BaseModel
{
    protected $table = 'teams';

    protected $fillable = [
        'name',
        'tag',
        'description',
        'owner_id',
        'is_open',
        'min_rating',
        'score',
        'members_count',
    ];

    protected $casts = [
        'is_open' => 'boolean',
        'min_rating' => 'int',
        'score' => 'int',
        'members_count' => 'int',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(TeamMember::class);
    }
}
