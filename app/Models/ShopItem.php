<?php

namespace App\Models;

use App\Models\Scopes\Searchable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShopItem extends Model
{
    use HasFactory;
    use Searchable;

    protected $table = 'shop_items';

    protected $fillable = [
        'entity_id',
        'category_id',
        'name',
        'brand',
        'price',
        'promo_price',
        'stock',
        'description',
        'image',
        'gallery',
        'status',
    ];

    protected $casts = [
        'gallery' => 'array',
        'price' => 'decimal:2',
        'promo_price' => 'decimal:2',
        'stock' => 'integer',
    ];

    protected $searchableFields = ['*'];

    public function entity()
    {
        return $this->belongsTo(Entity::class);
    }

    public function category()
    {
        return $this->belongsTo(ShopCategory::class, 'category_id');
    }
}
