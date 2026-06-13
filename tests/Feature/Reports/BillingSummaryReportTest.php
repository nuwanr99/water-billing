<?php

use App\Enums\BillStatus;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SystemLedgerAccountSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(SystemLedgerAccountSeeder::class);

    $this->treasurer = User::factory()->create();
    $this->treasurer->assignRole('Treasurer');
});

test('the billing summary totals the current month bills', function () {
    ['bill' => $first] = billedAccount(10);
    ['bill' => $second] = billedAccount(5);

    $this->actingAs($this->treasurer)
        ->get(route('admin.reports.billing.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/reports/BillingSummary')
            ->where('summary.total_billed', 300)
            ->where('summary.bill_count', 2)
            ->where('summary.status_counts.approved', 2)
            ->has('bills.data', 2));

    expect((float) $first->total_due + (float) $second->total_due)->toBe(300.0);
});

test('bills outside the requested period are excluded', function () {
    ['account' => $account] = billedAccount(10);

    // A second bill for the same account, last month.
    $lastMonth = now()->subMonthNoOverflow()->format('Y-m');
    $account->bills()->update(['billing_month' => $lastMonth]);
    $account->readings()->update(['billing_month' => $lastMonth]);

    billedAccount(5);

    $this->actingAs($this->treasurer)
        ->get(route('admin.reports.billing.index', ['period_type' => 'month', 'month' => now()->format('Y-m')]))
        ->assertInertia(fn ($page) => $page
            ->where('summary.bill_count', 1)
            ->where('summary.total_billed', 100));

    $this->actingAs($this->treasurer)
        ->get(route('admin.reports.billing.index', ['period_type' => 'month', 'month' => $lastMonth]))
        ->assertInertia(fn ($page) => $page
            ->where('summary.bill_count', 1)
            ->where('summary.total_billed', 200));
});

test('the category breakdown groups billed and paid totals', function () {
    ['account' => $account, 'bill' => $bill] = billedAccount(10);
    $bill->update(['status' => BillStatus::Paid]);

    $this->actingAs($this->treasurer)
        ->get(route('admin.reports.billing.index'))
        ->assertInertia(fn ($page) => $page
            ->has('byCategory', 1)
            ->where('byCategory.0.category', $account->billingCategory->name)
            ->where('byCategory.0.total_billed', 200)
            ->where('byCategory.0.total_paid', 200)
            ->where('byCategory.0.total_outstanding', 0));
});

test('selecting an account narrows the report to its statement', function () {
    ['account' => $account] = billedAccount(10);
    billedAccount(5);

    $this->actingAs($this->treasurer)
        ->get(route('admin.reports.billing.index', ['account' => $account->id]))
        ->assertInertia(fn ($page) => $page
            ->where('statement.account_number', $account->account_number)
            ->where('summary.bill_count', 1)
            ->has('bills.data', 1));
});

test('users without reports.view cannot open the report', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('admin');

    $this->actingAs($user)
        ->get(route('admin.reports.billing.index'))
        ->assertForbidden();
});

test('the pdf and csv exports download with the expected content types', function () {
    billedAccount(10);

    $this->actingAs($this->treasurer)
        ->get(route('admin.reports.billing.pdf'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    $response = $this->actingAs($this->treasurer)
        ->get(route('admin.reports.billing.csv'))
        ->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('text/csv')
        ->and($response->streamedContent())->toContain('Bill number');
});

test('exports require the reports.export permission', function () {
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(['admin', 'reports.view']);

    $this->actingAs($viewer)
        ->get(route('admin.reports.billing.pdf'))
        ->assertForbidden();
});
