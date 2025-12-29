<?php

namespace QuizBot\Domain\Model;

use Illuminate\Database\Eloquent\Model;

class UserAchievement extends Model
{
    protected $table = 'user_achievements';
    
    public $timestamps = false;
    
    protected $fillable = [
        'user_id',
        'achievement_id',
        'current_value',
        'is_completed',
        'completed_at',
        'is_showcased',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'achievement_id' => 'integer',
        'current_value' => 'integer',
        'is_completed' => 'boolean',
        'is_showcased' => 'boolean',
        'completed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Связь с достижением
    public function achievement()
    {
        return $this->belongsTo(Achievement::class);
    }

    // Связь с пользователем
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Вычисляемый прогресс в процентах
    public function getProgressAttribute(): int
    {
        if ($this->is_completed) {
            return 100;
        }
        
        $achievement = $this->achievement;
        if (!$achievement || $achievement->condition_value == 0) {
            return 0;
        }
        
        return min(100, (int)(($this->current_value / $achievement->condition_value) * 100));
    }
}
