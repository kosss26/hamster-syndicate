<?php

namespace QuizBot\Domain\Model;

use Illuminate\Database\Eloquent\Model;

class UserCollectionItem extends Model
{
    protected $table = 'user_collection_items';
    
    public $timestamps = false;
    
    protected $fillable = [
        'user_id',
        'collection_item_id',
        'obtained_at',
        'obtained_from',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'collection_item_id' => 'integer',
        'obtained_at' => 'datetime',
    ];

    // Связь с карточкой
    public function collectionItem()
    {
        return $this->belongsTo(CollectionItem::class);
    }

    // Связь с пользователем
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

