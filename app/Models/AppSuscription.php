<?php

namespace App\Models;

use App\Models\Scopes\Searchable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AppSuscription extends Model
{
    use HasFactory;
    use Searchable;

    protected $fillable = ['entity_id', 'pricing_id'];

    protected $searchableFields = ['*'];

    protected $table = 'app_suscriptions';

    public function entity()
    {
        return $this->belongsTo(Entity::class);
    }

    public function pricing()
    {
        return $this->belongsTo(Pricing::class);
    }

    public function appPayments()
    {
        return $this->hasMany(AppPayment::class);
    }
}
