<?php

use App\Models\AccountLedgerEntry;
use App\Models\SystemLedgerAccount;
use App\Models\User;
use App\Services\PaymentService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SystemLedgerAccountSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(SystemLedgerAccountSeeder::class);

    $this->treasurer = User::factory()->create();
    $this->treasurer->assignRole('Treasurer');
});

test('an unpaid bill lands in the current aging bucket', function () {
    ['account' => $account] = billedAccount(10); // 200.00 billed, unpaid

    $this->actingAs($this->treasurer)
        ->get(route('admin.reports.arrears.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/reports/Arrears')
            ->where('summary.total_arrears', 200)
            ->where('summary.account_count', 1)
            ->where('aging.current', 200)
            ->where('aging.30_59', 0)
            ->where('aging.60_89', 0)
            ->where('aging.90_plus', 0)
            ->where('accounts.data.0.account_number', $account->account_number)
            ->where('accounts.data.0.balance', 200)
            ->where('accounts.data.0.aging.current', 200));
});

test('a backdated charge ages into the 30-59 day bucket', function () {
    billedAccount(10); // 200.00 billed, unpaid

    $entry = AccountLedgerEntry::query()->where('amount', '>', 0)->firstOrFail();

    DB::table('account_ledger_entries')
        ->where('id', $entry->id)
        ->update(['created_at' => now()->subDays(45)]);

    $this->actingAs($this->treasurer)
        ->get(route('admin.reports.arrears.index'))
        ->assertInertia(fn ($page) => $page
            ->where('aging.current', 0)
            ->where('aging.30_59', 200)
            ->where('aging.60_89', 0)
            ->where('aging.90_plus', 0)
            ->where('accounts.data.0.aging.30_59', 200));
});

test('the arrears total and account count sum across accounts', function () {
    billedAccount(10); // 200.00
    billedAccount(5); // 100.00

    $this->actingAs($this->treasurer)
        ->get(route('admin.reports.arrears.index'))
        ->assertInertia(fn ($page) => $page
            ->where('summary.total_arrears', 300)
            ->where('summary.account_count', 2)
            ->has('accounts.data', 2));
});

test('a fully paid account is excluded from arrears', function () {
    ['account' => $account] = billedAccount(10); // 200.00 billed
    billedAccount(5); // 100.00 billed, remains unpaid

    $cash = SystemLedgerAccount::query()->where('code', '1000')->firstOrFail();
    app(PaymentService::class)->record($account, 200.00, $cash, $this->treasurer);

    $this->actingAs($this->treasurer)
        ->get(route('admin.reports.arrears.index'))
        ->assertInertia(fn ($page) => $page
            ->where('summary.total_arrears', 100)
            ->where('summary.account_count', 1)
            ->has('accounts.data', 1));
});

test('users without reports.view cannot open the report', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('admin');

    $this->actingAs($user)
        ->get(route('admin.reports.arrears.index'))
        ->assertForbidden();
});

test('the pdf and csv exports download with the expected content types', function () {
    billedAccount(10);

    $this->actingAs($this->treasurer)
        ->get(route('admin.reports.arrears.pdf'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    $response = $this->actingAs($this->treasurer)
        ->get(route('admin.reports.arrears.csv'))
        ->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('text/csv')
        ->and($response->streamedContent())->toContain('Account number');
});

test('exports require the reports.export permission', function () {
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(['admin', 'reports.view']);

    $this->actingAs($viewer)
        ->get(route('admin.reports.arrears.pdf'))
        ->assertForbidden();
});
