<?php

use App\Models\MeterReading;
use App\Models\User;
use App\Models\WaterAccount;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->waterController = User::factory()->create();
    $this->waterController->assignRole('Water Controller');
});

test('a water controller sees the account cards on the index', function () {
    $account = WaterAccount::factory()->create();

    $this->actingAs($this->waterController)
        ->get(route('meter-readings.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('meter-readings/Index')
            ->where('accounts.0.account_number', $account->account_number)
            ->where('accounts.0.meter_number', $account->meter_number)
            ->where('accounts.0.owner_name', $account->owner->name)
            ->where('progress.read', 0)
            ->where('progress.total', 1)
        );
});

test('the index filters accounts by search term', function () {
    WaterAccount::factory()->create(['account_number' => 'ACC-0101', 'meter_number' => 'MTR-000101']);
    WaterAccount::factory()->create(['account_number' => 'ACC-0202', 'meter_number' => 'MTR-000202']);

    $this->actingAs($this->waterController)
        ->get(route('meter-readings.index', ['search' => 'ACC-0202']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('meter-readings/Index')
            ->count('accounts', 1)
            ->where('accounts.0.account_number', 'ACC-0202')
            ->where('progress.total', 2)
        );
});

test('the first reading derives consumption from the initial reading', function () {
    $account = WaterAccount::factory()->create(['initial_reading' => 1200.25]);

    $this->actingAs($this->waterController)
        ->post(route('meter-readings.store', $account), ['reading_value' => 1235.75])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('bills.preview', MeterReading::first()));

    $this->assertDatabaseHas('meter_readings', [
        'water_account_id' => $account->id,
        'recorded_by' => $this->waterController->id,
        'billing_month' => now()->format('Y-m'),
        'reading_value' => '1235.75',
        'consumption' => '35.50',
    ]);
});

test('a subsequent reading derives consumption from the previous reading', function () {
    $account = WaterAccount::factory()->create(['initial_reading' => 1200]);
    MeterReading::factory()
        ->for($account)
        ->forMonth(now()->subMonth()->format('Y-m'))
        ->create(['reading_value' => 1235.5, 'consumption' => 35.5]);

    $this->actingAs($this->waterController)
        ->post(route('meter-readings.store', $account), ['reading_value' => 1260.75])
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('meter_readings', [
        'water_account_id' => $account->id,
        'billing_month' => now()->format('Y-m'),
        'reading_value' => '1260.75',
        'consumption' => '25.25',
    ]);
});

test('a reading lower than the previous value is rejected and nothing is stored', function () {
    $account = WaterAccount::factory()->create(['initial_reading' => 1200]);

    $this->actingAs($this->waterController)
        ->post(route('meter-readings.store', $account), ['reading_value' => 1199.99])
        ->assertSessionHasErrors('reading_value');

    $this->assertDatabaseCount('meter_readings', 0);
});

test('a second reading for the same month is rejected', function () {
    $account = WaterAccount::factory()->create(['initial_reading' => 1200]);
    MeterReading::factory()
        ->for($account)
        ->forMonth(now()->format('Y-m'))
        ->create(['reading_value' => 1235, 'consumption' => 35]);

    $this->actingAs($this->waterController)
        ->post(route('meter-readings.store', $account), ['reading_value' => 1260])
        ->assertSessionHasErrors('reading_value');

    $this->assertDatabaseCount('meter_readings', 1);
});

test('correcting the latest reading re-derives its consumption', function () {
    $account = WaterAccount::factory()->create(['initial_reading' => 1200]);
    $latest = MeterReading::factory()
        ->for($account)
        ->forMonth(now()->format('Y-m'))
        ->create(['reading_value' => 1235, 'consumption' => 35]);

    $this->actingAs($this->waterController)
        ->put(route('meter-readings.update', $latest), ['reading_value' => 1240.5])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('meter-readings.history', $account));

    expect($latest->refresh())
        ->reading_value->toBe('1240.50')
        ->consumption->toBe('40.50');
});

test('a reading older than the latest cannot be edited', function () {
    $account = WaterAccount::factory()->create();
    $older = MeterReading::factory()
        ->for($account)
        ->forMonth(now()->subMonth()->format('Y-m'))
        ->create(['reading_value' => 1235]);
    MeterReading::factory()
        ->for($account)
        ->forMonth(now()->format('Y-m'))
        ->create(['reading_value' => 1260]);

    $this->actingAs($this->waterController)
        ->put(route('meter-readings.update', $older), ['reading_value' => 1240])
        ->assertNotFound();
});

test('the history page lists readings with the latest marked editable', function () {
    $account = WaterAccount::factory()->create(['initial_reading' => 1200.5]);
    $latest = MeterReading::factory()
        ->for($account)
        ->for($this->waterController, 'recorder')
        ->forMonth(now()->format('Y-m'))
        ->create(['reading_value' => 1235.75, 'consumption' => 35.25]);

    $this->actingAs($this->waterController)
        ->get(route('meter-readings.history', $account))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('meter-readings/History')
            ->where('readings.0.id', $latest->id)
            ->where('readings.0.is_latest', true)
            ->where('readings.0.value', 1235.75)
            ->where('readings.0.recorded_by', $this->waterController->name)
            ->where('initialReading', 1200.5)
        );
});

test('an admin updating the initial reading re-derives the first reading', function () {
    $admin = User::factory()->create();
    $admin->givePermissionTo('admin', 'water-accounts.edit');
    $member = User::factory()->create();
    $member->assignRole('Member');

    $account = WaterAccount::factory()->for($member, 'owner')->create(['initial_reading' => 100]);
    $firstReading = MeterReading::factory()
        ->for($account)
        ->forMonth(now()->format('Y-m'))
        ->create(['reading_value' => 150.5, 'consumption' => 50.5]);

    $this->actingAs($admin)
        ->put(route('admin.water-accounts.update', $account), [
            'user_id' => $member->id,
            'billing_category_id' => $account->billing_category_id,
            'account_number' => $account->account_number,
            'meter_number' => $account->meter_number,
            'initial_reading' => 120.25,
            'connection_address' => null,
            'status' => 'active',
            'connected_at' => null,
        ])
        ->assertSessionHasNoErrors();

    expect($firstReading->refresh()->consumption)->toBe('30.25');
});

test('a user without readings permissions cannot open the field interface', function () {
    $member = User::factory()->create();
    $member->assignRole('Member');

    $this->actingAs($member)
        ->get(route('meter-readings.index'))
        ->assertForbidden();
});

test('a water controller logging in from a mobile device lands on the reading interface', function () {
    $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Mobile/15E148 Safari/604.1'])
        ->post(route('login.store'), [
            'email' => $this->waterController->email,
            'password' => 'password',
        ])
        ->assertRedirect(route('meter-readings.index', absolute: false));
});

test('a water controller logging in from a desktop lands on the dashboard', function () {
    $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) Chrome/126.0 Safari/537.36'])
        ->post(route('login.store'), [
            'email' => $this->waterController->email,
            'password' => 'password',
        ])
        ->assertRedirect(route('dashboard', absolute: false));
});

test('the member dashboard shows the latest reading for the current account', function () {
    $member = User::factory()->create();
    $member->assignRole('Member');
    $account = WaterAccount::factory()->for($member, 'owner')->create();
    MeterReading::factory()
        ->for($account)
        ->forMonth(now()->format('Y-m'))
        ->create(['reading_value' => 1254.25, 'consumption' => 18.5]);

    $this->actingAs($member)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('latestReading.value', 1254.25)
            ->where('latestReading.consumption', 18.5)
        );
});

test('guests are redirected to login', function () {
    $this->get(route('meter-readings.index'))->assertRedirect(route('login'));
});
