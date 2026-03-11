<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\PermissionCategory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * The permissions to create, keyed by their category name.
     *
     * @var array<string, list<string>>
     */
    protected array $permissions = [
        'users' => [
            'users.view',
            'users.create',
            'users.edit',
            'users.delete',
        ],
        'roles' => [
            'roles.view',
            'roles.create',
            'roles.edit',
            'roles.delete',
        ],
        'permissions' => [
            'permissions.view',
        ],
        'water-accounts' => [
            'water-accounts.view',
            'water-accounts.create',
            'water-accounts.edit',
        ],
        'readings' => [
            'readings.view',
            'readings.create',
            'readings.edit',
        ],
        'tariffs' => [
            'tariffs.view',
            'tariffs.manage',
        ],
        'bills' => [
            'bills.view',
            'bills.generate',
            'bills.print',
            'bills.reissue',
        ],
        'ledger' => [
            'ledger.view',
            'ledger.record-charge',
        ],
        'system-ledger' => [
            'system-ledger.view',
            'system-ledger.manage',
        ],
        'payments' => [
            'payments.view-own',
            'payments.view-all',
            'payments.record-manual',
            'payments.reconcile',
        ],
        'admin' => [
            'admin',
        ],
    ];

    /**
     * Seed the roles, categorized permissions, and the default super admin.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->permissions as $category => $permissions) {
            $permissionCategory = PermissionCategory::firstOrCreate(['name' => $category]);

            foreach ($permissions as $permission) {
                Permission::firstOrCreate(
                    ['name' => $permission, 'guard_name' => 'web'],
                    ['category_id' => $permissionCategory->id],
                );
            }
        }

        $superAdminRole = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'User', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Member', 'guard_name' => 'web']);

        $waterControllerRole = Role::firstOrCreate(['name' => 'Water Controller', 'guard_name' => 'web']);
        $waterControllerRole->syncPermissions([
            'readings.view', 'readings.create', 'readings.edit',
            'bills.view', 'bills.generate', 'bills.print',
        ]);

        $treasurerRole = Role::firstOrCreate(['name' => 'Treasurer', 'guard_name' => 'web']);
        $treasurerRole->syncPermissions([
            'admin', 'tariffs.view', 'tariffs.manage',
            'bills.view', 'bills.generate', 'bills.print', 'bills.reissue',
            'ledger.view', 'ledger.record-charge',
            'system-ledger.view', 'system-ledger.manage',
            'payments.view-all', 'payments.record-manual', 'payments.reconcile',
        ]);

        $superAdminRole->syncPermissions(Permission::all());

        if (User::count() === 0) {
            User::factory()->create([
                'first_name' => 'Super',
                'last_name' => 'Admin',
                'email' => 'admin@admin.com',
            ])->assignRole($superAdminRole);
        }
    }
}
