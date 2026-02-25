<?php

namespace App\Models;

use App\Models\Scopes\Searchable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Admin extends Model
{
    use HasFactory;
    use Searchable;

    protected $fillable = ['name', 'email', 'password', 'status', 'reference'];

    protected $searchableFields = ['*'];

    protected $hidden = ['password'];
}
