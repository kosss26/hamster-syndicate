<?php

namespace QuizBot\Domain\Model;

use Illuminate\Database\Eloquent\Model;

class Achievement extends Model
{
    protected $table = 'achievements';
    
    public $timestamps = false;
    
    protected $fillable = [
        'key',
        'title',
        'description',
        'icon',
        'rarity',
        'category',
        'condition_type',
        'condition_value',
        'reward_coins',
        'reward_gems',
        'is_secret',
        'sort_order',
    ];

    protected $casts = [
        'is_secret' => 'boolean',
        'condition_value' => 'integer',
        'reward_coins' => 'integer',
        'reward_gems' => 'integer',
        'sort_order' => 'integer',
    ];

    // Связь с прогрессом пользователей
    public function userAchievements()
    {
        return $this->hasMany(UserAchievement::class);
    }
}
