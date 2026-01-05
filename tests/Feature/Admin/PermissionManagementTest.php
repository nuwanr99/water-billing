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
