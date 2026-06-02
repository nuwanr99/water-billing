<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Models\InventoryItem;
use App\Models\StockMovement;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The only writer of stock movements (M7). Every movement and the item's
 * running on-hand count are written in one transaction under a row lock, so
 * `quantity_in_stock` can never drift from its history or be driven negative
 * by a concurrent movement.
 */
class InventoryService
{
    public function __construct(protected AuditLogger $audit) {}

    /**
     * Record a stock movement and apply it to the item's on-hand count.
     *
     * `$quantity` is the value as entered: a positive magnitude for a
     * purchase or usage, or a signed delta for an adjustment. The movement
     * type fixes the final sign.
     */
    public function recordMovement(
        InventoryItem $item,
        StockMovementType $type,
        int $quantity,
        User $movedBy,
        ?float $unitRate = null,
        ?string $note = null,
        ?int $maintenanceJobId = null,
        ?CarbonInterface $movedAt = null,
    ): StockMovement {
        return DB::transaction(function () use ($item, $type, $quantity, $movedBy, $unitRate, $note, $maintenanceJobId, $movedAt): StockMovement {
            $item = InventoryItem::query()->lockForUpdate()->findOrFail($item->id);

            $change = $type->signedQuantity($quantity);
            $newQuantity = $item->quantity_in_stock + $change;

            if ($newQuantity < 0) {
                throw new RuntimeException("Movement would drive [{$item->name}] stock below zero.");
            }

            $movement = $item->movements()->create([
                'maintenance_job_id' => $type === StockMovementType::Usage ? $maintenanceJobId : null,
                'movement_type' => $type,
                'quantity' => $change,
                'unit_rate' => $type === StockMovementType::Purchase ? $unitRate : null,
                'note' => $note,
                'moved_by' => $movedBy->id,
                'moved_at' => $movedAt ?? now(),
            ]);

            $item->update(['quantity_in_stock' => $newQuantity]);

            $this->audit->log('inventory.movement-recorded', $movement, [
                'item' => $item->name,
                'type' => $type->value,
                'quantity' => $change,
                'balance' => $newQuantity,
            ], $movedBy);

            return $movement;
        });
    }
}
