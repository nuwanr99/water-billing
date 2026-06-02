<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StockMovementType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreStockMovementRequest;
use App\Models\InventoryItem;
use App\Models\MaintenanceJob;
use App\Services\InventoryService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Records stock movements (M7): purchases add stock, usages remove it
 * (optionally against a maintenance job), and adjustments correct it. Each
 * movement and the item's on-hand count are written together by
 * InventoryService.
 */
class StockMovementController extends Controller
{
    public function __construct(protected InventoryService $inventory) {}

    /**
     * Search maintenance jobs to attribute a usage movement to (D-45).
     */
    public function jobs(Request $request): JsonResponse
    {
        $search = $request->string('search')->trim()->value();

        $jobs = MaintenanceJob::query()
            ->when($search !== '', function (Builder $query) use ($search): void {
                $term = "%{$search}%";

                $query->where(fn (Builder $sub) => $sub
                    ->orWhere('job_number', 'like', $term)
                    ->orWhere('title', 'like', $term));
            })
            ->latest()
            ->limit(20)
            ->get(['id', 'job_number', 'title'])
            ->map(fn (MaintenanceJob $job): array => [
                'id' => $job->id,
                'job_number' => $job->job_number,
                'title' => $job->title,
            ]);

        return response()->json($jobs);
    }

    /**
     * Show the record-movement page for an item.
     */
    public function create(Request $request, InventoryItem $inventoryItem): Response
    {
        $job = $request->filled('job')
            ? MaintenanceJob::query()->findOrFail($request->integer('job'))
            : null;

        return Inertia::render('admin/inventory/RecordMovement', [
            'item' => [
                'id' => $inventoryItem->id,
                'name' => $inventoryItem->name,
                'unit' => $inventoryItem->unit,
                'unit_rate' => (float) $inventoryItem->unit_rate,
                'quantity_in_stock' => $inventoryItem->quantity_in_stock,
            ],
            'job' => $job === null ? null : [
                'id' => $job->id,
                'job_number' => $job->job_number,
                'title' => $job->title,
            ],
        ]);
    }

    /**
     * Record the movement and update the item's on-hand count.
     */
    public function store(StoreStockMovementRequest $request, InventoryItem $inventoryItem): RedirectResponse
    {
        $this->inventory->recordMovement(
            $inventoryItem,
            StockMovementType::from($request->validated('movement_type')),
            $request->integer('quantity'),
            $request->user(),
            $request->input('unit_rate') === null ? null : (float) $request->validated('unit_rate'),
            $request->validated('note'),
            $request->integer('maintenance_job_id') ?: null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Stock movement recorded.')]);

        return to_route('admin.inventory.show', $inventoryItem);
    }
}
