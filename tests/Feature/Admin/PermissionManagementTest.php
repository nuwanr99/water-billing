<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('the permissions index is displayed with categories', function () {
    $admin = User::factory()->create();
    $admin->givePermissionTo('admin', 'permissions.view');

    $this->actingAs($admin)
        ->get(route('admin.permissions.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/permissions/Index')
            ->has('permissions.data')
            ->where('permissions.data.0.category', fn ($category) => filled($category))
        );
});

test('the permissions index can be sorted by category', function () {
    $admin = User::factory()->create();
    $admin->givePermissionTo('admin', 'permissions.view');

    $this->actingAs($admin)
        ->get(route('admin.permissions.index', ['sort' => 'category', 'direction' => 'asc']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('permissions.data.0.category', 'admin')
            ->where('filters.sort', 'category')
        );
});
