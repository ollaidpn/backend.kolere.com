<?php

namespace App\Models;

use App\Models\Scopes\Searchable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShopPromoCode extends Model
{
    use HasFactory;
    use Searchable;

    protected $table = 'shop_promo_codes';

    protected $fillable = [
        'entity_id',
        'code',
        'description',
        'type',
        'value',
        'min_amount',
        'uses',
        'max_uses',
        'status',
        'valid_until',
    ];

    protected $casts = [
        'valid_until' => 'date',
        'value' => 'decimal:2',
        'min_amount' => 'decimal:2',
        'uses' => 'integer',
        'max_uses' => 'integer',
    ];

    protected $searchableFields = ['*'];

    public function entity()
    {
        return $this->belongsTo(Entity::class);
    }
}
