<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->givePermissionTo('admin', 'roles.view', 'roles.create', 'roles.edit', 'roles.delete');
});

test('a role can be created and redirects to its edit page', function () {
    $response = $this->actingAs($this->admin)->post(route('admin.roles.store'), [
        'name' => 'Editor',
    ]);

    $role = Role::findByName('Editor');

    $response->assertSessionHasNoErrors()->assertRedirect(route('admin.roles.edit', $role));
});

test('the role edit page lists permissions grouped by category', function () {
    $role = Role::findByName('Admin');

    $this->actingAs($this->admin)
        ->get(route('admin.roles.edit', $role))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/roles/Edit')
            ->where('role.name', 'Admin')
            ->has('categories.users')
            ->has('categories.admin')
        );
});

test('a role name can be updated', function () {
    $role = Role::create(['name' => 'Editor', 'guard_name' => 'web']);

    $this->actingAs($this->admin)
        ->put(route('admin.roles.update', $role), ['name' => 'Publisher'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.roles.index'));

    expect($role->refresh()->name)->toBe('Publisher');
});

test('role permissions can be synced', function () {
    $role = Role::create(['name' => 'Editor', 'guard_name' => 'web']);

    $this->actingAs($this->admin)
        ->put(route('admin.roles.permissions.update', $role), [
            'permissions' => ['users.view', 'users.edit'],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.roles.edit', $role));

    expect($role->refresh()->permissions->pluck('name')->sort()->values()->all())
        ->toBe(['users.edit', 'users.view']);
});

test('a role can be deleted after typing its name to confirm', function () {
    $role = Role::create(['name' => 'Editor', 'guard_name' => 'web']);

    $this->actingAs($this->admin)
        ->delete(route('admin.roles.destroy', $role), ['confirmation' => 'Editor'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.roles.index'));

    expect($role->fresh())->toBeNull();
});
