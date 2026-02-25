<?php

namespace App\Models;

use App\Models\Scopes\Searchable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Laravel\Sanctum\HasApiTokens;

class Admin extends Authenticatable
{
    use HasFactory;
    use Searchable;
    use HasApiTokens;

    protected $fillable = ['name', 'email', 'password', 'status', 'reference', 'ccphone', 'phone'];

    protected $searchableFields = ['*'];

    protected $hidden = ['password'];
}
