<?php

namespace App\Services;

use App\Models\Link;
use App\Models\Manager;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Collection;

class RbacService
{
    public function catalog(): array
    {
        return config('rbac');
    }

    public function allPermissions(): Collection
    {
        return Permission::query()->orderBy('module')->orderBy('action')->get();
    }

    public function defaultRoles(): Collection
    {
        return Role::query()->where('is_system', true)->orderBy('user_type')->orderBy('name')->get();
    }

    public function entityRoles(?int $entityId = null): Collection
    {
        return Role::query()
            ->where('user_type', 'shop')
            ->where(function ($query) use ($entityId) {
                $query->whereNull('entity_id');
                if ($entityId) {
                    $query->orWhere('entity_id', $entityId);
                }
            })
            ->with('permissions')
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->get();
    }

    public function currentLinkPermissions(?Link $link): Collection
    {
        if (!$link) {
            return collect();
        }

        if ($link->is_admin) {
            return $this->allPermissions();
        }

        $link->loadMissing('role.permissions');
        return $link->role?->permissions?->values() ?? collect();
    }

    public function permissionsForManager(Manager $manager): Collection
    {
        $link = $manager->currentLink()->with('role.permissions')->first();
        return $this->currentLinkPermissions($link);
    }

    public function hasPermission(Manager $manager, string $permissionSlug): bool
    {
        $link = $manager->currentLink()->with('role.permissions')->first();

        if (!$link) {
            return false;
        }

        if ($link->is_admin) {
            return true;
        }

        return $link->role?->permissions?->contains('slug', $permissionSlug) ?? false;
    }

    public function managerPayload(Manager $manager): array
    {
        $link = $manager->currentLink()->with('role.permissions')->first();
        $permissions = $this->currentLinkPermissions($link);

        return [
            'user' => $manager,
            'user_type' => 'shop',
            'entity_id' => $link?->entity_id,
            'is_admin' => (bool) ($link?->is_admin),
            'role' => $link?->role ? [
                'id' => $link->role->id,
                'slug' => $link->role->slug,
                'name' => $link->role->name,
                'description' => $link->role->description,
                'user_type' => $link->role->user_type,
                'scope' => $link->role->scope,
                'is_system' => $link->role->is_system,
                'is_active' => $link->role->is_active,
            ] : null,
            'permissions' => $permissions->pluck('slug')->values(),
        ];
    }
}
