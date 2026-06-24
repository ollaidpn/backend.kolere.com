<?php

namespace App\Models;

use App\Models\Scopes\Searchable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShopPayment extends Model
{
    use HasFactory;
    use Searchable;

    protected $table = 'shop_payments';

    protected $fillable = [
        'entity_id',
        'shop_order_id',
        'reference',
        'customer_name',
        'amount',
        'method',
        'status',
        'source',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'metadata' => 'array',
    ];

    protected $searchableFields = ['*'];

    public function entity()
    {
        return $this->belongsTo(Entity::class);
    }

    public function order()
    {
        return $this->belongsTo(ShopOrder::class, 'shop_order_id');
    }
}
