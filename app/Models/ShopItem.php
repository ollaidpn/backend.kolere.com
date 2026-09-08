<?php

namespace App\Models;

use App\Models\Scopes\Searchable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ShopItem extends Model
{
    use HasFactory;
    use Searchable;

    protected $table = 'shop_items';

    protected $fillable = [
        'entity_id',
        'external_source',
        'external_item_id',
        'external_reference',
        'is_enriched',
        'needs_enrich_update',
        'external_meta',
        'category_id',
        'brand_id',
        'reference',
        'name',
        'price',
        'promo_price',
        'stock',
        'description',
        'image',
        'gallery',
        'status',
    ];

    protected $casts = [
        'is_enriched' => 'boolean',
        'needs_enrich_update' => 'boolean',
        'gallery' => 'array',
        'external_meta' => 'array',
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

    public function brand()
    {
        return $this->belongsTo(ShopBrand::class, 'brand_id');
    }

    protected static function booted(): void
    {
        static::creating(function (self $item) {
            if (empty($item->reference)) {
                do {
                    $item->reference = 'SHI-' . strtoupper(Str::random(8));
                } while (static::where('reference', $item->reference)->exists());
            }
        });
    }
}
