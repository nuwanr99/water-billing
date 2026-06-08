<?php

use App\Models\Bill;
use App\Models\BillingCategory;
use App\Models\MeterReading;
use App\Models\User;
use App\Models\WaterAccount;
use App\Services\BillGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * An active water account owned by the given user, on a flat 20.00/unit
 * tariff — ready to be billed via billedAccount(units, $account).
 */
function memberAccount(User $owner): WaterAccount
{
    $category = BillingCategory::factory()->create();
    $category->tiers()->createMany([
        ['lower_units' => 0, 'upper_units' => null, 'rate_per_unit' => 20.00, 'service_charge' => 0],
    ]);

    return WaterAccount::factory()->create([
        'user_id' => $owner->id,
        'billing_category_id' => $category->id,
        'initial_reading' => 1000,
    ]);
}

/**
 * An account on a flat 20.00/unit tariff with a current-month reading of
 * the given consumption, billed — total due = 20 x units.
 *
 * @return array{account: WaterAccount, bill: Bill}
 */
function billedAccount(float $units = 10, ?WaterAccount $account = null): array
{
    if ($account === null) {
        $category = BillingCategory::factory()->create();
        $category->tiers()->createMany([
            ['lower_units' => 0, 'upper_units' => null, 'rate_per_unit' => 20.00, 'service_charge' => 0],
        ]);

        $account = WaterAccount::factory()->create(['billing_category_id' => $category->id, 'initial_reading' => 1000]);
    }

    $previous = $account->previousMeterValue();

    $reading = MeterReading::factory()->for($account)
        ->forMonth(now()->format('Y-m'))
        ->create(['reading_value' => $previous + $units, 'consumption' => $units]);

    $bill = app(BillGenerationService::class)->generate($reading, User::factory()->create());

    return ['account' => $account, 'bill' => $bill];
}
