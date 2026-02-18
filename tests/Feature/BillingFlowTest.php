<?php

use App\Models\AccountLedgerEntry;
use App\Models\Bill;
use App\Models\BillingCategory;
use App\Models\MeterReading;
use App\Models\User;
use App\Models\WaterAccount;
use App\Services\BillGenerationService;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->waterController = User::factory()->create();
    $this->waterController->assignRole('Water Controller');

    $this->treasurer = User::factory()->create();
    $this->treasurer->assignRole('Treasurer');
});

/**
 * @return array{account: WaterAccount, reading: MeterReading}
 */
function fieldAccount(): array
{
    $category = BillingCategory::factory()->create();
    $category->tiers()->createMany([
        ['lower_units' => 0, 'upper_units' => 10, 'rate_per_unit' => 20.00, 'service_charge' => 0],
        ['lower_units' => 10, 'upper_units' => null, 'rate_per_unit' => 35.00, 'service_charge' => 0],
    ]);

    $account = WaterAccount::factory()->create(['billing_category_id' => $category->id, 'initial_reading' => 1000]);

    $reading = MeterReading::factory()->for($account)->forMonth(now()->format('Y-m'))
        ->create(['reading_value' => 1012.50, 'consumption' => 12.50]);

    return ['account' => $account, 'reading' => $reading];
}

test('a water controller previews and confirms a bill on-site', function () {
    ['reading' => $reading] = fieldAccount();

    $this->actingAs($this->waterController)
        ->get(route('bills.preview', $reading))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('bills/Preview')
            ->where('preview.summary.this_month', 287.5)
            ->where('preview.summary.total_due', 287.5)
        );

    $this->actingAs($this->waterController)
        ->post(route('bills.store', $reading))
        ->assertSessionHasNoErrors();

    $bill = Bill::firstOrFail();

    $this->actingAs($this->waterController)
        ->get(route('bills.show', $bill))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('bills/Show')
            ->where('bill.total_due', 287.5)
        );
});

test('a member without permissions cannot reach the billing flow', function () {
    ['reading' => $reading] = fieldAccount();

    $member = User::factory()->create();
    $member->assignRole('Member');

    $this->actingAs($member)->get(route('bills.preview', $reading))->assertForbidden();
    $this->actingAs($member)->post(route('bills.store', $reading))->assertForbidden();
});

test('a treasurer records a printable charge on an account', function () {
    ['account' => $account] = fieldAccount();

    $this->actingAs($this->treasurer)
        ->post(route('admin.water-accounts.charges.store', $account), [
            'type' => 'charge',
            'amount' => 250.00,
            'description' => 'Repair charge — replaced valve',
        ])
        ->assertSessionHasNoErrors();

    $entry = AccountLedgerEntry::firstOrFail();

    expect($entry->document_number)->toStartWith('CHG-')
        ->and((float) $entry->running_balance)->toBe(250.00);

    $this->actingAs($this->treasurer)
        ->get(route('admin.charges.print', $entry))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/charges/Print'));
});

test('a non-adjustment charge must be positive', function () {
    ['account' => $account] = fieldAccount();

    $this->actingAs($this->treasurer)
        ->post(route('admin.water-accounts.charges.store', $account), [
            'type' => 'penalty',
            'amount' => -50.00,
            'description' => 'Bad penalty',
        ])
        ->assertSessionHasErrors('amount');
});

test('the statement page lists the account ledger with running balances', function () {
    ['account' => $account, 'reading' => $reading] = fieldAccount();

    app(BillGenerationService::class)->generate($reading, $this->waterController);

    $this->actingAs($this->treasurer)
        ->get(route('admin.water-accounts.statement', $account))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/water-accounts/Statement')
            ->where('account.balance', 287.5)
            ->count('entries.data', 1)
            ->where('entries.data.0.type', 'water_charge')
        );
});

test('an admin reissues a bill from the detail page', function () {
    ['reading' => $reading] = fieldAccount();

    $bill = app(BillGenerationService::class)->generate($reading, $this->waterController);

    $this->actingAs($this->treasurer)
        ->post(route('admin.bills.reissue', $bill), ['reading_value' => 1010.00])
        ->assertSessionHasNoErrors();

    $reissued = Bill::query()->where('is_reissue', true)->firstOrFail();

    expect((float) $reissued->monthly_charge)->toBe(200.00)
        ->and($bill->refresh()->is_current)->toBeNull();
});

test('the admin bills index lists current bills', function () {
    ['reading' => $reading] = fieldAccount();

    app(BillGenerationService::class)->generate($reading, $this->waterController);

    $this->actingAs($this->treasurer)
        ->get(route('admin.bills.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/bills/Index')
            ->count('bills.data', 1)
            ->where('bills.data.0.total_due', 287.5)
        );
});
