<?php

use App\Models\Permission;
use App\Models\PermissionCategory;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('guests are redirected to the login page', function () {
    $this->get(route('admin.users.index'))->assertRedirect(route('login'));
});

test('users without permissions cannot access the admin area', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('admin.users.index'))->assertForbidden();
});

test('users with the required permissions can access admin pages', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('admin', 'users.view');

    $this->actingAs($user)->get(route('admin.users.index'))->assertOk();
});

test('super admins bypass all permission checks', function () {
    $user = User::factory()->create();
    $user->assignRole('Super Admin');

    $this->actingAs($user)->get(route('admin.users.index'))->assertOk();
    $this->actingAs($user)->get(route('admin.roles.index'))->assertOk();
    $this->actingAs($user)->get(route('admin.permissions.index'))->assertOk();
});

test('the seeder creates the default roles, categorized permissions, and super admin', function () {
    expect(Role::pluck('name')->all())->toContain('Super Admin', 'Admin', 'User');

    expect(PermissionCategory::pluck('name')->all())
        ->toContain('users', 'roles', 'permissions', 'admin');

    $superAdmin = User::where('email', 'admin@admin.com')->firstOrFail();

    expect($superAdmin->hasRole('Super Admin'))->toBeTrue()
        ->and($superAdmin->getAllPermissions()->count())->toBe(Permission::count());
});
