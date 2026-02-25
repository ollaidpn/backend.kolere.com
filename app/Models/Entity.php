<?php

namespace App\Models;

use App\Models\Scopes\Searchable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Entity extends Model
{
    use HasFactory;
    use Searchable;

    protected $fillable = [
        'domain_id',
        'name',
        'logo',
        'primary_color',
        'secondary_color',
        'address',
        'town',
        'country',
    ];

    protected $searchableFields = ['*'];

    public function alertApps()
    {
        return $this->hasMany(AlertApp::class);
    }

    public function alertMessages()
    {
        return $this->hasMany(AlertMessage::class);
    }

    public function cards()
    {
        return $this->hasMany(Card::class);
    }

    public function domain()
    {
        return $this->belongsTo(Domain::class);
    }

    public function suscriptions()
    {
        return $this->hasMany(AppSuscription::class, 'entity_id');
    }

    public function links()
    {
        return $this->hasMany(Link::class);
    }

    public function appOrders()
    {
        return $this->hasMany(AppOrder::class);
    }

    public function discounts()
    {
        return $this->hasMany(Discount::class);
    }
}
