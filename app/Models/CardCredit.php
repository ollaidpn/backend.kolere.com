<?php

namespace App\Models;

use App\Models\Entity;
use App\Models\Scopes\Searchable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CardCredit extends Model
{
    use HasFactory;
    use Searchable;

    protected $fillable = ['entity_id', 'card_id', 'order_id', 'reward_id', 'amount', 'credit', 'points', 'type', 'description'];

    protected $searchableFields = ['*'];

    protected $table = 'card_credits';

    public function card()
    {
        return $this->belongsTo(Card::class);
    }

    public function entity()
    {
        return $this->belongsTo(Entity::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
