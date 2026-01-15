<?php

namespace App\Models;

use App\Enums\WaterAccountStatus;
use Database\Factories\WaterAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $account_number
 * @property string $meter_number
 * @property numeric-string $initial_reading
 * @property string|null $connection_address
 * @property WaterAccountStatus $status
 * @property Carbon|null $connected_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $owner
 * @property-read Collection<int, MeterReading> $readings
 * @property-read MeterReading|null $latestReading
 */
#[Fillable(['user_id', 'account_number', 'meter_number', 'initial_reading', 'connection_address', 'status', 'connected_at'])]
class WaterAccount extends Model
{
    /** @use HasFactory<WaterAccountFactory> */
    use HasFactory;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => WaterAccountStatus::Active->value,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'initial_reading' => 'decimal:2',
            'status' => WaterAccountStatus::class,
            'connected_at' => 'date',
        ];
    }

    /**
     * Get the member who owns the water account.
     *
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the meter readings recorded for the account.
     *
     * @return HasMany<MeterReading, $this>
     */
    public function readings(): HasMany
    {
        return $this->hasMany(MeterReading::class);
    }

    /**
     * Get the most recent meter reading for the account.
     *
     * @return HasOne<MeterReading, $this>
     */
    public function latestReading(): HasOne
    {
        return $this->hasOne(MeterReading::class)->ofMany('billing_month', 'max');
    }

    /**
     * The meter value the next reading is measured against: the latest
     * reading, or the initial (baseline) reading when none exists.
     */
    public function previousMeterValue(): float
    {
        return (float) ($this->latestReading?->reading_value ?? $this->initial_reading);
    }
}
