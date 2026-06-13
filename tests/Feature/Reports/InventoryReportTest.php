<?php

use App\Enums\StockMovementType;
use App\Models\InventoryItem;
use App\Models\MaintenanceJob;
use App\Models\User;
use App\Services\InventoryService;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->treasurer = User::factory()->create();
    $this->treasurer->assignRole('Treasurer');
});

test('the inventory summary totals stock value and low-stock count for active items', function () {
    InventoryItem::factory()->create([
        'name' => 'PVC Pipe 20mm',
        'quantity_in_stock' => 20,
        'reorder_level' => 5,
        'unit_rate' => 100,
        'is_active' => true,
    ]);

    InventoryItem::factory()->lowStock()->create([
        'name' => 'Gate Valve 32mm',
        'unit_rate' => 50,
        'is_active' => true,
    ]);

    // Inactive item: excluded from the summary tiles.
    InventoryItem::factory()->create([
        'name' => 'Retired Item',
        'quantity_in_stock' => 100,
        'reorder_level' => 5,
        'unit_rate' => 10,
        'is_active' => false,
    ]);

    $this->actingAs($this->treasurer)
        ->get(route('admin.reports.inventory.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/reports/InventoryReport')
            ->where('summary.active_items', 2)
            ->where('summary.low_stock_count', 1)
            ->where('summary.total_stock_value', 2100)
            ->has('position', 3));
});

test('movements outside the requested period are excluded', function () {
    $item = InventoryItem::factory()->create(['quantity_in_stock' => 50]);
    $mover = User::factory()->create();
    $inventory = app(InventoryService::class);

    $inventory->recordMovement($item, StockMovementType::Purchase, 10, $mover, 100.00, null, null, now());
    $inventory->recordMovement($item, StockMovementType::Usage, 5, $mover, null, null, null, now()->subMonthNoOverflow());

    $this->actingAs($this->treasurer)
        ->get(route('admin.reports.inventory.index', ['period_type' => 'month', 'month' => now()->format('Y-m')]))
        ->assertInertia(fn ($page) => $page
            ->has('movements.data', 1)
            ->where('movements.data.0.quantity', 10));

    $lastMonth = now()->subMonthNoOverflow()->format('Y-m');

    $this->actingAs($this->treasurer)
        ->get(route('admin.reports.inventory.index', ['period_type' => 'month', 'month' => $lastMonth]))
        ->assertInertia(fn ($page) => $page
            ->has('movements.data', 1)
            ->where('movements.data.0.quantity', -5));
});

test('usage movements carry the linked maintenance job number', function () {
    $item = InventoryItem::factory()->create(['quantity_in_stock' => 50]);
    $mover = User::factory()->create();
    $job = MaintenanceJob::factory()->create(['job_number' => 'JOB-2026-00099']);

    app(InventoryService::class)->recordMovement(
        $item,
        StockMovementType::Usage,
        4,
        $mover,
        null,
        null,
        $job->id,
        now(),
    );

    $this->actingAs($this->treasurer)
        ->get(route('admin.reports.inventory.index'))
        ->assertInertia(fn ($page) => $page
            ->where('movements.data.0.job_number', 'JOB-2026-00099'));
});

test('users without reports.view cannot open the report', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('admin');

    $this->actingAs($user)
        ->get(route('admin.reports.inventory.index'))
        ->assertForbidden();
});

test('the pdf and csv exports download with the expected content types', function () {
    InventoryItem::factory()->create(['name' => 'PVC Pipe 20mm']);

    $this->actingAs($this->treasurer)
        ->get(route('admin.reports.inventory.pdf'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    $response = $this->actingAs($this->treasurer)
        ->get(route('admin.reports.inventory.csv'))
        ->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('text/csv')
        ->and($response->streamedContent())->toContain('PVC Pipe 20mm');
});

test('exports require the reports.export permission', function () {
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(['admin', 'reports.view']);

    $this->actingAs($viewer)
        ->get(route('admin.reports.inventory.pdf'))
        ->assertForbidden();
});
