<?php

namespace App\Models;

use App\Models\Scopes\Searchable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Link extends Model
{
    use HasFactory;
    use Searchable;

    protected $fillable = ['manager_id', 'entity_id', 'role_id', 'is_admin'];

    protected $casts = [
        'is_admin' => 'boolean',
    ];

    protected $searchableFields = ['*'];

    public function manager()
    {
        return $this->belongsTo(Manager::class);
    }

    public function entity()
    {
        return $this->belongsTo(Entity::class);
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }
}
