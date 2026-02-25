<?php

namespace App\Models;

use App\Models\Scopes\Searchable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Laravel\Sanctum\HasApiTokens;

class Manager extends Authenticatable
{
    use HasFactory;
    use Searchable;
    use HasApiTokens;

    protected $fillable = [
        'name',
        'email',
        'ccphone',
        'phone',
        'status',
        'password',
        'reference',
    ];

    protected $searchableFields = ['*'];

    protected $hidden = ['password'];

    public function links()
    {
        return $this->hasMany(Link::class);
    }

    public function alertApps()
    {
        return $this->hasMany(AlertApp::class);
    }
}
