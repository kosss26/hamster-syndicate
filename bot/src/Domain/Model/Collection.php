<?php

namespace QuizBot\Domain\Model;

use Illuminate\Database\Eloquent\Model;

class Collection extends Model
{
    protected $table = 'collections';
    
    public $timestamps = false;
    
    protected $fillable = [
        'key',
        'title',
        'description',
        'icon',
        'total_items',
        'rarity',
        'reward_coins',
        'reward_gems',
    ];

    protected $casts = [
        'total_items' => 'integer',
        'reward_coins' => 'integer',
        'reward_gems' => 'integer',
    ];

    // Связь с элементами коллекции
    public function items()
    {
        return $this->hasMany(CollectionItem::class);
    }
}

