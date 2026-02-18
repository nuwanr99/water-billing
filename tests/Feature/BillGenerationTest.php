<?php

use App\Enums\AccountLedgerEntryType;
use App\Enums\BillStatus;
use App\Events\BillGenerated;
use App\Models\AccountLedgerEntry;
use App\Models\BillingCategory;
use App\Models\MeterReading;
use App\Models\User;
use App\Models\WaterAccount;
use App\Services\AccountLedgerService;
use App\Services\BillGenerationService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

/**
 * An account on the spec §14 tariff (0-10 @ 20.00, 11-25 @ 35.00, 26+ @
 * 50.00, no service charges) with a reading for the current month.
 *
 * @return array{account: WaterAccount, reading: MeterReading, user: User}
 */
function billableAccount(float $consumption = 12.5): array
{
    $category = BillingCategory::factory()->create();

    $category->tiers()->createMany([
        ['lower_units' => 0, 'upper_units' => 10, 'rate_per_unit' => 20.00, 'service_charge' => 0],
        ['lower_units' => 10, 'upper_units' => 25, 'rate_per_unit' => 35.00, 'service_charge' => 0],
        ['lower_units' => 25, 'upper_units' => null, 'rate_per_unit' => 50.00, 'service_charge' => 0],
    ]);

    $account = WaterAccount::factory()->create(['billing_category_id' => $category->id, 'initial_reading' => 1000]);

    $reading = MeterReading::factory()
        ->for($account)
        ->forMonth(now()->format('Y-m'))
        ->create(['reading_value' => 1000 + $consumption, 'consumption' => $consumption]);

    return ['account' => $account, 'reading' => $reading, 'user' => User::factory()->create()];
}

test('confirming a reading generates an auto-approved bill with correct math', function () {
    Event::fake([BillGenerated::class]);

    ['reading' => $reading, 'user' => $user, 'account' => $account] = billableAccount(12.5);

    $bill = app(BillGenerationService::class)->generate($reading, $user);

    // (10 x 20.00) + (2.5 x 35.00) = 287.50
    expect((float) $bill->monthly_charge)->toBe(287.50)
        ->and((float) $bill->previous_balance)->toBe(0.00)
        ->and((float) $bill->total_due)->toBe(287.50)
        ->and($bill->status)->toBe(BillStatus::Approved)
        ->and($bill->is_current)->toBeTrue()
        ->and($bill->bill_number)->toStartWith('BILL-')
        ->and($bill->breakdown['summary']['total_due'])->toBe(287.5);

    expect(app(AccountLedgerService::class)->balanceFor($account))->toBe(287.50);

    Event::assertDispatched(BillGenerated::class);
});

test('unbilled charges are presented on the next bill and roll into the total', function () {
    ['reading' => $reading, 'user' => $user, 'account' => $account] = billableAccount(12.5);

    app(AccountLedgerService::class)->post($account, AccountLedgerEntryType::Charge, 250.00, 'Repair charge');

    $bill = app(BillGenerationService::class)->generate($reading, $user);

    expect((float) $bill->previous_balance)->toBe(250.00)
        ->and((float) $bill->total_due)->toBe(537.50)
        ->and($bill->breakdown['presented_entries'])->toHaveCount(1)
        ->and($bill->breakdown['presented_entries'][0]['description'])->toBe('Repair charge')
        ->and($bill->breakdown['summary']['debits'])->toEqual(250);
});

test('a second bill for the same month is rejected', function () {
    ['reading' => $reading, 'user' => $user] = billableAccount();

    $service = app(BillGenerationService::class);
    $service->generate($reading, $user);

    $service->generate($reading->refresh(), $user);
})->throws(ValidationException::class);

test('zero consumption still bills the first slab service charge', function () {
    $category = BillingCategory::factory()->create();
    $category->tiers()->createMany([
        ['lower_units' => 0, 'upper_units' => 10, 'rate_per_unit' => 20.00, 'service_charge' => 100],
        ['lower_units' => 10, 'upper_units' => null, 'rate_per_unit' => 35.00, 'service_charge' => 150],
    ]);

    $account = WaterAccount::factory()->create(['billing_category_id' => $category->id, 'initial_reading' => 1000]);
    $reading = MeterReading::factory()->for($account)->forMonth(now()->format('Y-m'))
        ->create(['reading_value' => 1000, 'consumption' => 0]);

    $bill = app(BillGenerationService::class)->generate($reading, User::factory()->create());

    expect((float) $bill->usage_charge)->toBe(0.00)
        ->and((float) $bill->service_charge)->toBe(100.00)
        ->and((float) $bill->total_due)->toBe(100.00);
});

test('reissuing reverses the original and regenerates from the corrected reading', function () {
    ['reading' => $reading, 'user' => $user, 'account' => $account] = billableAccount(12.5);

    $service = app(BillGenerationService::class);
    $original = $service->generate($reading, $user);

    // Correct the reading from 1012.50 to 1010.00 → 10 units → 200.00.
    $reissued = $service->reissue($original, 1010.00, $user);

    expect($reissued->is_reissue)->toBeTrue()
        ->and($reissued->supersedes_bill_id)->toBe($original->id)
        ->and((float) $reissued->monthly_charge)->toBe(200.00)
        ->and($original->refresh()->is_current)->toBeNull()
        ->and($reissued->is_current)->toBeTrue();

    // Ledger tape: +287.50, −287.50, +200.00 → balance 200.00.
    expect(app(AccountLedgerService::class)->balanceFor($account))->toBe(200.00);

    $this->assertDatabaseHas('account_ledger_entries', [
        'water_account_id' => $account->id,
        'entry_type' => AccountLedgerEntryType::Reversal->value,
        'amount' => '-287.50',
    ]);
});

test('the overdue command flips past-due bills and posts the late-fee penalty', function () {
    ['reading' => $reading, 'user' => $user, 'account' => $account] = billableAccount(12.5);

    $bill = app(BillGenerationService::class)->generate($reading, $user);
    $bill->update(['due_date' => now()->subDays(3)->toDateString()]);

    $this->artisan('bills:mark-overdue')->assertSuccessful();

    // late_fee_percent 2.50 (factory default) × 287.50 = 7.19
    expect($bill->refresh()->status)->toBe(BillStatus::Overdue);

    $this->assertDatabaseHas('account_ledger_entries', [
        'water_account_id' => $account->id,
        'entry_type' => AccountLedgerEntryType::Penalty->value,
        'amount' => '7.19',
    ]);

    // Idempotent: a second run posts nothing new.
    $this->artisan('bills:mark-overdue')->assertSuccessful();

    expect(AccountLedgerEntry::query()->where('entry_type', AccountLedgerEntryType::Penalty->value)->count())->toBe(1);
});

test('a billed reading can no longer be edited', function () {
    $this->seed(RolePermissionSeeder::class);

    ['reading' => $reading, 'user' => $user] = billableAccount();

    app(BillGenerationService::class)->generate($reading, $user);

    $controller = User::factory()->create();
    $controller->assignRole('Water Controller');

    $this->actingAs($controller)
        ->put(route('meter-readings.update', $reading), ['reading_value' => 1020.00])
        ->assertForbidden();
});
