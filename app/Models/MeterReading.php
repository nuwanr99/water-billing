<?php

namespace App\Models;

use Database\Factories\MeterReadingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $water_account_id
 * @property int $recorded_by
 * @property string $billing_month
 * @property numeric-string $reading_value
 * @property numeric-string $consumption
 * @property Carbon $reading_date
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read WaterAccount $waterAccount
 * @property-read User $recorder
 */
#[Fillable(['water_account_id', 'recorded_by', 'billing_month', 'reading_value', 'consumption', 'reading_date'])]
class MeterReading extends Model
{
    /** @use HasFactory<MeterReadingFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reading_value' => 'decimal:2',
            'consumption' => 'decimal:2',
            'reading_date' => 'date',
        ];
    }

    /**
     * Get the water account the reading belongs to.
     *
     * @return BelongsTo<WaterAccount, $this>
     */
    public function waterAccount(): BelongsTo
    {
        return $this->belongsTo(WaterAccount::class);
    }

    /**
     * Get the user who recorded the reading.
     *
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * Get the reading recorded before this one, if any.
     */
    public function previousReading(): ?MeterReading
    {
        return $this->waterAccount->readings()
            ->where('billing_month', '<', $this->billing_month)
            ->orderByDesc('billing_month')
            ->first();
    }

    /**
     * The meter value this reading is measured against: the previous
     * reading, or the account's initial (baseline) reading.
     */
    public function previousValue(): float
    {
        return (float) ($this->previousReading()->reading_value
            ?? $this->waterAccount->initial_reading);
    }
}
