<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Role extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_type',
        'entity_id',
        'slug',
        'name',
        'description',
        'scope',
        'is_system',
        'is_active',
    ];

    protected $casts = [
        'entity_id' => 'integer',
        'is_system' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'role_permissions');
    }

    public function syncPermissions(array|Collection|null $permissions): void
    {
        $ids = collect($permissions ?? [])->filter()->map(fn ($permission) => $permission instanceof Permission ? $permission->id : $permission)->values()->all();
        $this->permissions()->sync($ids);
    }

    public function entity()
    {
        return $this->belongsTo(Entity::class);
    }
}
