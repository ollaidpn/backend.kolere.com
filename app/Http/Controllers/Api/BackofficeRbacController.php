<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Link;
use App\Models\Manager;
use App\Models\Permission;
use App\Models\Role;
use App\Services\RbacService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BackofficeRbacController extends Controller
{
    public function __construct(private readonly RbacService $rbac)
    {
    }

    private function currentEntityId(Request $request): ?int
    {
        return $request->attributes->get('current_entity_id');
    }

    public function catalog(Request $request): JsonResponse
    {
        $entityId = $this->currentEntityId($request);

        return response()->json([
            'data' => [
                'modules' => $this->rbac->catalog()['modules'] ?? [],
                'roles' => $this->rbac->entityRoles($entityId)->map(fn (Role $role) => [
                    'id' => $role->id,
                    'slug' => $role->slug,
                    'name' => $role->name,
                    'description' => $role->description,
                    'user_type' => $role->user_type,
                    'scope' => $role->scope,
                    'entity_id' => $role->entity_id,
                    'is_system' => $role->is_system,
                    'is_active' => $role->is_active,
                    'permissions' => $role->permissions->pluck('slug')->values(),
                ])->values(),
                'permissions' => Permission::query()
                    ->orderBy('module')
                    ->orderBy('action')
                    ->get()
                    ->map(fn (Permission $permission) => [
                        'id' => $permission->id,
                        'slug' => $permission->slug,
                        'module' => $permission->module,
                        'action' => $permission->action,
                        'label' => $permission->label,
                        'description' => $permission->description,
                        'user_type' => $permission->user_type,
                        'is_system' => $permission->is_system,
                    ])
                    ->values(),
            ],
        ]);
    }

    public function roles(Request $request): JsonResponse
    {
        $entityId = $this->currentEntityId($request);
        $roles = $this->rbac->entityRoles($entityId);

        return response()->json([
            'data' => $roles->map(fn (Role $role) => [
                'id' => $role->id,
                'slug' => $role->slug,
                'name' => $role->name,
                'description' => $role->description,
                'user_type' => $role->user_type,
                'scope' => $role->scope,
                'entity_id' => $role->entity_id,
                'is_system' => $role->is_system,
                'is_active' => $role->is_active,
                'permissions' => $role->permissions->map(fn (Permission $permission) => [
                    'id' => $permission->id,
                    'slug' => $permission->slug,
                    'label' => $permission->label,
                    'module' => $permission->module,
                    'action' => $permission->action,
                ])->values(),
                'permissions_count' => $role->permissions->count(),
            ])->values(),
        ]);
    }

    public function managers(Request $request): JsonResponse
    {
        $entityId = $this->currentEntityId($request);
        $links = Link::query()
            ->with(['manager', 'role.permissions'])
            ->where('entity_id', $entityId)
            ->orderByDesc('is_admin')
            ->orderBy('id')
            ->get();

        return response()->json([
            'data' => $links->map(function (Link $link) {
                $permissions = $link->is_admin
                    ? $this->rbac->allPermissions()
                    : ($link->role?->permissions ?? collect());

                return [
                    'id' => $link->manager?->id,
                    'link_id' => $link->id,
                    'name' => $link->manager?->name,
                    'email' => $link->manager?->email,
                    'ccphone' => $link->manager?->ccphone,
                    'phone' => $link->manager?->phone,
                    'status' => $link->manager?->status,
                    'reference' => $link->manager?->reference,
                    'is_admin' => (bool) $link->is_admin,
                    'entity_id' => $link->entity_id,
                    'role' => $link->is_admin ? null : ($link->role ? [
                        'id' => $link->role->id,
                        'slug' => $link->role->slug,
                        'name' => $link->role->name,
                        'description' => $link->role->description,
                    ] : null),
                    'permissions' => $permissions->pluck('slug')->values(),
                    'permissions_count' => $permissions->count(),
                ];
            })->values(),
        ]);
    }

    public function storeRole(Request $request): JsonResponse
    {
        $entityId = $this->currentEntityId($request);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'user_type' => ['required', 'in:admin,shop'],
            'scope' => ['nullable', 'in:global,entity'],
            'entity_id' => ['nullable', 'integer'],
            'is_active' => ['sometimes', 'boolean'],
            'permission_ids' => ['array'],
            'permission_ids.*' => ['integer', 'exists:permissions,id'],
        ]);

        $role = DB::transaction(function () use ($validated, $entityId) {
            $slug = $validated['slug'] ?? Str::slug($validated['name'], '_');
            $scope = $validated['scope'] ?? (($validated['user_type'] === 'shop' && !empty($validated['entity_id'])) ? 'entity' : 'global');

            $role = Role::create([
                'name' => $validated['name'],
                'slug' => $slug,
                'description' => $validated['description'] ?? null,
                'user_type' => $validated['user_type'],
                'scope' => $scope,
                'entity_id' => $validated['entity_id'] ?? ($scope === 'entity' ? $entityId : null),
                'is_system' => false,
                'is_active' => $validated['is_active'] ?? true,
            ]);

            $role->permissions()->sync($validated['permission_ids'] ?? []);
            return $role->load('permissions');
        });

        return response()->json([
            'message' => 'Rôle créé avec succès.',
            'data' => $this->formatRole($role),
        ], 201);
    }

    public function updateRole(Request $request, Role $role): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'user_type' => ['required', 'in:admin,shop'],
            'scope' => ['nullable', 'in:global,entity'],
            'entity_id' => ['nullable', 'integer'],
            'is_active' => ['sometimes', 'boolean'],
            'permission_ids' => ['array'],
            'permission_ids.*' => ['integer', 'exists:permissions,id'],
        ]);

        DB::transaction(function () use ($validated, $role) {
            $role->update([
                'name' => $validated['name'],
                'slug' => $validated['slug'] ?? $role->slug,
                'description' => $validated['description'] ?? null,
                'user_type' => $validated['user_type'],
                'scope' => $validated['scope'] ?? $role->scope,
                'entity_id' => $validated['entity_id'] ?? $role->entity_id,
                'is_active' => $validated['is_active'] ?? $role->is_active,
            ]);

            if (array_key_exists('permission_ids', $validated)) {
                $role->permissions()->sync($validated['permission_ids'] ?? []);
            }
        });

        return response()->json([
            'message' => 'Rôle mis à jour.',
            'data' => $this->formatRole($role->load('permissions')),
        ]);
    }

    public function destroyRole(Role $role): JsonResponse
    {
        if ($role->is_system) {
            return response()->json(['message' => 'Impossible de supprimer un rôle système.'], 422);
        }

        if (Link::where('role_id', $role->id)->exists()) {
            return response()->json(['message' => 'Ce rôle est encore attribué à un manager.'], 422);
        }

        $role->delete();

        return response()->json(['message' => 'Rôle supprimé.']);
    }

    public function assignManagerRole(Request $request, Manager $manager): JsonResponse
    {
        $validated = $request->validate([
            'role_id' => ['nullable', 'integer', 'exists:roles,id'],
        ]);

        $entityId = $this->currentEntityId($request);
        $link = $manager->currentLink()->where('entity_id', $entityId)->first();

        if (!$link) {
            return response()->json(['message' => 'Lien manager/boutique introuvable.'], 404);
        }

        if ($link->is_admin) {
            return response()->json(['message' => 'Le manager principal est administrateur et ne dépend pas d’un rôle.'], 422);
        }

        if (!empty($validated['role_id'])) {
            $role = Role::findOrFail($validated['role_id']);
            if ($role->user_type !== 'shop') {
                return response()->json(['message' => 'Le rôle sélectionné n’est pas compatible avec un manager.'], 422);
            }
        }

        $link->update([
            'role_id' => $validated['role_id'] ?? null,
        ]);

        return response()->json([
            'message' => 'Rôle attribué au manager.',
            'data' => $this->managers($request)->getData(true)['data'] ?? [],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $manager = $request->user();

        if (!$manager instanceof Manager) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return response()->json([
            'data' => $this->rbac->managerPayload($manager),
        ]);
    }

    private function formatRole(Role $role): array
    {
        return [
            'id' => $role->id,
            'slug' => $role->slug,
            'name' => $role->name,
            'description' => $role->description,
            'user_type' => $role->user_type,
            'scope' => $role->scope,
            'entity_id' => $role->entity_id,
            'is_system' => $role->is_system,
            'is_active' => $role->is_active,
            'permissions' => $role->permissions->map(fn (Permission $permission) => [
                'id' => $permission->id,
                'slug' => $permission->slug,
                'label' => $permission->label,
                'module' => $permission->module,
                'action' => $permission->action,
            ])->values(),
            'permissions_count' => $role->permissions->count(),
        ];
    }
}
