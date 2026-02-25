<?php

namespace App\Models;

use App\Models\Scopes\Searchable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CardType extends Model
{
    use HasFactory;
    use Searchable;

    protected $fillable = ['name', 'discount', 'status'];

    protected $searchableFields = ['*'];

    protected $table = 'card_types';

    public function cards()
    {
        return $this->hasMany(Card::class);
    }
}
