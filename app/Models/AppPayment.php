<?php

namespace App\Models;

use App\Models\Scopes\Searchable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AppPayment extends Model
{
    use HasFactory;
    use Searchable;

    protected $fillable = [
        'amount',
        'paid_by',
        'status',
        'app_suscription_id',
        'app_order_id',
    ];

    protected $searchableFields = ['*'];

    protected $table = 'app_payments';

    public function appSuscription()
    {
        return $this->belongsTo(AppSuscription::class);
    }

    public function appOrder()
    {
        return $this->belongsTo(AppOrder::class);
    }
}
