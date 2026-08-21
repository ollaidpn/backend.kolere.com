<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class PermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $config = config('rbac');
        $modules = $config['modules'] ?? [];
        $defaultRoles = $config['default_roles'] ?? [];

        DB::transaction(function () use ($modules, $defaultRoles) {
            $permissionsBySlug = [];

            foreach ($modules as $moduleKey => $module) {
                foreach ($module['permissions'] ?? [] as $permissionDef) {
                    $slug = "backoffice.{$moduleKey}.{$permissionDef['action']}";

                    $permission = Permission::updateOrCreate(
                        ['slug' => $slug],
                        [
                            'user_type' => 'shop',
                            'module' => $moduleKey,
                            'action' => $permissionDef['action'],
                            'label' => $permissionDef['label'],
                            'description' => $permissionDef['description'] ?? null,
                            'is_system' => true,
                        ]
                    );

                    $permissionsBySlug[$slug] = $permission;
                }
            }

            foreach ($defaultRoles as $roleDef) {
                $role = Role::updateOrCreate(
                    [
                        'slug' => $roleDef['slug'],
                        'entity_id' => null,
                        'user_type' => $roleDef['user_type'],
                    ],
                    [
                        'name' => $roleDef['name'],
                        'description' => $roleDef['description'] ?? null,
                        'scope' => $roleDef['scope'] ?? 'global',
                        'is_system' => $roleDef['is_system'] ?? true,
                        'is_active' => true,
                    ]
                );

                $permissionIds = [];

                if ($roleDef['slug'] === 'super_admin') {
                    $permissionIds = Permission::pluck('id')->all();
                } elseif ($roleDef['slug'] === 'boutique_manager') {
                    $permissionIds = Permission::pluck('id')->all();
                } elseif ($roleDef['slug'] === 'caissier') {
                    $permissionIds = Permission::whereIn('slug', [
                        'backoffice.dashboard.read',
                        'backoffice.stats.read',
                        'backoffice.clients.read',
                        'backoffice.cards.read',
                        'backoffice.sales.read',
                        'backoffice.notifications.read',
                    ])->pluck('id')->all();
                } elseif ($roleDef['slug'] === 'catalogue_manager') {
                    $permissionIds = Permission::whereIn('slug', [
                        'backoffice.dashboard.read',
                        'backoffice.stats.read',
                        'backoffice.shop.items.read',
                        'backoffice.shop.items.view_details',
                        'backoffice.shop.items.create',
                        'backoffice.shop.items.update',
                        'backoffice.shop.items.delete',
                        'backoffice.shop.items.publish',
                        'backoffice.shop.items.archive',
                        'backoffice.shop.categories.read',
                        'backoffice.shop.categories.create',
                        'backoffice.shop.categories.update',
                        'backoffice.shop.categories.delete',
                        'backoffice.shop.brands.read',
                        'backoffice.shop.brands.create',
                        'backoffice.shop.brands.update',
                        'backoffice.shop.brands.delete',
                        'backoffice.shop.promo_codes.read',
                        'backoffice.shop.promo_codes.create',
                        'backoffice.shop.promo_codes.update',
                        'backoffice.shop.promo_codes.delete',
                        'backoffice.shop.promo_codes.activate',
                        'backoffice.shop.promo_codes.deactivate',
                        'backoffice.shop.orders.read',
                        'backoffice.shop.orders.view_details',
                    ])->pluck('id')->all();
                } elseif ($roleDef['slug'] === 'support_marketing') {
                    $permissionIds = Permission::whereIn('slug', [
                        'backoffice.dashboard.read',
                        'backoffice.stats.read',
                        'backoffice.notifications.read',
                        'backoffice.notifications.create',
                        'backoffice.notifications.update',
                        'backoffice.notifications.delete',
                        'backoffice.notifications.send',
                        'backoffice.notifications.mark_read',
                        'backoffice.notifications.archive',
                        'backoffice.demandes.read',
                        'backoffice.demandes.view_details',
                        'backoffice.demandes.update',
                        'backoffice.demandes.delete',
                        'backoffice.demandes.approve',
                        'backoffice.demandes.reject',
                        'backoffice.conversions.read',
                        'backoffice.conversions.create',
                        'backoffice.conversions.update',
                        'backoffice.conversions.delete',
                        'backoffice.conversions.approve',
                        'backoffice.conversions.reject',
                        'backoffice.rewards.read',
                        'backoffice.rewards.create',
                        'backoffice.rewards.update',
                        'backoffice.rewards.delete',
                        'backoffice.rewards.activate',
                        'backoffice.rewards.deactivate',
                    ])->pluck('id')->all();
                } elseif ($roleDef['slug'] === 'lecture_seule') {
                    $permissionIds = Permission::where('action', 'read')->pluck('id')->all();
                }

                $role->permissions()->sync($permissionIds);
            }
        });
    }
}
