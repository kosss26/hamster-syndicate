<?php

namespace QuizBot\Domain\Model;

use Illuminate\Database\Eloquent\Model;

class AchievementStat extends Model
{
    protected $table = 'achievement_stats';
    
    public $timestamps = false;
    
    protected $fillable = [
        'user_id',
        'stat_key',
        'stat_value',
        'last_updated',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'stat_value' => 'integer',
        'last_updated' => 'datetime',
    ];

    // Связь с пользователем
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

