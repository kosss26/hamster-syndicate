<?php

declare(strict_types=1);

namespace QuizBot\Domain\Model;

use Illuminate\Database\Eloquent\Model;

class UserCosmetic extends Model
{
    protected $table = 'user_cosmetics';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'cosmetic_type',
        'cosmetic_id',
        'rarity',
        'is_equipped',
        'acquired_at',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'is_equipped' => 'boolean',
        'acquired_at' => 'datetime',
    ];

    // Типы косметики
    public const TYPE_FRAME = 'frame';
    public const TYPE_EMOJI = 'emoji';

    /**
     * Пользователь
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

