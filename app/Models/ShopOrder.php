<?php

namespace App\Models;

use App\Models\Scopes\Searchable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShopOrder extends Model
{
    use HasFactory;
    use Searchable;

    protected $table = 'shop_orders';

    protected $fillable = [
        'entity_id',
        'reference',
        'customer_name',
        'customer_phone',
        'customer_email',
        'total',
        'status',
        'payment_method',
        'payment_status',
        'items',
        'notes',
    ];

    protected $casts = [
        'items' => 'array',
        'total' => 'decimal:2',
    ];

    protected $searchableFields = ['*'];

    public function entity()
    {
        return $this->belongsTo(Entity::class);
    }
}
