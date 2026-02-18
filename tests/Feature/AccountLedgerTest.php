<?php

use App\Enums\AccountLedgerEntryType;
use App\Models\WaterAccount;
use App\Services\AccountLedgerService;
use App\Services\RunningNumberService;

test('posting an entry stamps the running balance and updates the cached balance', function () {
    $account = WaterAccount::factory()->create();
    $ledger = app(AccountLedgerService::class);

    $charge = $ledger->post($account, AccountLedgerEntryType::Charge, 250.00, 'Repair charge');
    $payment = $ledger->post($account, AccountLedgerEntryType::Payment, -100.00, 'Cash payment');

    expect((float) $charge->running_balance)->toBe(250.00)
        ->and((float) $payment->running_balance)->toBe(150.00)
        ->and($ledger->balanceFor($account))->toBe(150.00);

    $this->assertDatabaseHas('water_account_balances', [
        'water_account_id' => $account->id,
        'balance' => '150.00',
    ]);
});

test('entriesAfter returns only entries past the cutoff, in statement order', function () {
    $account = WaterAccount::factory()->create();
    $ledger = app(AccountLedgerService::class);

    $first = $ledger->post($account, AccountLedgerEntryType::Charge, 100.00, 'First');
    $second = $ledger->post($account, AccountLedgerEntryType::Penalty, 50.00, 'Second');

    expect($ledger->entriesAfter($account, null)->pluck('id')->all())->toBe([$first->id, $second->id])
        ->and($ledger->entriesAfter($account, $first->id)->pluck('id')->all())->toBe([$second->id]);
});

test('running numbers increment sequentially within a series', function () {
    $numbers = app(RunningNumberService::class);

    $year = now()->year;

    expect($numbers->next('bill'))->toBe("BILL-{$year}-00001")
        ->and($numbers->next('bill'))->toBe("BILL-{$year}-00002")
        ->and($numbers->next('charge'))->toBe("CHG-{$year}-00001");
});
