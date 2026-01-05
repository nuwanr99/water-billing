<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('normal users can visit their dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('admins are redirected to the admin dashboard', function () {
    $this->seed(RolePermissionSeeder::class);

    $user = User::factory()->create();
    $user->givePermissionTo('admin');

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('admin.dashboard'));
});

test('the admin dashboard shows stats and recent users', function () {
    $this->seed(RolePermissionSeeder::class);

    $user = User::factory()->create();
    $user->assignRole('Super Admin');

    $this->actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/Dashboard')
            ->where('stats.users', 2)
            ->where('stats.roles', 3)
            ->has('recentUsers')
        );
});

test('normal users cannot visit the admin dashboard', function () {
    $this->seed(RolePermissionSeeder::class);

    $user = User::factory()->create();

    $this->actingAs($user)->get(route('admin.dashboard'))->assertForbidden();
});
