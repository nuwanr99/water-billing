<?php

use App\Models\Expense;
use App\Models\MaintenanceJob;
use App\Models\SystemLedgerAccount;
use App\Models\User;
use App\Services\ExpenseService;
use Carbon\CarbonInterface;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SystemLedgerAccountSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(SystemLedgerAccountSeeder::class);

    $this->treasurer = User::factory()->create();
    $this->treasurer->assignRole('Treasurer');

    $this->maintenance = SystemLedgerAccount::query()->where('code', '5000')->firstOrFail();
    $this->other = SystemLedgerAccount::query()->where('code', '5900')->firstOrFail();
    $this->cash = SystemLedgerAccount::query()->where('code', '1000')->firstOrFail();
});

/**
 * Record an expense via the domain service so its journal stays consistent.
 */
function recordExpense(
    SystemLedgerAccount $category,
    SystemLedgerAccount $paidFrom,
    float $amount,
    ?CarbonInterface $date = null,
    ?MaintenanceJob $job = null,
): Expense {
    return app(ExpenseService::class)->record(
        $date ?? now(),
        $amount,
        $category,
        $paidFrom,
        'Test expense',
        User::factory()->create(),
        $job?->id,
    );
}

test('the expense summary totals the current period expenses', function () {
    recordExpense($this->maintenance, $this->cash, 1000);
    recordExpense($this->other, $this->cash, 500);

    $this->actingAs($this->treasurer)
        ->get(route('admin.reports.expenses.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/reports/ExpenseSummary')
            ->where('summary.total_expenditure', 1500)
            ->where('summary.expense_count', 2)
            ->where('summary.category_count', 2)
            ->has('expenses.data', 2)
            ->has('byCategory', 2));
});

test('expenses outside the requested period are excluded', function () {
    recordExpense($this->maintenance, $this->cash, 1000, now());
    recordExpense($this->maintenance, $this->cash, 2000, now()->subMonthNoOverflow());

    $this->actingAs($this->treasurer)
        ->get(route('admin.reports.expenses.index', ['period_type' => 'month', 'month' => now()->format('Y-m')]))
        ->assertInertia(fn ($page) => $page
            ->where('summary.expense_count', 1)
            ->where('summary.total_expenditure', 1000));

    $this->actingAs($this->treasurer)
        ->get(route('admin.reports.expenses.index', ['period_type' => 'month', 'month' => now()->subMonthNoOverflow()->format('Y-m')]))
        ->assertInertia(fn ($page) => $page
            ->where('summary.expense_count', 1)
            ->where('summary.total_expenditure', 2000));
});

test('the category filter narrows the summary and expense lines', function () {
    recordExpense($this->maintenance, $this->cash, 1000);
    recordExpense($this->other, $this->cash, 500);

    $this->actingAs($this->treasurer)
        ->get(route('admin.reports.expenses.index', ['category' => $this->maintenance->id]))
        ->assertInertia(fn ($page) => $page
            ->where('summary.expense_count', 1)
            ->where('summary.total_expenditure', 1000)
            ->has('expenses.data', 1)
            ->where('expenses.data.0.category', $this->maintenance->name));
});

test('the job filter narrows expense lines and the byCategory table still reconciles', function () {
    $job = MaintenanceJob::factory()->create();

    recordExpense($this->maintenance, $this->cash, 1000, now(), $job);
    recordExpense($this->maintenance, $this->cash, 300);

    $this->actingAs($this->treasurer)
        ->get(route('admin.reports.expenses.index', ['job' => $job->id]))
        ->assertInertia(fn ($page) => $page
            ->where('summary.expense_count', 1)
            ->where('summary.total_expenditure', 1000)
            ->has('expenses.data', 1)
            ->where('expenses.data.0.job_number', $job->job_number)
            ->where('byCategory.0.expense_count', 1)
            ->where('byCategory.0.total', 1000));
});

test('users without reports.view cannot open the report', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('admin');

    $this->actingAs($user)
        ->get(route('admin.reports.expenses.index'))
        ->assertForbidden();
});

test('the pdf and csv exports download with the expected content types', function () {
    recordExpense($this->maintenance, $this->cash, 1000);

    $this->actingAs($this->treasurer)
        ->get(route('admin.reports.expenses.pdf'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    $response = $this->actingAs($this->treasurer)
        ->get(route('admin.reports.expenses.csv'))
        ->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('text/csv')
        ->and($response->streamedContent())->toContain('Expense number');
});

test('exports require the reports.export permission', function () {
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(['admin', 'reports.view']);

    $this->actingAs($viewer)
        ->get(route('admin.reports.expenses.pdf'))
        ->assertForbidden();
});
