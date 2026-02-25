<?php

namespace App\Models;

use App\Models\Scopes\Searchable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Invitation extends Model
{
    use HasFactory;
    use Searchable;

    protected $fillable = [
        'entity_id',
        'email',
        'name',
        'ccphone',
        'phone',
        'token',
        'status',
        'is_admin',
    ];

    protected $searchableFields = ['*'];

    protected $casts = [
        'is_admin' => 'boolean',
    ];

    public function entity()
    {
        return $this->belongsTo(Entity::class);
    }
}
