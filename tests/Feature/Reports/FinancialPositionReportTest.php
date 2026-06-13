<?php

use App\Models\SystemLedgerAccount;
use App\Models\User;
use App\Services\ExpenseService;
use App\Services\PaymentService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SystemLedgerAccountSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(SystemLedgerAccountSeeder::class);

    $this->treasurer = User::factory()->create();
    $this->treasurer->assignRole('Treasurer');

    $this->cash = SystemLedgerAccount::query()->where('code', '1000')->firstOrFail();
    $this->income = SystemLedgerAccount::query()->where('code', '4000')->firstOrFail();
    $this->maintenance = SystemLedgerAccount::query()->where('code', '5000')->firstOrFail();
});

test('the financial position totals income, expense, and net for the period, and the statement balances', function () {
    ['account' => $account] = billedAccount(10);

    app(PaymentService::class)->record($account, 300, $this->cash, $this->treasurer);
    app(ExpenseService::class)->record(now(), 120, $this->maintenance, $this->cash, 'Pump service', $this->treasurer);

    // Chart of accounts, ordered by type (asset, liability, equity, income,
    // expense) then code: [0] 1000 Cash, [1] 1100 Bank, [2] 4000 Water
    // Charges Income, [3] 4100 Penalties & Other Income, [4] 5000
    // Maintenance Expense, [5] 5900 Other Expenses.
    $this->actingAs($this->treasurer)
        ->get(route('admin.reports.financial-position.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/reports/FinancialPosition')
            ->where('summary.total_income', 300)
            ->where('summary.total_expense', 120)
            ->where('summary.net', 180)
            ->where('summary.total_debits', 420)
            ->where('summary.total_credits', 420)
            ->where('summary.is_balanced', true)
            ->has('trialBalance', 6)
            ->where('trialBalance.0.code', '1000')
            ->where('trialBalance.0.debit', 300)
            ->where('trialBalance.0.credit', 120)
            ->where('trialBalance.0.balance', 180)
            ->where('trialBalance.2.code', '4000')
            ->where('trialBalance.2.debit', 0)
            ->where('trialBalance.2.credit', 300)
            ->where('trialBalance.2.balance', 300)
            ->where('trialBalance.4.code', '5000')
            ->where('trialBalance.4.debit', 120)
            ->where('trialBalance.4.credit', 0)
            ->where('trialBalance.4.balance', 120)
            // An untouched account still appears, at a zero balance.
            ->where('trialBalance.5.code', '5900')
            ->where('trialBalance.5.debit', 0)
            ->where('trialBalance.5.credit', 0)
            ->where('trialBalance.5.balance', 0));
});

test('a payment dated outside the requested period is excluded', function () {
    ['account' => $account] = billedAccount(10);

    $lastMonth = now()->subMonthNoOverflow();
    $payment = app(PaymentService::class)->record($account, 300, $this->cash, $this->treasurer, paidAt: $lastMonth);

    // Belt and braces: move both the payment and its ledger entry outside the period explicitly.
    $payment->update(['paid_at' => $lastMonth]);
    $payment->journal()->update(['entry_date' => $lastMonth->toDateString()]);

    $this->actingAs($this->treasurer)
        ->get(route('admin.reports.financial-position.index', ['period_type' => 'month', 'month' => now()->format('Y-m')]))
        ->assertInertia(fn ($page) => $page
            ->where('summary.total_income', 0)
            ->where('summary.total_debits', 0)
            ->where('summary.total_credits', 0)
            ->where('summary.is_balanced', true));

    $this->actingAs($this->treasurer)
        ->get(route('admin.reports.financial-position.index', ['period_type' => 'month', 'month' => $lastMonth->format('Y-m')]))
        ->assertInertia(fn ($page) => $page
            ->where('summary.total_income', 300)
            ->where('summary.total_debits', 300)
            ->where('summary.total_credits', 300));
});

test('users without reports.view cannot open the report', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('admin');

    $this->actingAs($user)
        ->get(route('admin.reports.financial-position.index'))
        ->assertForbidden();
});

test('the pdf and csv exports download with the expected content types', function () {
    ['account' => $account] = billedAccount(10);
    app(PaymentService::class)->record($account, 300, $this->cash, $this->treasurer);

    $this->actingAs($this->treasurer)
        ->get(route('admin.reports.financial-position.pdf'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    $response = $this->actingAs($this->treasurer)
        ->get(route('admin.reports.financial-position.csv'))
        ->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('text/csv')
        ->and($response->streamedContent())->toContain('Code')
        ->and($response->streamedContent())->toContain('Water Charges Income');
});

test('exports require the reports.export permission', function () {
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(['admin', 'reports.view']);

    $this->actingAs($viewer)
        ->get(route('admin.reports.financial-position.pdf'))
        ->assertForbidden();
});
