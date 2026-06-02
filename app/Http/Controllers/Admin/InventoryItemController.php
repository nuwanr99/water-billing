<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreInventoryItemRequest;
use App\Http\Requests\Admin\UpdateInventoryItemRequest;
use App\Libraries\Datatable;
use App\Models\InventoryItem;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Inventory item management (M7): CRUD for the society's stocked materials.
 * On-hand counts are read here but only ever change through a recorded stock
 * movement (see StockMovementController).
 */
class InventoryItemController extends Controller
{
    /**
     * Show the inventory list, with an optional low-stock filter.
     */
    public function index(Request $request): Response
    {
        $datatable = new Datatable(
            $request,
            searchColumns: ['name', 'unit'],
            orderColumns: ['name', 'unit', 'quantity_in_stock', 'reorder_level', 'unit_rate', 'is_active'],
            defaultSort: 'name',
            defaultDirection: 'asc',
        );

        $lowStockOnly = $request->boolean('low_stock');

        $query = InventoryItem::query()
            ->when($lowStockOnly, fn (Builder $q) => $q->whereColumn('quantity_in_stock', '<=', 'reorder_level'));

        $items = $datatable->paginate($query)
            ->through(fn (InventoryItem $item): array => [
                'id' => $item->id,
                'name' => $item->name,
                'unit' => $item->unit,
                'unit_rate' => (float) $item->unit_rate,
                'quantity_in_stock' => $item->quantity_in_stock,
                'reorder_level' => $item->reorder_level,
                'is_low_stock' => $item->isLowStock(),
                'is_active' => $item->is_active,
            ]);

        return Inertia::render('admin/inventory/Index', [
            'items' => $items,
            'filters' => $datatable->filters(),
            'lowStockOnly' => $lowStockOnly,
            'lowStockCount' => InventoryItem::query()
                ->where('is_active', true)
                ->whereColumn('quantity_in_stock', '<=', 'reorder_level')
                ->count(),
        ]);
    }

    /**
     * Show the create item page.
     */
    public function create(): Response
    {
        return Inertia::render('admin/inventory/Create');
    }

    /**
     * Store a new inventory item with its opening stock.
     */
    public function store(StoreInventoryItemRequest $request): RedirectResponse
    {
        $item = InventoryItem::query()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name added to inventory.', ['name' => $item->name])]);

        return to_route('admin.inventory.show', $item);
    }

    /**
     * Show an item with its current stock and movement history.
     */
    public function show(InventoryItem $inventoryItem): Response
    {
        $movements = $inventoryItem->movements()
            ->with(['mover:id,first_name,last_name', 'maintenanceJob:id,job_number'])
            ->paginate(20)
            ->through(fn (StockMovement $movement): array => [
                'id' => $movement->id,
                'type' => $movement->movement_type->value,
                'type_label' => $movement->movement_type->label(),
                'quantity' => $movement->quantity,
                'unit_rate' => $movement->unit_rate === null ? null : (float) $movement->unit_rate,
                'note' => $movement->note,
                'job_id' => $movement->maintenanceJob?->id,
                'job_number' => $movement->maintenanceJob?->job_number,
                'moved_by' => $movement->mover?->name,
                'moved_at' => $movement->moved_at->format('d M Y H:i'),
            ]);

        return Inertia::render('admin/inventory/Show', [
            'item' => [
                'id' => $inventoryItem->id,
                'name' => $inventoryItem->name,
                'unit' => $inventoryItem->unit,
                'unit_rate' => (float) $inventoryItem->unit_rate,
                'quantity_in_stock' => $inventoryItem->quantity_in_stock,
                'reorder_level' => $inventoryItem->reorder_level,
                'is_low_stock' => $inventoryItem->isLowStock(),
                'is_active' => $inventoryItem->is_active,
            ],
            'movements' => $movements,
        ]);
    }

    /**
     * Show the edit item page.
     */
    public function edit(InventoryItem $inventoryItem): Response
    {
        return Inertia::render('admin/inventory/Edit', [
            'item' => [
                'id' => $inventoryItem->id,
                'name' => $inventoryItem->name,
                'unit' => $inventoryItem->unit,
                'unit_rate' => (float) $inventoryItem->unit_rate,
                'quantity_in_stock' => $inventoryItem->quantity_in_stock,
                'reorder_level' => $inventoryItem->reorder_level,
                'is_active' => $inventoryItem->is_active,
            ],
        ]);
    }

    /**
     * Update an item's catalog details (not its stock).
     */
    public function update(UpdateInventoryItemRequest $request, InventoryItem $inventoryItem): RedirectResponse
    {
        $inventoryItem->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name updated.', ['name' => $inventoryItem->name])]);

        return to_route('admin.inventory.show', $inventoryItem);
    }

    /**
     * Delete an item — only while it has no movement history.
     */
    public function destroy(InventoryItem $inventoryItem): RedirectResponse
    {
        if ($inventoryItem->movements()->exists()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('This item has stock movements and cannot be deleted. Deactivate it instead.'),
            ]);

            return back();
        }

        $inventoryItem->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Item deleted.')]);

        return to_route('admin.inventory.index');
    }
}
