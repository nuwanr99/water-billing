<?php

use App\Models\BillingCategory;
use App\Models\User;
use App\Models\WaterAccount;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->givePermissionTo('admin', 'water-accounts.view', 'water-accounts.create', 'water-accounts.edit');

    $this->member = User::factory()->create();
    $this->member->assignRole('Member');
});

test('the water accounts index is displayed', function () {
    WaterAccount::factory()->for($this->member, 'owner')->create();

    $this->actingAs($this->admin)
        ->get(route('admin.water-accounts.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/water-accounts/Index')
            ->has('waterAccounts.data', 1)
        );
});

test('the water accounts index can be searched by owner name', function () {
    WaterAccount::factory()->for($this->member, 'owner')->create();

    $other = User::factory()->create(['first_name' => 'Kamala', 'last_name' => 'Perera']);
    $other->assignRole('Member');
    WaterAccount::factory()->for($other, 'owner')->create();

    $this->actingAs($this->admin)
        ->get(route('admin.water-accounts.index', ['search' => 'Kamala']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->count('waterAccounts.data', 1)
            ->where('waterAccounts.data.0.owner.name', 'Kamala Perera')
        );
});

test('a water account can be created for a member', function () {
    $billingCategory = BillingCategory::factory()->create();

    $response = $this->actingAs($this->admin)->post(route('admin.water-accounts.store'), [
        'user_id' => $this->member->id,
        'billing_category_id' => $billingCategory->id,
        'account_number' => 'ACC-9001',
        'meter_number' => 'MTR-900001',
        'initial_reading' => 1200,
        'connection_address' => '12 Lake Road',
        'status' => 'active',
        'connected_at' => '2026-01-15',
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect(route('admin.water-accounts.index'));

    $waterAccount = WaterAccount::where('account_number', 'ACC-9001')->firstOrFail();

    expect($waterAccount->owner->is($this->member))->toBeTrue()
        ->and($waterAccount->meter_number)->toBe('MTR-900001')
        ->and($waterAccount->initial_reading)->toBe('1200.00')
        ->and($waterAccount->billingCategory->is($billingCategory))->toBeTrue();
});

test('a water account can be updated', function () {
    $waterAccount = WaterAccount::factory()->for($this->member, 'owner')->create();

    $response = $this->actingAs($this->admin)->put(route('admin.water-accounts.update', $waterAccount), [
        'user_id' => $this->member->id,
        'billing_category_id' => $waterAccount->billing_category_id,
        'account_number' => $waterAccount->account_number,
        'meter_number' => 'MTR-777777',
        'initial_reading' => $waterAccount->initial_reading,
        'connection_address' => null,
        'status' => 'inactive',
        'connected_at' => null,
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect(route('admin.water-accounts.index'));

    expect($waterAccount->refresh()->meter_number)->toBe('MTR-777777')
        ->and($waterAccount->status->value)->toBe('inactive');
});

test('the owner search returns matching members only', function () {
    $staff = User::factory()->create(['first_name' => 'Staffer']);

    $this->actingAs($this->admin)
        ->getJson(route('admin.water-accounts.owners', ['search' => $this->member->first_name]))
        ->assertOk()
        ->assertJsonFragment(['id' => $this->member->id])
        ->assertJsonMissing(['id' => $staff->id]);
});

test('water account pages require permission', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('admin');

    $this->actingAs($user)
        ->get(route('admin.water-accounts.index'))
        ->assertForbidden();
});
