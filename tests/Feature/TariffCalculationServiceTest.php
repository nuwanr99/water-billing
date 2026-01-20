<?php

use App\Models\BillingCategory;
use App\Services\TariffCalculationService;
use Database\Seeders\BillingCategorySeeder;

/**
 * The spec §14 test tariff for the domestic category: 0-10 @ 20.00,
 * 11-25 @ 35.00, 26+ @ 50.00, stored as contiguous (lower, upper] slabs.
 */
function specTariffCategory(array $serviceCharges = [0, 0, 0]): BillingCategory
{
    $category = BillingCategory::factory()->create(['name' => 'Spec Domestic']);

    $category->tiers()->createMany([
        ['lower_units' => 0, 'upper_units' => 10, 'rate_per_unit' => 20.00, 'service_charge' => $serviceCharges[0]],
        ['lower_units' => 10, 'upper_units' => 25, 'rate_per_unit' => 35.00, 'service_charge' => $serviceCharges[1]],
        ['lower_units' => 25, 'upper_units' => null, 'rate_per_unit' => 50.00, 'service_charge' => $serviceCharges[2]],
    ]);

    return $category;
}

test('consumption spanning three tiers is allocated progressively', function () {
    $result = app(TariffCalculationService::class)->calculate(specTariffCategory(), 35);

    // (10 x 20.00) + (15 x 35.00) + (10 x 50.00)
    expect($result['usage_charge'])->toBe(1225.00)
        ->and($result['total'])->toBe(1225.00)
        ->and($result['tiers'])->toHaveCount(3)
        ->and(array_column($result['tiers'], 'units'))->toBe([10.0, 15.0, 10.0])
        ->and(array_column($result['tiers'], 'amount'))->toBe([200.0, 525.0, 500.0]);
});

test('fractional consumption lands in the correct slabs', function () {
    $result = app(TariffCalculationService::class)->calculate(specTariffCategory(), 12.5);

    // (10 x 20.00) + (2.5 x 35.00)
    expect($result['usage_charge'])->toBe(287.50)
        ->and(array_column($result['tiers'], 'units'))->toBe([10.0, 2.5]);
});

test('zero consumption produces no usage charge and the first slab service charge', function () {
    $category = specTariffCategory([300, 500, 1000]);

    $result = app(TariffCalculationService::class)->calculate($category, 0);

    expect($result['usage_charge'])->toBe(0.00)
        ->and($result['tiers'])->toBeEmpty()
        ->and($result['service_charge'])->toBe(300.00)
        ->and($result['total'])->toBe(300.00);
});

test('the service charge follows the slab the total consumption falls in', function () {
    $category = specTariffCategory([300, 500, 1000]);

    $result = app(TariffCalculationService::class)->calculate($category, 12.5);

    expect($result['service_charge'])->toBe(500.00)
        ->and($result['total'])->toBe(787.50);
});

test('a bill computes from the account category tariff, not another category', function () {
    $this->seed(BillingCategorySeeder::class);

    $service = app(TariffCalculationService::class);
    $business = BillingCategory::where('name', 'Business')->firstOrFail();
    $industrial = BillingCategory::where('name', 'Industrial')->firstOrFail();

    // Flat-rate categories: 20 units @ 150.00 vs @ 110.00, both in the
    // 0-25 block whose service charge is 500.00.
    expect($service->calculate($business, 20)['total'])->toBe(3500.00)
        ->and($service->calculate($industrial, 20)['total'])->toBe(2700.00);
});

test('the seeded domestic tariff prices a typical month correctly', function () {
    $this->seed(BillingCategorySeeder::class);

    $domestic = BillingCategory::where('name', 'Domestic')->firstOrFail();

    $result = app(TariffCalculationService::class)->calculate($domestic, 22);

    // (5 x 60) + (5 x 80) + (5 x 100) + (5 x 110) + (2 x 130) = 2,010.00
    // plus the 21-25 block's 500.00 service charge.
    expect($result['usage_charge'])->toBe(2010.00)
        ->and($result['service_charge'])->toBe(500.00)
        ->and($result['total'])->toBe(2510.00);
});
