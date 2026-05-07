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
        'user_id',
        'card_id',
        'reference',
        'name',
        'description',
        'items',
        'amount',
        'points_earned',
        'status',
        'discount',
        'total',
        'discount_id',
        'prescription_photo',
    ];

    protected $casts = [
        'items' => 'array',
    ];

    protected $searchableFields = ['*'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function card()
    {
        return $this->belongsTo(Card::class);
    }

    public function discount()
    {
        return $this->belongsTo(Discount::class);
    }

    public function cardCredits()
    {
        return $this->hasMany(CardCredit::class);
    }
}
