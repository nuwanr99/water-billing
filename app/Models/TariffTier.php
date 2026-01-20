<?php

namespace App\Models;

use Database\Factories\TariffTierFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A tariff slab covering the half-open consumption range
 * (lower_units, upper_units]; a null upper_units is the open-ended top slab.
 *
 * @property int $id
 * @property int $billing_category_id
 * @property int $lower_units
 * @property int|null $upper_units
 * @property numeric-string $rate_per_unit
 * @property numeric-string $service_charge
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read BillingCategory $billingCategory
 */
#[Fillable(['billing_category_id', 'lower_units', 'upper_units', 'rate_per_unit', 'service_charge'])]
class TariffTier extends Model
{
    /** @use HasFactory<TariffTierFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rate_per_unit' => 'decimal:2',
            'service_charge' => 'decimal:2',
        ];
    }

    /**
     * Get the billing category the slab belongs to.
     *
     * @return BelongsTo<BillingCategory, $this>
     */
    public function billingCategory(): BelongsTo
    {
        return $this->belongsTo(BillingCategory::class);
    }
}
