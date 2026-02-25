<?php

namespace App\Models;

use App\Models\Scopes\Searchable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Term extends Model
{
    use HasFactory;
    use Searchable;

    protected $fillable = ['title', 'content'];

    protected $searchableFields = ['*'];
}
