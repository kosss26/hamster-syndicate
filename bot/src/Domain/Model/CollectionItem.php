<?php

namespace QuizBot\Domain\Model;

use Illuminate\Database\Eloquent\Model;

class CollectionItem extends Model
{
    protected $table = 'collection_items';
    
    public $timestamps = false;
    
    protected $fillable = [
        'collection_id',
        'key',
        'name',
        'description',
        'image_url',
        'rarity',
        'drop_chance',
        'sort_order',
    ];

    protected $casts = [
        'collection_id' => 'integer',
        'drop_chance' => 'float',
        'sort_order' => 'integer',
    ];

    // Связь с коллекцией
    public function collection()
    {
        return $this->belongsTo(Collection::class);
    }

    // Связь с собранными карточками пользователей
    public function userCollectionItems()
    {
        return $this->hasMany(UserCollectionItem::class);
    }
}

