<?php

namespace App\Models;

use App\Models\Scopes\Searchable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AppOrder extends Model
{
    use HasFactory;
    use Searchable;

    protected $fillable = [
        'entity_id',
        'name',
        'amount',
        'infos',
        'status',
        'reference',
    ];

    protected $searchableFields = ['*'];

    protected $table = 'app_orders';

    public function entity()
    {
        return $this->belongsTo(Entity::class);
    }

    public function cards()
    {
        return $this->hasMany(Card::class);
    }

    public function appPayments()
    {
        return $this->hasMany(AppPayment::class);
    }
}
