<?php

declare(strict_types=1);

namespace QuizBot\Domain\Model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserLiveOpsClaim extends BaseModel
{
    protected $table = 'user_live_ops_claims';

    protected $fillable = [
        'user_id',
        'claim_key',
        'payload',
    ];

    protected $casts = [
        'user_id' => 'int',
        'payload' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
