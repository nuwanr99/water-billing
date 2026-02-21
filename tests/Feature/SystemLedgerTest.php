<?php

use App\Services\SystemLedgerService;
use Database\Seeders\SystemLedgerAccountSeeder;

test('a balanced journal posts with lines and totals', function () {
    $this->seed(SystemLedgerAccountSeeder::class);

    $entry = app(SystemLedgerService::class)->post('Payment received', [
        ['account' => '1000', 'amount' => 750.00],
        ['account' => '4000', 'amount' => -750.00],
    ]);

    expect($entry->is_balanced)->toBeTrue()
        ->and($entry->is_posted)->toBeTrue()
        ->and((float) $entry->total_debit)->toBe(750.00)
        ->and((float) $entry->total_credit)->toBe(750.00)
        ->and($entry->lines)->toHaveCount(2)
        ->and($entry->reference_number)->toStartWith('JRN-');
});

test('an unbalanced journal is refused', function () {
    $this->seed(SystemLedgerAccountSeeder::class);

    app(SystemLedgerService::class)->post('Broken journal', [
        ['account' => '1000', 'amount' => 750.00],
        ['account' => '4000', 'amount' => -700.00],
    ]);
})->throws(InvalidArgumentException::class, 'sum to zero');
