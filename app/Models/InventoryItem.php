<?php

namespace App\Models;

use Database\Factories\InventoryItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A stocked material the society keeps on hand (M7). `quantity_in_stock` is
 * the authoritative running count, updated transactionally with each
 * movement — never re-summed from history.
 *
 * @property int $id
 * @property string $name
 * @property string $unit
 * @property numeric-string $unit_rate
 * @property int $quantity_in_stock
 * @property int $reorder_level
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, StockMovement> $movements
 */
#[Fillable(['name', 'unit', 'unit_rate', 'quantity_in_stock', 'reorder_level', 'is_active'])]
class InventoryItem extends Model
{
    /** @use HasFactory<InventoryItemFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_rate' => 'decimal:2',
            'quantity_in_stock' => 'integer',
            'reorder_level' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * The item's movement history, newest first.
     *
     * @return HasMany<StockMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class)->latest('moved_at');
    }

    /**
     * Whether the on-hand count has fallen to or below the reorder level.
     */
    public function isLowStock(): bool
    {
        return $this->quantity_in_stock <= $this->reorder_level;
    }
}
