<?php

use App\Models\MeterReading;
use App\Models\User;
use App\Models\WaterAccount;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->treasurer = User::factory()->create();
    $this->treasurer->assignRole('Treasurer');
});

/**
 * A meter reading for the given account in the given billing month, with a
 * known consumption.
 */
function meterReading(WaterAccount $account, string $billingMonth, float $consumption): MeterReading
{
    return MeterReading::factory()->for($account)
        ->forMonth($billingMonth)
        ->create([
            'reading_value' => $account->initial_reading + $consumption,
            'consumption' => $consumption,
        ]);
}

test('the consumption report totals readings for the period and computes coverage', function () {
    $month = now()->format('Y-m');

    $account1 = memberAccount(User::factory()->create());
    $account2 = memberAccount(User::factory()->create());
    // An active account with no reading at all — drops coverage.
    memberAccount(User::factory()->create());

    meterReading($account1, $month, 30);
    meterReading($account2, $month, 20);

    $this->actingAs($this->treasurer)
        ->get(route('admin.reports.consumption.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/reports/Consumption')
            ->where('summary.total_consumption', 50)
            ->where('summary.accounts_read', 2)
            ->where('summary.average_per_account', 25)
            ->where('summary.active_accounts', 3)
            ->where('summary.coverage_percent', 66.7)
            ->has('readings.data', 2)
            ->has('byCategory', 2));
});

test('readings outside the requested period are excluded', function () {
    $month = now()->format('Y-m');
    $lastMonth = now()->subMonthNoOverflow()->format('Y-m');

    $account = memberAccount(User::factory()->create());

    meterReading($account, $month, 15);
    meterReading($account, $lastMonth, 40);

    $this->actingAs($this->treasurer)
        ->get(route('admin.reports.consumption.index', ['period_type' => 'month', 'month' => $month]))
        ->assertInertia(fn ($page) => $page
            ->where('summary.total_consumption', 15)
            ->where('summary.accounts_read', 1)
            ->has('readings.data', 1));

    $this->actingAs($this->treasurer)
        ->get(route('admin.reports.consumption.index', ['period_type' => 'month', 'month' => $lastMonth]))
        ->assertInertia(fn ($page) => $page
            ->where('summary.total_consumption', 40)
            ->where('summary.accounts_read', 1)
            ->has('readings.data', 1));
});

test('the category breakdown groups accounts read and consumption per category', function () {
    $month = now()->format('Y-m');

    $account1 = memberAccount(User::factory()->create());
    $account2 = memberAccount(User::factory()->create());

    meterReading($account1, $month, 30);
    meterReading($account2, $month, 20);

    $this->actingAs($this->treasurer)
        ->get(route('admin.reports.consumption.index'))
        ->assertInertia(fn ($page) => $page
            ->has('byCategory', 2)
            ->where('byCategory.0.category', $account1->billingCategory->name < $account2->billingCategory->name
                ? $account1->billingCategory->name
                : $account2->billingCategory->name));
});

test('users without reports.view cannot open the report', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('admin');

    $this->actingAs($user)
        ->get(route('admin.reports.consumption.index'))
        ->assertForbidden();
});

test('the pdf and csv exports download with the expected content types', function () {
    $account = memberAccount(User::factory()->create());
    meterReading($account, now()->format('Y-m'), 12);

    $this->actingAs($this->treasurer)
        ->get(route('admin.reports.consumption.pdf'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    $response = $this->actingAs($this->treasurer)
        ->get(route('admin.reports.consumption.csv'))
        ->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('text/csv')
        ->and($response->streamedContent())->toContain('Account')
        ->and($response->streamedContent())->toContain($account->account_number);
});

test('exports require the reports.export permission', function () {
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(['admin', 'reports.view']);

    $this->actingAs($viewer)
        ->get(route('admin.reports.consumption.pdf'))
        ->assertForbidden();
});
