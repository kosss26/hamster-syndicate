<?php

declare(strict_types=1);

namespace QuizBot\Domain\Model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamMember extends BaseModel
{
    protected $table = 'team_members';
    public $timestamps = false; // Нет created_at/updated_at, есть joined_at

    protected $fillable = [
        'team_id',
        'user_id',
        'role',
        'contribution',
        'joined_at',
    ];

    protected $casts = [
        'contribution' => 'int',
        'joined_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
