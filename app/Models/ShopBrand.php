<?php

namespace App\Models;

use App\Models\Scopes\Searchable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ShopBrand extends Model
{
    use HasFactory;
    use Searchable;

    protected $table = 'shop_brands';

    protected $fillable = [
        'entity_id',
        'reference',
        'name',
        'image',
    ];

    protected $searchableFields = ['*'];

    public function entity()
    {
        return $this->belongsTo(Entity::class);
    }

    public function items()
    {
        return $this->hasMany(ShopItem::class, 'brand_id');
    }

    protected static function booted(): void
    {
        static::creating(function (self $brand) {
            if (empty($brand->reference)) {
                do {
                    $brand->reference = 'SHB-' . strtoupper(Str::random(8));
                } while (static::where('reference', $brand->reference)->exists());
            }
        });
    }
}
