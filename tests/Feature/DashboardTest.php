<?php

use App\Models\SystemLedgerAccount;
use App\Models\User;
use App\Services\PaymentService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SystemLedgerAccountSeeder;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('normal users can visit their dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('admins can also visit the normal dashboard', function () {
    $this->seed(RolePermissionSeeder::class);

    $user = User::factory()->create();
    $user->givePermissionTo('admin');

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Dashboard'));
});

test('the admin dashboard shows stats and recent users', function () {
    $this->seed(RolePermissionSeeder::class);

    $user = User::factory()->create();
    $user->assignRole('Super Admin');

    $this->actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/Dashboard')
            ->where('stats.users', 2)
            ->where('stats.roles', 8)
            ->has('recentUsers')
        );
});

test('normal users cannot visit the admin dashboard', function () {
    $this->seed(RolePermissionSeeder::class);

    $user = User::factory()->create();

    $this->actingAs($user)->get(route('admin.dashboard'))->assertForbidden();
});

test('the dashboard shows the current bill for the member account', function () {
    $this->seed(RolePermissionSeeder::class);

    $member = User::factory()->create();
    $member->assignRole('Member');

    $account = memberAccount($member);
    billedAccount(10, $account); // 200.00 due

    $this->actingAs($member)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('currentBill.total_due', 200)
            ->where('currentBill.status', 'approved')
            ->where('currentBill.is_overdue', false)
            ->where('can.view_bills', true)
        );
});

test('a bill past its due date shows as overdue on the dashboard', function () {
    $this->seed(RolePermissionSeeder::class);

    $member = User::factory()->create();
    $member->assignRole('Member');

    $account = memberAccount($member);
    billedAccount(10, $account);

    // Due date is config('billing.due_days') days out; jump past it.
    $this->travel((int) config('billing.due_days') + 1)->days();

    $this->actingAs($member)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('currentBill.is_overdue', true)
        );
});

test('usage history is deferred and loads on a partial reload', function () {
    $this->seed(RolePermissionSeeder::class);

    $member = User::factory()->create();
    $member->assignRole('Member');

    $account = memberAccount($member);
    billedAccount(10, $account);

    $initial = $this->actingAs($member)->get(route('dashboard'));

    $initial->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->missing('usageHistory')
        );

    $version = $initial->viewData('page')['version'];

    $this->get(route('dashboard'), [
        'X-Inertia' => 'true',
        'X-Inertia-Partial-Component' => 'Dashboard',
        'X-Inertia-Partial-Data' => 'usageHistory',
        'X-Inertia-Version' => $version,
    ])
        ->assertOk()
        ->assertJsonCount(1, 'props.usageHistory')
        ->assertJsonPath('props.usageHistory.0.consumption', 10);
});

test('recent payments appear on the dashboard', function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(SystemLedgerAccountSeeder::class);

    $member = User::factory()->create();
    $member->assignRole('Member');

    $account = memberAccount($member);
    billedAccount(10, $account);

    $staff = User::factory()->create();
    $cash = SystemLedgerAccount::query()->where('code', '1000')->firstOrFail();

    app(PaymentService::class)->record($account, 50.00, $cash, $staff);

    $this->actingAs($member)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->count('recentPayments', 1)
            ->where('recentPayments.0.amount', 50)
            ->has('recentPayments.0.receipt_url')
        );
});

test('a user without the member role sees no bills or payments data', function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(SystemLedgerAccountSeeder::class);

    $user = User::factory()->create();

    $account = memberAccount($user);
    billedAccount(10, $account);

    $staff = User::factory()->create();
    $cash = SystemLedgerAccount::query()->where('code', '1000')->firstOrFail();

    app(PaymentService::class)->record($account, 50.00, $cash, $staff);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->count('recentPayments', 0)
            ->where('can.view_bills', false)
            ->where('can.view_payments', false)
        );
});
