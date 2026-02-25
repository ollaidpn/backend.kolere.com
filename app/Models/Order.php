<?php

namespace App\Models;

use App\Models\Scopes\Searchable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Order extends Model
{
    use HasFactory;
    use Searchable;

    protected $fillable = [
        'name',
        'description',
        'price',
        'status',
        'amount',
        'discount',
        'total',
        'discount_id',
    ];

    protected $searchableFields = ['*'];

    public function discount()
    {
        return $this->belongsTo(Discount::class);
    }

    public function cardCredits()
    {
        return $this->hasMany(CardCredit::class);
    }
}
