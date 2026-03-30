<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->givePermissionTo('admin', 'users.view', 'users.create', 'users.edit', 'users.delete');
});

test('the users index is displayed', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/users/Index')
            ->has('users.data')
        );
});

test('the users index can be searched and sorted', function () {
    User::factory()->create(['first_name' => 'Aaron', 'last_name' => 'Aardvark']);
    User::factory()->create(['first_name' => 'Zed', 'last_name' => 'Zulu']);

    $this->actingAs($this->admin)
        ->get(route('admin.users.index', ['sort' => 'name', 'direction' => 'asc']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('users.data.0.name', 'Aaron Aardvark')
            ->where('filters.sort', 'name')
            ->where('filters.direction', 'asc')
        );

    $this->actingAs($this->admin)
        ->get(route('admin.users.index', ['search' => 'Zulu']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->count('users.data', 1)
            ->where('users.data.0.name', 'Zed Zulu')
        );
});

test('a user can be created with roles', function () {
    $response = $this->actingAs($this->admin)->post(route('admin.users.store'), [
        'first_name' => 'New',
        'last_name' => 'User',
        'phone' => '0123456789',
        'address' => '123 Main Street',
        'wa_number' => null,
        'email' => 'new.user@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'roles' => ['User'],
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect(route('admin.users.index'));

    $user = User::where('email', 'new.user@example.com')->firstOrFail();

    expect($user->name)->toBe('New User')
        ->and($user->hasRole('User'))->toBeTrue();
});

test('a user cannot be created with a duplicate phone number', function () {
    User::factory()->create(['phone' => '0771234567']);

    $response = $this->actingAs($this->admin)->post(route('admin.users.store'), [
        'first_name' => 'New',
        'last_name' => 'User',
        'phone' => '0771234567',
        'address' => '123 Main Street',
        'wa_number' => null,
        'email' => 'new.user@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'roles' => ['User'],
    ]);

    $response->assertSessionHasErrors('phone');
});

test('a user can be updated and their roles synced', function () {
    $user = User::factory()->create();
    $user->assignRole('User');

    $response = $this->actingAs($this->admin)->put(route('admin.users.update', $user), [
        'first_name' => 'Updated',
        'last_name' => 'Name',
        'phone' => '0999999999',
        'address' => '456 Side Street',
        'wa_number' => null,
        'email' => $user->email,
        'roles' => ['Admin'],
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect(route('admin.users.index'));

    $user->refresh();

    expect($user->name)->toBe('Updated Name')
        ->and($user->getRoleNames()->all())->toBe(['Admin']);
});

test('a user password can be updated by an admin', function () {
    $user = User::factory()->create();

    $this->actingAs($this->admin)
        ->put(route('admin.users.password.update', $user), [
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.users.edit', $user));

    expect(Hash::check('new-password', $user->refresh()->password))->toBeTrue();
});

test('a user can be deleted', function () {
    $user = User::factory()->create();

    $this->actingAs($this->admin)
        ->delete(route('admin.users.destroy', $user))
        ->assertRedirect(route('admin.users.index'));

    expect($user->fresh())->toBeNull();
});
