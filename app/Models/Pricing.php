<?php

namespace App\Models;

use App\Models\Scopes\Searchable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Pricing extends Model
{
    use HasFactory;
    use Searchable;

    protected $fillable = ['name', 'description', 'amount', 'duration'];

    protected $searchableFields = ['*'];

    public function suscriptions()
    {
        return $this->hasMany(AppSuscription::class, 'pricing_id');
    }
}
