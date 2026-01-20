<?php

namespace App\Models;

use Database\Factories\BillingCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property numeric-string $late_fee_percent
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, TariffTier> $tiers
 * @property-read Collection<int, WaterAccount> $waterAccounts
 */
#[Fillable(['name', 'description', 'late_fee_percent', 'is_active'])]
class BillingCategory extends Model
{
    /** @use HasFactory<BillingCategoryFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'late_fee_percent' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the tariff slabs for the category, ordered from the lowest.
     *
     * @return HasMany<TariffTier, $this>
     */
    public function tiers(): HasMany
    {
        return $this->hasMany(TariffTier::class)->orderBy('lower_units');
    }

    /**
     * Get the water accounts billed under the category.
     *
     * @return HasMany<WaterAccount, $this>
     */
    public function waterAccounts(): HasMany
    {
        return $this->hasMany(WaterAccount::class);
    }
}
