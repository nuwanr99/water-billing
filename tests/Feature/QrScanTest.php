<?php

use App\Enums\WaterAccountStatus;
use App\Models\MeterReading;
use App\Models\User;
use App\Models\WaterAccount;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Support\SessionKey;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->waterController = User::factory()->create();
    $this->waterController->assignRole('Water Controller');

    $this->treasurer = User::factory()->create();
    $this->treasurer->assignRole('Treasurer');
});

/**
 * The qr.resolve URL for the given account's scanned pair.
 */
function scanUrl(WaterAccount $account, string $context, ?string $meter = null): string
{
    return route('qr.resolve', [
        'account' => $account->account_number,
        'meter' => $meter ?? $account->meter_number,
        'context' => $context,
    ]);
}

test('a meter reading scan lands on the entry form when the month is unread', function () {
    $account = WaterAccount::factory()->create();

    $this->actingAs($this->waterController)
        ->get(scanUrl($account, 'meter-reading'))
        ->assertRedirect(route('meter-readings.create', $account));
});

test('a meter reading scan lands on the history when the month is already read', function () {
    $account = WaterAccount::factory()->create();
    MeterReading::factory()
        ->for($account)
        ->forMonth(now()->format('Y-m'))
        ->create();

    $this->actingAs($this->waterController)
        ->get(scanUrl($account, 'meter-reading'))
        ->assertRedirect(route('meter-readings.history', $account));
});

test('a payment scan lands on the account collect page', function () {
    $account = WaterAccount::factory()->create();

    $this->actingAs($this->treasurer)
        ->get(scanUrl($account, 'payment'))
        ->assertRedirect(route('admin.payments.collect.show', $account));
});

test('an unknown pair returns to the scan origin with an error toast', function () {
    $this->actingAs($this->waterController)
        ->get(route('qr.resolve', ['account' => 'ACC-9999', 'meter' => 'MTR-999999', 'context' => 'meter-reading']))
        ->assertRedirect(route('meter-readings.index'))
        ->assertSessionHas(SessionKey::FLASH_DATA, fn (array $flash): bool => $flash['toast']['type'] === 'error');

    $this->actingAs($this->treasurer)
        ->get(route('qr.resolve', ['account' => 'ACC-9999', 'meter' => 'MTR-999999', 'context' => 'payment']))
        ->assertRedirect(route('admin.payments.collect'))
        ->assertSessionHas(SessionKey::FLASH_DATA, fn (array $flash): bool => $flash['toast']['type'] === 'error');
});

test('a failed scan keeps the page\'s active search filter', function () {
    $this->actingAs($this->waterController)
        ->get(route('qr.resolve', ['account' => 'ACC-9999', 'meter' => 'MTR-999999', 'context' => 'meter-reading', 'search' => 'perera']))
        ->assertRedirect(route('meter-readings.index', ['search' => 'perera']));
});

test('a meter number from a different account does not resolve', function () {
    $account = WaterAccount::factory()->create();
    $other = WaterAccount::factory()->create();

    $this->actingAs($this->waterController)
        ->get(scanUrl($account, 'meter-reading', $other->meter_number))
        ->assertRedirect(route('meter-readings.index'));
});

test('an inactive account does not resolve for staff scans', function () {
    $account = WaterAccount::factory()->create(['status' => WaterAccountStatus::Inactive]);

    $this->actingAs($this->treasurer)
        ->get(scanUrl($account, 'payment'))
        ->assertRedirect(route('admin.payments.collect'));
});

test('a missing or unknown context is rejected', function () {
    $account = WaterAccount::factory()->create();

    $this->actingAs($this->waterController)
        ->getJson(route('qr.resolve', ['account' => $account->account_number, 'meter' => $account->meter_number]))
        ->assertUnprocessable();

    $this->actingAs($this->waterController)
        ->getJson(scanUrl($account, 'billing'))
        ->assertUnprocessable();
});

test('a member without staff permissions cannot resolve either context', function () {
    $member = User::factory()->create();
    $member->assignRole('Member');

    $account = WaterAccount::factory()->create(['user_id' => $member->id]);

    $this->actingAs($member)->get(scanUrl($account, 'meter-reading'))->assertForbidden();
    $this->actingAs($member)->get(scanUrl($account, 'payment'))->assertForbidden();
});

test('a water controller cannot resolve the payment context', function () {
    $account = WaterAccount::factory()->create();

    $this->actingAs($this->waterController)
        ->get(scanUrl($account, 'payment'))
        ->assertForbidden();
});
