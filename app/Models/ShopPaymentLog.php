<?php

namespace App\Models;

use App\Models\Scopes\Searchable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShopPaymentLog extends Model
{
    use HasFactory;
    use Searchable;

    protected $table = 'shop_payment_logs';

    protected $fillable = [
        'entity_id',
        'user_id',
        'type',
        'user_info',
        'data',
        'shop_order_id',
        'reference',
        'transaction_id',
        'client_infos',
        'amount',
        'payment_method',
        'paid_by',
        'status',
        'gateway_reference',
        'gateway_payload',
        'error_message',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'user_info' => 'array',
        'data' => 'array',
        'client_infos' => 'array',
        'gateway_payload' => 'array',
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
