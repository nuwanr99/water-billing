<?php

use App\Enums\AccountLedgerEntryType;
use App\Enums\BillStatus;
use App\Models\Bill;
use App\Models\BillingCategory;
use App\Models\MeterReading;
use App\Models\Payment;
use App\Models\SystemLedgerAccount;
use App\Models\User;
use App\Models\WaterAccount;
use App\Services\AccountLedgerService;
use App\Services\BillGenerationService;
use App\Services\PaymentService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SystemLedgerAccountSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(SystemLedgerAccountSeeder::class);

    $this->treasurer = User::factory()->create();
    $this->treasurer->assignRole('Treasurer');

    $this->cash = SystemLedgerAccount::query()->where('code', '1000')->firstOrFail();
});

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

test('a payment posts to both ledgers and issues a receipt', function () {
    ['account' => $account] = billedAccount(10); // total due 200.00

    $payment = app(PaymentService::class)->record($account, 200.00, $this->cash, $this->treasurer, 'slip-1');

    expect($payment->receipt_number)->toStartWith('RCPT-')
        ->and($payment->status->value)->toBe('completed')
        ->and((float) $payment->ledgerEntry->amount)->toBe(-200.00)
        ->and($payment->journal->is_posted)->toBeTrue()
        ->and((float) $payment->journal->total_debit)->toBe(200.00);

    expect(app(AccountLedgerService::class)->balanceFor($account))->toBe(0.00);

    // Journal: debit cash, credit water charges income.
    expect((float) $this->cash->lines()->sum('amount'))->toBe(200.00);
});

test('a full payment settles the bill; a partial one does not', function () {
    ['account' => $account, 'bill' => $bill] = billedAccount(10); // 200.00

    $service = app(PaymentService::class);

    $service->record($account, 50.00, $this->cash, $this->treasurer);
    expect($bill->refresh()->status)->toBe(BillStatus::Approved);

    $service->record($account, 150.00, $this->cash, $this->treasurer);
    expect($bill->refresh()->status)->toBe(BillStatus::Paid);
});

test('an overpayment settles the bill and leaves a credit balance', function () {
    ['account' => $account, 'bill' => $bill] = billedAccount(10); // 200.00

    app(PaymentService::class)->record($account, 250.00, $this->cash, $this->treasurer);

    expect($bill->refresh()->status)->toBe(BillStatus::Paid)
        ->and(app(AccountLedgerService::class)->balanceFor($account))->toBe(-50.00);
});

test('bills settle oldest-first across months', function () {
    ['account' => $account, 'bill' => $firstBill] = billedAccount(10); // 200.00

    $this->travel(1)->months();
    ['bill' => $secondBill] = billedAccount(10, $account); // total due 400.00

    app(PaymentService::class)->record($account, 200.00, $this->cash, $this->treasurer);

    expect($firstBill->refresh()->status)->toBe(BillStatus::Paid)
        ->and($secondBill->refresh()->status)->toBe(BillStatus::Approved)
        ->and(app(AccountLedgerService::class)->balanceFor($account))->toBe(200.00);
});

test('paying the bill total settles it even when a later penalty is owed', function () {
    ['account' => $account, 'bill' => $bill] = billedAccount(10); // 200.00

    app(AccountLedgerService::class)->post($account, AccountLedgerEntryType::Penalty, 10.00, 'Late fee');

    app(PaymentService::class)->record($account, 200.00, $this->cash, $this->treasurer);

    expect($bill->refresh()->status)->toBe(BillStatus::Paid)
        ->and(app(AccountLedgerService::class)->balanceFor($account))->toBe(10.00);
});

test('the treasurer records a payment through the collection flow', function () {
    ['account' => $account] = billedAccount(10);

    $this->actingAs($this->treasurer)
        ->get(route('admin.payments.collect.show', $account))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/payments/Record')
            ->where('account.balance', 200)
            ->where('lastBill.total_due', 200)
        );

    $this->actingAs($this->treasurer)
        ->post(route('admin.payments.collect.store', $account), [
            'amount' => 200.00,
            'destination_account_id' => $this->cash->id,
            'reference' => null,
            'paid_at' => now()->toDateString(),
        ])
        ->assertSessionHasNoErrors();

    $payment = Payment::firstOrFail();

    $this->actingAs($this->treasurer)
        ->get(route('admin.payments.receipt', $payment))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/payments/Receipt')
            ->where('payment.amount', 200)
            ->where('payment.balance_after', 0)
        );
});

test('a bank slip photo can be attached and viewed', function () {
    Storage::fake();

    ['account' => $account] = billedAccount(10);

    $this->actingAs($this->treasurer)
        ->post(route('admin.payments.collect.store', $account), [
            'amount' => 200.00,
            'destination_account_id' => $this->cash->id,
            'reference' => 'Deposit slip 991',
            'attachment' => UploadedFile::fake()->image('slip.jpg'),
            'paid_at' => now()->toDateString(),
        ])
        ->assertSessionHasNoErrors();

    $payment = Payment::firstOrFail();

    expect($payment->attachment_path)->not->toBeNull();
    Storage::assertExists($payment->attachment_path);

    $this->actingAs($this->treasurer)
        ->get(route('admin.payments.attachment', $payment))
        ->assertOk();
});

test('a member cannot reach the payment collection flow', function () {
    ['account' => $account] = billedAccount(10);

    $member = User::factory()->create();
    $member->assignRole('Member');

    $this->actingAs($member)->get(route('admin.payments.collect'))->assertForbidden();
    $this->actingAs($member)->post(route('admin.payments.collect.store', $account), [])->assertForbidden();
});
