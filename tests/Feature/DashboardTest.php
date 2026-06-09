<?php

use App\Models\MaintenanceJob;
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

test('the admin dashboard shows operational widgets built from live billing data', function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(SystemLedgerAccountSeeder::class);

    $admin = User::factory()->create();
    $admin->assignRole('Super Admin');

    ['account' => $account] = billedAccount(10); // 200.00 billed

    $cash = SystemLedgerAccount::query()->where('code', '1000')->firstOrFail();
    app(PaymentService::class)->record($account, 50.00, $cash, $admin);

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/Dashboard')
            ->where('billing.month_total', 200)
            ->where('billing.month_count', 1)
            ->where('billing.unpaid_count', 1)
            ->where('billing.overdue_count', 0)
            ->where('collections.month_total', 50)
            ->count('collections.recent', 1)
            ->where('collections.recent.0.amount', 50)
            ->where('outstanding.total_due', 150)
            ->where('outstanding.accounts_in_arrears', 1)
            ->where('accounts.active', 1)
            ->where('readings.recorded', 1)
            ->where('readings.active_accounts', 1)
        );
});

test('every admin dashboard widget is hidden without its view permission', function () {
    $this->seed(RolePermissionSeeder::class);

    $user = User::factory()->create();
    $user->givePermissionTo('admin');

    $this->actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/Dashboard')
            ->where('billing', null)
            ->where('collections', null)
            ->where('outstanding', null)
            ->where('accounts', null)
            ->where('readings', null)
            ->where('complaints', null)
            ->where('maintenanceJobs', null)
            ->where('myJobs', null)
            ->where('expenses', null)
            ->where('inventory', null)
        );
});

test('jobs assigned to the current user appear on the admin dashboard', function () {
    $this->seed(RolePermissionSeeder::class);

    $staff = User::factory()->create();
    $staff->givePermissionTo('admin', 'maintenance-jobs.view-assigned');

    $ownJob = MaintenanceJob::factory()->create();
    $ownJob->assignees()->attach($staff->id, ['assigned_by' => $ownJob->created_by, 'assigned_at' => now()]);

    // A completed job and someone else's job must not show up.
    $completed = MaintenanceJob::factory()->completed()->create();
    $completed->assignees()->attach($staff->id, ['assigned_by' => $completed->created_by, 'assigned_at' => now()]);
    MaintenanceJob::factory()->create();

    $this->actingAs($staff)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/Dashboard')
            ->where('myJobs.open_count', 1)
            ->count('myJobs.open', 1)
            ->where('myJobs.open.0.job_number', $ownJob->job_number)
            ->where('maintenanceJobs', null)
        );
});

test('each admin dashboard widget only requires its own permission', function () {
    $this->seed(RolePermissionSeeder::class);

    $user = User::factory()->create();
    $user->givePermissionTo('admin', 'payments.view-all', 'complaints.view-all');

    $this->actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/Dashboard')
            ->has('collections')
            ->has('complaints')
            ->where('billing', null)
            ->where('inventory', null)
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
