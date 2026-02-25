<?php

namespace App\Models;

use App\Models\Scopes\Searchable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Card extends Model
{
    use HasFactory;
    use Searchable;

    protected $fillable = [
        'status',
        'entity_id',
        'user_id',
        'card_type_id',
        'images',
        'app_order_id',
        'credit',
    ];

    protected $searchableFields = ['*'];

    public function entity()
    {
        return $this->belongsTo(Entity::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function cardType()
    {
        return $this->belongsTo(CardType::class);
    }

    public function appOrder()
    {
        return $this->belongsTo(AppOrder::class);
    }

    public function discounts()
    {
        return $this->hasMany(Discount::class);
    }

    public function cardCredits()
    {
        return $this->hasMany(CardCredit::class);
    }

    public function alertApps()
    {
        return $this->hasMany(AlertApp::class);
    }
}
