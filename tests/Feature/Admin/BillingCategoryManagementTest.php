<?php

use App\Models\BillingCategory;
use App\Models\User;
use App\Models\WaterAccount;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->treasurer = User::factory()->create();
    $this->treasurer->givePermissionTo('admin', 'tariffs.view', 'tariffs.manage');
});

test('the billing categories index is displayed', function () {
    BillingCategory::factory()->count(2)->create();

    $this->actingAs($this->treasurer)
        ->get(route('admin.billing-categories.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/billing-categories/Index')
            ->has('billingCategories.data', 2)
        );
});

test('a billing category can be created with its tariff slabs', function () {
    $response = $this->actingAs($this->treasurer)->post(route('admin.billing-categories.store'), [
        'name' => 'Domestic',
        'description' => 'Households',
        'late_fee_percent' => 2.5,
        'is_active' => true,
        'tiers' => [
            ['lower_units' => 0, 'upper_units' => 10, 'rate_per_unit' => 20.00, 'service_charge' => 300.00],
            ['lower_units' => 10, 'upper_units' => 25, 'rate_per_unit' => 35.00, 'service_charge' => 500.00],
            ['lower_units' => 25, 'upper_units' => null, 'rate_per_unit' => 50.00, 'service_charge' => 1000.00],
        ],
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect(route('admin.billing-categories.index'));

    $category = BillingCategory::where('name', 'Domestic')->firstOrFail();

    expect($category->tiers)->toHaveCount(3)
        ->and($category->tiers->last()->upper_units)->toBeNull();
});

test('non-contiguous tariff slabs are rejected', function () {
    $this->actingAs($this->treasurer)->post(route('admin.billing-categories.store'), [
        'name' => 'Broken',
        'description' => null,
        'late_fee_percent' => 0,
        'is_active' => true,
        'tiers' => [
            ['lower_units' => 0, 'upper_units' => 10, 'rate_per_unit' => 20.00, 'service_charge' => 0],
            ['lower_units' => 15, 'upper_units' => null, 'rate_per_unit' => 35.00, 'service_charge' => 0],
        ],
    ])->assertSessionHasErrors('tiers.1.lower_units');

    expect(BillingCategory::where('name', 'Broken')->exists())->toBeFalse();
});

test('updating a billing category replaces its tariff slabs', function () {
    $category = BillingCategory::factory()->create(['name' => 'Domestic']);
    $category->tiers()->create(['lower_units' => 0, 'upper_units' => null, 'rate_per_unit' => 20.00, 'service_charge' => 100.00]);

    $response = $this->actingAs($this->treasurer)->put(route('admin.billing-categories.update', $category), [
        'name' => 'Domestic',
        'description' => 'Households',
        'late_fee_percent' => 5,
        'is_active' => true,
        'tiers' => [
            ['lower_units' => 0, 'upper_units' => 10, 'rate_per_unit' => 25.00, 'service_charge' => 200.00],
            ['lower_units' => 10, 'upper_units' => null, 'rate_per_unit' => 40.00, 'service_charge' => 400.00],
        ],
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect(route('admin.billing-categories.index'));

    $category->refresh();

    expect($category->tiers)->toHaveCount(2)
        ->and((float) $category->late_fee_percent)->toBe(5.00)
        ->and((float) $category->tiers->first()->rate_per_unit)->toBe(25.00);
});

test('a billing category without accounts can be deleted', function () {
    $category = BillingCategory::factory()->create();
    $category->tiers()->create(['lower_units' => 0, 'upper_units' => null, 'rate_per_unit' => 20.00, 'service_charge' => 100.00]);

    $this->actingAs($this->treasurer)
        ->delete(route('admin.billing-categories.destroy', $category))
        ->assertRedirect(route('admin.billing-categories.index'));

    expect(BillingCategory::find($category->id))->toBeNull();
});

test('a billing category assigned to water accounts cannot be deleted', function () {
    $member = User::factory()->create();
    $member->assignRole('Member');

    $category = BillingCategory::factory()->create();
    WaterAccount::factory()->for($member, 'owner')->for($category)->create();

    $this->actingAs($this->treasurer)->delete(route('admin.billing-categories.destroy', $category));

    expect(BillingCategory::find($category->id))->not->toBeNull();
});

test('a user without tariff permissions cannot view billing categories', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('admin');

    $this->actingAs($user)
        ->get(route('admin.billing-categories.index'))
        ->assertForbidden();
});
