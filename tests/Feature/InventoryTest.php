<?php

use App\Models\InventoryItem;
use App\Models\MaintenanceJob;
use App\Models\StockMovement;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->treasurer = User::factory()->create();
    $this->treasurer->assignRole('Treasurer');
});

test('a treasurer creates an item with opening stock', function () {
    $this->actingAs($this->treasurer)
        ->post(route('admin.inventory.store'), [
            'name' => '20mm PVC pipe',
            'unit' => 'm',
            'unit_rate' => 120.50,
            'quantity_in_stock' => 40,
            'reorder_level' => 10,
            'is_active' => true,
        ])
        ->assertRedirect();

    $item = InventoryItem::query()->firstOrFail();

    expect($item->name)->toBe('20mm PVC pipe')
        ->and($item->quantity_in_stock)->toBe(40)
        ->and((float) $item->unit_rate)->toBe(120.50);
});

test('editing an item cannot change its on-hand stock', function () {
    $item = InventoryItem::factory()->create(['quantity_in_stock' => 25]);

    $this->actingAs($this->treasurer)
        ->put(route('admin.inventory.update', $item), [
            'name' => 'Renamed',
            'unit' => 'pcs',
            'unit_rate' => 10,
            'reorder_level' => 3,
            'is_active' => true,
            'quantity_in_stock' => 999,
        ])
        ->assertSessionHasNoErrors();

    expect($item->refresh()->name)->toBe('Renamed')
        ->and($item->quantity_in_stock)->toBe(25);
});

test('a purchase adds stock and records a signed movement', function () {
    $item = InventoryItem::factory()->create(['quantity_in_stock' => 5]);

    $this->actingAs($this->treasurer)
        ->post(route('admin.inventory.movements.store', $item), [
            'movement_type' => 'purchase',
            'quantity' => 10,
            'unit_rate' => 99.99,
        ])
        ->assertSessionHasNoErrors();

    $movement = StockMovement::query()->firstOrFail();

    expect($item->refresh()->quantity_in_stock)->toBe(15)
        ->and($movement->quantity)->toBe(10)
        ->and((float) $movement->unit_rate)->toBe(99.99);
});

test('a usage removes stock and can be attributed to a job', function () {
    $item = InventoryItem::factory()->create(['quantity_in_stock' => 12]);
    $job = MaintenanceJob::factory()->create();

    $this->actingAs($this->treasurer)
        ->post(route('admin.inventory.movements.store', $item), [
            'movement_type' => 'usage',
            'quantity' => 4,
            'maintenance_job_id' => $job->id,
        ])
        ->assertSessionHasNoErrors();

    $movement = StockMovement::query()->firstOrFail();

    expect($item->refresh()->quantity_in_stock)->toBe(8)
        ->and($movement->quantity)->toBe(-4)
        ->and($movement->maintenance_job_id)->toBe($job->id);
});

test('a usage beyond the on-hand count is rejected', function () {
    $item = InventoryItem::factory()->create(['quantity_in_stock' => 2]);

    $this->actingAs($this->treasurer)
        ->post(route('admin.inventory.movements.store', $item), [
            'movement_type' => 'usage',
            'quantity' => 5,
        ])
        ->assertSessionHasErrors('quantity');

    expect($item->refresh()->quantity_in_stock)->toBe(2)
        ->and(StockMovement::query()->count())->toBe(0);
});

test('a negative adjustment corrects stock but requires a note', function () {
    $item = InventoryItem::factory()->create(['quantity_in_stock' => 10]);

    $this->actingAs($this->treasurer)
        ->post(route('admin.inventory.movements.store', $item), [
            'movement_type' => 'adjustment',
            'quantity' => -3,
        ])
        ->assertSessionHasErrors('note');

    $this->actingAs($this->treasurer)
        ->post(route('admin.inventory.movements.store', $item), [
            'movement_type' => 'adjustment',
            'quantity' => -3,
            'note' => 'Damaged in storage',
        ])
        ->assertSessionHasNoErrors();

    expect($item->refresh()->quantity_in_stock)->toBe(7);
});

test('an item with movements cannot be deleted', function () {
    $item = InventoryItem::factory()->create(['quantity_in_stock' => 5]);
    StockMovement::factory()->create(['inventory_item_id' => $item->id]);

    $this->actingAs($this->treasurer)->delete(route('admin.inventory.destroy', $item));

    expect(InventoryItem::query()->whereKey($item->id)->exists())->toBeTrue();

    $fresh = InventoryItem::factory()->create();
    $this->actingAs($this->treasurer)->delete(route('admin.inventory.destroy', $fresh));

    expect(InventoryItem::query()->whereKey($fresh->id)->exists())->toBeFalse();
});

test('the low-stock filter returns only items at or below their reorder level', function () {
    InventoryItem::factory()->lowStock()->create(['name' => 'Almost out']);
    InventoryItem::factory()->create(['name' => 'Plenty', 'quantity_in_stock' => 100, 'reorder_level' => 5]);

    $this->actingAs($this->treasurer)
        ->get(route('admin.inventory.index', ['low_stock' => 1]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/inventory/Index')
            ->where('lowStockCount', 1)
            ->count('items.data', 1)
            ->where('items.data.0.name', 'Almost out')
            ->where('items.data.0.is_low_stock', true)
        );
});

test('a user without inventory permissions is refused', function () {
    $member = User::factory()->create();
    $member->assignRole('Member');

    $item = InventoryItem::factory()->create();

    $this->actingAs($member)->get(route('admin.inventory.index'))->assertForbidden();
    $this->actingAs($member)->get(route('admin.inventory.create'))->assertForbidden();
    $this->actingAs($member)
        ->post(route('admin.inventory.movements.store', $item), [])
        ->assertForbidden();
});
