<?php

namespace App\Models;

use App\Models\Scopes\Searchable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ShopOrder extends Model
{
    use HasFactory;
    use Searchable;

    protected $table = 'shop_orders';

    protected $fillable = [
        'entity_id',
        'reference',
        'amount',
        'discount',
        'total',
        'client_infos',
        'status_payment',
        'status_delivery',
        'status_order',
        'items',
        'note',
    ];

    protected $casts = [
        'items' => 'array',
        'client_infos' => 'array',
        'total' => 'decimal:2',
        'amount' => 'decimal:2',
        'discount' => 'decimal:2',
    ];

    protected $searchableFields = ['*'];

    public function entity()
    {
        return $this->belongsTo(Entity::class);
    }

    protected static function booted(): void
    {
        static::creating(function (self $order) {
            if (empty($order->reference)) {
                do {
                    $order->reference = 'SHO-' . strtoupper(Str::random(8));
                } while (static::where('reference', $order->reference)->exists());
            }
        });
    }
}
