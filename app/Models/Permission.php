<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Permission extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_type',
        'module',
        'action',
        'slug',
        'label',
        'description',
        'is_system',
    ];

    protected $casts = [
        'is_system' => 'boolean',
    ];

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_permissions');
    }

    public function syncRoles(array|Collection|null $roles): void
    {
        $ids = collect($roles ?? [])->filter()->map(fn ($role) => $role instanceof Role ? $role->id : $role)->values()->all();
        $this->roles()->sync($ids);
    }
}
