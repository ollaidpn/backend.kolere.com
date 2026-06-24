<?php

namespace App\Models;

use App\Models\Scopes\Searchable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShopCategory extends Model
{
    use HasFactory;
    use Searchable;

    protected $table = 'shop_categories';

    protected $fillable = [
        'entity_id',
        'name',
    ];

    protected $searchableFields = ['*'];

    public function entity()
    {
        return $this->belongsTo(Entity::class);
    }

    public function items()
    {
        return $this->hasMany(ShopItem::class, 'category_id');
    }
}
