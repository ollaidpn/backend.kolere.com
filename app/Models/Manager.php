<?php

namespace App\Models;

use App\Models\Scopes\Searchable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Manager extends Model
{
    use HasFactory;
    use Searchable;

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
