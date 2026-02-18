<?php

namespace App\Models;

use App\Enums\BillStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The customer-facing statement document (D-26): a frozen snapshot generated
 * at reading confirmation. Monetary columns never change after generation —
 * only status transitions (paid/overdue) and supersession (is_current → NULL
 * on reissue, D-20/D-21) are written afterwards.
 *
 * @property int $id
 * @property string $bill_number
 * @property int $water_account_id
 * @property int $meter_reading_id
 * @property string $billing_month
 * @property bool|null $is_current
 * @property numeric-string $usage_charge
 * @property numeric-string $service_charge
 * @property numeric-string $monthly_charge
 * @property numeric-string $previous_balance
 * @property numeric-string $total_due
 * @property array<string, mixed> $breakdown
 * @property BillStatus $status
 * @property Carbon $due_date
 * @property Carbon $approved_at
 * @property int $generated_by
 * @property int $account_ledger_entry_id
 * @property bool $is_reissue
 * @property int|null $supersedes_bill_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read WaterAccount $waterAccount
 * @property-read MeterReading $meterReading
 * @property-read User $generator
 * @property-read AccountLedgerEntry $ledgerEntry
 * @property-read Bill|null $supersededBill
 */
#[Fillable(['bill_number', 'water_account_id', 'meter_reading_id', 'billing_month', 'is_current', 'usage_charge', 'service_charge', 'monthly_charge', 'previous_balance', 'total_due', 'breakdown', 'status', 'due_date', 'approved_at', 'generated_by', 'account_ledger_entry_id', 'is_reissue', 'supersedes_bill_id'])]
class Bill extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_current' => 'boolean',
            'usage_charge' => 'decimal:2',
            'service_charge' => 'decimal:2',
            'monthly_charge' => 'decimal:2',
            'previous_balance' => 'decimal:2',
            'total_due' => 'decimal:2',
            'breakdown' => 'array',
            'status' => BillStatus::class,
            'due_date' => 'date',
            'approved_at' => 'datetime',
            'is_reissue' => 'boolean',
        ];
    }

    /**
     * Get the water account the bill was issued for.
     *
     * @return BelongsTo<WaterAccount, $this>
     */
    public function waterAccount(): BelongsTo
    {
        return $this->belongsTo(WaterAccount::class);
    }

    /**
     * Get the meter reading the bill is based on.
     *
     * @return BelongsTo<MeterReading, $this>
     */
    public function meterReading(): BelongsTo
    {
        return $this->belongsTo(MeterReading::class);
    }

    /**
     * Get the user who confirmed the bill (Water Controller or reissuing admin).
     *
     * @return BelongsTo<User, $this>
     */
    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    /**
     * Get the bill's water_charge ledger entry — its cutoff marker (D-25).
     *
     * @return BelongsTo<AccountLedgerEntry, $this>
     */
    public function ledgerEntry(): BelongsTo
    {
        return $this->belongsTo(AccountLedgerEntry::class, 'account_ledger_entry_id');
    }

    /**
     * Get the bill this one replaced, when it is a reissue (D-20).
     *
     * @return BelongsTo<Bill, $this>
     */
    public function supersededBill(): BelongsTo
    {
        return $this->belongsTo(Bill::class, 'supersedes_bill_id');
    }
}
