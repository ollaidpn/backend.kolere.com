<?php

namespace App\Models;

use App\Models\Scopes\Searchable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Discount extends Model
{
    use HasFactory;
    use Searchable;

    protected $fillable = [
        'entity_id',
        'card_id',
        'discount_type',
        'discount_value',
        'discount_amount',
        'expiration',
    ];

    protected $searchableFields = ['*'];

    protected $casts = [
        'expiration' => 'date',
    ];

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function entity()
    {
        return $this->belongsTo(Entity::class);
    }

    public function card()
    {
        return $this->belongsTo(Card::class);
    }
}
