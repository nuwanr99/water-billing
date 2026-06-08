<?php

use App\Models\SystemLedgerAccount;
use App\Models\User;
use App\Services\PaymentService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SystemLedgerAccountSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(SystemLedgerAccountSeeder::class);

    $this->member = User::factory()->create();
    $this->member->assignRole('Member');

    $this->staff = User::factory()->create();
    $this->cash = SystemLedgerAccount::query()->where('code', '1000')->firstOrFail();
});

test('a member sees only their own completed payments', function () {
    $account = memberAccount($this->member);
    billedAccount(10, $account); // 200.00

    $payment = app(PaymentService::class)->record($account, 200.00, $this->cash, $this->staff);

    $otherMember = User::factory()->create();
    $otherMember->assignRole('Member');
    $otherAccount = memberAccount($otherMember);
    billedAccount(5, $otherAccount);
    app(PaymentService::class)->record($otherAccount, 100.00, $this->cash, $this->staff);

    $this->actingAs($this->member)
        ->get(route('my.payments.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('my/payments/Index')
            ->count('payments.data', 1)
            ->where('payments.data.0.id', $payment->id)
            ->where('payments.data.0.amount', 200)
            ->where('payments.data.0.method', 'manual')
        );
});

test('a member downloads the receipt for their own payment', function () {
    $account = memberAccount($this->member);
    billedAccount(10, $account);

    $payment = app(PaymentService::class)->record($account, 200.00, $this->cash, $this->staff);

    $this->actingAs($this->member)
        ->get(route('my.payments.receipt', $payment))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('a member cannot download another member\'s receipt', function () {
    $otherMember = User::factory()->create();
    $otherMember->assignRole('Member');

    $otherAccount = memberAccount($otherMember);
    billedAccount(10, $otherAccount);

    $foreignPayment = app(PaymentService::class)->record($otherAccount, 200.00, $this->cash, $this->staff);

    $this->actingAs($this->member)
        ->get(route('my.payments.receipt', $foreignPayment))
        ->assertForbidden();
});

test('a user without the payments permission cannot open the payment history', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('my.payments.index'))
        ->assertForbidden();
});
