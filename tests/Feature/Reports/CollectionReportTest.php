<?php

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\SystemLedgerAccount;
use App\Models\User;
use App\Services\PaymentService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SystemLedgerAccountSeeder;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(SystemLedgerAccountSeeder::class);

    $this->treasurer = User::factory()->create();
    $this->treasurer->assignRole('Treasurer');

    $this->cash = SystemLedgerAccount::query()->where('code', '1000')->firstOrFail();
});

test('the collection report totals completed payments in the period', function () {
    ['account' => $first] = billedAccount(10);
    ['account' => $second] = billedAccount(20);

    app(PaymentService::class)->record($first, 100.00, $this->cash, $this->treasurer);
    app(PaymentService::class)->record($second, 150.00, $this->cash, $this->treasurer);

    $this->actingAs($this->treasurer)
        ->get(route('admin.reports.collections.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/reports/Collections')
            ->where('summary.total_collected', 250)
            ->where('summary.payment_count', 2)
            ->where('summary.manual_total', 250)
            ->where('summary.payhere_total', 0)
            ->has('payments.data', 2));
});

test('collection efficiency is computed against the period total billed', function () {
    // Total billed this month: 20 x 10 = 200.
    ['account' => $account] = billedAccount(10);

    app(PaymentService::class)->record($account, 100.00, $this->cash, $this->treasurer);

    $this->actingAs($this->treasurer)
        ->get(route('admin.reports.collections.index'))
        ->assertInertia(fn ($page) => $page
            ->where('summary.total_billed', 200)
            ->where('summary.total_collected', 100)
            ->where('summary.collection_efficiency', 50));
});

test('collection efficiency is null when nothing is billed for the period', function () {
    $this->actingAs($this->treasurer)
        ->get(route('admin.reports.collections.index'))
        ->assertInertia(fn ($page) => $page
            ->where('summary.total_billed', 0)
            ->where('summary.collection_efficiency', null));
});

test('payments outside the requested period are excluded', function () {
    ['account' => $account] = billedAccount(10);

    $payment = app(PaymentService::class)->record($account, 100.00, $this->cash, $this->treasurer);

    $lastMonth = now()->subMonthNoOverflow();
    $payment->update(['paid_at' => $lastMonth]);

    $this->actingAs($this->treasurer)
        ->get(route('admin.reports.collections.index', ['period_type' => 'month', 'month' => now()->format('Y-m')]))
        ->assertInertia(fn ($page) => $page
            ->where('summary.payment_count', 0)
            ->where('summary.total_collected', 0));

    $this->actingAs($this->treasurer)
        ->get(route('admin.reports.collections.index', ['period_type' => 'month', 'month' => $lastMonth->format('Y-m')]))
        ->assertInertia(fn ($page) => $page
            ->where('summary.payment_count', 1)
            ->where('summary.total_collected', 100));
});

test('the method filter narrows the summary and table', function () {
    ['account' => $first] = billedAccount(10);
    ['account' => $second] = billedAccount(20);

    app(PaymentService::class)->record($first, 100.00, $this->cash, $this->treasurer);

    $gateway = Payment::query()->create([
        'receipt_number' => 'RCPT-TEST-1',
        'public_token' => (string) Str::uuid(),
        'water_account_id' => $second->id,
        'method' => PaymentMethod::Payhere,
        'status' => PaymentStatus::Completed,
        'amount' => 150.00,
        'paid_at' => now(),
    ]);

    $this->actingAs($this->treasurer)
        ->get(route('admin.reports.collections.index', ['method' => 'payhere']))
        ->assertInertia(fn ($page) => $page
            ->where('summary.payment_count', 1)
            ->where('summary.total_collected', 150)
            ->has('payments.data', 1)
            ->where('payments.data.0.receipt_number', (string) $gateway->receipt_number));
});

test('users without reports.view cannot open the report', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('admin');

    $this->actingAs($user)
        ->get(route('admin.reports.collections.index'))
        ->assertForbidden();
});

test('the pdf and csv exports download with the expected content types', function () {
    ['account' => $account] = billedAccount(10);
    app(PaymentService::class)->record($account, 100.00, $this->cash, $this->treasurer);

    $this->actingAs($this->treasurer)
        ->get(route('admin.reports.collections.pdf'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    $response = $this->actingAs($this->treasurer)
        ->get(route('admin.reports.collections.csv'))
        ->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('text/csv')
        ->and($response->streamedContent())->toContain('Receipt number');
});

test('exports require the reports.export permission', function () {
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(['admin', 'reports.view']);

    $this->actingAs($viewer)
        ->get(route('admin.reports.collections.pdf'))
        ->assertForbidden();
});
