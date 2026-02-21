<?php

use App\Models\SystemLedgerAccount;
use App\Models\SystemLedgerEntry;
use App\Models\User;
use App\Services\SystemLedgerService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SystemLedgerAccountSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(SystemLedgerAccountSeeder::class);

    $this->treasurer = User::factory()->create();
    $this->treasurer->assignRole('Treasurer');
});

test('the chart of accounts lists accounts with natural balances', function () {
    app(SystemLedgerService::class)->post('Payment received', [
        ['account' => '1000', 'amount' => 750.00],
        ['account' => '4000', 'amount' => -750.00],
    ]);

    $this->actingAs($this->treasurer)
        ->get(route('admin.ledger-accounts.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/ledger-accounts/Index')
            ->where('accounts.data.0.code', '1000')
            ->where('accounts.data.0.balance', 750)
        );
});

test('the account view page shows its journal lines with running balances', function () {
    app(SystemLedgerService::class)->post('Payment received', [
        ['account' => '1000', 'amount' => 750.00],
        ['account' => '4000', 'amount' => -750.00],
    ]);

    app(SystemLedgerService::class)->post('Bought fittings', [
        ['account' => '5000', 'amount' => 300.00],
        ['account' => '1000', 'amount' => -300.00],
    ]);

    $cash = SystemLedgerAccount::query()->where('code', '1000')->firstOrFail();

    $this->actingAs($this->treasurer)
        ->get(route('admin.ledger-accounts.show', $cash))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/ledger-accounts/Show')
            ->where('account.balance', 450)
            ->where('account.total_debits', 750)
            ->where('account.total_credits', 300)
            ->count('lines.data', 2)
            ->where('lines.data.0.credit', 300)
            ->where('lines.data.0.balance', 450)
            ->where('lines.data.1.debit', 750)
            ->where('lines.data.1.balance', 750)
        );
});

test('a treasurer creates and edits a chart account', function () {
    $this->actingAs($this->treasurer)
        ->post(route('admin.ledger-accounts.store'), [
            'code' => '4200',
            'name' => 'Donations',
            'type' => 'income',
            'is_active' => true,
        ])
        ->assertSessionHasNoErrors();

    $account = SystemLedgerAccount::query()->where('code', '4200')->firstOrFail();

    $this->actingAs($this->treasurer)
        ->put(route('admin.ledger-accounts.update', $account), [
            'code' => '4200',
            'name' => 'Donations & Grants',
            'type' => 'income',
            'is_active' => true,
        ])
        ->assertSessionHasNoErrors();

    expect($account->refresh()->name)->toBe('Donations & Grants');
});

test('an account with journal lines cannot be deleted or retyped', function () {
    app(SystemLedgerService::class)->post('Payment received', [
        ['account' => '1000', 'amount' => 100.00],
        ['account' => '4000', 'amount' => -100.00],
    ]);

    $cash = SystemLedgerAccount::query()->where('code', '1000')->firstOrFail();

    $this->actingAs($this->treasurer)
        ->put(route('admin.ledger-accounts.update', $cash), [
            'code' => '1000',
            'name' => 'Cash on Hand',
            'type' => 'expense',
            'is_active' => true,
        ])
        ->assertSessionHasErrors('type');

    $this->actingAs($this->treasurer)
        ->delete(route('admin.ledger-accounts.destroy', $cash));

    expect(SystemLedgerAccount::query()->where('code', '1000')->exists())->toBeTrue();
});

test('a treasurer posts a balanced manual journal', function () {
    $cash = SystemLedgerAccount::query()->where('code', '1000')->firstOrFail();
    $income = SystemLedgerAccount::query()->where('code', '4100')->firstOrFail();

    $this->actingAs($this->treasurer)
        ->post(route('admin.system-ledger.store'), [
            'description' => 'Opening cash balance',
            'entry_date' => now()->toDateString(),
            'lines' => [
                ['system_ledger_account_id' => $cash->id, 'debit' => 5000, 'credit' => null],
                ['system_ledger_account_id' => $income->id, 'debit' => null, 'credit' => 5000],
            ],
        ])
        ->assertSessionHasNoErrors();

    $entry = SystemLedgerEntry::query()->firstOrFail();

    expect($entry->source_type)->toBe('manual')
        ->and($entry->is_posted)->toBeTrue()
        ->and((float) $entry->total_debit)->toBe(5000.00);

    $this->actingAs($this->treasurer)
        ->get(route('admin.system-ledger.show', $entry))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/system-ledger/Show')
            ->where('entry.lines.0.debit', 5000)
            ->where('entry.lines.1.credit', 5000)
        );
});

test('a transfer moves money between asset accounts', function () {
    $cash = SystemLedgerAccount::query()->where('code', '1000')->firstOrFail();
    $bank = SystemLedgerAccount::query()->where('code', '1100')->firstOrFail();

    $this->actingAs($this->treasurer)
        ->post(route('admin.system-ledger.transfer.store'), [
            'from_account_id' => $cash->id,
            'to_account_id' => $bank->id,
            'amount' => 2500,
            'entry_date' => now()->toDateString(),
            'description' => null,
        ])
        ->assertSessionHasNoErrors();

    $entry = SystemLedgerEntry::query()->firstOrFail();

    expect($entry->source_type)->toBe('transfer')
        ->and((float) $entry->total_debit)->toBe(2500.00);

    // Bank gains (debit +), cash shrinks (credit −).
    expect((float) $bank->lines()->sum('amount'))->toBe(2500.00)
        ->and((float) $cash->lines()->sum('amount'))->toBe(-2500.00);
});

test('a transfer cannot target an income account or itself', function () {
    $cash = SystemLedgerAccount::query()->where('code', '1000')->firstOrFail();
    $income = SystemLedgerAccount::query()->where('code', '4000')->firstOrFail();

    $this->actingAs($this->treasurer)
        ->post(route('admin.system-ledger.transfer.store'), [
            'from_account_id' => $cash->id,
            'to_account_id' => $income->id,
            'amount' => 100,
            'entry_date' => now()->toDateString(),
        ])
        ->assertSessionHasErrors('to_account_id');

    $this->actingAs($this->treasurer)
        ->post(route('admin.system-ledger.transfer.store'), [
            'from_account_id' => $cash->id,
            'to_account_id' => $cash->id,
            'amount' => 100,
            'entry_date' => now()->toDateString(),
        ])
        ->assertSessionHasErrors('from_account_id');
});

test('an unbalanced manual journal is rejected', function () {
    $cash = SystemLedgerAccount::query()->where('code', '1000')->firstOrFail();
    $income = SystemLedgerAccount::query()->where('code', '4000')->firstOrFail();

    $this->actingAs($this->treasurer)
        ->post(route('admin.system-ledger.store'), [
            'description' => 'Broken journal',
            'entry_date' => now()->toDateString(),
            'lines' => [
                ['system_ledger_account_id' => $cash->id, 'debit' => 5000, 'credit' => null],
                ['system_ledger_account_id' => $income->id, 'debit' => null, 'credit' => 4000],
            ],
        ])
        ->assertSessionHasErrors('lines');

    expect(SystemLedgerEntry::query()->count())->toBe(0);
});

test('a user without system ledger permissions is refused', function () {
    $member = User::factory()->create();
    $member->assignRole('Member');

    $this->actingAs($member)->get(route('admin.system-ledger.index'))->assertForbidden();
    $this->actingAs($member)->get(route('admin.ledger-accounts.index'))->assertForbidden();
});
