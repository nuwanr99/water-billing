<?php

use App\Enums\BillStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\WaterAccountStatus;
use App\Models\Bill;
use App\Models\Complaint;
use App\Models\Expense;
use App\Models\InventoryItem;
use App\Models\MaintenanceJob;
use App\Models\MeterReading;
use App\Models\Payment;
use App\Models\SystemLedgerLine;
use App\Models\User;
use App\Models\WaterAccount;
use App\Services\AccountLedgerService;
use Database\Seeders\DemoSeeder;

test('the demo seeder builds six months of consistent operations', function () {
    $this->seed(DemoSeeder::class);

    // Population: 20 ordinary members plus 4 exco officers (who must be
    // members with connections of their own), 29 accounts in the villages.
    expect(User::role('Member')->count())->toBe(24)
        ->and(WaterAccount::count())->toBe(29)
        ->and(WaterAccount::where('connection_address', 'like', '%Medamahanuwara%')->count())->toBe(29);

    // Every exco officer satisfies the membership rule: Member role and
    // at least one water account.
    foreach (['saman@demo.lk', 'herath@demo.lk', 'anula@demo.lk', 'tikiri@demo.lk'] as $email) {
        $officer = User::query()->where('email', $email)->firstOrFail();

        expect($officer->hasRole('Member'))->toBeTrue()
            ->and($officer->waterAccounts()->count())->toBeGreaterThanOrEqual(1);
    }

    // Six months of readings, each turned into a bill.
    expect(MeterReading::query()->distinct('billing_month')->count('billing_month'))->toBe(6)
        ->and(Bill::count())->toBe(MeterReading::count())
        ->and(Bill::count())->toBeGreaterThanOrEqual(29 * 5);

    // Mixed payment behaviour: settled, partial, and overdue bills exist.
    expect(Payment::where('status', PaymentStatus::Completed)->count())->toBeGreaterThan(50)
        ->and(Bill::where('status', BillStatus::Paid)->count())->toBeGreaterThan(0)
        ->and(Bill::where('status', BillStatus::Overdue)->count())->toBeGreaterThan(0);

    // The two businesses check out online, so PayHere payments exist
    // alongside the manual receipts, with consistent ledger postings.
    expect(Payment::where('method', PaymentMethod::Payhere)->where('status', PaymentStatus::Completed)->count())->toBeGreaterThan(0);

    Payment::where('method', PaymentMethod::Payhere)->get()->each(function (Payment $payment): void {
        expect($payment->payhere_reference)->not->toBeNull()
            ->and($payment->receipt_number)->not->toBeNull()
            ->and($payment->ledgerEntry)->not->toBeNull()
            ->and($payment->journal)->not->toBeNull();
    });

    // A couple of defaulting accounts are marked inactive but keep their
    // billing history.
    $inactiveAccounts = WaterAccount::where('status', WaterAccountStatus::Inactive)->get();

    expect($inactiveAccounts->count())->toBeGreaterThanOrEqual(1);

    foreach ($inactiveAccounts as $inactiveAccount) {
        expect($inactiveAccount->bills()->count())->toBeGreaterThan(0);
    }

    // Both ledgers stay internally consistent: every posted journal is
    // balanced, and every stored account balance matches a recalculation.
    expect((float) SystemLedgerLine::sum('amount'))->toBe(0.0);

    $ledger = app(AccountLedgerService::class);

    WaterAccount::each(function (WaterAccount $account) use ($ledger): void {
        $stored = $ledger->balanceFor($account);

        expect($ledger->recalculate($account))->toBe($stored);
    });

    // Operations around the network: complaints, jobs, costs, and stock.
    // Current-month events dated after "today" are skipped by design, so
    // the tail of the complaint plan may not have landed yet.
    $complaintCount = Complaint::count();

    expect($complaintCount)->toBeGreaterThanOrEqual(8)->toBeLessThanOrEqual(10)
        ->and(Complaint::where('status', 'closed')->count())->toBeGreaterThan(0)
        ->and(MaintenanceJob::count())->toBeGreaterThanOrEqual(5)
        ->and(MaintenanceJob::where('status', 'completed')->count())->toBeGreaterThan(0)
        ->and(Expense::count())->toBeGreaterThan(10)
        ->and(InventoryItem::count())->toBe(6)
        ->and(InventoryItem::where('quantity_in_stock', '<', 0)->count())->toBe(0);

    // At least one active item has dipped to or below its reorder level,
    // so the Inventory report has a low-stock row to show.
    $lowStockItems = InventoryItem::where('is_active', true)
        ->whereColumn('quantity_in_stock', '<=', 'reorder_level')
        ->get();

    expect($lowStockItems->count())->toBeGreaterThanOrEqual(1);

    foreach ($lowStockItems as $lowStockItem) {
        expect($lowStockItem->isLowStock())->toBeTrue();
    }

    // Running the seeder again must be a no-op (the top-up path finds
    // nothing missing).
    $billCount = Bill::count();

    $this->seed(DemoSeeder::class);

    expect(WaterAccount::count())->toBe(29)
        ->and(Bill::count())->toBe($billCount)
        ->and(Complaint::count())->toBe($complaintCount);
});
