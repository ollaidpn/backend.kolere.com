<?php

namespace App\Models;

use App\Models\Scopes\Searchable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CardCredit extends Model
{
    use HasFactory;
    use Searchable;

    protected $fillable = ['card_id', 'order_id', 'amount', 'credit'];

    protected $searchableFields = ['*'];

    protected $table = 'card_credits';

    public function card()
    {
        return $this->belongsTo(Card::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
