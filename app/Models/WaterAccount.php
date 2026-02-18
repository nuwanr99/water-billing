<?php

namespace App\Models;

use App\Enums\WaterAccountStatus;
use Database\Factories\WaterAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
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
 * @property int|null $billing_category_id
 * @property string $account_number
 * @property string $meter_number
 * @property numeric-string $initial_reading
 * @property string|null $connection_address
 * @property WaterAccountStatus $status
 * @property Carbon|null $connected_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $owner
 * @property-read BillingCategory|null $billingCategory
 * @property-read Collection<int, MeterReading> $readings
 * @property-read MeterReading|null $latestReading
 * @property-read Collection<int, AccountLedgerEntry> $ledgerEntries
 * @property-read WaterAccountBalance|null $balanceRecord
 * @property-read Collection<int, Bill> $bills
 * @property-read Bill|null $latestCurrentBill
 */
#[Fillable(['user_id', 'billing_category_id', 'account_number', 'meter_number', 'initial_reading', 'connection_address', 'status', 'connected_at'])]
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
     * Get the billing category whose tariff applies to the account.
     *
     * @return BelongsTo<BillingCategory, $this>
     */
    public function billingCategory(): BelongsTo
    {
        return $this->belongsTo(BillingCategory::class);
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
     * Get the account's ledger statement in posting order.
     *
     * @return HasMany<AccountLedgerEntry, $this>
     */
    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(AccountLedgerEntry::class)->orderBy('id');
    }

    /**
     * Get the account's cached ledger balance row.
     *
     * @return HasOne<WaterAccountBalance, $this>
     */
    public function balanceRecord(): HasOne
    {
        return $this->hasOne(WaterAccountBalance::class);
    }

    /**
     * Get the bills issued for the account.
     *
     * @return HasMany<Bill, $this>
     */
    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }

    /**
     * Get the newest current (non-superseded) bill — its water_charge entry
     * is the cutoff the next bill presents entries from (D-25).
     *
     * @return HasOne<Bill, $this>
     */
    public function latestCurrentBill(): HasOne
    {
        return $this->hasOne(Bill::class)->ofMany(
            ['account_ledger_entry_id' => 'max'],
            fn (Builder $query) => $query->where('is_current', true),
        );
    }

    /**
     * The meter value the next reading is measured against: the latest
     * reading, or the initial (baseline) reading when none exists.
     */
    public function previousMeterValue(): float
    {
        return (float) ($this->latestReading->reading_value ?? $this->initial_reading);
    }
}
