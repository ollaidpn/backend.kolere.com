<?php

namespace App\Models;

use App\Models\Scopes\Searchable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class Entity extends Model
{
    use HasFactory;
    use Searchable;

    protected $fillable = [
        'reference',
        'subdomain',
        'website_status',
        'domain_id',
        'name',
        'logo',
        'primary_color',
        'secondary_color',
        'address',
        'town',
        'country',
        'email',
        'ccphone',
        'phone',
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

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function cardCredits()
    {
        return $this->hasMany(CardCredit::class);
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
        return $this->hasOne(Link::class);
    }

    public function appOrders()
    {
        return $this->hasMany(AppOrder::class);
    }

    public function discounts()
    {
        return $this->hasMany(Discount::class);
    }

    public function rewards()
    {
        return $this->hasMany(Reward::class);
    }

    public function demandes()
    {
        return $this->hasMany(Demande::class);
    }

    public function shopCategories()
    {
        return $this->hasMany(ShopCategory::class);
    }

    public function shopBrands()
    {
        return $this->hasMany(ShopBrand::class);
    }

    public function shopItems()
    {
        return $this->hasMany(ShopItem::class);
    }

    public function shopOrders()
    {
        return $this->hasMany(ShopOrder::class);
    }

    public function shopPromoCodes()
    {
        return $this->hasMany(ShopPromoCode::class);
    }

    public function shopPayments()
    {
        return $this->hasMany(ShopPayment::class);
    }

    protected static function booted(): void
    {
        static::creating(function (self $entity) {
            if (empty($entity->reference)) {
                do {
                    $reference = 'ENT-' . strtoupper(Str::random(8));
                } while (static::where('reference', $reference)->exists());

                $entity->reference = $reference;
            }
        });
    }
}
