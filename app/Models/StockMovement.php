<?php

namespace App\Models;

use App\Enums\StockMovementType;
use Database\Factories\StockMovementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One entry in an item's stock history (M7): a signed change to the on-hand
 * count, its kind, and who moved it. Usage movements may point back to the
 * maintenance job that consumed the stock (D-45).
 *
 * @property int $id
 * @property int $inventory_item_id
 * @property int|null $maintenance_job_id
 * @property StockMovementType $movement_type
 * @property int $quantity
 * @property numeric-string|null $unit_rate
 * @property string|null $note
 * @property int $moved_by
 * @property Carbon $moved_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read InventoryItem $item
 * @property-read MaintenanceJob|null $maintenanceJob
 * @property-read User $mover
 */
#[Fillable(['inventory_item_id', 'maintenance_job_id', 'movement_type', 'quantity', 'unit_rate', 'note', 'moved_by', 'moved_at'])]
class StockMovement extends Model
{
    /** @use HasFactory<StockMovementFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'movement_type' => StockMovementType::class,
            'quantity' => 'integer',
            'unit_rate' => 'decimal:2',
            'moved_at' => 'datetime',
        ];
    }

    /**
     * The item this movement belongs to.
     *
     * @return BelongsTo<InventoryItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    /**
     * The maintenance job that consumed the stock, if any.
     *
     * @return BelongsTo<MaintenanceJob, $this>
     */
    public function maintenanceJob(): BelongsTo
    {
        return $this->belongsTo(MaintenanceJob::class);
    }

    /**
     * The user who recorded the movement.
     *
     * @return BelongsTo<User, $this>
     */
    public function mover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moved_by');
    }
}
