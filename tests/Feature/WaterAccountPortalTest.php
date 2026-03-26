<?php

use App\Models\User;
use App\Models\WaterAccount;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->member = User::factory()->create();
    $this->member->assignRole('Member');
});

test('a member sees only their own water accounts', function () {
    $ownAccounts = WaterAccount::factory()->count(2)->for($this->member, 'owner')->create();
    WaterAccount::factory()->create();

    $this->actingAs($this->member)
        ->get(route('water-accounts.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('water-accounts/Index')
            ->count('waterAccounts', 2)
            ->where('waterAccounts.0.account_number', $ownAccounts->sortBy('account_number')->first()->account_number)
        );
});

test('a member can switch their current water account and it persists across sessions', function () {
    WaterAccount::factory()->for($this->member, 'owner')->create();
    $second = WaterAccount::factory()->for($this->member, 'owner')->create();

    $this->actingAs($this->member)
        ->from(route('dashboard'))
        ->post(route('water-accounts.switch', $second))
        ->assertRedirect(route('dashboard'));

    // Persisted on the user, not the session (survives logout/devices).
    expect($this->member->refresh()->last_water_account_id)->toBe($second->id);

    $this->flushSession();

    $this->actingAs($this->member)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('currentAccount.id', $second->id)
        );
});

test('a member cannot switch to another member\'s water account', function () {
    $foreignAccount = WaterAccount::factory()->create();

    $this->actingAs($this->member)
        ->post(route('water-accounts.switch', $foreignAccount))
        ->assertForbidden();
});

test('the dashboard shows the current water account', function () {
    $account = WaterAccount::factory()->for($this->member, 'owner')->create();

    $this->actingAs($this->member)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('currentAccount.account_number', $account->account_number)
            ->where('activeAccountsCount', 1)
        );
});

test('the dashboard handles a member with no water accounts', function () {
    $this->actingAs($this->member)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('currentAccount', null)
            ->where('activeAccountsCount', 0)
        );
});
