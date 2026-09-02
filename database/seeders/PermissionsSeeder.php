<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Create default permissions
        Permission::create(['name' => 'list admins']);
        Permission::create(['name' => 'view admins']);
        Permission::create(['name' => 'create admins']);
        Permission::create(['name' => 'update admins']);
        Permission::create(['name' => 'delete admins']);

        Permission::create(['name' => 'list alertapps']);
        Permission::create(['name' => 'view alertapps']);
        Permission::create(['name' => 'create alertapps']);
        Permission::create(['name' => 'update alertapps']);
        Permission::create(['name' => 'delete alertapps']);

        Permission::create(['name' => 'list alertmessages']);
        Permission::create(['name' => 'view alertmessages']);
        Permission::create(['name' => 'create alertmessages']);
        Permission::create(['name' => 'update alertmessages']);
        Permission::create(['name' => 'delete alertmessages']);

        Permission::create(['name' => 'list apporders']);
        Permission::create(['name' => 'view apporders']);
        Permission::create(['name' => 'create apporders']);
        Permission::create(['name' => 'update apporders']);
        Permission::create(['name' => 'delete apporders']);

        Permission::create(['name' => 'list apppayments']);
        Permission::create(['name' => 'view apppayments']);
        Permission::create(['name' => 'create apppayments']);
        Permission::create(['name' => 'update apppayments']);
        Permission::create(['name' => 'delete apppayments']);

        Permission::create(['name' => 'list appsuscriptions']);
        Permission::create(['name' => 'view appsuscriptions']);
        Permission::create(['name' => 'create appsuscriptions']);
        Permission::create(['name' => 'update appsuscriptions']);
        Permission::create(['name' => 'delete appsuscriptions']);

        Permission::create(['name' => 'list cards']);
        Permission::create(['name' => 'view cards']);
        Permission::create(['name' => 'create cards']);
        Permission::create(['name' => 'update cards']);
        Permission::create(['name' => 'delete cards']);

        Permission::create(['name' => 'list cardcredits']);
        Permission::create(['name' => 'view cardcredits']);
        Permission::create(['name' => 'create cardcredits']);
        Permission::create(['name' => 'update cardcredits']);
        Permission::create(['name' => 'delete cardcredits']);

        Permission::create(['name' => 'list cardtypes']);
        Permission::create(['name' => 'view cardtypes']);
        Permission::create(['name' => 'create cardtypes']);
        Permission::create(['name' => 'update cardtypes']);
        Permission::create(['name' => 'delete cardtypes']);

        Permission::create(['name' => 'list discounts']);
        Permission::create(['name' => 'view discounts']);
        Permission::create(['name' => 'create discounts']);
        Permission::create(['name' => 'update discounts']);
        Permission::create(['name' => 'delete discounts']);

        Permission::create(['name' => 'list domains']);
        Permission::create(['name' => 'view domains']);
        Permission::create(['name' => 'create domains']);
        Permission::create(['name' => 'update domains']);
        Permission::create(['name' => 'delete domains']);

        Permission::create(['name' => 'list entities']);
        Permission::create(['name' => 'view entities']);
        Permission::create(['name' => 'create entities']);
        Permission::create(['name' => 'update entities']);
        Permission::create(['name' => 'delete entities']);

        Permission::create(['name' => 'list links']);
        Permission::create(['name' => 'view links']);
        Permission::create(['name' => 'create links']);
        Permission::create(['name' => 'update links']);
        Permission::create(['name' => 'delete links']);

        Permission::create(['name' => 'list managers']);
        Permission::create(['name' => 'view managers']);
        Permission::create(['name' => 'create managers']);
        Permission::create(['name' => 'update managers']);
        Permission::create(['name' => 'delete managers']);

        Permission::create(['name' => 'list orders']);
        Permission::create(['name' => 'view orders']);
        Permission::create(['name' => 'create orders']);
        Permission::create(['name' => 'update orders']);
        Permission::create(['name' => 'delete orders']);

        Permission::create(['name' => 'list pricings']);
        Permission::create(['name' => 'view pricings']);
        Permission::create(['name' => 'create pricings']);
        Permission::create(['name' => 'update pricings']);
        Permission::create(['name' => 'delete pricings']);

        // Create user role and assign existing permissions
        $currentPermissions = Permission::all();
        $userRole = Role::create(['name' => 'user']);
        $userRole->givePermissionTo($currentPermissions);

        // Create admin exclusive permissions
        Permission::create(['name' => 'list roles']);
        Permission::create(['name' => 'view roles']);
        Permission::create(['name' => 'create roles']);
        Permission::create(['name' => 'update roles']);
        Permission::create(['name' => 'delete roles']);

        Permission::create(['name' => 'list permissions']);
        Permission::create(['name' => 'view permissions']);
        Permission::create(['name' => 'create permissions']);
        Permission::create(['name' => 'update permissions']);
        Permission::create(['name' => 'delete permissions']);

        Permission::create(['name' => 'list users']);
        Permission::create(['name' => 'view users']);
        Permission::create(['name' => 'create users']);
        Permission::create(['name' => 'update users']);
        Permission::create(['name' => 'delete users']);

        // Create admin role and assign all permissions
        $allPermissions = Permission::all();
        $adminRole = Role::create(['name' => 'super-admin']);
        $adminRole->givePermissionTo($allPermissions);

        $user = \App\Models\User::whereEmail('admin@admin.com')->first();

        if ($user) {
            $user->assignRole($adminRole);
        }
    }
}
